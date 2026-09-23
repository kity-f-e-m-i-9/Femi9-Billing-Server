<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/NeksomoStockBridge.php");
require_once("include/RawMaterialBundles.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support).
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$godownId    = (string)(int)($_POST['godownid'] ?? 0);
$warehouseId = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;

// Lets the operator backdate a conversion (e.g. entering it the next day)
// instead of every conversion always being timestamped "now". Applies to
// the whole batch, not per-row — re-validated server-side since the date
// input's client-side `max` alone is never trusted.
$rawConversionDate = trim($_POST['conversion_date'] ?? '');
$conversionDateObj  = \DateTime::createFromFormat('Y-m-d', $rawConversionDate);
if (!$conversionDateObj || $conversionDateObj->format('Y-m-d') !== $rawConversionDate) {
    $_SESSION['errorMessage'] = "Please select a valid conversion date.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}
if ($rawConversionDate > date('Y-m-d')) {
    $_SESSION['errorMessage'] = "Conversion date cannot be in the future.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}
$conversionDate = $rawConversionDate;

$rawProductIds    = $_POST['product_id'] ?? [];
$rawDirections    = $_POST['direction'] ?? [];
$rawPackCounts    = $_POST['pack_count'] ?? [];
$rawBundleIds     = $_POST['bundle_id'] ?? [];
$rawMachineCodeIds = $_POST['machine_code_id'] ?? [];

if (!$godownId || !is_array($rawProductIds) || empty($rawProductIds)) {
    $_SESSION['errorMessage'] = "Invalid submission — please fill in every field.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}

if (!is_godown_allowed($db_conn, (int)$godownId)) {
    $_SESSION['errorMessage'] = "You are not authorized to use this company profile.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}

// Warehouse is required for this feature specifically (see plan's Global
// Constraints) — the picker is `required` client-side, this re-validates
// server-side since the client-side attribute alone is never trusted.
if ($warehouseId === null) {
    $_SESSION['errorMessage'] = "Please select a godown (physical).";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}

// Validate and normalize every row up front, before touching StockService —
// any malformed row fails the whole batch immediately rather than partway
// through a transaction.
$rows = [];
foreach ($rawProductIds as $i => $rawProductId) {
    $productId = (int) $rawProductId;
    $direction = $rawDirections[$i] ?? '';
    $packCount = (int) ($rawPackCounts[$i] ?? 0);
    $bundleId  = filter_var($rawBundleIds[$i] ?? '', FILTER_VALIDATE_INT) ?: null;
    $machineCodeId = filter_var($rawMachineCodeIds[$i] ?? '', FILTER_VALIDATE_INT) ?: null;

    if (!$productId || $packCount < 1 || !in_array($direction, ['pieces_to_pack', 'pack_to_pieces'], true)) {
        $_SESSION['errorMessage'] = "Invalid submission — please fill in every field for every product.";
        header("Location: neksomo-piece-pack-convert.php");
        exit;
    }

    $rows[] = ['product_id' => $productId, 'direction' => $direction, 'pack_count' => $packCount, 'bundle_id' => $bundleId, 'machine_code_id' => $machineCodeId];
}

// A product appearing twice in one batch is never a legitimate
// request — reject up front rather than silently applying two
// conversions to the same row in sequence.
$seenProductIds = array_column($rows, 'product_id');
if (count($seenProductIds) !== count(array_unique($seenProductIds))) {
    $_SESSION['errorMessage'] = "Each product can only appear once per conversion batch.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}

$productIds = array_column($rows, 'product_id');
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$stmt = $db_conn->prepare("SELECT id, productName, pieces_per_pack FROM products WHERE id IN ($placeholders) AND pieces_per_pack > 1");
$stmt->bind_param(str_repeat('i', count($productIds)), ...$productIds);
$stmt->execute();
$productsById = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
    $productsById[(int) $p['id']] = $p;
}
$stmt->close();

foreach ($rows as $row) {
    if (!isset($productsById[$row['product_id']])) {
        $_SESSION['errorMessage'] = "One of the selected products does not support pieces/pack conversion.";
        header("Location: neksomo-piece-pack-convert.php");
        exit;
    }
}

// A mapped product only ever assembles FROM its raw pool — "break open"
// has no meaningful raw pool to return pieces to (see updateDirectionOptions()
// in neksomo-piece-pack-convert.php, which hides this option client-side;
// re-validated here since the client-side hide alone is never trusted).
foreach ($rows as $row) {
    $rawSource = get_neksomo_source_for_company_product($db_conn, $row['product_id']);
    if ($rawSource && $row['direction'] === 'pack_to_pieces') {
        $_SESSION['errorMessage'] = "This product draws from a raw material pool — breaking a finished pack back open isn't supported for it.";
        header("Location: neksomo-piece-pack-convert.php");
        exit;
    }
}

// A mapped product's Pieces -> Pack conversion requires a specific open
// raw material bundle to be selected — no fallback to the old pooled
// deduction (see docs/superpowers/specs/2026-09-22-raw-material-bundle-
// tracking-design.md's confirmed "block until bundle exists" decision).
// Re-validated here since the client-side `required` attribute on a
// hidden field is never trusted.
foreach ($rows as $row) {
    $rawSource = get_neksomo_source_for_company_product($db_conn, $row['product_id']);
    if ($rawSource && $row['direction'] === 'pieces_to_pack' && !$row['bundle_id']) {
        $_SESSION['errorMessage'] = "Please select a raw material bundle for every mapped product — add one via Input Stock → Raw Bundles if none exist yet.";
        header("Location: neksomo-piece-pack-convert.php");
        exit;
    }
}

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

// All-or-nothing: one shared transaction across every row in the batch —
// if any product's conversion fails (e.g. insufficient pieces/packs to
// draw from), none of the batch's conversions are applied.
$db_conn->begin_transaction();
try {
    $summaries = [];
    foreach ($rows as $row) {
        $piecesPerPack = (int) $productsById[$row['product_id']]['pieces_per_pack'];
        $productName   = $productsById[$row['product_id']]['productName'];
        $refId         = 'CONV-' . date('YmdHis') . '-' . random_int(100, 999);

        $rawSource = get_neksomo_source_for_company_product($db_conn, $row['product_id']);

        if ($rawSource && $row['direction'] === 'pieces_to_pack') {
            // The selected bundle's own remaining_pieces IS the hard limit
            // for this conversion — no pooled stock.closing_qty deduction
            // is involved at all. Caps at however many FULL packs the
            // bundle can actually support; if that's less than requested,
            // converts only that many and reports the shortfall (see
            // docs/superpowers/specs/2026-09-22-raw-material-bundle-
            // tracking-design.md).
            $bundleResult = convert_from_raw_material_bundle($db_conn, $row['bundle_id'], $row['product_id'], $piecesPerPack, $row['pack_count'], $refId, $createdBy);

            $creditResult = $stockService->credit(
                $row['product_id'], 'company', $godownId, $bundleResult['packs_made'],
                'conversion', $refId, $createdBy, true, $warehouseId, $row['machine_code_id'], $conversionDate
            );

            if ($bundleResult['packs_made'] < $bundleResult['requested_packs']) {
                $summaries[] = "$productName: only {$bundleResult['packs_made']} of {$bundleResult['requested_packs']} pack(s) could be made — selected bundle ran short ({$bundleResult['remaining_after']} pc left). Now {$creditResult['qty_after']} pack(s) on hand";
            } else {
                $summaries[] = "$productName: assembled {$bundleResult['packs_made']} pack(s) from the selected bundle — now {$creditResult['qty_after']} pack(s) on hand";
            }
        } elseif ($row['direction'] === 'pieces_to_pack') {
            $result = $stockService->convertPiecesToPack(
                $row['product_id'], 'company', $godownId, $piecesPerPack, $row['pack_count'],
                $refId, $createdBy, true, $warehouseId, $row['machine_code_id'], $conversionDate
            );
            $summaries[] = "$productName: assembled {$row['pack_count']} pack(s) — now {$result['closing_qty_after']} pack(s), {$result['extra_pieces_after']} loose piece(s)";
        } else {
            $result = $stockService->convertPackToPieces(
                $row['product_id'], 'company', $godownId, $piecesPerPack, $row['pack_count'],
                $refId, $createdBy, true, $warehouseId, $row['machine_code_id'], $conversionDate
            );
            $summaries[] = "$productName: broke open {$row['pack_count']} pack(s) — now {$result['closing_qty_after']} pack(s), {$result['extra_pieces_after']} loose piece(s)";
        }
    }

    $db_conn->commit();
    $_SESSION['sucMessage'] = implode('. ', $summaries) . '.';
} catch (StockException $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = $e->getMessage();
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("neksomo-piece-pack-convert-action error: " . $e->getMessage());
    $_SESSION['errorMessage'] = "An error occurred. Please try again.";
}

header("Location: neksomo-piece-pack-convert.php");
exit;
