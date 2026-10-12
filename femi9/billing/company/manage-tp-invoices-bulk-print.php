<?php
// Bulk-print view for TP Invoices — prints every invoice matching the exact
// same filters as manage-tp-invoices.php's "Print All" button (same query
// params: state_id/location_id/tp_id/date_from/date_to/type_filter/src),
// one after another with a page-break between each, as a single browser
// print job instead of opening each invoice's Print page one at a time.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner_edit');
require_once("include/GodownAccess.php");
require_once __DIR__ . '/../shared/TpInvoiceData.php';
require_once __DIR__ . '/../shared/TpInvoiceHtml.php';
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// ── Same filter parsing as manage-tp-invoices.php ───────────────────────────
$filter_location_ids = array_values(array_unique(array_filter(array_map('intval', (array)($_GET['location_id'] ?? [])))));
$filter_state_id   = (int)($_GET['state_id'] ?? 0);
$filter_tp_id       = (int)($_GET['tp_id'] ?? 0);
$filter_date_from   = trim($_GET['date_from'] ?? '');
$filter_date_to     = trim($_GET['date_to']   ?? '');
$filter_type        = $_GET['type_filter'] ?? '';
if (!in_array($filter_type, ['napkin', 'diaper'], true)) $filter_type = '';
$filter_src_raw = trim($_GET['src'] ?? '');
$filter_src_cp_id = 0; $filter_src_godown_id = 0; $filter_src_name = '';
if (preg_match('/^cp:(\d+)$/', $filter_src_raw, $m)) { $filter_src_cp_id = (int)$m[1]; }
elseif (preg_match('/^gd:(\d+)$/', $filter_src_raw, $m)) { $filter_src_godown_id = (int)$m[1]; }

$where  = ['(tpi.source_cp_id > 0 OR tpi.source_godown_id > 0)'];
$params = [];
$types  = '';

if (!empty($filter_location_ids)) {
    $placeholders = implode(',', array_fill(0, count($filter_location_ids), '?'));
    $where[]  = "tpi.source_location_id IN ($placeholders)";
    foreach ($filter_location_ids as $lid) { $params[] = $lid; $types .= 'i'; }
} elseif ($filter_state_id > 0) {
    $where[]  = "(pln.id IS NOT NULL AND (pln.id = ? OR pln.parent_id = ?))";
    $params[] = $filter_state_id;
    $params[] = $filter_state_id;
    $types   .= 'ii';
}

if ($filter_tp_id > 0) {
    $where[]  = "tpi.territory_partner_id = ?";
    $params[] = $filter_tp_id;
    $types   .= 'i';
}
if ($filter_date_from !== '') { $where[] = "tpi.invoice_date >= ?"; $params[] = $filter_date_from; $types .= 's'; }
if ($filter_date_to   !== '') { $where[] = "tpi.invoice_date <= ?"; $params[] = $filter_date_to;   $types .= 's'; }
if ($filter_type !== '') { $where[] = "tpi.product_type = ?"; $params[] = $filter_type; $types .= 's'; }

if ($filter_src_cp_id > 0) {
    $where[] = "tpi.source_cp_id = ?"; $params[] = $filter_src_cp_id; $types .= 'i';
    $nameRow = $db_conn->query("SELECT name FROM channel_partners WHERE id = " . (int)$filter_src_cp_id)->fetch_assoc();
    $filter_src_name = $nameRow['name'] ?? '';
} elseif ($filter_src_godown_id > 0 && is_godown_allowed($db_conn, $filter_src_godown_id)) {
    $where[] = "tpi.source_godown_id = ?"; $params[] = $filter_src_godown_id; $types .= 'i';
    $nameRow = $db_conn->query("SELECT gname FROM company_godown WHERE id = " . (int)$filter_src_godown_id)->fetch_assoc();
    $filter_src_name = $nameRow['gname'] ?? '';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$sql = "
    SELECT tpi.id
    FROM tp_invoices tpi
    LEFT JOIN partner_location_nodes pln ON pln.id = tpi.source_location_id
    $where_sql
    ORDER BY tpi.invoice_date ASC, tpi.id ASC
";
if ($params) {
    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $invoiceIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    $stmt->close();
} else {
    $invoiceIds = array_column($db_conn->query($sql)->fetch_all(MYSQLI_ASSOC), 'id');
}

// Cap how many invoices a single print job renders — a few hundred full
// invoice layouts in one DOM is what a browser print dialog can still
// reasonably paginate; well beyond that it's a sign the filter needs
// narrowing (a date range or a specific source), not a bigger cap.
$MAX_BULK_PRINT = 300;
$truncated = count($invoiceIds) > $MAX_BULK_PRINT;
if ($truncated) { $invoiceIds = array_slice($invoiceIds, 0, $MAX_BULK_PRINT); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulk Print TP Invoices<?php echo $filter_src_name ? ' : ' . htmlspecialchars($filter_src_name) : ''; ?></title>
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background:#f3f4f6; }
        .bp-toolbar { position:sticky; top:0; z-index:20; background:#fff; border-bottom:1px solid #e5e7eb; padding:14px 20px; display:flex; align-items:center; gap:14px; box-shadow:0 1px 4px rgba(0,0,0,.06); }
        .bp-toolbar h5 { margin:0; font-size:15px; font-weight:700; color:#1f2937; }
        .bp-toolbar .bp-meta { font-size:12.5px; color:#6b7280; }
        .bp-btn-print { margin-left:auto; background:linear-gradient(135deg,#0369a1 0%,#0284c7 100%); color:#fff; border:none; border-radius:7px; padding:9px 20px; font-size:13.5px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
        .bp-empty { padding:60px 20px; text-align:center; color:#9ca3af; }
        .bp-invoice-wrap { page-break-after:always; padding:16px; }
        .bp-invoice-wrap:last-child { page-break-after:auto; }
        @media print {
            .bp-toolbar { display:none; }
            body { background:#fff; }
        }
    </style>
</head>
<body>
    <div class="bp-toolbar">
        <div>
            <h5>Bulk Print — TP Invoices</h5>
            <div class="bp-meta">
                <?php echo count($invoiceIds); ?> invoice(s)<?php echo $filter_src_name ? ' &middot; Source: ' . htmlspecialchars($filter_src_name) : ''; ?>
                <?php if ($filter_date_from || $filter_date_to): ?>
                    &middot; <?php echo htmlspecialchars($filter_date_from ?: '…'); ?> to <?php echo htmlspecialchars($filter_date_to ?: '…'); ?>
                <?php endif; ?>
                <?php if ($truncated): ?>
                    &middot; <span style="color:#b45309;">showing first <?php echo $MAX_BULK_PRINT; ?> — narrow the filter to print the rest</span>
                <?php endif; ?>
            </div>
        </div>
        <button type="button" class="bp-btn-print" onclick="window.print()">
            🖨 Print <?php echo count($invoiceIds); ?> Invoice<?php echo count($invoiceIds) === 1 ? '' : 's'; ?>
        </button>
    </div>

    <?php if (empty($invoiceIds)): ?>
        <div class="bp-empty">No invoices match this filter.</div>
    <?php else: foreach ($invoiceIds as $invId):
        $invData = load_tp_invoice_data($db_conn, (int)$invId);
        if (!$invData) continue;
        extract($invData);
        $show_carton_cols = $has_carton_data;
    ?>
        <div class="bp-invoice-wrap">
            <?php
            // forPdf=true deliberately — the normal Print page's own @media
            // print rule hides everything except a SINGLE #divToPrint and
            // absolutely-positions it at the page's top-left (fine for one
            // invoice per page load). With many invoices rendered on this one
            // page, that rule would stack every one of them on top of each
            // other at the same spot. Skipping it here and using this page's
            // own .bp-invoice-wrap { page-break-after } instead keeps each
            // invoice on its own printed page in document order.
            echo render_tp_invoice_html($invData, $show_carton_cols, true);
            ?>
        </div>
    <?php endforeach; endif; ?>
</body>
</html>
