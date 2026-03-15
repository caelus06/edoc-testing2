<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Track Request</title>
  <link rel="stylesheet" href="assets/css/track_request.css">

</head>
<body>

<header class="topbar">
  <a class="brand" href="index.php">
    <span class="logo">
      <!-- Optional small logo Waiting for design -->
      <!-- <img src="assets/img/edoc-logo.jpeg" alt="E-Doc Logo"> -->
    </span>
    <span class="brand-title">E-Doc: Document Requesting System</span>
  </a>

  <nav class="nav">
    <a href="track_request.php">Track Request</a>
    <a href="requirements.php">Requirements</a>
    <a href="auth/auth.php">Login</a>
  </nav>
</header>


<main class="container">
  <section class="hero">
    <h1>Track Request</h1>
    <p>Enter your reference number to view your request status.</p>
  </section>

  <section class="panel">
    <label class="label">Reference Number</label>
    <input id="ref" placeholder="EDOC-2026-1234" style="text-transform:uppercase;" />

    <button class="btn" id="btnTrack">Track</button>

    <div id="result" style="margin-top:14px;"></div>
  </section>
</main>

<script>
const btn = document.getElementById("btnTrack");
const refInput = document.getElementById("ref");
const result = document.getElementById("result");

function pillClass(status){
  status = (status||"").toUpperCase();
  if (status === "APPROVED" || status === "VERIFIED") return "status-approved";
  if (status === "PROCESSING") return "status-processing";
  if (status === "RETURNED" || status === "CANCELLED") return "status-returned";
  if (status === "COMPLETED" || status === "READY FOR PICKUP" || status === "RELEASED") return "status-completed";
  return "status-pending";
}

btn.addEventListener("click", async () => {
  const ref = (refInput.value || "").trim().toUpperCase();
  if (!ref) {
    result.innerHTML = `<div class="small">Please enter a reference number.</div>`;
    return;
  }

  result.innerHTML = `<div class="small">Loading...</div>`;

  try{
    const res = await fetch("api/public_track.php?ref=" + encodeURIComponent(ref));
    const data = await res.json();

    if (!data.ok){
      result.innerHTML = `<div class="small">${data.error || "Not found."}</div>`;
      return;
    }

    const status = data.request.status || "PENDING";

    let logsHtml = "";
    if (data.logs && data.logs.length){
      logsHtml = `
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>STATUS UPDATE</th><th>DATE</th></tr>
            </thead>
            <tbody>
              ${data.logs
              .sort((a,b) => new Date(b.created_at) - new Date(a.created_at))
                .map((x) => `
                  <tr>
                    <td>${(x.message||"")}</td>
                    <td>${(x.created_at||"")}</td>
                  </tr>
              `).join("")}
            </tbody>
          </table>
        </div>
      `;
    }

    result.innerHTML = `
      <div class="panel" style="margin-top:12px;">
        <div><b>Reference:</b> ${data.request.reference_no}</div>
        <div><b>Document:</b> ${data.request.document_type}</div>
        <div style="margin-top:8px;">
          <span class="status-pill ${pillClass(status)}">${status}</span>
        </div>
        <div class="small" style="margin-top:10px;">
          Privacy note: This public page shows status only (no personal info/files).
        </div>
      </div>
      ${logsHtml}
    `;
  }catch(e){
    result.innerHTML = `<div class="small">Failed to load. Check your server/API.</div>`;
  }
});
</script>

</body>
</html>
