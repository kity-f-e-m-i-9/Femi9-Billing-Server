<?php
/**
 * Delete Partner Location
 * Guards against deletion when the node has active children.
 * Called via GET with prid (base64-encoded node id) and optional return_parent.
 */

include("checksession.php");
error_reporting(0);

$prid         = isset($_GET['prid'])          ? trim($_GET['prid'])  : '';
$return_parent = isset($_GET['return_parent']) ? trim($_GET['return_parent']) : '';

$node_id = $prid ? (int) base64_decode($prid) : 0;

if (!$node_id) {
    header("Location: manage-partner-location");
    exit;
}

$manage_url = "manage-partner-location" . ($return_parent !== '' ? "?parent_id=$return_parent" : "");

// Fetch node
$stmt_n = $db_conn->prepare("SELECT id, name, parent_id FROM partner_location_nodes WHERE id = ?");
$stmt_n->bind_param("i", $node_id);
$stmt_n->execute();
$node = $stmt_n->get_result()->fetch_assoc();
$stmt_n->close();

if (!$node) {
    header("Location: $manage_url");
    exit;
}

// Guard: block if any children exist
$stmt_c = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM partner_location_nodes WHERE parent_id = ?");
$stmt_c->bind_param("i", $node_id);
$stmt_c->execute();
$child_count = (int) $stmt_c->get_result()->fetch_assoc()['cnt'];
$stmt_c->close();

if ($child_count > 0) {
    header("Location: {$manage_url}" . ($return_parent !== '' ? '' : '?') . ($return_parent !== '' ? '&' : '') . "hasChildren=1");
    exit;
}

// Delete
$stmt_d = $db_conn->prepare("DELETE FROM partner_location_nodes WHERE id = ?");
$stmt_d->bind_param("i", $node_id);
$stmt_d->execute();
$stmt_d->close();

header("Location: {$manage_url}" . ($return_parent !== '' ? '&' : '?') . "deletedDone=1");
exit;
