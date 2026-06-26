<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['email'])) {
  header("Location: login.php");
  exit();
}

$email = $_SESSION['email'];

// CSRF token for destructive actions
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch patient data (for header)
$stmt = $conn->prepare("SELECT * FROM tbl_patient WHERE Email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  echo "Patient not found.";
  exit();
}
$patient = $result->fetch_assoc();
$full_name = $patient['First_name'] . ' ' . $patient['Middle_name'] . ' ' . $patient['Last_name'];
$status = $patient['Status'];
$stmt->close();

$alert = '';

// Show alert after redirect (PRG)
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
  $alert = '<div class="bg-green-100 text-green-700 px-4 py-2 rounded mb-4">Message deleted.</div>';
}
if (isset($_GET['updated']) && $_GET['updated'] === '1') {
  $alert = '<div class="bg-green-100 text-green-700 px-4 py-2 rounded mb-4">Message updated.</div>';
}

// Mark read / unread actions
if (isset($_GET['mark_read'])) {
  $msgId = intval($_GET['mark_read']);
  $m = $conn->prepare("UPDATE tbl_messages SET is_read = 1 WHERE Id = ? AND Email = ?");
  $m->bind_param("is", $msgId, $email);
  $m->execute();
  $m->close();
}
if (isset($_GET['mark_unread'])) {
  $msgId = intval($_GET['mark_unread']);
  $m = $conn->prepare("UPDATE tbl_messages SET is_read = 0 WHERE Id = ? AND Email = ?");
  $m->bind_param("is", $msgId, $email);
  $m->execute();
  $m->close();
}

// Handle delete message (POST only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
  $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Invalid request token.</div>';
  } else {
    $msgId = intval($_POST['message_id'] ?? 0);
    if ($msgId > 0) {
      $del = $conn->prepare("DELETE FROM tbl_messages WHERE Id = ? AND Email = ? LIMIT 1");
      $del->bind_param("is", $msgId, $email);
      if ($del->execute()) {
        if ($del->affected_rows > 0) {
          $del->close();
          header('Location: messages.php?deleted=1');
          exit();
        } else {
          $alert = '<div class="bg-yellow-100 text-yellow-800 px-4 py-2 rounded mb-4">Message not found or not owned by you.</div>';
        }
      } else {
        $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Failed to delete message: ' . htmlspecialchars($del->error) . '</div>';
      }
      $del->close();
    }
  }
}

// Handle update/edit message (POST only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
  $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Invalid request token.</div>';
  } else {
    $msgId = intval($_POST['message_id'] ?? 0);
    $newMessage = isset($_POST['message']) ? trim($_POST['message']) : '';
    if ($msgId <= 0) {
      $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Invalid message.</div>';
    } elseif ($newMessage === '') {
      $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Message cannot be empty.</div>';
    } elseif (strlen($newMessage) > 4000) {
      $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Message is too long.</div>';
    } else {
      $upd = $conn->prepare("UPDATE tbl_messages SET `Message` = ? WHERE Id = ? AND Email = ? AND `Sender` = 'Patient' LIMIT 1");
      $upd->bind_param("sis", $newMessage, $msgId, $email);
      if ($upd->execute()) {
        if ($upd->affected_rows > 0) {
          $upd->close();
          header('Location: messages.php?updated=1');
          exit();
        } else {
          $alert = '<div class="bg-yellow-100 text-yellow-800 px-4 py-2 rounded mb-4">Message not found or not editable.</div>';
        }
      } else {
        $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Failed to update message: ' . htmlspecialchars($upd->error) . '</div>';
      }
      $upd->close();
    }
  }
}

// Handle compose/send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
  $message = isset($_POST['message']) ? trim($_POST['message']) : '';
  if ($message === '') {
    $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Message cannot be empty.</div>';
  } elseif (strlen($message) > 4000) {
    $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Message is too long.</div>';
  } else {
    $ins = $conn->prepare("INSERT INTO tbl_messages (Email, sender, message, is_read, sent_at) VALUES (?, 'Patient', ?, 0, NOW())");
    $ins->bind_param("ss", $email, $message);
    if ($ins->execute()) {
      $alert = '<div class="bg-green-100 text-green-700 px-4 py-2 rounded mb-4">Message sent to clinic.</div>';
    } else {
      $alert = '<div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">Failed to send message. Please try again.</div>';
    }
    $ins->close();
  }
}

// Unread count
$unread_count = 0;
$unread_stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_messages WHERE Email = ? AND is_read = 0");
$unread_stmt->bind_param("s", $email);
$unread_stmt->execute();
$unread_stmt->bind_result($unread_count);
$unread_stmt->fetch();
$unread_stmt->close();

// Fetch messages (latest first)
$messages = [];
$limit = 50;
$msg_stmt = $conn->prepare("SELECT Id, `Sender` AS sender, `Message` AS message, is_read, sent_at FROM tbl_messages WHERE Email = ? ORDER BY sent_at DESC LIMIT ?");
$msg_stmt->bind_param("si", $email, $limit);
$msg_stmt->execute();
$msg_result = $msg_stmt->get_result();
while ($row = $msg_result->fetch_assoc()) {
  $messages[] = $row;
}
$msg_stmt->close();

// If editing, fetch the message to edit (must be sent by Patient)
$edit_message = null;
if (isset($_GET['edit_id'])) {
  $editId = intval($_GET['edit_id']);
  if ($editId > 0) {
    $em = $conn->prepare("SELECT Id, `Message` AS message FROM tbl_messages WHERE Id = ? AND Email = ? AND `Sender` = 'Patient' LIMIT 1");
    $em->bind_param("is", $editId, $email);
    $em->execute();
    $er = $em->get_result();
    if ($er && $er->num_rows > 0) {
      $edit_message = $er->fetch_assoc();
    } else {
      $alert = $alert ?: '<div class="bg-yellow-100 text-yellow-800 px-4 py-2 rounded mb-4">Message not found or not editable.</div>';
    }
    $em->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Messages</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="patients.css" />
  <style>
    html { scroll-behavior: smooth; }
    /* Responsive Sidebar Toggle */
    .hamburger { position: fixed; left: 12px; top: 12px; z-index: 11000; display: none; padding: 10px 12px; border:1px solid #e5e7eb; background:#ffffff; color:#111827; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .hamburger svg { width:20px; height:20px; }
    .backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:10900; opacity:0; transition:opacity .2s ease; }
    @media (max-width: 900px) {
      .hamburger { display: inline-flex; align-items:center; gap:8px; }
      /* Sidebar off-canvas */
      .sidebar { position: fixed; left:0; top:0; bottom:0; height:100vh; transform: translateX(-100%); box-shadow:0 8px 24px rgba(0,0,0,0.1); transition: transform .25s ease; z-index: 11050; }
      body.sidebar-open .sidebar { transform: translateX(0); }
      body.sidebar-open .backdrop { display:block; opacity:1; }
    }
  </style>
  <script>
    function confirmMarkUnread(url) {
      if (confirm('Mark this message as unread?')) { window.location = url; }
      return false;
    }
    function confirmDelete() {
      return confirm('Delete this message? This cannot be undone.');
    }
  </script>
<?php /* Keep head clean */ ?>
</head>
<body class="bg-gray-50 text-gray-800">
<button id="menuToggle" class="hamburger" aria-label="Toggle menu">
  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
  Menu
</button>
<div id="drawerBackdrop" class="backdrop"></div>
<div class="min-h-screen bg-gray-50 flex">
  <!-- Header removed -->

  <?php include 'patient_sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-8 space-y-8">
    <?php
      $faqs = [
        ['q' => 'What are the clinic hours?', 'a' => 'Monday to Saturday, 7:00 AM – 7:00 PM. Time slots are in 30-minute intervals.'],
        ['q' => 'How do I book an appointment?', 'a' => 'Go to Appointments, choose a date, pick a time slot, select the treatment, then submit. Your request will be reviewed and confirmed by our staff.'],
        ['q' => 'Can I choose my dentist?', 'a' => 'Yes. You can pick your preferred dentist from the list when booking.'],
        ['q' => 'How do I reschedule or cancel?', 'a' => 'Open Appointments, select your booking, then choose Reschedule or Cancel. You will receive an email notification when changes are processed.'],
        ['q' => 'How late can I book on a given day?', 'a' => 'Appointments must finish by 7:00 PM. Slots that would end after closing are disabled.'],
        ['q' => 'How much does a treatment cost?', 'a' => 'The price is shown when you select a treatment during booking. Payment is made at the clinic.'],
        ['q' => 'What payment methods are available?', 'a' => 'Payments are accepted at the clinic counter. Online payments are not currently available.'],
        ['q' => 'Will I receive notifications?', 'a' => 'Yes. You will receive email notifications for appointment confirmation, cancellation, reschedule, and completion.'],
        ['q' => 'Where can I see my treatment history?', 'a' => 'Go to Treatment Records to view completed appointments and details.'],
        ['q' => 'How do I update my information?', 'a' => 'Use the Profile page to update your contact information and preferences.'],
      ];
    ?>
    <section class="bg-white shadow rounded-xl p-6">
      <h2 class="text-xl font-semibold mb-4">Frequently Asked Questions</h2>
      <div class="space-y-2">
        <?php foreach ($faqs as $i => $item): ?>
          <details class="border rounded-lg p-3 bg-gray-50">
            <summary class="cursor-pointer font-medium text-gray-800"><?= htmlspecialchars($item['q']) ?></summary>
            <div class="mt-2 text-sm text-gray-700"><?= htmlspecialchars($item['a']) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
      
    </section>

    <section class="bg-white shadow rounded-xl p-6">
      <h2 class="text-xl font-semibold mb-4">Emergency Contacts</h2>
      <div class="text-gray-800 space-y-2">
        <p><span class="font-semibold">Office:</span> (555) 123-4567</p>
        <p><span class="font-semibold">After Hours:</span> (555) 987-6543</p>
        <p class="text-sm text-gray-600">For medical emergencies, call your local emergency number immediately.</p>
      </div>
    </section>
  </main>
</div>
<script>
  (function(){
    var btn=document.getElementById('menuToggle');
    var bd=document.getElementById('drawerBackdrop');
    function t(){ document.body.classList.toggle('sidebar-open'); }
    function c(){ document.body.classList.remove('sidebar-open'); }
    if(btn) btn.addEventListener('click', t);
    if(bd) bd.addEventListener('click', c);
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') c(); });
  })();
</script>
</body>
</html>


