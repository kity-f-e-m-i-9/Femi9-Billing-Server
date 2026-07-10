<?php 
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
include("config.php"); 
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");

// Calculate dates efficiently
$today_date = date("Y-m-d");
$today_number = date("d");
$Yesterday_date = date("Y-m-d", strtotime("-1 day", strtotime($today_date)));
$start_date = date("Y-m-01");
$endDate = date('Y-m-t');
$lastmonth_only = date("m", strtotime("-1 month", strtotime($start_date)));
$lastmonth_date_start = date("Y-".$lastmonth_only."-01");
$nof_of_days_month = date("t", strtotime($lastmonth_date_start));
$lastmonth_date_end = date("Y-".$lastmonth_only."-".$nof_of_days_month."");

// ===== CRON INTEGRATION: NO HEAVY OPERATIONS ON PAGE LOAD =====
// All database updates now handled by cron job automatically
// This ensures fast page loading while maintaining data integrity

// OPTIMIZED: Load report data files with error handling
$report_data_loaded = false;

// Load company sales data
if (file_exists("report_company_sales.php")) {
    ob_start();
    try {
        include("report_company_sales.php");
        $company_sales_output = ob_get_clean();
        $report_data_loaded = true;
    } catch (Exception $e) {
        ob_end_clean();
        $company_sales_output = "";
        error_log("Error loading company sales: " . $e->getMessage());
    }
} else {
    $company_sales_output = "";
    error_log("report_company_sales.php file not found");
}

// Load channel sales data
if (file_exists("report_channel_sales.php")) {
    ob_start();
    try {
        include("report_channel_sales.php");
        $channel_sales_output = ob_get_clean();
    } catch (Exception $e) {
        ob_end_clean();
        $channel_sales_output = "";
        error_log("Error loading channel sales: " . $e->getMessage());
    }
} else {
    $channel_sales_output = "";
    error_log("report_channel_sales.php file not found");
}

// Set default values for ALL time periods to prevent undefined variable errors
// TODAY - Company Sales
if (!isset($today_invoice_count)) $today_invoice_count = 0;
if (!isset($today_total_qty)) $today_total_qty = 0;
if (!isset($today_total_amount)) $today_total_amount = 0;

// YESTERDAY - Company Sales
if (!isset($yesterday_invoice_count)) $yesterday_invoice_count = 0;
if (!isset($yesterday_total_qty)) $yesterday_total_qty = 0;
if (!isset($yesterday_total_amount)) $yesterday_total_amount = 0;

// THIS MONTH - Company Sales
if (!isset($thismonth_invoice_count)) $thismonth_invoice_count = 0;
if (!isset($thismonth_total_qty)) $thismonth_total_qty = 0;
if (!isset($thismonth_total_amount)) $thismonth_total_amount = 0;

// LAST MONTH - Company Sales
if (!isset($lastmonth_invoice_count)) $lastmonth_invoice_count = 0;
if (!isset($lastmonth_total_qty)) $lastmonth_total_qty = 0;
if (!isset($lastmonth_total_amount)) $lastmonth_total_amount = 0;

// TODAY - Channel Sales
if (!isset($today_invoice_count_channel)) $today_invoice_count_channel = 0;
if (!isset($today_total_qty_channel)) $today_total_qty_channel = 0;
if (!isset($today_total_amount_channel)) $today_total_amount_channel = 0;

// YESTERDAY - Channel Sales  
if (!isset($yesterday_invoice_count_channel)) $yesterday_invoice_count_channel = 0;
if (!isset($yesterday_total_qty_channel)) $yesterday_total_qty_channel = 0;
if (!isset($yesterday_total_amount_channel)) $yesterday_total_amount_channel = 0;

// THIS MONTH - Channel Sales
if (!isset($thismonth_invoice_count_channel)) $thismonth_invoice_count_channel = 0;
if (!isset($thismonth_total_qty_channel)) $thismonth_total_qty_channel = 0;
if (!isset($thismonth_total_amount_channel)) $thismonth_total_amount_channel = 0;

// LAST MONTH - Channel Sales
if (!isset($lastmonth_invoice_count_channel)) $lastmonth_invoice_count_channel = 0;
if (!isset($lastmonth_total_qty_channel)) $lastmonth_total_qty_channel = 0;
if (!isset($lastmonth_total_amount_channel)) $lastmonth_total_amount_channel = 0;

// ===== CRON STATUS CHECK (OPTIONAL - LIGHT OPERATION) =====
// Quick check for cron job status - this is very fast
$cron_status = ['status' => 'unknown', 'last_run' => 'Never', 'next_run' => 'Scheduled'];

// Check if cron status file exists
$status_file = __DIR__ . '/status/cron-update-status.json';
if (file_exists($status_file)) {
    $status_data = json_decode(file_get_contents($status_file), true);
    if ($status_data) {
        $cron_status = $status_data;
    }
}

// Optional: Quick pending count check (with limit for performance)
$pending_count = 0;
$pending_result = @mysqli_query($db_conn, "SELECT COUNT(*) as count FROM receipt WHERE from_user_type = '' AND from_user_id = '' LIMIT 1");
if ($pending_result) {
    $pending_data = mysqli_fetch_array($pending_result);
    $pending_count = $pending_data['count'] ?? 0;
}

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
    
    <!-- Title -->
    <title>Report : <?php echo $business_name;?></title>

    <!-- Optimized Styles Loading -->
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

    <style type="text/css">
    #dashanch{color:#000 !important;}
    #dashanch:hover{color:#1a06a6 !important;}
    #reportdash th{font-size:13px;font-weight:600;}
    #reportdash td{font-weight:700;font-size:14px;}
    
    /* Performance optimizations */
    .widget-stats {
        transition: transform 0.2s ease-in-out;
        margin-bottom: 20px;
    }
    .widget-stats:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .section-title {
        margin: 30px 0 20px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }
    
    /* Loading states */
    .loading-shimmer {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
    }
    
    @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }
    
    /* Responsive improvements */
    @media (max-width: 768px) {
        .col-xl-3 {
            margin-bottom: 15px;
        }
    }
    
    /* ===== CRON STATUS INDICATOR (OPTIONAL VISUAL ENHANCEMENT) ===== */
    .cron-indicator {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(255, 255, 255, 0.95);
        padding: 10px 15px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-size: 12px;
        color: #666;
        z-index: 1000;
        border-left: 4px solid #28a745;
        max-width: 300px;
    }
    .cron-indicator.warning { border-left-color: #ffc107; }
    .cron-indicator.error { border-left-color: #dc3545; }
    .cron-indicator .status-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #28a745;
        margin-right: 8px;
        animation: pulse 2s infinite;
    }
    .cron-indicator.warning .status-dot { background: #ffc107; }
    .cron-indicator.error .status-dot { background: #dc3545; }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    
    /* Hide cron indicator on mobile */
    @media (max-width: 768px) {
        .cron-indicator { display: none; }
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
                    <div class="container">
                        <div class="row">
                            <div class="col">
                                <div class="page-description" style="margin-left:-25px;">
                                    <h1>Report</h1>
                                    
                                    <?php if(!$report_data_loaded) { ?>
                                        <div class="alert alert-warning">
                                            <strong>Notice:</strong> Some report data could not be loaded. Please check if report files exist.
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
						
						<!--------------------------------------------------------------------->
						<!------- SALES FROM COMPANY - ALL TIME PERIODS --------------------->
						<!--------------------------------------------------------------------->
						<?php 
                        // Output the company sales data (this loads the variables)
                        echo $company_sales_output;
                        ?>
						<h3 class="section-title"><b>Sales From Company</b></h3>
						<div class="row">
                            <!-- TODAY -->
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
						<a href="overview-report1?frdate=<?=$today_date;?>&&todate=<?=$today_date;?>&&lable=1&&rptlable=1&&out1=<?=$today_invoice_count;?>&&out2=<?=$today_total_qty;?>&&out3=<?=$today_total_amount;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Today</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$today_invoice_count;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$today_total_qty;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$today_total_amount;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							<!-- YESTERDAY -->
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report1?frdate=<?=$Yesterday_date;?>&&todate=<?=$Yesterday_date;?>&&lable=2&&rptlable=1&&out1=<?=$yesterday_invoice_count;?>&&out2=<?=$yesterday_total_qty;?>&&out3=<?=$yesterday_total_amount;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Yesterday</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$yesterday_invoice_count;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$yesterday_total_qty;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$yesterday_total_amount;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							<!-- THIS MONTH -->
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report1?frdate=<?=$start_date;?>&&todate=<?=$endDate;?>&&lable=3&&rptlable=1&&out1=<?=$thismonth_invoice_count;?>&&out2=<?=$thismonth_total_qty;?>&&out3=<?=$thismonth_total_amount;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">This Month</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$thismonth_invoice_count;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$thismonth_total_qty;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$thismonth_total_amount;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							<!-- LAST MONTH -->
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report1?frdate=<?=$lastmonth_date_start;?>&&todate=<?=$lastmonth_date_end;?>&&lable=4&&rptlable=1&&out1=<?=$lastmonth_invoice_count;?>&&out2=<?=$lastmonth_total_qty;?>&&out3=<?=$lastmonth_total_amount;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Last Month</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$lastmonth_invoice_count;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$lastmonth_total_qty;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$lastmonth_total_amount;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
						
				<!--------------------------------------------------------------------->
				<!------- CHANNELWISE SALES - ALL TIME PERIODS ----------------------->
				<!--------------------------------------------------------------------->
				<?php 
                // Output the channel sales data (this loads the variables)
                echo $channel_sales_output;
                ?>
						<h3 class="section-title"><b>Channelwise Sales</b></h3>
						<div class="row">
                            <!-- TODAY -->
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report3?frdate=<?=$today_date;?>&&todate=<?=$today_date;?>&&lable=1&&rptlable=1&&out1=<?=$today_invoice_count_channel;?>&&out2=<?=$today_total_qty_channel;?>&&out3=<?=$today_total_amount_channel;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Today</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$today_invoice_count_channel;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$today_total_qty_channel;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$today_total_amount_channel;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- YESTERDAY -->
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report3?frdate=<?=$Yesterday_date;?>&&todate=<?=$Yesterday_date;?>&&lable=2&&rptlable=1&&out1=<?=$yesterday_invoice_count_channel;?>&&out2=<?=$yesterday_total_qty_channel;?>&&out3=<?=$yesterday_total_amount_channel;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Yesterday</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$yesterday_invoice_count_channel;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$yesterday_total_qty_channel;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$yesterday_total_amount_channel;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- THIS MONTH -->
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report3?frdate=<?=$start_date;?>&&todate=<?=$endDate;?>&&lable=3&&rptlable=1&&out1=<?=$thismonth_invoice_count_channel;?>&&out2=<?=$thismonth_total_qty_channel;?>&&out3=<?=$thismonth_total_amount_channel;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">This Month</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$thismonth_invoice_count_channel;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$thismonth_total_qty_channel;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$thismonth_total_amount_channel;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- LAST MONTH -->
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report3?frdate=<?=$lastmonth_date_start;?>&&todate=<?=$lastmonth_date_end;?>&&lable=4&&rptlable=1&&out1=<?=$lastmonth_invoice_count_channel;?>&&out2=<?=$lastmonth_total_qty_channel;?>&&out3=<?=$lastmonth_total_amount_channel;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Last Month</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$lastmonth_invoice_count_channel;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$lastmonth_total_qty_channel;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$lastmonth_total_amount_channel;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
				
					<!--------------------end***--------------------------------------->
						
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ===== OPTIONAL: CRON STATUS INDICATOR ===== -->
    <?php if ($cron_status['status'] !== 'unknown' || $pending_count > 0): ?>
    <div class="cron-indicator <?php echo $pending_count > 0 ? 'warning' : ''; ?>" id="cronIndicator">
        <span class="status-dot"></span>
        <strong>Auto-Sync:</strong> 
        <?php 
        if ($pending_count > 0) {
            echo "Processing $pending_count records";
        } else {
            echo "All data synchronized";
        }
        ?>
        <br>
        <small>Last: <?php echo date('H:i', strtotime($cron_status['last_run'])); ?></small>
    </div>
    <?php endif; ?>
    
    <!-- Optimized Javascripts Loading -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>

    <script>
    // Performance monitoring and smooth animations (UNCHANGED)
    document.addEventListener('DOMContentLoaded', function() {
        // Add staggered fade-in animations for all widgets
        const widgets = document.querySelectorAll('.widget-stats');
        widgets.forEach(function(widget, index) {
            widget.style.opacity = '0';
            widget.style.transform = 'translateY(20px)';
            
            setTimeout(function() {
                widget.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                widget.style.opacity = '1';
                widget.style.transform = 'translateY(0)';
            }, index * 100); // Stagger animations by 100ms
        });
        
        // Add loading complete indicator
        setTimeout(function() {
            console.log('All report widgets loaded successfully');
        }, widgets.length * 100 + 600);
        
        // ===== CRON STATUS INDICATOR FUNCTIONALITY =====
        const cronIndicator = document.getElementById('cronIndicator');
        if (cronIndicator) {
            // Auto-hide after 10 seconds
            setTimeout(function() {
                cronIndicator.style.opacity = '0.7';
            }, 10000);
            
            // Click to hide
            cronIndicator.addEventListener('click', function() {
                this.style.display = 'none';
            });
            
            // Show tooltip
            cronIndicator.title = 'Click to hide | Updates run automatically every 15 minutes';
        }
    });
    
    // Performance tracking (UNCHANGED)
    window.addEventListener('load', function() {
        if(performance && performance.timing) {
            const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
            console.log('Page load time: ' + (loadTime/1000).toFixed(2) + ' seconds');
            
            // Log performance status
            if(loadTime < 3000) {
                console.log('✅ Performance: Excellent (Cron-optimized)');
            } else {
                console.log('⚠️ Performance: Consider further optimization');
            }
        }
    });
    
    // ===== OPTIONAL: AUTO-REFRESH PAGE EVERY 5 MINUTES =====
    // Uncomment to enable automatic page refresh to show updated data
    /*
    setTimeout(function() {
        console.log('Auto-refreshing to show latest data...');
        window.location.reload();
    }, 300000); // 5 minutes
    */
    </script>
</body>
</html>