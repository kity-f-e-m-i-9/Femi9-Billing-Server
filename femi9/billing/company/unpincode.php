<?php include("checksession.php");
include("config.php");
error_reporting(0);

$title="Unassigned Distributors - Pincodewise";
$add_title="Unassigned Distributors - Pincodewise";

if(isset($_REQUEST['search-records']))
{
	$state_id=$_REQUEST['state_id'];
	$dist_id=$_REQUEST['dist_id'];
	$talukid=$_REQUEST['talukid'];
	
//$sestate=$_REQUEST['sestate'];
//$sedist=$_REQUEST['sedist'];

//state details
if($state_id!=NULL)
{
							   $select_stateList="select * from `state` where id='$state_id'";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   $result_stateList=mysqli_fetch_array($fetch_staeList);
							   $state_name=$result_stateList['st_name'];
}
							   
										//district details
										if($dist_id!=NULL)
										{
										$select_Distdetails="select * from district where id=$dist_id";
										$fetch_Distdetails=mysqli_query($db_conn,$select_Distdetails);
										$result_Distdetails=mysqli_fetch_array($fetch_Distdetails);
										$district_name=$result_Distdetails['dist_name'];
										}
											
										//taluk details
										if($talukid!=NULL)
										{
										$select_taluk="select * from taluk where id=$talukid";
										$fetch_taluk=mysqli_query($db_conn,$select_taluk);
										$result_taluk=mysqli_fetch_array($fetch_taluk);
										$talukname=$result_taluk['taluk'];
										}	
	
	//echo "<script>window.location='untaluk?sestate=".$state_id."&&sedist=".$dist_id."';</script>";
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
    <title><?=$title;?> : <?=$business_name;?></title>

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
									<td>
									<?php if($state_id!=NULL){?>
									<a href="export_unpincode?stid=<?=$state_id;?>&&did=<?=$dist_id;?>&&tid=<?=$talukid;?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a>
									<?php }else{?>
									<a href="#" title="Export" onclick="return confirm('Please select any one search criteria');"><img src="../../assets/images/excel-3-32.png"></a>
									<?php }?>
									</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						<form method="post" enctype="multipart/form-data">
                                        <div class="example-container">
                                            <div class="example-content">
								
<script language="javascript" type="text/javascript">
function getXMLHTTP() { //fuction to return the xml http object
		var xmlhttp=false;	
		try{
			xmlhttp=new XMLHttpRequest();
		}
		catch(e)	{		
			try{			
				xmlhttp= new ActiveXObject("Microsoft.XMLHTTP");
			}
			catch(e){
				try{
				xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
				}
				catch(e1){
					xmlhttp=false;
				}
			}
		}
		return xmlhttp;
    }
	
	function getState(courseId) {		
		var strURL="load-un-district2.php?subcourseID="+courseId;
		var req = getXMLHTTP();
		if (req) {
			req.onreadystatechange = function() {
				if (req.readyState == 4) {
					// only if "OK"
					if (req.status == 200) {						
						document.getElementById('statediv').innerHTML=req.responseText;						
					} else {
						alert("There was a problem while using XMLHTTP:\n" + req.statusText);
					}
				}				
			}			
			req.open("GET", strURL, true);
			req.send(null);
		}		
	}
	function getCity(courseId,scourseID) {		
		
		var strURL="load-un-taluk.php?subcourseID="+courseId+"&techID="+scourseID;
		var req = getXMLHTTP();
		if (req) {
			req.onreadystatechange = function() {
				if (req.readyState == 4) {
					// only if "OK"
					if (req.status == 200) {						
						document.getElementById('techdiv').innerHTML=req.responseText;						
					} else {
						alert("There was a problem while using XMLHTTP:\n" + req.statusText);
					}
				}				
			}			
			req.open("GET", strURL, true);
			req.send(null);
		}		
	}
	</script>
								<!------------State Name--------->
							   <label for="exampleInputEmail1" class="form-label">State Name</label>
                               <select required="" name="state_id" class="form-control" onChange="getState(this.value)">
							   <?php if($state_id==NULL){?>
							   <option value="" hidden="">Select</option>
							   <?php }else{?>
							   <option value="<?=$state_id;?>" hidden=""><?=$state_name;?></option>
							   <?php }?>
							   <?php $select_stateList="select * from `state` order by `st_name` asc";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   while($result_stateList=mysqli_fetch_array($fetch_staeList))
							   {?>
						   <option value="<?php echo $result_stateList['id'];?>"><?php echo $result_stateList['st_name'];?></option>
							   <?php }?>
							   </select>
							   
							   <!------------District Name--------->
							   <label for="exampleInputEmail1" class="form-label">District Name</label>
<div id="statediv">                                               
		<select name="dist_id" class="form-control" onChange="getCity(<?=$state_id;?>,this.value)">
												<?php if($dist_id==NULL){?>
							   <option value="" hidden="">Select</option>
							   <?php }else{?>
							   <option value="<?=$dist_id;?>" hidden=""><?=$district_name;?></option>
							   <option value="">--All--</option>
							   <?php }?>
							   
							   <?php $select_product_list233="select * from district where state_id='$state_id' order by dist_name asc";
										$fetch_product_list233=mysqli_query($db_conn,$select_product_list233);
										while($result_product_list233=mysqli_fetch_array($fetch_product_list233))
										{
											?>
											<option value="<?php echo $result_product_list233['id'];?>"><?php echo $result_product_list233['dist_name'];?></option>
										<?php }?>
												</select>
												</div>
												
<!---------Taluk----------->
<label for="exampleInputEmail1" class="form-label">Taluk Name</label>
<div id="techdiv">                                               
<select name="talukid" class="form-control">
								<?php if($talukid==NULL){?>
							   <option value="" hidden="">Select</option>
							   <?php }else{?>
							   <option value="<?=$talukid;?>" hidden=""><?=$talukname;?></option>
							   <option value="">--All--</option>
							   <?php }?>
							   
				<?php $select_product_list244="select * from taluk where state_id='$state_id' and dist_id='$dist_id' order by taluk asc";
			$fetch_product_list244=mysqli_query($db_conn,$select_product_list244);
			while($result_product_list244=mysqli_fetch_array($fetch_product_list244))
										{
											?>
<option value="<?=$result_product_list244['id'];?>"><?=$result_product_list244['taluk'];?></option>
								<?php }?>
												</select>
												</div>
												<!------------------------>
											
			<button type="submit" name="search-records" class="btn btn-primary" style="margin-top:10px;"><i class="material-icons">search</i>Search</button>
												
                                            </div>
                                        </div>
										</form>
						
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
                                        <table id="datatable1" class="display" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>State Name</th>
                                                    <th>District Name</th>
													<th>Taluk Name</th>
													<th>Pincode</th>
													<th>Stockist Status</th>
													<th>Distributor Status</th>
                                                </tr>
                                            </thead>
											 <tbody>
											 
							<?php 
							if($state_id!=NULL && $dist_id!=NULL && $talukid!=NULL)
							{
							$select_product_list="select * from pincode where state_id='$state_id' and dist_id='$dist_id' and taluk_id='$talukid' order by id asc";
							}
							else if($state_id!=NULL && $dist_id==NULL && $talukid==NULL)
							{
							$select_product_list="select * from pincode where state_id='$state_id' order by id asc";
							}
							else
							{
							$select_product_list="select * from pincode where state_id='$state_id' and dist_id='$dist_id' order by id asc";
							}
							
							$fetch_product_list=mysqli_query($db_conn,$select_product_list);
							while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											//state details
											$dis_state_id=$result_product_list['state_id'];
								$select_stateList12="select * from `state` where id='$dis_state_id'";
							   $fetch_staeList12=mysqli_query($db_conn,$select_stateList12);
							   $result_stateList12=mysqli_fetch_array($fetch_staeList12);
							   $dis_state_name=$result_stateList12['st_name'];
							   
							   //district details
							   $dis_district_id=$result_product_list['dist_id'];
							   $select_district12="select * from district where id=$dis_district_id";
										$fetch_district12=mysqli_query($db_conn,$select_district12);
										$result_district12=mysqli_fetch_array($fetch_district12);
										$dis_district_name=$result_district12['dist_name'];
										
										//taluk details
							   $dis_talk_id=$result_product_list['taluk_id'];
							   $select_district1223="select * from taluk where id=$dis_talk_id";
										$fetch_district1223=mysqli_query($db_conn,$select_district1223);
										$result_district1223=mysqli_fetch_array($fetch_district1223);
										$dis_taluk_name=$result_district1223['taluk'];
											
											?>
                                           
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
									<td><?php echo $dis_state_name;?></td>
									<td><?php echo $dis_district_name;?></td>
									<td><?php echo $dis_taluk_name;?></td>
									<td><?php echo $result_product_list['pincode'];?></td>
<td>
<?php if($result_product_list["assigned_SID"]=="Nil"){?>
<span class='badge badge-style-bordered badge-success'>Available</span>
<?php }else{?>
<span class='badge badge-style-bordered badge-danger'>already user appointed</span>
<?php }?>
</td>	

<td>
<?php if($result_product_list["assigned_DID"]=="Nil"){?>
<span class='badge badge-style-bordered badge-success'>Available</span>
<?php }else{?>
<span class='badge badge-style-bordered badge-danger'>already user appointed</span>
<?php }?>
</td>												
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