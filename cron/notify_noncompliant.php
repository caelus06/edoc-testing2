<?php
/**
 * Cron job: Send automated compliance notifications.
 *
 * CLI ONLY — cannot be accessed via browser.
 *
 * Setup (Windows Task Scheduler):
 *   Action: Start a program
 *   Program: C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\edoc\cron\notify_noncompliant.php
 *   Trigger: Daily at desired time (e.g., 8:00 AM)
 *
 * Manual run:
 *   php cron/notify_noncompliant.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI access only.";
    exit(1);
}

// Bootstrap: helpers.php handles session conditionally, safe in CLI
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/compliance.php';

// Set session vars for audit_log (cron has no real user)
$_SESSION['user_id'] = 0;  // sentinel: 0 = system/cron
$_SESSION['role'] = 'SYSTEM';

$cooldownHours = (int)(get_setting($conn, 'compliance_cooldown_hours') ?: 48);

$allNonCompliant = get_non_compliant_users($conn);

$sent = 0;
$skipped = 0;

$defaultMessage = "Reminder: You have pending requirements for your document request. Please log in to the E-Doc system and complete your submission.";

foreach ($allNonCompliant as $entry) {
    $requestId = $entry['request_id'];

    // Check cooldown
    $st = $conn->prepare("
        SELECT created_at FROM compliance_notifications
        WHERE request_id = ? AND notification_type = 'AUTO'
        ORDER BY created_at DESC LIMIT 1
    ");
    $st->bind_param("i", $requestId);
    $st->execute();
    $last = $st->get_result()->fetch_assoc();

    if ($last) {
        $lastTime = strtotime($last['created_at']);
        $hoursSince = (time() - $lastTime) / 3600;
        if ($hoursSince < $cooldownHours) {
            $skipped++;
            continue;
        }
    }

    // Build personalized message
    $reasons = format_reasons($entry['reasons']);
    $message = $defaultMessage . " Reason: " . implode(', ', $reasons) . ".";

    $userId = $entry['user_id'];
    $email  = $entry['email'];
    $refNo  = $entry['reference_no'];

    // In-app notification
    add_log($conn, $requestId, "Auto-Compliance Notice: " . $message);

    // Email
    $emailBody = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #0a3a63; margin-bottom: 16px;'>E-Doc — Action Required</h2>
            <p style='color: #333; font-size: 15px; line-height: 1.6;'>" . htmlspecialchars($message) . "</p>
            <p style='color: #666; font-size: 13px; margin-top: 20px;'>Reference: <strong>" . htmlspecialchars($refNo) . "</strong></p>
            <p style='color: #666; font-size: 13px;'>Please log in to the E-Doc system to complete your requirements.</p>
            <hr style='border: none; border-top: 1px solid #dfe3ea; margin: 20px 0;'>
            <p style='color: #999; font-size: 12px;'>This is an automated reminder from the E-Doc System.</p>
        </div>
    ";
    $emailSent = send_email($conn, $email, "E-Doc — Action Required: " . $refNo, $emailBody);

    // Log to compliance_notifications
    $channel = $emailSent ? 'BOTH' : 'IN_APP';
    $ins = $conn->prepare("
        INSERT INTO compliance_notifications (user_id, request_id, notification_type, channel, message, sent_by)
        VALUES (?, ?, 'AUTO', ?, ?, NULL)
    ");
    $ins->bind_param("iiss", $userId, $requestId, $channel, $message);
    $ins->execute();

    $sent++;
}

echo date("Y-m-d H:i:s") . " — Cron complete. Sent: {$sent}, Skipped (cooldown): {$skipped}, Total non-compliant: " . count($allNonCompliant) . "\n";
