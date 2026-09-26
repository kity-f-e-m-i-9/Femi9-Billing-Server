<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/NeksomoStockBridge.php");
require_once("include/AutoTransferDemand.php");
require_once("include/GodownWarehouseMapping.php");
include("config.php");
date_default_timezone_set("Asia/Kolkata");

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header("Location: dashboard.php");
    exit;
}

$neksomoId    = get_neksomo_godown_id($db_conn);
$healthcareId = resolve_godown_id_by_gname($db_conn, 'FEMI HEALTH CARE');
$llpId        = resolve_godown_id_by_gname($db_conn, 'FEMI NAYAN LLP');

if (!$neksomoId || !$healthcareId || !$llpId) {
    die("Auto Transfer is unavailable: one or more required company profiles "
        . "(Neksomo / FEMI HEALTH CARE / FEMI NAYAN LLP) could not be found "
        . "in company_godown.");
}

$allWarehouses = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC")->fetch_all(MYSQLI_ASSOC);

// Each leg's endpoint has its own independently-scoped warehouse list —
// Source = Neksomo's linked warehouses, Intermediate = Healthcare's,
// Destination = LLP's — falling back to every active warehouse if
// nothing's mapped yet, so no picker is ever a dead end. Defaults to G1
// (by code) when present, on every one of the three pickers.
$sourceWarehouseOptions = get_warehouses_for_godown($db_conn, $neksomoId);
if (empty($sourceWarehouseOptions)) {
    $sourceWarehouseOptions = $allWarehouses;
}

$intermediateWarehouseOptions = get_warehouses_for_godown($db_conn, $healthcareId);
if (empty($intermediateWarehouseOptions)) {
    $intermediateWarehouseOptions = $allWarehouses;
}

$destWarehouseOptions = get_warehouses_for_godown($db_conn, $llpId);
if (empty($destWarehouseOptions)) {
    $destWarehouseOptions = $allWarehouses;
}

function default_warehouse_id(array $options): ?int
{
    foreach ($options as $wh) {
        if ($wh['code'] === 'G1') return (int) $wh['id'];
    }
    return null;
}
$defaultSourceWarehouseId       = default_warehouse_id($sourceWarehouseOptions);
$defaultIntermediateWarehouseId = default_warehouse_id($intermediateWarehouseOptions);
$defaultDestWarehouseId         = default_warehouse_id($destWarehouseOptions);

$stockService = new StockService($db_conn);
$requirements = get_auto_transfer_requirements($db_conn, $llpId);
$defaultRates = get_auto_transfer_default_rates($db_conn);
$otDraftsOutsideLlp = get_ot_drafts_outside_llp_godown($db_conn, $llpId);
$waitingPoCount = get_auto_transfer_waiting_po_count($db_conn);
$waitingPoCountByType = get_auto_transfer_waiting_po_count_by_type($db_conn);

$rows = [];
if (!empty($requirements)) {
    $productIds = array_keys($requirements);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));
    $stmt = $db_conn->prepare("SELECT id, productName, category FROM products WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $productResult = $stmt->get_result();
    $productNames = [];
    $productCategories = [];
    while ($p = $productResult->fetch_assoc()) {
        $productNames[(int) $p['id']] = $p['productName'];
        $productCategories[(int) $p['id']] = $p['category'] === 'diaper' ? 'diaper' : 'napkin';
    }
    $stmt->close();

    foreach ($requirements as $pid => $required) {
        $tpRequired      = (int) $required['tp'];
        $otRequired      = (int) $required['ot'];
        $neksomoAvail    = (int) ($stockService->getClosingQty($pid, $Login_user_TYPEvl, (string) $neksomoId, $defaultSourceWarehouseId) ?? 0);
        $healthcareAvail = (int) ($stockService->getClosingQty($pid, $Login_user_TYPEvl, (string) $healthcareId, $defaultIntermediateWarehouseId) ?? 0);
        // Capped by Neksomo (leg 1 source) alone — Healthcare's own balance
        // is leftover from a prior run and irrelevant to how much leg 1 can
        // newly move today; adding it in was inflating the capped qty past
        // what Neksomo actually has to send.
        $available       = $neksomoAvail;
        $split           = cap_auto_transfer_qty_by_source($tpRequired, $otRequired, $available);
        $cappedQty       = $split['tp'] + $split['ot'];

        $rows[] = [
            'product_id'      => $pid,
            'product_name'    => $productNames[$pid] ?? "Product #$pid",
            'required'        => $tpRequired + $otRequired,
            'required_tp'     => $tpRequired,
            'required_ot'     => $otRequired,
            'capped'          => $cappedQty,
            'capped_tp'       => $split['tp'],
            'capped_ot'       => $split['ot'],
            'neksomo_avail'   => $neksomoAvail,
            'healthcare_avail'=> $healthcareAvail,
            'rate_healthcare' => $defaultRates[$pid]['healthcare'] ?? null,
            'rate_llp'        => $defaultRates[$pid]['llp'] ?? null,
            'category'        => $productCategories[$pid] ?? 'napkin',
        ];
    }
}

// Product-wise TP demand behind the "Total PO" stat card's hover tooltip —
// scoped to required_tp only (that stat counts waiting TP purchase orders,
// not OT drafts), split into the same napkin/diaper buckets as the
// "Napkin (n) / Lumi Diaper (n)" filter counts, so hovering answers "which
// products, how much, in which bucket" without opening "View All Orders".
$poProductBreakdown = ['napkin' => [], 'diaper' => []];
$poQtyByType = ['napkin' => 0, 'diaper' => 0];
foreach ($rows as $r) {
    if ((int) $r['required_tp'] <= 0) continue;
    $poProductBreakdown[$r['category']][] = $r;
    $poQtyByType[$r['category']] += (int) $r['required_tp'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auto Transfer for Orders : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --ata-tp-1: #667eea;
            --ata-tp-2: #764ba2;
            --ata-ot-1: #0891b2;
            --ata-ot-2: #0e7490;
        }
        .ata-page-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
        .ata-page-head h1 { font-size:20px; font-weight:600; color:#1f2937; margin:0; }
        .ata-manage-link { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:9px; background:#f3f4f6; color:#4b5563; text-decoration:none; transition:background .15s; }
        .ata-manage-link:hover { background:#e5e7eb; color:#1f2937; }

        .ata-warn-card { border:1px solid #fde68a; background:#fffbeb; border-left:4px solid #f59e0b; border-radius:10px; padding:14px 16px; margin-bottom:16px; }
        .ata-warn-card strong { color:#92400e; font-size:14px; }
        .ata-warn-card table th { font-size:11.5px; text-transform:uppercase; letter-spacing:.02em; color:#92400e; background:#fef3c7; }
        .ata-warn-card table td { font-size:12.5px; }

        .ata-card { border:1px solid #eef0f3; border-radius:14px; box-shadow:0 1px 3px rgba(16,24,40,.04); }
        .ata-intro { color:#6b7280; font-size:13.5px; line-height:1.55; margin-bottom:16px; }

        .ata-btn { display:inline-flex; align-items:center; gap:6px; border:none; border-radius:9px; color:#fff; font-size:13px; font-weight:500; padding:8px 14px; cursor:pointer; transition:filter .15s, transform .1s; }
        .ata-btn:active { transform:translateY(1px); }
        .ata-btn:hover { filter:brightness(1.06); color:#fff; }
        .ata-btn-tp { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); box-shadow:0 2px 6px rgba(102,126,234,.3); }
        .ata-btn-ot { background:linear-gradient(135deg, var(--ata-ot-1) 0%, var(--ata-ot-2) 100%); box-shadow:0 2px 6px rgba(8,145,178,.3); }
        .ata-btn-ghost { background:#f3f4f6; color:#374151; }
        .ata-btn-ghost:hover { background:#e5e7eb; color:#1f2937; }
        .ata-btn-submit { background:linear-gradient(135deg,#22c55e 0%,#15803d 100%); box-shadow:0 2px 8px rgba(21,128,61,.3); font-size:14px; padding:10px 20px; }
        .ata-actionbar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }

        .ata-summary { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; }
        .ata-stat { flex:1 1 150px; border:1px solid #eef0f3; border-radius:12px; padding:10px 14px; background:#fafbfc; }
        .ata-stat .num { font-size:19px; font-weight:700; color:#1f2937; line-height:1.2; }
        .ata-stat .lbl { font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.03em; margin-top:2px; }
        .ata-stat.warn .num { color:#b45309; }
        .ata-stat.tp .num { color:var(--ata-tp-2); }
        .ata-stat.ot .num { color:var(--ata-ot-2); }

        /* "Total PO" card hover -> Napkin vs Lumi Diaper qty summary only.
           "Products" card hover -> full per-product qty breakdown, split by
           type. Two separate cards, two separate tooltips — redesigned
           2026-09-26 per request (previously both lived combined on one
           card, then briefly as two independently-hoverable chips). */
        .ata-stat-hoverable { position:relative; cursor:default; }
        .ata-stat-tooltip {
            display:none; position:absolute; bottom:100%; left:0; margin-bottom:6px;
            z-index:50; background:#fff; border:1px solid #e5e7eb; border-radius:10px;
            box-shadow:0 -8px 24px rgba(16,24,40,.14); padding:10px 12px;
            min-width:220px; max-width:320px; max-height:300px; overflow-y:auto; text-align:left;
            cursor:default; font-weight:400; color:#374151;
        }
        /* Products card's tooltip opens downward instead — it's a longer
           per-product list and the user wants it below the card, unlike
           Total PO's short Napkin/Diaper summary which opens upward so it
           doesn't cover the View All Orders / Transfer History buttons. */
        .ata-stat-tooltip-below { top:100%; bottom:auto; margin-top:6px; margin-bottom:0; box-shadow:0 8px 24px rgba(16,24,40,.14); }
        .ata-stat-hoverable:hover .ata-stat-tooltip,
        .ata-stat-hoverable:focus-within .ata-stat-tooltip,
        .ata-stat-hoverable:focus .ata-stat-tooltip { display:block; }
        .ata-stat-tooltip-summary { display:flex; flex-direction:column; gap:6px; min-width:190px; }
        .ata-stat-tooltip-group-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.02em; color:#9ca3af; margin:8px 0 4px; }
        .ata-stat-tooltip-group-title:first-child { margin-top:0; }
        .ata-stat-tooltip-table { width:100%; border-collapse:collapse; font-size:12px; }
        .ata-stat-tooltip-table td { padding:3px 4px; border-bottom:1px solid #f8fafc; }
        .ata-stat-tooltip-table td:last-child { text-align:right; font-weight:600; color:#1f2937; white-space:nowrap; }
        .ata-stat-chip {
            /* Sized to match .ata-btn (the "View All Orders" button below)
               — same padding/font/radius, and stretched full-width of the
               tooltip so both chips read as a consistent pair of buttons
               rather than small inline pills. */
            display:flex; align-items:center; justify-content:center;
            font-size:13px; font-weight:500; border-radius:9px; padding:8px 14px; color:#fff;
        }
        .ata-stat-chip-napkin { background:#3b82f6; }
        .ata-stat-chip-diaper { background:#8b5cf6; }

        .ata-tag { display:inline-flex; align-items:center; gap:4px; border-radius:6px; font-size:11px; font-weight:600; padding:2px 7px; white-space:nowrap; }
        .ata-tag-tp { background:#eef0ff; color:#4c3f9e; }
        .ata-tag-ot { background:#e0f7fa; color:#0c5c6e; }
        .ata-tag-dot { width:6px; height:6px; border-radius:50%; display:inline-block; }
        .ata-tag-tp .ata-tag-dot { background:var(--ata-tp-1); }
        .ata-tag-ot .ata-tag-dot { background:var(--ata-ot-1); }

        .ata-rows { display:flex; flex-direction:column; gap:10px; }
        .ata-row-card { border:1px solid #eef0f3; border-radius:12px; padding:14px 16px; background:#fff; transition:box-shadow .15s; }
        .ata-row-card:hover { box-shadow:0 2px 8px rgba(16,24,40,.06); }
        .ata-row-card.blocked { border-color:#fecaca; background:#fff9f9; }
        .ata-row-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
        .ata-row-name { font-size:14.5px; font-weight:600; color:#1f2937; }
        .ata-badge-nostock { background:#fee2e2; color:#991b1b; font-weight:600; font-size:10.5px; padding:2px 8px; border-radius:6px; margin-left:6px; vertical-align:middle; }
        .ata-split-row { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:4px; }
        .ata-avail-chips { display:flex; gap:8px; flex-wrap:wrap; }
        .ata-avail-chip { border:1px solid #eef0f3; border-radius:8px; padding:4px 10px; font-size:12px; color:#4b5563; background:#fafbfc; }
        .ata-avail-chip b { color:#1f2937; }
        .ata-row-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:10px; align-items:end; }
        .ata-field label { display:block; font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.02em; margin-bottom:4px; }

        .ata-route-row { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-bottom:10px; }
        .ata-route-field { display:flex; flex-direction:column; gap:3px; }
        .ata-route-field label { font-size:10px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.02em; }
        .ata-route-field select { height:34px; padding:2px 24px 2px 8px; font-size:12.5px; border-radius:7px; border:1px solid #dde1ea; min-width:108px; background-position:right 6px center; }
        .ata-route-arrow { color:#c7cbd4; font-size:16px; margin:0 2px; align-self:center; margin-top:14px; }
        .ata-view-btn { width:38px; height:38px; border-radius:9px; border:none; background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; box-shadow:0 2px 6px rgba(102,126,234,.3); }
        .ata-view-btn:hover { filter:brightness(1.06); }

        .ata-modal-tag-tp, .ata-modal-tag-ot { display:inline-block; width:4px; align-self:stretch; border-radius:3px; margin-right:8px; flex-shrink:0; }
        .ata-modal-tag-tp { background:var(--ata-tp-1); }
        .ata-modal-tag-ot { background:var(--ata-ot-1); }

        .ata-nav-tabs .nav-link { font-size:13px; font-weight:500; color:#6b7280; }
        .ata-nav-tabs .nav-link.active { color:#1f2937; font-weight:600; }
        .ata-bulk-row { display:flex; align-items:center; gap:8px; margin-top:10px; flex-wrap:wrap; }
        .ata-bulk-btn { display:inline-flex; align-items:center; gap:5px; border:1px solid #e5e7eb; background:#fafbfc; color:#374151; font-size:12px; font-weight:500; border-radius:8px; padding:5px 10px; cursor:pointer; transition:background .15s; }
        .ata-bulk-btn:hover { background:#eef0f3; }
        .ata-bulk-btn i { font-size:15px; }
        .ata-bulk-hint { font-size:11px; color:#9ca3af; }

        /* "View All Orders" modal toolbar — Select all / Omit all / Delete
           Selected on their own row, Show:-filter segmented control below,
           hint text moved out from between the buttons into its own small
           caption. Redesigned 2026-09-25 (was one crowded, wrapping row). */
        .ov-toolbar { border:1px solid #eef0f3; background:#fafbfc; border-radius:10px; padding:10px 12px; margin-top:10px; }
        .ov-toolbar-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .ov-toolbar-row + .ov-toolbar-row { margin-top:8px; }
        .ov-toolbar-group { display:flex; align-items:center; gap:6px; }
        .ov-toolbar-divider { width:1px; align-self:stretch; background:#e5e7eb; min-height:22px; }
        .ov-toolbar-label { font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.03em; }
        .ov-toolbar-hint { font-size:11px; color:#9ca3af; margin-top:8px; }
        .ov-delete-btn { color:#b91c1c; border-color:#fecaca; background:#fef2f2; }
        .ov-delete-btn:hover { background:#fee2e2; }

        .ov-segmented { display:inline-flex; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; }
        .ov-type-filter-btn { border:none; border-right:1px solid #e5e7eb; background:#fafbfc; color:#374151; font-size:12px; font-weight:500; padding:5px 12px; cursor:pointer; transition:background .15s; }
        .ov-type-filter-btn:last-child { border-right:none; }
        .ov-type-filter-btn:hover { background:#eef0f3; }
        .ov-type-filter-btn:focus { outline:none; }
        .ov-type-filter-btn.ov-type-all.active    { background:#374151; color:#fff; }
        .ov-type-filter-btn.ov-type-napkin.active { background:#3b82f6; color:#fff; }
        .ov-type-filter-btn.ov-type-diaper.active { background:#8b5cf6; color:#fff; }

        .bd-row.ata-line-off, .ov-product-row.ata-line-off { opacity:.5; }
        .bd-row, .ov-product-row { border-radius:8px; }
        .bd-row:hover, .ov-product-row:hover { background:#fafbfc; }
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

                    <?php
                    if (isset($_SESSION['sucMessage'])) {
                        $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8');
                        unset($_SESSION['sucMessage']);
                        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
                           . '<script>Swal.fire({icon:"success",title:"Success",text:"' . $flashMsg . '",confirmButtonText:"OK"});</script>';
                    }
                    if (isset($_SESSION['errorMessage'])) {
                        $flashErr = htmlspecialchars($_SESSION['errorMessage'], ENT_QUOTES, 'UTF-8');
                        unset($_SESSION['errorMessage']);
                        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
                           . '<script>Swal.fire({icon:"error",title:"Error",text:"' . $flashErr . '",confirmButtonText:"OK"});</script>';
                    }
                    ?>

                    <div class="ata-page-head">
                        <h1>Auto Transfer for Orders</h1>
                        <a class="ata-manage-link" href="internal_transfer_manage" title="Manage Internal Stock Transfer">
                            <i class="material-icons-outlined" style="font-size:19px;">menu</i>
                        </a>
                    </div>

                    <?php if (!empty($otDraftsOutsideLlp)): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="ata-warn-card">
                                <strong><i class="material-icons-outlined" style="font-size:17px;vertical-align:middle;">warning</i>
                                    <?php echo count($otDraftsOutsideLlp); ?> OT channel draft line(s) booked against a godown other than FEMI NAYAN LLP</strong>
                                <p class="text-muted small" style="margin:6px 0 8px;">
                                    Auto Transfer only replenishes LLP, so these drafts are NOT counted in Required Qty below.
                                    Check the godown selected when these were created — it may have been picked by mistake.
                                </p>
                                <div style="max-height:180px;overflow-y:auto;">
                                <table class="table table-sm table-bordered mb-0" style="font-size:12.5px;">
                                    <thead><tr><th>Order</th><th>Product</th><th>Qty</th><th>Booked Godown</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($otDraftsOutsideLlp as $d): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(($d['customer_name'] ?: 'Draft Order') . ' (' . $d['cat'] . ')', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($d['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo (int) $d['qty']; ?></td>
                                            <td><?php echo htmlspecialchars($d['godown_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="ata-card card">
                                <div class="card-body">
                                    <p class="ata-intro">
                                        Quantities required for every still-waiting <span class="ata-tag ata-tag-tp"><span class="ata-tag-dot"></span>TP Purchase Order</span>
                                        and drafted <span class="ata-tag ata-tag-ot"><span class="ata-tag-dot"></span>OT Channel Order</span> (LLP) — regardless of when it was
                                        raised — auto-capped to available Neksomo + Healthcare stock. Adjust
                                        any row before transferring — Neksomo &rarr; Healthcare &rarr; LLP,
                                        both legs move in one click.
                                    </p>

                                    <?php if (!empty($rows)):
                                        $totalProducts = count($rows);
                                        $blockedCount  = 0;
                                        $totalTp       = 0;
                                        $totalOt       = 0;
                                        foreach ($rows as $r) {
                                            if ((int) $r['capped'] <= 0) { $blockedCount++; }
                                            $totalTp += (int) $r['capped_tp'];
                                            $totalOt += (int) $r['capped_ot'];
                                        }
                                    ?>
                                    <div class="ata-summary">
                                        <div class="ata-stat ata-stat-hoverable" tabindex="0">
                                            <div class="num"><?php echo $waitingPoCount; ?></div>
                                            <div class="lbl">Total PO</div>
                                            <div class="ata-stat-tooltip">
                                                <div class="ata-stat-tooltip-summary">
                                                    <span class="ata-stat-chip ata-stat-chip-napkin">Napkin Qty: <b><?php echo (int) $poQtyByType['napkin']; ?></b> (<?php echo (int) $waitingPoCountByType['napkin']; ?> PO)</span>
                                                    <span class="ata-stat-chip ata-stat-chip-diaper">Lumi Diaper Qty: <b><?php echo (int) $poQtyByType['diaper']; ?></b> (<?php echo (int) $waitingPoCountByType['diaper']; ?> PO)</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ata-stat ata-stat-hoverable" tabindex="0">
                                            <div class="num"><?php echo $totalProducts; ?></div>
                                            <div class="lbl">Products</div>
                                            <div class="ata-stat-tooltip ata-stat-tooltip-below">
                                                <?php if (!empty($poProductBreakdown['napkin'])): ?>
                                                <div class="ata-stat-tooltip-group-title">Napkin (TP qty)</div>
                                                <table class="ata-stat-tooltip-table">
                                                    <?php foreach ($poProductBreakdown['napkin'] as $r): ?>
                                                    <tr><td><?php echo htmlspecialchars($r['product_name'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $r['required_tp']; ?></td></tr>
                                                    <?php endforeach; ?>
                                                </table>
                                                <?php endif; ?>
                                                <?php if (!empty($poProductBreakdown['diaper'])): ?>
                                                <div class="ata-stat-tooltip-group-title">Lumi Diaper (TP qty)</div>
                                                <table class="ata-stat-tooltip-table">
                                                    <?php foreach ($poProductBreakdown['diaper'] as $r): ?>
                                                    <tr><td><?php echo htmlspecialchars($r['product_name'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $r['required_tp']; ?></td></tr>
                                                    <?php endforeach; ?>
                                                </table>
                                                <?php endif; ?>
                                                <?php if (empty($poProductBreakdown['napkin']) && empty($poProductBreakdown['diaper'])): ?>
                                                <div class="text-muted" style="font-size:12px;">No waiting TP purchase orders.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="ata-stat tp">
                                            <div class="num"><?php echo $totalTp; ?></div>
                                            <div class="lbl">TP Qty to Transfer</div>
                                        </div>
                                        <div class="ata-stat ot">
                                            <div class="num"><?php echo $totalOt; ?></div>
                                            <div class="lbl">OT Qty to Transfer</div>
                                        </div>
                                        <?php if ($blockedCount > 0): ?>
                                        <div class="ata-stat warn">
                                            <div class="num"><?php echo $blockedCount; ?></div>
                                            <div class="lbl">Need Attention</div>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="ata-actionbar">
                                        <button type="button" class="ata-btn ata-btn-tp" onclick="openOrdersOverview()">
                                            <i class="material-icons" style="font-size:15px;">list_alt</i> View All Orders
                                        </button>
                                        <button type="button" class="ata-btn ata-btn-ot" onclick="openTransferHistory()">
                                            <i class="material-icons" style="font-size:15px;">history</i> Transfer History
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <div class="ata-actionbar">
                                        <button type="button" class="ata-btn ata-btn-ot" onclick="openTransferHistory()">
                                            <i class="material-icons" style="font-size:15px;">history</i> Transfer History
                                        </button>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (empty($rows)): ?>
                                        <div class="alert alert-info">Nothing to transfer today.</div>
                                    <?php else: ?>
                                        <form method="post" action="internal_transfer_auto_action.php" id="autoTransferForm" onsubmit="return confirmAutoTransferSubmit(event);">
                                            <div class="ata-common-warehouse-bar" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;border:1px solid #eef0f3;border-radius:12px;padding:12px 16px;margin-bottom:14px;background:#fafbfc;">
                                                <span style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.02em;">Set warehouse for all:</span>
                                                <select id="commonSourceWarehouse" class="form-control" style="width:auto;min-width:90px;height:34px;font-size:12.5px;padding:2px 22px 2px 8px;">
                                                    <option value="">Source</option>
                                                    <?php foreach ($sourceWarehouseOptions as $wh): ?>
                                                    <option value="<?php echo (int) $wh['id']; ?>"><?php echo htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select id="commonIntermediateWarehouse" class="form-control" style="width:auto;min-width:90px;height:34px;font-size:12.5px;padding:2px 22px 2px 8px;">
                                                    <option value="">Via</option>
                                                    <?php foreach ($intermediateWarehouseOptions as $wh): ?>
                                                    <option value="<?php echo (int) $wh['id']; ?>"><?php echo htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select id="commonDestWarehouse" class="form-control" style="width:auto;min-width:90px;height:34px;font-size:12.5px;padding:2px 22px 2px 8px;">
                                                    <option value="">Destination</option>
                                                    <?php foreach ($destWarehouseOptions as $wh): ?>
                                                    <option value="<?php echo (int) $wh['id']; ?>"><?php echo htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <span class="ata-bulk-hint" style="margin-left:4px;">Applies to every row below — each row stays individually editable.</span>
                                            </div>
                                            <div class="ata-rows">
                                                <?php foreach ($rows as $row): $blocked = (int) $row['capped'] <= 0; ?>
                                                <div class="ata-row-card auto-transfer-row<?php echo $blocked ? ' blocked' : ''; ?>" data-product-id="<?php echo (int) $row['product_id']; ?>" data-product-name="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" data-neksomo-avail="<?php echo (int) $row['neksomo_avail']; ?>" data-healthcare-avail="<?php echo (int) $row['healthcare_avail']; ?>">
                                                    <input type="hidden" name="product_id[]" value="<?php echo (int) $row['product_id']; ?>">
                                                    <div class="ata-row-top">
                                                        <div>
                                                            <span class="ata-row-name"><?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                            <?php if ($blocked): ?>
                                                                <span class="ata-badge-nostock" title="No stock available in Neksomo or Healthcare to fulfill this yet">No stock</span>
                                                            <?php endif; ?>
                                                            <div class="ata-split-row">
                                                                <span style="font-size:12px;color:#6b7280;">Required: <b id="req_<?php echo (int) $row['product_id']; ?>_num" style="color:#1f2937;"><?php echo (int) $row['required']; ?></b></span>
                                                                <span class="ata-tag ata-tag-tp" title="TP purchase order demand, capped by available stock"><span class="ata-tag-dot"></span>TP <b id="capped_tp_<?php echo (int) $row['product_id']; ?>"><?php echo (int) $row['capped_tp']; ?></b>/<?php echo (int) $row['required_tp']; ?></span>
                                                                <span class="ata-tag ata-tag-ot" title="OT channel draft demand, capped by available stock"><span class="ata-tag-dot"></span>OT <b id="capped_ot_<?php echo (int) $row['product_id']; ?>"><?php echo (int) $row['capped_ot']; ?></b>/<?php echo (int) $row['required_ot']; ?></span>
                                                                <span id="req_<?php echo (int) $row['product_id']; ?>" style="display:none;"><?php echo (int) $row['required']; ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="ata-avail-chips">
                                                            <div class="ata-avail-chip">Neksomo: <b id="avail_neksomo_<?php echo (int) $row['product_id']; ?>"><?php echo (int) $row['neksomo_avail']; ?></b></div>
                                                            <div class="ata-avail-chip">Healthcare: <b id="avail_healthcare_<?php echo (int) $row['product_id']; ?>"><?php echo (int) $row['healthcare_avail']; ?></b></div>
                                                        </div>
                                                    </div>
                                                    <div class="ata-route-row">
                                                        <div class="ata-route-field">
                                                            <label>Source</label>
                                                            <select class="form-control ata-source-warehouse" name="warehouse_source[]" data-product-id="<?php echo (int) $row['product_id']; ?>" data-required-tp="<?php echo (int) $row['required_tp']; ?>" data-required-ot="<?php echo (int) $row['required_ot']; ?>">
                                                                <option value="">—</option>
                                                                <?php foreach ($sourceWarehouseOptions as $wh): ?>
                                                                <option value="<?php echo (int) $wh['id']; ?>" <?php echo ((int) $wh['id'] === $defaultSourceWarehouseId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8'); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <span class="ata-route-arrow">&rarr;</span>
                                                        <div class="ata-route-field">
                                                            <label>Via</label>
                                                            <select class="form-control ata-intermediate-warehouse" name="warehouse_intermediate[]">
                                                                <option value="">—</option>
                                                                <?php foreach ($intermediateWarehouseOptions as $wh): ?>
                                                                <option value="<?php echo (int) $wh['id']; ?>" <?php echo ((int) $wh['id'] === $defaultIntermediateWarehouseId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8'); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <span class="ata-route-arrow">&rarr;</span>
                                                        <div class="ata-route-field">
                                                            <label>Destination</label>
                                                            <select class="form-control ata-dest-warehouse" name="warehouse_dest[]">
                                                                <option value="">—</option>
                                                                <?php foreach ($destWarehouseOptions as $wh): ?>
                                                                <option value="<?php echo (int) $wh['id']; ?>" <?php echo ((int) $wh['id'] === $defaultDestWarehouseId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8'); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="ata-row-grid">
                                                        <div class="ata-field">
                                                            <label>Qty to Transfer</label>
                                                            <input type="number" min="0" name="qty[]" id="qty_<?php echo (int) $row['product_id']; ?>"
                                                                   value="<?php echo (int) $row['capped']; ?>" class="form-control">
                                                        </div>
                                                        <div class="ata-field">
                                                            <label>Rate to Health Care (Rs.)</label>
                                                            <input type="number" min="0" step="0.01" name="rate1[]" placeholder="Rate(Rs.)" class="form-control"
                                                                   value="<?php echo $row['rate_healthcare'] !== null ? htmlspecialchars((string) $row['rate_healthcare'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                                        </div>
                                                        <div class="ata-field">
                                                            <label>Rate to LLP (Rs.)</label>
                                                            <input type="number" min="0" step="0.01" name="rate2[]" placeholder="Rate(Rs.)" class="form-control"
                                                                   value="<?php echo $row['rate_llp'] !== null ? htmlspecialchars((string) $row['rate_llp'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                                        </div>
                                                        <div class="ata-field" style="flex:0 0 auto;">
                                                            <label>&nbsp;</label>
                                                            <button type="button" class="ata-view-btn"
                                                                    title="View order breakdown"
                                                                    onclick="openBreakdown(<?php echo (int) $row['product_id']; ?>, <?php echo (int) $row['neksomo_avail']; ?>, <?php echo (int) $row['healthcare_avail']; ?>)">
                                                                <i class="material-icons" style="font-size:18px;">list_alt</i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <button type="submit" class="ata-btn ata-btn-submit" style="margin-top:16px;">
                                                <i class="material-icons" style="font-size:16px;">sync_alt</i> Transfer Now
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Breakdown modal: which orders make up a product's Required Qty, with
     the option to exclude one specific order from today's transfer (its
     own status/record is never touched — only what this popup sums and
     submits changes). -->
<div class="modal fade" id="breakdownModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                <h6 class="modal-title" style="font-weight:600;color:#1f2937;">
                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#2563eb;">list_alt</i>
                    Order Breakdown
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:14px 20px;">
                <p class="text-muted small">
                    Uncheck an order + Apply to leave it out of just this view (comes back if you
                    reopen this page). The order itself stays exactly as it is
                    (still waiting/draft), only its stock movement is postponed.
                </p>
                <ul class="nav nav-tabs ata-nav-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#bdTpPane" type="button"><span class="ata-tag-dot" style="background:var(--ata-tp-1);margin-right:6px;"></span>TP Purchase Orders</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#bdOtPane" type="button"><span class="ata-tag-dot" style="background:var(--ata-ot-1);margin-right:6px;"></span>OT Channel Orders</button></li>
                </ul>
                <div class="ata-bulk-row">
                    <button type="button" class="ata-bulk-btn" onclick="bdBulkSet(true)"><i class="material-icons-outlined">done_all</i>Select all</button>
                    <button type="button" class="ata-bulk-btn" onclick="bdBulkSet(false)"><i class="material-icons-outlined">remove_done</i>Omit all</button>
                    <span class="ata-bulk-hint">(applies to the currently open tab)</span>
                </div>
                <div class="tab-content" style="padding-top:10px;">
                    <div class="tab-pane fade show active" id="bdTpPane"><div id="bdTpList"></div></div>
                    <div class="tab-pane fade" id="bdOtPane"><div id="bdOtList"></div></div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                <button type="button" class="ata-btn ata-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="ata-btn ata-btn-tp" onclick="applyBreakdown()">Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- "View All Orders" — every order contributing to today's transfer, one
     row per PO/OT-invoice with ALL of its own products listed underneath
     (not scoped to a single product, unlike the modal above). Uncheck an
     order/product + Apply recomputes each affected product row's Required
     Qty / Qty to Transfer for this view only (same as the per-product
     breakdown modal's own checkbox+Apply) — nothing is persisted, so it
     resets on reload. -->
<div class="modal fade" id="ordersOverviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                <h6 class="modal-title" style="font-weight:600;color:#1f2937;">
                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#2563eb;">list_alt</i>
                    View All Orders
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:14px 20px;">
                <p class="text-muted small">
                    Every order behind today's transfer, with all of its own products. Uncheck one
                    product (e.g. if just that item has a stock problem) or the whole order's
                    checkbox, then Apply — this only recomputes the current view (nothing is saved),
                    so it resets if you close and reopen this page.
                </p>
                <ul class="nav nav-tabs ata-nav-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ovTpPane" type="button"><span class="ata-tag-dot" style="background:var(--ata-tp-1);margin-right:6px;"></span>TP Purchase Orders</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ovOtPane" type="button"><span class="ata-tag-dot" style="background:var(--ata-ot-1);margin-right:6px;"></span>OT Channel Orders</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ovExcludedPane" type="button" onclick="loadExcludedAndDeletedToday()">Already Transferred Today</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ovDeletedPane" type="button" onclick="loadExcludedAndDeletedToday()">Deleted</button></li>
                </ul>
                <div class="ov-toolbar">
                    <div class="ov-toolbar-row">
                        <button type="button" class="ata-bulk-btn" onclick="ovBulkSet(true)"><i class="material-icons-outlined">done_all</i>Select all</button>
                        <button type="button" class="ata-bulk-btn" onclick="ovBulkSet(false)"><i class="material-icons-outlined">remove_done</i>Omit all</button>
                        <span class="ov-toolbar-label">Show</span>
                        <div class="ov-segmented">
                            <button type="button" class="ov-type-filter-btn ov-type-all active" data-type="all" onclick="ovSetTypeFilter('all', this)">All (<?php echo (int) $waitingPoCount; ?>)</button>
                            <button type="button" class="ov-type-filter-btn ov-type-napkin" data-type="napkin" onclick="ovSetTypeFilter('napkin', this)">Napkin (<?php echo (int) $waitingPoCountByType['napkin']; ?>)</button>
                            <button type="button" class="ov-type-filter-btn ov-type-diaper" data-type="diaper" onclick="ovSetTypeFilter('diaper', this)">Lumi Diaper (<?php echo (int) $waitingPoCountByType['diaper']; ?>)</button>
                        </div>
                        <button type="button" class="ata-bulk-btn ov-delete-btn" onclick="ovDeleteSelected()"><i class="material-icons-outlined">delete_outline</i>Delete Selected</button>
                    </div>
                    <div class="ov-toolbar-hint">Select all / Omit all / Delete Selected apply to the currently open tab.</div>
                </div>
                <div class="tab-content" style="padding-top:10px;">
                    <div class="tab-pane fade show active" id="ovTpPane"><div id="ovTpList"></div></div>
                    <div class="tab-pane fade" id="ovOtPane"><div id="ovOtList"></div></div>
                    <div class="tab-pane fade" id="ovExcludedPane">
                        <p class="text-muted small">
                            Orders whose stock already moved earlier today via Transfer Now —
                            grouped by order, for reference only.
                        </p>
                        <div id="ovExcludedList"></div>
                    </div>
                    <div class="tab-pane fade" id="ovDeletedPane">
                        <p class="text-muted small">
                            Orders soft-deleted from today's Auto Transfer via "Delete Selected" —
                            stock was never touched for these, and they can be re-added below.
                        </p>
                        <div id="ovDeletedList"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                <button type="button" class="ata-btn ata-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="ata-btn ata-btn-tp" onclick="applyOrdersOverview()">Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- "Transfer History" — per-product before-stock (Neksomo/Healthcare/LLP)
     and qty transferred, for every auto-transfer run on a chosen date.
     Read entirely from stock_ledger's own qty_before/created_at — nothing
     new is tracked, this just surfaces what StockService already logged. -->
<div class="modal fade" id="transferHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                <h6 class="modal-title" style="font-weight:600;color:#1f2937;">
                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#6b7280;">history</i>
                    Auto Transfer History
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:14px 20px;">
                <div style="display:flex;align-items:end;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
                    <div>
                        <label class="form-label" style="font-size:12.5px;font-weight:600;color:#6b7280;">Date</label>
                        <input type="date" id="thDateInput" class="form-control form-control-sm" style="width:170px;">
                    </div>
                    <button type="button" class="ata-btn ata-btn-ot" onclick="loadTransferHistory()">Show</button>
                </div>
                <div id="thResult"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                <button type="button" class="ata-btn ata-btn-ghost" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
    var currentBreakdownPid    = null;
    var currentNeksomoAvail    = 0;
    var currentHealthcareAvail = 0;

    // Shared checked/qty state for one (order, product) line, used by BOTH
    // the Order Breakdown modal and View All Orders — selecting/unchecking
    // a line in either one is reflected in the other, and by
    // effectiveRequiredForProduct() below, since re-doing the same
    // selection by hand in more than one place isn't practical. Keyed by
    // "tp:<po_id>:<product_id>" / "ot:<tempid>:<product_id>", the same
    // source_id shape the breakdown/overview endpoints emit. Declared here
    // (not where it's rendered further down) so every reader — including
    // refreshRowAvailability(), wired at page load — sees it defined.
    var lineState = {};

    // Orders re-added to TP/OT via the "Already Transferred Today" tab's
    // "Re-add" button this session — keyed by "tp:<order_key>" /
    // "ot:<order_key>", so ovRenderOrderList() can flag them. Resets on
    // page reload, same as lineState.
    var reAddedGroupKeys = {};

    function escBd(str) { return $('<div>').text(str == null ? '' : str).html(); }

    // escBd() only escapes <, >, & (via .html()) — fine for text content,
    // but a JSON-stringified array of STRINGS (e.g. '["tp:800:17"]')
    // contains literal " characters, which escBd() leaves untouched. Used
    // directly inside a double-quoted HTML attribute, those embedded
    // quotes terminate the attribute early, truncating the value the
    // browser reads back — JSON.parse() then fails with "Unexpected end
    // of JSON input". This also escapes ", so it's the one to use for any
    // attribute value that isn't guaranteed quote-free (unlike, say, a
    // JSON array of plain numbers, which never contains one). Confirmed
    // 2026-09-24 via reAddToTpPurchaseOrders()'s data-source-ids attribute.
    function escAttr(str) {
        return (str == null ? '' : String(str))
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // Mirrors AutoTransferDemand.php's cap_auto_transfer_qty_by_source():
    // TP demand is capped/prioritized first, OT gets whatever's left.
    function capQtyBySource(tpRequired, otRequired, available) {
        available = Math.max(0, available);
        var tpCapped = Math.max(0, Math.min(tpRequired, available));
        var otCapped = Math.max(0, Math.min(otRequired, available - tpCapped));
        return { tp: tpCapped, ot: otCapped };
    }

    // Recomputes one row's Neksomo/Healthcare availability + capped
    // qty-to-transfer whenever its Source or Intermediate Godown picker
    // changes — Neksomo's figure scopes to the Source (Neksomo) godown,
    // Healthcare's figure scopes to the Intermediate (Healthcare) godown,
    // since each leg's endpoint is now independently selectable (see
    // docs/superpowers/specs/2026-09-21-warehouse-aware-auto-transfer-
    // design.md).
    // Required-tp/ot for one product, honoring any exclusions already made
    // in the Order Breakdown / View All Orders modals (lineState) instead
    // of the row's original server-rendered totals. lineState only has
    // entries for source_ids that were actually rendered into one of those
    // modals, so a product the user never opened either modal for falls
    // back to its raw data-required-tp/ot untouched. Without this,
    // refreshRowAvailability() silently wiped out an exclusion the moment
    // the Source warehouse picker changed afterward (qty_<pid> reset back
    // to the full unfiltered requirement).
    function effectiveRequiredForProduct(productId, rawRequiredTp, rawRequiredOt) {
        var suffix = ':' + productId;
        var touched = false;
        var tp = 0, ot = 0;
        Object.keys(lineState).forEach(function (sourceId) {
            if (sourceId.slice(-suffix.length) !== suffix) return;
            touched = true;
            var line = lineState[sourceId];
            if (!line.checked) return;
            if (sourceId.indexOf('tp:') === 0) { tp += line.qty; } else { ot += line.qty; }
        });
        return touched ? { tp: tp, ot: ot } : { tp: rawRequiredTp, ot: rawRequiredOt };
    }

    function refreshRowAvailability(rowEl) {
        var sourceSelect = rowEl.querySelector('.ata-source-warehouse');
        var intermediateSelect = rowEl.querySelector('.ata-intermediate-warehouse');
        var productId = sourceSelect.getAttribute('data-product-id');
        var rawRequiredTp = parseInt(sourceSelect.getAttribute('data-required-tp'), 10) || 0;
        var rawRequiredOt = parseInt(sourceSelect.getAttribute('data-required-ot'), 10) || 0;
        var effectiveRequired = effectiveRequiredForProduct(productId, rawRequiredTp, rawRequiredOt);
        var requiredTp = effectiveRequired.tp;
        var requiredOt = effectiveRequired.ot;
        var sourceWarehouseId = sourceSelect.value;
        var intermediateWarehouseId = intermediateSelect.value;

        var url = 'get-auto-transfer-row-availability.php?product_id=' + encodeURIComponent(productId)
            + (sourceWarehouseId ? '&source_warehouse_id=' + encodeURIComponent(sourceWarehouseId) : '')
            + (intermediateWarehouseId ? '&intermediate_warehouse_id=' + encodeURIComponent(intermediateWarehouseId) : '');

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) return;
                var neksomoAvail = data.neksomo_avail;
                var healthcareAvail = data.healthcare_avail;

                rowEl.setAttribute('data-neksomo-avail', neksomoAvail);
                rowEl.setAttribute('data-healthcare-avail', healthcareAvail);

                var neksomoChip = document.getElementById('avail_neksomo_' + productId);
                var healthcareChip = document.getElementById('avail_healthcare_' + productId);
                if (neksomoChip) neksomoChip.textContent = neksomoAvail;
                if (healthcareChip) healthcareChip.textContent = healthcareAvail;

                // Capped by Neksomo (leg 1 source) alone — see the matching
                // PHP-side comment above; Healthcare's own balance no longer
                // inflates how much leg 1 can newly move.
                var available = neksomoAvail;
                var split = capQtyBySource(requiredTp, requiredOt, available);
                var cappedTotal = split.tp + split.ot;

                var cappedTpEl = document.getElementById('capped_tp_' + productId);
                var cappedOtEl = document.getElementById('capped_ot_' + productId);
                if (cappedTpEl) cappedTpEl.textContent = split.tp;
                if (cappedOtEl) cappedOtEl.textContent = split.ot;

                var qtyInput = document.getElementById('qty_' + productId);
                if (qtyInput) qtyInput.value = cappedTotal;

                var badge = rowEl.querySelector('.ata-badge-nostock');
                if (cappedTotal <= 0) {
                    rowEl.classList.add('blocked');
                } else {
                    rowEl.classList.remove('blocked');
                }
            })
            .catch(function () { /* leave current values on fetch failure */ });
    }

    // Source, Via, and Destination are each independently selectable per
    // row — Neksomo, Healthcare, and LLP can each hold stock in more than
    // one physical warehouse now, so a row's three legs may legitimately
    // land in different warehouses.
    document.querySelectorAll('.ata-source-warehouse').forEach(function (sel) {
        sel.addEventListener('change', function () {
            refreshRowAvailability(sel.closest('.ata-row-card'));
        });
    });
    document.querySelectorAll('.ata-intermediate-warehouse').forEach(function (sel) {
        sel.addEventListener('change', function () {
            refreshRowAvailability(sel.closest('.ata-row-card'));
        });
    });

    // "Set warehouse for all" bar — three independent bulk pickers, one per
    // leg. Each only bulk-fills its own column across every row; legs no
    // longer mirror each other.
    function wireCommonWarehouseSelect(commonId, rowSelector) {
        var commonSelect = document.getElementById(commonId);
        if (!commonSelect) return;
        commonSelect.addEventListener('change', function () {
            var value = commonSelect.value;
            document.querySelectorAll(rowSelector).forEach(function (rowSelect) {
                // Only set it if the option exists in this row's list (its
                // options may differ from the common list in rare cases);
                // otherwise leave that row's own selection untouched.
                var hasOption = Array.from(rowSelect.options).some(function (o) { return o.value === value; });
                if (!hasOption) return;
                rowSelect.value = value;
                refreshRowAvailability(rowSelect.closest('.ata-row-card'));
            });
        });
    }
    wireCommonWarehouseSelect('commonSourceWarehouse', '.ata-source-warehouse');
    wireCommonWarehouseSelect('commonIntermediateWarehouse', '.ata-intermediate-warehouse');
    wireCommonWarehouseSelect('commonDestWarehouse', '.ata-dest-warehouse');

    // Warns before submitting if any product has zero stock at the first
    // leg's source (Neksomo) — the backend already silently caps/skips a
    // product with insufficient Neksomo stock (see
    // internal_transfer_auto_action.php's $writeLeg(), which computes
    // actualQty = min(requested, available) and continues past a product
    // whose first leg moves 0), so without this warning that skip happens
    // invisibly. This only flags products with NO stock at all
    // (neksomo_avail <= 0) — a partial shortfall still transfers what's
    // available and is left to the existing "Capped: ..." success message.
    function confirmAutoTransferSubmit(e) {
        var zeroStockNames = [];
        document.querySelectorAll('.auto-transfer-row').forEach(function (row) {
            var avail = parseInt(row.getAttribute('data-neksomo-avail'), 10) || 0;
            if (avail <= 0) zeroStockNames.push(row.getAttribute('data-product-name'));
        });

        if (zeroStockNames.length > 0) {
            var msg = 'These product(s) have no stock in Neksomo and will be skipped:\n\n'
                + zeroStockNames.map(function (n) { return '- ' + n; }).join('\n')
                + '\n\nProceed with transferring the remaining products?';
            if (!confirm(msg)) {
                e.preventDefault();
                return false;
            }
            return true;
        }

        return confirm('Transfer these quantities now?');
    }

    function renderBreakdownTab(containerId, items, emptyMsg) {
        var el = document.getElementById(containerId);
        if (!items || !items.length) {
            el.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">' + emptyMsg + '</div>';
            return;
        }
        var html = '';
        items.forEach(function (it) {
            var remembered = lineState[it.source_id];
            var isChecked = remembered ? remembered.checked : true;
            var qtyVal = remembered ? remembered.qty : it.qty;
            html += '<div class="bd-row' + (isChecked ? '' : ' ata-line-off') + '" data-source-id="' + it.source_id + '" style="display:flex;justify-content:space-between;align-items:center;gap:10px;border-bottom:1px solid #f1f5f9;padding:8px 4px;flex-wrap:wrap;">' +
                '<label style="display:flex;align-items:center;flex:1;cursor:pointer;margin:0;min-width:160px;">' +
                    '<input type="checkbox" class="bd-check" data-source-id="' + it.source_id + '"' + (isChecked ? ' checked' : '') + ' style="margin-right:8px;flex-shrink:0;">' +
                    '<span style="overflow-wrap:anywhere;">' + escBd(it.label) + '</span>' +
                '</label>' +
                '<input type="number" min="0" max="' + it.qty + '" class="form-control form-control-sm bd-qty-input" data-source-id="' + it.source_id + '" ' +
                    'value="' + qtyVal + '" style="width:80px;flex-shrink:0;" title="Max ' + it.qty + ' — this order\'s own qty">' +
            '</div>';
        });
        el.innerHTML = html;
        // Persist every line's state as soon as it changes, not just on
        // Apply — so reopening the modal without ever clicking Apply
        // still shows what the user last set (here or in View All Orders).
        el.querySelectorAll('.bd-row').forEach(function (rowEl) {
            var checkbox = rowEl.querySelector('.bd-check');
            var qtyInput = rowEl.querySelector('.bd-qty-input');
            checkbox.addEventListener('change', function () { rowEl.classList.toggle('ata-line-off', !checkbox.checked); bdSaveLineState(rowEl); });
            qtyInput.addEventListener('input', function () { bdSaveLineState(rowEl); });
        });
    }

    function bdSaveLineState(rowEl) {
        var sourceId = rowEl.getAttribute('data-source-id');
        var checkbox = rowEl.querySelector('.bd-check');
        var qtyInput = rowEl.querySelector('.bd-qty-input');
        var qty = parseInt(qtyInput.value, 10);
        if (isNaN(qty) || qty < 0) qty = 0;
        lineState[sourceId] = { checked: checkbox.checked, qty: qty };
    }

    // Select all / Omit all for the currently-visible Order Breakdown tab
    // only (mirrors the existing single-line checkbox behavior — does not
    // touch the other tab, and does not Apply by itself).
    function bdBulkSet(checked) {
        var activePane = document.querySelector('#breakdownModal .tab-pane.active');
        if (!activePane) return;
        activePane.querySelectorAll('.bd-row').forEach(function (rowEl) {
            var checkbox = rowEl.querySelector('.bd-check');
            checkbox.checked = checked;
            rowEl.classList.toggle('ata-line-off', !checked);
            bdSaveLineState(rowEl);
        });
    }

    // Updates every on-row Required Qty display for one product: the
    // hidden total span (#req_<pid>, still read by the recompute logic
    // above/below), the visible bold total (#req_<pid>_num), and the
    // TP/OT tag pair's own "x/y" counts — all three must move together
    // whenever a breakdown/overview Apply changes what's checked.
    function updateRequiredDisplay(pid, tpCapped, otCapped) {
        var total = tpCapped + otCapped;
        var hidden = document.getElementById('req_' + pid);
        if (hidden) hidden.textContent = total;
        var num = document.getElementById('req_' + pid + '_num');
        if (num) num.textContent = total;
        var rowEl = document.querySelector('.auto-transfer-row[data-product-id="' + pid + '"]');
        if (rowEl) {
            var tpTag = rowEl.querySelector('.ata-tag-tp');
            var otTag = rowEl.querySelector('.ata-tag-ot');
            if (tpTag) {
                var tpMax = (tpTag.textContent.split('/')[1] || '').trim();
                tpTag.innerHTML = '<span class="ata-tag-dot"></span>TP ' + tpCapped + '/' + tpMax;
            }
            if (otTag) {
                var otMax = (otTag.textContent.split('/')[1] || '').trim();
                otTag.innerHTML = '<span class="ata-tag-dot"></span>OT ' + otCapped + '/' + otMax;
            }
        }
    }

    // Recomputes the currently-open product's Required Qty + Qty to
    // Transfer from whatever's still checked across all three tabs —
    // shared by the plain "Apply" button.
    function recomputeCurrentRowFromCheckboxes() {
        if (currentBreakdownPid === null) return;
        var tpTotal = 0, otTotal = 0;
        document.querySelectorAll('.bd-check:checked').forEach(function (chk) {
            var sourceId = chk.getAttribute('data-source-id');
            var qtyInput = document.querySelector('.bd-qty-input[data-source-id="' + sourceId.replace(/"/g, '') + '"]');
            var maxQty = qtyInput ? parseInt(qtyInput.getAttribute('max'), 10) || 0 : 0;
            var val = qtyInput ? parseInt(qtyInput.value, 10) : 0;
            if (isNaN(val) || val < 0) val = 0;
            if (val > maxQty) val = maxQty; // never more than that order actually needs
            if (qtyInput) qtyInput.value = val;
            // source_id is "tp:..." or "ot:..." — classify by that prefix so
            // this stays consistent with the server-side TP-first cap.
            if (sourceId.indexOf('tp:') === 0) { tpTotal += val; } else { otTotal += val; }
        });
        var pid = currentBreakdownPid;

        // Capped by Neksomo (leg 1 source) alone — see refreshRowAvailability().
        var available = currentNeksomoAvail;
        if (available < 0) available = 0;
        var tpCapped = Math.max(0, Math.min(tpTotal, available));
        var otCapped = Math.max(0, Math.min(otTotal, available - tpCapped));
        updateRequiredDisplay(pid, tpCapped, otCapped);
        document.getElementById('qty_' + pid).value = tpCapped + otCapped;
    }

    function openBreakdown(pid, neksomoAvail, healthcareAvail) {
        currentBreakdownPid    = pid;
        currentNeksomoAvail    = neksomoAvail;
        currentHealthcareAvail = healthcareAvail;

        var loading = '<div class="text-muted small" style="padding:10px 4px;">Loading&hellip;</div>';
        document.getElementById('bdTpList').innerHTML = loading;
        document.getElementById('bdOtList').innerHTML = loading;

        $.getJSON('get-auto-transfer-breakdown.php', { product_id: pid }, function (data) {
            renderBreakdownTab('bdTpList', data.tp, 'No Territory Partner orders for this product today.');
            renderBreakdownTab('bdOtList', data.ot, 'No OT channel draft orders for this product today.');
        }).fail(function () {
            var failMsg = '<div class="text-danger small" style="padding:10px 4px;">Could not load breakdown.</div>';
            document.getElementById('bdTpList').innerHTML = failMsg;
            document.getElementById('bdOtList').innerHTML = failMsg;
        });

        var modal = new bootstrap.Modal(document.getElementById('breakdownModal'));
        modal.show();
    }

    function applyBreakdown() {
        recomputeCurrentRowFromCheckboxes();
        var modalEl = document.getElementById('breakdownModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }

    // ── "View All Orders" — order-level overview, all products per order.
    // Uncheck an order/product + Apply recomputes each affected product
    // row in the main table for this view only — nothing is persisted. ──
    // Shares the same `lineState` map the Order Breakdown modal uses (see
    // above) — checking/unchecking a line here shows up there too, and
    // vice versa, since re-doing the same selection by hand in both
    // places isn't practical. order_key + product_id is normalized into
    // the same "tp:<order_key>:<product_id>" / "ot:<order_key>:<product_id>"
    // shape breakdown's source_id already uses, derived from which list
    // (TP or OT) this render call is for.
    function ovRenderOrderList(containerId, orders, emptyMsg) {
        var el = document.getElementById(containerId);
        orders = (orders || []).filter(function (order) {
            return ovTypeFilter === 'all' || order.order_type === ovTypeFilter;
        });
        if (!orders.length) {
            el.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">' + emptyMsg + '</div>';
            return;
        }
        var sourceType = containerId === 'ovTpList' ? 'tp' : 'ot';
        var html = '';
        orders.forEach(function (order) {
            var productsHtml = order.products.map(function (p) {
                var sourceId = sourceType + ':' + order.order_key + ':' + p.product_id;
                var remembered = lineState[sourceId];
                var isChecked = remembered ? remembered.checked : true;
                var qtyVal = remembered ? remembered.qty : p.qty;
                return '<div class="ov-product-row' + (isChecked ? '' : ' ata-line-off') + '" data-source-id="' + sourceId + '" data-product-id="' + p.product_id + '" style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:5px 0;font-size:12.5px;color:#4b5563;">' +
                    '<label style="display:flex;align-items:center;flex:1;cursor:pointer;margin:0;min-width:0;">' +
                        '<input type="checkbox" class="ov-product-check"' + (isChecked ? ' checked' : '') + ' style="margin-right:8px;flex-shrink:0;">' +
                        '<span style="overflow-wrap:anywhere;">' + escBd(p.product_name) + '</span>' +
                    '</label>' +
                    '<input type="number" min="0" max="' + p.qty + '" class="form-control form-control-sm ov-product-qty" ' +
                        'value="' + qtyVal + '" style="width:75px;flex-shrink:0;" title="Max ' + p.qty + '">' +
                '</div>';
            }).join('');
            // Whole-order checkbox reflects the current state too — checked
            // only when every one of its own product lines is checked.
            var allChecked = order.products.every(function (p) {
                var remembered = lineState[sourceType + ':' + order.order_key + ':' + p.product_id];
                return remembered ? remembered.checked : true;
            });
            // Flags an order that just came back from "Already Transferred
            // Today" via the Re-add button, so it's obvious in this list
            // which one to re-check before running Transfer Now again.
            var wasReAdded = !!reAddedGroupKeys[sourceType + ':' + order.order_key];
            html += '<div class="ov-order" style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:10px;">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">' +
                    '<label style="display:flex;align-items:center;flex:1;cursor:pointer;margin:0;min-width:160px;font-weight:600;">' +
                        '<input type="checkbox" class="ov-check"' + (allChecked ? ' checked' : '') + ' style="margin-right:8px;flex-shrink:0;" title="Select/deselect every product in this order">' +
                        '<span style="overflow-wrap:anywhere;">' + escBd(order.label) + '</span>' +
                        (wasReAdded ? ' <span class="badge" style="background:#fef3c7;color:#92400e;margin-left:6px;">Re-add</span>' : '') +
                    '</label>' +
                '</div>' +
                '<div style="margin-top:6px;padding-left:26px;border-top:1px solid #f1f5f9;padding-top:6px;">' + productsHtml + '</div>' +
            '</div>';
        });
        el.innerHTML = html;
        // Order checkbox is a select-all/deselect-all toggle for its own
        // product checkboxes — the real, individually-actionable state
        // lives on each .ov-product-check, not on this one.
        el.querySelectorAll('.ov-check').forEach(function (orderCheck) {
            orderCheck.addEventListener('change', function () {
                var rowEl = orderCheck.closest('.ov-order');
                rowEl.querySelectorAll('.ov-product-check').forEach(function (pc) {
                    pc.checked = orderCheck.checked;
                    var productRow = pc.closest('.ov-product-row');
                    productRow.classList.toggle('ata-line-off', !pc.checked);
                    ovSaveLineState(productRow);
                });
            });
        });
        // Persist every product line's state as soon as it changes, not
        // just on Apply — so reopening either modal without ever clicking
        // Apply still shows what the user last set, in the other one too.
        el.querySelectorAll('.ov-product-row').forEach(function (rowEl) {
            var checkbox = rowEl.querySelector('.ov-product-check');
            var qtyInput = rowEl.querySelector('.ov-product-qty');
            checkbox.addEventListener('change', function () { rowEl.classList.toggle('ata-line-off', !checkbox.checked); ovSaveLineState(rowEl); });
            qtyInput.addEventListener('input', function () { ovSaveLineState(rowEl); });
        });
    }

    // Select all / Omit all for the currently-visible View All Orders tab
    // only (TP or OT pane) — updates every product row and each order's
    // own select-all checkbox to match, same as ticking every box by hand.
    function ovBulkSet(checked) {
        var activePane = document.querySelector('#ordersOverviewModal .tab-pane.active');
        if (!activePane) return;
        activePane.querySelectorAll('.ov-product-row').forEach(function (rowEl) {
            var checkbox = rowEl.querySelector('.ov-product-check');
            checkbox.checked = checked;
            rowEl.classList.toggle('ata-line-off', !checked);
            ovSaveLineState(rowEl);
        });
        activePane.querySelectorAll('.ov-check').forEach(function (orderCheck) {
            orderCheck.checked = checked;
        });
    }

    function ovSaveLineState(rowEl) {
        var sourceId = rowEl.getAttribute('data-source-id');
        var checkbox = rowEl.querySelector('.ov-product-check');
        var qtyInput = rowEl.querySelector('.ov-product-qty');
        var qty = parseInt(qtyInput.value, 10);
        if (isNaN(qty) || qty < 0) qty = 0;
        lineState[sourceId] = { checked: checkbox.checked, qty: qty };
    }

    // "Delete Selected" — TP Purchase Orders / OT Channel Orders tabs only
    // (a no-op on the read-only "Already Transferred Today" tab, since it
    // has no .ov-product-row checkboxes at all). Marks every currently
    // CHECKED product line in the active tab as reason='excluded' via
    // delete-auto-transfer-order.php — same table as a completed transfer's
    // 'transferred' rows, so the line disappears from today's Required Qty
    // and reappears in "Already Transferred Today" tagged "Deleted"
    // (undoable with that tab's existing "Re-add" button). Never touches
    // stock, unlike Transfer Now / Undo.
    function ovDeleteSelected() {
        var activePane = document.querySelector('#ordersOverviewModal .tab-pane.active');
        if (!activePane) return;
        var checkedRows = activePane.querySelectorAll('.ov-product-row');
        var toDelete = [];
        checkedRows.forEach(function (rowEl) {
            var checkbox = rowEl.querySelector('.ov-product-check');
            if (checkbox && checkbox.checked) toDelete.push(rowEl.getAttribute('data-source-id'));
        });
        if (!toDelete.length) {
            alert('Select at least one product line to delete.');
            return;
        }
        if (!confirm('Delete ' + toDelete.length + ' selected order line(s) from today\'s Auto Transfer?\n\nStock already moved is left untouched — this only removes it from today\'s Required Qty. It will show as "Deleted" in Already Transferred Today, and can be re-added from there.')) return;

        var calls = toDelete.map(function (sourceId) {
            return $.post('delete-auto-transfer-order.php', { source_id: sourceId }, null, 'json')
                .then(function (res) { return res; }, function () { return { success: false }; });
        });

        $.when.apply($, calls).done(function () {
            var results = Array.prototype.slice.call(arguments);
            var anyFailed = results.some(function (res) { return !res || !res.success; });
            if (anyFailed) {
                alert('Could not delete some of the selected line(s). Please try again.');
            }
            window.location.reload();
        }).fail(function () {
            alert('Could not delete the selected line(s). Please try again.');
        });
    }

    // Recomputes every affected product row in the main table from
    // whatever's currently checked across both View All Orders tabs — a
    // product can be checked/unchecked from more than one order, so this
    // sums across every .ov-product-row for the same product_id rather
    // than acting on a single row at a time. View-only: nothing is sent
    // to the server, so this resets on reload just like the per-product
    // breakdown modal's own checkbox+Apply.
    function applyOrdersOverview() {
        // Computed from the FULL order set (ovLastData), not from whatever
        // .ov-product-row elements happen to be rendered — but when a
        // specific Napkin/Lumi Diaper filter is active (not "All"), every
        // order of the OTHER type is treated as fully deselected for this
        // Apply, regardless of its lineState. This matches how staff
        // actually run Auto Transfer: one product type at a time (Napkin
        // today, Lumi Diaper separately) — switching the filter and
        // clicking Apply is meant to zero the type not currently being
        // worked on, not silently leave its old total standing untouched.
        // Confirmed 2026-09-25 (reverses an earlier same-day attempt that
        // instead preserved the hidden type's total — that was the wrong
        // direction). With "All" selected, both types are always live and
        // behave exactly as before (each line's own checked/qty state).
        var tpTotalsByProduct = {};
        var otTotalsByProduct = {};
        var seenProductIds = {};

        function accumulate(sourceType, orders) {
            (orders || []).forEach(function (order) {
                var otherTypeHidden = ovTypeFilter !== 'all' && order.order_type !== ovTypeFilter;
                order.products.forEach(function (p) {
                    var pid = String(p.product_id);
                    seenProductIds[pid] = true;
                    if (otherTypeHidden) return; // counted as seen (so it's zeroed, not left untouched), but contributes nothing
                    var sourceId = sourceType + ':' + order.order_key + ':' + p.product_id;
                    var remembered = lineState[sourceId];
                    var checked = remembered ? remembered.checked : true;
                    if (!checked) return;
                    var qty = remembered ? remembered.qty : p.qty;
                    if (isNaN(qty) || qty < 0) qty = 0;
                    if (qty > p.qty) qty = p.qty; // never more than this order's own qty
                    if (sourceType === 'tp') {
                        tpTotalsByProduct[pid] = (tpTotalsByProduct[pid] || 0) + qty;
                    } else {
                        otTotalsByProduct[pid] = (otTotalsByProduct[pid] || 0) + qty;
                    }
                });
            });
        }
        accumulate('tp', ovLastData.tp);
        accumulate('ot', ovLastData.ot);

        document.querySelectorAll('.auto-transfer-row').forEach(function (row) {
            var pid = row.getAttribute('data-product-id');
            if (!(pid in seenProductIds)) return; // this product has no overview rows at all — leave untouched
            var tpTotal = tpTotalsByProduct[pid] || 0;
            var otTotal = otTotalsByProduct[pid] || 0;
            var neksomoAvail = parseInt(row.getAttribute('data-neksomo-avail'), 10) || 0;
            // Capped by Neksomo (leg 1 source) alone — see refreshRowAvailability().
            var available = neksomoAvail;
            if (available < 0) available = 0;
            var tpCapped = Math.max(0, Math.min(tpTotal, available));
            var otCapped = Math.max(0, Math.min(otTotal, available - tpCapped));
            updateRequiredDisplay(pid, tpCapped, otCapped);
            var qtyEl = document.getElementById('qty_' + pid);
            if (qtyEl) qtyEl.value = tpCapped + otCapped;
        });

        var modalEl = document.getElementById('ordersOverviewModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }

    // Last-fetched TP/OT overview data, cached so the Napkin/Lumi Diaper
    // filter (ovSetTypeFilter) can re-render instantly client-side instead
    // of refetching from the server every time it's toggled.
    var ovLastData = { tp: [], ot: [] };
    // 'all' | 'napkin' | 'diaper' — which order_type ovRenderOrderList()
    // keeps. Resets to 'all' on every fresh openOrdersOverview() open, same
    // as lineState/reAddedGroupKeys resetting on page reload.
    var ovTypeFilter = 'all';

    // Shared by the modal's initial open AND by "Re-add to TP Purchase
    // Orders" (which must refresh the TP/OT panes in place, without
    // reopening the modal that's already showing).
    function loadOrdersOverviewData() {
        var loading = '<div class="text-muted small" style="padding:10px 4px;">Loading&hellip;</div>';
        document.getElementById('ovTpList').innerHTML = loading;
        document.getElementById('ovOtList').innerHTML = loading;

        return $.getJSON('get-auto-transfer-orders-overview.php', {}, function (data) {
            ovLastData = data;
            ovRenderOrderList('ovTpList', data.tp, 'No Territory Partner orders contributing today.');
            ovRenderOrderList('ovOtList', data.ot, 'No OT channel draft orders contributing today.');
        }).fail(function () {
            var failMsg = '<div class="text-danger small" style="padding:10px 4px;">Could not load orders.</div>';
            document.getElementById('ovTpList').innerHTML = failMsg;
            document.getElementById('ovOtList').innerHTML = failMsg;
        });
    }

    // Napkin / Lumi Diaper / All toggle above ALL THREE tabs (TP, OT, and
    // Already Transferred Today) — filters every list at once by each
    // order's own order_type, purely client-side against already-fetched
    // data (no re-fetch needed for any of the three).
    function ovSetTypeFilter(type, btn) {
        ovTypeFilter = type;
        document.querySelectorAll('.ov-type-filter-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        ovRenderOrderList('ovTpList', ovLastData.tp, 'No Territory Partner orders contributing today.');
        ovRenderOrderList('ovOtList', ovLastData.ot, 'No OT channel draft orders contributing today.');
        if (ovExcludedLastData) {
            renderTransferredToday(ovExcludedLastData);
            renderDeletedToday(ovExcludedLastData);
        }
    }

    function openOrdersOverview() {
        ovTypeFilter = 'all';
        document.querySelectorAll('.ov-type-filter-btn').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-type') === 'all'); });
        loadOrdersOverviewData();
        var modal = new bootstrap.Modal(document.getElementById('ordersOverviewModal'));
        modal.show();
    }

    // ── "Already Transferred Today" and "Deleted" — two separate, read-only
    // tabs sharing one data source (get-auto-transfer-skipped.php returns
    // both reasons together). Cached so the Napkin/Lumi Diaper filter
    // (ovSetTypeFilter) can re-render both tabs client-side, the same way
    // it already does for the TP/OT tabs via ovLastData — no re-fetch
    // needed when just the type filter changes. Originally rendered as one
    // combined tab with "Deleted" as a section underneath — split into its
    // own tab 2026-09-25 per request: a "Deleted" heading nested inside the
    // "Already Transferred Today" tab still read as a second, confusing
    // sub-tab.
    var ovExcludedLastData = null;

    function loadExcludedAndDeletedToday() {
        if (ovExcludedLastData) {
            renderTransferredToday(ovExcludedLastData);
            renderDeletedToday(ovExcludedLastData);
            return;
        }
        var loading = '<div class="text-muted small" style="padding:10px 4px;">Loading&hellip;</div>';
        document.getElementById('ovExcludedList').innerHTML = loading;
        document.getElementById('ovDeletedList').innerHTML = loading;

        $.getJSON('get-auto-transfer-skipped.php', {}, function (data) {
            ovExcludedLastData = data;
            renderTransferredToday(data);
            renderDeletedToday(data);
        }).fail(function () {
            var failMsg = '<div class="text-danger small" style="padding:10px 4px;">Could not load orders.</div>';
            document.getElementById('ovExcludedList').innerHTML = failMsg;
            document.getElementById('ovDeletedList').innerHTML = failMsg;
        });
    }

    // Groups a flat item list by order (source_id up to the trailing
    // productId segment) — shared by the "Already Transferred" and
    // "Deleted" sections below, which are now rendered as two fully
    // separate lists (never mixed items within the same order-group), per
    // request 2026-09-25: mixing a still-genuinely-transferred line with a
    // soft-deleted one under the same "Already transferred" heading made
    // the deleted (never-moved) qty look like it had actually shipped.
    function ovExclGroupByOrder(items) {
        var groups = [];
        var groupsByKey = {};
        items.forEach(function (item) {
            var lastColon = item.source_id.lastIndexOf(':');
            var groupKey = item.source_id.substring(0, lastColon);
            if (!groupsByKey[groupKey]) {
                groupsByKey[groupKey] = { key: groupKey, label: item.label, sourceType: item.source_id.slice(0, 2), items: [] };
                groups.push(groupsByKey[groupKey]);
            }
            groupsByKey[groupKey].items.push(item);
        });
        return groups;
    }

    function ovExclProductTotals(items) {
        var totals = [];
        var byName = {};
        items.forEach(function (item) {
            if (!byName[item.product_name]) {
                byName[item.product_name] = { name: item.product_name, qty: 0 };
                totals.push(byName[item.product_name]);
            }
            byName[item.product_name].qty += Number(item.qty) || 0;
        });
        return totals;
    }

    function ovExclRenderSection(items, opts) {
        // opts: { summaryColor, summaryBg, summaryTitle, badgeBg, badgeColor,
        //         badgeLabel(item, group) }. No section title here — the
        // caller (renderTransferredToday/renderDeletedToday) decides whether
        // one is needed, since
        // this tab is ALREADY titled "Already Transferred Today"; printing
        // an "Already Transferred" heading right under it read like a
        // second, nested tab. Confirmed 2026-09-25.
        if (!items.length) return '';

        var html = '';
        var totals = ovExclProductTotals(items);
        html += '<div style="border:1px solid ' + opts.summaryBorder + ';background:' + opts.summaryBg + ';border-radius:10px;padding:10px 12px;margin-bottom:12px;">' +
            '<div style="font-weight:600;color:' + opts.summaryColor + ';margin-bottom:6px;">' + opts.summaryTitle + '</div>';
        totals.forEach(function (p) {
            html += '<div style="display:flex;justify-content:space-between;gap:10px;padding:2px 0;">' +
                '<span>' + escBd(p.name) + '</span>' +
                '<span style="font-weight:600;">' + p.qty + '</span>' +
            '</div>';
        });
        html += '</div>';

        var groups = ovExclGroupByOrder(items);
        groups.forEach(function (group) {
            var groupSourceIds = group.items.map(function (item) { return item.source_id; });
            html += '<div class="ov-excl-group" style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:10px;">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">' +
                    '<div style="font-weight:600;overflow-wrap:anywhere;">' + escBd(group.label) + '</div>' +
                    '<button type="button" class="btn btn-sm btn-outline-primary ov-readd-btn" ' +
                        'data-source-ids="' + escAttr(JSON.stringify(groupSourceIds)) + '" ' +
                        'data-group-key="' + escAttr(group.key) + '" ' +
                        'style="white-space:nowrap;font-size:11px;padding:2px 10px;">Re-add to TP Purchase Orders</button>' +
                '</div>';
            group.items.forEach(function (item) {
                html += '<div style="display:flex;align-items:center;gap:8px;padding:5px 0 0 4px;">' +
                    '<span style="color:#9ca3af;">' + escBd(item.product_name) + '</span>' +
                    '<span style="color:#374151;font-weight:600;">Qty: ' + (Number(item.qty) || 0) + '</span>' +
                    '<span class="badge" style="background:' + opts.badgeBg + ';color:' + opts.badgeColor + ';">' + opts.badgeLabel(item, group) + '</span>' +
                '</div>';
            });
            html += '</div>';
        });
        return html;
    }

    function renderTransferredToday(data) {
        var el = document.getElementById('ovExcludedList');
        var items = (data.tp || []).concat(data.ot || []).filter(function (item) {
            return item.reason !== 'excluded' && (ovTypeFilter === 'all' || item.order_type === ovTypeFilter);
        });
        if (!items.length) {
            el.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">Nothing transferred yet today.</div>';
            return;
        }
        el.innerHTML = ovExclRenderSection(items, {
            summaryBorder: '#bbf7d0', summaryBg: '#f0fdf4', summaryColor: '#065f46',
            summaryTitle: 'Total Transferred Qty (Product-wise)',
            badgeBg: '#d1fae5', badgeColor: '#065f46',
            badgeLabel: function () { return 'Already transferred'; }
        });
        el.querySelectorAll('.ov-readd-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { reAddToTpPurchaseOrders(btn); });
        });
    }

    function renderDeletedToday(data) {
        var el = document.getElementById('ovDeletedList');
        var items = (data.tp || []).concat(data.ot || []).filter(function (item) {
            return item.reason === 'excluded' && (ovTypeFilter === 'all' || item.order_type === ovTypeFilter);
        });
        if (!items.length) {
            el.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">Nothing deleted today.</div>';
            return;
        }
        el.innerHTML = ovExclRenderSection(items, {
            summaryBorder: '#fecaca', summaryBg: '#fef2f2', summaryColor: '#991b1b',
            summaryTitle: 'Total Deleted Qty (Product-wise) — never moved, stock untouched',
            badgeBg: '#fee2e2', badgeColor: '#991b1b',
            // "Deleted PO" for a TP purchase order line, "Deleted Order" for
            // an OT channel draft line — OT orders aren't POs, so reusing
            // "Deleted PO" there would misdescribe them.
            badgeLabel: function (item, group) { return group.sourceType === 'tp' ? 'Deleted PO' : 'Deleted Order'; }
        });
        el.querySelectorAll('.ov-readd-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { reAddToTpPurchaseOrders(btn); });
        });
    }

    // "Re-add to TP Purchase Orders" — un-skips every product line of one
    // order (deletes its auto_transfer_skip_today row so the existing,
    // trusted demand calculation counts it again) WITHOUT touching stock
    // at all. Used when an earlier auto-transfer for this order was wrong
    // (bad qty/rate) and staff want to redo it manually — the stock that
    // already moved is left exactly where it is; only the "still needed"
    // flag comes back. Confirmed 2026-09-24: deliberately no stock
    // reversal here, unlike undo-auto-transfer.php.
    function reAddToTpPurchaseOrders(btn) {
        var sourceIds = JSON.parse(btn.getAttribute('data-source-ids') || '[]');
        var groupKey = btn.getAttribute('data-group-key') || '';
        if (!sourceIds.length) return;
        btn.disabled = true;
        btn.textContent = 'Re-adding…';

        var calls = sourceIds.map(function (sourceId) {
            return $.post('unskip-auto-transfer-order.php', { source_id: sourceId }, null, 'json')
                .then(function (res) { return res; }, function () { return { success: false }; });
        });

        $.when.apply($, calls).done(function () {
            var results = Array.prototype.slice.call(arguments);
            var anyFailed = results.some(function (res) { return !res || !res.success; });
            if (anyFailed) {
                alert('Could not re-add this order. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Re-add to TP Purchase Orders';
                return;
            }
            if (groupKey) reAddedGroupKeys[groupKey] = true;
            ovExcludedLastData = null; // force a re-fetch — the re-add just changed the underlying skip rows
            loadExcludedAndDeletedToday();
            $.when(loadOrdersOverviewData()).done(function () {
                var tpTabBtn = document.querySelector('#ordersOverviewModal button[data-bs-target="#ovTpPane"]');
                // Bootstrap 5.0 (this site's version) has no Tab.getOrCreateInstance
                // (added in 5.2) — `new bootstrap.Tab()` is the version that
                // actually exists here. The earlier getOrCreateInstance call threw
                // a TypeError, which silently aborted this whole callback before
                // the list/tab ever updated. Confirmed 2026-09-24.
                if (tpTabBtn) { try { new bootstrap.Tab(tpTabBtn).show(); } catch (e) {} }
            });
        }).fail(function () {
            alert('Could not re-add this order. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Re-add to TP Purchase Orders';
        });
    }

    // ── "Transfer History" ──
    function openTransferHistory() {
        var dateInput = document.getElementById('thDateInput');
        if (!dateInput.value) {
            dateInput.value = new Date().toISOString().slice(0, 10);
        }
        document.getElementById('thResult').innerHTML = '';
        var modal = new bootstrap.Modal(document.getElementById('transferHistoryModal'));
        modal.show();
        loadTransferHistory();
    }

    function thFmtStock(v) { return v === null ? '<span class="text-muted">&mdash;</span>' : v; }

    function thFmtDateTime(v) {
        if (!v) return '<span class="text-muted">&mdash;</span>';
        var d = new Date(v.replace(' ', 'T'));
        if (isNaN(d.getTime())) return escBd(v);
        return d.toLocaleDateString('en-IN') + ' ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
    }

    // Invoice-wise layout, matching Manage Internal Stock Transfer: one
    // row per Auto Transfer run (one "Transfer Now" click, identified by
    // its shared tempid pair), one column per product that appears in
    // ANY run on this date (union across runs, same convention the
    // manage page uses for its per-product columns) — 0/blank for a run
    // that didn't move that product.
    function loadTransferHistory() {
        var date = document.getElementById('thDateInput').value;
        if (!date) return;
        var resultEl = document.getElementById('thResult');
        resultEl.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">Loading&hellip;</div>';

        $.getJSON('get-auto-transfer-history.php', { date: date }, function (data) {
            if (data.error) {
                resultEl.innerHTML = '<div class="text-danger small" style="padding:10px 4px;">' + escBd(data.error) + '</div>';
                return;
            }
            if (!data.runs || !data.runs.length) {
                resultEl.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">No auto-transfers found on this date.</div>';
                return;
            }

            // Union of every product across every run, in first-seen order.
            var productColumns = [];
            var seenProductIds = {};
            data.runs.forEach(function (run) {
                run.products.forEach(function (p) {
                    if (!seenProductIds[p.product_id]) {
                        seenProductIds[p.product_id] = true;
                        productColumns.push({ id: p.product_id, name: p.product_name });
                    }
                });
            });

            var html = '<div style="overflow-x:auto;"><table class="table table-bordered table-sm" style="min-width:' + (700 + productColumns.length * 90) + 'px;">' +
                '<thead><tr style="background:#f8fafc;">' +
                    '<th>Date &amp; Time</th>' +
                    '<th>Invoice (Neksomo&rarr;Healthcare)</th>' +
                    '<th>Invoice (Healthcare&rarr;LLP)</th>';
            productColumns.forEach(function (col) { html += '<th>' + escBd(col.name) + '</th>'; });
            html += '<th>Undo</th></tr></thead><tbody>';

            data.runs.forEach(function (run) {
                var qtyByProductId = {};
                run.products.forEach(function (p) { qtyByProductId[p.product_id] = p.qty_transferred; });
                var runProductIds = run.products.map(function (p) { return p.product_id; });

                html += '<tr data-product-ids="' + escBd(JSON.stringify(runProductIds)) + '">' +
                    '<td>' + thFmtDateTime(run.transferred_at) + '</td>' +
                    '<td>' + (run.inv_number_leg1 ? escBd(run.inv_number_leg1) : '<span class="text-muted">&mdash;</span>') + '</td>' +
                    '<td>' + (run.inv_number_leg2 ? escBd(run.inv_number_leg2) : '<span class="text-muted">&mdash;</span>') + '</td>';
                productColumns.forEach(function (col) {
                    var qty = qtyByProductId[col.id];
                    html += '<td' + (qty ? ' style="font-weight:600;"' : '') + '>' + (qty || '<span class="text-muted">&mdash;</span>') + '</td>';
                });
                html += '<td>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger th-undo" ' +
                            'data-tempid="' + escBd(run.tempid) + '" ' +
                            'style="white-space:nowrap;font-size:11px;padding:2px 8px;">Undo</button>' +
                    '</td>' +
                '</tr>';
            });

            html += '</tbody></table></div>';
            resultEl.innerHTML = html;

            resultEl.querySelectorAll('.th-undo').forEach(function (btn) {
                btn.addEventListener('click', function () { undoAutoTransfer(this); });
            });
        }).fail(function () {
            resultEl.innerHTML = '<div class="text-danger small" style="padding:10px 4px;">Could not load history.</div>';
        });
    }

    // Reverses every product moved in one Auto Transfer run (both legs:
    // Neksomo -> Healthcare -> LLP, for every product in that run) via
    // undo-auto-transfer.php, then reloads the whole page so the main
    // table's Required Qty / Available (Neksomo/Healthcare) columns pick
    // up the restored stock. If any single product in the run can't be
    // undone (its stock already moved on further down the chain), the
    // ones that succeeded stay undone — reported so the user can
    // reconcile the rest manually rather than silently losing partial
    // progress.
    function undoAutoTransfer(btn) {
        var tempid = btn.getAttribute('data-tempid');
        var row = btn.closest('tr');
        var runProductIds = JSON.parse(row.getAttribute('data-product-ids') || '[]');

        if (!confirm('Undo this transfer run? This reverses both legs (Neksomo -> Healthcare -> LLP) for every product moved in this run (' + runProductIds.length + ' product(s)).')) return;
        btn.disabled = true;

        // Each call resolves to its parsed JSON response directly (never
        // jQuery's raw [data, textStatus, jqXHR] triple), avoiding
        // $.when's varargs quirk where a single deferred's .done() callback
        // receives different argument shapes than two or more.
        var calls = runProductIds.map(function (pid) {
            return $.post('undo-auto-transfer.php', { tempid: tempid, product_id: pid }, null, 'json')
                .then(function (res) { return res; }, function () { return { success: false, reason: 'request_failed' }; });
        });

        $.when.apply($, calls).done(function () {
            var results = Array.prototype.slice.call(arguments);
            var failures = results.filter(function (res) { return !res || !res.success; });
            if (failures.length > 0) {
                var insufficientMsgs = failures
                    .filter(function (f) { return f && f.reason === 'insufficient_stock_to_reverse'; })
                    .map(function (f) { return 'only ' + f.available + ' of ' + f.requested + ' still in stock'; });
                alert('Some products in this run could not be undone (already moved on further down the chain: '
                    + (insufficientMsgs.join('; ') || 'unknown reason') + '). Products that could be undone were reversed; reconcile the rest manually.');
            }
            window.location.reload();
        });
    }
</script>
</body>
</html>
