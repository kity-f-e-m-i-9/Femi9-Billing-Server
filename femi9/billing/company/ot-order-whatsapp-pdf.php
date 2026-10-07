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

// Shared markup for one order session (Company Profile -> From/To Address ->
// Product+Qty rows). Rendered once here into a <template> and cloned by JS
// for both the first session and every "Add Another Session" click, so the
// PHP-rendered dropdown options never need to be duplicated server-side.
function render_godown_options($all_godowns) {
    foreach ($all_godowns as $g) {
        $addrParts = array_filter([$g['address_line1'] ?? '', $g['address_line2'] ?? '']);
        $fullAddr = trim(implode("\n", $addrParts));
        if (!empty($g['gstin'])) { $fullAddr .= "\nGSTIN: " . $g['gstin']; }
        if (!empty($g['contact'])) { $fullAddr .= "\nContact: " . $g['contact']; }
        echo '<option value="' . (int)$g['id'] . '" data-address="' . htmlspecialchars($fullAddr, ENT_QUOTES, 'UTF-8') . '">'
           . htmlspecialchars($g['gname']) . '</option>';
    }
}
function render_product_options($all_products) {
    foreach ($all_products as $p) {
        echo '<option value="' . (int)$p['id'] . '">' . htmlspecialchars($p['productName']) . '</option>';
    }
}
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
        .items-table th, .items-table td { vertical-align: middle; }
        .order-session { border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 20px; position: relative; }
        .session-title { font-size: 15px; font-weight: 600; color: #667eea; }
        #printArea { display: none; }
        @media print {
            /* display:none (not visibility:hidden) so the hidden sidebar/menu
               doesn't still reserve layout height — visibility:hidden keeps
               an element's space in the flow, which was leaving a trailing
               blank page whenever the hidden menu was taller than the
               printed content. */
            .app { display: none !important; }
            #printArea { display: block !important; }
        }
        /* Each session only breaks to a new page if it doesn't fit on the
           current one — no forced break-after, so two short sessions share
           a page instead of leaving half a blank page between them. */
        #printArea .order-doc { page-break-inside: avoid; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px dashed #999; }
        #printArea .order-doc:last-child { border-bottom: none; }
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

                                    <div id="sessionsContainer"></div>

                                    <button type="button" id="addSessionBtn" class="btn btn-outline-primary btn-sm">+ Add Another Session</button>

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

<template id="sessionTemplate">
    <div class="order-session">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="session-title">Order Session</span>
            <button type="button" class="btn btn-danger btn-sm remove-session-btn">Remove Session</button>
        </div>

        <div class="form-group">
            <label>Company Profile</label>
            <select class="form-control company-profile-select">
                <option value="">-- Select --</option>
                <?php render_godown_options($all_godowns); ?>
            </select>
        </div>

        <div class="form-group">
            <label>From Address</label>
            <textarea class="form-control from-address" rows="3" placeholder="Select a Company Profile to auto-fill, or type manually"></textarea>
        </div>

        <div class="form-group">
            <label>To Address</label>
            <textarea class="form-control to-address" rows="3" placeholder="Type recipient address..."></textarea>
        </div>

        <label>Order Items</label>
        <table class="table table-bordered items-table">
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
                            <?php render_product_options($all_products); ?>
                        </select>
                    </td>
                    <td><input type="text" class="form-control qty-input" placeholder="Qty"></td>
                    <td><button type="button" class="btn btn-danger btn-sm remove-row">&times;</button></td>
                </tr>
            </tbody>
        </table>
        <button type="button" class="btn btn-secondary btn-sm add-row-btn">+ Add Product</button>
    </div>
</template>

<div id="printArea"></div>

<script>
var sessionsContainer = document.getElementById('sessionsContainer');
var sessionTemplate = document.getElementById('sessionTemplate');

function addRow(session) {
    var tbody = session.querySelector('.items-table tbody');
    if (tbody.rows.length >= 100) {
        alert('Maximum 100 product rows allowed.');
        return;
    }
    var newRow = tbody.rows[0].cloneNode(true);
    newRow.querySelectorAll('select, input').forEach(function(el) { el.value = ''; });
    tbody.appendChild(newRow);
}

function addSession() {
    var frag = sessionTemplate.content.cloneNode(true);
    sessionsContainer.appendChild(frag);
    updateRemoveSessionButtons();
}

function updateRemoveSessionButtons() {
    var sessions = sessionsContainer.querySelectorAll('.order-session');
    sessions.forEach(function(session) {
        session.querySelector('.remove-session-btn').style.display = (sessions.length > 1) ? '' : 'none';
    });
}

// Event delegation — handles clicks/changes from every session, including
// ones added later via "Add Another Session", without re-binding listeners.
sessionsContainer.addEventListener('click', function(e) {
    if (e.target.classList.contains('add-row-btn')) {
        addRow(e.target.closest('.order-session'));
    } else if (e.target.classList.contains('remove-row')) {
        var tbody = e.target.closest('.items-table').querySelector('tbody');
        if (tbody.rows.length > 1) {
            e.target.closest('tr').remove();
        } else {
            alert('Cannot remove all product rows.');
        }
    } else if (e.target.classList.contains('remove-session-btn')) {
        if (sessionsContainer.querySelectorAll('.order-session').length > 1) {
            e.target.closest('.order-session').remove();
            updateRemoveSessionButtons();
        } else {
            alert('Cannot remove the last session.');
        }
    }
});

sessionsContainer.addEventListener('change', function(e) {
    if (e.target.classList.contains('company-profile-select')) {
        var opt = e.target.options[e.target.selectedIndex];
        e.target.closest('.order-session').querySelector('.from-address').value = opt.dataset.address || '';
    }
});

document.getElementById('addSessionBtn').addEventListener('click', addSession);

// Start with exactly one session on page load.
addSession();

document.getElementById('generateBtn').addEventListener('click', function() {
    var printArea = document.getElementById('printArea');
    printArea.innerHTML = '';
    var sessions = sessionsContainer.querySelectorAll('.order-session');
    var docsBuilt = 0;

    try {
    sessions.forEach(function(session, sIndex) {
        var fromVal = session.querySelector('.from-address').value.trim();
        var toVal = session.querySelector('.to-address').value.trim();

        var items = [];
        session.querySelectorAll('.items-table tbody tr').forEach(function(row) {
            var productSel = row.querySelector('.product-select');
            var qty = row.querySelector('.qty-input').value.trim();
            if (!productSel.value || !qty) { return; }
            items.push({ name: productSel.options[productSel.selectedIndex].text, qty: qty });
        });

        if (!fromVal && !toVal && items.length === 0) {
            return; // skip a wholly untouched extra session
        }
        if (!fromVal) { alert('Session ' + (sIndex + 1) + ': please enter a From Address.'); throw new Error('validation'); }
        if (!toVal) { alert('Session ' + (sIndex + 1) + ': please enter a To Address.'); throw new Error('validation'); }
        if (items.length === 0) { alert('Session ' + (sIndex + 1) + ': please add at least one product with a quantity.'); throw new Error('validation'); }

        var doc = document.createElement('div');
        doc.className = 'order-doc';

        var h2 = document.createElement('h2'); h2.textContent = 'Order';
        doc.appendChild(h2);

        var addrTable = document.createElement('table'); addrTable.className = 'addr-table';
        var addrRow = document.createElement('tr');
        var fromTd = document.createElement('td'); fromTd.innerHTML = '<b>From:</b><br/>';
        fromTd.appendChild(document.createTextNode(fromVal));
        var toTd = document.createElement('td'); toTd.innerHTML = '<b>To:</b><br/>';
        toTd.appendChild(document.createTextNode(toVal));
        addrRow.appendChild(fromTd); addrRow.appendChild(toTd);
        addrTable.appendChild(addrRow);
        doc.appendChild(addrTable);

        var docTable = document.createElement('table'); docTable.className = 'doc-table';
        docTable.innerHTML = '<thead><tr><th style="width:10%;">Sl No.</th><th style="width:70%;">Product</th><th style="width:20%;">Qty</th></tr></thead>';
        var tbody = document.createElement('tbody');
        items.forEach(function(item, i) {
            var tr = document.createElement('tr');
            var tdSl = document.createElement('td'); tdSl.textContent = i + 1;
            var tdName = document.createElement('td'); tdName.textContent = item.name;
            var tdQty = document.createElement('td'); tdQty.textContent = item.qty;
            tr.appendChild(tdSl); tr.appendChild(tdName); tr.appendChild(tdQty);
            tbody.appendChild(tr);
        });
        docTable.appendChild(tbody);
        doc.appendChild(docTable);

        printArea.appendChild(doc);
        docsBuilt++;
    });
    } catch (err) {
        return; // a per-session validation alert already fired above
    }

    if (docsBuilt === 0) {
        alert('Please fill in at least one session with a From Address, To Address, and product.');
        return;
    }

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
