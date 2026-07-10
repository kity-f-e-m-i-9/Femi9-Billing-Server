<?php
ob_start();
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
include("config.php");

// Clear all filters
if (isset($_GET['clear_filters']) || isset($_POST['clear_all'])) {
    ob_clean();
    unset($_SESSION['tpstock_filter_type']);
    unset($_SESSION['tpstock_tp_id']);
    unset($_SESSION['tpstock_location_id']);
    unset($_SESSION['tpstock_records_per_page']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

mysqli_set_charset($db_conn, 'utf8mb4');

// Filter type — 'location' or 'tp'
$filter_type = 'location';
if (isset($_POST['filter_type'])) {
    $filter_type = $_POST['filter_type'] === 'tp' ? 'tp' : 'location';
    $_SESSION['tpstock_filter_type'] = $filter_type;
} elseif (isset($_SESSION['tpstock_filter_type'])) {
    $filter_type = $_SESSION['tpstock_filter_type'];
}

// Location filter (only active when filter_type = location)
$selected_location_id   = 0;
$selected_location_name = '';
if ($filter_type === 'location') {
    if (isset($_POST['location_id'])) {
        $selected_location_id = (int)$_POST['location_id'];
        if ($selected_location_id) $_SESSION['tpstock_location_id'] = $selected_location_id;
        else unset($_SESSION['tpstock_location_id']);
    } elseif (isset($_SESSION['tpstock_location_id'])) {
        $selected_location_id = (int)$_SESSION['tpstock_location_id'];
    }
    if ($selected_location_id > 0) {
        $ln_res = $db_conn->query("SELECT name FROM partner_location_nodes WHERE id = $selected_location_id LIMIT 1");
        if ($ln_res && $ln_row = $ln_res->fetch_assoc()) $selected_location_name = $ln_row['name'];
    }
}

// TP filter (only active when filter_type = tp)
$selected_tp_id   = '';
$selected_tp_name = '';
if ($filter_type === 'tp') {
    if (isset($_POST['tp_id'])) {
        $selected_tp_id = trim($_POST['tp_id']);
        if ($selected_tp_id) $_SESSION['tpstock_tp_id'] = $selected_tp_id;
        else unset($_SESSION['tpstock_tp_id']);
    } elseif (isset($_SESSION['tpstock_tp_id'])) {
        $selected_tp_id = $_SESSION['tpstock_tp_id'];
    }
    if ($selected_tp_id !== '') {
        $esc = $db_conn->real_escape_string($selected_tp_id);
        $tn_res = $db_conn->query("SELECT name FROM territory_partners WHERE tp_id = '$esc' LIMIT 1");
        if ($tn_res && $tn_row = $tn_res->fetch_assoc()) $selected_tp_name = $tn_row['name'];
    }
}

// Pagination
$records_per_page = 20;
if (isset($_POST['records_per_page'])) {
    $records_per_page = (int)$_POST['records_per_page'];
    $_SESSION['tpstock_records_per_page'] = $records_per_page;
} elseif (isset($_SESSION['tpstock_records_per_page'])) {
    $records_per_page = (int)$_SESSION['tpstock_records_per_page'];
}
if (!in_array($records_per_page, [20, 40, 60, 100])) $records_per_page = 20;

$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

$has_filter = ($filter_type === 'location' && $selected_location_id > 0)
           || ($filter_type === 'tp'       && !empty($selected_tp_id));

$products      = [];
$tp_rows       = [];
$stock_data    = [];
$total_records = 0;
$total_pages   = 1;

if ($has_filter) {
    $where_parts = ["tp.is_active = 1"];
    if ($filter_type === 'location') {
        $where_parts[] = "EXISTS (SELECT 1 FROM territory_partner_locations tpl2 WHERE tpl2.territory_partner_id = tp.id AND tpl2.location_id = $selected_location_id)";
    } else {
        $esc_tp = $db_conn->real_escape_string($selected_tp_id);
        $where_parts[] = "tp.tp_id = '$esc_tp'";
    }
    $where_sql = implode(' AND ', $where_parts);

    // Products
    $prod_res = mysqli_query($db_conn, "SELECT id, productName FROM products ORDER BY id ASC");
    if ($prod_res) while ($pr = mysqli_fetch_assoc($prod_res)) $products[$pr['id']] = $pr['productName'];

    // Count
    $count_res = mysqli_query($db_conn, "
        SELECT COUNT(DISTINCT tp.id) AS total
        FROM territory_partners tp
        LEFT JOIN territory_partner_stock tps ON tps.territory_partner_id = tp.id
        WHERE $where_sql AND tp.stock_initialized = 1
    ");
    $total_records = $count_res ? (int)mysqli_fetch_assoc($count_res)['total'] : 0;
    $total_pages   = max(1, (int)ceil($total_records / $records_per_page));

    // Paginated list
    $list_result = mysqli_query($db_conn, "
        SELECT tp.id AS tp_db_id, tp.tp_id, tp.name AS tp_name, tp.mobile
        FROM territory_partners tp
        LEFT JOIN territory_partner_stock tps ON tps.territory_partner_id = tp.id
        WHERE $where_sql AND tp.stock_initialized = 1
        GROUP BY tp.id
        ORDER BY tp.name ASC
        LIMIT " . (int)$records_per_page . " OFFSET " . (int)$offset
    );
    if ($list_result) while ($row = mysqli_fetch_assoc($list_result)) $tp_rows[] = $row;

    // Stock quantities
    if (!empty($tp_rows)) {
        $tp_db_ids = implode(',', array_map(fn($r) => (int)$r['tp_db_id'], $tp_rows));
        $stk_res   = mysqli_query($db_conn, "
            SELECT territory_partner_id, product_id, input_qty, closing_qty
            FROM territory_partner_stock
            WHERE territory_partner_id IN ($tp_db_ids)
        ");
        if ($stk_res) while ($row = mysqli_fetch_assoc($stk_res))
            $stock_data[$row['territory_partner_id']][$row['product_id']] = $row;
    }
}

// TP list for dropdown
$tp_list = [];
$tp_list_res = mysqli_query($db_conn, "SELECT tp_id, name FROM territory_partners WHERE is_active = 1 ORDER BY name ASC LIMIT 1000");
if ($tp_list_res) while ($r = mysqli_fetch_assoc($tp_list_res)) $tp_list[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Stock : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">

    <style>
        #overflowon { width: 100%; overflow-x: auto; }

        .table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 600; white-space: nowrap; font-size: 14px;
            padding: 12px 8px; border-color: #dee2e6; color: #495057;
        }
        .table td {
            white-space: nowrap; font-size: 13px; padding: 10px 8px;
            vertical-align: middle; border-color: #dee2e6;
        }
        .product-col {
            background: linear-gradient(135deg, #e3f2fd 0%, #f0f8ff 100%);
            text-align: center; min-width: 80px; font-weight: 500;
        }
        .stock-positive { color: #28a745; font-weight: 600; }
        .stock-zero     { color: #dc3545; font-weight: 600; }
        .stock-low      { color: #ffc107; font-weight: 600; }
        .table-bordered { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
        .table-hover tbody tr:hover { background-color: rgba(0,123,255,0.05); }

        .card { border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-radius: 12px; }
        .card-header { background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%); border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0; }

        .pagination .page-link { border-radius: 6px; margin: 0 2px; border: none; background: #f8f9fa; color: #495057; font-weight: 500; }
        .pagination .page-item.active .page-link { background: #0d6efd; color: #fff; box-shadow: 0 2px 4px rgba(13,110,253,.3); }
        .pagination .page-link:hover { background: #e9ecef; }

        .filter-toggle-group .btn { display: inline-flex; align-items: center; gap: 5px; font-size: 14px; }
        .filter-input-area { margin-top: 14px; }
        .filter-hint { font-size: 12px; color: #6c757d; margin-top: 4px; }

        /* Location picker */
        .lp-wrapper { position: relative; }
        .lp-control {
            display: flex; align-items: center; min-height: 38px;
            border: 1px solid #ced4da; border-radius: 4px; background: #fff;
            padding: 2px 8px; cursor: pointer; user-select: none;
        }
        .lp-control:hover { border-color: #adb5bd; }
        .lp-control.open { border-color: #86b7fe; box-shadow: 0 0 0 .2rem rgba(13,110,253,.25); }
        .lp-value { flex: 1; display: flex; align-items: center; min-height: 28px; font-size: 13px; }
        .lp-placeholder { color: #aaa; font-size: 13px; }
        .lp-clear { margin-left: 4px; cursor: pointer; color: #999; font-size: 18px; line-height: 1; flex-shrink: 0; }
        .lp-clear:hover { color: #dc3545; }
        .lp-arrow { margin-left: 6px; display: flex; align-items: center; }
        .lp-panel {
            position: absolute; top: calc(100% + 3px); left: 0; right: 0;
            background: #fff; border: 1px solid #ced4da; border-radius: 4px;
            z-index: 1050; box-shadow: 0 4px 16px rgba(0,0,0,.12);
            display: flex; flex-direction: column; max-height: 280px;
        }
        .lp-search-box {
            padding: 7px 10px; border-bottom: 1px solid #f0f0f0;
            flex-shrink: 0; display: flex; align-items: center; gap: 6px;
        }
        .lp-search-box input {
            flex: 1; border: 1px solid #ced4da; border-radius: 4px;
            padding: 5px 10px; font-size: 13px; outline: none; font-family: inherit;
        }
        .lp-search-box input:focus { border-color: #86b7fe; box-shadow: 0 0 0 .15rem rgba(13,110,253,.2); }
        .lp-body { overflow-y: auto; flex: 1; }
        .lp-row {
            display: flex; align-items: center; gap: 8px;
            padding: 9px 14px; font-size: 13px;
            border-bottom: 1px solid #f5f5f5; cursor: pointer;
        }
        .lp-row:last-child { border-bottom: none; }
        .lp-row-selectable:hover { background: #f8f9fa; }
        .lp-row-selected { background: #e8f0fe; color: #1a73e8; font-weight: 500; }
        .lp-row-selected:hover { background: #d2e3fc; }
        .lp-check { color: #1a73e8; flex-shrink: 0; font-size: 16px; }
        .lp-result-path { font-size: 11px; color: #999; margin-top: 1px; line-height: 1.3; }
        .lp-empty, .lp-loading { padding: 16px; text-align: center; font-size: 13px; color: #aaa; }

        /* Select2 overrides */
        .select2-container--default .select2-selection--single {
            border-radius: 4px; border: 1px solid #ced4da;
            height: auto; padding: 6px 10px; font-size: 0.875rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.5; padding: 0; color: #495057; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 100%; top: 0; right: 6px; }
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single { border-color: #86b7fe; box-shadow: 0 0 0 .2rem rgba(13,110,253,.25); }
        .select2-dropdown { border: 1px solid #ced4da; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,.1); font-size: .875rem; }
        .select2-search--dropdown .select2-search__field { border: 1px solid #ced4da; border-radius: 4px; padding: 5px 8px; }
        .select2-container { width: 100% !important; }

        .active-filter-bar {
            background: #eff6ff; border-left: 4px solid #3b82f6;
            border-radius: 6px; padding: 8px 14px;
            font-size: 13px; display: flex; align-items: center; gap: 10px;
        }
        .no-filter-notice {
            background: #f8f9fa; border: 1px dashed #dee2e6;
            border-radius: 8px; padding: 32px; text-align: center; color: #6c757d;
        }
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

                    <!-- Header -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td>TP Stock</td>
                                            <td><a href="manage-territory-partner">&#8592;&nbsp;Territory&nbsp;Partners</a></td>
                                            <td><a href="add-tp-input-stock">+ Add Input Stock</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Card -->
                    <div class="row mb-3">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <form method="post" action="<?= $_SERVER['PHP_SELF']; ?>" id="filterForm">
                                        <input type="hidden" name="filter_type" id="filterTypeInput" value="<?= htmlspecialchars($filter_type); ?>">

                                        <!-- Toggle -->
                                        <div class="filter-toggle-group">
                                            <div class="btn-group" role="group">
                                                <button type="button"
                                                        class="btn <?= $filter_type === 'location' ? 'btn-primary' : 'btn-outline-primary'; ?>"
                                                        id="toggleLocation" onclick="setFilterType('location')">
                                                    <i class="material-icons" style="font-size:16px;">location_on</i>
                                                    By Location
                                                </button>
                                                <button type="button"
                                                        class="btn <?= $filter_type === 'tp' ? 'btn-primary' : 'btn-outline-primary'; ?>"
                                                        id="toggleTP" onclick="setFilterType('tp')">
                                                    <i class="material-icons" style="font-size:16px;">person_pin</i>
                                                    By Territory Partner
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Location picker (search-only) -->
                                        <div class="filter-input-area" id="locationFilterArea"
                                             style="<?= $filter_type !== 'location' ? 'display:none;' : ''; ?>">
                                            <label class="form-label mb-1">Stock Location</label>
                                            <div style="max-width: 400px;">
                                                <div class="lp-wrapper" id="filterLocWrapper">
                                                    <div class="lp-control" id="filterLocControl">
                                                        <div class="lp-value" id="filterLocValue">
                                                            <?php if ($selected_location_id > 0 && $selected_location_name !== ''): ?>
                                                                <span><?= htmlspecialchars($selected_location_name); ?></span>
                                                            <?php else: ?>
                                                                <span class="lp-placeholder">Search locations&hellip;</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="lp-clear" id="filterLocClear" title="Clear"
                                                              style="<?= $selected_location_id > 0 ? '' : 'display:none;'; ?>">&times;</span>
                                                        <div class="lp-arrow">
                                                            <i class="material-icons" style="font-size:18px;color:#999;">arrow_drop_down</i>
                                                        </div>
                                                    </div>
                                                    <div class="lp-panel" id="filterLocPanel" style="display:none;">
                                                        <div class="lp-search-box">
                                                            <i class="material-icons" style="font-size:18px;color:#aaa;">search</i>
                                                            <input type="text" id="flpSearchInput"
                                                                   placeholder="Type to search locations&hellip;"
                                                                   autocomplete="off">
                                                        </div>
                                                        <div class="lp-body" id="flpBody">
                                                            <div class="lp-empty">Type to search locations&hellip;</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="location_id" id="filterLocHidden"
                                                       value="<?= $selected_location_id; ?>">
                                            </div>
                                            <div class="filter-hint">Select a location to see all TP stock there.</div>
                                        </div>

                                        <!-- TP select -->
                                        <div class="filter-input-area" id="tpFilterArea"
                                             style="<?= $filter_type !== 'tp' ? 'display:none;' : ''; ?>">
                                            <label class="form-label mb-1">Territory Partner</label>
                                            <div style="max-width: 400px;">
                                                <select name="tp_id" id="tp_filter" class="form-control">
                                                    <option value="">— Choose a Territory Partner —</option>
                                                    <?php foreach ($tp_list as $tp): ?>
                                                    <option value="<?= htmlspecialchars($tp['tp_id']); ?>"
                                                        <?= ($selected_tp_id === $tp['tp_id']) ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($tp['name']); ?> (<?= htmlspecialchars($tp['tp_id']); ?>)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="filter-hint">Select a TP to see their stock.</div>
                                        </div>

                                        <!-- Reset -->
                                        <div class="mt-3">
                                            <button type="submit" name="clear_all" class="btn btn-sm btn-outline-secondary">
                                                <i class="material-icons" style="font-size:15px;vertical-align:middle;">refresh</i>
                                                Reset
                                            </button>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Results -->
                    <div class="row">
                        <div class="col">
                            <div class="card">

                                <?php if ($has_filter && !empty($tp_rows)): ?>

                                <!-- Toolbar -->
                                <div class="card-header py-3">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

                                        <form method="post" action="<?= $_SERVER['PHP_SELF']; ?>"
                                              class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="filter_type" value="<?= htmlspecialchars($filter_type); ?>">
                                            <input type="hidden" name="location_id" value="<?= $selected_location_id; ?>">
                                            <input type="hidden" name="tp_id" value="<?= htmlspecialchars($selected_tp_id, ENT_QUOTES); ?>">
                                            <label class="mb-0 text-muted">Show:</label>
                                            <select name="records_per_page" class="form-select form-select-sm"
                                                    style="width:auto" onchange="this.form.submit()">
                                                <option value="20"  <?= $records_per_page == 20  ? 'selected' : ''; ?>>20</option>
                                                <option value="40"  <?= $records_per_page == 40  ? 'selected' : ''; ?>>40</option>
                                                <option value="60"  <?= $records_per_page == 60  ? 'selected' : ''; ?>>60</option>
                                                <option value="100" <?= $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                                            </select>
                                            <label class="mb-0 text-muted">entries</label>
                                        </form>

                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <?php if ($filter_type === 'location' && $selected_location_name): ?>
                                            <div class="active-filter-bar">
                                                <i class="material-icons" style="font-size:16px;color:#3b82f6;">location_on</i>
                                                <strong><?= htmlspecialchars($selected_location_name); ?></strong>
                                                <span class="text-muted">&mdash; <?= $total_records; ?> TP<?= $total_records !== 1 ? 's' : ''; ?></span>
                                            </div>
                                            <?php elseif ($filter_type === 'tp' && $selected_tp_name): ?>
                                            <div class="active-filter-bar">
                                                <i class="material-icons" style="font-size:16px;color:#3b82f6;">person_pin</i>
                                                <strong><?= htmlspecialchars($selected_tp_name); ?></strong>
                                            </div>
                                            <?php endif; ?>
                                            <div class="text-muted small">
                                                Page <?= $page; ?> of <?= $total_pages; ?>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="card-body px-3 pt-0 pb-2">
                                    <div id="overflowon">
                                        <table class="table table-bordered table-hover table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th rowspan="2">#</th>
                                                    <th rowspan="2">Territory Partner</th>
                                                    <th colspan="<?= count($products); ?>"
                                                        style="text-align:center; background:#e3f2fd;">
                                                        Product Stock (Closing Qty)
                                                    </th>
                                                    <th rowspan="2">Total</th>
                                                </tr>
                                                <tr>
                                                    <?php foreach ($products as $pr_id => $pr_name): ?>
                                                    <th class="product-col" title="<?= htmlspecialchars($pr_name); ?>">
                                                        <?= htmlspecialchars(strlen($pr_name) > 18 ? substr($pr_name,0,15).'...' : $pr_name); ?>
                                                    </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $serial         = $offset + 1;
                                            $product_totals = array_fill_keys(array_keys($products), 0);
                                            $grand_total    = 0;
                                            foreach ($tp_rows as $tp):
                                                $tp_db_id  = (int)$tp['tp_db_id'];
                                                $tp_stock  = $stock_data[$tp_db_id] ?? [];
                                                $row_total = 0;
                                            ?>
                                            <tr>
                                                <td class="text-muted"><?= $serial++; ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($tp['tp_name']); ?></strong><br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($tp['tp_id']); ?>
                                                        &nbsp;&middot;&nbsp;
                                                        <?= htmlspecialchars($tp['mobile']); ?>
                                                    </small>
                                                </td>

                                                <?php foreach ($products as $pr_id => $pr_name):
                                                    $stk     = $tp_stock[$pr_id] ?? null;
                                                    $closing = $stk ? (int)$stk['closing_qty'] : 0;
                                                    $product_totals[$pr_id] += $closing;
                                                    $row_total += $closing;
                                                    $cls = 'stock-zero';
                                                    if ($closing > 10)    $cls = 'stock-positive';
                                                    elseif ($closing > 0) $cls = 'stock-low';
                                                ?>
                                                <td align="center" class="product-col"
                                                    title="Input: <?= $stk ? (int)$stk['input_qty'] : 0; ?> | Closing: <?= $closing; ?>">
                                                    <?php if ($closing > 0): ?>
                                                        <strong class="<?= $cls; ?>"><?= $closing; ?></strong>
                                                    <?php else: ?>
                                                        <span style="color:#ccc;">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endforeach; ?>

                                                <td align="center">
                                                    <strong class="<?= $row_total > 0 ? 'stock-positive' : 'stock-zero'; ?>">
                                                        <?= $row_total; ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <?php
                                                $grand_total += $row_total;
                                            endforeach;
                                            ?>
                                            </tbody>
                                            <tfoot>
                                                <tr style="background:#e9ecef; font-weight:bold;">
                                                    <th colspan="2" align="right">Total:</th>
                                                    <?php foreach ($product_totals as $total): ?>
                                                    <th align="center" class="product-col"><?= $total; ?></th>
                                                    <?php endforeach; ?>
                                                    <th align="center"><?= $grand_total; ?></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if ($total_pages > 1): ?>
                                    <nav class="mt-3 px-1">
                                        <ul class="pagination justify-content-center">
                                            <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1; ?>">Previous</a>
                                            </li>
                                            <?php endif; ?>
                                            <?php
                                            $start_page = max(1, $page - 2);
                                            $end_page   = min($total_pages, $page + 2);
                                            if ($start_page > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                                if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                            }
                                            for ($i = $start_page; $i <= $end_page; $i++):
                                            ?>
                                            <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                                            </li>
                                            <?php
                                            endfor;
                                            if ($end_page < $total_pages) {
                                                if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                            }
                                            ?>
                                            <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1; ?>">Next</a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                    <p class="text-center text-muted small pb-2">
                                        Showing <?= $offset + 1; ?> – <?= min($offset + $records_per_page, $total_records); ?>
                                        of <?= $total_records; ?> entries
                                    </p>
                                    <?php endif; ?>

                                </div>

                                <?php elseif ($has_filter && empty($tp_rows)): ?>
                                <div class="card-body">
                                    <div class="alert alert-warning mb-0">No stock data found for the selected filter.</div>
                                </div>

                                <?php else: ?>
                                <div class="card-body">
                                    <div class="no-filter-notice">
                                        <i class="material-icons" style="font-size:40px;color:#ced4da;display:block;margin-bottom:8px;">inventory_2</i>
                                        Select a <strong>location</strong> or <strong>territory partner</strong> above to view stock.
                                    </div>
                                </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script>
// Filter type toggle
function setFilterType(type) {
    $('#filterTypeInput').val(type);
    if (type === 'location') {
        $('#locationFilterArea').show();
        $('#tpFilterArea').hide();
        $('#toggleLocation').removeClass('btn-outline-primary').addClass('btn-primary');
        $('#toggleTP').removeClass('btn-primary').addClass('btn-outline-primary');
    } else {
        $('#locationFilterArea').hide();
        $('#tpFilterArea').show();
        $('#toggleLocation').removeClass('btn-primary').addClass('btn-outline-primary');
        $('#toggleTP').removeClass('btn-outline-primary').addClass('btn-primary');
    }
    $('#filterForm').submit();
}

$(function () {
    // TP Select2 — auto-submit on change
    $('#tp_filter').select2({
        placeholder: '— Choose a Territory Partner —',
        allowClear: true,
        width: '100%'
    }).on('change', function () {
        $('#filterForm').submit();
    });
});
</script>

<script>
// Location picker — search-only with default 20 locations on open
(function ($) {
    var selected       = null;
    var open           = false;
    var searchTimer    = null;
    var defaultsLoaded = false;

    function selectNode(node) {
        selected = { id: node.id, name: node.name };
        $('#filterLocHidden').val(node.id);
        renderControl();
        closePanel();
        $('#filterForm').submit();
    }

    function clearSelection() {
        selected = null;
        $('#filterLocHidden').val('');
        renderControl();
        $('#filterForm').submit();
    }

    function renderControl() {
        var $val = $('#filterLocValue').empty();
        if (selected) {
            $val.append($('<span>').text(selected.name));
            $('#filterLocClear').show();
        } else {
            $val.html('<span class="lp-placeholder">Search locations&hellip;</span>');
            $('#filterLocClear').hide();
        }
    }

    function renderResults(results, emptyMsg) {
        var $body = $('#flpBody').empty();
        if (!results || !results.length) {
            $body.html('<div class="lp-empty">' + emptyMsg + '</div>');
            return;
        }
        $.each(results, function (_, node) {
            var isStock = !!node.is_tp_filter_enabled;
            var isSel   = selected && selected.id === node.id;
            var $row    = $('<div class="lp-row"></div>');
            var $info   = $('<div style="flex:1;min-width:0;"></div>')
                            .append($('<div>').text(node.name))
                            .append($('<div class="lp-result-path">').text(node.path));
            if (isStock) {
                $row.addClass('lp-row-selectable' + (isSel ? ' lp-row-selected' : ''));
                if (isSel) $row.prepend('<i class="material-icons lp-check" style="font-size:16px;">check</i>');
                $row.append($info);
                $row.on('click', function () { selectNode(node); });
            } else {
                $row.css({ color: '#bbb', cursor: 'default' }).append($info);
            }
            $body.append($row);
        });
    }

    function loadDefaults() {
        if (defaultsLoaded) return;
        $('#flpBody').html('<div class="lp-loading">Loading&hellip;</div>');
        $.getJSON('search-location-nodes.php?mode=tp_stock_filter&default=1', function (results) {
            defaultsLoaded = true;
            renderResults(results, 'No locations available.');
        }).fail(function () {
            $('#flpBody').html('<div class="lp-empty">Failed to load. Please try again.</div>');
        });
    }

    function openPanel() {
        open = true;
        $('#filterLocControl').addClass('open');
        $('#filterLocPanel').show();
        setTimeout(function () { $('#flpSearchInput').focus(); }, 40);
        if ($.trim($('#flpSearchInput').val()) === '') loadDefaults();
    }

    function closePanel() {
        open = false;
        $('#filterLocControl').removeClass('open');
        $('#filterLocPanel').hide();
    }

    $('#filterLocControl').on('click', function (e) {
        e.stopPropagation();
        open ? closePanel() : openPanel();
    });
    $('#filterLocPanel').on('click', function (e) { e.stopPropagation(); });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#filterLocWrapper').length && open) closePanel();
    });
    $('#filterLocClear').on('click', function (e) { e.stopPropagation(); clearSelection(); });

    $('#flpSearchInput').on('input', function () {
        var q = $.trim($(this).val());
        clearTimeout(searchTimer);
        if (!q) {
            loadDefaults();
            return;
        }
        defaultsLoaded = false;
        $('#flpBody').html('<div class="lp-loading">Searching&hellip;</div>');
        searchTimer = setTimeout(function () {
            $.getJSON('search-location-nodes.php?mode=tp_stock_filter&q=' + encodeURIComponent(q), function (results) {
                renderResults(results, 'No locations found.');
            }).fail(function () {
                $('#flpBody').html('<div class="lp-empty">Search failed. Please try again.</div>');
            });
        }, 280);
    }).on('click', function (e) { e.stopPropagation(); });

    // Restore from session
    <?php if ($filter_type === 'location' && $selected_location_id > 0 && $selected_location_name !== ''): ?>
    selected = { id: <?= $selected_location_id; ?>, name: <?= json_encode($selected_location_name); ?> };
    <?php endif; ?>

}(jQuery));
</script>
</body>
</html>
