<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/TpCourierPayment.php';
require_once __DIR__ . '/../shared/TpCourierAmountRequest.php';
header('Content-Type: application/json');
error_reporting(0);

function respond(bool $ok, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

$tp_id = (int)$Login_user_IDvl;
$productType = tpResolveProductType($_POST['product_type'] ?? null);

tpEnsureCourierAmountRequestTable($db_conn);

// Never trust the client's box/cover/amount figures — this only exists to
// tell the TP what number to show the BDM while the request is pending;
// re-derive the real ones from the same in-progress draft
// pay-courier-payment.php itself reads from, the same way every other
// consumer of this cart re-validates server-side.
$draft = $_SESSION['po_draft_' . $tp_id] ?? null;
if (!$draft || empty($draft['lines'])) {
    respond(false, 'No purchase order draft found — please start again from your cart.');
}
$items = [];
foreach ($draft['lines'] as $l) {
    if (($l['method'] ?? 'courier') === 'pickup') continue;
    $items[] = ['pid' => (int)$l['pr_id'], 'qty' => (int)$l['qty']];
}

$shipment = tpCourierComputeShipmentForItems($db_conn, $items);
$totalBoxes = $shipment['boxes'];
$totalCovers = $shipment['covers'];
$calculatedAmount = tpCourierComputeAmount($db_conn, $productType, $totalBoxes, $totalCovers);

if ($calculatedAmount <= 0) {
    respond(false, 'There is no courier amount to dispute on this order.');
}

// A request is tied to THIS specific draft via the stashed id, not looked up
// by TP/type/shape — see stash-po-draft.php's comment on why that fuzzier
// match was dropped. If this exact draft already has one, don't allow a
// second.
$existingId = $draft['courier_request_id'] ?? null;
$existing = $existingId ? tpCourierAmountRequestGetById($db_conn, (int)$existingId, $tp_id) : null;
if ($existing) {
    respond(false, $existing['status'] === 'pending'
        ? 'A request is already pending review for this order.'
        : 'Your Sales BDM has already reviewed this order\'s amount.');
}

$note = trim((string)($_POST['note'] ?? ''));
if (mb_strlen($note) > 500) { $note = mb_substr($note, 0, 500); }

$id = tpCourierAmountRequestCreate($db_conn, $tp_id, $productType, $totalBoxes, $totalCovers, $calculatedAmount, $items, $note);
// Bind this new request to the exact draft it was raised for — every later
// consumer (pay-courier-payment.php, upload-courier-payment-screenshot.php,
// purchase-order-action.php) resolves the override through this same flag,
// never through TP/type/box-cover matching alone.
$_SESSION['po_draft_' . $tp_id]['courier_request_id'] = $id;

// The request itself is never lost even with no BDM currently covering this
// TP's district — it still sits in the queue for Company's audit view — but
// the TP should know up front why nobody may act on it for a while, rather
// than assuming it's just slow.
$coveredByBdm = !empty(tpFindBdmIdsForTp($db_conn, $tp_id));
$message = $coveredByBdm
    ? 'Request sent to your Sales BDM.'
    : 'Request submitted, but no Sales BDM is currently assigned to your district — it will be reviewed once one is assigned.';

respond(true, $message, ['id' => $id]);
