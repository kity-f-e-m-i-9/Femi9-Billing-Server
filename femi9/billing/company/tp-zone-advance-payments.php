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
$filter_status  = $_GET['status'] ?? '';
$filter_type    = $_GET['type_filter'] ?? '';

$allowed_statuses = ['active', 'partially_adjusted', 'fully_adjusted', ''];
if (!in_array($filter_status, $allowed_statuses, true)) $filter_status = '';
if (!in_array($filter_type, ['napkin', 'diaper'], true)) $filter_type = '';

$company_profiles = $db_conn->query("SELECT id, gname FROM company_godown WHERE gname LIKE '%Femi%' AND " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);

$stats = null;
$selectedZoneName = '';
$zoneTps = [];
$detailRows = [];
if ($selectedZoneId > 0) {
    $stmt = $db_conn->prepare("SELECT name FROM partner_zones WHERE id = ?");
    $stmt->bind_param('i', $selectedZoneId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $selectedZoneName = $row['name'];
        $tpIds = getZoneTpIds($db_conn, $selectedZoneId, true);
        if (!empty($tpIds)) {
            $placeholders0 = implode(',', array_fill(0, count($tpIds), '?'));
            $stmtT = $db_conn->prepare("SELECT id, tp_id, name FROM territory_partners WHERE id IN ($placeholders0) ORDER BY name");
            $typesT = str_repeat('i', count($tpIds));
            $stmtT->bind_param($typesT, ...$tpIds);
            $stmtT->execute();
            $zoneTps = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtT->close();
        }

        $stats = ['count' => 0, 'amount' => 0.0, 'balance' => 0.0, 'adjusted' => 0.0];
        $stats_by_type = ['napkin' => ['count' => 0, 'amount' => 0.0, 'balance' => 0.0, 'adjusted' => 0.0], 'diaper' => ['count' => 0, 'amount' => 0.0, 'balance' => 0.0, 'adjusted' => 0.0]];

        if (!empty($tpIds)) {
            $where = ["tap.deleted_at IS NULL", "tap.territory_partner_id IN (" . implode(',', array_map('intval', $tpIds)) . ")", "tap.payment_date BETWEEN ? AND ?"];
            $params = [$filter_from, $filter_to];
            $types = "ss";

            if ($filter_tp > 0 && in_array($filter_tp, $tpIds, true)) {
                $where[] = "tap.territory_partner_id = ?";
                $params[] = $filter_tp;
                $types .= "i";
            }
            if ($filter_company > 0) {
                $where[] = "tap.company_id = ?";
                $params[] = $filter_company;
                $types .= "i";
            }
            if ($filter_status !== '') {
                $where[] = "tap.status = ?";
                $params[] = $filter_status;
                $types .= "s";
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
            }
        }
    } else {
        $selectedZoneId = 0;
    }
}

// Carries every current filter forward — used by the "View Full List" link
// so nothing gets silently dropped when jumping to the detailed table.
function zoneQS(array $overrides = []): string {
    global $selectedZoneId, $filter_from, $filter_to, $filter_tp, $filter_company, $filter_status, $filter_type;
    $qs = array_merge([
        'zone_id' => $selectedZoneId, 'from_date' => $filter_from, 'to_date' => $filter_to,
        'tp_id' => $filter_tp ?: '', 'company_id' => $filter_company ?: '', 'status' => $filter_status, 'type_filter' => $filter_type,
    ], $overrides);
    return http_build_query(array_filter($qs, fn($v) => $v !== '' && $v !== 0));
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
                                    <div class="stats-card">
                                        <h3><?php echo $stats['count']; ?></h3>
                                        <p>Total Payments</p>
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
                                    <div class="stats-card">
                                        <h3>&#8377;<?php echo inr_format($stats['balance'], 2); ?></h3>
                                        <p>Total Balance</p>
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

                            <!-- Filters -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="filter-card">
                                        <form method="GET" action="">
                                            <input type="hidden" name="zone_id" value="<?=(int)$selectedZoneId?>">
                                            <div class="row g-2 align-items-end">
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
                                                    <select name="status" class="form-control">
                                                        <option value="">All Status</option>
                                                        <option value="active" <?=$filter_status === 'active' ? 'selected' : ''?>>Active</option>
                                                        <option value="partially_adjusted" <?=$filter_status === 'partially_adjusted' ? 'selected' : ''?>>Partially Adjusted</option>
                                                        <option value="fully_adjusted" <?=$filter_status === 'fully_adjusted' ? 'selected' : ''?>>Fully Adjusted</option>
                                                    </select>
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
                                                                <?php if (($dr['status'] ?? '') === 'active'): ?>
                                                                <span class="status-active">Active</span>
                                                                <?php elseif (($dr['status'] ?? '') === 'partially_adjusted'): ?>
                                                                <span class="status-partially">Partially Adjusted</span>
                                                                <?php else: ?>
                                                                <span class="status-fully">Fully Adjusted</span>
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

        $('#zoneAdvPayTable').DataTable({
            dom: '<"row"<"col-sm-6"l><"col-sm-6"f>><"row"<"col-sm-12"B>><"row"<"col-sm-12"tr>><"row"<"col-sm-5"i><"col-sm-7"p>>',
            buttons: [
                { extend: 'excel', text: '<i class="material-icons" style="vertical-align:middle">download</i> Excel', className: 'btn btn-success' },
                { extend: 'print', text: '<i class="material-icons" style="vertical-align:middle">print</i> Print', className: 'btn btn-info' }
            ]
        });
    });
    </script>
    <?php endif; ?>
</body>
</html>
