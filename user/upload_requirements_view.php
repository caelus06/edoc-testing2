<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);

$document_type = trim($_GET["document_type"] ?? "");
if ($document_type === "") { header("Location: upload_requirements.php"); exit(); }

$docUpper = strtoupper($document_type);

// working days + process html
$pStmt = $conn->prepare("SELECT working_days, process_html FROM document_process WHERE document_type = ? LIMIT 1");
$pStmt->bind_param("s", $document_type);
$pStmt->execute();
$proc = $pStmt->get_result()->fetch_assoc();
$workingDays = $proc["working_days"] ?? "";
$processHtml = $proc["process_html"] ?? "<b>Application Process</b><br><br>(No process content set yet.)";

// requirements grouped by title_type
$grad = [];
$notgrad = [];

$rStmt = $conn->prepare("
  SELECT title_type, req_name
  FROM requirements_master
  WHERE UPPER(document_type)=?
  ORDER BY id ASC
");
$rStmt->bind_param("s", $docUpper);
$rStmt->execute();
$all = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($all as $row) {
  $t = strtoupper($row["title_type"]);
  if ($t === "GRADUATE") $grad[] = $row["req_name"];
  if ($t === "NOT-GRADUATE" || $t === "NOT GRADUATE") $notgrad[] = $row["req_name"];
}

$_SESSION["upload_doc_type"] = $document_type;
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Upload Requirements</title>
  <link rel="stylesheet" href="../assets/css/upload_requirements.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div>E-Doc Document Requesting System</div>
  </div>
  <div class="top-icons">
    <div class="icon-btn" title="Account"><a href="profile.php"><i class="bi bi-person-circle"></i></a></div>
    <div class="icon-btn" title="Dashboard"><a href="dashboard.php"><i class="bi bi-house"></i></a></div>
    <button class="icon-btn" title="Logout" onclick="swalConfirm('Logout', 'Are you sure you want to log out?', 'Yes, log out', function(){ window.location='../auth/logout.php'; })"><i class="bi bi-box-arrow-right"></i></button>
  </div>
</header>

<main class="container">
  <section class="banner">
    <h1>Upload Requirements</h1>
    <p>Submit your document request by uploading clear, properly scanned files in PDF format and reviewing the full list of requirements.</p>
  </section>

  <section class="panel">
    <a class="exit-btn" href="dashboard.php"><i class="bi bi-x-lg"></i> EXIT</a>
    <div class="dots">
      <div class="dot"></div><div class="dot active"></div><div class="dot"></div>
    </div>

    <div class="content">
      <div class="section">
        <h3>Documentary Requirements</h3>
        <div class="small-note">Read the process carefully and review the full list of requirements before uploading your documents.</div>

        <div class="doc-head">
          <div class="doc-title"><?= htmlspecialchars($docUpper) ?></div>
          <div class="workdays"><?= htmlspecialchars($workingDays) ?></div>
        </div>

        <h3>Graduate</h3>
        <ul>
          <?php if (count($grad)===0): ?><li>(No Graduate requirements set.)</li>
          <?php else: foreach($grad as $x): ?><li><?= htmlspecialchars($x) ?></li><?php endforeach; endif; ?>
        </ul>

        <h3>Not-Graduate</h3>
        <ul>
          <?php if (count($notgrad)===0): ?><li>(No Not-Graduate requirements set.)</li>
          <?php else: foreach($notgrad as $x): ?><li><?= htmlspecialchars($x) ?></li><?php endforeach; endif; ?>
        </ul>

        <div class="small-note">
          <i>Note: For authorized person</i><br>
          1. Authorization Letter with signature<br>
          2. Photocopy of valid ID of student<br>
          3. Original ID of authorized person
        </div>

        <div class="section">
          <?= $processHtml ?>
        </div>

        <div class="actions">
          <a class="btn prev" href="upload_requirements.php" style="text-decoration:none;display:inline-block;"><i class="bi bi-arrow-left"></i> PREVIOUS</a>
          <a class="btn next" href="upload_requirements_upload.php" style="text-decoration:none;display:inline-block;">NEXT <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
    </div>
  </section>
</main>

</body>
</html>
