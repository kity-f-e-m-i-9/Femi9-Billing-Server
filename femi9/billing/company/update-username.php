<?php include("checksession.php"); error_reporting(0);

$get_row_id=$_REQUEST['prid'];
$get_row_id_DECODE=base64_decode($get_row_id);
$pageNme=$_REQUEST['pageNme'];

if($pageNme=="superstockist")
{
	$Display_label="Super Stockist";
	$updae_table_name="super_stockiest"; //table name
	
	$select_candidate_list="select * from super_stockiest where id='$get_row_id_DECODE'";
	$fetch_candidate_list=mysqli_query($db_conn,$select_candidate_list);
	$result_candidate_list=mysqli_fetch_array($fetch_candidate_list);
				
}

else if($pageNme=="stockist")
{
	$Display_label="Stockist";
	$updae_table_name="stockiest"; //table name
	
	$select_candidate_list="select * from stockiest where id='$get_row_id_DECODE'";
	$fetch_candidate_list=mysqli_query($db_conn,$select_candidate_list);
	$result_candidate_list=mysqli_fetch_array($fetch_candidate_list);
	
}

else
{
	$Display_label="Distributor";
	$updae_table_name="distributor"; //table name
	
	$select_candidate_list="select * from distributor where id='$get_row_id_DECODE'";
	$fetch_candidate_list=mysqli_query($db_conn,$select_candidate_list);
	$result_candidate_list=mysqli_fetch_array($fetch_candidate_list);
}


if(isset($_REQUEST['update-username-action']))
{
	$update_id=$_REQUEST['update_id'];
	$page_name=$_REQUEST['page_name'];
	$update_table=$_REQUEST['update_table'];
	$usrname=str_replace("'","&#39;",$_REQUEST['usrname']);
	
	$select_count_Username="select count(*) as numUsrname from ".$update_table." where username='$usrname'";
	$fetch_count_Username=mysqli_query($db_conn,$select_count_Username);
	$result_count_Username=mysqli_fetch_array($fetch_count_Username);
	if($result_count_Username['numUsrname']==0)
	{
		$updateUsrname="update ".$update_table." set username='$usrname' where id='$update_id'";
		mysqli_query($db_conn,$updateUsrname);
		
		echo "<script>opener.location.reload(true);
     self.close();</script>";
	}
	else
	{
		$update_id_encode=base64_encode($update_id);
		
		echo "<script>window.location='update-username?pageNme=".$page_name."&&prid=".$update_id_encode."&&InvalidUsrname&&ActionUpdate';</script>";
	}
	
	
	
	
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
    <title>Update Username : <?php echo $Display_label;?></title>

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
		<style type="text/css">
		.table123{border-collapse:collapse;margin-left:10%;}
		.table123 tr td, .table123 tr th{padding:5px;}
		#inputbox{border:1px solid #000 !important;}
		#usernamebox{background:#c6ff54;font-weight:bold;padding:5px;border-radius:5px;letter-spacing:1px;}
		.centerBox{width:95%;margin:0 auto;}
		</style>
</head>

<body>

<script>
function closerefresh()
{
	/*opener.location.reload(true);*/
     self.close();
}
</script>
    
                        <div style="padding:20px;">
						 <h1><button type="button" class="btn btn-danger btn-burger" onClick="closerefresh()"><i class="material-icons">close</i></button> 
						 Update Username : <?php echo $Display_label;?>
									</h1>
									<hr/>
</div>

<div class="centerBox">
<?php if(isset($_REQUEST['InvalidUsrname'])){?><div class="alert alert-danger">Invalid username, already exists !</div>
			<?php }?>
			</div>

<form method="post" enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF'];?>">

<input type="hidden" name="update_id" value="<?php echo $get_row_id_DECODE;?>">
<input type="hidden" name="update_table" value="<?php echo $updae_table_name;?>">
<input type="hidden" name="page_name" value="<?php echo $pageNme;?>">

 <table class="table123">
                    <tr>
                    <th scope="col">Name</th>
                    <td>:&nbsp;&nbsp; <b><?php echo $result_candidate_list['name'];?></b></td>
                    </tr>
					<tr>
                    <th scope="col">Mobile Number</th>
                    <td>:&nbsp;&nbsp; <b><?php echo $result_candidate_list['mobile_number'];?></b></td>
                    </tr>
					<tr>
                    <th scope="col">Username</th>
                    <td><span id="usernamebox"><?php echo $result_candidate_list['username'];?></span></td>
                    </tr>
					<tr>
                    <th scope="col">Update Username</th>
                    <td><input type="text" name="usrname" id="inputbox" required="" class="form-control"/></td>
                    </tr>
					<tr>
                    <th scope="col"></th>
                    <td>
					<button type="submit" name="update-username-action" class="btn btn-primary"><i class="material-icons">update</i>Update</button>
					</td>
                    </tr>					
</table>
</form>
                        
                   

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