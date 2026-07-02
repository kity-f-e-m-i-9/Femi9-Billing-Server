<?php
/**
 * Add Input Stock - Multi-Product Form
 * 
 * Changes from original:
 * - SQL injection fix: prepared statement for godown lookup
 * - XSS fix: all output wrapped in htmlspecialchars()
 * - CSRF token added (generate + embed in form)
 * - $_REQUEST replaced with $_GET / $_POST as appropriate
 * - error_reporting(0) removed; proper error handling used instead
 * - GeraHash() moved to top, outside form markup
 * - Multi-product dynamic rows via JavaScript
 * - ob_start() to prevent whitespace breaking JSON if this file is ever included
 */

ob_start();
include("checksession.php");
include("config.php");
require_once __DIR__ . "/include/GodownAccess.php";

date_default_timezone_set("Asia/Kolkata");

// ── CSRF token generation ────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ── Temp ID generation (moved out of form body) ──────────────────────────────
function generateTempId(int $length = 6): string
{
    $chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $max   = strlen($chars) - 1;
    $hash  = '';
    for ($i = 0; $i < $length; $i++) {
        $hash .= $chars[random_int(0, $max)];
    }
    return $hash;
}

$tempID = sprintf(
    '%s/%s/%s',
    generateTempId(6),
    date('dmy'),
    date('gis')
);

// ── Godown lookup (prepared statement) ──────────────────────────────────────
$godownResult = null;
$gid = isset($_GET['gid']) ? (int) $_GET['gid'] : 0;

if ($gid > 0 && is_godown_allowed($db_conn, $gid)) {
    $stmtGd = $db_conn->prepare("SELECT * FROM company_godown WHERE id = ?");
    $stmtGd->bind_param('i', $gid);
    $stmtGd->execute();
    $godownResult = $stmtGd->get_result()->fetch_assoc();
    $stmtGd->close();
}

// ── Alert flags ──────────────────────────────────────────────────────────────
$showStockNotUpdated = isset($_GET['stocknotupdated']);
$showAlreadyExists   = isset($_GET['alreadyexists']);

// ── Godown list ──────────────────────────────────────────────────────────────
$godowns = [];
$resGd = $db_conn->query("SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC");
while ($row = $resGd->fetch_assoc()) {
    $godowns[] = $row;
}

// ── Product list ─────────────────────────────────────────────────────────────
$products = [];
$resProd = $db_conn->query("SELECT id, productName FROM products ORDER BY productName ASC");
while ($row = $resProd->fetch_assoc()) {
    $products[] = $row;
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Input Stock : <?= htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">

    <style>
        .product-rows-table { width: 100%; border-collapse: collapse; }
        .product-rows-table th { background: #f0f4ff; padding: 8px 10px; font-size: 13px; color: #333; border: 1px solid #dde3f0; }
        .product-rows-table td { padding: 6px 8px; border: 1px solid #e5e9f2; vertical-align: middle; }
        .product-rows-table select,
        .product-rows-table input { font-size: 13px; }
        .btn-remove-row { background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 18px; line-height: 1; }
        .btn-remove-row:hover { color: #c0392b; }
        #add-row-btn { margin-top: 10px; }
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

                    <!-- Page Header -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td>Add Input Stock</td>
                                            <td><a href="manage-input" title="Manage Input Stock">&#9776;</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <?php if ($showStockNotUpdated && $godownResult): ?>
                        <div class="alert alert-danger">
                            Please update opening stock
                            (<?= htmlspecialchars($godownResult['gname'], ENT_QUOTES, 'UTF-8') ?>) !
                        </div>
                    <?php endif; ?>

                    <?php if ($showAlreadyExists): ?>
                        <div class="alert alert-danger">Invalid Input Stock details, already exists!</div>
                    <?php endif; ?>

                    <?php if (isset($_GET['saveerror'])): ?>
                        <div class="alert alert-danger">Failed to save. Please try again.</div>
                    <?php endif; ?>

                    <?php if (isset($_GET['invalid'])): ?>
                        <div class="alert alert-danger">Invalid input. Please check all fields and try again.</div>
                    <?php endif; ?>

                    <!-- Form Card -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">

                                    <form action="input-action" method="post"
                                          onsubmit="return validateForm();">

                                        <!-- CSRF token -->
                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                                        <!-- Temp ID (duplicate-submission guard) -->
                                        <input type="hidden" name="tempid"
                                               value="<?= htmlspecialchars($tempID, ENT_QUOTES, 'UTF-8') ?>">

                                        <div class="example-container">
                                            <div class="example-content">

                                                <!-- Company / Godown -->
                                                <div class="mb-3">
                                                    <label class="form-label">Company Profile *</label>
                                                    <select required name="godownid" class="form-control">
                                                        <option value="" hidden>Select</option>
                                                        <?php foreach ($godowns as $gd): ?>
                                                            <option value="<?= (int) $gd['id'] ?>">
                                                                <?= htmlspecialchars($gd['gname'], ENT_QUOTES, 'UTF-8') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <!-- Date -->
                                                <div class="mb-3">
                                                    <label class="form-label">Date *</label>
                                                    <input type="date" required name="input_date"
                                                           value="<?= date('Y-m-d') ?>"
                                                           class="form-control">
                                                </div>

                                                <!-- ── Multi-product rows ─────────────────── -->
                                                <div class="mb-3">
                                                    <label class="form-label">Products *</label>

                                                    <!-- Build product options once; JS will clone them -->
                                                    <template id="product-options-tpl">
                                                        <option value="" hidden>Select Product</option>
                                                        <?php foreach ($products as $p): ?>
                                                            <option value="<?= (int) $p['id'] ?>">
                                                                <?= htmlspecialchars($p['productName'], ENT_QUOTES, 'UTF-8') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </template>

                                                    <table class="product-rows-table" id="product-rows-table">
                                                        <thead>
                                                            <tr>
                                                                <th style="width:40%">Product Name</th>
                                                                <th style="width:15%">Qty</th>
                                                                <th style="width:35%">Remarks</th>
                                                                <th style="width:10%"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="product-rows-body">
                                                            <!-- First row inserted by JS on load -->
                                                        </tbody>
                                                    </table>

                                                    <button type="button" id="add-row-btn"
                                                            class="btn btn-outline-primary btn-sm">
                                                        <i class="material-icons" style="font-size:16px;vertical-align:middle">add</i>
                                                        Add Another Product
                                                    </button>
                                                </div>
                                                <!-- ── end multi-product rows ─────────────── -->

                                                <button type="submit" name="add-record"
                                                        class="btn btn-primary">
                                                    <i class="material-icons">save</i> Save
                                                </button>

                                            </div>
                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>
                    </div><!-- /row -->

                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS libs -->
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>

<script>
(function () {
    'use strict';

    const tbody      = document.getElementById('product-rows-body');
    const addRowBtn  = document.getElementById('add-row-btn');
    const optionsTpl = document.getElementById('product-options-tpl');

    /**
     * Build a new product row <tr> and append it to tbody.
     */
    function addRow() {
        const index = tbody.rows.length;          // 0-based row index
        const tr    = document.createElement('tr');
        tr.dataset.index = index;

        // ── Product select ────────────────────────────────────────────────
        const tdProduct = document.createElement('td');
        const sel       = document.createElement('select');
        sel.name        = 'product_id[]';
        sel.required    = true;
        sel.className   = 'form-control form-control-sm';
        // Clone options from template
        sel.appendChild(document.importNode(optionsTpl.content, true));
        tdProduct.appendChild(sel);

        // ── Qty input ─────────────────────────────────────────────────────
        const tdQty  = document.createElement('td');
        const qty    = document.createElement('input');
        qty.type     = 'number';
        qty.name     = 'input_qty[]';
        qty.required = true;
        qty.min      = '1';
        qty.className = 'form-control form-control-sm';
        tdQty.appendChild(qty);

        // ── Remarks textarea ──────────────────────────────────────────────
        const tdRmk  = document.createElement('td');
        const rmk    = document.createElement('textarea');
        rmk.name     = 'input_remarks[]';
        rmk.required = true;
        rmk.rows     = 1;
        rmk.className = 'form-control form-control-sm';
        tdRmk.appendChild(rmk);

        // ── Remove button ─────────────────────────────────────────────────
        const tdDel  = document.createElement('td');
        const delBtn = document.createElement('button');
        delBtn.type      = 'button';
        delBtn.className = 'btn-remove-row';
        delBtn.title     = 'Remove row';
        delBtn.innerHTML = '&times;';
        delBtn.addEventListener('click', function () {
            // Always keep at least one row
            if (tbody.rows.length > 1) {
                tr.remove();
            } else {
                alert('At least one product row is required.');
            }
        });
        tdDel.appendChild(delBtn);

        tr.appendChild(tdProduct);
        tr.appendChild(tdQty);
        tr.appendChild(tdRmk);
        tr.appendChild(tdDel);
        tbody.appendChild(tr);
    }

    // Insert the first row on page load
    addRow();

    // Wire up the "Add Another Product" button
    addRowBtn.addEventListener('click', addRow);

    /**
     * Client-side validation: ensure no duplicate product is selected.
     */
    window.validateForm = function () {
        const selects = tbody.querySelectorAll('select[name="product_id[]"]');
        const seen    = new Set();

        for (const sel of selects) {
            if (!sel.value) {
                alert('Please select a product for every row.');
                sel.focus();
                return false;
            }
            if (seen.has(sel.value)) {
                alert('Duplicate product detected. Please select different products or remove duplicate rows.');
                sel.focus();
                return false;
            }
            seen.add(sel.value);
        }

        return confirm('Save input stock entries?');
    };
}());
</script>
</body>
</html>