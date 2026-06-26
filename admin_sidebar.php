<?php 
$__logoUrl = '';
$__clinicName = 'Miles Dental';
if (isset($conn) && $conn instanceof mysqli) {
  $conn->query("CREATE TABLE IF NOT EXISTS tbl_clinic_settings (id INT PRIMARY KEY AUTO_INCREMENT, setting_key VARCHAR(50) UNIQUE NOT NULL, setting_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
  
  
  if ($__st = $conn->prepare("SELECT setting_value FROM tbl_clinic_settings WHERE setting_key = 'clinic_logo_url' LIMIT 1")) {
    $__st->execute();
    $__res = $__st->get_result();
    if ($__row = $__res->fetch_assoc()) { $__logoUrl = (string)$__row['setting_value']; }
    $__st->close();
  }
  
  
  if ($__st = $conn->prepare("SELECT setting_value FROM tbl_clinic_settings WHERE setting_key = 'clinic_name' LIMIT 1")) {
    $__st->execute();
    $__res = $__st->get_result();
    if ($__row = $__res->fetch_assoc()) { $__clinicName = (string)$__row['setting_value']; }
    $__st->close();
  }
}
?>
<style>
.root {
}
:root {
  --bg: #ffffff;
  --text: #1f2937;
  --muted: #6b7280;
  --border: #e5e7eb;
  --hover: #f8fafc;
  --primary: #2563eb;
  --primary-hover: #1e40af;
  --active-bg: #e7f0ff;
  --active-border: #bfdbfe;
}
[data-theme="dark"] {
  --bg: #0f172a;
  --text: #e2e8f0;
  --muted: #94a3b8;
  --border: rgba(255,255,255,0.08);
  --hover: rgba(255,255,255,0.06);
  --primary: #60a5fa;
  --primary-hover: #3b82f6;
  --active-bg: rgba(37,99,235,0.18);
  --active-border: rgba(59,130,246,0.35);
}
.sidebar {
  position: sticky; /* keep sidebar pinned while content scrolls */
  top: 0;
  height: 100vh;
  background-color: var(--bg); /* light theme */
  color: var(--text);
  padding: 16px;
  display: flex;
  flex-direction: column;
  min-width: 260px;
  flex: 0 0 260px; /* prevent width collapse when centering content */
  font-family: Arial, sans-serif;
  font-size: 14px; /* unify font size across sidebar */
}

.sidebar-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 30px;
  padding: 20px 0;
  border-bottom: 1px solid var(--border);
}

.logo-icon {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #007bff, #0056b3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 12px;
  box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
  overflow: hidden;
}

.logo-icon svg {
  width: 24px;
  height: 24px;
  color: white;
}

.logo-text { font-size: inherit; font-weight: 600; color: #111827; }
.logo-subtitle { font-size: inherit; color: var(--muted); }

.theme-toggle {
  margin-top: 8px;
  align-self: center;
  background: var(--hover);
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 6px 10px;
  font-size: inherit; /* unify font size */
  cursor: pointer;
}
.theme-toggle:hover { background: var(--active-bg); border-color: var(--active-border); }

.sidebar a {
  color: var(--text);
  text-decoration: none;
  display: flex;
  align-items: center;
  padding: 10px 12px;
  border-radius: 8px;
  transition: background-color 0.2s ease, transform 0.2s ease;
  font-size: inherit;
  margin: 4px 0;
}

.sidebar a svg {
  width: 18px;
  height: 18px;
  margin-right: 12px;
  flex-shrink: 0;
}

.sidebar a:hover { 
  background-color: var(--hover); 
  text-decoration: none; 
  transform: translateX(4px); 
}

.sidebar a:hover svg { color: #2563eb; }

.sidebar a.active { 
  background-color: var(--active-bg); 
  color: var(--text); 
}

.sidebar a.active svg { color: #1d4ed8; }



.sidebar-footer {
  margin-top: 5px;
  font-size: 12px;
  color: var(--muted);
  text-align: center;
  padding: 5px 10px;
  border-top: 1px solid var(--border);
  background: var(--bg);
  position: relative;
  z-index: 1;
}
.sidebar-footer .text-muted {
  line-height: 1.4;
}

.sidebar > a:last-of-type {
  margin-bottom: 0;
}

/* ===== Sidebar Appointments Table Styles ===== */
/* Container prevents page-level horizontal scrolling; scrolls only inside */
.sidebar .appt-table-wrap {
  width: 100%;
  max-width: 100%;
  overflow-x: hidden;
  overflow-y: auto;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px;
  background: #111827;
}

/* Table fills container and distributes columns evenly */
.sidebar table.appt-table {
  width: 100%;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
}

.sidebar table.appt-table thead th,
.sidebar table.appt-table tbody td {
  padding: 10px 12px;       /* equal padding */
  vertical-align: middle;   /* balanced row height */
  white-space: normal;      /* allow wrapping */
  word-wrap: break-word;
  overflow-wrap: anywhere;  /* wrap long names without breaking layout */
  color: #e5e7eb;
}

.sidebar table.appt-table thead th {
  text-align: left;
  font-weight: 600;
  color: #cbd5e1;
}

/* Status pill centered in cell */
.sidebar .status-pill {
  display: inline-block;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  text-align: center;
  margin: 0 auto;
}
.sidebar .status-confirmed { background:#16a34a; color:#0b1220; }
.sidebar .status-pending   { background:#6b7280; color:#fff; }
.sidebar .status-ongoing   { background:#0ea5e9; color:#0b1220; }
.sidebar .status-finished  { background:#2563eb; color:#fff; }
.sidebar .status-cancelled { background:#dc2626; color:#fff; }

/* Actions column: center icons/controls horizontally and wrap if tight */
.sidebar .appt-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  flex-wrap: wrap;
}
.sidebar .appt-actions .icon-btn {
  width: 28px; height: 28px;
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: 6px;
  border: 1px solid rgba(255,255,255,0.12);
  color: #e5e7eb; background: transparent;
}
.sidebar .appt-actions .icon-btn:hover { background: rgba(255,255,255,0.08); }

/* Responsive: shrink gracefully in narrower sidebars */
@media (max-width: 992px) {
  .sidebar table.appt-table thead th,
  .sidebar table.appt-table tbody td { padding: 8px 10px; font-size: 13px; }
}
@media (max-width: 768px) {
  .sidebar table.appt-table { table-layout: fixed; }
  .sidebar .status-pill { padding: 5px 8px; font-size: 11px; }
  .sidebar .appt-actions { gap: 4px; }
}
@media (max-width: 576px) {
  .sidebar table.appt-table thead th,
  .sidebar table.appt-table tbody td { padding: 6px 8px; font-size: 12px; }
}

/* ===== Mobile sidebar behavior ===== */
@media (max-width: 768px) {
  .sidebar {
    position: fixed; left: 0; top: 0; bottom: 0; height: auto; width: 240px; z-index: 10000;
    transform: translateX(-100%);
    transition: transform .25s ease;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
  }
  body.sidebar-open .sidebar { transform: translateX(0); }
}

.sidebar-toggle-btn {
  position: fixed; left: 12px; top: 12px; z-index: 11000; border: 1px solid var(--border);
  background: var(--bg); color: var(--text); border-radius: 10px; padding: 8px 10px; display: none;
}
.sidebar-toggle-btn svg { width: 18px; height: 18px; }
@media (max-width: 768px) { .sidebar-toggle-btn { display: inline-flex; align-items:center; gap:6px; } }
</style>

<script>
(function(){
  try {
    var favicon = <?php echo json_encode($__logoUrl); ?> || '';
    if (!favicon) return;
    var head = document.head || document.getElementsByTagName('head')[0];
    if (!head) return;
    var rels = ['icon','shortcut icon','apple-touch-icon'];
    rels.forEach(function(rel){
      var l = document.createElement('link');
      l.rel = rel; l.href = favicon; if (rel === 'icon') l.type = 'image/png';
      head.appendChild(l);
    });
  } catch(e) {}
})();
</script>

<button class="sidebar-toggle-btn" type="button" onclick="document.body.classList.toggle('sidebar-open')" aria-label="Toggle sidebar">
  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
  Menu
</button>

<div class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">
    <?php if ($__logoUrl): ?>
      <img src="<?= htmlspecialchars($__logoUrl) ?>" alt="Clinic Logo" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
    <?php else: ?>
      <img src="https://i.ibb.co/v4xfPSLp/weps.jpg" alt="Clinic Logo" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
    <?php endif; ?>
    </div>
    <div>
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
      </svg>
    </div>
    <div>
      <div class="logo-text"><?= htmlspecialchars($__clinicName) ?></div>
      <div class="logo-subtitle">Admin</div>
    </div>
  </div>
  <?php $current = basename($_SERVER['PHP_SELF'] ?? ''); ?>
  <a href="admin_dashboard.php" class="<?= $current === 'admin_dashboard.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
      <path stroke-linecap="round" stroke-linejoin="round" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"/>
    </svg>
    Dashboard
  </a>
  <a href="admin_appointments.php" class="<?= $current === 'admin_appointments.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
    </svg>
    Appointments
  </a>
  <a href="admin_appointments_history.php" class="<?= $current === 'admin_appointments_history.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    Appointment History
  </a>
  <a href="admin_dentists.php" class="<?= $current === 'admin_dentists.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6a3 3 0 100 6 3 3 0 000-6zM4 20a8 8 0 1116 0"/>
    </svg>
    Dentists
  </a>
  <a href="admin_calendar.php" class="<?= $current === 'admin_calendar.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
    </svg>
    Calendar
  </a>
  <a href="admin_patient_records.php" class="<?= $current === 'admin_patient_records.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
    </svg>
    Patient Records
  </a>
  <a href="admin_inventory.php" class="<?= $current === 'admin_inventory.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
    </svg>
    Inventory
  </a>
  <a href="preferences.php" class="<?= $current === 'preferences.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/>
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>
    Settings
  </a>
  <a href="admin_about.php" class="<?= $current === 'admin_about.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9h.01M11 12h1v4h1m-1-13a9 9 0 110 18 9 9 0 010-18z"/>
    </svg>
    About Us
  </a>
  <a href="logout.php" onclick="return confirm('Are you sure you want to log out?');">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/>
    </svg>
    Logout
  </a>
  <div class="sidebar-footer">
    <div class="text-center py-2">
      <div class="text-muted small">© <?= date('Y') ?> <?= htmlspecialchars($__clinicName) ?></div>
      <div class="text-muted small">All rights reserved</div>
    </div>
  </div>
</div>
