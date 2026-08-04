<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

if (isset($_REQUEST['addInvoice'])) {

    $user_type_Loginvl = $Login_user_TYPEvl;
    $user_id_Loginvl   = $Login_user_IDvl;

    $randum_number = mysqli_real_escape_string($db_conn, $_REQUEST['randum_number']);
    $inv_id        = mysqli_real_escape_string($db_conn, $_REQUEST['inv_id']);
    $customer_id   = (int) $_REQUEST['customer_id'];
    $date          = date("Y-m-d", strtotime($_REQUEST['date']));
    $inv_year      = date("Y",     strtotime($_REQUEST['date']));
    $pr_id         = (int) $_REQUEST['pr_id'];
    $amount        = (int) $_REQUEST['amount'];
    $qty           = (int) $_REQUEST['qty'];
    $totalamount   = $amount * $qty;

    // Product GST — inclusive-tax products already have GST baked into the
    // entered price, so the tax is carved out of subtotal (and NOT added
    // again into total); exclusive-tax products get GST added on top —
    // same convention as tp-invoice-print.php.
    $stmtProd = $db_conn->prepare("SELECT gst, gst_type, hsn FROM products WHERE id = ?");
    $stmtProd->bind_param('i', $pr_id);
    $stmtProd->execute();
    $prodRow = $stmtProd->get_result()->fetch_assoc();
    $stmtProd->close();
    $gst_percentage   = (float) ($prodRow['gst'] ?? 0);
    $product_gst_type = ($prodRow['gst_type'] ?? 'exclusive') === 'inclusive' ? 'inclusive' : 'exclusive';
    $hsn              = $prodRow['hsn'] ?? '';

    $subtotal            = $totalamount;
    $discount_percentage = 0;
    $discount_amount     = 0;
    if ($product_gst_type === 'inclusive' && $gst_percentage > 0) {
        $gstamount_total = $subtotal - ($subtotal * 100 / (100 + $gst_percentage));
        $total           = $subtotal;
    } else {
        $gstamount_total = $subtotal * $gst_percentage / 100;
        $total           = $subtotal + $gstamount_total;
    }
    $gstamount_singlepr = 0;

    // Buyer GST registration + intra/inter-state — same convention as
    // customer-user-invoice-action.php (customer sales always treated intra-state).
    $stmtCust = $db_conn->prepare("SELECT gstin FROM customers WHERE id = ?");
    $stmtCust->bind_param('i', $customer_id);
    $stmtCust->execute();
    $custRow = $stmtCust->get_result()->fetch_assoc();
    $stmtCust->close();
    $buyer_gsttype = (strlen($custRow['gstin'] ?? '') === 15) ? 'register' : 'unregister';
    $gst_type      = 'inner';

    // Create invoice header if this is the first item
    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM invoice WHERE inv_id = ? AND user_type = ? AND user_id = ?"
    );
    $stmt->bind_param('sss', $inv_id, $user_type_Loginvl, $user_id_Loginvl);
    $stmt->execute();
    $exists = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    if ($exists === 0) {
        $stmt = $db_conn->prepare(
            "SELECT MAX(id_only) AS max_id FROM invoice WHERE user_type = ? AND user_id = ?"
        );
        $stmt->bind_param('ss', $user_type_Loginvl, $user_id_Loginvl);
        $stmt->execute();
        $maxId      = (int)($stmt->get_result()->fetch_assoc()['max_id'] ?? 0);
        $stmt->close();
        $id_only    = $maxId + 1;
        $format_num = str_pad($id_only, 3, '0', STR_PAD_LEFT);
        $INVDATE    = date("Ymd", strtotime($_REQUEST['date']));
        $inv_number = "{$INVDATE}/{$randum_number}/{$format_num}";

        $stmt = $db_conn->prepare(
            "INSERT INTO invoice
                (inv_id, id_only, inv_number, customer_id, date, inv_year,
                 sub_total, discount, total, user_type, user_id, gst_type, buyer_gsttype)
             VALUES (?, ?, ?, ?, ?, ?, '0', '0', '0', ?, ?, ?, ?)"
        );
        $stmt->bind_param('sisisissss', $inv_id, $id_only, $inv_number, $customer_id,
                          $date, $inv_year, $user_type_Loginvl, $user_id_Loginvl, $gst_type, $buyer_gsttype);
        $stmt->execute();
        $stmt->close();
    }

    // Stock availability check — read-only, no deduction here (deferred to submit)
    $stockService = new StockService($db_conn);
    $available    = $stockService->getClosingQty($pr_id, $user_type_Loginvl, $user_id_Loginvl);

    if ($available === null || $available < $qty) {
        echo "<script>window.location='invoice?InvoiceID=" . base64_encode($inv_id) . "&&InvalidStock&&AlertStockError';</script>";
        exit;
    }

    // Duplicate product guard
    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM invoice_items
          WHERE inv_id = ? AND pr_id = ? AND user_type = ? AND user_id = ?"
    );
    $stmt->bind_param('siss', $inv_id, $pr_id, $user_type_Loginvl, $user_id_Loginvl);
    $stmt->execute();
    $itemExists = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    if ($itemExists > 0) {
        echo "<script>window.location='invoice?InvoiceID=" . base64_encode($inv_id) . "&&ItemAlreadyExists&&AlertMessage';</script>";
        exit;
    }

    // Insert line item — stock deduction happens atomically in invoice-submit.php
    $stmt = $db_conn->prepare(
        "INSERT INTO invoice_items
            (inv_id, pr_id, amount, qty, total, user_type, user_id,
             gst_percentage, gstamount_singlepr, gstamount_total, subtotal,
             discount_percentage, discount_amount, gst_type, hsn, date, buyer_gsttype)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('siiidssddddddssss', $inv_id, $pr_id, $amount, $qty, $total,
                      $user_type_Loginvl, $user_id_Loginvl,
                      $gst_percentage, $gstamount_singlepr, $gstamount_total, $subtotal,
                      $discount_percentage, $discount_amount, $gst_type, $hsn, $date, $buyer_gsttype);
    $stmt->execute();
    $stmt->close();

    echo "<script>window.location='invoice?InvoiceID=" . base64_encode($inv_id) . "&&AddedSuccess&&FemiAdded';</script>";
}
?>
