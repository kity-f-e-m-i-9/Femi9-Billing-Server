<?php include("checksession.php"); include 'config.php'; error_reporting(0);
if($result_LoGuserDtails['user_position']==0){
	echo "<script>window.location='dashboard.php';</script>";exit;
}

// Request variables
if($_REQUEST['frdate']!=NULL) {
    $from_date = $_REQUEST['frdate'];
    $to_date   = $_REQUEST['todate'];
} else {
    $to_date   = date("Y-m-d");
    $from_date = date("Y-m-d", strtotime("-2 days", strtotime($to_date)));
}

$se_msid  = $_REQUEST['se_msid'] ?? '';
$se_taluk = $_REQUEST['se_taluk'] ?? '';

// Marketing staff selected name for dropdown
$result_msDetails12 = [];
if(!empty($se_msid)) {
    $select_msDetails12 = "SELECT * FROM marketing_staff WHERE id='$se_msid'";
    $fetch_msDetails12  = mysqli_query($db_conn, $select_msDetails12);
    $result_msDetails12 = mysqli_fetch_array($fetch_msDetails12);
}

// Taluk condition
$taluk_cond = !empty($se_taluk) ? " AND s.taluk_name = '" . mysqli_real_escape_string($db_conn, $se_taluk) . "'" : "";

// ✅ Single JOIN query — replaces 2 per-row queries
$base_select = "
    SELECT o.*,
           s.name           AS shop_name,
           s.mobile_number  AS shop_mobile,
           s.address        AS shop_address,
           s.taluk_name,
           m.ms_name,
           m.ms_mobile
    FROM ms_orders o
    LEFT JOIN ms_shop          s ON s.id  = o.shop_id
    LEFT JOIN marketing_staff  m ON m.id  = o.ms_id
    WHERE o.new_order = 'no'
";

if($from_date==NULL && $se_msid==NULL)
    $select_product_list = $base_select . " $taluk_cond ORDER BY o.id DESC";
elseif($from_date!=NULL && $se_msid==NULL)
    $select_product_list = $base_select . " AND o.order_date BETWEEN '$from_date' AND '$to_date' $taluk_cond ORDER BY o.id DESC";
else
    $select_product_list = $base_select . " AND o.order_date BETWEEN '$from_date' AND '$to_date' AND o.ms_id='$se_msid' $taluk_cond ORDER BY o.id DESC";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marketing Staff > No Orders : <?php echo $business_name;?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="../../assets/css/vlstyle.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
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
                                                <td>Marketing Staff &gt; No Orders</td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <!-- Search Form -->
                        <form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
                            <div class="overviewcontainar">

                                <div id="searchleftcont">
                                    <label class="form-label">From Date</label>
                                    <input type="date" required name="frdate" value="<?=$from_date;?>" class="form-control">
                                </div>

                                <div id="searchleftcont">
                                    <label class="form-label">To Date</label>
                                    <input type="date" required name="todate" value="<?=$to_date;?>" class="form-control">
                                </div>

                                <div id="searchleftcont">
                                    <label class="form-label">Taluk</label>
                                    <select name="se_taluk" class="form-control">
                                        <option value="">All Taluks</option>
                                        <?php
                                        $sel_taluks = "SELECT DISTINCT taluk_name FROM ms_shop WHERE taluk_name != '' ORDER BY taluk_name ASC";
                                        $fet_taluks = mysqli_query($db_conn, $sel_taluks);
                                        while($t = mysqli_fetch_assoc($fet_taluks)) {
                                            $sel = ($se_taluk == $t['taluk_name']) ? 'selected' : '';
                                            echo '<option value="'.htmlspecialchars($t['taluk_name']).'" '.$sel.'>'.htmlspecialchars($t['taluk_name']).'</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div id="searchleftcont">
                                    <label class="form-label">Marketing Staff</label>
                                    <select name="se_msid" class="form-control">
                                        <?php if(empty($se_msid)): ?>
                                        <option value="" hidden>Select</option>
                                        <?php else: ?>
                                        <option value="<?=$se_msid?>" hidden><?=strtoupper($result_msDetails12['ms_name'])?>, <?=$result_msDetails12['ms_mobile']?></option>
                                        <?php endif; ?>
                                        <?php
                                        $fetch_ms = mysqli_query($db_conn, "SELECT * FROM marketing_staff ORDER BY ms_name ASC");
                                        while($r = mysqli_fetch_array($fetch_ms)):
                                        ?>
                                        <option value="<?=$r['id']?>"><?=strtoupper($r['ms_name'])?>, <?=$r['ms_mobile']?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div id="searchbuttoncont">
                                    <button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
                                </div>
                                <div id="searchbuttoncont">
                                    <button type="button" onclick="window.location='ms_prorders';" style="margin-left:10px;" class="btn btn-primary">Reset</button>
                                </div>

                            </div>
                            <div style="clear:both;"></div>
                            <br/>
                        </form>

                        <?php
                        $num_rec_per_page = 30;
                        $page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
                        $start_from = ($page - 1) * $num_rec_per_page;
                        $i = $start_from;
                        ?>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="overflow-x:scroll;">
                                            <table id="datatable1" style="width:100%;">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Marketing Staff</th>
                                                        <th>Shop Name</th>
                                                        <th>Shop Contact Number</th>
                                                        <th>Address</th>
                                                        <th>Date</th>
                                                        <th>Reason</th>
                                                        <th>Marketing Tool</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                $fetch_product_list = mysqli_query($db_conn, $select_product_list);
                                                while($row = mysqli_fetch_array($fetch_product_list)):
                                                ?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td>
                                                        <?=$row['ms_name']?><br/>
                                                        <?=$row['ms_mobile']?>
                                                    </td>
                                                    <td>
                                                        <?=htmlspecialchars($row['shop_name'])?>
                                                        <?php if($row['taluk_name']): ?>
                                                        <br/><small class="text-muted"><?=htmlspecialchars($row['taluk_name'])?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?=htmlspecialchars($row['shop_mobile'])?></td>
                                                    <td><?=ucwords(htmlspecialchars($row['shop_address']))?></td>
                                                    <td><?=date("d/m/Y", strtotime($row['order_date']))?></td>
                                                    <td><?=htmlspecialchars($row['noorder_reason'])?></td>
                                                    <td><?=htmlspecialchars($row['marketing_tool'])?></td>
                                                </tr>
                                                <?php endwhile; ?>
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