<?php
include("checksession.php");
error_reporting(0);

$enc_id   = $_GET['tpid'] ?? '';
$tp_db_id = (int)base64_decode($enc_id);
if (!$tp_db_id) { header("Location: manage-territory-partner"); exit; }

// Verify exists
$stmt = $db_conn->prepare("SELECT id, photo FROM territory_partners WHERE id = ?");
$stmt->bind_param("i", $tp_db_id);
$stmt->execute();
$tp = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tp) { header("Location: manage-territory-partner"); exit; }

// Delete — territory_partner_locations rows cascade automatically
$stmt_del = $db_conn->prepare("DELETE FROM territory_partners WHERE id = ?");
$stmt_del->bind_param("i", $tp_db_id);
$stmt_del->execute();
$stmt_del->close();

// Remove photo file if exists
if ($tp['photo']) {
    $photo_path = __DIR__ . '/tp_photo/' . $tp['photo'];
    if (file_exists($photo_path)) {
        @unlink($photo_path);
    }
}

header("Location: manage-territory-partner?deletedDone=1");
exit;
