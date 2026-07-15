<?php
include("checksession.php");
include("config.php");
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$order_date = date("Y-m-d");
$title      = "Purchase Order";

// Full company product catalog — this is a stock replenishment request to the
// company, not limited to what the TP already holds (unlike Field Order).
$productList = [];
$resProd = mysqli_query($db_conn, "SELECT id, productName, stockist_price FROM products WHERE deleted_at IS NULL ORDER BY productName ASC");
if ($resProd) while ($p = mysqli_fetch_assoc($resProd)) $productList[] = $p;
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
                                                <td><?php echo $title;?></td>
                                                <td><a href="manage-purchase-orders.php" title="My Purchase Orders">&#9776;</a></td>
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

                                        <form action="purchase-order-action.php" method="post" id="uploadForm" onsubmit="return validatePoLines();">
                                            <label class="form-label">Territory Partner</label>
                                            <input type="text" disabled value="<?=htmlspecialchars($Login_user_name)?>" class="form-control"><br/>

                                            <label class="form-label">Invoice Date*</label>
                                            <input type="text" disabled value="<?=date("d-m-Y", strtotime($order_date))?>" class="form-control"><br/>

                                            <?php if (empty($productList)): ?>
                                            <div class="alert alert-warning">No products available to order.</div>
                                            <?php else: ?>
                                            <label class="form-label">Select Product</label>
                                            <select class="form-control mb-2" id="pr_select" onchange="showPoPrice(this.value)">
                                                <option value="" hidden>Select Product</option>
                                                <?php foreach ($productList as $p): ?>
                                                <option value="<?=$p['id']?>" data-price="<?=htmlspecialchars($p['stockist_price'])?>"><?=htmlspecialchars($p['productName'])?></option>
                                                <?php endforeach; ?>
                                            </select>

                                            <div class="row g-2 align-items-end mb-3">
                                                <div class="col">
                                                    <label class="form-label">Qty</label>
                                                    <input type="number" min="1" id="po_qty" onkeyup="poTotal()" placeholder="Qty" class="form-control">
                                                </div>
                                                <div class="col">
                                                    <label class="form-label">Price</label>
                                                    <input type="number" min="0" step="any" id="po_price" placeholder="Price" class="form-control" disabled>
                                                </div>
                                                <div class="col">
                                                    <label class="form-label">Total</label>
                                                    <input type="number" min="0" step="any" id="po_total" placeholder="Total" class="form-control" readonly>
                                                </div>
                                                <div class="col">
                                                    <label class="form-label">Disc(%)</label>
                                                    <input type="number" min="0" step="any" id="po_disc_pct" onkeyup="poDiscAmount()" placeholder="Disc(%)" class="form-control">
                                                </div>
                                                <div class="col">
                                                    <label class="form-label">Disc(Rs.)</label>
                                                    <input type="number" min="0" step="any" id="po_disc_amt" placeholder="Disc(Rs.)" class="form-control">
                                                </div>
                                                <div class="col-auto">
                                                    <button type="button" class="btn btn-primary" id="add" onclick="addPoLine()"><i class="material-icons">add</i> Add</button>
                                                </div>
                                            </div>

                                            <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Price</th><th>Disc</th><th>Total</th><th></th></tr></thead>
                                                <tbody id="poItemsBody">
                                                    <tr id="poItemsEmptyRow"><td colspan="7" class="text-center">No products added yet.</td></tr>
                                                </tbody>
                                            </table>
                                            </div>
                                            <div id="hiddenInputsHolder"></div>

                                            <button type="submit" name="submit_po" onclick="return confirm('Submit this purchase order?');" class="btn btn-primary">
                                                <i class="material-icons">add</i> Submit Purchase Order
                                            </button>
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
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>

    <script>
    var poLines = [];

    function showPoPrice(str) {
        var sel = document.getElementById('pr_select');
        var opt = sel.options[sel.selectedIndex];
        document.getElementById('po_price').value = str ? (opt.getAttribute('data-price') || '') : '';
        poTotal();
    }

    function poTotal() {
        var qty   = parseFloat(document.getElementById('po_qty').value) || 0;
        var price = parseFloat(document.getElementById('po_price').value) || 0;
        document.getElementById('po_total').value = (qty * price).toFixed(2);
        poDiscAmount();
    }

    function poDiscAmount() {
        var total = parseFloat(document.getElementById('po_total').value) || 0;
        var pct   = parseFloat(document.getElementById('po_disc_pct').value) || 0;
        document.getElementById('po_disc_amt').value = (total * pct / 100).toFixed(2);
    }

    function addPoLine() {
        var sel   = document.getElementById('pr_select');
        var prId  = sel.value;
        var prName = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
        var qty   = parseInt(document.getElementById('po_qty').value) || 0;
        var price = parseFloat(document.getElementById('po_price').value) || 0;
        var discPct = parseFloat(document.getElementById('po_disc_pct').value) || 0;
        var discAmt = parseFloat(document.getElementById('po_disc_amt').value) || 0;

        if (!prId) { alert('Select a product.'); return; }
        if (qty <= 0) { alert('Enter a valid qty.'); return; }
        for (var i = 0; i < poLines.length; i++) {
            if (poLines[i].pr_id === prId) { alert('That product is already added.'); return; }
        }

        poLines.push({ pr_id: prId, name: prName, qty: qty, price: price, discPct: discPct, discAmt: discAmt });
        renderPoLines();

        sel.value = '';
        document.getElementById('po_qty').value = '';
        document.getElementById('po_price').value = '';
        document.getElementById('po_total').value = '';
        document.getElementById('po_disc_pct').value = '';
        document.getElementById('po_disc_amt').value = '';
    }

    function removePoLine(idx) {
        poLines.splice(idx, 1);
        renderPoLines();
    }

    function renderPoLines() {
        var tbody = document.getElementById('poItemsBody');
        tbody.innerHTML = '';
        if (poLines.length === 0) {
            tbody.innerHTML = '<tr id="poItemsEmptyRow"><td colspan="7" class="text-center">No products added yet.</td></tr>';
        } else {
            poLines.forEach(function(l, idx) {
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
                    '<td><span class="badge bg-danger" style="cursor:pointer;" onclick="removePoLine(' + idx + ')">Remove</span></td>';
                tbody.appendChild(tr);
            });
        }

        var holder = document.getElementById('hiddenInputsHolder');
        holder.innerHTML = '';
        poLines.forEach(function(l) {
            holder.innerHTML +=
                '<input type="hidden" name="pr_id[]" value="' + l.pr_id + '">' +
                '<input type="hidden" name="qty[]" value="' + l.qty + '">' +
                '<input type="hidden" name="price[]" value="' + l.price + '">' +
                '<input type="hidden" name="discount_percentage[]" value="' + l.discPct + '">' +
                '<input type="hidden" name="discount_amount[]" value="' + l.discAmt + '">';
        });
    }

    function validatePoLines() {
        if (poLines.length === 0) {
            alert('Add at least one product before submitting.');
            return false;
        }
        return true;
    }
    </script>
</body>
</html>
