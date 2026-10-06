<?php
/**
 * Loads and computes everything render_stockist_user_invoice_html()
 * (StockistUserInvoiceHtml.php) needs for one stockist-raised invoice —
 * DB queries, HSN totals, amount-in-words. Shared between
 * stockist/user-invoice-print.php (the logged-in Print page) and
 * stockist/user-invoice-pdf.php (the no-login PDF endpoint used for
 * WhatsApp sharing).
 *
 * The buyer can be a stockiest, super_distributor, or distributor —
 * user_invoice.to_user_type picks which table to join, same dynamic
 * lookup the original inline page used.
 *
 * Unlike SuperStockistUserInvoiceData.php, this page's original markup
 * trusted stored/frozen values rather than recomputing GST per line from
 * the product master: item rate = user_invoice_items.amount as-is (one
 * "Rate" column, no Excl./Incl. split), item GST% = the row's own stored
 * gst_percentage, and the invoice's total GST = SUM(gstamount_total)
 * across its items. Kept exactly as-is rather than silently upgrading to
 * the fresher-computation convention other invoice types use.
 *
 * Returns null if the invoice id doesn't resolve to a real invoice.
 */

require_once __DIR__ . '/number-format-helpers.php';
require_once __DIR__ . '/SuperStockistUserInvoiceData.php'; // for amount_in_words_simple()

function load_stockist_user_invoice_data($db_conn, string $Invoice_ID, int $st_id, string $crcode = ''): ?array {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));
    if (!$inv) {
        return null;
    }
    $getinvuser = $inv['to_user_type'] ?? '';

    // Invoice-level GST total, trusted from the frozen per-item
    // gstamount_total column (not recomputed), same as the original page.
    $gstRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(gstamount_total) AS t FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "'"));
    $totalgstamount  = (float)($gstRow['t'] ?? 0);
    $invoice_heading = $totalgstamount > 0 ? 'Tax Invoice' : 'Bill of Supply';

    // Seller (stockist) details
    $result_UserProfiles = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM users_profile WHERE user_tempid='$st_id' AND usertype='stockiest' LIMIT 1"));
    $result_UserdETAILS  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM stockiest WHERE temp_id='$st_id' LIMIT 1"));
    $business_address    = $result_UserdETAILS['address'] ?? '';
    $state_row_inv        = mysqli_fetch_array(mysqli_query($db_conn, "SELECT st_name FROM state WHERE id='" . (int)($result_UserdETAILS['state_id'] ?? 0) . "' LIMIT 1"));
    $state_nameINV        = $state_row_inv['st_name'] ?? '';

    // Buyer table depends on who the invoice was raised to
    $tablename = match ($getinvuser) {
        'stockiest'         => 'stockiest',
        'super_distributor' => 'super_distributor',
        'distributor'       => 'distributor',
        default             => null,
    };
    $customer_id          = $inv['to_user_id'] ?? '';
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
    $buyer_business_address = $result_Customer_Details['address'] ?? '';

    $result_userprofile = mysqli_fetch_array(mysqli_query($db_conn, "SELECT companyname, deliveryaddress FROM users_profile WHERE user_tempid='" . mysqli_real_escape_string($db_conn, $customer_id) . "' AND usertype='" . mysqli_real_escape_string($db_conn, $getinvuser) . "' LIMIT 1"));
    $resultstockreq      = mysqli_fetch_array(mysqli_query($db_conn, "SELECT delivery_address FROM stock_request WHERE reqid='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));
    $deliveryaddress      = !empty($resultstockreq['delivery_address']) ? $resultstockreq['delivery_address'] : ($result_userprofile['deliveryaddress'] ?? '');

    // Currency
    if ($crcode === 'Default' || $crcode === '') {
        $Currency_symbol    = "&#8377;";
        $Currency_Name      = "INR";
        $result_currency223 = null;
    } else {
        $get_ccode           = base64_decode($crcode);
        $result_currency223  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM country WHERE id='" . mysqli_real_escape_string($db_conn, $get_ccode) . "' LIMIT 1"));
        $Currency_symbol     = "&#" . $result_currency223['currency_ascii_code'] . ";";
        $Currency_Name       = $result_currency223['currency_name'];
    }

    $Result_DLDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM delivery_note WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));

    // Items — rate/GST%/discount all trusted as stored on each
    // user_invoice_items row, same as the original page (no per-line
    // inclusive/exclusive recompute).
    $items = mysqli_query($db_conn, "
        SELECT uii.*, p.productName, p.hsn AS p_hsn, p.mrp AS p_mrp
        FROM user_invoice_items uii
        JOIN products p ON p.id = uii.pr_id
        WHERE uii.inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' ORDER BY uii.id DESC
    ");
    $invoice_items  = [];
    $TotalAMount123 = 0; $Totalquantity123 = 0;
    $hsn_totals = [];
    while ($row = mysqli_fetch_array($items)) {
        $gross_amount = (float)$row['qty'] * (float)$row['amount'];
        $net_amount   = $gross_amount - (float)$row['discount_amount'];
        $row['line_total'] = $net_amount;
        $invoice_items[] = $row;

        $TotalAMount123   += $net_amount;
        $Totalquantity123 += (float)$row['qty'];
    }

    // HSN-wise totals (distinct HSN, summed `total` column) — same query
    // shape as the original page.
    $hsnRes = mysqli_query($db_conn, "SELECT DISTINCT hsn FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "'");
    while ($h = mysqli_fetch_array($hsnRes)) {
        $hsncode = $h['hsn'];
        $sumRow  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(total) AS t FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' AND hsn='" . mysqli_real_escape_string($db_conn, $hsncode) . "'"));
        $hsn_totals[$hsncode] = (float)($sumRow['t'] ?? 0);
    }
    $hsnTotalRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(total) AS t FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "'"));
    $hsn_grand_total = (float)($hsnTotalRow['t'] ?? 0);

    $gsttype = $inv['gst_type'] ?? '';
    $discountamount = (float)($inv['discount'] ?? 0) + (float)($inv['credit'] ?? 0);

    $result    = amount_in_words_simple((float)($inv['total'] ?? 0));
    $TAXresult = amount_in_words_simple($totalgstamount);

    return compact(
        'inv', 'getinvuser', 'result_UserProfiles', 'result_UserdETAILS', 'business_address', 'state_nameINV',
        'result_Customer_Details', 'state_name', 'district_name', 'buyer_business_address',
        'result_userprofile', 'deliveryaddress', 'Result_DLDetails',
        'Currency_symbol', 'Currency_Name', 'result_currency223',
        'invoice_items', 'TotalAMount123', 'Totalquantity123', 'totalgstamount',
        'hsn_totals', 'hsn_grand_total', 'invoice_heading', 'gsttype',
        'discountamount', 'result', 'TAXresult'
    );
}
