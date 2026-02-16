<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "USER") {
  header("Location: ../auth/auth.php");
  exit();
}

$user_id = (int)$_SESSION["user_id"];
$request_id = (int)($_SESSION["upload_request_id"] ?? 0);
$ref = trim($_POST["ref"] ?? ($_SESSION["upload_ref"] ?? ""));

if ($request_id <= 0 || $ref === "") {
  die("No request selected.");
}

// Verify ownership
$stmt = $conn->prepare("SELECT id FROM requests WHERE id=? AND user_id=? LIMIT 1");
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) die("Invalid request.");

// Inputs
$req_names = $_POST["req_names"] ?? [];
$files = $_FILES["files"] ?? null;

if (!$files || count($req_names) === 0) die("No files submitted.");

$uploadDir = "../uploads/requirements/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

for ($i = 0; $i < count($req_names); $i++) {
  if (!isset($files["tmp_name"][$i]) || $files["error"][$i] !== UPLOAD_ERR_OK) {
    die("Upload error for " . htmlspecialchars($req_names[$i]));
  }

  if ($files["size"][$i] > 15 * 1024 * 1024) {
    die("File too large (15MB max): " . htmlspecialchars($req_names[$i]));
  }

  $tmp = $files["tmp_name"][$i];

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $tmp);
  finfo_close($finfo);

  if ($mime !== "application/pdf") {
    die("Only PDF files allowed: " . htmlspecialchars($req_names[$i]));
  }

  $safeName = preg_replace("/[^a-zA-Z0-9\-_\.]/", "_", basename($files["name"][$i]));
  $newName = bin2hex(random_bytes(12)) . "_" . $safeName;
  $dest = $uploadDir . $newName;

  if (!move_uploaded_file($tmp, $dest)) die("Failed to save file.");

  $relativePath = "uploads/requirements/" . $newName;

  // overwrite same requirement
  $del = $conn->prepare("DELETE FROM request_files WHERE request_id=? AND requirement_name=?");
  $del->bind_param("is", $request_id, $req_names[$i]);
  $del->execute();

  $ins = $conn->prepare("
    INSERT INTO request_files (request_id, requirement_name, file_path)
    VALUES (?, ?, ?)
  ");
  $ins->bind_param("iss", $request_id, $req_names[$i], $relativePath);
  $ins->execute();
}

// log
$log = $conn->prepare("INSERT INTO request_logs (request_id, message) VALUES (?, ?)");
$msg = "REQUIREMENTS UPLOADED";
$log->bind_param("is", $request_id, $msg);
$log->execute();

// redirect back to track page for that request
header("Location: track.php?ref=" . urlencode($ref));
exit();
