 <div class="app-header">
                <nav class="navbar navbar-light navbar-expand-lg">
                    <div class="container-fluid">
                        <div class="navbar-nav" id="navbarNav">

				<table id="logoTablevl">
				<tr valign="top">
				<td><img src="../../assets/images/femi-logo.png" class="usericon"></td>
				<td><h1><?=htmlspecialchars((string)(strtoupper($result_LoGuserDtails['ms_name'])), ENT_QUOTES, 'ISO-8859-1', false);?></h1>
				<h2><?=htmlspecialchars($_SESSION['LOGIN_USER'] ?? ($result_LoGuserDtails['ms_mobile'] ?? ''));?></h2>
				<h3>Marketing</h3></td>
				</tr>
				</table>

                        </div>

                        <div class="d-flex">
                            <ul class="navbar-nav">
                                <?php if (!empty($_SESSION['LOGGED_IN_VIA_COMPANY'])): ?>
                                <style>
                                    .ms-back-to-company {
                                        display: inline-flex; align-items: center; gap: 6px;
                                        background: linear-gradient(135deg,#f59e0b,#d97706);
                                        color: #fff; font-size: 13px; font-weight: 600;
                                        padding: 8px 16px; border-radius: 30px;
                                        text-decoration: none; white-space: nowrap;
                                        box-shadow: 0 2px 6px rgba(217,119,6,0.35);
                                        transition: filter .15s, transform .1s;
                                        margin-top: 8px; margin-right: 10px;
                                    }
                                    .ms-back-to-company:hover { filter: brightness(1.08); color: #fff; }
                                    .ms-back-to-company:active { transform: scale(0.97); }
                                    .ms-back-to-company .material-icons-outlined { font-size: 18px; }
                                </style>
                                <li class="nav-item">
                                    <a class="ms-back-to-company" href="../company/ms-team-shop-view.php" title="Back to Company Login"
                                       onclick="window.close(); setTimeout(function(){ window.location.href='../company/ms-team-shop-view.php'; }, 300); return false;">
                                        <i class="material-icons-outlined" style="transform:scaleX(-1);">logout</i><span>Back to Company</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <li class="nav-item hidden-on-mobile">
                                    <a class="nav-link nav-notifications-toggle" id="notificationsDropDown" href="#" data-bs-toggle="dropdown"><img src="../../assets/images/femi-logo.png"/></a>
                                    <div class="dropdown-menu dropdown-menu-end notifications-dropdown" aria-labelledby="notificationsDropDown">
                                        <h6 class="dropdown-header">Marketing (<?php echo htmlspecialchars($_SESSION['LOGIN_USER'] ?? ($result_LoGuserDtails['ms_mobile'] ?? '')); ?>)</h6>
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
			<div style="clear:both;"></div>
			<br/>
			<style type="text/css">
			#linkcaption{text-decoration:none;color:#2911ea;font-weight:bold;}
			#linkcaption:hover{color:#1b0ba1;background:#DDD;}
			</style>