<?php
// Status page for the "emergency" stock requests this TP's Order Assistant
// chat has raised (see shared/TpEmergencyStockRequest.php) — a TP never
// files these manually, they come from saying something like "emergency,
// I need 10" when their CP only has 5. This page is where they come back
// to check whether Company approved it yet; approval doesn't place an
// order by itself, the TP still has to submit the actual Purchase Order
// themselves once cleared.
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpEmergencyStockRequest.php';
error_reporting(0);

$tpId = (int) $Login_user_IDvl;
$requests = $tpId > 0 ? getTpEmergencyStockRequestsForTp($db_conn, $tpId) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Emergency Requests : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        :root { --ata-tp-1: #667eea; --ata-tp-2: #764ba2; }
        .ata-page-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
        .ata-page-head h1 { font-size:20px; font-weight:600; color:#1f2937; margin:0; display:flex; align-items:center; }
        .ata-intro { color:#6b7280; font-size:13.5px; line-height:1.55; margin-bottom:16px; }

        .eq-card { background:#fff; border-radius:14px; box-shadow:0 1px 3px rgba(0,0,0,.06); overflow:hidden; }
        .eq-table { width:100%; border-collapse:separate; border-spacing:0; }
        .eq-table thead th { padding:12px 16px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#9ca3af; background:#fafbfc; border-bottom:1px solid #eef0f3; white-space:nowrap; }
        .eq-table tbody td { padding:14px 16px; vertical-align:middle; border-bottom:1px solid #f1f5f9; font-size:13.5px; color:#344054; }
        .eq-table tbody tr:last-child td { border-bottom:none; }
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
                        <h1><i class="material-icons-outlined" style="font-size:22px;vertical-align:middle;margin-right:8px;color:var(--ata-tp-1);">priority_high</i>My Emergency Requests</h1>
                    </div>
                    <p class="ata-intro">Requests your Order Assistant sent to Company when you needed more of a product than your Channel Partner had. Once approved, place the actual order yourself as normal.</p>

                    <?php if (empty($requests)): ?>
                        <div class="alert alert-info">No emergency requests yet.</div>
                    <?php else: ?>
                    <div class="eq-card">
                        <table class="eq-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th style="text-align:right;">Requested</th>
                                    <th style="text-align:right;">CP Had</th>
                                    <th>Status</th>
                                    <th>Raised</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td><?=htmlspecialchars($r['product_name'], ENT_QUOTES, 'UTF-8');?></td>
                                    <td style="text-align:right;font-weight:600;"><?=number_format($r['requested_qty']);?></td>
                                    <td style="text-align:right;color:#9ca3af;"><?=number_format($r['cp_available_qty']);?></td>
                                    <td><span class="badge-<?=htmlspecialchars((string)($r['status']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?=htmlspecialchars((string)(ucfirst($r['status'])), ENT_QUOTES, 'ISO-8859-1', false);?></span></td>
                                    <td class="text-muted" style="font-size:12px;"><?=date('d M Y, h:i A', strtotime($r['created_at']));?></td>
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
