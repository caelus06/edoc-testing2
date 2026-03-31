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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
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
    <div class="step">
      <span class="num">1</span>
      <span>Select Document</span>
    </div>
    <div class="divider"></div>
    <div class="step active">
      <span class="num">2</span>
      <span>Review & Submit</span>
    </div>
  </div>

  <section class="panel">
    <a class="exit-btn" href="request.php?clear=1"><i class="bi bi-x-lg"></i> EXIT</a>

    <div class="info-section">
      <button type="button" class="btn-edit-info" id="editInfoBtn">
        <i class="bi bi-pencil-square"></i> Edit
      </button>
      <h2>Review Your Personal Information</h2>
      <p class="sub">Ensure that the details you provide are accurate and consistent with your official academic records.</p>

      <div class="edit-warning" id="editWarning">
        <i class="bi bi-exclamation-triangle"></i>
        Updating information here will also change your main profile information. This ensures you don't have to re-enter details every time you make a request.
      </div>

      <div class="info-grid">
        <?php
        $infoFields = [
            "first_name"     => ["First Name",     $u["first_name"]],
            "middle_name"    => ["Middle Name",     $u["middle_name"]],
            "last_name"      => ["Last Name",       $u["last_name"]],
            "suffix"         => ["Suffix",          $u["suffix"]],
            "student_id"     => ["ID Number",       $u["student_id"]],
            "course"         => ["Course/Program",  $u["course"]],
            "major"          => ["Major",           $u["major"]],
            "year_graduated" => ["Year Graduated",  $u["year_graduated"]],
            "gender"         => ["Gender",          $u["gender"]],
            "contact_number" => ["Contact Number",  $u["contact_number"]],
            "address"        => ["Complete Address", $u["address"]],
        ];
        foreach ($infoFields as $field => $meta): ?>
          <div class="info-row" data-field="<?= $field ?>">
            <span class="label"><?= $meta[0] ?></span>
            <span class="value-text"><?= h($meta[1] ?? '') ?: '—' ?></span>
            <?php if ($field === "gender"): ?>
              <select name="<?= $field ?>">
                <option value="">—</option>
                <option value="Male" <?= ($meta[1] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= ($meta[1] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
              </select>
            <?php else: ?>
              <input type="text" name="<?= $field ?>" value="<?= h($meta[1] ?? '') ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <div class="info-row">
          <span class="label">Email</span>
          <span class="value-text"><?= h($u["email"] ?? '') ?: '—' ?></span>
        </div>
      </div>

      <div class="edit-actions" id="editActions">
        <button type="button" class="btn-save-info" id="saveInfoBtn"><i class="bi bi-check-lg"></i> Save Changes</button>
        <button type="button" class="btn-cancel-edit" id="cancelEditBtn">Cancel</button>
      </div>
    </div>

    <hr class="section-divider">

    <div class="info-grid request-details">
      <div class="info-row">
        <span class="label">Document Type</span>
        <span class="value-text"><?= h($req["document_type"]) ?></span>
      </div>
      <div class="info-row">
        <span class="label">Title Type</span>
        <span class="value-text"><?= h($req["title_type"]) ?></span>
      </div>
      <div class="info-row">
        <span class="label">Purpose</span>
        <span class="value-text"><?= h($req["purpose"]) ?></span>
      </div>
      <div class="info-row">
        <span class="label">Copies</span>
        <span class="value-text"><?= (int)$req["copies"] ?></span>
      </div>
    </div>

    <div class="actions">
      <a class="btn prev" href="request.php"><i class="bi bi-arrow-left"></i> Previous</a>

      <form method="POST" action="request_submit.php" style="margin:0;">
        <?= csrf_field() ?>
        <button class="btn save" type="submit"><i class="bi bi-send"></i> Submit Request</button>
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

</script>

<script>
  const editBtn = document.getElementById("editInfoBtn");
  const saveBtn = document.getElementById("saveInfoBtn");
  const cancelBtn = document.getElementById("cancelEditBtn");
  const warning = document.getElementById("editWarning");
  const actions = document.getElementById("editActions");
  const infoSection = document.querySelector(".info-section");
  const rows = document.querySelectorAll(".info-row[data-field]");

  let originals = {};

  editBtn?.addEventListener("click", () => {
    originals = {};
    rows.forEach(row => {
      const field = row.dataset.field;
      const input = row.querySelector("input, select");
      if (input) originals[field] = input.value;
      row.classList.add("editing");
    });
    warning.style.display = "flex";
    actions.style.display = "flex";
    editBtn.style.display = "none";
  });

  cancelBtn?.addEventListener("click", () => {
    rows.forEach(row => {
      const field = row.dataset.field;
      const input = row.querySelector("input, select");
      if (input && originals[field] !== undefined) input.value = originals[field];
      row.classList.remove("editing");
    });
    warning.style.display = "none";
    actions.style.display = "none";
    editBtn.style.display = "";
  });

  saveBtn?.addEventListener("click", async () => {
    const body = new URLSearchParams();
    body.append("_csrf_token", "<?= urlencode(csrf_token()) ?>");
    body.append("ajax", "1");
    body.append("exclude_email", "1");
    rows.forEach(row => {
      const field = row.dataset.field;
      const input = row.querySelector("input, select");
      if (input) body.append(field, input.value);
    });

    try {
      const res = await fetch("profile_update.php", { method: "POST", body });
      const data = await res.json();
      if (data.success) {
        rows.forEach(row => {
          const input = row.querySelector("input, select");
          const text = row.querySelector(".value-text");
          if (input && text) text.textContent = input.value || "—";
          row.classList.remove("editing");
        });
        warning.style.display = "none";
        actions.style.display = "none";
        editBtn.style.display = "";
        Swal.fire({ icon: "success", title: "Saved", text: data.message, timer: 1500, showConfirmButton: false });
      } else {
        Swal.fire({ icon: "error", title: "Error", text: data.message || "Update failed." });
      }
    } catch (e) {
      Swal.fire({ icon: "error", title: "Error", text: "Network error. Please try again." });
    }
  });
</script>

</body>
</html>