<?php
include("checksession.php");
require_once("include/GodownAccess.php");
error_reporting(0);
include("config.php");
date_default_timezone_set("Asia/Kolkata");

$transfer_id = (int)($_GET['id'] ?? 0);
if ($transfer_id <= 0) { header("Location: manage-pl-godown-transfers"); exit; }

// Transfer header
$stmt = $db_conn->prepare("
    SELECT t.*, g.gname AS godown_name, g.address_line1, g.address_line2,
           g.gstin, g.state, g.state_code, g.contact, g.email, g.logo,
           pln.name AS location_name
    FROM pl_godown_transfers t
    JOIN company_godown g ON g.id = t.godown_id AND (" . godown_finance_filter_sql($db_conn, 'g') . ")
    JOIN partner_location_nodes pln ON pln.id = t.location_id
    WHERE t.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $transfer_id);
$stmt->execute();
$transfer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transfer) { header("Location: manage-pl-godown-transfers"); exit; }

// Items
$items_res = $db_conn->query("
    SELECT p.productName, ti.quantity
    FROM pl_godown_transfer_items ti
    JOIN products p ON p.id = ti.product_id
    WHERE ti.transfer_id = $transfer_id
    ORDER BY p.productName ASC
");
$items     = $items_res ? $items_res->fetch_all(MYSQLI_ASSOC) : [];
$total_qty = array_sum(array_column($items, 'quantity'));

$is_out     = $transfer['transfer_type'] === 'location_to_godown';
$type_label = $is_out ? 'Location → Godown' : 'Godown → Location';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transfer Receipt <?php echo htmlspecialchars($transfer['ref_number']); ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
    <style>
        #divToPrint { padding: 20px 30px; }
        .receipt { width: 750px; margin: 0 auto; border: 1px solid #000; font-family: Arial, sans-serif; font-size: 13px; }
        .receipt-header { border-bottom: 2px solid #000; padding: 12px 16px; display: flex; align-items: center; gap: 16px; }
        .receipt-header img { max-height: 60px; }
        .godown-name { font-size: 17px; font-weight: bold; }
        .godown-sub { font-size: 12px; line-height: 1.7; color: #444; }
        .receipt-title { border-bottom: 1px solid #000; text-align: center; padding: 8px; font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .meta-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid #000; }
        .meta-table td { padding: 6px 12px; font-size: 13px; border-right: 1px solid #ddd; vertical-align: top; }
        .meta-table td:nth-child(odd) { font-weight: bold; width: 120px; color: #555; background: #fafafa; }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th { background: #f0f0f0; border: 1px solid #ccc; padding: 7px 10px; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
        .items-table td { border: 1px solid #ddd; padding: 7px 10px; font-size: 13px; }
        .items-table tfoot td { border-top: 2px solid #000; font-weight: bold; background: #f8f8f8; }
        .sign-row { width: 100%; border-collapse: collapse; border-top: 1px solid #000; }
        .sign-row td { padding: 30px 16px 10px; font-size: 12px; border-right: 1px solid #ddd; }
        .receipt-footer { border-top: 1px solid #000; padding: 7px 16px; display: flex; justify-content: space-between; font-size: 11px; color: #888; }

        @media print {
            .app-sidebar, .app-header, table[align="right"] { display: none !important; }
            #divToPrint { padding: 0; }
            .receipt { width: 100%; border: 1px solid #000; }
        }
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

            <script>
            function PrintDiv() {
                var content = document.getElementById('divToPrint').innerHTML;
                var w = window.open('', '_blank', 'width=800,height=700,left=100,top=60');
                w.document.open();
                w.document.write('<!DOCTYPE html><html><head>'
                    + '<meta charset="utf-8">'
                    + '<style>'
                    + 'body{font-family:Arial,sans-serif;margin:0;padding:0;}'
                    + '.receipt{width:750px;margin:20px auto;border:1px solid #000;font-size:13px;}'
                    + '.receipt-header{border-bottom:2px solid #000;padding:12px 16px;display:flex;align-items:center;gap:16px;}'
                    + '.receipt-header img{max-height:60px;}'
                    + '.godown-name{font-size:17px;font-weight:bold;}'
                    + '.godown-sub{font-size:12px;line-height:1.6;color:#444;}'
                    + '.receipt-title{border-bottom:1px solid #000;text-align:center;padding:8px;font-size:16px;font-weight:bold;letter-spacing:1px;}'
                    + '.meta-table{width:100%;border-collapse:collapse;border-bottom:1px solid #000;}'
                    + '.meta-table td{padding:6px 12px;font-size:13px;border-right:1px solid #ddd;vertical-align:top;}'
                    + '.meta-table td:nth-child(odd){font-weight:bold;width:120px;color:#555;}'
                    + '.items-table{width:100%;border-collapse:collapse;}'
                    + '.items-table th{background:#f0f0f0;border:1px solid #ccc;padding:7px 10px;font-size:12px;text-transform:uppercase;letter-spacing:.4px;}'
                    + '.items-table td{border:1px solid #ddd;padding:6px 10px;font-size:13px;}'
                    + '.items-table tfoot td{border-top:2px solid #000;font-weight:bold;background:#f8f8f8;}'
                    + '.receipt-footer{border-top:1px solid #000;padding:8px 16px;display:flex;justify-content:space-between;font-size:11px;color:#888;margin-top:4px;}'
                    + '.sign-row{width:100%;border-collapse:collapse;border-top:1px solid #000;margin-top:4px;}'
                    + '.sign-row td{padding:30px 16px 8px;font-size:12px;border-right:1px solid #ddd;}'
                    + '</style></head>'
                    + '<body onload="window.print()">' + content + '</body></html>');
                w.document.close();
            }
            </script>

            <!-- Action buttons -->
            <table align="right" style="margin:10px 20px;">
                <tr>
                    <td><button type="button" onclick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">
                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">print</i> Print
                    </button></td>
                    <td><button type="button" onclick="window.location='manage-pl-godown-transfers';" class="btn btn-primary m-b-xs m-r-xs">
                        ← All Transfers
                    </button></td>
                </tr>
            </table>
            <div style="clear:both;"></div>

            <div id="divToPrint">

<div class="receipt">

    <!-- Godown header -->
    <div class="receipt-header">
        <?php if (!empty($transfer['logo'])): ?>
        <img src="<?= htmlspecialchars($transfer['logo']); ?>" alt="logo">
        <?php endif; ?>
        <div>
            <div class="godown-name"><?= htmlspecialchars($transfer['godown_name']); ?></div>
            <div class="godown-sub">
                <?= htmlspecialchars($transfer['address_line1']); ?>
                <?php if (!empty($transfer['address_line2'])): ?>, <?= htmlspecialchars($transfer['address_line2']); ?><?php endif; ?><br>
                <?php if (!empty($transfer['gstin'])): ?><strong>GSTIN:</strong> <?= htmlspecialchars($transfer['gstin']); ?> &nbsp;<?php endif; ?>
                <?php if (!empty($transfer['state'])): ?><strong>State:</strong> <?= htmlspecialchars($transfer['state']); ?><?php endif; ?><br>
                <?php if (!empty($transfer['contact'])): ?><strong>Contact:</strong> <?= htmlspecialchars($transfer['contact']); ?><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Title -->
    <div class="receipt-title">STOCK TRANSFER RECEIPT</div>

    <!-- Transfer meta -->
    <table class="meta-table">
        <tr>
            <td>Ref No.</td>
            <td><strong><?= htmlspecialchars($transfer['ref_number']); ?></strong></td>
            <td>Date</td>
            <td><?= date('d M Y', strtotime($transfer['transfer_date'])); ?></td>
        </tr>
        <tr>
            <td>Transfer Type</td>
            <td><?= htmlspecialchars($type_label); ?></td>
            <td>Partner Location</td>
            <td><?= htmlspecialchars($transfer['location_name']); ?></td>
        </tr>
        <tr>
            <td>Created By</td>
            <td><?= htmlspecialchars($transfer['created_by']); ?></td>
            <td>Created At</td>
            <td><?= date('d M Y, h:i A', strtotime($transfer['created_at'])); ?></td>
        </tr>
        <?php if (!empty($transfer['note'])): ?>
        <tr>
            <td>Note</td>
            <td colspan="3"><?= htmlspecialchars($transfer['note']); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <!-- Items -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:40px;text-align:center;">#</th>
                <th>Product Name</th>
                <th style="width:100px;text-align:center;">Quantity</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $item): ?>
            <tr>
                <td style="text-align:center;color:#999;"><?= $i + 1; ?></td>
                <td><?= htmlspecialchars($item['productName']); ?></td>
                <td style="text-align:center;font-weight:600;"><?= (int)$item['quantity']; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" style="text-align:right;">Total Units</td>
                <td style="text-align:center;"><?= number_format($total_qty); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Signature row -->
    <table class="sign-row">
        <tr>
            <td style="width:50%;">Received By (Signature &amp; Stamp)</td>
            <td style="text-align:right;">Authorised Signatory<br><strong><?= htmlspecialchars($transfer['godown_name']); ?></strong></td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="receipt-footer">
        <span>This is a computer-generated document.</span>
        <span>Printed on: <?= date('d M Y, h:i A'); ?></span>
    </div>

</div><!-- .receipt -->

            </div><!-- #divToPrint -->

        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
