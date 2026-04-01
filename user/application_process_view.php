<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);

$document_type = trim($_GET["document_type"] ?? "");
if ($document_type === "") {
  header("Location: application_process.php");
  exit();
}

// working days + process html
$pStmt = $conn->prepare("SELECT working_days, process_html FROM document_process WHERE document_type = ? LIMIT 1");
$pStmt->bind_param("s", $document_type);
$pStmt->execute();
$proc = $pStmt->get_result()->fetch_assoc();

$workingDays = $proc["working_days"] ?? "—";
$rawProcess  = $proc["process_html"] ?? "";

// ── FIX 1: Decode process_html JSON → extract application_process entries ──
$appProcessItems = [];
$reminders       = [];

$decoded = json_decode($rawProcess, true);
if (is_array($decoded)) {
  // New JSON format (saved by edit_document.php)
  $appProcessItems = $decoded["application_process"] ?? [];
  $reminders       = $decoded["reminders"]           ?? [];
} else {
  // Legacy raw HTML fallback
  $appProcessItems = [["id" => "", "details" => strip_tags($rawProcess)]];
}

// title types
$tStmt = $conn->prepare(
  "SELECT DISTINCT title_type FROM requirements_master
   WHERE document_type = ?
   ORDER BY title_type ASC"
);
$tStmt->bind_param("s", $document_type);
$tStmt->execute();
$titleTypes = $tStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── FIX 2: Exclude __placeholder__ rows from requirements query ──
$reqStmt = $conn->prepare(
  "SELECT req_name FROM requirements_master
   WHERE document_type = ? AND title_type = ?
     AND req_name != '__placeholder__'
   ORDER BY id ASC"
);

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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Application Process</title>
  <link rel="stylesheet" href="../assets/css/user_application_process_view.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div>E-Doc Document Requesting System</div>
  </div>
  <div class="top-icons">
    <span class="notif-wrap">
      <button class="icon-btn" id="notifBtn" title="Notifications" type="button"><i class="bi bi-bell"></i></button>
      <?php if ($badgeCount > 0): ?>
        <span class="notif-badge" id="notifBadge"><?= (int)$badgeCount ?></span>
      <?php endif; ?>
    </span>

    <div class="icon-btn" title="Account"><a href="profile.php"><i class="bi bi-person-circle"></i></a></div>
    <button class="icon-btn" title="Logout" onclick="swalConfirm('Logout', 'Are you sure you want to log out?', 'Yes, log out', function(){ window.location='../auth/logout.php'; })"><i class="bi bi-box-arrow-right"></i></button>
  </div>
</header>

<main class="container">
  <section class="banner">
    <h1>Application Process</h1>
    <p>View Requirement and Process</p>
  </section>

  <section class="panel">
    <div class="panel-header">
      <div class="doc-head">
        <div>
          <h2 class="doc-title"><?= htmlspecialchars(strtoupper($document_type)) ?></h2>
          <?php if ($workingDays): ?>
            <span class="workdays-pill"><i class="bi bi-clock"></i> <?= htmlspecialchars($workingDays) ?></span>
          <?php endif; ?>
        </div>
        <a class="exit-btn" href="dashboard.php"><i class="bi bi-x-lg"></i> EXIT</a>
      </div>
    </div>

    <div class="note">
      <i class="bi bi-info-circle"></i>
      <div>
        <b>Please note: Read carefully the requirement</b>
        Digital uploads are required for verification, but all official requirements must be submitted once verified and approved to the Registrar's Office.
      </div>
    </div>

    <!-- Documentary Requirements -->
    <div class="content-section">
      <h3 class="section-title"><i class="bi bi-file-earmark-check"></i> Documentary Requirements</h3>
      <p class="section-sub">Review the full list of requirements before uploading your documents.</p>

      <?php if (count($titleTypes) === 0): ?>
        <p class="empty-msg">No requirements configured.</p>
      <?php else: ?>
        <?php foreach ($titleTypes as $tt): ?>
          <?php
            $title = $tt["title_type"];
            $reqStmt->bind_param("ss", $document_type, $title);
            $reqStmt->execute();
            $reqs = $reqStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            if (empty($reqs)) continue;
            $counter = 1;
          ?>
          <div class="req-card">
            <div class="req-card-header"><?= htmlspecialchars($title) ?></div>
            <div class="req-card-body">
              <?php foreach ($reqs as $r): ?>
                <div class="req-item">
                  <span class="req-num"><?= $counter++ ?></span>
                  <span><?= htmlspecialchars($r["req_name"]) ?></span>
                </div>
              <?php endforeach; ?>

              <?php if (!empty($reminders)): ?>
                <?php foreach ($reminders as $rem): ?>
                  <?php if (trim($rem["title_type"]) === trim($title)): ?>
                    <div class="reminder-block">
                      <i class="bi bi-exclamation-triangle"></i>
                      <em><?= htmlspecialchars($rem["details"]) ?></em>
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Application Process -->
    <?php if (!empty($appProcessItems)): ?>
      <div class="content-section">
        <h3 class="section-title"><i class="bi bi-list-ol"></i> Application Process</h3>
        <div class="process-list">
          <?php foreach ($appProcessItems as $i => $ap): ?>
            <div class="process-step">
              <span class="step-num"><?= $i + 1 ?></span>
              <span class="step-text"><?= htmlspecialchars($ap["details"]) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="actions">
      <a class="btn prev" href="application_process.php"><i class="bi bi-arrow-left"></i> RETURN</a>
    </div>
  </section>
</main>


<!-- NOTIFICATION MODAL -->
<div class="modal-backdrop" id="notifBackdrop">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="notifTitle">
    <button class="close-x" id="notifClose" type="button">×</button>
    <h3 id="notifTitle">NOTIFICATION</h3>
    <div class="notif-list">
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
    document.body.style.overflow = "hidden";
    markSeen(); // reset unread count when opened
  }

  function closeNotif(){
    backdrop.style.display = "none";
    document.body.style.overflow = "";
  }

  notifBtn?.addEventListener("click", openNotif);
  closeBtn?.addEventListener("click", closeNotif);

  backdrop?.addEventListener("click", (e) => {
    if (e.target === backdrop) closeNotif();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeNotif();
  });
</script>


</body>
</html>
