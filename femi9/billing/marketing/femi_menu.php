<div class="app-menu">

<?php 
$select_LoGuserDtailsMN="select * from marketing_staff where ms_mobile='".$_SESSION['LOGIN_USER']."'";
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
                        <a href="#"><i class="material-icons-two-tone">done</i>Add Shop</a>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">view_agenda</i>Manage Shop</a>
                    </li>
					<li>
                        <a href="#"><i class="material-icons-two-tone">done</i>New Order</a>
                    </li>
					
					<li>
                        <a href="#"><i class="material-icons-two-tone">view_agenda</i>Manage Orders</a>
                    </li>
					
					
					
					 <li>
                        <a href=""><i class="material-icons-two-tone">security</i>Security<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
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
					
					<li>
                        <a href="add_ss"><i class="material-icons-two-tone">done</i>Add Shop</a>
                    </li>
					
					<li>
                        <a href="manage_ss.php"><i class="material-icons-two-tone">view_agenda</i>Manage Shop</a>
                    </li>
					
					
					<li>
                        <a href=""><i class="material-icons-two-tone">done</i>New Order
						<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li>
                                <a href="add_order?actorder=femi9getorder12aedrftgo23epmncl">Get Order</a>
                            </li>
							<li>
                                <a href="add_order?actorder=femi9noorder12aedrftgop2we4mncl">No Order</a>
                            </li>
                        </ul>
                    </li>
					
					
					<li>
                        <a href=""><i class="material-icons-two-tone">view_agenda</i>Manage Order's
						<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li>
                                <a href="manage_order_product">Product Orders</a>
                            </li>
							<li>
                                <a href="manage_order">No Orders</a>
                            </li>
                        </ul>
                    </li>
					
					
					<li>
                        <a href=""><i class="material-icons-two-tone">done</i>Expenses
						<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                            <li>
                                <a href="exp_add">Add Expenses</a>
                            </li>
							<li>
                                <a href="exp_manage">Manage Expenses</a>
                            </li>
                        </ul>
                    </li>
					
					<?php if($result_LoGuserDtails['user_position']==1){?>
					<li>
                        <a href=""><i class="material-icons-two-tone">done</i>Reports
						<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
                           <li><a href="ms_prorders">Product Orders Report</a></li>
							<li><a href="ms_noorders">No Orders Report</a></li>
							<!--<li><a href="stockist_sales_report">Stockist Sales Report</a></li>-->
                        </ul>
                    </li>
					<?php }?>
					
					 <li>
                        <a href=""><i class="material-icons-two-tone">security</i>Security<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
                        <ul class="sub-menu">
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