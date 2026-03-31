<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);

$user_id = (int)$_SESSION["user_id"];

// Logic for handling the EXIT button clearing the session
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    unset($_SESSION["req"]);
    header("Location: dashboard.php");
    exit();
}

// Block pending accounts that already have a request
$user_id = (int)$_SESSION["user_id"];
if (($_SESSION["verification_status"] ?? "") === "PENDING") {
  $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM requests WHERE user_id = ?");
  $countStmt->bind_param("i", $user_id);
  $countStmt->execute();
  $reqCount = (int)$countStmt->get_result()->fetch_assoc()["total"];
  if ($reqCount >= 1) {
    header("Location: dashboard.php?limit=1");
    exit();
  }
}

// Check if there is existing data in the session to pre-fill the form
$saved_req = $_SESSION["req"] ?? null;

// Document types from DB
$docs = [];
$res = $conn->query("SELECT DISTINCT document_type FROM requirements_master ORDER BY document_type ASC");
if ($res) {
  while ($row = $res->fetch_assoc()) $docs[] = $row["document_type"];
}

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
  <title>Request Document</title>
  <link rel="stylesheet" href="../assets/css/user_request.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
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
    <h1>Request Document</h1>
    <p>Start your application by completing all required fields and reviewing your personal information for accuracy.</p>
  </section>

  <div class="step-indicator">
    <div class="step active">
      <span class="num">1</span>
      <span>Select Document</span>
    </div>
    <div class="divider"></div>
    <div class="step">
      <span class="num">2</span>
      <span>Review & Submit</span>
    </div>
  </div>

  <section class="panel">
    <!-- Changed link to pass a 'clear' parameter to trigger the session unset logic above -->
      <a class="exit-btn" href="request.php?clear=1"><i class="bi bi-x-lg"></i> EXIT</a>

    <div class="h2">Application Details</div>
    <p class="sub">Kindly complete all required fields to ensure accurate processing of your request</p>

    <form method="POST" action="request_review.php" id="requestForm">
      <?= csrf_field() ?>

      <label class="label">Select Document Type: *</label>
      <select name="document_type" id="documentType" required>
        <option value="">--- Select Document Request: e.g. Transcript, Diploma, Certificate ---</option>
        <?php foreach ($docs as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>"<?= ($saved_req && $saved_req['document_type'] == $d) ? 'selected' : '' ?>>
                <?= htmlspecialchars($d) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <label class="label">Select Title Type: *</label>
      <select name="title_type" id="titleType" required>
        <option value="">--- Select Title Type ---</option>
        <?php if ($saved_req && !empty($saved_req['title_type'])): ?>
                    <option value="<?= htmlspecialchars($saved_req['title_type']) ?>" selected>
                        <?= htmlspecialchars($saved_req['title_type']) ?>
                    </option>
        <?php endif; ?>
      </select>

      <label class="label">Purpose/s of request: *</label>
      <input type="text" name="purpose" placeholder="e.g. employment, transfer, board exam..." required 
            value="<?= htmlspecialchars($saved_req['purpose'] ?? '') ?>">

      <label class="label">Number of Copies: *</label>
      <input type="number" name="copies" min="1" max="5" required
            value="<?= htmlspecialchars($saved_req['copies'] ?? '1') ?>">

      <div class="actions">
        <button class="btn next" type="submit">NEXT <i class="bi bi-arrow-right"></i></button>
      </div>
    </form>
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
    markSeen(); // ✅ reset unread count when opened
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

<script>
const docSel = document.getElementById("documentType");
const titleSel = document.getElementById("titleType");

function resetTitle() {
  titleSel.innerHTML = "<option value=''>--- Select Title Type ---</option>";
}

docSel.addEventListener("change", () => {
  const doc = docSel.value.trim();
  resetTitle();
  if (!doc) return;

  fetch("../api/get_title_types.php?doc=" + encodeURIComponent(doc))
    .then(res => res.json())
    .then(rows => {
      rows.forEach(row => {
        const opt = document.createElement("option");
        opt.value = row.title_type;
        opt.textContent = row.title_type;
        // If we returned and this was the previous selection, re-select it
                <?php if ($saved_req): ?>
                if (row.title_type === "<?= addslashes($saved_req['title_type']) ?>") {
                    opt.selected = true;
                }
                <?php endif; ?>
        titleSel.appendChild(opt);
      });
    })
    .catch(() => resetTitle());
});

// Trigger change event on load if a document type is already selected (for "Previous" button scenario)
window.addEventListener('DOMContentLoaded', () => {
    if (docSel.value) {
        docSel.dispatchEvent(new Event('change'));
    }
});
</script>

</body>
</html>
