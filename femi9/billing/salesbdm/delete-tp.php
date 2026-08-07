<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
error_reporting(0);

$enc_id   = $_GET['tpid'] ?? '';
$tp_db_id = (int)base64_decode($enc_id);
if (!$tp_db_id) { header("Location: manage-tp"); exit; }

// Ownership check — only allow deleting a TP inside this BDM's own districts.
$myTpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID);
if (!in_array($tp_db_id, $myTpIds, true)) { header("Location: manage-tp"); exit; }

$stmt = $db_conn->prepare("SELECT id, photo FROM territory_partners WHERE id = ?");
$stmt->bind_param("i", $tp_db_id);
$stmt->execute();
$tp = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tp) { header("Location: manage-tp"); exit; }

$stmt_del = $db_conn->prepare("DELETE FROM territory_partners WHERE id = ?");
$stmt_del->bind_param("i", $tp_db_id);
$stmt_del->execute();
$stmt_del->close();

if ($tp['photo']) {
    $photo_path = __DIR__ . '/../company/tp_photo/' . $tp['photo'];
    if (file_exists($photo_path)) {
        @unlink($photo_path);
    }
}

header("Location: manage-tp?deletedDone=1");
exit;
?>
