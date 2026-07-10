<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
include("config.php");
date_default_timezone_set("Asia/Kolkata");

// ---------------------------------------------------------------
// Cleanup: Delete orphan invoices with total=0 (no line items)
// Using prepared statements to prevent SQL injection
// ---------------------------------------------------------------
$stmt_orphan = $db_conn->prepare(
    "SELECT tempid FROM ot_sales_invoice WHERE total = '0'"
);
$stmt_orphan->execute();
$result_orphan = $stmt_orphan->get_result();

$orphan_tempids = [];
while ($row = $result_orphan->fetch_assoc()) {
    $orphan_tempids[] = $row['tempid'];
}
$stmt_orphan->close();

if (!empty($orphan_tempids)) {
    $placeholders = implode(',', array_fill(0, count($orphan_tempids), '?'));
    $types = str_repeat('s', count($orphan_tempids));

    // Find which orphan tempids have no sales rows
    $stmt_check = $db_conn->prepare(
        "SELECT tempid FROM ot_sales WHERE tempid IN ($placeholders)"
    );
    $stmt_check->bind_param($types, ...$orphan_tempids);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    $has_sales = [];
    while ($row = $result_check->fetch_assoc()) {
        $has_sales[$row['tempid']] = true;
    }
    $stmt_check->close();

    $to_delete = array_filter($orphan_tempids, fn($tid) => !isset($has_sales[$tid]));

    if (!empty($to_delete)) {
        $del_placeholders = implode(',', array_fill(0, count($to_delete), '?'));
        $del_types = str_repeat('s', count($to_delete));
        $stmt_del = $db_conn->prepare(
            "DELETE FROM ot_sales_invoice WHERE tempid IN ($del_placeholders)"
        );
        $stmt_del->bind_param($del_types, ...$to_delete);
        $stmt_del->execute();
        $stmt_del->close();
    }
}

// ---------------------------------------------------------------
// Generate secure temp ID
// ---------------------------------------------------------------
function generateTempId(): string {
    $chars = '123456789';
    $rand3 = '';
    for ($i = 0; $i < 3; $i++) {
        $rand3 .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $date = date('dmy');
    $time = date('gis');
    return $rand3 . 'RTST/' . $date . '/' . $time;
}

$tempid       = generateTempId();
$inv_randum_no = substr($tempid, 0, 3);

// ---------------------------------------------------------------
// Fetch category list for wallet-eligible categories check
// Used in JS to show/hide wallet field
// ---------------------------------------------------------------
$wallet_categories = ['website', 'id concept']; // Add exact category names here
$wallet_cats_json  = json_encode(array_map('strtolower', $wallet_categories));

// ---------------------------------------------------------------
// Fetch product list (once, reuse in table row)
// ---------------------------------------------------------------
$stmt_products = $db_conn->prepare("SELECT id, productName FROM products ORDER BY productName ASC");
$stmt_products->execute();
$all_products = $stmt_products->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_products->close();

// ---------------------------------------------------------------
// Fetch category list based on user type
// ---------------------------------------------------------------
$usertype   = $Result_Log_users_Dtails134['usertype'] ?? '';
$login_user = $_SESSION['LOGIN_USER'] ?? '';
$categories = [];

if ($usertype === 'admin') {
    $stmt_cat = $db_conn->prepare("SELECT cat FROM ot_cat ORDER BY cat ASC");
    $stmt_cat->execute();
    $res_cat = $stmt_cat->get_result();
    while ($row = $res_cat->fetch_assoc()) {
        $categories[] = $row['cat'];
    }
    $stmt_cat->close();
} else {
    $stmt_cat = $db_conn->prepare(
        "SELECT oc.cat FROM admin_log_ot alo
         JOIN ot_cat oc ON oc.id = alo.ot_cat
         WHERE alo.username = ?"
    );
    $stmt_cat->bind_param('s', $login_user);
    $stmt_cat->execute();
    $res_cat = $stmt_cat->get_result();
    while ($row = $res_cat->fetch_assoc()) {
        $categories[] = $row['cat'];
    }
    $stmt_cat->close();
}

// ---------------------------------------------------------------
// Fetch godown list
// ---------------------------------------------------------------
$stmt_godown = $db_conn->prepare("SELECT id, gname FROM company_godown ORDER BY id ASC");
$stmt_godown->execute();
$all_godowns = $stmt_godown->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_godown->close();

// ---------------------------------------------------------------
// Fetch state list
// ---------------------------------------------------------------
$stmt_states = $db_conn->prepare("SELECT id, st_name FROM state ORDER BY st_name ASC");
$stmt_states->execute();
$all_states = $stmt_states->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_states->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add OT Sales : <?php echo htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
    <style>
        /* Wallet field hidden by default */
        #walletAmountGroup { display: none; }
        #grandTotalDisplay {
            font-size: 1.2rem;
            font-weight: 600;
            color: #0d6efd;
            padding: 8px 0;
        }
        #grandTotalDisplay span { color: #198754; }
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
                                            <td>Add OT Sales</td>
                                            <td><a href="ot-sale-view" title="Manage OT Sales">&#9776;</a></td>
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

                                    <?php
                                    // ------- Flash messages (XSS-safe) -------
                                    foreach (['errorMessageOT', 'errorMessage'] as $sessKey):
                                        if (isset($_SESSION[$sessKey])):
                                            $flashMsg = htmlspecialchars($_SESSION[$sessKey], ENT_QUOTES, 'UTF-8');
                                            $isOT     = ($sessKey === 'errorMessageOT');
                                    ?>
                                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                                    <script>
                                        Swal.fire({
                                            icon: 'error',
                                            title: '<?= $isOT ? 'Stock Error' : 'Warning' ?>',
                                            text: '<?= $flashMsg ?>',
                                            confirmButtonText: 'OK'
                                        });
                                    </script>
                                    <?php
                                            unset($_SESSION[$sessKey]);
                                            if ($isOT) unset($_SESSION['sucMessage']);
                                        endif;
                                    endforeach;
                                    ?>

                                    <?php include("validate-scripts.php"); ?>

                                    <form action="ot-sale-action" method="post"
                                          enctype="multipart/form-data"
                                          onsubmit="return confirm('Please confirm submission!');">

                                        <!-- Hidden fields -->
                                        <input type="hidden" name="tempid"      value="<?= htmlspecialchars($tempid, ENT_QUOTES) ?>">
                                        <input type="hidden" name="randumnumber" value="<?= htmlspecialchars($inv_randum_no, ENT_QUOTES) ?>">
                                        <input type="hidden" name="username"    value="<?= htmlspecialchars($login_user, ENT_QUOTES) ?>">
                                        <input type="hidden" name="usertype"    value="<?= htmlspecialchars($usertype, ENT_QUOTES) ?>">
                                        <input type="hidden" name="admin_state_id" value="<?= htmlspecialchars($Config_Admin_State ?? '', ENT_QUOTES) ?>">

                                        <div class="example-container">
                                            <div class="example-content">

                                                <!-- Company / Godown -->
                                                <label class="form-label">Company Profile *</label>
                                                <select required name="godownid" autofocus class="form-control"
                                                        onchange="checkOpeningStock(this.value);">
                                                    <option value="" hidden>Select</option>
                                                    <?php foreach ($all_godowns as $gd): ?>
                                                        <option value="<?= (int)$gd['id'] ?>">
                                                            <?= htmlspecialchars($gd['gname'], ENT_QUOTES) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <br>

                                                <!-- State -->
                                                <label class="form-label">State Name *</label>
                                                <select required name="state_id" class="form-control">
                                                    <option value="" hidden>Select</option>
                                                    <?php foreach ($all_states as $st): ?>
                                                        <option value="<?= (int)$st['id'] ?>">
                                                            <?= htmlspecialchars($st['st_name'], ENT_QUOTES) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <br>

                                                <!-- Date -->
                                                <label class="form-label">Date *</label>
                                                <input type="date" required id="bookingDate" name="date"
                                                       value="<?= date('Y-m-d') ?>" class="form-control">
                                                <br>

                                                <!-- Category — triggers wallet field visibility -->
                                                <label class="form-label">Category *</label>
                                                <select required name="catname" id="catname" class="form-control"
                                                        onchange="handleCategoryChange(this.value);">
                                                    <option value="" hidden>Select</option>
                                                    <?php foreach ($categories as $cat): ?>
                                                        <option value="<?= htmlspecialchars($cat, ENT_QUOTES) ?>">
                                                            <?= htmlspecialchars($cat, ENT_QUOTES) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <br>

                                                <!-- Coupon Code -->
                                                <label class="form-label">Coupon Code (User ID)</label>
                                                <input type="text" name="coupon_code" id="coupon_code"
                                                       onchange="showValidCouponCode(this.value)"
                                                       placeholder="Optional"
                                                       style="text-transform:uppercase;"
                                                       onkeypress="restrictSpecialChars(event)"
                                                       class="form-control"
                                                       maxlength="50">
                                                <div id="PrintCouponcode"></div>
                                                <br>

                                                <!-- Invoice Number -->
                                                <label class="form-label">Invoice Number *</label>
                                                <input type="text" name="inv_number" required
                                                       onkeypress="restrictSpecialChars(event)"
                                                       class="form-control" maxlength="50">
                                                <br>

                                                <!-- Customer Name -->
                                                <label class="form-label">Customer Name *</label>
                                                <input type="text" required name="customer_name"
                                                       onkeypress="restrictSpecialChars(event)"
                                                       class="form-control" maxlength="100">
                                                <br>

                                                <!-- Customer Mobile -->
                                                <label class="form-label">Customer Mobile</label>
                                                <input type="text" name="customer_mobile"
                                                       onchange="showMobileNumber(this.value)"
                                                       onkeypress="restrictnumber(event)"
                                                       pattern="[1-9]{1}[0-9]{9}"
                                                       class="form-control" maxlength="10">
                                                <br>

                                                <!-- Billing Address -->
                                                <label class="form-label">Billing Address *</label>
                                                <textarea name="customer_address" class="form-control"
                                                          onkeypress="restrictSpecialChars(event)"
                                                          required maxlength="500"></textarea>
                                                <br>

                                                <!-- Shipping Address -->
                                                <label class="form-label">Shipping Address *</label>
                                                <textarea name="shipping_address" class="form-control"
                                                          onkeypress="restrictSpecialChars(event)"
                                                          required maxlength="500"></textarea>
                                                <br>

                                                <!-- GST Number -->
                                                <label class="form-label">GST Number</label>
                                                <input type="text" maxlength="15"
                                                       onkeypress="restrictGSTIN(event)"
                                                       name="gst_number" class="form-control">
                                                <br>

                                                <!-- Order Number -->
                                                <label class="form-label">Order Number</label>
                                                <input type="text" name="order_number"
                                                       onkeypress="restrictSpecialChars(event)"
                                                       class="form-control" maxlength="50">
                                                <br>

                                                <!-- Order Date -->
                                                <label class="form-label">Order Date</label>
                                                <input type="date" name="order_date" class="form-control">
                                                <br>

                                                <!-- Ship Date -->
                                                <label class="form-label">Ship Date</label>
                                                <input type="date" name="ship_date" class="form-control">
                                                <br>

                                                <!-- Courier Charges -->
                                                <label class="form-label">Courier Charges (Rs.) *</label>
                                                <input type="number" min="0" step="0.01" id="courier_charges"
                                                       name="courier_charges" required class="form-control"
                                                       oninput="recalculateGrandTotal()">
                                                <br>

                                                <!-- ============================================================
                                                     WALLET AMOUNT — shown only for "website" / "id concept"
                                                     ============================================================ -->
                                                <div id="walletAmountGroup">
                                                    <label class="form-label">
                                                        Wallet Amount
                                                        <small class="text-muted">(will be deducted from grand total)</small>
                                                    </label>
                                                    <input type="number" min="0" step="0.01" id="wallet_amount"
                                                           name="wallet_amount" value="0" class="form-control"
                                                           oninput="recalculateGrandTotal()">
                                                    <br>
                                                </div>

                                                <!-- Live Grand Total display -->
                                                <div id="grandTotalDisplay" class="mb-3" style="display:none;">
                                                    Grand Total: <span id="grandTotalValue">₹0.00</span>
                                                    &nbsp;|&nbsp; Wallet Deduction: <span id="walletDeductionValue" style="color:#dc3545;">- ₹0.00</span>
                                                    &nbsp;|&nbsp; <strong>Net Payable: <span id="netPayableValue">₹0.00</span></strong>
                                                </div>

                                                <!-- Product rows -->
                                                <p>
                                                    <button type="button" class="btn btn-primary btn-burger"
                                                            onclick="addRow('dataTable')">
                                                        <i class="material-icons">add</i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-burger"
                                                            onclick="deleteRow('dataTable')">
                                                        <i class="material-icons">delete_outline</i>
                                                    </button>
                                                </p>

                                                <table id="dataTable" border="0">
                                                    <tr>
                                                        <td><input type="checkbox" name="chk[]"></td>
                                                        <td>
                                                            <select required name="product_id[]" class="form-control">
                                                                <option value="" hidden>Select Product</option>
                                                                <?php foreach ($all_products as $pr): ?>
                                                                    <option value="<?= (int)$pr['id'] ?>">
                                                                        <?= htmlspecialchars($pr['productName'], ENT_QUOTES) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="number" placeholder="Qty" min="0"
                                                                   name="qty[]" class="form-control item-qty"
                                                                   required oninput="recalculateGrandTotal()">
                                                        </td>
                                                        <td>
                                                            <input type="number" placeholder="Rate (Rs.)" min="0" step="0.01"
                                                                   name="rate[]" class="form-control item-rate"
                                                                   required oninput="recalculateGrandTotal()">
                                                        </td>
                                                        <td>
                                                            <input type="number" placeholder="Discount (Rs.)" min="0" step="0.01"
                                                                   name="discount[]" class="form-control item-discount"
                                                                   required oninput="recalculateGrandTotal()">
                                                        </td>
                                                    </tr>
                                                </table>
                                                <br>

                                                <span id="opstock">
                                                    <button type="submit" name="add-record" class="btn btn-primary">
                                                        <i class="material-icons">add</i> Submit
                                                    </button>
                                                </span>

                                            </div>
                                        </div>
                                    </form>

                                </div><!-- card-body -->
                            </div><!-- card -->
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/highlight/highlight.pack.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // ----------------------------------------------------------------
    // Flatpickr date config
    // ----------------------------------------------------------------
    flatpickr("#bookingDate", {
        dateFormat: "Y-m-d",
        maxDate: "today"
    });

    // ----------------------------------------------------------------
    // Categories that should show the wallet amount field
    // Injected safely from PHP via json_encode
    // ----------------------------------------------------------------
    const WALLET_ELIGIBLE_CATS = <?= $wallet_cats_json ?>;

    /**
     * Show/hide wallet field based on selected category.
     * Recalculates grand total whenever category changes.
     */
    function handleCategoryChange(selectedCat) {
        const group     = document.getElementById('walletAmountGroup');
        const walletInp = document.getElementById('wallet_amount');
        const catLower  = selectedCat.trim().toLowerCase();

        if (WALLET_ELIGIBLE_CATS.includes(catLower)) {
            group.style.display = 'block';
        } else {
            group.style.display = 'none';
            walletInp.value = 0; // reset wallet when not applicable
        }
        recalculateGrandTotal();
    }

    // ----------------------------------------------------------------
    // Grand Total Calculation
    // Formula: SUM((qty * rate) - discount) + courier_charges - wallet_amount
    // ----------------------------------------------------------------
    function recalculateGrandTotal() {
        const table    = document.getElementById('dataTable');
        const rows     = table.rows;
        let   subtotal = 0;

        for (let i = 0; i < rows.length; i++) {
            const qtyInput      = rows[i].querySelector('.item-qty');
            const rateInput     = rows[i].querySelector('.item-rate');
            const discountInput = rows[i].querySelector('.item-discount');

            if (!qtyInput || !rateInput || !discountInput) continue;

            const qty      = parseFloat(qtyInput.value)      || 0;
            const rate     = parseFloat(rateInput.value)     || 0;
            const discount = parseFloat(discountInput.value) || 0;

            subtotal += (qty * rate) - discount;
        }

        const courier = parseFloat(document.getElementById('courier_charges')?.value) || 0;
        const grandTotal = subtotal + courier;

        // Wallet deduction — only if visible
        const walletGroup = document.getElementById('walletAmountGroup');
        let   walletAmt   = 0;
        if (walletGroup.style.display !== 'none') {
            walletAmt = parseFloat(document.getElementById('wallet_amount')?.value) || 0;
            // Wallet cannot exceed grand total
            if (walletAmt > grandTotal) {
                walletAmt = grandTotal;
                document.getElementById('wallet_amount').value = grandTotal.toFixed(2);
            }
        }

        const netPayable = grandTotal - walletAmt;

        // Update display
        const display = document.getElementById('grandTotalDisplay');
        display.style.display = 'block';
        document.getElementById('grandTotalValue').textContent     = '₹' + grandTotal.toFixed(2);
        document.getElementById('walletDeductionValue').textContent = '- ₹' + walletAmt.toFixed(2);
        document.getElementById('netPayableValue').textContent      = '₹' + netPayable.toFixed(2);
    }

    // ----------------------------------------------------------------
    // Add / Delete product rows — preserve event listeners via cloneNode
    // ----------------------------------------------------------------
    function addRow(tableID) {
        const table    = document.getElementById(tableID);
        const rowCount = table.rows.length;
        if (rowCount >= 100) {
            alert('Maximum 100 product rows allowed.');
            return;
        }
        const newRow  = table.rows[0].cloneNode(true);
        // Clear values in cloned row
        newRow.querySelectorAll('input').forEach(inp => {
            inp.value   = '';
            inp.checked = false;
        });
        newRow.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
        // Re-bind recalculate
        newRow.querySelectorAll('.item-qty, .item-rate, .item-discount').forEach(inp => {
            inp.addEventListener('input', recalculateGrandTotal);
        });
        table.appendChild(newRow);
    }

    function deleteRow(tableID) {
        const table    = document.getElementById(tableID);
        const rowCount = table.rows.length;
        if (rowCount <= 1) {
            alert('Cannot remove all product rows.');
            return;
        }
        for (let i = rowCount - 1; i >= 0; i--) {
            const chk = table.rows[i].cells[0].querySelector('input[type="checkbox"]');
            if (chk && chk.checked) {
                table.deleteRow(i);
            }
        }
        recalculateGrandTotal();
    }

    // ----------------------------------------------------------------
    // AJAX helpers (using fetch API instead of legacy XMLHttpRequest)
    // ----------------------------------------------------------------
    function checkOpeningStock(godownId) {
        if (!godownId) {
            document.getElementById('opstock').innerHTML = '';
            return;
        }
        fetch('loadopeningstock2.php?q=' + encodeURIComponent(godownId))
            .then(r => r.text())
            .then(html => { document.getElementById('opstock').innerHTML = html; })
            .catch(() => {});
    }

    function showValidCouponCode(code) {
        if (!code) {
            document.getElementById('PrintCouponcode').innerHTML = '';
            return;
        }
        fetch('load_coupon_valid.php?q=' + encodeURIComponent(code))
            .then(r => r.text())
            .then(html => { document.getElementById('PrintCouponcode').innerHTML = html; })
            .catch(() => {});
    }
</script>
</body>
</html>