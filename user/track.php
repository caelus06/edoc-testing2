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
  swal_flash("error", "Error", "Request not found or not yours.");
  header("Location: dashboard.php");
  exit();
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
  $uploadedMap[$key] = $u;
}

// status pill class
function status_class($statusRaw){
  $s = strtoupper(trim($statusRaw ?? ""));
  return match ($s) {
    "PENDING"          => "status-pending",
    "RETURNED"         => "status-returned",
    "VERIFIED","APPROVED" => "status-approved",
    "PROCESSING"       => "status-processing",
    "READY FOR PICKUP","RELEASED","COMPLETED" => "status-completed",
    "CANCELLED"        => "status-returned",
    default            => "status-pending",
  };
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

// Notifications
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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Track Progress</title>
  <link rel="stylesheet" href="../assets/css/track.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div>E-Doc Document Requesting System</div>
  </div>
  <div class="top-icons">
    <span class="notif-wrap">
      <button class="icon-btn" id="notifBtn" title="Notifications" type="button"><i class="bi bi-bell"></i></button>
      <?php if ($badgeCount > 0): ?>
        <span class="notif-badge" id="notifBadge"><?= (int)$badgeCount ?></span>
      <?php endif; ?>
    </span>
    <div class="icon-btn" title="Profile"><a href="profile.php"><i class="bi bi-person-circle"></i></a></div>
    <div class="icon-btn" title="Dashboard"><a href="dashboard.php"><i class="bi bi-house"></i></a></div>
    <button class="icon-btn" title="Logout" onclick="swalConfirm('Logout', 'Are you sure you want to log out?', 'Yes, log out', function(){ window.location='../auth/logout.php'; })"><i class="bi bi-box-arrow-right"></i></button>
  </div>
</header>

<main class="container">

  <!-- Request Info -->
  <div class="card">
    <h2><i class="bi bi-clipboard-check"></i> Track Progress</h2>
    <p class="card-subtitle">Stay updated with your request status and monitor the history of your application.</p>

    <div class="info-grid">
      <div class="info-item">
        <label>Reference Number</label>
        <span><?= h($request["reference_no"]) ?></span>
      </div>
      <div class="info-item">
        <label>Document Type</label>
        <span><?= h(strtoupper($request["document_type"])) ?></span>
      </div>
      <div class="info-item">
        <label>Title Type</label>
        <span><?= h($request["title_type"]) ?></span>
      </div>
      <div class="info-item">
        <label>Purpose</label>
        <span><?= h($request["purpose"] ?? "—") ?></span>
      </div>
      <div class="info-item">
        <label>Copies</label>
        <span><?= (int)$request["copies"] ?></span>
      </div>
      <div class="info-item">
        <label>Requested On</label>
        <span><?= h($requestedOn) ?></span>
      </div>
      <div class="info-item">
        <label>Last Updated</label>
        <span><?= h($lastUpdated) ?></span>
      </div>
      <div class="info-item">
        <label>Application Status</label>
        <span class="status-pill <?= status_class($status) ?>"><?= h(ucwords(strtolower($status))) ?></span>
      </div>
    </div>

    <?php if ($status === STATUS_PENDING): ?>
      <button class="btn-cancel" onclick="cancelRequest(<?= (int)$request['id'] ?>, '<?= h($request['reference_no']) ?>')">
        <i class="bi bi-x-circle"></i> Cancel Request
      </button>
    <?php endif; ?>
  </div>

  <!-- Requirements -->
  <div class="card">
    <h2><i class="bi bi-folder-check"></i> Requirements</h2>
    <div class="req-counter">
      Uploaded: <b><?= $uploadedCount ?></b> / <b><?= $totalReq ?></b>
    </div>

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
          $uploadedAtText = "";
          if ($isUploaded) {
            $path = $uploadedMap[$key]["file_path"];
            $viewHref = "../" . ltrim($path, "/");
            $uploadedAtText = date("m/d/y, g:i A", strtotime($uploadedMap[$key]["uploaded_at"]));
          }
          $uploadLink = "upload_requirements_upload.php?ref=" . urlencode($request["reference_no"]);
        ?>
        <div class="req-row">
          <div class="req-left">
            <span class="req-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
            <span class="req-name"><?= h(strtoupper($reqName)) ?></span>
          </div>
          <div class="req-actions">
            <?php if ($isUploaded): ?>
              <a class="req-view" href="<?= h($viewHref) ?>" target="_blank"><i class="bi bi-eye"></i> View</a>
              <span class="req-time"><?= h($uploadedAtText) ?></span>
            <?php else: ?>
              <a class="req-view" href="<?= h($uploadLink) ?>"><i class="bi bi-upload"></i> Upload</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Tracking History -->
  <div class="card">
    <h2><i class="bi bi-clock-history"></i> Tracking History</h2>

    <?php if (count($logs) === 0): ?>
      <div class="timeline-item">
        <span class="time">—</span>
        <span class="message">No tracking history yet.</span>
      </div>
    <?php else: ?>
      <?php foreach (array_reverse($logs) as $l): ?>
        <div class="timeline-item">
          <span class="time"><?= h(date("m/d/y, g:i A", strtotime($l["created_at"]))) ?></span>
          <span class="message"><?= h($l["message"]) ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</main>

<!-- NOTIFICATION MODAL -->
<div class="modal-backdrop" id="notifBackdrop">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="notifTitle">
    <button class="close-x" id="notifClose" type="button">&times;</button>
    <h3 id="notifTitle">NOTIFICATION</h3>
    <div class="notif-list">

    <?php if (empty($notifs)): ?>
      <div class="notif-item">
        <div class="notif-title">No notifications yet</div>
        <div class="notif-time">—</div>
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
    document.body.style.overflow = "hidden";
    markSeen();
  }

  function closeNotif(){
    backdrop.style.display = "none";
    document.body.style.overflow = "";
  }

  notifBtn?.addEventListener("click", openNotif);
  closeBtn?.addEventListener("click", closeNotif);

  backdrop?.addEventListener("click", (e) => {
    if (e.target === backdrop) closeNotif();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeNotif();
  });

  function cancelRequest(requestId, refNo) {
    Swal.fire({
      title: 'Cancel Request?',
      text: `Are you sure you want to cancel request ${refNo}?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#EF4444',
      confirmButtonText: 'Yes, cancel it',
      cancelButtonText: 'No, keep it'
    }).then((result) => {
      if (result.isConfirmed) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'request_cancel.php';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf_token';
        csrfInput.value = '<?= csrf_token() ?>';
        form.appendChild(csrfInput);

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'request_id';
        idInput.value = requestId;
        form.appendChild(idInput);

        document.body.appendChild(form);
        form.submit();
      }
    });
  }
</script>

</body>
</html>
