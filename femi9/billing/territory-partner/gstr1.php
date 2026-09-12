<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$from_month = $_REQUEST['frdate'] ?? '';
$to_month   = $_REQUEST['todate'] ?? '';

$tp_id = $Login_user_IDvl;

if ($from_month != '') {
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

    // Net (sales - credit notes), per intra/inter x register/unregister —
    // same "GSTR-1 Filing Summary" grand totals as company/GSTR1.php.
    $intra_reg_supplies_grand_total   = $Total_intra_register_sales   - $total_intra_register_credit_note;
    $intra_unreg_supplies_grand_total = $Total_intra_unregister_sales - $total_intra_unregister_credit_note;
    $inter_reg_supplies_grand_total   = $Total_inter_register_sales   - $total_inter_register_credit_note;
    $inter_unreg_supplies_grand_total = $Total_inter_unregister_sales - $total_inter_unregister_credit_note;

    // Nil-rated vs taxable split of each grand total — same fix as
    // company/GSTR1.php: both sides net of their own credit notes so taxable
    // doesn't double-subtract nil-rated returns.
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

    // HSN-wise total quantity/taxable value — net of sales minus returns,
    // split nil-rated vs taxable per HSN, same as company/GSTR1.php's Table 12
    // (but scoped to Shop+Customer only — no OT sales/internal transfer/TP-
    // invoices-received for a TP).
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

    // Table 13 — Documents Issued: split by each invoice number's own leading
    // series code, same as company/GSTR1.php (a channel can carry more than
    // one series, e.g. Customer Sale mixes "C..." and "CD..." numbers).
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GSTR1 : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <style type="text/css">
    #dashanch{color:#000 !important;}
    #dashanch:hover{color:#1a06a6 !important;}
    #reportdash th{font-size:13px;font-weight:600;}
    #reportdash td{font-weight:700;font-size:14px;}

    #gsttablevl{height:200px;margin-bottom:10px;}
    #gsttablevl tr th{border:1px solid #000;padding:5px;}
    #gsttablevl tr td{border:1px solid #000;text-align:right;padding:5px;}
    #gsttablevl a{text-decoration:none;color:blue;}
    #gsttablevl a:hover{background:#ddd;}
    </style>
</head>
<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php"); ?>
            <?php include("femi_menu.php"); ?>
        </div>
        <div class="app-container">
            <?php include("app-header.php"); ?>
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container">
                        <div class="row">
                            <div class="col">
                                <div class="page-description" style="margin-left:-25px;">
                                    <table style="width:100%;">
                                    <tr>
                                    <td><h1>GST Reports &gt; GSTR1</h1></td>
                                    </tr>
                                    </table>
                                </div>
                            </div>

                            <form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
                            <div class="overviewcontainar">
                            <div id="searchleftcont">
                            <label class="form-label">From Month</label>
                            <input type="month" required name="frdate" value="<?=htmlspecialchars($from_month);?>" class="form-control">
                            </div>
                            <div id="searchleftcont">
                            <label class="form-label">To Month</label>
                            <input type="month" required name="todate" value="<?=htmlspecialchars($to_month);?>" class="form-control">
                            </div>
                            <div id="searchbuttoncont">
                            <button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
                            </div>
                            </div>
                            <div style="clear:both;"></div>
                            <br/>
                            </form>

                        </div>

                        <!--------------------------------------------------------------------->
                        <div class="row">

                        <?php if ($from_month != ''): ?>

                        <table style="width:100%;">
                        <tr valign="top">

                        <!----------Left: Intra-state------------>
                        <td>

                        <h1>Intra-state</h1>
                        <table id="gsttablevl">
                        <tr>
                        <th width="50%"></th>
                        <th width="25%">registered person</th>
                        <th width="25%">unregistered person</th>
                        </tr>
                        <tr>
                        <th>Total Sales (Shop, Customer)</th>
                        <td><a href="gst_sls_detailed_report?data1=inner&data2=register&frd=<?=$from_date;?>&tod=<?=$to_date;?>" target="_blank"><?=inr_format($Total_sls_register_intra, 2);?></a></td>
                        <td><a href="gst_sls_detailed_report?data1=inner&data2=unregister&frd=<?=$from_date;?>&tod=<?=$to_date;?>" target="_blank"><?=inr_format($Total_sls_unregister_intra, 2);?></a></td>
                        </tr>
                        <tfoot>
                        <tr>
                        <td style="text-align:right;"><b>Total</b></td>
                        <td><b><?=inr_format($Total_intra_register_sales, 2);?></b></td>
                        <td><b><?=inr_format($Total_intra_unregister_sales, 2);?></b></td>
                        </tr>
                        </tfoot>
                        </table>

                        <h3 style="color:red;">Credit Note</h3>
                        <table id="gsttablevl">
                        <tr>
                        <th width="50%"></th>
                        <th width="25%">registered person</th>
                        <th width="25%">unregistered person</th>
                        </tr>
                        <tr>
                        <th>Sales Return<br/>(Shop, Customer)</th>
                        <td><a href="gst_credit_sls_detailed_report?data1=inner&data2=register&frd=<?=$from_date;?>&tod=<?=$to_date;?>" target="_blank"><?=inr_format($total_intra_register_credit, 2);?></a></td>
                        <td><a href="gst_credit_sls_detailed_report?data1=inner&data2=unregister&frd=<?=$from_date;?>&tod=<?=$to_date;?>" target="_blank"><?=inr_format($total_intra_unregister_credit, 2);?></a></td>
                        </tr>
                        <tfoot>
                        <tr>
                        <td style="text-align:right;"><b>Total</b></td>
                        <td><b><?=inr_format($total_intra_register_credit_note, 2);?></b></td>
                        <td><b><?=inr_format($total_intra_unregister_credit_note, 2);?></b></td>
                        </tr>
                        </tfoot>
                        </table>

                        </td>

                        <td>&nbsp;&nbsp;</td>

                        <!------------------------------------------------------------------------------>
                        <!-----------------------------Inter State (Other State)------------------------>
                        <!--------Right------------>
                        <td>

                        <h1>Inter-state</h1>
                        <table id="gsttablevl">
                        <tr>
                        <th width="50%"></th>
                        <th width="25%">registered person</th>
                        <th width="25%">unregistered person</th>
                        </tr>
                        <tr>
                        <th>Total Sales (Shop, Customer)</th>
                        <td><a href="gst_sls_detailed_report?data1=outer&data2=register&frd=<?=$from_date;?>&tod=<?=$to_date;?>" target="_blank"><?=inr_format($Total_sls_register_inter, 2);?></a></td>
                        <td><a href="gst_sls_detailed_report?data1=outer&data2=unregister&frd=<?=$from_date;?>&tod=<?=$to_date;?>" target="_blank"><?=inr_format($Total_sls_unregister_inter, 2);?></a></td>
                        </tr>
                        <tfoot>
                        <tr>
                        <td style="text-align:right;"><b>Total</b></td>
                        <td><b><?=inr_format($Total_inter_register_sales, 2);?></b></td>
                        <td><b><?=inr_format($Total_inter_unregister_sales, 2);?></b></td>
                        </tr>
                        </tfoot>
                        </table>

                        <h3 style="color:red;">Credit Note</h3>
                        <table id="gsttablevl">
                        <tr>
                        <th width="50%"></th>
                        <th width="25%">registered person</th>
                        <th width="25%">unregistered person</th>
                        </tr>
                        <tr>
                        <th>Sales Return<br/>(Shop, Customer)</th>
                        <td>
                        <a href="gst_credit_sls_detailed_report?data1=outer&data2=register&frd=<?=$from_date;?>&tod=<?=$to_date;?>" target="_blank">
                        <?=inr_format($total_inter_register_credit, 2);?></a>
                        </td>
                        <td><a href="gst_credit_sls_detailed_report?data1=outer&data2=unregister&frd=<?=$from_date;?>&tod=<?=$to_date;?>" target="_blank">
                        <?=inr_format($total_inter_unregister_credit, 2);?></a>
                        </td>
                        </tr>
                        <tfoot>
                        <tr>
                        <td style="text-align:right;"><b>Total</b></td>
                        <td><b><?=inr_format($total_inter_register_credit_note, 2);?></b></td>
                        <td><b><?=inr_format($total_inter_unregister_credit_note, 2);?></b></td>
                        </tr>
                        </tfoot>
                        </table>

                        </td>

                        </tr>
                        </table>

                        <div style="clear:both;"></div>
                        <br/>

                        <h1 style="margin-top:20px;">GSTR-1 Filing Summary</h1>
                        <p style="color:#666;margin-top:-8px;">Standard GST portal table layout &mdash; Table 4 (B2B), Table 7 (B2C), Table 8 (Nil/Exempt/Non-GST), Table 12 (HSN Summary), Table 13 (Documents Issued).</p>

                        <table id="gsttablevl">
                        <tr>
                        <th>Description</th>
                        <th>Nil Rated Supplies</th>
                        <th>Taxable Supplies (GST Rated)</th>
                        <th>Non GST Supplies</th>
                        </tr>

                        <tr>
                        <th>Intra-state supplies to registered person</th>
                        <td><?=inr_format($intra_reg_nil, 2);?></td>
                        <td><?=inr_format($intra_reg_taxable, 2);?></td>
                        <td>0.00</td>
                        </tr>
                        <tr>
                        <th>Intra-state supplies to unregistered person</th>
                        <td><?=inr_format($intra_unreg_nil, 2);?></td>
                        <td><?=inr_format($intra_unreg_taxable, 2);?></td>
                        <td>0.00</td>
                        </tr>
                        <tr>
                        <th>Inter-state supplies to registered person</th>
                        <td><?=inr_format($inter_reg_nil, 2);?></td>
                        <td><?=inr_format($inter_reg_taxable, 2);?></td>
                        <td>0.00</td>
                        </tr>
                        <tr>
                        <th>Inter-state supplies to unregistered person</th>
                        <td><?=inr_format($inter_unreg_nil, 2);?></td>
                        <td><?=inr_format($inter_unreg_taxable, 2);?></td>
                        <td>0.00</td>
                        </tr>

                        <tr>
                        <td></td>
                        <td><?=inr_format($Nil_rated_total, 2);?></td>
                        <td><?=inr_format($Taxable_total, 2);?></td>
                        <td></td>
                        </tr>

                        </table>

                        <!-------------HSN wise Total Qty---------->
                        <br/>
                        <h3>Table 12 — HSN-wise Summary of Outward Supplies</h3>
                        <table id="gsttablevl" style="height:auto;">
                        <tr>
                        <th>HSN</th>
                        <th>GST Rate</th>
                        <th>Total Quantity</th>
                        <th>Nil-Rated Value</th>
                        <th>Taxable Value</th>
                        </tr>
                        <?php foreach ($hsn_rows as $hr): ?>
                        <tr>
                        <td style="text-align:left;"><?=htmlspecialchars($hr['hsn']);?></td>
                        <td style="text-align:left;"><?=$hr['rate'] > 0 ? $hr['rate'].'%' : 'Nil';?></td>
                        <td style="text-align:left;"><?=$hr['qty'];?></td>
                        <td style="text-align:left;"><?=$hr['rate'] == 0 ? inr_format($hr['val'], 2) : '';?></td>
                        <td style="text-align:left;"><?=$hr['rate'] > 0 ? inr_format($hr['val'], 2) : '';?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr>
                        <td colspan="3" style="text-align:right;"><b>Total</b></td>
                        <td><b><?=inr_format($HSN_grand_nil, 2);?></b></td>
                        <td><b><?=inr_format($HSN_grand_taxable, 2);?></b></td>
                        </tr>
                        </table>

                        <!-------------HSN-wise B2B / B2C split (rated supplies only)---------->
                        <br/>
                        <h3>Table 4 &amp; 7 — Rated (Taxable) Supplies, B2B vs B2C, HSN-wise</h3>
                        <table id="gsttablevl" style="height:auto;">
                        <tr>
                        <th>HSN</th>
                        <th>GST Rate</th>
                        <th>B2B Qty</th>
                        <th>B2B Taxable Value</th>
                        <th>B2C Qty</th>
                        <th>B2C Taxable Value</th>
                        </tr>
                        <?php foreach ($hsn_b2b_rows as $hr): ?>
                        <tr>
                        <td style="text-align:left;"><?=htmlspecialchars($hr['hsn']);?></td>
                        <td style="text-align:left;"><?=$hr['rate'];?>%</td>
                        <td style="text-align:left;"><?=$hr['b2b_qty'];?></td>
                        <td style="text-align:left;"><?=inr_format($hr['b2b_val'], 2);?></td>
                        <td style="text-align:left;"><?=$hr['b2c_qty'];?></td>
                        <td style="text-align:left;"><?=inr_format($hr['b2c_val'], 2);?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr>
                        <td colspan="3" style="text-align:right;"><b>Total</b></td>
                        <td><b><?=inr_format($B2B_grand_val, 2);?></b></td>
                        <td></td>
                        <td><b><?=inr_format($B2C_grand_val, 2);?></b></td>
                        </tr>
                        </table>

                        <!-------------B2B Buyer-Wise Summary---------->
                        <br/>
                        <h3>Table 4 — B2B Invoices, Buyer-Wise Summary</h3>
                        <table id="gsttablevl" style="height:auto;">
                        <tr>
                        <th>Description</th>
                        <th>B2B Buyers</th>
                        <th>Taxable Value</th>
                        <th>CGST</th>
                        <th>SGST</th>
                        <th>IGST</th>
                        </tr>
                        <tr>
                        <td style="text-align:left;"><a href="gst_b2b_buyer_report?frd=<?=$from_date;?>&tod=<?=$to_date;?>" target="_blank"><b>Total B2B Supplies (view buyer-wise detail)</b></a></td>
                        <td style="text-align:left;"><?=$b2b_buyer_count;?></td>
                        <td style="text-align:left;"><b><?=inr_format($b2b_grand_taxable, 2);?></b></td>
                        <td style="text-align:left;"><b><?=inr_format($b2b_grand_cgst, 2);?></b></td>
                        <td style="text-align:left;"><b><?=inr_format($b2b_grand_sgst, 2);?></b></td>
                        <td style="text-align:left;"><b><?=inr_format($b2b_grand_igst, 2);?></b></td>
                        </tr>
                        </table>

                        <!-------------Table 7 - B2C (Others)---------->
                        <br/>
                        <h3>Table 7 — B2C (Others), Tax Summary</h3>
                        <table id="gsttablevl" style="height:auto;">
                        <tr>
                        <th>Description</th>
                        <th>Taxable Value</th>
                        <th>CGST</th>
                        <th>SGST</th>
                        <th>IGST</th>
                        </tr>
                        <tr>
                        <td style="text-align:left;"><a href="gst_b2c_invoice_report?frd=<?=$from_date;?>&tod=<?=$to_date;?>" target="_blank"><b>Total B2C (Others) Supplies (view invoice-wise detail)</b></a></td>
                        <td style="text-align:left;"><b><?=inr_format($b2c_totals['taxable'], 2);?></b></td>
                        <td style="text-align:left;"><b><?=inr_format($b2c_totals['cgst'], 2);?></b></td>
                        <td style="text-align:left;"><b><?=inr_format($b2c_totals['sgst'], 2);?></b></td>
                        <td style="text-align:left;"><b><?=inr_format($b2c_totals['igst'], 2);?></b></td>
                        </tr>
                        </table>

                        <!-------------Documents Issued During the Tax Period---------->
                        <br/>
                        <h3>Table 13 — Documents Issued During the Tax Period</h3>
                        <table id="gsttablevl" style="height:auto;">
                        <tr>
                        <th>Channel</th>
                        <th>Document Series</th>
                        <th>Series From</th>
                        <th>Series To</th>
                        <th>Total Issued</th>
                        </tr>
                        <?php foreach ($doc_rows as $dr): ?>
                        <tr>
                        <td style="text-align:left;"><?=htmlspecialchars($dr['label']);?></td>
                        <td style="text-align:left;"><?=htmlspecialchars($dr['series']);?></td>
                        <td style="text-align:left;"><?=htmlspecialchars($dr['from']);?></td>
                        <td style="text-align:left;"><?=htmlspecialchars($dr['to']);?></td>
                        <td style="text-align:left;"><?=$dr['count'];?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr>
                        <td colspan="4" style="text-align:right;"><b>Total</b></td>
                        <td><b><?=$doc_grand_total;?></b></td>
                        </tr>
                        </table>

                        <?php endif; ?>

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>
</body>
</html>
