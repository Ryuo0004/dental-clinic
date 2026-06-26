<?php
session_start();
include 'db_connect.php';
if (!isset($_SESSION['dentist_name'])) { $_SESSION['dentist_name'] = 'Dr. Dentist'; }
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$dentistId = (int)($_SESSION['dentist_id'] ?? 0);
if ($dentistId <= 0) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Patient Records</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="dentists.css" />
  <style>
    body{background:#f8fafc; color:#0f172a;}
    .layout{min-height:100vh;}
    .content{padding:16px; width:100%;}
    .card{background:#ffffff; color:#0f172a; border:1px solid rgba(29,78,216,0.12); border-radius:12px; box-shadow: 0 6px 16px rgba(29,78,216,0.06), 0 1px 2px rgba(0,0,0,0.03);} 
    .table{color:#0f172a;} .table thead th{color:#475569; border-color: rgba(29,78,216,0.12);} .table td, .table th{border-color: rgba(29,78,216,0.12);} 
    .page-header{gap:12px;}
    .page-header h3{margin:0; font-weight:700; color:#1d4ed8;}
    .search-form{gap:.5rem; flex-wrap:wrap;}
    .search-form .form-control{background:#ffffff; border-color: rgba(29,78,216,0.2); color:#0f172a;} 
    .search-form .btn{font-weight:600;}
    .table-responsive{border-radius:10px; overflow:hidden; border:1px solid rgba(29,78,216,0.12);} 
    /* offset for fixed sidebar */
    .content { margin-left: 260px; }
    @media (max-width: 576px){
      .content{padding:12px; margin-left: 0;}
      .page-header{flex-direction:column; align-items:stretch;}
      .search-form .form-control{width:100%;}
      .search-form .btn{width:100%;}
    }
  </style>
</head>
<body>
  <div class="d-flex layout">
    <?php include 'dentist_sidebar.php'; ?>
    <div class="flex-grow-1 content dentist-main">
      <div class="d-flex align-items-center justify-content-between page-header mb-3">
        <h3>Patient Records</h3>
        <form class="d-flex search-form" method="get">
          <input type="text" class="form-control form-control-sm" name="q" placeholder="Search name or email" value="<?= htmlspecialchars($q) ?>">
          <button class="btn btn-sm btn-primary" type="submit">Search</button>
        </form>
      </div>

      <div class="card p-3">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th></th></tr></thead>
            <tbody>
              <?php
              if (isset($conn)) {
                // Show only patients who have appointments with this dentist (chosen by the patient)
                $sql = "SELECT DISTINCT p.Patient_Id, p.First_name, p.Last_name, p.Email, p.Phone_num
                        FROM tbl_patient p
                        JOIN tbl_appointments a ON (a.Email = p.Email OR a.Patient_Id = p.Patient_Id)
                        WHERE a.Dentist_Id = ? AND a.Status <> 'Cancelled'";
                $types = 'i';
                $params = [$dentistId];
                if ($q !== '') {
                  $sql .= " AND (p.First_name LIKE ? OR p.Last_name LIKE ? OR CONCAT(p.First_name,' ',p.Last_name) LIKE ? OR p.Email LIKE ?)";
                  $types .= 'ssss';
                  $like = '%'.$q.'%';
                  array_push($params, $like, $like, $like, $like);
                }
                $sql .= " ORDER BY p.First_name ASC, p.Last_name ASC";
                $st = $conn->prepare($sql);
                if (!empty($types)) { $st->bind_param($types, ...$params); }
                $st->execute(); $rs = $st->get_result();
                if ($rs && $rs->num_rows) {
                  while ($row = $rs->fetch_assoc()) {
                    $name = trim(($row['First_name']??'').' '.($row['Last_name']??''));
                    echo '<tr>';
                    echo '<td>'.htmlspecialchars($name).'</td>';
                    echo '<td>'.htmlspecialchars($row['Email']).'</td>';
                    echo '<td>'.htmlspecialchars($row['Phone_num'] ?? '').'</td>';
                    echo '<td><a class="btn btn-sm btn-outline-light" href="admin_patient_history.php?email='.urlencode($row['Email']).'">View History</a></td>';
                    echo '</tr>';
                  }
                } else { echo '<tr><td colspan="4" class="text-muted">No records found.</td></tr>'; }
                $st->close();
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
