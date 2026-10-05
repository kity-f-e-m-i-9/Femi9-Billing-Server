<?php 
include("checksession.php");
include("config.php"); 

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// ========================================
// CHECK IF USER MUST CHANGE PASSWORD
// ========================================
$userMobile = $result_LoGuserDtails['ms_mobile'];
$userType = 'marketing'; // Change based on user type

// Check if user has a pending password reset
$checkResetStmt = mysqli_prepare($db_conn, 
    "SELECT id, reset_at FROM forgotpassword 
     WHERE usertype = ? AND mobilenumber = ? AND must_change_password = 1 
     ORDER BY reset_at DESC LIMIT 1"
);
mysqli_stmt_bind_param($checkResetStmt, "ss", $userType, $userMobile);
mysqli_stmt_execute($checkResetStmt);
$resetResult = mysqli_stmt_get_result($checkResetStmt);
$resetData = mysqli_fetch_assoc($resetResult);
mysqli_stmt_close($checkResetStmt);

// If user has pending password reset, force them to change password
if ($resetData) {
    echo "<script>
        alert('For security reasons, you must change your password before continuing.');
        window.location='change-password.php?forced=1';
    </script>";
    exit;
}
// ========================================


//INSERT ATTENDANCE
date_default_timezone_set("Asia/Kolkata");
$ATTND_date=date("Y-m-d");
$ATTND_time=date("H:i:s");

$select_AttendanceCount="select * from ms_attendance where ms_id='$markeingSTFID' and date='$ATTND_date'";
$fetch_AttendanceCount=mysqli_query($db_conn,$select_AttendanceCount);
$result_AttendanceCount=mysqli_num_rows($fetch_AttendanceCount);
if($result_AttendanceCount==0)
	{
		$insertATTND="insert into ms_attendance (ms_id,date,time) values ('$markeingSTFID','$ATTND_date','$ATTND_time')";
		mysqli_query($db_conn,$insertATTND);

	}

// ========================================
// DISTRICT DASHBOARD DATA
// ========================================
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/MsDistrictScope.php';

$msId          = (int)$Login_user_IDvl;
$districts     = getMsAssignedDistricts($db_conn, $msId);
$districtNames = array_column($districts, 'name');
$districtLabel = !empty($districtNames) ? implode(', ', $districtNames) : 'No District Assigned';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = date('Y-m-d'); }

$districtStats  = getMsDistrictTargetAndFirkaStats($db_conn, $districtNames);
$vacantFirkas   = getMsVacantFirkas($db_conn, $districtNames);
$allTpIds       = getMsDistrictTpIds($db_conn, $districtNames, true);

$invoicedAmount = 0.0;
$invoiceRows    = [];
if (!empty($allTpIds)) {
    $idList = implode(',', $allTpIds);

    $stmtInv = $db_conn->prepare(
        "SELECT COALESCE(SUM(total),0) AS amt FROM user_invoice
         WHERE from_user_type='territory_partner' AND from_user_id IN ($idList)
           AND `date` BETWEEN ? AND ?"
    );
    $stmtInv->bind_param('ss', $from, $to);
    $stmtInv->execute();
    $invoicedAmount = (float)($stmtInv->get_result()->fetch_assoc()['amt'] ?? 0);
    $stmtInv->close();

    // Per-TP target = sum of target_amount of every Firka that TP is assigned to.
    $stmtRows = $db_conn->prepare(
        "SELECT ui.inv_id, ui.inv_number, ui.date, ui.total, ui.to_user_id,
                tp.id AS tp_pk, tp.name AS tp_name, tp.tp_id AS tp_code, tp.mobile AS tp_mobile,
                COALESCE((
                    SELECT SUM(pln.target_amount) FROM territory_partner_locations tpl
                    JOIN partner_location_nodes pln ON pln.id = tpl.location_id
                    WHERE tpl.territory_partner_id = tp.id
                ), 0) AS tp_target,
                s.name AS shop_name
         FROM user_invoice ui
         JOIN territory_partners tp ON tp.id = ui.from_user_id
         LEFT JOIN shop s ON s.temp_id = ui.to_user_id
         WHERE ui.from_user_type='territory_partner' AND ui.from_user_id IN ($idList)
           AND ui.`date` BETWEEN ? AND ?
         ORDER BY ui.date DESC, ui.inv_number DESC"
    );
    $stmtRows->bind_param('ss', $from, $to);
    $stmtRows->execute();
    $invoiceRows = $stmtRows->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtRows->close();
}

$balanceTarget = max($districtStats['district_total_target'] - $invoicedAmount, 0);

// TP roster for the tabs — All / Active / Inactive.
$tpRoster = [];
if (!empty($allTpIds)) {
    $idList = implode(',', $allTpIds);
    $resTp = $db_conn->query(
        "SELECT id, tp_id AS tp_code, name, mobile, is_active FROM territory_partners
         WHERE id IN ($idList) ORDER BY is_active DESC, name ASC"
    );
    $tpRoster = $resTp ? $resTp->fetch_all(MYSQLI_ASSOC) : [];
}
$activeTpRoster   = array_values(array_filter($tpRoster, fn($r) => $r['is_active']));
$inactiveTpRoster = array_values(array_filter($tpRoster, fn($r) => !$r['is_active']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Responsive Admin Dashboard Template">
    <meta name="keywords" content="admin,dashboard">
    <meta name="author" content="stacks">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    
    <!-- Title -->
    <title>Dashboard : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">

    
    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
        body { font-family: 'Poppins', sans-serif; }

        .stat-card {
            background: #fff; border-radius: 14px; padding: 22px 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06); border-left: 5px solid;
            margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between;
            transition: transform .15s, box-shadow .15s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.10); }
        .stat-card.purple { border-color: #667eea; }
        .stat-card.green  { border-color: #10b981; }
        .stat-card.blue   { border-color: #3b82f6; }
        .stat-card.orange { border-color: #f59e0b; }
        .stat-card.red    { border-color: #ef4444; }
        .stat-card.red h3 { color: #ef4444; }
        .stat-card h3 { font-size: 24px; font-weight: 700; margin: 0 0 3px 0; color: #1f2937; line-height: 1; }
        .stat-card p  { margin: 0; font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: .7px; }
        .stat-icon-wrap { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .stat-icon-wrap i { font-size: 26px; }
        .stat-card.purple .stat-icon-wrap { background: #ede9fe; color: #667eea; }
        .stat-card.green  .stat-icon-wrap { background: #d1fae5; color: #10b981; }
        .stat-card.blue   .stat-icon-wrap { background: #dbeafe; color: #3b82f6; }
        .stat-card.orange .stat-icon-wrap { background: #fef3c7; color: #f59e0b; }
        .stat-card.red    .stat-icon-wrap { background: #fee2e2; color: #ef4444; }

        .firka-tile { background: #fff; border-radius: 14px; padding: 16px 18px; box-shadow: 0 2px 12px rgba(0,0,0,.06); margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; }
        .firka-tile .ft-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .4px; }
        .firka-tile .ft-count { font-size: 22px; font-weight: 700; color: #1f2937; }
        .firka-tile .ft-value { font-size: 13px; font-weight: 600; color: #64748b; }
        .firka-tile-clickable { cursor: pointer; border: 1.5px solid transparent; transition: border-color .15s, background .15s; }
        .firka-tile-clickable:hover, .firka-tile-clickable:focus { border-color: #fecaca; background: #fef7f7; outline: none; }
        .ft-tap-hint { font-size: 10.5px; color: #ef4444; font-weight: 600; margin-top: 2px; }

        .main-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 16px rgba(0,0,0,.07); border: none; overflow: hidden; margin-bottom: 24px; }
        .main-card-header { padding: 20px 28px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; background: #fff; }
        .main-card-header-left { display: flex; align-items: center; gap: 12px; }
        .header-icon-box { width: 42px; height: 42px; border-radius: 11px; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; }
        .header-icon-box i { font-size: 20px; color: #fff; }
        .header-title { font-size: 15px; font-weight: 700; color: #1f2937; margin: 0; line-height: 1.2; }
        .header-sub   { font-size: 12px; color: #9ca3af; margin: 0; }
        .tp-count-pill { background: #ede9fe; color: #6d28d9; font-size: 12px; font-weight: 700; padding: 5px 14px; border-radius: 20px; }

        .tp-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .tp-table thead tr { background: #f8fafc; }
        .tp-table thead th { padding: 14px 20px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #94a3b8; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        .tp-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .12s; }
        .tp-table tbody tr:last-child { border-bottom: none; }
        .tp-table tbody tr:hover { background: #fafbff; }
        .tp-table tbody td { padding: 14px 20px; vertical-align: middle; }

        .tp-avatar { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; flex-shrink: 0; text-transform: uppercase; }
        .tp-info { display: flex; align-items: center; gap: 12px; }
        .tp-info-text { display: flex; flex-direction: column; gap: 3px; }
        .tp-name { font-size: 13.5px; font-weight: 600; color: #1e293b; line-height: 1.2; }
        .tp-code-pill { display: inline-block; font-size: 10.5px; font-weight: 700; color: #7c3aed; background: #ede9fe; padding: 2px 8px; border-radius: 5px; letter-spacing: .3px; }

        .mobile-cell { display: flex; align-items: center; gap: 7px; }
        .mobile-cell i { font-size: 15px; color: #cbd5e1; }
        .mobile-text { font-size: 13px; color: #475569; font-weight: 500; }

        .sales-amount { font-size: 13.5px; font-weight: 700; color: #1e293b; }
        .sales-currency { font-size: 11px; color: #94a3b8; font-weight: 600; margin-right: 1px; }

        .status-dot { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 20px; }
        .status-dot::before { content: ''; width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .status-active   { background: #ecfdf5; color: #059669; }
        .status-active::before   { background: #10b981; }
        .status-inactive { background: #fef2f2; color: #dc2626; }
        .status-inactive::before { background: #ef4444; }

        .serial-num { font-size: 12px; color: #cbd5e1; font-weight: 600; }

        .empty-state { text-align: center; padding: 56px 20px; color: #9ca3af; }
        .empty-icon-wrap { width: 64px; height: 64px; border-radius: 18px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; }
        .empty-icon-wrap i { font-size: 32px; color: #cbd5e1; }
        .empty-state h6 { font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 6px; }
        .empty-state p  { font-size: 13px; color: #9ca3af; margin: 0; }

        #tpSearch, .dash-search {
            border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 7px 14px 7px 36px; font-size: 13px; color: #374151;
            outline: none; width: 220px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%239ca3af' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.242 1.656a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E") no-repeat 12px center;
            transition: border-color .15s;
        }
        #tpSearch:focus, .dash-search:focus { border-color: #667eea; background-color: #fff; }

        .date-filter-form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .date-filter-form input[type=date] { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 6px 10px; font-size: 13px; }
        .nav-tabs .nav-link.active { font-weight: 700; color: #6d28d9; border-color: #e2e8f0 #e2e8f0 #fff; }
        .inv-number-link { color: #2563eb; font-weight: 700; text-decoration: none; }
        .inv-number-link:hover { text-decoration: underline; }

        .status-filter-tabs { display: flex; gap: 4px; padding: 0 20px; border-bottom: 1px solid #f1f5f9; }
        .status-filter-btn { border: none; background: none; padding: 14px 10px; font-size: 13.5px; font-weight: 600; color: #6b7280; cursor: pointer; border-bottom: 2px solid transparent; }
        .status-filter-btn.active { color: #6d28d9; border-bottom-color: #6d28d9; font-weight: 700; }

        .mis-pagination { display:flex; align-items:center; justify-content:flex-end; gap:6px; padding:14px 20px; flex-wrap:wrap; }
        .mis-pagination button { padding:4px 11px; border-radius:6px; border:1px solid #e5e7eb; background:#fff; color:#374151; font-size:12.5px; font-weight:600; cursor:pointer; }
        .mis-pagination button.active { background:#667eea; border-color:#667eea; color:#fff; }
        .mis-pagination button:disabled { opacity:.4; cursor:default; }
        .mis-pagination .mis-pg-info { font-size:12px; color:#6b7280; margin-right:6px; }

        /* ── Mobile responsiveness ───────────────────────── */
        @media (max-width: 767px) {
            .stat-card { padding: 16px 18px; margin-bottom: 14px; }
            .stat-card h3 { font-size: 19px; }
            .stat-icon-wrap { width: 42px; height: 42px; border-radius: 11px; }
            .stat-icon-wrap i { font-size: 20px; }

            .main-card-header { padding: 14px 16px; }
            .header-title { font-size: 14px; }
            .header-icon-box { width: 36px; height: 36px; }

            .date-filter-form { width: 100%; }
            .date-filter-form input[type=date] { flex: 1 1 auto; min-width: 0; }
            .date-filter-form .btn { flex: 0 0 auto; }

            .dash-search, #tpSearch { width: 100%; }

            .status-filter-tabs { padding: 0 10px; overflow-x: auto; white-space: nowrap; flex-wrap: nowrap; }
            .status-filter-btn { padding: 12px 8px; font-size: 12.5px; flex-shrink: 0; }

            .tp-table thead th, .tp-table tbody td { padding: 10px 12px; font-size: 12px; }
            .tp-name { font-size: 12.5px; }
            .tp-avatar { width: 32px; height: 32px; font-size: 11px; }
            .mobile-text, .sales-amount { font-size: 12px; }

            .firka-tile { padding: 13px 14px; }
            .firka-tile .ft-count { font-size: 18px; }

            .mis-pagination { justify-content: center; padding: 12px 10px; }
            .mis-pagination .mis-pg-info { width: 100%; text-align: center; margin: 0 0 6px; }

            .modal-dialog { margin: 10px; }
        }
    </style>
</head>
<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        <div class="app-container">
            
           <?php include("app-header.php");?>
			
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">

                        <div class="row mb-2">
                            <div class="col">
                                <h4 style="font-weight:700;color:#1f2937;margin-bottom:4px;">
                                    <i class="material-icons-two-tone" style="vertical-align:middle;font-size:22px;">dashboard</i>
                                    District Dashboard
                                </h4>
                                <p style="color:#6b7280;font-size:13px;margin:0;">
                                    <?php echo htmlspecialchars($districtLabel); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Top Stat Cards -->
                        <div class="row">
                            <div class="col-xl-4 col-md-6">
                                <div class="stat-card purple">
                                    <div>
                                        <h3>&#8377;<?php echo inr_format($districtStats['district_total_target'], 0); ?></h3>
                                        <p>District Total Target</p>
                                    </div>
                                    <div class="stat-icon-wrap"><i class="material-icons-outlined">flag</i></div>
                                </div>
                            </div>
                            <div class="col-xl-4 col-md-6">
                                <div class="stat-card green">
                                    <div>
                                        <h3>&#8377;<?php echo inr_format($invoicedAmount, 0); ?></h3>
                                        <p>Your TPs' Invoice Amount</p>
                                    </div>
                                    <div class="stat-icon-wrap"><i class="material-icons-outlined">receipt_long</i></div>
                                </div>
                            </div>
                            <div class="col-xl-4 col-md-6">
                                <div class="stat-card red">
                                    <div>
                                        <h3>&#8377;<?php echo inr_format($balanceTarget, 0); ?></h3>
                                        <p>Balance Target Amount</p>
                                    </div>
                                    <div class="stat-icon-wrap"><i class="material-icons-outlined">trending_up</i></div>
                                </div>
                            </div>
                        </div>

                        <!-- Shop Invoices (full width) -->
                        <div class="row">
                            <div class="col-12">
                                <div class="main-card">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">receipt</i></div>
                                            <div>
                                                <p class="header-title">Shop Invoices</p>
                                                <p class="header-sub">TP sales to shops in your district</p>
                                            </div>
                                        </div>
                                        <form class="date-filter-form" method="get">
                                            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
                                            <span style="color:#9ca3af;">to</span>
                                            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                                            <a class="btn btn-sm btn-success" href="export-shop-invoices-xlsx.php?from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>">
                                                <i class="material-icons-outlined" style="font-size:15px;vertical-align:text-bottom;">file_download</i> Export
                                            </a>
                                        </form>
                                    </div>

                                    <div style="padding:14px 20px 0;display:flex;justify-content:flex-end;">
                                        <input type="text" id="shopInvSearch" class="dash-search" placeholder="Search invoices…">
                                    </div>

                                    <div class="table-responsive" style="max-height:480px;overflow-y:auto;">
                                        <table class="tp-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:48px;text-align:center;">#</th>
                                                    <th>TP Name</th>
                                                    <th>TP ID</th>
                                                    <th>Phone</th>
                                                    <th style="text-align:right;">Target Amount</th>
                                                    <th>Invoice Number</th>
                                                    <th style="text-align:right;">Invoice Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody id="shopInvTbody">
                                                <tr><td colspan="7" class="text-muted" style="text-align:center;padding:30px;">Loading…</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div id="shopInvPagination" class="mis-pagination"></div>
                                </div>
                            </div>
                        </div>

                        <script>
                        (function () {
                            var allRows = <?php echo json_encode(array_map(function ($r) {
                                return [
                                    'tp_name'   => ucwords(strtolower($r['tp_name'])),
                                    'tp_code'   => $r['tp_code'],
                                    'tp_mobile' => $r['tp_mobile'],
                                    'tp_target' => (float)$r['tp_target'],
                                    'inv_number'=> $r['inv_number'],
                                    'inv_id'    => base64_encode($r['inv_id']),
                                    'total'     => (float)$r['total'],
                                ];
                            }, $invoiceRows)); ?>;
                            var pageSize = 10;
                            var currentPage = 1;
                            var searchTerm = '';

                            var escDiv = document.createElement('div');
                            function esc(s) { escDiv.textContent = (s == null ? '' : s); return escDiv.innerHTML; }
                            function money(n) { return '&#8377;' + Number(n || 0).toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 0}); }

                            function filteredRows() {
                                if (!searchTerm) return allRows;
                                return allRows.filter(function (r) {
                                    return (r.tp_name + ' ' + r.tp_code + ' ' + r.tp_mobile + ' ' + r.inv_number).toLowerCase().indexOf(searchTerm) > -1;
                                });
                            }

                            function render() {
                                var rows = filteredRows();
                                var tbody = document.getElementById('shopInvTbody');
                                var pager = document.getElementById('shopInvPagination');
                                if (!rows.length) {
                                    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><div class="empty-icon-wrap"><i class="material-icons-outlined">inbox</i></div><h6>No Invoices Found</h6><p>No shop invoices match this filter.</p></div></td></tr>';
                                    pager.innerHTML = '';
                                    return;
                                }
                                var totalPages = Math.ceil(rows.length / pageSize);
                                if (currentPage > totalPages) currentPage = totalPages;
                                var start = (currentPage - 1) * pageSize;
                                var pageRows = rows.slice(start, start + pageSize);
                                var html = '';
                                pageRows.forEach(function (r, i) {
                                    html += '<tr>' +
                                        '<td style="text-align:center;"><span class="serial-num">' + (start + i + 1) + '</span></td>' +
                                        '<td><span class="tp-name">' + esc(r.tp_name) + '</span></td>' +
                                        '<td><span class="tp-code-pill">' + esc(r.tp_code) + '</span></td>' +
                                        '<td><div class="mobile-cell"><i class="material-icons-outlined">phone_iphone</i><span class="mobile-text">' + esc(r.tp_mobile) + '</span></div></td>' +
                                        '<td style="text-align:right;"><span class="sales-amount">' + money(r.tp_target) + '</span></td>' +
                                        '<td><a class="inv-number-link" target="_blank" href="view-shop-invoice.php?invoiceid=' + encodeURIComponent(r.inv_id) + '">' + esc(r.inv_number) + '</a></td>' +
                                        '<td style="text-align:right;"><span class="sales-amount">' + money(r.total) + '</span></td>' +
                                        '</tr>';
                                });
                                tbody.innerHTML = html;

                                var pHtml = '<span class="mis-pg-info">' + (start + 1) + '–' + Math.min(start + pageSize, rows.length) + ' of ' + rows.length + '</span>';
                                pHtml += '<button type="button" data-pg="prev"' + (currentPage === 1 ? ' disabled' : '') + '>Prev</button>';
                                for (var p = 1; p <= totalPages; p++) {
                                    pHtml += '<button type="button" data-pg="' + p + '" class="' + (p === currentPage ? 'active' : '') + '">' + p + '</button>';
                                }
                                pHtml += '<button type="button" data-pg="next"' + (currentPage === totalPages ? ' disabled' : '') + '>Next</button>';
                                pager.innerHTML = pHtml;
                            }

                            document.getElementById('shopInvPagination').addEventListener('click', function (e) {
                                var btn = e.target.closest('button[data-pg]');
                                if (!btn) return;
                                var pg = btn.getAttribute('data-pg');
                                var totalPages = Math.ceil(filteredRows().length / pageSize);
                                if (pg === 'prev') currentPage = Math.max(1, currentPage - 1);
                                else if (pg === 'next') currentPage = Math.min(totalPages, currentPage + 1);
                                else currentPage = parseInt(pg, 10);
                                render();
                            });

                            document.getElementById('shopInvSearch').addEventListener('input', function () {
                                searchTerm = this.value.toLowerCase().trim();
                                currentPage = 1;
                                render();
                            });

                            render();
                        })();
                        </script>

                        <!-- Territory Partners (left) + Firka Coverage (right) -->
                        <div class="row">
                            <div class="col-xl-8">
                                <div class="main-card">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">people_alt</i></div>
                                            <div>
                                                <p class="header-title">Territory Partners</p>
                                                <p class="header-sub">In your assigned district(s)</p>
                                            </div>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:12px;">
                                            <input type="text" id="tpSearch" class="dash-search" placeholder="Search TPs…">
                                        </div>
                                    </div>

                                    <div class="status-filter-tabs" id="tpRosterTabs">
                                        <button type="button" class="status-filter-btn active" data-status="all">Total TPs <span class="tp-count-pill"><?php echo count($tpRoster); ?></span></button>
                                        <button type="button" class="status-filter-btn" data-status="active">Active TPs <span class="tp-count-pill"><?php echo count($activeTpRoster); ?></span></button>
                                        <button type="button" class="status-filter-btn" data-status="inactive">Inactive TPs <span class="tp-count-pill"><?php echo count($inactiveTpRoster); ?></span></button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="tp-table tp-roster-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:48px;text-align:center;">#</th>
                                                    <th>Territory Partner</th>
                                                    <th>Mobile</th>
                                                    <th style="text-align:center;">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tpRosterTbody">
                                                <tr><td colspan="4" class="text-muted" style="text-align:center;padding:30px;">Loading…</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div id="tpRosterPagination" class="mis-pagination"></div>
                                </div>
                            </div>

                            <!-- Right: Firka coverage -->
                            <div class="col-xl-4">
                                <div class="main-card" style="padding:4px 0;">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">map</i></div>
                                            <div>
                                                <p class="header-title">Firka Coverage</p>
                                                <p class="header-sub">Your assigned district(s)</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding:18px 20px 4px;">
                                        <div class="firka-tile">
                                            <div>
                                                <div class="ft-label">Total Firkas</div>
                                                <div class="ft-count"><?php echo $districtStats['firka_total_count']; ?></div>
                                            </div>
                                            <div class="ft-value">&#8377;<?php echo inr_format($districtStats['district_total_target'], 0); ?></div>
                                        </div>
                                        <div class="firka-tile">
                                            <div>
                                                <div class="ft-label">Filled Firkas</div>
                                                <div class="ft-count" style="color:#10b981;"><?php echo $districtStats['firka_filled_count']; ?></div>
                                            </div>
                                            <div class="ft-value">&#8377;<?php echo inr_format($districtStats['target_active_amount'] + $districtStats['target_inactive_amount'], 0); ?></div>
                                        </div>
                                        <div class="firka-tile firka-tile-clickable" id="vacantFirkaTile" data-bs-toggle="modal" data-bs-target="#vacantFirkaModal" role="button" tabindex="0">
                                            <div>
                                                <div class="ft-label">Vacant Firkas</div>
                                                <div class="ft-count" style="color:#ef4444;"><?php echo $districtStats['firka_vacant_count']; ?></div>
                                            </div>
                                            <div style="text-align:right;">
                                                <div class="ft-value">&#8377;<?php echo inr_format($districtStats['target_unassigned_amount'], 0); ?></div>
                                                <?php if (!empty($vacantFirkas)): ?>
                                                <div class="ft-tap-hint"><i class="material-icons-outlined" style="font-size:13px;vertical-align:text-bottom;">touch_app</i> Tap for list</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vacant Firkas drill-down modal -->
                        <div class="modal fade" id="vacantFirkaModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title" style="font-weight:700;">Vacant Firkas — <?php echo htmlspecialchars($districtLabel); ?></h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding:0;">
                                        <?php if (empty($vacantFirkas)): ?>
                                        <div class="empty-state">
                                            <div class="empty-icon-wrap"><i class="material-icons-outlined">check_circle</i></div>
                                            <h6>No Vacant Firkas</h6>
                                            <p>Every Firka in your district already has a TP.</p>
                                        </div>
                                        <?php else: ?>
                                        <table class="tp-table">
                                            <thead>
                                                <tr>
                                                    <th>District</th>
                                                    <th>Firka</th>
                                                    <th style="text-align:right;">Target Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($vacantFirkas as $vf): ?>
                                                <tr>
                                                    <td><span class="mobile-text"><?php echo htmlspecialchars($vf['district_name']); ?></span></td>
                                                    <td><span class="tp-name"><?php echo htmlspecialchars($vf['firka_name']); ?></span></td>
                                                    <td style="text-align:right;">
                                                        <span class="sales-amount"><span class="sales-currency">&#8377;</span><?php echo inr_format($vf['target_amount'], 0); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($vacantFirkas)): ?>
                                    <div class="modal-footer" style="justify-content:space-between;">
                                        <span style="font-size:12.5px;color:#6b7280;"><?php echo count($vacantFirkas); ?> vacant Firka(s)</span>
                                        <span style="font-weight:700;color:#ef4444;">&#8377;<?php echo inr_format($districtStats['target_unassigned_amount'], 0); ?> total</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <script>
                        (function () {
                            var allRows = <?php echo json_encode(array_map(function ($tp) {
                                return [
                                    'tp_code'   => $tp['tp_code'],
                                    'name'      => ucwords(strtolower($tp['name'])),
                                    'mobile'    => $tp['mobile'],
                                    'is_active' => (int)$tp['is_active'],
                                ];
                            }, $tpRoster)); ?>;
                            var pageSize = 10;
                            var currentPage = 1;
                            var currentStatus = 'all';
                            var searchTerm = '';

                            var escDiv = document.createElement('div');
                            function esc(s) { escDiv.textContent = (s == null ? '' : s); return escDiv.innerHTML; }
                            function initialsOf(name) {
                                var words = name.trim().split(/\s+/);
                                if (words.length > 1) return (words[0][0] + words[words.length - 1][0]).toUpperCase();
                                return name.trim().substring(0, 1).toUpperCase();
                            }

                            function filteredRows() {
                                return allRows.filter(function (r) {
                                    if (currentStatus === 'active' && !r.is_active) return false;
                                    if (currentStatus === 'inactive' && r.is_active) return false;
                                    if (searchTerm && (r.name + ' ' + r.tp_code + ' ' + r.mobile).toLowerCase().indexOf(searchTerm) === -1) return false;
                                    return true;
                                });
                            }

                            function render() {
                                var rows = filteredRows();
                                var tbody = document.getElementById('tpRosterTbody');
                                var pager = document.getElementById('tpRosterPagination');
                                if (!rows.length) {
                                    tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state"><div class="empty-icon-wrap"><i class="material-icons-outlined">person_search</i></div><h6>No Territory Partners</h6><p>None found for this filter.</p></div></td></tr>';
                                    pager.innerHTML = '';
                                    return;
                                }
                                var totalPages = Math.ceil(rows.length / pageSize);
                                if (currentPage > totalPages) currentPage = totalPages;
                                var start = (currentPage - 1) * pageSize;
                                var pageRows = rows.slice(start, start + pageSize);
                                var html = '';
                                pageRows.forEach(function (r, i) {
                                    var statusHtml = r.is_active
                                        ? '<span class="status-dot status-active">Active</span>'
                                        : '<span class="status-dot status-inactive">Inactive</span>';
                                    html += '<tr>' +
                                        '<td style="text-align:center;"><span class="serial-num">' + (start + i + 1) + '</span></td>' +
                                        '<td><div class="tp-info"><div class="tp-avatar">' + esc(initialsOf(r.name)) + '</div><div class="tp-info-text"><span class="tp-name">' + esc(r.name) + '</span><span class="tp-code-pill">' + esc(r.tp_code) + '</span></div></div></td>' +
                                        '<td><div class="mobile-cell"><i class="material-icons-outlined">phone_iphone</i><span class="mobile-text">' + esc(r.mobile) + '</span></div></td>' +
                                        '<td style="text-align:center;">' + statusHtml + '</td>' +
                                        '</tr>';
                                });
                                tbody.innerHTML = html;

                                var pHtml = '<span class="mis-pg-info">' + (start + 1) + '–' + Math.min(start + pageSize, rows.length) + ' of ' + rows.length + '</span>';
                                pHtml += '<button type="button" data-pg="prev"' + (currentPage === 1 ? ' disabled' : '') + '>Prev</button>';
                                for (var p = 1; p <= totalPages; p++) {
                                    pHtml += '<button type="button" data-pg="' + p + '" class="' + (p === currentPage ? 'active' : '') + '">' + p + '</button>';
                                }
                                pHtml += '<button type="button" data-pg="next"' + (currentPage === totalPages ? ' disabled' : '') + '>Next</button>';
                                pager.innerHTML = pHtml;
                            }

                            document.getElementById('tpRosterPagination').addEventListener('click', function (e) {
                                var btn = e.target.closest('button[data-pg]');
                                if (!btn) return;
                                var pg = btn.getAttribute('data-pg');
                                var totalPages = Math.ceil(filteredRows().length / pageSize);
                                if (pg === 'prev') currentPage = Math.max(1, currentPage - 1);
                                else if (pg === 'next') currentPage = Math.min(totalPages, currentPage + 1);
                                else currentPage = parseInt(pg, 10);
                                render();
                            });

                            document.getElementById('tpRosterTabs').addEventListener('click', function (e) {
                                var btn = e.target.closest('.status-filter-btn');
                                if (!btn) return;
                                document.querySelectorAll('#tpRosterTabs .status-filter-btn').forEach(function (b) { b.classList.remove('active'); });
                                btn.classList.add('active');
                                currentStatus = btn.getAttribute('data-status');
                                currentPage = 1;
                                render();
                            });

                            document.getElementById('tpSearch').addEventListener('input', function () {
                                searchTerm = this.value.toLowerCase().trim();
                                currentPage = 1;
                                render();
                            });

                            render();
                        })();
                        </script>

                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>
</body>
</html>