<?php
// 3% CP wallet commission on DM-order Shop Invoices a Channel Partner
// fulfils (company/ms-order-invoice-add.php's CP-stock path). Credited once
// the invoice is fully paid via the existing add-receipt.php flow — that
// invoice path never gets its own receipt at creation time, payment is
// recorded separately there exactly like any other shop invoice. Follows
// the same per-order wallet_monthly_sls_report pattern already used for OT
// Sales' "Website Order Commission" (company/ot-sale-action.php), just
// under its own commission_type label so it shows as its own line in the
// CP's wallet history (channel-partner/wallet-history.php), 2026-11-13.

const SHOP_WALLET_COMMISSION_PERCENTAGE = 3.0;

function ensureShopWalletCommissionSchema(mysqli $db_conn): void
{
    $col = $db_conn->query("SHOW COLUMNS FROM user_invoice LIKE 'fulfilled_by_cp_id'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE user_invoice ADD COLUMN fulfilled_by_cp_id INT UNSIGNED NULL DEFAULT NULL AFTER source_ms_order_id");
    }
    $col2 = $db_conn->query("SHOW COLUMNS FROM user_invoice LIKE 'shop_wallet_credited_at'");
    if ($col2 && $col2->num_rows === 0) {
        $db_conn->query("ALTER TABLE user_invoice ADD COLUMN shop_wallet_credited_at DATETIME NULL DEFAULT NULL AFTER fulfilled_by_cp_id");
    }
}

/**
 * Call after a receipt is recorded for a 'shop' invoice. No-op unless the
 * invoice is both CP-fulfilled and now fully paid (both product and courier
 * pending at/below zero) and hasn't already been credited. Idempotent —
 * shop_wallet_credited_at guards against double-crediting on a repeat
 * receipt-add (e.g. a courier-charge receipt added after the product
 * payment already completed it).
 */
function maybeCreditShopWalletCommission(mysqli $db_conn, string $invId): void
{
    ensureShopWalletCommissionSchema($db_conn);

    $stmt = $db_conn->prepare(
        "SELECT inv_number, total, courier_charges, to_user_id, fulfilled_by_cp_id, shop_wallet_credited_at, source_ms_order_id
         FROM user_invoice WHERE inv_id = ? AND to_user_type = 'shop' LIMIT 1"
    );
    $stmt->bind_param('s', $invId);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inv || $inv['fulfilled_by_cp_id'] === null || $inv['shop_wallet_credited_at'] !== null) {
        return;
    }

    // Napkin orders only — the DM's own Category choice on the Get Order
    // form (New Order's Napkin/Diaper toggle, ms_orders.order_category) is
    // the source of truth. A Lumi Baby Diaper order never earns this 3%,
    // same convention as the Target/Achieved "Napkin-only" rule elsewhere
    // (TeamTargetRollup.php). 2026-11-13, user request.
    if (!empty($inv['source_ms_order_id'])) {
        $stmtCat = $db_conn->prepare("SELECT order_category FROM ms_orders WHERE order_id = ? LIMIT 1");
        $stmtCat->bind_param('s', $inv['source_ms_order_id']);
        $stmtCat->execute();
        $catRow = $stmtCat->get_result()->fetch_assoc();
        $stmtCat->close();
        if (($catRow['order_category'] ?? 'napkin') === 'diaper') {
            return;
        }
    }

    $stmtPaid = $db_conn->prepare(
        "SELECT
            SUM(CASE WHEN payment_type IN ('advance_product','regular') THEN received ELSE 0 END) AS paid_product,
            SUM(CASE WHEN payment_type = 'courier_charge' THEN received ELSE 0 END) AS paid_courier
         FROM receipt WHERE inv_id = ?"
    );
    $stmtPaid->bind_param('s', $invId);
    $stmtPaid->execute();
    $paidRow = $stmtPaid->get_result()->fetch_assoc();
    $stmtPaid->close();

    $total           = (float) $inv['total'];
    $courierCharges  = (float) $inv['courier_charges'];
    $paidProduct     = round((float) ($paidRow['paid_product'] ?? 0), 2);
    $paidCourier     = round((float) ($paidRow['paid_courier'] ?? 0), 2);
    $pendingProduct  = round(max(0.0, $total - $courierCharges - $paidProduct), 2);
    $pendingCourier  = round(max(0.0, $courierCharges - $paidCourier), 2);

    if ($pendingProduct > 0.01 || $pendingCourier > 0.01) {
        return; // not fully paid yet
    }

    $cpId         = (int) $inv['fulfilled_by_cp_id'];
    $shopTempId   = (string) $inv['to_user_id'];
    $commission   = round($total * SHOP_WALLET_COMMISSION_PERCENTAGE / 100, 2);
    $today        = date('Y-m-d');
    $remarks      = "Invoice Number : " . $inv['inv_number'] . "<br/>Date : " . date('d/m/Y');

    $db_conn->begin_transaction();
    try {
        $ins = $db_conn->prepare(
            "INSERT INTO wallet_monthly_sls_report
                (user_type,user_id,from_date,to_date,month,year,total_sls_amount,
                 target_sls_amount,target_reached,refer_by_usertype,refer_by_userid,
                 commission_percentage,commission_amount,commission_type,remarks)
             VALUES ('shop',?,?,?,'0','0','0','0','yes','channel_partner',?,?,?,'Shop Wallet',?)"
        );
        $cpIdStr = (string) $cpId;
        $pct     = (string) SHOP_WALLET_COMMISSION_PERCENTAGE;
        $amt     = (string) $commission;
        $ins->bind_param('sssssss', $shopTempId, $today, $today, $cpIdStr, $pct, $amt, $remarks);
        $ins->execute();
        $ins->close();

        $upd = $db_conn->prepare("UPDATE user_invoice SET shop_wallet_credited_at = NOW() WHERE inv_id = ?");
        $upd->bind_param('s', $invId);
        $upd->execute();
        $upd->close();

        $db_conn->commit();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("Shop Wallet commission credit failed for invoice $invId — " . $e->getMessage());
    }
}

/**
 * Call when a return (credit note) is finalized against a CP-fulfilled Shop
 * Invoice (company/cnote_finish.php). No-op unless that invoice already had
 * its 3% commission credited (shop_wallet_credited_at set) — a return
 * against an invoice that was never fully paid never got a commission to
 * claw back in the first place. Reverses 3% of THIS return's own amount
 * (not the original invoice total), so partial returns claw back only
 * their own share and a second return on the same invoice claws back its
 * own share again. Always writes the full negative amount regardless of
 * the CP's current wallet balance -- it is allowed to go negative, exactly
 * mirroring whatever amount was actually credited for this same return's
 * share, so withdrawing the original credit first doesn't let a CP dodge
 * the clawback. Uses the same wallet_monthly_sls_report row-sum mechanism
 * add-receipt.php's credit uses, so wallet-history.php's SUM(commission_
 * amount) balance calc picks it up with no other code changes, 2026-11-13.
 */
function reverseShopWalletCommissionForReturn(mysqli $db_conn, string $invId, string $returnId, float $returnTotalAmount): void
{
    ensureShopWalletCommissionSchema($db_conn);

    $stmt = $db_conn->prepare(
        "SELECT inv_number, to_user_id, fulfilled_by_cp_id, shop_wallet_credited_at
         FROM user_invoice WHERE inv_id = ? AND to_user_type = 'shop' LIMIT 1"
    );
    $stmt->bind_param('s', $invId);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inv || $inv['fulfilled_by_cp_id'] === null || $inv['shop_wallet_credited_at'] === null) {
        return; // never got a commission credit for this invoice -- nothing to claw back
    }

    $cpId        = (int) $inv['fulfilled_by_cp_id'];
    $clawback    = round($returnTotalAmount * SHOP_WALLET_COMMISSION_PERCENTAGE / 100, 2);
    if ($clawback <= 0) {
        return;
    }

    $today    = date('Y-m-d');
    $negated  = -$clawback;
    $remarks  = "Return against Invoice Number : " . $inv['inv_number'] . " (Return " . $returnId . ")<br/>Date : " . date('d/m/Y');

    $ins = $db_conn->prepare(
        "INSERT INTO wallet_monthly_sls_report
            (user_type,user_id,from_date,to_date,month,year,total_sls_amount,
             target_sls_amount,target_reached,refer_by_usertype,refer_by_userid,
             commission_percentage,commission_amount,commission_type,remarks)
         VALUES ('shop',?,?,?,'0','0','0','0','yes','channel_partner',?,?,?,'Shop Wallet',?)"
    );
    $shopTempId = (string) $inv['to_user_id'];
    $cpIdStr    = (string) $cpId;
    $pct        = (string) SHOP_WALLET_COMMISSION_PERCENTAGE;
    $amtStr     = (string) $negated;
    $ins->bind_param('sssssss', $shopTempId, $today, $today, $cpIdStr, $pct, $amtStr, $remarks);
    $ins->execute();
    $ins->close();
}
