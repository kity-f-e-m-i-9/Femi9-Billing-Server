<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
require_once("include/CompanyOrderInvoice.php");
require_once("include/GodownAccess.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// Turns a DM's Get Order (ms_orders rows, no TP assigned) into a real
// invoice, company-side. Two fulfilment paths, chosen automatically:
//   - A Channel Partner covers the shop's area -> the invoice is created
//     complete right here, stock comes out of that CP's own stock
//     (channel_partner_stock), and the CP can see the deduction in their
//     own stock ledger.
//   - No CP covers it -> a draft invoice is created against a company
//     godown the staff picks, then handed off to the existing, unmodified
//     shop-user-invoice-add.php pipeline (receipt/Submit Invoice/stock
//     deduction all already work there).

$order_id = $_REQUEST['order_id'] ?? '';
if ($order_id === '') { header('Location: ms_prorders.php'); exit; }

$stmtLines = $db_conn->prepare(
    "SELECT id, shop_id, ms_id, order_date, pr_id, qty FROM ms_orders
     WHERE order_id=? AND new_order='yes' AND tp_id IS NULL ORDER BY id ASC"
);
$stmtLines->bind_param('s', $order_id);
$stmtLines->execute();
$lines = $stmtLines->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtLines->close();

if (empty($lines)) {
    $_SESSION['errorMessage'] = "This order is not eligible for direct company invoicing.";
    header('Location: ms_prorders.php'); exit;
}

// Already invoiced earlier — resume/view instead of creating a duplicate.
$stmtExisting = $db_conn->prepare("SELECT inv_id, from_user_id FROM user_invoice WHERE source_ms_order_id=? LIMIT 1");
$stmtExisting->bind_param('s', $order_id);
$stmtExisting->execute();
$existing = $stmtExisting->get_result()->fetch_assoc();
$stmtExisting->close();
if ($existing) {
    if ($existing['from_user_id'] === 'company') {
        // CP-fulfilled invoices are created complete, nothing left to edit.
        $_SESSION['successMessage'] = "This order was already invoiced (Invoice: " . $existing['inv_id'] . ").";
        header('Location: ms_prorders.php');
    } else {
        header('Location: shop-user-invoice-add.php?InvoiceID=' . base64_encode($existing['inv_id']) . '&invuser=shop&gid=' . urlencode($existing['from_user_id']) . '&action=edit');
    }
    exit;
}

$ms_shop_id = (int)$lines[0]['shop_id'];
$ms_id      = (int)$lines[0]['ms_id'];

$stmtDm = $db_conn->prepare("SELECT ms_name FROM marketing_staff WHERE id=? LIMIT 1");
$stmtDm->bind_param('i', $ms_id);
$stmtDm->execute();
$dmName = $stmtDm->get_result()->fetch_assoc()['ms_name'] ?? 'Unknown DM';
$stmtDm->close();

$stmtShopInfo = $db_conn->prepare("SELECT name, mobile_number, district_name, taluk_name FROM ms_shop WHERE id=? LIMIT 1");
$stmtShopInfo->bind_param('i', $ms_shop_id);
$stmtShopInfo->execute();
$msShopInfo = $stmtShopInfo->get_result()->fetch_assoc() ?: [];
$stmtShopInfo->close();

$cp = resolveCpForMsShop($db_conn, $ms_shop_id);

$prodIds = array_column($lines, 'pr_id');
$prodMap = [];
if (!empty($prodIds)) {
    $idList = implode(',', array_map('intval', $prodIds));
    $r = $db_conn->query("SELECT id, productName FROM products WHERE id IN ($idList)");
    while ($p = $r->fetch_assoc()) { $prodMap[$p['id']] = $p['productName']; }
}

function generateCompanyOrderInvId(): string {
    $chars = '123456789';
    $hash = '';
    for ($x = 0; $x < 10; $x++) { $hash .= substr($chars, random_int(0, strlen($chars) - 1), 1); }
    return $hash . 'CMPDIR' . date('dmygis');
}

$errorMsg = '';
$godownOptions = [];
$resGodown = $db_conn->query("SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname ASC");
while ($g = $resGodown->fetch_assoc()) { $godownOptions[] = $g; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($cp) {
        // ── Channel Partner stock path ──────────────────────────────────
        $bridgedShopId = bridgeMsShopToCompanyShop($db_conn, $ms_shop_id);
        if (!$bridgedShopId) {
            $errorMsg = "Could not prepare the shop record for invoicing.";
        } else {
            $stmtShopRow = $db_conn->prepare("SELECT temp_id, gstin, state_id FROM shop WHERE id=? LIMIT 1");
            $stmtShopRow->bind_param('i', $bridgedShopId);
            $stmtShopRow->execute();
            $shopRow = $stmtShopRow->get_result()->fetch_assoc();
            $stmtShopRow->close();

            $customer_id   = $shopRow['temp_id'];
            $buyer_GSTIN   = $shopRow['gstin'] ?? '';
            $buyer_gsttype = strlen($buyer_GSTIN) === 15 ? 'register' : 'unregister';

            $adminRow = $db_conn->query("SELECT state FROM admin_log WHERE usertype='admin' LIMIT 1")->fetch_assoc();
            $admin_statecode = $adminRow['state'] ?? '';
            $gst_type = ((string)($shopRow['state_id'] ?? '') === (string)$admin_statecode) ? 'inner' : 'outer';

            $shortfalls = [];
            $validLines = [];
            foreach ($lines as $ln) {
                $pr_id = (int)$ln['pr_id']; $qty = (int)$ln['qty'];
                if ($pr_id <= 0 || $qty <= 0) { continue; }

                $stmtProd = $db_conn->prepare("SELECT productName, gst, gst_type, hsn, outlet_price FROM products WHERE id=?");
                $stmtProd->bind_param('i', $pr_id);
                $stmtProd->execute();
                $prod = $stmtProd->get_result()->fetch_assoc();
                $stmtProd->close();
                if (!$prod) { continue; }

                $stmtStk = $db_conn->prepare("SELECT closing_qty FROM channel_partner_stock WHERE channel_partner_id=? AND product_id=?");
                $stmtStk->bind_param('ii', $cp['id'], $pr_id);
                $stmtStk->execute();
                $available = (int)($stmtStk->get_result()->fetch_assoc()['closing_qty'] ?? 0);
                $stmtStk->close();

                if ($available < $qty) {
                    $shortfalls[] = $prod['productName'] . " (need $qty, have $available)";
                    continue;
                }
                $validLines[] = ['pr_id' => $pr_id, 'qty' => $qty, 'prod' => $prod];
            }

            if (!empty($shortfalls)) {
                $errorMsg = "Not enough stock with " . $cp['name'] . " to invoice: " . implode(', ', $shortfalls) . ". Ask them to restock, or edit the order first.";
            } elseif (empty($validLines)) {
                $errorMsg = "No valid product lines to invoice.";
            } else {
                $inv_id     = generateCompanyOrderInvId();
                $inv_number = $inv_id;
                $inv_date   = date('Y-m-d');
                $inv_year   = date('Y', strtotime($inv_date));
                $note       = "DM: $dmName (order $order_id)";

                $db_conn->begin_transaction();
                try {
                    $zero = '0'; $one = '1'; $nil = 'Nil'; $fromType = 'company'; $fromId = 'company'; $invuser = 'shop';
                    $stmtInv = $db_conn->prepare(
                        "INSERT INTO user_invoice
                         (inv_id,source_ms_order_id,id_only,inv_number,date,inv_year,sub_total,discount,total,to_user_type,to_user_id,
                          from_user_type,from_user_id,gst_type,credit,roundoff,courier_charges,rwpoints_enable,buyer_gsttype,username,usertype)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    $stmtInv->bind_param('sssssssssssssssssssss',
                        $inv_id, $order_id, $zero, $inv_number, $inv_date, $inv_year, $zero, $zero, $zero,
                        $invuser, $customer_id, $fromType, $fromId,
                        $gst_type, $zero, $zero, $zero, $one, $buyer_gsttype, $nil, $nil
                    );
                    $stmtInv->execute();
                    $stmtInv->close();

                    $headerSubTotal = 0; $headerTotal = 0;
                    foreach ($validLines as $vl) {
                        $pr_id = $vl['pr_id']; $qty = $vl['qty']; $prod = $vl['prod'];
                        $amount          = (float)$prod['outlet_price'];
                        $gst_percentage  = $prod['gst'] ?? 0;
                        $product_gst_type = ($prod['gst_type'] ?? 'exclusive') === 'inclusive' ? 'inclusive' : 'exclusive';
                        $hsn             = $prod['hsn'] ?? '';
                        $totalamount     = $amount * $qty;
                        $discount_amount = 0; $discount_percentage = 0;
                        $subtotal        = $totalamount;
                        // Inclusive-tax products already have GST baked into outlet_price, so
                        // the tax is carved out of subtotal (and NOT added again into total);
                        // exclusive-tax products get GST added on top — same convention as
                        // tp-invoice-print.php.
                        if ($product_gst_type === 'inclusive' && $gst_percentage > 0) {
                            $gstamount_total = $subtotal - ($subtotal * 100 / (100 + $gst_percentage));
                            $total           = $subtotal;
                        } else {
                            $gstamount_total = $subtotal * $gst_percentage / 100;
                            $total           = $subtotal + $gstamount_total;
                        }
                        $headerSubTotal += $subtotal;
                        $headerTotal    += $total;

                        $stmtItem = $db_conn->prepare(
                            "INSERT INTO user_invoice_items
                             (inv_id,pr_id,amount,qty,total,to_user_type,to_user_id,from_user_type,from_user_id,
                              gst_percentage,gstamount_singlepr,gstamount_total,subtotal,
                              discount_percentage,discount_amount,gst_type,hsn,date,buyer_gsttype)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                        );
                        $stmtItem->bind_param('sididssssdsddddssss',
                            $inv_id, $pr_id, $amount, $qty, $total,
                            $invuser, $customer_id, $fromType, $fromId,
                            $gst_percentage, $zero, $gstamount_total, $subtotal,
                            $discount_percentage, $discount_amount, $gst_type, $hsn, $inv_date, $buyer_gsttype
                        );
                        $stmtItem->execute();
                        $stmtItem->close();

                        // Debit CP stock the same way an existing TP-invoice-from-CP-stock does.
                        $stmtLock = $db_conn->prepare("SELECT closing_qty FROM channel_partner_stock WHERE channel_partner_id=? AND product_id=? FOR UPDATE");
                        $stmtLock->bind_param('ii', $cp['id'], $pr_id);
                        $stmtLock->execute();
                        $before = (int)($stmtLock->get_result()->fetch_assoc()['closing_qty'] ?? 0);
                        $stmtLock->close();
                        $after = $before - $qty;

                        $stmtDebit = $db_conn->prepare("UPDATE channel_partner_stock SET closing_qty=closing_qty-? WHERE channel_partner_id=? AND product_id=?");
                        $stmtDebit->bind_param('iii', $qty, $cp['id'], $pr_id);
                        $stmtDebit->execute();
                        $stmtDebit->close();

                        $action = 'transfer_out'; $ref_type = 'company_direct_invoice';
                        $stmtLedger = $db_conn->prepare(
                            "INSERT INTO channel_partner_stock_ledger (channel_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by)
                             VALUES (?,?,?,?,?,?,?,?,?,?)"
                        );
                        $stmtLedger->bind_param('iisiiissss', $cp['id'], $pr_id, $action, $qty, $before, $after, $ref_type, $inv_id, $note, $dmName);
                        $stmtLedger->execute();
                        $stmtLedger->close();
                    }

                    $stmtTotals = $db_conn->prepare("UPDATE user_invoice SET sub_total=?, total=? WHERE inv_id=?");
                    $stmtTotals->bind_param('dds', $headerSubTotal, $headerTotal, $inv_id);
                    $stmtTotals->execute();
                    $stmtTotals->close();

                    $db_conn->commit();
                    $_SESSION['successMessage'] = "Invoice $inv_number created against " . $cp['name'] . "'s stock.";
                    header('Location: ms_prorders.php');
                    exit;
                } catch (\Throwable $e) {
                    $db_conn->rollback();
                    $errorMsg = "Could not create the invoice. Please try again.";
                }
            }
        }
    } else {
        // ── Company's own godown stock path ─────────────────────────────
        $godownid = (int)($_POST['godownid'] ?? 0);
        if (!is_godown_allowed($db_conn, $godownid)) {
            $errorMsg = "Please choose a valid Company Profile.";
        } else {
            $bridgedShopId = bridgeMsShopToCompanyShop($db_conn, $ms_shop_id);
            if (!$bridgedShopId) {
                $errorMsg = "Could not prepare the shop record for invoicing.";
            } else {
                $stmtShopRow = $db_conn->prepare("SELECT temp_id, gstin, state_id FROM shop WHERE id=? LIMIT 1");
                $stmtShopRow->bind_param('i', $bridgedShopId);
                $stmtShopRow->execute();
                $shopRow = $stmtShopRow->get_result()->fetch_assoc();
                $stmtShopRow->close();

                $customer_id   = $shopRow['temp_id'];
                $buyer_GSTIN   = $shopRow['gstin'] ?? '';
                $buyer_gsttype = strlen($buyer_GSTIN) === 15 ? 'register' : 'unregister';

                $adminRow = $db_conn->query("SELECT state FROM admin_log WHERE usertype='admin' LIMIT 1")->fetch_assoc();
                $admin_statecode = $adminRow['state'] ?? '';
                $gst_type = ((string)($shopRow['state_id'] ?? '') === (string)$admin_statecode) ? 'inner' : 'outer';

                $godownid_str = (string)$godownid;
                $shortfalls = [];
                $validLines = [];
                foreach ($lines as $ln) {
                    $pr_id = (int)$ln['pr_id']; $qty = (int)$ln['qty'];
                    if ($pr_id <= 0 || $qty <= 0) { continue; }

                    $stmtProd = $db_conn->prepare("SELECT productName, gst, gst_type, hsn, outlet_price FROM products WHERE id=?");
                    $stmtProd->bind_param('i', $pr_id);
                    $stmtProd->execute();
                    $prod = $stmtProd->get_result()->fetch_assoc();
                    $stmtProd->close();
                    if (!$prod) { continue; }

                    $stmtStk = $db_conn->prepare("SELECT closing_qty FROM stock WHERE user_type='company' AND user_id=? AND product_id=?");
                    $stmtStk->bind_param('si', $godownid_str, $pr_id);
                    $stmtStk->execute();
                    $available = (int)($stmtStk->get_result()->fetch_assoc()['closing_qty'] ?? 0);
                    $stmtStk->close();

                    if ($available < $qty) {
                        $shortfalls[] = $prod['productName'] . " (need $qty, have $available)";
                        continue;
                    }
                    $validLines[] = ['pr_id' => $pr_id, 'qty' => $qty, 'prod' => $prod];
                }

                if (!empty($shortfalls)) {
                    $errorMsg = "Not enough stock at this Company Profile to invoice: " . implode(', ', $shortfalls) . ".";
                } elseif (empty($validLines)) {
                    $errorMsg = "No valid product lines to invoice.";
                } else {
                    $inv_id     = generateCompanyOrderInvId();
                    $inv_number = $inv_id;
                    $inv_date   = date('Y-m-d');
                    $inv_year   = date('Y', strtotime($inv_date));

                    $db_conn->begin_transaction();
                    try {
                        $zero = '0'; $one = '1'; $nil = 'Nil'; $fromType = 'company'; $invuser = 'shop';
                        $stmtInv = $db_conn->prepare(
                            "INSERT INTO user_invoice
                             (inv_id,source_ms_order_id,id_only,inv_number,date,inv_year,sub_total,discount,total,to_user_type,to_user_id,
                              from_user_type,from_user_id,gst_type,credit,roundoff,courier_charges,rwpoints_enable,buyer_gsttype,username,usertype)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                        );
                        $stmtInv->bind_param('sssssssssssssssssssss',
                            $inv_id, $order_id, $zero, $inv_number, $inv_date, $inv_year, $zero, $zero, $zero,
                            $invuser, $customer_id, $fromType, $godownid_str,
                            $gst_type, $zero, $zero, $zero, $one, $buyer_gsttype, $nil, $nil
                        );
                        $stmtInv->execute();
                        $stmtInv->close();

                        foreach ($validLines as $vl) {
                            $pr_id = $vl['pr_id']; $qty = $vl['qty']; $prod = $vl['prod'];
                            $amount          = (float)$prod['outlet_price'];
                            $gst_percentage  = $prod['gst'] ?? 0;
                            $product_gst_type = ($prod['gst_type'] ?? 'exclusive') === 'inclusive' ? 'inclusive' : 'exclusive';
                            $hsn             = $prod['hsn'] ?? '';
                            $totalamount     = $amount * $qty;
                            $discount_amount = 0; $discount_percentage = 0;
                            $subtotal        = $totalamount;
                            // Inclusive-tax products already have GST baked into outlet_price, so
                            // the tax is carved out of subtotal (and NOT added again into total);
                            // exclusive-tax products get GST added on top — same convention as
                            // tp-invoice-print.php.
                            if ($product_gst_type === 'inclusive' && $gst_percentage > 0) {
                                $gstamount_total = $subtotal - ($subtotal * 100 / (100 + $gst_percentage));
                                $total           = $subtotal;
                            } else {
                                $gstamount_total = $subtotal * $gst_percentage / 100;
                                $total           = $subtotal + $gstamount_total;
                            }

                            $stmtItem = $db_conn->prepare(
                                "INSERT INTO user_invoice_items
                                 (inv_id,pr_id,amount,qty,total,to_user_type,to_user_id,from_user_type,from_user_id,
                                  gst_percentage,gstamount_singlepr,gstamount_total,subtotal,
                                  discount_percentage,discount_amount,gst_type,hsn,date,buyer_gsttype)
                                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                            );
                            $stmtItem->bind_param('sididssssdsddddssss',
                                $inv_id, $pr_id, $amount, $qty, $total,
                                $invuser, $customer_id, $fromType, $godownid_str,
                                $gst_percentage, $zero, $gstamount_total, $subtotal,
                                $discount_percentage, $discount_amount, $gst_type, $hsn, $inv_date, $buyer_gsttype
                            );
                            $stmtItem->execute();
                            $stmtItem->close();
                        }

                        $db_conn->commit();
                        // Draft stays a draft (no stock deducted yet) — the existing
                        // shop-user-invoice-add.php page finishes it exactly like any
                        // other company invoice (edit lines, Submit Invoice deducts
                        // stock via StockService). Remarks ("DM: <name>") aren't
                        // pre-fillable there without touching that unmodified pipeline,
                        // so it's shown as a note on this page instead — the DM name
                        // is also always visible below via source_ms_order_id.
                        header('Location: shop-user-invoice-add.php?InvoiceID=' . base64_encode($inv_id) . '&invuser=shop&gid=' . $godownid . '&action=edit');
                        exit;
                    } catch (\Throwable $e) {
                        $db_conn->rollback();
                        $errorMsg = "Could not create the invoice. Please try again.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice DM Order : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="../../assets/css/vlstyle.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar"><?php include("logo.php"); ?><?php include("femi_menu.php"); ?></div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row"><div class="col">
                        <div class="page-description">
                            <h1><table class="headertble"><tr><td>Invoice DM Order</td></tr></table></h1>
                        </div>
                    </div></div>

                    <?php if ($errorMsg): ?>
                    <div class="row"><div class="col"><div class="alert alert-danger"><?=htmlspecialchars($errorMsg)?></div></div></div>
                    <?php endif; ?>

                    <div class="row"><div class="col-md-8">
                        <div class="card"><div class="card-body">

                            <p><b>DM:</b> <?=htmlspecialchars($dmName)?></p>
                            <p><b>Shop:</b> <?=htmlspecialchars($msShopInfo['name'] ?? '-')?> (<?=htmlspecialchars($msShopInfo['mobile_number'] ?? '')?>)</p>
                            <p><b>Location:</b> <?=htmlspecialchars(trim(($msShopInfo['taluk_name'] ?? '') . ', ' . ($msShopInfo['district_name'] ?? ''), ', '))?></p>

                            <table class="table table-bordered">
                                <thead><tr><th>Product</th><th>Qty</th></tr></thead>
                                <tbody>
                                <?php foreach ($lines as $ln): $pid = (int)$ln['pr_id']; if ($pid <= 0) continue; ?>
                                <tr><td><?=htmlspecialchars($prodMap[$pid] ?? '-')?></td><td><?=(int)$ln['qty']?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php if ($cp): ?>
                                <div class="alert alert-info">
                                    This shop is covered by Channel Partner <b><?=htmlspecialchars($cp['name'])?></b> —
                                    the invoice will be created now and stock will come out of their stock.
                                </div>
                                <form method="post">
                                    <input type="hidden" name="order_id" value="<?=htmlspecialchars($order_id)?>">
                                    <button type="submit" class="btn btn-primary" onclick="return confirm('Create this invoice against <?=htmlspecialchars(addslashes($cp['name']))?>\'s stock?');">
                                        <i class="material-icons">receipt_long</i> Create Invoice
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    No Channel Partner covers this shop's area — this will be invoiced from the
                                    company's own stock. Pick which Company Profile (godown) to invoice from.
                                </div>
                                <form method="post">
                                    <input type="hidden" name="order_id" value="<?=htmlspecialchars($order_id)?>">
                                    <label class="form-label">Company Profile*</label>
                                    <select name="godownid" class="form-control" required>
                                        <option value="" hidden>Select</option>
                                        <?php foreach ($godownOptions as $g): ?>
                                        <option value="<?=$g['id']?>"><?=htmlspecialchars($g['gname'])?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <br/>
                                    <div class="text-muted" style="font-size:12px;margin-bottom:10px;">
                                        Note: remember to add "DM: <?=htmlspecialchars($dmName)?>" in the invoice remarks when you submit it.
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="material-icons">receipt_long</i> Continue to Invoice
                                    </button>
                                </form>
                            <?php endif; ?>

                        </div></div>
                    </div></div>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
