<?php
require_once __DIR__ . "/../includes/helpers.php";
$logout_user_id = $_SESSION["user_id"] ?? null;
audit_log($conn, "LOGOUT", "users", $logout_user_id, "User logged out");
session_destroy();
header("Location: auth.php");
exit();
