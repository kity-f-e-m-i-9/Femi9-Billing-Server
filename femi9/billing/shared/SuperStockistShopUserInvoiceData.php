<?php
/**
 * Loads and computes everything render_superstockist_shop_user_invoice_html()
 * (SuperStockistShopUserInvoiceHtml.php) needs for one super-stockist-to-shop
 * invoice — DB queries, GST computation, HSN totals, amount-in-words. Shared
 * between super-stockist/shop-user-invoice-print.php (the logged-in Print
 * page) and super-stockist/shop-user-invoice-pdf.php (the no-login PDF
 * endpoint used for WhatsApp sharing) so both always show identical
 * figures.
 *
 * Same seller-side logic as SuperStockistUserInvoiceData.php, but the buyer
 * here is always a `shop` row (not dynamically stockiest/super_distributor/
 * distributor) — a separate pair rather than reusing that one, since the
 * buyer field shapes (and the discount calc, no `credit` added here) genuinely
 * differ.
 *
 * Returns null if the invoice id doesn't resolve to a real invoice.
 */

require_once __DIR__ . '/number-format-helpers.php';

if (!function_exists('fmt_gst_pct')) {
    function fmt_gst_pct($v) {
        return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    }
}
if (!function_exists('amount_in_words_simple')) {
    require_once __DIR__ . '/SuperStockistUserInvoiceData.php';
}

function load_superstockist_shop_user_invoice_data($db_conn, string $Invoice_ID, int $ss_id, string $crcode = ''): ?array {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));
    if (!$inv) {
        return null;
    }
    $getinvuser = $inv['to_user_type'] ?? 'shop';

    // Seller (super-stockist) details
    $result_UserProfiles = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM users_profile WHERE user_tempid='$ss_id' AND usertype='super_stockiest' LIMIT 1"));
    $result_UserdETAILS  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM super_stockiest WHERE temp_id='$ss_id' LIMIT 1"));
    $business_address    = $result_UserdETAILS['address'] ?? '';
    $state_row_inv        = mysqli_fetch_array(mysqli_query($db_conn, "SELECT st_name FROM state WHERE id='" . (int)($result_UserdETAILS['state_id'] ?? 0) . "' LIMIT 1"));
    $state_nameINV        = $state_row_inv['st_name'] ?? '';

    // Buyer: shop
    $customer_id = $inv['to_user_id'] ?? '';
    $result_Customer_Details = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM shop WHERE temp_id='" . mysqli_real_escape_string($db_conn, $customer_id) . "' LIMIT 1"));

    $state_name    = '';
    $district_name = '';
    if ($result_Customer_Details) {
        $state_row    = mysqli_fetch_array(mysqli_query($db_conn, "SELECT st_name FROM state WHERE id='" . (int)($result_Customer_Details['state_id'] ?? 0) . "' LIMIT 1"));
        $state_name   = $state_row['st_name'] ?? '';
        $district_row = mysqli_fetch_array(mysqli_query($db_conn, "SELECT dist_name FROM district WHERE id='" . (int)($result_Customer_Details['district_id'] ?? 0) . "' LIMIT 1"));
        $district_name = $district_row['dist_name'] ?? '';
    }

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

    // Delivery note
    $Result_DLDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM delivery_note WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));

    // GST computed fresh from the product master at print time — same
    // convention as SuperStockistUserInvoiceData.php.
    $items = mysqli_query($db_conn, "
        SELECT uii.*, p.productName, p.hsn AS p_hsn, p.mrp AS p_mrp, p.gst AS p_gst, p.gst_type AS p_gst_type
        FROM user_invoice_items uii
        JOIN products p ON p.id = uii.pr_id
        WHERE uii.inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' ORDER BY uii.id DESC
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
        'inv', 'getinvuser', 'result_UserProfiles', 'result_UserdETAILS', 'business_address', 'state_nameINV',
        'result_Customer_Details', 'state_name', 'district_name',
        'Currency_symbol', 'Currency_Name', 'result_currency223', 'Result_DLDetails',
        'invoice_items', 'TotalAMount123', 'Totalquantity123', 'totalgstamount', '__inv_gst_pct',
        'hsn_totals', 'hsn_gst_totals', 'hsn_gst_pct', 'has_gst_product', 'invoice_heading', 'gsttype',
        'discountamount', 'result', 'TAXresult'
    );
}
