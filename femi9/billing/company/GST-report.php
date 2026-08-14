<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once "include/TpGstHelper.php";
include("config.php");
$title = "GST Report";
date_default_timezone_set("Asia/Kolkata");
$current_date = date("Y-m-d");
error_reporting(0);

// Indian financial year (Apr-Mar) quick-select, e.g. "2026-27" -> Apr 2026 - Mar 2027.
function gst_report_fy_bounds($fy_start_year) {
    return [$fy_start_year . "-04-01", ($fy_start_year + 1) . "-03-31"];
}
$current_month = (int)date("n");
$current_fy_start = $current_month >= 4 ? (int)date("Y") : (int)date("Y") - 1;

if ($_REQUEST['fy'] != NULL && $_REQUEST['fromdate'] == NULL) {
    [$get_from_date, $get_to_date] = gst_report_fy_bounds((int)$_REQUEST['fy']);
} elseif ($_REQUEST['fromdate'] != NULL) {
    $get_from_date = mysqli_real_escape_string($db_conn, $_REQUEST['fromdate']);
    $get_to_date   = mysqli_real_escape_string($db_conn, $_REQUEST['todate']);
} else {
    [$get_from_date, $get_to_date] = gst_report_fy_bounds($current_fy_start);
}
$get_to_date = min($get_to_date, $current_date);

$godown_ids_sql = godown_ids_subquery($db_conn);

// Unified per-line rows across all four outward-supply channels. Each line carries
// hsn, gst_percentage, taxable_value, gst_amount, is_intra, is_registered, plus
// invoice-level fields (inv_number/date/channel) for the detail table.
$lines = [];

// Channel 1: network invoices (SS/ST/DT/SHOP) — user_invoice_items, taxable value
// strips embedded GST via total-gstamount_total (same fix used across all GST reports
// for 'inclusive'-priced products).
$sql1 = "
    SELECT ui.inv_number, uii.date, uii.hsn, uii.gst_percentage,
           (uii.total - uii.gstamount_total) AS taxable_value,
           uii.gstamount_total AS gst_amount,
           uii.gst_type, uii.buyer_gsttype, uii.qty
    FROM user_invoice_items uii
    LEFT JOIN user_invoice ui ON ui.inv_id = uii.inv_id
    WHERE uii.from_user_type = 'company'
      AND uii.from_user_id IN ($godown_ids_sql)
      AND uii.date BETWEEN '$get_from_date' AND '$get_to_date'
";
$res1 = mysqli_query($db_conn, $sql1);
while ($row = mysqli_fetch_assoc($res1)) {
    $lines[] = [
        'channel' => 'Network Sale', 'inv_number' => $row['inv_number'], 'date' => $row['date'],
        'hsn' => $row['hsn'], 'gst_percentage' => (float)$row['gst_percentage'],
        'taxable_value' => (float)$row['taxable_value'], 'gst_amount' => (float)$row['gst_amount'],
        'is_intra' => $row['gst_type'] == 'inner', 'is_registered' => $row['buyer_gsttype'] == 'register',
        'qty' => (float)$row['qty'],
    ];
}

// Channel 2: customer invoices — invoice_items, same taxable-value fix.
$sql2 = "
    SELECT i.inv_number, ii.date, ii.hsn, ii.gst_percentage,
           (ii.total - ii.gstamount_total) AS taxable_value,
           ii.gstamount_total AS gst_amount,
           ii.gst_type, ii.buyer_gsttype, ii.qty
    FROM invoice_items ii
    LEFT JOIN invoice i ON i.inv_id = ii.inv_id
    WHERE ii.user_type = 'company'
      AND ii.user_id IN ($godown_ids_sql)
      AND ii.date BETWEEN '$get_from_date' AND '$get_to_date'
";
$res2 = mysqli_query($db_conn, $sql2);
while ($row = mysqli_fetch_assoc($res2)) {
    $lines[] = [
        'channel' => 'Customer Sale', 'inv_number' => $row['inv_number'], 'date' => $row['date'],
        'hsn' => $row['hsn'], 'gst_percentage' => (float)$row['gst_percentage'],
        'taxable_value' => (float)$row['taxable_value'], 'gst_amount' => (float)$row['gst_amount'],
        'is_intra' => $row['gst_type'] == 'inner', 'is_registered' => $row['buyer_gsttype'] == 'register',
        'qty' => (float)$row['qty'],
    ];
}

// Channel 3: OT (offline/on-the-spot) sales — ot_sales, gst rate column is 'gst'.
$sql3 = "
    SELECT i.inv_number, s.date, s.hsn, s.gst AS gst_percentage,
           (s.total - s.gst_amount) AS taxable_value,
           s.gst_amount,
           s.gst_type, s.buyer_gsttype, s.qty
    FROM ot_sales s
    LEFT JOIN ot_sales_invoice i ON i.tempid = s.tempid
    WHERE s.godownid IN ($godown_ids_sql)
      AND s.date BETWEEN '$get_from_date' AND '$get_to_date'
";
$res3 = mysqli_query($db_conn, $sql3);
while ($row = mysqli_fetch_assoc($res3)) {
    $lines[] = [
        'channel' => 'OT Sale', 'inv_number' => $row['inv_number'], 'date' => $row['date'],
        'hsn' => $row['hsn'], 'gst_percentage' => (float)$row['gst_percentage'],
        'taxable_value' => (float)$row['taxable_value'], 'gst_amount' => (float)$row['gst_amount'],
        'is_intra' => $row['gst_type'] == 'inner', 'is_registered' => $row['buyer_gsttype'] == 'register',
        'qty' => (float)$row['qty'],
    ];
}

// Channel 4: TP invoices (company -> territory partner stock transfers), godown-sourced only.
$tp_lines = tp_sales_gst_lines($db_conn, $get_from_date, $get_to_date, "tpi.source_godown_id IN ($godown_ids_sql)");
foreach ($tp_lines as $l) {
    $lines[] = [
        'channel' => 'TP Transfer', 'inv_number' => $l['invoice_number'], 'date' => $l['invoice_date'],
        'hsn' => $l['hsn'] ?? '', 'gst_percentage' => (float)$l['gst_percentage'],
        'taxable_value' => (float)$l['taxable_value'], 'gst_amount' => (float)$l['gst_amount'],
        'is_intra' => (bool)$l['is_intra'], 'is_registered' => (bool)$l['is_registered'],
        'qty' => (float)($l['quantity'] ?? 0),
    ];
}

// ----- Aggregate: outward-supply summary (nil-rated vs taxable, intra/inter x reg/unreg) -----
$summary = [
    'nil_reg_intra' => 0, 'nil_unreg_intra' => 0, 'nil_reg_inter' => 0, 'nil_unreg_inter' => 0,
    'tax_reg_intra' => 0, 'tax_unreg_intra' => 0, 'tax_reg_inter' => 0, 'tax_unreg_inter' => 0,
];
$tax_payable = ['cgst' => 0, 'sgst' => 0, 'igst' => 0];

// HSN-wise summary: keyed by "hsn|rate"
$hsn_summary = [];

foreach ($lines as $l) {
    $bucket_tax = $l['gst_percentage'] > 0 ? 'tax' : 'nil';
    $bucket_geo = $l['is_intra'] ? 'intra' : 'inter';
    $bucket_reg = $l['is_registered'] ? 'reg' : 'unreg';
    $summary["{$bucket_tax}_{$bucket_reg}_{$bucket_geo}"] += $l['taxable_value'];

    if ($l['is_intra']) {
        $tax_payable['cgst'] += $l['gst_amount'] / 2;
        $tax_payable['sgst'] += $l['gst_amount'] / 2;
    } else {
        $tax_payable['igst'] += $l['gst_amount'];
    }

    $hsn_key = ($l['hsn'] ?: 'N/A') . '|' . $l['gst_percentage'];
    if (!isset($hsn_summary[$hsn_key])) {
        $hsn_summary[$hsn_key] = [
            'hsn' => $l['hsn'] ?: 'N/A', 'rate' => $l['gst_percentage'],
            'qty' => 0, 'taxable_value' => 0, 'gst_amount' => 0,
        ];
    }
    $hsn_summary[$hsn_key]['qty']           += $l['qty'];
    $hsn_summary[$hsn_key]['taxable_value'] += $l['taxable_value'];
    $hsn_summary[$hsn_key]['gst_amount']    += $l['gst_amount'];
}
usort($hsn_summary, fn($a, $b) => $a['hsn'] <=> $b['hsn'] ?: $a['rate'] <=> $b['rate']);

$total_nil_rated   = $summary['nil_reg_intra'] + $summary['nil_unreg_intra'] + $summary['nil_reg_inter'] + $summary['nil_unreg_inter'];
$total_taxable     = $summary['tax_reg_intra'] + $summary['tax_unreg_intra'] + $summary['tax_reg_inter'] + $summary['tax_unreg_inter'];
$total_gst_payable = $tax_payable['cgst'] + $tax_payable['sgst'] + $tax_payable['igst'];
$grand_total_supply = $total_nil_rated + $total_taxable;

// ----- Invoice-level detail rows (grouped by invoice, for audit trail) -----
$invoice_rows = [];
foreach ($lines as $l) {
    $key = $l['channel'] . '|' . $l['inv_number'];
    if (!isset($invoice_rows[$key])) {
        $invoice_rows[$key] = [
            'channel' => $l['channel'], 'inv_number' => $l['inv_number'], 'date' => $l['date'],
            'taxable_value' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'is_intra' => $l['is_intra'],
        ];
    }
    $invoice_rows[$key]['taxable_value'] += $l['taxable_value'];
    if ($l['is_intra']) {
        $invoice_rows[$key]['cgst'] += $l['gst_amount'] / 2;
        $invoice_rows[$key]['sgst'] += $l['gst_amount'] / 2;
    } else {
        $invoice_rows[$key]['igst'] += $l['gst_amount'];
    }
}
$invoice_rows = array_values($invoice_rows);
usort($invoice_rows, fn($a, $b) => strcmp($a['date'], $b['date']));

// FY quick-select options: current + last 5 financial years.
$fy_options = [];
for ($i = 0; $i < 6; $i++) {
    $y = $current_fy_start - $i;
    $fy_options[] = ['start' => $y, 'label' => $y . '-' . ($y + 1)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title); ?> : <?php echo htmlspecialchars($business_name); ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style type="text/css">
    #gsttablevl tr th, #gsttablevl tr td { border: 1px solid #000; padding: 5px; }
    #gsttablevl tr td { text-align: right; }
    #gsttablevl tr td:first-child, #gsttablevl tr td.text-left { text-align: left; }
    .gst-section-title { margin-top: 25px; margin-bottom: 10px; }
    .gst-summary-card { background: #f8f9fa; border-radius: 6px; padding: 15px; margin-bottom: 10px; }
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
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                            <tr><td><?php echo htmlspecialchars($title); ?></td></tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">

                                        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                                            <div class="example-container">
                                                <div class="example-content">
                                                    <label class="form-label">Financial Year</label>
                                                    <select name="fy" class="form-control">
                                                        <option value="">Custom Range</option>
                                                        <?php foreach ($fy_options as $fy): ?>
                                                        <option value="<?= $fy['start'] ?>" <?= (($_REQUEST['fy'] ?? '') == $fy['start']) ? 'selected' : '' ?>><?= $fy['label'] ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label class="form-label">From Date</label>
                                                    <input type="date" name="fromdate" value="<?= $get_from_date ?>" required class="form-control">
                                                    <label class="form-label">To Date</label>
                                                    <input type="date" name="todate" required value="<?= $get_to_date ?>" class="form-control">
                                                    <br/>
                                                    <button type="submit" name="search-network" class="btn btn-primary">
                                                        <i class="material-icons">search</i>Search
                                                    </button>
                                                    <a href="GST-report-export.php?fromdate=<?= $get_from_date ?>&todate=<?= $get_to_date ?>" class="btn btn-success" title="Export to Excel">
                                                        <i class="material-icons">description</i>Export Excel
                                                    </a>
                                                </div>
                                            </div>
                                        </form>
                                        <br/>

                                        <!-- ===================== TAX PAYABLE SUMMARY ===================== -->
                                        <h3 class="gst-section-title">Tax Payable Summary</h3>
                                        <div class="row">
                                            <div class="col-md-3"><div class="gst-summary-card"><strong>Total Outward Supply</strong><br/><span style="font-size:20px;"><?= inr_format($grand_total_supply, 2) ?></span></div></div>
                                            <div class="col-md-3"><div class="gst-summary-card"><strong>CGST Payable</strong><br/><span style="font-size:20px;"><?= inr_format($tax_payable['cgst'], 2) ?></span></div></div>
                                            <div class="col-md-3"><div class="gst-summary-card"><strong>SGST Payable</strong><br/><span style="font-size:20px;"><?= inr_format($tax_payable['sgst'], 2) ?></span></div></div>
                                            <div class="col-md-3"><div class="gst-summary-card"><strong>IGST Payable</strong><br/><span style="font-size:20px;"><?= inr_format($tax_payable['igst'], 2) ?></span></div></div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-3"><div class="gst-summary-card"><strong>Total Tax Payable</strong><br/><span style="font-size:20px; color:#c0392b;"><?= inr_format($total_gst_payable, 2) ?></span></div></div>
                                            <div class="col-md-3"><div class="gst-summary-card"><strong>Nil Rated Supplies</strong><br/><span style="font-size:20px;"><?= inr_format($total_nil_rated, 2) ?></span></div></div>
                                            <div class="col-md-3"><div class="gst-summary-card"><strong>Taxable Supplies</strong><br/><span style="font-size:20px;"><?= inr_format($total_taxable, 2) ?></span></div></div>
                                        </div>

                                        <!-- ===================== NIL RATED / TAXABLE SUPPLY BREAKDOWN ===================== -->
                                        <h3 class="gst-section-title">Supply Breakdown — Nil Rated vs Taxable</h3>
                                        <table width="100%" class="ReportTablevl" id="gsttablevl">
                                            <thead>
                                                <tr>
                                                    <th>Supply Type</th>
                                                    <th>Nil Rated (Intra, Reg.)</th>
                                                    <th>Nil Rated (Intra, Unreg.)</th>
                                                    <th>Nil Rated (Inter, Reg.)</th>
                                                    <th>Nil Rated (Inter, Unreg.)</th>
                                                    <th>Taxable (Intra, Reg.)</th>
                                                    <th>Taxable (Intra, Unreg.)</th>
                                                    <th>Taxable (Inter, Reg.)</th>
                                                    <th>Taxable (Inter, Unreg.)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="text-left">All Outward Supplies</td>
                                                    <td><?= inr_format($summary['nil_reg_intra'], 2) ?></td>
                                                    <td><?= inr_format($summary['nil_unreg_intra'], 2) ?></td>
                                                    <td><?= inr_format($summary['nil_reg_inter'], 2) ?></td>
                                                    <td><?= inr_format($summary['nil_unreg_inter'], 2) ?></td>
                                                    <td><?= inr_format($summary['tax_reg_intra'], 2) ?></td>
                                                    <td><?= inr_format($summary['tax_unreg_intra'], 2) ?></td>
                                                    <td><?= inr_format($summary['tax_reg_inter'], 2) ?></td>
                                                    <td><?= inr_format($summary['tax_unreg_inter'], 2) ?></td>
                                                </tr>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td class="text-left"><b>Total</b></td>
                                                    <td colspan="4"><b><?= inr_format($total_nil_rated, 2) ?></b></td>
                                                    <td colspan="4"><b><?= inr_format($total_taxable, 2) ?></b></td>
                                                </tr>
                                            </tfoot>
                                        </table>

                                        <!-- ===================== HSN-WISE SUMMARY ===================== -->
                                        <h3 class="gst-section-title">HSN / Tax Rate-Wise Summary</h3>
                                        <table width="100%" class="ReportTablevl" id="gsttablevl">
                                            <thead>
                                                <tr>
                                                    <th>HSN Code</th>
                                                    <th>GST Rate</th>
                                                    <th>Total Qty</th>
                                                    <th>Taxable Value</th>
                                                    <th>GST Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($hsn_summary)): ?>
                                                <tr><td colspan="5" style="text-align:center; padding:20px;">No records found.</td></tr>
                                                <?php else: foreach ($hsn_summary as $h): ?>
                                                <tr>
                                                    <td class="text-left"><?= htmlspecialchars($h['hsn']) ?></td>
                                                    <td><?= $h['rate'] > 0 ? $h['rate'] . '%' : 'Nil' ?></td>
                                                    <td><?= number_format($h['qty'], 0) ?></td>
                                                    <td><?= inr_format($h['taxable_value'], 2) ?></td>
                                                    <td><?= inr_format($h['gst_amount'], 2) ?></td>
                                                </tr>
                                                <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>

                                        <!-- ===================== INVOICE-LEVEL DETAIL ===================== -->
                                        <h3 class="gst-section-title">Invoice-Wise Detail</h3>
                                        <table width="100%" class="ReportTablevl" id="gsttablevl">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Channel</th>
                                                    <th>Date</th>
                                                    <th>Invoice Number</th>
                                                    <th>Taxable Amount</th>
                                                    <th>CGST</th>
                                                    <th>SGST</th>
                                                    <th>IGST</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($invoice_rows)): ?>
                                                <tr>
                                                    <td colspan="8" style="text-align:center; padding:20px;">No records found.</td>
                                                </tr>
                                                <?php else: ?>
                                                <?php $i = 0; foreach ($invoice_rows as $row): $i++; ?>
                                                <tr>
                                                    <td><?= $i ?></td>
                                                    <td class="text-left"><?= htmlspecialchars($row['channel']) ?></td>
                                                    <td><?= date("d/m/Y", strtotime($row['date'])) ?></td>
                                                    <td class="text-left"><?= htmlspecialchars($row['inv_number']) ?></td>
                                                    <td><?= inr_format($row['taxable_value'], 2) ?></td>
                                                    <td><?= $row['is_intra'] ? inr_format($row['cgst'], 2) : '' ?></td>
                                                    <td><?= $row['is_intra'] ? inr_format($row['sgst'], 2) : '' ?></td>
                                                    <td><?= !$row['is_intra'] ? inr_format($row['igst'], 2) : '' ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
