<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$tempid = $_REQUEST['tempid'] ?? '';

$stmt = $db_conn->prepare("SELECT * FROM demofreedamage WHERE tempid=? LIMIT 1");
$stmt->bind_param('s', $tempid);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Update Demo/Free/Damage : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
                                <h1><table class="headertble"><tr>
                                    <td>Update Demo/Free/Damage</td>
                                    <td><a href="demofree-manage.php" title="Manage">&#9776;</a></td>
                                </tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
<form action="demofree_action.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="update_tempid" value="<?php echo htmlspecialchars($tempid); ?>">

<div class="example-container">
<div class="example-content">

<label class="form-label">Category*</label>
<select required name="category" class="form-control">
    <option value="<?php echo htmlspecialchars($rec['category'] ?? ''); ?>" selected><?php echo htmlspecialchars($rec['category'] ?? ''); ?></option>
    <option>Demo</option>
    <option>Free</option>
    <option>Damage</option>
</select>
<br/>

<label class="form-label">Date*</label>
<input type="date" id="bookingDate" required name="date" value="<?php echo htmlspecialchars($rec['date'] ?? ''); ?>" class="form-control">
<br/>

<label class="form-label">Remarks*</label>
<textarea required name="remarks" class="form-control"><?php echo htmlspecialchars($rec['remarks'] ?? ''); ?></textarea>
<br/>

<button type="submit" name="update-record" class="btn btn-primary">Update</button>
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
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>flatpickr("#bookingDate", { dateFormat: "Y-m-d", maxDate: "today" });</script>
</body>
</html>
