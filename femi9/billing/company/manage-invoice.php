<?php include("checksession.php");
include("config.php");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Manage Invoice : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">


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
    <style>
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .action-link.delete:hover { background:#fef2f2;border-color:#fecaca; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }
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
								
								<?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved success.</div><?php }?>
								
								<?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! one Invoice details deleted success.</div><?php }?>
								
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Manage Invoice</td>
									<td><a href="invoice" title="Add Invoice">&#10011;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
<?php
//----Continuos Serial Number In Next Page.......................
$num_rec_per_page=30;
if (isset($_GET["page"])) { $page  = $_GET["page"]; } else { $page=1; }; 
 $start_from = ($page-1) * $num_rec_per_page; 
$i= $start_from;
//---------------------------------------------------------------
//echo ++$i; 
?>
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
									
									<style type="text/css">
									#overflowon{width:100%;overflow-x:scroll !important;height:100%;overflow-y:hidden;}
									</style>
									
									<div id="overflowon">
                                        <table id="datatable1" class="display" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Inv Number</th>
													<th>Customer</th>
													<th>Date</th>
													<th>Sub Total</th>
													<th>Discount</th>
													<th>Total</th>
													<th>Print</th>
													
                                                    <th>Actions</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $select_product_list="select * from invoice where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' order by id asc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											//customer details
											$CuSTID=$result_product_list['customer_id'];
										$select_Customers="select * from customers where id='$CuSTID'";
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
										//
										$Cust_Name=$result_Customers['name'];
										$Cust_Mbile=$result_Customers['mobile'];
											
											$RowID_encode=base64_encode($result_product_list["id"]);
											$INVID_encode=base64_encode($result_product_list["inv_id"]);
											?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo $result_product_list["inv_number"];?></td>
													<td><?php echo $Cust_Name;?>, <?php echo $Cust_Mbile;?></td>
													<td><?php echo date("d/M/Y",strtotime($result_product_list["date"]));?></td>
													
				<td><?php echo inr_format($result_product_list["sub_total"], 2);?></td>
				<td><?php echo inr_format($result_product_list["discount"], 2);?></td>
				<td><?php echo inr_format($result_product_list["total"], 2);?></td>
													
									
													
													<td style="white-space:nowrap;">
<div style="display:flex;align-items:center;gap:8px;">
<a href="invoice-print?invoiceid=<?php echo $result_product_list["inv_id"];?>" title="Print">
<img src="../../assets/images/print32.png"/></a>
<button type="button" title="Share to WhatsApp" style="background:none;border:none;padding:0;cursor:pointer;"
	data-invid="<?php echo $result_product_list["inv_id"];?>"
	data-mobile="<?php echo htmlspecialchars($Cust_Mbile ?? '');?>"
	data-invoice="<?php echo htmlspecialchars($result_product_list["inv_number"]);?>"
	onclick="shareInvoiceDirect(this)"><svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="#25D366"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.29-1.39c1.44.79 3.06 1.2 4.71 1.2h.01c5.46 0 9.9-4.45 9.9-9.9C21.91 6.45 17.5 2 12.04 2zm5.8 14.03c-.24.68-1.4 1.32-1.93 1.4-.5.08-1.13.11-1.82-.11-.42-.13-.96-.31-1.65-.61-2.9-1.25-4.79-4.17-4.94-4.36-.14-.19-1.18-1.57-1.18-3 0-1.42.75-2.12 1.02-2.41.27-.29.58-.36.78-.36.19 0 .39 0 .56.01.18.01.42-.07.66.5.24.58.83 2 .9 2.15.07.15.12.32.02.51-.1.19-.15.31-.29.48-.15.17-.31.38-.44.51-.15.15-.3.31-.13.6.17.29.76 1.25 1.63 2.02 1.12 1 2.06 1.31 2.35 1.46.29.15.46.13.63-.08.17-.21.72-.84.92-1.13.19-.29.38-.24.64-.14.26.1 1.65.78 1.94.92.29.15.48.22.55.34.07.13.07.72-.17 1.4z"/></svg></button>
</div>
													</td>
													
																										<td>
													    <div class="actions-group">
													        <a href="invoice?InvoiceID=<?php echo $INVID_encode;?>" class="action-link" title="Edit"><i class="material-icons-outlined" style="font-size:17px;color:#667eea;">edit</i></a>
													        <a href="#" class="action-link delete" title="Delete" onclick="return confirm('You want to delete confirm?');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
													    </div>
													</td>
													
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
                                        </table>
										</div><!--overflow on end***-->
										
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
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/datatables.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
    <script src="../../assets/js/whatsapp-invoice-share.js"></script>
    <script>
    // Shares an invoice straight to WhatsApp from this list — no detour through
    // the print page. This click is a real user gesture, so the whole async
    // chain below (fetch -> PDF -> alert -> WhatsApp) keeps that gesture's
    // permission to open a window, same as the print page's own button.
    function shareInvoiceDirect(btn) {
        var invId         = btn.getAttribute('data-invid');
        var mobile        = btn.getAttribute('data-mobile');
        var invoiceNumber = btn.getAttribute('data-invoice');
        var originalHtml  = btn.innerHTML;

        btn.disabled  = true;
        btn.innerHTML = '&hellip;';

        fetch('invoice-print?invoiceid=' + encodeURIComponent(invId))
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var parsed   = new DOMParser().parseFromString(html, 'text/html');
                var printDiv = parsed.getElementById('divToPrint');
                if (!printDiv) throw new Error('Invoice content not found.');

                var iframe = document.createElement('iframe');
                iframe.style.cssText = 'position:fixed;left:-9999px;top:0;width:900px;height:600px;border:0;';
                iframe.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' + printDiv.outerHTML + '</body></html>';
                iframe.onload = function () {
                    var idoc = iframe.contentDocument;

                    btn.disabled  = false;
                    btn.innerHTML = originalHtml;

                    shareInvoiceToWhatsApp({
                        elementId:     'divToPrint',
                        doc:           idoc,
                        mobile:        mobile,
                        invoiceNumber: invoiceNumber,
                        fileName:      'Invoice_' + invoiceNumber,
                        businessName:  <?php echo json_encode($business_name ?? ''); ?>,
                        button:        btn
                    });

                    setTimeout(function () { iframe.remove(); }, 15000);
                };
                document.body.appendChild(iframe);
            })
            .catch(function (err) {
                console.error(err);
                btn.disabled  = false;
                btn.innerHTML = originalHtml;
                alert('Could not prepare the invoice. Please try again.');
            });
    }
    </script>
</body>

</html>