<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

if($_REQUEST['lable']==1 && $_REQUEST['rptlable']==1)
{
	$DISPLAY_LABLE="Today";
	$Report_LABLE="Sales";
}
else if($_REQUEST['lable']==2 && $_REQUEST['rptlable']==1)
{
	$DISPLAY_LABLE="Yesterday";
	$Report_LABLE="Sales";
}
else if($_REQUEST['lable']==3 && $_REQUEST['rptlable']==1)
{
	$DISPLAY_LABLE="This Month";
	$Report_LABLE="Sales";
}
else
{
	$DISPLAY_LABLE="Last Month till date";
	$Report_LABLE="Sales";
	
}

$from_date=$_REQUEST['frdate'];
$to_date=$_REQUEST['todate'];

// Set the filename for download
$file = "".$Report_LABLE."".$from_date."(to)".$to_date.".csv";

// Fetch product data from database
$select_records = "select * from user_invoice where date between '$from_date' and '$to_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and sub_total>0 order by id asc";
$fetch_records = mysqli_query($db_conn, $select_records);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "Inv Number,User Type,Name,Mobile,Date,Total Amount,Product Qty\n";

// Fetching data and formatting into CSV rows
while ($result_product_list = mysqli_fetch_array($fetch_records)) {
	
	
	$tousertype=$result_product_list['to_user_type'];
											
if($tousertype=="super_stockiest"){$tablename="super_stockiest"; $userTypee="Super Stockist";}
else if($tousertype=="stockiest"){$tablename="stockiest"; $userTypee="Stockist";}
else if($tousertype=="distributor"){$tablename="distributor"; $userTypee="Distributor";}
else if($tousertype=="outlet"){$tablename="outlet"; $userTypee="Outlet";}
else{$tablename="shop"; $userTypee="Shop";}
											
											//customer details
											$CuSTID=$result_product_list['to_user_id'];
										
$select_Customers="select * from ".$tablename." where temp_id='$CuSTID'";
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
										//
										$Cust_Name=$result_Customers['name'];
										$Cust_Mbile=$result_Customers['mobile_number'];
											
//product Qty
$inv_id=$result_product_list['inv_id'];

$select_sumprqty="select sum(qty) from user_invoice_items where inv_id='$inv_id'";
$fetch_sumprqty=mysqli_query($db_conn,$select_sumprqty);
$result_sumprqty=mysqli_fetch_array($fetch_sumprqty);

$TotalAmount=number_format($result_product_list["total"],2,'.','');
				$TotalAmount123+=$TotalAmount;
				
				if($result_sumprqty[0]!=NULL){
				$TotalPrQty=$result_sumprqty[0];
				}else{$TotalPrQty="0";}
				$TotalPrQty123+=$TotalPrQty;
				

    // Prepare CSV row
    $csv_content .= '"' . $result_product_list["inv_number"] . '",' .
                    '"' . $userTypee . '",' .
                    '"' . $Cust_Name . '",' .
                    '"' . $Cust_Mbile . '",' .
					'"' . $result_product_list["date"] . '",' .
					'"' . $TotalAmount . '",' .
                    '"' . $TotalPrQty . "\"\n";
}



$select_product_listcUSTOMER="select * from invoice where date between '$from_date' and '$to_date' and user_type='$Login_user_TYPEvl' and sub_total>0 and user_id='$Login_user_IDvl' order by id asc";
			$fetch_product_listCUTOMER=mysqli_query($db_conn,$select_product_listcUSTOMER);
			while($result_product_listCUSTOMER=mysqli_fetch_array($fetch_product_listCUTOMER))
										{
											
											//customer details
											$CuSTID=$result_product_listCUSTOMER['customer_id'];
										if($CuSTID==0)
										{
											$Cust_Name123="Walking Customer";
											
										}else{
$select_Customers="select * from customers where id='$CuSTID'";
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
										//
										$Cust_Name123=$result_Customers['name'];
										$Cust_Mbile123=$result_Customers['mobile'];
										}
											
//product Qty
$inv_id=$result_product_listCUSTOMER['inv_id'];

$select_sumprqty="select sum(qty) from invoice_items where inv_id='$inv_id'";
$fetch_sumprqty=mysqli_query($db_conn,$select_sumprqty);
$result_sumprqty=mysqli_fetch_array($fetch_sumprqty);

$TotalAmountCUS=$result_product_listCUSTOMER["total"];
				$TotalAmount123CUS+=$TotalAmountCUS;
				
				if($result_sumprqty[0]!=NULL){
				$TotalPrQtyCUS=$result_sumprqty[0];
				}else{$TotalPrQtyCUS="0";}
				$TotalPrQty123CUS+=$TotalPrQtyCUS;

 // Prepare CSV row
    $csv_content .= '"' . $result_product_listCUSTOMER["inv_number"] . '",' .
                    '"Customer",' .
                    '"' . $Cust_Name123 . '",' .
                    '"' . $Cust_Mbile123 . '",' .
					'"' . $result_product_listCUSTOMER["date"] . '",' .
					'"' . $TotalAmountCUS . '",' .
                    '"' . $TotalPrQtyCUS . "\"\n";
					

										}
										
										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>