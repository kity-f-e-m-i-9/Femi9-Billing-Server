<?php
include("checksession.php");
error_reporting(0);

$enc_id   = $_GET['tpid'] ?? '';
$tp_db_id = (int)base64_decode($enc_id);
if (!$tp_db_id) { header("Location: manage-territory-partner"); exit; }

// Ownership check
$own = $db_conn->prepare("SELECT id, name FROM territory_partners WHERE id=? AND onboard_ss_id=?");
$own->bind_param("is", $tp_db_id, $Login_user_IDvl);
$own->execute();
$tp_row = $own->get_result()->fetch_assoc();
$own->close();
if (!$tp_row) { header("Location: manage-territory-partner?error=notfound"); exit; }

// Prevent deletion if invoices exist for this TP
$inv_chk = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM tp_invoices WHERE territory_partner_id=?");
$inv_chk->bind_param("i", $tp_db_id);
$inv_chk->execute();
$inv_cnt = (int)$inv_chk->get_result()->fetch_assoc()['cnt'];
$inv_chk->close();
if ($inv_cnt > 0) {
    header("Location: manage-territory-partner?error=hasinvoices");
    exit;
}

$db_conn->begin_transaction();
try {
    $d1 = $db_conn->prepare("DELETE FROM territory_partner_locations WHERE territory_partner_id=?");
    $d1->bind_param("i", $tp_db_id); $d1->execute(); $d1->close();

    $d2 = $db_conn->prepare("DELETE FROM territory_partners WHERE id=? AND onboard_ss_id=?");
    $d2->bind_param("is", $tp_db_id, $Login_user_IDvl); $d2->execute(); $d2->close();

    $db_conn->commit();
    header("Location: manage-territory-partner?deletedDone=1");
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    header("Location: manage-territory-partner?error=1");
    exit;
}
