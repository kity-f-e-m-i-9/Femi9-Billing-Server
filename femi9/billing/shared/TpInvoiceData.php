<?php
/**
 * Loads and computes everything render_tp_invoice_html() (TpInvoiceHtml.php)
 * needs for one TP invoice (a company-issued invoice billed to a Territory
 * Partner) — DB queries, GST computation, HSN totals, carton breakdown,
 * amount-in-words. Shared between company/tp-invoice-print.php (the
 * logged-in Print page) and company/tp-invoice-pdf.php (the no-login PDF
 * endpoint used for WhatsApp sharing) so both always show identical figures.
 *
 * Returns null if the invoice id doesn't resolve to a real invoice.
 */

require_once __DIR__ . '/number-format-helpers.php';
require_once __DIR__ . '/TpProductType.php';

if (!function_exists('fmt_gst_pct')) {
    // Trims a rate like 1.50 down to "1.5" or 9.00 down to "9" — CGST/SGST
    // is always exactly half the item's GST% which is often a non-whole
    // number (e.g. 3% GST -> 1.5% + 1.5%).
    function fmt_gst_pct($v) {
        return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    }
}

function load_tp_invoice_data($db_conn, int $inv_id): ?array {
    // Invoice header
    $stmt = $db_conn->prepare("
        SELECT tpi.*,
               tp.name AS tp_name, tp.company_name AS tp_company_name, tp.tp_id AS tp_code, tp.mobile AS tp_mobile, tp.gstin AS tp_gstin,
               tp.branch_line1, tp.branch_line2, tp.branch_city, tp.branch_district, tp.branch_state, tp.branch_country, tp.branch_pincode,
               tp.delivery_line1, tp.delivery_line2, tp.delivery_city, tp.delivery_district, tp.delivery_state, tp.delivery_country, tp.delivery_pincode,
               COALESCE(cp_src.name, gd.gname, pln.name) AS source_location,
               COALESCE(cp_src.name, cp_old.name) AS cp_name,
               COALESCE(cp_src.cp_id, cp_old.cp_id) AS cp_code,
               COALESCE(cp_src.branch_district, cp_old.branch_district) AS cp_district,
               COALESCE(cp_src.company_name, cp_old.company_name) AS cp_company_name,
               COALESCE(cp_src.gstin, cp_old.gstin) AS cp_gstin,
               COALESCE(cp_src.mobile, cp_old.mobile) AS cp_mobile,
               COALESCE(cp_src.branch_line1, cp_old.branch_line1) AS cp_branch_line1,
               COALESCE(cp_src.branch_line2, cp_old.branch_line2) AS cp_branch_line2,
               COALESCE(cp_src.branch_city, cp_old.branch_city) AS cp_branch_city,
               COALESCE(cp_src.branch_district, cp_old.branch_district) AS cp_branch_district_full,
               COALESCE(cp_src.branch_state, cp_old.branch_state) AS cp_branch_state,
               COALESCE(cp_src.branch_country, cp_old.branch_country) AS cp_branch_country,
               COALESCE(cp_src.gst_enabled, cp_old.gst_enabled, 0) AS cp_gst_enabled
        FROM tp_invoices tpi
        JOIN territory_partners tp              ON tp.id = tpi.territory_partner_id
        LEFT JOIN partner_location_nodes pln    ON pln.id = tpi.source_location_id
        LEFT JOIN channel_partner_locations cpl ON cpl.location_id = tpi.source_location_id
        LEFT JOIN channel_partners cp_old       ON cp_old.id = cpl.channel_partner_id
        LEFT JOIN channel_partners cp_src       ON cp_src.id = tpi.source_cp_id
        LEFT JOIN company_godown gd             ON gd.id = tpi.source_godown_id AND (" . godown_finance_filter_sql($db_conn, 'gd') . ")
        WHERE tpi.id = ?
    ");
    $stmt->bind_param("i", $inv_id);
    $stmt->execute();
    $result_Invoice_Details = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$result_Invoice_Details) {
        return null;
    }

    // Godown — use the invoice's source_godown_id if set, else fall back to primary godown
    $result_Godown = null;
    $_gd_id = (int)($result_Invoice_Details['source_godown_id'] ?? 0);
    if ($_gd_id && is_godown_allowed($db_conn, $_gd_id)) {
        $_gd_stmt = $db_conn->prepare("SELECT * FROM company_godown WHERE id = ? LIMIT 1");
        $_gd_stmt->bind_param("i", $_gd_id);
        $_gd_stmt->execute();
        $result_Godown = $_gd_stmt->get_result()->fetch_assoc();
        $_gd_stmt->close();
    }
    if (empty($result_Godown)) {
        $result_Godown = $db_conn->query("SELECT * FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " LIMIT 1")->fetch_assoc();
    }

    // Line items with product details
    $stmt2 = $db_conn->prepare("
        SELECT tpii.quantity, tpii.rate, tpii.amount, tpii.discount_percentage, tpii.discount_amount,
               p.productName, p.hsn, p.gst AS gst_percentage, p.gst_type, p.mrp, p.packs_per_carton
        FROM tp_invoice_items tpii
        JOIN products p ON p.id = tpii.product_id
        WHERE tpii.tp_invoice_id = ?
        ORDER BY tpii.id
    ");
    $stmt2->bind_param("i", $inv_id);
    $stmt2->execute();
    $invoice_items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    // Totals
    $TotalAMount123   = 0; // sum of taxable values (exclusive of GST)
    $Totalquantity123 = 0;
    $totalgstamount   = 0;
    $hsn_totals       = []; // hsn => taxable sum
    $hsn_gst_totals   = []; // hsn => gst amount sum
    $hsn_gst_pct      = []; // hsn => gst rate (assumed uniform per HSN)
    $__inv_gst_pct    = 0;  // MAX gst rate across all lines — for the SGST/CGST label below, not a per-HSN figure
    foreach ($invoice_items as &$item) {
        $gross_amount  = (float)$item['amount'];
        $item_disc_amt = (float)($item['discount_amount'] ?? 0);
        $net_amount    = $gross_amount - $item_disc_amt; // what's actually billed for this line
        $gst_pct       = (int)$item['gst_percentage'];
        $gst_type      = $item['gst_type'] ?? 'exclusive';

        if ($gst_type === 'inclusive' && $gst_pct > 0) {
            // Rate column keeps showing the pre-discount per-unit rate, so its taxable
            // portion is carved out of the gross amount, not the discounted one.
            $gross_taxable_value = $gross_amount * 100 / (100 + $gst_pct);
            $taxable_value       = $net_amount * 100 / (100 + $gst_pct);
            $gst_amount          = $net_amount - $taxable_value;
        } else {
            $gross_taxable_value = $gross_amount;
            $taxable_value       = $net_amount;
            $gst_amount          = $net_amount * $gst_pct / 100;
        }
        $item['taxable_value'] = $taxable_value;
        $item['gst_amount']    = $gst_amount;
        $item['taxable_rate']  = ((int)$item['quantity'] > 0) ? $gross_taxable_value / (int)$item['quantity'] : 0;
        $item['taxable_rate_incl'] = $item['taxable_rate'] + ($gst_pct > 0 ? $item['taxable_rate'] * $gst_pct / 100 : 0);

        $TotalAMount123   += $taxable_value;
        $Totalquantity123 += (int)$item['quantity'];
        $totalgstamount   += $gst_amount;
        $hsn = $item['hsn'] ?: '-';
        $hsn_totals[$hsn]     = ($hsn_totals[$hsn] ?? 0) + $taxable_value;
        $hsn_gst_totals[$hsn] = ($hsn_gst_totals[$hsn] ?? 0) + $gst_amount;
        $hsn_gst_pct[$hsn]    = $gst_pct;
        $__inv_gst_pct        = max($__inv_gst_pct, $gst_pct);
    }
    unset($item);

    // Carton breakdown per line — packs_per_carton is optional per-product metadata;
    // when unset for a product, that line is shown as '—' and excluded from the cartons total.
    $TotalCartons123 = 0;
    $has_carton_data = false;
    foreach ($invoice_items as &$item) {
        $ppc = $item['packs_per_carton'];
        $item['carton_display'] = '—';
        if ($ppc !== null && $ppc !== '' && (int)$ppc > 0) {
            $has_carton_data = true;
            $ppc_int  = (int)$ppc;
            $qty      = (int)$item['quantity'];
            $cartons  = intdiv($qty, $ppc_int);
            $leftover = $qty % $ppc_int;
            $TotalCartons123 += $cartons;
            $item['carton_display'] = $cartons . ' ctn' . ($leftover > 0 ? ' + ' . $leftover . ' pack' . ($leftover > 1 ? 's' : '') : '');
        }
    }
    unset($item);

    $courier_charges  = (float)$result_Invoice_Details['courier_charges'];
    $discount_amount  = (float)($result_Invoice_Details['discount_amount'] ?? 0);
    $grand_total      = (float)$result_Invoice_Details['total_amount'];
    $has_gst_product  = $totalgstamount > 0;
    $invoice_heading  = $has_gst_product ? 'Tax Invoice' : 'Bill of Supply';

    $result    = number_to_words_inr($grand_total);
    $TAXresult = number_to_words_inr($totalgstamount);
    // Paise portion — without this, a tax amount under ₹1 (e.g. ₹0.73, common
    // on small-value lines) has floor(totalgstamount)=0, so $TAXresult comes
    // out empty and "Tax Amount (in words)" prints as just "INR  Only" with
    // nothing in it.
    $TAXpaise = (int) round(($totalgstamount - floor($totalgstamount)) * 100);
    $TAXpaise_words = '';
    if ($TAXpaise > 0) {
        $words = ['0'=>'','1'=>'one','2'=>'two','3'=>'three','4'=>'four','5'=>'five','6'=>'six','7'=>'seven','8'=>'eight','9'=>'nine','10'=>'ten','11'=>'eleven','12'=>'twelve','13'=>'thirteen','14'=>'fourteen','15'=>'fifteen','16'=>'sixteen','17'=>'seventeen','18'=>'eighteen','19'=>'nineteen','20'=>'twenty','30'=>'thirty','40'=>'forty','50'=>'fifty','60'=>'sixty','70'=>'seventy','80'=>'eighty','90'=>'ninety'];
        $TAXpaise_words = ($TAXpaise < 21)
            ? $words[$TAXpaise]
            : trim($words[floor($TAXpaise / 10) * 10] . " " . $words[$TAXpaise % 10]);
    }
    if (trim($TAXresult) !== '' && $TAXpaise_words !== '') {
        $TAXresult = trim($TAXresult) . ' Rupees and ' . $TAXpaise_words . ' Paise';
    } elseif ($TAXpaise_words !== '') {
        $TAXresult = $TAXpaise_words . ' Paise';
    } elseif (trim($TAXresult) === '') {
        $TAXresult = 'Zero';
    }

    $Currency_symbol = "&#8377;";
    $Currency_Name   = "INR";

    return compact(
        'result_Invoice_Details', 'result_Godown', 'invoice_items',
        'TotalAMount123', 'Totalquantity123', 'totalgstamount',
        'hsn_totals', 'hsn_gst_totals', 'hsn_gst_pct', '__inv_gst_pct',
        'TotalCartons123', 'has_carton_data',
        'courier_charges', 'discount_amount', 'grand_total',
        'has_gst_product', 'invoice_heading', 'result', 'TAXresult',
        'Currency_symbol', 'Currency_Name'
    );
}
