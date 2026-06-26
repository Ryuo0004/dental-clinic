<?php
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include 'admin_init.php';
} else {
  include 'db_connect.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
if (file_exists($baseDir . '/admin_actions.php')) {
  include 'admin_actions.php';
}
if (file_exists($baseDir . '/admin_queries.php')) {
  include 'admin_queries.php';
}

$__tokenReady = isset($_SESSION['admin_csrf']) && is_string($_SESSION['admin_csrf']) && $_SESSION['admin_csrf'] !== '';
if (!$__tokenReady) {
  try {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
  } catch (Exception $e) {
    $_SESSION['admin_csrf'] = bin2hex(openssl_random_pseudo_bytes(32));
  }
}

$alert = '';

// Show alert after redirect (PRG)
if (isset($_GET['sent']) && $_GET['sent'] === '1') {
  $alert = '<div class="alert alert-success">Reply sent successfully.</div>';
}

// Handle reply to message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = $_POST['csrf'] ?? '';
  if (hash_equals($_SESSION['admin_csrf'], $csrf)) {
    $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    $replyText = trim($_POST['reply_text'] ?? '');
    
    if ($messageId > 0 && $replyText !== '') {
      // Get the patient email from the original message
      $stmt = $conn->prepare("SELECT Email FROM tbl_messages WHERE Id = ? AND Sender = 'Patient' LIMIT 1");
      $stmt->bind_param("i", $messageId);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows > 0) {
        $message = $result->fetch_assoc();
        $patientEmail = $message['Email'];
        
        // Insert the admin reply
        $replyStmt = $conn->prepare("INSERT INTO tbl_messages (Email, Sender, Message, is_read, sent_at) VALUES (?, 'Admin', ?, 0, NOW())");
        $replyStmt->bind_param("ss", $patientEmail, $replyText);
        
        if ($replyStmt->execute()) {
          // Mark the original message as read
          $markRead = $conn->prepare("UPDATE tbl_messages SET is_read = 1 WHERE Id = ?");
          $markRead->bind_param("i", $messageId);
          $markRead->execute();
          $markRead->close();
          
          $replyStmt->close();
          header('Location: admin_messages.php?sent=1');
          exit();
        } else {
          $alert = '<div class="alert alert-danger">Failed to send reply. Please try again.</div>';
        }
        $replyStmt->close();
      } else {
        $alert = '<div class="alert alert-danger">Message not found.</div>';
      }
      $stmt->close();
    } else {
      $alert = '<div class="alert alert-danger">Please provide a message and reply.</div>';
    }
  } else {
    $alert = '<div class="alert alert-danger">Invalid CSRF token.</div>';
  }
}

// Fetch all messages from patients (latest first)
$messages = [];
$stmt = $conn->prepare("SELECT m.Id, m.Email, m.Sender, m.Message, m.is_read, m.sent_at, 
                               p.First_name, p.Last_name 
                        FROM tbl_messages m 
                        LEFT JOIN tbl_patient p ON m.Email = p.Email 
                        WHERE m.Sender = 'Patient' 
                        ORDER BY m.sent_at DESC 
                        LIMIT 100");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
  $messages[] = $row;
}
$stmt->close();

// Count unread messages
$unreadCount = 0;
$unreadStmt = $conn->prepare("SELECT COUNT(*) FROM tbl_messages WHERE Sender = 'Patient' AND is_read = 0");
$unreadStmt->execute();
$unreadStmt->bind_result($unreadCount);
$unreadStmt->fetch();
$unreadStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Messages - Miles Dental Clinic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: Arial, sans-serif; }
    .sidebar {
      height: 100vh;
      background-color: #343a40;
      color: white;
      padding: 20px;
    }
    .sidebar a { color: white; text-decoration: none; display: block; margin: 10px 0; }
    .sidebar a:hover { text-decoration: underline; }
    .content { padding: 20px; }
    .message-card {
      border: 1px solid #dee2e6;
      border-radius: 0.375rem;
      margin-bottom: 1rem;
    }
    .message-card.unread {
      background-color: #e3f2fd;
      border-color: #90caf9;
    }
    .message-card.read {
      background-color: #f8f9fa;
    }
  </style>
</head>
<body>
<div class="d-flex">
  <?php if (file_exists($baseDir . '/admin_sidebar.php')) { include 'admin_sidebar.php'; } else { ?>
  <div class="sidebar">
    <h3>Miles Dental</h3>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="admin_appointments.php">Appointments</a>
    <a href="admin_messages.php">Messages</a>
    <a href="admin_inventory.php">Inventory</a>
    <a href="preferences.php">Settings</a>
    <a href="logout.php">Logout</a>
  </div>
  <?php } ?>

  <div class="content flex-grow-1">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0">Patient Messages<?= $unreadCount > 0 ? ' <span class="badge bg-danger ms-2">' . intval($unreadCount) . ' unread</span>' : '' ?></h2>
      <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
    </div>

    <?= $alert ?>

    <!-- Messages Section -->
    <div class="card">
      <div class="card-body">
        <?php if (empty($messages)): ?>
          <div class="text-center py-4 text-muted">
            <p class="h5">No messages from patients yet.</p>
          </div>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($messages as $message): ?>
              <div class="message-card p-3 <?= $message['is_read'] ? 'read' : 'unread' ?>">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div class="d-flex align-items-center">
                    <span class="fw-bold me-2">
                      <?= htmlspecialchars($message['First_name'] . ' ' . $message['Last_name']) ?>
                    </span>
                    <span class="text-muted small">(<?= htmlspecialchars($message['Email']) ?>)</span>
                    <?php if (!$message['is_read']): ?>
                      <span class="badge bg-danger ms-2">New</span>
                    <?php endif; ?>
                  </div>
                  <span class="text-muted small">
                    <?= date('M j, Y g:i A', strtotime($message['sent_at'])) ?>
                  </span>
                </div>
                <p class="mb-3"><?= nl2br(htmlspecialchars($message['Message'])) ?></p>
                
                <!-- Reply Form -->
                <form method="post" class="mt-3">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf'] ?? '') ?>" />
                  <input type="hidden" name="message_id" value="<?= $message['Id'] ?>" />
                  <div class="row g-2">
                    <div class="col-md-8">
                      <textarea name="reply_text" rows="2" class="form-control form-control-sm" placeholder="Type your reply..."></textarea>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                      <button type="submit" class="btn btn-primary btn-sm">Reply</button>
                    </div>
                  </div>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="card mt-4">
      <div class="card-body">
        <h5 class="card-title">Quick Actions</h5>
        <div class="row">
          <div class="col-md-6">
            <div class="border rounded p-3">
              <h6 class="fw-bold mb-2">Message Statistics</h6>
              <div class="small text-muted">
                <p class="mb-1">Total Messages: <?= count($messages) ?></p>
                <p class="mb-1">Unread Messages: <?= $unreadCount ?></p>
                <p class="mb-0">Read Messages: <?= count($messages) - $unreadCount ?></p>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="border rounded p-3">
              <h6 class="fw-bold mb-2">Response Guidelines</h6>
              <div class="small text-muted">
                <p class="mb-1">• Respond within 24 hours</p>
                <p class="mb-1">• Be professional and courteous</p>
                <p class="mb-1">• Address patient concerns clearly</p>
                <p class="mb-0">• Include relevant appointment info if needed</p>
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


