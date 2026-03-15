<?php
$signupSuccess = (isset($_GET["signup"]) && $_GET["signup"] === "success");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>E-Doc Auth</title>
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>

<header class="topbar">
  <a class="brand" href="../index.php">
    <span class="logo">
      <!-- Optional small logo Waiting for design -->
      <!-- <img src="../assets/img/edoc-logo.jpeg" > -->
    </span>
    <span class="brand-title">E-Doc: Document Requesting System</span>
  </a>
  <nav class="nav">
    <a href="../track_request.php">Track Request</a>
    <a href="../requirements.php">Requirements</a>
    <a class="active-link" href="auth.php">Login</a>
  </nav>
</header>

<main class="page">
  <section class="card">

    <h2 class="title">Document Management System</h2>
    <p class="subtitle">School Registrar Services</p>

    <div class="tabs">
      <button id="loginTab" class="tab active">Login</button>
      <button id="signupTab" class="tab">Sign up</button>
    </div>

    <?php if ($signupSuccess): ?>
      <div class="notice">
        Signup successful! Your account is <b>pending verification</b>.
      </div>
    <?php endif; ?>

    <!-- LOGIN FORM -->
    <form id="loginForm" class="form" method="POST" action="login_process.php">
      <label class="label">EMAIL</label>
      <input class="input" type="email" name="email" placeholder="name@gmail.com" required>

      <div class="row-between">
        <label class="label">PASSWORD</label>
        <a class="link" href="#">Forgot password?</a>
      </div>
      <input class="input" type="password" name="password" placeholder="********" required>

      <button class="btn primary" type="submit">Sign in</button>
    </form>

    <!-- SIGNUP FORM -->
    <form id="signupForm" class="form" method="POST" action="signup_process.php" enctype="multipart/form-data" hidden>

      <div class="grid">
        <div>
          <label class="label">FIRST NAME *</label>
          <input class="input" name="first_name" placeholder="EX. JUAN CARLOS" required>
        </div>

        <div>
          <label class="label">MIDDLE NAME</label>
          <input class="input" name="middle_name" placeholder="EX. CANLAS">
        </div>

        <div>
          <label class="label">LAST NAME *</label>
          <input class="input" name="last_name" placeholder="EX. MACARAEG" required>
        </div>

        <div>
          <label class="label">SUFFIX</label>
          <input class="input" name="suffix" placeholder="Ex. Jr., Sr., III, etc.">
        </div>

        <div>
          <label class="label">COMPLETE ADDRESS *</label>
          <input class="input" name="address" placeholder="Street, Barangay, Municipality/City, Province" required>
        </div>

        <div>
          <label class="label">GENDER</label>
          <select class="input" name="gender">
            <option value="">--- SELECT GENDER ---</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Prefer not to say">Prefer not to say</option>
          </select>
        </div>

        <div>
          <label class="label">EMAIL ADDRESS *</label>
          <input class="input" type="email" name="email" placeholder="Ex. name@gmail.com" required>
        </div>

        <div>
          <label class="label">CONTACT NUMBER</label>
          <input class="input" name="contact" placeholder="Ex. 0909 653 2234">
        </div>

        <div>
          <label class="label">ID NUMBER</label>
          <input class="input" name="student_id" placeholder="Ex. 23-22223-2323">
        </div>

        <div>
          <label class="label">COURSE/PROGRAM *</label>
          <input class="input" name="course" placeholder="Ex. BS Computer Science" required>
        </div>

        <div>
          <label class="label">MAJOR</label>
          <input class="input" name="major" placeholder="Ex. (Optional)">
        </div>

        <div>
          <label class="label">YEAR GRADUATED</label>
          <input class="input" name="year_graduated" placeholder="Ex. 2025 / If current, N/A">
        </div>

        <div class="span-3">
          <label class="label">PASSWORD *</label>
          <input class="input" type="password" name="password" placeholder="Minimum 8 characters" required>
        </div>
      </div>

      <div class="upload-area">
        <div class="upload-left">
          <h3 class="upload-title">SCAN FRONT OF ID *</h3>
          <input class="input" type="file" name="id_front" accept="image/*" required>

          <h3 class="upload-title">SCAN BACK OF ID *</h3>
          <input class="input" type="file" name="id_back" accept="image/*" required>
          
          <h3 class="upload-title">FACE VERIFICATION *</h3>
          <input class="input" type="file" name="face_photo" accept="image/*" required>
        </div>

        <div class="upload-right">
          <h3 class="upload-title">Reminder on ID Uploading for Verification</h3>
          <ul class="tips">
            <li>Upload a clear, colored scanned copy of your valid ID.</li>
            <li>Do not submit blurred, cropped, or shadowed images.</li>
            <li>Ensure all details are visible and readable.</li>
          </ul>
        </div>
      </div>

      <button class="btn primary" type="submit">Sign up</button>
    </form>

  </section>
</main>

<script src="../assets/js/auth.js"></script>
</body>
</html>
