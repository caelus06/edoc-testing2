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

/* =========================
   FILTERS
========================= */
$q = trim($_GET["q"] ?? "");
$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo   = trim($_GET["date_to"] ?? "");
$actionFilter = strtoupper(trim($_GET["action"] ?? ""));
$roleFilter   = strtoupper(trim($_GET["role"] ?? ""));

$allowedActions = ["", "CREATE", "UPDATE", "DELETE", "STATUS CHANGE", "LOGIN", "ROLE CHANGE"];
if (!in_array($actionFilter, $allowedActions, true)) $actionFilter = "";

$allowedRoles = ["MIS", "REGISTRAR", "USER"];
if (!in_array($roleFilter, $allowedRoles, true)) $roleFilter = "";

/* =========================
REMOVE FROM LN 38 - $allowedRoles = ["", "MIS", "REGISTRAR", "USER", "SYSTEM"];
   ACTION / ROLE CLASSIFIER SQL
========================= */
$actionCase = "
CASE
  WHEN UPPER(rl.message) LIKE '%LOGIN%' THEN 'LOGIN'
  WHEN UPPER(rl.message) LIKE '%ROLE CHANGE%' OR UPPER(rl.message) LIKE '%CHANGED ROLE%' THEN 'ROLE CHANGE'
  WHEN UPPER(rl.message) LIKE '%STATUS UPDATED%' OR UPPER(rl.message) LIKE '%STATUS CHANGED%' OR UPPER(rl.message) LIKE '%ACCOUNT STATUS%' OR UPPER(rl.message) LIKE '%APPLICATION STATUS UPDATED%' OR UPPER(rl.message) LIKE '%RESUBMIT%' OR UPPER(rl.message) LIKE '%VERIFIED%' THEN 'STATUS CHANGE'
  WHEN UPPER(rl.message) LIKE '%DELETED%' OR UPPER(rl.message) LIKE '%REMOVED%' THEN 'DELETE'
  WHEN UPPER(rl.message) LIKE '%UPDATED%' OR UPPER(rl.message) LIKE '%EDITED%' THEN 'UPDATE'
  WHEN UPPER(rl.message) LIKE '%CREATED%' OR UPPER(rl.message) LIKE '%UPLOADED%' OR UPPER(rl.message) LIKE '%NEW REQUEST%' THEN 'CREATE'
  ELSE 'UPDATE'
END
";

$roleCase = "
CASE
  WHEN UPPER(rl.message) LIKE '%MIS%' THEN 'MIS'
  WHEN UPPER(rl.message) LIKE '%REGISTRAR%' THEN 'REGISTRAR'
  WHEN UPPER(rl.message) LIKE '%USER%' THEN 'USER'
END
";

/* =========================
REMOVE FROM LN 62 - ELSE 'SYSTEM
   BASE WHERE 
========================= */
$where = " WHERE 1=1 ";
$params = [];
$types = "";

if ($q !== "") {
  $where .= " AND (rl.message LIKE ? OR COALESCE(r.reference_no,'') LIKE ?)";
  $like = "%".$q."%";
  $params[] = $like;
  $params[] = $like;
  $types .= "ss";
}

if ($dateFrom !== "") {
  $where .= " AND DATE(rl.created_at) >= ?";
  $params[] = $dateFrom;
  $types .= "s";
}

if ($dateTo !== "") {
  $where .= " AND DATE(rl.created_at) <= ?";
  $params[] = $dateTo;
  $types .= "s";
}

if ($actionFilter !== "") {
  $where .= " AND ($actionCase) = ?";
  $params[] = $actionFilter;
  $types .= "s";
}

if ($roleFilter !== "") {
  $where .= " AND ($roleCase) = ?";
  $params[] = $roleFilter;
  $types .= "s";
}

/* =========================
   SUMMARY CARDS
========================= */
function getAuditCount(mysqli $conn, string $where, string $types, array $params, string $extraCondition = ""): int {
  $sql = "
    SELECT COUNT(*) AS c
    FROM request_logs rl
    LEFT JOIN requests r ON r.id = rl.request_id
    $where
    $extraCondition
  ";
  $stmt = $conn->prepare($sql);
  if ($types !== "") $stmt->bind_param($types, ...$params);
  $stmt->execute();
  return (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
}

$totalEvents  = getAuditCount($conn, $where, $types, $params);
$totalCreate  = getAuditCount($conn, $where, $types, $params, " AND ($actionCase) = 'CREATE'");
$totalUpdate  = getAuditCount($conn, $where, $types, $params, " AND ($actionCase) = 'UPDATE'");
$totalDelete  = getAuditCount($conn, $where, $types, $params, " AND ($actionCase) = 'DELETE'");
$totalStatus  = getAuditCount($conn, $where, $types, $params, " AND ($actionCase) = 'STATUS CHANGE'");
$totalLogin   = getAuditCount($conn, $where, $types, $params, " AND ($actionCase) = 'LOGIN'");
$totalRole    = getAuditCount($conn, $where, $types, $params, " AND ($actionCase) = 'ROLE CHANGE'");

/* =========================
   PAGINATION
========================= */
$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$countSql = "
  SELECT COUNT(*) c
  FROM request_logs rl
  LEFT JOIN requests r ON r.id = rl.request_id
  $where
";
$countStmt = $conn->prepare($countSql);
if ($types !== "") $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()["c"] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

/* =========================
   LOGS QUERY
========================= */
$sql = "
SELECT
  rl.message,
  rl.created_at,
  COALESCE(r.reference_no, '-') AS reference_no,
  $actionCase AS action_type,
  $roleCase AS role_type
FROM request_logs rl
LEFT JOIN requests r ON r.id = rl.request_id
$where
ORDER BY rl.created_at DESC, rl.id DESC
LIMIT $perPage OFFSET $offset
";

$stmt = $conn->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function actionBadgeClass($action){
  $a = strtoupper(trim((string)$action));
  return match($a){
    "CREATE" => "act-create",
    "UPDATE" => "act-update",
    "DELETE" => "act-delete",
    "STATUS CHANGE" => "act-status",
    "LOGIN" => "act-login",
    "ROLE CHANGE" => "act-role",
    default => "act-default"
  };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Audit Logs</title>
  <link rel="stylesheet" href="../assets/css/mis_audit_logs.css">
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
      <a class="sb-item" href="reports.php"><span class="sb-icon">📊</span>Reports</a>
      <a class="sb-item active" href="audit_logs.php"><span class="sb-icon">🛡️</span>Audit Logs</a>
    </nav>

    <div class="sb-section-title">SETTINGS</div>
    <nav class="sb-nav">
      <a class="sb-item" href="../auth/logout.php"><span class="sb-icon">⎋</span>Logout</a>
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

    <main class="container">

      <div class="page-head">
        <h1>Audit Logs</h1>
        <p>Track system events, actions, and activity history across all roles.</p>
      </div>

      <section class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Total Events</div>
          <div class="stat-value"><?= $totalEvents ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Create</div>
          <div class="stat-value"><?= $totalCreate ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Update</div>
          <div class="stat-value"><?= $totalUpdate ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Delete</div>
          <div class="stat-value"><?= $totalDelete ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Status Change</div>
          <div class="stat-value"><?= $totalStatus ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Login</div>
          <div class="stat-value"><?= $totalLogin ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Role Change</div>
          <div class="stat-value"><?= $totalRole ?></div>
        </div>
      </section>

      <form class="toolbar" method="GET" action="audit_logs.php">
        <input type="text" name="q" placeholder="Search reference or message" value="<?= h($q) ?>">

        <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
        <input type="date" name="date_to" value="<?= h($dateTo) ?>">

        <select name="action">
          <option value="">All Actions</option>
          <option value="CREATE" <?= $actionFilter==="CREATE" ? "selected" : "" ?>>Create</option>
          <option value="UPDATE" <?= $actionFilter==="UPDATE" ? "selected" : "" ?>>Update</option>
          <option value="DELETE" <?= $actionFilter==="DELETE" ? "selected" : "" ?>>Delete</option>
          <option value="STATUS CHANGE" <?= $actionFilter==="STATUS CHANGE" ? "selected" : "" ?>>Status Change</option>
          <option value="LOGIN" <?= $actionFilter==="LOGIN" ? "selected" : "" ?>>Login</option>
          <option value="ROLE CHANGE" <?= $actionFilter==="ROLE CHANGE" ? "selected" : "" ?>>Role Change</option>
        </select>

        <select name="role">
          <option value="">All Roles</option>
          <option value="MIS" <?= $roleFilter==="MIS" ? "selected" : "" ?>>MIS</option>
          <option value="REGISTRAR" <?= $roleFilter==="REGISTRAR" ? "selected" : "" ?>>Registrar</option>
          <option value="USER" <?= $roleFilter==="USER" ? "selected" : "" ?>>User</option>
          <!-- COMMENTED OUT <option value="SYSTEM" <?= $roleFilter==="SYSTEM" ? "selected" : "" ?>>System</option> -->
        </select>

        <button class="filter-btn" type="submit">FILTER</button>
      </form>

      <section class="table-card">
        <table>
          <thead>
            <tr>
              <th>Reference No.</th>
              <th>Action</th>
              <th>Role</th>
              <th>Activity</th>
              <th>Date / Time</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$logs): ?>
              <tr>
                <td colspan="5" class="empty-row">No logs found.</td>
              </tr>
            <?php else: ?>
              <?php foreach($logs as $log): ?>
                <tr>
                  <td><?= h($log["reference_no"] ?? "-") ?></td>
                  <td>
                    <span class="action-pill <?= actionBadgeClass($log["action_type"] ?? "") ?>">
                      <?= h($log["action_type"] ?? "UPDATE") ?>
                    </span>
                  </td>
                  <!-- DUPLICATE & COMMENTED OUT <td><?= h($log["role_type"] ?? "SYSTEM") ?></td> -->
                  <td><?= h($log["role_type"] ?? "REGISTRAR") ?></td>
                  <td><?= h($log["message"] ?? "") ?></td>
                  <td><?= date("M d, Y g:i A", strtotime($log["created_at"])) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="pagination">
          <?php
            $prev = max(1, $page - 1);
            $next = min($totalPages, $page + 1);
          ?>
          <a href="audit_logs.php?<?= http_build_query(array_merge($_GET, ["page"=>$prev])) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">Prev</a>
          <div class="current"><?= $page ?></div>
          <a href="audit_logs.php?<?= http_build_query(array_merge($_GET, ["page"=>$next])) ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">Next</a>
        </div>
      </section>
    </main>
  </div>
</div>

<div class="footer-bar"></div>

<script>
function toggleSidebar(){
  const sb = document.getElementById('sidebar');
  if(window.innerWidth <= 720){
    sb.style.display = sb.style.display === "block" ? "none" : "block";
  }
}
</script>

</body>
</html>
