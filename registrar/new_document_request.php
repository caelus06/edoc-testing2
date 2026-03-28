<?php
// registrar/new_document_request.php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_REGISTRAR);

$registrarId = (int)$_SESSION["user_id"];

/* Registrar name */
$registrarName = "Registrar";
$me = $conn->prepare("SELECT first_name, last_name FROM users WHERE id=? LIMIT 1");
$me->bind_param("i", $registrarId);
$me->execute();
$mr = $me->get_result()->fetch_assoc();
if ($mr) $registrarName = trim(($mr["first_name"] ?? "") . " " . ($mr["last_name"] ?? ""));

/* Status counters (top bar) */
function countRequestsByStatus($conn, $status){
  $sql="SELECT COUNT(*) c FROM requests WHERE UPPER(status)=?";
  $st=strtoupper($status);
  $q=$conn->prepare($sql);
  $q->bind_param("s",$st);
  $q->execute();
  return (int)($q->get_result()->fetch_assoc()["c"] ?? 0);
}
$cnt_pending   = countRequestsByStatus($conn,"PENDING");
$cnt_returned  = countRequestsByStatus($conn,"RETURNED");
$cnt_verified  = countRequestsByStatus($conn,"VERIFIED");
$cnt_approved  = countRequestsByStatus($conn,"APPROVED");
$cnt_processing= countRequestsByStatus($conn,"PROCESSING");
$cnt_ready     = countRequestsByStatus($conn,"READY FOR PICKUP");

// -----------------------------
// STEP control
// -----------------------------
$step = (int)($_GET["step"] ?? 1);
if ($step < 1 || $step > 2) $step = 1;

// -----------------------------
// STEP 1: Search + list users
// -----------------------------
$q = trim($_GET["q"] ?? "");
$statusFilter = strtoupper(trim($_GET["status"] ?? "")); // optional filter
$users = [];

if ($step === 1) {
  $sql = "
    SELECT id, first_name, middle_name, last_name, suffix,
           student_id, contact_number, email, verification_status
    FROM users
    WHERE role = 'USER'
  ";

  $params = [];
  $types  = "";

  if ($q !== "") {
    $sql .= " AND (
      CONCAT_WS(' ', first_name, middle_name, last_name, suffix) LIKE ?
      OR student_id LIKE ?
      OR email LIKE ?
      OR contact_number LIKE ?
    )";
    $like = "%{$q}%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "ssss";
  }

  if ($statusFilter !== "" && in_array($statusFilter, ["VERIFIED","PENDING"], true)) {
    $sql .= " AND UPPER(verification_status) = ?";
    $params[] = $statusFilter;
    $types .= "s";
  }

  $sql .= " ORDER BY last_name ASC, first_name ASC LIMIT 200";

  $st = $conn->prepare($sql);
  if ($types !== "") $st->bind_param($types, ...$params);
  $st->execute();
  $users = $st->get_result()->fetch_all(MYSQLI_ASSOC);
}

// -----------------------------
// STEP 2: Create request for selected user
// -----------------------------
$user_id = (int)($_GET["user_id"] ?? 0);
$userRow = null;

$error = "";
$docTypes = [];
$titleTypes = [];
$selectedDocType = trim($_POST["document_type"] ?? ($_GET["doc_type"] ?? ""));

if ($step === 2) {
  if ($user_id <= 0) {
    header("Location: new_document_request.php");
    exit();
  }

  $stU = $conn->prepare("
    SELECT id, first_name, middle_name, last_name, suffix,
           student_id, course, major, year_graduated, gender,
           email, contact_number, address, verification_status
    FROM users
    WHERE id = ? AND role = 'USER'
    LIMIT 1
  ");
  $stU->bind_param("i", $user_id);
  $stU->execute();
  $userRow = $stU->get_result()->fetch_assoc();
  if (!$userRow) {
    swal_flash("error", "Error", "Student not found.");
    header("Location: new_document_request.php");
    exit();
  }

  // Fetch available document types + title types from requirements_master
  $stDT = $conn->prepare("SELECT DISTINCT document_type FROM requirements_master ORDER BY document_type ASC");
  $stDT->execute();
  $docTypes = $stDT->get_result()->fetch_all(MYSQLI_ASSOC);

  if ($selectedDocType !== "") {
    $stTT = $conn->prepare("
      SELECT DISTINCT title_type
      FROM requirements_master
      WHERE UPPER(TRIM(document_type)) = UPPER(TRIM(?))
      ORDER BY title_type ASC
    ");
    $stTT->bind_param("s", $selectedDocType);
    $stTT->execute();
    $titleTypes = $stTT->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  // Handle create request
  if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "create") {
    csrf_verify();
    $document_type = trim($_POST["document_type"] ?? "");
    $title_type    = trim($_POST["title_type"] ?? "");
    $purpose       = trim($_POST["purpose"] ?? "");
    $copies        = (int)($_POST["copies"] ?? 1);

    if ($document_type === "" || $title_type === "" || $purpose === "") {
      $error = "Please fill in Document Type, Title Type, and Purpose.";
    } elseif ($copies <= 0) {
      $error = "Copies must be at least 1.";
    } else {

      // ✅ Prevent duplicate request for same document unless COMPLETED
      $dup = $conn->prepare("
        SELECT id
        FROM requests
        WHERE user_id=? AND UPPER(TRIM(document_type))=UPPER(TRIM(?))
          AND UPPER(status) <> 'COMPLETED'
          AND UPPER(status) <> 'CANCELLED'
        LIMIT 1
      ");
      $dup->bind_param("is", $user_id, $document_type);
      $dup->execute();
      if ($dup->get_result()->fetch_assoc()) {
        $error = "This student already has an active request for this document type. Must be COMPLETED first.";
      } else {

        // Generate unique reference number
        $year = date("Y");
        $reference_no = "";
        for ($i=0; $i<10; $i++){
          $rand = random_int(1000, 9999);
          $try = "EDOC-{$year}-{$rand}";
          $chk = $conn->prepare("SELECT id FROM requests WHERE reference_no=? LIMIT 1");
          $chk->bind_param("s", $try);
          $chk->execute();
          if (!$chk->get_result()->fetch_assoc()) { $reference_no = $try; break; }
        }
        if ($reference_no === "") $error = "Failed to generate reference number.";
        else {
          $status = "PENDING";

          $ins = $conn->prepare("
            INSERT INTO requests (reference_no, user_id, document_type, title_type, purpose, copies, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
          ");
          $ins->bind_param("sisssis", $reference_no, $user_id, $document_type, $title_type, $purpose, $copies, $status);
          $ins->execute();
          $newRequestId = (int)$conn->insert_id;

          $registrar_anon = get_registrar_id($conn, (int)$_SESSION["user_id"]);
          $msg = "Reference Number: " . $reference_no . " (" . strtoupper($document_type) . ") — Request created on your behalf. Processed by " . $registrar_anon;
          notify_user($conn, $newRequestId, $msg);

          audit_log($conn, "INSERT", "requests", $newRequestId, "Registrar created request " . $reference_no);

          header("Location: verify_request.php?id=" . $newRequestId);
          exit();
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Document Request</title>

  <!-- ✅ IMPORTANT: load SAME layout CSS as request_management -->
  <link rel="stylesheet" href="../assets/css/new_document_request.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
</head>
<body>

<div class="layout">
  <!-- SIDEBAR (same as request_management) -->
  <aside class="sidebar" id="sidebar">
    <div class="sb-user">
      <div class="avatar">👤</div>
      <div class="meta">
        <div class="name"><?= h($registrarName) ?></div>
        <div class="role">Registrar</div>
      </div>
    </div>

    <div class="sb-section-title">MODULES</div>
    <nav class="sb-nav">
      <a class="sb-item" href="dashboard.php"><span class="sb-icon">🏠</span>Dashboard</a>
      <a class="sb-item active" href="new_document_request.php"><span class="sb-icon">📝</span>New Document Request</a>
      <a class="sb-item" href="request_management.php"><span class="sb-icon">🔎</span>Request Management</a>
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

  <div class="main">
    <!-- TOPBAR (same as request_management) -->
    <header class="topbar">
      <button class="hamburger" type="button" onclick="toggleSidebar()">≡</button>
      <div class="brand">
        <div class="logo"><img src="../assets/img/edoc-logo.jpeg" alt="E-Doc"></div>
        <div>E-Doc Document Requesting System</div>
      </div>
    </header>
    
    <!-- STATS BAR -->
    <div class="statsbar">
      <div class="stats">
        <div class="stat">
          <div class="num"><?= $cnt_pending ?></div>
          <div class="label">Incoming</div>
          <div class="sub">(Pending)</div>
        </div>
        <div class="stat">
          <div class="num"><?= $cnt_returned ?></div>
          <div class="label">Returned</div>
          <div class="sub">(Resubmit)</div>
        </div>
        <div class="stat">
          <div class="num"><?= $cnt_verified ?></div>
          <div class="label">Verified</div>
          <div class="sub">(Submit Soft Copy)</div>
        </div>
        <div class="stat">
          <div class="num"><?= $cnt_approved ?></div>
          <div class="label">Approved</div>
          <div class="sub">(Submit Hard Copy)</div>
        </div>
        <div class="stat">
          <div class="num"><?= $cnt_processing ?></div>
          <div class="label">Processing</div>
          <div class="sub">(Submission)</div>
        </div>
        <div class="stat">
          <div class="num"><?= $cnt_ready ?></div>
          <div class="label">Ready for Pickup</div>
        </div>
      </div>
    </div>

    <main class="container">

      <?php if ($step === 1): ?>
        <div class="ndr-head">
          <div class="title">New Document Request</div>
          <div class="sub">Search student accounts and create a request.</div>
        </div>

        <form method="GET" class="ndr-search">
          <input type="hidden" name="step" value="1">
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search Name, ID Number, Email, Contact Number">
          <select name="status">
            <option value="">-- All Status --</option>
            <option value="VERIFIED" <?= $statusFilter==="VERIFIED" ? "selected" : "" ?>>VERIFIED</option>
            <option value="PENDING"  <?= $statusFilter==="PENDING"  ? "selected" : "" ?>>PENDING</option>
          </select>
          <button type="submit">FILTER</button>
        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th style="width:260px;">NAME</th>
                <th style="width:140px;">ID Number</th>
                <th style="width:160px;">Contact</th>
                <th style="width:150px;">Account Status</th>
                <th style="width:150px;">ACTION</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($users) === 0): ?>
                <tr><td colspan="5">No students found.</td></tr>
              <?php else: ?>
                <?php foreach ($users as $u): ?>
                  <?php
                    $name = trim(($u["last_name"] ?? "").", ".($u["first_name"] ?? "")." ".($u["middle_name"] ?? "")." ".($u["suffix"] ?? ""));
                    $acc  = strtoupper($u["verification_status"] ?? "PENDING");
                  ?>
                  <tr>
                    <td><?= h($name) ?></td>
                    <td><?= h($u["student_id"] ?? "N/A") ?></td>
                    <td><?= h($u["contact_number"] ?? "N/A") ?></td>
                    <td>
                      <span class="pill <?= $acc==="VERIFIED" ? "pill-green" : "pill-gray" ?>">
                        <?= h($acc) ?>
                      </span>
                    </td>
                    <td>
                      <a class="btn-verify" href="new_document_request.php?step=2&user_id=<?= (int)$u["id"] ?>">
                        NEW REQUEST
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <?php else: ?>
        <?php
          $fullName = trim(($userRow["first_name"] ?? "")." ".($userRow["middle_name"] ?? "")." ".($userRow["last_name"] ?? "")." ".($userRow["suffix"] ?? ""));
          $acc  = strtoupper($userRow["verification_status"] ?? "PENDING");
        ?>

        <div class="ndr-head">
          <div class="title">Application Details</div>
          <div class="sub">Complete the fields below to create a new request.</div>
        </div>

        <?php if (!empty($error)): ?>
          <script>document.addEventListener("DOMContentLoaded", function(){ swalError("Validation Error", <?= json_encode($error) ?>); });</script>
        <?php endif; ?>

        <div class="student-card">
          <div class="student-card-title">Review Student Information</div>
          <div class="student-grid">
            <div><b>Name:</b> <?= h($fullName) ?></div>
            <div><b>ID Number:</b> <?= h($userRow["student_id"] ?: "N/A") ?></div>
            <div><b>Course/Program:</b> <?= h($userRow["course"] ?: "N/A") ?></div>
            <div><b>Major:</b> <?= h($userRow["major"] ?: "N/A") ?></div>
            <div><b>Year Graduated:</b> <?= h($userRow["year_graduated"] ?: "N/A") ?></div>
            <div><b>Email:</b> <?= h($userRow["email"] ?: "N/A") ?></div>
            <div><b>Contact Number:</b> <?= h($userRow["contact_number"] ?: "N/A") ?></div>
            <div><b>Complete Address:</b> <?= h($userRow["address"] ?: "N/A") ?></div>
            <div>
              <b>Account Status:</b>
              <span class="pill <?= $acc==="VERIFIED" ? "pill-green" : "pill-gray" ?>">
                <?= h($acc) ?>
              </span>
            </div>
          </div>
        </div>

        <form method="POST" class="form-card">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="step" value="2">
          <input type="hidden" name="user_id" value="<?= (int)$user_id ?>">

          <div class="form-row">
            <label>Document Type:</label>
            <select name="document_type" required onchange="if(this.value) window.location='new_document_request.php?step=2&user_id=<?= (int)$user_id ?>&doc_type='+encodeURIComponent(this.value)">
              <option value="">--- Select Document Type ---</option>
              <?php foreach ($docTypes as $d): ?>
                <?php $dt = (string)$d["document_type"]; ?>
                <option value="<?= h($dt) ?>" <?= ($selectedDocType===$dt) ? "selected" : "" ?>><?= h($dt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-row">
            <label>Title Type:</label>
            <select name="title_type" required>
              <option value="">--- Select Title Type ---</option>
              <?php foreach ($titleTypes as $t): ?>
                <?php $tt = (string)$t["title_type"]; ?>
                <option value="<?= h($tt) ?>" <?= (($_POST["title_type"] ?? "")===$tt) ? "selected" : "" ?>><?= h($tt) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="hint">Choose a Document Type first to load Title Types.</div>
          </div>

          <div class="form-row">
            <label>Purpose/s of request</label>
            <input type="text" name="purpose" value="<?= h($_POST["purpose"] ?? "") ?>"
                   placeholder="e.g. for employment, for further studies, for Transfer..." required>
          </div>

          <div class="form-row">
            <label>Number of Copies *</label>
            <input type="number" name="copies" min="1" value="<?= h($_POST["copies"] ?? "1") ?>" required>
          </div>

          <div class="button-group">
            <button class="btn-create" type="submit">CREATE REQUEST</button>
            <a class="btn-cancel" href="new_document_request.php">CANCEL</a>
          </div>
        </form>
      <?php endif; ?>

      
    </main>
  </div>
</div>
<div class="footer-bar"></div>

</body>
</html>