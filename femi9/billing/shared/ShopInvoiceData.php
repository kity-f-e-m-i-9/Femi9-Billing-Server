<?php
/**
 * Loads and computes everything render_shop_invoice_html() (ShopInvoiceHtml.php)
 * needs for one shop invoice — DB queries, GST computation, HSN totals,
 * amount-in-words. Shared between shop-invoice-print.php (the logged-in
 * Print page) and shop-invoice-pdf.php (the no-login PDF endpoint used for
 * WhatsApp sharing) so both always show identical figures.
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

function load_shop_invoice_data($db_conn, string $Invoice_ID, int $tp_id, string $crcode = ''): ?array {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='$Invoice_ID' LIMIT 1"));
    if (!$inv) {
        return null;
    }
    $getinvuser = $inv['to_user_type'] ?? 'shop';

    // TP (seller) details
    $tpRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM territory_partners WHERE id='$tp_id' LIMIT 1"));

    // Customer (shop) details
    $customer_id  = $inv['to_user_id'] ?? '';
    $shop         = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM shop WHERE temp_id='$customer_id' LIMIT 1"));
    // shop.state_id is a `state` table id, NOT a partner_location_nodes id —
    // the two id spaces overlap numerically (e.g. state.id=7 is Tamilnadu,
    // but partner_location_nodes.id=7 is Kanchipuram), so looking state_id up
    // in partner_location_nodes silently shows the wrong state/district name
    // on the printed invoice. shop.district_id IS a partner_location_nodes id
    // (that one's correct as-is).
    $state_row    = mysqli_fetch_array(mysqli_query($db_conn, "SELECT st_name AS name FROM state WHERE id='{$shop['state_id']}' LIMIT 1"));
    $state_name   = $state_row['name'] ?? '';
    $district_row = mysqli_fetch_array(mysqli_query($db_conn, "SELECT name FROM partner_location_nodes WHERE id='{$shop['district_id']}' LIMIT 1"));
    $district_name = $district_row['name'] ?? '';

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

    // Profile / logo
    $profile = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM users_profile WHERE user_tempid='$tp_id' AND usertype='territory_partner' LIMIT 1"));
    $seller_display_name = $profile['companyname'] ?? $tpRow['company_name'] ?? $tpRow['name'] ?? '';

    // GST computed fresh from the product master at print time (inclusive vs
    // exclusive tax treatment), the same convention as tp-invoice-print.php —
    // not trusted from the gstamount_total value frozen on the invoice item
    // at add-time (which was always calculated as if every product were
    // exclusive).
    $select_INVProductDetails = "select uii.*, p.productName, p.hsn as p_hsn, p.mrp as p_mrp, p.gst as p_gst, p.gst_type as p_gst_type
        from user_invoice_items uii
        join products p on p.id = uii.pr_id
        where uii.inv_id='$Invoice_ID' order by uii.id desc";
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
        'inv', 'getinvuser', 'tpRow', 'shop', 'state_name', 'district_name',
        'Currency_symbol', 'Currency_Name', 'result_currency223',
        'Result_DLDetails', 'profile', 'seller_display_name',
        'invoice_items', 'TotalAMount123', 'Totalquantity123', 'totalgstamount',
        'hsn_totals', 'hsn_gst_totals', 'hsn_gst_pct', '__inv_gst_pct',
        'has_gst_product', 'invoice_heading', 'gsttype', 'result', 'TAXresult'
    );
}
