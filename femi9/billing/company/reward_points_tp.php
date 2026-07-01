<?php
/**
 * Reward Points - Territory Partners (Company View)
 */

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

require_once("checksession.php");
require_once("config.php");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set("Asia/Kolkata");

function validateDate_rtp(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}

$daysInMonth     = (int) date('t');
$currentMonth    = date('m');
$defaultFromDate = date("Y-{$currentMonth}-01");
$defaultToDate   = date("Y-{$currentMonth}-{$daysInMonth}");

$current_from_date = validateDate_rtp($_POST['frdate'] ?? $_GET['frdate'] ?? null, $defaultFromDate);
$current_to_date   = validateDate_rtp($_POST['todate'] ?? $_GET['todate'] ?? null, $defaultToDate);

if (strtotime($current_from_date) > strtotime($current_to_date)) {
    [$current_from_date, $current_to_date] = [$current_to_date, $current_from_date];
}

$safe_business_name = htmlspecialchars($business_name ?? 'Femi9', ENT_QUOTES, 'UTF-8');

$combined_users = [];

try {
    // PDO connection (uses $servername/$username/$password/$dbname from config)
    $pdo = new PDO(
        "mysql:host={$servername};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // ── Purchase Points ────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT territory_partner_id AS user_id,
               COALESCE(SUM(total_amount) / 100, 0) AS purchase_points
        FROM tp_invoices
        WHERE invoice_date BETWEEN :from_date AND :to_date
        GROUP BY territory_partner_id
    ");
    $stmt->execute([':from_date' => $current_from_date, ':to_date' => $current_to_date]);
    $purchase_data = [];
    foreach ($stmt->fetchAll() as $row) {
        $purchase_data[$row['user_id']] = (float)$row['purchase_points'];
    }

    // ── Daily Login Points ─────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT user_id, COALESCE(SUM(points_awarded), 0) AS daily_points
        FROM daily_login_rewards
        WHERE user_type = 'territory_partner'
          AND reward_date BETWEEN :from_date AND :to_date
        GROUP BY user_id
        HAVING daily_points > 0
    ");
    $stmt->execute([':from_date' => $current_from_date, ':to_date' => $current_to_date]);
    $daily_data = [];
    foreach ($stmt->fetchAll() as $row) {
        $daily_data[$row['user_id']] = (float)$row['daily_points'];
    }

    // ── Return Deductions ─────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT r.from_userid AS user_id,
               COALESCE(SUM(r.subtotal) / 100, 0) AS return_points
        FROM user_return_stock_items r
        WHERE r.from_usertype = 'territory_partner'
          AND r.invnumber IN (
              SELECT invoice_number
              FROM tp_invoices
              WHERE invoice_date BETWEEN :from_date AND :to_date
          )
        GROUP BY r.from_userid
    ");
    $stmt->execute([':from_date' => $current_from_date, ':to_date' => $current_to_date]);
    $return_data = [];
    foreach ($stmt->fetchAll() as $row) {
        $return_data[$row['user_id']] = (float)$row['return_points'];
    }

    // ── Collect all TP IDs that have any data ─────────────────────────────────
    $all_ids = array_unique(array_merge(
        array_keys($purchase_data),
        array_keys($daily_data),
        array_keys($return_data)
    ));

    if (!empty($all_ids)) {
        $placeholders = str_repeat('?,', count($all_ids) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT id, tp_id, name, mobile
            FROM territory_partners
            WHERE id IN ($placeholders)
            ORDER BY name ASC
        ");
        $stmt->execute($all_ids);
        $tp_details = [];
        foreach ($stmt->fetchAll() as $row) {
            $tp_details[$row['id']] = $row;
        }

        foreach ($all_ids as $uid) {
            $purchase_pts = $purchase_data[$uid] ?? 0;
            $daily_pts    = $daily_data[$uid]    ?? 0;
            $return_pts   = $return_data[$uid]   ?? 0;
            $total        = max(0, $purchase_pts + $daily_pts - $return_pts);

            $combined_users[] = [
                'user_id'         => $uid,
                'purchase_points' => $purchase_pts,
                'daily_points'    => $daily_pts,
                'return_points'   => $return_pts,
                'total_points'    => $total,
                'details'         => $tp_details[$uid] ?? null,
            ];
        }

        usort($combined_users, fn($a, $b) => $b['total_points'] <=> $a['total_points']);
    }

} catch (PDOException $e) {
    error_log("reward_points_tp error: " . $e->getMessage());
    $combined_users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reward Points - Territory Partners | <?php echo $safe_business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png" />
    <style>
        .rp-card { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); border:none; margin-bottom:20px; }
        .filter-bar { background:#fff; border-radius:10px; padding:18px 20px; box-shadow:0 1px 6px rgba(0,0,0,.07); margin-bottom:20px; }
        .badge-total { background:#4f46e5; color:#fff; font-size:13px; padding:5px 12px; border-radius:20px; font-weight:600; }
        .badge-purchase { background:#10b981; color:#fff; font-size:12px; padding:4px 10px; border-radius:12px; }
        .badge-daily { background:#3b82f6; color:#fff; font-size:12px; padding:4px 10px; border-radius:12px; }
        .badge-return { background:#ef4444; color:#fff; font-size:12px; padding:4px 10px; border-radius:12px; }
        table.dataTable thead th { background:#f8f9fa; font-weight:600; }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">

                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble" style="width:100%">
                                        <tr>
                                            <td>Reward Points — Territory Partners</td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Date Filter -->
                    <div class="filter-bar">
                        <form method="POST">
                            <div class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <label class="form-label mb-1">From Date</label>
                                    <input type="date" name="frdate" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($current_from_date, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1">To Date</label>
                                    <input type="date" name="todate" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($current_to_date, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="material-icons" style="vertical-align:middle;font-size:16px">search</i> Filter
                                    </button>
                                    <a href="reward_points_tp" class="btn btn-secondary btn-sm ms-1">Reset</a>
                                </div>
                                <div class="col-auto ms-auto">
                                    <small class="text-muted">
                                        <?php echo date('d M Y', strtotime($current_from_date)); ?> –
                                        <?php echo date('d M Y', strtotime($current_to_date)); ?>
                                    </small>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Table -->
                    <div class="card rp-card">
                        <div class="card-body">
                            <?php if (empty($combined_users)): ?>
                                <div class="alert alert-info mb-0">No reward points data found for this date range.</div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="rpTable" class="table table-hover table-sm" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>TP ID</th>
                                            <th>Name</th>
                                            <th>Mobile</th>
                                            <th>Purchase Pts</th>
                                            <th>Login Pts</th>
                                            <th>Returns (–)</th>
                                            <th>Total Points</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $sr = 1; foreach ($combined_users as $u): ?>
                                        <?php $d = $u['details']; ?>
                                        <tr>
                                            <td><?php echo $sr++; ?></td>
                                            <td><?php echo htmlspecialchars($d['tp_id'] ?? '–', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($d['name'] ?? '–', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($d['mobile'] ?? '–', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><span class="badge-purchase"><?php echo number_format($u['purchase_points'], 2); ?></span></td>
                                            <td><span class="badge-daily"><?php echo number_format($u['daily_points'], 2); ?></span></td>
                                            <td><?php if ($u['return_points'] > 0): ?><span class="badge-return"><?php echo number_format($u['return_points'], 2); ?></span><?php else: ?>–<?php endif; ?></td>
                                            <td><span class="badge-total"><?php echo number_format($u['total_points'], 2); ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="../../assets/js/main.min.js"></script>
<script>
$(function(){
    $('#rpTable').DataTable({
        order: [[7, 'desc']],
        pageLength: 25,
        language: { emptyTable: 'No data found' }
    });
});
</script>
</body>
</html>
