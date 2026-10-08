<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$uid  = mysqli_real_escape_string($db_conn, $Login_user_IDvl);
$utype = mysqli_real_escape_string($db_conn, $Login_user_TYPEvl);

// Available balance = total credits - approved withdrawals
$totalCredits   = (float)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COALESCE(SUM(commission_amount),0) FROM wallet_monthly_sls_report WHERE refer_by_usertype='$utype' AND refer_by_userid='$uid'"))[0] ?? 0);
$totalWithdrawn = (float)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COALESCE(SUM(amount),0) FROM wallet_withdraw WHERE user_type='$utype' AND user_id='$uid' AND req_status='approved'"))[0] ?? 0);
$walletBalance  = $totalCredits - $totalWithdrawn;

// TDS percentage
$tds_row = mysqli_fetch_array(mysqli_query($db_conn, "SELECT tds_percentage FROM admin_settings WHERE id='1'"));
$tds_percentage = $tds_row['tds_percentage'] ?? 0;

// Bank details from profile
$profileRow = mysqli_fetch_assoc(mysqli_query($db_conn,
    "SELECT acname, acnumber, bankname, ifsc, pannumber FROM users_profile WHERE user_tempid='$uid' AND usertype='$utype' LIMIT 1"));

// Random request ID generator
function GeraHash_tp(int $len): string {
    $chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $result = '';
    for ($i = 0; $i < $len; $i++) $result .= $chars[rand(0, strlen($chars) - 1)];
    return $result;
}
$tempID = GeraHash_tp(20) . date("dmyHis");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wallet : <?php echo $business_name; ?></title>
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
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>

        <?php if (isset($_SESSION['successMessage'])): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>Swal.fire({icon:'success',title:'Success',text:'<?php echo $_SESSION['successMessage']; ?>',confirmButtonText:'OK'});</script>
        <?php unset($_SESSION['successMessage']); endif; ?>

        <?php if (isset($_SESSION['errorMessage'])): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>Swal.fire({icon:'error',title:'Error',text:'<?php echo $_SESSION['errorMessage']; ?>',confirmButtonText:'OK'});</script>
        <?php unset($_SESSION['errorMessage']); endif; ?>

        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col">
                            <div class="card todo-container wh-card">
                                <div class="row wh-row">

                                    <!-- Balance Panel -->
                                    <div class="col-12 wh-page">
                                        <div class="todo-menu wh-menu" style="padding:0;text-align:left;">
                                            <div class="wh-balance-box"><div class="wh-bal"><span class="wh-bal-label">Wallet - Available Amount</span><span class="wh-bal-amt">&#8377;<?php echo inr_format($walletBalance, 2); ?></span></div><div class="wh-bal-action">

                                            <?php if ($walletBalance > 0): ?>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                                                <button type="button" class="btn btn-primary">Send Withdraw Request</button>
                                            </a>
                                            <?php endif; ?></div></div>

                                            <div class="wh-note"><b>Note:</b> <?php echo $tds_percentage; ?>% TDS will be deducted for all withdrawals by Femi9, and it will be reflected in your PAN card only if it is linked with your aadhar.</div>

                                            <!-- Withdraw Modal -->
                                            <div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="withdrawModalLabel">Wallet Withdraw Request</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="post" onsubmit="return confirm('Please confirm your request!');" action="wallet_request_process.php">
                                                            <input type="hidden" name="req_id" value="<?php echo htmlspecialchars($tempID); ?>">
                                                            <input type="hidden" name="req_status" value="pending">
                                                            <input type="hidden" name="user_type" value="<?php echo htmlspecialchars($Login_user_TYPEvl); ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($Login_user_IDvl); ?>">
                                                            <div class="example-content" style="padding:20px;">
                                                                <div class="form-floating mb-3">
                                                                    <input type="number" min="100" max="<?php echo $walletBalance; ?>" name="request_amount" required placeholder="Amount" class="form-control">
                                                                    <label>Amount</label>
                                                                </div>
                                                                <div class="form-floating mb-3">
                                                                    <input type="text" value="<?php echo htmlspecialchars($profileRow['acname'] ?? ''); ?>" name="acname" required placeholder="A/C Name" class="form-control">
                                                                    <label>A/C Name</label>
                                                                </div>
                                                                <div class="form-floating mb-3">
                                                                    <input type="text" value="<?php echo htmlspecialchars($profileRow['acnumber'] ?? ''); ?>" name="acnumber" required placeholder="A/C Number" class="form-control">
                                                                    <label>A/C Number</label>
                                                                </div>
                                                                <div class="form-floating mb-3">
                                                                    <input type="text" value="<?php echo htmlspecialchars($profileRow['bankname'] ?? ''); ?>" name="bankname" required placeholder="Bank Name" class="form-control">
                                                                    <label>Bank Name</label>
                                                                </div>
                                                                <div class="form-floating mb-3">
                                                                    <input type="text" value="<?php echo htmlspecialchars($profileRow['ifsc'] ?? ''); ?>" name="ifsc" required placeholder="IFS Code" class="form-control">
                                                                    <label>IFS Code</label>
                                                                </div>
                                                                <div class="form-floating mb-3">
                                                                    <input type="text" value="<?php echo htmlspecialchars($profileRow['pannumber'] ?? ''); ?>" name="pannumber" required placeholder="PAN Number" class="form-control">
                                                                    <label>PAN Number</label>
                                                                </div>
                                                                <button type="submit" name="sent_money_request" class="btn btn-primary">
                                                                    <i class="material-icons">send</i> Send
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Credit History -->
<?php
$wh_to   = (isset($_GET['to_date'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date']))   ? $_GET['to_date']   : date('Y-m-d');
$wh_from = (isset($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'])) ? $_GET['from_date'] : date('Y-m-d', strtotime('-7 months'));
if ($wh_from > $wh_to) { $t = $wh_from; $wh_from = $wh_to; $wh_to = $t; }
?>
<style>
.wh-page{padding:0}
.wh-balance-box{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;padding:22px 26px;border-radius:12px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff}
.wh-bal{display:flex;flex-direction:column;gap:4px}
.wh-bal-label{font-size:12px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;opacity:.85}
.wh-bal-amt{font-size:32px;font-weight:700;line-height:1.1}
.wh-bal-action .btn{background:#fff;color:#4f46e5;border:0;font-weight:600;padding:10px 22px;border-radius:8px}
.wh-note{margin:14px 0 0;padding:12px 16px;border-radius:10px;background:#fff7e6;border:1px solid #ffe2a8;color:#8a5a00;font-size:13px;line-height:1.5}
.wh-section-title{font-size:13px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#6b7280;margin:26px 0 10px}
.wh-filter{display:flex;flex-wrap:wrap;align-items:flex-end;gap:14px;padding:14px 18px;background:#f6f8fb;border:1px solid #e4e8ef;border-radius:10px}
.wh-filter .wh-f{display:flex;flex-direction:column;gap:4px}
.wh-filter label{font-size:12px;font-weight:600;color:#6b7280;margin:0}
.wh-filter input[type=date]{height:40px;min-width:170px;border-radius:8px}
.wh-filter .wh-actions{display:flex;gap:8px;margin-left:auto}
.wh-filter .btn{height:40px;border-radius:8px;display:inline-flex;align-items:center}
.wh-col{margin-top:22px}
.wh-col .todo-list{height:100%}
.wh-col .todo-menu-title{display:flex;align-items:center;gap:8px;font-size:16px;font-weight:700;padding:0 0 10px;margin:0 0 14px;border-bottom:2px solid #eef0f4}
.wh-col .todo-menu-title:before{content:"";width:10px;height:10px;border-radius:50%;background:#22c55e}
.wh-col.wh-debit .todo-menu-title:before{background:#4f46e5}
.wh-col .todo-list>ul{max-height:620px;overflow-y:auto;padding:0 4px 0 0;margin:0}
.wh-col .todo-item{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;background:#fff;border:1px solid #e9ecf1;border-radius:10px;padding:14px 16px;margin:0 0 10px}
.wh-col .todo-item:hover{box-shadow:0 2px 8px rgba(0,0,0,.07)}
.wh-col .todo-item-content{display:flex;flex-direction:column;gap:4px;flex:1;min-width:0}
.wh-col .todo-item-title{display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:17px;font-weight:700}
.wh-col .todo-item-subtitle{display:block;font-size:12px;color:#8a93a2}
.wh-col .todo-item-actions{flex:none}
.wh-empty{display:block;text-align:center;padding:34px 16px;border:1px dashed #d5dae3;border-radius:10px;color:#8a93a2;font-size:14px;list-style:none}
.wh-empty .material-icons-outlined{display:block;font-size:34px;margin-bottom:6px;color:#c3c9d4}
@media(max-width:767px){.wh-bal-amt{font-size:26px}.wh-filter .wh-actions{margin-left:0;width:100%}.wh-filter .wh-f{flex:1 1 140px}}
</style>
<style>
.wh-card{height:auto!important;min-height:0;overflow:visible;padding:24px}
.wh-card>.wh-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 24px;margin:0;height:auto}
.wh-card>.wh-row>div{width:auto;max-width:none;height:auto;padding:0;margin:0}
.wh-card>.wh-row>.wh-page,.wh-card>.wh-row>.wh-filter-wrap{grid-column:1/-1}
.wh-card .wh-menu{margin:0!important;padding:0!important;height:auto!important;overflow:visible;border:0!important}
.wh-card .wh-col .todo-list{margin:0;height:auto;overflow:visible}
.wh-card .wh-col .todo-list>ul{padding:0 6px 0 0}
.wh-card .wh-section-title{margin-top:24px}
.wh-card>.wh-row>.wh-col{margin-top:32px}
@media(max-width:991px){.wh-card{padding:16px}.wh-card>.wh-row{grid-template-columns:minmax(0,1fr)}}
</style>
<div class="col-12 wh-filter-wrap">
<div class="wh-section-title">Filter by date</div>
<form method="get" class="wh-filter">
<div class="wh-f"><label>From</label><input type="date" name="from_date" value="<?=$wh_from;?>" max="<?=date('Y-m-d');?>" class="form-control" required></div>
<div class="wh-f"><label>To</label><input type="date" name="to_date" value="<?=$wh_to;?>" max="<?=date('Y-m-d');?>" class="form-control" required></div>
<div class="wh-actions"><button type="submit" class="btn btn-primary">Apply</button><a href="wallet-history" class="btn btn-outline-secondary">Last 7 months</a></div>
</form>
</div>
                                    <div class="col-lg-6 wh-col wh-credit">
                                        <div class="todo-list">
                                            <h5 class="todo-menu-title">Credit History</h5>
                                            <ul class="list-unstyled">
<?php
$creditRes = mysqli_query($db_conn,
    "SELECT * FROM wallet_monthly_sls_report WHERE refer_by_usertype='$utype' AND refer_by_userid='$uid' AND DATE(from_date) BETWEEN '$wh_from' AND '$wh_to' ORDER BY from_date DESC");
$hasCredits = false;
while ($credit = mysqli_fetch_array($creditRes)):
    $hasCredits = true;
    $commType = $credit['commission_type'];

    $whomType = $credit['user_type'] ?? '';
    $whomId   = $credit['user_id'] ?? '';
    $whomName = '';
    $whomMobile = '';
    if ($commType === 'Refferral' && $whomType && $whomId) {
        // territory_partners uses id/mobile, not temp_id/mobile_number like the others
        $tmap = [
            'candf'             => ['table' => 'c_and_f',           'id_col' => 'temp_id', 'mobile_col' => 'mobile_number'],
            'super_stockiest'   => ['table' => 'super_stockiest',   'id_col' => 'temp_id', 'mobile_col' => 'mobile_number'],
            'stockiest'         => ['table' => 'stockiest',         'id_col' => 'temp_id', 'mobile_col' => 'mobile_number'],
            'distributor'       => ['table' => 'distributor',       'id_col' => 'temp_id', 'mobile_col' => 'mobile_number'],
            'territory_partner' => ['table' => 'territory_partners','id_col' => 'id',      'mobile_col' => 'mobile'],
        ];
        $wmap = $tmap[$whomType] ?? null;
        if ($wmap) {
            $whomIdEsc = mysqli_real_escape_string($db_conn, $whomId);
            $wr = mysqli_fetch_assoc(mysqli_query($db_conn,
                "SELECT name, {$wmap['mobile_col']} AS mobile_number FROM {$wmap['table']} WHERE {$wmap['id_col']}='$whomIdEsc' LIMIT 1"));
            $whomName   = $wr['name'] ?? '';
            $whomMobile = $wr['mobile_number'] ?? '';
        }
    }
?>
                                                <li class="todo-item">
                                                    <div class="todo-item-content">
                                                        <span class="todo-item-title">
                                                            &#8377;<?php echo inr_format($credit['commission_amount'], 2); ?>
                                                            <span class="badge badge-style-light rounded-pill badge-success">Credit (<?php echo htmlspecialchars($commType); ?>)</span>
                                                        </span>
                                                        <?php if ($commType === 'Refferral' && $whomName): ?>
                                                        <span><b><?php echo htmlspecialchars(ucwords($whomName)); ?></b>
                                                            <span style="font-size:12px;">(<?php echo ucwords($whomType); ?>)</span><br/>
                                                            <?php echo htmlspecialchars($whomMobile); ?>
                                                        </span>
                                                        <?php endif; ?>
                                                        <?php if ($commType === 'Cashback'): ?>
                                                        Cashback: <?php echo htmlspecialchars($credit['commission_percentage']); ?>%<br/>
                                                        <?php echo htmlspecialchars($credit['remarks'] ?? ''); ?>
                                                        <?php endif; ?>
                                                        <?php if ($commType !== 'Website Order Commission'): ?>
                                                        <span class="todo-item-subtitle"><?php echo htmlspecialchars($credit['month'] ?? ''); ?>, <?php echo htmlspecialchars($credit['year'] ?? ''); ?></span>
                                                        <?php else: ?>
                                                        <span class="todo-item-subtitle">Website Order Commission (OT Sales)<br/><?php echo htmlspecialchars($credit['remarks'] ?? ''); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </li>
<?php endwhile;
if(mysqli_num_rows($creditRes)==0){ ?><li class="wh-empty"><i class="material-icons-outlined">inbox</i>No credits found from <?=date("d M Y",strtotime($wh_from));?> to <?=date("d M Y",strtotime($wh_to));?>.<br/>Try a different date range.</li><?php }
?>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Debit History -->
                                    <div class="col-lg-6 wh-col wh-debit">
                                        <div class="todo-list">
                                            <h5 class="todo-menu-title">Debit History</h5>
                                            <ul class="list-unstyled">
<?php
$debitRes = mysqli_query($db_conn,
    "SELECT * FROM wallet_withdraw WHERE user_type='$utype' AND user_id='$uid' AND DATE(date) BETWEEN '$wh_from' AND '$wh_to' ORDER BY date DESC");
$hasDebits = false;
while ($debit = mysqli_fetch_array($debitRes)):
    $hasDebits = true;
?>
                                                <li class="todo-item">
                                                    <div class="todo-item-content">
                                                        <span class="todo-item-title">
                                                            &#8377;<?php echo inr_format($debit['amount'], 2); ?>
                                                            <?php if ($debit['req_status'] === 'pending'): ?>
                                                            <span class="badge badge-style-light rounded-pill badge-danger">Pending</span>
                                                            <?php else: ?>
                                                            <span class="badge badge-style-light rounded-pill badge-primary">Debit</span>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span class="todo-item-subtitle">
                                                            <?php echo date("d/m/Y", strtotime($debit['date'])); ?>,
                                                            <?php echo date("g:i A", strtotime($debit['time'])); ?>
                                                        </span>
                                                    </div>
                                                    <div class="todo-item-actions">
                                                        <?php if ($debit['req_status'] === 'pending'): ?>
                                                        <a href="wallet_request_process.php?delid=<?php echo base64_encode($debit['id']); ?>"
                                                           onclick="return confirm('Cancel this request?');"
                                                           class="todo-item-delete">
                                                            <i class="material-icons-outlined no-m">close</i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </li>
<?php endwhile;
if(mysqli_num_rows($debitRes)==0){ ?><li class="wh-empty"><i class="material-icons-outlined">inbox</i>No withdrawals found from <?=date("d M Y",strtotime($wh_from));?> to <?=date("d M Y",strtotime($wh_to));?>.<br/>Try a different date range.</li><?php }
?>
                                            </ul>
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
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
