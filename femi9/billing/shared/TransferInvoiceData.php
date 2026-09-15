<?php
/**
 * Loads and computes everything render_transfer_invoice_html()
 * (TransferInvoiceHtml.php) needs for one internal stock transfer, rendered
 * as a full GST tax invoice — DB queries, GST computation, HSN totals,
 * carton breakdown, amount-in-words. Mirrors TpInvoiceData.php's shape and
 * computation exactly (see docs/superpowers/specs/2026-09-15-transfer-tax-invoice-design.md),
 * with one structural difference: pl_godown_transfer_items has no stored
 * rate/amount/discount, so every line's rate is looked up fresh from
 * products.mrp at print time instead of being read from a stored invoice
 * line. There is also no discount or courier-charge concept for a transfer,
 * so those sections of TP's template are simply never populated here.
 *
 * Returns null if the transfer id doesn't resolve to a real transfer.
 */

require_once __DIR__ . '/number-format-helpers.php';
require_once __DIR__ . '/../company/include/GodownAccess.php';

if (!function_exists('fmt_gst_pct')) {
    // Trims a rate like 1.50 down to "1.5" or 9.00 down to "9" — CGST/SGST
    // is always exactly half the item's GST% which is often a non-whole
    // number (e.g. 3% GST -> 1.5% + 1.5%). Same helper TpInvoiceData.php
    // defines, guarded the same way so both can be required in the same
    // request without a redeclaration fatal.
    function fmt_gst_pct($v) {
        return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    }
}

function load_transfer_invoice_data(mysqli $db_conn, int $transfer_id): ?array {
    // Transfer header + seller (godown) + buyer (CP or location — mutually
    // exclusive per row, same pattern already fixed in
    // pl-godown-transfer-print.php's own header query).
    $stmt = $db_conn->prepare("
        SELECT t.id AS transfer_id, t.ref_number, t.transfer_date, t.created_by, t.created_at,
               g.gname, g.address_line1, g.address_line2, g.gstin, g.state, g.state_code,
               g.contact, g.email, g.logo, g.acname, g.acnumber, g.bankname, g.branchname,
               g.ifsc, g.upinumber,
               cp.id AS cp_id, cp.name AS cp_name, cp.gstin AS cp_gstin, cp.mobile AS cp_mobile,
               cp.branch_line1 AS cp_branch_line1, cp.branch_line2 AS cp_branch_line2,
               cp.branch_city AS cp_branch_city, cp.branch_district AS cp_branch_district,
               cp.branch_state AS cp_branch_state, cp.branch_country AS cp_branch_country,
               cp.branch_pincode AS cp_branch_pincode,
               pln.name AS location_name
        FROM pl_godown_transfers t
        JOIN company_godown g ON g.id = t.godown_id AND (" . godown_finance_filter_sql($db_conn, 'g') . ")
        LEFT JOIN channel_partners cp ON cp.id = t.cp_id
        LEFT JOIN partner_location_nodes pln ON pln.id = t.location_id
        WHERE t.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $transfer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    $is_cp_buyer = !empty($row['cp_id']);
    if ($is_cp_buyer) {
        $buyer_name  = $row['cp_name'];
        $buyer_gstin = $row['cp_gstin'];
        $buyer_mobile = $row['cp_mobile'];
        $buyer_address_parts = array_filter([
            $row['cp_branch_line1'],
            $row['cp_branch_line2'],
            implode(', ', array_filter([$row['cp_branch_city'], $row['cp_branch_district']])),
            implode(', ', array_filter([$row['cp_branch_state'], $row['cp_branch_country']])),
            !empty($row['cp_branch_pincode']) ? 'Pincode: ' . $row['cp_branch_pincode'] : '',
        ]);
    } else {
        $buyer_name  = $row['location_name'];
        $buyer_gstin = null;
        $buyer_mobile = null;
        $buyer_address_parts = [];
    }

    // Neither a CP nor a resolvable location — both cp_id/location_id are
    // NULL, or location_id points at a deleted row (both joins above are
    // LEFT JOINs, so that doesn't fail the query, it just yields NULL).
    // There is no real buyer to print, so treat this the same as a
    // nonexistent transfer rather than silently rendering a blank
    // "Consignee & Buyer:" block on a document that claims to be valid.
    if (!$is_cp_buyer && empty($buyer_name)) {
        return null;
    }

    $result_Invoice_Details = [
        'transfer_id'         => $row['transfer_id'],
        'ref_number'          => $row['ref_number'],
        'transfer_date'       => $row['transfer_date'],
        'created_by'          => $row['created_by'],
        'created_at'          => $row['created_at'],
        'buyer_name'          => $buyer_name,
        'buyer_gstin'         => $buyer_gstin,
        'buyer_mobile'        => $buyer_mobile,
        'buyer_address_parts' => $buyer_address_parts,
        'is_cp_buyer'         => $is_cp_buyer,
    ];

    $result_Godown = [
        'gname'         => $row['gname'],
        'address_line1' => $row['address_line1'],
        'address_line2' => $row['address_line2'],
        'gstin'         => $row['gstin'],
        'state'         => $row['state'],
        'state_code'    => $row['state_code'],
        'contact'       => $row['contact'],
        'email'         => $row['email'],
        'logo'          => $row['logo'],
        'acname'        => $row['acname'],
        'acnumber'      => $row['acnumber'],
        'bankname'      => $row['bankname'],
        'branchname'    => $row['branchname'],
        'ifsc'          => $row['ifsc'],
        'upinumber'     => $row['upinumber'],
    ];

    // Line items — rate is always the product's CURRENT mrp, since
    // pl_godown_transfer_items stores no rate of its own.
    $stmt2 = $db_conn->prepare("
        SELECT ti.quantity,
               p.productName, p.hsn, p.gst AS gst_percentage, p.gst_type, p.mrp, p.packs_per_carton
        FROM pl_godown_transfer_items ti
        JOIN products p ON p.id = ti.product_id
        WHERE ti.transfer_id = ?
        ORDER BY ti.id
    ");
    $stmt2->bind_param("i", $transfer_id);
    $stmt2->execute();
    $invoice_items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    // Totals — identical computation to TpInvoiceData.php's loop, with
    // "amount" (which TP reads from its stored invoice line) replaced by
    // mrp * quantity, since a transfer has no stored line amount.
    $TotalAMount123   = 0;
    $Totalquantity123 = 0;
    $totalgstamount   = 0;
    $hsn_totals       = [];
    $hsn_gst_totals   = [];
    $hsn_gst_pct      = [];
    $__inv_gst_pct    = 0;
    foreach ($invoice_items as &$item) {
        $qty           = (int)$item['quantity'];
        $mrp           = (float)$item['mrp'];
        $gross_amount  = $mrp * $qty;
        $gst_pct       = (int)$item['gst_percentage'];
        $gst_type      = $item['gst_type'] ?? 'exclusive';

        if ($gst_type === 'inclusive' && $gst_pct > 0) {
            $gross_taxable_value = $gross_amount * 100 / (100 + $gst_pct);
            $taxable_value       = $gross_taxable_value; // no discount to subtract for a transfer
            $gst_amount          = $gross_amount - $gross_taxable_value;
        } else {
            $gross_taxable_value = $gross_amount;
            $taxable_value       = $gross_amount;
            $gst_amount          = $gross_amount * $gst_pct / 100;
        }
        $item['taxable_value'] = $taxable_value;
        $item['gst_amount']    = $gst_amount;
        $item['taxable_rate']  = $qty > 0 ? $gross_taxable_value / $qty : 0;
        $item['taxable_rate_incl'] = $item['taxable_rate'] + ($gst_pct > 0 ? $item['taxable_rate'] * $gst_pct / 100 : 0);

        $TotalAMount123   += $taxable_value;
        $Totalquantity123 += $qty;
        $totalgstamount   += $gst_amount;
        $hsn = $item['hsn'] ?: '-';
        $hsn_totals[$hsn]     = ($hsn_totals[$hsn] ?? 0) + $taxable_value;
        $hsn_gst_totals[$hsn] = ($hsn_gst_totals[$hsn] ?? 0) + $gst_amount;
        $hsn_gst_pct[$hsn]    = $gst_pct;
        $__inv_gst_pct        = max($__inv_gst_pct, $gst_pct);
    }
    unset($item);

    // Carton breakdown — identical logic to TpInvoiceData.php.
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

    // Whether the SGST/CGST total rows' single blended percentage (derived
    // from $__inv_gst_pct, the MAX rate across all lines) is potentially
    // inaccurate: true when the GST-bearing lines span more than one
    // distinct rate. A 0% line contributes nothing to the SGST/CGST total
    // in the first place, so it's excluded here — a mix of "0% and 5%"
    // isn't a mixed-rate label problem, only "5% and 18%" (etc.) is.
    $__nonzero_gst_rates = array_filter(array_unique(array_values($hsn_gst_pct)), fn($pct) => $pct > 0);
    $has_mixed_gst_rates = count($__nonzero_gst_rates) > 1;

    $grand_total     = $TotalAMount123 + $totalgstamount;
    $has_gst_product = $totalgstamount > 0;
    $invoice_heading = $has_gst_product ? 'Tax Invoice' : 'Bill of Supply';

    $result    = number_to_words_inr($grand_total);
    $TAXresult = number_to_words_inr($totalgstamount);
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
        'has_mixed_gst_rates',
        'TotalCartons123', 'has_carton_data',
        'grand_total', 'has_gst_product', 'invoice_heading',
        'result', 'TAXresult', 'Currency_symbol', 'Currency_Name'
    );
}
