<?php require_once __DIR__ . '/../shared/db-helpers.php';
// OPTIMIZED report_company_sales.php
// Fixed and optimized version with all time periods enabled

//-------------------------------------Today---------------------------------------
//---------------------------------------------------------------------------------
//Today Invoice Count
$select_count_invoice_today="SELECT COUNT(*) as numinvoicetdy FROM user_invoice WHERE date=? AND from_user_type=? AND sub_total>0";
$fetch_count_invoice_today=db_q($db_conn,$select_count_invoice_today,[$today_date,$Login_user_TYPEvl]);
$result_count_invoice_today=mysqli_fetch_array($fetch_count_invoice_today);

$select_count_invoice_today2="SELECT COUNT(*) as numinvcntcus FROM invoice WHERE date=? AND user_type=? AND sub_total>0";
$fetch_count_invoice_today2=db_q($db_conn,$select_count_invoice_today2,[$today_date,$Login_user_TYPEvl]);
$result_count_invoice_today2=mysqli_fetch_array($fetch_count_invoice_today2);

$today_invoice_count = ($result_count_invoice_today['numinvoicetdy'] ?? 0) + ($result_count_invoice_today2['numinvcntcus'] ?? 0);

//Today Product Qty
$select_prqty_today="SELECT COALESCE(SUM(qty), 0) as total_qty FROM user_invoice_items WHERE date=? AND from_user_type=?";
$fetch_prqty_today=db_q($db_conn,$select_prqty_today,[$today_date,$Login_user_TYPEvl]);
$result_prqty_today=mysqli_fetch_array($fetch_prqty_today);

$select_prqty12_today="SELECT COALESCE(SUM(qty), 0) as total_qty FROM invoice_items WHERE date=? AND user_type=?";
$fetch_prqty12_today=db_q($db_conn,$select_prqty12_today,[$today_date,$Login_user_TYPEvl]);
$result_prqty12_today=mysqli_fetch_array($fetch_prqty12_today);

$today_total_qty = ($result_prqty_today['total_qty'] ?? 0) + ($result_prqty12_today['total_qty'] ?? 0);

//Today Total Amount
$select_totalamount_today="SELECT COALESCE(SUM(total), 0) as total_amount FROM user_invoice WHERE date=? AND from_user_type=?";
$fetch_totalamount_today=db_q($db_conn,$select_totalamount_today,[$today_date,$Login_user_TYPEvl]);
$result_totalamount_today=mysqli_fetch_array($fetch_totalamount_today);

$select_totalamount_today2="SELECT COALESCE(SUM(total), 0) as total_amount FROM invoice WHERE date=? AND user_type=?";
$fetch_totalamount_today2=db_q($db_conn,$select_totalamount_today2,[$today_date,$Login_user_TYPEvl]);
$result_totalamount_today2=mysqli_fetch_array($fetch_totalamount_today2);

$today_total_amountSUM = ($result_totalamount_today['total_amount'] ?? 0) + ($result_totalamount_today2['total_amount'] ?? 0);
$today_total_amount = $today_total_amountSUM > 0 ? inr_format($today_total_amountSUM, 2) : "0.00";

//-------------------------------------Yesterday-----------------------------------
//---------------------------------------------------------------------------------
//Yesterday Invoice Count - ENABLED AND OPTIMIZED
$select_count_invoice_yesterday="SELECT COUNT(*) as numinvoicetdy FROM user_invoice WHERE date=? AND from_user_type=? AND sub_total>0";
$fetch_count_invoice_yesterday=db_q($db_conn,$select_count_invoice_yesterday,[$Yesterday_date,$Login_user_TYPEvl]);
$result_count_invoice_yesterday=mysqli_fetch_array($fetch_count_invoice_yesterday);

$select_count_invoice_yesterday2="SELECT COUNT(*) as numinvcntcus FROM invoice WHERE date=? AND user_type=? AND sub_total>0";
$fetch_count_invoice_yesterday2=db_q($db_conn,$select_count_invoice_yesterday2,[$Yesterday_date,$Login_user_TYPEvl]);
$result_count_invoice_yesterday2=mysqli_fetch_array($fetch_count_invoice_yesterday2);

$yesterday_invoice_count = ($result_count_invoice_yesterday['numinvoicetdy'] ?? 0) + ($result_count_invoice_yesterday2['numinvcntcus'] ?? 0);

//Yesterday Product Qty
$select_prqty_yesterday="SELECT COALESCE(SUM(qty), 0) as total_qty FROM user_invoice_items WHERE date=? AND from_user_type=?";
$fetch_prqty_yesterday=db_q($db_conn,$select_prqty_yesterday,[$Yesterday_date,$Login_user_TYPEvl]);
$result_prqty_yesterday=mysqli_fetch_array($fetch_prqty_yesterday);

$select_prqty12_yesterday="SELECT COALESCE(SUM(qty), 0) as total_qty FROM invoice_items WHERE date=? AND user_type=?";
$fetch_prqty12_yesterday=db_q($db_conn,$select_prqty12_yesterday,[$Yesterday_date,$Login_user_TYPEvl]);
$result_prqty12_yesterday=mysqli_fetch_array($fetch_prqty12_yesterday);

$yesterday_total_qty = ($result_prqty_yesterday['total_qty'] ?? 0) + ($result_prqty12_yesterday['total_qty'] ?? 0);

//Yesterday Total Amount
$select_totalamount_yesterday="SELECT COALESCE(SUM(total), 0) as total_amount FROM user_invoice WHERE date=? AND from_user_type=?";
$fetch_totalamount_yesterday=db_q($db_conn,$select_totalamount_yesterday,[$Yesterday_date,$Login_user_TYPEvl]);
$result_totalamount_yesterday=mysqli_fetch_array($fetch_totalamount_yesterday);

$select_totalamount_yesterday2="SELECT COALESCE(SUM(total), 0) as total_amount FROM invoice WHERE date=? AND user_type=?";
$fetch_totalamount_yesterday2=db_q($db_conn,$select_totalamount_yesterday2,[$Yesterday_date,$Login_user_TYPEvl]);
$result_totalamount_yesterday2=mysqli_fetch_array($fetch_totalamount_yesterday2);

$yesterday_total_amountSUM = ($result_totalamount_yesterday['total_amount'] ?? 0) + ($result_totalamount_yesterday2['total_amount'] ?? 0);
$yesterday_total_amount = $yesterday_total_amountSUM > 0 ? inr_format($yesterday_total_amountSUM, 2) : "0.00";

//-------------------------------------This Month----------------------------------
//---------------------------------------------------------------------------------
//This Month Invoice Count - ENABLED AND OPTIMIZED
$select_count_invoice_thismonth="SELECT COUNT(*) as numinvoicetdy FROM user_invoice WHERE date BETWEEN ? AND ? AND from_user_type=? AND sub_total>0";
$fetch_count_invoice_thismonth=db_q($db_conn,$select_count_invoice_thismonth,[$start_date,$endDate,$Login_user_TYPEvl]);
$result_count_invoice_thismonth=mysqli_fetch_array($fetch_count_invoice_thismonth);

$select_count_invoice_thismonth2="SELECT COUNT(*) as numinvcntcus FROM invoice WHERE date BETWEEN ? AND ? AND user_type=? AND sub_total>0";
$fetch_count_invoice_thismonth2=db_q($db_conn,$select_count_invoice_thismonth2,[$start_date,$endDate,$Login_user_TYPEvl]);
$result_count_invoice_thismonth2=mysqli_fetch_array($fetch_count_invoice_thismonth2);

$thismonth_invoice_count = ($result_count_invoice_thismonth['numinvoicetdy'] ?? 0) + ($result_count_invoice_thismonth2['numinvcntcus'] ?? 0);

//This Month Product Qty
$select_prqty_thismonth="SELECT COALESCE(SUM(qty), 0) as total_qty FROM user_invoice_items WHERE date BETWEEN ? AND ? AND from_user_type=?";
$fetch_prqty_thismonth=db_q($db_conn,$select_prqty_thismonth,[$start_date,$endDate,$Login_user_TYPEvl]);
$result_prqty_thismonth=mysqli_fetch_array($fetch_prqty_thismonth);

$select_prqty12_thismonth="SELECT COALESCE(SUM(qty), 0) as total_qty FROM invoice_items WHERE date BETWEEN ? AND ? AND user_type=?";
$fetch_prqty12_thismonth=db_q($db_conn,$select_prqty12_thismonth,[$start_date,$endDate,$Login_user_TYPEvl]);
$result_prqty12_thismonth=mysqli_fetch_array($fetch_prqty12_thismonth);

$thismonth_total_qty = ($result_prqty_thismonth['total_qty'] ?? 0) + ($result_prqty12_thismonth['total_qty'] ?? 0);

//This Month Total Amount
$select_totalamount_thismonth="SELECT COALESCE(SUM(total), 0) as total_amount FROM user_invoice WHERE date BETWEEN ? AND ? AND from_user_type=?";
$fetch_totalamount_thismonth=db_q($db_conn,$select_totalamount_thismonth,[$start_date,$endDate,$Login_user_TYPEvl]);
$result_totalamount_thismonth=mysqli_fetch_array($fetch_totalamount_thismonth);

$select_totalamount_thismonth2="SELECT COALESCE(SUM(total), 0) as total_amount FROM invoice WHERE date BETWEEN ? AND ? AND user_type=?";
$fetch_totalamount_thismonth2=db_q($db_conn,$select_totalamount_thismonth2,[$start_date,$endDate,$Login_user_TYPEvl]);
$result_totalamount_thismonth2=mysqli_fetch_array($fetch_totalamount_thismonth2);

$thismonth_total_amountSUM = ($result_totalamount_thismonth['total_amount'] ?? 0) + ($result_totalamount_thismonth2['total_amount'] ?? 0);
$thismonth_total_amount = $thismonth_total_amountSUM > 0 ? inr_format($thismonth_total_amountSUM, 2) : "0.00";

//-------------------------------------Last Month----------------------------------
//---------------------------------------------------------------------------------
//Last Month Invoice Count - ENABLED AND OPTIMIZED
$select_count_invoice_lastmonth="SELECT COUNT(*) as numinvoicetdy FROM user_invoice WHERE date BETWEEN ? AND ? AND from_user_type=? AND sub_total>0";
$fetch_count_invoice_lastmonth=db_q($db_conn,$select_count_invoice_lastmonth,[$lastmonth_date_start,$lastmonth_date_end,$Login_user_TYPEvl]);
$result_count_invoice_lastmonth=mysqli_fetch_array($fetch_count_invoice_lastmonth);

$select_count_invoice_lastmonth2="SELECT COUNT(*) as numinvcntcus FROM invoice WHERE date BETWEEN ? AND ? AND user_type=? AND sub_total>0";
$fetch_count_invoice_lastmonth2=db_q($db_conn,$select_count_invoice_lastmonth2,[$lastmonth_date_start,$lastmonth_date_end,$Login_user_TYPEvl]);
$result_count_invoice_lastmonth2=mysqli_fetch_array($fetch_count_invoice_lastmonth2);

$lastmonth_invoice_count = ($result_count_invoice_lastmonth['numinvoicetdy'] ?? 0) + ($result_count_invoice_lastmonth2['numinvcntcus'] ?? 0);

//Last Month Product Qty
$select_prqty_lastmonth="SELECT COALESCE(SUM(qty), 0) as total_qty FROM user_invoice_items WHERE date BETWEEN ? AND ? AND from_user_type=?";
$fetch_prqty_lastmonth=db_q($db_conn,$select_prqty_lastmonth,[$lastmonth_date_start,$lastmonth_date_end,$Login_user_TYPEvl]);
$result_prqty_lastmonth=mysqli_fetch_array($fetch_prqty_lastmonth);

$select_prqty12_lastmonth="SELECT COALESCE(SUM(qty), 0) as total_qty FROM invoice_items WHERE date BETWEEN ? AND ? AND user_type=?";
$fetch_prqty12_lastmonth=db_q($db_conn,$select_prqty12_lastmonth,[$lastmonth_date_start,$lastmonth_date_end,$Login_user_TYPEvl]);
$result_prqty12_lastmonth=mysqli_fetch_array($fetch_prqty12_lastmonth);

$lastmonth_total_qty = ($result_prqty_lastmonth['total_qty'] ?? 0) + ($result_prqty12_lastmonth['total_qty'] ?? 0);

//Last Month Total Amount
$select_totalamount_lastmonth="SELECT COALESCE(SUM(total), 0) as total_amount FROM user_invoice WHERE date BETWEEN ? AND ? AND from_user_type=?";
$fetch_totalamount_lastmonth=db_q($db_conn,$select_totalamount_lastmonth,[$lastmonth_date_start,$lastmonth_date_end,$Login_user_TYPEvl]);
$result_totalamount_lastmonth=mysqli_fetch_array($fetch_totalamount_lastmonth);

$select_totalamount_lastmonth2="SELECT COALESCE(SUM(total), 0) as total_amount FROM invoice WHERE date BETWEEN ? AND ? AND user_type=?";
$fetch_totalamount_lastmonth2=db_q($db_conn,$select_totalamount_lastmonth2,[$lastmonth_date_start,$lastmonth_date_end,$Login_user_TYPEvl]);
$result_totalamount_lastmonth2=mysqli_fetch_array($fetch_totalamount_lastmonth2);

$lastmonth_total_amountSUM = ($result_totalamount_lastmonth['total_amount'] ?? 0) + ($result_totalamount_lastmonth2['total_amount'] ?? 0);
$lastmonth_total_amount = $lastmonth_total_amountSUM > 0 ? inr_format($lastmonth_total_amountSUM, 2) : "0.00";

// Optional: Debug output (add ?debug_company=1 to URL to see)
if (isset($_GET['debug_company'])) {
    echo "<div style='background: #f8f9fa; padding: 10px; margin: 10px 0; border: 1px solid #dee2e6;'>";
    echo "<strong>Company Sales Debug:</strong><br>";
    echo "Login User Type: $Login_user_TYPEvl<br>";
    echo "Today ($today_date): $today_invoice_count invoices, $today_total_qty qty, ₹$today_total_amount<br>";
    echo "Yesterday ($Yesterday_date): $yesterday_invoice_count invoices, $yesterday_total_qty qty, ₹$yesterday_total_amount<br>";
    echo "This Month ($start_date to $endDate): $thismonth_invoice_count invoices, $thismonth_total_qty qty, ₹$thismonth_total_amount<br>";
    echo "Last Month ($lastmonth_date_start to $lastmonth_date_end): $lastmonth_invoice_count invoices, $lastmonth_total_qty qty, ₹$lastmonth_total_amount<br>";
    echo "</div>";
}
?>