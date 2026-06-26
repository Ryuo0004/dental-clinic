<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['email'])) {
  header("Location: login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Support</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    .flex-grow {
      flex-grow: 1;
    }
  </style>
</head>
<body class="bg-gray-100">
  <div class="flex flex-grow">
    <div class="min-h-screen">
      <header class="bg-white shadow px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
        <div class="flex items-center gap-4 sm:gap-6">
          <a href="patient_dashboard.php" class="text-blue-600 hover:underline text-sm sm:text-base">← Back to Dashboard</a>
          <h1 class="text-lg sm:text-xl font-semibold text-blue-700">Get Help</h1>
        </div>
      </header>

      <main class="max-w-4xl mx-auto p-4 sm:p-6 lg:p-8">
        <!-- Contact Information Section -->
        <div class="bg-white shadow rounded-xl p-8 mb-8">
          <h2 class="text-2xl font-bold text-blue-700 mb-6 text-center">Contact Information</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
            <!-- Phone Numbers -->
            <div class="space-y-4">
              <div class="flex items-center space-x-4 p-4 bg-blue-50 rounded-lg">
                <div class="bg-blue-600 p-3 rounded-full">
                  <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                  </svg>
                </div>
                <div>
                  <h3 class="font-semibold text-gray-800">Office Phone</h3>
                  <p class="text-blue-600 font-medium"><a href="tel:+639001234567" class="hover:underline">+63 900 123 4567</a></p>
                  <p class="text-sm text-gray-600">Monday - Sunday, 7:00 AM - 7:00 PM</p>
                </div>
              </div>
               
              <div class="flex items-center space-x-4 p-4 bg-red-50 rounded-lg">
                <div class="bg-red-600 p-3 rounded-full">
                  <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                  </svg>
                </div>
                <div>
                  <h3 class="font-semibold text-gray-800">Emergency Line</h3>
                  <p class="text-red-600 font-medium"><a href="tel:+639009876543" class="hover:underline">+63 900 987 6543</a></p>
                  <p class="text-sm text-gray-600">24/7 Emergency Service</p>
                </div>
              </div>
            </div>
            
            <!-- Email and Social -->
            <div class="space-y-4">
              <div class="flex items-center space-x-4 p-4 bg-green-50 rounded-lg">
                <div class="bg-green-600 p-3 rounded-full">
                  <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 4.26a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                  </svg>
                </div>
                <div>
                  <h3 class="font-semibold text-gray-800">Email</h3>
                  <p class="text-green-600 font-medium"><a href="mailto:info@milesdental.com" class="hover:underline">info@milesdental.com</a></p>
                  <p class="text-sm text-gray-600">We'll respond within 24 hours</p>
                </div>
              </div>
              
              <a href="https://www.facebook.com/share/17Wn8Hgmpz" target="_blank" rel="noopener noreferrer" class="flex items-center space-x-4 p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors" aria-label="Open our Facebook page">
                <div class="bg-blue-600 p-3 rounded-full">
                  <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                  </svg>
                </div>
                <div>
                  <h3 class="font-semibold text-gray-800">Facebook</h3>
                  <p class="text-blue-600 font-medium underline">Visit our Facebook</p>
                  <p class="text-sm text-gray-600">Follow us for updates and tips</p>
                </div>
              </a>
            </div>
          </div>
        </div>

        <!-- Location Information -->
        <div class="bg-white shadow rounded-xl p-8 mb-8">
          <h2 class="text-2xl font-bold text-blue-700 mb-6 text-center">Visit Our Clinic</h2>
          
          <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 p-4 bg-gray-50 rounded-lg">
            <div class="bg-gray-600 p-3 rounded-full">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-gray-800">Address</h3>
              <p class="text-gray-700">Rizal Street, Poblacion, Mabini, Pangasinan</p>
              <p class="text-sm text-gray-600">Philippines</p>
            </div>
          </div>
        </div>

        <!-- Business Hours -->
        <div class="bg-white shadow rounded-xl p-8">
          <h2 class="text-2xl font-bold text-blue-700 mb-6 text-center">Business Hours</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            <div class="space-y-3">
              <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                <span class="font-medium text-gray-800">Monday - Sunday</span>
                <span class="text-blue-600 font-medium">7:00 AM - 7:00 PM</span>
              </div>
          
            <div class="bg-yellow-50 p-4 rounded-lg border-l-4 border-yellow-400">
              <h4 class="font-semibold text-yellow-800 mb-2">Emergency Services</h4>
              <p class="text-sm text-yellow-700">For dental emergencies outside business hours, please call our emergency line. We provide 24/7 emergency dental care for urgent situations.</p>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <?php include 'footer.php'; ?>

</body>
</html>
