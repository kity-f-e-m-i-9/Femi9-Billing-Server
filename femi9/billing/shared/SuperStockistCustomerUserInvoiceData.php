<?php
/**
 * Loads and computes everything render_superstockist_customer_user_invoice_html()
 * (SuperStockistCustomerUserInvoiceHtml.php) needs for one super-stockist-
 * to-customer invoice — DB queries, GST computation, HSN totals, amount-in-
 * words. Shared between super-stockist/customer-user-invoice-print.php (the
 * logged-in Print page) and super-stockist/customer-user-invoice-pdf.php
 * (the no-login PDF endpoint used for WhatsApp sharing).
 *
 * Buyer here is a `customers` row looked up by `id` (not `temp_id`, unlike
 * shop/stockiest) — can be absent (customer_id = 0 means "Walking
 * Customer"), and shows no MRP/state/district columns, matching this
 * page's own previous inline markup.
 *
 * Returns null if the invoice id doesn't resolve to a real invoice.
 */

require_once __DIR__ . '/number-format-helpers.php';
require_once __DIR__ . '/SuperStockistUserInvoiceData.php'; // for amount_in_words_simple()

if (!function_exists('fmt_gst_pct')) {
    function fmt_gst_pct($v) {
        return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    }
}

function load_superstockist_customer_user_invoice_data($db_conn, string $Invoice_ID, int $ss_id, string $crcode = ''): ?array {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM invoice WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));
    if (!$inv) {
        return null;
    }

    $Result_DLDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM delivery_note WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));

    // Seller (super-stockist) details
    $result_UserProfiles = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM users_profile WHERE user_tempid='$ss_id' AND usertype='super_stockiest' LIMIT 1"));
    $result_UserdETAILS  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM super_stockiest WHERE temp_id='$ss_id' LIMIT 1"));
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

    // GST computed fresh from the product master at print time — same
    // convention as SuperStockistUserInvoiceData.php.
    $items = mysqli_query($db_conn, "
        SELECT ii.*, p.productName, p.hsn AS p_hsn, p.mrp AS p_mrp, p.gst AS p_gst, p.gst_type AS p_gst_type
        FROM invoice_items ii
        JOIN products p ON p.id = ii.pr_id
        WHERE ii.inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' ORDER BY ii.id DESC
    ");
    $invoice_items   = [];
    $TotalAMount123  = 0; $Totalquantity123 = 0; $totalgstamount = 0;
    $hsn_totals = []; $hsn_gst_totals = []; $hsn_gst_pct = []; $__inv_gst_pct = 0;
    while ($row = mysqli_fetch_array($items)) {
        $qty           = (int)$row['qty'];
        $gross_amount  = $qty * (float)$row['amount'];
        $item_disc_amt = (float)$row['discount_amount'];
        $net_amount    = $gross_amount - $item_disc_amt;
        $gst_pct       = (int)$row['p_gst'];
        $gst_type_item = $row['p_gst_type'] ?: 'exclusive';

        if ($gst_type_item === 'inclusive' && $gst_pct > 0) {
            $gross_taxable_value = $gross_amount * 100 / (100 + $gst_pct);
            $taxable_value       = $net_amount * 100 / (100 + $gst_pct);
            $gst_amount          = $net_amount - $taxable_value;
        } else {
            $gross_taxable_value = $gross_amount;
            $taxable_value       = $net_amount;
            $gst_amount          = $net_amount * $gst_pct / 100;
        }
        $row['taxable_value']     = $taxable_value;
        $row['gst_amount']        = $gst_amount;
        $row['taxable_rate']      = $qty > 0 ? $gross_taxable_value / $qty : 0;
        $row['taxable_rate_incl'] = $row['taxable_rate'] + ($gst_pct > 0 ? $row['taxable_rate'] * $gst_pct / 100 : 0);
        $row['gst_pct']           = $gst_pct;
        $invoice_items[] = $row;

        $TotalAMount123   += $taxable_value;
        $Totalquantity123 += $qty;
        $totalgstamount   += $gst_amount;
        $hsn = $row['p_hsn'] ?: '-';
        $hsn_totals[$hsn]     = ($hsn_totals[$hsn] ?? 0) + $taxable_value;
        $hsn_gst_totals[$hsn] = ($hsn_gst_totals[$hsn] ?? 0) + $gst_amount;
        $hsn_gst_pct[$hsn]    = $gst_pct;
        $__inv_gst_pct        = max($__inv_gst_pct, $gst_pct);
    }
    $has_gst_product = $totalgstamount > 0;
    $invoice_heading = $has_gst_product ? 'Tax Invoice' : 'Bill of Supply';
    $gsttype         = $inv['gst_type'] ?? '';
    $discountamount  = (float)($inv['discount'] ?? 0);

    $result    = amount_in_words_simple((float)($inv['total'] ?? 0));
    $TAXresult = amount_in_words_simple($totalgstamount);

    return compact(
        'inv', 'result_UserProfiles', 'result_UserdETAILS', 'business_address', 'state_nameINV',
        'customer_id', 'result_Customer_Details', 'Result_DLDetails',
        'Currency_symbol', 'Currency_Name', 'result_currency223',
        'invoice_items', 'TotalAMount123', 'Totalquantity123', 'totalgstamount', '__inv_gst_pct',
        'hsn_totals', 'hsn_gst_totals', 'hsn_gst_pct', 'has_gst_product', 'invoice_heading', 'gsttype',
        'discountamount', 'result', 'TAXresult'
    );
}
