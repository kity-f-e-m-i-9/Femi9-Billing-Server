<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$advBalance = 0;
$tp_id = (int) $Login_user_IDvl;

// Fetch TP details
$stmtTP = $db_conn->prepare("SELECT * FROM territory_partners WHERE id = ?");
$stmtTP->bind_param('i', $tp_id);
$stmtTP->execute();
$result_tp = $stmtTP->get_result()->fetch_assoc();
$stmtTP->close();

// Fetch profile/bank details
$stmtProf = $db_conn->prepare("SELECT * FROM users_profile WHERE user_tempid = ? AND usertype = ?");
$tp_type = 'territory_partner';
$stmtProf->bind_param('ss', $Login_user_IDvl, $tp_type);
$stmtProf->execute();
$result_profile = $stmtProf->get_result()->fetch_assoc();
$stmtProf->close();

// Handle profile update
if (isset($_REQUEST['updateprofile'])) {
    $name          = str_replace("'", "&#39;", $_REQUEST['name']          ?? '');
    $email         = str_replace("'", "&#39;", $_REQUEST['email']         ?? '');
    $gstin         = str_replace("'", "&#39;", $_REQUEST['gstin']         ?? '');
    $company_name  = str_replace("'", "&#39;", $_REQUEST['companyname']   ?? '');
    $branch_line1  = str_replace("'", "&#39;", $_REQUEST['branch_line1']  ?? '');
    $branch_line2  = str_replace("'", "&#39;", $_REQUEST['branch_line2']  ?? '');
    $branch_city   = str_replace("'", "&#39;", $_REQUEST['branch_city']   ?? '');
    $branch_state  = str_replace("'", "&#39;", $_REQUEST['branch_state']  ?? '');
    $branch_pincode= str_replace("'", "&#39;", $_REQUEST['branch_pincode']?? '');

    $stmtUpd = $db_conn->prepare("UPDATE territory_partners SET name=?, email=?, gstin=?, company_name=?, branch_line1=?, branch_line2=?, branch_city=?, branch_state=?, branch_pincode=? WHERE id=?");
    $stmtUpd->bind_param('sssssssssi', $name, $email, $gstin, $company_name, $branch_line1, $branch_line2, $branch_city, $branch_state, $branch_pincode, $tp_id);
    $stmtUpd->execute();
    $stmtUpd->close();

    // Bank/profile details
    $acname          = $_REQUEST['acname']          ?? '';
    $acnumber        = $_REQUEST['acnumber']        ?? '';
    $bankname        = $_REQUEST['bankname']        ?? '';
    $branchname      = $_REQUEST['branchname']      ?? '';
    $ifsc            = $_REQUEST['ifsc']            ?? '';
    $upinumber       = $_REQUEST['upinumber']       ?? '';
    $companyname     = $_REQUEST['companyname']     ?? '';
    $deliveryaddress = $_REQUEST['deliveryaddress'] ?? '';

    // Logo upload
    $old_logo   = $_REQUEST['old_logo'] ?? '';
    $logo_value = $old_logo;
    if (!empty($_FILES['logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $newname   = 'tp_logo_' . $Login_user_IDvl . '_' . time() . '.' . $ext;
            $uploaddir = __DIR__ . '/bussiness_logo/';
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploaddir . $newname)) {
                if ($old_logo && file_exists($uploaddir . $old_logo)) {
                    unlink($uploaddir . $old_logo);
                }
                $logo_value = $newname;
            }
        }
    }

    $stmtCntProf = $db_conn->prepare("SELECT COUNT(*) AS n FROM users_profile WHERE user_tempid = ? AND usertype = ?");
    $stmtCntProf->bind_param('ss', $Login_user_IDvl, $tp_type);
    $stmtCntProf->execute();
    $profCount = (int)$stmtCntProf->get_result()->fetch_assoc()['n'];
    $stmtCntProf->close();

    if ($profCount == 0) {
        $empty = '';
        $stmtInsProf = $db_conn->prepare("INSERT INTO users_profile (user_tempid, usertype, companyname, deliveryaddress, acname, acnumber, bankname, branchname, ifsc, upinumber, pannumber, logo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmtInsProf->bind_param('ssssssssssss', $Login_user_IDvl, $tp_type, $companyname, $deliveryaddress, $acname, $acnumber, $bankname, $branchname, $ifsc, $upinumber, $empty, $logo_value);
        $stmtInsProf->execute();
        $stmtInsProf->close();
    } else {
        $stmtUpdProf = $db_conn->prepare("UPDATE users_profile SET companyname=?, deliveryaddress=?, acname=?, acnumber=?, bankname=?, branchname=?, ifsc=?, upinumber=?, logo=? WHERE user_tempid=? AND usertype=?");
        $stmtUpdProf->bind_param('sssssssssss', $companyname, $deliveryaddress, $acname, $acnumber, $bankname, $branchname, $ifsc, $upinumber, $logo_value, $Login_user_IDvl, $tp_type);
        $stmtUpdProf->execute();
        $stmtUpdProf->close();
    }

    echo "<script>window.location='my-profile.php?Updatedsuccess';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1><table class="headertble"><tr><td>My Profile</td></tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($_REQUEST['Updatedsuccess'])): ?><div class="alert alert-success">Profile updated successfully.</div><?php endif; ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Please confirm update.');">
                                        <div class="example-container">
                                            <div class="example-content">

<label class="form-label">Name</label>
<input type="text" required name="name" value="<?php echo htmlspecialchars($result_tp['name'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">Mobile Number</label>
<input type="text" value="<?php echo htmlspecialchars($result_tp['mobile'] ?? ''); ?>" disabled class="form-control">
<br/>

<label class="form-label">Email ID</label>
<input type="email" name="email" value="<?php echo htmlspecialchars($result_tp['email'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">GSTIN</label>
<input type="text" name="gstin" value="<?php echo htmlspecialchars($result_tp['gstin'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">Company Name</label>
<input type="text" name="companyname" value="<?php echo htmlspecialchars($result_tp['company_name'] ?? $result_profile['companyname'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">Logo / Photo</label>
<?php if (!empty($result_profile['logo'])): ?>
<img src="bussiness_logo/<?php echo htmlspecialchars($result_profile['logo']); ?>" style="width:120px;border-radius:8px;display:block;margin-bottom:8px;"/>
<?php endif; ?>
<input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png">
<input type="hidden" name="old_logo" value="<?php echo htmlspecialchars($result_profile['logo'] ?? ''); ?>">
<small class="text-muted">Allowed: jpg, jpeg, png. This logo appears on your invoices.</small>
<br/><br/>

<label class="form-label">Address Line 1</label>
<input type="text" name="branch_line1" value="<?php echo htmlspecialchars($result_tp['branch_line1'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">Address Line 2</label>
<input type="text" name="branch_line2" value="<?php echo htmlspecialchars($result_tp['branch_line2'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">City</label>
<input type="text" name="branch_city" value="<?php echo htmlspecialchars($result_tp['branch_city'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">State</label>
<input type="text" name="branch_state" value="<?php echo htmlspecialchars($result_tp['branch_state'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">Pincode</label>
<input type="text" name="branch_pincode" value="<?php echo htmlspecialchars($result_tp['branch_pincode'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">Delivery Address</label>
<textarea name="deliveryaddress" class="form-control"><?php echo htmlspecialchars($result_profile['deliveryaddress'] ?? ''); ?></textarea>
<br/>

<h5>Bank Details</h5>

<label class="form-label">A/c Name</label>
<input type="text" name="acname" value="<?php echo htmlspecialchars($result_profile['acname'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">A/c Number</label>
<input type="text" name="acnumber" value="<?php echo htmlspecialchars($result_profile['acnumber'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">Bank Name</label>
<input type="text" name="bankname" value="<?php echo htmlspecialchars($result_profile['bankname'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">Branch Name</label>
<input type="text" name="branchname" value="<?php echo htmlspecialchars($result_profile['branchname'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">IFS Code</label>
<input type="text" name="ifsc" value="<?php echo htmlspecialchars($result_profile['ifsc'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">UPI Number</label>
<input type="text" name="upinumber" value="<?php echo htmlspecialchars($result_profile['upinumber'] ?? ''); ?>" class="form-control">
<br/>

<button type="submit" name="updateprofile" class="btn btn-primary"><i class="material-icons">update</i>Update</button>

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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
