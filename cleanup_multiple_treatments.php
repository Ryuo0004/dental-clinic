<?php
require_once 'db_connect.php';

// Drop the appointment_treatments table
$sql = "DROP TABLE IF EXISTS `tbl_appointment_treatments`";
if ($conn->query($sql) === TRUE) {
    echo "Table 'tbl_appointment_treatments' has been removed.\n";
} else {
    echo "Error dropping table: " . $conn->error . "\n";
}

// Remove the has_multiple_treatments column if it exists
$sql = "ALTER TABLE `tbl_appointments` 
        DROP COLUMN IF EXISTS `has_multiple_treatments`";

if ($conn->query($sql) === TRUE) {
    echo "Column 'has_multiple_treatments' has been removed from 'tbl_appointments'.\n";
} else {
    echo "Error removing column: " . $conn->error . "\n";
}

// Close connection
$conn->close();
?>

<h2>Multiple Treatments Cleanup Complete</h2>
<p>The database changes for multiple treatments have been reverted.</p>
<p><a href="admin_appointments.php">Back to Appointments</a></p>
