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
            <a class="hide-sidebar-toggle-button tp-mobile-menu-toggle" href="#">
                <i class="material-icons-outlined">menu</i>
            </a>
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
                    /* The REAL cause of the header looking broken/overlapped on mobile
                       this whole time: main.css gives .app-sidebar .logo (the small
                       white rounded box with the hamburger icon, from logo.php) its own
                       position:fixed + translateX at <=1199px specifically so it floats
                       on screen as a compact mobile top bar even while the rest of
                       .app-sidebar slides off-canvas. This app's app-header.php renders
                       a SECOND, separate fixed header on top of that for the same
                       breakpoint — the two were never designed to coexist, so they
                       landed in the same screen region and overlapped. Since
                       app-header.php already shows richer info (name, wallet, account
                       switcher), the fix is to hide the theme's own floating logo box on
                       mobile and move its hamburger toggle into this header instead. */
                    .tp-mobile-menu-toggle {
                        display: none;
                        width: 38px; height: 38px; border-radius: 50%; background: #f3f4f6;
                        align-items: center; justify-content: center; flex: 0 0 auto; margin-right: 6px;
                    }
                    .tp-mobile-menu-toggle .material-icons-outlined { font-size: 24px; color: #293442; }
                    @media (max-width: 1199px) {
                        .app-sidebar .logo { display: none !important; }
                        .tp-mobile-menu-toggle { display: flex !important; }
                        /* main.css sets .app-sidebar to height:100vh, which on
                           mobile is the LARGEST possible viewport — as if the
                           browser's own address bar/bottom toolbar were fully
                           collapsed. Whenever that browser chrome is actually
                           on screen (varies by browser, OS version, and even
                           scroll position — never a fixed pixel amount), the
                           sidebar overflows past the real visible area and its
                           last menu items land underneath the toolbar. 100dvh
                           ("dynamic viewport height") is the modern fix built
                           for exactly this — it tracks the CURRENT visible
                           height live as browser chrome shows/hides, no
                           per-device guessing needed. Kept as a second
                           declaration (not a replacement) so browsers that
                           don't understand dvh yet just ignore this line and
                           fall back to main.css's own 100vh untouched. */
                        .app-sidebar { height: 100dvh; }
                        .app-sidebar .app-menu {
                            overflow-y: auto !important;
                            -webkit-overflow-scrolling: touch !important;
                            /* Small fixed cushion so the last item never sits
                               flush against the very bottom edge. Not trying
                               to solve OS-level gesture-bar/notch safe areas
                               here — that needs viewport-fit=cover on every
                               page's own <meta name="viewport">, a separate,
                               wider change (see the plain 100dvh fix above,
                               which already covers the actual reported case:
                               the browser's own address/tool bar). */
                            padding-bottom: 16px !important;
                        }
                    }
                    @media (max-width: 991px) {
                        .app-header { left: 0 !important; right: auto !important; width: 100% !important; box-sizing: border-box !important; }
                        /* Bootstrap's .navbar is itself display:flex, and with only one
                           child (.container-fluid) a flex parent doesn't reliably stretch
                           that child to 100% just because the child says width:100% —
                           that's what left a small white box (sized to its content)
                           floating at the left with a transparent gap, and the account
                           logo pinned separately at .app-header's real right edge, instead
                           of one full-width white bar. Switching .navbar to a plain block
                           removes that ambiguity entirely — .container-fluid's own flex
                           rules below do the actual row layout. */
                        .app-header .navbar {
                            display: block !important;
                            height: auto !important; min-height: 60px; padding: 8px 10px;
                            width: 100% !important; box-sizing: border-box !important;
                        }
                        .app-header .navbar > .container-fluid {
                            display: flex !important; flex-wrap: nowrap !important;
                            align-items: center !important; justify-content: space-between !important;
                            gap: 6px; width: 100% !important; box-sizing: border-box;
                        }
                        /* flex:1 1 auto with min-width:0 lets the browser shrink this all
                           the way to 0 (hiding the name entirely) once space is tight —
                           it needs a real floor so it always keeps some visible width. */
                        .app-header #navbarNav { min-width: 160px; flex: 1 1 auto; overflow: hidden; }
                        .app-header .d-flex { flex: 0 0 auto; display: flex !important; align-items: center; gap: 4px; }
                        #tp-logoTable tr { vertical-align: middle !important; }
                        #tp-logoTable td:first-child { padding-right: 4px; }
                        #tp-logoTable td:last-child { min-width: 0; padding-left: 0; }
                        #tp-logoTable h1, #tp-logoTable h2, #tp-logoTable h3 {
                            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
                        }
                        /* Keep the account logo vertically centered in line with the
                           name row, not riding high/low relative to it. */
                        .app-header .tp-account-toggle { display: flex !important; align-items: center; }
                    }
                    /* Per feedback: wallet balance shouldn't show on mobile at all (back
                       to the original behavior) — just name+details and the account
                       logo, in line with each other. Removing wallet frees up enough
                       width that the full name/TP-ID/mobile/"Territory Partner" block no
                       longer needs to be hidden or aggressively truncated down to a
                       name-only sliver, the way it did when wallet was competing for the
                       same row. */
                    @media (max-width: 1199px) {
                        .app-header .tp-wallet-toggle { display: none !important; }
                    }
                    @media (max-width: 400px) {
                        #tp-logoTable td:first-child > div { width: 30px !important; height: 30px !important; font-size: 13px !important; }
                        #tp-logoTable td { padding: 3px !important; vertical-align: middle !important; }
                        #tp-logoTable h1 { font-size: 11px !important; }
                        #tp-logoTable h2 { font-size: 9.5px !important; }
                        #tp-logoTable h3 { font-size: 8px !important; }
                    }
                    @media (min-width: 401px) and (max-width: 576px) {
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
                        width: 46px;
                        height: 46px;
                        object-fit: cover;
                        border-radius: 50%;
                        vertical-align: middle;
                    }
                    @media (max-width: 1100px) {
                        .tp-account-toggle { display: flex !important; }
                        /* main.css's .nav-notifications-toggle carries 6.5px 15px of its
                           own padding — added to the mobile hamburger toggle, avatar, name
                           column, and wallet pill all competing for the same narrow row,
                           that 15px each side was enough to push this logo entirely past
                           the right edge of the screen (present in the DOM, just invisible
                           off-viewport, not merely small/hard to see). Trimmed way down. */
                        .tp-account-toggle .nav-notifications-toggle { padding: 2px !important; }
                        /* Matches the "T" avatar circle's own size at each breakpoint —
                           wallet no longer competes for room in this row, so there's
                           space for the logo to be this size without pushing off-screen. */
                        .tp-account-toggle .nav-notifications-toggle img { width: 44px; height: 44px; }
                    }
                    @media (max-width: 400px) {
                        .tp-account-toggle .nav-notifications-toggle img { width: 45px; height: 45px; }
                    }
                        .notifications-dropdown.tp-account-dropdown {
                            position: fixed !important;
                            top: 62px !important;
                            right: 8px !important;
                            left: auto !important;
                            width: 220px !important;
                            max-width: calc(100vw - 16px) !important;
                            max-height: min(320px, calc(100vh - 80px)) !important;
                            z-index: 2000 !important;
                        }
                        /* Bootstrap's Popper-based positioning fights the forced
                           position:fixed above on mobile and can silently fail to open
                           the menu at all — driven manually below instead, so .show is
                           the only thing that controls visibility here. */
                        .notifications-dropdown.tp-account-dropdown:not(.show) { display: none !important; }
                        .notifications-dropdown.tp-account-dropdown.show { display: block !important; }
                        .tp-account-dropdown .dropdown-header { padding: 6px 10px !important; font-size: 10px !important; }
                        .tp-account-dropdown .notifications-dropdown-item { padding: 6px 10px !important; }
                        .tp-account-dropdown .notifications-dropdown-item-text p { font-size: 12.5px !important; }
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
                    <li class="nav-item tp-wallet-toggle">
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
