<?php
/**
 * Get TP Bonus Points Data - DataTable Server-Side Processing
 * Territory Partner counterpart of get-bonus-points-data.php.
 *
 * Always scoped to user_type = 'territory_partner'. No district filter —
 * unlike super_stockiest/stockiest, territory_partners has no direct
 * district_id column (location assignment is a many-to-many tree via
 * territory_partner_locations -> partner_location_nodes), so it's omitted
 * rather than faked.
 *
 * Supports "consolidate" mode: when no month_year is selected, rows for the
 * same TP are merged into one record.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/tp-bonus-points-data-errors.log');

ob_start();

header('Content-Type: application/json');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'error' => 'Server error: ' . $error['message'],
            'file'  => basename($error['file']),
            'line'  => $error['line'],
        ]);
    }
    ob_end_flush();
});

require_once("checksession.php");
require_once("config.php");

$logged_user_id   = isset($_SESSION['LOGIN_USER_ID'])   ? (string)$_SESSION['LOGIN_USER_ID']   : '';
$logged_user_type = isset($_SESSION['LOGIN_USER_TYPE']) ? (string)$_SESSION['LOGIN_USER_TYPE'] : '';

if (empty($logged_user_id) || empty($logged_user_type)) {
    ob_clean();
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

if ($db_conn) {
    mysqli_set_charset($db_conn, 'utf8mb4');
    $db_conn->query("SET SESSION group_concat_max_len = 10000");
}

function tpbpd_validateMonthYear($monthYear)
{
    if (empty($monthYear)) return null;
    if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) return null;
    return $monthYear;
}

function tpbpd_validateEligibility($status)
{
    $allowed = ['eligible', 'not_eligible', ''];
    $status  = isset($status) ? (string)$status : '';
    return in_array($status, $allowed, true) ? $status : '';
}

$filter_month_year   = tpbpd_validateMonthYear(isset($_POST['month_year']) ? $_POST['month_year'] : null);
$filter_user_id      = isset($_POST['user_id'])      ? trim((string)$_POST['user_id'])      : '';
$filter_eligibility  = tpbpd_validateEligibility(isset($_POST['eligibility']) ? $_POST['eligibility'] : '');
$filter_execution_id = isset($_POST['execution_id']) ? trim((string)$_POST['execution_id']) : '';

// Consolidate = no specific month selected
$consolidate = empty($filter_month_year);

function tpbpd_buildWhere(
    $filter_month_year,
    $filter_user_id,
    $filter_eligibility,
    $filter_execution_id,
    $logged_user_type,
    $logged_user_id,
    $consolidate
) {
    $conditions = ["bph.rolled_back_at IS NULL", "bph.user_type = 'territory_partner'"];
    $params     = [];
    $types      = '';

    if (!empty($filter_month_year)) {
        $conditions[] = 'bph.month_year = ?';
        $params[]     = $filter_month_year;
        $types       .= 's';
    }

    if (!empty($filter_user_id)) {
        $conditions[] = 'bph.user_id = ?';
        $params[]     = $filter_user_id;
        $types       .= 's';
    }

    // Eligibility: only filter at SQL level for per-month mode.
    // Consolidated eligibility is computed after GROUP BY and filtered in PHP.
    if (!$consolidate && !empty($filter_eligibility)) {
        $conditions[] = 'bph.eligibility_status = ?';
        $params[]     = $filter_eligibility;
        $types       .= 's';
    }

    if (!empty($filter_execution_id)) {
        $conditions[] = 'bph.execution_id = ?';
        $params[]     = $filter_execution_id;
        $types       .= 's';
    }

    // Non-company users only see their own records
    if ($logged_user_type !== 'company') {
        $conditions[] = '(bph.user_id = ? OR bph.executed_by_user_id = ?)';
        $params[]     = $logged_user_id;
        $params[]     = $logged_user_id;
        $types       .= 'ss';
    }

    return [
        'where'  => 'WHERE ' . implode("\n  AND ", $conditions),
        'params' => $params,
        'types'  => $types,
    ];
}

$where_data = tpbpd_buildWhere(
    $filter_month_year,
    $filter_user_id,
    $filter_eligibility,
    $filter_execution_id,
    $logged_user_type,
    $logged_user_id,
    $consolidate
);

$whereClause = $where_data['where'];
$params      = $where_data['params'];
$types       = $where_data['types'];

if ($consolidate) {

    $query = "
        SELECT
            MIN(bph.id)                                                     AS id,
            bph.user_id,
            bph.user_name,
            bph.user_type,
            bph.category_id,
            bph.category_name,

            MIN(bph.month_year)                                             AS month_year,
            COUNT(bph.month_year)                                           AS months_count,

            (
                SELECT GROUP_CONCAT(m.month_year ORDER BY m.month_year ASC SEPARATOR ',')
                FROM (
                    SELECT DISTINCT bph2.month_year
                    FROM bonus_points_history bph2
                    WHERE bph2.user_id      = bph.user_id
                      AND bph2.category_id  = bph.category_id
                      AND bph2.user_type    = 'territory_partner'
                      AND bph2.rolled_back_at IS NULL
                ) AS m
            )                                                               AS all_months,

            SUM(bph.monthly_target)                                         AS monthly_target,
            SUM(bph.total_advance_paid)                                     AS total_advance_paid,
            SUM(bph.bonus_points_awarded)                                   AS bonus_points_awarded,

            SUM(CASE WHEN bph.week1_status = 'pass' THEN 1 ELSE 0 END)     AS week1_pass_count,
            SUM(CASE WHEN bph.week2_status = 'pass' THEN 1 ELSE 0 END)     AS week2_pass_count,
            SUM(CASE WHEN bph.week3_status = 'pass' THEN 1 ELSE 0 END)     AS week3_pass_count,
            SUM(CASE WHEN bph.week4_status = 'pass' THEN 1 ELSE 0 END)     AS week4_pass_count,

            CASE
                WHEN SUM(CASE WHEN bph.eligibility_status != 'eligible'     THEN 1 ELSE 0 END) = 0 THEN 'eligible'
                WHEN SUM(CASE WHEN bph.eligibility_status != 'not_eligible' THEN 1 ELSE 0 END) = 0 THEN 'not_eligible'
                ELSE 'mixed'
            END                                                             AS eligibility_status,

            MAX(bph.executed_at)                                            AS executed_at,
            MAX(bph.executed_by_user_name)                                  AS executed_by_user_name,
            MAX(bph.executed_by_user_type)                                  AS executed_by_user_type,

            NULL AS week1_status, NULL AS week2_status, NULL AS week3_status, NULL AS week4_status,
            NULL AS week1_amount, NULL AS week2_amount, NULL AS week3_amount, NULL AS week4_amount,
            NULL AS bonus_calculation,
            NULL AS execution_id,
            NULL AS rolled_back_at

        FROM bonus_points_history bph
        {$whereClause}
        GROUP BY
            bph.user_id,
            bph.user_name,
            bph.user_type,
            bph.category_id,
            bph.category_name
        ORDER BY MAX(bph.executed_at) DESC
    ";

} else {

    $query = "
        SELECT
            bph.id,
            bph.user_id,
            bph.user_name,
            bph.user_type,
            bph.month_year,
            bph.category_id,
            bph.category_name,
            bph.monthly_target,
            bph.total_advance_paid,
            bph.week1_amount, bph.week1_cumulative, bph.week1_required, bph.week1_status,
            bph.week2_amount, bph.week2_cumulative, bph.week2_required, bph.week2_status,
            bph.week3_amount, bph.week3_cumulative, bph.week3_required, bph.week3_status,
            bph.week4_amount, bph.week4_cumulative, bph.week4_required, bph.week4_status,
            bph.eligibility_status,
            bph.bonus_points_awarded,
            bph.bonus_calculation,
            bph.execution_id,
            bph.executed_by_user_id,
            bph.executed_by_user_type,
            bph.executed_by_user_name,
            bph.executed_at,
            bph.rolled_back_at,
            NULL  AS all_months,
            1     AS months_count,
            NULL  AS week1_pass_count,
            NULL  AS week2_pass_count,
            NULL  AS week3_pass_count,
            NULL  AS week4_pass_count
        FROM bonus_points_history bph
        {$whereClause}
        ORDER BY bph.executed_at DESC, bph.month_year DESC
    ";
}

try {
    $stmt = $db_conn->prepare($query);

    if (!$stmt) {
        $err = $db_conn->error;
        error_log("Prepare failed: " . $err);
        error_log("Query: " . $query);
        ob_clean();
        echo json_encode(['error' => 'Query preparation failed: ' . $err]);
        exit;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        error_log("Execute failed: " . $err);
        ob_clean();
        echo json_encode(['error' => 'Query execution failed: ' . $err]);
        exit;
    }

    $result = $stmt->get_result();
    $data   = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    $stmt->close();

} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    ob_clean();
    echo json_encode(['error' => 'Database exception: ' . $e->getMessage()]);
    exit;
}

if ($consolidate && !empty($filter_eligibility)) {
    $data = array_values(array_filter($data, function ($row) use ($filter_eligibility) {
        if ($filter_eligibility === 'eligible') {
            return $row['eligibility_status'] === 'eligible';
        }
        return in_array($row['eligibility_status'], ['not_eligible', 'mixed'], true);
    }));
}

$total_records      = count($data);
$eligible_users     = 0;
$ineligible_users   = 0;
$total_bonus_points = 0.00;

foreach ($data as $record) {
    $total_bonus_points += (float)(isset($record['bonus_points_awarded']) ? $record['bonus_points_awarded'] : 0);

    if ($record['eligibility_status'] === 'eligible') {
        $eligible_users++;
    } else {
        $ineligible_users++;
    }
}

ob_clean();
echo json_encode([
    'data'            => $data,
    'stats'           => [
        'total_records'      => $total_records,
        'eligible_users'     => $eligible_users,
        'ineligible_users'   => $ineligible_users,
        'total_bonus_points' => $total_bonus_points,
        'consolidate'        => $consolidate,
    ],
    'recordsTotal'    => $total_records,
    'recordsFiltered' => $total_records,
]);

if (isset($db_conn) && $db_conn) {
    $db_conn->close();
}
