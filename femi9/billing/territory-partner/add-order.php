<?php
include("checksession.php");
include("config.php");
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$order_date = date("Y-m-d");
$title      = "Field Order";
$manage_url = "manage-orders.php";

function GeraHash($qtd) {
    $Caracteres = '123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $max = strlen($Caracteres) - 1;
    $Hash = '';
    for ($x = 1; $x <= $qtd; $x++) {
        $Hash .= substr($Caracteres, rand(0, $max), 1);
    }
    return $Hash;
}
$tempID = GeraHash(32) . date("dmy") . date("gis");

// ── Shops onboarded by this TP — raw location ids for the cascading filter ──
$shopList = [];
$stmt = mysqli_prepare($db_conn,
    "SELECT id, name, district_id, taluk_id, firka_id
     FROM shop
     WHERE onboard_userID=? AND onboard_userTYPE='territory_partner'
     ORDER BY name ASC"
);
mysqli_stmt_bind_param($stmt, "s", $Login_user_IDvl);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($s = mysqli_fetch_assoc($res)) $shopList[] = $s;
mysqli_stmt_close($stmt);

// Same District → Division → Taluk → Firka cascading selector used on
// shop-add.php, scoped to this TP's assigned territory (see geo_layers.php).
include("geo_layers.php");

// ── Products — only what this TP actually has stock for, same list
// shop-invoice-add.php uses, so anything ordered here can really be invoiced. ──
$productList = [];
$stmtProd = mysqli_prepare($db_conn,
    "SELECT p.id, p.productName
     FROM products p
     INNER JOIN territory_partner_stock tps ON tps.product_id = p.id AND tps.territory_partner_id = ? AND tps.closing_qty > 0
     ORDER BY p.productName ASC"
);
mysqli_stmt_bind_param($stmtProd, "i", $Login_user_IDvl);
mysqli_stmt_execute($stmtProd);
$resProd = mysqli_stmt_get_result($stmtProd);
while ($p = mysqli_fetch_assoc($resProd)) $productList[] = $p;
mysqli_stmt_close($stmtProd);

$isNoOrder = (isset($_REQUEST['actorder']) && $_REQUEST['actorder'] == "femi9noorder12aedrftgop2we4mncl");

// Renders the District/Division/Taluk/Firka cascading filter (display-only —
// narrows the Shop dropdown below, doesn't submit as form fields itself).
function render_geo_filter($layers, $geoNodes, $depthToField) {
    foreach ($layers as $_layer) {
        $_d = (int)$_layer['depth'];
        if ($_d < 3 || !isset($depthToField[$_d])) continue; // only levels shop rows actually store
        $_label = htmlspecialchars($_layer['layer_name']);
?>
    <label class="form-label"><?php echo $_label; ?> Filter</label>
    <select class="form-control geo-filter" id="ord_loc_<?php echo $_d; ?>" data-depth="<?php echo $_d; ?>"
            onchange="ordGeoPopulate(<?php echo $_d; ?>, this.value); ordFilterShops();">
        <option value="" hidden>Select <?php echo $_label; ?></option>
        <?php if ($_d === 3): foreach ($geoNodes as $_n): if ((int)$_n['depth'] === 3): ?>
        <option value="<?php echo $_n['id']; ?>"><?php echo htmlspecialchars($_n['name']); ?></option>
        <?php endif; endforeach; endif; ?>
    </select><br/>
<?php
    }
}
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
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        table td { padding: 5px !important; }
        select:disabled { opacity: 0.45; }
        #add { background:green; border:1px solid green; }
        #add:hover, #add:focus { background:#DDD; color:#000; border:1px solid #000; }
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
                                                <td><?php echo $title;?><?php echo $isNoOrder ? ' - No Order' : ' - Get Order';?></td>
                                                <td><a href="<?php echo $manage_url;?>" title="Manage Orders">&#9776;</a></td>
                                            </tr>
                                        </table>
                                    </h1>
                                    <p>
                                        <a href="add-order.php" class="btn btn-<?php echo $isNoOrder ? 'outline-primary' : 'primary';?> btn-sm">Get Order</a>
                                        <a href="add-order.php?actorder=femi9noorder12aedrftgop2we4mncl" class="btn btn-<?php echo $isNoOrder ? 'primary' : 'outline-primary';?> btn-sm">No Order</a>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">

                                        <?php if (empty($shopList)): ?>
                                        <div class="alert alert-warning">No shops onboarded yet. Add a shop before logging a field order.</div>
                                        <?php elseif ($isNoOrder): ?>
                                        <!-- ─────────────── NO ORDER FORM ─────────────── -->
                                        <form action="order-action.php" method="post" id="uploadForm">
                                            <input type="hidden" name="tp_id"      value="<?=htmlspecialchars($Login_user_IDvl)?>">
                                            <input type="hidden" name="order_date" value="<?=htmlspecialchars($order_date)?>">
                                            <input type="hidden" name="order_id"   value="<?=htmlspecialchars($tempID)?>">

                                            <?php render_geo_filter($layers, $geoNodes, $depthToField); ?>

                                            <label class="form-label">Shop*</label>
                                            <select class="form-control" name="shop_id" id="shop_select" required>
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($shopList as $s): ?>
                                                <option value="<?=htmlspecialchars($s['id'])?>"
                                                        data-district="<?=htmlspecialchars($s['district_id'])?>"
                                                        data-taluk="<?=htmlspecialchars($s['taluk_id'])?>"
                                                        data-firka="<?=htmlspecialchars($s['firka_id'])?>">
                                                    <?=htmlspecialchars($s['name'])?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select><br/>

                                            <label class="form-label">Order Date*</label>
                                            <input type="text" disabled value="<?=date("d-m-Y", strtotime($order_date))?>" class="form-control"><br/>

                                            <label class="form-label">Reason*</label>
                                            <textarea name="noorder_reason" class="form-control" required></textarea><br/>

                                            <label class="form-label">Notes</label>
                                            <textarea name="marketing_tool" class="form-control"></textarea><br/>

                                            <button type="submit" name="add_order_no" onclick="return confirm('Please confirm');" class="btn btn-primary">
                                                <i class="material-icons">add</i> Add
                                            </button>
                                        </form>

                                        <?php else: ?>
                                        <!-- ─────────────── GOT ORDER FORM ─────────────── -->
                                        <form action="order-action-get.php" method="post" id="uploadForm" onsubmit="return validateOrderLines();">
                                            <input type="hidden" name="tp_id"      value="<?=htmlspecialchars($Login_user_IDvl)?>">
                                            <input type="hidden" name="order_date" value="<?=htmlspecialchars($order_date)?>">
                                            <input type="hidden" name="order_id"   value="<?=htmlspecialchars($tempID)?>">

                                            <?php render_geo_filter($layers, $geoNodes, $depthToField); ?>

                                            <label class="form-label">Shop*</label>
                                            <select class="form-control" name="shop_id" id="shop_select" required>
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($shopList as $s): ?>
                                                <option value="<?=htmlspecialchars($s['id'])?>"
                                                        data-district="<?=htmlspecialchars($s['district_id'])?>"
                                                        data-taluk="<?=htmlspecialchars($s['taluk_id'])?>"
                                                        data-firka="<?=htmlspecialchars($s['firka_id'])?>">
                                                    <?=htmlspecialchars($s['name'])?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select><br/>

                                            <label class="form-label">Order Date*</label>
                                            <input type="text" disabled value="<?=date("d-m-Y", strtotime($order_date))?>" class="form-control"><br/>

                                            <label class="form-label">Notes</label>
                                            <textarea name="marketing_tool" class="form-control"></textarea><br/>

                                            <?php if (empty($productList)): ?>
                                            <div class="alert alert-warning">No stock available to order against. Add stock before logging a got-order visit.</div>
                                            <?php else: ?>
                                            <label class="form-label">Select Product</label>
                                            <select class="form-control mb-2" id="pr_select" onchange="showOrderPrice(this.value)">
                                                <option value="" hidden>Select Product</option>
                                                <?php foreach ($productList as $p): ?>
                                                <option value="<?=$p['id']?>"><?=htmlspecialchars($p['productName'])?></option>
                                                <?php endforeach; ?>
                                            </select>

                                            <div class="row g-2 align-items-end mb-3">
                                                <div class="col">
                                                    <label class="form-label">Qty</label>
                                                    <input type="number" min="1" id="ord_qty" onkeyup="orderTotalkm()" placeholder="Qty" class="form-control">
                                                </div>
                                                <div class="col">
                                                    <label class="form-label">Price</label>
                                                    <input type="number" min="0" step="any" id="ord_amount" onkeyup="orderTotalkm()" placeholder="Price" class="form-control" readonly>
                                                </div>
                                                <div class="col">
                                                    <label class="form-label">Total</label>
                                                    <input type="number" min="0" step="any" id="ord_total" placeholder="Total" class="form-control" readonly>
                                                </div>
                                                <div class="col">
                                                    <label class="form-label">Disc(%)</label>
                                                    <input type="number" min="0" step="any" id="ord_disc_pct" onkeyup="orderDiscAmount()" placeholder="Disc(%)" class="form-control">
                                                </div>
                                                <div class="col">
                                                    <label class="form-label">Disc(Rs.)</label>
                                                    <input type="number" min="0" step="any" id="ord_disc_amt" placeholder="Disc(Rs.)" class="form-control">
                                                </div>
                                                <div class="col-auto">
                                                    <button type="button" class="btn btn-primary" id="add" onclick="addOrderLine()"><i class="material-icons">add</i> Add</button>
                                                </div>
                                            </div>

                                            <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Price</th><th>Disc</th><th>Total</th><th></th></tr></thead>
                                                <tbody id="orderItemsBody">
                                                    <tr id="orderItemsEmptyRow"><td colspan="7" class="text-center">No products added yet.</td></tr>
                                                </tbody>
                                            </table>
                                            </div>
                                            <div id="hiddenInputsHolder"></div>

                                            <button type="submit" name="add_order_get" onclick="return confirm('Please confirm');" class="btn btn-primary">
                                                <i class="material-icons">add</i> Add
                                            </button>
                                            <?php endif; ?>
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
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>

    <script>
    // ── Got-order product line entry — same Select Product / Qty / Price /
    // Total / Add pattern as shop-invoice-add.php, so this page feels and
    // behaves exactly like adding items to a real invoice. Price is fetched
    // live via loadPrice.php (invuser=shop, i.e. products.outlet_price) —
    // purely a preview here; the invoice created later from this visit
    // (order-to-invoice.php) always re-reads the price at that time.
    var orderLines = [];

    function showOrderPrice(str) {
        document.getElementById('ord_amount').value = '';
        orderTotalkm();
        if (str === '') return;
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange = function() {
            if (xmlhttp.readyState === 4 && xmlhttp.status === 200) {
                var temp = document.createElement('div');
                temp.innerHTML = xmlhttp.responseText;
                var input = temp.querySelector('input');
                document.getElementById('ord_amount').value = input ? input.value : '';
                orderTotalkm();
            }
        };
        xmlhttp.open("GET", "loadPrice.php?q=" + str + "&invuser=shop", true);
        xmlhttp.send();
    }

    function orderTotalkm() {
        var qty   = parseFloat(document.getElementById('ord_qty').value) || 0;
        var price = parseFloat(document.getElementById('ord_amount').value) || 0;
        document.getElementById('ord_total').value = (qty * price).toFixed(2);
        orderDiscAmount();
    }

    // Disc(%) drives Disc(Rs.) off the current Total — same one-directional
    // calc as shop-invoice-add.php's discamount().
    function orderDiscAmount() {
        var total = parseFloat(document.getElementById('ord_total').value) || 0;
        var pct   = parseFloat(document.getElementById('ord_disc_pct').value) || 0;
        document.getElementById('ord_disc_amt').value = (total * pct / 100).toFixed(2);
    }

    function addOrderLine() {
        var sel   = document.getElementById('pr_select');
        var prId  = sel.value;
        var prName = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
        var qty   = parseInt(document.getElementById('ord_qty').value) || 0;
        var price = parseFloat(document.getElementById('ord_amount').value) || 0;
        var discPct = parseFloat(document.getElementById('ord_disc_pct').value) || 0;
        var discAmt = parseFloat(document.getElementById('ord_disc_amt').value) || 0;

        if (!prId) { alert('Select a product.'); return; }
        if (qty <= 0) { alert('Enter a valid qty.'); return; }
        for (var i = 0; i < orderLines.length; i++) {
            if (orderLines[i].pr_id === prId) { alert('That product is already added.'); return; }
        }

        orderLines.push({ pr_id: prId, name: prName, qty: qty, price: price, discPct: discPct, discAmt: discAmt });
        renderOrderLines();

        sel.value = '';
        document.getElementById('ord_qty').value = '';
        document.getElementById('ord_amount').value = '';
        document.getElementById('ord_total').value = '';
        document.getElementById('ord_disc_pct').value = '';
        document.getElementById('ord_disc_amt').value = '';
    }

    function removeOrderLine(idx) {
        orderLines.splice(idx, 1);
        renderOrderLines();
    }

    function renderOrderLines() {
        var tbody = document.getElementById('orderItemsBody');
        tbody.innerHTML = '';
        if (orderLines.length === 0) {
            tbody.innerHTML = '<tr id="orderItemsEmptyRow"><td colspan="7" class="text-center">No products added yet.</td></tr>';
        } else {
            orderLines.forEach(function(l, idx) {
                var grossTotal = l.qty * l.price;
                var netTotal = (grossTotal - l.discAmt).toFixed(2);
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<th>' + (idx + 1) + '</th>' +
                    '<td>' + l.name + '</td>' +
                    '<td>' + l.qty + '</td>' +
                    '<td>₹' + l.price.toFixed(2) + '</td>' +
                    '<td>₹' + l.discAmt.toFixed(2) + '(' + l.discPct.toFixed(2) + '%)</td>' +
                    '<td>₹' + netTotal + '</td>' +
                    '<td><span class="badge bg-danger" style="cursor:pointer;" onclick="removeOrderLine(' + idx + ')">Remove</span></td>';
                tbody.appendChild(tr);
            });
        }

        var holder = document.getElementById('hiddenInputsHolder');
        holder.innerHTML = '';
        orderLines.forEach(function(l) {
            holder.innerHTML +=
                '<input type="hidden" name="pr_id[]" value="' + l.pr_id + '">' +
                '<input type="hidden" name="qty[]" value="' + l.qty + '">' +
                '<input type="hidden" name="discount_percentage[]" value="' + l.discPct + '">' +
                '<input type="hidden" name="discount_amount[]" value="' + l.discAmt + '">';
        });
    }

    function validateOrderLines() {
        if (orderLines.length === 0) {
            alert('Add at least one product before submitting.');
            return false;
        }
        return true;
    }

    // ── Shop location metadata (raw partner_location_nodes ids) ──────────────
    var allShops = [];
    document.querySelectorAll('#shop_select option[data-district]').forEach(function(opt) {
        allShops.push({
            id: opt.value, text: opt.textContent.trim(),
            district: opt.getAttribute('data-district') || '',
            taluk:    opt.getAttribute('data-taluk') || '',
            firka:    opt.getAttribute('data-firka') || ''
        });
    });

    // depth 3 = district_id, depth 4 = taluk_id, depth 5 = firka_id on the shop row
    // (see geo_layers.php's $depthToField — layer *names* like "Division"/"Taluk"
    // don't line up 1:1 with these column names, but the depth numbers do).
    var geoNodes  = <?php echo json_encode(array_values($geoNodes)); ?>;
    var geoLayers = <?php echo json_encode($layers); ?>;
    var ordDepthField = { 3: 'district', 4: 'taluk', 5: 'firka' };

    function ordGeoLayerName(depth) {
        var l = geoLayers.find(function(x){ return x.depth === depth; });
        return l ? l.layer_name : 'Location';
    }

    function ordGeoPopulate(parentDepth, parentId) {
        parentId = parentId ? parseInt(parentId) : null;
        for (var d = parentDepth + 1; d <= 5; d++) {
            var s = document.getElementById('ord_loc_' + d);
            if (s) s.innerHTML = '<option value="" hidden>Select ' + ordGeoLayerName(d) + '</option>';
        }
        if (!parentId) return;
        var childDepth = parentDepth + 1;
        var childSel = document.getElementById('ord_loc_' + childDepth);
        if (!childSel) return;
        var children = geoNodes.filter(function(n){ return n.depth === childDepth && n.parent_id === parentId; });
        children.forEach(function(n) {
            var o = document.createElement('option');
            o.value = n.id; o.textContent = n.name;
            childSel.appendChild(o);
        });
    }

    // Narrows the Shop dropdown to shops matching every currently-selected
    // geo filter level (district/taluk/firka) — levels left unselected are
    // treated as wildcards.
    function ordFilterShops() {
        var selected = {};
        [3, 4, 5].forEach(function(d) {
            var el = document.getElementById('ord_loc_' + d);
            selected[d] = el && el.value ? el.value : '';
        });

        var select = $('#shop_select');
        select.empty();
        select.append('<option value="" hidden>Select</option>');
        allShops.forEach(function(s) {
            var ok = true;
            [3, 4, 5].forEach(function(d) {
                var field = ordDepthField[d];
                if (selected[d] && s[field] !== selected[d]) ok = false;
            });
            if (ok) {
                select.append($('<option>', {
                    value: s.id, 'data-district': s.district, 'data-taluk': s.taluk, 'data-firka': s.firka, text: s.text
                }));
            }
        });
        select.trigger('change.select2');
        select.val('').trigger('change');
    }
    </script>
</body>
</html>
