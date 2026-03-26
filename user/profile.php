<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);

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
  swal_flash("error", "Error", "User not found.");
  header("Location: dashboard.php");
  exit();
}

// Image paths (empty string if missing)
$frontImg = !empty($u["id_front_path"]) ? "../" . $u["id_front_path"] : "";
$faceImg  = !empty($u["face_path"])     ? "../" . $u["face_path"]     : "";
$backImg  = !empty($u["id_back_path"])  ? "../" . $u["id_back_path"]  : "";

$status    = strtoupper($u["verification_status"] ?? "PENDING");
$badgeText = ($status === "VERIFIED") ? "VERIFIED" : (($status === "RESUBMIT") ? "RESUBMIT" : "PENDING");

// Flash messages from redirects (legacy query-string based — convert to swal_flash)
if (isset($_GET["msg"])) {
  if ($_GET["msg"] === "uploaded") swal_flash("success", "Uploaded", "File uploaded successfully.");
  if ($_GET["msg"] === "deleted")  swal_flash("success", "Deleted", "File deleted successfully.");
}
if (isset($_GET["saved"])) {
  swal_flash("success", "Saved", "Profile saved successfully.");
}
if (isset($_GET["error"])) {
  $errMsg = match ($_GET["error"]) {
    "upload"   => "File upload failed. Please try again.",
    "size"     => "File is too large. Maximum size is 15 MB.",
    "filetype" => "Invalid file type. Only JPG, PNG, and WEBP are accepted.",
    "move"     => "Server error moving file. Please try again.",
    default    => "An error occurred.",
  };
  swal_flash("error", "Error", $errMsg);
}

$initialStep = max(0, min(2, (int)($_GET["step"] ?? 0)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Profile</title>
  <link rel="stylesheet" href="../assets/css/profile.css">
  <?php include __DIR__ . "/../includes/swal_header.php"; ?>
</head>
<body>


<div class="wrapper">
  <div class="card">
    <button class="close-x" onclick="window.location.href='dashboard.php'">&times;</button>

    <div class="grid">

      <!-- LEFT IMAGE VIEWER -->
      <div class="left-box">
        <div class="left-title" id="leftTitle">FRONT ID</div>

        <div class="preview">
          <img id="previewImg"
               src=""
               alt="Preview"
               data-front="<?= h($frontImg) ?>"
               data-back="<?= h($backImg) ?>"
               data-face="<?= h($faceImg) ?>">
        </div>

        <!-- Upload form (file input hidden, triggered by button) -->
        <form id="uploadForm" method="POST" action="profile_id_action.php" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="type" id="uploadType" value="">
          <input type="file" name="id_file" id="fileInput" accept="image/jpeg,image/png,image/webp"
                 style="display:none;" onchange="this.form.submit()">
        </form>

        <!-- Delete form -->
        <form id="deleteForm" method="POST" action="profile_id_action.php" style="display:none">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="type" id="deleteType" value="">
          <input type="hidden" name="file_id" id="deleteFileId" value="">
        </form>

        <div class="left-actions">
          <button class="nav-arrow" type="button" onclick="prevStep()">&lt;</button>
          <button class="btn delete" type="button" onclick="doDelete()">DELETE</button>
          <button class="btn upload" type="button" onclick="doUpload()">UPLOAD</button>
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
          <?= csrf_field() ?>
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
                      <span>&#9998;</span> EDIT
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
              <div class="badge badge-<?= strtolower($status) ?>"><?= h($badgeText) ?></div>
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

<script>
var step = <?= $initialStep ?>;

var steps = [
  { key: "front", title: "FRONT ID" },
  { key: "back",  title: "BACK ID" },
  { key: "face",  title: "FACE VERIFICATION" }
];

function getImgSrc() {
  var img = document.getElementById("previewImg");
  return img.dataset[steps[step].key] || "";
}

function renderStep() {
  var title = document.getElementById("leftTitle");
  var img   = document.getElementById("previewImg");
  var src   = getImgSrc();

  title.textContent = steps[step].title;

  if (src) {
    img.src = src;
    img.style.display = "";
  } else {
    img.src = "";
    img.style.display = "none";
  }
}

function nextStep() {
  if (step < steps.length - 1) step++;
  renderStep();
}

function prevStep() {
  if (step > 0) step--;
  renderStep();
}

function doUpload() {
  var type = steps[step].key;
  document.getElementById("uploadType").value = type;

  var fi = document.getElementById("fileInput");
  fi.accept = "image/jpeg,image/png,image/webp";
  fi.value = "";
  fi.click();
}

function doDelete() {
  var type = steps[step].key;
  var src  = getImgSrc();

  if (!src) {
    swalWarning("No Image", "No image to delete.");
    return;
  }

  swalConfirmDanger(
    "Delete " + steps[step].title + "?",
    "Are you sure you want to delete this image? This cannot be undone.",
    "Yes, delete",
    function() {
      document.getElementById("deleteType").value = type;
      document.getElementById("deleteForm").submit();
    }
  );
}

function enableEdit(field) {
  var textEl  = document.getElementById(field + "_text");
  var inputEl = document.getElementById(field + "_input");
  if (!textEl || !inputEl) return;

  textEl.classList.add("hidden");
  inputEl.classList.remove("hidden");
  inputEl.focus();
}

renderStep();

</script>
</body>
</html>
