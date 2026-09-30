<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("include/PartnerZones.php");
require_once("include/GodownAccess.php");
require_once __DIR__ . '/../shared/number-format-helpers.php';
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);
include("config.php");
tpEnsureAdvanceWalletColumns($db_conn);
ensurePartnerZonesTables($db_conn);

$zones = getAllZonesWithCounts($db_conn);

$selectedZoneId = (int)($_GET['zone_id'] ?? 0);
$filter_from    = $_GET['from_date'] ?? date('Y-m-01');
$filter_to      = $_GET['to_date']   ?? date('Y-m-d');
$filter_tp      = (int)($_GET['tp_id'] ?? 0);
$filter_company = (int)($_GET['company_id'] ?? 0);
$filter_type    = $_GET['type_filter'] ?? '';

// Both multi-select — arrive as arrays (?status[]=...&district[]=...).
$allowed_statuses = ['active', 'partially_adjusted', 'fully_adjusted'];
$filter_statuses = array_values(array_intersect((array)($_GET['status'] ?? []), $allowed_statuses));
$filter_districts = array_values(array_filter(array_map('trim', (array)($_GET['district'] ?? []))));

if (!in_array($filter_type, ['napkin', 'diaper'], true)) $filter_type = '';

$company_profiles = $db_conn->query("SELECT id, gname FROM company_godown WHERE gname LIKE '%Femi%' AND " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);

$stats = null;
$selectedZoneName = '';
$zoneTps = [];
$detailRows = [];
$districtList = [];
$tpEntryCounts = [];
$tpBalanceByTp = [];
$tpBalanceByCompany = []; // [company_id => [tp_id => ['tp_name','tp_code','balance']]]
$tpEntryCountsByCompany = []; // [company_id => [tp_id => ['tp_name','tp_code','count']]]
if ($selectedZoneId > 0) {
    $stmt = $db_conn->prepare("SELECT name FROM partner_zones WHERE id = ?");
    $stmt->bind_param('i', $selectedZoneId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $selectedZoneName = $row['name'];
        $tpIds = getZoneTpIds($db_conn, $selectedZoneId, true);
        $tpDistricts = [];
        if (!empty($tpIds)) {
            $placeholders0 = implode(',', array_fill(0, count($tpIds), '?'));
            $stmtT = $db_conn->prepare("SELECT id, tp_id, name, COALESCE(NULLIF(assigned_district,''), branch_district) AS district_name FROM territory_partners WHERE id IN ($placeholders0) ORDER BY name");
            $typesT = str_repeat('i', count($tpIds));
            $stmtT->bind_param($typesT, ...$tpIds);
            $stmtT->execute();
            $zoneTps = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtT->close();
            foreach ($zoneTps as $_t) { $tpDistricts[(int)$_t['id']] = $_t['district_name'] ?? ''; }
        }

        // Distinct district names within this zone, for the District filter.
        $districtList = array_values(array_unique(array_filter(array_column($zoneTps, 'district_name'))));
        sort($districtList);
        $filter_districts = array_values(array_intersect($filter_districts, $districtList));

        // Restricts the query to only TPs in the selected district(s) — the
        // stats/detail table both key off territory_partner_id, so
        // narrowing this list is enough to scope the whole report.
        $scopedTpIds = !empty($filter_districts)
            ? array_keys(array_filter($tpDistricts, fn($d) => in_array($d, $filter_districts, true)))
            : $tpIds;

        $stats = ['count' => 0, 'amount' => 0.0, 'balance' => 0.0, 'adjusted' => 0.0];
        $stats_by_type = ['napkin' => ['count' => 0, 'amount' => 0.0, 'balance' => 0.0, 'adjusted' => 0.0], 'diaper' => ['count' => 0, 'amount' => 0.0, 'balance' => 0.0, 'adjusted' => 0.0]];

        if (!empty($scopedTpIds)) {
            $where = ["tap.deleted_at IS NULL", "tap.territory_partner_id IN (" . implode(',', array_map('intval', $scopedTpIds)) . ")", "tap.payment_date BETWEEN ? AND ?"];
            $params = [$filter_from, $filter_to];
            $types = "ss";

            if ($filter_tp > 0 && in_array($filter_tp, $scopedTpIds, true)) {
                $where[] = "tap.territory_partner_id = ?";
                $params[] = $filter_tp;
                $types .= "i";
            }
            if ($filter_company > 0) {
                $where[] = "tap.company_id = ?";
                $params[] = $filter_company;
                $types .= "i";
            }
            if (!empty($filter_statuses)) {
                $where[] = "tap.status IN (" . implode(',', array_fill(0, count($filter_statuses), '?')) . ")";
                foreach ($filter_statuses as $fs) { $params[] = $fs; $types .= "s"; }
            }
            if ($filter_type !== '') {
                $where[] = "tap.product_type = ?";
                $params[] = $filter_type;
                $types .= "s";
            }

            $stmt2 = $db_conn->prepare("
                SELECT tap.*, tp.name AS tp_name, tp.tp_id AS tp_code,
                       COALESCE(NULLIF(tp.assigned_district,''), tp.branch_district) AS tp_district,
                       cg.gname AS receiver_name
                FROM tp_advance_payments tap
                JOIN territory_partners tp ON tp.id = tap.territory_partner_id
                LEFT JOIN company_godown cg ON cg.id = tap.company_id
                WHERE " . implode(" AND ", $where) . "
                ORDER BY tap.payment_date DESC, tap.id DESC
            ");
            $stmt2->bind_param($types, ...$params);
            $stmt2->execute();
            $detailRows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt2->close();

            foreach ($detailRows as $r) {
                $t = ($r['product_type'] ?? 'napkin') === 'diaper' ? 'diaper' : 'napkin';
                $stats['count']++;
                $stats['amount']   += (float)$r['amount'];
                $stats['balance']  += (float)$r['balance_amount'];
                $stats['adjusted'] += (float)$r['adjusted_amount'];
                $stats_by_type[$t]['count']++;
                $stats_by_type[$t]['amount']   += (float)$r['amount'];
                $stats_by_type[$t]['balance']  += (float)$r['balance_amount'];
                $stats_by_type[$t]['adjusted'] += (float)$r['adjusted_amount'];

                $tpKey = (int)$r['territory_partner_id'];
                if (!isset($tpEntryCounts[$tpKey])) {
                    $tpEntryCounts[$tpKey] = ['tp_name' => $r['tp_name'], 'tp_code' => $r['tp_code'], 'count' => 0];
                }
                $tpEntryCounts[$tpKey]['count']++;

                if (!isset($tpBalanceByTp[$tpKey])) {
                    $tpBalanceByTp[$tpKey] = ['tp_name' => $r['tp_name'], 'tp_code' => $r['tp_code'], 'balance' => 0.0];
                }
                $tpBalanceByTp[$tpKey]['balance'] += (float)$r['balance_amount'];

                // Same breakdowns, but bucketed per receiver company too —
                // the Total Payments / Total Balance modals' "All
                // Receivers" vs a specific company (e.g. FEMI NAYAN LLP)
                // tabs read from these per-company buckets instead of the
                // combined ones above.
                $companyKey = (int)($r['company_id'] ?? 0);
                if ($companyKey > 0) {
                    if (!isset($tpBalanceByCompany[$companyKey][$tpKey])) {
                        $tpBalanceByCompany[$companyKey][$tpKey] = ['tp_name' => $r['tp_name'], 'tp_code' => $r['tp_code'], 'balance' => 0.0];
                    }
                    $tpBalanceByCompany[$companyKey][$tpKey]['balance'] += (float)$r['balance_amount'];

                    if (!isset($tpEntryCountsByCompany[$companyKey][$tpKey])) {
                        $tpEntryCountsByCompany[$companyKey][$tpKey] = ['tp_name' => $r['tp_name'], 'tp_code' => $r['tp_code'], 'count' => 0];
                    }
                    $tpEntryCountsByCompany[$companyKey][$tpKey]['count']++;
                }
            }
            usort($tpEntryCounts, fn($a, $b) => $b['count'] <=> $a['count']);
            // Only TPs still carrying an actual balance are worth showing in
            // the Total Balance card's modal — one that's fully adjusted
            // (balance 0) would just be noise in that breakdown.
            $tpBalanceByTp = array_values(array_filter($tpBalanceByTp, fn($t) => $t['balance'] > 0));
            usort($tpBalanceByTp, fn($a, $b) => $b['balance'] <=> $a['balance']);
            foreach ($tpBalanceByCompany as $companyKey => $rowsForCompany) {
                $rowsForCompany = array_values(array_filter($rowsForCompany, fn($t) => $t['balance'] > 0));
                usort($rowsForCompany, fn($a, $b) => $b['balance'] <=> $a['balance']);
                $tpBalanceByCompany[$companyKey] = $rowsForCompany;
            }
            foreach ($tpEntryCountsByCompany as $companyKey => $rowsForCompany) {
                $rowsForCompany = array_values($rowsForCompany);
                usort($rowsForCompany, fn($a, $b) => $b['count'] <=> $a['count']);
                $tpEntryCountsByCompany[$companyKey] = $rowsForCompany;
            }
        }
    } else {
        $selectedZoneId = 0;
    }
}

// Carries every current filter forward — used by the "View Full List" link
// so nothing gets silently dropped when jumping to the detailed table.
function zoneQS(array $overrides = []): string {
    global $selectedZoneId, $filter_from, $filter_to, $filter_tp, $filter_company, $filter_statuses, $filter_type, $filter_districts;
    $qs = array_merge([
        'zone_id' => $selectedZoneId, 'from_date' => $filter_from, 'to_date' => $filter_to,
        'tp_id' => $filter_tp ?: '', 'company_id' => $filter_company ?: '', 'status' => $filter_statuses,
        'type_filter' => $filter_type, 'district' => $filter_districts,
    ], $overrides);
    return http_build_query(array_filter($qs, fn($v) => $v !== '' && $v !== 0 && $v !== []));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Zone wise Advance Payments : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        .zone-pick-wrap { display:flex; flex-wrap:wrap; justify-content:center; gap:20px; }
        .zone-pick-card {
            font-family: 'Poppins', sans-serif;
            background:#fff; border-radius:14px; padding:34px 24px;
            box-shadow:0 2px 10px rgba(0,0,0,0.05); border:2px solid #e5e7eb;
            text-decoration:none; display:flex; flex-direction:column; align-items:center; text-align:center;
            color:inherit; transition:border-color .15s, box-shadow .15s, transform .15s;
            width:260px;
        }
        .zone-pick-card:hover { border-color:#818cf8; box-shadow:0 6px 18px rgba(102,126,234,0.15); color:inherit; transform:translateY(-2px); }
        .zone-pick-card .zone-icon {
            width:56px; height:56px; border-radius:12px; background:#eef0ff; color:#667eea;
            display:flex; align-items:center; justify-content:center; margin-bottom:14px;
        }
        .zone-pick-card .zone-icon .material-icons-outlined { font-size:28px; }
        .zone-pick-card h4 { margin:0 0 6px 0; font-weight:700; font-size:19px; color:#1f2937; }
        .zone-pick-card .meta { color:#6b7280; font-size:13px; }
        .stats-card { background:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-left:4px solid #667eea; }
        .stats-card h3 { font-size:26px; font-weight:700; margin:0; color:#667eea; }
        .stats-card p { margin:4px 0 0 0; color:#6b7280; font-size:13px; font-weight:500; }
        .filter-card { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px; padding:20px; margin-bottom:20px; }
        .filter-card .form-label { color:#fff; font-weight:500; margin-bottom:5px; }
        .filter-card .form-control, .filter-card select { background:rgba(255,255,255,0.95); border:none; border-radius:6px; }
        .filter-card .select2-container--default .select2-selection--single {
            background:rgba(255,255,255,0.95); border:none; border-radius:6px;
            height:38px; display:flex; align-items:center;
        }
        .filter-card .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height:normal; padding-left:12px; color:#374151;
        }
        .filter-card .select2-container--default .select2-selection--single .select2-selection__arrow {
            height:36px;
        }
        .filter-card .select2-container--default .select2-selection--multiple {
            background:rgba(255,255,255,0.95); border:none; border-radius:6px; min-height:38px;
        }
        .filter-card .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            color:#374151;
        }
        .check-dropdown .dropdown-toggle { background:rgba(255,255,255,0.95); color:#374151; border:none; }
        .check-dropdown .dropdown-toggle:focus { box-shadow:none; }
        .check-dropdown-menu {
            max-height:260px; overflow-y:auto; padding:6px; min-width:220px;
        }
        .check-dropdown-item {
            display:block; padding:6px 8px; margin:0; font-size:13.5px; color:#374151;
            border-radius:5px; cursor:pointer; font-weight:400;
        }
        .check-dropdown-item:hover { background:#f3f4f6; }
        .check-dropdown-item input { margin-right:7px; }
        .tp-balance-clickable { cursor:pointer; transition:box-shadow .15s, transform .15s; }
        .tp-balance-clickable:hover { box-shadow:0 4px 14px rgba(0,0,0,0.10); transform:translateY(-1px); }
        .balance-scope-btn.active { background:#667eea; color:#fff; border-color:#667eea; }
        .tp-count-badge { display:inline-block; background:#eef0ff; color:#667eea; font-size:10.5px; font-weight:700; padding:1px 7px; border-radius:8px; margin-left:5px; vertical-align:middle; }
        .tp-count-popover-row { display:flex; justify-content:space-between; gap:10px; font-size:12.5px; color:#374151; padding:4px 0; border-bottom:1px solid #f3f4f6; }
        .tp-count-popover-row:last-child { border-bottom:none; }
        .tp-action-link { font-size:13px; font-weight:600; text-decoration:none; padding:6px 13px; border-radius:6px; display:inline-block; }
        .tp-action-success { color:#374151; background:#f3f4f6; border:1px solid #d1d5db; }
        .tp-action-success:hover { color:#111827; background:#e9eaec; }
        .status-active { background:#d1fae5; color:#065f46; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .status-partially { background:#fef3c7; color:#92400e; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .status-fully { background:#dbeafe; color:#1e40af; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
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
                                        <table class="headertble"><tr>
                                            <td>TP Zone wise Advance Payments<?php if ($selectedZoneId > 0): ?> &mdash; <?=htmlspecialchars($selectedZoneName)?><?php endif; ?></td>
                                        </tr></table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <?php if ($selectedZoneId === 0): ?>
                            <?php if (empty($zones)): ?>
                            <div class="alert alert-info">No zones set up yet. <a href="manage-zones.php">Create one first</a>.</div>
                            <?php else: ?>
                            <div class="zone-pick-wrap">
                                <?php foreach ($zones as $z): ?>
                                <a class="zone-pick-card" href="tp-zone-advance-payments.php?zone_id=<?=(int)$z['id']?>">
                                    <div class="zone-icon"><i class="material-icons-outlined">map</i></div>
                                    <h4><?=htmlspecialchars($z['name'])?></h4>
                                    <div class="meta"><?=(int)$z['district_count']?> district(s) &middot; <?=(int)$z['tp_count']?> TP(s)</div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="mb-2">
                                <a href="tp-zone-advance-payments.php" class="tp-action-link tp-action-success"><i class="material-icons" style="font-size:14px;vertical-align:middle;">arrow_back</i> All Zones</a>
                                <a href="manage-tp-advance-payments.php?<?=zoneQS()?>" class="tp-action-link tp-action-success">
                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">list_alt</i> View Full List
                                </a>
                            </div>

                            <!-- Stats -->
                            <div class="row">
                                <div class="col-lg-3 col-md-6">
                                    <div class="stats-card<?php echo !empty($tpEntryCounts) ? ' tp-balance-clickable' : ''; ?>" <?php echo !empty($tpEntryCounts) ? 'onclick="openPaymentsBreakdownModal()"' : ''; ?>>
                                        <h3><?php echo $stats['count']; ?></h3>
                                        <p>Total Payments <span class="tp-count-badge"><?=count($tpEntryCounts)?> TP(s)</span></p>
                                        <p style="font-size:11.5px;color:#9ca3af;margin-top:4px;">
                                            <?php echo $stats_by_type['napkin']['count']; ?> Napkin &middot; <?php echo $stats_by_type['diaper']['count']; ?> Diaper
                                        </p>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="stats-card">
                                        <h3>&#8377;<?php echo inr_format($stats['amount'], 2); ?></h3>
                                        <p>Total Amount</p>
                                        <p style="font-size:11.5px;color:#9ca3af;margin-top:4px;">
                                            &#8377;<?php echo inr_format($stats_by_type['napkin']['amount'], 2); ?> Napkin &middot; &#8377;<?php echo inr_format($stats_by_type['diaper']['amount'], 2); ?> Diaper
                                        </p>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="stats-card<?php echo !empty($tpBalanceByTp) ? ' tp-balance-clickable' : ''; ?>" <?php echo !empty($tpBalanceByTp) ? 'onclick="openBalanceBreakdownModal()"' : ''; ?>>
                                        <h3>&#8377;<?php echo inr_format($stats['balance'], 2); ?></h3>
                                        <p>Total Balance <?php if (!empty($tpBalanceByTp)): ?><span class="tp-count-badge"><?=count($tpBalanceByTp)?> TP(s)</span><?php endif; ?></p>
                                        <p style="font-size:11.5px;color:#9ca3af;margin-top:4px;">
                                            &#8377;<?php echo inr_format($stats_by_type['napkin']['balance'], 2); ?> Napkin &middot; &#8377;<?php echo inr_format($stats_by_type['diaper']['balance'], 2); ?> Diaper
                                        </p>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="stats-card">
                                        <h3>&#8377;<?php echo inr_format($stats['adjusted'], 2); ?></h3>
                                        <p>Adjusted Amount</p>
                                        <p style="font-size:11.5px;color:#9ca3af;margin-top:4px;">
                                            &#8377;<?php echo inr_format($stats_by_type['napkin']['adjusted'], 2); ?> Napkin &middot; &#8377;<?php echo inr_format($stats_by_type['diaper']['adjusted'], 2); ?> Diaper
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Balance breakdown modal — click-to-open (not hover, so it also
                                 works on touch), with an "All Receivers" tab plus one tab per receiver
                                 company (e.g. FEMI NAYAN LLP), each showing that scope's own
                                 TP-wise balance list. -->
                            <?php if (!empty($tpBalanceByTp)): ?>
                            <div class="modal fade" id="balanceBreakdownModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title" style="font-weight:700;">Total Balance — by TP</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="btn-group mb-3" role="group">
                                                <?php foreach ($company_profiles as $i => $cp): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary balance-scope-btn<?=$i === 0 ? ' active' : ''?>" data-scope="company-<?=$cp['id']?>"><?=htmlspecialchars($cp['gname'])?></button>
                                                <?php endforeach; ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary balance-scope-btn<?=empty($company_profiles) ? ' active' : ''?>" data-scope="all">All Receivers</button>
                                            </div>
                                            <?php foreach ($company_profiles as $i => $cp): $rowsForCp = $tpBalanceByCompany[$cp['id']] ?? []; $cpTotal = array_sum(array_column($rowsForCp, 'balance')); ?>
                                            <div class="balance-scope-pane" data-scope="company-<?=$cp['id']?>" <?=$i === 0 ? '' : 'style="display:none;"'?>>
                                                <div class="tp-count-popover-row" style="font-weight:700;border-bottom:2px solid #e5e7eb;">
                                                    <span>Total</span>
                                                    <b>&#8377;<?=inr_format($cpTotal, 2)?></b>
                                                </div>
                                                <?php if (empty($rowsForCp)): ?>
                                                <p class="text-muted small mb-0 mt-2">No balance under this receiver.</p>
                                                <?php else: foreach ($rowsForCp as $tb): ?>
                                                <div class="tp-count-popover-row">
                                                    <span><?=htmlspecialchars($tb['tp_name'])?> <code style="font-size:10.5px;"><?=htmlspecialchars($tb['tp_code'])?></code></span>
                                                    <b>&#8377;<?=inr_format($tb['balance'], 2)?></b>
                                                </div>
                                                <?php endforeach; endif; ?>
                                            </div>
                                            <?php endforeach; $allTotal = array_sum(array_column($tpBalanceByTp, 'balance')); ?>
                                            <div class="balance-scope-pane" data-scope="all" <?=empty($company_profiles) ? '' : 'style="display:none;"'?>>
                                                <div class="tp-count-popover-row" style="font-weight:700;border-bottom:2px solid #e5e7eb;">
                                                    <span>Total</span>
                                                    <b>&#8377;<?=inr_format($allTotal, 2)?></b>
                                                </div>
                                                <?php foreach ($tpBalanceByTp as $tb): ?>
                                                <div class="tp-count-popover-row">
                                                    <span><?=htmlspecialchars($tb['tp_name'])?> <code style="font-size:10.5px;"><?=htmlspecialchars($tb['tp_code'])?></code></span>
                                                    <b>&#8377;<?=inr_format($tb['balance'], 2)?></b>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Total Payments breakdown modal — same click-to-open, company-tabs
                                 pattern as the Total Balance modal above, but counting entries per
                                 TP instead of summing balance. -->
                            <?php if (!empty($tpEntryCounts)): ?>
                            <div class="modal fade" id="paymentsBreakdownModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title" style="font-weight:700;">Total Payments — by TP</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="btn-group mb-3" role="group">
                                                <?php foreach ($company_profiles as $i => $cp): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary balance-scope-btn<?=$i === 0 ? ' active' : ''?>" data-scope="company-<?=$cp['id']?>"><?=htmlspecialchars($cp['gname'])?></button>
                                                <?php endforeach; ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary balance-scope-btn<?=empty($company_profiles) ? ' active' : ''?>" data-scope="all">All Receivers</button>
                                            </div>
                                            <?php foreach ($company_profiles as $i => $cp): $rowsForCp = $tpEntryCountsByCompany[$cp['id']] ?? []; $cpTotal = array_sum(array_column($rowsForCp, 'count')); ?>
                                            <div class="balance-scope-pane" data-scope="company-<?=$cp['id']?>" <?=$i === 0 ? '' : 'style="display:none;"'?>>
                                                <div class="tp-count-popover-row" style="font-weight:700;border-bottom:2px solid #e5e7eb;">
                                                    <span>Total</span>
                                                    <b><?=(int)$cpTotal?></b>
                                                </div>
                                                <?php if (empty($rowsForCp)): ?>
                                                <p class="text-muted small mb-0 mt-2">No entries under this receiver.</p>
                                                <?php else: foreach ($rowsForCp as $tec): ?>
                                                <div class="tp-count-popover-row">
                                                    <span><?=htmlspecialchars($tec['tp_name'])?> <code style="font-size:10.5px;"><?=htmlspecialchars($tec['tp_code'])?></code></span>
                                                    <b><?=(int)$tec['count']?></b>
                                                </div>
                                                <?php endforeach; endif; ?>
                                            </div>
                                            <?php endforeach; $allEntryTotal = array_sum(array_column($tpEntryCounts, 'count')); ?>
                                            <div class="balance-scope-pane" data-scope="all" <?=empty($company_profiles) ? '' : 'style="display:none;"'?>>
                                                <div class="tp-count-popover-row" style="font-weight:700;border-bottom:2px solid #e5e7eb;">
                                                    <span>Total</span>
                                                    <b><?=(int)$allEntryTotal?></b>
                                                </div>
                                                <?php foreach ($tpEntryCounts as $tec): ?>
                                                <div class="tp-count-popover-row">
                                                    <span><?=htmlspecialchars($tec['tp_name'])?> <code style="font-size:10.5px;"><?=htmlspecialchars($tec['tp_code'])?></code></span>
                                                    <b><?=(int)$tec['count']?></b>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Filters -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="filter-card">
                                        <form method="GET" action="">
                                            <input type="hidden" name="zone_id" value="<?=(int)$selectedZoneId?>">
                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-2">
                                                    <label class="form-label">District</label>
                                                    <div class="dropdown check-dropdown">
                                                        <button type="button" class="form-control text-start dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                                                            <span class="check-dropdown-label"><?= empty($filter_districts) ? 'All Districts' : count($filter_districts) . ' District(s)' ?></span>
                                                        </button>
                                                        <div class="dropdown-menu check-dropdown-menu">
                                                            <?php foreach ($districtList as $d): ?>
                                                            <label class="check-dropdown-item">
                                                                <input type="checkbox" class="check-dropdown-input" data-group="district" name="district[]" value="<?=htmlspecialchars($d)?>" <?=in_array($d, $filter_districts, true) ? 'checked' : ''?>>
                                                                <?=htmlspecialchars($d)?>
                                                            </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">From Date</label>
                                                    <input type="date" name="from_date" class="form-control" value="<?=htmlspecialchars($filter_from)?>" max="<?php echo date('Y-m-d'); ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">To Date</label>
                                                    <input type="date" name="to_date" class="form-control" value="<?=htmlspecialchars($filter_to)?>" max="<?php echo date('Y-m-d'); ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Payer Name</label>
                                                    <select name="tp_id" id="zoneTpPayerSelect" class="form-control">
                                                        <option value="">All Payers</option>
                                                        <?php foreach ($zoneTps as $tp): ?>
                                                        <option value="<?=$tp['id']?>" <?=$filter_tp == $tp['id'] ? 'selected' : ''?>>
                                                            <?=htmlspecialchars($tp['name'])?> (<?=htmlspecialchars($tp['tp_id'])?>)
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Receiver Name</label>
                                                    <select name="company_id" class="form-control">
                                                        <option value="">All Receivers</option>
                                                        <?php foreach ($company_profiles as $cp): ?>
                                                        <option value="<?=$cp['id']?>" <?=$filter_company == $cp['id'] ? 'selected' : ''?>><?=htmlspecialchars($cp['gname'])?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Type</label>
                                                    <select name="type_filter" class="form-control">
                                                        <option value="">Napkin + Diaper</option>
                                                        <option value="napkin" <?=$filter_type === 'napkin' ? 'selected' : ''?>>Napkin only</option>
                                                        <option value="diaper" <?=$filter_type === 'diaper' ? 'selected' : ''?>>Lumi Diaper only</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Status</label>
                                                    <div class="dropdown check-dropdown">
                                                        <button type="button" class="form-control text-start dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                                                            <span class="check-dropdown-label"><?= empty($filter_statuses) ? 'All Status' : count($filter_statuses) . ' selected' ?></span>
                                                        </button>
                                                        <div class="dropdown-menu check-dropdown-menu">
                                                            <label class="check-dropdown-item">
                                                                <input type="checkbox" class="check-dropdown-input" data-group="status" name="status[]" value="active" <?=in_array('active', $filter_statuses, true) ? 'checked' : ''?>>
                                                                Active
                                                            </label>
                                                            <label class="check-dropdown-item">
                                                                <input type="checkbox" class="check-dropdown-input" data-group="status" name="status[]" value="partially_adjusted" <?=in_array('partially_adjusted', $filter_statuses, true) ? 'checked' : ''?>>
                                                                Partially Adjusted
                                                            </label>
                                                            <label class="check-dropdown-item">
                                                                <input type="checkbox" class="check-dropdown-input" data-group="status" name="status[]" value="fully_adjusted" <?=in_array('fully_adjusted', $filter_statuses, true) ? 'checked' : ''?>>
                                                                Fully Adjusted
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row g-2 mt-1">
                                                <div class="col-auto">
                                                    <button type="submit" class="btn btn-light font-weight-bold">
                                                        <i class="material-icons" style="vertical-align:middle;font-size:17px;">filter_list</i> Filter
                                                    </button>
                                                </div>
                                                <div class="col-auto">
                                                    <a href="tp-zone-advance-payments.php?zone_id=<?=(int)$selectedZoneId?>" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.5);">
                                                        <i class="material-icons" style="vertical-align:middle;font-size:17px;">refresh</i> Reset
                                                    </a>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Details -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-body">
                                            <div style="overflow-x:auto;">
                                                <table id="zoneAdvPayTable" style="width:100%;">
                                                    <thead>
                                                        <tr>
                                                            <th>S.No</th>
                                                            <th>TP Name</th>
                                                            <th>TP ID</th>
                                                            <th>District</th>
                                                            <th>Type</th>
                                                            <th>Receiver</th>
                                                            <th>Date</th>
                                                            <th>Amount (&#8377;)</th>
                                                            <th>Balance (&#8377;)</th>
                                                            <th>Adjusted (&#8377;)</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php $_sno = 0; foreach ($detailRows as $dr): $_sno++; ?>
                                                        <tr>
                                                            <td><?=$_sno?></td>
                                                            <td><?=htmlspecialchars($dr['tp_name'] ?? '')?></td>
                                                            <td><code style="font-size:12px;"><?=htmlspecialchars($dr['tp_code'] ?? '')?></code></td>
                                                            <td><?=htmlspecialchars($dr['tp_district'] ?: '—')?></td>
                                                            <td>
                                                                <?php $ptype = tpResolveProductType($dr['product_type'] ?? null); [$ptBg, $ptFg] = tpProductTypeBadgeColors($ptype); ?>
                                                                <span style="background:<?=$ptBg?>;color:<?=$ptFg?>;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;"><?=tpProductTypeLabel($ptype)?></span>
                                                            </td>
                                                            <td><?=htmlspecialchars($dr['receiver_name'] ?: '—')?></td>
                                                            <td><?=htmlspecialchars(date('d M Y', strtotime($dr['payment_date'])))?></td>
                                                            <td class="text-right font-weight-bold"><?=inr_format($dr['amount'], 2)?></td>
                                                            <td class="text-right" style="color:#10b981;font-weight:600;"><?=inr_format($dr['balance_amount'], 2)?></td>
                                                            <td class="text-right"><?=inr_format($dr['adjusted_amount'], 2)?></td>
                                                            <td>
                                                                <?php
                                                                // Display-only: derive the badge from balance/adjusted
                                                                // rather than trusting the stored status column verbatim
                                                                // — a handful of real records have balance_amount = 0
                                                                // (nothing owed) but were saved with status = 'active',
                                                                // which reads wrong here even though the underlying
                                                                // data isn't otherwise touched.
                                                                $_bal = (float)($dr['balance_amount'] ?? 0);
                                                                $_adj = (float)($dr['adjusted_amount'] ?? 0);
                                                                if ($_bal <= 0.001): ?>
                                                                <span class="status-fully">Fully Adjusted</span>
                                                                <?php elseif ($_adj > 0.001): ?>
                                                                <span class="status-partially">Partially Adjusted</span>
                                                                <?php else: ?>
                                                                <span class="status-active">Active</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

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
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <?php if ($selectedZoneId > 0): ?>
    <script>
    $(document).ready(function () {
        $('#zoneTpPayerSelect').select2({ placeholder: 'All Payers', allowClear: true, width: '100%' });

        // Checkbox dropdowns (District/Status) — clicking the label text
        // (not just the tiny checkbox box) toggles it, since the checkbox
        // sits inside the <label>. Keep the button's own text in sync with
        // however many are currently checked.
        function updateCheckDropdownLabel(group, defaultText) {
            var $inputs = $('.check-dropdown-input[data-group="' + group + '"]');
            var checked = $inputs.filter(':checked').length;
            var $label = $inputs.first().closest('.check-dropdown-menu').siblings('.dropdown-toggle').find('.check-dropdown-label');
            if (checked === 0) {
                $label.text(defaultText);
            } else if (group === 'district') {
                $label.text(checked + ' District(s)');
            } else {
                $label.text(checked + ' selected');
            }
        }
        $('.check-dropdown-input[data-group="district"]').on('change', function () { updateCheckDropdownLabel('district', 'All Districts'); });
        $('.check-dropdown-input[data-group="status"]').on('change', function () { updateCheckDropdownLabel('status', 'All Status'); });

        $('#zoneAdvPayTable').DataTable({
            dom: '<"row"<"col-sm-6"l><"col-sm-6"f>><"row"<"col-sm-12"B>><"row"<"col-sm-12"tr>><"row"<"col-sm-5"i><"col-sm-7"p>>',
            buttons: [
                { extend: 'excel', text: '<i class="material-icons" style="vertical-align:middle">download</i> Excel', className: 'btn btn-success' },
                { extend: 'print', text: '<i class="material-icons" style="vertical-align:middle">print</i> Print', className: 'btn btn-info' }
            ]
        });

        // Total Payments / Total Balance cards' breakdown modals — "All
        // Receivers" vs a specific receiver company (e.g. FEMI NAYAN LLP).
        // Both modals share this same tab markup/classes, so the switch is
        // scoped to whichever modal-body the clicked button is actually in
        // — otherwise clicking a tab in one modal would also flip the
        // other modal's panes. All panes are already rendered server-side,
        // so switching is just a show/hide, no re-fetch needed.
        $('.balance-scope-btn').on('click', function () {
            var $scope = $(this).closest('.modal-body');
            var scope = $(this).data('scope');
            $scope.find('.balance-scope-btn').removeClass('active');
            $(this).addClass('active');
            $scope.find('.balance-scope-pane').hide();
            $scope.find('.balance-scope-pane[data-scope="' + scope + '"]').show();
        });
    });

    function openBalanceBreakdownModal() {
        var modalEl = document.getElementById('balanceBreakdownModal');
        if (!modalEl) return;
        new bootstrap.Modal(modalEl).show();
    }
    function openPaymentsBreakdownModal() {
        var modalEl = document.getElementById('paymentsBreakdownModal');
        if (!modalEl) return;
        new bootstrap.Modal(modalEl).show();
    }
    </script>
    <?php endif; ?>
</body>
</html>
