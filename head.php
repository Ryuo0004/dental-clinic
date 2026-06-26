<?php
// Head template with all necessary meta tags and styles
// Usage: include 'head.php'; at the beginning of the <head> section
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<meta name="theme-color" content="#1d4ed8">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

<!-- Preload critical CSS -->
<link rel="preload" href="css/mobile-adaptive.css" as="style">

<!-- Load styles -->
<link rel="stylesheet" href="https://cdn.tailwindcss.com">
<link rel="stylesheet" href="css/mobile-adaptive.css">
<link rel="stylesheet" href="patients.css">

<!-- Favicon and app icons -->
<?php include 'header.php'; ?>

<!-- iOS specific -->
<link rel="apple-touch-startup-image" href="/apple-splash-2048-2732.jpg" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/apple-splash-2732-2048.jpg" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)">

<!-- Android/Chrome -->
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="Miles Dental">

<!-- Windows -->
<meta name="msapplication-TileColor" content="#1d4ed8">
<meta name="msapplication-tap-highlight" content="no">

<!-- PWA related -->
<link rel="manifest" href="/manifest.json">

<!-- Preconnect to external resources -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<!-- Preload critical fonts -->
<link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"></noscript>

<!-- Inline critical CSS for above-the-fold content -->
<style>
  /* Critical CSS for initial render */
  html { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
  body { margin: 0; padding: 0; overflow-x: hidden; }
  * { box-sizing: border-box; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
  
  /* Prevent layout shifts */
  img, svg, video { max-width: 100%; height: auto; }
  
  /* Smooth scrolling */
  @media (prefers-reduced-motion: no-preference) {
    html { scroll-behavior: smooth; }
  }
</style>
