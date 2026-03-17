<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);

$user_id = (int)$_SESSION["user_id"];
$ref = trim($_GET["ref"] ?? "");

if ($ref === "") {
  header("Location: dashboard.php");
  exit();
}

// Fetch request by reference number
$stmt = $conn->prepare("
  SELECT id, reference_no, document_type, title_type, purpose, copies,
         status, created_at, updated_at
  FROM requests
  WHERE reference_no = ? AND user_id = ?
  LIMIT 1
");
$stmt->bind_param("si", $ref, $user_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
  die("Request not found or not yours.");
}

$request_id = (int)$request["id"];
$docType = $request["document_type"];
$titleType = $request["title_type"];

$docUpper = strtoupper(trim($docType));
$titleUpper = strtoupper(trim($titleType));

// Fetch tracking logs
$logStmt = $conn->prepare("
  SELECT message, created_at
  FROM request_logs
  WHERE request_id = ?
  ORDER BY created_at ASC
");
$logStmt->bind_param("i", $request_id);
$logStmt->execute();
$logs = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch required requirements for THIS request (doc + title)
$reqStmt = $conn->prepare("
  SELECT req_name
  FROM requirements_master
  WHERE UPPER(document_type) = ? AND UPPER(title_type) = ?
  ORDER BY id ASC
");
$reqStmt->bind_param("ss", $docUpper, $titleUpper);
$reqStmt->execute();
$required = $reqStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch uploaded files for this request
$fileStmt = $conn->prepare("
  SELECT requirement_name, file_path, uploaded_at
  FROM request_files
  WHERE request_id = ?
");
$fileStmt->bind_param("i", $request_id);
$fileStmt->execute();
$uploadedRows = $fileStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build map: requirement_name => file info
$uploadedMap = [];
foreach ($uploadedRows as $u) {
  $key = strtoupper(trim($u["requirement_name"]));
  $uploadedMap[$key] = $u; // latest/only row (you delete old before insert)
}

// status pill class (matches dashboard.css)
function status_class($statusRaw){
  $s = strtoupper(trim($statusRaw ?? ""));
  if ($s === "COMPLETED") return "status-completed";
  if ($s === "READY" || $s === "READY FOR PICKUP") return "status-completed";
  if ($s === "APPROVED" || $s === "VERIFIED") return "status-approved";
  if ($s === "PROCESSING") return "status-processing";
  if ($s === "RETURNED") return "status-returned";
  return "status-pending";
}

$requestedOn = date("F j, Y", strtotime($request["created_at"]));
$lastUpdated = date("F j, Y", strtotime($request["updated_at"]));
$status = strtoupper($request["status"] ?? "PENDING");

// Counts
$totalReq = count($required);
$uploadedCount = 0;
foreach ($required as $r) {
  $k = strtoupper(trim($r["req_name"]));
  if (isset($uploadedMap[$k])) $uploadedCount++;
}

// Simple inline CSS for requirements rows + feed
$uiCSS = "
.track-box{ margin-top:14px; }
.big-status{ display:inline-block; padding:8px 14px; border-radius:12px; font-size:12px; }
.hr{ height:1px; background:#eee; margin:16px 0; }
.kv p{ margin:6px 0; font-size:13px; }

.req-wrap{ background:#fff; border:1px solid #dfe3ea; border-radius:14px; padding:14px; }
.req-row{ display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 0; border-top:1px solid #eee; }
.req-row:first-child{ border-top:none; }
.req-left{ display:flex; align-items:center; gap:10px; }
.req-badge{
  display:inline-block;
  padding:6px 10px;
  border-radius:10px;
  font-size:10px;
  font-weight:900;
  color:#fff;
  background:#333;
  min-width:90px;
  text-align:center;
}
.req-badge.ok{ background:#2f8a3a; }
.req-badge.miss{ background:#b36a2b; }
.req-name{ font-weight:900; font-size:12px; }
.req-actions{ display:flex; align-items:center; gap:10px; }
.req-view{
  font-size:11px;
  font-weight:900;
  color:#111;
  text-decoration:none;
}
.req-view:hover{ text-decoration:underline; }

.feed{ background:#fff; border:1px solid #dfe3ea; border-radius:14px; padding:14px; }
.feed-item{ padding:10px 0; border-top:1px solid #eee; }
.feed-item:first-child{ border-top:none; }
.feed-msg{ font-weight:900; font-size:12px; margin:0; }
.feed-time{ font-size:11px; color:#555; margin:4px 0 0; }
.smallnote{ font-size:11px; color:#444; margin-top:6px; }
";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Track Progress</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <style><?= $uiCSS ?></style>
</head>
<body>

<header class="topbar">
  <div class="brand">
      <!-- Optional small logo Waiting for design -->
      <!-- <img src="assets/img/edoc-logo.jpeg">  -->
    <div>E-Doc Document Requesting System</div>
  </div>
  <div class="top-icons">
    <div class="icon-btn" title="Home"><a href="dashboard.php">🏠</a></div>
    <div class="icon-btn" title="Logout"><a href="../auth/logout.php">⎋</a></div>
  </div>
</header>

<main class="container">

  <section class="panel">
    <h2>Track Progress</h2>
    <p>Stay updated with your request status and monitor the history of your application.</p>

    <div class="track-box kv">
      <p><b>Reference Number:</b> <?= htmlspecialchars($request["reference_no"]) ?></p>
      <p><b>Document:</b> <?= htmlspecialchars(strtoupper($request["document_type"])) ?></p>
      <p><b>Title Type:</b> <?= htmlspecialchars($request["title_type"]) ?></p>
      <p><b>Requested on:</b> <?= htmlspecialchars($requestedOn) ?></p>
      <p><b>Last Updated:</b> <?= htmlspecialchars($lastUpdated) ?></p>

      <p>
        <b>Application Status:</b>
        <span class="status-pill big-status <?= status_class($status) ?>">
          <?= htmlspecialchars($status) ?>
        </span>
      </p>
    </div>

    <div class="hr"></div>

    <h3>Requirements</h3>
    <div class="smallnote">
      Uploaded: <b><?= $uploadedCount ?></b> / <b><?= $totalReq ?></b>
    </div>

    <div class="req-wrap" style="margin-top:10px;">
      <?php if ($totalReq === 0): ?>
        <div class="req-row">
          <div class="req-left">
            <span class="req-badge miss">NO LIST</span>
            <span class="req-name">No requirements configured for this document/title type.</span>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($required as $r): ?>
          <?php
            $reqName = $r["req_name"];
            $key = strtoupper(trim($reqName));

            $isUploaded = isset($uploadedMap[$key]);
            $badgeClass = $isUploaded ? "ok" : "miss";
            $badgeText  = $isUploaded ? "UPLOADED" : "MISSING";

            $viewHref = "#";
            $uploadedAtText = "—";

            if ($isUploaded) {
              $path = $uploadedMap[$key]["file_path"]; // like uploads/requirements/file.pdf
              // link from /user/ -> ../uploads/...
              $viewHref = "../" . ltrim($path, "/");
              $uploadedAtText = date("m/d/y, g:i A", strtotime($uploadedMap[$key]["uploaded_at"]));
            }

            // Optional: link to upload page if missing
            $uploadLink = "upload_requirements_upload.php?ref=" . urlencode($request["reference_no"]);

          ?>
          <div class="req-row">
            <div class="req-left">
              <span class="req-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
              <span class="req-name"><?= htmlspecialchars(strtoupper($reqName)) ?></span>
            </div>

            <div class="req-actions">
              <?php if ($isUploaded): ?>
                <a class="req-view" href="<?= htmlspecialchars($viewHref) ?>" target="_blank">view &gt;&gt;&gt;</a>
                <span class="smallnote"><?= htmlspecialchars($uploadedAtText) ?></span>
              <?php else: ?>
                <a class="req-view" href="<?= htmlspecialchars($uploadLink) ?>">upload &gt;&gt;&gt;</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="hr"></div>

    <h3>Tracking History</h3>
    <div class="feed">
      <?php if (count($logs) === 0): ?>
        <div class="feed-item">
          <p class="feed-msg">No tracking history yet.</p>
          <p class="feed-time">—</p>
        </div>
      <?php else: ?>
        <?php foreach ($logs as $l): ?>
          <div class="feed-item">
            <p class="feed-msg"><?= htmlspecialchars(strtoupper($l["message"])) ?></p>
            <p class="feed-time"><?= htmlspecialchars(date("m/d/y, g:i A", strtotime($l["created_at"]))) ?></p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </section>

</main>
</body>
</html>
