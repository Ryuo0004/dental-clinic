<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/settings_helper.php'; // For clinic branding
require_once __DIR__ . '/mailer.php'; // Include PHPMailer

$clinicBranding = getClinicBranding($conn);
date_default_timezone_set('Asia/Manila');

// Clear expired OTPs
$conn->query("UPDATE tbl_patient SET otp_code = NULL, otp_expiration = NULL WHERE otp_expiration IS NOT NULL AND otp_expiration < NOW()");

$message = '';
$message_type = 'info';
$step = 'request';
$email = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '';

if (!isset($_SESSION['otp_cooldown'])) $_SESSION['otp_cooldown'] = [];
$cooldownRemaining = 0;
if (!empty($email) && isset($_SESSION['otp_cooldown'][$email])) {
    $remaining = $_SESSION['otp_cooldown'][$email] - time();
    if ($remaining > 0) $cooldownRemaining = $remaining;
}

// Send OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } elseif ($cooldownRemaining > 0) {
        $message = "Please wait {$cooldownRemaining}s before requesting again.";
        $message_type = 'info';
        $step = 'verify';
    } else {
        $stmt = $conn->prepare('SELECT patient_Id FROM tbl_patient WHERE Email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $otp = random_int(100000, 999999);
            $expiration = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            $upd = $conn->prepare('UPDATE tbl_patient SET otp_code = ?, otp_expiration = ? WHERE Email = ?');
            $upd->bind_param('sss', $otp, $expiration, $email);
            $upd->execute();
            $upd->close();

            // Send OTP via PHPMailer
            $subject = 'Your Password Reset Code';
            $htmlBody = "<p>Your OTP is <strong>$otp</strong>. It expires in 5 minutes.</p>";
            $textBody = "Your OTP is $otp. It expires in 5 minutes.";
            sendMail($email, $subject, $htmlBody, '', $textBody);
        }

        $stmt->close();
        $_SESSION['otp_cooldown'][$email] = time() + 60;
        $message = 'If an account exists with this email, an OTP has been sent.';
        $message_type = 'success';
        $step = 'verify';
    }
}

// Verify OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $entered = trim($_POST['otp']);
    if (!ctype_digit($entered) || strlen($entered) !== 6) {
        $message = 'Invalid OTP format. Enter the 6-digit code.';
        $message_type = 'error';
        $step = 'verify';
    } else {
        $stmt = $conn->prepare('SELECT otp_code, otp_expiration FROM tbl_patient WHERE Email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if ($entered === (string)$row['otp_code'] && strtotime($row['otp_expiration']) > time()) {
                $_SESSION['reset_email'] = $email;
                header('Location: reset_password.php');
                exit();
            }
        }
        $stmt->close();
        $message = 'Invalid or expired OTP. Please try again.';
        $message_type = 'error';
        $step = 'verify';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password - <?= htmlspecialchars($clinicBranding['name']) ?></title>
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
    
    .btn-resend {
        background-color: #f0f2f5;
        color: #555;
        margin-top: 10px;
    }
    .btn-resend:hover {
        background-color: #e0e0e0;
    }
    .btn-resend:disabled {
        cursor: not-allowed;
        opacity: 0.7;
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

    .message {
        padding: 10px;
        border-radius: 5px;
        text-align: center;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .message.error { color: #D32F2F; background-color: #FFCDD2; }
    .message.success { color: #388E3C; background-color: #C8E6C9; }
    .message.info { color: #0288D1; background-color: #B3E5FC; }
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
      <h2>Forgot Password</h2>
      <p>Enter your email to receive a reset code.</p>
    </div>

    <div class="auth-body">
      <?php if ($message): ?>
        <p class="message <?= $message_type ?>"><?= htmlspecialchars($message) ?></p>
      <?php endif; ?>
      
      <?php if ($step === 'request'): ?>
        <form method="POST">
          <div class="input-group">
            <i class="fas fa-envelope"></i>
            <input type="email" name="email" placeholder="Enter your Email" required>
          </div>
          <button type="submit" class="btn-submit" name="send_otp">Send OTP</button>
        </form>
      <?php elseif ($step === 'verify'): ?>
        <form method="POST">
          <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
          <div class="input-group">
            <i class="fas fa-key"></i>
            <input type="text" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="Enter 6-digit OTP" required>
          </div>
          <button type="submit" class="btn-submit" name="verify_otp">Verify OTP</button>
        </form>
        <form method="POST">
          <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
          <button type="submit" class="btn-submit btn-resend" name="send_otp" id="resendBtn" data-remaining="<?= (int)$cooldownRemaining ?>" <?= $cooldownRemaining > 0 ? 'disabled' : '' ?>>
            <?= $cooldownRemaining > 0 ? 'Resend OTP (' . (int)$cooldownRemaining . 's)' : 'Resend OTP' ?>
          </button>
        </form>
      <?php endif; ?>
    </div>

    <div class="auth-footer">
      <p>Remember your password? <a href="login.php">Login Now</a></p>
    </div>
  </div>

  <script>
    (function(){
      const btn = document.getElementById('resendBtn');
      if (!btn) return;
      let remaining = parseInt(btn.getAttribute('data-remaining') || '0', 10);
      if (!Number.isFinite(remaining) || remaining <= 0) return;
      btn.disabled = true;
      const tick = () => {
        if (remaining <= 0) {
          btn.disabled = false;
          btn.textContent = 'Resend OTP';
          clearInterval(timer);
          return;
        }
        btn.textContent = 'Resend OTP (' + remaining + 's)';
        remaining -= 1;
      };
      tick();
      const timer = setInterval(tick, 1000);
    })();
  </script>
</body>
</html>
