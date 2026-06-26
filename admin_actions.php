<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appt_status'])) {
  $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
  if (!hash_equals($_SESSION['admin_csrf'], $token)) {
    $adminAlert = '<div class="alert alert-danger">Invalid request token.</div>';
  } else {
    $apptId = intval($_POST['appointment_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $allowed = ['Confirmed','Ongoing','Completed','Cancelled'];
    if ($apptId > 0 && in_array($newStatus, $allowed, true)) {
      $stmt = $conn->prepare("UPDATE tbl_appointments SET Status = ? WHERE Appointment_Id = ? LIMIT 1");
      $stmt->bind_param('si', $newStatus, $apptId);
      if ($stmt->execute() && $stmt->affected_rows >= 0) {
        $adminAlert = '<div class="alert alert-success">Appointment updated.</div>';
      } else {
        $adminAlert = '<div class="alert alert-danger">Failed to update appointment.</div>';
      }
      $stmt->close();
    }
  }
}
?>


