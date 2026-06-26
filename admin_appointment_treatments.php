<?php
require_once 'db_connect.php';
require_once 'admin_auth.php';

// Get all available treatments
function getAvailableTreatments($conn) {
    $treatments = [];
    $result = $conn->query("SELECT DISTINCT `Procedure` FROM tbl_appointments WHERE `Procedure` IS NOT NULL AND `Procedure` != ''");
    while ($row = $result->fetch_assoc()) {
        $treatments[] = $row['Procedure'];
    }
    return $treatments;
}

// Get treatments for an appointment
function getAppointmentTreatments($conn, $appointmentId) {
    $treatments = [];
    $stmt = $conn->prepare("SELECT * FROM tbl_appointment_treatments WHERE appointment_id = ?");
    $stmt->bind_param('i', $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $treatments[] = $row;
    }
    return $treatments;
}

// Add a treatment to an appointment
if (isset($_POST['action']) && $_POST['action'] === 'add_treatment' && isset($_POST['appointment_id'])) {
    $appointmentId = (int)$_POST['appointment_id'];
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    $names = $_POST['treatment_name'] ?? [];
    if (!is_array($names)) { $names = [$names]; }

    $stmt = $conn->prepare("INSERT INTO tbl_appointment_treatments (appointment_id, treatment_name, price, duration, notes) VALUES (?, ?, 0, 0, ?)");
    if (!$stmt) { echo json_encode(['success' => false, 'error' => $conn->error]); exit; }

    $inserted = 0; $lastId = null; $err = null;
    foreach ($names as $nm) {
        $nm = trim((string)$nm);
        if ($nm === '') { continue; }
        $safeName = $conn->real_escape_string($nm);
        $stmt->bind_param('iss', $appointmentId, $safeName, $notes);
        if ($stmt->execute()) { $inserted++; $lastId = $stmt->insert_id; }
        else { $err = $conn->error; }
    }
    $stmt->close();

    if ($inserted > 0) { echo json_encode(['success' => true, 'id' => $lastId, 'count' => $inserted]); }
    else { echo json_encode(['success' => false, 'error' => $err ?: 'No treatments added']); }
    exit;
}

// Remove a treatment from an appointment
if (isset($_POST['action']) && $_POST['action'] === 'remove_treatment' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare("DELETE FROM tbl_appointment_treatments WHERE id = ?");
    $stmt->bind_param('i', $id);
    echo json_encode(['success' => $stmt->execute()]);
    exit;
}

// Get appointment details
$appointmentId = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$treatments = [];
$appointment = null;

if ($appointmentId > 0) {
    // Get appointment details including dentist display name
    $result = $conn->query(
        "SELECT a.*, COALESCE(NULLIF(TRIM(d.Name), ''), CONCAT('Dentist #', a.Dentist_Id)) AS Dentist_Name
         FROM tbl_appointments a
         LEFT JOIN tbl_dentist d ON d.Dentist_id = a.Dentist_Id
         WHERE a.Appointment_Id = $appointmentId"
    );
    $appointment = $result ? $result->fetch_assoc() : null;
    
    // Get treatments for this appointment
    $treatments = getAppointmentTreatments($conn, $appointmentId);
}

$availableTreatments = getAvailableTreatments($conn);
// Merge with config-defined treatments for richer list
$treatmentOptions = [];
if (file_exists(__DIR__ . '/appointment_config.php')) {
    include __DIR__ . '/appointment_config.php';
    if (!empty($treatments) && is_array($treatments)) {
        foreach ($treatments as $t) {
            if (!empty($t['name'])) { $treatmentOptions[] = $t['name']; }
        }
    }
}
foreach ($availableTreatments as $t) { if (!in_array($t, $treatmentOptions, true)) { $treatmentOptions[] = $t; } }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Treatments - Miles Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body { padding: 20px; }
        .treatment-item { margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .treatment-actions { margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Manage Treatments for Appointment #<?= $appointmentId ?></h2>
        
        <?php if ($appointment): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Appointment Details</h5>
                </div>
                <div class="card-body">
                    <p><strong>Patient:</strong> <?= htmlspecialchars($appointment['Email']) ?></p>
                    <p><strong>Dentist:</strong> <?= htmlspecialchars($appointment['Dentist_Name'] ?? ('Dentist #' . ($appointment['Dentist_Id'] ?? ''))) ?></p>
                    <p><strong>Date:</strong> <?= date('F j, Y', strtotime($appointment['Date'])) ?></p>
                    <p><strong>Time:</strong> <?= date('g:i A', strtotime($appointment['Time'])) ?></p>
                    <p class="mb-0"><strong>Base Treatment (from appointment):</strong> <?= htmlspecialchars($appointment['Procedure'] ?? '') ?></p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5>Current Treatments</h5>
                </div>
                <div class="card-body" id="treatments-list">
                    <?php if (empty($treatments)): ?>
                        <p class="text-muted">No treatments added yet.</p>
                    <?php else: ?>
                        <?php foreach ($treatments as $treatment): ?>
                            <div class="treatment-item" id="treatment-<?= $treatment['id'] ?>">
                                <h6><?= htmlspecialchars($treatment['treatment_name']) ?></h6>
                                <p class="mb-1"></p>
                                <?php if (!empty($treatment['notes'])): ?>
                                    <p class="mb-2"><?= nl2br(htmlspecialchars($treatment['notes'])) ?></p>
                                <?php endif; ?>
                                <div class="treatment-actions">
                                    <button class="btn btn-sm btn-danger remove-treatment" data-id="<?= $treatment['id'] ?>">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Add New Treatment</h5>
                </div>
                <div class="card-body">
                    <form id="add-treatment-form">
                        <input type="hidden" name="action" value="add_treatment">
                        <input type="hidden" name="appointment_id" value="<?= $appointmentId ?>">
                        
                        <div class="mb-3">
                            <label for="treatment_name" class="form-label">Treatment Name(s)</label>
                            <select class="form-select" id="treatment_name" name="treatment_name[]" multiple required>
                                <?php foreach ($treatmentOptions as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple treatments.</div>
                        </div>
                        
                        
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Add Treatment</button>
                        <a href="admin_appointments.php" class="btn btn-secondary">Back to Appointments</a>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">Appointment not found.</div>
            <a href="admin_appointments.php" class="btn btn-secondary">Back to Appointments</a>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Add treatment
        $('#add-treatment-form').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                url: 'admin_appointment_treatments.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.error || 'Failed to add treatment'));
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });
        
        // Remove treatment
        $(document).on('click', '.remove-treatment', function() {
            if (!confirm('Are you sure you want to remove this treatment?')) {
                return;
            }
            
            const treatmentId = $(this).data('id');
            
            $.ajax({
                url: 'admin_appointment_treatments.php',
                type: 'POST',
                data: {
                    action: 'remove_treatment',
                    id: treatmentId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $(`#treatment-${treatmentId}`).fadeOut(300, function() {
                            $(this).remove();
                            if ($('#treatments-list').children().length === 0) {
                                $('#treatments-list').html('<p class="text-muted">No treatments added yet.</p>');
                            }
                        });
                    } else {
                        alert('Failed to remove treatment');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });
    });
    </script>
</body>
</html>
