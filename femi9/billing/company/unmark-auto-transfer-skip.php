<?php
// AJAX backend for internal_transfer_auto.php's "Excluded Today" tab —
// "Include Again" button. Undoes one skip so that order/product line
// counts toward Required Qty again; the order's own status was never
// touched by the skip, so there's nothing to restore there.
include("checksession.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
header('Content-Type: application/json');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

$sourceType = $_POST['source_type'] ?? '';
$sourceRef  = trim($_POST['source_ref'] ?? '');

if (!in_array($sourceType, ['tp', 'ot'], true) || $sourceRef === '') {
    echo json_encode(['success' => false, 'error' => 'invalid']); exit;
}

unmark_auto_transfer_order_skipped($db_conn, $sourceType, $sourceRef);

echo json_encode(['success' => true]);
