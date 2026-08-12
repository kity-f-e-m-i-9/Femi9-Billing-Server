<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpApproverContext.php';
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$order_date = date("Y-m-d");
$title      = "Purchase Order";

// Full company product catalog — this is a stock replenishment request to the
// company, not limited to what the TP already holds (unlike Field Order).
$productList = [];
$resProd = mysqli_query($db_conn, "SELECT id, productName, stockist_price FROM products WHERE deleted_at IS NULL AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY productName ASC");
if ($resProd) while ($p = mysqli_fetch_assoc($resProd)) $productList[] = $p;

// If this TP is assigned to a Super Stockist, they get a choice of approver
// per order — otherwise everything routes to Company exactly as before, and
// $assignedSs stays null so the selector never renders.
$assignedSs = tpGetAssignedSs($db_conn, (int)$Login_user_IDvl);

// Available advance balance and reserved-by-waiting-orders amount, computed
// per approver pool — reused as-is (same query, just filtered by approver)
// so the existing single-pool math for TPs with no SS assignment is
// byte-for-byte unchanged.
function tpApoBalanceFor(mysqli $db_conn, int $tpId, array $approver): array {
    $balStmt = mysqli_prepare($db_conn,
        "SELECT COALESCE(SUM(balance_amount), 0) AS bal
         FROM tp_advance_payments
         WHERE territory_partner_id = ? AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL
           AND approver_type = ? AND approver_ss_id <=> ?"
    );
    mysqli_stmt_bind_param($balStmt, "isi", $tpId, $approver['type'], $approver['ss_id']);
    mysqli_stmt_execute($balStmt);
    $advBalance = (float)(mysqli_stmt_get_result($balStmt)->fetch_assoc()['bal'] ?? 0);
    mysqli_stmt_close($balStmt);

    // Orders still "waiting" (not yet invoiced/fulfilled, not cancelled) already
    // have their advance-covered portion (order total minus any excess covered
    // by uploaded payment proof) implicitly earmarked, even though
    // tp_advance_payments.balance_amount is only actually decremented once the
    // order is fulfilled. Without subtracting this, a TP could place several
    // pending orders that each look "within budget" individually while
    // cumulatively over-committing the real balance. Scoped to the same
    // approver pool the order itself was routed to.
    $reservedStmt = mysqli_prepare($db_conn,
        "SELECT COALESCE(SUM(poi.total - po.excess_amount), 0) AS reserved
         FROM tp_purchase_orders po
         JOIN (SELECT po_id, SUM(amount) AS total FROM tp_purchase_order_items GROUP BY po_id) poi
           ON poi.po_id = po.id
         WHERE po.territory_partner_id = ? AND po.status = 'waiting'
           AND po.approver_type = ? AND po.approver_ss_id <=> ?"
    );
    mysqli_stmt_bind_param($reservedStmt, "isi", $tpId, $approver['type'], $approver['ss_id']);
    mysqli_stmt_execute($reservedStmt);
    $reservedAmount = (float)(mysqli_stmt_get_result($reservedStmt)->fetch_assoc()['reserved'] ?? 0);
    mysqli_stmt_close($reservedStmt);

    return [max(0, round($advBalance - $reservedAmount, 2))];
}

[$advBalanceCompany] = tpApoBalanceFor($db_conn, (int)$Login_user_IDvl, ['type' => 'company', 'ss_id' => null]);
$advBalanceSs = null;
if ($assignedSs !== null) {
    [$advBalanceSs] = tpApoBalanceFor($db_conn, (int)$Login_user_IDvl, ['type' => 'ss', 'ss_id' => $assignedSs['id']]);
}

// Default selection and displayed balance — Company unless the TP has an SS
// assignment, matching "routes to Company exactly as today" for TPs without one.
$advBalance = $advBalanceCompany;

// Self-migrating: ensure used_for_po_id exists so an already-consumed
// submission (one that already unlocked a different order) can be told
// apart from one still available to cover this order's excess.
$_usedForPoCol = $db_conn->query("SHOW COLUMNS FROM tp_advance_payment_submissions LIKE 'used_for_po_id'");
if ($_usedForPoCol && $_usedForPoCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_advance_payment_submissions ADD COLUMN used_for_po_id INT UNSIGNED NULL AFTER advance_payment_id");
}

// Advance-payment submission(s) made specifically to cover this order's
// excess. A TP is redirected to add-advance-payment.php when their order
// exceeds the available balance, then bounced back here — so a submission
// still 'pending_review' AND not yet claimed by another order
// (used_for_po_id IS NULL) means the excess is covered and the PO can go
// ahead; company reviews the actual payment before the order is invoiced,
// same checkpoint the old inline screenshot upload used to provide.
//
// Deliberately excludes status='accepted': once company accepts a
// submission it becomes a real row in tp_advance_payments (via
// advance_payment_id) and its amount is already inside $advBalance above —
// counting it again here as a separate "still covering the excess" pool
// double-counts the same money and can make an order look covered when the
// balance was never actually enough (e.g. an old accepted ₹10,000
// submission, whose money is already spent as part of the visible balance,
// otherwise kept "covering" every future unrelated order indefinitely).
function tpApoEligibleSubmissionFor(mysqli $db_conn, int $tpId, array $approver): array {
    $advSubStmt = mysqli_prepare($db_conn,
        "SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS cnt
         FROM tp_advance_payment_submissions
         WHERE territory_partner_id = ? AND status = 'pending_review' AND used_for_po_id IS NULL
           AND approver_type = ? AND approver_ss_id <=> ?"
    );
    mysqli_stmt_bind_param($advSubStmt, "isi", $tpId, $approver['type'], $approver['ss_id']);
    mysqli_stmt_execute($advSubStmt);
    $advSubRow = mysqli_stmt_get_result($advSubStmt)->fetch_assoc();
    mysqli_stmt_close($advSubStmt);
    return [(float)($advSubRow['total'] ?? 0), (int)($advSubRow['cnt'] ?? 0) > 0];
}

[$eligibleAdvanceSubmissionTotalCompany, $hasEligibleAdvanceSubmissionCompany] =
    tpApoEligibleSubmissionFor($db_conn, (int)$Login_user_IDvl, ['type' => 'company', 'ss_id' => null]);
$eligibleAdvanceSubmissionTotalSs = 0.0;
$hasEligibleAdvanceSubmissionSs = false;
if ($assignedSs !== null) {
    [$eligibleAdvanceSubmissionTotalSs, $hasEligibleAdvanceSubmissionSs] =
        tpApoEligibleSubmissionFor($db_conn, (int)$Login_user_IDvl, ['type' => 'ss', 'ss_id' => $assignedSs['id']]);
}
$eligibleAdvanceSubmissionTotal = $eligibleAdvanceSubmissionTotalCompany;
$hasEligibleAdvanceSubmission = $hasEligibleAdvanceSubmissionCompany;

// Restore an in-progress cart/delivery draft saved right before the TP was
// sent to add-advance-payment.php (see stash-po-draft.php) — cleared once
// read so it doesn't resurface on unrelated future visits.
$poDraft = $_SESSION['po_draft_' . (int)$Login_user_IDvl] ?? null;
unset($_SESSION['po_draft_' . (int)$Login_user_IDvl]);

// TP's own registered delivery address — shown as the default when the
// "use existing delivery address" checkbox is left checked.
$tpDeliveryStmt = mysqli_prepare($db_conn,
    "SELECT delivery_line1, delivery_line2, delivery_city, delivery_district, delivery_state, delivery_country, delivery_pincode
     FROM territory_partners WHERE id = ?"
);
mysqli_stmt_bind_param($tpDeliveryStmt, "i", $Login_user_IDvl);
mysqli_stmt_execute($tpDeliveryStmt);
$tpDeliveryAddress = mysqli_stmt_get_result($tpDeliveryStmt)->fetch_assoc() ?: [];
mysqli_stmt_close($tpDeliveryStmt);
$tpDeliveryAddressParts = array_filter([
    $tpDeliveryAddress['delivery_line1'] ?? '',
    $tpDeliveryAddress['delivery_line2'] ?? '',
    implode(', ', array_filter([$tpDeliveryAddress['delivery_city'] ?? '', $tpDeliveryAddress['delivery_district'] ?? ''])),
    implode(', ', array_filter([$tpDeliveryAddress['delivery_state'] ?? '', $tpDeliveryAddress['delivery_country'] ?? ''])),
    !empty($tpDeliveryAddress['delivery_pincode']) ? 'Pincode: ' . $tpDeliveryAddress['delivery_pincode'] : '',
]);
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
        body { font-family: 'Poppins', sans-serif; }

        .btn-back-orders {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fff; color: #667eea; border: 1px solid #e5e7eb;
            padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 13.5px;
            text-decoration: none; transition: all .2s;
        }
        .btn-back-orders:hover { background: #f8fafc; color: #667eea; border-color: #667eea; }

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

        .apo-delivery-default { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 16px; font-size: 13.5px; color: #374151; line-height: 1.6; }
        .apo-delivery-check { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 14px; font-weight: 600; color: #374151; }
        .apo-delivery-check input { width: 16px; height: 16px; }
        .apo-delivery-fields { display: none; }
        .apo-delivery-fields.show { display: block; }

        .apo-add-panel { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px; margin-bottom: 6px; }
        .apo-add-panel .form-label { font-size: 11.5px; font-weight: 600; color: #6b7280; margin-bottom: 4px; }
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
        .apo-table-wrap tbody td { padding: 5px !important; padding: 10px 14px !important; vertical-align: middle; font-size: 13.5px; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
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
        .apo-excess-card .apo-excess-desc { font-size: 12.5px; color: #78716c; margin-bottom: 10px; }
        .apo-progress-track { background: #fde68a; border-radius: 20px; height: 8px; overflow: hidden; margin: 8px 0 14px; }
        .apo-progress-fill { background: #10b981; height: 100%; border-radius: 20px; transition: width .3s; }

        .apo-screenshot-card {
            background: #fff; border: 1px solid #f1f5f9; border-radius: 10px; padding: 12px 14px;
            display: flex; align-items: center; gap: 12px; margin-bottom: 10px;
        }
        .apo-screenshot-card .thumb { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; border: 1px solid #e5e7eb; flex-shrink: 0; }
        .apo-screenshot-card .thumb-placeholder {
            width: 48px; height: 48px; border-radius: 8px; background: #f3f4f6; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; color: #9ca3af;
        }
        .apo-screenshot-card .details { flex: 1; min-width: 0; font-size: 12.5px; color: #4b5563; }
        .apo-status-pill { padding: 3px 10px; border-radius: 20px; font-size: 10.5px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; }
        .apo-status-pill.accepted { background: #d1fae5; color: #065f46; }
        .apo-status-pill.rejected { background: #fee2e2; color: #991b1b; }
        .apo-status-pill.pending  { background: #fef3c7; color: #92400e; }

        .apo-upload-row { background: #fff; border: 1px dashed #d1d5db; border-radius: 10px; padding: 14px; }

        .btn-submit-po {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: #fff;
            font-weight: 600; padding: 11px 26px; border-radius: 10px; font-size: 14.5px; transition: all .2s;
        }
        .btn-submit-po:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(102,126,234,.4); color: #fff; }
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
                            <div class="col d-flex align-items-center justify-content-between flex-wrap" style="gap:10px;">
                                <div class="page-description">
                                    <h1 style="margin:0;"><?php echo $title;?></h1>
                                </div>
                                <a href="manage-purchase-orders.php" class="btn-back-orders">
                                    <i class="material-icons" style="font-size:17px;">list_alt</i> My Purchase Orders
                                </a>
                            </div>
                        </div>
                        <br/>

                        <form action="purchase-order-action.php" method="post" id="uploadForm" onsubmit="return validatePoLines();">
                            <input type="hidden" id="advBalanceVal" value="<?=$advBalance?>">
                            <input type="hidden" name="approver_type" id="approver_type_input" value="company">

                            <?php if ($assignedSs !== null): ?>
                            <div class="apo-card">
                                <div class="apo-card-title"><i class="material-icons-outlined">alt_route</i>Submit To</div>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="d-flex align-items-center gap-2" style="border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;cursor:pointer;">
                                            <input type="radio" name="approver_choice" value="company" checked onchange="onApproverChange()"> Company
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="d-flex align-items-center gap-2" style="border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;cursor:pointer;">
                                            <input type="radio" name="approver_choice" value="ss" onchange="onApproverChange()"> <?=htmlspecialchars($assignedSs['name'])?> (Super Stockist)
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="apo-balance-card">
                                <i class="material-icons-outlined">account_balance_wallet</i>
                                <div>
                                    <div class="label">Available Advance Balance</div>
                                    <div class="value">&#8377;<span id="advBalanceDisplay"><?=inr_format($advBalance, 2)?></span></div>
                                </div>
                            </div>

                            <div class="apo-card">
                                <div class="apo-card-title"><i class="material-icons-outlined">badge</i>Order Details</div>
                                <div class="apo-info-row">
                                    <div class="apo-info-chip">
                                        <label>Territory Partner</label>
                                        <div class="value"><?=htmlspecialchars($Login_user_name)?></div>
                                    </div>
                                    <div class="apo-info-chip">
                                        <label>Invoice Date</label>
                                        <div class="value"><?=date("d-m-Y", strtotime($order_date))?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="apo-card">
                                <div class="apo-card-title"><i class="material-icons-outlined">local_shipping</i>Delivery Address</div>

                                <label class="apo-delivery-check">
                                    <input type="checkbox" id="useDefaultDeliveryAddress" name="use_default_delivery_address" value="1" checked onchange="toggleDeliveryFields()">
                                    Use existing delivery address
                                </label>

                                <div id="defaultDeliveryPreview" class="apo-delivery-default">
                                    <?php if (!empty($tpDeliveryAddressParts)): ?>
                                        <?=implode('<br/>', array_map('htmlspecialchars', $tpDeliveryAddressParts))?>
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
                                        <option value="<?=$p['id']?>" data-price="<?=htmlspecialchars($p['stockist_price'])?>"><?=htmlspecialchars($p['productName'])?></option>
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
                                    <span>Available Advance Balance: <span class="amt">&#8377;<span id="advBalanceDisplay2"><?=inr_format($advBalance, 2)?></span></span></span>
                                </div>
                            </div>

                            <div id="poExcessWarning" class="apo-excess-card" style="display:none;">
                                <div class="apo-excess-title">Your order total exceeds your available advance balance by &#8377;<span id="poExcessAmount">0.00</span></div>
                                <div class="apo-excess-desc">
                                    Submit an advance payment for review to cover the difference. Your cart and delivery details will be
                                    saved and restored automatically when you come back to finish this order.
                                </div>

                                <div id="poAdvanceCoveredNote" style="display:none;font-size:13px;color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:10px 14px;margin-bottom:12px;">
                                    <i class="material-icons-outlined" style="font-size:16px;vertical-align:middle;">check_circle</i>
                                    An advance payment of &#8377;<span id="poAdvanceCoveredAmt">0.00</span> is already submitted and awaiting/passed company review —
                                    you can submit this order now; the payment will be verified before invoicing.
                                </div>

                                <button type="button" class="btn-submit-po" id="poGoToAdvanceBtn" onclick="goToAdvancePayment()">
                                    <i class="material-icons-outlined" style="vertical-align:middle;font-size:18px;">account_balance_wallet</i>
                                    Submit Advance Payment for &#8377;<span id="poExcessAmount2">0.00</span>
                                </button>
                            </div>

                            <button type="submit" name="submit_po" id="poSubmitBtn" onclick="return confirm('Submit this purchase order?');" class="btn-submit-po">
                                <i class="material-icons" style="vertical-align:middle;font-size:18px;">add</i> Submit Purchase Order
                            </button>
                            <?php endif; ?>
                        </form>

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
    <script src="https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js"></script>

    <script>
    $(document).ready(function() {
        $('#pr_select').select2({ width: '100%', placeholder: 'Select Product' });
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

    // Whether an advance-payment submission already exists (pending review
    // or accepted) that can cover the excess — set server-side by checking
    // tp_advance_payment_submissions, refreshed on every page load including
    // the redirect back from add-advance-payment.php. Both pools are sent
    // down so the radio toggle can swap between them without a reload —
    // company is the default/fallback and the only pool that exists at all
    // when this TP has no SS assignment.
    var advBalanceByApprover = {
        company: <?=json_encode($advBalanceCompany)?>,
        ss: <?=json_encode($advBalanceSs)?>
    };
    var eligibleSubmissionByApprover = {
        company: { has: <?=json_encode($hasEligibleAdvanceSubmissionCompany)?>, total: <?=json_encode($eligibleAdvanceSubmissionTotalCompany)?> },
        ss: { has: <?=json_encode($hasEligibleAdvanceSubmissionSs)?>, total: <?=json_encode($eligibleAdvanceSubmissionTotalSs)?> }
    };
    var hasEligibleAdvanceSubmission = eligibleSubmissionByApprover.company.has;
    var eligibleAdvanceSubmissionTotal = eligibleSubmissionByApprover.company.total;

    function onApproverChange() {
        var choice = document.querySelector('input[name="approver_choice"]:checked');
        var approver = choice ? choice.value : 'company';
        document.getElementById('approver_type_input').value = approver;

        var bal = advBalanceByApprover[approver];
        if (bal === null || bal === undefined) bal = 0;
        document.getElementById('advBalanceVal').value = bal;
        document.getElementById('advBalanceDisplay').textContent = bal.toFixed(2);
        document.getElementById('advBalanceDisplay2').textContent = bal.toFixed(2);

        hasEligibleAdvanceSubmission = eligibleSubmissionByApprover[approver].has;
        eligibleAdvanceSubmissionTotal = eligibleSubmissionByApprover[approver].total;

        updatePoSummary();
    }

    function poGrandTotal() {
        var total = 0;
        poLines.forEach(function(l) {
            total += (l.qty * l.price) - l.discAmt;
        });
        return total;
    }

    function updatePoSummary() {
        var advBalance = parseFloat(document.getElementById('advBalanceVal').value) || 0;
        var total = poGrandTotal();
        var excess = total - advBalance;

        document.getElementById('poGrandTotal').textContent = '₹' + total.toFixed(2);

        var warning = document.getElementById('poExcessWarning');
        if (excess > 0.001) {
            document.getElementById('poExcessAmount').textContent = excess.toFixed(2);
            document.getElementById('poExcessAmount2').textContent = excess.toFixed(2);
            warning.style.display = '';

            var coveredNote = document.getElementById('poAdvanceCoveredNote');
            var goToAdvanceBtn = document.getElementById('poGoToAdvanceBtn');
            if (hasEligibleAdvanceSubmission) {
                document.getElementById('poAdvanceCoveredAmt').textContent = eligibleAdvanceSubmissionTotal.toFixed(2);
                coveredNote.style.display = '';
                goToAdvanceBtn.style.display = 'none';
            } else {
                coveredNote.style.display = 'none';
                goToAdvanceBtn.style.display = '';
            }
        } else {
            warning.style.display = 'none';
        }
    }

    // Saves the current cart + delivery details into the session, then sends
    // the TP to submit an advance payment for the excess. add-advance-payment.php
    // is told to bounce back here (return_to=po) once that submission is made.
    function goToAdvancePayment() {
        if (poLines.length === 0) {
            alert('Add at least one product before submitting an advance payment.');
            return;
        }
        var total = poGrandTotal();
        var advBalance = parseFloat(document.getElementById('advBalanceVal').value) || 0;
        var excess = Math.max(0, total - advBalance);

        var useDefault = document.getElementById('useDefaultDeliveryAddress').checked;
        var payload = {
            lines: poLines,
            use_default_delivery_address: useDefault,
            custom_delivery_line1: document.getElementById('custom_delivery_line1') ? document.getElementById('custom_delivery_line1').value : '',
            custom_delivery_line2: document.querySelector('[name="custom_delivery_line2"]') ? document.querySelector('[name="custom_delivery_line2"]').value : '',
            custom_delivery_city: document.querySelector('[name="custom_delivery_city"]') ? document.querySelector('[name="custom_delivery_city"]').value : '',
            custom_delivery_district: document.querySelector('[name="custom_delivery_district"]') ? document.querySelector('[name="custom_delivery_district"]').value : '',
            custom_delivery_state: document.querySelector('[name="custom_delivery_state"]') ? document.querySelector('[name="custom_delivery_state"]').value : '',
            custom_delivery_country: document.querySelector('[name="custom_delivery_country"]') ? document.querySelector('[name="custom_delivery_country"]').value : '',
            custom_delivery_pincode: document.querySelector('[name="custom_delivery_pincode"]') ? document.querySelector('[name="custom_delivery_pincode"]').value : ''
        };

        var btn = document.getElementById('poGoToAdvanceBtn');
        btn.disabled = true;

        fetch('stash-po-draft.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    alert(data.message || 'Could not save your order. Please try again.');
                    btn.disabled = false;
                    return;
                }
                var approver = document.getElementById('approver_type_input').value || 'company';
                window.location.href = 'add-advance-payment.php?return_to=po&amount=' + encodeURIComponent(excess.toFixed(2)) + '&approver=' + encodeURIComponent(approver);
            })
            .catch(function() {
                alert('Could not reach the server — please try again.');
                btn.disabled = false;
            });
    }

    $(function() {
        if ($('#pr_select').length) {
            $('#pr_select').select2({ placeholder: 'Search product…', allowClear: true, width: '100%' });
        }
        toggleDeliveryFields();

        // Restore a draft saved before being sent to add-advance-payment.php.
        var draft = <?=json_encode($poDraft)?>;
        if (draft && draft.lines && draft.lines.length) {
            poLines = draft.lines;
            renderPoLines();

            if (!draft.use_default_delivery_address) {
                document.getElementById('useDefaultDeliveryAddress').checked = false;
                toggleDeliveryFields();
                var fieldMap = {
                    custom_delivery_line1: draft.custom_delivery_line1,
                    custom_delivery_line2: draft.custom_delivery_line2,
                    custom_delivery_city: draft.custom_delivery_city,
                    custom_delivery_district: draft.custom_delivery_district,
                    custom_delivery_state: draft.custom_delivery_state,
                    custom_delivery_country: draft.custom_delivery_country,
                    custom_delivery_pincode: draft.custom_delivery_pincode
                };
                Object.keys(fieldMap).forEach(function(name) {
                    var el = document.querySelector('[name="' + name + '"]');
                    if (el && fieldMap[name]) el.value = fieldMap[name];
                });
            }
        } else {
            updatePoSummary();
        }
    });

    function validatePoLines() {
        if (poLines.length === 0) {
            alert('Add at least one product before submitting.');
            return false;
        }

        if (!document.getElementById('useDefaultDeliveryAddress').checked &&
            !document.getElementById('custom_delivery_line1').value.trim()) {
            alert('Enter a delivery address, or check "Use existing delivery address".');
            return false;
        }

        var advBalance = parseFloat(document.getElementById('advBalanceVal').value) || 0;
        var total = poGrandTotal();
        var excess = total - advBalance;

        if (excess > 0.001 && !hasEligibleAdvanceSubmission) {
            alert('Your order total exceeds your available advance balance by ₹' + excess.toFixed(2) +
                '. Please submit an advance payment for review before submitting this order.');
            return false;
        }
        return true;
    }
    </script>
</body>
</html>
