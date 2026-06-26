<?php
session_start();
include 'db_connect.php';
include 'settings_helper.php';
include 'appointment_config.php';
include 'appointment_booking.php';
include 'appointment_calendar.php';

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
$isBlockedPatient = (strcasecmp((string)$status, 'Blocked') === 0);

// Calendar params
$calYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$calMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
if ($calMonth < 1 || $calMonth > 12) { $calMonth = intval(date('n')); }
if ($calYear < 2000 || $calYear > 2100) { $calYear = intval(date('Y')); }

// Handle booking and cancellation (block bookings if patient is blocked)
$alert = '';
if ($isBlockedPatient) {
    $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Your account has been blocked by the clinic. Booking is disabled. Please contact the clinic for assistance.</div>';
} else {
    $alert = handleAppointmentBooking($conn, $email, $patient, $treatments);
    if (empty($alert)) {
        $alert = handleAppointmentCancellation($conn, $email);
    }
}

// Show success messages
if (isset($_GET['booked'])) {
    // Remove booking success notification UI but still clear last booking session data
    unset($_SESSION['last_booking']);
} elseif (isset($_GET['cancelled'])) {
    $alert = '<div class="bg-yellow-100 text-yellow-700 px-4 py-2 rounded mb-4">⚠️ Appointment cancelled successfully.</div>';
}



$calendarData = generateCalendarData($conn, $calYear, $calMonth);
$activeAppts = getActiveAppointments($conn, $email);
$appts = getAllAppointments($conn, $email);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>All Appointments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="patients.css" />
    <style>
        /* Base and Layout */
        .layout { display: flex; min-height: 100vh; flex-direction: column; }
        .content-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: 0; width: 100%; }
        .content { flex: 1; padding: 1rem; background: #ffffff; color: #111827; border-radius: 12px; margin: 0.5rem; width: calc(100% - 1rem); }
        .flex-grow {
            flex-grow: 1;
        }

        // Prevent submitting a booking in the past
        function validateNotPast() {
            const form = document.getElementById('appointmentForm');
            if (!form) return true;
            const dateVal = (form.querySelector('input[name="date"]')||{}).value;
            const timeVal = (document.getElementById('timeHidden')||{}).value;
            if (!dateVal || !timeVal) return true;
            try {
                const now = new Date();
                const todayStr = now.toISOString().split('T')[0];
                const round5 = n=> Math.floor(n/5)*5;
                const nowHM = String(now.getHours()).padStart(2,'0')+":"+String(round5(now.getMinutes())).padStart(2,'0');
                if (dateVal === todayStr && timeVal <= nowHM) { return false; }
                // If date is before today
                if (dateVal < todayStr) { return false; }
            } catch(e) { return true; }
            return true;
        }

        document.addEventListener('DOMContentLoaded', function(){
            const form = document.getElementById('appointmentForm');
            if (form) {
                form.addEventListener('submit', function(e){
                    if (!validateNotPast()) {
                        e.preventDefault();
                    }
                });
            }
        });
        .time-slot-btn {
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 70px;
            border: 1px solid #e5e7eb; /* gray-200 */
            background-color: #ffffff;
            border-radius: 0.75rem; /* rounded-xl */
        }
        /* Treatments reuse time-slot style but need variable height and wrapping */
        .time-slot-btn.treatment-btn {
            height: 70px;                /* same as time button */
            padding: 0.5rem 0.75rem;     /* match time button density */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;            /* clip overflow */
        }
        .time-slot-btn.treatment-btn .time-slot-time {
            max-width: 100%;
            white-space: normal;         /* wrap to two lines */
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;      /* limit to two lines */
            -webkit-box-orient: vertical;
            line-height: 1.2;
            max-height: 2.4em;          /* two lines */
        }
        .time-slot-btn.treatment-btn .time-slot-meridiem {
            max-width: 100%;
            white-space: nowrap;         /* single line */
            overflow: hidden;
            text-overflow: ellipsis;     /* ellipsis when too long */
        }
        .time-slot-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-color: #fca5a5; /* red-300 */
            background-color: #fef2f2; /* red-50 */
        }
        .time-slot-btn.selected {
            background-color: #ef4444 !important;
            color: white !important;
            border-color: #ef4444 !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
        }
        .time-slot-btn.selected-span {
            background-color: #fee2e2 !important; /* red-200 */
            border-color: #ef4444 !important;
        }
        .time-slot-btn.disabled-span {
            background-color: #fee2e2 !important; /* red-200 */
            border-color: #ef4444 !important;
            color: #991b1b !important; /* red-800 text */
            cursor: not-allowed !important;
            opacity: 0.9;
            pointer-events: none; /* block clicks entirely */
        }
        .time-slot-time { font-weight: 700; font-size: 1rem; line-height: 1.1rem; }
        .time-slot-meridiem { font-size: 0.8rem; line-height: 0.9rem; opacity: 0.9; }
        .time-slot-btn.disabled-other {
            background-color: #fecaca !important; /* red-300 */
            color: #7f1d1d !important; /* red-900 text */
            cursor: not-allowed;
            opacity: 0.95;
            border-color: #fecaca !important;
        }
        .time-slot-btn.disabled-user {
            background-color: #fecaca !important; /* red-300 */
            color: #7f1d1d !important;
            cursor: not-allowed;
            opacity: 0.9;
            border-color: #fecaca !important;
        }
        /* Treatment pill styles */
        .treatment-btn {
            transition: all 0.2s ease;
            border: 1px solid #e5e7eb; /* gray-200 */
            background-color: #ffffff;
            border-radius: 0.75rem; /* rounded-xl */
            padding: 0.75rem 1rem; /* px-4 py-3 */
            text-align: center;
            font-size: 0.875rem; /* text-sm */
            color: #111827; /* gray-900 */
            position: relative; /* allow absolute badge */
        }
        .treatment-btn:hover { 
            background-color: #f8fafc; /* slate-50 */
            border-color: #cbd5e1; /* slate-300 */
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
        }
        .treatment-btn.selected {
            background-color: #3b82f6; /* blue-500 */
            color: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 2px 4px rgba(59,130,246,0.3);
        }
        /* Disabled treatment look */
        .treatment-btn.disabled-user,
        .treatment-btn[data-disabled] {
            background-color: #fee2e2 !important; /* red-200 */
            border-color: #fecaca !important; /* red-300 */
            color: #7f1d1d !important; /* red-900 */
            cursor: not-allowed !important;
            box-shadow: none !important;
        }
        .treatment-btn.disabled-user:hover,
        .treatment-btn[data-disabled]:hover { transform: none; }
        .treatment-btn .no-stock-badge {
            position: absolute;
            top: 6px; right: 6px;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 9999px;
            background: #fecaca; /* red-300 */
            color: #7f1d1d;      /* red-900 */
            border: 1px solid #fca5a5; /* red-400 */
            pointer-events: none;
        }
        /* Step headers and summary */
        .step-title { display: flex; align-items: center; gap: 0.5rem; font-weight: 600; color: #1f2937; }
        .step-badge { width: 26px; height: 26px; border-radius: 9999px; background: #dbeafe; color: #1d4ed8; display: inline-flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 700; }
        .summary-bar { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.75rem 1rem; font-size: 0.875rem; color: #334155; }
        /* Ensure each calendar cell has enough width; allow horizontal scroll on narrow screens */
        .calendar-grid { grid-template-columns: repeat(7, minmax(78px, 1fr)); }
        /* Dentist cards */
        .dentist-btn { 
            transition: all 0.18s ease; 
            border: 1px solid #e5e7eb; 
            background-color: #ffffff; 
            border-radius: 0.75rem; 
            padding: 0.75rem 0.9rem; 
            text-align: left; 
        }
        .dentist-btn:hover { 
            background:#f8fafc; 
            border-color:#cbd5e1; 
            transform: translateY(-1px); 
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .dentist-btn.selected { 
            border-color:#2563eb; 
            box-shadow: 0 0 0 3px rgba(37,99,235,0.18);
            background:#f8fafc;
        }
        .dentist-avatar {
            width: 40px; height: 40px; border-radius: 9999px; 
            display: inline-flex; align-items: center; justify-content: center;
            background: #e0e7ff; color:#3730a3; font-weight: 700;
            border: 1px solid #c7d2fe;
            flex: 0 0 auto;
        }
        .dentist-name { font-weight: 700; color: #0f172a; font-size: 0.95rem; }
        .dentist-meta { font-size: 0.75rem; color: #475569; }
        .dentist-status { 
            font-size: 0.70rem; 
            padding: 0.15rem 0.45rem; 
            border-radius: 9999px; 
            border: 1px solid #d1fae5; 
            background:#ecfdf5; 
            color:#065f46; 
            white-space: nowrap;
        }
        .dentist-btn.inactive .dentist-status {
            border-color:#fee2e2; background:#fef2f2; color:#991b1b;
        }
        .dentist-btn.on-leave .dentist-status {
            border-color:#fee2e2; background:#fef2f2; color:#b91c1c;
        }
        /* Booking modal responsive panel */
        .modal-panel { max-width: 52rem; width: 100%; margin-left: 1rem; margin-right: 1rem; max-height: 90vh; overflow-y: auto; border-radius: 0.5rem; }
        @media (max-width: 640px) {
          .modal-panel { max-width: 100%; width: 100%; height: 100vh; max-height: 100vh; margin: 0; border-radius: 0; }
          /* make form spacing tighter on small screens */
          #appointmentForm { gap: 0.75rem; padding-bottom: 0.25rem; }
          /* denser grids on mobile */
          #timeSlotsContainer { grid-template-columns: repeat(2, minmax(0,1fr)); }
          #treatmentsContainer { grid-template-columns: repeat(1, minmax(0,1fr)); }
        }
        /* Calendar header + mobile tweaks */
        .calendar-weekdays { user-select: none; }
        .calendar-weekdays > div { padding: 6px 0; }
        /* Mobile-first responsive styles */
        @media (max-width: 768px) {
          .content { padding: 0.75rem; margin: 0; width: 100%; border-radius: 0; }
          .calendar-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
          .calendar-header h2 { font-size: 1.25rem; margin: 0; }
          .calendar-controls { width: 100%; justify-content: space-between; }
          .calendar-controls a, .calendar-controls span { 
            padding: 0.4rem 0.6rem; 
            font-size: 0.9rem;
            min-width: 2.5rem;
            text-align: center;
          }
          .calendar-grid { 
            font-size: 0.85rem;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.25rem;
          }
          .calendar-grid .day-cell { 
            padding: 0.5rem 0.25rem !important; 
            min-height: 3.5rem;
            font-size: 0.85rem;
          }
          .day-number { 
            width: 1.75rem; 
            height: 1.75rem; 
            line-height: 1.75rem;
            font-size: 0.9rem;
          }
          .appointment-indicator {
            width: 6px;
            height: 6px;
            margin: 1px auto 0;
          }
        }
          .calendar-weekdays { font-size: 0.75rem; }
          .calendar-grid .day-count { max-width: 64px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display:inline-block; }
          /* Keep calendar at 7 columns so it aligns with weekday header */
          .calendar-grid { grid-template-columns: repeat(7, minmax(0, 1fr)) !important; }
        
        /* Responsive calendar columns to avoid cramming on small screens */
        @media (max-width: 480px) {
          .calendar-grid .day-cell { min-height: 80px; }
          .calendar-grid { grid-template-columns: repeat(7, minmax(0, 1fr)) !important; }
          .calendar-weekdays { font-size: 0.7rem; }
        }
        @media (min-width: 481px) and (max-width: 640px) {
          .calendar-grid .day-cell { min-height: 72px; }
          .calendar-grid { grid-template-columns: repeat(7, minmax(0, 1fr)) !important; }
        }
        /* Responsive Sidebar Toggle */
        .hamburger { position: fixed; left: 12px; top: 12px; z-index: 11000; display: none; padding: 10px 12px; border:1px solid #e5e7eb; background:#ffffff; color:#111827; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
        .hamburger svg { width:20px; height:20px; }
        .backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:10900; opacity:0; transition:opacity .2s ease; }
        @media (max-width: 900px) {
          .hamburger { display: inline-flex; align-items:center; gap:8px; }
          /* Sidebar off-canvas */
          .sidebar { position: fixed; left:0; top:0; bottom:0; height:100vh; transform: translateX(-100%); box-shadow:0 8px 24px rgba(0,0,0,0.1); transition: transform .25s ease; }
          body.sidebar-open .sidebar { transform: translateX(0); }
          body.sidebar-open .backdrop { display:block; opacity:1; }
        }
    </style>
    <script>
        // Booking disabled for blocked patients
        const patientBlocked = <?= $isBlockedPatient ? 'true' : 'false' ?>;
        const isAdmin = <?= (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) ? 'true' : 'false' ?>;
        const dentists = <?php
          // Build dentist list directly from tbl_dentist; filter to ACTIVE dentists only
          $jsDentists = [];
          if (isset($conn)) {
            $chk = $conn->query("SHOW TABLES LIKE 'tbl_dentist'");
            if ($chk && $chk->num_rows > 0) {
              $hasActiveCol = false;
              if ($col = $conn->query("SHOW COLUMNS FROM tbl_dentist LIKE 'is_active'")) {
                $hasActiveCol = $col->num_rows > 0; 
              }
              $sql = "SELECT Dentist_id AS id,
                              COALESCE(NULLIF(TRIM(Name), ''), CONCAT('Dentist #', Dentist_id)) AS name,
                              COALESCE(NULLIF(TRIM(Specialization), ''),'General Dentistry') AS specialty,
                              " . ($hasActiveCol ? "COALESCE(is_active,1)" : "1") . " AS is_active
                       FROM tbl_dentist ";
              $sql .= "ORDER BY name ASC";
              $res = $conn->query($sql);
              if ($res) { while ($row = $res->fetch_assoc()) { $jsDentists[] = $row; } }
            }
          }
          if (empty($jsDentists)) {
            // Fallback if table missing/empty
            $jsDentists = [['id'=>1,'name'=>'Dentist #1','specialty'=>'General Dentistry','is_active'=>1]];
          }
          echo json_encode($jsDentists);
        ?>;
        const treatments = <?= json_encode($treatments) ?>;
        function validateTreatmentSelection() {
            const listHidden = document.getElementById('treatmentListHidden');
            const tHidden = document.getElementById('treatmentHidden');
            const selected = (listHidden && listHidden.value) ? listHidden.value : (tHidden && tHidden.value ? tHidden.value : '');
            if (!selected) {
                alert('Please select at least one treatment to continue.');
                return false;
            }
            return true;
        }
        
        function selectTimeSlot(timeValue) {
            if (patientBlocked) { return; }
            const timeHidden = document.getElementById('timeHidden');
            const form = document.getElementById('appointmentForm');
            const dateVal = form ? (form.querySelector('input[name="date"]')||{}).value : '';
            // Prevent picking past time for today
            try {
                if (dateVal) {
                    const now = new Date();
                    const todayStr = now.toISOString().split('T')[0];
                    const round5 = n=> Math.floor(n/5)*5;
                    const nowHM = String(now.getHours()).padStart(2,'0')+":"+String(round5(now.getMinutes())).padStart(2,'0');
                    if (dateVal === todayStr && timeValue <= nowHM) {
                        return; // do not allow selecting past time
                    }
                }
            } catch(e){}
            timeHidden.value = timeValue;

            // Only affect time buttons inside the timeSlotsContainer, not treatment pills
            const timeSlotsContainer = document.getElementById('timeSlotsContainer');
            if (timeSlotsContainer) {
                timeSlotsContainer.querySelectorAll('.time-slot-btn').forEach(btn => {
                    btn.classList.remove('selected');
                });
            }

            const selectedBtn = timeSlotsContainer ? timeSlotsContainer.querySelector(`[onclick="selectTimeSlot('${timeValue}')"]`) : document.querySelector(`[onclick="selectTimeSlot('${timeValue}')"]`);
            if (selectedBtn && selectedBtn.disabled) { return; }
            if (selectedBtn) {
                selectedBtn.classList.add('selected');
            }
            // Ensure duration is up-to-date before marking the span
            const durationHidden = form ? form.querySelector('#durationHidden') : null;
            if (durationHidden && typeof getSelectedDuration === 'function') {
                durationHidden.value = String(getSelectedDuration());
            }
            // Highlight the rest of the required span based on duration
            if (typeof markDurationSpan === 'function') markDurationSpan();
            
            checkAvailability();
            if (typeof updateSummary === 'function') updateSummary();
        }

        function fetchAndDisableTimeSlots() {
            const form = document.getElementById('appointmentForm');
            if (!form) return;
            const date = form.querySelector('input[name="date"]').value;
            const dentistSelect = form.querySelector('select[name="dentist_id"]');
            const dentistId = dentistSelect ? dentistSelect.value : ((document.getElementById('dentistHidden')||{}).value || '');
            const timeSlotsContainer = document.getElementById('timeSlotsContainer');
            // If inputs missing, show hint
            if (!date || !dentistId) {
                timeSlotsContainer.innerHTML = '<div class="col-span-3 text-sm text-gray-500">Select a date and dentist to view available times.</div>';
                return;
            }

            // Build full grid (7:00 to 18:55) and then mark booked slots in blue
            const params = new URLSearchParams();
            params.set('get_booked_slots', '1');
            params.set('date', date);
            params.set('dentist_id', dentistId);

            fetch('appointment_api.php?' + params.toString())
              .then(r=>r.json())
              .then(data=>{
                // If the selected dentist is on leave for this date, show message and stop.
                if (data && data.ok && data.leave === true) {
                  timeSlotsContainer.innerHTML = '<div class="col-span-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-3">Selected dentist is unavailable on this date (on leave). Please choose another date or dentist.</div>';
                  return;
                }
                const otherBooked = (data && data.ok && Array.isArray(data.other_booked_slots)) ? data.other_booked_slots : [];
                const userBooked  = (data && data.ok && Array.isArray(data.user_booked_slots)) ? data.user_booked_slots  : [];
                timeSlotsContainer.innerHTML = '';
                const frag = document.createDocumentFragment();
                const toHM = (h,m)=> `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
                const todayStr = new Date().toISOString().split('T')[0];
                const nowHM = toHM(new Date().getHours(), Math.floor(new Date().getMinutes()/5)*5);
                for (let h=7; h<=18; h++) {
                  for (let m=0; m<60; m+=5) {
                    if (h===18 && m>55) break;
                    const hm = toHM(h,m);
                    const d = new Date(); d.setHours(h); d.setMinutes(m||0);
                    const pretty = d.toLocaleTimeString([], {hour:'numeric', minute:'2-digit', hour12:true});
                    const parts = pretty.split(' ');
                    const timePart = parts.slice(0, parts.length - 1).join(' ');
                    const meridiem = (parts[parts.length - 1] || '').toUpperCase();
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'time-slot-btn px-3 py-2 text-sm';
                    btn.setAttribute('data-time', hm);
                    btn.innerHTML = '<span class="time-slot-time">'+timePart+'</span><span class="time-slot-meridiem">'+meridiem+'</span>';
                    btn.setAttribute('onclick', `selectTimeSlot('${hm}')`);
                    // Past times today -> disable and mark as user style
                    if (date === todayStr && hm <= nowHM) {
                      btn.disabled = true; btn.classList.add('disabled-user');
                    }
                    // Mark own holds/bookings
                    if (userBooked.includes(hm)) { btn.disabled = true; btn.classList.add('disabled-user'); }
                    // Mark other people's bookings (blue)
                    if (otherBooked.includes(hm)) { btn.disabled = true; btn.classList.add('disabled-other'); }
                    frag.appendChild(btn);
                  }
                }
                timeSlotsContainer.appendChild(frag);

                // Also disable any start times that cannot fit the current duration due to booked slots
                try {
                  const form = document.getElementById('appointmentForm');
                  const durationVal = form ? parseInt((form.querySelector('#durationHidden')||{value:'0'}).value||'0',10) : 0;
                  if (durationVal > 0) {
                    const bookedSet = new Set([...(otherBooked||[]), ...(userBooked||[])]);
                    const toMin = (hm)=>{ const [h,m] = (hm||'').split(':').map(v=>parseInt(v,10)); return (isNaN(h)||isNaN(m))?NaN:(h*60+m); };
                    const toHM = (mins)=>{ const h = Math.floor(mins/60), m = mins%60; return String(h).padStart(2,'0')+":"+String(m).padStart(2,'0'); };
                    timeSlotsContainer.querySelectorAll('button.time-slot-btn').forEach(btn=>{
                      if (btn.disabled) return; // keep booked/past disabled
                      const startHM = btn.getAttribute('data-time')||'';
                      const startM = toMin(startHM);
                      if (isNaN(startM)) return;
                      const endM = startM + durationVal;
                      let conflict = false;
                      for (let m = startM+5; m < endM; m+=5) {
                        if (bookedSet.has(toHM(m))) { conflict = true; break; }
                      }
                      if (conflict) {
                        btn.disabled = true;
                        btn.classList.add('disabled-span');
                      }
                    });
                  }
                } catch(e) {}

                // Re-apply chosen highlight
                const chosen = document.getElementById('timeHidden') ? document.getElementById('timeHidden').value : '';
                if (chosen) {
                  const btn = timeSlotsContainer.querySelector(`[onclick=\"selectTimeSlot('${chosen}')\"]`);
                  if (btn) { btn.classList.add('selected'); }
                }
                // Also mark the covered span based on current duration
                if (typeof markDurationSpan === 'function') markDurationSpan();
              })
              .catch(()=>{
                timeSlotsContainer.innerHTML = '<div class="col-span-3 text-sm text-red-600">Failed to load time slots.</div>';
              });
        }

        // Local fallback renderer for 5-minute slots (07:00 to 18:55)
        function renderLocalFiveMinuteSlots() {
            const form = document.getElementById('appointmentForm');
            if (!form) return;
            const date = form.querySelector('input[name="date"]').value;
            const container = document.getElementById('timeSlotsContainer');
            if (!date || !container) return;
            const frag = document.createDocumentFragment();
            const toHM = (h,m)=> `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
            // filter out past times if today
            const todayStr = new Date().toISOString().split('T')[0];
            const nowHM = toHM(new Date().getHours(), Math.floor(new Date().getMinutes()/5)*5);
            container.innerHTML = '';
            for (let h=7; h<=18; h++) {
                for (let m=0; m<60; m+=5) {
                    if (h===18 && m>55) break; // up to 18:55
                    const hm = toHM(h,m);
                    if (date===todayStr && hm <= nowHM) continue;
                    const d = new Date(); d.setHours(h); d.setMinutes(m);
                    const pretty = d.toLocaleTimeString([], { hour:'numeric', minute:'2-digit', hour12:true });
                    const parts = pretty.split(' ');
                    const timePart = parts.slice(0, parts.length - 1).join(' ');
                    const meridiem = (parts[parts.length - 1] || '').toUpperCase();
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'time-slot-btn px-3 py-2 text-sm';
                    btn.setAttribute('data-time', hm);
                    btn.innerHTML = '<span class="time-slot-time">'+timePart+'</span><span class="time-slot-meridiem">'+meridiem+'</span>';
                    btn.setAttribute('onclick', `selectTimeSlot('${hm}')`);
                    frag.appendChild(btn);
                }
            }
            container.appendChild(frag);
        }

        function checkAvailability() {
            const form = document.getElementById('appointmentForm');
            if (!form) return;
            const date = form.querySelector('input[name="date"]').value;
            const time = document.getElementById('timeHidden').value;
            const dentistSelect2 = form.querySelector('select[name="dentist_id"]');
            const dentistId = dentistSelect2 ? dentistSelect2.value : ((document.getElementById('dentistHidden')||{}).value || '');
            const hint = document.getElementById('availabilityHint');
            if (!hint) return;

            hint.textContent = '';
            hint.className = 'text-xs mt-1';
            if (!date || !time) return;

            const durationHidden = form.querySelector('#durationHidden');
            const durationVal = durationHidden ? parseInt(durationHidden.value||'0',10) : 0;

            const params = new URLSearchParams();
            params.set('availability', '1');
            params.set('date', date);
            params.set('time', time);
            if (dentistId) params.set('dentist_id', dentistId);
            if (durationVal>0) params.set('duration', String(durationVal));

            fetch('appointment_api.php?' + params.toString())
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.ok) return;
                    const clinic = data.clinicOverlaps || 0;
                    const dentist = data.dentistOverlaps || 0;
                    let color = 'text-green-600';
                    if (clinic >= 10 || dentist >= 2) color = 'text-red-600';
                    else if (clinic >= 5 || dentist >= 1) color = 'text-yellow-600';
                    hint.className = 'text-xs mt-1 ' + color;
                    hint.textContent = `Load: ${clinic} overlapping in clinic` + (dentist ? `, ${dentist} with selected dentist` : '');
                })
                .catch(() => {});

            // Cooldown info (pre-submit)
            const params2 = new URLSearchParams();
            params2.set('cooldown_info','1');
            params2.set('date', date);
            params2.set('time', time);
            if (dentistId) params2.set('dentist_id', dentistId);
            fetch('appointment_api.php?' + params2.toString())
              .then(r=>r.json())
              .then(info=>{
                if (!info || !info.ok) return;
                if (info.same_dentist_block) {
                  if (cooldownHint) { cooldownHint.textContent = info.message_same || 'Please wait 24 hours before booking with the same dentist.'; cooldownHint.className='text-xs mt-1 text-yellow-700'; }
                  if (submitBtn) { submitBtn.disabled = true; submitBtn.classList.add('opacity-60','cursor-not-allowed'); }
                } else if (info.other_dentist_notice) {
                  if (cooldownHint) { cooldownHint.textContent = info.message_other || 'Booking another dentist within 24 hours is allowed, but consider recovery time.'; cooldownHint.className='text-xs mt-1 text-blue-700'; }
                }
              })
              .catch(()=>{});
        }

        // Duration logic restored: total selected treatment minutes used for availability checks
        function getSelectedDuration() {
            const pills = Array.from(document.querySelectorAll('.treatment-btn.selected'));
            const lookup = (window && window.treatmentDurationLookup) ? window.treatmentDurationLookup : {};
            const seen = new Set();
            let total = 0;
            pills.forEach(b => {
                const name = (b.getAttribute('data-name')||'').trim();
                if (!name || seen.has(name)) return;
                seen.add(name);
                const min = parseInt(lookup[name]||0, 10);
                if (!isNaN(min) && min>0) total += min;
            });
            return total;
        }

        function markDurationSpan() {
            const container = document.getElementById('timeSlotsContainer');
            const form = document.getElementById('appointmentForm');
            if (!container || !form) return;
            const startHM = (document.getElementById('timeHidden') || { value: '' }).value;
            const durationVal = parseInt((form.querySelector('#durationHidden') || { value: '0' }).value || '0', 10) || 0;
            // Clear prior marks we control (do not touch booked/user-disabled)
            container.querySelectorAll('.time-slot-btn.selected-span').forEach(b=>b.classList.remove('selected-span'));
            container.querySelectorAll('.time-slot-btn.disabled-span').forEach(b=>{ b.disabled = false; b.classList.remove('disabled-span'); });
            if (!startHM || durationVal <= 5) return;
            const toMinutes = (hm)=>{ const [h,m] = hm.split(':').map(x=>parseInt(x,10)); return h*60 + m; };
            const startM = toMinutes(startHM);
            const endM = startM + durationVal;
            container.querySelectorAll('.time-slot-btn').forEach(btn => {
                const hm = btn.getAttribute('data-time') || '';
                if (!hm) return;
                const m = toMinutes(hm);
                // Mark anything after the selected start and up to and including the end boundary
                if (m > startM && m <= endM) {
                    btn.classList.add('selected-span');
                    btn.classList.add('disabled-span');
                    btn.disabled = true;
                }
            });
        }

        // New: select treatment via pill UI (supports multi-select)
        function selectTreatment(btnElem, name) {
            if (patientBlocked) { alert('Booking is disabled for your account.'); return; }
            const pills = Array.from(document.querySelectorAll('.treatment-btn'));
            const allBtn = pills.find(b => (b.getAttribute('data-name')||'') === 'All Treatments');
            const tHidden = document.getElementById('treatmentHidden');
            const listHidden = document.getElementById('treatmentListHidden');
            const legacySel = document.getElementById('treatment');
            const durationHidden = document.getElementById('durationHidden');

            if (!btnElem) return;
            if (btnElem.hasAttribute('data-disabled')) return;

            // If user clicks "All Treatments", select all named pills; otherwise toggle this pill
            if (name === 'All Treatments') {
                // Toggle All Treatments: if already selected, clear all; else select all
                const willSelectAll = !btnElem.classList.contains('selected');
                pills.forEach(b => b.classList.remove('selected'));
                if (willSelectAll) {
                    pills.forEach(b => { if (!b.hasAttribute('data-disabled')) b.classList.add('selected'); });
                }
            } else {
                // Toggle this pill
                btnElem.classList.toggle('selected');
                // If any specific selection is made, ensure All Treatments is not selected
                if (allBtn) allBtn.classList.remove('selected');
            }

            // Build selected names (exclude the label itself)
            let selectedNames = pills
                .filter(b => b.classList.contains('selected'))
                .map(b => b.getAttribute('data-name')||'')
                .filter(v => v);

            // If All Treatments is selected, expand to all configured names (exclude the label)
            if (selectedNames.includes('All Treatments')) {
                selectedNames = pills
                    .map(b => b.getAttribute('data-name')||'')
                    .filter(v => v && v !== 'All Treatments');
            }

            // Update hidden input fields: pipe-delimited list for server, and human-readable for treatment
            if (listHidden) listHidden.value = selectedNames.join('|');
            if (tHidden) tHidden.value = selectedNames.join(', ');

            // Update duration
            const durationHidden2 = document.getElementById('durationHidden');
            if (durationHidden2) durationHidden2.value = String(getSelectedDuration());

            // Re-evaluate combined inventory availability
            if (typeof applyCombinedInventoryGating === 'function') {
                applyCombinedInventoryGating();
            }

            // Legacy select no-op for multi-select

            // Trigger downstream recalcs
            if (typeof fetchAndDisableTimeSlots === 'function') fetchAndDisableTimeSlots();
            if (typeof markDurationSpan === 'function') markDurationSpan();
            checkAvailability();
            if (typeof updateSummary === 'function') updateSummary();
        }

        function selectDentist(btn, id) {
            document.querySelectorAll('#dentistsContainer .dentist-btn').forEach(b=>b.classList.remove('selected'));
            if (btn) btn.classList.add('selected');
            const h = document.getElementById('dentistHidden');
            if (h) h.value = String(id);
            if (typeof fetchAndDisableTimeSlots === 'function') fetchAndDisableTimeSlots();
            checkAvailability();
            if (typeof updateSummary === 'function') updateSummary();
        }

        function updateSummary() {
            const form = document.getElementById('appointmentForm');
            if (!form) return;
            const dentistId = (document.getElementById('dentistHidden') || { value: '' }).value;
            const dentistText = (()=>{
              const btn = document.querySelector('#dentistsContainer .dentist-btn.selected .dentist-name');
              return btn ? btn.textContent : '—';
            })();
            const dateVal = (form.querySelector('input[name="date"]') || { value: '—' }).value || '—';
            const timeVal = (document.getElementById('timeHidden') || { value: '' }).value || '—';
            const tHidden = document.getElementById('treatmentHidden');
            const treatmentsText = (tHidden && tHidden.value) ? tHidden.value : '—';
            // If we later add a summary bar, this function will populate it
        }

        function selectDate(selectedDate) {
            const today = new Date().toISOString().split('T')[0];
            if (selectedDate < today) {
                alert('Cannot book appointments for past dates. Please select today or a future date.');
                return;
            }
            
            const existingModal = document.getElementById('bookingModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            const modal = document.createElement('div');
            modal.id = 'bookingModal';
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.onclick = function(e) {
                if (e.target === modal) {
                    closeBookingForm();
                }
            };
            
            const modalContent = document.createElement('div');
            modalContent.className = 'modal-panel bg-white shadow-xl';
            modalContent.onclick = function(e) {
                e.stopPropagation();
            };
            
            const defaultActiveDentistId = (dentists.find(d=>Number(d.is_active)===1)||{}).id || '';
            let dentistOptions = '';
            dentists.forEach((dentist) => {
                const label = Number(dentist.is_active)===1 ? dentist.name : 'Unavailable';
                const sel = (dentist.id===defaultActiveDentistId) ? 'selected' : '';
                dentistOptions += `<option value="${dentist.id}" ${sel}>${label} - ${dentist.specialty}</option>`;
            });
            
            // Build treatment pills grid HTML
            let treatmentPills = '';
            // Optional All Treatments button
            treatmentPills += `
                <button type="button" class="treatment-btn time-slot-btn px-3 py-2 text-sm" data-name="All Treatments" title="All Treatments — Multiple procedures" onclick="selectTreatment(this, 'All Treatments')">
                    <span class="time-slot-time">All Treatments</span>
                    <span class="time-slot-meridiem">Treatment</span>
                </button>`;

            const durationMap = {
                'Oral Prophylaxis (Cleaning)': '45 minutes',
                'Tooth Restoration': '60 minutes',
                'Oral Surgery': '60 minutes',
                'Dentures': '60 minutes (per fitting session)',
                'Panoramic X-Ray (X-Ray)': '15–20 minutes',
                'Full Braces': '120 minutes (installation)',
                'Upper Braces': '90 minutes',
                'Lower Braces': '90 minutes',
                'Brace Adjustment': '30 minutes',
                'Teeth Whitening': '60 minutes',
                'Check-Up': '15 minutes'
            };

            const apiTreatments = Array.isArray(treatments) ? treatments : [];
            const apiByName = new Map(apiTreatments.map(x => [String(x.name||''), x]));
            const ensuredFromMap = Object.keys(durationMap).map(name => apiByName.get(name) || { name });
            const combinedTreatments = apiTreatments.concat(ensuredFromMap.filter(x => !apiByName.has(x.name)));

            const durationLookup = {};
            const treatmentNames = [];
            const combinedFiltered = combinedTreatments;
            combinedFiltered.forEach(t => {
                const name = String(t.name || '').trim();
                if (!name) return;
                const safeName = name.replace(/'/g, "\\'");
                const titleTxt = name.replace(/\"/g,'&quot;');
                let sub = '';
                let minutes = 0;
                if (typeof t.duration === 'number' && t.duration > 0) {
                    minutes = t.duration;
                    sub = `${t.duration} min`;
                } else if (typeof t.duration_text === 'string' && t.duration_text.trim() !== '') {
                    sub = t.duration_text.trim();
                } else {
                    sub = durationMap[name] || 'Treatment';
                }
                if (!minutes && sub) {
                    const nums = (sub.match(/\d+/g) || []).map(n=>parseInt(n,10)).filter(n=>!isNaN(n));
                    if (nums.length) minutes = Math.max(...nums);
                }
                durationLookup[name] = minutes || 0;
                treatmentNames.push(name);
                treatmentPills += `
                    <button type="button" class="treatment-btn time-slot-btn px-3 py-2 text-sm" data-name="${name}" title="${titleTxt}" onclick="selectTreatment(this, '${safeName}')">
                        <span class=\"time-slot-time\">${name}</span>
                        <span class=\"time-slot-meridiem\">${sub}</span>
                    </button>`;
            });
            window.treatmentDurationLookup = durationLookup;
            window.treatmentNames = treatmentNames;

            // Combined inventory gating across multiple selected treatments
            window.applyCombinedInventoryGating = function(){
                try {
                    const info = (window.__treatmentInventoryStatus || {});
                    const itemsByTreatment = info.items || {};
                    const pills = Array.from(document.querySelectorAll('#treatmentsContainer .treatment-btn[data-name]'));
                    if (!pills.length) return;
                    // Helper: build aggregated needs for a set of treatment names
                    const buildNeeds = (names)=>{
                        const needs = new Map(); // item_name -> {need, stock, matched}
                        names.forEach(nm => {
                            const rows = itemsByTreatment[nm] || [];
                            rows.forEach(r => {
                                const item = String(r.item_name || '').trim();
                                const need = parseInt(r.quantity_needed || r.Quantity_Used || 0, 10) || 0;
                                const stock = (r.current_stock !== undefined && r.current_stock !== null) ? parseInt(r.current_stock,10) : null;
                                const matched = (typeof r.matched !== 'undefined') ? !!r.matched : (stock !== null);
                                if (!item || !need) return;
                                const cur = needs.get(item) || {need:0, stock:stock, matched:matched};
                                cur.need += need;
                                if (stock !== null) { cur.stock = stock; }
                                if (matched) { cur.matched = true; }
                                needs.set(item, cur);
                            });
                        });
                        return needs;
                    };
                    const selected = pills.filter(b=>b.classList.contains('selected')).map(b=>b.getAttribute('data-name')).filter(Boolean);
                    const baseNeeds = buildNeeds(selected);
                    // Evaluate each pill's availability when combined with current selection
                    pills.forEach(btn => {
                        const nm = btn.getAttribute('data-name') || '';
                        if (!nm || nm === 'All Treatments') return;
                        // Clear prior badge/state unless hard-disabled by server availability
                        const hard = btn.hasAttribute('data-hard-disabled');
                        const wasExpired = btn.hasAttribute('data-expired');
                        if (!hard) {
                          btn.classList.remove('disabled-user');
                          btn.removeAttribute('data-disabled');
                          const oldBadge = btn.querySelector('.no-stock-badge');
                          if (oldBadge) oldBadge.remove();
                        }
                        // Build combined needs (avoid double-counting when already selected)
                        const together = new Set(selected);
                        together.add(nm);
                        const combined = buildNeeds(Array.from(together));
                        let insufficient = false;
                        combined.forEach((v, key) => {
                            if (v.matched && typeof v.stock === 'number') {
                                if (v.stock < v.need) insufficient = true;
                            }
                        });
                        if (insufficient || hard) {
                            btn.classList.add('disabled-user');
                            btn.setAttribute('data-disabled','1');
                            if (!btn.querySelector('.no-stock-badge')) {
                                const badge = document.createElement('span');
                                badge.className = 'no-stock-badge';
                                badge.textContent = wasExpired ? 'Unavailable (expired)' : 'Unavailable';
                                badge.style.fontSize = '10px';
                                badge.style.marginTop = '4px';
                                badge.style.padding = '2px 6px';
                                badge.style.borderRadius = '9999px';
                                badge.style.background = '#fee2e2';
                                badge.style.color = '#991b1b';
                                badge.style.border = '1px solid #fecaca';
                                btn.appendChild(badge);
                            }
                        }
                    });

                    // Disable 'All Treatments' when any treatment is out of stock OR
                    // when the combined needs of ALL treatments exceed stock for any item
                    const allBtn = pills.find(b => (b.getAttribute('data-name')||'') === 'All Treatments');
                    if (allBtn) {
                        // Clear prior state
                        allBtn.classList.remove('disabled-user');
                        allBtn.removeAttribute('data-disabled');
                        const old = allBtn.querySelector('.no-stock-badge'); if (old) old.remove();

                        // If any pill already disabled -> disable All
                        let anyDisabled = pills.some(b => b !== allBtn && b.classList.contains('disabled-user'));

                        // Also compute combined needs across ALL treatments
                        const allNames = pills
                          .filter(b=>b!==allBtn)
                          .map(b=>b.getAttribute('data-name'))
                          .filter(Boolean);
                        const needsAll = buildNeeds(allNames);
                        let combinedInsufficient = false;
                        needsAll.forEach((v)=>{ if (v.matched && typeof v.stock==='number' && v.stock < v.need) combinedInsufficient = true; });

                        if (anyDisabled || combinedInsufficient) {
                            allBtn.classList.add('disabled-user');
                            allBtn.setAttribute('data-disabled','1');
                            const badge = document.createElement('span');
                            badge.className = 'no-stock-badge';
                            badge.textContent = 'Unavailable';
                            badge.style.fontSize = '10px';
                            badge.style.marginTop = '4px';
                            badge.style.padding = '2px 6px';
                            badge.style.borderRadius = '9999px';
                            badge.style.background = '#fee2e2';
                            badge.style.color = '#991b1b';
                            badge.style.border = '1px solid #fecaca';
                            allBtn.appendChild(badge);
                        }
                    }
                } catch(e) {}
            };
            
            modalContent.innerHTML = `
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-800">Book Appointment</h3>
                        <button type="button" onclick="closeBookingForm()" class="text-gray-500 hover:text-gray-700 text-3xl font-bold leading-none">&times;</button>
                    </div>
                    
                    <form method="post" id="appointmentForm" class="grid grid-cols-1 md:grid-cols-2 gap-4" onsubmit="return validateTreatmentSelection()">
                        <div>
                            <div class="step-title mb-2"><span class="step-badge">1</span> Choose Dentist</div>
                            <div id="dentistsContainer" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                ${dentists.map((d)=>{
                                    const active = Number(d.is_active)===1;
                                    const sel = active && d.id===defaultActiveDentistId ? 'selected' : '';
                                    const label = active ? d.name : 'Unavailable';
                                    const extraCls = active ? '' : ' opacity-60 cursor-not-allowed';
                                    const disabledAttr = active ? '' : ' disabled';
                                    return `
                                    <button type=\"button\" class=\"dentist-btn${extraCls} ${sel}\" data-id=\"${d.id}\" onclick=\"${active?`selectDentist(this, ${d.id})`:'return false;'}\"${disabledAttr}>\n                                        <div class=\\\"dentist-name\\\">${label}</div>\n                                        <div class=\\\"dentist-meta\\\">${d.specialty}</div>\n                                    </button>`;
                                }).join('')}
                            </div>
                            <input type="hidden" name="dentist_id" id="dentistHidden" value="${defaultActiveDentistId}">
                            <input type="hidden" name="date" value="${selectedDate}">
                        </div>
                        <div class="md:col-span-2">
                            <div class="step-title mb-2"><span class="step-badge">2</span> Select Treatment</div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 gap-3 max-h-60 overflow-y-auto overflow-x-hidden border rounded-lg p-3 bg-gray-50" id="treatmentsContainer">
                                ${treatmentPills}
                            </div>
                            <!-- Hidden fields used by server and compatibility functions -->
                            <input type="hidden" name="treatment" id="treatmentHidden" value="">
                            <input type="hidden" name="treatment_list" id="treatmentListHidden" value="">
                            <input type="hidden" name="duration" id="durationHidden" value="0">
                            <!-- Keep a hidden select for legacy compatibility (not visible) -->
                            <select id="treatment" class="hidden"></select>
                        </div>
                        <div class="md:col-span-2">
                            <div class="step-title mb-2"><span class="step-badge">3</span> Pick a Time</div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 max-h-56 overflow-y-auto border rounded-lg p-3 bg-gray-50" id="timeSlotsContainer">
                                <div class="col-span-3 text-sm text-gray-500">Select a date and dentist to view available times.</div>
                            </div>
                            <input type="hidden" name="time" id="timeHidden" value="" required>
                            <p class="text-xs text-gray-500 mt-1">Clinic hours: 7:00 AM - 7:00 PM (5-minute intervals)</p>
                            <p id="availabilityHint" class="text-xs mt-1"></p>
                        </div>
                        
                        <div class="md:col-span-2 flex gap-4 mt-4">
                            <button type="button" onclick="closeBookingForm()" class="flex-1 bg-gray-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-600 transition-colors">Cancel</button>
                            <button type="submit" name="book" class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">Book Appointment</button>
                        </div>
                    </form>
                </div>
            `;
            
            modal.appendChild(modalContent);
            document.body.appendChild(modal);
            // Immediately load available time slots for the preselected dentist and selected date
            if (typeof fetchAndDisableTimeSlots === 'function') {
                fetchAndDisableTimeSlots();
            }
            // Disable unavailable treatments based on inventory
            (function(){
              try {
                const names = (window.treatmentNames||[]).join('|');
                if (!names) return;
                fetch('appointment_api.php?treatments_availability=1&names=' + encodeURIComponent(names))
                  .then(r=>r.json()).then(data=>{
                    if (!data || !data.ok) return;
                    const avail = data.availability || {};
                    window.__treatAvail = avail;
                    const pills = Array.from(document.querySelectorAll('#treatmentsContainer .treatment-btn'));
                    pills.forEach(btn=>{
                      const nm = (btn.getAttribute('data-name')||'').trim();
                      if (!nm || nm === 'All Treatments') return;
                      const a = avail[nm];
                      if (a && a.clickable === false) {
                        btn.classList.add('disabled-user');
                        btn.setAttribute('data-disabled','1');
                        btn.setAttribute('data-hard-disabled','1');
                        if (a.expired) { btn.setAttribute('data-expired','1'); }
                        const isExpired = !!a.expired;
                        btn.title = (btn.title||nm) + (isExpired ? ' — Unavailable (expired)' : ' — Unavailable (insufficient inventory)');
                        // Add visible Unavailable badge
                        if (!btn.querySelector('.no-stock-badge')) {
                          const badge = document.createElement('span');
                          badge.className = 'no-stock-badge';
                          badge.textContent = isExpired ? 'Unavailable (expired)' : 'Unavailable';
                          badge.style.fontSize = '10px';
                          badge.style.marginTop = '4px';
                          badge.style.padding = '2px 6px';
                          badge.style.borderRadius = '9999px';
                          badge.style.background = '#fee2e2';
                          badge.style.color = '#991b1b';
                          badge.style.border = '1px solid #fecaca';
                          btn.appendChild(badge);
                        }
                        // Keep existing selection highlighted to avoid UI "vanishing"; submission will still be blocked by validateTreatmentSelection when needed.
                      }
                    });
                  }).catch(()=>{});
                // Also fetch detailed inventory status for combined gating
                try {
                  const names2 = (window.treatmentNames||[]).join('|');
                  if (names2) {
                    fetch('appointment_api.php?treatments_inventory_status=1&names=' + encodeURIComponent(names2))
                      .then(r=>r.json())
                      .then(info=>{ window.__treatmentInventoryStatus = info || null; if (typeof applyCombinedInventoryGating === 'function') applyCombinedInventoryGating(); })
                      .catch(()=>{});
                  }
                } catch(e) {}
              } catch(e) {}
            })();
            
            const handleKeyPress = function(e) {
                if (e.key === 'Escape') {
                    closeBookingForm();
                    document.removeEventListener('keydown', handleKeyPress);
                }
            };
            document.addEventListener('keydown', handleKeyPress);
            
            modal.style.opacity = '0';
            modalContent.style.transform = 'scale(0.9)';
            modalContent.style.transition = 'transform 0.2s ease-out';
            
            setTimeout(() => {
                modal.style.transition = 'opacity 0.2s ease-out';
                modal.style.opacity = '1';
                modalContent.style.transform = 'scale(1)';
            }, 10);
            // Initialize duration hidden value on open
            const form = document.getElementById('appointmentForm');
            if (form) {
                const durationHidden = form.querySelector('#durationHidden');
                if (durationHidden) {
                    durationHidden.value = String(getSelectedDuration());
                }
                const dateInput = form.querySelector('input[name="date"]');
                const applyPastDisable = () => disablePastTimes(dateInput.value);
                if (dateInput) { 
                    dateInput.addEventListener('change', () => {
                        applyPastDisable();
                        fetchAndDisableTimeSlots();
                    }); 
                    applyPastDisable(); 
                }
                // Initial fetch for booked slots
                fetchAndDisableTimeSlots();

                // Disable dentists that are on leave for the selected date
                (async () => {
                  try {
                    const dateVal = (form.querySelector('input[name="date"]')||{value:''}).value;
                    const container = document.getElementById('dentistsContainer');
                    const hiddenDent = document.getElementById('dentistHidden');
                    if (!dateVal || !container) return;
                    const btns = Array.from(container.querySelectorAll('button.dentist-btn'));
                    let firstAvailableId = null;
                    for (const btn of btns) {
                      const id = btn.getAttribute('data-id');
                      const isDisabled = btn.hasAttribute('disabled');
                      if (!id || isDisabled) continue;
                      const p = new URLSearchParams();
                      p.set('get_booked_slots','1');
                      p.set('date', dateVal);
                      p.set('dentist_id', id);
                      const res = await fetch('appointment_api.php?' + p.toString());
                      const data = await res.json();
                      if (data && data.ok && data.leave === true) {
                        // Mark this dentist as unavailable (on leave)
                        btn.setAttribute('disabled','disabled');
                        btn.classList.add('opacity-60','cursor-not-allowed','on-leave');
                        // Update label to reflect leave state
                        const nameEl = btn.querySelector('.dentist-name');
                        if (nameEl && !nameEl.textContent.includes('(On Leave)')) {
                          nameEl.textContent = nameEl.textContent + ' (On Leave)';
                        }
                        // If this was selected, unselect and clear hidden field
                        if (btn.classList.contains('selected')) {
                          btn.classList.remove('selected');
                          if (hiddenDent && hiddenDent.value === String(id)) {
                            hiddenDent.value = '';
                          }
                        }
                      } else {
                        // Track a first available dentist
                        if (!firstAvailableId) firstAvailableId = id;
                      }
                    }
                    // If no dentist selected after disabling, select the first available
                    const currentVal = hiddenDent ? hiddenDent.value : '';
                    if ((!currentVal || currentVal === '') && firstAvailableId) {
                      const firstBtn = container.querySelector(`button.dentist-btn[data-id="${firstAvailableId}"]`);
                      if (firstBtn && !firstBtn.hasAttribute('disabled')) {
                        firstBtn.classList.add('selected');
                        if (hiddenDent) hiddenDent.value = String(firstAvailableId);
                        if (typeof fetchAndDisableTimeSlots === 'function') fetchAndDisableTimeSlots();
                      }
                    }
                  } catch(e) { /* ignore */ }
                })();
            }

            // If admin, show quick manage link
            try {
                if (isAdmin) {
                    var hook = document.getElementById('manageTreatmentsHook');
                    if (hook) {
                        hook.innerHTML = '<a href="admin_treatments.php" target="_blank" class="text-xs text-blue-600 underline ml-2" title="Manage treatments (opens in new tab)">Manage</a>';
                    }
                }
            } catch(e) {}
        }

        // Disable past time buttons if booking for today
        function disablePastTimes(dateStr) {
            const container = document.getElementById('timeSlotsContainer');
            if (!container) return;
            const todayStr = new Date().toISOString().split('T')[0];
            // Enable all by default
            container.querySelectorAll('button.time-slot-btn').forEach(btn => {
                btn.disabled = false;
                btn.classList.remove('opacity-50','cursor-not-allowed');
            });
            if (dateStr !== todayStr) return;
            const now = new Date();
            const nowHM = now.toTimeString().slice(0,5); // HH:MM
            container.querySelectorAll('button.time-slot-btn').forEach(btn => {
                const onclk = btn.getAttribute('onclick') || '';
                const m = onclk.match(/selectTimeSlot\('([0-9:]{4,5})'\)/);
                if (!m) return;
                const hm = m[1];
                if (hm < nowHM) {
                    btn.disabled = true;
                    btn.classList.add('opacity-50','cursor-not-allowed');
                }
            });
        }

        function closeBookingForm() {
            const modal = document.getElementById('bookingModal');
            if (modal) {
                modal.style.transition = 'opacity 0.2s ease-out';
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.remove();
                }, 200);
            }
        }

        function confirmCancel(appointmentId) {
            if (!confirm('Cancel this appointment?')) { return false; }
            fetch('appointment_api.php?cancel_id=' + appointmentId + '&ajax=1')
                .then(r => r.json())
                .then(data => {
                    if (data && data.ok) {
                        location.reload();
                    } else {
                        alert('Cancellation failed.');
                    }
                })
                .catch(() => {
                    window.location.href = 'appointment.php?cancel_id=' + appointmentId;
                });
            return false;
        }

        function closeAlert() {
            document.querySelectorAll('.fixed.inset-0.z-50').forEach(e => e.remove());
            document.querySelectorAll('.fixed.inset-0.bg-black').forEach(e => e.remove());
        }

        // ---------- Patient Reschedule Modal (limit 2 enforced server-side) ----------
        function openRescheduleModal(apptId, dentistId, duration, currentDate, currentTime) {
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            overlay.onclick = (e) => { if (e.target === overlay) document.body.removeChild(overlay); };

            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg shadow-xl w-full max-w-xl mx-4 p-6';
            card.innerHTML = `
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold">Reschedule Appointment</h3>
                    <button class="text-gray-500 hover:text-gray-700 text-2xl leading-none" onclick="document.body.removeChild(this.closest('.fixed.inset-0'))">&times;</button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                        <input type="date" id="reschedDate" class="w-full border rounded-lg px-3 py-2" min="${new Date().toISOString().split('T')[0]}" value="${currentDate}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Time</label>
                        <div id="reschedTimeSlotsContainer" class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto border rounded-lg p-3 bg-gray-50">
                            <div class="col-span-2 text-sm text-gray-500">Select a date to view available times.</div>
                        </div>
                        <input type="hidden" id="reschedTimeHidden" value="${currentTime}">
                        <p class="text-xs text-gray-500 mt-1">Clinic hours: 7:00 AM - 7:00 PM</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason (optional)</label>
                        <input type="text" id="reschedReason" class="w-full border rounded-lg px-3 py-2" placeholder="Why are you rescheduling?">
                    </div>
                </div>
                <div class="flex gap-3 mt-6 justify-end">
                    <button class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600" onclick="document.body.removeChild(this.closest('.fixed.inset-0'))">Close</button>
                    <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" onclick="submitReschedule(${apptId}, ${dentistId}, ${duration})">Save</button>
                </div>
            `;

            overlay.appendChild(card);
            document.body.appendChild(overlay);

            const dateInput = card.querySelector('#reschedDate');
            const timeContainer = card.querySelector('#reschedTimeSlotsContainer');

            const fetchSlots = () => {
                if (!dateInput.value) { timeContainer.innerHTML = '<div class="col-span-2 text-sm text-gray-500">Select a date to view available times.</div>'; return; }
                timeContainer.innerHTML = '<div class="col-span-2 text-sm text-gray-500">Loading available times...</div>';
                const p = new URLSearchParams();
                p.set('slots', '1');
                p.set('date', dateInput.value);
                p.set('dentist_id', String(dentistId));
                fetch('appointment_api.php?' + p.toString())
                  .then(r=>r.json())
                  .then(data => {
                      let slots = (data && data.ok) ? (data.slots || []) : [];
                      // Filter out past times if selected date is today
                      const todayStr = new Date().toISOString().split('T')[0];
                      if (dateInput.value === todayStr) {
                          const nowHM = new Date().toTimeString().slice(0,5);
                          slots = slots.filter(hm => hm >= nowHM);
                      }
                      if (slots.length === 0) { timeContainer.innerHTML = '<div class="col-span-2 text-sm text-gray-500">No available time slots for the selected date.</div>'; return; }
                      timeContainer.innerHTML = '';
                      const frag = document.createDocumentFragment();
                      slots.forEach(hm => {
                          const [h,m] = hm.split(':').map(n=>parseInt(n,10));
                          const d = new Date(); d.setHours(h); d.setMinutes((m||0));
                          const label = d.toLocaleTimeString([], {hour:'numeric', minute:'2-digit'});
                          const btn = document.createElement('button');
                          btn.type = 'button';
                          btn.className = 'time-slot-btn px-3 py-2 text-sm rounded border bg-white hover:bg-blue-50 hover:border-blue-300';
                          btn.textContent = label;
                          btn.onclick = () => {
                              card.querySelector('#reschedTimeHidden').value = hm;
                              // highlight selection
                              timeContainer.querySelectorAll('button').forEach(b=>b.classList.remove('selected'));
                              btn.classList.add('selected');
                          };
                          // Preselect current time if matches
                          const currentTimeVal = (card.querySelector('#reschedTimeHidden') || { value: '' }).value;
                          if (currentTimeVal && currentTimeVal === hm) {
                              btn.classList.add('selected');
                          }
                          frag.appendChild(btn);
                      });
                      timeContainer.appendChild(frag);
                  })
                  .catch(()=>{ timeContainer.innerHTML = '<div class="col-span-2 text-sm text-red-600">Failed to load time slots.</div>'; });
            };

            dateInput.addEventListener('change', fetchSlots);
            // initial load
            fetchSlots();

            return false;
        }

        function submitReschedule(apptId, dentistId, duration) {
            const modal = document.querySelector('.fixed.inset-0');
            if (!modal) return;
            const date = modal.querySelector('#reschedDate').value;
            const time = modal.querySelector('#reschedTimeHidden').value;
            const reason = modal.querySelector('#reschedReason').value || '';
            if (!date || !time) { alert('Please select date and time.'); return; }
            const fd = new FormData();
            fd.set('action','reschedule');
            fd.set('appointment_id', String(apptId));
            fd.set('date', date);
            fd.set('time', time);
            fd.set('duration', String(duration || 60));
            fd.set('reason', reason);
            fetch('appointment_api.php', { method:'POST', body: fd })
              .then(r=>r.json())
              .then(data=>{
                  if (!data || !data.ok) { alert(data && data.error ? data.error : 'Reschedule failed'); return; }
                  alert('Reschedule submitted successfully.');
                  window.location.reload();
              })
              .catch(()=>alert('Reschedule failed'));
        }

        // ---------- Patient Cancel With Reason Modal ----------
        function openCancelReasonModal(apptId) {
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            overlay.onclick = (e) => { if (e.target === overlay) document.body.removeChild(overlay); };

            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 p-6';
            card.innerHTML = `
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold">Cancel Appointment</h3>
                    <button class="text-gray-500 hover:text-gray-700 text-2xl leading-none" onclick="document.body.removeChild(this.closest('.fixed.inset-0'))">&times;</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                        <select id="cancelReason" class="w-full border rounded-lg px-3 py-2">
                            <option value="">Select a reason</option>
                            <option>Feeling unwell</option>
                            <option>Schedule conflict</option>
                            <option>Transportation issue</option>
                            <option>Financial constraints</option>
                            <option>Found an earlier slot</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                        <textarea id="cancelNotes" class="w-full border rounded-lg px-3 py-2" rows="3" placeholder="Provide more details (optional)"></textarea>
                    </div>
                </div>
                <div class="flex gap-3 mt-6 justify-end">
                    <button class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600" onclick="document.body.removeChild(this.closest('.fixed.inset-0'))">Close</button>
                    <button class="px-4 py-2 rounded bg-orange-600 text-white hover:bg-orange-700" onclick="submitCancelWithReason(${apptId})">Confirm Cancel</button>
                </div>
            `;

            overlay.appendChild(card);
            document.body.appendChild(overlay);
            return false;
        }

        function submitCancelWithReason(apptId) {
            const modal = document.querySelector('.fixed.inset-0');
            if (!modal) return;
            const reason = modal.querySelector('#cancelReason').value;
            const notes = modal.querySelector('#cancelNotes').value || '';
            if (!reason) { alert('Please select a reason.'); return; }
            const fd = new FormData();
            fd.set('action','cancel_with_reason');
            fd.set('appointment_id', String(apptId));
            fd.set('reason', reason);
            fd.set('notes', notes);
            fetch('appointment_api.php', { method:'POST', body: fd })
              .then(r=>r.json())
              .then(data=>{
                  if (!data || !data.ok) { alert(data && data.error ? data.error : 'Cancellation failed'); return; }
                  alert('Appointment cancelled.');
                  window.location.reload();
              })
              .catch(()=>alert('Cancellation failed'));
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 flex-grow">
    <button id="menuToggle" class="hamburger" aria-label="Toggle menu">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
      Menu
    </button>
    <div id="drawerBackdrop" class="backdrop"></div>
    <div class="flex min-h-screen">
        <?php include 'patient_sidebar.php'; ?>
 
         <!-- Main content -->
         <main class="flex-1 p-4 md:p-8 space-y-8">
             <div id="alertContainer"><?= $alert ?></div>
             <script>
               setTimeout(function(){
                 var c = document.getElementById('alertContainer');
                 if (c) { c.innerHTML = ''; }
               }, 5000);
             </script>
            
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-3xl font-semibold text-blue-700">All Appointments</h1>
                <button onclick="location.reload()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Refresh
                </button>
            </div>

            <!-- Availability Calendar -->
            <div class="bg-white shadow rounded-xl p-6">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-3 calendar-header">
                    <h2 class="text-xl font-semibold">Availability Calendar</h2>
                    <div class="space-x-2 calendar-controls flex items-center">
                        <?php
                            $prevMonthTs = strtotime('-1 month', strtotime(sprintf('%04d-%02d-01', $calendarData['year'], $calendarData['month'])));
                            $nextMonthTs = strtotime('+1 month', strtotime(sprintf('%04d-%02d-01', $calendarData['year'], $calendarData['month'])));
                            $prevY = intval(date('Y', $prevMonthTs));
                            $prevM = intval(date('n', $prevMonthTs));
                            $nextY = intval(date('Y', $nextMonthTs));
                            $nextM = intval(date('n', $nextMonthTs));
                        ?>
                        <a class="px-3 py-1 border rounded hover:bg-gray-50" href="appointment.php?year=<?= $prevY ?>&month=<?= $prevM ?>">Prev</a>
                        <span class="px-3 py-1 font-medium"><?= date('F Y', strtotime(sprintf('%04d-%02d-01', $calendarData['year'], $calendarData['month']))) ?></span>
                        <a class="px-3 py-1 border rounded hover:bg-gray-50" href="appointment.php?year=<?= $nextY ?>&month=<?= $nextM ?>">Next</a>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4">Click on any date to book an appointment</p>
                <div class="grid grid-cols-7 gap-0 text-center text-sm calendar-grid">
                    <?php
                        $weekdays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                        foreach ($weekdays as $wd) {
                            echo '<div class="font-semibold text-gray-600 day-label">'.$wd.'</div>';
                        }

                        $firstDow = intval(date('w', strtotime(sprintf('%04d-%02d-01', $calendarData['year'], $calendarData['month']))));
                        $daysInMonth = intval(date('t', strtotime(sprintf('%04d-%02d-01', $calendarData['year'], $calendarData['month']))));

                        // leading blanks
                        for ($i = 0; $i < $firstDow; $i++) {
                            echo '<div class="p-3 border rounded bg-gray-50"></div>';
                        }

                        for ($d = 1; $d <= $daysInMonth; $d++) {
                            $dstr = sprintf('%04d-%02d-%02d', $calendarData['year'], $calendarData['month'], $d);
                            $count = $calendarData['dailyCounts'][$dstr] ?? 0;
                            $colorClass = 'bg-green-200 text-green-800';
                            if ($count >= $highThreshold) { $colorClass = 'bg-red-100 text-red-800'; }
                            elseif ($count >= $lowThreshold) { $colorClass = 'bg-yellow-100 text-yellow-800'; }
                            $label = '<span class="inline-block">'.$count.'</span> <span class="inline-block">clients</span>';
                            
                            $today = date('Y-m-d');
                            $isPastDate = $dstr < $today;
                            
                            if ($isPastDate) {
                                echo '<div class="block p-3 day-cell border rounded bg-gray-100 text-gray-400 cursor-not-allowed opacity-50" title="Past date - cannot book appointments"><div>'.$d.'</div><div class="text-xs mt-1 font-semibold">'.$label.'</div></div>';
                            } else {
                                echo '<div onclick="selectDate(\''.$dstr.'\')" class="block p-3 day-cell border rounded '.$colorClass.' cursor-pointer hover:opacity-80 transition-opacity" title="Click to book appointment on '.$dstr.'"><div>'.$d.'</div><div class="text-xs mt-1 font-semibold">'.$label.'</div></div>';
                            }
                        }
                    ?>
                </div>
                <div class="flex items-center gap-4 mt-4 text-sm">
                    <div class="flex items-center gap-2"><span class="w-4 h-4 bg-green-100 inline-block border rounded"></span> Less</div>
                    <div class="flex items-center gap-2"><span class="w-4 h-4 bg-yellow-100 inline-block border rounded"></span> Moderate</div>
                    <div class="flex items-center gap-2"><span class="w-4 h-4 bg-red-100 inline-block border rounded"></span> Full</div>
                    <div class="flex items-center gap-2"><span class="w-4 h-4 bg-gray-100 inline-block border rounded opacity-50"></span> Past Date</div>
                </div>
            </div>

            <!-- All Appointments List -->
            <div id="appointments" class="bg-white shadow rounded-xl p-6 mt-6">
                <h2 class="text-xl font-semibold mb-4">Active Appointments</h2>
                <?php if (count($appts) > 0): ?>
                    <div class="mb-4 p-3 bg-blue-50 rounded-lg">
                        <p class="text-sm text-blue-700">
                            <strong>Note:</strong> Pending appointments are awaiting admin confirmation. You can cancel appointments if needed. Completed appointments will automatically disappear from this view.
                        </p>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($appts as $appt): ?>
                            <div class="flex flex-col w-full md:flex-row md:items-center justify-between <?= $appt['Status'] === 'Pending' ? 'bg-orange-50 border-l-4 border-orange-400' : 'bg-blue-50' ?> rounded-lg p-4 md:p-5 shadow-sm">
                                <div class="flex items-start space-x-3 flex-1 flex-wrap">
                                    <div class="<?= $appt['Status'] === 'Pending' ? 'bg-orange-100' : 'bg-blue-100' ?> p-3 rounded-full">
                                        <svg class="w-7 h-7 <?= $appt['Status'] === 'Pending' ? 'text-orange-600' : 'text-blue-600' ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div>
                                        <div class="font-bold text-lg text-gray-800"><?= htmlspecialchars($appt['Procedure']) ?></div>
                                        <div class="text-sm text-gray-600">Dr. <?= htmlspecialchars($appt['Dentist_Name'] ?? 'N/A') ?></div>
                                        <div class="flex items-center text-gray-500 text-sm mt-1">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <?php 
                                                $apptDate = strtotime($appt['Date']);
                                                $isPast = $apptDate < strtotime('today');
                                                $dateClass = $isPast ? 'text-red-500' : 'text-gray-500';
                                            ?>
                                            <span class="<?= $dateClass ?>">
                                                <?= htmlspecialchars(date('M j, Y', $apptDate)) ?> at <?= htmlspecialchars(date('g:i A', strtotime($appt['Time']))) ?>
                                                <?= $isPast ? ' (Past)' : '' ?>
                                            </span>
                                        </div>
                                        
                                        <div class="flex items-center text-gray-500 text-sm mt-1">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 12.414a8 8 0 111.414-1.414l4.243 4.243a1 1 0 01-1.414 1.414z"/></svg>
                                            <?php $__dur = isset($appt['Duration']) ? (int)$appt['Duration'] : 0; if ($__dur > 0): ?>
                                              <?= htmlspecialchars($__dur) ?> minutes
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center flex-wrap gap-2 mt-3 md:mt-0 md:justify-end">
                                    <!-- Status Badge -->
                                    <?php if ($appt['Status'] === 'Confirmed'): ?>
                                        <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-semibold">✅ Confirmed</span>
                                    <?php elseif ($appt['Status'] === 'Pending'): ?>
                                        <span class="bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs font-semibold border border-orange-300">⏳ Pending</span>
                                    <?php elseif ($appt['Status'] === 'Booked'): ?>
                                        <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-semibold">📅 Booked</span>
                                    <?php else: ?>
                                        <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-xs font-semibold"><?= htmlspecialchars($appt['Status']) ?></span>
                                    <?php endif; ?>
                                    
                                    <!-- Reschedule Button -->
                                    <button class="text-blue-600 hover:text-blue-800 p-2 rounded hover:bg-blue-50 transition-colors" title="Reschedule appointment" onclick="return openRescheduleModal(<?= (int)$appt['id'] ?>, <?= (int)$appt['Dentist_Id'] ?>, <?= (int)($appt['Duration'] ?? 0) ?>, '<?= htmlspecialchars($appt['Date'], ENT_QUOTES) ?>', '<?= htmlspecialchars(substr($appt['Time'],0,5), ENT_QUOTES) ?>')">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3M12 22a10 10 0 100-20 10 10 0 000 20z"/></svg>
                                    </button>

                                    <!-- Cancel With Reason Button -->
                                    <button class="text-orange-600 hover:text-orange-800 p-2 rounded hover:bg-orange-50 transition-colors" title="Cancel appointment with reason" onclick="return openCancelReasonModal(<?= (int)$appt['id'] ?>)">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-gray-500 text-lg">No active appointments found.</p>
                        <p class="text-gray-400 text-sm mt-1">All appointments have been completed or cancelled. Schedule a new appointment using the form above.</p>
                        <div class="mt-4 p-3 bg-green-50 rounded-lg">
                            <p class="text-sm text-green-700">
                                <strong>💡 Tip:</strong> Check your <a href="records.php" class="text-green-600 underline">Treatment Records</a> to view completed appointments.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mt-6">
                    <a href="records.php" class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-colors">
                        📋 View Treatment Records
                    </a>
                    <a href="#" class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 border border-blue-600 text-blue-700 rounded-lg font-semibold hover:bg-blue-50 transition-colors">+ Schedule New Appointment</a>
                </div>
            </div>
        </main>
    </div>

    <?php include 'footer.php'; ?>
    <script>
    // Handle mobile menu toggle
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        
        function openSidebar() {
            document.body.style.overflow = 'hidden';
            sidebar.classList.add('active');
            document.body.appendChild(overlay);
        }
        
        function closeSidebar() {
            document.body.style.overflow = '';
            sidebar.classList.remove('active');
            if (document.body.contains(overlay)) {
                document.body.removeChild(overlay);
            }
        }
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                if (sidebar.classList.contains('active')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
            
            // Close sidebar when clicking outside
            overlay.addEventListener('click', closeSidebar);
            
            // Close sidebar when clicking a link (for single page navigation)
            const navLinks = sidebar.querySelectorAll('a');
            navLinks.forEach(link => {
                link.addEventListener('click', closeSidebar);
            });
            
            // Close sidebar on window resize if it becomes desktop view
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (window.innerWidth >= 1024) {
                        closeSidebar();
                    }
                }, 250);
            });
        }
    });
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
