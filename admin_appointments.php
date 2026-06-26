<?php
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include 'admin_init.php';
} else {
  include 'db_connect.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

// Include inventory deduction functions
include 'inventory_deduction.php';
// Email helpers for appointment notifications (optional)
if (file_exists(__DIR__ . '/appointment_notifications.php')) {
  include_once __DIR__ . '/appointment_notifications.php';
}

// Lightweight suggestions endpoint for patient names
if (isset($_GET['suggest']) && $_GET['suggest'] === 'patients' && isset($conn)) {
  header('Content-Type: application/json');
  $q = trim((string)($_GET['q'] ?? ''));
  $out = [];
  if ($q !== '') {
    $like = $q.'%';
    $likeAny = '%'.$q.'%';
    $sql = "SELECT TRIM(CONCAT(COALESCE(NULLIF(First_name,''),''),' ',COALESCE(NULLIF(Last_name,''),''))) AS FullName, Email
            FROM tbl_patient
            WHERE First_name LIKE ? OR Last_name LIKE ? OR CONCAT(First_name,' ',Last_name) LIKE ? OR Email LIKE ?
            ORDER BY First_name ASC, Last_name ASC
            LIMIT 10";
    if ($st = $conn->prepare($sql)) {
      $st->bind_param('ssss', $like, $like, $likeAny, $likeAny);
      if ($st->execute()) {
        $rs = $st->get_result();
        while ($row = $rs->fetch_assoc()) {
          $name = trim((string)($row['FullName'] ?? ''));
          $email = (string)($row['Email'] ?? '');
          $label = $name !== '' ? $name.' — '.$email : $email;
          $out[] = ['value' => $label];
        }
      }
      $st->close();
    }
  }
  echo json_encode(['ok'=>true,'items'=>$out]);
  exit();
}

// Load dentist names dynamically from tbl_dentist for filters and labels

// Create an in-app notification (and ensure table exists)
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

// Common filter/state vars (used later in page)
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$range = isset($_GET['range']) ? trim($_GET['range']) : '';
$weekStartParam = isset($_GET['week_start']) ? trim($_GET['week_start']) : '';
$showCompleted = isset($_GET['show_completed']) ? (bool)$_GET['show_completed'] : false;
$dentistFilter = isset($_GET['dentist_filter']) ? trim($_GET['dentist_filter']) : 'all';
if ($range === 'week') {
  $refDate = $weekStartParam ?: $selectedDate ?: date('Y-m-d');
  $refTs = strtotime($refDate);
  $weekday = (int)date('N', $refTs);
  $currentWeekStart = date('Y-m-d', strtotime('-' . ($weekday - 1) . ' days', $refTs));
} else {
  $currentWeekStart = date('Y-m-d', strtotime('monday this week'));
}
$prevWeekStart = date('Y-m-d', strtotime('-7 days', strtotime($currentWeekStart)));
$nextWeekStart = date('Y-m-d', strtotime('+7 days', strtotime($currentWeekStart)));
$apptActionAlert = '';

// CSV Export (simple filtered export)
if (isset($_GET['export']) && $_GET['export'] === 'csv' && isset($conn)) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=appointments_export_'.date('Ymd_His').'.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Name','Procedure','Date','Doctor','Time','Status','Notes']);
  $sqlE = "SELECT a.Date, a.Time, a.`Procedure`, a.Status, a.Admin_Notes,
                  CONCAT(COALESCE(p.First_name,''),' ',COALESCE(p.Last_name,'')) AS Patient_Name,
                  COALESCE(d.Name, CONCAT('Dentist #', a.Dentist_Id)) AS Dentist_Name
           FROM tbl_appointments a
           LEFT JOIN tbl_patient p ON (p.Patient_Id = a.Patient_Id OR p.Email = a.Email)
           LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id
           WHERE 1=1";
  $typesE = '';
  $paramsE = [];
  // Filters
  if ($searchQuery !== '') {
    $sqlE .= " AND (p.First_name LIKE ? OR p.Last_name LIKE ? OR CONCAT(p.First_name,' ',p.Last_name) LIKE ? OR a.Email LIKE ?)";
    $typesE .= 'ssss'; $like = '%'.$searchQuery.'%'; array_push($paramsE, $like,$like,$like,$like);
  }
  if (!empty($_GET['status']) && $_GET['status'] !== 'all') { $sqlE .= " AND a.Status = ?"; $typesE .= 's'; $paramsE[] = $_GET['status']; }
  if ($dentistFilter !== 'all' && ctype_digit($dentistFilter)) { $sqlE .= " AND a.Dentist_Id = ?"; $typesE .= 'i'; $paramsE[] = (int)$dentistFilter; }
  $dateFrom = trim($_GET['date_from'] ?? ''); $dateTo = trim($_GET['date_to'] ?? '');
  if ($dateFrom !== '' && $dateTo !== '') { $sqlE .= " AND a.Date BETWEEN ? AND ?"; $typesE .= 'ss'; array_push($paramsE,$dateFrom,$dateTo); }
  elseif ($dateFrom !== '') { $sqlE .= " AND a.Date >= ?"; $typesE .= 's'; $paramsE[] = $dateFrom; }
  elseif ($dateTo !== '') { $sqlE .= " AND a.Date <= ?"; $typesE .= 's'; $paramsE[] = $dateTo; }
  $sqlE .= " ORDER BY a.Date ASC, a.Time ASC";
  $stmtE = $conn->prepare($sqlE);
  if ($stmtE) {
    if ($typesE !== '' && !empty($paramsE)) { $stmtE->bind_param($typesE, ...$paramsE); }
    $stmtE->execute(); $resE = $stmtE->get_result();
    while ($r = $resE->fetch_assoc()) {
      $name = trim((string)$r['Patient_Name']) !== '' ? $r['Patient_Name'] : '';
      $proc = (string)$r['Procedure'];
      $proc = preg_replace('/\s*\|\s*(Contact|Address)\s*:[^|]*/i', '', $proc);
      $proc = trim(preg_replace('/\s*\|\s*$/', '', $proc));
      $status = ($r['Status']==='Finished') ? 'Completed' : $r['Status'];
      fputcsv($out, [$name, $proc, $r['Date'], $r['Dentist_Name'], $r['Time'], $status, $r['Admin_Notes']]);
    }
    $stmtE->close();
  }
  fclose($out);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($conn)) {
  // Save admin notes
  if (isset($_POST['save_notes_id'])) {
    $nid = (int)$_POST['save_notes_id'];
    $ncontent = trim((string)($_POST['notes_content'] ?? ''));
    $up = $conn->prepare("UPDATE tbl_appointments SET Admin_Notes = ? , Updated_At = NOW() WHERE Appointment_Id = ?");
    if ($up) { $up->bind_param('si', $ncontent, $nid); $up->execute(); $up->close(); }
    // Also update treatment history so notes added after completion are reflected there as well
    if ($hist = $conn->prepare("UPDATE tbl_treatment_history SET Admin_Notes = ? WHERE Appointment_Id = ?")) {
      $hist->bind_param('si', $ncontent, $nid);
      $hist->execute();
      $hist->close();
    }
    // PRG
    header('Location: admin_appointments.php?success=notes_saved');
    exit();
  }
  // Add Walk-in appointment (no email required; generate placeholder)
  if (isset($_POST['add_walkin'])) {
    $firstName = trim($_POST['walkin_first_name'] ?? '');
    $middleName = trim($_POST['walkin_middle_name'] ?? '');
    $lastName  = trim($_POST['walkin_last_name'] ?? '');
    $address   = trim($_POST['walkin_address'] ?? '');
    $contact   = trim($_POST['walkin_contact'] ?? '');
    $contact   = preg_replace('/\D+/', '', $contact);
    $procedure = $_POST['walkin_procedure'] ?? '';
    $dentistId = intval($_POST['walkin_dentist_id'] ?? 0);
    $date      = trim($_POST['walkin_date'] ?? '');
    $time      = trim($_POST['walkin_time'] ?? '');
    $room      = trim($_POST['walkin_room'] ?? '');

    $isProcEmpty = is_array($procedure) ? (count(array_filter($procedure, function($v){ return trim((string)$v) !== ''; })) === 0) : (trim((string)$procedure) === '');
    if ($firstName === '' || $lastName === '' || $contact === '' || $isProcEmpty || $dentistId <= 0 || $date === '' || $time === '') {
      $apptActionAlert = '<div class="alert alert-danger">Please complete all required walk-in fields.</div>';
    } else {
      // Block walk-in if an existing patient with this contact is Blocked
      try {
        $blocked = false;
        // Normalize number for comparison
        $norm = preg_replace('/\D+/', '', $contact);
        if ($norm !== '' && isset($conn)) {
          // Try Phone and Phone_num columns robustly
          $sqlB = "SELECT Status FROM tbl_patient WHERE REPLACE(REPLACE(REPLACE(COALESCE(NULLIF(Phone,''), NULLIF(Phone_num,''), ''), ' ', ''), '-', ''), '+', '') = ? LIMIT 1";
          if ($stb = $conn->prepare($sqlB)) {
            $stb->bind_param('s', $norm);
            $stb->execute();
            $rb = $stb->get_result();
            if ($row = $rb->fetch_assoc()) { if (strcasecmp((string)$row['Status'], 'Blocked') === 0) { $blocked = true; } }
            $stb->close();
          }
        }
        if ($blocked) {
          $apptActionAlert = '<div class="alert alert-danger">This contact number belongs to a blocked patient. Walk-in booking is not allowed.</div>';
        }
      } catch (Throwable $e) { /* ignore */ }
      if (!preg_match('/^09\d{9}$/', $contact)) {
        $apptActionAlert = '<div class="alert alert-danger">Contact number must be 11 digits and start with 09.</div>';
      }
      // Normalize to 5-minute steps and clinic hours
      $ts = strtotime($time);
      if ($ts !== false) {
        $mins = intval(date('i', $ts));
        $hrs  = intval(date('H', $ts));
        $mins = intval(round($mins / 5) * 5);
        if ($mins >= 60) { $hrs += 1; $mins = 0; }
        if ($hrs < 7) { $hrs = 7; $mins = 0; }
        if ($hrs > 18 || ($hrs === 18 && $mins > 55)) { $hrs = 18; $mins = 55; }
        $time = sprintf('%02d:%02d:00', $hrs, $mins);
      }
      if ($time < '07:00:00' || $time > '19:00:00') {
        $apptActionAlert = '<div class="alert alert-danger">Time is outside clinic hours.</div>';
      } else {
        // Disallow past datetime selections
        $selectedTs = strtotime($date . ' ' . $time);
        if ($selectedTs !== false && $selectedTs < time()) {
          $apptActionAlert = '<div class="alert alert-danger">Selected date/time is in the past.</div>';
        }
      }
      if (empty($apptActionAlert)) {
        // Determine total duration from selected procedure(s)
        $totalDuration = 0;
        // Default duration should match appointment duration setting (preferences)
        $defaultDuration = 60;
        try {
          if (file_exists(__DIR__ . '/settings_helper.php') && isset($conn)) {
            include_once __DIR__ . '/settings_helper.php';
            $as = function_exists('getAppointmentSettings') ? getAppointmentSettings($conn) : [];
            if (!empty($as) && isset($as['appointment_duration']) && (int)$as['appointment_duration'] > 0) {
              $defaultDuration = (int)$as['appointment_duration'];
            }
          }
        } catch (Throwable $e) { /* ignore */ }
        // Source of truth for durations: appointment_config.php only (to match patient booking)
        include_once __DIR__ . '/appointment_config.php';
        if (isset($treatments) && is_array($treatments)) {
          $map = [];
          foreach ($treatments as $t) {
            if (!empty($t['name'])) {
              $nm = trim((string)$t['name']);
              $dur = isset($t['duration']) ? (int)$t['duration'] : 0;
              if ($dur <= 0) { $dur = $defaultDuration; }
              $map[$nm] = $dur;
            }
          }
          if (is_array($procedure)) {
            foreach ($procedure as $pname) { $pname = trim((string)$pname); $totalDuration += $map[$pname] ?? $defaultDuration; }
          } else {
            $pname = trim((string)$procedure); $totalDuration += $map[$pname] ?? $defaultDuration;
          }
        }
        if ($totalDuration <= 0) { $totalDuration = $defaultDuration; }

        // Duration-aware dentist conflict check (overlap): existing_start < requested_end AND requested_start < existing_end
        $reqStart = $time; $reqDuration = (int)$totalDuration; if ($reqDuration <= 0) { $reqDuration = 30; }
        $check = $conn->prepare("SELECT Appointment_Id FROM tbl_appointments\n                                  WHERE Dentist_Id = ? AND Date = ? AND Status IN ('Booked','Confirmed','Pending','Ongoing')\n                                    AND (\n                                      TIME_TO_SEC(Time) < TIME_TO_SEC(ADDTIME(?, SEC_TO_TIME(?*60)))\n                                      AND\n                                      TIME_TO_SEC(?) < TIME_TO_SEC(ADDTIME(Time, SEC_TO_TIME(COALESCE(Duration, 30)*60)))\n                                    )\n                                  LIMIT 1");
        if ($check) {
          $check->bind_param('issis', $dentistId, $date, $reqStart, $reqDuration, $reqStart);
          $check->execute();
          $check->store_result();
          if ($check->num_rows > 0) {
            $apptActionAlert = '<div class="alert alert-danger">Selected time overlaps another appointment for this dentist.</div>';
          }
          $check->close();
        }

        // Room duration-aware conflict check (optional: only when a room is specified)
        if (empty($apptActionAlert) && $room !== '') {
          $reqStart = $time; $reqDuration = (int)$totalDuration; if ($reqDuration <= 0) { $reqDuration = 30; }
          $chkRoom = $conn->prepare("SELECT Appointment_Id FROM tbl_appointments\n                                      WHERE Date = ? AND Room = ? AND Room <> '' AND Status IN ('Booked','Confirmed','Pending','Ongoing')\n                                        AND (\n                                          TIME_TO_SEC(Time) < TIME_TO_SEC(ADDTIME(?, SEC_TO_TIME(?*60)))\n                                          AND\n                                          TIME_TO_SEC(?) < TIME_TO_SEC(ADDTIME(Time, SEC_TO_TIME(COALESCE(Duration, 30)*60)))\n                                        )\n                                      LIMIT 1");
          if ($chkRoom) {
            $chkRoom->bind_param('sssis', $date, $room, $reqStart, $reqDuration, $reqStart);
            $chkRoom->execute();
            $chkRoom->store_result();
            if ($chkRoom->num_rows > 0) {
              $apptActionAlert = '<div class="alert alert-danger">Selected room time overlaps another appointment.</div>';
            }
            $chkRoom->close();
          }
        }

        if (empty($apptActionAlert)) {
          $adminNotes = '';

          // Try to reuse existing patient by phone if the column exists
          $patientId = null;
          $email = '';
          $hasPhoneCol = false; $hasPhoneNumCol = false; $hasAddressCol = false;
          if ($cols = $conn->query("SHOW COLUMNS FROM tbl_patient")) {
            while ($c = $cols->fetch_assoc()) {
              if (strcasecmp($c['Field'], 'Phone') === 0) $hasPhoneCol = true;
              if (strcasecmp($c['Field'], 'Phone_num') === 0) $hasPhoneNumCol = true;
              if (strcasecmp($c['Field'], 'Address') === 0) $hasAddressCol = true;
            }
            $cols->close();
          }

          if (false && $contact !== '' && ($hasPhoneCol || $hasPhoneNumCol)) {
            $where = [];
            $types = '';
            $vals = [];
            if ($hasPhoneCol) { $where[] = 'Phone = ?'; $types .= 's'; $vals[] = $contact; }
            if ($hasPhoneNumCol) { $where[] = 'Phone_num = ?'; $types .= 's'; $vals[] = $contact; }
            if (!empty($where)) {
              $sqlFind = "SELECT Patient_Id, Email FROM tbl_patient WHERE " . implode(' OR ', $where) . " LIMIT 1";
              if ($st = $conn->prepare($sqlFind)) {
                $st->bind_param($types, ...$vals);
                if ($st->execute()) {
                  $r = $st->get_result();
                  if ($r && ($row = $r->fetch_assoc())) { $patientId = (int)$row['Patient_Id']; $email = (string)$row['Email']; }
                }
                $st->close();
              }
            }
          }

          if (!$patientId) {
            // Build anonymized placeholder email for schema compatibility (no PII like name/phone)
            $uniqueToken = bin2hex(random_bytes(4));
            $email = 'walkin' . time() . '_' . $uniqueToken . '@clinic.local';
            $randPass = password_hash(bin2hex(random_bytes(6)), PASSWORD_DEFAULT);
            if ($insP = $conn->prepare("INSERT INTO tbl_patient (Email, Password, First_name, Middle_name, Last_name, Status, Email_Verified, Patient_Type) VALUES (?, ?, ?, ?, ?, 'Active', 1, 'Walk-in')")) {
              $insP->bind_param('sssss', $email, $randPass, $firstName, $middleName, $lastName);
              if ($insP->execute()) { $patientId = (int)$conn->insert_id; }
              $insP->close();
            }
            if (!$patientId) {
              if ($sel2 = $conn->prepare("SELECT Patient_Id FROM tbl_patient WHERE Email = ? LIMIT 1")) {
                $sel2->bind_param('s', $email);
                if ($sel2->execute()) {
                  $r2 = $sel2->get_result();
                  if ($r2 && ($rw = $r2->fetch_assoc())) { $patientId = (int)$rw['Patient_Id']; }
                }
                $sel2->close();
              }
            }

            // Persist optional fields if columns exist
            if ($patientId) {
              $sets = [];
              $vals = [];
              $types = '';
              if ($hasAddressCol && $address !== '') { $sets[] = 'Address = ?'; $vals[] = $address; $types .= 's'; }
              if ($hasPhoneCol && $contact !== '') { $sets[] = 'Phone = ?'; $vals[] = $contact; $types .= 's'; }
              if ($hasPhoneNumCol && $contact !== '') { $sets[] = 'Phone_num = ?'; $vals[] = $contact; $types .= 's'; }
              if (!empty($sets)) {
                $sql = 'UPDATE tbl_patient SET ' . implode(', ', $sets) . ' WHERE Patient_Id = ?';
                $types .= 'i';
                $vals[] = $patientId;
                if ($up = $conn->prepare($sql)) {
                  $up->bind_param($types, ...$vals);
                  $up->execute();
                  $up->close();
                }
              }
            }
          }

          // Final safety: if we still don't have an email (reused patient path), fetch by id
          if ($email === '' && $patientId) {
            if ($se = $conn->prepare("SELECT Email FROM tbl_patient WHERE Patient_Id = ? LIMIT 1")) {
              $se->bind_param('i', $patientId);
              if ($se->execute()) { $re = $se->get_result(); if ($re && ($rw = $re->fetch_assoc())) { $email = (string)$rw['Email']; } }
              $se->close();
            }
          }

          // Prevent duplicate active appointment for same patient with the SAME dentist on the SAME date
          // Allow booking with a different dentist on the same date
          if (empty($apptActionAlert) && $email !== '' && $dentistId > 0) {
            $dup = $conn->prepare("SELECT Appointment_Id FROM tbl_appointments 
                                    WHERE Date = ? 
                                      AND Dentist_Id = ? 
                                      AND Status IN ('Booked','Confirmed','Pending','Ongoing') 
                                      AND (Email = ? OR (Patient_Id IS NOT NULL AND Patient_Id = ?))
                                    LIMIT 1");
            if ($dup) {
              $pidCheck = $patientId ? $patientId : 0;
              $dup->bind_param('siss', $date, $dentistId, $email, $pidCheck);
              $dup->execute();
              $dup->store_result();
              if ($dup->num_rows > 0) {
                $apptActionAlert = '<div class="alert alert-danger">You already have an appointment on this date with this dentist.</div>';
              }
              $dup->close();
            }
          }

          if (empty($apptActionAlert)) {
            // Build SQL dynamically to handle NULL Patient_Id cleanly
            $procStr = is_array($procedure) ? implode(', ', array_values(array_filter($procedure, function($v){ return trim((string)$v) !== ''; }))) : trim((string)$procedure);
            $pidParam = $patientId ? $patientId : null;
            $durParam = (int)$totalDuration; if ($durParam <= 0) { $durParam = $defaultDuration; }
            $sqlIns = "INSERT INTO tbl_appointments (Email, Patient_Id, Dentist_Id, `Procedure`, Date, Time, Duration, Room, Admin_Notes, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Confirmed')";
            $stmt = $conn->prepare($sqlIns);
            if ($stmt) {
              // Always bind Patient_Id, allowing NULL
              $stmt->bind_param('siisssiss', $email, $pidParam, $dentistId, $procStr, $date, $time, $durParam, $room, $adminNotes);
              if ($stmt->execute()) {
                $newApptId = $conn->insert_id;
                if ($newApptId && is_array($procedure)) {
                  $insT = $conn->prepare("INSERT INTO tbl_appointment_treatments (appointment_id, treatment_name, price, duration, notes) VALUES (?, ?, 0, 0, '')");
                  if ($insT) {
                    foreach ($procedure as $pname) {
                      $pname = trim((string)$pname);
                      if ($pname === '') continue;
                      $insT->bind_param('is', $newApptId, $pname);
                      $insT->execute();
                    }
                    $insT->close();
                  }
                }
                $apptActionAlert = '<div class="alert alert-success">Walk-in appointment added and confirmed.</div>';
              } else {
                $apptActionAlert = '<div class="alert alert-danger">Failed to add walk-in appointment.</div>';
              }
              $stmt->close();
            }
          }
        }
      }
    }

    // Disable submit to prevent double-clicks (used for Confirm/Cancel/etc.)
  }

  // Complete
  if (isset($_POST['complete_id'])) {
    $completeId = (int)$_POST['complete_id'];
    $apptDetails = null;
    
    // First get appointment details for notification and inventory deduction
    $getAppt = $conn->prepare("SELECT a.Email, a.Date, a.Time, a.`Procedure`, p.First_name, p.Last_name 
                               FROM tbl_appointments a 
                               LEFT JOIN tbl_patient p ON a.Email = p.Email 
                               WHERE a.Appointment_Id = ?");
    if ($getAppt) {
      $getAppt->bind_param('i', $completeId);
      $getAppt->execute();
      $apptDetails = $getAppt->get_result()->fetch_assoc();
      $getAppt->close();
    }
    
    // Validate appointment details exist
    if (!$apptDetails || empty($apptDetails['Procedure'])) {
      $apptActionAlert = '<div class="alert alert-danger">Appointment not found or invalid procedure.</div>';
    } else {
      // Normalize procedure name for inventory checks (strip extra annotations like Contact/Address)
      $procRaw = (string)$apptDetails['Procedure'];
      $procBase = preg_replace('/\s*\|\s*(Contact|Address)\s*:[^|]*/i', '', $procRaw);
      $procBase = trim(preg_replace('/\s*\|\s*$/', '', $procBase));

      // Check inventory availability before completing treatment using base name
      $inventoryCheck = canCompleteTreatment($conn, $procBase);
      $inventoryMessage = '';

      if (!$inventoryCheck['can_complete']) {
        $blockingItems = array_map(function($item) {
          return $item['item_name'] . ' (Need: ' . $item['quantity_needed'] . ', Have: ' . $item['current_stock'] . ')';
        }, $inventoryCheck['blocking_items']);
        
        $inventoryMessage = '<div class="alert alert-warning"><strong>Cannot complete treatment:</strong> Insufficient inventory for: ' . implode(', ', $blockingItems) . '</div>';
      } else {
        // Proceed with completion and inventory deduction
        $upd = $conn->prepare("UPDATE tbl_appointments SET Status = 'Finished', Updated_At = NOW() WHERE Appointment_Id = ? AND Status NOT IN ('Finished','Cancelled')");
        if ($upd) {
          $upd->bind_param('i', $completeId);
          if ($upd->execute()) { 
            
            // Deduct inventory for the completed treatment
            $deductionResult = deductInventoryForTreatment($conn, $procBase);
            
            // Log the inventory deduction
            logInventoryDeduction($conn, $completeId, $procBase, $deductionResult);
            
            // Prepare success message with inventory details
            $successMessage = '<div class="alert alert-success"><strong>Treatment completed successfully!</strong><br>';
            
            if ($deductionResult['success'] && !empty($deductionResult['deducted_items'])) {
              $successMessage .= '<strong>Inventory deducted:</strong><ul class="mb-0">';
              foreach ($deductionResult['deducted_items'] as $item) {
                $successMessage .= '<li>' . $item['item'] . ' (-' . $item['quantity_used'] . ', Remaining: ' . $item['remaining'] . ')</li>';
              }
              $successMessage .= '</ul>';
            }
            
            if (!empty($deductionResult['failed_items'])) {
              $successMessage .= '<strong>Inventory issues:</strong><ul class="mb-0">';
              foreach ($deductionResult['failed_items'] as $item) {
                $successMessage .= '<li>' . $item['item'] . ': ' . $item['reason'] . '</li>';
              }
              $successMessage .= '</ul>';
            }
            
            $successMessage .= '</div>';
            $apptActionAlert = $successMessage;
            
            // Store notification for patient (distinct message/type from confirm)
            if (isset($apptDetails)) {
              $message = "Your appointment for " . $apptDetails['Procedure'] . " on " . date('M j, Y', strtotime($apptDetails['Date'])) . " at " . date('g:i A', strtotime($apptDetails['Time'])) . " has been COMPLETED. Thank you for choosing our clinic!";
              createNotification($conn, $apptDetails['Email'], $message, 'appointment_completed');
              // Email: completed template (different from confirm)
              if (function_exists('sendAppointmentCompletedEmail') && isset($apptDetails['Email']) && stripos((string)$apptDetails['Email'], '@clinic.local') === false) {
                $patientName = trim(($apptDetails['First_name'] ?? '') . ' ' . ($apptDetails['Last_name'] ?? ''));
                @sendAppointmentCompletedEmail(
                  (string)$apptDetails['Email'],
                  $patientName,
                  (string)$apptDetails['Date'],
                  (string)$apptDetails['Time'],
                  (string)$apptDetails['Procedure']
                );
              }
            }

            // PRG: redirect to avoid duplicate post on refresh
            header('Location: admin_appointments.php?success=completed');
            exit();
          }
          else { $apptActionAlert = '<div class="alert alert-danger">Failed to update appointment.</div>'; }
          $upd->close();
        }
      }
      
      // Show inventory warning if treatment cannot be completed
      if (!empty($inventoryMessage)) {
        $apptActionAlert = $inventoryMessage;
      }
    }
  }

  // Confirm
  if (isset($_POST['confirm_id'])) {
    $confirmId = (int)$_POST['confirm_id'];
    $apptDetails = null;
    
    // First get appointment details for notification
    $getAppt = $conn->prepare("SELECT a.Email, a.Date, a.Time, a.`Procedure`, a.Dentist_Id, p.First_name, p.Last_name 
                               FROM tbl_appointments a 
                               LEFT JOIN tbl_patient p ON a.Email = p.Email 
                               WHERE a.Appointment_Id = ?");
    if ($getAppt) {
      $getAppt->bind_param('i', $confirmId);
      $getAppt->execute();
      $apptDetails = $getAppt->get_result()->fetch_assoc();
      $getAppt->close();
    }
    
    // Enforce 24-hour cooldown for same dentist at confirmation time
    if (!empty($apptDetails['Email']) && !empty($apptDetails['Dentist_Id']) && !empty($apptDetails['Date']) && !empty($apptDetails['Time'])) {
      $email = $apptDetails['Email'];
      $dentistId = (int)$apptDetails['Dentist_Id'];
      $d = $apptDetails['Date'];
      $t = $apptDetails['Time'];
      $chk = $conn->prepare("SELECT Appointment_Id FROM tbl_appointments 
                              WHERE Email = ? AND Dentist_Id = ? AND Status = 'Confirmed' AND Appointment_Id <> ?
                                AND ABS(TIMESTAMPDIFF(HOUR, CONCAT(`Date`,' ',`Time`), CONCAT(?, ' ', ?))) < 24
                              LIMIT 1");
      if ($chk) {
        $chk->bind_param('siiss', $email, $dentistId, $confirmId, $d, $t);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
          $apptActionAlert = '<div class="alert alert-warning">Cannot confirm: patient has another confirmed appointment with this dentist within 24 hours.</div>';
          $chk->close();
          // PRG
          header('Location: admin_appointments.php?error=cooldown');
          exit();
        }
        $chk->close();
      }
    }

    // Update status to confirmed
    $upd = $conn->prepare("UPDATE tbl_appointments SET Status = 'Confirmed', Updated_At = NOW() WHERE Appointment_Id = ? AND Status NOT IN ('Confirmed','Finished','Cancelled')");
    if ($upd) {
      $upd->bind_param('i', $confirmId);
      if ($upd->execute()) { 
        $apptActionAlert = '<div class="alert alert-success">Appointment confirmed. Patient will be notified.</div>';
        
        // Store notification for patient
        if (isset($apptDetails)) {
          $message = "Your appointment for " . $apptDetails['Procedure'] . " on " . date('M j, Y', strtotime($apptDetails['Date'])) . " at " . date('g:i A', strtotime($apptDetails['Time'])) . " has been CONFIRMED by admin.";
          createNotification($conn, $apptDetails['Email'], $message, 'appointment_confirmed');
          // Try sending email too, if helpers are available
          if (function_exists('sendAppointmentConfirmedEmail') && isset($apptDetails['Email']) && stripos((string)$apptDetails['Email'], '@clinic.local') === false) {
            $patientName = trim(($apptDetails['First_name'] ?? '') . ' ' . ($apptDetails['Last_name'] ?? ''));
            @sendAppointmentConfirmedEmail(
              (string)$apptDetails['Email'],
              $patientName,
              (string)$apptDetails['Date'],
              (string)$apptDetails['Time'],
              (string)$apptDetails['Procedure']
            );
          }
        }

        // Insert into treatment history on confirm so it appears in history/records immediately (avoid duplicates)
        $historyStmt = $conn->prepare("INSERT INTO tbl_treatment_history (Appointment_Id, Patient_Id, Patient_Email, Dentist_Id, Procedure_Name, Treatment_Date, Treatment_Time, Room, Admin_Notes) 
                                      SELECT a.Appointment_Id, a.Patient_Id, a.Email, a.Dentist_Id, a.`Procedure`, a.Date, a.Time, a.Room, a.Admin_Notes 
                                      FROM tbl_appointments a 
                                      WHERE a.Appointment_Id = ?
                                        AND NOT EXISTS (SELECT 1 FROM tbl_treatment_history th WHERE th.Appointment_Id = a.Appointment_Id)");
        if ($historyStmt) {
          $historyStmt->bind_param('i', $confirmId);
          $historyStmt->execute();
          $historyStmt->close();
        }

        // PRG
        header('Location: admin_appointments.php?success=confirmed');
        exit();
      }
      else { $apptActionAlert = '<div class="alert alert-danger">Failed to confirm appointment.</div>'; }
      $upd->close();
    }
  }

  // Cancel
  if (isset($_POST['cancel_id'])) {
    $cancelId = (int)$_POST['cancel_id'];
    $apptDetails = null;
    
    // First get appointment details for notification
    $getAppt = $conn->prepare("SELECT a.Email, a.Date, a.Time, a.`Procedure`, p.First_name, p.Last_name 
                               FROM tbl_appointments a 
                               LEFT JOIN tbl_patient p ON a.Email = p.Email 
                               WHERE a.Appointment_Id = ?");
    if ($getAppt) {
      $getAppt->bind_param('i', $cancelId);
      $getAppt->execute();
      $apptDetails = $getAppt->get_result()->fetch_assoc();
      $getAppt->close();
    }
    
    $upd = $conn->prepare("UPDATE tbl_appointments SET Status = 'Cancelled', Updated_At = NOW() WHERE Appointment_Id = ? AND Status NOT IN ('Cancelled','Finished')");
    if ($upd) {
      $upd->bind_param('i', $cancelId);
      if ($upd->execute()) { 
        // Add to treatment history for cancelled appointments (avoid duplicates)
        $historyStmt = $conn->prepare("INSERT INTO tbl_treatment_history (Appointment_Id, Patient_Id, Patient_Email, Dentist_Id, Procedure_Name, Treatment_Date, Treatment_Time, Room, Admin_Notes) 
                                      SELECT a.Appointment_Id, a.Patient_Id, a.Email, a.Dentist_Id, a.`Procedure`, a.Date, a.Time, a.Room, a.Admin_Notes 
                                      FROM tbl_appointments a 
                                      WHERE a.Appointment_Id = ?
                                        AND NOT EXISTS (SELECT 1 FROM tbl_treatment_history th WHERE th.Appointment_Id = a.Appointment_Id)");
        if ($historyStmt) {
          $historyStmt->bind_param('i', $cancelId);
          $historyStmt->execute();
          $historyStmt->close();
        }
        $apptActionAlert = '<div class="alert alert-warning">Appointment cancelled. Patient will be notified.</div>';
        
        // Store notification for patient
        if (isset($apptDetails)) {
          $message = "Your appointment for " . $apptDetails['Procedure'] . " on " . date('M j, Y', strtotime($apptDetails['Date'])) . " at " . date('g:i A', strtotime($apptDetails['Time'])) . " has been CANCELLED by admin.";
          createNotification($conn, $apptDetails['Email'], $message, 'appointment_cancelled');
          // Try to send email as well (if helpers available)
          if (function_exists('sendAppointmentCancelledEmail') && isset($apptDetails['Email']) && stripos((string)$apptDetails['Email'], '@clinic.local') === false) {
            $patientName = trim(($apptDetails['First_name'] ?? '') . ' ' . ($apptDetails['Last_name'] ?? ''));
            $mailOk = sendAppointmentCancelledEmail(
              (string)$apptDetails['Email'],
              $patientName,
              (string)$apptDetails['Date'],
              (string)$apptDetails['Time'],
              (string)$apptDetails['Procedure']
            );
            if (!$mailOk) {
              error_log('Admin cancel: sendAppointmentCancelledEmail failed for ' . ($apptDetails['Email'] ?? 'unknown') . ' appt #' . $cancelId);
              $apptActionAlert = '<div class="alert alert-warning">Appointment cancelled. Note: Email could not be sent at this time.</div>';
            }
          }
        }

        // PRG
        header('Location: admin_appointments.php?success=cancelled');
        exit();
      }
      else { $apptActionAlert = '<div class="alert alert-danger">Failed to cancel appointment.</div>'; }
      $upd->close();
    }
  }

  // Reschedule (date/time only; keep dentist/room)
  if (isset($_POST['reschedule_id'])) {
    $resId = (int)$_POST['reschedule_id'];
    $newDate = trim($_POST['new_date'] ?? '');
    $newTime = trim($_POST['new_time'] ?? '');
    // Normalize to 5-minute steps and clamp to clinic hours 07:00–18:55
    if ($newTime !== '') {
      $ts = strtotime($newTime);
      if ($ts !== false) {
        $mins = (int)date('i', $ts);
        $hrs  = (int)date('H', $ts);
        $mins = (int)round($mins / 5) * 5;
        if ($mins >= 60) { $hrs += 1; $mins = 0; }
        if ($hrs < 7) { $hrs = 7; $mins = 0; }
        if ($hrs > 18 || ($hrs === 18 && $mins > 55)) { $hrs = 18; $mins = 55; }
        $newTime = sprintf('%02d:%02d:00', $hrs, $mins);
      }
    }
    if ($resId > 0 && $newDate && $newTime) {
      // Fetch details (no duration)
      $info = $conn->prepare("SELECT Appointment_Id, Dentist_Id, Room, Email, `Procedure`, `Date` AS Old_Date, `Time` AS Old_Time FROM tbl_appointments WHERE Appointment_Id = ?");
      $info->bind_param('i', $resId);
      $info->execute();
      $r = $info->get_result();
      if ($r && $r->num_rows === 1) {
        $ap = $r->fetch_assoc();
        $start_time = $newTime;
        if ($start_time < '07:00:00' || $start_time > '18:55:00') {
          $apptActionAlert = '<div class="alert alert-danger">Outside clinic hours.</div>';
        } else {
          // Dentist conflict
          // Block against active statuses
          $check = $conn->prepare("SELECT Appointment_Id FROM tbl_appointments WHERE Appointment_Id <> ? AND Dentist_Id = ? AND Date = ? AND Status IN ('Booked','Confirmed','Pending','Ongoing') AND Time = ?");
          $check->bind_param('iiss', $resId, $ap['Dentist_Id'], $newDate, $start_time);
          $check->execute();
          $check->store_result();
          $dentistConflict = $check->num_rows > 0;
          $check->close();

          // Room conflict
          // Block against active statuses
          $checkR = $conn->prepare("SELECT Appointment_Id FROM tbl_appointments WHERE Appointment_Id <> ? AND Room = ? AND Date = ? AND Status IN ('Booked','Confirmed','Pending','Ongoing') AND Time = ?");
          $checkR->bind_param('isss', $resId, $ap['Room'], $newDate, $start_time);
          $checkR->execute();
          $checkR->store_result();
          $roomConflict = $checkR->num_rows > 0;
          $checkR->close();

          if ($dentistConflict || $roomConflict) {
            $apptActionAlert = '<div class="alert alert-danger">Conflict detected. Please choose another time.</div>';
          } else {
            $upd = $conn->prepare("UPDATE tbl_appointments SET Date = ?, Time = ?, Status = 'Pending', Updated_At = NOW() WHERE Appointment_Id = ?");
            $upd->bind_param('ssi', $newDate, $newTime, $resId);
            if ($upd->execute()) { 
              $apptActionAlert = '<div class="alert alert-success">Appointment rescheduled. Patient will be notified.</div>';
              // Send reschedule email (best-effort)
              try {
                require_once __DIR__ . '/appointment_notifications.php';
                if (function_exists('sendAppointmentRescheduledEmail')) {
                  $oldDate = (string)($ap['Old_Date'] ?? $newDate);
                  $oldTime = (string)($ap['Old_Time'] ?? $newTime);
                  @sendAppointmentRescheduledEmail(
                    (string)$ap['Email'],
                    '',
                    $oldDate,
                    $oldTime,
                    $newDate,
                    $newTime,
                    (string)($ap['Procedure'] ?? ''),
                    ''
                  );
                }
              } catch (\Throwable $e) { /* ignore mail errors */ }

              // Store notification for patient
              $message = "Your appointment for " . $ap['Procedure'] . " has been RESCHEDULED to " . date('M j, Y', strtotime($newDate)) . " at " . date('g:i A', strtotime($newTime)) . " by admin.";
              createNotification($conn, $ap['Email'], $message, 'appointment_rescheduled');

              // PRG
              header('Location: admin_appointments.php?success=rescheduled');
              exit();
            }
            else { $apptActionAlert = '<div class="alert alert-danger">Failed to reschedule.</div>'; }
            $upd->close();
          }
        }
      }
      $info->close();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Appointments - Miles Dental Clinic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; }
    .layout { min-height: 100vh; }
    .content { padding: 16px; min-height: 100vh; overflow-y: auto; overflow-x: hidden; background: #ffffff; color: #111827; }
    /* Hide content area's scrollbars visually (keep scroll functionality) */
    .content { scrollbar-width: none; -ms-overflow-style: none; }
    .content::-webkit-scrollbar { width: 0; height: 0; }
    .card, .alert { background: #ffffff; color: #111827; border: 1px solid #e5e7eb; }
    .table { color: #111827; }
    .table thead { color: #111827; }
    .table-hover tbody tr:hover { background: rgba(2,6,23,0.03); }
    /* Hide horizontal scrollbar for table container but keep scroll gesture */
    .table-responsive { overflow-x: auto; }
    .table-responsive { scrollbar-width: none; -ms-overflow-style: none; }
    .table-responsive::-webkit-scrollbar { height: 0; width: 0; }
    /* Filters: date + search */
    .filter-form .form-label.small { font-size: 12px; font-weight: 600; margin-bottom: 4px; }
    .filter-form .form-control-sm { height: 38px; padding: 6px 10px; }
    .filter-form .btn { height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-weight: 600; }
    .filter-form .btn i { font-size: 0.95rem; line-height: 1; display: inline-block; }
    /* Quick range buttons */
    .quick-range { display:flex; gap:8px; align-items:end; flex-wrap:wrap; }
    .quick-range .btn { min-width: 110px; }
    .filter-form .quick-range .btn { height: 38px; }
    .filter-form .col-auto { display: flex; flex-direction: column; justify-content: flex-end; }
    /* Helpers to align the filter row perfectly */
    .filter-form .form-label.small { min-height: 18px; display: inline-block; margin-bottom: 4px; }
    .filter-form .fixed-180 { min-width: 180px; }
    .filter-form .fixed-actions { min-width: 160px; }
    .filter-form .actions-label { min-height: 18px; margin-bottom: 4px; visibility: hidden; }
    @media (max-width: 576px) {
      .filter-form .col-auto { width: 100%; }
      .filter-form .form-control-sm { width: 100%; }
      .filter-form .btn { width: 100%; height: 42px; font-size: 1rem; }
      .filter-form .btn i { font-size: 1rem; }
    }
    .table tbody td:last-child { border-top-right-radius: 8px; border-bottom-right-radius: 8px; }

    /* Action Buttons */
    .action-buttons-container { display: flex; flex-direction: column; gap: 0.5rem; }
    .action-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;}
    .icon-btn { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid rgba(255,255,255,0.12); color: #e5e7eb; background: transparent; }
    .icon-btn:hover { background: rgba(255,255,255,0.08); }
    .icon-success{ color:#22c55e; }
    .icon-primary{ color:#60a5fa; }
    .icon-danger{ color:#ef4444; }
    .icon-warning{ color:#f59e0b; }

    /* Badges */
    .badge-status { font-weight: 600; border-radius: 999px; padding: 6px 10px; font-size: 12px; }
    .bg-booked { background-color: #16a34a; } /* green */
    .bg-finished { background-color: #2563eb; } /* blue */
    .bg-cancelled { background-color: #dc2626; } /* red */
    .bg-rescheduled { background-color: #f59e0b; } /* yellow */
    .bg-pending { background-color: #6b7280; } /* gray */
    .bg-confirmed { background-color: #16a34a; }
    .bg-ongoing { background-color: #0ea5e9; }

    /* Empty state */
    .empty-state { border: 1px dashed #e5e7eb; border-radius: 12px; padding: 24px; text-align: center; color: #6b7280; background: #ffffff; }
    .empty-state i { font-size: 28px; margin-bottom: 8px; display:block; color:#2563eb; }

    /* Reschedule Form */
    .reschedule-form { display: flex; gap: 4px; align-items: center; justify-content: center; margin-top: 0; flex-wrap: wrap; }
    .reschedule-form input { background-color: #0b1220; border: 1px solid rgba(255,255,255,0.15); color: #e5e7eb; border-radius: 6px; padding: 0.15rem 0.4rem; font-size: 0.8rem; height: 26px; }
    .reschedule-form .btn-reschedule { background-color: #6c757d; color: white; font-weight: 600; border: none; border-radius: 6px; height: 26px; padding: 0.15rem 0.45rem; }
    .reschedule-form .btn-reschedule:hover { background-color: #5a6268; }

    /* Appointments table: tight, aligned, no gaps */
    .table.appointments-table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; }
    .table.appointments-table thead th,
    .table.appointments-table tbody td { padding: 10px 12px; vertical-align: middle; }
    /* Default left alignment for readability */
    .table.appointments-table thead th { white-space: nowrap; text-align: left; }
    .table.appointments-table tbody td { text-align: left; }
    /* Center Date(3), Time(5), Status(6), Actions(8) */
    .table.appointments-table thead th:nth-child(3),
    .table.appointments-table tbody td:nth-child(3),
    .table.appointments-table thead th:nth-child(5),
    .table.appointments-table tbody td:nth-child(5),
    .table.appointments-table thead th:nth-child(6),
    .table.appointments-table tbody td:nth-child(6),
    .table.appointments-table thead th:nth-child(8),
    .table.appointments-table tbody td:nth-child(8) { text-align: center; }
    /* Allow wrapping in Actions column to prevent cramped layout */
    .table.appointments-table tbody td:last-child { white-space: normal; }
    /* Prevent awkward mid-word breaks; wrap only at spaces */
    .table.appointments-table tbody td { white-space: normal; word-break: normal; overflow-wrap: normal; }
    /* Keep key columns readable: single line with ellipsis */
    .table.appointments-table th.name-cell,
    .table.appointments-table td.name-cell,
    .table.appointments-table th.proc-cell,
    .table.appointments-table td.proc-cell,
    .table.appointments-table th.doctor-cell,
    .table.appointments-table td.doctor-cell {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    /* Status pill */
    .table.appointments-table .badge-status { display: inline-block; margin: 0 auto; }
    /* Actions row compact layout */
    .action-buttons-container { display: flex; flex-direction: column; gap: 4px; align-items: center; justify-content: center; position: relative; }
    .action-buttons { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; justify-content: center; }
    .reschedule-form { display: flex; gap: 4px; align-items: center; margin-top: 0; }
    .reschedule-form input { height: 26px; padding: 0.15rem 0.4rem; }
    .reschedule-form .btn-reschedule { height: 26px; padding: 0.15rem 0.45rem; }
    .icon-btn { width: 26px; height: 26px; }
    .table.appointments-table .form-control,
    .table.appointments-table .form-select { height: 26px; padding: 0.15rem 0.4rem; font-size: 0.85rem; }

    /* Popover for reschedule (Option A) */
    .reschedule-popover { 
      /* Shown as a floating layer; JS sets top/left via fixed positioning */
      position: fixed;
      background: #ffffff; 
      color: #0b1220;
      border: 1px solid rgba(0,0,0,0.12); 
      border-radius: 8px; 
      padding: 8px; 
      box-shadow: 0 8px 24px rgba(0,0,0,0.3);
      z-index: 1050; 
      display: none;
      max-width: 90vw;
    }
    /* Light theme inputs inside popover */
    .reschedule-popover .reschedule-form input {
      background: #ffffff;
      color: #212529;
      border: 1px solid #ced4da;
    }
    .reschedule-popover .btn-reschedule {
      background-color: #2563eb;
      color: #ffffff;
    }
    .reschedule-popover .btn-reschedule:hover { background-color: #1e40af; }
    .reschedule-popover.show { display: block; }
    @media (max-width: 768px) {
      .table.appointments-table thead th,
      .table.appointments-table tbody td { padding: 8px 10px; }
    }
    /* Small screens: improve tap targets and spacing */
    @media (max-width: 576px) {
      .content { padding: 12px; }
      .btn, .btn-sm { padding: 0.5rem 0.75rem; font-size: 14px; }
      .form-control, .form-select, .form-control-sm, .form-select-sm { font-size: 14px; height: auto; padding: 0.5rem 0.75rem; }
      table.table { font-size: 14px; }
      .d-flex.gap-2 { gap: 6px !important; }
    }
    /* Mobile cards for admin appointments */
    .admin-app-table { display: block; }
    .admin-app-cards { display: none; }
    @media (max-width: 640px) {
      .admin-app-table { display: none; }
      .admin-app-cards { display: grid; gap: 12px; }
      .admin-app-card { border: 1px solid #e5e7eb; border-radius: 12px; background: #ffffff; padding: 12px; }
      .admin-app-card .row1 { display:flex; align-items:center; justify-content:space-between; gap:8px; }
      .admin-app-card .meta { color:#6b7280; font-size: 12px; }
      .admin-app-card .actions { display:flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    }
    /* Admin notifications bell */
    #adminNotifBell { position: fixed; bottom: 18px; right: 18px; z-index: 9999; border:1px solid #e5e7eb; background:#ffffff; color:#111827; width:40px; height:40px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); cursor: pointer; pointer-events: auto; }
    #adminNotifBell i { font-size:18px; }
    #adminNotifBell .badge { position:absolute; top:-6px; right:-6px; background:#ef4444; color:#fff; border-radius:999px; min-width:18px; height:18px; display:none; align-items:center; justify-content:center; font-size:11px; padding:0 5px; font-weight:700; box-shadow:0 0 0 2px #fff; }
    #adminNotifBell.has { border-color:#fecaca; box-shadow:0 4px 14px rgba(239,68,68,0.18); }
    /* Admin notifications popover */
    #adminNotifPanel { position: fixed; bottom: 66px; right: 18px; z-index: 9999; width: 320px; max-height: 60vh; overflow: auto; display: none; background:#ffffff; color:#111827; border:1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 12px 28px rgba(0,0,0,0.18); }
    #adminNotifPanel .panel-hdr { display:flex; align-items:center; justify-content: space-between; gap:8px; padding:10px 12px; border-bottom:1px solid #e5e7eb; font-weight:600; }
    #adminNotifPanel .panel-body { padding: 8px 0; }
    #adminNotifPanel .notif-item { padding: 10px 12px; border-bottom:1px solid #f1f5f9; font-size: 0.92rem; }
    #adminNotifPanel .notif-item small { color:#6b7280; display:block; margin-top:4px; }
    #adminNotifPanel .empty { padding: 16px; color:#6b7280; text-align:center; }
    /* Walk-in Modal: modern panel styles */
    #walkinModal { display:none; position:fixed; inset:0; z-index:1050; backdrop-filter: blur(2px); }
    #walkinModal .walkin-overlay { position:absolute; inset:0; background:rgba(15,23,42,0.55); }
    #walkinModal .walkin-panel {
      position: relative;
      max-width: 760px;
      width: calc(100% - 24px);
      margin: 12px auto;
      border-radius: 12px;
      background: #ffffff;
      color: #0b1220;
      border: 1px solid #e5e7eb;
      box-shadow: 0 20px 40px rgba(2,6,23,0.18);
      max-height: 90vh;
      overflow: hidden;
    }
    #walkinModal .walkin-header {
      display:flex; align-items:center; justify-content:space-between;
      padding: 14px 16px; border-bottom: 1px solid #e5e7eb;
      background: linear-gradient(180deg,#f8fafc,#ffffff);
    }
    #walkinModal .walkin-header h5 { margin:0; font-weight:700; color:#0f172a; }
    #walkinModal .walkin-body { padding: 12px; overflow-y: auto; max-height: calc(90vh - 56px - 56px); }
    #walkinModal .walkin-actions { position: sticky; bottom: 0; padding: 12px 16px; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:8px; background:#f8fafc; }
    #walkinModal .walkin-actions .btn { min-width: 160px; }
    @media (max-width: 576px) { #walkinModal .walkin-actions { padding: 12px; } #walkinModal .walkin-actions .btn { flex: 1 1 auto; min-width: 0; } }
    @media (max-width: 576px) { #walkinModal .walkin-panel { margin: 0; border-radius: 0; width: 100%; height: 100vh; max-width: none; } }
    /* Time slot & treatment pills (match appointment UI) */
    .time-slot-btn { transition: all 0.2s ease; display:flex; flex-direction:column; align-items:center; justify-content:center; height: 64px; border: 1px solid #e5e7eb; background:#ffffff; border-radius: 10px; }
    .time-slot-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.08); border-color:#93c5fd; background:#eff6ff; }
    .time-slot-btn.selected { background:#3b82f6 !important; color:#fff !important; border-color:#3b82f6 !important; box-shadow: 0 2px 4px rgba(59,130,246,0.25); }
    .time-slot-time { font-weight:700; font-size:0.95rem; line-height:1.1rem; }
    .time-slot-meridiem { font-size:0.75rem; opacity:0.9; }
    .treatment-btn { transition: all 0.2s ease; border:1px solid #e5e7eb; background:#ffffff; border-radius:12px; padding:12px 10px; font-size:0.93rem; color:#111827; text-align:center; min-height: 72px; display:flex; flex-direction:column; justify-content:center; align-items:center; }
    .treatment-btn:hover { background:#f8fafc; border-color:#cbd5e1; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.06); }
    .treatment-btn.selected { background:#3b82f6; color:#ffffff; border-color:#3b82f6; box-shadow: 0 2px 4px rgba(59,130,246,0.3); }
    .treatment-btn.disabled-user { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
    /* Time grid container */
    #walkinTimeSlots { display:grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap:8px; max-height: 260px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:12px; padding:10px; background:#f8fafc; }
    #walkinTreatmentsPills { display:grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap:10px; max-height: 380px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:12px; padding:10px; background:#f8fafc; }
    @media (max-width: 992px) { #walkinTimeSlots { grid-template-columns: repeat(4, minmax(0,1fr)); } }
    @media (max-width: 992px) { #walkinTreatmentsPills { grid-template-columns: repeat(4, minmax(0,1fr)); } }
    @media (max-width: 576px) { #walkinTimeSlots { grid-template-columns: repeat(3, minmax(0,1fr)); } }
    @media (max-width: 576px) { #walkinTreatmentsPills { grid-template-columns: repeat(2, minmax(0,1fr)); } }
  </style>
  <script>
    function __roundToFiveMinutes(dateObj) {
      const d = new Date(dateObj.getTime());
      d.setSeconds(0, 0);
      const ms = 5 * 60 * 1000;
      return new Date(Math.ceil(d.getTime() / ms) * ms);
    }
    function __applyTimeMinForDate() {
      const dateInput = document.querySelector('#walkinModal input[name="walkin_date"]');
      const timeInput = document.querySelector('#walkinModal #walkinTimeHidden') || document.querySelector('#walkinModal input[name="walkin_time"]');
      if (!dateInput || !timeInput) return;
      const todayStr = new Date().toISOString().slice(0,10);
      const selDate = dateInput.value || todayStr;
      if (selDate === todayStr) {
        const nowRounded = __roundToFiveMinutes(new Date());
        const hh = String(nowRounded.getHours()).padStart(2,'0');
        const mm = String(nowRounded.getMinutes()).padStart(2,'0');
        const minVal = `${hh}:${mm}`;
        if (timeInput.tagName === 'INPUT' && timeInput.type === 'time') {
          timeInput.min = minVal < '07:00' ? '07:00' : (minVal > '19:00' ? '19:00' : minVal);
        } else {
          // For hidden field, no attribute to set; grid rendering already filters past times.
        }
      } else {
        if (timeInput.tagName === 'INPUT' && timeInput.type === 'time') {
          timeInput.min = '07:00';
        }
      }
    }
    function openWalkinModal() {
      const m = document.getElementById('walkinModal');
      if (m) {
        m.style.display = 'block';
        setTimeout(() => {
          __applyTimeMinForDate();
          walkinFetchAndRenderSlots();
          try {
            const names = Array.from(document.querySelectorAll('#walkinTreatmentsPills .treatment-btn'))
              .map(b => (b.getAttribute('data-name')||'').trim())
              .filter(n => n && n !== 'All Treatments')
              .join('|');
            if (names) {
              fetch('appointment_api.php?treatments_inventory_status=1&names=' + encodeURIComponent(names))
                .then(r=>r.json())
                .then(info=>{ window.__walkinInventoryStatus = info || null; if (typeof applyCombinedInventoryGatingWalkin === 'function') applyCombinedInventoryGatingWalkin(); })
                .catch(()=>{});
            }
          } catch(e) {}
        }, 0);
      }
    }
    function closeWalkinModal() { const m = document.getElementById('walkinModal'); if (m) m.style.display = 'none'; }
    function hideCompleteButton(form) {
      // Hide the Complete button immediately after clicking
      const completeButton = form.querySelector('button[type="submit"]');
      if (completeButton) {
        completeButton.style.display = 'none';
        completeButton.textContent = 'Processing...';
        completeButton.disabled = true;
      }
      
      // Find the table row and add a fade-out effect
      const tableRow = form.closest('tr');
      if (tableRow) {
        tableRow.style.transition = 'opacity 0.5s ease-out';
        tableRow.style.opacity = '0.5';
        
        // After a short delay, hide the row completely
        setTimeout(() => {
          tableRow.style.display = 'none';
        }, 500);
      }
    }

    // Disable submit to prevent double-clicks (used for Confirm/Cancel/etc.)
    function disableSubmitOnce(form){
      const btn = form.querySelector('button[type="submit"]');
      if (btn){
        btn.disabled = true;
        btn.textContent = 'Processing...';
      }
      return true; // allow submit
    }

    // Walk-in: slot grid helpers
    function __hmToMinutes(hm){ try { const [h,m]=hm.split(':').map(n=>parseInt(n,10)); return h*60+(m||0); } catch(e){ return NaN; } }
    function __clearSelfDurationBlock(container){
      try {
        if (!container) return;
        Array.from(container.querySelectorAll('.time-slot-btn.conflict')).forEach(b=>{
          b.disabled = false;
          b.classList.remove('conflict','cursor-not-allowed');
          b.removeAttribute('aria-disabled');
          b.tabIndex = 0;
        });
      } catch(e){}
    }
    function __applySelfDurationBlock(container, selectedHM, durationMin){
      try {
        if (!container || !selectedHM) return;
        // Clear previous conflict-only blocks so user can change time
        __clearSelfDurationBlock(container);
        const selStart = __hmToMinutes(selectedHM);
        const selEnd = selStart + (parseInt(durationMin,10)||0);
        Array.from(container.querySelectorAll('.time-slot-btn')).forEach(b=>{
          const hm = b.getAttribute('data-time')||'';
          const t = __hmToMinutes(hm);
          if (!Number.isFinite(t)) return;
          // Match patient booking: mark anything after start and up to and including end boundary
          if (t > selStart && t <= selEnd){
            b.disabled = true; b.classList.add('conflict','cursor-not-allowed'); b.setAttribute('aria-disabled','true'); b.tabIndex = -1;
            b.classList.remove('selected');
          }
        });
      } catch(e){}
    }
    function walkinSelectTimeSlot(hm) {
      const modal = document.getElementById('walkinModal'); if (!modal) return;
      const hidden = modal.querySelector('#walkinTimeHidden');
      const container = modal.querySelector('#walkinTimeSlots');
      const btn = container ? container.querySelector(`[data-time="${hm}"]`) : null;
      // Do not allow selecting past/disabled slots
      if (btn && (btn.disabled || btn.classList.contains('past'))) { return; }
      if (hidden) hidden.value = hm;
      if (container) {
        container.querySelectorAll('.time-slot-btn').forEach(b=>b.classList.remove('selected'));
        if (btn) btn.classList.add('selected');
        // Self-block all starts within the selected duration window
        const dur = (window.__walkinDuration||window.APPT_DEFAULT_DURATION||30);
        __applySelfDurationBlock(container, hm, dur);
      }
    }
    function walkinRenderLocalFiveMinuteSlots() {
      const modal = document.getElementById('walkinModal'); if (!modal) return;
      const date = (modal.querySelector('input[name="walkin_date"]')||{}).value;
      const container = modal.querySelector('#walkinTimeSlots');
      const hidden = modal.querySelector('#walkinTimeHidden');
      const dentistSel = modal.querySelector('select[name="walkin_dentist_id"]');
      const dentistId = dentistSel ? dentistSel.value : '';
      __computeSelectedWalkinDuration();
      if (!container) return;
      container.innerHTML = '';
      if (!date) { container.innerHTML = '<div class="col-span-3 text-muted small">Select date and dentist.</div>'; return; }
      const todayStr = new Date().toISOString().split('T')[0];
      const round5 = n=> Math.floor(n/5)*5;
      const now = new Date(); const nowHM = String(now.getHours()).padStart(2,'0')+':'+String(round5(now.getMinutes())).padStart(2,'0');
      const frag = document.createDocumentFragment();
      for (let h=7; h<=18; h++) {
        for (let m=0; m<60; m+=5) {
          if (h===18 && m>55) break;
          const hm = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
          const d = new Date(); d.setHours(h); d.setMinutes(m||0);
          const pretty = d.toLocaleTimeString([], {hour:'numeric', minute:'2-digit', hour12:true}).split(' ');
          const timePart = pretty.slice(0, pretty.length-1).join(' '); const mer = (pretty[pretty.length-1]||'').toUpperCase();
          const btn = document.createElement('button');
          btn.type = 'button'; btn.className = 'time-slot-btn px-3 py-2 text-sm';
          btn.setAttribute('data-time', hm);
          btn.innerHTML = `<span class="time-slot-time">${timePart}</span><span class="time-slot-meridiem">${mer}</span>`;
          btn.onclick = ()=> walkinSelectTimeSlot(hm);
          if (date===todayStr && hm <= nowHM) { btn.disabled = true; btn.classList.add('past','cursor-not-allowed'); btn.setAttribute('aria-disabled','true'); btn.tabIndex = -1; }
          // Re-apply previous selection if it matches this slot
          if (hidden && hidden.value && hidden.value === hm) { btn.classList.add('selected'); }
          frag.appendChild(btn);
        }
      }
      container.appendChild(frag);
      // If no dentist yet, disable all until a dentist is selected (visual like appointment)
      if (!dentistId) {
        const noteId = 'walkinPickDentistNote';
        if (!document.getElementById(noteId)) {
          const note = document.createElement('div');
          note.id = noteId; note.className = 'col-span-3 text-muted small';
          note.textContent = 'Select a dentist to see available times for the selected treatment duration.';
          container.prepend(note);
        }
        Array.from(container.querySelectorAll('.time-slot-btn')).forEach(b=>{
          b.disabled = true; b.classList.add('conflict','cursor-not-allowed'); b.setAttribute('aria-disabled','true'); b.tabIndex = -1;
          b.classList.remove('selected');
        });
      }
    }
    function walkinFetchAndRenderSlots() {
      const modal = document.getElementById('walkinModal'); if (!modal) return;
      const date = (modal.querySelector('input[name="walkin_date"]')||{}).value;
      const dentistSel = modal.querySelector('select[name="walkin_dentist_id"]');
      const dentistId = dentistSel ? dentistSel.value : '';
      const container = modal.querySelector('#walkinTimeSlots');
      const hidden = modal.querySelector('#walkinTimeHidden');
      const durationMin = __computeSelectedWalkinDuration();
      if (!container) return;
      if (!date) { container.innerHTML = '<div class="col-span-3 text-muted small">Select a date.</div>'; return; }
      if (!dentistId) { walkinRenderLocalFiveMinuteSlots(); return; }
      // show full 5-min grid first, then we'll disable conflicts after fetching
      walkinRenderLocalFiveMinuteSlots();
      const params = new URLSearchParams(); params.set('slots','1'); params.set('date', date); params.set('dentist_id', dentistId); params.set('duration', String(durationMin||window.APPT_DEFAULT_DURATION||30));
      fetch('appointment_api.php?' + params.toString())
        .then(r=>r.json())
        .then(data=>{
          const slots = (data && data.ok && Array.isArray(data.slots)) ? data.slots : [];
          // If nothing available, keep grid and disable all buttons; add inline notice
          if (slots.length === 0) {
            try {
              const dur = (window.__walkinDuration||window.APPT_DEFAULT_DURATION||30);
              const noteId = 'walkinNoSlotsNote';
              const existing = document.getElementById(noteId);
              const msg = (data && data.leave)
                ? 'Dentist is on leave for this date.'
                : 'No available time slots for the selected duration ('+dur+' min). Try another time, date, or dentist.';
              if (!existing) {
                const note = document.createElement('div');
                note.id = noteId;
                note.className = 'col-span-3 text-muted small';
                note.textContent = msg;
                container.prepend(note);
              } else { existing.textContent = msg; }
              Array.from(container.querySelectorAll('.time-slot-btn')).forEach(b=>{
                b.disabled = true; b.classList.add('conflict','cursor-not-allowed'); b.setAttribute('aria-disabled','true'); b.tabIndex = -1;
                b.classList.remove('selected');
              });
            } catch(e){}
            return;
          }
          // Mark conflict (non-available) start times as disabled red
          try {
            const btns = Array.from(container.querySelectorAll('.time-slot-btn'));
            const allowed = new Set(slots);
            const prev = hidden ? (hidden.value || '') : '';
            btns.forEach(b=>{
              const hm = b.getAttribute('data-time')||'';
              if (!hm) return;
              if (!allowed.has(hm)) {
                b.disabled = true; b.classList.add('conflict','cursor-not-allowed'); b.setAttribute('aria-disabled','true'); b.tabIndex = -1;
                if (b.classList.contains('selected')) { b.classList.remove('selected'); if (hidden && hidden.value===hm) hidden.value=''; }
              }
              // Restore selection if still allowed
              if (prev && prev === hm && allowed.has(hm) && !b.disabled) { b.classList.add('selected'); }
            });
          } catch(e){}
        })
        .catch(()=>{});
    }

    // Walk-in: treatment pills selection
    function walkinSelectTreatment(btnElem, name) {
      const modal = document.getElementById('walkinModal'); if (!modal) return;
      const pills = Array.from(modal.querySelectorAll('#walkinTreatmentsPills .treatment-btn'));
      const allBtn = pills.find(b => (b.getAttribute('data-name')||'') === 'All Treatments');
      if (btnElem && btnElem.hasAttribute('data-disabled')) return;
      const selectEl = modal.querySelector('#walkinTreatmentSelect');
      if (!selectEl) return;

      if (name === 'All Treatments') {
        const willSelectAll = !btnElem.classList.contains('selected');
        pills.forEach(b => b.classList.remove('selected'));
        if (willSelectAll) {
          // Select ALL treatments regardless of inventory state (ignore gating)
          pills.forEach(b => { if ((b.getAttribute('data-name')||'') !== 'All Treatments') b.classList.add('selected'); });
        }
        // Reflect in the hidden multiple select
        for (let i=0;i<selectEl.options.length;i++) {
          const opt = selectEl.options[i];
          if (opt.value) opt.selected = willSelectAll;
        }
        // Do not early-return; continue to recompute duration, update summary, and refresh slots
      } else {
        btnElem.classList.toggle('selected');
        if (allBtn) allBtn.classList.remove('selected');
      }
      const val = name;
      for (let i=0;i<selectEl.options.length;i++) {
        const opt = selectEl.options[i];
        if (opt.value === val) { opt.selected = btnElem.classList.contains('selected'); break; }
      }
      // After selection changes, apply combined inventory gating
      if (typeof applyCombinedInventoryGatingWalkin === 'function') { applyCombinedInventoryGatingWalkin(); }
      // Duration may have changed; re-render available time slots
      try { __computeSelectedWalkinDuration(); updateWalkinSelectedTreatmentsSummary(); walkinFetchAndRenderSlots(); } catch(e){}
    }

    function updateWalkinSelectedTreatmentsSummary(){
      try {
        const modal = document.getElementById('walkinModal'); if (!modal) return;
        const box = modal.querySelector('#walkinSelectedTreatmentsSummary'); if (!box) return;
        const selected = Array.from(modal.querySelectorAll('#walkinTreatmentsPills .treatment-btn.selected'))
          .filter(b => (b.getAttribute('data-name')||'') !== 'All Treatments');
        if (selected.length === 0) { box.textContent = ''; return; }
        const items = selected.map(btn => {
          const n = btn.getAttribute('data-name') || '';
          const d = parseInt(btn.getAttribute('data-duration')||'0',10) || 0;
          return n + ' (' + d + ' min)';
        });
        const total = selected.reduce((s,btn)=> s + (parseInt(btn.getAttribute('data-duration')||'0',10)||0), 0);
        box.textContent = items.join(', ') + ' — Total: ' + total + ' min';
      } catch(e) { /* ignore */ }
    }

    // Combined inventory gating for Walk-in modal
    function applyCombinedInventoryGatingWalkin() {
      try {
        const modal = document.getElementById('walkinModal'); if (!modal) return;
        const pills = Array.from(modal.querySelectorAll('#walkinTreatmentsPills .treatment-btn'));
        const status = (window.__walkinInventoryStatus && window.__walkinInventoryStatus.items) ? window.__walkinInventoryStatus.items : {};
        // Stock per item (max across treatments)
        const stockByItem = {};
        Object.keys(status||{}).forEach(nm=>{
          const rows = Array.isArray(status[nm])?status[nm]:[];
          rows.forEach(r=>{
            if (r && (r.matched === true || r.current_stock !== null)) {
              const key = String(r.item_name||'').trim(); if (!key) return;
              const q = parseInt(r.current_stock||0,10); if (!Number.isFinite(q)) return;
              stockByItem[key] = Math.max(stockByItem[key]||0, q);
            }
          });
        });
        // Requirements per treatment
        const reqByTreatment = {};
        Object.keys(status||{}).forEach(nm=>{
          const rows = Array.isArray(status[nm])?status[nm]:[];
          const m = {};
          rows.forEach(r=>{
            const matched = (r && (r.matched === true || r.current_stock !== null));
            if (!matched) return;
            const key = String(r.item_name||'').trim(); if (!key) return;
            const need = parseInt(r.quantity_needed||0,10); if (!Number.isFinite(need) || need<=0) return;
            m[key] = (m[key]||0) + need;
          });
          reqByTreatment[nm] = m;
        });
        // Selected set (exclude All Treatments)
        const selected = new Set(
          pills.filter(b=>b.classList.contains('selected'))
               .map(b=> (b.getAttribute('data-name')||'').trim())
               .filter(n=> n && n!=='All Treatments')
        );
        function feasibleWith(cand){
          const combined = {};
          selected.forEach(nm=>{ const m=reqByTreatment[nm]||{}; Object.keys(m).forEach(it=>{ combined[it]=(combined[it]||0)+m[it]; }); });
          if (!selected.has(cand)) { const m=reqByTreatment[cand]||{}; Object.keys(m).forEach(it=>{ combined[it]=(combined[it]||0)+m[it]; }); }
          for (const it in combined){ if ((combined[it]||0) > (stockByItem[it]||0)) return false; }
          return true;
        }
        pills.forEach(btn=>{
          const nm = (btn.getAttribute('data-name')||'').trim(); if (!nm || nm==='All Treatments') return;
          const isSelected = btn.classList.contains('selected');
          if (!isSelected) {
            const ok = feasibleWith(nm);
            if (!ok) {
              btn.classList.add('disabled-user');
              btn.setAttribute('data-disabled','1');
              if (!btn.querySelector('.no-stock-badge')) {
                const badge = document.createElement('span');
                badge.className = 'no-stock-badge';
                badge.textContent = 'Unavailable';
                badge.style.display = 'inline-block';
                badge.style.fontSize = '10px';
                badge.style.marginTop = '4px';
                badge.style.padding = '2px 6px';
                badge.style.borderRadius = '9999px';
                badge.style.background = '#fee2e2';
                badge.style.color = '#991b1b';
                badge.style.border = '1px solid #fecaca';
                btn.appendChild(badge);
              }
            } else {
              btn.classList.remove('disabled-user');
              btn.removeAttribute('data-disabled');
              const b = btn.querySelector('.no-stock-badge'); if (b) b.remove();
            }
          }
        });
      } catch(e) { /* ignore */ }
    }

    // Toggle the reschedule popover anchored to the action buttons container
    function toggleReschedulePopover(btn) {
      const targetSel = btn.getAttribute('data-target');
      const pop = document.querySelector(targetSel);
      if (!pop) return;

      // Close others
      document.querySelectorAll('.reschedule-popover.show').forEach(el => { if (el !== pop) el.classList.remove('show'); });

      const isOpen = pop.classList.contains('show');
      if (isOpen) {
        pop.classList.remove('show');
        btn.setAttribute('aria-expanded', 'false');
        return;
      }

      // Temporarily show to measure, then position near the button (fixed positioning)
      pop.style.visibility = 'hidden';
      pop.classList.add('show');
      const rect = btn.getBoundingClientRect();
      const pw = pop.offsetWidth;
      const ph = pop.offsetHeight;
      const margin = 8;
      let top = rect.bottom + margin;
      let left = rect.right - pw; // right-align to button
      // Keep inside viewport
      if (left < margin) left = margin;
      if (left + pw > window.innerWidth - margin) left = window.innerWidth - pw - margin;
      if (top + ph > window.innerHeight - margin) top = Math.max(margin, rect.top - ph - margin); // flip above if needed
      pop.style.top = `${top}px`;
      pop.style.left = `${left}px`;
      pop.style.visibility = 'visible';
      btn.setAttribute('aria-expanded', 'true');

      // Focus first input for quick entry
      const first = pop.querySelector('input,button,select,textarea');
      if (first) setTimeout(() => first.focus({ preventScroll: true }), 0);
    }

    // Close on outside click
    document.addEventListener('click', (e) => {
      const pop = e.target.closest('.reschedule-popover');
      const trigger = e.target.closest('[onclick^="toggleReschedulePopover"]');
      if (pop || trigger) return;
      document.querySelectorAll('.reschedule-popover.show').forEach(el => el.classList.remove('show'));
      document.querySelectorAll('[onclick^="toggleReschedulePopover"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.reschedule-popover.show').forEach(el => el.classList.remove('show'));
        document.querySelectorAll('[onclick^="toggleReschedulePopover"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
      }
    });
  </script>
  <script>
    // Expose default appointment duration (from settings) to JS
    <?php
      $defaultApptDuration = 60;
      try {
        if (file_exists(__DIR__ . '/settings_helper.php') && isset($conn)) {
          include_once __DIR__ . '/settings_helper.php';
          $as = function_exists('getAppointmentSettings') ? getAppointmentSettings($conn) : [];
          if (!empty($as) && isset($as['appointment_duration']) && (int)$as['appointment_duration'] > 0) {
            $defaultApptDuration = (int)$as['appointment_duration'];
          }
        }
      } catch (Throwable $e) { /* ignore */ }
    ?>
    window.APPT_DEFAULT_DURATION = <?= (int)$defaultApptDuration ?>;
    window.__walkinDuration = window.APPT_DEFAULT_DURATION;

    function __computeSelectedWalkinDuration(){
      try {
        const modal = document.getElementById('walkinModal'); if (!modal) return (window.APPT_DEFAULT_DURATION||30);
        const pills = Array.from(modal.querySelectorAll('#walkinTreatmentsPills .treatment-btn.selected'));
        let total = 0;
        pills.forEach(b=>{
          const durAttr = b.getAttribute('data-duration');
          let d = durAttr ? parseInt(durAttr, 10) : NaN;
          if (!Number.isFinite(d) || d <= 0) {
            const txt = (b.innerText||'').toLowerCase();
            const m = txt.match(/(\d+)\s*min/);
            d = m ? parseInt(m[1],10) : 0;
          }
          total += d;
        });
        if (!Number.isFinite(total) || total <= 0) total = (window.APPT_DEFAULT_DURATION||30);
        window.__walkinDuration = total;
        return total;
      } catch(e){ return (window.APPT_DEFAULT_DURATION||30); }
    }
    function __formatDateInput(d) {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${y}-${m}-${day}`;
    }
    // offsetDays: 0 => Today, 1 => Tomorrow
    function __setApptWeek(offsetDays) {
      const now = new Date();
      const target = new Date(now.getFullYear(), now.getMonth(), now.getDate() + (offsetDays || 0));
      const fromInput = document.querySelector('input[name="date_from"]');
      const toInput = document.querySelector('input[name="date_to"]');
      if (fromInput && toInput) {
        const v = __formatDateInput(target);
        fromInput.value = v;
        toInput.value = v;
        const form = fromInput.closest('form');
        if (form) form.submit();
      }
    }
  </script>
</head>
<body>
<div class="layout d-flex">
  <?php if (file_exists($baseDir . '/admin_sidebar.php')) { include 'admin_sidebar.php'; } else { ?>
  <div class="sidebar">
    <h3>Miles Dental</h3>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="admin_appointments.php">Appointments</a>
    <a href="admin_messages.php">Messages</a>
    <a href="admin_inventory.php">Inventory</a>
    <a href="preferences.php">Settings</a>
    <a href="logout.php">Logout</a>
  </div>
  <?php } ?>

  <div class="content flex-grow-1">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0">Appointments</h2>
      <div class="d-flex gap-2">
        <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
        <button type="button" class="btn btn-success btn-sm" onclick="openWalkinModal()"><i class="fa fa-user-plus me-1"></i>Add Walk-in</button>
        <?php $exportUrl = 'admin_appointments.php?'.http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>
        <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-outline-success btn-sm"><i class="fa fa-file-export me-1"></i>Export</a>
      </div>
      <?php if (!isset($appointments) || !is_array($appointments)) { $appointments = []; } ?>
    <div class="admin-app-cards">
      <?php if (!empty($appointments)): foreach ($appointments as $a): ?>
        <?php 
          $rawName = trim((string)($a['Patient_Name'] ?? ''));
          $rawEmail = (string)($a['Email'] ?? '');
          $displayName = $rawName !== '' ? $rawName : $rawEmail;
          $rawProc = (string)($a['Procedure'] ?? '');
          $procDisplay = preg_replace('/\s*\|\s*(Contact|Address)\s*:[^|]*/i', '', $rawProc);
          $procDisplay = trim(preg_replace('/\s*\|\s*$/', '', $procDisplay));
          $st = (string)($a['Status'] ?? '');
          $map = [ 'Booked'=>'bg-booked','Confirmed'=>'bg-confirmed','Ongoing'=>'bg-ongoing','Finished'=>'bg-finished','Cancelled'=>'bg-cancelled','Rescheduled'=>'bg-rescheduled','Pending'=>'bg-pending'];
          $cls = $map[$st] ?? 'bg-pending';
          $aid = (int)$a['Appointment_Id'];
          $notes = trim((string)($a['Admin_Notes'] ?? ''));
        ?>
        <div class="admin-app-card">
          <div class="row1">
            <div class="fw-semibold text-truncate" title="<?= htmlspecialchars($displayName) ?>">
              <?= htmlspecialchars($displayName) ?>
            </div>
            <div id="walkinSelectedTreatmentsSummary" class="text-muted small mt-2"></div>
            <span class="badge badge-status <?= $cls ?>"><?= htmlspecialchars($st) ?></span>
          </div>
          <div class="meta mt-1">Procedure: <?= htmlspecialchars($procDisplay) ?></div>
          <div class="meta">Date/Time: <?= htmlspecialchars($a['Date']) ?> • <?= htmlspecialchars($a['Time']) ?></div>
          <div class="meta">Doctor: <?= htmlspecialchars($a['Dentist_Name']) ?></div>
          <div class="actions">
            <?php if ($a['Status'] !== 'Finished'): ?>
              <form method="post" class="d-inline" onsubmit="hideCompleteButton(this)">
                <input type="hidden" name="complete_id" value="<?= $aid ?>" />
                <button class="btn btn-success btn-sm" type="submit">Done</button>
              </form>
              <form method="post" class="d-inline" onsubmit="return disableSubmitOnce(this)">
                <input type="hidden" name="confirm_id" value="<?= $aid ?>" />
                <button class="btn btn-primary btn-sm" type="submit">Confirm</button>
              </form>
              <form method="post" class="d-inline" onsubmit="return disableSubmitOnce(this)">
                <input type="hidden" name="cancel_id" value="<?= $aid ?>" />
                <button class="btn btn-danger btn-sm" type="submit">Cancel</button>
              </form>
              <button class="icon-btn" type="button" title="Reschedule" aria-haspopup="true" aria-expanded="false" data-target="#rs-<?= $aid ?>" onclick="toggleReschedulePopover(this)">
                <i class="fa-regular fa-clock icon-warning"></i>
              </button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#notesModal-<?= $aid ?>">
              <i class="fa-regular fa-note-sticky me-1"></i> <?= $notes !== '' ? 'Edit' : 'Add' ?> Notes
            </button>
          </div>
        </div>
      <?php endforeach; else: ?>
        <div class="empty-state">
          <i class="fa-regular fa-calendar-xmark"></i>
          <div>No appointments match your filters.</div>
        </div>
      <?php endif; ?>
    </div>
    </div>

    <?php
    // Tabs: All | each Dentist loaded from tbl_dentist (dynamic)
    $tabItems = ['all' => 'All'];
    $hasDentTbl = $conn->query("SHOW TABLES LIKE 'tbl_dentist'");
    if ($hasDentTbl && $hasDentTbl->num_rows > 0) {
      $res = $conn->query("SELECT Dentist_id, COALESCE(NULLIF(TRIM(Name), ''), CONCAT('Dentist #', Dentist_id)) AS Name FROM tbl_dentist ORDER BY Name ASC");
      if ($res) {
        while ($row = $res->fetch_assoc()) {
          $tabItems[(string)$row['Dentist_id']] = $row['Name'];
        }
      }
    }
    ?>
    <!-- Walk-in Modal -->
    <div id="walkinModal">
      <div class="walkin-overlay" onclick="closeWalkinModal()"></div>
      <div class="walkin-panel">
        <div class="walkin-header">
          <h5 class="mb-0">Add Walk-in</h5>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="closeWalkinModal()">Close</button>
        </div>
        <div class="walkin-body">
        <form method="post" class="row g-2" onsubmit="return validateWalkinForm(this)">
          <input type="hidden" name="add_walkin" value="1" />
          <div class="col-md-4">
            <label class="form-label">First Name</label>
            <input type="text" class="form-control" name="walkin_first_name" required />
          </div>
          <div class="col-md-4">
            <label class="form-label">Middle Name <span class="text-muted small">(optional)</span></label>
            <input type="text" class="form-control" name="walkin_middle_name" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Last Name</label>
            <input type="text" class="form-control" name="walkin_last_name" required />
          </div>
          <div class="col-md-8">
            <label class="form-label">Address</label>
            <input type="text" class="form-control" name="walkin_address" placeholder="Street, Barangay, City/Province" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Contact Number</label>
            <input type="tel" class="form-control" name="walkin_contact" placeholder="09XXXXXXXXX" required maxlength="11" pattern="09[0-9]{9}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Dentist</label>
            <select class="form-select" name="walkin_dentist_id" required onchange="walkinFetchAndRenderSlots()">
              <?php $walkinDentSel = isset($_POST['walkin_dentist_id']) ? (string)$_POST['walkin_dentist_id'] : ''; ?>
              <option value="" disabled <?= $walkinDentSel==='' ? 'selected' : '' ?>>Select dentist</option>
              <?php
                $dentQ = $conn->query("SHOW TABLES LIKE 'tbl_dentist'");
                if ($dentQ && $dentQ->num_rows > 0) {
                  $dl = $conn->query("SELECT Dentist_id, COALESCE(NULLIF(TRIM(Name), ''), CONCAT('Dentist #', Dentist_id)) AS Name FROM tbl_dentist ORDER BY Name ASC");
                  if ($dl) { while ($r = $dl->fetch_assoc()) { 
                    $val = (string)(int)$r['Dentist_id'];
                    $sel = ($walkinDentSel !== '' && $walkinDentSel === $val) ? ' selected' : '';
                    echo '<option value="'.$val.'"'.$sel.'>'.htmlspecialchars($r['Name']).'</option>'; 
                  } }
                }
              ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" name="walkin_date" value="<?= htmlspecialchars($selectedDate) ?>" min="<?= date('Y-m-d') ?>" required onchange="__applyTimeMinForDate(); walkinFetchAndRenderSlots()" />
          </div>
          <div class="col-md-12">
            <div class="d-flex align-items-center mb-2"><span class="badge rounded-pill bg-primary me-2">3</span><span class="fw-semibold text-muted">Select Treatment</span></div>
            <select id="walkinTreatmentSelect" class="form-select d-none" name="walkin_procedure[]" multiple required>
              <option value="">Select treatment</option>
              <?php
                // Try to load from config first
                $treatmentOptions = [];
                $loadedFromConfig = false;
                if (file_exists(__DIR__ . '/appointment_config.php')) {
                  include __DIR__ . '/appointment_config.php';
                  if (!empty($treatments) && is_array($treatments)) {
                    foreach ($treatments as $t) {
                      if (!empty($t['name'])) { $treatmentOptions[] = $t['name']; }
                    }
                    $loadedFromConfig = count($treatmentOptions) > 0;
                  }
                }
                if (!$loadedFromConfig && isset($conn)) {
                  $resProc = $conn->query("SELECT DISTINCT `Procedure` AS P FROM tbl_appointments WHERE `Procedure` IS NOT NULL AND `Procedure` != '' ORDER BY `Procedure` ASC");
                  if ($resProc) { while ($r = $resProc->fetch_assoc()) { $treatmentOptions[] = $r['P']; } }
                }
                // Keep all treatments, including 'Tooth Extraction'
                // Ensure unique and alphabetically sorted list (case-insensitive)
                $treatmentOptions = array_values(array_unique($treatmentOptions, SORT_STRING));
                usort($treatmentOptions, function($a, $b) { return strcasecmp($a, $b); });

                // Server-side availability to render disabled immediately
                $disabledByName = [];
                try {
                  require_once __DIR__ . '/inventory_deduction.php';
                  foreach ($treatmentOptions as $optName) {
                    $statusList = getTreatmentInventoryStatus($conn, $optName);
                    $clickable = true; $anyMatched = false;
                    if (!empty($statusList)) {
                      foreach ($statusList as $row) {
                        $matched = isset($row['matched']) ? (bool)$row['matched'] : ($row['current_stock'] !== null);
                        if (!$matched) { continue; }
                        $anyMatched = true;
                        $q = isset($row['current_stock']) ? (int)$row['current_stock'] : 0;
                        $need = isset($row['quantity_needed']) ? (int)$row['quantity_needed'] : 0;
                        $expired = !empty($row['expired']);
                        if ($expired || $q < $need) { $clickable = false; break; }
                      }
                    }
                    // Unmapped treatments remain clickable by default
                    if (!$anyMatched) { $clickable = true; }
                    if (!$clickable) { $disabledByName[$optName] = true; }
                  }
                } catch (\Throwable $e) { /* ignore */ }

                // Build duration maps from appointment_config.php ONLY (match patient booking exactly)
                $durationNumericByName = [];
                $durationLabelByName = [];
                try {
                  if (file_exists(__DIR__ . '/appointment_config.php')) {
                    include __DIR__ . '/appointment_config.php';
                    if (isset($treatments) && is_array($treatments)) {
                      foreach ($treatments as $tconf) {
                        $nm = trim((string)($tconf['name'] ?? ''));
                        if ($nm === '') continue;
                        $dur = isset($tconf['duration']) ? (int)$tconf['duration'] : 0;
                        if ($dur > 0) { $durationNumericByName[$nm] = $dur; $durationLabelByName[$nm] = $dur.' min'; }
                      }
                    }
                  }
                } catch (\Throwable $e) { /* ignore */ }

                foreach ($treatmentOptions as $opt): $safe = htmlspecialchars($opt, ENT_QUOTES); $isDisabled = !empty($disabledByName[$opt]); $durLabel = isset($durationLabelByName[$opt]) ? $durationLabelByName[$opt] : ($defaultApptDuration.' min'); $durNum = isset($durationNumericByName[$opt]) ? (int)$durationNumericByName[$opt] : (int)$defaultApptDuration; ?>
                  <option value="<?= $safe ?>" data-duration="<?= $durNum ?>" <?= $isDisabled ? ' disabled title="Unavailable based on inventory"' : '' ?>><?= $safe ?><?= $isDisabled?' (Unavailable)':'' ?></option>
                <?php endforeach; ?>
              ?>
            </select>
            <style>
              /* Red styling for out-of-stock treatments (disabled) */
              .treatment-btn.disabled-user,
              .treatment-btn[data-disabled="1"],
              .treatment-btn[aria-disabled="true"] {
                background: #fee2e2 !important; /* red-100 */
                border-color: #fecaca !important; /* red-200 */
                color: #991b1b !important; /* red-900 */
              }
              .treatment-btn.disabled-user .no-stock-badge {
                background: #fee2e2;
                color: #991b1b;
                border: 1px solid #fecaca;
              }
              /* Time grid and buttons (match appointment visual) */
              #walkinTimeSlots {
                display: grid;
                grid-template-columns: repeat(5, minmax(0,1fr));
                gap: 10px;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 12px;
                background: #f8fafc;
                max-height: 300px; overflow-y: auto;
              }
              .time-slot-btn {
                border: 1px solid #e5e7eb;
                background: #ffffff;
                color: #1f2937;
                border-radius: 12px;
                min-height: 48px;
                display: flex; flex-direction: column; align-items: center; justify-content: center;
              }
              .time-slot-btn.selected {
                background: #ef4444 !important; /* red-500 */
                color: #ffffff !important;
                border-color: #ef4444 !important;
                box-shadow: 0 2px 4px rgba(239,68,68,0.25);
              }
              /* Stronger specificity to override any earlier blue theme */
              .walkin-panel #walkinTreatmentsPills .treatment-btn.selected,
              .walkin-panel .treatment-btn.selected,
              #walkinTreatmentsPills .treatment-btn.selected {
                background: #ef4444 !important;
                border-color: #ef4444 !important;
                color: #ffffff !important;
                box-shadow: 0 2px 4px rgba(239,68,68,0.25) !important;
              }
              /* Red styling for past/disabled time slots */
              .time-slot-btn.past,
              .time-slot-btn[disabled],
              .time-slot-btn[aria-disabled="true"],
              .time-slot-btn[data-disabled="1"] {
                background: #fee2e2 !important;
                border-color: #fecaca !important;
                color: #991b1b !important;
              }
            </style>
            <div id="walkinTreatmentsPills" class="mt-2" style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:10px; max-height: 300px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:12px; padding:12px; background:#f8fafc">
              <?php $anyDisabled = false; foreach (($disabledByName ?? []) as $k=>$v) { if ($v) { $anyDisabled = true; break; } } ?>
              <button type="button" class="treatment-btn<?= $anyDisabled ? ' disabled-user' : '' ?>" data-name="All Treatments" data-duration="0" <?= $anyDisabled ? 'data-disabled="1" aria-disabled="true" style="opacity:0.6; cursor:not-allowed; pointer-events:none;"' : '' ?> <?= $anyDisabled ? '' : "onclick=\"walkinSelectTreatment(this, 'All Treatments')\"" ?>>
                <div class="font-medium">All Treatments</div>
                <div class="text-xs opacity-80">Multiple procedures<?= $anyDisabled ? ' — Unavailable' : '' ?></div>
              </button>
              <?php foreach ($treatmentOptions as $opt): $safe = htmlspecialchars($opt, ENT_QUOTES); $isDisabled = !empty($disabledByName[$opt]); $durLabel = isset($durationLabelByName[$opt]) ? $durationLabelByName[$opt] : ($defaultApptDuration.' min'); $durNum = isset($durationNumericByName[$opt]) ? (int)$durationNumericByName[$opt] : (int)$defaultApptDuration; ?>
                <button type="button" class="treatment-btn<?= $isDisabled ? ' disabled-user' : '' ?>" data-name="<?= $safe ?>" data-duration="<?= (int)$durNum ?>" <?= $isDisabled ? 'data-disabled="1" aria-disabled="true" style="opacity:0.6; cursor:not-allowed; pointer-events:none;"' : '' ?> <?= $isDisabled ? '' : "onclick=\"walkinSelectTreatment(this, '$safe')\"" ?>>
                  <div class="font-medium"><?= htmlspecialchars($opt) ?></div>
                  <div class="text-xs opacity-80"><?= htmlspecialchars($durLabel) ?></div>
                  <?php if ($isDisabled): ?><span class="no-stock-badge" style="display:inline-block; font-size:10px; margin-top:4px; padding:2px 6px; border-radius:9999px; background:#fee2e2; color:#991b1b; border:1px solid #fecaca;">Unavailable</span><?php endif; ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-md-12">
            <label class="form-label">Time</label>
            <div id="walkinTimeSlots" class="grid">
              <div class="col-span-3 text-muted small">Select date and dentist to view available times.</div>
            </div>
            <div class="text-muted small mt-1">Clinic hours: 7:00 AM - 7:00 PM (5-minute intervals)</div>
            <input type="hidden" name="walkin_time" id="walkinTimeHidden" value="" />
          </div>
        </div>
        <div class="walkin-actions">
          <button type="button" class="btn btn-outline-secondary" onclick="closeWalkinModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Book Appointment</button>
        </div>
        </form>
      </div>
    </div>
    <!-- Filter Bar -->
    <form method="get" class="filter-form row g-2 align-items-end mb-3">
      <div class="col flex-grow-1 d-flex flex-column justify-content-end">
        <label class="form-label small text-muted">Search patient</label>
        <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Name or email" list="patient-suggest">
        <datalist id="patient-suggest"></datalist>
      </div>
      <div class="col-auto d-flex flex-column justify-content-end">
        <label class="form-label small text-muted">Doctor</label>
        <select class="form-select form-select-sm" name="dentist_filter">
          <option value="all" <?= $dentistFilter==='all'?'selected':'' ?>>All</option>
          <?php foreach ($tabItems as $did => $label): if ($did==='all') continue; ?>
            <option value="<?= htmlspecialchars($did) ?>" <?= ((string)$dentistFilter===(string)$did)?'selected':'' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex flex-column justify-content-end">
        <label class="form-label small text-muted">Status</label>
        <?php $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'Pending'; ?>
        <select class="form-select form-select-sm" name="status">
          <?php foreach (['all','Pending','Confirmed'] as $st): ?>
            <option value="<?= $st ?>" <?= ($statusFilter===$st)?'selected':'' ?>><?= $st==='all'?'All status':$st ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto fixed-180 d-flex flex-column justify-content-end">
        <label class="form-label small text-muted">From</label>
        <input type="date" class="form-control form-control-sm" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
      </div>
      <div class="col-auto fixed-180 d-flex flex-column justify-content-end">
        <label class="form-label small text-muted">To</label>
        <input type="date" class="form-control form-control-sm" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
      </div>
      <div class="col-auto ms-auto d-flex flex-column align-items-end justify-content-end gap-2 align-self-end mt-0 fixed-actions">
        <label class="form-label small text-muted actions-label">Actions</label>
        <div class="d-flex flex-nowrap gap-2">
          <a class="btn btn-outline-secondary btn-sm" href="admin_appointments.php">Clear</a>
          <button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-search me-1"></i>Filter</button>
        </div>
      </div>
      <div class="col-12 d-flex align-items-center gap-2 flex-wrap mt-2 quick-range">
        <button class="btn btn-outline-primary btn-sm" type="button" onclick="__setApptWeek(0)">Today</button>
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="__setApptWeek(1)">Tomorrow</button>
      </div>
    </form>
    <script>
      (function(){
        var form = document.querySelector('form.filter-form');
        if (!form) form = document.querySelector('.filter-form');
        var q = form ? form.querySelector('input[name="q"]') : null;
        if (!q || !form) return;
        var t;
        q.addEventListener('input', function(){
          clearTimeout(t);
          t = setTimeout(function(){ form.requestSubmit ? form.requestSubmit() : form.submit(); }, 400);
        });
        // suggestions
        var dl = document.getElementById('patient-suggest');
        var ts;
        q.addEventListener('input', function(){
          clearTimeout(ts);
          var v = q.value.trim();
          if (v.length === 0) { if (dl) dl.innerHTML = ''; return; }
          ts = setTimeout(function(){
            fetch('admin_appointments.php?suggest=patients&q=' + encodeURIComponent(v))
              .then(r=>r.json()).then(data=>{
                if (!dl) return; dl.innerHTML = '';
                if (data && data.ok && Array.isArray(data.items)) {
                  data.items.forEach(function(it){ var o=document.createElement('option'); o.value = it.value; dl.appendChild(o); });
                }
              }).catch(()=>{});
          }, 250);
        });
      })();
    </script>
    <script>
      function validateWalkinForm(form){
        try {
          var dentist = form.querySelector('select[name="walkin_dentist_id"]');
          var dateInp = form.querySelector('input[name="walkin_date"]');
          var timeHid = form.querySelector('#walkinTimeHidden');
          var missing = [];
          if (!dentist || !dentist.value) missing.push('dentist');
          if (!dateInp || !dateInp.value) missing.push('date');
          if (!timeHid || !timeHid.value) missing.push('time');
          if (missing.length){
            alert('Please select ' + missing.join(', ') + ' for the walk-in appointment.');
            return false;
          }
        } catch(e) { /* ignore and allow submit */ }
        return true;
      }
      function validateRescheduleForm(form){
        try {
          var d = form.querySelector('input[name="new_date"]');
          var t = form.querySelector('select[name="new_time"], input[name="new_time"]');
          if (!d || !d.value || !t || !t.value){
            alert('Please select both date and time to reschedule.');
            return false;
          }
        } catch(e) { /* ignore */ }
        return true;
      }
    </script>

    <?php
    $appointments = [];
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    if (isset($conn)) {
      // Build base query based on range/date
      if ($range === 'all') {
        // True View All: any date, any status
        $sql = "SELECT a.Appointment_Id, a.Date, a.Time, a.`Procedure`, a.Status, a.Email, a.Dentist_Id,
                       CONCAT(COALESCE(p.First_name,''),' ',COALESCE(p.Last_name,'')) AS Patient_Name,
                       COALESCE(d.Name, CONCAT('Dentist #', a.Dentist_Id)) AS Dentist_Name
                FROM tbl_appointments a
                LEFT JOIN tbl_patient p ON (p.Patient_Id = a.Patient_Id OR p.Email = a.Email)
                LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id
                WHERE 1=1";
        $types = '';
        $params = [];
      } elseif ($range === 'week') {
        $startOfWeek = $currentWeekStart;
        $endOfWeek = date('Y-m-d', strtotime('+6 days', strtotime($startOfWeek)));
        $sql = "SELECT a.Appointment_Id, a.Date, a.Time, a.`Procedure`, a.Status, a.Email, a.Dentist_Id,
                       CONCAT(COALESCE(p.First_name,''),' ',COALESCE(p.Last_name,'')) AS Patient_Name,
                       COALESCE(d.Name, CONCAT('Dentist #', a.Dentist_Id)) AS Dentist_Name
                FROM tbl_appointments a
                LEFT JOIN tbl_patient p ON (p.Patient_Id = a.Patient_Id OR p.Email = a.Email)
                LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id
                WHERE a.Date BETWEEN ? AND ?";
        $types = 'ss';
        $params = [$startOfWeek, $endOfWeek];
      } elseif (!empty($_GET['date_from']) || !empty($_GET['date_to'])) {
        // If a date range is provided, do NOT clamp by CURDATE(); allow past dates to be queried
        $sql = "SELECT a.Appointment_Id, a.Date, a.Time, a.`Procedure`, a.Status, a.Email, a.Dentist_Id,
                       CONCAT(COALESCE(p.First_name,''),' ',COALESCE(p.Last_name,'')) AS Patient_Name,
                       COALESCE(d.Name, CONCAT('Dentist #', a.Dentist_Id)) AS Dentist_Name
                FROM tbl_appointments a
                LEFT JOIN tbl_patient p ON (p.Patient_Id = a.Patient_Id OR p.Email = a.Email)
                LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id
                WHERE 1=1";
        $types = '';
        $params = [];
      } elseif (empty($_GET['date']) || $_GET['date'] === date('Y-m-d')) {
        // Default view: upcoming, relevant statuses
        $sql = "SELECT a.Appointment_Id, a.Date, a.Time, a.`Procedure`, a.Status, a.Email, a.Dentist_Id,
                       CONCAT(COALESCE(p.First_name,''),' ',COALESCE(p.Last_name,'')) AS Patient_Name,
                       COALESCE(d.Name, CONCAT('Dentist #', a.Dentist_Id)) AS Dentist_Name
                FROM tbl_appointments a
                LEFT JOIN tbl_patient p ON (p.Patient_Id = a.Patient_Id OR p.Email = a.Email)
                LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id
                WHERE a.Date >= CURDATE() AND a.Status IN ('Pending', 'Booked', 'Confirmed', 'Ongoing')";
        $types = '';
        $params = [];
      } else {
        // Specific date view
        $sql = "SELECT a.Appointment_Id, a.Date, a.Time, a.`Procedure`, a.Status, a.Email, a.Dentist_Id,
                       CONCAT(COALESCE(p.First_name,''),' ',COALESCE(p.Last_name,'')) AS Patient_Name,
                       COALESCE(d.Name, CONCAT('Dentist #', a.Dentist_Id)) AS Dentist_Name
                FROM tbl_appointments a
                LEFT JOIN tbl_patient p ON (p.Patient_Id = a.Patient_Id OR p.Email = a.Email)
                LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id
                WHERE a.Date = ?";
        $types = 's';
        $params = [$selectedDate];
      }

      // Optional patient search (supports first name, last name, full name, and email)
      if ($searchQuery !== '') {
        $sql .= " AND (p.First_name LIKE ? OR p.Last_name LIKE ? OR CONCAT(p.First_name,' ',p.Last_name) LIKE ? OR a.Email LIKE ?)";
        $types .= 'ssss';
        $like = '%'.$searchQuery.'%';
        array_push($params, $like, $like, $like, $like);
      }

      // Status filter (trim and case-normalize to avoid mismatches)
      if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
        $sql .= " AND UPPER(TRIM(a.Status)) = UPPER(TRIM(?))";
        $types .= 's';
        $params[] = $_GET['status'];
      }
      // By default, hide Cancelled unless explicitly requested via status=Cancelled
      if (empty($_GET['status']) || $_GET['status'] === 'all') {
        $sql .= " AND a.Status <> 'Cancelled'";
      }

      // Date range filter
      $dateFrom = trim($_GET['date_from'] ?? '');
      $dateTo = trim($_GET['date_to'] ?? '');
      if ($dateFrom !== '' && $dateTo !== '') {
        $sql .= " AND a.Date BETWEEN ? AND ?";
        $types .= 'ss';
        array_push($params, $dateFrom, $dateTo);
      } elseif ($dateFrom !== '') {
        $sql .= " AND a.Date >= ?";
        $types .= 's';
        $params[] = $dateFrom;
      } elseif ($dateTo !== '') {
        $sql .= " AND a.Date <= ?";
        $types .= 's';
        $params[] = $dateTo;
      }

      // If Show Finished is checked, expand default upcoming status filter to include Finished
      if ($showCompleted && $range !== 'all') {
        $sql = str_replace(
          "a.Status IN ('Pending', 'Booked', 'Confirmed', 'Ongoing')",
          "(a.Status IN ('Pending', 'Booked', 'Confirmed', 'Ongoing') OR a.Status = 'Finished')",
          $sql
        );
      }

      // Filter out finished appointments unless explicitly requested
      if (!$showCompleted) {
         $sql .= " AND a.Status != 'Finished'";
       }

      // Optional dentist filter (all base queries already include a WHERE clause)
      if ($dentistFilter !== 'all' && ctype_digit($dentistFilter)) {
        $sql .= " AND a.Dentist_Id = ?";
        $types .= 'i';
        $params[] = (int)$dentistFilter;
      }

      // Order
      $sql .= " ORDER BY a.Date ASC, a.Time ASC";
      // Pagination
      $sql .= " LIMIT ? OFFSET ?";
      $types .= 'ii';
      array_push($params, $perPage, $offset);

      $stmt = $conn->prepare($sql);
      if ($stmt) {
        if (!empty($types) && !empty($params)) {
          $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
          $res = $stmt->get_result();
          while ($row = $res->fetch_assoc()) { 
            $appointments[] = $row; 
          }
        }
        $stmt->close();
      }
    }
    ?>

    <div class="table-responsive admin-app-table">
      <table class="table table-striped table-hover align-middle appointments-table">
        <colgroup>
          <col style="width: 10%;">  <!-- Name -->
          <col style="width: 13%;">  <!-- Procedure -->
          <col style="width: 12%;">   <!-- Date -->
          <col style="width: 14%;">  <!-- Doctor -->
          <col style="width: 10%;">   <!-- Time -->
          <col style="width: 10%;">  <!-- Status -->
          <col style="width: 12%;">  <!-- Notes (wider) -->
          <col style="width: 17%;">  <!-- Actions (wider) -->
        </colgroup>
        <thead>
          <tr>
            <th class="name-cell">Name</th>
            <th class="proc-cell">Procedure</th>
            <th>Date</th>
            <th class="doctor-cell">Doctor</th>
            <th>Time</th>
            <th>Status</th>
            <th class="text-nowrap">Notes</th>
          <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($appointments)): ?>
            <tr>
              <td colspan="8">
                <div class="empty-state">
                  <i class="fa-regular fa-calendar-xmark"></i>
                  <div>No appointments match your filters.</div>
                </div>
              </td>
            </tr>
          <?php else: foreach ($appointments as $a): ?>
            <tr>
              <?php 
                $rawName = trim((string)($a['Patient_Name'] ?? ''));
                $rawEmail = (string)($a['Email'] ?? '');
                $displayName = $rawName !== '' ? $rawName : $rawEmail;
                // Sanitize procedure display: remove appended Contact/Address segments like "| Contact: ..." or "| Address: ..."
                $rawProc = (string)($a['Procedure'] ?? '');
                $procDisplay = preg_replace('/\s*\|\s*(Contact|Address)\s*:[^|]*/i', '', $rawProc);
                // Remove any trailing separators and extra spaces
                $procDisplay = trim(preg_replace('/\s*\|\s*$/', '', $procDisplay));
              ?>
              <td class="name-cell" title="<?= htmlspecialchars($displayName) ?>"><?= htmlspecialchars($displayName) ?></td>
              <td class="proc-cell" title="<?= htmlspecialchars($procDisplay) ?>"><?= htmlspecialchars($procDisplay) ?></td>
              <td><?= htmlspecialchars(date('M j, Y', strtotime((string)($a['Date'] ?? '')))) ?></td>
              <td class="doctor-cell" title="<?= htmlspecialchars($a['Dentist_Name']) ?>"><?= htmlspecialchars($a['Dentist_Name']) ?></td>
              <td><?= htmlspecialchars(date('g:i A', strtotime((string)($a['Time'] ?? '')))) ?></td>
              <td>
                <?php
                  $st = (string)($a['Status'] ?? '');
                  $map = [
                    'Booked' => 'bg-booked',
                    'Confirmed' => 'bg-confirmed',
                    'Ongoing' => 'bg-ongoing',
                    'Finished' => 'bg-finished',
                    'Cancelled' => 'bg-cancelled',
                    'Rescheduled' => 'bg-rescheduled',
                    'Pending' => 'bg-pending',
                  ];
                  $cls = $map[$st] ?? 'bg-pending';
                ?>
                <span class="badge badge-status <?= $cls ?>"><?= htmlspecialchars($st) ?></span>
              </td>
              <td class="text-nowrap">
                <?php 
                  $notes = trim((string)($a['Admin_Notes'] ?? ''));
                  $aid = (int)$a['Appointment_Id'];
                ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#notesModal-<?= $aid ?>">
                  <i class="fa-regular fa-note-sticky me-1"></i> <?= $notes !== '' ? 'Edit' : 'Add' ?>
                </button>
                <div class="modal fade" id="notesModal-<?= $aid ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header"><h5 class="modal-title">Notes</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                      <form method="post">
                        <div class="modal-body">
                          <input type="hidden" name="save_notes_id" value="<?= $aid ?>" />
                          <textarea name="notes_content" class="form-control" rows="5" placeholder="Enter notes..."><?= htmlspecialchars($notes) ?></textarea>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                          <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <?php if ($a['Status'] !== 'Finished'): ?>
                  <div class="d-flex gap-1 flex-wrap">
                    <form method="post" class="d-inline" onsubmit="hideCompleteButton(this)" title="Mark as Done">
                      <input type="hidden" name="complete_id" value="<?= (int)$a['Appointment_Id'] ?>" />
                      <button class="btn btn-success btn-sm" type="submit">Done</button>
                    </form>
                    <form method="post" class="d-inline" title="Confirm Appointment" onsubmit="return disableSubmitOnce(this)">
                      <input type="hidden" name="confirm_id" value="<?= (int)$a['Appointment_Id'] ?>" />
                      <button class="btn btn-primary btn-sm" type="submit">Confirm</button>
                    </form>
                    <form method="post" class="d-inline" title="Cancel Appointment" onsubmit="return disableSubmitOnce(this)">
                      <input type="hidden" name="cancel_id" value="<?= (int)$a['Appointment_Id'] ?>" />
                      <button class="btn btn-danger btn-sm" type="submit">Cancel</button>
                    </form>
                    <button class="icon-btn" type="button" title="Reschedule"
                            aria-haspopup="true" aria-expanded="false"
                            data-target="#rs-<?= (int)$a['Appointment_Id'] ?>"
                            onclick="toggleReschedulePopover(this)">
                      <i class="fa-regular fa-clock icon-warning"></i>
                    </button>
                    <div id="rs-<?= (int)$a['Appointment_Id'] ?>" class="reschedule-popover" role="dialog" aria-modal="false">
                      <form method="post" class="reschedule-form" onsubmit="return validateRescheduleForm(this) && hideCompleteButton(this)">
                        <input type="hidden" name="reschedule_id" value="<?= (int)$a['Appointment_Id'] ?>" />
                        <input type="date" name="new_date" value="<?= htmlspecialchars($selectedDate) ?>" class="form-control form-control-sm" />
                        <select name="new_time" class="form-select form-select-sm">
                          <?php
                            for ($h = 7; $h <= 18; $h++) {
                              for ($m = 0; $m < 60; $m += 5) {
                                if ($h === 18 && $m > 55) { break; }
                                $hm = sprintf('%02d:%02d:00', $h, $m);
                                $sel = (isset($a['Time']) && $a['Time'] === $hm) ? ' selected' : '';
                                echo '<option value="'.$hm.'"'.$sel.'>'.date('g:i A', strtotime($hm))."</option>";
                              }
                            }
                          ?>
                        </select>
                        <button class="btn-reschedule" type="submit">Save</button>
                      </form>
                    </div>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>

        <?php
          // Simple Prev/Next pagination without total count
          $hasNext = count($appointments) === $perPage; // heuristic
          $query = $_GET; $query['page'] = max(1, $page-1); $prevUrl = 'admin_appointments.php?'.http_build_query($query);
          $query['page'] = $page+1; $nextUrl = 'admin_appointments.php?'.http_build_query($query);
        ?>
       
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      </body>
    </html>
