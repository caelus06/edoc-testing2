<?php
// registrar/document_management.php
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


// Handle add/edit
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_verify();
  $action = $_POST["action"] ?? "";
  if ($action === "add" || $action === "edit") {
    $name = trim($_POST["name"] ?? "");
    $processing_time = trim($_POST["processing_time"] ?? "");
    $id = (int)($_POST["id"] ?? 0);


    if ($name === "" || $processing_time === "") {
      $message = "Please fill in all fields.";
    } else {
      if ($action === "add") {
        $stmt = $conn->prepare("INSERT INTO document_process (document_type, working_days, last_updated, updated_by) VALUES (?, ?, NOW(), ?)");
        $stmt->bind_param("ssi", $name, $processing_time, $registrarId);
        $stmt->execute();
        $newDocId = $conn->insert_id;
        audit_log($conn, "INSERT", "document_process", $newDocId, "Added document type: " . $name);
        $message = "Document added successfully.";
      } elseif ($action === "edit" && $id > 0) {
        $stmt = $conn->prepare("UPDATE document_process SET document_type=?, working_days=?, last_updated=NOW(), updated_by=? WHERE id=?");
        $stmt->bind_param("ssii", $name, $processing_time, $registrarId, $id);
        $stmt->execute();
        audit_log($conn, "UPDATE", "document_process", $id, "Updated document type: " . $name);
        $message = "Document updated successfully.";
      }
    }
  }
}


// Filter
$filter = trim($_GET["filter"] ?? "");


// Pagination
$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;


// Fetch all document types for dropdown
$docTypes = [];
$dtStmt = $conn->query("SELECT DISTINCT document_type FROM document_process ORDER BY document_type ASC");
while ($row = $dtStmt->fetch_assoc()) {
  $docTypes[] = $row['document_type'];
}


// Total count for pagination
$sqlCount = "SELECT COUNT(*) c FROM document_process dt LEFT JOIN users u ON dt.updated_by = u.id";
$countParams = [];
$countTypes = "";
if ($filter !== "") {
  $sqlCount .= " WHERE dt.document_type = ?";
  $countParams[] = $filter;
  $countTypes .= "s";
}
$countStmt = $conn->prepare($sqlCount);
if ($countTypes !== "") $countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()["c"] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));


// Fetch documents
$sql = "SELECT dt.id, dt.document_type AS name, dt.working_days AS processing_time, dt.last_updated, dt.updated_by, u.first_name, u.last_name
        FROM document_process dt
        LEFT JOIN users u ON dt.updated_by = u.id";
$params = [];
$types = "";
if ($filter !== "") {
  $sql .= " WHERE dt.document_type = ?";
  $params[] = $filter;
  $types .= "s";
}
$sql .= " ORDER BY dt.document_type ASC LIMIT $perPage OFFSET $offset";


$stmt = $conn->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Document Management</title>
  <link rel="stylesheet" href="../assets/css/registrar_document_management.css">
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
      <a class="sb-item" href="request_management.php"><span class="sb-icon">🔎</span>Request Management</a>
      <a class="sb-item" href="track_progress.php"><span class="sb-icon">📍</span>Track Progress</a>
      <a class="sb-item active" href="document_management.php"><span class="sb-icon">📄</span>Document Management</a>
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


    <main class="container">
      <form class="filters">
        <div class="title">Document Management</div>
        <select name="filter">
          <option value="">All Documents</option>
          <?php foreach ($docTypes as $dt): ?>
            <option value="<?php echo h($dt); ?>" <?php if ($filter === $dt) echo "selected"; ?>><?php echo h($dt); ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="filter-btn">Filter</button>
        <button type="button" class="btn add-btn" onclick="openModal('add')">Add New Document</button>
      </form>


      <table class="table">
        <thead>
          <tr>
            <th>Document Name</th>
            <th>Processing Time</th>
            <th>Last Updated</th>
            <th>Updated By</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($documents as $doc): ?>
            <tr>
              <td><?php echo h($doc['name']); ?></td>
              <td><?php echo h($doc['processing_time']); ?></td>
              <td><?php echo h($doc['last_updated']); ?></td>
              <td><?php echo h(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? '') . ' (' . $doc['updated_by'] . ')'); ?></td>
              <td>
                <a href="edit_document.php?id=<?= $doc['id'] ?>" class="btn edit-btn">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>


      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php
          $qs = $_GET;
          $prev = $page - 1;
          $next = $page + 1;
          ?>
          <?php if ($page > 1): ?>
            <?php $qs["page"] = $prev; ?>
            <a href="?<?= http_build_query($qs) ?>" class="prev">
              <<< BACK
            </a>
          <?php endif; ?>


          <div class="page-numbers">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <?php $qs["page"] = $i; ?>
              <a href="?<?= http_build_query($qs) ?>" class="page-number <?php if ($i === $page) echo 'active'; ?>">
                <?= $i ?>
              </a>
            <?php endfor; ?>
          </div>


          <?php if ($page < $totalPages): ?>
            <?php $qs["page"] = $next; ?>
            <a href="?<?= http_build_query($qs) ?>" class="next">
              NEXT >>>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>


    </main>
  </div>
</div>


  <!-- Modal -->
  <div id="modal" class="modal">
    <div class="modal-content">
      <h2 id="modal-title">Add Document</h2>
      <form method="POST" id="doc-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="action" value="add">
        <input type="hidden" name="id" id="doc-id" value="">
        <div class="form-group">
          <label for="name">Document Name</label>
          <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
          <label for="processing_time">Processing Time</label>
          <input type="text" id="processing_time" name="processing_time" required>
        </div>
        <div class="modal-actions">
          <button type="submit" class="btn save-btn">Save</button>
          <button type="button" class="btn cancel-btn" onclick="closeModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <div class="footer-bar"></div>

  <script>
    function openModal(action, id = '', name = '', processing_time = '') {
      document.getElementById('modal').style.display = 'block';
      document.getElementById('action').value = action;
      document.getElementById('doc-id').value = id;
      document.getElementById('name').value = name;
      document.getElementById('processing_time').value = processing_time;
      document.getElementById('modal-title').textContent = action === 'add' ? 'Add Document' : 'Edit Document';
    }


    function closeModal() {
      document.getElementById('modal').style.display = 'none';
    }


    window.onclick = function(event) {
      if (event.target == document.getElementById('modal')) {
        closeModal();
      }
    }
  </script>


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
