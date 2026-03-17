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

/* ===== Dashboard counts ===== */
$totalUsers = 0;
$totalRequests = 0;
$todayRequests = 0;
$pendingRequests = 0;

/* total users */
$q1 = $conn->query("SELECT COUNT(*) AS c FROM users");
if ($q1) $totalUsers = (int)($q1->fetch_assoc()["c"] ?? 0);

/* total requests */
$q2 = $conn->query("SELECT COUNT(*) AS c FROM requests");
if ($q2) $totalRequests = (int)($q2->fetch_assoc()["c"] ?? 0);

/* today requests */
$q3 = $conn->query("SELECT COUNT(*) AS c FROM requests WHERE DATE(created_at)=CURDATE()");
if ($q3) $todayRequests = (int)($q3->fetch_assoc()["c"] ?? 0);

/* pending requests */
$q4 = $conn->query("SELECT COUNT(*) AS c FROM requests WHERE UPPER(status)='PENDING'");
if ($q4) $pendingRequests = (int)($q4->fetch_assoc()["c"] ?? 0);

/* ===== Recent activity =====
   Uses request_logs for now
*/
$activities = [];
$logQ = $conn->query("
  SELECT rl.message, rl.created_at, r.reference_no
  FROM request_logs rl
  LEFT JOIN requests r ON r.id = rl.request_id
  ORDER BY rl.created_at DESC, rl.id DESC
  LIMIT 8
");
if ($logQ) {
  while ($row = $logQ->fetch_assoc()) {
    $activities[] = $row;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MIS Dashboard</title>
  <link rel="stylesheet" href="../assets/css/mis_dashboard.css">
</head>
<body>

<div class="layout">

  <!-- SIDEBAR -->
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
      <a class="sb-item active" href="dashboard.php"><span class="sb-icon">🏠</span>Dashboard</a>
      <a class="sb-item" href="account_management.php"><span class="sb-icon">👥</span>Account Management</a>
      <a class="sb-item" href="reports.php"><span class="sb-icon">📊</span>Reports</a>
      <a class="sb-item" href="audit_logs.php"><span class="sb-icon">🛡️</span>Audit Logs</a>
    </nav>

    <div class="sb-section-title">SETTINGS</div>
    <nav class="sb-nav">
      <a class="sb-item" href="../auth/logout.php"><span class="sb-icon">⎋</span>Logout</a>
    </nav>
  </aside>

  <!-- MAIN -->
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
        <h1>MIS Admin Dashboard</h1>
        <p>Welcome back, <?= h($misName) ?>. System overview and management.</p>
      </div>

      <!-- STAT CARDS -->
      <section class="stats-grid">
        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?= (int)$totalUsers ?></div>
          </div>
          <div class="stat-icon blue">👥</div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">Total Requests</div>
            <div class="stat-value"><?= (int)$totalRequests ?></div>
          </div>
          <div class="stat-icon green">📄</div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">Today</div>
            <div class="stat-value"><?= (int)$todayRequests ?></div>
          </div>
          <div class="stat-icon orange">📈</div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?= (int)$pendingRequests ?></div>
          </div>
          <div class="stat-icon red">❗</div>
        </div>
      </section>

      <!-- QUICK ACTIONS -->
      <section class="quick-grid">
        <a href="account_management.php" class="quick-card-acc">
          <div class="quick-icon blue-soft">👥</div>
          <div>
            <div class="quick-title">Account Management</div>
            <div class="quick-sub">Manage users and roles</div>
          </div>
        </a>

        <a href="reports.php" class="quick-card-rep">
          <div class="quick-icon green-soft">📊</div>
          <div>
            <div class="quick-title">Reports</div>
            <div class="quick-sub">View analytics</div>
          </div>
        </a>

        <a href="audit_logs.php" class="quick-card-aud">
          <div class="quick-icon gold-soft">🛡️</div>
          <div>
            <div class="quick-title">Audit Logs</div>
            <div class="quick-sub">System activity</div>
          </div>
        </a>
      </section>

      <!-- RECENT ACTIVITY -->
      <section class="activity-card">
        <div class="activity-head">
          <div class="activity-title">Recent System Activity</div>
          <a href="audit_logs.php" class="view-all">View All →</a>
        </div>

        <?php if (count($activities) === 0): ?>
          <div class="empty-state">No recent system activity found.</div>
        <?php else: ?>
          <div class="activity-list">
            <?php foreach ($activities as $a): ?>
              <div class="activity-item">
                <div class="activity-left">
                  <div class="activity-symbol">~</div>
                  <div class="activity-texts">
                    <div class="activity-main"><?= h($a["message"] ?? "System activity") ?></div>
                    <div class="activity-sub">
                      <?php if (!empty($a["reference_no"])): ?>
                        Ref: <?= h($a["reference_no"]) ?>
                      <?php else: ?>
                        E-Doc System
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <div class="activity-time">
                  <?= h(date("M j, g:i A", strtotime($a["created_at"] ?? "now"))) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
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
</script>

</body>
</html>