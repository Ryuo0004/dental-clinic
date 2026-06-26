<?php
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include 'admin_init.php';
} else {
  include 'db_connect.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$patientEmail = isset($_GET['email']) ? $_GET['email'] : '';

  $patient = [];
$treatmentHistory = [];
$totalAppointments = 0;

// Handle admin-triggered deactivate/reactivate/block/unblock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patient_status_action']) && $patientId > 0) {
  $action = trim((string)$_POST['patient_status_action']);
  // Load current status to preserve Blocked unless explicitly unblocked
  $currentStatus = 'Active';
  if (isset($conn)) {
    if ($cs = $conn->prepare("SELECT COALESCE(Status,'Active') AS S FROM tbl_patient WHERE Patient_Id = ? LIMIT 1")) {
      $cs->bind_param('i', $patientId);
      if ($cs->execute()) { $gr = $cs->get_result(); if ($row=$gr->fetch_assoc()) { $currentStatus = (string)$row['S']; } }
      $cs->close();
    }
  }
  $newStatus = $currentStatus;
  if ($action === 'block') { $newStatus = 'Blocked'; }
  elseif ($action === 'unblock') { $newStatus = 'Active'; }
  elseif ($action === 'deactivate') { $newStatus = (strcasecmp($currentStatus,'Blocked')===0) ? 'Blocked' : 'Inactive'; }
  elseif ($action === 'reactivate') { $newStatus = (strcasecmp($currentStatus,'Blocked')===0) ? 'Blocked' : 'Active'; }
  if (isset($conn)) {
    $u = $conn->prepare("UPDATE tbl_patient SET Status = ? WHERE Patient_Id = ?");
    if ($u) { $u->bind_param("si", $newStatus, $patientId); $u->execute(); $u->close(); }
    // If blocked, auto-cancel pending/active appointments for this patient
    if (strcasecmp($newStatus, 'Blocked') === 0) {
      // Fetch email if missing
      $pEmail = $patientEmail;
      if ($pEmail === '') {
        if ($se = $conn->prepare("SELECT Email FROM tbl_patient WHERE Patient_Id = ? LIMIT 1")) {
          $se->bind_param('i', $patientId);
          if ($se->execute()) { $gr = $se->get_result(); if ($row = $gr->fetch_assoc()) { $pEmail = (string)$row['Email']; } }
          $se->close();
        }
      }
      if ($pEmail !== '') {
        if ($ca = $conn->prepare("UPDATE tbl_appointments SET Status = 'Cancelled', Updated_At = NOW() WHERE Email = ? AND Status IN ('Pending','Booked','Confirmed','Ongoing')")) {
          $ca->bind_param('s', $pEmail); $ca->execute(); $ca->close();
        }
      }
    }
  }
  $redirect = "admin_patient_history.php?patient_id={$patientId}";
  if (!empty($patientEmail)) { $redirect .= '&email=' . urlencode($patientEmail); }
  header("Location: $redirect");
  exit;
}

if (isset($conn) && $patientId > 0) {
  // Determine existing columns for phone and birthdate to avoid unknown column errors
  $hasPhone = false; $hasPhoneNum = false; $hasBday = false; $hasBirthdate = false; $hasDOB = false;
  if ($res = $conn->query("SHOW COLUMNS FROM tbl_patient LIKE 'Phone'")) { $hasPhone = $res->num_rows > 0; $res->close(); }
  if ($res = $conn->query("SHOW COLUMNS FROM tbl_patient LIKE 'Phone_num'")) { $hasPhoneNum = $res->num_rows > 0; $res->close(); }
  if ($res = $conn->query("SHOW COLUMNS FROM tbl_patient LIKE 'bday'")) { $hasBday = $res->num_rows > 0; $res->close(); }
  if ($res = $conn->query("SHOW COLUMNS FROM tbl_patient LIKE 'Birthdate'")) { $hasBirthdate = $res->num_rows > 0; $res->close(); }
  if ($res = $conn->query("SHOW COLUMNS FROM tbl_patient LIKE 'Date_of_Birth'")) { $hasDOB = $res->num_rows > 0; $res->close(); }

  // Build phone select
  if ($hasPhone && $hasPhoneNum) {
    $selectPhone = "COALESCE(NULLIF(Phone, ''), NULLIF(Phone_num, ''), '') AS Phone";
  } elseif ($hasPhone) {
    $selectPhone = "NULLIF(Phone, '') AS Phone";
  } elseif ($hasPhoneNum) {
    $selectPhone = "NULLIF(Phone_num, '') AS Phone";
  } else {
    $selectPhone = "'' AS Phone";
  }

  // Build birthdate select
  $bdParts = [];
  if ($hasBirthdate) { $bdParts[] = "NULLIF(Birthdate, '')"; }
  if ($hasDOB) { $bdParts[] = "NULLIF(Date_of_Birth, '')"; }
  if ($hasBday) { $bdParts[] = "NULLIF(bday, '')"; }
  $selectBirthdate = !empty($bdParts) ? ("COALESCE(" . implode(', ', $bdParts) . ", '') AS Birthdate") : "'' AS Birthdate";

  // Get patient information (robust across schemas)
  $sqlPatient = "SELECT 
      Patient_Id,
      Email,
      First_name,
      Middle_name,
      Last_name,
      COALESCE(Suffix, '') AS Suffix,
      $selectPhone,
      $selectBirthdate,
      COALESCE(Gender, '') AS Gender,
      COALESCE(Address, '') AS Address,
      COALESCE(Status, 'Active') AS Status
    FROM tbl_patient
    WHERE Patient_Id = ?";
  $stmt = $conn->prepare($sqlPatient);
  $stmt->bind_param("i", $patientId);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows > 0) {
    $patient = $result->fetch_assoc();
  }
  $stmt->close();
  if (!empty($patient)) {
    // Get total appointments count (including finished ones) for statistics (kept for overview)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_appointments WHERE Email = ?");
    $stmt->bind_param("s", $patient['Email']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
      $totalAppointments = $result->fetch_assoc()['total'];
    }
    $stmt->close();
    
    // Debug: Check what's in the treatment history for this patient
    $debugStmt = $conn->prepare("SELECT * FROM tbl_treatment_history WHERE Patient_Email = ?");
    $debugStmt->bind_param("s", $patient['Email']);
    $debugStmt->execute();
    $debugResult = $debugStmt->get_result();
    $debugCount = $debugResult->num_rows;
    error_log("Found $debugCount treatment history records for patient " . $patient['Email']);
    $debugStmt->close();
    
    // Removed: fetching active appointments to decouple history from appointments view
    
    // Get status filter
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
  
    // Debug: Log the status filter being used
    error_log("Status Filter: " . $statusFilter);
    
    // Unified: include Cancelled/Completed from appointments + Completed from treatment history
    // Ensure Duration column is optional across schemas
    $hasApptDuration = false; $hasThDuration = false;
    if ($tmp = $conn->query("SHOW COLUMNS FROM tbl_appointments LIKE 'Duration'")) { $hasApptDuration = $tmp->num_rows > 0; $tmp->close(); }
    if ($tmp = $conn->query("SHOW COLUMNS FROM tbl_treatment_history LIKE 'Duration'")) { $hasThDuration = $tmp->num_rows > 0; $tmp->close(); }
    $selApptDuration = $hasApptDuration ? "a.Duration AS Duration" : "NULL AS Duration";
    $selThDuration = $hasThDuration ? "th.Duration AS Duration" : "NULL AS Duration";

    $sql = "SELECT 
              a.Appointment_Id AS id,
              a.Patient_Id,
              a.Email AS Patient_Email,
              a.Dentist_Id,
              a.`Procedure` AS Procedure_Name,
              a.Date AS Treatment_Date,
              a.Time AS Treatment_Time,
              a.Room,
              a.Admin_Notes,
              COALESCE(d.Name, a.Dentist_Id) AS Dentist_Name,
              COALESCE(a.Status, 'Completed') AS Status,
              {$selApptDuration}
            FROM tbl_appointments a
            LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id
            WHERE a.Email = ? AND a.Status IN ('Finished','Completed','Cancelled')
            
            UNION ALL
            
            SELECT 
              0 AS id,
              th.Patient_Id,
              th.Patient_Email,
              th.Dentist_Id,
              th.Procedure_Name,
              th.Treatment_Date,
              th.Treatment_Time,
              th.Room,
              th.Admin_Notes,
              COALESCE(d2.Name, th.Dentist_Id) AS Dentist_Name,
              'Completed' AS Status,
              {$selThDuration}
            FROM tbl_treatment_history th
            LEFT JOIN tbl_dentist d2 ON th.Dentist_Id = d2.Dentist_id
            WHERE th.Patient_Email = ?
            
            ORDER BY Treatment_Date DESC, Treatment_Time DESC";
    
    // Debug: Log the SQL query
    error_log("SQL Query: " . $sql);
    error_log("Patient Email: " . $patient['Email']);
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $patient['Email'], $patient['Email']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $treatmentHistory[] = $row;
    }
    $stmt->close();
  
    // Analyze history to determine optional columns and data completeness
    $hasNextVisit = false;
    foreach ($treatmentHistory as $th) {
      $nextRaw = $th['Next_Visit_Date'] ?? ($th['Follow_Up_Date'] ?? '');
      if (!empty($nextRaw)) { $hasNextVisit = true; break; }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Patient History - Myles Dental Clinic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: Arial, sans-serif; }
    .sidebar { min-height: 100vh; background-color: #343a40; color: white; padding: 20px; }
    .sidebar a { color: white; text-decoration: none; display: block; margin: 10px 0; }
    .sidebar a:hover { text-decoration: underline; }
    .content { padding: 20px; min-height: 100vh; }
    .status-badge { font-size: 0.8em; }

    @media (max-width: 576px) {
      .content { padding: 12px; }
      .form-select-sm { font-size: 14px; padding: 0.5rem 0.75rem; height: auto; }
      .btn, .btn-sm { padding: 0.5rem 0.75rem; font-size: 14px; }
      table.table { font-size: 14px; }
    }
  </style>
</head>
<body>
<div class="d-flex">
  <?php if (file_exists($baseDir . '/admin_sidebar.php')) { include 'admin_sidebar.php'; } else { ?>
  <div class="sidebar">
    <h3>Myles Dental</h3>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="admin_appointments.php">Appointments</a>
    
    <a href="admin_patient_records.php" class="active">Patient Records</a>
    <a href="admin_messages.php">Messages</a>
    <a href="admin_inventory.php">Inventory</a>
    <a href="preferences.php">Settings</a>
    <a href="logout.php">Logout</a>
  </div>
  <?php } ?>

  <div class="content flex-grow-1">
    <?php if (!empty($patient) && strcasecmp((string)($patient['Status'] ?? ''), 'Blocked') === 0): ?>
      <div class="alert alert-danger d-flex align-items-center justify-content-between" role="alert">
        <div>
          <strong>Blocked patient.</strong> This patient is blocked; new bookings should not be allowed. Existing pending/active appointments have been cancelled.
        </div>
      </div>
    <?php endif; ?>
    <div class="mb-4">
      <div class="d-flex align-items-center justify-content-between">
        <h2 class="mb-0">Patient History</h2>
        <a href="admin_patient_records.php" class="btn btn-outline-secondary btn-sm">← Back to Patient Records</a>
      </div>
      <div class="d-flex justify-content-center gap-2 mt-2">
        <div class="card bg-primary text-white">
          <div class="card-body py-2 px-3 text-center">
            <div class="small">Total Appointments</div>
            <div class="fw-bold fs-5 mb-0"><?= $totalAppointments ?></div>
          </div>
        </div>
        <div class="card bg-success text-white">
          <div class="card-body py-2 px-3 text-center">
            <div class="small">Completed Treatments</div>
            <div class="fw-bold fs-5 mb-0"><?= count($treatmentHistory) ?></div>
          </div>
        </div>
      </div>
    </div>

    <?php if (empty($patient)): ?>
      <div class="alert alert-warning">
        <h4>Patient Not Found</h4>
        <p>The requested patient could not be found.</p>
        <a href="admin_patient_records.php" class="btn btn-primary">Back to Patient Records</a>
      </div>
    <?php else: ?>
      <!-- Patient Information -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Patient Information</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <p><strong>Name:</strong> <?= htmlspecialchars(trim($patient['First_name'] . ' ' . $patient['Middle_name'] . ' ' . $patient['Last_name'] . ' ' . ($patient['Suffix'] ?? ''))) ?></p>
              <p><strong>Email:</strong> <?= htmlspecialchars($patient['Email']) ?></p>
              <p><strong>Phone:</strong> <?= htmlspecialchars(($patient['Phone'] ?? '') !== '' ? $patient['Phone'] : 'Not provided') ?></p>
            </div>
            <div class="col-md-6">
              <p class="d-flex align-items-center gap-2 flex-wrap">
                <strong>Status:</strong> 
                <?php 
                  $statusRaw = (string)($patient['Status'] ?? 'Active');
                  $badgeCls = 'bg-secondary';
                  if (strcasecmp($statusRaw,'Active')===0) { $badgeCls = 'bg-success'; }
                  elseif (strcasecmp($statusRaw,'Blocked')===0) { $badgeCls = 'bg-danger'; }
                ?>
                <span class="badge <?= $badgeCls ?> status-badge">
                  <?= htmlspecialchars($statusRaw) ?>
                </span>
                <?php if (strcasecmp($statusRaw,'Blocked')!==0): ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="patient_status_action" value="<?= strcasecmp($statusRaw,'Active')===0 ? 'deactivate' : 'reactivate' ?>">
                    <button type="submit" class="btn btn-sm <?= strcasecmp($statusRaw,'Active')===0 ? 'btn-outline-danger' : 'btn-outline-success' ?>" onclick="return confirm('Are you sure you want to <?= strcasecmp($statusRaw,'Active')===0 ? 'deactivate' : 'reactivate' ?> this patient?');">
                      <?= strcasecmp($statusRaw,'Active')===0 ? 'Deactivate' : 'Reactivate' ?>
                    </button>
                  </form>
                <?php endif; ?>
                <form method="post" class="d-inline">
                  <?php $isBlocked = (strcasecmp($statusRaw,'Blocked')===0); ?>
                  <input type="hidden" name="patient_status_action" value="<?= $isBlocked ? 'unblock' : 'block' ?>">
                  <button type="submit" class="btn btn-sm <?= $isBlocked ? 'btn-outline-warning' : 'btn-outline-dark' ?>" onclick="return confirm('Are you sure you want to <?= $isBlocked ? 'unblock' : 'block' ?> this patient?');">
                    <?= $isBlocked ? 'Unblock' : 'Block' ?>
                  </button>
                </form>
              </p>
              <p><strong>Date of Birth:</strong> <?= htmlspecialchars(($patient['Birthdate'] ?? '') !== '' ? $patient['Birthdate'] : 'Not provided') ?></p>
              <p><strong>Gender:</strong> <?= htmlspecialchars(($patient['Gender'] ?? '') !== '' ? $patient['Gender'] : 'Not provided') ?></p>
              <p><strong>Address:</strong> <?= htmlspecialchars(($patient['Address'] ?? '') !== '' ? $patient['Address'] : 'Not provided') ?></p>
            </div>
          </div>
        </div>
      </div>

      

      <!-- Treatment History -->
      <?php if (!empty($treatmentHistory)): ?>
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <h5 class="mb-0">Treatment History</h5>
            <form method="get" class="d-flex gap-2">
              <input type="hidden" name="patient_id" value="<?= $patientId ?>">
              <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all" <?= ($statusFilter ?? 'all') === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="Completed" <?= ($statusFilter ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                <option value="Cancelled" <?= ($statusFilter ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
              </select>
            </form>
          </div>
          <span class="badge bg-dark">Finalized</span>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
              <thead>
                <tr>
                  <th>Appointment Date & Time</th>
                  <th>Time</th>
                  <th>Dentist/Doctor Name</th>
                  <th>Service/Procedure</th>
                  <th>Duration</th>
                  <th>Status</th>
                  <th>Notes</th>
                  <?php if ($hasNextVisit): ?>
                  <th>Next Recommended Visit</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($treatmentHistory as $treatment): ?>
                <?php 
                  $dt = trim(($treatment['Treatment_Date'] ?? '') . ' ' . ($treatment['Treatment_Time'] ?? ''));
                  $formattedDT = $dt ? date('M j, Y g:i A', strtotime($dt)) : '—';
                  $rawStatus = strtolower(trim($treatment['Status'] ?? ''));
                  if (in_array($rawStatus, ['finished','completed','done'])) { $statusLabel = 'Completed'; }
                  else if (in_array($rawStatus, ['cancelled','canceled'])) { $statusLabel = 'Cancelled'; }
                  else if (in_array($rawStatus, ['no-show','no show','noshow'])) { $statusLabel = 'No-show'; }
                  else if ($rawStatus === '') { $statusLabel = 'Completed'; } else { $statusLabel = ucfirst($rawStatus); }
                  $findingsRaw = $treatment['Findings'] ?? ($treatment['Admin_Notes'] ?? '');
                  $findings = trim((string)$findingsRaw) !== '' ? $findingsRaw : 'No notes';
                  $nextVisitRaw = $treatment['Next_Visit_Date'] ?? ($treatment['Follow_Up_Date'] ?? '');
                  $nextVisit = $nextVisitRaw ? date('M j, Y', strtotime($nextVisitRaw)) : '—';
                  // Apply status filter at render time
                  if (($statusFilter ?? 'all') !== 'all' && strtolower($statusLabel) !== strtolower($statusFilter)) {
                    continue;
                  }
                ?>
                <tr>
                  <td><?= htmlspecialchars(date('M j, Y', strtotime((string)($treatment['Treatment_Date'] ?? '')))) ?></td>
                  <td><?= htmlspecialchars(date('g:i A', strtotime((string)($treatment['Treatment_Time'] ?? '')))) ?></td>
                  <td><?= htmlspecialchars($treatment['Dentist_Name']) ?></td>
                  <td class="fw-medium"><?= htmlspecialchars($treatment['Procedure_Name']) ?></td>
                  <td>
                    <?php 
                      $durVal = $treatment['Duration'] ?? null; 
                      echo (is_numeric($durVal) && (int)$durVal > 0) ? (htmlspecialchars((string)$durVal) . ' minutes') : 'N/A'; 
                    ?>
                  </td>
                  <td>
                    <span class="badge 
                      <?php 
                        switch(strtolower($statusLabel)) {
                          case 'completed': echo 'bg-success'; break;
                          case 'cancelled': echo 'bg-danger'; break;
                          case 'no-show': echo 'bg-warning text-dark'; break;
                          default: echo 'bg-secondary';
                        }
                      ?>
                    ">
                      <?= htmlspecialchars(ucwords($statusLabel)) ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($findings) ?></td>
                  <?php if ($hasNextVisit): ?>
                  <td><?= htmlspecialchars($nextVisit) ?></td>
                  <?php endif; ?>
                  
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>

      
    <?php endif; ?>
  </div>
</div>
</body>
</html>