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
        * { box-sizing: border-box; }
        .df-header {
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
            margin-bottom: 4px;
        }
        .df-header-left { display: flex; align-items: center; gap: 12px; min-width: 0; flex: 1; }
        .df-header-icon {
            width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 10px rgba(102,126,234,.3);
        }
        .df-header-icon .material-icons { color: #fff; font-size: 22px; }
        .df-header-text { min-width: 0; }
        .df-header h1 { margin: 0; font-size: 21px; font-weight: 700; color: #1f2937; line-height: 1.2; }
        .df-header-sub { margin: 2px 0 0; font-size: 12.5px; color: #9ca3af; overflow-wrap: break-word; }
        .df-manage-link {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fff; color: #667eea; border: 1px solid #e5e7eb;
            padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 13.5px;
            text-decoration: none; transition: all .2s; white-space: nowrap;
        }
        .df-manage-link:hover { background: #f8fafc; color: #667eea; border-color: #667eea; transform: translateY(-1px); }
        .df-manage-link .material-icons { font-size: 18px; }

        .card { border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(15,23,42,.06); }
        .card-body { padding: 24px; }

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

        .df-items-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .df-items-head .df-panel-title { margin-bottom: 0; }
        .df-items-actions { display: flex; gap: 8px; }
        .df-items-actions .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 4px; padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 600;
        }
        #add-row-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: #fff; }
        #add-row-btn:hover, #add-row-btn:focus { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,.35); color: #fff; }
        .df-items-actions .material-icons { font-size: 17px; }
        .df-row-count {
            font-size: 11.5px; font-weight: 600; color: #9ca3af; background: #f1f5f9;
            padding: 3px 9px; border-radius: 20px; margin-left: 8px;
        }

        /* Product rows: CSS grid on desktop/tablet, stacked cards on phones -- no horizontal scrolling either way. */
        .df-items-list { display: flex; flex-direction: column; gap: 10px; }
        .df-item-row {
            display: grid; grid-template-columns: 1fr 110px 44px; gap: 10px; align-items: start;
            background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; transition: border-color .15s, box-shadow .15s;
        }
        .df-item-row:hover { border-color: #d6dbe6; box-shadow: 0 2px 8px rgba(15,23,42,.05); }
        .df-item-row .df-field-label { display: none; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #94a3b8; margin-bottom: 4px; }
        .df-item-row .select2-container { width: 100% !important; }
        .row-remove-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 4px;
            background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px;
            width: 44px; height: 38px; padding: 0; cursor: pointer; transition: background .15s, transform .1s;
        }
        .row-remove-btn:hover { background: #fecaca; }
        .row-remove-btn:active { transform: scale(.95); }
        .row-remove-btn i { font-size: 19px; }
        .df-empty-hint { text-align: center; color: #9ca3af; font-size: 13px; padding: 18px 0; }

        .select2-container .select2-selection--single { border-radius: 8px !important; border: 1px solid #e2e8f0 !important; height: 38px !important; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }

        .df-submit-row { margin-top: 22px; }
        #df-submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: #fff;
            font-weight: 600; border-radius: 8px; padding: 10px 24px; display: inline-flex; align-items: center; gap: 6px;
            width: 100%; justify-content: center;
        }
        #df-submit-btn:hover, #df-submit-btn:focus { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,.35); color: #fff; }
        #df-submit-btn[disabled] { opacity: .65; pointer-events: none; transform: none; box-shadow: none; }

        @media (min-width: 480px) {
            #df-submit-btn { width: auto; }
        }

        /* Below ~560px, drop the grid and stack each row's fields as a labeled mini-card -- no scrollbar needed.
           Product spans the full width on its own line; Qty and Remove share the line below it so Remove
           stays a normal-sized button instead of stretching into a tall bar. */
        @media (max-width: 560px) {
            .card-body { padding: 16px; }
            .df-panel { padding: 14px; }
            .df-header h1 { font-size: 18px; }
            .df-manage-link span { display: none; }
            .df-items-head { margin-bottom: 14px; }
            .df-items-actions, .df-items-actions .btn { width: 100%; }
            .df-item-row {
                grid-template-columns: 1fr 44px; grid-template-areas: "product product" "qty remove";
                row-gap: 8px; align-items: end;
            }
            .df-item-row .df-product-cell { grid-area: product; }
            .df-item-row .df-qty-cell { grid-area: qty; }
            .df-item-row .row-remove-btn { grid-area: remove; }
            .df-item-row .df-field-label { display: block; }
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
                                    <div class="df-header-text">
                                        <h1>Add Demo/Free/Damage</h1>
                                        <p class="df-header-sub">Record products given as demo, free samples, or written off as damaged</p>
                                    </div>
                                </div>
                                <a href="demofree-manage.php" class="df-manage-link" title="Manage Demo/Free/Damage">
                                    <i class="material-icons">list_alt</i> <span>Manage Entries</span>
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

<div class="df-panel">
    <div class="df-items-head">
        <div class="df-panel-title">
            <i class="material-icons-outlined">inventory_2</i> Products
            <span class="df-row-count" id="rowCount">1 item</span>
        </div>
        <div class="df-items-actions">
            <button type="button" id="add-row-btn" class="btn" onclick="addRow()"><i class="material-icons">add</i><span class="label">Add Row</span></button>
        </div>
    </div>

    <div class="df-items-list" id="itemsList"></div>
    <template id="itemRowTemplate">
        <div class="df-item-row">
            <div class="df-product-cell">
                <div class="df-field-label">Product</div>
                <select required name="product_id[]" class="form-control product-select">
                    <option value="" hidden selected>Select Product</option>
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
            </div>
            <div class="df-qty-cell">
                <div class="df-field-label">Qty</div>
                <input type="number" placeholder="Qty" min="1" step="1" name="qty[]" class="form-control" required/>
            </div>
            <button type="button" class="row-remove-btn" onclick="removeRow(this)" title="Remove this product"><i class="material-icons">delete</i></button>
        </div>
    </template>
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

var MAX_ROWS = 100;

function updateRowCount() {
    var n = document.querySelectorAll('#itemsList .df-item-row').length;
    document.getElementById('rowCount').textContent = n + (n === 1 ? ' item' : ' items');
    document.getElementById('add-row-btn').disabled = (n >= MAX_ROWS);
}

function addRow() {
    var list = document.getElementById('itemsList');
    if (list.children.length >= MAX_ROWS) {
        alert('Maximum ' + MAX_ROWS + ' rows allowed.');
        return;
    }
    var tpl = document.getElementById('itemRowTemplate');
    var frag = tpl.content.cloneNode(true);
    var row = frag.querySelector('.df-item-row');
    list.appendChild(row);
    $(row).find('.product-select').select2({ width: '100%', placeholder: 'Select Product', dropdownAutoWidth: true });
    updateRowCount();
    return row;
}

function removeRow(btn) {
    var list = document.getElementById('itemsList');
    if (list.children.length <= 1) { alert('At least one product row is required.'); return; }
    var row = btn.closest('.df-item-row');
    var $select = $(row).find('.product-select');
    if ($select.hasClass('select2-hidden-accessible')) { $select.select2('destroy'); }
    row.parentNode.removeChild(row);
    updateRowCount();
}

$(document).ready(function() {
    addRow(); // seed the first, always-present row
});

document.querySelector('form').addEventListener('submit', function() {
    var btn = document.getElementById('df-submit-btn');
    // Defer disabling: the browser has already captured this button's name/value
    // for the submit by the time this handler runs, but disabling it synchronously
    // here (before that capture settles in some browsers) could drop add-record
    // from the POST, which demofree_action.php requires to process the save.
    setTimeout(function() {
        btn.disabled = true;
        btn.innerHTML = '<i class="material-icons">hourglass_top</i> Submitting…';
    }, 0);
});
</script>
</body>
</html>
