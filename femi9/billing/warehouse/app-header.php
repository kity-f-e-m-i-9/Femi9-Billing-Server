<div class="app-header">
    <nav class="navbar navbar-light navbar-expand-lg">
        <div class="container-fluid">
            <div class="navbar-nav" id="navbarNav">
                <style>
                    #wh-logoTable { border-collapse: collapse; }
                    #wh-logoTable td { padding: 4px 6px; vertical-align: top; }
                    #wh-logoTable .wh-name  { font-size: 14px; font-weight: 700; color: #0d9488; margin: 0 0 1px 0; line-height: 1.2; text-transform: capitalize; }
                    #wh-logoTable .wh-meta  { font-size: 11px; color: #94a3b8; margin: 0; }
                </style>
                <table id="wh-logoTable">
                    <tr>
                        <td>
                            <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#0d9488,#0f766e);display:flex;align-items:center;justify-content:center;color:#fff;font-size:19px;font-weight:700;flex-shrink:0;">
                                <?php echo strtoupper(substr($Login_user_name, 0, 1)); ?>
                            </div>
                        </td>
                        <td>
                            <p class="wh-name"><?php echo htmlspecialchars($Login_user_name); ?></p>
                            <p class="wh-meta"><?php echo htmlspecialchars($Login_user_mobile); ?> &nbsp;&middot;&nbsp; Godown Viewer</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="d-flex">
                <ul class="navbar-nav">
                    <li class="nav-item hidden-on-mobile">
                        <a class="nav-link nav-notifications-toggle" id="whDropDown" href="#" data-bs-toggle="dropdown">
                            <img src="../../assets/images/femi-logo.png"/>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end notifications-dropdown" aria-labelledby="whDropDown">
                            <h6 class="dropdown-header">Godown Viewer (<?php echo htmlspecialchars($Login_user_mobile); ?>)</h6>
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
