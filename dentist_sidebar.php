<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$dentistName = isset($_SESSION['dentist_name']) ? $_SESSION['dentist_name'] : 'Dentist Portal';
$dentistPhoto = '';
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
} catch (Throwable $e) { /* ignore */ }
// If the including page already has a DB connection, try to load the profile photo
if (isset($conn) && $conn instanceof mysqli) {
  $dentistId = isset($_SESSION['dentist_id']) ? (int)$_SESSION['dentist_id'] : 0;
  if ($dentistId > 0) {
    if ($__st = $conn->prepare('SELECT Photo_Url FROM tbl_dentist WHERE Dentist_id = ? LIMIT 1')) {
      $__st->bind_param('i', $dentistId);
      if ($__st->execute()) {
        $__res = $__st->get_result();
        if ($__row = $__res->fetch_assoc()) { $dentistPhoto = trim((string)($__row['Photo_Url'] ?? '')); }
      }
      $__st->close();
    }
  }
}

// Compute initials for fallback avatar
$dentistInitials = '';
if (!empty($dentistName)) {
  $parts = preg_split('/\s+/', trim((string)$dentistName));
  foreach ($parts as $p) { if ($p !== '') { $dentistInitials .= strtoupper($p[0]); } }
  $dentistInitials = substr($dentistInitials, 0, 2);
}
?>
<style>
  :root { --bg:#ffffff; --text:#1f2937; --muted:#6b7280; --border:#e5e7eb; --hover:#f8fafc; --active-bg:#e7f0ff; --active-border:#bfdbfe; }
  .dentist-sidebar { width: 260px; background: var(--bg); color: var(--text); height: 100vh; position: fixed; left:0; top:0; z-index: 9000; border-right: 1px solid var(--border); overflow-y:auto; user-select:none; -webkit-user-drag:none; font-family: Arial, sans-serif; font-size: 14px; }
  @media (max-width: 768px) {
    .dentist-sidebar { transform: translateX(-100%); transition: transform .2s ease-out; }
    body.sidebar-open .dentist-sidebar { transform: translateX(0); }
  }
  .dentist-sidebar .brand { display:flex; align-items:center; gap:10px; padding:18px 16px; border-bottom:1px solid var(--border); }
  .dentist-sidebar .brand .logo { width:38px; height:38px; border-radius:50%; background:#1d4ed8; display:flex; align-items:center; justify-content:center; font-weight:700; color:#ffffff; overflow:hidden; }
  .dentist-sidebar .brand .logo img { width:38px; height:38px; object-fit:cover; border-radius:50%; display:block; }
  .dentist-sidebar a { display:block; color: var(--text); text-decoration:none; padding:12px 16px; margin:6px 10px; border-radius:10px; transition: background .15s ease, transform .15s ease; -webkit-user-drag:none; font-size: inherit; }
  .dentist-sidebar a:hover { background: var(--hover); color: var(--text); transform: translateX(4px); }
  .dentist-sidebar a.active { background: var(--active-bg); color: var(--text); font-weight:700; }
  .dentist-sidebar .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; opacity:.7; padding: 12px 16px 0; color: var(--muted); }
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
<button class="sidebar-toggle-btn" type="button" onclick="document.body.classList.toggle('sidebar-open')" aria-label="Toggle sidebar" style="position:fixed;left:12px;top:12px;z-index:11000;display:none;border:1px solid rgba(0,0,0,0.1);background:#ffffff;color:#111827;border-radius:10px;padding:8px 10px;">
  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
  Menu
</button>
<aside class="dentist-sidebar">
  <div class="brand">
    <div class="logo">
      <?php if (!empty($dentistPhoto)): ?>
        <img src="<?= htmlspecialchars($dentistPhoto) ?>" alt="Profile" />
      <?php else: ?>
        <?= htmlspecialchars($dentistInitials !== '' ? $dentistInitials : 'D') ?>
      <?php endif; ?>
    </div>
    <div>
      <div style="font-weight:700;"></div>
      <div style="font-size:12px; opacity:.8;"><?= htmlspecialchars($dentistName) ?></div>
    </div>
  </div>
  <div class="section-title"></div>
  <?php
    $path = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    function li($href, $label, $curr) {
      $active = ($curr === $href) ? 'active' : '';
      echo '<a class="'.$active.'" href="'.$href.'">'.$label.'</a>';
    }
    li('dentist_dashboard.php', 'Dashboard', $path);
    li('dentist_appointments_schedule.php', 'Appointments & Schedule', $path);
    li('dentist_patient_records.php', 'Patient Records', $path);
    echo '<div class="section-title">Account</div>';
    li('dentist_profile.php', 'Profile', $path);
    // Add logout confirmation
    echo '<a class="'.(($path==='logout.php')?'active':'').'" href="logout.php" onclick="return confirm(\'Are you sure you want to log out?\');">Logout</a>';
  ?>
</aside>
