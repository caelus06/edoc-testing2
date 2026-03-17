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

$user_id = (int)$_SESSION["user_id"];

// Save step-1 inputs in session
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_verify();
  $_SESSION["req"] = [
    "document_type" => trim($_POST["document_type"] ?? ""),
    "title_type"    => trim($_POST["title_type"] ?? ""),
    "purpose"       => trim($_POST["purpose"] ?? ""),
    "copies"        => (int)($_POST["copies"] ?? 1),
  ];
}

$req = $_SESSION["req"] ?? null;
if (
  !$req ||
  $req["document_type"] === "" ||
  $req["title_type"] === "" ||
  $req["purpose"] === "" ||
  (int)$req["copies"] < 1
) {
  header("Location: request.php");
  exit();
}

// Fetch user info
$stmt = $conn->prepare("
  SELECT first_name, middle_name, last_name, suffix, student_id, course, major, year_graduated, gender, email, contact_number, address
  FROM users
  WHERE id = ?
  LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();

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
  <title>Request Document - Review</title>
  <link rel="stylesheet" href="../assets/css/user_request_review.css">
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div class="logo">
      <!-- Optional small logo Waiting for design -->
      <!-- <img src="assets/img/edoc-logo.jpeg" alt="E-Doc Logo"> -->
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
    <button class="icon-btn" title="Logout" id="logoutBtn" style="background:none; border:none; cursor:pointer;">⎋</button>
  </div>
</header>

<main class="container">
  <section class="banner">
    <h1>Request Document</h1>
    <p>Start your application by completing all required fields and reviewing your personal information for accuracy.</p>
  </section>

  <section class="panel">
        <!-- Changed link to pass a 'clear' parameter to trigger the session unset logic above -->
      <a class="exit-btn" href="request.php?clear=1">EXIT</a>

    <h2>Review Your Personal Information</h2>
    <p class="sub">Ensure that the details you provide are accurate and consistent with your official academic records.</p>

    <div class="info">
      <p><b>First Name:</b> <?= htmlspecialchars($u["first_name"] ?: "N/A") ?></p>
      <p><b>Middle Name:</b> <?= htmlspecialchars($u["middle_name"] ?: "N/A") ?></p>
      <p><b>Last Name:</b> <?= htmlspecialchars($u["last_name"] ?: "N/A") ?></p>
      <p><b>Suffix:</b> <?= htmlspecialchars($u["suffix"] ?: "N/A") ?></p>
      <p><b>ID Number:</b> <?= htmlspecialchars($u["student_id"] ?: "N/A") ?></p>
      <p><b>Course/Program:</b> <?= htmlspecialchars($u["course"] ?: "N/A") ?></p>
      <p><b>Major:</b> <?= htmlspecialchars($u["major"] ?: "N/A") ?></p>
      <p><b>Year Graduated:</b> <?= htmlspecialchars($u["year_graduated"] ?: "N/A") ?></p>
      <p><b>Gender:</b> <?= htmlspecialchars($u["gender"] ?: "N/A") ?></p>
      <p><b>Email:</b> <?= htmlspecialchars($u["email"] ?: "N/A") ?></p>
      <p><b>Contact Number:</b> <?= htmlspecialchars($u["contact_number"] ?: "N/A") ?></p>
      <p><b>Complete Address:</b> <?= htmlspecialchars($u["address"] ?: "N/A") ?></p>
    </div>

    <hr style="border:none;border-top:1px solid #eef1f6;margin:18px 0;">

    <div class="info">
      <p><b>Document Type:</b> <?= htmlspecialchars($req["document_type"]) ?></p>
      <p><b>Title Type:</b> <?= htmlspecialchars($req["title_type"]) ?></p>
      <p><b>Purpose:</b> <?= htmlspecialchars($req["purpose"]) ?></p>
      <p><b>Copies:</b> <?= (int)$req["copies"] ?></p>
    </div>

    <div class="actions">
      <a class="btn prev" href="request.php" style="text-decoration:none;display:inline-block;">&lt;&lt;&lt; PREVIOUS</a>

      <form method="POST" action="request_submit.php" style="margin:0;">
        <?= csrf_field() ?>
        <button class="btn save" type="submit">SAVE</button>
      </form>
    </div>
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
</html>