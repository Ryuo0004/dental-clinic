<?php
$host = "localhost"; // ✅ Local server
$user = "root";      // ✅ Default MySQL username for localhost
$pass = "";          // ✅ Default password is blank in XAMPP/WAMP
$db   = "dentalclinic_db"; // ✅ Change this to your local database name

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
// Ensure UTF-8 (utf8mb4) charset for proper Unicode handling
if (method_exists($conn, 'set_charset')) {
  $conn->set_charset('utf8mb4');
}
?>
