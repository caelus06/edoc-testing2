<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_REGISTRAR);

$registrar_id = (int)$_SESSION["user_id"];

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
    if (str_contains($t, "GRAD")) return "Graduate";
    if (str_contains($t, "NOT"))  return "Not-Graduate";
    if (str_contains($t, "BACHELOR") || str_contains($t, "UNDERGRAD")) return "Not-Graduate";
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

if ($request_id <= 0) die("Missing request id.");
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
if (!$reqRow) die("Request not found.");

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
    $app_status = normalize_app_status($_POST["app_status"] ?? "");

    // 1. Update Requirements ONLY IF changed
        if (is_array($req_status)) {
            foreach ($req_status as $key => $val) {
                $key = trim((string)$key);
                $val = strtoupper(trim((string)$val));
                if ($key === "" || $key === $SCANNED_KEY) continue;

                $row = $conn->prepare("SELECT id, verified_at FROM request_files WHERE request_id=? AND requirement_key=? LIMIT 1");
                $row->bind_param("is", $request_id, $key);
                $row->execute();
                $rf = $row->get_result()->fetch_assoc();
                if (!$rf) continue;

              // is currently verified
               $isCurrentlyVerified = !empty($rf["verified_at"]);

                // Handle Status Transitions
                if ($val === "VERIFIED" && !$isCurrentlyVerified) {
                  // Change from Not Verified to Verified
                    $up = $conn->prepare("
                      UPDATE request_files 
                      SET verified_at=NOW(), verified_by=?  
                      WHERE request_id=? AND requirement_key=? 
                    ");
                    $up->bind_param("iis", $registrar_id, $request_id, $key);
                    $up->execute();
                    add_log($conn, $request_id, "Registrar Update: " . ucfirst($key) . " has been verified");
                } elseif ($val === "RESUBMIT" && $isCurrentlyVerified) {
                    // Downgrade from Verified to Resubmit
                    $up = $conn->prepare("
                      UPDATE request_files 
                      SET verified_at=NULL, verified_by=NULL 
                      WHERE request_id=? AND requirement_key=?
                    ");
                    $up->bind_param("is", $request_id, $key);
                    $up->execute();
                    add_log($conn, $request_id, "Registrar Update: Resubmission required for " . ucfirst($key));
                  } elseif ($val === "PENDING" && $isCurrentlyVerified) {
                    // Downgrade from Verified to Pending
                    $up = $conn->prepare("
                      UPDATE request_files
                      SET verified_at=NULL, verified_by=NULL
                      WHERE request_id=? AND requirement_key=?
                    ");
                    $up->bind_param("is", $request_id, $key);
                    $up->execute();
                    add_log($conn, $request_id, "Registrar Update: " . ucfirst($key) . " returned to Pending");
                  }
                  // Note: If $val matches the current state (Verified -> Verified, etc.), no code executes = no duplicate logs.
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
                add_log($conn, $request_id, "Registrar Update: Application status updated to " . $app_status);
            }
        }
        redirect_back($request_id, $rk);
    }

  // DELETE
  if ($action === "delete") {
    $st = $conn->prepare("SELECT id, file_path, verified_at FROM request_files WHERE request_id=? AND requirement_key=? LIMIT 1");
    $st->bind_param("is", $request_id, $rk);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    if (!$row) die("No file found for this item. Nothing to delete.");

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
    redirect_back($request_id, $rk);
  }

  // UPLOAD (registrar overwrites preview file)
  if ($action === "upload") {
    if (!isset($_FILES["req_file"]) || $_FILES["req_file"]["error"] !== UPLOAD_ERR_OK) {
      die("No file uploaded.");
    }

    $chk = $conn->prepare("SELECT id, file_path, verified_at FROM request_files WHERE request_id=? AND requirement_key=? LIMIT 1");
    $chk->bind_param("is", $request_id, $rk);
    $chk->execute();
    $ex = $chk->get_result()->fetch_assoc();

    // Optional lock: lock overwrite if verified
    if ($ex && !empty($ex["verified_at"])) die("This file is VERIFIED. Upload is locked.");

    $tmp = $_FILES["req_file"]["tmp_name"];
    $size = (int)$_FILES["req_file"]["size"];
    if ($size > 15 * 1024 * 1024) die("Max 15MB only.");

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    $allowed = [
      "image/jpeg" => "jpg",
      "image/png"  => "png",
      "image/webp" => "webp",
      "application/pdf" => "pdf"
    ];
    if (!isset($allowed[$mime])) die("Only JPG/PNG/WebP/PDF allowed.");

    $ext = $allowed[$mime];
    $dir = "../uploads/request_files/" . $request_id;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $fname = $rk . "_" . bin2hex(random_bytes(8)) . "." . $ext;
    $dest = $dir . "/" . $fname;

    if (!move_uploaded_file($tmp, $dest)) die("Upload failed.");
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
    redirect_back($request_id, $rk);
  }

  die("Invalid action.");
  
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Request</title>
  <link rel="stylesheet" href="../assets/css/verify_request.css">
  <style>
    /* safe additions (won't break your existing css) */
    .preview-pdf {
      width: 100%;
      height: 520px;
      border: none;
      border-radius: 12px;
      background: #fff;
    }
  </style>
</head>
<body>

<header class="topbar">
  <a href="request_management.php" class="back-btn" title="Go Back">←</a>
</header>

<main class="verify-wrapper">

  <!-- LEFT (FORM) -->
  <form id="verifyForm" class="verify-left" method="POST" action="verify_request.php">
    <?= csrf_field() ?>
    <input type="hidden" name="request_id" value="<?= (int)$request_id ?>">
    <input type="hidden" name="rk" value="<?= h($rk) ?>">

    <div class="verify-title">Verify Request</div>
    <div class="verify-sub">Track and view the status of the document request</div>

    <div class="block">
      <div class="block-title">Requestor Information</div>
      <div><b>Name:</b> <?= h($fullName) ?></div>
      <div><b>ID Number:</b> <?= h($reqRow["student_id"] ?: "N/A") ?></div>
      <div><b>Email:</b> <?= h($reqRow["email"] ?: "N/A") ?></div>
      <div><b>Contact Number:</b> <?= h($reqRow["contact_number"] ?: "N/A") ?></div>
      <div><b>Course/Program:</b> <?= h($reqRow["course"] ?: "N/A") ?></div>
      <div><b>Major:</b> <?= h($reqRow["major"] ?: "N/A") ?></div>
      <div><b>Year Graduated:</b> <?= h($reqRow["year_graduated"] ?: "N/A") ?></div>
      <div>
        <b>Account Status:</b>
        <?php if ($accountStatus === "VERIFIED"): ?>
          <span class="pill pill-green">✓ VERIFIED</span>
        <?php else: ?>
          <span class="pill pill-gray">PENDING</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="block">
      <div class="block-title">Document Request Status</div>
      <div><b>Reference Number:</b> <?= h($reqRow["reference_no"]) ?></div>
      <div><b>Document:</b> <?= h(strtoupper($doc_type_raw)) ?></div>
      <div><b>Title Type:</b> <?= h($title_type_raw ?: "N/A") ?></div>
      <div><b>Number of Copies:</b> <?= (int)$reqRow["copies"] ?></div>
      <div><b>Requested on:</b> <?= h(date("F j, Y", strtotime($reqRow["created_at"] ?? "now"))) ?></div>
      <div><b>Last Updated:</b> <?= h(date("F j, Y", strtotime($reqRow["updated_at"] ?? ($reqRow["created_at"] ?? "now")))) ?></div>
    </div>

    <div class="block">
      <div class="block-title">Requirements Status</div>

      <?php foreach ($reqs as $r): ?>
        <?php
          $key = (string)$r["requirement_key"];
          $row = $filesByKey[$key] ?? null;
          $isVerified = ($row && !empty($row["verified_at"]));
          $statusVal = $isVerified ? "VERIFIED" : "PENDING";
        ?>
        <div class="req-row">
          <div class="req-name"><?= h($r["req_name"]) ?></div>

          <select class="req-select" name="req_status[<?= h($key) ?>]">
            <option value="PENDING"  <?= $statusVal==="PENDING" ? "selected" : "" ?>>PENDING</option>
            <option value="VERIFIED" <?= $statusVal==="VERIFIED" ? "selected" : "" ?>>VERIFIED</option>
            <option value="RESUBMIT">RESUBMIT</option>
          </select>

          <a class="req-view" href="verify_request.php?id=<?= (int)$request_id ?>&rk=<?= urlencode($key) ?>">View</a>
        </div>
      <?php endforeach; ?>

      <div class="block-title" style="margin-top:14px;">Application Status</div>
      <div class="app-row">
        <select class="app-select" name="app_status">
          <?php
            $app = normalize_app_status($reqRow["status"] ?? "PENDING");
            $opts = ["PENDING","APPROVED","PROCESSING","READY FOR PICKUP","COMPLETED"];
            foreach ($opts as $o):
          ?>
            <option value="<?= h($o) ?>" <?= $app===$o ? "selected" : "" ?>>
              <?= ($o==="COMPLETED") ? 'APPLICATION STATUS "COMPLETE"' : ''.$o?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="app-help">Application Status: Approved, Processing, Ready for Pickup, and Complete.</div>
      </div>

      <div class="note">
        NOTE!!! Once the Application Status is <b>COMPLETED</b> scanned the document and upload it here
      </div>

      <!-- ✅ NEW: SCANNED DOCUMENT line -->
      <div class="req-row" style="margin-top:10px;">
        <div class="req-name"><b>SCANNED DOCUMENT</b></div>
        <div style="flex:1;"></div>
        <a class="req-view" href="verify_request.php?id=<?= (int)$request_id ?>&rk=<?= urlencode($SCANNED_KEY) ?>">View</a>
      </div>
    </div>

    <input type="hidden" name="action" value="save">
  </form>

  <!-- RIGHT -->
  <section class="verify-right">
    <div class="preview-card">
      <div class="preview-title">
        <?php
          if ($rk === $SCANNED_KEY) {
            echo "SCANNED DOCUMENT";
          } else {
            $shownTitle = null;
            foreach ($reqs as $r) {
              if ($r["requirement_key"] === $rk) { $shownTitle = $r["req_name"]; break; }
            }
            echo h(strtoupper($shownTitle ?: $rk));
          }
        ?>
      </div>

      <div class="preview-body">
        <?php if ($selectedFile && !empty($selectedFile["file_path"]) && file_exists("../".$selectedFile["file_path"])): ?>
          <?php
            $path = "../".$selectedFile["file_path"];
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
          ?>

          <?php if ($ext === "pdf"): ?>
            <!-- PDF preview -->
            <iframe class="preview-pdf" src="<?= h($path) ?>"></iframe>
            <div style="margin-top:8px;">
              <!-- need to fix position <a href="<?= h($path) ?>" target="_blank">Open PDF in new tab</a> -->
            </div>
          <?php elseif (in_array($ext, ["jpg","jpeg","png","webp"])): ?>
            <img class="preview-img" src="<?= h($path) ?>" alt="Uploaded file">
          <?php else: ?>
            <div class="preview-file">
              <b>Uploaded File:</b>
              <a href="<?= h($path) ?>" target="_blank"><?= h(basename($path)) ?></a>
            </div>
          <?php endif; ?>

        <?php else: ?>
          <div class="preview-empty">No file uploaded yet.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="right-actions">
      <!-- DELETE -->
      <form method="POST" action="verify_request.php" class="mini-form">
        <?= csrf_field() ?>
        <input type="hidden" name="request_id" value="<?= (int)$request_id ?>">
        <input type="hidden" name="rk" value="<?= h($rk) ?>">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn-delete" onclick="return confirm('Delete this file?');">DELETE</button>
      </form>

      <!-- UPLOAD -->
      <form method="POST" action="verify_request.php" enctype="multipart/form-data" class="mini-form upload-form">
        <?= csrf_field() ?>
        <input type="hidden" name="request_id" value="<?= (int)$request_id ?>">
        <input type="hidden" name="rk" value="<?= h($rk) ?>">
        <input type="hidden" name="action" value="upload">
        <input class="file-input" type="file" name="req_file" accept="image/*,application/pdf" required>
        <button type="submit" class="btn-upload">UPLOAD</button>
      </form>

      <div class="arrows">
        <?php if ($prevKey): ?>
          <a class="arrow" href="verify_request.php?id=<?= (int)$request_id ?>&rk=<?= urlencode($prevKey) ?>">&lt;</a>
        <?php else: ?>
          <span class="arrow disabled">&lt;</span>
        <?php endif; ?>

        <?php if ($nextKey): ?>
          <a class="arrow" href="verify_request.php?id=<?= (int)$request_id ?>&rk=<?= urlencode($nextKey) ?>">&gt;</a>
        <?php else: ?>
          <span class="arrow disabled">&gt;</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="bottom-actions">
      <button class="btn-save" type="submit" form="verifyForm">SAVE</button>
      <a class="btn-cancel" href="request_management.php">CANCEL</a>
    </div>
  </section>

</main>
</body>
</html>