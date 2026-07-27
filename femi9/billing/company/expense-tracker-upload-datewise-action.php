<?php
/**
 * Expense Tracker — Date-Detecting Upload Handler (Neksomo)
 *
 * Unlike expense-tracker-upload-action.php (a single Tally Group Summary
 * pivot covering one manually-picked date range), this parses a plain
 * per-transaction list — one row per expense, each carrying its own Date —
 * and figures out the period(s) itself: rows are grouped by the month of
 * their own date, and one expense_imports batch is created per month found
 * in the file, so a file spanning several months lands correctly in each
 * month's bucket instead of all being counted under a single period.
 *
 * Expected columns (header names matched case-insensitively, any order):
 *   - a "Date" column
 *   - a "Particulars" / "Description" / "Narration" column
 *   - an "Amount" column (positive = expense/debit, negative = credit/refund)
 *
 * Accepts .xlsx, .xls, and .csv — PhpSpreadsheet's IOFactory::load() detects
 * the actual format from file content, not just the extension.
 */

session_start();

include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

date_default_timezone_set("Asia/Kolkata");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function redirect_back_dw($params) {
    $query = http_build_query(array_merge([
        'company_id' => $_POST['company_id'] ?? '',
    ], $params));
    header("Location: expense-tracker.php?$query");
    exit;
}

if (empty($_SESSION['LOGIN_USER_ID'])) {
    redirect_back_dw(['err' => 'Please login first']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_back_dw(['err' => 'Invalid request method']);
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    redirect_back_dw(['err' => 'Security validation failed']);
}

$company_id = (int)($_POST['company_id'] ?? 0);
if ($company_id <= 0 || !is_godown_allowed($db_conn, $company_id)) {
    redirect_back_dw(['err' => 'Invalid or unauthorized company profile']);
}

if (empty($_FILES['expense_file']) || $_FILES['expense_file']['error'] !== UPLOAD_ERR_OK) {
    redirect_back_dw(['err' => 'File upload failed. Please try again.']);
}

$original_name = $_FILES['expense_file']['name'];
$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
    redirect_back_dw(['err' => 'Only .xlsx, .xls, or .csv files are allowed']);
}

$upload_dir = __DIR__ . '/expense_uploads';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
$stored_name = 'exp_dw_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$stored_path = $upload_dir . '/' . $stored_name;

if (!move_uploaded_file($_FILES['expense_file']['tmp_name'], $stored_path)) {
    redirect_back_dw(['err' => 'Could not save uploaded file']);
}

// ---------------------------------------------------------------------
// Parse the uploaded list
// ---------------------------------------------------------------------

function dw_parse_amount($val) {
    if ($val === null || $val === '') return null;
    $val = str_replace([',', "\xC2\xA0"], ['', ''], (string)$val);
    $val = trim($val);
    if ($val === '' || !is_numeric($val)) return null;
    return (float)$val;
}

// Excel/CSV dates arrive as whatever the cell displays — a formatted string
// for xlsx/xls (toArray()'s formatData=true below turns date cells into
// their displayed text), plain unformatted text for csv, or occasionally a
// raw Excel serial number if the source cell had no date format applied.
// d/m/Y is tried before the locale-ambiguous fallback so Indian-convention
// dates (17/07/2026) aren't misread as month/day.
function dw_parse_date($val) {
    $val = trim((string)$val);
    if ($val === '') return null;

    if (is_numeric($val) && $val > 20000 && $val < 60000) {
        try {
            return ExcelDate::excelToDateTimeObject((float)$val)->format('Y-m-d');
        } catch (Exception $e) {
            // fall through to string parsing
        }
    }

    foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'Y/m/d', 'd-M-y', 'd-M-Y', 'd.m.Y', 'M-d-Y'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $val);
        if ($dt !== false) {
            $errors = DateTime::getLastErrors();
            if (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                return $dt->format('Y-m-d');
            }
        }
    }

    $ts = strtotime($val);
    return $ts !== false ? date('Y-m-d', $ts) : null;
}

try {
    $spreadsheet = IOFactory::load($stored_path);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);
} catch (Exception $e) {
    error_log("Expense tracker (datewise) parse error: " . $e->getMessage());
    @unlink($stored_path);
    redirect_back_dw(['err' => 'Could not read the uploaded file. Please confirm it is a valid Excel or CSV file.']);
}

// Find the header row: the first row containing recognizable Date and
// Amount headers (Particulars/Description is nice-to-have, not required —
// it falls back to whichever column is left over).
$header_row_num = null;
$date_col = null;
$amount_col = null;
$desc_col = null;

foreach ($rows as $rnum => $row) {
    $found_date = null;
    $found_amount = null;
    $found_desc = null;
    foreach ($row as $col => $val) {
        $v = strtolower(trim((string)$val));
        if ($v === '') continue;
        if ($found_date === null && strpos($v, 'date') !== false) {
            $found_date = $col;
        } elseif ($found_amount === null && (strpos($v, 'amount') !== false || strpos($v, 'debit') !== false)) {
            $found_amount = $col;
        } elseif ($found_desc === null && (strpos($v, 'particular') !== false || strpos($v, 'description') !== false || strpos($v, 'narration') !== false || strpos($v, 'detail') !== false || strpos($v, 'remark') !== false)) {
            $found_desc = $col;
        }
    }
    if ($found_date !== null && $found_amount !== null) {
        $header_row_num = $rnum;
        $date_col = $found_date;
        $amount_col = $found_amount;
        $desc_col = $found_desc;
        break;
    }
}

if ($header_row_num === null) {
    @unlink($stored_path);
    redirect_back_dw(['err' => "Could not find both a 'Date' column and an 'Amount' column in this file — please confirm the header row names them clearly."]);
}

// Simple two-column-only files: whichever column isn't Date/Amount is the
// description, so a header the scan didn't recognise by name still works.
if ($desc_col === null) {
    foreach (array_keys(reset($rows)) as $col) {
        if ($col !== $date_col && $col !== $amount_col) {
            $desc_col = $col;
            break;
        }
    }
}

$row_nums = array_keys($rows);
$start_index = array_search($header_row_num, $row_nums, true) + 1;

$transactions = [];
$skipped = 0;
for ($idx = $start_index; $idx < count($row_nums); $idx++) {
    $rnum = $row_nums[$idx];
    $row = $rows[$rnum];

    $raw_date   = $row[$date_col] ?? '';
    $raw_amount = $row[$amount_col] ?? '';
    $raw_desc   = $desc_col !== null ? trim((string)($row[$desc_col] ?? '')) : '';

    if (trim((string)$raw_date) === '' && trim((string)$raw_amount) === '') {
        continue; // blank spacer row
    }

    $date   = dw_parse_date($raw_date);
    $amount = dw_parse_amount($raw_amount);

    if ($date === null || $amount === null || $amount == 0.0) {
        $skipped++;
        continue;
    }

    $transactions[] = [
        'date' => $date,
        'particulars' => $raw_desc !== '' ? $raw_desc : 'Expense',
        'debit' => $amount > 0 ? $amount : 0.0,
        'credit' => $amount < 0 ? -$amount : 0.0,
        'net_amount' => $amount,
    ];
}

if (empty($transactions)) {
    @unlink($stored_path);
    redirect_back_dw(['err' => 'No valid rows found — each row needs a readable Date and a non-zero Amount.']);
}

// Group by month so each month gets its own expense_imports batch, matching
// the existing month-bucketed schema (mis-report.php's net profit calc and
// this page both filter by expense_month).
$by_month = [];
foreach ($transactions as $t) {
    $month_key = substr($t['date'], 0, 7); // 'Y-m'
    $by_month[$month_key][] = $t;
}

// ---------------------------------------------------------------------
// Save to database — one batch per month, one item per transaction row
// ---------------------------------------------------------------------

mysqli_begin_transaction($db_conn);
try {
    $uploaded_by = $_SESSION['LOGIN_USER'] ?? '';
    $overall_from = null;
    $overall_to = null;

    $batch_stmt = $db_conn->prepare("
        INSERT INTO expense_imports
            (company_id, expense_month, period_from, period_to, source_filename, group_name, period_label, total_debit, total_credit, net_amount, uploaded_by)
        VALUES (?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?)
    ");
    $item_stmt = $db_conn->prepare("
        INSERT INTO expense_import_items (import_id, date, particulars, debit, credit, net_amount)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($by_month as $month_key => $items) {
        $dates = array_column($items, 'date');
        $period_from = min($dates);
        $period_to   = max($dates);
        $expense_month = $month_key . '-01';

        $total_debit  = array_sum(array_column($items, 'debit'));
        $total_credit = array_sum(array_column($items, 'credit'));
        $net_amount   = $total_debit - $total_credit;

        $batch_stmt->bind_param(
            "issssddds",
            $company_id, $expense_month, $period_from, $period_to, $original_name,
            $total_debit, $total_credit, $net_amount, $uploaded_by
        );
        $batch_stmt->execute();
        $import_id = $batch_stmt->insert_id;

        foreach ($items as $item) {
            $item_stmt->bind_param(
                "issddd",
                $import_id, $item['date'], $item['particulars'], $item['debit'], $item['credit'], $item['net_amount']
            );
            $item_stmt->execute();
        }

        if ($overall_from === null || $period_from < $overall_from) $overall_from = $period_from;
        if ($overall_to === null || $period_to > $overall_to) $overall_to = $period_to;
    }
    $batch_stmt->close();
    $item_stmt->close();

    mysqli_commit($db_conn);
} catch (Exception $e) {
    mysqli_rollback($db_conn);
    @unlink($stored_path);
    error_log("Expense tracker (datewise) save error: " . $e->getMessage());
    redirect_back_dw(['err' => 'Failed to save expense data: ' . $e->getMessage()]);
}

$month_count = count($by_month);
$msg = count($transactions) . " expense entries across {$month_count} month" . ($month_count > 1 ? 's' : '') . " uploaded successfully";
if ($skipped > 0) {
    $msg .= " ({$skipped} row" . ($skipped > 1 ? 's' : '') . " skipped — missing/unreadable date or amount)";
}

redirect_back_dw([
    'msg' => $msg,
    'from_date' => $overall_from,
    'to_date' => $overall_to,
]);
