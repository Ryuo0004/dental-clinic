<?php
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include 'admin_init.php';
} else {
  include 'db_connect.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

// Include email helpers
if (file_exists(__DIR__ . '/appointment_notifications.php')) {
  include_once __DIR__ . '/appointment_notifications.php';
}

// Simple notification helper (same schema as elsewhere)
if (!function_exists('createNotification')) {
  function createNotification(mysqli $conn, string $email, string $message, string $type): void {
    $conn->query("CREATE TABLE IF NOT EXISTS tbl_notifications (
      Id INT AUTO_INCREMENT PRIMARY KEY,
      Email VARCHAR(255) NOT NULL,
      Message TEXT NOT NULL,
      Type VARCHAR(64) NOT NULL,
      Is_Read TINYINT(1) DEFAULT 0,
      Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX (Email), INDEX (Type), INDEX (Is_Read)
    ) ENGINE=InnoDB");
    if ($stmt = $conn->prepare("INSERT INTO tbl_notifications (Email, Message, Type) VALUES (?, ?, ?)")) {
      $stmt->bind_param('sss', $email, $message, $type);
      $stmt->execute();
      $stmt->close();
    }
  }
}

$dentistId = isset($_GET['dentist_id']) ? (int)$_GET['dentist_id'] : 0;
if ($dentistId <= 0) {
  header('Location: admin_appointments.php');
  exit();
}

$actionAlert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($conn)) {
  // Confirm
  if (isset($_POST['confirm_id'])) {
    $confirmId = (int)$_POST['confirm_id'];
    // Load appointment + patient for notification/email
    $info = null;
    if ($s = $conn->prepare("SELECT a.Email, a.Date, a.Time, a.Procedure, p.First_name, p.Last_name FROM tbl_appointments a LEFT JOIN tbl_patient p ON a.Email = p.Email WHERE a.Appointment_Id = ? AND a.Dentist_Id = ? LIMIT 1")) {
      $s->bind_param('ii', $confirmId, $dentistId);
      $s->execute();
      $info = $s->get_result()->fetch_assoc();
      $s->close();
    }
    $upd = $conn->prepare("UPDATE tbl_appointments SET Status = 'Confirmed', Updated_At = NOW() WHERE Appointment_Id = ? AND Dentist_Id = ?");
    if ($upd) {
      $upd->bind_param('ii', $confirmId, $dentistId);
      if ($upd->execute() && $upd->affected_rows > 0) {
        $actionAlert = '<div class="alert alert-success">Appointment confirmed.</div>';
        if ($info) {
          $msg = "Your appointment for " . $info['Procedure'] . " on " . date('M j, Y', strtotime($info['Date'])) . " at " . date('g:i A', strtotime($info['Time'])) . " has been CONFIRMED.";
          createNotification($conn, (string)$info['Email'], $msg, 'appointment_confirmed');
          if (function_exists('sendAppointmentConfirmedEmail')) {
            $patientName = trim(($info['First_name'] ?? '') . ' ' . ($info['Last_name'] ?? ''));
            @sendAppointmentConfirmedEmail((string)$info['Email'], $patientName, (string)$info['Date'], (string)$info['Time'], (string)$info['Procedure']);
          }
        }
      } else {
        $actionAlert = '<div class="alert alert-danger">Failed to confirm appointment.</div>';
      }
      $upd->close();
    }
  }
  // Cancel
  if (isset($_POST['cancel_id'])) {
    $cancelId = (int)$_POST['cancel_id'];
    // Load appointment + patient
    $info = null;
    if ($s = $conn->prepare("SELECT a.Email, a.Date, a.Time, a.Procedure, p.First_name, p.Last_name FROM tbl_appointments a LEFT JOIN tbl_patient p ON a.Email = p.Email WHERE a.Appointment_Id = ? AND a.Dentist_Id = ? LIMIT 1")) {
      $s->bind_param('ii', $cancelId, $dentistId);
      $s->execute();
      $info = $s->get_result()->fetch_assoc();
      $s->close();
    }
    $upd = $conn->prepare("UPDATE tbl_appointments SET Status = 'Cancelled', Updated_At = NOW() WHERE Appointment_Id = ? AND Dentist_Id = ?");
    if ($upd) {
      $upd->bind_param('ii', $cancelId, $dentistId);
      if ($upd->execute() && $upd->affected_rows > 0) {
        $actionAlert = '<div class="alert alert-warning">Appointment cancelled.</div>';
        if ($info) {
          $msg = "Your appointment for " . $info['Procedure'] . " on " . date('M j, Y', strtotime($info['Date'])) . " at " . date('g:i A', strtotime($info['Time'])) . " has been CANCELLED.";
          createNotification($conn, (string)$info['Email'], $msg, 'appointment_cancelled');
          if (function_exists('sendAppointmentCancelledEmail')) {
            $patientName = trim(($info['First_name'] ?? '') . ' ' . ($info['Last_name'] ?? ''));
            @sendAppointmentCancelledEmail((string)$info['Email'], $patientName, (string)$info['Date'], (string)$info['Time'], (string)$info['Procedure']);
          }
        }
      } else {
        $actionAlert = '<div class="alert alert-danger">Failed to cancel appointment.</div>';
      }
      $upd->close();
    }
  }
  // Reschedule (per-dentist)
  if (isset($_POST['reschedule_id'])) {
    $resId = (int)$_POST['reschedule_id'];
    $newDate = trim($_POST['new_date'] ?? '');
    $newTime = trim($_POST['new_time'] ?? '');
    if ($resId > 0 && $newDate && $newTime) {
      // Load the appointment (ensure ownership by dentist)
      if ($s = $conn->prepare("SELECT Appointment_Id, `Procedure`, Email, Date, Time FROM tbl_appointments WHERE Appointment_Id = ? AND Dentist_Id = ? LIMIT 1")) {
        $s->bind_param('ii', $resId, $dentistId);
        $s->execute();
        $ap = $s->get_result()->fetch_assoc();
        $s->close();
        if ($ap) {
          $start_time = $newTime;
        
          if ($start_time < '07:00:00' || $end_time > '18:00:00') {
            $actionAlert = '<div class="alert alert-danger">Outside clinic hours.</div>';
          } else {
            // Check conflicts for this dentist only
            if ($c = $conn->prepare("SELECT Appointment_Id FROM tbl_appointments WHERE Appointment_Id <> ? AND Dentist_Id = ? AND Date = ? AND Status IN ('Booked','Confirmed','Pending','Ongoing') AND ((Time <= ? AND DATE_ADD(Time, INTERVAL Duration MINUTE) > ?) OR (Time < ? AND DATE_ADD(Time, INTERVAL Duration MINUTE) >= ?) OR (Time >= ? AND Time < ?))")) {
              $c->bind_param('iisssssss', $resId, $dentistId, $newDate, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time);
              $c->execute();
              $c->store_result();
              $conflict = $c->num_rows > 0;
              $c->close();
              if ($conflict) {
                $actionAlert = '<div class="alert alert-danger">Conflict detected. Please choose another time.</div>';
              } else {
                $upd = $conn->prepare("UPDATE tbl_appointments SET Date = ?, Time = ?, Status = 'Pending', Updated_At = NOW() WHERE Appointment_Id = ? AND Dentist_Id = ?");
                if ($upd) {
                  $upd->bind_param('ssii', $newDate, $newTime, $resId, $dentistId);
                  if ($upd->execute()) {
                    $actionAlert = '<div class="alert alert-success">Appointment rescheduled.</div>';
                    // Notify patient
                    $msg = "Your appointment for " . $ap['Procedure'] . " has been RESCHEDULED to " . date('M j, Y', strtotime($newDate)) . " at " . date('g:i A', strtotime($newTime)) . ".";
                    createNotification($conn, (string)$ap['Email'], $msg, 'appointment_rescheduled');
                    if (function_exists('sendAppointmentRescheduledEmail')) {
                      @sendAppointmentRescheduledEmail((string)$ap['Email'], '', (string)$ap['Date'], (string)$ap['Time'], (string)$newDate, (string)$newTime, (string)$ap['Procedure']);
                    }
                  } else {
                    $actionAlert = '<div class="alert alert-danger">Failed to reschedule.</div>';
                  }
                  $upd->close();
                }
              }
            }
          }
        }
      }
    }
  }
}

$dentist = ['Name' => 'Dentist #'.$dentistId, 'Specialization' => ''];
// Load dentist info if table exists
$hasDent = $conn->query("SHOW TABLES LIKE 'tbl_dentist'");
if ($hasDent && $hasDent->num_rows > 0) {
  if ($s = $conn->prepare("SELECT Name, COALESCE(Specialization,'') AS Specialization FROM tbl_dentist WHERE Dentist_id = ? LIMIT 1")) {
    $s->bind_param('i', $dentistId);
    if ($s->execute()) { $res = $s->get_result(); if ($row = $res->fetch_assoc()) { $dentist = $row; } }
    $s->close();
  }
}

// Patient list (name, service, date, status)
$patients = [];
if ($s = $conn->prepare("SELECT a.Appointment_Id, CONCAT(COALESCE(p.First_name,''),' ',COALESCE(p.Last_name,'')) AS Patient_Name, a.`Procedure`, a.Date, a.Time,a.Status FROM tbl_appointments a LEFT JOIN tbl_patient p ON a.Email = p.Email WHERE a.Dentist_Id = ? ORDER BY a.Date DESC, a.Time DESC LIMIT 200")) {
  $s->bind_param('i', $dentistId);
  if ($s->execute()) { $res = $s->get_result(); while ($row = $res->fetch_assoc()) { $patients[] = $row; } }
  $s->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dentist Details - Appointments</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: Arial, sans-serif; margin:0; }
    .content { padding:16px; height:100vh; overflow-y:auto; background:#0f172a; color:#e2e8f0; }
    .card { background:#111827; color:#e5e7eb; border:1px solid rgba(255,255,255,0.06); }
    .table { color:#e5e7eb; }
    .action-icon-btn { width: 34px; height: 32px; display:inline-flex; align-items:center; justify-content:center; padding:0; }
    .action-group { display:flex; gap:6px; align-items:center; }
  </style>
</head>
<body>
<div class="d-flex" style="height:100vh;">
  <?php if (file_exists($baseDir . '/admin_sidebar.php')) { include 'admin_sidebar.php'; } ?>
  <div class="content flex-grow-1">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h3 class="mb-0"><?= htmlspecialchars($dentist['Name']) ?></h3>
        <div class="text-muted small"><?= htmlspecialchars($dentist['Specialization']) ?></div>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="admin_appointments.php">Back</a>
    </div>

    <?= $actionAlert ?>

    <div class="card"><div class="card-body">
      <h5 class="mb-3">Patients (All)</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead><tr><th>Patient</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($patients)): ?>
              <tr><td colspan="6" class="text-muted">No records found.</td></tr>
            <?php else: foreach ($patients as $p): ?>
              <tr>
                <td><?= htmlspecialchars(trim($p['Patient_Name']) !== '' ? $p['Patient_Name'] : 'Unknown') ?></td>
                <td><?= htmlspecialchars($p['Procedure']) ?></td>
                <td><?= htmlspecialchars($p['Date']) ?></td>
                <td><?= htmlspecialchars($p['Time']) ?></td>
                <td><?= htmlspecialchars($p['Status']) ?></td>
                <td>
                  <?php if (in_array($p['Status'], ['Pending','Booked','Confirmed','Ongoing'])): ?>
                    <?php $rowId = (int)($p['Appointment_Id'] ?? 0); ?>
                    <div class="action-group">
                      <form method="post" class="d-inline" title="Confirm">
                        <input type="hidden" name="confirm_id" value="<?= $rowId ?>" />
                        <button type="submit" class="btn btn-success btn-sm action-icon-btn"><i class="fas fa-check"></i></button>
                      </form>
                      <form method="post" class="d-inline" onsubmit="return confirm('Cancel this appointment?');" title="Cancel">
                        <input type="hidden" name="cancel_id" value="<?= $rowId ?>" />
                        <button type="submit" class="btn btn-outline-danger btn-sm action-icon-btn"><i class="fas fa-xmark"></i></button>
                      </form>
                      <form method="post" class="d-inline" title="Reschedule">
                        <input type="hidden" name="reschedule_id" value="<?= $rowId ?>" />
                        <input type="date" name="new_date" value="<?= htmlspecialchars(date('Y-m-d')) ?>" class="form-control form-control-sm d-none d-lg-inline w-auto" />
                        <input type="time" name="new_time" value="<?= htmlspecialchars($p['Time'] ?? '') ?>" class="form-control form-control-sm d-none d-lg-inline w-auto" />
                        <button class="btn btn-secondary btn-sm action-icon-btn"><i class="fas fa-calendar-days"></i></button>
                      </form>
                    </div>
                  <?php else: ?>
                    <span class="badge bg-secondary"><?= htmlspecialchars($p['Status']) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div></div>
  </div>
</div>
</body>
</html>
