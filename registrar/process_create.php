<?php
session_start();
require_once "../config/database.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "REGISTRAR") {
header("Location: ../auth/auth.php");
exit();
}
function h($s){
return htmlspecialchars((string)($s ?? ""), ENT_QUOTES, "UTF-8");
}
/* IMPORTANT: change this to your actual Tesseract path */
define('TESSERACT_PATH', 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe');
$requestId = (int)($_GET["id"] ?? $_POST["request_id"] ?? 0);
if ($requestId <= 0) {
die("Missing request ID.");
}
/* Registrar name */
$registrarId = (int)$_SESSION["user_id"];
$registrarName = "Registrar";
$me = $conn->prepare("SELECT first_name, last_name FROM users WHERE id=? LIMIT 1");
$me->bind_param("i", $registrarId);
$me->execute();
$mr = $me->get_result()->fetch_assoc();
if ($mr) {
$registrarName = trim(($mr["first_name"] ?? "") . " " . ($mr["last_name"] ?? ""));
}
/* Fetch selected request + student */
$sql = "
SELECT
r.id,
r.reference_no,
r.document_type,
r.title_type,
r.purpose,
r.copies,
r.status,
r.created_at,
u.id AS user_id,
u.first_name,
u.middle_name,
u.last_name,
u.student_id,
u.course,
u.major,
u.year_graduated,
u.email,
u.contact_number
FROM requests r
JOIN users u ON u.id = r.user_id
WHERE r.id = ?
LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $requestId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
if (!$request) {
die("Request not found.");
}
$studentName = trim(
($request["last_name"] ?? "") . ", " .
($request["first_name"] ?? "") . " " .
($request["middle_name"] ?? "")
);
$year = date("Y");
$soNumber = "SO-" . $year . "-" . random_int(1000, 9999);
$docTitle = trim($_POST["doc_title"] ?? ("School Order for " . ($request["document_type"] ??
"Document")));
$previewPath = "";
$previewExt = "";
$ocrText = "";
$ocrMessage = "";
/* Parsed fields */
$parsed = [
"receipt_no" => "",
"date" => "",
"name" => "",
"student_id" => "",
"reference_no" => "",
"amount" => ""
];
/* Fetched fallback values */
$fetchedReceiptNo = "";
$fetchedDate = date("F j, Y", strtotime($request["created_at"] ?? "now"));
$fetchedName = $studentName;
$fetchedStudentId = (string)($request["student_id"] ?? "");
$fetchedReferenceNo = (string)($request["reference_no"] ?? "");
$fetchedAmount = "";
/* Final display values */
$displayParsed = [
"receipt_no" => $fetchedReceiptNo,
"date" => $fetchedDate,
"name" => $fetchedName,
"student_id" => $fetchedStudentId,
"reference_no" => $fetchedReferenceNo,
"amount" => $fetchedAmount
];
/* =========================
HELPERS
========================= */
function is_tesseract_available(): bool {
return file_exists(TESSERACT_PATH);
}
function run_tesseract_ocr(string $imagePath, string &$error = ""): string {
if (!is_tesseract_available()) {
$error = "Tesseract OCR not found. Check TESSERACT_PATH in process_create.php.";
return "";
}
$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "ocr_" . uniqid();
$cmd = '"' . TESSERACT_PATH . '" ' .
escapeshellarg($imagePath) . ' ' .
escapeshellarg($base) . ' 2>&1';
exec($cmd, $output, $returnVar);
if ($returnVar !== 0) {
$error = "OCR failed: " . implode("\n", $output);
return "";
}
$txtFile = $base . ".txt";
if (!file_exists($txtFile)) {
$error = "OCR output file was not created.";
return "";
}
$text = file_get_contents($txtFile);
@unlink($txtFile);
return trim((string)$text);
}
function convert_pdf_first_page_to_image(string $pdfPath, string &$error = ""): string {
if (!extension_loaded('imagick')) {
$error = "PDF OCR requires Imagick extension to convert PDF pages to image.";
return "";
}
try {
$imagick = new Imagick();
$imagick->setResolution(200, 200);
$imagick->readImage($pdfPath . "[0]");
$imagick->setImageFormat("png");
$output = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "pdf_ocr_" . uniqid() . ".png";
$imagick->writeImage($output);
$imagick->clear();
$imagick->destroy();
return $output;
} catch (Throwable $e) {
$error = "PDF conversion failed: " . $e->getMessage();
return "";
}
}
function save_uploaded_file(array $file, string &$relativePath, string &$ext, string &$error = ""):
bool {
if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
$error = "No file uploaded or upload failed.";
return false;
}
$tmp = $file["tmp_name"];
$size = (int)$file["size"];
if ($size > 15 * 1024 * 1024) {
$error = "Max file size is 15MB.";
return false;
}
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
$error = "Only JPG, PNG, WEBP, and PDF files are allowed.";
return false;
}
$ext = $allowed[$mime];
$dir = "../uploads/ocr/" . date("Ymd");
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}
$filename = "ocr_" . time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
$dest = $dir . "/" . $filename;
if (!move_uploaded_file($tmp, $dest)) {
$error = "Failed to save uploaded file.";
return false;
}
$relativePath = str_replace("../", "", $dest);
return true;
}
function normalize_spaces(string $text): string {
$text = str_replace(["\r\n", "\r"], "\n", $text);
$text = preg_replace('/[ \t]+/', ' ', $text);
return trim((string)$text);
}
function parse_ocr_fields(string $text): array {
$clean = normalize_spaces($text);
$fields = [
"receipt_no" => "",
"date" => "",
"name" => "",
"student_id" => "",
"reference_no" => "",
"amount" => ""
];
$patterns = [
"receipt_no" => [
'/(?:receipt\s*no|or\s*no|official\s*receipt\s*no|receipt\s*number)\s*[:#-]?\s*([A-Z0-9\-]+)/i',
],
"date" => [
'/(?:date)\s*[:#-]?\s*([A-Za-z0-9,\/\- ]{4,40})/i',
],
"name" => [
'/(?:name|student\s*name)\s*[:#-]?\s*([A-Za-z .,\-]{4,80})/i',
],
"student_id" => [
'/(?:student\s*id|id\s*number|student\s*no)\s*[:#-]?\s*([A-Z0-9\-]+)/i',
],
"reference_no" => [
'/(?:reference\s*no|ref\s*no)\s*[:#-]?\s*([A-Z0-9\-]+)/i',
'/\b(EDOC-\d{4}-\d{3,6})\b/i',
],
"amount" => [
'/(?:amount|total|paid)\s*[:#-]?\s*((?:PHP|Php|php|P)?\s?[0-9,]+\.\d{2})/i',
'/\b((?:PHP|Php|php|P)\s?[0-9,]+\.\d{2})\b/i',
],
];
foreach ($patterns as $key => $list) {
foreach ($list as $pattern) {
if (preg_match($pattern, $clean, $m)) {
$fields[$key] = trim($m[1]);
break;
}
}
}
return $fields;
}
/* =========================
HANDLE UPLOAD + OCR
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") ===
"upload_ocr") {
if (isset($_FILES["ocr_file"])) {
$error = "";
if (save_uploaded_file($_FILES["ocr_file"], $previewPath, $previewExt, $error)) {
$absPath = "../" . $previewPath;
if ($previewExt === "pdf") {
$tmpImage = convert_pdf_first_page_to_image($absPath, $error);
if ($tmpImage !== "") {
$ocrText = run_tesseract_ocr($tmpImage, $error);
@unlink($tmpImage);
} else {
$ocrMessage = $error;
}
} else {
$ocrText = run_tesseract_ocr($absPath, $error);
}
if ($error !== "") {
$ocrMessage = $error;
} elseif ($ocrText !== "") {
$ocrMessage = "OCR extraction completed successfully.";
$parsed = parse_ocr_fields($ocrText);
$displayParsed = [
"receipt_no" => $parsed["receipt_no"] !== "" ? $parsed["receipt_no"] :
$fetchedReceiptNo,
"date" => $parsed["date"] !== "" ? $parsed["date"] : $fetchedDate,
"name" => $parsed["name"] !== "" ? $parsed["name"] :
$fetchedName,
"student_id" => $parsed["student_id"] !== "" ? $parsed["student_id"] :
$fetchedStudentId,
"reference_no" => $parsed["reference_no"] !== "" ? $parsed["reference_no"] :
$fetchedReferenceNo,
"amount" => $parsed["amount"] !== "" ? $parsed["amount"] :
$fetchedAmount
];
} else {
$ocrMessage = "OCR finished, but no readable text was extracted.";
}
} else {
$ocrMessage = $error;
}
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Process Create</title>
<link rel="stylesheet" href="../assets/css/create_document.css">
<link rel="stylesheet" href="../assets/css/process_create.css">
</head>
<body>
<div class="layout">
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
<a class="sb-item" href="dashboard.php"><span
class="sb-icon">🏠</span>Dashboard</a>
<a class="sb-item" href="new_document_request.php"><span
class="sb-icon">📝</span>New Document Request</a>
<a class="sb-item" href="request_management.php"><span
class="sb-icon">🔎</span>Request Management</a>
<a class="sb-item" href="track_progress.php"><span class="sb-icon">📍</span>Track
Progress</a>
<a class="sb-item" href="document_management.php"><span
class="sb-icon">📄</span>Document Management</a>
<a class="sb-item active" href="create_document.php"><span
class="sb-icon">➕</span>Create Document</a>
</nav>
<div class="sb-section-title">SETTINGS</div>
<nav class="sb-nav">
<a class="sb-item" href="../auth/logout.php"><span
class="sb-icon">⎋</span>Logout</a>
</nav>
</aside>
<div class="main">
<header class="topbar">
<button class="hamburger" type="button" onclick="toggleSidebar()">≡</button>
<div class="brand">
<div class="logo"><img src="../assets/img/edoc-logo.jpeg" alt="E-Doc"></div>
<div>E-Doc Document Requesting System</div>
</div>
</header>
<main class="container">
<a class="back-link" href="create_document.php">← Back to Create Document List</a>
<div class="page-head">
<h1>Create School Order (SO)</h1>
<p>Upload a file for OCR data extraction and generate the SO document.</p>
</div>
<div class="create-grid">
<section class="left-column">
<div class="card">
<div class="card-title">Student Information</div>
<div class="info-grid">
<div class="form-group">
<label>Student Name</label>
<input type="text" value="<?= h($studentName) ?>" readonly>
</div>
<div class="form-group">
<label>Student ID</label>
<input type="text" value="<?= h($request["student_id"] ?? "") ?>" readonly>
</div>
<div class="form-group">
<label>Reference Number</label>
<input type="text" value="<?= h($request["reference_no"] ?? "") ?>"
readonly>
</div>
<div class="form-group">
<label>Document Type</label>
<input type="text" value="<?= h($request["document_type"] ?? "") ?>"
readonly>
</div>
<div class="form-group">
<label>Title Type</label>
<input type="text" value="<?= h($request["title_type"] ?? "") ?>" readonly>
</div>
<div class="form-group">
<label>Copies</label>
<input type="text" value="<?= h($request["copies"] ?? "") ?>" readonly>
</div>
</div>
</div>
<form method="POST" enctype="multipart/form-data" class="card">
<input type="hidden" name="action" value="upload_ocr">
<input type="hidden" name="request_id" value="<?= (int)$requestId ?>">
<input type="hidden" name="doc_title" value="<?= h($docTitle) ?>">
<div class="card-title">Upload Document (OCR)</div>
<label class="upload-box" for="ocrFile">
<input type="file" id="ocrFile" name="ocr_file"
accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="this.form.submit()">
<div class="upload-icon">⤴</div>
<div class="upload-text">Click to upload image or PDF</div>
</label>
</form>
<div class="form-group">
<label>SO Number</label>
<input type="text" value="<?= h($soNumber) ?>" readonly>
</div>
<div class="form-group">
<label>Document Title</label>
<input type="text" value="<?= h($docTitle) ?>" readonly>
</div>
<div class="ocr-result-card">
<div class="card-title">OCR Extracted Text</div>
<?php if ($ocrMessage !== ""): ?>
<div class="ocr-message"><?= h($ocrMessage) ?></div>
<?php else: ?>
<div class="ocr-message">Upload a file to extract readable text using
Tesseract OCR.</div>
<?php endif; ?>
<textarea class="ocr-textarea" readonly><?= h($ocrText) ?></textarea>
<div class="card-title parsed-title">Parsed Fields</div>
<div class="parsed-note">Note: Parsed Fields are for testing only, it will be changed later on with the given format from the registrar. 
<div class="parsed-grid">
<div class="parsed-box">
<div class="parsed-label">Receipt / OR Number</div>
<div class="parsed-value"><?= h($displayParsed["receipt_no"] ?: "-")
?></div>
</div>
<div class="parsed-box">
<div class="parsed-label">Date</div>
<div class="parsed-value"><?= h($displayParsed["date"] ?: "-") ?></div>
</div>
<div class="parsed-box">
<div class="parsed-label">Name</div>
<div class="parsed-value"><?= h($displayParsed["name"] ?: "-") ?></div>
</div>
<div class="parsed-box">
<div class="parsed-label">Student ID</div>
<div class="parsed-value"><?= h($displayParsed["student_id"] ?: "-")
?></div>
</div>
<div class="parsed-box">
<div class="parsed-label">Reference Number</div>
<div class="parsed-value"><?= h($displayParsed["reference_no"] ?: "-")
?></div>
</div>
<div class="parsed-box">
<div class="parsed-label">Amount</div>
<div class="parsed-value"><?= h($displayParsed["amount"] ?: "-") ?></div>
</div>
</div>
<div class="so-preview-box">
<h3>Generated SO Preview Data</h3>
<p><strong>SO Number:</strong> <?= h($soNumber) ?></p>
<p><strong>Student:</strong> <?= h($studentName) ?></p>
<p><strong>Student ID:</strong> <?= h($request["student_id"] ?? "") ?></p>
<p><strong>Reference No.:</strong> <?= h($request["reference_no"] ?? "")
?></p>
<p><strong>Document Type:</strong> <?= h($request["document_type"] ??
"") ?></p>
<p><strong>Parsed OCR Reference:</strong> <?=
h($displayParsed["reference_no"] ?: "-") ?></p>
<p><strong>Parsed OCR Name:</strong> <?= h($displayParsed["name"] ?:
"-") ?></p>
</div>
</div>
<div class="action-row">
<button type="button" class="btn-secondary"
onclick="window.location='create_document.php'">Cancel</button>
<button type="button" class="btn-primary">Generate SO</button>
</div>
</section>
<section class="card preview-card">
<div class="card-title">Image Preview</div>
<div class="preview-area" id="previewArea">
<?php if ($previewPath === ""): ?>
<div class="preview-placeholder" id="previewPlaceholder">
<div class="preview-icon">📄</div>
<div class="preview-text">Upload an image or PDF to preview the OCR
source document.</div>
</div>
<?php else: ?>
<?php if ($previewExt === "pdf"): ?>
<iframe class="preview-frame process-preview-frame" src="../<?=
h($previewPath) ?>"></iframe>
<?php else: ?>
<img class="preview-image process-preview-image" src="../<?=
h($previewPath) ?>" alt="Preview">
<?php endif; ?>
<?php endif; ?>
</div>
</section>
</div>
</main>
</div>
</div>
<script>
function toggleSidebar(){
const sb = document.getElementById('sidebar');
if (!sb) return;
if (window.innerWidth <= 720) {
sb.style.display = (sb.style.display === 'none' || sb.style.display === '') ? 'block' : 'none';
}
}
</script>
</body>
</html>
