<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_REGISTRAR);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: create_document.php");
    exit();
}

csrf_verify();

$registrarId = (int)$_SESSION["user_id"];

/* Collect and validate form data */
$request_id      = (int)($_POST["request_id"] ?? 0);
$source_file_id  = (int)($_POST["source_file_id"] ?? 0);
$so_number       = trim($_POST["so_number"] ?? "");
$student_name    = trim($_POST["student_name"] ?? "");
$student_id_num  = trim($_POST["student_id_number"] ?? "");
$course_program  = trim($_POST["course_program"] ?? "");
$document_type   = trim($_POST["document_type"] ?? "");
$date_of_grad    = trim($_POST["date_of_graduation"] ?? "");
$date_issued     = trim($_POST["date_issued"] ?? "");
$registrar_name  = trim($_POST["registrar_name"] ?? "");
$additional_notes = trim($_POST["additional_notes"] ?? "");
$ocr_raw_text    = $_POST["ocr_raw_text"] ?? "";

/* Basic validation */
$errors = [];
if ($request_id <= 0) $errors[] = "Invalid request.";
if ($so_number === "") $errors[] = "SO number is required.";
if ($student_name === "") $errors[] = "Student name is required.";
if ($student_id_num === "") $errors[] = "Student ID is required.";
if ($document_type === "") $errors[] = "Document type is required.";
if ($date_issued === "") $errors[] = "Date issued is required.";
if ($registrar_name === "") $errors[] = "Registrar name is required.";

if (!empty($errors)) {
    die("Validation errors: " . implode(", ", $errors));
}

/* Verify the request exists and has an allowed status */
$reqCheck = $conn->prepare("SELECT id, status FROM requests WHERE id = ? LIMIT 1");
$reqCheck->bind_param("i", $request_id);
$reqCheck->execute();
$reqRow = $reqCheck->get_result()->fetch_assoc();
if (!$reqRow) {
    die("Request not found.");
}

$allowedStatuses = [STATUS_VERIFIED, STATUS_APPROVED, STATUS_PROCESSING];
if (!in_array(strtoupper($reqRow["status"]), $allowedStatuses, true)) {
    die("Cannot create SO for request with status: " . htmlspecialchars($reqRow["status"]));
}

/* Check for duplicate SO number */
$dupCheck = $conn->prepare("SELECT id FROM school_orders WHERE so_number = ? LIMIT 1");
$dupCheck->bind_param("s", $so_number);
$dupCheck->execute();
if ($dupCheck->get_result()->num_rows > 0) {
    die("SO number '" . htmlspecialchars($so_number) . "' already exists. Please use a different number.");
}

/* Generate DOCX using PHPWord TemplateProcessor */
use PhpOffice\PhpWord\TemplateProcessor;

$templatePath = __DIR__ . "/../templates/so_template.docx";
if (!file_exists($templatePath)) {
    die("SO template file not found. Please ensure templates/so_template.docx exists.");
}

$outputDir = __DIR__ . "/../uploads/school_orders";
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$safeSONum = preg_replace('/[^A-Za-z0-9\-]/', '_', $so_number);
$outputFile = $outputDir . "/" . $safeSONum . ".docx";
$relativeFile = "uploads/school_orders/" . $safeSONum . ".docx";

$dateIssuedFormatted = date("F j, Y", strtotime($date_issued));

try {
    $template = new TemplateProcessor($templatePath);
    $template->setValue('so_number', $so_number);
    $template->setValue('student_name', $student_name);
    $template->setValue('student_id', $student_id_num);
    $template->setValue('course_program', $course_program);
    $template->setValue('document_type', $document_type);
    $template->setValue('date_of_graduation', $date_of_grad);
    $template->setValue('date_issued', $dateIssuedFormatted);
    $template->setValue('registrar_name', $registrar_name);
    $template->setValue('additional_notes', $additional_notes);
    $template->saveAs($outputFile);
} catch (\Exception $e) {
    die("Failed to generate DOCX: " . htmlspecialchars($e->getMessage()));
}

/* Save to database */
$source_file_id_val = $source_file_id > 0 ? $source_file_id : null;

$ins = $conn->prepare("
    INSERT INTO school_orders
        (request_id, source_file_id, so_number, status, student_name, student_id_number,
         course_program, document_type, date_of_graduation, date_issued,
         registrar_name, additional_notes, ocr_raw_text, file_path, created_by)
    VALUES (?, ?, ?, 'DRAFT', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$ins->bind_param(
    "iisssssssssssi",
    $request_id,
    $source_file_id_val,
    $so_number,
    $student_name,
    $student_id_num,
    $course_program,
    $document_type,
    $date_of_grad,
    $date_issued,
    $registrar_name,
    $additional_notes,
    $ocr_raw_text,
    $relativeFile,
    $registrarId
);

if (!$ins->execute()) {
    // Clean up generated file on DB error
    @unlink($outputFile);
    die("Failed to save School Order: " . htmlspecialchars($ins->error));
}

$soId = $conn->insert_id;

/* Audit log */
audit_log($conn, "CREATE", "school_orders", $soId, "Generated School Order: " . $so_number . " for request #" . $request_id);
add_log($conn, $request_id, "School Order " . $so_number . " generated (DRAFT)");

/* Redirect to download page */
header("Location: download_so.php?id=" . $soId);
exit();
