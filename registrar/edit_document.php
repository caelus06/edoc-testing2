<?php
// registrar/edit_document.php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_REGISTRAR);

$registrarId = (int)$_SESSION["user_id"];
$registrarName = "Registrar";
$me = $conn->prepare("SELECT first_name, last_name FROM users WHERE id=? LIMIT 1");
$me->bind_param("i", $registrarId);
$me->execute();
$mr = $me->get_result()->fetch_assoc();
if ($mr) $registrarName = trim(($mr["first_name"] ?? "") . " " . ($mr["last_name"] ?? ""));

// Get document ID
$docId = (int)($_GET["id"] ?? 0);
if ($docId <= 0) { header("Location: document_management.php"); exit(); }

// Fetch document
$docStmt = $conn->prepare("SELECT * FROM document_process WHERE id=? LIMIT 1");
$docStmt->bind_param("i", $docId);
$docStmt->execute();
$doc = $docStmt->get_result()->fetch_assoc();
if (!$doc) { header("Location: document_management.php"); exit(); }

$docType = $doc["document_type"];

// Fetch all titles (distinct title_type) for this document
$titlesStmt = $conn->prepare("SELECT DISTINCT title_type FROM requirements_master WHERE document_type=? ORDER BY title_type ASC");
$titlesStmt->bind_param("s", $docType);
$titlesStmt->execute();
$titlesResult = $titlesStmt->get_result();
$titles = [];
while ($row = $titlesResult->fetch_assoc()) {
  $titles[] = $row["title_type"];
}

// Fetch requirements grouped by title
$reqStmt = $conn->prepare("SELECT * FROM requirements_master WHERE document_type=? ORDER BY title_type ASC, id ASC");
$reqStmt->bind_param("s", $docType);
$reqStmt->execute();
$allReqs = $reqStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group requirements by title_type
$reqsByTitle = [];
foreach ($allReqs as $req) {
  $reqsByTitle[$req["title_type"]][] = $req;
}

// process_html stores either raw HTML (legacy) or our JSON envelope.
$rawProcessHtml = $doc["process_html"] ?? "";
$processData    = [];

$decoded = json_decode($rawProcessHtml, true);
if (is_array($decoded)) {
  $processData = $decoded;
} else {
  if (trim($rawProcessHtml) !== "") {
    $processData["application_process"][] = [
      "id"      => uniqid("ap_"),
      "details" => trim(strip_tags($rawProcessHtml))
    ];
  }
  $migratedJson = json_encode($processData);
  $migStmt = $conn->prepare("UPDATE document_process SET process_html=? WHERE id=?");
  $migStmt->bind_param("si", $migratedJson, $docId);
  $migStmt->execute();
  $doc["process_html"] = $migratedJson;
}

$reminders    = $processData["reminders"]           ?? [];
$appProcesses = $processData["application_process"] ?? [];

$message = "";
$messageType = "";

// ─── AJAX / POST HANDLERS ───────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_verify();
  $action = $_POST["action"] ?? "";
  header("Content-Type: application/json");

  function getFreshProcessData($conn, $docId) {
    $s = $conn->prepare("SELECT process_html FROM document_process WHERE id=? LIMIT 1");
    $s->bind_param("i", $docId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $raw = $row["process_html"] ?? "";
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
  }

  // ── Document Name / Processing Time ────────────────────────────────────────
  if ($action === "update_document") {
    $newName = trim($_POST["document_name"] ?? "");
    $newTime = trim($_POST["processing_time"] ?? "");
    if ($newName && $newTime) {
      $upd = $conn->prepare("UPDATE document_process SET document_type=?, working_days=?, last_updated=NOW(), updated_by=? WHERE id=?");
      $upd->bind_param("ssii", $newName, $newTime, $registrarId, $docId);
      $upd->execute();
      audit_log($conn, "UPDATE", "document_process", $docId, "Renamed document to: " . $newName);
      echo json_encode(["success" => true, "document_name" => $newName, "processing_time" => $newTime]);
    } else {
      echo json_encode(["success" => false, "error" => "All fields required."]);
    }
    exit();
  }

// ── Add Title ───────────────────────────────────────────────────────────────
if ($action === "add_title") {
    $titleName = trim($_POST["title_name"] ?? "");
    if ($titleName) {
        // We insert a "placeholder" requirement so the title exists in the requirements_master table
        // This is necessary because your schema seems to derive "titles" from this table
        $stmt = $conn->prepare("INSERT INTO requirements_master (document_type, title_type, req_name, requirement_key) VALUES (?, ?, '__placeholder__', 'placeholder')");
        $stmt->bind_param("ss", $docType, $titleName);
        
        if ($stmt->execute()) {
            audit_log($conn, "INSERT", "requirements_master", $conn->insert_id, "Added new title group: " . $titleName);
            echo json_encode(["success" => true, "title_name" => $titleName]);
        } else {
            echo json_encode(["success" => false, "error" => "Database error."]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Title name required."]);
    }
    exit();
}

  // ── Edit Title ──────────────────────────────────────────────────────────────
  if ($action === "edit_title") {
    $oldTitle = trim($_POST["old_title"] ?? "");
    $newTitle = trim($_POST["new_title"] ?? "");
    if ($oldTitle && $newTitle) {
      $upd = $conn->prepare("UPDATE requirements_master SET title_type=? WHERE document_type=? AND title_type=?");
      $upd->bind_param("sss", $newTitle, $docType, $oldTitle);
      $upd->execute();
      audit_log($conn, "UPDATE", "requirements_master", null, "Renamed title '" . $oldTitle . "' to '" . $newTitle . "' in " . $docType);
      $processData2 = getFreshProcessData($conn, $docId);
      foreach (($processData2["reminders"] ?? []) as &$r) {
        if (($r["title_type"] ?? "") === $oldTitle) $r["title_type"] = $newTitle;
      }
      $newJson = json_encode($processData2);
      $updDoc = $conn->prepare("UPDATE document_process SET process_html=?, last_updated=NOW(), updated_by=? WHERE id=?");
      $updDoc->bind_param("sii", $newJson, $registrarId, $docId);
      $updDoc->execute();
      echo json_encode(["success" => true, "new_title" => $newTitle]);
    } else {
      echo json_encode(["success" => false, "error" => "Title names required."]);
    }
    exit();
  }

  // ── Delete Title ────────────────────────────────────────────────────────────
  if ($action === "delete_title") {

  $titleName = trim($_POST["title_name"] ?? "");

  if ($titleName) {

    // Delete requirements under this title
    $del = $conn->prepare("DELETE FROM requirements_master WHERE document_type=? AND title_type=?");
    $del->bind_param("ss", $docType, $titleName);
    $del->execute();
    audit_log($conn, "DELETE", "requirements_master", null, "Deleted title '" . $titleName . "' from " . $docType);

    // Remove reminders linked to this title
    $pData = getFreshProcessData($conn, $docId);

    if (!empty($pData["reminders"])) {
      $pData["reminders"] = array_values(array_filter(
        $pData["reminders"],
        fn($r) => ($r["title_type"] ?? "") !== $titleName
      ));
    }

    // Save updated JSON
    $newJson = json_encode($pData);
    $upd = $conn->prepare("UPDATE document_process SET process_html=?, last_updated=NOW(), updated_by=? WHERE id=?");
    $upd->bind_param("sii", $newJson, $registrarId, $docId);
    $upd->execute();

    echo json_encode(["success" => true]);

  } else {
    echo json_encode(["success" => false, "error" => "Title name required."]);
  }

  exit();
}

  // ── Add Requirement ─────────────────────────────────────────────────────────
  if ($action === "add_requirement") {
  $titleType = trim($_POST["title_type"] ?? "");
  $reqName   = trim($_POST["req_name"] ?? "");
  $reqKey    = trim($_POST["req_key"] ?? strtolower(str_replace(" ", "_", $reqName)));

  if ($titleType && $reqName) {

    // REMOVE placeholder if it exists
    $conn->query("DELETE FROM requirements_master 
                  WHERE document_type='$docType' 
                  AND title_type='$titleType' 
                  AND req_name='__placeholder__'");

    $ins = $conn->prepare("
      INSERT INTO requirements_master 
      (document_type, title_type, req_name, requirement_key) 
      VALUES (?, ?, ?, ?)
    ");
    $ins->bind_param("ssss", $docType, $titleType, $reqName, $reqKey);
    $ins->execute();

    $newId = $conn->insert_id;
    audit_log($conn, "INSERT", "requirements_master", $newId, "Added requirement: " . $reqName . " under " . $titleType);

    echo json_encode([
      "success" => true,
      "id" => $newId,
      "req_name" => $reqName,
      "title_type" => $titleType,
      "req_key" => $reqKey
    ]);
  } else {
    echo json_encode(["success" => false, "error" => "All fields required."]);
  }
  exit();
}

  // ── Edit Requirement ────────────────────────────────────────────────────────
  if ($action === "edit_requirement") {
    $reqId   = (int)($_POST["req_id"] ?? 0);
    $reqName = trim($_POST["req_name"] ?? "");
    $reqKey  = trim($_POST["req_key"] ?? "");
    if ($reqId && $reqName) {
      $upd = $conn->prepare("UPDATE requirements_master SET req_name=?, requirement_key=? WHERE id=?");
      $upd->bind_param("ssi", $reqName, $reqKey, $reqId);
      $upd->execute();
      audit_log($conn, "UPDATE", "requirements_master", $reqId, "Updated requirement: " . $reqName);
      echo json_encode(["success" => true, "req_name" => $reqName]);
    } else {
      echo json_encode(["success" => false, "error" => "Fields required."]);
    }
    exit();
  }

  // ── Delete Requirement ──────────────────────────────────────────────────────
  if ($action === "delete_requirement") {
    $reqId = (int)($_POST["req_id"] ?? 0);
    if ($reqId) {
      $del = $conn->prepare("DELETE FROM requirements_master WHERE id=?");
      $del->bind_param("i", $reqId);
      $del->execute();
      audit_log($conn, "DELETE", "requirements_master", $reqId, "Deleted requirement #" . $reqId);
      echo json_encode(["success" => true]);
    } else {
      echo json_encode(["success" => false, "error" => "ID required."]);
    }
    exit();
  }

  // ── Add Reminder ────────────────────────────────────────────────────────────
  if ($action === "add_reminder") {
    $titleType = trim($_POST["title_type"] ?? "");
    $details   = trim($_POST["details"] ?? "");
    if ($details) {
      $pData = getFreshProcessData($conn, $docId);
      $pData["reminders"][] = ["id" => uniqid("r_"), "title_type" => $titleType, "details" => $details];
      $newJson = json_encode($pData);
      $upd = $conn->prepare("UPDATE document_process SET process_html=?, last_updated=NOW(), updated_by=? WHERE id=?");
      $upd->bind_param("sii", $newJson, $registrarId, $docId);
      $upd->execute();
      audit_log($conn, "INSERT", "document_process", $docId, "Added reminder for " . ($titleType ?: "general"));
      $added = end($pData["reminders"]);
      echo json_encode(["success" => true, "reminder" => $added]);
    } else {
      echo json_encode(["success" => false, "error" => "Details required."]);
    }
    exit();
  }

  // ── Edit Reminder ───────────────────────────────────────────────────────────
  if ($action === "edit_reminder") {
    $remId     = $_POST["rem_id"] ?? "";
    $titleType = trim($_POST["title_type"] ?? "");
    $details   = trim($_POST["details"] ?? "");
    if ($remId && $details) {
      $pData = getFreshProcessData($conn, $docId);
      foreach ($pData["reminders"] as &$r) {
        if ($r["id"] === $remId) { $r["title_type"] = $titleType; $r["details"] = $details; }
      }
      $newJson = json_encode($pData);
      $upd = $conn->prepare("UPDATE document_process SET process_html=?, last_updated=NOW(), updated_by=? WHERE id=?");
      $upd->bind_param("sii", $newJson, $registrarId, $docId);
      $upd->execute();
      audit_log($conn, "UPDATE", "document_process", $docId, "Edited reminder");
      echo json_encode(["success" => true, "details" => $details, "title_type" => $titleType]);
    } else {
      echo json_encode(["success" => false, "error" => "Fields required."]);
    }
    exit();
  }

  // ── Delete Reminder ─────────────────────────────────────────────────────────
  if ($action === "delete_reminder") {
    $remId = $_POST["rem_id"] ?? "";
    if ($remId) {
      $pData = getFreshProcessData($conn, $docId);
      $pData["reminders"] = array_values(array_filter($pData["reminders"] ?? [], fn($r) => $r["id"] !== $remId));
      $newJson = json_encode($pData);
      $upd = $conn->prepare("UPDATE document_process SET process_html=?, last_updated=NOW(), updated_by=? WHERE id=?");
      $upd->bind_param("sii", $newJson, $registrarId, $docId);
      $upd->execute();
      audit_log($conn, "DELETE", "document_process", $docId, "Deleted reminder");
      echo json_encode(["success" => true]);
    } else {
      echo json_encode(["success" => false, "error" => "ID required."]);
    }
    exit();
  }

  // ── Add Application Process ─────────────────────────────────────────────────
  if ($action === "add_app_process") {
    $details = trim($_POST["details"] ?? "");
    if ($details) {
      $pData = getFreshProcessData($conn, $docId);
      $pData["application_process"][] = ["id" => uniqid("ap_"), "details" => $details];
      $newJson = json_encode($pData);
      $upd = $conn->prepare("UPDATE document_process SET process_html=?, last_updated=NOW(), updated_by=? WHERE id=?");
      $upd->bind_param("sii", $newJson, $registrarId, $docId);
      $upd->execute();
      audit_log($conn, "INSERT", "document_process", $docId, "Added application process step");
      $added = end($pData["application_process"]);
      echo json_encode(["success" => true, "app_process" => $added]);
    } else {
      echo json_encode(["success" => false, "error" => "Details required."]);
    }
    exit();
  }

  // ── Edit Application Process ────────────────────────────────────────────────
  if ($action === "edit_app_process") {
    $apId    = $_POST["ap_id"] ?? "";
    $details = trim($_POST["details"] ?? "");
    if ($apId && $details) {
      $pData = getFreshProcessData($conn, $docId);
      foreach ($pData["application_process"] as &$ap) {
        if ($ap["id"] === $apId) { $ap["details"] = $details; }
      }
      $newJson = json_encode($pData);
      $upd = $conn->prepare("UPDATE document_process SET process_html=?, last_updated=NOW(), updated_by=? WHERE id=?");
      $upd->bind_param("sii", $newJson, $registrarId, $docId);
      $upd->execute();
      audit_log($conn, "UPDATE", "document_process", $docId, "Edited application process step");
      echo json_encode(["success" => true, "details" => $details]);
    } else {
      echo json_encode(["success" => false, "error" => "Fields required."]);
    }
    exit();
  }

  // ── Delete Application Process ──────────────────────────────────────────────
  if ($action === "delete_app_process") {
    $apId = $_POST["ap_id"] ?? "";
    if ($apId) {
      $pData = getFreshProcessData($conn, $docId);
      $pData["application_process"] = array_values(array_filter($pData["application_process"] ?? [], fn($ap) => $ap["id"] !== $apId));
      $newJson = json_encode($pData);
      $upd = $conn->prepare("UPDATE document_process SET process_html=?, last_updated=NOW(), updated_by=? WHERE id=?");
      $upd->bind_param("sii", $newJson, $registrarId, $docId);
      $upd->execute();
      audit_log($conn, "DELETE", "document_process", $docId, "Deleted application process step");
      echo json_encode(["success" => true]);
    } else {
      echo json_encode(["success" => false, "error" => "ID required."]);
    }
    exit();
  }

  // ── Delete Entire Document ───────────────────────────────────────────────────
  if ($action === "delete_document") {
    $d1 = $conn->prepare("DELETE FROM requirements_master WHERE document_type=?");
    $d1->bind_param("s", $docType);
    $d1->execute();
    $d2 = $conn->prepare("DELETE FROM document_process WHERE id=?");
    $d2->bind_param("i", $docId);
    $d2->execute();
    audit_log($conn, "DELETE", "document_process", $docId, "Deleted entire document type: " . $docType);
    echo json_encode(["success" => true]);
    exit();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Document</title>
  <link rel="stylesheet" href="../assets/css/edit_document.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
</head>
<body>

<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sb-user">
      <div class="avatar">&#128100;</div>
      <div class="meta">
        <div class="name"><?= h($registrarName) ?></div>
        <div class="role">Registrar</div>
      </div>
    </div>
    <div class="sb-section-title">MODULES</div>
    <nav class="sb-nav">
      <a class="sb-item" href="dashboard.php"><span class="sb-icon">&#127968;</span>Dashboard</a>
      <a class="sb-item" href="new_document_request.php"><span class="sb-icon">&#128221;</span>New Document Request</a>
      <a class="sb-item" href="request_management.php"><span class="sb-icon">&#128269;</span>Request Management</a>
      <a class="sb-item" href="track_progress.php"><span class="sb-icon">&#128205;</span>Track Progress</a>
      <a class="sb-item active" href="document_management.php"><span class="sb-icon">&#128196;</span>Document Management</a>
      <a class="sb-item" href="create_document.php"><span class="sb-icon">&#10133;</span>Create Document</a>
      <a class="sb-item" href="non_compliant.php"><span class="sb-icon">&#9888;</span>Non-Compliant Users</a>
    </nav>
    <div class="sb-section-title">SETTINGS</div>
    <nav class="sb-nav">
      <a class="sb-item" href="../mis/system_settings.php"><span class="sb-icon">&#9881;</span>System Settings</a>
      <a class="sb-item" href="#" onclick="event.preventDefault(); swalConfirm('Logout', 'Are you sure you want to log out?', 'Yes, log out', function(){ window.location='../auth/logout.php'; })"><span class="sb-icon">&#9099;</span>Logout</a>
    </nav>
  </aside>

  <div class="main">
    <!-- TOPBAR -->
    <header class="topbar">
      <button class="hamburger" type="button" onclick="toggleSidebar()">&#9776;</button>
      <div class="brand">
        <div class="logo"><img src="../assets/img/edoc-logo.jpeg" alt="E-Doc"></div>
        <div>E-Doc Document Requesting System</div>
      </div>
    </header>

    <div class="page-wrap">
      <div class="container">

        <!-- ─── DOCUMENT NAME + PROCESSING TIME HEADER ─── -->
        <div class="doc-header-panel">

          <div class="doc-header-row">
            <span class="doc-field-label">Document Name:</span>
            <span class="doc-field-display" id="disp-doc-name"><?= h($doc['document_type']) ?></span>
            <div class="doc-header-actions">
              <button class="btn-inline" onclick="openEditDocName()">&#9998; EDIT</button>
              <button class="btn-inline" onclick="confirmDelete('clear_field','doc-name')">&#128465; DEL</button>
            </div>
            <button class="btn-delete-doc" onclick="confirmDelete('document')">DELETE &times;</button>
          </div>

          <div class="doc-header-row">
            <span class="doc-field-label">Processing Time:</span>
            <span class="doc-field-display" id="disp-proc-time"><?= h($doc['working_days']) ?></span>
            <div class="doc-header-actions">
              <button class="btn-inline" onclick="openEditProcTime()">&#9998; EDIT</button>
              <button class="btn-inline" onclick="confirmDelete('clear_field','proc-time')">&#128465; DEL</button>
            </div>
          </div>

        </div><!-- /doc-header-panel -->

        <!-- ─── TITLES ─── -->
        <div class="section-block">
          <div class="section-block-header">
            <span class="section-block-title">Titles</span>
            <button class="btn-add-section" onclick="openModal('title')">ADD +</button>
          </div>
          <div class="section-block-body">
            <div class="titles-area" id="title-list">
              <?php if (empty($titles)): ?>
                <span class="empty-msg" id="title-empty">No titles added yet.</span>
              <?php else: ?>
                <?php foreach ($titles as $t): ?>
                  <span class="title-pill" data-title="<?= h($t) ?>">
                    <span class="title-pill-name"><?= h($t) ?></span>
                    <button class="title-pill-btn title-pill-edit"
                            onclick="openEditTitle(this.closest('.title-pill').dataset.title)"
                            title="Edit">&#9998;</button>
                    <button class="title-pill-btn"
                            onclick="confirmDelete('title', null, this.closest('.title-pill').dataset.title)"
                            title="Delete">&times;</button>
                  </span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- ─── REQUIREMENTS ─── -->
        <div class="section-block">
          <div class="section-block-header">
            <span class="section-block-title">Requirements</span>
            <button class="btn-add-section" onclick="openModal('requirement')">ADD +</button>
          </div>
          <div class="section-block-body" id="req-list">
            <?php
            $hasReqs = false;
            foreach ($reqsByTitle as $titleKey => $reqs):
              $realReqs = $reqs;
              if (empty($realReqs)) continue;
              $hasReqs = true;
            ?>
              <div class="req-group" data-group="<?= h($titleKey) ?>">
                <div class="req-group-header"><?= h($titleKey) ?></div>
                <?php foreach ($realReqs as $req): ?>
                  <div class="req-item"
                       data-req-id="<?= $req['id'] ?>"
                       data-req-title="<?= h($req['title_type']) ?>"
                       data-req-name="<?= h($req['req_name']) ?>"
                       data-req-key="<?= h($req['requirement_key']) ?>">
                    <div class="req-item-bullet"><?= h($req['req_name']) ?></div>
                    <div class="req-item-actions">
                      <button class="btn-row-edit" onclick="openEditReqFromEl(this)">&#9998; EDIT</button>
                      <button class="btn-row-del"  onclick="confirmDelete('requirement', parseInt(this.closest('.req-item').dataset.reqId))">&#128465; DEL</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
            <?php if (!$hasReqs): ?>
              <div class="empty-msg" id="req-empty">No requirements added yet.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ─── REMINDER ─── -->
        <div class="section-block">
          <div class="section-block-header">
            <span class="section-block-title">REMINDER</span>
            <button class="btn-add-section" onclick="openModal('reminder')">ADD +</button>
          </div>
          <div class="section-block-body" id="reminder-list">
            <?php if (empty($reminders)): ?>
              <div class="empty-msg" id="rem-empty">No reminders added yet.</div>
            <?php else: ?>
              <?php foreach ($reminders as $rem): ?>
                <div class="info-card"
                     data-rem-id="<?= h($rem['id']) ?>"
                     data-rem-title="<?= h($rem['title_type'] ?? '') ?>"
                     data-rem-details="<?= h($rem['details']) ?>">
                  <?php if (!empty($rem['title_type'])): ?>
                    <div class="info-card-header"><?= h($rem['title_type']) ?></div>
                  <?php endif; ?>
                  <div class="info-card-body">
                    <div class="info-card-text"><?= h($rem['details']) ?></div>
                    <div class="info-card-actions">
                      <button class="btn-row-edit" onclick="openEditReminderFromEl(this)">&#9998; EDIT</button>
                      <button class="btn-row-del"  onclick="confirmDelete('reminder', null, null, this.closest('.info-card').dataset.remId)">&#128465; DEL</button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- ─── APPLICATION PROCESS ─── -->
        <div class="section-block">
          <div class="section-block-header">
            <span class="section-block-title">Application Process</span>
            <button class="btn-add-section" onclick="openModal('app_process')">ADD +</button>
          </div>
          <div class="section-block-body" id="appprocess-list">
            <?php if (empty($appProcesses)): ?>
              <div class="empty-msg" id="ap-empty">No application process added yet.</div>
            <?php else: ?>
              <?php foreach ($appProcesses as $ap): ?>
                <div class="info-card"
                     data-ap-id="<?= h($ap['id']) ?>"
                     data-ap-details="<?= h($ap['details']) ?>">
                  <div class="info-card-body">
                    <div class="info-card-text"><?= h($ap['details']) ?></div>
                    <div class="info-card-actions">
                      <button class="btn-row-edit ap-edit-btn" onclick="openEditApFromEl(this)">&#9998; EDIT</button>
                      <button class="btn-row-del  ap-del-btn"  onclick="confirmDelete('app_process', null, null, null, this.closest('.info-card').dataset.apId)">&#128465; DEL</button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /container -->
    </div><!-- /page-wrap -->

    <!-- ─── FIXED BOTTOM BAR ─── -->
    <div class="bottom-bar">
      <button class="btn-save-changes" onclick="saveDocInfo()">SAVE CHANGES</button>
      <a href="document_management.php" class="btn-close">CLOSE</a>
    </div>

  </div><!-- /main -->
</div><!-- /layout -->


<!-- ═══════════════════ MODALS ═══════════════════ -->

<!-- Edit Document Name -->
<div class="modal-backdrop" id="modal-edit-docname">
  <div class="modal-box">
    <div class="modal-title">Edit Document Name</div>
    <div class="modal-form">
      <div class="form-group">
        <label>Document Name</label>
        <input type="text" id="modal-doc-name" placeholder="e.g., Admission/Enrollment">
      </div>
      <div class="modal-actions">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('edit-docname')">Cancel</button>
        <button class="modal-btn modal-btn-primary" onclick="saveDocName()">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Processing Time -->
<div class="modal-backdrop" id="modal-edit-proctime">
  <div class="modal-box">
    <div class="modal-title">Edit Processing Time</div>
    <div class="modal-form">
      <div class="form-group">
        <label>Processing Time</label>
        <input type="text" id="modal-proc-time" placeholder="e.g., Within the Day">
      </div>
      <div class="modal-actions">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('edit-proctime')">Cancel</button>
        <button class="modal-btn modal-btn-primary" onclick="saveProcTime()">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Title -->
<div class="modal-backdrop" id="modal-title">
  <div class="modal-box">
    <div class="modal-title">Add Title</div>
    <div class="modal-form">
      <div class="form-group">
        <label>Title Name</label>
        <input type="text" id="add-title-name" placeholder="e.g., Graduate, Undergraduate">
      </div>
      <div class="modal-actions">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('title')">Cancel</button>
        <button class="modal-btn modal-btn-primary" onclick="addTitle()">+ Add</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Title -->
<div class="modal-backdrop" id="modal-edit-title">
  <div class="modal-box">
    <div class="modal-title">Edit Title</div>
    <div class="modal-form">
      <input type="hidden" id="edit-title-old">
      <div class="form-group">
        <label>Title Name</label>
        <input type="text" id="edit-title-new" placeholder="Title name">
      </div>
      <div class="modal-actions">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('edit-title')">Cancel</button>
        <button class="modal-btn modal-btn-primary" onclick="saveEditTitle()">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Requirement -->
<div class="modal-backdrop" id="modal-requirement">
  <div class="modal-box">
    <div class="modal-title">Add Requirement</div>
    <div class="modal-form">
      <div class="form-group">
        <label>Select Title Name</label>
        <select id="add-req-title">
          <option value="">&#8212; Select Title &#8212;</option>
          <?php foreach ($titles as $t): ?>
            <option value="<?= h($t) ?>"><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Requirement Name</label>
        <input type="text" id="add-req-name" placeholder="e.g., Valid ID, F-138 (Report Card)">
      </div>
      <input type="hidden" id="add-req-key">
      <div class="modal-actions">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('requirement')">Cancel</button>
        <button class="modal-btn modal-btn-primary" onclick="addRequirement()">+ Add</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Requirement -->
<div class="modal-backdrop" id="modal-edit-req">
  <div class="modal-box">
    <div class="modal-title">Edit Requirement</div>
    <div class="modal-form">
      <input type="hidden" id="edit-req-id">
      <div class="form-group">
        <label>Title</label>
        <input type="text" id="edit-req-title-display" readonly
               style="background:#f5f5f5;color:#90a4ae;cursor:default;">
      </div>
      <div class="form-group">
        <label>Requirement Name</label>
        <input type="text" id="edit-req-name" placeholder="Requirement name">
      </div>
      <input type="hidden" id="edit-req-key">
      <div class="modal-actions">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('edit-req')">Cancel</button>
        <button class="modal-btn modal-btn-primary" onclick="saveEditReq()">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Reminder -->
<div class="modal-backdrop" id="modal-reminder">
  <div class="modal-box">
    <div class="modal-title">Add Reminder</div>
    <div class="modal-form">
      <div class="form-group">
        <label>Select Title Name <span style="font-weight:400;opacity:.6;">(optional)</span></label>
        <select id="add-rem-title">
          <option value="">&#8212; General &#8212;</option>
          <?php foreach ($titles as $t): ?>
            <option value="<?= h($t) ?>"><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Reminder Details</label>
        <textarea id="add-rem-details" placeholder="e.g., Note, Policy, Reminder"></textarea>
      </div>
      <div class="modal-actions">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('reminder')">Cancel</button>
        <button class="modal-btn modal-btn-primary" onclick="addReminder()">+ Add</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Reminder -->
<div class="modal-backdrop" id="modal-edit-reminder">
  <div class="modal-box">
    <div class="modal-title">Edit Reminder</div>
    <div class="modal-form">
      <input type="hidden" id="edit-rem-id">
      <div class="form-group">
        <label>Select Title Name <span style="font-weight:400;opacity:.6;">(optional)</span></label>
        <select id="edit-rem-title">
          <option value="">&#8212; General &#8212;</option>
          <?php foreach ($titles as $t): ?>
            <option value="<?= h($t) ?>"><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Reminder Details</label>
        <textarea id="edit-rem-details" placeholder="Reminder details..."></textarea>
      </div>
      <div class="modal-actions">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('edit-reminder')">Cancel</button>
        <button class="modal-btn modal-btn-primary" onclick="saveEditReminder()">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Application Process -->
<div class="modal-backdrop" id="modal-app_process">
  <div class="modal-box">
    <div class="modal-title">Add Application Process</div>
    <div class="modal-form">
      <div class="form-group">
        <label>Details</label>
        <textarea id="add-ap-details" style="min-height:110px;"
                  placeholder="Description of the Application Process..."></textarea>
      </div>
      <div class="modal-actions">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('app_process')">Cancel</button>
        <button class="modal-btn modal-btn-primary" onclick="addAppProcess()">+ Add</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Application Process -->
<div class="modal-backdrop" id="modal-edit-ap">
  <div class="modal-box">
    <div class="modal-title">Edit Application Process</div>
    <div class="modal-form">
      <input type="hidden" id="edit-ap-id">
      <div class="form-group">
        <label>Details</label>
        <textarea id="edit-ap-details" style="min-height:110px;"
                  placeholder="Application process details..."></textarea>
      </div>
      <div class="modal-actions">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('edit-ap')">Cancel</button>
        <button class="modal-btn modal-btn-primary" onclick="saveEditAppProcess()">Save</button>
      </div>
    </div>
  </div>
</div>



<script>
const DOC_ID = <?= $docId ?>;

/* ── Helpers ─────────────────────────────────────────────── */
function openModal(name)  { document.getElementById('modal-' + name).classList.add('open'); }
function closeModal(name) { document.getElementById('modal-' + name).classList.remove('open'); }

function showToast(msg, isError = false) {
  swalToast(msg, isError ? 'error' : 'success');
}

async function post(data) {
  const fd = new FormData();
  
  // 1. Add all the data passed to the function
  for (const k in data) fd.append(k, data[k]);
  
  // 2. ADD THE CORRECT CSRF TOKEN NAME
  // Note the underscore: _csrf_token
  fd.append('_csrf_token', '<?= csrf_token() ?>'); 

  // 3. Ensure we post back to the current page with the ID
  const r = await fetch('edit_document.php?id=' + DOC_ID, { 
    method: 'POST', 
    body: fd 
  });
  
  if (!r.ok) {
     const errorText = await r.text();
     showToast("Server Error: " + errorText, true);
     return { success: false };
  }

  return r.json();
}

// Close on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(b => {
  b.addEventListener('click', e => { if (e.target === b) b.classList.remove('open'); });
});

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Document Name modal ─────────────────────────────────── */
function openEditDocName() {
  document.getElementById('modal-doc-name').value = document.getElementById('disp-doc-name').textContent.trim();
  openModal('edit-docname');
}

async function saveDocName() {
  const name = document.getElementById('modal-doc-name').value.trim();
  if (!name) { showToast('Document name is required.', true); return; }
  const time = document.getElementById('disp-proc-time').textContent.trim();
  const res = await post({ action: 'update_document', document_name: name, processing_time: time });
  if (res.success) {
    document.getElementById('disp-doc-name').textContent = name;
    closeModal('edit-docname');
    showToast('Document name saved.');
  } else showToast(res.error || 'Error saving.', true);
}

/* ── Processing Time modal ───────────────────────────────── */
function openEditProcTime() {
  document.getElementById('modal-proc-time').value = document.getElementById('disp-proc-time').textContent.trim();
  openModal('edit-proctime');
}

async function saveProcTime() {
  const time = document.getElementById('modal-proc-time').value.trim();
  if (!time) { showToast('Processing time is required.', true); return; }
  const name = document.getElementById('disp-doc-name').textContent.trim();
  const res = await post({ action: 'update_document', document_name: name, processing_time: time });
  if (res.success) {
    document.getElementById('disp-proc-time').textContent = time;
    closeModal('edit-proctime');
    showToast('Processing time saved.');
  } else showToast(res.error || 'Error saving.', true);
}

/* ── SAVE CHANGES bottom bar ─────────────────────────────── */
async function saveDocInfo() {
  const name = document.getElementById('disp-doc-name').textContent.trim();
  const time = document.getElementById('disp-proc-time').textContent.trim();
  if (!name || !time) { showToast('Document name and processing time are required.', true); return; }
  const res = await post({ action: 'update_document', document_name: name, processing_time: time });
  if (res.success) showToast('Changes saved.');
  else showToast(res.error || 'Error saving.', true);
}

/* ── Titles ──────────────────────────────────────────────── */
async function addTitle() {
  const name = document.getElementById('add-title-name').value.trim();
  if (!name) { showToast('Title name is required.', true); return; }
  const res = await post({ action: 'add_title', title_name: name });
  if (res.success) {
    appendTitlePill(res.title_name);
    updateTitleDropdowns(res.title_name, 'add');
    document.getElementById('add-title-name').value = '';
    closeModal('title');
    showToast('Title added.');
  } else showToast(res.error || 'Error.', true);
}

function appendTitlePill(titleName) {
  const empty = document.getElementById('title-empty');
  if (empty) empty.remove();
  const pill = document.createElement('span');
  pill.className = 'title-pill';
  pill.dataset.title = titleName;

  const nameSpan = document.createElement('span');
  nameSpan.className = 'title-pill-name';
  nameSpan.textContent = titleName;

  const editBtn = document.createElement('button');
  editBtn.className = 'title-pill-btn title-pill-edit';
  editBtn.title = 'Edit';
  editBtn.innerHTML = '&#9998;';
  editBtn.onclick = () => openEditTitle(pill.dataset.title);

  const delBtn = document.createElement('button');
  delBtn.className = 'title-pill-btn';
  delBtn.title = 'Delete';
  delBtn.innerHTML = '&times;';
  delBtn.onclick = () => confirmDelete('title', null, pill.dataset.title);

  pill.appendChild(nameSpan);
  pill.appendChild(editBtn);
  pill.appendChild(delBtn);
  document.getElementById('title-list').appendChild(pill);
}

function openEditTitle(oldTitle) {
  document.getElementById('edit-title-old').value = oldTitle;
  document.getElementById('edit-title-new').value = oldTitle;
  openModal('edit-title');
}

async function saveEditTitle() {
  const oldT = document.getElementById('edit-title-old').value;
  const newT = document.getElementById('edit-title-new').value.trim();
  if (!newT) { showToast('Title name required.', true); return; }
  const res = await post({ action: 'edit_title', old_title: oldT, new_title: newT });
  if (res.success) {
    const pill = document.querySelector(`.title-pill[data-title="${CSS.escape(oldT)}"]`);
    if (pill) {
      pill.dataset.title = res.new_title;
      pill.querySelector('.title-pill-name').textContent = res.new_title;
    }
    updateTitleDropdowns(oldT, 'rename', res.new_title);
    closeModal('edit-title');
    showToast('Title updated.');
  } else showToast(res.error || 'Error.', true);
}

/* ── Requirements ────────────────────────────────────────── */
async function addRequirement() {
  const title = document.getElementById('add-req-title').value;
  const name  = document.getElementById('add-req-name').value.trim();
  const key   = name.toLowerCase().replace(/\s+/g,'_').replace(/[^a-z0-9_]/g,'');
  if (!title || !name) { showToast('Title and requirement name are required.', true); return; }
  const res = await post({ action: 'add_requirement', title_type: title, req_name: name, req_key: key });
  if (res.success) {
    appendReqRow(res.id, res.title_type, res.req_name, res.req_key);
    document.getElementById('add-req-name').value = '';
    closeModal('requirement');
    showToast('Requirement added.');
  } else showToast(res.error || 'Error.', true);
}

function appendReqRow(id, titleType, reqName, reqKey) {
  const empty = document.getElementById('req-empty');
  if (empty) empty.remove();
  const list = document.getElementById('req-list');
  let group = list.querySelector(`.req-group[data-group="${CSS.escape(titleType)}"]`);
  if (!group) {
    group = document.createElement('div');
    group.className = 'req-group';
    group.dataset.group = titleType;
    const hdr = document.createElement('div');
    hdr.className = 'req-group-header';
    hdr.textContent = titleType;
    group.appendChild(hdr);
    list.appendChild(group);
  }
  const row = document.createElement('div');
  row.className = 'req-item';
  row.dataset.reqId    = id;
  row.dataset.reqTitle = titleType;
  row.dataset.reqName  = reqName;
  row.dataset.reqKey   = reqKey;

  const bullet = document.createElement('div');
  bullet.className = 'req-item-bullet';
  bullet.textContent = reqName;

  const actions = document.createElement('div');
  actions.className = 'req-item-actions';

  const editBtn = document.createElement('button');
  editBtn.className = 'btn-row-edit';
  editBtn.innerHTML = '&#9998; EDIT';
  editBtn.onclick = () => openEditReqFromEl(editBtn);

  const delBtn = document.createElement('button');
  delBtn.className = 'btn-row-del';
  delBtn.innerHTML = '&#128465; DEL';
  delBtn.onclick = () => confirmDelete('requirement', parseInt(row.dataset.reqId));

  actions.appendChild(editBtn);
  actions.appendChild(delBtn);
  row.appendChild(bullet);
  row.appendChild(actions);
  group.appendChild(row);
}

/* FIX: read data from the element itself — no inline string escaping issues */
function openEditReqFromEl(btn) {
  const row = btn.closest('.req-item');
  document.getElementById('edit-req-id').value            = row.dataset.reqId;
  document.getElementById('edit-req-title-display').value = row.dataset.reqTitle;
  document.getElementById('edit-req-name').value          = row.dataset.reqName;
  document.getElementById('edit-req-key').value           = row.dataset.reqKey;
  openModal('edit-req');
}

async function saveEditReq() {
  const id   = document.getElementById('edit-req-id').value;
  const name = document.getElementById('edit-req-name').value.trim();
  const key  = document.getElementById('edit-req-key').value.trim();
  if (!name) { showToast('Requirement name required.', true); return; }
  const res = await post({ action: 'edit_requirement', req_id: id, req_name: name, req_key: key });
  if (res.success) {
    const row = document.querySelector(`.req-item[data-req-id="${id}"]`);
    if (row) {
      row.dataset.reqName = res.req_name;
      row.dataset.reqKey  = key;
      row.querySelector('.req-item-bullet').textContent = res.req_name;
    }
    closeModal('edit-req');
    showToast('Requirement updated.');
  } else showToast(res.error || 'Error.', true);
}

/* ── Reminders ───────────────────────────────────────────── */
async function addReminder() {
  const title   = document.getElementById('add-rem-title').value;
  const details = document.getElementById('add-rem-details').value.trim();
  if (!details) { showToast('Reminder details required.', true); return; }
  const res = await post({ action: 'add_reminder', title_type: title, details: details });
  if (res.success) {
    appendReminderCard(res.reminder.id, res.reminder.title_type, res.reminder.details);
    document.getElementById('add-rem-details').value = '';
    closeModal('reminder');
    showToast('Reminder added.');
  } else showToast(res.error || 'Error.', true);
}

function appendReminderCard(id, titleType, details) {
  const empty = document.getElementById('rem-empty');
  if (empty) empty.remove();

  const card = document.createElement('div');
  card.className = 'info-card';
  card.dataset.remId     = id;
  card.dataset.remTitle  = titleType || '';
  card.dataset.remDetails = details;

  if (titleType) {
    const hdr = document.createElement('div');
    hdr.className = 'info-card-header';
    hdr.textContent = titleType;
    card.appendChild(hdr);
  }

  const body = document.createElement('div');
  body.className = 'info-card-body';

  const textDiv = document.createElement('div');
  textDiv.className = 'info-card-text';
  textDiv.textContent = details;

  const actions = document.createElement('div');
  actions.className = 'info-card-actions';

  const editBtn = document.createElement('button');
  editBtn.className = 'btn-row-edit';
  editBtn.innerHTML = '&#9998; EDIT';
  editBtn.onclick = () => openEditReminderFromEl(editBtn);

  const delBtn = document.createElement('button');
  delBtn.className = 'btn-row-del';
  delBtn.innerHTML = '&#128465; DEL';
  delBtn.onclick = () => confirmDelete('reminder', null, null, card.dataset.remId);

  actions.appendChild(editBtn);
  actions.appendChild(delBtn);
  body.appendChild(textDiv);
  body.appendChild(actions);
  card.appendChild(body);
  document.getElementById('reminder-list').appendChild(card);
}

/* FIX: read from data-* attributes — safe for all content including quotes/newlines */
function openEditReminderFromEl(btn) {
  const card = btn.closest('.info-card');
  document.getElementById('edit-rem-id').value      = card.dataset.remId;
  document.getElementById('edit-rem-title').value   = card.dataset.remTitle || '';
  document.getElementById('edit-rem-details').value = card.dataset.remDetails;
  openModal('edit-reminder');
}

async function saveEditReminder() {
  const id      = document.getElementById('edit-rem-id').value;
  const title   = document.getElementById('edit-rem-title').value;
  const details = document.getElementById('edit-rem-details').value.trim();
  if (!details) { showToast('Details required.', true); return; }
  const res = await post({ action: 'edit_reminder', rem_id: id, title_type: title, details: details });
  if (res.success) {
    const card = document.querySelector(`.info-card[data-rem-id="${CSS.escape(id)}"]`);
    if (card) {
      card.dataset.remTitle   = title;
      card.dataset.remDetails = res.details;
      const hdr = card.querySelector('.info-card-header');
      if (title) {
        if (hdr) hdr.textContent = title;
        else card.insertAdjacentHTML('afterbegin', `<div class="info-card-header">${escHtml(title)}</div>`);
      } else if (hdr) hdr.remove();
      card.querySelector('.info-card-text').textContent = res.details;
    }
    closeModal('edit-reminder');
    showToast('Reminder updated.');
  } else showToast(res.error || 'Error.', true);
}

/* ── Application Process ─────────────────────────────────── */
async function addAppProcess() {
  const details = document.getElementById('add-ap-details').value.trim();
  if (!details) { showToast('Details required.', true); return; }
  const res = await post({ action: 'add_app_process', details: details });
  if (res.success) {
    appendApCard(res.app_process.id, res.app_process.details);
    document.getElementById('add-ap-details').value = '';
    closeModal('app_process');
    showToast('Application process added.');
  } else showToast(res.error || 'Error.', true);
}

function appendApCard(id, details) {
  const empty = document.getElementById('ap-empty');
  if (empty) empty.remove();

  const card = document.createElement('div');
  card.className = 'info-card';
  card.dataset.apId      = id;
  card.dataset.apDetails = details;

  const body = document.createElement('div');
  body.className = 'info-card-body';

  const textDiv = document.createElement('div');
  textDiv.className = 'info-card-text';
  textDiv.textContent = details;

  const actions = document.createElement('div');
  actions.className = 'info-card-actions';

  const editBtn = document.createElement('button');
  editBtn.className = 'btn-row-edit ap-edit-btn';
  editBtn.innerHTML = '&#9998; EDIT';
  editBtn.onclick = () => openEditApFromEl(editBtn);

  const delBtn = document.createElement('button');
  delBtn.className = 'btn-row-del ap-del-btn';
  delBtn.innerHTML = '&#128465; DEL';
  delBtn.onclick = () => confirmDelete('app_process', null, null, null, card.dataset.apId);

  actions.appendChild(editBtn);
  actions.appendChild(delBtn);
  body.appendChild(textDiv);
  body.appendChild(actions);
  card.appendChild(body);
  document.getElementById('appprocess-list').appendChild(card);
}

/* FIX: read from data-* attributes on closest card — safe for all content */
function openEditApFromEl(btn) {
  const card = btn.closest('.info-card');
  document.getElementById('edit-ap-id').value      = card.dataset.apId;
  document.getElementById('edit-ap-details').value = card.dataset.apDetails;
  openModal('edit-ap');
}

async function saveEditAppProcess() {
  const id      = document.getElementById('edit-ap-id').value;
  const details = document.getElementById('edit-ap-details').value.trim();
  if (!details) { showToast('Details required.', true); return; }
  const res = await post({ action: 'edit_app_process', ap_id: id, details: details });
  if (res.success) {
    const card = document.querySelector(`.info-card[data-ap-id="${CSS.escape(id)}"]`);
    if (card) {
      card.dataset.apDetails = res.details;
      card.querySelector('.info-card-text').textContent = res.details;
    }
    closeModal('edit-ap');
    showToast('Application process updated.');
  } else showToast(res.error || 'Error.', true);
}

/* ── Delete ──────────────────────────────────────────────── */
function confirmDelete(type, reqId=null, titleName=null, remId=null, apId=null) {
  swalConfirmDanger("Delete?", "This action cannot be undone. The item will be permanently removed.", "Yes, delete", function() {
    executeDelete({ type, reqId, titleName, remId, apId });
  });
}

async function executeDelete(ctx) {
  const { type, reqId, titleName, remId, apId } = ctx;
  let res;

  if (type === 'clear_field') {
    const isDocName = (reqId === 'doc-name');
    document.getElementById(isDocName ? 'disp-doc-name' : 'disp-proc-time').textContent = '';
    const name = isDocName ? '' : document.getElementById('disp-doc-name').textContent.trim();
    const time = isDocName ? document.getElementById('disp-proc-time').textContent.trim() : '';
    post({ action: 'update_document', document_name: name, processing_time: time });
    showToast(isDocName ? 'Document name cleared.' : 'Processing time cleared.');
    return;
  }

  if (type === 'document') {
    res = await post({ action: 'delete_document' });
    if (res && res.success) { window.location.href = 'document_management.php'; }
    else showToast((res && res.error) || 'Error.', true);
    return;
  }

  if (type === 'title')       res = await post({ action: 'delete_title',       title_name: titleName });
  if (type === 'requirement') res = await post({ action: 'delete_requirement', req_id: reqId });
  if (type === 'reminder')    res = await post({ action: 'delete_reminder',    rem_id: remId });
  if (type === 'app_process') res = await post({ action: 'delete_app_process', ap_id: apId });

  if (res && res.success) {
    if (type === 'title') {
      const pill = document.querySelector(`.title-pill[data-title="${CSS.escape(titleName)}"]`);
      if (pill) pill.remove();
      updateTitleDropdowns(titleName, 'remove');
      if (!document.querySelectorAll('#title-list .title-pill').length)
        document.getElementById('title-list').innerHTML = '<span class="empty-msg" id="title-empty">No titles added yet.</span>';
    }
    if (type === 'requirement') {
      const row = document.querySelector(`.req-item[data-req-id="${reqId}"]`);
      if (row) {
        const group = row.closest('.req-group');
        row.remove();
        if (group && !group.querySelectorAll('.req-item').length) group.remove();
      }
      if (!document.querySelectorAll('#req-list .req-group').length)
        document.getElementById('req-list').innerHTML = '<div class="empty-msg" id="req-empty">No requirements added yet.</div>';
    }
    if (type === 'reminder') {
      const card = document.querySelector(`.info-card[data-rem-id="${CSS.escape(remId)}"]`);
      if (card) card.remove();
      if (!document.querySelectorAll('#reminder-list .info-card').length)
        document.getElementById('reminder-list').innerHTML = '<div class="empty-msg" id="rem-empty">No reminders added yet.</div>';
    }
    if (type === 'app_process') {
      const card = document.querySelector(`.info-card[data-ap-id="${CSS.escape(apId)}"]`);
      if (card) card.remove();
      if (!document.querySelectorAll('#appprocess-list .info-card').length)
        document.getElementById('appprocess-list').innerHTML = '<div class="empty-msg" id="ap-empty">No application process added yet.</div>';
    }
    showToast('Deleted successfully.');
  } else showToast((res && res.error) || 'Error.', true);
}

/* ── Title Dropdown Sync ─────────────────────────────────── */
function updateTitleDropdowns(title, action, newTitle=null) {
  ['add-req-title','add-rem-title','edit-rem-title'].forEach(id => {
    const sel = document.getElementById(id);
    if (!sel) return;
    if (action === 'add') {
      const o = document.createElement('option');
      o.value = title; o.textContent = title;
      sel.appendChild(o);
    } else if (action === 'remove') {
      const o = sel.querySelector(`option[value="${CSS.escape(title)}"]`);
      if (o) o.remove();
    } else if (action === 'rename' && newTitle) {
      const o = sel.querySelector(`option[value="${title}"]`);
      if (o) { o.value = newTitle; o.textContent = newTitle; }
    }
  });
}

/* ── Sidebar ─────────────────────────────────────────────── */
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
</script>

</body>
</html>