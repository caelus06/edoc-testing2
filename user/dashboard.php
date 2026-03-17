<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "USER") {
  header("Location: ../auth/auth.php");
  exit();
}

$user_id = (int)$_SESSION["user_id"];

$q = trim($_GET["q"] ?? "");
$statusFilter = trim($_GET["status"] ?? "ALL");

// Pagination
$page = (int)($_GET["page"] ?? 1);
if ($page < 1) $page = 1;

$perPage = 10;
$offset = ($page - 1) * $perPage;

$allowedStatuses = [
  "ALL",
  "PENDING",
  "RETURNED",
  "VERIFIED",
  "APPROVED",
  "PROCESSING",
  "READY FOR PICKUP",
  "RELEASED",
  "CANCELLED",
  "COMPLETED"
];

if (!in_array(strtoupper($statusFilter), $allowedStatuses, true)) {
  $statusFilter = "ALL";
}

/* ---------- COUNT (for pagination) ---------- */
$countSql = "SELECT COUNT(*) AS total FROM requests WHERE user_id = ?";
$countParams = [$user_id];
$countTypes = "i";

if ($q !== "") {
  $countSql .= " AND (reference_no LIKE ? OR document_type LIKE ?)";
  $like = "%" . $q . "%";
  $countParams[] = $like;
  $countParams[] = $like;
  $countTypes .= "ss";
}

if ($statusFilter !== "ALL") {
  $countSql .= " AND UPPER(status) = ?";
  $countParams[] = strtoupper($statusFilter);
  $countTypes .= "s";
}

$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()["total"] ?? 0);

$totalPages = (int)ceil($total / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;

/* ---------- DATA ---------- */
$sql = "
  SELECT id, reference_no, document_type, status, updated_at, created_at
  FROM requests
  WHERE user_id = ?
";
$params = [$user_id];
$types = "i";

if ($q !== "") {
  $sql .= " AND (reference_no LIKE ? OR document_type LIKE ?)";
  $like = "%" . $q . "%";
  $params[] = $like;
  $params[] = $like;
  $types .= "ss";
}

if ($statusFilter !== "ALL") {
  $sql .= " AND UPPER(status) = ?";
  $params[] = strtoupper($statusFilter);
  $types .= "s";
}

$sql .= " ORDER BY updated_at DESC, id DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ---------- Notifications list (modal content) ---------- */
$notifStmt = $conn->prepare("
  SELECT rl.message, rl.created_at
  FROM request_logs rl
  INNER JOIN requests r ON r.id = rl.request_id
  WHERE r.user_id = ?
  ORDER BY rl.created_at DESC
  LIMIT 30
");
$notifStmt->bind_param("i", $user_id);
$notifStmt->execute();
$notifs = $notifStmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ---------- Get last seen timestamp ---------- */
$seenStmt = $conn->prepare("SELECT last_seen_at FROM user_notif_seen WHERE user_id = ? LIMIT 1");
$seenStmt->bind_param("i", $user_id);
$seenStmt->execute();
$seenRow = $seenStmt->get_result()->fetch_assoc();
$lastSeenAt = $seenRow["last_seen_at"] ?? "2000-01-01 00:00:00";

/* ---------- Unread badge count = logs newer than last_seen_at ---------- */
$badgeStmt = $conn->prepare("
  SELECT COUNT(*) AS c
  FROM request_logs rl
  INNER JOIN requests r ON r.id = rl.request_id
  WHERE r.user_id = ?
    AND rl.created_at > ?
");
$badgeStmt->bind_param("is", $user_id, $lastSeenAt);
$badgeStmt->execute();
$badgeCount = (int)($badgeStmt->get_result()->fetch_assoc()["c"] ?? 0);
if ($badgeCount > 99) $badgeCount = 99;

/* ---------- status class mapping to your CSS ---------- */
function status_css($statusRaw){
  $s = strtoupper(trim($statusRaw ?? "PENDING"));
  return match ($s) {
    "PENDING" => "status-pending",
    "RETURNED" => "status-returned",
    "VERIFIED" => "status-approved",
    "APPROVED" => "status-approved",
    "PROCESSING" => "status-processing",
    "READY FOR PICKUP" => "status-completed",
    "RELEASED" => "status-completed",
    "CANCELLED" => "status-returned",
    "COMPLETED" => "status-completed",
    default => "status-pending"
  };
}

/* ---------- pagination URL helper ---------- */
function page_url($pageNum, $q, $status){
  $params = [];
  if ($q !== "") $params["q"] = $q;
  if ($status !== "" && strtoupper($status) !== "ALL") $params["status"] = $status;
  $params["page"] = $pageNum;
  return "dashboard.php?" . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>User Dashboard</title>
  <link rel="stylesheet" href="../assets/css/user_dashboard.css">
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div class="logo">
      <!-- Optional small logo Waiting for design -->
      <!-- <img src="assets/img/edoc-logo.jpeg">  -->
    </div>
    <div>E-Doc Document Requesting System</div>
  </div>
  <div class="top-icons">
    <span class="notif-wrap">
      <button class="icon-btn" id="notifBtn" title="Notifications" type="button">🔔</button>
      <?php if ($badgeCount > 0): ?>
        <span class="notif-badge" id="notifBadge"><?= (int)$badgeCount ?></span>
      <?php endif; ?>
    </span>

    <div class="icon-btn" title="Account"><a href="profile.php">👤</a></div>
    <button class="icon-btn" title="Logout" id="logoutBtn";">⎋</button>
  </div>
</header>

<main class="container">

  <?php if (($_SESSION["verification_status"] ?? "") === "PENDING"): ?>
    <section class="panel welcome" style="background:#fff3cd;border:1px solid #ffc107;border-radius:12px;padding:14px 18px;margin-bottom:0;">
      <h2 style="color:#856404;margin:0 0 4px;">Account Pending Verification</h2>
      <p style="color:#856404;margin:0;">Your account is still pending MIS verification. You are limited to <b>1 document request</b> until your account is verified.</p>
    </section>
  <?php endif; ?>

  <?php if (isset($_GET["limit"])): ?>
    <section class="panel welcome" style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:12px;padding:14px 18px;margin-bottom:0;">
      <p style="color:#721c24;margin:0;"><b>Request limit reached.</b> Pending accounts can only submit 1 document request. Please wait for your account to be verified by MIS.</p>
    </section>
  <?php endif; ?>

  <section class="panel welcome">
    <h2>Welcome!</h2>
    <p>Track your document requests and submit new applications easily.</p>
  </section>

  <section class="cards">
    <a class="card" href="request.php">
      <div class="icon">📄</div>
      <div class="title">Request Document</div>
      <div class="desc">Transcript, Diploma, Certifications</div>
    </a>
    <a class="card" href="upload_requirements.php">
      <div class="icon">⬆️</div>
      <div class="title">Upload Requirements</div>
      <div class="desc">Valid ID, forms, clearance</div>
    </a>
    <a class="card" href="application_process.php">
      <div class="icon">📌</div>
      <div class="title">Application Process</div>
      <div class="desc">View requirement and process</div>
    </a>
  </section>

  <!-- Search + Filter -->
  <form class="toolbar" method="GET" action="dashboard.php">
    <div class="searchbar">
      🔎
      <input
        type="text"
        name="q"
        placeholder="Search by reference number or document type..."
        value="<?= htmlspecialchars($q) ?>"
      />
      <input type="hidden" name="page" value="1">
    </div>

    <div class="filterbox">
      <select name="status" onchange="this.form.page.value=1; this.form.submit()">
        <?php foreach ($allowedStatuses as $st): ?>
          <?php $label = ($st === "ALL") ? "All Status" : ucwords(strtolower($st)); ?>
          <option value="<?= htmlspecialchars($st) ?>" <?= (strtoupper($statusFilter) === $st ? "selected" : "") ?>>
            <?= htmlspecialchars($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="page" value="<?= (int)$page ?>">
    </div>
  </form>

  <div class="section-title">Recent Requests</div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>DOCUMENT TYPE</th>
          <th>REFERENCE NUMBER</th>
          <th>Last Updated</th>
          <th>APPLICATION STATUS</th>
          <th>VIEW PROGRESS</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5">No requests found.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $date = $r["updated_at"]
                ? date("m/d/y", strtotime($r["updated_at"]))
                : date("m/d/y", strtotime($r["created_at"]));
              $st = strtoupper($r["status"] ?? "PENDING");
            ?>
            <tr>
              <td><?= htmlspecialchars(strtoupper($r["document_type"])) ?></td>
              <td><?= htmlspecialchars($r["reference_no"]) ?></td>
              <td><?= htmlspecialchars($date) ?></td>
              <td>
                <span class="status-pill <?= status_css($st) ?>">
                  <?= htmlspecialchars(ucwords(strtolower($st))) ?>
                </span>
              </td>
              <td>
                <a class="track-btn" href="track.php?ref=<?= urlencode($r["reference_no"]) ?>">
                  Track Progress &gt;
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($total > 0): ?>
    <div class="pager">
      <a class="pg <?= ($page <= 1 ? "disabled" : "") ?>" href="<?= htmlspecialchars(page_url(1, $q, $statusFilter)) ?>">&laquo;</a>
      <a class="pg <?= ($page <= 1 ? "disabled" : "") ?>" href="<?= htmlspecialchars(page_url($page - 1, $q, $statusFilter)) ?>">&lsaquo;</a>

      <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($p = $start; $p <= $end; $p++):
      ?>
        <a class="pg <?= ($p === $page ? "active" : "") ?>" href="<?= htmlspecialchars(page_url($p, $q, $statusFilter)) ?>"><?= $p ?></a>
      <?php endfor; ?>

      <a class="pg <?= ($page >= $totalPages ? "disabled" : "") ?>" href="<?= htmlspecialchars(page_url($page + 1, $q, $statusFilter)) ?>">&rsaquo;</a>
      <a class="pg <?= ($page >= $totalPages ? "disabled" : "") ?>" href="<?= htmlspecialchars(page_url($totalPages, $q, $statusFilter)) ?>">&raquo;</a>

      <span class="pginfo">Page <?= $page ?> of <?= $totalPages ?> • Total: <?= $total ?></span>
    </div>
  <?php endif; ?>

</main>
<div class="footer-bar"></div></div>

<!-- NOTIFICATION MODAL -->
<div class="modal-backdrop" id="notifBackdrop">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="notifTitle">
    <button class="close-x" id="notifClose" type="button">×</button>
    <h3 id="notifTitle">NOTIFICATION</h3>

    <?php if (empty($notifs)): ?>
      <div class="notif-item">
        <div class="notif-title">No notifications yet</div>
        <div class="notif-time">—</div>
      </div>
    <?php else: ?>
      <?php foreach ($notifs as $n): ?>
        <div class="notif-item">
          <div class="notif-title"><?= htmlspecialchars($n["message"]) ?></div>
          <div class="notif-time">
            <?= $n["created_at"] ? date("m/d/y, g:i A", strtotime($n["created_at"])) : "" ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</div>

<!-- LOGOUT CONFIRMATION MODAL -->
<div class="modal-backdrop" id="logoutBackdrop">
  <div class="modal" role="dialog" aria-modal="true" style="max-width: 400px;">
    <div class="logout-content">
      <h3>Are you sure you want to log out?</h3>
      <p>You will need to sign in again to access your account.</p>
    <div class="logout-actions">
      <button class="btn-cancel" id="logoutCancel">Cancel</button>
      <a href="../auth/logout.php" class="btn-confirm">Log Out</a>
    </div>
    </div>
  </div>
</div>

<script>
  const notifBtn = document.getElementById("notifBtn");
  const backdrop = document.getElementById("notifBackdrop");
  const closeBtn = document.getElementById("notifClose");
  const badge = document.getElementById("notifBadge");

  async function markSeen(){
    try{
      await fetch("notif_seen.php", { method: "POST" });
      if (badge) badge.style.display = "none";
    }catch(e){}
  }

  function openNotif(){
    backdrop.style.display = "flex";
    markSeen(); // ✅ reset unread count when opened
  }

  function closeNotif(){
    backdrop.style.display = "none";
  }

  notifBtn?.addEventListener("click", openNotif);
  closeBtn?.addEventListener("click", closeNotif);

  backdrop?.addEventListener("click", (e) => {
    if (e.target === backdrop) closeNotif();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeNotif();
  });

  // Logout Logic
  const logoutBtn = document.getElementById("logoutBtn");
  const logoutBackdrop = document.getElementById("logoutBackdrop");
  const logoutCancel = document.getElementById("logoutCancel");

  logoutBtn?.addEventListener("click", () => logoutBackdrop.style.display = "flex");
  logoutCancel?.addEventListener("click", () => logoutBackdrop.style.display = "none");

  // General Modal Logic
  window.addEventListener("click", (e) => {
    if (e.target === notifBackdrop) notifBackdrop.style.display = "none";
    if (e.target === logoutBackdrop) logoutBackdrop.style.display = "none";
  });

document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      notifBackdrop.style.display = "none";
      logoutBackdrop.style.display = "none";
    }
  });
</script>

</body>
</html>