<?php
/**
 * Loads and computes everything render_customer_invoice_html() (CustomerInvoiceHtml.php)
 * needs for one customer invoice — DB queries, GST computation, HSN totals,
 * amount-in-words. Shared between customer-invoice-print.php (the logged-in
 * Print page) and customer-invoice-pdf.php (the no-login PDF endpoint used
 * for WhatsApp sharing) so both always show identical figures.
 *
 * Mirrors ShopInvoiceData.php's load_shop_invoice_data() structure, but
 * against the invoice/invoice_items/customers tables used by the TP
 * "customer" invoice flow (as opposed to user_invoice/user_invoice_items/shop
 * used by the shop flow).
 *
 * Returns null if the invoice id doesn't resolve to a real invoice.
 */

require_once __DIR__ . '/number-format-helpers.php';

if (!function_exists('fmt_gst_pct')) {
    // Trims a rate like 1.50 down to "1.5" or 9.00 down to "9" — CGST/SGST
    // is always exactly half the item's GST% which is often a non-whole
    // number (e.g. 3% GST -> 1.5% + 1.5%).
    function fmt_gst_pct($v) {
        return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    }
}

function load_customer_invoice_data($db_conn, string $Invoice_ID, int $tp_id, string $crcode = ''): ?array {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM invoice WHERE inv_id='$Invoice_ID' LIMIT 1"));
    if (!$inv) {
        return null;
    }

    // Seller: territory_partners + profile
    $seller  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM territory_partners WHERE id='$tp_id' LIMIT 1"));
    $profile = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM users_profile WHERE user_tempid='$tp_id' AND usertype='territory_partner' LIMIT 1"));
    $seller_display_name = $profile['companyname'] ?? $seller['company_name'] ?? $seller['name'] ?? '';

    // Buyer: customers
    $customer_id = $inv['customer_id'] ?? 0;
    $buyer       = $customer_id ? mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM customers WHERE id='$customer_id' LIMIT 1")) : null;

    // Currency
    if ($crcode === "Default" || $crcode === '') {
        $Currency_symbol    = "&#8377;";
        $Currency_Name      = "INR";
        $result_currency223 = null;
    } else {
        $get_ccode           = base64_decode($crcode);
        $result_currency223  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM country WHERE id='$get_ccode' LIMIT 1"));
        $Currency_symbol     = "&#" . $result_currency223['currency_ascii_code'] . ";";
        $Currency_Name       = $result_currency223['currency_name'];
    }

    // Delivery note
    $Result_DLDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM delivery_note WHERE inv_id='$Invoice_ID' LIMIT 1"));

    // GST computed fresh from the product master at print time (inclusive vs
    // exclusive tax treatment), the same convention as tp-invoice-print.php —
    // not trusted from the gstamount_total value frozen on the invoice item
    // at add-time (which was always calculated as if every product were
    // exclusive).
    $select_INVProductDetails = "select ii.*, p.productName, p.hsn as p_hsn, p.mrp as p_mrp, p.gst as p_gst, p.gst_type as p_gst_type
        from invoice_items ii
        join products p on p.id = ii.pr_id
        where ii.inv_id='$Invoice_ID' order by ii.id desc";
    $fetch_INVProductDetails = mysqli_query($db_conn, $select_INVProductDetails);
    $invoice_items  = [];
    $TotalAMount123 = 0; $Totalquantity123 = 0; $totalgstamount = 0;
    $hsn_totals = []; $hsn_gst_totals = []; $hsn_gst_pct = []; $__inv_gst_pct = 0;
    while ($row = mysqli_fetch_array($fetch_INVProductDetails)) {
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
        $row['gst_type_item']     = $gst_type_item;
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
    $gsttype         = $inv['gst_type'];

    $result    = number_to_words_inr($inv['total'] ?? 0);
    $TAXresult = number_to_words_inr($totalgstamount);

    return compact(
        'inv', 'seller', 'buyer', 'profile', 'seller_display_name',
        'Currency_symbol', 'Currency_Name', 'result_currency223',
        'Result_DLDetails',
        'invoice_items', 'TotalAMount123', 'Totalquantity123', 'totalgstamount',
        'hsn_totals', 'hsn_gst_totals', 'hsn_gst_pct', '__inv_gst_pct',
        'has_gst_product', 'invoice_heading', 'gsttype', 'result', 'TAXresult'
    );
}
