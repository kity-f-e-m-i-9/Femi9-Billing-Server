<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$transfer_id = (int)($_GET['id'] ?? 0);
if ($transfer_id <= 0) { echo json_encode(['error' => 'Invalid ID']); exit; }

// Transfer header
$stmt = $db_conn->prepare("
    SELECT t.id, t.transfer_type, t.transfer_date, t.ref_number, t.note, t.created_by, t.created_at,
           g.gname AS godown_name,
           COALESCE(cp.name, pln.name) AS location_name
    FROM pl_godown_transfers t
    JOIN company_godown g ON g.id = t.godown_id
    LEFT JOIN partner_location_nodes pln ON pln.id = t.location_id
    LEFT JOIN channel_partners cp ON cp.id = t.cp_id
    WHERE t.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $transfer_id);
$stmt->execute();
$transfer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transfer) { echo json_encode(['error' => 'Not found']); exit; }

// Items with product names
$items_res = $db_conn->query("
    SELECT p.productName AS product_name, ti.quantity
    FROM pl_godown_transfer_items ti
    JOIN products p ON p.id = ti.product_id
    WHERE ti.transfer_id = $transfer_id
    ORDER BY p.productName ASC
");
$items = $items_res ? $items_res->fetch_all(MYSQLI_ASSOC) : [];

echo json_encode(['transfer' => $transfer, 'items' => $items]);
