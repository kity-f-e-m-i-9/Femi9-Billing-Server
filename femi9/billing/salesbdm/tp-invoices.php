<?php
// "TP Invoices" — read-only list of invoices raised for this Sales BDM's own
// assigned-district TPs, mirroring company/manage-tp-invoices.php's look but
// scoped down and with only a Print action (view-only for a BDM — no Edit,
// Shipping Label, WhatsApp share, Credit Note, or Delete, per explicit
// request: "print button matthum thaan venum").
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

$tpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID);
$hasTps = !empty($tpIds);
$tpIdListSql = $hasTps ? implode(',', array_map('intval', $tpIds)) : '0';

$tpNameMap = [];
if ($hasTps) {
    $tpRows = $db_conn->query("SELECT id, name, tp_id FROM territory_partners WHERE id IN ($tpIdListSql) ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
    foreach ($tpRows as $r) { $tpNameMap[(int)$r['id']] = $r; }
}

$filter_tp_id     = (int)($_GET['tp_id'] ?? 0);
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to'] ?? '');
$filter_type      = $_GET['type_filter'] ?? '';
if (!in_array($filter_type, ['napkin', 'diaper'], true)) $filter_type = '';

$invoices = [];
if ($hasTps) {
    $where  = ["tpi.territory_partner_id IN ($tpIdListSql)"];
    $params = [];
    $types  = '';

    if ($filter_tp_id > 0 && isset($tpNameMap[$filter_tp_id])) {
        $where[]  = "tpi.territory_partner_id = ?";
        $params[] = $filter_tp_id;
        $types   .= 'i';
    }
    if ($filter_date_from !== '') {
        $where[]  = "tpi.invoice_date >= ?";
        $params[] = $filter_date_from;
        $types   .= 's';
    }
    if ($filter_date_to !== '') {
        $where[]  = "tpi.invoice_date <= ?";
        $params[] = $filter_date_to;
        $types   .= 's';
    }
    if ($filter_type !== '') {
        $where[]  = "tpi.product_type = ?";
        $params[] = $filter_type;
        $types   .= 's';
    }
    $where_sql = 'WHERE ' . implode(' AND ', $where);

    $sql = "
        SELECT tpi.id, tpi.invoice_number, tpi.invoice_date, tpi.total_amount, tpi.product_type,
               tp.name AS tp_name, tp.tp_id AS tp_code,
               COALESCE(cp_src.name, gd.gname, pln.name) AS source_location
        FROM tp_invoices tpi
        JOIN territory_partners tp            ON tp.id = tpi.territory_partner_id
        LEFT JOIN partner_location_nodes pln  ON pln.id = tpi.source_location_id
        LEFT JOIN channel_partners cp_src     ON cp_src.id = tpi.source_cp_id
        LEFT JOIN company_godown gd           ON gd.id = tpi.source_godown_id
        $where_sql
        ORDER BY tpi.created_at DESC
    ";
    if ($params) {
        $stmt = $db_conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $res = $db_conn->query($sql);
        $invoices = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}

$total_count  = count($invoices);
$total_amount = array_sum(array_column($invoices, 'total_amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Invoices : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        :root {
            --surface-1: #ffffff; --page-plane: #f7f7f6; --text-primary: #0b0b0b;
            --text-secondary: #52514e; --text-muted: #898781; --gridline: #e1e0d9; --border: rgba(11,11,11,0.10);
            --blue: #2a78d6; --blue-tint: #eaf2fc;
        }
        body { background: var(--page-plane); }
        .ti-card { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); border:none; margin-bottom:20px; }
        .mis-filter { background: var(--surface-1); border: 1px solid var(--border); border-radius: 10px; padding: 14px 18px; margin-bottom: 14px; }
        table.dataTable thead th { background:#f8f9fa; font-weight:600; }
        .action-btn.print { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; color:#0369a1; text-decoration:none; }
        .action-btn.print:hover { background:#e0f2fe; }
        @media (max-width: 700px) {
            .mis-filter form { flex-direction: column; align-items: stretch !important; }
            .mis-filter form > div { width: 100% !important; }
            .mis-filter select, .mis-filter input { width: 100% !important; }
        }
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
                                <h1><table class="headertble"><tr><td>TP Invoices — Your Territory Partners</td></tr></table></h1>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($_companyBridgeView)): ?>
                        <div class="alert alert-info" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
                            <span><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">visibility</i> Viewing <b><?php echo htmlspecialchars($result_LoGuserDtails['bdm_name'] ?? ''); ?>'s</b> TP Invoices (read-only).</span>
                        </div>
                    <?php endif; ?>

                    <?php if (!$hasTps): ?>
                        <div class="alert alert-info">No Territory Partners are assigned to your districts yet.</div>
                    <?php else: ?>

                    <div class="mis-filter">
                        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Territory Partner</label>
                                <select name="tp_id" class="form-control form-control-sm" style="width:200px;" onchange="this.form.submit()">
                                    <option value="0">All Territory Partners</option>
                                    <?php foreach ($tpNameMap as $tid => $t): ?>
                                        <option value="<?php echo $tid; ?>" <?php echo $filter_tp_id===$tid?'selected':''; ?>><?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['tp_id']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From</label>
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>" class="form-control form-control-sm" style="width:145px;">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To</label>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>" class="form-control form-control-sm" style="width:145px;">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Type</label>
                                <select name="type_filter" class="form-control form-control-sm" style="width:160px;" onchange="this.form.submit()">
                                    <option value="">Napkin + Diaper</option>
                                    <option value="napkin" <?php echo $filter_type==='napkin'?'selected':''; ?>>Napkin only</option>
                                    <option value="diaper" <?php echo $filter_type==='diaper'?'selected':''; ?>>Lumi Diaper only</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            </div>
                            <?php if ($filter_tp_id || $filter_date_from || $filter_date_to || $filter_type): ?>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">&nbsp;</label>
                                <a href="tp-invoices.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                            </div>
                            <?php endif; ?>
                        </form>
                        <div style="font-size:12px;color:#888;margin-top:7px;">
                            <?php echo $total_count; ?> invoice(s) &nbsp;|&nbsp; Total &#8377;<?php echo inr_format($total_amount, 2); ?>
                        </div>
                    </div>

                    <div class="card ti-card">
                        <div class="card-body">
                            <?php if (empty($invoices)): ?>
                                <div class="alert alert-info mb-0">No TP invoices found for this filter.</div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="tiTable" class="table table-hover table-sm" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>S.No</th>
                                            <th>Invoice #</th>
                                            <th>Territory Partner</th>
                                            <th>Source</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Print</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $sr = 1; foreach ($invoices as $inv):
                                            $enc = base64_encode($inv['id']);
                                            $type = tpResolveProductType($inv['product_type'] ?? null);
                                            [$tBg, $tFg] = tpProductTypeBadgeColors($type);
                                        ?>
                                        <tr>
                                            <td><?php echo $sr++; ?></td>
                                            <td>
                                                <code style="font-size:12px;background:#f3f4f6;padding:2px 7px;border-radius:4px;"><?php echo htmlspecialchars($inv['invoice_number']); ?></code>
                                                <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:9px;background:<?php echo $tBg; ?>;color:<?php echo $tFg; ?>;"><?php echo htmlspecialchars(tpProductTypeLabel($type)); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($inv['tp_name']); ?> <small style="color:#9ca3af;">(<?php echo htmlspecialchars($inv['tp_code']); ?>)</small></td>
                                            <td><?php echo htmlspecialchars($inv['source_location'] ?? '—'); ?></td>
                                            <td><?php echo date('d M Y', strtotime($inv['invoice_date'])); ?></td>
                                            <td>&#8377;<?php echo inr_format($inv['total_amount'], 2); ?></td>
                                            <td>
                                                <a href="tp-invoice-print.php?id=<?php echo $enc; ?>" class="action-btn print" title="Print Invoice">
                                                    <i class="material-icons-outlined" style="font-size:19px;">print</i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
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
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="../../assets/js/main.min.js"></script>
<script>
$(function(){
    $('#tiTable').DataTable({
        order: [],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100]
    });
});
</script>
</body>
</html>
