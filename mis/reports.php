<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_MIS);

$misId = (int)$_SESSION["user_id"];

/* MIS name */
$misName = "MIS Admin";
$me = $conn->prepare("SELECT first_name, last_name FROM users WHERE id=? LIMIT 1");
$me->bind_param("i", $misId);
$me->execute();
$mr = $me->get_result()->fetch_assoc();
if ($mr) {
  $misName = trim(($mr["first_name"] ?? "") . " " . ($mr["last_name"] ?? ""));
}

/* filters */
$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo   = trim($_GET["date_to"] ?? "");
$docType  = trim($_GET["doc_type"] ?? "");

/* request filter where */
$where = " WHERE 1=1 ";
$params = [];
$types = "";

if ($dateFrom !== "") {
  $where .= " AND DATE(created_at) >= ?";
  $params[] = $dateFrom;
  $types .= "s";
}
if ($dateTo !== "") {
  $where .= " AND DATE(created_at) <= ?";
  $params[] = $dateTo;
  $types .= "s";
}
if ($docType !== "") {
  $where .= " AND document_type = ?";
  $params[] = $docType;
  $types .= "s";
}

/* summary counts */
$totalUsers = 0;
$totalRequests = 0;
$todayRequests = 0;
$pendingRequests = 0;

$q1 = $conn->query("SELECT COUNT(*) AS c FROM users");
if ($q1) $totalUsers = (int)($q1->fetch_assoc()["c"] ?? 0);

$q2 = $conn->prepare("SELECT COUNT(*) AS c FROM requests $where");
if ($types !== "") $q2->bind_param($types, ...$params);
$q2->execute();
$totalRequests = (int)($q2->get_result()->fetch_assoc()["c"] ?? 0);

$q3 = $conn->query("SELECT COUNT(*) AS c FROM requests WHERE DATE(created_at)=CURDATE()");
if ($q3) $todayRequests = (int)($q3->fetch_assoc()["c"] ?? 0);

$q4 = $conn->query("SELECT COUNT(*) AS c FROM requests WHERE UPPER(TRIM(COALESCE(status,'')))='PENDING'");
if ($q4) $pendingRequests = (int)($q4->fetch_assoc()["c"] ?? 0);

/* document types for filter */
$docTypes = [];
$dt = $conn->query("SELECT DISTINCT document_type FROM requests ORDER BY document_type ASC");
if ($dt) {
  while ($r = $dt->fetch_assoc()) {
    $docTypes[] = $r["document_type"];
  }
}

/* request status summary */
$requestStatusRows = [];
$sqlStatus = "
  SELECT
    UPPER(TRIM(COALESCE(status,'PENDING'))) AS status_name,
    COUNT(*) AS total
  FROM requests
  $where
  GROUP BY UPPER(TRIM(COALESCE(status,'PENDING')))
  ORDER BY total DESC, status_name ASC
";
$stStatus = $conn->prepare($sqlStatus);
if ($types !== "") $stStatus->bind_param($types, ...$params);
$stStatus->execute();
$requestStatusRows = $stStatus->get_result()->fetch_all(MYSQLI_ASSOC);

$statusLabels = [];
$statusTotals = [];
foreach ($requestStatusRows as $row) {
  $statusLabels[] = $row["status_name"];
  $statusTotals[] = (int)$row["total"];
}

/* document type summary */
$docTypeRows = [];
$sqlDoc = "
  SELECT document_type, COUNT(*) AS total
  FROM requests
  $where
  GROUP BY document_type
  ORDER BY total DESC, document_type ASC
";
$stDoc = $conn->prepare($sqlDoc);
if ($types !== "") $stDoc->bind_param($types, ...$params);
$stDoc->execute();
$docTypeRows = $stDoc->get_result()->fetch_all(MYSQLI_ASSOC);

$docLabels = [];
$docTotals = [];
foreach ($docTypeRows as $row) {
  $docLabels[] = $row["document_type"] ?: "UNKNOWN";
  $docTotals[] = (int)$row["total"];
}

/* requests per month */
$monthRows = [];
$sqlMonth = "
  SELECT
    DATE_FORMAT(created_at, '%b %Y') AS month_label,
    YEAR(created_at) AS y,
    MONTH(created_at) AS m,
    COUNT(*) AS total
  FROM requests
  $where
  GROUP BY YEAR(created_at), MONTH(created_at), DATE_FORMAT(created_at, '%b %Y')
  ORDER BY YEAR(created_at), MONTH(created_at)
";
$stMonth = $conn->prepare($sqlMonth);
if ($types !== "") $stMonth->bind_param($types, ...$params);
$stMonth->execute();
$monthRows = $stMonth->get_result()->fetch_all(MYSQLI_ASSOC);

$monthLabels = [];
$monthTotals = [];
foreach ($monthRows as $row) {
  $monthLabels[] = $row["month_label"];
  $monthTotals[] = (int)$row["total"];
}

/* user role summary */
$userRoleRows = [];
$qRole = $conn->query("
  SELECT UPPER(COALESCE(role,'USER')) AS role_name, COUNT(*) AS total
  FROM users
  GROUP BY UPPER(COALESCE(role,'USER'))
  ORDER BY total DESC, role_name ASC
");
if ($qRole) {
  while ($r = $qRole->fetch_assoc()) {
    $userRoleRows[] = $r;
  }
}

/* recent activity */
$activities = [];
$sqlLog = "
  SELECT rl.message, rl.created_at, r.reference_no
  FROM request_logs rl
  LEFT JOIN requests r ON r.id = rl.request_id
  ORDER BY rl.created_at DESC, rl.id DESC
  LIMIT 12
";
$qLog = $conn->query($sqlLog);
if ($qLog) {
  while ($r = $qLog->fetch_assoc()) {
    $activities[] = $r;
  }
}

function statusBadgeClass($status){
  $s = strtoupper(trim((string)$status));
  return match($s){
    "VERIFIED" => "status-verified",
    "APPROVED" => "status-approved",
    "PROCESSING" => "status-processing",
    "READY FOR PICKUP" => "status-ready",
    "COMPLETED" => "status-completed",
    "RETURNED", "RESUBMIT" => "status-returned",
    default => "status-pending"
  };
}

$excelQuery = http_build_query([
  "date_from" => $dateFrom,
  "date_to"   => $dateTo,
  "doc_type"  => $docType
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MIS Reports</title>
  <link rel="stylesheet" href="../assets/css/mis_reports.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="layout">

  <aside class="sidebar" id="sidebar">
    <div class="sb-user">
      <div class="avatar">👤</div>
      <div class="meta">
        <div class="name"><?= h($misName) ?></div>
        <div class="role">MIS Admin</div>
      </div>
    </div>

    <div class="sb-badge">MIS Admin</div>

    <div class="sb-section-title">MODULES</div>
    <nav class="sb-nav">
      <a class="sb-item" href="dashboard.php"><span class="sb-icon">🏠</span>Dashboard</a>
      <a class="sb-item" href="account_management.php"><span class="sb-icon">👥</span>Account Management</a>
      <a class="sb-item active" href="reports.php"><span class="sb-icon">📊</span>Reports</a>
      <a class="sb-item" href="audit_logs.php"><span class="sb-icon">🛡️</span>Audit Logs</a>
      <a class="sb-item" href="system_settings.php"><span class="sb-icon">&#9881;</span>System Settings</a>
    </nav>

    <div class="sb-section-title">SETTINGS</div>
    <nav class="sb-nav">
      <a class="sb-item" href="#" onclick="event.preventDefault(); swalConfirm('Logout', 'Are you sure you want to log out?', 'Yes, log out', function(){ window.location='../auth/logout.php'; })"><span class="sb-icon">⎋</span>Logout</a>
    </nav>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="hamburger" type="button" onclick="toggleSidebar()">≡</button>

      <div class="brand">
        <div class="logo">
          <img src="../assets/img/edoc-logo.jpeg" alt="E-Doc">
        </div>
        <div>E-Doc Document Requesting System</div>
      </div>
    </header>

    <main class="container" id="reportArea">

      <div class="page-head">
        <h1>Reports</h1>
        <p>System overview, request summaries, charts, and recent activity reports.</p>
      </div>

      <section class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Total Users</div>
          <div class="stat-value"><?= (int)$totalUsers ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Requests</div>
          <div class="stat-value"><?= (int)$totalRequests ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Today's Requests</div>
          <div class="stat-value"><?= (int)$todayRequests ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Pending Requests</div>
          <div class="stat-value"><?= (int)$pendingRequests ?></div>
        </div>
      </section>

      <form class="toolbar no-print" method="GET" action="reports.php">
        <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
        <input type="date" name="date_to" value="<?= h($dateTo) ?>">

        <select name="doc_type">
          <option value="">-- All Document Types --</option>
          <?php foreach ($docTypes as $d): ?>
            <option value="<?= h($d) ?>" <?= $docType === $d ? "selected" : "" ?>>
              <?= h($d) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button type="submit" class="filter-btn">FILTER</button>
      </form>

      <div class="report-grid">
        <section class="card chart-card">
          <div class="card-title">Request Status Summary</div>
          <div class="chart-container">
            <canvas id="statusChart"></canvas>
          </div>
        </section>

        <section class="card chart-card">
          <div class="card-title">Document Type Distribution</div>
          <div class="chart-container">
            <canvas id="docChart"></canvas>
          </div>
        </section>
      </div>

      <section class="card chart-card full-width">
        <div class="card-title">Requests Per Month</div>
        <div class="chart-container line-chart">
          <canvas id="monthChart"></canvas>
        </div>
      </section>

      <div class="report-grid">
        <section class="card">
          <div class="card-title">Request Status Table</div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Status</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($requestStatusRows) === 0): ?>
                  <tr><td colspan="2" class="empty-row">No data found.</td></tr>
                <?php else: ?>
                  <?php foreach ($requestStatusRows as $r): ?>
                    <tr>
                      <td>
                        <span class="status-pill <?= statusBadgeClass($r["status_name"] ?? "") ?>">
                          <?= h($r["status_name"] ?? "PENDING") ?>
                        </span>
                      </td>
                      <td><?= (int)($r["total"] ?? 0) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <section class="card">
          <div class="card-title">User Role Summary</div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Role</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($userRoleRows) === 0): ?>
                  <tr><td colspan="2" class="empty-row">No data found.</td></tr>
                <?php else: ?>
                  <?php foreach ($userRoleRows as $r): ?>
                    <tr>
                      <td><?= h($r["role_name"] ?? "USER") ?></td>
                      <td><?= (int)($r["total"] ?? 0) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <section class="card activity-card">
        <div class="card-title">Recent System Activity</div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Activity</th>
                <th>Reference No.</th>
                <th>Date / Time</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($activities) === 0): ?>
                <tr><td colspan="3" class="empty-row">No recent activity found.</td></tr>
              <?php else: ?>
                <?php foreach ($activities as $a): ?>
                  <tr>
                    <td><?= h($a["message"] ?? "System activity") ?></td>
                    <td><?= h($a["reference_no"] ?? "-") ?></td>
                    <td><?= h(date("M j, Y g:i A", strtotime($a["created_at"] ?? "now"))) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

    </main>
  </div>
</div>

<div class="footer-bar"></div>

<script>
function toggleSidebar(){
  const sb = document.getElementById('sidebar');
  if(!sb) return;
  if (window.innerWidth <= 720) {
    sb.style.display = (sb.style.display === 'none' || sb.style.display === '') ? 'block' : 'none';
  }
}

const statusCtx = document.getElementById('statusChart');
new Chart(statusCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($statusLabels) ?>,
    datasets: [{
      label: 'Requests',
      data: <?= json_encode($statusTotals) ?>,
      backgroundColor: ['#f4b942','#4a90e2','#2ecc71','#1abc9c','#95a5a6','#e74c3c','#8e44ad'],
      borderRadius: 8
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { precision: 0 }
      }
    }
  }
});

const docCtx = document.getElementById('docChart');
new Chart(docCtx, {
  type: 'pie',
  data: {
    labels: <?= json_encode($docLabels) ?>,
    datasets: [{
      data: <?= json_encode($docTotals) ?>,
      backgroundColor: ['#3498db','#9b59b6','#1abc9c','#f39c12','#e74c3c','#2ecc71','#34495e','#f1c40f']
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false
  }
});

const monthCtx = document.getElementById('monthChart');
new Chart(monthCtx, {
  type: 'line',
  data: {
    labels: <?= json_encode($monthLabels) ?>,
    datasets: [{
      label: 'Requests',
      data: <?= json_encode($monthTotals) ?>,
      borderColor: '#3498db',
      backgroundColor: 'rgba(52, 152, 219, 0.15)',
      fill: true,
      tension: 0.3,
      pointRadius: 4,
      pointHoverRadius: 5
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: {
        beginAtZero: true,
        ticks: { precision: 0 }
      }
    }
  }
});
</script>

</body>
</html>
