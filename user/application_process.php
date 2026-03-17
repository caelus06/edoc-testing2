<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);

// pull ALL doc types from requirements_master (not from requests)
$docs = [];
$res = $conn->query("SELECT DISTINCT document_type FROM requirements_master ORDER BY document_type ASC");
if ($res) {
  while ($row = $res->fetch_assoc()) $docs[] = $row["document_type"];
}
$user_id = (int)$_SESSION["user_id"];

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Application Process</title>
  <link rel="stylesheet" href="../assets/css/user_application_process.css">
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
  <section class="banner">
    <h1>Application Process</h1>
    <p>View Requirement and Process</p>
  </section>

  <section class="panel">
    <a class="exit-btn" href="dashboard.php">EXIT</a>

    <div class="note">
      <span class="pin"></span><b>Please note: Read carefully the requirement</b><br>
      Digital uploads are required for verification, but all official requirements must be submitted once verified and approved to the Registrar’s Office
    </div>

    <form method="GET" action="application_process_view.php">
      <label class="label">Select Document Request: *</label>

      <select name="document_type" required>
        <option value="">--- Select Document Request: e.g. Transcript, Diploma, Certificate---</option>
        <?php foreach ($docs as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="actions">
        <button class="btn next" type="submit">NEXT &gt;&gt;&gt;</button>
      </div>
    </form>
  </section>
</main>

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
      await fetch("notif_seen.php", { method: "POST", headers: {"Content-Type": "application/x-www-form-urlencoded"}, body: "_csrf_token=<?= urlencode(csrf_token()) ?>" });
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
</html