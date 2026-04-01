<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);
csrf_verify();

$user_id = $_SESSION["user_id"];
$isAjax = !empty($_POST["ajax"]);
$excludeEmail = !empty($_POST["exclude_email"]);

// Collect fields
$fields = [
    "first_name"     => trim($_POST["first_name"] ?? ""),
    "middle_name"    => trim($_POST["middle_name"] ?? ""),
    "last_name"      => trim($_POST["last_name"] ?? ""),
    "suffix"         => trim($_POST["suffix"] ?? ""),
    "student_id"     => trim($_POST["student_id"] ?? ""),
    "course"         => trim($_POST["course"] ?? ""),
    "major"          => trim($_POST["major"] ?? ""),
    "year_graduated" => trim($_POST["year_graduated"] ?? ""),
    "gender"         => trim($_POST["gender"] ?? ""),
    "contact_number" => trim($_POST["contact_number"] ?? ""),
    "address"        => trim($_POST["address"] ?? ""),
];

// Include email only when not excluded (profile.php includes it, request_review.php excludes it)
if (!$excludeEmail && isset($_POST["email"])) {
    $email = trim($_POST["email"]);
    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($isAjax) {
            http_response_code(400);
            header("Content-Type: application/json");
            echo json_encode(["success" => false, "message" => "Invalid email address."]);
            exit;
        }
        swal_flash("error", "Error", "Invalid email address.");
        header("Location: profile.php");
        exit;
    }
    $fields["email"] = $email;
}

// Convert empty strings and "N/A" to NULL
foreach ($fields as $key => &$value) {
    if ($value === "" || strtoupper($value) === "N/A") {
        $value = null;
    }
}
unset($value);

// Build dynamic UPDATE
$setParts = [];
$types = "";
$values = [];
foreach ($fields as $col => $val) {
    $setParts[] = "$col = ?";
    $types .= "s";
    $values[] = $val;
}
$types .= "i";
$values[] = $user_id;

$sql = "UPDATE users SET " . implode(", ", $setParts) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$values);
$stmt->execute();
$stmt->close();

audit_log($conn, "UPDATE", "users", $user_id, "Profile updated");

if ($isAjax) {
    header("Content-Type: application/json");
    echo json_encode(["success" => true, "message" => "Profile updated successfully."]);
    exit;
}

swal_flash("success", "Updated", "Profile updated successfully.");
header("Location: profile.php");
exit;
