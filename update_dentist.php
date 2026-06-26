<?php
// Start session and include database connection
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get and validate input
$dentistId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

if (!$dentistId || !$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    // Check if email already exists
    $stmt = $conn->prepare('SELECT Dentist_id FROM tbl_dentist WHERE Email = ? AND Dentist_id != ?');
    $stmt->bind_param('si', $email, $dentistId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('This email is already in use by another dentist');
    }
    $stmt->close();

    // Update the email
    $stmt = $conn->prepare('UPDATE tbl_dentist SET Email = ? WHERE Dentist_id = ?');
    $stmt->bind_param('si', $email, $dentistId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to update email');
    }
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
