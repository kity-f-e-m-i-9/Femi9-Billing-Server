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

    // Sales — a TP sells onward via two paths depending on buyer type:
    // shop/order sales land in user_invoice(_items), direct customer sales
    // in invoice(_items). Both are tagged with this TP's identity as seller.
    $stmt = $db_conn->prepare(
        "SELECT
            SUM(CASE WHEN buyer_gsttype='register'   AND gst_type!='outer' THEN total ELSE 0 END) AS intra_reg,
            SUM(CASE WHEN buyer_gsttype='unregister' AND gst_type!='outer' THEN total ELSE 0 END) AS intra_unreg,
            SUM(CASE WHEN buyer_gsttype='register'   AND gst_type='outer' THEN total ELSE 0 END) AS inter_reg,
            SUM(CASE WHEN buyer_gsttype='unregister' AND gst_type='outer' THEN total ELSE 0 END) AS inter_unreg
         FROM user_invoice
         WHERE from_user_type = ? AND from_user_id = ? AND date BETWEEN ? AND ?"
    );
    $stmt->bind_param("siss", $Login_user_TYPEvl, $tp_id, $from_date, $to_date);
    $stmt->execute();
    $shop_sls = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $db_conn->prepare(
        "SELECT
            SUM(CASE WHEN buyer_gsttype='register'   AND gst_type!='outer' THEN total ELSE 0 END) AS intra_reg,
            SUM(CASE WHEN buyer_gsttype='unregister' AND gst_type!='outer' THEN total ELSE 0 END) AS intra_unreg,
            SUM(CASE WHEN buyer_gsttype='register'   AND gst_type='outer' THEN total ELSE 0 END) AS inter_reg,
            SUM(CASE WHEN buyer_gsttype='unregister' AND gst_type='outer' THEN total ELSE 0 END) AS inter_unreg
         FROM invoice
         WHERE user_type = ? AND user_id = ? AND date BETWEEN ? AND ?"
    );
    $stmt->bind_param("siss", $Login_user_TYPEvl, $tp_id, $from_date, $to_date);
    $stmt->execute();
    $cust_sls = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $Total_sls_register_intra   = (float)$shop_sls['intra_reg']   + (float)$cust_sls['intra_reg'];
    $Total_sls_unregister_intra = (float)$shop_sls['intra_unreg'] + (float)$cust_sls['intra_unreg'];
    $Total_sls_register_inter   = (float)$shop_sls['inter_reg']   + (float)$cust_sls['inter_reg'];
    $Total_sls_unregister_inter = (float)$shop_sls['inter_unreg'] + (float)$cust_sls['inter_unreg'];

    // Credit Note — TP-side stock returns (customers/shops returning goods
    // to this TP), the same buyer_gsttype/gst_type tagging as sales above.
    $stmt = $db_conn->prepare(
        "SELECT
            SUM(CASE WHEN buyer_gsttype='register'   AND gst_type!='outer' THEN total ELSE 0 END) AS intra_reg,
            SUM(CASE WHEN buyer_gsttype='unregister' AND gst_type!='outer' THEN total ELSE 0 END) AS intra_unreg,
            SUM(CASE WHEN buyer_gsttype='register'   AND gst_type='outer' THEN total ELSE 0 END) AS inter_reg,
            SUM(CASE WHEN buyer_gsttype='unregister' AND gst_type='outer' THEN total ELSE 0 END) AS inter_unreg
         FROM user_return_stock
         WHERE to_usertype = ? AND to_userid = ? AND date BETWEEN ? AND ?"
    );
    $stmt->bind_param("siss", $Login_user_TYPEvl, $tp_id, $from_date, $to_date);
    $stmt->execute();
    $credit = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $Total_intra_register_credit   = (float)$credit['intra_reg'];
    $Total_intra_unregister_credit = (float)$credit['intra_unreg'];
    $Total_inter_register_credit   = (float)$credit['inter_reg'];
    $Total_inter_unregister_credit = (float)$credit['inter_unreg'];

    // Net (sales - credit notes)
    $intra_reg_supplies_grand_total   = $Total_sls_register_intra   - $Total_intra_register_credit;
    $intra_unreg_supplies_grand_total = $Total_sls_unregister_intra - $Total_intra_unregister_credit;
    $inter_reg_supplies_grand_total   = $Total_sls_register_inter   - $Total_inter_register_credit;
    $inter_unreg_supplies_grand_total = $Total_sls_unregister_inter - $Total_inter_unregister_credit;

    $Nil_rated_total = $intra_reg_supplies_grand_total + $intra_unreg_supplies_grand_total
                      + $inter_reg_supplies_grand_total + $inter_unreg_supplies_grand_total;

    // HSN-wise total quantity — net of sales minus returns, across both
    // outgoing-sale tables, for products this TP actually has stock history for.
    $stmt = $db_conn->prepare(
        "SELECT DISTINCT p.hsn
         FROM territory_partner_stock s
         JOIN products p ON p.id = s.product_id
         WHERE s.territory_partner_id = ? AND p.hsn IS NOT NULL AND p.hsn <> ''
         ORDER BY p.hsn ASC"
    );
    $stmt->bind_param("i", $tp_id);
    $stmt->execute();
    $hsn_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Per-HSN net quantity and taxable value (subtotal = pre-GST amount),
    // each netted as sales (user_invoice_items + invoice_items) minus
    // returns (user_return_stock_items) for that HSN.
    $hsn_qty_stmt = $db_conn->prepare(
        "SELECT
            COALESCE((SELECT SUM(qty) FROM user_invoice_items WHERE hsn = ? AND date BETWEEN ? AND ? AND from_user_type = ? AND from_user_id = ?), 0) +
            COALESCE((SELECT SUM(qty) FROM invoice_items       WHERE hsn = ? AND date BETWEEN ? AND ? AND user_type = ?      AND user_id = ?), 0) -
            COALESCE((SELECT SUM(qty) FROM user_return_stock_items WHERE hsn = ? AND date BETWEEN ? AND ? AND to_usertype = ? AND to_userid = ?), 0)
            AS net_qty,
            COALESCE((SELECT SUM(subtotal) FROM user_invoice_items WHERE hsn = ? AND date BETWEEN ? AND ? AND from_user_type = ? AND from_user_id = ?), 0) +
            COALESCE((SELECT SUM(subtotal) FROM invoice_items       WHERE hsn = ? AND date BETWEEN ? AND ? AND user_type = ?      AND user_id = ?), 0) -
            COALESCE((SELECT SUM(subtotal) FROM user_return_stock_items WHERE hsn = ? AND date BETWEEN ? AND ? AND to_usertype = ? AND to_userid = ?), 0)
            AS net_taxable_value"
    );
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
                        <th>Total Sales</th>
                        <td><?=inr_format($Total_sls_register_intra, 2);?></td>
                        <td><?=inr_format($Total_sls_unregister_intra, 2);?></td>
                        </tr>
                        <tfoot>
                        <tr>
                        <td style="text-align:right;"><b>Total</b></td>
                        <td><b><?=inr_format($Total_sls_register_intra, 2);?></b></td>
                        <td><b><?=inr_format($Total_sls_unregister_intra, 2);?></b></td>
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
                        <th>Sales Return</th>
                        <td><?=inr_format($Total_intra_register_credit, 2);?></td>
                        <td><?=inr_format($Total_intra_unregister_credit, 2);?></td>
                        </tr>
                        <tfoot>
                        <tr>
                        <td style="text-align:right;"><b>Total</b></td>
                        <td><b><?=inr_format($Total_intra_register_credit, 2);?></b></td>
                        <td><b><?=inr_format($Total_intra_unregister_credit, 2);?></b></td>
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
                        <th>Total Sales</th>
                        <td><?=inr_format($Total_sls_register_inter, 2);?></td>
                        <td><?=inr_format($Total_sls_unregister_inter, 2);?></td>
                        </tr>
                        <tfoot>
                        <tr>
                        <td style="text-align:right;"><b>Total</b></td>
                        <td><b><?=inr_format($Total_sls_register_inter, 2);?></b></td>
                        <td><b><?=inr_format($Total_sls_unregister_inter, 2);?></b></td>
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
                        <th>Sales Return</th>
                        <td><?=inr_format($Total_inter_register_credit, 2);?></td>
                        <td><?=inr_format($Total_inter_unregister_credit, 2);?></td>
                        </tr>
                        <tfoot>
                        <tr>
                        <td style="text-align:right;"><b>Total</b></td>
                        <td><b><?=inr_format($Total_inter_register_credit, 2);?></b></td>
                        <td><b><?=inr_format($Total_inter_unregister_credit, 2);?></b></td>
                        </tr>
                        </tfoot>
                        </table>

                        </td>

                        </tr>
                        </table>

                        <div style="clear:both;"></div>
                        <br/>

                        <div align="right">
                        <a href="export_gstr1.php?t1=<?=$intra_reg_supplies_grand_total;?>&t2=<?=$intra_unreg_supplies_grand_total;?>&t3=<?=$inter_reg_supplies_grand_total;?>&t4=<?=$inter_unreg_supplies_grand_total;?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a>
                        </div>

                        <div style="clear:both;"></div>
                        <br/>

                        <table id="gsttablevl">
                        <tr>
                        <th>Description</th>
                        <th>Nil Rated Supplies</th>
                        <th>Exempted (Other than Nil Rated/non GST Supply)</th>
                        <th>Non GST Supplies</th>
                        </tr>

                        <tr>
                        <th>Intra-state supplies to registered person</th>
                        <td><?=inr_format($intra_reg_supplies_grand_total, 2);?></td>
                        <td>0.00</td>
                        <td>0.00</td>
                        </tr>
                        <tr>
                        <th>Intra-state supplies to unregistered person</th>
                        <td><?=inr_format($intra_unreg_supplies_grand_total, 2);?></td>
                        <td>0.00</td>
                        <td>0.00</td>
                        </tr>
                        <tr>
                        <th>Inter-state supplies to registered person</th>
                        <td><?=inr_format($inter_reg_supplies_grand_total, 2);?></td>
                        <td>0.00</td>
                        <td>0.00</td>
                        </tr>
                        <tr>
                        <th>Inter-state supplies to unregistered person</th>
                        <td><?=inr_format($inter_unreg_supplies_grand_total, 2);?></td>
                        <td>0.00</td>
                        <td>0.00</td>
                        </tr>

                        <tr>
                        <td></td>
                        <td><?=inr_format($Nil_rated_total, 2);?></td>
                        <td></td>
                        <td></td>
                        </tr>

                        </table>

                        <!-------------HSN wise Total Qty---------->
                        <br/>
                        <table id="gsttablevl" style="height:auto;">
                        <tr>
                        <th>HSN</th>
                        <th>Total Quantity</th>
                        <th>Total Taxable Value</th>
                        </tr>

                        <?php foreach ($hsn_list as $h):
                            $hsn_code = $h['hsn'];
                            $hsn_qty_stmt->bind_param(
                                "ssssssssssssssssssssssssssssss",
                                $hsn_code, $from_date, $to_date, $Login_user_TYPEvl, $tp_id,
                                $hsn_code, $from_date, $to_date, $Login_user_TYPEvl, $tp_id,
                                $hsn_code, $from_date, $to_date, $Login_user_TYPEvl, $tp_id,
                                $hsn_code, $from_date, $to_date, $Login_user_TYPEvl, $tp_id,
                                $hsn_code, $from_date, $to_date, $Login_user_TYPEvl, $tp_id,
                                $hsn_code, $from_date, $to_date, $Login_user_TYPEvl, $tp_id
                            );
                            $hsn_qty_stmt->execute();
                            $hsn_row = $hsn_qty_stmt->get_result()->fetch_assoc();
                            $net_qty = (int)($hsn_row['net_qty'] ?? 0);
                            $net_taxable_value = (float)($hsn_row['net_taxable_value'] ?? 0);
                        ?>
                        <tr>
                        <td style="text-align:left;"><?=htmlspecialchars($hsn_code);?></td>
                        <td style="text-align:left;"><?=$net_qty;?></td>
                        <td style="text-align:left;"><?=inr_format($net_taxable_value, 2);?></td>
                        </tr>
                        <?php endforeach; ?>
                        </table>

                        <?php $hsn_qty_stmt->close(); ?>

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
