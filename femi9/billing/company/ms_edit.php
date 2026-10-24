<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms_edit');

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$_chkTL = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'team_level_id'");
if ($_chkTL && $_chkTL->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN team_level_id INT NULL DEFAULT NULL AFTER user_position");
}
$_chkMgr = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'manager_id'");
if ($_chkMgr && $_chkMgr->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN manager_id INT NULL DEFAULT NULL AFTER team_level_id");
}
$_chkTgt = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'monthly_target_amount'");
if ($_chkTgt && $_chkTgt->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN monthly_target_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER manager_id");
}

//fetch product details
$select_product_list="select * from marketing_staff where id=?";
$fetch_product_list=db_q($db_conn,$select_product_list,[$prid]);
$result_product_list=mysqli_fetch_array($fetch_product_list);

// Currently assigned locations (with names for the picker)
$db_conn->query("CREATE TABLE IF NOT EXISTS marketing_staff_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ms_id INT NOT NULL,
    location_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ms_location (ms_id, location_id)
)");
$stmt_cur = $db_conn->prepare("
    SELECT n.id, n.name, n.depth
    FROM marketing_staff_locations msl
    JOIN partner_location_nodes n ON n.id = msl.location_id
    WHERE msl.ms_id = ?
    ORDER BY n.name
");
$prid_int = (int)$prid;
$stmt_cur->bind_param("i", $prid_int);
$stmt_cur->execute();
$current_locations = $stmt_cur->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cur->close();
$preselected_json = json_encode(array_map(function($loc) {
    return ['id' => (int)$loc['id'], 'name' => $loc['name']];
}, $current_locations));
// The person's existing assignment's own depth (e.g. Division instead of
// District) — passed to JS so the picker can default to the deeper layer on
// load instead of silently showing an empty "District" picker with their
// real (Division-level) assignment invisible. Compared client-side against
// the layer depths get-ms-location-options.php reports for this team level.
$preselected_depth = !empty($current_locations) ? (int)$current_locations[0]['depth'] : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Update Marketing Staff : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">


    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
    <style>
    .lp-wrapper { position: relative; }
    .lp-control {
        display: flex; align-items: center; min-height: 38px;
        border: 1px solid #ced4da; border-radius: 4px; background: #fff;
        padding: 2px 8px; cursor: pointer; user-select: none;
    }
    .lp-control:hover { border-color: #adb5bd; }
    .lp-control.open { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
    .lp-value { flex: 1; display: flex; flex-wrap: wrap; gap: 4px; min-height: 28px; align-items: center; padding: 2px 0; }
    .lp-placeholder { color: #aaa; font-size: 13px; }
    .lp-arrow { margin-left: 6px; display: flex; align-items: center; }
    .lp-chip { display: inline-flex; align-items: center; gap: 4px; background: #e9ecef; border-radius: 3px; padding: 2px 6px; font-size: 12px; max-width: 180px; }
    .lp-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lp-chip-remove { cursor: pointer; font-size: 15px; line-height: 1; color: #888; flex-shrink: 0; }
    .lp-chip-remove:hover { color: #dc3545; }
    .lp-panel {
        position: absolute; top: calc(100% + 3px); left: 0; right: 0;
        background: #fff; border: 1px solid #ced4da; border-radius: 4px;
        z-index: 1050; box-shadow: 0 4px 16px rgba(0,0,0,.12);
        display: flex; flex-direction: column; max-height: 280px;
    }
    .lp-body { overflow-y: auto; flex: 1; }
    .lp-row { display: flex; align-items: center; gap: 8px; padding: 9px 14px; font-size: 13px; border-bottom: 1px solid #f5f5f5; cursor: pointer; }
    .lp-row:last-child { border-bottom: none; }
    .lp-row-selectable { color: #333; }
    .lp-row-selectable:hover { background: #f8f9fa; }
    .lp-row-selected { background: #e8f0fe; color: #1a73e8; font-weight: 500; }
    .lp-row-selected:hover { background: #d2e3fc; }
    .lp-row-taken { color: #bbb; cursor: not-allowed; }
    .lp-row-taken:hover { background: #fff; }
    .lp-row .lp-check { color: #1a73e8; flex-shrink: 0; font-size: 16px; }
    .lp-row .lp-lock { color: #ccc; flex-shrink: 0; font-size: 16px; }
    .lp-empty, .lp-loading { padding: 16px; text-align: center; font-size: 13px; color: #aaa; }
    .lp-search-box { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; flex-shrink: 0; display: flex; align-items: center; gap: 6px; }
    .lp-search-box input { flex: 1; border: 1px solid #ced4da; border-radius: 4px; padding: 5px 10px; font-size: 13px; outline: none; font-family: inherit; }
    .lp-search-box input:focus { border-color: #80bdff; box-shadow: 0 0 0 .15rem rgba(0,123,255,.2); }
    </style>
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        <div class="app-container">
           
          <?php include("app-header.php");?>
			
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                     <h1>
									<table class="headertble">
									<tr>
									<td>Update Marketing Staff</td>
									<td><a href="ms_manage" title="Manage Customer">&#9776;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <!----<div class="card-header">
                                        <h5 class="card-title">Basic Input</h5>
                                    </div>--->
                                    <div class="card-body">
									
									<?php include("validate-scripts.php");?>        
                                       
<form action="ms_action" method="post" enctype="multipart/form-data">
<input type="hidden" name="update_id" value="<?php echo $result_product_list["id"]?>">

                                        <div class="example-container">
                                            <div class="example-content">
                                               
											   <label class="form-label">Name*</label>
<input type="text" value="<?=$result_product_list['ms_name'];?>" required="" name="ms_name" class="form-control" onkeypress="restrictSpecialChars(event)">
<br/>
												
<style>
        .form-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .form-group .country-code {
            flex: 0 0 20%;
        }
        .form-group .mobile-number {
            flex: 1;
        }
    </style>
		<div class="form-group">
            <div class="country-code">
                <label class="form-label">Country Code *</label>
<select id='country_code' name='country_code' required="" class="form-control">
<option value="<?php echo $result_product_list['country_code'];?>" hidden><?php echo $result_product_list['country_code'];?></option>
<?php $selectCountry="select * from country order by c_name asc";
$fetchCountry=mysqli_query($db_conn,$selectCountry);
while($resultCountry=mysqli_fetch_array($fetchCountry)){?>
<option value='<?php echo $resultCountry['c_code'];?>' ><?php echo $resultCountry['c_name'];?> (<?php echo $resultCountry['c_code'];?>)</option>
<?php }?>
</select>
            </div>
            <div class="mobile-number">
                <label class="form-label">Mobile Number (Username)*</label>
                <input type="text" required disabled onkeypress="restrictnumber(event)" pattern="[1-9]{1}[0-9]{9}" value="<?php echo $result_product_list['ms_mobile'];?>" class="form-control" maxlength="10">
            </div>
        </div>
        <!-- New add end -->
</br>

<label class="form-label">Email ID</label>
<input type="email" name="ms_email" onkeypress="restrictemail(event)" value="<?=$result_product_list['ms_email'];?>" class="form-control" placeholder="optional">
</br>

<label class="form-label">Address</label>
<textarea name="ms_address" onkeypress="restrictSpecialChars(event)" class="form-control" placeholder="optional"><?=$result_product_list['ms_address'];?></textarea>
</br>

<label class="form-label">Monthly Target Amount (&#8377;)</label>
<input type="number" step="0.01" min="0" name="monthly_target_amount" value="<?=$result_product_list['monthly_target_amount'] !== null ? htmlspecialchars($result_product_list['monthly_target_amount']) : '';?>" class="form-control" placeholder="optional">
</br>

<?php
$db_conn->query("CREATE TABLE IF NOT EXISTS marketing_team_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_rank INT NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_level_rank (level_rank)
)");
$currentTeamLevelId = (int)($result_product_list['team_level_id'] ?? 0);
$currentTeamLevelName = '';
if ($currentTeamLevelId) {
    $stmt_ctl = $db_conn->prepare("SELECT level_name FROM marketing_team_levels WHERE id = ?");
    $stmt_ctl->bind_param("i", $currentTeamLevelId);
    $stmt_ctl->execute();
    $ctlRow = $stmt_ctl->get_result()->fetch_assoc();
    $stmt_ctl->close();
    $currentTeamLevelName = $ctlRow['level_name'] ?? '';
}
?>
<label class="form-label">Team Level</label>
<div class="lp-wrapper" id="teamLevelPickerWrapper">
    <div class="lp-control" id="teamLevelPickerControl">
        <div class="lp-value" id="tlpValue">
            <?php if ($currentTeamLevelName !== ''): ?>
                <span><?php echo htmlspecialchars($currentTeamLevelName); ?></span>
            <?php else: ?>
                <span class="lp-placeholder">-- None --</span>
            <?php endif; ?>
        </div>
        <div class="lp-arrow"><i class="material-icons" style="font-size:18px;color:#999;">arrow_drop_down</i></div>
    </div>
    <div class="lp-panel" id="teamLevelPanel" style="display:none;">
        <div class="lp-search-box">
            <i class="material-icons" style="font-size:18px;color:#aaa;">search</i>
            <input type="text" id="tlpSearchInput" placeholder="Search team level&hellip;" autocomplete="off">
        </div>
        <div class="lp-body" id="tlpBody"><div class="lp-loading">Loading&hellip;</div></div>
    </div>
</div>
<input type="hidden" name="team_level_id" id="teamLevelSelect" value="<?php echo $currentTeamLevelId ?: ''; ?>">
<small class="text-muted">Manage levels from <a href="manage-marketing-team-levels">Manage Team Levels</a>.</small>
<br/>

<div id="managerFieldWrap" style="display:none;margin-top:10px;">
    <label class="form-label" id="managerFieldLabel">Reports To</label>
    <div class="lp-wrapper" id="managerPickerWrapper">
        <div class="lp-control" id="managerPickerControl">
            <div class="lp-value" id="mgrValue">
                <span class="lp-placeholder">-- Select --</span>
            </div>
            <div class="lp-arrow"><i class="material-icons" style="font-size:18px;color:#999;">arrow_drop_down</i></div>
        </div>
        <div class="lp-panel" id="managerPanel" style="display:none;">
            <div class="lp-search-box">
                <i class="material-icons" style="font-size:18px;color:#aaa;">search</i>
                <input type="text" id="mgrSearchInput" placeholder="Search&hellip;" autocomplete="off">
            </div>
            <div class="lp-body" id="mgrBody"><div class="lp-loading">Loading&hellip;</div></div>
        </div>
    </div>
    <input type="hidden" name="manager_id" id="managerSelect" value="">
    <div id="managerChainPreview" style="font-size:12px;color:#666;margin-top:5px;"></div>
</div>
			<br/>
			<style type="text/css"> .hidden {display: none;}</style>

<label class="form-label">Assign Location</label>
<div id="lpZoneFill" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;margin-bottom:12px;">
    <label class="form-label" style="font-size:12.5px;font-weight:700;color:#475569;margin-bottom:6px;">Fill from Zone</label>
    <select class="form-control" id="lpZoneSelect" style="background-color:#fff !important;-webkit-box-shadow:0 0 0 1000px #fff inset;">
        <option value="">-- select a zone --</option>
    </select>
    <small class="text-muted d-block mt-2" id="lpZoneFillStatus">Picking a zone replaces the selection below with every district in that zone — pick a different zone to start over from it instead.</small>
</div>
<div id="lpLayerToggle" style="display:none;margin-bottom:8px;">
    <div class="btn-group btn-group-sm" role="group">
        <button type="button" class="btn btn-outline-primary active" id="lpOwnLayerBtn">District</button>
        <button type="button" class="btn btn-outline-primary" id="lpNextLayerBtn">Division</button>
    </div>
    <small class="text-muted d-block mt-1">Pick a whole District, or switch to Division to narrow down to the divisions inside whichever districts are already selected below.</small>
</div>
<div class="lp-wrapper" id="locationPickerWrapper">
    <div class="lp-control" id="locationPickerControl">
        <div class="lp-value" id="lpValue">
            <span class="lp-placeholder">Select locations&hellip;</span>
        </div>
        <div class="lp-arrow"><i class="material-icons" style="font-size:18px;color:#999;">arrow_drop_down</i></div>
    </div>
    <div class="lp-panel" id="locationPanel" style="display:none;">
        <div class="lp-search-box">
            <i class="material-icons" style="font-size:18px;color:#aaa;">search</i>
            <input type="text" id="lpSearchInput" placeholder="Search locations&hellip;" autocomplete="off">
        </div>
        <div class="lp-body" id="lpBody"><div class="lp-loading">Loading&hellip;</div></div>
    </div>
</div>
<div id="lpHiddenInputs"></div>
<small class="text-muted">Already-assigned locations are shown locked and cannot be selected.</small>
<br/><br/>

												<button type="submit" name="update-customer" class="btn btn-primary">Update</button>
												
                                            </div>
											
                                        </div>
										
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
    </div>

    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>

    <script>
    (function ($) {
        var allNodes = [];
        var selected = <?php echo $preselected_json; ?>;
        var open     = false;
        var loaded   = false;
        var EXCLUDE_MS = <?php echo (int)$prid; ?>;
        // District/Division toggle — whether the picker should fetch nodes
        // one depth below the team level's own configured layer. Auto-set to
        // true once (see loadNodes()'s success handler) if this person's
        // existing assignment already sits at that deeper depth.
        var useNextLayer = false;
        var PRESELECTED_DEPTH = <?php echo $preselected_depth !== null ? (int)$preselected_depth : 'null'; ?>;
        var autoDepthChecked = false;
        // Set right before loadNodes() when the user actively picks a
        // manager (not on initial page load, which must leave an existing
        // assignment alone) — tells loadZones() to auto-apply that
        // manager's own zone once it comes back, if they have exactly one.
        var autoFillZoneOnLoad = false;
        // The district selection as it stood right before switching to
        // Division mode — restored when switching back to District instead
        // of losing it (Division mode's selection is a different, narrower
        // thing and isn't carried back).
        var savedDistrictSelection = null;

        renderChips();
        updateHiddenInputs();

        function escHtml(str) { return $('<div>').text(str).html(); }

        function isSelected(id) {
            for (var i = 0; i < selected.length; i++) { if (selected[i].id === id) return true; }
            return false;
        }

        function toggleSelect(node) {
            var idx = -1;
            for (var i = 0; i < selected.length; i++) { if (selected[i].id === node.id) { idx = i; break; } }
            if (idx >= 0) { selected.splice(idx, 1); } else { selected.push({ id: node.id, name: node.name }); }
            renderChips();
            renderList($.trim($('#lpSearchInput').val()));
            updateHiddenInputs();
        }

        function renderList(q) {
            var $body = $('#lpBody').empty();
            var nodes = allNodes;
            if (q) {
                var ql = q.toLowerCase();
                nodes = nodes.filter(function (n) { return n.name.toLowerCase().indexOf(ql) >= 0; });
            }
            if (nodes.length === 0) {
                $body.html('<div class="lp-empty">' + (q ? 'No results for "' + escHtml(q) + '".' : 'No locations available. Ask admin to enable "Marketing Staff Filter" on a Location Layer.') + '</div>');
                return;
            }
            var curLayer = null;
            $.each(nodes, function (_, node) {
                if (node.layer_name && node.layer_name !== curLayer) {
                    curLayer = node.layer_name;
                    $body.append('<div style="padding:5px 14px 4px;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.8px;border-bottom:1px solid #f0f0f0;">' + escHtml(curLayer) + '</div>');
                }
                var $row = $('<div class="lp-row"></div>');
                if (isSelected(node.id)) {
                    // Already in this person's own selection takes priority
                    // over is_taken — a Zone-fill can add a district that was
                    // taken from someone else (see ms_action.php's
                    // reassignment), and it must still show as removable
                    // here, not as a locked row contradicting the chip above.
                    $row.addClass('lp-row-selectable lp-row-selected');
                    $row.append('<i class="material-icons lp-check" style="font-size:16px;">check</i>');
                    $row.append($('<span>').text(node.name));
                    $row.on('click', function () { toggleSelect(node); });
                } else if (node.is_taken) {
                    $row.addClass('lp-row-taken');
                    $row.append('<i class="material-icons lp-lock" style="font-size:16px;">lock</i>');
                    $row.append($('<span>').text(node.name));
                } else {
                    $row.addClass('lp-row-selectable');
                    $row.append($('<span>').text(node.name));
                    $row.on('click', function () { toggleSelect(node); });
                }
                $body.append($row);
            });
        }

        function renderChips() {
            var $val = $('#lpValue').empty();
            if (selected.length === 0) { $val.html('<span class="lp-placeholder">Select locations&hellip;</span>'); return; }
            $.each(selected, function (_, s) {
                var $chip = $('<span class="lp-chip"></span>');
                $chip.append($('<span>').text(s.name));
                var $x = $('<span class="lp-chip-remove">&times;</span>');
                $x.on('click', function (e) {
                    e.stopPropagation();
                    selected = selected.filter(function (r) { return r.id !== s.id; });
                    renderChips();
                    renderList($.trim($('#lpSearchInput').val()));
                    updateHiddenInputs();
                });
                $chip.append($x);
                $val.append($chip);
            });
        }

        function updateHiddenInputs() {
            var $c = $('#lpHiddenInputs').empty();
            $.each(selected, function (_, s) { $c.append('<input type="hidden" name="location_ids[]" value="' + s.id + '">'); });
        }

        function clearSelection() {
            selected = [];
            renderChips();
            updateHiddenInputs();
        }

        function updateLayerToggleUi(resp) {
            if (!resp || !resp.next_layer_name) {
                $('#lpLayerToggle').hide();
            } else {
                $('#lpOwnLayerBtn').text(titleCase(resp.own_layer_name)).toggleClass('active', !useNextLayer);
                $('#lpNextLayerBtn').text(titleCase(resp.next_layer_name)).toggleClass('active', useNextLayer);
                $('#lpLayerToggle').show();
            }
            // "Fill from Zone" only makes sense at the own (District) depth —
            // a Zone is always a group of whole districts, never divisions.
            $('#lpZoneFill').toggle(!useNextLayer && !!(resp && resp.own_layer_name));
        }

        function titleCase(s) {
            s = String(s || '').toLowerCase();
            return s.charAt(0).toUpperCase() + s.slice(1);
        }

        function loadNodes() {
            var levelId = $('#teamLevelSelect').val();
            if (!levelId) {
                loaded = false;
                allNodes = [];
                $('#lpLayerToggle').hide();
                $('#lpBody').html('<div class="lp-empty">Select a Team Level first.</div>');
                return;
            }
            var managerId = $('#managerSelect').val() || 0;
            $('#lpBody').html('<div class="lp-loading">Loading&hellip;</div>');
            var url = 'get-ms-location-options.php?team_level_id=' + levelId + '&manager_id=' + managerId
                + '&exclude_ms_id=' + EXCLUDE_MS + (useNextLayer ? '&use_next_layer=1' : '');
            $.getJSON(url, function (resp) {
                loaded = true;
                updateLayerToggleUi(resp);
                // One-time auto-detect: if this person's existing assignment
                // already sits at the deeper (Division) depth, flip the
                // toggle and re-fetch once before the user ever sees the
                // picker, instead of showing it stuck on "District" with
                // their real assignment effectively hidden from new-pick
                // candidates (the already-selected chips still show fine
                // either way — this only affects what's offered to ADD).
                if (!autoDepthChecked) {
                    autoDepthChecked = true;
                    if (!useNextLayer && resp.next_layer_depth && PRESELECTED_DEPTH === resp.next_layer_depth) {
                        useNextLayer = true;
                        loadNodes();
                        return;
                    }
                }
                if (resp.needs_manager) {
                    allNodes = [];
                    $('#lpBody').html('<div class="lp-empty">Select "Reports To" first.</div>');
                    loadZones(0, resp.own_layer_depth);
                    return;
                }
                loadZones(managerId, resp.own_layer_depth);
                if (resp.error === 'no_layer_linked') {
                    allNodes = [];
                    $('#lpBody').html('<div class="lp-empty">This team level has no Location Layer linked. Configure it in Manage Team Levels.</div>');
                    return;
                }
                if (resp.error === 'layer_not_enabled') {
                    allNodes = [];
                    $('#lpBody').html('<div class="lp-empty">Location layer is not enabled for Marketing Staff. Enable "Marketing Staff Filter Enabled" on it in Location Layers.</div>');
                    return;
                }
                allNodes = resp.nodes;
                renderList($.trim($('#lpSearchInput').val()));
            }).fail(function () {
                $('#lpBody').html('<div class="lp-empty">Failed to load. Please try again.</div>');
            });
        }

        $('#lpOwnLayerBtn').on('click', function () {
            if (!useNextLayer) return;
            useNextLayer = false;
            $('#lpOwnLayerBtn').addClass('active');
            $('#lpNextLayerBtn').removeClass('active');
            selected = savedDistrictSelection || [];
            savedDistrictSelection = null;
            renderChips();
            updateHiddenInputs();
            loadNodes();
        });
        $('#lpNextLayerBtn').on('click', function () {
            if (useNextLayer) return;
            // Remember whichever districts are already selected — Division
            // mode narrows down to just these, not the manager's whole area
            // (see loadDivisionsForSelectedDistricts()) — and restore them
            // if the user switches back to District instead of losing them.
            var priorDistrictIds = $.map(selected, function (s) { return s.id; });
            savedDistrictSelection = selected.slice();
            useNextLayer = true;
            $('#lpNextLayerBtn').addClass('active');
            $('#lpOwnLayerBtn').removeClass('active');
            clearSelection();
            if (priorDistrictIds.length) {
                loadDivisionsForSelectedDistricts(priorDistrictIds);
            } else {
                loadNodes();
            }
        });

        function loadDivisionsForSelectedDistricts(districtIds) {
            loaded = false;
            $('#lpBody').html('<div class="lp-loading">Loading&hellip;</div>');
            $.getJSON('get-ms-divisions-for-districts.php?district_ids=' + districtIds.join(',') + '&exclude_ms_id=' + EXCLUDE_MS, function (resp) {
                loaded = true;
                if (resp.error || !resp.nodes || !resp.nodes.length) {
                    allNodes = [];
                    $('#lpBody').html('<div class="lp-empty">No divisions found under the previously-selected district(s).</div>');
                    return;
                }
                allNodes = resp.nodes;
                renderList($.trim($('#lpSearchInput').val()));
            }).fail(function () {
                $('#lpBody').html('<div class="lp-empty">Failed to load. Please try again.</div>');
            });
        }

        // "Fill from Zone" — picking a zone replaces the current selection
        // with every district in that zone, matched against whatever
        // allNodes already holds (a district outside this manager's own
        // scope is silently skipped exactly like picking it manually from
        // the list would behave, rather than trusting the zone's district
        // list blindly). Picking a different zone afterward starts over
        // from that zone's districts rather than piling on top.
        //
        // The zone list itself is scoped to whichever manager is picked
        // above (re-fetched by loadNodes() every time team level/manager
        // changes) — a DM under a given ASM should only see that ASM's own
        // zone(s), not every zone in the company, same as the district list.
        function loadZones(managerId, ownLayerDepth) {
            var url = 'get-ms-zones.php' + (managerId && ownLayerDepth ? '?manager_id=' + managerId + '&depth=' + ownLayerDepth : '');
            $.getJSON(url, function (zones) {
                var $sel = $('#lpZoneSelect');
                var keepVal = $sel.val();
                $sel.empty().append($('<option>').val('').text('-- select a zone --'));
                $.each(zones, function (_, z) {
                    $sel.append($('<option>').val(z.id).text(z.name));
                });
                // A manager was just picked and they have exactly one zone
                // of their own — apply it automatically instead of making
                // the admin pick it again from a dropdown that only has one
                // option anyway. Skips districts already taken by someone
                // else (an automatic action shouldn't silently steal
                // another staff member's territory the way a deliberate
                // manual zone-pick does).
                if (autoFillZoneOnLoad && zones.length === 1) {
                    autoFillZoneOnLoad = false;
                    $sel.val(zones[0].id);
                    applyZoneFill(zones[0].id, zones[0].name, true);
                    return;
                }
                autoFillZoneOnLoad = false;
                if (keepVal && $sel.find('option[value="' + keepVal + '"]').length) {
                    $sel.val(keepVal);
                }
            });
        }

        // Picking a zone replaces the current selection with every district
        // in it, matched against whatever allNodes already holds (a
        // district outside this manager's own scope is silently skipped
        // exactly like picking it manually from the list would behave,
        // rather than trusting the zone's district list blindly). Picking a
        // different zone afterward starts over from that zone's districts
        // rather than piling on top.
        //
        // skipTaken=true (used for the automatic apply-on-manager-pick path
        // above) leaves out districts already assigned to someone else
        // instead of reassigning them — a manual pick from the dropdown
        // still reassigns, since that's a deliberate "this is now their
        // territory" action, not an automatic one.
        function applyZoneFill(zoneId, zoneName, skipTaken) {
            var $status = $('#lpZoneFillStatus');
            if (useNextLayer) { $status.text('Switch back to District first — a Zone fills in whole districts, not divisions.'); $('#lpZoneSelect').val(''); return; }
            if (!loaded || !allNodes.length) { $status.text('Locations are still loading — please wait a moment and try again.'); return; }

            $status.text('Adding…');
            $.getJSON('get-ms-zone-districts.php?zone_id=' + zoneId, function (zoneDistricts) {
                var byId = {};
                $.each(allNodes, function (_, n) { byId[n.id] = n; });

                // Picking a zone replaces the whole selection — it's meant
                // as "this person's territory is now exactly this zone",
                // not an additive pick. Whatever was selected before
                // (manually, from the person's existing assignment, or from
                // a previously-picked zone) is cleared first.
                selected = [];

                var added = 0, reassigned = 0, skippedOutOfScope = 0, skippedTaken = 0;
                $.each(zoneDistricts, function (_, zd) {
                    var node = byId[zd.id];
                    if (!node) { skippedOutOfScope++; return; }
                    if (skipTaken && node.is_taken) { skippedTaken++; return; }
                    selected.push({ id: node.id, name: node.name });
                    if (node.is_taken) { reassigned++; } else { added++; }
                });

                renderChips();
                renderList($.trim($('#lpSearchInput').val()));
                updateHiddenInputs();

                var parts = [];
                if (added) parts.push(added + ' district(s) selected');
                if (reassigned) parts.push(reassigned + ' moved from whoever else had them (on Update)');
                if (skippedTaken) parts.push(skippedTaken + ' already assigned to someone else (skipped)');
                if (skippedOutOfScope) parts.push(skippedOutOfScope + ' outside this manager\'s own district(s)');
                $status.text((zoneName ? zoneName + ': ' : '') + (parts.length ? parts.join(', ') + '.' : 'Nothing to add.'));
            }).fail(function () {
                $status.text('Failed to load that Zone\'s districts. Please try again.');
            });
        }

        $('#lpZoneSelect').on('change', function () {
            var zoneId = $(this).val();
            var zoneName = $(this).find('option:selected').text();
            if (!zoneId) return;
            applyZoneFill(zoneId, zoneName, false);
        });

        $('#locationPanel').on('click', function (e) { e.stopPropagation(); });

        $('#locationPickerControl').on('click', function (e) {
            e.stopPropagation();
            if (!open) {
                open = true;
                $('#locationPickerControl').addClass('open');
                $('#locationPanel').show();
                if (!loaded) loadNodes();
                setTimeout(function () { $('#lpSearchInput').focus(); }, 50);
            } else {
                open = false;
                $('#locationPickerControl').removeClass('open');
                $('#locationPanel').hide();
            }
        });

        $(document).on('click', function () {
            if (open) {
                open = false;
                $('#locationPickerControl').removeClass('open');
                $('#locationPanel').hide();
            }
        });

        $('#lpSearchInput').on('input', function () { renderList($.trim($(this).val())); });

        // ---- Team Level: searchable single-select (like Assign Location) ----
        var teamLevels = [];
        var tlOpen = false;

        function renderTeamLevelList(q) {
            var $body = $('#tlpBody').empty();
            var items = teamLevels;
            if (q) {
                var ql = q.toLowerCase();
                items = items.filter(function (n) { return n.name.toLowerCase().indexOf(ql) >= 0; });
            }
            if (items.length === 0) {
                $body.html('<div class="lp-empty">' + (q ? 'No results for "' + escHtml(q) + '".' : 'No team levels configured. Add one in Manage Team Levels.') + '</div>');
                return;
            }
            $.each(items, function (_, item) {
                var $row = $('<div class="lp-row lp-row-selectable"></div>');
                $row.append($('<span>').text(item.name));
                $row.on('click', function () { selectTeamLevel(item); });
                $body.append($row);
            });
        }

        function selectTeamLevel(item) {
            $('#teamLevelSelect').val(item.id).trigger('change');
            $('#tlpValue').empty().append($('<span>').text(item.name));
            closeTeamLevelPanel();
        }

        function openTeamLevelPanel() {
            tlOpen = true;
            $('#teamLevelPickerControl').addClass('open');
            $('#teamLevelPanel').show();
            renderTeamLevelList('');
            setTimeout(function () { $('#tlpSearchInput').val('').focus(); }, 50);
        }

        function closeTeamLevelPanel() {
            tlOpen = false;
            $('#teamLevelPickerControl').removeClass('open');
            $('#teamLevelPanel').hide();
        }

        $('#teamLevelPanel').on('click', function (e) { e.stopPropagation(); });
        $('#teamLevelPickerControl').on('click', function (e) {
            e.stopPropagation();
            if (!tlOpen) { openTeamLevelPanel(); } else { closeTeamLevelPanel(); }
        });
        $(document).on('click', function () { if (tlOpen) closeTeamLevelPanel(); });
        $('#tlpSearchInput').on('input', function () { renderTeamLevelList($.trim($(this).val())); });

        // ---- Team hierarchy: manager selection based on Team Level ----
        var CURRENT_TEAM_LEVEL_ID = <?php echo (int)$currentTeamLevelId; ?>;
        var CURRENT_MANAGER_ID = <?php echo (int)($result_product_list['manager_id'] ?? 0); ?>;

        function levelAbove(levelId) {
            var idx = -1;
            for (var i = 0; i < teamLevels.length; i++) { if (teamLevels[i].id == levelId) { idx = i; break; } }
            if (idx <= 0) return null;
            return teamLevels[idx - 1];
        }

        function renderChain(chain) {
            var $prev = $('#managerChainPreview').empty();
            if (!chain.length) return;
            var parts = [];
            $.each(chain, function (_, c) {
                parts.push((c.level_name ? c.level_name + ': ' : '') + c.name);
            });
            $prev.text('Reporting chain: ' + parts.join(' → '));
        }

        // ---- Reports To (manager): searchable single-select ----
        var managerOptions = [];
        var mgrOpen = false;

        function renderManagerList(q) {
            var $body = $('#mgrBody').empty();
            var items = managerOptions;
            if (q) {
                var ql = q.toLowerCase();
                items = items.filter(function (n) { return n.name.toLowerCase().indexOf(ql) >= 0; });
            }
            if (items.length === 0) {
                $body.html('<div class="lp-empty">' + (q ? 'No results for "' + escHtml(q) + '".' : 'No staff available at this level yet.') + '</div>');
                return;
            }
            $.each(items, function (_, item) {
                var $row = $('<div class="lp-row lp-row-selectable"></div>');
                $row.append($('<span>').text(item.name));
                $row.on('click', function () { selectManager(item); });
                $body.append($row);
            });
        }

        function setManagerValue(id, name) {
            $('#managerSelect').val(id || '');
            if (name) {
                $('#mgrValue').empty().append($('<span>').text(name));
            } else {
                $('#mgrValue').html('<span class="lp-placeholder">-- Select --</span>');
            }
        }

        function selectManager(item) {
            setManagerValue(item.id, item.name);
            $('#managerSelect').trigger('change');
            closeManagerPanel();
        }

        function openManagerPanel() {
            mgrOpen = true;
            $('#managerPickerControl').addClass('open');
            $('#managerPanel').show();
            renderManagerList('');
            setTimeout(function () { $('#mgrSearchInput').val('').focus(); }, 50);
        }

        function closeManagerPanel() {
            mgrOpen = false;
            $('#managerPickerControl').removeClass('open');
            $('#managerPanel').hide();
        }

        $('#managerPanel').on('click', function (e) { e.stopPropagation(); });
        $('#managerPickerControl').on('click', function (e) {
            e.stopPropagation();
            if (!mgrOpen) { openManagerPanel(); } else { closeManagerPanel(); }
        });
        $(document).on('click', function () { if (mgrOpen) closeManagerPanel(); });
        $('#mgrSearchInput').on('input', function () { renderManagerList($.trim($(this).val())); });

        function loadManagerOptions(levelId, preselectId) {
            var above = levelAbove(levelId);
            if (!above) {
                $('#managerFieldWrap').hide();
                managerOptions = [];
                setManagerValue('', '');
                renderChain([]);
                loadNodes();
                return;
            }
            $('#managerFieldLabel').text('Reports To (' + above.name + ')');
            $('#managerFieldWrap').show();
            $('#mgrValue').html('<span class="lp-placeholder">Loading&hellip;</span>');
            $.getJSON('get-ms-by-level.php?level_id=' + above.id + '&exclude_ms_id=' + EXCLUDE_MS, function (staff) {
                managerOptions = staff;
                var match = null;
                if (preselectId) {
                    $.each(staff, function (_, s) { if (s.id == preselectId) match = s; });
                }
                if (match) {
                    setManagerValue(match.id, match.name);
                    $.getJSON('get-ms-chain.php?ms_id=' + preselectId, function (chain) { renderChain(chain); });
                } else {
                    setManagerValue('', '');
                    renderChain([]);
                }
                loadNodes();
            }).fail(function () {
                $('#mgrValue').html('<span class="lp-placeholder">Failed to load</span>');
            });
        }

        $('#teamLevelSelect').on('change', function () {
            var levelId = $(this).val();
            clearSelection();
            if (!levelId) {
                $('#managerFieldWrap').hide();
                renderChain([]);
                loadNodes();
                return;
            }
            loadManagerOptions(levelId, null);
        });

        $('#managerSelect').on('change', function () {
            var managerId = $(this).val();
            clearSelection();
            if (!managerId) { renderChain([]); loadNodes(); return; }
            $.getJSON('get-ms-chain.php?ms_id=' + managerId, function (chain) {
                renderChain(chain);
            });
            autoFillZoneOnLoad = true;
            loadNodes();
        });

        $.getJSON('get-ms-team-levels.php', function (levels) {
            teamLevels = levels;
            if (CURRENT_TEAM_LEVEL_ID) {
                loadManagerOptions(CURRENT_TEAM_LEVEL_ID, CURRENT_MANAGER_ID);
            } else {
                loadNodes();
            }
        });
    })(jQuery);
    </script>
</body>

</html>