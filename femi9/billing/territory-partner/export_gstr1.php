<?php
ob_start();
include("checksession.php");
include("config.php");
error_reporting(0);

$from_month = $_REQUEST['frdate'] ?? '';
$to_month   = $_REQUEST['todate'] ?? '';
$tp_id      = $Login_user_IDvl;

$to_month_days = date("t", strtotime($to_month));
$from_date = date("Y-m-01", strtotime($from_month));
$to_date   = date("Y-m-" . $to_month_days, strtotime($to_month));

include('gst_details.php');              // intra-state
include('gst_details_inter.php');        // inter-state
include('gst_details_credit.php');       // intra-state credit notes
include('gst_details_credit_inter.php'); // inter-state credit notes

$Total_sls_register_intra   = $total_intra_register   + $total_intra_register2;
$Total_sls_unregister_intra = $total_intra_unregister  + $total_intra_unregister2;
$Total_sls_register_inter   = $total_inter_register    + $total_inter_register2;
$Total_sls_unregister_inter = $total_inter_unregister  + $total_inter_unregister2;

$Total_intra_register_sales   = $Total_sls_register_intra;
$Total_intra_unregister_sales = $Total_sls_unregister_intra;
$Total_inter_register_sales   = $Total_sls_register_inter;
$Total_inter_unregister_sales = $Total_sls_unregister_inter;

$total_intra_register_credit_note   = $total_intra_register_credit;
$total_intra_unregister_credit_note = $total_intra_unregister_credit;
$total_inter_register_credit_note   = $total_inter_register_credit;
$total_inter_unregister_credit_note = $total_inter_unregister_credit;

$intra_reg_supplies_grand_total   = $Total_intra_register_sales   - $total_intra_register_credit_note;
$intra_unreg_supplies_grand_total = $Total_intra_unregister_sales - $total_intra_unregister_credit_note;
$inter_reg_supplies_grand_total   = $Total_inter_register_sales   - $total_inter_register_credit_note;
$inter_unreg_supplies_grand_total = $Total_inter_unregister_sales - $total_inter_unregister_credit_note;

$intra_reg_nil       = $nil_intra_register   - $nil_intra_register_credit;
$intra_reg_taxable   = $intra_reg_supplies_grand_total - $intra_reg_nil;
$intra_unreg_nil     = $nil_intra_unregister - $nil_intra_unregister_credit;
$intra_unreg_taxable = $intra_unreg_supplies_grand_total - $intra_unreg_nil;
$inter_reg_nil       = $nil_inter_register   - $nil_inter_register_credit;
$inter_reg_taxable   = $inter_reg_supplies_grand_total - $inter_reg_nil;
$inter_unreg_nil     = $nil_inter_unregister - $nil_inter_unregister_credit;
$inter_unreg_taxable = $inter_unreg_supplies_grand_total - $inter_unreg_nil;

$Nil_rated_total = $intra_reg_nil + $intra_unreg_nil + $inter_reg_nil + $inter_unreg_nil;
$Taxable_total   = $intra_reg_taxable + $intra_unreg_taxable + $inter_reg_taxable + $inter_unreg_taxable;

// HSN-wise summary (Table 12) — same query as gstr1.php.
$stmt = $db_conn->prepare(
    "SELECT DISTINCT p.hsn, p.gst
     FROM territory_partner_stock s
     JOIN products p ON p.id = s.product_id
     WHERE s.territory_partner_id = ? AND p.hsn IS NOT NULL AND p.hsn <> ''
     ORDER BY p.hsn ASC, p.gst ASC"
);
$stmt->bind_param("i", $tp_id);
$stmt->execute();
$hsn_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$HSN_grand_nil = 0; $HSN_grand_taxable = 0;
$hsn_rows = [];
foreach ($hsn_list as $h) {
    $hsn_code = $h['hsn'];
    $hsn_rate = (float)$h['gst'];
    $rate_filter = $hsn_rate == 0 ? '=0' : '>0';

    $q = "select sum(qty), sum(total-gstamount_total) from user_invoice_items where hsn='$hsn_code' and gst_percentage$rate_filter and date between '$from_date' and '$to_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$tp_id'";
    $r = mysqli_fetch_array(mysqli_query($db_conn, $q));
    $qty = (float)($r[0] ?? 0); $val = (float)($r[1] ?? 0);

    $q = "select sum(qty), sum(total-gstamount_total) from invoice_items where hsn='$hsn_code' and gst_percentage$rate_filter and date between '$from_date' and '$to_date' and user_type='$Login_user_TYPEvl' and user_id='$tp_id'";
    $r = mysqli_fetch_array(mysqli_query($db_conn, $q));
    $qty += (float)($r[0] ?? 0); $val += (float)($r[1] ?? 0);

    $q = "select sum(qty), sum(total-gstamount_total) from user_return_stock_items where hsn='$hsn_code' and gst_percentage$rate_filter and date between '$from_date' and '$to_date' and to_usertype='$Login_user_TYPEvl' and to_userid='$tp_id'";
    $r = mysqli_fetch_array(mysqli_query($db_conn, $q));
    $qty -= (float)($r[0] ?? 0); $val -= (float)($r[1] ?? 0);

    if ($qty == 0 && $val == 0) continue;

    if ($hsn_rate == 0) { $HSN_grand_nil += $val; } else { $HSN_grand_taxable += $val; }
    $hsn_rows[] = ['hsn' => $hsn_code, 'rate' => $hsn_rate, 'qty' => $qty, 'val' => $val];
}

// Table 4 & 7 — B2B vs B2C, HSN-wise (rated supplies only).
$stmt_rated = $db_conn->prepare(
    "SELECT DISTINCT p.hsn, p.gst
     FROM territory_partner_stock s
     JOIN products p ON p.id = s.product_id
     WHERE s.territory_partner_id = ? AND p.gst > 0 AND p.hsn IS NOT NULL AND p.hsn <> ''
     ORDER BY p.hsn ASC, p.gst ASC"
);
$stmt_rated->bind_param("i", $tp_id);
$stmt_rated->execute();
$hsn_rated_list = $stmt_rated->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_rated->close();

$B2B_grand_val = 0; $B2C_grand_val = 0;
$hsn_b2b_rows = [];
foreach ($hsn_rated_list as $h) {
    $hsn_code = $h['hsn'];
    $hsn_rate = (float)$h['gst'];
    $b2b_qty = 0; $b2b_val = 0; $b2c_qty = 0; $b2c_val = 0;
    foreach (['register' => true, 'unregister' => false] as $bg => $is_b2b) {
        $q = "select sum(qty), sum(total-gstamount_total) from user_invoice_items where hsn='$hsn_code' and gst_percentage>0 and buyer_gsttype='$bg' and date between '$from_date' and '$to_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$tp_id'";
        $r = mysqli_fetch_array(mysqli_query($db_conn, $q));
        $qty = (float)($r[0] ?? 0); $val = (float)($r[1] ?? 0);

        $q = "select sum(qty), sum(total-gstamount_total) from invoice_items where hsn='$hsn_code' and gst_percentage>0 and buyer_gsttype='$bg' and date between '$from_date' and '$to_date' and user_type='$Login_user_TYPEvl' and user_id='$tp_id'";
        $r = mysqli_fetch_array(mysqli_query($db_conn, $q));
        $qty += (float)($r[0] ?? 0); $val += (float)($r[1] ?? 0);

        $q = "select sum(qty), sum(total-gstamount_total) from user_return_stock_items where hsn='$hsn_code' and gst_percentage>0 and buyer_gsttype='$bg' and date between '$from_date' and '$to_date' and to_usertype='$Login_user_TYPEvl' and to_userid='$tp_id'";
        $r = mysqli_fetch_array(mysqli_query($db_conn, $q));
        $qty -= (float)($r[0] ?? 0); $val -= (float)($r[1] ?? 0);

        if ($is_b2b) { $b2b_qty = $qty; $b2b_val = $val; } else { $b2c_qty = $qty; $b2c_val = $val; }
    }
    if ($b2b_qty == 0 && $b2b_val == 0 && $b2c_qty == 0 && $b2c_val == 0) continue;
    $B2B_grand_val += $b2b_val; $B2C_grand_val += $b2c_val;
    $hsn_b2b_rows[] = ['hsn' => $hsn_code, 'rate' => $hsn_rate, 'b2b_qty' => $b2b_qty, 'b2b_val' => $b2b_val, 'b2c_qty' => $b2c_qty, 'b2c_val' => $b2c_val];
}

require_once __DIR__ . '/include/TpB2bBuyerHelper.php';
require_once __DIR__ . '/include/TpB2cInvoiceHelper.php';
$b2b_buyers = tp_compute_b2b_buyers($db_conn, $Login_user_TYPEvl, $tp_id, $from_date, $to_date);
$b2b_grand_taxable = 0; $b2b_grand_cgst = 0; $b2b_grand_sgst = 0; $b2b_grand_igst = 0; $b2b_buyer_count = count($b2b_buyers);
foreach ($b2b_buyers as $b) {
    $b2b_grand_taxable += $b['taxable']; $b2b_grand_cgst += $b['cgst']; $b2b_grand_sgst += $b['sgst']; $b2b_grand_igst += $b['igst'];
}
$b2c_totals = tp_compute_b2c_totals($db_conn, $Login_user_TYPEvl, $tp_id, $from_date, $to_date);

// Table 13 — Documents Issued.
function doc_series_code($inv_number) {
    $inv_number = trim($inv_number);
    if (preg_match('/^([A-Za-z]+)/', $inv_number, $m)) return strtoupper($m[1]);
    return 'OTHER';
}
$doc_channels = [
    ['label' => 'Shop Sale', 'table' => 'user_invoice', 'num_col' => 'inv_number', 'date_col' => 'date', 'where' => "from_user_type='$Login_user_TYPEvl' and from_user_id='$tp_id'"],
    ['label' => 'Customer Sale', 'table' => 'invoice', 'num_col' => 'inv_number', 'date_col' => 'date', 'where' => "user_type='$Login_user_TYPEvl' and user_id='$tp_id'"],
];
$doc_grand_total = 0;
$doc_rows = [];
foreach ($doc_channels as $dc) {
    $dq = "select {$dc['num_col']} as num from {$dc['table']} where {$dc['where']} and {$dc['date_col']} between '$from_date' and '$to_date' order by {$dc['date_col']} asc, {$dc['num_col']} asc";
    $dres = mysqli_query($db_conn, $dq);
    $series_groups = [];
    while ($drow = mysqli_fetch_assoc($dres)) {
        $series_groups[doc_series_code($drow['num'])][] = $drow['num'];
    }
    ksort($series_groups);
    foreach ($series_groups as $series_code => $nums) {
        $count = count($nums);
        if ($count == 0) continue;
        $doc_grand_total += $count;
        $doc_rows[] = ['label' => $dc['label'], 'series' => $series_code, 'from' => $nums[0], 'to' => $nums[$count-1], 'count' => $count];
    }
}

function csv_row($cols) {
    return implode(',', array_map(function ($v) {
        return '"' . str_replace('"', '""', $v) . '"';
    }, $cols)) . "\n";
}
function money($v) { return number_format((float)$v, 2, '.', ''); }

$csv = '';
$csv .= csv_row(["GSTR1 Report", "$from_date to $to_date"]);
$csv .= "\n";

$csv .= csv_row(["Intra-state Summary"]);
$csv .= csv_row(["", "Registered Person", "Unregistered Person"]);
$csv .= csv_row(["Total Sales (Shop, Customer)", money($Total_intra_register_sales), money($Total_intra_unregister_sales)]);
$csv .= csv_row(["Sales Return (Credit Note)", money($total_intra_register_credit_note), money($total_intra_unregister_credit_note)]);
$csv .= "\n";

$csv .= csv_row(["Inter-state Summary"]);
$csv .= csv_row(["", "Registered Person", "Unregistered Person"]);
$csv .= csv_row(["Total Sales (Shop, Customer)", money($Total_inter_register_sales), money($Total_inter_unregister_sales)]);
$csv .= csv_row(["Sales Return (Credit Note)", money($total_inter_register_credit_note), money($total_inter_unregister_credit_note)]);
$csv .= "\n";

$csv .= csv_row(["GSTR-1 Filing Summary (Table 4, 7, 8)"]);
$csv .= csv_row(["Description", "Nil Rated Supplies", "Taxable Supplies (GST Rated)", "Non GST Supplies"]);
$csv .= csv_row(["Intra-state supplies to registered person", money($intra_reg_nil), money($intra_reg_taxable), "0.00"]);
$csv .= csv_row(["Intra-state supplies to unregistered person", money($intra_unreg_nil), money($intra_unreg_taxable), "0.00"]);
$csv .= csv_row(["Inter-state supplies to registered person", money($inter_reg_nil), money($inter_reg_taxable), "0.00"]);
$csv .= csv_row(["Inter-state supplies to unregistered person", money($inter_unreg_nil), money($inter_unreg_taxable), "0.00"]);
$csv .= csv_row(["Total", money($Nil_rated_total), money($Taxable_total), "0.00"]);
$csv .= "\n";

$csv .= csv_row(["Table 12 - HSN-wise Summary of Outward Supplies"]);
$csv .= csv_row(["HSN", "GST Rate", "Total Quantity", "Nil-Rated Value", "Taxable Value"]);
foreach ($hsn_rows as $hr) {
    $csv .= csv_row([
        $hr['hsn'],
        $hr['rate'] > 0 ? $hr['rate'] . '%' : 'Nil',
        $hr['qty'],
        $hr['rate'] == 0 ? money($hr['val']) : '',
        $hr['rate'] > 0 ? money($hr['val']) : '',
    ]);
}
$csv .= csv_row(["Total", "", "", money($HSN_grand_nil), money($HSN_grand_taxable)]);
$csv .= "\n";

$csv .= csv_row(["Table 4 & 7 - Rated (Taxable) Supplies, B2B vs B2C, HSN-wise"]);
$csv .= csv_row(["HSN", "GST Rate", "B2B Qty", "B2B Taxable Value", "B2C Qty", "B2C Taxable Value"]);
foreach ($hsn_b2b_rows as $hr) {
    $csv .= csv_row([$hr['hsn'], $hr['rate'] . '%', $hr['b2b_qty'], money($hr['b2b_val']), $hr['b2c_qty'], money($hr['b2c_val'])]);
}
$csv .= csv_row(["Total", "", "", money($B2B_grand_val), "", money($B2C_grand_val)]);
$csv .= "\n";

$csv .= csv_row(["Table 4 - B2B Invoices, Buyer-Wise Summary"]);
$csv .= csv_row(["B2B Buyers", "Taxable Value", "CGST", "SGST", "IGST"]);
$csv .= csv_row([$b2b_buyer_count, money($b2b_grand_taxable), money($b2b_grand_cgst), money($b2b_grand_sgst), money($b2b_grand_igst)]);
$csv .= "\n";

$csv .= csv_row(["Table 7 - B2C (Others), Tax Summary"]);
$csv .= csv_row(["Taxable Value", "CGST", "SGST", "IGST"]);
$csv .= csv_row([money($b2c_totals['taxable']), money($b2c_totals['cgst']), money($b2c_totals['sgst']), money($b2c_totals['igst'])]);
$csv .= "\n";

$csv .= csv_row(["Table 13 - Documents Issued During the Tax Period"]);
$csv .= csv_row(["Channel", "Document Series", "Series From", "Series To", "Total Issued"]);
foreach ($doc_rows as $dr) {
    $csv .= csv_row([$dr['label'], $dr['series'], $dr['from'], $dr['to'], $dr['count']]);
}
$csv .= csv_row(["Total", "", "", "", $doc_grand_total]);

ob_end_clean();
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=GSTR1_Report_" . date("Ymd", strtotime($from_date)) . "_" . date("Ymd", strtotime($to_date)) . ".csv");
echo $csv;
