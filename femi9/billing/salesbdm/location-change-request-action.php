<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once __DIR__ . '/../shared/ShopLocationChangeRequest.php';
header('Content-Type: application/json');
error_reporting(0);

function respond(bool $ok, string $message = ''): void
{
    echo json_encode(['success' => $ok, 'message' => $message]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$decision = $_POST['decision'] ?? '';
$reason = trim($_POST['reason'] ?? '');
if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
    respond(false, 'Invalid request.');
}
if ($decision === 'approved' && $reason === '') {
    respond(false, 'Please enter a reason for accepting this request.');
}

ensureShopLocationChangeRequestsTable($db_conn);

// Same district-name scoping as the listing page — a request id guessed/
// tampered from outside this BDM's own districts is rejected here rather
// than trusted from the client.
$districtNames = array_map(fn($n) => mb_strtolower(trim($n)), getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID));
if (empty($districtNames)) { respond(false, 'You have no assigned districts.'); }

$row = $db_conn->query("SELECT id, shop_id, status, district_name FROM ms_shop_location_requests WHERE id = $id")->fetch_assoc();
if (!$row) { respond(false, 'Request not found.'); }
if ($row['status'] !== 'pending') { respond(false, 'This request has already been reviewed.'); }
if (!in_array(mb_strtolower(trim($row['district_name'] ?? '')), $districtNames, true)) { respond(false, 'This request is not assigned to you.'); }

$shop_id = (int)$row['shop_id'];

$bdmName = $_SESSION['LOGIN_USER_NAME'] ?? '';

$db_conn->begin_transaction();
try {
    $stmt = $db_conn->prepare("UPDATE ms_shop_location_requests SET status=?, accept_reason=?, responded_at=NOW(), responded_by_bdm_id=?, responded_by_name=? WHERE id=?");
    $stmt->bind_param('ssisi', $decision, $reason, $salesBdmID, $bdmName, $id);
    $stmt->execute();
    $stmt->close();

    if ($decision === 'approved') {
        // Unlocks 2 fresh manual recaptures for this shop (see
        // edit-ss-action.php's location_recapture_count enforcement).
        $db_conn->query("UPDATE ms_shop SET location_recapture_count=0 WHERE id='$shop_id'");
    }

    $db_conn->commit();
} catch (Throwable $e) {
    $db_conn->rollback();
    respond(false, 'Could not save — please refresh and try again.');
}

respond(true);
