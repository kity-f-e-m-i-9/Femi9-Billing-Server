<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("include/GodownAccess.php");
require_once __DIR__ . '/../shared/TpProductType.php';
include("config.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

date_default_timezone_set("Asia/Kolkata");
$today = date("Y-m-d");

// Arriving from tp-today-orders.php's "View This TP's Submissions" button
// with a specific tp_id — show every one of that TP's submissions (not just
// pending) so staff can find whichever one actually covers the order in
// question, since there's no hard link between an order and a submission.
$filterTpId = (int)($_GET['tp_id'] ?? 0);

// Only true when the reviewer arrived from tp-today-orders.php specifically
// (that link appends &from=po) — used later to send them straight back
// there after converting a submission, instead of the normal advance
// payments list. A direct visit to this page (sidebar/menu, even with a
// tp_id manually filtered in) never sets this, so that flow is untouched.
$fromPo = ($_GET['from'] ?? '') === 'po';

$filterSubmitted = isset($_GET['from_date']) || isset($_GET['to_date']) || isset($_GET['status_filter']);

if ($filterSubmitted) {
    $from_date = $_GET['from_date'] ?? $today;
    $to_date   = $_GET['to_date']   ?? $today;
    if (strtotime($from_date) > strtotime($to_date)) { [$from_date, $to_date] = [$to_date, $from_date]; }

    $statusFilter = $_GET['status_filter'] ?? 'all';
    $allowedStatusFilters = ['all', 'pending_review', 'accepted', 'rejected'];
    if (!in_array($statusFilter, $allowedStatusFilters, true)) $statusFilter = 'all';
} else {
    $from_date = '';
    $to_date = '';
    $statusFilter = $filterTpId > 0 ? 'all' : 'pending_review';
}

// Drafts (status='draft') are still-in-progress TP uploads not yet
// submitted for review — never shown here, same as a PO's po_id IS NULL
// screenshots never appearing on tp-today-orders.php. SS-routed submissions
// belong exclusively to that SS's own queue
// (super-stockist/manage-tp-advance-submissions.php).
$whereSql = "WHERE sub.status != 'draft' AND sub.approver_type = 'company'";
$bindTypes = '';
$bindValues = [];
if ($filterSubmitted) {
    $whereSql .= ' AND DATE(sub.created_at) BETWEEN ? AND ?';
    $bindTypes .= 'ss';
    $bindValues[] = $from_date;
    $bindValues[] = $to_date;
}
if ($statusFilter !== 'all') {
    $whereSql .= ' AND sub.status = ?';
    $bindTypes .= 's';
    $bindValues[] = $statusFilter;
}
if ($filterTpId > 0) {
    $whereSql .= ' AND sub.territory_partner_id = ?';
    $bindTypes .= 'i';
    $bindValues[] = $filterTpId;
}

$stmt = $db_conn->prepare(
    "SELECT sub.id, sub.territory_partner_id, sub.amount, sub.payment_date, sub.payment_mode, sub.reference_number, sub.note,
            sub.status, sub.rejection_reason, sub.reviewed_by, sub.reviewed_at, sub.advance_payment_id, sub.created_at,
            sub.product_type,
            tp.name AS tp_name, tp.mobile AS tp_mobile, tp.tp_id AS tp_code
     FROM tp_advance_payment_submissions sub
     LEFT JOIN territory_partners tp ON tp.id = sub.territory_partner_id
     $whereSql
     ORDER BY (sub.status = 'pending_review') DESC, sub.created_at DESC"
);
if ($bindTypes !== '') {
    $stmt->bind_param($bindTypes, ...$bindValues);
}
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Attach each submission's screenshots.
$submissionIds = array_column($submissions, 'id');
$screenshotsBySubmission = [];
if (!empty($submissionIds)) {
    $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
    $types = str_repeat('i', count($submissionIds));
    $scrStmt = $db_conn->prepare(
        "SELECT id, submission_id, file_path, detected_amount, reference_number, status, rejection_reason
         FROM tp_advance_payment_screenshots WHERE submission_id IN ($placeholders) ORDER BY id ASC"
    );
    $scrStmt->bind_param($types, ...$submissionIds);
    $scrStmt->execute();
    $scrRes = $scrStmt->get_result();
    while ($s = $scrRes->fetch_assoc()) {
        $screenshotsBySubmission[$s['submission_id']][] = $s;
    }
    $scrStmt->close();
}
foreach ($submissions as &$sub) {
    $sub['screenshots'] = $screenshotsBySubmission[$sub['id']] ?? [];
}
unset($sub);

$totalCount    = count($submissions);
$pendingCount  = count(array_filter($submissions, fn($r) => $r['status'] === 'pending_review'));
$acceptedCount = count(array_filter($submissions, fn($r) => $r['status'] === 'accepted'));
$totalAmount   = array_sum(array_column($submissions, 'amount'));

$companyProfiles = $db_conn->query(
    "SELECT id, gname FROM company_godown WHERE gname LIKE '%Femi%' AND " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Advance Payment Submissions : <?php echo $business_name;?></title>
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
        body { font-family: 'Poppins', sans-serif; }

        .po-stat-card {
            background: #fff; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06); border-left: 4px solid;
            display: flex; align-items: center; justify-content: space-between;
        }
        .po-stat-card h3 { font-size: 22px; font-weight: 700; margin: 0; color: #1f2937; }
        .po-stat-card p { margin: 2px 0 0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; }
        .po-stat-card .material-icons-outlined { font-size: 32px; opacity: .15; }
        .po-stat-card.purple { border-color: #667eea; } .po-stat-card.purple .material-icons-outlined { color: #667eea; }
        .po-stat-card.orange { border-color: #f59e0b; } .po-stat-card.orange .material-icons-outlined { color: #f59e0b; }
        .po-stat-card.amber  { border-color: #f59e0b; } .po-stat-card.amber  .material-icons-outlined { color: #f59e0b; }
        .po-stat-card.green  { border-color: #10b981; } .po-stat-card.green  .material-icons-outlined { color: #10b981; }

        .po-filter-card { background: #fff; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .po-filter-card .form-label { font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 4px; }

        .po-table-card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .po-table { width: 100%; margin: 0; }
        .po-table thead th {
            background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .4px; padding: 12px 16px;
            border-bottom: 2px solid #e5e7eb; white-space: nowrap;
        }
        .po-table tbody td { padding: 13px 16px; vertical-align: middle; font-size: 13.5px; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
        .po-table tbody tr:last-child td { border-bottom: none; }
        .po-table tbody tr:hover { background: #f8fafc; }

        .badge-waiting   { background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-completed { background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }

        .proof-view-trigger { border: none; cursor: pointer; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }

        .proof-summary { background: #f8fafc; border-radius: 10px; padding: 14px 16px; margin-bottom: 16px; font-size: 13.5px; }
        .proof-info-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 10px 24px; }
        .proof-info-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; margin-bottom: 2px; }
        .proof-info-value { font-size: 14px; font-weight: 600; color: #1f2937; }
        .proof-reason { grid-column: 1 / -1; font-size: 12.5px; color: #92400e; background: #fffbeb; border-radius: 8px; padding: 8px 12px; margin-top: 4px; }

        .proof-shot-card {
            border: 1px solid #f1f5f9; border-radius: 12px; padding: 14px 16px; margin-bottom: 12px;
            display: flex; gap: 16px; align-items: flex-start;
        }
        .proof-shot-card:last-child { margin-bottom: 0; }
        .proof-shot-media { flex: 0 0 110px; }
        .proof-shot-media img { width: 100%; border-radius: 8px; border: 1px solid #e5e7eb; }
        .proof-shot-main { flex: 1; min-width: 0; font-size: 13px; }
        .proof-badge { padding: 3px 10px; border-radius: 20px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
        .proof-badge.is-accepted { background: #d1fae5; color: #065f46; }
        .proof-badge.is-rejected { background: #fee2e2; color: #991b1b; }
        .proof-badge.is-pending  { background: #fef3c7; color: #92400e; }

        .proof-action-panel { margin-top: 16px; padding-top: 16px; border-top: 1px dashed #e5e7eb; }
        .proof-field-row { display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap; }
        .proof-field { display: flex; flex-direction: column; gap: 4px; }
        .proof-field label { font-size: 11px; font-weight: 600; color: #6b7280; margin: 0; }
        .proof-field .form-control, .proof-field .form-select { font-size: 13px; }
        .proof-confirmed-badge {
            display: inline-flex; align-items: center; gap: 6px; background: #d1fae5; color: #065f46;
            padding: 6px 14px; border-radius: 20px; font-size: 12.5px; font-weight: 600;
        }
        .proof-empty { text-align: center; padding: 48px 20px; color: #9ca3af; }
        .proof-empty .material-icons-outlined { font-size: 40px; opacity: .4; display: block; margin-bottom: 8px; }
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
                                    <h1>TP Advance Payment Submissions</h1>
                                </div>
                            </div>
                        </div>
                        <br/>

                        <div id="tasAlert"></div>

                        <!-- Stats -->
                        <div class="row">
                            <div class="col-lg-3 col-sm-6">
                                <div class="po-stat-card purple">
                                    <div><h3><?=$totalCount?></h3><p>Total Submissions</p></div>
                                    <i class="material-icons-outlined">receipt_long</i>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="po-stat-card orange">
                                    <div><h3 style="font-size:18px;">₹<?=number_format($totalAmount, 2)?></h3><p>Total Amount</p></div>
                                    <i class="material-icons-outlined">currency_rupee</i>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="po-stat-card amber">
                                    <div><h3><?=$pendingCount?></h3><p>Pending Review</p></div>
                                    <i class="material-icons-outlined">hourglass_top</i>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="po-stat-card green">
                                    <div><h3><?=$acceptedCount?></h3><p>Approved</p></div>
                                    <i class="material-icons-outlined">check_circle</i>
                                </div>
                            </div>
                        </div>

                        <?php if ($filterTpId > 0): ?>
                        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                            <span style="font-size:13.5px;color:#1e3a8a;">
                                <i class="material-icons-outlined" style="font-size:16px;vertical-align:middle;">filter_alt</i>
                                Showing submissions for one Territory Partner only.
                            </span>
                            <a href="manage-tp-advance-submissions.php" style="font-size:12.5px;font-weight:600;color:#1e40af;">Clear filter — show all TPs</a>
                        </div>
                        <?php endif; ?>

                        <!-- Filters -->
                        <div class="po-filter-card">
                            <form method="get" class="row g-2 align-items-end">
                                <?php if ($filterTpId > 0): ?><input type="hidden" name="tp_id" value="<?=(int)$filterTpId?>"><?php endif; ?>
                                <?php if ($fromPo): ?><input type="hidden" name="from" value="po"><?php endif; ?>
                                <div class="col-md-3">
                                    <label class="form-label">From Date</label>
                                    <input type="date" name="from_date" value="<?=htmlspecialchars($from_date)?>" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">To Date</label>
                                    <input type="date" name="to_date" value="<?=htmlspecialchars($to_date)?>" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status_filter" class="form-control">
                                        <option value="all" <?=$statusFilter === 'all' ? 'selected' : ''?>>All</option>
                                        <option value="pending_review" <?=$statusFilter === 'pending_review' ? 'selected' : ''?>>Pending Review</option>
                                        <option value="accepted" <?=$statusFilter === 'accepted' ? 'selected' : ''?>>Approved</option>
                                        <option value="rejected" <?=$statusFilter === 'rejected' ? 'selected' : ''?>>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary"><i class="material-icons" style="vertical-align:middle;font-size:17px;">filter_list</i> Filter</button>
                                    <a href="manage-tp-advance-submissions.php" class="btn btn-outline-secondary"><i class="material-icons" style="vertical-align:middle;font-size:17px;">refresh</i> Reset</a>
                                </div>
                            </form>
                        </div>

                        <!-- Table -->
                        <div class="po-table-card">
                            <div style="overflow-x:auto;">
                                <table class="po-table">
                                    <thead>
                                        <tr>
                                            <th>Submitted</th>
                                            <th>Territory Partner</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Mode</th>
                                            <th>Reference</th>
                                            <th>Screenshots</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($submissions)): ?>
                                        <tr><td colspan="9">
                                            <div class="proof-empty">
                                                <i class="material-icons-outlined">inbox</i>
                                                No submissions in this view.
                                            </div>
                                        </td></tr>
                                        <?php else: foreach ($submissions as $sub):
                                            $submission_json = htmlspecialchars(json_encode($sub, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                        ?>
                                        <tr>
                                            <td><?=htmlspecialchars(date("d-m-Y g:i A", strtotime($sub['created_at'])))?></td>
                                            <td><?=htmlspecialchars($sub['tp_name'] ?? ('#'.$sub['territory_partner_id']))?><div class="text-muted" style="font-size:11.5px;"><?=htmlspecialchars($sub['tp_code'] ?? '')?></div></td>
                                            <td>
                                                <?php $_subType = tpResolveProductType($sub['product_type'] ?? null); [$_subBg, $_subFg] = tpProductTypeBadgeColors($_subType); ?>
                                                <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:9px;background:<?=$_subBg?>;color:<?=$_subFg?>;"><?=htmlspecialchars(tpProductTypeLabel($_subType))?></span>
                                            </td>
                                            <td><strong>₹<?=number_format((float)$sub['amount'], 2)?></strong></td>
                                            <td><?=htmlspecialchars($sub['payment_mode'])?></td>
                                            <td><?=htmlspecialchars($sub['reference_number'])?></td>
                                            <td><?=count($sub['screenshots'])?></td>
                                            <td>
                                                <?php if ($sub['status'] === 'accepted'): ?>
                                                <span class="badge-completed">Approved</span>
                                                <?php if ($sub['advance_payment_id']): ?><div class="text-muted" style="font-size:11px;margin-top:3px;">Advance #<?=(int)$sub['advance_payment_id']?></div><?php endif; ?>
                                                <?php elseif ($sub['status'] === 'rejected'): ?>
                                                <span class="badge-cancelled">Rejected</span>
                                                <?php else: ?>
                                                <span class="badge-waiting">Pending Review</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="proof-view-trigger"
                                                        style="<?= $sub['status'] === 'pending_review' ? 'background:#fef3c7;color:#92400e;' : ($sub['status'] === 'rejected' ? 'background:#fee2e2;color:#991b1b;' : 'background:#d1fae5;color:#065f46;') ?>"
                                                        data-partner="<?=htmlspecialchars($sub['tp_name'] ?? '', ENT_QUOTES)?>"
                                                        data-submission="<?=$submission_json?>">
                                                    View
                                                </button>
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

    <!-- Payment Proof Modal -->
    <div class="modal fade" id="proofViewModal" tabindex="-1" aria-labelledby="proofViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;">
                <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                    <h6 class="modal-title mb-0" id="proofViewModalLabel" style="font-weight:700;color:#1f2937;">
                        <i class="material-icons-outlined" style="font-size:19px;vertical-align:middle;margin-right:6px;color:#667eea;">receipt_long</i>
                        Payment Proof
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="proofViewModalBody">
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
    <script>
    var CSRF_TOKEN = <?=json_encode($_SESSION['csrf_token'])?>;
    var COMPANY_PROFILES = <?=json_encode($companyProfiles, JSON_UNESCAPED_UNICODE)?>;
    var fromPo = <?=json_encode($fromPo)?>;

    var currentProofButton = null;
    var currentSubmission = null;

    function tasShowAlert(message, type) {
        var el = document.getElementById('tasAlert');
        el.innerHTML = '<div class="alert alert-' + type + '">' + message + '</div>';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function renderScreenshotCard(s) {
        var badgeClass = s.status === 'accepted' ? 'is-accepted' : (s.status === 'rejected' ? 'is-rejected' : 'is-pending');
        var badgeText = s.status === 'accepted' ? 'Verified' : (s.status === 'rejected' ? 'Rejected' : 'Pending');

        var isHeic = /\.hei[cf]$/i.test(s.file_path);
        var fileUrl = '../territory-partner/advance_payment_screenshots/' + encodeURIComponent(s.file_path);
        var mediaHtml = isHeic
            ? '<div class="proof-shot-media d-flex align-items-center justify-content-center" style="height:90px;background:#f3f4f6;border-radius:8px;">' +
              '<a href="' + fileUrl + '" target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size:10px;">HEIC</a></div>'
            : '<div class="proof-shot-media"><a href="' + fileUrl + '" target="_blank"><img src="' + fileUrl + '" alt="Payment screenshot"></a></div>';

        var detail = s.detected_amount !== null
            ? 'Detected: ₹' + parseFloat(s.detected_amount).toFixed(2) + (s.reference_number ? ', Ref: ' + $('<div>').text(s.reference_number).html() : '')
            : (s.rejection_reason ? $('<div>').text(s.rejection_reason).html() : 'Not detected');

        return '<div class="proof-shot-card">' + mediaHtml +
            '<div class="proof-shot-main"><span class="proof-badge ' + badgeClass + '">' + badgeText + '</span>' +
            '<div class="mt-1">' + detail + '</div></div></div>';
    }

    function renderProofModal() {
        var sub = currentSubmission;
        var statusLabel = sub.status === 'accepted' ? 'Approved' : (sub.status === 'rejected' ? 'Rejected' : 'Pending Review');
        var statusColor = sub.status === 'accepted' ? '#065f46' : (sub.status === 'rejected' ? '#991b1b' : '#92400e');
        var statusBg = sub.status === 'accepted' ? '#d1fae5' : (sub.status === 'rejected' ? '#fee2e2' : '#fef3c7');

        var isDiaper = sub.product_type === 'diaper';
        var typeLabel = isDiaper ? 'Lumi Diaper' : 'Napkin';
        var typeBg = isDiaper ? '#ede9fe' : '#dcfce7';
        var typeFg = isDiaper ? '#6d28d9' : '#15803d';

        var summary = '<div class="proof-summary">' +
            '<div class="proof-info-grid">' +
            '<div><div class="proof-info-label">Type</div><span style="display:inline-block;font-size:11px;font-weight:700;padding:2px 9px;border-radius:9px;background:' + typeBg + ';color:' + typeFg + ';">' + typeLabel + '</span></div>' +
            '<div><div class="proof-info-label">Amount</div><span class="proof-info-value">₹' + parseFloat(sub.amount).toFixed(2) + '</span></div>' +
            '<div><div class="proof-info-label">Reference</div><span class="proof-info-value">' + $('<div>').text(sub.reference_number).html() + '</span></div>' +
            '<div><div class="proof-info-label">Payment Mode</div><span class="proof-info-value">' + $('<div>').text(sub.payment_mode).html() + '</span></div>' +
            '<div><div class="proof-info-label">Payment Date</div><span class="proof-info-value">' + sub.payment_date + '</span></div>' +
            (sub.note ? '<div style="grid-column:1/-1;"><div class="proof-info-label">Note</div><span class="proof-info-value">' + $('<div>').text(sub.note).html() + '</span></div>' : '') +
            '<div style="grid-column:1/-1;"><span style="background:' + statusBg + ';color:' + statusColor + ';padding:3px 10px;border-radius:20px;font-size:10.5px;font-weight:700;text-transform:uppercase;">' + statusLabel + '</span></div>' +
            (sub.rejection_reason ? '<div class="proof-reason">' + $('<div>').text(sub.rejection_reason).html() + '</div>' : '') +
            '</div></div>';

        var shotsHtml = sub.screenshots.length
            ? sub.screenshots.map(renderScreenshotCard).join('')
            : '<div class="proof-empty">No screenshots.</div>';

        var actionsHtml = '';
        if (sub.status === 'pending_review') {
            var firstAccepted = sub.screenshots.find(function (s) { return s.status === 'accepted'; });
            var defaultAmount = firstAccepted ? firstAccepted.detected_amount : sub.amount;
            var defaultRef = firstAccepted ? firstAccepted.reference_number : sub.reference_number;
            actionsHtml = '<div class="proof-action-panel" data-submission-id="' + sub.id + '">' +
                '<div class="proof-field-row">' +
                    '<div class="proof-field"><label>Confirmed Amount</label>' +
                    '<input type="number" step="0.01" min="0" class="form-control proof-amount-input" style="width:120px;" value="' + defaultAmount + '"></div>' +
                    '<div class="proof-field"><label>Confirmed Reference</label>' +
                    '<input type="text" class="form-control proof-ref-input" style="width:180px;" value="' + $('<div>').text(defaultRef).html() + '"></div>' +
                    '<button type="button" class="btn btn-success proof-approve-btn">Approve</button>' +
                    '<button type="button" class="btn btn-outline-danger proof-reject-btn">Reject</button>' +
                '</div>' +
            '</div>';
        } else if (sub.status === 'accepted') {
            if (sub.advance_payment_id) {
                actionsHtml = '<div class="proof-action-panel">' +
                    '<span class="proof-confirmed-badge"><i class="material-icons-outlined" style="font-size:16px;">check_circle</i>Added as Advance Payment #' + sub.advance_payment_id + '</span>' +
                    '</div>';
            } else {
                var companyOptions = '<option value="">Select company profile…</option>';
                COMPANY_PROFILES.forEach(function (cp) {
                    companyOptions += '<option value="' + cp.id + '">' + $('<div>').text(cp.gname).html() + '</option>';
                });
                var todayStr = new Date().toISOString().slice(0, 10);
                actionsHtml = '<div class="proof-action-panel" data-submission-id="' + sub.id + '">' +
                    '<button type="button" class="btn btn-outline-primary add-advance-toggle-btn">' +
                    '<i class="material-icons-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;">add_card</i>Add to TP Payment Entry</button> ' +
                    '<button type="button" class="btn btn-outline-danger proof-cancel-btn">Cancel Submission</button>' +
                    '<div class="advance-form proof-field-row mt-3" style="display:none;">' +
                        '<div class="proof-field"><label>Company Profile</label>' +
                        '<select class="form-select advance-company-select" style="width:180px;">' + companyOptions + '</select></div>' +
                        '<div class="proof-field"><label>Amount</label>' +
                        '<input type="number" step="0.01" min="0" class="form-control advance-amount-input" style="width:110px;" value="' + sub.amount + '"></div>' +
                        '<div class="proof-field"><label>Reference</label>' +
                        '<input type="text" class="form-control advance-ref-input" style="width:170px;" value="' + $('<div>').text(sub.reference_number).html() + '"></div>' +
                        '<div class="proof-field"><label>Payment Mode</label>' +
                        '<select class="form-select advance-mode-select" style="width:120px;">' +
                            ['UPI', 'NEFT', 'RTGS', 'IMPS', 'Bank Transfer', 'Cash', 'Cheque', 'Demand Draft', 'Other'].map(function (m) {
                                return '<option value="' + m + '"' + (m === sub.payment_mode ? ' selected' : '') + '>' + m + '</option>';
                            }).join('') +
                        '</select></div>' +
                        '<div class="proof-field"><label>Date</label>' +
                        '<input type="date" class="form-control advance-date-input" style="width:140px;" max="' + todayStr + '" value="' + sub.payment_date + '"></div>' +
                        '<button type="button" class="btn btn-primary advance-save-btn">Save</button>' +
                    '</div>' +
                '</div>';
            }
        }

        $('#proofViewModalBody').html(summary + shotsHtml + actionsHtml);
    }

    function updateProofButton() {
        if (!currentProofButton) return;
        var sub = currentSubmission;
        currentProofButton.attr('data-submission', JSON.stringify(sub));
        if (sub.status === 'pending_review') {
            currentProofButton.css({ background: '#fef3c7', color: '#92400e' });
        } else if (sub.status === 'rejected') {
            currentProofButton.css({ background: '#fee2e2', color: '#991b1b' });
        } else {
            currentProofButton.css({ background: '#d1fae5', color: '#065f46' });
        }
    }

    $(document).on('click', '.proof-view-trigger', function () {
        currentProofButton = $(this);
        currentSubmission = currentProofButton.data('submission');

        $('#proofViewModalLabel').html(
            '<i class="material-icons-outlined" style="font-size:19px;vertical-align:middle;margin-right:6px;color:#667eea;">receipt_long</i>' +
            $('<span>').text(currentProofButton.data('partner')).html()
        );
        renderProofModal();
        $('#proofViewModal').modal('show');
    });

    $(document).on('click', '.proof-approve-btn, .proof-reject-btn', function () {
        var $btn = $(this);
        var $row = $btn.closest('[data-submission-id]');
        var submissionId = parseInt($row.data('submission-id'), 10);
        var isApprove = $btn.hasClass('proof-approve-btn');
        var payload = {
            csrf_token: CSRF_TOKEN,
            submission_id: submissionId,
            action: isApprove ? 'approve' : 'reject'
        };
        if (isApprove) {
            payload.confirmed_amount = $row.find('.proof-amount-input').val();
            payload.confirmed_reference = $row.find('.proof-ref-input').val();
            if (!payload.confirmed_amount || parseFloat(payload.confirmed_amount) <= 0) { alert('Enter a valid confirmed amount.'); return; }
            if (!payload.confirmed_reference) { alert('Enter the confirmed reference number.'); return; }
        } else {
            var reason = prompt('Reason for rejection (shown to the TP):', '');
            if (reason === null) return;
            payload.rejection_reason = reason;
        }
        $row.find('button').prop('disabled', true);

        $.post('tp-advance-submission-review-action-ajax.php', payload)
            .done(function (data) {
                if (!data.success) {
                    alert(data.message || 'Action failed.');
                    $row.find('button').prop('disabled', false);
                    return;
                }
                currentSubmission.status = isApprove ? 'accepted' : 'rejected';
                if (isApprove) {
                    currentSubmission.amount = parseFloat(payload.confirmed_amount);
                    currentSubmission.reference_number = payload.confirmed_reference;
                    currentSubmission.rejection_reason = null;
                } else {
                    currentSubmission.rejection_reason = payload.rejection_reason;
                }
                renderProofModal();
                updateProofButton();
                tasShowAlert(isApprove ? 'Submission verified — you can now add it to the TP advance balance.' : 'Submission rejected.', 'success');
            })
            .fail(function () {
                alert('Could not reach the server. Please try again.');
                $row.find('button').prop('disabled', false);
            });
    });

    $(document).on('click', '.add-advance-toggle-btn', function () {
        $(this).hide().siblings('.advance-form').show();
    });

    $(document).on('click', '.proof-cancel-btn', function () {
        var $btn = $(this);
        var $row = $btn.closest('[data-submission-id]');
        var submissionId = parseInt($row.data('submission-id'), 10);

        var reason = prompt('Reason for cancelling this submission (shown to the TP):', '');
        if (reason === null) return;

        $row.find('button').prop('disabled', true);

        $.post('cancel-tp-advance-submission.php', {
            csrf_token: CSRF_TOKEN,
            submission_id: submissionId,
            reason: reason
        })
            .done(function (data) {
                if (!data.success) {
                    alert(data.message || 'Could not cancel submission.');
                    $row.find('button').prop('disabled', false);
                    return;
                }
                currentSubmission.status = 'rejected';
                currentSubmission.rejection_reason = reason || 'Cancelled by reviewer.';
                renderProofModal();
                updateProofButton();
                tasShowAlert('Submission cancelled.', 'success');
            })
            .fail(function () {
                alert('Could not reach the server. Please try again.');
                $row.find('button').prop('disabled', false);
            });
    });

    $(document).on('click', '.advance-save-btn', function () {
        var $btn = $(this);
        var $row = $btn.closest('[data-submission-id]');
        var submissionId = parseInt($row.data('submission-id'), 10);
        var companyId = $row.find('.advance-company-select').val();

        if (!companyId) { alert('Please select a company profile.'); return; }

        var payload = {
            csrf_token: CSRF_TOKEN,
            submission_id: submissionId,
            company_id: companyId,
            amount: $row.find('.advance-amount-input').val(),
            reference_number: $row.find('.advance-ref-input').val(),
            payment_mode: $row.find('.advance-mode-select').val(),
            payment_date: $row.find('.advance-date-input').val(),
            note: ''
        };
        $row.find('button, select, input').prop('disabled', true);

        $.post('tp-advance-submission-to-advance-payment.php', payload)
            .done(function (data) {
                if (!data.success) {
                    alert(data.message || 'Could not add this as a payment entry.');
                    $row.find('button, select, input').prop('disabled', false);
                    return;
                }
                currentSubmission.advance_payment_id = data.advance_payment_id;
                updateProofButton();
                renderProofModal();

                var tpId = currentSubmission && currentSubmission.territory_partner_id ? currentSubmission.territory_partner_id : '';

                if (fromPo) {
                    // Reached here from tp-today-orders.php's "View This TP's
                    // Submissions" link (excess-balance purchase order) — once
                    // the payment is added to the TP's advance balance, go
                    // straight back to that order instead of the advance
                    // payments list, since that's the task the reviewer was
                    // actually in the middle of.
                    tasShowAlert('Added to TP advance balance (Advance Payment #' + data.advance_payment_id + '). Returning to their purchase orders…', 'success');
                    setTimeout(function () {
                        window.location.href = 'tp-today-orders.php';
                    }, 900);
                } else {
                    // Direct visit to this page — land the reviewer directly
                    // on the new advance payment entry instead of leaving
                    // them to search the full list for it.
                    tasShowAlert('Added to TP advance balance (Advance Payment #' + data.advance_payment_id + '). Taking you to the entry now…', 'success');
                    setTimeout(function () {
                        window.location.href = 'manage-tp-advance-payments.php?highlight=' + encodeURIComponent(data.advance_payment_id)
                            + (tpId ? '&tp_id=' + encodeURIComponent(tpId) : '');
                    }, 900);
                }
            })
            .fail(function () {
                alert('Could not reach the server. Please try again.');
                $row.find('button, select, input').prop('disabled', false);
            });
    });
    </script>
</body>
</html>
