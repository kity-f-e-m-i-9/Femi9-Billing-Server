<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//fetch product details
$select_product_list="select * from marketing_staff where id='$prid'";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
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
    SELECT n.id, n.name
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

<label class="form-label">Marketing Head
<?php if($result_product_list['user_position']==1){?>
       <input type="checkbox" name="user_position" value="1" checked>&nbsp;Enable</label>
<?php }else{?><input type="checkbox" name="user_position" value="1">&nbsp;Enable</label><?php }?>
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
        }

        function loadNodes() {
            $('#lpBody').html('<div class="lp-loading">Loading&hellip;</div>');
            $.getJSON('get-ms-flat-nodes.php?exclude_ms_id=' + EXCLUDE_MS, function (nodes) {
                allNodes = nodes;
                loaded = true;
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
    })(jQuery);
    </script>
</body>

</html>