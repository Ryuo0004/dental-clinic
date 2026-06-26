<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['email'])) {
  header("Location: login.php");
  exit();
}

$email = $_SESSION['email'];

// Fetch patient data
$stmt = $conn->prepare("SELECT * FROM tbl_patient WHERE Email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  echo "Patient not found.";
  exit();
}
$patient = $result->fetch_assoc();
$full_name = $patient['First_name'] . ' ' . $patient['Middle_name'] . ' ' . $patient['Last_name'];
$stmt->close();

// Fetch treatment history records
$records = [];
$rec_stmt = $conn->prepare("SELECT th.History_Id, th.Appointment_Id, th.Treatment_Date, th.Treatment_Time, th.Procedure_Name, th.Admin_Notes, COALESCE(d.Name, th.Dentist_Id) as Dentist_Name FROM tbl_treatment_history th LEFT JOIN tbl_dentist d ON th.Dentist_Id = d.Dentist_id WHERE th.Patient_Email = ? ORDER BY th.Treatment_Date DESC, th.Treatment_Time DESC");
$rec_stmt->bind_param("s", $email);
$rec_stmt->execute();
$rec_result = $rec_stmt->get_result();
while ($row = $rec_result->fetch_assoc()) {
  // Treat items in treatment_history as Finished
  $row['Status'] = 'Finished';
  $records[] = $row;
}
$rec_stmt->close();

// Also include cancelled appointments
$can_stmt = $conn->prepare("SELECT a.Appointment_Id as History_Id, a.Appointment_Id, a.Date as Treatment_Date, a.Time as Treatment_Time, a.`Procedure` as Procedure_Name, a.Admin_Notes, COALESCE(d.Name, a.Dentist_Id) as Dentist_Name, 'Cancelled' AS Status FROM tbl_appointments a LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id WHERE a.Email = ? AND a.Status = 'Cancelled' ORDER BY a.Date DESC, a.Time DESC");
if ($can_stmt) {
  $can_stmt->bind_param("s", $email);
  $can_stmt->execute();
  $can_res = $can_stmt->get_result();
  while ($row = $can_res->fetch_assoc()) { $records[] = $row; }
  $can_stmt->close();
}

// If no records in treatment history, fallback to finished appointments
if (empty($records)) {
  $fallback_stmt = $conn->prepare("SELECT a.Appointment_Id as History_Id, a.Appointment_Id, a.Date as Treatment_Date, a.Time as Treatment_Time, a.`Procedure` as Procedure_Name, a.Admin_Notes, COALESCE(d.Name, a.Dentist_Id) as Dentist_Name, a.Status AS Status FROM tbl_appointments a LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id WHERE a.Email = ? AND a.Status IN ('Finished','Cancelled') ORDER BY a.Date DESC, a.Time DESC");
  $fallback_stmt->bind_param("s", $email);
  $fallback_stmt->execute();
  $fallback_result = $fallback_stmt->get_result();
  while ($row = $fallback_result->fetch_assoc()) {
    $records[] = $row;
  }
  $fallback_stmt->close();
}

// Summary metrics for header cards
$cmp = function($a, $b){
  $at = strtotime(($a['Treatment_Date'] ?? '1970-01-01') . ' ' . ($a['Treatment_Time'] ?? '00:00:00'));
  $bt = strtotime(($b['Treatment_Date'] ?? '1970-01-01') . ' ' . ($b['Treatment_Time'] ?? '00:00:00'));
  return $bt <=> $at; // DESC
};
if (!empty($records)) { usort($records, $cmp); }
$totalTreatments = 0;
$uniqueProcedures = 0;
if (!empty($records)) {
  $totalTreatments = count($records);
  $procs = [];
  foreach ($records as $r) {
    if (!empty($r['Procedure_Name'])) { $procs[] = (string)$r['Procedure_Name']; }
  }
  $uniqueProcedures = count(array_unique($procs));
}

// Collect inventory items used per appointment from tbl_inventory_log
$logsByAppt = [];
$apptIds = [];
foreach ($records as $r) {
  if (!empty($r['Appointment_Id'])) { $apptIds[] = (int)$r['Appointment_Id']; }
}
$apptIds = array_values(array_unique(array_filter($apptIds)));
if (count($apptIds) > 0) {
  $placeholders = implode(',', array_fill(0, count($apptIds), '?'));
  $types = str_repeat('i', count($apptIds));
  $logSql = "SELECT Appointment_Id, Item_Name, Quantity_Deducted, Status FROM tbl_inventory_log WHERE Appointment_Id IN ($placeholders)";
  $logStmt = $conn->prepare($logSql);
  if ($logStmt) {
    $logStmt->bind_param($types, ...$apptIds);
    $logStmt->execute();
    $logRes = $logStmt->get_result();
    while ($row = $logRes->fetch_assoc()) {
      $aid = (int)$row['Appointment_Id'];
      if (!isset($logsByAppt[$aid])) { $logsByAppt[$aid] = []; }
      $logsByAppt[$aid][] = $row;
    }
    $logStmt->close();
  }
}

// Removed treatment statistics summary cards and calculations
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Records</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="patients.css" />
  <style>
    /* Layout styles */
    .layout { display: flex; min-height: 100vh; }
    .content-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: 16px; }
    .content { flex: 1; padding: 24px; background: #ffffff; color: #111827; border-radius: 12px; margin: 16px; }
    /* Responsive Sidebar Toggle */
    .hamburger { position: fixed; left: 12px; top: 12px; z-index: 11000; display: none; padding: 10px 12px; border:1px solid #e5e7eb; background:#ffffff; color:#111827; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .hamburger svg { width:20px; height:20px; }
    .backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:10900; opacity:0; transition:opacity .2s ease; }
    @media (max-width: 900px) {
      .hamburger { display: inline-flex; align-items:center; gap:8px; }
      /* Sidebar off-canvas */
      .sidebar { position: fixed; left:0; top:0; bottom:0; height:100vh; transform: translateX(-100%); box-shadow:0 8px 24px rgba(0,0,0,0.1); transition: transform .25s ease; z-index: 11050; }
      body.sidebar-open .sidebar { transform: translateX(0); }
      body.sidebar-open .backdrop { display:block; opacity:1; }
    }
  </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
  <!-- Mobile Menu Toggle -->
  <button id="menuToggle" class="hamburger fixed top-4 left-4 z-50 bg-white p-2 rounded-md shadow-md md:hidden" aria-label="Toggle menu">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
    <span class="sr-only">Menu</span>
  </button>
  
  <!-- Backdrop for mobile menu -->
  <div id="drawerBackdrop" class="backdrop fixed inset-0 bg-black bg-opacity-50 z-40 hidden"></div>
  
  <!-- Main content wrapper -->
  <div class="layout">
    <?php if (file_exists(__DIR__ . '/patient_sidebar.php')) { include 'patient_sidebar.php'; } ?>

    <main class="content-wrapper">
      <div class="content">
        <div class="content-inner">
          <h1 class="text-2xl font-bold text-gray-800 mb-2">Your Dental Records</h1>
          <p class="text-gray-600 text-sm mb-6">View your complete treatment history and records</p>
      <?php if (count($records) > 0): ?>
        <!-- Treatment Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div class="bg-white shadow rounded-xl p-6 text-center">
            <div class="text-3xl font-bold text-blue-600"><?= $totalTreatments ?></div>
            <div class="text-sm text-gray-600">Total Treatments</div>
          </div>
          <div class="bg-white shadow rounded-xl p-6 text-center">
            <div class="text-3xl font-bold text-purple-600"><?= $uniqueProcedures ?></div>
            <div class="text-sm text-gray-600">Different Procedures</div>
          </div>
        </div>

        <!-- Treatment History Table -->
        <div class="bg-white shadow rounded-xl overflow-hidden">
          <div class="px-6 py-5 border-b border-gray-100">
            <h2 class="text-xl font-semibold text-gray-800">Treatment History</h2>
            <p class="text-sm text-gray-500 mt-1">Your complete dental treatment records</p>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 table-fixed">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider col-date">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider col-time">Time</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider col-treatment">Treatment</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider col-dentist">Dentist</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider col-notes">Notes</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($records as $rec): ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      <?= htmlspecialchars(date('M j, Y', strtotime($rec['Treatment_Date']))) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <?= htmlspecialchars(date('g:i A', strtotime($rec['Treatment_Time']))) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-normal">
                      <div class="text-sm font-medium text-gray-900 treatment-text"><?= htmlspecialchars($rec['Procedure_Name']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <?= htmlspecialchars((ctype_digit((string)($rec['Dentist_Name'])) ? 'Dr. #'.$rec['Dentist_Name'] : 'Dr. '.$rec['Dentist_Name'])) ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 whitespace-normal">
                      <?= htmlspecialchars($rec['Admin_Notes'] ?? 'No notes') ?>
                    </td>
                    <?php $st = strtoupper($rec['Status'] ?? 'Finished'); $chip = $st==='CANCELLED' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'; ?>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?= $chip ?>"><?= htmlspecialchars(ucfirst(strtolower($st))) ?></span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php else: ?>
        <div class="bg-white shadow rounded-xl p-12 text-center">
          <div class="text-6xl text-gray-300 mb-4">📋</div>
          <h2 class="text-xl font-semibold text-gray-700 mb-2">No Treatment History</h2>
          <p class="text-gray-600 mb-6">You haven't completed any treatments yet. Book an appointment to start building your dental care history.</p>
          <a href="appointment.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">Book Your First Appointment</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'footer.php'; ?>
  <style>
    /* Ensure proper layout and spacing */
    .sidebar-wrapper {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      z-index: 40;
      width: 260px;
      transition: transform 0.3s ease-in-out;
    }
    
    /* Adjust main content when sidebar is open on mobile */
    body.sidebar-open .sidebar-wrapper {
      transform: translateX(0);
    }
    
    /* Ensure content is properly spaced below fixed header on mobile */
    @media (max-width: 768px) {
      .sidebar-wrapper {
        transform: translateX(-100%);
      }
      
      body.sidebar-open .backdrop {
        display: block;
      }
      
      /* Add space for the fixed header on mobile */
      .flex-1 {
        padding-top: 4rem;
      }
    }
    
    /* Ensure content is properly aligned when sidebar is fixed */
    @media (min-width: 769px) {
      .flex-1 {
        margin-left: 260px;
        width: calc(100% - 260px);
      }
    }

    /* Records table layout: keep columns tight and clamp long treatment text */
    .table-fixed { table-layout: fixed; }
    .col-date { width: 110px; }
    .col-time { width: 90px; }
    .col-dentist { width: 180px; }
    .col-notes { width: 150px; }
    .col-treatment { width: auto; }
    .treatment-text {
      white-space: normal;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2; /* show up to 2 lines */
      -webkit-box-orient: vertical;
    }
    /* On small screens allow treatment to take more space */
    @media (max-width: 640px) {
      .col-dentist { width: 140px; }
      .col-notes { width: 120px; }
    }
  </style>
  <script>
    (function(){
      var btn=document.getElementById('menuToggle');
      var bd=document.getElementById('drawerBackdrop');
      function t(){ document.body.classList.toggle('sidebar-open'); }
      function c(){ document.body.classList.remove('sidebar-open'); }
      if(btn) btn.addEventListener('click', t);
      if(bd) bd.addEventListener('click', c);
      document.addEventListener('keydown', function(e){ if(e.key==='Escape') c(); });
    })();
  </script>

</body>
</html>
