<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once __DIR__ . '/../shared/TpShopInvoiceActionRequest.php';
error_reporting(0);

tpEnsureShopInvoiceActionRequestTable($db_conn);

// Only a BDM Company has separately marked eligible can see this page at
// all — same gate salesbdm/femi_menu.php uses to hide the sidebar link, but
// re-checked here since the sidebar hiding it is UX only.
if (!tpIsBdmEligibleForShopInvoiceRequests($db_conn, (int)$salesBdmID)) {
    header('Location: dashboard.php'); exit;
}

// Subtree-aware — sees requests for every TP under their own Zone AND
// every subordinate's Zone (their whole "Our Team"), not just their own
// personal district match. Otherwise a Chief BDM who's eligible but whose
// own Zone doesn't happen to cover a subordinate's TP's district would
// never see that subordinate's requests, even when the subordinate isn't
// themselves marked eligible.
$tpIds = getBdmSubtreeAssignedTpIds($db_conn, (int)$salesBdmID);
$hasTps = !empty($tpIds);
$tpIdList = $hasTps ? implode(',', array_map('intval', $tpIds)) : '0';

$filter_status = $_GET['status'] ?? 'pending';
if (!in_array($filter_status, ['pending', 'approved', 'rejected', ''], true)) { $filter_status = 'pending'; }
$filter_from = $_GET['from_date'] ?? date('Y-m-01');
$filter_to   = $_GET['to_date']   ?? date('Y-m-d');

$requests = [];
$pendingCount = 0;
if ($hasTps) {
    $pendingCount = (int)($db_conn->query("SELECT COUNT(*) c FROM tp_shop_invoice_action_requests WHERE territory_partner_id IN ($tpIdList) AND status = 'pending'")->fetch_assoc()['c'] ?? 0);

    $where = ["r.territory_partner_id IN ($tpIdList)", "DATE(r.created_at) BETWEEN ? AND ?"];
    $params = [$filter_from, $filter_to];
    $types = 'ss';
    if ($filter_status !== '') {
        $where[] = "r.status = ?";
        $params[] = $filter_status;
        $types .= 's';
    }
    $sql = "
        SELECT r.*, tp.name AS tp_name, tp.tp_id AS tp_code, ui.inv_number
        FROM tp_shop_invoice_action_requests r
        JOIN territory_partners tp ON tp.id = r.territory_partner_id
        LEFT JOIN user_invoice ui ON ui.inv_id = r.inv_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY r.created_at DESC
    ";
    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop Invoice Permission Requests : <?php echo $business_name; ?></title>
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
        .sir-filter { background:#fff; border:1px solid rgba(11,11,11,0.10); border-radius:10px; padding:14px 18px; margin-bottom:20px; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:8px 11px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:8px 11px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .sir-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap; }
        .sir-badge.pending { background:#fef3c7; color:#92400e; }
        .sir-badge.approved { background:#d1fae5; color:#065f46; }
        .sir-badge.rejected { background:#fee2e2; color:#991b1b; }
        .sir-badge.return { background:#dbeafe; color:#1e40af; }
        .sir-badge.remove { background:#fee2e2; color:#991b1b; }
        .sir-tabs { display:flex; gap:8px; margin-bottom:16px; }
        .sir-tab { padding:7px 16px; border-radius:20px; font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid #e5e7eb; color:#4b5563; }
        .sir-tab.active { background:#667eea; border-color:#667eea; color:#fff; }
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
                                <h1>Shop Invoice Permission Requests<?php if ($pendingCount > 0): ?> <span class="sir-badge pending" style="font-size:12px;"><?php echo $pendingCount; ?> pending</span><?php endif; ?></h1>
                            </div>
                        </div>
                    </div>

                    <div class="sir-tabs">
                        <?php $tabQs = fn($st) => '?status=' . urlencode($st) . '&from_date=' . urlencode($filter_from) . '&to_date=' . urlencode($filter_to); ?>
                        <a class="sir-tab <?php echo $filter_status==='pending'?'active':''; ?>" href="<?php echo $tabQs('pending'); ?>">Pending</a>
                        <a class="sir-tab <?php echo $filter_status==='approved'?'active':''; ?>" href="<?php echo $tabQs('approved'); ?>">Approved</a>
                        <a class="sir-tab <?php echo $filter_status==='rejected'?'active':''; ?>" href="<?php echo $tabQs('rejected'); ?>">Rejected</a>
                        <a class="sir-tab <?php echo $filter_status===''?'active':''; ?>" href="<?php echo $tabQs(''); ?>">All</a>
                    </div>

                    <div class="sir-filter">
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
                                        <th>Invoice</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Reviewed</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">No requests found for this filter.</td></tr>
                                <?php else: foreach ($requests as $r): ?>
                                    <tr id="sirRow<?php echo $r['id']; ?>">
                                        <td><?php echo date('d M Y', strtotime($r['created_at'])); ?><br><span class="text-muted" style="font-size:11px;"><?php echo date('h:i A', strtotime($r['created_at'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($r['tp_name']); ?><br><span class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($r['tp_code']); ?></span></td>
                                        <td><?php
                                            $isPendingInvNumber = ($r['inv_number'] === null || $r['inv_number'] === $r['inv_id']);
                                            echo $isPendingInvNumber
                                                ? '<span class="sir-badge pending">Pending</span>'
                                                : '<code style="font-size:12px;">' . htmlspecialchars($r['inv_number']) . '</code>';
                                        ?></td>
                                        <td><span class="sir-badge <?php echo $r['action_type']; ?>"><?php echo ucfirst($r['action_type']); ?></span></td>
                                        <td><span class="sir-badge <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
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
                                            <button type="button" class="btn btn-success btn-sm" onclick="sirReview(<?php echo $r['id']; ?>, 'approved')">Approve</button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="sirReview(<?php echo $r['id']; ?>, 'rejected')">Reject</button>
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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
function sirReview(id, decision) {
    if (!confirm((decision === 'approved' ? 'Approve' : 'Reject') + ' this request?')) return;
    fetch('shop-invoice-request-review-ajax.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id) + '&decision=' + encodeURIComponent(decision)
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) { window.location.reload(); }
            else { alert(data.message || 'Could not save.'); }
        })
        .catch(function () { alert('Could not reach the server.'); });
}
</script>
</body>
</html>
