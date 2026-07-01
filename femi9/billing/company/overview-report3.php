<?php
declare(strict_types=1);

ob_start();

include("checksession.php");
include("config.php");

error_reporting(E_ALL);          // turn ON during debug — set back to 0 in production
ini_set('display_errors', '1');  // never display — log only
ini_set('log_errors', '1');

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Safe HTML output — always use this when echoing user/db data into HTML.
 */
function esc(mixed $val): string
{
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
}

function sanitizeInt(mixed $value, int $default = 0): int
{
    $filtered = filter_var($value ?? $default, FILTER_VALIDATE_INT);
    return ($filtered !== false) ? (int)$filtered : $default;
}

function sanitizeDate(mixed $value): string
{
    $date = trim((string)($value ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false) {
        return $date;
    }
    return date('Y-m-d');
}

/**
 * Safe mysqli prepared query helper — works with both mysqlnd and non-mysqlnd.
 * Returns array of assoc rows.
 */
function dbQuery(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log('DB prepare error: ' . $db->error . ' | SQL: ' . $sql);
        return [];
    }
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log('DB execute error: ' . $stmt->error . ' | SQL: ' . $sql);
        $stmt->close();
        return [];
    }

    // ── Compatibility: works without mysqlnd ──────────────────────────────────
    $result = $stmt->get_result();
    if ($result === false) {
        // Fallback for non-mysqlnd: use bind_result + fetch
        $meta    = $stmt->result_metadata();
        $fields  = [];
        $row     = [];
        $refs    = [];
        while ($field = $meta->fetch_field()) {
            $fields[] = $field->name;
            $refs[]   = &$row[$field->name];
        }
        call_user_func_array([$stmt, 'bind_result'], $refs);
        $rows = [];
        while ($stmt->fetch()) {
            $copy = [];
            foreach ($fields as $f) {
                $copy[$f] = $row[$f];
            }
            $rows[] = $copy;
        }
        $stmt->close();
        return $rows;
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Single-value shortcut — returns first column of first row, or $default.
 */
function dbScalar(mysqli $db, string $sql, string $types = '', array $params = [], mixed $default = 0): mixed
{
    $rows = dbQuery($db, $sql, $types, $params);
    if (empty($rows)) return $default;
    return reset($rows[0]);
}

// ─── Clear Filters ────────────────────────────────────────────────────────────
if (isset($_POST['clear_all'])) {
    $keys = [
        'ot_report_from_date', 'ot_report_to_date',
        'ot_report_catname', 'ot_report_records_per_page',
        'ot_report_search',
    ];
    foreach ($keys as $key) {
        unset($_SESSION[$key]);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        http_response_code(403);
        die('Invalid request token. Please refresh and try again.');
    }
}

// ─── Records per page ─────────────────────────────────────────────────────────
$allowedRpp     = [20, 40, 60];
$recordsPerPage = 20;

if (isset($_POST['records_per_page'])) {
    $rpp = (int)$_POST['records_per_page'];
    $recordsPerPage = in_array($rpp, $allowedRpp, true) ? $rpp : 20;
    $_SESSION['ot_report_records_per_page'] = $recordsPerPage;
} elseif (isset($_SESSION['ot_report_records_per_page'])) {
    $recordsPerPage = (int)$_SESSION['ot_report_records_per_page'];
}

// ─── Dates ────────────────────────────────────────────────────────────────────
$defaultFrom = date('Y-m-d', strtotime('-7 days'));
$defaultTo   = date('Y-m-d');

if (isset($_POST['frdate']) && $_POST['frdate'] !== '') {
    $fromDate = sanitizeDate($_POST['frdate']);
    $_SESSION['ot_report_from_date'] = $fromDate;
} elseif (isset($_SESSION['ot_report_from_date'])) {
    $fromDate = $_SESSION['ot_report_from_date'];
} else {
    // Accept from GET on first load (coming from dashboard link)
    $fromDate = sanitizeDate($_REQUEST['frdate'] ?? $defaultFrom);
}

if (isset($_POST['todate']) && $_POST['todate'] !== '') {
    $toDate = sanitizeDate($_POST['todate']);
    $_SESSION['ot_report_to_date'] = $toDate;
} elseif (isset($_SESSION['ot_report_to_date'])) {
    $toDate = $_SESSION['ot_report_to_date'];
} else {
    $toDate = sanitizeDate($_REQUEST['todate'] ?? $defaultTo);
}

// ─── Category ─────────────────────────────────────────────────────────────────
if (isset($_POST['catname'])) {
    $catname = trim((string)$_POST['catname']);
    $_SESSION['ot_report_catname'] = $catname;
} elseif (isset($_SESSION['ot_report_catname'])) {
    $catname = $_SESSION['ot_report_catname'];
} else {
    $catname = trim((string)($_REQUEST['catname'] ?? ''));
}
// Strip any HTML/special chars from category name
$catname = strip_tags(trim($catname));

// ─── Other request params ─────────────────────────────────────────────────────
$label      = sanitizeInt($_REQUEST['lable']     ?? 0);
$rptLabel   = sanitizeInt($_REQUEST['rptlable']  ?? 0);
$out1       = sanitizeInt($_REQUEST['out1']      ?? 0);
$out2       = sanitizeInt($_REQUEST['out2']      ?? 0);
$out3       = strip_tags(trim((string)($_REQUEST['out3'] ?? '0')));
$setTrigger = sanitizeInt($_REQUEST['setrigger'] ?? 0);

// ─── Universal Search ─────────────────────────────────────────────────────────
$search = '';
if (isset($_GET['q'])) {
    $search = trim((string)$_GET['q']);
    $_SESSION['ot_report_search'] = $search;
} elseif (isset($_POST['q'])) {
    $search = trim((string)$_POST['q']);
    $_SESSION['ot_report_search'] = $search;
} elseif (isset($_SESSION['ot_report_search'])) {
    $search = (string)$_SESSION['ot_report_search'];
}
$isSearch = ($search !== '');
$qParam   = urlencode($search);

// ─── Display labels ───────────────────────────────────────────────────────────
$displayLabel = 'Last Month';
if ($label === 1 && $rptLabel === 1)      $displayLabel = 'Today';
elseif ($label === 2 && $rptLabel === 1)  $displayLabel = 'Yesterday';
elseif ($label === 3 && $rptLabel === 1)  $displayLabel = 'This Month';

$reportLabel = 'Channelwise Sales';

// ─── Load categories from DB ──────────────────────────────────────────────────
$categories = [];
$catRows    = dbQuery($db_conn, "SELECT cat FROM ot_cat ORDER BY cat ASC");
foreach ($catRows as $row) {
    $categories[] = $row['cat'];
}

// Whitelist catname against real DB values
if ($catname !== '' && !in_array($catname, $categories, true)) {
    $catname = '';
}

// ─── Load products ────────────────────────────────────────────────────────────
$products   = [];
$productIds = [];
$prRows     = dbQuery($db_conn, "SELECT id, productName FROM products ORDER BY id ASC");
foreach ($prRows as $row) {
    $products[(int)$row['id']] = $row['productName'];
    $productIds[]              = (int)$row['id'];
}

// ─── Build shared WHERE clause parts ─────────────────────────────────────────
/**
 * All COUNT, DISTINCT tempid, and grand total queries share the same
 * WHERE conditions. Build them once to avoid repetition and drift.
 */
$whereSql    = " WHERE date BETWEEN ? AND ?";
$whereTypes  = 'ss';
$whereParams = [$fromDate, $toDate];

if ($catname !== '') {
    $whereSql    .= " AND cat = ?";
    $whereTypes  .= 's';
    $whereParams[] = $catname;
}

if ($isSearch) {
    $like            = '%' . $search . '%';
    $whereSql       .= " AND (customer_name LIKE ? OR customer_mobile LIKE ? OR order_number LIKE ?)";
    $whereTypes     .= 'sss';
    $whereParams[]   = $like;
    $whereParams[]   = $like;
    $whereParams[]   = $like;
}

// ─── COUNT total distinct tempids ────────────────────────────────────────────
$totalRows = (int)dbScalar(
    $db_conn,
    "SELECT COUNT(DISTINCT tempid) AS cnt FROM ot_sales" . $whereSql,
    $whereTypes,
    $whereParams,
    0
);

$totalPages  = max(1, (int)ceil($totalRows / $recordsPerPage));
$currentPage = max(1, min(sanitizeInt($_GET['page'] ?? 1), $totalPages));
$offset      = ($currentPage - 1) * $recordsPerPage;
$serialStart = $offset + 1;

// ─── Fetch this page's tempids ────────────────────────────────────────────────
$pageSql    = "SELECT tempid, MIN(date) AS first_date
                 FROM ot_sales" . $whereSql
            . " GROUP BY tempid ORDER BY first_date DESC LIMIT ? OFFSET ?";
$pageTypes  = $whereTypes . 'ii';
$pageParams = array_merge($whereParams, [$recordsPerPage, $offset]);

$tidRows = dbQuery($db_conn, $pageSql, $pageTypes, $pageParams);
$tempids = array_column($tidRows, 'tempid');

// ─── Bulk load all data for this page's tempids ───────────────────────────────
$orderHeaders    = [];
$orderTotals     = [];
$orderProductQty = [];
$companies       = [];

if (!empty($tempids)) {
    $ph    = implode(',', array_fill(0, count($tempids), '?'));
    $ttype = str_repeat('s', count($tempids));

    // Order headers — one representative row per tempid
    $hdrRows = dbQuery($db_conn,
        "SELECT
             tempid,
             ANY_VALUE(godownid)          AS godownid,
             ANY_VALUE(cat)               AS cat,
             ANY_VALUE(date)              AS date,
             ANY_VALUE(order_number)      AS order_number,
             ANY_VALUE(customer_name)     AS customer_name,
             ANY_VALUE(customer_mobile)   AS customer_mobile,
             ANY_VALUE(customer_address)  AS customer_address
         FROM ot_sales
         WHERE tempid IN ($ph)
         GROUP BY tempid
         ORDER BY ANY_VALUE(date) DESC",
        $ttype, $tempids
    );
    foreach ($hdrRows as $row) {
        $orderHeaders[$row['tempid']] = $row;
    }

    // Totals per tempid
    $totRows = dbQuery($db_conn,
        "SELECT tempid, SUM(total) AS total_amount
         FROM ot_sales
         WHERE tempid IN ($ph)
         GROUP BY tempid",
        $ttype, $tempids
    );
    foreach ($totRows as $row) {
        $orderTotals[$row['tempid']] = (float)$row['total_amount'];
    }

    // Product quantities per tempid
    $qtyRows = dbQuery($db_conn,
        "SELECT tempid, prid, qty
         FROM ot_sales
         WHERE tempid IN ($ph)",
        $ttype, $tempids
    );
    foreach ($qtyRows as $row) {
        $orderProductQty[$row['tempid']][(int)$row['prid']] = (int)$row['qty'];
    }

    // Company names — bulk load
    $godownIds = [];
    foreach ($orderHeaders as $hdr) {
        if (!empty($hdr['godownid'])) {
            $godownIds[] = (int)$hdr['godownid'];
        }
    }
    $godownIds = array_unique($godownIds);

    if (!empty($godownIds)) {
        $gph   = implode(',', array_fill(0, count($godownIds), '?'));
        $gtype = str_repeat('i', count($godownIds));
        $cmpRows = dbQuery($db_conn,
            "SELECT id, gname FROM company_godown WHERE id IN ($gph)",
            $gtype, $godownIds
        );
        foreach ($cmpRows as $row) {
            $companies[(int)$row['id']] = $row['gname'];
        }
    }
}

// ─── Grand total (full date range, not just page) ─────────────────────────────
$grandTotal = (float)dbScalar(
    $db_conn,
    "SELECT COALESCE(SUM(total), 0) AS s FROM ot_sales" . $whereSql,
    $whereTypes,
    $whereParams,
    0
);

// ─── Pagination URL builder ───────────────────────────────────────────────────
/**
 * Builds paginated URLs that preserve ALL current filter state.
 * Does NOT use array_filter — keeps zero values intact.
 */
function buildPageUrl(
    int $page, string $fromDate, string $toDate, string $catname,
    int $label, int $rptLabel, int $out1, int $out2, string $out3,
    int $setTrigger, string $search = ''
): string {
    $params = [
        'page'      => $page,
        'frdate'    => $fromDate,
        'todate'    => $toDate,
        'lable'     => $label,
        'rptlable'  => $rptLabel,
        'out1'      => $out1,
        'out2'      => $out2,
        'out3'      => $out3,
        'setrigger' => $setTrigger,
    ];
    // Only add optional params if they have a value
    if ($catname !== '') $params['catname'] = $catname;
    if ($search !== '')  $params['q']       = $search;

    return '?' . http_build_query($params);
}

// ─── Active filters list ──────────────────────────────────────────────────────
$activeFilters = [];
if ($catname !== '') {
    $activeFilters[] = 'Category: ' . $catname;
}
if ($fromDate !== $defaultFrom || $toDate !== $defaultTo) {
    $activeFilters[] = 'Date: '
        . date('d/m/Y', strtotime($fromDate))
        . ' – '
        . date('d/m/Y', strtotime($toDate));
}
if ($search !== '') {
    $activeFilters[] = 'Search: "' . esc($search) . '"';
}

// ─── PDF export URL ───────────────────────────────────────────────────────────
$pdfUrl = 'ot_sales_pdf?' . http_build_query([
    'frdate'   => $fromDate,
    'todate'   => $toDate,
    'lable'    => $label,
    'rptlable' => $rptLabel,
    'out1'     => $out1,
    'out2'     => $out2,
    'out3'     => $out3,
    'cat'      => base64_encode($catname),
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($reportLabel) ?> : <?= esc($business_name) ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png">

    <style>
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

        .table-hover tbody tr:hover {
            background-color: rgba(0,123,255,0.05);
            transition: background-color 0.2s ease;
        }

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

        .pagination .page-link:hover { background: #e9ecef; }
        .pagination .page-item.disabled .page-link { color: #aaa; }

        .toolbar-info { font-size: 13px; color: #6c757d; }

        .summary-card td, .summary-card th {
            font-size: 13px;
            padding: 4px 8px;
            border: none;
            white-space: nowrap;
        }

        .alert { border-radius: 8px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }

        tfoot tr th { background: #e9ecef; font-weight: 700; font-size: 13px; }

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
                                            <td><?= esc($reportLabel) ?></td>
                                            <td><a href="Report_company">&#8592;&nbsp;Go&nbsp;Back</a></td>
                                            <td>
                                                <a href="<?= esc($pdfUrl) ?>"
                                                   title="Export PDF" target="_blank">
                                                    <img src="32-pdf.png" alt="PDF Export">
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>


                    <!-- ── Filters Card ──────────────────────────────────── -->
                    <div class="row mb-3">
                        <div class="col">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Filters</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post"
                                          action="<?= esc($_SERVER['PHP_SELF']) ?>"
                                          id="filterForm">

                                        <input type="hidden" name="csrf_token"
                                               value="<?= esc($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="lable"
                                               value="<?= esc($label) ?>">
                                        <input type="hidden" name="rptlable"
                                               value="<?= esc($rptLabel) ?>">
                                        <input type="hidden" name="out1"
                                               value="<?= esc($out1) ?>">
                                        <input type="hidden" name="out2"
                                               value="<?= esc($out2) ?>">
                                        <input type="hidden" name="out3"
                                               value="<?= esc($out3) ?>">
                                        <input type="hidden" name="setrigger" value="1">

                                        <div class="row mb-3">
                                            <!-- From Date -->
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">
                                                    From Date <span class="text-danger">*</span>
                                                </label>
                                                <input type="date" name="frdate"
                                                       value="<?= esc($fromDate) ?>"
                                                       class="form-control" required>
                                            </div>
                                            <!-- To Date -->
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">
                                                    To Date <span class="text-danger">*</span>
                                                </label>
                                                <input type="date" name="todate"
                                                       value="<?= esc($toDate) ?>"
                                                       class="form-control" required>
                                            </div>
                                            <!-- Category -->
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">Category</label>
                                                <select name="catname" class="form-control">
                                                    <option value="">All Categories</option>
                                                    <?php foreach ($categories as $cat): ?>
                                                        <option value="<?= esc($cat) ?>"
                                                            <?= ($catname === $cat) ? 'selected' : '' ?>>
                                                            <?= esc($cat) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <!-- Buttons -->
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <button type="submit" name="sedatas"
                                                            class="btn btn-primary">
                                                        <i class="material-icons"
                                                           style="font-size:16px;vertical-align:middle">
                                                            search
                                                        </i>
                                                        Apply Filters
                                                    </button>
                                                    <button type="submit" name="clear_all"
                                                            class="btn btn-secondary">
                                                        <i class="material-icons"
                                                           style="font-size:16px;vertical-align:middle">
                                                            refresh
                                                        </i>
                                                        Reset
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Active Filters Badge -->
                                        <?php if (!empty($activeFilters)): ?>
                                        <div class="alert alert-success mb-0 py-2">
                                            <strong>Active Filters:</strong>
                                            <?php
                                            $escaped = array_map('esc', $activeFilters);
                                            echo implode(' &nbsp;|&nbsp; ', $escaped);
                                            ?>
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

                                <!-- Toolbar -->
                                <div class="card-header py-3 bg-white border-0">
                                    <div class="d-flex align-items-center justify-content-between
                                                flex-wrap gap-2">

                                        <!-- LEFT: Show entries -->
                                        <form method="post"
                                              action="<?= esc($_SERVER['PHP_SELF']) ?>"
                                              class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= esc($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="lable"
                                                   value="<?= esc($label) ?>">
                                            <input type="hidden" name="rptlable"
                                                   value="<?= esc($rptLabel) ?>">
                                            <input type="hidden" name="frdate"
                                                   value="<?= esc($fromDate) ?>">
                                            <input type="hidden" name="todate"
                                                   value="<?= esc($toDate) ?>">
                                            <input type="hidden" name="catname"
                                                   value="<?= esc($catname) ?>">
                                            <input type="hidden" name="out1"
                                                   value="<?= esc($out1) ?>">
                                            <input type="hidden" name="out2"
                                                   value="<?= esc($out2) ?>">
                                            <input type="hidden" name="out3"
                                                   value="<?= esc($out3) ?>">
                                            <input type="hidden" name="setrigger" value="1">
                                            <input type="hidden" name="page"      value="1">
                                            <input type="hidden" name="q"
                                                   value="<?= esc($search) ?>">

                                            <label class="mb-0 text-muted">Show:</label>
                                            <select name="records_per_page"
                                                    class="form-select form-select-sm"
                                                    style="width:auto"
                                                    onchange="this.form.submit()">
                                                <?php foreach ($allowedRpp as $rpp): ?>
                                                    <option value="<?= $rpp ?>"
                                                        <?= ($recordsPerPage === $rpp) ? 'selected' : '' ?>>
                                                        <?= $rpp ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label class="mb-0 text-muted">entries</label>
                                        </form>

                                        <!-- RIGHT: Search + page info -->
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <form method="get"
                                                  action="<?= esc($_SERVER['PHP_SELF']) ?>"
                                                  class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="page"
                                                       value="1">
                                                <input type="hidden" name="frdate"
                                                       value="<?= esc($fromDate) ?>">
                                                <input type="hidden" name="todate"
                                                       value="<?= esc($toDate) ?>">
                                                <input type="hidden" name="catname"
                                                       value="<?= esc($catname) ?>">
                                                <input type="hidden" name="lable"
                                                       value="<?= esc($label) ?>">
                                                <input type="hidden" name="rptlable"
                                                       value="<?= esc($rptLabel) ?>">
                                                <input type="hidden" name="setrigger"
                                                       value="<?= esc($setTrigger) ?>">
                                                <input type="text"
                                                       name="q"
                                                       value="<?= esc($search) ?>"
                                                       class="form-control form-control-sm"
                                                       placeholder="Search order no, customer, mobile..."
                                                       style="min-width:240px">
                                            </form>

                                            <div class="toolbar-info">
                                                <?php if ($isSearch): ?>
                                                    Showing all matches
                                                <?php elseif ($totalRows > 0): ?>
                                                    Showing
                                                    <strong><?= number_format($offset + 1) ?></strong>
                                                    –
                                                    <strong><?= number_format(min($offset + $recordsPerPage, $totalRows)) ?></strong>
                                                    of <strong><?= number_format($totalRows) ?></strong>
                                                    &nbsp;|&nbsp; Page
                                                    <strong><?= $currentPage ?></strong>
                                                    of <strong><?= $totalPages ?></strong>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- card-header -->

                                <div class="card-body pt-0">
                                    <div id="overflowon">
                                        <table class="table table-bordered table-hover table-sm">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Company Profile</th>
                                                    <th>Category</th>
                                                    <th>Date</th>
                                                    <th>Order Number</th>
                                                    <th>Customer Name</th>
                                                    <th>Customer Mobile</th>
                                                    <th>Address</th>
                                                    <th>Total Amount</th>
                                                    <?php foreach ($products as $pid => $pname): ?>
                                                        <th class="product-col"
                                                            title="<?= esc($pname) ?>">
                                                            <?= esc(strlen($pname) > 20
                                                                ? substr($pname, 0, 17) . '...'
                                                                : $pname) ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>

                                            <tbody>
                                            <?php if (empty($tempids)): ?>
                                                <tr>
                                                    <td colspan="<?= 9 + count($products) ?>"
                                                        class="text-center text-muted py-4">
                                                        No records found for the selected filters.
                                                    </td>
                                                </tr>
                                            <?php else:
                                                $rowNum            = $serialStart;
                                                $pageTotal         = 0.0;
                                                $productPageTotals = array_fill_keys($productIds, 0);

                                                foreach ($tempids as $tid):
                                                    $hdr    = $orderHeaders[$tid]      ?? [];
                                                    $total  = $orderTotals[$tid]       ?? 0.0;
                                                    $qtyMap = $orderProductQty[$tid]   ?? [];
                                                    $cmpName = $companies[(int)($hdr['godownid'] ?? 0)] ?? '';
                                                    $pageTotal += $total;

                                                    $rowDate = '';
                                                    if (!empty($hdr['date'])) {
                                                        $ts = strtotime($hdr['date']);
                                                        $rowDate = $ts ? date('d/M/Y', $ts) : $hdr['date'];
                                                    }
                                            ?>
                                                <tr>
                                                    <td><?= $rowNum++ ?></td>
                                                    <td><?= esc($cmpName) ?></td>
                                                    <td><?= esc($hdr['cat'] ?? '') ?></td>
                                                    <td><?= esc($rowDate) ?></td>
                                                    <td><?= esc($hdr['order_number'] ?? '') ?></td>
                                                    <td>
                                                        <strong><?= esc($hdr['customer_name'] ?? '') ?></strong>
                                                    </td>
                                                    <td><?= esc($hdr['customer_mobile'] ?? '') ?></td>
                                                    <td><?= esc($hdr['customer_address'] ?? '') ?></td>
                                                    <td align="right">
                                                        <strong><?= number_format($total, 2, '.', '') ?></strong>
                                                    </td>
                                                    <?php foreach ($productIds as $pid):
                                                        $qty = (int)($qtyMap[$pid] ?? 0);
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
                                            <?php endif; ?>
                                            </tbody>

                                            <tfoot>
                                                <!-- Page total -->
                                                <tr>
                                                    <th colspan="8" class="text-end">
                                                        Page Total:
                                                    </th>
                                                    <th align="right">
                                                        <?= number_format($pageTotal, 2, '.', '') ?>
                                                    </th>
                                                    <?php foreach ($productPageTotals as $t): ?>
                                                        <th align="center" class="product-col">
                                                            <?= ($t > 0) ? $t : '–' ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                                <!-- Grand total -->
                                                <tr style="background:#d1ecf1;">
                                                    <th colspan="8" class="text-end">
                                                        Grand Total
                                                        <small class="text-muted fw-normal">
                                                            (all <?= number_format($totalRows) ?> orders)
                                                        </small>
                                                    </th>
                                                    <th align="right">
                                                        <?= number_format($grandTotal, 2, '.', '') ?>
                                                    </th>
                                                    <?php foreach ($productIds as $unused): ?>
                                                        <th class="product-col"></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div><!-- #overflowon -->

                                    <!-- ── Pagination ────────────────────────── -->
                                    <?php if ($totalPages > 1):
                                        // Build windowed page list
                                        $window    = 2;
                                        $showPages = [];
                                        for ($pg = 1; $pg <= $totalPages; $pg++) {
                                            if ($pg === 1
                                                || $pg === $totalPages
                                                || abs($pg - $currentPage) <= $window) {
                                                $showPages[] = $pg;
                                            }
                                        }
                                    ?>
                                    <nav aria-label="Report pagination" class="mt-3">
                                        <ul class="pagination justify-content-center">

                                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                   href="<?= $currentPage > 1
                                                       ? esc(buildPageUrl(1, $fromDate, $toDate, $catname, $label, $rptLabel, $out1, $out2, $out3, $setTrigger, $search))
                                                       : '#' ?>">
                                                    &#171; First
                                                </a>
                                            </li>
                                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                   href="<?= $currentPage > 1
                                                       ? esc(buildPageUrl($currentPage - 1, $fromDate, $toDate, $catname, $label, $rptLabel, $out1, $out2, $out3, $setTrigger, $search))
                                                       : '#' ?>">
                                                    Previous
                                                </a>
                                            </li>

                                            <?php
                                            $prevShown = null;
                                            foreach ($showPages as $pg):
                                                if ($prevShown !== null && ($pg - $prevShown) > 1): ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link">…</span>
                                                    </li>
                                                <?php endif; ?>
                                                <li class="page-item <?= $pg === $currentPage ? 'active' : '' ?>">
                                                    <a class="page-link"
                                                       href="<?= esc(buildPageUrl($pg, $fromDate, $toDate, $catname, $label, $rptLabel, $out1, $out2, $out3, $setTrigger, $search)) ?>">
                                                        <?= $pg ?>
                                                    </a>
                                                </li>
                                            <?php $prevShown = $pg; endforeach; ?>

                                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                   href="<?= $currentPage < $totalPages
                                                       ? esc(buildPageUrl($currentPage + 1, $fromDate, $toDate, $catname, $label, $rptLabel, $out1, $out2, $out3, $setTrigger, $search))
                                                       : '#' ?>">
                                                    Next
                                                </a>
                                            </li>
                                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                   href="<?= $currentPage < $totalPages
                                                       ? esc(buildPageUrl($totalPages, $fromDate, $toDate, $catname, $label, $rptLabel, $out1, $out2, $out3, $setTrigger, $search))
                                                       : '#' ?>">
                                                    Last &#187;
                                                </a>
                                            </li>

                                        </ul>
                                    </nav>
                                    <?php endif; ?>

                                    <!-- Entry count -->
                                    <p class="text-center text-muted mt-2" style="font-size:13px;">
                                        <?php if ($totalRows === 0): ?>
                                            No records found.
                                        <?php elseif ($isSearch): ?>
                                            Showing all <?= number_format($totalRows) ?> matching orders
                                        <?php else: ?>
                                            Showing <?= number_format($offset + 1) ?>
                                            to <?= number_format(min($offset + $recordsPerPage, $totalRows)) ?>
                                            of <?= number_format($totalRows) ?> orders
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

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>