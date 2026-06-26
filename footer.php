<?php
// Reusable footer component
if (!isset($clinicBranding)) {
    // Ensure $clinicBranding is available, loading it if necessary.
    if (isset($conn) && function_exists('getClinicBranding')) {
        $clinicBranding = getClinicBranding($conn);
    } else {
        $clinicBranding = ['name' => 'Miles Dental Clinic']; // Fallback
    }
}
?>
<style>
    .footer-bar {
        background-color: #0d47a1; /* Dark Blue */
        color: #fff;
        text-align: center;
        padding: 10px 0;
        font-size: 0.9rem;
        width: 100%;
        /* If the page content is short, this can help push it to the bottom */
        /* position: absolute; */
        /* bottom: 0; */
    }
</style>
<!-- Footer intentionally left blank per request -->
