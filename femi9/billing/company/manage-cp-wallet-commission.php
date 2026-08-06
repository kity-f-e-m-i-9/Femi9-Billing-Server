<?php
/**
 * Manage CP Wallet Commission
 *
 * Browse/audit every "CP Commission" credit ever written into
 * wallet_monthly_sls_report for Channel Partners — whether inserted by the
 * automatic monthly cron (channel-partner/cron-cp-commission.php) or by the
 * admin dry-run/execute tool (cp-wallet-commission-calculator.php).
 *
 * Read-only by design: correcting a bad credit means rolling back its whole
 * execution from cp-wallet-commission-calculator.php (cron-credited rows
 * have no execution_id and can't be selectively rolled back from here —
 * this page exists to see what happened, not to hand-edit ledger rows).
 *
 * @author Femi9 Billing System
 * @version 1.0
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
require_once('config.php');

$title         = "Manage CP Wallet Commission";
$business_name = $business_name ?? 'Femi9 Billing';

// =====================================================================
// FILTERS
// =====================================================================

function validateMonthYear(?string $monthYear): ?string
{
    if (empty($monthYear) || !preg_match('/^\d{4}-\d{2}$/', $monthYear)) return null;
    return $monthYear;
}

$filter_cp_id      = isset($_GET['cp_id']) && $_GET['cp_id'] !== '' ? (int)base64_decode((string)$_GET['cp_id']) : null;
$filter_month_year = validateMonthYear($_GET['month_year'] ?? null);
$filter_source     = in_array($_GET['source'] ?? '', ['cron', 'manual'], true) ? $_GET['source'] : '';

$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 50;
$offset   = ($page - 1) * $perPage;

// CP dropdown list
$cp_list = [];
$cp_res = mysqli_query($db_conn, "SELECT id, cp_id, name, mobile FROM channel_partners ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($cp_res)) {
    $cp_list[] = $r;
}

// Build WHERE clause
$where = ["commission_type = 'CP Commission'"];
$types = "";
$params = [];

if ($filter_cp_id) {
    $where[] = "user_id = ?";
    $types  .= "s";
    $params[] = (string)$filter_cp_id;
}
if ($filter_month_year) {
    $where[] = "from_date = ?";
    $types  .= "s";
    $params[] = $filter_month_year . '-01';
}
if ($filter_source === 'cron') {
    $where[] = "(execution_id IS NULL OR execution_id = '')";
} elseif ($filter_source === 'manual') {
    $where[] = "execution_id IS NOT NULL AND execution_id != ''";
}

$whereSql = implode(' AND ', $where);

// Total count + sum for filtered set
$countStmt = $db_conn->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(commission_amount),0) AS total
    FROM wallet_monthly_sls_report WHERE $whereSql");
if ($types) { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$countRow  = $countStmt->get_result()->fetch_assoc();
$totalRows = (int)$countRow['cnt'];
$totalAmt  = (float)$countRow['total'];
$countStmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Fetch page of rows, joined to CP name/code
$listSql = "
    SELECT w.*, cp.name AS cp_name, cp.cp_id AS cp_code, cp.mobile AS cp_mobile
    FROM wallet_monthly_sls_report w
    LEFT JOIN channel_partners cp ON cp.id = w.user_id
    WHERE $whereSql
    ORDER BY w.from_date DESC, w.id DESC
    LIMIT $perPage OFFSET $offset
";
$listStmt = $db_conn->prepare($listSql);
if ($types) { $listStmt->bind_param($types, ...$params); }
$listStmt->execute();
$rows = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

function buildQueryString(array $overrides = []): string
{
    $base = [
        'cp_id'      => $_GET['cp_id']      ?? '',
        'month_year' => $_GET['month_year'] ?? '',
        'source'     => $_GET['source']     ?? '',
        'page'       => $_GET['page']       ?? '1',
    ];
    $merged = array_merge($base, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return http_build_query($merged);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />

    <style>
    .select2-container--default .select2-selection--single { border:2px solid #e5e7eb; border-radius:9px; height:auto; padding:11px 15px; font-size:14px; font-family:'Poppins',sans-serif; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:1.5; padding:0; color:#1e293b; }
    .select2-container { width:100% !important; }
    .filter-card { border-radius:16px; border:1px solid #f0f0f0; box-shadow:0 4px 16px rgba(0,0,0,0.05); }
    .summary-strip { display:flex; gap:1.5rem; flex-wrap:wrap; align-items:center; padding:1rem 1.5rem; background:linear-gradient(135deg,#eef2ff 0%,#e0e7ff 100%); border-radius:14px; margin-bottom:1.5rem; }
    .summary-strip .item { font-size:0.95rem; color:#3730a3; }
    .summary-strip .item b { font-size:1.15rem; }
    .badge-source-cron   { background:#dbeafe; color:#1e40af; padding:0.35rem 0.7rem; border-radius:8px; font-weight:600; font-size:0.78rem; }
    .badge-source-manual { background:#fef3c7; color:#92400e; padding:0.35rem 0.7rem; border-radius:8px; font-weight:600; font-size:0.78rem; }
    .table thead th { background:#f8fafc; color:#475569; font-weight:700; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.4px; }
    .table tbody td { vertical-align:middle; }
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
                                        <table class="headertble"><tr><td><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></td></tr></table>
                                    </h1>
                                </div>
                            </div>
                            <div class="col-auto d-flex align-items-center">
                                <a href="cp-wallet-commission-calculator" class="btn btn-primary" style="border-radius:10px;">
                                    <i class="material-icons-outlined" style="vertical-align:middle;font-size:18px">calculate</i>
                                    Run / Rollback Commission
                                </a>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card filter-card">
                                    <div class="card-body">
                                        <form method="GET" class="row g-3 align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label">Channel Partner</label>
                                                <select id="cpSelect" name="cp_id" class="form-control">
                                                    <option value="">All Channel Partners</option>
                                                    <?php foreach ($cp_list as $cp): ?>
                                                        <option value="<?php echo base64_encode((string)$cp['id']); ?>"
                                                            data-cpid="<?php echo htmlspecialchars($cp['cp_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-mobile="<?php echo htmlspecialchars($cp['mobile'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            <?php echo ($filter_cp_id === (int)$cp['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($cp['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($cp['cp_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Month</label>
                                                <input type="month" name="month_year" class="form-control" value="<?php echo htmlspecialchars($filter_month_year ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Credited Via</label>
                                                <select name="source" class="form-control">
                                                    <option value="" <?php echo $filter_source === '' ? 'selected' : ''; ?>>All Sources</option>
                                                    <option value="cron" <?php echo $filter_source === 'cron' ? 'selected' : ''; ?>>Auto Cron</option>
                                                    <option value="manual" <?php echo $filter_source === 'manual' ? 'selected' : ''; ?>>Manual Tool</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2 d-flex gap-2">
                                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                                                <a href="manage-cp-wallet-commission" class="btn btn-outline-secondary">Reset</a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="summary-strip mt-4">
                            <div class="item">Entries: <b><?php echo number_format($totalRows); ?></b></div>
                            <div class="item">Total Commission: <b>&#8377;<?php echo inr_format($totalAmt, 2); ?></b></div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Channel Partner</th>
                                                        <th>Period</th>
                                                        <th>Sales (Net)</th>
                                                        <th>Deposit</th>
                                                        <th>Basis</th>
                                                        <th>Commission</th>
                                                        <th>Credited Via</th>
                                                        <th>Recorded At</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($rows)): ?>
                                                        <tr><td colspan="8" class="text-center text-muted py-4">No CP commission entries found for this filter.</td></tr>
                                                    <?php endif; ?>
                                                    <?php foreach ($rows as $row): ?>
                                                        <?php $isManual = !empty($row['execution_id']); ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($row['cp_name'] ?? ('CP #' . $row['user_id']), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($row['cp_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($row['month'] . ' ' . $row['year'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td>&#8377;<?php echo inr_format((float)$row['total_sls_amount'], 0); ?></td>
                                                            <td>&#8377;<?php echo inr_format((float)$row['target_sls_amount'], 0); ?></td>
                                                            <td><small class="text-muted"><?php echo htmlspecialchars($row['remarks'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small></td>
                                                            <td><strong style="color:#059669;">&#8377;<?php echo inr_format((float)$row['commission_amount'], 2); ?></strong></td>
                                                            <td>
                                                                <?php if ($isManual): ?>
                                                                    <span class="badge-source-manual">Manual Tool</span><br>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($row['execution_id'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                                <?php else: ?>
                                                                    <span class="badge-source-cron">Auto Cron</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><small class="text-muted"><?php echo !empty($row['created_at']) ? date('d M Y H:i', strtotime($row['created_at'])) : '—'; ?></small></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <?php if ($totalPages > 1): ?>
                                            <nav class="mt-3">
                                                <ul class="pagination justify-content-center">
                                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                                            <a class="page-link" href="?<?php echo buildQueryString(['page' => (string)$p]); ?>"><?php echo $p; ?></a>
                                                        </li>
                                                    <?php endfor; ?>
                                                </ul>
                                            </nav>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
    <script>
    $(function () {
        function cpMatcher(params, data) {
            if (!params.term || params.term.trim() === '') return data;
            var q = params.term.trim().toLowerCase();
            if ((data.text || '').toLowerCase().indexOf(q) > -1) return data;
            if (data.element) {
                var cpid   = (data.element.getAttribute('data-cpid')   || '').toLowerCase();
                var mobile = (data.element.getAttribute('data-mobile')  || '').toLowerCase();
                if (cpid.indexOf(q) > -1 || mobile.indexOf(q) > -1) return data;
            }
            return null;
        }
        $('#cpSelect').select2({
            placeholder: 'Search by name, CP ID or mobile…',
            allowClear: true,
            matcher: cpMatcher
        });
    });
    </script>
</body>
</html>
