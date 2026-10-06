<?php
/**
 * Loads and computes everything render_stockist_customer_user_invoice_html()
 * (StockistCustomerUserInvoiceHtml.php) needs for one stockist-to-customer
 * invoice — DB queries, HSN totals, amount-in-words. Shared between
 * stockist/customer-user-invoice-print.php (the logged-in Print page) and
 * stockist/customer-user-invoice-pdf.php (the no-login PDF endpoint used
 * for WhatsApp sharing).
 *
 * Buyer here is a `customers` row looked up by `id` (not `temp_id`), with a
 * Walking Customer fallback when customer_id=0, mobile field is `mobile`
 * (not `mobile_number`), no MRP column, single "Rate" column (= stored
 * invoice_items.amount), discount = $inv['discount'] alone, GST total
 * trusted from frozen gstamount_total, bank-details unconditional — same
 * old-style convention as StockistUserInvoiceData.php/
 * StockistShopUserInvoiceData.php, kept exactly as the original page
 * rendered it.
 *
 * Returns null if the invoice id doesn't resolve to a real invoice.
 */

require_once __DIR__ . '/number-format-helpers.php';
require_once __DIR__ . '/SuperStockistUserInvoiceData.php'; // for amount_in_words_simple()

function load_stockist_customer_user_invoice_data($db_conn, string $Invoice_ID, int $st_id, string $crcode = ''): ?array {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM invoice WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));
    if (!$inv) {
        return null;
    }

    $gstRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(gstamount_total) AS t FROM invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "'"));
    $totalgstamount  = (float)($gstRow['t'] ?? 0);
    $invoice_heading = $totalgstamount > 0 ? 'Tax Invoice' : 'Bill of Supply';

    $Result_DLDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM delivery_note WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));

    // Seller (stockist) details
    $result_UserProfiles = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM users_profile WHERE user_tempid='$st_id' AND usertype='stockiest' LIMIT 1"));
    $result_UserdETAILS  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM stockiest WHERE temp_id='$st_id' LIMIT 1"));
    $business_address    = $result_UserdETAILS['address'] ?? '';
    $state_row_inv        = mysqli_fetch_array(mysqli_query($db_conn, "SELECT st_name FROM state WHERE id='" . (int)($result_UserdETAILS['state_id'] ?? 0) . "' LIMIT 1"));
    $state_nameINV        = $state_row_inv['st_name'] ?? '';

    // Buyer: customers (id, not temp_id) — 0 means Walking Customer
    $customer_id = (int)($inv['customer_id'] ?? 0);
    $result_Customer_Details = $customer_id
        ? mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM customers WHERE id='$customer_id' LIMIT 1"))
        : null;

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

    $items = mysqli_query($db_conn, "
        SELECT ii.*, p.productName, p.hsn AS p_hsn
        FROM invoice_items ii
        JOIN products p ON p.id = ii.pr_id
        WHERE ii.inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' ORDER BY ii.id DESC
    ");
    $invoice_items  = [];
    $TotalAMount123 = 0; $Totalquantity123 = 0;
    while ($row = mysqli_fetch_array($items)) {
        $gross_amount = (float)$row['qty'] * (float)$row['amount'];
        $net_amount   = $gross_amount - (float)$row['discount_amount'];
        $row['line_total'] = $net_amount;
        $invoice_items[] = $row;

        $TotalAMount123   += $net_amount;
        $Totalquantity123 += (float)$row['qty'];
    }

    $hsn_totals = [];
    $hsnRes = mysqli_query($db_conn, "SELECT DISTINCT hsn FROM invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "'");
    while ($h = mysqli_fetch_array($hsnRes)) {
        $hsncode = $h['hsn'];
        $sumRow  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(total) AS t FROM invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' AND hsn='" . mysqli_real_escape_string($db_conn, $hsncode) . "'"));
        $hsn_totals[$hsncode] = (float)($sumRow['t'] ?? 0);
    }
    $hsnTotalRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(total) AS t FROM invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "'"));
    $hsn_grand_total = (float)($hsnTotalRow['t'] ?? 0);

    $gsttype = $inv['gst_type'] ?? '';
    $discountamount = (float)($inv['discount'] ?? 0);

    $result    = amount_in_words_simple((float)($inv['total'] ?? 0));
    $TAXresult = amount_in_words_simple($totalgstamount);

    return compact(
        'inv', 'result_UserProfiles', 'result_UserdETAILS', 'business_address', 'state_nameINV',
        'customer_id', 'result_Customer_Details', 'Result_DLDetails',
        'Currency_symbol', 'Currency_Name', 'result_currency223',
        'invoice_items', 'TotalAMount123', 'Totalquantity123', 'totalgstamount',
        'hsn_totals', 'hsn_grand_total', 'invoice_heading', 'gsttype',
        'discountamount', 'result', 'TAXresult'
    );
}
