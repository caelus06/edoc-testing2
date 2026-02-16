<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
  header("Location: ../auth/auth.php");
  exit();
}

$user_id = $_SESSION["user_id"];

// Split "name" back into first/middle/last (simple approach)
// If you want stricter, we can disable name editing.
$name = trim($_POST["name"] ?? "");
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

// Name parse (very basic)
$parts = preg_split('/\s+/', $name);
$first_name = $parts[0] ?? "";
$last_name = (count($parts) >= 2) ? $parts[count($parts)-1] : "";
$middle_name = "";
if (count($parts) > 2) {
  $middle_name = implode(" ", array_slice($parts, 1, -1));
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

header("Location: profile.php?saved=1");
exit();
