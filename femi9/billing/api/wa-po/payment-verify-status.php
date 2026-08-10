<?php
/**
 * GET /payment/verify-status?proof_id=PRF-9931&session_token=...
 * Spec §1.7 — polls the current status of a submission. Values returned:
 * pending | approved | rejected (spec's exact wording — note the
 * submission row itself uses pending_review/accepted/rejected internally;
 * this endpoint translates to the spec's public vocabulary).
 */
require_once __DIR__ . '/_bootstrap.php';

$session = wa_po_require_session($db_conn, $input);

$proofId = trim((string)($_GET['proof_id'] ?? ($input['proof_id'] ?? '')));
if ($proofId === '') wa_po_fail(400, 'proof_id is required');

if (!preg_match('/^PRF-(\d+)$/', $proofId, $m)) {
    wa_po_fail(400, 'proof_id is not a valid format');
}
$submissionId = (int)$m[1];

$stmt = mysqli_prepare($db_conn,
    "SELECT id, user_category, user_id, amount, status, rejection_reason
     FROM wa_po_advance_payment_submissions WHERE id = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $submissionId);
mysqli_stmt_execute($stmt);
$row = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$row) {
    wa_po_fail(404, 'proof_id not found');
}

// Ownership check: a session can only poll its own submissions.
if ($row['user_category'] !== $session['user_category'] || (int)$row['user_id'] !== $session['user_id']) {
    wa_po_fail(403, 'This proof_id does not belong to the authenticated session');
}

$statusMap = [
    'pending_review' => 'pending',
    'draft' => 'pending',
    'accepted' => 'approved',
    'rejected' => 'rejected',
];
$publicStatus = $statusMap[$row['status']] ?? 'pending';

echo json_encode([
    'proof_id' => $proofId,
    'status' => $publicStatus,
    'verified_amount' => $publicStatus === 'approved' ? (float)$row['amount'] : null,
    'remarks' => $row['rejection_reason'],
]);
