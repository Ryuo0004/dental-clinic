<?php
session_start();

$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
    include $baseDir . '/admin_init.php';
} else {
    include $baseDir . '/db_connect.php';
}

// Redirect if not logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$alert = '';
// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32)); }
}
$alert = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $suffix = $_POST['suffix'] ?? '';
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $bday = $_POST['bday'] ?? '';
    $newPhotoUrl = null;
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $alert = '<div class="alert alert-error">❌ Invalid request. Please refresh the page and try again.</div>';
    } else {
   
    // Server-side phone validation: exactly 11 digits
    if (!preg_match('/^\d{11}$/', $phone)) {
        $alert = '<div class="alert alert-error">❌ Phone number must be exactly 11 digits.</div>';
    } else {
        // Check if bday column exists, if not create it
        $checkBdayColumn = $conn->query("SHOW COLUMNS FROM tbl_patient LIKE 'bday'");
        if ($checkBdayColumn->num_rows == 0) {
            $conn->query("ALTER TABLE tbl_patient ADD COLUMN bday DATE DEFAULT NULL");
        }
        
        // Check if Suffix column exists, if not create it
        $checkSuffixColumn = $conn->query("SHOW COLUMNS FROM tbl_patient LIKE 'Suffix'");
        if ($checkSuffixColumn->num_rows == 0) {
            $conn->query("ALTER TABLE tbl_patient ADD COLUMN Suffix VARCHAR(50) DEFAULT NULL");
        }

        // Ensure Gender column exists
        $checkGenderColumn = $conn->query("SHOW COLUMNS FROM tbl_patient LIKE 'Gender'");
        if ($checkGenderColumn->num_rows == 0) {
            $conn->query("ALTER TABLE tbl_patient ADD COLUMN Gender VARCHAR(20) DEFAULT NULL");
        }

        // Ensure photo_url column exists
        $checkPhotoColumn = $conn->query("SHOW COLUMNS FROM tbl_patient LIKE 'photo_url'");
        if ($checkPhotoColumn->num_rows == 0) {
            $conn->query("ALTER TABLE tbl_patient ADD COLUMN photo_url VARCHAR(255) DEFAULT NULL");
        }

        // Handle profile photo upload if provided
        if (isset($_FILES['profile_photo']) && is_uploaded_file($_FILES['profile_photo']['tmp_name'])) {
            $file = $_FILES['profile_photo'];
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = mime_content_type($file['tmp_name']);
            $sizeOk = $file['size'] <= 2 * 1024 * 1024; // 2MB
            if (!isset($allowed[$mime])) {
                $alert = '<div class="alert alert-error">❌ Invalid image type. Please upload JPG, PNG, or WEBP.</div>';
            } elseif (!$sizeOk) {
                $alert = '<div class="alert alert-error">❌ Image too large. Max size is 2MB.</div>';
            } else {
                $ext = $allowed[$mime];
                $safeEmail = preg_replace('/[^a-zA-Z0-9]/', '_', $email);
                $dir = __DIR__ . '/uploads/patient_photos';
                if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                $filename = $safeEmail . '_' . time() . '.' . $ext;
                $destAbs = $dir . '/' . $filename;
                $destRel = 'uploads/patient_photos/' . $filename; // store relative path
                if (move_uploaded_file($file['tmp_name'], $destAbs)) {
                    $newPhotoUrl = $destRel;
                } else {
                }
            }
        }
        
        // Build dynamic UPDATE to support multiple possible column names
        $present = [];
        if ($colRes = $conn->query("SHOW COLUMNS FROM tbl_patient")) {
            while ($c = $colRes->fetch_assoc()) { $present[$c['Field']] = true; }
            $colRes->close();
        }

        $sets = [];
        $vals = [];
        $types = '';

        // Always-update standard fields
        $sets[] = 'First_name = ?'; $vals[] = $first_name; $types .= 's';
        $sets[] = 'Middle_name = ?'; $vals[] = $middle_name; $types .= 's';
        $sets[] = 'Last_name = ?'; $vals[] = $last_name; $types .= 's';
        if (isset($present['Suffix'])) { $sets[] = 'Suffix = ?'; $vals[] = $suffix; $types .= 's'; }
        if (isset($present['Address'])) { $sets[] = 'Address = ?'; $vals[] = $address; $types .= 's'; }

        // Phone variants
        if (isset($present['Phone_num'])) { $sets[] = 'Phone_num = ?'; $vals[] = $phone; $types .= 's'; }
        if (isset($present['Phone'])) { $sets[] = 'Phone = ?'; $vals[] = $phone; $types .= 's'; }

        // Birthdate variants
        if (isset($present['bday'])) { $sets[] = 'bday = ?'; $vals[] = $bday; $types .= 's'; }
        if (isset($present['Birthdate'])) { $sets[] = 'Birthdate = ?'; $vals[] = $bday; $types .= 's'; }
        if (isset($present['Date_of_Birth'])) { $sets[] = 'Date_of_Birth = ?'; $vals[] = $bday; $types .= 's'; }

        // Photo if uploaded and column exists
        if ($newPhotoUrl !== null && isset($present['photo_url'])) { $sets[] = 'photo_url = ?'; $vals[] = $newPhotoUrl; $types .= 's'; }

        // Prepare and execute
        $sql = 'UPDATE tbl_patient SET ' . implode(', ', $sets) . ' WHERE Email = ?';
        $types .= 's';
        $vals[] = $email;

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            // mysqli bind_param requires references
            $bind = [];
            $bind[] = & $types;
            foreach ($vals as $i => $v) { $bind[] = & $vals[$i]; }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }

        if ($stmt && $stmt->execute()) {
            $alert = '<div class="alert alert-success">✅ Profile updated successfully!</div>';
            // Refresh patient data to show updated information
            $stmt2 = $conn->prepare("SELECT * FROM tbl_patient WHERE Email = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            if ($result2->num_rows > 0) {
                $patient = $result2->fetch_assoc();
            }
            $stmt2->close();
        } else {
            $alert = '<div class="alert alert-error">❌ Error updating profile: ' . $conn->error . '</div>';
        }
        if ($stmt) { $stmt->close(); }
    }
    }
}

// Handle change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Basic validations
    if ($new !== $confirm) {
        $alert = '<div class="alert alert-error">❌ New passwords do not match.</div>';
    } elseif (strlen($new) < 6) {
        $alert = '<div class="alert alert-error">❌ Password must be at least 6 characters.</div>';
    } else {
        // CSRF validation
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
            $alert = '<div class="alert alert-error">❌ Invalid request. Please refresh the page and try again.</div>';
        } else {
        // Fetch current password from DB
        $stmt = $conn->prepare("SELECT Password FROM tbl_patient WHERE Email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $dbPass = (string)$row['Password'];
                $valid = password_verify($current, $dbPass);
                // Backward-compat: if stored is plaintext and matches, treat as valid
                if (!$valid && $dbPass !== '' && strpos($dbPass, '$2y$') !== 0 && $current === $dbPass) {
                    $valid = true;
                }
                if (!$valid) {
                    $alert = '<div class="alert alert-error">❌ Current password is incorrect.</div>';
                } else {
                    $newHash = password_hash($new, PASSWORD_DEFAULT);
                    $up = $conn->prepare("UPDATE tbl_patient SET Password = ? WHERE Email = ?");
                    if ($up) {
                        $up->bind_param('ss', $newHash, $email);
                        if ($up->execute()) {
                            $alert = '<div class="alert alert-success">✅ Password changed successfully.</div>';
                        } else {
                            $alert = '<div class="alert alert-error">❌ Could not update password. Please try again.</div>';
                        }
                        $up->close();
                    }
                }
            }
            $stmt->close();
        }
        }
    }
}

// Handle deactivate account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_account'])) {
    // Check current status first; disallow changes if Blocked
    $cur = $conn->prepare("SELECT COALESCE(Status,'Active') AS S FROM tbl_patient WHERE Email = ? LIMIT 1");
    $cur->bind_param("s", $email);
    $cur->execute();
    $res = $cur->get_result();
    $currStatus = ($row = $res->fetch_assoc()) ? (string)$row['S'] : 'Active';
    $cur->close();
    if (strcasecmp($currStatus, 'Blocked') === 0) {
        $alert = '<div class="alert alert-error">❌ Your account is blocked by the clinic. You cannot change your status. Please contact the clinic.</div>';
    } else {
        $deact = $conn->prepare("UPDATE tbl_patient SET Status = 'Inactive' WHERE Email = ?");
        $deact->bind_param("s", $email);
        if ($deact->execute()) {
            $alert = '<div class="alert alert-warning">⚠️ Your account has been set to Inactive.</div>';
        } else {
            $alert = '<div class="alert alert-error">❌ Could not deactivate account. Please try again.</div>';
        }
        $deact->close();
    }
}

// Handle reactivate account (patient self-service)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_account'])) {
    // Optional: require CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $alert = '<div class="alert alert-error">❌ Invalid request. Please refresh and try again.</div>';
    } else {
        // Check current status first; disallow changes if Blocked
        $cur = $conn->prepare("SELECT COALESCE(Status,'Active') AS S FROM tbl_patient WHERE Email = ? LIMIT 1");
        $cur->bind_param("s", $email);
        $cur->execute();
        $res = $cur->get_result();
        $currStatus = ($row = $res->fetch_assoc()) ? (string)$row['S'] : 'Active';
        $cur->close();
        if (strcasecmp($currStatus, 'Blocked') === 0) {
            $alert = '<div class="alert alert-error">❌ Your account is blocked by the clinic. You cannot reactivate. Please contact the clinic.</div>';
        } else {
            $act = $conn->prepare("UPDATE tbl_patient SET Status = 'Active' WHERE Email = ?");
            $act->bind_param("s", $email);
            if ($act->execute()) {
                $alert = '<div class="alert alert-success">✅ Your account has been reactivated.</div>';
            } else {
                $alert = '<div class="alert alert-error">❌ Could not reactivate account. Please try again.</div>';
            }
            $act->close();
        }
    }
}

// Fetch patient data
$stmt = $conn->prepare("SELECT * FROM tbl_patient WHERE Email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "Patient not found.";
    exit();
}
$patient = $result->fetch_assoc();
$stmt->close();

// Derived values for a more engaging UI
$status = isset($patient['Status']) ? $patient['Status'] : 'Active';
$firstInitial = isset($patient['First_name'][0]) ? strtoupper($patient['First_name'][0]) : '';
$lastInitial = isset($patient['Last_name'][0]) ? strtoupper($patient['Last_name'][0]) : '';
$initials = $firstInitial . $lastInitial;
$photoUrl = trim($patient['photo_url'] ?? '');

// Simple profile completeness score
$fieldsToCheck = [
    trim((string)$patient['First_name']),
    trim((string)$patient['Last_name']),
    trim((string)$patient['Phone_num']),
    trim((string)$patient['Address']),
    trim((string)($patient['bday'] ?? ''))
];
$filled = 0;
foreach ($fieldsToCheck as $f) { if (!empty($f)) { $filled++; } }
$completeness = count($fieldsToCheck) > 0 ? intval(($filled / count($fieldsToCheck)) * 100) : 0;

$full_name = $patient['First_name'] . ' ' . $patient['Middle_name'] . ' ' . $patient['Last_name'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="patients.css" />
  <style>
    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }
    .flex-grow {
        flex-grow: 1;
    }
    :root { 
      --primary: #6366f1; 
      --primary-dark: #4f46e5; 
      --primary-light: #a5b4fc;
      --secondary: #f8fafc;
      --accent: #10b981;
      --danger: #ef4444;
      --warning: #f59e0b;
      --text-primary: #1f2937;
      --text-secondary: #6b7280;
      --border: #e5e7eb;
      --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
    
    .gradient-bg { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); }
    .glass-card { 
      background: rgba(255, 255, 255, 0.95); 
      backdrop-filter: blur(10px); 
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: var(--shadow-lg);
    }
    .card { 
      background: white; 
      border-radius: 16px; 
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
      transition: all 0.3s ease;
    }
    .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
    
    .btn-primary { 
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white; 
      border: none;
      border-radius: 12px;
      padding: 12px 24px;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 14px 0 rgba(99, 102, 241, 0.39);
    }
    .btn-primary:hover { 
      transform: translateY(-1px); 
      box-shadow: 0 6px 20px 0 rgba(99, 102, 241, 0.5);
    }
    
    .btn-secondary { 
      background: white; 
      color: var(--text-primary);
      border: 2px solid var(--border);
      border-radius: 12px;
      padding: 10px 24px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    .btn-secondary:hover { 
      border-color: var(--primary); 
      color: var(--primary);
      transform: translateY(-1px);
    }
    
    .btn-danger { 
      background: var(--danger); 
      color: white; 
      border: none;
      border-radius: 12px;
      padding: 10px 24px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    .btn-danger:hover { 
      background: #dc2626; 
      transform: translateY(-1px);
    }
    
    .input-field { 
      border: 2px solid var(--border); 
      border-radius: 12px; 
      padding: 14px 16px;
      font-size: 16px;
      transition: all 0.3s ease;
      background: white;
    }
    .input-field:focus { 
      border-color: var(--primary); 
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
      outline: none;
    }
    
    .label { 
      font-weight: 600; 
      color: var(--text-primary); 
      margin-bottom: 8px;
      display: block;
    }
    
    .avatar { 
      width: 64px; 
      height: 64px; 
      border-radius: 50%; 
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      display: flex; 
      align-items: center; 
      justify-content: center; 
      font-size: 24px; 
      font-weight: 700; 
      color: white;
      box-shadow: var(--shadow);
    }
    
    .progress-bar { 
      height: 8px; 
      background: #e5e7eb; 
      border-radius: 4px; 
      overflow: hidden;
    }
    .progress-fill { 
      height: 100%; 
      border-radius: 4px; 
      transition: width 0.5s ease;
    }
    
    .status-badge { 
      padding: 6px 16px; 
      border-radius: 20px; 
      font-size: 14px; 
      font-weight: 600;
    }
    .status-active { background: #dcfce7; color: #166534; }
    .status-inactive { background: #fef2f2; color: #991b1b; }
    
    .section-header { 
      border-left: 4px solid var(--primary); 
      padding-left: 16px; 
      margin-bottom: 24px;
    }
    
    .nav-link { 
      color: var(--text-secondary); 
      text-decoration: none; 
      font-weight: 500;
      padding: 8px 16px;
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    .nav-link:hover { 
      color: var(--primary); 
      background: rgba(99, 102, 241, 0.1);
    }
    .nav-link.active { 
      color: var(--primary); 
      background: rgba(99, 102, 241, 0.1);
      font-weight: 600;
    }
    
    .alert { 
      padding: 16px 20px; 
      border-radius: 12px; 
      margin-bottom: 24px;
      font-weight: 500;
    }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

    /* Compact overview card */
    .compact-card { padding: 12px !important; }
    .compact-card .avatar { width: 56px; height: 56px; font-size: 20px; }
    .compact-card h2 { font-size: 1.125rem; margin-bottom: 0.25rem; }
    .compact-card p { margin-bottom: 0.25rem; }
    .compact-card .status-badge { padding: 4px 12px; font-size: 12px; }
    .compact-card .progress-bar { height: 6px; }
    /* Reduce vertical gaps inside the compact card */
    .compact-card .space-y-6 > :not([hidden]) ~ :not([hidden]) {
      --tw-space-y-reverse: 0;
      margin-top: calc(1rem * calc(1 - var(--tw-space-y-reverse)));
      margin-bottom: calc(1rem * var(--tw-space-y-reverse));
    }
    .compact-card .text-lg { font-size: 0.95rem; }
    /* Responsive Sidebar Toggle */
    .hamburger { position: fixed; left: 12px; top: 12px; z-index: 11000; display: none; padding: 10px 12px; border:1px solid #e5e7eb; background:#ffffff; color:#111827; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .hamburger svg { width:20px; height:20px; }
    .backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:10900; opacity:0; transition:opacity .2s ease; }
    @media (max-width: 900px) {
      .hamburger { display: inline-flex; align-items:center; gap:8px; }
      /* Sidebar off-canvas */
      .sidebar { position: fixed; left:0; top:0; bottom:0; height:100vh; transform: translateX(-100%); box-shadow:0 8px 24px rgba(0,0,0,0.1); transition: transform .25s ease; }
      body.sidebar-open .sidebar { transform: translateX(0); }
      body.sidebar-open .backdrop { display:block; opacity:1; }
      /* Content full-width on mobile */
      .flex-grow, .content-wrapper { margin-left:0 !important; }
    }
  </style>
</head>
<body class="bg-gray-100">
    <button id="menuToggle" class="hamburger" aria-label="Toggle menu">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
      Menu
    </button>
    <div id="drawerBackdrop" class="backdrop"></div>
    <!-- Header removed (sidebar-only layout) -->
    <div class="flex flex-col md:flex-row flex-grow">
        <?php include 'patient_sidebar.php'; ?>
        <div class="flex-1 p-4 md:p-8">
            <h1 class="text-3xl font-bold mb-6">My Profile</h1>
            <?= $alert ?>
            <?php if (strcasecmp((string)$status, 'Blocked') === 0): ?>
              <div class="alert alert-error">❌ Your account is blocked by the clinic. Booking and status changes are disabled. Please contact the clinic.</div>
            <?php endif; ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
              <!-- Profile Overview Card (compact) -->
              <section class="card compact-card p-4 lg:col-span-1">
                <div class="text-center mb-4">
                  <?php if ($photoUrl): ?>
                    <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Profile photo" class="mx-auto mb-3" style="width: 80px; height: 80px; border-radius: 9999px; object-fit: cover; box-shadow: var(--shadow);">
                  <?php else: ?>
                    <div class="avatar mx-auto mb-3">
                      <?= htmlspecialchars($initials ?: 'U') ?>
                    </div>
                  <?php endif; ?>
                  <h2 class="text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($full_name) ?></h2>
                  <p class="text-gray-600 mb-1"><?= htmlspecialchars($patient['Email']) ?></p>
                  <?php if (!empty($patient['bday'])): ?>
                    <p class="text-sm text-gray-500">Born <?= date('F j, Y', strtotime($patient['bday'])) ?></p>
                  <?php endif; ?>
                  <span class="status-badge <?= $status === 'Active' ? 'status-active' : 'status-inactive' ?> mt-2 inline-block">
                    <?= htmlspecialchars($status) ?>
                  </span>
                </div>
                
                <div class="space-y-6">
                  <div>
                    <div class="flex items-center justify-between mb-2">
                      <span class="label text-sm">Profile Completeness</span>
                      <span class="text-sm font-semibold text-gray-700"><?= $completeness ?>%</span>
                    </div>
                    <div class="progress-bar">
                      <div class="progress-fill <?= $completeness >= 80 ? 'bg-green-500' : ($completeness >= 50 ? 'bg-yellow-500' : 'bg-red-500') ?>" style="width: <?= $completeness ?>%"></div>
                    </div>
                  </div>
                  
                  <div class="space-y-2">
                    <h3 class="font-semibold text-gray-800 text-sm">Profile Checklist</h3>
                    <div class="space-y-2 text-sm">
                      <div class="flex items-center space-x-2">
                        <span class="text-lg"><?= empty(trim($patient['Phone_num'])) ? '⭕' : '✅' ?></span>
                        <span class="<?= empty(trim($patient['Phone_num'])) ? 'text-gray-500' : 'text-gray-700' ?>">Phone number</span>
                      </div>
                      <div class="flex items-center space-x-2">
                        <span class="text-lg"><?= empty(trim($patient['Address'])) ? '⭕' : '✅' ?></span>
                        <span class="<?= empty(trim($patient['Address'])) ? 'text-gray-500' : 'text-gray-700' ?>">Address</span>
                      </div>
                      <div class="flex items-center space-x-2">
                        <span class="text-lg"><?= empty(trim($patient['bday'] ?? '')) ? '⭕' : '✅' ?></span>
                        <span class="<?= empty(trim($patient['bday'] ?? '')) ? 'text-gray-500' : 'text-gray-700' ?>">Birthday</span>
                      </div>
                    </div>
                  </div>
                </div>
              </section>

              <!-- Edit Form -->
              <section class="card p-4 md:p-6 lg:col-span-2">
                <h1 class="text-2xl font-semibold mb-8 section-header">Edit Your Profile</h1>
                <form method="post" action="profile.php" class="space-y-4 md:space-y-6" enctype="multipart/form-data">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                    <div class="md:col-span-2">
                      <label for="profile_photo" class="label">Profile Photo</label>
                      <input type="file" id="profile_photo" name="profile_photo" accept="image/png,image/jpeg,image/webp" class="input-field w-full">
                      <p class="text-xs text-gray-500 mt-2">JPG, PNG, or WEBP. Max 2MB.</p>
                    </div>
                  </div>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <label for="first_name" class="label">First Name</label>
                      <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($patient['First_name']) ?>" class="input-field w-full" required>
                    </div>
                    <div>
                      <label for="middle_name" class="label">Middle Name</label>
                      <input type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($patient['Middle_name']) ?>" class="input-field w-full">
                    </div>
                    <div>
                      <label for="last_name" class="label">Last Name</label>
                      <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($patient['Last_name']) ?>" class="input-field w-full" required>
                    </div>
                    <div>
                      <label for="suffix" class="label">Suffix</label>
                      <input type="text" id="suffix" name="suffix" value="<?= htmlspecialchars($patient['Suffix'] ?? '') ?>" class="input-field w-full" placeholder="Jr., Sr., III, etc.">
                    </div>
                    <div>
                      <label for="email" class="label">Email</label>
                      <input type="email" id="email" name="email" value="<?= htmlspecialchars($patient['Email']) ?>" class="input-field w-full bg-gray-100" readonly>
                      <p class="text-xs text-gray-500 mt-2">Email cannot be changed.</p>
                    </div>
                    <div>
                      <label for="bday" class="label">Date of Birth</label>
                      <input type="date" id="bday" name="bday" value="<?= htmlspecialchars($patient['bday'] ?? '') ?>" max="<?= date('Y-m-d') ?>" class="input-field w-full">
                    </div>
                    <div>
                      <label class="label">Gender</label>
                      <div class="input-field w-full bg-gray-100" style="pointer-events:none;">
                        <?= htmlspecialchars($patient['Gender'] ?? '—') ?>
                      </div>
                      <p class="text-xs text-gray-500 mt-2">Gender is set during signup and cannot be changed here.</p>
                    </div>
                    <div>
                      <label for="phone" class="label">Phone Number</label>
                      <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($patient['Phone_num']) ?>" maxlength="11" pattern="[0-9]{11}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')" class="input-field w-full" placeholder="11-digit number">
                      <p class="text-xs text-gray-500 mt-2">Exactly 11 digits.</p>
                    </div>
                    <div class="md:col-span-2">
                      <label for="address" class="label">Address</label>
                      <textarea id="address" name="address" rows="3" class="input-field w-full resize-none"><?= htmlspecialchars($patient['Address']) ?></textarea>
                    </div>
                  </div>
                  
                  <!-- Emergency Contact Section -->
                  <div class="border-t border-gray-200 pt-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 section-header">Emergency Contact</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                      <div>
                        <label for="emergency_name" class="label">Emergency Contact Name</label>
                        <input type="text" id="emergency_name" name="emergency_name" value="<?= htmlspecialchars($patient['emergency_name'] ?? '') ?>" class="input-field w-full" placeholder="Full name">
                      </div>
                      <div>
                        <label for="emergency_relationship" class="label">Relationship</label>
                        <select id="emergency_relationship" name="emergency_relationship" class="input-field w-full">
                          <option value="">Select relationship</option>
                          <option value="Spouse" <?= ($patient['emergency_relationship'] ?? '') === 'Spouse' ? 'selected' : '' ?>>Spouse</option>
                          <option value="Parent" <?= ($patient['emergency_relationship'] ?? '') === 'Parent' ? 'selected' : '' ?>>Parent</option>
                          <option value="Child" <?= ($patient['emergency_relationship'] ?? '') === 'Child' ? 'selected' : '' ?>>Child</option>
                          <option value="Sibling" <?= ($patient['emergency_relationship'] ?? '') === 'Sibling' ? 'selected' : '' ?>>Sibling</option>
                          <option value="Friend" <?= ($patient['emergency_relationship'] ?? '') === 'Friend' ? 'selected' : '' ?>>Friend</option>
                          <option value="Other" <?= ($patient['emergency_relationship'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                      </div>
                      <div>
                        <label for="emergency_phone" class="label">Emergency Contact Phone</label>
                        <input type="tel" id="emergency_phone" name="emergency_phone" value="<?= htmlspecialchars($patient['emergency_phone'] ?? '') ?>" maxlength="11" pattern="[0-9]{11}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')" class="input-field w-full" placeholder="11-digit number">
                        <p class="text-xs text-gray-500 mt-2">Exactly 11 digits.</p>
                      </div>
                    </div>
                  </div>
                  
                  <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 pt-6 border-t border-gray-200">
                    <?php $isBlockedProfile = (strtolower($status) === 'blocked'); ?>
                    <?php if (strtolower($status) !== 'inactive'): ?>
                      <form method="post" class="order-2 md:order-1" onsubmit="<?php if($isBlockedProfile){ ?>alert('Your account is blocked by the clinic. Status changes are disabled.'); return false;<?php } else { ?>return confirm('Are you sure you want to deactivate your account? You can contact the clinic to reactivate.');<?php } ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <button type="submit" name="deactivate_account" class="btn-danger w-full md:w-auto" <?= $isBlockedProfile ? 'disabled title="Blocked by clinic"' : '' ?>>Deactivate Account</button>
                      </form>
                    <?php else: ?>
                      <form method="post" class="order-2 md:order-1" onsubmit="<?php if($isBlockedProfile){ ?>alert('Your account is blocked by the clinic. Status changes are disabled.'); return false;<?php } else { ?>return confirm('Reactivate your account now?');<?php } ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <button type="submit" name="reactivate_account" class="btn-secondary w-full md:w-auto" <?= $isBlockedProfile ? 'disabled title="Blocked by clinic"' : '' ?>>Reactivate Account</button>
                      </form>
                    <?php endif; ?>
                    <button type="submit" name="update_profile" class="btn-primary w-full md:w-auto order-1 md:order-2">Save Changes</button>
                  </div>
                </form>
              </section>
            </div>
            <!-- Change Password -->
            <section class="card p-4 md:p-6 max-w-4xl mx-auto">
              <h2 class="text-xl font-semibold mb-6 section-header">Change Password</h2>
              <form method="post" action="profile.php" class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div>
                  <label class="label" for="current_password">Current Password</label>
                  <input id="current_password" name="current_password" type="password" class="input-field w-full" required>
                </div>
                <div>
                  <label class="label" for="new_password">New Password</label>
                  <input id="new_password" name="new_password" type="password" class="input-field w-full" required>
                </div>
                <div>
                  <label class="label" for="confirm_password">Confirm New Password</label>
                  <input id="confirm_password" name="confirm_password" type="password" class="input-field w-full" required>
                </div>
                <div class="md:col-span-3 text-right">
                  <button type="submit" name="change_password" class="btn-primary w-full md:w-auto">Update Password</button>
                </div>
              </form>
            </section>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <script>
      (function(){
        var btn=document.getElementById('menuToggle');
        var bd=document.getElementById('drawerBackdrop');
        function t(){ document.body.classList.toggle('sidebar-open'); }
        function c(){ document.body.classList.remove('sidebar-open'); }
        if(btn) btn.addEventListener('click', t);
        if(bd) bd.addEventListener('click', c);
        document.addEventListener('keydown', function(e){ if(e.key==='Escape') c(); });
      })();
    </script>
    <style>
      @media (max-width: 900px) {
        .sidebar { position: fixed; left:0; top:0; bottom:0; width: 250px; transform: translateX(-100%); transition: transform .25s ease; }
        body.sidebar-open .sidebar { transform: translateX(0); }
        .backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:10900; opacity:0; transition:opacity .2s ease; }
        body.sidebar-open .backdrop { display:block; opacity:1; }
      }
    </style>

</body>
</html>