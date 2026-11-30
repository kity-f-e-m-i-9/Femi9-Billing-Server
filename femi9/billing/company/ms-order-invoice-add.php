<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
require_once("include/CompanyOrderInvoice.php");
require_once("include/GodownAccess.php");
require_once(__DIR__ . '/../shared/MsOrderEdits.php');
require_once(__DIR__ . '/../shared/CpShopWalletCommission.php');
require_once(__DIR__ . '/../shared/InvoiceNumberSuggest.php');
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

// assigned_cp_id is a self-migrating column (marketing/order_action_get.php,
// 2026-11-12) -- guard here too so this page never 500s on an environment
// where no vacant-area order has used it yet.
$chkCpCol = $db_conn->query("SHOW COLUMNS FROM ms_orders LIKE 'assigned_cp_id'");
if ($chkCpCol && $chkCpCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE ms_orders ADD COLUMN assigned_cp_id INT UNSIGNED NULL DEFAULT NULL AFTER tp_id");
}
msOrderEditsEnsureSchema($db_conn); // adds price_override if missing

$stmtLines = $db_conn->prepare(
    "SELECT id, shop_id, ms_id, order_date, pr_id, qty, assigned_cp_id, price_override FROM ms_orders
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
$stmtExisting = $db_conn->prepare("SELECT inv_id, inv_number, from_user_id FROM user_invoice WHERE source_ms_order_id=? LIMIT 1");
$stmtExisting->bind_param('s', $order_id);
$stmtExisting->execute();
$existing = $stmtExisting->get_result()->fetch_assoc();
$stmtExisting->close();
if ($existing) {
    if ($existing['from_user_id'] === 'company') {
        // CP-fulfilled invoices are created complete, nothing left to edit —
        // go straight to the printable invoice instead of just bouncing back
        // to ms_prorders.php with a dead-end message (2026-11-20, user-reported:
        // "View Invoice" showed a popup instead of the actual invoice, and the
        // popup quoted the raw system inv_id instead of the real SH/FY/LLP{n}
        // number everywhere else uses).
        header('Location: shop-user-invoice-print?invoiceid=' . base64_encode($existing['inv_id']));
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

// The DM's own explicit choice (marketing/add_order.php's "Assign To CP"
// dropdown, 2026-11-12) is now the source of truth for which CP (if any)
// fulfils this order -- it replaces resolveCpForMsShop()'s automatic
// geography-based guess, which could silently pick a different CP than the
// one the DM actually saw/chose on the Get Order form. An explicit
// assigned_cp_id of NULL means the DM picked "Company" -- that's final, not
// a signal to fall back to auto-resolving a CP.
$cp = null;
$assigned_cp_id = (int)($lines[0]['assigned_cp_id'] ?? 0);
if ($assigned_cp_id > 0) {
    $stmtCp = $db_conn->prepare("SELECT id, name FROM channel_partners WHERE id=? AND is_active=1 LIMIT 1");
    $stmtCp->bind_param('i', $assigned_cp_id);
    $stmtCp->execute();
    $cpRow = $stmtCp->get_result()->fetch_assoc();
    $stmtCp->close();
    if ($cpRow) { $cp = ['id' => (int)$cpRow['id'], 'name' => $cpRow['name']]; }
}

$prodIds = array_column($lines, 'pr_id');
$prodMap = [];
if (!empty($prodIds)) {
    $idList = implode(',', array_map('intval', $prodIds));
    $r = $db_conn->query("SELECT id, productName FROM products WHERE id IN ($idList)");
    while ($p = $r->fetch_assoc()) { $prodMap[$p['id']] = $p['productName']; }
}

// Company's own per-line price edit (shared/MsOrderEdits.php) overrides the
// product's default outlet_price when set -- a CP edit never touches price,
// only qty (ms_orders.qty itself, already reflected in $lines above).
$priceOverrideMap = [];
foreach ($lines as $ln) {
    if ($ln['price_override'] !== null) { $priceOverrideMap[(int)$ln['pr_id']] = (float)$ln['price_override']; }
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

// Physical warehouse to deduct from at Submit time — same list and same
// required field as shop-user-invoice-add.php's own "new invoice" form.
// Staff picks explicitly now instead of it being silently inferred from the
// Company Profile choice (2026-11-17, user request — "warehouse also choose
// pannura mathiri vechuru").
$warehouseOptions = [];
$resWarehouse = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
while ($w = $resWarehouse->fetch_assoc()) { $warehouseOptions[] = $w; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Staff can override the auto-detected CP coverage and invoice from the
    // company's own stock instead (e.g. the covering CP is genuinely out of
    // stock) — see the two separate forms below, 2026-11-13.
    $useCp = $cp && (($_POST['fulfillment_mode'] ?? 'cp') === 'cp');

    if ($useCp) {
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
                // Same SH/FY/LLP{n} (napkin) / SHD/FY/LLP{n} (diaper) numbering the
                // regular "Add Invoice" flow uses (load_next_invoice_number_shop.
                // php) — this page used to just reuse the raw system inv_id as
                // inv_number, which looked nothing like every other Shop invoice's
                // number. 2026-11-20, user-reported.
                $inv_number = invoiceSuggestNextNumber(
                    $db_conn, 'user_invoice', "from_user_type='company'", 'SH', 'SHD',
                    invoiceProductCategory($db_conn, (int)$validLines[0]['pr_id'])
                );
                $inv_date   = date('Y-m-d');
                $inv_year   = date('Y', strtotime($inv_date));
                $note       = "DM: $dmName (order $order_id)";

                ensureShopWalletCommissionSchema($db_conn);

                $db_conn->begin_transaction();
                try {
                    $zero = '0'; $one = '1'; $nil = 'Nil'; $fromType = 'company'; $fromId = 'company'; $invuser = 'shop';
                    $cpIdForInvoice = (string) $cp['id'];
                    $stmtInv = $db_conn->prepare(
                        "INSERT INTO user_invoice
                         (inv_id,source_ms_order_id,fulfilled_by_cp_id,id_only,inv_number,date,inv_year,sub_total,discount,total,to_user_type,to_user_id,
                          from_user_type,from_user_id,gst_type,credit,roundoff,courier_charges,rwpoints_enable,buyer_gsttype,username,usertype)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    $stmtInv->bind_param('ssssssssssssssssssssss',
                        $inv_id, $order_id, $cpIdForInvoice, $zero, $inv_number, $inv_date, $inv_year, $zero, $zero, $zero,
                        $invuser, $customer_id, $fromType, $fromId,
                        $gst_type, $zero, $zero, $zero, $one, $buyer_gsttype, $nil, $nil
                    );
                    $stmtInv->execute();
                    $stmtInv->close();

                    $headerSubTotal = 0; $headerTotal = 0;
                    foreach ($validLines as $vl) {
                        $pr_id = $vl['pr_id']; $qty = $vl['qty']; $prod = $vl['prod'];
                        $amount          = $priceOverrideMap[$pr_id] ?? (float)$prod['outlet_price'];
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
                        // Same floor-at-0 guard StockService::deduct() enforces everywhere
                        // else in the codebase — without it, two concurrent invoices (or a
                        // stale on-screen qty) could drive channel_partner_stock negative
                        // with no error, since the plain UPDATE below has no WHERE clause
                        // checking closing_qty >= qty. 2026-11-21, code-review finding.
                        if ($qty > $before) {
                            throw new \RuntimeException("Insufficient CP stock for product=$pr_id. Available=$before, Requested=$qty");
                        }
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
                    // Two buttons on the same form (2026-11-23, user-requested):
                    // "Create Invoice" (pay_now) goes straight to Add Receipt so
                    // payment can be entered right away -- if it ends up fully
                    // paid, add-receipt.php's own maybeCreditShopWalletCommission()
                    // call credits the CP's 3% commission immediately, same as
                    // ever. "Create Not Paid Invoice" keeps the original
                    // behaviour unchanged -- invoice created, no receipt, handled
                    // later from Manage Shop exactly like before.
                    if (!empty($_POST['pay_now'])) {
                        header('Location: add-receipt.php?invid=' . urlencode($inv_id) . '&invuser=shop');
                    } else {
                        $_SESSION['successMessage'] = "Invoice $inv_number created against " . $cp['name'] . "'s stock.";
                        header('Location: ms_prorders.php');
                    }
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
        $postedWarehouseId = (int)($_POST['warehouse_id'] ?? 0);
        if (!is_godown_allowed($db_conn, $godownid)) {
            $errorMsg = "Please choose a valid Company Profile.";
        } elseif ($postedWarehouseId <= 0) {
            $errorMsg = "Please choose a valid Warehouse.";
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

                // No stock-sufficiency check here on purpose — a draft never
                // deducts stock (that only happens at Submit Invoice, via
                // StockService::deduct(), which has its own hard floor-at-0
                // guard), so blocking draft creation just because the chosen
                // godown is short right now defeats Auto Transfer's whole
                // point: it only ever sees a Shop Invoice as demand once a
                // draft already exists (company/include/AutoTransferDemand.
                // php's source_ms_order_id-keyed query), so staff need to be
                // able to create the draft BEFORE stock arrives, run Auto
                // Transfer to bring it in, then Submit. Real safety still
                // lives at Submit time. 2026-11-13, user request.
                $godownid_str = (string)$godownid;
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

                    $validLines[] = ['pr_id' => $pr_id, 'qty' => $qty, 'prod' => $prod];
                }

                if (empty($validLines)) {
                    $errorMsg = "No valid product lines to invoice.";
                } else {
                    $inv_id     = generateCompanyOrderInvId();
                    // Same SH/FY/LLP{n} / SHD/FY/LLP{n} numbering as the regular
                    // "Add Invoice" flow — see the CP-stock branch's own comment
                    // above for the full story. 2026-11-20, user-reported.
                    $inv_number = invoiceSuggestNextNumber(
                        $db_conn, 'user_invoice', "from_user_type='company'", 'SH', 'SHD',
                        invoiceProductCategory($db_conn, (int)$validLines[0]['pr_id'])
                    );
                    $inv_date   = date('Y-m-d');
                    $inv_year   = date('Y', strtotime($inv_date));

                    $db_conn->begin_transaction();
                    try {
                        $zero = '0'; $one = '1'; $nil = 'Nil'; $fromType = 'company'; $invuser = 'shop';

                        // Physical warehouse to deduct from at Submit time. Previously left
                        // NULL here — StockService::deduct() then looked for a NULL-warehouse
                        // stock row, which no longer exists for any product (stock lives in
                        // real warehouse buckets like G1/HO), so Submit (and any later re-Submit
                        // on Edit) failed with "No stock record found". Now an explicit required
                        // field on the form (validated above), not inferred — staff picks the
                        // real warehouse themselves. 2026-11-17, user-reported bug.
                        $warehouseIdForGodown = $postedWarehouseId;

                        $stmtInv = $db_conn->prepare(
                            "INSERT INTO user_invoice
                             (inv_id,source_ms_order_id,id_only,inv_number,date,inv_year,sub_total,discount,total,to_user_type,to_user_id,
                              from_user_type,from_user_id,warehouse_id,gst_type,credit,roundoff,courier_charges,rwpoints_enable,buyer_gsttype,username,usertype)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                        );
                        $stmtInv->bind_param('sssssssssssssissssssss',
                            $inv_id, $order_id, $zero, $inv_number, $inv_date, $inv_year, $zero, $zero, $zero,
                            $invuser, $customer_id, $fromType, $godownid_str, $warehouseIdForGodown,
                            $gst_type, $zero, $zero, $zero, $one, $buyer_gsttype, $nil, $nil
                        );
                        $stmtInv->execute();
                        $stmtInv->close();

                        foreach ($validLines as $vl) {
                            $pr_id = $vl['pr_id']; $qty = $vl['qty']; $prod = $vl['prod'];
                            $amount          = $priceOverrideMap[$pr_id] ?? (float)$prod['outlet_price'];
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

                            <style>
                                .fo-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; margin-bottom:14px; color:#6b7280; }
                                .fo-option { border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; margin-bottom:16px; background:#fafbfc; }
                                .fo-option-cp { border-left:4px solid #2269f5; }
                                .fo-option-company { border-left:4px solid #ff9500; }
                                .fo-option-title { display:flex; align-items:center; gap:8px; font-weight:700; font-size:15px; color:#1f2937; margin-bottom:6px; }
                                .fo-option-title .material-icons { font-size:19px; color:inherit; }
                                .fo-option-cp .fo-option-title { color:#2269f5; }
                                .fo-option-company .fo-option-title { color:#b56a00; }
                                .fo-option-desc { font-size:13px; color:#6b7280; margin-bottom:14px; line-height:1.5; }
                                .fo-select { max-width:360px; }
                                .fo-back { margin-top:4px; }
                            </style>

                            <div class="fo-label">Choose how to fulfil this order</div>

                            <?php if ($cp): ?>
                            <div class="fo-option fo-option-cp">
                                <div class="fo-option-title"><i class="material-icons">local_shipping</i> Via Channel Partner</div>
                                <div class="fo-option-desc">
                                    This shop is covered by <b><?=htmlspecialchars($cp['name'])?></b> — stock will come out of
                                    their own stock, and the invoice is created complete right away.
                                </div>
                                <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;">
                                    <input type="hidden" name="order_id" value="<?=htmlspecialchars($order_id)?>">
                                    <input type="hidden" name="fulfillment_mode" value="cp">
                                    <button type="submit" name="pay_now" value="1" class="btn" style="background:#2269f5;color:#fff;" onclick="return confirm('Create this invoice against <?=htmlspecialchars(addslashes($cp['name']))?>\'s stock, then enter payment now?');">
                                        <i class="material-icons">receipt_long</i> Create Invoice (via <?=htmlspecialchars($cp['name'])?>)
                                    </button>
                                    <button type="submit" class="btn" style="background:#fff;color:#2269f5;border:1.5px solid #2269f5;" onclick="return confirm('Create this invoice against <?=htmlspecialchars(addslashes($cp['name']))?>\'s stock as Not Paid? You can record payment later from Manage Shop.');">
                                        <i class="material-icons">receipt_long</i> Create Not Paid Invoice
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>

                            <div class="fo-option fo-option-company">
                                <div class="fo-option-title"><i class="material-icons">apartment</i> Via Draft Company</div>
                                <div class="fo-option-desc">
                                    <?php if ($cp): ?>
                                        Use this instead if <?=htmlspecialchars($cp['name'])?> doesn't have enough stock, or you'd
                                        rather this one go through the company. Pick which Company Profile (godown) to invoice from.
                                    <?php else: ?>
                                        No Channel Partner covers this shop's area — this will be invoiced from the
                                        company's own stock. Pick which Company Profile (godown) to invoice from.
                                    <?php endif; ?>
                                    This creates a <strong>Draft only</strong> — stock is not deducted here. Release it later
                                    from the Product Orders page's checkbox, or Auto Transfer's "Release Shop Invoice Stock" button.
                                </div>
                                <form method="post">
                                    <input type="hidden" name="order_id" value="<?=htmlspecialchars($order_id)?>">
                                    <input type="hidden" name="fulfillment_mode" value="company">
                                    <label class="form-label">Company Profile*</label>
                                    <select name="godownid" class="form-control fo-select" required>
                                        <option value="" hidden>Select</option>
                                        <?php foreach ($godownOptions as $g): ?>
                                        <option value="<?=htmlspecialchars((string)($g['id']), ENT_QUOTES, 'ISO-8859-1', false)?>"><?=htmlspecialchars($g['gname'])?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <br/>
                                    <label class="form-label">Warehouse (physical)*</label>
                                    <select name="warehouse_id" class="form-control fo-select" required>
                                        <option value="" hidden>Select</option>
                                        <?php foreach ($warehouseOptions as $w): ?>
                                        <option value="<?=(int)$w['id']?>"><?=htmlspecialchars($w['code'], ENT_QUOTES, 'UTF-8')?><?=$w['name'] ? ' - ' . htmlspecialchars($w['name'], ENT_QUOTES, 'UTF-8') : ''?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="text-muted" style="font-size:12px;margin:8px 0 14px;">
                                        Note: remember to add "DM: <?=htmlspecialchars($dmName)?>" in the invoice remarks when you submit it.
                                    </div>
                                    <button type="submit" class="btn" style="background:#ff9500;color:#fff;">
                                        <i class="material-icons">receipt_long</i> Continue to Invoice (via Draft Company)
                                    </button>
                                </form>
                            </div>

                            <div class="fo-back">
                                <a href="ms_prorders.php" class="btn btn-outline-secondary">
                                    <i class="material-icons">arrow_back</i> Back
                                </a>
                            </div>

                        </div></div>
                    </div></div>

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
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
