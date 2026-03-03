<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "REGISTRAR") {
  header("Location: ../auth/auth.php");
  exit();
}

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

/* Filters */
$q = trim($_GET["q"] ?? "");
$docType = trim($_GET["doc_type"] ?? "");
$status = trim($_GET["status"] ?? "");

$allowedStatuses = ["","PENDING","RETURNED","VERIFIED","APPROVED","PROCESSING","READY FOR PICKUP","COMPLETED","CANCELLED","RELEASED"];
if (!in_array(strtoupper($status), $allowedStatuses, true)) $status = "";

/* Pagination */
$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

/* WHERE */
$where = " WHERE 1=1 ";
$params = [];
$types = "";

if ($q !== "") {
  $where .= " AND (CONCAT(u.last_name, ', ', u.first_name, ' ', u.middle_name) LIKE ? OR u.student_id LIKE ? OR r.reference_no LIKE ?)";
  $like = "%".$q."%";
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= "sss";
}
if ($docType !== "") {
  $where .= " AND r.document_type = ?";
  $params[] = $docType;
  $types .= "s";
}
if ($status !== "") {
  $where .= " AND UPPER(r.status) = ?";
  $params[] = strtoupper($status);
  $types .= "s";
}

/* total */
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

/* FIFO list (oldest first) */
$sql = "
  SELECT
    r.id, r.reference_no, r.document_type, r.status, r.created_at,
    u.first_name, u.middle_name, u.last_name, u.student_id
  FROM requests r
  JOIN users u ON u.id = r.user_id
  $where
  ORDER BY r.created_at ASC, r.id ASC
  LIMIT $perPage OFFSET $offset
";
$stmt = $conn->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Document types (distinct) */
$docTypes = [];
$dt = $conn->query("SELECT DISTINCT document_type FROM requests ORDER BY document_type ASC");
if ($dt) while($r=$dt->fetch_assoc()) $docTypes[]=$r["document_type"];

/* Helpers */
function badgeClass($s){
  $s=strtoupper(trim($s));
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Request Management</title>
  <link rel="stylesheet" href="../assets/css/registrar_request_management.css">
</head>
<body>

<div class="layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sb-user">
      <div class="avatar">👤</div>
      <div class="meta">
        <div class="name"><?= htmlspecialchars($registrarName) ?></div>
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
      <form class="filters" method="GET" action="request_management.php">
        <div class="title">Request Management</div>

        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search Name, ID Number, Reference Number">

        <select name="doc_type">
          <option value="">Document Type</option>
          <?php foreach($docTypes as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>" <?= ($docType===$d ? "selected":"") ?>>
              <?= htmlspecialchars($d) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select name="status">
          <option value="">-- All Status --</option>
          <?php foreach(["PENDING","RETURNED","VERIFIED","APPROVED","PROCESSING","READY FOR PICKUP","COMPLETED"] as $st): ?>
            <option value="<?= $st ?>" <?= (strtoupper($status)=== $st ? "selected":"") ?>>
              <?= $st ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button type="submit">FILTER</button>
      </form>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:230px;">NAME</th>
              <th style="width:110px;">ID Number</th>
              <th style="width:140px;">Ref. Num.</th>
              <th style="width:90px;">DATE</th>
              <th>Document Type</th>
              <th style="width:90px;">Verified By</th>
              <th style="width:100px;">Date Verified</th>
              <th style="width:140px;">Application Status</th>
              <th style="width:90px;">ACTION</th>
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

                  // Get verification info from request_files (latest verified)
                  $vb="-"; $vd="-";
                  $info = $conn->prepare("
                    SELECT rf.verified_by, rf.verified_at
                    FROM request_files rf
                    WHERE rf.request_id = ?
                      AND rf.verified_at IS NOT NULL
                    ORDER BY rf.verified_at DESC
                    LIMIT 1
                  ");
                  $info->bind_param("i", $r["id"]);
                  $info->execute();
                  $iv = $info->get_result()->fetch_assoc();
                  if ($iv && $iv["verified_by"]) {
                    $vb = "R1"; // if you want dynamic registrar code, we can map it later
                    $vd = date("m/d/y", strtotime($iv["verified_at"]));
                  }

                  $st = strtoupper($r["status"] ?? "PENDING");
                ?>
                <tr>
                  <td><?= htmlspecialchars($name) ?></td>
                  <td><?= htmlspecialchars($r["student_id"] ?? "-") ?></td>
                  <td><?= htmlspecialchars($r["reference_no"]) ?></td>
                  <td><?= htmlspecialchars($date) ?></td>
                  <td><?= htmlspecialchars(strtoupper($r["document_type"])) ?></td>
                  <td><?= htmlspecialchars($vb) ?></td>
                  <td><?= htmlspecialchars($vd) ?></td>
                  <td><span class="badge <?= badgeClass($st) ?>"><?= htmlspecialchars($st) ?></span></td>
                  <td>
                    <a class="btn-verify" href="verify_request.php?id=<?= (int)$r["id"] ?>">VERIFY</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="pagination">
          <?php
            $qs = $_GET;
            $prev = max(1, $page-1);
            $next = min($totalPages, $page+1);
            $qs["page"] = $prev;
          ?>
          <a href="request_management.php?<?= http_build_query($qs) ?>"><?= $page>1 ? $prev : 1 ?></a>
          <div><?= $page ?></div>
          <a href="request_management.php?<?= http_build_query(array_merge($_GET,["page"=>$next])) ?>" class="next">NEXT &gt;&gt;&gt;</a>
        </div>
      </div>

      
    </main>
  </div>
</div>
<div class="footer-bar"></div>

<script>
function toggleSidebar(){
  const sb=document.getElementById('sidebar');
  if(!sb) return;
  if(window.innerWidth<=720){
    sb.style.display = (sb.style.display==='none') ? 'block' : 'none';
  }
}
</script>

</body>
</html>