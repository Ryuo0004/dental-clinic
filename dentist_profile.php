<?php
session_start();
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include $baseDir . '/admin_init.php';
} else {
  include $baseDir . '/db_connect.php';
}
$err = $ok = '';

// Require login context
$dentistId = (int)($_SESSION['dentist_id'] ?? 0);
$dentistEmail = (string)($_SESSION['dentist_email'] ?? '');
if ($dentistId <= 0 && $dentistEmail === '') {
  header('Location: login.php');
  exit;
}

// Ensure extra columns exist
if (isset($conn)) {
  $conn->query("CREATE TABLE IF NOT EXISTS tbl_dentist (
    Dentist_id INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255),
    Email VARCHAR(255) UNIQUE,
    Password VARCHAR(255),
    Specialization VARCHAR(100) NULL,
    Phone VARCHAR(20) NULL,
    Photo_Url VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  foreach (['Specialization VARCHAR(100) NULL','Phone VARCHAR(20) NULL','Photo_Url VARCHAR(255) NULL','is_active TINYINT(1) NOT NULL DEFAULT 1'] as $def) {
    if (preg_match('/^(\w+)\s+/', $def, $m)) {
      $col = $m[1];
      $chk = $conn->query("SHOW COLUMNS FROM tbl_dentist LIKE '".$conn->real_escape_string($col)."'");
      if ($chk && $chk->num_rows === 0) { $conn->query("ALTER TABLE tbl_dentist ADD COLUMN $def"); }
    }
  }
}

// Load current profile
$profile = [
  'Name' => $_SESSION['dentist_name'] ?? '',
  'Email' => $dentistEmail,
  'Specialization' => 'General Dentistry',
  'Phone' => '',
  'Photo_Url' => '',
  'is_active' => 1,
];
if (isset($conn)) {
  if ($dentistId > 0) {
    $stmt = $conn->prepare('SELECT Name, Email, Specialization, Phone, Photo_Url, COALESCE(is_active,1) AS is_active FROM tbl_dentist WHERE Dentist_id = ? LIMIT 1');
    if ($stmt) { $stmt->bind_param('i', $dentistId); $stmt->execute(); $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) { $profile = array_merge($profile, $row); } $stmt->close(); }
  } elseif ($dentistEmail !== '') {
    $stmt = $conn->prepare('SELECT Dentist_id, Name, Email, Specialization, Phone, Photo_Url, COALESCE(is_active,1) AS is_active FROM tbl_dentist WHERE Email = ? LIMIT 1');
    if ($stmt) { $stmt->bind_param('s', $dentistEmail); $stmt->execute(); $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) { $dentistId = (int)$row['Dentist_id']; $_SESSION['dentist_id'] = $dentistId; $profile = array_merge($profile, $row); } $stmt->close(); }
  }
}

// Handle profile save (name, email, specialization, phone, photo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($conn) && !isset($_POST['change_password'])) {
  $name = trim($_POST['name'] ?? '');
  $spec = trim($_POST['specialization'] ?? 'General Dentistry');
  $phone = trim($_POST['phone'] ?? '');
  // keep only digits and validate length for PH mobile
  $phone = preg_replace('/\D+/', '', $phone);
  $emailNew = trim($_POST['email'] ?? $dentistEmail);
  $newPhotoUrl = null;

  // Basic validations
  if ($name === '') { $err = 'Full Name is required.'; }
  if ($err === '' && $emailNew === '') { $err = 'Email is required.'; }
  if ($err === '' && !filter_var($emailNew, FILTER_VALIDATE_EMAIL)) { $err = 'Please enter a valid email address.'; }
  if ($err === '' && $phone !== '' && !preg_match('/^09\d{9}$/', $phone)) { $err = 'Phone must be 11 digits and start with 09.'; }
  // Check email uniqueness if changed
  if ($err === '' && strcasecmp($emailNew, $dentistEmail) !== 0) {
    if ($chk = $conn->prepare('SELECT Dentist_id FROM tbl_dentist WHERE Email = ? AND Dentist_id <> ? LIMIT 1')) {
      $chk->bind_param('si', $emailNew, $dentistId);
      $chk->execute();
      $res = $chk->get_result();
      if ($res && $res->num_rows > 0) { $err = 'This email is already in use by another account.'; }
      $chk->close();
    }
  }
  if ($err === '') {
    // Handle photo upload
    if (isset($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
      $file = $_FILES['photo'];
      $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
      $mime = mime_content_type($file['tmp_name']);
      $sizeOk = $file['size'] <= 2*1024*1024;
      if (!isset($allowed[$mime])) { $err = 'Invalid image type. Use JPG, PNG, or WEBP.'; }
      elseif (!$sizeOk) { $err = 'Image too large. Max 2MB.'; }
      else {
        $ext = $allowed[$mime];
        $safe = preg_replace('/[^a-zA-Z0-9]/','_', $dentistEmail ?: ('dentist_'.$dentistId));
        $dir = __DIR__.'/uploads/dentist_photos'; if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $fname = $safe.'_'.time().'.'.$ext; $abs = $dir.'/'.$fname; $rel = 'uploads/dentist_photos/'.$fname;
        if (move_uploaded_file($file['tmp_name'], $abs)) { $newPhotoUrl = $rel; }
        else { $err = 'Failed to upload image.'; }
      }
    }
  }

  if ($err === '') {
    if ($dentistId > 0) {
      if ($newPhotoUrl !== null) {
        $stmt = $conn->prepare('UPDATE tbl_dentist SET Name = ?, Email = ?, Specialization = ?, Phone = ?, Photo_Url = ? WHERE Dentist_id = ?');
        if ($stmt) { $stmt->bind_param('sssssi', $name, $emailNew, $spec, $phone, $newPhotoUrl, $dentistId); $stmt->execute(); $stmt->close(); }
        $profile['Photo_Url'] = $newPhotoUrl;
      } else {
        $stmt = $conn->prepare('UPDATE tbl_dentist SET Name = ?, Email = ?, Specialization = ?, Phone = ? WHERE Dentist_id = ?');
        if ($stmt) { $stmt->bind_param('ssssi', $name, $emailNew, $spec, $phone, $dentistId); $stmt->execute(); $stmt->close(); }
      }
    } else {
      // Insert if not present
      $stmt = $conn->prepare('INSERT INTO tbl_dentist (Name, Email, Specialization, Phone, Photo_Url) VALUES (?, ?, ?, ?, ?)');
      if ($stmt) { $stmt->bind_param('sssss', $name, $emailNew, $spec, $phone, $newPhotoUrl); $stmt->execute(); $dentistId = $conn->insert_id; $_SESSION['dentist_id']=$dentistId; $stmt->close(); }
    }
    $_SESSION['dentist_name'] = $name;
    $_SESSION['dentist_email'] = $emailNew;
    $dentistEmail = $emailNew;
    $profile['Name'] = $name; $profile['Email'] = $emailNew; $profile['Specialization'] = $spec; $profile['Phone'] = $phone; $ok = 'Profile saved.';
  }
}

// Handle change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']) && isset($conn)) {
  $current = trim($_POST['current_password'] ?? '');
  $new = trim($_POST['new_password'] ?? '');
  $confirm = trim($_POST['confirm_password'] ?? '');
  if ($new !== $confirm) {
    $err = 'New passwords do not match.';
  } elseif (strlen($new) < 6) {
    $err = 'Password must be at least 6 characters.';
  } else {
    // Fetch current password
    $q = $conn->prepare('SELECT Password FROM tbl_dentist WHERE Dentist_id = ? LIMIT 1');
    if ($q) {
      $q->bind_param('i', $dentistId);
      $q->execute();
      $res = $q->get_result();
      if ($row = $res->fetch_assoc()) {
        $stored = (string)($row['Password'] ?? '');
        $valid = password_verify($current, $stored);
        // Legacy plaintext support
        if (!$valid && $stored !== '' && strpos($stored, '$2y$') !== 0 && $current === $stored) {
          $valid = true;
        }
        if (!$valid) {
          $err = 'Current password is incorrect.';
        } else {
          $newHash = password_hash($new, PASSWORD_DEFAULT);
          if ($up = $conn->prepare('UPDATE tbl_dentist SET Password = ? WHERE Dentist_id = ?')) {
            $up->bind_param('si', $newHash, $dentistId);
            if ($up->execute()) { $ok = 'Password changed successfully.'; }
            else { $err = 'Could not update password. Please try again.'; }
            $up->close();
          }
        }
      }
      $q->close();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dentist Profile</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="dentists.css" />
  <style>
    :root {
      --primary:#1d4ed8; --primary-dark:#1e3a8a; --primary-light:#93c5fd;
      --bg:#f8fafc; --card:#ffffff; --card-muted:#ffffff;
      --text:#0f172a; --text-muted:#475569; --border:rgba(29,78,216,0.12);
      --shadow:0 6px 16px rgba(29,78,216,0.06), 0 1px 2px rgba(0,0,0,0.03);
      --shadow-lg:0 18px 28px -8px rgba(29,78,216,0.25), 0 10px 12px -6px rgba(0,0,0,0.12);
    }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text); }
    .card { background: var(--card); border-radius:16px; border:1px solid var(--border); box-shadow:var(--shadow); transition:all .3s ease; }
    .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
    .label { font-weight:600; color:#1d4ed8; margin-bottom:6px; display:block; }
    .input-field { background: var(--card-muted); color: var(--text); border:1px solid var(--border); border-radius:12px; padding:12px 14px; transition: all .2s ease; width:100%; }
    .input-field::placeholder { color: var(--text-muted); }
    .input-field:focus { border-color: rgba(29,78,216,.7); box-shadow: 0 0 0 3px rgba(29,78,216,.15); outline:none; }
    .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color:#ffffff; border:none; border-radius:12px; padding:10px 20px; font-weight:700; box-shadow:0 4px 14px 0 rgba(29,78,216,.25); }
    .btn-primary:hover { transform: translateY(-1px); box-shadow:0 6px 20px 0 rgba(29,78,216,.35); }
    .avatar { width:64px; height:64px; border-radius:9999px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); display:flex; align-items:center; justify-content:center; color:#ffffff; font-weight:800; }
    .badge-active { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; border-radius:9999px; padding:2px 8px; font-size:12px; font-weight:700; }
    .badge-inactive { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; border-radius:9999px; padding:2px 8px; font-size:12px; font-weight:700; }
    h1,h2 { color:#1d4ed8; }
    p, .help-text { color: var(--text-muted); }
  </style>
</head>
<body>
  <div class="flex min-h-screen">
    <?php include 'dentist_sidebar.php'; ?>
    <main class="flex-1 p-6 space-y-6 dentist-main">
      <h1 class="text-3xl font-bold">My Profile</h1>
      <?php if (!empty($ok)): ?>
        <div class="bg-teal-50 text-teal-800 border border-teal-200 rounded-lg px-4 py-3"><?= htmlspecialchars($ok) ?></div>
      <?php endif; ?>
      <?php if (!empty($err)): ?>
        <div class="bg-red-50 text-red-800 border border-red-200 rounded-lg px-4 py-3"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Overview -->
        <section class="card p-6 lg:col-span-1">
          <div class="text-center mb-4">
            <?php $photo = trim($profile['Photo_Url'] ?? ''); $nm = trim($profile['Name'] ?? ''); $initials = ''; if ($nm !== '') { $parts=preg_split('/\s+/', $nm); foreach ($parts as $p) { if ($p!=='') $initials .= strtoupper($p[0]); } $initials = substr($initials,0,2);} ?>
            <?php if ($photo): ?>
              <img src="<?= htmlspecialchars($photo) ?>" alt="Profile photo" class="mx-auto mb-3" style="width:80px;height:80px;border-radius:9999px;object-fit:cover;box-shadow:var(--shadow);">
            <?php else: ?>
              <div class="avatar mx-auto mb-3"><?= htmlspecialchars($initials ?: 'D') ?></div>
            <?php endif; ?>
            <h2 class="text-xl font-bold mb-1"><?= htmlspecialchars($profile['Name'] ?? '') ?></h2>
            <p class="text-sm mb-1"><?= htmlspecialchars($profile['Email'] ?? '') ?></p>
            <p class="text-sm text-slate-400">Specialization: <?= htmlspecialchars($profile['Specialization'] ?? 'General Dentistry') ?></p>
            <div class="mt-2">
              <?php if ((int)($profile['is_active'] ?? 1) === 1): ?>
                <span class="badge-active">Active</span>
              <?php else: ?>
                <span class="badge-inactive">Inactive</span>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <!-- Edit form -->
        <section class="card p-6 lg:col-span-2">
          <h2 class="text-2xl font-semibold mb-6 border-l-4 pl-4" style="border-color: var(--primary);">Edit Your Profile</h2>
          <form method="post" enctype="multipart/form-data" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label class="label">Full Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($profile['Name'] ?? '') ?>" class="input-field" required />
              </div>
              <div>
                <label class="label">Specialization</label>
                <input type="text" name="specialization" value="<?= htmlspecialchars($profile['Specialization'] ?? 'General Dentistry') ?>" class="input-field" />
              </div>
              <div>
                <label class="label">Contact Phone</label>
                <input type="tel" name="phone" maxlength="11" pattern="09[0-9]{9}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" value="<?= htmlspecialchars($profile['Phone'] ?? '') ?>" class="input-field" />
              </div>
              <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($profile['Email'] ?? $dentistEmail) ?>" class="input-field" required />
                <p class="text-xs help-text mt-1">Changing email updates your login. Must be unique.</p>
              </div>
              <div class="md:col-span-2">
                <label class="label">Profile Photo</label>
                <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="input-field" />
                <p class="text-xs help-text mt-1">JPG, PNG, or WEBP. Max 2MB.</p>
              </div>
            </div>
            <div class="flex items-center justify-end">
              <button class="btn-primary">Save</button>
            </div>
          </form>
        </section>
      </div>
    </main>
  </div>
</body>
</html>
