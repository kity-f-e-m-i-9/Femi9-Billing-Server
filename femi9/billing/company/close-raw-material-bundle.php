<?php
// femi9/billing/company/close-raw-material-bundle.php
//
// AJAX backend for the "Close Bundle" quick-action on
// neksomo-piece-pack-convert.php's bundle picker — lets the operator
// close an exhausted bundle right where they're converting, without
// leaving the page and losing the rest of the form. Wraps the same
// close_raw_material_bundle() used by raw-material-bundles-manage.php
// (carry-forward logic included), so behavior stays identical between
// both entry points.

include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/RawMaterialBundles.php");
include("config.php");
header('Content-Type: application/json');
error_reporting(0);

// Dedicated to the neksomo login (admin retained for oversight/support),
// matching Convert Pieces<->Packs' own gate.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_csrf']);
    exit;
}

$bundleId = (int) ($_POST['bundle_id'] ?? 0);
$closedBy = $_SESSION['LOGIN_USER'] ?? 'system';

if (!$bundleId) {
    echo json_encode(['success' => false, 'error' => 'invalid_bundle']);
    exit;
}

$db_conn->begin_transaction();
try {
    $closed = close_raw_material_bundle($db_conn, $bundleId, $closedBy);
    $db_conn->commit();
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log('[close-raw-material-bundle] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'server_error']);
    exit;
}

echo json_encode(['success' => $closed]);
