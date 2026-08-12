<?php
/**
 * OT Channel Sales — Bulk Import Handler
 *
 * Parses an uploaded Excel file (see ot-sale-import-template.php) and creates
 * ot_sales_invoice / ot_sales rows, mirroring ot-sale-action.php's add-record
 * insert logic. One spreadsheet row = one invoice; every active product has
 * its own "<Name> Qty" / "<Name> Rate" column pair, so a single row can carry
 * several products at once and each product can only appear once per row —
 * there's no way to add the same product twice under one invoice.
 *
 * Columns are matched to products by the product_id embedded in row 2 of the
 * template (falls back to matching the "<Name> Qty"/"<Name> Rate" header text
 * against the live product list if row 2 is missing/edited), not by re-typing
 * product names, so the product is always linked to the real DB record.
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
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

date_default_timezone_set("Asia/Kolkata");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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

if (!move_uploaded_file($_FILES['import_file']['tmp_name'], $stored_path)) {
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

// The template puts "product_id:<id>" markers in the row right after the
// header, one per Qty/Rate column pair — that's the authoritative link back
// to the product. If that marker row is missing/blank (user deleted it),
// fall back to matching header text against the live product list by name.
$row_nums_all = array_keys($rows);
$header_pos = array_search($header_row_num, $row_nums_all, true);
$marker_row_num = $row_nums_all[$header_pos + 1] ?? null;
$markerRow = ($marker_row_num !== null) ? $rows[$marker_row_num] : [];

$colToFixedField = [];
$colToProductId = []; // col => product_id, for Qty columns
$rateColForQtyCol = []; // qty col => rate col

$activeProductByName = [];
$res = $db_conn->query(
    "SELECT id, productName, gst, hsn FROM products
     WHERE deleted_at IS NULL AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL)"
);
while ($row = $res->fetch_assoc()) {
    $activeProductByName[strtolower(trim($row['productName']))] = $row;
}

$headerRowData = $rows[$header_row_num];
$colKeys = array_keys($headerRowData);

foreach ($colKeys as $idx => $col) {
    $rawHeader = (string)($headerRowData[$col] ?? '');
    $norm = ot_import_normalize_header($rawHeader);

    if (isset($fixedFieldMap[$norm])) {
        $colToFixedField[$col] = $fixedFieldMap[$norm];
        continue;
    }

    if (preg_match('/^(.*)\s+qty$/i', trim($rawHeader), $m)) {
        $productLabel = trim($m[1]);
        $nextCol = $colKeys[$idx + 1] ?? null;

        $pid = 0;
        $markerVal = (string)($markerRow[$col] ?? '');
        if (preg_match('/^product_id:(\d+)$/', trim($markerVal), $pm)) {
            $pid = (int)$pm[1];
        } elseif (isset($activeProductByName[strtolower($productLabel)])) {
            $pid = (int)$activeProductByName[strtolower($productLabel)]['id'];
        }

        if ($pid > 0) {
            $colToProductId[$col] = $pid;
            if ($nextCol !== null) {
                $rateColForQtyCol[$col] = $nextCol;
            }
        }
    }
}

if (!in_array('inv_number', $colToFixedField, true)) {
    @unlink($stored_path);
    $_SESSION['errorMessage'] = "The template is missing the required 'Invoice Number' column.";
    ot_import_back([]);
}

if (empty($colToProductId)) {
    @unlink($stored_path);
    $_SESSION['errorMessage'] = "No product Qty/Rate columns were recognized — please use the provided sample template without altering the product columns.";
    ot_import_back([]);
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

$productById = [];
$res = $db_conn->query("SELECT id, productName, gst, hsn FROM products WHERE id IN (" . implode(',', array_map('intval', array_unique(array_values($colToProductId)))) . ")");
while ($row = $res->fetch_assoc()) {
    $productById[(int)$row['id']] = $row;
}

$stateByName = [];
$res = $db_conn->query("SELECT id, st_name FROM state");
while ($row = $res->fetch_assoc()) {
    $stateByName[strtolower(trim($row['st_name']))] = (int)$row['id'];
}
$admin_state_id = $Result_Log_users_Dtails134['state_id'] ?? 0;

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
// Skip the product_id marker row if present right after the header.
if ($marker_row_num !== null && !empty($colToProductId)) {
    $firstQtyCol = array_key_first($colToProductId);
    $markerCell = trim((string)($markerRow[$firstQtyCol] ?? ''));
    if (preg_match('/^product_id:\d+$/', $markerCell)) {
        $data_start_index++;
    }
}

$errors = [];
$groups = []; // groupKey => invoice group
$rowLabel = 0;

for ($idx = $data_start_index; $idx < count($row_nums); $idx++) {
    $rnum = $row_nums[$idx];
    $rowData = $rows[$rnum];

    $inv_number = ot_import_val($rowData, $colToFixedField, 'inv_number');

    // Skip fully blank rows.
    $hasAnyProductQty = false;
    foreach ($colToProductId as $col => $pid) {
        if (trim((string)($rowData[$col] ?? '')) !== '') { $hasAnyProductQty = true; break; }
    }
    if ($inv_number === '' && !$hasAnyProductQty) {
        continue;
    }

    $rowLabel++;
    $excelRowLabel = "Row $rowLabel (Invoice Number: " . ($inv_number !== '' ? $inv_number : '?') . ")";

    $godownName = ot_import_val($rowData, $colToFixedField, 'godown');
    $godownid = 0;
    if ($godownName !== '') {
        $godownid = $godownByName[strtolower($godownName)] ?? 0;
        if (!$godownid) {
            $errors[] = "$excelRowLabel: unknown Company Profile '$godownName'";
            continue;
        }
    } elseif ($default_godownid > 0) {
        $godownid = $default_godownid;
    } else {
        $errors[] = "$excelRowLabel: Company Profile is required (or set a Default Company Profile)";
        continue;
    }

    if (!is_godown_allowed($db_conn, $godownid)) {
        $errors[] = "$excelRowLabel: you are not authorized to use this company profile";
        continue;
    }

    $catRaw = ot_import_val($rowData, $colToFixedField, 'cat');
    $catRaw = ($catRaw !== '') ? $catRaw : $default_cat;
    if ($catRaw === '') {
        $errors[] = "$excelRowLabel: Category is required (or set a Default Category)";
        continue;
    }
    $catKey = strtolower(trim($catRaw));
    if (!isset($allowedCats[$catKey])) {
        $errors[] = "$excelRowLabel: unknown or unauthorized category '$catRaw'";
        continue;
    }
    $catname = $allowedCats[$catKey];

    if ($inv_number === '') {
        $errors[] = "$excelRowLabel: Invoice Number is required";
        continue;
    }

    $dateRaw = ot_import_val($rowData, $colToFixedField, 'date');
    $dateTs = $dateRaw !== '' ? strtotime($dateRaw) : false;
    if ($dateTs === false) {
        $errors[] = "$excelRowLabel: Date is missing or invalid";
        continue;
    }
    $date = date('Y-m-d', $dateTs);

    $stateName = ot_import_val($rowData, $colToFixedField, 'state');
    $state_id = $stateName !== '' ? ($stateByName[strtolower($stateName)] ?? 0) : 0;
    if ($stateName !== '' && !$state_id) {
        $errors[] = "$excelRowLabel: unknown state '$stateName'";
        continue;
    }

    // Collect product lines for this row. Each product column pair can only
    // contribute one line per row by construction — there's exactly one Qty
    // cell per product per row, so duplicate line items are structurally
    // impossible.
    $lines = [];
    $rowHasProductError = false;
    foreach ($colToProductId as $qtyCol => $pid) {
        $qtyRaw = trim((string)($rowData[$qtyCol] ?? ''));
        if ($qtyRaw === '') continue;

        $qty = (int)$qtyRaw;
        if ($qty <= 0) continue; // treat 0/blank as "not ordered"

        $rateCol = $rateColForQtyCol[$qtyCol] ?? null;
        $rate = $rateCol !== null ? (float)trim((string)($rowData[$rateCol] ?? '0')) : 0;
        if ($rate <= 0) {
            $product = $productById[$pid] ?? null;
            $pname = $product['productName'] ?? "#$pid";
            $errors[] = "$excelRowLabel: Rate must be greater than 0 for product '$pname'";
            $rowHasProductError = true;
            continue;
        }

        if (!isset($productById[$pid])) {
            $errors[] = "$excelRowLabel: product #$pid is no longer active";
            $rowHasProductError = true;
            continue;
        }
        $product = $productById[$pid];

        $lines[] = [
            'product_id' => $pid,
            'gst'        => (float)$product['gst'],
            'hsn'        => $product['hsn'] ?? '',
            'qty'        => $qty,
            'rate'       => $rate,
            'discount'   => 0.0,
        ];
    }

    if ($rowHasProductError) continue;

    if (empty($lines)) {
        $errors[] = "$excelRowLabel: no product quantities were entered";
        continue;
    }

    // Reject invoice numbers that already exist for this category.
    $stmt = $db_conn->prepare("SELECT COUNT(*) AS n FROM ot_sales_invoice WHERE cat = ? AND inv_number = ?");
    $stmt->bind_param('ss', $catname, $inv_number);
    $stmt->execute();
    $exists = (int)$stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    if ($exists > 0) {
        $errors[] = "$excelRowLabel: Invoice Number '$inv_number' already exists for category '$catname' — skipped";
        continue;
    }

    $groupKey = $catKey . '|' . strtolower($inv_number);
    if (isset($groups[$groupKey])) {
        $errors[] = "$excelRowLabel: duplicate Invoice Number '$inv_number' within this file for category '$catname'";
        continue;
    }

    $gst_number = RemoveSpecialChar(ot_import_val($rowData, $colToFixedField, 'gst_number'));
    $buyer_gsttype = (strlen($gst_number) === 15) ? 'register' : 'unregister';

    $groups[$groupKey] = [
        'godownid'         => $godownid,
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
        'lines'            => $lines,
    ];
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
// Pre-validate stock across the whole file (per godown+product), same
// spirit as ot-sale-action.php's pre-check before any DB write.
// ---------------------------------------------------------------------
$stockService = new StockService($db_conn);
$createdBy = $_SESSION['LOGIN_USER'] ?? 'system';

$neededByKey = []; // "godownid|product_id" => total qty
foreach ($groups as $g) {
    foreach ($g['lines'] as $line) {
        $key = $g['godownid'] . '|' . $line['product_id'];
        $neededByKey[$key] = ($neededByKey[$key] ?? 0) + $line['qty'];
    }
}

foreach ($neededByKey as $key => $needed) {
    [$godownid, $product_id] = explode('|', $key);
    $available = $stockService->getClosingQty((int)$product_id, $Login_user_TYPEvl, $godownid);
    if ($available === null || $available < $needed) {
        @unlink($stored_path);
        $pname = $productById[(int)$product_id]['productName'] ?? "#$product_id";
        $_SESSION['errorMessage'] = "Import cancelled — insufficient stock for product '$pname' in company profile #$godownid. Available: " . ($available ?? 0) . ", Requested across file: $needed";
        ot_import_back([]);
    }
}

// ---------------------------------------------------------------------
// Insert everything in one transaction — mirrors ot-sale-action.php.
// ---------------------------------------------------------------------
function ot_import_gen_tempid(): string {
    $chars = '123456789';
    $rand3 = '';
    for ($i = 0; $i < 3; $i++) { $rand3 .= $chars[random_int(0, strlen($chars) - 1)]; }
    return $rand3 . 'RTST/' . date('dmy') . '/' . date('gis') . mt_rand(100, 999);
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
                 subtotal, round_off, total, buyer_gsttype, cat)
             VALUES (?, '0', ?, ?, '0.00', '0', '0', '0', ?, ?)"
        );
        $stmt->bind_param('ssdss', $tempid, $g['inv_number'], $g['courier_charges'], $g['buyer_gsttype'], $g['cat']);
        $stmt->execute();
        $stmt->close();
        $invoiceCount++;

        foreach ($g['lines'] as $line) {
            $sub_total_rate = $line['rate'] * $line['qty'];
            $sub_total = $sub_total_rate - $line['discount'];
            $gst_amount = number_format($sub_total * $line['gst'] / 100, 2, '.', '');
            $total = $sub_total + (float)$gst_amount;
            $gst_type = ($g['state_id'] && $g['state_id'] == $admin_state_id) ? 'inner' : 'outer';

            $stmt = $db_conn->prepare(
                "INSERT INTO ot_sales
                    (godownid, cat, qty, date, tempid, prid, price, discount,
                     sub_total, total, gst, gst_amount, customer_name, customer_mobile,
                     customer_address, order_number, amount_received, amount_date,
                     shipping_address, gst_number, order_date, ship_date, hsn,
                     buyer_gsttype, state_id, gst_type, username, usertype)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '0', '1991-01-01',
                         ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'issssidddddsssssssssssssss',
                $g['godownid'], $g['cat'], $line['qty'], $g['date'], $tempid, $line['product_id'],
                $line['rate'], $line['discount'], $sub_total, $total, $line['gst'], $gst_amount,
                $g['customer_name'], $g['customer_mobile'], $g['customer_address'], $g['order_number'],
                $g['shipping_address'], $g['gst_number'], $g['order_date'], $g['ship_date'], $line['hsn'],
                $g['buyer_gsttype'], $g['state_id'], $gst_type, $login_user, $Login_user_TYPEvl
            );
            $stmt->execute();
            $stmt->close();
            $lineCount++;

            $stockService->otDeduct(
                $line['product_id'], $Login_user_TYPEvl, (string)$g['godownid'],
                $line['qty'], $tempid, $createdBy,
                true
            );
        }

        // Recompute rounded total, same formula as ot-sale-action.php.
        $stmt = $db_conn->prepare("SELECT SUM(total) AS s FROM ot_sales WHERE tempid = ?");
        $stmt->bind_param('s', $tempid);
        $stmt->execute();
        $subtotal = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0);
        $stmt->close();

        $with_courier = $subtotal + $g['courier_charges'];
        $roundvalue = round($with_courier);
        $roundoff = $roundvalue - $with_courier;

        $stmt = $db_conn->prepare("UPDATE ot_sales_invoice SET subtotal=?, round_off=?, total=? WHERE tempid=?");
        $stmt->bind_param('ddds', $subtotal, $roundoff, $roundvalue, $tempid);
        $stmt->execute();
        $stmt->close();
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
$_SESSION['sucMessage'] = "Imported $invoiceCount invoice(s) with $lineCount product line(s) successfully.";
ot_import_back([]);
