<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['email'])) {
  header("Location: login.php");
  exit();
}

$email = $_SESSION['email'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// CSRF token for destructive actions
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch patient (for header)
$stmt = $conn->prepare("SELECT First_name, Middle_name, Last_name FROM tbl_patient WHERE Email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { echo "Patient not found."; exit(); }
$p = $result->fetch_assoc();
$full_name = $p['First_name'] . ' ' . $p['Middle_name'] . ' ' . $p['Last_name'];
$stmt->close();

// Fetch message
$msg = null;
$alert = '';

// Handle delete from this page as well (POST only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
  $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Invalid request token.</div>';
  } else {
    $delId = intval($_POST['message_id'] ?? 0);
    if ($delId > 0) {
      $del = $conn->prepare("DELETE FROM tbl_messages WHERE Id = ? AND Email = ? LIMIT 1");
      $del->bind_param("is", $delId, $email);
      if ($del->execute() && $del->affected_rows > 0) {
        $del->close();
        header('Location: messages.php?deleted=1');
        exit();
      }
      $err = htmlspecialchars($del->error);
      $del->close();
      $alert = $alert ?: '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Failed to delete message' . ($err ? ': ' . $err : '.') . '</div>';
    }
  }
}
$m = $conn->prepare("SELECT Id, `Sender` AS sender, `Message` AS message, is_read, sent_at FROM tbl_messages WHERE Id = ? AND Email = ? LIMIT 1");
$m->bind_param("is", $id, $email);
$m->execute();
$res = $m->get_result();
if ($res && $res->num_rows > 0) {
  $msg = $res->fetch_assoc();
}
$m->close();

if (!$msg) {
  echo "Message not found.";
  exit();
}

// Mark as read
if (!$msg['is_read']) {
  $mr = $conn->prepare("UPDATE tbl_messages SET is_read = 1 WHERE Id = ? AND Email = ?");
  $mr->bind_param("is", $id, $email);
  $mr->execute();
  $mr->close();
}

// Unread count
$unread_count = 0;
$unread_stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_messages WHERE Email = ? AND is_read = 0");
$unread_stmt->bind_param("s", $email);
$unread_stmt->execute();
$unread_stmt->bind_result($unread_count);
$unread_stmt->fetch();
$unread_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Message</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800">
<div class="min-h-screen bg-gray-50">
  <!-- Header -->
  <header class="bg-white shadow flex items-center justify-between px-8 py-4">
    <div class="flex items-center space-x-4">
      <span class="text-2xl font-bold text-blue-700">DentalCare</span>
      <nav class="space-x-6 hidden md:block">
        <a href="patient_dashboard.php" class="hover:text-blue-600">Dashboard</a>
        <a href="appointment.php" class="hover:text-blue-600">Appointments</a>
        <a href="messages.php" class="text-blue-700 font-semibold">Messages<?= $unread_count > 0 ? ' <span class=\'ml-1 inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full\'>' . intval($unread_count) . '</span>' : '' ?></a>
        <a href="profile.php" class="hover:text-blue-600">Profile</a>
      </nav>
    </div>
    <div class="flex items-center space-x-4">
      <span class="font-medium text-gray-700"><?= htmlspecialchars($full_name) ?></span>
      <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Logout</a>
    </div>
  </header>

  <main class="max-w-4xl mx-auto p-4 md:p-8 space-y-6">
    <a href="messages.php" class="text-blue-700 hover:underline">← Back to inbox</a>
    <div class="bg-white shadow rounded-xl p-6">
      <?= $alert ?>
      <div class="flex justify-between items-center mb-3">
        <div>
          <div class="text-sm text-gray-500">From</div>
          <div class="font-semibold"><?= htmlspecialchars($msg['sender']) ?></div>
        </div>
        <div class="text-sm text-gray-500"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($msg['sent_at']))) ?></div>
      </div>
      <div class="border-t pt-4 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
      <div class="mt-6 flex gap-3">
        <?php if (strtolower($msg['sender']) === 'patient'): ?>
          <a href="messages.php?edit_id=<?= $msg['Id'] ?>" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Edit</a>
        <?php endif; ?>
        <form method="post" action="message_view.php?id=<?= $msg['Id'] ?>" onsubmit="return confirm('Delete this message? This cannot be undone.');">
          <input type="hidden" name="message_id" value="<?= $msg['Id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <button type="submit" name="delete" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Delete</button>
        </form>
      </div>
    </div>
  </main>
</div>
</body>
</html>


