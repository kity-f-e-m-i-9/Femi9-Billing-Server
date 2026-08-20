<?php
include("checksession.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'super_stockiest') {
    header("Location: dashboard.php?error=unauthorized"); exit;
}

$ss_temp_id    = $Login_user_IDvl;
$ss_account_id = (int)($result_LoGuserDtails['id'] ?? 0);

$stmt = $db_conn->prepare(
    "SELECT s.id, s.territory_partner_id, s.po_id, s.file_path, s.detected_amount, s.reference_number,
            s.ocr_raw_text, s.rejection_reason, s.created_at, tp.name AS tp_name, tp.mobile AS tp_mobile,
            po.product_type
     FROM tp_purchase_order_screenshots s
     JOIN tp_purchase_orders po ON po.id = s.po_id
     JOIN territory_partners tp ON tp.id = s.territory_partner_id
     WHERE s.status = 'pending_review' AND tp.onboard_ss_id = ? AND po.approver_type = 'ss' AND po.approver_ss_id = ?
     ORDER BY s.created_at ASC"
);
$stmt->bind_param('si', $ss_temp_id, $ss_account_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PO Screenshot Review : <?php echo $business_name;?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
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
                                    <h1>PO Screenshot Review</h1>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($_SESSION['successMessage'])): ?>
                        <div class="alert alert-success"><?=htmlspecialchars($_SESSION['successMessage']); unset($_SESSION['successMessage']);?></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['errorMessage'])): ?>
                        <div class="alert alert-danger"><?=htmlspecialchars($_SESSION['errorMessage']); unset($_SESSION['errorMessage']);?></div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <?php if (empty($rows)): ?>
                                        <div class="text-center text-muted py-4">No payment screenshots are awaiting review.</div>
                                        <?php else: foreach ($rows as $r): ?>
                                        <div class="border rounded p-3 mb-3">
                                            <div class="row">
                                                <div class="col-md-3 text-center">
                                                    <?php if (preg_match('/\.hei[cf]$/i', $r['file_path'])): ?>
                                                    <div class="border rounded p-3 text-center">
                                                        <div>📄 HEIC image</div>
                                                        <small class="text-muted d-block mb-2">Not previewable in-browser — download to view.</small>
                                                        <a href="../territory-partner/po_screenshots/<?=urlencode($r['file_path'])?>" target="_blank" class="btn btn-sm btn-outline-secondary">Download</a>
                                                    </div>
                                                    <?php else: ?>
                                                    <a href="../territory-partner/po_screenshots/<?=urlencode($r['file_path'])?>" target="_blank">
                                                        <img src="../territory-partner/po_screenshots/<?=htmlspecialchars($r['file_path'])?>" style="max-width:100%;max-height:220px;border:1px solid #ddd;">
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-9">
                                                    <div><b>TP:</b> <?=htmlspecialchars($r['tp_name'] ?? ('#'.$r['territory_partner_id']))?> (<?=htmlspecialchars($r['tp_mobile'] ?? '')?>)
                                                        <?php if ($r['product_type'] !== null): $_poType = tpResolveProductType($r['product_type']); [$_tBg, $_tFg] = tpProductTypeBadgeColors($_poType); ?>
                                                        <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:9px;background:<?=$_tBg?>;color:<?=$_tFg?>;"><?=htmlspecialchars(tpProductTypeLabel($_poType))?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div><b>Uploaded:</b> <?=htmlspecialchars(date("d-m-Y g:i A", strtotime($r['created_at'])))?></div>
                                                    <div><b>Detected amount:</b> <?=$r['detected_amount'] !== null ? '₹'.number_format((float)$r['detected_amount'], 2) : '<span class="text-muted">not detected</span>'?></div>
                                                    <div><b>Detected reference number:</b> <?=$r['reference_number'] !== null && $r['reference_number'] !== '' ? htmlspecialchars($r['reference_number']) : '<span class="text-muted">not detected</span>'?></div>
                                                    <div><b>Why it needs review:</b> <?=htmlspecialchars($r['rejection_reason'] ?? '')?></div>
                                                    <details class="mt-2">
                                                        <summary style="cursor:pointer;">OCR text</summary>
                                                        <pre style="white-space:pre-wrap;font-size:12px;background:#f8f9fa;padding:8px;border-radius:4px;"><?=htmlspecialchars($r['ocr_raw_text'] ?? '')?></pre>
                                                    </details>

                                                    <form method="post" action="tp-po-screenshot-review-action.php" class="mt-3">
                                                        <input type="hidden" name="screenshot_id" value="<?=(int)$r['id']?>">
                                                        <div class="row g-2 align-items-end">
                                                            <div class="col-auto">
                                                                <label class="form-label">Confirmed amount</label>
                                                                <input type="number" step="0.01" min="0" name="confirmed_amount" class="form-control" value="<?=$r['detected_amount'] !== null ? htmlspecialchars($r['detected_amount']) : ''?>" required>
                                                            </div>
                                                            <div class="col-auto">
                                                                <label class="form-label">Confirmed reference number</label>
                                                                <input type="text" name="confirmed_reference" class="form-control" value="<?=htmlspecialchars($r['reference_number'] ?? '')?>" required>
                                                            </div>
                                                            <div class="col-auto">
                                                                <button type="submit" name="action" value="approve" class="btn btn-success" onclick="return confirm('Approve this screenshot?');">Approve</button>
                                                            </div>
                                                            <div class="col-auto">
                                                                <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Reject this screenshot?');">Reject</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; endif; ?>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
