<?php
// API endpoints for appointment functionality
// Handles availability checking and other AJAX requests

session_start();
include 'db_connect.php';
// Include inventory helpers to evaluate treatment availability
include_once 'inventory_deduction.php';

// Check if user is logged in (allow patient OR admin)
$isPatient = isset($_SESSION['email']) && $_SESSION['email'] !== '';
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!$isPatient && !$isAdmin) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit();
}

// Treatments availability for disabling treatment selection when out of stock
if (isset($_GET['treatments_availability'])) {
    header('Content-Type: application/json');
    $namesRaw = isset($_GET['names']) ? (string)$_GET['names'] : '';
    $names = array_values(array_filter(array_map('trim', explode('|', $namesRaw))));
    $out = [];
    foreach ($names as $nm) {
        $statusList = getTreatmentInventoryStatus($conn, $nm);
        // If no mapping rows, treat as available (no gating)
        $available = true; $hasZero = false; $expiredAny = false;
        if (!empty($statusList)) {
            foreach ($statusList as $row) {
                $matched = isset($row['matched']) ? (bool)$row['matched'] : ($row['current_stock'] !== null);
                if (!$matched) { continue; } // ignore unmatched mapping rows
                $q = isset($row['current_stock']) ? (int)$row['current_stock'] : 0;
                $need = isset($row['quantity_needed']) ? (int)$row['quantity_needed'] : 0;
                $expired = !empty($row['expired']);
                if ($q <= 0 || $expired) { $hasZero = true; }
                // block on insufficient OR expired
                if ($expired || $q < $need) { $available = false; }
                if ($expired) { $expiredAny = true; }
            }
        }
        // clickable only if available and no zero/expired among matched rows
        $out[$nm] = [
            'clickable' => ($available && !$hasZero),
            'has_zero' => $hasZero,
            'expired' => $expiredAny
        ];
    }
    echo json_encode(['ok'=>true, 'availability'=>$out]);
  exit();
}

// Detailed inventory status per treatment (for combined gating)
if (isset($_GET['treatments_inventory_status'])) {
    header('Content-Type: application/json');
    $namesRaw = isset($_GET['names']) ? (string)$_GET['names'] : '';
    $names = array_values(array_filter(array_map('trim', explode('|', $namesRaw))));
    $out = [];
    foreach ($names as $nm) {
        $list = getTreatmentInventoryStatus($conn, $nm);
        $out[$nm] = $list;
    }
    echo json_encode(['ok' => true, 'items' => $out]);
    exit();
}

// Return current user's pending/booked time for a date+dentist (for blue highlight)
if (isset($_GET['user_pending'])) {
    header('Content-Type: application/json');
    $email = $_SESSION['email'];
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    $dentistId = isset($_GET['dentist_id']) ? intval($_GET['dentist_id']) : 0;
    if (!$date || $dentistId <= 0) { echo json_encode(['ok'=>false,'error'=>'Missing params']); exit(); }
    $time = null;
    // Prefer user's most recent Pending/Booked on that date with that dentist
    $sql = "SELECT Time FROM tbl_appointments WHERE Email = ? AND Date = ? AND Dentist_Id = ? AND Status IN ('Pending','Booked') ORDER BY Appointment_Id DESC LIMIT 1";
    if ($st = $conn->prepare($sql)) {
        $st->bind_param('ssi', $email, $date, $dentistId);
        $st->execute();
        $rs = $st->get_result();
        if ($row = $rs->fetch_assoc()) { $time = substr((string)$row['Time'],0,5); } // HH:MM
        $st->close();
    }
    echo json_encode(['ok'=>true, 'time'=>$time]);
    exit();
}

// Cooldown info (pre-submit) for booking modal
if (isset($_GET['cooldown_info'])) {
    header('Content-Type: application/json');
    $email = $_SESSION['email'];
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    $time = isset($_GET['time']) ? $_GET['time'] : '';
    $dentistId = isset($_GET['dentist_id']) ? intval($_GET['dentist_id']) : 0;
    if (!$date || !$time || $dentistId <= 0) { echo json_encode(['ok'=>false,'error'=>'Missing params']); exit(); }
    $requestedTs = strtotime($date . ' ' . $time);

    $sameDentistBlock = false; $otherDentistNotice = false;

    // Last completed with same dentist (treatment_history preferred)
    $lastSameTs = null;
    $st = $conn->prepare("SELECT Treatment_Date, Treatment_Time FROM tbl_treatment_history WHERE Patient_Email = ? AND Dentist_Id = ? ORDER BY Treatment_Date DESC, Treatment_Time DESC LIMIT 1");
    if ($st) {
        $st->bind_param('si', $email, $dentistId);
        $st->execute();
        $res = $st->get_result();
        if ($row = $res->fetch_assoc()) { $lastSameTs = strtotime($row['Treatment_Date'].' '.$row['Treatment_Time']); }
        $st->close();
    }
    if ($lastSameTs === null) {
        $st2 = $conn->prepare("SELECT `Date`, `Time` FROM tbl_appointments WHERE Email = ? AND Dentist_Id = ? AND Status = 'Finished' ORDER BY `Date` DESC, `Time` DESC LIMIT 1");
        if ($st2) {
            $st2->bind_param('si', $email, $dentistId);
            $st2->execute();
            $res2 = $st2->get_result();
            if ($row = $res2->fetch_assoc()) { $lastSameTs = strtotime($row['Date'].' '.$row['Time']); }
            $st2->close();
        }
    }
    if ($lastSameTs !== null && $requestedTs < ($lastSameTs + 24*3600)) {
        $sameDentistBlock = true;
    }

    // Last completed with any dentist (for notice)
    $lastAnyTs = null; $lastAnyDent = null;
    $ah = $conn->prepare("SELECT Treatment_Date, Treatment_Time, Dentist_Id FROM tbl_treatment_history WHERE Patient_Email = ? ORDER BY Treatment_Date DESC, Treatment_Time DESC LIMIT 1");
    if ($ah) {
        $ah->bind_param('s', $email);
        $ah->execute();
        $ar = $ah->get_result();
        if ($row = $ar->fetch_assoc()) { $lastAnyTs = strtotime($row['Treatment_Date'].' '.$row['Treatment_Time']); $lastAnyDent = intval($row['Dentist_Id']); }
        $ah->close();
    }
    if ($lastAnyTs === null) {
        $ah2 = $conn->prepare("SELECT `Date`, `Time`, Dentist_Id FROM tbl_appointments WHERE Email = ? AND Status = 'Finished' ORDER BY `Date` DESC, `Time` DESC LIMIT 1");
        if ($ah2) {
            $ah2->bind_param('s', $email);
            $ah2->execute();
            $ar2 = $ah2->get_result();
            if ($row = $ar2->fetch_assoc()) { $lastAnyTs = strtotime($row['Date'].' '.$row['Time']); $lastAnyDent = intval($row['Dentist_Id']); }
            $ah2->close();
        }
    }
    if ($lastAnyTs !== null && $requestedTs < ($lastAnyTs + 24*3600) && $lastAnyDent !== null && $lastAnyDent !== $dentistId) {
        $otherDentistNotice = true;
    }

    echo json_encode([
        'ok' => true,
        'same_dentist_block' => $sameDentistBlock,
        'other_dentist_notice' => $otherDentistNotice,
        'message_same' => 'You recently completed a treatment with this dentist. Please wait 24 hours before booking again with the same doctor.',
        'message_other' => 'You recently completed a treatment. Booking another dentist within 24 hours is allowed, but consider recovery time.'
    ]);
    exit();
}

// Generate available time slots for a date/dentist (used by reschedule modal)
if (isset($_GET['slots'])) {
    header('Content-Type: application/json');
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    $dentistId = isset($_GET['dentist_id']) ? intval($_GET['dentist_id']) : 0;
    $reqDuration = isset($_GET['duration']) ? intval($_GET['duration']) : 0;
    if ($reqDuration <= 0) { $reqDuration = 30; }
    if (!$date || $dentistId <= 0) { echo json_encode(['ok'=>false,'error'=>'Missing date or dentist_id']); exit(); }

    // If dentist is on leave for this date, no slots available
    $leaveFlag = false;
    if ($st = $conn->prepare("SELECT 1 FROM tbl_dentist_leave WHERE Dentist_Id = ? AND Leave_Date = ? LIMIT 1")) {
        $st->bind_param('is', $dentistId, $date);
        if ($st->execute()) { $st->store_result(); $leaveFlag = ($st->num_rows > 0); }
        $st->close();
    }
    if ($leaveFlag) {
        echo json_encode(['ok'=>true, 'slots'=>[], 'leave'=>true]);
        exit();
    }

    // Clinic hours
    $startClinic = '07:00:00';
    $endClinic = '19:00:00';

    // Build 5-min increments list within clinic hours
    $slots = [];
    $cursor = strtotime($startClinic);
    $endLimit = strtotime($endClinic);
    while ($cursor < $endLimit) {
        $hm = date('H:i', $cursor);
        $slots[] = $hm;
        $cursor = strtotime('+5 minutes', $cursor);
    }

    // Filter out past times if date is today
    $todayStr = date('Y-m-d');
    if ($date === $todayStr) {
        $nowHM = date('H:i');
        $slots = array_values(array_filter($slots, function($hm) use ($nowHM) { return $hm > $nowHM; }));
    }

    // Remove any slot that conflicts for this dentist, duration-aware
    $avail = [];
    // For admin (no patient email), leave empty so we don't try to exclude their own pending/booked rows
    $currentUserEmail = $isPatient ? $_SESSION['email'] : '';
    $q = "SELECT 1 FROM tbl_appointments
          WHERE Dentist_Id = ? AND Date = ?
            AND Status IN ('Booked','Confirmed','Pending','Ongoing')
            AND NOT (Email = ? AND Status IN ('Pending','Booked'))
            AND (
              TIME_TO_SEC(Time) < TIME_TO_SEC(ADDTIME(?, SEC_TO_TIME(?*60)))
              AND
              TIME_TO_SEC(?) < TIME_TO_SEC(ADDTIME(Time, SEC_TO_TIME(COALESCE(Duration, 30)*60)))
            )
          LIMIT 1";
    $st = $conn->prepare($q);
    foreach ($slots as $hm) {
        $start_time = $hm . ':00';
        // Ensure requested end is within clinic hours
        $startTs = strtotime($start_time);
        if ($startTs + ($reqDuration*60) > $endLimit) { continue; }
        if ($st) {
            $st->bind_param('isssis', $dentistId, $date, $currentUserEmail, $start_time, $reqDuration, $start_time);
            $st->execute();
            $st->store_result();
            if ($st->num_rows === 0) { $avail[] = $hm; }
        }
    }
    if ($st) { $st->close(); }

    echo json_encode(['ok'=>true, 'slots'=>$avail]);
    exit();
}

// Availability API for live calendar/time-slot load
if (isset($_GET['availability'])) {
    header('Content-Type: application/json');
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    $time = isset($_GET['time']) ? $_GET['time'] : '';
    $dentistId = isset($_GET['dentist_id']) ? intval($_GET['dentist_id']) : 0;
    $reqDuration = isset($_GET['duration']) ? intval($_GET['duration']) : 0;
    if ($reqDuration <= 0) { $reqDuration = 30; }
   
    // Basic validation
    if (!$date || !$time) {
        echo json_encode(['ok' => false, 'error' => 'Missing date/time']);
        exit();
    }
    $start_time = date('H:i:s', strtotime($time));
    // Conflicts across the clinic at exact same time (informational only)
    $clinicSql = "SELECT COUNT(*) AS Cnt FROM tbl_appointments WHERE Date = ? AND Status IN ('Booked','Confirmed','Pending','Ongoing') AND Time = ?";
    $clinicStmt = $conn->prepare($clinicSql);
    $clinicStmt->bind_param('ss', $date, $start_time);
    $clinicStmt->execute();
    $clinicRes = $clinicStmt->get_result();
    $clinicCnt = 0;
    if ($row = $clinicRes->fetch_assoc()) { $clinicCnt = intval($row['Cnt']); }
    $clinicStmt->close();
    
    // Duration-aware overlaps for selected dentist (if provided)
    $dentCnt = 0;
    if ($dentistId > 0) {
        $dentSql = "SELECT COUNT(*) AS Cnt FROM tbl_appointments
                    WHERE Dentist_Id = ? AND Date = ? AND Status IN ('Booked','Confirmed','Pending','Ongoing')
                    AND (
                      TIME_TO_SEC(Time) < TIME_TO_SEC(ADDTIME(?, SEC_TO_TIME(?*60)))
                      AND
                      TIME_TO_SEC(?) < TIME_TO_SEC(ADDTIME(Time, SEC_TO_TIME(COALESCE(Duration, 30)*60)))
                    )";
        $dentStmt = $conn->prepare($dentSql);
        $dentStmt->bind_param('issis', $dentistId, $date, $start_time, $reqDuration, $start_time);
        $dentStmt->execute();
        $dentRes = $dentStmt->get_result();
        if ($row2 = $dentRes->fetch_assoc()) { $dentCnt = intval($row2['Cnt']); }
        $dentStmt->close();
    }
    
    echo json_encode(['ok' => true, 'clinicOverlaps' => $clinicCnt, 'dentistOverlaps' => $dentCnt]);
    exit();
}

// Get booked slots for a specific date and dentist
if (isset($_GET['get_booked_slots'])) {
    header('Content-Type: application/json');
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    $dentistId = isset($_GET['dentist_id']) ? intval($_GET['dentist_id']) : 0;
    $currentUserEmail = $_SESSION['email'];

    if (!$date || $dentistId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Missing date or dentist ID']);
        exit();
    }

    // Leave flag: if dentist is on leave, communicate it so UI can block the day
    $leaveFlag = false;
    if ($dentistId > 0 && $date !== '') {
        if ($st = $conn->prepare("SELECT 1 FROM tbl_dentist_leave WHERE Dentist_Id = ? AND Leave_Date = ? LIMIT 1")) {
            $st->bind_param('is', $dentistId, $date);
            if ($st->execute()) { $st->store_result(); $leaveFlag = ($st->num_rows > 0); }
            $st->close();
        }
    }

    $otherBookedSlots = [];
    $userBookedSlots = [];
    // Fetch start time and duration to expand into 5-minute increments for the full occupied span
    $stmt = $conn->prepare("SELECT Time AS start_time, COALESCE(Duration, 30) AS dur_min, Email FROM tbl_appointments WHERE Date = ? AND Dentist_Id = ? AND Status IN ('Pending', 'Confirmed', 'Booked', 'Ongoing')");
    if ($stmt) {
        $stmt->bind_param('si', $date, $dentistId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $startTs = strtotime((string)$row['start_time']);
                $durMin = (int)$row['dur_min'];
                if ($durMin <= 0) { $durMin = 30; }
                $endTs = $startTs + ($durMin * 60);
                // Emit every 5-minute HH:MM within [start, end)
                $cursor = $startTs;
                while ($cursor < $endTs) {
                    $hm = date('H:i', $cursor);
                    if ($row['Email'] === $currentUserEmail) {
                        $userBookedSlots[] = $hm;
                    } else {
                        $otherBookedSlots[] = $hm;
                    }
                    $cursor += 5 * 60;
                }
            }
        }
        $stmt->close();
    }
    // Deduplicate
    $otherBookedSlots = array_values(array_unique($otherBookedSlots));
    $userBookedSlots = array_values(array_unique($userBookedSlots));

    echo json_encode(['ok' => true, 'leave' => $leaveFlag, 'other_booked_slots' => $otherBookedSlots, 'user_booked_slots' => $userBookedSlots]);
    exit();
}

// Reschedule appointment (max 1 time per appointment per patient)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reschedule') {
    header('Content-Type: application/json');
    $email = $_SESSION['email'];
    $apptId = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
    $date = isset($_POST['date']) ? $_POST['date'] : '';
    $time = isset($_POST['time']) ? $_POST['time'] : '';
    // Duration removed
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    if ($apptId <= 0 || !$date || !$time) { echo json_encode(['ok'=>false,'error'=>'Missing parameters']); exit(); }

    // Ensure tables for logging exist
    $conn->query("CREATE TABLE IF NOT EXISTS tbl_appointment_reschedules (
        Id INT AUTO_INCREMENT PRIMARY KEY,
        Appointment_Id INT NOT NULL,
        Email VARCHAR(255) NOT NULL,
        Old_Date DATE NOT NULL,
        Old_Time TIME NOT NULL,
        New_Date DATE NOT NULL,
        New_Time TIME NOT NULL,
        Reason VARCHAR(255) NULL,
        Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Load appointment and verify ownership
    $stmt = $conn->prepare("SELECT a.Appointment_Id, a.Email, a.Dentist_Id, a.Date, a.Time, a.Status FROM tbl_appointments a WHERE a.Appointment_Id = ? LIMIT 1");
    $stmt->bind_param('i', $apptId);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) { echo json_encode(['ok'=>false,'error'=>'Appointment not found']); exit(); }
    $appt = $res->fetch_assoc();
    $stmt->close();
    if (strcasecmp($appt['Email'], $email) !== 0) { echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit(); }
    if (in_array($appt['Status'], ['Finished','Cancelled'])) { echo json_encode(['ok'=>false,'error'=>'Cannot reschedule finished or cancelled appointments']); exit(); }

    // Enforce max 1 reschedule for this appointment
    $cntStmt = $conn->prepare("SELECT COUNT(*) AS Cnt FROM tbl_appointment_reschedules WHERE Appointment_Id = ?");
    $cntStmt->bind_param('i', $apptId);
    $cntStmt->execute();
    $cntRes = $cntStmt->get_result();
    $cnt = 0; if ($row = $cntRes->fetch_assoc()) { $cnt = intval($row['Cnt']); }
    $cntStmt->close();
    if ($cnt >= 1) { echo json_encode(['ok'=>false,'error'=>'Reschedule limit reached (1)']); exit(); }

    // Validate clinic hours and past time
    include 'appointment_config.php';
    $startClinic = isset($clinicHours['start']) ? $clinicHours['start'] : '07:00:00';
    $endClinic = isset($clinicHours['end']) ? $clinicHours['end'] : '19:00:00';
    $start_time = date('H:i:s', strtotime($time));
    if ($start_time < $startClinic || $start_time > $endClinic) { echo json_encode(['ok'=>false,'error'=>'Selected time is outside clinic hours']); exit(); }
    if ($date === date('Y-m-d') && $start_time <= date('H:i:s')) { echo json_encode(['ok'=>false,'error'=>'Selected time is in the past']); exit(); }

    // Check conflicts for the same dentist
    $dentistId = intval($appt['Dentist_Id']);
    $conf = $conn->prepare("SELECT 1 FROM tbl_appointments WHERE Appointment_Id <> ? AND Dentist_Id = ? AND Date = ? AND Status IN ('Booked','Confirmed','Pending','Ongoing') AND Time = ? LIMIT 1");
    $conf->bind_param('iiss', $apptId, $dentistId, $date, $start_time);
    $conf->execute();
    $conf->store_result();
    if ($conf->num_rows > 0) { $conf->close(); echo json_encode(['ok'=>false,'error'=>'Selected time conflicts with another appointment']); exit(); }
    $conf->close();

    // Update appointment
    $upd = $conn->prepare("UPDATE tbl_appointments SET Date = ?, Time = ? WHERE Appointment_Id = ?");
    $upd->bind_param('ssi', $date, $start_time, $apptId);
    if (!$upd->execute()) { $upd->close(); echo json_encode(['ok'=>false,'error'=>'Failed to reschedule']); exit(); }
    $upd->close();

    // Log reschedule
    $log = $conn->prepare("INSERT INTO tbl_appointment_reschedules (Appointment_Id, Email, Old_Date, Old_Time, New_Date, New_Time, Reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $log->bind_param('issssss', $apptId, $email, $appt['Date'], $appt['Time'], $date, $start_time, $reason);
    $log->execute();
    $log->close();

    // Notify dentist and admins about the reschedule
    // Ensure notifications table exists
    $conn->query("CREATE TABLE IF NOT EXISTS tbl_notifications (
        Id INT AUTO_INCREMENT PRIMARY KEY,
        Email VARCHAR(255) NOT NULL,
        Admin_Id INT NULL,
        Message TEXT NOT NULL,
        Type VARCHAR(64) NOT NULL,
        Is_Read TINYINT(1) DEFAULT 0,
        Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (Email), INDEX (Type), INDEX (Is_Read), INDEX (Admin_Id)
    ) ENGINE=InnoDB");

    // Prepare details
    $dentistId = intval($appt['Dentist_Id']);
    $dentistEmail = '';
    if ($dentistId > 0) {
        $hasEmailCol = false;
        if ($col = $conn->query("SHOW COLUMNS FROM tbl_dentist LIKE 'Email'")) { $hasEmailCol = $col->num_rows > 0; }
        if ($hasEmailCol) {
            if ($ds = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(Email),''), '') AS Email FROM tbl_dentist WHERE Dentist_id = ? LIMIT 1")) {
                $ds->bind_param('i', $dentistId);
                if ($ds->execute()) {
                    $dr = $ds->get_result();
                    if ($row = $dr->fetch_assoc()) { $dentistEmail = $row['Email'] ?: ''; }
                }
                $ds->close();
            }
        }
        if ($dentistEmail === '') { $dentistEmail = 'dentist'.$dentistId.'@local'; }
    }

    // Fetch patient name and treatment
    $patientName = '';
    $treat = '';
    if ($d2 = $conn->prepare("SELECT a.Email, a.\`Procedure\`, p.First_name, p.Last_name FROM tbl_appointments a LEFT JOIN tbl_patient p ON a.Email = p.Email WHERE a.Appointment_Id = ? LIMIT 1")) {
        $d2->bind_param('i', $apptId);
        if ($d2->execute()) {
            $dr2 = $d2->get_result();
            if ($row2 = $dr2->fetch_assoc()) {
                $treat = trim((string)($row2['Procedure'] ?? 'appointment'));
                $patientName = trim(((string)($row2['First_name'] ?? '')).' '.((string)($row2['Last_name'] ?? '')));
            }
        }
        $d2->close();
    }
    if ($patientName === '') { $patientName = 'Patient'; }
    $oldWhen = '';
    if (!empty($appt['Date']) && !empty($appt['Time'])) { $oldWhen = date('M j, Y g:i A', strtotime($appt['Date'].' '.substr($appt['Time'],0,5))); }
    $newWhen = date('M j, Y g:i A', strtotime($date.' '.substr($start_time,0,5)));
    $msg = $patientName.' rescheduled '.($treat !== '' ? $treat : 'an appointment').' from '.($oldWhen ?: 'previous time').' to '.$newWhen.'.'
         .(trim((string)$reason)!=='' ? ' Reason: '.$reason : '');

    // Notify dentist
    if ($dentistEmail !== '') {
        if ($ins = $conn->prepare("INSERT INTO tbl_notifications (Email, Message, Type) VALUES (?, ?, 'patient_reschedule')")) {
            $ins->bind_param('ss', $dentistEmail, $msg);
            $ins->execute();
            $ins->close();
        }
    }

    // Ensure Admin_Id column exists (older installs may lack it)
    $hasAdminIdCol = false; if ($col = $conn->query("SHOW COLUMNS FROM tbl_notifications LIKE 'Admin_Id'")) { $hasAdminIdCol = $col->num_rows > 0; }
    if (!$hasAdminIdCol) { $conn->query("ALTER TABLE tbl_notifications ADD COLUMN Admin_Id INT NULL AFTER Email"); $conn->query("CREATE INDEX IF NOT EXISTS idx_admin_id ON tbl_notifications (Admin_Id)"); }

    // Notify all admins (using Admin_Id when available)
    $admins = [];
    $hasAdminTbl = $conn->query("SHOW TABLES LIKE 'tbl_admin'");
    if ($hasAdminTbl && $hasAdminTbl->num_rows > 0) {
        if ($ar = $conn->query("SELECT Admin_Id, Email FROM tbl_admin WHERE COALESCE(NULLIF(TRIM(Email),''),'') <> ''")) {
            while ($row = $ar->fetch_assoc()) { $admins[] = $row; }
        }
    }
    if (!empty($admins)) {
        if ($ina = $conn->prepare("INSERT INTO tbl_notifications (Email, Admin_Id, Message, Type) VALUES (?, ?, ?, 'patient_reschedule_admin')")) {
            foreach ($admins as $a) { $ae = $a['Email']; $aid = (int)$a['Admin_Id']; $ina->bind_param('sis', $ae, $aid, $msg); $ina->execute(); }
            $ina->close();
        } else if ($ina = $conn->prepare("INSERT INTO tbl_notifications (Email, Message, Type) VALUES (?, ?, 'patient_reschedule_admin')")) {
            foreach ($admins as $a) { $ae = $a['Email']; $ina->bind_param('ss', $ae, $msg); $ina->execute(); }
            $ina->close();
        }
    }

    echo json_encode(['ok'=>true]);
    exit();
}

// Cancel with reason
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_with_reason') {
    header('Content-Type: application/json');
    $email = $_SESSION['email'];
    $apptId = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    if ($apptId <= 0 || $reason === '') { echo json_encode(['ok'=>false,'error'=>'Missing parameters']); exit(); }

    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS tbl_appointment_cancellations (
        Id INT AUTO_INCREMENT PRIMARY KEY,
        Appointment_Id INT NOT NULL,
        Email VARCHAR(255) NOT NULL,
        Reason VARCHAR(255) NOT NULL,
        Notes TEXT NULL,
        Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Verify ownership and status, also fetch details for notification
    $stmt = $conn->prepare("SELECT Email, Status, Dentist_Id, `Procedure`, `Date`, `Time` FROM tbl_appointments WHERE Appointment_Id = ? LIMIT 1");
    $stmt->bind_param('i', $apptId);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) { echo json_encode(['ok'=>false,'error'=>'Appointment not found']); exit(); }
    $appt = $res->fetch_assoc();
    $stmt->close();
    if (strcasecmp($appt['Email'], $email) !== 0) { echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit(); }
    if ($appt['Status'] === 'Cancelled') { echo json_encode(['ok'=>false,'error'=>'Already cancelled']); exit(); }

    // Update status
    $upd = $conn->prepare("UPDATE tbl_appointments SET Status = 'Cancelled' WHERE Appointment_Id = ?");
    $upd->bind_param('i', $apptId);
    if (!$upd->execute()) { $upd->close(); echo json_encode(['ok'=>false,'error'=>'Failed to cancel']); exit(); }
    $upd->close();

    // Log reason
    $log = $conn->prepare("INSERT INTO tbl_appointment_cancellations (Appointment_Id, Email, Reason, Notes) VALUES (?, ?, ?, ?)");
    $log->bind_param('isss', $apptId, $email, $reason, $notes);
    $log->execute();
    $log->close();

    // Notify dentist
    $conn->query("CREATE TABLE IF NOT EXISTS tbl_notifications (Id INT AUTO_INCREMENT PRIMARY KEY, Email VARCHAR(255) NOT NULL, Message TEXT NOT NULL, Type VARCHAR(64) NOT NULL, Is_Read TINYINT(1) DEFAULT 0, Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (Email), INDEX (Type), INDEX (Is_Read)) ENGINE=InnoDB");
    $dentistId = (int)($appt['Dentist_Id'] ?? 0);
    $dentistEmail = '';
    $dentistName = 'Dentist #'.$dentistId;
    if ($dentistId > 0) {
        // Check if Email column exists and fetch
        $hasEmailCol = false;
        if ($col = $conn->query("SHOW COLUMNS FROM tbl_dentist LIKE 'Email'")) { $hasEmailCol = $col->num_rows > 0; }
        if ($hasEmailCol) {
            if ($ds = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(Email),''), '') AS Email, COALESCE(NULLIF(TRIM(Name),''), CONCAT('Dentist #', Dentist_id)) AS Name FROM tbl_dentist WHERE Dentist_id = ? LIMIT 1")) {
                $ds->bind_param('i', $dentistId);
                if ($ds->execute()) {
                    $dr = $ds->get_result();
                    if ($row = $dr->fetch_assoc()) { $dentistEmail = $row['Email'] ?: ''; $dentistName = $row['Name'] ?: $dentistName; }
                }
                $ds->close();
            }
        }
        if ($dentistEmail === '') { $dentistEmail = 'dentist'.$dentistId.'@local'; }
    }
    // Build message with patient name + treatment + datetime + reason/notes
    $when = '';
    if (!empty($appt['Date']) && !empty($appt['Time'])) { $when = date('M j, Y g:i A', strtotime($appt['Date'].' '.substr($appt['Time'],0,5))); }
    $patientName = '';
    if (!empty($appt['Email'])) {
        if ($ps = $conn->prepare("SELECT TRIM(CONCAT(COALESCE(NULLIF(TRIM(First_name),''),''),' ',COALESCE(NULLIF(TRIM(Last_name),''),''))) AS FullName FROM tbl_patient WHERE Email = ? LIMIT 1")) {
            $ps->bind_param('s', $appt['Email']);
            if ($ps->execute()) { $pr = $ps->get_result(); if ($row=$pr->fetch_assoc()) { $patientName = trim($row['FullName'] ?? ''); } }
            $ps->close();
        }
    }
    if ($patientName === '') { $patientName = 'Patient'; }
    $treat = trim((string)($appt['Procedure'] ?? 'appointment'));
    $msg = $patientName.' cancelled '.($treat !== '' ? $treat : 'an appointment').($when?(' on '.$when):'').'.'
         .(trim((string)$reason)!=='' ? ' Reason: '.$reason : '')
         .(trim((string)$notes)!=='' ? ' — '.$notes : '');
    if ($ins = $conn->prepare("INSERT INTO tbl_notifications (Email, Message, Type) VALUES (?, ?, 'patient_cancel')")) {
        $ins->bind_param('ss', $dentistEmail, $msg);
        $ins->execute();
        $ins->close();
    }
    // Notify all admins as well (include Admin_Id if column exists)
    // Ensure Admin_Id column exists
    $hasAdminIdCol = false; if ($col = $conn->query("SHOW COLUMNS FROM tbl_notifications LIKE 'Admin_Id'")) { $hasAdminIdCol = $col->num_rows > 0; }
    if (!$hasAdminIdCol) { $conn->query("ALTER TABLE tbl_notifications ADD COLUMN Admin_Id INT NULL AFTER Email"); $conn->query("CREATE INDEX IF NOT EXISTS idx_admin_id ON tbl_notifications (Admin_Id)"); $hasAdminIdCol = true; }
    $admins = [];
    $hasAdminTbl = $conn->query("SHOW TABLES LIKE 'tbl_admin'");
    if ($hasAdminTbl && $hasAdminTbl->num_rows > 0) {
        if ($ar = $conn->query("SELECT Admin_Id, Email FROM tbl_admin WHERE COALESCE(NULLIF(TRIM(Email),''),'') <> ''")) {
            while ($row = $ar->fetch_assoc()) { $admins[] = $row; }
        }
    }
    if (!empty($admins)) {
        if ($hasAdminIdCol) {
            if ($ina = $conn->prepare("INSERT INTO tbl_notifications (Email, Admin_Id, Message, Type) VALUES (?, ?, ?, 'patient_cancel_admin')")) {
                foreach ($admins as $a) { $ae = $a['Email']; $aid = (int)$a['Admin_Id']; $ina->bind_param('sis', $ae, $aid, $msg); $ina->execute(); }
                $ina->close();
            }
        } else {
            if ($ina = $conn->prepare("INSERT INTO tbl_notifications (Email, Message, Type) VALUES (?, ?, 'patient_cancel_admin')")) {
                foreach ($admins as $a) { $ae = $a['Email']; $ina->bind_param('ss', $ae, $msg); $ina->execute(); }
                $ina->close();
            }
        }
    }

    echo json_encode(['ok'=>true]);
    exit();
}

// Handle AJAX cancellation
if (isset($_GET['cancel_id']) && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $cancel_id = intval($_GET['cancel_id']);
    $email = $_SESSION['email'];
    
    $stmt = $conn->prepare("UPDATE tbl_appointments SET Status = 'Cancelled' WHERE Appointment_Id = ? AND Email = ?");
    $stmt->bind_param("is", $cancel_id, $email);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Fetch appointment details for notification
        $d = $conn->prepare("SELECT Dentist_Id, `Date`, `Time` FROM tbl_appointments WHERE Appointment_Id = ? LIMIT 1");
        if ($d) { $d->bind_param('i', $cancel_id); $d->execute(); $dr = $d->get_result(); $appt = $dr ? $dr->fetch_assoc() : null; $d->close(); }
        // Notify dentist (no explicit reason/notes here)
        $conn->query("CREATE TABLE IF NOT EXISTS tbl_notifications (Id INT AUTO_INCREMENT PRIMARY KEY, Email VARCHAR(255) NOT NULL, Message TEXT NOT NULL, Type VARCHAR(64) NOT NULL, Is_Read TINYINT(1) DEFAULT 0, Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (Email), INDEX (Type), INDEX (Is_Read)) ENGINE=InnoDB");
        $dentistEmail = '';
        $dentistId = isset($appt['Dentist_Id']) ? (int)$appt['Dentist_Id'] : 0;
        if ($dentistId > 0) {
            $hasEmailCol = false;
            if ($col = $conn->query("SHOW COLUMNS FROM tbl_dentist LIKE 'Email'")) { $hasEmailCol = $col->num_rows > 0; }
            if ($hasEmailCol) {
                if ($ds = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(Email),''), '') AS Email FROM tbl_dentist WHERE Dentist_id = ? LIMIT 1")) {
                    $ds->bind_param('i', $dentistId);
                    if ($ds->execute()) { $dr = $ds->get_result(); if ($row = $dr->fetch_assoc()) { $dentistEmail = $row['Email'] ?: ''; } }
                    $ds->close();
                }
            }
            if ($dentistEmail === '') { $dentistEmail = 'dentist'.$dentistId.'@local'; }
        }
        // Build message with patient name + treatment + datetime (no reason for ajax path)
        $when = '';
        if (!empty($appt['Date']) && !empty($appt['Time'])) { $when = date('M j, Y g:i A', strtotime($appt['Date'].' '.substr($appt['Time'],0,5))); }
        $patientName = '';
        $treat = '';
        if ($d2 = $conn->prepare("SELECT a.Email, a.`Procedure`, p.First_name, p.Last_name FROM tbl_appointments a LEFT JOIN tbl_patient p ON a.Email = p.Email WHERE a.Appointment_Id = ? LIMIT 1")) {
            $d2->bind_param('i', $cancel_id);
            if ($d2->execute()) {
                $dr2 = $d2->get_result();
                if ($row2 = $dr2->fetch_assoc()) {
                    $treat = trim((string)($row2['Procedure'] ?? 'appointment'));
                    $patientName = trim(((string)($row2['First_name'] ?? '')).' '.((string)($row2['Last_name'] ?? '')));
                }
            }
            $d2->close();
        }
        if ($patientName === '') { $patientName = 'Patient'; }
        $msg = $patientName.' cancelled '.($treat !== '' ? $treat : 'an appointment').($when?(' on '.$when):'').'.';
        if ($ins = $conn->prepare("INSERT INTO tbl_notifications (Email, Message, Type) VALUES (?, ?, 'patient_cancel')")) {
            $ins->bind_param('ss', $dentistEmail, $msg);
            $ins->execute();
            $ins->close();
        }
        // Notify all admins as well (include Admin_Id if column exists)
        $hasAdminIdCol = false; if ($col = $conn->query("SHOW COLUMNS FROM tbl_notifications LIKE 'Admin_Id'")) { $hasAdminIdCol = $col->num_rows > 0; }
        if (!$hasAdminIdCol) { $conn->query("ALTER TABLE tbl_notifications ADD COLUMN Admin_Id INT NULL AFTER Email"); $conn->query("CREATE INDEX IF NOT EXISTS idx_admin_id ON tbl_notifications (Admin_Id)"); $hasAdminIdCol = true; }
        $admins = [];
        $hasAdminTbl = $conn->query("SHOW TABLES LIKE 'tbl_admin'");
        if ($hasAdminTbl && $hasAdminTbl->num_rows > 0) {
            if ($ar = $conn->query("SELECT Admin_Id, Email FROM tbl_admin WHERE COALESCE(NULLIF(TRIM(Email),''),'') <> ''")) {
                while ($row = $ar->fetch_assoc()) { $admins[] = $row; }
            }
        }
        if (!empty($admins)) {
            if ($hasAdminIdCol) {
                if ($ina = $conn->prepare("INSERT INTO tbl_notifications (Email, Admin_Id, Message, Type) VALUES (?, ?, ?, 'patient_cancel_admin')")) {
                    foreach ($admins as $a) { $ae = $a['Email']; $aid = (int)$a['Admin_Id']; $ina->bind_param('sis', $ae, $aid, $msg); $ina->execute(); }
                    $ina->close();
                }
            } else {
                if ($ina = $conn->prepare("INSERT INTO tbl_notifications (Email, Message, Type) VALUES (?, ?, 'patient_cancel_admin')")) {
                    foreach ($admins as $a) { $ae = $a['Email']; $ina->bind_param('ss', $ae, $msg); $ina->execute(); }
                    $ina->close();
                }
            }
        }

        echo json_encode(['ok' => true, 'id' => $cancel_id]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Cancellation failed']);
    }
    $stmt->close();
    exit();
}

// Default response
header('Content-Type: application/json');
echo json_encode(['ok' => false, 'error' => 'Invalid request']);
?>
