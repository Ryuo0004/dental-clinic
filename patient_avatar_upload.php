<?php
// patient_avatar_upload.php
// Handles avatar upload for logged-in patient; returns JSON

header('Content-Type: application/json');

try {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    if (!isset($_SESSION['email'])) {
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }

    // Initialize DB connection (prefer admin_init if available)
    $baseDir = __DIR__;
    if (file_exists($baseDir . '/admin_init.php')) {
        include $baseDir . '/admin_init.php';
    } else {
        include $baseDir . '/db_connect.php';
    }

    if (!isset($_FILES['avatar']) || !is_uploaded_file($_FILES['avatar']['tmp_name'])) {
        echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['avatar'];
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        echo json_encode(['ok' => false, 'error' => 'Invalid image type. Use JPG, PNG, or WEBP.']);
        exit;
    }

    if ($file['size'] > 2 * 1024 * 1024) { // 2MB
        echo json_encode(['ok' => false, 'error' => 'Image too large. Max 2MB.']);
        exit;
    }

    $ext = $allowed[$mime];
    $email = $_SESSION['email'];
    $safeEmail = preg_replace('/[^a-zA-Z0-9]/', '_', $email);

    $dir = __DIR__ . '/uploads/patient_photos';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            echo json_encode(['ok' => false, 'error' => 'Failed to create upload directory']);
            exit;
        }
    }

    $filename = $safeEmail . '_' . time() . '.' . $ext;
    $destAbs = $dir . '/' . $filename;
    $destRel = 'uploads/patient_photos/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
        echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
        exit;
    }

    // Optionally delete old photo to avoid orphaned files
    $old = null;
    if ($st = $conn->prepare('SELECT photo_url FROM tbl_patient WHERE Email = ? LIMIT 1')) {
        $st->bind_param('s', $email);
        if ($st->execute()) {
            $res = $st->get_result();
            if ($row = $res->fetch_assoc()) {
                $old = trim((string)($row['photo_url'] ?? ''));
            }
        }
        $st->close();
    }

    if ($up = $conn->prepare('UPDATE tbl_patient SET photo_url = ? WHERE Email = ? LIMIT 1')) {
        $up->bind_param('ss', $destRel, $email);
        if (!$up->execute()) {
            echo json_encode(['ok' => false, 'error' => 'DB update failed']);
            exit;
        }
        $up->close();
    }

    // Clean up old photo file if it exists and is within our uploads folder
    if ($old && strpos($old, 'uploads/patient_photos/') === 0) {
        $oldAbs = __DIR__ . '/' . $old;
        if (is_file($oldAbs)) { @unlink($oldAbs); }
    }

    echo json_encode(['ok' => true, 'url' => $destRel]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
