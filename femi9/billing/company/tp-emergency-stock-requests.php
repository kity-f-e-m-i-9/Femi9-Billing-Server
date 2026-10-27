<?php
// Review queue for TP "emergency" stock requests — raised from the TP
// Order Assistant chat when a TP says a product is needed urgently beyond
// what their Channel Partner (CP) currently has. Approving/rejecting here
// does NOT create or touch any purchase order — it only records Company's
// decision; the TP sees the outcome on their own "My Emergency Requests"
// page and, if approved, places the actual order themselves afterwards.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner_edit');
require_once __DIR__ . '/../shared/TpEmergencyStockRequest.php';
include("config.php");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['approve', 'reject'], true)) {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
        header("Location: tp-emergency-stock-requests.php");
        exit;
    }
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $newStatus = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
    $reviewer = $_SESSION['LOGIN_USER'] ?? 'company';
    $ok = $requestId > 0 && reviewTpEmergencyStockRequest($db_conn, $requestId, $newStatus, $reviewer);
    $_SESSION[$ok ? 'sucMessage' : 'errorMessage'] = $ok
        ? "Request " . ($newStatus === 'approved' ? 'approved' : 'rejected') . "."
        : "Could not update that request — it may already be reviewed.";
    header("Location: tp-emergency-stock-requests.php");
    exit;
}

$statusFilter = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : 'pending';
$requests = getTpEmergencyStockRequests($db_conn, $statusFilter);
$pendingCount = count(getTpEmergencyStockRequests($db_conn, 'pending', 500));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Emergency Stock Requests : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root { --ata-tp-1: #667eea; --ata-tp-2: #764ba2; }
        .ata-page-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
        .ata-page-head h1 { font-size:20px; font-weight:600; color:#1f2937; margin:0; display:flex; align-items:center; }
        .ata-intro { color:#6b7280; font-size:13.5px; line-height:1.55; margin-bottom:16px; }

        .ata-nav-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
        .ata-nav-tab { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:9px; font-size:13.5px; font-weight:500; text-decoration:none; transition:filter .15s, background .15s; }
        .ata-nav-tab.active { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); color:#fff; box-shadow:0 2px 8px rgba(102,126,234,.3); }
        .ata-nav-tab:not(.active) { background:#f3f4f6; color:#4b5563; }
        .ata-nav-tab:not(.active):hover { background:#e5e7eb; color:#1f2937; }
        .ata-nav-tab .pill { background:rgba(255,255,255,.3); border-radius:10px; padding:1px 7px; font-size:11px; margin-left:2px; }
        .ata-nav-tab:not(.active) .pill { background:#fecaca; color:#991b1b; }

        .eq-card { background:#fff; border-radius:14px; box-shadow:0 1px 3px rgba(0,0,0,.06); overflow:hidden; }
        .eq-table { width:100%; border-collapse:separate; border-spacing:0; }
        .eq-table thead th { padding:12px 16px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#9ca3af; background:#fafbfc; border-bottom:1px solid #eef0f3; white-space:nowrap; }
        .eq-table tbody td { padding:14px 16px; vertical-align:middle; border-bottom:1px solid #f1f5f9; font-size:13.5px; color:#344054; }
        .eq-table tbody tr:last-child td { border-bottom:none; }
        .eq-table tbody tr:hover { background:#fafbff; }
        .badge-pending { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; }
        .badge-approved { background:#dcfce7; color:#166534; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; }
        .badge-rejected { background:#fee2e2; color:#991b1b; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; }
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
                    <div class="ata-page-head">
                        <h1><i class="material-icons-outlined" style="font-size:22px;vertical-align:middle;margin-right:8px;color:var(--ata-tp-1);">priority_high</i>TP Emergency Stock Requests</h1>
                    </div>
                    <p class="ata-intro">Raised by a TP's Order Assistant when they need more of a product than their Channel Partner currently has. Approving/rejecting here doesn't place any order — it just tells the TP whether they're cleared to proceed.</p>

                    <?php if (isset($_SESSION['errorMessage'])): $flashErr = htmlspecialchars($_SESSION['errorMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['errorMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"error",title:"Error",text:"<?= $flashErr ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['sucMessage'])): $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['sucMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"success",title:"Success",text:"<?= $flashMsg ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>

                    <div class="ata-nav-tabs">
                        <a href="?status=pending" class="ata-nav-tab<?=$statusFilter === 'pending' ? ' active' : '';?>">Pending<?php if ($pendingCount > 0): ?><span class="pill"><?=$pendingCount;?></span><?php endif; ?></a>
                        <a href="?status=approved" class="ata-nav-tab<?=$statusFilter === 'approved' ? ' active' : '';?>">Approved</a>
                        <a href="?status=rejected" class="ata-nav-tab<?=$statusFilter === 'rejected' ? ' active' : '';?>">Rejected</a>
                    </div>

                    <?php if (empty($requests)): ?>
                        <div class="alert alert-info">No <?=htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8');?> requests.</div>
                    <?php else: ?>
                    <div class="eq-card">
                        <table class="eq-table">
                            <thead>
                                <tr>
                                    <th>Territory Partner</th>
                                    <th>Channel Partner</th>
                                    <th>Product</th>
                                    <th style="text-align:right;">Requested</th>
                                    <th style="text-align:right;">CP Had</th>
                                    <th>TP's Message</th>
                                    <th>Status</th>
                                    <th>Raised</th>
                                    <?php if ($statusFilter === 'pending'): ?><th></th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td><?=htmlspecialchars($r['tp_name'], ENT_QUOTES, 'UTF-8');?> <span class="text-muted" style="font-size:11.5px;">(<?=htmlspecialchars($r['tp_code'], ENT_QUOTES, 'UTF-8');?>)</span></td>
                                    <td><?=htmlspecialchars($r['cp_name'], ENT_QUOTES, 'UTF-8');?></td>
                                    <td><?=htmlspecialchars($r['product_name'], ENT_QUOTES, 'UTF-8');?></td>
                                    <td style="text-align:right;font-weight:600;"><?=number_format($r['requested_qty']);?></td>
                                    <td style="text-align:right;color:#9ca3af;"><?=number_format($r['cp_available_qty']);?></td>
                                    <td style="max-width:220px;font-size:12.5px;color:#6b7280;"><?=htmlspecialchars($r['note'] ?? '', ENT_QUOTES, 'UTF-8');?></td>
                                    <td><span class="badge-<?=htmlspecialchars((string)($r['status']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?=htmlspecialchars((string)(ucfirst($r['status'])), ENT_QUOTES, 'ISO-8859-1', false);?></span></td>
                                    <td class="text-muted" style="font-size:12px;"><?=date('d M Y, h:i A', strtotime($r['created_at']));?></td>
                                    <?php if ($statusFilter === 'pending'): ?>
                                    <td>
                                        <div style="display:flex;gap:6px;">
                                            <form method="post" onsubmit="return confirm('Approve this request?');">
                                                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');?>">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="request_id" value="<?=(int)$r['id'];?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Approve</button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Reject this request?');">
                                                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');?>">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="request_id" value="<?=(int)$r['id'];?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

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
</body>
</html>
