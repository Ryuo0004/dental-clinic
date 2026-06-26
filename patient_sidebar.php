<?php /* Patient Sidebar */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($conn) && file_exists(__DIR__.'/db_connect.php')) { include __DIR__.'/db_connect.php'; }
$current = basename($_SERVER['PHP_SELF'] ?? '');

// Fetch patient avatar/name (if logged in)
$__avatarUrl = '';
$__fullName = '';
$__initials = 'U';
if (isset($_SESSION['email']) && isset($conn)) {
  $__email = $_SESSION['email'];
  if ($st = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(First_name),''), '') AS fn, COALESCE(NULLIF(TRIM(Last_name),''), '') AS ln, COALESCE(NULLIF(TRIM(Photo_url),''), '') AS photo_url, COALESCE(NULLIF(TRIM(Status),''),'Active') AS pstatus FROM tbl_patient WHERE Email = ? LIMIT 1")) {
    $st->bind_param('s', $__email);
    if ($st->execute()) {
      $res = $st->get_result();
      if ($row = $res->fetch_assoc()) {
        $__avatarUrl = (string)($row['photo_url'] ?? '');
        $fn = (string)($row['fn'] ?? '');
        $ln = (string)($row['ln'] ?? '');
        $__fullName = trim($fn.' '.$ln);
        $__pstatus = (string)($row['pstatus'] ?? 'Active');
        $fi = $fn !== '' ? strtoupper($fn[0]) : '';
        $li = $ln !== '' ? strtoupper($ln[0]) : '';
        $__initials = ($fi.$li) !== '' ? ($fi.$li) : 'U';
      }
    }
  }
}
// Compute clinic favicon from settings or uploads fallback
$__favicon = '';
try {
  if (file_exists(__DIR__.'/settings_helper.php')) { include_once __DIR__.'/settings_helper.php'; }
  if (function_exists('getClinicBranding') && isset($conn)) {
    $branding = getClinicBranding($conn);
    if (!empty($branding['logo_url'])) { $__favicon = trim($branding['logo_url']); }
  }
  if ($__favicon === '') {
    $candidates = [];
    foreach (['png','jpg','jpeg','webp','ico'] as $ext) {
      foreach (glob(__DIR__ . "/uploads/clinic_logo*.{$ext}") as $p) { $candidates[] = $p; }
    }
    if (!empty($candidates)) {
      usort($candidates, function($a,$b){ return @filemtime($b) <=> @filemtime($a); });
      $abs = $candidates[0];
      $rel = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $abs);
      $rel = str_replace('\\','/',$rel);
      $__favicon = $rel;
    }
  }
} catch (Throwable $e) {}
?>
<style>
  :root { --bg:#ffffff; --text:#1f2937; --muted:#6b7280; --border:#e5e7eb; --hover:#f8fafc; --active-bg:#e7f0ff; --active-border:#bfdbfe; }
  .sidebar { font-family: Arial, sans-serif; font-size: 14px; color: var(--text); background-color: var(--bg); padding: 16px; min-width: 260px; flex: 0 0 260px; border-right: 1px solid var(--border); }
  @media (min-width: 769px) { .sidebar { position: sticky; top: 0; height: 100vh; } }
  .sidebar .avatar-wrap { display:flex; flex-direction: column; align-items:center; gap:8px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
  .sidebar a { display:flex; align-items:center; gap:12px; color: var(--text) !important; padding: 10px 12px; border-radius: 8px; text-decoration: none; transition: background-color .2s ease, transform .2s ease; }
  .sidebar a svg { width:18px; height:18px; }
  .sidebar a:hover { background-color: var(--hover); transform: translateX(4px); }
  .sidebar a.active { background-color: var(--active-bg); }
  .sidebar .avatar { width: 56px; height: 56px; border-radius: 999px; background: #e5edff; color: #1d4ed8; font-weight: 700; display:flex; align-items:center; justify-content:center; }
  /* Always show initials; no photo rendering */
  .sidebar .avatar-name { font-size: 14px; font-weight: 600; color: var(--text); text-align:center; }
  /* No upload control on avatar */
  .sidebar .status-chip { display:inline-block; margin-top: 2px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
  .sidebar .status-active { background: #dcfce7; color: #166534; }
  .sidebar .status-inactive { background: #fee2e2; color: #991b1b; }
  .sidebar .user-meta { display:flex; align-items:center; justify-content:center; gap:8px; margin-top: 2px; flex-wrap: wrap; }
  .sidebar .menu { margin-top: 12px; display:flex; flex-direction: column; gap: 6px; }
  /* Mobile-specific styles removed */
  .sidebar-toggle-btn { display: none; }
  /* Force mobile off-canvas behavior (overrides) */
  @media (max-width: 900px) {
    .sidebar { position: fixed !important; left: 0 !important; top: 0 !important; bottom: 0 !important; height: 100vh !important; transform: translateX(-100%) !important; box-shadow: 0 8px 24px rgba(0,0,0,0.1) !important; z-index: 11050 !important; transition: transform .25s ease !important; }
    body.sidebar-open .sidebar { transform: translateX(0) !important; }
  }
</style>

<script>
(function(){
  try{
    var icon = <?php echo json_encode($__favicon); ?> || '';
    if(!icon) return;
    var head = document.head || document.getElementsByTagName('head')[0];
    if(!head) return;
    ['icon','shortcut icon','apple-touch-icon'].forEach(function(rel){
      var l = document.createElement('link'); l.rel = rel; l.href = icon; if(rel==='icon') l.type='image/png'; head.appendChild(l);
    });
  }catch(e){}
})();
</script>

<button class="sidebar-toggle-btn" type="button" onclick="document.body.classList.toggle('sidebar-open')" aria-label="Toggle sidebar" style="position:fixed;left:12px;top:12px;z-index:11000;display:none;border:1px solid #e5e7eb;background:#ffffff;color:#111827;border-radius:10px;padding:8px 10px;">
  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
  Menu
</button>

<aside class="sidebar">

  <div class="avatar-wrap">
    <div class="avatar" title="My Account">
      <span id="patientAvatarInitials"><?= htmlspecialchars($__initials) ?></span>
    </div>
    <div class="user-meta">
      <div class="avatar-name"><?= htmlspecialchars($__fullName ?: 'My Account') ?></div>
      <?php if (!empty($__pstatus)): ?>
        <div class="status-chip <?= strtolower($__pstatus)==='active' ? 'status-active' : 'status-inactive' ?>"><?= htmlspecialchars($__pstatus) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <nav class="menu">
  <a href="patient_dashboard.php" class="<?= $current === 'patient_dashboard.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"/></svg>
    Dashboard
  </a>
  <a href="appointment.php" class="<?= $current === 'appointment.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2z"/></svg>
    Appointments
  </a>
  <a href="messages.php" class="<?= $current === 'messages.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6l2-5a9 9 0 1116-3 9 9 0 01-9 9H4z"/></svg>
    FAQ<?= isset($unread_count) && $unread_count > 0 ? ' ('.(int)$unread_count.')' : '' ?>
  </a>
  <a href="records.php" class="<?= $current === 'records.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    Treatment Records
  </a>
  <a href="profile.php" class="<?= $current === 'profile.php' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
    Profile
  </a>
  <a href="logout.php" onclick="return confirm('Are you sure you want to log out?');">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h4a2 2 0 012 2v1"/></svg>
    Logout
  </a>
  </nav>

</aside>
