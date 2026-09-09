<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$advBalance = 0;

include("geo_layers.php");
$filter_district = isset($_GET['f_district']) ? (int)$_GET['f_district'] : 0;
$filter_division = isset($_GET['f_division']) ? (int)$_GET['f_division'] : 0;
$filter_firka    = isset($_GET['f_firka']) ? (int)$_GET['f_firka'] : 0;

$export_qs = [];
if ($filter_district > 0) $export_qs['f_district'] = $filter_district;
if ($filter_division > 0) $export_qs['f_division'] = $filter_division;
if ($filter_firka > 0) $export_qs['f_firka'] = $filter_firka;
$export_url = "export_shop.php" . (!empty($export_qs) ? ("?" . http_build_query($export_qs)) : "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Shop (Retailers) : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
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
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <?php if (isset($_REQUEST['addedsuccess'])): ?><div class="alert alert-success">Shop added successfully.</div><?php endif; ?>
                                <?php if (isset($_REQUEST['updatedSuccess'])): ?><div class="alert alert-info">Changes saved.</div><?php endif; ?>
                                <?php if (isset($_REQUEST['deletedDone'])): ?><div class="alert alert-warning">Shop deleted.</div><?php endif; ?>
                                <h1><table class="headertble"><tr>
                                    <td>Manage Shop (Retailers)</td>
                                    <td><a href="shop-add.php" title="Add Shop">&#10011;</a></td>
                                    <td><a href="shop-import.php" title="Import CSV"><i class="material-icons-outlined" style="vertical-align:middle">upload_file</i></a></td>
                                    <td><a href="<?php echo htmlspecialchars($export_url); ?>" id="export_shop_link" title="Export CSV"><i class="material-icons-outlined" style="vertical-align:middle">download</i></a></td>
                                </tr></table></h1>
                            </div>
                        </div>
                    </div>
<?php
$num_rec_per_page = 30;
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$start_from = ($page - 1) * $num_rec_per_page;
$i = $start_from;
?>
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row" style="margin-bottom:15px;">
                                        <div class="col-md-3">
                                            <label class="form-label">District</label>
                                            <select id="filter_district" class="form-control" onchange="filterGeoPopulate(3, this.value); applyShopFilters();">
                                                <option value="">All Districts</option>
                                                <?php foreach ($geoNodes as $_n): if ($_n['depth'] === 3): ?>
                                                <option value="<?php echo $_n['id']; ?>" <?php echo ($filter_district === $_n['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($_n['name']); ?></option>
                                                <?php endif; endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3" id="filter_division_wrap" style="<?php echo $filter_district ? '' : 'display:none;'; ?>">
                                            <label class="form-label">Division</label>
                                            <select id="filter_division" class="form-control" onchange="filterGeoPopulate(4, this.value); applyShopFilters();">
                                                <option value="">All Divisions</option>
                                                <?php if ($filter_district): foreach ($geoNodes as $_n): if ($_n['depth'] === 4 && $_n['parent_id'] === $filter_district): ?>
                                                <option value="<?php echo $_n['id']; ?>" <?php echo ($filter_division === $_n['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($_n['name']); ?></option>
                                                <?php endif; endforeach; endif; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3" id="filter_firka_wrap" style="<?php echo $filter_division ? '' : 'display:none;'; ?>">
                                            <label class="form-label">Firka</label>
                                            <select id="filter_firka" class="form-control" onchange="applyShopFilters();">
                                                <option value="">All Firkas</option>
                                                <?php if ($filter_division): foreach ($geoNodes as $_n): if ($_n['depth'] === 5 && $_n['parent_id'] === $filter_division): ?>
                                                <option value="<?php echo $_n['id']; ?>" <?php echo ($filter_firka === $_n['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($_n['name']); ?></option>
                                                <?php endif; endforeach; endif; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3" style="align-self:flex-end;">
                                            <a href="shop-manage.php" class="btn btn-secondary" style="margin-top:24px;">Clear Filters</a>
                                        </div>
                                    </div>
                                    <div style="overflow-x:scroll;">
                                        <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Category</th>
                                                    <th>ID</th>
                                                    <th>Name</th>
                                                    <th>Pincode</th>
                                                    <th>Mobile Number</th>
                                                    <th>Landline</th>
                                                    <th>Edit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
<?php
$select_shops = "SELECT s.*, sc.catlable FROM shop s LEFT JOIN shop_category sc ON s.shop_cat = sc.id WHERE s.onboard_userID=? AND s.onboard_userTYPE=? AND s.deleted_at IS NULL";
$filter_types = "is";
$filter_params = [$Login_user_IDvl, $Login_user_TYPEvl];
if ($filter_district > 0) {
    $select_shops .= " AND s.district_id=?";
    $filter_types .= "i";
    $filter_params[] = $filter_district;
}
if ($filter_division > 0) {
    $select_shops .= " AND s.taluk_id=?";
    $filter_types .= "i";
    $filter_params[] = $filter_division;
}
if ($filter_firka > 0) {
    $select_shops .= " AND s.firka_id=?";
    $filter_types .= "i";
    $filter_params[] = $filter_firka;
}
$select_shops .= " ORDER BY s.id DESC";
$stmt_shops = mysqli_prepare($db_conn, $select_shops);
mysqli_stmt_bind_param($stmt_shops, $filter_types, ...$filter_params);
mysqli_stmt_execute($stmt_shops);
$fetch_shops = mysqli_stmt_get_result($stmt_shops);
while ($result_shop = mysqli_fetch_array($fetch_shops)) {
    $rowid = base64_encode($result_shop["id"]);
?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo htmlspecialchars($result_shop['catlable'] ?? '---'); ?></td>
                                                    <td><?php echo htmlspecialchars($result_shop["useridtext"]); ?></td>
                                                    <td><b><?php echo htmlspecialchars(ucwords($result_shop["name"])); ?></b></td>
                                                    <td><?php echo htmlspecialchars($result_shop["pincode_id"]); ?></td>
                                                    <td><?php echo htmlspecialchars($result_shop["country_code"]); ?>&nbsp;<?php echo htmlspecialchars($result_shop["mobile_number"]); ?></td>
                                                    <td><?php echo htmlspecialchars($result_shop["landline"]); ?></td>
                                                    <td><a href="shop-edit.php?prid=<?php echo $rowid; ?>&&actionupdate"><img src="../../assets/images/edit-32.png"/></a></td>
                                                </tr>
<?php } ?>
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
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
<script>
var geoNodes = <?php echo json_encode(array_values($geoNodes)); ?>;

function filterGeoPopulate(parentDepth, parentId) {
    parentId = parentId ? parseInt(parentId) : null;
    if (parentDepth === 3) {
        var divSel = document.getElementById('filter_division');
        divSel.innerHTML = '<option value="">All Divisions</option>';
        var firkaSel = document.getElementById('filter_firka');
        firkaSel.innerHTML = '<option value="">All Firkas</option>';
        document.getElementById('filter_firka_wrap').style.display = 'none';
        if (!parentId) {
            document.getElementById('filter_division_wrap').style.display = 'none';
            return;
        }
        document.getElementById('filter_division_wrap').style.display = '';
        geoNodes.filter(function(n){ return n.depth === 4 && n.parent_id === parentId; }).forEach(function(n){
            var o = document.createElement('option');
            o.value = n.id; o.textContent = n.name;
            divSel.appendChild(o);
        });
    } else if (parentDepth === 4) {
        var firkaSel2 = document.getElementById('filter_firka');
        firkaSel2.innerHTML = '<option value="">All Firkas</option>';
        if (!parentId) {
            document.getElementById('filter_firka_wrap').style.display = 'none';
            return;
        }
        document.getElementById('filter_firka_wrap').style.display = '';
        geoNodes.filter(function(n){ return n.depth === 5 && n.parent_id === parentId; }).forEach(function(n){
            var o = document.createElement('option');
            o.value = n.id; o.textContent = n.name;
            firkaSel2.appendChild(o);
        });
    }
}

function applyShopFilters() {
    var d = document.getElementById('filter_district').value;
    var v = document.getElementById('filter_division').value;
    var f = document.getElementById('filter_firka').value;
    var params = new URLSearchParams();
    if (d) params.set('f_district', d);
    if (v) params.set('f_division', v);
    if (f) params.set('f_firka', f);
    window.location.href = 'shop-manage.php' + (params.toString() ? ('?' + params.toString()) : '');
}
</script>
</body>
</html>
