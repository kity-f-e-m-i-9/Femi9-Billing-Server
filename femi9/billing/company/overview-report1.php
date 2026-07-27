<?php
declare(strict_types=1);

include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");

error_reporting(0);
ini_set('log_errors', '1');

// ─── Helpers ──────────────────────────────────────────────────────────────────

function e(mixed $val): string
{
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
}

function sanitizeInt(mixed $value, int $default = 0): int
{
    return filter_var($value ?? $default, FILTER_VALIDATE_INT) !== false
        ? (int)$value : $default;
}

function sanitizeDate(mixed $value): string
{
    $date = trim((string)($value ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false) {
        return $date;
    }
    return date('Y-m-d');
}

function sanitizeString(mixed $value, string $default = ''): string
{
    return htmlspecialchars(trim((string)($value ?? $default)), ENT_QUOTES, 'UTF-8');
}

// ─── Constants ────────────────────────────────────────────────────────────────

const ALLOWED_CATEGORIES = [
    '', 'super_stockiest', 'stockiest', 'distributor',
    'super_distributor', 'customer', 'shop', 'outlet', 'territory_partner',
];

const USER_TYPE_LABELS = [
    'super_stockiest'   => 'Super Stockist',
    'stockiest'         => 'Stockist',
    'super_distributor' => 'Super Distributor',
    'distributor'       => 'Distributor',
    'outlet'            => 'Outlet',
    'shop'              => 'Shop',
    'territory_partner' => 'Territory Partner',
];

const ROWS_PER_PAGE = 30;

// ─── Clear Filters ────────────────────────────────────────────────────────────
if (isset($_POST['clear_all'])) {
    foreach ([
        'sales_report_from_date', 'sales_report_to_date',
        'sales_report_category', 'sales_report_records_per_page',
    ] as $key) {
        unset($_SESSION[$key]);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

// ─── Records per page ─────────────────────────────────────────────────────────
$allowedRpp    = [20, 40, 60];
$recordsPerPage = 20;
if (isset($_POST['records_per_page'])) {
    $rpp = (int)$_POST['records_per_page'];
    $recordsPerPage = in_array($rpp, $allowedRpp, true) ? $rpp : 20;
    $_SESSION['sales_report_records_per_page'] = $recordsPerPage;
} elseif (isset($_SESSION['sales_report_records_per_page'])) {
    $recordsPerPage = (int)$_SESSION['sales_report_records_per_page'];
}

// ─── Dates ────────────────────────────────────────────────────────────────────
$defaultFrom = date('Y-m-d', strtotime('-7 days'));
$defaultTo   = date('Y-m-d');

if (isset($_POST['frdate']) && !empty($_POST['frdate'])) {
    $fromDate = sanitizeDate($_POST['frdate']);
    $_SESSION['sales_report_from_date'] = $fromDate;
} elseif (isset($_SESSION['sales_report_from_date'])) {
    $fromDate = $_SESSION['sales_report_from_date'];
} else {
    $fromDate = $defaultFrom;
}

if (isset($_POST['todate']) && !empty($_POST['todate'])) {
    $toDate = sanitizeDate($_POST['todate']);
    $_SESSION['sales_report_to_date'] = $toDate;
} elseif (isset($_SESSION['sales_report_to_date'])) {
    $toDate = $_SESSION['sales_report_to_date'];
} else {
    $toDate = $defaultTo;
}

// ─── Other Inputs ─────────────────────────────────────────────────────────────
$label      = sanitizeInt($_REQUEST['lable']     ?? 0);
$rptLabel   = sanitizeInt($_REQUEST['rptlable']  ?? 0);
$out1       = sanitizeInt($_REQUEST['out1']      ?? 0);
$out2       = sanitizeInt($_REQUEST['out2']      ?? 0);
$out3       = sanitizeString($_REQUEST['out3']   ?? '0');
$setTrigger = sanitizeInt($_REQUEST['setrigger'] ?? 0);

// Category filter
if (isset($_POST['catname'])) {
    $raw = trim((string)$_POST['catname']);
    $selectedCategory = in_array($raw, ALLOWED_CATEGORIES, true) ? $raw : '';
    $_SESSION['sales_report_category'] = $selectedCategory;
} elseif (isset($_SESSION['sales_report_category'])) {
    $selectedCategory = $_SESSION['sales_report_category'];
} else {
    $selectedCategory = '';
}

// Universal search
$search = '';
if (isset($_GET['q'])) {
    $search = trim($_GET['q']);
    $_SESSION['sales_report_search'] = $search;
} elseif (isset($_POST['q'])) {
    $search = trim($_POST['q']);
    $_SESSION['sales_report_search'] = $search;
} elseif (isset($_SESSION['sales_report_search'])) {
    $search = $_SESSION['sales_report_search'];
}
$isSearch  = ($search !== '');
$qParam    = urlencode($search);

// ─── Display Labels ───────────────────────────────────────────────────────────
$displayLabel = match([$label, $rptLabel]) {
    [1, 1]  => 'Today',
    [2, 1]  => 'Yesterday',
    [3, 1]  => 'This Month',
    default => 'Last Month',
};
$reportLabel = 'Sales From Company';

// ─── Pagination: Current page ─────────────────────────────────────────────────
$currentPage = max(1, sanitizeInt($_GET['page'] ?? 1));

// ─── COUNT queries ────────────────────────────────────────────────────────────

$totalUserInvoices     = 0;
$totalCustomerInvoices = 0;

if ($selectedCategory !== 'customer') {
    $sqlCount = "SELECT COUNT(*) AS cnt FROM user_invoice
                 WHERE date BETWEEN ? AND ? AND from_user_type = ? AND sub_total > 0
                   AND (from_user_type != 'company' OR from_user_id IN (" . godown_ids_subquery($db_conn) . "))";
    $types    = 'sss';
    $params   = [$fromDate, $toDate, $Login_user_TYPEvl];
    if (!empty($selectedCategory)) {
        $sqlCount .= " AND to_user_type = ?";
        $params[]  = $selectedCategory;
        $types    .= 's';
    }
    $stmt = $db_conn->prepare($sqlCount);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalUserInvoices = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
}

if (empty($selectedCategory) || $selectedCategory === 'customer') {
    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS cnt FROM invoice
         WHERE date BETWEEN ? AND ? AND user_type = ? AND sub_total > 0"
    );
    $stmt->bind_param('sss', $fromDate, $toDate, $Login_user_TYPEvl);
    $stmt->execute();
    $totalCustomerInvoices = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
}

// Territory Partner invoices raised by the Company login itself (not by an SS
// acting on the TP's behalf) — mirrors created_by_user_type used elsewhere.
$totalTpInvoices = 0;
if (empty($selectedCategory) || $selectedCategory === 'territory_partner') {
    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS cnt FROM tp_invoices
         WHERE invoice_date BETWEEN ? AND ? AND created_by_user_type = 'company' AND total_amount > 0
           AND source_godown_id IN (" . godown_ids_subquery($db_conn) . ")"
    );
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $totalTpInvoices = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
}

$totalRows  = $totalUserInvoices + $totalCustomerInvoices + $totalTpInvoices;
$totalPages = max(1, (int)ceil($totalRows / $recordsPerPage));
$currentPage = min($currentPage, $totalPages);

// ─── Slice math ───────────────────────────────────────────────────────────────
$globalOffset = ($currentPage - 1) * $recordsPerPage;
$remaining    = $recordsPerPage;

$userOffset = 0; $userLimit = 0;
if ($globalOffset < $totalUserInvoices) {
    $userOffset = $globalOffset;
    $userLimit  = min($remaining, $totalUserInvoices - $userOffset);
    $remaining -= $userLimit;
}

$custOffset = 0; $custLimit = 0;
if ($remaining > 0) {
    $custOffset = max(0, $globalOffset - $totalUserInvoices);
    $custLimit  = min($remaining, $totalCustomerInvoices - $custOffset);
    $remaining -= $custLimit;
}

$tpOffset = 0; $tpLimit = 0;
if ($remaining > 0) {
    $tpOffset = max(0, $globalOffset - $totalUserInvoices - $totalCustomerInvoices);
    $tpLimit  = min($remaining, $totalTpInvoices - $tpOffset);
}

// ─── Fetch page rows ──────────────────────────────────────────────────────────
$userInvoices = [];
if ($userLimit > 0) {
    $sql    = "SELECT inv_id, inv_number, date, total, to_user_type, to_user_id, from_user_id
               FROM user_invoice
               WHERE date BETWEEN ? AND ? AND from_user_type = ? AND sub_total > 0
                 AND (from_user_type != 'company' OR from_user_id IN (" . godown_ids_subquery($db_conn) . "))";
    $types  = 'sss';
    $params = [$fromDate, $toDate, $Login_user_TYPEvl];
    if (!empty($selectedCategory)) {
        $sql .= " AND to_user_type = ?";
        $params[] = $selectedCategory; $types .= 's';
    }
    $sql .= " ORDER BY id ASC LIMIT ? OFFSET ?";
    $params[] = $userLimit; $params[] = $userOffset; $types .= 'ii';
    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $userInvoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$customerInvoices = [];
if ($custLimit > 0) {
    $stmt = $db_conn->prepare(
        "SELECT inv_id, inv_number, date, total, customer_id, user_id
         FROM invoice
         WHERE date BETWEEN ? AND ? AND user_type = ? AND sub_total > 0
         ORDER BY id ASC LIMIT ? OFFSET ?"
    );
    $stmt->bind_param('sssii', $fromDate, $toDate, $Login_user_TYPEvl, $custLimit, $custOffset);
    $stmt->execute();
    $customerInvoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$tpInvoices = [];
if ($tpLimit > 0) {
    $sql = "SELECT id AS inv_id, invoice_number AS inv_number, invoice_date AS date, total_amount AS total,
                   territory_partner_id, source_godown_id
            FROM tp_invoices
            WHERE invoice_date BETWEEN ? AND ? AND created_by_user_type = 'company' AND total_amount > 0
              AND source_godown_id IN (" . godown_ids_subquery($db_conn) . ")
            ORDER BY id ASC LIMIT ? OFFSET ?";
    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param('ssii', $fromDate, $toDate, $tpLimit, $tpOffset);
    $stmt->execute();
    $tpInvoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ─── Products ─────────────────────────────────────────────────────────────────
$products = []; $productIds = [];
$stmt = $db_conn->prepare("SELECT id, productName FROM products ORDER BY id ASC");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $products[$row['id']] = $row['productName'];
    $productIds[]         = $row['id'];
}
$stmt->close();

// ─── Invoice items for this page ─────────────────────────────────────────────
$userInvItems = [];
if (!empty($userInvoices)) {
    $ids          = array_column($userInvoices, 'inv_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt         = $db_conn->prepare(
        "SELECT inv_id, pr_id, qty FROM user_invoice_items WHERE inv_id IN ($placeholders)"
    );
    $stmt->bind_param(str_repeat('s', count($ids)), ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $userInvItems[$row['inv_id']][$row['pr_id']] = $row['qty'];
    }
    $stmt->close();
}

$custInvItems = [];
if (!empty($customerInvoices)) {
    $ids          = array_column($customerInvoices, 'inv_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt         = $db_conn->prepare(
        "SELECT inv_id, pr_id, qty FROM invoice_items WHERE inv_id IN ($placeholders)"
    );
    $stmt->bind_param(str_repeat('s', count($ids)), ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $custInvItems[$row['inv_id']][$row['pr_id']] = $row['qty'];
    }
    $stmt->close();
}

$tpInvItems = [];
if (!empty($tpInvoices)) {
    $ids          = array_column($tpInvoices, 'inv_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt         = $db_conn->prepare(
        "SELECT tp_invoice_id, product_id, quantity FROM tp_invoice_items WHERE tp_invoice_id IN ($placeholders)"
    );
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $tpInvItems[$row['tp_invoice_id']][$row['product_id']] = $row['quantity'];
    }
    $stmt->close();
}

// ─── Companies for this page ──────────────────────────────────────────────────
$companies  = [];
$companyIds = array_unique(array_merge(
    array_column($userInvoices, 'from_user_id'),
    array_column($customerInvoices, 'user_id'),
    array_filter(array_column($tpInvoices, 'source_godown_id'))
));
if (!empty($companyIds)) {
    $placeholders = implode(',', array_fill(0, count($companyIds), '?'));
    $stmt         = $db_conn->prepare(
        "SELECT id, gname FROM company_godown WHERE id IN ($placeholders) AND " . godown_finance_filter_sql($db_conn)
    );
    $stmt->bind_param(str_repeat('i', count($companyIds)), ...$companyIds);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $companies[$row['id']] = $row['gname'];
    }
    $stmt->close();
}

// ─── B2B user names ───────────────────────────────────────────────────────────
$userNames   = [];
$usersByType = [];
foreach ($userInvoices as $inv) {
    $tbl = match($inv['to_user_type']) {
        'super_stockiest'   => 'super_stockiest',
        'stockiest'         => 'stockiest',
        'super_distributor' => 'super_distributor',
        'distributor'       => 'distributor',
        'outlet'            => 'outlet',
        default             => 'shop',
    };
    $usersByType[$tbl][] = $inv['to_user_id'];
}
foreach ($usersByType as $table => $ids) {
    $ids          = array_unique($ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt         = $db_conn->prepare(
        "SELECT temp_id, name, mobile_number FROM `{$table}` WHERE temp_id IN ($placeholders)"
    );
    $stmt->bind_param(str_repeat('s', count($ids)), ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $userNames[$table][$row['temp_id']] = [
            'name' => $row['name'], 'mobile' => $row['mobile_number'],
        ];
    }
    $stmt->close();
}

// ─── Customer names ───────────────────────────────────────────────────────────
$customerNames = [];
$custIds = array_filter(
    array_unique(array_column($customerInvoices, 'customer_id')),
    fn($id) => (int)$id > 0
);
if (!empty($custIds)) {
    $placeholders = implode(',', array_fill(0, count($custIds), '?'));
    $stmt         = $db_conn->prepare(
        "SELECT id, name, mobile FROM customers WHERE id IN ($placeholders)"
    );
    $stmt->bind_param(str_repeat('i', count($custIds)), ...$custIds);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $customerNames[$row['id']] = ['name' => $row['name'], 'mobile' => $row['mobile']];
    }
    $stmt->close();
}

// ─── Territory Partner names ─────────────────────────────────────────────────
$tpNames = [];
$tpIds   = array_unique(array_column($tpInvoices, 'territory_partner_id'));
if (!empty($tpIds)) {
    $placeholders = implode(',', array_fill(0, count($tpIds), '?'));
    $stmt         = $db_conn->prepare(
        "SELECT id, name, mobile FROM territory_partners WHERE id IN ($placeholders)"
    );
    $stmt->bind_param(str_repeat('i', count($tpIds)), ...$tpIds);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $tpNames[$row['id']] = ['name' => $row['name'], 'mobile' => $row['mobile']];
    }
    $stmt->close();
}

// ─── Grand total (full range, not just page) ──────────────────────────────────
$grandTotal = 0.0;
if ($selectedCategory !== 'customer') {
    $sqlS = "SELECT COALESCE(SUM(total),0) AS s FROM user_invoice
             WHERE date BETWEEN ? AND ? AND from_user_type = ? AND sub_total > 0
               AND (from_user_type != 'company' OR from_user_id IN (" . godown_ids_subquery($db_conn) . "))";
    $tp   = 'sss'; $pr = [$fromDate, $toDate, $Login_user_TYPEvl];
    if (!empty($selectedCategory)) { $sqlS .= " AND to_user_type = ?"; $pr[] = $selectedCategory; $tp .= 's'; }
    $stmt = $db_conn->prepare($sqlS);
    $stmt->bind_param($tp, ...$pr);
    $stmt->execute();
    $grandTotal += (float)$stmt->get_result()->fetch_assoc()['s'];
    $stmt->close();
}
if (empty($selectedCategory) || $selectedCategory === 'customer') {
    $stmt = $db_conn->prepare(
        "SELECT COALESCE(SUM(total),0) AS s FROM invoice
         WHERE date BETWEEN ? AND ? AND user_type = ? AND sub_total > 0"
    );
    $stmt->bind_param('sss', $fromDate, $toDate, $Login_user_TYPEvl);
    $stmt->execute();
    $grandTotal += (float)$stmt->get_result()->fetch_assoc()['s'];
    $stmt->close();
}
if (empty($selectedCategory) || $selectedCategory === 'territory_partner') {
    $stmt = $db_conn->prepare(
        "SELECT COALESCE(SUM(total_amount),0) AS s FROM tp_invoices
         WHERE invoice_date BETWEEN ? AND ? AND created_by_user_type = 'company' AND total_amount > 0
           AND source_godown_id IN (" . godown_ids_subquery($db_conn) . ")"
    );
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $grandTotal += (float)$stmt->get_result()->fetch_assoc()['s'];
    $stmt->close();
}

// ─── Pagination URL builder ───────────────────────────────────────────────────
function buildPageUrl(int $page, string $fromDate, string $toDate, string $category,
                      int $label, int $rptLabel, int $out1, int $out2, string $out3,
                      int $setTrigger, string $search = ''): string
{
    return '?' . http_build_query(array_filter([
        'page'      => $page,
        'frdate'    => $fromDate,
        'todate'    => $toDate,
        'catname'   => $category,
        'lable'     => $label,
        'rptlable'  => $rptLabel,
        'out1'      => $out1,
        'out2'      => $out2,
        'out3'      => $out3,
        'setrigger' => $setTrigger,
        'q'         => $search,
    ]));
}

$serialStart = $globalOffset + 1;

// ─── Active filters for badge display ────────────────────────────────────────
$activeFilters = [];
if (!empty($selectedCategory)) {
    $activeFilters[] = 'Category: ' . (USER_TYPE_LABELS[$selectedCategory] ?? ucfirst($selectedCategory));
}
if ($fromDate !== date('Y-m-d', strtotime('-7 days')) || $toDate !== date('Y-m-d')) {
    $activeFilters[] = 'Date: ' . date('d/m/Y', strtotime($fromDate)) . ' – ' . date('d/m/Y', strtotime($toDate));
}
if ($search !== '') {
    $activeFilters[] = 'Search: "' . e($search) . '"';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($reportLabel) ?> : <?= e($business_name) ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png">

    <style>
        /* ── Table ──────────────────────────────────────────── */
        #overflowon { width: 100%; overflow-x: auto; }

        .table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 600;
            white-space: nowrap;
            font-size: 14px;
            padding: 12px 8px;
            border-color: #dee2e6;
            color: #495057;
        }

        .table td {
            white-space: nowrap;
            font-size: 13px;
            padding: 10px 8px;
            vertical-align: middle;
            border-color: #dee2e6;
        }

        .product-col {
            background: linear-gradient(135deg, #e3f2fd 0%, #f0f8ff 100%);
            text-align: center;
            min-width: 80px;
            font-weight: 500;
        }

        .table-bordered {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0,123,255,0.05);
            transition: background-color 0.2s ease;
        }

        /* ── Cards ──────────────────────────────────────────── */
        .card {
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border-radius: 12px;
        }

        .card-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-bottom: 1px solid #e9ecef;
            border-radius: 12px 12px 0 0 !important;
        }

        /* ── Pagination ──────────────────────────────────────── */
        .pagination .page-link {
            border-radius: 6px;
            margin: 0 2px;
            border: none;
            background: #f8f9fa;
            color: #495057;
            font-weight: 500;
            min-width: 36px;
            text-align: center;
        }

        .pagination .page-item.active .page-link {
            background: #0d6efd;
            color: white;
            box-shadow: 0 2px 4px rgba(13,110,253,0.3);
        }

        .pagination .page-link:hover {
            background: #e9ecef;
            color: #495057;
        }

        .pagination .page-item.disabled .page-link { color: #aaa; }

        /* ── Toolbar ──────────────────────────────────────────── */
        .form-select-sm {
            border-radius: 6px;
            border-color: #ced4da;
            font-size: 13px;
        }

        .toolbar-info {
            font-size: 13px;
            color: #6c757d;
        }

        /* ── Summary widget ──────────────────────────────────── */
        .summary-card td, .summary-card th {
            font-size: 13px;
            padding: 4px 8px;
            border: none;
            white-space: nowrap;
        }

        /* ── Alert / active filters ──────────────────────────── */
        .alert { border-radius: 8px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }

        /* ── tfoot ───────────────────────────────────────────── */
        tfoot tr th {
            background: #e9ecef;
            font-weight: 700;
            font-size: 13px;
        }

        /* ── Responsive ──────────────────────────────────────── */
        @media (max-width: 768px) {
            .table th, .table td { font-size: 12px; padding: 8px 4px; }
            .card-header .d-flex { flex-direction: column; gap: 10px; }
        }
    </style>
</head>

<body>
<div class="app align-content-stretch d-flex flex-wrap">

    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>

    <div class="app-container">
        <?php include("app-header.php"); ?>

        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">

                    <!-- ── Page Header ───────────────────────────────────── -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td><?= e($reportLabel) ?></td>
                                            <td><a href="Report_company">&#8592;&nbsp;Go&nbsp;Back</a></td>
                                            <td>
                                                <a href="export_sales_report.php?<?= http_build_query([
                                                    'frdate'  => $fromDate,
                                                    'todate'  => $toDate,
                                                    'catname' => $selectedCategory,
                                                ]) ?>" title="Export to Excel">
                                                    <img src="../../assets/images/excel-3-32.png" alt="Export">
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- ── Summary Widget (only on first load) ───────────── -->
                    <?php if ($setTrigger === 0): ?>
                    <div class="row mb-3">
                        <div class="col-xl-3">
                            <div class="card widget widget-stats">
                                <div class="card-body">
                                    <div class="widget-stats-content flex-fill">
                                        <span class="widget-stats-title"><?= e($displayLabel) ?></span>
                                        <table class="summary-card w-100 mt-2">
                                            <tr><th>Invoice Count</th><td>: <?= e($out1) ?></td></tr>
                                            <tr><th>Product Qty</th><td>: <?= e($out2) ?></td></tr>
                                            <tr><th>Total Amount</th><td>: &#x20B9;<?= e($out3) ?></td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ── Filters Card ──────────────────────────────────── -->
                    <div class="row mb-3">
                        <div class="col">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Filters</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="<?= e($_SERVER['PHP_SELF']) ?>" id="filterForm">
                                        <input type="hidden" name="csrf_token"  value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="lable"       value="<?= e($label) ?>">
                                        <input type="hidden" name="rptlable"    value="<?= e($rptLabel) ?>">
                                        <input type="hidden" name="out1"        value="<?= e($out1) ?>">
                                        <input type="hidden" name="out2"        value="<?= e($out2) ?>">
                                        <input type="hidden" name="out3"        value="<?= e($out3) ?>">
                                        <input type="hidden" name="setrigger"   value="1">

                                        <div class="row mb-3">
                                            <!-- From Date -->
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">From Date <span class="text-danger">*</span></label>
                                                <input type="date" name="frdate"
                                                       value="<?= e($fromDate) ?>"
                                                       class="form-control" required>
                                            </div>
                                            <!-- To Date -->
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">To Date <span class="text-danger">*</span></label>
                                                <input type="date" name="todate"
                                                       value="<?= e($toDate) ?>"
                                                       class="form-control" required>
                                            </div>
                                            <!-- Category -->
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">Category</label>
                                                <select name="catname" class="form-control">
                                                    <option value="">All Categories</option>
                                                    <?php foreach ([
                                                        'super_stockiest'   => 'Super Stockist',
                                                        'stockiest'         => 'Stockist',
                                                        'distributor'       => 'Distributor',
                                                        'super_distributor' => 'Super Distributor',
                                                        'customer'          => 'Customer',
                                                        'shop'              => 'Shop',
                                                        'territory_partner' => 'Territory Partner',
                                                    ] as $val => $lbl): ?>
                                                        <option value="<?= e($val) ?>"
                                                            <?= $selectedCategory === $val ? 'selected' : '' ?>>
                                                            <?= e($lbl) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <!-- Action buttons -->
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <button type="submit" name="sedatas" class="btn btn-primary">
                                                        <i class="material-icons" style="font-size:16px;vertical-align:middle">search</i>
                                                        Apply Filters
                                                    </button>
                                                    <button type="submit" name="clear_all" class="btn btn-secondary">
                                                        <i class="material-icons" style="font-size:16px;vertical-align:middle">refresh</i>
                                                        Reset
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Active Filters Badge -->
                                        <?php if (!empty($activeFilters)): ?>
                                        <div class="alert alert-success mb-0 py-2">
                                            <strong>Active Filters:</strong>
                                            <?= implode(' &nbsp;|&nbsp; ', array_map('e', $activeFilters)) ?>
                                        </div>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Report Table Card ─────────────────────────────── -->
                    <div class="row">
                        <div class="col">
                            <div class="card">

                                <!-- Toolbar: Show entries + Search + Page info -->
                                <div class="card-header py-3 bg-white border-0">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

                                        <!-- LEFT: Show entries per page -->
                                        <form method="post" action="<?= e($_SERVER['PHP_SELF']) ?>"
                                              class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="csrf_token"         value="<?= e($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="lable"              value="<?= e($label) ?>">
                                            <input type="hidden" name="rptlable"           value="<?= e($rptLabel) ?>">
                                            <input type="hidden" name="frdate"             value="<?= e($fromDate) ?>">
                                            <input type="hidden" name="todate"             value="<?= e($toDate) ?>">
                                            <input type="hidden" name="catname"            value="<?= e($selectedCategory) ?>">
                                            <input type="hidden" name="out1"               value="<?= e($out1) ?>">
                                            <input type="hidden" name="out2"               value="<?= e($out2) ?>">
                                            <input type="hidden" name="out3"               value="<?= e($out3) ?>">
                                            <input type="hidden" name="setrigger"          value="1">
                                            <input type="hidden" name="page"               value="1">
                                            <input type="hidden" name="q"                  value="<?= e($search) ?>">

                                            <label class="mb-0 text-muted">Show:</label>
                                            <select name="records_per_page"
                                                    class="form-select form-select-sm"
                                                    style="width:auto"
                                                    onchange="this.form.submit()">
                                                <?php foreach ($allowedRpp as $rpp): ?>
                                                    <option value="<?= $rpp ?>"
                                                        <?= $recordsPerPage === $rpp ? 'selected' : '' ?>>
                                                        <?= $rpp ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label class="mb-0 text-muted">entries</label>
                                        </form>

                                        <!-- RIGHT: Search + page info -->
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <form method="get" action="<?= e($_SERVER['PHP_SELF']) ?>"
                                                  class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="page"      value="1">
                                                <input type="hidden" name="frdate"    value="<?= e($fromDate) ?>">
                                                <input type="hidden" name="todate"    value="<?= e($toDate) ?>">
                                                <input type="hidden" name="catname"   value="<?= e($selectedCategory) ?>">
                                                <input type="hidden" name="lable"     value="<?= e($label) ?>">
                                                <input type="hidden" name="rptlable"  value="<?= e($rptLabel) ?>">
                                                <input type="hidden" name="setrigger" value="<?= e($setTrigger) ?>">
                                                <input type="text"
                                                       name="q"
                                                       value="<?= e($search) ?>"
                                                       class="form-control form-control-sm"
                                                       placeholder="Search invoice, name, type..."
                                                       style="min-width:240px">
                                            </form>

                                            <div class="toolbar-info">
                                                <?php if ($isSearch): ?>
                                                    Showing all matches
                                                <?php elseif ($totalRows > 0): ?>
                                                    Showing
                                                    <strong><?= inr_format($globalOffset + 1, 0) ?></strong>–<strong><?= inr_format(min($globalOffset + $recordsPerPage, $totalRows), 0) ?></strong>
                                                    of <strong><?= inr_format($totalRows, 0) ?></strong>
                                                    &nbsp;|&nbsp; Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body pt-0">

                                    <div id="divToPrint">
                                        <div id="overflowon">
                                            <table class="table table-bordered table-hover table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>S.No</th>
                                                        <th>Company Profile</th>
                                                        <th>Inv Number</th>
                                                        <th>User Type</th>
                                                        <th>Name</th>
                                                        <th>Date</th>
                                                        <th>Total Amount</th>
                                                        <?php foreach ($products as $pname): ?>
                                                            <th class="product-col"
                                                                title="<?= e($pname) ?>">
                                                                <?= e(strlen($pname) > 20
                                                                    ? substr($pname, 0, 17) . '...'
                                                                    : $pname) ?>
                                                            </th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                <?php
                                                $rowNum    = $serialStart;
                                                $pageTotal = 0.0;
                                                $productPageTotals = array_fill_keys($productIds, 0);

                                                // ── B2B Rows ───────────────────────────────────────
                                                foreach ($userInvoices as $inv):
                                                    $tbl = match($inv['to_user_type']) {
                                                        'super_stockiest'   => 'super_stockiest',
                                                        'stockiest'         => 'stockiest',
                                                        'super_distributor' => 'super_distributor',
                                                        'distributor'       => 'distributor',
                                                        'outlet'            => 'outlet',
                                                        default             => 'shop',
                                                    };
                                                    $typeLabel = USER_TYPE_LABELS[$inv['to_user_type']] ?? ucfirst($inv['to_user_type']);
                                                    $userRow   = $userNames[$tbl][$inv['to_user_id']] ?? [];
                                                    $custName  = $userRow['name']   ?? 'Unknown';
                                                    $custMob   = $userRow['mobile'] ?? '';
                                                    $cmpName   = $companies[$inv['from_user_id']] ?? '';
                                                    $invItems  = $userInvItems[$inv['inv_id']] ?? [];
                                                    $pageTotal += (float)$inv['total'];
                                                ?>
                                                <tr>
                                                    <td><?= $rowNum++ ?></td>
                                                    <td><?= e($cmpName) ?></td>
                                                    <td><?= e($inv['inv_number']) ?></td>
                                                    <td><?= e($typeLabel) ?></td>
                                                    <td>
                                                        <strong><?= e($custName) ?></strong><br>
                                                        <small class="text-muted"><b>M:</b> <?= e($custMob) ?></small>
                                                    </td>
                                                    <td><?= e(date('d/M/Y', strtotime($inv['date']))) ?></td>
                                                    <td align="right"><strong>₹<?= inr_format((float)$inv['total'], 2) ?></strong></td>
                                                    <?php foreach ($productIds as $pid):
                                                        $qty = (int)($invItems[$pid] ?? 0);
                                                        $productPageTotals[$pid] += $qty;
                                                    ?>
                                                        <td align="center" class="product-col">
                                                            <?php if ($qty > 0): ?>
                                                                <strong><?= $qty ?></strong>
                                                            <?php else: ?>
                                                                <span style="color:#ccc">–</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                                <?php endforeach; ?>

                                                <?php
                                                // ── Customer Rows ──────────────────────────────────
                                                foreach ($customerInvoices as $inv):
                                                    $cmpName  = $companies[$inv['user_id']] ?? '';
                                                    if ((int)$inv['customer_id'] === 0) {
                                                        $custName = 'Walking Customer'; $custMob = '';
                                                    } else {
                                                        $custRow  = $customerNames[$inv['customer_id']] ?? [];
                                                        $custName = $custRow['name']   ?? 'Unknown';
                                                        $custMob  = $custRow['mobile'] ?? '';
                                                    }
                                                    $invItems  = $custInvItems[$inv['inv_id']] ?? [];
                                                    $pageTotal += (float)$inv['total'];
                                                ?>
                                                <tr>
                                                    <td><?= $rowNum++ ?></td>
                                                    <td><?= e($cmpName) ?></td>
                                                    <td><?= e($inv['inv_number']) ?></td>
                                                    <td>Customer</td>
                                                    <td>
                                                        <strong><?= e($custName) ?></strong><br>
                                                        <small class="text-muted"><b>M:</b> <?= e($custMob) ?></small>
                                                    </td>
                                                    <td><?= e(date('d/M/Y', strtotime($inv['date']))) ?></td>
                                                    <td align="right"><strong>₹<?= inr_format((float)$inv['total'], 2) ?></strong></td>
                                                    <?php foreach ($productIds as $pid):
                                                        $qty = (int)($invItems[$pid] ?? 0);
                                                        $productPageTotals[$pid] += $qty;
                                                    ?>
                                                        <td align="center" class="product-col">
                                                            <?php if ($qty > 0): ?>
                                                                <strong><?= $qty ?></strong>
                                                            <?php else: ?>
                                                                <span style="color:#ccc">–</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                                <?php endforeach; ?>

                                                <?php
                                                // ── Territory Partner Rows ─────────────────────────
                                                foreach ($tpInvoices as $inv):
                                                    $tpRow    = $tpNames[$inv['territory_partner_id']] ?? [];
                                                    $custName = $tpRow['name']   ?? 'Unknown';
                                                    $custMob  = $tpRow['mobile'] ?? '';
                                                    $cmpName  = $companies[(int)$inv['source_godown_id']] ?? '';
                                                    $invItems  = $tpInvItems[$inv['inv_id']] ?? [];
                                                    $pageTotal += (float)$inv['total'];
                                                ?>
                                                <tr>
                                                    <td><?= $rowNum++ ?></td>
                                                    <td><?= e($cmpName) ?></td>
                                                    <td><?= e($inv['inv_number']) ?></td>
                                                    <td>Territory Partner</td>
                                                    <td>
                                                        <strong><?= e($custName) ?></strong><br>
                                                        <small class="text-muted"><b>M:</b> <?= e($custMob) ?></small>
                                                    </td>
                                                    <td><?= e(date('d/M/Y', strtotime($inv['date']))) ?></td>
                                                    <td align="right"><strong>₹<?= inr_format((float)$inv['total'], 2) ?></strong></td>
                                                    <?php foreach ($productIds as $pid):
                                                        $qty = (int)($invItems[$pid] ?? 0);
                                                        $productPageTotals[$pid] += $qty;
                                                    ?>
                                                        <td align="center" class="product-col">
                                                            <?php if ($qty > 0): ?>
                                                                <strong><?= $qty ?></strong>
                                                            <?php else: ?>
                                                                <span style="color:#ccc">–</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                                <?php endforeach; ?>

                                                <?php if ($totalRows === 0): ?>
                                                <tr>
                                                    <td colspan="<?= 7 + count($products) ?>"
                                                        class="text-center text-muted py-4">
                                                        No records found for the selected filters.
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                                </tbody>

                                                <tfoot>
                                                    <!-- Page total row -->
                                                    <tr>
                                                        <th colspan="6" class="text-end">Page Total:</th>
                                                        <th align="right">₹<?= inr_format($pageTotal, 2) ?></th>
                                                        <?php foreach ($productPageTotals as $t): ?>
                                                            <th align="center" class="product-col"><?= $t ?: '–' ?></th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                    <!-- Grand total row -->
                                                    <tr style="background:#d1ecf1;">
                                                        <th colspan="6" class="text-end">
                                                            Grand Total
                                                            <small class="text-muted fw-normal">
                                                                (all <?= inr_format($totalRows, 0) ?> records)
                                                            </small>
                                                        </th>
                                                        <th align="right">₹<?= inr_format($grandTotal, 2) ?></th>
                                                        <?php foreach ($productIds as $unused): ?>
                                                            <th class="product-col"></th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div><!-- #overflowon -->
                                    </div><!-- #divToPrint -->

                                    <!-- ── Pagination ─────────────────────────────── -->
                                    <?php if ($totalPages > 1):
                                        $urlBase = fn(int $pg) => buildPageUrl(
                                            $pg, $fromDate, $toDate, $selectedCategory,
                                            $label, $rptLabel, $out1, $out2, $out3,
                                            $setTrigger, $search
                                        );

                                        // Windowed page list
                                        $window    = 2;
                                        $showPages = [];
                                        for ($pg = 1; $pg <= $totalPages; $pg++) {
                                            if ($pg === 1 || $pg === $totalPages
                                                || abs($pg - $currentPage) <= $window) {
                                                $showPages[] = $pg;
                                            }
                                        }
                                    ?>
                                    <nav aria-label="Report pagination" class="mt-3">
                                        <ul class="pagination justify-content-center">

                                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= $currentPage > 1 ? e($urlBase(1)) : '#' ?>">
                                                    &#171; First
                                                </a>
                                            </li>
                                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= $currentPage > 1 ? e($urlBase($currentPage - 1)) : '#' ?>">
                                                    Previous
                                                </a>
                                            </li>

                                            <?php
                                            $prevShown = null;
                                            foreach ($showPages as $pg):
                                                if ($prevShown !== null && $pg - $prevShown > 1): ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link">…</span>
                                                    </li>
                                                <?php endif; ?>
                                                <li class="page-item <?= $pg === $currentPage ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= e($urlBase($pg)) ?>"><?= $pg ?></a>
                                                </li>
                                            <?php $prevShown = $pg; endforeach; ?>

                                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= $currentPage < $totalPages ? e($urlBase($currentPage + 1)) : '#' ?>">
                                                    Next
                                                </a>
                                            </li>
                                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= $currentPage < $totalPages ? e($urlBase($totalPages)) : '#' ?>">
                                                    Last &#187;
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                    <?php endif; ?>

                                    <!-- Entry count summary -->
                                    <p class="text-center text-muted mt-2" style="font-size:13px;">
                                        <?php if ($totalRows === 0): ?>
                                            No records found.
                                        <?php elseif ($isSearch): ?>
                                            Showing all <?= inr_format($totalRows, 0) ?> matching entries
                                        <?php else: ?>
                                            Showing <?= inr_format($globalOffset + 1, 0) ?>
                                            to <?= inr_format(min($globalOffset + $recordsPerPage, $totalRows), 0) ?>
                                            of <?= inr_format($totalRows, 0) ?> entries
                                        <?php endif; ?>
                                    </p>

                                </div><!-- card-body -->
                            </div><!-- card -->
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print helper (hidden from paginated view) -->
<script>
    function PrintDiv() {
        var d = document.getElementById('divToPrint');
        var w = window.open('', '_blank', 'width=900,height=640,left=100,top=60');
        w.document.open();
        w.document.write(
            '<html><style>body{font-family:arial;}table{width:100%;border-collapse:collapse;}'
            + 'table th,table td{padding:5px;border:1px solid #000;font-size:12px;}'
            + 'p{font-size:18px;text-align:center;}</style>'
            + '<body onload="window.print()">' + d.innerHTML + '</html>'
        );
        w.document.close();
    }
</script>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/highlight/highlight.pack.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>