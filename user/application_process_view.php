<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "USER") {
  header("Location: ../auth/auth.php");
  exit();
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Application Process</title>
  <link rel="stylesheet" href="../assets/css/upload_requirements.css">
  <style>
    .return-btn{ margin-top:14px; display:inline-block; padding:10px 18px;
      background:#0b3a5a; color:#fff; border-radius:8px; text-decoration:none; font-weight:900; font-size:12px;}
    .doc-head{ display:flex; justify-content:space-between; align-items:flex-start; margin-top:10px;}
    .doc-title{ font-weight:900; font-size:18px; }
    .workdays{ font-size:11px; margin-top:6px; }
    .section h3{ font-size:13px; font-weight:900; margin:12px 0 6px; }
    .process-item { margin-bottom: 12px; white-space: pre-line; font-size: 13px; line-height: 1.6; }
    .reminder-block { margin-bottom: 10px; }
    .reminder-title { font-weight: 700; font-size: 12px; margin-bottom: 3px; }
    .reminder-detail { font-size: 12px; white-space: pre-line; line-height: 1.55; }
  </style>
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div class="logo">📄</div>
    <div>E-Doc Document Requesting System</div>
  </div>
  <div class="top-icons">
    <button class="icon-btn" type="button">🔔</button>
    <div class="icon-btn"><a href="profile.php">👤</a></div>
    <div class="icon-btn"><a href="../auth/logout.php">⎋</a></div>
  </div>
</header>

<main class="container">
  <section class="banner">
    <h1>Application Process</h1>
    <p>View Requirement and Process</p>
  </section>

  <section class="panel">
    <div class="note">
      <span class="pin">📌</span><b>Please note: Read carefully the requirement</b><br>
      Digital uploads are required for verification, but all official requirements must be submitted once verified and approved to the Registrar's Office
    </div>

    <div class="section">
      <h3>Documentary Requirements</h3>
      <div class="small-note">Read the process carefully and review the full list of requirements before uploading your documents.</div>

      <div class="doc-head">
        <div class="doc-title"><?= htmlspecialchars(strtoupper($document_type)) ?></div>
        <div class="workdays"><?= htmlspecialchars($workingDays) ?></div>
      </div>

      <?php if (count($titleTypes) === 0): ?>
        <p>(No requirements configured.)</p>
      <?php else: ?>
        <?php foreach ($titleTypes as $tt): ?>
          <?php
            $title = $tt["title_type"];
            $reqStmt->bind_param("ss", $document_type, $title);
            $reqStmt->execute();
            $reqs = $reqStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            // Skip title groups that only had placeholder rows
            if (empty($reqs)) continue;
          ?>
          <h3><?= htmlspecialchars($title) ?></h3>
          <ul>
            <?php foreach ($reqs as $r): ?>
              <li><?= htmlspecialchars($r["req_name"]) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- ── Reminders ── -->
      <?php if (!empty($reminders)): ?>
        <h3>Reminders</h3>
        <?php foreach ($reminders as $rem): ?>
          <div class="reminder-block">
            <?php if (!empty($rem["title_type"])): ?>
              <div class="reminder-title"><?= htmlspecialchars($rem["title_type"]) ?></div>
            <?php endif; ?>
            <div class="reminder-detail"><?= htmlspecialchars($rem["details"]) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- ── Application Process ── -->
      <?php if (!empty($appProcessItems)): ?>
        <h3>Application Process</h3>
        <?php foreach ($appProcessItems as $ap): ?>
          <div class="process-item"><?= htmlspecialchars($ap["details"]) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <a class="return-btn" href="application_process.php">RETURN</a>
    </div>
  </section>
</main>

</body>
</html>