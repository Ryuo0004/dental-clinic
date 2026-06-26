<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db_connect.php';

// Set JSON header
header('Content-Type: application/json');

// Get the action from the request
$action = $_GET['action'] ?? 'check_notifications';

// Only require login for sensitive actions (support both patient and admin sessions)
// Correct session sources: prefer patient email, else admin email
$email = $_SESSION['email'] ?? ($_SESSION['admin_email'] ?? null);
// Admin id if logged in as admin (used by the bell)
$adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;

// Public actions that don't require login
$publicActions = ['get_appointment_dates', 'get_daily_schedule', 'get_pending_confirmations'];

// Check if login is required for this action (allow admin_id OR email)
if (!in_array($action, $publicActions) && !$email && !$adminId) {
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

// Delete a single notification by Id for current user (support admin via Admin_Id)
if ($action === 'delete_notification') {
    if (!$email && !$adminId) { echo json_encode(['success'=>false,'error'=>'Not logged in']); exit(); }
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid id']); exit(); }
    if ($adminId) {
        if ($del = $conn->prepare("DELETE FROM tbl_notifications WHERE Id = ? AND Admin_Id = ?")) {
            $del->bind_param('ii', $id, $adminId);
            $ok = $del->execute();
            $del->close();
            echo json_encode(['success'=>$ok]);
        } else { echo json_encode(['success'=>false]); }
    } else {
        if ($del = $conn->prepare("DELETE FROM tbl_notifications WHERE Id = ? AND Email = ?")) {
            $del->bind_param('is', $id, $email);
            $ok = $del->execute();
            $del->close();
            echo json_encode(['success'=>$ok]);
        } else { echo json_encode(['success'=>false]); }
    }
    exit();
}

// Return recent notifications for the current user (patient or admin)
if ($action === 'get_recent_notifications') {
    if (!$email && !$adminId) { echo json_encode(['success'=>false,'error'=>'Not logged in']); exit(); }
    $items = [];
    if ($adminId) {
        if ($stmt = $conn->prepare("SELECT Id, Email, Message, Type, Is_Read, Created_At FROM tbl_notifications WHERE Admin_Id = ? ORDER BY Created_At DESC, Id DESC LIMIT 20")) {
            $stmt->bind_param('i', $adminId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) { $items[] = $row; }
            }
            $stmt->close();
        }
    } else {
        if ($stmt = $conn->prepare("SELECT Id, Email, Message, Type, Is_Read, Created_At FROM tbl_notifications WHERE Email = ? ORDER BY Created_At DESC, Id DESC LIMIT 20")) {
            $stmt->bind_param('s', $email);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) { $items[] = $row; }
            }
            $stmt->close();
        }
    }
    echo json_encode(['success'=>true,'items'=>$items]);
    exit();
}

// Mark all notifications as read for current user
if ($action === 'mark_all_read') {
    if (!$email && !$adminId) { echo json_encode(['success'=>false,'error'=>'Not logged in']); exit(); }
    if ($adminId) {
        if ($up = $conn->prepare("UPDATE tbl_notifications SET Is_Read = 1 WHERE Admin_Id = ? AND Is_Read = 0")) {
            $up->bind_param('i', $adminId);
            $ok = $up->execute();
            $up->close();
            echo json_encode(['success'=>$ok]);
        } else { echo json_encode(['success'=>false]); }
    } else {
        if ($up = $conn->prepare("UPDATE tbl_notifications SET Is_Read = 1 WHERE Email = ? AND Is_Read = 0")) {
            $up->bind_param('s', $email);
            $ok = $up->execute();
            $up->close();
            echo json_encode(['success'=>$ok]);
        } else { echo json_encode(['success'=>false]); }
    }
    exit();
}

if ($action === 'get_appointment_dates') {
    // Get all appointment dates with counts for calendar display
    try {
        $stmt = $conn->prepare("
            SELECT 
                a.`Date` AS appointment_date,
                COUNT(*) AS appointment_count
            FROM tbl_appointments a
            WHERE a.`Status` = 'Confirmed'
            GROUP BY a.`Date`
            ORDER BY a.`Date`
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $dates = [];
        $counts = [];
        while ($row = $result->fetch_assoc()) {
            $dates[] = $row['appointment_date'];
            $counts[$row['appointment_date']] = (int)$row['appointment_count'];
        }
        
        echo json_encode([
            'success' => true,
            'dates' => $dates,
            'counts' => $counts
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Could not fetch appointment dates'
        ]);
    }
    exit();
}

if ($action === 'get_pending_confirmations') {
    try {
        $stmt = $conn->prepare("
            SELECT 
                a.`Date` AS appointment_date,
                COUNT(*) AS appointment_count
            FROM tbl_appointments a
            WHERE LOWER(a.`Status`) = 'pending'
            GROUP BY a.`Date`
            ORDER BY a.`Date`
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $dates = [];
        $counts = [];
        while ($row = $result->fetch_assoc()) {
            $dates[] = $row['appointment_date'];
            $counts[$row['appointment_date']] = (int)$row['appointment_count'];
        }
        
        echo json_encode([
            'success' => true,
            'dates' => $dates,
            'counts' => $counts
        ]);
    } catch (Exception $e) {
        error_log('Error in get_pending_confirmations: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Could not fetch pending confirmations: ' . $e->getMessage()
        ]);
    }
    exit();
}

if ($action === 'get_daily_schedule') {
    // Get appointments for a specific date
    $date = $_GET['date'] ?? date('Y-m-d');
    
    try {
        // First, validate the date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('Invalid date format');
        }
        
        // Prepare the query to get appointments for the selected date
        $query = "
            SELECT 
                a.Appointment_Id as appointment_id,
                a.Date as appointment_date,
                a.Time as appointment_time,
                a.Status as status,
                a.`Procedure` as procedure_name,
                a.`Procedure` as treatment,
                a.Patient_Id as patient_id,
                a.Dentist_Id as dentist_id,
                CONCAT(COALESCE(p.First_name, ''), ' ', COALESCE(p.Last_name, '')) as patient_name,
                a.Email as patient_email,
                CASE 
                    WHEN COALESCE(d.is_active, 1) = 1 
                      THEN COALESCE(d.Name, CONCAT('Dentist #', a.Dentist_Id))
                    ELSE 'Unavailable'
                  END as dentist_name
            FROM tbl_appointments a
            LEFT JOIN tbl_patient p ON (a.Patient_Id = p.Patient_Id OR a.Email = p.Email)
            LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id
            WHERE DATE(a.Date) = ? 
            AND LOWER(a.Status) IN ('pending', 'confirmed', 'booked', 'scheduled')
            /* Debug: Fetching all non-cancelled appointments including pending */
            ORDER BY 
                a.Time ASC,
                CASE 
                    WHEN LOWER(a.Status) = 'pending' THEN 0
                    WHEN LOWER(a.Status) = 'confirmed' THEN 1
                    WHEN LOWER(a.Status) = 'booked' THEN 2
                    WHEN LOWER(a.Status) = 'scheduled' THEN 3
                    ELSE 4
                END";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }
        
        $stmt->bind_param('s', $date);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute query: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $appointments = [];
        
        while ($row = $result->fetch_assoc()) {
            $status = strtolower((string)$row['status']);
            if ($status === 'finished') { $status = 'completed'; }

            $appointments[] = [
                'id' => $row['appointment_id'],
                'appointment_date' => $row['appointment_date'],
                'appointment_time' => $row['appointment_time'],
                'status' => $status,
                'procedure' => $row['procedure_name'] ?? 'No procedure specified',
                'procedure_name' => $row['procedure_name'] ?? 'No procedure specified',
                'room' => $row['room'] ?? 'Not assigned',
                'patient_id' => $row['patient_id'],
                'patient_name' => $row['patient_name'] ?: 'Unknown Patient',
                'patient_phone' => $row['patient_phone'] ?? 'N/A',
                'patient_email' => $row['patient_email'] ?? 'N/A',
                'dentist_id' => $row['dentist_id'],
                'dentist_name' => $row['dentist_name'] ?? 'No dentist assigned',
            ];
        }
        
        // Return the appointments data
        echo json_encode([
            'success' => true,
            'appointments' => $appointments,
            'count' => count($appointments)
        ]);
        
        // Log the fetched data for debugging
        error_log('Fetched ' . count($appointments) . ' appointments for date: ' . $date);
        error_log('Sample appointment data: ' . print_r($appointments[0] ?? 'No appointments', true));
        error_log('Sample procedure data: ' . print_r($appointments[0]['procedure'] ?? 'No procedure', true));
        
    } catch (Exception $e) {
        error_log('Error in get_daily_schedule: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'date' => $date,
            'appointments' => []
        ]);
    }
    exit();
}

// Default notification checking
// Check if there are new unread notifications
if ($adminId) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_notifications WHERE Admin_Id = ? AND Is_Read = 0");
    $stmt->bind_param('i', $adminId);
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_notifications WHERE Email = ? AND Is_Read = 0");
    $stmt->bind_param('s', $email);
}
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$unreadCount = (int)($row['count'] ?? 0);
$hasNewNotifications = $unreadCount > 0;

// Also check if there are any completion notifications (which indicate completed appointments)
if ($adminId) {
    $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM tbl_notifications WHERE Admin_Id = ? AND Type = 'appointment_completed' AND Is_Read = 0");
    $stmt2->bind_param('i', $adminId);
} else {
    $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM tbl_notifications WHERE Email = ? AND Type = 'appointment_completed' AND Is_Read = 0");
    $stmt2->bind_param('s', $email);
}
$stmt2->execute();
$result2 = $stmt2->get_result();
$row2 = $result2->fetch_assoc();

$hasRecentCompletions = !empty($row2['count']) && ((int)$row2['count'] > 0);

echo json_encode([
    'hasNewNotifications' => $hasNewNotifications,
    'hasRecentCompletions' => $hasRecentCompletions,
    'count' => $unreadCount,
    'shouldRefresh' => $hasNewNotifications || $hasRecentCompletions
]);
?>
