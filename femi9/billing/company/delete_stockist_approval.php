<?php 
include("checksession.php");
error_reporting(0);

$rowid=$_REQUEST['rowid'];
$rowid=base64_decode($rowid);

//DELETE STOCKIST DETAILS
$del_product="delete from stockiest where id='$rowid'";
mysqli_query($db_conn,$del_product);

//UNASSIGNED TALUK
$talukID=$_REQUEST['talukID'];
$un_assigned="update taluk set assigned_SID='Nil' where id='$talukID'";
mysqli_query($db_conn,$un_assigned);

$_SESSION['SuccessMessage']="One Pending Stockist Deleted Successfully!";
echo "<script>window.location='pending-stockist?deletedDone';</script>";
?>