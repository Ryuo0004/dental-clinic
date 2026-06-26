<?php
session_start();
include 'db_connect.php';

// Auth guard: require dentist login
if (!isset($_SESSION['dentist_id'])) {
  header('Location: login.php');
  exit();
}

// You may set $_SESSION['dentist_name'] at login. Fallback for demo.
if (!isset($_SESSION['dentist_name'])) { $_SESSION['dentist_name'] = 'Dr. Dentist'; }

// Quick stats (fallback counts if tables not present)
$today = date('Y-m-d');
$stats = ['today' => 0, 'upcoming' => 0, 'patients' => 0];
if (isset($conn)) {
  // Today appointments
  if ($stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_appointments WHERE Date = ? AND Status IN ('Pending','Booked','Confirmed','Ongoing')")) {
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $stmt->bind_result($c); $stmt->fetch(); $stats['today'] = (int)$c; $stmt->close();
  }
  // Upcoming (future)
  $r = $conn->query("SELECT COUNT(*) AS c FROM tbl_appointments WHERE Date > CURDATE() AND Status IN ('Pending','Booked','Confirmed','Ongoing')");
  if ($r && ($row = $r->fetch_assoc())) { $stats['upcoming'] = (int)$row['c']; }
  // Total patients
  $r = $conn->query("SELECT COUNT(*) AS c FROM tbl_patient");
  if ($r && ($row = $r->fetch_assoc())) { $stats['patients'] = (int)$row['c']; }
}

// Advise crawlers not to index this page
header('X-Robots-Tag: noindex, nofollow', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Dentist Dashboard - Miles Dental Clinic</title>
  <?php include 'header.php'; ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="dentists.css" />
  <style> body { background:#ffffff; color:#0f172a; } </style>
</head>
<body>
  <div class="flex min-h-screen bg-[#f8fafc]">
    <?php include 'dentist_sidebar.php'; ?>

    <!-- Main -->
    <main class="flex-1 p-6 space-y-6 dentist-main">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl md:text-3xl font-semibold text-blue-800">Welcome, <?= htmlspecialchars($_SESSION['dentist_name']) ?></h1>
          <p class="text-sm text-gray-500 mt-1">Here’s a quick overview of your schedule and patients.</p>
        </div>
      </div>

      <!-- Stats Cards -->
      <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-2xl p-5 bg-white border border-blue-100 shadow-sm">
          <div class="text-gray-600 text-sm">Today’s Appointments</div>
          <div class="text-4xl font-extrabold mt-1 text-blue-800"><?= (int)$stats['today'] ?></div>
          <div class="text-xs text-gray-500 mt-1">Pending/Booked/Confirmed/Ongoing</div>
        </div>
        <div class="rounded-2xl p-5 bg-white border border-blue-100 shadow-sm">
          <div class="text-gray-600 text-sm">Upcoming</div>
          <div class="text-4xl font-extrabold mt-1 text-blue-800"><?= (int)$stats['upcoming'] ?></div>
          <div class="text-xs text-gray-500 mt-1">Future days</div>
        </div>
        <div class="rounded-2xl p-5 bg-white border border-blue-100 shadow-sm">
          <div class="text-gray-600 text-sm">Patients</div>
          <div class="text-4xl font-extrabold mt-1 text-blue-800"><?= (int)$stats['patients'] ?></div>
          <div class="text-xs text-gray-500 mt-1">Total in records</div>
        </div>
      </section>

      <!-- Quick Actions -->
      <section class="bg-white rounded-2xl border border-blue-100 p-5 shadow-sm">
        <h2 class="text-lg font-semibold mb-4 text-blue-800">Quick Actions</h2>
        <div class="flex flex-wrap gap-3">
          <a href="dentist_appointments_schedule.php" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 transition font-medium text-white">View Schedule</a>
          <a href="dentist_patient_records.php" class="px-4 py-2 rounded-xl border border-blue-200 hover:bg-blue-50 transition font-medium text-blue-700">Patient Records</a>
          <a href="dentist_profile.php" class="px-4 py-2 rounded-xl border border-blue-500 text-blue-600 hover:bg-blue-50 transition font-medium">Edit Profile</a>
        </div>
      </section>

      <!-- Today’s Appointments -->
      <section class="bg-white rounded-2xl border border-blue-100 p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold text-blue-800">Today’s Appointments</h2>
          <div class="text-sm text-gray-500"><?= htmlspecialchars(date('M j, Y')) ?></div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="text-gray-600">
              <tr class="text-left">
                <th class="py-2 pr-4">Time</th>
                <th class="py-2 pr-4">Patient</th>
                <th class="py-2 pr-4">Procedure</th>
                <th class="py-2 pr-4">Status</th>
                <th class="py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-blue-100">
              <?php
              if (isset($conn)) {
                $sql = "SELECT a.Appointment_Id, a.Time, a.`Procedure`, a.Status, COALESCE(CONCAT(p.First_name,' ',p.Last_name), a.Email) AS PatientName
                        FROM tbl_appointments a LEFT JOIN tbl_patient p ON (p.Email = a.Email)
                        WHERE a.Date = ? ORDER BY a.Time ASC";
                if ($st = $conn->prepare($sql)) {
                  $st->bind_param('s', $today);
                  $st->execute();
                  $rs = $st->get_result();
                  if ($rs && $rs->num_rows) {
                    while ($row = $rs->fetch_assoc()) {
                      echo '<tr class="hover:bg-blue-50">';
                      echo '<td class="py-2 pr-4">'.htmlspecialchars(date('g:i A', strtotime($row['Time']))).'</td>';
                      echo '<td class="py-2 pr-4">'.htmlspecialchars($row['PatientName']).'</td>';
                      echo '<td class="py-2 pr-4">'.htmlspecialchars($row['Procedure']).'</td>';
                      echo '<td class="py-2 pr-4">'.htmlspecialchars($row['Status']).'</td>';
                      echo '<td class="py-2"><a class="px-3 py-1 rounded-lg border border-blue-200 hover:bg-blue-50 text-blue-700 text-xs" href="dentist_appointments_schedule.php">Open</a></td>';
                      echo '</tr>';
                    }
                  } else {
                    echo '<tr><td class="py-3 text-slate-400" colspan="5">No appointments today.</td></tr>';
                  }
                  $st->close();
                }
              }
              ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
