<?php include("checksession.php"); 
include("config.php"); 
date_default_timezone_set("Asia/Kolkata");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Add Demo/Free/Damage : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
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
                                     <h1>
									<table class="headertble">
									<tr>
									<td>Add Demo/Free/Damage</td>
		<td><a href="demofree_manage" title="Manage Demo/Free/Damage">&#9776;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <!----<div class="card-header">
                                        <h5 class="card-title">Basic Input</h5>
                                    </div>--->
                                    <div class="card-body">
									
									<?php
// Check for error message in session
if (isset($_SESSION['errorMessage'])) {
$errorMessage = $_SESSION['errorMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'error',
                          title: 'Warning',
                          text: '<?php echo $errorMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['errorMessage']); }
unset($_SESSION['sucMessage']);
 ?>
                                       
	 
<form action="demofree_action" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">


<?php function GeraHash($qtd){ $Caracteres = '123456789'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } $randum_number=GeraHash(5);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$tempid="".$randum_number."DFD/".$temp_date."/".$temp_time."";?>

<input type="hidden" name="tempid" value="<?=$tempid?>">
<input type="hidden" name="usertype" value="<?=$Login_user_TYPEvl?>">

                                        <div class="example-container">
                                        <div class="example-content">
										
<!----------------------------------GODOWN------------------------------>										
<script type="text/javascript">
function checkopeningstock(str){
    if (str == "") { document.getElementById("txtHint").innerHTML = ""; return; }
    // Load submit button / stock warning
    var x = new XMLHttpRequest();
    x.onreadystatechange = function(){ if(x.readyState==4 && x.status==200){ document.getElementById("opstock").innerHTML = x.responseText; } };
    x.open("GET", "loadopeningstock2.php?q=" + str, true);
    x.send();
    // Load products with stock for selected godown
    var y = new XMLHttpRequest();
    y.onreadystatechange = function(){
        if (y.readyState == 4 && y.status == 200) {
            var selects = document.querySelectorAll('select[name="product_id[]"]');
            for (var i = 0; i < selects.length; i++) {
                selects[i].innerHTML = y.responseText;
            }
        }
    };
    y.open("GET", "load_products_by_godown.php?godown_id=" + str, true);
    y.send();
}
</script>
							   <label for="exampleInputEmail1" class="form-label">Company Profile*</label>
                               <select required="" name="userid" class="form-control" onchange="checkopeningstock(this.value);">
							   <option value="" hidden="">Select</option>
							   <?php $select_Godown="select * from company_godown order by id asc";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   while($result_Godown=mysqli_fetch_array($fetch_Godown))
							   {?>
						       <option value="<?=$result_Godown['id'];?>"><?=$result_Godown['gname'];?></option>
							   <?php }?>
							   </select>
							   <br/>
<!------------------------------------GODOWN------------------------------>

<label for="exampleInputEmail1" class="form-label">Category*</label>
                               <select required="" name="category" class="form-control">
							   <option value="" hidden="">Select</option>
							   <option>Demo</option>
							   <option>Free</option>
							   <option>Damage</option>
							   <option>Conversion</option>
							   </select>
							   <br/>	
							
<label class="form-label">Date*</label>
<input type="date" required="" name="date" value="<?php echo date("Y-m-d");?>" class="form-control">
<br/>

<label class="form-label">Remarks*</label>
<textarea required="" name="remarks" onkeypress="restrictSpecialChars(event)" class="form-control"></textarea>
<br/>

<script>
        function addRow(tableID) {
	var table = document.getElementById(tableID);
	var rowCount = table.rows.length;
	if(rowCount < 100){							// limit the user from creating fields more than your limits
		var row = table.insertRow(rowCount);
		var colCount = table.rows[0].cells.length;
		for(var i=0; i<colCount; i++) {
			var newcell = row.insertCell(i);
			newcell.innerHTML = table.rows[0].cells[i].innerHTML;
		}
	}else{
		 alert("Maximum Passenger per ticket is 100.");
			   
	}
}
function deleteRow(tableID) {
	var table = document.getElementById(tableID);
	var rowCount = table.rows.length;
	for(var i=0; i<rowCount; i++) {
		var row = table.rows[i];
		var chkbox = row.cells[0].childNodes[0];
		if(null != chkbox && true == chkbox.checked) {
			if(rowCount <= 1) { 						// limit the user from removing all the fields
				alert("Cannot Remove all Field .");
				break;
			}
			table.deleteRow(i);
			rowCount--;
			i--;
		}
	}
}</script> 
				
				<p> 
					<button type="button" class="btn btn-primary btn-burger" onClick="addRow('dataTable')"><i class="material-icons">add</i></button> 
					<button type="button" class="btn btn-danger btn-burger" onClick="deleteRow('dataTable')"><i class="material-icons">delete_outline</i></button>
				</p>
				
				 <table id="dataTable" border="0">
                    <tr>
						<td><input type="checkbox" name="chk[]"/></td>
						 <td>
							<select required="" name="product_id[]" class="form-control" required="">
<option value="" hidden="">Select Company Profile first</option>
</select>
					     </td>
						 <td><input type="number" placeholder="Qty" min="0" name="qty[]" class="form-control" required=""/></td>
                    </tr>
                </table>
				<br/>					

			<span id="opstock">									
<button type="submit" name="add-record" class="btn btn-primary"><i class="material-icons">add</i>Submit</button>
</span>
												
                                            </div>
                                        </div>
										</form>
	 
										
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