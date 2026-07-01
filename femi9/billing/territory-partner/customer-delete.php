<?php
include("checksession.php");
include("config.php");
$prid = (int)base64_decode($_REQUEST['prid'] ?? '');
if ($prid > 0) {
    mysqli_query($db_conn, "DELETE FROM customers WHERE id='$prid'");
}
echo "<script>window.location='customer-manage.php?deletedDone';</script>";
?>
