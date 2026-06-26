<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];

if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $notification_id = (int)$_POST['notification_id'];
    
    $updateStmt = $conn->prepare("UPDATE tbl_notifications SET Is_Read = 1 WHERE Notification_Id = ? AND Email = ?");
    if ($updateStmt) {
        $updateStmt->bind_param('is', $notification_id, $email);
        $updateStmt->execute();
        $updateStmt->close();
    }
}

// Redirect back to appointments page
header("Location: appointment.php");
exit();
?>
