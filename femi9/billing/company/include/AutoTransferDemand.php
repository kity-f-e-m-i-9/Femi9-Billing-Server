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
// requirement" — reason 'excluded' when staff explicitly chose not to
// transfer it today via the breakdown modal's "Not Today" button, reason
// 'transferred' when a previous Transfer Now click already moved its
// stock today (see internal_transfer_auto_action.php). Either way the
// order's OWN status (tp_purchase_orders.status / ot_sales_invoice.status
// / wa_po_purchase_orders.status) is never touched — this table is purely
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
 * Aggregates today's required quantity per product from:
 *  - tp_purchase_order_items joined to tp_purchase_orders
 *    WHERE status = 'waiting' AND order_date = CURDATE()
 *  - ot_sales joined to ot_sales_invoice (on tempid)
 *    WHERE ot_sales_invoice.status = 'draft'
 *      AND ot_sales.godownid = $llpGodownId
 *      AND ot_sales.date = CURDATE()
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
    $requirements = [];

    // Skip-matching is per (PO, product) — CONCAT'd since a single PO can
    // carry multiple products, and excluding one product from it must
    // never also exclude the PO's other products (see the matching
    // comment on get_auto_transfer_breakdown_for_product()).
    $tpStmt = $db_conn->prepare(
        "SELECT poi.product_id AS product_id, SUM(poi.qty) AS total_qty
         FROM tp_purchase_order_items poi
         INNER JOIN tp_purchase_orders po ON po.id = poi.po_id
         WHERE po.status = 'waiting' AND po.order_date = CURDATE()
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
         WHERE osi.status = 'draft' AND os.godownid = ? AND os.date = CURDATE()
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
         WHERE po.status = 'waiting' AND po.order_date = CURDATE() AND poi.product_id = ?
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
         WHERE osi.status = 'draft' AND os.godownid = ? AND os.date = CURDATE() AND os.prid = ?
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
 * Caps a required qty to what's actually movable through the
 * Neksomo -> Healthcare -> LLP pass-through: never more than Neksomo's
 * and Healthcare's combined available stock. Never negative.
 */
function cap_auto_transfer_qty(int $required, int $neksomoAvail, int $healthcareAvail): int
{
    $capped = min($required, $neksomoAvail + $healthcareAvail);
    return max(0, $capped);
}
