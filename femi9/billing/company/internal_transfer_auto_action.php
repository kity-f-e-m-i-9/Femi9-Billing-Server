<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/NeksomoStockBridge.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
include("RemoveSpecialChar.php");

error_reporting(0);

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header("Location: dashboard.php");
    exit;
}

// Manual internal transfers use a free-typed inv_number following a fixed
// per-leg convention observed in real data: "G/<FY>/<seq>" for Neksomo ->
// Healthcare, "S/<FY>/<seq>" for Healthcare -> LLP, FY as "26-27" (Apr-Mar),
// seq an increasing integer per prefix+FY (not reset mid-year). This
// generates the next number in that same series instead of reusing the
// internal AUTO... tempid as the invoice number.
function next_internal_transfer_invoice_number(mysqli $db, string $prefix): string
{
    $month = (int) date('n');
    $year  = (int) date('Y');
    $fy = ($month >= 4)
        ? substr((string) $year, -2) . '-' . substr((string) ($year + 1), -2)
        : substr((string) ($year - 1), -2) . '-' . substr((string) $year, -2);

    $pattern = $prefix . '/' . $fy . '/%';
    $stmt = $db->prepare(
        "SELECT MAX(CAST(SUBSTRING_INDEX(inv_number, '/', -1) AS UNSIGNED)) AS max_seq
         FROM internal_transfer_invoice WHERE inv_number LIKE ?"
    );
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $maxSeq = (int) ($stmt->get_result()->fetch_assoc()['max_seq'] ?? 0);
    $stmt->close();

    return $prefix . '/' . $fy . '/' . ($maxSeq + 1);
}

$productIds = $_REQUEST['product_id'] ?? [];
$qtyArr     = $_REQUEST['qty'] ?? [];
$rate1Arr   = $_REQUEST['rate1'] ?? []; // Neksomo -> Healthcare rate, entered on this page
$rate2Arr   = $_REQUEST['rate2'] ?? []; // Healthcare -> LLP rate, entered on this page
// Each leg's physical warehouse, per row — Neksomo, Healthcare, and LLP can
// each hold stock across more than one warehouse, so Source/Intermediate/
// Destination are independently submitted and trusted per row.
$warehouseSourceArr       = $_REQUEST['warehouse_source'] ?? [];
$warehouseIntermediateArr = $_REQUEST['warehouse_intermediate'] ?? [];
$warehouseDestArr         = $_REQUEST['warehouse_dest'] ?? [];

if (!is_array($productIds) || count($productIds) === 0) {
    $_SESSION['errorMessage'] = "No products submitted.";
    echo "<script>window.location='internal_transfer_auto?invalid';</script>";
    exit;
}

$neksomoId    = get_neksomo_godown_id($db_conn);
$healthcareId = resolve_godown_id_by_gname($db_conn, 'FEMI HEALTH CARE');
$llpId        = resolve_godown_id_by_gname($db_conn, 'FEMI NAYAN LLP');

if (!$neksomoId || !$healthcareId || !$llpId) {
    $_SESSION['errorMessage'] = "Required company profiles not found.";
    echo "<script>window.location='internal_transfer_auto?misconfigured';</script>";
    exit;
}

// Same authorization gate internal_transfer_action.php applies to the two
// godowns a manual transfer names — the three fixed godowns this feature
// always moves through are no different, so a company user not allowed to
// touch one of them can't use this shortcut to bypass that.
if (!is_godown_allowed($db_conn, (int)$neksomoId) || !is_godown_allowed($db_conn, (int)$healthcareId) || !is_godown_allowed($db_conn, (int)$llpId)) {
    $_SESSION['errorMessage'] = "You are not authorized to use this company profile.";
    echo "<script>window.location='internal_transfer_auto?unauthorized';</script>";
    exit;
}

$rows = [];
foreach ($productIds as $i => $rawPid) {
    $pid   = (int) $rawPid;
    $qty   = (int) RemoveSpecialChar($qtyArr[$i] ?? '0');
    $rate1 = (float) ($rate1Arr[$i] ?? 0);
    $rate2 = (float) ($rate2Arr[$i] ?? 0);
    $sourceWarehouseId       = filter_var($warehouseSourceArr[$i] ?? '', FILTER_VALIDATE_INT) ?: null;
    $intermediateWarehouseId = filter_var($warehouseIntermediateArr[$i] ?? '', FILTER_VALIDATE_INT) ?: null;
    $destWarehouseId         = filter_var($warehouseDestArr[$i] ?? '', FILTER_VALIDATE_INT) ?: null;
    if ($pid <= 0 || $qty <= 0) continue;
    $rows[] = [
        'pid' => $pid, 'qty' => $qty, 'rate1' => $rate1, 'rate2' => $rate2,
        'source_warehouse_id' => $sourceWarehouseId,
        'intermediate_warehouse_id' => $intermediateWarehouseId,
        'dest_warehouse_id' => $destWarehouseId,
    ];
}

if (empty($rows)) {
    $_SESSION['errorMessage'] = "No valid quantities submitted.";
    echo "<script>window.location='internal_transfer_auto?invalid';</script>";
    exit;
}

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

// Unchecking a line in the Order Breakdown / View All Orders modals only
// ever adjusts the qty number for THIS transfer run — it's a same-run
// adjustment, not a "skip this order today" decision. An order left out
// this way simply isn't part of $legTwoQty below, so it's never marked
// 'transferred' and naturally reappears as outstanding demand the very
// next time Auto Transfer runs, same day or not. No skip-table row is
// written for it (contrast with reason='transferred' below, which really
// must persist — that one reflects stock that has actually moved).
$username     = htmlspecialchars(strip_tags(trim($_SESSION['LOGIN_USER'] ?? '')), ENT_QUOTES, 'UTF-8');
$usertype     = htmlspecialchars(strip_tags(trim($Login_user_TYPEvl ?? '')), ENT_QUOTES, 'UTF-8');
$date         = date('Y-m-d');

$tempidBase = 'AUTO' . date('YmdHis') . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
$tempid1 = $tempidBase . '-N1'; // Neksomo -> Healthcare
$tempid2 = $tempidBase . '-N2'; // Healthcare -> LLP

$cappedRows = [];

$db_conn->begin_transaction();

try {
    // Generated inside the same transaction as the inserts that consume
    // them, so the MAX-based lookup sees any number this same batch has
    // already claimed.
    $invNumber1 = next_internal_transfer_invoice_number($db_conn, 'G'); // Neksomo -> Healthcare
    $invNumber2 = next_internal_transfer_invoice_number($db_conn, 'S'); // Healthcare -> LLP

    $stmtInvChk = $db_conn->prepare("SELECT COUNT(*) AS n FROM internal_transfer_invoice WHERE tempid = ?");
    $stmtInvIns = $db_conn->prepare(
        "INSERT INTO internal_transfer_invoice (tempid, inv_id, inv_number, courier_charges)
         VALUES (?, '0', ?, '0')"
    );
    $stmtProdIns = $db_conn->prepare(
        "INSERT INTO internal_transfer
             (tempid, send_from, send_to, date, product_id, qty, price, discount,
              sub_total, gst, gst_type, taxable_value, gst_amount, total, hsn, username, usertype)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmtProd = $db_conn->prepare("SELECT gst, gst_type, hsn FROM products WHERE id = ?");

    /**
     * Writes one internal_transfer_invoice (once per tempid) + one
     * internal_transfer row, then performs the StockService
     * transferOut/transferIn pair. Returns the actual qty moved
     * (may be less than $qty if stock is insufficient at commit time —
     * re-validated here, never trusting the popup's earlier snapshot).
     */
    $writeLeg = function (
        string $tempid, string $invNumber, string $sendFrom, string $sendTo, int $pid, int $qty, float $rate,
        ?int $sourceWarehouseId, ?int $destWarehouseId
    ) use (
        $db_conn, $stockService, $createdBy, $username, $usertype, $date,
        $stmtInvChk, $stmtInvIns, $stmtProdIns, $stmtProd, $Login_user_TYPEvl
    ): int {
        $available = $stockService->getClosingQty($pid, $Login_user_TYPEvl, $sendFrom, $sourceWarehouseId);
        $actualQty = min($qty, (int) ($available ?? 0));
        if ($actualQty <= 0) return 0;

        $stmtProd->bind_param('i', $pid);
        $stmtProd->execute();
        $prod = $stmtProd->get_result()->fetch_assoc();
        if (!$prod) return 0;

        $gst      = (float) $prod['gst'];
        $gstType  = ($prod['gst_type'] === 'inclusive') ? 'inclusive' : 'exclusive';
        $hsn      = $prod['hsn'];
        $subTotal = $rate * $actualQty;

        if ($gstType === 'inclusive') {
            $total         = $subTotal;
            $taxableValue  = number_format($total / (1 + $gst / 100), 2, '.', '');
            $gstAmount     = number_format($total - $taxableValue, 2, '.', '');
        } else {
            $taxableValue = number_format($subTotal, 2, '.', '');
            $gstAmount    = number_format($subTotal * $gst / 100, 2, '.', '');
            $total        = (float) $taxableValue + (float) $gstAmount;
        }

        $stmtInvChk->bind_param('s', $tempid);
        $stmtInvChk->execute();
        if ((int) $stmtInvChk->get_result()->fetch_assoc()['n'] === 0) {
            $stmtInvIns->bind_param('ss', $tempid, $invNumber);
            $stmtInvIns->execute();
        }

        $disc = 0.0;
        $stmtProdIns->bind_param(
            'ssssiiddddsssssss',
            $tempid, $sendFrom, $sendTo, $date, $pid, $actualQty,
            $rate, $disc, $subTotal, $gst, $gstType, $taxableValue, $gstAmount, $total, $hsn,
            $username, $usertype
        );
        $stmtProdIns->execute();

        $outResult = $stockService->transferOut(
            $pid, $Login_user_TYPEvl, $sendFrom, $actualQty,
            'transfer', $tempid, $createdBy, true, $sourceWarehouseId
        );
        $stockService->transferIn(
            $pid, $Login_user_TYPEvl, $sendTo, $actualQty,
            'transfer', $tempid, $createdBy, true,
            $outResult['consumed_rate'] ?? null, $destWarehouseId
        );

        return $actualQty;
    };

    foreach ($rows as $row) {
        $pid = $row['pid'];
        $requestedQty = $row['qty'];

        // Snapshot which orders are currently behind this product's demand
        // BEFORE moving stock, so they can be marked covered afterward —
        // tp_purchase_orders.status / ot_sales_invoice.status / wa_po_
        // purchase_orders.status stay 'waiting'/'draft' even after a
        // successful transfer (by design, per the spec — fulfilling a PO
        // is still a separate manual step), so without this a second
        // Transfer Now click would re-count and re-move the same demand.
        $contributingOrders = get_auto_transfer_breakdown_for_product($db_conn, $pid, $llpId);

        // Each leg's endpoint warehouse is independently user-selected:
        // Leg 1 moves Neksomo's Source Godown stock into Healthcare's
        // Intermediate Godown; Leg 2 moves that same Intermediate Godown
        // stock into LLP's Destination Godown. Healthcare's own warehouse
        // (Intermediate) is Leg 1's destination AND Leg 2's source — it's
        // one picker, not two, since it's the single physical place that
        // leg's stock actually sits between the two hops.
        $legOneQty = $writeLeg($tempid1, $invNumber1, (string) $neksomoId, (string) $healthcareId, $pid, $requestedQty, $row['rate1'], $row['source_warehouse_id'], $row['intermediate_warehouse_id']);
        if ($legOneQty <= 0) continue;

        $legTwoQty = $writeLeg($tempid2, $invNumber2, (string) $healthcareId, (string) $llpId, $pid, $legOneQty, $row['rate2'], $row['intermediate_warehouse_id'], $row['dest_warehouse_id']);

        if ($legTwoQty < $requestedQty) {
            $cappedRows[] = "Product #$pid: requested $requestedQty, transferred $legTwoQty";
        }

        save_auto_transfer_default_rate($db_conn, $pid, $row['rate1'], $row['rate2'], $createdBy);

        // Only mark an order 'transferred' once its own qty actually fit
        // within what was really moved ($legTwoQty) — TP orders claim their
        // share first (real, completable purchase orders), OT drafts only
        // get whatever's left over. Walking each source's own list
        // cumulatively means an order past the point stock ran out is left
        // unmarked, so it correctly reappears as outstanding demand next
        // time instead of being silently marked done with nothing moved
        // for it (previously every contributing order was marked
        // regardless of whether the cap actually covered it).
        $remaining = $legTwoQty;
        foreach (['tp', 'ot'] as $sourceType) {
            foreach ($contributingOrders[$sourceType] as $order) {
                $orderQty = (int) $order['qty'];
                if ($orderQty <= 0) continue;
                if ($remaining < $orderQty) break; // this and every later order in this source's list stay unmarked
                $remaining -= $orderQty;
                $sourceRef = substr($order['source_id'], strlen($sourceType) + 1); // strip "tp:"/"ot:"/"wa:" prefix
                mark_auto_transfer_order_skipped($db_conn, $sourceType, $sourceRef, 'transferred', $createdBy, $tempid2);
            }
        }
    }

    $stmtInvChk->close();
    $stmtInvIns->close();
    $stmtProdIns->close();
    $stmtProd->close();

    $db_conn->commit();
} catch (StockException $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = "Stock error: " . $e->getMessage();
    echo "<script>window.location='internal_transfer_auto?InvalidStock&&AlertStockError';</script>";
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("internal_transfer_auto_action error: " . $e->getMessage());
    $_SESSION['errorMessage'] = "An error occurred. Please try again.";
    echo "<script>window.location='internal_transfer_auto?saveerror';</script>";
    exit;
}

$_SESSION['sucMessage'] = "Auto transfer complete (Neksomo->Healthcare: $invNumber1, Healthcare->LLP: $invNumber2)."
    . (empty($cappedRows) ? "" : " Capped: " . implode('; ', $cappedRows));
echo "<script>window.location='internal_transfer_manage';</script>";
