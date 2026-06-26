<?php
session_start();
include 'db_connect.php';
include 'settings_helper.php';
include 'mailer.php';

// Redirect if not coming from login with unverified account
if (!isset($_SESSION['unverified_user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['unverified_user'];
$error = '';
$success = '';

// Handle resend verification code
if (isset($_POST['resend_code']) || (isset($_GET['resend']) && $_GET['resend'] === '1')) {
    // Generate new verification code
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + 15 * 60);
    
    // Update verification code in database
    $stmt = $conn->prepare("UPDATE tbl_patient SET Verification_Code = ?, Verification_Expires = ? WHERE Patient_Id = ?");
    $stmt->bind_param("ssi", $code, $expiresAt, $user['id']);
    
    if ($stmt->execute()) {
        // Send verification email
        $clinicName = getClinicBranding($conn)['name'] ?? 'Miles Dental Clinic';
        $subject = "$clinicName: Your Verification Code";
        $htmlBody = '<p>Hello ' . htmlspecialchars($user['name']) . ',</p>'
                  . '<p>Your new verification code is:</p>'
                  . '<h2 style="letter-spacing:3px;font-size:32px;margin:20px 0;color:#2c3e50;">' . htmlspecialchars($code) . '</h2>'
                  . '<p>This code will expire in 15 minutes.</p>'
                  . '<p>If you did not request this code, please ignore this email.</p>'
                  . '<p style="margin-top:30px;font-size:12px;color:#7f8c8d;">This is an automated message, please do not reply.</p>';
        
        if (sendMail($user['email'], $subject, $htmlBody, $user['name'])) {
            $success = 'A new verification code has been sent to your email. Please check your inbox.';
            // Prevent form resubmission on page refresh
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?resent=1');
            exit;
        } else {
            $error = 'Failed to send verification email. Please try again.';
        }
    } else {
        $error = 'Failed to generate verification code. Please try again.';
    }
}

// Check if this is a redirect after resend
if (isset($_GET['resent']) && $_GET['resent'] === '1') {
    $success = 'A new verification code has been sent to your email. Please check your inbox.';
}


// Handle verification code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $submittedCode = $_POST['verification_code'] ?? '';
    
    if (empty($submittedCode)) {
        $error = 'Please enter the verification code';
    } else {
        // Check verification code
        $stmt = $conn->prepare("SELECT Verification_Code, Verification_Expires FROM tbl_patient WHERE Patient_Id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $data = $result->fetch_assoc();
            $now = date('Y-m-d H:i:s');
            
            if ($data['Verification_Code'] === $submittedCode && $data['Verification_Expires'] > $now) {
                // Mark email as verified
                $update = $conn->prepare("UPDATE tbl_patient SET Email_Verified = 1, Verification_Code = NULL, Verification_Expires = NULL WHERE Patient_Id = ?");
                $update->bind_param("i", $user['id']);
                
                if ($update->execute()) {
                    // Set session and redirect to dashboard
                    $_SESSION['patient_id'] = $user['id'];
                    $_SESSION['patient_email'] = $user['email'];
                    $_SESSION['patient_name'] = $user['name'];
                    unset($_SESSION['unverified_user']);
                    
                    header("Location: patient_dashboard.php");
                    exit;
                } else {
                    $error = 'Failed to verify email. Please try again.';
                }
            } else {
                $error = 'Invalid or expired verification code. Please try again or request a new code.';
            }
        } else {
            $error = 'User not found. Please try logging in again.';
        }
    }
}

// Get clinic branding
$clinicBranding = getClinicBranding($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Your Email - <?= htmlspecialchars($clinicBranding['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .verification-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 1.5rem;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        p {
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        .verification-form {
            margin-top: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 500;
        }
        input[type="text"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
            margin-bottom: 1rem;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn-resend {
            background-color: #f1f1f1;
            color: #2c3e50;
        }
        .btn-resend:hover {
            background-color: #e1e1e1;
        }
        .error {
            color: #e74c3c;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: #fde8e8;
            border-radius: 4px;
            text-align: left;
        }
        .success {
            color: #27ae60;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: #e8f8f0;
            border-radius: 4px;
            text-align: left;
        }
        .login-link {
            margin-top: 1.5rem;
            color: #7f8c8d;
        }
        .login-link a {
            color: #3498db;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <?php if (!empty($clinicBranding['logo_url'])): ?>
            <img src="<?= htmlspecialchars($clinicBranding['logo_url']) ?>" alt="Clinic Logo" class="logo">
        <?php else: ?>
            <h1><?= htmlspecialchars($clinicBranding['name'] ?? 'Miles Dental Clinic') ?></h1>
        <?php endif; ?>
        
        <h1>Verify Your Email</h1>
        <p>We've sent a verification code to <strong><?= htmlspecialchars($user['email']) ?></strong>. Please enter the code below to verify your email address.</p>
        
        <?php if (isset($success)): ?>
            <div class="success-message"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" class="verification-form">
            <div class="form-group">
                <label for="verification_code">Verification Code</label>
                <input type="text" 
                       id="verification_code" 
                       name="verification_code" 
                       placeholder="Enter 6-digit code" 
                       required 
                       maxlength="6" 
                       pattern="\d{6}" 
                       title="Please enter a 6-digit code"
                       autocomplete="one-time-code"
                       inputmode="numeric">
            </div>
            
            <div class="button-group">
                <button type="submit" name="verify_code" class="btn-verify">
                    <i class="fas fa-check-circle"></i> Verify Email
                </button>
            </div>
        </form>
        
        <div class="resend-container">
            <span>Didn't receive a code?</span>
            <form method="POST" style="display: inline;">
                <button type="submit" name="resend_code" class="btn-link">
                    Resend Code
                </button>
            </form>
        </form>
        
        <div class="login-link">
            <a href="logout.php">Not <?= htmlspecialchars($user['email'] ?? 'you') ?>? Log out</a>
        </div>
        
        <style>
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #4caf50;
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #f44336;
        }
        
        .verification-form {
            margin: 25px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .button-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn-verify {
            background-color: #4CAF50;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-verify:hover {
            background-color: #388e3c;
        }
        
        .resend-container {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .btn-link {
            background: none;
            border: none;
            color: #2196F3;
            cursor: pointer;
            padding: 5px 10px;
            font-size: 14px;
            text-decoration: underline;
        }
        
        .btn-link:hover {
            color: #0d8aee;
        }
        </style>
    </div>
</html>
