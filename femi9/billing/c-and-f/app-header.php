 <div class="app-header">
                <nav class="navbar navbar-light navbar-expand-lg">
                    <div class="container-fluid">
                        <div class="navbar-nav" id="navbarNav">
                            
							<table id="logoTablevl">
				<tr valign="top">
				<td><img src="<?php echo $usericon_concat;?>" class="usericon"></td>
				<td><h1><?=strtoupper($result_superstock['name']);?></h1>
				<h3><?=$result_superstock['useridtext'];?></h3>
				<h3>C&F</h3></td>
				</tr>
				</table>
            
                        </div>
						
                        <div class="d-flex">
                            <ul class="navbar-nav">
								
                                <li class="nav-item hidden-on-mobile">
                                    <a class="nav-link nav-notifications-toggle" id="notificationsDropDown" href="#" data-bs-toggle="dropdown"><img src="../../assets/images/femi-logo.png"/></a>
                                    <div class="dropdown-menu dropdown-menu-end notifications-dropdown" aria-labelledby="notificationsDropDown">
                                        <h6 class="dropdown-header">C&F (<?php echo $log_username;?>)</h6>
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
						
                    </div>
                </nav>
            </div>
			<br/>
			<style type="text/css">
			#linkcaption{text-decoration:none;color:#2911ea;font-weight:bold;}
			#linkcaption:hover{color:#1b0ba1;background:#DDD;}
			</style>