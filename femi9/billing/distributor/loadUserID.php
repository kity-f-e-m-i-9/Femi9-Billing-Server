<?php include("checksession.php");
$st_user_type=$_GET['q']; 
error_reporting(0);
		
		if($st_user_type=="super_distributor"){
		$tblename="super_distributor";
		$lablename="Super-Distributor";
		$preLable="FEMI9-SD-";
		}
		if($st_user_type=="distributor"){
			$tblename="distributor";
			$lablename="Distributor";
			$preLable="FEMI9-D-";
		}
		if($st_user_type=="super_stockiest"){
			$tblename="super_stockiest";
			$lablename="Super Stockist";
			$preLable="FEMI9-SS-";
		}
		if($st_user_type=="stockiest"){
			$tblename="stockiest";
			$lablename="Stockist";
			$preLable="FEMI9-S-";
		}
		
		if($st_user_type!="company")
		{
	?>

<input type="hidden" name="tblename" value="<?=$tblename;?>">

<div class="form-group">
            <div class="country-code">
			<label class="form-label">Referred User ID</label>
<input type="text" required="" name="st_ref_userid" value="<?=$preLable;?>" class="form-control" readonly>
            </div>
            <div class="mobile-number">
                <label class="form-label">&nbsp;</label>
                <input type="number" required="" name="st_ref_userid2" min="0" class="form-control" onkeypress="restrictnumber(event)">
			</div>
        </div>
<br/>
		<?php }?>
 