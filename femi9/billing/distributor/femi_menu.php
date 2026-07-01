<div class="app-menu">

<?php 
$select_LoGuserDtailsMN="select * from distributor where username='".$_SESSION['LOGIN_USER']."'";
$fetch_LoGuserDtailsMN=mysqli_query($db_conn,$select_LoGuserDtailsMN);
$result_LoGuserDtailsMN=mysqli_fetch_array($fetch_LoGuserDtailsMN);
$LoginPasswordCheck=$result_LoGuserDtailsMN['password'];

if($LoginPasswordCheck=="12345678")
{ 
?>

<ul class="accordion-menu">
                        
                    <li>
                        <a href="#"><i class="material-icons-two-tone">dashboard</i>Dashboard</a>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">view_agenda</i>Report</a>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock Return<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                    </li>
					
					<!-----<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock Request<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                    </li>---->
					
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Shop<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">star</i>Customer<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                    </li>
					
					
					 <li>
                        <a href=""><i class="material-icons-two-tone">security</i>Security<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="#">My Profile</a></li>
							 <li><a href="change-password">Change Password</a></li>
                            <li>
                                <a href="logout" onclick="return confirm('You want to logout confirm?');">Logout</a>
                            </li>
                        </ul>
                    </li>
					
                </ul>

<?php }else{?>

                <ul class="accordion-menu">
                    <li>
                        <a href="dashboard"><i class="material-icons-two-tone">dashboard</i>Dashboard</a>
                    </li>
					
					<!--Reward Points-->
					<li>
                        <a href="reward_points.php"><i class="material-icons-two-tone">analytics</i>Reward Points: Pur</a>
                    </li>
					<li>
                        <a href="reward_points_sls.php"><i class="material-icons-two-tone">analytics</i>Reward Points: Sls</a>
                    </li>
					
					<li>
                        <a href="Report"><i class="material-icons-two-tone">view_agenda</i>Report</a>
                    </li>
					
					<li>
                    <a href="wallet-history"><i class="material-icons-outlined">wallet</i>Wallet History</a>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">analytics</i>GST Reports<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="GSTR1">GSTR1</a></li>
						<li><a href="GSTR3B">GSTR3B</a></li>
                        </ul>
                    </li>
					
					<!------<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Demo Awareness<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="add_demo.php">Add Demo</a></li>
						<li><a href="manage_demo.php">Manage Demo</a></li>
                        </ul>
                    </li>----->
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="op-stock">Set Opening Stock</a></li>
						<li><a href="overall-stock">Overall Stock</a></li>
                        </ul>
                    </li>
					
					<!--<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Input Stock<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="add-input">Add Input stock</a></li>
						<li><a href="manage-input">Manage Input stock</a></li>
                        </ul>
                    </li>-->
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Demo/Free/Damage<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="demofree_new">Add Demo/Free/Damage</a></li>
						<li><a href="demofree_manage">Manage Demo/Free/Damage</a></li>
                        </ul>
                    </li>
					
					<li>
					<a href="cnote_manage"><i class="material-icons-two-tone">done</i>Manage Return<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
					</li>
					
					<li>
					<a href="dnote_manage"><i class="material-icons-two-tone">done</i>Debit Note<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
					</li>
					
					<!------<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock Return<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="stock-return-add">Add Stock Return</a></li>
						<li><a href="stock-return-manage">Manage Stock Return</a></li>
                        </ul>
                    </li>----->
					
					<!------<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock Request<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
							<li><a href="stock-request-add">Add Request</a></li>
                            <li><a href="stock-request-manage">Manage Request</a></li>
                        </ul>
                    </li>----->
					
					<?php /*?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">cloud_queue</i>Pincode<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                           <li><a href="add-pincode">Add Pincode</a></li>
                            <li><a href="manage-pincode">Manage Pincode</a></li>
                        </ul>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">cloud_queue</i>Taluk<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                           <li><a href="add-taluk">Add Taluk</a></li>
                            <li><a href="manage-taluk">Manage Taluk</a></li>
                        </ul>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Products<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="Products">Add Product</a></li>
                            <li><a href="manage-products">Manage Products</a></li>
                        </ul>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Coupons<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="coupon">Add Coupons</a></li>
                            <li><a href="manage-coupon">Manage Coupons</a></li>
                        </ul>
                    </li>
					
					<?php */?>
					
					<?php if($result_LoGuserDtails['shop_onboard']=="1"){?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Shop (Retailers)<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="add_ss">Add Shop</a></li>
                            <li><a href="manage_ss">Manage Shop</a></li>
							<li><a href="user-invoice-add?invuser=shop">Add Invoice</a></li>
                            <li><a href="user-manage-invoice?invuser=shop">Manage Invoice</a></li>
                        </ul>
                    </li>
					<?php }?>
					
					<!-----<li>
                        <a href="#"><i class="material-icons-two-tone">view_agenda</i>Invoice<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                        </ul>
                    </li>---->
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">star</i>Customer<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="add-customer">Add Customer</a></li>
                            <li><a href="manage-customer">Manage Customer</a></li>
							<li><a href="customer-user-invoice-add?invuser=customer">Add Invoice</a></li>
                            <li><a href="customer-user-manage-invoice">Manage Invoice</a></li>
							
                        </ul>
                    </li>
					
					<li>
                        <a href="purchasebill.php" class="active"><i class="material-icons-two-tone">inbox</i>Purchased Bill Copy</a>
                    </li>
					 
					 <li>
                        <a href="acmanager" class="active"><i class="material-icons-two-tone">info</i>Account Manager</a>
                    </li>
					
					 <li>
                        <a href=""><i class="material-icons-two-tone">security</i>Security<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="Myprofile">My Profile</a></li>
                            <li>
                                <a href="change-password">Change Password</a>
                            </li>
                            <li>
                                <a href="logout" onclick="return confirm('You want to logout confirm?');">Logout</a>
                            </li>
                        </ul>
                    </li>
					
					
					
                </ul>
				
<?php }?>

            </div>