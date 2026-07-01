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
                            <div class="card todo-container">
                                <div class="row">

                                    <!-- Balance Panel -->
                                    <div class="col-xl-4 col-xxl-3">
                                        <div class="todo-menu" style="text-align:center;">
                                            <h5 class="todo-menu-title">Wallet - Available Amount</h5>
                                            <ul class="list-unstyled todo-status-filter">
                                                <li>
                                                    <a><i class="material-icons-outlined">wallet</i>
                                                    <b>&#8377;<?php echo number_format($walletBalance, 2, '.', ''); ?></b></a>
                                                </li>
                                            </ul>

                                            <?php if ($walletBalance > 0): ?>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                                                <button type="button" class="btn btn-primary">Send Withdraw Request</button>
                                            </a>
                                            <?php endif; ?>

                                            <br/><br/>
                                            <div style="color:red;text-align:left;">
                                                Note:-<br/>
                                                <b><?php echo $tds_percentage; ?>% TDS will be deducted for all withdrawals by Femi9, and it will be reflected in your PAN card only if it is linked with your aadhar.</b>
                                            </div>

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

                                    <!-- Last 10 Credits -->
                                    <div class="col-xl-4 col-xxl-9" style="border-right:1px solid #ddd;">
                                        <div class="todo-list">
                                            <h5 class="todo-menu-title">Last 10 Credit</h5>
                                            <ul class="list-unstyled">
<?php
$creditRes = mysqli_query($db_conn,
    "SELECT * FROM wallet_monthly_sls_report WHERE refer_by_usertype='$utype' AND refer_by_userid='$uid' ORDER BY from_date DESC LIMIT 10");
$hasCredits = false;
while ($credit = mysqli_fetch_array($creditRes)):
    $hasCredits = true;
    $commType = $credit['commission_type'];

    $whomType = $credit['user_type'] ?? '';
    $whomId   = $credit['user_id'] ?? '';
    $whomName = '';
    $whomMobile = '';
    if ($commType === 'Refferral' && $whomType && $whomId) {
        $tmap = ['candf'=>'c_and_f','super_stockiest'=>'super_stockiest','stockiest'=>'stockiest','distributor'=>'distributor','territory_partner'=>'territory_partners'];
        $wtable = $tmap[$whomType] ?? '';
        if ($wtable) {
            $wr = mysqli_fetch_assoc(mysqli_query($db_conn, "SELECT name, mobile_number FROM $wtable WHERE temp_id='$whomId' LIMIT 1"));
            $whomName   = $wr['name'] ?? '';
            $whomMobile = $wr['mobile_number'] ?? '';
        }
    }
?>
                                                <li class="todo-item">
                                                    <div class="todo-item-content">
                                                        <span class="todo-item-title">
                                                            &#8377;<?php echo number_format($credit['commission_amount'], 2, '.', ''); ?>
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
if (!$hasCredits) echo '<li class="todo-item"><div class="todo-item-content"><span class="todo-item-title">No credit records found.</span></div></li>';
?>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Last 10 Debits -->
                                    <div class="col-xl-4 col-xxl-9">
                                        <div class="todo-list">
                                            <h5 class="todo-menu-title">Last 10 Debit</h5>
                                            <ul class="list-unstyled">
<?php
$debitRes = mysqli_query($db_conn,
    "SELECT * FROM wallet_withdraw WHERE user_type='$utype' AND user_id='$uid' ORDER BY date DESC LIMIT 10");
$hasDebits = false;
while ($debit = mysqli_fetch_array($debitRes)):
    $hasDebits = true;
?>
                                                <li class="todo-item">
                                                    <div class="todo-item-content">
                                                        <span class="todo-item-title">
                                                            &#8377;<?php echo number_format($debit['amount'], 2, '.', ''); ?>
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
if (!$hasDebits) echo '<li class="todo-item"><div class="todo-item-content"><span class="todo-item-title">No withdrawal records found.</span></div></li>';
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
