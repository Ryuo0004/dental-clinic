<?php
/**
 * Settings Helper - Provides centralized access to clinic settings
 * This file ensures that admin settings are properly connected to patient-facing pages
 */

// Function to get clinic settings
function getClinicSettings($conn) {
    static $settings = null;
    
    if ($settings === null) {
        // Ensure the clinic settings table exists
        $conn->query("CREATE TABLE IF NOT EXISTS tbl_clinic_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Load current settings
        $settings = [];
        $loadStmt = $conn->prepare("SELECT setting_key, setting_value FROM tbl_clinic_settings");
        if ($loadStmt) {
            $loadStmt->execute();
            $result = $loadStmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            $loadStmt->close();
        }
    }
    
    return $settings;
}

// Function to get a specific setting with fallback
function getSetting($conn, $key, $default = '') {
    $settings = getClinicSettings($conn);
    return isset($settings[$key]) ? $settings[$key] : $default;
}

// Function to get clinic branding information
function getClinicBranding($conn) {
    return [
        'name' => getSetting($conn, 'clinic_name', 'Miles Dental Clinic'),
        'logo_url' => getSetting($conn, 'clinic_logo_url', ''),
        'contact_email' => getSetting($conn, 'contact_email', 'admin@milesdental.com'),
        'phone_number' => getSetting($conn, 'phone_number', '(555) 123-4567'),
        'address' => getSetting($conn, 'address', ''),
        'clinic_hours' => getSetting($conn, 'clinic_hours', 'Monday - Sunday: 7:00 AM - 7:00 PM'),
        'timezone' => getSetting($conn, 'timezone', 'Asia/Manila')
    ];
}

// Function to get emergency contact information
function getEmergencyContacts($conn) {
    $phone = getSetting($conn, 'phone_number', '(555) 123-4567');
    return [
        'main_phone' => $phone,
        'after_hours_phone' => getSetting($conn, 'after_hours_phone', '(555) 987-6543'),
        'emergency_instructions' => getSetting($conn, 'emergency_instructions', 'For severe pain, bleeding, or broken teeth, call immediately. Do not wait for regular office hours.')
    ];
}

// Function to get appointment settings
function getAppointmentSettings($conn) {
    return [
        'max_appointments_per_day' => (int)getSetting($conn, 'max_appointments_per_day', '20'),
        'appointment_duration' => (int)getSetting($conn, 'appointment_duration', '60'),
        'session_timeout_minutes' => (int)getSetting($conn, 'session_timeout_minutes', '0')
    ];
}

// Function to format time according to clinic timezone
function formatTimeWithTimezone($time, $timezone = 'Asia/Manila') {
    if (empty($time)) return 'N/A';
    
    try {
        $dt = new DateTime($time, new DateTimeZone($timezone));
        return $dt->format('g:i A');
    } catch (Exception $e) {
        // Fallback to original formatting
        $timestamp = strtotime($time);
        return date('g:i A', $timestamp);
    }
}

// Function to get current year for footer
function getCurrentYear() {
    return date('Y');
}
?>
