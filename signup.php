<?php
session_start();
include 'db_connect.php';
include 'settings_helper.php'; // For clinic branding
include 'mailer.php'; // For sending verification email

$clinicBranding = getClinicBranding($conn);

$success = "";
$error = "";

// Inline AJAX: handle send/resend code without redirect
if ($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['send_code','resend_code'])) {
    $emailAjax = trim($_POST['email'] ?? '');
    header('Content-Type: application/json');
    if (!filter_var($emailAjax, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok'=>false,'message'=>'Invalid email']);
        exit;
    }
    // Check cooldown for this email
    $nowTs = time();
    $cooldownUntil = $_SESSION['email_cooldown'][$emailAjax] ?? 0;
    if ($nowTs < $cooldownUntil) {
        $remaining = $cooldownUntil - $nowTs;
        echo json_encode(['ok'=>false,'message'=>'Please wait ' . $remaining . ' seconds before requesting a new code.']);
        exit;
    }
    
    // Generate 6-digit code and store in session
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['pre_email'] = $emailAjax;
    $_SESSION['pre_code'] = $code;
    $_SESSION['pre_expires'] = time() + 15 * 60;
    
    // Set cooldown (60 seconds)
    $_SESSION['email_cooldown'][$emailAjax] = $nowTs + 60;

    // Send the email
    $clinicName = $clinicBranding['name'] ?? 'Miles Dental Clinic';
    $subject = "$clinicName: Your verification code";
    $htmlBody = '<p>Hi,</p>'
              . '<p>Your verification code is:</p>'
              . '<h2 style="letter-spacing:3px;">' . htmlspecialchars($code) . '</h2>'
              . '<p>This code will expire in 15 minutes.</p>';
    $toName = explode('@', $emailAjax)[0];
    sendMail($emailAjax, $subject, $htmlBody, $toName);
    echo json_encode(['ok'=>true,'message'=>'Code sent','email'=>$emailAjax,'cooldown'=>60]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first = $_POST['first_name'];
    $middle = empty($_POST['middle_name']) ? null : $_POST['middle_name'];
    $last = $_POST['last_name'];
    $suffix = empty($_POST['suffix']) ? null : $_POST['suffix'];
    $address = $_POST['address'];
    $bday = $_POST['bday'];
    $gender = $_POST['gender'] ?? '';
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    // keep only digits for validation/storage
    $phone = preg_replace('/\D+/', '', (string)$phone);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $email_code = $_POST['email_code'] ?? '';

    // Validate email verification code
    if (empty($email_code)) {
        $error = "Please enter the verification code sent to your email.";
    } elseif (!isset($_SESSION['pre_code']) || !isset($_SESSION['pre_expires'])) {
        $error = "Please request a verification code first.";
    } elseif (time() > $_SESSION['pre_expires']) {
        $error = "Verification code has expired. Please request a new one.";
    } elseif ($email_code !== $_SESSION['pre_code']) {
        $error = "Invalid verification code. Please check and try again.";
    } elseif ($_SESSION['pre_email'] !== $email) {
        $error = "Email verification code was sent to a different email address.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!ctype_upper($password[0])) {
        $error = "Password must start with a capital letter.";
    } elseif (!preg_match('/\d/', $password)) {
        $error = "Password must contain at least one number.";
    } elseif (!preg_match('/[\W_]/', $password)) {
        $error = "Password must contain at least one special character.";
    } elseif (!preg_match('/^\d{11}$/', $phone)) {
        $error = "Phone number must be exactly 11 digits.";
    } elseif (empty($bday) || !strtotime($bday)) {
        $error = "Please provide a valid birthdate.";
    } elseif (strtotime($bday) > strtotime('today')) {
        $error = "Birthdate cannot be in the future.";
    } else {
        $check = $conn->prepare("SELECT 1 FROM tbl_patient WHERE Email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        if ($result && $result->num_rows > 0) {
            $error = "Email is already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO tbl_patient 
        (Email, Password, First_name, Middle_name, Last_name, Status, Email_Verified, Patient_Type)
        VALUES (?, ?, ?, ?, ?, 'Active', 1, 'Online')");
    $ins->bind_param("sssss", $email, $hashed, $first, $middle, $last);

    if ($ins->execute()) {
        // After creating the base record, persist optional profile fields if columns exist
        $optional = [
          'Address' => $address,
          'Birthdate' => $bday,
          'Date_of_Birth' => $bday,
          'bday' => $bday,
          'Gender' => $gender,
          'Phone' => $phone,
          'Phone_num' => $phone,
          'Suffix' => $suffix,
        ];
        // Detect present columns in tbl_patient
        $present = [];
        if ($colRes = $conn->query("SHOW COLUMNS FROM tbl_patient")) {
          while ($c = $colRes->fetch_assoc()) { $present[$c['Field']] = true; }
          $colRes->close();
        }
        $sets = [];
        $vals = [];
        $types = '';
        foreach ($optional as $col => $val) {
          if (isset($present[$col])) { $sets[] = "$col = ?"; $vals[] = $val; $types .= 's'; }
        }
        if (!empty($sets)) {
          $sql = "UPDATE tbl_patient SET ".implode(', ', $sets)." WHERE Email = ?";
          $types .= 's';
          $vals[] = $email;
          if ($up = $conn->prepare($sql)) {
            // mysqli bind_param requires references
            $bind = [];
            $bind[] = & $types;
            foreach ($vals as $i => $v) { $bind[] = & $vals[$i]; }
            call_user_func_array([$up, 'bind_param'], $bind);
            $up->execute();
            $up->close();
          }
        }
        echo "<script>alert('Account created successfully! You can now log in.'); window.location='login.php';</script>";
    } else {
        echo "<script>alert('Error creating account. Please try again later.');</script>";
    }

    $ins->close();
    $check->close();
    $conn->close();
   }
   }
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sign Up - <?= htmlspecialchars($clinicBranding['name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    /* Notification styles */
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 25px;
      border-radius: 6px;
      color: white;
      font-weight: 500;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      z-index: 1000;
      transform: translateX(120%);
      transition: transform 0.3s ease-in-out;
      max-width: 350px;
      opacity: 0.95;
    }
    
    .notification.show {
      transform: translateX(0);
    }
    
    .notification.success {
      background-color: #4CAF50;
    }
    
    .notification.error {
      background-color: #f44336;
    }
    
    .notification.warning {
      background-color: #ff9800;
    }

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
        max-width: 420px;
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
        margin-bottom: 15px;
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
        margin-top: 10px;
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

    .error, .success {
        padding: 10px;
        border-radius: 5px;
        text-align: center;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .error { color: #D32F2F; background-color: #FFCDD2; }
    .success { color: #388E3C; background-color: #C8E6C9; }

    .note {
        font-size: 0.85em;
        color: #666;
        margin-top: -10px;
        margin-bottom: 15px;
        padding-left: 5px;
    }
    /* Inline Send Code button inside email field */
    .send-code-btn {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        padding: 8px 12px;
        font-size: 12px;
        border: none;
        border-radius: 6px;
        background: #2563eb !important;
        color: #fff !important;
        cursor: pointer;
        z-index: 100;
        font-weight: 600;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    .send-code-btn:hover { background: #1d4ed8 !important; }
    .send-code-btn:disabled { 
        background: #9ca3af !important; 
        cursor: not-allowed !important; 
        opacity: 0.6 !important; 
    }
    /* Code input row below email with resend on right */
    .code-row { 
        display: flex !important; 
        gap: 10px; 
        align-items: center; 
        margin-bottom: 10px;
        visibility: visible !important;
        opacity: 1 !important;
    }
    .code-row input[type="text"] { flex: 1; }
    .code-row .resend-btn { 
        white-space: nowrap; 
        flex: 0 0 auto; 
        padding: 8px 12px; 
        font-size: 12px; 
        border: none; 
        border-radius: 6px; 
        background: #607D8B !important; 
        color: #fff !important; 
        cursor: pointer; 
        font-weight: 600;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    .code-row .resend-btn:hover { background: #546E7A !important; }
    .code-row .resend-btn:disabled { 
        background: #9ca3af !important; 
        cursor: not-allowed !important; 
        opacity: 0.6 !important; 
    }
    
    /* Ensure input has space for the eye button */
    
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

    .toggle-pass:hover { background-color: rgba(0, 0, 0, 0.05); }
    .toggle-pass:active { background-color: rgba(0, 0, 0, 0.1); }

    .toggle-pass .on { display: none; }
    .showing .on { display: inline; }
    .showing .off { display: none; }

    @media (max-width: 640px) {
      #password, #confirm_password { 
          padding-right: 50px; 
      }
      .toggle-pass.toggle-visible {
          display: inline-flex;
      }
    }
  </style>
  <!-- Notification container -->
  <div id="notification" class="notification"></div>
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
      <h2>Create Your Account</h2>
      <p>Join us to manage your dental health with ease.</p>
    </div>

    <div class="auth-body">
      <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
      <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

      <?php if (!$success): ?>
      <form method="POST">
        <div class="input-group">
            <i class="fas fa-user"></i>
            <input type="text" name="first_name" placeholder="First Name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
        </div>
        <div class="input-group">
            <i class="fas fa-user"></i>
            <input type="text" name="middle_name" placeholder="Middle Name (Optional)" value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
        </div>
        <div class="input-group">
            <i class="fas fa-user"></i>
            <input type="text" name="last_name" placeholder="Last Name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
        </div>
        <div class="input-group">
            <i class="fas fa-user"></i>
            <input type="text" name="suffix" placeholder="Suffix (Optional)" value="<?= htmlspecialchars($_POST['suffix'] ?? '') ?>">
        </div>
        <div class="input-group">
            <i class="fas fa-map-marker-alt"></i>
            <input type="text" name="address" placeholder="Address" required value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
        </div>
        <div class="input-group">
            <i class="fas fa-calendar-alt"></i>
            <input type="text" name="bday" placeholder="Birthday (MM/DD/YYYY)" aria-label="Birthday" required 
                   value="<?= htmlspecialchars($_POST['bday'] ?? '') ?>"
                   onfocus="this.type='date'; this.max='<?= date('Y-m-d') ?>';"
                   onblur="if(!this.value){ this.type='text'; this.removeAttribute('max'); }">
        </div>
        <div class="input-group">
            <i class="fas fa-venus-mars"></i>
            <select name="gender" required style="width:100%; padding:12px 12px 12px 40px; border:1px solid #E0E0E0; border-radius:10px; background-color: var(--input-background); font-size:16px;">
              <option value="" disabled <?= empty($_POST['gender'] ?? '') ? 'selected' : '' ?>>Select Gender</option>
              <option value="Male" <?= (($_POST['gender'] ?? '')==='Male')?'selected':'' ?>>Male</option>
              <option value="Female" <?= (($_POST['gender'] ?? '')==='Female')?'selected':'' ?>>Female</option>
              <option value="Other" <?= (($_POST['gender'] ?? '')==='Other')?'selected':'' ?>>Other</option>
            </select>
        </div>
        <div class="input-group">
            <i class="fas fa-envelope"></i>
            <input type="email" id="signup_email" name="email" placeholder="Email Address" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" style="padding-right: 80px;">
            <button type="button" id="sendCodeInline" class="send-code-btn">Send Code</button>
        </div>
        <div class="verification-section" style="margin: 15px 0 20px;">
            <div class="verification-input-container" style="position: relative; margin-bottom: 8px;">
                <div class="input-group" style="flex: 1; margin-bottom: 0;">
                    <i class="fas fa-key"></i>
                    <input type="text" id="email_code" name="email_code" 
                           placeholder="_ _ _ _ _ _" maxlength="6" pattern="\d{6}" 
                           inputmode="numeric" autocomplete="one-time-code"
                           style="letter-spacing: 10px; text-align: center; font-family: monospace; font-size: 18px; font-weight: bold; padding-right: 15px;"
                           oninput="formatVerificationCode(this)">
                </div>
                <div id="verification-timer" class="verification-timer" style="display: none; position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 14px;">
                    <span id="countdown">60</span>s
                </div>
            </div>
            <div class="verification-actions" style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                <div id="verification-hint" class="verification-hint" style="font-size: 13px; color: #6b7280;">
                    Enter the 6-digit code sent to your email
                </div>
                <button type="button" id="resendCodeBtn" class="resend-btn" style="background: #4A5C9F; border: none; border-radius: 6px; color: white; padding: 8px 12px; font-size: 13px; cursor: pointer; transition: all 0.2s;">
                    <span id="resendText">Resend Code</span>
                </button>
            </div>
        </div>
        <div class="input-group">
            <i class="fas fa-phone"></i>
            <input type="tel" name="phone" placeholder="Phone Number (11 digits)" required maxlength="11" pattern="[0-9]{11}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        <div class="input-group" style="position:relative;">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" id="password" placeholder="Password" required>
            <button type="button" class="toggle-pass" id="togglePwd" aria-label="Show password" aria-controls="password">
              <span class="off"><i class="fas fa-eye"></i></span>
              <span class="on"><i class="fas fa-eye-slash"></i></span>
            </button>
        </div>
        <div id="password-feedback" class="note"></div>
        <div class="input-group" style="position:relative;">
            <i class="fas fa-lock"></i>
            <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
            <button type="button" class="toggle-pass" id="toggleConfirm" aria-label="Show confirm password" aria-controls="confirm_password">
              <span class="off"><i class="fas fa-eye"></i></span>
              <span class="on"><i class="fas fa-eye-slash"></i></span>
            </button>
        </div>
        <div id="confirm-feedback" class="note"></div>
        
        <button type="submit" class="btn-submit">Sign Up</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="auth-footer">
      <p>Already have an account? <a href="login.php">Login Now</a></p>
    </div>
  </div>

<script>
// Notification function
function showNotification(message, type = 'info') {
  const notification = document.getElementById('notification');
  if (!notification) return;
  
  notification.textContent = message;
  notification.className = 'notification';
  notification.classList.add(type);
  notification.classList.add('show');
  
  // Auto-hide after 5 seconds
  setTimeout(() => {
    notification.classList.remove('show');
  }, 5000);
}

const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const passwordFeedback = document.getElementById('password-feedback');
const confirmFeedback = document.getElementById('confirm-feedback');

function validatePassword(password) {
  const checks = {
    length: password.length >= 8,
    capital: /^[A-Z]/.test(password),
    number: /\d/.test(password),
    special: /[\W_]/.test(password)
  };
  
  let feedback = [];
  if (!checks.length) feedback.push('At least 8 characters');
  if (!checks.capital) feedback.push('Starts with a capital');
  if (!checks.number) feedback.push('Contains a number');
  if (!checks.special) feedback.push('Contains a special character');
  
  const isValid = Object.values(checks).every(check => check);
  return { isValid, feedback };
}

if (passwordInput) {
    passwordInput.addEventListener('input', function() {
      const result = validatePassword(this.value);
      passwordFeedback.textContent = result.feedback.length > 0 ? 
        'Requires: ' + result.feedback.join(', ') : 
        '✓ Password meets requirements';
      passwordFeedback.style.color = result.isValid ? 'green' : 'red';
    });
}

  if (confirmInput) {
      confirmInput.addEventListener('input', function() {
        const matches = this.value === passwordInput.value;
        confirmFeedback.textContent = this.value ? 
          (matches ? '✓ Passwords match' : '✗ Passwords do not match') : '';
        confirmFeedback.style.color = matches ? 'green' : 'red';
      });
  }

  // Email code: Send and Resend handlers (AJAX to signup.php, no redirect)
  async function postCode(action, emailVal) {
    try {
      const res = await fetch('signup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action, email: emailVal })
      });
      const data = await res.json().catch(() => ({}));
      
      if (data && data.ok) {
        showNotification(`Verification code sent to ${emailVal}`, 'success');
        // Start cooldown timer
        if (data.cooldown) {
          startCooldownTimer(data.cooldown);
        }
        // Focus on the code input field
        const codeInput = document.getElementById('email_code');
        if (codeInput) {
          setTimeout(() => codeInput.focus(), 100);
        }
      } else {
        showNotification(data.message || 'Failed to send code. Please try again.', 'error');
      }
      return data;
    } catch (err) {
      console.error('Error:', err);
      showNotification('Network error while sending code. Please check your connection.', 'error');
      throw err; // Re-throw to allow .catch() to handle it
    }
  }

  // Cooldown timer function
  function startCooldownTimer(seconds) {
    const sendBtn = document.getElementById('sendCodeInline');
    const resendBtn = document.getElementById('resendCodeBtn');
    
    if (!sendBtn || !resendBtn) return;
    
    let remaining = seconds;
    
    // Disable buttons
    sendBtn.disabled = true;
    resendBtn.disabled = true;
    
    const updateButtons = () => {
      if (remaining <= 0) {
        sendBtn.disabled = false;
        resendBtn.disabled = false;
        sendBtn.textContent = 'Send Code';
        resendBtn.textContent = 'Resend';
        return;
      }
      
      sendBtn.textContent = `Send Code (${remaining}s)`;
      resendBtn.textContent = `Resend (${remaining}s)`;
      remaining--;
      
      setTimeout(updateButtons, 1000);
    };
    
    updateButtons();
  }

  function getEmailOrAlert() {
    const el = document.getElementById('signup_email');
    const v = (el && el.value || '').trim();
    if (!v) { 
      showNotification('Please enter your email first.', 'error');
      el.focus();
      return null; 
    }
    const re = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
    if (!re.test(v)) { 
      showNotification('Please enter a valid email.', 'error');
      el.focus();
      return null; 
    }
    return v;
  }

  const sendBtn = document.getElementById('sendCodeInline');
  if (sendBtn) {
    sendBtn.addEventListener('click', function () {
      const emailVal = getEmailOrAlert();
      if (!emailVal) return;
      postCode('send_code', emailVal);
    });
  }

  const resendBtn = document.getElementById('resendCodeBtn');
  if (resendBtn) {
    resendBtn.addEventListener('click', function () {
      const emailVal = getEmailOrAlert();
      if (!emailVal) return;
      postCode('resend_code', emailVal);
    });
  }

  // Mobile-only password visibility toggles
  (function(){
    var t1 = document.getElementById('togglePwd');
    var i1 = document.getElementById('password');
    if (t1 && i1) {
      t1.addEventListener('click', function(){
        var isText = i1.getAttribute('type') === 'text';
        i1.setAttribute('type', isText ? 'password' : 'text');
        t1.classList.toggle('showing', !isText);
        t1.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');
      });
      var updateVis1 = function(){
        var show = !!i1.value || document.activeElement === i1;
        if (show) t1.classList.add('toggle-visible'); else t1.classList.remove('toggle-visible');
      };
      i1.addEventListener('input', updateVis1);
      i1.addEventListener('focus', updateVis1);
      i1.addEventListener('blur', updateVis1);
      updateVis1();
    }
    var t2 = document.getElementById('toggleConfirm');
    var i2 = document.getElementById('confirm_password');
    if (t2 && i2) {
      t2.addEventListener('click', function(){
        var isText = i2.getAttribute('type') === 'text';
        i2.setAttribute('type', isText ? 'password' : 'text');
        t2.classList.toggle('showing', !isText);
        t2.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');
      });
      var updateVis2 = function(){
        var show = !!i2.value || document.activeElement === i2;
        if (show) t2.classList.add('toggle-visible'); else t2.classList.remove('toggle-visible');
      };
      i2.addEventListener('input', updateVis2);
      i2.addEventListener('focus', updateVis2);
      i2.addEventListener('blur', updateVis2);
      updateVis2();
    }
  })();
</script>

</body>
</html>
