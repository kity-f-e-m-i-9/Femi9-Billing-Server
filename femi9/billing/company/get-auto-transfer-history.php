<?php
// AJAX backend for internal_transfer_auto.php's "Transfer History" button
// — per-product before-stock (Neksomo/Healthcare/LLP) + qty transferred +
// timestamp, for every auto-transfer run on one calendar date.
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/NeksomoStockBridge.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
header('Content-Type: application/json');
error_reporting(0);

$date = trim($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['rows' => [], 'error' => 'Invalid date']); exit;
}

$neksomoId    = get_neksomo_godown_id($db_conn);
$healthcareId = resolve_godown_id_by_gname($db_conn, 'FEMI HEALTH CARE');
$llpId        = resolve_godown_id_by_gname($db_conn, 'FEMI NAYAN LLP');

if (!$neksomoId || !$healthcareId || !$llpId) {
    echo json_encode(['rows' => [], 'error' => 'Required company profiles not found']); exit;
}

echo json_encode(['rows' => get_auto_transfer_history_for_date($db_conn, $date, $neksomoId, $healthcareId, $llpId)]);
