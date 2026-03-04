<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
  header("Location: ../auth/auth.php");
  exit();
}

$user_id = $_SESSION["user_id"];

// Fetch user record
$stmt = $conn->prepare("
  SELECT first_name, middle_name, last_name, suffix, student_id, course, major, year_graduated,
         gender, email, contact_number, address, verification_status,
         id_front_path, id_back_path, face_path
  FROM users
  WHERE id = ?
  LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();

if (!$u) {
  die("User not found.");
}

// $fullName = trim($u["first_name"] . " " . ($u["middle_name"] ?? "") . " " . $u["last_name"]);

// Image fallbacks (if missing)
$frontImg = !empty($u["id_front_path"]) ? "../" . $u["id_front_path"] : "https://via.placeholder.com/900x500?text=No+Front+ID";
$faceImg  = !empty($u["face_path"])     ? "../" . $u["face_path"]     : "https://via.placeholder.com/900x500?text=No+Face+Photo";
$backImg  = !empty($u["id_back_path"])  ? "../" . $u["id_back_path"]  : "https://via.placeholder.com/900x500?text=No+Back+ID";

$status = strtoupper($u["verification_status"] ?? "PENDING");
$badgeText = ($status === "VERIFIED") ? "✓ VERIFIED" : (($status === "REJECTED") ? "✗ REJECTED" : "⏳ PENDING");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Profile</title>
  <link rel="stylesheet" href="../assets/css/profile.css">
</head>
<body>

<div class="wrapper">
  <div class="card">
    <button class="close-x" onclick="window.location.href='dashboard.php'">×</button>

    <div class="grid">

      <!-- LEFT IMAGE VIEWER -->
      <div class="left-box">
        <div class="left-title" id="leftTitle">FRONT ID</div>

        <div class="preview">
          <img id="previewImg"
               src=""
               alt="Preview"
               data-front="<?= htmlspecialchars($frontImg) ?>"
               data-face="<?= htmlspecialchars($faceImg) ?>"
               data-back="<?= htmlspecialchars($backImg) ?>">
        </div>

        <div class="left-actions">
          <button class="nav-arrow" type="button" onclick="prevStep()">&lt;</button>
          <button class="btn delete" type="button">DELETE</button>
          <button class="btn upload" type="button">UPLOAD</button>
          <button class="nav-arrow" type="button" onclick="nextStep()">&gt;</button>
        </div>
      </div>

      <!-- RIGHT INFO -->
      <div class="right-box">
        <div class="top-right">
          <div>
            <p class="h-title">Personal Information</p>
            <p class="p-sub"><b>Ensure that the details that provide are accurate and consistent with your official academic records.</b>
            </p>
          </div>
        </div>

        <form method="POST" action="profile_update.php">
          <div class="info">

            <?php
              // Helper to render editable row
              function row($key, $label, $value) {
                $safe = htmlspecialchars($value ?? "N/A");
                echo '
                  <div class="row">
                    <div class="label">'.$label.':</div>

                    <div class="value">
                      <span id="'.$key.'_text">'.$safe.'</span>
                      <input class="input hidden" id="'.$key.'_input" name="'.$key.'" value="'.$safe.'">
                    </div>

                    <button class="edit-btn" type="button" onclick="enableEdit(\''.$key.'\')">
                      <span>✎</span> EDIT
                    </button>
                  </div>
                ';
              }
              row("first_name", "First Name", $u["first_name"]);
              row("middle_name", "Middle Name", $u["middle_name"]);
              row("last_name", "Last Name", $u["last_name"]);              
              row("suffix", "Suffix", $u["suffix"] ?: "N/A");
              row("student_id", "ID Number", $u["student_id"] ?: "N/A");
              row("course", "Course/Program", $u["course"] ?: "N/A");
              row("major", "Major", $u["major"] ?: "N/A");
              row("year_graduated", "Year Graduated", $u["year_graduated"] ?: "N/A");
              row("gender", "Gender", $u["gender"] ?: "N/A");
              row("email", "Email", $u["email"] ?: "N/A");
              row("contact_number", "Contact Number", $u["contact_number"] ?: "N/A");
              row("address", "Complete Address", $u["address"] ?: "N/A");
            ?>

            <div class="status-row">
              <div>Account Status:</div>
              <div class="badge"><?= htmlspecialchars($badgeText) ?></div>
            </div>

            <div class="bottom-actions">
              <button class="btn save" type="submit">SAVE</button>
              <button class="btn cancel" type="button" onclick="window.location.href='dashboard.php'">CANCEL</button>
            </div>

          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<script src="../assets/js/profile.js"></script>
</body>
</html>
