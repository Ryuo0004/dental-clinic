<?php
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include 'admin_init.php';
} else {
  include 'db_connect.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
if (file_exists($baseDir . '/settings_helper.php')) {
  include_once 'settings_helper.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>About Us - Miles Dental Clinic</title>
  <?php if (file_exists($baseDir . '/header.php')) { include 'header.php'; } ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    html, body { overflow-x: hidden; }
    body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f3f8ff; }
    .content-wrapper { flex: 1; min-height: 100vh; background-color: #f3f8ff; }
    .content { padding: 16px; color: #111827; }
    .card { border: 1px solid #e5e7eb; background: #ffffff; color: #111827; border-radius: 14px; }
    /* Consistent researcher card and photo sizing */
    .researcher-card { align-items: center; }
    .researcher-photo { width: 90px !important; height: 90px !important; object-fit: cover; border-radius: 10px; border: 1px solid #e5e7eb; flex: 0 0 90px; display: block; }
    .about-fixed .sidebar { position: fixed !important; left: 0; top: 0; bottom: 0; height: 100vh; overflow: hidden; }
    .about-fixed .content-wrapper { margin-left: 260px; }
    @media (max-width: 768px) {
      .about-fixed .sidebar { position: fixed !important; transform: none; width: 240px; }
      .about-fixed .content-wrapper { margin-left: 240px; }
    }
  </style>
</head>
<body class="about-fixed">
<div class="app-layout d-flex" style="min-height:100vh; width:100%; margin:0; padding:0;">
  <?php if (file_exists($baseDir . '/admin_sidebar.php')) { include 'admin_sidebar.php'; } ?>
  <div class="content-wrapper">
    <div class="content">
      <div class="card mb-3">
        <div class="card-body d-flex align-items-center justify-content-between" style="padding:18px 20px;">
          <div>
            <h4 class="mb-1" style="font-weight:700;">About <?= htmlspecialchars($clinicBranding['name'] ?? 'Miles Dental Clinic') ?></h4>
          </div>
          <?php if (!empty($clinicBranding['logo_url'])): ?>
            <img src="<?= htmlspecialchars($clinicBranding['logo_url']) ?>" alt="Clinic Logo" style="height:40px;width:40px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;">
          <?php endif; ?>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-body">
          <h4 class="mb-2" style="font-weight:700;">About the Researchers</h4>
          <p class="text-muted mb-3">This Dental Clinic Management System was created by BS Information Technology students as part of their Project Management course requirement. The system is designed to automate appointment scheduling, patient management, and record handling for Miles Dental Clinic..</p>

          <div class="row g-3">
            <div class="col-md-4">
              <div class="card h-100" style="border:1px solid #e5e7eb;">
                <div class="card-body d-flex gap-3 researcher-card">
                  <img class="researcher-photo"src="" alt="Lawrence B. Valderama">
                  <div class="flex-grow-1">
                    <div class="fw-bold">Lawrence B. Valderama</div>
                    <div class="small text-muted"> Programmer</div>
                    <div class="mt-2 small"><strong>Address:</strong> Poblacion, Mabini, Pangasinan</div>
                    <div class="small"><strong>Course:</strong> Bachelor of Science in Computer Science</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="card h-100" style="border:1px solid #e5e7eb;">
                <div class="card-body d-flex gap-3 researcher-card">
                  <img class="researcher-photo" src="" alt="Rg Krulak L. Baroma">
                  <div class="flex-grow-1">
                    <div class="fw-bold">Rg Krulak L. Baroma</div>
                    <div class="small text-muted">Programmer</div>
                    <div class="mt-2 small"><strong>Address:</strong> Calzada, Mabini, Pangasinan</div>
                    <div class="small"><strong>Course:</strong> Bachelor of Science in Computer Science</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="card h-100" style="border:1px solid #e5e7eb;">
                <div class="card-body d-flex gap-3 researcher-card">
                  <img class="researcher-photo" src="" alt="Brian Karl A. Petalver">
                  <div class="flex-grow-1">
                    <div class="fw-bold">Brian Karl A. Petalver</div>
                    <div class="small text-muted">Documentation Specialist</div>
                    <div class="mt-2 small"><strong>Address:</strong> Banog Sur, Bani, Pangasinan</div>
                    <div class="small"><strong>Course:</strong> Bachelor of Science in Computer Science</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
           

          <div class="row g-3 mt-2">
            <div class="col-12">
              <div class="card" style="border:1px solid #e5e7eb;">
                <div class="card-body text-center" style="font-size:14px;">
                  <div><strong>Course:</strong> Bachelor of Science in Computer Science</div>
                  <div><strong>Subject:</strong>Project Management</div>
                  <div><strong>Institution:</strong> PASS College</div>
                  <div><strong>Academic Year:</strong> 2025–2026</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
