<?php
// AJAX backend for internal_transfer_auto.php's breakdown modal "Not
// Today" button — excludes one specific order from today's auto-transfer
// requirement without touching that order's own status anywhere.
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

if (!in_array($sourceType, ['tp', 'ot', 'wa'], true) || $sourceRef === '') {
    echo json_encode(['success' => false, 'error' => 'invalid']); exit;
}

$createdBy = $_SESSION['LOGIN_USER'] ?? null;
mark_auto_transfer_order_skipped($db_conn, $sourceType, $sourceRef, 'excluded', $createdBy);

echo json_encode(['success' => true]);
