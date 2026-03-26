<?php
require_once __DIR__ . "/../includes/helpers.php";

function fail($msg) {
  swal_flash("error", "Login Failed", $msg);
  header("Location: auth.php");
  exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") fail("Invalid request.");
csrf_verify();

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") fail("Missing credentials.");

$sql = "SELECT id, password, role, verification_status FROM users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($password, $user["password"])) {
  fail("Invalid email or password.");
}

// Block users with RESUBMIT or UNAFFILIATED status — only VERIFIED and PENDING can login
if ($user["role"] === "USER" && !in_array($user["verification_status"], ["VERIFIED", "PENDING"])) {
  fail("Account not verified. Please contact the MIS office.");
}

$_SESSION["user_id"] = $user["id"];
$_SESSION["role"] = $user["role"];
$_SESSION["verification_status"] = $user["verification_status"];

audit_log($conn, "LOGIN", "users", $user["id"], "User logged in");

if ($user["role"] === "REGISTRAR") {
  header("Location: ../registrar/dashboard.php");
} elseif ($user["role"] === "MIS") {
  header("Location: ../mis/dashboard.php");
} else {
  header("Location: ../user/dashboard.php");
}
exit();
