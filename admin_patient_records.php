<?php
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include 'admin_init.php';
} else {
  include 'db_connect.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

// Get filter parameters
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$dateFrom = isset($_GET['from']) ? $_GET['from'] : '';
$dateTo = isset($_GET['to']) ? $_GET['to'] : '';

$patients = [];
$patientStats = [];

if (isset($conn)) {
  // Build the base query for patient records
  $sql = "SELECT DISTINCT p.Patient_Id, p.Email, p.First_name, p.Last_name, p.Middle_name, p.Status as Patient_Status,
                 COUNT(a.Appointment_Id) as Total_Appointments,
                 COUNT(CASE WHEN a.Status = 'Finished' THEN 1 END) as Completed_Treatments,
                 MAX(a.Date) as Last_Visit
          FROM tbl_patient p
          LEFT JOIN tbl_appointments a ON p.Email = a.Email
          WHERE 1=1";
  
  $params = [];
  $types = '';
  
  // Add search filter
  if ($searchQuery !== '') {
    $sql .= " AND (p.First_name LIKE ? OR p.Last_name LIKE ? OR p.Email LIKE ?)";
    $searchParam = '%' . $searchQuery . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
  }
  
  // Add status filter
  if ($statusFilter !== '') {
    $sql .= " AND p.Status = ?";
    $params[] = $statusFilter;
    $types .= 's';
  }
  
  // Add date range filter
  if ($dateFrom !== '') {
    $sql .= " AND COALESCE(a.Date, p.Date_Registered) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
  }
  
  if ($dateTo !== '') {
    $sql .= " AND COALESCE(a.Date, p.Date_Registered) <= ?";
    $params[] = $dateTo;
    $types .= 's';
  }
  
  $sql .= " GROUP BY p.Patient_Id, p.Email, p.First_name, p.Last_name, p.Middle_name, p.Status
            ORDER BY p.Last_name ASC, p.First_name ASC";
  
  try {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
      throw new Exception("Error preparing query: " . $conn->error);
    }
    
    if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
      throw new Exception("Error executing query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result === false) {
      throw new Exception("Error getting result set: " . $stmt->error);
    }
    
    while ($row = $result->fetch_assoc()) {
      $patients[] = $row;
    }
    $stmt->close();
  } catch (Exception $e) {
    error_log("Database error in admin_patient_records.php: " . $e->getMessage());
    $error = "An error occurred while fetching patient records. Please try again later.";
  }
  
  // Calculate overall statistics
  $statsQuery = "SELECT 
    COUNT(DISTINCT p.Patient_Id) as Total_Patients,
    COUNT(CASE WHEN a.Status = 'Finished' THEN 1 END) as Total_Completed_Treatments,
    COUNT(CASE WHEN a.Status IN ('Pending', 'Booked', 'Confirmed') THEN 1 END) as Active_Appointments
    FROM tbl_patient p
    LEFT JOIN tbl_appointments a ON p.Email = a.Email";
  
  $statsResult = $conn->query($statsQuery);
  if ($statsResult) {
    $patientStats = $statsResult->fetch_assoc();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Patient Records - Myles Dental Clinic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: Arial, sans-serif; }
    .sidebar { height: 100vh; background-color: #343a40; color: white; padding: 20px; }
    .sidebar a { color: white; text-decoration: none; display: block; margin: 10px 0; }
    .sidebar a:hover { text-decoration: underline; }
    .content { padding: 20px; }
    .patient-card { transition: transform 0.2s; }
    .patient-card:hover { transform: translateY(-2px); }
    /* KPI cards (Total Patients, Completed Treatments) */
    .kpi-card { 
      border: none; 
      border-radius: 14px; 
      box-shadow: 0 10px 20px rgba(0,0,0,0.08); 
      overflow: hidden;
      min-height: 120px;
    }
    .kpi-card .card-body { padding: 22px 18px; }
    .kpi-card .card-title { font-weight: 700; letter-spacing: .2px; opacity: .95; }
    .kpi-card h3 { font-size: 2rem; font-weight: 800; margin-top: 6px; }
    .kpi-blue { background: linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; }
    .kpi-green { background: linear-gradient(135deg,#10b981,#059669); color:#fff; }
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
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h2 class="mb-0">Patient Records</h2>
      <div>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
      </div>
    </div>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Statistics Overview -->
    <div class="row mb-4 justify-content-center">
      <div class="col-md-4 col-lg-3">
        <div class="card kpi-card kpi-blue">
          <div class="card-body text-center">
            <h5 class="card-title">Total Patients</h5>
            <h3 class="mb-0"><?= $patientStats['Total_Patients'] ?? 0 ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-lg-3">
        <div class="card kpi-card kpi-green">
          <div class="card-body text-center">
            <h5 class="card-title">Completed Treatments</h5>
            <h3 class="mb-0"><?= $patientStats['Total_Completed_Treatments'] ?? 0 ?></h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="card-body">
        <form method="get" class="row g-2 align-items-end filters-card">
          <div class="col-12 col-md-4">
            <label class="form-label small text-muted mb-1">Search Patient</label>
            <input type="text" class="form-control form-control-sm" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Name or email">
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1">Status</label>
            <select class="form-select form-select-sm" name="status">
              <option value="">All Status</option>
              <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
              <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1">From Date</label>
            <input type="date" class="form-control form-control-sm" name="from" value="<?= htmlspecialchars($dateFrom) ?>">
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1">To Date</label>
            <input type="date" class="form-control form-control-sm" name="to" value="<?= htmlspecialchars($dateTo) ?>">
          </div>
          <div class="col-6 col-md-2 d-flex gap-2 justify-content-start justify-content-md-end">
            <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
            <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Patient Records -->
    <div class="row">
      <?php if (empty($patients)): ?>
        <div class="col-12">
          <div class="card">
            <div class="card-body text-center py-5">
              <h5 class="text-muted">No patients found</h5>
              <p class="text-muted">Try adjusting your search criteria</p>
            </div>
          </div>
        </div>
      <?php else: foreach ($patients as $patient): ?>
        <div class="col-md-6 col-lg-4 mb-4">
          <div class="card patient-card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                  <h5 class="card-title mb-1">
                    <?= htmlspecialchars($patient['First_name'] . ' ' . $patient['Last_name']) ?>
                  </h5>
                  <?php 
                    $isWalkInEmail = (bool)preg_match('/^walkin.*@clinic\.local$/i', (string)$patient['Email']);
                    $displayEmail = $isWalkInEmail ? 'Walk-in Patient' : $patient['Email'];
                  ?>
                  <p class="text-muted mb-0"><?= htmlspecialchars($displayEmail) ?></p>
                </div>
                <span class="badge <?= $patient['Patient_Status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>">
                  <?= htmlspecialchars($patient['Patient_Status']) ?>
                </span>
              </div>
              
              <div class="row text-center mb-3">
                <div class="col-6">
                  <div class="border-end pe-2">
                    <h6 class="mb-0 text-primary fw-bold"><?= $patient['Total_Appointments'] ?></h6>
                    <small class="text-muted">Appointments</small>
                  </div>
                </div>
                <div class="col-6">
                  <div class="ps-2">
                    <h6 class="mb-0 text-success fw-bold"><?= $patient['Completed_Treatments'] ?></h6>
                    <small class="text-muted">Completed</small>
                  </div>
                </div>
              </div>
              
              <?php if ($patient['Last_Visit']): ?>
                <p class="text-muted small mb-3">
                  <i class="bi bi-calendar"></i> Last visit: <?= date('M j, Y', strtotime($patient['Last_Visit'])) ?>
                </p>
              <?php endif; ?>
              
              <div class="d-grid gap-2">
                <a href="admin_patient_history.php?patient_id=<?= $patient['Patient_Id'] ?>&email=<?= urlencode($patient['Email']) ?>" class="btn btn-outline-primary btn-sm">
                  View Full History
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- Patient Details Modal -->
<div class="modal fade" id="patientModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Patient Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="patientModalBody">
        <!-- Content will be loaded here -->
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewPatientDetails(patientId, email) {
  // This would load detailed patient information
  document.getElementById('patientModalBody').innerHTML = `
    <div class="text-center">
      <h6>Patient ID: ${patientId}</h6>
      <p>Email: ${email}</p>
      <p class="text-muted">Detailed patient information would be loaded here.</p>
    </div>
  `;
  new bootstrap.Modal(document.getElementById('patientModal')).show();
}

function viewPatientHistory(patientId, email) {
  // This would show the patient's complete treatment history
  document.getElementById('patientModalBody').innerHTML = `
    <div class="text-center">
      <h6>Treatment History</h6>
      <p>Patient: ${email}</p>
      <p class="text-muted">Complete treatment history would be loaded here.</p>
    </div>
  `;
  new bootstrap.Modal(document.getElementById('patientModal')).show();
}
</script>
</body>
</html>
