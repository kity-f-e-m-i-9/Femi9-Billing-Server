<?php
// Stock Movement History — three-level drill-down (Company Profile+Warehouse
// card -> Product list -> full stock_ledger trail) so staff can answer "what
// invoices/transfers actually moved the stock that's sitting in G2 right
// now" without anyone needing DB access. Read-only, no writes anywhere.
// Confirmed 2026-09-25.
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/GodownWarehouseMapping.php");
require_once("include/PermissionCheck.php"); requirePermission('products');
error_reporting(0);

if (is_neksomo_login($db_conn)) { header("Location: dashboard.php"); exit; }

$user_type_Loginvl = "company";

$godownid   = isset($_GET['godownid']) ? (int) $_GET['godownid'] : 0;
$warehouseParam = $_GET['warehouse_id'] ?? null; // '' means Unassigned, null means "not chosen yet"
$productId  = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;

// Human label for one ref_type + ref_id pair — same convention this
// codebase already uses in similar spots (Auto Transfer History's
// inv_number lookups), just generalised across every stock_ledger
// ref_type instead of one feature's own.
function ledger_ref_label(string $refType, string $refId): string
{
    switch ($refType) {
        case 'invoice':      return 'Customer Invoice — ' . $refId;
        case 'user_invoice': return 'Shop Invoice — ' . $refId;
        case 'tp_invoice':   return 'TP Invoice — ' . $refId;
        case 'ot_sale':      return 'OT Channel Sale — ' . $refId;
        case 'transfer':     return 'Internal Transfer — ' . $refId;
        case 'return':       return 'Return — ' . $refId;
        case 'demofree':     return 'Demo / Free / Damage — ' . $refId;
        case 'adjustment':   return 'Manual Adjustment — ' . $refId;
        case 'conversion':   return 'Pieces/Pack Conversion — ' . $refId;
        default:             return $refType . ' — ' . $refId;
    }
}

function ledger_action_label(string $action): array
{
    // [label, direction] — direction only decides the +/- color, the qty
    // column itself already carries the real signed effect via before/after.
    $map = [
        'deduct'               => ['Sale / Invoice Deduct', 'out'],
        'credit'                => ['Credit', 'in'],
        'reverse_deduct'       => ['Reversed (Deduct undone)', 'in'],
        'reverse_credit'       => ['Reversed (Credit undone)', 'out'],
        'transfer_out'         => ['Transferred Out', 'out'],
        'transfer_in'          => ['Transferred In', 'in'],
        'transfer_out_reverse' => ['Transfer-Out Undone', 'in'],
        'transfer_in_reverse'  => ['Transfer-In Undone', 'out'],
        'return_accept'        => ['Return Accepted', 'in'],
        'return_reject'        => ['Return Rejected', 'out'],
        'ot_deduct'            => ['OT Sale Deduct', 'out'],
        'ot_reverse'           => ['OT Sale Reversed', 'in'],
    ];
    return $map[$action] ?? [$action, 'in'];
}

$warehouseNames = [];
$whRes = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
while ($whRes && ($whRow = $whRes->fetch_assoc())) {
    $warehouseNames[(int)$whRow['id']] = $whRow['code'] . ($whRow['name'] ? ' - ' . $whRow['name'] : '');
}

$godownNames = [];
$gRes = $db_conn->query("SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC");
while ($gRes && ($gRow = $gRes->fetch_assoc())) {
    $godownNames[(int)$gRow['id']] = $gRow['gname'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock Movement History : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .smh-crumb { font-size: 13px; color: #6b7280; margin-bottom: 14px; }
        .smh-crumb a { color: #667eea; text-decoration: none; }
        .smh-crumb a:hover { text-decoration: underline; }
        .smh-card { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.06); padding:18px 20px; margin-bottom:16px; text-decoration:none; display:block; color:inherit; transition:box-shadow .15s, transform .1s; border:1px solid #eef0f3; }
        .smh-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.1); transform:translateY(-1px); color:inherit; }
        .smh-card h5 { margin:0 0 4px; font-weight:600; font-size:15px; color:#1f2937; }
        .smh-card .smh-sub { font-size:12px; color:#9ca3af; }
        .smh-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:14px; }
        .smh-table { width:100%; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.06); }
        .smh-table th { background:#f8fafc; font-size:11px; text-transform:uppercase; letter-spacing:.03em; color:#6b7280; padding:10px 14px; text-align:left; }
        .smh-table td { padding:10px 14px; font-size:13px; border-top:1px solid #f1f5f9; }
        .smh-badge-in { background:#dcfce7; color:#166534; font-weight:600; padding:2px 8px; border-radius:6px; font-size:11.5px; }
        .smh-badge-out { background:#fee2e2; color:#991b1b; font-weight:600; padding:2px 8px; border-radius:6px; font-size:11.5px; }
        .smh-page-title { font-size:20px; font-weight:600; color:#1f2937; margin-bottom:6px; }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">

                    <div class="smh-page-title">Stock Movement History</div>

                    <?php if (!$godownid): ?>
                        <p class="text-muted small">Pick a company profile + warehouse to see every product's current stock there, then drill into one product's full movement history.</p>
                        <div class="smh-grid">
                        <?php foreach ($godownNames as $gid => $gname): ?>
                            <?php
                            $linkedWarehouses = get_warehouses_for_godown($db_conn, $gid);
                            if (empty($linkedWarehouses)) {
                                foreach ($warehouseNames as $whId => $whLabel) { $linkedWarehouses[] = ['id' => $whId, 'code' => $whLabel, 'name' => null]; }
                            }
                            ?>
                            <?php foreach ($linkedWarehouses as $wh): ?>
                            <a class="smh-card" href="?godownid=<?php echo (int) $gid; ?>&warehouse_id=<?php echo (int) $wh['id']; ?>">
                                <h5><?php echo htmlspecialchars($gname, ENT_QUOTES, 'UTF-8'); ?></h5>
                                <div class="smh-sub"><?php echo htmlspecialchars($warehouseNames[(int)$wh['id']] ?? $wh['code'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </a>
                            <?php endforeach; ?>
                            <a class="smh-card" href="?godownid=<?php echo (int) $gid; ?>&warehouse_id=">
                                <h5><?php echo htmlspecialchars($gname, ENT_QUOTES, 'UTF-8'); ?></h5>
                                <div class="smh-sub">Unassigned</div>
                            </a>
                        <?php endforeach; ?>
                        </div>

                    <?php elseif ($productId === 0): ?>
                        <?php
                        $whIsUnassigned = ($warehouseParam === '' || $warehouseParam === null);
                        $whId = $whIsUnassigned ? null : (int) $warehouseParam;
                        $whLabel = $whIsUnassigned ? 'Unassigned' : ($warehouseNames[$whId] ?? "Warehouse #$whId");
                        ?>
                        <div class="smh-crumb"><a href="stock-movement-history.php">Stock Movement History</a> &rsaquo; <?php echo htmlspecialchars(($godownNames[$godownid] ?? "Profile #$godownid") . ' — ' . $whLabel, ENT_QUOTES, 'UTF-8'); ?></div>

                        <table class="smh-table">
                            <thead><tr><th>Product Name</th><th style="text-align:right;">Closing Qty</th><th></th></tr></thead>
                            <tbody>
                            <?php
                            $stmt = $db_conn->prepare(
                                "SELECT s.product_id, s.closing_qty, p.productName
                                 FROM stock s INNER JOIN products p ON p.id = s.product_id
                                 WHERE s.user_type = 'company' AND s.user_id = ?
                                   AND " . ($whIsUnassigned ? "s.warehouse_id IS NULL" : "s.warehouse_id = ?") . "
                                 ORDER BY p.productName ASC"
                            );
                            if ($whIsUnassigned) {
                                $stmt->bind_param('s', $godownid);
                            } else {
                                $stmt->bind_param('si', $godownid, $whId);
                            }
                            $stmt->execute();
                            $stockRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            $stmt->close();
                            if (empty($stockRows)) {
                                echo '<tr><td colspan="3" class="text-muted">No products with any stock row here.</td></tr>';
                            }
                            foreach ($stockRows as $sr):
                                $historyUrl = '?godownid=' . $godownid . '&warehouse_id=' . htmlspecialchars((string)$warehouseParam, ENT_QUOTES, 'UTF-8') . '&product_id=' . (int) $sr['product_id'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sr['productName'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="text-align:right;font-weight:600;"><?php echo (int) $sr['closing_qty']; ?></td>
                                    <td style="text-align:right;"><a href="<?php echo $historyUrl; ?>" class="btn btn-sm btn-outline-primary">View Movements</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php else: ?>
                        <?php
                        $whIsUnassigned = ($warehouseParam === '' || $warehouseParam === null);
                        $whId = $whIsUnassigned ? null : (int) $warehouseParam;
                        $whLabel = $whIsUnassigned ? 'Unassigned' : ($warehouseNames[$whId] ?? "Warehouse #$whId");

                        $prodStmt = $db_conn->prepare("SELECT productName FROM products WHERE id = ?");
                        $prodStmt->bind_param('i', $productId);
                        $prodStmt->execute();
                        $productName = $prodStmt->get_result()->fetch_assoc()['productName'] ?? "Product #$productId";
                        $prodStmt->close();

                        $backUrl = '?godownid=' . $godownid . '&warehouse_id=' . htmlspecialchars((string)$warehouseParam, ENT_QUOTES, 'UTF-8');

                        // Optional date-range filter — narrows the ledger rows shown,
                        // but the running Before/After balances stay the real,
                        // unfiltered ones from stock_ledger itself (never
                        // recomputed relative to the filtered window), so a
                        // filtered view still shows genuine stock levels at each
                        // moment rather than a confusing reset-to-zero illusion.
                        $fromDate = trim($_GET['from_date'] ?? '');
                        $toDate   = trim($_GET['to_date'] ?? '');
                        $fromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ? $fromDate : '';
                        $toDate   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) ? $toDate : '';
                        ?>
                        <div class="smh-crumb">
                            <a href="stock-movement-history.php">Stock Movement History</a> &rsaquo;
                            <a href="<?php echo $backUrl; ?>"><?php echo htmlspecialchars(($godownNames[$godownid] ?? "Profile #$godownid") . ' — ' . $whLabel, ENT_QUOTES, 'UTF-8'); ?></a> &rsaquo;
                            <?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>
                        </div>

                        <form method="get" style="display:flex;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:14px;background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 2px 10px rgba(0,0,0,0.06);">
                            <input type="hidden" name="godownid" value="<?php echo (int) $godownid; ?>">
                            <input type="hidden" name="warehouse_id" value="<?php echo htmlspecialchars((string) $warehouseParam, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="product_id" value="<?php echo (int) $productId; ?>">
                            <div>
                                <label class="form-label" style="font-size:12px;font-weight:600;color:#6b7280;display:block;">From Date</label>
                                <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label class="form-label" style="font-size:12px;font-weight:600;color:#6b7280;display:block;">To Date</label>
                                <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-sm">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <?php if ($fromDate || $toDate): ?>
                                <a href="?godownid=<?php echo (int) $godownid; ?>&warehouse_id=<?php echo htmlspecialchars((string) $warehouseParam, ENT_QUOTES, 'UTF-8'); ?>&product_id=<?php echo (int) $productId; ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </form>

                        <table class="smh-table">
                            <thead><tr><th>Date &amp; Time</th><th>Movement</th><th>Source</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Before</th><th style="text-align:right;">After</th></tr></thead>
                            <tbody>
                            <?php
                            $dateSql = '';
                            if ($fromDate !== '') { $dateSql .= " AND created_at >= '" . $db_conn->real_escape_string($fromDate) . " 00:00:00'"; }
                            if ($toDate !== '')   { $dateSql .= " AND created_at <= '" . $db_conn->real_escape_string($toDate) . " 23:59:59'"; }

                            $stmt = $db_conn->prepare(
                                "SELECT action, qty, qty_before, qty_after, ref_type, ref_id, created_at
                                 FROM stock_ledger
                                 WHERE product_id = ? AND user_type = 'company' AND user_id = ?
                                   AND " . ($whIsUnassigned ? "warehouse_id IS NULL" : "warehouse_id = ?") . "
                                   $dateSql
                                 ORDER BY id ASC"
                            );
                            if ($whIsUnassigned) {
                                $stmt->bind_param('is', $productId, $godownid);
                            } else {
                                $stmt->bind_param('isi', $productId, $godownid, $whId);
                            }
                            $stmt->execute();
                            $ledgerRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            $stmt->close();
                            if (empty($ledgerRows)) {
                                echo '<tr><td colspan="6" class="text-muted">No recorded movements for this product here — its stock may pre-date the ledger system, or was set via a direct migration/import.</td></tr>';
                            }
                            foreach ($ledgerRows as $lr):
                                [$actionLabel, $dir] = ledger_action_label($lr['action']);
                                $badgeClass = $dir === 'in' ? 'smh-badge-in' : 'smh-badge-out';
                                $sign = $dir === 'in' ? '+' : '-';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('d/M/Y h:i A', strtotime($lr['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="<?php echo $badgeClass; ?>"><?php echo $sign . (int) $lr['qty']; ?></span> <?php echo htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(ledger_ref_label($lr['ref_type'], $lr['ref_id']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="text-align:right;"><?php echo (int) $lr['qty']; ?></td>
                                    <td style="text-align:right;color:#9ca3af;"><?php echo (int) $lr['qty_before']; ?></td>
                                    <td style="text-align:right;font-weight:600;"><?php echo (int) $lr['qty_after']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
