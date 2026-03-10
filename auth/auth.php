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

<header class="top-nav">
  <a class="brand" href="../index.php">
    <span class="brand-logo">
      <img src="../assets/img/edoc-logo.jpeg" >
    </span>
    <span class="brand-title">Document Requesting System</span>
  </a>
  <nav>
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
          <select class="input" name="course" id="courseSelect" required>
            <option value="">--- SELECT COURSE/PROGRAM ---</option>

            <optgroup label="School of Information Technology Education (SITE)">
              <option value="BS COMPUTER SCIENCE">BS COMPUTER SCIENCE (BSCS)</option>
              <option value="BS INFORMATION TECHNOLOGY">BS INFORMATION TECHNOLOGY (BSIT)</option>
              <option value="ASSOCIATE IN COMPUTER SCIENCE">ASSOCIATE IN COMPUTER SCIENCE (ACS) (OLD)</option>
            </optgroup>

            <optgroup label="School of Health Sciences (SOHS)">
              <option value="BS NURSING">BS NURSING (BSN)</option>
              <option value="DIPLOMA IN MIDWIFERY">DIPLOMA IN MIDWIFERY (DM) (OLD)</option>
            </optgroup>

            <optgroup label="School of Engineering (SOE)">
              <option value="BS CIVIL ENGINEERING">BS CIVIL ENGINEERING (BSCE)</option>
              <option value="BS ELECTRICAL ENGINEERING">BS ELECTRICAL ENGINEERING (BSEE)</option>
              <option value="BS COMPUTER ENGINEERING">BS COMPUTER ENGINEERING (BSCPE)</option>
              <option value="ASSOCIATE IN COMPUTER TECHNOLOGY">ASSOCIATE IN COMPUTER TECHNOLOGY (ACPT) (OLD)</option>
              <option value="BS MECHANICAL ENGINEERING">BS MECHANICAL ENGINEERING (BSME)</option>
              <option value="BS ELECTRONICS ENGINEERING">BS ELECTRONICS ENGINEERING (BSECE)</option>
              <option value="BS ELECTRONICS COMMUNICATION ENGINEERING">BS ELECTRONICS COMMUNICATION ENGINEERING (BSECE) (OLD)</option>
              <option value="ASSOCIATE IN ELECTRONICS & COMMUNICATION">ASSOCIATE IN ELECTRONICS & COMMUNICATION (AEC) (OLD)</option>
            </optgroup>

            <optgroup label="School of Teacher Education (STE)">
              <option value="BS EDUCATION">BS EDUCATION (BSED)</option>
              <option value="BE EDUCATION">BE EDUCATION (BEED)</option>
              <option value="BE EDUCATION MAJOR IN GENERAL EDUCATION">BE EDUCATION MAJOR IN GENERAL EDUCATION (BEED GENED)</option>
            </optgroup>

            <optgroup label="School of Humanities (SOH)">
              <option value="BS PSYCHOLOGY">BS PSYCHOLOGY (BS PSYCH)</option>
              <option value="AB MASS COMMUNICATION">AB MASS COMMUNICATION (ABMC) (OLD)</option>
              <option value="AB COMMUNICATION">AB COMMUNICATION (ABC)</option>
            </optgroup>

            <optgroup label="School of Business and Accountancy (SBA)">
              <option value="BS ACCOUNTANCY">BS ACCOUNTANCY (BSA)</option>
              <option value="BS BUSINESS ADMINISTRATION MAJOR IN HUMAN RESOURCE DEVELOPMENT">BS BUSINESS ADMINISTRATION MAJOR IN HUMAN RESOURCE DEVELOPMENT (BSBA HRD)</option>
              <option value="BS BUSINESS ADMINISTRATION MAJOR IN HUMAN RESOURCE DEVELOPMENT MANAGEMENT">BS BUSINESS ADMINISTRATION MAJOR IN HUMAN RESOURCE DEVELOPMENT MANAGEMENT (BSBA HRDM) (OLD)</option>
              <option value="BS BUSINESS ADMINISTRATION">BS BUSINESS ADMINISTRATION (BSBA)</option>
            </optgroup>

            <optgroup label="School of International Hospitality Management (SIHM)">
              <option value="BS HOTEL AND RESTAURANT MANAGEMENT">BS HOTEL AND RESTAURANT MANAGEMENT (BS HRM)</option>
              <option value="ASSOCIATE HOTEL AND RESTAURANT MANAGEMENT">ASSOCIATE HOTEL AND RESTAURANT MANAGEMENT (AHRM)</option>
              <option value="BS HOSPITALITY MANAGEMENT">BS HOSPITALITY MANAGEMENT (BS HM)</option>
              <option value="BS TOURISM MANAGEMENT">BS TOURISM MANAGEMENT (BSTM)</option>
            </optgroup>
          </select>
        </div>

        <div>
          <label class="label">MAJOR</label>
          <select class="input" name="major" id="majorSelect" disabled>
            <option value="">--- SELECT MAJOR (IF APPLICABLE) ---</option>
          </select>
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

<script>
  const majorMap = {
    "BS COMPUTER SCIENCE": [
      "DATA SCIENCE",
      "INTERNET ENGINEERING"
    ],
    "BS INFORMATION TECHNOLOGY": [
      "WEB DEVELOPMENT",
      "MULTIMEDIA ARTS",
      "INFRASTRUCTURE W/ CYBERSECURITY"
    ],
    "BS EDUCATION": [
      "BIOLOGICAL SCIENCE (BSED SCIE)",
      "FILIPINO (BSED FIL)",
      "MATH (BSED MATH)",
      "ENGLISH (BSED ENG)",
      "EARLY CHILDHOOD EDUCATION (BEED ECE)"
    ],
    "BS BUSINESS ADMINISTRATION": [
      "FINANCIAL MANAGEMENT (BSBA FM)",
      "MANAGEMENT ACCOUNTING (BSBA MA)",
      "BANKING AND FINANCE",
      "MARKETING MANAGEMENT (BSBA MM)"
    ]
  };

  const courseSelect = document.getElementById("courseSelect");
  const majorSelect = document.getElementById("majorSelect");

  courseSelect.addEventListener("change", function () {
    const selected = this.value;
    const majors = majorMap[selected] || [];

    majorSelect.innerHTML = '<option value="">--- SELECT MAJOR (IF APPLICABLE) ---</option>';

    if (majors.length > 0) {
      majors.forEach(function (m) {
        const opt = document.createElement("option");
        opt.value = m;
        opt.textContent = m;
        majorSelect.appendChild(opt);
      });
      majorSelect.disabled = false;
      majorSelect.required = true;
    } else {
      majorSelect.disabled = true;
      majorSelect.required = false;
    }
  });
</script>

</body>
</html>
