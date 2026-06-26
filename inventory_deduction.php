<?php
// Inventory deduction functions for treatment completion
// Include this file in admin_appointments.php

function normalizeTreatmentName($name) {
    $n = trim((string)$name);
    $lc = strtolower($n);
    if (strpos($lc, 'extraction') !== false) { return 'Tooth Extraction'; }
    return $n;
}

/**
 * Automatically deduct inventory items when a treatment is completed
 * @param mysqli $conn Database connection
 * @param string $treatmentName The name of the completed treatment
 * @return array Result array with success status and details
 */
function deductInventoryForTreatment($conn, $treatmentName) {
    $result = [
        'success' => false,
        'deducted_items' => [],
        'failed_items' => [],
        'errors' => []
    ];
    
    try {
        $treatmentName = normalizeTreatmentName($treatmentName);
        // Detect treatment inventory mapping column names to avoid referencing non-existent columns
        $tiCols = [];
        if ($cr = $conn->query("SHOW COLUMNS FROM tbl_treatment_inventory")) {
            while ($c = $cr->fetch_assoc()) { $tiCols[strtolower($c['Field'])] = $c['Field']; }
        }
        $qtyMapCol = isset($tiCols['quantity_used']) ? 'Quantity_Used' : (isset($tiCols['quantity_required']) ? 'Quantity_Required' : 'Quantity_Required');
        $itemMapCol = isset($tiCols['item_name']) ? 'Item_Name' : 'Item_Name';
        $itemIdCol  = isset($tiCols['item_id']) ? 'Item_Id' : null;

        // Build mapping query using existing column names
        $selectId = $itemIdCol ? (", $itemIdCol AS Map_Item_Id") : '';
        $mappingQuery = "SELECT $itemMapCol AS Item_Name, $qtyMapCol AS Quantity_Used".$selectId." FROM tbl_treatment_inventory WHERE Treatment_Name = ?";
        $mappingStmt = $conn->prepare($mappingQuery);
        
        if (!$mappingStmt) {
            $result['errors'][] = "Failed to prepare mapping query: " . $conn->error;
            return $result;
        }
        
        $mappingStmt->bind_param("s", $treatmentName);
        $mappingStmt->execute();
        $mappingResult = $mappingStmt->get_result();
        
        if ($mappingResult->num_rows === 0) {
            $result['errors'][] = "No inventory mapping found for treatment: " . $treatmentName;
            $mappingStmt->close();
            return $result;
        }
        
        // Start transaction for atomicity
        $conn->begin_transaction();
        
        while ($mapping = $mappingResult->fetch_assoc()) {
            $itemName = $mapping['Item_Name'];
            $quantityToDeduct = (int)(is_numeric($mapping['Quantity_Used']) ? $mapping['Quantity_Used'] : 0);
            if ($quantityToDeduct < 1) {
                $result['failed_items'][] = [
                    'item' => $itemName,
                    'reason' => 'Invalid quantity to deduct (must be a whole number >= 1)'
                ];
                continue;
            }
            
            // Detect inventory column names
            $invCols = [];
            if ($icr = $conn->query("SHOW COLUMNS FROM tbl_inventory")) {
                while ($c = $icr->fetch_assoc()) { $invCols[strtolower($c['Field'])] = $c['Field']; }
            }
            $idCol = isset($invCols['inventory_id']) ? 'Inventory_Id' : (isset($invCols['item_id']) ? 'Item_Id' : 'Item_Id');
            $nameCol = isset($invCols['item_name']) ? 'Item_Name' : (isset($invCols['item_name']) ? 'Item_Name' : 'Item_Name');
            $qtyCol = isset($invCols['current_stock']) ? 'Current_Stock' : (isset($invCols['quantity']) ? 'Quantity' : 'Quantity');
            $lastCol = isset($invCols['updated_at']) ? 'Updated_At' : (isset($invCols['last_update']) ? 'Last_update' : 'Updated_At');

            // Initialize mapItemId from mapping data if available
            $mapItemId = isset($mapping['Item_Id']) ? (int)$mapping['Item_Id'] : 0;
            
            // Build inventory query without referencing missing columns
            if ($mapItemId > 0) {
                $inventoryQuery = "SELECT ".$idCol." AS Inventory_Id, ".$nameCol." AS Item_name, ".$qtyCol." AS Quantity FROM tbl_inventory WHERE ".$idCol." = ?";
                $inventoryStmt = $conn->prepare($inventoryQuery);
                if (!$inventoryStmt) { $result['errors'][] = "Failed to prepare inventory query by id"; continue; }
                $inventoryStmt->bind_param("i", $mapItemId);
            } else {
                // Robust name match: case/space-insensitive
                $inventoryQuery = "SELECT ".$idCol." AS Inventory_Id, ".$nameCol." AS Item_name, ".$qtyCol." AS Quantity FROM tbl_inventory WHERE LOWER(TRIM(".$nameCol.")) = LOWER(TRIM(?))";
                $inventoryStmt = $conn->prepare($inventoryQuery);
                if (!$inventoryStmt) { $result['errors'][] = "Failed to prepare inventory query for item: " . $itemName; continue; }
                $inventoryStmt->bind_param("s", $itemName);
            }
            $inventoryStmt->execute();
            $inventoryResult = $inventoryStmt->get_result();
            
            if ($inventoryResult->num_rows === 0) {
                $result['failed_items'][] = [
                    'item' => $itemName,
                    'reason' => 'Item not found in inventory'
                ];
                $inventoryStmt->close();
                continue;
            }
            
            $inventoryItem = $inventoryResult->fetch_assoc();
            $currentQuantity = (int)$inventoryItem['Quantity'];
            $newQuantity = $currentQuantity - $quantityToDeduct;
            
            // Check if we have enough inventory
            if ($newQuantity < 0) {
                $result['failed_items'][] = [
                    'item' => $itemName,
                    'reason' => 'Insufficient inventory (Current: ' . $currentQuantity . ', Needed: ' . $quantityToDeduct . ')'
                ];
                $inventoryStmt->close();
                continue;
            }
            
            // Update inventory quantity using detected columns
            $tsCol = ($lastCol === 'Updated_At') ? 'Updated_At' : 'Last_update';
            $updateQuery = "UPDATE tbl_inventory SET ".$qtyCol." = ?, ".$tsCol." = NOW() WHERE ".$idCol." = ?";
            $updateStmt = $conn->prepare($updateQuery);
            
            if (!$updateStmt) {
                $result['errors'][] = "Failed to prepare update query for item: " . $itemName;
                $inventoryStmt->close();
                continue;
            }
            
            $updateStmt->bind_param("ii", $newQuantity, $inventoryItem['Inventory_Id']);
            
            if ($updateStmt->execute()) {
                $result['deducted_items'][] = [
                    'item' => $itemName,
                    'quantity_used' => $quantityToDeduct,
                    'remaining' => $newQuantity
                ];
            } else {
                $result['failed_items'][] = [
                    'item' => $itemName,
                    'reason' => 'Failed to update inventory: ' . $updateStmt->error
                ];
            }
            
            $updateStmt->close();
            $inventoryStmt->close();
        }
        
        $mappingStmt->close();
        
        // Commit transaction if no critical errors and at least some items were processed
        if (empty($result['errors']) && (!empty($result['deducted_items']) || !empty($result['failed_items']))) {
            $conn->commit();
            $result['success'] = true;
        } else {
            $conn->rollback();
            if (empty($result['errors'])) {
                $result['errors'][] = "No inventory items found for treatment: " . $treatmentName;
            }
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $result['errors'][] = "Exception occurred: " . $e->getMessage();
    }
    
    return $result;
}

/**
 * Get inventory status for a specific treatment
 * @param mysqli $conn Database connection
 * @param string $treatmentName The name of the treatment
 * @return array Array of inventory items and their availability
 */
function getTreatmentInventoryStatus($conn, $treatmentName) {
    $status = [];
    $treatmentName = normalizeTreatmentName($treatmentName);
    // Detect columns for both tables first to avoid referencing missing ones
    $tiCols = [];$invCols = [];
    if ($cr = $conn->query("SHOW COLUMNS FROM tbl_treatment_inventory")) {
        while ($c = $cr->fetch_assoc()) { $tiCols[strtolower($c['Field'])] = $c['Field']; }
    }
    if ($ir = $conn->query("SHOW COLUMNS FROM tbl_inventory")) {
        while ($c = $ir->fetch_assoc()) { $invCols[strtolower($c['Field'])] = $c['Field']; }
    }
    $qtyMapCol = isset($tiCols['quantity_used']) ? 'Quantity_Used' : (isset($tiCols['quantity_required']) ? 'Quantity_Required' : 'Quantity_Required');
    $tiNameCol = isset($tiCols['item_name']) ? 'Item_Name' : 'Item_Name';
    $tiIdCol   = isset($tiCols['item_id']) ? 'Item_Id' : null;
    $invNameCol = isset($invCols['item_name']) ? 'Item_Name' : 'Item_Name';
    $invQtyCol = isset($invCols['current_stock']) ? 'Current_Stock' : (isset($invCols['quantity']) ? 'Quantity' : 'Quantity');
    $invThrCol = isset($invCols['reorder_level']) ? 'Reorder_Level' : (isset($invCols['threshold']) ? 'Threshold' : 'Threshold');
    $invExpCol = isset($invCols['expiration_date']) ? 'Expiration_Date' : null;

    // Prefer Item_Id match; if Item_Id is NULL/0, fall back to Item_Name match (avoids all-red when ids not populated yet)
    if ($tiIdCol) {
        $idCol = isset($invCols['inventory_id']) ? 'Inventory_Id' : (isset($invCols['item_id']) ? 'Item_Id' : 'Item_Id');
        // Prefer id; fallback to case/space-insensitive name match
        $join = "( (ti.".$tiIdCol." IS NOT NULL AND ti.".$tiIdCol." <> 0 AND i.".$idCol." = ti.".$tiIdCol.") OR ( (ti.".$tiIdCol." IS NULL OR ti.".$tiIdCol." = 0) AND LOWER(TRIM(ti.".$tiNameCol.")) = LOWER(TRIM(i.".$invNameCol.")) ) )";
        $selectExp = $invExpCol ? (", i.".$invExpCol." AS Expiration_Date") : '';
        $query = "SELECT ti.".$tiNameCol." AS Item_Name, ti.".$qtyMapCol." AS Quantity_Used, i.".$invQtyCol." AS Current_Stock, i.".$invThrCol." AS Threshold".$selectExp." FROM tbl_treatment_inventory ti LEFT JOIN tbl_inventory i ON " . $join . " WHERE ti.Treatment_Name = ?";
    } else {
        // Case/space-insensitive name join when no Item_Id in mapping schema
        $selectExp = $invExpCol ? (", i.".$invExpCol." AS Expiration_Date") : '';
        $query = "SELECT ti.".$tiNameCol." AS Item_Name, ti.".$qtyMapCol." AS Quantity_Used, i.".$invQtyCol." AS Current_Stock, i.".$invThrCol." AS Threshold".$selectExp." FROM tbl_treatment_inventory ti LEFT JOIN tbl_inventory i ON LOWER(TRIM(ti.".$tiNameCol.")) = LOWER(TRIM(i.".$invNameCol.")) WHERE ti.Treatment_Name = ?";
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return $status;
    }
    
    $stmt->bind_param("s", $treatmentName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $curr = $row['Current_Stock'] ?? 0;
        $need = $row['Quantity_Used'];
        $thr  = $row['Threshold'] ?? 0;
        $expired = false;
        if (isset($row['Expiration_Date']) && !empty($row['Expiration_Date'])) {
            $expired = strtotime($row['Expiration_Date']) < strtotime(date('Y-m-d'));
        }
        $available = ($curr >= $need) && !$expired;
        $st = ($curr == 0 || $expired) ? 'out_of_stock' : (($curr <= $thr) ? 'low_stock' : 'in_stock');
        $status[] = [
            'item_name' => $row['Item_Name'],
            'quantity_needed' => $need,
            'current_stock' => $curr,
            'threshold' => $thr,
            'matched' => ($row['Current_Stock'] !== null),
            'available' => $available,
            'expired' => $expired,
            'status' => $st
        ];
    }
    $stmt->close();
    return $status;
}

/**
 * Check if treatment can be completed based on inventory availability
 * @param mysqli $conn Database connection
 * @param string $treatmentName The name of the treatment
 * @return array Result with can_complete status and details
 */
function canCompleteTreatment($conn, $treatmentName) {
    $treatmentName = normalizeTreatmentName($treatmentName);
    $inventoryStatus = getTreatmentInventoryStatus($conn, $treatmentName);
    
    $result = [
        'can_complete' => true,
        'blocking_items' => [],
        'warning_items' => [],
        'all_items' => $inventoryStatus
    ];
    
    foreach ($inventoryStatus as $item) {
        if (!$item['available']) {
            $result['can_complete'] = false;
            $result['blocking_items'][] = $item;
        } elseif ($item['status'] === 'low_stock') {
            $result['warning_items'][] = $item;
        }
    }
    
    return $result;
}

/**
 * Log inventory deduction for audit trail
 * @param mysqli $conn Database connection
 * @param int $appointmentId The appointment ID
 * @param string $treatmentName The treatment name
 * @param array $deductionResult The result from deductInventoryForTreatment
 */
function logInventoryDeduction($conn, $appointmentId, $treatmentName, $deductionResult) {
    // Create inventory log table if it doesn't exist
    $createLogTable = "CREATE TABLE IF NOT EXISTS tbl_inventory_log (
        Log_Id INT AUTO_INCREMENT PRIMARY KEY,
        Appointment_Id INT,
        Treatment_Name VARCHAR(255),
        Patient_Name VARCHAR(255) NULL,
        Dentist_Name VARCHAR(255) NULL,
        Item_Name VARCHAR(255),
        Quantity_Deducted INT,
        Remaining_Quantity INT,
        Log_Date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        Status ENUM('success', 'failed', 'insufficient') DEFAULT 'success',
        Notes TEXT,
        INDEX idx_appointment (Appointment_Id),
        INDEX idx_treatment (Treatment_Name),
        INDEX idx_date (Log_Date)
    )";
    
    $conn->query($createLogTable);

    // Auto-migrate: add Patient_Name and Dentist_Name if table already existed without them
    try {
        if ($cr = $conn->query("SHOW COLUMNS FROM tbl_inventory_log")) {
            $have = [];
            while ($c = $cr->fetch_assoc()) { $have[strtolower($c['Field'])] = true; }
            if (!isset($have['patient_name'])) { $conn->query("ALTER TABLE tbl_inventory_log ADD COLUMN Patient_Name VARCHAR(255) NULL AFTER Treatment_Name"); }
            if (!isset($have['dentist_name'])) { $conn->query("ALTER TABLE tbl_inventory_log ADD COLUMN Dentist_Name VARCHAR(255) NULL AFTER Patient_Name"); }
        }
    } catch (Throwable $e) { /* ignore */ }

    // Resolve patient and dentist display names for this appointment (best-effort)
    $patientName = null; $dentistName = null;
    try {
        $apptCols = [];$patCols=[];$denCols=[];
        if ($cr = $conn->query("SHOW COLUMNS FROM tbl_appointments")) { while ($c = $cr->fetch_assoc()) { $apptCols[strtolower($c['Field'])] = $c['Field']; } }
        if ($cr = $conn->query("SHOW COLUMNS FROM tbl_patient")) { while ($c = $cr->fetch_assoc()) { $patCols[strtolower($c['Field'])] = $c['Field']; } }
        if ($cr = $conn->query("SHOW COLUMNS FROM tbl_dentist")) { while ($c = $cr->fetch_assoc()) { $denCols[strtolower($c['Field'])] = $c['Field']; } }
        $apptIdCol = isset($apptCols['appointment_id']) ? $apptCols['appointment_id'] : 'Appointment_Id';
        $apptPatientNameCol = isset($apptCols['patient_name']) ? $apptCols['patient_name'] : null;
        $apptEmailCol = isset($apptCols['email']) ? $apptCols['email'] : (isset($apptCols['patient_email']) ? $apptCols['patient_email'] : null);
        $apptPatientIdCol = isset($apptCols['patient_id']) ? $apptCols['patient_id'] : (isset($apptCols['patientid']) ? $apptCols['patientid'] : null);
        $apptDentistNameCol = isset($apptCols['dentist_name']) ? $apptCols['dentist_name'] : (isset($apptCols['doctor_name']) ? $apptCols['doctor_name'] : null);
        $apptDentistIdCol = isset($apptCols['dentist_id']) ? $apptCols['dentist_id'] : (isset($apptCols['dentistid']) ? $apptCols['dentistid'] : (isset($apptCols['doctor_id']) ? $apptCols['doctor_id'] : (isset($apptCols['doctorid']) ? $apptCols['doctorid'] : null)));

        $patFirst = isset($patCols['first_name']) ? $patCols['first_name'] : null;
        $patMiddle = isset($patCols['middle_name']) ? $patCols['middle_name'] : null;
        $patLast = isset($patCols['last_name']) ? $patCols['last_name'] : null;
        $patFull = isset($patCols['full_name']) ? $patCols['full_name'] : null;
        $patEmail = isset($patCols['email']) ? $patCols['email'] : null;
        $patIdColInPatient = isset($patCols['patient_id']) ? $patCols['patient_id'] : (isset($patCols['id']) ? $patCols['id'] : (isset($patCols['patientid']) ? $patCols['patientid'] : null));

        $denIdCol = isset($denCols['dentist_id']) ? $denCols['dentist_id'] : (isset($denCols['id']) ? $denCols['id'] : (isset($denCols['doctor_id']) ? $denCols['doctor_id'] : null));
        $denNameCol = isset($denCols['name']) ? $denCols['name'] : (isset($denCols['dentist_name']) ? $denCols['dentist_name'] : (isset($denCols['doctor_name']) ? $denCols['doctor_name'] : null));

        // Build query dynamically
        $sel = ["a.`$apptIdCol` AS Appointment_Id"]; $joins = '';
        if ($apptPatientNameCol) { $sel[] = "a.`$apptPatientNameCol` AS Appt_Patient"; }
        if ($apptDentistNameCol) { $sel[] = "a.`$apptDentistNameCol` AS Appt_Dentist"; }
        if ($apptEmailCol && $patEmail) {
            $joins .= " LEFT JOIN tbl_patient p ON p.`$patEmail` = a.`$apptEmailCol`";
            $pn = [];
            if ($patFull) { $pn[] = "p.`$patFull`"; }
            if ($patFirst) { $pn[] = "CONCAT_WS(' ', p.`$patFirst`, p.`$patMiddle`, p.`$patLast`)"; }
            if (!empty($pn)) { $sel[] = implode(', ', $pn) . " AS Patient_From_P"; }
        } elseif ($apptPatientIdCol && $patIdColInPatient) {
            $joins .= " LEFT JOIN tbl_patient p ON p.`$patIdColInPatient` = a.`$apptPatientIdCol`";
            $pn = [];
            if ($patFull) { $pn[] = "p.`$patFull`"; }
            if ($patFirst) { $pn[] = "CONCAT_WS(' ', p.`$patFirst`, p.`$patMiddle`, p.`$patLast`)"; }
            if (!empty($pn)) { $sel[] = implode(', ', $pn) . " AS Patient_From_P"; }
        }
        if ($apptDentistIdCol && $denIdCol && $denNameCol) {
            $joins .= " LEFT JOIN tbl_dentist d ON d.`$denIdCol` = a.`$apptDentistIdCol`";
            $sel[] = "d.`$denNameCol` AS Dentist_From_D";
        }
        $sql = "SELECT ".implode(',', $sel)." FROM tbl_appointments a WHERE a.`$apptIdCol` = ? LIMIT 1".$joins;
        if ($st = $conn->prepare($sql)) {
            $st->bind_param('i', $appointmentId);
            if ($st->execute()) {
                $rs = $st->get_result();
                if ($row = $rs->fetch_assoc()) {
                    $patientName = $row['Appt_Patient'] ?? ($row['Patient_From_P'] ?? null);
                    $dentistName = $row['Appt_Dentist'] ?? ($row['Dentist_From_D'] ?? null);
                }
            }
            $st->close();
        }
    } catch (Throwable $e) { /* ignore */ }
    
    // Log successful deductions
    foreach ($deductionResult['deducted_items'] as $item) {
        $logQuery = "INSERT INTO tbl_inventory_log (Appointment_Id, Treatment_Name, Patient_Name, Dentist_Name, Item_Name, Quantity_Deducted, Remaining_Quantity, Status, Notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'success', 'Automatic deduction for treatment completion')";
        $logStmt = $conn->prepare($logQuery);
        if ($logStmt) {
            $logStmt->bind_param("issssii", $appointmentId, $treatmentName, $patientName, $dentistName, $item['item'], $item['quantity_used'], $item['remaining']);
            $logStmt->execute();
            $logStmt->close();
        }
    }
    
    // Log failed deductions
    foreach ($deductionResult['failed_items'] as $item) {
        $logQuery = "INSERT INTO tbl_inventory_log (Appointment_Id, Treatment_Name, Patient_Name, Dentist_Name, Item_Name, Quantity_Deducted, Remaining_Quantity, Status, Notes) 
                     VALUES (?, ?, ?, ?, ?, 0, 0, 'failed', ?)";
        $logStmt = $conn->prepare($logQuery);
        if ($logStmt) {
            $logStmt->bind_param("isssss", $appointmentId, $treatmentName, $patientName, $dentistName, $item['item'], $item['reason']);
            $logStmt->execute();
            $logStmt->close();
        }
    }
}
?>
