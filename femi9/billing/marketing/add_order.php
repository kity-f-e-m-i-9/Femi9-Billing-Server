<?php 
error_reporting(E_ALL);
ini_set('display_errors', '1');

include("checksession.php");
include("config.php");
require_once("include/AssignedLocations.php");

// District Filter is scoped to this DM's assigned locations (not every
// district their shop history happens to touch) — same source as add_ss.php.
$assignedDistricts = getMsAssignedDistricts($db_conn, (int)$markeingSTFID);

// Firka (depth 6) options per taluk, and which TP (if any) is assigned to
// each firka — territory_partner_locations is the TP side's own location
// assignment, same table used by territory-partner/geo_layers.php. Picking
// a firka on the Get Order form auto-detects and locks the TP field instead
// of the DM having to guess who covers that area.
$allTalukIds = [];
foreach ($assignedDistricts as $d) {
    foreach ($d['taluks'] as $t) { $allTalukIds[] = $t['id']; }
}

$talukFirkaMap = [];
if (!empty($allTalukIds)) {
    $talukIdList = implode(',', array_map('intval', $allTalukIds));
    $resFirka = $db_conn->query(
        "SELECT id, name, parent_id FROM partner_location_nodes
         WHERE depth=6 AND parent_id IN ($talukIdList) AND is_active=1 ORDER BY name ASC"
    );
    while ($f = mysqli_fetch_assoc($resFirka)) {
        $talukFirkaMap[$f['parent_id']][] = ['id' => (int)$f['id'], 'name' => $f['name']];
    }
}

$firkaTpMap = [];
$allFirkaIds = [];
foreach ($talukFirkaMap as $list) { foreach ($list as $f) { $allFirkaIds[] = $f['id']; } }
if (!empty($allFirkaIds)) {
    $firkaIdList = implode(',', array_map('intval', $allFirkaIds));
    $resTpLoc = $db_conn->query(
        "SELECT tpl.location_id, tp.id AS tp_id, tp.name AS tp_name
         FROM territory_partner_locations tpl
         JOIN territory_partners tp ON tp.id=tpl.territory_partner_id
         WHERE tpl.location_id IN ($firkaIdList) AND tp.is_active=1"
    );
    while ($tr = mysqli_fetch_assoc($resTpLoc)) {
        $firkaTpMap[(int)$tr['location_id']] = ['id' => (int)$tr['tp_id'], 'name' => $tr['tp_name']];
    }
}

$title         = "New Order";
$manage_url    = "manage_order";
$manage_title  = "Manage Orders";
$message_title = "Order";

date_default_timezone_set("Asia/Kolkata");
$order_date = date("Y-m-d");

// ── GeraHash defined ONCE at the top ────────────────────────────────────────
function GeraHash($qtd) {
    $Caracteres           = '123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $QuantidadeCaracteres = strlen($Caracteres) - 1;
    $Hash = '';
    for ($x = 1; $x <= $qtd; $x++) {
        $Hash .= substr($Caracteres, rand(0, $QuantidadeCaracteres), 1);
    }
    return $Hash;
}

// ── Generate tempID once — reused for whichever form renders ─────────────────
$tempID = GeraHash(32) . date("dmy") . date("gis");

// ── Fetch all shops for this staff member — one query for all dropdowns ───────
// district_name/taluk_name are free text (source of the "Erode" / "Erode/" /
// "Erode-" duplicate-dropdown problem) — district_node_id/taluk_node_id were
// backfilled against the clean partner_location_nodes hierarchy, so the
// District/Taluk filters below group by that id (one clean spelling per real
// place) instead of the raw text. Shops that couldn't be matched (free-text
// typo/out-of-state) fall back into a single "Other / Unmapped" bucket rather
// than being lost.
$shopList    = [];
$districtSet = []; // districtKey (node id, or 0 = unmapped) => display name

if (!empty($markeingSTFID)) {
    $msid_esc = mysqli_real_escape_string($db_conn, $markeingSTFID);
    $r = mysqli_query($db_conn,
        "SELECT s.id, s.name, s.district_name, s.taluk_name, s.latitude, s.longitude,
                s.district_node_id, s.taluk_node_id,
                dn.name AS clean_district_name, tn.name AS clean_taluk_name
         FROM ms_shop s
         LEFT JOIN partner_location_nodes dn ON dn.id = s.district_node_id
         LEFT JOIN partner_location_nodes tn ON tn.id = s.taluk_node_id
         WHERE s.ms_id = '$msid_esc'
         ORDER BY COALESCE(dn.name, s.district_name) ASC, COALESCE(tn.name, s.taluk_name) ASC, s.name ASC"
    );
    while ($s = mysqli_fetch_assoc($r)) {
        $districtKey   = (int)($s['district_node_id'] ?? 0);
        $talukKey      = (int)($s['taluk_node_id'] ?? 0);
        $districtLabel = $districtKey > 0 ? $s['clean_district_name'] : 'Other / Unmapped';
        $talukLabel    = $talukKey > 0 ? $s['clean_taluk_name'] : ($s['taluk_name'] ?: 'Other');

        $s['district_key']   = $districtKey;
        $s['taluk_key']      = $talukKey;
        $s['district_label'] = $districtLabel;
        $s['taluk_label']    = $talukLabel;

        $shopList[] = $s;
        if (!isset($districtSet[$districtKey])) { $districtSet[$districtKey] = $districtLabel; }
    }
}

// ── Products — fetched once, used in GET ORDER form ───────────────────────────
$productList = [];
$r = mysqli_query($db_conn, "SELECT id, productName FROM products ORDER BY productName ASC");
while ($p = mysqli_fetch_assoc($r)) $productList[] = $p;

// ── Active TPs — used to populate the "Assign To TP" dropdown, filtered
// client-side to the selected shop's district (branch_district is free text,
// matched case-insensitively against ms_shop.district_name) ───────────────────
$tpList = [];
$r = mysqli_query($db_conn, "SELECT id, name, branch_district FROM territory_partners WHERE is_active=1 ORDER BY name ASC");
while ($t = mysqli_fetch_assoc($r)) $tpList[] = $t;

// ── Which form to show ────────────────────────────────────────────────────────
$isNoOrder = (isset($_REQUEST['actorder']) && $_REQUEST['actorder'] == "femi9noorder12aedrftgop2we4mncl");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title;?> : <?php echo $business_name;?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
        table td { padding: 5px !important; }
        select:disabled { opacity: 0.45; }
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
                                    <?php if(isset($_REQUEST['distalready'])): ?>
                                    <div class="alert alert-danger">Shop Details Already Exists.</div>
                                    <?php endif; ?>
                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td><?php echo $title;?></td>
                                                <td><a href="<?php echo $manage_url;?>" title="<?php echo $manage_title;?>">&#9776;</a></td>
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

                                        <?php if(isset($_REQUEST['alreadyexists'])): ?>
                                        <div class="alert alert-danger"><?php echo $message_title;?> already exists!</div>
                                        <?php endif; ?>

                                        <?php include("validate-scripts.php"); ?>

                                        <?php if(empty($markeingSTFID)): ?>
                                        <div class="alert alert-warning">Marketing staff session not found. Please log in again.</div>

                                        <?php elseif($isNoOrder): ?>
                                        <!-- ═══════════════ NO ORDER FORM ═══════════════ -->
                                        <form action="order_action" method="post" enctype="multipart/form-data" id="uploadForm">
                                            <input type="hidden" name="ms_id"      value="<?=htmlspecialchars($markeingSTFID)?>">
                                            <input type="hidden" name="order_date" value="<?=htmlspecialchars($order_date)?>">
                                            <input type="hidden" name="order_id"   value="<?=htmlspecialchars($tempID)?>">

                                            <div class="example-container">
                                                <div class="example-content">

                                                    <label class="form-label">District Filter</label>
                                                    <select id="district_filter_select" class="form-control" onchange="onDistrictChange(this.value)">
                                                        <option value="">All Districts</option>
                                                        <?php foreach($assignedDistricts as $d): ?>
                                                        <option value="<?=$d['id']?>"><?=htmlspecialchars($d['name'])?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <br/>

                                                    <label class="form-label">Taluk Filter</label>
                                                    <select id="taluk_filter_select" class="form-control" onchange="onTalukChange(this.value)" disabled>
                                                        <option value="">All Taluks</option>
                                                    </select>
                                                    <br/>

                                                    <label class="form-label">Shop*</label>
                                                    <select class="my-select form-control" name="shop_id" id="shop_select" required>
                                                        <option value="" hidden>Select</option>
                                                        <?php foreach($shopList as $s): ?>
                                                        <option value="<?=htmlspecialchars($s['id'])?>"
                                                                data-district-id="<?=$s['district_key']?>"
                                                                data-taluk-id="<?=$s['taluk_key']?>"
                                                                data-taluk-label="<?=htmlspecialchars($s['taluk_label'])?>">
                                                            <?=htmlspecialchars($s['name'])?> (<?=htmlspecialchars($s['taluk_label'])?>)
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <br/>

                                                    <label class="form-label">Order Date*</label>
                                                    <input type="text" disabled value="<?=date("d-m-Y", strtotime($order_date))?>" class="form-control">
                                                    <br/>

                                                    <label class="form-label">Reason*</label>
                                                    <textarea name="noorder_reason" onkeypress="restrictSpecialChars(event)" class="form-control" required></textarea>
                                                    <br/>

                                                    <label class="form-label">Marketing Tool*</label>
                                                    <textarea name="marketing_tool" onkeypress="restrictSpecialChars(event)" class="form-control" required></textarea>
                                                    <br/>

                                                    <button type="submit" name="add_order_no" onclick="return confirm('Please confirm');" class="btn btn-primary">
                                                        <i class="material-icons">add</i> Add
                                                    </button>

                                                </div>
                                            </div>
                                        </form>

                                        <?php else: ?>
                                        <!-- ═══════════════ GET ORDER FORM ═══════════════ -->
                                        <form action="order_action_get" method="post" enctype="multipart/form-data" id="uploadForm">
                                            <input type="hidden" name="ms_id"      value="<?=htmlspecialchars($markeingSTFID)?>">
                                            <input type="hidden" name="order_date" value="<?=htmlspecialchars($order_date)?>">
                                            <input type="hidden" name="order_id"   value="<?=htmlspecialchars($tempID)?>">
                                            <input type="hidden" name="latitude"   id="order_latitude"  value="">
                                            <input type="hidden" name="longitude" id="order_longitude" value="">

                                            <div class="example-container">
                                                <div class="example-content">

                                                    <label class="form-label">District Filter</label>
                                                    <select id="district_filter_select" class="form-control" onchange="onDistrictChange(this.value)">
                                                        <option value="">All Districts</option>
                                                        <?php foreach($assignedDistricts as $d): ?>
                                                        <option value="<?=$d['id']?>"><?=htmlspecialchars($d['name'])?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <br/>

                                                    <label class="form-label">Taluk Filter</label>
                                                    <select id="taluk_filter_select" class="form-control" onchange="onTalukChange(this.value)" disabled>
                                                        <option value="">All Taluks</option>
                                                    </select>
                                                    <br/>

                                                    <label class="form-label">Firka Filter</label>
                                                    <select id="firka_filter_select" class="form-control" onchange="onFirkaChange(this.value)" disabled>
                                                        <option value="">Select Taluk First</option>
                                                    </select>
                                                    <br/>

                                                    <label class="form-label">Shop*</label>
                                                    <select class="my-select form-control" name="shop_id" id="shop_select" required>
                                                        <option value="" hidden>Select</option>
                                                        <?php foreach($shopList as $s): ?>
                                                        <option value="<?=htmlspecialchars($s['id'])?>"
                                                                data-district-id="<?=$s['district_key']?>"
                                                                data-taluk-id="<?=$s['taluk_key']?>"
                                                                data-taluk-label="<?=htmlspecialchars($s['taluk_label'])?>"
                                                                data-district="<?=htmlspecialchars($s['district_name'])?>"
                                                                data-lat="<?=htmlspecialchars($s['latitude'])?>"
                                                                data-lng="<?=htmlspecialchars($s['longitude'])?>">
                                                            <?=htmlspecialchars($s['name'])?> (<?=htmlspecialchars($s['taluk_label'])?>)
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div id="shopDistanceInfo" style="margin-top:4px; font-size:13px; color:#555;"></div>
                                                    <br/>

                                                    <label class="form-label">Assign To TP*</label>
                                                    <select class="my-select form-control" name="tp_id" id="tp_select" required>
                                                        <option value="" hidden>Select TP</option>
                                                        <?php foreach($tpList as $t): ?>
                                                        <option value="<?=htmlspecialchars($t['id'])?>"
                                                                data-district="<?=htmlspecialchars($t['branch_district'])?>">
                                                            <?=htmlspecialchars($t['name'])?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <br/>

                                                    <label class="form-label">Order Date*</label>
                                                    <input type="text" disabled value="<?=date("d-m-Y", strtotime($order_date))?>" class="form-control">
                                                    <br/>

                                                    <label class="form-label">Marketing Tool*</label>
                                                    <textarea name="marketing_tool" onkeypress="restrictSpecialChars(event)" class="form-control" required></textarea>
                                                    <br/>

                                                    <p>
                                                        <button type="button" class="btn btn-primary btn-burger" onclick="addRow('dataTable')">
                                                            <i class="material-icons">add</i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-burger" onclick="deleteRow('dataTable')">
                                                            <i class="material-icons">delete_outline</i>
                                                        </button>
                                                    </p>

                                                    <table id="dataTable" border="0">
                                                        <tr>
                                                            <td><input type="checkbox" name="chk[]"/></td>
                                                            <td>
                                                                <select required name="pr_id[]" class="form-control">
                                                                    <option value="" hidden>Select Product</option>
                                                                    <?php foreach($productList as $p): ?>
                                                                    <option value="<?=$p['id']?>"><?=htmlspecialchars($p['productName'])?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <input type="number" placeholder="Qty" min="0" name="qty[]" class="form-control" required/>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <br/>

                                                    <button type="submit" name="add_order_get" onclick="return confirm('Please confirm');" class="btn btn-primary">
                                                        <i class="material-icons">add</i> Add
                                                    </button>

                                                </div>
                                            </div>
                                        </form>
                                        <?php endif; ?>

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
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/select3.js"></script>

    <script>
    // Clean district → taluk (and taluk → firka → TP) data, sourced from the
    // partner_location_nodes hierarchy / territory_partner_locations — not
    // from shop records — so the Taluk Filter never shows spelling variants
    // like "Anthiur"/"Anthiyur" again, and a Firka pick can auto-detect the
    // TP who actually covers that area.
    var msDistrictData  = <?php echo json_encode(array_column($assignedDistricts, null, 'id')); ?>;
    var talukFirkaData  = <?php echo json_encode($talukFirkaMap); ?>;
    var firkaTpData     = <?php echo json_encode($firkaTpMap); ?>;

    // ── Add / Remove product rows ────────────────────────────────────────────
    function addRow(tableID) {
        var table    = document.getElementById(tableID);
        var rowCount = table.rows.length;
        if (rowCount < 100) {
            var row      = table.insertRow(rowCount);
            var colCount = table.rows[0].cells.length;
            for (var i = 0; i < colCount; i++) {
                var newcell       = row.insertCell(i);
                newcell.innerHTML = table.rows[0].cells[i].innerHTML;
            }
        } else {
            alert("Maximum allowed record is 100.");
        }
    }

    function deleteRow(tableID) {
        var table    = document.getElementById(tableID);
        var rowCount = table.rows.length;
        for (var i = 0; i < rowCount; i++) {
            var row    = table.rows[i];
            var chkbox = row.cells[0].childNodes[0];
            if (null != chkbox && true == chkbox.checked) {
                if (rowCount <= 1) {
                    alert("Cannot remove all rows.");
                    break;
                }
                table.deleteRow(i);
                rowCount--;
                i--;
            }
        }
    }

    // ── District → Taluk → Shop chained filter ───────────────────────────────
    // District/taluk are matched by the clean partner_location_nodes id
    // (data-district-id/data-taluk-id), not raw text — that's what collapses
    // "Erode"/"Erode/"/"Erode-" etc into one dropdown entry. Shops that never
    // matched a node (id 0) sit together under "Other / Unmapped".
    var allShops = [];
    document.querySelectorAll('#shop_select option[data-district-id]').forEach(function(opt) {
        allShops.push({
            id:         opt.value,
            text:       opt.textContent.trim(),
            districtId: opt.getAttribute('data-district-id'),
            talukId:    opt.getAttribute('data-taluk-id'),
            talukLabel: opt.getAttribute('data-taluk-label'),
            district:   opt.getAttribute('data-district'),
            lat:        opt.getAttribute('data-lat'),
            lng:        opt.getAttribute('data-lng')
        });
    });

    // ── Assign To TP: filter to TPs whose branch district matches the
    // selected shop's district (case-insensitive, trimmed text match — TP
    // branch_district and ms_shop.district_name are independently free-typed
    // fields, not a shared master list, so mismatched spellings won't match). ──
    var allTPs = [];
    var tpSelectEl = document.getElementById('tp_select');
    if (tpSelectEl) {
        document.querySelectorAll('#tp_select option[data-district]').forEach(function(opt) {
            allTPs.push({
                id:       opt.value,
                text:     opt.textContent.trim(),
                district: (opt.getAttribute('data-district') || '').trim().toLowerCase()
            });
        });

        function rebuildTPs(district) {
            var select = $('#tp_select');
            var currentVal = select.val();
            select.empty();
            select.append('<option value="" hidden>Select TP</option>');

            var normDistrict = (district || '').trim().toLowerCase();
            allTPs.forEach(function(t) {
                if (!normDistrict || t.district === normDistrict) {
                    select.append($('<option>', { value: t.id, text: t.text }));
                }
            });

            if (select.find('option[value="' + currentVal + '"]').length) {
                select.val(currentVal);
            }
            select.trigger('change.select2');
        }

        $('#shop_select').on('change', function() {
            // Skip while a Firka pick has auto-detected and locked the TP —
            // otherwise this district-text-match rebuild wipes it back to
            // empty the moment a shop is chosen afterwards.
            if ($('#tp_select').next('.select2-container').css('pointer-events') === 'none') { return; }
            var opt = this.options[this.selectedIndex];
            rebuildTPs(opt ? opt.getAttribute('data-district') : '');
        });
    }

    function resetFirkaAndTp() {
        var firkaSel = document.getElementById('firka_filter_select');
        if (!firkaSel) { return; }
        firkaSel.innerHTML = '<option value="">Select Taluk First</option>';
        firkaSel.disabled  = true;
        unlockTpSelect();
    }

    // #tp_select is select2-initialized (.my-select) — the visible widget is
    // a sibling .select2-container, not the (hidden) native <select> itself,
    // so both the value update and the interaction lock have to go through
    // jQuery/select2, not plain DOM properties.
    function unlockTpSelect() {
        var $tp = $('#tp_select');
        if (!$tp.length) { return; }
        $tp.next('.select2-container').css('pointer-events', '');
        if (typeof rebuildTPs === 'function') { rebuildTPs(''); }
    }

    function onDistrictChange(districtId) {
        var talukSel       = document.getElementById('taluk_filter_select');
        talukSel.innerHTML = '<option value="">All Taluks</option>';
        talukSel.disabled  = !districtId;

        // Clean taluk list straight from the location hierarchy for this
        // district — not derived from (possibly dirty) shop records.
        var d = msDistrictData[districtId];
        if (d) {
            d.taluks.forEach(function(t) {
                var o = document.createElement('option');
                o.value = t.id; o.textContent = t.name;
                talukSel.appendChild(o);
            });
        }

        resetFirkaAndTp();
        rebuildShops(districtId, '');
    }

    function onTalukChange(talukId) {
        var districtId = document.getElementById('district_filter_select').value;
        rebuildShops(districtId, talukId);

        var firkaSel = document.getElementById('firka_filter_select');
        if (firkaSel) {
            firkaSel.innerHTML = '<option value="">All Firkas</option>';
            firkaSel.disabled  = !talukId;
            var firkas = talukFirkaData[talukId] || [];
            firkas.forEach(function(f) {
                var o = document.createElement('option');
                o.value = f.id; o.textContent = f.name;
                firkaSel.appendChild(o);
            });
        }
        unlockTpSelect();
    }

    function onFirkaChange(firkaId) {
        var $tp = $('#tp_select');
        if (!$tp.length) { return; }
        var tp = firkaTpData[firkaId];
        if (!tp) { unlockTpSelect(); return; }

        // Auto-detect the TP covering this firka and lock the field — the DM
        // no longer picks a TP manually once a firka is chosen. The select
        // itself stays enabled (so its value still submits); only the
        // visible select2 widget is blocked from further interaction.
        if (!$tp.find('option[value="' + tp.id + '"]').length) {
            $tp.append($('<option>', { value: tp.id, text: tp.name }));
        }
        $tp.val(tp.id).trigger('change.select2');
        $tp.next('.select2-container').css('pointer-events', 'none');
    }

    function rebuildShops(districtId, talukId) {
        var select = $('#shop_select');
        select.empty();
        select.append('<option value="" hidden>Select</option>');

        allShops.forEach(function(s) {
            var okDistrict = !districtId || s.districtId === districtId;
            var okTaluk    = !talukId    || s.talukId    === talukId;
            if (okDistrict && okTaluk) {
                select.append(
                    $('<option>', {
                        value:              s.id,
                        'data-district-id': s.districtId,
                        'data-taluk-id':    s.talukId,
                        'data-taluk-label': s.talukLabel,
                        'data-district':    s.district,
                        'data-lat':         s.lat,
                        'data-lng':         s.lng,
                        text:               s.text
                    })
                );
            }
        });

        select.trigger('change.select2');
        select.val('').trigger('change');
    }

    // If this DM has exactly one assigned district, select it automatically
    // instead of leaving "All Districts" as the default — narrows the Shop
    // list down to just that district's shops right away.
    (function() {
        var districtSel = document.getElementById('district_filter_select');
        if (!districtSel) { return; }
        var opts = Array.from(districtSel.options).filter(function(o) { return o.value !== ''; });
        if (opts.length === 1) {
            districtSel.value = opts[0].value;
            onDistrictChange(opts[0].value);
        }
    })();

    // ── Auto-capture device location on the Get Order form ──────────────────
    (function() {
        var latField = document.getElementById('order_latitude');
        var lngField = document.getElementById('order_longitude');
        if (!latField || !lngField) { return; } // Not the Get Order form

        var capturedLat = null;
        var capturedLng = null;
        var capturedPlaceName = null;

        function showToast(lines) {
            var toast = document.createElement('div');
            toast.className = 'location-toast';
            lines.forEach(function(line, idx) {
                var lineEl = document.createElement('div');
                if (idx === 0) { lineEl.style.fontWeight = '600'; lineEl.style.marginBottom = '4px'; }
                lineEl.textContent = line;
                toast.appendChild(lineEl);
            });
            document.body.appendChild(toast);
            requestAnimationFrame(function() { toast.classList.add('location-toast-visible'); });
            setTimeout(function() {
                toast.classList.remove('location-toast-visible');
                setTimeout(function() { toast.remove(); }, 300);
            }, 4000);
        }

        // Reuses the same server-side Google Geocoding proxy used on the Add Shop page.
        function reverseGeocodeToName(lat, lng) {
            var url = 'reverse-geocode.php?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng);
            return fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && data.error) { throw new Error(data.error); }
                    return (data && data.name) ? data.name : (lat + ', ' + lng);
                });
        }

        // Haversine distance in meters
        function distanceMeters(lat1, lng1, lat2, lng2) {
            var R = 6371000;
            var dLat = (lat2 - lat1) * Math.PI / 180;
            var dLng = (lng2 - lng1) * Math.PI / 180;
            var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                    Math.sin(dLng / 2) * Math.sin(dLng / 2);
            var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        function updateShopDistance(andShowToast) {
            var infoEl = document.getElementById('shopDistanceInfo');
            if (!infoEl || capturedLat === null || capturedLng === null) { return; }

            var selected = document.getElementById('shop_select');
            var opt = selected ? selected.options[selected.selectedIndex] : null;
            var shopLat = opt ? parseFloat(opt.getAttribute('data-lat')) : NaN;
            var shopLng = opt ? parseFloat(opt.getAttribute('data-lng')) : NaN;

            if (!opt || !opt.value || isNaN(shopLat) || isNaN(shopLng)) {
                infoEl.textContent = '';
                return;
            }

            var meters = distanceMeters(capturedLat, capturedLng, shopLat, shopLng);
            var distText = meters >= 1000 ? (meters / 1000).toFixed(2) + ' km' : Math.round(meters) + ' m';
            var distLine = '🏬 ' + distText + ' away from this shop\'s saved location';
            infoEl.textContent = '📍 ' + distText + ' away from this shop\'s saved location';

            if (andShowToast) {
                var lines = capturedPlaceName ? ['📍 ' + capturedPlaceName, distLine] : [distLine];
                showToast(lines);
            }
        }

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    capturedLat = position.coords.latitude;
                    capturedLng = position.coords.longitude;
                    latField.value = capturedLat;
                    lngField.value = capturedLng;

                    reverseGeocodeToName(capturedLat, capturedLng)
                        .then(function(name) {
                            capturedPlaceName = name;
                            showToast(['📍 ' + name]);
                        })
                        .catch(function() {
                            showToast(['📍 Location captured (' + capturedLat.toFixed(5) + ', ' + capturedLng.toFixed(5) + ')']);
                        });

                    updateShopDistance(false);
                },
                function() {
                    // Permission denied / unavailable — order still proceeds without location
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        }

        $('#shop_select').on('change', function() { updateShopDistance(true); });
    })();
    </script>

    <style>
        .location-toast {
            position: fixed;
            top: 20px;
            right: -320px;
            max-width: 280px;
            background: #323232;
            color: #fff;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            z-index: 99999;
            transition: right 0.3s ease;
        }
        .location-toast-visible {
            right: 20px;
        }
    </style>

</body>
</html>