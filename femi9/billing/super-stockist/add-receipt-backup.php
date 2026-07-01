<?php 
include("checksession.php");
$title="Receipt";
date_default_timezone_set("Asia/Kolkata");
$current_date=date("Y-m-d");
error_reporting(0);
require_once("advance-payment-functions.php");

// Receipt ID generation function (used by both invoice and courier forms)
function GeraHash($qtd){ 
    $Caracteres = '123456789ABDEFGHJKMNPQRS'; 
    $QuantidadeCaracteres = strlen($Caracteres); 
    $QuantidadeCaracteres--; 
    $Hash=NULL; 
    for($x=1;$x<=$qtd;$x++){ 
        $Posicao = rand(0,$QuantidadeCaracteres); 
        $Hash .= substr($Caracteres,$Posicao,1); 
    } 
    return $Hash; 
}


$invid=$_REQUEST['invid'];
$getinvuser=$_REQUEST['invuser'];

if($getinvuser=="candf")
{
	$lablenamedisplay="C&F Name";
	$tablename="c_and_f";
	$backlink="user-manage-invoice?invuser=$getinvuser";
	$invtable_name="user_invoice";
}
else if($getinvuser=="super_stockiest")
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

// Check if this user type requires advance payment
$is_advance_mandatory = isAdvancePaymentMandatory($getinvuser);

// Get invoice details first
$select_product_list="select * from ".$invtable_name." where inv_id='$invid'";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
$result_product_list=mysqli_fetch_array($fetch_product_list);

// Get courier charges and calculate amounts
$courier_charges = floatval($result_product_list['courier_charges'] ?? 0);
$invoice_total = floatval($result_product_list['total']);
$invoice_amount_only = $invoice_total - $courier_charges; // Product amount

//RECEIPT DELETE ACTION
if(isset($_REQUEST['delreceiptact']))
{
	$rcptid=base64_decode($_REQUEST['rcptid']);
	
	// Get receipt details before deleting
	$stmt = $db_conn->prepare("SELECT * FROM receipt WHERE id = ?");
	$stmt->bind_param("i", $rcptid);
	$stmt->execute();
	$receipt_to_delete = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	
	if ($receipt_to_delete) {
		$receipt_method = $receipt_to_delete['receipt_method'];
		$receipt_amount = floatval($receipt_to_delete['received']);
		
		// If this was an advance payment receipt, restore the balance
		if ($receipt_method === "Advance Payment" && $is_advance_mandatory) {
			error_log("Deleting advance payment receipt - restoring balance");
			
			// Get invoice details for restoration
			$invoice_number = $result_product_list['inv_number'];
			$company_id = $result_product_list['from_user_id'];
			$company_type = $result_product_list['from_user_type'];
			
			// Restore the advance payment balance
			$restoreResult = restoreAdvancePaymentOnInvoiceEdit(
				$db_conn,
				$invid,
				$invoice_number,
				date('Y-m-d'),
				"Receipt deleted - restoring advance payment balance",
				$company_id,
				$company_type
			);
			
			if ($restoreResult['success']) {
				error_log("SUCCESS: Restored Rs." . number_format($receipt_amount, 2) . " to advance balance");
			} else {
				error_log("ERROR: Failed to restore balance: " . $restoreResult['message']);
			}
		}
		
		// Delete the receipt
		$delreceipt="delete from receipt where id='$rcptid'";
		mysqli_query($db_conn,$delreceipt);
	}
	
	echo "<script>window.location='add-receipt.php?invid=".$_REQUEST['invid']."&&invuser=".$_REQUEST['invuser']."&&DeletedSuccess';</script>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <title><?php echo $title;?> : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons_Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <style>
    .alert {
        border-radius: 10px;
        border: none;
        padding: 15px 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border-left: 4px solid #10b981;
    }
    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
        border-left: 4px solid #ef4444;
    }
    .alert-info {
        background: #dbeafe;
        color: #1e40af;
        border-left: 4px solid #3b82f6;
    }
    .alert-warning {
        background: #fef3c7;
        color: #92400e;
        border-left: 4px solid #f59e0b;
    }
    .card {
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .card-body {
        padding: 25px;
    }
    table#receipttble {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }
    table#receipttble th {
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        padding: 12px;
        border-bottom: 2px solid #e5e7eb;
    }
    table#receipttble td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
    }
    table#receipttble tbody tr:hover {
        background: #f8fafc;
    }
    .form-label {
        font-weight: 500;
        color: #374151;
        margin-bottom: 8px;
        font-size: 14px;
    }
    .form-control {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 14px;
    }
    .form-control:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    .btn-primary {
        background: #2563eb;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .btn-primary:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }
    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
    }
    .badge-danger:hover {
        background: #fecaca;
    }
    .badge-primary {
        background: #dbeafe;
        color: #1e40af;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
    }
    .badge-warning {
        background: #fef3c7;
        color: #92400e;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
    }
    .badge-info {
        background: #e0e7ff;
        color: #3730a3;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
    }
    </style>
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
						
                        <?php if(isset($_REQUEST['ReceiptAddedSuc'])){?>
                            <div class="alert alert-success">
                                <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">check_circle</i>
                                Receipt Added Successfully.
                            </div>
                        <?php }?>
						
                        <?php if(isset($_REQUEST['DeletedSuccess'])){?>
                            <div class="alert alert-success">
                                <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">delete</i>
                                Receipt Deleted Successfully. Balance has been restored.
                            </div>
                        <?php }?>
								
                        <?php if(isset($_REQUEST['InvalidAmount'])){?>
                            <div class="alert alert-danger">
                                <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">error</i>
                                Invalid Amount.
                            </div>
                        <?php }?>
                        
                        <?php if(isset($_REQUEST['InsufficientBalance'])){?>
                            <div class="alert alert-danger">
                                <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">account_balance_wallet</i>
                                Insufficient advance balance. Please add more advance payment first.
                            </div>
                        <?php }?>
								
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
                                                    <th>Courier Charges</th>
                                                    <th>Total Amount</th>
                                                </tr>
                                            </thead>
											
                                            <tbody>
                                                <?php 
                                                //customer details
                                                if($getinvuser=="customer"){
                                                    $CuSTID=$result_product_list['customer_id'];
                                                    $select_Customers="select * from ".$tablename." where id='$CuSTID'";
                                                    $fetch_Customers=mysqli_query($db_conn,$select_Customers);
                                                    $result_Customers=mysqli_fetch_array($fetch_Customers);
                                                    $Cust_Name=$result_Customers['name'];
                                                    $Cust_Mbile=$result_Customers['mobile'];
                                                } else {
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
                                                    <td>₹<?php echo number_format($invoice_amount_only, 2, '.', '');?></td>
                                                    <td>₹<?php echo number_format($courier_charges, 2, '.', '');?></td>
                                                    <td><strong>₹<?php echo number_format($invoice_total, 2, '.', '');?></strong></td>
                                                </tr>
                                            </tbody>
                                        </table>
										
                                        <?php 
                                        // Get all receipts with receipt type categorization
                                        $select_picnode_list="select * from receipt where inv_id='$invid' and received>0 order by id asc";
                                        $fetch_picnode_list=mysqli_query($db_conn,$select_picnode_list);
                                        $count_receipt_details=mysqli_num_rows($fetch_picnode_list);
                                        
                                        // Calculate received amounts by type
                                        $invoice_amount_received = 0;
                                        $courier_amount_received = 0;
                                        $total_received = 0;
                                        
                                        if($count_receipt_details>0) {
                                            mysqli_data_seek($fetch_picnode_list, 0);
                                            $running_allocated = 0;
                                            
                                            while($temp_receipt=mysqli_fetch_array($fetch_picnode_list)) {
                                                $amount = floatval($temp_receipt['received']);
                                                $total_received += $amount;
                                                
                                                if($is_advance_mandatory) {
                                                    // ✅ Advance mandatory (stockist/super_stockist): Check method
                                                    if($temp_receipt['receipt_method'] === "Advance Payment") {
                                                        $invoice_amount_received += $amount;
                                                    } else {
                                                        $courier_amount_received += $amount;
                                                    }
                                                } else {
                                                    // ✅ NON-advance users: FIFO allocation
                                                    // First allocate to invoice amount, then to courier
                                                    $remaining_invoice = $invoice_amount_only - $invoice_amount_received;
                                                    
                                                    if ($remaining_invoice > 0) {
                                                        // Still need to pay invoice amount
                                                        $apply_to_invoice = min($amount, $remaining_invoice);
                                                        $invoice_amount_received += $apply_to_invoice;
                                                        $courier_amount_received += ($amount - $apply_to_invoice);
                                                    } else {
                                                        // Invoice fully paid, this goes to courier
                                                        $courier_amount_received += $amount;
                                                    }
                                                }
                                            }
                                        }
                                        
                                        // Calculate pending amounts
                                        $invoice_amount_pending = $invoice_amount_only - $invoice_amount_received;
                                        $courier_amount_pending = $courier_charges - $courier_amount_received;
                                        
                                        if($count_receipt_details>0)
                                        {
                                            mysqli_data_seek($fetch_picnode_list, 0); // Reset pointer again
                                        ?>									
                                        <h1>Receipt Details</h1>
                                        <table id="receipttble" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Amount</th>
                                                    <th>Received Method</th>
                                                    <th>Remarks</th>
                                                    <th>Delete</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $i = 0;
                                                $running_total = 0; // ✅ Initialize running total for non-advance display
                                                while($result_pincode_list=mysqli_fetch_array($fetch_picnode_list))
                                                {
                                                    $receiptamount=$result_pincode_list['received'];
                                                    if($receiptamount>0){	
                                                        // ✅ Determine receipt type
                                                        if($is_advance_mandatory) {
                                                            // Advance mandatory: method-based
                                                            $receipt_type = ($result_pincode_list['receipt_method'] === "Advance Payment") 
                                                                ? "Invoice Amount" 
                                                                : "Courier Charges";
                                                            $badge_class = ($result_pincode_list['receipt_method'] === "Advance Payment") 
                                                                ? "badge-primary" 
                                                                : "badge-warning";
                                                        } else {
                                                            // ✅ NON-advance: FIFO allocation for display
                                                            $running_total += $receiptamount;
                                                            
                                                            if ($running_total <= $invoice_amount_only) {
                                                                // Entire payment goes to invoice
                                                                $receipt_type = "Invoice Amount";
                                                                $badge_class = "badge-primary";
                                                            } else if (($running_total - $receiptamount) < $invoice_amount_only) {
                                                                // Payment spans both invoice and courier
                                                                $receipt_type = "Invoice+Courier";
                                                                $badge_class = "badge-info";
                                                            } else {
                                                                // Payment goes to courier only
                                                                $receipt_type = "Courier Charges";
                                                                $badge_class = "badge-warning";
                                                            }
                                                        }
                                                ?>
                                                <tr>
                                                    <td><?=$i=$i+1; ?></td>
                                                    <td><?=date("d/m/Y",strtotime($result_pincode_list['date']));?></td>
                                                    <td><span class="<?=$badge_class?>"><?=$receipt_type?></span></td>
                                                    <td>₹<?=number_format($receiptamount,2,'.','');?></td>
                                                    <td><?=$result_pincode_list['receipt_method'];?></td>
                                                    <td><?=$result_pincode_list['receipt_remarks'];?></td>
                                                    <td>
                                                        <a href="add-receipt.php?invid=<?=$_REQUEST['invid'];?>&&invuser=<?=$_REQUEST['invuser'];?>&&delreceiptact&&rcptid=<?php echo base64_encode($result_pincode_list['id']);?>" 
                                                           onclick="return confirm('Delete this receipt? <?php if($result_pincode_list['receipt_method'] === 'Advance Payment') echo 'The advance balance will be restored.'; ?>');">
                                                            <span class='badge badge-danger'>
                                                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">delete</i> Remove
                                                            </span>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php 
                                                    }
                                                }
                                                ?>
                                            </tbody>
									
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3" style="text-align:right;font-weight:bold;">Total Received</td>
                                                    <td style="font-weight:bold;">₹<?=number_format($total_received,2,'.','');?></td>
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
                                        // Determine what needs to be paid
                                        $needs_invoice_payment = ($invoice_amount_pending > 0.01);
                                        $needs_courier_payment = ($courier_amount_pending > 0.01);
                                        
                                        // HANDLE RECEIPT SUBMISSION
                                        if(isset($_REQUEST['addreceipt']))
                                        {
                                            $receiptid=$_REQUEST['receiptid'];
                                            $invid=$_REQUEST['invid'];
                                            $invuser=$_REQUEST['invuser'];
                                            $payment_type=$_REQUEST['payment_type']; // 'invoice' or 'courier'
                                            $receivableamount=$_REQUEST['receivableamount'];
                                            $receivedamount=$_REQUEST['receivedamount'];
                                            $balanceamountvl=$receivableamount-$receivedamount;
                                            $receiptdate=date("Y-m-d");
                                            
                                            if($receivedamount<=$receivableamount && $receivedamount>0)
                                            {
                                                // Check for duplicate
                                                $insertreceiptcount="select count(*) as numreceipt from receipt where receiptid='$receiptid'";
                                                $fetchreceipt=mysqli_query($db_conn,$insertreceiptcount);
                                                $resultreceipt=mysqli_fetch_array($fetchreceipt);
                                                
                                                if($resultreceipt['numreceipt']==0)
                                                {
                                                    if($payment_type === 'invoice' && $is_advance_mandatory) {
                                                        // INVOICE AMOUNT - Use advance payment
                                                        $receipt_method = "Advance Payment";
                                                        $receipt_remarks = "Invoice amount paid via advance payment re-adjustment";
                                                        
                                                        // Validate and deduct from advance
                                                        $company_id = $result_product_list['from_user_id'];
                                                        $company_type = $result_product_list['from_user_type'];
                                                        $customer_id = $result_product_list['to_user_id'];
                                                        $customer_type = $result_product_list['to_user_type'];
                                                        $invoice_number = $result_product_list['inv_number'];
                                                        
                                                        $adjustmentResult = processInvoiceAdvancePaymentDeduction(
                                                            $db_conn,
                                                            $invid,
                                                            $invoice_number,
                                                            $receivedamount,
                                                            $receiptdate,
                                                            $customer_id,
                                                            $customer_type,
                                                            $company_id,
                                                            $company_type,
                                                            $company_id,
                                                            $company_type
                                                        );
                                                        
                                                        if (!$adjustmentResult['success']) {
                                                            echo "<script>window.location='add-receipt?invid=$invid&&invuser=$invuser&&InsufficientBalance';</script>";
                                                            exit;
                                                        }
                                                    } else {
                                                        // Manual payment (courier or non-advance invoice)
                                                        $receipt_method = mysqli_real_escape_string($db_conn, $_REQUEST['receipt_method']);
                                                        $receipt_remarks = mysqli_real_escape_string($db_conn, str_replace("'","&#39;",$_REQUEST['receipt_remarks']));
                                                    }
                                                    
                                                    // Insert receipt
                                                    $insertreceipt="insert into receipt (receiptid,inv_id,invoice_amount,received,receivable,date,
                                                    from_user_type,from_user_id,to_user_type,to_user_id,receipt_method,receipt_remarks) 
                                                    values 
                                                    ('$receiptid','$invid','$receivableamount','$receivedamount','$balanceamountvl','$receiptdate',
                                                    '".$result_product_list['from_user_type']."','".$result_product_list['from_user_id']."',
                                                    '".$result_product_list['to_user_type']."','".$result_product_list['to_user_id']."',
                                                    '$receipt_method','$receipt_remarks')";
                                                    mysqli_query($db_conn,$insertreceipt);
                                                }
                                                
                                                echo "<script>window.location='add-receipt?invid=$invid&&invuser=$invuser&&ReceiptAddedSuc';</script>";
                                            } else {
                                                echo "<script>window.location='add-receipt?invid=$invid&&invuser=$invuser&&InvalidAmount';</script>";		
                                            }
                                        }
                                        
                                        // DISPLAY PAYMENT FORMS
                                        if($needs_invoice_payment || $needs_courier_payment)
                                        {
                                            // Define common variables for BOTH forms
                                            $temp_date=date("dmy");
                                            $temp_time=date("gis");
                                        ?>
                                        
                                        <?php if ($needs_invoice_payment && $is_advance_mandatory): ?>
                                        <!-- INVOICE AMOUNT PAYMENT (Advance Payment Only for Stockist) -->
                                        <div class="alert alert-warning" style="margin-top: 20px;">
                                            <i class="material-icons" style="vertical-align: middle; font-size: 20px;">warning</i>
                                            <strong>Invoice Amount Pending</strong>
                                            <br/><small>The invoice amount (₹<?=number_format($invoice_amount_pending, 2)?>) needs to be paid via advance payment.</small>
                                        </div>
                                        
                                        <form action="<?=$_SERVER['PHP_SELF'];?>" method="post" enctype="multipart/form-data" onsubmit="return confirm('Pay invoice amount from advance balance?')">
                                        
                                        <?php 
                                        $inv_randum_number=GeraHash(10);
                                        $receiptid_invoice="".$inv_randum_number."/RCPT/".$temp_date."/".$temp_time."";
                                        ?>

                                        <input type="hidden" name="receiptid" value="<?=$receiptid_invoice;?>"/>
                                        <input type="hidden" name="invid" value="<?=$_REQUEST['invid'];?>"/>
                                        <input type="hidden" name="invuser" value="<?=$_REQUEST['invuser'];?>"/>
                                        <input type="hidden" name="payment_type" value="invoice"/>
                                        <input type="hidden" name="receivableamount" value="<?=$invoice_amount_pending;?>"/>
                                        <input type="hidden" name="receivedamount" value="<?=$invoice_amount_pending;?>"/>

                                        <div class="example-container" style="background: #fef3c7; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                                            <h3 style="color: #92400e; margin-bottom: 15px;">
                                                <i class="material-icons" style="vertical-align: middle;">receipt</i>
                                                Pay Invoice Amount (Advance Payment)
                                            </h3>
                                            <div class="example-content">
                                                <p><strong>Amount to Pay:</strong> ₹<?=number_format($invoice_amount_pending, 2)?></p>
                                                <p><strong>Payment Method:</strong> Advance Payment (Auto-deducted)</p>
                                                <button type="submit" name="addreceipt" class="btn btn-primary">
                                                    <i class="material-icons">account_balance_wallet</i> Pay from Advance Balance
                                                </button>
                                            </div>
                                        </div>
                                        </form>
                                        <?php elseif($needs_invoice_payment && !$is_advance_mandatory): ?>
                                        <!-- ✅ INVOICE AMOUNT PAYMENT (Manual for Non-Advance Users) -->
                                        <div class="alert alert-info" style="margin-top: 20px;">
                                            <i class="material-icons" style="vertical-align: middle; font-size: 20px;">receipt</i>
                                            <strong>Invoice Amount Pending</strong>
                                            <br/><small>Please add receipt for invoice amount: ₹<?=number_format($invoice_amount_pending, 2)?></small>
                                        </div>
                                        
                                        <form action="<?=$_SERVER['PHP_SELF'];?>" method="post" enctype="multipart/form-data" onsubmit="return confirm('Confirm receipt submission?')">
                                        
                                        <?php 
                                        $inv_randum_number_manual=GeraHash(10);
                                        $receiptid_invoice_manual="".$inv_randum_number_manual."/RCPT/".$temp_date."/".$temp_time."I";
                                        ?>

                                        <input type="hidden" name="receiptid" value="<?=$receiptid_invoice_manual;?>"/>
                                        <input type="hidden" name="invid" value="<?=$_REQUEST['invid'];?>"/>
                                        <input type="hidden" name="invuser" value="<?=$_REQUEST['invuser'];?>"/>
                                        <input type="hidden" name="payment_type" value="invoice"/>
                                        <input type="hidden" name="receivableamount" value="<?=$invoice_amount_pending;?>"/>

                                        <div class="example-container">
                                            <h3 style="color: #1e293b; margin-bottom: 15px;">
                                                <i class="material-icons" style="vertical-align: middle;">payments</i>
                                                Add Receipt for Invoice Amount
                                            </h3>
                                            <div class="example-content">
                                                
                                                <label for="exampleInputEmail1" class="form-label">Date</label>
                                                <input type="date" value="<?=date("Y-m-d");?>" disabled required="" class="form-control">
                                                
                                                <script>
                                                function receiptamount_invoice(){
                                                    var receivable = document.getElementById('receivable_invoice').value;
                                                    var received = document.getElementById('received_invoice').value;
                                                    document.getElementById('balanceamount_invoice').value = (receivable*1)-(received*1); 
                                                }
                                                </script>

                                                <label for="exampleInputEmail1" class="form-label">Invoice Amount Pending</label>
                                                <input type="number" required="" id="receivable_invoice" value="<?=$invoice_amount_pending;?>" disabled class="form-control">
                                                
                                                <label for="exampleInputEmail1" class="form-label">Received Amount</label>
                                                <input type="number" onkeyup="receiptamount_invoice()" id="received_invoice" name="receivedamount" required="" min="0" max="<?=$invoice_amount_pending;?>" class="form-control" placeholder="Enter amount received">
                                                
                                                <label for="exampleInputEmail1" class="form-label">Received Method</label>
                                                <select name="receipt_method" required class="form-control">
                                                    <option value="" hidden="">Select</option>
                                                    <option>Cash</option>
                                                    <option>UPI</option>
                                                    <option>Bank Transfer</option>
                                                    <option>Deposit</option>
                                                </select>
                                                
                                                <label for="exampleInputEmail1" class="form-label">Remarks</label>
                                                <textarea name="receipt_remarks" required class="form-control" placeholder="Invoice payment"></textarea>
                                                
                                                <label for="exampleInputEmail1" class="form-label">Receivable Amount</label>
                                                <input type="number" readonly id="balanceamount_invoice" min="0" required="" class="form-control">
                                                <br/>
                                                
                                                <button type="submit" name="addreceipt" class="btn btn-primary">
                                                    <i class="material-icons">add</i> Submit Receipt
                                                </button>
                                            </div>
                                        </div>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($needs_courier_payment): ?>
                                        <!-- COURIER CHARGES PAYMENT (Manual for All Users) -->
                                        <?php if ($is_advance_mandatory): ?>
                                        <div class="alert alert-info" style="margin-top: 20px;">
                                            <i class="material-icons" style="vertical-align: middle; font-size: 20px;">local_shipping</i>
                                            <strong>Courier Charges Payment Required</strong>
                                            <br/><small>Courier charges (₹<?=number_format($courier_amount_pending, 2)?>) must be paid separately via cash/UPI/bank transfer.</small>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <form action="<?=$_SERVER['PHP_SELF'];?>" method="post" enctype="multipart/form-data" onsubmit="return confirm('Confirm receipt submission?')">
                                        
                                        <?php 
                                        $inv_randum_number2=GeraHash(10);
                                        $receiptid_courier="".$inv_randum_number2."/RCPT/".$temp_date."/".$temp_time."2";
                                        ?>

                                        <input type="hidden" name="receiptid" value="<?=$receiptid_courier;?>"/>
                                        <input type="hidden" name="invid" value="<?=$_REQUEST['invid'];?>"/>
                                        <input type="hidden" name="invuser" value="<?=$_REQUEST['invuser'];?>"/>
                                        <input type="hidden" name="payment_type" value="courier"/>
                                        <input type="hidden" name="receivableamount" value="<?=$courier_amount_pending;?>"/>

                                        <div class="example-container">
                                            <h3 style="color: #1e293b; margin-bottom: 15px;">
                                                <i class="material-icons" style="vertical-align: middle;">payments</i>
                                                <?php if ($is_advance_mandatory): ?>
                                                Pay Courier Charges
                                                <?php else: ?>
                                                Add Receipt Payment
                                                <?php endif; ?>
                                            </h3>
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

                                                <label for="exampleInputEmail1" class="form-label">
                                                    <?php if ($is_advance_mandatory): ?>
                                                    Courier Charge Balance
                                                    <?php else: ?>
                                                    Balance Amount
                                                    <?php endif; ?>
                                                </label>
                                                <input type="number" required="" id="receivable" value="<?=$courier_amount_pending;?>" disabled class="form-control">
                                                
                                                <label for="exampleInputEmail1" class="form-label">Received Amount</label>
                                                <input type="number" onkeyup="receiptamount()" id="received" name="receivedamount" required="" min="0" max="<?=$courier_amount_pending;?>" class="form-control" placeholder="Enter amount received">
                                                
                                                <label for="exampleInputEmail1" class="form-label">Received Method</label>
                                                <select name="receipt_method" required class="form-control">
                                                    <option value="" hidden="">Select</option>
                                                    <option>Cash</option>
                                                    <option>UPI</option>
                                                    <option>Bank Transfer</option>
                                                    <option>Deposit</option>
                                                </select>
                                                
                                                <label for="exampleInputEmail1" class="form-label">Remarks</label>
                                                <textarea name="receipt_remarks" required class="form-control" placeholder="<?php echo $is_advance_mandatory ? 'Courier charge payment' : 'Payment remarks'; ?>"></textarea>
                                                
                                                <label for="exampleInputEmail1" class="form-label">Receivable Amount</label>
                                                <input type="number" readonly id="balanceamount" min="0" required="" class="form-control">
                                                <br/>
                                                
                                                <button type="submit" name="addreceipt" class="btn btn-primary">
                                                    <i class="material-icons">add</i> Submit Receipt
                                                </button>
                                            </div>
                                        </div>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        } else {
                                            // Fully paid
                                        ?>
                                        <div class="alert alert-success" style="margin-top: 20px;">
                                            <i class="material-icons" style="vertical-align: middle; font-size: 20px;">check_circle</i>
                                            <strong>Invoice Fully Paid</strong>
                                            <br/><small>All payments including courier charges have been received.</small>
                                        </div>
                                        <?php
                                        }
                                        ?>
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