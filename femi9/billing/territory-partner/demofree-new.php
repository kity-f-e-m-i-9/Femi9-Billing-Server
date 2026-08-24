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
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <style>
        .df-header {
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
            margin-bottom: 4px;
        }
        .df-header-left { display: flex; align-items: center; gap: 12px; }
        .df-header-icon {
            width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 10px rgba(102,126,234,.3);
        }
        .df-header-icon .material-icons { color: #fff; font-size: 22px; }
        .df-header h1 { margin: 0; font-size: 21px; font-weight: 700; color: #1f2937; line-height: 1.2; }
        .df-header-sub { margin: 2px 0 0; font-size: 12.5px; color: #9ca3af; }
        .df-manage-link {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fff; color: #667eea; border: 1px solid #e5e7eb;
            padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 13.5px;
            text-decoration: none; transition: all .2s;
        }
        .df-manage-link:hover { background: #f8fafc; color: #667eea; border-color: #667eea; transform: translateY(-1px); }
        .df-manage-link .material-icons { font-size: 18px; }

        .card { border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(15,23,42,.06); }

        .df-panel {
            background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 12px;
            padding: 20px; margin-bottom: 20px; transition: border-color .2s;
        }
        .df-panel:hover { border-color: #e2e8f0; }
        .df-panel-title {
            font-size: 12.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #6b7280;
            margin-bottom: 16px; display: flex; align-items: center; gap: 6px;
        }
        .df-panel-title .material-icons-outlined { font-size: 17px; color: #667eea; }
        .df-panel .form-label { font-size: 11.5px; font-weight: 600; color: #6b7280; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .4px; }
        .df-panel .form-control { border-radius: 8px; border: 1px solid #e2e8f0; }
        .df-panel .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.12); }
        .df-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .df-row > div { flex: 1; min-width: 220px; }
        .df-row:last-child { margin-bottom: 0; }

        .df-items-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .df-items-head .df-panel-title { margin-bottom: 0; }
        .df-items-actions { display: flex; gap: 8px; }
        .df-items-actions .btn {
            display: inline-flex; align-items: center; gap: 4px; padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 600;
        }
        #add-row-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: #fff; }
        #add-row-btn:hover, #add-row-btn:focus { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,.35); color: #fff; }
        #del-row-btn { background: #fff; border: 1px solid #fca5a5; color: #dc2626; }
        #del-row-btn:hover, #del-row-btn:focus { background: #fef2f2; color: #dc2626; }
        .df-items-actions .material-icons { font-size: 17px; }

        .df-table-wrap { border: 1px solid #f1f5f9; border-radius: 10px; overflow: hidden; }
        .dataTable-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        #dataTable { width: 100%; margin: 0; border-collapse: collapse; }
        .df-col-labels {
            display: flex; background: #f8fafc; border-bottom: 2px solid #f1f5f9;
            color: #94a3b8; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
            padding: 10px 12px;
        }
        .df-col-labels span:first-child { width: 40px; flex-shrink: 0; }
        .df-col-labels span:nth-child(2) { flex: 1; }
        .df-col-labels span:last-child { min-width: 100px; }
        #dataTable tr { transition: background .15s; }
        #dataTable tr:hover td { background: #fafbff; }
        #dataTable td { padding: 10px 12px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; background: #fff; }
        #dataTable tr:last-child td { border-bottom: none; }
        #dataTable td:first-child { width: 40px; text-align: center; }
        #dataTable td:last-child { min-width: 100px; }
        #dataTable input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: #667eea; }
        #dataTable .form-control { border-radius: 8px; border: 1px solid #e2e8f0; }
        .df-row-count {
            font-size: 11.5px; font-weight: 600; color: #9ca3af; background: #f1f5f9;
            padding: 3px 9px; border-radius: 20px; margin-left: 8px;
        }
        .select2-container { min-width: 180px; }
        .select2-container .select2-selection--single { border-radius: 8px !important; border: 1px solid #e2e8f0 !important; height: 38px !important; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }

        .df-submit-row { margin-top: 22px; }
        #df-submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: #fff;
            font-weight: 600; border-radius: 8px; padding: 10px 24px; display: inline-flex; align-items: center; gap: 6px;
        }
        #df-submit-btn:hover, #df-submit-btn:focus { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,.35); color: #fff; }

        @media (max-width: 576px) {
            #dataTable, .df-col-labels { min-width: 480px; }
            .df-items-actions .btn span.label { display: none; }
        }
    </style>
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
                            <div class="page-description df-header">
                                <div class="df-header-left">
                                    <div class="df-header-icon"><i class="material-icons">redeem</i></div>
                                    <div>
                                        <h1>Add Demo/Free/Damage</h1>
                                        <p class="df-header-sub">Record products given as demo, free samples, or written off as damaged</p>
                                    </div>
                                </div>
                                <a href="demofree-manage.php" class="df-manage-link" title="Manage Demo/Free/Damage">
                                    <i class="material-icons">list_alt</i> Manage Entries
                                </a>
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

<div class="df-panel">
    <div class="df-panel-title"><i class="material-icons-outlined">description</i> Entry Details</div>
    <div class="df-row">
        <div>
            <label class="form-label">Category*</label>
            <select required name="category" class="form-control">
                <option value="" hidden>Select</option>
                <option>Demo</option>
                <option>Free</option>
                <option>Damage</option>
            </select>
        </div>
        <div>
            <label class="form-label">Date*</label>
            <input type="date" id="bookingDate" required name="date" value="<?php echo date("Y-m-d"); ?>" class="form-control">
        </div>
    </div>
    <div class="df-row">
        <div>
            <label class="form-label">Remarks*</label>
            <textarea required name="remarks" class="form-control" rows="2"></textarea>
        </div>
    </div>
</div>

<script>
function reinitProductSelects() {
    // select2 wraps each select in extra markup, so strip it back to the
    // plain <select> before cloning a row and only re-wrap once the clone
    // is in place -- otherwise the clone duplicates the widget, not the field.
    $('select[name="product_id[]"]').each(function() {
        if ($(this).hasClass('select2-hidden-accessible')) { $(this).select2('destroy'); }
    });
    $('select[name="product_id[]"]').select2({ width: '100%', placeholder: 'Select Product' });
}

function addRow(tableID) {
    var table = document.getElementById(tableID);
    $('select[name="product_id[]"]').each(function() {
        if ($(this).hasClass('select2-hidden-accessible')) { $(this).select2('destroy'); }
    });
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
    $('select[name="product_id[]"]').select2({ width: '100%', placeholder: 'Select Product' });
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

<div class="df-panel">
    <div class="df-items-head">
        <div class="df-panel-title"><i class="material-icons-outlined">inventory_2</i> Products</div>
        <div class="df-items-actions">
            <button type="button" id="add-row-btn" class="btn" onclick="addRow('dataTable')"><i class="material-icons">add</i><span class="label">Add Row</span></button>
            <button type="button" id="del-row-btn" class="btn" onclick="deleteRow('dataTable')"><i class="material-icons">delete_outline</i><span class="label">Remove</span></button>
        </div>
    </div>

    <div class="df-table-wrap">
    <div class="dataTable-scroll">
    <div class="df-col-labels"><span></span><span>Product</span><span>Qty</span></div>
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
                         WHERE tps.closing_qty > 0 AND (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL)
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
    </div>
    </div>
</div>

<div class="df-submit-row">
    <button type="submit" name="add-record" id="df-submit-btn"><i class="material-icons">add</i> Submit</button>
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
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr("#bookingDate", { dateFormat: "Y-m-d", maxDate: "today" });
$(document).ready(function() { reinitProductSelects(); });
</script>
</body>
</html>
