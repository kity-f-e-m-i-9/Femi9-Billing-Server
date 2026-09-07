<div class="app-header">
    <nav class="navbar navbar-light navbar-expand-lg">
        <div class="container-fluid">
            <div class="navbar-nav" id="navbarNav">
                <style>
                    #track-logoTable { border-collapse: collapse; width: 100%; }
                    #track-logoTable td { padding: 5px; vertical-align: middle; }
                    #track-logoTable h1 { font-size: 15px; text-transform: capitalize; padding: 0; margin: 0; color: #d97706; font-weight: 700; }
                    #track-logoTable h2 { font-size: 13px; color: #999; padding: 0; margin: 0; font-weight: 400; }
                    #track-logoTable h3 { font-size: 11px; color: #003333; padding: 0; margin: 0; font-weight: 400; }
                </style>
                <table id="track-logoTable">
                    <tr valign="top">
                        <td>
                            <div style="width:45px;height:45px;border-radius:100%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-weight:700;">
                                <?php echo strtoupper(substr($result_LoGuserDtails['name'] ?? '?', 0, 1)); ?>
                            </div>
                        </td>
                        <td>
                            <h1><?php echo strtoupper($result_LoGuserDtails['name'] ?? ''); ?></h1>
                            <h2><?php echo htmlspecialchars($_SESSION['LOGIN_USER'] ?? ($result_LoGuserDtails['mobile'] ?? '')); ?></h2>
                            <h3>Track</h3>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="d-flex">
                <ul class="navbar-nav">
                    <li class="nav-item hidden-on-mobile">
                        <a class="nav-link nav-notifications-toggle" id="trackDropDown" href="#" data-bs-toggle="dropdown">
                            <img src="../../assets/images/femi-logo.png"/>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end notifications-dropdown" aria-labelledby="trackDropDown">
                            <h6 class="dropdown-header">Track (<?php echo htmlspecialchars($_SESSION['LOGIN_USER'] ?? ($result_LoGuserDtails['mobile'] ?? '')); ?>)</h6>
                            <div class="notifications-dropdown-list">
                                <a href="logout" onclick="return confirm('You want to logout confirm?');">
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
