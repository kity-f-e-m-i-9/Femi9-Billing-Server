<?php
// Dedicated action for releasing Shop Invoice (DM Get Order, Company-
// fulfilled) stock — deliberately separate from internal_transfer_auto_action.php
// ("Transfer Now"), which only ever replenishes TP/CP/OT demand into LLP and
// must never touch Shop Invoice stock on its own (user request, 2026-11-17).
// Staff click this explicitly, on its own button, when they want Shop
// Invoices released.
// Reachable from two places with two different audiences: the Auto Transfer
// page (internal_transfer_auto.php, finance-only) and ms_prorders.php's own
// per-row checkboxes (marketing/company-ops staff who manage DM orders, not
// necessarily usertype='finance'). requireAnyPermission() below is the real
// gate either way — deliberately NOT also hard-requiring usertype='finance'
// (internal_transfer_auto_action.php's own extra check) since that would
// block the ms_prorders.php path for everyone who isn't finance, even with
// the right permission. 2026-11-17, user request.
include("checksession.php"); require_once("include/PermissionCheck.php"); requireAnyPermission(['internal_transfer','stock_transfers','ms']);
require_once("include/StockService.php");
require_once("include/AutoTransferDemand.php");
include("config.php");

error_reporting(0);

$excludedSourceIds = [];
$rawExcluded = json_decode((string) ($_REQUEST['excluded_source_ids'] ?? '[]'), true);
if (is_array($rawExcluded)) {
    foreach ($rawExcluded as $sid) {
        if (is_string($sid)) { $excludedSourceIds[$sid] = true; }
    }
}

// ms_prorders.php's per-row checkboxes post the exact inv_ids to release —
// only those get touched. Omitted/empty (the Auto Transfer page's own
// button) keeps the original blanket "release every eligible invoice"
// behavior. 2026-11-17, user request.
$onlyInvIds = [];
$rawInvIds = $_REQUEST['inv_ids'] ?? [];
if (is_array($rawInvIds)) {
    foreach ($rawInvIds as $iid) {
        if (is_string($iid) && $iid !== '') { $onlyInvIds[] = $iid; }
    }
}

// Where to send staff back to — ms_prorders.php's form posts its own path
// here so the per-row checkbox flow lands back where it started, not on the
// Auto Transfer page. Restricted to a fixed allowlist, never an open
// redirect to an arbitrary path.
$returnTo = $_REQUEST['return_to'] ?? 'internal_transfer_auto';
if (!in_array($returnTo, ['internal_transfer_auto', 'ms_prorders.php'], true)) {
    $returnTo = 'internal_transfer_auto';
}

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';
$tempidBase   = 'SHOPREL' . date('YmdHis') . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

$db_conn->begin_transaction();
try {
    $summary = release_shop_invoices_from_existing_stock($db_conn, $stockService, $createdBy, $tempidBase, $excludedSourceIds, $onlyInvIds);
    $db_conn->commit();
    $backfillNote = $summary['receipts_backfilled'] > 0
        ? (" (" . $summary['receipts_backfilled'] . " invoice(s) had stock already moved but were missing their receipt — fixed.)")
        : "";
    if ($summary['lines_considered'] === 0) {
        if ($summary['receipts_backfilled'] > 0) {
            $_SESSION['successMessage'] = "Nothing new to release." . $backfillNote;
            $_SESSION['sucMessage'] = $_SESSION['successMessage'];
        } else {
            $_SESSION['errorMessage'] = "Nothing to release — the selected order(s) either have no pending lines, or were already released.";
        }
    } elseif ($summary['lines_released'] === 0) {
        $_SESSION['errorMessage'] = "Stock not released — the warehouse doesn't have enough stock yet for " . $summary['lines_short'] . " product line(s). Left pending; try again after a transfer brings stock in.";
    } elseif ($summary['lines_short'] > 0) {
        $successMsg = "Partially released: " . $summary['lines_released'] . " line(s) released, " . $summary['lines_short'] . " still short on stock and left pending." . $backfillNote;
        $_SESSION['successMessage'] = $successMsg;
        $_SESSION['sucMessage'] = $successMsg; // internal_transfer_auto.php reads this key, ms_prorders.php reads 'successMessage'
    } else {
        $successMsg = "Shop Invoice stock released (" . $summary['lines_released'] . " line(s))." . $backfillNote;
        $_SESSION['successMessage'] = $successMsg;
        $_SESSION['sucMessage'] = $successMsg;
    }
} catch (StockException $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = "Stock error: " . $e->getMessage();
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("release-shop-invoice-stock-action error: " . $e->getMessage());
    $_SESSION['errorMessage'] = "An error occurred. Please try again.";
}

echo "<script>window.location='" . $returnTo . "';</script>";
