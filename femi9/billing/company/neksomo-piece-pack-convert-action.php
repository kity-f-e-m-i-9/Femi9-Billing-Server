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

// Each product row can carry any number of damaged/extra entries, each
// tagged with its own reason — submitted as damaged_reason_id[rowIndex][]
// / damaged_qty[rowIndex][] (and extra_ equivalents), rowIndex matching
// this same row's position in product_id[] (see assignAdjustEntryNames()
// in neksomo-piece-pack-convert.php, which stamps those names from live
// DOM order right before submit).
$rawDamagedReasonIds = $_POST['damaged_reason_id'] ?? [];
$rawDamagedQtys      = $_POST['damaged_qty'] ?? [];
$rawExtraReasonIds   = $_POST['extra_reason_id'] ?? [];
$rawExtraQtys        = $_POST['extra_qty'] ?? [];

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
// Parses one row's damaged/extra entry arrays into
// [['reason_id' => int, 'qty' => int], ...], validating that every
// entry has both a reason and a positive qty — re-checked here since
// the client-side `required` on these dynamically-added fields is
// never trusted.
function parse_bundle_adjust_entries($rawReasonIds, $rawQtys, int $rowIndex, string $label): array
{
    $reasonIds = $rawReasonIds[$rowIndex] ?? [];
    $qtys      = $rawQtys[$rowIndex] ?? [];
    if (!is_array($reasonIds) || !is_array($qtys) || count($reasonIds) !== count($qtys)) {
        throw new \RuntimeException("Invalid $label entries submitted.");
    }
    $entries = [];
    foreach ($reasonIds as $j => $rawReasonId) {
        $reasonId = filter_var($rawReasonId, FILTER_VALIDATE_INT) ?: null;
        $qty      = (int) ($qtys[$j] ?? 0);
        if (!$reasonId || $qty < 1) {
            throw new \RuntimeException("Please select a reason and enter a valid quantity for every $label entry.");
        }
        $entries[] = ['reason_id' => $reasonId, 'qty' => $qty];
    }
    return $entries;
}

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

    try {
        $damagedEntries = parse_bundle_adjust_entries($rawDamagedReasonIds, $rawDamagedQtys, $i, 'damaged');
        $extraEntries   = parse_bundle_adjust_entries($rawExtraReasonIds, $rawExtraQtys, $i, 'extra');
    } catch (\RuntimeException $e) {
        $_SESSION['errorMessage'] = $e->getMessage();
        header("Location: neksomo-piece-pack-convert.php");
        exit;
    }

    $rows[] = ['product_id' => $productId, 'direction' => $direction, 'pack_count' => $packCount, 'bundle_id' => $bundleId, 'machine_code_id' => $machineCodeId, 'damaged_entries' => $damagedEntries, 'extra_entries' => $extraEntries];
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
            // Damage/extra are applied against the bundle first, in the
            // same transaction, so remaining_pieces reflects reality
            // before the conversion draw computes its cap. Each entry
            // is logged individually (own reason + qty) rather than
            // collapsed into one call, so the adjustments log stays
            // itemized — see record_damaged_pieces()/
            // add_extra_pieces_to_bundle() in RawMaterialBundles.php.
            $totalDamaged = 0;
            foreach ($row['damaged_entries'] as $entry) {
                record_damaged_pieces($db_conn, $row['bundle_id'], $entry['qty'], null, $createdBy, $entry['reason_id'], $refId);
                $totalDamaged += $entry['qty'];
            }
            $totalExtra = 0;
            foreach ($row['extra_entries'] as $entry) {
                add_extra_pieces_to_bundle($db_conn, $row['bundle_id'], $entry['qty'], $createdBy, $entry['reason_id'], $refId);
                $totalExtra += $entry['qty'];
            }

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

            $adjustNote = '';
            if ($totalDamaged > 0) { $adjustNote .= ", $totalDamaged pc damaged"; }
            if ($totalExtra > 0) { $adjustNote .= ", $totalExtra pc extra found"; }

            if ($bundleResult['packs_made'] < $bundleResult['requested_packs']) {
                $summaries[] = "$productName: only {$bundleResult['packs_made']} of {$bundleResult['requested_packs']} pack(s) could be made — selected bundle ran short ({$bundleResult['remaining_after']} pc left$adjustNote). Now {$creditResult['qty_after']} pack(s) on hand";
            } else {
                $summaries[] = "$productName: assembled {$bundleResult['packs_made']} pack(s) from the selected bundle$adjustNote — now {$creditResult['qty_after']} pack(s) on hand";
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
