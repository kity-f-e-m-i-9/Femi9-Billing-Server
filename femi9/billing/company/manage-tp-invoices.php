<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("include/GodownAccess.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Ensure courier_charges column exists (migration)
$col = $db_conn->query("SHOW COLUMNS FROM tp_invoices LIKE 'courier_charges'");
if ($col && $col->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN courier_charges DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER invoice_date");
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_state_id    = (int)($_GET['state_id']    ?? 0);
$filter_location_id = (int)($_GET['location_id'] ?? 0);
$filter_tp_id       = (int)($_GET['tp_id']       ?? 0);

// ── Filter dropdown data ───────────────────────────────────────────────────────
// States = depth-2 nodes that are ancestors of (or equal to) any source_location used in invoices
$states_res = $db_conn->query("
    SELECT DISTINCT n2.id, n2.name
    FROM partner_location_nodes n2
    WHERE n2.depth = 2
      AND n2.is_active = 1
      AND (
          n2.id IN (SELECT DISTINCT source_location_id FROM tp_invoices)
          OR n2.id IN (
              SELECT DISTINCT pln.parent_id FROM tp_invoices tpi
              JOIN partner_location_nodes pln ON pln.id = tpi.source_location_id
              WHERE pln.parent_id IS NOT NULL
          )
      )
    ORDER BY n2.name
");
$states = $states_res ? $states_res->fetch_all(MYSQLI_ASSOC) : [];

// Source locations actually used in invoices (with parent_id for cascade)
$locations_res = $db_conn->query("
    SELECT DISTINCT pln.id, pln.name, pln.parent_id, pln.depth
    FROM tp_invoices tpi
    JOIN partner_location_nodes pln ON pln.id = tpi.source_location_id
    ORDER BY pln.name
");
$locations = $locations_res ? $locations_res->fetch_all(MYSQLI_ASSOC) : [];

// TPs with invoices — collect all location_ids per TP for cascade
// (company-issued only — see $where below for why)
$tp_rows_res = $db_conn->query("
    SELECT DISTINCT tp.id, tp.name, tp.tp_id AS tp_code, tpi.source_location_id
    FROM tp_invoices tpi
    JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
    WHERE (tpi.source_cp_id > 0 OR tpi.source_godown_id > 0)
    ORDER BY tp.name
");
$tp_rows = $tp_rows_res ? $tp_rows_res->fetch_all(MYSQLI_ASSOC) : [];

$tps_unique    = [];   // id => [id, name, tp_code]
$tp_loc_map    = [];   // tp_id => [location_ids]
$tp_state_map  = [];   // tp_id => [state_ids]  (for state-only cascade)
$loc_state_idx = [];   // location_id => state_id
foreach ($locations as $loc) {
    $loc_state_idx[$loc['id']] = ($loc['depth'] == 2) ? $loc['id'] : (int)$loc['parent_id'];
}
foreach ($tp_rows as $row) {
    $tid = $row['id'];
    $lid = (int)$row['source_location_id'];
    if (!isset($tps_unique[$tid])) {
        $tps_unique[$tid]   = ['id' => $tid, 'name' => $row['name'], 'tp_code' => $row['tp_code']];
        $tp_loc_map[$tid]   = [];
        $tp_state_map[$tid] = [];
    }
    if (!in_array($lid, $tp_loc_map[$tid]))   $tp_loc_map[$tid][]   = $lid;
    $sid = $loc_state_idx[$lid] ?? 0;
    if ($sid && !in_array($sid, $tp_state_map[$tid])) $tp_state_map[$tid][] = $sid;
}

// ── Build main query with filters ─────────────────────────────────────────────
// Company only — a super-stockist's own TP invoices never populate
// source_cp_id/source_godown_id (see super-stockist/tp-invoice-action.php),
// so this excludes every SS-issued invoice from this company-side listing.
$where  = ['(tpi.source_cp_id > 0 OR tpi.source_godown_id > 0)'];
$params = [];
$types  = '';

if ($filter_location_id > 0) {
    $where[]  = "tpi.source_location_id = ?";
    $params[] = $filter_location_id;
    $types   .= 'i';
} elseif ($filter_state_id > 0) {
    // Locations that ARE the state node, or whose parent IS the state node (old invoices only)
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

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT tpi.id, tpi.invoice_number, tpi.invoice_date, tpi.total_amount,
           COALESCE(tpi.courier_charges, 0) AS courier_charges,
           tpi.created_by, tpi.created_at,
           tp.name AS tp_name, tp.tp_id AS tp_code,
           COALESCE(cp_src.name, gd.gname, pln.name) AS source_location,
           COALESCE(cp_src.name, cp_old.name) AS cp_name,
           COALESCE(cp_src.cp_id, cp_old.cp_id) AS cp_code,
           COALESCE(rcpt.collected, 0) AS courier_collected
    FROM tp_invoices tpi
    JOIN territory_partners tp             ON tp.id  = tpi.territory_partner_id
    LEFT JOIN partner_location_nodes pln   ON pln.id = tpi.source_location_id
    LEFT JOIN channel_partner_locations cpl ON cpl.location_id = tpi.source_location_id
    LEFT JOIN channel_partners cp_old      ON cp_old.id = cpl.channel_partner_id
    LEFT JOIN channel_partners cp_src      ON cp_src.id = tpi.source_cp_id
    LEFT JOIN company_godown gd            ON gd.id = tpi.source_godown_id AND (" . godown_finance_filter_sql($db_conn, 'gd') . ")
    LEFT JOIN (
        SELECT tp_invoice_id, SUM(amount) AS collected
        FROM tp_invoice_receipts
        GROUP BY tp_invoice_id
    ) rcpt ON rcpt.tp_invoice_id = tpi.id
    $where_sql
    ORDER BY tpi.created_at DESC
";

if ($params) {
    $stmt_main = $db_conn->prepare($sql);
    $stmt_main->bind_param($types, ...$params);
    $stmt_main->execute();
    $invoices = $stmt_main->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_main->close();
} else {
    $result   = $db_conn->query($sql);
    $invoices = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$total_count  = count($invoices);
$total_amount = array_sum(array_column($invoices, 'total_amount'));
$this_month   = 0; $month_amount = 0;
$cur_ym = date('Y-m');
foreach ($invoices as $inv) {
    if (substr($inv['invoice_date'], 0, 7) === $cur_ym) { $this_month++; $month_amount += $inv['total_amount']; }
}
$i = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Invoices : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        body { font-family: 'Poppins', sans-serif; }

        .stat-card { background:#fff; border-radius:10px; padding:18px 20px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border-left:4px solid; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; }
        .stat-card.purple { border-color:#667eea; }
        .stat-card.green  { border-color:#10b981; }
        .stat-card.blue   { border-color:#3b82f6; }
        .stat-card.amber  { border-color:#f59e0b; }
        .stat-card h3 { font-size:22px; font-weight:700; margin:0 0 2px 0; color:#1f2937; }
        .stat-card p  { margin:0; font-size:11.5px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
        .stat-icon { font-size:36px; opacity:.12; }
        .stat-card.purple .stat-icon { color:#667eea; }
        .stat-card.green  .stat-icon { color:#10b981; }
        .stat-card.blue   .stat-icon { color:#3b82f6; }
        .stat-card.amber  .stat-icon { color:#f59e0b; }

        .card { border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border:none; margin-bottom:20px; }
        .card-header { background:#fff; border-bottom:1px solid #f0f0f0; border-radius:10px 10px 0 0 !important; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; }
        .card-header-title { font-size:14px; font-weight:600; color:#2c3e50; margin:0; display:flex; align-items:center; gap:8px; }
        .card-header-title i { font-size:18px; color:#667eea; }

        .alert { border-radius:8px; border:none; font-size:13.5px; padding:12px 16px; }
        .alert-success { background:#f0fdf4; color:#166534; border-left:4px solid #22c55e; }
        .alert-danger  { background:#fef2f2; color:#991b1b; border-left:4px solid #ef4444; }

        .btn-add { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border-radius:7px; font-size:13px; font-weight:500; border:none; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; transition:all .2s; text-decoration:none; }
        .btn-add:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(102,126,234,.4); color:#fff; }

        table#datatable1 thead th { font-size:11.5px !important; font-weight:600 !important; color:#6b7280 !important; text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }

        .action-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; transition:background .15s; text-decoration:none; }
        .action-btn.view   { color:#667eea; } .action-btn.view:hover   { background:#ede9fe; }
        .action-btn.edit   { color:#d97706; } .action-btn.edit:hover   { background:#fef3c7; }
        .action-btn.print  { color:#0369a1; } .action-btn.print:hover  { background:#e0f2fe; }
        .action-btn.delete { color:#dc2626; } .action-btn.delete:hover { background:#fee2e2; }
        .courier-chip { display:inline-block; background:#fef3c7; color:#92400e; border-radius:5px; padding:2px 8px; font-size:11px; font-weight:600; }
        .pay-badge { display:inline-flex; align-items:center; gap:3px; border-radius:5px; padding:3px 9px; font-size:11px; font-weight:700; text-decoration:none; white-space:nowrap; transition:opacity .15s; }
        .pay-badge:hover { opacity:.8; }
        .pay-badge.not-collected { background:#fee2e2; color:#991b1b; }
        .pay-badge.partial       { background:#fef3c7; color:#92400e; }
        .pay-badge.collected     { background:#d1fae5; color:#065f46; }
        .pay-badge.na            { background:#f3f4f6; color:#9ca3af; cursor:default; }
        .action-btn.receipt { color:#059669; } .action-btn.receipt:hover { background:#d1fae5; }
        .action-btn.cn      { color:#7c3aed; } .action-btn.cn:hover      { background:#ede9fe; }

        .filter-card { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; }
        .filter-card .form-label { color:#fff; font-weight:500; margin-bottom:4px; font-size:12.5px; }
        .filter-card .form-control, .filter-card select.form-control { background:rgba(255,255,255,0.95); border:none; border-radius:6px; font-size:13px; height:36px; padding:4px 10px; }
        .filter-card .btn-filter { background:#fff; color:#667eea; border:none; border-radius:6px; padding:7px 18px; font-size:13px; font-weight:600; height:36px; line-height:1; cursor:pointer; transition:all .15s; }
        .filter-card .btn-filter:hover { background:#f0f0ff; }
        .filter-card .btn-clear { background:rgba(255,255,255,0.18); color:#fff; border:1px solid rgba(255,255,255,0.4); border-radius:6px; padding:7px 14px; font-size:13px; height:36px; line-height:1; cursor:pointer; transition:all .15s; text-decoration:none; display:inline-flex; align-items:center; }
        .filter-card .btn-clear:hover { background:rgba(255,255,255,0.28); color:#fff; }
        .filter-active-badge { display:inline-block; background:#fbbf24; color:#78350f; border-radius:5px; padding:2px 8px; font-size:11px; font-weight:700; margin-left:8px; vertical-align:middle; }
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

                    <!-- Page Header -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble"><tr>
                                        <td>TP Invoices</td>
                                        <td><a href="add-tp-invoice" title="Create TP Invoice">&#10011;</a></td>
                                    </tr></table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success mb-3">
                            <i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">check_circle</i>
                            Invoice <strong><?php echo htmlspecialchars($_GET['inv'] ?? ''); ?></strong> created successfully.
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['updated'])): ?>
                        <div class="alert alert-success mb-3">
                            <i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">edit</i>
                            Invoice <strong><?php echo htmlspecialchars($_GET['inv'] ?? ''); ?></strong> updated. Stock and advance balance recalculated.
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['deleted'])): ?>
                        <div class="alert alert-warning mb-3">
                            <i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">delete_outline</i>
                            Invoice <strong><?php echo htmlspecialchars($_GET['inv'] ?? ''); ?></strong> deleted. Stock and advance balance have been restored.
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger mb-3">
                            <i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">error_outline</i>
                            An error occurred. Please try again.
                        </div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="filter-card">
                        <form method="GET" action="" id="filterForm">
                            <div class="row align-items-end g-2">
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">map</i>
                                        State
                                    </label>
                                    <select name="state_id" id="filter_state" class="form-control" onchange="cascadeLocations()">
                                        <option value="">All States</option>
                                        <?php foreach ($states as $st): ?>
                                        <option value="<?php echo $st['id']; ?>" <?php echo $filter_state_id == $st['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($st['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">location_on</i>
                                        Location
                                    </label>
                                    <select name="location_id" id="filter_location" class="form-control" onchange="cascadeTps()">
                                        <option value="">All Locations</option>
                                        <?php foreach ($locations as $loc): ?>
                                        <option value="<?php echo $loc['id']; ?>"
                                            data-parent="<?php echo (int)($loc['depth'] == 2 ? $loc['id'] : $loc['parent_id']); ?>"
                                            <?php echo $filter_location_id == $loc['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loc['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">person</i>
                                        Territory Partner
                                    </label>
                                    <select name="tp_id" id="filter_tp" class="form-control">
                                        <option value="">All TPs</option>
                                        <?php foreach ($tps_unique as $tp): ?>
                                        <option value="<?php echo $tp['id']; ?>"
                                            data-locations="<?php echo implode(',', $tp_loc_map[$tp['id']]); ?>"
                                            data-states="<?php echo implode(',', $tp_state_map[$tp['id']]); ?>"
                                            <?php echo $filter_tp_id == $tp['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tp['name']); ?> (<?php echo htmlspecialchars($tp['tp_code']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label" style="visibility:hidden;">.</label>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <button type="submit" class="btn-filter">
                                            <i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">filter_list</i>
                                            Filter
                                        </button>
                                        <?php if ($filter_state_id || $filter_location_id || $filter_tp_id): ?>
                                        <a href="manage-tp-invoices" class="btn-clear">
                                            <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;margin-right:3px;">close</i>
                                            Clear
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Stats -->
                    <div class="row">
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card purple">
                                <div><h3><?php echo $total_count; ?></h3><p>Total Invoices</p></div>
                                <i class="material-icons-outlined stat-icon">receipt_long</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card green">
                                <div><h3>₹<?php echo inr_format($total_amount, 0); ?></h3><p>Total Value</p></div>
                                <i class="material-icons-outlined stat-icon">currency_rupee</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card blue">
                                <div><h3><?php echo $this_month; ?></h3><p>This Month</p></div>
                                <i class="material-icons-outlined stat-icon">calendar_month</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card amber">
                                <div><h3>₹<?php echo inr_format($month_amount, 0); ?></h3><p>Month Value</p></div>
                                <i class="material-icons-outlined stat-icon">trending_up</i>
                            </div>
                        </div>
                    </div>

                    <!-- Table Card -->
                    <div class="card">
                        <div class="card-header">
                            <span class="card-header-title">
                                <i class="material-icons-outlined">receipt_long</i>
                                All TP Invoices
                                <?php if ($filter_state_id || $filter_location_id || $filter_tp_id): ?>
                                <span class="filter-active-badge">Filtered</span>
                                <?php endif; ?>
                            </span>
                            <a href="add-tp-invoice" class="btn-add">
                                <i class="material-icons" style="font-size:16px;">add</i>
                                New Invoice
                            </a>
                        </div>
                        <div class="card-body">
                            <div style="overflow-x:auto;">
                                <table id="datatable1" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Invoice #</th>
                                            <th>Territory Partner</th>
                                            <th>Source Location</th>
                                            <th>Channel Partner</th>
                                            <th>Date</th>
                                            <th>Amount (₹)</th>
                                            <th>Courier</th>
                                            <th>Payment</th>
                                            <th>Created By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($invoices as $inv):
                                        $enc     = base64_encode($inv['id']);
                                        $courier = (float)$inv['courier_charges'];
                                        $subtotal = round((float)$inv['total_amount'] - $courier, 2);
                                    ?>
                                        <tr>
                                            <td style="color:#9ca3af;font-size:13px;"><?php echo ++$i; ?></td>
                                            <td><code style="font-size:12px;background:#f3f4f6;padding:2px 7px;border-radius:4px;"><?php echo htmlspecialchars($inv['invoice_number']); ?></code></td>
                                            <td>
                                                <span style="font-weight:600;font-size:13.5px;"><?php echo htmlspecialchars($inv['tp_name']); ?></span><br>
                                                <small style="color:#9ca3af;font-size:11px;"><?php echo htmlspecialchars($inv['tp_code']); ?></small>
                                            </td>
                                            <td style="font-size:13.5px;"><?php echo htmlspecialchars($inv['source_location']); ?></td>
                                            <td style="font-size:13.5px;">
                                                <?php if ($inv['cp_name']): ?>
                                                    <?php echo htmlspecialchars($inv['cp_name']); ?><br>
                                                    <small style="color:#9ca3af;font-size:11px;"><?php echo htmlspecialchars($inv['cp_code']); ?></small>
                                                <?php else: ?>
                                                    <span style="color:#d1d5db;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:13px;color:#6b7280;"><?php echo date('d M Y', strtotime($inv['invoice_date'])); ?></td>
                                            <td>
                                                <span style="font-weight:700;color:#10b981;font-size:13.5px;">₹<?php echo inr_format($subtotal, 2); ?></span>
                                                <?php if ($courier > 0): ?>
                                                    <br><small style="color:#94a3b8;font-size:11px;">+₹<?php echo inr_format($courier, 2); ?> courier</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($courier > 0): ?>
                                                    <span class="courier-chip">₹<?php echo inr_format($courier, 2); ?></span>
                                                <?php else: ?>
                                                    <span style="color:#d1d5db;font-size:12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $collected = (float)$inv['courier_collected'];
                                                if ($courier < 0.01):
                                                ?>
                                                    <span class="pay-badge na">N/A</span>
                                                <?php elseif ($collected <= 0): ?>
                                                    <a href="add-tp-receipt?id=<?php echo $enc; ?>" class="pay-badge not-collected" title="Add courier receipt">
                                                        <i class="material-icons-outlined" style="font-size:12px;">error_outline</i>
                                                        Not Collected
                                                    </a>
                                                <?php elseif ($collected < $courier - 0.01): ?>
                                                    <a href="add-tp-receipt?id=<?php echo $enc; ?>" class="pay-badge partial" title="Partial — add more">
                                                        <i class="material-icons-outlined" style="font-size:12px;">schedule</i>
                                                        Partial
                                                    </a>
                                                <?php else: ?>
                                                    <span class="pay-badge collected">
                                                        <i class="material-icons-outlined" style="font-size:12px;">check_circle</i>
                                                        Collected
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:13px;color:#6b7280;"><?php echo htmlspecialchars($inv['created_by']); ?></td>
                                            <td style="white-space:nowrap;">
                                                <a href="view-tp-invoice?id=<?php echo $enc; ?>" class="action-btn view" title="View Invoice">
                                                    <i class="material-icons-outlined" style="font-size:19px;">visibility</i>
                                                </a>
                                                <a href="edit-tp-invoice?id=<?php echo $enc; ?>" class="action-btn edit" title="Edit Invoice">
                                                    <i class="material-icons-outlined" style="font-size:19px;">edit</i>
                                                </a>
                                                <a href="tp-invoice-print?id=<?php echo $enc; ?>" class="action-btn print" title="Print Invoice">
                                                    <i class="material-icons-outlined" style="font-size:19px;">print</i>
                                                </a>
                                                <a href="tp-cnote-new?inv_id=<?php echo $inv['id']; ?>" class="action-btn cn" title="Credit Note / Return">
                                                    <i class="material-icons-outlined" style="font-size:19px;">assignment_return</i>
                                                </a>
                                                <?php if ($courier > 0): ?>
                                                <a href="add-tp-receipt?id=<?php echo $enc; ?>" class="action-btn receipt" title="Courier Receipt">
                                                    <i class="material-icons-outlined" style="font-size:19px;">receipt</i>
                                                </a>
                                                <?php endif; ?>
                                                <form method="POST" action="delete-tp-invoice" class="d-inline delete-invoice-form" style="display:inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="invoice_enc" value="<?php echo $enc; ?>">
                                                    <button type="button"
                                                       class="action-btn delete btn-delete-invoice" title="Delete Invoice"
                                                       data-inv="<?php echo htmlspecialchars($inv['invoice_number']); ?>">
                                                        <i class="material-icons-outlined" style="font-size:19px;">delete_outline</i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-delete-invoice').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var inv  = this.dataset.inv;
            var form = this.closest('form');
            Swal.fire({
                title: 'Delete ' + inv + '?',
                html: 'This will <strong>reverse all stock movements</strong> and restore the advance balance.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>
<script>
function cascadeLocations() {
    var stateId  = parseInt(document.getElementById('filter_state').value) || 0;
    var locSel   = document.getElementById('filter_location');
    var tpSel    = document.getElementById('filter_tp');

    // Show/hide location options based on selected state
    Array.from(locSel.options).forEach(function(opt) {
        if (!opt.value) return;
        var parent = parseInt(opt.dataset.parent) || 0;
        opt.hidden = stateId > 0 && parent !== stateId;
    });

    // If the currently selected location is now hidden, reset it
    var selLoc = locSel.querySelector('option:checked');
    if (selLoc && selLoc.value && selLoc.hidden) locSel.value = '';

    cascadeTps();
}

function cascadeTps() {
    var stateId = parseInt(document.getElementById('filter_state').value)   || 0;
    var locId   = parseInt(document.getElementById('filter_location').value) || 0;
    var tpSel   = document.getElementById('filter_tp');

    Array.from(tpSel.options).forEach(function(opt) {
        if (!opt.value) return;
        var locs   = (opt.dataset.locations || '').split(',').map(Number).filter(Boolean);
        var states = (opt.dataset.states    || '').split(',').map(Number).filter(Boolean);
        if (locId > 0) {
            opt.hidden = !locs.includes(locId);
        } else if (stateId > 0) {
            opt.hidden = !states.includes(stateId);
        } else {
            opt.hidden = false;
        }
    });

    // If the currently selected TP is now hidden, reset it
    var selTp = tpSel.querySelector('option:checked');
    if (selTp && selTp.value && selTp.hidden) tpSel.value = '';
}

// Apply cascade on page load to reflect any server-side selected values
document.addEventListener('DOMContentLoaded', function() {
    cascadeLocations();
});
</script>
</body>
</html>
