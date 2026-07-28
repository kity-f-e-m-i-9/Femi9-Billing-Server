<?php
/**
 * Expense Tracker — Manual Single Entry (Neksomo)
 *
 * One expense typed in directly — no spreadsheet needed — for one-off
 * entries. Reuses (or creates) a single expense_imports batch per company
 * per month, marked source_filename='Manual Entry', so repeated manual
 * additions for the same month accumulate into that one batch instead of
 * creating a new batch row per entry — the same "adds to existing totals"
 * convention the Tally and date-detecting uploads already follow for a
 * period that falls in an already-covered month.
 */

session_start();

include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");

date_default_timezone_set("Asia/Kolkata");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function redirect_back_manual($params) {
    $query = http_build_query(array_merge([
        'company_id' => $_POST['company_id'] ?? '',
    ], $params));
    header("Location: expense-tracker.php?$query");
    exit;
}

if (empty($_SESSION['LOGIN_USER_ID'])) {
    redirect_back_manual(['err' => 'Please login first']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_back_manual(['err' => 'Invalid request method']);
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    redirect_back_manual(['err' => 'Security validation failed']);
}

$company_id = (int)($_POST['company_id'] ?? 0);
if ($company_id <= 0 || !is_godown_allowed($db_conn, $company_id)) {
    redirect_back_manual(['err' => 'Invalid or unauthorized company profile']);
}

$date = $_POST['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
    redirect_back_manual(['err' => 'Please provide a valid date']);
}

$particulars = trim($_POST['particulars'] ?? '');
if ($particulars === '') {
    redirect_back_manual(['err' => 'Please enter what the expense was for']);
}
if (mb_strlen($particulars) > 255) {
    $particulars = mb_substr($particulars, 0, 255);
}

$amount = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);
if ($amount === false || $amount == 0.0) {
    redirect_back_manual(['err' => 'Please enter a non-zero amount']);
}

$debit         = $amount > 0 ? $amount : 0.0;
$credit        = $amount < 0 ? -$amount : 0.0;
$net_amount    = $amount;
$expense_month = date('Y-m-01', strtotime($date));
$uploaded_by   = $_SESSION['LOGIN_USER'] ?? '';

mysqli_begin_transaction($db_conn);
try {
    $stmt = $db_conn->prepare("
        SELECT id, period_from, period_to FROM expense_imports
        WHERE company_id = ? AND expense_month = ? AND source_filename = 'Manual Entry'
        LIMIT 1
    ");
    $stmt->bind_param("is", $company_id, $expense_month);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $import_id   = (int)$existing['id'];
        $period_from = (!empty($existing['period_from']) && $existing['period_from'] < $date) ? $existing['period_from'] : $date;
        $period_to   = (!empty($existing['period_to'])   && $existing['period_to']   > $date) ? $existing['period_to']   : $date;

        $stmt = $db_conn->prepare("
            UPDATE expense_imports
            SET period_from = ?, period_to = ?,
                total_debit = total_debit + ?, total_credit = total_credit + ?, net_amount = net_amount + ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssdddi", $period_from, $period_to, $debit, $credit, $net_amount, $import_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $db_conn->prepare("
            INSERT INTO expense_imports
                (company_id, expense_month, period_from, period_to, source_filename, group_name, period_label, total_debit, total_credit, net_amount, uploaded_by)
            VALUES (?, ?, ?, ?, 'Manual Entry', NULL, NULL, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issssdds", $company_id, $expense_month, $date, $date, $debit, $credit, $net_amount, $uploaded_by);
        $stmt->execute();
        $import_id = $stmt->insert_id;
        $stmt->close();
    }

    $item_stmt = $db_conn->prepare("
        INSERT INTO expense_import_items (import_id, date, particulars, debit, credit, net_amount)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $item_stmt->bind_param("issddd", $import_id, $date, $particulars, $debit, $credit, $net_amount);
    $item_stmt->execute();
    $item_stmt->close();

    mysqli_commit($db_conn);
} catch (Exception $e) {
    mysqli_rollback($db_conn);
    error_log("Expense tracker manual entry error: " . $e->getMessage());
    redirect_back_manual(['err' => 'Failed to save expense: ' . $e->getMessage()]);
}

redirect_back_manual([
    'msg' => "Added — " . ($net_amount < 0 ? "credit" : "expense") . " of \xE2\x82\xB9" . number_format(abs($net_amount), 2) . " on " . date('d M Y', strtotime($date)),
    'from_date' => $date,
    'to_date' => $date,
]);
