<?php 

ini_set('session.cookie_lifetime', 86400); 
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params([
  'lifetime' => 86400,
  'path' => '/',
  'secure' => false, 
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();
include 'db_connect.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';
  $user_type = 'admin'; 

  $stmt = $conn->prepare("SELECT * FROM tbl_admin WHERE Email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $admin = $result->fetch_assoc();

    $stored = $admin['Password_hash'] ?? '';
    $ok = password_verify($password, $stored);
    if (!$ok && $stored !== '' && strpos((string)$stored, '$2y$') !== 0 && $password === $stored) {
      $newHash = password_hash($password, PASSWORD_DEFAULT);
      $aid = (int)($admin['Admin_Id'] ?? 0);
      if ($aid > 0 && ($upd = $conn->prepare("UPDATE tbl_admin SET Password_hash = ? WHERE Admin_Id = ?"))) {
        $upd->bind_param("si", $newHash, $aid);
        $upd->execute();
        $upd->close();
      }
      $ok = true;
    }
    if ($ok) {
      $_SESSION['admin_logged_in'] = true;
      $_SESSION['admin_email'] = $admin['Email'];
      $_SESSION['admin_name'] = $admin['Name'];
      // Persist admin id for notifications and access control
      if (!empty($admin['Admin_Id'])) { $_SESSION['admin_id'] = (int)$admin['Admin_Id']; }
      header("Location: admin_dashboard.php");
      exit();
    } else {
      $error = "Incorrect password.";
    }
  } else {
    $error = "Admin not found.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login - Miles Dental Clinic</title>
  <style>
    :root {
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --bg: #f3f4f6;
      --card: #ffffff;
      --text: #111827;
      --muted: #6b7280;
      --error: #dc2626;
      --border: #e5e7eb;
      --shadow: 0 10px 25px -10px rgba(0,0,0,0.15);
    }
    * { box-sizing: border-box; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: radial-gradient(1200px 600px at 20% -10%, #dbeafe 0%, transparent 60%),
                  radial-gradient(1000px 500px at 120% 110%, #fee2e2 0%, transparent 60%),
                  var(--bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      color: var(--text);
    }
    .login-box {
      width: 100%;
      max-width: 420px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      box-shadow: var(--shadow);
      padding: 2rem;
    }
    .brand {
      display: flex; align-items: center; justify-content: center; gap: .5rem; margin-bottom: .75rem;
      color: var(--primary); font-weight: 800; letter-spacing: .2px;
    }
    h2 { text-align: center; margin: 0 0 .5rem; color: var(--text); }
    .subtitle { text-align: center; color: var(--muted); margin-bottom: 1.5rem; font-size: .95rem; }
    .field { margin-bottom: 1rem; }
    .label { display: block; font-size: .9rem; color: var(--muted); margin-bottom: .35rem; }
    .input {
      width: 100%; padding: .75rem .9rem; border: 1px solid var(--border); border-radius: .6rem; outline: none; background: #fff;
      transition: border-color .2s, box-shadow .2s;
    }
    .input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
    .btn {
      width: 100%; padding: .8rem 1rem; border: none; border-radius: .6rem; background: var(--primary); color: #fff; font-weight: 600;
      cursor: pointer; transition: background .2s, transform .05s;
    }
    .btn:hover { background: var(--primary-dark); }
    .btn:active { transform: translateY(1px); }
    .error { color: var(--error); text-align: center; margin: .5rem 0 1rem; }
    .footer { margin-top: 1rem; text-align: center; color: var(--muted); font-size: .85rem; }
  </style>
</head>
<body>

<div class="login-box">
  <div class="brand">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    Miles Dental Clinic
  </div>
  <h2>Admin Login</h2>
  <div class="subtitle">Sign in to manage appointments and messages</div>
  <?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>
  <form method="POST">
    <div class="field">
      <label class="label" for="email">Email</label>
      <input class="input" id="email" type="email" name="email" placeholder="admin@example.com" required>
    </div>
    <div class="field">
      <label class="label" for="password">Password</label>
      <input class="input" id="password" type="password" name="password" placeholder="••••••••" required>
    </div>
    <button class="btn" type="submit">Login</button>
  </form>
  <div class="footer">© <?= date('Y') ?> Miles Dental Clinic</div>
</div>

</body>
</html>
