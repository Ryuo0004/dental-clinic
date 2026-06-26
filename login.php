<?php
// Ensure session cookie persists across refresh/navigation (applies to all roles)
ini_set('session.cookie_lifetime', 86400); // 1 day
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params([
  'lifetime' => 86400,
  'path' => '/',
  'secure' => false, // set true if using HTTPS
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();
include 'db_connect.php';
include 'settings_helper.php'; // Include settings helper
include 'mailer.php'; // For sending verification emails

$clinicBranding = getClinicBranding($conn); // Fetch clinic branding info

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $email = $_POST['email'];
  $password = $_POST['password'];
  $role = 'patient'; // Default role, can be adjusted if role selection is added back


  // Admin Login Check
  $stmt = $conn->prepare("SELECT * FROM tbl_admin WHERE Email = ?");
  if ($stmt) {
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result->num_rows === 1) {
          $user = $result->fetch_assoc();
          $stored = $user['Password_hash'] ?? '';
          $ok = password_verify($password, $stored);
          // Backward-compat: migrate legacy plaintext if it matches
          if (!$ok && $stored !== '' && strpos((string)$stored, '$2y$') !== 0 && $password === $stored) {
              $newHash = password_hash($password, PASSWORD_DEFAULT);
              $aid = (int)($user['Admin_Id'] ?? 0);
              if ($aid > 0 && ($upd = $conn->prepare("UPDATE tbl_admin SET Password_hash = ? WHERE Admin_Id = ?"))) {
                  $upd->bind_param("si", $newHash, $aid);
                  $upd->execute();
                  $upd->close();
              }
              $ok = true;
          }
          if ($ok) {
              $_SESSION['admin_logged_in'] = true;
              $_SESSION['admin_id'] = (int)($user['Admin_Id'] ?? 0);
              $_SESSION['admin_email'] = $user['Email'];
              $_SESSION['admin_name'] = $user['Name'];
              // Clear any existing patient session to prevent mismatched bell fetches
              unset($_SESSION['email']);
              unset($_SESSION['user']);
              header("Location: admin_dashboard.php");
              exit;
          }
      }
      $stmt->close();
  }

  // Dentist Login Check
  $stmt = $conn->prepare("SELECT * FROM tbl_dentist WHERE Email = ?");
  if ($stmt) {
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result->num_rows === 1) {
          $user = $result->fetch_assoc();
          $stored = $user['Password'] ?? '';
          $ok = password_verify($password, $stored);
          // Backward-compat: migrate legacy plaintext if it matches
          if (!$ok && $stored !== '' && strpos((string)$stored, '$2y$') !== 0 && $password === $stored) {
              $newHash = password_hash($password, PASSWORD_DEFAULT);
              $did = (int)($user['Dentist_id'] ?? 0);
              if ($did > 0 && ($upd = $conn->prepare("UPDATE tbl_dentist SET Password = ? WHERE Dentist_id = ?"))) {
                  $upd->bind_param("si", $newHash, $did);
                  $upd->execute();
                  $upd->close();
              }
              $ok = true;
          }
          if ($ok) {
              $_SESSION['dentist_id'] = (int)$user['Dentist_id'];
              $_SESSION['dentist_email'] = $user['Email'];
              $_SESSION['dentist_name'] = $user['Name'] ?? '';
              header("Location: dentist_dashboard.php");
              exit;
          }
      }
      $stmt->close();
  }

  // Patient Login Check
  $stmt = $conn->prepare("SELECT * FROM tbl_patient WHERE Email = ?");
  if ($stmt) {
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result->num_rows === 1) {
          $user = $result->fetch_assoc();
          $stored = $user['Password'] ?? '';
          $ok = password_verify($password, $stored);
          // Backward-compat: migrate legacy plaintext if it matches
          if (!$ok && $stored !== '' && strpos((string)$stored, '$2y$') !== 0 && $password === $stored) {
              $newHash = password_hash($password, PASSWORD_DEFAULT);
              $pid = (int)($user['Patient_Id'] ?? 0);
              if ($pid > 0 && ($upd = $conn->prepare("UPDATE tbl_patient SET Password = ? WHERE Patient_Id = ?"))) {
                  $upd->bind_param("si", $newHash, $pid);
                  $upd->execute();
                  $upd->close();
              }
              $ok = true;
          }
          
          // Check if account is verified and handle unverified accounts
          if ($ok) {
              $isVerified = (int)($user['Email_Verified'] ?? 1); // Default to verified for backward compatibility
              if (!$isVerified) {
                  // Store user data in session for verification page
                  $fullName = trim(($user['First_Name'] ?? '') . ' ' . ($user['Last_Name'] ?? ''));
                  
                  $_SESSION['unverified_user'] = [
                      'id' => $user['Patient_Id'],
                      'email' => $user['Email'],
                      'name' => $fullName
                  ];
                  
                  // Generate a new verification code
                  $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                  $expiresAt = date('Y-m-d H:i:s', time() + 15 * 60);
                  
                  // Update verification code in database
                  if ($upd2 = $conn->prepare("UPDATE tbl_patient SET Verification_Code = ?, Verification_Expires = ? WHERE Patient_Id = ?")) {
                      $upd2->bind_param("ssi", $code, $expiresAt, $user['Patient_Id']);
                      $upd2->execute();
                      $upd2->close();
                  }
                  
                  // Prepare and send verification email
                  $clinicName = $clinicBranding['name'] ?? 'Miles Dental Clinic';
                  $subject = "$clinicName: Verify Your Email";
                  
                  $htmlBody = "<p>Hello " . htmlspecialchars($fullName) . ",</p>"
                            . "<p>Thank you for registering with " . htmlspecialchars($clinicName) . ".</p>"
                            . "<p>Your verification code is:</p>"
                            . "<h2 style=\"letter-spacing:3px;\">$code</h2>"
                            . "<p>This code will expire in 15 minutes.</p>"
                            . "<p>If you did not create an account, please ignore this email.</p>";
                  
                  // Send the email
                  if (sendMail($user['Email'], $subject, $htmlBody, $fullName)) {
                      $_SESSION['verify_notice'] = 'A verification code has been sent to your email. Please check your inbox.';
                  } else {
                      $_SESSION['verify_notice'] = 'Failed to send verification email. Please try again.';
                  }
                  
                  header("Location: verify_email.php");
                  exit;
              }
              // If verified, log patient in
              $_SESSION['email'] = $user['Email'];
              $_SESSION['user'] = $user['First_name'] . ' ' . $user['Last_name'];
              header("Location: patient_dashboard.php");
              exit;
          }
      }
      $stmt->close();
  }
 // If none of the role checks matched, show a generic warning
  if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $error = 'Invalid email or password.';
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - <?= htmlspecialchars($clinicBranding['name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
        --primary-color: #4A5C9F;
        --secondary-color: #7887C4;
        --background-color: #F0F2F5;
        --form-background: #FFFFFF;
        --input-background: #F7F8FA;
        --text-color: #333;
        --light-text-color: #888;
        --white-color: #fff;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: var(--background-color);
        margin: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
        box-sizing: border-box;
    }

    .auth-container {
        background-color: var(--form-background);
        width: 100%;
        max-width: 400px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        position: relative;
    }

    .auth-header {
        background-color: var(--primary-color);
        color: var(--white-color);
        padding: 40px 30px;
        text-align: center;
        position: relative;
    }

    .auth-header::before {
        content: '';
        position: absolute;
        top: -20px;
        right: -30px;
        width: 120px;
        height: 120px;
        background-color: var(--secondary-color);
        border-radius: 30px;
        transform: rotate(45deg);
        opacity: 0.5;
    }

    .logo-container {
        margin-bottom: 15px;
    }

    .logo {
        max-height: 60px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--white-color);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .default-logo {
        font-size: 50px;
        color: var(--white-color);
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        border: 3px solid var(--white-color);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        margin: 0 auto;
    }

    .auth-header h2 {
        margin: 0 0 10px 0;
        font-size: 24px;
        color: var(--white-color);
    }

    .auth-header p {
        margin: 0;
        font-size: 14px;
        opacity: 0.8;
    }

    .auth-body {
        padding: 30px;
    }

    .input-group {
        position: relative;
        margin-bottom: 20px;
    }

    .input-group i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--light-text-color);
        z-index: 2;
    }

    .input-group input {
        width: 100%;
        padding: 12px 12px 12px 40px;
        border: 1px solid #E0E0E0;
        border-radius: 10px;
        background-color: #ffffff;
        font-size: 16px;
        box-sizing: border-box;
        transition: border-color 0.3s;
    }

    .input-group input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(74,92,159,0.2);
    }

    .form-options {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 14px;
        margin-bottom: 20px;
    }

    .form-options label {
        display: flex;
        align-items: center;
        color: var(--light-text-color);
        cursor: pointer;
    }

    .form-options input[type="checkbox"] {
        margin-right: 8px;
    }

    .form-options a {
        color: var(--primary-color);
        text-decoration: none;
    }

    .btn-submit {
        width: 100%;
        padding: 15px;
        border: none;
        border-radius: 10px;
        background-color: var(--primary-color);
        color: var(--white-color);
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .btn-submit:hover {
        background-color: var(--secondary-color);
    }

    .auth-footer {
        text-align: center;
        padding: 20px 30px;
        font-size: 14px;
        background-color: #F7F8FA;
    }

    .auth-footer a {
        color: var(--primary-color);
        font-weight: bold;
        text-decoration: none;
    }

    .error {
        color: #D32F2F;
        background-color: #FFCDD2;
        padding: 10px;
        border-radius: 5px;
        text-align: center;
        margin-bottom: 20px;
        font-size: 14px;
    }

    /* Mobile-only show/hide password */
    .toggle-pass { 
        display: none; 
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        border: none;
        background: transparent;
        color: #6b7280;
        font-size: 16px;
        padding: 8px;
        cursor: pointer;
        line-height: 1;
        align-items: center;
        justify-content: center;
        z-index: 3;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        transition: all 0.2s ease;
    }

    .toggle-pass:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }

    .toggle-pass:active {
        background-color: rgba(0, 0, 0, 0.1);
    }

    .toggle-pass .on { display: none; }
    .showing .on { display: inline; }
    .showing .off { display: none; }

    @media (max-width: 640px) {
      .input-group input { 
          padding-right: 50px; 
      }
      .toggle-pass.toggle-visible {
          display: inline-flex;
      }
    }
  </style>
</head>
<body>
  <div class="auth-container">
    <div class="auth-header">
      <div class="logo-container">
        <?php if (!empty($clinicBranding['logo_url'])): ?>
          <img src="<?= htmlspecialchars($clinicBranding['logo_url']) ?>" alt="Clinic Logo" class="logo">
        <?php else: ?>
          <i class="fas fa-tooth default-logo"></i>
        <?php endif; ?>
      </div>
      <h2>Welcome Back To Miles Clinic!</h2>
      <p>Log in to continue your journey to a brighter smile.</p>
    </div>

    <div class="auth-body">
      <?php if (!empty($error)): ?>
        <p class='error'><?= $error ?></p>
      <?php endif; ?>
      
      <form method="post" action="" id="loginForm">
        <div class="input-group">
          <i class="fas fa-envelope"></i>
          <input type="email" name="email" placeholder="Enter your Email here" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="input-group">
          <i class="fas fa-lock"></i>
          <input id="passwordInput" type="password" name="password" placeholder="Password" required>
          <button type="button" class="toggle-pass" id="togglePwd" aria-label="Show password" aria-controls="passwordInput">
            <span class="off"><i class="fas fa-eye"></i></span>
            <span class="on"><i class="fas fa-eye-slash"></i></span>
          </button>
        </div>
        <div class="form-options">
          <label><input type="checkbox" name="remember"> Remember me</label>
          <a href="forgot_password.php">Forgot Password?</a>
        </div>
        <button type="submit" class="btn-submit">Sign In</button>
      </form>
    </div>

    <div class="auth-footer">
      <p>Don't have an account? <a href="signup.php">Register Now</a></p>
    </div>
  </div>
  <script>
    (function(){
      var btn = document.getElementById('togglePwd');
      var input = document.getElementById('passwordInput');
      if (btn && input) {
        btn.addEventListener('click', function(){
          var isText = input.getAttribute('type') === 'text';
          input.setAttribute('type', isText ? 'password' : 'text');
          btn.classList.toggle('showing', !isText);
          btn.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');
        });

        var updateVis = function(){
          var show = !!input.value || document.activeElement === input;
          if (show) btn.classList.add('toggle-visible'); else btn.classList.remove('toggle-visible');
        };
        input.addEventListener('input', updateVis);
        input.addEventListener('focus', updateVis);
        input.addEventListener('blur', updateVis);
        updateVis();
      }
    })();
  </script>
</body>
</html>