<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_REGISTRAR);
require_once __DIR__ . "/../includes/compliance.php";

$registrarName = "Registrar";
$registrarLabel = "Registrar";

$me = $conn->prepare("SELECT first_name, last_name, role FROM users WHERE id = ? LIMIT 1");
$me->bind_param("i", $_SESSION["user_id"]);
$me->execute();
$meRow = $me->get_result()->fetch_assoc();
if ($meRow) {
  $registrarName = trim(($meRow["first_name"] ?? "") . " " . ($meRow["last_name"] ?? ""));
  $registrarLabel = $meRow["role"] ?? "REGISTRAR";
}

/* =========================
   ACCOUNT MANAGEMENT COUNTS
   =========================
   Assumptions:
   - users.verification_status: VERIFIED / PENDING / RESUBMIT / UNAFFILIATED
   If your DB uses different values, tell me and I will adjust.
*/
function countUsersByStatus($conn, $status) {
  $sql = "SELECT COUNT(*) AS c FROM users WHERE role = 'USER' AND UPPER(verification_status) = ?";
  $stmt = $conn->prepare($sql);
  $s = strtoupper($status);
  $stmt->bind_param("s", $s);
  $stmt->execute();
  return (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
}

$acc_verified     = countUsersByStatus($conn, "VERIFIED");
$acc_unverified   = countUsersByStatus($conn, "PENDING");
$acc_resubmit     = countUsersByStatus($conn, "RESUBMIT");
$acc_unaffiliated = countUsersByStatus($conn, "UNAFFILIATED");

/* =========================
   TRACK PROGRESS COUNTS
   =========================
   Assumptions:
   - requests.status: PENDING, RETURNED, VERIFIED, APPROVED, PROCESSING, READY FOR PICKUP
*/
function countRequestsByStatus($conn, $status) {
  $sql = "SELECT COUNT(*) AS c FROM requests WHERE UPPER(status) = ?";
  $stmt = $conn->prepare($sql);
  $s = strtoupper($status);
  $stmt->bind_param("s", $s);
  $stmt->execute();
  return (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
}

$trk_incoming   = countRequestsByStatus($conn, "PENDING");
$trk_returned   = countRequestsByStatus($conn, "RETURNED");
$trk_verified   = countRequestsByStatus($conn, "VERIFIED");
$trk_approved   = countRequestsByStatus($conn, "APPROVED");
$trk_processing = countRequestsByStatus($conn, "PROCESSING");
$trk_ready      = countRequestsByStatus($conn, "READY FOR PICKUP");
$nonCompliantCount = count_non_compliant($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Registrar Dashboard</title>
  <link rel="stylesheet" href="../assets/css/registrar_dashboard.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
</head>
<body>

<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sb-user">
      <div class="avatar">👤</div>
      <div class="meta">
        <div class="name"><?= htmlspecialchars($registrarName) ?></div>
        <div class="role"><?= htmlspecialchars($registrarLabel) ?></div>
      </div>
    </div>

    <div class="sb-section-title">MODULES</div>
    <nav class="sb-nav">
      <a class="sb-item active" href="dashboard.php"><span class="sb-icon">🏠</span>Dashboard</a>
      <a class="sb-item" href="new_document_request.php"><span class="sb-icon">📝</span>New Document Request</a>
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

  <!-- MAIN -->
  <div class="main">

    <!-- TOPBAR -->
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
      <div class="title">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($registrarLabel) ?>. System overview and management.</p>
      </div>

      <!-- TRACK PROGRESS -->
      <div class="section-label">TRACK PROGRESS</div>

      <div class="cards-track">
        <div class="track-left">
          <a class="card clickable" href="track_progress.php?status=PENDING">
            <div class="big"><?= (int)$trk_incoming ?></div>
            <div class="desc"><b>Incoming (Pending):</b> The digital document has been successfully submitted and is awaiting initial review.</div>
          </a>

          <a class="card clickable" href="track_progress.php?status=RETURNED">
            <div class="big"><?= (int)$trk_returned ?></div>
            <div class="desc"><b>Returned (Resubmit):</b> Errors or missing info were found; the document is sent back for correction.</div>
          </a>

          <a class="card clickable" href="track_progress.php?status=VERIFIED">
            <div class="big"><?= (int)$trk_verified ?></div>
            <div class="desc"><b>Verified (Submit Soft Copy):</b> Individual documents have been checked and confirmed as correct.</div>
          </a>

          <a class="card clickable" href="track_progress.php?status=APPROVED">
            <div class="big"><?= (int)$trk_approved ?></div>
            <div class="desc"><b>Approved (Submit Hard Copy):</b> All files are verified; submit physical original documents.</div>
          </a>

          <a class="card clickable" href="track_progress.php?status=PROCESSING">
            <div class="big"><?= (int)$trk_processing ?></div>
            <div class="desc"><b>Processing (Submission):</b> Physical documents have been received and are undergoing final formal handling.</div>
          </a>

          <a class="card clickable" href="track_progress.php?status=READY%20FOR%20PICKUP">
            <div class="big"><?= (int)$trk_ready ?></div>
            <div class="desc"><b>Ready for Pickup:</b> The process is finished; your items are ready for collection.</div>
          </a>
        </div>

        <a class="card report-card track-right">
          <div class="report-icon">📈</div>
          <div class="report-text">Reports &amp; Analytics</div>
        </a>
      </div>

      <div class="section-label">COMPLIANCE</div>
      <div class="cards-compliance">
        <a class="card clickable card-compliance" href="non_compliant.php">
          <div class="big compliance-count"><?= (int)$nonCompliantCount ?></div>
          <div class="desc"><b>Non-Compliant Users:</b> Students with missing documents, resubmission required, or abandoned requests that need follow-up.</div>
        </a>
      </div>
    </main>
  </div>
</div>
<div class="footer-bar"></div>

<script>
function toggleSidebar(){
  const sb = document.getElementById('sidebar');
  if (!sb) return;
  // simple toggle for small screens
  if (sb.style.display === 'none') sb.style.display = 'block';
  else if (window.innerWidth <= 720) sb.style.display = 'none';
}
</script>

</body>
</html>