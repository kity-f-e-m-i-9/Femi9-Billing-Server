<?php
/**
 * One-time browser-triggered runner for the Napkin/Diaper backfill scripts
 * under db_migrations/ — for servers where only FTP/File Manager access is
 * available (no SSH/terminal to run the CLI versions directly).
 *
 * Same three backfills, same rules, just re-implemented here so they can run
 * inside a normal authenticated web request instead of `php script.php --apply`:
 *   1. tp_advance_payments.product_type  — traced via tp_invoice_advance_log
 *   2. tp_invoices.product_type          — traced via tp_invoice_items
 *   3. tp_purchase_orders.product_type   — traced via tp_purchase_order_items
 *
 * Safe to load repeatedly / re-run: every backfill only recomputes from live
 * item data and only touches rows with a single, unambiguous product type.
 * Mixed-type rows are always left alone and listed for manual review, never
 * guessed at.
 *
 * Delete this file once the migration has been run successfully on the
 * server — it has no reason to stay reachable afterward.
 */
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: dashboard?error=unauthorized"); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Ensure every column this migration touches actually exists yet ─────────
tpEnsureAdvanceWalletColumns($db_conn);
$_poCol = $db_conn->query("SHOW COLUMNS FROM tp_purchase_orders LIKE 'product_type'");
if ($_poCol && $_poCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_purchase_orders ADD COLUMN product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin' AFTER territory_partner_id");
}
$_invCol = $db_conn->query("SHOW COLUMNS FROM tp_invoices LIKE 'product_type'");
if ($_invCol && $_invCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin' AFTER territory_partner_id");
}

// ── Shared classification helper ────────────────────────────────────────────
function classifyByItems(mysqli $db, string $parentTable, string $itemTable, string $parentFk, string $parentIdCol = 'id'): array
{
    $parents = $db->query("SELECT $parentIdCol AS id FROM $parentTable ORDER BY $parentIdCol")->fetch_all(MYSQLI_ASSOC);
    $toNapkin = []; $toDiaper = []; $mixed = []; $noItems = [];
    foreach ($parents as $row) {
        $id = (int)$row['id'];
        $cats = $db->query("
            SELECT DISTINCT CASE WHEN p.category = 'diaper' THEN 'diaper' ELSE 'napkin' END AS derived_type
            FROM $itemTable it JOIN products p ON p.id = it.product_id
            WHERE it.$parentFk = $id
        ")->fetch_all(MYSQLI_ASSOC);
        $types = array_unique(array_column($cats, 'derived_type'));
        if (count($types) === 0) $noItems[] = $id;
        elseif (count($types) === 1) { if ($types[0] === 'diaper') $toDiaper[] = $id; else $toNapkin[] = $id; }
        else $mixed[] = $id;
    }
    return compact('toNapkin', 'toDiaper', 'mixed', 'noItems');
}

function classifyAdvancePayments(mysqli $db): array
{
    $rows = $db->query("SELECT id FROM tp_advance_payments WHERE deleted_at IS NULL ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    $toNapkin = []; $toDiaper = []; $mixed = []; $noHistory = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $cats = $db->query("
            SELECT DISTINCT CASE WHEN p.category = 'diaper' THEN 'diaper' ELSE 'napkin' END AS derived_type
            FROM tp_invoice_advance_log l
            JOIN tp_invoice_items tii ON tii.tp_invoice_id = l.tp_invoice_id
            JOIN products p ON p.id = tii.product_id
            WHERE l.tp_advance_id = $id
        ")->fetch_all(MYSQLI_ASSOC);
        $types = array_unique(array_column($cats, 'derived_type'));
        if (count($types) === 0) $noHistory[] = $id;
        elseif (count($types) === 1) { if ($types[0] === 'diaper') $toDiaper[] = $id; else $toNapkin[] = $id; }
        else $mixed[] = $id;
    }
    return compact('toNapkin', 'toDiaper', 'mixed', 'noHistory');
}

function applyBackfill(mysqli $db, string $table, array $result): void
{
    if (!empty($result['toDiaper'])) {
        $ids = implode(',', array_map('intval', $result['toDiaper']));
        $db->query("UPDATE $table SET product_type='diaper' WHERE id IN ($ids)");
    }
    if (!empty($result['toNapkin'])) {
        $ids = implode(',', array_map('intval', $result['toNapkin']));
        $db->query("UPDATE $table SET product_type='napkin' WHERE id IN ($ids)");
    }
}

$applied = false;
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Session expired — please reload this page and try again.');
    }

    $advResult = classifyAdvancePayments($db_conn);
    $invResult = classifyByItems($db_conn, 'tp_invoices', 'tp_invoice_items', 'tp_invoice_id');
    $poResult  = classifyByItems($db_conn, 'tp_purchase_orders', 'tp_purchase_order_items', 'po_id');

    $db_conn->begin_transaction();
    try {
        applyBackfill($db_conn, 'tp_advance_payments', $advResult);
        applyBackfill($db_conn, 'tp_invoices', $invResult);
        applyBackfill($db_conn, 'tp_purchase_orders', $poResult);
        $db_conn->commit();
        $applied = true;
        $results = ['adv' => $advResult, 'inv' => $invResult, 'po' => $poResult];
    } catch (\Throwable $e) {
        $db_conn->rollback();
        die('Migration failed and was rolled back: ' . htmlspecialchars($e->getMessage()));
    }
} else {
    // Dry run — always shown, whether this is a first visit or a reload
    // after applying (so the counts prove it settled at zero more to do).
    $results = [
        'adv' => classifyAdvancePayments($db_conn),
        'inv' => classifyByItems($db_conn, 'tp_invoices', 'tp_invoice_items', 'tp_invoice_id'),
        'po'  => classifyByItems($db_conn, 'tp_purchase_orders', 'tp_purchase_order_items', 'po_id'),
    ];
}

function renderCounts(string $label, array $r, string $mixedNoun): string
{
    $html = "<h3>$label</h3><ul>";
    $html .= "<li>Napkin: " . count($r['toNapkin']) . "</li>";
    $html .= "<li>Diaper: " . count($r['toDiaper']) . "</li>";
    $key = array_key_exists('noHistory', $r) ? 'noHistory' : 'noItems';
    $html .= "<li>No history / left at default: " . count($r[$key]) . "</li>";
    $html .= "<li>Mixed (left at Napkin, needs manual review): " . count($r['mixed']);
    if (!empty($r['mixed'])) {
        $html .= " &mdash; $mixedNoun ids: " . htmlspecialchars(implode(', ', $r['mixed']));
    }
    $html .= "</li></ul>";
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Napkin/Diaper Backfill Migration</title>
<style>
body { font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; color: #1f2937; }
h1 { font-size: 22px; }
h3 { margin-top: 24px; margin-bottom: 6px; font-size: 16px; }
ul { margin: 0; padding-left: 20px; }
.banner { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; }
.banner.info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; }
.banner.success { background: #dcfce7; border: 1px solid #86efac; color: #14532d; }
button { background: #4f46e5; color: #fff; border: none; padding: 12px 22px; border-radius: 6px; font-size: 15px; cursor: pointer; margin-top: 20px; }
button:hover { background: #4338ca; }
</style>
</head>
<body>
<h1>Napkin / Diaper Product Type Backfill</h1>

<?php if ($applied): ?>
<div class="banner success">Migration applied successfully. Below are the final counts.</div>
<?php else: ?>
<div class="banner info">This is a dry run — nothing has been changed yet. Review the counts below, then click "Apply Migration" to write them.</div>
<?php endif; ?>

<?= renderCounts('1. Advance Payments (tp_advance_payments)', $results['adv'], 'advance payment') ?>
<?= renderCounts('2. Invoices (tp_invoices)', $results['inv'], 'invoice') ?>
<?= renderCounts('3. Purchase Orders (tp_purchase_orders)', $results['po'], 'PO') ?>

<?php if (!$applied): ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="apply">
    <button type="submit" onclick="return confirm('This will update product_type on the rows listed above. Continue?');">Apply Migration</button>
</form>
<?php else: ?>
<p style="margin-top:20px;color:#6b7280;">Reload this page any time to re-check counts (safe to re-run — it recomputes from live data and never overwrites a row a human already corrected).</p>
<?php endif; ?>

</body>
</html>
