<?php
require_once __DIR__ . "/../includes/helpers.php";
require_once __DIR__ . "/../includes/ocr.php";
require_role(ROLE_REGISTRAR);

$registrarId = (int)$_SESSION["user_id"];

/* Registrar name */
$registrarName = "Registrar";
$me = $conn->prepare("SELECT first_name, last_name FROM users WHERE id=? LIMIT 1");
$me->bind_param("i", $registrarId);
$me->execute();
$mr = $me->get_result()->fetch_assoc();
if ($mr) $registrarName = trim(($mr["first_name"] ?? "") . " " . ($mr["last_name"] ?? ""));

/* Request ID */
$request_id = (int)($_GET["id"] ?? 0);
if ($request_id <= 0) {
    header("Location: create_document.php");
    exit();
}

/* Check if active SO already exists for this request */
$soCheck = $conn->prepare("SELECT id FROM school_orders WHERE request_id = ? AND status IN ('DRAFT','FINALIZED') LIMIT 1");
$soCheck->bind_param("i", $request_id);
$soCheck->execute();
$existingSO = $soCheck->get_result()->fetch_assoc();
if ($existingSO) {
    header("Location: download_so.php?id=" . (int)$existingSO["id"]);
    exit();
}

/* Fetch request + user info */
$st = $conn->prepare("
    SELECT r.*,
           u.first_name, u.middle_name, u.last_name, u.suffix,
           u.student_id, u.course, u.major, u.year_graduated
    FROM requests r
    JOIN users u ON u.id = r.user_id
    WHERE r.id = ?
    LIMIT 1
");
$st->bind_param("i", $request_id);
$st->execute();
$reqRow = $st->get_result()->fetch_assoc();
if (!$reqRow) {
    swal_flash("error", "Error", "Request not found.");
    header("Location: create_document.php");
    exit();
}

/* Validate status — only VERIFIED/APPROVED/PROCESSING allowed */
$allowedStatuses = [STATUS_VERIFIED, STATUS_APPROVED, STATUS_PROCESSING];
$reqStatus = strtoupper(trim($reqRow["status"] ?? ""));
if (!in_array($reqStatus, $allowedStatuses, true)) {
    swal_flash("error", "Error", "Cannot create a School Order for a request with status: " . htmlspecialchars($reqStatus) . ". Request must be VERIFIED, APPROVED, or PROCESSING.");
    header("Location: create_document.php");
    exit();
}

/* Build student info */
$fullName = trim(($reqRow["first_name"] ?? "") . " " . ($reqRow["middle_name"] ?? "") . " " . ($reqRow["last_name"] ?? ""));
$fullName = preg_replace('/\s+/', ' ', $fullName);
$studentId = $reqRow["student_id"] ?? "";
$course = $reqRow["course"] ?? "";
$yearGrad = $reqRow["year_graduated"] ?? "";
$docType = strtoupper(trim($reqRow["document_type"] ?? ""));

/* Fetch uploaded files for this request */
$f = $conn->prepare("SELECT * FROM request_files WHERE request_id = ? ORDER BY id ASC");
$f->bind_param("i", $request_id);
$f->execute();
$fileRows = $f->get_result()->fetch_all(MYSQLI_ASSOC);

/* Selected file for OCR */
$selectedFileId = (int)($_GET["file_id"] ?? 0);
$selectedFile = null;

if ($selectedFileId > 0) {
    foreach ($fileRows as $fr) {
        if ((int)$fr["id"] === $selectedFileId) {
            $selectedFile = $fr;
            break;
        }
    }
}
if (!$selectedFile && count($fileRows) > 0) {
    $selectedFile = $fileRows[0];
    $selectedFileId = (int)$selectedFile["id"];
}

/* Run OCR if a file is selected and OCR was triggered */
$ocrResult = null;
$ocrFields = [
    'student_name'       => ['value' => $fullName, 'confidence' => ''],
    'student_id'         => ['value' => $studentId, 'confidence' => ''],
    'course_program'     => ['value' => $course, 'confidence' => ''],
    'date_of_graduation' => ['value' => $yearGrad, 'confidence' => ''],
];
$ocrError = null;
$ocrRawText = '';

$runOcr = isset($_GET["ocr"]) && $_GET["ocr"] === "1";

if ($runOcr && $selectedFile && !empty($selectedFile["file_path"])) {
    $absPath = realpath(__DIR__ . "/../" . $selectedFile["file_path"]);
    if ($absPath && file_exists($absPath)) {
        $ocrResult = extract_fields($absPath);
        $ocrRawText = $ocrResult['raw_text'];
        $ocrError = $ocrResult['error'];

        if (!$ocrError) {
            // Merge OCR results — only override if OCR found something
            foreach ($ocrResult['fields'] as $key => $val) {
                if (!empty($val['value'])) {
                    $ocrFields[$key] = $val;
                }
            }
        }
    } else {
        $ocrError = "Source file not found on disk.";
    }
}

/* Generate next SO number */
$year = date("Y");
$soStmt = $conn->prepare("
    SELECT so_number FROM school_orders
    WHERE so_number LIKE CONCAT('SO-', ?, '-%')
    ORDER BY id DESC LIMIT 1
");
$soStmt->bind_param("s", $year);
$soStmt->execute();
$lastSO = $soStmt->get_result()->fetch_assoc();

$nextSeq = 1;
if ($lastSO) {
    $parts = explode('-', $lastSO['so_number']);
    if (isset($parts[2])) {
        $nextSeq = (int)$parts[2] + 1;
    }
}
$suggestedSO = sprintf("SO-%s-%04d", $year, $nextSeq);

/* Helper functions for confidence indicator UI */
function confidence_class(string $conf): string {
    return match($conf) {
        'high'   => 'conf-high',
        'medium' => 'conf-medium',
        'low'    => 'conf-low',
        default  => '',
    };
}

function confidence_hint(string $conf): string {
    return match($conf) {
        'high'   => '<span class="field-hint hint-high">OCR: High confidence</span>',
        'medium' => '<span class="field-hint hint-medium">OCR: Medium confidence — please verify</span>',
        'low'    => '<span class="field-hint hint-low">OCR: Low confidence — please verify</span>',
        default  => '',
    };
}

/* Badge helper */
function badgeClass($s) {
    $s = strtoupper(trim($s));
    return match($s) {
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
    <meta charset="UTF-8">
    <title>Create School Order</title>
    <link rel="stylesheet" href="../assets/css/registrar_create_document.css">
    <link rel="stylesheet" href="../assets/css/process_create.css">
    <?php include __DIR__ . "/../includes/swal_header.php"; ?>
</head>
<body>

<div class="layout">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sb-user">
            <div class="avatar">&#x1F464;</div>
            <div class="meta">
                <div class="name"><?= h($registrarName) ?></div>
                <div class="role">Registrar</div>
            </div>
        </div>

        <div class="sb-section-title">MODULES</div>
        <nav class="sb-nav">
            <a class="sb-item" href="dashboard.php"><span class="sb-icon">&#x1F3E0;</span>Dashboard</a>
            <a class="sb-item" href="new_document_request.php"><span class="sb-icon">&#x1F4DD;</span>New Document Request</a>
            <a class="sb-item" href="request_management.php"><span class="sb-icon">&#x1F50E;</span>Request Management</a>
            <a class="sb-item" href="track_progress.php"><span class="sb-icon">&#x1F4CD;</span>Track Progress</a>
            <a class="sb-item" href="document_management.php"><span class="sb-icon">&#x1F4C4;</span>Document Management</a>
            <a class="sb-item active" href="create_document.php"><span class="sb-icon">&#x2795;</span>Create Document</a>
            <a class="sb-item" href="non_compliant.php"><span class="sb-icon">&#9888;</span>Non-Compliant Users</a>
        </nav>

        <div class="sb-section-title">SETTINGS</div>
        <nav class="sb-nav">
            <a class="sb-item" href="../mis/system_settings.php"><span class="sb-icon">&#9881;</span>System Settings</a>
            <a class="sb-item" href="#" onclick="event.preventDefault(); swalConfirm('Logout', 'Are you sure you want to log out?', 'Yes, log out', function(){ window.location='../auth/logout.php'; })"><span class="sb-icon">&#x238B;</span>Logout</a>
        </nav>
    </aside>

    <div class="main">
        <!-- TOPBAR -->
        <header class="topbar">
            <button class="hamburger" type="button" onclick="toggleSidebar()">&#x2261;</button>
            <div class="brand">
                <div class="logo"><img src="../assets/img/edoc-logo.jpeg" alt="E-Doc"></div>
                <div>E-Doc Document Requesting System</div>
            </div>
        </header>

        <main class="container">
            <!-- Title bar -->
            <div class="page-header">
                <div class="title">Create School Order</div>
                <a href="create_document.php" class="btn-back">&larr; Back to Requests</a>
            </div>

            <!-- Student info bar -->
            <div class="info-bar">
                <div class="info-item"><b>Ref No:</b> <?= h($reqRow["reference_no"]) ?></div>
                <div class="info-item"><b>Student:</b> <?= h($fullName) ?></div>
                <div class="info-item"><b>ID:</b> <?= h($studentId ?: "N/A") ?></div>
                <div class="info-item"><b>Document:</b> <?= h($docType) ?></div>
                <div class="info-item">
                    <span class="badge <?= badgeClass($reqStatus) ?>"><?= h($reqStatus) ?></span>
                </div>
            </div>

            <!-- Split grid -->
            <div class="verify-grid">
                <!-- LEFT PANEL: Source Document -->
                <div class="panel">
                    <h2>Source Document</h2>

                    <?php if (count($fileRows) > 0): ?>
                        <div class="file-selector">
                            <label for="fileSelect">Select file:</label>
                            <select id="fileSelect" onchange="changeFile(this.value)">
                                <?php foreach ($fileRows as $fr): ?>
                                    <option value="<?= (int)$fr["id"] ?>" <?= ((int)$fr["id"] === $selectedFileId) ? "selected" : "" ?>>
                                        <?= h($fr["requirement_name"] ?? $fr["requirement_key"] ?? "File #" . $fr["id"]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if ($selectedFile && !empty($selectedFile["file_path"])): ?>
                            <?php
                                $filePath = "../" . $selectedFile["file_path"];
                                $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                            ?>
                            <div class="preview-box">
                                <?php if ($fileExt === "pdf"): ?>
                                    <iframe src="<?= h($filePath) ?>" style="width:100%;height:500px;border:none;"></iframe>
                                <?php elseif (in_array($fileExt, ["jpg","jpeg","png","webp"])): ?>
                                    <img src="<?= h($filePath) ?>" alt="Source document" style="max-width:100%;max-height:500px;object-fit:contain;">
                                <?php else: ?>
                                    <p>Cannot preview this file type. <a href="<?= h($filePath) ?>" target="_blank">Open file</a></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="preview-box">
                                <p class="preview-empty">No file available for preview.</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="preview-box">
                            <p class="preview-empty">No files uploaded for this request.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- RIGHT PANEL: Editable Form -->
                <div class="panel">
                    <div class="panel-header-row">
                        <h2>Extracted Data (Editable)</h2>
                        <?php if (count($fileRows) > 0): ?>
                            <a href="process_create.php?id=<?= $request_id ?>&file_id=<?= $selectedFileId ?>&ocr=1" class="btn-ocr">Run OCR</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($ocrError): ?>
                        <div class="ocr-banner ocr-error">
                            <b>OCR Error:</b> <?= h($ocrError) ?>
                        </div>
                    <?php elseif ($runOcr && !$ocrError): ?>
                        <div class="ocr-banner ocr-success">
                            OCR completed. <?= count(array_filter($ocrResult['fields'] ?? [], fn($f) => !empty($f['value']))) ?> field(s) extracted. Review and correct below.
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="generate_so.php" id="soForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="request_id" value="<?= $request_id ?>">
                        <input type="hidden" name="source_file_id" value="<?= $selectedFileId ?>">
                        <input type="hidden" name="ocr_raw_text" value="<?= h($ocrRawText) ?>">

                        <div class="form-group">
                            <label for="so_number">SO Number</label>
                            <input type="text" id="so_number" name="so_number" value="<?= h($suggestedSO) ?>" required>
                            <span class="field-hint">Auto-generated. You may override.</span>
                        </div>

                        <div class="form-group">
                            <label for="student_name">Student Name</label>
                            <input type="text" id="student_name" name="student_name"
                                   value="<?= h($ocrFields['student_name']['value']) ?>" required
                                   class="<?= confidence_class($ocrFields['student_name']['confidence']) ?>">
                            <?= confidence_hint($ocrFields['student_name']['confidence']) ?>
                        </div>

                        <div class="form-group">
                            <label for="student_id_number">Student ID</label>
                            <input type="text" id="student_id_number" name="student_id_number"
                                   value="<?= h($ocrFields['student_id']['value']) ?>" required
                                   class="<?= confidence_class($ocrFields['student_id']['confidence']) ?>">
                            <?= confidence_hint($ocrFields['student_id']['confidence']) ?>
                        </div>

                        <div class="form-group">
                            <label for="course_program">Course / Program</label>
                            <input type="text" id="course_program" name="course_program"
                                   value="<?= h($ocrFields['course_program']['value']) ?>"
                                   class="<?= confidence_class($ocrFields['course_program']['confidence']) ?>">
                            <?= confidence_hint($ocrFields['course_program']['confidence']) ?>
                        </div>

                        <div class="form-group">
                            <label for="document_type">Document Type</label>
                            <input type="text" id="document_type" name="document_type"
                                   value="<?= h($docType) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="date_of_graduation">Date of Graduation</label>
                            <input type="text" id="date_of_graduation" name="date_of_graduation"
                                   value="<?= h($ocrFields['date_of_graduation']['value']) ?>"
                                   class="<?= confidence_class($ocrFields['date_of_graduation']['confidence']) ?>">
                            <?= confidence_hint($ocrFields['date_of_graduation']['confidence']) ?>
                        </div>

                        <div class="form-group">
                            <label for="date_issued">Date Issued</label>
                            <input type="date" id="date_issued" name="date_issued"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="registrar_name">Registrar Name</label>
                            <input type="text" id="registrar_name" name="registrar_name"
                                   value="<?= h($registrarName) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="additional_notes">Additional Notes</label>
                            <textarea id="additional_notes" name="additional_notes" rows="3" placeholder="Optional notes..."></textarea>
                        </div>

                        <div class="form-actions">
                            <a href="create_document.php" class="btn-cancel">Cancel</a>
                            <button type="submit" class="btn-save">Confirm &amp; Generate</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="footer-bar"></div>

<script>
function toggleSidebar() {
    var sb = document.getElementById('sidebar');
    if (!sb) return;
    if (window.innerWidth <= 720) {
        sb.style.display = (sb.style.display === 'none' || sb.style.display === '') ? 'block' : 'none';
    }
}

function changeFile(fileId) {
    window.location.href = 'process_create.php?id=<?= $request_id ?>&file_id=' + fileId;
}
</script>

</body>
</html>
