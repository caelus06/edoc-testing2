<?php
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

/* total count */
$sqlCount = "SELECT COUNT(*) c FROM requests r JOIN users u ON u.id = r.user_id $where";
$stmtC = $conn->prepare($sqlCount);
if ($types !== "") $stmtC->bind_param($types, ...$params);
$stmtC->execute();
$total = (int)($stmtC->get_result()->fetch_assoc()["c"] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

/* Main List */
$sql = "
    SELECT 
        r.id, r.reference_no, r.document_type, r.status, r.created_at,
        u.first_name, u.middle_name, u.last_name, u.student_id
    FROM requests r
    JOIN users u ON u.id = r.user_id
    $where
    ORDER BY r.created_at DESC, r.id DESC
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

/* Helper for Badges */
function badgeClass($s){
    $s=strtoupper(trim($s));
    return match($s){
        "RETURNED" => "returned",
        "VERIFIED" => "verified",
        "APPROVED" => "approved",
        "PROCESSING" => "processing",
        "READY" => "ready",
        "COMPLETED" => "completed",
        default => "pending"
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Create Document</title>
    <link rel="stylesheet" href="../assets/css/registrar_create_document.css">
    <?php include __DIR__ . "/../includes/swal_header.php"; ?>
</head>
<body>

<div class="layout">
    <!-- SIDEBAR (Matching registrar_request_management.php) -->
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
            <a class="sb-item" href="document_management.php"><span class="sb-icon">📄</span>Document Management</a>
            <a class="sb-item active" href="create_document.php"><span class="sb-icon">➕</span>Create Document</a>
            <a class="sb-item" href="non_compliant.php"><span class="sb-icon">&#9888;</span>Non-Compliant Users</a>
        </nav>

        <div class="sb-section-title">SETTINGS</div>
        <nav class="sb-nav">
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
            <form class="filters" method="GET" action="create_document.php">
                <div class="title">Create Document</div>

                <div class="filter-controls" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search Name, ID Number, Reference Number" style="flex: 1;">

                    <select name="status">
                        <option value="">-- All Status --</option>
                        <?php foreach(["PENDING","RETURNED","VERIFIED","APPROVED","PROCESSING","READY FOR PICKUP","COMPLETED"] as $st): ?>
                            <option value="<?= $st ?>" <?= (strtoupper($status)=== $st ? "selected":"") ?>>
                                <?= $st ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">FILTER</button>
                    <button type="button" class="btn-bulk" style="background: #2c3e50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;">BULK Create Document</button>
                </div>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                            <th style="width:230px;">NAME</th>
                            <th style="width:110px;">ID Number</th>
                            <th style="width:140px;">Ref. Num.</th>
                            <th style="width:90px;">DATE</th>
                            <th>Document Type</th>
                            <th style="width:90px;">Verified By</th>
                            <th style="width:100px;">Date Verified</th>
                            <th style="width:140px;">Application Status</th>
                            <th style="width:150px;">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows)===0): ?>
                            <tr><td colspan="10">No requests found.</td></tr>
                        <?php else: ?>
                            <?php foreach($rows as $r): ?>
                                <?php
                                    $name = strtoupper(trim(($r["last_name"]??"").", ".($r["first_name"]??"")." ".($r["middle_name"]??"")));
                                    $date = $r["created_at"] ? date("m/d/y", strtotime($r["created_at"])) : "-";
                                    
                                    $vb = "-"; $vd = "-";
                                    $vInfo = $conn->prepare("
                                      SELECT rf.verified_by, rf.verified_at
                                      FROM request_files rf
                                      WHERE rf.request_id = ? AND rf.verified_at IS NOT NULL
                                      ORDER BY rf.verified_at DESC LIMIT 1
                                    ");
                                    $vInfo->bind_param("i", $r["id"]);
                                    $vInfo->execute();
                                    $vRow = $vInfo->get_result()->fetch_assoc();
                                    if ($vRow && $vRow["verified_by"]) {
                                      $regQ = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1");
                                      $regQ->bind_param("i", $vRow["verified_by"]);
                                      $regQ->execute();
                                      $regRow = $regQ->get_result()->fetch_assoc();
                                      $vb = $regRow ? trim($regRow["first_name"] . " " . $regRow["last_name"]) : "Registrar #" . $vRow["verified_by"];
                                      $vd = date("m/d/y", strtotime($vRow["verified_at"]));
                                    }

                                    $st = strtoupper($r["status"] ?? "PENDING");

                                    // Check if an active School Order exists for this request
                                    $soQ = $conn->prepare("SELECT id FROM school_orders WHERE request_id = ? AND status IN ('DRAFT','FINALIZED') LIMIT 1");
                                    $soQ->bind_param("i", $r["id"]);
                                    $soQ->execute();
                                    $activeSO = $soQ->get_result()->fetch_assoc();
                                ?>
                                <tr>
                                    <td><input type="checkbox" class="row-check" value="<?= $r["id"] ?>"></td>
                                    <td><?= htmlspecialchars($name) ?></td>
                                    <td><?= htmlspecialchars($r["student_id"] ?? "-") ?></td>
                                    <td><?= htmlspecialchars($r["reference_no"]) ?></td>
                                    <td><?= htmlspecialchars($date) ?></td>
                                    <td><?= htmlspecialchars(strtoupper($r["document_type"])) ?></td>
                                    <td><?= htmlspecialchars($vb) ?></td>
                                    <td><?= htmlspecialchars($vd) ?></td>
                                    <td><span class="badge <?= badgeClass($st) ?>">✓ <?= htmlspecialchars($st) ?></span></td>
                                    <td>
                                        <?php if ($activeSO): ?>
                                            <a class="btn-verify" href="download_so.php?id=<?= (int)$activeSO["id"] ?>" style="background: #2c5a9e; text-decoration: none; color: white; padding: 5px 10px; border-radius: 3px; font-size: 12px;">View Document</a>
                                        <?php else: ?>
                                            <a class="btn-verify" href="process_create.php?id=<?= (int)$r["id"] ?>" style="background: #27ae60; text-decoration: none; color: white; padding: 5px 10px; border-radius: 3px; font-size: 12px;">Create Document</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination">
                    <?php
                        $qs = $_GET;
                        $prev = max(1, $page - 1);
                        $next = min($totalPages, $page + 1);
                    ?>

                    <?php if ($page > 1): ?>
                        <?php $qs["page"] = $prev; ?>
                        <a href="create_document.php?<?= http_build_query($qs) ?>" class="next"><<< BACK</a>
                    <?php endif; ?>

                    <div class="page-number"><?= $page ?></div>

                    <?php if ($page < $totalPages): ?>
                        <?php $qs["page"] = $next; ?>
                        <a href="create_document.php?<?= http_build_query($qs) ?>" class="next">NEXT >>></a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="footer-bar"></div>

<script>
function toggleSidebar(){
    const sb = document.getElementById('sidebar');
    if(!sb) return;
    if(window.innerWidth <= 720){
        sb.style.display = (sb.style.display === 'none' || sb.style.display === '') ? 'block' : 'none';
    }
}

// Checkbox Logic
document.getElementById('selectAll').addEventListener('change', function() {
    const checks = document.querySelectorAll('.row-check');
    checks.forEach(c => c.checked = this.checked);
});
</script>

</body>
</html>