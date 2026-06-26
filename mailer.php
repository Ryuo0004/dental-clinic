<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendMail(string $toEmail, string $subject, string $htmlBody, string $toName = '', string $textBody = '') {
    $mail = new PHPMailer(true);

    // SMTP configuration - Using Gmail SMTP
    $smtpHost = 'smtp.gmail.com';
    $smtpPort = 587;
    $smtpUser = 'faaaannnyy@gmail.com'; // Your Gmail address
    $smtpPass = "vbxsznigdvmwijmo"; // Your App Password (not your Gmail password)
    $fromEmail = 'faaaannnyy@gmail.com'; // Same as smtpUser
    $fromName  = 'Miles Dental Clinic';
    
    // For testing - you can use Mailtrap or another service
    $useTestAccount = false; // Set to true to use Mailtrap for testing
    
    if ($useTestAccount) {
        // Mailtrap SMTP settings for testing
        $smtpHost = 'sandbox.smtp.mailtrap.io';
        $smtpPort = 2525;
        $smtpUser = 'your_mailtrap_username';
        $smtpPass = 'your_mailtrap_password';
        $fromEmail = 'test@example.com';
    }

    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpPort;
        $mail->CharSet    = 'UTF-8';
        $mail->isHTML(true);
        $mail->Timeout    = 15; // Reduced timeout for faster failure
        
        // Enable verbose debug output for troubleshooting
        $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer ($level): $str");
            file_put_contents(__DIR__ . '/mailer_debug.log', date('Y-m-d H:i:s') . " - $str\n", FILE_APPEND);
        };

        // Debugging logs
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer ($level): $str");
        };

        // Sender & recipient
        $mail->setFrom($fromEmail, $fromName);
        $mail->addReplyTo($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName ?: $toEmail);

        // Email content
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);

        return $mail->send();
    } catch (Exception $e) {
        $errorMsg = "Mailer Exception: " . $e->getMessage();
        error_log($errorMsg);
        file_put_contents(__DIR__ . '/mailer_error.log', date('Y-m-d H:i:s') . " - {$errorMsg}\n", FILE_APPEND);
        return false;
    }
}
