<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "USER") {
  header("Location: ../auth/auth.php");
  exit();
}

$user_id = (int)$_SESSION["user_id"];
$ref = trim($_GET["ref"] ?? "");

// If no ref given, fallback to previous flow using session doc type
if ($ref !== "") {

  // Load request by ref (must belong to user)
  $stmt = $conn->prepare("
    SELECT id, reference_no, document_type, title_type, status, created_at, updated_at
    FROM requests
    WHERE reference_no = ? AND user_id = ?
    LIMIT 1
  ");
  $stmt->bind_param("si", $ref, $user_id);
  $stmt->execute();
  $req = $stmt->get_result()->fetch_assoc();

  if (!$req) die("Request not found or not yours.");

  $request_id = (int)$req["id"];
  $document_type = $req["document_type"];
  $title_type = $req["title_type"];

  // Save to session for save handler
  $_SESSION["upload_request_id"] = $request_id;
  $_SESSION["upload_ref"] = $ref;

} else {
  // OLD FLOW: uses session upload_doc_type
  $document_type = $_SESSION["upload_doc_type"] ?? "";
  if ($document_type === "") { header("Location: upload_requirements.php"); exit(); }

  // Find latest request for that doc type
  $stmt = $conn->prepare("
    SELECT id, reference_no, document_type, title_type, status, created_at, updated_at
    FROM requests
    WHERE user_id=? AND document_type=?
    ORDER BY id DESC
    LIMIT 1
  ");
  $stmt->bind_param("is", $user_id, $document_type);
  $stmt->execute();
  $req = $stmt->get_result()->fetch_assoc();

  if (!$req) die("No request found for this document type. Please create a request first.");

  $request_id = (int)$req["id"];
  $title_type = $req["title_type"];
  $ref = $req["reference_no"];

  $_SESSION["upload_request_id"] = $request_id;
  $_SESSION["upload_ref"] = $ref;
}

$docUpper = strtoupper(trim($document_type));
$titleUpper = strtoupper(trim($title_type));

// Requirements list for this doc + title type
$reqStmt = $conn->prepare("
  SELECT req_name
  FROM requirements_master
  WHERE UPPER(document_type)=? AND UPPER(title_type)=?
  ORDER BY id ASC
");
$reqStmt->bind_param("ss", $docUpper, $titleUpper);
$reqStmt->execute();
$requirements = $reqStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$requestedOn = $req["created_at"] ? date("F j, Y", strtotime($req["created_at"])) : "—";
$lastUpdated = $req["updated_at"] ? date("F j, Y", strtotime($req["updated_at"])) : "—";
$statusText = strtoupper($req["status"] ?? "PENDING");

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
<html>
<head>
  <meta charset="UTF-8">
  <title>Upload Requirements</title>
  <link rel="stylesheet" href="../assets/css/user_upload_requirements_upload.css">
</head>
<body>

<header class="topbar">
  <div class="brand">
      <!-- Optional small logo Waiting for design -->
      <!-- <img src="assets/img/edoc-logo.jpeg">  -->
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
    <h1>Upload Requirements</h1>
    <p>Upload clear, properly scanned files in PDF format.</p>
  </section>

  <section class="panel">
    <a class="exit-btn" href="dashboard.php">EXIT</a>

    <div class="h2">Read Carefully Before Uploading Requirement</div>
    <ul style="margin-top:8px; font-size:12px;">
      <li>Submit each requirement as a separate file.</li>
      <li>Upload all documents as clear, colored, and properly scanned copies.</li>
      <li>Ensure pages are arranged correctly before uploading.</li>
    </ul>

    <div class="status-block">
      <div class="h2" style="margin-top:14px;">Document Request Status</div>
      <div style="margin-top:8px;">
        <div><b>Reference Number:</b> <?= htmlspecialchars($ref) ?></div>
        <div><b>Document:</b> <?= htmlspecialchars($docUpper) ?></div>
        <div><b>Title Type:</b> <?= htmlspecialchars($title_type) ?></div>
        <div><b>Requested:</b> <?= htmlspecialchars($requestedOn) ?></div>
        <div><b>Last Updated:</b> <?= htmlspecialchars($lastUpdated) ?></div>
        <div><b>Application Status:</b> <span class="pill"><?= htmlspecialchars($statusText) ?></span></div>
      </div>
    </div>

    <form method="POST" action="upload_requirements_save.php" enctype="multipart/form-data" style="margin-top:14px;">
      <input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>">

      <?php if (count($requirements)===0): ?>
        <p>No requirements configured for <?= htmlspecialchars($docUpper) ?> (<?= htmlspecialchars($titleUpper) ?>).</p>
      <?php else: ?>
        <?php foreach($requirements as $r): ?>
          <label class="label"><?= htmlspecialchars(strtoupper($r["req_name"])) ?></label>
          <input type="file" name="files[]" accept="application/pdf" required>
          <input type="hidden" name="req_names[]" value="<?= htmlspecialchars($r["req_name"]) ?>">
          <div class="small">PDF files only (Max. size: 15MB)</div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="actions">
        <button class="btn pre" onclick="history.back()">PREVIOUS</button>
        <button class="btn save" type="submit">SAVE</button>
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
