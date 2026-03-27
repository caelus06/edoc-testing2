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
$q            = trim($_GET["q"] ?? "");
$dateFrom     = trim($_GET["date_from"] ?? "");
$dateTo       = trim($_GET["date_to"] ?? "");
$actionFilter = strtoupper(trim($_GET["action"] ?? ""));
$roleFilter   = strtoupper(trim($_GET["role"] ?? ""));

$allowedActions = ["", "INSERT", "UPDATE", "DELETE", "LOGIN", "LOGOUT"];
if (!in_array($actionFilter, $allowedActions, true)) $actionFilter = "";

$allowedRoles = ["", "MIS", "REGISTRAR", "USER"];
if (!in_array($roleFilter, $allowedRoles, true)) $roleFilter = "";

/* =========================
   BASE QUERY BUILDING
========================= */
$where  = " WHERE 1=1 ";
$params = [];
$types  = "";

if ($q !== "") {
  $where .= " AND (al.details LIKE ? OR CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) LIKE ?)";
  $like = "%" . $q . "%";
  $params[] = $like;
  $params[] = $like;
  $types .= "ss";
}

if ($dateFrom !== "") {
  $where .= " AND DATE(al.created_at) >= ?";
  $params[] = $dateFrom;
  $types .= "s";
}

if ($dateTo !== "") {
  $where .= " AND DATE(al.created_at) <= ?";
  $params[] = $dateTo;
  $types .= "s";
}

if ($actionFilter !== "") {
  $where .= " AND al.action = ?";
  $params[] = $actionFilter;
  $types .= "s";
}

if ($roleFilter !== "") {
  $where .= " AND u.role = ?";
  $params[] = $roleFilter;
  $types .= "s";
}

/* =========================
   SUMMARY CARDS
========================= */
function getAuditCount(mysqli $conn, string $where, string $types, array $params, string $extra = ""): int {
  $sql = "
    SELECT COUNT(*) AS c
    FROM audit_logs al
    LEFT JOIN users u ON u.id = al.user_id
    $where $extra
  ";
  $stmt = $conn->prepare($sql);
  if ($types !== "") $stmt->bind_param($types, ...$params);
  $stmt->execute();
  return (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
}

$totalEvents = getAuditCount($conn, $where, $types, $params);
$totalInsert = getAuditCount($conn, $where, $types, $params, " AND al.action='INSERT'");
$totalUpdate = getAuditCount($conn, $where, $types, $params, " AND al.action='UPDATE'");
$totalDelete = getAuditCount($conn, $where, $types, $params, " AND al.action='DELETE'");
$totalLogin  = getAuditCount($conn, $where, $types, $params, " AND al.action='LOGIN'");
$totalLogout = getAuditCount($conn, $where, $types, $params, " AND al.action='LOGOUT'");

/* =========================
   PAGINATION
========================= */
$page    = max(1, (int)($_GET["page"] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$countSql  = "SELECT COUNT(*) c FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id $where";
$countStmt = $conn->prepare($countSql);
if ($types !== "") $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total      = (int)($countStmt->get_result()->fetch_assoc()["c"] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

/* =========================
   LOGS QUERY
========================= */
$sql = "
SELECT
  al.id,
  al.action,
  al.table_name,
  al.record_id,
  al.details,
  al.ip_address,
  al.created_at,
  al.user_id,
  COALESCE(CONCAT(u.first_name, ' ', u.last_name), '—') AS performed_by,
  COALESCE(u.role, '—') AS user_role
FROM audit_logs al
LEFT JOIN users u ON u.id = al.user_id
$where
ORDER BY al.created_at DESC, al.id DESC
LIMIT $perPage OFFSET $offset
";

$stmt = $conn->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function actionBadgeClass($action) {
  return match(strtoupper(trim((string)$action))) {
    "INSERT" => "act-create",
    "UPDATE" => "act-update",
    "DELETE" => "act-delete",
    "LOGIN"  => "act-login",
    "LOGOUT" => "act-logout",
    default  => "act-default"
  };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Audit Logs</title>
  <link rel="stylesheet" href="../assets/css/mis_audit_logs.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
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
          <div class="stat-label">Insert</div>
          <div class="stat-value"><?= $totalInsert ?></div>
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
          <div class="stat-label">Login</div>
          <div class="stat-value"><?= $totalLogin ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Logout</div>
          <div class="stat-value"><?= $totalLogout ?></div>
        </div>
      </section>

      <form class="toolbar" method="GET" action="audit_logs.php">
        <input type="text" name="q" placeholder="Search by name or details" value="<?= h($q) ?>">

        <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
        <input type="date" name="date_to" value="<?= h($dateTo) ?>">

        <select name="action">
          <option value="">All Actions</option>
          <option value="INSERT" <?= $actionFilter==="INSERT" ? "selected" : "" ?>>Insert</option>
          <option value="UPDATE" <?= $actionFilter==="UPDATE" ? "selected" : "" ?>>Update</option>
          <option value="DELETE" <?= $actionFilter==="DELETE" ? "selected" : "" ?>>Delete</option>
          <option value="LOGIN"  <?= $actionFilter==="LOGIN"  ? "selected" : "" ?>>Login</option>
          <option value="LOGOUT" <?= $actionFilter==="LOGOUT" ? "selected" : "" ?>>Logout</option>
        </select>

        <select name="role">
          <option value="">All Roles</option>
          <option value="MIS"       <?= $roleFilter==="MIS"       ? "selected" : "" ?>>MIS</option>
          <option value="REGISTRAR" <?= $roleFilter==="REGISTRAR" ? "selected" : "" ?>>Registrar</option>
          <option value="USER"      <?= $roleFilter==="USER"      ? "selected" : "" ?>>User</option>
        </select>

        <button class="filter-btn" type="submit">FILTER</button>
      </form>

      <section class="table-card">
        <table>
          <thead>
            <tr>
              <th>Performed By</th>
              <th>Role</th>
              <th>Action</th>
              <th>Table</th>
              <th>Details</th>
              <th>IP Address</th>
              <th>Date / Time</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$logs): ?>
              <tr>
                <td colspan="7" class="empty-row">No logs found.</td>
              </tr>
            <?php else: ?>
              <?php foreach($logs as $log): ?>
                <tr>
                  <td><?= h($log["performed_by"]) ?></td>
                  <td><?= h($log["user_role"]) ?></td>
                  <td>
                    <span class="action-pill <?= actionBadgeClass($log["action"]) ?>">
                      <?= h($log["action"]) ?>
                    </span>
                  </td>
                  <td><?= h($log["table_name"]) ?></td>
                  <td><?= h($log["details"] ?? "") ?></td>
                  <td><?= h($log["ip_address"] ?? "—") ?></td>
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
