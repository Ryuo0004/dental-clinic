<?php
// Reusable header snippet for favicon/branding
// Usage: include 'header.php'; inside the <head> section after <title>

// Attempt to load settings helper for branding if available
$__favicon = '';
try {
  if (file_exists(__DIR__ . '/settings_helper.php')) {
    include_once __DIR__ . '/settings_helper.php';
  }
  if (function_exists('getClinicBranding')) {
    // $conn is expected to be available in including scope
    $branding = isset($conn) ? getClinicBranding($conn) : ['logo_url' => ''];
    if (!empty($branding['logo_url'])) { $__favicon = trim($branding['logo_url']); }
  }
} catch (Throwable $e) {
  // Silently ignore; we'll fallback to uploads
}

if ($__favicon === '') {
  // Fallback: find latest uploads/clinic_logo*
  $candidates = [];
  foreach (['png','jpg','jpeg','webp','ico'] as $ext) {
    foreach (glob(__DIR__ . "/uploads/clinic_logo*.{$ext}") as $p) { $candidates[] = $p; }
  }
  if (!empty($candidates)) {
    usort($candidates, function($a,$b){ return @filemtime($b) <=> @filemtime($a); });
    $abs = $candidates[0];
    $rel = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $abs);
    $rel = str_replace('\\', '/', $rel);
    $__favicon = $rel;
  }
}

if ($__favicon !== '') {
  echo '<link rel="icon" href="'.htmlspecialchars($__favicon).'" type="image/png">' . "\n";
  echo '<link rel="shortcut icon" href="'.htmlspecialchars($__favicon).'" type="image/png">' . "\n";
  echo '<link rel="apple-touch-icon" href="'.htmlspecialchars($__favicon).'">' . "\n";
  echo '<meta name="theme-color" content="#1d4ed8">' . "\n";
}
