<?php
// AJAX backend for internal_transfer_auto.php's page-level "View All
// Orders" modal — every order (TP PO / OT draft) contributing to today's
// auto-transfer, each with ALL of its own product lines (not scoped to
// one product, unlike get-auto-transfer-breakdown.php).
include("checksession.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
header('Content-Type: application/json');
error_reporting(0);

$llpId = resolve_godown_id_by_gname($db_conn, 'FEMI NAYAN LLP');
if (!$llpId) { echo json_encode(['tp' => [], 'ot' => []]); exit; }

echo json_encode(get_auto_transfer_orders_overview($db_conn, $llpId));
