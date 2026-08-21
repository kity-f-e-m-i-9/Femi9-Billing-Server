 <div class="app-header">
                <nav class="navbar navbar-light navbar-expand-lg">
                    <div class="container-fluid">
                        <a class="hide-sidebar-toggle-button ss-mobile-menu-toggle" href="#">
                            <i class="material-icons-outlined">menu</i>
                        </a>
                        <style>
                            /* Same root cause/fix as territory-partner/app-header.php: main.css
                               gives .app-sidebar .logo (logo.php's own floating mobile bar) a
                               fixed position at <=1199px that overlaps this separate header —
                               hide that and move its hamburger toggle into this header instead. */
                            .ss-mobile-menu-toggle {
                                display: none;
                                width: 38px; height: 38px; border-radius: 50%; background: #f3f4f6;
                                align-items: center; justify-content: center; flex: 0 0 auto; margin-right: 6px;
                            }
                            .ss-mobile-menu-toggle .material-icons-outlined { font-size: 24px; color: #293442; }
                            @media (max-width: 1199px) {
                                .app-sidebar .logo { display: none !important; }
                                .ss-mobile-menu-toggle { display: flex !important; }
                                /* The theme's own scroll plugin measures the sidebar menu's
                                   height once at page-load, while the sidebar itself is still
                                   off-canvas — it locks in a wrong, too-short height, so items
                                   further down a long menu (Account Manager, Security) become
                                   unreachable. Pure-CSS native scroll here works the same on
                                   every device (phone/tablet/laptop) at this breakpoint,
                                   independent of that plugin's timing. */
                                .app-sidebar .app-menu {
                                    overflow-y: auto !important;
                                    -webkit-overflow-scrolling: touch !important;
                                }
                            }
                            @media (max-width: 991px) {
                                .app-header { left: 0 !important; right: auto !important; width: 100% !important; box-sizing: border-box !important; }
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
                                .app-header #navbarNav { min-width: 160px; flex: 1 1 auto; overflow: hidden; }
                                .app-header .navbar-nav { flex-direction: row !important; flex-wrap: nowrap !important; align-items: center !important; }
                                #logoTablevl h1, #logoTablevl h2, #logoTablevl h3 {
                                    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
                                }
                            }
                        </style>
                        <div class="navbar-nav" id="navbarNav">

							<table id="logoTablevl">
				<tr valign="top">
				<td><img src="<?php echo $usericon_concat;?>" class="usericon"></td>
				<td><h1><?=strtoupper($result_superstock['name']);?></h1>
				<h3><?=$result_superstock['useridtext'];?></h3>
				<h2><?=$result_distname12['dist_name'];?></h2>
				<h3>Super Stockist</h3></td>
				</tr>
				</table>
            
                        </div>
						
                        <div class="d-flex">
                            <ul class="navbar-nav">
								
								<?php 
								//Total wallet amount
$select_wallet_amount_ST="select sum(commission_amount) from wallet_monthly_sls_report where refer_by_usertype='$Login_user_TYPEvl' and refer_by_userid='$Login_user_IDvl'";
$fetch_wallet_amount_ST=mysqli_query($db_conn,$select_wallet_amount_ST);
$result_wallet_amount_ST=mysqli_fetch_array($fetch_wallet_amount_ST);
$Total_wallet_amount_ST=$result_wallet_amount_ST[0] ?? '0';

//Total Withdraw Amount
$select_wallet_withdraw_amount_ST="select sum(amount) from wallet_withdraw where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and req_status='approved'";
$fetch_wallet_withdraw_amount_ST=mysqli_query($db_conn,$select_wallet_withdraw_amount_ST);
$result_wallet_withdraw_amount_ST=mysqli_fetch_array($fetch_wallet_withdraw_amount_ST);
$Total_withdraw_amount_ST=$result_wallet_withdraw_amount_ST[0] ?? '0';

$Average_available_walletAmount_ST=$Total_wallet_amount_ST-$Total_withdraw_amount_ST;
								?>
								<li class="nav-item hidden-on-mobile">
                                    <a class="nav-link" href="wallet-history" style="margin-top:12px;"> <i class="material-icons-outlined">wallet</i>&nbsp;<b><?=inr_format($Average_available_walletAmount_ST, 2);?></b></a>
                                </li>
								
                                <li class="nav-item ss-account-toggle">
                                    <style>
                                        /* Account switcher (logo) stays visible on mobile even though
                                           .hidden-on-mobile hides the rest of this nav — same treatment
                                           as territory-partner/app-header.php's .tp-account-toggle. */
                                        @media (max-width: 1100px) {
                                            .ss-account-toggle { display: flex !important; }
                                            .ss-account-toggle .nav-notifications-toggle { padding: 2px !important; }
                                            .ss-account-toggle .nav-notifications-toggle img { width: 52px; height: 52px; object-fit: cover; border-radius: 50%; }
                                        }
                                        @media (max-width: 400px) {
                                            .ss-account-toggle .nav-notifications-toggle img { width: 48px; height: 48px; }
                                        }
                                        @media (max-width: 991px) {
                                            .notifications-dropdown.ss-account-dropdown {
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
                                               position:fixed above on mobile and can silently fail to
                                               open at all — driven manually below instead. */
                                            .notifications-dropdown.ss-account-dropdown:not(.show) { display: none !important; }
                                            .notifications-dropdown.ss-account-dropdown.show { display: block !important; }
                                        }
                                    </style>
                                    <a class="nav-link nav-notifications-toggle" id="notificationsDropDown" href="#"><img src="../../assets/images/femi-logo.png"/></a>
                                    <div class="dropdown-menu dropdown-menu-end notifications-dropdown ss-account-dropdown" aria-labelledby="notificationsDropDown">
                                        <h6 class="dropdown-header">Super Stockist (<?php echo $log_username;?>)</h6>
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
                                            <a href="change-password.php">
                                                <div class="notifications-dropdown-item">
                                                    <div class="notifications-dropdown-item-text">
                                  <p class="bold-notifications-text">Change Password</p>
                                                    </div>
                                                </div>
                                            </a>
                                            
                                           <a href="logout.php" onclick="return confirm('You want to logout confirm?');">
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
			<style type="text/css">
			#linkcaption{text-decoration:none;color:#2911ea;font-weight:bold;}
			#linkcaption:hover{color:#1b0ba1;background:#DDD;}
			</style>
			<script>
			(function () {
			    // Driven manually (not via data-bs-toggle) so it doesn't depend on
			    // Bootstrap's Popper positioning, which fights the position:fixed CSS
			    // this menu needs on mobile and can silently fail to open there.
			    var toggle = document.getElementById('notificationsDropDown');
			    var menu = toggle ? toggle.closest('.nav-item').querySelector('.ss-account-dropdown') : null;
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