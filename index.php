<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>E-Doc Document Requesting System</title>
  <link rel="stylesheet" href="assets/css/home.css">
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div class="logo">
      <!-- Optional small logo Waiting for design -->
      <!-- <img src="assets/img/edoc-logo.jpeg">  -->
    </div>
    <span class="brand-title">E-Doc: Document Requesting System</span>
  </div>

  <nav class="nav">
    <a href="track_request.php">Track Request</a>
    <a href="requirements.php">Requirements</a>
    <a href="auth/auth.php">Login</a>
  </nav>
</header>

<main class="container">

  <!-- HERO -->
  <section class="hero">
    <div class="hero-grid">
      <div>
        <h1>Fast and Secure Academic<br/>Document Requests</h1>
        <p>
          E-Doc is a web-based platform for Students and Alumni of Universidad de Dagupan
          to request, submit, and track academic documents efficiently.
        </p>

        <a class="cta" href="auth/auth.php">Apply for your E-Doc</a>
      </div>

      <div class="hero-imgbox">
        <img src="assets/img/edoc-logo.jpeg" alt="E-Doc Logo"
             onerror="this.style.display='none'; this.parentElement.innerHTML='<b style=\'color:#444\'>E-Doc Logo Here</b>';">
      </div>
    </div>

    <div class="notice">
      <b>Important Notice:</b>
      <span>
        All the hardcopy of the document must be submitted at the Registrar's Office.
        Digital uploads are necessary for document validation.
      </span>
    </div>

    <!-- CARDS -->
    <div class="cards">
      <div class="card">
        <div class="ic">👤</div>
        <h3>Login</h3>
        <p>Log in to securely request and track documents.</p>
        <a class="btn" href="auth/auth.php">LOGIN</a>
      </div>

      <div class="card">
        <div class="ic">📄</div>
        <h3>Requirements</h3>
        <p>View document requirements for your application.</p>
        <a class="btn" href="requirements.php">Requirements</a>
      </div>

      <div class="card">
        <div class="ic">🔎</div>
        <h3>Track Request</h3>
        <p>Monitor your request status and progress updates.</p>
        <a class="btn" href="track_request.php">TRACK REQUEST</a>
      </div>
    </div>
  </section>

  <!-- STEPS -->
  <section class="steps">
    <h2>How to Apply for Your E-Doc</h2>

    <div class="step">
      <b>1. Register Your Account</b><br/>
      <span class="muted">
        Create your E-Doc User Account by filling in all required fields with accurate details.
        Use a valid email address for verification and notifications. Set a secure password, then submit the form to activate your account.
      </span>
    </div>

    <div class="step">
      <b>2. Submit Your Request Online</b><br/>
      <span class="muted">
        Login and complete the E-Doc application form. Provide accurate information and upload all required documents in PDF format.
        Double-check your entries to avoid errors and ensure smooth processing.
      </span>
    </div>

    <div class="step">
      <b>3. Provide Physical Requirements</b><br/>
      <span class="muted">
        Bring the original or required physical documents to the Registrar’s Office together with your reference number.
        This step ensures proper validation of your request.
      </span>
    </div>

    <div class="step">
      <b>4. Track Your Application</b><br/>
      <span class="muted">
        Monitor the progress of your request online using your unique reference number.
        Updates will be reflected in your account dashboard.
      </span>
    </div>

    <div class="step">
      <b>5. Manage Your Requests</b><br/>
      <span class="muted">
        Use the My Requests section to view all your submitted applications.
        You can also check the Requirements tab to confirm what documents are needed for future requests.
      </span>
    </div>

    <div class="step">
      <b>6. Claim Your Document</b><br/>
      <span class="muted">
        Once processing is complete, present your reference number at the Registrar’s Office to claim your official document.
      </span>
    </div>
  </section>

</main>

<footer class="footer-bar">
  <div class="footer-container">
    <div class="footer-line">
      <span>&copy; 2026 Universidad de Dagupan | E-Doc Document Requesting System | </span>
      <span>Powered by Quadcore</span>
    </div>
    
    <div class="footer-line team-credits">
      <span class="sep">|</span>
      <span><b>Project Manager:</b> <a href="https://www.instagram.com/itsme_sias/" class="member-link" target="_blank">John Mesias Bautista</a></span>
      <span class="sep">|</span>
      <span><b>Business Analyst:</b> <a href="https://www.instagram.com/my.cahla/" class="member-link" target="_blank">Michaela Patungan</a></span>
      <span class="sep">|</span>
      <span><b>Software Developer:</b> <a href="https://www.instagram.com/celiio.o/" class="member-link" target="_blank">Russel Perez</a></span>
      <span class="sep">|</span>
      <span><b>QA:</b> <a href="https://www.facebook.com/junjeremy.arciaga" class="member-link" target="_blank">Jeremy Arciaga</a></span>
    </div>
  </div>
</footer>

</body>
</html>
