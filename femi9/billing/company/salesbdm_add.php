<?php include("checksession.php"); date_default_timezone_set("Asia/Kolkata");
require_once("include/PermissionCheck.php"); requirePermission('ms');?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Add Sales BDM : <?php echo $business_name;?></title>

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
									<td>Add Sales BDM</td>
									<td><a href="salesbdm_manage" title="Manage Sales BDM">&#9776;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">


									<?php if(isset($_REQUEST['alreadyexists'])){?><div class="alert alert-danger">Sales BDM details already exists !</div>
									<?php }?>

<?php include("validate-scripts.php");?>

<form action="salesbdm_action" method="post" enctype="multipart/form-data">


                                        <div class="example-container">
                                            <div class="example-content">

<label class="form-label">Name*</label>
<input type="text" required="" name="bdm_name" class="form-control" onkeypress="restrictSpecialChars(event)">
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
<?php $selectCountry="select * from country order by id asc";
$fetchCountry=mysqli_query($db_conn,$selectCountry);
while($resultCountry=mysqli_fetch_array($fetchCountry)){?>
<option value='<?php echo $resultCountry['c_code'];?>' ><?php echo $resultCountry['c_name'];?> (<?php echo $resultCountry['c_code'];?>)</option>
<?php }?>
</select>
            </div>
            <div class="mobile-number">
                <label class="form-label">Mobile Number (Username)*</label>
                <input type="text" required name="bdm_mobile" onkeypress="restrictnumber(event)" pattern="[1-9]{1}[0-9]{9}" class="form-control" maxlength="10">
            </div>
        </div>
        <!-- New add end -->
</br>

<label class="form-label">Email ID</label>
<input type="email" name="bdm_email" class="form-control" placeholder="optional" onkeypress="restrictemail(event)">
</br>

<label class="form-label">Address</label>
<textarea name="bdm_address" class="form-control" onkeypress="restrictSpecialChars(event)" placeholder="optional"></textarea>
</br>

<label class="form-label">Monthly Target Amount (&#8377;)</label>
<input type="number" step="0.01" min="0" name="monthly_target_amount" class="form-control" placeholder="optional">
</br>

<?php
$_chkTL = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'team_level_id'");
if ($_chkTL && $_chkTL->num_rows === 0) {
    $db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN team_level_id INT NULL DEFAULT NULL AFTER user_position");
}
$db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_team_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_rank INT NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    location_layer_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_level_rank (level_rank)
)");
?>
<label class="form-label">Team Level</label>
<div class="lp-wrapper" id="teamLevelPickerWrapper">
    <div class="lp-control" id="teamLevelPickerControl">
        <div class="lp-value" id="tlpValue">
            <span class="lp-placeholder">-- None --</span>
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
<input type="hidden" name="team_level_id" id="teamLevelSelect" value="">
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

<div id="dualRoleWrap" style="display:none;">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="dualRoleCheck">
        <label class="form-check-label" for="dualRoleCheck" id="dualRoleCheckLabel">Also directly handle some locations one level down</label>
    </div>
    <div id="dualRolePickerBlock" style="display:none;margin-top:10px;">
        <label class="form-label" id="dualRoleLabel">Assign Location (one level down)</label>
        <div class="lp-wrapper" id="dualLocationPickerWrapper">
            <div class="lp-control" id="dualLocationPickerControl">
                <div class="lp-value" id="dualLpValue">
                    <span class="lp-placeholder">Select locations&hellip;</span>
                </div>
                <div class="lp-arrow"><i class="material-icons" style="font-size:18px;color:#999;">arrow_drop_down</i></div>
            </div>
            <div class="lp-panel" id="dualLocationPanel" style="display:none;">
                <div class="lp-search-box">
                    <i class="material-icons" style="font-size:18px;color:#aaa;">search</i>
                    <input type="text" id="dualLpSearchInput" placeholder="Search locations&hellip;" autocomplete="off">
                </div>
                <div class="lp-body" id="dualLpBody"><div class="lp-loading">Loading&hellip;</div></div>
            </div>
        </div>
        <div id="dualLpHiddenInputs"></div>
        <small class="text-muted">Pick the locations you personally handle within what you selected above. Already-assigned ones are shown locked.</small>
    </div>
    <br/>
</div>

<button type="submit" name="add-salesbdm" class="btn btn-primary"><i class="material-icons">add</i>Add</button>

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
        var selected = [];
        var open     = false;
        var loaded   = false;

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
                $body.html('<div class="lp-empty">' + (q ? 'No results for "' + escHtml(q) + '".' : 'No locations available. Ask admin to enable "Sales BDM Filter" on a Location Layer.') + '</div>');
                return;
            }
            var curLayer = null;
            $.each(nodes, function (_, node) {
                if (node.layer_name && node.layer_name !== curLayer) {
                    curLayer = node.layer_name;
                    $body.append('<div style="padding:5px 14px 4px;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.8px;border-bottom:1px solid #f0f0f0;">' + escHtml(curLayer) + '</div>');
                }
                var $row = $('<div class="lp-row"></div>');
                if (node.is_taken) {
                    $row.addClass('lp-row-taken');
                    $row.append('<i class="material-icons lp-lock" style="font-size:16px;">lock</i>');
                    $row.append($('<span>').text(node.name));
                } else if (isSelected(node.id)) {
                    $row.addClass('lp-row-selectable lp-row-selected');
                    $row.append('<i class="material-icons lp-check" style="font-size:16px;">check</i>');
                    $row.append($('<span>').text(node.name));
                    $row.on('click', function () { toggleSelect(node); });
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
            refreshDualPickerIfOpen();
        }

        function clearSelection() {
            selected = [];
            renderChips();
            updateHiddenInputs();
        }

        function loadNodes() {
            var levelId = $('#teamLevelSelect').val();
            if (!levelId) {
                loaded = false;
                allNodes = [];
                $('#lpBody').html('<div class="lp-empty">Select a Team Level first.</div>');
                return;
            }
            var managerId = $('#managerSelect').val() || 0;
            $('#lpBody').html('<div class="lp-loading">Loading&hellip;</div>');
            $.getJSON('get-salesbdm-location-options.php?team_level_id=' + levelId + '&manager_id=' + managerId + '&exclude_bdm_id=0', function (resp) {
                loaded = true;
                if (resp.needs_manager) {
                    allNodes = [];
                    $('#lpBody').html('<div class="lp-empty">Select "Reports To" first.</div>');
                    return;
                }
                if (resp.error === 'no_layer_linked') {
                    allNodes = [];
                    $('#lpBody').html('<div class="lp-empty">This team level has no Location Layer linked. Configure it in Manage Team Levels.</div>');
                    return;
                }
                if (resp.error === 'layer_not_enabled') {
                    allNodes = [];
                    $('#lpBody').html('<div class="lp-empty">Location layer is not enabled for Sales BDM. Enable "Sales BDM Filter Enabled" on it in Location Layers.</div>');
                    return;
                }
                allNodes = resp.nodes;
                renderList($.trim($('#lpSearchInput').val()));
            }).fail(function () {
                $('#lpBody').html('<div class="lp-empty">Failed to load. Please try again.</div>');
            });
        }

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

        function loadManagerOptions(levelId) {
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
            setManagerValue('', null);
            $('#mgrValue').html('<span class="lp-placeholder">Loading&hellip;</span>');
            $.getJSON('get-salesbdm-by-level.php?level_id=' + above.id + '&exclude_bdm_id=0', function (staff) {
                managerOptions = staff;
                setManagerValue('', '');
                renderChain([]);
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
            loadManagerOptions(levelId);
        });

        $('#managerSelect').on('change', function () {
            var managerId = $(this).val();
            clearSelection();
            if (!managerId) { renderChain([]); loadNodes(); return; }
            $.getJSON('get-salesbdm-chain.php?bdm_id=' + managerId, function (chain) {
                renderChain(chain);
            });
            loadNodes();
        });

        // ---- Optional dual role: "also directly handle locations one level
        // down" — e.g. a Chief BDM (State) who personally also owns specific
        // Districts. Only offered when the selected Team Level's own layer has
        // a layer one depth below it configured among the Team Levels list.
        var dualSelected  = [];
        var dualOpen      = false;
        var dualLoaded    = false;
        var nextDepthInfo = null; // { depth, levelName }

        function findNextDepthInfo(levelId) {
            var cur = null;
            $.each(teamLevels, function (_, lvl) { if (lvl.id == levelId) { cur = lvl; } });
            if (!cur || cur.layer_depth === null || cur.layer_depth === undefined) { return null; }
            var match = null;
            $.each(teamLevels, function (_, lvl) {
                if (lvl.layer_depth !== null && lvl.layer_depth !== undefined && lvl.layer_depth === cur.layer_depth + 1) {
                    match = lvl;
                }
            });
            return match ? { depth: match.layer_depth, levelName: match.name } : null;
        }

        function resetDualRole() {
            dualSelected = [];
            dualLoaded = false;
            $('#dualRoleCheck').prop('checked', false);
            $('#dualRolePickerBlock').hide();
            renderDualChips();
            updateDualHiddenInputs();
        }

        function updateDualRoleAvailability(levelId) {
            resetDualRole();
            if (!levelId) { $('#dualRoleWrap').hide(); nextDepthInfo = null; return; }
            nextDepthInfo = findNextDepthInfo(levelId);
            if (!nextDepthInfo) { $('#dualRoleWrap').hide(); return; }
            $('#dualRoleCheckLabel').text('Also directly handle some ' + nextDepthInfo.levelName + ' locations');
            $('#dualRoleLabel').text('Assign ' + nextDepthInfo.levelName + ' (one level down)');
            $('#dualRoleWrap').show();
        }

        function refreshDualPickerIfOpen() {
            if (!nextDepthInfo || !$('#dualRoleCheck').is(':checked')) { return; }
            dualLoaded = false;
            if (dualOpen) { loadDualNodes(); }
        }

        function isDualSelected(id) {
            for (var i = 0; i < dualSelected.length; i++) { if (dualSelected[i].id === id) return true; }
            return false;
        }

        function toggleDualSelect(node) {
            var idx = -1;
            for (var i = 0; i < dualSelected.length; i++) { if (dualSelected[i].id === node.id) { idx = i; break; } }
            if (idx >= 0) { dualSelected.splice(idx, 1); } else { dualSelected.push({ id: node.id, name: node.name }); }
            renderDualChips();
            renderDualList($.trim($('#dualLpSearchInput').val()));
            updateDualHiddenInputs();
        }

        function renderDualList(q) {
            var $body = $('#dualLpBody').empty();
            var nodes = window.__dualAllNodes || [];
            if (q) {
                var ql = q.toLowerCase();
                nodes = nodes.filter(function (n) { return n.name.toLowerCase().indexOf(ql) >= 0; });
            }
            if (nodes.length === 0) {
                $body.html('<div class="lp-empty">' + (q ? 'No results for "' + escHtml(q) + '".' : 'Select a location above first.') + '</div>');
                return;
            }
            $.each(nodes, function (_, node) {
                var $row = $('<div class="lp-row"></div>');
                if (node.is_taken) {
                    $row.addClass('lp-row-taken');
                    $row.append('<i class="material-icons lp-lock" style="font-size:16px;">lock</i>');
                    $row.append($('<span>').text(node.name));
                } else if (isDualSelected(node.id)) {
                    $row.addClass('lp-row-selectable lp-row-selected');
                    $row.append('<i class="material-icons lp-check" style="font-size:16px;">check</i>');
                    $row.append($('<span>').text(node.name));
                    $row.on('click', function () { toggleDualSelect(node); });
                } else {
                    $row.addClass('lp-row-selectable');
                    $row.append($('<span>').text(node.name));
                    $row.on('click', function () { toggleDualSelect(node); });
                }
                $body.append($row);
            });
        }

        function renderDualChips() {
            var $val = $('#dualLpValue').empty();
            if (dualSelected.length === 0) { $val.html('<span class="lp-placeholder">Select locations&hellip;</span>'); return; }
            $.each(dualSelected, function (_, s) {
                var $chip = $('<span class="lp-chip"></span>');
                $chip.append($('<span>').text(s.name));
                var $x = $('<span class="lp-chip-remove">&times;</span>');
                $x.on('click', function (e) {
                    e.stopPropagation();
                    dualSelected = dualSelected.filter(function (r) { return r.id !== s.id; });
                    renderDualChips();
                    renderDualList($.trim($('#dualLpSearchInput').val()));
                    updateDualHiddenInputs();
                });
                $chip.append($x);
                $val.append($chip);
            });
        }

        function updateDualHiddenInputs() {
            var $c = $('#dualLpHiddenInputs').empty();
            $.each(dualSelected, function (_, s) { $c.append('<input type="hidden" name="dual_location_ids[]" value="' + s.id + '">'); });
        }

        function loadDualNodes() {
            if (!nextDepthInfo) { return; }
            if (!selected.length) {
                window.__dualAllNodes = [];
                $('#dualLpBody').html('<div class="lp-empty">Select a location above first.</div>');
                return;
            }
            var qs = $.param({ target_depth: nextDepthInfo.depth, exclude_bdm_id: 0 }) +
                     '&' + $.param({ parent_ids: selected.map(function (s) { return s.id; }) });
            $('#dualLpBody').html('<div class="lp-loading">Loading&hellip;</div>');
            $.getJSON('get-salesbdm-child-locations.php?' + qs, function (resp) {
                dualLoaded = true;
                window.__dualAllNodes = resp.nodes || [];
                renderDualList($.trim($('#dualLpSearchInput').val()));
            }).fail(function () {
                $('#dualLpBody').html('<div class="lp-empty">Failed to load. Please try again.</div>');
            });
        }

        $('#dualLocationPanel').on('click', function (e) { e.stopPropagation(); });
        $('#dualLocationPickerControl').on('click', function (e) {
            e.stopPropagation();
            if (!dualOpen) {
                dualOpen = true;
                $('#dualLocationPickerControl').addClass('open');
                $('#dualLocationPanel').show();
                if (!dualLoaded) loadDualNodes();
                setTimeout(function () { $('#dualLpSearchInput').focus(); }, 50);
            } else {
                dualOpen = false;
                $('#dualLocationPickerControl').removeClass('open');
                $('#dualLocationPanel').hide();
            }
        });
        $(document).on('click', function () {
            if (dualOpen) {
                dualOpen = false;
                $('#dualLocationPickerControl').removeClass('open');
                $('#dualLocationPanel').hide();
            }
        });
        $('#dualLpSearchInput').on('input', function () { renderDualList($.trim($(this).val())); });

        $('#dualRoleCheck').on('change', function () {
            if ($(this).is(':checked')) {
                $('#dualRolePickerBlock').show();
                dualLoaded = false;
                loadDualNodes();
            } else {
                $('#dualRolePickerBlock').hide();
                dualSelected = [];
                renderDualChips();
                updateDualHiddenInputs();
            }
        });

        $.getJSON('get-salesbdm-team-levels.php', function (levels) {
            teamLevels = levels;
            var restoredLevelId = $('#teamLevelSelect').val();
            if (restoredLevelId) {
                $.each(teamLevels, function (_, lvl) {
                    if (lvl.id == restoredLevelId) { $('#tlpValue').empty().append($('<span>').text(lvl.name)); }
                });
                loadManagerOptions(restoredLevelId);
                updateDualRoleAvailability(restoredLevelId);
            } else {
                loadNodes();
            }
        });

        $('#teamLevelSelect').on('change', function () {
            updateDualRoleAvailability($(this).val());
        });
    })(jQuery);
    </script>
</body>

</html>
