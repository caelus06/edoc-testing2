<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);
csrf_verify();

$userId    = $_SESSION["user_id"];
$requestId = (int)($_POST["request_id"] ?? 0);

if ($requestId <= 0) {
    swal_flash("error", "Error", "Invalid request.");
    header("Location: dashboard.php");
    exit;
}

// Verify request belongs to user and is PENDING
$stmt = $conn->prepare("SELECT id, reference_no, status FROM requests WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $requestId, $userId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    swal_flash("error", "Error", "Request not found.");
    header("Location: dashboard.php");
    exit;
}

if ($request["status"] !== STATUS_PENDING) {
    swal_flash("error", "Error", "Only pending requests can be cancelled.");
    header("Location: dashboard.php");
    exit;
}

// Cancel the request
$update = $conn->prepare("UPDATE requests SET status = ? WHERE id = ?");
$cancelled = STATUS_CANCELLED;
$update->bind_param("si", $cancelled, $requestId);
$update->execute();
$update->close();

// Log it
add_log($conn, $requestId, "Request cancelled by user");
audit_log($conn, "CANCEL", "requests", $requestId, "Cancelled request " . $request["reference_no"]);

swal_flash("success", "Cancelled", "Your request has been cancelled.");

// Redirect back to referer or dashboard
$referer = $_SERVER["HTTP_REFERER"] ?? "dashboard.php";
$allowed = ["dashboard.php", "track.php"];
$redirectTo = "dashboard.php";
foreach ($allowed as $page) {
    if (strpos($referer, $page) !== false) {
        $redirectTo = $referer;
        break;
    }
}
header("Location: " . $redirectTo);
exit;
