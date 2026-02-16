<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "USER") {
  header("Location: ../auth/auth.php");
  exit();
}

// pull ALL doc types from requirements_master (not from requests)
$docs = [];
$res = $conn->query("SELECT DISTINCT document_type FROM requirements_master ORDER BY document_type ASC");
if ($res) {
  while ($row = $res->fetch_assoc()) $docs[] = $row["document_type"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Application Process</title>
  <link rel="stylesheet" href="../assets/css/upload_requirements.css">
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div class="logo">📄</div>
    <div>E-Doc Document Requesting System</div>
  </div>
  <div class="top-icons">
    <button class="icon-btn" type="button">🔔</button>
    <div class="icon-btn"><a href="profile.php">👤</a></div>
    <div class="icon-btn"><a href="../auth/logout.php">⎋</a></div>
  </div>
</header>

<main class="container">
  <section class="banner">
    <h1>Application Process</h1>
    <p>View Requirement and Process</p>
  </section>

  <section class="panel">
    <a class="exit-btn" href="dashboard.php">EXIT</a>

    <div class="note">
      <span class="pin">📌</span><b>Please note: Read carefully the requirement</b><br>
      Digital uploads are required for verification, but all official requirements must be submitted once verified and approved to the Registrar’s Office
    </div>

    <form method="GET" action="application_process_view.php">
      <label class="label">Select Document Request: *</label>

      <select name="document_type" required>
        <option value="">--- Select Document Request: e.g. Transcript, Diploma, Certificate---</option>
        <?php foreach ($docs as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="actions">
        <button class="btn next" type="submit">NEXT &gt;&gt;&gt;</button>
      </div>
    </form>
  </section>
</main>

</body>
</html>
