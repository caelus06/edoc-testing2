<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);
csrf_verify();

$user_id    = (int)$_SESSION["user_id"];
$request_id = (int)($_SESSION["upload_request_id"] ?? 0);
$ref        = trim($_POST["ref"] ?? ($_SESSION["upload_ref"] ?? ""));

if ($request_id <= 0 || $ref === "") {
  die("No request selected.");
}

// Verify ownership + fetch doc info (helps requirement_key lookup)
$stmt = $conn->prepare("SELECT id, document_type, title_type FROM requests WHERE id=? AND user_id=? LIMIT 1");
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$reqRow = $stmt->get_result()->fetch_assoc();
if (!$reqRow) die("Invalid request.");

// Inputs
$req_names = $_POST["req_names"] ?? [];
$files     = $_FILES["files"] ?? null;

if (!$files || !is_array($req_names) || count($req_names) === 0) {
  die("No files submitted.");
}

$docType  = strtoupper(trim((string)($reqRow["document_type"] ?? "")));
$titleTyp = trim((string)($reqRow["title_type"] ?? ""));

// Upload directory
$uploadDir = "../uploads/requirements/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Helper: find requirement_key by req_name using requirements_master
function find_requirement_key(mysqli $conn, string $docType, string $titleTyp, string $reqName): ?string {
  // 1) Try strict match doc + title + name
  $q1 = $conn->prepare("
    SELECT requirement_key
    FROM requirements_master
    WHERE UPPER(TRIM(document_type)) = UPPER(TRIM(?))
      AND TRIM(title_type) = TRIM(?)
      AND TRIM(req_name) = TRIM(?)
    LIMIT 1
  ");
  $q1->bind_param("sss", $docType, $titleTyp, $reqName);
  $q1->execute();
  $r1 = $q1->get_result()->fetch_assoc();
  if ($r1 && !empty($r1["requirement_key"])) return $r1["requirement_key"];

  // 2) Fallback: doc + name (ignore title)
  $q2 = $conn->prepare("
    SELECT requirement_key
    FROM requirements_master
    WHERE UPPER(TRIM(document_type)) = UPPER(TRIM(?))
      AND TRIM(req_name) = TRIM(?)
    ORDER BY id ASC
    LIMIT 1
  ");
  $q2->bind_param("ss", $docType, $reqName);
  $q2->execute();
  $r2 = $q2->get_result()->fetch_assoc();
  if ($r2 && !empty($r2["requirement_key"])) return $r2["requirement_key"];

  // 3) If still not found, return null (caller will generate a safe fallback key)
  return null;
}

// Helper: safe fallback requirement_key if master lookup fails
function fallback_key(string $reqName): string {
  $k = strtolower(trim($reqName));
  $k = preg_replace('/[^a-z0-9]+/', '_', $k);
  $k = trim($k, '_');
  return $k !== "" ? $k : "requirement";
}

for ($i = 0; $i < count($req_names); $i++) {
  $reqName = trim((string)$req_names[$i]);
  if ($reqName === "") continue;

  if (!isset($files["tmp_name"][$i]) || $files["error"][$i] !== UPLOAD_ERR_OK) {
    die("Upload error for " . htmlspecialchars($reqName));
  }

  if ((int)$files["size"][$i] > 15 * 1024 * 1024) {
    die("File too large (15MB max): " . htmlspecialchars($reqName));
  }

  $tmp = $files["tmp_name"][$i];

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $tmp);
  finfo_close($finfo);

  // If you want images too, add them here. For now you required PDF only:
  if ($mime !== "application/pdf") {
    die("Only PDF files allowed: " . htmlspecialchars($reqName));
  }

  // Determine requirement_key from master
  $rk = find_requirement_key($conn, $docType, $titleTyp, $reqName);
  if (!$rk) $rk = fallback_key($reqName);

  // Save file
  $safeName = preg_replace("/[^a-zA-Z0-9\-_\.]/", "_", basename($files["name"][$i]));
  $newName  = bin2hex(random_bytes(12)) . "_" . $safeName;
  $dest     = $uploadDir . $newName;

  if (!move_uploaded_file($tmp, $dest)) die("Failed to save file.");

  $relativePath = "uploads/requirements/" . $newName;

  // Overwrite by (request_id + requirement_key) — this matches registrar fetching
  $del = $conn->prepare("DELETE FROM request_files WHERE request_id=? AND requirement_key=?");
  $del->bind_param("is", $request_id, $rk);
  $del->execute();

  $ins = $conn->prepare("
    INSERT INTO request_files (request_id, requirement_key, requirement_name, file_path, uploaded_at)
    VALUES (?, ?, ?, ?, NOW())
  ");
  $ins->bind_param("isss", $request_id, $rk, $reqName, $relativePath);
  $ins->execute();
}

// log
add_log($conn, $request_id, "REQUIREMENTS UPLOADED");

// redirect back to track page
header("Location: track.php?ref=" . urlencode($ref));
exit();