<?php 
error_reporting(E_ALL);
ini_set('display_errors', '1');

include("checksession.php");
include("config.php");
require_once("include/AssignedLocations.php");

// District Filter is scoped to this DM's assigned locations (not every
// district their shop history happens to touch) — same source as add_ss.php.
$assignedDistricts = getMsAssignedDistricts($db_conn, (int)$markeingSTFID);

// Company-approved per-DM exemption from Get Order's 100m-from-shop check
// (see ms_edit.php's checkbox and order_action_get.php's own matching
// server-side check) — read here too so the client-side confirm() below
// doesn't block this DM before the form ever reaches the server.
$skipLocationCheckForThisDm = false;
if (!empty($markeingSTFID)) {
    $chkSkipCol = mysqli_query($db_conn, "SHOW COLUMNS FROM marketing_staff LIKE 'skip_location_check'");
    if ($chkSkipCol && mysqli_num_rows($chkSkipCol) > 0) {
        $msidForSkip = mysqli_real_escape_string($db_conn, $markeingSTFID);
        $skipRes = mysqli_query($db_conn, "SELECT skip_location_check FROM marketing_staff WHERE id='$msidForSkip' LIMIT 1");
        $skipRow = $skipRes ? mysqli_fetch_assoc($skipRes) : null;
        $skipLocationCheckForThisDm = $skipRow && (int)$skipRow['skip_location_check'] === 1;
    }
}

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

// Whether this DM's assigned area has any active TP coverage at all — not a
// DM choice, purely automatic. If their district has no TP (or the TP that
// used to cover it was deactivated), the TP-assignment fields never show at
// all and the order is understood to be handled by the company directly.
$dmHasActiveTp = !empty($firkaTpMap);

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
        "SELECT s.id, s.name, s.mobile_number, s.district_name, s.taluk_name, s.latitude, s.longitude,
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
// Neksomo-sourced products (temp_id LIKE 'NKS-%') are excluded from selection
// here, same convention as the shop/customer/TP invoice "add product" dropdowns.
//
// Filtered on visible_in_get_order, NOT deleted_at (Active/Inactive). Get
// Order isn't fulfilled from company stock — a TP sources it from their own
// existing stock, or buys it elsewhere and hands it over — so a product the
// company marked Inactive (stopped manufacturing/selling it) can still be a
// valid thing to raise a Get Order for. visible_in_get_order is a separate,
// independent per-product toggle on the Edit Product page precisely for
// this (see db_migrations/2026_10_29_products_visible_in_get_order.sql).
// Self-migrating guard, same pattern as packs_per_cover elsewhere.
$_vgoCol = $db_conn->query("SHOW COLUMNS FROM products LIKE 'visible_in_get_order'");
if ($_vgoCol && $_vgoCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE products ADD COLUMN visible_in_get_order TINYINT(1) NOT NULL DEFAULT 1 AFTER deleted_at");
    $db_conn->query("UPDATE products SET visible_in_get_order = IF(deleted_at IS NULL, 1, 0)");
}

$productList = [];
$r = mysqli_query($db_conn, "SELECT id, productName, outlet_price, category FROM products WHERE visible_in_get_order = 1 AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY productName ASC");
while ($p = mysqli_fetch_assoc($r)) $productList[] = $p;

// ── Active TPs — used to populate the "Assign To TP" dropdown, filtered
// client-side to the selected shop's district (branch_district is free text,
// matched case-insensitively against ms_shop.district_name) ───────────────────
$tpList = [];
$r = mysqli_query($db_conn, "SELECT id, name, branch_district FROM territory_partners WHERE is_active=1 ORDER BY name ASC");
while ($t = mysqli_fetch_assoc($r)) $tpList[] = $t;

// ── Active Channel Partners — for a Firka with no TP coverage (Vacant Area),
// the DM picks a CP instead of a TP (2026-11-12, user request). Same district
// free-text match convention as $tpList above. A CP with no delivery_district
// falls back to branch_district, same COALESCE company/manage-cp-invoices.php
// already uses for a CP's "district". The order itself is still handled and
// invoiced by the company exactly as before (no TP/CP fulfillment pipeline
// change) -- this is purely a record of which CP the order is attributed to.
$cpList = [];
$r = mysqli_query($db_conn, "SELECT id, name, COALESCE(NULLIF(delivery_district,''), branch_district) AS district FROM channel_partners WHERE is_active=1 ORDER BY name ASC");
while ($c = mysqli_fetch_assoc($r)) $cpList[] = $c;

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
        .mobile-field-label { display: none; }

        /* ── SKU picker — click a card to add it to the order ── */
        .sku-search-box { margin-bottom: 10px; max-width: 360px; }
        .sku-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 10px; max-height: 320px; overflow-y: auto; padding: 2px;
        }
        .sku-card {
            background: #fff; border: 1.5px solid #e5e7eb; border-radius: 10px;
            padding: 12px 12px; cursor: pointer; text-align: left;
            transition: border-color .15s ease, box-shadow .15s ease, transform .1s ease;
        }
        .sku-card:hover { border-color: #4f6ef7; box-shadow: 0 2px 8px rgba(79,110,247,0.14); }
        .sku-card:active { transform: scale(0.98); }
        .sku-card.in-cart { border-color: #16a34a; background: #f0fdf4; }
        .sku-card-name { font-weight: 600; font-size: 13px; color: #1f2937; line-height: 1.3; margin-bottom: 6px; min-height: 34px; }
        .sku-card-price { font-size: 12.5px; color: #4f6ef7; font-weight: 700; }
        .sku-card-badge {
            display: inline-flex; align-items: center; gap: 2px; font-size: 10.5px; font-weight: 700;
            color: #16a34a; margin-top: 6px;
        }
        .sku-card-badge .material-icons { font-size: 13px; }
        .sku-grid-empty { padding: 20px; text-align: center; color: #9ca3af; font-size: 13px; }

        /* ── Cart (selected products) ── */
        .cart-empty-hint { font-size: 12.5px; color: #9ca3af; padding: 8px 2px; }
        #orderItemsList { display: flex; flex-direction: column; gap: 8px; }
        .cart-row {
            display: flex; align-items: flex-end; gap: 12px;
            background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px;
            padding: 10px 14px; position: relative;
        }
        .cart-row:focus-within { border-color: #c7d2fe; box-shadow: 0 0 0 3px rgba(79,110,247,0.08); }
        .cart-row .cart-field { display: flex; flex-direction: column; }
        .cart-row .cart-field-name { flex: 1 1 200px; min-width: 150px; font-weight: 600; font-size: 13.5px; color: #1f2937; justify-content: center; }
        .cart-row .cart-field-qty { flex: 0 0 90px; }
        .cart-row .cart-field-disc { flex: 0 0 100px; }
        .cart-row .cart-field-amount { flex: 0 0 150px; white-space: nowrap; }
        .cart-row .cart-field-amount .cart-line-actual { font-size: 12px; color: #6b7280; }
        .cart-row .cart-field-amount .cart-line-final { font-size: 13.5px; font-weight: 700; color: #1f2937; }
        .cart-row .cart-row-remove {
            flex: 0 0 auto; width: 32px; height: 32px; border-radius: 50%;
            border: 1px solid #fecaca; background: #fff5f5; color: #dc2626;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; margin-bottom: 2px;
        }
        .cart-row .cart-row-remove:hover { background: #fee2e2; }
        .cart-row .cart-row-remove .material-icons { font-size: 18px; }
        #orderGrandTotal {
            background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;
            padding: 10px 14px; color: #166534; font-size: 14px;
        }
        #orderGrandTotal:empty { display: none; padding: 0; border: none; background: none; }
        #productCategoryFilter .category-card.cat-locked { opacity: 0.5; cursor: not-allowed; }

        /* Mobile: stack each cart row's fields one per line. */
        @media (max-width: 600px) {
            .sku-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); max-height: 260px; }
            .cart-row { flex-direction: column; align-items: stretch; gap: 8px; padding-top: 14px; }
            .cart-row .cart-field-name,
            .cart-row .cart-field-qty,
            .cart-row .cart-field-disc,
            .cart-row .cart-field-amount { flex: 1 1 auto; width: 100%; }
            .cart-row .cart-row-remove { position: absolute; top: 8px; right: 8px; width: 28px; height: 28px; margin-bottom: 0; }
            .mobile-field-label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 2px; }
        }
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

                        <?php if (isset($_SESSION['errorMessage'])): $_errMsg = $_SESSION['errorMessage']; unset($_SESSION['errorMessage']); ?>
                        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                        <script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: '<?php echo addslashes($_errMsg); ?>',
                            confirmButtonText: 'OK'
                        });
                        </script>
                        <?php endif; ?>

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

                                                    <label class="form-label">Category*</label>
                                                    <div class="btn-group d-block mb-2" role="group" id="noOrderCategoryFilter">
                                                        <input type="radio" class="btn-check" name="order_category" id="noOrderCatNapkin" value="napkin" checked>
                                                        <label class="btn btn-outline-primary" for="noOrderCatNapkin">Napkin</label>
                                                        <input type="radio" class="btn-check" name="order_category" id="noOrderCatDiaper" value="diaper">
                                                        <label class="btn btn-outline-primary" for="noOrderCatDiaper">Diaper</label>
                                                    </div>

                                                    <label class="form-label">Reason*</label>
                                                    <textarea name="noorder_reason" onkeypress="restrictSpecialChars(event)" class="form-control" required></textarea>
                                                    <br/>

                                                    <label class="form-label">Marketing Tool*</label>
                                                    <input type="hidden" name="marketing_tool" id="marketing_tool_value" value="">
                                                    <div id="mtStatusToggle" style="display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap;">
                                                        <div class="mt-status-card" data-status="Already Available" onclick="setMtStatus('Already Available')" style="flex:1;min-width:140px;cursor:pointer;border:2px solid #e5e7eb;border-radius:9px;padding:10px 12px;text-align:center;font-weight:600;color:#1f2937;">Already Available</div>
                                                        <div class="mt-status-card" data-status="Needed - Not Available" onclick="setMtStatus('Needed - Not Available')" style="flex:1;min-width:140px;cursor:pointer;border:2px solid #e5e7eb;border-radius:9px;padding:10px 12px;text-align:center;font-weight:600;color:#1f2937;">Needed - Not Available</div>
                                                        <div class="mt-status-card" data-status="Not Required" onclick="setMtStatus('Not Required')" style="flex:1;min-width:140px;cursor:pointer;border:2px solid #e5e7eb;border-radius:9px;padding:10px 12px;text-align:center;font-weight:600;color:#1f2937;">Not Required</div>
                                                    </div>
                                                    <style>
                                                        .mt-status-card.selected { border-color:#4f6ef7 !important; background:#eef2ff; color:#4f46e5 !important; }
                                                        @media (max-width:480px) { .mt-status-card { flex:1 1 100% !important; min-width:0 !important; } }
                                                        #mtToolsBlock { display:none; border:1px solid #e5e7eb; border-radius:9px; padding:10px 14px; margin-bottom:10px; }
                                                        #mtToolsBlock label { display:flex; align-items:center; gap:8px; font-weight:400; padding:5px 0; margin:0; cursor:pointer; }
                                                        #mtToolsBlock input[type=checkbox] { width:16px; height:16px; }
                                                        #mtToolsLabel { font-size:12px; color:#6b7280; font-weight:600; margin-bottom:4px; display:block; }
                                                        .mt-tools-grid { display:grid; grid-template-columns:1fr 1fr; grid-template-rows:repeat(4, auto); grid-auto-flow:column; column-gap:20px; }
                                                        @media (max-width:480px) { .mt-tools-grid { grid-template-columns:1fr; grid-template-rows:none; grid-auto-flow:row; } }
                                                    </style>
                                                    <div id="mtToolsBlock">
                                                        <span id="mtToolsLabel">Which tools are at this shop?</span>
                                                        <label style="border-bottom:1px solid #f1f5f9;padding-bottom:8px;margin-bottom:4px;font-weight:600;"><input type="checkbox" id="mtSelectAll" onchange="toggleMtSelectAll(this.checked)"> Select All</label>
                                                        <?php $mtTools = ['Touch & Feel', 'Rack Sticker', 'Dangler', 'Floor Desk', 'Counter Desk', 'Roll-Up Standee', 'Medical Shop Backing Sheet - Femi9', 'Medical Shop Backing Sheet - Lumi9']; ?>
                                                        <div class="mt-tools-grid">
                                                        <?php foreach ($mtTools as $mtIdx => $mtTool): ?>
                                                        <label><input type="checkbox" class="mt-tool-check" value="<?=htmlspecialchars($mtTool)?>" onchange="updateMarketingToolValue()"><?=htmlspecialchars($mtTool)?></label>
                                                        <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <script>
                                                        var mtCurrentStatus = '';
                                                        function setMtStatus(status) {
                                                            mtCurrentStatus = status;
                                                            document.querySelectorAll('#mtStatusToggle .mt-status-card').forEach(function (c) {
                                                                c.classList.toggle('selected', c.getAttribute('data-status') === status);
                                                            });
                                                            // "Not Required" skips the tool checklist entirely — nothing
                                                            // to pick when the shop doesn't need a marketing tool at
                                                            // all. 2026-11-30, user-requested (3rd option added
                                                            // alongside the existing Already Available / Needed - Not
                                                            // Available pair).
                                                            var toolsBlock = document.getElementById('mtToolsBlock');
                                                            if (status === 'Not Required') {
                                                                toolsBlock.style.display = 'none';
                                                            } else {
                                                                toolsBlock.style.display = 'block';
                                                                document.getElementById('mtToolsLabel').textContent = (status === 'Already Available')
                                                                    ? 'Which tools are at this shop?'
                                                                    : 'Which tools does this shop need?';
                                                            }
                                                            // Switching status clears every tool checkbox — the same
                                                            // tool list means a different thing depending on status, so
                                                            // a selection made under the old status shouldn't silently
                                                            // carry over. 2026-11-30, user-requested.
                                                            document.querySelectorAll('.mt-tool-check').forEach(function (c) { c.checked = false; });
                                                            var selectAll = document.getElementById('mtSelectAll');
                                                            if (selectAll) { selectAll.checked = false; }
                                                            updateMarketingToolValue();
                                                        }
                                                        function toggleMtSelectAll(checked) {
                                                            document.querySelectorAll('.mt-tool-check').forEach(function (c) { c.checked = checked; });
                                                            updateMarketingToolValue();
                                                        }
                                                        function updateMarketingToolValue() {
                                                            var all = document.querySelectorAll('.mt-tool-check');
                                                            var checked = Array.prototype.slice.call(all).filter(function (c) { return c.checked; });
                                                            var selectAll = document.getElementById('mtSelectAll');
                                                            if (selectAll) { selectAll.checked = all.length > 0 && checked.length === all.length; }
                                                            var val = !mtCurrentStatus ? '' : (mtCurrentStatus === 'Not Required' ? 'Not Required' : (mtCurrentStatus + ': ' + checked.map(function (c) { return c.value; }).join(', ')));
                                                            document.getElementById('marketing_tool_value').value = val;
                                                        }
                                                        function validateMarketingTool() {
                                                            if (!mtCurrentStatus) { alert('Pick a Marketing Tool status.'); return false; }
                                                            if (mtCurrentStatus !== 'Not Required' && !document.querySelector('.mt-tool-check:checked')) { alert('Select at least one marketing tool.'); return false; }
                                                            return true;
                                                        }
                                                    </script>
                                                    <br/>

                                                    <button type="submit" name="add_order_no" onclick="return validateMarketingTool() && confirm('Please confirm');" class="btn btn-primary">
                                                        <i class="material-icons">add</i> Add
                                                    </button>

                                                </div>
                                            </div>
                                        </form>

                                        <?php else: ?>
                                        <!-- ═══════════════ GET ORDER FORM ═══════════════ -->
                                        <form action="order_action_get" method="post" enctype="multipart/form-data" id="uploadForm" onsubmit="return validateMarketingTool();">
                                            <input type="hidden" name="ms_id"      value="<?=htmlspecialchars($markeingSTFID)?>">
                                            <input type="hidden" name="order_date" value="<?=htmlspecialchars($order_date)?>">
                                            <input type="hidden" name="order_id"   value="<?=htmlspecialchars($tempID)?>">
                                            <input type="hidden" name="latitude"   id="order_latitude"  value="">
                                            <input type="hidden" name="longitude" id="order_longitude" value="">

                                            <div class="example-container">
                                                <div class="example-content">

                                                    <?php if (!$dmHasActiveTp): ?>
                                                    <div style="margin-bottom:14px; font-size:12px; color:#374151; background:#f3f4f6; padding:8px 12px; border-radius:6px;">No active TP covers your assigned area — pick the Channel Partner handling it below, or "Company" if none.</div>
                                                    <?php endif; ?>

                                                    <label class="form-label">Category*</label>
                                                    <div id="productCategoryFilter" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                                                        <div class="category-card active" data-cat="napkin" style="flex:1 1 160px;cursor:pointer;border:2px solid #4f6ef7;background:#eef2ff;border-radius:10px;padding:14px 16px;text-align:center;">
                                                            <div style="font-weight:700;color:#1f2937;">Napkin</div>
                                                            <div style="font-size:12px;color:#6b7280;margin-top:2px;">Femi9 Sanitary Napkin products</div>
                                                        </div>
                                                        <div class="category-card" data-cat="diaper" style="flex:1 1 160px;cursor:pointer;border:2px solid #e5e7eb;background:#fff;border-radius:10px;padding:14px 16px;text-align:center;">
                                                            <div style="font-weight:700;color:#1f2937;">Diaper</div>
                                                            <div style="font-size:12px;color:#6b7280;margin-top:2px;">Lumi Baby Diaper products</div>
                                                        </div>
                                                    </div>

                                                    <div id="tpAssignBlock" style="<?=$dmHasActiveTp ? '' : 'display:none;'?>">
                                                    <label class="form-label">District Filter</label>
                                                    <select id="district_filter_select" class="form-control" onchange="onDistrictChange(this.value)">
                                                        <option value="">All Districts</option>
                                                        <?php foreach($assignedDistricts as $d): ?>
                                                        <option value="<?=$d['id']?>"><?=htmlspecialchars($d['name'])?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <br/>

                                                    <label class="form-label">Taluk Filter</label>
                                                    <select id="taluk_filter_select" class="form-control" onchange="onTalukChange(this.value)" disabled required>
                                                        <option value="" hidden>Select Taluk</option>
                                                    </select>
                                                    <br/>

                                                    <label class="form-label">Firka Filter</label>
                                                    <select id="firka_filter_select" class="form-control" onchange="onFirkaChange(this.value)" disabled required>
                                                        <option value="" hidden>Select Taluk First</option>
                                                    </select>
                                                    <br/>
                                                    </div>

                                                    <style>
                                                        .shop-field-header { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:6px; }
                                                        .shop-field-links { display:flex; gap:8px; flex-wrap:wrap; }
                                                        .shop-link-btn {
                                                            display:inline-flex; align-items:center; gap:4px;
                                                            font-size:12px; font-weight:600; text-decoration:none; white-space:nowrap;
                                                            padding:6px 12px; border-radius:20px; border:1px solid transparent;
                                                        }
                                                        .shop-link-btn .material-icons { font-size:15px; }
                                                        #tpShopsLink { display:none; color:#059669; background:#ecfdf5; border-color:#a7f3d0; }
                                                        #manageShopLink { color:#4f46e5; background:#eef2ff; border-color:#c7d2fe; }
                                                        @media (max-width: 576px) {
                                                            .shop-field-header { flex-direction:column; align-items:flex-start; }
                                                            .shop-field-links { width:100%; }
                                                            .shop-link-btn { flex:1 1 auto; justify-content:center; padding:9px 12px; font-size:12.5px; }
                                                        }
                                                    </style>
                                                    <div class="shop-field-header">
                                                        <span class="form-label" style="margin:0;">Shop*</span>
                                                        <span class="shop-field-links">
                                                            <a href="#" id="tpShopsLink" target="_blank" class="shop-link-btn" title="See the shops this firka's TP already services (view only)">
                                                                <i class="material-icons">storefront</i><span id="tpShopsLinkLabel">TP's Shops</span>
                                                            </a>
                                                            <a href="javascript:void(0);" id="manageShopLink" class="shop-link-btn" title="Show every shop you own in the dropdown below, ignoring the district/taluk/firka filter" onclick="showAllMyShops();">
                                                                <i class="material-icons">store</i>My Manage Shop
                                                            </a>
                                                        </span>
                                                    </div>
                                                    <select class="my-select form-control" name="shop_id" id="shop_select" required>
                                                        <option value="" hidden>Select</option>
                                                        <?php foreach($shopList as $s): ?>
                                                        <option value="<?=htmlspecialchars($s['id'])?>"
                                                                data-district-id="<?=$s['district_key']?>"
                                                                data-taluk-id="<?=$s['taluk_key']?>"
                                                                data-taluk-label="<?=htmlspecialchars($s['taluk_label'])?>"
                                                                data-district="<?=htmlspecialchars($s['district_name'])?>"
                                                                data-mobile="<?=htmlspecialchars(trim((string)($s['mobile_number'] ?? '')))?>"
                                                                data-lat="<?=htmlspecialchars($s['latitude'])?>"
                                                                data-lng="<?=htmlspecialchars($s['longitude'])?>">
                                                            <?=htmlspecialchars($s['name'])?> (<?=htmlspecialchars($s['taluk_label'])?>)
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div id="shopTpMatchHint" style="display:none;margin-top:4px;font-size:12px;background:#fef3c7;color:#92400e;padding:5px 9px;border-radius:6px;">
                                                        <i class="material-icons" style="font-size:14px;vertical-align:middle;">info</i>
                                                        Shops highlighted in <span style="background:#fef3c7;border:1px solid #fcd34d;padding:0 5px;border-radius:3px;">yellow</span> already exist with this TP — picking one reuses their existing shop record instead of creating a new one.
                                                    </div>
                                                    <div id="shopDistanceInfo" style="margin-top:4px; font-size:13px; color:#555;"></div>
                                                    <br/>

                                                    <div id="tpSelectBlock" style="<?=$dmHasActiveTp ? '' : 'display:none;'?>">
                                                    <label class="form-label">Assign To TP</label>
                                                    <select class="my-select form-control" name="tp_id" id="tp_select">
                                                        <option value="" hidden>Select TP</option>
                                                        <?php foreach($tpList as $t): ?>
                                                        <option value="<?=htmlspecialchars($t['id'])?>"
                                                                data-district="<?=htmlspecialchars($t['branch_district'])?>">
                                                            <?=htmlspecialchars($t['name'])?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    </div>

                                                    <!-- Vacant Area (no TP covers this Firka) -- pick a Channel Partner
                                                         instead of a TP. "Company" is always offered as a fallback, so
                                                         there's never a dead end even if no CP matches the district text
                                                         (2026-11-12, user request). Record-only: the order is still
                                                         handled/invoiced by the company exactly as before. -->
                                                    <div id="cpSelectBlock" style="<?=$dmHasActiveTp ? 'display:none;' : ''?>">
                                                    <label class="form-label">No TP covers this area — Assign To CP</label>
                                                    <select class="my-select form-control" name="assigned_cp_id" id="cp_select">
                                                        <option value="" selected>Company</option>
                                                        <?php foreach($cpList as $c): ?>
                                                        <option value="<?=htmlspecialchars($c['id'])?>"
                                                                data-district="<?=htmlspecialchars($c['district'])?>">
                                                            <?=htmlspecialchars($c['name'])?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div id="noTpHint" style="margin-top:4px; font-size:12px; color:#9a6b00;">No TP covers this area — pick the Channel Partner handling it, or "Company" if none.</div>
                                                    </div>
                                                    <br/>

                                                    <label class="form-label">Order Date*</label>
                                                    <input type="text" disabled value="<?=date("d-m-Y", strtotime($order_date))?>" class="form-control">
                                                    <br/>

                                                    <label class="form-label">Marketing Tool*</label>
                                                    <input type="hidden" name="marketing_tool" id="marketing_tool_value" value="">
                                                    <div id="mtStatusToggle" style="display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap;">
                                                        <div class="mt-status-card" data-status="Already Available" onclick="setMtStatus('Already Available')" style="flex:1;min-width:140px;cursor:pointer;border:2px solid #e5e7eb;border-radius:9px;padding:10px 12px;text-align:center;font-weight:600;color:#1f2937;">Already Available</div>
                                                        <div class="mt-status-card" data-status="Needed - Not Available" onclick="setMtStatus('Needed - Not Available')" style="flex:1;min-width:140px;cursor:pointer;border:2px solid #e5e7eb;border-radius:9px;padding:10px 12px;text-align:center;font-weight:600;color:#1f2937;">Needed - Not Available</div>
                                                        <div class="mt-status-card" data-status="Not Required" onclick="setMtStatus('Not Required')" style="flex:1;min-width:140px;cursor:pointer;border:2px solid #e5e7eb;border-radius:9px;padding:10px 12px;text-align:center;font-weight:600;color:#1f2937;">Not Required</div>
                                                    </div>
                                                    <style>
                                                        .mt-status-card.selected { border-color:#4f6ef7 !important; background:#eef2ff; color:#4f46e5 !important; }
                                                        @media (max-width:480px) { .mt-status-card { flex:1 1 100% !important; min-width:0 !important; } }
                                                        #mtToolsBlock { display:none; border:1px solid #e5e7eb; border-radius:9px; padding:10px 14px; margin-bottom:10px; }
                                                        #mtToolsBlock label { display:flex; align-items:center; gap:8px; font-weight:400; padding:5px 0; margin:0; cursor:pointer; }
                                                        #mtToolsBlock input[type=checkbox] { width:16px; height:16px; }
                                                        #mtToolsLabel { font-size:12px; color:#6b7280; font-weight:600; margin-bottom:4px; display:block; }
                                                        .mt-tools-grid { display:grid; grid-template-columns:1fr 1fr; grid-template-rows:repeat(4, auto); grid-auto-flow:column; column-gap:20px; }
                                                        @media (max-width:480px) { .mt-tools-grid { grid-template-columns:1fr; grid-template-rows:none; grid-auto-flow:row; } }
                                                    </style>
                                                    <div id="mtToolsBlock">
                                                        <span id="mtToolsLabel">Which tools are at this shop?</span>
                                                        <label style="border-bottom:1px solid #f1f5f9;padding-bottom:8px;margin-bottom:4px;font-weight:600;"><input type="checkbox" id="mtSelectAll" onchange="toggleMtSelectAll(this.checked)"> Select All</label>
                                                        <?php $mtTools = ['Touch & Feel', 'Rack Sticker', 'Dangler', 'Floor Desk', 'Counter Desk', 'Roll-Up Standee', 'Medical Shop Backing Sheet - Femi9', 'Medical Shop Backing Sheet - Lumi9']; ?>
                                                        <div class="mt-tools-grid">
                                                        <?php foreach ($mtTools as $mtIdx => $mtTool): ?>
                                                        <label><input type="checkbox" class="mt-tool-check" value="<?=htmlspecialchars($mtTool)?>" onchange="updateMarketingToolValue()"><?=htmlspecialchars($mtTool)?></label>
                                                        <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <script>
                                                        var mtCurrentStatus = '';
                                                        function setMtStatus(status) {
                                                            mtCurrentStatus = status;
                                                            document.querySelectorAll('#mtStatusToggle .mt-status-card').forEach(function (c) {
                                                                c.classList.toggle('selected', c.getAttribute('data-status') === status);
                                                            });
                                                            // "Not Required" skips the tool checklist entirely — nothing
                                                            // to pick when the shop doesn't need a marketing tool at
                                                            // all. 2026-11-30, user-requested (3rd option added
                                                            // alongside the existing Already Available / Needed - Not
                                                            // Available pair).
                                                            var toolsBlock = document.getElementById('mtToolsBlock');
                                                            if (status === 'Not Required') {
                                                                toolsBlock.style.display = 'none';
                                                            } else {
                                                                toolsBlock.style.display = 'block';
                                                                document.getElementById('mtToolsLabel').textContent = (status === 'Already Available')
                                                                    ? 'Which tools are at this shop?'
                                                                    : 'Which tools does this shop need?';
                                                            }
                                                            // Switching status clears every tool checkbox — the same
                                                            // tool list means a different thing depending on status, so
                                                            // a selection made under the old status shouldn't silently
                                                            // carry over. 2026-11-30, user-requested.
                                                            document.querySelectorAll('.mt-tool-check').forEach(function (c) { c.checked = false; });
                                                            var selectAll = document.getElementById('mtSelectAll');
                                                            if (selectAll) { selectAll.checked = false; }
                                                            updateMarketingToolValue();
                                                        }
                                                        function toggleMtSelectAll(checked) {
                                                            document.querySelectorAll('.mt-tool-check').forEach(function (c) { c.checked = checked; });
                                                            updateMarketingToolValue();
                                                        }
                                                        function updateMarketingToolValue() {
                                                            var all = document.querySelectorAll('.mt-tool-check');
                                                            var checked = Array.prototype.slice.call(all).filter(function (c) { return c.checked; });
                                                            var selectAll = document.getElementById('mtSelectAll');
                                                            if (selectAll) { selectAll.checked = all.length > 0 && checked.length === all.length; }
                                                            var val = !mtCurrentStatus ? '' : (mtCurrentStatus === 'Not Required' ? 'Not Required' : (mtCurrentStatus + ': ' + checked.map(function (c) { return c.value; }).join(', ')));
                                                            document.getElementById('marketing_tool_value').value = val;
                                                        }
                                                        function validateMarketingTool() {
                                                            if (!mtCurrentStatus) { alert('Pick a Marketing Tool status.'); return false; }
                                                            if (mtCurrentStatus !== 'Not Required' && !document.querySelector('.mt-tool-check:checked')) { alert('Select at least one marketing tool.'); return false; }
                                                            return true;
                                                        }
                                                    </script>
                                                    <br/>

                                                    <label class="form-label" style="margin-bottom:6px;">Products*</label>
                                                    <div class="sku-picker">
                                                        <input type="text" id="skuSearchBox" class="form-control sku-search-box" placeholder="Search SKU to add…" autocomplete="off">
                                                        <div id="skuGrid" class="sku-grid"></div>
                                                        <div id="skuGridEmpty" class="sku-grid-empty" style="display:none;">No products match.</div>
                                                    </div>

                                                    <div id="cartSection" style="display:none;">
                                                        <label class="form-label" style="margin:14px 0 6px;">Selected Products</label>
                                                        <div id="orderItemsList"></div>
                                                    </div>
                                                    <div id="cartEmptyHint" class="cart-empty-hint">Click a product above to add it to this order.</div>
                                                    <br/>
                                                    <div id="orderGrandTotal" style="font-weight:600;margin-bottom:10px;"></div>

                                                    <button type="submit" name="add_order_get" onclick="return confirmGetOrderSubmit();" class="btn btn-primary">
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

    // ── SKU click-to-add picker + cart ───────────────────────────────────────
    // Replaces the old per-row product <select> entirely: every SKU is shown
    // as a clickable card (filtered by the Napkin/Diaper category toggle and
    // the search box); clicking one adds it to the "Selected Products" cart
    // below (or bumps its qty by 1 if it's already in there). The cart is
    // what actually gets submitted — pr_id[]/qty[]/discount_percentage[]
    // hidden/visible inputs are generated per cart line.
    var productCatalog = <?php echo json_encode(array_map(function($p) {
        return [
            'id'       => (int)$p['id'],
            'name'     => $p['productName'],
            'price'    => (float)$p['outlet_price'],
            'category' => ($p['category'] ?? '') === 'diaper' ? 'diaper' : 'napkin',
        ];
    }, $productList)); ?>;

    var activeProductCategory = 'napkin';
    var cart = {}; // productId (string) => { id, name, price, qty, discount }
    var cartOrder = []; // insertion order of productIds, for stable row ordering

    function renderSkuGrid() {
        var grid = document.getElementById('skuGrid');
        var empty = document.getElementById('skuGridEmpty');
        if (!grid) { return; }
        var term = ($('#skuSearchBox').val() || '').trim().toUpperCase();
        grid.innerHTML = '';
        var shown = 0;
        productCatalog.forEach(function (p) {
            if (p.category !== activeProductCategory) { return; }
            if (term && p.name.toUpperCase().indexOf(term) === -1) { return; }
            shown++;
            var card = document.createElement('div');
            card.className = 'sku-card' + (cart[p.id] ? ' in-cart' : '');
            card.setAttribute('data-id', p.id);
            card.innerHTML =
                '<div class="sku-card-name"></div>' +
                '<div class="sku-card-price"></div>' +
                (cart[p.id] ? '<div class="sku-card-badge"><i class="material-icons">check_circle</i>In order (' + cart[p.id].qty + ')</div>' : '');
            card.querySelector('.sku-card-name').textContent = p.name;
            card.querySelector('.sku-card-price').textContent = '₹' + p.price.toFixed(2);
            card.addEventListener('click', function () { addToCart(p.id); });
            grid.appendChild(card);
        });
        if (empty) { empty.style.display = shown === 0 ? 'block' : 'none'; }
    }

    function addToCart(productId) {
        var p = productCatalog.find(function (x) { return x.id === productId; });
        if (!p) { return; }
        var key = String(productId);
        if (cart[key]) {
            cart[key].qty += 1;
        } else {
            cart[key] = { id: p.id, name: p.name, price: p.price, qty: 1, discount: 0 };
            cartOrder.push(key);
        }
        renderCart();
        renderSkuGrid();
    }

    function removeFromCart(productId) {
        var key = String(productId);
        delete cart[key];
        cartOrder = cartOrder.filter(function (k) { return k !== key; });
        renderCart();
        renderSkuGrid();
    }

    function updateCartField(productId, field, value) {
        var key = String(productId);
        if (!cart[key]) { return; }
        if (field === 'qty') { cart[key].qty = Math.max(0, parseFloat(value) || 0); }
        if (field === 'discount') { cart[key].discount = Math.min(100, Math.max(0, parseFloat(value) || 0)); }
        recalcCartLine(key);
        recalcOrderGrandTotal();
    }

    function recalcCartLine(key) {
        var item = cart[key];
        var row = document.querySelector('.cart-row[data-id="' + key + '"]');
        if (!item || !row) { return; }
        var actual = item.price * item.qty;
        var final  = actual * (1 - item.discount / 100);
        row.querySelector('.cart-line-actual').textContent = 'Actual: ₹' + actual.toFixed(2);
        row.querySelector('.cart-line-final').textContent  = 'After Disc: ₹' + final.toFixed(2);
    }

    function renderCart() {
        var list = document.getElementById('orderItemsList');
        var section = document.getElementById('cartSection');
        var hint = document.getElementById('cartEmptyHint');
        if (!list) { return; }
        list.innerHTML = '';
        cartOrder.forEach(function (key) {
            var item = cart[key];
            if (!item) { return; }
            var row = document.createElement('div');
            row.className = 'cart-row';
            row.setAttribute('data-id', key);
            row.innerHTML =
                '<input type="hidden" name="pr_id[]" value="' + item.id + '">' +
                '<div class="cart-field cart-field-name"></div>' +
                '<div class="cart-field cart-field-qty">' +
                    '<label class="mobile-field-label">Qty</label>' +
                    '<input type="number" min="0" name="qty[]" class="form-control cart-qty" autocomplete="off" required>' +
                '</div>' +
                '<div class="cart-field cart-field-disc">' +
                    '<label class="mobile-field-label">Discount %</label>' +
                    '<input type="number" min="0" max="100" step="0.01" name="discount_percentage[]" class="form-control cart-disc" autocomplete="off">' +
                '</div>' +
                '<div class="cart-field cart-field-amount">' +
                    '<label class="mobile-field-label">Amount</label>' +
                    '<div class="cart-line-actual">Actual: ₹0.00</div>' +
                    '<div class="cart-line-final">After Disc: ₹0.00</div>' +
                '</div>' +
                '<button type="button" class="cart-row-remove" title="Remove this product"><i class="material-icons">close</i></button>';
            row.querySelector('.cart-field-name').textContent = item.name;
            var qtyInput  = row.querySelector('.cart-qty');
            var discInput = row.querySelector('.cart-disc');
            qtyInput.value  = item.qty;
            discInput.value = item.discount;
            qtyInput.addEventListener('input', function () { updateCartField(item.id, 'qty', this.value); });
            discInput.addEventListener('input', function () { updateCartField(item.id, 'discount', this.value); });
            row.querySelector('.cart-row-remove').addEventListener('click', function () { removeFromCart(item.id); });
            list.appendChild(row);
            recalcCartLine(key);
        });

        var hasItems = cartOrder.length > 0;
        if (section) { section.style.display = hasItems ? '' : 'none'; }
        if (hint) { hint.style.display = hasItems ? 'none' : ''; }
        updateCategoryLockState();
    }

    function recalcOrderGrandTotal() {
        var actualTotal = 0, finalTotal = 0;
        cartOrder.forEach(function (key) {
            var item = cart[key];
            if (!item) { return; }
            var actual = item.price * item.qty;
            actualTotal += actual;
            finalTotal  += actual * (1 - item.discount / 100);
        });
        var totalEl = document.getElementById('orderGrandTotal');
        if (totalEl) {
            totalEl.textContent = (actualTotal > 0 || finalTotal > 0)
                ? 'Order Total — Actual: ₹' + actualTotal.toFixed(2) + '  |  After Discount: ₹' + finalTotal.toFixed(2)
                : '';
        }
    }

    // An order can only contain ONE product category at a time (Napkin OR
    // Diaper, never mixed) — user-requested, 2026-11-25. Once the cart has at
    // least one item, the OTHER category card is locked (greyed out,
    // clicking it just explains why) until every item is removed.
    function updateCategoryLockState() {
        var lockedCat = null;
        if (cartOrder.length) {
            var firstItem = cart[cartOrder[0]];
            var p = productCatalog.find(function (x) { return x.id === firstItem.id; });
            lockedCat = p ? p.category : null;
        }
        $('#productCategoryFilter .category-card').each(function () {
            var cat = this.getAttribute('data-cat');
            this.classList.toggle('cat-locked', !!lockedCat && cat !== lockedCat);
        });
        return lockedCat;
    }
    $(document).on('click', '#productCategoryFilter .category-card', function () {
        var clickedCat = this.getAttribute('data-cat');
        var lockedCat = updateCategoryLockState();
        if (lockedCat && clickedCat !== lockedCat) {
            alert('This order already has ' + lockedCat.charAt(0).toUpperCase() + lockedCat.slice(1) + ' products in it. Remove them first to switch to ' + clickedCat.charAt(0).toUpperCase() + clickedCat.slice(1) + '.');
            return;
        }
        activeProductCategory = clickedCat;
        $('#productCategoryFilter .category-card').each(function () {
            var active = (this.getAttribute('data-cat') === activeProductCategory);
            this.classList.toggle('active', active);
            this.style.borderColor = active ? '#4f6ef7' : '#e5e7eb';
            this.style.background = active ? '#eef2ff' : '#fff';
        });
        renderSkuGrid();
    });

    $('#skuSearchBox').on('input', renderSkuGrid);

    $(document).ready(function() {
        if (document.getElementById('shopTpMatchHint')) { initShopSelect2(); }
        // Assign To CP — plain <select class="my-select"> with no select2 of
        // its own was rendering as a tiny content-width pill instead of a
        // full-width dropdown like Shop/Product, since nothing here ever
        // sets its width. Matching the Shop select's own init, 2026-11-13.
        if (document.getElementById('cp_select')) { $('#cp_select').select2({ width: '100%' }); }
        renderSkuGrid();
        renderCart();
    });

    // ── District → Taluk → Shop chained filter ───────────────────────────────
    // District/taluk are matched by the clean partner_location_nodes id
    // (data-district-id/data-taluk-id), not raw text — that's what collapses
    // "Erode"/"Erode/"/"Erode-" etc into one dropdown entry. Shops that never
    // matched a node (id 0) sit together under "Other / Unmapped".
    // Mobile numbers this firka's auto-detected TP already has under their own
    // shop list — populated via AJAX once a TP is known (see
    // fetchTpShopMobiles below), used to colour-highlight matching shop
    // options so a DM can see BEFORE picking whether a shop will be
    // reused/merged into the TP's existing record instead of created fresh.
    var tpShopMobiles = new Set();

    var allShops = [];
    document.querySelectorAll('#shop_select option[data-district-id]').forEach(function(opt) {
        allShops.push({
            id:         opt.value,
            text:       opt.textContent.trim(),
            districtId: opt.getAttribute('data-district-id'),
            talukId:    opt.getAttribute('data-taluk-id'),
            talukLabel: opt.getAttribute('data-taluk-label'),
            district:   opt.getAttribute('data-district'),
            mobile:     opt.getAttribute('data-mobile'),
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

            var hint = document.getElementById('noTpHint');
            if (hint) { hint.style.display = (select.find('option').length <= 1) ? 'block' : 'none'; }
        }

        $('#shop_select').on('change', function() {
            // Skip while a Firka pick has auto-detected and locked the TP —
            // otherwise this district-text-match rebuild wipes it back to
            // empty the moment a shop is chosen afterwards.
            if ($('#tp_select').next('.select2-container').css('pointer-events') === 'none') { return; }
            var opt = this.options[this.selectedIndex];
            rebuildTPs(opt ? opt.getAttribute('data-district') : '');
            rebuildCPs(opt ? opt.getAttribute('data-district') : '');
        });
    }

    // ── Vacant Area (no TP covers the selected Firka): Assign To CP instead
    // of a TP, same district free-text match as rebuildTPs above. "Company"
    // is always kept as the first/default option regardless of match results
    // -- never a dead end even if no CP's district text matches
    // (2026-11-12, user request). ──
    var allCPs = [];
    document.querySelectorAll('#cp_select option[data-district]').forEach(function(opt) {
        allCPs.push({
            id:       opt.value,
            text:     opt.textContent.trim(),
            district: (opt.getAttribute('data-district') || '').trim().toLowerCase()
        });
    });

    function rebuildCPs(district) {
        var select = $('#cp_select');
        if (!select.length) { return; }
        var currentVal = select.val();
        select.empty();
        select.append('<option value="" selected>Company</option>');

        var normDistrict = (district || '').trim().toLowerCase();
        allCPs.forEach(function(c) {
            if (!normDistrict || c.district === normDistrict) {
                select.append($('<option>', { value: c.id, text: c.text }));
            }
        });

        if (currentVal && select.find('option[value="' + currentVal + '"]').length) {
            select.val(currentVal);
        }
        select.trigger('change.select2');
    }

    function showCpBlock() {
        document.getElementById('tpSelectBlock').style.display = 'none';
        document.getElementById('cpSelectBlock').style.display = '';
    }
    function hideCpBlock() {
        var block = document.getElementById('cpSelectBlock');
        if (block) { block.style.display = 'none'; }
        if (<?php echo $dmHasActiveTp ? 'true' : 'false'; ?>) {
            document.getElementById('tpSelectBlock').style.display = '';
        }
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
        updateTpShopsLink(null);
        tpShopMobiles = new Set();
        $('#shopTpMatchHint').hide();
    }

    function onDistrictChange(districtId) {
        var talukSel       = document.getElementById('taluk_filter_select');
        talukSel.innerHTML = '<option value="" hidden>Select Taluk</option>';
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
            firkaSel.innerHTML = '<option value="" hidden>Select Firka</option>';
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

    function updateTpShopsLink(tp) {
        var link = document.getElementById('tpShopsLink');
        if (!link) { return; }
        if (tp) {
            link.href = 'view-tp-shops.php?tp_id=' + encodeURIComponent(tp.id);
            document.getElementById('tpShopsLinkLabel').textContent = tp.name + "'s Shops";
            link.style.display = 'inline-flex';
        } else {
            // No TP covers this firka — nothing to show, fall back to just
            // "My Manage Shop" (the DM's own list) being visible.
            link.style.display = 'none';
        }
    }

    // Renders each shop option — highlights ones whose mobile number already
    // matches a shop under the currently-detected TP (see tpShopMobiles),
    // since selecting one of those REUSES the TP's existing shop record
    // (bridgeOrderToTp's mobile-match dedupe) instead of creating a new one.
    function shopOptionTemplate(state) {
        if (!state.id) { return state.text; }
        var mobile = state.element ? (state.element.getAttribute('data-mobile') || '') : '';
        var $wrap = $('<span></span>').text(state.text);
        if (mobile && tpShopMobiles.has(mobile)) {
            $wrap.css({ background: '#fef3c7', display: 'block', padding: '4px 6px', borderRadius: '4px' });
            $wrap.append($('<small></small>').css({ color: '#92400e', fontWeight: '600', marginLeft: '6px' }).text('(Already with TP)'));
        }
        return $wrap;
    }

    function initShopSelect2() {
        var $shop = $('#shop_select');
        if ($shop.data('select2')) { $shop.select2('destroy'); }
        $shop.select2({ width: '100%', templateResult: shopOptionTemplate });
    }

    // Pulls the TP's own existing shop mobile numbers so shopOptionTemplate
    // can highlight matches. Best-effort: a failed/slow request just leaves
    // nothing highlighted rather than blocking the form.
    function fetchTpShopMobiles(tpId) {
        tpShopMobiles = new Set();
        $('#shopTpMatchHint').hide();
        $.getJSON('get-tp-shop-mobiles.php', { tp_id: tpId }, function (resp) {
            if (resp && resp.success && Array.isArray(resp.mobiles)) {
                tpShopMobiles = new Set(resp.mobiles);
                if (tpShopMobiles.size > 0) { $('#shopTpMatchHint').show(); }
            }
        });
    }

    function onFirkaChange(firkaId) {
        var $tp = $('#tp_select');
        if (!$tp.length) { return; }
        var tp = firkaTpData[firkaId];
        if (!tp) {
            // Vacant Area — no TP covers this Firka. Assign To CP instead of
            // letting the DM manually pick a TP (2026-11-12, user request).
            unlockTpSelect();
            // unlockTpSelect() -> rebuildTPs('') deliberately restores
            // whatever TP was selected before (so re-picking the same firka
            // doesn't lose it) — but that means a TP chosen for an EARLIER,
            // TP-covered firka silently survives in this hidden field even
            // though the visible form now shows the CP/Company dropdown.
            // order_action_get.php trusts $_POST['tp_id'] alone, so without
            // this it still bridges the order to that stale TP (2026-11-20
            // bug report: picked a TP-covered firka, switched to a vacant
            // one, chose Company, but the order still showed "Pending with
            // TP"). Clear it so an empty firka can never submit a TP id.
            $tp.val('').trigger('change.select2');
            showCpBlock();
            var shopOpt = document.getElementById('shop_select').selectedOptions[0];
            rebuildCPs(shopOpt ? shopOpt.getAttribute('data-district') : '');
            return;
        }
        hideCpBlock();
        // Symmetric case: a CP (or "Company") picked for an earlier vacant
        // firka must not survive into this TP-covered one either.
        $('#cp_select').val('').trigger('change.select2');

        // Auto-detect the TP covering this firka and lock the field — the DM
        // no longer picks a TP manually once a firka is chosen. The select
        // itself stays enabled (so its value still submits); only the
        // visible select2 widget is blocked from further interaction.
        if (!$tp.find('option[value="' + tp.id + '"]').length) {
            $tp.append($('<option>', { value: tp.id, text: tp.name }));
        }
        $tp.val(tp.id).trigger('change.select2');
        $tp.next('.select2-container').css('pointer-events', 'none');
        updateTpShopsLink(tp);
        fetchTpShopMobiles(tp.id);
    }

    // "My Manage Shop" — instead of navigating away, shows every shop this DM
    // owns right in the same dropdown (ignores the district/taluk/firka
    // filter entirely) and opens it immediately for picking, so a get-order
    // can be raised for any of their shops without leaving the page.
    function showAllMyShops() {
        rebuildShops('', '');
        var $shop = $('#shop_select');
        if ($shop.data('select2')) {
            $shop.select2('open');
        }
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
                        'data-mobile':      s.mobile,
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

        // Get Order can only be submitted while the DM is physically near the
        // shop — confirmed 2026-09-11. Client-side only checks distance when
        // this shop already has a KNOWN saved lat/lng (data-lat/lng below) —
        // a shop with none is geocoded from its own stored address, not the
        // DM's GPS, by order_action_get.php server-side (never client-side —
        // that's an external API call this page shouldn't be making on every
        // keystroke), which then does the real distance check itself. So a
        // no-location shop always falls through to the plain confirm() here;
        // the server may still reject it if its address can't be geocoded.
        var GET_ORDER_MAX_METERS = 100;
        var SKIP_LOCATION_CHECK = <?php echo $skipLocationCheckForThisDm ? 'true' : 'false'; ?>;
        window.confirmGetOrderSubmit = function confirmGetOrderSubmit() {
            if (cartOrder.length === 0) {
                alert('Please add at least one product to this order.');
                return false;
            }
            for (var ci = 0; ci < cartOrder.length; ci++) {
                if (!(cart[cartOrder[ci]].qty > 0)) {
                    alert('Please enter a valid Qty for every selected product.');
                    return false;
                }
            }
            if (SKIP_LOCATION_CHECK) { return confirm('Please confirm'); }
            if (capturedLat === null || capturedLng === null) {
                alert('Could not get your current location. Please enable location access and try again — Get Order requires you to be at the shop.');
                return false;
            }
            var selected = document.getElementById('shop_select');
            var opt = selected ? selected.options[selected.selectedIndex] : null;
            if (opt && opt.getAttribute('data-geocode-failed') === '1') {
                alert("This shop's address could not be automatically located. Please edit the shop and manually capture its location before taking a Get Order here.");
                return false;
            }
            var shopLat = opt ? parseFloat(opt.getAttribute('data-lat')) : NaN;
            var shopLng = opt ? parseFloat(opt.getAttribute('data-lng')) : NaN;
            if (!opt || !opt.value || isNaN(shopLat) || isNaN(shopLng)) {
                return confirm('Please confirm');
            }
            var meters = distanceMeters(capturedLat, capturedLng, shopLat, shopLng);
            if (meters > GET_ORDER_MAX_METERS) {
                var distText = meters >= 1000 ? (meters / 1000).toFixed(2) + ' km' : Math.round(meters) + ' m';
                alert('You are ' + distText + ' away from this shop\'s saved location. Get Order can only be submitted within ' + GET_ORDER_MAX_METERS + ' m of the shop — please move closer and try again.');
                return false;
            }
            return confirm('Please confirm');
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

        // Shows the distance warning as soon as a shop is picked — not only
        // after Add is clicked — so a DM who's clearly nowhere near the shop
        // finds out before filling in products, not after. Most shops already
        // have data-lat/data-lng baked into the <option> (updateShopDistance
        // handles those directly); a shop with none is geocoded here via its
        // stored address, same lookup order_action_get.php does at submit
        // time — this is just a preview, nothing is saved until Add is
        // actually pressed.
        $('#shop_select').on('change', function() {
            var opt = this.options[this.selectedIndex];
            var infoEl = document.getElementById('shopDistanceInfo');
            if (!opt || !opt.value) { if (infoEl) infoEl.textContent = ''; return; }
            opt.removeAttribute('data-geocode-failed');
            var hasLat = opt.getAttribute('data-lat');
            var hasLng = opt.getAttribute('data-lng');
            if (hasLat && hasLng && hasLat !== '' && hasLng !== '') {
                updateShopDistance(true);
                return;
            }
            if (infoEl) infoEl.textContent = '📍 Checking this shop\'s location…';
            fetch('geocode-shop.php?shop_id=' + encodeURIComponent(opt.value))
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (this.value !== opt.value) return; // shop changed again while this was in flight
                    if (data && data.lat && data.lng) {
                        opt.setAttribute('data-lat', data.lat);
                        opt.setAttribute('data-lng', data.lng);
                        updateShopDistance(true);
                    } else {
                        // Marked so confirmGetOrderSubmit() blocks right away
                        // instead of letting the DM fill in products only to
                        // have order_action_get.php reject it at the end.
                        opt.setAttribute('data-geocode-failed', '1');
                        if (infoEl) infoEl.textContent = '⚠️ Could not verify this shop\'s location — please edit the shop and capture its location before taking a Get Order here.';
                    }
                }.bind(this))
                .catch(function() {
                    if (infoEl) infoEl.textContent = '';
                });
        });
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