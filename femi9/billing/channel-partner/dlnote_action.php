<?php
include("checksession.php");
include("config.php");
error_reporting(0);

if (!isset($_REQUEST['UpdateDlNote'])) {
    echo "<script>window.location='dashboard.php';</script>"; exit;
}

$inv_id   = mysqli_real_escape_string($db_conn, $_POST['inv_id']    ?? '');
$inv_num  = mysqli_real_escape_string($db_conn, $_POST['inv_number']?? '');
$inv_tbl  = mysqli_real_escape_string($db_conn, $_POST['inv_table'] ?? 'shop');

$fields = ['dl_note','mode_pmnt','ref_no','ref_date','ot_ref','order_no','dated','dispatch_doc_no','dlnote_date','dispatch_through','destination','terms'];
$data = [];
foreach ($fields as $f) { $data[$f] = mysqli_real_escape_string($db_conn, $_POST[$f] ?? ''); }

$exists = mysqli_fetch_array(mysqli_query($db_conn, "SELECT COUNT(*) AS n FROM delivery_note WHERE inv_id='$inv_id'"));
if ((int)$exists['n'] === 0) {
    mysqli_query($db_conn, "INSERT INTO delivery_note (inv_id,inv_number,inv_table,dl_note,mode_pmnt,ref_no,ref_date,ot_ref,order_no,dated,dispatch_doc_no,dlnote_date,dispatch_through,destination,terms) VALUES ('$inv_id','$inv_num','$inv_tbl','{$data['dl_note']}','{$data['mode_pmnt']}','{$data['ref_no']}','{$data['ref_date']}','{$data['ot_ref']}','{$data['order_no']}','{$data['dated']}','{$data['dispatch_doc_no']}','{$data['dlnote_date']}','{$data['dispatch_through']}','{$data['destination']}','{$data['terms']}')");
} else {
    mysqli_query($db_conn, "UPDATE delivery_note SET dl_note='{$data['dl_note']}',mode_pmnt='{$data['mode_pmnt']}',ref_no='{$data['ref_no']}',ref_date='{$data['ref_date']}',ot_ref='{$data['ot_ref']}',order_no='{$data['order_no']}',dated='{$data['dated']}',dispatch_doc_no='{$data['dispatch_doc_no']}',dlnote_date='{$data['dlnote_date']}',dispatch_through='{$data['dispatch_through']}',destination='{$data['destination']}',terms='{$data['terms']}' WHERE inv_id='$inv_id'");
}

$printurl = ($inv_tbl === 'shop') ? 'shop-invoice-print.php' : 'customer-invoice-print.php';
echo "<script>window.location='{$printurl}?invoiceid=".base64_encode($inv_id)."';</script>";
