<?php include("checksession.php"); error_reporting(0);

$usertype=$_REQUEST['usertype'];
$userid=$_REQUEST['userid'];
$fromdate=$_REQUEST['frd'];
$todate=$_REQUEST['tod'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Invoice Details : <?php echo $business_name;?></title>

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
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">
	
        <div class="app-sidebar">
            <?php //include("logo.php");?>
            <?php //include("femi_menu.php");?>
        </div>
		
        <div class="app-container">
            
          <?php //include("app-header.php");?>
			
            <div>
                <div>
                    <div>
            
		<script>
function closerefresh()
  {
     self.close();
  }
</script>
<input type="submit" value="&#8630;&nbsp;Go Back" onClick="closerefresh();" style="margin-bottom:10px;"/>


                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                              <table id="datatable3" class="display nowrap" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
													<th>Inv Number</th>
													<th>Product</th>
													<th>Qty</th>
                                                </tr>
                                            </thead>
											
											<tbody>
				<?php 
				$select_tslsDetails="select * from temp_report where usertype='$usertype' and userid='$userid' and date between '$fromdate' and '$todate' order by id asc";
$fetch_tslsDetails=mysqli_query($db_conn,$select_tslsDetails);
while($result_tslsDetails=mysqli_fetch_array($fetch_tslsDetails))
{
											
//
$pr_id=$result_tslsDetails['pr_id'];
$select_distict="select * from products where id='$pr_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$productName=$result_district['productName'];

//
$inv_id=$result_tslsDetails['inv_id'];
$select_distict12="select * from user_invoice where inv_id='$inv_id'";
$fetch_district12=mysqli_query($db_conn,$select_distict12);
$result_district12=mysqli_fetch_array($fetch_district12);
$invnumber=$result_district12['inv_number'];
											
					
						?>
                                            
                                            <tr>
					<td><?php echo date("d/m/Y",strtotime($result_tslsDetails["date"]));?></td>
					<td><?=$invnumber;?></td>
					<td><?=$productName;?></td>
					<td><?=$result_tslsDetails['qty'];?></td>
					
                                                </tr>
                                           
										<?php }?>
										
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