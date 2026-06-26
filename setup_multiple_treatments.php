<?php
require_once 'db_connect.php';

// Create the appointment_treatments table
$sql = "CREATE TABLE IF NOT EXISTS `tbl_appointment_treatments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `appointment_id` int(11) NOT NULL,
    `treatment_name` varchar(255) NOT NULL,
    `price` decimal(10,2) NOT NULL,
    `duration` int(11) NOT NULL,
    `notes` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `appointment_id` (`appointment_id`),
    CONSTRAINT `fk_appointment_treatment` FOREIGN KEY (`appointment_id`) 
      REFERENCES `tbl_appointments` (`Appointment_Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'tbl_appointment_treatments' created successfully or already exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Add a column to track if an appointment has multiple treatments
$sql = "ALTER TABLE `tbl_appointments` 
        ADD COLUMN IF NOT EXISTS `has_multiple_treatments` TINYINT(1) NOT NULL DEFAULT 0 AFTER `Status`";

if ($conn->query($sql) === TRUE) {
    echo "Column 'has_multiple_treatments' added to 'tbl_appointments' or already exists.\n";
} else {
    echo "Error adding column: " . $conn->error . "\n";
}

echo "Database setup complete. You can now use multiple treatments per appointment.\n";

// Close connection
$conn->close();
?>

<h2>Multiple Treatments Setup Complete</h2>
<p>The database has been updated to support multiple treatments per appointment.</p>
<p><a href="admin_appointments.php">Go to Appointments</a></p>
