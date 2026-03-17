<?php
// registrar/track_progress.php
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

/* ===== stats bar counts (same as request_management) ===== */
function countRequestsByStatus($conn, $status){
  $sql="SELECT COUNT(*) c FROM requests WHERE UPPER(status)=?";
  $st=strtoupper($status);
  $q=$conn->prepare($sql);
  $q->bind_param("s",$st);
  $q->execute();
  return (int)($q->get_result()->fetch_assoc()["c"] ?? 0);
}
$cnt_pending    = countRequestsByStatus($conn,"PENDING");
$cnt_returned   = countRequestsByStatus($conn,"RETURNED");
$cnt_verified   = countRequestsByStatus($conn,"VERIFIED");
$cnt_approved   = countRequestsByStatus($conn,"APPROVED");
$cnt_processing = countRequestsByStatus($conn,"PROCESSING");
$cnt_ready      = countRequestsByStatus($conn,"READY FOR PICKUP");

/* ===== helpers ===== */
function badgeClass($s){
  $s = strtoupper(trim((string)$s));
  return match($s){
    "RETURNED" => "returned",
    "VERIFIED" => "verified",
    "APPROVED" => "approved",
    "PROCESSING" => "processing",
    "READY FOR PICKUP" => "ready",
    "COMPLETED" => "completed",
    default => "pending"
  };
}

/* ===== filter inputs ===== */
$q        = trim($_GET["q"] ?? "");
$docType  = trim($_GET["doc_type"] ?? "");
$gradYear = trim($_GET["grad_year"] ?? "");
$status   = trim($_GET["status"] ?? "");

$allowedStatuses = ["","PENDING","RETURNED","VERIFIED","APPROVED","PROCESSING","READY FOR PICKUP","COMPLETED","CANCELLED","RELEASED"];
if (!in_array(strtoupper($status), $allowedStatuses, true)) $status = "";

/* ===== pagination ===== */
$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

/* ===== details mode ===== */
$detailId = (int)($_GET["id"] ?? 0);

/* ===== Document types distinct ===== */
$docTypes = [];
$dt = $conn->query("SELECT DISTINCT document_type FROM requests ORDER BY document_type ASC");
if ($dt) while($r=$dt->fetch_assoc()) $docTypes[] = $r["document_type"];

/* ===== Graduation years distinct ===== */
$gradYears = [];
$gy = $conn->query("SELECT DISTINCT year_graduated FROM users WHERE role='USER' AND year_graduated IS NOT NULL AND year_graduated <> '' ORDER BY year_graduated DESC");
if ($gy) while($r=$gy->fetch_assoc()) $gradYears[] = $r["year_graduated"];

/* ===== LIST MODE DATA ===== */
$rows = [];
$totalPages = 1;

if ($detailId <= 0) {

  /* ===== build WHERE for list ===== */
  $where = " WHERE 1=1 ";
  $params = [];
  $types = "";

  if ($q !== "") {
    $where .= " AND (
      CONCAT(u.last_name, ', ', u.first_name, ' ', u.middle_name) LIKE ?
      OR u.student_id LIKE ?
      OR r.reference_no LIKE ?
    )";
    $like = "%".$q."%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
  }

  if ($docType !== "") {
    $where .= " AND r.document_type = ?";
    $params[] = $docType;
    $types .= "s";
  }

  if ($gradYear !== "") {
    $where .= " AND u.year_graduated = ?";
    $params[] = $gradYear;
    $types .= "s";
  }

  if ($status !== "") {
    $where .= " AND UPPER(r.status) = ?";
    $params[] = strtoupper($status);
    $types .= "s";
  }

  /* ===== total count ===== */
  $sqlCount = "
    SELECT COUNT(*) c
    FROM requests r
    JOIN users u ON u.id = r.user_id
    $where
  ";
  $stmtC = $conn->prepare($sqlCount);
  if ($types !== "") $stmtC->bind_param($types, ...$params);
  $stmtC->execute();
  $total = (int)($stmtC->get_result()->fetch_assoc()["c"] ?? 0);
  $totalPages = max(1, (int)ceil($total / $perPage));

  /* ===== list query ===== */
  $sql = "
    SELECT
      r.id, r.reference_no, r.document_type, r.status, r.created_at, r.updated_at,
      u.first_name, u.middle_name, u.last_name, u.student_id, u.year_graduated
    FROM requests r
    JOIN users u ON u.id = r.user_id
    $where
    ORDER BY r.updated_at DESC, r.id DESC
    LIMIT $perPage OFFSET $offset
  ";
  $stmt = $conn->prepare($sql);
  if ($types !== "") $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ===== DETAIL MODE DATA ===== */
$detail = null;
$reqFiles = [];
$logs = [];
$reqs = [];
$filesByKey = [];

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

if ($detailId > 0) {
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
  $st->bind_param("i", $detailId);
  $st->execute();
  $detail = $st->get_result()->fetch_assoc();

  if ($detail) {
    // request_files
    $f = $conn->prepare("SELECT * FROM request_files WHERE request_id=?");
    $f->bind_param("i", $detailId);
    $f->execute();
    $reqFiles = $f->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($reqFiles as $fr) {
      $k = (string)($fr["requirement_key"] ?? "");
      if ($k !== "") $filesByKey[$k] = $fr;
    }

    // logs
    $lg = $conn->prepare("SELECT message, created_at FROM request_logs WHERE request_id=? ORDER BY created_at DESC, id DESC LIMIT 60");
    $lg->bind_param("i", $detailId);
    $lg->execute();
    $logs = $lg->get_result()->fetch_all(MYSQLI_ASSOC);

    // requirements
    $doc_type_raw   = (string)($detail["document_type"] ?? "");
    $title_type_raw = (string)($detail["title_type"] ?? "");
    $doc_type_db   = strtoupper(trim($doc_type_raw));
    $title_type_db = normalize_title_type($doc_type_db, $title_type_raw);

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

    if (count($reqs) === 0) {
      $reqs = [
        ["requirement_key" => "valid_id", "req_name" => "Valid ID"]
      ];
    }

    // dedupe keys
    $seen = [];
    $deduped = [];
    foreach ($reqs as $r) {
      $k = (string)($r["requirement_key"] ?? "");
      if ($k === "" || isset($seen[$k])) continue;
      $seen[$k] = true;
      $deduped[] = $r;
    }
    $reqs = $deduped;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Track Progress</title>
  <!-- same layout look -->
  <link rel="stylesheet" href="../assets/css/registrar_request_management.css">
  <!-- page-specific -->
  <link rel="stylesheet" href="../assets/css/registrar_track_progress.css">
</head>
<body>

<div class="layout">

  <!-- SIDEBAR -->
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
      <a class="sb-item" href="new_document_request.php"><span class="sb-icon">📝</span>New Document Request</a>
      <a class="sb-item" href="request_management.php"><span class="sb-icon">🔎</span>Request Management</a>
      <a class="sb-item active" href="track_progress.php"><span class="sb-icon">📍</span>Track Progress</a>
      <a class="sb-item" href="document_management.php"><span class="sb-icon">📄</span>Document Management</a>
      <a class="sb-item" href="create_document.php"><span class="sb-icon">➕</span>Create Document</a>
    </nav>

    <div class="sb-section-title">SETTINGS</div>
    <nav class="sb-nav">
      <a class="sb-item" href="../auth/logout.php"><span class="sb-icon">⎋</span>Logout</a>
    </nav>
  </aside>

  <div class="main">

    <!-- TOPBAR -->
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

      <?php if ($detailId > 0): ?>
        <?php if (!$detail): ?>
          <div class="tp-notfound">
            Request not found.
            <a class="tp-back-btn" href="track_progress.php">← Back</a>
          </div>
        <?php else: ?>
          <?php
            $fullName = trim(($detail["first_name"] ?? "")." ".($detail["middle_name"] ?? "")." ".($detail["last_name"] ?? "")." ".($detail["suffix"] ?? ""));
            $accountStatus = strtoupper($detail["verification_status"] ?? "PENDING");
            $reqStatus = strtoupper($detail["status"] ?? "PENDING");

            $scannedFiles = [];
            foreach ($reqFiles as $rf) {
              $rk = strtolower((string)($rf["requirement_key"] ?? ""));
              $rn = strtolower((string)($rf["requirement_name"] ?? ""));
              if (str_starts_with($rk, "scanned") || str_contains($rn, "scanned")) {
                if (!empty($rf["file_path"])) $scannedFiles[] = $rf;
              }
            }
          ?>

          <!-- ✅ DETAIL HEADER ON TOP -->
          <div class="tp-detail-header">
            <div class="title">Track Progress</div>
            <a class="tp-back-btn" href="track_progress.php">← Back to list</a>
          </div>

          <div class="tp-detail">
            <!-- LEFT -->
            <div class="tp-detail-left">
              <div class="tp-section">
                <div class="tp-h1">Track Progress</div>
                <div class="tp-sub">Track and view the status of the document request</div>
              </div>

              <div class="tp-section">
                <div class="tp-h2">Requestor Information</div>
                <div class="tp-row"><b>Name:</b> <?= h($fullName) ?></div>
                <div class="tp-row"><b>Suffix:</b> <?= h($detail["suffix"] ?: "N/A") ?> &nbsp;&nbsp; <b>Gender:</b> <?= h($detail["gender"] ?: "N/A") ?></div>
                <div class="tp-row"><b>ID Number:</b> <?= h($detail["student_id"] ?: "N/A") ?></div>
                <div class="tp-row"><b>Email:</b> <?= h($detail["email"] ?: "N/A") ?></div>
                <div class="tp-row"><b>Contact Number:</b> <?= h($detail["contact_number"] ?: "N/A") ?></div>
                <div class="tp-row"><b>Course/Program:</b> <?= h($detail["course"] ?: "N/A") ?></div>
                <div class="tp-row"><b>Major:</b> <?= h($detail["major"] ?: "N/A") ?></div>
                <div class="tp-row"><b>Year Graduated:</b> <?= h($detail["year_graduated"] ?: "N/A") ?></div>
                <div class="tp-row">
                  <b>Account Status:</b>
                  <span class="pill <?= $accountStatus==="VERIFIED" ? "pill-green" : "pill-gray" ?>">
                    <?= h($accountStatus==="VERIFIED" ? "✓ VERIFIED" : $accountStatus) ?>
                  </span>
                </div>
              </div>

              <div class="tp-section">
                <div class="tp-h2">Document Request Status</div>
                <div class="tp-row"><b>Reference Number:</b> <?= h($detail["reference_no"]) ?></div>
                <div class="tp-row"><b>Document:</b> <?= h(strtoupper($detail["document_type"] ?? "")) ?></div>
                <div class="tp-row"><b>Number of Copies:</b> <?= (int)($detail["copies"] ?? 1) ?></div>
                <div class="tp-row"><b>Requested on:</b> <?= h(date("F j, Y", strtotime($detail["created_at"] ?? "now"))) ?></div>
                <div class="tp-row"><b>Last Updated:</b> <?= h(date("F j, Y", strtotime($detail["updated_at"] ?? ($detail["created_at"] ?? "now")))) ?></div>
                <div class="tp-row"><b>Title Type:</b> <?= h($detail["title_type"] ?: "N/A") ?></div>
              </div>

              <div class="tp-section">
                <div class="tp-h2">Requirements</div>

                <?php foreach ($reqs as $r): ?>
                  <?php
                    $rk = (string)$r["requirement_key"];
                    $row = $filesByKey[$rk] ?? null;
                    $isVerified = ($row && !empty($row["verified_at"]));
                  ?>
                  <div class="tp-req-row">
                    <div class="tp-req-name"><?= h(strtoupper($r["req_name"])) ?></div>
                    <span class="tp-req-pill <?= $isVerified ? "ok" : "pending" ?>">
                      <?= $isVerified ? "✓ VERIFIED" : "PENDING" ?>
                    </span>
                    <a class="tp-view" href="verify_request.php?id=<?= (int)$detailId ?>&rk=<?= urlencode($rk) ?>">view &gt;&gt;&gt;</a>
                  </div>
                <?php endforeach; ?>

                <?php if (count($scannedFiles) > 0): ?>
                  <div class="tp-h2 tp-mt">Scanned Document</div>
                  <?php foreach ($scannedFiles as $sf): ?>
                    <div class="tp-scan">
                      <a target="_blank" href="../<?= h($sf["file_path"]) ?>">
                        <?= h($sf["requirement_name"] ?: basename($sf["file_path"])) ?>
                      </a>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>

                <div class="tp-h2 tp-mt">SELECT Application Status:</div>
                <span class="badge <?= badgeClass($reqStatus) ?> tp-big-badge"><?= h($reqStatus) ?></span>
              </div>
            </div>

            <!-- RIGHT -->
            <div class="tp-detail-right">
              <div class="tp-section">
                <div class="tp-h1">Tracking History</div>

                <?php if (count($logs) === 0): ?>
                  <div class="tp-log-empty">No history yet.</div>
                <?php else: ?>
                  <div class="tp-logs">
                    <?php foreach ($logs as $l): ?>
                      <div class="tp-log-item">
                        <div class="tp-log-msg"><?= h(strtoupper($l["message"] ?? "")) ?></div>
                        <div class="tp-log-time"><?= h(date("m/d/y, g:i A", strtotime($l["created_at"] ?? "now"))) ?></div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

      <?php else: ?>

        <!-- FILTER BAR -->
        <form class="filters tp-filters" method="GET" action="track_progress.php">
          <div class="title">Track Progress</div>

          <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search Name, ID Number, Reference Number">

          <select name="doc_type">
            <option value="">Document Type</option>
            <?php foreach($docTypes as $d): ?>
              <option value="<?= h($d) ?>" <?= ($docType===$d ? "selected":"") ?>><?= h($d) ?></option>
            <?php endforeach; ?>
          </select>

          <select name="grad_year">
            <option value="">---Date of Grad---</option>
            <?php foreach($gradYears as $y): ?>
              <option value="<?= h($y) ?>" <?= ($gradYear===$y ? "selected":"") ?>><?= h($y) ?></option>
            <?php endforeach; ?>
          </select>

          <select name="status">
            <option value="">-- All Status --</option>
            <?php foreach(["PENDING","RETURNED","VERIFIED","APPROVED","PROCESSING","READY FOR PICKUP","COMPLETED"] as $st): ?>
              <option value="<?= $st ?>" <?= (strtoupper($status)===$st ? "selected":"") ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>

          <button type="submit">FILTER</button>
        </form>

        <!-- LIST TABLE -->
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th style="width:260px;">NAME</th>
                <th style="width:120px;">ID Number</th>
                <th style="width:140px;">Ref. Num.</th>
                <th style="width:90px;">DATE</th>
                <th>Document Type</th>
                <th style="width:110px;">Verified By</th>
                <th style="width:110px;">Last Updated</th>
                <th style="width:160px;">Application Status</th>
                <th style="width:140px;">ACTION</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($rows)===0): ?>
                <tr><td colspan="9">No requests found.</td></tr>
              <?php else: ?>
                <?php foreach($rows as $r): ?>
                  <?php
                    $name = strtoupper(trim(($r["last_name"]??"").", ".($r["first_name"]??"")." ".($r["middle_name"]??"")));
                    $date = $r["created_at"] ? date("m/d/y", strtotime($r["created_at"])) : "-";
                    $lastUpd = $r["updated_at"] ? date("m/d/y", strtotime($r["updated_at"])) : "-";

                    $vb="-";
                    $info = $conn->prepare("
                      SELECT verified_by
                      FROM request_files
                      WHERE request_id = ?
                        AND verified_at IS NOT NULL
                      ORDER BY verified_at DESC
                      LIMIT 1
                    ");
                    $info->bind_param("i", $r["id"]);
                    $info->execute();
                    $iv = $info->get_result()->fetch_assoc();
                    if ($iv && !empty($iv["verified_by"])) $vb = "R1";

                    $st = strtoupper($r["status"] ?? "PENDING");
                  ?>
                  <tr>
                    <td><?= h($name) ?></td>
                    <td><?= h($r["student_id"] ?? "-") ?></td>
                    <td><?= h($r["reference_no"]) ?></td>
                    <td><?= h($date) ?></td>
                    <td><?= h(strtoupper($r["document_type"])) ?></td>
                    <td><?= h($vb) ?></td>
                    <td><?= h($lastUpd) ?></td>
                    <td><span class="badge <?= badgeClass($st) ?>"><?= h($st) ?></span></td>
                    <td>
                      <a class="btn-verify" href="track_progress.php?id=<?= (int)$r["id"] ?>">Track Progress &gt;</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

          <div class="pagination">
    <?php
        // Preserve current URL parameters but update the page index
        $qs = $_GET;
        $prev = max(1, $page - 1);
        $next = min($totalPages, $page + 1);
    ?>

    <?php if ($page > 1): ?>
        <?php $qs["page"] = $prev; ?>
        <a href="request_management.php?<?= http_build_query($qs) ?>" class="next">
            <<< BACK
        </a>
    <?php endif; ?>

    <div class="page-number"><?= $page ?></div>

    <?php if ($page < $totalPages): ?>
        <?php $qs["page"] = $next; ?>
        <a href="request_management.php?<?= http_build_query($qs) ?>" class="next">
            NEXT >>>
        </a>
    <?php endif; ?>
</div>

      <?php endif; ?>

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
</script>

</body>
</html>