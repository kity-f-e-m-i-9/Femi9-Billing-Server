<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="Shop Sales";

if($_REQUEST['lable']==1 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Today";}
else if($_REQUEST['lable']==2 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Yesterday";}
else if($_REQUEST['lable']==3 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="This Month";}
else
{$DISPLAY_LABLE="Last Month till date";}

$from_date=$_REQUEST['frdate'];
$to_date=$_REQUEST['todate'];

// Set the filename for download
$file = "".$Report_LABLE."".$from_date."(to)".$to_date.".csv";

// Fetch product data from database
$select_records = "select * from user_invoice where date between '$from_date' and '$to_date' and to_user_type='shop' and sub_total>0 and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' order by id asc";
$fetch_records = mysqli_query($db_conn, $select_records);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "Inv Number,Name,Mobile,Date,Total AMount,PRODUCT QTY\n";

// Fetching data and formatting into CSV rows
while ($result_product_list = mysqli_fetch_array($fetch_records)) {
	
	
	//customer details
											$CuSTID=$result_product_list['to_user_id'];
$select_Customers="select * from shop where temp_id='$CuSTID'";
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

$TotalAmount=$result_product_list["total"];
				$TotalAmount123+=$TotalAmount;
				
				if($result_sumprqty[0]!=NULL){
				$TotalPrQty=$result_sumprqty[0];
				}else{$TotalPrQty="0";}
				$TotalPrQty123+=$TotalPrQty;
				

    // Prepare CSV row
    $csv_content .= '"' . $result_product_list["inv_number"] . '",' .
                    '"' . $Cust_Name . '",' .
                    '"' . $Cust_Mbile . '",' .
					'"' . $result_product_list["date"] . '",' .
					'"' . $TotalAmount . '",' .
                    '"' . $TotalPrQty . "\"\n";
}


										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>