<?php
$_wUid   = mysqli_real_escape_string($db_conn, $Login_user_IDvl);
$_wUtype = mysqli_real_escape_string($db_conn, $Login_user_TYPEvl);
$_wCredits   = (float)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COALESCE(SUM(commission_amount),0) FROM wallet_monthly_sls_report WHERE refer_by_usertype='$_wUtype' AND refer_by_userid='$_wUid'"))[0] ?? 0);
$_wWithdrawn = (float)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COALESCE(SUM(amount),0) FROM wallet_withdraw WHERE user_type='$_wUtype' AND user_id='$_wUid' AND req_status='approved'"))[0] ?? 0);
$walletBalance = $_wCredits - $_wWithdrawn;

// Assigned locations for header display
$_hLocStmt = $db_conn->prepare("
    SELECT n.name AS location_name, COALESCE(pll.layer_name, '') AS layer_name
    FROM channel_partner_locations cpl
    JOIN partner_location_nodes n    ON n.id = cpl.location_id
    LEFT JOIN partner_location_layers pll ON pll.depth = n.depth
    WHERE cpl.channel_partner_id = ?
    ORDER BY n.name ASC
");
$_hLocStmt->bind_param('i', $Login_user_IDvl);
$_hLocStmt->execute();
$_hLocs = $_hLocStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$_hLocStmt->close();
$_hLocCount = count($_hLocs);
?>
<div class="app-header">
    <nav class="navbar navbar-light navbar-expand-lg">
        <div class="container-fluid">
            <div class="navbar-nav" id="navbarNav">
                <style>
                    #cp-logoTable { border-collapse: collapse; }
                    #cp-logoTable td { padding: 4px 6px; vertical-align: top; }
                    #cp-logoTable .cp-name  { font-size: 14px; font-weight: 700; color: #0d9488; margin: 0 0 1px 0; line-height: 1.2; text-transform: capitalize; }
                    #cp-logoTable .cp-meta  { font-size: 11px; color: #94a3b8; margin: 0 0 5px 0; }
                    .cp-loc-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 4px; }
                    .cp-loc-chip {
                        display: inline-flex; align-items: center; gap: 3px;
                        background: #f0fdfa; color: #0f766e;
                        border: 1px solid #99f6e4;
                        font-size: 10.5px; font-weight: 600;
                        padding: 2px 8px; border-radius: 20px;
                        white-space: nowrap;
                    }
                    .cp-loc-chip i { font-size: 11px; }
                    .cp-loc-more {
                        display: inline-flex; align-items: center;
                        background: #e2e8f0; color: #475569;
                        font-size: 10.5px; font-weight: 600;
                        padding: 2px 8px; border-radius: 20px;
                        cursor: pointer; border: none;
                        white-space: nowrap;
                    }
                    /* tooltip for remaining locations */
                    .cp-loc-more-wrap { position: relative; display: inline-block; }
                    .cp-loc-tooltip {
                        display: none;
                        position: absolute;
                        top: calc(100% + 6px);
                        left: 0;
                        background: #1e293b;
                        color: #f1f5f9;
                        font-size: 11px;
                        border-radius: 8px;
                        padding: 8px 12px;
                        min-width: 160px;
                        z-index: 9999;
                        box-shadow: 0 4px 16px rgba(0,0,0,.2);
                        line-height: 1.9;
                    }
                    .cp-loc-more-wrap:hover .cp-loc-tooltip { display: block; }
                </style>
                <table id="cp-logoTable">
                    <tr>
                        <td>
                            <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#0d9488,#0f766e);display:flex;align-items:center;justify-content:center;color:#fff;font-size:19px;font-weight:700;flex-shrink:0;">
                                <?php echo strtoupper(substr($Login_user_name, 0, 1)); ?>
                            </div>
                        </td>
                        <td>
                            <p class="cp-name"><?php echo htmlspecialchars($Login_user_name); ?></p>
                            <p class="cp-meta"><?php echo htmlspecialchars($Login_user_cp_id); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($Login_user_mobile); ?> &nbsp;·&nbsp; Channel Partner</p>

                            <?php if ($_hLocCount > 0): ?>
                            <div class="cp-loc-chips">
                                <?php
                                $showMax = 2;
                                $shown   = array_slice($_hLocs, 0, $showMax);
                                $rest    = array_slice($_hLocs, $showMax);
                                foreach ($shown as $_loc): ?>
                                <span class="cp-loc-chip">
                                    <i class="material-icons-outlined">place</i>
                                    <?php echo htmlspecialchars($_loc['location_name']); ?>
                                    <?php if (!empty($_loc['layer_name'])): ?>
                                        <span style="opacity:.6;font-weight:400;">(<?php echo htmlspecialchars($_loc['layer_name']); ?>)</span>
                                    <?php endif; ?>
                                </span>
                                <?php endforeach; ?>

                                <?php if (count($rest) > 0): ?>
                                <span class="cp-loc-more-wrap">
                                    <span class="cp-loc-more">+<?php echo count($rest); ?> more</span>
                                    <div class="cp-loc-tooltip">
                                        <?php foreach ($rest as $_r): ?>
                                            <div>📍 <?php echo htmlspecialchars($_r['location_name']); ?><?php if (!empty($_r['layer_name'])): ?> <span style="opacity:.6;">(<?php echo htmlspecialchars($_r['layer_name']); ?>)</span><?php endif; ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
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
                        <a class="nav-link nav-notifications-toggle" id="cpDropDown" href="#" data-bs-toggle="dropdown">
                            <img src="../../assets/images/femi-logo.png"/>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end notifications-dropdown" aria-labelledby="cpDropDown">
                            <h6 class="dropdown-header">Channel Partner (<?php echo htmlspecialchars($Login_user_mobile); ?>)</h6>
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
