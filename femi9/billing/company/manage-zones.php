<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("include/PartnerZones.php");
error_reporting(0);
include("config.php");
ensurePartnerZonesTables($db_conn);

$zones = getAllZonesWithCounts($db_conn);

// Editing an existing zone — pre-fill the picker with its current districts.
$editZoneId = (int)($_GET['edit'] ?? 0);
$editZoneName = '';
if ($editZoneId > 0) {
    $stmt = $db_conn->prepare("SELECT name FROM partner_zones WHERE id = ?");
    $stmt->bind_param('i', $editZoneId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) { $editZoneName = $row['name']; } else { $editZoneId = 0; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Zones : <?php echo $business_name; ?></title>
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        .zone-card { background:#fff; border-radius:10px; padding:16px 18px; margin-bottom:12px; box-shadow:0 1px 4px rgba(0,0,0,0.06); border-left:4px solid #667eea; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
        .zone-card h5 { margin:0 0 4px 0; font-weight:700; }
        .zone-card .meta { color:#6b7280; font-size:12.5px; }
        .picker-col { max-height:260px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:8px; padding:8px; background:#fafafa; }
        .picker-col label { display:block; font-size:13px; padding:3px 4px; margin:0; cursor:pointer; }
        .picker-col label.assigned { color:#9ca3af; cursor:not-allowed; }
        .picker-col .zone-badge { font-size:10.5px; background:#fbe6e6; color:#d03b3b; border-radius:8px; padding:1px 6px; margin-left:4px; }
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

                        <div class="page-description">
                            <h1>Manage Zones</h1>
                        </div>

                        <?php if (isset($_SESSION['successMessage'])): ?>
                        <div class="alert alert-success"><?=htmlspecialchars($_SESSION['successMessage']); unset($_SESSION['successMessage']);?></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['errorMessage'])): ?>
                        <div class="alert alert-danger"><?=htmlspecialchars($_SESSION['errorMessage']); unset($_SESSION['errorMessage']);?></div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-5">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="mb-3">Existing Zones</h5>
                                        <?php if (empty($zones)): ?>
                                        <p class="text-muted">No zones yet — create one on the right.</p>
                                        <?php else: foreach ($zones as $z): ?>
                                        <div class="zone-card">
                                            <div>
                                                <h5><?=htmlspecialchars($z['name'])?></h5>
                                                <div class="meta"><?=(int)$z['district_count']?> district(s) &middot; <?=(int)$z['tp_count']?> TP(s)</div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a href="manage-zones.php?edit=<?=(int)$z['id']?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <form method="post" action="manage-zones-action.php" onsubmit="return confirm('Delete this zone? TPs in it are not affected, only the zone grouping.');" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="zone_id" value="<?=(int)$z['id']?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                        <?php endforeach; endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-7">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="mb-3"><?=$editZoneId > 0 ? 'Edit Zone' : 'Add Zone'?></h5>
                                        <form method="post" action="manage-zones-action.php" id="zoneForm">
                                            <input type="hidden" name="action" value="save">
                                            <input type="hidden" name="zone_id" value="<?=(int)$editZoneId?>">
                                            <div class="mb-3">
                                                <label class="form-label">Zone Name</label>
                                                <input type="text" name="name" class="form-control" required value="<?=htmlspecialchars($editZoneName)?>">
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label class="form-label">Country</label>
                                                    <select id="countrySelect" class="form-control">
                                                        <option value="">-- Select Country --</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">State</label>
                                                    <select id="stateSelect" class="form-control">
                                                        <option value="">-- Select State --</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">District</label>
                                                    <div id="districtList" class="picker-col"><p class="text-muted small px-2 mb-0">Select a State first.</p></div>
                                                </div>
                                            </div>
                                            <p class="text-muted small mt-2">Select a Country, then a State, then check the Districts for this zone (you can go back and pick another State to add more districts — earlier picks stay checked). A district already used by another zone is shown struck-through and can't be picked.</p>

                                            <div id="selectedDistrictsBox"></div>

                                            <button type="submit" class="btn btn-primary mt-2"><i class="material-icons" style="font-size:16px;vertical-align:middle;">save</i> Save Zone</button>
                                            <?php if ($editZoneId > 0): ?>
                                            <a href="manage-zones.php" class="btn btn-secondary mt-2">Cancel Edit</a>
                                            <?php endif; ?>
                                        </form>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script>
    var EDIT_ZONE_ID = <?=json_encode($editZoneId)?>;
    // Districts already picked for this zone (when editing) — pre-checked
    // once they appear in the cascading list below.
    var preCheckedDistricts = <?=json_encode(array_map('intval', $editZoneId > 0 ? getZoneDistrictNodeIds($db_conn, $editZoneId) : []))?>;
    // id -> name, tracked across state/district reloads so switching states
    // doesn't lose earlier picks. Seeded with the zone's existing districts
    // (by id+name, not just id) so Save works even if the picker is never
    // re-navigated back to those states/districts.
    var checkedDistricts = <?php
        $seed = [];
        if ($editZoneId > 0) {
            foreach (getZoneDistrictNodeIds($db_conn, $editZoneId) as $did) { $seed[$did] = null; }
            if (!empty($seed)) {
                $ids = array_keys($seed);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $typesX = str_repeat('i', count($ids));
                $stmtX = $db_conn->prepare("SELECT id, name FROM partner_location_nodes WHERE id IN ($placeholders)");
                $stmtX->bind_param($typesX, ...$ids);
                $stmtX->execute();
                foreach ($stmtX->get_result()->fetch_all(MYSQLI_ASSOC) as $rowX) { $seed[(int)$rowX['id']] = $rowX['name']; }
                $stmtX->close();
            }
        }
        echo json_encode($seed, JSON_FORCE_OBJECT);
    ?>;

    function loadChildren(parentIds, targetSelector, isDistrict) {
        $.getJSON('get-zone-location-children.php', { parent_ids: parentIds.join(','), exclude_zone_id: EDIT_ZONE_ID }, function (rows) {
            if (isDistrict) {
                var html = '';
                rows.forEach(function (r) {
                    var isAssigned = !!r.assigned_zone_name;
                    var isChecked = !!checkedDistricts[r.id] || preCheckedDistricts.indexOf(r.id) !== -1;
                    html += '<label class="' + (isAssigned ? 'assigned' : '') + '">' +
                        '<input type="checkbox" class="district-check" value="' + r.id + '" data-name="' + $('<div>').text(r.name).html() + '" ' +
                        (isAssigned ? 'disabled' : '') + (isChecked ? ' checked' : '') + '> ' +
                        $('<div>').text(r.name).html() +
                        (isAssigned ? '<span class="zone-badge">' + $('<div>').text(r.assigned_zone_name).html() + '</span>' : '') +
                        '</label>';
                });
                $(targetSelector).html(html || '<p class="text-muted small px-2">No districts here.</p>');
                bindDistrictChecks();
            } else {
                var placeholder = targetSelector === '#countrySelect' ? '-- Select Country --' : '-- Select State --';
                var html = '<option value="">' + placeholder + '</option>';
                rows.forEach(function (r) {
                    html += '<option value="' + r.id + '">' + $('<div>').text(r.name).html() + '</option>';
                });
                $(targetSelector).html(html);
            }
        });
    }

    function bindDistrictChecks() {
        $('.district-check').off('change').on('change', function () {
            var id = parseInt($(this).val(), 10);
            if (this.checked) {
                checkedDistricts[id] = $(this).data('name');
            } else {
                delete checkedDistricts[id];
            }
            renderSelectedSummary();
        });
    }

    function renderSelectedSummary() {
        var ids = Object.keys(checkedDistricts);
        var html = '<input type="hidden" name="district_ids[]" value="">'; // keeps the key present even if empty
        var names = [];
        ids.forEach(function (id) {
            html += '<input type="hidden" name="district_ids[]" value="' + id + '">';
            names.push(checkedDistricts[id]);
        });
        $('#selectedDistrictsBox').html(html +
            (names.length ? '<p class="small mt-2"><strong>Selected districts:</strong> ' + names.map(function(n){return $('<div>').text(n).html();}).join(', ') + '</p>' : ''));
    }

    var districtPlaceholder = '<p class="text-muted small px-2 mb-0">Select a State first.</p>';
    $('#countrySelect').on('change', function () {
        var id = parseInt($(this).val(), 10);
        $('#stateSelect').html('<option value="">-- Select State --</option>');
        $('#districtList').html(districtPlaceholder);
        if (id) loadChildren([id], '#stateSelect', false);
    });
    $('#stateSelect').on('change', function () {
        var id = parseInt($(this).val(), 10);
        $('#districtList').html(districtPlaceholder);
        if (id) loadChildren([id], '#districtList', true);
    });

    $(document).ready(function () {
        loadChildren([], '#countrySelect', false);
        renderSelectedSummary();
    });
    </script>
</body>
</html>
