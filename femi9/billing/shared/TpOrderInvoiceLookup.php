<?php
// Shared by territory-partner/manage-orders.php and marketing/manage_order_product.php
// (the DM's own "Product Orders" page) — both let someone "Convert" a
// pending Get Order to one of the TP's own ALREADY-RAISED invoices for that
// shop, instead of auto-generating a brand new invoice from the order's
// captured product/qty. 2026-11-24, user-requested (DM side: "dm ku location
// pakkathula convert aprm not convert nu button vechuru... ithey tp
// pannitangalum saei ivarey pannitalum saei" — either the TP or the DM
// should be able to do this).

// Dedupe key (normalised name + 10-digit mobile) — a TP can end up with two
// separate `shop` rows for the exact same real shop (one created while
// placing the Get Order, another created separately while invoicing it), so
// matching invoices by the order's literal shop_id/temp_id alone can miss a
// real invoice raised to the OTHER duplicate record. 2026-11-24, user-
// reported (shop "jayadeepa Femi9WD" — two shop rows, same name+mobile).
function tpOrderDedupeKey($name, $mobile): ?string {
    $digits = preg_replace('/\D+/', '', (string)$mobile);
    $nm = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string)$name)));
    if (strlen($digits) === 10 && $nm !== '') { return $nm . '|' . $digits; }
    return null;
}

// $pendingShops: [shop_temp_id => ['name' => ..., 'mobile' => ...]] — every
// shop temp_id a Convert button might need invoices for, all for the SAME
// $tpId. Returns [shop_temp_id => [ ['inv_id','inv_number','date','placed_at','completed'], ... ]],
// keyed by the SAME temp_id passed in (duplicate-shop invoices are folded
// back in automatically).
function getInvoicesByShopTempIdForTp($db_conn, int $tpId, array $pendingShops): array {
    $invoicesByShopTempId = [];
    if (empty($pendingShops)) { return $invoicesByShopTempId; }

    $resShops = $db_conn->query(
        "SELECT temp_id, name, mobile_number FROM shop WHERE onboard_userTYPE='territory_partner' AND onboard_userID=" . (int)$tpId
    );
    $shopsByDedupeKey = [];
    while ($sr = mysqli_fetch_assoc($resShops)) {
        $k = tpOrderDedupeKey($sr['name'], $sr['mobile_number']);
        if ($k !== null) { $shopsByDedupeKey[$k][] = $sr['temp_id']; }
    }

    $familyByPendingTempId = [];
    $allFamilyTempIds = [];
    foreach ($pendingShops as $tempId => $info) {
        $k = tpOrderDedupeKey($info['name'] ?? '', $info['mobile'] ?? '');
        $family = $k !== null && isset($shopsByDedupeKey[$k]) ? $shopsByDedupeKey[$k] : [$tempId];
        if (!in_array($tempId, $family, true)) { $family[] = $tempId; }
        $familyByPendingTempId[$tempId] = $family;
        $allFamilyTempIds = array_merge($allFamilyTempIds, $family);
    }
    $allFamilyTempIds = array_values(array_unique($allFamilyTempIds));
    if (empty($allFamilyTempIds)) { return $invoicesByShopTempId; }

    $invoicesByRawTempId = [];
    $ph = implode(',', array_fill(0, count($allFamilyTempIds), '?'));
    $types = 'i' . str_repeat('s', count($allFamilyTempIds));
    // NOT EXISTS ... tp_orders — an invoice already linked (via an earlier
    // Convert) to SOME Get Order must never be offered again for a DIFFERENT
    // one; one invoice can only ever back one order. 2026-11-24, user-
    // requested ("oru invoice ahh oru shop ithukku than use pannanum... tp
    // kudutha antha invoice number dm ku also katta kudathu" — already-used
    // invoices must disappear from BOTH the TP's and the DM's own picker,
    // since they share this same lookup).
    $stmtInv = $db_conn->prepare(
        "SELECT ui.inv_id, ui.inv_number, ui.date, ui.created_at, ui.to_user_id,
                EXISTS(SELECT 1 FROM receipt r WHERE r.inv_id=ui.inv_id) AS receipted
         FROM user_invoice ui
         WHERE ui.from_user_type='territory_partner' AND ui.from_user_id=?
           AND ui.to_user_type='shop' AND ui.to_user_id IN ($ph)
           AND NOT EXISTS (SELECT 1 FROM tp_orders t2 WHERE t2.invoiced_inv_id = ui.inv_id)
         ORDER BY ui.date DESC, ui.inv_number DESC"
    );
    $stmtInv->bind_param($types, $tpId, ...$allFamilyTempIds);
    $stmtInv->execute();
    $resInv = $stmtInv->get_result();
    while ($ir = $resInv->fetch_assoc()) {
        $invoicesByRawTempId[$ir['to_user_id']][] = [
            'inv_id'     => $ir['inv_id'],
            'inv_number' => $ir['inv_number'],
            'date'       => date('d-m-Y', strtotime($ir['date'])),
            'placed_at'  => !empty($ir['created_at']) ? date('d-m-Y h:i A', strtotime($ir['created_at'])) : '',
            'completed'  => (bool)$ir['receipted'],
        ];
    }
    $stmtInv->close();

    foreach ($familyByPendingTempId as $pendingTempId => $family) {
        foreach ($family as $famTempId) {
            foreach ($invoicesByRawTempId[$famTempId] ?? [] as $inv) {
                $invoicesByShopTempId[$pendingTempId][] = $inv;
            }
        }
    }
    return $invoicesByShopTempId;
}

// Validates a chosen invoice really belongs to this TP and this shop
// (never trust the posted inv_id alone), then links it — shared by both
// convert-order-invoice.php (TP) and marketing/convert-order-invoice.php (DM).
// Returns a string error message on failure, or null on success.
function convertTpOrderToInvoice($db_conn, string $orderId, int $tpId, string $invId): ?string {
    $stmt = $db_conn->prepare(
        "SELECT o.id, o.new_order, o.invoiced_inv_id, o.voided_at, o.shop_id,
                s.temp_id AS shop_temp_id, s.name AS shop_name, s.mobile_number AS shop_mobile
         FROM tp_orders o LEFT JOIN shop s ON s.id=o.shop_id
         WHERE o.order_id=? AND o.tp_id=?"
    );
    $stmt->bind_param('si', $orderId, $tpId);
    $stmt->execute();
    $lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($lines)) { return "Order not found."; }
    if ($lines[0]['new_order'] !== 'yes') { return "This visit has no order to convert."; }
    if (!empty($lines[0]['voided_at'])) { return "This order has already been marked Not Converted."; }
    if (!empty($lines[0]['invoiced_inv_id'])) { return "This order has already been converted to an invoice."; }

    $shopTempId = $lines[0]['shop_temp_id'] ?? '';
    $orderShopKey = tpOrderDedupeKey($lines[0]['shop_name'] ?? '', $lines[0]['shop_mobile'] ?? '');

    // Matches either the order's own literal shop_temp_id, OR (duplicate-
    // shop case — same as getInvoicesByShopTempIdForTp() above) any other
    // shop record this TP owns sharing the same name+mobile dedupe key.
    // Without the OR, an invoice correctly OFFERED in the Convert list (via
    // the dedupe-aware lookup) could still be rejected right here. 2026-11-24,
    // user-reported (shop "jayadeepa Femi9WD", invoice raised to its
    // duplicate shop record was listed but then refused on Confirm Convert).
    $stmtInv = $db_conn->prepare(
        "SELECT ui.inv_id, s2.name AS inv_shop_name, s2.mobile_number AS inv_shop_mobile
         FROM user_invoice ui
         LEFT JOIN shop s2 ON s2.temp_id = ui.to_user_id
         WHERE ui.inv_id=? AND ui.from_user_type='territory_partner' AND ui.from_user_id=?
           AND ui.to_user_type='shop'"
    );
    $stmtInv->bind_param('si', $invId, $tpId);
    $stmtInv->execute();
    $invRow = $stmtInv->get_result()->fetch_assoc();
    $stmtInv->close();

    if (!$invRow) { return "That invoice does not belong to this shop."; }
    $invShopKey = tpOrderDedupeKey($invRow['inv_shop_name'] ?? '', $invRow['inv_shop_mobile'] ?? '');
    $sameShop = ($orderShopKey !== null && $invShopKey !== null && $orderShopKey === $invShopKey);
    if (!$sameShop) {
        // Fall back to an exact to_user_id check only when a dedupe key
        // couldn't be computed for one side (e.g. no 10-digit mobile).
        $stmtInv2 = $db_conn->prepare("SELECT inv_id FROM user_invoice WHERE inv_id=? AND to_user_id=?");
        $stmtInv2->bind_param('ss', $invId, $shopTempId);
        $stmtInv2->execute();
        $sameShop = (bool)$stmtInv2->get_result()->fetch_assoc();
        $stmtInv2->close();
    }
    if (!$sameShop) { return "That invoice does not belong to this shop."; }

    // One invoice can only ever back one order — re-checked here too (not
    // just at the picker-list stage) as a race-condition guard, e.g. two
    // browser tabs converting different orders to the same invoice at
    // nearly the same time. 2026-11-24, user-requested.
    $stmtUsed = $db_conn->prepare("SELECT order_id FROM tp_orders WHERE invoiced_inv_id=? LIMIT 1");
    $stmtUsed->bind_param('s', $invId);
    $stmtUsed->execute();
    $usedRow = $stmtUsed->get_result()->fetch_assoc();
    $stmtUsed->close();
    if ($usedRow) { return "That invoice has already been used to convert another order."; }

    $chkInvoicedAt = $db_conn->query("SHOW COLUMNS FROM tp_orders LIKE 'invoiced_at'");
    if ($chkInvoicedAt && $chkInvoicedAt->num_rows === 0) {
        $db_conn->query("ALTER TABLE tp_orders ADD COLUMN invoiced_at DATETIME NULL DEFAULT NULL AFTER invoiced_inv_id");
    }

    $stmtMark = $db_conn->prepare("UPDATE tp_orders SET invoiced_inv_id=?, invoiced_at=NOW() WHERE order_id=? AND tp_id=?");
    $stmtMark->bind_param('ssi', $invId, $orderId, $tpId);
    $stmtMark->execute();
    $stmtMark->close();
    return null;
}
