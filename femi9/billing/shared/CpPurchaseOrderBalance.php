<?php
/**
 * CpPurchaseOrderBalance — schema guards and live headroom calculation for
 * the Channel Partner purchase-order flow.
 *
 * Unlike Territory Partners (which fund orders from a pre-paid advance
 * wallet, see shared/TpProductType.php + TpAdvanceService), a Channel
 * Partner has no wallet at all. Their ordering capacity is a live
 * inventory-value cap: at any moment they may hold stock (valued at MRP)
 * worth up to (their total security deposit + Rs.5000) across their
 * assigned locations. As held stock sells out — channel_partner_stock is
 * debited elsewhere whenever Company invoices a TP from this CP's stock,
 * see company/tp-invoice-action.php — headroom reopens automatically.
 * There is no stored balance to decrement/increment; every caller
 * recomputes this fresh from live tables, same spirit as
 * tp_advance_payments' "balance minus reserved-by-waiting-orders" pattern
 * but with deposit+held-stock standing in for the wallet.
 */

/** Self-migrating: create the two CP purchase-order tables and the
 * pl_godown_transfers.source_po_id link column if they don't exist yet.
 * Called from every entry file that touches any of them, same pattern as
 * tpEnsureAdvanceWalletColumns() in shared/TpProductType.php. */
function cpEnsurePurchaseOrderTables(mysqli $db): void
{
    $poTable = $db->query("SHOW TABLES LIKE 'channel_partner_purchase_orders'");
    if ($poTable && $poTable->num_rows === 0) {
        $db->query("
            CREATE TABLE channel_partner_purchase_orders (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                channel_partner_id INT UNSIGNED NOT NULL,
                product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin',
                order_date DATE NOT NULL,
                status ENUM('waiting','completed','cancelled') NOT NULL DEFAULT 'waiting',
                transfer_id INT UNSIGNED NULL,
                cancel_reason VARCHAR(255) NULL,
                cancelled_at DATETIME NULL,
                cancelled_by VARCHAR(100) NULL,
                use_default_delivery_address TINYINT(1) NOT NULL DEFAULT 1,
                custom_delivery_line1 VARCHAR(255) NULL,
                custom_delivery_line2 VARCHAR(255) NULL,
                custom_delivery_city VARCHAR(100) NULL,
                custom_delivery_district VARCHAR(100) NULL,
                custom_delivery_state VARCHAR(100) NULL,
                custom_delivery_country VARCHAR(100) NULL,
                custom_delivery_pincode VARCHAR(20) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cppo_cp_status (channel_partner_id, status),
                KEY idx_cppo_transfer (transfer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    $itemsTable = $db->query("SHOW TABLES LIKE 'channel_partner_purchase_order_items'");
    if ($itemsTable && $itemsTable->num_rows === 0) {
        $db->query("
            CREATE TABLE channel_partner_purchase_order_items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                po_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                qty INT UNSIGNED NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                KEY idx_cppoi_po (po_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    $col = $db->query("SHOW COLUMNS FROM pl_godown_transfers LIKE 'source_po_id'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE pl_godown_transfers ADD COLUMN source_po_id INT UNSIGNED NULL AFTER cp_id");
        $db->query("ALTER TABLE pl_godown_transfers ADD KEY idx_plgt_source_po (source_po_id)");
    }
}

/** Sum of security deposit across every location assigned to this CP.
 * Mirrors company/cp-wallet-commission-calculator.php::getCpTotalDeposit()
 * exactly (same query) — that function takes a string cp_id (the
 * 'CP-0007'-style code); this one takes the numeric channel_partners.id,
 * since that's what every CP-session page already has as $Login_user_IDvl. */
function cpTotalDeposit(mysqli $db, int $cpId): float
{
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(n.deposit_amount), 0) AS total
         FROM channel_partner_locations cpl
         JOIN partner_location_nodes n ON n.id = cpl.location_id
         WHERE cpl.channel_partner_id = ?"
    );
    $stmt->bind_param("i", $cpId);
    $stmt->execute();
    $total = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

/** Value, at MRP, of everything this CP currently holds in stock. */
function cpHeldStockValue(mysqli $db, int $cpId): float
{
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(cps.closing_qty * p.mrp), 0) AS total
         FROM channel_partner_stock cps
         JOIN products p ON p.id = cps.product_id
         WHERE cps.channel_partner_id = ?"
    );
    $stmt->bind_param("i", $cpId);
    $stmt->execute();
    $total = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

/** Value of this CP's own still-waiting purchase-order carts — these
 * haven't landed in channel_partner_stock yet, but they've earmarked
 * headroom, same reasoning tpApoBalanceFor()'s "reserved" subtraction
 * uses for TP's advance wallet (see territory-partner/add-purchase-order.php).
 * $excludePoId lets a caller re-validate an existing PO's own headroom
 * without double-counting that PO's own value against itself. */
function cpPendingPoValue(mysqli $db, int $cpId, ?int $excludePoId = null): float
{
    $sql = "SELECT COALESCE(SUM(i.amount), 0) AS total
            FROM channel_partner_purchase_orders o
            JOIN channel_partner_purchase_order_items i ON i.po_id = o.id
            WHERE o.channel_partner_id = ? AND o.status = 'waiting'";
    if ($excludePoId !== null) $sql .= " AND o.id != ?";

    $stmt = $db->prepare($sql);
    if ($excludePoId !== null) {
        $stmt->bind_param("ii", $cpId, $excludePoId);
    } else {
        $stmt->bind_param("i", $cpId);
    }
    $stmt->execute();
    $total = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

/** The live headroom a new (or re-validated) cart must fit inside. Always
 * recomputed from current tables — never stored — so it self-corrects the
 * moment held stock sells out or the deposit changes. */
function cpAvailableHeadroom(mysqli $db, int $cpId, ?int $excludePoId = null): float
{
    $cap = cpTotalDeposit($db, $cpId) + 5000;
    $used = cpHeldStockValue($db, $cpId) + cpPendingPoValue($db, $cpId, $excludePoId);
    return max(0.0, round($cap - $used, 2));
}
