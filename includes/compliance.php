<?php
/**
 * Non-compliance detection queries.
 *
 * Three rules:
 * 1. Resubmit flagged — request_files with review_status='RESUBMIT' on active requests
 * 2. Missing uploads — fewer uploaded files than required by requirements_master
 * 3. Abandoned — PENDING requests older than threshold with incomplete uploads
 *
 * Main function:
 *   get_non_compliant_users(mysqli $conn, array $filters, bool $countOnly): array
 */

// mailer.php is loaded via helpers.php — get_setting() is available.

/**
 * Active request statuses (excludes terminal states).
 */
function active_statuses(): array {
    return ['PENDING', 'RETURNED', 'VERIFIED', 'APPROVED', 'PROCESSING', 'READY FOR PICKUP'];
}

/**
 * Build a prepared-statement-safe IN clause placeholder string.
 */
function build_in_clause(array $values): array {
    return [
        'placeholders' => str_repeat('?,', count($values) - 1) . '?',
        'types'        => str_repeat('s', count($values)),
        'values'       => $values,
    ];
}

/**
 * Get all non-compliant user+request pairs.
 */
function get_non_compliant_users(mysqli $conn, array $filters = [], bool $countOnly = false): array {
    $threshold = (int)(get_setting($conn, 'compliance_threshold_days') ?: 7);
    $in = build_in_clause(active_statuses());

    // --- Rule 1: Resubmit flagged ---
    $sql1 = "
        SELECT
            u.id AS user_id, u.first_name, u.last_name, u.email, u.student_id, u.course,
            r.id AS request_id, r.reference_no, r.document_type, r.title_type, r.status AS request_status,
            'RESUBMIT_FLAGGED' AS rule_type,
            GROUP_CONCAT(DISTINCT COALESCE(rf.requirement_name, rf.requirement_key) SEPARATOR ', ') AS flagged_docs
        FROM requests r
        JOIN users u ON u.id = r.user_id
        JOIN request_files rf ON rf.request_id = r.id AND UPPER(rf.review_status) = 'RESUBMIT'
        WHERE UPPER(r.status) IN ({$in['placeholders']})
        GROUP BY r.id
    ";
    $st1 = $conn->prepare($sql1);
    $st1->bind_param($in['types'], ...$in['values']);
    $st1->execute();
    $res1 = $st1->get_result();

    // --- Rule 2: Missing uploads ---
    $sql2 = "
        SELECT
            u.id AS user_id, u.first_name, u.last_name, u.email, u.student_id, u.course,
            r.id AS request_id, r.reference_no, r.document_type, r.title_type, r.status AS request_status,
            'MISSING_UPLOADS' AS rule_type,
            NULL AS flagged_docs
        FROM requests r
        JOIN users u ON u.id = r.user_id
        WHERE UPPER(r.status) IN ({$in['placeholders']})
          AND (
            SELECT COUNT(DISTINCT rm.requirement_key)
            FROM requirements_master rm
            WHERE UPPER(TRIM(rm.document_type)) = UPPER(TRIM(r.document_type))
              AND (TRIM(rm.title_type) = TRIM(r.title_type) OR rm.title_type IS NULL OR rm.title_type = '')
          ) > (
            SELECT COUNT(*)
            FROM request_files rf2
            WHERE rf2.request_id = r.id
              AND rf2.requirement_key != 'scanned_document'
          )
    ";
    $st2 = $conn->prepare($sql2);
    $st2->bind_param($in['types'], ...$in['values']);
    $st2->execute();
    $res2 = $st2->get_result();

    // --- Rule 3: Abandoned (PENDING > threshold days, incomplete uploads) ---
    $sql3 = "
        SELECT
            u.id AS user_id, u.first_name, u.last_name, u.email, u.student_id, u.course,
            r.id AS request_id, r.reference_no, r.document_type, r.title_type, r.status AS request_status,
            'ABANDONED' AS rule_type,
            NULL AS flagged_docs
        FROM requests r
        JOIN users u ON u.id = r.user_id
        WHERE UPPER(r.status) = 'PENDING'
          AND r.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
          AND (
            SELECT COUNT(DISTINCT rm.requirement_key)
            FROM requirements_master rm
            WHERE UPPER(TRIM(rm.document_type)) = UPPER(TRIM(r.document_type))
              AND (TRIM(rm.title_type) = TRIM(r.title_type) OR rm.title_type IS NULL OR rm.title_type = '')
          ) > (
            SELECT COUNT(*)
            FROM request_files rf2
            WHERE rf2.request_id = r.id
              AND rf2.requirement_key != 'scanned_document'
          )
    ";
    $st3 = $conn->prepare($sql3);
    $st3->bind_param("i", $threshold);
    $st3->execute();
    $res3 = $st3->get_result();

    // Merge all three result sets
    $results = [];
    $requestMap = [];

    foreach ([$res1, $res2, $res3] as $res) {
        if (!$res) continue;
        while ($row = $res->fetch_assoc()) {
            $rid = (int)$row['request_id'];
            $rule = $row['rule_type'];

            if (isset($requestMap[$rid])) {
                $idx = $requestMap[$rid];
                if (!in_array($rule, $results[$idx]['reasons'])) {
                    $results[$idx]['reasons'][] = $rule;
                }
                if ($row['flagged_docs'] && !in_array($row['flagged_docs'], $results[$idx]['pending_docs'])) {
                    $results[$idx]['pending_docs'][] = $row['flagged_docs'];
                }
            } else {
                $requestMap[$rid] = count($results);
                $results[] = [
                    'user_id'        => (int)$row['user_id'],
                    'first_name'     => $row['first_name'],
                    'last_name'      => $row['last_name'],
                    'email'          => $row['email'],
                    'student_id'     => $row['student_id'],
                    'course'         => $row['course'],
                    'request_id'     => $rid,
                    'reference_no'   => $row['reference_no'],
                    'document_type'  => $row['document_type'],
                    'title_type'     => $row['title_type'],
                    'request_status' => $row['request_status'],
                    'reasons'        => [$rule],
                    'pending_docs'   => $row['flagged_docs'] ? [$row['flagged_docs']] : [],
                    'last_notified'  => null,
                ];
            }
        }
    }

    // If count-only mode, skip expensive per-row enrichment
    if ($countOnly) {
        return $results;
    }

    // Fetch last notification time for each request
    foreach ($results as &$entry) {
        $st = $conn->prepare("
            SELECT created_at FROM compliance_notifications
            WHERE request_id = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $st->bind_param("i", $entry['request_id']);
        $st->execute();
        $nr = $st->get_result()->fetch_assoc();
        $entry['last_notified'] = $nr['created_at'] ?? null;
    }
    unset($entry);

    // Fetch missing doc names for rules 2 & 3
    foreach ($results as &$entry) {
        if (in_array('MISSING_UPLOADS', $entry['reasons']) || in_array('ABANDONED', $entry['reasons'])) {
            $reqSt = $conn->prepare("
                SELECT requirement_key, req_name
                FROM requirements_master
                WHERE UPPER(TRIM(document_type)) = UPPER(TRIM(?))
                  AND (TRIM(title_type) = TRIM(?) OR title_type IS NULL OR title_type = '')
            ");
            $reqSt->bind_param("ss", $entry['document_type'], $entry['title_type']);
            $reqSt->execute();
            $allReqs = $reqSt->get_result()->fetch_all(MYSQLI_ASSOC);

            $upSt = $conn->prepare("
                SELECT requirement_key FROM request_files
                WHERE request_id = ? AND requirement_key != 'scanned_document'
            ");
            $upSt->bind_param("i", $entry['request_id']);
            $upSt->execute();
            $uploadedRows = $upSt->get_result()->fetch_all(MYSQLI_ASSOC);
            $uploadedKeys = array_column($uploadedRows, 'requirement_key');

            $missing = [];
            foreach ($allReqs as $req) {
                if (!in_array($req['requirement_key'], $uploadedKeys)) {
                    $missing[] = $req['req_name'];
                }
            }
            if (!empty($missing)) {
                $entry['pending_docs'] = array_merge($entry['pending_docs'], $missing);
            }
        }
        $entry['pending_docs'] = array_unique($entry['pending_docs']);
    }
    unset($entry);

    // Apply filters
    $search   = trim($filters['search'] ?? '');
    $reason   = trim($filters['reason'] ?? '');
    $docType  = trim($filters['doc_type'] ?? '');

    if ($search !== '' || $reason !== '' || $docType !== '') {
        $results = array_filter($results, function($e) use ($search, $reason, $docType) {
            if ($search !== '') {
                $haystack = strtolower($e['first_name'] . ' ' . $e['last_name'] . ' ' . $e['student_id'] . ' ' . $e['reference_no']);
                if (strpos($haystack, strtolower($search)) === false) return false;
            }
            if ($reason !== '' && !in_array($reason, $e['reasons'])) return false;
            if ($docType !== '' && strtoupper($e['document_type']) !== strtoupper($docType)) return false;
            return true;
        });
        $results = array_values($results);
    }

    return $results;
}

/**
 * Get count of all non-compliant request entries (for dashboard card).
 */
function count_non_compliant(mysqli $conn): int {
    return count(get_non_compliant_users($conn, [], true));
}

/**
 * Format reason codes into human-readable labels.
 */
function format_reasons(array $reasons): array {
    $map = [
        'RESUBMIT_FLAGGED' => 'Resubmit Required',
        'MISSING_UPLOADS'  => 'Missing Uploads',
        'ABANDONED'        => 'Abandoned Request',
    ];
    return array_map(fn($r) => $map[$r] ?? $r, $reasons);
}
