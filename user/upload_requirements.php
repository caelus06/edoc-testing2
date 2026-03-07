<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "USER") {
  header("Location: ../auth/auth.php");
  exit();
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Upload Requirements</title>
  <link rel="stylesheet" href="../assets/css/upload_requirements.css">
  <style>
    .ref-input{
      width:100%; padding:12px; border-radius:10px;
      border:2px solid #444; background:#f3f3f3; outline:none;
      font-weight:900; text-transform:uppercase;
    }
    .hint{ font-size:12px; color:#444; margin-top:8px; }
    .err{ margin-top:10px; padding:10px; border-radius:10px; border:1px solid #f3b3b3; background:#fff2f2; color:#8a1f1f; font-size:12px; }
  </style>
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div class="logo">📄</div>
    <div>E-Doc Document Requesting System</div>
  </div>
  <div class="top-icons">
    <button class="icon-btn">🔔</button>
    <div class="icon-btn"><a href="profile.php">👤</a></div>
    <div class="icon-btn"><a href="../auth/logout.php">⎋</a></div>
  </div>
</header>

<main class="container">
  <section class="banner">
    <h1>Upload Requirements</h1>
    <p>Digital uploads are required for verification, but all official requirements must be submitted once verified and approved to the Registrar’s Office.</p>
  </section>

  <section class="panel">
    <a class="exit-btn" href="dashboard.php">EXIT</a>

    <div class="h2">Upload by Reference Number</div>
    <p class="sub">Type your reference number exactly as it appears on your request tracking page. Upload the required files, and review the complete list of requirements before submitting.</p>

    <form method="POST" action="upload_requirements_find.php">
      <label class="label">Reference Number *</label>
      <input class="ref-input" name="ref" placeholder="EDOC-2026-1234" required>

      <div class="hint">Example: EDOC-2026-1234</div>

      <div class="actions">
        <button class="btn next" type="submit">NEXT &gt;&gt;&gt;</button>
      </div>
    </form>

    <?php if (isset($_GET["err"])): ?>
      <div class="err"><?= htmlspecialchars($_GET["err"]) ?></div>
    <?php endif; ?>
  </section>
</main>

</body>
</html>
