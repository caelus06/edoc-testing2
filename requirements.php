<?php
require_once "config/database.php";

// Document types from requirements_master
$docs = [];
$res = $conn->query("SELECT DISTINCT document_type FROM requirements_master ORDER BY document_type ASC");
if ($res) while ($row = $res->fetch_assoc()) $docs[] = $row["document_type"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>View Requirements</title>
  <link rel="stylesheet" href="assets/css/home.css">
  <link rel="stylesheet" href="assets/css/auth.css">

</head>
<body>

<header class="top-nav">
  <a class="brand" href="index.php">
    <span class="brand-logo">
      <img src="assets/img/edoc-logo.jpeg" alt="E-Doc Logo">
    </span>
    <span class="brand-title">Document Requesting System</span>
  </a>

  <nav>
    <a href="track_request.php">Track Request</a>
    <a href="requirements.php">Requirements</a>
    <a href="auth/auth.php">Login</a>
  </nav>
</header>


<main class="container">
  <section class="hero">
    <h1>View Requirements</h1>
    <p>Select document type and title type to view required documents.</p>
  </section>

  <section class="panel">
    <label class="label">Document Type</label>
    <select id="doc">
      <option value="">-- Select Document Type --</option>
      <?php foreach($docs as $d): ?>
        <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
      <?php endforeach; ?>
    </select>

    <label class="label">Title Type</label>
    <select id="title" disabled>
      <option value="">-- Select Title Type --</option>
    </select>

    <button class="btn" id="btnView" disabled>View Requirements</button>

    <div id="reqList" style="margin-top:14px;"></div>
  </section>
</main>

<script>
const docSel = document.getElementById("doc");
const titleSel = document.getElementById("title");
const btnView = document.getElementById("btnView");
const reqList = document.getElementById("reqList");

docSel.addEventListener("change", async () => {
  const doc = docSel.value.trim();
  titleSel.innerHTML = `<option value="">-- Select Title Type --</option>`;
  titleSel.disabled = true;
  btnView.disabled = true;
  reqList.innerHTML = "";

  if (!doc) return;

  const res = await fetch("api/get_title_types.php?doc=" + encodeURIComponent(doc));
  const rows = await res.json();

  rows.forEach(r => {
    const opt = document.createElement("option");
    opt.value = r.title_type;
    opt.textContent = r.title_type;
    titleSel.appendChild(opt);
  });

  titleSel.disabled = false;
});

titleSel.addEventListener("change", () => {
  btnView.disabled = !titleSel.value;
  reqList.innerHTML = "";
});

btnView.addEventListener("click", async () => {
  const doc = docSel.value.trim();
  const title = titleSel.value.trim();
  if (!doc || !title) return;

  reqList.innerHTML = `<div class="small">Loading...</div>`;

  const res = await fetch("api/get_requirements.php?doc=" + encodeURIComponent(doc) + "&title=" + encodeURIComponent(title));
  const data = await res.json();

  if (!data.ok){
    reqList.innerHTML = `<div class="small">${data.error || "No requirements found."}</div>`;
    return;
  }

  reqList.innerHTML = `
    <div class="table-wrap">
      <table>
        <thead><tr><th>REQUIREMENTS</th></tr></thead>
        <tbody>
          ${data.requirements.map(x => `<tr><td>${x.req_name}</td></tr>`).join("")}
        </tbody>
      </table>
    </div>
  `;
});
</script>

</body>
</html>
