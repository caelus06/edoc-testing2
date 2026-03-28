<?php
require_once __DIR__ . "/../includes/helpers.php";
require_once __DIR__ . "/../includes/compliance.php";
require_role(ROLE_REGISTRAR);

$registrarId = (int)$_SESSION["user_id"];

// Registrar name
$registrarName = "Registrar";
$me = $conn->prepare("SELECT first_name, last_name FROM users WHERE id=? LIMIT 1");
$me->bind_param("i", $registrarId);
$me->execute();
$mr = $me->get_result()->fetch_assoc();
if ($mr) $registrarName = trim(($mr["first_name"] ?? "") . " " . ($mr["last_name"] ?? ""));

// Filters
$search  = trim($_GET["q"] ?? "");
$reason  = trim($_GET["reason"] ?? "");
$docType = trim($_GET["doc_type"] ?? "");

$allResults = get_non_compliant_users($conn, [
    'search'   => $search,
    'reason'   => $reason,
    'doc_type' => $docType,
]);

// Pagination
$page    = max(1, (int)($_GET["page"] ?? 1));
$perPage = PER_PAGE;
$total   = count($allResults);
$totalPages = max(1, (int)ceil($total / $perPage));
$offset  = ($page - 1) * $perPage;
$rows    = array_slice($allResults, $offset, $perPage);

// Document types for filter dropdown
$docTypes = [];
$dt = $conn->query("SELECT DISTINCT document_type FROM requests ORDER BY document_type ASC");
if ($dt) while ($r = $dt->fetch_assoc()) $docTypes[] = $r["document_type"];

// Badge class mapping
function reasonBadgeClass(string $reason): string {
    return match($reason) {
        'RESUBMIT_FLAGGED' => 'resubmit',
        'MISSING_UPLOADS'  => 'missing',
        'ABANDONED'        => 'abandoned',
        default            => 'missing',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Non-Compliant Users</title>
  <link rel="stylesheet" href="../assets/css/non_compliant.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
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
      <a class="sb-item" href="track_progress.php"><span class="sb-icon">📍</span>Track Progress</a>
      <a class="sb-item" href="document_management.php"><span class="sb-icon">📄</span>Document Management</a>
      <a class="sb-item" href="create_document.php"><span class="sb-icon">➕</span>Create Document</a>
      <a class="sb-item active" href="non_compliant.php"><span class="sb-icon">⚠️</span>Non-Compliant Users</a>
    </nav>

    <div class="sb-section-title">SETTINGS</div>
    <nav class="sb-nav">
      <a class="sb-item" href="../mis/system_settings.php"><span class="sb-icon">⚙️</span>System Settings</a>
      <a class="sb-item" href="#" onclick="event.preventDefault(); swalConfirm('Logout', 'Are you sure you want to log out?', 'Yes, log out', function(){ window.location='../auth/logout.php'; })"><span class="sb-icon">⎋</span>Logout</a>
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

    <main class="container">
      <!-- FILTERS -->
      <form class="filters" method="GET" action="non_compliant.php">
        <div class="title">Non-Compliant Users</div>

        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search Name, ID Number, Reference Number">

        <select name="reason">
          <option value="">-- All Reasons --</option>
          <option value="RESUBMIT_FLAGGED" <?= ($reason === 'RESUBMIT_FLAGGED' ? 'selected' : '') ?>>Resubmit Required</option>
          <option value="MISSING_UPLOADS"  <?= ($reason === 'MISSING_UPLOADS'  ? 'selected' : '') ?>>Missing Uploads</option>
          <option value="ABANDONED"        <?= ($reason === 'ABANDONED'        ? 'selected' : '') ?>>Abandoned Request</option>
        </select>

        <select name="doc_type">
          <option value="">-- Document Type --</option>
          <?php foreach ($docTypes as $d): ?>
            <option value="<?= h($d) ?>" <?= (strtoupper($docType) === strtoupper($d) ? 'selected' : '') ?>>
              <?= h($d) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button type="submit">FILTER</button>
      </form>

      <!-- BULK ACTIONS BAR -->
      <div class="bulk-bar">
        <div class="bulk-left">
          <input type="checkbox" id="selectAll">
          <label for="selectAll">Select All</label>
        </div>
        <div class="bulk-right">
          <button class="btn-notify-selected" id="btnNotifySelected" disabled>Notify Selected</button>
          <button class="btn-notify-all" id="btnNotifyAll">Notify All (<?= (int)$total ?>)</button>
        </div>
      </div>

      <!-- TABLE -->
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th class="checkbox-col"></th>
              <th>Student Name</th>
              <th style="width:110px;">Student ID</th>
              <th style="width:140px;">Ref. No.</th>
              <th>Document Type</th>
              <th style="width:180px;">Reason(s)</th>
              <th style="width:180px;">Pending Document(s)</th>
              <th style="width:110px;">Last Notified</th>
              <th style="width:90px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($rows) === 0): ?>
              <tr><td colspan="9" style="text-align:center;padding:20px;">No non-compliant users found.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $fullName = strtoupper(trim(($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? '')));
                  $pendingDocs = implode(', ', $row['pending_docs'] ?? []);
                  if (strlen($pendingDocs) > 60) {
                      $pendingDocs = substr($pendingDocs, 0, 57) . '...';
                  }
                  $lastNotified = $row['last_notified']
                      ? date('m/d/Y', strtotime($row['last_notified']))
                      : 'Never';
                  $reasons = $row['reasons'] ?? [];
                  $reasonLabels = format_reasons($reasons);
                ?>
                <tr>
                  <td class="checkbox-col">
                    <input type="checkbox" class="row-check" value="<?= (int)$row['request_id'] ?>">
                  </td>
                  <td><?= h($fullName) ?></td>
                  <td><?= h($row['student_id'] ?? '-') ?></td>
                  <td><?= h($row['reference_no'] ?? '-') ?></td>
                  <td><?= h(strtoupper($row['document_type'] ?? '')) ?></td>
                  <td>
                    <?php foreach ($reasons as $i => $rc): ?>
                      <span class="badge <?= reasonBadgeClass($rc) ?>"><?= h($reasonLabels[$i] ?? $rc) ?></span>
                    <?php endforeach; ?>
                  </td>
                  <td>
                    <?php if ($pendingDocs !== ''): ?>
                      <span class="pending-docs"><?= h($pendingDocs) ?></span>
                    <?php else: ?>
                      <span class="pending-docs">—</span>
                    <?php endif; ?>
                  </td>
                  <td><?= h($lastNotified) ?></td>
                  <td>
                    <button class="btn-notify" onclick="notifySingle(<?= (int)$row['request_id'] ?>)">NOTIFY</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <!-- PAGINATION -->
        <div class="pagination">
          <?php
            $qs = $_GET;
            $prev = max(1, $page - 1);
            $next = min($totalPages, $page + 1);
          ?>
          <?php if ($page > 1): ?>
            <?php $qs["page"] = $prev; ?>
            <a href="non_compliant.php?<?= http_build_query($qs) ?>" class="next">&lt;&lt;&lt; BACK</a>
          <?php endif; ?>

          <div class="page-number"><?= (int)$page ?></div>

          <?php if ($page < $totalPages): ?>
            <?php $qs["page"] = $next; ?>
            <a href="non_compliant.php?<?= http_build_query($qs) ?>" class="next">NEXT &gt;&gt;&gt;</a>
          <?php endif; ?>
        </div>
      </div>

    </main>
  </div>
</div>
<div class="footer-bar"></div>

<script>
// Select All
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
    toggleBulkBtn();
});
document.querySelectorAll('.row-check').forEach(function(cb) {
    cb.addEventListener('change', toggleBulkBtn);
});
function toggleBulkBtn() {
    var checked = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('btnNotifySelected').disabled = (checked === 0);
}

// Single row notify
function notifySingle(requestId) {
    Swal.fire({
        title: 'Send Notification',
        input: 'textarea',
        inputLabel: 'Message',
        inputValue: 'You have pending requirements for your document request. Please complete your submission to avoid delays.',
        showCancelButton: true,
        confirmButtonText: 'Send',
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'send_notification.php';
            var fields = {
                '_csrf_token': '<?= h(csrf_token()) ?>',
                'action': 'send_single',
                'request_id': requestId,
                'message': result.value
            };
            for (var key in fields) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Notify Selected
document.getElementById('btnNotifySelected').addEventListener('click', function() {
    var ids = [];
    document.querySelectorAll('.row-check:checked').forEach(function(cb) { ids.push(cb.value); });
    if (ids.length === 0) return;

    Swal.fire({
        title: 'Notify ' + ids.length + ' user(s)?',
        input: 'textarea',
        inputLabel: 'Message (same for all)',
        inputValue: 'You have pending requirements for your document request. Please complete your submission to avoid delays.',
        showCancelButton: true,
        confirmButtonText: 'Send All',
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'send_notification.php';
            var fields = {
                '_csrf_token': '<?= h(csrf_token()) ?>',
                'action': 'send_selected',
                'request_ids': JSON.stringify(ids),
                'message': result.value
            };
            for (var key in fields) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
        }
    });
});

// Notify All
document.getElementById('btnNotifyAll').addEventListener('click', function() {
    Swal.fire({
        icon: 'warning',
        title: 'Notify ALL non-compliant users?',
        text: 'This will send notifications to all <?= (int)$total ?> non-compliant request(s).',
        input: 'textarea',
        inputLabel: 'Message',
        inputValue: 'You have pending requirements for your document request. Please complete your submission to avoid delays.',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, notify all',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'send_notification.php';
            var fields = {
                '_csrf_token': '<?= h(csrf_token()) ?>',
                'action': 'notify_all',
                'message': result.value
            };
            for (var key in fields) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
        }
    });
});

// Sidebar toggle
function toggleSidebar() {
    var sb = document.getElementById('sidebar');
    if (!sb) return;
    if (window.innerWidth <= 720) {
        sb.style.display = (sb.style.display === 'none') ? 'block' : 'none';
    }
}
</script>

</body>
</html>
