<?php
include("checksession.php");
require_once("include/GodownAccess.php");
error_reporting(0);

// ── Date resolution (needed before export) ────────────────────────────
if(!empty($_REQUEST['frdate'])){
    $from_date = $_REQUEST['frdate'];
    $to_date   = $_REQUEST['todate'];
} else {
    $to_date   = date("Y-m-d");
    $from_date = date("Y-m-d", strtotime("-2 days", strtotime($to_date)));
}

// ── Handle Excel Export ───────────────────────────────────────────────
if(isset($_REQUEST['export_excel']))
{
    $sql_exp = "SELECT i.*, p.productName, g.gname
                FROM input_stock i
                LEFT JOIN products p ON p.id = i.product_id
                LEFT JOIN company_godown g ON g.id = i.godownid
                WHERE i.input_date BETWEEN '$from_date' AND '$to_date'
                  AND (g.id IS NULL OR " . godown_finance_filter_sql($db_conn, 'g') . ")
                ORDER BY i.input_date DESC";
    $res_exp = mysqli_query($db_conn, $sql_exp);

    ob_end_clean(); // clear any output buffered by checksession.php

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename=input_stocks_'.$from_date.'_to_'.$to_date.'.xls');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    echo "<table border='1'>";
    echo "<tr>
            <th>S.No</th>
            <th>Godown Name</th>
            <th>Date</th>
            <th>Product Name</th>
            <th>Input Qty</th>
            <th>Remarks</th>
          </tr>";

    $sno = 1;
    while($row_exp = mysqli_fetch_array($res_exp)){
        echo "<tr>
                <td>".$sno++."</td>
                <td>".htmlspecialchars($row_exp['gname'])."</td>
                <td>".date("d/M/Y", strtotime($row_exp['input_date']))."</td>
                <td>".htmlspecialchars($row_exp['productName'])."</td>
                <td>".$row_exp['input_qty']."</td>
                <td>".htmlspecialchars($row_exp['input_remarks'])."</td>
              </tr>";
    }
    echo "</table>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Manage Input Stocks : <?php echo $business_name;?></title>

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

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
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
								
								<?php if(isset($_REQUEST['addesuccess'])){?><div class="alert alert-success">Input stock details added success.</div><?php }?>
								<?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved success.</div><?php }?>
								<?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! one input stock details deleted success.</div><?php }?>

<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
    <div class="overviewcontainar">
        <div id="searchleftcont">
            <label class="form-label">From Date</label>
            <input type="date" required="" name="frdate" value="<?=$from_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
        </div>
        <div id="searchleftcont">
            <label class="form-label">To Date</label>
            <input type="date" required="" name="todate" value="<?=$to_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
        </div>
        <div id="searchbuttoncont">
            <button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
        </div>
        <div id="searchbuttoncont">
            <a href="export-input-stocks.php?frdate=<?=$from_date?>&todate=<?=$to_date?>" target="_blank" class="btn btn-warning">
                <i class="material-icons" style="vertical-align:middle;font-size:18px;">table_view</i> Export Excel
            </a>
        </div>
    </div>
    <div style="clear:both;"></div>
    <br/>
</form>	

                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td>Manage Input Stocks</td>
                                                <td><a href="add-input" title="Add Input Stock">&#10011;</a></td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>
						
<?php
$num_rec_per_page = 30;
if (isset($_GET["page"])) { $page = $_GET["page"]; } else { $page = 1; }
$start_from = ($page-1) * $num_rec_per_page; 
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
                                                        <th>S.No</th>
                                                        <th>Godown Name</th>
                                                        <th>Date</th>
                                                        <th>Product Name</th>
                                                        <th>Input Qty</th>
                                                        <th>Remarks</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php 
                                                $select_product_list = "SELECT i.*, p.productName, g.gname
                                                                         FROM input_stock i
                                                                         LEFT JOIN products p ON p.id = i.product_id
                                                                         LEFT JOIN company_godown g ON g.id = i.godownid
                                                                         WHERE i.input_date BETWEEN '$from_date' AND '$to_date'
                                                                           AND (g.id IS NULL OR " . godown_finance_filter_sql($db_conn, 'g') . ")
                                                                         ORDER BY i.input_date DESC";
                                                $fetch_product_list = mysqli_query($db_conn, $select_product_list);
                                                while($result_product_list = mysqli_fetch_array($fetch_product_list)){
                                                    $RowID = base64_encode($result_product_list["id"]);
                                                ?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo $result_product_list["gname"]; ?></td>
                                                    <td><?php echo date("d/M/Y", strtotime($result_product_list["input_date"])); ?></td>
                                                    <td><?php echo $result_product_list["productName"]; ?></td>
                                                    <td><?php echo $result_product_list["input_qty"]; ?></td>
                                                    <td><?php echo $result_product_list["input_remarks"]; ?></td>
                                                                                                        <td>
                                                        <div class="actions-group">
                                                            <a href="delete-input?Roowid=<?php echo $RowID;?>" class="action-link delete" title="Delete" onclick="return confirm('You want to delete confirm?');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php } ?>
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