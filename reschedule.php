<?php
session_start();
include 'db_connect.php';
if (!isset($_SESSION['email'])) { header('Location: login.php'); exit(); }
$email = $_SESSION['email'];
$alert = '';

// Ensure reschedule counter column exists
$colCheck = $conn->query("SHOW COLUMNS FROM tbl_appointments LIKE 'Reschedule_Count'");
if ($colCheck && $colCheck->num_rows === 0) {
  $conn->query("ALTER TABLE tbl_appointments ADD COLUMN Reschedule_Count INT NOT NULL DEFAULT 0");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $appointment_id = intval($_POST['appointment_id'] ?? 0);
  $new_date = trim($_POST['date'] ?? '');
  $new_time = trim($_POST['time'] ?? '');
  // Normalize to 5-minute increments on server
  if ($new_time !== '') {
    $ts = strtotime($new_time);
    if ($ts !== false) {
      $mins = intval(date('i', $ts));
      $hrs = intval(date('H', $ts));
      // round to nearest 5-minute mark
      $mins = intval(round($mins / 5) * 5);
      if ($mins >= 60) { $hrs += 1; $mins = 0; }
      // enforce clinic bounds 07:00 to 19:00 (last selectable 18:55)
      if ($hrs < 7) { $hrs = 7; $mins = 0; }
      if ($hrs > 18 || ($hrs === 18 && $mins > 55)) { $hrs = 18; $mins = 55; }
      $new_time = sprintf('%02d:%02d:00', $hrs, $mins);
    }
  }
  if ($appointment_id > 0 && $new_date && $new_time) {
    // Fetch appointment details (without Duration)
    $info = $conn->prepare("SELECT Appointment_Id, Dentist_Id, Room, Reschedule_Count FROM tbl_appointments WHERE Appointment_Id = ? AND Email = ? AND Status IN ('Booked','Confirmed','Pending')");
    $info->bind_param('is', $appointment_id, $email);
    $info->execute();
    $resInfo = $info->get_result();
    if ($resInfo && $resInfo->num_rows === 1) {
      $ap = $resInfo->fetch_assoc();
      $currentCount = intval($ap['Reschedule_Count'] ?? 0);
      if ($currentCount >= 2) {
        $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Reschedule limit reached (maximum of 2).</div>';
      } else {
        // Validate clinic hours (start time only)
        $start_time = $new_time;
        if ($start_time < '07:00:00' || $start_time > '19:00:00') {
          $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Selected time is outside clinic hours.</div>';
        }

        // Conflict checks for dentist and room on the new date
        if (empty($alert)) {
          $check = $conn->prepare("SELECT Appointment_Id FROM tbl_appointments WHERE Appointment_Id <> ? AND Dentist_Id = ? AND Date = ? AND Status IN ('Booked','Confirmed','Pending','Ongoing') AND Time = ?");
          $check->bind_param('iiss', $appointment_id, $ap['Dentist_Id'], $new_date, $start_time);
          $check->execute();
          $check->store_result();
          if ($check->num_rows > 0) {
            $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">This time conflicts with another appointment for the selected dentist.</div>';
          }
          $check->close();
        }

        if (empty($alert)) {
          $checkR = $conn->prepare("SELECT Appointment_Id FROM tbl_appointments WHERE Appointment_Id <> ? AND Room = ? AND Date = ? AND Status IN ('Booked','Confirmed','Pending','Ongoing') AND Time = ?");
          $checkR->bind_param('isss', $appointment_id, $ap['Room'], $new_date, $start_time);
          $checkR->execute();
          $checkR->store_result();
          if ($checkR->num_rows > 0) {
            $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">The selected room is occupied during this time.</div>';
          }
          $checkR->close();
        }

        if (empty($alert)) {
          $stmt = $conn->prepare("UPDATE tbl_appointments SET Date = ?, Time = ?, Status = 'Pending', Reschedule_Count = Reschedule_Count + 1 WHERE Appointment_Id = ? AND Email = ? AND Status IN ('Booked','Confirmed','Pending')");
          $stmt->bind_param('ssis', $new_date, $new_time, $appointment_id, $email);
          if ($stmt->execute() && $stmt->affected_rows > 0) {
            $alert = '<div class="bg-green-100 text-green-700 px-4 py-2 rounded mb-4">Reschedule request submitted.</div>';
          } else {
            $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Unable to reschedule.</div>';
          }
          $stmt->close();
        }
      }
    } else {
      $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Appointment not found.</div>';
    }
    $info->close();
  } else {
    $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Please complete the form.</div>';
  }
}

$appts = [];
$list = $conn->prepare("SELECT Appointment_Id, Date, Time, Status FROM tbl_appointments WHERE Email = ? AND Status IN ('Booked','Confirmed','Pending') ORDER BY Date ASC, Time ASC");
$list->bind_param('s', $email);
$list->execute();
$res = $list->get_result();
while ($row = $res->fetch_assoc()) { $appts[] = $row; }
$list->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reschedule</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800">
<div class="max-w-3xl mx-auto p-8">
  <a href="patient_dashboard.php" class="text-blue-600 hover:underline">← Back</a>
  <h1 class="text-2xl font-semibold text-blue-700 mb-4">Reschedule Appointment</h1>
  <?= $alert ?>
  <form method="post" class="bg-white rounded shadow p-6 space-y-4">
    <div>
      <label class="block text-sm font-medium mb-1">Select Appointment</label>
      <select name="appointment_id" class="w-full border rounded px-3 py-2" required>
        <option value="">Choose...</option>
        <?php foreach ($appts as $a): ?>
          <option value="<?= $a['Appointment_Id'] ?>">#<?= $a['Appointment_Id'] ?> — <?= htmlspecialchars($a['Date']) ?> <?= htmlspecialchars(date('g:i A', strtotime($a['Time']))) ?> (<?= htmlspecialchars($a['Status']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">New Date</label>
        <input type="date" name="date" class="w-full border rounded px-3 py-2" required min="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">New Time</label>
        <input type="time" name="time" class="w-full border rounded px-3 py-2" required step="300" min="07:00" max="18:55">
      </div>
    </div>
    <div class="text-right">
      <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Submit</button>
    </div>
  </form>
</div>
</body>
</html>


