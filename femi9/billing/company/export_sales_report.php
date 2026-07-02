<?php
ob_start();
include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
error_reporting(0);

// Get parameters
$from_date = $_REQUEST['frdate'];
$to_date = $_REQUEST['todate'];
$selected_category = $_REQUEST['catname'];

// Build category filter
$category_filter = "";
if(!empty($selected_category) && $selected_category != 'customer') {
    $category_filter = " AND to_user_type='$selected_category'";
}

ob_end_clean();

// Set headers for CSV download
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=Sales_Report_".date('Y-m-d').".csv");
header("Pragma: no-cache");
header("Expires: 0");

// Open output stream
$output = fopen('php://output', 'w');

// Write UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write title rows
fputcsv($output, array('Sales From Company'));
fputcsv($output, array('Date: '.date("d/m/Y",strtotime($from_date)).' to '.date("d/m/Y",strtotime($to_date))));
fputcsv($output, array('')); // Empty row

// Build header array
$header = array('S.No', 'Company Profile', 'Inv Number', 'User Type', 'Name', 'Mobile', 'Category', 'District', 'Taluk', 'Date', 'Total Amount');

// Add product names to header
$select_prdetails_header = "select * from `products` order by `id` asc";
$fetch_prdetails_header = mysqli_query($db_conn, $select_prdetails_header);
$products = array();
while($result_prdetails_header = mysqli_fetch_array($fetch_prdetails_header)) {
    $header[] = $result_prdetails_header['productName'];
    $products[] = $result_prdetails_header['id'];
}

fputcsv($output, $header);

// Initialize
$i = 0;
$grand_total = 0;

// USER INVOICES
if($selected_category != 'customer') {
    $select_product_list = "select * from user_invoice 
                            where date between '$from_date' and '$to_date' 
                            and from_user_type='$Login_user_TYPEvl' 
                            and sub_total>0 
                            $category_filter 
                            order by id asc";
    $fetch_product_list = mysqli_query($db_conn, $select_product_list);
    
    while($result_product_list = mysqli_fetch_array($fetch_product_list)) {
        $tousertype = $result_product_list['to_user_type'];
        
        if($tousertype=="super_stockiest"){$tablename="super_stockiest"; $userTypee="Super Stockist";}
        else if($tousertype=="stockiest"){$tablename="stockiest"; $userTypee="Stockist";}
        else if($tousertype=="super_distributor"){$tablename="super_distributor"; $userTypee="Super Distributor";}
        else if($tousertype=="distributor"){$tablename="distributor"; $userTypee="Distributor";}
        else if($tousertype=="outlet"){$tablename="outlet"; $userTypee="Outlet";}
        else{$tablename="shop"; $userTypee="Shop";}
        
        $CuSTID = $result_product_list['to_user_id'];
        $select_Customers = "select * from ".$tablename." where temp_id='$CuSTID'";
        $fetch_Customers = mysqli_query($db_conn, $select_Customers);
        $result_Customers = mysqli_fetch_array($fetch_Customers);
        $Cust_Name = $result_Customers['name'];
        $Cust_Mbile = $result_Customers['mobile_number'];
        
        // Fetch Category (only available for stockist)
        $category_name = "-";
        if($tousertype == 'stockiest') {
            $stockist_temp_id = $result_Customers['temp_id'];
            $select_category = "select st_cat_id from stockist_referral where stockist_id='$stockist_temp_id'";
            $fetch_category = mysqli_query($db_conn, $select_category);
            $result_category = mysqli_fetch_array($fetch_category);
            
            if($result_category['st_cat_id']) {
                $cat_id = $result_category['st_cat_id'];
                $select_cat_name = "select catname from stockist_category where id='$cat_id'";
                $fetch_cat_name = mysqli_query($db_conn, $select_cat_name);
                $result_cat_name = mysqli_fetch_array($fetch_cat_name);
                $category_name = $result_cat_name['catname'] ? $result_cat_name['catname'] : "-";
            }
        }
        
        // Fetch District
        $district_name = "-";
        if($result_Customers['district_id']) {
            $district_id = $result_Customers['district_id'];
            $select_district = "select dist_name from district where id='$district_id'";
            $fetch_district = mysqli_query($db_conn, $select_district);
            $result_district = mysqli_fetch_array($fetch_district);
            $district_name = $result_district['dist_name'] ? $result_district['dist_name'] : "-";
        }
        
        // Fetch Taluk
        $taluk_name = "-";
        if($result_Customers['taluk_id']) {
            $taluk_id = $result_Customers['taluk_id'];
            if(is_numeric($taluk_id)) {
                $select_taluk = "select taluk from taluk where id='$taluk_id'";
                $fetch_taluk = mysqli_query($db_conn, $select_taluk);
                $result_taluk = mysqli_fetch_array($fetch_taluk);
                $taluk_name = $result_taluk['taluk'] ? $result_taluk['taluk'] : "-";
            } else {
                $taluk_name = $taluk_id;
            }
        }
        
        $company_id = $result_product_list['from_user_id'];
        $select_cmpdetails = "select * from company_godown where id='$company_id' AND " . godown_finance_filter_sql($db_conn);
        $fetch_cmpdetails = mysqli_query($db_conn, $select_cmpdetails);
        $result_cmpdetails = mysqli_fetch_array($fetch_cmpdetails);
        $cmpname = $result_cmpdetails['gname'];
        
        $inv_id = $result_product_list['inv_id'];
        $TotalAmount = $result_product_list["total"];
        $grand_total += $TotalAmount;
        
        // Build row data
        $row = array(
            ++$i,
            $cmpname,
            $result_product_list["inv_number"],
            $userTypee,
            $Cust_Name,
            $Cust_Mbile,
            $category_name,
            $district_name,
            $taluk_name,
            date("d/M/Y",strtotime($result_product_list["date"])),
            number_format($TotalAmount,2,'.','')
        );
        
        // Add product quantities
        foreach($products as $prid_header) {
            $select_sumprqty = "select qty from user_invoice_items where inv_id='$inv_id' and pr_id='$prid_header'";
            $fetch_sumprqty = mysqli_query($db_conn, $select_sumprqty);
            $result_sumprqty = mysqli_fetch_array($fetch_sumprqty);
            $row[] = ($result_sumprqty['qty']!=NULL) ? $result_sumprqty['qty'] : "0";
        }
        
        fputcsv($output, $row);
    }
}

// CUSTOMER INVOICES
if(empty($selected_category) || $selected_category == 'customer') {
    $select_product_listcUSTOMER = "select * from invoice 
                                    where date between '$from_date' and '$to_date' 
                                    and user_type='$Login_user_TYPEvl' 
                                    and sub_total>0 
                                    order by id asc";
    $fetch_product_listCUTOMER = mysqli_query($db_conn, $select_product_listcUSTOMER);
    
    while($result_product_listCUSTOMER = mysqli_fetch_array($fetch_product_listCUTOMER)) {
        $company_id2 = $result_product_listCUSTOMER['user_id'];
        $select_cmpdetails2 = "select * from company_godown where id='$company_id2' AND " . godown_finance_filter_sql($db_conn);
        $fetch_cmpdetails2 = mysqli_query($db_conn, $select_cmpdetails2);
        $result_cmpdetails2 = mysqli_fetch_array($fetch_cmpdetails2);
        $cmpname2 = $result_cmpdetails2['gname'];
        
        $CuSTID = $result_product_listCUSTOMER['customer_id'];
        if($CuSTID == 0) {
            $Cust_Name123 = "Walking Customer";
            $Cust_Mbile123 = "";
        } else {
            $select_Customers = "select * from customers where id='$CuSTID'";
            $fetch_Customers = mysqli_query($db_conn, $select_Customers);
            $result_Customers = mysqli_fetch_array($fetch_Customers);
            $Cust_Name123 = $result_Customers['name'];
            $Cust_Mbile123 = $result_Customers['mobile'];
        }
        
        $inv_id_cus = $result_product_listCUSTOMER['inv_id'];
        $TotalAmountCUS = $result_product_listCUSTOMER["total"];
        $grand_total += $TotalAmountCUS;
        
        // Build row data - customers don't have category/district/taluk
        $row = array(
            ++$i,
            $cmpname2,
            $result_product_listCUSTOMER["inv_number"],
            'Customer',
            $Cust_Name123,
            $Cust_Mbile123,
            '-',
            '-',
            '-',
            date("d/M/Y",strtotime($result_product_listCUSTOMER["date"])),
            number_format($TotalAmountCUS,2,'.','')
        );
        
        // Add product quantities
        foreach($products as $prid_header_cus) {
            $select_sumprqty12 = "select qty from invoice_items where inv_id='$inv_id_cus' and pr_id='$prid_header_cus'";
            $fetch_sumprqty12 = mysqli_query($db_conn, $select_sumprqty12);
            $result_sumprqty12 = mysqli_fetch_array($fetch_sumprqty12);
            $row[] = ($result_sumprqty12['qty']!=NULL) ? $result_sumprqty12['qty'] : "0";
        }
        
        fputcsv($output, $row);
    }
}

// Write empty row
fputcsv($output, array(''));

// Write GRAND TOTAL row
$total_row = array_fill(0, 10, '');
$total_row[9] = 'GRAND TOTAL:';
$total_row[10] = number_format($grand_total,2,'.','');

// Add empty cells for products
foreach($products as $p) {
    $total_row[] = '';
}

fputcsv($output, $total_row);

fclose($output);
exit;
?>