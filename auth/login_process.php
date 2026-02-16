<?php
session_start();
require_once "../config/database.php";

function fail($msg) {
  http_response_code(401);
  echo $msg;
  exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") fail("Invalid request.");

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

// OPTIONAL: block unverified users
if ($user["role"] === "USER" && $user["verification_status"] !== "VERIFIED") {
  fail("Account pending verification.");
}

$_SESSION["user_id"] = $user["id"];
$_SESSION["role"] = $user["role"];

if ($user["role"] === "REGISTRAR") {
  header("Location: ../registrar/dashboard.php");
} elseif ($user["role"] === "MIS") {
  header("Location: ../mis/dashboard.php");
} else {
  header("Location: ../user/dashboard.php");
}
exit();
