<?php 
$selectusertypeGET="select * from admin_log where username='".$_SESSION['LOGIN_USER']."'";
$fetchusertypeGET=mysqli_query($db_conn,$selectusertypeGET);
$resultusertypeGET=mysqli_fetch_array($fetchusertypeGET);
$LoginusertypeGET=$resultusertypeGET['usertype'];
?>
<div class="app-menu">
                <ul class="accordion-menu">
                    
					<?php /*?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">view_agenda</i>Report<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li>
                        <a href="users-network-sls"><i class="material-icons-two-tone">done</i>Users Network - Sales Report</a>
                    </li>
					<li>
                        <a href="other-channel-sales-report"><i class="material-icons-two-tone">done</i>Other Channel Sales Report</a>
                    </li>
					<li>
                        <a href="purchase-order-report-pending"><i class="material-icons-two-tone">done</i>Purchase order Report - Pending</a>
                    </li>
					<li>
                        <a href="purchase-order-report"><i class="material-icons-two-tone">done</i>Purchase order Report - Completed</a>
                    </li>
					<li>
                        <a href="GST-report"><i class="material-icons-two-tone">done</i>GST Report</a>
                    </li>
					
                        </ul>
                    </li>
					<?php */?>
					
<!-------------------------------------------------------------------------------------------------->
<!--------------------------------------------FINANCE----------------------------------------------->
<!-------------------------------------------------------------------------------------------------->					
					<?php if($LoginusertypeGET=="finance"){?>
					
					<li>
                        <a href="dashboard"><i class="material-icons-two-tone">dashboard</i>Dashboard</a>
                    </li>
					
					<li>
                        <a href="Report_company" class="active"><i class="material-icons-two-tone">view_agenda</i>Report - Company</a>
                    </li>
					<li>
                        <a href="Report-First-Page" <?php if($current_page == 'Report-First-Page' || $current_page == 'Report-Details') echo 'class="active"'; ?>>
                            <i class="material-icons-two-tone">view_agenda</i>Report - B2B
                        </a>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">analytics</i>GST Reports<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="GSTR1">GSTR1</a></li>
						<li><a href="GSTR3B">GSTR3B</a></li>
                        </ul>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Internal Stock Transfer<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="internal_transfer">Add Internal Stock Transfer</a></li>
						<li><a href="internal_transfer_manage">Manage Internal Stock Transfer</a></li>
                        </ul>
                    </li>
					
					<!-----<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock Return<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="stock_return_pending">Pending</a></li>
						<li><a href="stock_return_accepted">Accepted</a></li>
						<li><a href="stock_return_rejected">Rejected</a></li>
                        </ul>
                    </li>---->
					
					<li class="active-page">
                        <a href="stock_request_pending_accounts" class="active"><i class="material-icons-two-tone">done</i>Stock Request</a>
                    </li>

					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Input Stock<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="add-input">Add Input Stock</a></li>
                        <li><a href="manage-input">Manage Input Stocks</a></li>
						<li><a href="add-input-users">Add Input Stock Users</a></li>
                        <li><a href="manage-input-users">Manage Input Stocks Users</a></li>
                        </ul>
                    </li>

					<li>
                        <a href="overall-stock"><i class="material-icons-two-tone">done</i>Overall Stock</a>
                    </li>

					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>OT Channel Sales<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="ot-sale-cat">Category</a></li>
						<li><a href="ot-sale-add">Add Sale</a></li>
						<li><a href="ot-sale-view">Manage Sales</a></li>
						<li><a href="ot-sale-manage-return">Manage Return</a></li>
                        </ul>
                    </li>
					<li>
                        <a href="OT-Report-Detail-Page"><i class="material-icons-two-tone">view_agenda</i>Report - OT Channel</a>
                    </li>


					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Delivery Note(INV)<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="invoiceDLN?invuser=super_stockiest">SS Invoice</a></li>
							<li><a href="invoiceDLN?invuser=stockiest">Stockist Invoice</a></li>
							<li><a href="invoiceDLN?invuser=distributor">Distributor Invoice</a></li>
							<li><a href="ShopDLN">Shop Invoice</a></li>
							<li><a href="CustomerDLN">Customer Invoice</a></li>
                        </ul>
                    </li>
					
					<li>
                        <a href=""><i class="material-icons-two-tone">security</i>Security<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li>
                                <a href="logout" onclick="return confirm('You want to logout confirm?');">Logout (Finance)</a>
                            </li>
                        </ul>
                    </li>
					<?php }?>
					
					
<!-------------------------------------------------------------------------------------------------->
<!--------------------------------------------ADMIN------------------------------------------------->
<!-------------------------------------------------------------------------------------------------->

                    <?php 
                    // Get current page name
                    $current_page = basename($_SERVER['PHP_SELF'], '.php');
                    ?>
					
					<?php if($LoginusertypeGET=="admin"){?>

					<li>
                        <a href="dashboard"><i class="material-icons-two-tone">dashboard</i>Dashboard</a>
                    </li>

					<!----------------------New section--------------------------->
					<li style="padding:10px 30px 4px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:1px;cursor:default;pointer-events:none;">New</li>
					<li>
                        <a href="manage-partner-location"><i class="material-icons-two-tone">place</i>Partner Location</a>
                    </li>
					<li>
                        <a href="manage-partner-location-layers"><i class="material-icons-two-tone">layers</i>Location Layers</a>
                    </li>
					<!----------------------Channel Partner--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">handshake</i>Channel Partner<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
							<li><a href="add-channel-partner">Add Channel Partner</a></li>
							<li><a href="manage-channel-partner">Manage Channel Partner</a></li>
							<li><a href="cp-stock">CP Stock</a></li>
                        </ul>
                    </li>
					<!----------------------Territory Partner--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">map</i>Territory Partner<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
							<li><a href="add-territory-partner">Add Territory Partner</a></li>
							<li><a href="manage-territory-partner">Manage Territory Partner</a></li>
							<li><a href="tp-stock">TP Stock</a></li>
							<li><a href="add-tp-input-stock">Add TP Input Stock</a></li>
							<li><a href="manage-tp-invoices">TP Invoices</a></li>
								<li><a href="tp-cnote-manage">TP Credit Notes</a></li>
							<li><a href="manage-tp-advance-payments">TP Advance Payments</a></li>
                        </ul>
                    </li>
					<!----------------------Stock Transfers--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">swap_horiz</i>Stock Transfers<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
							<li><a href="add-godown-to-location">Godown → Location</a></li>
							<li><a href="add-location-to-godown">Location → Godown</a></li>
							<li><a href="manage-pl-godown-transfers">All Transfers</a></li>
                        </ul>
                    </li>
					<li><span class="divider"></span></li>

                    <li>
                        <a href="#"><i class="material-icons-two-tone">wallet</i>Reports<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="mis-report"><i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;margin-right:3px;">assessment</i>MIS Report</a></li>
						<li><a href="Report_company">Company</a></li>
						<li><a href="Report-First-Page">B2B</a></li>
						<li><a href="Retail-Report-First-Page">Retail</a></li>
						<li><a href="Retail-Report-Details-Distributor.php">Retail (D/SD)</a></li>
						<li><a href="OT-Report-Detail-Page">OT Channel</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#"><i class="material-icons-two-tone">money</i>Payment Entry<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="add-advance-payment">Add Payment Entry</a></li>
						<li><a href="manage-advance-payments">Manage Payment Entry</a></li>
						<li><a href="consolidated-manage-advance-payments">Consolidated Payment Entry</a></li>
						<li><a href="bonus-points-calculator">Bonus Calculator</a></li>
						<li><a href="bonus-advance-payments">Manage Bonus Points</a></li>
                        </ul>
                    </li>
					
					<li>
                        <a href="godown"><i class="material-icons-two-tone">dashboard</i>Company Profile</a>
                    </li>
					
					<li>
                    <a href="wallet_request"><i class="material-icons-outlined">wallet</i>Withdraw</a>
                    </li>
                   
					<li>
                        <a href="#"><i class="material-icons-two-tone">wallet</i>Wallet History<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="cashback_report">Cashback History</a></li>
						<li><a href="referral_report">Referral Commission History</a></li>
						<li><a href="available_wallet">Available Wallet</a></li>
										<li><a href="available_wallet_cp">CP Wallet</a></li>
                        </ul>
                    </li>
					
					<!-----<li>
                        <a href="users_demo"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Users Demo</a>
                    </li>---->
					
					<!---------Reward Points---------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">analytics</i>Reward Points<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="reward_points?pgeusr=1ss&&femiusr=super_stockiest">Super Stockist's</a></li>
						<li><a href="reward_points?pgeusr=2st&&femiusr=stockiest">Stockist's</a></li>
						<li><a href="reward_points?pgeusr=3dst&&femiusr=distributor">Distributor's</a></li>
						<li><a href="reward_points?pgeusr=4sdst&&femiusr=super_distributor">Super Distributor's</a></li>
						<li><a href="reward_points_tp">Territory Partner's</a></li>
						<li><a href="Daily-Login-Report-First-Page">Daily Login Points</a></li>
                        </ul>
                    </li>
					<!--<li>
                        <a href="#"><i class="material-icons-two-tone">analytics</i>Reward Points: Sls<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="reward_points_sls?pgeusr=1ss&&femiusr=super_stockiest">Super Stockist's</a></li>
						<li><a href="reward_points_sls?pgeusr=2st&&femiusr=stockiest">Stockist's</a></li>
						<li><a href="reward_points_sls?pgeusr=3dst&&femiusr=distributor">Distributor's</a></li>
						<li><a href="reward_points_sls?pgeusr=4sdst&&femiusr=super_distributor">Super Distributor's</a></li>
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
					<a href="cnote_manage?invuser=super_stockiest"><i class="material-icons-two-tone">done</i>Manage Return<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
					</li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Debit Note<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="add-return">Add Return Stock</a></li>
                        <li><a href="manage-return">Manage Return Stocks</a></li>
                        </ul>
                    </li>
					
					<!-------<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock Return<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="stock_return_pending">Pending</a></li>
						<li><a href="stock_return_accepted">Accepted</a></li>
						<li><a href="stock_return_rejected">Rejected</a></li>
                        </ul>
                    </li>----->
					
					<!------<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock Request<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="stock_request_pending">Pending</a></li>
						<li><a href="stock_request_billed">Billed</a></li>
                        </ul>
                    </li>----->
					
					<!----------------------Products--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Products<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="Products">Add Product</a></li>
                        <li><a href="manage-products">Manage Products</a></li>
						<li><a href="op-stock">Set Opening Stock</a></li>
						<li><a href="overall-stock">Overall Stock</a></li>
						<li><a href="Stock-First-Page">User Stock</a></li>
						<li><a href="Competitor-brand">Add Competitor Brand</a></li>
                        <li><a href="Competitor-brand-manage">Manage Competitor Brand</a></li>
                        </ul>
                    </li>
					
					<!----------------------Input Stock--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Input Stock<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="add-input">Add Input Stock</a></li>
                        <li><a href="manage-input">Manage Input Stocks</a></li>
						<li><a href="add-input-users">Add Input Stock Users</a></li>
                        <li><a href="manage-input-users">Manage Input Stocks Users</a></li>
                        </ul>
                    </li>
					
					<!----------------------OT Channel Sales--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>OT Channel Sales<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="ot-sale-cat">Category</a></li>
						<li><a href="ot-sale-add">Add Sale</a></li>
						<li><a href="ot-sale-view">Manage Sales</a></li>
						<li><a href="ot-sale-manage-return">Manage Return</a></li>
                        </ul>
                    </li>
					
					<!----------------------Locations--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">cloud_queue</i>Location<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="add-country">Add Country</a></li>
                           <li><a href="manage-country">Manage Country</a></li>
                           <li><a href="add-state">Add State</a></li>
                           <li><a href="manage-state">Manage State</a></li>
                           <li><a href="add-district">Add District</a></li>
                           <li><a href="manage-district">Manage District</a></li>
                           <li><a href="add-taluk">Add Taluk</a></li>
                           <li><a href="manage-taluk">Manage Taluk</a></li>
                           <li><a href="add-pincode">Add Pincode</a></li>
                           <li><a href="manage-pincode">Manage Pincode</a></li>
							
							<!----<li><a href="upload-state">Upload Bulk State</a></li>
							 <li><a href="upload-district">Upload Bulk District</a></li>
							 <li><a href="upload-taluk">Upload Bulk Taluk</a></li>
							 <li><a href="upload-pincode">Upload Bulk Pincode</a></li>--->
                        </ul>
                    </li>

					<!-----<li class="active-page">
                        <a href="manage-coupon"><i class="material-icons-two-tone">done</i>Coupons</a>
                    </li>---->
					<!----
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Coupons<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="coupon">Add Coupons</a></li>
                            <li><a href="manage-coupon">Manage Coupons</a></li>
                        </ul>
                    </li>--->
					
					<li>
					<!----------------------C&F--------------------------->
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>C&F<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="add_cf">Add C&F</a></li>
                            <li><a href="manage_cf">Manage&nbsp;C&F</a></li>
							<li><a href="user-invoice-add?invuser=candf">Add Invoice</a></li>
                            <li><a href="user-manage-invoice?invuser=candf">Manage Invoice</a></li>
                        </ul>
                    </li>
					
					<!----------------------Super Stockist--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Super Stockist<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="add_ss">Add Super Stockist</a></li>
                            <li><a href="cat-view-ss">Manage&nbsp;Category</a></li>
                            <li><a href="manage_ss">Manage&nbsp;Super&nbsp;Stockist</a></li>
							<li><a href="user-invoice-add?invuser=super_stockiest">Add Invoice</a></li>
                            <li><a href="user-manage-invoice?invuser=super_stockiest">Manage Invoice</a></li>
                            <li><a href="deactiveusers?invuser=super_stockiest">Deactive Users</a></li>
                        </ul>
                    </li>
					
					<!----------------------Stockist--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Stockist<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="cat-add-st">Add Category</a></li>
                            <li><a href="cat-view-st">Manage Category</a></li>
							<!----<li><a href="assigned_taluk.php">Assigned Taluk</a></li>--->
                            <li><a href="stockist-add">Add Stockist</a></li>
                            <li><a href="stockist-manage">Manage Stockist</a></li>
							<li><a href="user-invoice-add?invuser=stockiest">Add Invoice</a></li>
                            <li><a href="user-manage-invoice?invuser=stockiest">Manage Invoice</a></li>
							<li><a href="overallusers_stockist?invuser=stockiest">Overall&nbsp;Stockist</a></li>
							<li><a href="deactiveusers?invuser=stockiest">Deactive Users</a></li>
                        </ul>
                    </li>
					
					
					<!----------------------Super Distributor--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Super-Distributor<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="super_Distributor_add">Add Super-Distributor</a></li>
                            <li><a href="super_Distributor_manage">Manage Super-Distributor</a></li>
                            <li><a href="cat-view-sdt">Manage Category</a></li>
							<li><a href="user-invoice-add?invuser=super_distributor">Add Invoice</a></li>
                            <li><a href="user-manage-invoice?invuser=super_distributor">Manage Invoice</a></li>
							<li><a href="super_Distributor_overallusers?invuser=super_distributor">Overall&nbsp;Super-Distributor</a></li>
							<li><a href="super_Distributor_overallusers2">Onboardwise&nbsp;Super-Distributor</a></li>
							<li><a href="deactiveusers?invuser=super_distributor">Deactive Users</a></li>
                        </ul>
                    </li>
					
					<!----------------------Distributor--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Distributor<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="Distributor-add?usertype=Distributor">Add Distributor</a></li>
							<!----<li><a href="Distributor-add?usertype=OpenArea">Add Open Area</a></li>---->
                            <li><a href="Distributor-manage">Manage Distributor</a></li>
                            <li><a href="cat-view-dt">Manage Category</a></li>
							<li><a href="user-invoice-add?invuser=distributor">Add Invoice</a></li>
                            <li><a href="user-manage-invoice?invuser=distributor">Manage Invoice</a></li>
							<li><a href="overallusers?invuser=distributor">Overall&nbsp;Distributor</a></li>
							<li><a href="overallusers2">Onboardwise&nbsp;Distributor</a></li>
							<li><a href="deactiveusers?invuser=distributor">Deactive Users</a></li>
                        </ul>
                    </li>
					
					
					<!----------------------Shop(Retailers)--------------------------->
					<li>
                       <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Shop (Retailers)
<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="shop-cat-add">Add Category</a></li>
                            <li><a href="shop-cat-manage">Manage Category</a></li>
                            <li><a href="Shop-add">Add Shop</a></li>
                            <li><a href="Shop-manage">Manage Shop</a></li>
                            <li><a href="overallusers_shop?invuser=shop">Overall&nbsp;Shop</a></li>
							<li><a href="shop-user-invoice-add?invuser=shop">Add Invoice</a></li>
                            <li><a href="shop-user-manage-invoice">Manage Invoice</a></li>
                        </ul>
                    </li>
					
					<!----------------------Outlet--------------------------->
					<!------<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Outlet<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="Outlet-add">Add Outlet</a></li>
                            <li><a href="Outlet-manage">Manage Outlet</a></li>
							<li><a href="user-invoice-add?invuser=outlet">Add Invoice</a></li>
                            <li><a href="user-manage-invoice?invuser=outlet">Manage Invoice</a></li>
                        </ul>
                    </li>---->
					
					<!----------------------Customers--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">star</i>Customer<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="add-customer">Add Customer</a></li>
                            <li><a href="manage-customer">Manage Customer</a></li>
							<li><a href="customer-user-invoice-add?invuser=customer">Add Invoice</a></li>
                            <li><a href="customer-user-manage-invoice">Manage Invoice</a></li>
							
                        </ul>
                    </li>
					
					<!----------------------Marketting Staff--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">star</i>Marketing Staff<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="ms_add">Add Marketing Staff</a></li>
                            <li><a href="ms_manage">Manage Marketing Staff</a></li>
							<li><a href="ms_prorders">Product Orders Report</a></li>
							<li><a href="ms_noorders">No Orders Report</a></li>
							<li><a href="ms_expenses">Expenses Report</a></li>
                        </ul>
                    </li>
					
					<!-----<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Users<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="Stockist">Stockist</a></li>
                        </ul>
                    </li>---->
					
					<!----------------------Unassigned--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Unassigned<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="undistrict">District</a></li>
							<li><a href="untaluk">Taluk</a></li>
							<!----<li><a href="unpincode">Pincode</a></li>--->
                        </ul>
                    </li>
					
					<!----------------------Re-mapping--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">card_giftcard</i>Re-mapping<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="remapping-sst">Stockist</a></li>
							<li><a href="remapping_distributor">Distributor</a></li>
							<li><a href="remapping_superdistributor">Super Distributor</a></li>
							<li><a href="remapping_shop">Shop</a></li>
							<li><a href="remapping_customer">Customer</a></li>
							<li><a href="remapping-tp-ss.php">Territory Partner</a></li>
                        </ul>
                    </li>
					 
					 <!----------------------Users Network--------------------------->
					 <li>
                        <a href="users-network"><i class="material-icons-two-tone">done</i>Users Network</a>
                    </li>
					
					<!----------------------Offers--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">settings</i>Offers<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="offers_add">Add Offers</a></li>
                            <li><a href="offers_manage">Manage Offers</a></li>
                        </ul>
                    </li>
					
					<!----------------------Top Performers--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">badge</i>Top Performers<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="performers_add">Add Performers</a></li>
                            <li><a href="performers_manage">Manage Performers</a></li>
                        </ul>
                    </li>
					
					<!----------------------Scroll Message--------------------------->
					<li>
                        <a href="Update_scroll_msg"><i class="material-icons-two-tone">message</i>Scroll Message</a>
                    </li>
					
					<!----------------------Users--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">people</i>Users<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="users_add">Add Users</a></li>
                            <li><a href="users_manage">Manage Users</a></li>
                        </ul>
                    </li>
					
					<!----------------------Security--------------------------->
					<li>
                        <a href=""><i class="material-icons-two-tone">security</i>Security<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li>
                                <a href="change-password">Change Password</a>
                            </li>
                            <li>
                                <a href="logout" onclick="return confirm('You want to logout confirm?');">Logout (Admin)</a>
                            </li>
                        </ul>
                    </li>
					
					<?php }?>
					
					
					
<!-------------------------------------------------------------------------------------------------->
<!--------------------------------------------USERS------------------------------------------------->
<!-------------------------------------------------------------------------------------------------->	

<?php if($LoginusertypeGET=="users"){?>


                    <?php if($resultusertypeGET['dash']==1){?>
                    <li><a href="dashboard"><i class="material-icons-two-tone">dashboard</i>Dashboard</a></li>
                    <?php }?>

					<!----------------------New section (users)--------------------------->
					<li style="padding:10px 30px 4px;font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:1px;cursor:default;pointer-events:none;">New</li>
					<li>
                        <a href="manage-partner-location"><i class="material-icons-two-tone">place</i>Partner Location</a>
                    </li>
					<li>
                        <a href="manage-partner-location-layers"><i class="material-icons-two-tone">layers</i>Location Layers</a>
                    </li>
					<!----------------------Channel Partner--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">handshake</i>Channel Partner<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
							<li><a href="add-channel-partner">Add Channel Partner</a></li>
							<li><a href="manage-channel-partner">Manage Channel Partner</a></li>
							<li><a href="cp-stock">CP Stock</a></li>
                        </ul>
                    </li>
					<!----------------------Territory Partner--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">map</i>Territory Partner<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
							<li><a href="add-territory-partner">Add Territory Partner</a></li>
							<li><a href="manage-territory-partner">Manage Territory Partner</a></li>
							<li><a href="tp-stock">TP Stock</a></li>
							<li><a href="add-tp-input-stock">Add TP Input Stock</a></li>
							<li><a href="manage-tp-invoices">TP Invoices</a></li>
								<li><a href="tp-cnote-manage">TP Credit Notes</a></li>
							<li><a href="manage-tp-advance-payments">TP Advance Payments</a></li>
                        </ul>
                    </li>
					<!----------------------Stock Transfers--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">swap_horiz</i>Stock Transfers<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
							<li><a href="add-godown-to-location">Godown → Location</a></li>
							<li><a href="add-location-to-godown">Location → Godown</a></li>
							<li><a href="manage-pl-godown-transfers">All Transfers</a></li>
                        </ul>
                    </li>
					<li><span class="divider"></span></li>

					<?php if($resultusertypeGET['report']==1){?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">wallet</i>Reports<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="mis-report"><i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;margin-right:3px;">assessment</i>MIS Report</a></li>
						<li><a href="Report_company">Company</a></li>
						<li><a href="Report-First-Page">B2B</a></li>
						<li><a href="Retail-Report-First-Page">Retail</a></li>
						<li><a href="OT-Report-Detail-Page">OT Channel</a></li>
                        </ul>
                    </li>
					<?php }?>
					
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">money</i>Payment Entry<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                        <?php if($resultusertypeGET['payment_entry']==1){?>    
						    <li><a href="add-advance-payment">Add Payment Entry</a></li>
						<?php }?>
						<?php if($resultusertypeGET['manage_payment_entry']==1){?>
						    <li><a href="manage-advance-payments.php">Manage Payment Entry</a></li>
						<?php }?>
						<?php if($resultusertypeGET['consolidated_payment_entry']==1){?>
						    <li><a href="consolidated-manage-advance-payments">Consolidated Payment Entry</a></li>
						<?php }?>
						<?php if($resultusertypeGET['bonus_calculator']==1){?>
						    <li><a href="bonus-points-calculator">Bonus Calculator</a></li>
						<?php }?>
						<?php if($resultusertypeGET['manage_bonus_points']==1){?>
						    <li><a href="bonus-advance-payments">Manage Bonus Points</a></li>
						<?php }?>    
                        </ul>
                    </li>
                    
					
					<?php if($resultusertypeGET['company_profile']==1){?>
					<li>
                        <a href="godown"><i class="material-icons-two-tone">dashboard</i>Company Profile</a>
                    </li>
					
					<?php }?>
					
					<?php if($resultusertypeGET['users_demo']==1){?>
					<li>
                        <a href="users_demo"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Users Demo</a>
                    </li>
					
					<?php }?>
					
					<?php if($resultusertypeGET['reward_points']==1){?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">analytics</i>Reward Points<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="reward_points?pgeusr=1ss&&femiusr=super_stockiest">Super Stockist's</a></li>
						<li><a href="reward_points?pgeusr=2st&&femiusr=stockiest">Stockist's</a></li>
						<li><a href="reward_points?pgeusr=3dst&&femiusr=distributor">Distributor's</a></li>
						<li><a href="reward_points?pgeusr=4sdst&&femiusr=super_distributor">Super Distributor's</a></li>
										<li><a href="reward_points_tp">Territory Partner's</a></li>
                        </ul>
                    </li>
					<?php }?>
					
					<?php if($resultusertypeGET['demo_free']==1){?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Demo/Free/Damage<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="demofree_new">Add Demo/Free/Damage</a></li>
						<li><a href="demofree_manage">Manage Demo/Free/Damage</a></li>
                        </ul>
                    </li>
					<?php }?>
					
					<?php if($resultusertypeGET['manage_return']==1){?>
					<<li>
					<a href="cnote_manage?invuser=super_stockiest"><i class="material-icons-two-tone">done</i>Manage Return<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
					</li>
					<?php }?>
					
					<?php if($resultusertypeGET['debit_note']==1){?>					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Debit Note<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="add-return">Add Return Stock</a></li>
                        <li><a href="manage-return">Manage Return Stocks</a></li>
                        </ul>
                    </li>
					<?php }?>
					
					<?php if($resultusertypeGET['stock_request']==1){?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Stock Request<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="stock_request_pending">Pending</a></li>
						<li><a href="stock_request_billed">Billed</a></li>
                        </ul>
                    </li>
					<?php }?>
					
					<?php if($resultusertypeGET['products']==1){?>					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Products<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="Products">Add Product</a></li>
                        <li><a href="manage-products">Manage Products</a></li>
						<li><a href="op-stock">Set Opening Stock</a></li>
						<li><a href="overall-stock">Overall Stock</a></li>
						<li><a href="Competitor-brand">Add Competitor Brand</a></li>
                        <li><a href="Competitor-brand-manage">Manage Competitor Brand</a></li>
                        </ul>
                    </li>
					<?php }?>
					
										
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>Input Stock<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                        <?php if($resultusertypeGET['add_input_stock']==1){?>    
						    <li><a href="add-input">Add Input Stock</a></li>
						<?php }?>
						<?php if($resultusertypeGET['manage_input_stock']==1){?> 
                            <li><a href="manage-input">Manage Input Stocks</a></li>
                        <?php }?>
                        <?php if($resultusertypeGET['add_input_stock_users']==1){?> 
                            <li><a href="add-input-users">Add Input Stock Users</a></li>
                        <?php }?>
                        <?php if($resultusertypeGET['manage_input_stock_users']==1){?> 
                            <li><a href="manage-input-users">Manage Input Stocks Users</a></li>
                        <?php }?>
                        </ul>
                    </li>
					
					
					<?php if($resultusertypeGET['ot_channels']==1){?>					
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>OT Channel Sales<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="ot-sale-cat">Category</a></li>
						<li><a href="ot-sale-add">Add Sale</a></li>
						<li><a href="ot-sale-view">Manage Sales</a></li>
						<li><a href="ot-sale-manage-return">Manage Return</a></li>
                        </ul>
                    </li>
					<?php }?>
					
					<?php if($resultusertypeGET['location']==1){?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">cloud_queue</i>Location<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="add-country">Add Country</a></li>
                           <li><a href="manage-country">Manage Country</a></li>
                           <li><a href="add-state">Add State</a></li>
                           <li><a href="manage-state">Manage State</a></li>
                           <li><a href="add-district">Add District</a></li>
                           <li><a href="manage-district">Manage District</a></li>
                           <li><a href="add-taluk">Add Taluk</a></li>
                           <li><a href="manage-taluk">Manage Taluk</a></li>
                           <li><a href="add-pincode">Add Pincode</a></li>
                           <li><a href="manage-pincode">Manage Pincode</a></li>
                        </ul>
                    </li>
					<?php }?>

					<?php if($resultusertypeGET['ss']==1){?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Super Stockist<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="add_ss">Add Super Stockist</a></li>
                            <li><a href="cat-view-ss">Manage&nbsp;Category</a></li>
                            <li><a href="manage_ss">Manage&nbsp;Super&nbsp;Stockist</a></li>
							<li><a href="user-invoice-add?invuser=super_stockiest">Add Invoice</a></li>
                            <li><a href="user-manage-invoice?invuser=super_stockiest">Manage Invoice</a></li>
                            <li><a href="deactiveusers?invuser=super_stockiest">Deactive Users</a></li>
                        </ul>
                    </li>
					<?php }?>
					
					<?php if($resultusertypeGET['st']==1){?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Stockist<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="cat-add-st">Add Category</a></li>
                            <li><a href="cat-view-st">Manage Category</a></li>
							<!----<li><a href="assigned_taluk.php">Assigned Taluk</a></li>--->
                            <li><a href="stockist-add">Add Stockist</a></li>
                            <li><a href="stockist-manage">Manage Stockist</a></li>
							<li><a href="user-invoice-add?invuser=stockiest">Add Invoice</a></li>
                            <li><a href="user-manage-invoice?invuser=stockiest">Manage Invoice</a></li>
							<li><a href="overallusers_stockist?invuser=stockiest">Overall&nbsp;Stockist</a></li>
							<li><a href="deactiveusers?invuser=stockiest">Deactive Users</a></li>
                        </ul>
                    </li>
					<?php }?>
					
					<?php if($resultusertypeGET['sdt']==1){?>	
					
					<!----------------------Super Distributor--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Super-Distributor<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="super_Distributor_add">Add Super-Distributor</a></li>
                            <li><a href="super_Distributor_manage">Manage Super-Distributor</a></li>
							<li><a href="user-invoice-add?invuser=super_distributor">Add Invoice</a></li>
                            <li><a href="user-manage-invoice?invuser=super_distributor">Manage Invoice</a></li>
							<li><a href="super_Distributor_overallusers?invuser=super_distributor">Overall&nbsp;Super-Distributor</a></li>
							<li><a href="super_Distributor_overallusers2">Onboardwise&nbsp;Super-Distributor</a></li>
							<li><a href="deactiveusers?invuser=super_distributor">Deactive Users</a></li>
                        </ul>
                    </li>
					<?php }?>
					
					<?php if($resultusertypeGET['dt']==1){?>
					<!----------------------Distributor--------------------------->
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Distributor<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="Distributor-add?usertype=Distributor">Add Distributor</a></li>
							<!----<li><a href="Distributor-add?usertype=OpenArea">Add Open Area</a></li>---->
                            <li><a href="Distributor-manage">Manage Distributor</a></li>
							<li><a href="user-invoice-add?invuser=distributor">Add Invoice</a></li>
                            <li><a href="user-manage-invoice?invuser=distributor">Manage Invoice</a></li>
							<li><a href="overallusers?invuser=distributor">Overall&nbsp;Distributor</a></li>
							<li><a href="deactiveusers?invuser=distributor">Deactive Users</a></li>

                        </ul>
                    </li>
					
					<?php }?>
					
					<?php if($resultusertypeGET['shop']==1){?>
					<li>
                       <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Shop (Retailers)
<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
						<li><a href="shop-cat-add">Add Category</a></li>
                            <li><a href="shop-cat-manage">Manage Category</a></li>
                            <li><a href="Shop-add">Add Shop</a></li>
                            <li><a href="Shop-manage">Manage Shop</a></li>
							<li><a href="shop-user-invoice-add?invuser=shop">Add Invoice</a></li>
                            <li><a href="shop-user-manage-invoice">Manage Invoice</a></li>
                        </ul>
                    </li>
					<?php }?>
					
					<?php if($resultusertypeGET['cus']==1){?>					
					<li>
                        <a href="#"><i class="material-icons-two-tone">star</i>Customer<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="add-customer">Add Customer</a></li>
                            <li><a href="manage-customer">Manage Customer</a></li>
							<li><a href="customer-user-invoice-add?invuser=customer">Add Invoice</a></li>
                            <li><a href="customer-user-manage-invoice">Manage Invoice</a></li>
							
                        </ul>
                    </li>
					<?php }?>
					
					<?php if($resultusertypeGET['ms']==1){?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">star</i>Marketing Staff<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="ms_add">Add Marketing Staff</a></li>
                            <li><a href="ms_manage">Manage Marketing Staff</a></li>
							<li><a href="ms_prorders">Product Orders Report</a></li>
							<li><a href="ms_noorders">No Orders Report</a></li>
                        </ul>
                    </li>
					<?php }?>
					
					<?php if($resultusertypeGET['unassigned']==1){?>					
					<li>
                        <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Unassigned<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="undistrict">District</a></li>
							<li><a href="untaluk">Taluk</a></li>
							<!----<li><a href="unpincode">Pincode</a></li>--->
                        </ul>
                    </li>
					<?php }?>
					
					<?php if($resultusertypeGET['remap']==1){?>
					<li>
                        <a href="#"><i class="material-icons-two-tone">card_giftcard</i>Re-mapping<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li><a href="remapping-sst">Stockist</a></li>
							<li><a href="remapping_distributor">Distributor</a></li>
							<li><a href="remapping_superdistributor">Super Distributor</a></li>
							<li><a href="remapping_shop">Shop</a></li>
							<li><a href="remapping-tp-ss.php">Territory Partner</a></li>
                        </ul>
                    </li>
					 <?php }?>
					
					<?php if($resultusertypeGET['users_network']==1){?>
					 <li>
                        <a href="users-network"><i class="material-icons-two-tone">done</i>Users Network</a>
                    </li>
					<?php }?>
					
					<li>
                        <a href=""><i class="material-icons-two-tone">security</i>Security<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li>
                                <a href="logout" onclick="return confirm('You want to logout confirm?');">Logout (Admin)</a>
                            </li>
                        </ul>
                    </li>


<?php }?>
                </ul>
            </div>