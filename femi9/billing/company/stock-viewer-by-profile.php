<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockViewerData.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['stockviewer', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$profiles   = get_stock_viewer_company_profiles($db_conn);
$warehouses = get_stock_viewer_warehouses($db_conn);
$rows       = get_stock_viewer_rows($db_conn);

// Distinct product names across every row — populates the searchable
// product picker below (same product list the data can actually show,
// rather than the full catalog, so there's never a selectable option with
// nothing to show for it).
$productNames = array_values(array_unique(array_column($rows, 'productName')));
sort($productNames, SORT_STRING | SORT_FLAG_CASE);

// Bucket rows by [company_godown_id][warehouse_id or ''] => rows, so each
// card renders one (profile, warehouse) combination — same shape as
// overall-stock.php's per-warehouse cards, but spanning every profile.
$buckets = [];
foreach ($rows as $row) {
    $profileKey = (int) $row['company_godown_id'];
    $whKey      = $row['warehouse_id'] !== null ? (string) $row['warehouse_id'] : '';
    $buckets[$profileKey][$whKey]['rows'][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock Viewer : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        :root {
            --ata-tp-1: #667eea;
            --ata-tp-2: #764ba2;
        }
        .ata-page-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
        .ata-page-head h1 { font-size:20px; font-weight:600; color:#1f2937; margin:0; display:flex; align-items:center; }
        .ata-intro { color:#6b7280; font-size:13.5px; line-height:1.55; margin-bottom:16px; }

        .ata-nav-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
        .ata-nav-tab { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:9px; font-size:13.5px; font-weight:500; text-decoration:none; transition:filter .15s, background .15s; }
        .ata-nav-tab i { font-size:17px; }
        .ata-nav-tab.active { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); color:#fff; box-shadow:0 2px 8px rgba(102,126,234,.3); }
        .ata-nav-tab:not(.active) { background:#f3f4f6; color:#4b5563; }
        .ata-nav-tab:not(.active):hover { background:#e5e7eb; color:#1f2937; }

        .ms-dropdown-selectall {
            display:flex; align-items:center; gap:8px; font-size:12px; font-weight:600;
            padding:0 0 6px; margin-bottom:6px; border-bottom:1px solid #e9ecef; white-space:nowrap;
        }
        .ms-dropdown-selectall a { color:#3f51b5; text-decoration:none; }
        .ms-dropdown-selectall a:hover { text-decoration:underline; }
        .ms-dropdown-selectall span { color:#ced4da; }

        .tick-item {
            display:flex; align-items:center; justify-content:space-between; gap:12px;
            padding:7px 10px; margin:0 -4px; border-radius:6px; cursor:pointer;
            white-space:nowrap; font-weight:normal; color:#374151;
        }
        .tick-item:hover { background:#f3f4f6; }
        .tick-item input[type="checkbox"] { display:none; }
        .tick-item .material-icons { font-size:18px; opacity:0; color:#667eea; transition:opacity .15s; }
        .tick-item.selected { background:#eef1fd; color:#667eea; font-weight:600; }
        .tick-item.selected .material-icons { opacity:1; }
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
                    <div class="ata-page-head">
                        <h1><i class="material-icons-outlined" style="font-size:22px;vertical-align:middle;margin-right:8px;color:var(--ata-tp-1);">store</i>Stock Viewer</h1>
                    </div>
                    <p class="ata-intro">Closing stock across every company profile and warehouse, filterable by product, company profile, or warehouse.</p>

                    <div class="ata-nav-tabs">
                        <a href="stock-viewer-dashboard.php" class="ata-nav-tab"><i class="material-icons-outlined">dashboard</i> Dashboard</a>
                        <a href="stock-viewer-by-profile.php" class="ata-nav-tab active"><i class="material-icons-outlined">store</i> Stock Viewer</a>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label">Filter by Product</label>
                                            <select id="productFilterSelect" class="form-control" style="width:100%;">
                                                <option value="">All Products</option>
                                                <?php foreach ($productNames as $pname): ?>
                                                <option value="<?=htmlspecialchars(strtolower($pname), ENT_QUOTES, 'UTF-8');?>"><?=htmlspecialchars($pname, ENT_QUOTES, 'UTF-8');?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Filter by Company Profile</label>
                                            <div class="ms-dropdown" style="position:relative;">
                                                <div class="form-control" id="profileDropdownToggle" onclick="toggleDropdown(event, 'profileDropdownPanel')" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;">
                                                <span id="profileDropdownLabel" style="color:#6c757d;">All</span>
                                                <i class="material-icons" style="font-size:20px;">arrow_drop_down</i>
                                                </div>
                                                <div id="profileDropdownPanel" style="display:none;position:absolute;z-index:20;background:#fff;border:1px solid #ced4da;border-radius:4px;padding:8px 12px;margin-top:2px;min-width:100%;max-width:320px;box-shadow:0 4px 10px rgba(0,0,0,0.1);">
                                                <div class="ms-dropdown-selectall">
                                                    <a href="javascript:void(0)" onclick="setAllProfileChecks(true)">Select all</a>
                                                    <span>&middot;</span>
                                                    <a href="javascript:void(0)" onclick="setAllProfileChecks(false)">Deselect all</a>
                                                </div>
                                                <?php foreach ($profiles as $p): ?>
                                                <label class="tick-item selected">
                                                <span><?=htmlspecialchars($p['gname'], ENT_QUOTES, 'UTF-8');?></span>
                                                <input type="checkbox" class="profile-filter-check" value="cp-<?=(int)$p['id'];?>" checked onchange="onProfileFilterChange()">
                                                <i class="material-icons">check</i>
                                                </label>
                                                <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Filter by Warehouse</label>
                                            <div class="ms-dropdown" style="position:relative;">
                                                <div class="form-control" id="warehouseDropdownToggle" onclick="toggleDropdown(event, 'warehouseDropdownPanel')" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;">
                                                <span id="warehouseDropdownLabel" style="color:#6c757d;">All</span>
                                                <i class="material-icons" style="font-size:20px;">arrow_drop_down</i>
                                                </div>
                                                <div id="warehouseDropdownPanel" style="display:none;position:absolute;z-index:20;background:#fff;border:1px solid #ced4da;border-radius:4px;padding:8px 12px;margin-top:2px;min-width:100%;max-width:320px;box-shadow:0 4px 10px rgba(0,0,0,0.1);">
                                                <div class="ms-dropdown-selectall">
                                                    <a href="javascript:void(0)" onclick="setAllWarehouseChecks(true)">Select all</a>
                                                    <span>&middot;</span>
                                                    <a href="javascript:void(0)" onclick="setAllWarehouseChecks(false)">Deselect all</a>
                                                </div>
                                                <label class="tick-item selected">
                                                <span>Unassigned</span>
                                                <input type="checkbox" class="warehouse-filter-check" value="unassigned" checked onchange="onWarehouseFilterChange()">
                                                <i class="material-icons">check</i>
                                                </label>
                                                <?php foreach ($warehouses as $wh): ?>
                                                <label class="tick-item selected">
                                                <span><?=htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8');?></span>
                                                <input type="checkbox" class="warehouse-filter-check" value="wh-<?=(int)$wh['id'];?>" checked onchange="onWarehouseFilterChange()">
                                                <i class="material-icons">check</i>
                                                </label>
                                                <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

<?php foreach ($profiles as $profile):
    $profileId = (int) $profile['id'];
    if (empty($buckets[$profileId])) continue;
    foreach ($buckets[$profileId] as $whKey => $bucket):
        $whLabel = 'Unassigned';
        $cardFilterWh = 'unassigned';
        if ($whKey !== '') {
            $whRow = null;
            foreach ($warehouses as $wh) { if ((string)$wh['id'] === $whKey) { $whRow = $wh; break; } }
            $whLabel = $whRow ? ($whRow['code'] . ($whRow['name'] ? ' - ' . $whRow['name'] : '')) : "Godown #$whKey";
            $cardFilterWh = 'wh-' . $whKey;
        }
?>
                    <div class="row wh-card" data-profile="cp-<?=$profileId;?>" data-warehouse="<?=$cardFilterWh;?>">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <h1><?=htmlspecialchars($profile['gname'], ENT_QUOTES, 'UTF-8');?> &mdash; <?=htmlspecialchars($whLabel, ENT_QUOTES, 'UTF-8');?></h1>
                                    <div style="background:#fff;overflow:scroll;width:100%;">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Product Name</th>
                                                <th style="text-align:right;">Closing Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
<?php foreach ($bucket['rows'] as $row): ?>
                                            <tr class="product-row" data-product-name="<?=htmlspecialchars(strtolower($row['productName']), ENT_QUOTES, 'UTF-8');?>">
                                                <td><?=htmlspecialchars($row['productName'], ENT_QUOTES, 'UTF-8');?></td>
                                                <td align="right"><b><?=(int)$row['closing_qty'];?></b></td>
                                            </tr>
<?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
<?php
    endforeach;
endforeach;
?>

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
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
function applyStockFilters() {
    var productQuery = document.getElementById('productFilterSelect').value;
    var checkedProfiles = Array.prototype.slice.call(document.querySelectorAll('.profile-filter-check:checked')).map(function (cb) { return cb.value; });
    var checkedWarehouses = Array.prototype.slice.call(document.querySelectorAll('.warehouse-filter-check:checked')).map(function (cb) { return cb.value; });

    document.querySelectorAll('.wh-card').forEach(function (card) {
        var profileVisible = checkedProfiles.indexOf(card.getAttribute('data-profile')) !== -1;
        var warehouseVisible = checkedWarehouses.indexOf(card.getAttribute('data-warehouse')) !== -1;
        if (!profileVisible || !warehouseVisible) {
            card.style.display = 'none';
            return;
        }
        var anyRowVisible = false;
        card.querySelectorAll('.product-row').forEach(function (row) {
            var matches = !productQuery || row.getAttribute('data-product-name') === productQuery;
            row.style.display = matches ? '' : 'none';
            if (matches) anyRowVisible = true;
        });
        card.style.display = (productQuery && !anyRowVisible) ? 'none' : '';
    });
}

$(function () {
    $('#productFilterSelect').select2({ placeholder: 'Select a product…', allowClear: true, width: '100%' });
});
document.getElementById('productFilterSelect').addEventListener('change', applyStockFilters);

function toggleDropdown(e, panelId) {
    e.stopPropagation();
    var panel = document.getElementById(panelId);
    var willOpen = panel.style.display !== 'block';
    // Close every other filter panel first — two panels open at once can
    // overlap (long company/warehouse names force a panel wider than its
    // column) and silently swallow clicks meant for the one underneath.
    document.querySelectorAll('[id$="DropdownPanel"]').forEach(function (p) {
        if (p.id !== panelId) p.style.display = 'none';
    });
    panel.style.display = willOpen ? 'block' : 'none';
}

function updateDropdownLabel(checkClass, labelId, singularName) {
    var boxes = document.querySelectorAll('.' + checkClass);
    var checked = document.querySelectorAll('.' + checkClass + ':checked');
    var label = document.getElementById(labelId);
    if (checked.length === boxes.length) {
        label.textContent = 'All';
        label.style.color = '#6c757d';
    } else if (checked.length === 0) {
        label.textContent = 'None';
        label.style.color = '#000';
    } else if (checked.length === 1) {
        label.textContent = checked[0].closest('label').querySelector('span').textContent.trim();
        label.style.color = '#000';
    } else {
        label.textContent = checked.length + ' ' + singularName + ' selected';
        label.style.color = '#000';
    }
}

function updateWarehouseDropdownLabel() { updateDropdownLabel('warehouse-filter-check', 'warehouseDropdownLabel', 'warehouses'); }
function updateProfileDropdownLabel() { updateDropdownLabel('profile-filter-check', 'profileDropdownLabel', 'profiles'); }

function onWarehouseFilterChange() {
    updateWarehouseDropdownLabel();
    applyStockFilters();
}
function onProfileFilterChange() {
    updateProfileDropdownLabel();
    applyStockFilters();
}
updateWarehouseDropdownLabel();
updateProfileDropdownLabel();

document.querySelectorAll('.profile-filter-check, .warehouse-filter-check').forEach(function (cb) {
    cb.addEventListener('change', function () {
        this.closest('.tick-item').classList.toggle('selected', this.checked);
    });
});

function setAllWarehouseChecks(checked) {
    document.querySelectorAll('.warehouse-filter-check').forEach(function (cb) {
        cb.checked = checked;
        cb.closest('.tick-item').classList.toggle('selected', checked);
    });
    onWarehouseFilterChange();
}
function setAllProfileChecks(checked) {
    document.querySelectorAll('.profile-filter-check').forEach(function (cb) {
        cb.checked = checked;
        cb.closest('.tick-item').classList.toggle('selected', checked);
    });
    onProfileFilterChange();
}

document.addEventListener('click', function (e) {
    document.querySelectorAll('.ms-dropdown').forEach(function (dropdown) {
        if (!dropdown.contains(e.target)) {
            var panel = dropdown.querySelector('[id$="DropdownPanel"]');
            if (panel) panel.style.display = 'none';
        }
    });
});
document.getElementById('warehouseDropdownPanel').addEventListener('click', function (e) {
    e.stopPropagation();
});
document.getElementById('profileDropdownPanel').addEventListener('click', function (e) {
    e.stopPropagation();
});
</script>
</body>
</html>
