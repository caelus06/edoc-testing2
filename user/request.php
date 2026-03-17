request.php
<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "USER") {
  header("Location: ../auth/auth.php");
  exit();
}

// Logic for handling the EXIT button clearing the session
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    unset($_SESSION["req"]);
    header("Location: dashboard.php");
    exit();
}

// Block pending accounts that already have a request
$user_id = (int)$_SESSION["user_id"];
if (($_SESSION["verification_status"] ?? "") === "PENDING") {
  $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM requests WHERE user_id = ?");
  $countStmt->bind_param("i", $user_id);
  $countStmt->execute();
  $reqCount = (int)$countStmt->get_result()->fetch_assoc()["total"];
  if ($reqCount >= 1) {
    header("Location: dashboard.php?limit=1");
    exit();
  }
}

// Check if there is existing data in the session to pre-fill the form
$saved_req = $_SESSION["req"] ?? null;

// Document types from DB
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
  <title>Request Document</title>
  <link rel="stylesheet" href="../assets/css/user_request.css">
  <style>
    .banner2{
      background:#fff; border:1px solid #dfe3ea; border-radius:14px;
      padding:18px; box-shadow:0 8px 18px rgba(0,0,0,.06);
      margin-top:18px;
    }
    .banner2 h1{ margin:0; font-size:22px; }
    .banner2 p{ margin:6px 0 0; color:#444; font-size:13px; }
    input[type="text"], input[type="number"]{
      width:100%; padding:12px; border-radius:10px;
      border:2px solid #444; background:#f3f3f3; outline:none;
      font-weight:800;
    }
  </style>
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div class="logo">📄</div>
    <div>E-Doc Document Requesting System</div>
  </div>
  <div class="top-icons">
    <button class="icon-btn" type="button" title="Notifications">🔔</button>
    <div class="icon-btn" title="Account"><a href="profile.php">👤</a></div>
    <div class="icon-btn" title="Logout"><a href="../auth/logout.php">⎋</a></div>
  </div>
</header>

<main class="container">

  <section class="banner2">
    <h1>Request Document</h1>
    <p>Start your application by completing all required fields and reviewing your personal information for accuracy.</p>
  </section>

  <section class="panel">
    <!-- Changed link to pass a 'clear' parameter to trigger the session unset logic above -->
      <a class="exit-btn" href="request.php?clear=1">EXIT</a>

    <div class="h2">Application Details</div>
    <p class="sub">Kindly complete all required fields to ensure accurate processing of your request</p>

    <form method="POST" action="request_review.php" id="requestForm">

      <label class="label">Select Document Type: *</label>
      <select name="document_type" id="documentType" required>
        <option value="">--- Select Document Request: e.g. Transcript, Diploma, Certificate ---</option>
        <?php foreach ($docs as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>"<?= ($saved_req && $saved_req['document_type'] == $d) ? 'selected' : '' ?>>
                <?= htmlspecialchars($d) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <label class="label">Select Title Type: *</label>
      <select name="title_type" id="titleType" required>
        <option value="">--- Select Title Type ---</option>
        <?php if ($saved_req && !empty($saved_req['title_type'])): ?>
                    <option value="<?= htmlspecialchars($saved_req['title_type']) ?>" selected>
                        <?= htmlspecialchars($saved_req['title_type']) ?>
                    </option>
        <?php endif; ?>
      </select>

      <label class="label">Purpose/s of request: *</label>
      <input type="text" name="purpose" placeholder="e.g. employment, transfer, board exam..." required 
            value="<?= htmlspecialchars($saved_req['purpose'] ?? '') ?>">

      <label class="label">Number of Copies: *</label>
      <input type="number" name="copies" min="1" value="1" max="5" required 
            value="<?= htmlspecialchars($saved_req['copies'] ?? '1') ?>">

      <div class="actions">
        <button class="btn next" type="submit">NEXT &gt;&gt;&gt;</button>
      </div>
    </form>
  </section>

</main>

<script>
const docSel = document.getElementById("documentType");
const titleSel = document.getElementById("titleType");

function resetTitle() {
  titleSel.innerHTML = "<option value=''>--- Select Title Type ---</option>";
}

docSel.addEventListener("change", () => {
  const doc = docSel.value.trim();
  resetTitle();
  if (!doc) return;

  fetch("../api/get_title_types.php?doc=" + encodeURIComponent(doc))
    .then(res => res.json())
    .then(rows => {
      rows.forEach(row => {
        const opt = document.createElement("option");
        opt.value = row.title_type;
        opt.textContent = row.title_type;
        // If we returned and this was the previous selection, re-select it
                <?php if ($saved_req): ?>
                if (row.title_type === "<?= addslashes($saved_req['title_type']) ?>") {
                    opt.selected = true;
                }
                <?php endif; ?>
        titleSel.appendChild(opt);
      });
    })
    .catch(() => resetTitle());
});

// Trigger change event on load if a document type is already selected (for "Previous" button scenario)
window.addEventListener('DOMContentLoaded', () => {
    if (docSel.value) {
        docSel.dispatchEvent(new Event('change'));
    }
});
</script>

</body>
</html>
