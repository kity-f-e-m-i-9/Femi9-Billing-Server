<?php
/**
 * One-time backfill for tp_purchase_orders.product_type — same issue as
 * tp_invoices.product_type: the self-migrating ALTER TABLE that added this
 * column (see db_migrations/2026_08_18_tp_napkin_diaper_purchase_order_type.sql)
 * defaults every pre-existing PO to 'napkin' regardless of what it actually
 * contained. This traces each PO's real line items back to products.category
 * and corrects the column.
 *
 * Classification per PO:
 *   - All items Napkin  -> 'napkin' (already the default; a no-op update)
 *   - All items Diaper  -> 'diaper'
 *   - Mixed items        -> left at 'napkin', printed for manual review
 *   - No items            -> left at 'napkin' (shouldn't happen, safety net)
 *
 * Safe to re-run: idempotent, only recomputes from live item data each time.
 *
 * Usage: php 2026_08_18_tp_napkin_diaper_purchase_order_type_backfill.php [--apply]
 */

require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/../shared/TpProductType.php';

$servername = $_ENV['DB_HOST']     ?? 'localhost';
$db_port    = (int)($_ENV['DB_PORT'] ?? 3306);
$username   = $_ENV['DB_USERNAME'] ?? 'billing0femi9_femi9admin';
$password   = $_ENV['DB_PASSWORD'] ?? 'mavNip-xukvyk-9veqra';
$dbname     = $_ENV['DB_NAME']     ?? 'billing0femi9_billingapp';

$db = new mysqli($servername, $username, $password, $dbname, $db_port);
if ($db->connect_errno) {
    fwrite(STDERR, "DB connection failed: {$db->connect_error}\n");
    exit(1);
}

$apply = in_array('--apply', $argv ?? [], true);

$pos = $db->query("SELECT id, product_type FROM tp_purchase_orders ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$toNapkin = [];
$toDiaper = [];
$mixed = [];
$noItems = [];

foreach ($pos as $po) {
    $cats = $db->query("
        SELECT DISTINCT CASE WHEN p.category = 'diaper' THEN 'diaper' ELSE 'napkin' END AS derived_type
        FROM tp_purchase_order_items poi
        JOIN products p ON p.id = poi.product_id
        WHERE poi.po_id = {$po['id']}
    ")->fetch_all(MYSQLI_ASSOC);

    $types = array_unique(array_column($cats, 'derived_type'));

    if (count($types) === 0) {
        $noItems[] = $po['id'];
    } elseif (count($types) === 1) {
        if ($types[0] === 'diaper') $toDiaper[] = $po['id'];
        else $toNapkin[] = $po['id'];
    } else {
        $mixed[] = $po['id'];
    }
}

echo "Total POs: " . count($pos) . "\n";
echo "  -> napkin: " . count($toNapkin) . "\n";
echo "  -> diaper: " . count($toDiaper) . "\n";
echo "  -> mixed (left at napkin, needs manual review): " . count($mixed) . "\n";
echo "  -> no items (left at napkin default): " . count($noItems) . "\n";

if (!empty($mixed)) {
    echo "\n--- MIXED POs (manual review) ---\n";
    echo implode(', ', $mixed) . "\n";
}

if (!$apply) {
    echo "\nDry run only — no changes written. Re-run with --apply to perform the UPDATEs.\n";
    exit(0);
}

$db->begin_transaction();
try {
    if (!empty($toDiaper)) {
        $ids = implode(',', array_map('intval', $toDiaper));
        $db->query("UPDATE tp_purchase_orders SET product_type='diaper' WHERE id IN ($ids)");
    }
    if (!empty($toNapkin)) {
        $ids = implode(',', array_map('intval', $toNapkin));
        $db->query("UPDATE tp_purchase_orders SET product_type='napkin' WHERE id IN ($ids)");
    }
    $db->commit();
    echo "\nApplied. " . count($toDiaper) . " POs set to diaper, " . count($toNapkin) . " confirmed napkin.\n";
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, "Backfill failed, rolled back: {$e->getMessage()}\n");
    exit(1);
}
