<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);
csrf_verify();

$user_id = $_SESSION["user_id"];

// Split "name" back into first/middle/last (simple approach)
// If you want stricter, we can disable name editing.
$first_name = trim($_POST["first_name"] ?? "");
$middle_name = trim($_POST["middle_name"] ?? "");
$last_name = trim($_POST["last_name"] ?? "");
$suffix = trim($_POST["suffix"] ?? "");
$student_id = trim($_POST["student_id"] ?? "");
$course = trim($_POST["course"] ?? "");
$major = trim($_POST["major"] ?? "");
$year_graduated = trim($_POST["year_graduated"] ?? "");
$gender = trim($_POST["gender"] ?? "");
$email = trim($_POST["email"] ?? "");
$contact_number = trim($_POST["contact_number"] ?? "");
$address = trim($_POST["address"] ?? "");

// Basic email check (optional)
if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  die("Invalid email.");
}

$stmt = $conn->prepare("
  UPDATE users
  SET first_name=?, middle_name=?, last_name=?, suffix=?,
      student_id=?, course=?, major=?, year_graduated=?,
      gender=?, email=?, contact_number=?, address=?
  WHERE id=?
");

$stmt->bind_param(
  "ssssssssssssi",
  $first_name, $middle_name, $last_name, $suffix,
  $student_id, $course, $major, $year_graduated,
  $gender, $email, $contact_number, $address,
  $user_id
);

$stmt->execute();

audit_log($conn, "UPDATE", "users", $user_id, "Profile updated");

header("Location: profile.php?saved=1");
exit();
