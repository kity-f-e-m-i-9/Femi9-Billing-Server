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
                    /* The base template pins .app-header to a fixed offset that assumes
                       its 340px sidebar is always visible — on real mobile widths the
                       sidebar is off-canvas, so that offset squeezes this header into a
                       tiny box and the account logo/name overflow it. It also relies on
                       Bootstrap's .navbar-expand-lg, which switches the nav to a stacked
                       "collapsed" layout below 992px expecting a working toggler button —
                       this theme never wires one up, so below that width the name block
                       and the account icon wrap onto their own line and spill outside the
                       header's fixed-height white box instead of staying in one row.
                       Scoped to TP pages only (this file is included on every one) rather
                       than touching the shared main.css used by every portal. */
                    @media (max-width: 991px) {
                        .app-header { left: 0 !important; right: 0 !important; width: 100% !important; }
                        .app-header .navbar { height: auto !important; min-height: 60px; padding: 8px 10px; }
                        .app-header .navbar > .container-fluid {
                            display: flex !important; flex-wrap: nowrap !important;
                            align-items: center !important; justify-content: space-between !important;
                            gap: 6px; width: 100%;
                        }
                        .app-header #navbarNav { min-width: 0; flex: 1 1 auto; overflow: hidden; }
                        .app-header .d-flex { flex: 0 0 auto; }
                        #tp-logoTable td:last-child { min-width: 0; }
                        #tp-logoTable h1, #tp-logoTable h2, #tp-logoTable h3 {
                            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
                        }
                    }
                    @media (max-width: 576px) {
                        #tp-logoTable h1 { font-size: 12px; }
                        #tp-logoTable h2 { font-size: 11px; }
                        #tp-logoTable h3 { font-size: 9px; }
                        #tp-logoTable td:first-child > div { width: 34px !important; height: 34px !important; font-size: 15px !important; }
                    }
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
                <style>
                    .tp-account-toggle .nav-notifications-toggle img {
                        width: 40px;
                        height: 40px;
                        object-fit: cover;
                        border-radius: 50%;
                        vertical-align: middle;
                    }
                    @media (max-width: 1100px) {
                        .tp-account-toggle { display: flex !important; }
                        .notifications-dropdown.tp-account-dropdown {
                            position: fixed !important;
                            top: 60px !important;
                            right: 8px !important;
                            left: 8px !important;
                            width: auto !important;
                            max-width: none !important;
                            z-index: 2000 !important;
                        }
                        /* Bootstrap's Popper-based positioning fights the forced
                           position:fixed above on mobile and can silently fail to open
                           the menu at all — driven manually below instead, so .show is
                           the only thing that controls visibility here. */
                        .notifications-dropdown.tp-account-dropdown:not(.show) { display: none !important; }
                        .notifications-dropdown.tp-account-dropdown.show { display: block !important; }
                    }
                    /* Bootstrap's .navbar-expand-lg switches .navbar-nav to a stacked
                       column below 992px (expecting a collapse toggler this theme never
                       adds) — force it back to a single row so the account icon stays
                       inline instead of dropping onto its own line. */
                    @media (max-width: 991px) {
                        .app-header .navbar-nav { flex-direction: row !important; flex-wrap: nowrap !important; align-items: center !important; }
                    }
                </style>
                <ul class="navbar-nav">
                    <li class="nav-item hidden-on-mobile">
                        <a class="nav-link" href="wallet-history.php" style="margin-top:12px;">
                            <i class="material-icons-outlined">wallet</i>&nbsp;<b>₹<?php echo inr_format($walletBalance, 2); ?></b>
                        </a>
                    </li>

                    <li class="nav-item tp-account-toggle">
                        <a class="nav-link nav-notifications-toggle" id="tpDropDown" href="#">
                            <img src="../../assets/images/femi-logo.png"/>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end notifications-dropdown tp-account-dropdown" aria-labelledby="tpDropDown">
                            <h6 class="dropdown-header">Territory Partner (<?php echo htmlspecialchars($Login_user_mobile); ?>)</h6>
                            <div class="notifications-dropdown-list">
                                <?php if (count($_SESSION['LINKED_ACCOUNTS'] ?? []) > 1): ?>
                                <h6 class="dropdown-header">Switch Account</h6>
                                <?php foreach ($_SESSION['LINKED_ACCOUNTS'] as $_acct): if ($_acct['type'] === $_SESSION['LOGIN_USER_TYPE']) continue; ?>
                                <a href="../login/switch-account.php?type=<?php echo urlencode($_acct['type']); ?>">
                                    <div class="notifications-dropdown-item">
                                        <div class="notifications-dropdown-item-text">
                                            <p class="bold-notifications-text"><?php echo htmlspecialchars($_acct['display_name']); ?> — <?php echo htmlspecialchars($_acct['name']); ?></p>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                                <?php endif; ?>
                                <h6 class="dropdown-header">Security</h6>
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
<script>
(function () {
    // Driven manually (not via data-bs-toggle) so it doesn't depend on
    // Bootstrap's Popper positioning, which fights the position:fixed CSS
    // this menu needs on mobile and can silently fail to open there.
    var toggle = document.getElementById('tpDropDown');
    var menu = toggle ? toggle.closest('.nav-item').querySelector('.tp-account-dropdown') : null;
    if (!toggle || !menu) return;
    toggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        menu.classList.toggle('show');
    });
    document.addEventListener('click', function (e) {
        if (menu.classList.contains('show') && !menu.contains(e.target) && e.target !== toggle) {
            menu.classList.remove('show');
        }
    });
})();
</script>
