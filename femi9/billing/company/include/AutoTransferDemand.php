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

/**
 * Aggregates today's required quantity per product from:
 *  - tp_purchase_order_items joined to tp_purchase_orders
 *    WHERE status = 'waiting' AND order_date = CURDATE()
 *  - ot_sales joined to ot_sales_invoice (on tempid)
 *    WHERE ot_sales_invoice.status = 'draft'
 *      AND ot_sales.godownid = $llpGodownId
 *      AND ot_sales.date = CURDATE()
 *  - wa_po_purchase_order_items joined to wa_po_purchase_orders (the
 *    separate WhatsApp-bot ordering subsystem under api/wa-po/ — its own
 *    tables, not tp_purchase_orders)
 *    WHERE status = 'waiting' AND order_date = CURDATE()
 *
 * Returns [product_id => requiredQty], omitting products with a
 * combined qty of 0 or less.
 */
function get_auto_transfer_requirements(mysqli $db_conn, int $llpGodownId): array
{
    $requirements = [];

    $tpStmt = $db_conn->prepare(
        "SELECT poi.product_id AS product_id, SUM(poi.qty) AS total_qty
         FROM tp_purchase_order_items poi
         INNER JOIN tp_purchase_orders po ON po.id = poi.po_id
         WHERE po.status = 'waiting' AND po.order_date = CURDATE()
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

    $waStmt = $db_conn->prepare(
        "SELECT wpoi.product_id AS product_id, SUM(wpoi.qty) AS total_qty
         FROM wa_po_purchase_order_items wpoi
         INNER JOIN wa_po_purchase_orders wpo ON wpo.id = wpoi.po_id
         WHERE wpo.status = 'waiting' AND wpo.order_date = CURDATE()
         GROUP BY wpoi.product_id"
    );
    $waStmt->execute();
    $waResult = $waStmt->get_result();
    while ($row = $waResult->fetch_assoc()) {
        $pid = (int) $row['product_id'];
        $requirements[$pid] = ($requirements[$pid] ?? 0) + (int) $row['total_qty'];
    }
    $waStmt->close();

    return array_filter($requirements, fn($qty) => $qty > 0);
}

/**
 * Minimal local copy of api/wa-po/_bootstrap.php's wa_po_category_configs()
 * — deliberately NOT require_once'd from that file, since it sets
 * webhook-only response headers and API-key/HMAC expectations that have no
 * business running inside a company-admin page. Only the table/name_field
 * pairs this breakdown actually needs to resolve a WhatsApp PO's placer
 * into a human name.
 */
function wa_po_display_name(mysqli $db_conn, string $category, int $userId): string
{
    $configs = [
        'distributor'        => ['table' => 'distributor',        'id_field' => 'id', 'name_field' => 'name'],
        'super_distributor'  => ['table' => 'super_distributor',  'id_field' => 'id', 'name_field' => 'name'],
        'stockiest'          => ['table' => 'stockiest',          'id_field' => 'id', 'name_field' => 'name'],
        'super_stockiest'    => ['table' => 'super_stockiest',    'id_field' => 'id', 'name_field' => 'name'],
        'channel_partner'    => ['table' => 'channel_partners',   'id_field' => 'id', 'name_field' => 'name'],
        'candf'              => ['table' => 'c_and_f',            'id_field' => 'id', 'name_field' => 'name'],
        'marketing'          => ['table' => 'marketing_staff',    'id_field' => 'id', 'name_field' => 'ms_name'],
        'territory_partner'  => ['table' => 'territory_partners', 'id_field' => 'id', 'name_field' => 'name'],
    ];
    $cfg = $configs[$category] ?? null;
    if (!$cfg) return "$category #$userId";

    $stmt = $db_conn->prepare("SELECT `{$cfg['name_field']}` AS name FROM `{$cfg['table']}` WHERE `{$cfg['id_field']}` = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['name'] ?? "$category #$userId";
}

/**
 * Per-order breakdown of today's requirement for one product, across all
 * three demand sources — powers the "View Breakdown" modal on
 * internal_transfer_auto.php, which lets staff exclude one specific
 * order's qty from today's transfer (e.g. "process this one tomorrow
 * instead") without changing that order's own status anywhere.
 *
 * Returns ['tp' => [...], 'ot' => [...], 'wa' => [...]], each entry
 * shaped ['source_id' => string, 'label' => string, 'qty' => int].
 * source_id is stable and unique across all three arrays (prefixed by
 * source), so the client can track exclusions per exact order.
 */
function get_auto_transfer_breakdown_for_product(mysqli $db_conn, int $productId, int $llpGodownId): array
{
    $breakdown = ['tp' => [], 'ot' => [], 'wa' => []];

    $tpStmt = $db_conn->prepare(
        "SELECT poi.po_id, poi.qty, tp.name AS tp_name, tp.tp_id AS tp_code
         FROM tp_purchase_order_items poi
         INNER JOIN tp_purchase_orders po ON po.id = poi.po_id
         INNER JOIN territory_partners tp ON tp.id = po.territory_partner_id
         WHERE po.status = 'waiting' AND po.order_date = CURDATE() AND poi.product_id = ?
         ORDER BY po.id"
    );
    $tpStmt->bind_param('i', $productId);
    $tpStmt->execute();
    $res = $tpStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $breakdown['tp'][] = [
            'source_id' => 'tp:' . $row['po_id'],
            'label'     => $row['tp_name'] . ' (' . $row['tp_code'] . ')',
            'qty'       => (int) $row['qty'],
        ];
    }
    $tpStmt->close();

    $otStmt = $db_conn->prepare(
        "SELECT os.tempid, os.qty, os.customer_name, osi.cat
         FROM ot_sales os
         INNER JOIN ot_sales_invoice osi ON osi.tempid = os.tempid
         WHERE osi.status = 'draft' AND os.godownid = ? AND os.date = CURDATE() AND os.prid = ?
         ORDER BY os.id"
    );
    $otStmt->bind_param('ii', $llpGodownId, $productId);
    $otStmt->execute();
    $res = $otStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $breakdown['ot'][] = [
            'source_id' => 'ot:' . $row['tempid'],
            'label'     => (($row['customer_name'] ?: 'Draft Order')) . ' (' . $row['cat'] . ')',
            'qty'       => (int) $row['qty'],
        ];
    }
    $otStmt->close();

    $waStmt = $db_conn->prepare(
        "SELECT wpo.id AS po_id, wpoi.qty, wpo.user_category, wpo.user_id
         FROM wa_po_purchase_order_items wpoi
         INNER JOIN wa_po_purchase_orders wpo ON wpo.id = wpoi.po_id
         WHERE wpo.status = 'waiting' AND wpo.order_date = CURDATE() AND wpoi.product_id = ?
         ORDER BY wpo.id"
    );
    $waStmt->bind_param('i', $productId);
    $waStmt->execute();
    $waRows = $waStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $waStmt->close();

    foreach ($waRows as $row) {
        $breakdown['wa'][] = [
            'source_id' => 'wa:' . $row['po_id'],
            'label'     => wa_po_display_name($db_conn, $row['user_category'], (int) $row['user_id']),
            'qty'       => (int) $row['qty'],
        ];
    }

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
