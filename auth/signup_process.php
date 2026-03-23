<?php
require_once __DIR__ . "/../includes/helpers.php";

function fail($msg) {
  http_response_code(400);
  echo $msg;
  exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") fail("Invalid request.");
csrf_verify();

$first_name = trim($_POST["first_name"] ?? "");
$middle_name = trim($_POST["middle_name"] ?? "");
$last_name = trim($_POST["last_name"] ?? "");
$suffix = trim($_POST["suffix"] ?? "");
$address = trim($_POST["address"] ?? "");
$gender = trim($_POST["gender"] ?? "");
$email = trim($_POST["email"] ?? "");
$contact = trim($_POST["contact"] ?? "");
$student_id = trim($_POST["student_id"] ?? "");
$course = trim($_POST["course"] ?? "");
$major = trim($_POST["major"] ?? "");
$year_graduated = trim($_POST["year_graduated"] ?? "");
$password_raw = $_POST["password"] ?? "";

if ($first_name === "" || $last_name === "" || $address === "" || $email === "" || $course === "" || $password_raw === "") {
  fail("Please complete all required fields.");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail("Invalid email.");
if (strlen($password_raw) < 8) fail("Password must be at least 8 characters.");

$check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->fetch_assoc()) fail("Email already registered.");

$password = password_hash($password_raw, PASSWORD_DEFAULT);

function save_image($fileKey, $targetDir) {
  if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]["error"] !== UPLOAD_ERR_OK) {
    return [false, null, "Missing upload: $fileKey"];
  }

  $tmp = $_FILES[$fileKey]["tmp_name"];
  $size = $_FILES[$fileKey]["size"];

  if ($size > 5 * 1024 * 1024) return [false, null, "Max 5MB only: $fileKey"];

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $tmp);
  finfo_close($finfo);

  $allowed = ["image/jpeg"=>"jpg","image/png"=>"png","image/webp"=>"webp"];
  if (!isset($allowed[$mime])) return [false, null, "Invalid image type (JPG/PNG/WebP only): $fileKey"];

  if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

  $ext = $allowed[$mime];
  $filename = bin2hex(random_bytes(16)) . "." . $ext;

  $path = rtrim($targetDir, "/") . "/" . $filename;
  if (!move_uploaded_file($tmp, $path)) return [false, null, "Upload failed: $fileKey"];

  $relative = str_replace("../", "", $path);
  return [true, $relative, null];
}

list($okFace, $facePath, $errFace) = save_image("face_photo", "../uploads/faces");
if (!$okFace) fail($errFace);

list($okFront, $frontPath, $errFront) = save_image("id_front", "../uploads/ids");
if (!$okFront) fail($errFront);

list($okBack, $backPath, $errBack) = save_image("id_back", "../uploads/ids");
if (!$okBack) fail($errBack);

$sql = "INSERT INTO users
(first_name, middle_name, last_name, suffix, address, gender, email, contact_number, student_id,
 course, major, year_graduated, password, role, verification_status, id_front_path, id_back_path, face_path)
VALUES
(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'USER', 'PENDING', ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  // Cleanup uploaded files if prepare fails
  @unlink("../" . $facePath);
  @unlink("../" . $frontPath);
  @unlink("../" . $backPath);
  fail("Prepare failed: " . $conn->error);
}

/**
 * ✅ IMPORTANT FIX:
 * 16 placeholders => 16 types => "ssssssssssssssss" (16 s)
 */
$stmt->bind_param(
  "ssssssssssssssss",
  $first_name, $middle_name, $last_name, $suffix,
  $address, $gender, $email, $contact, $student_id,
  $course, $major, $year_graduated, $password,
  $frontPath, $backPath, $facePath
);

if (!$stmt->execute()) {
  // Cleanup uploaded files if insert fails
  @unlink("../" . $facePath);
  @unlink("../" . $frontPath);
  @unlink("../" . $backPath);
  fail("Signup failed: " . $stmt->error);
}

$newUserId = $conn->insert_id;
audit_log($conn, "INSERT", "users", $newUserId, "Account created via signup");

header("Location: auth.php?signup=success");
exit();
