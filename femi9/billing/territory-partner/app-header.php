<?php
$_wUid   = mysqli_real_escape_string($db_conn, $Login_user_IDvl);
$_wUtype = mysqli_real_escape_string($db_conn, $Login_user_TYPEvl);
$_wCredits   = (float)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COALESCE(SUM(commission_amount),0) FROM wallet_monthly_sls_report WHERE refer_by_usertype='$_wUtype' AND refer_by_userid='$_wUid'"))[0] ?? 0);
$_wWithdrawn = (float)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COALESCE(SUM(amount),0) FROM wallet_withdraw WHERE user_type='$_wUtype' AND user_id='$_wUid' AND req_status='approved'"))[0] ?? 0);
$walletBalance = $_wCredits - $_wWithdrawn;
?>
<div class="app-header">
    <nav class="navbar navbar-light navbar-expand-lg">
        <div class="container-fluid">
            <div class="navbar-nav" id="navbarNav">
                <style>
                    #tp-logoTable { border-collapse: collapse; width: 100%; }
                    #tp-logoTable td { padding: 5px; }
                    #tp-logoTable h1 { font-size: 15px; text-transform: capitalize; padding: 0; margin: 0; color: #d97706; }
                    #tp-logoTable h2 { font-size: 13px; color: #999; padding: 0; margin: 0; }
                    #tp-logoTable h3 { font-size: 11px; color: #003333; padding: 0; margin: 0; font-weight: 400; }
                </style>
                <table id="tp-logoTable">
                    <tr valign="top">
                        <td>
                            <div style="width:45px;height:45px;border-radius:100%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-weight:700;">
                                <?php echo strtoupper(substr($Login_user_name, 0, 1)); ?>
                            </div>
                        </td>
                        <td>
                            <h1><?php echo strtoupper($Login_user_name); ?></h1>
                            <h3><?php echo htmlspecialchars($Login_user_tp_id); ?></h3>
                            <h2><?php echo htmlspecialchars($Login_user_mobile); ?></h2>
                            <h3>Territory Partner</h3>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="d-flex">
                <ul class="navbar-nav">
                    <li class="nav-item hidden-on-mobile">
                        <a class="nav-link" href="wallet-history.php" style="margin-top:12px;">
                            <i class="material-icons-outlined">wallet</i>&nbsp;<b>₹<?php echo number_format($walletBalance, 2); ?></b>
                        </a>
                    </li>

                    <li class="nav-item hidden-on-mobile">
                        <a class="nav-link nav-notifications-toggle" id="tpDropDown" href="#" data-bs-toggle="dropdown">
                            <img src="../../assets/images/femi-logo.png"/>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end notifications-dropdown" aria-labelledby="tpDropDown">
                            <h6 class="dropdown-header">Territory Partner (<?php echo htmlspecialchars($Login_user_mobile); ?>)</h6>
                            <div class="notifications-dropdown-list">
                                <a href="change-password.php">
                                    <div class="notifications-dropdown-item">
                                        <div class="notifications-dropdown-item-text">
                                            <p class="bold-notifications-text">Change Password</p>
                                        </div>
                                    </div>
                                </a>
                                <a href="logout.php" onclick="return confirm('You want to logout?');">
                                    <div class="notifications-dropdown-item">
                                        <div class="notifications-dropdown-item-text">
                                            <p class="bold-notifications-text">Logout</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</div>
<br/>
