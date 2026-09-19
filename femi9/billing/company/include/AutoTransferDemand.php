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
            source_type ENUM('tp','ot','wa') NOT NULL,
            source_ref VARCHAR(64) NOT NULL,
            skip_date DATE NOT NULL,
            reason ENUM('excluded','transferred') NOT NULL DEFAULT 'excluded',
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_auto_transfer_skip (source_type, source_ref, skip_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
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
 * for the same order/date (e.g. a double-click) is harmless.
 */
function mark_auto_transfer_order_skipped(mysqli $db_conn, string $sourceType, string $sourceRef, string $reason, ?string $createdBy = null): void
{
    ensure_auto_transfer_skip_table($db_conn);
    $stmt = $db_conn->prepare(
        "INSERT IGNORE INTO auto_transfer_skip_today (source_type, source_ref, skip_date, reason, created_by)
         VALUES (?, ?, CURDATE(), ?, ?)"
    );
    $stmt->bind_param('ssss', $sourceType, $sourceRef, $reason, $createdBy);
    $stmt->execute();
    $stmt->close();
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
 * ['source_id' => string, 'label' => string, 'product_name' => string].
 * Only reason='transferred' rows are returned — 'excluded' rows (the
 * retired "Not Today" feature) are never created any more, but any old
 * ones are simply not shown here rather than being auto-deleted. A skip
 * whose underlying order/product no longer resolves (rare — e.g. the PO
 * was deleted after being skipped) still shows using the raw order_key/
 * product_id so it stays
 * visible and undoable rather than silently vanishing.
 */
function get_auto_transfer_skipped_today(mysqli $db_conn): array
{
    ensure_auto_transfer_skip_table($db_conn);
    $skipped = ['tp' => [], 'ot' => []];

    $stmt = $db_conn->prepare(
        "SELECT source_type, source_ref FROM auto_transfer_skip_today
         WHERE skip_date = CURDATE() AND source_type IN ('tp', 'ot') AND reason = 'transferred' ORDER BY id"
    );
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $sourceType = $row['source_type'];
        $parts = explode(':', $row['source_ref'], 2);
        if (count($parts) !== 2) continue;
        [$orderKey, $productIdStr] = $parts;
        $productId = (int) $productIdStr;

        $prodStmt = $db_conn->prepare("SELECT productName FROM products WHERE id = ?");
        $prodStmt->bind_param('i', $productId);
        $prodStmt->execute();
        $productName = $prodStmt->get_result()->fetch_assoc()['productName'] ?? "Product #$productId";
        $prodStmt->close();

        if ($sourceType === 'tp') {
            $poId = (int) $orderKey;
            $poStmt = $db_conn->prepare(
                "SELECT tp.name AS tp_name, tp.tp_id AS tp_code
                 FROM tp_purchase_orders po
                 INNER JOIN territory_partners tp ON tp.id = po.territory_partner_id
                 WHERE po.id = ?"
            );
            $poStmt->bind_param('i', $poId);
            $poStmt->execute();
            $poRow = $poStmt->get_result()->fetch_assoc();
            $poStmt->close();
            $label = $poRow ? ($poRow['tp_name'] . ' (' . $poRow['tp_code'] . ') — PO #' . $poId) : ('PO #' . $poId);
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
        }

        $skipped[$sourceType][] = [
            'source_id'    => $sourceType . ':' . $row['source_ref'],
            'label'        => $label,
            'product_name' => $productName,
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
 * Returns [product_id => requiredQty], omitting products with a
 * combined qty of 0 or less.
 */
function get_auto_transfer_requirements(mysqli $db_conn, int $llpGodownId): array
{
    ensure_auto_transfer_skip_table($db_conn);
    ensure_ot_sales_invoice_status_column($db_conn);
    ensure_tp_purchase_orders_preferred_cp_id_column($db_conn);
    $requirements = [];

    // Skip-matching is per (PO, product) — CONCAT'd since a single PO can
    // carry multiple products, and excluding one product from it must
    // never also exclude the PO's other products (see the matching
    // comment on get_auto_transfer_breakdown_for_product()).
    $tpStmt = $db_conn->prepare(
        "SELECT poi.product_id AS product_id, SUM(poi.qty) AS total_qty
         FROM tp_purchase_order_items poi
         INNER JOIN tp_purchase_orders po ON po.id = poi.po_id
         WHERE po.status = 'waiting' AND po.approver_type = 'company' AND po.preferred_cp_id IS NULL
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'tp' AND s.source_ref = CONCAT(po.id, ':', poi.product_id) AND s.skip_date = CURDATE()
           )
         GROUP BY poi.product_id"
    );
    $tpStmt->execute();
    $tpResult = $tpStmt->get_result();
    while ($row = $tpResult->fetch_assoc()) {
        $pid = (int) $row['product_id'];
        $requirements[$pid] = ($requirements[$pid] ?? 0) + (int) $row['total_qty'];
    }
    $tpStmt->close();

    $otStmt = $db_conn->prepare(
        "SELECT os.prid AS product_id, SUM(os.qty) AS total_qty
         FROM ot_sales os
         INNER JOIN ot_sales_invoice osi ON osi.tempid = os.tempid
         WHERE osi.status = 'draft' AND os.godownid = ?
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'ot' AND s.source_ref = CONCAT(os.tempid, ':', os.prid) AND s.skip_date = CURDATE()
           )
         GROUP BY os.prid"
    );
    $otStmt->bind_param('i', $llpGodownId);
    $otStmt->execute();
    $otResult = $otStmt->get_result();
    while ($row = $otResult->fetch_assoc()) {
        $pid = (int) $row['product_id'];
        $requirements[$pid] = ($requirements[$pid] ?? 0) + (int) $row['total_qty'];
    }
    $otStmt->close();

    return array_filter($requirements, fn($qty) => $qty > 0);
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
    $breakdown = ['tp' => [], 'ot' => []];

    // source_id/source_ref carry the product too ("tp:<po_id>:<product_id>",
    // "ot:<tempid>:<product_id>") — a single PO or OT invoice can carry
    // several different products, and excluding one product's line via
    // "Not Today" must never also exclude that same PO/invoice's OTHER
    // products. Matches CONCAT(...) the same way in get_auto_transfer_requirements().
    $tpStmt = $db_conn->prepare(
        "SELECT poi.po_id, poi.qty, tp.name AS tp_name, tp.tp_id AS tp_code
         FROM tp_purchase_order_items poi
         INNER JOIN tp_purchase_orders po ON po.id = poi.po_id
         INNER JOIN territory_partners tp ON tp.id = po.territory_partner_id
         WHERE po.status = 'waiting' AND po.approver_type = 'company' AND po.preferred_cp_id IS NULL AND poi.product_id = ?
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'tp' AND s.source_ref = CONCAT(po.id, ':', poi.product_id) AND s.skip_date = CURDATE()
           )
         ORDER BY po.id"
    );
    $tpStmt->bind_param('i', $productId);
    $tpStmt->execute();
    $res = $tpStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $breakdown['tp'][] = [
            'source_id' => 'tp:' . $row['po_id'] . ':' . $productId,
            'label'     => $row['tp_name'] . ' (' . $row['tp_code'] . ') — PO #' . $row['po_id'],
            'qty'       => (int) $row['qty'],
        ];
    }
    $tpStmt->close();

    $otStmt = $db_conn->prepare(
        "SELECT os.tempid, os.qty, os.customer_name, osi.cat
         FROM ot_sales os
         INNER JOIN ot_sales_invoice osi ON osi.tempid = os.tempid
         WHERE osi.status = 'draft' AND os.godownid = ? AND os.prid = ?
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'ot' AND s.source_ref = CONCAT(os.tempid, ':', os.prid) AND s.skip_date = CURDATE()
           )
         ORDER BY os.id"
    );
    $otStmt->bind_param('ii', $llpGodownId, $productId);
    $otStmt->execute();
    $res = $otStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $breakdown['ot'][] = [
            'source_id' => 'ot:' . $row['tempid'] . ':' . $productId,
            'label'     => (($row['customer_name'] ?: 'Draft Order')) . ' (' . $row['cat'] . ')',
            'qty'       => (int) $row['qty'],
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
    $overview = ['tp' => [], 'ot' => []];

    $tpStmt = $db_conn->prepare(
        "SELECT po.id AS po_id, tp.name AS tp_name, tp.tp_id AS tp_code,
                poi.product_id, poi.qty, p.productName
         FROM tp_purchase_order_items poi
         INNER JOIN tp_purchase_orders po ON po.id = poi.po_id
         INNER JOIN territory_partners tp ON tp.id = po.territory_partner_id
         INNER JOIN products p ON p.id = poi.product_id
         WHERE po.status = 'waiting' AND po.approver_type = 'company' AND po.preferred_cp_id IS NULL
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'tp' AND s.source_ref = CONCAT(po.id, ':', poi.product_id) AND s.skip_date = CURDATE()
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
                'order_key' => (string) $poId,
                'label'     => $row['tp_name'] . ' (' . $row['tp_code'] . ') — PO #' . $poId,
                'products'  => [],
            ];
        }
        $tpByPo[$poId]['products'][] = [
            'product_id'   => (int) $row['product_id'],
            'product_name' => $row['productName'],
            'qty'          => (int) $row['qty'],
        ];
    }
    $tpStmt->close();
    $overview['tp'] = array_values($tpByPo);

    $otStmt = $db_conn->prepare(
        "SELECT os.tempid, os.customer_name, osi.cat, os.prid AS product_id, os.qty, p.productName
         FROM ot_sales os
         INNER JOIN ot_sales_invoice osi ON osi.tempid = os.tempid
         INNER JOIN products p ON p.id = os.prid
         WHERE osi.status = 'draft' AND os.godownid = ?
           AND NOT EXISTS (
               SELECT 1 FROM auto_transfer_skip_today s
               WHERE s.source_type = 'ot' AND s.source_ref = CONCAT(os.tempid, ':', os.prid) AND s.skip_date = CURDATE()
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
                'order_key' => $tempid,
                'label'     => (($row['customer_name'] ?: 'Draft Order')) . ' (' . $row['cat'] . ')',
                'products'  => [],
            ];
        }
        $otByTempid[$tempid]['products'][] = [
            'product_id'   => (int) $row['product_id'],
            'product_name' => $row['productName'],
            'qty'          => (int) $row['qty'],
        ];
    }
    $otStmt->close();
    $overview['ot'] = array_values($otByTempid);

    return $overview;
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
            'tempid'            => $row['tempid'],
            'product_id'        => (int) $row['product_id'],
            'product_name'      => $row['productName'],
            'qty_transferred'   => (int) $row['qty_transferred'],
            'neksomo_before'    => $row['neksomo_before'] !== null ? (int) $row['neksomo_before'] : null,
            'healthcare_before' => $row['healthcare_before'] !== null ? (int) $row['healthcare_before'] : null,
            'llp_before'        => $row['llp_before'] !== null ? (int) $row['llp_before'] : null,
            'neksomo_after'     => $row['neksomo_after'] !== null ? (int) $row['neksomo_after'] : null,
            'healthcare_after'  => $row['healthcare_after'] !== null ? (int) $row['healthcare_after'] : null,
            'llp_after'         => $row['llp_after'] !== null ? (int) $row['llp_after'] : null,
            'transferred_at'    => $row['transferred_at'],
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

    $stockService = new StockService($db_conn);
    $neksomoStr    = (string) $neksomoId;
    $healthcareStr = (string) $healthcareId;
    $llpStr        = (string) $llpId;

    $db_conn->begin_transaction();
    try {
        if ($qtyLeg2 > 0) {
            $reverseIn2 = $stockService->reverseTransferIn($productId, $userType, $llpStr, $qtyLeg2, 'transfer', $tempidN2, $createdBy, true);
            if (($reverseIn2['success'] ?? false) === false) {
                $db_conn->rollback();
                return $reverseIn2;
            }
            $stockService->reverseTransferOut($productId, $userType, $healthcareStr, $qtyLeg2, 'transfer', $tempidN2, $createdBy, true);
        }

        if ($qtyLeg1 > 0) {
            $reverseIn1 = $stockService->reverseTransferIn($productId, $userType, $healthcareStr, $qtyLeg1, 'transfer', $tempidN1, $createdBy, true);
            if (($reverseIn1['success'] ?? false) === false) {
                $db_conn->rollback();
                return $reverseIn1;
            }
            $stockService->reverseTransferOut($productId, $userType, $neksomoStr, $qtyLeg1, 'transfer', $tempidN1, $createdBy, true);
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

        $db_conn->commit();
        return ['success' => true];
    } catch (\Throwable $e) {
        $db_conn->rollback();
        throw $e;
    }
}
