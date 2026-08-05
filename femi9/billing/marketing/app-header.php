 <div class="app-header">
                <nav class="navbar navbar-light navbar-expand-lg">
                    <div><!---------------class="container-fluid"---------------->
                        <div><!------class="navbar-nav" id="navbarNav"--------->
						
				<table id="logoTablevl">
				<tr valign="top">
				<td><img src="../../assets/images/femi-logo.png" class="usericon"></td>
				<td><h1><?=strtoupper($result_LoGuserDtails['ms_name']);?></h1>
				<h2><?=$_SESSION['LOGIN_USER'];?></h2>
				<h3>Marketing</h3></td>
				</tr>
				</table>

                        </div>

                        <div class="d-flex">
                            <ul class="navbar-nav">
                                <li class="nav-item hidden-on-mobile">
                                    <a class="nav-link nav-notifications-toggle" id="notificationsDropDown" href="#" data-bs-toggle="dropdown"><img src="../../assets/images/femi-logo.png"/></a>
                                    <div class="dropdown-menu dropdown-menu-end notifications-dropdown" aria-labelledby="notificationsDropDown">
                                        <h6 class="dropdown-header">Marketing (<?php echo htmlspecialchars($_SESSION['LOGIN_USER']); ?>)</h6>
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