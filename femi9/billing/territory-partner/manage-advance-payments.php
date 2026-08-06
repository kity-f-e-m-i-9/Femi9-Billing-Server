<?php
include("checksession.php");
include("config.php");
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today     = date("Y-m-d");
$from_date = $_REQUEST['frdate'] ?? date("Y-m-d", strtotime("-30 days"));
$to_date   = $_REQUEST['todate'] ?? $today;

$statusFilter = $_REQUEST['status_filter'] ?? 'all';
$allowedStatusFilters = ['all', 'pending_review', 'accepted', 'rejected'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) $statusFilter = 'all';

// Drafts (still-in-progress, unsubmitted) never show here — they live only
// on add-advance-payment.php until the TP explicitly submits.
$stmt = mysqli_prepare($db_conn,
    "SELECT id, amount, payment_date, payment_mode, reference_number, note, status, rejection_reason, advance_payment_id, created_at
     FROM tp_advance_payment_submissions
     WHERE territory_partner_id=? AND status != 'draft' AND DATE(created_at) BETWEEN ? AND ?"
    . ($statusFilter !== 'all' ? " AND status = ?" : '')
    . "\n     ORDER BY created_at DESC"
);
if ($statusFilter !== 'all') {
    mysqli_stmt_bind_param($stmt, "isss", $Login_user_IDvl, $from_date, $to_date, $statusFilter);
} else {
    mysqli_stmt_bind_param($stmt, "iss", $Login_user_IDvl, $from_date, $to_date);
}
mysqli_stmt_execute($stmt);
$submissions = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

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
$totalAmount   = array_sum(array_column($submissions, 'amount'));
$pendingCount  = count(array_filter($submissions, fn($r) => $r['status'] === 'pending_review'));
$acceptedCount = count(array_filter($submissions, fn($r) => $r['status'] === 'accepted'));
$rejectedCount = count(array_filter($submissions, fn($r) => $r['status'] === 'rejected'));

// Available advance balance — same query as add-advance-payment.php / add-purchase-order.php.
$balStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(balance_amount), 0) AS bal
     FROM tp_advance_payments WHERE territory_partner_id = ? AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL"
);
mysqli_stmt_bind_param($balStmt, "i", $Login_user_IDvl);
mysqli_stmt_execute($balStmt);
$advBalance = (float)(mysqli_stmt_get_result($balStmt)->fetch_assoc()['bal'] ?? 0);
mysqli_stmt_close($balStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Advance Payments : <?php echo $business_name;?></title>
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

        .btn-new-order {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff;
            padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 13.5px;
            text-decoration: none; transition: all .2s;
        }
        .btn-new-order:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,.35); color: #fff; }

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

        .po-status-pill { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; display: inline-block; }
        .po-status-pill.waiting   { background: #fef3c7; color: #92400e; }
        .po-status-pill.completed { background: #d1fae5; color: #065f46; }
        .po-status-pill.cancelled { background: #fee2e2; color: #991b1b; }

        .po-items-trigger {
            border: none; cursor: pointer; background: #667eea; color: #fff;
            font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 20px; white-space: nowrap;
        }
        .apo-remove-chip {
            border: none; background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 600;
            padding: 5px 12px; border-radius: 20px; cursor: pointer; white-space: nowrap;
        }

        .po-empty { text-align: center; padding: 50px 20px; color: #9ca3af; }
        .po-empty .material-icons-outlined { font-size: 40px; display: block; margin: 0 auto 10px; opacity: .4; }

        .adv-shot-card {
            border: 1px solid #f1f5f9; border-radius: 12px; padding: 12px 14px; margin-bottom: 10px;
            display: flex; gap: 14px; align-items: flex-start;
        }
        .adv-shot-card:last-child { margin-bottom: 0; }
        .adv-shot-media { flex: 0 0 90px; }
        .adv-shot-media img { width: 100%; border-radius: 8px; border: 1px solid #e5e7eb; }
        .adv-shot-main { flex: 1; min-width: 0; font-size: 12.5px; }
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
                            <div class="col d-flex align-items-center justify-content-between flex-wrap" style="gap:10px;">
                                <div class="page-description">
                                    <h1 style="margin:0;">My Advance Payments</h1>
                                </div>
                                <a href="add-advance-payment.php" class="btn-new-order">
                                    <i class="material-icons" style="font-size:17px;">add</i> New Advance Payment
                                </a>
                            </div>
                        </div>
                        <br/>

                        <!-- Stats -->
                        <div class="row">
                            <div class="col-lg-3 col-sm-6">
                                <div class="po-stat-card purple">
                                    <div><h3 style="font-size:18px;">₹<?=number_format($advBalance, 2)?></h3><p>Available Advance Balance</p></div>
                                    <i class="material-icons-outlined">account_balance_wallet</i>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="po-stat-card orange">
                                    <div><h3 style="font-size:18px;">₹<?=number_format($totalAmount, 2)?></h3><p>Total Submitted</p></div>
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

                        <!-- Filters -->
                        <div class="po-filter-card">
                            <form method="get" class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">From Date</label>
                                    <input type="date" name="frdate" value="<?=htmlspecialchars($from_date)?>" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">To Date</label>
                                    <input type="date" name="todate" value="<?=htmlspecialchars($to_date)?>" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status_filter" class="form-control">
                                        <option value="all" <?=$statusFilter === 'all' ? 'selected' : ''?>>All</option>
                                        <option value="pending_review" <?=$statusFilter === 'pending_review' ? 'selected' : ''?>>Pending Review</option>
                                        <option value="accepted" <?=$statusFilter === 'accepted' ? 'selected' : ''?>>Approved</option>
                                        <option value="rejected" <?=$statusFilter === 'rejected' ? 'selected' : ''?>>Rejected<?=$rejectedCount ? ' (' . $rejectedCount . ')' : ''?></option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary"><i class="material-icons" style="vertical-align:middle;font-size:17px;">filter_list</i> Filter</button>
                                    <a href="manage-advance-payments.php" class="btn btn-outline-secondary"><i class="material-icons" style="vertical-align:middle;font-size:17px;">refresh</i> Reset</a>
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
                                            <th>Amount</th>
                                            <th>Mode</th>
                                            <th>Reference</th>
                                            <th>Screenshots</th>
                                            <th>Status</th>
                                            <th>Detail</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($submissions)): ?>
                                        <tr><td colspan="8">
                                            <div class="po-empty">
                                                <i class="material-icons-outlined">inbox</i>
                                                No advance payment submissions in this date range.
                                            </div>
                                        </td></tr>
                                        <?php else: foreach ($submissions as $sub):
                                            $detail_json = htmlspecialchars(json_encode($sub, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                        ?>
                                        <tr id="sub-row-<?=(int)$sub['id']?>">
                                            <td><?=htmlspecialchars(date("d-m-Y g:i A", strtotime($sub['created_at'])))?></td>
                                            <td><strong>₹<?=number_format((float)$sub['amount'], 2)?></strong></td>
                                            <td><?=htmlspecialchars($sub['payment_mode'])?></td>
                                            <td><?=htmlspecialchars($sub['reference_number'])?></td>
                                            <td><?=count($sub['screenshots'])?></td>
                                            <td>
                                                <?php if ($sub['status'] === 'accepted'): ?>
                                                <span class="po-status-pill completed">Approved</span>
                                                <?php if ($sub['advance_payment_id']): ?><div class="text-muted" style="font-size:11px;margin-top:3px;">Added to balance</div><?php endif; ?>
                                                <?php elseif ($sub['status'] === 'rejected'): ?>
                                                <span class="po-status-pill cancelled" <?=$sub['rejection_reason'] ? 'title="' . htmlspecialchars($sub['rejection_reason'], ENT_QUOTES) . '"' : ''?>>Rejected</span>
                                                <?php else: ?>
                                                <span class="po-status-pill waiting">Pending Review</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="po-items-trigger detail-view-trigger" data-detail="<?=$detail_json?>">
                                                    View
                                                </button>
                                            </td>
                                            <td>
                                                <?php if ($sub['status'] === 'pending_review'): ?>
                                                <button type="button" class="apo-remove-chip" onclick="cancelSubmission(<?=(int)$sub['id']?>)">Cancel</button>
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

    <!-- Detail Modal -->
    <div class="modal fade" id="detailViewModal" tabindex="-1" aria-labelledby="detailViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-md">
            <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
                <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                    <h6 class="modal-title" id="detailViewModalLabel" style="font-weight:700;color:#1f2937;">
                        <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#667eea;">receipt_long</i>
                        Submission Detail
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailViewModalBody" style="padding:18px 22px;">
                </div>
                <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
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
    function shotStatusText(status) {
        if (status === 'accepted') return 'Verified';
        if (status === 'rejected') return 'Rejected';
        return 'Awaiting Company Review';
    }

    $(document).on('click', '.detail-view-trigger', function () {
        var sub = $(this).data('detail');

        var html = '<div style="font-size:13.5px;">' +
            '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Amount</span><strong>₹' + parseFloat(sub.amount).toFixed(2) + '</strong></div>' +
            '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Payment Mode</span><strong>' + $('<div>').text(sub.payment_mode).html() + '</strong></div>' +
            '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Payment Date</span><strong>' + sub.payment_date + '</strong></div>' +
            '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Reference</span><strong>' + $('<div>').text(sub.reference_number).html() + '</strong></div>' +
            (sub.note ? '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Note</span><strong>' + $('<div>').text(sub.note).html() + '</strong></div>' : '') +
            '</div>';

        if (sub.status === 'rejected' && sub.rejection_reason) {
            html += '<div class="alert alert-danger mt-3 mb-0" style="font-size:12.5px;">' + $('<div>').text(sub.rejection_reason).html() + '</div>';
        } else if (sub.status === 'accepted') {
            html += '<div class="alert alert-success mt-3 mb-2" style="font-size:12.5px;">Approved' + (sub.advance_payment_id ? ' and added to your advance balance.' : ' — awaiting addition to your advance balance.') + '</div>';
        }

        html += '<hr><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#9ca3af;margin-bottom:8px;">Screenshots (' + sub.screenshots.length + ')</div>';

        sub.screenshots.forEach(function (s) {
            var isHeic = /\.hei[cf]$/i.test(s.file_path);
            var fileUrl = 'advance_payment_screenshots/' + encodeURIComponent(s.file_path);
            var mediaHtml = isHeic
                ? '<div class="adv-shot-media d-flex align-items-center justify-content-center" style="height:70px;background:#f3f4f6;border-radius:8px;">' +
                  '<a href="' + fileUrl + '" target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size:10px;">HEIC</a></div>'
                : '<div class="adv-shot-media"><a href="' + fileUrl + '" target="_blank"><img src="' + fileUrl + '"></a></div>';
            var detail = s.detected_amount !== null
                ? 'Detected: ₹' + parseFloat(s.detected_amount).toFixed(2) + (s.reference_number ? ', Ref: ' + $('<div>').text(s.reference_number).html() : '')
                : (s.rejection_reason ? $('<div>').text(s.rejection_reason).html() : 'Not detected');

            html += '<div class="adv-shot-card">' + mediaHtml +
                '<div class="adv-shot-main"><strong>' + shotStatusText(s.status) + '</strong><div class="mt-1">' + detail + '</div></div></div>';
        });

        $('#detailViewModalBody').html(html);
        $('#detailViewModal').modal('show');
    });

    function cancelSubmission(id) {
        if (!confirm('Cancel this advance payment submission? This cannot be undone.')) return;
        fetch('cancel-advance-payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'submission_id=' + encodeURIComponent(id)
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) { alert(data.message || 'Could not cancel submission.'); return; }
                var row = document.getElementById('sub-row-' + id);
                if (row) row.remove();
            })
            .catch(function() { alert('Could not cancel submission — please check your connection.'); });
    }
    </script>
</body>
</html>
