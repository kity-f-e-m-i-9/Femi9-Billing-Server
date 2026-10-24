<?php require_once __DIR__ . '/../shared/safe-upload.php';
/**
 * OT Channel Sales — Bulk Import Handler
 *
 * Parses an uploaded Excel file (see ot-sale-import-template.php) and creates
 * ot_sales_invoice / ot_sales rows, mirroring ot-sale-action.php's add-record
 * insert logic. One spreadsheet row = one PRODUCT LINE, not one invoice —
 * rows that share the same Invoice Number + Category are grouped back into a
 * single invoice, so a multi-product invoice is entered as one row per
 * product with the Invoice Number repeated. The same product can't appear
 * twice under one invoice (checked across all of that invoice's rows).
 *
 * Invoice Number has two extra conventions, used only by the
 * tracked-category template (Website/ID Concept/WhatsApp Sales/Digital
 * Orders — see OT_TRACKED_INVOICE_CATS in OtInvoiceNumberGenerator.php):
 * left BLANK, it means "another product line on the invoice directly
 * above"; the literal word "NEXT" means "auto-generate this category's
 * next sequential invoice number", the same convention the manual Add Sale
 * form suggests. Every other category always needs a real typed number.
 *
 * The Product column is matched against the live product list by its short
 * name (see OtImportProducts.php — same label the template's dropdown
 * shows), not a free-typed name, so it's always linked to a real DB record.
 *
 * The whole file is validated (stock availability, required fields, known
 * lookups) before any DB write — if any row fails, nothing is imported.
 */

session_start();

include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
include("config.php");
require_once("include/StockService.php");
require_once("include/GodownAccess.php");
include("RemoveSpecialChar.php");
require_once("include/OtImportProducts.php");
require_once("include/OtInvoiceNumberGenerator.php");
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

date_default_timezone_set("Asia/Kolkata");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Indian financial year (Apr–Mar) for a given date, e.g. 2026-07-22 -> "26-27".
// Same convention as ot-invoice-helper.php's numbering — used here to scope
// the duplicate-invoice-number check so a number legitimately reused in a
// later financial year isn't rejected as a clash with an old invoice.
function ot_import_financial_year(string $ymd): string {
    $ts = strtotime($ymd);
    $month = (int)date('n', $ts);
    $year  = (int)date('Y', $ts);
    $fyStartYear = $month >= 4 ? $year : $year - 1;
    return substr((string)$fyStartYear, -2) . '-' . substr((string)($fyStartYear + 1), -2);
}

function ot_import_back($params) {
    $query = http_build_query($params);
    header("Location: ot-sale-import.php" . ($query ? "?$query" : ""));
    exit;
}

if (empty($_SESSION['LOGIN_USER'])) {
    ot_import_back(['err' => 'Please login first']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ot_import_back(['err' => 'Invalid request method']);
}

$default_godownid = (int)($_POST['default_godownid'] ?? 0);
$default_cat      = trim($_POST['default_cat'] ?? '');

if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['errorMessage'] = 'File upload failed. Please try again.';
    ot_import_back([]);
}

$original_name = $_FILES['import_file']['name'];
$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls'], true)) {
    $_SESSION['errorMessage'] = 'Only .xlsx or .xls files are allowed';
    ot_import_back([]);
}

$upload_dir = __DIR__ . '/ot_sale_import_uploads';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
$stored_name = 'otimp_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$stored_path = $upload_dir . '/' . $stored_name;

if (!safe_move_uploaded_file($_FILES['import_file']['tmp_name'], $stored_path)) {
    $_SESSION['errorMessage'] = 'Could not save uploaded file';
    ot_import_back([]);
}

try {
    $spreadsheet = IOFactory::load($stored_path);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);
} catch (Exception $e) {
    error_log("OT sale import parse error: " . $e->getMessage());
    @unlink($stored_path);
    $_SESSION['errorMessage'] = 'Could not read the uploaded file. Please confirm it is a valid Excel file.';
    ot_import_back([]);
}

// ---------------------------------------------------------------------
// Locate header row (by "Company Profile" marker) and map fixed columns.
// ---------------------------------------------------------------------
$fixedFieldMap = [
    'company profile'  => 'godown',
    'warehouse code'   => 'warehouse_code',
    'category'         => 'cat',
    'date'             => 'date',
    'invoice number'   => 'inv_number',
    'customer name'    => 'customer_name',
    'customer mobile'  => 'customer_mobile',
    'customer address' => 'customer_address',
    'shipping address' => 'shipping_address',
    'gst number'       => 'gst_number',
    'order number'     => 'order_number',
    'order date'       => 'order_date',
    'ship date'        => 'ship_date',
    'courier charges'  => 'courier_charges',
    'state'            => 'state',
    'coupon code'      => 'coupon_code',
    'wallet amount'    => 'wallet_amount',
    'product'          => 'product_name',
    'qty'              => 'qty',
    'rate'             => 'rate',
    'discount'         => 'discount',
];

function ot_import_normalize_header($raw) {
    $s = strtolower(trim((string)$raw));
    $s = str_replace('*', '', $s);
    $s = preg_replace('/\(.*?\)/', '', $s);
    return trim($s);
}

$header_row_num = null;
foreach ($rows as $rnum => $row) {
    foreach ($row as $val) {
        if (ot_import_normalize_header($val) === 'company profile') {
            $header_row_num = $rnum;
            break 2;
        }
    }
}

if ($header_row_num === null) {
    @unlink($stored_path);
    $_SESSION['errorMessage'] = "Could not find a 'Company Profile' header column — please use the provided sample template.";
    ot_import_back([]);
}

$colToFixedField = [];

$headerRowData = $rows[$header_row_num];
$colKeys = array_keys($headerRowData);

foreach ($colKeys as $idx => $col) {
    $rawHeader = (string)($headerRowData[$col] ?? '');
    $norm = ot_import_normalize_header($rawHeader);

    if (isset($fixedFieldMap[$norm])) {
        $colToFixedField[$col] = $fixedFieldMap[$norm];
    }
}

if (!in_array('inv_number', $colToFixedField, true)) {
    @unlink($stored_path);
    $_SESSION['errorMessage'] = "The template is missing the required 'Invoice Number' column.";
    ot_import_back([]);
}

if (!in_array('product_name', $colToFixedField, true) || !in_array('qty', $colToFixedField, true) || !in_array('rate', $colToFixedField, true)) {
    @unlink($stored_path);
    $_SESSION['errorMessage'] = "The template is missing the required 'Product' / 'Qty' / 'Rate' column — please use the provided sample template.";
    ot_import_back([]);
}

// Live product list, keyed by the same short name the template's Product
// dropdown shows (see OtImportProducts.php) — case-insensitive match.
$productByShortName = [];
$productById = [];
foreach (ot_import_active_products($db_conn) as $p) {
    $productByShortName[strtolower(trim($p['short_name']))] = $p;
    $productById[(int)$p['id']] = $p;
}

// ---------------------------------------------------------------------
// Other lookups: godowns by name, categories allowed to this user, products
// by id (for gst/hsn), states by name.
// ---------------------------------------------------------------------
$godownByName = [];
$res = $db_conn->query("SELECT id, gname FROM company_godown");
while ($row = $res->fetch_assoc()) {
    $godownByName[strtolower(trim($row['gname']))] = (int)$row['id'];
}

// Optional per-invoice physical warehouse (Warehouse Code column) — same
// "Godown (physical)" concept the manual Add Sale form offers. Left blank,
// stock is checked/deducted against the untracked (no-warehouse) stock row,
// same as before this column existed.
$warehouseByCode = [];
$res = $db_conn->query("SELECT id, code FROM warehouses WHERE is_active = 1");
while ($row = $res->fetch_assoc()) {
    $warehouseByCode[strtolower(trim($row['code']))] = (int)$row['id'];
}

// Which warehouses each company profile is actually linked to — a typo'd or
// unrelated code is rejected rather than silently checking/deducting the
// wrong location's stock.
$warehousesByGodown = [];
$res = $db_conn->query("SELECT company_godown_id, warehouse_id FROM company_godown_warehouses");
while ($row = $res->fetch_assoc()) {
    $warehousesByGodown[(int)$row['company_godown_id']][] = (int)$row['warehouse_id'];
}

$allowedCats = [];
$usertype   = $Result_Log_users_Dtails134['usertype'] ?? '';
$login_user = $_SESSION['LOGIN_USER'] ?? '';
if ($usertype === 'admin') {
    $res = $db_conn->query("SELECT cat FROM ot_cat");
    while ($row = $res->fetch_assoc()) { $allowedCats[strtolower(trim($row['cat']))] = $row['cat']; }
} else {
    $stmt = $db_conn->prepare("SELECT oc.cat FROM admin_log_ot alo JOIN ot_cat oc ON oc.id = alo.ot_cat WHERE alo.username = ?");
    $stmt->bind_param('s', $login_user);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $allowedCats[strtolower(trim($row['cat']))] = $row['cat']; }
    $stmt->close();
}

$stateByName = [];
$res = $db_conn->query("SELECT id, st_name FROM state");
while ($row = $res->fetch_assoc()) {
    $stateByName[strtolower(trim($row['st_name']))] = (int)$row['id'];
}
$admin_state_id = $Result_Log_users_Dtails134['state_id'] ?? 0;

// Wallet Amount only applies to these categories — same restriction as
// ot-sale-add.php / ot-sale-action.php; silently ignored (treated as 0) for
// any other category rather than rejecting the row.
$wallet_eligible_cats = ['website', 'id concept'];

// ---------------------------------------------------------------------
// Parse + validate rows. One row = one invoice group.
// ---------------------------------------------------------------------
function ot_import_val($rowData, $colToField, $field, $default = '') {
    foreach ($colToField as $col => $f) {
        if ($f === $field) {
            $v = $rowData[$col] ?? null;
            return ($v === null) ? $default : trim((string)$v);
        }
    }
    return $default;
}

$row_nums = array_keys($rows);
$data_start_index = array_search($header_row_num, $row_nums, true) + 1;

$errors = [];
$groups = []; // groupKey => invoice group
$rowLabel = 0;
// Tracks the last invoice number used per "cat|godownid|napkin-or-diaper" key
// so a run of "NEXT" rows for the same tracked category + company continues
// sequentially within this file, instead of re-querying the database (which
// hasn't been written to yet mid-parse) for every row.
$categoryPointer = [];
// groupKey of the most recently appended-to invoice — a row with a BLANK
// Invoice Number continues this one (another product line on the same
// invoice) instead of starting a new one. This is how the tracked-category
// template signals "same invoice, no number to repeat" once its Invoice
// Number column stops auto-carrying down (see ot-sale-import-template.php).
$lastGroupKey = null;

for ($idx = $data_start_index; $idx < count($row_nums); $idx++) {
    $rnum = $row_nums[$idx];
    $rowData = $rows[$rnum];

    $inv_number = ot_import_val($rowData, $colToFixedField, 'inv_number');

    // Skip rows with no product on them — the template's invoice-level
    // columns (Invoice Number included) auto-carry a formula copying the row
    // above, so an unused trailing row can show a stale Invoice Number with
    // no Product/Qty of its own; only Product+Qty blank reliably means
    // "nothing was entered on this row".
    $productNameRaw = ot_import_val($rowData, $colToFixedField, 'product_name');
    $qtyRaw = ot_import_val($rowData, $colToFixedField, 'qty');
    if ($productNameRaw === '' && $qtyRaw === '') {
        continue;
    }

    $rowLabel++;
    $excelRowLabel = "Row $rowLabel (Invoice Number: " . ($inv_number !== '' ? $inv_number : '?') . ")";

    $godownName = ot_import_val($rowData, $colToFixedField, 'godown');
    $godownid = 0;
    if ($godownName !== '') {
        $godownid = $godownByName[strtolower($godownName)] ?? 0;
        if (!$godownid) {
            $errors[] = "$excelRowLabel: unknown Company Profile '$godownName' [Column: Company Profile]";
            continue;
        }
    } elseif ($default_godownid > 0) {
        $godownid = $default_godownid;
    } else {
        $errors[] = "$excelRowLabel: Company Profile is required (or set a Default Company Profile) [Column: Company Profile]";
        continue;
    }

    if (!is_godown_allowed($db_conn, $godownid)) {
        $errors[] = "$excelRowLabel: you are not authorized to use this company profile";
        continue;
    }

    // Optional physical warehouse — blank means "untracked" (same stock row
    // as before this column existed); a typed code must be a real, active
    // warehouse linked to this row's company profile.
    $warehouseCodeRaw = ot_import_val($rowData, $colToFixedField, 'warehouse_code');
    $warehouseId = null;
    if ($warehouseCodeRaw !== '') {
        $warehouseId = $warehouseByCode[strtolower($warehouseCodeRaw)] ?? null;
        if ($warehouseId === null) {
            $errors[] = "$excelRowLabel: unknown Warehouse Code '$warehouseCodeRaw' [Column: Warehouse Code]";
            continue;
        }
        if (!in_array($warehouseId, $warehousesByGodown[$godownid] ?? [], true)) {
            $errors[] = "$excelRowLabel: Warehouse Code '$warehouseCodeRaw' is not linked to this Company Profile [Column: Warehouse Code]";
            continue;
        }
    }

    $catRaw = ot_import_val($rowData, $colToFixedField, 'cat');
    $catRaw = ($catRaw !== '') ? $catRaw : $default_cat;
    if ($catRaw === '') {
        $errors[] = "$excelRowLabel: Category is required (or set a Default Category) [Column: Category]";
        continue;
    }
    $catKey = strtolower(trim($catRaw));
    if (!isset($allowedCats[$catKey])) {
        $errors[] = "$excelRowLabel: unknown or unauthorized category '$catRaw' [Column: Category]";
        continue;
    }
    $catname = $allowedCats[$catKey];

    // A blank Invoice Number means "another product line on the invoice
    // directly above" (the tracked-category template leaves this column
    // blank for a continuation row instead of auto-carrying a value down —
    // see ot-sale-import-template.php). Only valid when there IS a
    // previous row to continue, for the same category and company — and,
    // as a safety net against forgetting to type NEXT for a genuinely new
    // customer, only if this row's own Customer Mobile (when it has one)
    // actually matches that invoice's. A true continuation row normally
    // carries the same mobile down via formula anyway, so this only ever
    // fires when something doesn't add up.
    $isContinuation = false;
    $isNextInvoice = false;
    if (trim($inv_number) === '') {
        $continueCustomerMobile = ot_import_val($rowData, $colToFixedField, 'customer_mobile');
        if ($lastGroupKey !== null && isset($groups[$lastGroupKey])
            && $groups[$lastGroupKey]['cat'] === $catname
            && $groups[$lastGroupKey]['godownid'] === $godownid
            && $groups[$lastGroupKey]['warehouse_id'] === $warehouseId) {
            if ($continueCustomerMobile !== '' && $continueCustomerMobile !== $groups[$lastGroupKey]['customer_mobile']) {
                $errors[] = "$excelRowLabel: Customer Mobile '$continueCustomerMobile' doesn't match the invoice directly above ('{$groups[$lastGroupKey]['customer_mobile']}') — did you forget to type NEXT to start a new invoice? [Column: Invoice Number]";
                continue;
            }
            $isContinuation = true;
        } else {
            $errors[] = "$excelRowLabel: Invoice Number is required (leave it blank only to add another product to the invoice directly above) [Column: Invoice Number]";
            continue;
        }
    } else {
        $isNextInvoice = (strcasecmp(trim($inv_number), 'NEXT') === 0);
    }

    $dateRaw = ot_import_val($rowData, $colToFixedField, 'date');
    $dateTs = $dateRaw !== '' ? strtotime($dateRaw) : false;
    if ($dateTs === false) {
        $errors[] = "$excelRowLabel: Date is missing or invalid [Column: Date]";
        continue;
    }
    $date = date('Y-m-d', $dateTs);

    $stateName = ot_import_val($rowData, $colToFixedField, 'state');
    $state_id = $stateName !== '' ? ($stateByName[strtolower($stateName)] ?? 0) : 0;
    if ($stateName !== '' && !$state_id) {
        $errors[] = "$excelRowLabel: unknown state '$stateName' [Column: State]";
        continue;
    }

    // Resolve this row's single product line — one product per row;
    // rows sharing the same Invoice Number + Category stack into one
    // invoice below instead of one row carrying every product as a column.
    if ($productNameRaw === '' || $qtyRaw === '') {
        $missingCol = $productNameRaw === '' ? 'Product' : 'Qty';
        $errors[] = "$excelRowLabel: Product and Qty are required on every row [Column: $missingCol]";
        continue;
    }

    $productKey = strtolower(trim($productNameRaw));
    if (!isset($productByShortName[$productKey])) {
        $errors[] = "$excelRowLabel: unknown product '$productNameRaw' — please pick a product from the dropdown list [Column: Product]";
        continue;
    }
    $product = $productByShortName[$productKey];
    $pid = (int)$product['id'];

    $qty = (int)$qtyRaw;
    if ($qty <= 0) {
        $errors[] = "$excelRowLabel: Qty must be greater than 0 for product '{$product['productName']}' [Column: Qty]";
        continue;
    }

    $rate = (float) ot_import_val($rowData, $colToFixedField, 'rate', '0');
    if ($rate <= 0) {
        $errors[] = "$excelRowLabel: Rate must be greater than 0 for product '{$product['productName']}' [Column: Rate]";
        continue;
    }

    $discount = max(0, (float) ot_import_val($rowData, $colToFixedField, 'discount', '0'));

    $line = [
        'product_id' => $pid,
        'gst'        => (float)$product['gst'],
        'gst_type'   => ($product['gst_type'] ?? 'exclusive') === 'inclusive' ? 'inclusive' : 'exclusive',
        'hsn'        => $product['hsn'] ?? '',
        'qty'        => $qty,
        'rate'       => $rate,
        'discount'   => $discount,
    ];

    $rowFy = ot_import_financial_year($date);

    if ($isContinuation) {
        $groupKey = $lastGroupKey;
        $inv_number = $groups[$groupKey]['inv_number'];
    } else {
        // "NEXT" in the Invoice Number column asks for this tracked category's
        // next sequential invoice number instead of a typed-in one — same
        // convention the manual Add Sale form auto-suggests. Only valid for
        // the four tracked categories (see OT_TRACKED_INVOICE_CATS); every
        // other category's invoice number always comes from the marketplace/
        // manual entry itself and can't be auto-generated.
        $isDiaper = ($product['category'] ?? '') === 'diaper';
        $pointerKey = $catKey . '|' . $godownid . '|' . ($isDiaper ? 'diaper' : 'napkin');

        if ($isNextInvoice) {
            if (!in_array($catname, OT_TRACKED_INVOICE_CATS, true)) {
                $errors[] = "$excelRowLabel: 'NEXT' is only supported for Website / ID Concept / WhatsApp Sales / Digital Orders — type the invoice number for '$catname' yourself [Column: Invoice Number]";
                continue;
            }

            if (isset($categoryPointer[$pointerKey])) {
                $generated = ot_import_increment_trailing_number($categoryPointer[$pointerKey]);
                if ($generated === null) {
                    $errors[] = "$excelRowLabel: could not auto-generate the next invoice number — '{$categoryPointer[$pointerKey]}' has no trailing number to increment [Column: Invoice Number]";
                    continue;
                }
            } else {
                $generated = ot_invoice_next_number($db_conn, $catname, $godownid, $isDiaper ? 'diaper' : 'napkin');
                if ($generated === null) {
                    $errors[] = "$excelRowLabel: could not auto-generate an invoice number for '$catname' [Column: Invoice Number]";
                    continue;
                }
            }
            $inv_number = $generated;
        }
        $categoryPointer[$pointerKey] = $inv_number;

        $groupKey = $catKey . '|' . strtolower($inv_number) . '|' . $rowFy;

        if (!isset($groups[$groupKey])) {
            // First row seen for this invoice — reject it if the same Invoice
            // Number already exists for this category IN THE SAME FINANCIAL
            // YEAR as this row's Date. Invoice numbers like "IN-708" are
            // legitimately reused year to year (e.g. Amazon/Flipkart
            // order-derived numbers with no year tag baked in), so a
            // same-numbered invoice from a prior financial year is not a real
            // clash. ot_sales_invoice has no date of its own; the date lives
            // on ot_sales (one value per tempid).
            $stmt = $db_conn->prepare("
                SELECT DISTINCT os.date
                FROM ot_sales_invoice osi
                JOIN ot_sales os ON os.tempid = osi.tempid
                WHERE osi.cat = ? AND osi.inv_number = ?
            ");
            $stmt->bind_param('ss', $catname, $inv_number);
            $stmt->execute();
            $existingDates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $fyClash = false;
            foreach ($existingDates as $ed) {
                if (ot_import_financial_year((string)$ed['date']) === $rowFy) { $fyClash = true; break; }
            }
            if ($fyClash) {
                $errors[] = "$excelRowLabel: Invoice Number '$inv_number' already exists for category '$catname' in FY $rowFy — skipped [Column: Invoice Number]";
                continue;
            }

            $gst_number = RemoveSpecialChar(ot_import_val($rowData, $colToFixedField, 'gst_number'));
            $buyer_gsttype = (strlen($gst_number) === 15) ? 'register' : 'unregister';

            // Wallet Amount only applies to Website/ID Concept categories —
            // same restriction as the manual Add Sale form; other categories
            // force it to 0.
            $wallet_amount = in_array($catKey, $wallet_eligible_cats, true)
                ? max(0, (float) ot_import_val($rowData, $colToFixedField, 'wallet_amount', '0'))
                : 0.00;

            $coupon_code = RemoveSpecialChar(ot_import_val($rowData, $colToFixedField, 'coupon_code'));

            $groups[$groupKey] = [
                'godownid'         => $godownid,
                'warehouse_id'     => $warehouseId,
                'cat'              => $catname,
                'inv_number'       => $inv_number,
                'date'             => $date,
                'customer_name'    => RemoveSpecialChar(ot_import_val($rowData, $colToFixedField, 'customer_name')),
                'customer_mobile'  => RemoveSpecialChar(ot_import_val($rowData, $colToFixedField, 'customer_mobile')),
                'customer_address' => RemoveSpecialChar(ot_import_val($rowData, $colToFixedField, 'customer_address')),
                'shipping_address' => RemoveSpecialChar(ot_import_val($rowData, $colToFixedField, 'shipping_address')),
                'gst_number'       => $gst_number,
                'buyer_gsttype'    => $buyer_gsttype,
                'order_number'     => RemoveSpecialChar(ot_import_val($rowData, $colToFixedField, 'order_number')),
                'order_date'       => (($v = ot_import_val($rowData, $colToFixedField, 'order_date')) !== '' && strtotime($v) !== false) ? date('Y-m-d', strtotime($v)) : '1991-01-01',
                'ship_date'        => (($v = ot_import_val($rowData, $colToFixedField, 'ship_date')) !== '' && strtotime($v) !== false) ? date('Y-m-d', strtotime($v)) : '1991-01-01',
                'courier_charges'  => (float) ot_import_val($rowData, $colToFixedField, 'courier_charges', '0'),
                'state_id'         => $state_id,
                'wallet_amount'    => $wallet_amount,
                'coupon_code'      => $coupon_code,
                'lines'            => [],
                'product_ids'      => [],
            ];
        }
    }

    // Each product can only appear once per invoice — across all of that
    // invoice's rows, not just this one.
    if (in_array($pid, $groups[$groupKey]['product_ids'], true)) {
        $errors[] = "$excelRowLabel: product '{$product['productName']}' is already on invoice '$inv_number' — each product can only appear once per invoice [Column: Product]";
        continue;
    }

    $groups[$groupKey]['product_ids'][] = $pid;
    $groups[$groupKey]['lines'][] = $line;
    $lastGroupKey = $groupKey;
}

if (!empty($errors)) {
    @unlink($stored_path);
    $_SESSION['errorMessage'] = "Import cancelled — please fix the following and re-upload:\n" . implode("\n", array_slice($errors, 0, 30))
        . (count($errors) > 30 ? "\n… and " . (count($errors) - 30) . " more issue(s)." : "");
    ot_import_back([]);
}

if (empty($groups)) {
    @unlink($stored_path);
    $_SESSION['errorMessage'] = 'No valid rows were found in the uploaded file.';
    ot_import_back([]);
}

// ---------------------------------------------------------------------
// Every imported order lands as a Draft (ot_sales_invoice.status='draft'),
// exactly like ot-sale-add.php's "Save as Draft" button — never touching
// stock at import time. The company reviews the drafts on ot-sale-view.php,
// then uses ot-sale-confirm-action.php's "Confirm Selected" to actually
// transfer stock and turn them into real sales, one godown/warehouse-aware
// StockService::otDeduct() per line, at that later point in time (not
// whatever the file happened to show when it was imported). So there is no
// stock pre-validation and no otDeduct() call in this file at all — see
// ot-sale-action.php's own $isDraft branches for the identical pattern.
$isDraft = true;
$createdBy = $_SESSION['LOGIN_USER'] ?? 'system';

// ---------------------------------------------------------------------
// Insert everything in one transaction — mirrors ot-sale-action.php.
// ---------------------------------------------------------------------
function ot_import_gen_tempid(): string {
    $chars = '123456789';
    $rand3 = '';
    for ($i = 0; $i < 3; $i++) { $rand3 .= $chars[random_int(0, strlen($chars) - 1)]; }
    return $rand3 . 'RTST/' . date('dmy') . '/' . date('gis') . mt_rand(100, 999);
}

// Self-migrating — same guard as ot-sale-action.php, in case this is the
// first page in the company's session to need the column.
$_statusCol = $db_conn->query("SHOW COLUMNS FROM ot_sales_invoice LIKE 'status'");
if ($_statusCol && $_statusCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE ot_sales_invoice ADD COLUMN status ENUM('confirmed','draft') NOT NULL DEFAULT 'confirmed' AFTER cat");
}

$db_conn->begin_transaction();
try {
    $invoiceCount = 0;
    $lineCount = 0;

    foreach ($groups as $g) {
        $tempid = ot_import_gen_tempid();

        $stmt = $db_conn->prepare(
            "INSERT INTO ot_sales_invoice
                (tempid, inv_id, inv_number, courier_charges, wallet_amount,
                 subtotal, round_off, total, buyer_gsttype, cat, status)
             VALUES (?, '0', ?, ?, ?, '0', '0', '0', ?, ?, 'draft')"
        );
        $stmt->bind_param('ssddss', $tempid, $g['inv_number'], $g['courier_charges'], $g['wallet_amount'], $g['buyer_gsttype'], $g['cat']);
        $stmt->execute();
        $stmt->close();
        $invoiceCount++;

        foreach ($g['lines'] as $line) {
            $sub_total_rate = $line['rate'] * $line['qty'];
            $sub_total = $sub_total_rate - $line['discount'];
            // Product GST — inclusive-tax products already have GST baked into
            // the entered rate, so it's carved out of subtotal (not added again
            // into total); exclusive-tax products get GST added on top — same
            // convention as invoice-action.php / ot-sale-action.php.
            if (($line['gst_type'] ?? 'exclusive') === 'inclusive' && $line['gst'] > 0) {
                $gst_amount = number_format($sub_total - ($sub_total * 100 / (100 + $line['gst'])), 2, '.', '');
                $total = $sub_total;
            } else {
                $gst_amount = number_format($sub_total * $line['gst'] / 100, 2, '.', '');
                $total = $sub_total + (float)$gst_amount;
            }
            $gst_type = ($g['state_id'] && $g['state_id'] == $admin_state_id) ? 'inner' : 'outer';

            $stmt = $db_conn->prepare(
                "INSERT INTO ot_sales
                    (godownid, warehouse_id, cat, qty, date, tempid, prid, price, discount,
                     sub_total, total, gst, gst_amount, customer_name, customer_mobile,
                     customer_address, order_number, amount_received, amount_date,
                     shipping_address, gst_number, order_date, ship_date, hsn,
                     buyer_gsttype, state_id, gst_type, username, usertype)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '0', '1991-01-01',
                         ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'iissssidddddsssssssssssssss',
                $g['godownid'], $g['warehouse_id'], $g['cat'], $line['qty'], $g['date'], $tempid, $line['product_id'],
                $line['rate'], $line['discount'], $sub_total, $total, $line['gst'], $gst_amount,
                $g['customer_name'], $g['customer_mobile'], $g['customer_address'], $g['order_number'],
                $g['shipping_address'], $g['gst_number'], $g['order_date'], $g['ship_date'], $line['hsn'],
                $g['buyer_gsttype'], $g['state_id'], $gst_type, $login_user, $Login_user_TYPEvl
            );
            $stmt->execute();
            $stmt->close();
            $lineCount++;
            // No stock deduction here -- this import always lands as a Draft
            // (see the $isDraft comment above); stock only moves once the
            // company confirms it from ot-sale-view.php.
        }

        // Recompute rounded total, same formula as ot-sale-action.php:
        // total = round(subtotal + courier_charges) - wallet_amount, floored at 0.
        $stmt = $db_conn->prepare("SELECT SUM(total) AS s FROM ot_sales WHERE tempid = ?");
        $stmt->bind_param('s', $tempid);
        $stmt->execute();
        $subtotal = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0);
        $stmt->close();

        $with_courier = $subtotal + $g['courier_charges'];
        $roundvalue = round($with_courier);
        $roundoff = $roundvalue - $with_courier;
        $net_total = max(0, $roundvalue - $g['wallet_amount']);

        $stmt = $db_conn->prepare("UPDATE ot_sales_invoice SET subtotal=?, round_off=?, total=?, wallet_amount=?, courier_charges=? WHERE tempid=?");
        $stmt->bind_param('ddddds', $subtotal, $roundoff, $net_total, $g['wallet_amount'], $g['courier_charges'], $tempid);
        $stmt->execute();
        $stmt->close();

        // Coupon commission — identical logic to ot-sale-action.php: resolves
        // the coupon code to a CP account, computes commission = per-unit
        // amount × total qty, and logs it to wallet_monthly_sls_report keyed
        // by this invoice's tempid (one row per tempid, never duplicated).
        // Skipped for a draft, same as ot-sale-action.php's own $isDraft
        // guard -- a draft isn't a real sale yet, so no commission is earned
        // until it's actually confirmed.
        if (!$isDraft && $g['coupon_code'] !== '') {
            $coupon_code = $g['coupon_code'];
            preg_match('/-(.*?)-/', $coupon_code, $matches);
            $coupon_usertype = $matches[1] ?? '';

            $tablenameCP = '';
            $usertype_print = '';
            if ($coupon_usertype === 'SS')     { $tablenameCP = 'super_stockiest';   $usertype_print = 'super_stockiest'; }
            elseif ($coupon_usertype === 'S')  { $tablenameCP = 'stockiest';         $usertype_print = 'stockiest'; }
            elseif ($coupon_usertype === 'SD') { $tablenameCP = 'super_distributor'; $usertype_print = 'super_distributor'; }
            elseif ($coupon_usertype === 'D')  { $tablenameCP = 'distributor';       $usertype_print = 'distributor'; }

            if ($tablenameCP !== '') {
                $stmtCp = $db_conn->prepare("SELECT temp_id FROM $tablenameCP WHERE useridtext = ?");
                $stmtCp->bind_param('s', $coupon_code);
                $stmtCp->execute();
                $cpRows = $stmtCp->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmtCp->close();

                if (count($cpRows) === 1) {
                    $userid_print = $cpRows[0]['temp_id'];

                    $stmtQty = $db_conn->prepare("SELECT SUM(qty) AS s FROM ot_sales WHERE tempid = ?");
                    $stmtQty->bind_param('s', $tempid);
                    $stmtQty->execute();
                    $qty_total_result = (int)($stmtQty->get_result()->fetch_assoc()['s'] ?? 0);
                    $stmtQty->close();

                    $stmtComm = $db_conn->prepare("SELECT amount FROM admin_website_coupon_commission WHERE usertype = ?");
                    $stmtComm->bind_param('s', $usertype_print);
                    $stmtComm->execute();
                    $cp_amount = (float)($stmtComm->get_result()->fetch_assoc()['amount'] ?? 0);
                    $stmtComm->close();

                    $total_coupon_commission = $cp_amount * $qty_total_result;
                    $remarks_cb = "Invoice Number : " . $g['inv_number'] . "<br/>Date : " . date('d/m/Y');

                    $stmtExists = $db_conn->prepare("SELECT id FROM wallet_monthly_sls_report WHERE user_type = ?");
                    $stmtExists->bind_param('s', $tempid);
                    $stmtExists->execute();
                    $reportExists = $stmtExists->get_result()->num_rows > 0;
                    $stmtExists->close();

                    if (!$reportExists) {
                        $today = date('Y-m-d');
                        $stmtIns = $db_conn->prepare(
                            "INSERT INTO wallet_monthly_sls_report
                                (user_type, user_id, from_date, to_date, month, year, total_sls_amount,
                                 target_sls_amount, target_reached, refer_by_usertype, refer_by_userid,
                                 commission_percentage, commission_amount, commission_type, remarks)
                             VALUES (?, 'Nill', ?, ?, '0', '0', '0', '0', 'yes', ?, ?, '0', ?, 'Website Order Commission', ?)"
                        );
                        $stmtIns->bind_param(
                            'sssssds',
                            $tempid, $today, $today, $usertype_print, $userid_print,
                            $total_coupon_commission, $remarks_cb
                        );
                        $stmtIns->execute();
                        $stmtIns->close();
                    }

                    $stmtUpdCoupon = $db_conn->prepare("UPDATE ot_sales_invoice SET coupon_code=?, website_commission=? WHERE tempid=?");
                    $stmtUpdCoupon->bind_param('sds', $coupon_code, $total_coupon_commission, $tempid);
                    $stmtUpdCoupon->execute();
                    $stmtUpdCoupon->close();
                }
            }
        }
    }

    $db_conn->commit();
} catch (StockException $e) {
    $db_conn->rollback();
    @unlink($stored_path);
    $_SESSION['errorMessage'] = "Import cancelled — stock error: " . $e->getMessage();
    ot_import_back([]);
} catch (\Throwable $e) {
    $db_conn->rollback();
    @unlink($stored_path);
    error_log("ot-sale-import-action error: " . $e->getMessage());
    $_SESSION['errorMessage'] = "Import cancelled — an error occurred while saving. Please try again.";
    ot_import_back([]);
}

@unlink($stored_path);
$_SESSION['sucMessage'] = "Imported $invoiceCount invoice(s) with $lineCount product line(s) as Draft. Review them on this page, then use \"Confirm Selected\" to transfer stock and finalise.";
ot_import_back([]);
