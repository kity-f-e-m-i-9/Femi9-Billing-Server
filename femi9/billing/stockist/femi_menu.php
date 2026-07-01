<div class="app-menu">

<?php 
$select_LoGuserDtailsMN="select * from stockiest where username='".$_SESSION['LOGIN_USER']."'";
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
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Distributor<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Shop<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">star</i>Customer<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Users Network</a>
                    </li>
					
					 <li>
                        <a href=""><i class="material-icons-two-tone">security</i>Security<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="#">My Profile</a></li>
							 <li><a href="change-password.php">Change Password</a></li>
                            <li>
                                <a href="logout.php" onclick="return confirm('You want to logout confirm?');">Logout</a>
                            </li>
                        </ul>
                    </li>
					
                </ul>

<?php }else{?>

                <ul class="accordion-menu">
                    <li>
                        <a href="dashboard.php"><i class="material-icons-two-tone">dashboard</i>Dashboard</a>
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
						<li><a href="users_demo.php">Users Demo</a></li>
                        </ul>
                    </li>----->
					
					<!-----<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock Request<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="stock_request_pending.php">Pending</a></li>
						<li><a href="stock_request_billed.php">Billed</a></li>
						<li><a href="stock-request-add.php">Add Request</a></li>
                            <li><a href="stock-request-manage.php">Manage Request</a></li>
                        </ul>
                    </li>---->
					
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
					
					<!-----<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock Return<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="stock-return-add.php">Add Stock Return</a></li>
						<li><a href="stock-return-manage.php">Manage Stock Return</a></li>
						<li><a href="stock_return_pending.php">Pending</a></li>
						<li><a href="stock_return_accepted.php">Accepted</a></li>
						<li><a href="stock_return_rejected.php">Rejected</a></li>
                        </ul>
                    </li>---->
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="op-stock.php">Set Opening Stock</a></li>
						<li><a href="overall-stock.php">Overall Stock</a></li>
                        </ul>
                    </li>
					
					<!--<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Input Stock<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="add-input">Add Input stock</a></li>
						<li><a href="manage-input">Manage Input stock</a></li>
                        </ul>
                    </li>-->
					
					<!------<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Internal Stock Transfer<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="add_internal.php">Add Internal Stock Transfer</a></li>
						<li><a href="manage_internal.php">Manage Internal Stock Transfer</a></li>
                        </ul>
                    </li>---->
					
					<?php /*?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">cloud_queue</i>Pincode<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                           <li><a href="add-pincode.php">Add Pincode</a></li>
                            <li><a href="manage-pincode.php">Manage Pincode</a></li>
                        </ul>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">cloud_queue</i>Taluk<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                           <li><a href="add-taluk.php">Add Taluk</a></li>
                            <li><a href="manage-taluk.php">Manage Taluk</a></li>
                        </ul>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Products<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="Products.php">Add Product</a></li>
                            <li><a href="manage-products.php">Manage Products</a></li>
                        </ul>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Coupons<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="coupon.php">Add Coupons</a></li>
                            <li><a href="manage-coupon.php">Manage Coupons</a></li>
                        </ul>
                    </li>
					
					<?php */?>
					
					<!----------------------Super Distributor--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Super-Distributor<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="super_Distributor_manage">Manage Super-Distributor</a></li>
                            <li><a href="user-manage-invoice?invuser=super_distributor">Manage Invoice</a></li>
                        </ul>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Distributor<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
							<!---<li><a href="add_ss.php?usertype=OpenArea">Add Open Area</a></li>--->
                            <li><a href="manage_ss.php">Manage Distributor</a></li>
                            <li><a href="user-manage-invoice.php?invuser=distributor">Manage Invoice</a></li>
                        </ul>
                    </li>
					 
					 <li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Shop (Retailers)<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="Shop-manage.php">Manage Shop</a></li>
                            <li><a href="shop-user-manage-invoice.php">Manage Invoice</a></li>
                        </ul>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">star</i>Customer<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="manage-customer.php">Manage Customer</a></li>
                            <li><a href="customer-user-manage-invoice.php">Manage Invoice</a></li>
							
                        </ul>
                    </li>
					
					<li>
                        <a href="purchasebill.php" class="active"><i class="material-icons-two-tone">inbox</i>Purchased Bill Copy</a>
                    </li>
					
					<li>
                        <a href="distributor-network.php"><i class="material-icons-two-tone">done</i>Users Network</a>
                    </li>
					
					<li>
                        <a href="acmanager.php" class="active"><i class="material-icons-two-tone">info</i>Account Manager</a>
                    </li>
					
					 <li>
                        <a href=""><i class="material-icons-two-tone">security</i>Security<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="Myprofile.php">My Profile</a></li>
                            <li><a href="change-password.php">Change Password</a></li>
                            <li><a href="logout.php" onclick="return confirm('You want to logout confirm?');">Logout</a></li>
                        </ul>
                    </li>
					
                </ul>
				
<?php }?>
				
            </div>