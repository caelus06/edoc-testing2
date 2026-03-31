<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);
csrf_verify();

$userId = $_SESSION["user_id"];

$currentPw = $_POST["current_password"] ?? "";
$newPw     = $_POST["new_password"] ?? "";
$confirmPw = $_POST["confirm_password"] ?? "";

// Validate
if ($newPw !== $confirmPw) {
    swal_flash("error", "Error", "New passwords do not match.");
    header("Location: profile.php");
    exit;
}

if (strlen($newPw) < 8) {
    swal_flash("error", "Error", "New password must be at least 8 characters.");
    header("Location: profile.php");
    exit;
}

// Fetch current hash
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($currentPw, $row["password"])) {
    swal_flash("error", "Error", "Current password is incorrect.");
    header("Location: profile.php");
    exit;
}

if (password_verify($newPw, $row["password"])) {
    swal_flash("error", "Error", "New password must be different from current password.");
    header("Location: profile.php");
    exit;
}

// Update
$hash = password_hash($newPw, PASSWORD_DEFAULT);
$update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$update->bind_param("si", $hash, $userId);
$update->execute();
$update->close();

audit_log($conn, "CHANGE_PASSWORD", "users", $userId, "User changed their password");

swal_flash("success", "Success", "Password changed successfully.");
header("Location: profile.php");
exit;
