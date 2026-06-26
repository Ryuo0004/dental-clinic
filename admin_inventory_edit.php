<?php
$baseDir = __DIR__;
if (file_exists($baseDir . '/admin_init.php')) {
  include 'admin_init.php';
} else {
  include 'db_connect.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

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
  // Ensure Description column exists so edits persist even when visiting edit page directly
  if ($res = $conn->query("SHOW COLUMNS FROM tbl_inventory LIKE 'Description'")) {
    if ($res->num_rows === 0) {
      $conn->query("ALTER TABLE tbl_inventory ADD COLUMN Description TEXT NULL");
    }
  }
}

$item = null;
$message = '';
$isEdit = false;
// Treatments list for suggestions
$treatmentsList = [];

// Load distinct treatments for selection (from inventory Used_For + tbl_treatments + config)
if (isset($conn)) {
  if ($crUF = $conn->query("SELECT DISTINCT Used_For FROM tbl_inventory WHERE Used_For IS NOT NULL AND Used_For <> '' ORDER BY Used_For ASC")) {
    while ($r = $crUF->fetch_assoc()) { $treatmentsList[] = $r['Used_For']; }
  }
  try {
    if ($res = $conn->query("SHOW TABLES LIKE 'tbl_treatments'")) {
      if ($res->num_rows > 0) {
        if ($qr = $conn->query("SELECT name FROM tbl_treatments ORDER BY name ASC")) {
          while ($row = $qr->fetch_assoc()) { if (!empty($row['name'])) { $treatmentsList[] = $row['name']; } }
        }
      }
    }
  } catch (Throwable $e) {}
}
// Merge with config treatments
if (file_exists(__DIR__ . '/appointment_config.php')) {
  include __DIR__ . '/appointment_config.php';
  if (!empty($treatments) && is_array($treatments)) {
    foreach ($treatments as $t) { if (!empty($t['name'])) { $treatmentsList[] = $t['name']; } }
  }
}
// Unique and sort
$treatmentsList = array_values(array_unique(array_filter(array_map('strval', $treatmentsList))));
sort($treatmentsList, SORT_NATURAL | SORT_FLAG_CASE);

// Fetch item by ID (for editing)
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // Detect if Description column exists to include it in SELECT
    $hasDescTop = false;
    if ($cr0 = $conn->query("SHOW COLUMNS FROM tbl_inventory LIKE 'Description'")) { $hasDescTop = $cr0->num_rows > 0; }
    $descSelect = $hasDescTop ? ", Description" : "";
    $sqlSel = "SELECT 
                              Item_Id AS Inventory_Id,
                              Item_Name,
                              Current_Stock,
                              Unit,
                              Reorder_Level,
                              Category,
                              Supplier,
                              Expiration_Date,
                              Unit_Cost" . $descSelect . ",
                              Updated_At AS Last_update,
                              Created_At AS Dated_added
                            FROM tbl_inventory WHERE Item_Id=?";
    $stmt = $conn->prepare($sqlSel);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    
    if (!$item) {
        $message = "Item not found.";
    } else {
        $isEdit = true;
    }
} else {
    // Initialize empty item for adding new item
    $item = [
        'Inventory_Id' => '',
        'Item_Name' => '',
        'Current_Stock' => 0,
        'Unit' => '',
        'Reorder_Level' => 5,
        'Supplier' => '',
        'Category' => '',
        'Expiration_Date' => '',
        'Unit_Cost' => '',
        'Dated_added' => '',
        'Last_update' => ''
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['Item_name'] ?? $_POST['Item_Name'] ?? '');
    $quantity = isset($_POST['Quantity']) ? (int)$_POST['Quantity'] : (isset($_POST['Current_Stock']) ? (int)$_POST['Current_Stock'] : 0);
    $unit = trim($_POST['Unit'] ?? '');
    $threshold = isset($_POST['Threshold']) ? (int)$_POST['Threshold'] : (isset($_POST['Reorder_Level']) ? (int)$_POST['Reorder_Level'] : 5);
    $supplier = isset($_POST['Supplier']) ? trim($_POST['Supplier']) : '';
    $used_for = isset($_POST['Used_For']) ? trim($_POST['Used_For']) : '';
    $expiration = isset($_POST['Expiration_Date']) ? trim($_POST['Expiration_Date']) : '';
    $unit_cost = isset($_POST['Unit_Cost']) && $_POST['Unit_Cost'] !== '' ? (float)$_POST['Unit_Cost'] : null;
    $description = isset($_POST['Description']) ? trim($_POST['Description']) : '';
    // Detect inventory schema to update the right columns
    $cols = [];
    if ($cr = $conn->query("SHOW COLUMNS FROM tbl_inventory")) {
        while ($c = $cr->fetch_assoc()) { $cols[strtolower($c['Field'])] = $c['Field']; }
    }
    $qtyField = isset($cols['current_stock']) ? 'Current_Stock' : (isset($cols['quantity']) ? 'Quantity' : 'Current_Stock');
    $thrField = isset($cols['reorder_level']) ? 'Reorder_Level' : (isset($cols['threshold']) ? 'Threshold' : 'Reorder_Level');
    $nameField = isset($cols['item_name']) ? 'Item_Name' : 'Item_Name';
    $idField = isset($cols['item_id']) ? 'Item_Id' : 'Item_Id';
    $hasUpdatedAt = isset($cols['updated_at']);
    $hasLastUpdate = isset($cols['last_update']);
    $hasDescription = isset($cols['description']);
    
    if (!empty($item_name)) {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Update existing item
            $id = (int)$_POST['id'];
            // Fetch current quantity to compute delta
            $oldQty = 0;
            if ($qst = $conn->prepare("SELECT COALESCE(Current_Stock, Quantity, 0) AS Q FROM tbl_inventory WHERE Item_Id = ?")) {
              $qst->bind_param('i', $id);
              if ($qst->execute()) { $qr = $qst->get_result(); if ($rw = $qr->fetch_assoc()) { $oldQty = (int)$rw['Q']; } }
              $qst->close();
            }
            // Build dynamic SET parts
            $setParts = [];
            $types = '';
            $bind = [];
            // Name
            $setParts[] = "$nameField = ?"; $types .= 's'; $bind[] = $item_name;
            // Quantity (support both schemas)
            if ($qtyField === 'Current_Stock' && isset($cols['current_stock'])) { $setParts[] = "Current_Stock = ?"; $types .= 'i'; $bind[] = $quantity; }
            if (isset($cols['quantity'])) { $setParts[] = "Quantity = ?"; $types .= 'i'; $bind[] = $quantity; }
            // Other fields
            $setParts[] = "Unit = ?"; $types .= 's'; $bind[] = $unit;
            $setParts[] = "$thrField = ?"; $types .= 'i'; $bind[] = $threshold;
            $setParts[] = "Used_For = ?"; $types .= 's'; $bind[] = $used_for;
            $setParts[] = "Supplier = ?"; $types .= 's'; $bind[] = $supplier;
            $setParts[] = "Expiration_Date = ?"; $types .= 's'; $bind[] = $expiration;
            $setParts[] = "Unit_Cost = ?"; $types .= 'd'; $bind[] = $unit_cost;
            if ($hasDescription) { $setParts[] = "Description = ?"; $types .= 's'; $bind[] = $description; }
            if ($hasUpdatedAt) { $setParts[] = "Updated_At = NOW()"; }
            if ($hasLastUpdate) { $setParts[] = "Last_update = NOW()"; }
            $sql = "UPDATE tbl_inventory SET " . implode(", ", $setParts) . " WHERE $idField = ?";
            $types .= 'i'; $bind[] = $id;
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$bind);
            
            if ($stmt->execute()) {
                $message = "Item updated successfully!";
                // Refresh item data
                $stmt2 = $conn->prepare("SELECT 
                                            Item_Id AS Inventory_Id,
                                            Item_Name,
                                            COALESCE(Current_Stock, Quantity) AS Current_Stock,
                                            Unit,
                                            COALESCE(Reorder_Level, Threshold) AS Reorder_Level,
                                            Category,
                                            Supplier,
                                            Expiration_Date,
                                            Unit_Cost,
                                            COALESCE(Updated_At, Last_update) AS Last_update,
                                            COALESCE(Created_At, Last_update) AS Dated_added
                                          FROM tbl_inventory WHERE Item_Id=?");
                $stmt2->bind_param("i", $id);
                $stmt2->execute();
                $result = $stmt2->get_result();
                $item = $result->fetch_assoc();
                $stmt2->close();
                // Enrich with Description if column exists
                if ($cr = $conn->query("SHOW COLUMNS FROM tbl_inventory LIKE 'Description'")) {
                  if ($cr->num_rows > 0) {
                    if ($stmtD = $conn->prepare("SELECT Description FROM tbl_inventory WHERE Item_Id=?")) {
                      $stmtD->bind_param("i", $id);
                      $stmtD->execute();
                      $resD = $stmtD->get_result();
                      if ($rowD = $resD->fetch_assoc()) { $item['Description'] = $rowD['Description']; }
                      $stmtD->close();
                    }
                  }
                }
                $isEdit = true;
                // Audit log
                $who = $_SESSION['admin_name'] ?? ($_SESSION['admin_email'] ?? 'system');
                $email = $_SESSION['admin_email'] ?? '';
                if ($log = $conn->prepare("INSERT INTO tbl_inventory_audit (Inventory_Id, Action, Changed_By, Changed_By_Email, Details) VALUES (?, 'update', ?, ?, ?)" ) ) {
                  $details = 'Updated: '. $item_name . '; exp_at:' . ($expiration !== '' ? $expiration : '');
                  $log->bind_param('isss', $id, $who, $email, $details);
                  $log->execute();
                  $log->close();
                }
                // Quantity change audit (structured for history modal)
                try {
                  $newQty = (int)$quantity; $delta = $newQty - $oldQty;
                  if ($delta !== 0) {
                    if ($log2 = $conn->prepare("INSERT INTO tbl_inventory_audit (Inventory_Id, Action, Changed_By, Changed_By_Email, Details) VALUES (?, 'quantity_change', ?, ?, ?)" ) ) {
                      $det2 = 'qty_delta:' . ($delta >= 0 ? ('+' . $delta) : (string)$delta) . '; new_qty:' . $newQty . '; exp_at:' . ($expiration !== '' ? $expiration : '');
                      $log2->bind_param('isss', $id, $who, $email, $det2);
                      $log2->execute();
                      $log2->close();
                    }
                  }
                } catch (Throwable $e) { /* ignore */ }
            } else {
                $message = "Error updating item: " . $conn->error;
            }
            $stmt->close();
        } else {
            // Insert new item (support schema variants and optional Description)
            $qtyInsertField = isset($cols['current_stock']) ? 'Current_Stock' : (isset($cols['quantity']) ? 'Quantity' : 'Current_Stock');
            $thrInsertField = isset($cols['reorder_level']) ? 'Reorder_Level' : (isset($cols['threshold']) ? 'Threshold' : 'Reorder_Level');

            $columns = ['Item_Name', $qtyInsertField, 'Unit', $thrInsertField, 'Used_For', 'Supplier', 'Expiration_Date', 'Unit_Cost'];
            $placeholders = [];
            $typesIns = '';
            $bindIns = [];

            // Item_Name
            $placeholders[] = '?'; $typesIns .= 's'; $bindIns[] = $item_name;
            // Quantity
            $placeholders[] = '?'; $typesIns .= 'i'; $bindIns[] = $quantity;
            // Unit
            $placeholders[] = '?'; $typesIns .= 's'; $bindIns[] = $unit;
            // Threshold
            $placeholders[] = '?'; $typesIns .= 'i'; $bindIns[] = $threshold;
            // Used_For
            $placeholders[] = '?'; $typesIns .= 's'; $bindIns[] = $used_for;
            // Supplier
            $placeholders[] = '?'; $typesIns .= 's'; $bindIns[] = $supplier;
            // Expiration_Date
            $placeholders[] = '?'; $typesIns .= 's'; $bindIns[] = $expiration;
            // Unit_Cost
            $placeholders[] = '?'; $typesIns .= 'd'; $bindIns[] = $unit_cost;

            if ($hasDescription) { $columns[] = 'Description'; $placeholders[] = '?'; $typesIns .= 's'; $bindIns[] = $description; }

            $sqlIns = "INSERT INTO tbl_inventory (" . implode(", ", $columns) . ", Updated_At, Created_At) VALUES (" . implode(", ", $placeholders) . ", NOW(), NOW())";
            $stmt = $conn->prepare($sqlIns);
            $stmt->bind_param($typesIns, ...$bindIns);
            
            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                $message = "Item added successfully!";
                // Fetch the newly created item
                $stmt2 = $conn->prepare("SELECT 
                                            Item_Id AS Inventory_Id,
                                            Item_Name,
                                            Current_Stock,
                                            Unit,
                                            Reorder_Level,
                                            Category,
                                            Supplier,
                                            Expiration_Date,
                                            Unit_Cost,
                                            Updated_At AS Last_update,
                                            Created_At AS Dated_added
                                          FROM tbl_inventory WHERE Item_Id=?");
                $stmt2->bind_param("i", $newId);
                $stmt2->execute();
                $result = $stmt2->get_result();
                $item = $result->fetch_assoc();
                $stmt2->close();
                // Enrich with Description if column exists
                if ($cr = $conn->query("SHOW COLUMNS FROM tbl_inventory LIKE 'Description'")) {
                  if ($cr->num_rows > 0) {
                    if ($stmtD = $conn->prepare("SELECT Description FROM tbl_inventory WHERE Item_Id=?")) {
                      $stmtD->bind_param("i", $newId);
                      $stmtD->execute();
                      $resD = $stmtD->get_result();
                      if ($rowD = $resD->fetch_assoc()) { $item['Description'] = $rowD['Description']; }
                      $stmtD->close();
                    }
                  }
                }
                $isEdit = true;
                // Audit log
                $who = $_SESSION['admin_name'] ?? ($_SESSION['admin_email'] ?? 'system');
                $email = $_SESSION['admin_email'] ?? '';
                if ($log = $conn->prepare("INSERT INTO tbl_inventory_audit (Inventory_Id, Action, Changed_By, Changed_By_Email, Details) VALUES (?, 'add', ?, ?, ?)")) {
                  $details = 'Added: '. $item_name . '; exp_at:' . ($expiration !== '' ? $expiration : '');
                  $log->bind_param('isss', $newId, $who, $email, $details);
                  $log->execute();
                  $log->close();
                }
                // Initial quantity audit as quantity_change for history modal
                try {
                  $initQty = (int)$quantity;
                  if ($initQty !== 0) {
                    if ($log2 = $conn->prepare("INSERT INTO tbl_inventory_audit (Inventory_Id, Action, Changed_By, Changed_By_Email, Details) VALUES (?, 'quantity_change', ?, ?, ?)" ) ) {
                      $det2 = 'qty_delta:+' . $initQty . '; new_qty:' . $initQty . '; exp_at:' . ($expiration !== '' ? $expiration : '');
                      $log2->bind_param('isss', $newId, $who, $email, $det2);
                      $log2->execute();
                      $log2->close();
                    }
                  }
                } catch (Throwable $e) { /* ignore */ }
            } else {
                $message = "Error adding item: " . $conn->error;
            }
            $stmt->close();
        }
    } else {
        $message = "Item name is required.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $isEdit ? 'Edit' : 'Add' ?> Inventory Item - Miles Dental Clinic</title>
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
  </style>
</head>
<body>
<div class="d-flex">
  <?php if (file_exists($baseDir . '/admin_sidebar.php')) { include 'admin_sidebar.php'; } else { ?>
  <div class="sidebar">
    <h3>Miles Dental</h3>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="appointment.php">Appointments</a>
    <a href="admin_messages.php">Messages</a>
    <a href="admin_inventory.php">Inventory</a>
    <a href="preferences.php">Settings</a>
    <a href="logout.php">Logout</a>
  </div>
  <?php } ?>

  <div class="content flex-grow-1">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0"><?= $isEdit ? 'Edit' : 'Add' ?> Inventory Item</h2>
      <a href="admin_inventory.php" class="btn btn-outline-secondary btn-sm">Back to Inventory</a>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?= strpos($message, 'Error') !== false ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if ($item || !$isEdit): ?>
      <div class="card">
        <div class="card-body">
          <form method="post">
            <?php if ($isEdit && !empty($item['Inventory_Id'])): ?>
              <input type="hidden" name="id" value="<?= $item['Inventory_Id'] ?>">
            <?php endif; ?>
            
            <div class="mb-3">
              <label for="Item_name" class="form-label">Item Name *</label>
              <input type="text" class="form-control" id="Item_name" name="Item_name" 
                     value="<?= htmlspecialchars($item['Item_Name'] ?? '') ?>" required>
            </div>
            
            <div class="mb-3">
              <label for="Description" class="form-label">Description</label>
              <textarea class="form-control" id="Description" name="Description" rows="3"><?= htmlspecialchars($item['Description'] ?? '') ?></textarea>
            </div>
            
            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="Quantity" class="form-label">Quantity</label>
                  <input type="number" class="form-control" id="Quantity" name="Quantity" 
                         value="<?= (int)($item['Current_Stock'] ?? 0) ?>" min="0" step="1" inputmode="numeric" pattern="\d*">
                </div>
              </div>
              
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="Unit" class="form-label">Unit</label>
                  <input type="text" class="form-control" id="Unit" name="Unit" 
                         value="<?= htmlspecialchars($item['Unit']) ?>" placeholder="e.g., pieces, boxes, ml">
                </div>
              </div>
              
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="Threshold" class="form-label">Low Stock Threshold</label>
                  <input type="number" class="form-control" id="Threshold" name="Threshold" 
                         value="<?= (int)($item['Reorder_Level'] ?? 0) ?>" min="0">
                  <small class="form-text text-muted">Items with quantity below 5 will show as "Low Stock"</small>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="Used_For" class="form-label">Treatment</label>
                  <select class="form-select" id="Used_For" name="Used_For">
                    <option value="">Select treatment</option>
                    <?php foreach ($treatmentsList as $opt): 
                      $isSel = (isset($item['Used_For']) && strcasecmp((string)$item['Used_For'], (string)$opt) === 0) ? ' selected' : '';
                    ?>
                      <option value="<?= htmlspecialchars($opt) ?>"<?= $isSel ?>><?= htmlspecialchars($opt) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small class="text-muted">Pick one treatment. To map multiple treatments per item, use the Treatment-Inventory mapping screen.</small>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="Supplier" class="form-label">Supplier</label>
                  <input type="text" class="form-control" id="Supplier" name="Supplier" value="<?= htmlspecialchars($item['Supplier'] ?? '') ?>" placeholder="Supplier name">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="Expiration_Date" class="form-label">Expiration Date</label>
                  <input type="date" class="form-control" id="Expiration_Date" name="Expiration_Date" value="<?= htmlspecialchars(!empty($item['Expiration_Date']) ? date('Y-m-d', strtotime($item['Expiration_Date'])) : '') ?>">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="Unit_Cost" class="form-label">Unit Cost (₱)</label>
                  <input type="number" step="0.01" min="0" class="form-control" id="Unit_Cost" name="Unit_Cost" value="<?= htmlspecialchars($item['Unit_Cost'] !== null && $item['Unit_Cost'] !== '' ? $item['Unit_Cost'] : '') ?>" placeholder="0.00">
                </div>
              </div>
            </div>
            
            <?php if ($isEdit && !empty($item['Dated_added'])): ?>
              <div class="mb-3">
                <small class="text-muted">
                  Added: <?= htmlspecialchars($item['Dated_added']) ?> | 
                  Last Updated: <?= htmlspecialchars($item['Last_update']) ?>
                </small>
              </div>
            <?php endif; ?>
            
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update' : 'Add' ?> Item</button>
              <a href="admin_inventory.php" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-warning">
        <h4>Item Not Found</h4>
        <p>The requested inventory item could not be found.</p>
        <a href="admin_inventory.php" class="btn btn-primary">Back to Inventory</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
