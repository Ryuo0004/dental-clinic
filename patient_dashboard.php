<?php 
session_start();
include 'db_connect.php';
include 'settings_helper.php';

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
$status = $patient['Status'];

// Local fallback map for dentist names (in case tbl_dentist has no rows)
$dentistNameById = [
    1 => 'Dr. Carrel Miles Cabrales',
    2 => 'Dr. Catherine Aben Reyes',
    3 => 'Dr. Kyle Eve Evangelista',
    4 => 'Dr. Bernadine Bungaoen',
];

// Fetch next appointment (excluding completed/finished appointments)
$next_appt_stmt = $conn->prepare("SELECT a.Appointment_Id, a.Date, a.Time, a.Status, COALESCE(d.Name, CONCAT('Dentist #', a.Dentist_Id)) as Dentist_Name FROM tbl_appointments a LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id WHERE a.Email = ? AND a.Date >= CURDATE() AND a.Status NOT IN ('Finished','Cancelled') ORDER BY a.Date ASC LIMIT 1");
$next_appt_stmt->bind_param("s", $email);
$next_appt_stmt->execute();
$next_appt_result = $next_appt_stmt->get_result();
$next_appt = $next_appt_result->fetch_assoc();
$next_appt_stmt->close();

// Fetch last checkup (last completed appointment)
$last_checkup_stmt = $conn->prepare("SELECT a.Date, COALESCE(d.Name, a.Dentist_Id) as Dentist_Name FROM tbl_appointments a LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id WHERE a.Email = ? AND a.Status = 'Finished' ORDER BY a.Date DESC LIMIT 1");
$last_checkup_stmt->bind_param("s", $email);
$last_checkup_stmt->execute();
$last_checkup_stmt->bind_result($last_checkup_date, $last_checkup_dentist);
$last_checkup_stmt->fetch();
$last_checkup_stmt->close();

// Health score (dummy logic: excellent if last checkup < 6 months ago)
$health_score = 'Unknown';
if ($last_checkup_date) {
    $months = (strtotime(date('Y-m-d')) - strtotime($last_checkup_date)) / (30*24*60*60);
    $health_score = $months < 6 ? 'Excellent' : ($months < 12 ? 'Good' : 'Needs Attention');
}


// Fetch upcoming appointments (limit 3, excluding finished appointments)
$appts = [];
$appt_list_stmt = $conn->prepare("SELECT a.Appointment_Id, a.Patient_Id, a.Date, a.Time, a.Status, COALESCE(d.Name, CONCAT('Dentist #', a.Dentist_Id)) as Dentist_Name FROM tbl_appointments a LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id WHERE a.Email = ? AND a.Date >= CURDATE() AND a.Status NOT IN ('Finished','Cancelled') ORDER BY a.Date ASC LIMIT 3");
$appt_list_stmt->bind_param("s", $email);
$appt_list_stmt->execute();
$appt_result = $appt_list_stmt->get_result();
while ($row = $appt_result->fetch_assoc()) {
    $appts[] = $row;
}
$appt_list_stmt->close();

// Fetch recent messages (limit 3)
$messages = [];
$msg_list_stmt = $conn->prepare("SELECT Id, sender, message, is_read, sent_at FROM tbl_messages WHERE Email = ? ORDER BY sent_at DESC LIMIT 3");
$msg_list_stmt->bind_param("s", $email);
$msg_list_stmt->execute();
$msg_result = $msg_list_stmt->get_result();
while ($row = $msg_result->fetch_assoc()) {
    $messages[] = $row;
}
$msg_list_stmt->close();

// Unread messages count
$unread_count = 0;
$unread_stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_messages WHERE Email = ? AND is_read = 0");
$unread_stmt->bind_param("s", $email);
$unread_stmt->execute();
$unread_stmt->bind_result($unread_count);
$unread_stmt->fetch();
$unread_stmt->close();

// Fetch treatment history (last 5 treatments)
$treatments = [];
$treatment_stmt = $conn->prepare("SELECT a.Date, a.Status, COALESCE(d.Name, CONCAT('Dentist #', a.Dentist_Id)) as Dentist_Name FROM tbl_appointments a LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id WHERE a.Email = ? AND a.Status = 'Finished' ORDER BY a.Date DESC LIMIT 5");
$treatment_stmt->bind_param("s", $email);
$treatment_stmt->execute();
$treatment_result = $treatment_stmt->get_result();
while ($row = $treatment_result->fetch_assoc()) {
    $treatments[] = $row;
}
$treatment_stmt->close();

// Get clinic settings
$clinicBranding = getClinicBranding($conn);
$emergencyContacts = getEmergencyContacts($conn);
$appointmentSettings = getAppointmentSettings($conn);

// Compute greeting using clinic timezone (falls back to server timezone)
$greeting = 'Hello';
try {
    $tz = !empty($clinicBranding['timezone']) ? $clinicBranding['timezone'] : date_default_timezone_get();
    $nowTz = new DateTime('now', new DateTimeZone($tz));
    $h = (int)$nowTz->format('G');
    if ($h < 12) { $greeting = 'Good morning'; }
    elseif ($h < 18) { $greeting = 'Good afternoon'; }
    else { $greeting = 'Good evening'; }
} catch (Exception $e) {
    $h = (int)date('G');
    if ($h < 12) { $greeting = 'Good morning'; }
    elseif ($h < 18) { $greeting = 'Good afternoon'; }
    else { $greeting = 'Good evening'; }
}

// Helper function to format time (now uses clinic timezone)
function formatTime($time, $timezone = null) {
    if (empty($time)) return 'N/A';
    if ($timezone) {
        return formatTimeWithTimezone($time, $timezone);
    }
    $timestamp = strtotime($time);
    return date('g:i A', $timestamp);
}

$alert = '';
if (isset($_GET['success'])) {
    $alert = '<div class="bg-green-100 text-green-700 px-4 py-2 rounded mb-4">Profile updated successfully!</div>';
} elseif (isset($_GET['error'])) {
    $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Error updating profile.</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= htmlspecialchars($clinicBranding['name']) ?> - Patient Dashboard</title>
  <?php include 'header.php'; ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="patients.css" />
  <style>
    html { scroll-behavior: smooth; }
    tr.clickable-row { cursor: pointer; }
    /* Responsive helpers for dashboard */
    .section-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .table-responsive { overflow-x: auto; }
    /* Ensure sidebar and content are side-by-side with proper spacing */
    .layout { display: flex; min-height: 100vh; }
    .content-wrapper { flex: 1 1 auto; display: flex; flex-direction: column; margin-left: 16px !important; }
    .content { flex: 1 1 auto; padding: 24px; overflow-y: auto; background:#ffffff; color:#111827; border-radius: 12px; margin: 16px; }
    .content-inner { max-width: none; width: 100%; margin-left: 0; margin-right: 0; }
    h1.text-2xl { margin-top: 0; margin-bottom: 12px; color:#0f172a; }
    /* Unified tiles */
    .tile { border:1px solid #e5e7eb; background:#ffffff; border-radius:16px; padding:20px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
    .tile h3 { margin:0; font-weight:600; color:#0f172a; }
    .tile .subtitle { color:#64748b; font-size: 12px; }
    .tile-tint-blue { background:#f5f8ff; }
    .tile-tint-green { background:#f1fbf4; }
    .tile-tint-purple { background:#faf6ff; }
    .tile-tint-orange { background:#fff8ef; }
    .icon-chip { width:40px; height:40px; border-radius:999px; display:grid; place-items:center; }
    .chip-blue { background:#e6eeff; color:#3366ff; }
    .chip-green { background:#dcfce7; color:#059669; }
    .chip-purple { background:#efe7ff; color:#7c3aed; }
    .chip-orange { background:#ffedd5; color:#ea580c; }
    /* Section header like admin */
    .section-header h4 { margin:0; color:#111827; }
    .btn { background:#2563eb; color:#fff; border:1px solid #e5e7eb; padding:8px 12px; border-radius:8px; text-decoration:none; font-weight:600; }
    .btn:hover { background:#1e40af; color:#fff; }
    /* Responsive Sidebar Toggle */
    .hamburger { position: fixed; left: 12px; top: 12px; z-index: 11000; display: none; padding: 10px 12px; border:1px solid #e5e7eb; background:#ffffff; color:#111827; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .hamburger svg { width:20px; height:20px; }
    .backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:10900; opacity:0; transition:opacity .2s ease; }
    /* Mobile cards for appointments */
    .app-table { display: block; }
    .app-cards { display: none; }
    @media (max-width: 640px) {
      .app-table { display: none; }
      .app-cards { display: grid; gap: 12px; }
      .app-card { border: 1px solid #e5e7eb; border-radius: 12px; background: #ffffff; padding: 12px; }
      .app-card .row1 { display:flex; align-items:center; justify-content:space-between; gap:8px; }
      .app-card .meta { color:#64748b; font-size: 12px; }
      .badge { display:inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
      .badge.green { background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; }
      .badge.yellow { background:#fefce8; color:#854d0e; border:1px solid #fde68a; }
      .badge.gray { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }
      .btn-outline { display:inline-block; padding:6px 10px; border:1px solid #e5e7eb; border-radius:8px; color:#111827; text-decoration:none; font-weight:600; }
    }
    @media (max-width: 900px) {
      .hamburger { display: inline-flex; align-items:center; gap:8px; }
      .layout { display:block; }
      .content-wrapper { margin: 0 !important; }
      .content { margin: 0; border-radius: 0; padding: 16px; }
      /* Sidebar becomes off-canvas */
      .sidebar { position: fixed; left:0; top:0; bottom:0; height:100vh; transform: translateX(-100%); box-shadow:0 8px 24px rgba(0,0,0,0.1); transition: transform .25s ease; }
      body.sidebar-open .sidebar { transform: translateX(0); }
      body.sidebar-open .backdrop { display:block; opacity:1; }
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800">
<button id="menuToggle" class="hamburger" aria-label="Toggle menu">
  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
  Menu
</button>
<div id="drawerBackdrop" class="backdrop"></div>

<div class="layout">
  <!-- Header removed -->

  <?php if (file_exists(__DIR__ . '/patient_sidebar.php')) { include 'patient_sidebar.php'; } ?>

  <main class="content-wrapper">
    <div class="content">
    <div class="content-inner">
    <?= $alert ?>
    <h1 class="text-2xl font-semibold text-blue-700 mb-4"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($patient['First_name']) ?>!</h1>
    <p class="mb-6 text-gray-600">Welcome back to your Miles dental care dashboard. Stay on top of your oral health journey.</p>

    <!-- Primary Tiles -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="tile tile-tint-blue">
        <div class="flex items-start gap-3">
          <div class="icon-chip chip-blue">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </div>
          <div>
            <div class="subtitle">Next Appointment</div>
            <div class="text-lg font-semibold text-blue-700">
              <?= $next_appt ? htmlspecialchars($next_appt['Date']) : 'None' ?>
            </div>
            <?php if ($next_appt && $next_appt['Dentist_Name']): ?>
              <?php
                $dentistDisplay = $next_appt['Dentist_Name'];
                if (ctype_digit((string)$dentistDisplay) && isset($dentistNameById[(int)$dentistDisplay])) {
                  $dentistDisplay = $dentistNameById[(int)$dentistDisplay];
                } else {
                  $dentistDisplay = 'Dr. ' . $dentistDisplay;
                }
              ?>
              <div class="subtitle">with <?= htmlspecialchars($dentistDisplay) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="tile tile-tint-green">
        <div class="flex items-start gap-3">
          <div class="icon-chip chip-green">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          </div>
          <div>
            <div class="subtitle">Last Checkup</div>
            <div class="text-lg font-semibold text-green-700">
              <?= $last_checkup_date ? htmlspecialchars($last_checkup_date) : 'N/A' ?>
            </div>
          </div>
        </div>
      </div>
      <div class="tile tile-tint-purple">
        <div class="flex items-start gap-3">
          <div class="icon-chip chip-purple">✦</div>
          <div>
            <div class="subtitle">Health Score</div>
            <div class="text-lg font-semibold text-purple-700"><?= htmlspecialchars($health_score) ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-8">
      <a href="appointment.php" class="tile tile-tint-blue text-center flex flex-col items-center justify-center gap-2">
        <div class="icon-chip chip-blue">＋</div>
        <h3>Book Appointment</h3>
        <div class="subtitle">Schedule your next visit</div>
      </a>
      <a href="profile.php" class="tile tile-tint-green text-center flex flex-col items-center justify-center gap-2">
        <div class="icon-chip chip-green">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </div>
        <h3>Update Profile</h3>
        <div class="subtitle">Edit your information</div>
      </a>
      <a href="support.php" class="tile tile-tint-orange text-center flex flex-col items-center justify-center gap-2">
        <div class="icon-chip chip-orange">?</div>
        <h3>Get Help</h3>
        <div class="subtitle">Contact support</div>
      </a>
    </div>

    <!-- Upcoming Appointments -->
    <div id="appointments" class="section">
      <div class="section-header">
        <h4>Upcoming Appointments</h4>
        <a href="appointment.php" class="btn">Book Appointment</a>
      </div>
      <?php if (count($appts) > 0): ?>
        <div class="table-responsive app-table">
        <table class="table min-w-[640px] w-full">
          <thead>
            <tr>
              <th>Date</th>
              <th>Time</th>
              <th>Dentist</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($appts as $appt): ?>
              <tr>
                <td><?= htmlspecialchars($appt['Date']) ?></td>
                <td><?= formatTime($appt['Time'], $clinicBranding['timezone']) ?></td>
                <?php
                  $dentistDisplayRow = $appt['Dentist_Name'] ?? '';
                  if ($dentistDisplayRow !== '' && ctype_digit((string)$dentistDisplayRow) && isset($dentistNameById[(int)$dentistDisplayRow])) {
                    $dentistDisplayRow = $dentistNameById[(int)$dentistDisplayRow];
                  } elseif ($dentistDisplayRow !== '') {
                    $dentistDisplayRow = 'Dr. ' . $dentistDisplayRow;
                  } else {
                    $dentistDisplayRow = 'N/A';
                  }
                ?>
                <td><?= htmlspecialchars($dentistDisplayRow) ?></td>
                <td>
                  <?php if ($appt['Status'] === 'Confirmed'): ?>
                    <span class="badge green">Confirmed</span>
                  <?php elseif ($appt['Status'] === 'Pending'): ?>
                    <span class="badge yellow">Pending</span>
                  <?php else: ?>
                    <span class="badge gray"><?= htmlspecialchars($appt['Status']) ?></span>
                  <?php endif; ?>
                </td>
                
                <td>
                  <?php if (in_array($appt['Status'], ['Booked','Confirmed','Pending'])): ?>
                    <a href="appointment.php?cancel_id=<?= (int)$appt['Appointment_Id'] ?>" class="btn-outline" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
        <div class="app-cards">
          <?php foreach ($appts as $appt): ?>
            <?php
              $dentistDisplayRow = $appt['Dentist_Name'] ?? '';
              if ($dentistDisplayRow !== '' && ctype_digit((string)$dentistDisplayRow) && isset($dentistNameById[(int)$dentistDisplayRow])) {
                $dentistDisplayRow = $dentistNameById[(int)$dentistDisplayRow];
              } elseif ($dentistDisplayRow !== '') {
                $dentistDisplayRow = 'Dr. ' . $dentistDisplayRow;
              } else {
                $dentistDisplayRow = 'N/A';
              }
              $status = $appt['Status'];
              $badgeClass = $status==='Confirmed' ? 'green' : ($status==='Pending' ? 'yellow' : 'gray');
            ?>
            <div class="app-card">
              <div class="row1">
                <div class="text-base font-semibold text-gray-900">
                  <?= htmlspecialchars($appt['Date']) ?> • <?= formatTime($appt['Time'], $clinicBranding['timezone']) ?>
                </div>
                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
              </div>
              <div class="meta mt-1">Dentist: <?= htmlspecialchars($dentistDisplayRow) ?></div>
              <div class="mt-2">
                <?php if (in_array($appt['Status'], ['Booked','Confirmed','Pending'])): ?>
                  <a href="appointment.php?cancel_id=<?= (int)$appt['Appointment_Id'] ?>" class="btn-outline" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                <?php else: ?>
                  <span class="meta">—</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center text-gray-500 py-8">
          <p>No upcoming appointments scheduled.</p>
          <a href="appointment.php" class="btn mt-4">Book Your First Appointment</a>
        </div>
      <?php endif; ?>
    </div>
    </div>
  </main>
</div>
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
