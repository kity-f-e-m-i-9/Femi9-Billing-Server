<?php
/**
 * Loads and computes everything render_superstockist_purchased_bill_html()
 * (SuperStockistPurchasedBillHtml.php) needs for one company-to-super-
 * stockist purchase bill (the super-stockist is the BUYER here, not the
 * seller) — DB queries, GST computation, HSN totals, amount-in-words.
 * Shared between super-stockist/purchased-bill-print.php (the logged-in
 * Print page) and super-stockist/purchased-bill-pdf.php (the no-login PDF
 * endpoint used for WhatsApp sharing).
 *
 * The buyer row can be in super_stockiest/stockiest/distributor/outlet
 * depending on to_user_type, same dynamic lookup the original inline page
 * used. There's no currency selector on this page (unlike the sibling
 * invoice pages) — amounts are always plain INR, matching the original.
 *
 * Returns null if the invoice id doesn't resolve to a real invoice.
 */

require_once __DIR__ . '/number-format-helpers.php';
require_once __DIR__ . '/SuperStockistUserInvoiceData.php'; // for amount_in_words_simple()

function load_superstockist_purchased_bill_data($db_conn, string $Invoice_ID, int $ss_id): ?array {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));
    if (!$inv) {
        return null;
    }

    // Seller: the company godown that raised this bill
    $from_user_id  = $inv['from_user_id'] ?? '';
    $result_Godown = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM company_godown WHERE id='" . mysqli_real_escape_string($db_conn, $from_user_id) . "' LIMIT 1"));

    // Buyer: table depends on who the bill was raised to
    $getinvuser = $inv['to_user_type'] ?? '';
    $tablename = match ($getinvuser) {
        'super_stockiest' => 'super_stockiest',
        'stockiest'        => 'stockiest',
        'distributor'      => 'distributor',
        'outlet'           => 'outlet',
        default            => null,
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

    // Buyer's own profile (company name) and the stock-request's one-off
    // delivery address if typed at request time — same precedence the
    // original inline page used.
    $result_userprofile = mysqli_fetch_array(mysqli_query($db_conn, "SELECT companyname, deliveryaddress FROM users_profile WHERE user_tempid='" . mysqli_real_escape_string($db_conn, $customer_id) . "' AND usertype='" . mysqli_real_escape_string($db_conn, $getinvuser) . "' LIMIT 1"));
    $resultstockreq      = mysqli_fetch_array(mysqli_query($db_conn, "SELECT delivery_address FROM stock_request WHERE reqid='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));
    $deliveryaddress      = !empty($resultstockreq['delivery_address']) ? $resultstockreq['delivery_address'] : ($result_userprofile['deliveryaddress'] ?? '');

    $Result_DLDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM delivery_note WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));

    // Items — GST computed from the invoice item's own stored rate/amount
    // and the product's gst_type, same convention as the original page
    // (not the "fresh from product master" convention the newer invoice
    // pages use, since this one trusts the frozen gst_percentage already
    // on each user_invoice_items row).
    $items = mysqli_query($db_conn, "SELECT * FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' ORDER BY id DESC");
    $invoice_items  = [];
    $TotalAMount123 = 0; $Totalquantity123 = 0;
    while ($row = mysqli_fetch_array($items)) {
        $product = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM products WHERE id='" . (int)$row['pr_id'] . "' LIMIT 1"));

        $gross_amount  = (float)$row['qty'] * (float)$row['amount'];
        $net_amount    = $gross_amount - (float)$row['discount_amount'];
        $gst_pct       = (float)$row['gst_percentage'];
        $gst_type_item = $product['gst_type'] ?: 'exclusive';
        $rate_excl     = ($gst_type_item === 'inclusive' && $gst_pct > 0)
            ? (float)$row['amount'] * 100 / (100 + $gst_pct)
            : (float)$row['amount'];
        $rate_incl     = $rate_excl + ($gst_pct > 0 ? $rate_excl * $gst_pct / 100 : 0);

        $row['productName'] = $product['productName'] ?? '';
        $row['hsn']          = $product['hsn'] ?? '';
        $row['rate_excl']    = $rate_excl;
        $row['rate_incl']    = $rate_incl;
        $row['line_total']   = $net_amount;
        $invoice_items[] = $row;

        $TotalAMount123   += $net_amount;
        $Totalquantity123 += (float)$row['qty'];
    }

    // GST total trusted from the frozen gstamount_total on each item (same
    // convention the original page used here — unlike the fresh-computed
    // convention above for the per-line rate split).
    $gstRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(gstamount_total) AS t FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "'"));
    $totalgstamount = (float)($gstRow['t'] ?? 0);
    $gsttype        = $inv['gst_type'] ?? '';
    $discountamount = (float)($inv['discount'] ?? 0) + (float)($inv['credit'] ?? 0);

    // HSN-wise totals (distinct HSN, summed `total` column) — same query
    // shape as the original page.
    $hsn_totals = [];
    $hsnRes = mysqli_query($db_conn, "SELECT DISTINCT hsn FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "'");
    while ($h = mysqli_fetch_array($hsnRes)) {
        $hsncode = $h['hsn'];
        $sumRow  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(total) AS t FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' AND hsn='" . mysqli_real_escape_string($db_conn, $hsncode) . "'"));
        $hsn_totals[$hsncode] = (float)($sumRow['t'] ?? 0);
    }
    $hsnTotalRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(total) AS t FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "'"));
    $hsn_grand_total = (float)($hsnTotalRow['t'] ?? 0);

    $result    = amount_in_words_simple((float)($inv['total'] ?? 0));
    $TAXresult = amount_in_words_simple($totalgstamount);

    return compact(
        'inv', 'result_Godown', 'getinvuser', 'result_Customer_Details', 'state_name', 'district_name',
        'result_userprofile', 'deliveryaddress', 'Result_DLDetails',
        'invoice_items', 'TotalAMount123', 'Totalquantity123', 'totalgstamount', 'gsttype', 'discountamount',
        'hsn_totals', 'hsn_grand_total', 'result', 'TAXresult'
    );
}
