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

// Calendar params
$calYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$calMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
if ($calMonth < 1 || $calMonth > 12) { $calMonth = intval(date('n')); }
if ($calYear < 2000 || $calYear > 2100) { $calYear = intval(date('Y')); }

// Handle booking and cancellation
$alert = handleAppointmentBooking($conn, $email, $patient, $treatments);
if (empty($alert)) {
    $alert = handleAppointmentCancellation($conn, $email);
}

// Show success messages
if (isset($_GET['booked'])) {
    $alert = '<div class="bg-green-100 text-green-700 px-4 py-2 rounded mb-4">✅ Appointment request submitted successfully! Please wait for admin confirmation.</div>';
    unset($_SESSION['last_booking']);
} elseif (isset($_GET['cancelled'])) {
    $alert = '<div class="bg-yellow-100 text-yellow-700 px-4 py-2 rounded mb-4">⚠️ Appointment cancelled successfully.</div>';
}

// Get calendar data and appointments
$calendarData = generateCalendarData($conn, $calYear, $calMonth);
$activeAppts = getActiveAppointments($conn, $email);
$appts = getAllAppointments($conn, $email);

// Fetch notifications
$notifications = [];
$notifyStmt = $conn->prepare("SELECT Id AS Notification_Id, Message, Type, Created_At FROM tbl_notifications WHERE Email = ? AND Is_Read = 0 ORDER BY Created_At DESC LIMIT 5");
if ($notifyStmt) {
    $notifyStmt->bind_param('s', $email);
    $notifyStmt->execute();
    $notifyResult = $notifyStmt->get_result();
    while ($row = $notifyResult->fetch_assoc()) {
        $notifications[] = $row;
    }
    $notifyStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Appointments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .time-slot-btn {
            transition: all 0.2s ease;
        }
        .time-slot-btn[disabled] {
            pointer-events: none;
        }
        .time-slot-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .time-slot-btn.selected {
            background-color: #ef4444 !important; /* red-500 */
            color: white !important;
            border-color: #ef4444 !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
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
        .treatment-btn[disabled] {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
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
    </style>
    <script>
        const dentists = <?= json_encode($dentists) ?>;
        const treatments = <?= json_encode($treatments) ?>;
        
        function selectDentist(btn, id) {
            document.querySelectorAll('#dentistsContainer .dentist-btn').forEach(b=>b.classList.remove('selected'));
            if (btn && !btn.hasAttribute('disabled')) btn.classList.add('selected');
            const h = document.getElementById('dentistHidden');
            if (h) h.value = String(id);
            fetchAndRenderSlots();
            checkAvailability();
        }

        // Disable treatment pills when inventory is unavailable
        function gateTreatmentsByInventory() {
            try {
                const pills = Array.from(document.querySelectorAll('#treatmentsContainer .treatment-btn[data-name]'));
                if (!pills || pills.length === 0) return;
                const names = pills.map(b => b.getAttribute('data-name')).filter(Boolean).join('|');
                if (!names) return;
                fetch('appointment_api.php?treatments_inventory_status=1&names=' + encodeURIComponent(names))
                    .then(r => r.json())
                    .then(info => {
                        if (!info || info.ok === false) return;
                        const map = info.status || info.availability || {};
                        pills.forEach(btn => {
                            const name = btn.getAttribute('data-name') || '';
                            const s = map[name];
                            // Policy: If no mapping/status, allow selection (e.g., Tooth Extraction may not need tracked items).
                            // Disable only when we have mapped items and any are unmatched/zero/insufficient, or explicit available_all=false.
                            let unavailable = false;
                            if (s && s.available_all === false) {
                                unavailable = true;
                            } else if (s && Array.isArray(s.items)) {
                                unavailable = s.items.some(it => {
                                    const matched = (it && typeof it.matched !== 'undefined') ? !!it.matched : true;
                                    const cur = Number(it && it.current_stock != null ? it.current_stock : 0);
                                    const need = Number(it && it.quantity_needed != null ? it.quantity_needed : 0);
                                    return !matched || cur <= 0 || cur < need;
                                });
                            }
                            if (unavailable) {
                                btn.setAttribute('disabled', 'disabled');
                                btn.classList.add('opacity-60','cursor-not-allowed');
                                if (!btn.title) btn.title = 'Unavailable (out of stock)';
                                // If it was selected, unselect and update hidden fields
                                if (btn.classList.contains('selected')) {
                                    btn.classList.remove('selected');
                                    updateDuration();
                                }
                            } else {
                                btn.removeAttribute('disabled');
                                btn.classList.remove('opacity-60','cursor-not-allowed');
                                if (btn.title === 'Unavailable (out of stock)') btn.removeAttribute('title');
                            }
                        });
                    })
                    .catch(() => {});
            } catch (e) { /* noop */ }
        }
        
        function selectTimeSlot(timeValue) {
            const timeHidden = document.getElementById('timeHidden');
            // Prevent selecting a disabled slot
            const selectedBtn = document.querySelector(`[onclick="selectTimeSlot('${timeValue}')"]`);
            if (selectedBtn && selectedBtn.disabled) { return; }
            // Prevent selecting any time when selected date is in the past
            const form = document.getElementById('appointmentForm');
            const selectedDate = form ? (form.querySelector('input[name="date"]')||{}).value : '';
            const todayStr = new Date().toISOString().split('T')[0];
            if (selectedDate && selectedDate < todayStr) { return; }

            timeHidden.value = timeValue;
            document.querySelectorAll('.time-slot-btn').forEach(btn => { btn.classList.remove('selected'); });
            if (selectedBtn) { selectedBtn.classList.add('selected'); }
            checkAvailability();
        }

        function checkAvailability() {
            const form = document.getElementById('appointmentForm');
            if (!form) return;
            const date = form.querySelector('input[name="date"]').value;
            const time = document.getElementById('timeHidden').value;
            const dentistId = (document.getElementById('dentistHidden')||{value:''}).value;
            const duration = getSelectedDuration();
            const hint = document.getElementById('availabilityHint');
            if (!hint) return;
            
            hint.textContent = '';
            hint.className = 'text-xs mt-1';
            if (!date || !time) return;

            const params = new URLSearchParams();
            params.set('availability', '1');
            params.set('date', date);
            params.set('time', time);
            if (dentistId) params.set('dentist_id', dentistId);
            params.set('duration', String(duration));

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
        }

        // Collect selected treatment buttons
        function getSelectedTreatmentButtons() {
            return Array.from(document.querySelectorAll('.treatment-btn.selected'));
        }

        // Helper: support select or pill UI
        function getSelectedDuration() {
            const dHidden = document.getElementById('durationHidden');
            if (dHidden && dHidden.value) {
                const v = parseInt(dHidden.value, 10);
                if (Number.isFinite(v) && v > 0) return v;
            }
            const sel = document.getElementById('treatment');
            if (sel) {
                const opt = sel.options[sel.selectedIndex];
                const durAttr = opt ? opt.getAttribute('data-duration') : null;
                let dur = durAttr ? parseInt(durAttr, 10) : 60;
                if (!Number.isFinite(dur) || dur <= 0) dur = 60;
                return dur;
            }
            const pill = document.querySelector('.treatment-btn.selected');
            if (pill) {
                const durAttr = pill.getAttribute('data-duration');
                let dur = durAttr ? parseInt(durAttr, 10) : 60;
                if (!Number.isFinite(dur) || dur <= 0) dur = 60;
                return dur;
            }
            return 60;
        }

        function updateDuration() {
            const durationHidden = document.getElementById('durationHidden');
            const tHidden = document.getElementById('treatmentHidden');
            const tListHidden = document.getElementById('treatmentListHidden');

            const pills = getSelectedTreatmentButtons();
            if (pills.length > 0) {
                const names = pills.map(b => b.getAttribute('data-name'));
                const totalDur = pills.reduce((s,b)=> s + (Number(b.getAttribute('data-duration'))||0), 0);
                if (tHidden) tHidden.value = names.join(', ');
                if (tListHidden) tListHidden.value = names.join('|');
                if (durationHidden) durationHidden.value = String(totalDur);
            } else {
                // Fallback to legacy select
                let label = '';
                const sel = document.getElementById('treatment');
                if (sel && sel.options && sel.selectedIndex >= 0) {
                    const opt = sel.options[sel.selectedIndex];
                    label = opt ? opt.value : '';
                }
                if (tHidden) tHidden.value = label || '';
                if (tListHidden) tListHidden.value = label ? label : '';
                if (durationHidden) durationHidden.value = String(getSelectedDuration());
            }
            checkAvailability();
            if (typeof applyTimeConstraints === 'function') {
                applyTimeConstraints();
            }
        }

        function selectTreatment(btnElem, name, duration) {
            if (!btnElem || btnElem.disabled) return;
            
            // Toggle the selected state of the clicked button
            btnElem.classList.toggle('selected');
            
            // If "All Treatments" was clicked
            if (name === 'All Treatments') {
                // Get all treatment buttons except "All Treatments"
                const allTreatmentBtns = Array.from(document.querySelectorAll('.treatment-btn[data-name][data-name!="All Treatments"]'));
                
                if (btnElem.classList.contains('selected')) {
                    // If "All Treatments" is being selected, select all other treatments
                    allTreatmentBtns.forEach(btn => {
                        if (!btn.disabled) {
                            btn.classList.add('selected');
                        }
                    });
                } else {
                    // If "All Treatments" is being deselected, deselect all other treatments
                    allTreatmentBtns.forEach(btn => btn.classList.remove('selected'));
                }
            } else {
                // If a specific treatment is clicked, make sure "All Treatments" is not selected
                const allBtn = document.querySelector('.treatment-btn[data-name="All Treatments"]');
                if (allBtn) allBtn.classList.remove('selected');
            }
            
            // Prepare references
            const tHidden = document.getElementById('treatmentHidden');
            const tListHidden = document.getElementById('treatmentListHidden');
            const dHidden = document.getElementById('durationHidden');

            // If All Treatments is selected, build list and duration from ALL configured treatments (ignore inventory gating)
            if (name === 'All Treatments' && btnElem.classList.contains('selected')) {
                // Use global treatments array for authoritative list and durations
                const arr = Array.isArray(window.treatments) ? window.treatments : [];
                const names = [];
                let total = 0;
                arr.forEach(t => {
                    const n = String((t && t.name) || '').trim();
                    if (!n) return;
                    names.push(n);
                    const d = (t && typeof t.duration === 'number' && t.duration > 0) ? t.duration : 60;
                    total += d;
                });
                if (tHidden) tHidden.value = names.join(', ');
                if (tListHidden) tListHidden.value = names.join('|');
                if (dHidden) dHidden.value = String(total);
                // Visually mark all pills as selected (even if disabled by inventory) for clarity
                const pillsAll = Array.from(document.querySelectorAll('.treatment-btn[data-name]'));
                pillsAll.forEach(p => { if ((p.getAttribute('data-name')||'') !== 'All Treatments') p.classList.add('selected'); });
            } else {
                // Otherwise, use the selected (specific) buttons only
                const selectedBtns = Array.from(document.querySelectorAll('.treatment-btn.selected[data-name][data-name!="All Treatments"]'));
                const selectedNames = selectedBtns.map(btn => btn.getAttribute('data-name'));
                if (tHidden) tHidden.value = selectedNames.join(', ');
                if (tListHidden) tListHidden.value = selectedNames.join('|');
                if (dHidden) {
                    const totalDuration = selectedBtns.reduce((sum, btn) => {
                        const duration = parseInt(btn.getAttribute('data-duration') || '0', 10);
                        return sum + (isNaN(duration) ? 0 : duration);
                    }, 0);
                    dHidden.value = String(totalDuration);
                }
            }
            
            // Update any related UI elements
            if (typeof updateSelectedTreatmentsSummary === 'function') updateSelectedTreatmentsSummary();
            if (typeof applyTimeConstraints === 'function') applyTimeConstraints();
            if (typeof checkAvailability === 'function') checkAvailability();
        }

        function updateSelectedTreatmentsSummary() {
            const box = document.getElementById('selectedTreatmentsSummary');
            if (!box) return;
            const selected = Array.from(document.querySelectorAll('.treatment-btn.selected[data-name][data-name!="All Treatments"]'));
            if (selected.length === 0) { box.textContent = ''; return; }
            const items = selected.map(btn => {
                const n = btn.getAttribute('data-name') || '';
                const d = parseInt(btn.getAttribute('data-duration') || '0', 10) || 0;
                return `${n} (${d} min)`;
            });
            const total = selected.reduce((s,btn)=> s + (parseInt(btn.getAttribute('data-duration')||'0',10)||0), 0);
            box.textContent = items.join(', ') + ` — Total: ${total} min`;
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
            modalContent.className = 'bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto';
            modalContent.onclick = function(e) {
                e.stopPropagation();
            };
            
            const defaultActiveDentistId = (dentists.find(d=>Number(d.is_active)===1)||{}).id || '';
            
            // Build treatment pills
            let treatmentPills = '';
            // Fallback duration map for treatments missing durations in config/DB
            const durationMap = {
                'Oral Prophylaxis (Cleaning)': 45,
            treatmentPills += `
                <button type="button" class="treatment-btn" data-name="All Treatments" data-duration="" onclick="selectTreatment(this, 'All Treatments','')">
                    <div class="font-medium">All Treatments</div>
                    <div class="text-xs opacity-80">Multiple procedures</div>
                </button>`;
            (treatments || []).forEach(t => {
                const name = String(t.name || '').trim();
                if (!name) return;
                const safeName = name.replace(/'/g, "\\'");
                let minutes = 0;
                if (typeof t.duration === 'number' && t.duration > 0) {
                    minutes = t.duration;
                } else {
                    minutes = 60; // default when duration is missing from config
                }
                treatmentPills += `
                    <button type="button" class="treatment-btn" data-name="${name}" data-duration="${minutes}" onclick="selectTreatment(this, '${safeName}', ${minutes})">
                        <div class="font-medium">${name}</div>
                        <div class="text-xs opacity-80">${minutes} min</div>
                    </button>`;
            });
            
            modalContent.innerHTML = `
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-800">Book Appointment</h3>
                        <button type="button" onclick="closeBookingForm()" class="text-gray-500 hover:text-gray-700 text-3xl font-bold leading-none">&times;</button>
                    </div>
                    
                    <form method="post" id="appointmentForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Dentist</label>
                            <div id="dentistsContainer" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                ${dentists.map((d)=>{
                                    const isActive = Number(d.is_active)===1;
                                    const sel = isActive && d.id===defaultActiveDentistId ? 'selected' : '';
                                    const extraCls = isActive ? '' : ' inactive opacity-60 cursor-not-allowed';
                                    const disabledAttr = isActive ? '' : ' disabled';
                                    const initial = String(d.name||'D').trim().charAt(0).toUpperCase();
                                    const statusLabel = isActive ? 'Active' : 'Inactive';
                                    return `
                                    <button type=\"button\" class=\"dentist-btn flex items-center gap-3${extraCls} ${sel}\" data-id=\"${d.id}\" onclick=\"${isActive?`selectDentist(this, ${d.id})`:'return false;'}\"${disabledAttr}>
                                        <div class=\"dentist-avatar\">${initial}</div>
                                        <div class=\"flex-1 min-w-0\">
                                            <div class=\"dentist-name truncate\">${d.name}</div>
                                            <div class=\"dentist-meta truncate\">${d.specialty}</div>
                                        </div>
                                        <span class=\"dentist-status\">${statusLabel}</span>
                                    </button>`;
                                }).join('')}
                            </div>
                            <input type="hidden" name="dentist_id" id="dentistHidden" value="${defaultActiveDentistId}">
                            <input type="hidden" name="date" value="${selectedDate}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Time</label>
                            <div id="timeSlotsContainer" class="grid grid-cols-3 gap-2 max-h-48 overflow-y-auto border rounded-lg p-3 bg-gray-50">
                                <div class="col-span-3 text-sm text-gray-500">Select a date and dentist to view available times.</div>
                            </div>
                            <input type="hidden" name="time" id="timeHidden" value="" required>
                            <input type="hidden" name="duration" id="durationHidden" value="">
                            <p class="text-xs text-gray-500 mt-1">Clinic hours: 7:00 AM - 7:00 PM (30-minute intervals)</p>
                            <p id="availabilityHint" class="text-xs mt-1"></p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Treatment</label>
                            <div id="treatmentsContainer" class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto border rounded-lg p-3 bg-gray-50">
                                ${treatmentPills}
                            </div>
                            <div id="selectedTreatmentsSummary" class="text-xs text-gray-700 mt-2"></div>
                            <input type="hidden" name="treatment" id="treatmentHidden" value="">
                            <input type="hidden" name="treatment_list" id="treatmentListHidden" value="">
                            <!-- hidden select for compatibility -->
                            <select id="treatment" class="hidden" onchange="updateDuration()"></select>
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
            // Initial slots load for default dentist
            if (typeof fetchAndRenderSlots === 'function') fetchAndRenderSlots();
            // Disable unavailable treatments by inventory
            if (typeof gateTreatmentsByInventory === 'function') gateTreatmentsByInventory();
            // Initialize selected treatments summary
            if (typeof updateSelectedTreatmentsSummary === 'function') updateSelectedTreatmentsSummary();
            
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
            // Apply time constraints immediately and on relevant changes
            if (typeof applyTimeConstraints === 'function') {
                applyTimeConstraints();
            }
            const form = document.getElementById('appointmentForm');
            if (form) {
                const dateInput = form.querySelector('input[name="date"]');
                const treatmentSelect = form.querySelector('select[name="treatment"]');
                if (dateInput) dateInput.addEventListener('change', applyTimeConstraints);
                if (treatmentSelect) treatmentSelect.addEventListener('change', applyTimeConstraints);
                // Initialize duration hidden value on open
                const durationHidden = form.querySelector('#durationHidden');
                if (durationHidden) {
                    durationHidden.value = String(getSelectedDuration());
                }
                // Disable dentists on leave for this date and auto-select available one if needed
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
                        btn.setAttribute('disabled','disabled');
                        btn.classList.add('opacity-60','cursor-not-allowed','on-leave');
                        const nameEl = btn.querySelector('.dentist-name');
                        if (nameEl && !nameEl.textContent.includes('(On Leave)')) {
                          nameEl.textContent = nameEl.textContent + ' (On Leave)';
                        }
                        if (btn.classList.contains('selected')) {
                          btn.classList.remove('selected');
                          if (hiddenDent && hiddenDent.value === String(id)) {
                            hiddenDent.value = '';
                          }
                        }
                      } else {
                        if (!firstAvailableId) firstAvailableId = id;
                      }
                    }
                    const currentVal = hiddenDent ? hiddenDent.value : '';
                    if ((!currentVal || currentVal === '') && firstAvailableId) {
                      const firstBtn = container.querySelector(`button.dentist-btn[data-id="${firstAvailableId}"]`);
                      if (firstBtn && !firstBtn.hasAttribute('disabled')) {
                        firstBtn.classList.add('selected');
                        if (hiddenDent) hiddenDent.value = String(firstAvailableId);
                        if (typeof fetchAndRenderSlots === 'function') fetchAndRenderSlots();
                      }
                    }
                  } catch(e) {}
                })();
            }
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

        // Disable time slots in the past (for selected date) and those that would end after 7:00 PM
        function applyTimeConstraints() {
            const form = document.getElementById('appointmentForm');
            if (!form) return;
            const dateInput = form.querySelector('input[name="date"]');
            const selectedDate = dateInput ? dateInput.value : '';
            const duration = getSelectedDuration();
            const buttons = form.querySelectorAll('.time-slot-btn');
            const timeHidden = document.getElementById('timeHidden');

            const now = new Date();
            const todayStr = now.toISOString().split('T')[0];
            const currentMinutes = now.getHours() * 60 + now.getMinutes();

            const toMinutes = (hm) => {
                const [h, m] = hm.split(':').map(n => parseInt(n, 10));
                return h * 60 + (m || 0);
            };

            const openMinutes = toMinutes('07:00');
            const closeMinutes = toMinutes('19:00');

            let selectedStillAllowed = true;

            buttons.forEach(btn => {
                const startHM = btn.getAttribute('data-time');
                if (!startHM) return;
                const startMin = toMinutes(startHM);
                const endMin = startMin + duration;

                let disable = false;
                if (startMin < openMinutes) disable = true;
                if (endMin > closeMinutes) disable = true; // must end by 7:00 PM
                if (selectedDate === todayStr && startMin <= currentMinutes) disable = true; // past times today

                if (disable) {
                    btn.setAttribute('disabled', 'disabled');
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                    if (timeHidden && timeHidden.value && timeHidden.value.slice(0,5) === startHM) {
                        timeHidden.value = '';
                        selectedStillAllowed = false;
                    }
                } else {
                    btn.removeAttribute('disabled');
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            });

            if (!selectedStillAllowed) {
                const hint = document.getElementById('availabilityHint');
                if (hint) {
                    hint.textContent = 'Selected time is no longer available for the chosen date/duration. Please pick another time.';
                    hint.className = 'text-xs mt-1 text-red-600';
                }
            }
        }

        // Render dynamic time slots into the grid container
        function renderTimeSlots(slots) {
            const container = document.getElementById('timeSlotsContainer');
            if (!container) return;
            container.innerHTML = '';
            if (!Array.isArray(slots) || slots.length === 0) {
                container.innerHTML = '<div class="col-span-3 text-sm text-gray-500">No available time slots for the selected options.</div>';
                return;
            }
            const frag = document.createDocumentFragment();
            // Determine if selected date is today to disable past times
            const form = document.getElementById('appointmentForm');
            const selectedDate = form ? (form.querySelector('input[name="date"]')||{}).value : '';
            const todayStr = new Date().toISOString().split('T')[0];
            const now = new Date();
            const round5 = n => Math.floor(n/5)*5;
            const nowHM = String(now.getHours()).padStart(2,'0') + ':' + String(round5(now.getMinutes())).padStart(2,'0');

            slots.forEach(hm => {
                // Format label as e.g. 7:00 am
                const [h, m] = hm.split(':').map(n => parseInt(n, 10));
                const d = new Date(); d.setHours(h); d.setMinutes(m||0);
                const label = d.toLocaleTimeString([], {hour:'numeric', minute:'2-digit'}).toLowerCase();
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'time-slot-btn px-3 py-2 text-sm rounded border bg-white hover:bg-red-50 hover:border-red-300';
                btn.setAttribute('data-time', hm);
                btn.textContent = label;
                btn.onclick = () => selectTimeSlot(hm);
                // Disable any time when selected date is in the past
                if (selectedDate && selectedDate < todayStr) {
                    btn.disabled = true;
                    btn.classList.add('opacity-50','cursor-not-allowed');
                }
                // Disable past times for today
                else if (selectedDate === todayStr && hm <= nowHM) {
                    btn.disabled = true;
                    btn.classList.add('opacity-50','cursor-not-allowed');
                }
                frag.appendChild(btn);
            });
            container.appendChild(frag);
        }

        // Fetch available slots for selected dentist/date/duration and render them
        function fetchAndRenderSlots() {
            const form = document.getElementById('appointmentForm');
            if (!form) return;
            const container = document.getElementById('timeSlotsContainer');
            if (container) container.innerHTML = '<div class="col-span-3 text-sm text-gray-500">Loading available times...</div>';
            const date = form.querySelector('input[name="date"]').value;
            const dentistId = (document.getElementById('dentistHidden')||{value:''}).value;
            const duration = getSelectedDuration();
            if (!date || !dentistId) {
                if (container) container.innerHTML = '<div class="col-span-3 text-sm text-gray-500">Select a date and dentist to view available times.</div>';
                return;
            }
            const params = new URLSearchParams();
            params.set('slots', '1');
            params.set('date', date);
            params.set('dentist_id', dentistId);
            params.set('duration', String(duration));
            fetch('appointment_api.php?' + params.toString())
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.ok) { renderTimeSlots([]); if (typeof applyTimeConstraints === 'function') applyTimeConstraints(); return; }
                    if (data.leave === true) {
                        if (container) container.innerHTML = '<div class="col-span-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-3">Selected dentist is unavailable on this date (on leave). Please choose another date or dentist.</div>';
                        return;
                    }
                    renderTimeSlots(data.slots || []);
                    if (typeof applyTimeConstraints === 'function') applyTimeConstraints();
                })
                .catch(() => { renderTimeSlots([]); if (typeof applyTimeConstraints === 'function') applyTimeConstraints(); });
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
    </script>
</head>
<body class="bg-gray-50 text-gray-800">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white p-6 shadow-md space-y-6">
            <div>
                <h2 class="text-2xl font-bold text-blue-600">Miles Dental Clinic</h2>
                <p class="text-sm mt-1 text-gray-500">Welcome, <?= htmlspecialchars($full_name) ?></p>
                <span class="inline-block mt-1 text-xs px-2 py-1 rounded-full <?= $status === 'Active' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?>">
                    <?= htmlspecialchars($status) ?>
                </span>
            </div>
            <nav class="space-y-2">
                <a href="patient_dashboard.php" class="block hover:text-blue-600 font-medium">Dashboard</a>
                <a href="appointment.php" class="block text-blue-700 font-semibold">Appointments</a>
                <a href="records.php" class="block hover:text-blue-600">Treatment Records</a>
                <a href="messages.php" class="block hover:text-blue-600">Messages</a>
                <a href="profile.php" class="block hover:text-blue-600">Profile</a>
                <a href="logout.php" class="block text-red-600 font-semibold">Logout</a>
            </nav>
        </aside>

        <!-- Main content -->
        <main class="flex-1 p-8 space-y-8">
            <div id="alertContainer"><?= $alert ?></div>
            
            <!-- Notifications Section -->
            <?php if (!empty($notifications)): ?>
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">New Notifications</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <?php foreach ($notifications as $notif): ?>
                                    <div class="mb-2 p-2 <?= $notif['Type'] === 'appointment_completed' ? 'bg-green-100 border-l-4 border-green-400' : 'bg-blue-100' ?> rounded">
                                        <div class="font-medium <?= $notif['Type'] === 'appointment_completed' ? 'text-green-800' : '' ?>">
                                            <?php 
                                            switch($notif['Type']) {
                                                case 'appointment_confirmed':
                                                    echo '✅ Appointment Confirmed';
                                                    break;
                                                case 'appointment_cancelled':
                                                    echo '❌ Appointment Cancelled';
                                                    break;
                                                case 'appointment_rescheduled':
                                                    echo '🔄 Appointment Rescheduled';
                                                    break;
                                                case 'appointment_completed':
                                                    echo '🎉 Appointment Completed';
                                                    break;
                                                default:
                                                    echo '📢 Notification';
                                            }
                                            ?>
                                        </div>
                                        <div class="text-sm"><?= htmlspecialchars($notif['Message']) ?></div>
                                        <div class="text-xs text-blue-600 mt-1">
                                            <?= date('M j, Y g:i A', strtotime($notif['Created_At'])) ?>
                                            <form method="post" action="mark_notification_read.php" class="inline ml-2">
                                                <input type="hidden" name="notification_id" value="<?= $notif['Notification_Id'] ?>">
                                                <button type="submit" name="mark_read" class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
                                                    Mark as Read
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-3xl font-semibold text-blue-700">All Appointments</h1>
                <button onclick="location.reload()" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Refresh
                </button>
            </div>

            <!-- Availability Calendar -->
            <div class="bg-white shadow rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">Availability Calendar</h2>
                    <div class="space-x-2">
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
                <div class="grid grid-cols-7 gap-2 text-center text-sm">
                    <?php
                        $weekdays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                        foreach ($weekdays as $wd) {
                            echo '<div class="font-semibold text-gray-600">'.$wd.'</div>';
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
                            $colorClass = 'bg-green-100 text-green-800';
                            if ($count >= $highThreshold) { $colorClass = 'bg-red-100 text-red-800'; }
                            elseif ($count >= $lowThreshold) { $colorClass = 'bg-yellow-100 text-yellow-800'; }
                            $label = $count.' clients';
                            
                            $today = date('Y-m-d');
                            $isPastDate = $dstr < $today;
                            
                            if ($isPastDate) {
                                echo '<div class="block p-3 border rounded bg-gray-100 text-gray-400 cursor-not-allowed opacity-50" title="Past date - cannot book appointments"><div>'.$d.'</div><div class="text-xs mt-1 font-semibold">'.$label.'</div></div>';
                            } else {
                                echo '<div onclick="selectDate(\''.$dstr.'\')" class="block p-3 border rounded '.$colorClass.' cursor-pointer hover:opacity-80 transition-opacity" title="Click to book appointment on '.$dstr.'"><div>'.$d.'</div><div class="text-xs mt-1 font-semibold">'.$label.'</div></div>';
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
                    <div class="space-y-4">
                        <?php foreach ($appts as $appt): ?>
                            <div class="flex flex-col md:flex-row md:items-center justify-between <?= $appt['Status'] === 'Pending' ? 'bg-orange-50 border-l-4 border-orange-400' : 'bg-blue-50' ?> rounded-lg p-5 shadow-sm">
                                <div class="flex items-center space-x-4 flex-1">
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
                                            <?= htmlspecialchars($appt['Duration']) ?> minutes
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3 mt-4 md:mt-0">
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
                                    
                                    <!-- Cancel Button -->
                                    <a href="appointment.php?cancel_id=<?= $appt['id'] ?>" class="text-orange-500 hover:text-orange-700 p-2 rounded hover:bg-orange-50 transition-colors" title="Cancel appointment" onclick="return confirmCancel(<?= $appt['id'] ?>)">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </a>
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
                <div class="flex justify-between items-center mt-6">
                    <a href="records.php" class="px-6 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-colors">
                        📋 View Treatment Records
                    </a>
                    <a href="#" class="px-6 py-2 border border-blue-600 text-blue-700 rounded-lg font-semibold hover:bg-blue-50">+ Schedule New Appointment</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
