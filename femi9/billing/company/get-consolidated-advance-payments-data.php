<?php
/**
 * Get Advance Payments Data - Consolidated/Grouped by Payer
 * Femi9 Billing Application
 *
 * @version 2.4.0
 * @fix All user types now fetch target amount from category table only.
 *      Requires dist_cat_id in distributor_referral and sd_cat_id in
 *      super_distributor_referral (run ALTER TABLE before deploying).
 *
 * Target amount sources (all via category):
 *   super_stockiest   : ssr.ss_cat_id   → super_stockiest_category.target_amount
 *   stockiest         : sr.st_cat_id    → stockist_category.target_amount
 *   distributor       : dr.dist_cat_id  → distributor_category.amount
 *   super_distributor : sdr.sd_cat_id   → super_distributor_category.amount
 *   c_and_f           : always 0
 */

declare(strict_types=1);

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Cache-Control: no-store, no-cache, must-revalidate');

session_start();

if (!file_exists(__DIR__ . '/checksession.php') || !file_exists(__DIR__ . '/config.php')) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Server configuration error']);
    exit;
}
require_once __DIR__ . '/checksession.php';
require_once __DIR__ . '/config.php';

$response = [
    'data'    => [],
    'stats'   => [
        'total_payers'    => 0,
        'total_payments'  => 0,
        'total_amount'    => '0.00',
        'total_balance'   => '0.00',
        'adjusted_amount' => '0.00',
    ],
    'success' => false,
];

try {
    // --- Database check ---
    if (!isset($db_conn) || !($db_conn instanceof mysqli)) {
        throw new RuntimeException('Database connection failed');
    }

    // --- Session check ---
    $logged_user_id   = $_SESSION['LOGIN_USER_ID']   ?? '';
    $logged_user_type = $_SESSION['LOGIN_USER_TYPE'] ?? '';
    if (empty($logged_user_id) || empty($logged_user_type)) {
        throw new RuntimeException('Please login to continue');
    }

    $db_conn->set_charset('utf8mb4');

    // --- Input ---
    $from_date            = $_POST['from_date']            ?? date('Y-m-01');
    $to_date              = $_POST['to_date']              ?? date('Y-m-d');
    $payer_type           = $_POST['payer_type']           ?? '';
    $payer_district_id    = (int)($_POST['payer_district_id']    ?? 0);
    $payer_id             = $_POST['payer_id']             ?? '';
    $receiver_type        = $_POST['receiver_type']        ?? 'company';
    $receiver_district_id = (int)($_POST['receiver_district_id'] ?? 0);
    $receiver_id          = $_POST['receiver_id']          ?? '';
    $status               = $_POST['status']               ?? '';

    // --- Validate dates ---
    $dateRegex = '/^\d{4}-\d{2}-\d{2}$/';
    $from_date = preg_match($dateRegex, $from_date) ? $from_date : date('Y-m-01');
    $to_date   = preg_match($dateRegex, $to_date)   ? $to_date   : date('Y-m-d');

    // --- Whitelist enum fields ---
    $allowed_user_types = ['c_and_f', 'super_stockiest', 'stockiest', 'distributor', 'super_distributor', 'company'];
    $allowed_statuses   = ['pending', 'partial', 'adjusted', 'cancelled', 'active', 'partially_adjusted', 'fully_adjusted'];

    if (!in_array($payer_type,    $allowed_user_types, true)) { $payer_type    = ''; }
    if (!in_array($receiver_type, $allowed_user_types, true)) { $receiver_type = 'company'; }
    if (!in_array($status,        $allowed_statuses,   true)) { $status        = ''; }

    // --- Build WHERE ---
    $where  = ['ap.deleted_at IS NULL'];
    $params = [];
    $types  = '';

    $where[]  = 'ap.payment_date >= ?';
    $params[] = $from_date;
    $types   .= 's';

    $where[]  = 'ap.payment_date <= ?';
    $params[] = $to_date;
    $types   .= 's';

    if (!empty($payer_type)) {
        $where[]  = 'ap.from_user_type = ?';
        $params[] = $payer_type;
        $types   .= 's';
    }

    // Payer district — type-aware subquery (avoids false COALESCE matches)
    if ($payer_district_id > 0) {
        $where[] = "ap.from_user_id IN (
            SELECT temp_id FROM c_and_f           WHERE district_id = ? AND deleted_at IS NULL
            UNION ALL
            SELECT temp_id FROM super_stockiest   WHERE district_id = ? AND deleted_at IS NULL
            UNION ALL
            SELECT temp_id FROM stockiest         WHERE district_id = ? AND deleted_at IS NULL
            UNION ALL
            SELECT temp_id FROM distributor       WHERE CAST(district_id AS UNSIGNED) = ? AND deleted_at IS NULL
            UNION ALL
            SELECT temp_id FROM super_distributor WHERE CAST(district_id AS UNSIGNED) = ? AND deleted_at IS NULL
        )";
        for ($i = 0; $i < 5; $i++) {
            $params[] = $payer_district_id;
            $types   .= 'i';
        }
    }

    if (!empty($payer_id)) {
        $where[]  = 'ap.from_user_id = ?';
        $params[] = $payer_id;
        $types   .= 's';
    }

    if (!empty($receiver_type)) {
        $where[]  = 'ap.to_user_type = ?';
        $params[] = $receiver_type;
        $types   .= 's';
    }

    if (!empty($receiver_id)) {
        $where[]  = 'ap.to_user_id = ?';
        $params[] = $receiver_id;
        $types   .= 's';
    }

    // Receiver district — type-aware subquery
    if ($receiver_district_id > 0 && $receiver_type !== 'company') {
        $where[] = "ap.to_user_id IN (
            SELECT temp_id FROM c_and_f           WHERE district_id = ? AND deleted_at IS NULL
            UNION ALL
            SELECT temp_id FROM super_stockiest   WHERE district_id = ? AND deleted_at IS NULL
            UNION ALL
            SELECT temp_id FROM stockiest         WHERE district_id = ? AND deleted_at IS NULL
            UNION ALL
            SELECT temp_id FROM distributor       WHERE CAST(district_id AS UNSIGNED) = ? AND deleted_at IS NULL
            UNION ALL
            SELECT temp_id FROM super_distributor WHERE CAST(district_id AS UNSIGNED) = ? AND deleted_at IS NULL
        )";
        for ($i = 0; $i < 5; $i++) {
            $params[] = $receiver_district_id;
            $types   .= 'i';
        }
    }

    if (!empty($status)) {
        $where[]  = 'ap.status = ?';
        $params[] = $status;
        $types   .= 's';
    }

    // Non-company users see only their own records
    if ($logged_user_type !== 'company') {
        $where[]  = '(ap.to_user_id = ? OR ap.from_user_id = ?)';
        $params[] = $logged_user_id;
        $params[] = $logged_user_id;
        $types   .= 'ss';
    }

    $where_sql = implode(' AND ', $where);

    // --------------------------------------------------------------------------
    // Consolidated/grouped query — one row per payer
    // Target amount: all via category table (source of truth)
    //   super_stockiest   → ssc.target_amount  (via ssr.ss_cat_id)
    //   stockiest         → sc.target_amount   (via sr.st_cat_id)
    //   distributor       → dc.amount          (via dr.dist_cat_id)
    //   super_distributor → sdc.amount         (via sdr.sd_cat_id)
    // --------------------------------------------------------------------------
    $sql = "SELECT
                ap.from_user_id,
                ap.from_user_type,
                ap.from_user_name,

                /* ── Payer District Name ─────────────────────────────────── */
                CASE
                    WHEN ap.from_user_type = 'c_and_f'           THEN dist_payer_cf.dist_name
                    WHEN ap.from_user_type = 'super_stockiest'   THEN dist_payer_ss.dist_name
                    WHEN ap.from_user_type = 'stockiest'         THEN dist_payer_s.dist_name
                    WHEN ap.from_user_type = 'distributor'       THEN dist_payer_d.dist_name
                    WHEN ap.from_user_type = 'super_distributor' THEN dist_payer_sd.dist_name
                    ELSE 'N/A'
                END AS payer_district_name,

                /* ── Payer Target Amount (all from category) ─────────────── */
                MAX(CASE
                    WHEN ap.from_user_type = 'c_and_f'
                        THEN 0
                    WHEN ap.from_user_type = 'super_stockiest'
                        THEN COALESCE(ssc.target_amount, 0)
                    WHEN ap.from_user_type = 'stockiest'
                        THEN COALESCE(sc.target_amount, 0)
                    WHEN ap.from_user_type = 'distributor'
                        THEN COALESCE(dc.amount, 0)
                    WHEN ap.from_user_type = 'super_distributor'
                        THEN COALESCE(sdc.amount, 0)
                    ELSE 0
                END) AS payer_target_amount,

                /* ── Consolidated payment totals ─────────────────────────── */
                COUNT(ap.id)          AS payment_count,
                MIN(ap.payment_date)  AS first_payment_date,
                MAX(ap.payment_date)  AS last_payment_date,
                SUM(ap.amount)        AS total_amount,
                SUM(ap.adjusted_amount) AS total_adjusted,
                SUM(ap.balance_amount)  AS total_balance,

                /* ── Receiver info (aggregated) ──────────────────────────── */
                GROUP_CONCAT(DISTINCT ap.to_user_name ORDER BY ap.to_user_name SEPARATOR ', ') AS receiver_names,
                GROUP_CONCAT(DISTINCT ap.to_user_type ORDER BY ap.to_user_type SEPARATOR ', ') AS receiver_types,

                /* ── Overall status ──────────────────────────────────────── */
                CASE
                    WHEN SUM(CASE WHEN ap.status = 'active'             THEN 1 ELSE 0 END) > 0 THEN 'active'
                    WHEN SUM(CASE WHEN ap.status = 'partially_adjusted' THEN 1 ELSE 0 END) > 0 THEN 'partially_adjusted'
                    ELSE 'fully_adjusted'
                END AS overall_status

            FROM advance_payments ap

            /* ── Payer entity JOINs ──────────────────────────────────────── */
            LEFT JOIN c_and_f payer_cf
                ON ap.from_user_type = 'c_and_f'
                AND ap.from_user_id = payer_cf.temp_id
                AND payer_cf.deleted_at IS NULL
            LEFT JOIN super_stockiest payer_ss
                ON ap.from_user_type = 'super_stockiest'
                AND ap.from_user_id = payer_ss.temp_id
                AND payer_ss.deleted_at IS NULL
            LEFT JOIN stockiest payer_s
                ON ap.from_user_type = 'stockiest'
                AND ap.from_user_id = payer_s.temp_id
                AND payer_s.deleted_at IS NULL
            LEFT JOIN distributor payer_d
                ON ap.from_user_type = 'distributor'
                AND ap.from_user_id = payer_d.temp_id
                AND payer_d.deleted_at IS NULL
            LEFT JOIN super_distributor payer_sd
                ON ap.from_user_type = 'super_distributor'
                AND ap.from_user_id = payer_sd.temp_id
                AND payer_sd.deleted_at IS NULL

            /* ── Payer district JOINs ────────────────────────────────────── */
            LEFT JOIN district dist_payer_cf ON payer_cf.district_id = dist_payer_cf.id
            LEFT JOIN district dist_payer_ss ON payer_ss.district_id = dist_payer_ss.id
            LEFT JOIN district dist_payer_s  ON payer_s.district_id  = dist_payer_s.id
            LEFT JOIN district dist_payer_d  ON CAST(payer_d.district_id  AS UNSIGNED) = dist_payer_d.id
            LEFT JOIN district dist_payer_sd ON CAST(payer_sd.district_id AS UNSIGNED) = dist_payer_sd.id

            /* ── Payer target amount JOINs (all via category FK) ─────────── */

            /* Super Stockiest → category via ss_cat_id */
            LEFT JOIN super_stockiest_referral ssr ON payer_ss.temp_id = ssr.super_stockiest_id
            LEFT JOIN super_stockiest_category ssc ON ssr.ss_cat_id    = ssc.id

            /* Stockiest → category via st_cat_id */
            LEFT JOIN stockist_referral sr ON payer_s.temp_id = sr.stockist_id
            LEFT JOIN stockist_category sc ON sr.st_cat_id    = sc.id

            /* Distributor → category via dist_cat_id (requires ALTER TABLE) */
            LEFT JOIN distributor_referral dr  ON payer_d.temp_id  = dr.distributor_id
            LEFT JOIN distributor_category dc  ON dr.dist_cat_id   = dc.id

            /* Super Distributor → category via sd_cat_id (requires ALTER TABLE) */
            LEFT JOIN super_distributor_referral sdr ON payer_sd.temp_id = sdr.sd_id
            LEFT JOIN super_distributor_category sdc ON sdr.sd_cat_id    = sdc.id

            WHERE {$where_sql}

            GROUP BY
                ap.from_user_id,
                ap.from_user_type,
                ap.from_user_name,
                dist_payer_cf.dist_name,
                dist_payer_ss.dist_name,
                dist_payer_s.dist_name,
                dist_payer_d.dist_name,
                dist_payer_sd.dist_name

            ORDER BY total_balance DESC, ap.from_user_name ASC";

    // --- Prepared statement execution ---
    $stmt = $db_conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Query preparation failed: ' . $db_conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('Query execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new RuntimeException('Failed to get result set: ' . $stmt->error);
    }

    // --- Process results ---
    $payments       = [];
    $total_amount   = 0.0;
    $total_balance  = 0.0;
    $adjusted_total = 0.0;

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $row_amount   = round((float)($row['total_amount']    ?? 0), 2);
        $row_balance  = round((float)($row['total_balance']   ?? 0), 2);
        $row_adjusted = round((float)($row['total_adjusted']  ?? 0), 2);

        $payments[] = [
            'from_user_id'        => $row['from_user_id']   ?? '',
            'from_user_type'      => $row['from_user_type'] ?? '',
            'from_user_name'      => htmlspecialchars($row['from_user_name']      ?? '', ENT_QUOTES, 'UTF-8'),
            'payer_district_name' => htmlspecialchars($row['payer_district_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            'payer_target_amount' => round((float)($row['payer_target_amount']    ?? 0), 2),
            'payment_count'       => (int)($row['payment_count']       ?? 0),
            'first_payment_date'  => $row['first_payment_date'] ?? '',
            'last_payment_date'   => $row['last_payment_date']  ?? '',
            'receiver_names'      => htmlspecialchars($row['receiver_names'] ?? '', ENT_QUOTES, 'UTF-8'),
            'receiver_types'      => htmlspecialchars($row['receiver_types'] ?? '', ENT_QUOTES, 'UTF-8'),
            'total_amount'        => $row_amount,
            'total_adjusted'      => $row_adjusted,
            'total_balance'       => $row_balance,
            'overall_status'      => $row['overall_status'] ?? '',
        ];

        $total_amount   += $row_amount;
        $total_balance  += $row_balance;
        $adjusted_total += $row_adjusted;
    }

    $response['data']    = $payments;
    $response['stats']   = [
        'total_payers'    => count($payments),
        'total_payments'  => array_sum(array_column($payments, 'payment_count')),
        'total_amount'    => inr_format($total_amount, 2),
        'total_balance'   => inr_format($total_balance, 2),
        'adjusted_amount' => inr_format($adjusted_total, 2),
    ];
    $response['success'] = true;

} catch (RuntimeException $e) {
    $response['error'] = $e->getMessage();
    error_log('[advance-payments-grouped] Error: ' . $e->getMessage());
} catch (Throwable $e) {
    $response['error'] = 'An internal error occurred. Please try again.';
    error_log('[advance-payments-grouped] Fatal: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

if (isset($db_conn) && $db_conn instanceof mysqli) {
    $db_conn->close();
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;