<?php
$_menu_tp_id     = (int)($Login_user_IDvl ?? 0);
$_menu_tp_row    = mysqli_fetch_assoc(mysqli_query($db_conn, "SELECT stock_initialized FROM territory_partners WHERE id='$_menu_tp_id' AND is_active=1 LIMIT 1"));
$_tp_can_invoice = $_menu_tp_row && (int)$_menu_tp_row['stock_initialized'] === 1;
?>
<div class="app-menu">
    <ul class="accordion-menu">

        <li>
            <a href="dashboard.php"><i class="material-icons-two-tone">dashboard</i>Dashboard</a>
        </li>

        <li>
            <a href="mis-report.php"><i class="material-icons-two-tone">assessment</i>MIS Report</a>
        </li>

        <li>
            <a href="#"><i class="material-icons-two-tone">assignment_turned_in</i>Field Order<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
            <ul class="sub-menu">
                <li><a href="add-order.php">Add Order</a></li>
                <li><a href="manage-orders.php">Manage Orders</a></li>
            </ul>
        </li>

        <!-- Reward Points -->
        <li>
            <a href="reward-points.php"><i class="material-icons-two-tone">analytics</i>Reward Points: Pur</a>
        </li>
        <li>
            <a href="reward-points-sls.php"><i class="material-icons-two-tone">analytics</i>Reward Points: Sls</a>
        </li>


        <li>
            <a href="wallet-history.php"><i class="material-icons-outlined">wallet</i>Wallet History</a>
        </li>

        <li>
            <a href="#"><i class="material-icons-two-tone">analytics</i>GST Reports<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
            <ul class="sub-menu">
                <li><a href="gstr1.php">GSTR1</a></li>
                <li><a href="gstr3b.php">GSTR3B</a></li>
            </ul>
        </li>

        <li>
            <a href="#"><i class="material-icons-two-tone">done</i>Demo/Free/Damage<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
            <ul class="sub-menu">
                <li><a href="demofree-new.php">Add Demo/Free/Damage</a></li>
                <li><a href="demofree-manage.php">Manage Demo/Free/Damage</a></li>
            </ul>
        </li>

        <li>
            <a href="manage-return.php"><i class="material-icons-two-tone">done</i>Manage Return</a>
        </li>

        <li>
            <a href="debit-note.php"><i class="material-icons-two-tone">done</i>Debit Note</a>
        </li>

        <li>
            <a href="#"><i class="material-icons-two-tone">done</i>Stock<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
            <ul class="sub-menu">
                <li><a href="overall-stock.php">Overall Stock</a></li>
            </ul>
        </li>

        <li>
            <a href="#"><i class="material-icons-two-tone">sentiment_satisfied_alt</i>Shop (Retailers)<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
            <ul class="sub-menu">
                <li><a href="shop-add.php">Add Shop</a></li>
                <li><a href="shop-manage.php">Manage Shop</a></li>
                <?php if ($_tp_can_invoice): ?><li><a href="shop-invoice-add.php?invuser=shop">Add Invoice</a></li><?php endif; ?>
                <li><a href="shop-manage-invoice.php">Manage Invoice</a></li>
            </ul>
        </li>

        <li>
            <a href="#"><i class="material-icons-two-tone">star</i>Customer<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
            <ul class="sub-menu">
                <li><a href="customer-add.php">Add Customer</a></li>
                <li><a href="customer-manage.php">Manage Customer</a></li>
                <?php if ($_tp_can_invoice): ?><li><a href="customer-invoice-add.php?invuser=customer">Add Invoice</a></li><?php endif; ?>
                <li><a href="customer-manage-invoice.php">Manage Invoice</a></li>
            </ul>
        </li>

        <li>
            <a href="purchased-bill.php"><i class="material-icons-two-tone">inbox</i>Purchased Bill Copy</a>
        </li>

        <li>
            <a href="account-manager.php"><i class="material-icons-two-tone">info</i>Account Manager</a>
        </li>

        <li>
            <a href=""><i class="material-icons-two-tone">security</i>Security<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
            <ul class="sub-menu">
                <li><a href="my-profile.php">My Profile</a></li>
                <li><a href="change-password.php">Change Password</a></li>
                <li><a href="logout.php" onclick="return confirm('You want to logout confirm?');">Logout</a></li>
            </ul>
        </li>

    </ul>
</div>
