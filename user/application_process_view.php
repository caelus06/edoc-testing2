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
$processHtml = $proc["process_html"] ?? "<b>Application Process</b><br><br>(No process info yet)";

// title types (Graduate, Not-Graduate, etc.)
$tStmt = $conn->prepare("SELECT DISTINCT title_type FROM requirements_master WHERE document_type = ? ORDER BY title_type ASC");
$tStmt->bind_param("s", $document_type);
$tStmt->execute();
$titleTypes = $tStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// requirements per title type
$reqStmt = $conn->prepare("SELECT req_name FROM requirements_master WHERE document_type=? AND title_type=? ORDER BY id ASC");

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
      Digital uploads are required for verification, but all official requirements must be submitted once verified and approved to the Registrar’s Office
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
          ?>
          <h3><?= htmlspecialchars($title) ?></h3>
          <ul>
            <?php if (count($reqs) === 0): ?>
              <li>(No requirements)</li>
            <?php else: ?>
              <?php foreach ($reqs as $r): ?>
                <li><?= htmlspecialchars($r["req_name"]) ?></li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="section">
        <?= $processHtml ?>
      </div>

      <a class="return-btn" href="application_process.php">RETURN</a>
    </div>
  </section>
</main>

</body>
</html>
