<?php include("checksession.php"); date_default_timezone_set("Asia/Kolkata"); error_reporting(0);
include("config.php");
$Invoice_ID=$_REQUEST['invoiceid'];
$Invoice_ID=base64_decode($Invoice_ID);
//
$select_Invoice_Details="select * from user_invoice where inv_id='$Invoice_ID'";
$fetch_Invoice_Details=mysqli_query($db_conn,$select_Invoice_Details);
$result_Invoice_Details=mysqli_fetch_array($fetch_Invoice_Details);

//customer details
$getinvuser=$result_Invoice_Details['to_user_type'];

if($getinvuser=="distributor")
{	
	$tablename="distributor";
}
else
{	
	//$tablename="shop";	
}
	
	
$customer_id=$result_Invoice_Details['to_user_id'];
$select_Cusotmer_Details="select * from ".$tablename." where temp_id='$customer_id'";
$fetch_Customer_Details=mysqli_query($db_conn,$select_Cusotmer_Details);
$result_Customer_Details=mysqli_fetch_array($fetch_Customer_Details);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags 
    <!-- Title -->
    <title>Invoice : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">


    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        <div class="app-container">
          <?php include("app-header.php");?>
            <div class="app-content">
			
			<script type="text/javascript">     
           function PrintDiv() {    
           var divToPrint = document.getElementById('divToPrint');
           var popupWin = window.open('', '_blank', 'width=990,height=540,left=200,top=80');
           popupWin.document.open();
           popupWin.document.write('<html><body onload="window.print()">' + divToPrint.innerHTML + '</html>');
           popupWin.document.close();}
</script>

			<table align="right">
			<tr>
			<td><button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button></td>
			<td><button type="button" onClick="javascript:window.location='user-invoice-add.php?invuser=<?=$getinvuser;?>';" class="btn btn-success m-b-xs m-r-xs">+ New Invoice</button></td>
			<td><button type="button" onClick="javascript:window.location='user-manage-invoice.php?invuser=<?=$getinvuser;?>';" class="btn btn-primary m-b-xs m-r-xs">Manage Invoice</button></td>
			</tr>
			</table>
			<div style="clear:both;"></div>
			
			<div id="divToPrint"><!--Print content start-->
			
			<style>
			.invoice-box {
				max-width: 100%;
				margin: auto;
				padding: 30px;
				border: 1px solid #000;
				box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
				font-size: 16px;
				line-height: 24px;
				font-family: 'Poppins', sans-serif;
				color: #000;
			}
			.invoice-box h1{text-align:center;}

			.invoice-box table {
				width: 100%;
				line-height: inherit;
				text-align: left;
				border-collapse: collapse;
			}

			.invoice-box table td {
				padding: 5px;
				vertical-align: top;
			}

			.invoice-box table tr td:nth-child(2) {
				text-align: right;
			}

			.invoice-box table tr.top table td {
				padding-bottom: 20px;
			}

			.invoice-box table tr.top table td.title {
				font-size: 45px;
				line-height: 45px;
				color: #333;
			}

			.invoice-box table tr.information table td {
				padding-bottom: 40px;
			}

			.invoice-box table tr.heading td {
				background: #eee;
				border-bottom: 1px solid #999;
				font-weight: bold;
			}

			.invoice-box table tr.item td {
				border-bottom: 1px solid #999;
			}
			
			.invoice-box table tr.topborder td {
				border-top:0px;
				font-weight: bold;
			}
			#footer{font-size:15px;text-align:center;}

			@media only screen and (max-width: 600px) {
				.invoice-box table tr.top table td {
					width: 100%;
					display: block;
					text-align: center;
				}

				.invoice-box table tr.information table td {
					width: 100%;
					display: block;
					text-align: center;
				}
			}
			
		</style>
		
		<div class="invoice-box">
		<h1>Invoice</h1>
		<hr/>
			<table>
				<tr class="top">
					<td colspan="4">
						<table>
							<tr>
								<td class="title">
									<img src="<?php echo $invoice_logo;?>" alt="<?php echo $invoice_logo_alt;?>" style="<?php echo $invoice_logo_style;?>" />
								</td>

								<td>
									<b>Invoice # :</b> <?php echo $result_Invoice_Details['inv_number'];?><br />
									<b>Date:</b> <?php echo date("d/M/Y",strtotime($result_Invoice_Details['date']));?>
								</td>
							</tr>
						</table>
					</td>
				</tr>

				<tr class="information">
					<td colspan="4">
						<table>
							<tr>
								<td>
									<?=$invoice_from_line1;?><br />
									<?=$invoice_from_line2;?><br />
									<?=$invoice_from_line3;?>
								</td>

								<td>
								<?php echo ucwords($result_Customer_Details['name']);?><br />
									<?=$result_Customer_Details['mobile'];?><br />
									<?php echo ucwords($result_Customer_Details['address']);?>
								</td>
							</tr>
						</table>
					</td>
				</tr>


				<tr class="heading">
					<td>Product Description</td>
					<td align="right">MRP</td>
					<td align="right">Qty</td>
					<td align="right">Total</td>
				</tr>

<?php
	$select_INVProductDetails="select * from user_invoice_items where inv_id='$Invoice_ID' order by id desc";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	while($result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails))
	{
	
	//product dteails
		$InV_Product_ID=$result_INVProductDetails['pr_id'];
		$select_ProductDetails123="select * from products where id='$InV_Product_ID'";
		$fetch_ProductDetails123=mysqli_query($db_conn,$select_ProductDetails123);
		$result_ProductDetails123=mysqli_fetch_array($fetch_ProductDetails123);
		
		$TotalAMount=$result_INVProductDetails['total'];
		$TotalAMount123+=$TotalAMount;
		
		$ItemRowid=base64_encode($result_INVProductDetails['id']);
	?>
				<tr class="item">
					<td><?=$result_ProductDetails123['productName'];?></td>
					<td align="right">&#8377;<?php echo inr_format($result_INVProductDetails['amount'], 2);?></td>
<td align="right"><?=$result_INVProductDetails['qty'];?></td>
<td align="right">&#8377;<?php echo inr_format($TotalAMount, 2);?></td>
				</tr>

	<?php }?>
	
	
	<tr class="topborder">
					<td align="right" colspan="3">Sub Total</td>
					<td align="right">&#8377;<?php echo inr_format($TotalAMount123, 2);?></td>
				</tr>
				<tr class="topborder">
					<td align="right" colspan="3">GST(0%)</td>
					<td align="right">&#8377;0.00</td>
				</tr>
			
				<tr class="topborder">
					<td align="right" colspan="3">Discount</td>
					<td align="right">&#8377;<?php echo inr_format($result_Invoice_Details['discount'], 2);?></td>
				</tr>
				<tr class="topborder">
					<td align="right" colspan="3">Total</td>
					<td align="right">&#8377;<?php echo inr_format($result_Invoice_Details['total'], 2);?></td>
				</tr>
				
			</table>
			
			<hr/>
		<div id="footer">Thank you for business !</div>
		
		</div>
		
		</div><!----------------PRINT DIV END-------------->
				
                </div>
            </div>
        </div>
    </div>

    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>

</html>