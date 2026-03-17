<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);
csrf_verify();

$user_id = $_SESSION["user_id"];
$action  = $_POST["action"] ?? "";
$type    = $_POST["type"] ?? "";

$valid_types = ["front", "back", "face"];
if (!in_array($type, $valid_types, true)) {
    header("Location: profile.php?error=invalid_type");
    exit();
}

/* ── column / directory map ── */
function id_col_dir(string $type): array {
    switch ($type) {
        case "front": return ["id_front_path", "../uploads/ids"];
        case "back":  return ["id_back_path",  "../uploads/ids"];
        case "face":  return ["face_path",     "../uploads/faces"];
        default:      return ["", ""];
    }
}

/* ========================================
   UPLOAD
======================================== */
if ($action === "upload") {

    if (!isset($_FILES["id_file"]) || $_FILES["id_file"]["error"] !== UPLOAD_ERR_OK) {
        header("Location: profile.php?error=upload");
        exit();
    }

    $tmp  = $_FILES["id_file"]["tmp_name"];
    $size = (int)$_FILES["id_file"]["size"];

    if ($size > MAX_FILE_SIZE_BYTES) {
        header("Location: profile.php?error=size");
        exit();
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    $allowed = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp",
    ];

    if (!isset($allowed[$mime])) {
        header("Location: profile.php?error=filetype");
        exit();
    }

    $ext = $allowed[$mime];

    [$col, $dir] = id_col_dir($type);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    /* delete old file */
    $stmt = $conn->prepare("SELECT " . $col . " FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();
    if (!empty($old[$col])) {
        $oldPath = "../" . ltrim($old[$col], "/");
        if (file_exists($oldPath)) @unlink($oldPath);
    }

    $filename = $type . "_" . $user_id . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    $dest     = $dir . "/" . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        header("Location: profile.php?error=move");
        exit();
    }

    $relative = str_replace("../", "", $dest);

    $stmt = $conn->prepare("UPDATE users SET " . $col . " = ? WHERE id = ?");
    $stmt->bind_param("si", $relative, $user_id);
    $stmt->execute();

    $stepIdx = array_search($type, $valid_types);
    header("Location: profile.php?msg=uploaded&step=" . $stepIdx);
    exit();
}

/* ========================================
   DELETE
======================================== */
if ($action === "delete") {

    [$col, ] = id_col_dir($type);

    $stmt = $conn->prepare("SELECT " . $col . " FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();

    if (!empty($old[$col])) {
        $absPath = "../" . ltrim($old[$col], "/");
        if (file_exists($absPath)) @unlink($absPath);

        $stmt = $conn->prepare("UPDATE users SET " . $col . " = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }

    $stepIdx = array_search($type, $valid_types);
    header("Location: profile.php?msg=deleted&step=" . $stepIdx);
    exit();
}

/* fallback */
header("Location: profile.php");
exit();
