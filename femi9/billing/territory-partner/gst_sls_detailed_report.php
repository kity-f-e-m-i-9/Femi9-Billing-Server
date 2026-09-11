<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$from_date     = $_REQUEST['frd']  ?? '';
$to_date       = $_REQUEST['tod']  ?? '';
$gst_type      = $_REQUEST['data1'] ?? '';
$buyer_gsttype = $_REQUEST['data2'] ?? '';

$tp_id = $Login_user_IDvl;

if ($gst_type == "inner" && $buyer_gsttype == "register")
    $lable_header = "Intra-state (Registered person)";
elseif ($gst_type == "inner" && $buyer_gsttype == "unregister")
    $lable_header = "Intra-state (Unregistered person)";
elseif ($gst_type == "outer" && $buyer_gsttype == "register")
    $lable_header = "Inter-state (Registered person)";
else
    $lable_header = "Inter-state (Unregistered person)";

// Same "intra-state" convention used on gstr1.php: any non-'outer' value
// counts as intra, since some legacy invoice rows carry gst_type='0'.
// gst_type exists on both the items table and the joined parent invoice
// table, so each block below builds its own alias-qualified condition
// rather than sharing one unqualified string (which is ambiguous once
// both tables are in scope).
$intraOp = $gst_type == 'outer' ? '=' : '!=';
$is_intra_page = ($gst_type != 'outer');

// BLOCK 1: Shop/order sales — items carry the GST-corrected taxable value
// (total - gstamount_total; see gstr1.php for why), joined back to
// user_invoice for the invoice number and to shop for buyer details.
$select_Report = "
    SELECT
        ui.inv_number,
        uii.date,
        SUM(uii.total - uii.gstamount_total) AS total_sls_amount,
        SUM(uii.gstamount_total) AS gst_amount,
        sh.name          AS cust_name,
        sh.mobile_number AS cust_mobile,
        sh.gstin         AS cust_gstin
    FROM user_invoice_items uii
    LEFT JOIN user_invoice ui ON ui.inv_id = uii.inv_id
    LEFT JOIN shop sh ON sh.temp_id = uii.to_user_id
    WHERE uii.from_user_type = ?
      AND uii.from_user_id   = ?
      AND uii.buyer_gsttype  = ?
      AND uii.gst_type $intraOp 'outer'
      AND uii.date BETWEEN ? AND ?
    GROUP BY uii.inv_id, ui.inv_number, uii.date, sh.name, sh.mobile_number, sh.gstin
    ORDER BY uii.date ASC
";
$stmt = $db_conn->prepare($select_Report);
$stmt->bind_param("sisss", $Login_user_TYPEvl, $tp_id, $buyer_gsttype, $from_date, $to_date);
$stmt->execute();
$rows1 = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$total1 = 0; $total_gst1 = 0;
foreach ($rows1 as $row) { $total1 += (float)$row['total_sls_amount']; $total_gst1 += (float)$row['gst_amount']; }

// BLOCK 2: Direct customer sales — invoice_items + customers, same
// taxable-value correction.
$select_Report2 = "
    SELECT
        i.inv_number,
        ii.date,
        SUM(ii.total - ii.gstamount_total) AS total_sls_amount,
        SUM(ii.gstamount_total) AS gst_amount,
        c.name   AS cust_name,
        c.mobile AS cust_mobile,
        c.gstin  AS cust_gstin
    FROM invoice_items ii
    LEFT JOIN invoice i ON i.inv_id = ii.inv_id
    LEFT JOIN customers c ON c.id = ii.customer_id
    WHERE ii.user_type = ?
      AND ii.user_id   = ?
      AND ii.buyer_gsttype = ?
      AND ii.gst_type $intraOp 'outer'
      AND ii.date BETWEEN ? AND ?
    GROUP BY ii.inv_id, i.inv_number, ii.date, c.name, c.mobile, c.gstin
    ORDER BY ii.date ASC
";
$stmt2 = $db_conn->prepare($select_Report2);
$stmt2->bind_param("sisss", $Login_user_TYPEvl, $tp_id, $buyer_gsttype, $from_date, $to_date);
$stmt2->execute();
$rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();
$total2 = 0; $total_gst2 = 0;
foreach ($rows2 as $row) { $total2 += (float)$row['total_sls_amount']; $total_gst2 += (float)$row['gst_amount']; }

$overall_total = $total1 + $total2;
$overall_gst   = $total_gst1 + $total_gst2;

// Effective GST% for a row = gst_amount / taxable_value * 100 — same helper
// convention as company/gst_sls_detailed_report.php.
$gst_slabs = [0, 5, 12, 18, 28];
function gst_percentage_label($taxable_value, $gst_amount, $gst_slabs) {
    if ((float)$taxable_value == 0.0) return $gst_amount == 0 ? '0%' : 'Mixed';
    $rate = round(($gst_amount / $taxable_value) * 100, 1);
    foreach ($gst_slabs as $slab) {
        if (abs($rate - $slab) <= 0.3) return $slab . '%';
    }
    return $rate . '% (Mixed)';
}

// Splits a row's gst_amount into cgst/sgst/igst. The whole page is already
// filtered to one $gst_type (intra 'inner' vs inter 'outer'), so every row
// splits the same way — intra halves into CGST+SGST, inter goes to IGST.
function split_gst($gst_amount, $is_intra) {
    if ($is_intra) { $half = $gst_amount / 2; return [$half, $half, 0]; }
    return [0, 0, $gst_amount];
}
[$grand_cgst, $grand_sgst, $grand_igst] = split_gst($overall_gst, $is_intra_page);

// ✅ Excel (CSV) export — same rows/columns as the on-screen table.
if (isset($_REQUEST['export']) && $_REQUEST['export'] == 'csv') {
    ob_start();
    $sn = 0;
    $csv_rows = [];
    $csv_rows[] = ['#', 'Customer Type', 'Customer Name', 'Customer Mobile', 'GSTIN', 'Invoice Number', 'Invoice Date', 'GST %', 'Taxable Value', 'CGST', 'SGST', 'IGST', 'Total Sales Amount'];

    foreach ($rows1 as $row) {
        $sn++;
        [$cgst, $sgst, $igst] = split_gst($row['gst_amount'], $is_intra_page);
        $csv_rows[] = [
            $sn, 'Shop', $row['cust_name'], $row['cust_mobile'], $row['cust_gstin'], $row['inv_number'],
            date("d/m/Y", strtotime($row['date'])),
            gst_percentage_label($row['total_sls_amount'], $row['gst_amount'], $gst_slabs),
            number_format($row['total_sls_amount'], 2, '.', ''),
            number_format($cgst, 2, '.', ''),
            number_format($sgst, 2, '.', ''),
            number_format($igst, 2, '.', ''),
            number_format($row['total_sls_amount'] + $row['gst_amount'], 2, '.', ''),
        ];
    }
    foreach ($rows2 as $row) {
        $sn++;
        [$cgst, $sgst, $igst] = split_gst($row['gst_amount'], $is_intra_page);
        $csv_rows[] = [
            $sn, 'Customer', $row['cust_name'], $row['cust_mobile'], $row['cust_gstin'], $row['inv_number'],
            date("d/m/Y", strtotime($row['date'])),
            gst_percentage_label($row['total_sls_amount'], $row['gst_amount'], $gst_slabs),
            number_format($row['total_sls_amount'], 2, '.', ''),
            number_format($cgst, 2, '.', ''),
            number_format($sgst, 2, '.', ''),
            number_format($igst, 2, '.', ''),
            number_format($row['total_sls_amount'] + $row['gst_amount'], 2, '.', ''),
        ];
    }
    $csv_rows[] = ['', '', '', '', '', '', '', 'Grand Total',
        number_format($overall_total, 2, '.', ''),
        number_format($grand_cgst, 2, '.', ''),
        number_format($grand_sgst, 2, '.', ''),
        number_format($grand_igst, 2, '.', ''),
        number_format($overall_total + $overall_gst, 2, '.', ''),
    ];

    $csv_content = '';
    foreach ($csv_rows as $csv_row) {
        $csv_content .= implode(',', array_map(function ($v) {
            return '"' . str_replace('"', '""', $v) . '"';
        }, $csv_row)) . "\n";
    }

    ob_end_clean();
    header("Content-type: text/csv");
    header("Content-Disposition: attachment; filename=GST_Sales_Detailed_Report.csv");
    echo $csv_content;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GSTR1 : <?php echo htmlspecialchars($business_name); ?></title>
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
    #gsttablevl tr th { border: 1px solid #000; padding: 5px; }
    #gsttablevl tr td { border: 1px solid #000; padding: 5px; }
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
                                            <td>
                                                <h1>GSTR1 &gt; Detailed Sales Report</h1>
                                                <h4>(Shop, Customer)</h4>
                                                <h5><?= htmlspecialchars($lable_header) ?></h5>
                                            </td>
                                            <td align="right" valign="top">
                                                <a href="?data1=<?= urlencode($gst_type) ?>&amp;data2=<?= urlencode($buyer_gsttype) ?>&amp;frd=<?= urlencode($from_date) ?>&amp;tod=<?= urlencode($to_date) ?>&amp;export=csv" title="Export to Excel"><img src="../../assets/images/excel-3-32.png"></a>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <table style="width:100%;" id="gsttablevl">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Customer Type</th>
                                        <th>Customer Name</th>
                                        <th>Customer Mobile</th>
                                        <th>GSTIN</th>
                                        <th>Invoice Number</th>
                                        <th>Invoice Date</th>
                                        <th>GST %</th>
                                        <th>Taxable Value</th>
                                        <th>CGST</th>
                                        <th>SGST</th>
                                        <th>IGST</th>
                                        <th>Total Sales Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $sn = 0; ?>

                                    <?php foreach ($rows1 as $row): $sn++;
                                        [$cgst, $sgst, $igst] = split_gst($row['gst_amount'], $is_intra_page);
                                    ?>
                                    <tr>
                                        <td><?= $sn ?></td>
                                        <td>Shop</td>
                                        <td><?= htmlspecialchars($row['cust_name']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_mobile']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_gstin']) ?></td>
                                        <td><?= htmlspecialchars($row['inv_number']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['date'])) ?></td>
                                        <td align="center"><?= gst_percentage_label($row['total_sls_amount'], $row['gst_amount'], $gst_slabs) ?></td>
                                        <td align="right"><?= inr_format($row['total_sls_amount'], 2) ?></td>
                                        <td align="right"><?= inr_format($cgst, 2) ?></td>
                                        <td align="right"><?= inr_format($sgst, 2) ?></td>
                                        <td align="right"><?= inr_format($igst, 2) ?></td>
                                        <td align="right"><b><?= inr_format($row['total_sls_amount'] + $row['gst_amount'], 2) ?></b></td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php foreach ($rows2 as $row): $sn++;
                                        [$cgst, $sgst, $igst] = split_gst($row['gst_amount'], $is_intra_page);
                                    ?>
                                    <tr>
                                        <td><?= $sn ?></td>
                                        <td>Customer</td>
                                        <td><?= htmlspecialchars($row['cust_name']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_mobile']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_gstin']) ?></td>
                                        <td><?= htmlspecialchars($row['inv_number']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['date'])) ?></td>
                                        <td align="center"><?= gst_percentage_label($row['total_sls_amount'], $row['gst_amount'], $gst_slabs) ?></td>
                                        <td align="right"><?= inr_format($row['total_sls_amount'], 2) ?></td>
                                        <td align="right"><?= inr_format($cgst, 2) ?></td>
                                        <td align="right"><?= inr_format($sgst, 2) ?></td>
                                        <td align="right"><?= inr_format($igst, 2) ?></td>
                                        <td align="right"><b><?= inr_format($row['total_sls_amount'] + $row['gst_amount'], 2) ?></b></td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php if ($sn === 0): ?>
                                    <tr>
                                        <td colspan="13" style="text-align:center; padding:20px;">No records found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="8" align="right"><b>Grand Total</b></td>
                                        <td align="right"><b><?= inr_format($overall_total, 2) ?></b></td>
                                        <td align="right"><b><?= inr_format($grand_cgst, 2) ?></b></td>
                                        <td align="right"><b><?= inr_format($grand_sgst, 2) ?></b></td>
                                        <td align="right"><b><?= inr_format($grand_igst, 2) ?></b></td>
                                        <td align="right"><b><?= inr_format($overall_total + $overall_gst, 2) ?></b></td>
                                    </tr>
                                </tfoot>
                            </table>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
