<?php
include 'db_connect.php';
include 'settings_helper.php';
include 'appointment_config.php';
// Override treatments with DB-backed list if available; fallback to config
try {
  $dbTreatments = [];
  if ($conn && ($res = $conn->query("SHOW TABLES LIKE 'tbl_treatments'")) && $res->num_rows > 0) {
    $qr = $conn->query("SELECT name, duration, description FROM tbl_treatments ORDER BY name ASC");
    if ($qr) { while ($row = $qr->fetch_assoc()) { $dbTreatments[] = $row; } }
  }
  if (!empty($dbTreatments)) {
    // Merge config-defined treatments not present in DB by name
    $byName = [];
    foreach ($dbTreatments as $r) {
      $n = trim((string)($r['name'] ?? ''));
      if ($n !== '') { $byName[strtolower($n)] = $r; }
    }
    foreach (($treatments ?? []) as $cfg) {
      $n = trim((string)($cfg['name'] ?? ''));
      if ($n === '') continue;
      $key = strtolower($n);
      if (!isset($byName[$key])) {
        $dbTreatments[] = ['name' => $n, 'description' => (string)($cfg['description'] ?? ''), 'duration' => null];
      }
    }
    // Filter out 'Tooth Extraction'
    $treatments = array_values(array_filter($dbTreatments, function($t){
      $n = isset($t['name']) ? trim((string)$t['name']) : '';
      return strcasecmp($n, 'Tooth Extraction') !== 0;
    }));
  }
} catch (Throwable $e) { /* ignore */ }
$clinicBranding = getClinicBranding($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($clinicBranding['name'] ?? 'Miles Dental Clinic') ?></title>
    <?php
        // Favicon: use clinic logo; fallback to latest uploads/clinic_logo*
        $favicon = trim((string)($clinicBranding['logo_url'] ?? ''));
        if ($favicon === '') {
            $candidates = [];
            foreach (['png','jpg','jpeg','webp','ico'] as $ext) {
                foreach (glob(__DIR__ . "/uploads/clinic_logo*.{$ext}") as $p) { $candidates[] = $p; }
            }
            if (!empty($candidates)) {
                usort($candidates, function($a,$b){ return filemtime($b) <=> filemtime($a); });
                $abs = $candidates[0];
                $rel = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $abs);
                $rel = str_replace('\\','/',$rel);
                $favicon = $rel;
            }
        }
        if ($favicon !== '') {
            echo '<link rel="icon" href="'.htmlspecialchars($favicon).'" type="image/png">';
            echo '<link rel="shortcut icon" href="'.htmlspecialchars($favicon).'" type="image/png">';
            echo '<link rel="apple-touch-icon" href="'.htmlspecialchars($favicon).'">';
            echo '<meta name="theme-color" content="#1d4ed8">';
        }
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Header -->
    <header class="home-header">
        <div class="container header-container">
            <div class="logo">
                <?php if (!empty($clinicBranding['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($clinicBranding['logo_url']) ?>" alt="<?= htmlspecialchars($clinicBranding['name']) ?> Logo" class="clinic-logo">
                <?php else: ?>
                    <i class="fas fa-tooth"></i>
                <?php endif; ?>
                <h1><?= htmlspecialchars($clinicBranding['name'] ?? 'Miles Dental Clinic') ?></h1>
            </div>
            <div class="mobile-menu">
                <i class="fas fa-bars"></i>
            </div>
            <nav>
                <ul>
                    <li><a href="#">Home</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#treatments">Treatments</a></li>
                    <li><a href="#dentists">Dentists</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="login.php" class="login-btn">Login/Signup</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Your Smile is Our Priority</h1>
            <p>Experience modern dental care in a comfortable environment. Our team of specialists is dedicated to providing you with the highest quality dental treatments.</p>
            <a href="appointment.php" class="btn">Book an Appointment</a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <div class="container">
            <h2>About Our Clinic</h2>
            <div class="about-content">
                <div class="about-text">
                    <p>Welcome to <?= htmlspecialchars($clinicBranding['name'] ?? 'our clinic') ?>, where we combine cutting-edge technology with compassionate care to give you the healthy, beautiful smile you deserve.</p>
                    <p>Founded in 2010, our clinic has been serving the community with a wide range of dental services. Our team of experienced dentists and hygienists are committed to providing personalized care in a warm and welcoming environment.</p>
                    <p>We believe that dental health is an integral part of overall wellness, and we're dedicated to helping our patients achieve and maintain optimal oral health throughout their lives.</p>
                </div>
                <div class="about-image">
                    <?php if (!empty($clinicBranding['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($clinicBranding['logo_url']) ?>" alt="<?= htmlspecialchars($clinicBranding['name'] ?? 'Clinic') ?> Logo" style="width:100%;height:100%;object-fit:contain;border-radius:12px;background:#fff;">
                    <?php else: ?>
                        <div class="about-placeholder">
                            <i class="fas fa-clinic-medical"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services">
        <div class="container">
            <h2>Our Services</h2>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-teeth"></i>
                    </div>
                    <h3>Teeth Cleaning</h3>
                    <p>Professional cleaning to remove plaque and tartar, keeping your teeth and gums healthy.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-teeth"></i>
                    </div>
                    <h3>Braces & Orthodontics</h3>
                    <p>Correct teeth alignment and bite issues for a healthier, more beautiful smile.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-smile"></i>
                    </div>
                    <h3>Teeth Whitening</h3>
                    <p>Brighten your smile with our professional teeth whitening treatments.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Treatments Section (dynamic) -->
    <section id="treatments" class="section treatments">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">Available Treatments</h2>
                <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
                    <a href="admin_treatments.php" class="btn btn-sm btn-link text-muted p-0" style="text-decoration: none;">
                        <i class="fas fa-cog me-1"></i> Manage
                    </a>
                <?php endif; ?>
            </div>
            <p style="text-align:center;color:#555;margin-bottom:16px;">These match the options in the appointment page</p>
            <div class="services-grid">
                <?php if (!empty($treatments) && is_array($treatments)): foreach ($treatments as $t): ?>
                    <div class="service-card">
                        <h3><?= htmlspecialchars($t['name']) ?></h3>
                       
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </section>

    <!-- Dentists Section -->
    <section id="dentists" class="dentists">
        <div class="container">
            <h2 class="section-title">Meet Our Dentists</h2>
            <div class="dentists-grid">
                <?php
                // Define specializations and descriptions for the four dentists
                $dentistDetails = [
                    1 => ['description' => 'Dr. Cabrales is dedicated to providing comprehensive dental care with a focus on patient comfort and long-term oral health.'],
                    2 => ['description' => 'Dr. Reyes is committed to delivering high-quality dental services and helping patients achieve and maintain a healthy smile.'],
                    3 => ['description' => 'Dr. Evangelista offers a wide range of dental treatments, ensuring a positive and comfortable experience for every patient.'],
                    4 => ['description' => 'Dr. Bungaoen is an expert in preventive and restorative dental care, dedicated to the well-being of her patients.'],
                ];

                if (!empty($dentists)) {
                    foreach ($dentists as $dentist) {
                        $id = $dentist['id'];
                        $details = $dentistDetails[$id] ?? ['description' => 'A dedicated member of our professional dental team, committed to excellent patient care.'];
                        echo '<div class="dentist-card">';
                        echo '    <div class="dentist-image"><i class="fas fa-user-md"></i></div>';
                        echo '    <div class="dentist-info">';
                        echo '        <h3>' . htmlspecialchars($dentist['name']) . '</h3>';
                        echo '        <span class="specialization">' . htmlspecialchars($dentist['specialty'] ?? 'General Dentistry') . '</span>';
                        echo '        <p>' . htmlspecialchars($details['description']) . '</p>';
                        echo '    </div>';
                        echo '</div>';
                    }
                } else {
                    echo '<p>Our team of dentists will be listed here soon.</p>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <h2>Contact Us</h2>
            <div class="contact-content">
                <div class="contact-info">
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h3>Address</h3>
                            <p><?= htmlspecialchars($clinicBranding['address'] ?? 'Clinic 291 Rizal St, Poblacion, Mabini, 2409 Pangasinan') ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div>
                            <h3>Phone</h3>
                            <p><?= htmlspecialchars($clinicBranding['phone_number'] ?? '') ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <h3>Email</h3>
                            <p><?= htmlspecialchars($clinicBranding['contact_email'] ?? '') ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h3>Clinic Hours</h3>
                            <table class="hours-table">
                                <tr>
                                    <td>Clinic Hours</td>
                                    <td><?= htmlspecialchars($clinicBranding['clinic_hours'] ?? '') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="map-container">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d3833.9333280980622!2d119.9376761!3d16.0689492!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3393e99be8bc138d%3A0x87631158085f512!2sMiles%20Dental%20Clinic!5e0!3m2!1sen!2sph!4v1758895733884!5m2!1sen!2sph" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="home-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3><?= htmlspecialchars($clinicBranding['name'] ?? 'Miles Dental Clinic') ?></h3>
                    <p>Providing exceptional dental care with a focus on patient comfort and satisfaction.</p>
                    <div class="social-icons">
                        <a href="https://www.facebook.com/share/17Wn8Hgmpz/" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="#">Home</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#dentists">Dentists</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Our Services</h3>
                    <ul class="footer-links">
                        <li><a href="#services">Teeth Cleaning</a></li>
                        <li><a href="#services">Braces & Orthodontics</a></li>
                        
                        <li><a href="#services">Teeth Whitening</a></li>
                        <li><a href="#services">Dental Implants</a></li>
                    </ul>
                </div>
              
            <div class="copyright">
                <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($clinicBranding['name'] ?? 'Miles Dental Clinic') ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu').addEventListener('click', function() {
            document.querySelector('nav ul').classList.toggle('show');
        });
    </script>
</body>
</html>
