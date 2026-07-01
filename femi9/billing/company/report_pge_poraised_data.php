<?php

//super stockist
$select_count_PORAISE_SSUSER="select count(*) as numSSUSER from stock_request where fromusertype='super_stockiest' and status='pending'";
$fethc_ount_PORAISE_SSUSER=mysqli_query($db_conn,$select_count_PORAISE_SSUSER);
$result_count_PORAISE_SSUSER=mysqli_fetch_array($fethc_ount_PORAISE_SSUSER);
$show_PORAISE_SSUSER=$result_count_PORAISE_SSUSER['numSSUSER'];

//stockist
$select_count_PORAISE_STUSER="select count(*) as numSTUSER from stock_request where fromusertype='stockiest' and status='pending'";
$fethc_ount_PORAISE_STUSER=mysqli_query($db_conn,$select_count_PORAISE_STUSER);
$result_count_PORAISE_STUSER=mysqli_fetch_array($fethc_ount_PORAISE_STUSER);
$show_PORAISE_STUSER=$result_count_PORAISE_STUSER['numSTUSER'];


//distributor
$select_count_PORAISE_DTUSER="select count(*) as numDTUSER from stock_request where fromusertype='distributor' and status='pending'";
$fethc_ount_PORAISE_DTUSER=mysqli_query($db_conn,$select_count_PORAISE_DTUSER);
$result_count_PORAISE_DTUSER=mysqli_fetch_array($fethc_ount_PORAISE_DTUSER);
$show_PORAISE_DTUSER=$result_count_PORAISE_DTUSER['numDTUSER'];

?>