<?php include("checksession.php");
$title="Receipt";
date_default_timezone_set("Asia/Kolkata");
$current_date=date("Y-m-d");
error_reporting(0);

$invid=$_REQUEST['invid'];
$getinvuser=$_REQUEST['invuser'];

if($getinvuser=="super_stockiest")
{
	$lablenamedisplay="Super Stockist Name";
	$tablename="super_stockiest";
	$backlink="user-manage-invoice?invuser=$getinvuser";
	$invtable_name="user_invoice";
	
	}
else if($getinvuser=="stockiest")
{
	$lablenamedisplay="Stockist Name";
	$tablename="stockiest";
	$backlink="user-manage-invoice?invuser=$getinvuser";
	$invtable_name="user_invoice";
	}
else if($getinvuser=="super_distributor")
{
	$lablenamedisplay="Super Distributor Name";
	$tablename="super_distributor";
	$backlink="user-manage-invoice?invuser=$getinvuser";
	$invtable_name="user_invoice";
	}
	
	else if($getinvuser=="distributor")
{
	$lablenamedisplay="Distributor Name";
	$tablename="distributor";
	$backlink="user-manage-invoice?invuser=$getinvuser";
	$invtable_name="user_invoice";
	}
	
	else if($getinvuser=="outlet")
{
	$lablenamedisplay="Outlet Name";
	$tablename="outlet";
	$backlink="user-manage-invoice?invuser=$getinvuser";
	$invtable_name="user_invoice";
	}
else if($getinvuser=="shop")
{
	$lablenamedisplay="Shop Name";
	$tablename="shop";
	$backlink="shop-user-manage-invoice";
	$invtable_name="user_invoice";
	}
	else{
	$lablenamedisplay="Customer Name";
	$tablename="customers";
	$backlink="customer-user-manage-invoice";
$invtable_name="invoice";	
	}
	
	
	//RECEIPT DELETE ACTION
    if(isset($_REQUEST['delreceiptact']))
	{
		$rcptid=base64_decode($_REQUEST['rcptid']);
		$delreceipt="delete from receipt where id='$rcptid'";
		mysqli_query($db_conn,$delreceipt);
		
		echo "<script>window.location='add-receipt.php?invid=".$_REQUEST['invid']."&&invuser=".$_REQUEST['invuser']."&&DeletedSuccess';</script>";
		
		
	}
	
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?php echo $title;?> : <?php echo $business_name;?></title>

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
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                     <h1>
									<table class="headertble">
									<tr>
									<td><?php echo $title;?></td>
									<td><a href="<?=$backlink;?>" title="Go Back">&#9776;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						<?php if(isset($_REQUEST['ReceiptAddedSuc'])){?><div class="alert alert-success">Receipt Added Successfully.</div><?php }?>
						
						<?php if(isset($_REQUEST['DeletedSuccess'])){?><div class="alert alert-success">Receipt Deleted Successfully.</div><?php }?>
								
						<?php if(isset($_REQUEST['InvalidAmount'])){?><div class="alert alert-danger">Invalid Amount.</div><?php }?>
								
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    
                                    <div class="card-body">
									<h1>Invoice Details</h1>
									<table id="receipttble" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Invoice Number</th>
													<th><?=$lablenamedisplay;?></th>
													<th>Invoice Date</th>
													<th>Invoice Amount</th>
												</tr>
                                            </thead>
											
											<tbody>
					<?php $select_product_list="select * from ".$invtable_name." where inv_id='$invid'";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										$result_product_list=mysqli_fetch_array($fetch_product_list);
											
											//customer details
											if($getinvuser=="customer"){
												$CuSTID=$result_product_list['customer_id'];
												
$select_Customers="select * from ".$tablename." where id='$CuSTID'";
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
										$Cust_Name=$result_Customers['name'];
										$Cust_Mbile=$result_Customers['mobile'];
										
											}else{
										$CuSTID=$result_product_list['to_user_id'];
										
										$select_Customers="select * from ".$tablename." where temp_id='$CuSTID'";
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
										$Cust_Name=$result_Customers['name'];
										$Cust_Mbile=$result_Customers['mobile_number'];
										
											}
											
											$RowID_encode=base64_encode($result_product_list["id"]);
											$INVID_encode=base64_encode($result_product_list["inv_id"]);
											

?>
                                            
                                                <tr>
                                                    <td><?php echo $result_product_list["inv_number"];?></td>
													<td><?php echo $Cust_Name;?><br/>M: <?php echo $Cust_Mbile;?></td>
													<td><?php echo date("d/M/Y",strtotime($result_product_list["date"]));?></td>
				<td><?php echo inr_format($result_product_list["total"], 2);?></td>
													
                                        </tr>
										</tbody>
                                        </table>
									


<?php 
$select_picnode_list="select * from receipt where inv_id='$invid' and received>0 order by id asc";
		$fetch_picnode_list=mysqli_query($db_conn,$select_picnode_list);
		$count_receipt_details=mysqli_num_rows($fetch_picnode_list);
		if($count_receipt_details>0)
		{
		?>									
										<h1>Receipt Details</h1>
										<table id="receipttble" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Date</th>
                                                    <th>Amount</th>
													<th>Received Method</th>
													<th>Remarks</th>
													<th>Delete</th>
                                                </tr>
                                            </thead>
											 <tbody>
										<?php 
		while($result_pincode_list=mysqli_fetch_array($fetch_picnode_list))
							{
								$receiptamount=$result_pincode_list['received'];
								$receiptamount123+=$receiptamount;	
if($receiptamount>0){								
?>
                                      <tr>
									  
                        <td><?=$i=$i+1; ?></td>
						<td><?=date("d/m/Y",strtotime($result_pincode_list['date']));?></td>
						<td><?=inr_format($receiptamount, 2);?></td>
						
						<td><?=$result_pincode_list['receipt_method'];?></td>
						<td><?=$result_pincode_list['receipt_remarks'];?></td>
						
						<td><a href="add-receipt.php?invid=<?=$_REQUEST['invid'];?>&&invuser=<?=$_REQUEST['invuser'];?>&&delreceiptact&&rcptid=<?php echo base64_encode($result_pincode_list['id']);?>" onclick="return confirm('You want to delete confirm?');"><span class='badge badge-style-bordered badge-danger'>Remove</span></a></td>
                                      
                                     </tr>
									<?php }?>
									<?php }?>
									</tbody>
									
									<tfoot>
									<tr>
									<td colspan="2" style="text-align:right;font-weight:bold;">Total</td>
									<td style="font-weight:bold;"><?=inr_format($receiptamount123, 2);?></td>
									<td></td>
									<td></td>
									<td></td>
									</tr>
									</tfoot>
                                    </table>
									
		<?php }?>
									
	
<!---------------------------------INSERT RECEIPT---------------------------------------->
<!--------------------------------------------------------------------------------------->
	<?php
									
		//total received Amount
		$selectsumreceiptamont="select sum(received) from receipt where inv_id='$invid'";
		$fetchsumreceiptamont=mysqli_query($db_conn,$selectsumreceiptamont);
		$resultsumreceiptamont=mysqli_fetch_array($fetchsumreceiptamont);
									$TotalReceivedAmount=$resultsumreceiptamont[0];
									$balanceAmount=$result_product_list["total"]-$TotalReceivedAmount;
									
									
									if(isset($_REQUEST['addreceipt']))
									{
										
										$receiptid=$_REQUEST['receiptid'];
										$invid=$_REQUEST['invid'];
										
										$select_invoice_Dtils22="select * from user_invoice where inv_id='$invid'";
										$FETCH_invoice_Dtils22=mysqli_query($db_conn,$select_invoice_Dtils22);
										$Result_invoice_Dtils22=mysqli_fetch_array($FETCH_invoice_Dtils22);
										
										$from_user_type=$Result_invoice_Dtils22['from_user_type'];
										$from_user_id=$Result_invoice_Dtils22['from_user_id'];
										$to_user_type=$Result_invoice_Dtils22['to_user_type'];
										$to_user_id=$Result_invoice_Dtils22['to_user_id'];
										
										$invuser=$_REQUEST['invuser'];
										$receivableamount=$_REQUEST['receivableamount'];
										$receivedamount=$_REQUEST['receivedamount'];
										$balanceamountvl=$receivableamount-$receivedamount;
										$receiptdate=$_REQUEST['date'];
										
										if($receivedamount<=$receivableamount)
										{
										
										//insert receipt
	$insertreceiptcount="select count(*) as numreceipt from receipt where receiptid='$receiptid'";
	$fetchreceipt=mysqli_query($db_conn,$insertreceiptcount);
	$resultreceipt=mysqli_fetch_array($fetchreceipt);
	if($resultreceipt['numreceipt']==0)
	{
		
		$receipt_method=$_REQUEST['receipt_method'];
		$receipt_remarks=str_replace("'","&#39;",$_REQUEST['receipt_remarks']);
		
		$insertreceipt="insert into receipt (receiptid,inv_id,invoice_amount,received,receivable,date,
		from_user_type,from_user_id,to_user_type,to_user_id,receipt_method,receipt_remarks) 
		values 
		('$receiptid','$invid','$receivableamount','$receivedamount','$balanceamountvl','$receiptdate',
		'$from_user_type','$from_user_id','$to_user_type','$to_user_id','$receipt_method','$receipt_remarks')";
		mysqli_query($db_conn,$insertreceipt);
	}
	
	echo "<script>window.location='add-receipt.php?invid=$invid&&invuser=$invuser&&ReceiptAddedSuc';</script>";
	
										}else{
	echo "<script>window.location='add-receipt.php?invid=$invid&&invuser=$invuser&&InvalidAmount';</script>";		
										}
	
									}
									
									
									
									if($balanceAmount>0)
									{
									?>
										
				<form action="<?=$_SERVER['PHP_SELF'];?>" method="post" enctype="multipart/form-data" onsubmit="return confirm('Please make a confirm!')">
				<?php function GeraHash($qtd){ $Caracteres = '123456789ABDEFGHJKMNPQRS'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } ; 
$inv_randum_number=GeraHash(10);
$randum_number=GeraHash(5);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$receiptid="".$inv_randum_number."/RCPT/".$temp_date."/".$temp_time."";?>

				<input type="hidden" name="receiptid" value="<?=$receiptid;?>"/>
				<input type="hidden" name="invid" value="<?=$_REQUEST['invid'];?>"/>
				<input type="hidden" name="invuser" value="<?=$_REQUEST['invuser'];?>"/>
				<input type="hidden" name="receivableamount" value="<?=$balanceAmount;?>"/>
				<input type="hidden" name="date" value="<?=date("Y-m-d");?>"/>

                <div class="example-container">
                <div class="example-content">
							   
						<label for="exampleInputEmail1" class="form-label">Date</label>
						<input type="date" value="<?=date("Y-m-d");?>" disabled required="" class="form-control">
							   
							    <script>
  function receiptamount(){
   var receivable = document.getElementById('receivable').value;
   var received = document.getElementById('received').value;
   document.getElementById('balanceamount').value = (receivable*1)-(received*1); 
 }
</script>


						<label for="exampleInputEmail1" class="form-label">Balance Amount</label>
						<input type="number" required="" id="receivable" value="<?=$balanceAmount;?>" disabled class="form-control">
						
						<label for="exampleInputEmail1" class="form-label">Received Amount</label>
						<input type="number" onkeyup="receiptamount()" id="received" name="receivedamount" required="" min="0" max="<?=$balanceAmount;?>" class="form-control">
						
						<label for="exampleInputEmail1" class="form-label">Received Method</label>
						<select name="receipt_method" required class="form-control">
		 <option value="" hidden="">Select</option>
		 <option>Cash</option>
		 <option>UPI</option>
		 <option>Bank Transfer</option>
		 <option>Deposit</option>
		 </select>
		 
		 <label for="exampleInputEmail1" class="form-label">Remarks</label>
		  <textarea name="receipt_remarks" required class="form-control"></textarea>
		  
						<label for="exampleInputEmail1" class="form-label">Receivable Amount</label>
						<input type="number" disabled id="balanceamount" min="0" required="" class="form-control">
						<br/>
												
				<button type="submit" name="addreceipt" class="btn btn-primary">
				<i class="material-icons">add</i>Submit</button>
												
                                            </div>
                                        </div>
										</form>
										
									<?php }?>
									
<!---------------------------------------------------------------------------------------->
<!---------------------------------------------------------------------------------------->	
									
                                    </div>
                                </div>
                            </div>
								
                            </div>
                        </div>
                    </div>
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