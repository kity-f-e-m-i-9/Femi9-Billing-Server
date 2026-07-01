 <div class="app-header">
                <nav class="navbar navbar-light navbar-expand-lg">
                    <div class="container-fluid">
                        <div class="navbar-nav" id="navbarNav">
						   
						   <a href="dashboard" class="logo-icon"><span class="logo-text"></span></a>
                <div class="sidebar-user-switcher user-activity-online">
                    <a href="dashboard">
                        <img src="../../assets/images/cmp.jpeg" id="cmplogo">
                        <!----<span class="activity-indicator"></span>
                        <span class="user-info-text">Femi9<br><span class="user-state-info">Pengalulagam</span></span>--->
                    </a>
                </div>
                        </div>
						
						
                        <div class="d-flex">
                            <ul class="navbar-nav">
							   
                                <!------<li class="nav-item hidden-on-mobile">
                                    <a class="nav-link language-dropdown-toggle" href="#" id="languageDropDown" data-bs-toggle="dropdown"><img src="../../assets/images/flags/us.png" alt=""></a>
                                        <ul class="dropdown-menu dropdown-menu-end language-dropdown" aria-labelledby="languageDropDown">
                                            <li><a class="dropdown-item" href="#"><img src="../../assets/images/flags/germany.png" alt="">German</a></li>
                                            <li><a class="dropdown-item" href="#"><img src="../../assets/images/flags/italy.png" alt="">Italian</a></li>
                                            <li><a class="dropdown-item" href="#"><img src="../../assets/images/flags/china.png" alt="">Chinese</a></li>
                                        </ul>
                                </li>---->
								
								
                                <li class="nav-item hidden-on-mobile">
                                    <a class="nav-link nav-notifications-toggle" id="notificationsDropDown" href="#" data-bs-toggle="dropdown"><img src="../../assets/images/femi-logo.png"/></a>
                                    <div class="dropdown-menu dropdown-menu-end notifications-dropdown" aria-labelledby="notificationsDropDown">
                                        <h6 class="dropdown-header"><?=ucwords($LoginusertypeGET);?> (<?=$_SESSION['LOGIN_USER'];?>)</h6>
                                        <div class="notifications-dropdown-list">
                                            
											<?php if($LoginusertypeGET=="admin"){?>
											<a href="change-password">
                                                <div class="notifications-dropdown-item">
                                                    <div class="notifications-dropdown-item-text">
                                                        <p class="bold-notifications-text">Chanage Password</p>
                                                    </div>
                                                </div>
                                            </a>											
											<a href="wp-settings">
                                                <div class="notifications-dropdown-item">
                                                    <div class="notifications-dropdown-item-text">
                                                        <p class="bold-notifications-text">Whatsapp Settings</p>
                                                    </div>
                                                </div>
                                            </a>
											<?php }?>
											
											<a href="web-commission">
                                                <div class="notifications-dropdown-item">
                                                    <div class="notifications-dropdown-item-text">
                                                        <p class="bold-notifications-text">Website Order Commission Setup</p>
                                                    </div>
                                                </div>
                                            </a>
											
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
			<br/>
			<style type="text/css">
			#linkcaption{text-decoration:none;color:#2911ea;font-weight:bold;}
			#linkcaption:hover{color:#1b0ba1;background:#DDD;}
			</style>