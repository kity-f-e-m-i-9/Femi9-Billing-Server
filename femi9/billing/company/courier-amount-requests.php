<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
include("config.php");
require_once __DIR__ . '/../shared/TpCourierAmountRequest.php';
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

// Oversight of every TP→Sales BDM courier-amount change request, regardless
// of which BDM it's routed to — Company can see who approved what and by
// how much, and can also review a pending request directly (e.g. if the
// assigned BDM is slow to act) via courier-amount-request-review-ajax.php.
$title = "Courier Amount Requests";
tpEnsureCourierAmountRequestTable($db_conn);

$filter_status = $_GET['status'] ?? '';
if (!in_array($filter_status, ['pending', 'approved', 'rejected', ''], true)) { $filter_status = ''; }
$filter_from = $_GET['from_date'] ?? date('Y-m-01');
$filter_to   = $_GET['to_date']   ?? date('Y-m-d');

$where = ["DATE(r.created_at) BETWEEN ? AND ?"];
$params = [$filter_from, $filter_to];
$types = 'ss';
if ($filter_status !== '') {
    $where[] = "r.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
$sql = "
    SELECT r.*, tp.name AS tp_name, tp.tp_id AS tp_code
    FROM tp_courier_amount_requests r
    JOIN territory_partners tp ON tp.id = r.territory_partner_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.created_at DESC
";
$stmt = $db_conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pendingCount = (int)($db_conn->query("SELECT COUNT(*) c FROM tp_courier_amount_requests WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title;?> : <?php echo $business_name;?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .car-filter { background:#fff; border:1px solid rgba(11,11,11,0.10); border-radius:10px; padding:14px 18px; margin-bottom:20px; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:8px 11px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:8px 11px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .car-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap; }
        .car-badge.pending { background:#fef3c7; color:#92400e; }
        .car-badge.approved { background:#d1fae5; color:#065f46; }
        .car-badge.rejected { background:#fee2e2; color:#991b1b; }
        .car-tabs { display:flex; gap:8px; margin-bottom:16px; }
        .car-tab { padding:7px 16px; border-radius:20px; font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid #e5e7eb; color:#4b5563; }
        .car-tab.active { background:#667eea; border-color:#667eea; color:#fff; }
        .car-delta-up { color:#991b1b; font-weight:700; }
        .car-delta-down { color:#065f46; font-weight:700; }
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
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>Courier Amount Requests<?php if ($pendingCount > 0): ?> <span class="car-badge pending" style="font-size:12px;"><?php echo $pendingCount; ?> pending</span><?php endif; ?></h1>
                                </div>
                            </div>
                        </div>

                        <div class="car-tabs">
                            <?php $tabQs = fn($st) => '?status=' . urlencode($st) . '&from_date=' . urlencode($filter_from) . '&to_date=' . urlencode($filter_to); ?>
                            <a class="car-tab <?php echo $filter_status===''?'active':''; ?>" href="<?php echo $tabQs(''); ?>">All</a>
                            <a class="car-tab <?php echo $filter_status==='pending'?'active':''; ?>" href="<?php echo $tabQs('pending'); ?>">Pending</a>
                            <a class="car-tab <?php echo $filter_status==='approved'?'active':''; ?>" href="<?php echo $tabQs('approved'); ?>">Approved</a>
                            <a class="car-tab <?php echo $filter_status==='rejected'?'active':''; ?>" href="<?php echo $tabQs('rejected'); ?>">Rejected</a>
                        </div>

                        <div class="car-filter">
                            <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                <div>
                                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From</label>
                                    <input type="date" name="from_date" value="<?php echo htmlspecialchars($filter_from); ?>" class="form-control form-control-sm">
                                </div>
                                <div>
                                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To</label>
                                    <input type="date" name="to_date" value="<?php echo htmlspecialchars($filter_to); ?>" class="form-control form-control-sm">
                                </div>
                                <div><button type="submit" class="btn btn-primary btn-sm">Apply</button></div>
                            </form>
                        </div>

                        <div class="card">
                            <div class="card-body" style="overflow-x:auto;">
                                <table class="mt">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>TP</th>
                                            <th>Type</th>
                                            <th>Boxes/Covers</th>
                                            <th>Calculated</th>
                                            <th>Approved</th>
                                            <th>Change</th>
                                            <th>Note</th>
                                            <th>Status</th>
                                            <th>Reviewed By</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($requests)): ?>
                                        <tr><td colspan="11" class="text-center text-muted" style="padding:24px;">No requests found for this filter.</td></tr>
                                    <?php else: foreach ($requests as $r):
                                        $delta = $r['status'] === 'approved' ? ((float)$r['approved_amount'] - (float)$r['calculated_amount']) : null;
                                    ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($r['created_at'])); ?><br><span class="text-muted" style="font-size:11px;"><?php echo date('h:i A', strtotime($r['created_at'])); ?></span></td>
                                            <td><?php echo htmlspecialchars($r['tp_name']); ?><br><span class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($r['tp_code']); ?></span></td>
                                            <td><?php echo htmlspecialchars(tpProductTypeLabel($r['product_type'])); ?></td>
                                            <td><?php echo (int)$r['total_boxes']; ?> box<?php echo $r['total_boxes']!=1?'es':''; ?><?php if ($r['total_covers'] > 0): ?>, <?php echo (int)$r['total_covers']; ?> cover<?php echo $r['total_covers']!=1?'s':''; ?><?php endif; ?></td>
                                            <td>&#8377;<?php echo number_format($r['calculated_amount'],2); ?></td>
                                            <td><?php echo $r['approved_amount'] !== null ? '&#8377;'.number_format($r['approved_amount'],2) : '<span class="text-muted">&mdash;</span>'; ?></td>
                                            <td>
                                                <?php if ($delta === null): ?>
                                                    <span class="text-muted">&mdash;</span>
                                                <?php elseif (abs($delta) < 0.005): ?>
                                                    <span class="text-muted">No change</span>
                                                <?php else: ?>
                                                    <span class="<?php echo $delta > 0 ? 'car-delta-up' : 'car-delta-down'; ?>"><?php echo $delta > 0 ? '+' : ''; ?>&#8377;<?php echo number_format($delta,2); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="max-width:200px;white-space:normal;"><?php echo $r['note'] ? htmlspecialchars($r['note']) : '<span class="text-muted">&mdash;</span>'; ?></td>
                                            <td><span class="car-badge <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                            <td style="font-size:11.5px;">
                                                <?php if ($r['reviewed_by_name']): ?>
                                                    <?php echo htmlspecialchars($r['reviewed_by_name']); ?><br>
                                                    <span class="text-muted"><?php echo date('d M Y h:i A', strtotime($r['reviewed_at'])); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">&mdash;</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($r['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="openCarReview(<?php echo $r['id']; ?>, <?php echo (float)$r['calculated_amount']; ?>)">Review</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="carReviewModal" style="display:none;position:fixed;inset:0;background:rgba(17,24,39,.55);z-index:1050;align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;border-radius:14px;padding:22px;max-width:400px;width:100%;">
            <div style="font-weight:700;font-size:15px;color:#1f2937;margin-bottom:10px;">Review Courier Amount Request</div>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Correct Amount (&#8377;)</label>
            <input type="number" id="carApprovedAmount" step="0.01" min="0" class="form-control" style="margin-bottom:12px;">
            <div id="carReviewStatus" style="font-size:12.5px;margin-bottom:8px;"></div>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('carReviewModal').style.display='none';">Cancel</button>
                <button type="button" class="btn btn-danger" style="flex:1;" onclick="submitCarReview('rejected')">Reject</button>
                <button type="button" class="btn btn-success" style="flex:1;" onclick="submitCarReview('approved')">Approve</button>
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
    <script>
    var carActiveId = null;
    function openCarReview(id, calculatedAmount) {
        carActiveId = id;
        document.getElementById('carApprovedAmount').value = calculatedAmount;
        document.getElementById('carReviewStatus').textContent = '';
        document.getElementById('carReviewModal').style.display = 'flex';
    }
    function submitCarReview(decision) {
        var amount = document.getElementById('carApprovedAmount').value;
        var statusEl = document.getElementById('carReviewStatus');
        if (decision === 'approved' && (!amount || parseFloat(amount) < 0)) {
            statusEl.textContent = 'Enter a valid amount.'; statusEl.style.color = '#991b1b'; return;
        }
        statusEl.textContent = 'Saving…'; statusEl.style.color = '#6b7280';
        fetch('courier-amount-request-review-ajax.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(carActiveId) + '&decision=' + encodeURIComponent(decision) + '&amount=' + encodeURIComponent(amount)
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) { window.location.reload(); }
                else { statusEl.textContent = data.message || 'Could not save.'; statusEl.style.color = '#991b1b'; }
            })
            .catch(function() { statusEl.textContent = 'Could not reach the server.'; statusEl.style.color = '#991b1b'; });
    }
    </script>
</body>
</html>
