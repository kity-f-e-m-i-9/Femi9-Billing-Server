<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once __DIR__ . '/../shared/ShopLocationChangeRequest.php';
error_reporting(0);

ensureShopLocationChangeRequestsTable($db_conn);

// A shop's district (denormalized onto the request at file time) is matched
// against this BDM's own assigned districts — same district-name matching
// getBdmAssignedDistrictNames()/isLocationInBdmDistricts() use everywhere
// else, since there's no FK tying a DM's shop to a specific BDM.
$districtNames = getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID);
$hasDistricts = !empty($districtNames);

$filter_status = $_GET['status'] ?? 'pending';
if (!in_array($filter_status, ['pending', 'approved', 'rejected', ''], true)) { $filter_status = 'pending'; }

$requests = [];
$pendingCount = 0;
if ($hasDistricts) {
    $placeholders = implode(',', array_fill(0, count($districtNames), '?'));
    $types = str_repeat('s', count($districtNames));
    $normalized = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);

    $pendingStmt = $db_conn->prepare("SELECT COUNT(*) c FROM ms_shop_location_requests WHERE status='pending' AND LOWER(TRIM(district_name)) IN ($placeholders)");
    $pendingStmt->bind_param($types, ...$normalized);
    $pendingStmt->execute();
    $pendingCount = (int)($pendingStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $pendingStmt->close();

    $where = ["LOWER(TRIM(r.district_name)) IN ($placeholders)"];
    $params = $normalized;
    $bindTypes = $types;
    if ($filter_status !== '') {
        $where[] = "r.status = ?";
        $params[] = $filter_status;
        $bindTypes .= 's';
    }
    $sql = "
        SELECT r.*, s.name AS shop_name, s.address AS shop_address, s.district_name AS shop_district,
               s.location_recapture_count, ms.ms_name
        FROM ms_shop_location_requests r
        JOIN ms_shop s ON s.id = r.shop_id
        LEFT JOIN marketing_staff ms ON ms.id = r.ms_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY r.requested_at DESC
    ";
    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($bindTypes, ...$params);
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
    <title>Location Change Requests : <?php echo $business_name; ?></title>
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
        .lcr-tabs { display:flex; gap:8px; margin-bottom:16px; }
        .lcr-tab { padding:7px 16px; border-radius:20px; font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid #e5e7eb; color:#4b5563; }
        .lcr-tab.active { background:#667eea; border-color:#667eea; color:#fff; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:8px 11px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:8px 11px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .lcr-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap; }
        .lcr-badge.pending { background:#fef3c7; color:#92400e; }
        .lcr-badge.approved { background:#d1fae5; color:#065f46; }
        .lcr-badge.rejected { background:#fee2e2; color:#991b1b; }
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
                                <h1>Location Change Requests<?php if ($pendingCount > 0): ?> <span class="lcr-badge pending" style="font-size:12px;"><?php echo $pendingCount; ?> pending</span><?php endif; ?></h1>
                                <p class="text-muted" style="font-size:13px;">A DM's shop location can only be re-captured 2 times. Once that limit is hit, they can ask you here to unlock it for 2 more.</p>
                            </div>
                        </div>
                    </div>

                    <div class="lcr-tabs">
                        <?php $tabQs = fn($st) => '?status=' . urlencode($st); ?>
                        <a class="lcr-tab <?php echo $filter_status==='pending'?'active':''; ?>" href="<?php echo $tabQs('pending'); ?>">Pending</a>
                        <a class="lcr-tab <?php echo $filter_status==='approved'?'active':''; ?>" href="<?php echo $tabQs('approved'); ?>">Approved</a>
                        <a class="lcr-tab <?php echo $filter_status==='rejected'?'active':''; ?>" href="<?php echo $tabQs('rejected'); ?>">Rejected</a>
                        <a class="lcr-tab <?php echo $filter_status===''?'active':''; ?>" href="<?php echo $tabQs(''); ?>">All</a>
                    </div>

                    <div class="card">
                        <div class="card-body" style="overflow-x:auto;">
                            <table class="mt">
                                <thead>
                                    <tr>
                                        <th>Requested</th>
                                        <th>Shop</th>
                                        <th>DM</th>
                                        <th>Recaptures Used</th>
                                        <th>Status</th>
                                        <th>Reviewed</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!$hasDistricts): ?>
                                    <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">You have no assigned districts.</td></tr>
                                <?php elseif (empty($requests)): ?>
                                    <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">No requests found for this filter.</td></tr>
                                <?php else: foreach ($requests as $r): ?>
                                    <tr id="lcrRow<?php echo $r['id']; ?>">
                                        <td><?php echo date('d M Y', strtotime($r['requested_at'])); ?><br><span class="text-muted" style="font-size:11px;"><?php echo date('h:i A', strtotime($r['requested_at'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($r['shop_name']); ?><br><span class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($r['shop_address']); ?></span></td>
                                        <td><?php echo htmlspecialchars($r['ms_name'] ?? '—'); ?></td>
                                        <td><?php echo (int)$r['location_recapture_count']; ?> / 2</td>
                                        <td><span class="lcr-badge <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                        <td style="font-size:11.5px;">
                                            <?php if ($r['responded_at']): ?>
                                                <?php echo htmlspecialchars($r['responded_by_name'] ?? ''); ?><br>
                                                <span class="text-muted"><?php echo date('d M Y h:i A', strtotime($r['responded_at'])); ?></span>
                                                <?php if ($r['accept_reason']): ?><br><span class="text-muted"><?php echo htmlspecialchars($r['accept_reason']); ?></span><?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($r['status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="openLcrReview(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars($r['shop_name'], ENT_QUOTES); ?>')">Review</button>
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

<div id="lcrReviewModal" style="display:none;position:fixed;inset:0;background:rgba(17,24,39,.55);z-index:1050;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:14px;padding:22px;max-width:420px;width:100%;">
        <div style="font-weight:700;font-size:15px;color:#1f2937;margin-bottom:4px;">Review Location Change Request</div>
        <div id="lcrShopName" style="font-size:13px;color:#4b5563;margin-bottom:12px;"></div>
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Recaptures to grant (if approving)</label>
        <select id="lcrRecaptures" class="form-control" style="margin-bottom:12px;">
            <option value="2" selected>2 (full reset)</option>
            <option value="1">1 (one more attempt only)</option>
        </select>
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Reason (required to approve)</label>
        <textarea id="lcrReason" class="form-control" rows="3" style="margin-bottom:12px;" placeholder="Why are you approving/rejecting this request?"></textarea>
        <div id="lcrReviewStatus" style="font-size:12.5px;margin-bottom:8px;"></div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lcrReviewModal').style.display='none';">Cancel</button>
            <button type="button" class="btn btn-danger" style="flex:1;" onclick="submitLcrReview('rejected')">Reject</button>
            <button type="button" class="btn btn-success" style="flex:1;" onclick="submitLcrReview('approved')">Approve</button>
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
var lcrActiveId = null;
function openLcrReview(id, shopName) {
    lcrActiveId = id;
    document.getElementById('lcrShopName').textContent = shopName;
    document.getElementById('lcrReason').value = '';
    document.getElementById('lcrRecaptures').value = '2';
    document.getElementById('lcrReviewStatus').textContent = '';
    document.getElementById('lcrReviewModal').style.display = 'flex';
}
function submitLcrReview(decision) {
    var reason = document.getElementById('lcrReason').value.trim();
    var statusEl = document.getElementById('lcrReviewStatus');
    if (decision === 'approved' && !reason) {
        statusEl.textContent = 'Please enter a reason for accepting this request.'; statusEl.style.color = '#991b1b'; return;
    }
    var recaptures = document.getElementById('lcrRecaptures').value;
    statusEl.textContent = 'Saving…'; statusEl.style.color = '#6b7280';
    fetch('location-change-request-action.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(lcrActiveId) + '&decision=' + encodeURIComponent(decision) + '&reason=' + encodeURIComponent(reason) + '&recaptures=' + encodeURIComponent(recaptures)
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
