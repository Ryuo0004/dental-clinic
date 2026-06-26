<?php
session_start();
include 'db_connect.php';
include 'settings_helper.php'; // For clinic branding

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$clinicBranding = getClinicBranding($conn);
$email = $_SESSION['reset_email'];
$message = "";
$message_type = 'info';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $message = "Both password fields are required.";
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = 'error';
    } elseif (
        strlen($new_password) < 8 ||
        !preg_match('/[A-Z]/', $new_password) ||
        !preg_match('/[0-9]/', $new_password) ||
        !preg_match('/[\W_]/', $new_password)
    ) {
        $message = "Password must be at least 8 characters, start with a capital, contain a number, and a special character.";
        $message_type = 'error';
    } else {
        // Secure: store hashed password
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE tbl_patient SET Password = ?, otp_code = NULL, otp_expiration = NULL WHERE Email = ?");
        $update->bind_param("ss", $hashedPassword, $email);

        if ($update->execute()) {
            $message = "Password has been reset successfully. Redirecting to login...";
            $message_type = 'success';
            unset($_SESSION['reset_email']);
            header("Refresh: 3; url=login.php");
        } else {
            $message = "Error resetting password. Please try again.";
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password - <?= htmlspecialchars($clinicBranding['name']) ?></title>
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
        width: auto;
    }

    .default-logo {
        font-size: 50px;
        color: var(--white-color);
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
    }

    .input-group input {
        width: 100%;
        padding: 12px 12px 12px 40px;
        border: 1px solid #E0E0E0;
        border-radius: 10px;
        background-color: var(--input-background);
        font-size: 16px;
        box-sizing: border-box;
        transition: border-color 0.3s;
    }

    .input-group input:focus {
        outline: none;
        border-color: var(--primary-color);
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

    .message {
        padding: 10px;
        border-radius: 5px;
        text-align: center;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .message.error { color: #D32F2F; background-color: #FFCDD2; }
    .message.success { color: #388E3C; background-color: #C8E6C9; }
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
      <h2>Reset Your Password</h2>
      <p>Please enter your new password below.</p>
    </div>

    <div class="auth-body">
      <?php if ($message): ?>
        <p class="message <?= $message_type ?>"><?= $message ?></p>
      <?php endif; ?>
      
      <?php if ($message_type !== 'success'): ?>
        <form method="POST">
          <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" name="new_password" placeholder="New Password" required>
          </div>
          <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
          </div>
          <button type="submit" class="btn-submit">Change Password</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
