<?php require_once __DIR__ . '/../shared/db-helpers.php'; 
include("checksession.php");
error_reporting(0);

$rowid=$_REQUEST['rowid'];
$rowid=base64_decode($rowid);

//DELETE STOCKIST DETAILS
$del_product="delete from stockiest where id=?";
db_q($db_conn,$del_product,[$rowid]);

//UNASSIGNED TALUK
$talukID=$_REQUEST['talukID'];
$un_assigned="update taluk set assigned_SID='Nil' where id=?";
db_q($db_conn,$un_assigned,[$talukID]);

$_SESSION['SuccessMessage']="One Pending Stockist Deleted Successfully!";
echo "<script>window.location='pending-stockist?deletedDone';</script>";
?>