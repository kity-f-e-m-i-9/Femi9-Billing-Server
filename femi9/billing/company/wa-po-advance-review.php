<?php
/**
 * Minimal company-side review list for wa_po_advance_payment_submissions
 * (the WhatsApp-automation subsystem's own pending advance-payment
 * submissions) — separate from and does not modify any existing TP
 * review screen. Lists pending_review rows with approve/reject buttons
 * that POST to wa-po-advance-review-action.php.
 */
include("checksession.php");
error_reporting(0);

$stmt = $db_conn->prepare(
    "SELECT s.id, s.user_category, s.user_id, s.amount, s.payment_date, s.payment_mode,
            s.reference_number, s.note, s.created_at,
            sc.file_path, sc.status AS screenshot_status, sc.detected_amount, sc.rejection_reason AS screenshot_reason
     FROM wa_po_advance_payment_submissions s
     LEFT JOIN wa_po_advance_payment_screenshots sc ON sc.submission_id = s.id
     WHERE s.status = 'pending_review'
     ORDER BY s.created_at DESC"
);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>WhatsApp PO — Advance Payment Review</title>
    <style>
        body { font-family: -apple-system, Arial, sans-serif; background: #f5f6f8; margin: 0; padding: 24px; }
        h1 { font-size: 20px; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 13px; vertical-align: top; }
        th { background: #fafafa; }
        img.ss { max-width: 120px; max-height: 120px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; margin-right: 4px; }
        .btn-approve { background: #16a34a; color: #fff; }
        .btn-reject { background: #dc2626; color: #fff; }
        .empty { padding: 24px; text-align: center; color: #888; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
        .badge-accepted { background: #dcfce7; color: #166534; }
        .badge-pending_review { background: #fef9c3; color: #854d0e; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <h1>WhatsApp PO — Advance Payment Review</h1>
    <?php if (empty($rows)): ?>
        <div class="empty">No pending submissions.</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Category</th><th>User ID</th><th>Amount</th><th>Payment Date</th>
                <th>Mode</th><th>Reference</th><th>Screenshot</th><th>Auto Status</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td>PRF-<?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($r['user_category']) ?></td>
                <td><?= (int)$r['user_id'] ?></td>
                <td>&#8377;<?= number_format((float)$r['amount'], 2) ?></td>
                <td><?= htmlspecialchars($r['payment_date']) ?></td>
                <td><?= htmlspecialchars($r['payment_mode']) ?></td>
                <td><?= htmlspecialchars($r['reference_number'] ?? '') ?></td>
                <td>
                    <?php if (!empty($r['file_path'])): ?>
                        <img class="ss" src="../api/wa-po/payment_screenshots/<?= htmlspecialchars($r['file_path']) ?>" alt="proof">
                        <div>detected: &#8377;<?= htmlspecialchars((string)($r['detected_amount'] ?? '—')) ?></div>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge badge-<?= htmlspecialchars($r['screenshot_status'] ?? 'pending_review') ?>"><?= htmlspecialchars($r['screenshot_status'] ?? 'pending_review') ?></span>
                    <?php if (!empty($r['screenshot_reason'])): ?><div><?= htmlspecialchars($r['screenshot_reason']) ?></div><?php endif; ?>
                </td>
                <td>
                    <button class="btn-approve" onclick="waPoReview(<?= (int)$r['id'] ?>, 'approve')">Approve</button>
                    <button class="btn-reject" onclick="waPoReview(<?= (int)$r['id'] ?>, 'reject')">Reject</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <script>
        function waPoReview(submissionId, action) {
            let reason = '';
            if (action === 'reject') {
                reason = prompt('Rejection reason:');
                if (reason === null || reason.trim() === '') return;
            } else if (!confirm('Approve this submission?')) {
                return;
            }
            const body = new URLSearchParams({ submission_id: submissionId, action: action, rejection_reason: reason });
            fetch('wa-po-advance-review-action.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Done: ' + data.status);
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(() => alert('Request failed.'));
        }
    </script>
</body>
</html>
