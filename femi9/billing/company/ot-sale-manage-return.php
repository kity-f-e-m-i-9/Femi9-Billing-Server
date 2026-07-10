<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
error_reporting(0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Manage OT Sales Return : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

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
								<?php 
                                    // Check for error message in session
                                    if (isset($_SESSION['sucMessage'])) {
                                    $errorMessage = $_SESSION['sucMessage'];
                                    ?>
                                        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                                            <script>
                                                Swal.fire({
                                                    icon: 'success',
                                                    title: 'Success',
                                                    text: '<?php echo $errorMessage; ?>',
                                                        confirmButtonText: 'OK'
                                                    });
                                    		</script>
                                <?php  unset($_SESSION['sucMessage']); } ?>
                                    <h1>
								        <table class="headertble">
									<tr>
									<td>Manage OT Sales Return</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
                        <?php
                        //----Serial Number Counter.......................
                        $i = 0;
                        //---------------------------------------------------------------
                        
                        // Get products for table headers
                        $products = array();
                        $select_products = "SELECT * FROM products ORDER BY id ASC";
                        $fetch_products = mysqli_query($db_conn, $select_products);
                        while($result_products = mysqli_fetch_array($fetch_products)) {
                            $products[] = $result_products;
                        }
                        ?>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="overflow-x:scroll;">
                                         <table id="datatable1" class="table table-striped" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Return Date</th>
                                                    <th>Order Number</th>
													<th>Customer Name</th>
													<th>Customer Mobile</th>
													<th>Address</th>
													<th>Product Name</th>
													
                                    				<?php foreach($products as $product) { ?>
                                    				<th><?= $product['productName']; ?></th>
                                    				<?php } ?>
				
                                                </tr>
                                            </thead>
											<tbody>
										<?php
                                            // Main query with proper ordering - no pagination limit (DataTables will handle pagination)
                                            $select_product_list = "SELECT DISTINCT osr.tempid, osr.return_date 
                                                                   FROM ot_sales_return osr 
                                                                   ORDER BY osr.return_date DESC";
                                            $fetch_product_list = mysqli_query($db_conn, $select_product_list);
                                            
                                            if(mysqli_num_rows($fetch_product_list) > 0) {
                                                while($result_product_list = mysqli_fetch_array($fetch_product_list)) {
                                                    $tempid = $result_product_list["tempid"];
                                                    $return_date = $result_product_list["return_date"];
                                                    
                                                    // Get return details
                                                    $select_ReturnDetails = "SELECT * FROM ot_sales_return WHERE tempid='$tempid' LIMIT 1";
                                                    $fetch_ReturnDetails = mysqli_query($db_conn, $select_ReturnDetails);
                                                    $result_ReturnDetails = mysqli_fetch_array($fetch_ReturnDetails);
                                                    
                                                    if($result_ReturnDetails) {
                                                        $prid = $result_ReturnDetails['prid'];
                                                        
                                                        // Get product details
                                                        $selectProducts = "SELECT * FROM products WHERE id='$prid'";
                                                        $fetchProducts = mysqli_query($db_conn, $selectProducts);
                                                        $resultProducts = mysqli_fetch_array($fetchProducts);
                                                        
                                                        // Get sales details
                                                        $select_productDetails = "SELECT * FROM ot_sales WHERE tempid='$tempid'";
                                                        $fetch_productDetails = mysqli_query($db_conn, $select_productDetails);
                                                        $result_productDetails = mysqli_fetch_array($fetch_productDetails);
                                                        
                                                        if($result_productDetails && $resultProducts) {
                                            ?>
                                            <tr>
                                                <td><?php echo ++$i; ?></td>
												<td><?php echo date("d/m/Y", strtotime($return_date)); ?></td>
												<td><?php echo $result_productDetails["order_number"]; ?></td>
												<td><?php echo $result_productDetails["customer_name"]; ?></td>
												<td><?php echo $result_productDetails["customer_mobile"]; ?></td>
												<td><?php echo $result_productDetails["customer_address"]; ?></td>
												<td><?php echo $resultProducts["productName"]; ?></td>
												
												<?php 
                                                // Product wise quantities
                                                foreach($products as $product) {
                                                    $prid_header = $product['id'];
                                                    
                                                    $select_QTY = "SELECT qty FROM ot_sales_return WHERE tempid='$tempid' AND prid='$prid_header'";
                                                    $fetch_QTY = mysqli_query($db_conn, $select_QTY);
                                                    $result_QTY = mysqli_fetch_array($fetch_QTY);
                                                    
                                                    $returnqty = ($result_QTY && $result_QTY['qty'] != NULL) ? $result_QTY['qty'] : "0";
                                                ?>
                                                <td><?= $returnqty; ?></td>
                                                <?php } ?>
                                            </tr>
                                                <?php 
                                                            }
                                                        }
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="' . (7 + count($products)) . '">No records found</td></tr>';
                                                }
                                                ?>
										 </tbody>
                                        </table>
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
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/datatables.js"></script>
</body>

</html>