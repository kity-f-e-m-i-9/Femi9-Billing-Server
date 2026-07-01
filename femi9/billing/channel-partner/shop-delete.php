<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$prid = mysqli_real_escape_string($db_conn, base64_decode($_REQUEST['prid'] ?? ''));
if (empty($prid)) { header("Location: shop-manage.php"); exit; }

mysqli_query($db_conn, "DELETE FROM shop WHERE id='$prid'");
echo "<script>window.location='shop-manage.php?deletedDone';</script>";
