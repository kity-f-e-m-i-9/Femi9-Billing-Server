<?php
ob_start();
include("checksession.php");
require_once("include/GodownAccess.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── Clear filters ─────────────────────────────────────────────────────────────
if (isset($_GET['clear_filters']) || isset($_POST['clear_all'])) {
    ob_clean();
    unset($_SESSION['plgt_location_id'], $_SESSION['plgt_transfer_type'],
          $_SESSION['plgt_date_from'],   $_SESSION['plgt_date_to'],
          $_SESSION['plgt_cp_id'],       $_SESSION['plgt_records_per_page'],
          $_SESSION['plgt_search']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

mysqli_set_charset($db_conn, 'utf8mb4');

// ── Filter: location ──────────────────────────────────────────────────────────
$selected_location_id = 0;
if (isset($_POST['location_id'])) {
    $selected_location_id = (int)$_POST['location_id'];
    if ($selected_location_id) $_SESSION['plgt_location_id'] = $selected_location_id;
    else unset($_SESSION['plgt_location_id']);
} elseif (isset($_SESSION['plgt_location_id'])) {
    $selected_location_id = (int)$_SESSION['plgt_location_id'];
}

// ── Filter: channel partner ───────────────────────────────────────────────────
$selected_cp_id = '';
if (isset($_POST['cp_id'])) {
    $selected_cp_id = trim($_POST['cp_id']);
    if ($selected_cp_id) $_SESSION['plgt_cp_id'] = $selected_cp_id;
    else unset($_SESSION['plgt_cp_id']);
} elseif (isset($_SESSION['plgt_cp_id'])) {
    $selected_cp_id = $_SESSION['plgt_cp_id'];
}

// ── Filter: transfer type ─────────────────────────────────────────────────────
$selected_type = '';
if (isset($_POST['transfer_type'])) {
    $selected_type = trim($_POST['transfer_type']);
    if ($selected_type) $_SESSION['plgt_transfer_type'] = $selected_type;
    else unset($_SESSION['plgt_transfer_type']);
} elseif (isset($_SESSION['plgt_transfer_type'])) {
    $selected_type = $_SESSION['plgt_transfer_type'];
}

// ── Filter: dates ─────────────────────────────────────────────────────────────
$date_from = '';
if (isset($_POST['date_from'])) {
    $date_from = trim($_POST['date_from']);
    if ($date_from) $_SESSION['plgt_date_from'] = $date_from;
    else unset($_SESSION['plgt_date_from']);
} elseif (isset($_SESSION['plgt_date_from'])) {
    $date_from = $_SESSION['plgt_date_from'];
}
$date_to = '';
if (isset($_POST['date_to'])) {
    $date_to = trim($_POST['date_to']);
    if ($date_to) $_SESSION['plgt_date_to'] = $date_to;
    else unset($_SESSION['plgt_date_to']);
} elseif (isset($_SESSION['plgt_date_to'])) {
    $date_to = $_SESSION['plgt_date_to'];
}

// ── Filter: search ────────────────────────────────────────────────────────────
$search = '';
if (isset($_GET['q'])) {
    $search = trim($_GET['q']);
    $_SESSION['plgt_search'] = $search;
} elseif (isset($_POST['q'])) {
    $search = trim($_POST['q']);
    $_SESSION['plgt_search'] = $search;
} elseif (isset($_SESSION['plgt_search'])) {
    $search = $_SESSION['plgt_search'];
}
$search_esc = $db_conn->real_escape_string($search);
$is_search  = ($search !== '');

// ── Pagination ────────────────────────────────────────────────────────────────
$records_per_page = 20;
if (isset($_POST['records_per_page'])) {
    $records_per_page = (int)$_POST['records_per_page'];
    $_SESSION['plgt_records_per_page'] = $records_per_page;
} elseif (isset($_SESSION['plgt_records_per_page'])) {
    $records_per_page = (int)$_SESSION['plgt_records_per_page'];
}
if (!in_array($records_per_page, [20, 40, 60, 100])) $records_per_page = 20;
if ($is_search) $records_per_page = 5000;

$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;
$qparam = urlencode($search);

// ── WHERE clause ──────────────────────────────────────────────────────────────
$where_parts = ["1=1"];
if ($selected_location_id > 0) {
    $where_parts[] = "t.location_id = " . $selected_location_id;
}
if (in_array($selected_type, ['godown_to_location', 'location_to_godown'])) {
    $esc_type = $db_conn->real_escape_string($selected_type);
    $where_parts[] = "t.transfer_type = '$esc_type'";
}
if ($date_from !== '') {
    $where_parts[] = "t.transfer_date >= '" . $db_conn->real_escape_string($date_from) . "'";
}
if ($date_to !== '') {
    $where_parts[] = "t.transfer_date <= '" . $db_conn->real_escape_string($date_to) . "'";
}
if (!empty($selected_cp_id)) {
    $esc_cp = $db_conn->real_escape_string($selected_cp_id);
    $where_parts[] = "(t.cp_id IN (SELECT id FROM channel_partners WHERE cp_id = '$esc_cp') OR t.location_id IN (SELECT cpl.location_id FROM channel_partner_locations cpl JOIN channel_partners cpf ON cpf.id = cpl.channel_partner_id WHERE cpf.cp_id = '$esc_cp'))";
}
if ($search_esc !== '') {
    $where_parts[] = "(t.ref_number LIKE '%$search_esc%' OR g.gname LIKE '%$search_esc%' OR pln.name LIKE '%$search_esc%' OR cp.name LIKE '%$search_esc%' OR t.created_by LIKE '%$search_esc%')";
}
$where_sql = implode(' AND ', $where_parts);

// ── CP list for dropdown ──────────────────────────────────────────────────────
$cp_list = [];
$cp_res = $db_conn->query("SELECT cp_id, name FROM channel_partners WHERE is_active = 1 ORDER BY name ASC LIMIT 1000");
if ($cp_res) while ($r = $cp_res->fetch_assoc()) $cp_list[] = $r;

// ── Stats query (filtered totals for stat cards) ──────────────────────────────
$stats_sql = "
    SELECT
        COUNT(DISTINCT t.id) AS total,
        SUM(t.transfer_type = 'godown_to_location') AS to_loc,
        SUM(t.transfer_type = 'location_to_godown') AS to_godown,
        COALESCE(SUM(ti2.qty), 0) AS total_qty
    FROM pl_godown_transfers t
    JOIN company_godown g ON g.id = t.godown_id AND (" . godown_finance_filter_sql($db_conn, 'g') . ")
    LEFT JOIN partner_location_nodes pln ON pln.id = t.location_id
    LEFT JOIN channel_partners cp ON cp.id = t.cp_id
    LEFT JOIN (SELECT transfer_id, SUM(quantity) AS qty FROM pl_godown_transfer_items GROUP BY transfer_id) ti2
           ON ti2.transfer_id = t.id
    WHERE $where_sql
";
$stats_res = mysqli_query($db_conn, $stats_sql);
$stats = $stats_res ? mysqli_fetch_assoc($stats_res) : ['total'=>0,'to_loc'=>0,'to_godown'=>0,'total_qty'=>0];
$total     = (int)$stats['total'];
$to_loc    = (int)$stats['to_loc'];
$to_godown = (int)$stats['to_godown'];
$total_qty = (int)$stats['total_qty'];
$total_pages = max(1, (int)ceil($total / max(1, $records_per_page)));
if ($is_search) { $total_pages = 1; $page = 1; $offset = 0; }

// ── Paginated transfers ───────────────────────────────────────────────────────
$list_sql = "
    SELECT t.id, t.transfer_type, t.transfer_date, t.ref_number, t.note, t.created_by, t.created_at,
           g.gname AS godown_name,
           COALESCE(cp.name, pln.name) AS location_name,
           COUNT(ti.id) AS product_count,
           SUM(ti.quantity) AS total_qty
    FROM pl_godown_transfers t
    JOIN company_godown g ON g.id = t.godown_id AND (" . godown_finance_filter_sql($db_conn, 'g') . ")
    LEFT JOIN partner_location_nodes pln ON pln.id = t.location_id
    LEFT JOIN channel_partners cp ON cp.id = t.cp_id
    LEFT JOIN pl_godown_transfer_items ti ON ti.transfer_id = t.id
    WHERE $where_sql
    GROUP BY t.id
    ORDER BY t.created_at DESC
";
if (!$is_search) $list_sql .= " LIMIT " . (int)$records_per_page . " OFFSET " . (int)$offset;
$list_res = mysqli_query($db_conn, $list_sql);
$transfers = $list_res ? $list_res->fetch_all(MYSQLI_ASSOC) : [];

// ── Location name for display ─────────────────────────────────────────────────
$selected_location_name = '';
if ($selected_location_id > 0) {
    $ln_res = $db_conn->query("SELECT name FROM partner_location_nodes WHERE id = " . $selected_location_id . " LIMIT 1");
    if ($ln_res && $ln_row = $ln_res->fetch_assoc()) $selected_location_name = $ln_row['name'];
}

// ── Active filters display ────────────────────────────────────────────────────
$active_filters = [];
if ($selected_location_id > 0 && $selected_location_name !== '') $active_filters[] = "Location: " . htmlspecialchars($selected_location_name);
if (!empty($selected_cp_id)) $active_filters[] = "CP: " . htmlspecialchars($selected_cp_id);
if ($selected_type !== '') $active_filters[] = "Type: " . ($selected_type === 'godown_to_location' ? 'Godown → Location' : 'Location → Godown');
if ($date_from !== '')     $active_filters[] = "From: " . htmlspecialchars($date_from);
if ($date_to !== '')       $active_filters[] = "To: " . htmlspecialchars($date_to);
if ($search !== '')        $active_filters[] = "Search: &ldquo;" . htmlspecialchars($search) . "&rdquo;";

$i = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock Transfers : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        body { font-family: 'Poppins', sans-serif; }

        /* Stat cards */
        .stat-card { background:#fff; border-radius:10px; padding:18px 20px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border-left:4px solid; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; }
        .stat-card.purple { border-color:#667eea; }
        .stat-card.blue   { border-color:#3b82f6; }
        .stat-card.amber  { border-color:#f59e0b; }
        .stat-card.green  { border-color:#10b981; }
        .stat-card h3 { font-size:26px; font-weight:700; margin:0 0 2px 0; color:#1f2937; }
        .stat-card p  { margin:0; font-size:11.5px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
        .stat-icon { font-size:36px; opacity:.12; }
        .stat-card.purple .stat-icon { color:#667eea; }
        .stat-card.blue   .stat-icon { color:#3b82f6; }
        .stat-card.amber  .stat-icon { color:#f59e0b; }
        .stat-card.green  .stat-icon { color:#10b981; }

        /* Cards */
        .card { border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border:none; margin-bottom:20px; }
        .card-header { background:#fff; border-bottom:1px solid #f0f0f0; border-radius:10px 10px 0 0 !important; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; }
        .card-header-title { font-size:14px; font-weight:600; color:#2c3e50; margin:0; display:flex; align-items:center; gap:8px; }
        .card-header-title i { font-size:18px; color:#667eea; }

        .alert { border-radius:8px; border:none; font-size:13.5px; padding:12px 16px; }
        .alert-success { background:#f0fdf4; color:#166534; border-left:4px solid #22c55e; }
        .alert-warning { background:#fefce8; color:#92400e; border-left:4px solid #f59e0b; }

        /* Quick action buttons */
        .btn-action { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:7px; font-size:13px; font-weight:500; border:none; color:#fff; transition:all .2s; text-decoration:none; }
        .btn-action.purple { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); }
        .btn-action.purple:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(102,126,234,.4); color:#fff; }
        .btn-action.amber { background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%); }
        .btn-action.amber:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(245,158,11,.4); color:#fff; }

        /* Table */
        .table th { background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%); font-weight:600; white-space:nowrap; font-size:13.5px; padding:11px 10px; border-color:#dee2e6; color:#495057; }
        .table td { white-space:nowrap; font-size:13px; padding:9px 10px; vertical-align:middle; border-color:#dee2e6; }
        .table-hover tbody tr:hover { background-color:rgba(0,123,255,0.04); }

        .type-badge-in  { background:#ede9fe; color:#5b21b6; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; }
        .type-badge-out { background:#fef3c7; color:#92400e; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; }

        .btn-view { display:inline-flex; align-items:center; gap:4px; padding:5px 12px; border-radius:6px; font-size:12px; font-weight:500; border:1px solid #667eea; color:#667eea; background:#fff; cursor:pointer; transition:all .15s; }
        .btn-view:hover { background:#667eea; color:#fff; }

        /* Pagination */
        .pagination .page-link { border-radius:6px; margin:0 2px; border:none; background:#f8f9fa; color:#495057; font-weight:500; }
        .pagination .page-item.active .page-link { background:#667eea; color:white; box-shadow:0 2px 4px rgba(102,126,234,0.3); }
        .pagination .page-link:hover { background:#e9ecef; color:#495057; }
        .form-select-sm { border-radius:6px; border-color:#ced4da; font-size:13px; }

        /* Transfer detail modal */
        #transferModal .modal-header { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px 10px 0 0; }
        #transferModal .modal-header .btn-close { filter:invert(1); }
        #transferModal .modal-content { border-radius:10px; border:none; box-shadow:0 10px 40px rgba(0,0,0,.15); }
        .transfer-meta { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
        .meta-pill { background:#f3f4f6; border-radius:6px; padding:6px 12px; font-size:12.5px; }
        .meta-pill strong { color:#374151; }
        .meta-pill span { color:#6b7280; }
        .items-table th { background:#f8f9fa; font-size:12px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.4px; padding:8px 12px; }
        .items-table td { font-size:13px; padding:7px 12px; vertical-align:middle; }
        .items-table tbody tr:nth-child(even) { background:#fafafa; }
        .qty-cell { font-weight:700; color:#667eea; text-align:center; }

        /* Location picker */
        .lp-wrapper { position:relative; }
        .lp-control { display:flex; align-items:center; min-height:38px; border:1px solid #ced4da; border-radius:4px; background:#fff; padding:2px 8px; cursor:pointer; user-select:none; }
        .lp-control:hover { border-color:#adb5bd; }
        .lp-control.open { border-color:#86b7fe; box-shadow:0 0 0 0.2rem rgba(13,110,253,.25); }
        .lp-value { flex:1; display:flex; align-items:center; min-height:28px; padding:2px 0; font-size:13px; }
        .lp-placeholder { color:#aaa; font-size:13px; }
        .lp-clear { margin-left:4px; cursor:pointer; color:#999; font-size:18px; line-height:1; flex-shrink:0; }
        .lp-clear:hover { color:#dc3545; }
        .lp-arrow { margin-left:6px; display:flex; align-items:center; }
        .lp-panel { position:absolute; top:calc(100% + 3px); left:0; right:0; background:#fff; border:1px solid #ced4da; border-radius:4px; z-index:1050; box-shadow:0 4px 16px rgba(0,0,0,.12); display:flex; flex-direction:column; max-height:280px; }
        .lp-back { display:flex; align-items:center; gap:6px; padding:8px 12px; border-bottom:1px solid #e9ecef; cursor:pointer; font-size:13px; color:#0d6efd; flex-shrink:0; }
        .lp-back:hover { background:#f8f9fa; }
        .lp-body { overflow-y:auto; flex:1; }
        .lp-row { display:flex; align-items:center; gap:8px; padding:9px 14px; font-size:13px; border-bottom:1px solid #f5f5f5; cursor:pointer; }
        .lp-row:last-child { border-bottom:none; }
        .lp-row-nav { justify-content:space-between; padding:0; }
        .lp-row-select { flex:1; display:flex; align-items:center; gap:8px; padding:9px 14px; }
        .lp-row-select:hover { background:#f8f9fa; }
        .lp-row-into { display:flex; align-items:center; padding:9px 10px; border-left:1px solid #efefef; color:#aaa; flex-shrink:0; }
        .lp-row-into:hover { background:#f0f4ff; color:#1a73e8; }
        .lp-row-selectable { color:#333; }
        .lp-row-selectable:hover { background:#f8f9fa; }
        .lp-row-selected { background:#e8f0fe; color:#1a73e8; font-weight:500; }
        .lp-row-selected:hover { background:#d2e3fc; }
        .lp-row .lp-check { color:#1a73e8; flex-shrink:0; font-size:16px; }
        .lp-empty, .lp-loading { padding:16px; text-align:center; font-size:13px; color:#aaa; }
        .lp-tabs { display:flex; border-bottom:1px solid #e9ecef; flex-shrink:0; }
        .lp-tab { flex:1; padding:7px 6px; border:none; background:none; font-size:12px; font-weight:500; color:#666; cursor:pointer; border-bottom:2px solid transparent; transition:all .15s; display:flex; align-items:center; justify-content:center; gap:4px; }
        .lp-tab:hover { color:#1a73e8; background:#f8f9fa; }
        .lp-tab.lp-tab-active { color:#1a73e8; border-bottom-color:#1a73e8; background:#fff; }
        .lp-search-box { padding:7px 10px; border-bottom:1px solid #f0f0f0; flex-shrink:0; display:flex; align-items:center; gap:6px; }
        .lp-search-box input { flex:1; border:1px solid #ced4da; border-radius:4px; padding:5px 10px; font-size:13px; outline:none; font-family:inherit; }
        .lp-search-box input:focus { border-color:#86b7fe; box-shadow:0 0 0 .15rem rgba(13,110,253,.2); }
        .lp-result-path { font-size:11px; color:#999; margin-top:1px; line-height:1.3; }

        /* Select2 overrides */
        .select2-container--default .select2-selection--single { border-radius:4px; border:1px solid #ced4da; height:auto; padding:6px 10px; font-size:13px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:1.5; padding:0; color:#495057; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height:100%; top:0; right:6px; }
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single { border-color:#86b7fe; box-shadow:0 0 0 0.2rem rgba(13,110,253,0.25); }
        .select2-dropdown { border:1px solid #ced4da; border-radius:4px; box-shadow:0 4px 12px rgba(0,0,0,0.1); font-size:13px; }
        .select2-search--dropdown .select2-search__field { border:1px solid #ced4da; border-radius:4px; padding:5px 8px; }
        .select2-container { width:100% !important; }
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
                                        <td>Godown ↔ Location Transfers</td>
                                        <td>
                                            <a href="add-godown-to-location" title="Godown → Location" style="margin-right:8px;">&#8594;</a>
                                            <a href="add-location-to-godown" title="Location → Godown">&#8592;</a>
                                        </td>
                                    </tr></table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success mb-3">
                            <i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">check_circle</i>
                            Transfer <strong><?php echo htmlspecialchars($_GET['ref'] ?? ''); ?></strong> completed successfully.
                        </div>
                    <?php endif; ?>

                    <!-- Stat Cards -->
                    <div class="row">
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card purple">
                                <div><h3><?php echo $total; ?></h3><p>Total Transfers</p></div>
                                <i class="material-icons-outlined stat-icon">swap_horiz</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card blue">
                                <div><h3><?php echo $to_loc; ?></h3><p>Godown → Location</p></div>
                                <i class="material-icons-outlined stat-icon">arrow_forward</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card amber">
                                <div><h3><?php echo $to_godown; ?></h3><p>Location → Godown</p></div>
                                <i class="material-icons-outlined stat-icon">arrow_back</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card green">
                                <div><h3><?php echo number_format($total_qty); ?></h3><p>Total Units Moved</p></div>
                                <i class="material-icons-outlined stat-icon">inventory_2</i>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="d-flex gap-2 mb-3">
                        <a href="add-godown-to-location" class="btn-action purple">
                            <i class="material-icons" style="font-size:16px;">arrow_forward</i> Godown → Location
                        </a>
                        <a href="add-location-to-godown" class="btn-action amber">
                            <i class="material-icons" style="font-size:16px;">arrow_back</i> Location → Godown
                        </a>
                    </div>

                    <!-- Filter Card -->
                    <div class="card">
                        <div class="card-header">
                            <span class="card-header-title"><i class="material-icons-outlined">filter_list</i> Filters</span>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?= $_SERVER['PHP_SELF']; ?>" id="filterForm">

                                <div class="row mb-3">
                                    <!-- Location picker -->
                                    <div class="col-md-4 col-sm-6 mb-2">
                                        <label class="form-label" style="font-weight:600;font-size:13px;">Partner Location</label>
                                        <div class="lp-wrapper" id="filterLocWrapper">
                                            <div class="lp-control" id="filterLocControl">
                                                <div class="lp-value" id="filterLocValue">
                                                    <?php if ($selected_location_id > 0 && $selected_location_name !== ''): ?>
                                                        <span><?= htmlspecialchars($selected_location_name); ?></span>
                                                    <?php else: ?>
                                                        <span class="lp-placeholder">All Locations&hellip;</span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="lp-clear" id="filterLocClear" title="Clear" style="<?= $selected_location_id > 0 ? '' : 'display:none;'; ?>">&times;</span>
                                                <div class="lp-arrow"><i class="material-icons" style="font-size:18px;color:#999;">arrow_drop_down</i></div>
                                            </div>
                                            <div class="lp-panel" id="filterLocPanel" style="display:none;">
                                                <div class="lp-tabs">
                                                    <button type="button" class="lp-tab lp-tab-active" id="flpTabBrowse">
                                                        <i class="material-icons" style="font-size:14px;">account_tree</i> Browse
                                                    </button>
                                                    <button type="button" class="lp-tab" id="flpTabSearch">
                                                        <i class="material-icons" style="font-size:14px;">search</i> Search
                                                    </button>
                                                </div>
                                                <div id="flpBrowseControls">
                                                    <div class="lp-back" id="flpBack" style="display:none;">
                                                        <i class="material-icons" style="font-size:16px;">arrow_back</i>
                                                        <span id="flpBackName"></span>
                                                    </div>
                                                </div>
                                                <div id="flpSearchControls" style="display:none;">
                                                    <div class="lp-search-box">
                                                        <i class="material-icons" style="font-size:18px;color:#aaa;">search</i>
                                                        <input type="text" id="flpSearchInput" placeholder="Type to search locations&hellip;" autocomplete="off">
                                                    </div>
                                                </div>
                                                <div class="lp-body" id="flpBody">
                                                    <div class="lp-loading">Loading&hellip;</div>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="location_id" id="filterLocHidden" value="<?= $selected_location_id; ?>">
                                    </div>

                                    <!-- Channel Partner -->
                                    <div class="col-md-3 col-sm-6 mb-2">
                                        <label class="form-label" style="font-weight:600;font-size:13px;">Channel Partner</label>
                                        <select name="cp_id" id="cp_filter" class="form-control">
                                            <option value="">All Channel Partners</option>
                                            <?php foreach ($cp_list as $cp): ?>
                                            <option value="<?= htmlspecialchars($cp['cp_id']); ?>"
                                                <?= ($selected_cp_id === $cp['cp_id']) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($cp['name']); ?> (<?= htmlspecialchars($cp['cp_id']); ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Transfer type -->
                                    <div class="col-md-2 col-sm-6 mb-2">
                                        <label class="form-label" style="font-weight:600;font-size:13px;">Transfer Type</label>
                                        <select name="transfer_type" class="form-control" style="font-size:13px;">
                                            <option value="">All Types</option>
                                            <option value="godown_to_location" <?= $selected_type === 'godown_to_location' ? 'selected' : ''; ?>>Godown → Location</option>
                                            <option value="location_to_godown" <?= $selected_type === 'location_to_godown' ? 'selected' : ''; ?>>Location → Godown</option>
                                        </select>
                                    </div>

                                    <!-- Date From -->
                                    <div class="col-md-2 col-sm-6 mb-2">
                                        <label class="form-label" style="font-weight:600;font-size:13px;">Date From</label>
                                        <input type="date" name="date_from" class="form-control" style="font-size:13px;"
                                               value="<?= htmlspecialchars($date_from); ?>">
                                    </div>

                                    <!-- Date To -->
                                    <div class="col-md-2 col-sm-6 mb-2">
                                        <label class="form-label" style="font-weight:600;font-size:13px;">Date To</label>
                                        <input type="date" name="date_to" class="form-control" style="font-size:13px;"
                                               value="<?= htmlspecialchars($date_to); ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12">
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button type="submit" name="apply_filters" class="btn btn-primary">
                                                <i class="material-icons" style="font-size:16px;vertical-align:middle;">filter_list</i> Apply Filters
                                            </button>
                                            <button type="submit" name="clear_all" class="btn btn-secondary">
                                                <i class="material-icons" style="font-size:16px;vertical-align:middle;">refresh</i> Reset All
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($active_filters)): ?>
                                <div class="alert alert-success mt-3 mb-0">
                                    <strong>Active Filters:</strong> <?= implode(' | ', $active_filters); ?>
                                </div>
                                <?php endif; ?>

                            </form>
                        </div>
                    </div>

                    <!-- Table Card -->
                    <div class="card">
                        <div class="card-header">
                            <span class="card-header-title"><i class="material-icons-outlined">history</i> Transfer History</span>
                        </div>

                        <!-- Toolbar -->
                        <div class="card-header py-3 bg-white border-0" style="border-radius:0 !important;">
                            <div class="d-flex align-items-center justify-content-between">
                                <!-- Show entries -->
                                <form method="post" action="<?= $_SERVER['PHP_SELF']; ?>" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="location_id" value="<?= $selected_location_id; ?>">
                                    <input type="hidden" name="transfer_type" value="<?= htmlspecialchars($selected_type); ?>">
                                    <input type="hidden" name="cp_id" value="<?= htmlspecialchars($selected_cp_id, ENT_QUOTES); ?>">
                                    <input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from); ?>">
                                    <input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to); ?>">
                                    <input type="hidden" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES); ?>">
                                    <label class="mb-0 text-muted" style="font-size:13px;">Show:</label>
                                    <select name="records_per_page" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                                        <option value="20"  <?= $records_per_page == 20  ? 'selected' : ''; ?>>20</option>
                                        <option value="40"  <?= $records_per_page == 40  ? 'selected' : ''; ?>>40</option>
                                        <option value="60"  <?= $records_per_page == 60  ? 'selected' : ''; ?>>60</option>
                                        <option value="100" <?= $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                                    </select>
                                    <label class="mb-0 text-muted" style="font-size:13px;">entries</label>
                                </form>

                                <!-- Search + page info -->
                                <div class="d-flex align-items-center gap-3">
                                    <form method="get" action="<?= $_SERVER['PHP_SELF']; ?>" class="d-flex align-items-center gap-2">
                                        <input type="hidden" name="page" value="1">
                                        <input type="text" name="q"
                                               value="<?= htmlspecialchars($search, ENT_QUOTES); ?>"
                                               class="form-control form-control-sm"
                                               placeholder="Search ref, godown, location, user…"
                                               style="min-width:280px;font-size:13px;">
                                    </form>
                                    <div class="text-muted small">
                                        <?php if ($is_search): ?>
                                            Showing all matches
                                        <?php else: ?>
                                            Page <?= $page; ?> of <?= $total_pages; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <?php if (!empty($transfers)): ?>
                            <div style="overflow-x:auto;">
                                <table class="table table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Ref #</th>
                                            <th>Type</th>
                                            <th>Godown</th>
                                            <th>Partner Location</th>
                                            <th>Date</th>
                                            <th style="text-align:center;">Products</th>
                                            <th style="text-align:center;">Total Qty</th>
                                            <th>Created By</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($transfers as $t): ?>
                                        <tr>
                                            <td style="color:#9ca3af;"><?php echo $offset + (++$i); ?></td>
                                            <td><code style="font-size:12px;background:#f3f4f6;padding:2px 7px;border-radius:4px;"><?php echo htmlspecialchars($t['ref_number']); ?></code></td>
                                            <td>
                                                <?php if ($t['transfer_type'] === 'godown_to_location'): ?>
                                                    <span class="type-badge-in">Godown → Location</span>
                                                <?php else: ?>
                                                    <span class="type-badge-out">Location → Godown</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($t['godown_name']); ?></td>
                                            <td><?php echo htmlspecialchars($t['location_name']); ?></td>
                                            <td style="color:#6b7280;"><?php echo htmlspecialchars($t['transfer_date']); ?></td>
                                            <td style="text-align:center;font-weight:600;"><?php echo (int)$t['product_count']; ?></td>
                                            <td style="text-align:center;font-weight:600;color:#667eea;"><?php echo number_format((int)$t['total_qty']); ?></td>
                                            <td style="color:#6b7280;"><?php echo htmlspecialchars($t['created_by']); ?></td>
                                            <td>
                                                <button class="btn-view view-transfer" data-id="<?php echo (int)$t['id']; ?>">
                                                    <i class="material-icons" style="font-size:15px;">visibility</i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if (!$is_search && $total_pages > 1): ?>
                            <nav class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1; ?>&q=<?= $qparam; ?>">Previous</a>
                                    </li>
                                    <?php endif; ?>
                                    <?php
                                    $sp = max(1, $page - 2);
                                    $ep = min($total_pages, $page + 2);
                                    if ($sp > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?page=1&q=' . $qparam . '">1</a></li>';
                                        if ($sp > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    }
                                    for ($pg = $sp; $pg <= $ep; $pg++):
                                    ?>
                                    <li class="page-item <?= ($pg == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?= $pg; ?>&q=<?= $qparam; ?>"><?= $pg; ?></a>
                                    </li>
                                    <?php
                                    endfor;
                                    if ($ep < $total_pages) {
                                        if ($ep < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&q=' . $qparam . '">' . $total_pages . '</a></li>';
                                    }
                                    ?>
                                    <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1; ?>&q=<?= $qparam; ?>">Next</a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>

                            <p class="text-center text-muted mt-2" style="font-size:13px;">
                                <?php if ($is_search): ?>
                                    Showing all <?= $total; ?> matching entries
                                <?php else: ?>
                                    Showing <?= $offset + 1; ?> to <?= min($offset + $records_per_page, $total); ?> of <?= $total; ?> entries
                                <?php endif; ?>
                            </p>

                            <?php else: ?>
                                <div class="alert alert-warning">No transfers found for the selected filters.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transfer Detail Modal -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transferModalTitle" style="font-size:15px;font-weight:600;">Transfer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transferModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" style="width:1.8rem;height:1.8rem;"></div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                <a href="#" id="printTransferBtn" target="_blank" class="btn btn-dark" style="display:none;">
                    <i class="material-icons" style="font-size:16px;vertical-align:middle;">print</i> Print Receipt
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
// ── Select2 init ──────────────────────────────────────────────────────────────
$(function () {
    $('#cp_filter').select2({ placeholder: 'All Channel Partners', allowClear: true, width: '100%' });
});

// ── Transfer detail modal ──────────────────────────────────────────────────────
$(function () {
    $(document).on('click', '.view-transfer', function () {
        var id = $(this).data('id');
        var $body = $('#transferModalBody');
        $body.html('<div class="text-center py-4"><div class="spinner-border text-primary" style="width:1.8rem;height:1.8rem;"></div></div>');
        $('#printTransferBtn').hide().attr('href', 'pl-godown-transfer-print.php?id=' + id);
        $('#transferModal').modal('show');

        $.getJSON('get-transfer-items.php', { id: id }, function (d) {
            if (d.error) { $body.html('<div class="alert alert-danger">' + d.error + '</div>'); return; }
            var t = d.transfer;
            var typeLabel = t.transfer_type === 'godown_to_location'
                ? '<span class="type-badge-in">Godown → Location</span>'
                : '<span class="type-badge-out">Location → Godown</span>';

            $('#transferModalTitle').html(
                '<i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:6px;">swap_horiz</i>'
                + 'Ref: <code style="font-size:13px;background:rgba(255,255,255,.2);padding:2px 8px;border-radius:4px;">' + t.ref_number + '</code>'
            );

            var metaHtml = '<div class="transfer-meta">'
                + '<div class="meta-pill"><strong>Type</strong><br>' + typeLabel + '</div>'
                + '<div class="meta-pill"><strong>Godown</strong><br><span>' + t.godown_name + '</span></div>'
                + '<div class="meta-pill"><strong>Location</strong><br><span>' + t.location_name + '</span></div>'
                + '<div class="meta-pill"><strong>Date</strong><br><span>' + t.transfer_date + '</span></div>'
                + '<div class="meta-pill"><strong>Created By</strong><br><span>' + t.created_by + '</span></div>'
                + (t.note ? '<div class="meta-pill"><strong>Note</strong><br><span>' + t.note + '</span></div>' : '')
                + '</div>';

            var rows = '', grandTotal = 0;
            $.each(d.items, function (i, item) {
                grandTotal += parseInt(item.quantity, 10);
                rows += '<tr>'
                    + '<td style="color:#9ca3af;">' + (i + 1) + '</td>'
                    + '<td>' + item.product_name + '</td>'
                    + '<td class="qty-cell">' + parseInt(item.quantity, 10).toLocaleString() + '</td>'
                    + '</tr>';
            });

            var tableHtml = '<div style="overflow-x:auto;">'
                + '<table class="table table-bordered items-table mb-0">'
                + '<thead><tr><th style="width:40px;">#</th><th>Product</th><th style="width:100px;text-align:center;">Qty</th></tr></thead>'
                + '<tbody>' + rows + '</tbody>'
                + '<tfoot><tr>'
                + '<td colspan="2" style="font-weight:600;text-align:right;">Total</td>'
                + '<td class="qty-cell" style="font-size:15px;">' + grandTotal.toLocaleString() + '</td>'
                + '</tr></tfoot>'
                + '</table></div>';

            $body.html(metaHtml + tableHtml);
            $('#printTransferBtn').show();
        }).fail(function () {
            $body.html('<div class="alert alert-danger">Failed to load transfer details.</div>');
        });
    });
});
</script>
<script>
// ── Location picker ───────────────────────────────────────────────────────────
(function ($) {
    var stack = [], selected = null, open = false, loading = false, searchMode = false, searchTimer = null;

    function escHtml(s) { return $('<div>').text(s).html(); }

    function loadLevel(parent_id, parent_name, parent_layer) {
        if (loading) return;
        loading = true;
        renderLoading();
        $.getJSON('get-location-nodes.php?filter_type=tp&exclude_cp_id=0&parent_id=' + (parent_id !== null ? parent_id : ''), function (nodes) {
            stack.push({ parent_id: parent_id, parent_name: parent_name, parent_layer: parent_layer || null, nodes: nodes });
            renderPanel();
            loading = false;
        }).fail(function () {
            $('#flpBody').html('<div class="lp-empty">Failed to load. Please try again.</div>');
            loading = false;
        });
    }

    function goBack() { if (stack.length <= 1) return; stack.pop(); renderPanel(); }

    function selectNode(node) {
        selected = { id: node.id, name: node.name };
        $('#filterLocHidden').val(node.id);
        renderControl();
        closePanel();
    }

    function clearSelection() {
        selected = null;
        $('#filterLocHidden').val('');
        renderControl();
    }

    function renderControl() {
        var $val = $('#filterLocValue').empty();
        if (selected) {
            $val.append($('<span>').text(selected.name));
            $('#filterLocClear').show();
        } else {
            $val.html('<span class="lp-placeholder">All Locations&hellip;</span>');
            $('#filterLocClear').hide();
        }
    }

    function renderLoading() { $('#flpBody').html('<div class="lp-loading">Loading&hellip;</div>'); }

    function renderPanel() {
        if (!stack.length) return;
        var frame = stack[stack.length - 1];
        if (stack.length > 1) {
            var prev = stack[stack.length - 2];
            var backLabel = prev.parent_name
                ? (prev.parent_layer ? prev.parent_layer + ': ' + prev.parent_name : prev.parent_name)
                : 'Top Level';
            $('#flpBack').show().find('#flpBackName').text(backLabel);
        } else {
            $('#flpBack').hide();
        }
        var $body = $('#flpBody').empty();
        if (!frame.nodes || !frame.nodes.length) { $body.html('<div class="lp-empty">No locations at this level.</div>'); return; }
        $.each(frame.nodes, function (_, node) {
            var isStock = !!node.is_tp_filter_enabled, isSel = selected && selected.id === node.id;
            var $row = $('<div class="lp-row"></div>');
            if (!node.is_leaf) {
                $row.addClass('lp-row-nav');
                var $sel = $('<div class="lp-row-select"></div>');
                if (isStock) {
                    if (isSel) $sel.addClass('lp-row-selected').prepend('<i class="material-icons lp-check" style="font-size:16px;">check</i>');
                    $sel.append($('<span>').text(node.name));
                    $sel.on('click', function (e) { e.stopPropagation(); selectNode(node); });
                } else {
                    $sel.append($('<span>').text(node.name));
                }
                var $into = $('<div class="lp-row-into" title="Browse deeper"><i class="material-icons" style="font-size:18px;">chevron_right</i></div>');
                $into.on('click', function (e) { e.stopPropagation(); loadLevel(node.id, node.name, node.layer_name || null); });
                $row.append($sel).append($into);
            } else if (isStock) {
                $row.addClass(isSel ? 'lp-row-selectable lp-row-selected' : 'lp-row-selectable');
                if (isSel) $row.append('<i class="material-icons lp-check" style="font-size:16px;">check</i>');
                $row.append($('<span>').text(node.name));
                $row.on('click', function () { selectNode(node); });
            } else {
                $row.css({ color:'#bbb', cursor:'default' }).append($('<span>').text(node.name));
            }
            $body.append($row);
        });
    }

    function openPanel() {
        open = true;
        $('#filterLocControl').addClass('open');
        $('#filterLocPanel').show();
        if (stack.length === 0) loadLevel(null, null);
    }

    function closePanel() {
        open = false;
        $('#filterLocControl').removeClass('open');
        $('#filterLocPanel').hide();
    }

    $('#filterLocControl').on('click', function (e) { e.stopPropagation(); open ? closePanel() : openPanel(); });
    $('#filterLocPanel').on('click', function (e) { e.stopPropagation(); });
    $(document).on('click', function (e) { if (!$(e.target).closest('#filterLocWrapper').length && open) closePanel(); });
    $('#filterLocClear').on('click', function (e) { e.stopPropagation(); clearSelection(); });
    $('#flpBack').on('click', function (e) { e.stopPropagation(); goBack(); });

    $('#flpTabBrowse').on('click', function (e) {
        e.stopPropagation();
        if (!searchMode) return;
        searchMode = false;
        $('#flpTabBrowse').addClass('lp-tab-active'); $('#flpTabSearch').removeClass('lp-tab-active');
        $('#flpSearchControls').hide(); $('#flpBrowseControls').show();
        if (stack.length === 0) loadLevel(null, null); else renderPanel();
    });

    $('#flpTabSearch').on('click', function (e) {
        e.stopPropagation();
        if (searchMode) return;
        searchMode = true;
        $('#flpTabSearch').addClass('lp-tab-active'); $('#flpTabBrowse').removeClass('lp-tab-active');
        $('#flpBrowseControls').hide(); $('#flpSearchControls').show(); $('#flpBack').hide();
        $('#flpBody').html('<div class="lp-empty">Type to search locations&hellip;</div>');
        setTimeout(function () { $('#flpSearchInput').focus(); }, 50);
    });

    $('#flpSearchInput').on('input', function () {
        var q = $.trim($(this).val());
        clearTimeout(searchTimer);
        if (!q) { $('#flpBody').html('<div class="lp-empty">Type to search locations&hellip;</div>'); return; }
        $('#flpBody').html('<div class="lp-loading">Searching&hellip;</div>');
        searchTimer = setTimeout(function () {
            $.getJSON('search-location-nodes.php?filter_type=tp&exclude_cp_id=0&exclude_tp_id=0&q=' + encodeURIComponent(q), function (results) {
                var $body = $('#flpBody').empty();
                if (!results || !results.length) { $body.html('<div class="lp-empty">No locations found.</div>'); return; }
                $.each(results, function (_, node) {
                    var isStock = !!node.is_tp_filter_enabled, isSel = selected && selected.id === node.id;
                    var $row = $('<div class="lp-row"></div>');
                    var $info = $('<div style="flex:1;min-width:0;"></div>').append($('<div>').text(node.name))
                                                                            .append($('<div class="lp-result-path">').text(node.path));
                    if (isStock) {
                        $row.addClass(isSel ? 'lp-row-selectable lp-row-selected' : 'lp-row-selectable');
                        if (isSel) $row.append('<i class="material-icons lp-check" style="font-size:16px;">check</i>');
                        $row.append($info);
                        $row.on('click', function () { selectNode(node); });
                    } else {
                        $row.css({ color:'#bbb', cursor:'default' }).append($info);
                    }
                    $body.append($row);
                });
            }).fail(function () { $('#flpBody').html('<div class="lp-empty">Search failed.</div>'); });
        }, 280);
    }).on('click', function (e) { e.stopPropagation(); });

    // Restore pre-selected location from session
    <?php if ($selected_location_id > 0 && $selected_location_name !== ''): ?>
    selected = { id: <?= $selected_location_id; ?>, name: <?= json_encode($selected_location_name); ?> };
    <?php endif; ?>

}(jQuery));
</script>
</body>
</html>
