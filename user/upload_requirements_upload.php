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

// Use same CSS you already use
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Upload Requirements</title>
  <link rel="stylesheet" href="../assets/css/upload_requirements.css">
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div class="logo">📄</div>
    <div>E-Doc Document Requesting System</div>
  </div>
  <div class="top-icons">
    <button class="icon-btn" onclick="history.back()">←</button>
    <div class="icon-btn"><a href="dashboard.php">🏠</a></div>
    <div class="icon-btn"><a href="../auth/logout.php">⎋</a></div>
  </div>
</header>

<main class="container">
  <section class="banner">
    <h1>Upload Requirements</h1>
    <p>Upload clear, properly scanned files in PDF format.</p>
  </section>

  <section class="panel">
    <a class="exit-btn" href="dashboard.php">EXIT</a>

    <div class="h2">IMPORTANT: Read Carefully Before Uploading Requirement</div>
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
        <button class="btn save" type="submit">SAVE</button>
      </div>
    </form>
  </section>
</main>

</body>
</html>
