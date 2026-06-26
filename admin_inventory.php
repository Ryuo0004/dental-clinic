<?php
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include 'admin_init.php';
} else {
  include 'db_connect.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

// Handle actions
$inventoryAlert = '';
// Ensure audit log table exists
if (isset($conn)) {
  $conn->query("CREATE TABLE IF NOT EXISTS tbl_inventory_audit (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Inventory_Id INT NULL,
    Action VARCHAR(32) NOT NULL,
    Changed_By VARCHAR(255) NULL,
    Changed_By_Email VARCHAR(255) NULL,
    Details TEXT NULL,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (Inventory_Id), INDEX (Action), INDEX (Changed_By_Email)
  ) ENGINE=InnoDB");
}
// Detect schema variations early (id/name/qty/threshold/updated columns)
$invCols = [];
if (isset($conn)) {
  if ($cr = $conn->query("SHOW COLUMNS FROM tbl_inventory")) {
    while ($c = $cr->fetch_assoc()) { $invCols[strtolower($c['Field'])] = $c['Field']; }
  }
}
// Prefer modern columns when both exist
$idCol = isset($invCols['inventory_id']) ? 'Inventory_Id' : (isset($invCols['item_id']) ? 'Item_Id' : 'Inventory_Id');
$nameCol = isset($invCols['item_name']) ? 'Item_name' : (isset($invCols['item_name']) ? 'Item_Name' : 'Item_name');
// Quantity expression: prefer Current_Stock, fallback to Quantity, else 0 (force integer)
$qtyExpr = isset($invCols['current_stock']) ? 'CAST(Current_Stock AS SIGNED)' : (isset($invCols['quantity']) ? 'CAST(Quantity AS SIGNED)' : '0');
// Threshold expression: prefer Reorder_Level, fallback to Threshold, else 0
$thrExpr = isset($invCols['reorder_level']) ? 'CAST(Reorder_Level AS SIGNED)' : (isset($invCols['threshold']) ? 'Threshold' : '0');
// Last update: prefer Updated_At, fallback to Last_update, else NULL
$lastExpr = isset($invCols['updated_at']) ? 'Updated_At' : (isset($invCols['last_update']) ? 'Last_update' : 'NULL');
// Description may not exist in your schema; provide a safe expression
$descExpr = isset($invCols['description']) ? 'Description' : "''";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($conn)) {
  $action = $_POST['action'] ?? '';
  
  if ($action === 'add_item') {
    $itemName = trim($_POST['item_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $threshold = (int)($_POST['threshold'] ?? 5);
    $category = trim($_POST['category'] ?? '');
    $usedFor = trim($_POST['used_for'] ?? '');
    $supplier = trim($_POST['supplier'] ?? '');
    $expirationDate = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
    $unitCost = !empty($_POST['unit_cost']) ? (float)$_POST['unit_cost'] : null;
    
    if ($itemName !== '') {
      $stmt = $conn->prepare("INSERT INTO tbl_inventory (Item_name, Description, Quantity, Unit, Threshold, Category, Used_For, Supplier, Expiration_Date, Unit_Cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param('ssissssssd', $itemName, $description, $quantity, $unit, $threshold, $category, $usedFor, $supplier, $expirationDate, $unitCost);
      
      if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        
        // Log to audit
        $auditStmt = $conn->prepare("INSERT INTO tbl_inventory_audit (Inventory_Id, Action, Changed_By, Changed_By_Email, Details) VALUES (?, 'add', ?, ?, ?)");
        $user = $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'Admin';
        $email = $_SESSION['email'] ?? '';
        $details = "Added new item: $itemName, Qty: $quantity, Expiration: " . ($expirationDate ?: 'N/A');
        $auditStmt->bind_param('isss', $newId, $user, $email, $details);
        $auditStmt->execute();
        $auditStmt->close();
        
        $inventoryAlert = "Item '$itemName' added successfully!";
      } else {
        $inventoryAlert = "Error adding item: " . $conn->error;
      }
      $stmt->close();
    }
  }
  
  elseif ($action === 'update_item') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $itemName = trim($_POST['item_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $threshold = (int)($_POST['threshold'] ?? 5);
    $category = trim($_POST['category'] ?? '');
    $usedFor = trim($_POST['used_for'] ?? '');
    $supplier = trim($_POST['supplier'] ?? '');
    $expirationDate = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
    $unitCost = !empty($_POST['unit_cost']) ? (float)$_POST['unit_cost'] : null;
    
    if ($itemId > 0 && $itemName !== '') {
      // Get current values for audit
      $currentStmt = $conn->prepare("SELECT Quantity, Expiration_Date FROM tbl_inventory WHERE Inventory_Id = ?");
      $currentStmt->bind_param('i', $itemId);
      $currentStmt->execute();
      $current = $currentStmt->get_result()->fetch_assoc();
      $currentStmt->close();
      
      $qtyDelta = $quantity - ($current['Quantity'] ?? 0);
      
      $stmt = $conn->prepare("UPDATE tbl_inventory SET Item_name = ?, Description = ?, Quantity = ?, Unit = ?, Threshold = ?, Category = ?, Used_For = ?, Supplier = ?, Expiration_Date = ?, Unit_Cost = ? WHERE Inventory_Id = ?");
      $stmt->bind_param('ssissssssdi', $itemName, $description, $quantity, $unit, $threshold, $category, $usedFor, $supplier, $expirationDate, $unitCost, $itemId);
      
      if ($stmt->execute()) {
        // Log to audit
        $auditStmt = $conn->prepare("INSERT INTO tbl_inventory_audit (Inventory_Id, Action, Changed_By, Changed_By_Email, Details) VALUES (?, 'quantity_change', ?, ?, ?)");
        $user = $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'Admin';
        $email = $_SESSION['email'] ?? '';
        $details = "Updated: $itemName, Qty: $quantity (delta: $qtyDelta), Expiration: " . ($expirationDate ?: 'N/A');
        $auditStmt->bind_param('isss', $itemId, $user, $email, $details);
        $auditStmt->execute();
        $auditStmt->close();
        
        $inventoryAlert = "Item '$itemName' updated successfully!";
      } else {
        $inventoryAlert = "Error updating item: " . $conn->error;
      }
      $stmt->close();
    }
  }
  
  elseif ($action === 'delete_item') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    
    if ($itemId > 0) {
      // Get item name for audit
      $nameStmt = $conn->prepare("SELECT Item_name FROM tbl_inventory WHERE Inventory_Id = ?");
      $nameStmt->bind_param('i', $itemId);
      $nameStmt->execute();
      $item = $nameStmt->get_result()->fetch_assoc();
      $itemName = $item['Item_name'] ?? 'Unknown';
      $nameStmt->close();
      
      $stmt = $conn->prepare("DELETE FROM tbl_inventory WHERE Inventory_Id = ?");
      $stmt->bind_param('i', $itemId);
      
      if ($stmt->execute()) {
        // Log to audit
        $auditStmt = $conn->prepare("INSERT INTO tbl_inventory_audit (Inventory_Id, Action, Changed_By, Changed_By_Email, Details) VALUES (?, 'delete', ?, ?, ?)");
        $user = $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'Admin';
        $email = $_SESSION['email'] ?? '';
        $details = "Deleted item: $itemName";
        $auditStmt->bind_param('isss', $itemId, $user, $email, $details);
        $auditStmt->execute();
        $auditStmt->close();
        
        $inventoryAlert = "Item '$itemName' deleted successfully!";
      } else {
        $inventoryAlert = "Error deleting item: " . $conn->error;
      }
      $stmt->close();
    }
  }
}

// Diagnostics: treatment mapping and stock summary
if (isset($_GET['treatment_debug']) && isset($conn)) {
  header('Content-Type: application/json');
  $tname = trim((string)$_GET['treatment_debug']);
  $out = ['ok'=>true,'treatment'=>$tname,'rows'=>[]];
  if ($tname !== '') {
    // Detect columns
    $tiCols = [];$invCols=[];
    if ($cr=$conn->query("SHOW COLUMNS FROM tbl_treatment_inventory")) { while ($c=$cr->fetch_assoc()) { $tiCols[strtolower($c['Field'])]=$c['Field']; } }
    if ($ir=$conn->query("SHOW COLUMNS FROM tbl_inventory")) { while ($c=$ir->fetch_assoc()) { $invCols[strtolower($c['Field'])]=$c['Field']; } }
    $tiId = isset($tiCols['item_id'])?'Item_Id':null; $tiName = isset($tiCols['item_name'])?'Item_Name':null; $tiQty = isset($tiCols['quantity_required'])?'Quantity_Required':(isset($tiCols['quantity_used'])?'Quantity_Used':null);
    $invId = isset($invCols['inventory_id'])?'Inventory_Id':(isset($invCols['item_id'])?'Item_Id':null);
    $invName = isset($invCols['item_name'])?'Item_Name':null; $invQty = isset($invCols['current_stock'])?'Current_Stock':(isset($invCols['quantity'])?'Quantity':null);
    $invThr = isset($invCols['reorder_level'])?'Reorder_Level':(isset($invCols['threshold'])?'Threshold':null);
    if ($tiName && $tiQty && $invName && $invQty) {
      $rows = [];
      if ($st = $conn->prepare("SELECT " . $tiName . " AS Item_Name, " . ($tiId?:"NULL") . " AS Item_Id, " . $tiQty . " AS Qty FROM tbl_treatment_inventory WHERE Treatment_Name = ?")) {
        $st->bind_param('s', $tname);
        if ($st->execute()) {
          $rs = $st->get_result();
          while ($m = $rs->fetch_assoc()) {
            $mapName = (string)$m['Item_Name']; $mapId = (int)($m['Item_Id'] ?? 0); $need = (int)$m['Qty'];
            $matchType = 'none'; $have = null; $invRow = null;
            if ($mapId>0 && $invId) {
              if ($si = $conn->prepare("SELECT " . $invName . " AS Item_Name, " . $invQty . " AS Qty, " . ($invThr?:"0") . " AS Thr FROM tbl_inventory WHERE " . $invId . " = ? LIMIT 1")) {
                $si->bind_param('i', $mapId); $si->execute(); $ri=$si->get_result(); if ($r=$ri->fetch_assoc()) { $invRow=$r; $have=(int)$r['Qty']; $matchType='id'; } $si->close();
              }
            }
            if ($matchType==='none') {
              if ($sn = $conn->prepare("SELECT " . $invName . " AS Item_Name, " . $invQty . " AS Qty, " . ($invThr?:"0") . " AS Thr FROM tbl_inventory WHERE " . $invName . " = ? LIMIT 1")) {
                $sn->bind_param('s', $mapName); $sn->execute(); $rn=$sn->get_result(); if ($r=$rn->fetch_assoc()) { $invRow=$r; $have=(int)$r['Qty']; $matchType='name'; } $sn->close();
              }
            }
            $out['rows'][] = [
              'mapping_item_name'=>$mapName,
              'mapping_item_id'=>$mapId ?: null,
              'required_qty'=>$need,
              'match_type'=>$matchType,
              'inventory_item'=>(string)($invRow['Item_Name'] ?? ''),
              'current_stock'=>$have,
              'ok'=> ($have !== null && $have >= $need)
            ];
          }
        }
        $st->close();
      }
    }
    // Merge in audit events (add/update) as part of per-item ledger
    try {
      // Resolve Inventory_Id by item name if possible
      $invId = null; $nameColProbe = null;
      if ($cr = $conn->query("SHOW COLUMNS FROM tbl_inventory")) {
        $colsTmp = [];
        while ($c = $cr->fetch_assoc()) { $colsTmp[strtolower($c['Field'])] = $c['Field']; }
        $idF = isset($colsTmp['item_id']) ? $colsTmp['item_id'] : (isset($colsTmp['inventory_id']) ? $colsTmp['inventory_id'] : null);
        $nmF = isset($colsTmp['item_name']) ? $colsTmp['item_name'] : null;
        $nameColProbe = $nmF;
        if ($idF && $nmF) {
          if ($stInv = $conn->prepare("SELECT `".$idF."` AS InvId FROM tbl_inventory WHERE `".$nmF."` = ? LIMIT 1")) {
            $stInv->bind_param('s', $itemName);
            if ($stInv->execute()) { $rs = $stInv->get_result(); if ($row=$rs->fetch_assoc()) { $invId = (int)$row['InvId']; } }
            $stInv->close();
          }
        }
      }
      if ($invId) {
        if ($conn->query("SHOW TABLES LIKE 'tbl_inventory_audit'")) {
          if ($stA = $conn->prepare("SELECT Action, Changed_By, Changed_By_Email, Details, Created_At FROM tbl_inventory_audit WHERE Inventory_Id = ? ORDER BY Created_At DESC")) {
            $stA->bind_param('i', $invId);
            if ($stA->execute()) {
              $rsA = $stA->get_result();
              while ($r = $rsA->fetch_assoc()) {
                $out['rows'][] = [
                  '__kind' => 'audit',
                  'Action' => strtolower($r['Action'] ?? ''),
                  'By' => ($r['Changed_By'] ?: $r['Changed_By_Email'] ?: null),
                  'When' => $r['Created_At'] ?? null,
                  'Treatment_Name' => null,
                  'Appointment_Id' => null,
                  'Quantity_Deducted' => null,
                  'Remaining_Quantity' => null,
                  'Status' => $r['Action'] ?? 'update',
                  'Notes' => $r['Details'] ?? null,
                  'Details' => $r['Details'] ?? null,
                ];
              }
            }
            $stA->close();
          }
        }
      }
      // Sort unified rows by time desc if we have mixed sources
      if (!empty($out['rows'])) {
        usort($out['rows'], function($a,$b){ return strtotime($b['When'] ?? $b['Log_Date'] ?? '1970-01-01') <=> strtotime($a['When'] ?? $a['Log_Date'] ?? '1970-01-01'); });
      }
    } catch (Throwable $e) { /* ignore audit merge */ }
  }
  echo json_encode($out);
  exit;
}

// Item history JSON endpoint: returns recent inventory log entries for a given item name
if (isset($_GET['item_history']) && isset($conn)) {
  header('Content-Type: application/json');
  $itemName = trim((string)$_GET['item_history']);
  $itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
  $out = ['ok'=>true,'item'=>$itemName,'rows'=>[]];
  if ($itemName !== '') {
    $hasLog = $conn->query("SHOW TABLES LIKE 'tbl_inventory_log'");
    if ($hasLog && $hasLog->num_rows > 0) {
      // Build alias list: current inventory name by id, plus mapped item names in tbl_treatment_inventory for this Item_Id
      $aliases = [];
      $aliasesL = [];
      if ($itemId > 0) {
        // Current name by id
        if ($stN = $conn->prepare("SELECT Item_Name FROM tbl_inventory WHERE Item_Id = ?")) {
          $stN->bind_param('i', $itemId);
          if ($stN->execute()) { $rs = $stN->get_result(); if ($row = $rs->fetch_assoc()) { $nm = trim((string)$row['Item_Name']); if ($nm !== '') { $aliases[] = $nm; $aliasesL[] = strtolower($nm); } } }
          $stN->close();
        }
        // Aliases from mapping
        if ($conn->query("SHOW TABLES LIKE 'tbl_treatment_inventory'")) {
          if ($stM = $conn->prepare("SELECT DISTINCT Item_Name FROM tbl_treatment_inventory WHERE Item_Id = ? AND COALESCE(Item_Name,'') <> ''")) {
            $stM->bind_param('i', $itemId);
            if ($stM->execute()) { $rs = $stM->get_result(); while ($row = $rs->fetch_assoc()) { $nm = trim((string)$row['Item_Name']); if ($nm !== '' && !in_array(strtolower($nm), $aliasesL, true)) { $aliases[] = $nm; $aliasesL[] = strtolower($nm); } } }
            $stM->close();
          }
        }
      }
      // Always include the passed itemName as an alias (case-insensitive)
      if ($itemName !== '' && !in_array(strtolower($itemName), $aliasesL, true)) { $aliases[] = $itemName; $aliasesL[] = strtolower($itemName); }

      // Query logs for any alias names (case-insensitive) with the same joins as the "Items Used" list
      if (!empty($aliases)) {
        // Build dynamic IN list with LOWER(l.Item_Name)
        $ph = implode(',', array_fill(0, count($aliases), '?'));
        $sql = "SELECT 
                  l.Item_Name,
                  l.Quantity_Deducted,
                  l.Remaining_Quantity,
                  l.Status,
                  l.Treatment_Name,
                  l.Appointment_Id,
                  l.Log_Date,
                  l.Notes,
                  TRIM(CONCAT(COALESCE(p.First_name,''),' ',COALESCE(p.Last_name,''))) AS Patient_Name,
                  COALESCE(d.Name, a.Dentist_Id) AS Dentist_Name
                FROM tbl_inventory_log l
                LEFT JOIN tbl_appointments a ON l.Appointment_Id = a.Appointment_Id
                LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id
                LEFT JOIN tbl_patient p ON (p.Patient_Id = a.Patient_Id OR p.Email = a.Email)
                WHERE LOWER(l.Item_Name) IN ($ph)
                ORDER BY a.Date DESC, a.Time DESC, l.Log_Date DESC";
        if ($st = $conn->prepare($sql)) {
          // Lowercase parameters for comparison
          $params = array_map(function($s){ return strtolower($s); }, $aliases);
          $types = str_repeat('s', count($params));
          $st->bind_param($types, ...$params);
          if ($st->execute()) {
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) {
              $r['__kind'] = 'deduction';
              $r['Action'] = 'deducted';
              $r['By'] = null;
              $r['When'] = $r['Log_Date'] ?? null;
              // Cleanup any literal template fragments accidentally stored in treatment name
              if (isset($r['Treatment_Name'])) {
                $tn = (string)$r['Treatment_Name'];
                $tn = str_replace("'.htmlspecialchars(\$opt).'", '', $tn);
                $tn = str_replace('".htmlspecialchars($opt)."', '', $tn);
                $tn = preg_replace('/htmlspecialchars\\(\\$opt\\)/i', '', $tn);
                $tn = preg_replace('/\s*,\s*,+/', ', ', $tn);
                $tn = trim($tn, " ,");
                $r['Treatment_Name'] = $tn;
              }
              // Ensure front-end fields align
              if (isset($r['Dentist_Name']) && !isset($r['Doctor_Name'])) { $r['Doctor_Name'] = $r['Dentist_Name']; }
              $out['rows'][] = $r;
            }
          }
          $st->close();
        }
      }
      // Enrich with patient and doctor names using appointments/patient/dentist tables if present
      try {
        $apptIds = array_values(array_unique(array_filter(array_map(function($r){ return isset($r['Appointment_Id']) ? (int)$r['Appointment_Id'] : 0; }, $out['rows']))));
        if (!empty($apptIds)) {
          // Detect columns
          $apptCols = [];$patCols=[];$denCols=[];
          if ($cr = $conn->query("SHOW COLUMNS FROM tbl_appointments")) { while ($c = $cr->fetch_assoc()) { $apptCols[strtolower($c['Field'])] = $c['Field']; } }
          if ($cr = $conn->query("SHOW COLUMNS FROM tbl_patient")) { while ($c = $cr->fetch_assoc()) { $patCols[strtolower($c['Field'])] = $c['Field']; } }
          if ($cr = $conn->query("SHOW COLUMNS FROM tbl_dentist")) { while ($c = $cr->fetch_assoc()) { $denCols[strtolower($c['Field'])] = $c['Field']; } }

          $apptIdCol = isset($apptCols['appointment_id']) ? $apptCols['appointment_id'] : 'Appointment_Id';
          $apptEmailCol = isset($apptCols['email']) ? $apptCols['email'] : (isset($apptCols['patient_email']) ? $apptCols['patient_email'] : null);
          $apptPatientIdCol = isset($apptCols['patient_id']) ? $apptCols['patient_id'] : (isset($apptCols['patientid']) ? $apptCols['patientid'] : null);
          $apptDentistIdCol = isset($apptCols['dentist_id']) ? $apptCols['dentist_id'] : (isset($apptCols['dentistid']) ? $apptCols['dentistid'] : (isset($apptCols['doctor_id']) ? $apptCols['doctor_id'] : (isset($apptCols['doctorid']) ? $apptCols['doctorid'] : null)));
          $apptPatientNameCol = isset($apptCols['patient_name']) ? $apptCols['patient_name'] : null;
          $apptDentistNameCol = isset($apptCols['dentist_name']) ? $apptCols['dentist_name'] : (isset($apptCols['doctor_name']) ? $apptCols['doctor_name'] : null);

          $patFirst = isset($patCols['first_name']) ? $patCols['first_name'] : null;
          $patMiddle = isset($patCols['middle_name']) ? $patCols['middle_name'] : null;
          $patLast = isset($patCols['last_name']) ? $patCols['last_name'] : null;
          $patFull = isset($patCols['full_name']) ? $patCols['full_name'] : null;
          $patEmail = isset($patCols['email']) ? $patCols['email'] : null;
          // patient table id column candidates
          $patIdColInPatient = isset($patCols['patient_id']) ? $patCols['patient_id'] : (isset($patCols['id']) ? $patCols['id'] : (isset($patCols['patientid']) ? $patCols['patientid'] : null));

          $denIdCol = isset($denCols['dentist_id']) ? $denCols['dentist_id'] : (isset($denCols['id']) ? $denCols['id'] : (isset($denCols['doctor_id']) ? $denCols['doctor_id'] : null));
          $denNameCol = isset($denCols['name']) ? $denCols['name'] : (isset($denCols['dentist_name']) ? $denCols['dentist_name'] : (isset($denCols['doctor_name']) ? $denCols['doctor_name'] : null));

          // Build SELECT with safe fallbacks
          $selects = ["a.`$apptIdCol` AS Appointment_Id"];
          if ($apptPatientNameCol) { $selects[] = "a.`$apptPatientNameCol` AS Patient_Name"; }
          if ($apptDentistNameCol) { $selects[] = "a.`$apptDentistNameCol` AS Dentist_Name"; }
          // Patient name from patient table by Email or by Patient_Id
          if (($apptEmailCol && $patEmail && ($patFirst || $patFull)) || ($apptPatientIdCol && $patIdColInPatient)) {
            $nameExpr = [];
            if ($patFull) { $nameExpr[] = "p.`$patFull`"; }
            if ($patFirst) { $nameExpr[] = "CONCAT_WS(' ', p.`$patFirst`, p.`$patMiddle`, p.`$patLast`)"; }
            $pJoinExpr = !empty($nameExpr) ? implode(', ', $nameExpr) : "''";
            $selects[] = "COALESCE(a.`$apptPatientNameCol`, $pJoinExpr) AS Patient_Name_Join";
          }
          // Dentist name from dentist table by Dentist_Id or Doctor_Id
          if (($apptDentistIdCol && $denIdCol && $denNameCol)) {
            $selects[] = "COALESCE(a.`$apptDentistNameCol`, d.`$denNameCol`) AS Dentist_Name_Join";
          }
          if (count($selects) > 0) {
            // Build IN placeholders
            $ph = implode(',', array_fill(0, count($apptIds), '?'));
            $sql = "SELECT ".implode(',', $selects)." FROM tbl_appointments a ";
            if ($apptEmailCol && $patEmail) { $sql .= "LEFT JOIN tbl_patient p ON p.`$patEmail` = a.`$apptEmailCol` "; }
            else if ($apptPatientIdCol && $patIdColInPatient) { // join patient by id when available
              $sql .= "LEFT JOIN tbl_patient p ON p.`$patIdColInPatient` = a.`$apptPatientIdCol` ";
            }
            if ($apptDentistIdCol && $denIdCol) { $sql .= "LEFT JOIN tbl_dentist d ON d.`$denIdCol` = a.`$apptDentistIdCol` "; }
            $sql .= "WHERE a.`$apptIdCol` IN ($ph)";
            if ($stmt = $conn->prepare($sql)) {
              $types = str_repeat('i', count($apptIds));
              $stmt->bind_param($types, ...$apptIds);
              if ($stmt->execute()) {
                $map = [];
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                  $pid = (int)$row['Appointment_Id'];
                  $pname = isset($row['Patient_Name']) ? $row['Patient_Name'] : (isset($row['Patient_Name_Join']) ? $row['Patient_Name_Join'] : '');
                  $dname = isset($row['Dentist_Name']) ? $row['Dentist_Name'] : (isset($row['Dentist_Name_Join']) ? $row['Dentist_Name_Join'] : '');
                  $map[$pid] = ['patient_name'=>$pname, 'doctor_name'=>$dname];
                }
                // Merge into rows
                foreach ($out['rows'] as &$r) {
                  $aid = isset($r['Appointment_Id']) ? (int)$r['Appointment_Id'] : 0;
                  if ($aid && isset($map[$aid])) { $r['Patient_Name'] = $map[$aid]['patient_name']; $r['Doctor_Name'] = $map[$aid]['doctor_name']; }
                }
                unset($r);
              }
              $stmt->close();
            }
          }
        }
      } catch (Throwable $e) { /* ignore enrichment errors */ }
    }
    // Merge in audit rows (quantity_change/add/update) to surface manual adjustments
    try {
      $audInvId = $itemId > 0 ? $itemId : null;
      if (!$audInvId) {
        if ($st = $conn->prepare("SELECT Item_Id FROM tbl_inventory WHERE Item_Name = ? LIMIT 1")) {
          $st->bind_param('s', $itemName);
          if ($st->execute()) { $rs = $st->get_result(); if ($row=$rs->fetch_assoc()) { $audInvId = (int)$row['Item_Id']; } }
          $st->close();
        }
      }
      if ($audInvId) {
        // Fetch Used_For and Expiration_Date once to display for audit rows
        $usedForVal = null; $expVal = null; $currentQty = null;
        if ($stUF = $conn->prepare("SELECT Used_For, Expiration_Date, COALESCE(Current_Stock, Quantity, 0) AS CurrentQty FROM tbl_inventory WHERE Item_Id = ? LIMIT 1")) {
          $stUF->bind_param('i', $audInvId);
          if ($stUF->execute()) { $resUF = $stUF->get_result(); if ($rowUF = $resUF->fetch_assoc()) { $usedForVal = (string)($rowUF['Used_For'] ?? ''); $expVal = isset($rowUF['Expiration_Date']) ? (string)$rowUF['Expiration_Date'] : null; $currentQty = (int)($rowUF['CurrentQty'] ?? 0); } }
          $stUF->close();
        }
        if ($stA = $conn->prepare("SELECT a.Action, a.Changed_By, a.Changed_By_Email, a.Details, a.Created_At, i.Expiration_Date FROM tbl_inventory_audit a LEFT JOIN tbl_inventory i ON i.Item_Id = a.Inventory_Id WHERE a.Inventory_Id = ? ORDER BY a.Created_At DESC")) {
          $stA->bind_param('i', $audInvId);
          if ($stA->execute()) {
            $rsA = $stA->get_result();
            while ($r = $rsA->fetch_assoc()) {
              $act = strtolower($r['Action'] ?? '');
              $det = (string)($r['Details'] ?? '');
              $delta = null;
              if (preg_match('/qty_delta:\s*([+\-]?\d+)/i', $det, $m)) { $delta = (int)$m[1]; }
              $actionNorm = ($act === 'add' || ($delta !== null && $delta > 0)) ? 'added' : ($act === 'quantity_change' ? 'updated' : $act);
              // Prefer per-entry expiration captured in Details (exp_at:YYYY-MM-DD); fallback to current inventory Expiration
              $expFromDetails = null;
              if (preg_match('/exp_at:\s*(\d{4}-\d{2}-\d{2})/i', $det, $mm)) { $expFromDetails = $mm[1]; }
              $out['rows'][] = [
                '__kind' => 'audit',
                'Action' => $actionNorm,
                'By' => ($r['Changed_By'] ?: $r['Changed_By_Email'] ?: null),
                'When' => $r['Created_At'] ?? null,
                'Treatment_Name' => $usedForVal,
                'Appointment_Id' => null,
                'Quantity_Deducted' => null,
                'Qty_Change' => $delta,
                'Remaining_Quantity' => (string)$currentQty,
                'Status' => $r['Action'] ?? 'update',
                'Notes' => $r['Details'] ?? null,
                'Details' => $r['Details'] ?? null,
                'Expiration_Date' => ($expFromDetails ?: ($r['Expiration_Date'] ?? null)),
              ];
            }
          }
          $stA->close();
        }
      }
      if (!empty($out['rows'])) {
        usort($out['rows'], function($a,$b){ return strtotime(($b['When'] ?? $b['Log_Date'] ?? '1970-01-01')) <=> strtotime(($a['When'] ?? $a['Log_Date'] ?? '1970-01-01')); });
      }
    } catch (Throwable $e) { /* ignore audit merge */ }
  }
  echo json_encode($out);
  exit;
}
// Create inventory table if it doesn't exist (extended with Supplier, Expiration_Date, Unit_Cost)
$createInventoryTable = $conn->query("CREATE TABLE IF NOT EXISTS tbl_inventory (
  Inventory_Id INT AUTO_INCREMENT PRIMARY KEY,
  Item_name VARCHAR(255) NOT NULL,
  Description TEXT,
  Quantity INT NOT NULL DEFAULT 0,
  Unit VARCHAR(50),
  Threshold INT DEFAULT 5,
  Category VARCHAR(100) NULL,
  Used_For VARCHAR(255) NULL,
  Supplier VARCHAR(255) NULL,
  Expiration_Date DATE NULL,
  Unit_Cost DECIMAL(10,2) NULL,
  Last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_item_name (Item_name)
)");

// Auto-migrate: add missing columns if table already exists without them
if ($conn) {
  $colRes = $conn->query("SHOW COLUMNS FROM tbl_inventory");
  $cols = [];
  if ($colRes) { while ($c = $colRes->fetch_assoc()) { $cols[strtolower($c['Field'])] = true; } }
  if (!isset($cols['description'])) { $conn->query("ALTER TABLE tbl_inventory ADD COLUMN Description TEXT NULL"); }
  if (!isset($cols['category'])) { $conn->query("ALTER TABLE tbl_inventory ADD COLUMN Category VARCHAR(100) NULL AFTER Threshold"); }
  if (!isset($cols['used_for'])) { $conn->query("ALTER TABLE tbl_inventory ADD COLUMN Used_For VARCHAR(255) NULL AFTER Category"); }
  if (!isset($cols['supplier'])) { $conn->query("ALTER TABLE tbl_inventory ADD COLUMN Supplier VARCHAR(255) NULL AFTER Category"); }
  if (!isset($cols['expiration_date'])) { $conn->query("ALTER TABLE tbl_inventory ADD COLUMN Expiration_Date DATE NULL AFTER Supplier"); }
  if (!isset($cols['unit_cost'])) { $conn->query("ALTER TABLE tbl_inventory ADD COLUMN Unit_Cost DECIMAL(10,2) NULL AFTER Expiration_Date"); }
}

// Resolve column names dynamically to support both schemas
$idCol = isset($cols['inventory_id']) ? 'Inventory_Id' : (isset($cols['item_id']) ? 'Item_Id' : 'Inventory_Id');
$nameCol = isset($cols['item_name']) ? 'Item_name' : (isset($cols['item_name']) ? 'Item_Name' : 'Item_name');
$qtyCol = isset($cols['quantity']) ? 'Quantity' : (isset($cols['current_stock']) ? 'Current_Stock' : 'Quantity');
$thrCol = isset($cols['threshold']) ? 'Threshold' : (isset($cols['reorder_level']) ? 'Reorder_Level' : 'Threshold');
$lastCol = isset($cols['last_update']) ? 'Last_update' : (isset($cols['updated_at']) ? 'Updated_At' : 'Last_update');
// Recompute description expression after migration to ensure it's used immediately
$descExpr = isset($cols['description']) ? 'Description' : "''";

// Backfill Treatment names (Used_For) for existing items
try {
  if ($conn) {
    // If mapping table exists, derive treatments per item
    $hasMap = $conn->query("SHOW TABLES LIKE 'tbl_treatment_inventory'");
    if ($hasMap && $hasMap->num_rows > 0) {
      // Update Used_For using mapping, but do not overwrite existing values
      $conn->query(
        "UPDATE tbl_inventory i 
         JOIN (
           SELECT Item_Name, GROUP_CONCAT(DISTINCT Treatment_Name ORDER BY Treatment_Name SEPARATOR ', ') AS UF
           FROM tbl_treatment_inventory
           WHERE COALESCE(Item_Name,'') <> '' AND COALESCE(Treatment_Name,'') <> ''
           GROUP BY Item_Name
         ) m ON m.Item_Name = i.".$nameCol." 
         SET i.Used_For = m.UF
         WHERE (i.Used_For IS NULL OR i.Used_For = '')"
      );
    }
    // Fallback: copy Category to Used_For where still empty
    $conn->query("UPDATE tbl_inventory SET Used_For = Category WHERE (Used_For IS NULL OR Used_For = '') AND COALESCE(Category,'') <> ''");
  }
} catch (Throwable $e) { /* ignore backfill errors */ }

// Search and filter
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$treatmentFilter = isset($_GET['treatment']) ? trim($_GET['treatment']) : '';
// Stock filter (server-side): all|low|out|in
$stockFilter = isset($_GET['stock']) ? strtolower(trim($_GET['stock'])) : 'all';
if (!in_array($stockFilter, ['all','low','out','in'], true)) { $stockFilter = 'all'; }
$treatments = [];
$crTreat = $conn->query("SELECT DISTINCT Used_For FROM tbl_inventory WHERE Used_For IS NOT NULL AND Used_For <> '' ORDER BY Used_For ASC");
if ($crTreat) { while ($r = $crTreat->fetch_assoc()) { $treatments[] = $r['Used_For']; } }


// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=inventory_export_'.date('Ymd_His').'.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Item Name','Description','Quantity','Unit','Threshold','Treatment','Used For','Supplier','Expiration','Unit Cost','Status','Last Updated']);
  $expSQL = "SELECT 
                $idCol AS Inventory_Id,
                $nameCol AS Item_name,
                $descExpr AS Description,
                $qtyExpr AS Quantity,
                Unit,
                $thrExpr AS Threshold,
                Category,
                Used_For,
                Supplier,
                Expiration_Date,
                Unit_Cost,
                $lastExpr AS Last_update
              FROM tbl_inventory";
  $where = [];
  $expParams = [];$expTypes='';
  if ($searchQuery !== '') { $where[] = "$nameCol LIKE ?"; $expTypes.='s'; $expParams[] = $searchQuery.'%'; }
  if ($treatmentFilter !== '') { 
    if (isset($invCols['used_for'])) {
      $where[] = "Used_For = ?";
    } else {
      $where[] = "'' = ?";
    }
    $expTypes.='s'; 
    $expParams[] = $treatmentFilter; 
  }
  if ($where) { $expSQL .= ' WHERE '.implode(' AND ', $where); }
  $expSQL .= ' ORDER BY ' . $nameCol . ' ASC';
  $stmtE = $conn->prepare($expSQL);
  if ($stmtE) {
    if ($expTypes !== '' && !empty($expParams)) { $stmtE->bind_param($expTypes, ...$expParams); }
    $stmtE->execute();
    $resE = $stmtE->get_result();
    while ($r = $resE->fetch_assoc()) {
      $status = ($r['Quantity']==0?'Out of Stock':(($r['Quantity']<10)?'Low Stock':'In Stock'));
      fputcsv($out, [
        $r['Item_name'], $r['Description'], $r['Quantity'], $r['Unit'], $r['Threshold'], $r['Category'], $r['Used_For'], $r['Supplier'], $r['Expiration_Date'], $r['Unit_Cost'], $status, $r['Last_update']
      ]);
    }
    $stmtE->close();
  }
  fclose($out);
  exit;
}

// Fetch inventory items
$inventoryItems = [];
$sql = "SELECT 
          $idCol AS Inventory_Id,
          $nameCol AS Item_name,
          $descExpr AS Description,
          $qtyExpr AS Quantity,
          Unit,
          $thrExpr AS Threshold,
          Category,
          Used_For,
          Supplier,
          Expiration_Date,
          Unit_Cost,
          $lastExpr AS Last_update
        FROM tbl_inventory WHERE 1=1";
$types = '';
$params = [];

if (!empty($searchQuery)) {
  // Match by item name only; prefix match for faster intent-based search
  $sql .= " AND ($nameCol LIKE ?)";
  $types .= 's';
  $like = $searchQuery . '%';
  array_push($params, $like);
}

if ($treatmentFilter !== '') {
  $sql .= " AND COALESCE(Used_For,'') = ?";
  $types .= 's';
  $params[] = $treatmentFilter;
}

$sql .= " ORDER BY $nameCol ASC";

$stmt = $conn->prepare($sql);
if ($stmt) {
  if (!empty($types) && !empty($params)) {
    $stmt->bind_param($types, ...$params);
  }
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $inventoryItems[] = $row;
    }
  }
  $stmt->close();
}

// Calculate counts for summary cards (treat expired as out of stock)
$lowStockCount = count(array_filter($inventoryItems, function($item) { 
  $expired = (!empty($item['Expiration_Date']) && strtotime($item['Expiration_Date']) < strtotime(date('Y-m-d')));
  $q = (int)$item['Quantity'];
  return !$expired && $q > 0 && $q < 10; 
}));
$outOfStockCount = count(array_filter($inventoryItems, function($item) { 
  $expired = (!empty($item['Expiration_Date']) && strtotime($item['Expiration_Date']) < strtotime(date('Y-m-d')));
  $q = (int)$item['Quantity'];
  return $expired || $q === 0; 
}));
$inStockCount = count(array_filter($inventoryItems, function($item) { 
  $expired = (!empty($item['Expiration_Date']) && strtotime($item['Expiration_Date']) < strtotime(date('Y-m-d')));
  $q = (int)$item['Quantity'];
  $isLow = (!$expired && $q > 0 && $q < 10);
  return !$expired && !$isLow && $q >= 5; 
}));

// Apply server-side stock filter for display only (keep counts unfiltered)
$displayItems = array_values(array_filter($inventoryItems, function($item) use ($stockFilter) {
  $expired = (!empty($item['Expiration_Date']) && strtotime($item['Expiration_Date']) < strtotime(date('Y-m-d')));
  $q = (int)$item['Quantity'];
  $isLow = (!$expired && $q > 0 && $q < 10);
  $isOut = ($expired || $q === 0);
  $isIn  = (!$expired && !$isLow && $q >= 0 && !$isOut);
  if ($stockFilter === 'low') return $isLow;
  if ($stockFilter === 'out') return $isOut;
  if ($stockFilter === 'in')  return $isIn;
  return true; // all
}));

// Recent inventory activity (usage deductions + audit adds/updates)
$usageLogs = [];
// 1) Deductions from tbl_inventory_log (Action = deducted)
if ($conn && ($hasLogTbl = $conn->query("SHOW TABLES LIKE 'tbl_inventory_log'")) && $hasLogTbl->num_rows > 0) {
  $logSql = "SELECT 
                l.Appointment_Id,
                l.Item_Name,
                l.Quantity_Deducted,
                'deducted' AS Action,
                a.Date,
                a.Time,
                a.`Procedure`,
                a.Email,
                COALESCE(d.Name, a.Dentist_Id) AS Dentist_Name,
                TRIM(CONCAT(COALESCE(p.First_name,''),' ',COALESCE(p.Last_name,''))) AS Patient_Name
             FROM tbl_inventory_log l
             LEFT JOIN tbl_appointments a ON l.Appointment_Id = a.Appointment_Id
             LEFT JOIN tbl_dentist d ON a.Dentist_Id = d.Dentist_id
             LEFT JOIN tbl_patient p ON (p.Patient_Id = a.Patient_Id OR p.Email = a.Email)
             ORDER BY a.Date DESC, a.Time DESC
             LIMIT 50";
  if ($logRes = $conn->query($logSql)) {
    while ($row = $logRes->fetch_assoc()) {
      if (isset($row['Procedure'])) {
        $proc = (string)$row['Procedure'];
        // Remove accidental template literal fragments that may have been stored
        $proc = str_replace("'.htmlspecialchars(\$opt).'", '', $proc);
        $proc = str_replace('".htmlspecialchars($opt)."', '', $proc);
        $proc = preg_replace('/htmlspecialchars\\(\\$opt\\)/i', '', $proc);
        // Normalize duplicate commas/spaces
        $proc = preg_replace('/\s*,\s*,+/', ', ', $proc);
        $proc = trim($proc, " ,");
        $row['Procedure'] = $proc;
      }
      $usageLogs[] = $row;
    }
  }
}
// 2) Quantity changes from tbl_inventory_audit (Action = added/updated)
if ($conn && ($hasAuditTbl = $conn->query("SHOW TABLES LIKE 'tbl_inventory_audit'")) && $hasAuditTbl->num_rows > 0) {
  $auditSql = "SELECT 
                  a.Inventory_Id,
                  i.Item_Name,
                  i.Used_For AS `Procedure`,
                  i.Expiration_Date,
                  a.Action,
                  a.Details,
                  a.Created_At
               FROM tbl_inventory_audit a
               LEFT JOIN tbl_inventory i ON i.Item_Id = a.Inventory_Id
               WHERE a.Action IN ('add','quantity_change')
               ORDER BY a.Created_At DESC
               LIMIT 50";
  if ($aRes = $conn->query($auditSql)) {
    while ($r = $aRes->fetch_assoc()) {
      $act = strtolower((string)($r['Action'] ?? ''));
      $delta = null;
      $detStr = (string)($r['Details'] ?? '');
      if (preg_match('/qty_delta:\s*([+\-]?\d+)/i', $detStr, $m)) { $delta = (int)$m[1]; }
      $expFromDetails = null;
      if (preg_match('/exp_at:\s*(\d{4}-\d{2}-\d{2})/i', $detStr, $mm)) { $expFromDetails = $mm[1]; }
      $actionNorm = ($act === 'add' || ($delta !== null && $delta > 0)) ? 'added' : 'updated';
      $dt = (string)($r['Created_At'] ?? '');
      $datePart = $dt ? date('Y-m-d', strtotime($dt)) : '';
      $timePart = $dt ? date('H:i:s', strtotime($dt)) : '';
      $usageLogs[] = [
        'Appointment_Id' => null,
        'Item_Name' => (string)($r['Item_Name'] ?? ''),
        'Quantity_Deducted' => null,
        'Qty_Change' => $delta,
        'Action' => $actionNorm,
        'Date' => $datePart,
        'Time' => $timePart,
        'Procedure' => (string)($r['Procedure'] ?? ''),
        'Expiration_Date' => (string)($expFromDetails ?: ''),
        'Email' => '',
        'Dentist_Name' => '',
        'Patient_Name' => ''
      ];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Inventory Management - Miles Dental Clinic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body { 
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
      background-color: #f8f9fa;
      margin: 0;
      padding: 0;
      height: 100vh;
      overflow: hidden;
    }
    
    .main-container {
      display: flex;
      height: 100vh;
      width: 100vw;
    }
    
    .content { 
      flex: 1;
      padding: 20px;
      background-color: #f8f9fa;
      overflow-y: auto;
      height: 100vh;
    }
    
    .page-header {
      background: white;
      padding: 15px 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      margin-bottom: 15px;
      border-left: 4px solid #007bff;
    }
    
    .page-title {
      font-size: 1.5rem;
      font-weight: 600;
      color: #2c3e50;
      margin: 0;
    }
    
    .search-section { background: #fff; padding: 14px 16px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
    .inventory-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:10px; }
    .inventory-toolbar .grow { flex: 1 1 360px; min-width: 260px; }
    .inventory-toolbar .btn-sm, .inventory-toolbar .form-control-sm { height: 38px; display:inline-flex; align-items:center; }
    .inventory-toolbar .btn-icon { width: 38px; padding: 0; display:inline-flex; align-items:center; justify-content:center; }
    @media (max-width: 576px) { .inventory-toolbar { gap:8px; } }
    
    .summary-cards {
      margin-bottom: 20px;
    }
    
    .summary-card {
      background: white;
      border-radius: 8px;
      padding: 15px;
      text-align: center;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      border-top: 4px solid;
    }
    
    .summary-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 20px rgba(0,0,0,0.12);
    }
    
    .summary-card.total { border-top-color: #007bff; }
    .summary-card.low-stock { border-top-color: #ffc107; }
    .summary-card.out-of-stock { border-top-color: #dc3545; }
    .summary-card.in-stock { border-top-color: #28a745; }
    
    .summary-card h5 {
      font-size: 1.8rem;
      font-weight: 700;
      margin: 0 0 5px 0;
    }
    
    .summary-card p {
      color: #6c757d;
      font-size: 0.9rem;
      margin: 0;
      font-weight: 500;
    }
    
    .inventory-table-container {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    /* Horizontal scroll container to keep full table visible */
    .inventory-scroll {
      overflow-x: auto;
      overflow-y: hidden;
      -webkit-overflow-scrolling: touch;
      width: 100%;
    }
    
    .table {
      margin: 0;
    }
    /* Ensure the table doesn't shrink too small; enable horizontal scroll */
    .table.align-middle {
      min-width: 1200px;
    }
    
    .table thead th {
      background-color: #f8f9fa;
      border-bottom: 2px solid #dee2e6;
      font-weight: 600;
      color: #495057;
      padding: 15px 12px;
      font-size: 0.9rem;
    }
    /* Keep headers visible while scrolling horizontally */
    .inventory-scroll thead th {
      position: sticky;
      top: 0;
      z-index: 1;
    }
    
    .table tbody td {
      padding: 15px 12px;
      vertical-align: middle;
      border-bottom: 1px solid #f1f3f4;
    }
    
    .table tbody tr:hover {
      background-color: #f8f9fa;
    }
    
    .low-stock { 
      background-color: #fff8e1 !important; 
      border-left: 4px solid #ffc107;
    }
    
    .out-of-stock { 
      background-color: #ffebee !important; 
      border-left: 4px solid #dc3545;
    }
    
    .clickable-card {
      transition: all 0.3s ease;
    }
    
    .clickable-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .clickable-card.active {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      border: 2px solid #007bff;
    }
    
    .stock-indicator { 
      font-weight: 600;
      font-size: 1.1rem;
    }
    
    .badge {
      font-size: 0.75rem;
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: 500;
    }
    
    .btn {
      border-radius: 8px;
      font-weight: 500;
      padding: 8px 16px;
      transition: all 0.2s ease;
    }
    
    .btn:hover {
      transform: translateY(-1px);
    }
    
    .btn-sm {
      padding: 6px 12px;
      font-size: 0.8rem;
    }
    
    .alert {
      border-radius: 8px;
      border: none;
      padding: 15px 20px;
      margin-bottom: 20px;
    }
    
    .form-control {
      border-radius: 8px;
      border: 1px solid #ced4da;
      padding: 10px 15px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    
    .form-control:focus {
      border-color: #007bff;
      box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
    }
    
    .form-label {
      font-weight: 500;
      color: #495057;
      margin-bottom: 8px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .content {
        padding: 15px;
      }
      
      .page-header {
        padding: 20px;
        margin-bottom: 20px;
      }
      
      .page-title {
        font-size: 1.5rem;
      }
      
      .summary-card {
        padding: 20px;
        margin-bottom: 15px;
      }
      
      .table-responsive {
        border-radius: 8px;
      }
      
      .btn-group-mobile {
        display: flex;
        flex-direction: column;
        gap: 5px;
      }
    }
    
    /* Loading animation */
    .loading {
      opacity: 0.6;
      pointer-events: none;
    }
    
    /* Custom scrollbar */
    .inventory-scroll::-webkit-scrollbar {
      height: 8px;
    }
    .inventory-scroll::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }
    .inventory-scroll::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 4px;
    }
    .inventory-scroll::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
    }
    /* Items used section: make it easy to view without page scrolling */
    .items-used-wrap {
      max-height: 420px;
      overflow: auto;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      background: white;
    }
    .items-used-wrap thead th {
      position: sticky;
      top: 0;
      background: #f8f9fa;
      z-index: 1;
    }
  </style>
</head>
<body>
<div class="main-container">
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

  <div class="content">
    <!-- Page Header -->
    <div class="page-header">
      <div class="d-flex align-items-center justify-content-between">
        <h1 class="page-title">Inventory Management</h1>
        <div>
          <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
          </a>
        </div>

    <!-- Item History Modal -->
    <div class="modal fade" id="itemHistoryModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-fullscreen" id="itemHistoryDialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Item History</h5>
            <div class="d-flex align-items-center gap-2">
              <div class="btn-group btn-group-sm me-2" role="group" aria-label="Filter">
                <button type="button" class="btn btn-outline-secondary active" data-history-filter="all">All</button>
                <button type="button" class="btn btn-outline-secondary" data-history-filter="added">Added</button>
                <button type="button" class="btn btn-outline-secondary" data-history-filter="deducted">Deducted</button>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleHistorySizeBtn" title="Toggle Fullscreen">
                <i class="fas fa-expand" id="toggleHistorySizeIcon"></i>
              </button>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
          </div>
          <div class="modal-body">
            <div class="mb-2"><strong id="historyItemName">&nbsp;</strong></div>
            <div class="table-responsive" style="max-height:70vh; overflow:auto">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Action</th>
                    <th>Treatment</th>
                    <th>Item</th>
                    <th>Expiration</th>
                    <th>Patient</th>
                    <th>Dentist</th>
                    <th>History</th>
                    <th>Remaining</th>
                  </tr>
                </thead>
                <tbody id="historyRows">
                  <tr><td colspan="100" class="text-muted">Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
      </div>
    </div>

    <?= $inventoryAlert ?>

    <!-- Inventory Summary Cards -->
    <div class="summary-cards">
      <div class="row">
        <div class="col-lg-3 col-md-6 mb-3">
          <a href="admin_inventory.php?stock=all&search=<?= urlencode($searchQuery) ?>&treatment=<?= urlencode($treatmentFilter) ?>" class="text-decoration-none">
          <div class="summary-card total clickable-card <?= $stockFilter==='all' ? 'active' : '' ?>" style="cursor: pointer;">
            <h5 class="text-primary"><?= count($inventoryItems) ?></h5>
            <p>Total Items</p>
          </div>
          </a>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
          <a href="admin_inventory.php?stock=low&search=<?= urlencode($searchQuery) ?>&treatment=<?= urlencode($treatmentFilter) ?>" class="text-decoration-none">
          <div class="summary-card low-stock clickable-card <?= $stockFilter==='low' ? 'active' : '' ?>" style="cursor: pointer;">
            <h5 class="text-warning"><?= $lowStockCount ?></h5>
            <p>Low Stock</p>
          </div>
          </a>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
          <a href="admin_inventory.php?stock=out&search=<?= urlencode($searchQuery) ?>&treatment=<?= urlencode($treatmentFilter) ?>" class="text-decoration-none">
          <div class="summary-card out-of-stock clickable-card <?= $stockFilter==='out' ? 'active' : '' ?>" style="cursor: pointer;">
            <h5 class="text-danger"><?= $outOfStockCount ?></h5>
            <p>Out of Stock</p>
          </div>
          </a>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
          <a href="admin_inventory.php?stock=in&search=<?= urlencode($searchQuery) ?>&treatment=<?= urlencode($treatmentFilter) ?>" class="text-decoration-none">
          <div class="summary-card in-stock clickable-card <?= $stockFilter==='in' ? 'active' : '' ?>" style="cursor: pointer;">
            <h5 class="text-success"><?= $inStockCount ?></h5>
            <p>In Stock</p>
          </div>
          </a>
        </div>
      </div>
    </div>

    <!-- Search Section (moved here below summary cards) -->
    <div class="search-section">
      <form class="inventory-toolbar" method="get" id="searchForm">
        <div class="input-group grow">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($searchQuery) ?>" class="form-control form-control-sm" placeholder="Search inventory items..." autocomplete="off" />
        </div>
        <select name="treatment" class="form-select form-select-sm" style="max-width:220px">
          <option value="">All Treatments</option>
          <?php foreach ($treatments as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= $treatmentFilter === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search me-1"></i>Search</button>
        <a href="admin_inventory.php" class="btn btn-outline-secondary btn-sm" id="clearBtn"><i class="fas fa-times me-1"></i>Clear</a>
        <a href="?export=csv&amp;search=<?= urlencode($searchQuery) ?>&amp;treatment=<?= urlencode($treatmentFilter) ?>" class="btn btn-outline-success btn-sm btn-icon" title="Export CSV"><i class="fas fa-file-csv"></i></a>
        <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#itemsUsedModal"><i class="fas fa-list-ul me-1"></i>History</button>
        <a href="admin_inventory_edit.php" class="btn btn-success btn-sm ms-auto"><i class="fas fa-plus me-1"></i>Add Item</a>
      </form>
      <script>
        (function(){
          var form = document.getElementById('searchForm');
          var input = document.getElementById('searchInput');
          if (!form || !input) return;
          var timer = null;
          function submitDebounced(){
            if (timer) clearTimeout(timer);
            timer = setTimeout(function(){ form.requestSubmit ? form.requestSubmit() : form.submit(); }, 300);
          }
          input.addEventListener('input', submitDebounced);
        })();
      </script>
      <style>
        /* When fullscreen, let the table grow without inner scrollbars */
        #itemHistoryDialog.modal-fullscreen .table-responsive {
          max-height: none !important;
          overflow: visible !important;
        }
      </style>
      <script>
        function openItemHistoryModal(itemName, itemId){
          try {
            const modalEl = document.getElementById('itemHistoryModal');
            if (!modalEl) return;
            document.getElementById('historyItemName').textContent = itemName;
            const tbody = document.getElementById('historyRows');
            tbody.innerHTML = '<tr><td colspan="100" class="text-muted">Loading...</td></tr>';
            const url = 'admin_inventory.php?item_history=' + encodeURIComponent(itemName) + (itemId?('&item_id='+encodeURIComponent(String(itemId))):'');
            fetch(url)
              .then(r=>r.json())
              .then(data=>{
                const rows = Array.isArray(data.rows) ? data.rows : [];
                if (!rows.length) { tbody.innerHTML = '<tr><td colspan="100" class="text-muted">No history found</td></tr>'; return; }
                tbody.innerHTML = rows.map(r=>{
                  const when = r.When || r.Log_Date || '';
                  let dt = '';
                  if (when) {
                    try {
                      const d = new Date(when);
                      const opts = { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
                      dt = new Intl.DateTimeFormat(undefined, opts).format(d);
                    } catch(_) { dt = String(when); }
                  }
                  let action = (r.Action || (r.__kind==='deduction' ? 'deducted' : '')).toString().toLowerCase();
                  if (action === 'quantity_change') action = 'updated';
                  const patient = (r.Patient_Name || '').toString();
                  const doctor = (r.Doctor_Name || '').toString();
                  let historyText = '';
                  if (r.__kind === 'deduction' && r.Quantity_Deducted != null) {
                    historyText = '- ' + String(r.Quantity_Deducted);
                  } else if ((action === 'updated' || action === 'update' || action === 'added' || action === 'add') && typeof r.Details === 'string') {
                    const m = r.Details.match(/qty_delta:\s*([+-]?\d+)/i);
                    if (m) {
                      const d = parseInt(m[1], 10);
                      if (!isNaN(d) && d !== 0) { historyText = (d >= 0 ? '+ ' : '- ') + String(Math.abs(d)); }
                    }
                  }
                  // Fallback: use Qty_Change when Details is missing/unparsed
                  if (!historyText && (action === 'added' || action === 'updated') && typeof r.Qty_Change !== 'undefined' && r.Qty_Change !== null && r.Qty_Change !== '') {
                    const d2 = parseInt(r.Qty_Change, 10);
                    if (!isNaN(d2) && d2 !== 0) { historyText = (d2 >= 0 ? '+ ' : '- ') + String(Math.abs(d2)); }
                  }
                  const actNorm = (action==='add') ? 'added' : (action==='update' ? 'updated' : action);
                  const badgeCls = actNorm==='deducted' ? 'bg-danger' : (actNorm==='updated' ? 'bg-primary' : 'bg-success');
                  const actionHtml = '<span class="badge '+badgeCls+'">'+actNorm.charAt(0).toUpperCase()+actNorm.slice(1)+'</span>';
                  return '<tr data-action="'+actNorm+'">'+
                    '<td>'+ (dt || '') +'</td>'+
                    '<td>'+ actionHtml +'</td>'+
                    '<td>'+ (r.Treatment_Name || '') +'</td>'+
                    '<td>'+ (itemName || '') +'</td>'+
                    '<td>'+ ((actNorm==='added' && r.Expiration_Date)? (function(){try{return new Intl.DateTimeFormat(undefined,{month:'short',day:'numeric',year:'numeric'}).format(new Date(r.Expiration_Date));}catch(e){return String(r.Expiration_Date);} })() : '—') +'</td>'+
                    '<td>'+ patient +'</td>'+
                    '<td>'+ doctor +'</td>'+
                    '<td class="text-nowrap">'+ historyText +'</td>'+
                    '<td>'+ (r.Remaining_Quantity ?? '') +'</td>'+
                  '</tr>';
                }).join('');
                // Wire up filter buttons inside this modal after rows render
                const container = modalEl;
                const btns = container.querySelectorAll('[data-history-filter]');
                function updatePerItemColumnVisibility(val){
                  // New column order indices (Notes removed):
                  // 0 Date, 1 Action, 2 Treatment, 3 Item, 4 Expiration, 5 Patient, 6 Dentist, 7 History, 8 Remaining
                  let hideCols = [];
                  if (val === 'added') { hideCols = [5,6,8]; /* show Expiration (4) */ }
                  else { hideCols = [4]; /* hide Expiration for non-added */ }
                  const theadCells = container.querySelectorAll('thead tr th');
                  theadCells.forEach((th, idx)=>{ th.style.display = hideCols.includes(idx) ? 'none' : ''; });
                  const bodyRows = container.querySelectorAll('tbody tr');
                  bodyRows.forEach(tr=>{
                    const tds = tr.querySelectorAll('td');
                    tds.forEach((td, idx)=>{ td.style.display = hideCols.includes(idx) ? 'none' : ''; });
                  });
                }
                btns.forEach(function(b){
                  b.addEventListener('click', function(){
                    btns.forEach(function(x){ x.classList.remove('active'); });
                    this.classList.add('active');
                    const val = this.getAttribute('data-history-filter');
                    const rowsEls = container.querySelectorAll('tbody tr[data-action]');
                    rowsEls.forEach(function(rw){
                      if (val==='all') { rw.style.display=''; }
                      else { rw.style.display = (rw.getAttribute('data-action')===val) ? '' : 'none'; }
                    });
                    updatePerItemColumnVisibility(val);
                  });
                });
                // Apply default visibility for currently active filter
                const activeBtn = container.querySelector('[data-history-filter].active');
                updatePerItemColumnVisibility(activeBtn ? activeBtn.getAttribute('data-history-filter') : 'all');
              })
              .catch(()=>{ tbody.innerHTML = '<tr><td colspan="100" class="text-danger">Failed to load history</td></tr>'; });
            const m = new bootstrap.Modal(modalEl);
            m.show();
          } catch(e) {}
        }
        (function(){
          const dialog = document.getElementById('itemHistoryDialog');
          const btn = document.getElementById('toggleHistorySizeBtn');
          const icon = document.getElementById('toggleHistorySizeIcon');
          if (dialog && btn && icon) {
            btn.addEventListener('click', function(){
              if (dialog.classList.contains('modal-fullscreen')) {
                dialog.classList.remove('modal-fullscreen');
                icon.classList.remove('fa-compress');
                icon.classList.add('fa-expand');
              } else {
                dialog.classList.add('modal-fullscreen');
                icon.classList.remove('fa-expand');
                icon.classList.add('fa-compress');
              }
            });
          }
        })();
      </script>
    </div>

    <!-- Inventory Table -->
    <div class="inventory-table-container">
      <div class="inventory-scroll table-responsive">
        <table class="table align-middle">
        <thead>
          <tr>
            <th><i class="fas fa-box me-2"></i>Item Name</th>
            <th><i class="fas fa-cog me-2"></i>Actions</th>
            <th><i class="fas fa-info-circle me-2"></i>Description</th>
            <th><i class="fas fa-cubes me-2"></i>Quantity</th>
            <th><i class="fas fa-ruler me-2"></i>Unit</th>
            <th><i class="fas fa-tooth me-2"></i>Treatment</th>
            <th><i class="fas fa-exclamation-triangle me-2"></i>Threshold</th>
            <th><i class="fas fa-industry me-2"></i>Supplier</th>
            <th><i class="fas fa-calendar-alt me-2"></i>Expiration</th>
            <th><i class="fas fa-tag me-2"></i>Unit Cost</th>
            <th><i class="fas fa-chart-line me-2"></i>Status</th>
            <th><i class="fas fa-clock me-2"></i>Last Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($inventoryItems)): ?>
            <tr>
              <td colspan="100" class="text-center py-5">
                <div class="text-muted">
                  <i class="fas fa-inbox fa-3x mb-3"></i>
                  <h5>No inventory items found</h5>
                  <p>Start by adding your first inventory item.</p>
                  <a href="admin_inventory_edit.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add First Item
                  </a>
                </div>
              </td>
            </tr>
          <?php else: foreach ($displayItems as $item): 
            $isExpired = (!empty($item['Expiration_Date']) && strtotime($item['Expiration_Date']) < strtotime(date('Y-m-d')));
            $isLowStock = !$isExpired && $item['Quantity'] < 10 && $item['Quantity'] > 0;
            $isOutOfStock = $isExpired || $item['Quantity'] == 0;
            $rowClass = $isOutOfStock ? 'out-of-stock' : ($isLowStock ? 'low-stock' : '');
          ?>
            <tr class="<?= $rowClass ?>" role="button" style="cursor:pointer" onclick="openItemActionsModal(<?= (int)$item['Inventory_Id'] ?>,'<?= htmlspecialchars(addslashes($item['Item_name'] ?? ($item['Item_Name'] ?? ''))) ?>')">
              <td>
                <div class="d-flex align-items-center">
                  <div class="me-3">
                    <?php if ($isOutOfStock): ?>
                      <i class="fas fa-box-open text-danger"></i>
                    <?php elseif ($isLowStock): ?>
                      <i class="fas fa-box text-warning"></i>
                    <?php else: ?>
                      <i class="fas fa-box text-success"></i>
                    <?php endif; ?>
                  </div>
                  <div>
                    <strong><?= htmlspecialchars($item['Item_name'] ?? ($item['Item_Name'] ?? '')) ?></strong>
                  </div>
                </div>
              </td>
              <td>
                <div class="d-flex gap-2">
                  <a href="admin_inventory_edit.php?id=<?= (int)$item['Inventory_Id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit Item">
                    <i class="fas fa-edit"></i>
                  </a>
                  <button type="button" class="btn btn-sm btn-outline-secondary" title="View History" onclick="openItemHistoryModal('<?= htmlspecialchars(addslashes($item['Item_name'] ?? ($item['Item_Name'] ?? ''))) ?>', <?= (int)$item['Inventory_Id'] ?>)">
                    <i class="fas fa-history"></i>
                  </button>
                </div>
              </td>
              <td>
                <span class="text-muted">
                  <?= htmlspecialchars($item['Description'] ?: 'No description available') ?>
                </span>
              </td>
              <td>
                <span class="stock-indicator">
                  <?= $item['Quantity'] ?>
                </span>
              </td>
              <td>
                <span class="badge bg-light text-dark">
                  <?= htmlspecialchars($item['Unit'] ?: 'N/A') ?>
                </span>
              </td>
              <td><?= htmlspecialchars($item['Used_For'] ?? '—') !== '' ? htmlspecialchars($item['Used_For']) : '—' ?></td>
              <td><?= $item['Threshold'] ?></td>
              <td><?= htmlspecialchars($item['Supplier'] ?? '') ?></td>
              <td>
                <small class="text-muted">
                  <?= !empty($item['Expiration_Date']) ? date('M j, Y', strtotime($item['Expiration_Date'])) : '—' ?>
                </small>
              </td>
              <td>
                <span class="text-muted"><?= isset($item['Unit_Cost']) && $item['Unit_Cost'] !== null ? ('₱'.number_format((float)$item['Unit_Cost'], 2)) : '—' ?></span>
              </td>
              <td>
                <?php if ($isOutOfStock): ?>
                  <span class="badge bg-danger" title="<?= $isExpired ? 'Expired item' : 'No stock' ?>">
                    <i class="fas fa-times-circle me-1"></i>Out of Stock
                  </span>
                <?php elseif ($isLowStock): ?>
                  <span class="badge bg-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>Low Stock
                  </span>
                <?php else: ?>
                  <span class="badge bg-success">
                    <i class="fas fa-check-circle me-1"></i>In Stock
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <small class="text-muted">
                  <?= $item['Last_update'] ? date('M j, Y g:i A', strtotime($item['Last_update'])) : 'N/A' ?>
                </small>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        </table>
      </div>
    </div>

    <!-- Use Item Modal -->
    <div class="modal fade" id="useItemModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Record Item Usage</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="useItemForm">
            <div class="modal-body">
              <input type="hidden" id="useItemId" name="inventory_id">
              <div class="mb-3">
                <label for="itemName" class="form-label">Item</label>
                <input type="text" class="form-control" id="itemName" readonly>
              </div>
              <div class="mb-3">
                <label for="quantityUsed" class="form-label">Quantity Used</label>
                <input type="number" class="form-control" id="quantityUsed" name="quantity_used" min="1" step="1" inputmode="numeric" pattern="\d*" required>
                <div class="form-text">Current quantity: <span id="currentQuantity">0</span></div>
              </div>
              <div class="mb-3">
                <label for="usageNotes" class="form-label">Notes (Optional)</label>
                <textarea class="form-control" id="usageNotes" name="notes" rows="3"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Record Usage</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Items Used Modal -->
    <div class="modal fade" id="itemActionsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="itemActionsTitle">Item</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="d-grid gap-2">
              <a id="itemEditLink" href="#" class="btn btn-outline-primary"><i class="fas fa-edit me-2"></i>Edit</a>
              
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Items Used Modal -->
    <div class="modal fade" id="itemsUsedModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
          <div class="modal-header align-items-center">
            <h5 class="modal-title d-flex align-items-center gap-2 m-0"><i class="fas fa-list-ul"></i><span>History</span></h5>
            <div class="ms-auto d-flex align-items-center gap-2">
              <div class="btn-group btn-group-sm" role="group" aria-label="Filter">
                <button type="button" class="btn btn-outline-secondary active" data-history-filter="all">All</button>
                <button type="button" class="btn btn-outline-secondary" data-history-filter="added">Added</button>
                <button type="button" class="btn btn-outline-secondary" data-history-filter="deducted">Deducted</button>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
          </div>
          <div class="modal-body p-0">
            <div class="table-responsive m-0">
              <table class="table align-middle mb-0">
                <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                  <tr>
                    <th style="min-width:160px" class="text-nowrap"><span class="d-inline-flex align-items-center"><i class="fas fa-fw fa-calendar-alt me-2"></i><span>Date</span></span></th>
                    <th style="min-width:110px" class="text-nowrap"><span class="d-inline-flex align-items-center"><i class="fas fa-fw fa-tag me-2"></i><span>Action</span></span></th>
                    <th class="text-nowrap"><span class="d-inline-flex align-items-center"><i class="fas fa-fw fa-user me-2"></i><span>Patient</span></span></th>
                    <th class="text-nowrap"><span class="d-inline-flex align-items-center"><i class="fas fa-fw fa-user-md me-2"></i><span>Dentist</span></span></th>
                    <th class="text-nowrap"><span class="d-inline-flex align-items-center"><i class="fas fa-fw fa-tooth me-2"></i><span>Treatment</span></span></th>
                    <th class="text-nowrap"><span class="d-inline-flex align-items-center"><i class="fas fa-fw fa-boxes me-2"></i><span>Item</span></span></th>
                    <th class="text-nowrap"><span class="d-inline-flex align-items-center"><i class="fas fa-fw fa-calendar-alt me-2"></i><span>Expiration</span></span></th>
                    <th style="min-width:90px" class="text-nowrap">
                      <span class="d-inline-flex align-items-center"><i class="fas fa-fw fa-clock-rotate-left me-2"></i><span>History</span></span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($usageLogs)): ?>
                    <?php foreach ($usageLogs as $log): ?>
                      <?php 
                        $act = strtolower((string)($log['Action'] ?? 'deducted'));
                        $badgeCls = $act==='deducted' ? 'bg-danger' : ($act==='updated' ? 'bg-primary' : 'bg-success');
                        $histText = '';
                        if ($act==='deducted' && isset($log['Quantity_Deducted'])) { $histText = '- ' . (int)$log['Quantity_Deducted']; }
                        elseif (isset($log['Qty_Change']) && is_numeric($log['Qty_Change'])) { $d=(int)$log['Qty_Change']; if ($d!==0) { $histText = ($d>0?'+ ':'- ') . abs($d); } }
                      ?>
                      <tr data-action="<?= htmlspecialchars($act) ?>">
                        <td>
                          <div class="text-muted small"><?= htmlspecialchars(date('M j, Y', strtotime($log['Date']))) ?> <?= htmlspecialchars(date('g:i A', strtotime($log['Time']))) ?></div>
                        </td>
                        <td>
                          <span class="badge <?= $badgeCls ?>"><?= htmlspecialchars(ucfirst($act)) ?></span>
                        </td>
                        <td>
                          <?php 
                            $pn = trim((string)($log['Patient_Name'] ?? ''));
                            $disp = $pn !== '' ? $pn : (string)($log['Email'] ?? '');
                            echo htmlspecialchars($disp);
                          ?>
                        </td>
                        <td><?= htmlspecialchars((ctype_digit((string)$log['Dentist_Name']) ? ('Dr. #'.$log['Dentist_Name']) : ('Dr. '.$log['Dentist_Name']))) ?></td>
                        <td><?= htmlspecialchars($log['Procedure'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($log['Item_Name']) ?></td>
                        <td>
                          <?php if (strtolower($act)==='added' && !empty($log['Expiration_Date'])): ?>
                            <span class="text-muted small"><?= htmlspecialchars(date('M j, Y', strtotime($log['Expiration_Date']))) ?></span>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= htmlspecialchars($histText) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <div>No items used yet.</div>
                        <small>Usage will appear here when inventory is deducted during appointments.</small>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
          var modal = document.getElementById('itemsUsedModal');
          if (!modal) return;
          modal.addEventListener('shown.bs.modal', function(){
            var btns = modal.querySelectorAll('[data-history-filter]');
            function updateColumnVisibility(val){
              // Table structure: 0 Date, 1 Action, 2 Patient, 3 Dentist, 4 Treatment, 5 Item, 6 Expiration, 7 History
              var theadCells = modal.querySelectorAll('thead tr th');
              var rows = modal.querySelectorAll('tbody tr');
              var hideCols = [];
              if (val === 'added') { hideCols = [2,3]; /* show Expiration (6) */ }
              else { hideCols = [6]; /* hide Expiration for non-added */ }
              // Update header visibility
              theadCells.forEach(function(th, idx){
                if (hideCols.indexOf(idx) !== -1) th.style.display = 'none';
                else th.style.display = '';
              });
              // Update body cells visibility
              rows.forEach(function(tr){
                var cells = tr.querySelectorAll('td');
                cells.forEach(function(td, idx){
                  if (hideCols.indexOf(idx) !== -1) td.style.display = 'none';
                  else td.style.display = '';
                });
              });
            }
            btns.forEach(function(b){
              b.addEventListener('click', function(){
                btns.forEach(function(x){ x.classList.remove('active'); });
                this.classList.add('active');
                var val = this.getAttribute('data-history-filter');
                var rows = modal.querySelectorAll('tbody tr[data-action]');
                rows.forEach(function(r){
                  if (val==='all') { r.style.display=''; }
                  else { r.style.display = (r.getAttribute('data-action')===val) ? '' : 'none'; }
                });
                updateColumnVisibility(val);
              });
            });
            // Ensure correct columns visible for default active state
            var active = modal.querySelector('[data-history-filter].active');
            updateColumnVisibility(active ? active.getAttribute('data-history-filter') : 'all');
          });
        });
      </script>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Handle Use Item modal
const useItemModal = new bootstrap.Modal(document.getElementById('useItemModal'));
const useItemForm = document.getElementById('useItemForm');
const itemNameInput = document.getElementById('itemName');
const quantityUsedInput = document.getElementById('quantityUsed');
const currentQuantitySpan = document.getElementById('currentQuantity');

// Open Use Item modal
document.querySelectorAll('.use-item').forEach(button => {
  button.addEventListener('click', function() {
    const id = this.getAttribute('data-id');
    const name = this.getAttribute('data-name');
    const quantity = this.getAttribute('data-quantity');
    
    document.getElementById('useItemId').value = id;
    itemNameInput.value = name;
    currentQuantitySpan.textContent = quantity;
    quantityUsedInput.max = quantity;
    quantityUsedInput.value = '';
    document.getElementById('usageNotes').value = '';
    
    useItemModal.show();
  });
});

// Handle form submission
useItemForm.addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  const quantityUsed = parseInt(formData.get('quantity_used'), 10);
  const currentQuantity = parseInt(currentQuantitySpan.textContent, 10);
  if (!Number.isInteger(quantityUsed) || quantityUsed < 1) {
    alert('Please enter a whole number quantity of at least 1');
    return;
  }
  
  if (quantityUsed > currentQuantity) {
    alert('Cannot use more than the current quantity');
    return;
  }
  
  // Show loading state
  const submitBtn = this.querySelector('button[type="submit"]');
  const originalBtnText = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
  
  // Send AJAX request
  fetch('deduct_inventory.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Show success message
      alert(`Successfully recorded usage of ${quantityUsed} ${data.item_name}. New quantity: ${data.new_quantity}`);
      // Reload the page to update the inventory list
      window.location.reload();
    } else {
      alert('Error: ' + (data.message || 'Failed to update inventory'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while processing your request');
  })
  .finally(() => {
    // Reset button state
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalBtnText;
  });
});

function openItemActionsModal(id, name) {
  var title = document.getElementById('itemActionsTitle');
  var edit = document.getElementById('itemEditLink');
  var del = document.getElementById('itemDeleteId');
  if (title) title.textContent = name;
  if (edit) edit.href = 'admin_inventory_edit.php?id=' + encodeURIComponent(id);
  if (del) del.value = id;
  var modal = new bootstrap.Modal(document.getElementById('itemActionsModal'));
  modal.show();
}
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchForm = document.getElementById('searchForm');
    const clearBtn = document.getElementById('clearBtn');
    const tableBody = document.querySelector('tbody');
    const summaryCards = document.querySelectorAll('.summary-card h5');
    const originalItems = <?= json_encode($inventoryItems) ?>;
    
    // Store original table content
    const originalTableContent = tableBody.innerHTML;
    const originalCounts = {
        total: <?= count($inventoryItems) ?>,
        lowStock: <?= $lowStockCount ?>,
        outOfStock: <?= $outOfStockCount ?>,
        inStock: <?= $inStockCount ?>
    };
    
    // Real-time search function (item name prefix only)
    function performSearch(searchTerm) {
        if (!searchTerm.trim()) {
            // Show all items if search is empty
            tableBody.innerHTML = originalTableContent;
            updateSummaryCards(originalCounts);
            return;
        }
        
        const filteredItems = originalItems.filter(item => {
            const itemName = (item.Item_name || '').toLowerCase();
            const searchLower = searchTerm.toLowerCase();
            // Only prefix match on item name to keep results tight and predictable
            return itemName.startsWith(searchLower);
        });
        
        // Update table with filtered results
        updateTable(filteredItems);
        
        // Update summary cards
        updateSummaryCards(calculateCounts(filteredItems));
    }
    
    // Update table with filtered items
    function updateTable(items) {
        if (items.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-search fa-3x mb-3"></i>
                            <h5>No items found</h5>
                            <p>Try adjusting your search terms or filters.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        let tableHTML = '';
        items.forEach(item => {
            const isLowStock = item.Quantity < 5 && item.Quantity > 0;
            const isOutOfStock = item.Quantity == 0;
            const rowClass = isOutOfStock ? 'out-of-stock' : (isLowStock ? 'low-stock' : '');
            
            tableHTML += `
                <tr class="${rowClass}" role="button" style="cursor:pointer" onclick="openItemActionsModal(${item.Inventory_Id}, '${escapeHtml(item.Item_name).replace(/'/g, "\\'")}')">
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                ${isOutOfStock ? '<i class="fas fa-box-open text-danger"></i>' : 
                                  isLowStock ? '<i class="fas fa-box text-warning"></i>' : 
                                  '<i class="fas fa-box text-success"></i>'}
                            </div>
                            <div>
                                <strong>${escapeHtml(item.Item_name)}</strong>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <a href="admin_inventory_edit.php?id=${encodeURIComponent(item.Inventory_Id)}" class="btn btn-sm btn-outline-primary" title="Edit Item">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item? This action cannot be undone.')">
                                <input type="hidden" name="delete_id" value="${item.Inventory_Id}">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Item">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                    <td>
                        <span class="text-muted">
                            ${escapeHtml(item.Description || 'No description available')}
                        </span>
                    </td>
                    <td>
                        <span class="stock-indicator">
                            ${item.Quantity}
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark">
                            ${escapeHtml(item.Unit || 'N/A')}
                        </span>
                    </td>
                    <td>${item.Threshold}</td>
                    <td>${escapeHtml(item.Category || '')}</td>
                    <td>${escapeHtml(item.Used_For || '')}</td>
                    <td>${escapeHtml(item.Supplier || '')}</td>
                    <td><small class="text-muted">${item.Expiration_Date ? formatDate(item.Expiration_Date) : '—'}</small></td>
                    <td><span class="text-muted">${(item.Unit_Cost !== null && item.Unit_Cost !== undefined) ? ('₱' + Number(item.Unit_Cost).toFixed(2)) : '—'}</span></td>
                    <td>
                        ${isOutOfStock ? 
                            '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Out of Stock</span>' : 
                            (isLowStock ? 
                            '<span class="badge bg-warning"><i class="fas fa-exclamation-triangle me-1"></i>Low Stock</span>' : 
                            '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>In Stock</span>')}
                    </td>
                    <td><small class="text-muted">${item.Last_update ? formatDate(item.Last_update) : 'N/A'}</small></td>
                    <td>
                        <span class="text-muted">
                            ${escapeHtml(item.Description || 'No description available')}
                        </span>
                    </td>
                    <td>
                        <span class="stock-indicator">
                            ${item.Quantity}
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark">
                            ${escapeHtml(item.Unit || 'N/A')}
                        </span>
                    </td>
                    <td>${item.Threshold}</td>
                    <td>${escapeHtml(item.Category || '')}</td>
                    <td>${escapeHtml(item.Used_For || '')}</td>
                    <td>${escapeHtml(item.Supplier || '')}</td>
                    <td><small class="text-muted">${item.Expiration_Date ? formatDate(item.Expiration_Date) : '—'}</small></td>
                    <td><span class="text-muted">${(item.Unit_Cost !== null && item.Unit_Cost !== undefined) ? ('₱' + Number(item.Unit_Cost).toFixed(2)) : '—'}</span></td>
                    <td>
                        ${isOutOfStock ? 
                            '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Out of Stock</span>' : 
                            (isLowStock ? 
                            '<span class="badge bg-warning"><i class="fas fa-exclamation-triangle me-1"></i>Low Stock</span>' : 
                            '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>In Stock</span>')}
                    </td>
                    <td>${item.Last_update ? formatDate(item.Last_update) : 'N/A'}</td>
                </tr>
            `;
        });
        
        tableBody.innerHTML = tableHTML;
    
    // Calculate counts for filtered items
    function calculateCounts(items) {
        const lowStockCount = items.filter(item => 
            item.Quantity < 5 && item.Quantity > 0
        ).length;
        const outOfStockCount = items.filter(item => 
            item.Quantity == 0
        ).length;
        const inStockCount = items.filter(item => 
            item.Quantity >= 5
        ).length;
        
        return {
            total: items.length,
            lowStock: lowStockCount,
            outOfStock: outOfStockCount,
            inStock: inStockCount
        };
    }
    
    // Update summary cards
    function updateSummaryCards(counts) {
        summaryCards[0].textContent = counts.total;
        summaryCards[1].textContent = counts.lowStock;
        summaryCards[2].textContent = counts.outOfStock;
        summaryCards[3].textContent = counts.inStock;
    }
    
    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }
    
    // Event listeners
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch(this.value);
        }, 300); // 300ms delay to avoid too many searches
    });
    
    // Clear button functionality
    clearBtn.addEventListener('click', function(e) {
        e.preventDefault();
        searchInput.value = '';
        performSearch('');
        searchInput.focus();
    });
    
    // Handle form submission (fallback for when JS is disabled)
    searchForm.addEventListener('submit', function(e) {
        // Let the form submit normally if there's a search term
        if (searchInput.value.trim()) {
            return true;
        }
        // Prevent submission if search is empty
        e.preventDefault();
        performSearch('');
    });
    
    // Focus search input on page load
    searchInput.focus();
    
    // Stock filtering function
    window.filterByStock = function(stockType) {
        // Remove active class from all cards
        document.querySelectorAll('.clickable-card').forEach(card => {
            card.classList.remove('active');
        });
        
        // Add active class to clicked card
        const hostCard = (event && event.target) ? event.target.closest('.clickable-card') : null;
        if (hostCard) hostCard.classList.add('active');
        
        // Filter items based on stock type
        let filteredItems = [];
        
        switch(stockType) {
            case 'all':
                filteredItems = originalItems;
                break;
            case 'low':
                filteredItems = originalItems.filter(item => 
                    item.Quantity < 5 && item.Quantity > 0
                );
                break;
            case 'out':
                filteredItems = originalItems.filter(item => 
                    item.Quantity == 0
                );
                break;
            case 'in':
                filteredItems = originalItems.filter(item => 
                    item.Quantity >= 5
                );
                break;
        }
        
        // Update table with filtered items
        updateTable(filteredItems);
        
        // Update summary cards with filtered counts
        const counts = calculateCounts(filteredItems);
        updateSummaryCards(counts);
        
        // Show message if no items found (reuse existing tableBody reference)
        if (filteredItems.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-search fa-2x mb-2"></i>
                            <h6>No items found</h6>
                            <p>No items match the selected filter.</p>
                        </div>
                    </td>
                </tr>
            `;
        }
    };
});
</script>

</body>
</html>
