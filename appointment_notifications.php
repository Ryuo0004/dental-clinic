<?php
// Notification helpers for appointment emails (confirm, cancel, reschedule)
// Uses PHPMailer via the existing sendMail() helper in mailer.php

if (file_exists(__DIR__ . '/mailer.php')) {
    require_once __DIR__ . '/mailer.php';
}

/**
 * Formats a human-friendly date/time string.
 */
function __formatApptDateTime(string $date, string $time): string
{
    $tsDate = strtotime($date);
    $tsTime = strtotime($time);
    $dateStr = $tsDate ? date('M j, Y', $tsDate) : $date;
    $timeStr = $tsTime ? date('g:i A', $tsTime) : $time;
    return $dateStr . ' at ' . $timeStr;
}

/**
 * Builds a basic HTML email shell.
 */
function __wrapHtml(string $title, string $body): string
{
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title></head>' .
           '<body style="font-family: Arial, sans-serif; background:#f6f7fb; padding:20px; color:#111827;">' .
           '<div style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">' .
           '<div style="background:#1d4ed8; color:#fff; padding:14px 18px; font-weight:700;">Miles Dental Clinic</div>' .
           '<div style="padding:18px; line-height:1.6;">' . $body . '</div>' .
           '<div style="padding:14px 18px; font-size:12px; color:#6b7280; background:#f9fafb;">This is an automated message. Please do not reply.</div>' .
           '</div></body></html>';
}

/**
 * Sends an appointment confirmation email to a patient.
 */
function sendAppointmentConfirmedEmail(
    string $toEmail,
    string $toName,
    string $date,
    string $time,
    string $procedure,
    string $dentistName = ''
): bool {
    if (!function_exists('sendMail')) { return false; }

    $when = __formatApptDateTime($date, $time);
    $subject = 'Appointment Confirmed - ' . $when;
    $body = '<p>Hi ' . htmlspecialchars($toName ?: $toEmail) . ',</p>' .
            '<p>Your appointment has been <strong>confirmed</strong>.</p>' .
            '<ul style="padding-left:18px;">' .
            '<li><strong>When:</strong> ' . htmlspecialchars($when) . '</li>' .
            '<li><strong>Procedure:</strong> ' . htmlspecialchars($procedure) . '</li>' .
            ($dentistName !== '' ? '<li><strong>Dentist:</strong> ' . htmlspecialchars($dentistName) . '</li>' : '') .
            '</ul>' .
            '<p>Please arrive 10 minutes early. If you need to make changes, reply or contact the clinic.</p>';

    $html = __wrapHtml('Appointment Confirmed', $body);
    return sendMail($toEmail, $subject, $html, $toName);
}

/**
 * Sends an appointment cancellation email to a patient.
 */
function sendAppointmentCancelledEmail(
    string $toEmail,
    string $toName,
    string $date,
    string $time,
    string $procedure,
    string $reason = ''
): bool {
    if (!function_exists('sendMail')) { return false; }

    $when = __formatApptDateTime($date, $time);
    $subject = 'Appointment Cancelled - ' . $when;
    $extra = $reason !== '' ? '<p><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p>' : '';
    $body = '<p>Hi ' . htmlspecialchars($toName ?: $toEmail) . ',</p>' .
            '<p>Your appointment has been <strong>cancelled</strong>.</p>' .
            '<ul style="padding-left:18px;">' .
            '<li><strong>When:</strong> ' . htmlspecialchars($when) . '</li>' .
            '<li><strong>Procedure:</strong> ' . htmlspecialchars($procedure) . '</li>' .
            '</ul>' .
            $extra .
            '<p>If this was unintentional, you can book a new appointment through the portal.</p>';

    $html = __wrapHtml('Appointment Cancelled', $body);
    return sendMail($toEmail, $subject, $html, $toName);
}

/**
 * Sends an appointment rescheduled email to a patient.
 */
function sendAppointmentRescheduledEmail(
    string $toEmail,
    string $toName,
    string $oldDate,
    string $oldTime,
    string $newDate,
    string $newTime,
    string $procedure,
    string $dentistName = ''
): bool {
    if (!function_exists('sendMail')) { return false; }

    $oldWhen = __formatApptDateTime($oldDate, $oldTime);
    $newWhen = __formatApptDateTime($newDate, $newTime);
    $subject = 'Appointment Rescheduled - ' . $newWhen;
    $body = '<p>Hi ' . htmlspecialchars($toName ?: $toEmail) . ',</p>' .
            '<p>Your appointment has been <strong>rescheduled</strong>.</p>' .
            '<ul style="padding-left:18px;">' .
            '<li><strong>Previous:</strong> ' . htmlspecialchars($oldWhen) . '</li>' .
            '<li><strong>New:</strong> ' . htmlspecialchars($newWhen) . '</li>' .
            '<li><strong>Procedure:</strong> ' . htmlspecialchars($procedure) . '</li>' .
            ($dentistName !== '' ? '<li><strong>Dentist:</strong> ' . htmlspecialchars($dentistName) . '</li>' : '') .
            '</ul>' .
            '<p>If the new schedule doesn\'t work for you, please contact the clinic to adjust.</p>';

    $html = __wrapHtml('Appointment Rescheduled', $body);
    return sendMail($toEmail, $subject, $html, $toName);
}

/**
 * Sends an appointment completion email to a patient.
 */
function sendAppointmentCompletedEmail(
    string $toEmail,
    string $toName,
    string $date,
    string $time,
    string $procedure,
    string $dentistName = '',
    string $notes = ''
): bool {
    if (!function_exists('sendMail')) { return false; }

    $when = __formatApptDateTime($date, $time);
    $subject = 'Appointment Completed - ' . $procedure;
    $body = '<p>Hi ' . htmlspecialchars($toName ?: $toEmail) . ',</p>' .
            '<p>Your appointment on <strong>' . htmlspecialchars($when) . '</strong> has been <strong>completed</strong>.</p>' .
            '<ul style="padding-left:18px;">' .
            '<li><strong>Procedure:</strong> ' . htmlspecialchars($procedure) . '</li>' .
            ($dentistName !== '' ? '<li><strong>Dentist:</strong> ' . htmlspecialchars($dentistName) . '</li>' : '') .
            '</ul>' .
            ($notes !== '' ? '<p><strong>Notes:</strong> ' . htmlspecialchars($notes) . '</p>' : '') .
            '<p>If you experience any discomfort or have questions, please contact the clinic.</p>';

    $html = __wrapHtml('Appointment Completed', $body);
    return sendMail($toEmail, $subject, $html, $toName);
}

?>


