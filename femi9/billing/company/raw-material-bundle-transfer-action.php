<?php
include("checksession.php"); require_once("include/PermissionCheck.php"); requireStaffOnly();
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/RawMaterialBundles.php");
include("config.php");

// Same audience as the other bundle pages (Godown/stockviewer login,
// neksomo, admin).
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin', 'stockviewer'], true)) {
    header("Location: dashboard.php");
    exit;
}

$back = "raw-material-bundle-transfer.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
    header("Location: $back");
    exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$action     = $_POST['action'] ?? '';
$actingUser = $_SESSION['LOGIN_USER'] ?? 'system';
$stockService = new StockService($db_conn);

// DDL (CREATE/ALTER) implicitly commits, so every table this flow touches
// is ensured up front, before any transaction begins.
ensure_raw_material_bundles_table($db_conn);
ensure_raw_material_bundle_transfers_table($db_conn);
ensure_raw_material_bundle_draws_table($db_conn);
ensure_raw_material_bundle_adjustments_table($db_conn);

if ($action === 'transfer') {
    $selectionMode = (string) ($_POST['selection_mode'] ?? 'ids');
    $rawProductId  = filter_var($_POST['raw_product_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $bundleIds = $_POST['bundle_ids'] ?? [];
    $toCg = filter_var($_POST['to_company_godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $toWh = filter_var($_POST['to_warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $fromCg = filter_var($_POST['from_company_godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $fromWh = filter_var($_POST['from_warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;

    if (!in_array($selectionMode, ['ids', 'newest', 'oldest', 'range'], true)) {
        $selectionMode = 'ids';
    }
    $hasSelection = $selectionMode === 'ids'
        ? (is_array($bundleIds) && count($bundleIds) > 0)
        : $rawProductId > 0;
    if (!$hasSelection || !$toCg || !$toWh || !$fromCg || !$fromWh) {
        $_SESSION['errorMessage'] = "Please choose the source, destination and the bundles to transfer.";
        header("Location: $back");
        exit;
    }
    if ($selectionMode === 'ids' && count($bundleIds) > RM_BUNDLE_TRANSFER_PICK_CAP) {
        $_SESSION['errorMessage'] = "You can hand-pick at most " . RM_BUNDLE_TRANSFER_PICK_CAP . " bundles — use Newest N, Oldest N or Number range for larger transfers.";
        header("Location: $back");
        exit;
    }
    if (!is_godown_allowed($db_conn, $fromCg) || !is_godown_allowed($db_conn, $toCg)) {
        $_SESSION['errorMessage'] = "You are not authorized to use this company profile.";
        header("Location: $back");
        exit;
    }

    // Warehouse must be an active, real warehouse — the client dropdown
    // alone is never trusted.
    $whStmt = $db_conn->prepare("SELECT COUNT(*) AS c FROM warehouses WHERE is_active = 1 AND id IN (?, ?)");
    $whStmt->bind_param('ii', $fromWh, $toWh);
    $whStmt->execute();
    $whCount = (int) $whStmt->get_result()->fetch_assoc()['c'];
    $whStmt->close();
    if ($whCount !== (($fromWh === $toWh) ? 1 : 2)) {
        $_SESSION['errorMessage'] = "Invalid warehouse selected.";
        header("Location: $back");
        exit;
    }

    $db_conn->begin_transaction();
    try {
        // The source the form claims must match where the bundles really
        // are — transfer_raw_material_bundles() derives the true source
        // from the locked rows, so re-check the claim against it.
        // Rule-based selection is resolved HERE, on the server, from the
        // source the form claims — never from thousands of posted ids.
        if ($selectionMode !== 'ids') {
            $sel = select_transferable_raw_material_bundles(
                $db_conn, $rawProductId, $fromCg, $fromWh, $selectionMode,
                (int) ($_POST['count_n'] ?? 0), (int) ($_POST['range_from'] ?? 0), (int) ($_POST['range_to'] ?? 0)
            );
            if ($sel['overflow']) {
                throw new StockException("More than " . number_format(RM_BUNDLE_TRANSFER_CAP) . " bundles match — narrow the range, then repeat.");
            }
            if (!$sel['ids']) {
                throw new StockException("No transferable bundles match that selection.");
            }
            $bundleIds = $sel['ids'];
        }
        $result = transfer_raw_material_bundles(
            $db_conn, $stockService, $Login_user_TYPEvl,
            $bundleIds, $toCg, $toWh, $actingUser
        );
        $chk = $db_conn->prepare(
            "SELECT COUNT(*) AS c FROM raw_material_bundle_transfers
             WHERE ref_id = ? AND from_company_godown_id = ? AND from_warehouse_id = ?"
        );
        $chk->bind_param('sii', $result['ref_id'], $fromCg, $fromWh);
        $chk->execute();
        $matches = (int) $chk->get_result()->fetch_assoc()['c'];
        $chk->close();
        if ($matches !== $result['bundles']) {
            throw new StockException("Selected bundles don't belong to the chosen source company profile + warehouse.");
        }
        $db_conn->commit();
        $_SESSION['sucMessage'] = $result['bundles'] . " bundle(s) transferred, " . number_format($result['pieces']) . " pieces moved.";
    } catch (StockException $e) {
        $db_conn->rollback();
        $_SESSION['errorMessage'] = $e->getMessage();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log('[bundle-transfer] ' . $e->getMessage());
        $_SESSION['errorMessage'] = "An error occurred. Please try again.";
    }
    header("Location: $back");
    exit;
}

if ($action === 'undo') {
    $refId = trim((string) ($_POST['ref_id'] ?? ''));
    if ($refId === '') {
        $_SESSION['errorMessage'] = "Invalid request.";
        header("Location: $back");
        exit;
    }
    $db_conn->begin_transaction();
    try {
        undo_raw_material_bundle_transfer($db_conn, $stockService, $Login_user_TYPEvl, $refId, $actingUser);
        $db_conn->commit();
        $_SESSION['sucMessage'] = "Transfer undone — original bundle(s) reopened.";
    } catch (StockException $e) {
        $db_conn->rollback();
        $_SESSION['errorMessage'] = $e->getMessage();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log('[bundle-transfer-undo] ' . $e->getMessage());
        $_SESSION['errorMessage'] = "An error occurred. Please try again.";
    }
    header("Location: $back");
    exit;
}

$_SESSION['errorMessage'] = "Unknown action.";
header("Location: $back");
exit;
