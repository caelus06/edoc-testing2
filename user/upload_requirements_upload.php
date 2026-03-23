<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);

$user_id = (int)$_SESSION["user_id"];

/* ------------------------------------------------------------------ */
/*  HELPER: find requirement_key by req_name                           */
/* ------------------------------------------------------------------ */
function find_requirement_key(mysqli $conn, string $docType, string $titleTyp, string $reqName): ?string {
  $q1 = $conn->prepare("
    SELECT requirement_key
    FROM requirements_master
    WHERE UPPER(TRIM(document_type)) = UPPER(TRIM(?))
      AND TRIM(title_type) = TRIM(?)
      AND TRIM(req_name) = TRIM(?)
    LIMIT 1
  ");
  $q1->bind_param("sss", $docType, $titleTyp, $reqName);
  $q1->execute();
  $r1 = $q1->get_result()->fetch_assoc();
  if ($r1 && !empty($r1["requirement_key"])) return $r1["requirement_key"];

  $q2 = $conn->prepare("
    SELECT requirement_key
    FROM requirements_master
    WHERE UPPER(TRIM(document_type)) = UPPER(TRIM(?))
      AND TRIM(req_name) = TRIM(?)
    ORDER BY id ASC
    LIMIT 1
  ");
  $q2->bind_param("ss", $docType, $reqName);
  $q2->execute();
  $r2 = $q2->get_result()->fetch_assoc();
  if ($r2 && !empty($r2["requirement_key"])) return $r2["requirement_key"];

  return null;
}

function fallback_key(string $reqName): string {
  $k = strtolower(trim($reqName));
  $k = preg_replace('/[^a-z0-9]+/', '_', $k);
  $k = trim($k, '_');
  return $k !== "" ? $k : "requirement";
}

/* ------------------------------------------------------------------ */
/*  LOAD REQUEST                                                       */
/* ------------------------------------------------------------------ */
$ref = trim($_GET["ref"] ?? ($_POST["ref"] ?? ""));
$uploadMsg = "";
$uploadErr = "";

if ($ref !== "") {
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

  $_SESSION["upload_request_id"] = $request_id;
  $_SESSION["upload_ref"] = $ref;

} else {
  $document_type = $_SESSION["upload_doc_type"] ?? "";
  if ($document_type === "") { header("Location: upload_requirements.php"); exit(); }

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

/* ------------------------------------------------------------------ */
/*  POST HANDLER: Single-file upload                                   */
/* ------------------------------------------------------------------ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_verify();

  $postReqName = trim($_POST["req_name"] ?? "");
  $postRef = trim($_POST["ref"] ?? "");

  if ($postReqName === "" || $postRef === "") {
    $uploadErr = "Invalid upload request.";
  } else {
    // Find the requirement_key
    $rk = find_requirement_key($conn, $docUpper, $title_type, $postReqName);
    if (!$rk) $rk = fallback_key($postReqName);

    // Check current status — block if VERIFIED
    $chk = $conn->prepare("SELECT id, file_path, review_status FROM request_files WHERE request_id=? AND requirement_key=? LIMIT 1");
    $chk->bind_param("is", $request_id, $rk);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();

    if ($existing && $existing["review_status"] === "VERIFIED") {
      $uploadErr = "This requirement has been verified and cannot be replaced.";
    } elseif (!isset($_FILES["req_file"]) || $_FILES["req_file"]["error"] !== UPLOAD_ERR_OK) {
      $uploadErr = "No file was uploaded. Please select a PDF file.";
    } elseif ((int)$_FILES["req_file"]["size"] > MAX_FILE_SIZE_BYTES) {
      $uploadErr = "File too large. Maximum size is " . MAX_FILE_SIZE_MB . "MB.";
    } else {
      $tmp = $_FILES["req_file"]["tmp_name"];
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime = finfo_file($finfo, $tmp);
      finfo_close($finfo);

      if ($mime !== "application/pdf") {
        $uploadErr = "Only PDF files are allowed.";
      } else {
        $uploadDir = "../uploads/requirements/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $safeName = preg_replace("/[^a-zA-Z0-9\-_\.]/", "_", basename($_FILES["req_file"]["name"]));
        $newName = bin2hex(random_bytes(12)) . "_" . $safeName;
        $dest = $uploadDir . $newName;

        if (!move_uploaded_file($tmp, $dest)) {
          $uploadErr = "Failed to save file. Please try again.";
        } else {
          $relativePath = "uploads/requirements/" . $newName;

          // Delete old file from disk if replacing
          if ($existing && !empty($existing["file_path"]) && file_exists("../" . $existing["file_path"])) {
            @unlink("../" . $existing["file_path"]);
          }

          // Delete old DB row
          if ($existing) {
            $del = $conn->prepare("DELETE FROM request_files WHERE request_id=? AND requirement_key=?");
            $del->bind_param("is", $request_id, $rk);
            $del->execute();
          }

          // Insert new row with PENDING status
          $ins = $conn->prepare("
            INSERT INTO request_files (request_id, requirement_key, requirement_name, file_path, uploaded_at, review_status)
            VALUES (?, ?, ?, ?, NOW(), 'PENDING')
          ");
          $ins->bind_param("isss", $request_id, $rk, $postReqName, $relativePath);
          $ins->execute();

          add_log($conn, $request_id, "Requirement uploaded: " . $postReqName);
          audit_log($conn, "INSERT", "request_files", $request_id, "Requirement uploaded: " . $postReqName);
          $uploadMsg = "Successfully uploaded: " . $postReqName;
        }
      }
    }
  }

  // Redirect to prevent form resubmission (PRG pattern)
  if ($uploadErr === "") {
    header("Location: upload_requirements_upload.php?ref=" . urlencode($ref) . "&msg=" . urlencode($uploadMsg));
    exit();
  }
}

// Check for success message from redirect
if (isset($_GET["msg"]) && $_GET["msg"] !== "") {
  $uploadMsg = $_GET["msg"];
}

/* ------------------------------------------------------------------ */
/*  FETCH DATA FOR DISPLAY                                             */
/* ------------------------------------------------------------------ */

// Requirements list from master
$reqStmt = $conn->prepare("
  SELECT req_name, requirement_key
  FROM requirements_master
  WHERE UPPER(document_type)=? AND UPPER(title_type)=?
  ORDER BY id ASC
");
$reqStmt->bind_param("ss", $docUpper, $titleUpper);
$reqStmt->execute();
$requirements = $reqStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Uploaded files for this request, keyed by requirement_key
$fileStmt = $conn->prepare("
  SELECT requirement_key, requirement_name, file_path, uploaded_at, review_status, resubmit_reason
  FROM request_files
  WHERE request_id = ?
");
$fileStmt->bind_param("i", $request_id);
$fileStmt->execute();
$fileRows = $fileStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$filesByKey = [];
foreach ($fileRows as $fr) {
  $k = (string)($fr["requirement_key"] ?? "");
  if ($k !== "") $filesByKey[$k] = $fr;
}

$requestedOn = $req["created_at"] ? date("F j, Y", strtotime($req["created_at"])) : "—";
$lastUpdated = $req["updated_at"] ? date("F j, Y", strtotime($req["updated_at"])) : "—";
$statusText = strtoupper($req["status"] ?? "PENDING");

/* ---------- Notifications ---------- */
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

$seenStmt = $conn->prepare("SELECT last_seen_at FROM user_notif_seen WHERE user_id = ? LIMIT 1");
$seenStmt->bind_param("i", $user_id);
$seenStmt->execute();
$seenRow = $seenStmt->get_result()->fetch_assoc();
$lastSeenAt = $seenRow["last_seen_at"] ?? "2000-01-01 00:00:00";

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
    <button class="icon-btn" title="Logout" id="logoutBtn">⎋</button>
  </div>
</header>

<main class="container">
  <section class="banner">
    <h1>Upload Requirements</h1>
    <p>Upload clear, properly scanned files in PDF format.</p>
  </section>

  <section class="panel">
    <a class="exit-btn" href="dashboard.php">EXIT</a>

    <div class="h2">Important Instructions</div>
    <div class="note" style="margin-top: 15px;">
      <ul style="margin: 0; padding-left: 18px; font-size: 13px;">
        <li>Files must be in <b>PDF format</b> only.</li>
        <li>Each requirement must be uploaded as a <b>separate file</b>.</li>
        <li>Maximum file size per document is <b>15MB</b>.</li>
        <li>Ensure all scans are <b>clear and readable</b>.</li>
      </ul>
    </div>

    <div class="status-block">
      <div class="h2" style="margin-top:14px;">Document Request Status</div>
      <div style="margin-top:8px;">
        <div><b>Reference Number:</b> <?= h($ref) ?></div>
        <div><b>Document:</b> <?= h($docUpper) ?></div>
        <div><b>Title Type:</b> <?= h($title_type) ?></div>
        <div><b>Requested:</b> <?= h($requestedOn) ?></div>
        <div><b>Last Updated:</b> <?= h($lastUpdated) ?></div>
        <div><b>Application Status:</b> <span class="pill"><?= h($statusText) ?></span></div>
      </div>
    </div>

    <?php if ($uploadMsg !== ""): ?>
      <div class="alert alert-success"><?= h($uploadMsg) ?></div>
    <?php endif; ?>

    <?php if ($uploadErr !== ""): ?>
      <div class="alert alert-error"><?= h($uploadErr) ?></div>
    <?php endif; ?>

    <div class="h2" style="margin-top:24px;">Requirements</div>

    <?php if (count($requirements) === 0): ?>
      <p>No requirements configured for <?= h($docUpper) ?> (<?= h($titleUpper) ?>).</p>
    <?php else: ?>
      <div class="req-table">
        <?php foreach ($requirements as $r):
          $reqName = $r["req_name"];
          $rk = $r["requirement_key"] ?? null;
          if (!$rk) $rk = fallback_key($reqName);

          $file = $filesByKey[$rk] ?? null;
          $reviewStatus = $file["review_status"] ?? null;
          $resubmitReason = $file["resubmit_reason"] ?? null;
          $filePath = $file["file_path"] ?? null;
          $uploadedAt = $file["uploaded_at"] ?? null;

          $isVerified = ($reviewStatus === "VERIFIED");
          $isResubmit = ($reviewStatus === "RESUBMIT");
          $isPending = ($reviewStatus === "PENDING");
          $isUploaded = ($file !== null);

          // Determine pill class and label
          if (!$isUploaded) {
            $pillClass = "pill-none";
            $pillLabel = "NOT UPLOADED";
          } elseif ($isVerified) {
            $pillClass = "pill-verified";
            $pillLabel = "VERIFIED";
          } elseif ($isResubmit) {
            $pillClass = "pill-resubmit";
            $pillLabel = "RESUBMIT";
          } else {
            $pillClass = "pill-pending";
            $pillLabel = "PENDING";
          }

          $canUpload = !$isVerified;
        ?>
          <div class="req-row">
            <div class="req-row-header">
              <div class="req-name"><?= h(strtoupper($reqName)) ?></div>
              <span class="pill <?= $pillClass ?>"><?= $pillLabel ?></span>
            </div>

            <?php if ($isVerified): ?>
              <div class="req-row-body req-verified-msg">
                This requirement has been verified.
                <?php if ($filePath): ?>
                  <a class="file-view" href="../<?= h($filePath) ?>" target="_blank">View File</a>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="req-row-body">
                <?php if ($isUploaded && $filePath): ?>
                  <div class="req-file-info">
                    <span class="file-name"><?= h(basename($filePath)) ?></span>
                    <a class="file-view" href="../<?= h($filePath) ?>" target="_blank">View</a>
                    <?php if ($uploadedAt): ?>
                      <span class="file-date">Uploaded: <?= h(date("M j, Y g:i A", strtotime($uploadedAt))) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <?php if ($isResubmit && $resubmitReason): ?>
                  <div class="resubmit-note">
                    <b>Reason for resubmission:</b> <?= h($resubmitReason) ?>
                  </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="req-upload-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="ref" value="<?= h($ref) ?>">
                  <input type="hidden" name="req_name" value="<?= h($reqName) ?>">
                  <div class="upload-row">
                    <input type="file" name="req_file" accept="application/pdf" required>
                    <button class="btn-upload" type="submit">UPLOAD</button>
                  </div>
                  <div class="small">PDF only (Max. 15MB)</div>
                </form>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="actions">
      <button class="btn pre" onclick="history.back()">PREVIOUS</button>
      <a class="btn save" href="dashboard.php" style="text-decoration:none;">DONE</a>
    </div>
  </section>
</main>

<!-- NOTIFICATION MODAL -->
<div class="modal-backdrop" id="notifBackdrop">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="notifTitle">
    <button class="close-x" id="notifClose" type="button">&times;</button>
    <h3 id="notifTitle">NOTIFICATION</h3>

    <?php if (empty($notifs)): ?>
      <div class="notif-item">
        <div class="notif-title">No notifications yet</div>
        <div class="notif-time">&mdash;</div>
      </div>
    <?php else: ?>
      <?php foreach ($notifs as $n): ?>
        <div class="notif-item">
          <div class="notif-title"><?= h($n["message"]) ?></div>
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
    markSeen();
  }

  function closeNotif(){
    backdrop.style.display = "none";
  }

  notifBtn?.addEventListener("click", openNotif);
  closeBtn?.addEventListener("click", closeNotif);

  backdrop?.addEventListener("click", (e) => {
    if (e.target === backdrop) closeNotif();
  });

  // Logout Logic
  const logoutBtn = document.getElementById("logoutBtn");
  const logoutBackdrop = document.getElementById("logoutBackdrop");
  const logoutCancel = document.getElementById("logoutCancel");

  logoutBtn?.addEventListener("click", () => logoutBackdrop.style.display = "flex");
  logoutCancel?.addEventListener("click", () => logoutBackdrop.style.display = "none");

  // General Modal Logic
  window.addEventListener("click", (e) => {
    if (e.target === backdrop) backdrop.style.display = "none";
    if (e.target === logoutBackdrop) logoutBackdrop.style.display = "none";
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      backdrop.style.display = "none";
      logoutBackdrop.style.display = "none";
    }
  });
</script>

</body>
</html>
