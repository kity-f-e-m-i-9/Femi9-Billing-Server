<?php
include("checksession.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$enc_id   = $_GET['tpid'] ?? '';
$tp_db_id = (int)base64_decode($enc_id);
if (!$tp_db_id) { header("Location: manage-territory-partner"); exit; }

// Fetch TP with ownership check
$stmt = $db_conn->prepare("SELECT * FROM territory_partners WHERE id=? AND onboard_ss_id=?");
$stmt->bind_param("is", $tp_db_id, $Login_user_IDvl);
$stmt->execute();
$tp = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tp) { header("Location: manage-territory-partner?error=notfound"); exit; }

// Fetch current location assignments
$loc_stmt = $db_conn->prepare("
    SELECT tpl.location_id, pln.name, COALESCE(pln.target_amount,0) AS target_amount
    FROM territory_partner_locations tpl
    JOIN partner_location_nodes pln ON pln.id = tpl.location_id
    WHERE tpl.territory_partner_id = ?
");
$loc_stmt->bind_param("i", $tp_db_id);
$loc_stmt->execute();
$current_locs = $loc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$loc_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Territory Partner : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
    .lp-wrapper { position: relative; }
    .lp-control { display:flex; align-items:center; min-height:38px; border:1px solid #ced4da; border-radius:4px; background:#fff; padding:2px 8px; cursor:pointer; user-select:none; }
    .lp-control:hover { border-color:#adb5bd; }
    .lp-control.open { border-color:#80bdff; box-shadow:0 0 0 0.2rem rgba(0,123,255,.25); }
    .lp-value { flex:1; display:flex; flex-wrap:wrap; gap:4px; min-height:28px; align-items:center; padding:2px 0; }
    .lp-placeholder { color:#aaa; font-size:13px; }
    .lp-arrow { margin-left:6px; display:flex; align-items:center; }
    .lp-chip { display:inline-flex; align-items:center; gap:4px; background:#e9ecef; border-radius:3px; padding:2px 6px; font-size:12px; max-width:180px; }
    .lp-chip span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .lp-chip-remove { cursor:pointer; font-size:15px; line-height:1; color:#888; flex-shrink:0; }
    .lp-chip-remove:hover { color:#dc3545; }
    .lp-panel { position:absolute; top:calc(100% + 3px); left:0; right:0; background:#fff; border:1px solid #ced4da; border-radius:4px; z-index:1050; box-shadow:0 4px 16px rgba(0,0,0,.12); display:flex; flex-direction:column; max-height:280px; }
    .lp-body { overflow-y:auto; flex:1; }
    .lp-row { display:flex; align-items:center; gap:8px; padding:9px 14px; font-size:13px; border-bottom:1px solid #f5f5f5; cursor:pointer; }
    .lp-row:last-child { border-bottom:none; }
    .lp-row-selectable { color:#333; }
    .lp-row-selectable:hover { background:#f8f9fa; }
    .lp-row-selected { background:#e8f0fe; color:#1a73e8; font-weight:500; }
    .lp-row-selected:hover { background:#d2e3fc; }
    .lp-row-taken { color:#bbb; cursor:not-allowed; }
    .lp-row-taken:hover { background:#fff; }
    .lp-row .lp-check { color:#1a73e8; flex-shrink:0; font-size:16px; }
    .lp-row .lp-lock { color:#ccc; flex-shrink:0; font-size:16px; }
    .lp-empty, .lp-loading { padding:16px; text-align:center; font-size:13px; color:#aaa; }
    .lp-search-box { padding:7px 10px; border-bottom:1px solid #f0f0f0; flex-shrink:0; display:flex; align-items:center; gap:6px; }
    .lp-search-box input { flex:1; border:1px solid #ced4da; border-radius:4px; padding:5px 10px; font-size:13px; outline:none; font-family:inherit; }
    .lp-search-box input:focus { border-color:#80bdff; box-shadow:0 0 0 .15rem rgba(0,123,255,.2); }
    .lp-target-amt { margin-left:auto; font-size:11px; color:#78909c; white-space:nowrap; padding-left:8px; flex-shrink:0; }
    #lpTargetSummary { margin-top:8px; padding:8px 12px; background:#fff8e1; border-left:4px solid #f59e0b; border-radius:4px; font-size:13px; }
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

                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td>Edit Territory Partner</td>
                                            <td><a href="manage-territory-partner" title="Back to List">&#9776;</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_GET['mobiletaken'])): ?>
                        <div class="alert alert-danger mb-3">This mobile number is already registered to another partner.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger mb-3">An error occurred. Please try again.</div>
                    <?php endif; ?>

                    <form action="territory-partner-action" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update-territory-partner">
                        <input type="hidden" name="tp_db_id" value="<?php echo $enc_id; ?>">

                    <div class="row">

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <?php include("validate-scripts.php"); ?>
                                    <div class="example-container">
                                        <div class="example-content">

                                            <div class="mb-3">
                                                <label class="form-label">TP ID</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($tp['tp_id']); ?>" readonly style="background:#f8f9fa;">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                                <input type="text" name="tp_name" class="form-control" maxlength="100" required autofocus
                                                       value="<?php echo htmlspecialchars($tp['name']); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                                <input type="text" name="tp_company_name" class="form-control" maxlength="255" required
                                                       value="<?php echo htmlspecialchars($tp['company_name'] ?? ''); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Mobile <span class="text-danger">*</span></label>
                                                <input type="text" name="tp_mobile" class="form-control" maxlength="15"
                                                       pattern="[0-9]{10,15}" required
                                                       value="<?php echo htmlspecialchars($tp['mobile']); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" name="tp_email" class="form-control" maxlength="100"
                                                       value="<?php echo htmlspecialchars($tp['email'] ?? ''); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">GST Number</label>
                                                <input type="text" name="tp_gstin" class="form-control" maxlength="20"
                                                       value="<?php echo htmlspecialchars($tp['gstin'] ?? ''); ?>">
                                            </div>

                                            <!-- Billing Address -->
                                            <div class="mb-1"><label class="form-label fw-semibold">Billing Address <span class="text-danger">*</span></label></div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-12"><input type="text" name="tp_branch_line1" class="form-control" maxlength="255" placeholder="Line 1 *" required value="<?php echo htmlspecialchars($tp['branch_line1'] ?? ''); ?>"></div>
                                                <div class="col-12"><input type="text" name="tp_branch_line2" class="form-control" maxlength="255" placeholder="Line 2" value="<?php echo htmlspecialchars($tp['branch_line2'] ?? ''); ?>"></div>
                                                <div class="col-6"><input type="text" name="tp_branch_city" class="form-control" maxlength="100" placeholder="City" value="<?php echo htmlspecialchars($tp['branch_city'] ?? ''); ?>"></div>
                                                <div class="col-6"><input type="text" name="tp_branch_district" class="form-control" maxlength="100" placeholder="District" value="<?php echo htmlspecialchars($tp['branch_district'] ?? ''); ?>"></div>
                                                <div class="col-6"><input type="text" name="tp_branch_state" class="form-control" maxlength="100" placeholder="State" value="<?php echo htmlspecialchars($tp['branch_state'] ?? ''); ?>"></div>
                                                <div class="col-6"><input type="text" name="tp_branch_country" class="form-control" maxlength="100" placeholder="Country" value="<?php echo htmlspecialchars($tp['branch_country'] ?? ''); ?>"></div>
                                                <div class="col-6"><input type="text" name="tp_branch_pincode" id="tp_branch_pincode" class="form-control" maxlength="20" placeholder="Pincode" value="<?php echo htmlspecialchars($tp['branch_pincode'] ?? ''); ?>"></div>
                                            </div>

                                            <!-- Delivery Address -->
                                            <div class="mb-1 d-flex align-items-center gap-2">
                                                <label class="form-label fw-semibold mb-0">Delivery Address <span class="text-danger">*</span></label>
                                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="copyBranchToDelivery" style="font-size:11px;padding:2px 8px;">
                                                    <i class="material-icons" style="font-size:13px;vertical-align:middle;">content_copy</i> Same as Billing
                                                </button>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-12"><input type="text" name="tp_delivery_line1" id="tp_delivery_line1" class="form-control" maxlength="255" placeholder="Line 1 *" required value="<?php echo htmlspecialchars($tp['delivery_line1'] ?? ''); ?>"></div>
                                                <div class="col-12"><input type="text" name="tp_delivery_line2" id="tp_delivery_line2" class="form-control" maxlength="255" placeholder="Line 2" value="<?php echo htmlspecialchars($tp['delivery_line2'] ?? ''); ?>"></div>
                                                <div class="col-6"><input type="text" name="tp_delivery_city" id="tp_delivery_city" class="form-control" maxlength="100" placeholder="City" value="<?php echo htmlspecialchars($tp['delivery_city'] ?? ''); ?>"></div>
                                                <div class="col-6"><input type="text" name="tp_delivery_district" id="tp_delivery_district" class="form-control" maxlength="100" placeholder="District" value="<?php echo htmlspecialchars($tp['delivery_district'] ?? ''); ?>"></div>
                                                <div class="col-6"><input type="text" name="tp_delivery_state" id="tp_delivery_state" class="form-control" maxlength="100" placeholder="State" value="<?php echo htmlspecialchars($tp['delivery_state'] ?? ''); ?>"></div>
                                                <div class="col-6"><input type="text" name="tp_delivery_country" id="tp_delivery_country" class="form-control" maxlength="100" placeholder="Country" value="<?php echo htmlspecialchars($tp['delivery_country'] ?? ''); ?>"></div>
                                                <div class="col-6"><input type="text" name="tp_delivery_pincode" id="tp_delivery_pincode" class="form-control" maxlength="20" placeholder="Pincode" value="<?php echo htmlspecialchars($tp['delivery_pincode'] ?? ''); ?>"></div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Photo <span class="text-muted" style="font-size:12px;">(optional, leave blank to keep current)</span></label>
                                                <?php if ($tp['photo']): ?>
                                                    <div class="mb-2">
                                                        <img src="tp_photo/<?php echo htmlspecialchars($tp['photo']); ?>"
                                                             style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;">
                                                    </div>
                                                <?php endif; ?>
                                                <input type="file" name="tp_photo" class="form-control" accept="image/*">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select name="tp_active" class="form-control">
                                                    <option value="1" <?php echo $tp['is_active'] ? 'selected' : ''; ?>>Active</option>
                                                    <option value="0" <?php echo !$tp['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Location Picker -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted mb-3" style="font-size:13px;">
                                        <i class="material-icons-two-tone" style="vertical-align:middle;font-size:16px;">place</i>
                                        Select the locations this partner will manage.
                                    </p>
                                    <div class="mb-3">
                                        <label class="form-label">Assigned Locations</label>
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
                                        <div id="lpHiddenInputs">
                                            <?php foreach ($current_locs as $cl): ?>
                                                <input type="hidden" name="location_ids[]" value="<?php echo (int)$cl['location_id']; ?>">
                                            <?php endforeach; ?>
                                        </div>
                                        <div id="lpTargetSummary" style="display:none;">
                                            <span style="color:#92400e;">Total Target:</span>
                                            <strong id="lpTargetTotal" style="color:#b45309;margin-left:6px;">₹0</strong>
                                        </div>
                                        <small class="text-muted">Already-assigned locations (by other partners) are locked.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /.row -->

                    <div class="row mt-1 mb-3">
                        <div class="col">
                            <button type="submit" name="update-territory-partner" class="btn btn-primary">
                                <i class="material-icons">save</i> Save Changes
                            </button>
                            <a href="manage-territory-partner" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </div>

                    </form>

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
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
// Pre-loaded current locations from PHP
var PRELOADED = <?php echo json_encode(array_map(function($l) {
    return ['id' => (int)$l['location_id'], 'name' => $l['name'], 'target' => (float)$l['target_amount']];
}, $current_locs)); ?>;
var EXCLUDE_TP = <?php echo $tp_db_id; ?>;

(function ($) {
    var allNodes = [];
    var selected = PRELOADED.slice();
    var open     = false;
    var loaded   = false;

    function escHtml(str) { return $('<div>').text(str).html(); }
    function fmtAmount(v) { if (!v || v <= 0) return ''; return '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 }); }
    function isSelected(id) { for (var i=0;i<selected.length;i++){if(selected[i].id===id)return true;} return false; }

    function toggleSelect(node) {
        var idx=-1; for(var i=0;i<selected.length;i++){if(selected[i].id===node.id){idx=i;break;}}
        if(idx>=0){selected.splice(idx,1);}else{selected.push({id:node.id,name:node.name,target:parseFloat(node.target_amount)||0});}
        renderChips(); renderList($.trim($('#lpSearchInput').val())); updateHiddenInputs(); updateTargetSummary();
    }

    function renderList(q) {
        var $body=$('#lpBody').empty(); var nodes=allNodes;
        if(q){var ql=q.toLowerCase();nodes=nodes.filter(function(n){return n.name.toLowerCase().indexOf(ql)>=0;});}
        if(nodes.length===0){$body.html('<div class="lp-empty">'+(q?'No results.':'No locations available.')+'</div>');return;}
        var curLayer=null;
        $.each(nodes,function(_,node){
            if(node.layer_name&&node.layer_name!==curLayer){curLayer=node.layer_name;$body.append('<div style="padding:5px 14px 4px;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.8px;border-bottom:1px solid #f0f0f0;">'+escHtml(curLayer)+'</div>');}
            var $row=$('<div class="lp-row"></div>');
            if(node.is_taken){$row.addClass('lp-row-taken');$row.append('<i class="material-icons lp-lock" style="font-size:16px;">lock</i>');$row.append($('<span>').text(node.name));}
            else if(isSelected(node.id)){$row.addClass('lp-row-selectable lp-row-selected');$row.append('<i class="material-icons lp-check" style="font-size:16px;">check</i>');$row.append($('<span>').text(node.name));var _ta=parseFloat(node.target_amount)||0;if(_ta>0)$row.append($('<span class="lp-target-amt">').text(fmtAmount(_ta)));$row.on('click',function(){toggleSelect(node);});}
            else{$row.addClass('lp-row-selectable');$row.append($('<span>').text(node.name));var _ta=parseFloat(node.target_amount)||0;if(_ta>0)$row.append($('<span class="lp-target-amt">').text(fmtAmount(_ta)));$row.on('click',function(){toggleSelect(node);});}
            $body.append($row);
        });
    }

    function renderChips() {
        var $val=$('#lpValue').empty();
        if(selected.length===0){$val.html('<span class="lp-placeholder">Select locations&hellip;</span>');return;}
        $.each(selected,function(_,s){
            var $chip=$('<span class="lp-chip"></span>');$chip.append($('<span>').text(s.name));
            var $x=$('<span class="lp-chip-remove">&times;</span>');
            $x.on('click',function(e){e.stopPropagation();selected=selected.filter(function(r){return r.id!==s.id;});renderChips();renderList($.trim($('#lpSearchInput').val()));updateHiddenInputs();updateTargetSummary();});
            $chip.append($x);$val.append($chip);
        });
    }

    function updateHiddenInputs() {
        var $c=$('#lpHiddenInputs').empty();
        $.each(selected,function(_,s){$c.append('<input type="hidden" name="location_ids[]" value="'+s.id+'">');});
    }

    function updateTargetSummary() {
        var total=0; $.each(selected,function(_,s){total+=(s.target||0);});
        if(total>0){$('#lpTargetTotal').text(fmtAmount(total));$('#lpTargetSummary').show();}else{$('#lpTargetSummary').hide();}
    }

    function loadNodes() {
        $('#lpBody').html('<div class="lp-loading">Loading&hellip;</div>');
        $.getJSON('get-tp-flat-nodes.php?exclude_tp_id='+EXCLUDE_TP,function(nodes){
            allNodes=nodes; loaded=true; renderChips(); renderList($.trim($('#lpSearchInput').val())); updateTargetSummary();
        }).fail(function(){$('#lpBody').html('<div class="lp-empty">Failed to load.</div>');});
    }

    $('#locationPanel').on('click',function(e){e.stopPropagation();});
    $('#locationPickerControl').on('click',function(e){
        e.stopPropagation();
        if(!open){open=true;$('#locationPickerControl').addClass('open');$('#locationPanel').show();if(!loaded)loadNodes();setTimeout(function(){$('#lpSearchInput').focus();},50);}
        else{open=false;$('#locationPickerControl').removeClass('open');$('#locationPanel').hide();}
    });
    $(document).on('click',function(e){if(!$(e.target).closest('#locationPickerWrapper').length&&open){open=false;$('#locationPickerControl').removeClass('open');$('#locationPanel').hide();}});
    $('#lpSearchInput').on('input',function(e){e.stopPropagation();renderList($.trim($(this).val()));}).on('click',function(e){e.stopPropagation();});

    // Render pre-loaded chips on page load
    renderChips();
    updateTargetSummary();
}(jQuery));

document.getElementById('copyBranchToDelivery').addEventListener('click', function () {
    [['tp_branch_line1','tp_delivery_line1'],['tp_branch_line2','tp_delivery_line2'],
     ['tp_branch_city','tp_delivery_city'],['tp_branch_district','tp_delivery_district'],
     ['tp_branch_state','tp_delivery_state'],['tp_branch_country','tp_delivery_country'],
     ['tp_branch_pincode','tp_delivery_pincode']].forEach(function(p){
        var s=document.querySelector('[name="'+p[0]+'"]'),d=document.getElementById(p[1]);
        if(s&&d)d.value=s.value;
    });
});
</script>
</body>
</html>
