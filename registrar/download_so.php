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

/* SO ID */
$soId = (int)($_GET["id"] ?? 0);
if ($soId <= 0) {
    header("Location: create_document.php");
    exit();
}

/* Fetch SO record */
$st = $conn->prepare("SELECT * FROM school_orders WHERE id = ? LIMIT 1");
$st->bind_param("i", $soId);
$st->execute();
$so = $st->get_result()->fetch_assoc();
if (!$so) {
    die("School Order not found.");
}

/* Handle POST actions */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_verify();
    $action = trim($_POST["action"] ?? "");

    if ($action === "finalize" && $so["status"] === "DRAFT") {
        $up = $conn->prepare("UPDATE school_orders SET status = 'FINALIZED' WHERE id = ?");
        $up->bind_param("i", $soId);
        $up->execute();
        audit_log($conn, "UPDATE", "school_orders", $soId, "Finalized School Order: " . $so["so_number"]);
        add_log($conn, (int)$so["request_id"], "School Order " . $so["so_number"] . " finalized");
        header("Location: download_so.php?id=" . $soId . "&msg=finalized");
        exit();
    }

    if ($action === "void" && $so["status"] !== "VOIDED") {
        $up = $conn->prepare("UPDATE school_orders SET status = 'VOIDED' WHERE id = ?");
        $up->bind_param("i", $soId);
        $up->execute();
        audit_log($conn, "UPDATE", "school_orders", $soId, "Voided School Order: " . $so["so_number"]);
        add_log($conn, (int)$so["request_id"], "School Order " . $so["so_number"] . " voided");
        header("Location: download_so.php?id=" . $soId . "&msg=voided");
        exit();
    }

    if ($action === "download_docx") {
        $filePath = __DIR__ . "/../" . $so["file_path"];
        if (file_exists($filePath)) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit();
        }
        die("File not found on disk.");
    }

    if ($action === "download_pdf") {
        $docxPath = __DIR__ . "/../" . $so["file_path"];
        $pdfPath = str_replace('.docx', '.pdf', $docxPath);

        // Try LibreOffice conversion if PDF doesn't exist yet
        if (!file_exists($pdfPath)) {
            $outDir = dirname($docxPath);
            $cmd = sprintf(
                'soffice --headless --convert-to pdf --outdir %s %s 2>&1',
                escapeshellarg($outDir),
                escapeshellarg($docxPath)
            );
            exec($cmd, $output, $code);
        }

        if (file_exists($pdfPath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($pdfPath) . '"');
            header('Content-Length: ' . filesize($pdfPath));
            readfile($pdfPath);
            exit();
        }

        die("PDF conversion failed. LibreOffice may not be installed. Please download the DOCX and convert manually.");
    }
}

/* Re-fetch after potential POST update */
$st = $conn->prepare("SELECT * FROM school_orders WHERE id = ? LIMIT 1");
$st->bind_param("i", $soId);
$st->execute();
$so = $st->get_result()->fetch_assoc();

$msg = $_GET["msg"] ?? "";

/* Status badge helper */
function soBadgeClass($s) {
    return match($s) {
        "DRAFT" => "processing",
        "FINALIZED" => "approved",
        "VOIDED" => "returned",
        default => "pending",
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Order - <?= h($so["so_number"]) ?></title>
    <link rel="stylesheet" href="../assets/css/registrar_create_document.css">
    <link rel="stylesheet" href="../assets/css/process_create.css">
    <style>
        .so-card {
            background: #fff;
            border: 1px solid #dfe3ea;
            border-radius: 12px;
            padding: 24px;
            max-width: 700px;
            margin: 0 auto;
            box-shadow: 0 8px 18px rgba(0,0,0,.06);
        }
        .so-card h2 {
            margin: 0 0 16px;
            font-size: 18px;
        }
        .so-detail {
            display: grid;
            grid-template-columns: 160px 1fr;
            gap: 6px 12px;
            font-size: 12px;
            margin-bottom: 6px;
        }
        .so-detail dt {
            font-weight: 900;
        }
        .so-detail dd {
            margin: 0;
        }
        .so-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .so-actions form { margin: 0; }
        .btn-download {
            padding: 10px 22px;
            border-radius: 6px;
            border: none;
            font-weight: 900;
            font-size: 12px;
            cursor: pointer;
            color: #fff;
        }
        .btn-docx { background: #2c5a9e; }
        .btn-pdf { background: #d32f2f; }
        .btn-finalize { background: #1a6b2a; }
        .btn-void { background: #6d6d6d; }
        .msg-banner {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 14px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        .msg-success {
            background: #e8f5e9;
            color: #1b5e20;
            border: 1px solid #a5d6a7;
        }
    </style>
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
        </nav>

        <div class="sb-section-title">SETTINGS</div>
        <nav class="sb-nav">
            <a class="sb-item" href="../auth/logout.php"><span class="sb-icon">&#x238B;</span>Logout</a>
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
            <div class="page-header">
                <div class="title">School Order</div>
                <a href="create_document.php" class="btn-back">&larr; Back to Requests</a>
            </div>

            <?php if ($msg === "finalized"): ?>
                <div class="msg-banner msg-success">School Order has been finalized successfully.</div>
            <?php elseif ($msg === "voided"): ?>
                <div class="msg-banner msg-success">School Order has been voided.</div>
            <?php endif; ?>

            <div class="so-card">
                <h2>
                    <?= h($so["so_number"]) ?>
                    <span class="badge <?= soBadgeClass($so["status"]) ?>" style="margin-left:10px;">
                        <?= h($so["status"]) ?>
                    </span>
                </h2>

                <dl class="so-detail">
                    <dt>Student Name</dt>
                    <dd><?= h($so["student_name"]) ?></dd>

                    <dt>Student ID</dt>
                    <dd><?= h($so["student_id_number"]) ?></dd>

                    <dt>Course / Program</dt>
                    <dd><?= h($so["course_program"] ?: "N/A") ?></dd>

                    <dt>Document Type</dt>
                    <dd><?= h($so["document_type"]) ?></dd>

                    <dt>Date of Graduation</dt>
                    <dd><?= h($so["date_of_graduation"] ?: "N/A") ?></dd>

                    <dt>Date Issued</dt>
                    <dd><?= h(date("F j, Y", strtotime($so["date_issued"]))) ?></dd>

                    <dt>Registrar</dt>
                    <dd><?= h($so["registrar_name"]) ?></dd>

                    <?php if (!empty($so["additional_notes"])): ?>
                        <dt>Notes</dt>
                        <dd><?= h($so["additional_notes"]) ?></dd>
                    <?php endif; ?>

                    <dt>Created</dt>
                    <dd><?= h(date("F j, Y g:i A", strtotime($so["created_at"]))) ?></dd>
                </dl>

                <div class="so-actions">
                    <!-- Download DOCX -->
                    <form method="POST" action="download_so.php?id=<?= $soId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="download_docx">
                        <button type="submit" class="btn-download btn-docx">Download DOCX</button>
                    </form>

                    <!-- Download PDF -->
                    <form method="POST" action="download_so.php?id=<?= $soId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="download_pdf">
                        <button type="submit" class="btn-download btn-pdf">Download as PDF</button>
                    </form>

                    <?php if ($so["status"] === "DRAFT"): ?>
                        <!-- Finalize -->
                        <form method="POST" action="download_so.php?id=<?= $soId ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="finalize">
                            <button type="submit" class="btn-download btn-finalize"
                                    onclick="return confirm('Finalize this School Order? This marks it as official.');">
                                Finalize
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($so["status"] !== "VOIDED"): ?>
                        <!-- Void -->
                        <form method="POST" action="download_so.php?id=<?= $soId ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="void">
                            <button type="submit" class="btn-download btn-void"
                                    onclick="return confirm('Void this School Order? This action cannot be undone.');">
                                Void
                            </button>
                        </form>
                    <?php endif; ?>
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
</script>

</body>
</html>
