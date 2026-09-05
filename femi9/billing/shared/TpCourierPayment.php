<?php
/**
 * Courier payment for TP Purchase Orders — before a TP's cart can be
 * finally submitted as a real tp_purchase_orders row, they must pay a
 * courier fee computed from the shipment's Total Boxes (the exact same
 * dispatchSlipComputeBoxes() math dispatch-slip-print.php uses after
 * submission, so the fee quoted here always matches what the real dispatch
 * slip would later show) and upload proof, same screenshot-pool pattern as
 * the advance-payment excess flow: any accepted screenshot's amount joins
 * a pool (tp_courier_payments, po_id IS NULL) and purchase-order-action.php
 * requires that pool to cover the required amount before creating the PO,
 * then claims every pooled row onto the new po_id.
 *
 * Rates are company-configurable (company/courier-payment-settings.php),
 * not hardcoded — defaults match what the business first confirmed
 * 2026-09-03: napkin ₹80/box, but if the order's Total Boxes exceeds a
 * configurable threshold (default 10), EVERY box in that order re-rates to
 * a second configurable rate (default ₹60, flat, not a slab). Diaper is
 * always flat at its own configurable rate (default ₹80/box, no tiering).
 * Cover rate (default ₹50) is also configurable, flat regardless of type.
 */
require_once __DIR__ . '/DispatchSlipSettings.php';

function tpEnsureCourierPaymentTables(mysqli $db): void
{
    $db->query("
        CREATE TABLE IF NOT EXISTS courier_payment_settings (
          id INT UNSIGNED NOT NULL PRIMARY KEY,
          qr_image_path VARCHAR(255) NULL,
          upi_id VARCHAR(100) NULL,
          upi_payee_name VARCHAR(100) NULL,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $db->query("INSERT IGNORE INTO courier_payment_settings (id) VALUES (1)");

    // Self-migrating for a settings row created before the UPI deep-link
    // fields existed — a TP paying from the SAME phone that's showing the
    // QR code can't scan it with their own camera, so a tappable "Pay via
    // UPI app" button (upi://pay deep link) needs the raw UPI ID as text,
    // not just baked into the QR image.
    $col = $db->query("SHOW COLUMNS FROM courier_payment_settings LIKE 'upi_id'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE courier_payment_settings ADD COLUMN upi_id VARCHAR(100) NULL AFTER qr_image_path");
        $db->query("ALTER TABLE courier_payment_settings ADD COLUMN upi_payee_name VARCHAR(100) NULL AFTER upi_id");
    }

    // Self-migrating for a settings row created before the rates themselves
    // were made company-editable — defaults match the values the business
    // originally confirmed hardcoded (2026-09-03), so an existing row's
    // effective behavior doesn't change the moment this column appears.
    $col2 = $db->query("SHOW COLUMNS FROM courier_payment_settings LIKE 'napkin_box_rate'");
    if ($col2 && $col2->num_rows === 0) {
        $db->query("ALTER TABLE courier_payment_settings
            ADD COLUMN napkin_box_rate DECIMAL(10,2) NOT NULL DEFAULT 80.00,
            ADD COLUMN napkin_box_rate_tier2 DECIMAL(10,2) NOT NULL DEFAULT 60.00,
            ADD COLUMN napkin_tier2_threshold INT UNSIGNED NOT NULL DEFAULT 10,
            ADD COLUMN diaper_box_rate DECIMAL(10,2) NOT NULL DEFAULT 80.00,
            ADD COLUMN cover_rate DECIMAL(10,2) NOT NULL DEFAULT 50.00
        ");
    }

    $db->query("
        CREATE TABLE IF NOT EXISTS tp_courier_payments (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          territory_partner_id INT UNSIGNED NOT NULL,
          product_type ENUM('napkin','diaper') NOT NULL,
          total_boxes INT UNSIGNED NOT NULL,
          total_covers INT UNSIGNED NOT NULL DEFAULT 0,
          required_amount DECIMAL(10,2) NOT NULL,
          detected_amount DECIMAL(10,2) NULL,
          reference_number VARCHAR(100) NULL,
          ocr_raw_text TEXT NULL,
          image_hash CHAR(64) NULL,
          payment_date DATE NULL,
          file_path VARCHAR(255) NOT NULL,
          status ENUM('pending_review','accepted','rejected') NOT NULL DEFAULT 'pending_review',
          rejection_reason VARCHAR(500) NULL,
          po_id INT UNSIGNED NULL,
          reviewed_by VARCHAR(100) NULL,
          reviewed_at TIMESTAMP NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          KEY idx_tcp_pool (territory_partner_id, product_type, po_id),
          KEY idx_tcp_po (po_id),
          KEY idx_tcp_hash (image_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // Self-migrating for a table created before covers were charged for.
    $col = $db->query("SHOW COLUMNS FROM tp_courier_payments LIKE 'total_covers'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE tp_courier_payments ADD COLUMN total_covers INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_boxes");
    }

    // Self-migrating for a table created before duplicate-image detection and
    // payment-date validation existed.
    $col3 = $db->query("SHOW COLUMNS FROM tp_courier_payments LIKE 'image_hash'");
    if ($col3 && $col3->num_rows === 0) {
        $db->query("ALTER TABLE tp_courier_payments ADD COLUMN image_hash CHAR(64) NULL AFTER ocr_raw_text");
        $db->query("ALTER TABLE tp_courier_payments ADD COLUMN payment_date DATE NULL AFTER image_hash");
        $db->query("ALTER TABLE tp_courier_payments ADD KEY idx_tcp_hash (image_hash)");
    }
}

// A screenshot's exact image bytes reused by anyone (same TP re-uploading, or
// a different TP submitting someone else's proof) can't fund a second
// courier payment — checked regardless of the earlier upload's status, since
// a still-pending_review one hasn't been through company review yet and
// reusing it would let two different orders draw on the same real payment.
// Only a rejected upload's hash is safe to reuse (e.g. a blurry retake of
// the exact same screenshot).
function tpCourierImageIsDuplicate(mysqli $db, string $imageHash): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS cnt FROM tp_courier_payments WHERE image_hash = ? AND status != 'rejected'"
    );
    $stmt->bind_param('s', $imageHash);
    $stmt->execute();
    $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();
    return $cnt > 0;
}

/** @return array{napkin_rate:float,napkin_tier2_rate:float,napkin_tier2_threshold:int,diaper_rate:float,cover_rate:float} */
function tpCourierGetRateSettings(mysqli $db): array
{
    tpEnsureCourierPaymentTables($db);
    $row = $db->query(
        "SELECT napkin_box_rate, napkin_box_rate_tier2, napkin_tier2_threshold, diaper_box_rate, cover_rate
         FROM courier_payment_settings WHERE id = 1"
    )->fetch_assoc();
    return [
        'napkin_rate' => (float)($row['napkin_box_rate'] ?? 80),
        'napkin_tier2_rate' => (float)($row['napkin_box_rate_tier2'] ?? 60),
        'napkin_tier2_threshold' => (int)($row['napkin_tier2_threshold'] ?? 10),
        'diaper_rate' => (float)($row['diaper_box_rate'] ?? 80),
        'cover_rate' => (float)($row['cover_rate'] ?? 50),
    ];
}

function tpCourierRatePerBox(mysqli $db, string $productType, int $totalBoxes): float
{
    $rates = tpCourierGetRateSettings($db);
    if ($productType === 'diaper') return $rates['diaper_rate'];
    return $totalBoxes > $rates['napkin_tier2_threshold'] ? $rates['napkin_tier2_rate'] : $rates['napkin_rate'];
}

function tpCourierComputeAmount(mysqli $db, string $productType, int $totalBoxes, int $totalCovers = 0): float
{
    $rates = tpCourierGetRateSettings($db);
    $amount = 0.0;
    if ($totalBoxes > 0) {
        $boxRate = $productType === 'diaper'
            ? $rates['diaper_rate']
            : ($totalBoxes > $rates['napkin_tier2_threshold'] ? $rates['napkin_tier2_rate'] : $rates['napkin_rate']);
        $amount += $totalBoxes * $boxRate;
    }
    if ($totalCovers > 0) $amount += $totalCovers * $rates['cover_rate'];
    return round($amount, 2);
}

// Self-migrating: a TP near the company/warehouse can pick up some or all
// of an order in person instead of having it couriered — per confirmed
// business decision 2026-09-04, that line's qty is excluded from the box/
// cover count the courier fee is computed from entirely (not charged at
// all, not even at a reduced rate). Recorded per line on the real PO once
// submitted, purely for records — nothing currently reads it back besides
// this migration guard itself.
function tpEnsurePickupColumn(mysqli $db): void
{
    $col = $db->query("SHOW COLUMNS FROM tp_purchase_order_items LIKE 'delivery_method'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE tp_purchase_order_items ADD COLUMN delivery_method ENUM('pickup','courier') NOT NULL DEFAULT 'courier' AFTER product_id");
    }
}

// Self-migrating: lets the company manually correct the courier amount for
// one already-submitted order, when the auto box/cover calculation got it
// wrong for some reason — set once per PO, it then overrides
// tpCourierComputeAmount()'s own box/cover math for every retry-payment
// calculation on that specific order (pay-courier-payment.php's po_id
// branch, upload-courier-payment-screenshot.php's po_id branch) until the
// company clears it again. Pre-submission carts (no PO yet) have nothing to
// attach an override to — this only ever applies post-submission.
function tpEnsureCourierOverrideColumn(mysqli $db): void
{
    $col = $db->query("SHOW COLUMNS FROM tp_purchase_orders LIKE 'courier_amount_override'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE tp_purchase_orders ADD COLUMN courier_amount_override DECIMAL(10,2) NULL AFTER excess_amount");
    }
}

/**
 * Splits a cart into the subset actually needing courier, given a
 * pr_id => 'pickup'|'courier' map (pr_id as a STRING key, matching how the
 * cart's own JS keeps it) — a line missing from the map defaults to
 * 'courier' (the safe default: nothing is silently exempted from the fee
 * unless explicitly marked pickup).
 * @param array<int,array{pid:int,qty:int}> $items
 * @param array<string,string> $pickupMap
 * @return array<int,array{pid:int,qty:int}>
 */
function tpCourierFilterToCourierItems(array $items, array $pickupMap): array
{
    return array_values(array_filter($items, function ($it) use ($pickupMap) {
        return ($pickupMap[(string)$it['pid']] ?? 'courier') !== 'pickup';
    }));
}

/**
 * @param array<int,array{pid:int,qty:int}> $items
 * @return array{boxes:int,covers:int}
 */
function tpCourierComputeShipmentForItems(mysqli $db, array $items): array
{
    if (empty($items)) return ['boxes' => 0, 'covers' => 0];
    $pids = array_map(fn($it) => (int)$it['pid'], $items);
    $placeholders = implode(',', array_fill(0, count($pids), '?'));
    $types = str_repeat('i', count($pids));
    $stmt = $db->prepare("SELECT id, productName, packs_per_carton FROM products WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$pids);
    $stmt->execute();
    $res = $stmt->get_result();
    $byId = [];
    while ($r = $res->fetch_assoc()) { $byId[(int)$r['id']] = $r; }
    $stmt->close();

    $po_items = [];
    foreach ($items as $it) {
        $p = $byId[(int)$it['pid']] ?? null;
        $po_items[] = [
            'qty' => (int)$it['qty'],
            'packs_per_carton' => $p['packs_per_carton'] ?? null,
            'productName' => $p['productName'] ?? '',
        ];
    }
    $totals = dispatchSlipComputeBoxes($db, $po_items);
    return ['boxes' => (int)$totals['TotalBoxes'], 'covers' => (int)$totals['TotalCovers']];
}

/** Sum of accepted, not-yet-linked-to-a-PO courier payments — the pool a pending PO submission draws against. */
// Counts both 'pending_review' and 'accepted' screenshots — same posture as
// the advance-payment excess flow's tpApoEligibleSubmissionFor() (scoped by
// status='pending_review', not 'accepted'), which lets a TP submit as soon
// as they've uploaded proof, with company review happening afterward rather
// than blocking submission until a human confirms it. An earlier version of
// this function required 'accepted' only, which created a real deadlock —
// company can only review a courier payment via manage-courier-payments.php,
// but a TP whose auto-verification landed on pending_review had no accepted
// row yet and so could never submit the order that same review queue exists
// to review. Fixed 2026-09-04, confirmed with the business owner. A rejected
// screenshot never counts.
//
// Sums COALESCE(detected_amount, required_amount), not detected_amount
// alone — a pending_review row can have a NULL detected_amount (auto-
// verification genuinely couldn't read a number off the screenshot at all,
// as opposed to reading a number that didn't match), and summing NULL as 0
// silently zeroed out that upload's contribution to the pool, blocking
// submission even though a screenshot genuinely was uploaded. Falling back
// to the row's own required_amount is the same "trust it until company
// reviews" posture as the rest of this pending_review-counts design — a
// wrong optimistic count here is caught the same way a wrong auto-accept
// would be, by the reviewer in manage-courier-payments.php. Fixed 2026-09-04.
function tpCourierPoolTotal(mysqli $db, int $tpId, string $productType): float
{
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(COALESCE(detected_amount, required_amount)), 0) AS total FROM tp_courier_payments
         WHERE territory_partner_id = ? AND product_type = ? AND po_id IS NULL AND status IN ('pending_review', 'accepted')"
    );
    $stmt->bind_param('is', $tpId, $productType);
    $stmt->execute();
    $total = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

/**
 * Same pool math as tpCourierPoolTotal(), but for a purchase order that
 * ALREADY exists (a company rejection after submission, re-tried from
 * manage-purchase-orders.php's "Pay Courier Amount Again" button) — scoped
 * to that po_id's own screenshots instead of the pre-submission po_id-IS-NULL
 * pool, since a new retry upload for an existing order links straight to it.
 */
function tpCourierPoolTotalForPo(mysqli $db, int $poId): float
{
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(COALESCE(detected_amount, required_amount)), 0) AS total FROM tp_courier_payments
         WHERE po_id = ? AND status IN ('pending_review', 'accepted')"
    );
    $stmt->bind_param('i', $poId);
    $stmt->execute();
    $total = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

function tpCourierGetQrImagePath(mysqli $db): ?string
{
    tpEnsureCourierPaymentTables($db);
    $row = $db->query("SELECT qr_image_path FROM courier_payment_settings WHERE id = 1")->fetch_assoc();
    $path = $row['qr_image_path'] ?? null;
    return ($path !== null && $path !== '') ? $path : null;
}

/** @return array{upi_id:?string,payee_name:?string} */
function tpCourierGetUpiDetails(mysqli $db): array
{
    tpEnsureCourierPaymentTables($db);
    $row = $db->query("SELECT upi_id, upi_payee_name FROM courier_payment_settings WHERE id = 1")->fetch_assoc();
    $upiId = trim($row['upi_id'] ?? '');
    $payeeName = trim($row['upi_payee_name'] ?? '');
    return [
        'upi_id' => $upiId !== '' ? $upiId : null,
        'payee_name' => $payeeName !== '' ? $payeeName : 'Femi9',
    ];
}

/**
 * Standard UPI deep link (upi://pay?...) — tapping it on a phone that has a
 * UPI app installed opens that app's chooser directly with the amount
 * pre-filled, for the case a QR code can't solve: the TP paying from the
 * SAME phone that's showing this page can't scan a QR rendered on its own
 * screen. amount is formatted to exactly 2 decimals per the UPI spec.
 */
function tpCourierUpiDeepLink(string $upiId, string $payeeName, float $amount, string $note): string
{
    // http_build_query()'s default encoding (PHP_QUERY_RFC1738) encodes a
    // space as "+", which several UPI apps display literally instead of
    // decoding back to a space (seen in production: "Courier+Payment").
    // PHP_QUERY_RFC3986 encodes space as %20 instead, which every UPI app
    // handles correctly.
    return 'upi://pay?' . http_build_query([
        'pa' => $upiId,
        'pn' => $payeeName,
        'am' => number_format($amount, 2, '.', ''),
        'cu' => 'INR',
        'tn' => $note,
    ], '', '&', PHP_QUERY_RFC3986);
}
