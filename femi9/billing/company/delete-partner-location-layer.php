<?php
include("checksession.php");
error_reporting(0);

$prid     = isset($_GET['prid']) ? trim($_GET['prid']) : '';
$layer_id = $prid ? (int) base64_decode($prid) : 0;

if (!$layer_id) {
    header("Location: manage-partner-location-layers");
    exit;
}

// Fetch layer
$stmt_l = $db_conn->prepare("SELECT id, depth, layer_name FROM partner_location_layers WHERE id = ?");
$stmt_l->bind_param("i", $layer_id);
$stmt_l->execute();
$layer = $stmt_l->get_result()->fetch_assoc();
$stmt_l->close();

if (!$layer) {
    header("Location: manage-partner-location-layers");
    exit;
}

// Guard: block if any nodes exist at this depth
$stmt_n = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM partner_location_nodes WHERE depth = ?");
$stmt_n->bind_param("i", $layer['depth']);
$stmt_n->execute();
$node_cnt = (int)$stmt_n->get_result()->fetch_assoc()['cnt'];
$stmt_n->close();

if ($node_cnt > 0) {
    header("Location: manage-partner-location-layers?hasNodes=1");
    exit;
}

// Delete
$stmt_d = $db_conn->prepare("DELETE FROM partner_location_layers WHERE id = ?");
$stmt_d->bind_param("i", $layer_id);
$stmt_d->execute();
$stmt_d->close();

header("Location: manage-partner-location-layers?deletedDone=1");
exit;
