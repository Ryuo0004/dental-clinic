<?php
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include 'admin_init.php';
} else {
  include 'db_connect.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

// Ensure required columns exist
if (isset($conn)) {
  // Create table if it doesn't exist
  $conn->query("CREATE TABLE IF NOT EXISTS tbl_dentist (
    Dentist_id INT AUTO_INCREMENT PRIMARY KEY, 
    Name VARCHAR(255), 
    Email VARCHAR(255) UNIQUE, 
    Password VARCHAR(255), 
    Specialization VARCHAR(100) NULL, 
    Phone VARCHAR(20) NULL, 
    clinic_schedule VARCHAR(255) NULL,
    Photo_Url VARCHAR(255) NULL, 
    is_active TINYINT(1) NOT NULL DEFAULT 1, 
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");
  
  // Add is_active column if it doesn't exist
  $chk = $conn->query("SHOW COLUMNS FROM tbl_dentist LIKE 'is_active'");
  if ($chk && $chk->num_rows === 0) { 
    $conn->query("ALTER TABLE tbl_dentist ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1"); 
  }
  
  // Add clinic_schedule column if it doesn't exist
  $chk = $conn->query("SHOW COLUMNS FROM tbl_dentist LIKE 'clinic_schedule'");
  if ($chk && $chk->num_rows === 0) { 
    $conn->query("ALTER TABLE tbl_dentist ADD COLUMN clinic_schedule VARCHAR(255) NULL AFTER Phone"); 
  }
}

// Handle leave creation via AJAX
if (isset($_GET['leave']) && isset($conn)) {
  header('Content-Type: application/json');
  $dentistId = (int)($_POST['dentist_id'] ?? 0);
  $date = trim((string)($_POST['date'] ?? ''));
  if ($dentistId <= 0 || $date === '') { echo json_encode(['ok'=>false,'error'=>'invalid params']); exit; }
  // Ensure table exists with unique constraint
  $conn->query("CREATE TABLE IF NOT EXISTS tbl_dentist_leave (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Dentist_Id INT NOT NULL,
    Leave_Date DATE NOT NULL,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_leave (Dentist_Id, Leave_Date)
  ) ENGINE=InnoDB");
  
  // Check if leave already exists
  $check = $conn->prepare('SELECT Id FROM tbl_dentist_leave WHERE Dentist_Id = ? AND Leave_Date = ?');
  $check->bind_param('is', $dentistId, $date);
  $check->execute();
  $exists = $check->get_result()->num_rows > 0;
  $check->close();
  
  if ($exists) {
    // Remove existing leave
    $stmt = $conn->prepare('DELETE FROM tbl_dentist_leave WHERE Dentist_Id = ? AND Leave_Date = ?');
    $stmt->bind_param('is', $dentistId, $date);
    $result = $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => $result, 'action' => 'removed']);
  } else {
    // Add new leave
    $stmt = $conn->prepare('INSERT INTO tbl_dentist_leave (Dentist_Id, Leave_Date) VALUES (?, ?)');
    $stmt->bind_param('is', $dentistId, $date);
    $result = $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => $result, 'action' => 'added']);
  }
  exit;
}

// Handle bulk cancel for a dentist on a specific date (e.g., dentist on leave)
if (isset($_GET['cancel_for_day']) && isset($conn)) {
  header('Content-Type: application/json');
  $dentistId = (int)($_POST['dentist_id'] ?? 0);
  $date = trim((string)($_POST['date'] ?? ''));
  if ($dentistId <= 0 || $date === '') { echo json_encode(['ok'=>false,'error'=>'invalid params']); exit; }

  // Cancel only active/upcoming-like statuses
  $statuses = ['Pending','Booked','Confirmed','Ongoing'];
  $placeholders = implode(',', array_fill(0, count($statuses), '?'));

  // Build types for bind
  $types = 'is' . str_repeat('s', count($statuses));

  // Update appointments
  $sql = "UPDATE tbl_appointments SET Status = 'Cancelled', Updated_At = NOW() \n          WHERE Dentist_Id = ? AND Date = ? AND Status IN (" . $placeholders . ")";
  $stmt = $conn->prepare($sql);
  if (!$stmt) { echo json_encode(['ok'=>false,'error'=>'prep error']); exit; }
  $params = array_merge([$types, $dentistId, $date], $statuses);
  $tmp = $stmt->bind_param(...$params);
  $okUpd = $stmt->execute();
  $affected = $okUpd ? $stmt->affected_rows : 0;
  $stmt->close();

  // Insert into treatment history for visibility (avoid duplicates)
  if ($affected > 0) {
    $ins = $conn->prepare("INSERT INTO tbl_treatment_history (Appointment_Id, Patient_Id, Patient_Email, Dentist_Id, Procedure_Name, Treatment_Date, Treatment_Time, Room, Admin_Notes)
                              SELECT a.Appointment_Id, a.Patient_Id, a.Email, a.Dentist_Id, a.`Procedure`, a.Date, a.Time, a.Room, CONCAT(COALESCE(a.Admin_Notes,''),'', '')
                              FROM tbl_appointments a
                              WHERE a.Dentist_Id = ? AND a.Date = ? AND a.Status = 'Cancelled'
                                AND NOT EXISTS (SELECT 1 FROM tbl_treatment_history th WHERE th.Appointment_Id = a.Appointment_Id)");
    if ($ins) { $ins->bind_param('is', $dentistId, $date); $ins->execute(); $ins->close(); }
  }

  echo json_encode(['ok'=> $okUpd, 'cancelled' => (int)$affected]);
  exit;
}

// Handle quick add dentist submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add']) && isset($conn)) {
  $name  = trim((string)($_POST['full_name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');
  $spec  = trim((string)($_POST['specialization'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $schedule = trim((string)($_POST['clinic_schedule'] ?? ''));

  $errors = [];
  if ($name === '') { $errors[] = 'Name is required'; }
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Valid email is required'; }
  if ($pass === '' || strlen($pass) < 6) { $errors[] = 'Password must be at least 6 characters'; }

  if (empty($errors)) {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    if ($stmt = $conn->prepare('INSERT INTO tbl_dentist (Name, Email, Password, Specialization, Phone, clinic_schedule, is_active) VALUES (?,?,?,?,?,?,1)')) {
      $stmt->bind_param('ssssss', $name, $email, $hash, $spec, $phone, $schedule);
      if ($stmt->execute()) {
        $stmt->close();
        header('Location: admin_dentists.php?added=1');
        exit;
      }
      $stmt->close();
      $errors[] = 'Failed to add dentist (maybe email already exists)';
    } else {
      $errors[] = 'Database error';
    }
  }
}

// AJAX toggle
if (isset($_GET['toggle']) && isset($conn)) {
  header('Content-Type: application/json');
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'invalid id']); exit; }
  $q = $conn->prepare('UPDATE tbl_dentist SET is_active = 1 - is_active WHERE Dentist_id = ?');
  if ($q) {
    $q->bind_param('i', $id);
    $ok = $q->execute();
    $q->close();
    if ($ok) {
      $s = $conn->prepare('SELECT is_active FROM tbl_dentist WHERE Dentist_id = ?');
      $s->bind_param('i', $id); $s->execute(); $r = $s->get_result(); $row = $r->fetch_assoc(); $s->close();
      echo json_encode(['ok'=>true,'is_active'=> (int)$row['is_active']]);
    } else { echo json_encode(['ok'=>false,'error'=>'db error']); }
  } else { echo json_encode(['ok'=>false,'error'=>'prep error']); }
  exit;
}

// Fetch dentists
$dentists = [];
if (isset($conn)) {
  $res = $conn->query('SELECT Dentist_id, Name, Email, COALESCE(Specialization, "") AS Specialization, COALESCE(Phone, "") AS Phone, clinic_schedule, COALESCE(is_active,1) AS is_active FROM tbl_dentist ORDER BY Name ASC');
  if ($res) { while ($row = $res->fetch_assoc()) { $dentists[] = $row; } }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dentists - Miles Dental Clinic</title>
  <?php include 'header.php'; ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    html, body { overflow-x: hidden; }
    body { background: #ffffff; color: #111827; }
    .content { padding: 16px; }
    .card { background: #ffffff; color: #111827; border: 1px solid #e5e7eb; }
    .badge-active { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .badge-inactive { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .btn.btn-toggle { 
      border: 1px solid #93c5fd; 
      background: #2563eb; 
      color: #ffffff; 
    }
    .btn.btn-toggle:hover { 
      background: #1e40af; 
      border-color: #1d4ed8; 
      color: #ffffff; 
    }
    /* Dentists page: force sidebar fixed and offset content */
    .dentists-fixed .sidebar { position: fixed !important; left: 0; top: 0; bottom: 0; height: 100vh; overflow: hidden; }
    .dentists-fixed .content { margin-left: 260px; }
    @media (max-width: 768px) {
      .dentists-fixed .sidebar { position: fixed !important; transform: none; width: 240px; }
      .dentists-fixed .content { margin-left: 240px; }
    }
  </style>
</head>
<body class="dentists-fixed">
  <div class="d-flex">
    <?php if (file_exists($baseDir . '/admin_sidebar.php')) { include 'admin_sidebar.php'; } ?>
    <div class="flex-grow-1 content">
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars(implode('. ', $errors)) ?></div>
      <?php elseif (isset($_GET['added'])): ?>
        <div class="alert alert-success">Dentist added successfully.</div>
      <?php endif; ?>

      <div class="card p-3 mb-3">
        <h5 class="mb-3">Dentist Management</h5>
        <form method="post" action="admin_dentists.php" class="row g-2">
          <input type="hidden" name="quick_add" value="1">
          <div class="col-12 col-md-6 col-lg-4">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" name="full_name" placeholder="Full Name" required>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" placeholder="Email" required>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" placeholder="Password" required>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <label class="form-label">Specialization</label>
            <select class="form-select" name="specialization">
              <option value="General Dentistry" selected>General Dentistry</option>
              <option value="Orthodontics">Orthodontics</option>
              <option value="Endodontics">Endodontics</option>
              <option value="Periodontics">Periodontics</option>
              <option value="Prosthodontics">Prosthodontics</option>
              <option value="Pediatric Dentistry">Pediatric Dentistry</option>
            </select>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <label class="form-label">Phone</label>
            <input type="tel" class="form-control" name="phone" placeholder="Phone">
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <label class="form-label">Clinic Schedule</label>
            <input type="text" class="form-control" name="clinic_schedule" placeholder="e.g., Mon-Fri 9AM-6PM, Sat 9AM-1PM">
            <small class="text-muted">Enter the dentist's regular clinic hours</small>
          </div>
          <div class="col-12 mt-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Add Dentist</button>
            <button type="reset" class="btn btn-outline-secondary">Cancel</button>
          </div>
        </form>
      </div>

      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h4 class="mb-0">Dentists</h4>
        </div>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Specialization</th>
                <th>Phone</th>
                <th>Schedule</th>
                <th>Leave Area</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($dentists)): ?>
                <tr><td colspan="8" class="text-muted text-center py-4">No dentists found.</td></tr>
              <?php else: foreach ($dentists as $d): ?>
                <tr data-id="<?= (int)$d['Dentist_id'] ?>">
                  <td><?= htmlspecialchars($d['Name'] ?: '—') ?></td>
                  <td style="min-width: 200px;">
                    <?= htmlspecialchars($d['Email'] ?: '—') ?>
                  </td>
                  <td><?= htmlspecialchars($d['Specialization'] ?: '—') ?></td>
                  <td><?= htmlspecialchars($d['Phone'] ?: '—') ?></td>
                  <td>
                    <input type="date" class="form-control form-control-sm" style="max-width: 160px;" id="cal-<?= (int)$d['Dentist_id'] ?>" value="<?= date('Y-m-d') ?>" />
                  </td>
                  <td>
                    <div class="d-flex flex-column gap-1" style="min-width:220px;">
                      <div class="text-muted small">Selected: <span id="sel-<?= (int)$d['Dentist_id'] ?>"><?= date('Y-m-d') ?></span></div>
                      <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-outline-primary" type="button" onclick="useSelectedAndToggle(<?= (int)$d['Dentist_id'] ?>)">Toggle Leave</button>
                        <button class="btn btn-sm btn-outline-danger" type="button" onclick="useSelectedAndCancelDay(<?= (int)$d['Dentist_id'] ?>)">Cancel Day</button>
                      </div>
                    </div>
                  </td>
                  <td>
                    <?php if ((int)$d['is_active'] === 1): ?>
                      <span class="badge badge-active">Active</span>
                    <?php else: ?>
                      <span class="badge badge-inactive">Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button class="btn btn-sm btn-toggle btn-outline-light mb-1" onclick="toggleActive(this)" title="Toggle Status">Toggle Status</button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <style>
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      if (window.bootstrap) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el){ new bootstrap.Tooltip(el); });
      }
      // Keep the Leave Area 'Selected' label in sync with the Schedule date picker
      document.querySelectorAll('input[type="date"][id^="cal-"]').forEach(function(inp){
        const dentistId = inp.id.replace('cal-','');
        const lbl = document.getElementById('sel-' + dentistId);
        if (lbl) {
          if (inp.value) { lbl.textContent = inp.value; }
          inp.addEventListener('change', function(){ lbl.textContent = this.value; });
        }
      });
    });
    
    // Helpers used by the Leave Area buttons
    function getSelectedDate(did) {
      const el = document.getElementById('cal-' + did);
      return el ? el.value : '';
    }
    function useSelectedAndToggle(did) {
      const d = getSelectedDate(did);
      if (!d) { alert('Please pick a date first.'); return; }
      markLeave(did, d);
    }
    async function useSelectedAndCancelDay(did) {
      const d = getSelectedDate(did);
      if (!d) { alert('Please pick a date first.'); return; }
      const yes = confirm(`Cancel all appointments for ${d}?`);
      if (!yes) return;
      try {
        const fd2 = new FormData();
        fd2.append('dentist_id', did);
        fd2.append('date', d);
        const res2 = await fetch('admin_dentists.php?cancel_for_day=1', { method: 'POST', body: fd2 });
        const data2 = await res2.json();
        if (data2 && data2.ok) {
          alert(`Cancelled ${data2.cancelled || 0} appointment(s) for ${d}.`);
        } else {
          alert('Failed to cancel appointments for that date.');
        }
      } catch (e) {
        alert('Error: ' + (e?.message || e));
      }
    }

    async function markLeave(id, dateStr) {
      if (!dateStr) return;
      const inputEl = document.getElementById(`cal-${id}`);
      
      // Confirmation: selecting a date toggles leave for that date. Allow canceling the action.
      const ok = confirm(`This will toggle leave for ${dateStr}.\n\nOK = Mark/Unmark leave\nCancel = Do nothing`);
      if (!ok) {
        // Keep the selected date visible so the admin can act again
        return;
      }
      
      try {
        const fd = new FormData();
        fd.append('dentist_id', id);
        fd.append('date', dateStr);
        
        const res = await fetch('admin_dentists.php?leave=1', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data && data.ok) {
          // Reflect whether we added or removed the leave
          if (data.action === 'removed') {
            alert(`Leave removed for ${dateStr}.`);
          } else if (data.action === 'added') {
            alert(`Leave marked for ${dateStr}.`);
            // Offer to cancel all appointments for that day
            const doCancel = confirm(`Do you also want to cancel all appointments for ${dateStr} for this dentist?`);
            if (doCancel) {
              try {
                const fd2 = new FormData();
                fd2.append('dentist_id', id);
                fd2.append('date', dateStr);
                const res2 = await fetch('admin_dentists.php?cancel_for_day=1', { method: 'POST', body: fd2 });
                const data2 = await res2.json();
                if (data2 && data2.ok) {
                  alert(`Cancelled ${data2.cancelled || 0} appointment(s) for ${dateStr}.`);
                } else {
                  alert('Failed to cancel appointments for that date.');
                }
              } catch (err) {
                alert('Error cancelling appointments: ' + (err?.message || err));
              }
            }
          } else {
            alert(`Leave updated for ${dateStr}.`);
          }
        } else {
          throw new Error((data && data.error) ? data.error : 'Failed to update leave');
        }
      } catch (e) {
        alert('Error: ' + e.message);
      }
    }
    async function toggleActive(btn) {
      const tr = btn.closest('tr');
      const id = tr ? tr.getAttribute('data-id') : null;
      if (!id) return;
      
      // Store the current date picker value
      const datePicker = tr.querySelector('input[type="date"]');
      const dateValue = datePicker ? datePicker.value : '';
      
      btn.disabled = true;
      try {
        const fd = new FormData(); 
        fd.append('id', id);
        const res = await fetch('admin_dentists.php?toggle=1', { 
          method: 'POST', 
          body: fd 
        });
        
        const data = await res.json();
        if (data && data.ok) {
          // Only update the status badge, not the entire row
          const badgeCell = tr.querySelector('td:nth-child(7)'); // 7th column after adding 'Leave Area'
          if (badgeCell) {
            badgeCell.innerHTML = data.is_active == 1 
              ? '<span class="badge badge-active">Active</span>' 
              : '<span class="badge badge-inactive">Inactive</span>';
          }
          
          // Restore the date picker value if it existed
          if (datePicker) {
            datePicker.value = dateValue;
          }
        } else {
          throw new Error(data?.error || 'Failed to update status');
        }
      } catch (e) {
        console.error('Toggle error:', e);
        alert('Error: ' + (e.message || 'Failed to update status'));
      } finally {
        btn.disabled = false;
      }
    }
  </script>

  <!-- Mini Calendar Modal -->
  <div class="modal fade" id="dentistCalendarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="dentCalTitle">Schedule</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2 text-muted small">Pick a date to view or plan schedule.</div>
          <input type="date" id="dentCalDate" class="form-control" />
          <div class="mt-3" id="dentCalInfo" style="min-height:40px;" class="text-muted small"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function openDentistCalendar(id, name){
      var t = document.getElementById('dentCalTitle');
      if (t) t.textContent = name + ' — Calendar';
      var info = document.getElementById('dentCalInfo');
      var dateEl = document.getElementById('dentCalDate');
      if (dateEl) {
        var today = new Date();
        var yyyy = today.getFullYear();
        var mm = String(today.getMonth()+1).padStart(2,'0');
        var dd = String(today.getDate()).padStart(2,'0');
        dateEl.value = yyyy + '-' + mm + '-' + dd;
        dateEl.onchange = function(){ if(info){ info.textContent = 'Selected: ' + this.value; } };
      }
      var m = new bootstrap.Modal(document.getElementById('dentistCalendarModal'));
      m.show();
    }
  </script>
</body>
</html>


