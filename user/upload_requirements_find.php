<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "USER") {
  header("Location: ../auth/auth.php");
  exit();
}

$user_id = (int)$_SESSION["user_id"];
$ref = strtoupper(trim($_POST["ref"] ?? ""));

if ($ref === "") {
  header("Location: upload_requirements.php?err=" . urlencode("Please enter a reference number."));
  exit();
}

$stmt = $conn->prepare("
  SELECT id
  FROM requests
  WHERE reference_no = ? AND user_id = ?
  LIMIT 1
");
$stmt->bind_param("si", $ref, $user_id);
$stmt->execute();

if (!$stmt->get_result()->fetch_assoc()) {
  header("Location: upload_requirements.php?err=" . urlencode("Reference number not found or not yours."));
  exit();
}

header("Location: upload_requirements_upload.php?ref=" . urlencode($ref));
exit();
