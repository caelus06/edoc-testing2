<?php
require_once __DIR__ . "/../includes/helpers.php";

// Allow both REGISTRAR and MIS to access compliance settings.
// SMTP settings are MIS-only (checked in POST handler).
if (($_SESSION["role"] ?? "") !== ROLE_MIS && ($_SESSION["role"] ?? "") !== ROLE_REGISTRAR) {
    header("Location: ../auth/auth.php");
    exit();
}
$isMIS = ($_SESSION["role"] ?? "") === ROLE_MIS;

$userId   = (int)$_SESSION["user_id"];
$userRole = $_SESSION["role"];

// Fetch current user name
$userName = "Admin";
$me = $conn->prepare("SELECT first_name, last_name FROM users WHERE id=? LIMIT 1");
$me->bind_param("i", $userId);
$me->execute();
$mr = $me->get_result()->fetch_assoc();
if ($mr) $userName = trim(($mr["first_name"] ?? "") . " " . ($mr["last_name"] ?? ""));

// Handle POST
$testResult = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_verify();
    $action = trim($_POST["action"] ?? "");

    if ($action === "save_compliance") {
        $days     = max(1, min(30,  (int)($_POST["threshold_days"]  ?? 7)));
        $cooldown = max(1, min(168, (int)($_POST["cooldown_hours"]  ?? 48)));
        set_setting($conn, "compliance_threshold_days",  (string)$days,     $userId);
        set_setting($conn, "compliance_cooldown_hours",  (string)$cooldown, $userId);
        audit_log($conn, "UPDATE", "system_settings", null,
            "Updated compliance settings: threshold={$days}d, cooldown={$cooldown}h");
        swal_flash("success", "Saved", "Compliance settings updated.");
        header("Location: system_settings.php");
        exit();
    }

    if ($action === "save_smtp" && $isMIS) {
        $email = trim($_POST["smtp_email"]        ?? "");
        $pass  = trim($_POST["smtp_app_password"] ?? "");
        $name  = trim($_POST["smtp_sender_name"]  ?? "E-Doc System");
        set_setting($conn, "smtp_email",        $email, $userId);
        set_setting($conn, "smtp_app_password", $pass,  $userId);
        set_setting($conn, "smtp_sender_name",  $name,  $userId);
        audit_log($conn, "UPDATE", "system_settings", null, "Updated SMTP settings");
        swal_flash("success", "Saved", "SMTP settings updated.");
        header("Location: system_settings.php");
        exit();
    }

    if ($action === "test_smtp" && $isMIS) {
        $testEmail = trim($_POST["test_email"] ?? "");
        if ($testEmail !== "") {
            $ok = send_email($conn, $testEmail, "E-Doc SMTP Test",
                "<p>This is a test email from the E-Doc System. If you received this, SMTP is configured correctly.</p>");
            $testResult = $ok ? "success" : "fail";
        }
    }
}

// Load current settings
$thresholdDays = get_setting($conn, "compliance_threshold_days") ?: "7";
$cooldownHours = get_setting($conn, "compliance_cooldown_hours") ?: "48";
$smtpEmail     = get_setting($conn, "smtp_email");
$smtpPass      = get_setting($conn, "smtp_app_password");
$smtpName      = get_setting($conn, "smtp_sender_name") ?: "E-Doc System";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>System Settings</title>
  <link rel="stylesheet" href="../assets/css/system_settings.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
</head>
<body>

<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sb-user">
      <div class="avatar">👤</div>
      <div class="meta">
        <div class="name"><?= h($userName) ?></div>
        <div class="role"><?= $userRole === ROLE_MIS ? "MIS Admin" : "Registrar" ?></div>
      </div>
    </div>

    <div class="sb-badge"><?= $isMIS ? "MIS Admin" : "Registrar" ?></div>

    <div class="sb-section-title">MODULES</div>
    <nav class="sb-nav">
      <a class="sb-item" href="dashboard.php"><span class="sb-icon">🏠</span>Dashboard</a>
      <a class="sb-item" href="account_management.php"><span class="sb-icon">👥</span>Account Management</a>
      <a class="sb-item" href="reports.php"><span class="sb-icon">📊</span>Reports</a>
      <a class="sb-item" href="audit_logs.php"><span class="sb-icon">🛡️</span>Audit Logs</a>
      <a class="sb-item active" href="system_settings.php"><span class="sb-icon">&#9881;</span>System Settings</a>
    </nav>

    <div class="sb-section-title">SETTINGS</div>
    <nav class="sb-nav">
      <a class="sb-item" href="#" onclick="event.preventDefault(); swalConfirm('Logout', 'Are you sure you want to log out?', 'Yes, log out', function(){ window.location='../auth/logout.php'; })"><span class="sb-icon">⎋</span>Logout</a>
    </nav>
  </aside>

  <!-- MAIN -->
  <div class="main">
    <header class="topbar">
      <button class="hamburger" type="button" onclick="toggleSidebar()">≡</button>

      <div class="brand">
        <div class="logo">
          <img src="../assets/img/edoc-logo.jpeg" alt="E-Doc">
        </div>
        <div>E-Doc Document Requesting System</div>
      </div>
    </header>

    <main class="container">

      <div class="page-head">
        <h1>System Settings</h1>
        <p>Configure compliance rules and email (SMTP) settings.</p>
      </div>

      <div class="settings-grid">

        <!-- CARD 1: Compliance Settings -->
        <div class="settings-card">
          <h2>Compliance Settings</h2>
          <form method="POST" action="system_settings.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_compliance">

            <div class="form-group">
              <label for="threshold_days">Threshold Days</label>
              <input
                type="number"
                id="threshold_days"
                name="threshold_days"
                min="1"
                max="30"
                value="<?= h($thresholdDays) ?>"
              >
              <div class="form-hint">Number of days before a request is considered non-compliant (1&ndash;30).</div>
            </div>

            <div class="form-group">
              <label for="cooldown_hours">Cooldown Hours</label>
              <input
                type="number"
                id="cooldown_hours"
                name="cooldown_hours"
                min="1"
                max="168"
                value="<?= h($cooldownHours) ?>"
              >
              <div class="form-hint">Hours to wait before sending another compliance reminder (1&ndash;168).</div>
            </div>

            <div class="btn-row">
              <button type="submit" class="btn-save">Save Compliance Settings</button>
            </div>
          </form>
        </div>

        <?php if ($isMIS): ?>
        <!-- CARD 2: SMTP Email Settings -->
        <div class="settings-card">
          <h2>SMTP Email Settings</h2>
          <form method="POST" action="system_settings.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_smtp">

            <div class="form-group">
              <label for="smtp_email">Gmail Address</label>
              <input
                type="text"
                id="smtp_email"
                name="smtp_email"
                placeholder="you@gmail.com"
                value="<?= h($smtpEmail) ?>"
              >
            </div>

            <div class="form-group">
              <label for="smtp_app_password">App Password</label>
              <input
                type="password"
                id="smtp_app_password"
                name="smtp_app_password"
                placeholder="Google App Password"
                value="<?= h($smtpPass) ?>"
              >
              <div class="form-hint">Use a Gmail App Password, not your regular account password.</div>
            </div>

            <div class="form-group">
              <label for="smtp_sender_name">Sender Name</label>
              <input
                type="text"
                id="smtp_sender_name"
                name="smtp_sender_name"
                placeholder="E-Doc System"
                value="<?= h($smtpName) ?>"
              >
            </div>

            <div class="btn-row">
              <button type="submit" class="btn-save">Save SMTP Settings</button>
            </div>
          </form>

          <!-- Test Section -->
          <div class="test-section">
            <h3>Test SMTP Connection</h3>
            <form method="POST" action="system_settings.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="test_smtp">

              <div class="form-group">
                <label for="test_email">Send test email to</label>
                <input
                  type="text"
                  id="test_email"
                  name="test_email"
                  placeholder="recipient@example.com"
                >
              </div>

              <div class="btn-row">
                <button type="submit" class="btn-test">Send Test Email</button>
              </div>
            </form>

            <?php if ($testResult !== null): ?>
              <?php if ($testResult === "success"): ?>
                <div class="test-result success">Test email sent successfully. Check your inbox.</div>
              <?php else: ?>
                <div class="test-result fail">Test email failed. Check SMTP credentials and try again.</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /.settings-grid -->

    </main>
  </div>
</div>

<div class="footer-bar"></div>

<script>
function toggleSidebar(){
  const sb = document.getElementById('sidebar');
  if(!sb) return;
  if (window.innerWidth <= 720) {
    sb.style.display = (sb.style.display === 'none' || sb.style.display === '') ? 'block' : 'none';
  }
}
</script>

</body>
</html>
