<?php
// femi9/billing/company/include/AutoTransferDemand.php
//
// Pure demand-aggregation and stock-capping logic for the one-click
// auto internal transfer feature. See
// docs/superpowers/specs/2026-09-18-auto-internal-transfer-design.md.
//
// No session/HTML dependencies — takes a mysqli connection and returns
// plain arrays/scalars, so it can be exercised directly by
// includes/tests/AutoTransferDemandTest.php.

/**
 * Resolves a company_godown.id by exact gname match. Returns null if
 * no matching row exists.
 */
function resolve_godown_id_by_gname(mysqli $db_conn, string $gname): ?int
{
    $stmt = $db_conn->prepare("SELECT id FROM company_godown WHERE gname = ? LIMIT 1");
    $stmt->bind_param('s', $gname);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['id'] : null;
}

// Self-migrating. One row per (source_type, source_ref, skip_date) means
// "don't count this specific order's qty toward today's auto-transfer
// requirement" — reason 'transferred' when a previous Transfer Now click
// already moved its stock today (see internal_transfer_auto_action.php),
// so a completed transfer is never double-counted. (Reason 'excluded' —
// staff explicitly opting an order out via a "Not Today" button — existed
// previously and the ENUM/column still allows it for any old rows, but
// nothing creates new 'excluded' rows any more.) Either way the order's
// OWN status (tp_purchase_orders.status / ot_sales_invoice.status /
// wa_po_purchase_orders.status) is never touched — this table is purely
// "don't ask again today," scoped by date so it naturally clears itself
// tomorrow if the order is still genuinely outstanding.
function ensure_auto_transfer_skip_table(mysqli $db_conn): void
{
    $db_conn->query("
        CREATE TABLE IF NOT EXISTS auto_transfer_skip_today (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source_type ENUM('tp','ot','wa','cp') NOT NULL,
            source_ref VARCHAR(64) NOT NULL,
            skip_date DATE NOT NULL,
            reason ENUM('excluded','transferred') NOT NULL DEFAULT 'excluded',
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_auto_transfer_skip (source_type, source_ref, skip_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    // Added after the table's original release — the -N2 tempid of the
    // transfer run that marked this row 'transferred', so undo_auto_
    // transfer() can find and delete exactly the rows one specific run
    // created instead of leaving every order it covered permanently
    // hidden from Required Qty after the stock moved back.
    $col = $db_conn->query("SHOW COLUMNS FROM auto_transfer_skip_today LIKE 'transfer_tempid'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE auto_transfer_skip_today ADD COLUMN transfer_tempid VARCHAR(64) NULL AFTER reason");
    }

    // Widen source_type for environments where 'cp' didn't exist yet —
    // Channel Partner purchase orders joining TP/OT as a third Auto
    // Transfer demand source.
    $typeCol = $db_conn->query("SHOW COLUMNS FROM auto_transfer_skip_today LIKE 'source_type'")->fetch_assoc();
    if ($typeCol && strpos($typeCol['Type'], "'cp'") === false) {
        $db_conn->query("ALTER TABLE auto_transfer_skip_today MODIFY COLUMN source_type ENUM('tp','ot','wa','cp') NOT NULL");
    }

    // How much of this specific order+product this ONE event actually
    // moved — needed now that a 'transferred' row can represent a PARTIAL
    // fulfillment (see *_transferred_qty columns below), not just "this
    // order is fully done." Old rows (all full-order marks from before
    // partial tracking existed) have no way to know their own original
    // qty retroactively, so they're left NULL — get_auto_transfer_skipped_
    // today() falls back to the order's current full qty for those.
    $qtyCol = $db_conn->query("SHOW COLUMNS FROM auto_transfer_skip_today LIKE 'qty'");
    if ($qtyCol && $qtyCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE auto_transfer_skip_today ADD COLUMN qty INT NULL AFTER transfer_tempid");
    }
}

// Self-migrating. How much of this order LINE's own qty has actually been
// moved by Auto Transfer so far, across every run/day — unlike the
// skip-table above (which only ever meant "don't recount today," reset
// daily), this is permanent running progress toward the line's full qty,
// since a partial transfer (operator reduces the popup's qty input for
// just this run) must still correctly reduce what's required NEXT time,
// today or any later day. transferred_qty is never allowed to exceed the
// line's own qty (enforced at the call site, not by a DB constraint).
function ensure_auto_transfer_transferred_qty_columns(mysqli $db_conn): void
{
    $targets = [
        'tp_purchase_order_items'          => 'qty',
        'channel_partner_purchase_order_items' => 'qty',
        'ot_sales'                          => 'qty',
    ];
    foreach ($targets as $table => $afterCol) {
        $col = $db_conn->query("SHOW COLUMNS FROM `$table` LIKE 'transferred_qty'");
        if ($col && $col->num_rows === 0) {
            $db_conn->query("ALTER TABLE `$table` ADD COLUMN transferred_qty INT NOT NULL DEFAULT 0 AFTER `$afterCol`");
        }
    }
}

// Self-migrating — same guard as ot-sale-action.php's own (must stay in
// sync with it). Every function here that reads ot_sales_invoice.status
// calls this first: a fresh/never-touched ot_sales_invoice table has no
// such column until either that file or this one creates it, and this
// module can run first (e.g. Auto Transfer opened before any OT sale was
// ever drafted), so it can't assume the other file already migrated it.
function ensure_ot_sales_invoice_status_column(mysqli $db_conn): void
{
    $col = $db_conn->query("SHOW COLUMNS FROM ot_sales_invoice LIKE 'status'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE ot_sales_invoice ADD COLUMN status ENUM('confirmed','draft') NOT NULL DEFAULT 'confirmed' AFTER cat");
    }
}

// Self-migrating — same guard as territory-partner/purchase-order-action.php's
// own (must stay in sync with it; see also db_migrations/2026_09_18_tp_
// purchase_orders_preferred_cp_id.sql, written for the same reason). This
// column is only ever created lazily by that file's first PO submission
// after the Channel-Partner-preference feature shipped — any environment
// where that code path hasn't run yet (e.g. production, if no PO has been
// submitted there since) is missing it entirely. Every demand query here
// that filters on preferred_cp_id calls this first so it can't hit
// "Unknown column 'preferred_cp_id'" regardless of whether that other
// file's guard has ever fired.
function ensure_tp_purchase_orders_preferred_cp_id_column(mysqli $db_conn): void
{
    $col = $db_conn->query("SHOW COLUMNS FROM tp_purchase_orders LIKE 'preferred_cp_id'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE tp_purchase_orders ADD COLUMN preferred_cp_id INT NULL AFTER approver_ss_id");
    }
}

// Self-migrating. One row per product_id holds the last-used rate for
// each leg (Neksomo->Healthcare, Healthcare->LLP), so the Auto Transfer
// page can pre-fill its rate inputs instead of starting blank every time
// — staff can still edit/override before Transfer Now, this is only a
// starting value. Upserted every time a transfer actually completes with
// a non-zero rate, so it stays current with whatever was last charged.
function ensure_auto_transfer_default_rates_table(mysqli $db_conn): void
{
    $db_conn->query("
        CREATE TABLE IF NOT EXISTS auto_transfer_default_rates (
            product_id INT NOT NULL PRIMARY KEY,
            rate_healthcare DECIMAL(10,2) NOT NULL DEFAULT 0,
            rate_llp DECIMAL(10,2) NOT NULL DEFAULT 0,
            updated_by VARCHAR(100) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/**
 * Returns [product_id => ['healthcare' => float, 'llp' => float]] for
 * every product with a known default rate — used to pre-fill the Auto
 * Transfer page's rate inputs.
 */
function get_auto_transfer_default_rates(mysqli $db_conn): array
{
    ensure_auto_transfer_default_rates_table($db_conn);
    $rates = [];
    $res = $db_conn->query("SELECT product_id, rate_healthcare, rate_llp FROM auto_transfer_default_rates");
    while ($row = $res->fetch_assoc()) {
        $rates[(int) $row['product_id']] = [
            'healthcare' => (float) $row['rate_healthcare'],
            'llp'        => (float) $row['rate_llp'],
        ];
    }
    return $rates;
}

/**
 * Remembers the rate actually used for one product's transfer, so next
 * time it's the pre-filled starting value. Only stores non-zero rates —
 * a blank/zero entry shouldn't overwrite a previously known real rate.
 */
function save_auto_transfer_default_rate(mysqli $db_conn, int $productId, float $rateHealthcare, float $rateLlp, ?string $updatedBy = null): void
{
    if ($rateHealthcare <= 0 && $rateLlp <= 0) return;
    ensure_auto_transfer_default_rates_table($db_conn);
    $stmt = $db_conn->prepare(
        "INSERT INTO auto_transfer_default_rates (product_id, rate_healthcare, rate_llp, updated_by)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             rate_healthcare = IF(? > 0, ?, rate_healthcare),
             rate_llp        = IF(? > 0, ?, rate_llp),
             updated_by      = ?"
    );
    $stmt->bind_param(
        'iddsdddds',
        $productId, $rateHealthcare, $rateLlp, $updatedBy,
        $rateHealthcare, $rateHealthcare, $rateLlp, $rateLlp, $updatedBy
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * Explicitly sets a product's default rate — used by the "Transfer Price"
 * management page, unlike save_auto_transfer_default_rate() (called after
 * an actual transfer) this always overwrites both values, including down
 * to 0, since here the staff member is deliberately setting the rate
 * rather than a transfer incidentally recording what it used.
 */
function set_auto_transfer_default_rate(mysqli $db_conn, int $productId, float $rateHealthcare, float $rateLlp, ?string $updatedBy = null): void
{
    ensure_auto_transfer_default_rates_table($db_conn);
    $stmt = $db_conn->prepare(
        "INSERT INTO auto_transfer_default_rates (product_id, rate_healthcare, rate_llp, updated_by)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             rate_healthcare = ?,
             rate_llp        = ?,
             updated_by      = ?"
    );
    $stmt->bind_param(
        'iddsdds',
        $productId, $rateHealthcare, $rateLlp, $updatedBy,
        $rateHealthcare, $rateLlp, $updatedBy
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * Marks one order skipped for today — INSERT IGNORE so calling this twice
 * for the same order/date (e.g. a double-click) is harmless. $transferTempid
 * (the -N2 leg's tempid) is only meaningful for reason='transferred' — it's
 * how undo_auto_transfer() later finds exactly the rows one specific
 * transfer run created, so undoing that run can un-skip only its own
 * orders instead of leaving them permanently hidden or clearing rows
 * created by other runs.
 */
function mark_auto_transfer_order_skipped(mysqli $db_conn, string $sourceType, string $sourceRef, string $reason, ?string $createdBy = null, ?string $transferTempid = null): void
{
    ensure_auto_transfer_skip_table($db_conn);
    $stmt = $db_conn->prepare(
        "INSERT IGNORE INTO auto_transfer_skip_today (source_type, source_ref, skip_date, reason, transfer_tempid, created_by)
         VALUES (?, ?, CURDATE(), ?, ?, ?)"
    );
    $stmt->bind_param('sssss', $sourceType, $sourceRef, $reason, $transferTempid, $createdBy);
    $stmt->execute();
    $stmt->close();
}

/**
 * Records a PARTIAL (or full) fulfillment of one order line by Auto
 * Transfer — called instead of mark_auto_transfer_order_skipped(...,
 * 'transferred', ...) now that an order doesn't have to be fully covered
 * in one run to make progress. Two things happen:
 *
 *  1. The order line's own transferred_qty (tp_purchase_order_items /
 *     channel_partner_purchase_order_items / ot_sales) is bumped by
 *     $qtyMoved, capped at the line's own qty — this is PERMANENT
 *     progress, not scoped to today, since a 10-of-50 partial transfer
 *     must still correctly show only 40 remaining tomorrow, not the
 *     full 50 again.
 *  2. A dated log row is written/accumulated in auto_transfer_skip_today
 *     (reason='transferred', carrying $qtyMoved) purely for the
 *     "Already Transferred Today"/date-picker history view — demand
 *     calculation itself (get_auto_transfer_requirements() etc.) no
 *     longer reads this table for 'transferred' rows at all, only for
 *     'excluded' (Delete Selected) ones, so this log is historical
 *     only. If more than one run touches the same order+product on the
 *     same day, their qty accumulates into one row (transfer_tempid
 *     reflects whichever run touched it last) — a rare edge case where
 *     undo_auto_transfer() can't cleanly isolate one specific run's
 *     share for an order+product touched by two runs the same day;
 *     acceptable since the running transferred_qty itself (which
 *     actually gates demand) is still correctly adjusted by undo either way.
 *
 * $sourceRef is "<po_id>:<product_id>" for tp/cp, "<tempid>:<product_id>"
 * for ot — same shape callers already build for mark_auto_transfer_
 * order_skipped().
 */
function record_auto_transfer_partial(mysqli $db_conn, string $sourceType, string $sourceRef, int $qtyMoved, ?string $createdBy, string $transferTempid): void
{
    if ($qtyMoved <= 0) { return; }
    ensure_auto_transfer_skip_table($db_conn);
    ensure_auto_transfer_transferred_qty_columns($db_conn);

    $parts = explode(':', $sourceRef, 2);
    if (count($parts) !== 2) { return; }
    [$orderKey, $productIdStr] = $parts;
    $productId = (int) $productIdStr;

    if ($sourceType === 'ot') {
        $stmt = $db_conn->prepare(
            "UPDATE ot_sales SET transferred_qty = LEAST(qty, transferred_qty + ?) WHERE tempid = ? AND prid = ?"
        );
        $stmt->bind_param('isi', $qtyMoved, $orderKey, $productId);
    } elseif ($sourceType === 'cp') {
        $poId = (int) $orderKey;
        $stmt = $db_conn->prepare(
            "UPDATE channel_partner_purchase_order_items SET transferred_qty = LEAST(qty, transferred_qty + ?) WHERE po_id = ? AND product_id = ?"
        );
        $stmt->bind_param('iii', $qtyMoved, $poId, $productId);
    } else {
        $poId = (int) $orderKey;
        $stmt = $db_conn->prepare(
            "UPDATE tp_purchase_order_items SET transferred_qty = LEAST(qty, transferred_qty + ?) WHERE po_id = ? AND product_id = ?"
        );
        $stmt->bind_param('iii', $qtyMoved, $poId, $productId);
    }
    $stmt->execute();
    $stmt->close();

    $logStmt = $db_conn->prepare(
        "INSERT INTO auto_transfer_skip_today (source_type, source_ref, skip_date, reason, transfer_tempid, qty, created_by)
         VALUES (?, ?, CURDATE(), 'transferred', ?, ?, ?)
         ON DUPLICATE KEY UPDATE qty = COALESCE(qty, 0) + VALUES(qty), transfer_tempid = VALUES(transfer_tempid), created_by = VALUES(created_by)"
    );
    $logStmt->bind_param('sssis', $sourceType, $sourceRef, $transferTempid, $qtyMoved, $createdBy);
    $logStmt->execute();
    $logStmt->close();
}

/**
 * Everything already transferred today — the "Excluded Today" tab's data
 * source, so a completed transfer's orders are visible and explainable
 * (why a product no longer shows up in Required Qty) even though there's
 * nothing to undo there (that stock already moved). Resolves each
 * (order, product) skip row back to a human label the same way the other
 * list functions do.
 *
 * Returns ['tp' => [...], 'ot' => [...]], each entry shaped
 * ['source_id' => string, 'label' => string, 'product_name' => string,
 * 'qty' => int, 'reason' => 'transferred'|'excluded', 'order_type' =>
 * 'napkin'|'diaper']. order_type is the PO's own product_type for a TP
 * row, or the product's own category for an OT row (same convention
 * get_auto_transfer_orders_overview() uses) — lets the client's Napkin/
 * Lumi Diaper filter apply to this tab too, not just the TP/OT tabs.
 * reason='transferred'
 * means Auto Transfer actually moved this line's stock today;
 * reason='excluded' means it was deliberately left out via the "View All
 * Orders" modal's "Delete Selected" button (delete-auto-transfer-order.php)
 * — stock was never touched for that one. The client renders these with
 * different badges ("Already transferred" vs "Deleted") but both are
 * undoable the same way via unskip-auto-transfer-order.php's "Re-add"
 * button. A skip whose underlying order/product no longer resolves (rare
 * — e.g. the PO was deleted after being skipped) still shows using the raw
 * order_key/product_id so it stays
 * visible and undoable rather than silently vanishing.
 *
 * Deliberately NOT filtered by the order's own order_date — this tab's
 * whole point is "what stock actually moved today," regardless of when
 * the underlying PO/draft was originally raised (an older order whose
 * shortage got resolved today is still a today transfer). An order_date
 * filter was tried and reverted 2026-09-24 after it hid genuinely-today
 * transfers whose PO happened to be raised on an earlier date.
 */
function get_auto_transfer_skipped_today(mysqli $db_conn, ?string $date = null): array
{
    ensure_auto_transfer_skip_table($db_conn);
    $skipped = ['tp' => [], 'ot' => [], 'cp' => []];

    // Defaults to today (the function's original, still-literal behavior)
    // but accepts any past date so the "Already Transferred Today" tab can
    // be pointed at an earlier day instead of being stuck on today only.
    if ($date === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    $stmt = $db_conn->prepare(
        "SELECT source_type, source_ref, reason, qty FROM auto_transfer_skip_today
         WHERE skip_date = ? AND source_type IN ('tp', 'ot', 'cp') AND reason IN ('transferred', 'excluded') ORDER BY id"
    );
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $sourceType = $row['source_type'];
        $parts = explode(':', $row['source_ref'], 2);
        if (count($parts) !== 2) continue;
        [$orderKey, $productIdStr] = $parts;
        $productId = (int) $productIdStr;

        $prodStmt = $db_conn->prepare("SELECT productName, category FROM products WHERE id = ?");
        $prodStmt->bind_param('i', $productId);
        $prodStmt->execute();
        $prodRow = $prodStmt->get_result()->fetch_assoc();
        $productName = $prodRow['productName'] ?? "Product #$productId";
        // Fallback only — TP rows overwrite this with the PO's own
        // product_type just below, since a PO's declared type is the
        // authoritative one (same convention get_auto_transfer_orders_
        // overview() uses); this products.category read only matters for OT
        // rows, which have no per-order type column of their own.
        $orderType = ($prodRow['category'] ?? '') === 'diaper' ? 'diaper' : 'napkin';
        $prodStmt->close();

        if ($sourceType === 'tp') {
            $poId = (int) $orderKey;
            $poStmt = $db_conn->prepare(
                "SELECT tp.name AS tp_name, tp.tp_id AS tp_code, po.product_type
                 FROM tp_purchase_orders po
                 INNER JOIN territory_partners tp ON tp.id = po.territory_partner_id
                 WHERE po.id = ?"
            );
            $poStmt->bind_param('i', $poId);
            $poStmt->execute();
            $poRow = $poStmt->get_result()->fetch_assoc();
            $poStmt->close();
            $label = $poRow ? ($poRow['tp_name'] . ' (' . $poRow['tp_code'] . ') — PO #' . $poId) : ('PO #' . $poId);
            if ($poRow) $orderType = $poRow['product_type'] === 'diaper' ? 'diaper' : 'napkin';

            $qtyStmt = $db_conn->prepare(
                "SELECT SUM(qty) AS qty FROM tp_purchase_order_items WHERE po_id = ? AND product_id = ?"
            );
            $qtyStmt->bind_param('ii', $poId, $productId);
            $qtyStmt->execute();
            $derivedQty = (int) ($qtyStmt->get_result()->fetch_assoc()['qty'] ?? 0);
            $qtyStmt->close();
        } elseif ($sourceType === 'cp') {
            $poId = (int) $orderKey;
            $poStmt = $db_conn->prepare(
                "SELECT cp.name AS cp_name, cp.cp_id AS cp_code, po.product_type
                 FROM channel_partner_purchase_orders po
                 INNER JOIN channel_partners cp ON cp.id = po.channel_partner_id
                 WHERE po.id = ?"
            );
            $poStmt->bind_param('i', $poId);
            $poStmt->execute();
            $poRow = $poStmt->get_result()->fetch_assoc();
            $poStmt->close();
            $label = $poRow ? ($poRow['cp_name'] . ' (' . $poRow['cp_code'] . ') — PO #' . $poId) : ('PO #' . $poId);
            if ($poRow) $orderType = $poRow['product_type'] === 'diaper' ? 'diaper' : 'napkin';

            $qtyStmt = $db_conn->prepare(
                "SELECT SUM(qty) AS qty FROM channel_partner_purchase_order_items WHERE po_id = ? AND product_id = ?"
            );
            $qtyStmt->bind_param('ii', $poId, $productId);
            $qtyStmt->execute();
            $derivedQty = (int) ($qtyStmt->get_result()->fetch_assoc()['qty'] ?? 0);
            $qtyStmt->close();
        } else {
            $otStmt = $db_conn->prepare(
                "SELECT os.customer_name, osi.cat FROM ot_sales os
                 INNER JOIN ot_sales_invoice osi ON osi.tempid = os.tempid
                 WHERE os.tempid = ? LIMIT 1"
            );
            $otStmt->bind_param('s', $orderKey);
            $otStmt->execute();
            $otRow = $otStmt->get_result()->fetch_assoc();
            $otStmt->close();
            $label = $otRow ? (($otRow['customer_name'] ?: 'Draft Order') . ' (' . $otRow['cat'] . ')') : $orderKey;

            $qtyStmt = $db_conn->prepare(
                "SELECT SUM(qty) AS qty FROM ot_sales WHERE tempid = ? AND prid = ?"
            );
            $qtyStmt->bind_param('si', $orderKey, $productId);
            $qtyStmt->execute();
            $derivedQty = (int) ($qtyStmt->get_result()->fetch_assoc()['qty'] ?? 0);
            $qtyStmt->close();
        }

        // A 'transferred' row's own logged qty (see record_auto_transfer_
        // partial()) is the ACTUAL amount this specific event moved, which
        // may be less than the order line's full/current qty now that
        // partial transfers exist — show that, not the derived full
        // amount. Falls back to the derived full qty for 'excluded' rows
        // (never carry a logged qty, Delete Selected always excludes the
        // whole line) and for any 'transferred' row predating this column
        // (qty still NULL there).
        $qty = ($row['reason'] === 'transferred' && $row['qty'] !== null)
            ? (int) $row['qty']
            : $derivedQty;

        $skipped[$sourceType][] = [
            'source_id'    => $sourceType . ':' . $row['source_ref'],
            'label'        => $label,
            'product_name' => $productName,
            'qty'          => $qty,
            'reason'       => $row['reason'],
            'order_type'   => $orderType,
        ];
    }

    return $skipped;
}

/**
 * Aggregates the CURRENT (not just today's) required quantity per product
 * from every still-outstanding order/draft, regardless of when it was
 * created:
 *  - tp_purchase_order_items joined to tp_purchase_orders
 *    WHERE status = 'waiting' (any order_date — a PO raised yesterday
 *    and still waiting is just as much "required" as one raised today;
 *    'cancelled'/'completed' POs never match this)
 *      AND approver_type = 'company' (excludes SS-approved orders —
 *        those are fulfilled through the Super Stockist's own stock,
 *        not this company's Neksomo/Healthcare/LLP chain)
 *      AND preferred_cp_id IS NULL (excludes orders the TP wants
 *        sourced via a Channel Partner instead — Auto Transfer only
 *        ever moves stock between company godowns, never CP stock)
 *  - ot_sales joined to ot_sales_invoice (on tempid)
 *    WHERE ot_sales_invoice.status = 'draft'
 *      AND ot_sales.godownid = $llpGodownId (any date — same reasoning)
 *
 * WhatsApp-bot orders (api/wa-po/, its own wa_po_purchase_orders tables)
 * are deliberately NOT included as a third source here — confirmed
 * 2026-09-18 that they're not wanted in this feature's demand calc.
 *
 * Returns [product_id => ['tp' => qty, 'ot' => qty]], omitting products
 * where both sources are 0. Kept separate (not summed into one number)
 * because TP demand is real, completable purchase orders, while OT
 * demand is draft channel orders that — until they're confirmed via
 * ot-sale-confirm-action.php — can sit indefinitely and must never be
 * allowed to starve real TP demand of limited stock when the two
 * compete for the same availability cap. See
 * cap_auto_transfer_qty_by_source() for how callers should apply this.
 */
function get_auto_transfer_requirements(mysqli $db_conn, int $llpGodownId): array
{
    ensure_auto_transfer_skip_table($db_conn);
    ensure_ot_sales_invoice_status_column($db_conn);
    ensure_tp_purchase_orders_preferred_cp_id_column($db_conn);
    ensure_auto_transfer_transferred_qty_columns($db_conn);
    $requirements = [];

    // Demand is the order line's own REMAINING qty (qty - transferred_qty),
    // not its full original qty — a prior partial Auto Transfer run
    // permanently reduces what's still required, today or any later day
    // (see record_auto_transfer_partial()). Skip-matching here is only
    // against reason='excluded' (staff's "Delete Selected") — a prior
    // 'transferred' row no longer gates anything, since transferred_qty
    // itself already reflects that progress.
    //
    // Skip-matching is per (PO, product) — CONCAT'd since a single PO can
    // carry multiple products, and excluding one product from it must
    // never also exclude the PO's other products (see the matching
    // comment on get_auto_transfer_breakdown_for_product()).
    $tpStmt = $db_conn->prepare(
        "SELECT poi.product_id AS product_id, SUM(poi.qty - poi.transferred_qty) AS total_qty
         FROM tp_purchase_order_items poi
         INNER JOIN tp_purchase_orders po ON po.id = poi.po_id
         WHERE po.status = 'waiting' AND po.approver_type = 'company' AND po.preferred_cp_id IS NULL
           AND poi.qty > poi.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'tp' AND s.source_ref = CONCAT(po.id, ':', poi.product_id) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )
         GROUP BY poi.product_id"
    );
    $tpStmt->execute();
    $tpResult = $tpStmt->get_result();
    while ($row = $tpResult->fetch_assoc()) {
        $pid = (int) $row['product_id'];
        if (!isset($requirements[$pid])) $requirements[$pid] = ['tp' => 0, 'ot' => 0, 'cp' => 0];
        $requirements[$pid]['tp'] += (int) $row['total_qty'];
    }
    $tpStmt->close();

    // Channel Partner purchase orders — same shape as TP's own, just
    // against channel_partner_purchase_orders/_items. No approver_type/
    // preferred_cp_id equivalent here: a CP PO is always sourced from the
    // company's own Neksomo/Healthcare/LLP chain, never routed elsewhere.
    $cpStmt = $db_conn->prepare(
        "SELECT cpi.product_id AS product_id, SUM(cpi.qty - cpi.transferred_qty) AS total_qty
         FROM channel_partner_purchase_order_items cpi
         INNER JOIN channel_partner_purchase_orders po ON po.id = cpi.po_id
         WHERE po.status = 'waiting'
           AND cpi.qty > cpi.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'cp' AND s.source_ref = CONCAT(po.id, ':', cpi.product_id) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )
         GROUP BY cpi.product_id"
    );
    $cpStmt->execute();
    $cpResult = $cpStmt->get_result();
    while ($row = $cpResult->fetch_assoc()) {
        $pid = (int) $row['product_id'];
        if (!isset($requirements[$pid])) $requirements[$pid] = ['tp' => 0, 'ot' => 0, 'cp' => 0];
        $requirements[$pid]['cp'] += (int) $row['total_qty'];
    }
    $cpStmt->close();

    $otStmt = $db_conn->prepare(
        "SELECT os.prid AS product_id, SUM(os.qty - os.transferred_qty) AS total_qty
         FROM ot_sales os
         INNER JOIN ot_sales_invoice osi ON osi.tempid = os.tempid
         WHERE osi.status = 'draft' AND os.godownid = ?
           AND os.qty > os.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'ot' AND s.source_ref = CONCAT(os.tempid, ':', os.prid) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )
         GROUP BY os.prid"
    );
    $otStmt->bind_param('i', $llpGodownId);
    $otStmt->execute();
    $otResult = $otStmt->get_result();
    while ($row = $otResult->fetch_assoc()) {
        $pid = (int) $row['product_id'];
        if (!isset($requirements[$pid])) $requirements[$pid] = ['tp' => 0, 'ot' => 0, 'cp' => 0];
        $requirements[$pid]['ot'] += (int) $row['total_qty'];
    }
    $otStmt->close();

    return array_filter($requirements, fn($r) => ($r['tp'] + $r['ot'] + $r['cp']) > 0);
}

/**
 * Splits one product's available stock between TP, CP, and OT-draft
 * demand — TP first, then CP, then OT last. TP and CP are both real,
 * completable purchase orders (never starved by OT drafts that, until
 * confirmed, can sit indefinitely); TP keeps first claim ahead of CP
 * since that priority already existed before CP joined this split.
 * Returns ['tp' => qtyForTp, 'cp' => qtyForCp, 'ot' => qtyForOt], each
 * capped so tp+cp+ot never exceeds $available and no source gets a
 * negative share.
 */
function cap_auto_transfer_qty_by_source(int $tpRequired, int $cpRequired, int $otRequired, int $available): array
{
    $available = max(0, $available);
    $tpCapped  = max(0, min($tpRequired, $available));
    $cpCapped  = max(0, min($cpRequired, $available - $tpCapped));
    $otCapped  = max(0, min($otRequired, $available - $tpCapped - $cpCapped));
    return ['tp' => $tpCapped, 'cp' => $cpCapped, 'ot' => $otCapped];
}

/**
 * Per-order breakdown of today's requirement for one product, across
 * both demand sources — powers the "View Breakdown" modal on
 * internal_transfer_auto.php, which lets staff exclude one specific
 * order's qty from today's transfer (e.g. "process this one tomorrow
 * instead") without changing that order's own status anywhere.
 *
 * Returns ['tp' => [...], 'ot' => [...]], each entry shaped
 * ['source_id' => string, 'label' => string, 'qty' => int]. source_id is
 * stable and unique across both arrays (prefixed by source), so the
 * client can track exclusions per exact order.
 */
function get_auto_transfer_breakdown_for_product(mysqli $db_conn, int $productId, int $llpGodownId): array
{
    ensure_auto_transfer_skip_table($db_conn);
    ensure_ot_sales_invoice_status_column($db_conn);
    ensure_tp_purchase_orders_preferred_cp_id_column($db_conn);
    ensure_auto_transfer_transferred_qty_columns($db_conn);
    $breakdown = ['tp' => [], 'ot' => [], 'cp' => []];

    // 'qty' here is each order's REMAINING qty (qty - transferred_qty),
    // same as get_auto_transfer_requirements() — a partially-transferred
    // order still shows, just for whatever's left of it, not its full
    // original amount. source_id/source_ref carry the product too
    // ("tp:<po_id>:<product_id>", "ot:<tempid>:<product_id>") — a single
    // PO or OT invoice can carry several different products, and
    // excluding one product's line via "Not Today" must never also
    // exclude that same PO/invoice's OTHER products. Matches CONCAT(...)
    // the same way in get_auto_transfer_requirements(). Skip-matching is
    // only against reason='excluded' — see that function's own comment.
    $tpStmt = $db_conn->prepare(
        "SELECT poi.po_id, (poi.qty - poi.transferred_qty) AS qty, poi.transferred_qty AS already_transferred, tp.name AS tp_name, tp.tp_id AS tp_code
         FROM tp_purchase_order_items poi
         INNER JOIN tp_purchase_orders po ON po.id = poi.po_id
         INNER JOIN territory_partners tp ON tp.id = po.territory_partner_id
         WHERE po.status = 'waiting' AND po.approver_type = 'company' AND po.preferred_cp_id IS NULL AND poi.product_id = ?
           AND poi.qty > poi.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'tp' AND s.source_ref = CONCAT(po.id, ':', poi.product_id) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )
         ORDER BY po.id"
    );
    $tpStmt->bind_param('i', $productId);
    $tpStmt->execute();
    $res = $tpStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $breakdown['tp'][] = [
            'source_id'          => 'tp:' . $row['po_id'] . ':' . $productId,
            'label'              => $row['tp_name'] . ' (' . $row['tp_code'] . ') — PO #' . $row['po_id'],
            'qty'                => (int) $row['qty'],
            'already_transferred'=> (int) $row['already_transferred'],
        ];
    }
    $tpStmt->close();

    $cpStmt = $db_conn->prepare(
        "SELECT cpi.po_id, (cpi.qty - cpi.transferred_qty) AS qty, cpi.transferred_qty AS already_transferred, cp.name AS cp_name, cp.cp_id AS cp_code
         FROM channel_partner_purchase_order_items cpi
         INNER JOIN channel_partner_purchase_orders po ON po.id = cpi.po_id
         INNER JOIN channel_partners cp ON cp.id = po.channel_partner_id
         WHERE po.status = 'waiting' AND cpi.product_id = ?
           AND cpi.qty > cpi.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'cp' AND s.source_ref = CONCAT(po.id, ':', cpi.product_id) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )
         ORDER BY po.id"
    );
    $cpStmt->bind_param('i', $productId);
    $cpStmt->execute();
    $res = $cpStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $breakdown['cp'][] = [
            'source_id'          => 'cp:' . $row['po_id'] . ':' . $productId,
            'label'              => $row['cp_name'] . ' (' . $row['cp_code'] . ') — PO #' . $row['po_id'],
            'qty'                => (int) $row['qty'],
            'already_transferred'=> (int) $row['already_transferred'],
        ];
    }
    $cpStmt->close();

    $otStmt = $db_conn->prepare(
        "SELECT os.tempid, (os.qty - os.transferred_qty) AS qty, os.transferred_qty AS already_transferred, os.customer_name, osi.cat
         FROM ot_sales os
         INNER JOIN ot_sales_invoice osi ON osi.tempid = os.tempid
         WHERE osi.status = 'draft' AND os.godownid = ? AND os.prid = ?
           AND os.qty > os.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'ot' AND s.source_ref = CONCAT(os.tempid, ':', os.prid) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )
         ORDER BY os.id"
    );
    $otStmt->bind_param('ii', $llpGodownId, $productId);
    $otStmt->execute();
    $res = $otStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $breakdown['ot'][] = [
            'source_id'          => 'ot:' . $row['tempid'] . ':' . $productId,
            'label'              => (($row['customer_name'] ?: 'Draft Order')) . ' (' . $row['cat'] . ')',
            'qty'                => (int) $row['qty'],
            'already_transferred'=> (int) $row['already_transferred'],
        ];
    }
    $otStmt->close();

    return $breakdown;
}

/**
 * Every order (TP PO / OT draft) contributing to TODAY's auto-transfer,
 * across ALL products at once — powers the page-level "View All Orders"
 * overview, as opposed to get_auto_transfer_breakdown_for_product()'s
 * single-product view. Each order lists every one of its own product
 * lines (not just one), so unchecking a whole order in that overview can
 * correctly reduce several different product rows in the main table at
 * once — one order can, and often does, carry more than one product.
 *
 * Returns ['tp' => [...], 'ot' => [...]], each entry shaped
 * ['order_key' => string, 'label' => string, 'products' => [['product_id'
 * => int, 'product_name' => string, 'qty' => int], ...]]. order_key is
 * the PO id / OT tempid alone (no product suffix — that's per-line,
 * inside 'products') and is what "Not Today" for the whole order marks
 * skipped for every one of its still-outstanding product lines.
 */
function get_auto_transfer_orders_overview(mysqli $db_conn, int $llpGodownId): array
{
    ensure_auto_transfer_skip_table($db_conn);
    ensure_ot_sales_invoice_status_column($db_conn);
    ensure_tp_purchase_orders_preferred_cp_id_column($db_conn);
    ensure_auto_transfer_transferred_qty_columns($db_conn);
    $overview = ['tp' => [], 'ot' => [], 'cp' => []];

    // 'qty' is each line's REMAINING qty — see get_auto_transfer_requirements()'s
    // comment. Skip-matching is only against reason='excluded'.
    $tpStmt = $db_conn->prepare(
        "SELECT po.id AS po_id, po.product_type, tp.name AS tp_name, tp.tp_id AS tp_code,
                poi.product_id, (poi.qty - poi.transferred_qty) AS qty, poi.transferred_qty AS already_transferred, p.productName
         FROM tp_purchase_order_items poi
         INNER JOIN tp_purchase_orders po ON po.id = poi.po_id
         INNER JOIN territory_partners tp ON tp.id = po.territory_partner_id
         INNER JOIN products p ON p.id = poi.product_id
         WHERE po.status = 'waiting' AND po.approver_type = 'company' AND po.preferred_cp_id IS NULL
           AND poi.qty > poi.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'tp' AND s.source_ref = CONCAT(po.id, ':', poi.product_id) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )
         ORDER BY po.id, poi.product_id"
    );
    $tpStmt->execute();
    $res = $tpStmt->get_result();
    $tpByPo = [];
    while ($row = $res->fetch_assoc()) {
        $poId = (int) $row['po_id'];
        if (!isset($tpByPo[$poId])) {
            $tpByPo[$poId] = [
                'order_key'   => (string) $poId,
                'label'       => $row['tp_name'] . ' (' . $row['tp_code'] . ') — PO #' . $poId,
                // The PO's own napkin/diaper choice (add-purchase-order.php
                // enforces a pure cart, never mixed) — powers the Napkin /
                // Lumi Diaper filter in the "View All Orders" modal.
                'order_type'  => $row['product_type'] === 'diaper' ? 'diaper' : 'napkin',
                'products'    => [],
            ];
        }
        $tpByPo[$poId]['products'][] = [
            'product_id'          => (int) $row['product_id'],
            'product_name'        => $row['productName'],
            'qty'                 => (int) $row['qty'],
            'already_transferred' => (int) $row['already_transferred'],
        ];
    }
    $tpStmt->close();
    $overview['tp'] = array_values($tpByPo);

    $cpStmt = $db_conn->prepare(
        "SELECT po.id AS po_id, po.product_type, cp.name AS cp_name, cp.cp_id AS cp_code,
                cpi.product_id, (cpi.qty - cpi.transferred_qty) AS qty, cpi.transferred_qty AS already_transferred, p.productName
         FROM channel_partner_purchase_order_items cpi
         INNER JOIN channel_partner_purchase_orders po ON po.id = cpi.po_id
         INNER JOIN channel_partners cp ON cp.id = po.channel_partner_id
         INNER JOIN products p ON p.id = cpi.product_id
         WHERE po.status = 'waiting'
           AND cpi.qty > cpi.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'cp' AND s.source_ref = CONCAT(po.id, ':', cpi.product_id) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )
         ORDER BY po.id, cpi.product_id"
    );
    $cpStmt->execute();
    $res = $cpStmt->get_result();
    $cpByPo = [];
    while ($row = $res->fetch_assoc()) {
        $poId = (int) $row['po_id'];
        if (!isset($cpByPo[$poId])) {
            $cpByPo[$poId] = [
                'order_key'   => (string) $poId,
                'label'       => $row['cp_name'] . ' (' . $row['cp_code'] . ') — PO #' . $poId,
                'order_type'  => $row['product_type'] === 'diaper' ? 'diaper' : 'napkin',
                'products'    => [],
            ];
        }
        $cpByPo[$poId]['products'][] = [
            'product_id'          => (int) $row['product_id'],
            'product_name'        => $row['productName'],
            'qty'                 => (int) $row['qty'],
            'already_transferred' => (int) $row['already_transferred'],
        ];
    }
    $cpStmt->close();
    $overview['cp'] = array_values($cpByPo);

    $otStmt = $db_conn->prepare(
        "SELECT os.tempid, os.customer_name, osi.cat, os.prid AS product_id, (os.qty - os.transferred_qty) AS qty, os.transferred_qty AS already_transferred, p.productName, p.category
         FROM ot_sales os
         INNER JOIN ot_sales_invoice osi ON osi.tempid = os.tempid
         INNER JOIN products p ON p.id = os.prid
         WHERE osi.status = 'draft' AND os.godownid = ?
           AND os.qty > os.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'ot' AND s.source_ref = CONCAT(os.tempid, ':', os.prid) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )
         ORDER BY os.tempid, os.prid"
    );
    $otStmt->bind_param('i', $llpGodownId);
    $otStmt->execute();
    $res = $otStmt->get_result();
    $otByTempid = [];
    while ($row = $res->fetch_assoc()) {
        $tempid = $row['tempid'];
        if (!isset($otByTempid[$tempid])) {
            $otByTempid[$tempid] = [
                'order_key'  => $tempid,
                'label'      => (($row['customer_name'] ?: 'Draft Order')) . ' (' . $row['cat'] . ')',
                // OT drafts have no per-order product_type column like TP
                // POs do — classified from its first line's own
                // products.category instead (same napkin/diaper convention
                // TpProductType.php uses elsewhere).
                'order_type' => $row['category'] === 'diaper' ? 'diaper' : 'napkin',
                'products'   => [],
            ];
        }
        $otByTempid[$tempid]['products'][] = [
            'product_id'          => (int) $row['product_id'],
            'product_name'        => $row['productName'],
            'qty'                 => (int) $row['qty'],
            'already_transferred' => (int) $row['already_transferred'],
        ];
    }
    $otStmt->close();
    $overview['ot'] = array_values($otByTempid);

    return $overview;
}

/**
 * Distinct count of still-waiting TP purchase orders that genuinely need
 * Auto Transfer to move stock for them today — same WHERE clause as the
 * 'tp' half of get_auto_transfer_orders_overview() (waiting, company-
 * approved, not CP-preferred, not already fully marked 'transferred'
 * today), just COUNT(DISTINCT po.id) instead of the full per-order/
 * per-product breakdown. Powers the page-level "Total PO" stat card —
 * a PO whose stock has already been moved today (even if the order's own
 * status is still 'waiting', since fulfilling a PO is a separate manual
 * step) is excluded, same as it disappears from the "TP Purchase Orders"
 * tab in the "View All Orders" modal.
 */
function get_auto_transfer_waiting_po_count(mysqli $db_conn): int
{
    ensure_auto_transfer_skip_table($db_conn);
    ensure_tp_purchase_orders_preferred_cp_id_column($db_conn);
    ensure_auto_transfer_transferred_qty_columns($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT COUNT(DISTINCT po.id) AS n
         FROM tp_purchase_order_items poi
         INNER JOIN tp_purchase_orders po ON po.id = poi.po_id
         WHERE po.status = 'waiting' AND po.approver_type = 'company' AND po.preferred_cp_id IS NULL
           AND poi.qty > poi.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'tp' AND s.source_ref = CONCAT(po.id, ':', poi.product_id) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )"
    );
    $stmt->execute();
    $n = (int) ($stmt->get_result()->fetch_assoc()['n'] ?? 0);
    $stmt->close();
    return $n;
}

/**
 * Same waiting-PO population as get_auto_transfer_waiting_po_count(), split
 * by the PO's own napkin/diaper product_type — powers the "Napkin (n)" /
 * "Lumi Diaper (n)" counts on the "View All Orders" modal's type filter
 * buttons, so staff can see how many purchase orders each filter actually
 * covers before clicking it (previously the buttons carried no count at
 * all, so "how many napkin POs vs diaper POs" had no answer short of
 * clicking each filter and counting rows by hand).
 *
 * Returns ['napkin' => int, 'diaper' => int]. A PO with items of only one
 * product_type is counted once under that type — add-purchase-order.php
 * enforces a pure napkin-only or diaper-only cart per PO, so a PO is never
 * split across both counts.
 */
function get_auto_transfer_waiting_po_count_by_type(mysqli $db_conn): array
{
    ensure_auto_transfer_skip_table($db_conn);
    ensure_tp_purchase_orders_preferred_cp_id_column($db_conn);
    ensure_auto_transfer_transferred_qty_columns($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT po.product_type, COUNT(DISTINCT po.id) AS n
         FROM tp_purchase_order_items poi
         INNER JOIN tp_purchase_orders po ON po.id = poi.po_id
         WHERE po.status = 'waiting' AND po.approver_type = 'company' AND po.preferred_cp_id IS NULL
           AND poi.qty > poi.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'tp' AND s.source_ref = CONCAT(po.id, ':', poi.product_id) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )
         GROUP BY po.product_type"
    );
    $stmt->execute();
    $counts = ['napkin' => 0, 'diaper' => 0];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $type = $row['product_type'] === 'diaper' ? 'diaper' : 'napkin';
        $counts[$type] += (int) $row['n'];
    }
    $stmt->close();
    return $counts;
}

/**
 * Same as get_auto_transfer_waiting_po_count(), for Channel Partner
 * purchase orders instead of TP's — powers a "Total CP PO" stat card
 * alongside the existing "Total PO" (TP) one.
 */
function get_auto_transfer_waiting_cp_count(mysqli $db_conn): int
{
    ensure_auto_transfer_skip_table($db_conn);
    ensure_auto_transfer_transferred_qty_columns($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT COUNT(DISTINCT po.id) AS n
         FROM channel_partner_purchase_order_items cpi
         INNER JOIN channel_partner_purchase_orders po ON po.id = cpi.po_id
         WHERE po.status = 'waiting'
           AND cpi.qty > cpi.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'cp' AND s.source_ref = CONCAT(po.id, ':', cpi.product_id) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )"
    );
    $stmt->execute();
    $n = (int) ($stmt->get_result()->fetch_assoc()['n'] ?? 0);
    $stmt->close();
    return $n;
}

/**
 * Same as get_auto_transfer_waiting_po_count_by_type(), for Channel
 * Partner purchase orders. Returns ['napkin' => int, 'diaper' => int].
 */
function get_auto_transfer_waiting_cp_count_by_type(mysqli $db_conn): array
{
    ensure_auto_transfer_skip_table($db_conn);
    ensure_auto_transfer_transferred_qty_columns($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT po.product_type, COUNT(DISTINCT po.id) AS n
         FROM channel_partner_purchase_order_items cpi
         INNER JOIN channel_partner_purchase_orders po ON po.id = cpi.po_id
         WHERE po.status = 'waiting'
           AND cpi.qty > cpi.transferred_qty
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'cp' AND s.source_ref = CONCAT(po.id, ':', cpi.product_id) AND s.skip_date = CURDATE() AND s.reason = 'excluded'
           )
         GROUP BY po.product_type"
    );
    $stmt->execute();
    $counts = ['napkin' => 0, 'diaper' => 0];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $type = $row['product_type'] === 'diaper' ? 'diaper' : 'napkin';
        $counts[$type] += (int) $row['n'];
    }
    $stmt->close();
    return $counts;
}

/**
 * Every currently-draft OT sale line booked against a godown OTHER than
 * LLP — Auto Transfer only ever replenishes LLP (see
 * get_auto_transfer_requirements()), so a draft booked against any other
 * company_godown (e.g. picked by mistake in ot-sale-add.php, which lets
 * any non-finance-only godown be chosen) silently never appears as
 * demand there. This surfaces those drafts instead of leaving them
 * invisible, so staff can notice and correct the godown by hand.
 *
 * Returns a list of ['tempid' => string, 'customer_name' => ?string,
 * 'cat' => string, 'godown_name' => string, 'product_id' => int,
 * 'product_name' => string, 'qty' => int], one row per OT sale line.
 */
function get_ot_drafts_outside_llp_godown(mysqli $db_conn, int $llpGodownId): array
{
    ensure_ot_sales_invoice_status_column($db_conn);

    $stmt = $db_conn->prepare(
        "SELECT os.tempid, os.customer_name, osi.cat, os.prid AS product_id,
                os.qty, p.productName, cg.gname AS godown_name
         FROM ot_sales os
         INNER JOIN ot_sales_invoice osi ON osi.tempid = os.tempid
         INNER JOIN products p ON p.id = os.prid
         LEFT JOIN company_godown cg ON cg.id = os.godownid
         WHERE osi.status = 'draft' AND (os.godownid IS NULL OR os.godownid != ?)
         ORDER BY os.tempid, os.prid"
    );
    $stmt->bind_param('i', $llpGodownId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(function ($row) {
        return [
            'tempid'        => $row['tempid'],
            'customer_name' => $row['customer_name'],
            'cat'           => $row['cat'],
            'godown_name'   => $row['godown_name'] ?? 'Unknown/Unassigned',
            'product_id'    => (int) $row['product_id'],
            'product_name'  => $row['productName'],
            'qty'           => (int) $row['qty'],
        ];
    }, $rows);
}

/**
 * Caps a required qty to what's actually movable through the
 * Neksomo -> Healthcare -> LLP pass-through: never more than Neksomo's
 * and Healthcare's combined available stock. Never negative.
 */
function cap_auto_transfer_qty(int $required, int $neksomoAvail, int $healthcareAvail): int
{
    $capped = min($required, $neksomoAvail + $healthcareAvail);
    return max(0, $capped);
}

/**
 * Per-product history of every auto-transfer run on one calendar date —
 * powers the "Transfer History" button on internal_transfer_auto.php.
 * Scoped to auto-transfers only via internal_transfer_auto_action.php's
 * own tempid convention ("AUTO<timestamp><rand>-N1"/"-N2" — never
 * collides with manual transfers' "<rand>INTTRNS/<date>/<time>" format),
 * so this never picks up a manual transfer by mistake.
 *
 * Reads stock_ledger's own qty_before/qty_after + created_at for the
 * exact before/after stock and real timestamp of each movement — no
 * separate history table needed, since StockService already records
 * this for every transfer_out/transfer_in it performs. "Before" for
 * Healthcare specifically means before EITHER leg touched it that run
 * (i.e. its qty_before as the destination of leg 1, not as the source
 * of leg 2 — that would just be "after receiving from Neksomo").
 *
 * Returns a list of rows, each shaped:
 * ['product_id' => int, 'product_name' => string, 'qty_transferred' => int,
 *  'tempid' => string (the -N2 leg's tempid — pass to undo_auto_transfer()),
 *  'neksomo_before' => ?int, 'healthcare_before' => ?int, 'llp_before' => ?int,
 *  'neksomo_after' => ?int, 'healthcare_after' => ?int, 'llp_after' => ?int,
 *  'transferred_at' => ?string (Y-m-d H:i:s)]
 * A null before/after value means the matching stock_ledger row wasn't
 * found (e.g. a very old run predating this table, or a partial/failed
 * leg) — the caller should render that as "—", not 0.
 */
function get_auto_transfer_history_for_date(mysqli $db_conn, string $date, int $neksomoId, int $healthcareId, int $llpId): array
{
    $stmt = $db_conn->prepare(
        "SELECT it2.tempid, it2.product_id, p.productName, it2.qty AS qty_transferred,
                sl_out.qty_before AS neksomo_before,
                sl_in1.qty_before AS healthcare_before,
                sl_in2.qty_before AS llp_before,
                sl_out.qty_after AS neksomo_after,
                sl_in1.qty_after AS healthcare_after,
                sl_in2.qty_after AS llp_after,
                sl_out.warehouse_id AS neksomo_warehouse_id,
                sl_in2.warehouse_id AS llp_warehouse_id,
                COALESCE(sl_out.created_at, sl_in2.created_at) AS transferred_at
         FROM internal_transfer it2
         INNER JOIN products p ON p.id = it2.product_id
         LEFT JOIN stock_ledger sl_out
           ON sl_out.ref_id = REPLACE(it2.tempid, '-N2', '-N1') AND sl_out.action = 'transfer_out'
              AND sl_out.user_id = ? AND sl_out.product_id = it2.product_id
         LEFT JOIN stock_ledger sl_in1
           ON sl_in1.ref_id = REPLACE(it2.tempid, '-N2', '-N1') AND sl_in1.action = 'transfer_in'
              AND sl_in1.user_id = ? AND sl_in1.product_id = it2.product_id
         LEFT JOIN stock_ledger sl_in2
           ON sl_in2.ref_id = it2.tempid AND sl_in2.action = 'transfer_in'
              AND sl_in2.user_id = ? AND sl_in2.product_id = it2.product_id
         WHERE it2.tempid LIKE 'AUTO%-N2' AND it2.send_to = ? AND it2.date = ?
         ORDER BY transferred_at ASC, p.productName ASC"
    );
    $neksomoStr = (string) $neksomoId;
    $healthcareStr = (string) $healthcareId;
    $llpStr = (string) $llpId;
    $llpSendTo = (string) $llpId;
    $stmt->bind_param('sssss', $neksomoStr, $healthcareStr, $llpStr, $llpSendTo, $date);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(function ($row) {
        return [
            'tempid'               => $row['tempid'],
            'product_id'           => (int) $row['product_id'],
            'product_name'         => $row['productName'],
            'qty_transferred'      => (int) $row['qty_transferred'],
            'neksomo_before'       => $row['neksomo_before'] !== null ? (int) $row['neksomo_before'] : null,
            'healthcare_before'    => $row['healthcare_before'] !== null ? (int) $row['healthcare_before'] : null,
            'llp_before'           => $row['llp_before'] !== null ? (int) $row['llp_before'] : null,
            'neksomo_after'        => $row['neksomo_after'] !== null ? (int) $row['neksomo_after'] : null,
            'healthcare_after'     => $row['healthcare_after'] !== null ? (int) $row['healthcare_after'] : null,
            'llp_after'            => $row['llp_after'] !== null ? (int) $row['llp_after'] : null,
            'neksomo_warehouse_id' => $row['neksomo_warehouse_id'] !== null ? (int) $row['neksomo_warehouse_id'] : null,
            'llp_warehouse_id'     => $row['llp_warehouse_id'] !== null ? (int) $row['llp_warehouse_id'] : null,
            'transferred_at'       => $row['transferred_at'],
        ];
    }, $rows);
}

/**
 * Same underlying data as get_auto_transfer_history_for_date(), grouped
 * into one row per Auto Transfer RUN (one "Transfer Now" click) instead
 * of one row per product — matching Manage Internal Stock Transfer's own
 * invoice-wise layout (one row per tempid, one column per product).
 *
 * A single click can move several different products through the same
 * -N1/-N2 tempid pair, so the run's identity is the shared "AUTO<ts><rand>"
 * base (tempid with "-N1"/"-N2" stripped) — grouping by the full -N2
 * tempid the underlying rows already carry.
 *
 * Returns a list of runs, each shaped:
 * ['tempid' => string (the -N2 leg's tempid — pass to undo_auto_transfer()
 *   per product), 'inv_number_leg1' => ?string, 'inv_number_leg2' => ?string,
 *  'transferred_at' => ?string (Y-m-d H:i:s, earliest product in the run),
 *  'products' => [['product_id' => int, 'product_name' => string,
 *   'qty_transferred' => int, 'neksomo_before' => ?int, ... same per-product
 *   shape get_auto_transfer_history_for_date() returns, minus 'tempid'
 *   and 'transferred_at' — identical across every product in one run]]]
 */
function get_auto_transfer_history_grouped_for_date(mysqli $db_conn, string $date, int $neksomoId, int $healthcareId, int $llpId): array
{
    $flatRows = get_auto_transfer_history_for_date($db_conn, $date, $neksomoId, $healthcareId, $llpId);

    $runs = [];
    foreach ($flatRows as $row) {
        $tempid = $row['tempid'];
        if (!isset($runs[$tempid])) {
            $tempid1 = substr($tempid, 0, -3) . '-N1';
            $invStmt = $db_conn->prepare("SELECT tempid, inv_number FROM internal_transfer_invoice WHERE tempid IN (?, ?)");
            $invStmt->bind_param('ss', $tempid1, $tempid);
            $invStmt->execute();
            $invByTempid = [];
            foreach ($invStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $invRow) {
                $invByTempid[$invRow['tempid']] = $invRow['inv_number'];
            }
            $invStmt->close();

            $runs[$tempid] = [
                'tempid'          => $tempid,
                'inv_number_leg1' => $invByTempid[$tempid1] ?? null,
                'inv_number_leg2' => $invByTempid[$tempid] ?? null,
                'transferred_at'  => $row['transferred_at'],
                'products'        => [],
            ];
        }

        $productRow = $row;
        unset($productRow['tempid'], $productRow['transferred_at']);
        $runs[$tempid]['products'][] = $productRow;

        // Earliest product in the run represents when it actually started.
        if ($row['transferred_at'] !== null
            && ($runs[$tempid]['transferred_at'] === null || $row['transferred_at'] < $runs[$tempid]['transferred_at'])) {
            $runs[$tempid]['transferred_at'] = $row['transferred_at'];
        }
    }

    return array_values($runs);
}

/**
 * Undoes one product's auto-transfer run: reverses leg 2 (Healthcare ->
 * LLP) then leg 1 (Neksomo -> Healthcare), in that order (opposite of how
 * the stock moved — same convention internal_transfer_delete.php uses for
 * a single leg), then deletes both internal_transfer rows. Refuses if
 * either leg's stock has already moved on (same
 * insufficient_stock_to_reverse guard StockService::reverseTransferIn()
 * already enforces) — the caller must surface that rather than silently
 * flooring stock at 0.
 *
 * $tempidN2 must be the -N2 leg's tempid, as returned by
 * get_auto_transfer_history_for_date(). Only the ONE internal_transfer
 * row matching ($tempidN2, $productId) and its -N1 sibling are touched —
 * a single auto-transfer run can move several different products under
 * the same tempid pair, and undoing one must never affect the others.
 *
 * Returns ['success' => true] or
 * ['success' => false, 'reason' => 'not_found'|'insufficient_stock_to_reverse', ...].
 */
// Reads back the exact warehouse one leg's stock_ledger entry actually
// used — never trust a UI-supplied value, since the picker on screen
// when Undo is clicked may not reflect what the original transfer used.
// NULL for any pre-feature run (both legs always NULL back then), which
// reverseTransferIn()/reverseTransferOut() already treat identically to
// "no warehouse" — no special-casing needed by the caller.
function auto_transfer_leg_warehouse(mysqli $db, string $tempid, int $productId, string $action): ?int
{
    $stmt = $db->prepare(
        "SELECT warehouse_id FROM stock_ledger
         WHERE ref_type = 'transfer' AND ref_id = ? AND product_id = ? AND action = ?
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('sis', $tempid, $productId, $action);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row && $row['warehouse_id'] !== null) ? (int) $row['warehouse_id'] : null;
}

function undo_auto_transfer(mysqli $db_conn, string $tempidN2, int $productId, string $userType, int $neksomoId, int $healthcareId, int $llpId, string $createdBy): array
{
    if (!str_ends_with($tempidN2, '-N2')) {
        return ['success' => false, 'reason' => 'not_found'];
    }
    $tempidN1 = substr($tempidN2, 0, -3) . '-N1';

    $stmt = $db_conn->prepare(
        "SELECT id, tempid, qty, returned_qty FROM internal_transfer WHERE tempid IN (?, ?) AND product_id = ?"
    );
    $stmt->bind_param('ssi', $tempidN1, $tempidN2, $productId);
    $stmt->execute();
    $legs = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $legs[$row['tempid']] = $row;
    }
    $stmt->close();

    if (!isset($legs[$tempidN1]) || !isset($legs[$tempidN2])) {
        return ['success' => false, 'reason' => 'not_found'];
    }

    $qtyLeg1 = (int) $legs[$tempidN1]['qty'] - (int) $legs[$tempidN1]['returned_qty'];
    $qtyLeg2 = (int) $legs[$tempidN2]['qty'] - (int) $legs[$tempidN2]['returned_qty'];

    // Leg 2: Healthcare -> LLP. sourceWarehouse = where it left Healthcare
    // (the transfer_out row); destWarehouse = where it landed at LLP (the
    // transfer_in row) — these can legitimately differ (see
    // docs/superpowers/specs/2026-09-21-warehouse-aware-auto-transfer-
    // design.md). Leg 1: Neksomo -> Healthcare, same idea.
    $leg2SourceWarehouse = auto_transfer_leg_warehouse($db_conn, $tempidN2, $productId, 'transfer_out');
    $leg2DestWarehouse   = auto_transfer_leg_warehouse($db_conn, $tempidN2, $productId, 'transfer_in');
    $leg1SourceWarehouse = auto_transfer_leg_warehouse($db_conn, $tempidN1, $productId, 'transfer_out');
    $leg1DestWarehouse   = auto_transfer_leg_warehouse($db_conn, $tempidN1, $productId, 'transfer_in');

    $stockService = new StockService($db_conn);
    $neksomoStr    = (string) $neksomoId;
    $healthcareStr = (string) $healthcareId;
    $llpStr        = (string) $llpId;

    $db_conn->begin_transaction();
    try {
        if ($qtyLeg2 > 0) {
            $reverseIn2 = $stockService->reverseTransferIn($productId, $userType, $llpStr, $qtyLeg2, 'transfer', $tempidN2, $createdBy, true, $leg2DestWarehouse);
            if (($reverseIn2['success'] ?? false) === false) {
                $db_conn->rollback();
                return $reverseIn2;
            }
            $stockService->reverseTransferOut($productId, $userType, $healthcareStr, $qtyLeg2, 'transfer', $tempidN2, $createdBy, true, $leg2SourceWarehouse);
        }

        if ($qtyLeg1 > 0) {
            $reverseIn1 = $stockService->reverseTransferIn($productId, $userType, $healthcareStr, $qtyLeg1, 'transfer', $tempidN1, $createdBy, true, $leg1DestWarehouse);
            if (($reverseIn1['success'] ?? false) === false) {
                $db_conn->rollback();
                return $reverseIn1;
            }
            $stockService->reverseTransferOut($productId, $userType, $neksomoStr, $qtyLeg1, 'transfer', $tempidN1, $createdBy, true, $leg1SourceWarehouse);
        }

        foreach ([$tempidN1, $tempidN2] as $t) {
            $del = $db_conn->prepare("DELETE FROM internal_transfer WHERE id = ?");
            $del->bind_param('i', $legs[$t]['id']);
            $del->execute();
            $del->close();

            // Clean up the shared invoice header once no line items for
            // this tempid remain — same convention
            // internal_transfer_details.php's own delete flow uses.
            $countStmt = $db_conn->prepare("SELECT COUNT(*) AS n FROM internal_transfer WHERE tempid = ?");
            $countStmt->bind_param('s', $t);
            $countStmt->execute();
            if ((int) $countStmt->get_result()->fetch_assoc()['n'] === 0) {
                $delInv = $db_conn->prepare("DELETE FROM internal_transfer_invoice WHERE tempid = ?");
                $delInv->bind_param('s', $t);
                $delInv->execute();
                $delInv->close();
            }
            $countStmt->close();
        }

        // Reverses the partial-fulfillment credit this run's product line
        // gave each contributing order (see record_auto_transfer_partial())
        // — otherwise an order's transferred_qty would stay bumped even
        // though its stock just moved back, permanently under-counting it
        // as "required" from here on (not just today). Scoped to
        // (transfer_tempid, product) so undoing one product from a
        // multi-product run never touches another product's own rows.
        // NOTE: if this same order+product was ALSO touched by a second,
        // separate Auto Transfer run the same day, this row's qty is their
        // combined total (see record_auto_transfer_partial()'s own note)
        // — undoing either run then reverses the full combined amount,
        // which can over-reverse the other run's still-valid share. Rare
        // in practice (same order+product transferred twice in one day);
        // reconcile manually if it comes up.
        ensure_auto_transfer_skip_table($db_conn);
        ensure_auto_transfer_transferred_qty_columns($db_conn);
        $logStmt = $db_conn->prepare(
            "SELECT id, source_type, source_ref, qty FROM auto_transfer_skip_today
             WHERE transfer_tempid = ? AND reason = 'transferred'
               AND source_ref LIKE CONCAT('%:', ?)"
        );
        $productIdStr = (string) $productId;
        $logStmt->bind_param('ss', $tempidN2, $productIdStr);
        $logStmt->execute();
        $logRows = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $logStmt->close();

        foreach ($logRows as $logRow) {
            $loggedQty = $logRow['qty'] !== null ? (int) $logRow['qty'] : 0;
            if ($loggedQty > 0) {
                $parts = explode(':', $logRow['source_ref'], 2);
                if (count($parts) === 2) {
                    [$orderKey, $undoProductIdStr] = $parts;
                    $undoProductId = (int) $undoProductIdStr;
                    if ($logRow['source_type'] === 'ot') {
                        $revStmt = $db_conn->prepare(
                            "UPDATE ot_sales SET transferred_qty = GREATEST(0, transferred_qty - ?) WHERE tempid = ? AND prid = ?"
                        );
                        $revStmt->bind_param('isi', $loggedQty, $orderKey, $undoProductId);
                    } elseif ($logRow['source_type'] === 'cp') {
                        $poId = (int) $orderKey;
                        $revStmt = $db_conn->prepare(
                            "UPDATE channel_partner_purchase_order_items SET transferred_qty = GREATEST(0, transferred_qty - ?) WHERE po_id = ? AND product_id = ?"
                        );
                        $revStmt->bind_param('iii', $loggedQty, $poId, $undoProductId);
                    } else {
                        $poId = (int) $orderKey;
                        $revStmt = $db_conn->prepare(
                            "UPDATE tp_purchase_order_items SET transferred_qty = GREATEST(0, transferred_qty - ?) WHERE po_id = ? AND product_id = ?"
                        );
                        $revStmt->bind_param('iii', $loggedQty, $poId, $undoProductId);
                    }
                    $revStmt->execute();
                    $revStmt->close();
                }
            }

            $delLog = $db_conn->prepare("DELETE FROM auto_transfer_skip_today WHERE id = ?");
            $delLog->bind_param('i', $logRow['id']);
            $delLog->execute();
            $delLog->close();
        }

        $db_conn->commit();
        return ['success' => true];
    } catch (\Throwable $e) {
        $db_conn->rollback();
        throw $e;
    }
}
