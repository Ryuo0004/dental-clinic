<?php
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include 'admin_init.php';
} else {
  include 'db_connect.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

$settingsAlert = '';

// Handle settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Logo upload
  if (isset($_POST['upload_logo']) && isset($_FILES['clinic_logo'])) {
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
    if (is_uploaded_file($_FILES['clinic_logo']['tmp_name'])) {
      $ext = strtolower(pathinfo($_FILES['clinic_logo']['name'], PATHINFO_EXTENSION));
      if (in_array($ext, ['png','jpg','jpeg','gif','webp'])) {
        $fname = 'clinic_logo_'.date('Ymd_His').'.'.$ext;
        $dest = $uploadDir . '/' . $fname;
        if (move_uploaded_file($_FILES['clinic_logo']['tmp_name'], $dest)) {
          $public = 'uploads/'.$fname;
          $conn->query("CREATE TABLE IF NOT EXISTS tbl_clinic_settings (id INT PRIMARY KEY AUTO_INCREMENT, setting_key VARCHAR(50) UNIQUE NOT NULL, setting_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
          $stmt = $conn->prepare("INSERT INTO tbl_clinic_settings (setting_key, setting_value) VALUES ('clinic_logo_url', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
          if ($stmt) { $stmt->bind_param('s', $public); $stmt->execute(); $stmt->close(); }
          $settingsAlert = '<div class="alert alert-success">✅ Clinic logo updated.</div>';
        } else {
          $settingsAlert = '<div class="alert alert-danger">❌ Failed to upload logo.</div>';
        }
      } else {
        $settingsAlert = '<div class="alert alert-danger">❌ Invalid image type. Use PNG, JPG, GIF, or WEBP.</div>';
      }
    }
  }

  // Database backup
  if (isset($_POST['backup_db'])) {
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="miles_backup_'.date('Ymd_His').'.sql"');
    $dump = "-- Miles Dental Clinic DB Backup \n-- Generated: ".date('c')."\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = [];
    $res = $conn->query('SHOW TABLES');
    while ($row = $res->fetch_array()) { $tables[] = $row[0]; }
    foreach ($tables as $table) {
      $createRes = $conn->query('SHOW CREATE TABLE `'.$conn->real_escape_string($table).'`');
      if ($createRow = $createRes->fetch_assoc()) {
        $dump .= "-- -----------------------------\n-- Table structure for `$table`\n-- -----------------------------\n";
        $dump .= $createRow['Create Table'].";\n\n";
      }
      $dataRes = $conn->query('SELECT * FROM `'.$conn->real_escape_string($table).'`');
      if ($dataRes && $dataRes->num_rows > 0) {
        $dump .= "-- Data for `$table`\n";
        while ($r = $dataRes->fetch_assoc()) {
          $cols = array_map(function($c){ return "`".$c."`"; }, array_keys($r));
          $vals = array_map(function($v) use ($conn){ return isset($v) ? "'".$conn->real_escape_string($v)."'" : 'NULL'; }, array_values($r));
          $dump .= 'INSERT INTO `'.$table.'` ('.implode(',', $cols).') VALUES ('.implode(',', $vals).");\n";
        }
        $dump .= "\n";
      }
    }
    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    echo $dump;
    exit();
  }

  // Database restore (basic)
  if (isset($_POST['restore_db']) && isset($_FILES['sql_file']) && is_uploaded_file($_FILES['sql_file']['tmp_name'])) {
    $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
    if ($sql) {
      if ($conn->multi_query($sql)) {
        // flush results
        while ($conn->more_results() && $conn->next_result()) { /* flush */ }
        $settingsAlert = '<div class="alert alert-success">✅ Database restored successfully.</div>';
      } else {
        $settingsAlert = '<div class="alert alert-danger">❌ Restore failed: '.htmlspecialchars($conn->error).'</div>';
      }
    }
  }

  // Admin user management
  if (isset($_POST['add_admin'])) {
    $aemail = trim($_POST['admin_email'] ?? '');
    $aname = trim($_POST['admin_name'] ?? '');
    $apass = $_POST['admin_password'] ?? '';
    if ($aemail && $aname && $apass) {
      $conn->query("CREATE TABLE IF NOT EXISTS tbl_admin (Admin_Id INT AUTO_INCREMENT PRIMARY KEY, Email VARCHAR(255) UNIQUE, Name VARCHAR(255), Password_hash VARCHAR(255))");
      $stmt = $conn->prepare('INSERT INTO tbl_admin (Email, Name, Password_hash) VALUES (?, ?, ?)');
      $hashed = password_hash($apass, PASSWORD_DEFAULT);
      if ($stmt) { $stmt->bind_param('sss', $aemail, $aname, $hashed); $stmt->execute(); $stmt->close(); $settingsAlert = '<div class="alert alert-success">✅ Admin added.</div>'; }
    }
  }
  if (isset($_POST['change_admin_password'])) {
    $aid = intval($_POST['change_admin_id'] ?? 0);
    $npw = $_POST['new_password'] ?? '';
    if ($aid > 0 && $npw !== '') {
      $stmt = $conn->prepare('UPDATE tbl_admin SET Password_hash = ? WHERE Admin_Id = ?');
      $hashed = password_hash($npw, PASSWORD_DEFAULT);
      if ($stmt) { $stmt->bind_param('si', $hashed, $aid); $stmt->execute(); $stmt->close(); $settingsAlert = '<div class="alert alert-success">✅ Password updated.</div>'; }
    }
  }
  if (isset($_POST['delete_admin'])) {
    $aid = intval($_POST['delete_admin_id'] ?? 0);
    if ($aid > 0) {
      $stmt = $conn->prepare('DELETE FROM tbl_admin WHERE Admin_Id = ?');
      if ($stmt) { $stmt->bind_param('i', $aid); $stmt->execute(); $stmt->close(); $settingsAlert = '<div class="alert alert-warning">Admin removed.</div>'; }
    }
  }

  // Dentist management handlers
  // Ensure dentist table exists
  $conn->query("CREATE TABLE IF NOT EXISTS tbl_dentist (
    Dentist_id INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255),
    Email VARCHAR(255) UNIQUE,
    Password VARCHAR(255),
    Specialization VARCHAR(100) NULL,
    Phone VARCHAR(20) NULL,
    Photo_Url VARCHAR(255) NULL,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");

  if (isset($_POST['add_dentist'])) {
    $dname = trim($_POST['dentist_name'] ?? '');
    $demail = trim($_POST['dentist_email'] ?? '');
    $dpass = $_POST['dentist_password'] ?? '';
    $dspec = trim($_POST['dentist_specialization'] ?? 'General Dentistry');
    $dphone = trim($_POST['dentist_phone'] ?? '');
    if ($dname && $demail && $dpass) {
      $hashed = password_hash($dpass, PASSWORD_DEFAULT);
      $stmt = $conn->prepare('INSERT INTO tbl_dentist (Name, Email, Password, Specialization, Phone) VALUES (?, ?, ?, ?, ?)');
      if ($stmt) { $stmt->bind_param('sssss', $dname, $demail, $hashed, $dspec, $dphone); $ok = $stmt->execute(); $stmt->close(); $settingsAlert = $ok ? '<div class="alert alert-success">✅ Dentist added.</div>' : '<div class="alert alert-danger">❌ Failed to add dentist (email may exist).</div>'; }
    }
  }
  if (isset($_POST['change_dentist_password'])) {
    $did = intval($_POST['change_dentist_id'] ?? 0);
    $npw = $_POST['new_dentist_password'] ?? '';
    if ($did > 0 && $npw !== '') {
      $hashed = password_hash($npw, PASSWORD_DEFAULT);
      $stmt = $conn->prepare('UPDATE tbl_dentist SET Password = ? WHERE Dentist_id = ?');
      if ($stmt) { $stmt->bind_param('si', $hashed, $did); $stmt->execute(); $stmt->close(); $settingsAlert = '<div class="alert alert-success">✅ Dentist password updated.</div>'; }
    }
  }
  if (isset($_POST['delete_dentist'])) {
    $did = intval($_POST['delete_dentist_id'] ?? 0);
    if ($did > 0) {
      $stmt = $conn->prepare('DELETE FROM tbl_dentist WHERE Dentist_id = ?');
      if ($stmt) { $stmt->bind_param('i', $did); $stmt->execute(); $stmt->close(); $settingsAlert = '<div class="alert alert-warning">Dentist removed.</div>'; }
    }
  }

  // Save general settings
  $clinicName = trim($_POST['clinic_name'] ?? '');
  $contactEmail = trim($_POST['contact_email'] ?? '');
  $phoneNumber = trim($_POST['phone_number'] ?? '');
  $address = trim($_POST['address'] ?? '');
  $clinicHours = trim($_POST['clinic_hours'] ?? '');
  $timezone = trim($_POST['timezone'] ?? '');
  $maxAppointmentsPerDay = intval($_POST['max_appointments_per_day'] ?? 20);
  $appointmentDuration = intval($_POST['appointment_duration'] ?? 60);
  $sessionTimeout = intval($_POST['session_timeout_minutes'] ?? 0);
  $afterHoursPhone = trim($_POST['after_hours_phone'] ?? '');
  $emergencyInstructions = trim($_POST['emergency_instructions'] ?? '');
  
  // Validate inputs
  if (empty($clinicName) || empty($contactEmail)) {
    $settingsAlert = '<div class="alert alert-danger">Clinic name and contact email are required.</div>';
  } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    $settingsAlert = '<div class="alert alert-danger">Please enter a valid email address.</div>';
  } else {
    // Save settings to database (create table if it doesn't exist)
    $createTable = $conn->query("CREATE TABLE IF NOT EXISTS tbl_clinic_settings (
      id INT PRIMARY KEY AUTO_INCREMENT,
      setting_key VARCHAR(50) UNIQUE NOT NULL,
      setting_value TEXT,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    if ($createTable) {
      $settings = [
        'clinic_name' => $clinicName,
        'contact_email' => $contactEmail,
        'phone_number' => $phoneNumber,
        'address' => $address,
        'clinic_hours' => $clinicHours,
        'timezone' => $timezone,
        'max_appointments_per_day' => $maxAppointmentsPerDay,
        'appointment_duration' => $appointmentDuration,
        'session_timeout_minutes' => $sessionTimeout,
        'after_hours_phone' => $afterHoursPhone,
        'emergency_instructions' => $emergencyInstructions
      ];
      
      $success = true;
      foreach ($settings as $key => $value) {
        $stmt = $conn->prepare("INSERT INTO tbl_clinic_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("sss", $key, $value, $value);
        if (!$stmt->execute()) {
          $success = false;
        }
        $stmt->close();
      }
      
      if ($success) {
        $settingsAlert = '<div class="alert alert-success">✅ Settings saved successfully!</div>';
      } else {
        $settingsAlert = '<div class="alert alert-danger">❌ Error saving settings. Please try again.</div>';
      }
    }
  }
}

// Ensure the clinic settings table exists
$createTable = $conn->query("CREATE TABLE IF NOT EXISTS tbl_clinic_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(50) UNIQUE NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Load current settings
$currentSettings = [];
$loadStmt = $conn->prepare("SELECT setting_key, setting_value FROM tbl_clinic_settings");
if ($loadStmt) {
  $loadStmt->execute();
  $result = $loadStmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
  }
  $loadStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Settings - Miles Dental Clinic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: Arial, sans-serif; margin: 0; }
    .layout { height: 100vh; }
    .content { padding: 16px; height: 100vh; overflow-y: auto; background: #ffffff; color: #111827; }
    .card { background: #ffffff; color: #111827; border: 1px solid #e5e7eb; }
    .form-text { color: #6b7280; }
  </style>
</head>
<body>
<div class="layout d-flex">
  <?php if (file_exists($baseDir . '/admin_sidebar.php')) { include 'admin_sidebar.php'; } else { ?>
  <div class="sidebar">
    <h3>Miles Dental</h3>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="admin_appointments.php">Appointments</a>
    <a href="admin_messages.php">Messages</a>
    <a href="admin_inventory.php">Inventory</a>
    <a href="preferences.php">Settings</a>
    <a href="logout.php">Logout</a>
  </div>
  <?php } ?>

  <div class="content flex-grow-1">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0">Clinic Settings</h2>
      <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
    </div>

    <?= $settingsAlert ?>

    <!-- Settings Form -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title mb-4">Miles Clinic Settings</h5>
        <form method="post" enctype="multipart/form-data">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Clinic Name *</label>
              <input type="text" name="clinic_name" value="<?= htmlspecialchars($currentSettings['clinic_name'] ?? 'Miles Dental Clinic') ?>" class="form-control" required>
            </div>
            
            <div class="col-md-6">
              <label class="form-label">Contact Email *</label>
              <input type="email" name="contact_email" value="<?= htmlspecialchars($currentSettings['contact_email'] ?? 'admin@milesdental.com') ?>" class="form-control" required>
            </div>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Phone Number</label>
              <input type="tel" name="phone_number" value="<?= htmlspecialchars($currentSettings['phone_number'] ?? '(555) 123-4567') ?>" class="form-control">
            </div>
            
            <div class="col-md-6">
              <label class="form-label">Timezone</label>
              <select name="timezone" class="form-control">
                <option value="Asia/Manila" <?= ($currentSettings['timezone'] ?? '') === 'Asia/Manila' ? 'selected' : '' ?>>Asia/Manila (Philippines)</option>
                <option value="UTC" <?= ($currentSettings['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                <option value="America/New_York" <?= ($currentSettings['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>America/New_York</option>
                <option value="America/Chicago" <?= ($currentSettings['timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>America/Chicago</option>
                <option value="America/Los_Angeles" <?= ($currentSettings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>America/Los_Angeles</option>
              </select>
            </div>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Clinic Logo (for branding)</label>
              <input type="file" name="clinic_logo" accept="image/*" class="form-control">
              <?php if (!empty($currentSettings['clinic_logo_url'])): ?>
                <div class="mt-2">
                  <img src="<?= htmlspecialchars($currentSettings['clinic_logo_url']) ?>" alt="Clinic Logo" style="max-height:60px;">
                </div>
              <?php endif; ?>
            </div>
            <div class="col-md-6 align-self-end">
              <button type="submit" name="upload_logo" class="btn btn-outline-primary">Upload Logo</button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Clinic Address</label>
            <textarea name="address" rows="3" class="form-control" placeholder="Enter clinic address"><?= htmlspecialchars($currentSettings['address'] ?? '') ?></textarea>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Clinic Hours</label>
            <input type="text" name="clinic_hours" value="<?= htmlspecialchars($currentSettings['clinic_hours'] ?? 'Monday - Sunday: 7:00 AM - 6:00 PM') ?>" class="form-control" placeholder="e.g., Monday - Sunday: 7:00 AM - 6:00 PM">
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Max Appointments Per Day</label>
              <input type="number" name="max_appointments_per_day" value="<?= htmlspecialchars($currentSettings['max_appointments_per_day'] ?? '20') ?>" min="1" max="100" class="form-control">
              <div class="form-text">Maximum number of appointments allowed per day</div>
            </div>
            
            <div class="col-md-6">
              <label class="form-label">Default Appointment Duration (minutes)</label>
              <input type="number" name="appointment_duration" value="<?= htmlspecialchars($currentSettings['appointment_duration'] ?? '60') ?>" min="15" max="180" class="form-control">
              <div class="form-text">Default duration for new appointments</div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Session Timeout (minutes)</label>
              <input type="number" name="session_timeout_minutes" value="<?= htmlspecialchars($currentSettings['session_timeout_minutes'] ?? '0') ?>" min="0" max="480" class="form-control">
              <div class="form-text">0 to disable auto-logout</div>
            </div>
            
            <div class="col-md-6">
              <label class="form-label">After Hours Phone</label>
              <input type="tel" name="after_hours_phone" value="<?= htmlspecialchars($currentSettings['after_hours_phone'] ?? '(555) 987-6543') ?>" class="form-control">
              <div class="form-text">Emergency contact number for after hours</div>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Emergency Instructions</label>
            <textarea name="emergency_instructions" rows="2" class="form-control" placeholder="Instructions for emergency situations"><?= htmlspecialchars($currentSettings['emergency_instructions'] ?? 'For severe pain, bleeding, or broken teeth, call immediately. Do not wait for regular office hours.') ?></textarea>
            <div class="form-text">Instructions shown to patients for emergency situations</div>
          </div>
          
          <div class="text-end pt-3 border-top">
            <button type="submit" class="btn btn-primary">Save Settings</button>
          </div>
        </form>
      </div>
    </div>
  
        
      
   
    <!-- Admin Account Management -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title mb-3">Admin Account Management</h5>
        <form method="post" class="row g-2 align-items-end">
          <div class="col-md-3"><label class="form-label">Name</label><input name="admin_name" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Email</label><input name="admin_email" type="email" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Password</label><input name="admin_password" type="text" class="form-control"></div>
          <div class="col-md-3"><button type="submit" name="add_admin" class="btn btn-primary w-100">Add Admin</button></div>
        </form>
        <hr>
        <?php $admins = []; $r = $conn->query('SELECT Admin_Id, Name, Email FROM tbl_admin'); if ($r) { while ($row = $r->fetch_assoc()) { $admins[] = $row; } } ?>
        <?php if (!empty($admins)): ?>
          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead><tr><th>Name</th><th>Email</th><th class="text-end">Actions</th></tr></thead>
              <tbody>
                <?php foreach ($admins as $a): ?>
                <tr>
                  <td><?= htmlspecialchars($a['Name']) ?></td>
                  <td><?= htmlspecialchars($a['Email']) ?></td>
                  <td class="text-end">
                    <form method="post" class="d-inline">
                      <input type="hidden" name="change_admin_id" value="<?= (int)$a['Admin_Id'] ?>">
                      <input type="text" name="new_password" placeholder="New password" class="form-control d-inline w-auto">
                      <button type="submit" name="change_admin_password" class="btn btn-sm btn-outline-primary ms-1">Change Password</button>
                    </form>
                    <form method="post" class="d-inline ms-2" onsubmit="return confirm('Remove admin?')">
                      <input type="hidden" name="delete_admin_id" value="<?= (int)$a['Admin_Id'] ?>">
                      <button type="submit" name="delete_admin" class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-muted">No admin users found.</div>
        <?php endif; ?>
      </div>
    </div>

</body>
</html>


