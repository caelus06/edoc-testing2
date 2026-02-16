<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "USER") {
  header("Location: ../auth/auth.php");
  exit();
}

$user_id = (int)$_SESSION["user_id"];

// Save step-1 inputs in session
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $_SESSION["req"] = [
    "document_type" => trim($_POST["document_type"] ?? ""),
    "title_type"    => trim($_POST["title_type"] ?? ""),
    "purpose"       => trim($_POST["purpose"] ?? ""),
    "copies"        => (int)($_POST["copies"] ?? 1),
  ];
}

$req = $_SESSION["req"] ?? null;
if (
  !$req ||
  $req["document_type"] === "" ||
  $req["title_type"] === "" ||
  $req["purpose"] === "" ||
  (int)$req["copies"] < 1
) {
  header("Location: request.php");
  exit();
}

// Fetch user info
$stmt = $conn->prepare("
  SELECT first_name, middle_name, last_name, suffix, student_id, course, major, year_graduated, gender, email, contact_number, address
  FROM users
  WHERE id = ?
  LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();

$fullName = trim(
  ($u["first_name"] ?? "") . " " .
  ($u["middle_name"] ?? "") . " " .
  ($u["last_name"] ?? "") . " " .
  ($u["suffix"] ?? "")
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Request Document - Review</title>
  <link rel="stylesheet" href="../assets/css/request.css">
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div class="logo">📄</div>
    <div>E-Doc Document Requesting System</div>
  </div>
  <div class="top-icons">
    <button class="icon-btn" title="Notifications">🔔</button>
    <div class="icon-btn" title="Account"><a href="profile.php">👤</a></div>
    <div class="icon-btn" title="Logout"><a href="../auth/logout.php">⎋</a></div>
  </div>
</header>

<main class="container">
  <section class="banner">
    <h1>Request Document</h1>
    <p>Start your application by completing all required fields and reviewing your personal information for accuracy.</p>
  </section>

  <section class="panel">
    <a class="exit-btn" href="dashboard.php">EXIT</a>

    <h2>Review Your Personal Information</h2>
    <p class="sub">Ensure that the details you provide are accurate and consistent with your official academic records.</p>

    <div class="info">
      <p><b>Name:</b> <?= htmlspecialchars($fullName ?: "N/A") ?></p>
      <p><b>ID Number:</b> <?= htmlspecialchars($u["student_id"] ?: "N/A") ?></p>
      <p><b>Course/Program:</b> <?= htmlspecialchars($u["course"] ?: "N/A") ?></p>
      <p><b>Major:</b> <?= htmlspecialchars($u["major"] ?: "N/A") ?></p>
      <p><b>Year Graduated:</b> <?= htmlspecialchars($u["year_graduated"] ?: "N/A") ?></p>
      <p><b>Gender:</b> <?= htmlspecialchars($u["gender"] ?: "N/A") ?></p>
      <p><b>Email:</b> <?= htmlspecialchars($u["email"] ?: "N/A") ?></p>
      <p><b>Contact Number:</b> <?= htmlspecialchars($u["contact_number"] ?: "N/A") ?></p>
      <p><b>Complete Address:</b> <?= htmlspecialchars($u["address"] ?: "N/A") ?></p>
    </div>

    <hr style="border:none;border-top:1px solid #eef1f6;margin:18px 0;">

    <div class="info">
      <p><b>Document Type:</b> <?= htmlspecialchars($req["document_type"]) ?></p>
      <p><b>Title Type:</b> <?= htmlspecialchars($req["title_type"]) ?></p>
      <p><b>Purpose:</b> <?= htmlspecialchars($req["purpose"]) ?></p>
      <p><b>Copies:</b> <?= (int)$req["copies"] ?></p>
    </div>

    <div class="actions">
      <a class="btn prev" href="request.php" style="text-decoration:none;display:inline-block;">&lt;&lt;&lt; PREVIOUS</a>

      <form method="POST" action="request_submit.php" style="margin:0;">
        <button class="btn save" type="submit">SAVE</button>
      </form>
    </div>
  </section>
</main>

</body>
</html>
