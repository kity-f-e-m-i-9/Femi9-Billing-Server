#!/usr/bin/env php
<?php
/**
 * CP Monthly Commission Cron Job
 *
 * Run once a month (e.g. on the 1st) to credit every Channel Partner's
 * wallet for the previous calendar month:
 *
 *   commission = MAX( 6% of last month's sales sourced from the CP's stock,
 *                      2% of the CP's total security deposit across all locations )
 *
 * One record per CP per month (dedup on commission_type + user_id + from_date/to_date).
 *
 * Crontab example (run at 02:00 on the 1st of every month):
 *   0 2 1 * * /usr/bin/php /path/to/channel-partner/cron-cp-commission.php >> /path/to/channel-partner/status/cron-cp-commission.log 2>&1
 */

// Prevent web access - only allow command line execution
if (isset($_SERVER['HTTP_HOST'])) {
    http_response_code(403);
    die("Access denied. This script runs automatically via cron job.\n");
}

chdir(dirname(__FILE__));
require_once __DIR__ . '/include/db-connect.php';

date_default_timezone_set("Asia/Kolkata");

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    flush();
}

logMessage("=== CP MONTHLY COMMISSION CRON STARTED ===");

// Fix collation mismatch between connection default and DB table columns
mysqli_query($db_conn, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

$lastMonthFirst = date("Y-m-01", strtotime("-1 month"));
$lastMonthDays  = (int)date("t", strtotime($lastMonthFirst));
$lastMonthLast  = date("Y-m-{$lastMonthDays}", strtotime($lastMonthFirst));
$monthName      = date("M", strtotime($lastMonthFirst));
$year           = date("Y", strtotime($lastMonthFirst));

logMessage("Processing commission for period: $lastMonthFirst to $lastMonthLast");

$fromEsc = mysqli_real_escape_string($db_conn, $lastMonthFirst);
$toEsc   = mysqli_real_escape_string($db_conn, $lastMonthLast);
$utype   = 'channel_partner';

$cpResult = mysqli_query($db_conn, "SELECT id FROM channel_partners");
if (!$cpResult) {
    logMessage("ERROR: Could not fetch channel partners: " . mysqli_error($db_conn));
    exit(1);
}

$processed = 0;
$credited  = 0;
$skipped   = 0;

while ($cp = mysqli_fetch_assoc($cpResult)) {
    $processed++;
    $cpId  = (int)$cp['id'];
    $cpEsc = mysqli_real_escape_string($db_conn, (string)$cpId);

    // Dedup — already credited this CP for this month?
    $dup = (int)(mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT COUNT(*) FROM wallet_monthly_sls_report
         WHERE user_type='$utype' AND user_id='$cpEsc'
           AND from_date='$fromEsc' AND to_date='$toEsc'
           AND commission_type='CP Commission'"))[0] ?? 0);
    if ($dup > 0) {
        $skipped++;
        continue;
    }

    // 1. Last month's invoiced sales sourced from this CP's stock (minus accepted returns)
    $gross = (float)(mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT COALESCE(SUM(total_amount),0) FROM tp_invoices
         WHERE source_cp_id='$cpEsc'
           AND invoice_date BETWEEN '$fromEsc' AND '$toEsc'"))[0] ?? 0);

    $returns = (float)(mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT COALESCE(SUM(urs.total),0)
         FROM user_return_stock urs
         JOIN tp_invoices tpi ON tpi.invoice_number = urs.invnumber COLLATE utf8mb4_unicode_ci
         WHERE tpi.source_cp_id='$cpEsc'
           AND urs.from_usertype='territory_partner'
           AND urs.date BETWEEN '$fromEsc' AND '$toEsc'
           AND urs.status='accept'"))[0] ?? 0);

    $sales = max(0, $gross - $returns);

    // 2. Total security deposit across this CP's assigned locations
    $deposit = (float)(mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT COALESCE(SUM(n.deposit_amount),0)
         FROM channel_partner_locations cpl
         JOIN partner_location_nodes n ON n.id = cpl.location_id
         WHERE cpl.channel_partner_id='$cpEsc'"))[0] ?? 0);

    if ($sales <= 0 && $deposit <= 0) {
        $skipped++;
        continue;
    }

    // 3. Commission = higher of 6% of sales or 2% of total deposit
    $fromSales   = round($sales * 0.06, 2);
    $fromDeposit = round($deposit * 0.02, 2);

    if ($fromSales >= $fromDeposit) {
        $commission = $fromSales;
        $pct        = 6;
        $basis      = 'Sales-based (6%)';
    } else {
        $commission = $fromDeposit;
        $pct        = 2;
        $basis      = 'Deposit-floor (2%)';
    }

    if ($commission <= 0) {
        $skipped++;
        continue;
    }

    $remarks = mysqli_real_escape_string($db_conn, $basis);

    $ok = mysqli_query($db_conn,
        "INSERT INTO wallet_monthly_sls_report
         (user_type, user_id, from_date, to_date, month, year,
          total_sls_amount, target_sls_amount, target_reached,
          refer_by_usertype, refer_by_userid, commission_percentage,
          commission_amount, commission_type, remarks)
         VALUES
         ('$utype','$cpEsc',
          '$fromEsc','$toEsc','$monthName','$year',
          '$sales','$deposit','yes',
          '$utype','$cpEsc','$pct',
          '$commission','CP Commission','$remarks')"
    );

    if ($ok) {
        $credited++;
        logMessage("CP #$cpId credited ₹$commission ($basis) — sales=₹$sales deposit=₹$deposit");
    } else {
        logMessage("CP #$cpId FAILED to insert: " . mysqli_error($db_conn));
    }
}

logMessage("Processed: $processed | Credited: $credited | Skipped: $skipped");
logMessage("=== CP MONTHLY COMMISSION CRON COMPLETED ===");

mysqli_close($db_conn);