<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_REGISTRAR);

$registrar_id = (int)$_SESSION["user_id"];

$_regQ = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1");
$_regQ->bind_param("i", $registrar_id);
$_regQ->execute();
$_regRow = $_regQ->get_result()->fetch_assoc();
$registrar_name = $_regRow ? trim($_regRow["first_name"] . " " . $_regRow["last_name"]) : "Registrar #" . $registrar_id;
$registrar_anon = get_registrar_id($conn, $registrar_id);

function redirect_back(int $request_id, string $rk){
  header("Location: verify_request.php?id={$request_id}&rk=" . urlencode($rk));
  exit();
}

/**
 * Normalize title_type to improve matching in requirements_master.
 * (No DB change; only for fetching requirements.)
 */
function normalize_title_type(string $docType, string $titleType): string {
  $doc = strtoupper(trim($docType));
  $t   = strtoupper(trim($titleType));
  $t = preg_replace('/\s+/', ' ', $t);

  if ($doc === "TRANSCRIPT OF RECORDS") {
    if (str_contains($t, "NOT") || str_contains($t, "BACHELOR") || str_contains($t, "UNDERGRAD")) return "Not-Graduate";
    if (str_contains($t, "GRAD")) return "Graduate";
  }

  if ($doc === "DIPLOMA") {
    if (str_contains($t, "FIRST"))  return "First Request";
    if (str_contains($t, "SECOND")) return "Second Request";
  }

  if ($doc === "AUTHENTICATION") {
    if (str_contains($t, "LOCAL"))  return "Local";
    if (str_contains($t, "ABROAD")) return "Abroad";
  }

  if ($doc === "CERTIFICATES") {
    if (str_contains($t, "POST")) return "Post-Grad";
    if (str_contains($t, "BACC") || str_contains($t, "GRAD") || str_contains($t, "UNDER")) return "Baccalaureate";
  }

  return trim($titleType);
}

/**
 * Normalize application status values so READY FOR PICKUP + COMPLETED always save correctly.
 */
function normalize_app_status(string $raw): string {
  $s = strtoupper(trim($raw));
  $s = preg_replace('/\s+/', ' ', $s);

  $map = [
    "COMPLETE" => "COMPLETED",
    "COMPLETED" => "COMPLETED",

    "READY" => "READY FOR PICKUP",
    "READY FOR PICK UP" => "READY FOR PICKUP",
    "READY FOR PICKUP" => "READY FOR PICKUP",

    "PENDING" => "PENDING",
    "APPROVED" => "APPROVED",
    "PROCESSING" => "PROCESSING",
    "RETURNED" => "RETURNED",
    "CANCELLED" => "CANCELLED",
    "RELEASED" => "RELEASED",
    "VERIFIED" => "VERIFIED",
  ];

  return $map[$s] ?? $s;
}

// ------------------------------
// Identify request + selected requirement key
// ------------------------------
$request_id = (int)($_GET["id"] ?? ($_POST["request_id"] ?? 0));
$rk = trim($_GET["rk"] ?? ($_POST["rk"] ?? ""));

if ($request_id <= 0) {
  swal_flash("error", "Error", "Missing request id.");
  header("Location: request_management.php");
  exit();
}
if ($rk === "") $rk = "valid_id";

// Special key for scanned document
$SCANNED_KEY = "scanned_document";

// ------------------------------
// Fetch request + user info
// ------------------------------
$st = $conn->prepare("
  SELECT r.*,
         u.first_name, u.middle_name, u.last_name, u.suffix,
         u.student_id, u.course, u.major, u.year_graduated,
         u.gender, u.email, u.contact_number, u.address,
         u.verification_status
  FROM requests r
  JOIN users u ON u.id = r.user_id
  WHERE r.id = ?
  LIMIT 1
");
$st->bind_param("i", $request_id);
$st->execute();
$reqRow = $st->get_result()->fetch_assoc();
if (!$reqRow) {
  swal_flash("error", "Error", "Request not found.");
  header("Location: request_management.php");
  exit();
}

$doc_type_raw   = (string)($reqRow["document_type"] ?? "");
$title_type_raw = (string)($reqRow["title_type"] ?? "");

$doc_type_db   = strtoupper(trim($doc_type_raw));
$title_type_db = normalize_title_type($doc_type_db, $title_type_raw);

// ------------------------------
// Fetch requirements from requirements_master
// doc+title exact, then doc only; dedupe by requirement_key
// ------------------------------
$reqs = [];

// 1) doc + title_type
$qReq = $conn->prepare("
  SELECT requirement_key, req_name
  FROM requirements_master
  WHERE UPPER(TRIM(document_type)) = UPPER(TRIM(?))
    AND TRIM(title_type) = TRIM(?)
  ORDER BY id ASC
");
$qReq->bind_param("ss", $doc_type_db, $title_type_db);
$qReq->execute();
$reqs = $qReq->get_result()->fetch_all(MYSQLI_ASSOC);

// 2) fallback: doc only
if (count($reqs) === 0) {
  $qReq2 = $conn->prepare("
    SELECT requirement_key, MIN(req_name) AS req_name
    FROM requirements_master
    WHERE UPPER(TRIM(document_type)) = UPPER(TRIM(?))
    GROUP BY requirement_key
    ORDER BY MIN(id) ASC
  ");
  $qReq2->bind_param("s", $doc_type_db);
  $qReq2->execute();
  $reqs = $qReq2->get_result()->fetch_all(MYSQLI_ASSOC);
}

// 3) final fallback
if (count($reqs) === 0) {
  $reqs = [
    ["requirement_key" => "valid_id", "req_name" => "Valid ID"]
  ];
}

// Dedupe keys
$seen = [];
$deduped = [];
foreach ($reqs as $r) {
  $k = (string)($r["requirement_key"] ?? "");
  if ($k === "" || isset($seen[$k])) continue;
  $seen[$k] = true;
  $deduped[] = $r;
}
$reqs = $deduped;

// Build keys list
$keys = array_map(fn($x)=>$x["requirement_key"], $reqs);

// Always add scanned document as last item
$keys[] = $SCANNED_KEY;

// Ensure rk exists
if (!in_array($rk, $keys, true)) $rk = $keys[0];

// ------------------------------
// Fetch request files
// ------------------------------
$f = $conn->prepare("SELECT * FROM request_files WHERE request_id=?");
$f->bind_param("i", $request_id);
$f->execute();
$fileRows = $f->get_result()->fetch_all(MYSQLI_ASSOC);

$filesByKey = [];
foreach ($fileRows as $fr) {
  $k = (string)($fr["requirement_key"] ?? "");
  if ($k !== "") $filesByKey[$k] = $fr;
}

$selectedFile = $filesByKey[$rk] ?? null;

// prev/next keys for arrows
$idx = array_search($rk, $keys, true);
$prevKey = ($idx !== false && $idx > 0) ? $keys[$idx-1] : null;
$nextKey = ($idx !== false && $idx < count($keys)-1) ? $keys[$idx+1] : null;

// Display fields
$fullName = trim(($reqRow["first_name"] ?? "")." ".($reqRow["middle_name"] ?? "")." ".($reqRow["last_name"] ?? ""));
$accountStatus = strtoupper($reqRow["verification_status"] ?? "PENDING");

// ------------------------------
// Handle POST actions
// ------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_verify();
  $action = trim($_POST["action"] ?? "");

  // trust rk from POST for actions
  $rk = trim($_POST["rk"] ?? $rk);
  // if ($rk === "") $rk = "valid_id";

  // SAVE
  if ($action === "save") {
    $req_status = $_POST["req_status"] ?? [];
    $resubmit_reasons = $_POST["resubmit_reason"] ?? [];
    $app_status = normalize_app_status($_POST["app_status"] ?? "");

    // 1. Update Requirements ONLY IF changed
        if (is_array($req_status)) {
            foreach ($req_status as $key => $val) {
                $key = trim((string)$key);
                $val = strtoupper(trim((string)$val));
                if ($key === "" || $key === $SCANNED_KEY) continue;

                $row = $conn->prepare("SELECT id, verified_at, review_status FROM request_files WHERE request_id=? AND requirement_key=? LIMIT 1");
                $row->bind_param("is", $request_id, $key);
                $row->execute();
                $rf = $row->get_result()->fetch_assoc();
                if (!$rf) continue;

                $currentReviewStatus = strtoupper($rf["review_status"] ?? "PENDING");
                $reason = trim((string)($resubmit_reasons[$key] ?? ""));

                // Handle Status Transitions (only if changed)
                if ($val === "VERIFIED" && $currentReviewStatus !== "VERIFIED") {
                    $up = $conn->prepare("
                      UPDATE request_files
                      SET verified_at=NOW(), verified_by=?, review_status='VERIFIED', resubmit_reason=NULL
                      WHERE request_id=? AND requirement_key=?
                    ");
                    $up->bind_param("iis", $registrar_id, $request_id, $key);
                    $up->execute();
                    notify_user($conn, $request_id, "Reference Number: " . $reqRow["reference_no"] . " (" . strtoupper($reqRow["document_type"]) . ") — " . ucfirst($key) . " has been verified. Processed by " . $registrar_anon);
                    audit_log($conn, "UPDATE", "request_files", $request_id, "Verified: " . ucfirst($key));
                } elseif ($val === "RESUBMIT" && $currentReviewStatus !== "RESUBMIT") {
                    $up = $conn->prepare("
                      UPDATE request_files
                      SET verified_at=NULL, verified_by=NULL, review_status='RESUBMIT', resubmit_reason=?
                      WHERE request_id=? AND requirement_key=?
                    ");
                    $up->bind_param("sis", $reason, $request_id, $key);
                    $up->execute();
                    notify_user($conn, $request_id, "Reference Number: " . $reqRow["reference_no"] . " (" . strtoupper($reqRow["document_type"]) . ") — Resubmission required for " . ucfirst($key) . ". Processed by " . $registrar_anon);
                    audit_log($conn, "UPDATE", "request_files", $request_id, "Resubmit required: " . ucfirst($key));
                } elseif ($val === "RESUBMIT" && $currentReviewStatus === "RESUBMIT") {
                    // Same status but reason may have changed
                    $up = $conn->prepare("
                      UPDATE request_files
                      SET resubmit_reason=?
                      WHERE request_id=? AND requirement_key=?
                    ");
                    $up->bind_param("sis", $reason, $request_id, $key);
                    $up->execute();
                } elseif ($val === "PENDING" && $currentReviewStatus !== "PENDING") {
                    $up = $conn->prepare("
                      UPDATE request_files
                      SET verified_at=NULL, verified_by=NULL, review_status='PENDING', resubmit_reason=NULL
                      WHERE request_id=? AND requirement_key=?
                    ");
                    $up->bind_param("is", $request_id, $key);
                    $up->execute();
                    notify_user($conn, $request_id, "Reference Number: " . $reqRow["reference_no"] . " (" . strtoupper($reqRow["document_type"]) . ") — " . ucfirst($key) . " returned to Pending. Processed by " . $registrar_anon);
                    audit_log($conn, "UPDATE", "request_files", $request_id, "Returned to pending: " . ucfirst($key));
                }
            }
        }

        // 2. Update Application Status ONLY IF changed
        $allowed = ["PENDING","APPROVED","PROCESSING","READY FOR PICKUP","COMPLETED","RETURNED","CANCELLED","RELEASED","VERIFIED"];
        if ($app_status !== "" && in_array($app_status, $allowed, true)) {
            // Check current status in DB
            $currQ = $conn->prepare("SELECT status FROM requests WHERE id = ?");
            $currQ->bind_param("i", $request_id);
            $currQ->execute();
            $currentDBStatus = normalize_app_status($currQ->get_result()->fetch_assoc()['status'] ?? "");

            if ($app_status !== $currentDBStatus) {
                $upReq = $conn->prepare("UPDATE requests SET status=?, updated_at=NOW() WHERE id=?");
                $upReq->bind_param("si", $app_status, $request_id);
                $upReq->execute();
                notify_user($conn, $request_id, "Reference Number: " . $reqRow["reference_no"] . " (" . strtoupper($reqRow["document_type"]) . ") — Application status updated to " . $app_status . ". Processed by " . $registrar_anon);
                audit_log($conn, "UPDATE", "requests", $request_id, "Application status updated to " . $app_status);
            }
        }
        swal_flash("success", "Saved", "Changes saved successfully.");
        redirect_back($request_id, $rk);
    }

  // DELETE
  if ($action === "delete") {
    $st = $conn->prepare("SELECT id, file_path, verified_at FROM request_files WHERE request_id=? AND requirement_key=? LIMIT 1");
    $st->bind_param("is", $request_id, $rk);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    if (!$row) {
      swal_flash("error", "Error", "No file found for this item. Nothing to delete.");
      redirect_back($request_id, $rk);
    }

    // Optional lock: lock delete if verified (applies even to scanned_document)
    // Added a "lock" Mechanism so that if you change a status (for example, from Verified back to Pending), 
    // it will show a message "This file is VERIFIED. Delete is locked." to delete it you need to change first the status to pending 
    // then delete the file.  
    // if (!empty($row["verified_at"])) die("This file is VERIFIED. Delete is locked.");

    if (!empty($row["file_path"]) && file_exists("../" . $row["file_path"])) {
      @unlink("../" . $row["file_path"]);
    }

    $del = $conn->prepare("DELETE FROM request_files WHERE id=?");
    $del->bind_param("i", $row["id"]);
    $del->execute();

    add_log($conn, $request_id, "Registrar Update: Removed " . ucfirst($rk));
    audit_log($conn, "DELETE", "request_files", $row["id"], "Removed file: " . ucfirst($rk));
    swal_flash("success", "Deleted", "File deleted successfully.");
    redirect_back($request_id, $rk);
  }

  // UPLOAD (registrar overwrites preview file)
  if ($action === "upload") {
    if (!isset($_FILES["req_file"]) || $_FILES["req_file"]["error"] !== UPLOAD_ERR_OK) {
      swal_flash("error", "Error", "No file uploaded.");
      redirect_back($request_id, $rk);
    }

    $chk = $conn->prepare("SELECT id, file_path, verified_at FROM request_files WHERE request_id=? AND requirement_key=? LIMIT 1");
    $chk->bind_param("is", $request_id, $rk);
    $chk->execute();
    $ex = $chk->get_result()->fetch_assoc();

    // Optional lock: lock overwrite if verified
    if ($ex && !empty($ex["verified_at"])) {
      swal_flash("warning", "Locked", "This file is VERIFIED. Upload is locked.");
      redirect_back($request_id, $rk);
    }

    $tmp = $_FILES["req_file"]["tmp_name"];
    $size = (int)$_FILES["req_file"]["size"];
    if ($size > 15 * 1024 * 1024) {
      swal_flash("error", "Error", "Max 15MB only.");
      redirect_back($request_id, $rk);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    $allowed = [
      "image/jpeg" => "jpg",
      "image/png"  => "png",
      "image/webp" => "webp",
      "application/pdf" => "pdf"
    ];
    if (!isset($allowed[$mime])) {
      swal_flash("error", "Error", "Only JPG/PNG/WebP/PDF allowed.");
      redirect_back($request_id, $rk);
    }

    $ext = $allowed[$mime];
    $dir = "../uploads/request_files/" . $request_id;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $fname = $rk . "_" . bin2hex(random_bytes(8)) . "." . $ext;
    $dest = $dir . "/" . $fname;

    if (!move_uploaded_file($tmp, $dest)) {
      swal_flash("error", "Error", "Upload failed.");
      redirect_back($request_id, $rk);
    }
    $relative = str_replace("../", "", $dest);

    // requirement_name
    $reqName = null;

    if ($rk === $SCANNED_KEY) {
      $reqName = "Scanned Document";
    } else {
      foreach ($reqs as $r) {
        if ($r["requirement_key"] === $rk) { $reqName = $r["req_name"]; break; }
      }
      if (!$reqName) $reqName = ucwords(str_replace("_"," ", $rk));
    }

    if ($ex) {
      if (!empty($ex["file_path"]) && file_exists("../".$ex["file_path"])) {
        @unlink("../".$ex["file_path"]);
      }

      $up = $conn->prepare("
        UPDATE request_files
        SET file_path=?, uploaded_at=NOW(),
            verified_at=NULL, verified_by=NULL,
            requirement_name=?
        WHERE id=?
      ");
      $up->bind_param("ssi", $relative, $reqName, $ex["id"]);
      $up->execute();
    } else {
      $ins = $conn->prepare("
        INSERT INTO request_files (request_id, requirement_key, requirement_name, file_path, uploaded_at)
        VALUES (?, ?, ?, ?, NOW())
      ");
      $ins->bind_param("isss", $request_id, $rk, $reqName, $relative);
      $ins->execute();
    }

    add_log($conn, $request_id, "Registrar Update: " . ucfirst($rk) . " uploaded");
    audit_log($conn, "INSERT", "request_files", $request_id, "Uploaded file: " . ucfirst($rk));
    swal_flash("success", "Uploaded", "File uploaded successfully.");
    redirect_back($request_id, $rk);
  }

  swal_flash("error", "Error", "Invalid action.");
  redirect_back($request_id, $rk);
  
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Request</title>
  <link rel="stylesheet" href="../assets/css/verify_request.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
</head>
<body>

<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sb-user">
      <div class="avatar">👤</div>
      <div class="meta">
        <div class="name"><?= h($registrar_name) ?></div>
        <div class="role">Registrar</div>
      </div>
    </div>

    <div class="sb-section-title">MODULES</div>
    <nav class="sb-nav">
      <a class="sb-item" href="dashboard.php"><span class="sb-icon">🏠</span>Dashboard</a>
      <a class="sb-item" href="new_document_request.php"><span class="sb-icon">📝</span>New Document Request</a>
      <a class="sb-item active" href="request_management.php"><span class="sb-icon">🔎</span>Request Management</a>
      <a class="sb-item" href="track_progress.php"><span class="sb-icon">📍</span>Track Progress</a>
      <a class="sb-item" href="document_management.php"><span class="sb-icon">📄</span>Document Management</a>
      <a class="sb-item" href="create_document.php"><span class="sb-icon">➕</span>Create Document</a>
      <a class="sb-item" href="non_compliant.php"><span class="sb-icon">&#9888;</span>Non-Compliant Users</a>
    </nav>

    <div class="sb-section-title">SETTINGS</div>
    <nav class="sb-nav">
      <a class="sb-item" href="../mis/system_settings.php"><span class="sb-icon">&#9881;</span>System Settings</a>
      <a class="sb-item" href="#" onclick="event.preventDefault(); swalConfirm('Logout', 'Are you sure you want to log out?', 'Yes, log out', function(){ window.location='../auth/logout.php'; })"><span class="sb-icon">⎋</span>Logout</a>
    </nav>
  </aside>

  <!-- MAIN -->
  <div class="main">
    <header class="topbar">
      <button class="hamburger" type="button" onclick="toggleSidebar()">≡</button>
      <div class="brand">
        <div class="logo"><img src="../assets/img/edoc-logo.jpeg" alt="E-Doc"></div>
        <div>E-Doc Document Requesting System</div>
      </div>
    </header>

    <main class="container">

      <!-- PAGE HEADER -->
      <div class="page-head">
        <div class="page-head-row">
          <div>
            <h1>Verify Request</h1>
            <p>Review uploaded documents and update application status</p>
          </div>
          <a class="btn-back" href="request_management.php">Back to Requests</a>
        </div>
      </div>

      <!-- REQUEST SUMMARY BAR -->
      <div class="summary-bar">
        <div class="summary-item"><span class="summary-label">Reference</span><span class="summary-value"><?= h($reqRow["reference_no"]) ?></span></div>
        <div class="summary-item"><span class="summary-label">Document</span><span class="summary-value"><?= h(strtoupper($doc_type_raw)) ?></span></div>
        <div class="summary-item"><span class="summary-label">Title Type</span><span class="summary-value"><?= h($title_type_raw ?: "N/A") ?></span></div>
        <div class="summary-item"><span class="summary-label">Status</span><span class="summary-value pill pill-<?= strtolower(str_replace(' ', '', $reqRow["status"] ?? "pending")) ?>"><?= h(strtoupper($reqRow["status"] ?? "PENDING")) ?></span></div>
      </div>

      <!-- TWO-COLUMN CONTENT -->
      <form id="verifyForm" method="POST" action="verify_request.php">
        <?= csrf_field() ?>
        <input type="hidden" name="request_id" value="<?= (int)$request_id ?>">
        <input type="hidden" name="rk" value="<?= h($rk) ?>">
        <input type="hidden" name="action" value="save">

        <div class="content-grid">

          <!-- LEFT COLUMN -->
          <div class="left-col">

            <!-- Requestor Info Card -->
            <div class="card">
              <div class="card-head">Requestor Information</div>
              <div class="info-grid">
                <div class="info-item"><span class="info-label">Name</span><span class="info-value"><?= h($fullName) ?></span></div>
                <div class="info-item"><span class="info-label">Student ID</span><span class="info-value"><?= h($reqRow["student_id"] ?: "N/A") ?></span></div>
                <div class="info-item"><span class="info-label">Email</span><span class="info-value"><?= h($reqRow["email"] ?: "N/A") ?></span></div>
                <div class="info-item"><span class="info-label">Contact</span><span class="info-value"><?= h($reqRow["contact_number"] ?: "N/A") ?></span></div>
                <div class="info-item"><span class="info-label">Course</span><span class="info-value"><?= h($reqRow["course"] ?: "N/A") ?></span></div>
                <div class="info-item"><span class="info-label">Major</span><span class="info-value"><?= h($reqRow["major"] ?: "N/A") ?></span></div>
                <div class="info-item"><span class="info-label">Year Graduated</span><span class="info-value"><?= h($reqRow["year_graduated"] ?: "N/A") ?></span></div>
                <div class="info-item"><span class="info-label">Copies</span><span class="info-value"><?= (int)$reqRow["copies"] ?></span></div>
                <div class="info-item">
                  <span class="info-label">Account</span>
                  <span class="info-value"><?php if ($accountStatus === "VERIFIED"): ?><span class="pill pill-verified">VERIFIED</span><?php else: ?><span class="pill pill-pending">PENDING</span><?php endif; ?></span>
                </div>
              </div>
            </div>

            <!-- Requirements Card -->
            <div class="card">
              <div class="card-head">Requirements</div>

              <div class="req-list">
                <?php foreach ($reqs as $r): ?>
                  <?php
                    $key = (string)$r["requirement_key"];
                    $row = $filesByKey[$key] ?? null;
                    $statusVal = strtoupper($row["review_status"] ?? "PENDING");
                    $savedReason = $row["resubmit_reason"] ?? "";
                    $isActive = ($rk === $key);
                    $hasFile = $row && !empty($row["file_path"]);
                  ?>
                  <a class="req-item <?= $isActive ? 'req-active' : '' ?>" href="verify_request.php?id=<?= (int)$request_id ?>&rk=<?= urlencode($key) ?>">
                    <span class="req-name"><?= h($r["req_name"]) ?></span>
                    <span class="req-status req-status-<?= strtolower($statusVal) ?>"><?= $statusVal ?></span>
                  </a>
                  <?php if (!$isActive): ?>
                    <input type="hidden" name="req_status[<?= h($key) ?>]" value="<?= h($statusVal) ?>">
                    <input type="hidden" name="resubmit_reason[<?= h($key) ?>]" value="<?= h($savedReason) ?>">
                  <?php endif; ?>
                <?php endforeach; ?>

                <!-- Scanned Document -->
                <a class="req-item req-item-scan <?= ($rk === $SCANNED_KEY) ? 'req-active' : '' ?>" href="verify_request.php?id=<?= (int)$request_id ?>&rk=<?= urlencode($SCANNED_KEY) ?>">
                  <span class="req-name">Scanned Document</span>
                  <span class="req-hint">Final copy</span>
                </a>
              </div>
            </div>

            <!-- Application Status Card -->
            <div class="card">
              <div class="card-head">Application Status</div>
              <select class="app-select" name="app_status">
                <?php
                  $app = normalize_app_status($reqRow["status"] ?? "PENDING");
                  $opts = ["PENDING","APPROVED","PROCESSING","READY FOR PICKUP","COMPLETED"];
                  foreach ($opts as $o):
                ?>
                  <option value="<?= h($o) ?>" <?= $app===$o ? "selected" : "" ?>><?= h($o) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="app-hint">Once COMPLETED, scan and upload the final document above.</div>
            </div>

            <!-- Action Buttons -->
            <div class="form-actions">
              <button class="btn-save" type="submit" form="verifyForm">Save Changes</button>
              <a class="btn-cancel" href="request_management.php">Cancel</a>
            </div>

          </div>

        </div><!-- /.content-grid (left col) -->
      </form>

      <!-- RIGHT COLUMN: Document Preview (outside main form so upload/delete forms work) -->
      <div class="preview-wrapper">
        <div class="card preview-card">
          <div class="preview-header">
            <div class="preview-title">
              <?php
                if ($rk === $SCANNED_KEY) {
                  echo "Scanned Document";
                } else {
                  $shownTitle = null;
                  foreach ($reqs as $r) {
                    if ($r["requirement_key"] === $rk) { $shownTitle = $r["req_name"]; break; }
                  }
                  echo h($shownTitle ?: ucwords(str_replace("_", " ", $rk)));
                }
              ?>
            </div>
            <div class="preview-nav">
              <?php if ($prevKey): ?>
                <a class="nav-arrow" href="verify_request.php?id=<?= (int)$request_id ?>&rk=<?= urlencode($prevKey) ?>" title="Previous">&lsaquo;</a>
              <?php else: ?>
                <span class="nav-arrow disabled">&lsaquo;</span>
              <?php endif; ?>
              <?php if ($nextKey): ?>
                <a class="nav-arrow" href="verify_request.php?id=<?= (int)$request_id ?>&rk=<?= urlencode($nextKey) ?>" title="Next">&rsaquo;</a>
              <?php else: ?>
                <span class="nav-arrow disabled">&rsaquo;</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="preview-body">
            <?php if ($selectedFile && !empty($selectedFile["file_path"]) && file_exists("../".$selectedFile["file_path"])): ?>
              <?php
                $path = "../".$selectedFile["file_path"];
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
              ?>
              <?php if ($ext === "pdf"): ?>
                <iframe class="preview-pdf" src="<?= h($path) ?>"></iframe>
              <?php elseif (in_array($ext, ["jpg","jpeg","png","webp"])): ?>
                <img class="preview-img" src="<?= h($path) ?>" alt="Uploaded file">
              <?php else: ?>
                <div class="preview-file">
                  <a href="<?= h($path) ?>" target="_blank"><?= h(basename($path)) ?></a>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="preview-empty">No file uploaded yet</div>
            <?php endif; ?>
          </div>

          <?php if ($rk !== $SCANNED_KEY): ?>
          <!-- Review Status for selected requirement -->
          <?php
            $activeFile = $filesByKey[$rk] ?? null;
            $activeStatus = strtoupper($activeFile["review_status"] ?? "PENDING");
            $activeReason = $activeFile["resubmit_reason"] ?? "";
          ?>
          <div class="review-section">
            <div class="review-label">Review Status</div>
            <div class="review-controls">
              <select class="review-select status-select" name="req_status[<?= h($rk) ?>]" data-key="<?= h($rk) ?>" form="verifyForm">
                <option value="PENDING"  <?= $activeStatus==="PENDING" ? "selected" : "" ?>>PENDING</option>
                <option value="VERIFIED" <?= $activeStatus==="VERIFIED" ? "selected" : "" ?>>VERIFIED</option>
                <option value="RESUBMIT" <?= $activeStatus==="RESUBMIT" ? "selected" : "" ?>>RESUBMIT</option>
              </select>
            </div>
            <div class="resubmit-reason-wrap" id="reason-<?= h($rk) ?>" style="display:<?= $activeStatus==="RESUBMIT" ? "block" : "none" ?>;">
              <textarea class="resubmit-textarea" name="resubmit_reason[<?= h($rk) ?>]" placeholder="Reason for resubmission..." maxlength="500" form="verifyForm"><?= h($activeReason) ?></textarea>
            </div>
          </div>
          <?php endif; ?>

          <!-- File Actions -->
          <div class="file-actions">
            <form method="POST" action="verify_request.php" enctype="multipart/form-data" class="upload-row">
              <?= csrf_field() ?>
              <input type="hidden" name="request_id" value="<?= (int)$request_id ?>">
              <input type="hidden" name="rk" value="<?= h($rk) ?>">
              <input type="hidden" name="action" value="upload">
              <label class="file-label">
                <input class="file-input" type="file" name="req_file" accept="image/*,application/pdf" required>
                <span class="file-label-text">Choose file...</span>
              </label>
              <button type="submit" class="btn-upload">Upload</button>
            </form>

            <form id="deleteFileForm" method="POST" action="verify_request.php">
              <?= csrf_field() ?>
              <input type="hidden" name="request_id" value="<?= (int)$request_id ?>">
              <input type="hidden" name="rk" value="<?= h($rk) ?>">
              <input type="hidden" name="action" value="delete">
              <button type="button" class="btn-delete" onclick="swalConfirmDanger('Delete File?', 'This file will be permanently removed.', 'Yes, delete', function(){ document.getElementById('deleteFileForm').submit(); })">Delete File</button>
            </form>
          </div>

        </div>
      </div>

    </main>
  </div>
</div>

<div class="footer-bar"></div>

<script>
function toggleSidebar(){
  const sb = document.getElementById('sidebar');
  if(!sb) return;
  if (window.innerWidth <= 720) {
    sb.style.display = (sb.style.display === 'none' || sb.style.display === '') ? 'block' : 'none';
  }
}

// Show/hide resubmit reason textarea
document.querySelectorAll(".status-select").forEach(function(select) {
  select.addEventListener("change", function() {
    var key = this.getAttribute("data-key");
    var reasonWrap = document.getElementById("reason-" + key);
    if (reasonWrap) {
      reasonWrap.style.display = (this.value === "RESUBMIT") ? "block" : "none";
    }
  });
});

// File input label
document.querySelectorAll('.file-input').forEach(function(input) {
  input.addEventListener('change', function() {
    var label = this.closest('.file-label').querySelector('.file-label-text');
    if (this.files.length > 0) {
      label.textContent = this.files[0].name;
    }
  });
});
</script>
</body>
</html>