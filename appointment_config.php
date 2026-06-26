<?php
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
    ['name' => 'Oral Prophylaxis (Cleaning)', 'description' => 'Professional teeth cleaning and scaling', 'duration' => 60],
    ['name' => 'Tooth Restoration', 'description' => 'Restoration/filling to repair tooth structure', 'duration' => 30],
    ['name' => 'Tooth Extraction', 'description' => 'Tooth extraction procedure', 'duration' => 30],
    ['name' => 'Oral Surgery', 'description' => 'Minor oral surgical procedure', 'duration' => 60],
    ['name' => 'Dentures', 'description' => 'Denture measurement/adjustment session', 'duration' => 45],
    ['name' => 'Panoramic X-Ray (X-Ray)', 'description' => 'Panoramic radiograph', 'duration' => 15],
    ['name' => 'Full Braces', 'description' => 'Comprehensive orthodontic braces placement/adjustment (upper and lower)', 'duration' => 90],
    ['name' => 'Upper Braces', 'description' => 'Orthodontic braces placement/adjustment for upper teeth', 'duration' => 60],
    ['name' => 'Lower Braces', 'description' => 'Orthodontic braces placement/adjustment for lower teeth', 'duration' => 60],
    ['name' => 'Brace Adjustment', 'description' => 'Regular adjustment of installed orthodontic braces', 'duration' => 30],
    ['name' => 'Teeth Whitening', 'description' => 'Professional whitening treatment', 'duration' => 90],
    ['name' => 'Check-Up', 'description' => 'Routine dental examination and consultation', 'duration' => 15]
];

// Calendar thresholds for coloring
$lowThreshold = 10;      // < 5 = green (less customers)
$highThreshold = 30;    // >= 10 = red (full), between = yellow (moderate)

// Clinic hours (7:00 AM - 7:00 PM)
$clinicHours = ['start' => '07:00:00', 'end' => '19:00:00'];
?>
