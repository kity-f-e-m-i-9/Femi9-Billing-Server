<?php
// This file is now included from two different folders: normally from
// salesbdm/ itself, but also from company/'s shared Territory Partner pages
// (see company/add-territory-partner.php etc.) when a BDM session is using
// them — the sidebar should look identical either way. Relative links below
// need a different prefix depending on which folder is actually serving the
// current request, so both link groups resolve correctly from either origin.
$_inCompanyFolder = (strpos($_SERVER['SCRIPT_NAME'], '/company/') !== false);
$_bdmBase = $_inCompanyFolder ? '../salesbdm/' : '';
$_companyBase = $_inCompanyFolder ? '' : '../company/';

// "Our Team" only shows for a BDM who actually has someone reporting to
// them (e.g. a Chief BDM) — a plain individual-contributor BDM has no team
// to view, so the link (and the page itself, my-team.php guards it too)
// stays hidden for them.
$_hasReports = false;
if (!empty($salesBdmID)) {
    $_repChk = $db_conn->query("SELECT 1 FROM sales_bdm_staff WHERE manager_id = " . (int)$salesBdmID . " LIMIT 1");
    $_hasReports = $_repChk && $_repChk->num_rows > 0;
}
?>
<div class="app-menu">

<style>
/* Safety net so the sidebar always looks right even if the shared
   stylesheet/font cache is stale for this new folder — duplicates the key
   framework rules (icon font, spacing, hover) as literal, unmissable CSS. */
.app-menu .accordion-menu { margin: 0; padding: 0; }
.app-menu .accordion-menu > li { list-style: none; }
.app-menu .accordion-menu > li > a {
    display: flex; align-items: center; gap: 10px;
    text-decoration: none; color: #40475c; font-size: 14px;
    margin: 3px 15px; padding: 12px 15px; border-radius: 6px; line-height: 20px;
    transition: background .15s ease-in-out;
}
.app-menu .accordion-menu > li > a:hover { background: #f5f7fa; }
.app-menu .accordion-menu .material-icons-two-tone,
.app-menu .accordion-menu .material-icons {
    font-family: 'Material Icons Two Tone', 'Material Icons', sans-serif !important;
    font-size: 20px; flex-shrink: 0; color: #667eea;
}
.app-menu .accordion-menu .has-sub-menu {
    margin-left: auto; font-size: 19px; color: #6d7b91;
    font-family: 'Material Icons', sans-serif !important;
}
.app-menu .accordion-menu .sub-menu {
    margin: 6px 15px 10px 30px; padding: 8px 0; border-radius: 10px;
    background: #f5f7fa; list-style: none;
}
.app-menu .accordion-menu .sub-menu li a {
    display: block; padding: 8px 20px; font-size: 13px; color: #40475c; text-decoration: none;
}
.app-menu .accordion-menu .sub-menu li a:hover { color: #667eea; }
</style>

<ul class="accordion-menu">
    <li>
        <a href="<?php echo $_bdmBase; ?>dashboard"><i class="material-icons-two-tone">dashboard</i>Dashboard</a>
    </li>

    <li>
        <a href="<?php echo $_bdmBase; ?>tp-purchase-order"><i class="material-icons-two-tone">receipt_long</i>TP Purchase Order</a>
    </li>

    <li>
        <a href="<?php echo $_bdmBase; ?>tp-advance-payment-report"><i class="material-icons-two-tone">payments</i>TP Advance Payment Report</a>
    </li>

    <li>
        <a href="<?php echo $_bdmBase; ?>reward-points-tp"><i class="material-icons-two-tone">analytics</i>Reward Points: TPs</a>
    </li>

    <li>
        <a href="<?php echo $_bdmBase; ?>pending-amount"><i class="material-icons-two-tone">account_balance_wallet</i>Pending Amount</a>
    </li>

    <li>
        <a href="<?php echo $_bdmBase; ?>tp-invoices"><i class="material-icons-two-tone">receipt_long</i>TP Invoices</a>
    </li>

    <?php if ($_hasReports): ?>
    <li>
        <a href=""><i class="material-icons-two-tone">groups</i>Our Team
            <i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
        <ul class="sub-menu">
            <li><a href="<?php echo $_bdmBase; ?>my-team">Tree View</a></li>
            <li><a href="<?php echo $_bdmBase; ?>my-team-report">Our Team Report</a></li>
        </ul>
    </li>
    <?php endif; ?>

    <li>
        <a href=""><i class="material-icons-two-tone">map</i>Territory Partner
            <i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
        <ul class="sub-menu">
            <li><a href="<?php echo $_companyBase; ?>add-territory-partner">Add Territory Partner</a></li>
            <li><a href="<?php echo $_companyBase; ?>manage-territory-partner">Manage Territory Partner</a></li>
        </ul>
    </li>

    <li>
        <a href="<?php echo $_bdmBase; ?>courier-amount-requests"><i class="material-icons-two-tone">local_shipping</i>Courier Amount Requests</a>
    </li>

    <li>
        <a href=""><i class="material-icons-two-tone">security</i>Security<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
        <ul class="sub-menu">
            <li><a href="<?php echo $_bdmBase; ?>change-password">Change Password</a></li>
            <li>
                <a href="<?php echo $_bdmBase; ?>logout" onclick="return confirm('You want to logout confirm?');">Logout</a>
            </li>
        </ul>
    </li>
</ul>

</div>
