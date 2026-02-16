<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "USER") {
  header("Location: ../auth/auth.php");
  exit();
}

$user_id = (int)$_SESSION["user_id"];
$req = $_SESSION["req"] ?? null;

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

$log = $conn->prepare("
  INSERT INTO request_logs (request_id, message)
  VALUES (?, ?)
");
$message = "PENDING - " . strtoupper($document_type) . " (" . strtoupper($title_type) . ")";
$log->bind_param("is", $newRequestId, $message);
$log->execute();

// Clear session draft
unset($_SESSION["req"]);

// Redirect to track page
header("Location: track.php?ref=" . urlencode($reference));
exit();
