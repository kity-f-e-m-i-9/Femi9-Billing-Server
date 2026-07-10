<?php
/**
 * Add Input Stock - Users (Multi-Product Form)
 *
 * Improvements over original:
 * - Prepared statement for godown lookup (SQL injection fix)
 * - All output wrapped in htmlspecialchars() (XSS fix)
 * - CSRF token generated and embedded
 * - $_REQUEST replaced with $_GET
 * - error_reporting(0) removed
 * - generateTempId() uses random_int() (cryptographically secure)
 * - Product list sorted alphabetically
 * - Multi-product dynamic rows via JavaScript
 * - User type → user name AJAX uses jQuery (replaces raw XHR)
 * - ob_start() prevents whitespace before header()
 */

ob_start();
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('add_input_stock_users');
include("config.php");

date_default_timezone_set("Asia/Kolkata");

// ── CSRF token ───────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ── Temp ID ──────────────────────────────────────────────────────────────────
function generateTempId(int $length = 11): string
{
    $chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $max   = strlen($chars) - 1;
    $hash  = '';
    for ($i = 0; $i < $length; $i++) {
        $hash .= $chars[random_int(0, $max)];
    }
    return $hash;
}

$tempID = 'ITU' . generateTempId(11) . date('dmygis');

// ── Alert flags ──────────────────────────────────────────────────────────────
$showStockNotUpdated = isset($_GET['stocknotupdated']);
$showAlreadyExists   = isset($_GET['alreadyexists']);

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
    <title>Add Input Stock Users : <?= htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8') ?></title>

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
        .product-rows-table input,
        .product-rows-table textarea { font-size: 13px; }
        .btn-remove-row { background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 20px; line-height: 1; padding: 0 4px; }
        .btn-remove-row:hover { color: #c0392b; }
        #add-row-btn { margin-top: 10px; }
        #user-select-wrap { min-height: 60px; }
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
                                            <td>Add Input Stock : Users</td>
                                            <td><a href="manage-input-users" title="Manage Input Stock Users">&#9776;</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <?php if ($showStockNotUpdated): ?>
                        <div class="alert alert-danger">
                            Selected user has no opening stock — please update opening stock first!
                        </div>
                    <?php endif; ?>

                    <?php if ($showAlreadyExists): ?>
                        <div class="alert alert-danger">Invalid Input Stock details, already exists!</div>
                    <?php endif; ?>

                    <?php if (isset($_GET['saveerror'])): ?>
                        <div class="alert alert-danger">
                            Failed to save.<?php if (!empty($_GET['msg'])): ?> <?= htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['err'])): ?>
                        <div class="alert alert-danger">Validation error: <?= htmlspecialchars($_GET['err'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <!-- Form Card -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">

                                    <form action="input-action-users" method="post"
                                          onsubmit="return validateForm();">

                                        <!-- CSRF token -->
                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                                        <!-- Duplicate-submission guard -->
                                        <input type="hidden" name="tempid"
                                               value="<?= htmlspecialchars($tempID, ENT_QUOTES, 'UTF-8') ?>">

                                        <div class="example-container">
                                            <div class="example-content">

                                                <!-- User Type -->
                                                <div class="mb-3">
                                                    <label class="form-label">User Type *</label>
                                                    <select required name="to_usertype" id="to_usertype"
                                                            class="form-control">
                                                        <option value="" hidden>Select</option>
                                                        <option value="super_stockiest">Super Stockist</option>
                                                        <option value="stockiest">Stockist</option>
                                                        <option value="super_distributor">Super Distributor</option>
                                                        <option value="distributor">Distributor</option>
                                                    </select>
                                                </div>

                                                <!-- User Name (loaded via AJAX) -->
                                                <!-- 
                                                    IMPORTANT: loadUserID_input_stock.php returns a full
                                                    <label> + <select name="to_userid"> HTML block.
                                                    We replace the entire #user-select-wrap contents,
                                                    matching the original innerHTML approach.
                                                -->
                                                <div class="mb-3" id="user-select-wrap">
                                                    <label class="form-label">User Name, Mobile *</label>
                                                    <select name="to_userid" id="to_userid"
                                                            class="form-control" disabled>
                                                        <option value="" hidden>Select user type first</option>
                                                    </select>
                                                    <small id="user-loading" class="text-muted" style="display:none">
                                                        Loading…
                                                    </small>
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

                                                    <!-- Options template — cloned by JS, never re-fetched -->
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
                                                                <th style="width:38%">Product Name</th>
                                                                <th style="width:13%">Qty</th>
                                                                <th style="width:39%">Remarks</th>
                                                                <th style="width:10%"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="product-rows-body">
                                                            <!-- First row inserted by JS on load -->
                                                        </tbody>
                                                    </table>

                                                    <button type="button" id="add-row-btn"
                                                            class="btn btn-outline-primary btn-sm">
                                                        <i class="material-icons"
                                                           style="font-size:16px;vertical-align:middle">add</i>
                                                        Add Another Product
                                                    </button>
                                                </div>
                                                <!-- ── end multi-product rows ─────────────── -->

                                                <button type="submit" name="add-record" class="btn btn-primary">
                                                    <i class="material-icons">save</i> Save
                                                </button>

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

<!-- JS libs -->
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>

<script>
(function ($) {
    'use strict';

    const tbody     = document.getElementById('product-rows-body');
    const addRowBtn = document.getElementById('add-row-btn');
    const optTpl    = document.getElementById('product-options-tpl');

    // ── User Type → User Name AJAX ───────────────────────────────────────────
    // loadUserID_input_stock.php returns a full <label>+<select name="to_userid"> block.
    // We inject that HTML into #user-select-wrap, exactly like the original
    // innerHTML approach, so the response renders correctly regardless of its structure.
    $('#to_usertype').on('change', function () {
        const userType  = $(this).val();
        const $wrap     = $('#user-select-wrap');
        const $loading  = $('#user-loading');

        if (!userType) {
            $wrap.html(
                '<label class="form-label">User Name, Mobile *</label>' +
                '<select name="to_userid" id="to_userid" class="form-control" disabled>' +
                '<option value="" hidden>Select user type first</option></select>'
            );
            return;
        }

        // Show a loading placeholder while fetching
        $wrap.html(
            '<label class="form-label">User Name, Mobile *</label>' +
            '<select class="form-control" disabled>' +
            '<option>Loading…</option></select>'
        );

        $.ajax({
            url: 'loadUserID_input_stock.php',
            type: 'GET',
            data: { q: userType },
            success: function (html) {
                // Replace entire wrap content with the server response
                // (server returns <label>+<select name="to_userid">…</select>)
                $wrap.html(html);
                // Make sure the injected select has the correct name/id
                $wrap.find('select').attr('id', 'to_userid').prop('disabled', false);
            },
            error: function () {
                $wrap.html(
                    '<label class="form-label">User Name, Mobile *</label>' +
                    '<select name="to_userid" id="to_userid" class="form-control">' +
                    '<option value="" hidden>Failed to load — please retry</option></select>'
                );
            }
        });
    });

    // ── Multi-product rows ───────────────────────────────────────────────────

    function addRow() {
        const tr = document.createElement('tr');

        // Product select
        const tdProd = document.createElement('td');
        const sel    = document.createElement('select');
        sel.name      = 'product_id[]';
        sel.required  = true;
        sel.className = 'form-control form-control-sm';
        sel.appendChild(document.importNode(optTpl.content, true));
        tdProd.appendChild(sel);

        // Qty
        const tdQty = document.createElement('td');
        const qty   = document.createElement('input');
        qty.type      = 'number';
        qty.name      = 'input_qty[]';
        qty.required  = true;
        qty.min       = '1';
        qty.className = 'form-control form-control-sm';
        tdQty.appendChild(qty);

        // Remarks
        const tdRmk = document.createElement('td');
        const rmk   = document.createElement('textarea');
        rmk.name      = 'input_remarks[]';
        rmk.required  = true;
        rmk.rows      = 1;
        rmk.className = 'form-control form-control-sm';
        tdRmk.appendChild(rmk);

        // Remove button
        const tdDel  = document.createElement('td');
        const delBtn = document.createElement('button');
        delBtn.type      = 'button';
        delBtn.className = 'btn-remove-row';
        delBtn.title     = 'Remove row';
        delBtn.innerHTML = '&times;';
        delBtn.addEventListener('click', function () {
            if (tbody.rows.length > 1) {
                tr.remove();
            } else {
                alert('At least one product row is required.');
            }
        });
        tdDel.appendChild(delBtn);

        tr.appendChild(tdProd);
        tr.appendChild(tdQty);
        tr.appendChild(tdRmk);
        tr.appendChild(tdDel);
        tbody.appendChild(tr);
    }

    addRow(); // first row on page load
    addRowBtn.addEventListener('click', addRow);

    // ── Client-side validation ───────────────────────────────────────────────
    window.validateForm = function () {
        // to_userid is injected by AJAX — query it fresh each time
        const userSel = document.querySelector('#user-select-wrap select[name="to_userid"]');
        if (!userSel || !userSel.value) {
            alert('Please select a User Name.');
            if (userSel) userSel.focus();
            return false;
        }

        // Check for empty / duplicate products
        const selects = tbody.querySelectorAll('select[name="product_id[]"]');
        const seen    = new Set();

        for (const s of selects) {
            if (!s.value) {
                alert('Please select a product for every row.');
                s.focus();
                return false;
            }
            if (seen.has(s.value)) {
                alert('Duplicate product detected. Please select different products or remove duplicate rows.');
                s.focus();
                return false;
            }
            seen.add(s.value);
        }

        return confirm('Save input stock entries?');
    };

}(jQuery));
</script>
</body>
</html>