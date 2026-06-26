<?php
// Calendar functionality for appointment system
// Handles calendar generation and appointment counts

function generateCalendarData($conn, $year, $month) {
    $data = [
        'year' => $year,
        'month' => $month,
        'dailyCounts' => []
    ];

    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', strtotime($start));

    $stmt = $conn->prepare("SELECT Date, COUNT(*) as cnt FROM tbl_appointments WHERE Date BETWEEN ? AND ? AND Status IN ('Booked','Confirmed','Pending','Ongoing') GROUP BY Date");
    if ($stmt) {
        $stmt->bind_param('ss', $start, $end);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $data['dailyCounts'][$row['Date']] = intval($row['cnt']);
                }
            }
        }
        $stmt->close();
    }

    return $data;
}

function getActiveAppointments($conn, $email) {
    $appts = [];
    $stmt = $conn->prepare("SELECT Appointment_Id AS id, Date, Time, `Procedure`, Status FROM tbl_appointments WHERE Email = ? AND Status IN ('Booked','Confirmed','Pending','Ongoing') ORDER BY Date ASC, Time ASC");
    if ($stmt) {
        $stmt->bind_param('s', $email);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res) { while ($row = $res->fetch_assoc()) { $appts[] = $row; } }
        }
        $stmt->close();
    }
    return $appts;
}

function getAllAppointments($conn, $email) {
    $appts = [];
    $appt_list_stmt = $conn->prepare("SELECT a.Appointment_Id AS id, a.Patient_Id, a.Dentist_Id, a.Date, a.Time, COALESCE(d.Name, CONCAT('Dentist #', a.Dentist_Id)) AS Dentist_Name, a.`Procedure`, a.Status FROM tbl_appointments a LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id WHERE a.Email = ? AND a.Status NOT IN ('Finished','cancelled') ORDER BY a.Date ASC, a.Time ASC");
    if ($appt_list_stmt) {
        $appt_list_stmt->bind_param("s", $email);
        if ($appt_list_stmt->execute()) {
            $appt_result = $appt_list_stmt->get_result();
            if ($appt_result) {
                while ($row = $appt_result->fetch_assoc()) {
                    $appts[] = $row;
                }
            }
        }
        $appt_list_stmt->close();
    }
    
    return $appts;
}
?>
