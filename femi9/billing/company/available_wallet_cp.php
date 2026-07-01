<?php include("checksession.php");
include("config.php");

$title = "CP Wallet History";
error_reporting(0);

// Load all active CPs for dropdown
$cp_list = [];
$cp_res = mysqli_query($db_conn, "SELECT id, cp_id, name, mobile FROM channel_partners WHERE is_active=1 ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($cp_res)) {
    $cp_list[] = $r;
}

$se_cpID = isset($_REQUEST['cp_id']) ? (int)base64_decode($_REQUEST['cp_id']) : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
        .select2-container--default .select2-selection--single { border:2px solid #e5e7eb; border-radius:9px; height:auto; padding:11px 15px; font-size:14px; font-family:'Poppins',sans-serif; transition:border-color 0.3s; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:1.5; padding:0; color:#1e293b; }
        .select2-container--default .select2-selection--single .select2-selection__placeholder { color:#94a3b8; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height:100%; top:0; right:10px; }
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.1); outline:none; }
        .select2-container--default .select2-results__option--highlighted[aria-selected] { background:#2563eb; }
        .select2-dropdown { border:2px solid #e5e7eb; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.12); font-family:'Poppins',sans-serif; font-size:14px; }
        .select2-search--dropdown { padding:8px; }
        .select2-search--dropdown .select2-search__field { border:2px solid #e5e7eb; border-radius:6px; padding:8px 10px; font-family:'Poppins',sans-serif; }
        .select2-results__option { padding:9px 14px; }
        .select2-container { width:100% !important; }
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
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td><?php echo $title; ?></td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">

                                        <!-- CP Select2 Dropdown -->
                                        <div class="row mb-4">
                                            <div class="col-md-5">
                                                <label class="form-label">Select Channel Partner</label>
                                                <select id="cpSelect" class="form-control">
                                                    <option value=""></option>
                                                    <?php foreach ($cp_list as $cp): ?>
                                                    <option value="<?php echo base64_encode($cp['id']); ?>"
                                                        data-cpid="<?php echo htmlspecialchars($cp['cp_id'] ?? ''); ?>"
                                                        data-mobile="<?php echo htmlspecialchars($cp['mobile'] ?? ''); ?>"
                                                        <?php echo ($se_cpID === (int)$cp['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cp['name']); ?> (<?php echo htmlspecialchars($cp['cp_id'] ?? ''); ?>)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div style="font-size:12px;color:#94a3b8;margin-top:4px;">Search by name, CP ID or mobile number</div>
                                            </div>
                                        </div>

<?php if ($se_cpID):
    $se_cpID_esc = mysqli_real_escape_string($db_conn, $se_cpID);

    $cp_row = mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT id, cp_id, name, mobile FROM channel_partners WHERE id='$se_cpID_esc' LIMIT 1"));

    if (!$cp_row): ?>
        <div class="alert alert-warning">Channel Partner not found.</div>
<?php else:
        $user_det_Name   = $cp_row['name'];
        $user_det_Mobile = $cp_row['mobile'];
        $user_det_CPID   = $cp_row['cp_id'];

        $total_credits = (float)(mysqli_fetch_array(mysqli_query($db_conn,
            "SELECT COALESCE(SUM(commission_amount),0)
             FROM wallet_monthly_sls_report
             WHERE user_type='channel_partner' AND user_id='$se_cpID_esc'"))[0] ?? 0);

        $total_debits = (float)(mysqli_fetch_array(mysqli_query($db_conn,
            "SELECT COALESCE(SUM(amount),0)
             FROM wallet_withdraw
             WHERE user_type='channel_partner' AND user_id='$se_cpID_esc' AND req_status='approved'"))[0] ?? 0);

        $available_balance = $total_credits - $total_debits;
?>
    <h4><?php echo strtoupper(htmlspecialchars($user_det_Name)); ?>
        <small class="text-muted" style="font-size:14px;">
            &nbsp;<?php echo htmlspecialchars($user_det_CPID); ?> &nbsp;|&nbsp; <?php echo htmlspecialchars($user_det_Mobile); ?>
        </small>
    </h4>

    <div class="container-fluid mt-3">
        <div class="row">
            <div class="col">
                <div class="card todo-container">
                    <div class="row">

                        <!-- Available Balance -->
                        <div class="col-xl-4 col-xxl-3">
                            <div class="todo-menu" style="text-align:center;">
                                <h5 class="todo-menu-title">Wallet - Available Amount</h5>
                                <ul class="list-unstyled todo-status-filter">
                                    <li>
                                        <a><i class="material-icons-outlined">wallet</i>
                                            <b>&#8377;<?php echo number_format($available_balance, 2, '.', ''); ?></b>
                                        </a>
                                    </li>
                                </ul>
                                <hr/>
                                <ul class="list-unstyled todo-status-filter" style="font-size:13px;">
                                    <li><span class="text-muted">Total Credited</span><br/>
                                        <b>&#8377;<?php echo number_format($total_credits, 2, '.', ''); ?></b></li>
                                    <li class="mt-1"><span class="text-muted">Total Withdrawn</span><br/>
                                        <b>&#8377;<?php echo number_format($total_debits, 2, '.', ''); ?></b></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Last 10 Credits -->
                        <div class="col-xl-4 col-xxl-9" style="border-right:1px solid #ddd !important;">
                            <div class="todo-list">
                                <h5 class="todo-menu-title">Last 10 Credit</h5>
                                <ul class="list-unstyled">
<?php
$credits_q = mysqli_query($db_conn,
    "SELECT * FROM wallet_monthly_sls_report
     WHERE user_type='channel_partner' AND user_id='$se_cpID_esc'
     ORDER BY from_date DESC LIMIT 10");
while ($cr = mysqli_fetch_array($credits_q)):
?>
                                    <li class="todo-item">
                                        <div class="todo-item-content">
                                            <span class="todo-item-title">
                                                &#8377;<?php echo number_format($cr['commission_amount'], 2, '.', ''); ?>
                                                <span class="badge badge-style-light rounded-pill badge-success">Credit</span>
                                            </span>
                                            <span><?php echo htmlspecialchars($cr['commission_type']); ?>
                                                (<?php echo htmlspecialchars($cr['commission_percentage']); ?>%)</span><br/>
                                            <?php if (!empty($cr['remarks'])): ?>
                                                <span><?php echo htmlspecialchars($cr['remarks']); ?></span><br/>
                                            <?php endif; ?>
                                            <span class="todo-item-subtitle">
                                                <?php echo htmlspecialchars($cr['month']); ?>, <?php echo htmlspecialchars($cr['year']); ?>
                                            </span>
                                        </div>
                                    </li>
<?php endwhile; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- Last 10 Debits -->
                        <div class="col-xl-4 col-xxl-9">
                            <div class="todo-list">
                                <h5 class="todo-menu-title">Last 10 Debit</h5>
                                <ul class="list-unstyled">
<?php
$debits_q = mysqli_query($db_conn,
    "SELECT * FROM wallet_withdraw
     WHERE user_type='channel_partner' AND user_id='$se_cpID_esc'
     ORDER BY date DESC LIMIT 10");
while ($dr = mysqli_fetch_array($debits_q)):
?>
                                    <li class="todo-item">
                                        <div class="todo-item-content">
                                            <span class="todo-item-title">
                                                &#8377;<?php echo number_format($dr['amount'], 2, '.', ''); ?>
<?php if ($dr['req_status'] == 'pending'): ?>
                                                <span class="badge badge-style-light rounded-pill badge-danger">Pending</span>
<?php else: ?>
                                                <span class="badge badge-style-light rounded-pill badge-primary">Debit</span>
<?php endif; ?>
                                            </span>
                                            <span class="todo-item-subtitle">
                                                <?php echo date("d/m/Y", strtotime($dr['date'])); ?>,
                                                <?php echo date("g:i A", strtotime($dr['time'])); ?>
                                            </span>
                                        </div>
                                    </li>
<?php endwhile; ?>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; endif; ?>

                                    </div><!-- card-body -->
                                </div><!-- card -->
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
    <script>
    $(function () {
        function cpMatcher(params, data) {
            if (!params.term || params.term.trim() === '') return data;
            var q = params.term.trim().toLowerCase();
            if ((data.text || '').toLowerCase().indexOf(q) > -1) return data;
            if (data.element) {
                var cpid   = (data.element.getAttribute('data-cpid')   || '').toLowerCase();
                var mobile = (data.element.getAttribute('data-mobile')  || '').toLowerCase();
                if (cpid.indexOf(q) > -1 || mobile.indexOf(q) > -1) return data;
            }
            return null;
        }

        $('#cpSelect').select2({
            placeholder: 'Search by name, CP ID or mobile…',
            allowClear: true,
            matcher: cpMatcher
        }).on('change', function () {
            var val = $(this).val();
            if (val) {
                window.location = 'available_wallet_cp?cp_id=' + encodeURIComponent(val);
            } else {
                window.location = 'available_wallet_cp';
            }
        });
    });
    </script>
</body>
</html>
