<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");

$prid=$_REQUEST['rowid'];
$prid=base64_decode($prid);

$del_product="delete from stock where id=?";
db_q($db_conn,$del_product,[$prid]);

$_SESSION['successMessage']="One product stock deleted";
echo "<script>window.location='overall-stock.php?deletedDone';</script>";
?>