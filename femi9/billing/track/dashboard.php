<?php
include("checksession.php");
include("config.php");
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$title = "Dashboard";
$today = date("Y-m-d");
$from_date = $_GET['from_date'] ?? $today;
$to_date   = $_GET['to_date']   ?? $today;
if (strtotime($from_date) > strtotime($to_date)) { [$from_date, $to_date] = [$to_date, $from_date]; }

// Every PO placed in range, with the TP's assigned district — the base set
// every stat card below is derived from.
$poStmt = $db_conn->prepare("
    SELECT po.id, po.territory_partner_id, tp.assigned_district
    FROM tp_purchase_orders po
    JOIN territory_partners tp ON tp.id = po.territory_partner_id
    WHERE po.order_date BETWEEN ? AND ?
");
$poStmt->bind_param('ss', $from_date, $to_date);
$poStmt->execute();
$rangePos = $poStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$poStmt->close();

$todayOrderCount = count($rangePos);
$poIds = array_column($rangePos, 'id');

$orderAmount = 0.0;
$receivedAmt = 0.0;
$locationOrderCounts = []; // district name => order count
$locationBoxCounts = [];   // district name => box count (to be delivered there)

foreach ($rangePos as $p) {
    $loc = $p['assigned_district'] ?: 'Unknown';
    $locationOrderCounts[$loc] = ($locationOrderCounts[$loc] ?? 0) + 1;
    if (!isset($locationBoxCounts[$loc])) $locationBoxCounts[$loc] = 0;
}

if (!empty($poIds)) {
    $placeholders = implode(',', array_fill(0, count($poIds), '?'));
    $types = str_repeat('i', count($poIds));

    $amtStmt = $db_conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM tp_purchase_order_items WHERE po_id IN ($placeholders)");
    $amtStmt->bind_param($types, ...$poIds);
    $amtStmt->execute();
    $orderAmount = (float)$amtStmt->get_result()->fetch_assoc()['total'];
    $amtStmt->close();

    // One box-count reading per PO (not per screenshot attempt) — a PO can
    // carry more than one tp_courier_payments row across retries, all with
    // the same total_boxes for the same cart, so this takes one row per po_id
    // rather than summing every attempt. Kept per-PO here (not just a grand
    // total) so it can be attributed back to that PO's own delivery location.
    $boxStmt = $db_conn->prepare("
        SELECT po_id, MIN(total_boxes) AS total_boxes FROM tp_courier_payments
        WHERE po_id IN ($placeholders)
        GROUP BY po_id
    ");
    $boxStmt->bind_param($types, ...$poIds);
    $boxStmt->execute();
    $boxesByPoId = [];
    foreach ($boxStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $b) {
        $boxesByPoId[(int)$b['po_id']] = (int)$b['total_boxes'];
    }
    $boxStmt->close();

    foreach ($rangePos as $p) {
        $loc = $p['assigned_district'] ?: 'Unknown';
        $locationBoxCounts[$loc] += $boxesByPoId[(int)$p['id']] ?? 0;
    }
    $totalBoxes = array_sum($boxesByPoId);

    $recvStmt = $db_conn->prepare("
        SELECT COALESCE(SUM(detected_amount), 0) AS total FROM tp_courier_payments
        WHERE po_id IN ($placeholders) AND status = 'accepted'
    ");
    $recvStmt->bind_param($types, ...$poIds);
    $recvStmt->execute();
    $receivedAmt = (float)$recvStmt->get_result()->fetch_assoc()['total'];
    $recvStmt->close();
} else {
    $totalBoxes = 0;
}

arsort($locationOrderCounts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $title;?> : <?php echo $business_name;?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .trk-filter-card { background: #fff; border-radius: 14px; padding: 16px 20px; margin-bottom: 18px; box-shadow: 0 1px 3px rgba(16,24,40,.06); }
        .trk-card {
            background: #fff; border-radius: 14px; padding: 20px; height: 100%;
            box-shadow: 0 1px 3px rgba(16,24,40,.06); cursor: default;
        }
        .trk-card.clickable { cursor: pointer; transition: box-shadow .15s, transform .15s; }
        .trk-card.clickable:hover { box-shadow: 0 4px 14px rgba(16,24,40,.14); transform: translateY(-2px); }
        .trk-card i { font-size: 26px; color: #667eea; margin-bottom: 8px; }
        .trk-card .label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; }
        .trk-card .value { font-size: 26px; font-weight: 700; color: #1f2937; margin-top: 4px; }
        .trk-card .sub { font-size: 11.5px; color: #9ca3af; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        <div class="app-container">
            <?php include("app-header.php");?>
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <h1 style="font-size:20px;margin-bottom:16px;"><?php echo $title;?></h1>

                        <div class="trk-filter-card">
                            <form method="get" class="row g-2 align-items-end">
                                <div class="col-md-4 col-6">
                                    <label class="form-label" style="font-size:11.5px;font-weight:600;color:#6b7280;">From Date</label>
                                    <input type="date" name="from_date" value="<?=htmlspecialchars($from_date)?>" class="form-control" max="<?=htmlspecialchars($today)?>">
                                </div>
                                <div class="col-md-4 col-6">
                                    <label class="form-label" style="font-size:11.5px;font-weight:600;color:#6b7280;">To Date</label>
                                    <input type="date" name="to_date" value="<?=htmlspecialchars($to_date)?>" class="form-control" max="<?=htmlspecialchars($today)?>">
                                </div>
                                <div class="col-md-4 col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="dashboard.php" class="btn btn-outline-secondary">Today</a>
                                </div>
                            </form>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4 col-sm-6">
                                <div class="trk-card">
                                    <i class="material-icons-outlined">receipt_long</i>
                                    <div class="label">Order Count</div>
                                    <div class="value"><?=$todayOrderCount?></div>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="trk-card">
                                    <i class="material-icons-outlined">payments</i>
                                    <div class="label">Order Amount</div>
                                    <div class="value">&#8377;<?=number_format($orderAmount, 2)?></div>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="trk-card clickable" data-bs-toggle="modal" data-bs-target="#locationModal">
                                    <i class="material-icons-outlined">location_on</i>
                                    <div class="label">Received Location Count</div>
                                    <div class="value"><?=count($locationOrderCounts)?></div>
                                    <div class="sub">Click to view locations &amp; box count</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="trk-card">
                                    <i class="material-icons-outlined">account_balance_wallet</i>
                                    <div class="label">Received Amt</div>
                                    <div class="value">&#8377;<?=number_format($receivedAmt, 2)?></div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Location List Modal -->
    <div class="modal fade" id="locationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
                <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                    <h6 class="modal-title" style="font-weight:700;color:#1f2937;">
                        <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#667eea;">location_on</i>
                        Locations — <?=htmlspecialchars(date('d-M-Y', strtotime($from_date)))?><?php if ($from_date !== $to_date): ?> to <?=htmlspecialchars(date('d-M-Y', strtotime($to_date)))?><?php endif; ?>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding:18px 22px;">
                    <?php if (empty($locationOrderCounts)): ?>
                    <div class="text-muted" style="font-size:13px;">No orders in this range.</div>
                    <?php else: ?>
                    <table class="table table-sm">
                        <thead><tr><th>Location</th><th class="text-end">Orders</th><th class="text-end">Boxes to Deliver</th></tr></thead>
                        <tbody>
                        <?php foreach ($locationOrderCounts as $loc => $cnt): ?>
                            <tr>
                                <td><?=htmlspecialchars($loc)?></td>
                                <td class="text-end"><?=$cnt?></td>
                                <td class="text-end"><?=$locationBoxCounts[$loc] ?? 0?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
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
