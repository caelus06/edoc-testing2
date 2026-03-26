<?php
// PHPWord autoloader (lib/ — no Composer needed)
if (file_exists(__DIR__ . '/../lib/autoload.php')) {
    require_once __DIR__ . '/../lib/autoload.php';
}

/**
 * Centralized helpers for the E-Doc Document Management System.
 *
 * Include this file at the top of every protected page INSTEAD of
 * calling session_start() and require_once database.php manually.
 *
 * Usage:
 *   require_once __DIR__ . "/../includes/helpers.php";
 *   require_role("USER");          // or "REGISTRAR", "MIS"
 */

/* ------------------------------------------------------------------ */
/*  STATUS CONSTANTS                                                   */
/* ------------------------------------------------------------------ */

// Request statuses
const STATUS_PENDING          = "PENDING";
const STATUS_RETURNED         = "RETURNED";
const STATUS_VERIFIED         = "VERIFIED";
const STATUS_APPROVED         = "APPROVED";
const STATUS_PROCESSING       = "PROCESSING";
const STATUS_READY_FOR_PICKUP = "READY FOR PICKUP";
const STATUS_RELEASED         = "RELEASED";
const STATUS_CANCELLED        = "CANCELLED";
const STATUS_COMPLETED        = "COMPLETED";

// Verification statuses
const VSTATUS_PENDING      = "PENDING";
const VSTATUS_VERIFIED     = "VERIFIED";
const VSTATUS_RESUBMIT     = "RESUBMIT";
const VSTATUS_UNAFFILIATED = "UNAFFILIATED";

// Roles
const ROLE_USER      = "USER";
const ROLE_REGISTRAR = "REGISTRAR";
const ROLE_MIS       = "MIS";

// Upload limits
const MAX_FILE_SIZE_MB     = 15;
const MAX_IMAGE_SIZE_MB    = 5;
const MAX_FILE_SIZE_BYTES  = 15 * 1024 * 1024;
const MAX_IMAGE_SIZE_BYTES = 5 * 1024 * 1024;

// Pagination
const PER_PAGE = 10;

// Notification badge cap
const NOTIF_BADGE_CAP = 99;

/* ------------------------------------------------------------------ */
/*  SECURE SESSION                                                     */
/* ------------------------------------------------------------------ */

if (session_status() === PHP_SESSION_NONE) {
    ini_set("session.use_strict_mode", "1");
    ini_set("session.cookie_httponly", "1");
    ini_set("session.cookie_samesite", "Strict");

    // Enable secure cookies only when served over HTTPS
    if ((!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (int)($_SERVER["SERVER_PORT"] ?? 0) === 443) {
        ini_set("session.cookie_secure", "1");
    }

    session_start();
}

/* ------------------------------------------------------------------ */
/*  DATABASE CONNECTION                                                */
/* ------------------------------------------------------------------ */

require_once __DIR__ . "/../config/database.php";

/* ------------------------------------------------------------------ */
/*  OUTPUT ESCAPING                                                    */
/* ------------------------------------------------------------------ */

/**
 * Escape a value for safe HTML output.
 */
function h(?string $s): string {
    return htmlspecialchars((string)($s ?? ""), ENT_QUOTES, "UTF-8");
}

/* ------------------------------------------------------------------ */
/*  ROLE-BASED ACCESS CONTROL                                          */
/* ------------------------------------------------------------------ */

/**
 * Require the current session to have the given role.
 * Redirects to the login page if the check fails.
 */
function require_role(string $role): void {
    if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== $role) {
        header("Location: ../auth/auth.php");
        exit();
    }
}

/* ------------------------------------------------------------------ */
/*  CSRF PROTECTION                                                    */
/* ------------------------------------------------------------------ */

/**
 * Return (and lazily generate) the current CSRF token.
 */
function csrf_token(): string {
    if (empty($_SESSION["_csrf_token"])) {
        $_SESSION["_csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["_csrf_token"];
}

/**
 * Return an HTML hidden input containing the CSRF token.
 * Drop this inside every <form> that uses POST.
 */
function csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . h(csrf_token()) . '">';
}

/**
 * Validate the CSRF token sent with a POST request.
 * Call this at the top of every POST handler.
 * Returns true on success; sends a 403 and exits on failure.
 */
function csrf_verify(): bool {
    $token = $_POST["_csrf_token"] ?? "";
    if ($token === "" || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        echo "Invalid or missing CSRF token.";
        exit();
    }
    return true;
}

/* ------------------------------------------------------------------ */
/*  SWEETALERT FLASH MESSAGES                                          */
/* ------------------------------------------------------------------ */

/**
 * Queue a SweetAlert2 message to display after the next page load.
 * Call this before a header("Location: ...") redirect.
 *
 * @param string $icon  "success"|"error"|"warning"|"info"
 * @param string $title Alert title
 * @param string $text  Optional body text
 */
function swal_flash(string $icon, string $title, string $text = ""): void {
    $_SESSION["swal_flash"] = [
        "icon"  => $icon,
        "title" => $title,
        "text"  => $text,
    ];
}

/* ------------------------------------------------------------------ */
/*  REQUEST LOGGING                                                    */
/* ------------------------------------------------------------------ */

/**
 * Insert an audit / tracking log entry for a request.
 */
function add_log(mysqli $conn, int $request_id, string $msg): void {
    $st = $conn->prepare("INSERT INTO request_logs (request_id, message) VALUES (?, ?)");
    $st->bind_param("is", $request_id, $msg);
    $st->execute();
}

/**
 * Insert a universal audit log entry.
 * Captures the acting user, action type, affected table/record, and IP.
 */
function audit_log(
    mysqli $conn,
    string $action,
    string $table_name,
    ?int   $record_id = null,
    string $details = ""
): void {
    $user_id = $_SESSION["user_id"] ?? null;
    $ip = $_SERVER["REMOTE_ADDR"] ?? null;

    $st = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $st->bind_param("isisss", $user_id, $action, $table_name, $record_id, $details, $ip);
    $st->execute();
}
