<?php
require_once __DIR__ . "/../includes/helpers.php";
require_once __DIR__ . "/../includes/compliance.php";
require_role(ROLE_REGISTRAR);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: non_compliant.php");
    exit();
}
csrf_verify();

$registrarId = (int)$_SESSION["user_id"];
$action = trim($_POST["action"] ?? "");

$defaultMessage = "You have pending requirements for your document request. Please complete your submission to avoid delays.";

/**
 * Send notification to a single request's user.
 */
function send_compliance_notification(
    mysqli $conn,
    int $requestId,
    int $senderId,
    string $message,
    string $type = 'MANUAL'
): bool {
    $st = $conn->prepare("
        SELECT r.user_id, r.reference_no, u.email
        FROM requests r
        JOIN users u ON u.id = r.user_id
        WHERE r.id = ? LIMIT 1
    ");
    $st->bind_param("i", $requestId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) return false;

    $userId = (int)$row['user_id'];
    $refNo  = $row['reference_no'];
    $email  = $row['email'];

    // In-app notification
    add_log($conn, $requestId, "Compliance Notice: " . $message);

    // Email notification
    $emailBody = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #0a3a63; margin-bottom: 16px;'>E-Doc — Action Required</h2>
            <p style='color: #333; font-size: 15px; line-height: 1.6;'>" . htmlspecialchars($message) . "</p>
            <p style='color: #666; font-size: 13px; margin-top: 20px;'>Reference: <strong>" . htmlspecialchars($refNo) . "</strong></p>
            <p style='color: #666; font-size: 13px;'>Please log in to the E-Doc system to complete your requirements.</p>
            <hr style='border: none; border-top: 1px solid #dfe3ea; margin: 20px 0;'>
            <p style='color: #999; font-size: 12px;'>This is an automated message from the E-Doc System.</p>
        </div>
    ";
    $emailSent = send_email($conn, $email, "E-Doc — Action Required: " . $refNo, $emailBody);

    // Log to compliance_notifications
    $channel = $emailSent ? 'BOTH' : 'IN_APP';
    $senderParam = ($type === 'AUTO') ? null : $senderId;

    $ins = $conn->prepare("
        INSERT INTO compliance_notifications (user_id, request_id, notification_type, channel, message, sent_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $ins->bind_param("iisssi", $userId, $requestId, $type, $channel, $message, $senderParam);
    $ins->execute();

    audit_log($conn, "INSERT", "compliance_notifications", $requestId, "Compliance notification sent ({$type}) to user #{$userId}");

    return true;
}

// --- Handle actions ---

if ($action === "send_single") {
    $requestId = (int)($_POST["request_id"] ?? 0);
    $message   = trim($_POST["message"] ?? "") ?: $defaultMessage;

    if ($requestId > 0) {
        send_compliance_notification($conn, $requestId, $registrarId, $message, 'MANUAL');
        swal_flash("success", "Sent", "Notification sent successfully.");
    } else {
        swal_flash("error", "Error", "Invalid request.");
    }
    header("Location: non_compliant.php");
    exit();
}

if ($action === "send_selected") {
    $ids = $_POST["request_ids"] ?? [];
    if (is_string($ids)) $ids = json_decode($ids, true) ?: [];
    $message = trim($_POST["message"] ?? "") ?: $defaultMessage;
    $count = 0;

    foreach ($ids as $rid) {
        $rid = (int)$rid;
        if ($rid > 0) {
            send_compliance_notification($conn, $rid, $registrarId, $message, 'MANUAL');
            $count++;
        }
    }

    swal_flash("success", "Sent", "Notifications sent to {$count} request(s).");
    header("Location: non_compliant.php");
    exit();
}

if ($action === "notify_all") {
    $message = trim($_POST["message"] ?? "") ?: $defaultMessage;
    $allNonCompliant = get_non_compliant_users($conn);
    $count = 0;

    foreach ($allNonCompliant as $entry) {
        send_compliance_notification($conn, $entry['request_id'], $registrarId, $message, 'MANUAL');
        $count++;
    }

    swal_flash("success", "Sent", "Notifications sent to all {$count} non-compliant request(s).");
    header("Location: non_compliant.php");
    exit();
}

swal_flash("error", "Error", "Invalid action.");
header("Location: non_compliant.php");
exit();
