<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_MIS);

$misId = (int)$_SESSION["user_id"];

/* MIS name */
$misName = "MIS Admin";
$me = $conn->prepare("SELECT first_name, last_name FROM users WHERE id=? LIMIT 1");
$me->bind_param("i", $misId);
$me->execute();
$mr = $me->get_result()->fetch_assoc();
if ($mr) {
  $misName = trim(($mr["first_name"] ?? "") . " " . ($mr["last_name"] ?? ""));
}

$flash = "";
$flashType = "success";

/* =========================
   HELPERS
========================= */
function countUsersByStatus($conn, $status) {
  $sql = "SELECT COUNT(*) AS c FROM users WHERE UPPER(COALESCE(verification_status,'PENDING')) = ?";
  $st = $conn->prepare($sql);
  $s = strtoupper($status);
  $st->bind_param("s", $s);
  $st->execute();
  return (int)($st->get_result()->fetch_assoc()["c"] ?? 0);
}

function normalizeRole($role) {
  $role = strtoupper(trim((string)$role));
  $allowed = ["USER","MIS","REGISTRAR"];
  return in_array($role, $allowed, true) ? $role : "USER";
}

function normalizeStatus($status) {
  $status = strtoupper(trim((string)$status));
  $allowed = ["PENDING","VERIFIED","RESUBMIT","UNAFFILIATED"];
  return in_array($status, $allowed, true) ? $status : "PENDING";
}

/* =========================
   HANDLE DELETE ID + NOTIFY USER
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete_id_notify") {
  csrf_verify();
  $user_id = (int)($_POST["user_id"] ?? 0);
  $file_path = trim($_POST["file_path"] ?? "");
  $reason = trim($_POST["delete_reason"] ?? "");

  if ($user_id <= 0 || $file_path === "") {
    $flash = "Missing ID file data.";
    $flashType = "error";
  } else {
    $df = $conn->prepare("
      SELECT rf.id, rf.request_id, rf.file_path
      FROM request_files rf
      INNER JOIN requests r ON r.id = rf.request_id
      WHERE r.user_id = ?
        AND rf.file_path = ?
      LIMIT 1
    ");
    $df->bind_param("is", $user_id, $file_path);
    $df->execute();
    $delRow = $df->get_result()->fetch_assoc();

    if (!$delRow) {
      $flash = "ID file not found.";
      $flashType = "error";
    } else {
      $request_id = (int)$delRow["request_id"];
      $dbFilePath = $delRow["file_path"];

      $absPath = "../" . ltrim($dbFilePath, "/");
      if (file_exists($absPath)) {
        @unlink($absPath);
      }

      $dr = $conn->prepare("DELETE FROM request_files WHERE id=? LIMIT 1");
      $dr->bind_param("i", $delRow["id"]);
      $dr->execute();

      $us = $conn->prepare("UPDATE users SET verification_status='RESUBMIT' WHERE id=?");
      $us->bind_param("i", $user_id);
      $us->execute();

      $message = "VALID ID REMOVED BY MIS. USER MUST RESUBMIT.";
      if ($reason !== "") {
        $message .= " REASON: " . $reason;
      }

      $lg = $conn->prepare("INSERT INTO request_logs (request_id, message) VALUES (?, ?)");
      $lg->bind_param("is", $request_id, $message);
      $lg->execute();

      header("Location: account_management.php?edit=" . $user_id . "&msg=deleted");
      exit();
    }
  }
}

/* =========================
   HANDLE ID UPLOAD
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "upload_id") {
  csrf_verify();
  $user_id = (int)($_POST["user_id"] ?? 0);

  if ($user_id <= 0) {
    $flash = "Invalid user.";
    $flashType = "error";
  } elseif (!isset($_FILES["id_file"]) || $_FILES["id_file"]["error"] !== UPLOAD_ERR_OK) {
    $flash = "Please choose an ID image or PDF.";
    $flashType = "error";
  } else {
    $rq = $conn->prepare("SELECT id FROM requests WHERE user_id=? ORDER BY created_at DESC, id DESC LIMIT 1");
    $rq->bind_param("i", $user_id);
    $rq->execute();
    $rqRow = $rq->get_result()->fetch_assoc();

    if (!$rqRow) {
      $flash = "This user has no request yet. Cannot attach ID.";
      $flashType = "error";
    } else {
      $request_id = (int)$rqRow["id"];

      $tmp = $_FILES["id_file"]["tmp_name"];
      $size = (int)$_FILES["id_file"]["size"];

      if ($size > 15 * 1024 * 1024) {
        $flash = "Max file size is 15MB.";
        $flashType = "error";
      } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);

        $allowed = [
          "image/jpeg" => "jpg",
          "image/png" => "png",
          "image/webp" => "webp",
          "application/pdf" => "pdf"
        ];

        if (!isset($allowed[$mime])) {
          $flash = "Only JPG, PNG, WEBP, or PDF allowed.";
          $flashType = "error";
        } else {
          $ext = $allowed[$mime];
          $dir = "../uploads/request_files/" . $request_id;
          if (!is_dir($dir)) mkdir($dir, 0755, true);

          $filename = "valid_id_" . bin2hex(random_bytes(8)) . "." . $ext;
          $dest = $dir . "/" . $filename;

          if (!move_uploaded_file($tmp, $dest)) {
            $flash = "Failed to upload file.";
            $flashType = "error";
          } else {
            $relative = str_replace("../", "", $dest);

            $ins = $conn->prepare("
              INSERT INTO request_files (request_id, requirement_name, file_path, uploaded_at, requirement_key)
              VALUES (?, 'Valid ID', ?, NOW(), 'valid_id')
            ");
            $ins->bind_param("is", $request_id, $relative);
            $ins->execute();

            $upUser = $conn->prepare("UPDATE users SET verification_status='PENDING' WHERE id=?");
            $upUser->bind_param("i", $user_id);
            $upUser->execute();

            $msg = "VALID ID UPLOADED / RE-UPLOADED BY MIS";
            $lg = $conn->prepare("INSERT INTO request_logs (request_id, message) VALUES (?, ?)");
            $lg->bind_param("is", $request_id, $msg);
            $lg->execute();

            header("Location: account_management.php?edit=" . $user_id . "&msg=uploaded");
            exit();
          }
        }
      }
    }
  }
}

/* =========================
   HANDLE SAVE USER
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save_user") {
  csrf_verify();
  $user_id = (int)($_POST["user_id"] ?? 0);

  $first_name      = trim($_POST["first_name"] ?? "");
  $middle_name     = trim($_POST["middle_name"] ?? "");
  $last_name       = trim($_POST["last_name"] ?? "");
  $suffix          = trim($_POST["suffix"] ?? "");
  $gender          = trim($_POST["gender"] ?? "");
  $contact_number  = trim($_POST["contact_number"] ?? "");
  $student_id      = trim($_POST["student_id"] ?? "");
  $year_graduated  = trim($_POST["year_graduated"] ?? "");
  $course          = trim($_POST["course"] ?? "");
  $major           = trim($_POST["major"] ?? "");
  $address         = trim($_POST["address"] ?? "");
  $role            = normalizeRole($_POST["role"] ?? "USER");
  $verification_status = normalizeStatus($_POST["verification_status"] ?? "PENDING");
  $new_password    = trim($_POST["new_password"] ?? "");

  if ($user_id <= 0 || $first_name === "" || $last_name === "") {
    $flash = "Missing required user information.";
    $flashType = "error";
  } else {
    if ($new_password !== "") {
      $hashed = password_hash($new_password, PASSWORD_DEFAULT);

      $up = $conn->prepare("
        UPDATE users
        SET first_name=?, middle_name=?, last_name=?, suffix=?, gender=?, contact_number=?,
            student_id=?, year_graduated=?, course=?, major=?, address=?,
            role=?, verification_status=?, password=?
        WHERE id=?
      ");
      $up->bind_param(
        "ssssssssssssssi",
        $first_name, $middle_name, $last_name, $suffix, $gender, $contact_number,
        $student_id, $year_graduated, $course, $major, $address,
        $role, $verification_status, $hashed, $user_id
      );
    } else {
      $up = $conn->prepare("
        UPDATE users
        SET first_name=?, middle_name=?, last_name=?, suffix=?, gender=?, contact_number=?,
            student_id=?, year_graduated=?, course=?, major=?, address=?,
            role=?, verification_status=?
        WHERE id=?
      ");
      $up->bind_param(
        "sssssssssssssi",
        $first_name, $middle_name, $last_name, $suffix, $gender, $contact_number,
        $student_id, $year_graduated, $course, $major, $address,
        $role, $verification_status, $user_id
      );
    }

    if ($up->execute()) {
      header("Location: account_management.php?edit=" . $user_id . "&msg=saved");
      exit();
    } else {
      $flash = "Failed to update user.";
      $flashType = "error";
    }
  }
}

/* =========================
   HANDLE BULK IMPORT (CSV ONLY)
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "import_accounts") {
  csrf_verify();
  if (!isset($_FILES["bulk_file"]) || $_FILES["bulk_file"]["error"] !== UPLOAD_ERR_OK) {
    $flash = "Please upload a CSV file.";
    $flashType = "error";
  } else {
    $tmpName = $_FILES["bulk_file"]["tmp_name"];
    $origName = $_FILES["bulk_file"]["name"];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    $rowsImport = [];
    $header = [];

    if ($ext === "csv") {
      if (($handle = fopen($tmpName, "r")) !== false) {
        $lineNo = 0;
        while (($data = fgetcsv($handle, 10000, ",")) !== false) {
          if ($lineNo === 0) {
            $header = $data;
          } else {
            $rowsImport[] = $data;
          }
          $lineNo++;
        }
        fclose($handle);
      }
    } else {
      $flash = "Unsupported file type. Please upload CSV only.";
      $flashType = "error";
    }

    if ($flash === "" && !empty($header)) {
      $headerMap = [];
      foreach ($header as $idx => $col) {
        $key = strtolower(trim((string)$col));
        $headerMap[$key] = $idx;
      }

      $required = ["first_name","last_name","email","password"];
      $missing = [];
      foreach ($required as $req) {
        if (!array_key_exists($req, $headerMap)) $missing[] = $req;
      }

      if (!empty($missing)) {
        $flash = "Missing required column(s): " . implode(", ", $missing);
        $flashType = "error";
      } else {
        $imported = 0;
        $skipped = 0;

        foreach ($rowsImport as $r) {
          $first_name = trim((string)($r[$headerMap["first_name"] ?? -1] ?? ""));
          $middle_name = trim((string)($r[$headerMap["middle_name"] ?? -1] ?? ""));
          $last_name = trim((string)($r[$headerMap["last_name"] ?? -1] ?? ""));
          $suffix = trim((string)($r[$headerMap["suffix"] ?? -1] ?? ""));
          $gender = trim((string)($r[$headerMap["gender"] ?? -1] ?? ""));
          $contact_number = trim((string)($r[$headerMap["contact_number"] ?? -1] ?? ""));
          $student_id = trim((string)($r[$headerMap["student_id"] ?? -1] ?? ""));
          $year_graduated = trim((string)($r[$headerMap["year_graduated"] ?? -1] ?? ""));
          $course = trim((string)($r[$headerMap["course"] ?? -1] ?? ""));
          $major = trim((string)($r[$headerMap["major"] ?? -1] ?? ""));
          $address = trim((string)($r[$headerMap["address"] ?? -1] ?? ""));
          $email = trim((string)($r[$headerMap["email"] ?? -1] ?? ""));
          $password = trim((string)($r[$headerMap["password"] ?? -1] ?? ""));
          $role = normalizeRole((string)($r[$headerMap["role"] ?? -1] ?? "USER"));
          $verification_status = normalizeStatus((string)($r[$headerMap["verification_status"] ?? -1] ?? "PENDING"));

          if ($first_name === "" || $last_name === "" || $email === "" || $password === "") {
            $skipped++;
            continue;
          }

          $chk = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
          $chk->bind_param("s", $email);
          $chk->execute();
          if ($chk->get_result()->fetch_assoc()) {
            $skipped++;
            continue;
          }

          $hashed = password_hash($password, PASSWORD_DEFAULT);

          $ins = $conn->prepare("
            INSERT INTO users
            (first_name, middle_name, last_name, suffix, gender, contact_number,
             student_id, year_graduated, course, major, address,
             email, password, role, verification_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
          ");
          $ins->bind_param(
            "sssssssssssssss",
            $first_name, $middle_name, $last_name, $suffix, $gender, $contact_number,
            $student_id, $year_graduated, $course, $major, $address,
            $email, $hashed, $role, $verification_status
          );

          if ($ins->execute()) {
            $imported++;
          } else {
            $skipped++;
          }
        }

        $flash = "Bulk import finished. Imported: {$imported}, Skipped: {$skipped}.";
        $flashType = "success";
      }
    }
  }
}

/* =========================
   STATS
========================= */
$acc_verified     = countUsersByStatus($conn, "VERIFIED");
$acc_unverified   = countUsersByStatus($conn, "PENDING");
$acc_resubmit     = countUsersByStatus($conn, "RESUBMIT");
$acc_unaffiliated = countUsersByStatus($conn, "UNAFFILIATED");

/* =========================
   FILTERS
========================= */
$q = trim($_GET["q"] ?? "");
$status = trim($_GET["status"] ?? "");
$roleFilter = trim($_GET["role"] ?? "");

$allowedStatuses = ["","VERIFIED","PENDING","RESUBMIT","UNAFFILIATED"];
if (!in_array(strtoupper($status), $allowedStatuses, true)) $status = "";

$allowedRoles = ["","USER","REGISTRAR","MIS"];
if (!in_array(strtoupper($roleFilter), $allowedRoles, true)) $roleFilter = "";

/* pagination */
$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = " WHERE role IN ('USER','REGISTRAR','MIS') ";
$params = [];
$types = "";

if ($q !== "") {
  $where .= " AND (
    CONCAT(last_name, ', ', first_name, ' ', middle_name) LIKE ?
    OR student_id LIKE ?
    OR email LIKE ?
  )";
  $like = "%".$q."%";
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= "sss";
}

if ($status !== "") {
  $where .= " AND UPPER(COALESCE(verification_status,'PENDING')) = ?";
  $params[] = strtoupper($status);
  $types .= "s";
}

if ($roleFilter !== "") {
  $where .= " AND UPPER(role) = ?";
  $params[] = strtoupper($roleFilter);
  $types .= "s";
}

/* total count */
$countSql = "SELECT COUNT(*) AS c FROM users $where";
$countStmt = $conn->prepare($countSql);
if ($types !== "") $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()["c"] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

/* list query */
$sql = "
  SELECT
    u.id, u.first_name, u.middle_name, u.last_name, u.suffix,
    u.student_id, u.contact_number, u.email, u.gender,
    u.year_graduated, u.course, u.major, u.address,
    u.role, u.verification_status,
    (SELECT COUNT(*) FROM requests r WHERE r.user_id = u.id) AS request_count
  FROM users u
  $where
  ORDER BY u.last_name ASC, u.first_name ASC
  LIMIT $perPage OFFSET $offset
";
$stmt = $conn->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* =========================
   EDIT MODAL USER
========================= */
$editId = (int)($_GET["edit"] ?? 0);
$editUser = null;
$idFiles = [];

if ($editId > 0) {
  $eu = $conn->prepare("
    SELECT id, first_name, middle_name, last_name, suffix,
           student_id, gender, contact_number, year_graduated,
           course, major, address, email, role, verification_status
    FROM users
    WHERE id=? LIMIT 1
  ");
  $eu->bind_param("i", $editId);
  $eu->execute();
  $editUser = $eu->get_result()->fetch_assoc();

  if ($editUser) {
    $idf = $conn->prepare("
      SELECT rf.file_path, rf.uploaded_at
      FROM request_files rf
      INNER JOIN requests r ON r.id = rf.request_id
      WHERE r.user_id = ?
        AND (
          LOWER(COALESCE(rf.requirement_key,'')) = 'valid_id'
          OR LOWER(COALESCE(rf.requirement_name,'')) LIKE '%valid id%'
        )
      ORDER BY rf.uploaded_at DESC, rf.id DESC
      LIMIT 10
    ");
    $idf->bind_param("i", $editId);
    $idf->execute();
    $idFiles = $idf->get_result()->fetch_all(MYSQLI_ASSOC);
  }
}

if (isset($_GET["msg"])) {
  $msg = $_GET["msg"];
  if ($msg === "saved") {
    $flash = "Changes saved successfully.";
    $flashType = "success";
  } elseif ($msg === "uploaded") {
    $flash = "ID uploaded successfully.";
    $flashType = "success";
  } elseif ($msg === "deleted") {
    $flash = "ID deleted and user notified successfully.";
    $flashType = "success";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MIS Account Management</title>
  <link rel="stylesheet" href="../assets/css/mis_account_management.css">
</head>
<body>

<div class="layout">

  <aside class="sidebar" id="sidebar">
    <div class="sb-user">
      <div class="avatar">👤</div>
      <div class="meta">
        <div class="name"><?= h($misName) ?></div>
        <div class="role">MIS Admin</div>
      </div>
    </div>

    <div class="sb-badge">MIS Admin</div>

    <div class="sb-section-title">MODULES</div>
    <nav class="sb-nav">
      <a class="sb-item" href="dashboard.php"><span class="sb-icon">🏠</span>Dashboard</a>
      <a class="sb-item active" href="account_management.php"><span class="sb-icon">👥</span>Account Management</a>
      <a class="sb-item" href="reports.php"><span class="sb-icon">📊</span>Reports</a>
      <a class="sb-item" href="audit_logs.php"><span class="sb-icon">🛡️</span>Audit Logs</a>
    </nav>

    <div class="sb-section-title">SETTINGS</div>
    <nav class="sb-nav">
      <a class="sb-item" href="../auth/logout.php"><span class="sb-icon">⎋</span>Logout</a>
    </nav>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="hamburger" type="button" onclick="toggleSidebar()">≡</button>

      <div class="brand">
        <div class="logo">
          <img src="../assets/img/edoc-logo.jpeg" alt="E-Doc">
        </div>
        <div>E-Doc Document Requesting System</div>
      </div>
    </header>

    <main class="container">

      <div class="page-head">
        <h1>Account Management</h1>
        <p>Manage user, registrar, and MIS accounts. Review submitted IDs and update account information.</p>
      </div>

      <section class="stats-grid">
        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">Verified</div>
            <div class="stat-value"><?= (int)$acc_verified ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">Unverified</div>
            <div class="stat-value"><?= (int)$acc_unverified ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">Resubmit</div>
            <div class="stat-value"><?= (int)$acc_resubmit ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">Unaffiliated</div>
            <div class="stat-value"><?= (int)$acc_unaffiliated ?></div>
          </div>
        </div>
      </section>

      <?php if ($flash !== ""): ?>
        <div class="flash <?= $flashType === 'error' ? 'flash-error' : 'flash-success' ?>">
          <?= h($flash) ?>
        </div>
      <?php endif; ?>

      <form class="toolbar" method="GET" action="account_management.php">
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search name, ID number, email">

        <select name="status">
          <option value="">-- All Status --</option>
          <option value="VERIFIED" <?= strtoupper($status)==="VERIFIED" ? "selected" : "" ?>>VERIFIED</option>
          <option value="PENDING" <?= strtoupper($status)==="PENDING" ? "selected" : "" ?>>PENDING</option>
          <option value="RESUBMIT" <?= strtoupper($status)==="RESUBMIT" ? "selected" : "" ?>>RESUBMIT</option>
          <option value="UNAFFILIATED" <?= strtoupper($status)==="UNAFFILIATED" ? "selected" : "" ?>>UNAFFILIATED</option>
        </select>

        <select name="role">
          <option value="">-- All Roles --</option>
          <option value="USER" <?= strtoupper($roleFilter)==="USER" ? "selected" : "" ?>>USER</option>
          <option value="REGISTRAR" <?= strtoupper($roleFilter)==="REGISTRAR" ? "selected" : "" ?>>REGISTRAR</option>
          <option value="MIS" <?= strtoupper($roleFilter)==="MIS" ? "selected" : "" ?>>MIS</option>
        </select>

        <button type="submit" class="filter-btn">FILTER</button>
        <button type="button" class="add-btn" onclick="openImportModal()">ADD ACCOUNT +</button>
      </form>

      <section class="table-card">
        <table>
          <thead>
            <tr>
              <th>NAME</th>
              <th>ID Number</th>
              <th>Requests</th>
              <th>Contact</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($rows) === 0): ?>
              <tr><td colspan="8" class="empty-row">No accounts found.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <?php
                  $name = trim(($r["last_name"] ?? "") . ", " . ($r["first_name"] ?? "") . " " . ($r["middle_name"] ?? ""));
                  $acc  = strtoupper($r["verification_status"] ?? "PENDING");
                  $roleVal = strtoupper($r["role"] ?? "USER");
                ?>
                <tr>
                  <td><?= h($name) ?></td>
                  <td><?= h($r["student_id"] ?? "-") ?></td>
                  <td><?= (int)($r["request_count"] ?? 0) ?></td>
                  <td><?= h($r["contact_number"] ?? "-") ?></td>
                  <td><?= h($r["email"] ?? "-") ?></td>
                  <td><?= h($roleVal) ?></td>
                  <td>
                    <span class="status-pill status-<?= strtolower($acc) ?>">
                      <?= h($acc) ?>
                    </span>
                  </td>
                  <td>
                    <a class="tag-btn" href="account_management.php?<?= http_build_query(array_merge($_GET, ['edit' => (int)$r['id']])) ?>">TAG</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="pagination">
          <?php
            $prev = max(1, $page - 1);
            $next = min($totalPages, $page + 1);
          ?>
          <a href="account_management.php?<?= http_build_query(array_merge($_GET, ['page' => $prev])) ?>"><?= $page > 1 ? $prev : 1 ?></a>
          <div class="current-page"><?= $page ?></div>
          <a href="account_management.php?<?= http_build_query(array_merge($_GET, ['page' => $next])) ?>" class="next">NEXT &gt;&gt;&gt;</a>
        </div>
      </section>

    </main>
  </div>
</div>

<!-- BULK IMPORT MODAL -->
<div class="modal-overlay" id="importModal">
  <div class="modal-card import-card">
    <button class="modal-close" type="button" onclick="closeImportModal()">×</button>
    <div class="modal-title">Add Accounts via CSV</div>
    <div class="modal-sub">
      Upload a CSV file with signup fields. Required columns:
      <b>first_name, last_name, email, password</b>
    </div>

    <form method="POST" enctype="multipart/form-data" class="import-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="import_accounts">

      <div class="import-box">
        <label class="file-label">
          <input type="file" name="bulk_file" accept=".csv" required>
        </label>
      </div>

      <div class="sample-box">
        <div class="sample-title">Supported columns</div>
        <div class="sample-text">
          first_name, middle_name, last_name, suffix, gender, contact_number, student_id, year_graduated, course, major, address, email, password, role, verification_status
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeImportModal()">Cancel</button>
        <button type="submit" class="btn-save">Import Accounts</button>
      </div>
    </form>
  </div>
</div>

<?php if ($editUser): ?>
<div class="modal-overlay show" id="editModal">
  <div class="modal-card edit-card">
    <a class="modal-close-link" href="account_management.php?<?= http_build_query(array_diff_key($_GET, ['edit' => 1])) ?>">×</a>

    <div class="modal-title">Edit User</div>
    <div class="modal-sub">Update user information for <?= h($editUser["email"] ?? "") ?></div>

    <div class="upload-head">
      <div class="upload-title">Uploaded ID <span class="upload-count">(<?= count($idFiles) ?>)</span></div>
      <div class="upload-actions">
        <form method="POST" enctype="multipart/form-data" class="inline-upload-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="upload_id">
          <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
          <input type="file" name="id_file" id="id_file_input" accept=".jpg,.jpeg,.png,.webp,.pdf" style="display:none;" onchange="this.form.submit()">
          <button type="button" class="soft-btn" onclick="document.getElementById('id_file_input').click()">Upload</button>
        </form>

        <?php if (count($idFiles) > 0): ?>
          <button type="button" class="soft-btn danger" onclick="openDeleteIdModal()">Delete</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="id-preview-wrap">
      <button type="button" class="nav-btn" onclick="prevIdSlide()">‹</button>

      <div class="id-preview-frame">
        <?php if (count($idFiles) > 0): ?>
          <?php foreach ($idFiles as $idx => $file): ?>
            <?php
              $path = "../" . ltrim((string)$file["file_path"], "/");
              $ext = strtolower(pathinfo((string)$file["file_path"], PATHINFO_EXTENSION));
            ?>
            <div class="id-slide <?= $idx === 0 ? 'active' : '' ?>" data-file-path="<?= h($file['file_path']) ?>">
              <?php if (in_array($ext, ["jpg","jpeg","png","webp"])): ?>
                <img src="<?= h($path) ?>" alt="Uploaded ID">
              <?php else: ?>
                <iframe src="<?= h($path) ?>" class="pdf-viewer"></iframe>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-id">No uploaded ID found for this user yet.</div>
        <?php endif; ?>
      </div>

      <button type="button" class="nav-btn" onclick="nextIdSlide()">›</button>
    </div>

    <?php if (count($idFiles) > 1): ?>
      <div class="dots">
        <?php foreach ($idFiles as $idx => $file): ?>
          <span class="dot <?= $idx === 0 ? 'active' : '' ?>" onclick="goToIdSlide(<?= $idx ?>)"></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="edit-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_user">
      <input type="hidden" name="user_id" value="<?= (int)$editUser["id"] ?>">

      <div class="section-title">Personal Information</div>
      <div class="grid-2">
        <div class="form-row">
          <label>First Name</label>
          <input type="text" name="first_name" value="<?= h($editUser["first_name"] ?? "") ?>" required>
        </div>
        <div class="form-row">
          <label>Middle Name</label>
          <input type="text" name="middle_name" value="<?= h($editUser["middle_name"] ?? "") ?>">
        </div>

        <div class="form-row">
          <label>Last Name</label>
          <input type="text" name="last_name" value="<?= h($editUser["last_name"] ?? "") ?>" required>
        </div>
        <div class="form-row">
          <label>Suffix</label>
          <input type="text" name="suffix" value="<?= h($editUser["suffix"] ?? "") ?>">
        </div>

        <div class="form-row">
          <label>Gender</label>
          <select name="gender">
            <option value="">Select gender</option>
            <option value="Male" <?= ($editUser["gender"] ?? "") === "Male" ? "selected" : "" ?>>Male</option>
            <option value="Female" <?= ($editUser["gender"] ?? "") === "Female" ? "selected" : "" ?>>Female</option>
          </select>
        </div>
        <div class="form-row">
          <label>Contact Number</label>
          <input type="text" name="contact_number" value="<?= h($editUser["contact_number"] ?? "") ?>">
        </div>
      </div>

      <div class="section-title">Academic Information</div>
      <div class="grid-2">
        <div class="form-row">
          <label>ID Number</label>
          <input type="text" name="student_id" value="<?= h($editUser["student_id"] ?? "") ?>">
        </div>
        <div class="form-row">
          <label>Year Graduated</label>
          <input type="text" name="year_graduated" value="<?= h($editUser["year_graduated"] ?? "") ?>">
        </div>

        <div class="form-row">
          <label>Course / Program</label>
          <input type="text" name="course" value="<?= h($editUser["course"] ?? "") ?>">
        </div>
        <div class="form-row">
          <label>Major</label>
          <input type="text" name="major" value="<?= h($editUser["major"] ?? "") ?>">
        </div>
      </div>

      <div class="form-row">
        <label>Complete Address</label>
        <input type="text" name="address" value="<?= h($editUser["address"] ?? "") ?>">
      </div>

      <div class="section-title">Account Settings</div>
      <div class="grid-2">
        <div class="form-row">
          <label>User Type</label>
          <select name="role">
            <option value="USER" <?= ($editUser["role"] ?? "") === "USER" ? "selected" : "" ?>>User</option>
            <option value="MIS" <?= ($editUser["role"] ?? "") === "MIS" ? "selected" : "" ?>>MIS Admin</option>
            <option value="REGISTRAR" <?= ($editUser["role"] ?? "") === "REGISTRAR" ? "selected" : "" ?>>Registrar</option>
          </select>
        </div>
        <div class="form-row">
          <label>Account Status</label>
          <select name="verification_status">
            <option value="PENDING" <?= strtoupper($editUser["verification_status"] ?? "") === "PENDING" ? "selected" : "" ?>>Pending</option>
            <option value="VERIFIED" <?= strtoupper($editUser["verification_status"] ?? "") === "VERIFIED" ? "selected" : "" ?>>Verified</option>
            <option value="RESUBMIT" <?= strtoupper($editUser["verification_status"] ?? "") === "RESUBMIT" ? "selected" : "" ?>>Resubmit</option>
            <option value="UNAFFILIATED" <?= strtoupper($editUser["verification_status"] ?? "") === "UNAFFILIATED" ? "selected" : "" ?>>Unaffiliated</option>
          </select>
        </div>
      </div>

      <div class="section-title">Change Password</div>
      <div class="grid-1">
        <div class="form-row">
          <label>New Password <span class="muted">(leave blank to keep unchanged)</span></label>
          <input type="password" name="new_password" placeholder="Enter new password">
        </div>
      </div>

      <div class="form-actions">
        <a href="account_management.php?<?= http_build_query(array_diff_key($_GET, ['edit' => 1])) ?>" class="btn-cancel">Cancel</a>
        <button type="submit" class="btn-save">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($editUser && count($idFiles) > 0): ?>
<div class="modal-overlay" id="deleteIdModal">
  <div class="modal-card delete-card">
    <button class="modal-close" type="button" onclick="closeDeleteIdModal()">×</button>

    <div class="delete-title">Delete ID Image</div>
    <div class="delete-sub">This will remove the selected ID image and notify the user to resubmit.</div>

    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_id_notify">
      <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
      <input type="hidden" name="file_path" id="delete_file_path" value="<?= h($idFiles[0]['file_path'] ?? '') ?>">

      <div class="form-row">
        <label>Reason / Notes to User <span class="muted">(optional)</span></label>
        <textarea name="delete_reason" rows="5" placeholder="e.g. The image is blurry or unreadable. Please resubmit a clear photo of your valid ID."></textarea>
      </div>

      <div class="delete-note">
        A notification message will be logged so the user can be informed that their ID was removed and needs resubmission.
      </div>

      <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="closeDeleteIdModal()">Cancel</button>
        <button type="submit" class="btn-delete">Delete &amp; Notify User</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($_GET['msg'])): ?>
  <div class="toast show" id="toastMessage">
    <?php
      if ($_GET['msg'] === 'saved') echo 'Changes saved successfully.';
      elseif ($_GET['msg'] === 'uploaded') echo 'ID uploaded successfully.';
      elseif ($_GET['msg'] === 'deleted') echo 'ID deleted and user notified successfully.';
      else echo 'Action completed successfully.';
    ?>
  </div>
<?php endif; ?>

<div class="footer-bar"></div>

<script>
function toggleSidebar(){
  const sb = document.getElementById('sidebar');
  if(!sb) return;
  if (window.innerWidth <= 720) {
    sb.style.display = (sb.style.display === 'none' || sb.style.display === '') ? 'block' : 'none';
  }
}

function openImportModal(){
  document.getElementById('importModal').classList.add('show');
}
function closeImportModal(){
  document.getElementById('importModal').classList.remove('show');
}

function openDeleteIdModal(){
  const modal = document.getElementById('deleteIdModal');
  if (modal) modal.classList.add('show');
}

function closeDeleteIdModal(){
  const modal = document.getElementById('deleteIdModal');
  if (modal) modal.classList.remove('show');
}

let currentIdSlide = 0;
const slides = document.querySelectorAll('.id-slide');
const dots = document.querySelectorAll('.dot');

function syncDeleteFilePath(){
  const activeSlide = document.querySelector('.id-slide.active');
  const hiddenInput = document.getElementById('delete_file_path');
  if (!activeSlide || !hiddenInput) return;
  const path = activeSlide.getAttribute('data-file-path');
  if (path) hiddenInput.value = path;
}

function showIdSlide(index){
  if (!slides.length) return;
  if (index < 0) index = slides.length - 1;
  if (index >= slides.length) index = 0;
  currentIdSlide = index;

  slides.forEach((slide, i) => {
    slide.classList.toggle('active', i === index);
  });

  dots.forEach((dot, i) => {
    dot.classList.toggle('active', i === index);
  });

  syncDeleteFilePath();
}

function nextIdSlide(){
  showIdSlide(currentIdSlide + 1);
}

function prevIdSlide(){
  showIdSlide(currentIdSlide - 1);
}

function goToIdSlide(index){
  showIdSlide(index);
}

syncDeleteFilePath();

setTimeout(() => {
  const toast = document.getElementById('toastMessage');
  if (toast) {
    toast.classList.remove('show');
  }
}, 2500);
</script>

</body>
</html>