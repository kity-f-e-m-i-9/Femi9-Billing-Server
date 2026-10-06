<?php
/**
 * Loads and computes everything render_superstockist_user_invoice_html()
 * (SuperStockistUserInvoiceHtml.php) needs for one super-stockist-raised
 * invoice — DB queries, GST computation, HSN totals, amount-in-words.
 * Shared between super-stockist/user-invoice-print.php (the logged-in
 * Print page) and super-stockist/user-invoice-pdf.php (the no-login PDF
 * endpoint used for WhatsApp sharing) so both always show identical
 * figures.
 *
 * The buyer can be a stockiest, super_distributor, or distributor —
 * user_invoice.to_user_type picks which table to join, same dynamic
 * lookup the original inline page used.
 *
 * Returns null if the invoice id doesn't resolve to a real invoice.
 */

require_once __DIR__ . '/number-format-helpers.php';

if (!function_exists('fmt_gst_pct')) {
    function fmt_gst_pct($v) {
        return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    }
}

function load_superstockist_user_invoice_data($db_conn, string $Invoice_ID, int $ss_id, string $crcode = ''): ?array {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));
    if (!$inv) {
        return null;
    }
    $getinvuser = $inv['to_user_type'] ?? '';

    // Seller (super-stockist) details
    $result_UserProfiles = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM users_profile WHERE user_tempid='$ss_id' AND usertype='super_stockiest' LIMIT 1"));
    $result_UserdETAILS  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM super_stockiest WHERE temp_id='$ss_id' LIMIT 1"));
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

    // Buyer's own profile (company name / delivery address) and the
    // stock-request's one-off delivery address if the buyer typed one at
    // request time — same precedence the original inline page used.
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

    // Delivery note
    $Result_DLDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM delivery_note WHERE inv_id='" . mysqli_real_escape_string($db_conn, $Invoice_ID) . "' LIMIT 1"));

    // GST computed fresh from the product master at print time (inclusive
    // vs exclusive tax treatment), same convention as ShopInvoiceData.php —
    // not trusted from the gstamount_total value frozen on the invoice item
    // at add-time.
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
    $discountamount  = (float)($inv['discount'] ?? 0) + (float)($inv['credit'] ?? 0);

    // Amount in words
    $result = amount_in_words_simple((float)($inv['total'] ?? 0));
    $TAXresult = amount_in_words_simple($totalgstamount);

    return compact(
        'inv', 'getinvuser', 'result_UserProfiles', 'result_UserdETAILS', 'business_address', 'state_nameINV',
        'result_Customer_Details', 'state_name', 'district_name', 'buyer_business_address', 'deliveryaddress',
        'Currency_symbol', 'Currency_Name', 'result_currency223', 'Result_DLDetails',
        'invoice_items', 'TotalAMount123', 'Totalquantity123', 'totalgstamount', '__inv_gst_pct',
        'hsn_totals', 'hsn_gst_totals', 'hsn_gst_pct', 'has_gst_product', 'invoice_heading', 'gsttype',
        'discountamount', 'result', 'TAXresult'
    );
}

if (!function_exists('amount_in_words_simple')) {
    // Same number-to-words algorithm the original inline pages used
    // (duplicated across shop/customer/tp/super-stockist invoice pages
    // before this extraction) — kept byte-for-byte so wording doesn't
    // shift on any invoice.
    function amount_in_words_simple(float $number): string {
        $no = floor($number);
        $digits_1 = strlen((string)$no);
        $i = 0; $str = [];
        $words = ['0'=>'','1'=>'one','2'=>'two','3'=>'three','4'=>'four','5'=>'five','6'=>'six',
            '7'=>'seven','8'=>'eight','9'=>'nine','10'=>'ten','11'=>'eleven','12'=>'twelve',
            '13'=>'thirteen','14'=>'fourteen','15'=>'fifteen','16'=>'sixteen','17'=>'seventeen',
            '18'=>'eighteen','19'=>'nineteen','20'=>'twenty','30'=>'thirty','40'=>'forty',
            '50'=>'fifty','60'=>'sixty','70'=>'seventy','80'=>'eighty','90'=>'ninety'];
        $digits = ['','hundred','thousand','lakh','crore'];
        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number2 = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number2) {
                $plural  = (($counter = count($str)) && $number2 > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str[] = ($number2 < 21) ? $words[$number2] . " " . $digits[$counter] . $plural . " " . $hundred
                    : $words[floor($number2 / 10) * 10] . " " . $words[$number2 % 10] . " " . $digits[$counter] . $plural . " " . $hundred;
            } else {
                $str[] = null;
            }
        }
        return implode('', array_reverse($str));
    }
}
