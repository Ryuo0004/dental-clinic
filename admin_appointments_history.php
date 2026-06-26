<?php
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include 'admin_init.php';
} else {
  include 'db_connect.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

$q = trim($_GET['q'] ?? '');
$dentistFilter = trim($_GET['dentist'] ?? 'all');
$statusFilter = trim($_GET['status'] ?? 'all'); // Completed, Cancelled, No-show, all
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$history = [];
$dentists = [];

if (isset($conn)) {
  $hasDent = $conn->query("SHOW TABLES LIKE 'tbl_dentist'");
  if ($hasDent && $hasDent->num_rows > 0) {
    $res = $conn->query("SELECT Dentist_id, COALESCE(NULLIF(TRIM(Name), ''), CONCAT('Dentist #', Dentist_id)) AS Name FROM tbl_dentist ORDER BY Name ASC");
    if ($res) { while ($row = $res->fetch_assoc()) { $dentists[] = $row; } }
  }

  
  $showAll = $statusFilter === 'all';
  $useAppointments = $showAll || in_array(strtolower($statusFilter), ['cancelled']);
  $useTreatmentHistory = $showAll || !$useAppointments;
  
  $appointmentsQuery = "";
  $treatmentHistoryQuery = "";
  
  if ($useAppointments) {
    $appointmentsQuery = "SELECT a.Email AS Patient_Email, a.Patient_Id, a.Dentist_Id,
                   a.`Procedure` AS Procedure_Name, a.Date AS Treatment_Date, a.Time AS Treatment_Time,
                   
                   CASE 
                     WHEN (COALESCE(cac.Reason,'') <> '' OR COALESCE(Admin_Notes,'') <> '') THEN 
                       TRIM(CONCAT(
                         COALESCE(cac.Reason, ''),
                         CASE WHEN COALESCE(cac.Reason,'') <> '' AND COALESCE(Admin_Notes,'') <> '' THEN ' — ' ELSE '' END,
                         COALESCE(Admin_Notes, '')
                       ))
                     ELSE COALESCE(a.Admin_Notes, '')
                   END AS Findings,
                   a.Status AS Status,
                   CONCAT(COALESCE(p.First_name,''),' ',COALESCE(p.Last_name,'')) AS Patient_Name,
                   COALESCE(d.Name, CONCAT('Dentist #', a.Dentist_Id)) AS Dentist_Name
            FROM tbl_appointments a
            LEFT JOIN tbl_patient p ON p.Email = a.Email
            LEFT JOIN tbl_dentist d ON d.Dentist_id = a.Dentist_Id
            LEFT JOIN (
              SELECT t1.* FROM tbl_appointment_cancellations t1
              JOIN (
                SELECT Appointment_Id, MAX(Created_At) AS mx
                FROM tbl_appointment_cancellations
                GROUP BY Appointment_Id
              ) t2 ON t1.Appointment_Id = t2.Appointment_Id AND t1.Created_At = t2.mx
            ) cac ON cac.Appointment_Id = a.Appointment_Id
            WHERE 1=1";
    
    if (!$showAll) {
      $appointmentsQuery .= " AND LOWER(COALESCE(a.Status,'')) IN ('cancelled','canceled')";
    } else {
      $appointmentsQuery .= " AND LOWER(COALESCE(a.Status,'')) IN ('cancelled','canceled')";
    }
  }
  
  
  if ($useTreatmentHistory) {
    $treatmentHistoryQuery = "SELECT th.Patient_Email, th.Patient_Id, th.Dentist_Id,
                   th.Procedure_Name, th.Treatment_Date, th.Treatment_Time,
                   
                   COALESCE(th.Admin_Notes, '') AS Findings, 
                   'Completed' AS Status,
                   CONCAT(COALESCE(p.First_name,''),' ',COALESCE(p.Last_name,'')) AS Patient_Name,
                   COALESCE(d.Name, CONCAT('Dentist #', th.Dentist_Id)) AS Dentist_Name
            FROM tbl_treatment_history th
            LEFT JOIN tbl_patient p ON p.Email = th.Patient_Email
            LEFT JOIN tbl_dentist d ON d.Dentist_id = th.Dentist_Id
            WHERE 1=1";
  }
  
 
  $buildConditions = function($alias) use ($q, $dateFrom, $dateTo, $dentistFilter, &$types, &$params) {
    $conds = '';
    
    if ($q !== '') {
      $conds .= " AND (p.First_name LIKE ? OR p.Last_name LIKE ? OR CONCAT(p.First_name, ' ', p.Last_name) LIKE ? OR " 
              . ($alias === 'a' ? "a.Email" : "th.Patient_Email") . " LIKE ? OR "
              . ($alias === 'a' ? "a.Procedure" : "th.Procedure_Name") . " LIKE ? OR d.Name LIKE ?)";
      $like = '%' . $q . '%';
      array_push($params, $like, $like, $like, $like, $like, $like);
      $types .= str_repeat('s', 6);
    }
    
    if ($dateFrom !== '' && $dateTo !== '') {
      $conds .= $alias === 'a' ? " AND a.Date BETWEEN ? AND ?" : " AND th.Treatment_Date BETWEEN ? AND ?";
      array_push($params, $dateFrom, $dateTo);
      $types .= 'ss';
    } elseif ($dateFrom !== '') {
      $conds .= $alias === 'a' ? " AND a.Date >= ?" : " AND th.Treatment_Date >= ?";
      array_push($params, $dateFrom);
      $types .= 's';
    } elseif ($dateTo !== '') {
      $conds .= $alias === 'a' ? " AND a.Date <= ?" : " AND th.Treatment_Date <= ?";
      array_push($params, $dateTo);
      $types .= 's';
    }
    
    if ($dentistFilter !== 'all' && ctype_digit($dentistFilter)) {
      $conds .= $alias === 'a' ? " AND a.Dentist_Id = ?" : " AND th.Dentist_Id = ?";
      array_push($params, (int)$dentistFilter);
      $types .= 'i';
    }
    
    return $conds;
  };

  $types = '';
  $params = [];
  $sql = '';

  if ($showAll && $useAppointments && $useTreatmentHistory) {
    $condsA = $buildConditions('a');
    $condsT = $buildConditions('th');
    
    $sql = "(" . $appointmentsQuery . $condsA . ") UNION ALL (" . $treatmentHistoryQuery . $condsT . ")";
    $sql .= " ORDER BY Patient_Name ASC, Treatment_Date ASC, Treatment_Time ASC LIMIT 200";
    
  } elseif ($useAppointments) {
    
    $condsA = $buildConditions('a');
    $sql = $appointmentsQuery . $condsA . " ORDER BY Patient_Name ASC, a.Date ASC, a.Time ASC LIMIT 200";
    
  } elseif ($useTreatmentHistory) {
    $condsT = $buildConditions('th');
    $sql = $treatmentHistoryQuery . $condsT . " ORDER BY Patient_Name ASC, th.Treatment_Date ASC, th.Treatment_Time ASC LIMIT 200";
  }

  if (!empty($sql)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
      if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
      }
      
      if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { 
          $history[] = $row; 
        }
      } else {
        error_log("SQL Execution Error: " . $stmt->error);
      }
      $stmt->close();
    } else {
      error_log("SQL Prepare Error: " . $conn->error);
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Appointment History - Miles Dental Clinic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: Arial, sans-serif; margin: 0; }
    .content { padding: 16px; min-height: 100vh; overflow: auto; background: #ffffff; color: #111827; }
    .card, .alert { background: #ffffff; color: #111827; border: 1px solid #e5e7eb; }
    .table thead { color: #111827; }
    .badge-status { font-weight: 600; border-radius: 999px; padding: 6px 10px; font-size: 12px; }
    .bg-completed { background-color: #16a34a; }
    .bg-cancelled { background-color: #dc2626; }
    .bg-noshow { background-color: #f59e0b; color: #111827; }
    .empty-state { border: 1px dashed #e5e7eb; border-radius: 12px; padding: 24px; text-align: center; color: #6b7280; background: #ffffff; }
    /* Quick range buttons */
    .quick-range { display:flex; gap:8px; align-items:end; flex-wrap:wrap; }
    .quick-range .btn { min-width: 110px; }
    .filter-form .quick-range .btn { height: 38px; }

    /* Filter bar helpers for perfect alignment */
    .filter-form .col-auto { display:flex; flex-direction:column; justify-content:flex-end; }
    .filter-form .form-label.small { min-height:18px; display:inline-block; margin-bottom:4px; }
    .filter-form .fixed-180 { min-width:180px; }
    .filter-form .fixed-actions { min-width:160px; }
    .filter-form .actions-label { min-height:18px; margin-bottom:4px; visibility:hidden; }

    /* Small screens: improve tap targets and layout */
    @media (max-width: 576px) {
      .content { padding: 12px; }
      .row.g-2 > [class*='col-'] { margin-bottom: 8px; }
      .form-select-sm, .form-control-sm { font-size: 14px; padding: 0.5rem 0.75rem; height: auto; }
      .btn-sm { padding: 0.5rem 0.75rem; font-size: 14px; }
      table.table { font-size: 14px; }
    }
  </style>
</head>
<body>
<div class="d-flex" style="height:100vh;">
  <?php if (file_exists($baseDir . '/admin_sidebar.php')) { include 'admin_sidebar.php'; } ?>
  <div class="content flex-grow-1">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0">Appointment History</h2>
      <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
    </div>

    <form method="get" class="row g-2 align-items-end mb-3 filter-form" id="filterForm">
      <div class="col flex-grow-1 d-flex flex-column justify-content-end">
        <label class="form-label small text-muted">Search patient/doctor/service</label>
        <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Name, email, doctor, service">
      </div>
      <div class="col-auto d-flex flex-column justify-content-end">
        <label class="form-label small text-muted">Doctor</label>
        <select class="form-select form-select-sm" name="dentist">
          <option value="all" <?= $dentistFilter==='all'?'selected':'' ?>>All</option>
          <?php foreach ($dentists as $d): ?>
            <option value="<?= (int)$d['Dentist_id'] ?>" <?= ((string)$dentistFilter===(string)$d['Dentist_id'])?'selected':'' ?>><?= htmlspecialchars($d['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex flex-column justify-content-end">
        <label class="form-label small text-muted">Status</label>
        <select class="form-select form-select-sm" name="status">
          <?php foreach (['all','Completed','Cancelled'] as $st): ?>
            <option value="<?= $st ?>" <?= ($statusFilter===$st)?'selected':'' ?>><?= $st==='all'?'All status':$st ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto fixed-180 d-flex flex-column justify-content-end">
        <label class="form-label small text-muted">From</label>
        <input type="date" class="form-control form-control-sm" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div class="col-auto fixed-180 d-flex flex-column justify-content-end">
        <label class="form-label small text-muted">To</label>
        <input type="date" class="form-control form-control-sm" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      
      <div class="col-auto ms-auto d-flex flex-column align-items-end justify-content-end gap-2 align-self-end mt-0 fixed-actions">
        <label class="form-label small text-muted actions-label">Actions</label>
        <div class="d-flex flex-nowrap gap-2">
          <a class="btn btn-outline-secondary btn-sm" href="admin_appointments_history.php">Clear</a>
          <button class="btn btn-primary btn-sm" type="submit">Filter</button>
        </div>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th>Name</th>
            <th>Service/Procedure</th>
            <th>Appointment Date & Time</th>
            <th>Doctor</th>
            <th>Notes</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($history)): ?>
            <tr>
              <td colspan="6">
                <div class="empty-state">
                  <?php if (!empty($q) || !empty($dateFrom) || !empty($dateTo) || $dentistFilter !== 'all' || $statusFilter !== 'all'): ?>
                    No history matches your filters.
                  <?php else: ?>
                    No appointment history found.
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($history as $h): ?>
            <?php 
              $dt = trim(($h['Treatment_Date'] ?? '') . ' ' . ($h['Treatment_Time'] ?? ''));
              $formattedDT = $dt ? date('M j, Y g:i A', strtotime($dt)) : '—';
              $raw = strtolower(trim($h['Status'] ?? ''));
              if (in_array($raw, ['finished','completed','done',''])) { $statusLabel = 'Completed'; $cls = 'bg-completed'; }
              elseif (in_array($raw, ['cancelled','canceled'])) { $statusLabel = 'Cancelled'; $cls = 'bg-cancelled'; }
              elseif (in_array($raw, ['no-show','no show','noshow'])) { $statusLabel = 'No-show'; $cls = 'bg-noshow'; }
              else { $statusLabel = ucfirst($raw); $cls = 'bg-noshow'; }
              $find = trim((string)($h['Findings'] ?? '')) !== '' ? $h['Findings'] : 'No notes';
            ?>
            <tr>
              <?php 
                $hName = trim((string)($h['Patient_Name'] ?? ''));
                $hEmail = (string)($h['Patient_Email'] ?? '');
                $hIsWalkIn = preg_match('/^walkin.*@clinic\.local$/i', $hEmail) === 1;
                $hDisplay = $hName !== '' ? $hName : ($hIsWalkIn ? 'Walk-in Patient' : $hEmail);
              ?>
              <td><?= htmlspecialchars($hDisplay) ?></td>
              <td><?= htmlspecialchars($h['Procedure_Name']) ?></td>
              <td><?= htmlspecialchars($formattedDT) ?></td>
              <td><?= htmlspecialchars($h['Dentist_Name']) ?></td>
              <td>
                <?php if (trim((string)$find) !== ''): ?>
                  <span title="<?= htmlspecialchars($find) ?>">
                    <?= htmlspecialchars(mb_strimwidth($find, 0, 60, '…')) ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted">No notes</span>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-status <?= $cls ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('filterForm');
  if (form) {
    form.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
      }
    });
  }
});
</script>
</body>
</html>