<?php
// Appointment booking logic
// Handles form submission and validation

function handleAppointmentBooking($conn, $email, $patient, $treatmentPrices) {
    $alert = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {

        // Check if this is a form resubmission
        if (isset($_SESSION['last_booking']) && $_SESSION['last_booking'] === $_POST['date'] . $_POST['time'] . $_POST['dentist_id'] . $_POST['treatment']) {
            $alert = '<div class="bg-yellow-100 text-yellow-700 px-4 py-2 rounded mb-4">This appointment has already been submitted. Please check your appointments list below.</div>';
        } else {
            $date = $_POST['date'];
            $time = $_POST['time'];
            $dentist_id = isset($_POST['dentist_id']) ? (int)$_POST['dentist_id'] : 0;
            $treatment = isset($_POST['treatment']) ? $_POST['treatment'] : '';
            $treatment_list = isset($_POST['treatment_list']) ? $_POST['treatment_list'] : '';

            // Validate that the date is not in the past
            $today = date('Y-m-d');
            if ($date < $today) {
                $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">❌ Cannot book appointments for past dates. Please select today or a future date.</div>';
            } else {
                // Load treatment names (ignore price) and merge DB with config like homepage
                $treatmentsList = [];
                try {
                    if ($conn && ($res = $conn->query("SHOW TABLES LIKE 'tbl_treatments'")) && $res->num_rows > 0) {
                        $qr = $conn->query("SELECT name, description, duration FROM tbl_treatments ORDER BY name ASC");
                        if ($qr) { while ($row = $qr->fetch_assoc()) { $treatmentsList[] = $row; } }
                    }
                } catch (Throwable $e) {}
                // Merge in config-defined treatments missing from DB by name
                include 'appointment_config.php';
                if (!empty($treatmentsList)) {
                    $byName = [];
                    foreach ($treatmentsList as $r) {
                        $n = trim((string)($r['name'] ?? ''));
                        if ($n !== '') { $byName[strtolower($n)] = true; }
                    }
                    if (isset($treatments) && is_array($treatments)) {
                        foreach ($treatments as $cfg) {
                            $n = trim((string)($cfg['name'] ?? ''));
                            if ($n === '') continue;
                            $key = strtolower($n);
                            if (!isset($byName[$key])) {
                                $treatmentsList[] = [
                                    'name' => $n,
                                    'description' => (string)($cfg['description'] ?? ''),
                                    'duration' => isset($cfg['duration']) ? $cfg['duration'] : null
                                ];
                                $byName[$key] = true;
                            }
                        }
                    }
                } else {
                    if (isset($treatments) && is_array($treatments)) { $treatmentsList = $treatments; }
                }

                // Build lookup by name
                $map = [];
                foreach ($treatmentsList as $t) { if (!empty($t['name'])) $map[$t['name']] = $t; }

                // Determine which list to use: explicit list or single label
                $selectedNames = [];
                if (!empty($treatment_list)) {
                    $selectedNames = array_filter(array_map('trim', explode('|', $treatment_list)));
                } elseif (!empty($treatment)) {
                    $selectedNames = [$treatment];
                }

                // Expand "All Treatments" to all configured names
                if (in_array('All Treatments', $selectedNames, true)) {
                    $selectedNames = array_map(function($t){ return $t['name']; }, $treatmentsList);
                }

                // Remove dupes
                $selectedNames = array_values(array_unique($selectedNames));

                // Validate selected treatment names (ignore duration/price)
                $validNames = [];
                foreach ($selectedNames as $nm) {
                    if (isset($map[$nm])) {
                        $validNames[] = $nm;
                    }
                }
                // If nothing valid, try single treatment fallback
                if (empty($validNames) && isset($map[$treatment])) {
                    $validNames = [$treatment];
                }
                // Require at least one treatment
                if (empty($validNames)) {
                    $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">❌ Please select at least one treatment before booking.</div>';
                } else {
                    // Calculate total duration
                    $totalDuration = 0;
                    foreach ($validNames as $treatmentName) {
                        if (isset($map[$treatmentName]['duration'])) {
                            $totalDuration += (int)$map[$treatmentName]['duration'];
                        } else {
                            $totalDuration += 30; // Default duration
                        }
                    }

                    // Update treatment display text
                    $treatment = implode(', ', $validNames);
                }
                
                // Proceed with dentist validation and booking without duration/price logic
                if (empty($alert)) {
                    // Validate dentist exists in config
                    include 'appointment_config.php';
                    $dentist_exists = false;
                    $dentist_name_for_insert = 'Dentist #'.intval($dentist_id);
                    foreach ($dentists as $dentist) {
                        if ($dentist['id'] == $dentist_id) {
                            $dentist_exists = true;
                            if (!empty($dentist['name'])) { $dentist_name_for_insert = $dentist['name']; }
                            break;
                        }
                    }
                    if (!$dentist_exists) {
                        $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Invalid dentist selected.</div>';
                    } else {
                        // Ensure dentist exists in tbl_dentist to satisfy FK (handle Email UNIQUE/NOT NULL safely)
                        $tblCheck = $conn->query("SHOW TABLES LIKE 'tbl_dentist'");
                        if ($tblCheck && $tblCheck->num_rows > 0) {
                            $existsStmt = $conn->prepare("SELECT 1 FROM tbl_dentist WHERE Dentist_id = ? LIMIT 1");
                            if ($existsStmt) {
                                $existsStmt->bind_param('i', $dentist_id);
                                $existsStmt->execute();
                                $existsStmt->store_result();
                                if ($existsStmt->num_rows === 0) {
                                    // Detect if Email column exists
                                    $colRes = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_dentist' AND COLUMN_NAME = 'Email' LIMIT 1");
                                    $hasEmailCol = false;
                                    if ($colRes) { $colRes->execute(); $colRes->store_result(); $hasEmailCol = $colRes->num_rows > 0; $colRes->close(); }
                                    if ($hasEmailCol) {
                                        $dent_email = 'dentist'.$dentist_id.'@local';
                                        $sql = "INSERT INTO tbl_dentist (Dentist_id, Name, Specialization, Email) VALUES (?, ?, 'General Dentistry', ?) ON DUPLICATE KEY UPDATE Name = VALUES(Name), Specialization = VALUES(Specialization)";
                                        if ($insDent = $conn->prepare($sql)) {
                                            $insDent->bind_param('iss', $dentist_id, $dentist_name_for_insert, $dent_email);
                                            $insDent->execute();
                                            $insDent->close();
                                        }
                                    } else {
                                        $sql = "INSERT INTO tbl_dentist (Dentist_id, Name, Specialization) VALUES (?, ?, 'General Dentistry') ON DUPLICATE KEY UPDATE Name = VALUES(Name), Specialization = VALUES(Specialization)";
                                        if ($insDent = $conn->prepare($sql)) {
                                            $insDent->bind_param('is', $dentist_id, $dentist_name_for_insert);
                                            $insDent->execute();
                                            $insDent->close();
                                        }
                                    }
                                }
                                $existsStmt->close();
                            }
                        }

                        // Block booking if dentist is on leave for the selected date
                        try {
                            $conn->query("CREATE TABLE IF NOT EXISTS tbl_dentist_leave (\n                                Id INT AUTO_INCREMENT PRIMARY KEY,\n                                Dentist_Id INT NOT NULL,\n                                Leave_Date DATE NOT NULL,\n                                Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n                                UNIQUE KEY unique_leave (Dentist_Id, Leave_Date)\n                            ) ENGINE=InnoDB");
                            if ($chkLeave = $conn->prepare("SELECT 1 FROM tbl_dentist_leave WHERE Dentist_Id = ? AND Leave_Date = ? LIMIT 1")) {
                                $chkLeave->bind_param('is', $dentist_id, $date);
                                if ($chkLeave->execute()) {
                                    $chkLeave->store_result();
                                    if ($chkLeave->num_rows > 0) {
                                        $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Selected dentist is unavailable on this date (on leave). Please choose another date or dentist.</div>';
                                    }
                                }
                                $chkLeave->close();
                            }
                        } catch (Throwable $e) {}

                        // Normalize and validate clinic hours and past-time on current day
                        $start_time = date('H:i:s', strtotime($time));
                        // Prevent selecting past times when booking for today
                        if ($date === date('Y-m-d') && $start_time <= date('H:i:s')) {
                            $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Selected time is in the past for today. Please choose a future time.</div>';
                        } elseif ($start_time < '07:00:00' || $start_time > '19:00:00') {
                            $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Selected time is outside clinic hours (7:00 AM - 7:00 PM).</div>';
                        } else {
                            // Enforce 24-hour cooldown after a completed treatment with the SAME dentist
                            // Prefer treatment history; fallback to finished appointments
                            $requestedTs = strtotime($date . ' ' . $start_time);
                            $lastDoneTs = null; $lastDentId = null;
                            // 1) treatment history (same dentist)
                            $th = $conn->prepare("SELECT Treatment_Date, Treatment_Time, Dentist_Id FROM tbl_treatment_history WHERE Patient_Email = ? AND Dentist_Id = ? ORDER BY Treatment_Date DESC, Treatment_Time DESC LIMIT 1");
                            if ($th) {
                                $th->bind_param('si', $email, $dentist_id);
                                if ($th->execute()) {
                                    $rh = $th->get_result();
                                    if ($row = $rh->fetch_assoc()) {
                                        $lastDoneTs = strtotime($row['Treatment_Date'] . ' ' . $row['Treatment_Time']);
                                        $lastDentId = isset($row['Dentist_Id']) ? (int)$row['Dentist_Id'] : null;
                                    }
                                }
                                $th->close();
                            }
                            // 2) fallback: finished appointments
                            if ($lastDoneTs === null) {
                                $fa = $conn->prepare("SELECT `Date`, `Time`, Dentist_Id FROM tbl_appointments WHERE Email = ? AND Dentist_Id = ? AND Status = 'Finished' ORDER BY `Date` DESC, `Time` DESC LIMIT 1");
                                if ($fa) {
                                    $fa->bind_param('si', $email, $dentist_id);
                                    if ($fa->execute()) {
                                        $ra = $fa->get_result();
                                        if ($row = $ra->fetch_assoc()) {
                                            $lastDoneTs = strtotime($row['Date'] . ' ' . $row['Time']);
                                            $lastDentId = isset($row['Dentist_Id']) ? (int)$row['Dentist_Id'] : null;
                                        }
                                    }
                                    $fa->close();
                                }
                            }
                            if ($lastDoneTs !== null && $requestedTs < ($lastDoneTs + 24*3600)) {
                                $alert = '<div class="bg-yellow-100 text-yellow-800 px-4 py-2 rounded mb-4">You recently completed a treatment with this dentist. Please wait 24 hours before booking again with the same doctor.</div>';
                            }

                            // Informational notification if within 24h of any completed treatment but selecting a different dentist (do not block)
                            if ($lastDoneTs !== null && $requestedTs < ($lastDoneTs + 24*3600)) {
                                if ($lastDentId !== null && $lastDentId !== $dentist_id) {
                                    // Do not notify admins/dentists for pending bookings (per requirement)
                                    // Insert appointment with Duration (minutes)
                                    $insert = $conn->prepare("INSERT INTO tbl_appointments (Email, Patient_Id, Dentist_Id, `Procedure`, Date, Time, Duration, Status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
                                    $durMins = (int)$totalDuration; if ($durMins <= 0) { $durMins = 30; }
                                    $insert->bind_param("siisssi", $email, $patient['Patient_Id'], $dentist_id, $treatment, $date, $start_time, $durMins);
                                    if ($insert->execute()) {
                                        $_SESSION['last_booking'] = $date . $start_time . $dentist_id . $treatment;
                                        $alert = '<div class="bg-blue-100 text-blue-700 px-4 py-2 rounded mb-4">Appointment request submitted. Please wait for admin confirmation.</div>';
                                        header('Location: appointment.php?booked=1');
                                        exit();
                                    } else {
                                        $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Booking failed. Please try again.</div>';
                                    }
                                    $insert->close();
                                }
                            }

                            if (empty($alert)) {
                                // Limit to 2 active bookings in total (across all dentists)
                                $limit = 2;
                                $cnt = 0;
                                if ($chk = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_appointments WHERE Email = ? AND Status IN ('Pending','Confirmed')")) {
                                    $chk->bind_param('s', $email);
                                    if ($chk->execute()) {
                                        $resCnt = $chk->get_result();
                                        if ($resCnt && ($row = $resCnt->fetch_assoc())) { $cnt = (int)$row['c']; }
                                    }
                                    $chk->close();
                                }
                                if ($cnt >= $limit) {
                                    $alert = '<div class="bg-yellow-100 text-yellow-800 px-4 py-2 rounded mb-4">You already have 2 active bookings in total. Please wait for those to be processed or cancel one before booking another.</div>';
                                }
                            }

                            if (empty($alert)) {
                                // Duration-aware overlap check (existing_start < requested_end) AND (requested_start < existing_end)
                                // Treat missing Duration as 30 minutes for backward compatibility
                                $check = $conn->prepare("\n                                    SELECT Appointment_Id\n                                    FROM tbl_appointments\n                                    WHERE Dentist_Id = ?\n                                      AND Date = ?\n                                      AND Status IN ('Pending','Booked','Confirmed','Ongoing')\n                                      AND (\n                                        TIME_TO_SEC(Time) < TIME_TO_SEC(ADDTIME(?, SEC_TO_TIME(?*60)))\n                                        AND\n                                        TIME_TO_SEC(?) < TIME_TO_SEC(ADDTIME(Time, SEC_TO_TIME(COALESCE(Duration, 30)*60)))\n                                      )\n                                ");
                                $reqStart = $start_time; $reqDuration = (int)$totalDuration; if ($reqDuration <= 0) { $reqDuration = 30; }
                                $check->bind_param("isssi", $dentist_id, $date, $reqStart, $reqDuration, $reqStart);
                                $check->execute();
                                $check->store_result();
                                if ($check->num_rows > 0) {
                                    $alert = '<div class="fixed inset-0 flex items-center justify-center z-50"><div class="bg-white border border-red-400 text-red-700 px-6 py-4 rounded shadow-lg text-center"><span class="block mb-2 font-semibold">This time conflicts with another appointment for the selected dentist.</span><button onclick="closeAlert()" class="mt-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Close</button></div><div class="fixed inset-0 bg-black opacity-30"></div></div>';
                                } else {
                                    // Insert appointment without Duration/Price
                                    $insert = $conn->prepare("INSERT INTO tbl_appointments (Email, Patient_Id, Dentist_Id, `Procedure`, Date, Time, Duration, Status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
                                    $insert->bind_param("siisssi", $email, $patient['Patient_Id'], $dentist_id, $treatment, $date, $start_time, $totalDuration);
                                    if ($insert->execute()) {
                                        $_SESSION['last_booking'] = $date . $start_time . $dentist_id . $treatment;
                                        $alert = '<div class="bg-blue-100 text-blue-700 px-4 py-2 rounded mb-4">Appointment request submitted. Please wait for admin confirmation.</div>';
                                        header('Location: appointment.php?booked=1');
                                        exit();
                                    } else {
                                        $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Booking failed. Please try again.</div>';
                                    }
                                    $insert->close();
                                }
                                $check->close();
                            }
                        }
                    }
                }
            }
        }
    }
    
    return $alert;
}

function handleAppointmentCancellation($conn, $email) {
    $alert = '';
    
    if (isset($_GET['cancel_id'])) {
        $cancel_id = intval($_GET['cancel_id']);
        
        $stmt = $conn->prepare("UPDATE tbl_appointments SET Status = 'Cancelled' WHERE Appointment_Id = ? AND Email = ?");
        $stmt->bind_param("is", $cancel_id, $email);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'id' => $cancel_id]);
                exit();
            }
            header('Location: appointment.php?cancelled=1');
            exit();
        } else {
            $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Cancellation failed.</div>';
        }
        $stmt->close();
    }
    
    return $alert;
}
?>
