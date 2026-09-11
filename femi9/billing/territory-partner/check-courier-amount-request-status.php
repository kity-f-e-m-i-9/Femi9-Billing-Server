<?php
// Polled from pay-courier-payment.php while a "Change Courier Amount"
// request is pending, so the TP sees the Sales BDM's decision land live
// instead of having to notice and manually reload the page. Confirmed
// 2026-09-11.
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpCourierAmountRequest.php';
error_reporting(0);
header('Content-Type: application/json');

$tp_id = (int)$Login_user_IDvl;
$requestId = (int)($_GET['request_id'] ?? 0);

if ($requestId <= 0) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

tpEnsureCourierAmountRequestTable($db_conn);
$request = tpCourierAmountRequestGetById($db_conn, $requestId, $tp_id);

if (!$request) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

echo json_encode([
    'status' => $request['status'],
    'approved_amount' => $request['approved_amount'] !== null ? (float)$request['approved_amount'] : null,
]);
