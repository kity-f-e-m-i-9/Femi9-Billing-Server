<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/GodownStockMove.php");
include("config.php");

// Finance-only, same as the rest of the Internal Stock Transfer area —
// but this is a SEPARATE concept from Internal Transfer/Auto Transfer:
// a direct godown-to-godown stock move with no invoice. Never writes to
// internal_transfer / internal_transfer_invoice.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header("Location: dashboard.php");
    exit;
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
    header("Location: godown-stock-move.php");
    exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$fromCompanyGodownId = filter_var($_POST['from_company_godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$toCompanyGodownId   = filter_var($_POST['to_company_godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$fromWarehouseId      = filter_var($_POST['from_warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$toWarehouseId        = filter_var($_POST['to_warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$note                 = trim((string) ($_POST['note'] ?? ''));
$note                 = $note !== '' ? mb_substr($note, 0, 255) : null;
$createdBy            = $_SESSION['LOGIN_USER'] ?? 'system';

$rawProductIds = $_POST['product_id'] ?? [];
$rawQtys       = $_POST['qty'] ?? [];

if (!$fromCompanyGodownId || !$toCompanyGodownId || !is_array($rawProductIds) || empty($rawProductIds)) {
    $_SESSION['errorMessage'] = "Please fill in every required field.";
    header("Location: godown-stock-move.php");
    exit;
}

if ($fromCompanyGodownId === $toCompanyGodownId) {
    $_SESSION['errorMessage'] = "From and To company profile must be different.";
    header("Location: godown-stock-move.php");
    exit;
}

if (!is_godown_allowed($db_conn, $fromCompanyGodownId) || !is_godown_allowed($db_conn, $toCompanyGodownId)) {
    $_SESSION['errorMessage'] = "You are not authorized to use one of these company profiles.";
    header("Location: godown-stock-move.php");
    exit;
}

// Validate and normalize every row up front, before touching StockService —
// any malformed row fails the whole batch immediately rather than partway
// through a transaction.
$rows = [];
foreach ($rawProductIds as $i => $rawPid) {
    $productId = filter_var($rawPid, FILTER_VALIDATE_INT) ?: 0;
    $qty       = filter_var($rawQtys[$i] ?? '', FILTER_VALIDATE_INT) ?: 0;

    if (!$productId || $qty <= 0) {
        $_SESSION['errorMessage'] = "Please fill in every field for every product.";
        header("Location: godown-stock-move.php");
        exit;
    }

    $rows[] = ['product_id' => $productId, 'qty' => $qty];
}

// A product appearing twice in one batch is never a legitimate
// request — reject up front rather than silently applying two moves
// to the same row in sequence.
$seenProductIds = array_column($rows, 'product_id');
if (count($seenProductIds) !== count(array_unique($seenProductIds))) {
    $_SESSION['errorMessage'] = "Each product can only appear once per move batch.";
    header("Location: godown-stock-move.php");
    exit;
}

$stockService = new StockService($db_conn);

// All-or-nothing: one shared transaction across every row in the batch —
// if any product's move fails (e.g. insufficient stock to draw from),
// none of the batch's moves are applied.
$db_conn->begin_transaction();
try {
    foreach ($rows as $row) {
        $refId = generate_godown_stock_move_ref_id();

        $outResult = $stockService->transferOut(
            $row['product_id'], $Login_user_TYPEvl, (string) $fromCompanyGodownId, $row['qty'],
            'transfer', $refId, $createdBy, true, $fromWarehouseId
        );
        $stockService->transferIn(
            $row['product_id'], $Login_user_TYPEvl, (string) $toCompanyGodownId, $row['qty'],
            'transfer', $refId, $createdBy, true,
            $outResult['consumed_rate'] ?? null, $toWarehouseId
        );

        record_godown_stock_move(
            $db_conn, $refId, $row['product_id'], $fromCompanyGodownId, $toCompanyGodownId,
            $fromWarehouseId, $toWarehouseId, $row['qty'], $note, $createdBy
        );
    }

    $db_conn->commit();
    $_SESSION['sucMessage'] = count($rows) === 1
        ? "Stock moved successfully."
        : count($rows) . " products moved successfully.";
    header("Location: godown-stock-move.php");
    exit;
} catch (StockException $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = "Stock error: " . $e->getMessage();
    header("Location: godown-stock-move.php");
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log('[godown-stock-move] ' . $e->getMessage());
    $_SESSION['errorMessage'] = "An error occurred. Please try again.";
    header("Location: godown-stock-move.php");
    exit;
}
