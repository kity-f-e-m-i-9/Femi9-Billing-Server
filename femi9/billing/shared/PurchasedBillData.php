<?php
/**
 * Loads and computes everything render_purchased_bill_html()
 * (PurchasedBillHtml.php) needs for one TP purchase bill — DB queries, GST
 * computation, HSN totals, amount-in-words. Shared between
 * purchased-bill-print.php (the logged-in Print page, where the TP is the
 * BUYER of goods from the company/channel-partner) and purchased-bill-pdf.php
 * (the no-login PDF endpoint used for WhatsApp sharing) so both always show
 * identical figures.
 *
 * Returns null if the bill id doesn't resolve to a real tp_invoices row
 * owned by the given TP.
 */

require_once __DIR__ . '/number-format-helpers.php';

if (!function_exists('fmt_gst_pct')) {
    function fmt_gst_pct($v) {
        return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    }
}

function load_purchased_bill_data($db_conn, int $inv_id, int $tp_id): ?array {
    $stmt = $db_conn->prepare("
        SELECT tpi.*,
               tp.name AS tp_name, tp.company_name AS tp_company_name, tp.tp_id AS tp_code, tp.mobile AS tp_mobile, tp.gstin AS tp_gstin,
               tp.branch_line1, tp.branch_line2, tp.branch_city, tp.branch_district, tp.branch_state, tp.branch_country,
               tp.delivery_line1, tp.delivery_line2, tp.delivery_city, tp.delivery_district, tp.delivery_state, tp.delivery_country,
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
        LEFT JOIN company_godown gd             ON gd.id = tpi.source_godown_id
        WHERE tpi.id = ? AND tpi.territory_partner_id = ?
    ");
    $stmt->bind_param("ii", $inv_id, $tp_id);
    $stmt->execute();
    $result_Invoice_Details = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$result_Invoice_Details) {
        return null;
    }

    // Godown (used for seller letterhead / bank details when the source
    // isn't a GST-enabled channel partner)
    $_gd_id = (int)($result_Invoice_Details['source_godown_id'] ?? 0);
    $result_Godown = null;
    if ($_gd_id) {
        $_gd_stmt = $db_conn->prepare("SELECT * FROM company_godown WHERE id = ? LIMIT 1");
        $_gd_stmt->bind_param("i", $_gd_id);
        $_gd_stmt->execute();
        $result_Godown = $_gd_stmt->get_result()->fetch_assoc();
        $_gd_stmt->close();
    }
    if (empty($result_Godown)) {
        $result_Godown = $db_conn->query("SELECT * FROM company_godown LIMIT 1")->fetch_assoc();
    }

    // Line items
    $stmt2 = $db_conn->prepare("
        SELECT tpii.quantity, tpii.rate, tpii.amount,
               p.productName, p.hsn, p.gst AS gst_percentage, p.gst_type, p.mrp
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
    $TotalAMount123   = 0;
    $Totalquantity123 = 0;
    $totalgstamount   = 0;
    $hsn_totals       = [];
    foreach ($invoice_items as &$item) {
        $line_total = (float)$item['amount'];
        $gst_pct    = (int)$item['gst_percentage'];
        $gst_type   = $item['gst_type'] ?? 'exclusive';

        if ($gst_type === 'inclusive' && $gst_pct > 0) {
            $taxable_value = $line_total * 100 / (100 + $gst_pct);
            $gst_amount    = $line_total - $taxable_value;
        } else {
            $taxable_value = $line_total;
            $gst_amount    = $line_total * $gst_pct / 100;
        }
        $item['taxable_value'] = $taxable_value;
        $item['gst_amount']    = $gst_amount;
        $qty_int = (int)$item['quantity'];
        $item['taxable_rate']      = $qty_int > 0 ? $taxable_value / $qty_int : 0;
        $item['taxable_rate_incl'] = $item['taxable_rate'] + ($gst_pct > 0 ? $item['taxable_rate'] * $gst_pct / 100 : 0);

        $TotalAMount123   += $taxable_value;
        $Totalquantity123 += (int)$item['quantity'];
        $totalgstamount   += $gst_amount;
        $hsn = $item['hsn'] ?: '-';
        $hsn_totals[$hsn] = ($hsn_totals[$hsn] ?? 0) + $taxable_value;
    }
    unset($item);

    $courier_charges = (float)$result_Invoice_Details['courier_charges'];
    $discount_amount = (float)($result_Invoice_Details['discount_amount'] ?? 0);
    $grand_total     = (float)$result_Invoice_Details['total_amount'];
    $has_gst_product = $totalgstamount > 0;
    $invoice_heading = $has_gst_product ? 'Tax Invoice' : 'Bill of Supply';

    $result    = number_to_words_inr($grand_total);
    $TAXresult = number_to_words_inr($totalgstamount);

    $Currency_symbol = "&#8377;";
    $Currency_Name   = "INR";

    return compact(
        'result_Invoice_Details', 'result_Godown', 'invoice_items',
        'TotalAMount123', 'Totalquantity123', 'totalgstamount',
        'hsn_totals', 'courier_charges', 'discount_amount', 'grand_total',
        'has_gst_product', 'invoice_heading', 'result', 'TAXresult',
        'Currency_symbol', 'Currency_Name'
    );
}
