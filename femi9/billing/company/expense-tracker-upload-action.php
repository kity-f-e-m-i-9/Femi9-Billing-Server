<?php
/**
 * Expense Tracker — Tally Group Summary Upload Handler
 *
 * Parses a Tally "Group Summary" Excel export (e.g. Indirect Expenses) and
 * stores one expense_imports batch row plus one expense_import_items row
 * per ledger/particular found between the "Particulars" header row and the
 * "Grand Total" row.
 */

session_start();

include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

date_default_timezone_set("Asia/Kolkata");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function redirect_back($params) {
    $query = http_build_query(array_merge([
        'expense_month' => $_POST['expense_month'] ?? '',
        'company_id' => $_POST['company_id'] ?? '',
    ], $params));
    header("Location: expense-tracker.php?$query");
    exit;
}

if (empty($_SESSION['LOGIN_USER_ID'])) {
    redirect_back(['err' => 'Please login first']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_back(['err' => 'Invalid request method']);
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    redirect_back(['err' => 'Security validation failed']);
}

$company_id = (int)($_POST['company_id'] ?? 0);
$expense_month = $_POST['expense_month'] ?? '';

if ($company_id <= 0 || !is_godown_allowed($db_conn, $company_id)) {
    redirect_back(['err' => 'Invalid or unauthorized company profile']);
}

if (!preg_match('/^\d{4}-\d{2}$/', $expense_month)) {
    redirect_back(['err' => 'Invalid month']);
}
$expense_month_date = $expense_month . '-01';

if (empty($_FILES['tally_file']) || $_FILES['tally_file']['error'] !== UPLOAD_ERR_OK) {
    redirect_back(['err' => 'File upload failed. Please try again.']);
}

$original_name = $_FILES['tally_file']['name'];
$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls'], true)) {
    redirect_back(['err' => 'Only .xlsx or .xls files are allowed']);
}

$upload_dir = __DIR__ . '/expense_uploads';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
$stored_name = 'exp_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$stored_path = $upload_dir . '/' . $stored_name;

if (!move_uploaded_file($_FILES['tally_file']['tmp_name'], $stored_path)) {
    redirect_back(['err' => 'Could not save uploaded file']);
}

// ---------------------------------------------------------------------
// Parse the Tally Group Summary export
// ---------------------------------------------------------------------

function parse_amount($val) {
    if ($val === null || $val === '') return 0.0;
    $val = str_replace([',', "\xC2\xA0"], ['', ''], (string)$val);
    $val = trim($val);
    if ($val === '' || !is_numeric($val)) return 0.0;
    return (float)$val;
}

try {
    $spreadsheet = IOFactory::load($stored_path);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);
} catch (Exception $e) {
    error_log("Expense tracker parse error: " . $e->getMessage());
    @unlink($stored_path);
    redirect_back(['err' => 'Could not read the uploaded file. Please confirm it is a valid Tally Excel export.']);
}

$header_row_num = null;
$group_name = null;
$period_label = null;

foreach ($rows as $rnum => $row) {
    $colA = trim((string)($row['A'] ?? ''));
    if ($colA !== '' && stripos($colA, 'Expenses') !== false && $group_name === null && $header_row_num === null) {
        $group_name = $colA;
    }
    if ($period_label === null) {
        $colB = trim((string)($row['B'] ?? ''));
        foreach ([$colA, $colB] as $candidate) {
            if ($candidate !== '' && stripos($candidate, ' to ') !== false) {
                $period_label = $candidate;
                break;
            }
        }
    }
    if (strcasecmp($colA, 'Particulars') === 0) {
        $header_row_num = $rnum;
        break;
    }
}

if ($header_row_num === null) {
    @unlink($stored_path);
    redirect_back(['err' => "Could not find a 'Particulars' column in this file — please confirm it is a Tally Group Summary export."]);
}

$items = [];
$parsed_debit = 0.0;
$parsed_credit = 0.0;
$file_total_debit = null;
$file_total_credit = null;

// Tally's Group Summary export nests sub-ledgers beneath a group total row:
// the group row (cell indent 0) already equals the sum of its indented
// children, so only indent-0 rows are counted as real line items — the
// indented breakdown rows are display detail, not additional expense.
$row_nums = array_keys($rows);
$start_index = array_search($header_row_num, $row_nums, true) + 1;

for ($idx = $start_index; $idx < count($row_nums); $idx++) {
    $rnum = $row_nums[$idx];
    $row = $rows[$rnum];
    $particulars = trim((string)($row['A'] ?? ''));

    if ($particulars === '') {
        continue; // sub-header rows like "Closing Balance" / "Debit | Credit"
    }

    if (strcasecmp($particulars, 'Grand Total') === 0) {
        $file_total_debit = parse_amount($row['B'] ?? null);
        $file_total_credit = parse_amount($row['C'] ?? null);
        break;
    }

    $indent = $sheet->getStyle("A{$rnum}")->getAlignment()->getIndent();
    if ($indent > 0) {
        continue; // nested breakdown of the preceding indent-0 group row
    }

    $debit = parse_amount($row['B'] ?? null);
    $credit = parse_amount($row['C'] ?? null);

    $items[] = [
        'particulars' => $particulars,
        'debit' => $debit,
        'credit' => $credit,
        'net_amount' => $debit - $credit,
    ];
    $parsed_debit += $debit;
    $parsed_credit += $credit;
}

if (empty($items)) {
    @unlink($stored_path);
    redirect_back(['err' => 'No expense line items were found between the Particulars header and Grand Total row.']);
}

$net_amount = $parsed_debit - $parsed_credit;

$warning = '';
if ($file_total_debit !== null && abs($file_total_debit - $parsed_debit) > 0.5) {
    $warning = " (Note: parsed debit total ₹" . inr_format($parsed_debit, 2) . " differs from the file's Grand Total ₹" . inr_format($file_total_debit, 2) . " — please verify.)";
}

// ---------------------------------------------------------------------
// Save to database
// ---------------------------------------------------------------------

mysqli_begin_transaction($db_conn);
try {
    $uploaded_by = $_SESSION['LOGIN_USER'] ?? '';

    $stmt = $db_conn->prepare("
        INSERT INTO expense_imports
            (company_id, expense_month, source_filename, group_name, period_label, total_debit, total_credit, net_amount, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "issssddds",
        $company_id, $expense_month_date, $original_name, $group_name, $period_label,
        $parsed_debit, $parsed_credit, $net_amount, $uploaded_by
    );
    $stmt->execute();
    $import_id = $stmt->insert_id;
    $stmt->close();

    $item_stmt = $db_conn->prepare("
        INSERT INTO expense_import_items (import_id, particulars, debit, credit, net_amount)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($items as $item) {
        $item_stmt->bind_param(
            "isddd",
            $import_id, $item['particulars'], $item['debit'], $item['credit'], $item['net_amount']
        );
        $item_stmt->execute();
    }
    $item_stmt->close();

    mysqli_commit($db_conn);
} catch (Exception $e) {
    mysqli_rollback($db_conn);
    @unlink($stored_path);
    error_log("Expense tracker save error: " . $e->getMessage());
    redirect_back(['err' => 'Failed to save expense data: ' . $e->getMessage()]);
}

redirect_back(['msg' => "Uploaded successfully — " . count($items) . " expense items, net ₹" . inr_format($net_amount, 2) . $warning]);
