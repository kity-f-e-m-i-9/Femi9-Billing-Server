<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
require_once("include/GodownAccess.php");
include("config.php");
date_default_timezone_set("Asia/Kolkata");

$stmt_products = $db_conn->prepare("SELECT id, productName FROM products WHERE (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY productName ASC");
$stmt_products->execute();
$all_products = $stmt_products->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_products->close();

$stmt_godown = $db_conn->prepare("SELECT id, gname, address_line1, address_line2, gstin, state, contact, email FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC");
$stmt_godown->execute();
$all_godowns = $stmt_godown->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_godown->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WhatsApp Order PDF</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
    <style>
        #itemsTable th, #itemsTable td { vertical-align: middle; }
        #printArea { display: none; }
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { display: block !important; position: absolute; left: 0; top: 0; width: 100%; }
        }
        #printArea .doc-table th, #printArea .doc-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
        }
        #printArea .doc-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        #printArea .addr-table { width: 100%; margin-top: 10px; }
        #printArea .addr-table td { vertical-align: top; width: 50%; padding-right: 10px; white-space: pre-wrap; }
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
                                <h1>WhatsApp Order PDF</h1>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">

                                    <div class="form-group">
                                        <label for="companyProfile">Company Profile</label>
                                        <select id="companyProfile" class="form-control">
                                            <option value="">-- Select --</option>
                                            <?php foreach ($all_godowns as $g):
                                                $addrParts = array_filter([$g['address_line1'] ?? '', $g['address_line2'] ?? '']);
                                                $fullAddr = trim(implode("\n", $addrParts));
                                                if (!empty($g['gstin'])) { $fullAddr .= "\nGSTIN: " . $g['gstin']; }
                                                if (!empty($g['contact'])) { $fullAddr .= "\nContact: " . $g['contact']; }
                                            ?>
                                            <option value="<?= (int)$g['id'] ?>" data-address="<?= htmlspecialchars($fullAddr, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($g['gname']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="fromAddress">From Address</label>
                                        <textarea id="fromAddress" class="form-control" rows="3" placeholder="Select a Company Profile to auto-fill, or type manually"></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="toAddress">To Address</label>
                                        <textarea id="toAddress" class="form-control" rows="3" placeholder="Type recipient address..."></textarea>
                                    </div>

                                    <label>Order Items</label>
                                    <table id="itemsTable" class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th style="width:60%;">Product</th>
                                                <th style="width:25%;">Qty</th>
                                                <th style="width:15%;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="item-row">
                                                <td>
                                                    <select class="form-control product-select">
                                                        <option value="" hidden>Select Product</option>
                                                        <?php foreach ($all_products as $p): ?>
                                                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['productName']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td><input type="text" class="form-control qty-input" placeholder="Qty"></td>
                                                <td><button type="button" class="btn btn-danger btn-sm remove-row">&times;</button></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <button type="button" id="addRowBtn" class="btn btn-secondary btn-sm">+ Add Product</button>

                                    <div style="margin-top:20px;">
                                        <button type="button" id="generateBtn" class="btn btn-success">Print / Save as PDF</button>
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

<div id="printArea">
    <h2>Order</h2>
    <table class="addr-table">
        <tr>
            <td><b>From:</b><br/><span id="pFrom"></span></td>
            <td><b>To:</b><br/><span id="pTo"></span></td>
        </tr>
    </table>
    <table class="doc-table">
        <thead>
            <tr><th style="width:10%;">Sl No.</th><th style="width:70%;">Product</th><th style="width:20%;">Qty</th></tr>
        </thead>
        <tbody id="pItemsBody"></tbody>
    </table>
</div>

<script>
document.getElementById('companyProfile').addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    document.getElementById('fromAddress').value = opt.dataset.address || '';
});

function attachRemoveHandler(btn) {
    btn.addEventListener('click', function() {
        var tbody = document.querySelector('#itemsTable tbody');
        if (tbody.rows.length > 1) {
            btn.closest('tr').remove();
        } else {
            alert('Cannot remove all product rows.');
        }
    });
}

document.querySelectorAll('#itemsTable .remove-row').forEach(attachRemoveHandler);

document.getElementById('addRowBtn').addEventListener('click', function() {
    var tbody = document.querySelector('#itemsTable tbody');
    if (tbody.rows.length >= 100) {
        alert('Maximum 100 product rows allowed.');
        return;
    }
    var newRow = tbody.rows[0].cloneNode(true);
    newRow.querySelectorAll('select, input').forEach(function(el) { el.value = ''; });
    tbody.appendChild(newRow);
    attachRemoveHandler(newRow.querySelector('.remove-row'));
});

document.getElementById('generateBtn').addEventListener('click', function() {
    var fromVal = document.getElementById('fromAddress').value.trim();
    var toVal = document.getElementById('toAddress').value.trim();
    if (!fromVal) { alert('Please enter a From Address.'); return; }
    if (!toVal) { alert('Please enter a To Address.'); return; }

    var body = document.getElementById('pItemsBody');
    body.innerHTML = '';
    var i = 0;
    document.querySelectorAll('#itemsTable tbody tr').forEach(function(row) {
        var productSel = row.querySelector('.product-select');
        var qty = row.querySelector('.qty-input').value.trim();
        if (!productSel.value || !qty) { return; }
        var productName = productSel.options[productSel.selectedIndex].text;
        i++;
        var tr = document.createElement('tr');
        var tdSl = document.createElement('td'); tdSl.textContent = i;
        var tdName = document.createElement('td'); tdName.textContent = productName;
        var tdQty = document.createElement('td'); tdQty.textContent = qty;
        tr.appendChild(tdSl); tr.appendChild(tdName); tr.appendChild(tdQty);
        body.appendChild(tr);
    });

    if (i === 0) { alert('Please add at least one product with a quantity.'); return; }

    document.getElementById('pFrom').textContent = fromVal;
    document.getElementById('pTo').textContent = toVal;

    window.print();
});
</script>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/highlight/highlight.pack.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>

</html>
