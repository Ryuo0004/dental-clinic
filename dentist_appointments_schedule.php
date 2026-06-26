<?php
session_start();
include 'db_connect.php';
if (!isset($_SESSION['dentist_name'])) { $_SESSION['dentist_name'] = 'Dr. Dentist'; }
$dentistId = isset($_GET['dentist_id']) ? (int)$_GET['dentist_id'] : 0;
// Optionally bind dentistId to session if you store it at login
if (!$dentistId && isset($_SESSION['dentist_id'])) { $dentistId = (int)$_SESSION['dentist_id']; }

$today = date('Y-m-d');

// Allow dentist to edit notes on their own appointments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($conn)) {
  if (isset($_POST['save_notes_id'])) {
    $aid = (int)($_POST['save_notes_id'] ?? 0);
    $notes = trim((string)($_POST['notes_content'] ?? ''));
    $ownerId = (int)($_SESSION['dentist_id'] ?? 0);
    if ($aid > 0) {
      if ($ownerId > 0) {
        $up = $conn->prepare("UPDATE tbl_appointments SET Admin_Notes = ?, Updated_At = NOW() WHERE Appointment_Id = ? AND Dentist_Id = ?");
        if ($up) { $up->bind_param('sii', $notes, $aid, $ownerId); $up->execute(); $up->close(); }
      } else {
        $up = $conn->prepare("UPDATE tbl_appointments SET Admin_Notes = ?, Updated_At = NOW() WHERE Appointment_Id = ?");
        if ($up) { $up->bind_param('si', $notes, $aid); $up->execute(); $up->close(); }
      }
    }
    // PRG
    header('Location: dentist_appointments_schedule.php');
    exit();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Appointments & Schedule</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="dentists.css" />
  <style>
    body{background:#f8fafc; color:#0f172a;}
    .card{background:#ffffff; color:#0f172a; border:1px solid rgba(29,78,216,0.12); border-radius:12px; box-shadow:0 6px 16px rgba(29,78,216,0.06), 0 1px 2px rgba(0,0,0,0.03);} 
  </style>
</head>
<body>
  <div class="d-flex">
    <?php include 'dentist_sidebar.php'; ?>
    <div class="flex-grow-1 p-4 dentist-main">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0">Appointments & Schedule</h3>
      </div>

      <div class="card p-3 mb-4">
        <h5 class="mb-3">Today's Appointments</h5>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Time</th><th>Patient</th><th>Procedure</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
              <?php
              if (isset($conn)) {
                $sql = "SELECT a.Appointment_Id, a.Time, a.`Procedure`, a.Status, a.Admin_Notes, COALESCE(CONCAT(p.First_name,' ',p.Last_name), a.Email) AS PatientName
                        FROM tbl_appointments a LEFT JOIN tbl_patient p ON (p.Email = a.Email)
                        WHERE a.Date = ?" . ($dentistId ? " AND a.Dentist_Id = ?" : "") . " ORDER BY a.Time ASC";
                if ($dentistId) { $st = $conn->prepare($sql); $st->bind_param('si', $today, $dentistId); }
                else { $st = $conn->prepare($sql); $st->bind_param('s', $today); }
                $st->execute();
                $rs = $st->get_result();
                if ($rs && $rs->num_rows) {
                  while ($row = $rs->fetch_assoc()) {
                    echo '<tr>'; 
                    echo '<td>'.htmlspecialchars($row['Time']).'</td>';
                    echo '<td>'.htmlspecialchars($row['PatientName']).'</td>';
                    echo '<td>'.htmlspecialchars($row['Procedure']).'</td>';
                    echo '<td>'.htmlspecialchars($row['Status']).'</td>';
                    $notes = trim((string)($row['Admin_Notes'] ?? ''));
                    $noteBtn = '<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#notesModalToday-'.(int)$row['Appointment_Id'].'">'.($notes!==''? 'Edit' : 'Add').'</button>';
                    echo '<td>' . $noteBtn . '</td>';
                    echo '</tr>';
                  }
                } else { echo '<tr><td colspan="5" class="text-muted">No appointments today.</td></tr>'; }
                $st->close();
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card p-3">
        <h5 class="mb-3">Upcoming</h5>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Procedure</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
              <?php
              if (isset($conn)) {
                $sql = "SELECT a.Appointment_Id, a.Date, a.Time, a.`Procedure`, a.Status, a.Admin_Notes, COALESCE(CONCAT(p.First_name,' ',p.Last_name), a.Email) AS PatientName
                        FROM tbl_appointments a LEFT JOIN tbl_patient p ON (p.Email = a.Email)
                        WHERE a.Date > CURDATE()" . ($dentistId ? " AND a.Dentist_Id = ?" : "") . " ORDER BY a.Date ASC, a.Time ASC";
                if ($dentistId) { $st = $conn->prepare($sql); $st->bind_param('i', $dentistId); }
                else { $st = $conn->prepare($sql); }
                $st->execute();
                $rs = $st->get_result();
                if ($rs && $rs->num_rows) {
                  while ($row = $rs->fetch_assoc()) {
                    echo '<tr>'; 
                    echo '<td>'.htmlspecialchars($row['Date']).'</td>';
                    echo '<td>'.htmlspecialchars($row['Time']).'</td>';
                    echo '<td>'.htmlspecialchars($row['PatientName']).'</td>';
                    echo '<td>'.htmlspecialchars($row['Procedure']).'</td>';
                    echo '<td>'.htmlspecialchars($row['Status']).'</td>';
                    $notes = trim((string)($row['Admin_Notes'] ?? ''));
                    $noteBtn = '<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#notesModalUpcoming-'.(int)$row['Appointment_Id'].'">'.($notes!==''? 'Edit' : 'Add').'</button>';
                    echo '<td>' . $noteBtn . '</td>';
                    echo '</tr>';
                  }
                } else { echo '<tr><td colspan="6" class="text-muted">No upcoming appointments.</td></tr>'; }
                $st->close();
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <!-- Notes modals (generated server-side for both tables) -->
  <?php
    // Build a single list of appointment ids + notes for modal generation
    // For simplicity, re-query upcoming+today ids for the logged-in dentist
    $ownerId = (int)($_SESSION['dentist_id'] ?? 0);
    if ($ownerId > 0 && isset($conn)) {
      $mods = [];
      $res = $conn->prepare("SELECT Appointment_Id, Admin_Notes FROM tbl_appointments WHERE (Date = ? OR Date > CURDATE()) AND Dentist_Id = ?");
      if ($res) { $res->bind_param('si', $today, $ownerId); $res->execute(); $rr=$res->get_result(); while($r=$rr->fetch_assoc()){ $mods[]=$r; } $res->close(); }
      foreach($mods as $m){ $aid=(int)$m['Appointment_Id']; $val=trim((string)($m['Admin_Notes'] ?? '')); ?>
        <div class="modal fade" id="notesModalToday-<?= $aid ?>" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Notes</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <form method="post"><div class="modal-body">
              <input type="hidden" name="save_notes_id" value="<?= $aid ?>" />
              <textarea name="notes_content" class="form-control" rows="5" placeholder="Enter notes..."><?= htmlspecialchars($val) ?></textarea>
            </div><div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary">Save</button>
            </div></form>
          </div></div>
        </div>
        <div class="modal fade" id="notesModalUpcoming-<?= $aid ?>" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Notes</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <form method="post"><div class="modal-body">
              <input type="hidden" name="save_notes_id" value="<?= $aid ?>" />
              <textarea name="notes_content" class="form-control" rows="5" placeholder="Enter notes..."><?= htmlspecialchars($val) ?></textarea>
            </div><div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary">Save</button>
            </div></form>
          </div></div>
        </div>
  <?php } }
  ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      if (window.bootstrap) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el){ new bootstrap.Tooltip(el); });
      }
    });
  </script>
</body>
</html>
