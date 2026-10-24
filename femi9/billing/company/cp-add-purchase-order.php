<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';
error_reporting(0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

date_default_timezone_set("Asia/Kolkata");
$order_date = date("Y-m-d");
$title = "New CP Purchase Order";

// Only an actual company-side owner/finance login may place an order above
// the CP's available headroom — not a limited "users" sub-account that
// merely has the channel_partner permission flag (requirePermission() above
// already let any of those reach this page). cp-add-purchase-order-action.php
// re-checks this same way server-side; this only controls what the page
// tells the browser so the JS warning/confirm matches what will actually
// happen on submit.
$canExceedHeadroom = in_array($result_LoGuserDtails['usertype'] ?? '', ['admin', 'finance'], true);

cpEnsurePurchaseOrderTables($db_conn);

$cps = $db_conn->query("SELECT id, cp_id, name, company_name FROM channel_partners ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

$selectedCpId = (int) ($_GET['cp_id'] ?? 0);
$cp = null;
if ($selectedCpId > 0) {
    $stmtCp = $db_conn->prepare("SELECT * FROM channel_partners WHERE id = ?");
    $stmtCp->bind_param('i', $selectedCpId);
    $stmtCp->execute();
    $cp = $stmtCp->get_result()->fetch_assoc();
    $stmtCp->close();
    if (!$cp) $selectedCpId = 0;
}

$requestedType = $_GET['type'] ?? null;
$productType = null;
if ($selectedCpId > 0 && ($requestedType === 'napkin' || $requestedType === 'diaper')) {
    $productType = $requestedType;
}

$productList = [];
$headroom = 0.0;
$cpDeliveryAddressParts = [];
if ($productType !== null) {
    $headroom = cpAvailableHeadroom($db_conn, $selectedCpId);

    $resProd = mysqli_query($db_conn, "SELECT id, productName, mrp FROM products WHERE deleted_at IS NULL AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) AND " . tpProductTypeSqlFilter($productType, 'products') . " ORDER BY productName ASC");
    if ($resProd) while ($p = mysqli_fetch_assoc($resProd)) $productList[] = $p;

    $cpDeliveryAddressParts = array_filter([
        $cp['delivery_line1'] ?? '',
        $cp['delivery_line2'] ?? '',
        implode(', ', array_filter([$cp['delivery_city'] ?? '', $cp['delivery_district'] ?? ''])),
        implode(', ', array_filter([$cp['delivery_state'] ?? '', $cp['delivery_country'] ?? ''])),
        !empty($cp['delivery_pincode']) ? 'Pincode: ' . $cp['delivery_pincode'] : '',
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?> : <?php echo $business_name; ?></title>
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
        body { font-family: 'Poppins', sans-serif; }
        /* Scoped to the CP-picker select only — a page-wide rule here would
           also cap #pr_select's select2 (Add Product panel), squeezing it
           out of the "Select Product" row layout territory-partner's and
           channel-partner's own add-purchase-order.php use (full-width,
           stacked label above the dropdown). */
        #cpSelect + .select2-container { width: 100% !important; max-width: 480px; }
        .select2-container--default .select2-selection--single { height: 38px; padding: 4px 10px; border: 1px solid #ced4da; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 28px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }

        .btn-back-orders {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fff; color: #667eea; border: 1px solid #e5e7eb;
            padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 13.5px;
            text-decoration: none; transition: all .2s;
        }
        .btn-back-orders:hover { background: #f8fafc; color: #667eea; border-color: #667eea; }

        .apo-picker-card { background: #fff; border-radius: 12px; padding: 28px; max-width: 560px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .apo-picker-card label { font-size: 12.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; margin-bottom: 8px; display: block; }

        .apo-type-choice { display: flex; gap: 20px; flex-wrap: wrap; max-width: 640px; margin: 30px auto; }
        .apo-type-card {
            flex: 1; min-width: 240px; background: #fff; border: 2px solid #e5e7eb; border-radius: 14px;
            padding: 28px 22px; text-align: center; text-decoration: none; color: #1f2937; transition: all .2s;
        }
        .apo-type-card:hover { border-color: #667eea; box-shadow: 0 4px 16px rgba(102,126,234,.18); transform: translateY(-2px); color: #1f2937; }
        .apo-type-card .material-icons-outlined { font-size: 40px; color: #667eea; margin-bottom: 10px; }
        .apo-type-card .name { font-size: 18px; font-weight: 700; }
        .apo-type-card .desc { font-size: 12.5px; color: #6b7280; margin-top: 4px; }

        .apo-card { background: #fff; border-radius: 12px; padding: 22px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .apo-card-title {
            font-size: 12.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #6b7280;
            margin-bottom: 16px; display: flex; align-items: center; gap: 6px;
        }
        .apo-card-title .material-icons-outlined { font-size: 17px; color: #667eea; }

        .apo-info-row { display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 4px; }
        .apo-info-chip { flex: 1; min-width: 180px; }
        .apo-info-chip label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: #9ca3af; margin-bottom: 3px; }
        .apo-info-chip .value { font-size: 14.5px; font-weight: 600; color: #1f2937; }

        .apo-balance-card {
            background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%); border: 1px solid #a7f3d0;
            border-radius: 12px; padding: 16px 20px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 12px;
        }
        .apo-balance-card .material-icons-outlined { font-size: 30px; color: #10b981; }
        .apo-balance-card .value { font-size: 20px; font-weight: 700; color: #065f46; }
        .apo-balance-card .label { font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: #059669; }
        .apo-balance-card .explainer { font-size: 11.5px; color: #059669; margin-top: 2px; }

        .apo-delivery-default { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 16px; font-size: 13.5px; color: #374151; line-height: 1.6; }
        .apo-delivery-check { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 14px; font-weight: 600; color: #374151; }
        .apo-delivery-check input { width: 16px; height: 16px; }
        .apo-delivery-fields { display: none; }
        .apo-delivery-fields.show { display: block; }

        .apo-add-panel { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px; margin-bottom: 6px; }
        .apo-add-panel .form-label { font-size: 11.5px; font-weight: 600; color: #6b7280; margin-bottom: 4px; }
        @media (max-width: 576px) {
            .apo-add-panel .row.g-2 > .col { flex: 0 0 50%; max-width: 50%; }
            .apo-add-panel .row.g-2 > .col-auto { flex: 0 0 100%; max-width: 100%; margin-top: 4px; }
            .apo-add-panel .row.g-2 > .col-auto #add { width: 100%; }
        }
        #add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: #fff;
            font-weight: 600; border-radius: 8px; transition: all .2s;
        }
        #add:hover, #add:focus { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,.35); color: #fff; }

        .apo-table-wrap { border: 1px solid #f1f5f9; border-radius: 10px; overflow: hidden; margin-top: 18px; }
        .apo-table-wrap table { width: 100%; margin: 0; }
        .apo-table-wrap thead th {
            background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .4px; padding: 10px 14px; border-bottom: 2px solid #e5e7eb;
        }
        .apo-table-wrap tbody td { padding: 10px 14px !important; vertical-align: middle; font-size: 13.5px; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
        .apo-table-wrap tbody tr:last-child td { border-bottom: none; }
        .apo-remove-chip {
            border: none; cursor: pointer; background: #fee2e2; color: #991b1b;
            font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;
        }
        .apo-remove-chip:hover { background: #fecaca; }

        .apo-summary-row {
            display: flex; align-items: center; gap: 18px; flex-wrap: wrap;
            background: #f8fafc; border-radius: 10px; padding: 14px 18px; margin: 18px 0;
            font-size: 14px; color: #374151;
        }
        .apo-summary-row .amt { font-weight: 700; color: #1f2937; }

        .apo-excess-card {
            background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px;
        }
        .apo-excess-card .apo-excess-title { font-weight: 700; color: #92400e; font-size: 14.5px; margin-bottom: 8px; }

        .btn-submit-po {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: #fff;
            font-weight: 600; padding: 11px 26px; border-radius: 10px; font-size: 14.5px; transition: all .2s;
        }
        .btn-submit-po:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(102,126,234,.4); color: #fff; }
    </style>
</head>
<body>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<?php if (isset($_SESSION['errorMessage'])) {
    $em = $_SESSION['errorMessage']; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>Swal.fire({ icon:'error', title:'Warning', text:'<?php echo htmlspecialchars($em, ENT_QUOTES); ?>', confirmButtonText:'OK' });</script>
<?php unset($_SESSION['errorMessage']); } ?>

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
                            <div class="col d-flex align-items-center justify-content-between flex-wrap" style="gap:10px;">
                                <div class="page-description">
                                    <h1 style="margin:0;"><?php echo $title; ?>
                                        <?php if ($productType !== null): [$badgeBg, $badgeFg] = tpProductTypeBadgeColors($productType); ?>
                                        <span style="font-size:13px;font-weight:700;padding:4px 12px;border-radius:20px;background:<?=$badgeBg?>;color:<?=$badgeFg?>;vertical-align:middle;"><?=htmlspecialchars(tpProductTypeLabel($productType))?></span>
                                        <?php endif; ?>
                                    </h1>
                                    <?php if ($productType !== null): ?>
                                    <a href="cp-add-purchase-order.php?cp_id=<?php echo $selectedCpId; ?>" style="font-size:12.5px;color:#6b7280;">Change order type</a>
                                    <?php endif; ?>
                                </div>
                                <a href="cp-today-orders.php" class="btn-back-orders">
                                    <i class="material-icons" style="font-size:17px;">list_alt</i> CP Purchase Orders
                                </a>
                            </div>
                        </div>
                        <br/>

                        <?php if ($selectedCpId < 1): ?>
                        <!-- Step 1: pick a Channel Partner -->
                        <div class="apo-picker-card">
                            <form method="GET" action="">
                                <label>Channel Partner</label>
                                <select name="cp_id" id="cpSelect" class="form-control" onchange="this.form.submit()">
                                    <option value="">Select a Channel Partner…</option>
                                    <?php foreach ($cps as $c): ?>
                                    <option value="<?php echo (int) $c['id']; ?>">
                                        <?php echo htmlspecialchars($c['name'] . ' (' . $c['cp_id'] . ')' . ($c['company_name'] ? ' — ' . $c['company_name'] : '')); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <noscript><button type="submit" class="btn btn-primary mt-2">Continue</button></noscript>
                            </form>
                        </div>

                        <?php elseif ($productType === null): ?>
                        <!-- Step 2: pick a product type -->
                        <p style="color:#6b7280;">
                            For <strong><?php echo htmlspecialchars($cp['name']); ?> (<?php echo htmlspecialchars($cp['cp_id']); ?>)</strong> —
                            <a href="cp-add-purchase-order.php" style="font-size:12.5px;">change Channel Partner</a>
                        </p>
                        <p style="color:#6b7280;margin-top:6px;">What kind of order is this?</p>
                        <div class="apo-type-choice">
                            <a href="?cp_id=<?php echo $selectedCpId; ?>&type=napkin" class="apo-type-card">
                                <i class="material-icons-outlined">inventory_2</i>
                                <div class="name">Napkin</div>
                                <div class="desc">Femi9 Sanitary Napkin products</div>
                            </a>
                            <a href="?cp_id=<?php echo $selectedCpId; ?>&type=diaper" class="apo-type-card">
                                <i class="material-icons-outlined">inventory_2</i>
                                <div class="name">Lumi Diaper</div>
                                <div class="desc">Lumi Baby Diaper products</div>
                            </a>
                        </div>

                        <?php else: ?>
                        <!-- Step 3: build the order -->
                        <form action="cp-add-purchase-order-action.php" method="post" id="uploadForm" onsubmit="return validatePoLines();">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="cp_id" value="<?php echo $selectedCpId; ?>">
                            <input type="hidden" name="product_type" value="<?php echo htmlspecialchars($productType); ?>">

                            <div class="apo-balance-card">
                                <i class="material-icons-outlined">account_balance_wallet</i>
                                <div>
                                    <div class="label">Available Order Headroom</div>
                                    <div class="value">&#8377;<span id="headroomDisplay"><?php echo inr_format($headroom, 2); ?></span></div>
                                    <div class="explainer">Based on this CP's security deposit and current stock on hand. Company can place an order above this if needed — the CP's own portal can't.</div>
                                </div>
                            </div>

                            <div class="apo-card">
                                <div class="apo-card-title"><i class="material-icons-outlined">badge</i>Order Details</div>
                                <div class="apo-info-row">
                                    <div class="apo-info-chip">
                                        <label>Channel Partner</label>
                                        <div class="value"><?php echo htmlspecialchars($cp['name']); ?> (<?php echo htmlspecialchars($cp['cp_id']); ?>)
                                            — <a href="cp-add-purchase-order.php" style="font-size:11.5px;">change CP</a>
                                        </div>
                                    </div>
                                    <div class="apo-info-chip">
                                        <label>Invoice Date</label>
                                        <div class="value"><?php echo date("d-m-Y", strtotime($order_date)); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="apo-card">
                                <div class="apo-card-title"><i class="material-icons-outlined">local_shipping</i>Delivery Address</div>

                                <label class="apo-delivery-check">
                                    <input type="checkbox" id="useDefaultDeliveryAddress" name="use_default_delivery_address" value="1" checked onchange="toggleDeliveryFields()">
                                    Use CP's existing delivery address
                                </label>

                                <div id="defaultDeliveryPreview" class="apo-delivery-default">
                                    <?php if (!empty($cpDeliveryAddressParts)): ?>
                                        <?php echo implode('<br/>', array_map('htmlspecialchars', $cpDeliveryAddressParts)); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No delivery address on file. Uncheck above to enter one.</span>
                                    <?php endif; ?>
                                </div>

                                <div id="customDeliveryFields" class="apo-delivery-fields">
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Address Line 1</label>
                                            <input type="text" name="custom_delivery_line1" id="custom_delivery_line1" class="form-control" placeholder="Address Line 1">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Address Line 2</label>
                                            <input type="text" name="custom_delivery_line2" class="form-control" placeholder="Address Line 2">
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-3">
                                            <label class="form-label">City</label>
                                            <input type="text" name="custom_delivery_city" class="form-control" placeholder="City">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">District</label>
                                            <input type="text" name="custom_delivery_district" class="form-control" placeholder="District">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">State</label>
                                            <input type="text" name="custom_delivery_state" class="form-control" placeholder="State">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Pincode</label>
                                            <input type="text" name="custom_delivery_pincode" class="form-control" placeholder="Pincode">
                                        </div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Country</label>
                                            <input type="text" name="custom_delivery_country" class="form-control" placeholder="Country" value="India">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($productList)): ?>
                            <div class="alert alert-warning">No products available to order.</div>
                            <?php else: ?>
                            <div class="apo-card">
                                <div class="apo-card-title"><i class="material-icons-outlined">add_shopping_cart</i>Add Product</div>

                                <div class="apo-add-panel">
                                    <label class="form-label">Select Product</label>
                                    <select class="form-control mb-2" id="pr_select" onchange="showPoPrice(this.value)">
                                        <option value=""></option>
                                        <?php foreach ($productList as $p): ?>
                                        <option value="<?php echo htmlspecialchars((string)($p['id']), ENT_QUOTES, 'ISO-8859-1', false); ?>" data-price="<?php echo htmlspecialchars($p['mrp']); ?>"><?php echo htmlspecialchars($p['productName']); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <div class="row g-2 align-items-end">
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
                                            <button type="button" class="btn" id="add" onclick="addPoLine()"><i class="material-icons" style="font-size:16px;vertical-align:middle;">add</i> Add</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="apo-table-wrap">
                                <table class="table table-bordered mb-0">
                                    <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Price</th><th>Disc</th><th>Total</th><th></th></tr></thead>
                                    <tbody id="poItemsBody">
                                        <tr id="poItemsEmptyRow"><td colspan="7" class="text-center text-muted">No products added yet.</td></tr>
                                    </tbody>
                                </table>
                                </div>
                                <div id="hiddenInputsHolder"></div>

                                <div class="apo-summary-row">
                                    <span>Order Total: <span class="amt" id="poGrandTotal">&#8377;0.00</span></span>
                                    <span style="color:#d1d5db;">|</span>
                                    <span>Available Order Headroom: <span class="amt">&#8377;<span id="headroomDisplay2"><?php echo inr_format($headroom, 2); ?></span></span></span>
                                </div>
                            </div>

                            <div id="poExcessWarning" class="apo-excess-card" style="display:none;">
                                <div class="apo-excess-title" id="poExcessTitle">Order total is &#8377;<span id="poExcessAmount">0.00</span> above this CP's available headroom.</div>
                            </div>

                            <button type="submit" name="submit_po" id="poSubmitBtn" onclick="return confirmCreatePo();" class="btn-submit-po">
                                <i class="material-icons" style="vertical-align:middle;font-size:18px;">add</i> Create Purchase Order
                            </button>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?>

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
    $(document).ready(function() {
        if ($('#cpSelect').length) { $('#cpSelect').select2({ placeholder: 'Search by name or CP ID…', allowClear: false }); }
        if ($('#pr_select').length) { $('#pr_select').select2({ placeholder: 'Search product…', allowClear: true, width: '100%' }); }
    });

    function toggleDeliveryFields() {
        var useDefault = document.getElementById('useDefaultDeliveryAddress').checked;
        document.getElementById('defaultDeliveryPreview').style.display = useDefault ? '' : 'none';
        document.getElementById('customDeliveryFields').classList.toggle('show', !useDefault);
    }

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

        $(sel).val('').trigger('change');
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
            tbody.innerHTML = '<tr id="poItemsEmptyRow"><td colspan="7" class="text-center text-muted">No products added yet.</td></tr>';
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
                    '<td><strong>₹' + netTotal + '</strong></td>' +
                    '<td><button type="button" class="apo-remove-chip" onclick="removePoLine(' + idx + ')">Remove</button></td>';
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

        updatePoSummary();
    }

    function poGrandTotal() {
        var total = 0;
        poLines.forEach(function(l) {
            total += (l.qty * l.price) - l.discAmt;
        });
        return total;
    }

    var headroom = <?php echo json_encode($headroom); ?>;
    // Only true for an admin/finance login (see $canExceedHeadroom in PHP
    // above) — a "users" sub-account with just the channel_partner
    // permission still hits the hard cap, same as the CP's own order form.
    var canExceedHeadroom = <?php echo json_encode($canExceedHeadroom); ?>;
    function updatePoSummary() {
        var total = poGrandTotal();
        document.getElementById('poGrandTotal').textContent = '₹' + total.toFixed(2);
        var warning = document.getElementById('poExcessWarning');
        var excess = total - headroom;
        if (excess > 0.001) {
            document.getElementById('poExcessAmount').textContent = excess.toFixed(2);
            document.getElementById('poExcessTitle').innerHTML = canExceedHeadroom
                ? 'Order total is &#8377;' + excess.toFixed(2) + ' above this CP\'s available headroom. You can still create it — confirm this is intentional.'
                : 'Order total is &#8377;' + excess.toFixed(2) + ' above this CP\'s available headroom. Remove items before submitting.';
            warning.style.display = '';
        } else {
            warning.style.display = 'none';
        }
    }

    $(function() {
        toggleDeliveryFields();
        updatePoSummary();
    });

    function validatePoLines() {
        if (poLines.length === 0) {
            alert('Add at least one product before submitting.');
            return false;
        }

        if (!document.getElementById('useDefaultDeliveryAddress').checked &&
            !document.getElementById('custom_delivery_line1').value.trim()) {
            alert('Enter a delivery address, or check "Use CP\'s existing delivery address".');
            return false;
        }

        // Only an admin/finance login may go over headroom — everyone else
        // hits the same hard cap the CP's own order form enforces, re-checked
        // server-side in cp-add-purchase-order-action.php either way.
        if (!canExceedHeadroom) {
            var total = poGrandTotal();
            if (total - headroom > 0.001) {
                alert('Order total exceeds this CP\'s available headroom by ₹' + (total - headroom).toFixed(2) + '. Remove items before submitting.');
                return false;
            }
        }
        return true;
    }

    function confirmCreatePo() {
        var total = poGrandTotal();
        var excess = total - headroom;
        var msg = 'Create this purchase order for <?php echo htmlspecialchars(addslashes($cp['name']), ENT_QUOTES); ?>? It will go into the Waiting queue for approval.';
        if (excess > 0.001 && canExceedHeadroom) {
            msg = 'This order is ₹' + excess.toFixed(2) + ' above the CP\'s available headroom.\n\n' + msg;
        }
        return confirm(msg);
    }
    </script>
</body>
</html>
