<?php
/**
 * Email helper for the E-Doc system.
 * Uses PHPMailer with Gmail SMTP.
 */

// PHPMailer is loaded via Composer autoloader (already included in helpers.php)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Fetch a setting value from system_settings.
 */
function get_setting(mysqli $conn, string $key): string {
    $st = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $st->bind_param("s", $key);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return (string)($row["setting_value"] ?? "");
}

/**
 * Update a setting value.
 */
function set_setting(mysqli $conn, string $key, string $value, ?int $updatedBy = null): bool {
    $st = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)
    ");
    $st->bind_param("ssi", $key, $value, $updatedBy);
    return $st->execute();
}

/**
 * Send an email via Gmail SMTP.
 * Returns true on success, false on failure.
 */
function send_email(mysqli $conn, string $to, string $subject, string $body): bool {
    $smtpEmail = get_setting($conn, 'smtp_email');
    $smtpPass  = get_setting($conn, 'smtp_app_password');
    $senderName = get_setting($conn, 'smtp_sender_name') ?: 'E-Doc System';

    if ($smtpEmail === '' || $smtpPass === '') {
        return false; // SMTP not configured
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpEmail;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($smtpEmail, $senderName);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send an in-app notification + email to the user associated with a request.
 * This wraps add_log() and also sends an email if SMTP is configured.
 */
function notify_user(mysqli $conn, int $request_id, string $message, ?string $recipientEmail = null): void {
    // 1. In-app notification (existing system)
    add_log($conn, $request_id, $message);

    // 2. Email notification
    if ($recipientEmail === null) {
        $st = $conn->prepare("
            SELECT u.email
            FROM requests r
            JOIN users u ON u.id = r.user_id
            WHERE r.id = ? LIMIT 1
        ");
        $st->bind_param("i", $request_id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $recipientEmail = $row["email"] ?? null;
    }

    if ($recipientEmail) {
        $refSt = $conn->prepare("SELECT reference_no FROM requests WHERE id = ? LIMIT 1");
        $refSt->bind_param("i", $request_id);
        $refSt->execute();
        $refRow = $refSt->get_result()->fetch_assoc();
        $refNo = $refRow["reference_no"] ?? "N/A";

        $subject = "E-Doc Notification — " . $refNo;
        $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #0a3a63; margin-bottom: 16px;'>E-Doc Document Requesting System</h2>
                <p style='color: #333; font-size: 15px; line-height: 1.6;'>" . htmlspecialchars($message) . "</p>
                <p style='color: #666; font-size: 13px; margin-top: 20px;'>Reference: <strong>" . htmlspecialchars($refNo) . "</strong></p>
                <hr style='border: none; border-top: 1px solid #dfe3ea; margin: 20px 0;'>
                <p style='color: #999; font-size: 12px;'>This is an automated message from the E-Doc System. Please do not reply to this email.</p>
            </div>
        ";

        send_email($conn, $recipientEmail, $subject, $body);
    }
}
