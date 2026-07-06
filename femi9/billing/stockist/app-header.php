 <div class="app-header">
                <nav class="navbar navbar-light navbar-expand-lg">
                    <div class="container-fluid">
                        <div class="navbar-nav" id="navbarNav">
                            
							<table id="logoTablevl">
				<tr valign="top">
				<td><img src="<?php echo $usericon_concat;?>" class="usericon"></td>
				<td><h1><?=strtoupper($result_superstock['name']);?></h1>
				<h3><?=$result_superstock['useridtext'];?></h3>
				<h2><?=$result_distname12['dist_name'];?><br/><?=ucwords($taluk_name_log);?></h2>
				<h3>Stockist (<?=$Login_person_CAT;?>)</h3></td>
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
								
								
								
								 <li class="nav-item hidden-on-mobile">
                                    <a class="nav-link nav-notifications-toggle" id="notificationsDropDown" href="#" data-bs-toggle="dropdown"><img src="../../assets/images/femi-logo.png"/></a>
                                    <div class="dropdown-menu dropdown-menu-end notifications-dropdown" aria-labelledby="notificationsDropDown">
                                        <h6 class="dropdown-header">Stockist (<?php echo $log_username;?>)</h6>
                                        <div class="notifications-dropdown-list">
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
						<!---------------------------------------->
						


                    </div>
                </nav>
            </div>
			<br/>
			<style type="text/css">
			#linkcaption{text-decoration:none;color:#2911ea;font-weight:bold;}
			#linkcaption:hover{color:#1b0ba1;background:#DDD;}
			</style>