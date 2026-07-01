<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$advBalance = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Demo/Free/Damage : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
                                    <td>Add Demo/Free/Damage</td>
                                    <td><a href="demofree-manage.php" title="Manage Demo/Free/Damage">&#9776;</a></td>
                                </tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
<?php
if (isset($_SESSION['errorMessage'])) {
    $errorMessage = $_SESSION['errorMessage'];
?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>Swal.fire({ icon:'error', title:'Warning', text:'<?php echo $errorMessage; ?>', confirmButtonText:'OK' });</script>
<?php unset($_SESSION['errorMessage']); }
unset($_SESSION['sucMessage']);
?>
<form action="demofree_action.php" method="post" enctype="multipart/form-data" onsubmit="return confirm('Please make a confirm!');">

<?php
function GeraHashTP($qtd) {
    $Caracteres = '123456789';
    $QuantidadeCaracteres = strlen($Caracteres) - 1;
    $Hash = NULL;
    for ($x = 1; $x <= $qtd; $x++) {
        $Posicao = rand(0, $QuantidadeCaracteres);
        $Hash .= substr($Caracteres, $Posicao, 1);
    }
    return $Hash;
}
$randum_number = GeraHashTP(5);
$temp_date = date("dmy");
$temp_time = date("gis");
$tempid = "" . $randum_number . "DFD/" . $temp_date . "/" . $temp_time . "";
?>

<input type="hidden" name="tempid" value="<?php echo $tempid; ?>">
<input type="hidden" name="usertype" value="<?php echo $Login_user_TYPEvl; ?>">
<input type="hidden" name="userid" value="<?php echo $Login_user_IDvl; ?>">

<div class="example-container">
<div class="example-content">

<label class="form-label">Category*</label>
<select required name="category" class="form-control">
    <option value="" hidden>Select</option>
    <option>Demo</option>
    <option>Free</option>
    <option>Damage</option>
</select>
<br/>

<label class="form-label">Date*</label>
<input type="date" id="bookingDate" required name="date" value="<?php echo date("Y-m-d"); ?>" class="form-control">
<br/>

<label class="form-label">Remarks*</label>
<textarea required name="remarks" class="form-control"></textarea>
<br/>

<script>
function addRow(tableID) {
    var table = document.getElementById(tableID);
    var rowCount = table.rows.length;
    if (rowCount < 100) {
        var row = table.insertRow(rowCount);
        var colCount = table.rows[0].cells.length;
        for (var i = 0; i < colCount; i++) {
            var newcell = row.insertCell(i);
            newcell.innerHTML = table.rows[0].cells[i].innerHTML;
        }
    } else {
        alert("Maximum 100 rows allowed.");
    }
}
function deleteRow(tableID) {
    var table = document.getElementById(tableID);
    var rowCount = table.rows.length;
    for (var i = 0; i < rowCount; i++) {
        var row = table.rows[i];
        var chkbox = row.cells[0].childNodes[0];
        if (null != chkbox && true == chkbox.checked) {
            if (rowCount <= 1) { alert("Cannot Remove all Fields."); break; }
            table.deleteRow(i); rowCount--; i--;
        }
    }
}
</script>

<p>
    <button type="button" class="btn btn-primary btn-burger" onclick="addRow('dataTable')"><i class="material-icons">add</i></button>
    <button type="button" class="btn btn-danger btn-burger" onclick="deleteRow('dataTable')"><i class="material-icons">delete_outline</i></button>
</p>

<table id="dataTable" border="0">
    <tr>
        <td><input type="checkbox" name="chk[]"/></td>
        <td>
            <select required name="product_id[]" class="form-control">
                <option value="" hidden>Select Product</option>
                <?php
                $tp_id_esc = (int)$Login_user_IDvl;
                $fetch_product_list = $db_conn->prepare(
                    "SELECT p.id, p.productName, tps.closing_qty
                     FROM products p
                     JOIN territory_partner_stock tps ON tps.product_id = p.id AND tps.territory_partner_id = ?
                     WHERE tps.closing_qty > 0
                     ORDER BY p.productName"
                );
                $fetch_product_list->bind_param('i', $tp_id_esc);
                $fetch_product_list->execute();
                $product_result = $fetch_product_list->get_result();
                while ($result_product_list = $product_result->fetch_assoc()) {
                ?>
                <option value="<?php echo $result_product_list['id']; ?>"><?php echo htmlspecialchars($result_product_list['productName']); ?> (Stock: <?php echo $result_product_list['closing_qty']; ?>)</option>
                <?php }
                $fetch_product_list->close(); ?>
            </select>
        </td>
        <td><input type="number" placeholder="Qty" min="0" name="qty[]" class="form-control" required/></td>
    </tr>
</table>
<br/>

<button type="submit" name="add-record" class="btn btn-primary"><i class="material-icons">add</i>Submit</button>

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
<script>
flatpickr("#bookingDate", { dateFormat: "Y-m-d", maxDate: "today" });
</script>
</body>
</html>
