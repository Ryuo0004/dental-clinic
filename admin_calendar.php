<?php
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include 'admin_init.php';
} else {
  include 'db_connect.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
  $today = date('Y-m-d');
}
if (file_exists($baseDir . '/admin_actions.php')) {
  include 'admin_actions.php';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Miles Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        
        /* Appointment details styling */
        .appointment-detail {
            display: flex;
            align-items: flex-start;
            margin-bottom: 8px;
            padding: 6px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .appointment-detail:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .appointment-detail i {
            width: 24px;
            text-align: center;
            color: #6c757d;
            margin-top: 2px;
        }
        
        .detail-content {
            flex: 1;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #6c757d;
            display: block;
            margin-bottom: 2px;
        }
        
        .detail-value {
            display: block;
            font-size: 0.95rem;
            color: #333;
            word-break: break-word;
        }
        
        .appointment-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
        }
        
        .appointment-actions .btn {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        
        .d-flex {
            display: flex;
            min-height: 100vh;
        }
        
        .content-wrapper {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .calendar-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0 0 5px 0;
        }
        
        .calendar-subtitle {
            color: #666;
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        .calendar-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .calendar-nav {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .month-year {
            font-size: 18px;
            font-weight: 500;
            color: #333;
            margin-right: auto;
        }
        
        .nav-buttons {
            display: flex;
            gap: 5px;
        }
        
        .nav-btn {
            background: #fff;
            color: #555;
            border: 1px solid #ddd;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .nav-btn:hover {
            background: #f5f5f5;
            border-color: #ccc;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            background: #f0f0f0;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            table-layout: fixed;
            width: 100%;
        }
        
        .day-header {
            background: #f8f8f8;
            padding: 10px 5px;
            text-align: center;
            font-weight: 600;
            color: #555;
            font-size: 12px;
            border: 1px solid #e0e0e0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
        }
        
        .calendar-day {
            background: #fff;
            min-height: 100px;
            padding: 8px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            border: 1px solid #e0e0e0;
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .calendar-day:hover {
            background: #f9f9f9;
        }
        
        .calendar-day.today {
            background: #f0f7ff;
            border-color: #b3d8ff;
        }
        
        .calendar-day.selected {
            background: #e6f2ff;
            border-color: #99c2ff;
        }
        
        .calendar-day.other-month {
            background: #fafafa;
            color: #aaa;
        }
        
        .day-number {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 16px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .appointment-count {
            background: #4a90e2;
            color: white;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 12px;
            margin-top: 4px;
            text-align: center;
            min-width: 24px;
            box-shadow: 0 2px 4px rgba(74, 144, 226, 0.3);
            border: 1px solid #2a6fbb;
            width: 100%;
            box-sizing: border-box;
        }
        
        .appointment-count.pending {
            background: #f39c12;
            border-color: #d68910;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(243, 156, 18, 0.3);
        }
        
        .has-pending {
            background-color: #fff8e6;
            border: 1px solid #ffe0b2;
        }
        
        .has-pending:hover {
            background-color: #fff2cc;
        }
        
        .appointment-status.pending {
            background-color: #f39c12;
            box-shadow: 0 0 0 2px rgba(243, 156, 18, 0.2);
        }
        
        .appointment-count.zero {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: #d1d5db;
            border: 1px solid #374151;
            opacity: 0.7;
        }
        
        .appointment-dot {
            width: 6px;
            height: 6px;
            background: #dc2626;
            border-radius: 50%;
            margin: 1px;
        }
        
        .appointment-dots {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
        }
        
        .schedule-sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 350px;
            height: fit-content;
        }
        
        .schedule-header {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .schedule-date {
            color: #64748b;
            font-size: 15px;
            margin-bottom: 24px;
            font-weight: 500;
        }
        
        .appointment-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .appointment-item:last-child {
            border-bottom: none;
        }
        
        .appointment-time {
            font-size: 12px;
            font-weight: 600;
            color: #1e40af;
            min-width: 60px;
            margin-right: 12px;
        }
        
        .appointment-details {
            flex: 1;
        }
        
        .appointment-patient {
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 2px;
        }
        
        .appointment-procedure {
            font-size: 12px;
            color: #64748b;
        }
        
        .appointment-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-left: 12px;
        }
        
        .status-confirmed { background: #10b981; }
        .status-pending { background: #f59e0b; }
        .status-completed { background: #6b7280; }
        
        .no-appointments {
            text-align: center;
            color: #64748b;
            padding: 40px 20px;
            font-style: italic;
        }
        
        .add-appointment-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 13px;
        }
        
        .add-appointment-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Table styles for modal */
        .table th {
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            font-size: 0.9rem;
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .badge {
            font-size: 0.75rem;
            font-weight: 500;
        }

        .btn-group-sm > .btn {
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'admin_sidebar.php'; ?>
        
        <div class="content-wrapper">
            <div class="content">
                <div class="calendar-header">
                    <h1 class="calendar-title">
                        <i class="fas fa-calendar-alt me-3"></i>Calendar
                    </h1>
                    <p class="calendar-subtitle">Manage appointments and view schedules</p>
                </div>
                
                <div class="calendar-container">
                    <div class="calendar-main">
                        <div class="calendar-nav">
                            <div class="month-year" id="monthYear">December 2024</div>
                            <div class="nav-buttons">
                                <button class="nav-btn" onclick="previousMonth()">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button class="nav-btn" onclick="nextMonth()">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                                <button class="nav-btn" onclick="goToToday()">
                                    <i class="fas fa-home"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="calendar-grid" id="calendarGrid">
                            <!-- Calendar will be generated by JavaScript -->
                        </div>
                    </div>
                    
                    <div class="schedule-sidebar">
                        <div class="schedule-header">
                            <i class="fas fa-clock"></i>
                            Schedule
                        </div>
                        <div class="schedule-date" id="scheduleDate">Today, December 12</div>
                        
                        <div id="appointmentsList">
                            <!-- Appointments will be loaded here -->
                        </div>
                        
                        <button class="add-appointment-btn" onclick="addAppointment()">
                            <i class="fas fa-plus me-2"></i>Add Appointment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Day Appointments -->
    <div class="modal fade" id="dayAppointmentsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content" style="border: none; border-radius: 12px; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; border: none; padding: 1.25rem 1.5rem;">
            <div>
              <h5 class="modal-title" id="dayModalTitle" style="font-size: 1.25rem; font-weight: 600; margin: 0;">
                <i class="far fa-calendar-alt me-2"></i>Appointments
              </h5>
              <div id="dayModalDate" style="font-size: 0.875rem; opacity: 0.9; margin-top: 4px;">
                <!-- Date will be filled dynamically -->
              </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="opacity: 0.8;"></button>
          </div>
          <div class="modal-body p-4" id="dayModalBody" style="max-height: 70vh; overflow-y: auto;">
            <!-- Filled dynamically -->
            <div class="text-center py-4 text-muted" id="noAppointmentsMessage" style="display: none;">
              <i class="far fa-calendar-plus fa-2x mb-3" style="opacity: 0.5;"></i>
              <p class="mb-0">No appointments scheduled for this day</p>
            </div>
          </div>
          <div class="modal-footer" style="background-color: #f8f9fc; border-top: 1px solid #e3e6f0; padding: 1rem 1.5rem;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="padding: 0.5rem 1.25rem; border-radius: 8px; font-weight: 500;">
              <i class="fas fa-times me-2"></i>Close
            </button>
            <a id="dayModalOpenLink" href="#" class="btn btn-primary" style="padding: 0.5rem 1.25rem; border-radius: 8px; font-weight: 500;">
              <i class="fas fa-external-link-alt me-2"></i>View All Appointments
            </a>
            <button type="button" class="btn btn-success" onclick="addAppointment()" style="padding: 0.5rem 1.25rem; border-radius: 8px; font-weight: 500;">
              <i class="fas fa-plus me-2"></i>New Appointment
            </button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentDate = new Date();
        let selectedDate = new Date();
        let appointmentDates = [];
        let appointmentCounts = {};
        let pendingAppointmentDates = [];
        let pendingAppointmentCounts = {};

        // Initialize calendar
        document.addEventListener('DOMContentLoaded', function() {
            loadAppointmentData();
            generateCalendar();
            loadScheduleForDate(formatDate(selectedDate));
        });

        function loadAppointmentData() {
            // Load all appointments
            fetch('check_notifications.php?action=get_appointment_dates')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        appointmentDates = data.dates || [];
                        appointmentCounts = data.counts || {};
                        
                        // Load pending confirmations
                        return fetch('check_notifications.php?action=get_pending_confirmations');
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data && data.success) {
                        pendingAppointmentDates = data.dates || [];
                        pendingAppointmentCounts = data.counts || {};
                    }
                    generateCalendar();
                })
                .catch(error => console.log('Error loading appointment data:', error));
        }

        function generateCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            // Update month/year display
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            document.getElementById('monthYear').textContent = `${monthNames[month]} ${year}`;
            
            // Get calendar data
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = firstDay.getDay();
            
            const prevMonth = new Date(year, month, 0);
            const daysInPrevMonth = prevMonth.getDate();
            
            let calendarHTML = '';
            
            // Add day headers
            const dayHeaders = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            dayHeaders.forEach(day => {
                calendarHTML += `<div class="day-header">${day}</div>`;
            });
            
            // Add previous month's trailing days
            for (let i = startingDayOfWeek - 1; i >= 0; i--) {
                const day = daysInPrevMonth - i;
                const date = new Date(year, month - 1, day);
                calendarHTML += createDayCell(day, date, true);
            }
            
            // Add current month's days
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                calendarHTML += createDayCell(day, date, false);
            }
            
            // Add next month's leading days
            const totalCells = Math.ceil((startingDayOfWeek + daysInMonth) / 7) * 7;
            const remainingCells = totalCells - (startingDayOfWeek + daysInMonth);
            for (let day = 1; day <= remainingCells; day++) {
                const date = new Date(year, month + 1, day);
                calendarHTML += createDayCell(day, date, true);
            }
            
            document.getElementById('calendarGrid').innerHTML = calendarHTML;
        }

        function createDayCell(day, date, isOtherMonth) {
            const dateString = formatDate(date);
            const today = new Date();
            const isToday = dateString === formatDate(today);
            const isSelected = dateString === formatDate(selectedDate);
            const appointmentCount = appointmentCounts[dateString] || 0;
            const pendingCount = pendingAppointmentCounts[dateString] || 0;
            
            let classes = 'calendar-day';
            if (isOtherMonth) classes += ' other-month';
            if (isToday) classes += ' today';
            if (isSelected) classes += ' selected';
            if (pendingCount > 0) classes += ' has-pending';
            
            let countDisplay = '';
            if (appointmentCount > 0 || pendingCount > 0) {
                countDisplay = `
                    ${pendingCount > 0 ? `<div class="appointment-count pending">${pendingCount} pending</div>` : ''}
                    ${appointmentCount > 0 ? `<div class="appointment-count">${appointmentCount} confirmed</div>` : ''}
                `;
            } else if (!isOtherMonth) {
                countDisplay = `<div class="appointment-count zero">0 patients</div>`;
            }
            
            return `
                <div class="${classes}" onclick="openDayModal('${dateString}')">
                    <div class="day-number">${day}</div>
                    ${countDisplay}
                </div>
            `;
        }

        function selectDate(dateString) {
            selectedDate = parseLocalDate(dateString);
            generateCalendar();
        }

        function loadScheduleForDate(dateString) {
            const date = new Date(dateString);
            const today = new Date();
            const isToday = dateString === formatDate(today);
            
            // Update schedule date display
            const options = { weekday: 'long', month: 'long', day: 'numeric' };
            const dateDisplay = isToday ? 'Today, ' + date.toLocaleDateString('en-US', options) : 
                                date.toLocaleDateString('en-US', options);
            document.getElementById('scheduleDate').textContent = dateDisplay;
            
            // Fetch appointments for the selected date
            fetch(`check_notifications.php?action=get_daily_schedule&date=${dateString}&include_pending=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAppointments(data.appointments || []);
                    }
                })
                .catch(error => {
                    console.log('Could not load schedule:', error);
                    displayAppointments([]);
                });
        }

        // Open modal and show appointments in table format
        function openDayModal(dateString) {
            // Update selected date and refresh calendar
            selectedDate = parseLocalDate(dateString);
            generateCalendar();
            
            // Set modal title and deep-link to admin appointments filtered by date
            const d = new Date(dateString);
            const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('dayModalTitle').textContent = d.toLocaleDateString('en-US', opts);
            
            // Update the link to admin appointments
            const openLink = document.getElementById('dayModalOpenLink');
            if (openLink) {
                openLink.href = `admin_appointments.php?date=${dateString}`;
                openLink.style.display = 'inline-block';
            }

            // Show loading state
            const body = document.getElementById('dayModalBody');
            body.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading appointments...</p>
                </div>`;

            // Fetch appointments for the selected date
            fetch(`check_notifications.php?action=get_daily_schedule&date=${dateString}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error(data.error || 'No data available');
                    }
                    
                    const appts = Array.isArray(data.appointments) ? data.appointments : [];
                    
                    if (appts.length === 0) {
                        body.innerHTML = `
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-day fa-3x text-muted mb-3"></i>
                                <h5>No Appointments</h5>
                                <p class="text-muted">No appointments scheduled for this date.</p>
                                <a href="admin_appointments.php?date=${dateString}" class="btn btn-primary btn-sm mt-2">
                                    <i class="fas fa-plus me-1"></i> Schedule Appointment
                                </a>
                            </div>`;
                        return;
                    }

                    // Render appointments in table format
                    let html = `
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="ps-3">Name</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Doctor</th>
                                    <th scope="col">Time</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Treatments</th>
                                    <th scope="col" class="text-center pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>`;

                    appts.forEach(appt => {
                        html += renderAppointmentItem(appt);
                    });

                    html += `
                            </tbody>
                        </table>
                    </div>`;

                    body.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading appointments:', error);
                    body.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Failed to load appointments. Please try again.
                            <div class="small text-muted mt-1">${error.message || 'Unknown error'}</div>
                        </div>`;
                });

            // Show the modal
            const modalEl = document.getElementById('dayAppointmentsModal');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
        
        // Helper function to render a single appointment item in table format
        function renderAppointmentItem(appt) {
            const time = appt.appointment_time ? 
                new Date(`2000-01-01T${appt.appointment_time}`).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true}) : 
                '--:--';
            
            // Determine status class and text
            let statusClass = 'badge bg-warning';
            let statusText = 'Pending';
            
            if (appt.status === 'confirmed') {
                statusClass = 'badge bg-success';
                statusText = 'Confirmed';
            } else if (appt.status === 'completed') {
                statusClass = 'badge bg-secondary';
                statusText = 'Completed';
            } else if (appt.status === 'cancelled') {
                statusClass = 'badge bg-danger';
                statusText = 'Cancelled';
            }
            
            return `
            <tr>
                <td class="align-middle">
                    <div class="fw-medium">${appt.patient_name || 'Unknown Patient'}</div>
                </td>
                <td class="align-middle">${appt.appointment_date || 'No date'}</td>
                <td class="align-middle">${appt.dentist_name ? appt.dentist_name : ''}</td>
                <td class="align-middle fw-medium">${time}</td>
                <td class="align-middle">
                    <span class="${statusClass}">${statusText}</span>
                </td>
                <td class="align-middle">${appt.procedure || appt.treatment || appt.procedure_name || 'No treatment specified'}</td>
                <td class="align-middle">
                    <div class="btn-group btn-group-sm">
                        <a href="admin_appointments.php?view=${appt.id}" class="btn btn-outline-primary">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="admin_appointments.php?edit=${appt.id}" class="btn btn-outline-secondary">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                </td>
            </tr>`;
        }

        function displayAppointments(appointments) {
            const modalBody = document.getElementById('dayModalBody');
            const noAppointmentsMessage = document.getElementById('noAppointmentsMessage');
            
            if (appointments.length === 0) {
                noAppointmentsMessage.style.display = 'block';
                modalBody.innerHTML = '';
                modalBody.appendChild(noAppointmentsMessage);
                return;
            }
            
            noAppointmentsMessage.style.display = 'none';
            
            let html = `
                <div class="list-group list-group-flush">
                    ${appointments.map(appointment => {
                        // Format time
                        const time = appointment.appointment_time ? 
                            new Date(`2000-01-01T${appointment.appointment_time}`).toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            }) : '--:--';
                        
                        // Format date
                        const apptDate = appointment.appointment_date ? 
                            new Date(appointment.appointment_date).toLocaleDateString('en-US', {
                                weekday: 'short',
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric'
                            }) : 'No date set';
                        
                        // Determine status class and text
                        let statusClass = 'bg-warning';
                        let statusText = 'Pending';
                        
                        if (appointment.status === 'confirmed') {
                            statusClass = 'bg-success';
                            statusText = 'Confirmed';
                        } else if (appointment.status === 'completed') {
                            statusClass = 'bg-secondary';
                            statusText = 'Completed';
                        } else if (appointment.status === 'cancelled') {
                            statusClass = 'bg-danger';
                            statusText = 'Cancelled';
                        }
                        
                        return `
                        <div class="list-group-item border-0 py-3 px-4" style="border-bottom: 1px solid #f0f2f5 !important;">
                            <div class="d-flex align-items-start">
                                <div class="me-3 text-center" style="min-width: 80px;">
                                    <div class="text-primary fw-bold" style="font-size: 1.1rem;">${time}</div>
                                    <span class="badge ${statusClass} text-white text-uppercase mb-2" style="font-size: 0.6rem; font-weight: 600; padding: 0.25rem 0.5rem;">
                                        ${statusText}
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-column">
                                        <div class="mb-2">
                                            <span class="fw-medium">${appointment.patient_name || 'N/A'}</span>
                                        </div>
                                        <div class="mb-2">
                                            <span>${appointment.dentist_name ? appointment.dentist_name : ''}</span>
                                        </div>
                                        <div class="mb-2">
                                            <span>${apptDate}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="ms-3" style="min-width: 180px;">
                                    <div class="appointment-detail">
                                        <i class="fas fa-phone-alt"></i>
                                        <div class="detail-content">
                                            <span class="detail-label">Phone</span>
                                            <span class="detail-value">${appointment.patient_phone || 'N/A'}</span>
                                        </div>
                                    </div>
                                    ${appointment.patient_email ? `
                                    <div class="appointment-detail">
                                        <i class="fas fa-envelope"></i>
                                        <div class="detail-content">
                                            <span class="detail-label">Email</span>
                                            <span class="detail-value">${appointment.patient_email}</span>
                                        </div>
                                    </div>` : ''}
                                    ${appointment.notes ? `
                                    <div class="appointment-detail">
                                        <i class="fas fa-sticky-note"></i>
                                        <div class="detail-content">
                                            <span class="detail-label">Notes</span>
                                            <span class="detail-value">${appointment.notes}</span>
                                        </div>
                                    </div>` : ''}
                                    
                                    <div class="appointment-actions">
                                        <a href="admin_appointments.php?view=${appointment.id}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        <a href="admin_appointments.php?edit=${appointment.id}" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </a>
                                        ${appointment.status !== 'cancelled' ? `
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="return confirmAction('Are you sure you want to cancel this appointment?', 'admin_appointments.php?cancel=${appointment.id}')">
                                            <i class="fas fa-times me-1"></i> Cancel
                                        </button>` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    }).join('')}
                </div>`;
            
            // Update the modal body with the new content
            modalBody.innerHTML = html;
        }

        function previousMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            generateCalendar();
        }

        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            generateCalendar();
        }

        function goToToday() {
            currentDate = new Date();
            selectedDate = new Date();
            generateCalendar();
            loadScheduleForDate(formatDate(selectedDate));
        }

        function addAppointment() {
            const dateString = formatDate(selectedDate);
            window.location.href = `admin_appointments.php?date=${dateString}&action=add`;
        }

        // Format local date as YYYY-MM-DD (avoids UTC shift issues)
        function formatDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }
        // Parse YYYY-MM-DD to a local Date (not UTC), avoiding off-by-one
        function parseLocalDate(s) {
            const parts = String(s || '').split('-');
            const y = parseInt(parts[0], 10);
            const m = parseInt(parts[1], 10) - 1;
            const d = parseInt(parts[2], 10);
            if (!Number.isFinite(y) || !Number.isFinite(m) || !Number.isFinite(d)) return new Date();
            return new Date(y, m, d);
        }
    </script>
</body>
</html>