<?php
/**
 * Loads and computes everything render_superstockist_tp_invoice_html()
 * (SuperStockistTpInvoiceHtml.php) needs for one TP invoice raised by a
 * super-stockist to a TP they onboarded — DB queries, GST computation, HSN
 * totals, amount-in-words. Shared between
 * super-stockist/tp-invoice-print.php (the logged-in Print page) and
 * super-stockist/tp-invoice-pdf.php (the no-login PDF endpoint used for
 * WhatsApp sharing).
 *
 * Genuinely different from company/shared/TpInvoiceData.php: the seller
 * here is the super-stockist's OWN account (super_stockiest table), not
 * company_godown, and authorization is scoped via
 * territory_partners.onboard_ss_id (a TP must have been onboarded by this
 * specific super-stockist) rather than tp_invoices.source_cp_id. No carton
 * columns on this invoice type.
 *
 * $ss_id is required (not optional like TpInvoiceData.php's $cp_id) since
 * this endpoint has no "unrestricted" caller — every use of this invoice
 * type is scoped to one super-stockist.
 *
 * Returns null if the invoice id doesn't resolve to a real invoice owned
 * by this super-stockist.
 */

require_once __DIR__ . '/number-format-helpers.php';
require_once __DIR__ . '/TpProductType.php';
require_once __DIR__ . '/SuperStockistUserInvoiceData.php'; // for amount_in_words_simple()

function load_superstockist_tp_invoice_data($db_conn, int $inv_id, string $ss_id): ?array {
    // Invoice header — ownership via TP.onboard_ss_id, same scoping the
    // original inline page used.
    $stmt = $db_conn->prepare("
        SELECT tpi.*,
               tp.name AS tp_name, tp.company_name AS tp_company_name, tp.tp_id AS tp_code, tp.mobile AS tp_mobile, tp.gstin AS tp_gstin,
               tp.branch_line1, tp.branch_line2, tp.branch_city, tp.branch_district, tp.branch_state, tp.branch_country,
               tp.delivery_line1, tp.delivery_line2, tp.delivery_city, tp.delivery_district, tp.delivery_state, tp.delivery_country
        FROM tp_invoices tpi
        JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
        WHERE tpi.id = ? AND tp.onboard_ss_id = ?
    ");
    $stmt->bind_param("is", $inv_id, $ss_id);
    $stmt->execute();
    $result_Invoice_Details = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$result_Invoice_Details) {
        return null;
    }

    // Seller = this Super Stockist's own account
    $ss_stmt = $db_conn->prepare("SELECT * FROM super_stockiest WHERE temp_id = ? LIMIT 1");
    $ss_stmt->bind_param("s", $ss_id);
    $ss_stmt->execute();
    $result_Seller = $ss_stmt->get_result()->fetch_assoc();
    $ss_stmt->close();

    // Line items with product details
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
    $courier_charges  = (float)$result_Invoice_Details['courier_charges'];
    $discount_amount  = (float)($result_Invoice_Details['discount_amount'] ?? 0);
    $grand_total      = (float)$result_Invoice_Details['total_amount'];
    $has_gst_product  = $totalgstamount > 0;
    $invoice_heading  = $has_gst_product ? 'Tax Invoice' : 'Bill of Supply';

    $result    = amount_in_words_simple($grand_total);
    $TAXresult = amount_in_words_simple($totalgstamount);

    $Currency_symbol = "&#8377;";
    $Currency_Name   = "INR";

    return compact(
        'result_Invoice_Details', 'result_Seller', 'invoice_items',
        'TotalAMount123', 'Totalquantity123', 'totalgstamount', 'hsn_totals',
        'courier_charges', 'discount_amount', 'grand_total', 'has_gst_product', 'invoice_heading',
        'result', 'TAXresult', 'Currency_symbol', 'Currency_Name'
    );
}
