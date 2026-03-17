<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);
csrf_verify();

$user_id = (int)$_SESSION["user_id"];

// Enforce 1-request limit for pending accounts
if (($_SESSION["verification_status"] ?? "") === "PENDING") {
  $limitStmt = $conn->prepare("SELECT COUNT(*) AS total FROM requests WHERE user_id = ?");
  $limitStmt->bind_param("i", $user_id);
  $limitStmt->execute();
  $reqCount = (int)$limitStmt->get_result()->fetch_assoc()["total"];
  if ($reqCount >= 1) {
    header("Location: dashboard.php?limit=1");
    exit();
  }
}

$req = $_SESSION["req"] ?? null;
// --- SAVE EDITS ---
if (isset($_POST['first_name'])) {
    $upd = $conn->prepare("
        UPDATE users SET 
        first_name = ?, middle_name = ?, last_name = ?, suffix = ?, student_id = ?,
        course = ?, major = ?, year_graduated = ?, gender = ?, email = ?, 
        contact_number = ?, address = ?
        WHERE id = ?
    ");
    $upd->bind_param(
        "ssssssssssssi",
        $_POST['first_name'], $_POST['middle_name'], $_POST['last_name'], $_POST['suffix'], $_POST['student_id'],
        $_POST['course'], $_POST['major'], $_POST['year_graduated'], $_POST['gender'], $_POST['email'],
        $_POST['contact_number'], $_POST['address'], $user_id
    );
    $upd->execute();
}

if (!$req) {
  header("Location: request.php");
  exit();
}

$document_type = trim($req["document_type"] ?? "");
$title_type    = trim($req["title_type"] ?? "");
$purpose       = trim($req["purpose"] ?? "");
$copies        = (int)($req["copies"] ?? 1);

if ($document_type === "" || $title_type === "" || $purpose === "" || $copies < 1) {
  header("Location: request.php");
  exit();
}

// ✅ Validate that title_type belongs to document_type (prevents mismatch)
$valid = $conn->prepare("
  SELECT 1
  FROM requirements_master
  WHERE document_type = ? AND title_type = ?
  LIMIT 1
");
$valid->bind_param("ss", $document_type, $title_type);
$valid->execute();
if (!$valid->get_result()->fetch_assoc()) {
  die("Invalid Title Type for selected Document Type.");
}

// Generate unique reference number: EDOC-YYYY-1234
$year = date("Y");
$tries = 0;

do {
  $reference = "EDOC-" . $year . "-" . random_int(1000, 9999);

  $check = $conn->prepare("SELECT id FROM requests WHERE reference_no = ? LIMIT 1");
  $check->bind_param("s", $reference);
  $check->execute();
  $exists = $check->get_result()->fetch_assoc();

  $tries++;
  if ($tries > 10) {
    die("Could not generate reference number.");
  }
} while ($exists);

// Insert request
$stmt = $conn->prepare("
  INSERT INTO requests
    (user_id, reference_no, document_type, title_type, purpose, copies, status)
  VALUES
    (?, ?, ?, ?, ?, ?, 'PENDING')
");
$stmt->bind_param(
  "issssi",
  $user_id,
  $reference,
  $document_type,
  $title_type,
  $purpose,
  $copies
);

if (!$stmt->execute()) {
  die("Request submit failed: " . $stmt->error);
}

// Add tracking log
$newRequestId = $conn->insert_id;

$message = "PENDING - " . strtoupper($document_type) . " (" . strtoupper($title_type) . ")";
add_log($conn, $newRequestId, $message);

// Clear session draft
unset($_SESSION["req"]);

// Redirect to track page
header("Location: track.php?ref=" . urlencode($reference));
exit();
