<?php
include("checksession.php");
error_reporting(0);

$enc_id   = $_GET['cpid'] ?? '';
$cp_db_id = (int)base64_decode($enc_id);
if (!$cp_db_id) { header("Location: manage-channel-partner"); exit; }

// Verify exists
$stmt = $db_conn->prepare("SELECT id, photo FROM channel_partners WHERE id = ?");
$stmt->bind_param("i", $cp_db_id);
$stmt->execute();
$cp = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$cp) { header("Location: manage-channel-partner"); exit; }

// Delete — channel_partner_locations rows cascade automatically
$stmt_del = $db_conn->prepare("DELETE FROM channel_partners WHERE id = ?");
$stmt_del->bind_param("i", $cp_db_id);
$stmt_del->execute();
$stmt_del->close();

// Remove photo file if exists
if ($cp['photo']) {
    $photo_path = __DIR__ . '/cp_photo/' . $cp['photo'];
    if (file_exists($photo_path)) {
        @unlink($photo_path);
    }
}

header("Location: manage-channel-partner?deletedDone=1");
exit;
