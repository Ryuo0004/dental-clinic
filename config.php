<?php
return [
'smtp_host' => 'smtp.gmail.com',
'smtp_user' => 'faaaannnyy@gmail.com',      // Gmail address
'smtp_pass' => 'vbxsznigdvmwijmo',     // App password
'smtp_port' => 587,
'from_email' => 'faaaannnyy@gmail.com',
'from_name'  => 'Miles Dental Clinic',    // sender name
];
?><?php
// Configuration file for appointment system
// Contains treatment definitions, dentist info, and settings

// Define available dentists
$dentists = [
    ['id' => 1, 'name' => 'Carrel Miles Cabrales', 'specialty' => 'General Dentistry'],
    ['id' => 2, 'name' => 'Catherine Aben Reyes', 'specialty' => 'General Dentistry'],
    ['id' => 3, 'name' => 'Kyle Eve Evangelista', 'specialty' => 'General Dentistry'],
    ['id' => 4, 'name' => 'Bernadine Bungaoen', 'specialty' => 'General Dentistry'],
   
];

// Define available treatments with their durations
$treatments = [
    ['name' => 'Oral Prophylaxis (Cleaning)', 'duration' => 30, 'price' => 60, 'description' => 'Professional teeth cleaning and scaling'],
    ['name' => 'Tooth Restoration (15 min)', 'duration' => 15, 'price' => 70, 'description' => 'Restoration/filling to repair tooth structure'],
    ['name' => 'Oral Surgery (30 min)', 'duration' => 30, 'price' => 100, 'description' => 'Minor oral surgical procedure'],
    ['name' => 'Dentures', 'duration' => 60, 'price' => 90, 'description' => 'Denture measurement/adjustment session'],
    ['name' => 'Panoramic X-Ray (X-Ray)', 'duration' => 15, 'price' => 50, 'description' => 'Panoramic radiograph'],
    ['name' => 'Full Braces', 'duration' => 90, 'price' => 90, 'description' => 'Comprehensive orthodontic braces placement/adjustment (upper and lower)'],
    ['name' => 'Upper Braces', 'duration' => 60, 'price' => 80, 'description' => 'Orthodontic braces placement/adjustment for upper teeth'],
    ['name' => 'Lower Braces', 'duration' => 60, 'price' => 80, 'description' => 'Orthodontic braces placement/adjustment for lower teeth'],
 ['name' => 'Teeth Whitening (2 hours)', 'duration' => 120, 'price' => 70, 'description' => 'Professional whitening treatment']
];

// Build a quick lookup for prices by treatment name
$treatmentPrices = [];
foreach ($treatments as $t) { 
    $treatmentPrices[$t['name']] = $t['price']; 
}

// Calendar thresholds for coloring
$lowThreshold = 5;      // < 5 = green (less customers)
$highThreshold = 10;    // >= 10 = red (full), between = yellow (moderate)

// Clinic hours (7:00 AM - 7:00 PM)
$clinicHours = ['start' => '07:00:00', 'end' => '19:00:00'];
?>
