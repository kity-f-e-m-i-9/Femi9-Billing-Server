<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('stock_transfers');
require_once("include/GodownAccess.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$godowns  = $db_conn->query("SELECT id, gname, contact FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname")->fetch_all(MYSQLI_ASSOC);
$cp_list = $db_conn->query("SELECT id, cp_id, name FROM channel_partners WHERE is_active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Godown to Location Transfer : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        body { font-family: 'Poppins', sans-serif; }

        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
            border-radius: 10px 10px 0 0 !important;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-header-title {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header-title i { font-size: 18px; color: #667eea; }
        .card-body { padding: 20px; }

        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
            font-size: 13px;
        }
        .form-label .req { color: #ef4444; }
        .form-control, .form-select {
            border-radius: 7px;
            border: 1px solid #d1d5db;
            padding: 9px 12px;
            font-size: 13.5px;
            font-family: 'Poppins', sans-serif;
            transition: border-color .2s, box-shadow .2s;
            color: #374151;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,.15);
            outline: none;
        }
        .form-control[readonly] {
            background: #f8f9fa;
            color: #9ca3af;
            cursor: not-allowed;
        }
        .field-hint {
            font-size: 11.5px;
            color: #9ca3af;
            margin-top: 4px;
        }

        /* Transfer direction badge */
        .transfer-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg,#eff6ff,#f0ebff);
            border: 1px solid #c7d2fe;
            color: #4338ca;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
        }
        .transfer-badge i { font-size: 15px; }

        /* Product table */
        .product-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .product-table thead th {
            background: #f8fafc;
            color: #6b7280;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            padding: 10px 14px;
            border-top: 1px solid #f0f0f0;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }
        .product-table tbody td {
            padding: 9px 14px;
            vertical-align: middle;
            border-bottom: 1px solid #f4f4f4;
            font-size: 13px;
        }
        .product-table tbody tr:last-child td { border-bottom: none; }
        .product-table tbody tr:hover td { background: #fafafa; }

        .row-num {
            width: 32px;
            height: 32px;
            background: #f3f4f6;
            color: #6b7280;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }
        .avail-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 600;
            background: #d1fae5;
            color: #065f46;
        }
        .avail-pill.zero { background: #f3f4f6; color: #9ca3af; }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: #9ca3af;
        }
        .empty-state i { font-size: 52px; margin-bottom: 12px; display: block; opacity: .5; }
        .empty-state p { font-size: 13.5px; margin: 0; }

        /* Alert */
        .alert { border-radius: 8px; border: none; font-size: 13.5px; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }

        /* Buttons */
        .btn-transfer {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 10px 24px;
            border-radius: 7px;
            font-weight: 500;
            font-size: 14px;
            color: #fff;
            transition: all .25s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-transfer:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(102,126,234,.4); color: #fff; }
        .btn-transfer:disabled { opacity: .5; transform: none; box-shadow: none; cursor: not-allowed; }
        .btn-cancel {
            padding: 10px 20px;
            border-radius: 7px;
            font-size: 14px;
            font-weight: 500;
        }
        .btn-add-row {
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            padding: 5px 14px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Select2 overrides */
        .select2-container--default .select2-selection--single {
            border-radius: 7px;
            border: 1px solid #d1d5db;
            height: auto;
            padding: 9px 12px;
            font-size: 13.5px;
            font-family: 'Poppins', sans-serif;
            transition: border-color .2s, box-shadow .2s;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding: 0;
            color: #374151;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #9ca3af; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 100%; top: 0; right: 10px; }
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,.15);
            outline: none;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .select2-dropdown {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            font-family: 'Poppins', sans-serif;
            font-size: 13.5px;
        }
        .select2-search--dropdown { padding: 8px; }
        .select2-search--dropdown .select2-search__field {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 7px 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
        }
        .select2-results__option { padding: 8px 12px; }
        .select2-container { width: 100% !important; }
        .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-right: 24px;
            font-size: 16px;
            color: #9ca3af;
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

                    <!-- Page Header -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble"><tr>
                                        <td>Godown → Partner Location</td>
                                        <td><a href="manage-pl-godown-transfers" title="All Transfers">&#9776;</a></td>
                                    </tr></table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_GET['error'])): ?>
                    <div class="row mb-2"><div class="col">
                        <div class="alert alert-danger">
                            <i class="material-icons-outlined" style="vertical-align:middle;font-size:18px;">error_outline</i>
                            <?php $e=$_GET['error'];
                            echo $e==='insufficient' ? 'Insufficient stock at the godown for one or more products.'
                               : ($e==='missing'     ? 'Please fill in all required fields.'
                               : ($e==='noproducts'  ? 'Please add at least one product with a valid quantity.'
                               : 'An error occurred. Please try again.')); ?>
                        </div>
                    </div></div>
                    <?php endif; ?>

                    <form action="pl-godown-transfer-action" method="post" id="transferForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="transfer_type" value="godown_to_location">
                        <input type="hidden" name="note" value="">

                        <!-- Transfer Details Card -->
                        <div class="card">
                            <div class="card-header">
                                <span class="card-header-title">
                                    <i class="material-icons-outlined">swap_horiz</i>
                                    Transfer Details
                                </span>
                                <span class="transfer-badge">
                                    <i class="material-icons" style="font-size:14px;">warehouse</i>
                                    Godown → Location
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">

                                    <div class="col-md-4">
                                        <label class="form-label">Source Godown <span class="req">*</span></label>
                                        <select name="godown_id" id="godownSelect" class="form-control" required>
                                            <option value=""></option>
                                            <?php foreach ($godowns as $g): ?>
                                            <option value="<?php echo $g['id']; ?>"
                                                data-contact="<?php echo htmlspecialchars($g['contact'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($g['gname']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="field-hint">Search by godown name or phone number</div>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Destination Channel Partner <span class="req">*</span></label>
                                        <select name="cp_id" id="locationSelect" class="form-control" required>
                                            <option value=""></option>
                                            <?php foreach ($cp_list as $cp): ?>
                                            <option value="<?php echo $cp['id']; ?>"
                                                data-code="<?php echo htmlspecialchars($cp['cp_id']); ?>">
                                                <?php echo htmlspecialchars($cp['name']); ?> (<?php echo htmlspecialchars($cp['cp_id']); ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="field-hint">Search by name or CP ID</div>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Transfer Date <span class="req">*</span></label>
                                        <input type="date" name="transfer_date" class="form-control"
                                               value="<?php echo date('Y-m-d'); ?>"
                                               max="<?php echo date('Y-m-d'); ?>" required>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Reference Number</label>
                                        <input type="text" name="ref_number" class="form-control"
                                               maxlength="50" placeholder="Auto-generated" readonly>
                                        <div class="field-hint">Generated after transfer</div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Products Card -->
                        <div class="card">
                            <div class="card-header">
                                <span class="card-header-title">
                                    <i class="material-icons-outlined">inventory_2</i>
                                    Products to Transfer
                                </span>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-add-row" id="addRowBtn" style="display:none;">
                                    <i class="material-icons" style="font-size:16px;">add</i> Add Row
                                </button>
                            </div>
                            <div class="card-body p-0">

                                <div id="productSection" style="display:none;">
                                    <div style="overflow-x:auto;">
                                        <table class="product-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:48px;">#</th>
                                                    <th style="min-width:240px;">Product</th>
                                                    <th style="min-width:110px;">Available Qty</th>
                                                    <th style="min-width:130px;">Transfer Qty</th>
                                                    <th style="width:48px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="productBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <div id="productPlaceholder" class="empty-state">
                                    <i class="material-icons-outlined">inventory</i>
                                    <p>Select a source godown to load available products</p>
                                </div>

                            </div>
                        </div>

                        <!-- Action Row -->
                        <div class="row mb-4">
                            <div class="col">
                                <button type="submit" class="btn btn-transfer" id="submitBtn" disabled>
                                    <i class="material-icons" style="font-size:18px;">swap_horiz</i>
                                    Transfer Stock
                                </button>
                                <a href="manage-pl-godown-transfers" class="btn btn-secondary btn-cancel ms-2">
                                    Cancel
                                </a>
                            </div>
                        </div>

                    </form>

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
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script>
$(document).ready(function() {
    function godownMatcher(params, data) {
        if (!params.term || !params.term.trim()) return data;
        var q       = params.term.trim().toLowerCase();
        var text    = (data.text || '').toLowerCase();
        var contact = ($(data.element).data('contact') || '').toLowerCase();
        return (text.indexOf(q) > -1 || contact.indexOf(q) > -1) ? data : null;
    }
    function locationMatcher(params, data) {
        if (!params.term || !params.term.trim()) return data;
        var q    = params.term.trim().toLowerCase();
        var text = (data.text || '').toLowerCase();
        var code = ($(data.element).data('code') || '').toLowerCase();
        return (text.indexOf(q) > -1 || code.indexOf(q) > -1) ? data : null;
    }
    $('#godownSelect').select2({ placeholder: 'Search godown…', allowClear: true, matcher: godownMatcher });
    $('#locationSelect').select2({ placeholder: 'Search channel partner…', allowClear: true, matcher: locationMatcher });
});
</script>
<script>
(function ($) {
    var products = [];
    var rowCount = 0;

    $('#godownSelect').on('change', function () {
        var gid = $(this).val();
        products = []; rowCount = 0;
        $('#productBody').empty();
        if (!gid) {
            $('#productSection').hide();
            $('#addRowBtn').hide();
            $('#productPlaceholder').show().html('<i class="material-icons-outlined" style="font-size:52px;display:block;margin-bottom:12px;opacity:.5;">inventory</i><p style="font-size:13.5px;color:#9ca3af;margin:0;">Select a source godown to load available products</p>');
            $('#submitBtn').prop('disabled', true);
            return;
        }
        $('#productPlaceholder').show().html('<i class="material-icons-outlined" style="font-size:52px;display:block;margin-bottom:12px;opacity:.4;animation:spin 1s linear infinite;">refresh</i><p style="font-size:13.5px;color:#9ca3af;margin:0;">Loading products…</p>');
        $.getJSON('get-godown-products.php?godown_id=' + gid, function (data) {
            products = data;
            if (!products.length) {
                $('#productPlaceholder').show().html('<i class="material-icons-outlined" style="font-size:52px;display:block;margin-bottom:12px;color:#f59e0b;opacity:.7;">warning_amber</i><p style="font-size:13.5px;color:#9ca3af;margin:0;">No stock available at this godown</p>');
                $('#productSection').hide();
                $('#addRowBtn').hide();
                $('#submitBtn').prop('disabled', true);
                return;
            }
            $('#productPlaceholder').hide();
            $('#productSection').show();
            $('#addRowBtn').show();
            addRow();
            $('#submitBtn').prop('disabled', false);
        }).fail(function () {
            $('#productPlaceholder').show().html('<i class="material-icons-outlined" style="font-size:52px;display:block;margin-bottom:12px;color:#ef4444;opacity:.7;">error_outline</i><p style="font-size:13.5px;color:#9ca3af;margin:0;">Failed to load products. Please try again.</p>');
        });
    });

    function buildOptions() {
        var o = '<option value="">— Select Product —</option>';
        $.each(products, function (_, p) {
            o += '<option value="' + p.product_id + '" data-avail="' + p.available_qty + '">' + escHtml(p.productName) + '</option>';
        });
        return o;
    }

    function addRow() {
        rowCount++;
        var rn = rowCount;
        var $tr = $('<tr class="product-row" data-row="' + rn + '"></tr>');
        $tr.html(
            '<td><span class="row-num">' + rn + '</span></td>' +
            '<td><select name="product_id[]" class="form-control form-control-sm prod-sel" required>' + buildOptions() + '</select></td>' +
            '<td><span class="avail-pill zero">—</span></td>' +
            '<td><input type="number" name="qty[]" class="form-control form-control-sm" min="1" placeholder="0" required></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger remove-btn" style="padding:3px 7px;"><i class="material-icons" style="font-size:15px;vertical-align:middle;">close</i></button></td>'
        );
        $('#productBody').append($tr);
        $tr.find('.prod-sel').on('change', function () {
            var avail = parseInt($(this).find('option:selected').data('avail')) || 0;
            var $pill = $tr.find('.avail-pill');
            if (avail > 0) {
                $pill.text('Avail: ' + avail).removeClass('zero');
            } else {
                $pill.text('—').addClass('zero');
            }
            $tr.find('input[name="qty[]"]').attr('max', avail).val('');
        });
        $tr.find('.remove-btn').on('click', function () {
            if ($('#productBody .product-row').length <= 1) return;
            $tr.remove();
            renumber();
        });
    }

    function renumber() {
        $('#productBody .product-row').each(function (i) {
            $(this).find('.row-num').text(i + 1);
        });
    }

    $('#addRowBtn').on('click', function () { addRow(); });

    $('#transferForm').on('submit', function (e) {
        var ok = true;
        $('#productBody .product-row').each(function () {
            var pid = $(this).find('.prod-sel').val();
            var qty = parseInt($(this).find('input[name="qty[]"]').val());
            var max = parseInt($(this).find('input[name="qty[]"]').attr('max')) || 0;
            if (!pid || !qty || qty < 1) { ok = false; return false; }
            if (qty > max) {
                alert('Quantity exceeds available stock for ' + $(this).find('.prod-sel option:selected').text());
                ok = false; return false;
            }
        });
        if (!ok) e.preventDefault();
    });

    function escHtml(s) { return $('<div>').text(s).html(); }
}(jQuery));
</script>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
</body>
</html>
