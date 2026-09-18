<?php
// AJAX backend for internal_transfer_auto.php's "View Breakdown" modal —
// per-order detail (TP purchase orders / OT drafts / WhatsApp orders) for
// one product's contribution to today's auto-transfer requirement.
include("checksession.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
header('Content-Type: application/json');
error_reporting(0);

$productId = (int) ($_GET['product_id'] ?? 0);
if (!$productId) { echo json_encode(['tp' => [], 'ot' => [], 'wa' => []]); exit; }

$llpId = resolve_godown_id_by_gname($db_conn, 'FEMI NAYAN LLP');
if (!$llpId) { echo json_encode(['tp' => [], 'ot' => [], 'wa' => []]); exit; }

echo json_encode(get_auto_transfer_breakdown_for_product($db_conn, $productId, $llpId));
