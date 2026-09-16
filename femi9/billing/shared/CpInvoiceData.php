<?php
/**
 * Loads everything render_cp_invoice_html() (CpInvoiceHtml.php) needs for
 * one CP invoice (which doubles as its delivery note). Mirrors the shape of
 * shared/TpInvoiceData.php but deliberately simpler — no GST computation,
 * since the CP invoice-cum-delivery-note is not a GST tax invoice (see
 * spec: "New print view").
 *
 * Returns null if the invoice id doesn't resolve to a real invoice.
 */

function load_cp_invoice_data(mysqli $db_conn, int $inv_id): ?array {
    $stmt = $db_conn->prepare("
        SELECT cpi.*,
               cp.name AS cp_name, cp.cp_id AS cp_code, cp.mobile AS cp_mobile, cp.gstin AS cp_gstin,
               cp.branch_line1, cp.branch_line2, cp.branch_city, cp.branch_district, cp.branch_state, cp.branch_country, cp.branch_pincode,
               gd.gname AS godown_name
        FROM cp_invoices cpi
        JOIN channel_partners cp ON cp.id = cpi.channel_partner_id
        LEFT JOIN company_godown gd ON gd.id = cpi.source_godown_id
        WHERE cpi.id = ?
    ");
    $stmt->bind_param("i", $inv_id);
    $stmt->execute();
    $result_Invoice_Details = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$result_Invoice_Details) {
        return null;
    }

    $stmt2 = $db_conn->prepare("
        SELECT cpii.quantity, cpii.rate, cpii.amount, p.productName
        FROM cp_invoice_items cpii
        JOIN products p ON p.id = cpii.product_id
        WHERE cpii.cp_invoice_id = ?
        ORDER BY cpii.id ASC
    ");
    $stmt2->bind_param("i", $inv_id);
    $stmt2->execute();
    $result_Items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    global $business_name;

    return [
        'result_Invoice_Details' => $result_Invoice_Details,
        'result_Items'           => $result_Items,
        'business_name'          => $business_name ?? '',
    ];
}
