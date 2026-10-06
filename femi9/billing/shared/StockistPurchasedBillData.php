<?php
/**
 * Loads and computes everything render_stockist_purchased_bill_html()
 * (StockistPurchasedBillHtml.php) needs for one super-stockist-to-stockist
 * purchase bill (the stockist is the BUYER here) — DB queries, HSN totals,
 * amount-in-words. Shared between stockist/purchased-bill-print.php (the
 * logged-in Print page) and stockist/purchased-bill-pdf.php (the no-login
 * PDF endpoint used for WhatsApp sharing).
 *
 * The seller is resolved from the invoice's own from_user_id/
 * from_user_type and always looked up in the super_stockiest table (this
 * page assumes the seller is always a super-stockist — the hierarchy chain
 * this app models is Company -> Super-Stockist -> Stockist), same as the
 * original inline page. Buyer can be stockiest or distributor (no
 * super_distributor/outlet option here, unlike super-stockist's own
 * purchased-bill). No currency selector on this page — amounts are always
 * plain INR.
 *
 * Returns null if the invoice id doesn't resolve to a real invoice.
 */

require_once __DIR__ . '/number-format-helpers.php';
require_once __DIR__ . '/SuperStockistUserInvoiceData.php'; // for amount_in_words_simple()

function load_stockist_purchased_bill_data($db_conn, string $Invoice_ID, int $st_id): ?array {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));
    if (!$inv) {
        return null;
    }

    $gstRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(gstamount_total) AS t FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "'"));
    $totalgstamount  = (float)($gstRow['t'] ?? 0);
    $invoice_heading = $totalgstamount > 0 ? 'Tax Invoice' : 'Bill of Supply';

    // Seller: always a super-stockist, resolved via the invoice's own
    // from_user_id (the FromUserTYpe column exists but, same as the
    // original page, isn't actually used to pick the table).
    $FromUserID = $inv['from_user_id'] ?? '';
    $result_UserProfiles = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM users_profile WHERE user_tempid='" . mysqli_real_escape_string($db_conn, $FromUserID) . "' AND usertype='super_stockiest' LIMIT 1"));
    $result_UserdETAILS  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM super_stockiest WHERE temp_id='" . mysqli_real_escape_string($db_conn, $FromUserID) . "' LIMIT 1"));
    $business_address    = $result_UserdETAILS['address'] ?? '';
    $state_row_inv        = mysqli_fetch_array(mysqli_query($db_conn, "SELECT st_name FROM state WHERE id='" . (int)($result_UserdETAILS['state_id'] ?? 0) . "' LIMIT 1"));
    $state_nameINV        = $state_row_inv['st_name'] ?? '';

    // Buyer: table depends on who the bill was raised to
    $getinvuser = $inv['to_user_type'] ?? '';
    $tablename = match ($getinvuser) {
        'stockiest'   => 'stockiest',
        'distributor' => 'distributor',
        default       => null,
    };
    $customer_id = $inv['to_user_id'] ?? '';
    $result_Customer_Details = $tablename
        ? mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM $tablename WHERE temp_id='" . mysqli_real_escape_string($db_conn, $customer_id) . "' LIMIT 1"))
        : null;

    $state_name    = '';
    $district_name = '';
    if ($result_Customer_Details) {
        $state_row    = mysqli_fetch_array(mysqli_query($db_conn, "SELECT st_name FROM state WHERE id='" . (int)($result_Customer_Details['state_id'] ?? 0) . "' LIMIT 1"));
        $state_name   = $state_row['st_name'] ?? '';
        $district_row = mysqli_fetch_array(mysqli_query($db_conn, "SELECT dist_name FROM district WHERE id='" . (int)($result_Customer_Details['district_id'] ?? 0) . "' LIMIT 1"));
        $district_name = $district_row['dist_name'] ?? '';
    }

    $result_userprofile = mysqli_fetch_array(mysqli_query($db_conn, "SELECT companyname, deliveryaddress FROM users_profile WHERE user_tempid='" . mysqli_real_escape_string($db_conn, $customer_id) . "' AND usertype='" . mysqli_real_escape_string($db_conn, $getinvuser) . "' LIMIT 1"));
    $resultstockreq      = mysqli_fetch_array(mysqli_query($db_conn, "SELECT delivery_address FROM stock_request WHERE reqid='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));
    $deliveryaddress      = !empty($resultstockreq['delivery_address']) ? $resultstockreq['delivery_address'] : ($result_userprofile['deliveryaddress'] ?? '');

    $Result_DLDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM delivery_note WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));

    $items = mysqli_query($db_conn, "SELECT * FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' ORDER BY id DESC");
    $invoice_items  = [];
    $TotalAMount123 = 0; $Totalquantity123 = 0;
    while ($row = mysqli_fetch_array($items)) {
        $product = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM products WHERE id='" . (int)$row['pr_id'] . "' LIMIT 1"));
        $gross_amount = (float)$row['qty'] * (float)$row['amount'];
        $net_amount   = $gross_amount - (float)$row['discount_amount'];

        $row['productName'] = $product['productName'] ?? '';
        $row['hsn']          = $product['hsn'] ?? '';
        $row['line_total']   = $net_amount;
        $invoice_items[] = $row;

        $TotalAMount123   += $net_amount;
        $Totalquantity123 += (float)$row['qty'];
    }

    $hsn_totals = [];
    $hsnRes = mysqli_query($db_conn, "SELECT DISTINCT hsn FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "'");
    while ($h = mysqli_fetch_array($hsnRes)) {
        $hsncode = $h['hsn'];
        $sumRow  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(total) AS t FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' AND hsn='" . mysqli_real_escape_string($db_conn, $hsncode) . "'"));
        $hsn_totals[$hsncode] = (float)($sumRow['t'] ?? 0);
    }
    $hsnTotalRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(total) AS t FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "'"));
    $hsn_grand_total = (float)($hsnTotalRow['t'] ?? 0);

    $gsttype        = $inv['gst_type'] ?? '';
    $discountamount = (float)($inv['discount'] ?? 0) + (float)($inv['credit'] ?? 0);

    $result    = amount_in_words_simple((float)($inv['total'] ?? 0));
    $TAXresult = amount_in_words_simple($totalgstamount);

    return compact(
        'inv', 'result_UserProfiles', 'result_UserdETAILS', 'business_address', 'state_nameINV',
        'getinvuser', 'result_Customer_Details', 'state_name', 'district_name',
        'result_userprofile', 'deliveryaddress', 'Result_DLDetails',
        'invoice_items', 'TotalAMount123', 'Totalquantity123', 'totalgstamount', 'gsttype', 'discountamount',
        'hsn_totals', 'hsn_grand_total', 'invoice_heading', 'result', 'TAXresult'
    );
}
