<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(0);
include("RemoveSpecialChar.php");

// ============================================================
// INSERT
// ============================================================
if (isset($_REQUEST['add-record'])) {

    $tempid         = str_replace("'", "&#39;", $_REQUEST['tempid']);
    $godownid       = str_replace("'", "&#39;", $_REQUEST['godownid']);
    $date           = date("Y-m-d", strtotime($_REQUEST['date']));
    $catname        = str_replace("'", "&#39;", $_REQUEST['catname']);
    $inv_number     = RemoveSpecialChar(str_replace("'", "", $_REQUEST['inv_number']));
    $id_only        = "0";

    // ← CHANGED: Capture wallet_amount — only apply for eligible categories
    $wallet_eligible_cats = ['website', 'id concept']; // must match frontend list
    $cat_lower            = strtolower(trim($catname));
    $wallet_amount        = in_array($cat_lower, $wallet_eligible_cats)
                            ? (float) ($_REQUEST['wallet_amount'] ?? 0)
                            : 0.00;
    $wallet_amount        = max(0, $wallet_amount); // prevent negative

    /* Duplicate invoice number check
    $Select_Count_Invoice = "select * from ot_sales_invoice 
                             where inv_number='$inv_number' and cat='$catname'";
    $Fetch_Count_Invoice  = mysqli_query($db_conn, $Select_Count_Invoice);

    if (mysqli_num_rows($Fetch_Count_Invoice) != 0) {
        $_SESSION['errorMessage'] = "Invoice Number already exists this category ($catname)";
        echo "<script>window.location='ot-sale-add?invoicealready';</script>";
        exit;
    }*/

    $customer_name = RemoveSpecialChar(str_replace("'", "&#39;", $_POST["customer_name"]));

    $customer_mobile = ($_POST["customer_mobile"] != NULL)
        ? RemoveSpecialChar(str_replace("'", "&#39;", $_POST["customer_mobile"]))
        : "";

    $customer_address  = RemoveSpecialChar(str_replace("'", "&#39;", $_POST["customer_address"]));
    $shipping_address  = RemoveSpecialChar(str_replace("'", "&#39;", $_POST["shipping_address"]));

    $gst_number = ($_POST["gst_number"] != NULL)
        ? RemoveSpecialChar(str_replace("'", "&#39;", $_POST["gst_number"]))
        : "";

    $buyer_GSTIN_count = strlen($gst_number);
    $buyer_gsttype     = ($buyer_GSTIN_count == 15) ? "register" : "unregister";

    $order_number = ($_POST["order_number"] != NULL)
        ? RemoveSpecialChar(str_replace("'", "&#39;", $_POST["order_number"]))
        : "";

    $order_date   = ($_REQUEST['order_date'] != NULL)
        ? date("Y-m-d", strtotime($_REQUEST['order_date']))
        : "1991-01-01";

    $ship_date    = ($_REQUEST['ship_date'] != NULL)
        ? date("Y-m-d", strtotime($_REQUEST['ship_date']))
        : "1991-01-01";

    $amount_received  = "0";
    $amount_date      = "1991-01-01";
    $courier_charges  = RemoveSpecialChar($_REQUEST['courier_charges']);

    $state_id       = $_REQUEST['state_id'];
    $admin_state_id = $_REQUEST['admin_state_id'];
    $gst_type       = ($state_id == $admin_state_id) ? "inner" : "outer";

    $product_id_ex = $_REQUEST['product_id'];
    $qty_ex        = $_REQUEST['qty'];
    $rate_ex       = $_REQUEST['rate'];
    $discount_ex   = $_REQUEST['discount'];
    $number        = count($product_id_ex);
    $username      = $_REQUEST['username'];
    $usertype      = $_REQUEST['usertype'];

    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    // Pre-validate all stock levels before touching the DB
    for ($i = 0; $i < $number; $i++) {
        $pid = (int)($product_id_ex[$i] ?? 0);
        $qty = (int) RemoveSpecialChar($qty_ex[$i] ?? 0);
        if (!$pid || $qty <= 0) continue;

        $available = $stockService->getClosingQty($pid, $Login_user_TYPEvl, $godownid);
        if ($available === null || $available < $qty) {
            $_SESSION['errorMessageOT'] = "Insufficient stock for product #$pid. Available: " . ($available ?? 0) . ", Requested: $qty";
            echo "<script>window.location='ot-sale-add?InvalidStock&&AlertStockError';</script>";
            exit;
        }
    }

    // Wrap all inserts + stock deductions in one atomic transaction
    $db_conn->begin_transaction();
    try {
        for ($i = 0; $i < $number; $i++) {
            $product_id_value = (int)($product_id_ex[$i] ?? 0);
            $qty_value        = (int) RemoveSpecialChar($qty_ex[$i] ?? 0);
            $rate_value       = (float)($rate_ex[$i] ?? 0);
            $discount_value   = (float)($discount_ex[$i] ?? 0);

            if (!$product_id_value || $qty_value <= 0) continue;

            // Get product details
            $stmt = $db_conn->prepare("SELECT gst, hsn FROM products WHERE id = ?");
            $stmt->bind_param('i', $product_id_value);
            $stmt->execute();
            $prod = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $sub_total_rate = $rate_value * $qty_value;
            $sub_total      = $sub_total_rate - $discount_value;
            $gst            = (float)($prod['gst'] ?? 0);
            $gst_amount     = number_format($sub_total * $gst / 100, 2, '.', '');
            $total          = $sub_total + (float)$gst_amount;
            $hsn            = $prod['hsn'] ?? '';

            // Create invoice header once per tempid
            $stmt = $db_conn->prepare("SELECT COUNT(*) AS n FROM ot_sales_invoice WHERE tempid = ?");
            $stmt->bind_param('s', $tempid);
            $stmt->execute();
            $invExists = (int)$stmt->get_result()->fetch_assoc()['n'];
            $stmt->close();

            if ($invExists === 0) {
                $stmt = $db_conn->prepare(
                    "INSERT INTO ot_sales_invoice
                        (tempid, inv_id, inv_number, courier_charges, wallet_amount,
                         subtotal, round_off, total, buyer_gsttype, cat)
                     VALUES (?, '0', ?, ?, ?, '0', '0', '0', ?, ?)"
                );
                $stmt->bind_param('ssddss', $tempid, $inv_number, $courier_charges,
                                   $wallet_amount, $buyer_gsttype, $catname);
                $stmt->execute();
                $stmt->close();
            }

            // Skip if product already added under this tempid
            $stmt = $db_conn->prepare(
                "SELECT COUNT(*) AS n FROM ot_sales WHERE tempid = ? AND prid = ?"
            );
            $stmt->bind_param('si', $tempid, $product_id_value);
            $stmt->execute();
            $alreadyAdded = (int)$stmt->get_result()->fetch_assoc()['n'];
            $stmt->close();

            if ($alreadyAdded > 0) continue;

            // Insert ot_sales row
            $stmt = $db_conn->prepare(
                "INSERT INTO ot_sales
                    (godownid, cat, qty, date, tempid, prid, price, discount,
                     sub_total, total, gst, gst_amount, customer_name, customer_mobile,
                     customer_address, order_number, amount_received, amount_date,
                     shipping_address, gst_number, order_date, ship_date, hsn,
                     buyer_gsttype, state_id, gst_type, username, usertype)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '0', '1991-01-01',
                         ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'issssidddddsssssssssssssss',
                $godownid, $catname, $qty_value, $date, $tempid, $product_id_value,
                $rate_value, $discount_value, $sub_total, $total, $gst, $gst_amount,
                $customer_name, $customer_mobile, $customer_address, $order_number,
                $shipping_address, $gst_number, $order_date, $ship_date, $hsn,
                $buyer_gsttype, $state_id, $gst_type, $username, $usertype
            );
            $stmt->execute();
            $stmt->close();

            // Deduct stock via StockService (FOR UPDATE lock + ledger entry)
            $stockService->otDeduct(
                $product_id_value, $Login_user_TYPEvl, (string)$godownid,
                $qty_value, $tempid, $createdBy,
                true // externalTransaction — outer tx owns commit
            );
        }

        $db_conn->commit();

    } catch (StockException $e) {
        $db_conn->rollback();
        $_SESSION['errorMessageOT'] = "Stock error: " . $e->getMessage();
        echo "<script>window.location='ot-sale-add?InvalidStock&&AlertStockError';</script>";
        exit;
    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("ot-sale-action INSERT error: " . $e->getMessage());
        die("An error occurred. Please try again.");
    }

    // ← CHANGED: Final total UPDATE now deducts wallet_amount
    // Formula: total = round(subtotal + courier_charges) - wallet_amount
    $select_subtotal = "select sum(total) from ot_sales where tempid='$tempid'";
    $fetch_subtotal  = mysqli_query($db_conn, $select_subtotal);
    $result_subtotal = mysqli_fetch_array($fetch_subtotal);

    if ($result_subtotal[0] != NULL) {
        $unroundvalue = (float) $result_subtotal[0];
        $with_courier = $unroundvalue + (float) $courier_charges;
        $roundvalue   = round($with_courier);                         // ← courier added before rounding
        $roundoff     = $roundvalue - $with_courier;

        // ← CHANGED: wallet deducted from final payable total
        $net_total    = $roundvalue - $wallet_amount;
        $net_total    = max(0, $net_total); // cannot go negative

        $update_roundvalue = "update ot_sales_invoice 
            set subtotal='$unroundvalue',
                round_off='$roundoff',
                total='$net_total',
                wallet_amount='$wallet_amount',
                courier_charges='$courier_charges'
            where tempid='$tempid'";
        mysqli_query($db_conn, $update_roundvalue);
    }

    // Coupon commission logic (unchanged)
    if ($_REQUEST['coupon_code'] != NULL) {
        $coupon_code = $_REQUEST['coupon_code'];
        preg_match('/-(.*?)-/', $coupon_code, $matches);
        $coupon_usertype = $matches[1] ?? '';

        $tablenameCP   = '';
        $usertype_print = '';
        if ($coupon_usertype == "SS")     { $tablenameCP = "super_stockiest";    $usertype_print = "super_stockiest"; }
        elseif ($coupon_usertype == "S")  { $tablenameCP = "stockiest";          $usertype_print = "stockiest"; }
        elseif ($coupon_usertype == "SD") { $tablenameCP = "super_distributor";  $usertype_print = "super_distributor"; }
        elseif ($coupon_usertype == "D")  { $tablenameCP = "distributor";        $usertype_print = "distributor"; }

        if ($tablenameCP !== '') {
            $count12      = "select temp_id from $tablenameCP where useridtext='$coupon_code'";
            $fetch12      = mysqli_query($db_conn, $count12);

            if (mysqli_num_rows($fetch12) == 1) {
                $fetch_coupon_userID = mysqli_fetch_array($fetch12);
                $userid_print        = $fetch_coupon_userID['temp_id'];

                $select_total_qty = "select sum(qty) from ot_sales where tempid='$tempid'";
                $fetch_total_qty  = mysqli_query($db_conn, $select_total_qty);
                $result_total_qty = mysqli_fetch_array($fetch_total_qty);
                $qty_total_result = $result_total_qty[0];

                $select_coupon_commission = "select amount from admin_website_coupon_commission 
                    where usertype='$usertype_print'";
                $fetch_coupon_commission  = mysqli_query($db_conn, $select_coupon_commission);
                $result_coupon_commission = mysqli_fetch_array($fetch_coupon_commission);
                $cp_amount                = $result_coupon_commission['amount'];
                $total_coupon_commission  = $cp_amount * $qty_total_result;

                $Remarks_CB = "Invoice Number : " . $inv_number . "<br/>Date : " . date('d/m/Y');

                $count12_cpn = "select id from wallet_monthly_sls_report where user_type='$tempid'";
                $fetch12_cpn = mysqli_query($db_conn, $count12_cpn);

                if (mysqli_num_rows($fetch12_cpn) == 0) {
                    $Insert_wallet_records = "INSERT INTO wallet_monthly_sls_report 
                        (user_type,user_id,from_date,to_date,month,year,total_sls_amount,
                         target_sls_amount,target_reached,refer_by_usertype,refer_by_userid,
                         commission_percentage,commission_amount,commission_type,remarks) 
                        VALUES 
                        ('$tempid','Nill','".date('Y-m-d')."','".date('Y-m-d')."',
                         '0','0','0','0','yes','$usertype_print','$userid_print',
                         '0','$total_coupon_commission','Website Order Commission','$Remarks_CB')";
                    mysqli_query($db_conn, $Insert_wallet_records);
                }

                $update_ot_sales_invoice = "update ot_sales_invoice 
                    set coupon_code='$coupon_code', website_commission='$total_coupon_commission' 
                    where tempid='$tempid'";
                mysqli_query($db_conn, $update_ot_sales_invoice);
            }
        }
    }

    echo "<script>window.location='ot-sale-print?tempid=$tempid';</script>";
    exit;
}

// ============================================================
// UPDATE
// ============================================================
if (isset($_REQUEST['updateRecord'])) {

    $tempid   = $_REQUEST['tempid'];
    $date     = date("Y-m-d", strtotime($_REQUEST['date']));
    $catname  = str_replace("'", "&#39;", $_REQUEST['catname']);

    $customer_name = RemoveSpecialChar(str_replace("'", "&#39;", $_POST["customer_name"]));

    $customer_mobile = ($_POST["customer_mobile"] != NULL)
        ? RemoveSpecialChar(str_replace("'", "&#39;", $_POST["customer_mobile"]))
        : "";

    $customer_address = RemoveSpecialChar(str_replace("'", "&#39;", $_POST["customer_address"]));
    $shipping_address = RemoveSpecialChar(str_replace("'", "&#39;", $_POST["shipping_address"]));

    $gst_number = ($_POST["gst_number"] != NULL)
        ? RemoveSpecialChar(str_replace("'", "&#39;", $_POST["gst_number"]))
        : "";

    $order_number = ($_POST["order_number"] != NULL)
        ? RemoveSpecialChar(str_replace("'", "&#39;", $_POST["order_number"]))
        : "";

    $order_date = ($_REQUEST['order_date'] != NULL)
        ? date("Y-m-d", strtotime($_REQUEST['order_date']))
        : "1991-01-01";

    $ship_date = ($_REQUEST['ship_date'] != NULL)
        ? date("Y-m-d", strtotime($_REQUEST['ship_date']))
        : "1991-01-01";

    $inv_number      = RemoveSpecialChar(str_replace("'", "&#39;", $_POST["inv_number"]));
    $courier_charges = RemoveSpecialChar(str_replace("'", "&#39;", $_POST["courier_charges"]));

    // ← CHANGED: Capture wallet_amount on update too
    $wallet_eligible_cats = ['website', 'id concept'];
    $cat_lower            = strtolower(trim($catname));
    $wallet_amount        = in_array($cat_lower, $wallet_eligible_cats)
                            ? (float) ($_REQUEST['wallet_amount'] ?? 0)
                            : 0.00;
    $wallet_amount = max(0, $wallet_amount);

    $state_id       = $_REQUEST['state_id'];
    $admin_state_id = $_REQUEST['admin_state_id'];
    $gst_type       = ($state_id == $admin_state_id) ? "inner" : "outer";

    // ← CHANGED: wallet_amount included in invoice update
    $Update_Invoice = "update ot_sales_invoice 
        set inv_number='$inv_number',
            courier_charges='$courier_charges',
            wallet_amount='$wallet_amount',
            cat='$catname' 
        where tempid='$tempid'";
    mysqli_query($db_conn, $Update_Invoice);

    // ← CHANGED: Recalculate total on update to reflect any wallet_amount change
    $select_subtotal = "select sum(total) from ot_sales where tempid='$tempid'";
    $fetch_subtotal  = mysqli_query($db_conn, $select_subtotal);
    $result_subtotal = mysqli_fetch_array($fetch_subtotal);

    if ($result_subtotal[0] != NULL) {
        $unroundvalue = (float) $result_subtotal[0];
        $with_courier = $unroundvalue + (float) $courier_charges;
        $roundvalue   = round($with_courier);
        $roundoff     = $roundvalue - $with_courier;
        $net_total    = max(0, $roundvalue - $wallet_amount);

        $update_total = "update ot_sales_invoice 
            set subtotal='$unroundvalue',
                round_off='$roundoff',
                total='$net_total'
            where tempid='$tempid'";
        mysqli_query($db_conn, $update_total);
    }

    $Update_Records = "update ot_sales set 
        cat='$catname', date='$date', customer_name='$customer_name',
        customer_mobile='$customer_mobile', customer_address='$customer_address',
        order_number='$order_number', shipping_address='$shipping_address',
        gst_number='$gst_number', order_date='$order_date', ship_date='$ship_date',
        state_id='$state_id', gst_type='$gst_type' 
        where tempid='$tempid'";
    mysqli_query($db_conn, $Update_Records);

    $_SESSION['sucMessage'] = "Changes Saved Successfully!";
    echo "<script>window.location='ot-sale-view?Updated';</script>";
}
?>