<?php
include("checksession.php");
include("config.php");
require_once(__DIR__ . "/../company/include/StockViewerData.php");

$profiles   = get_stock_viewer_company_profiles($db_conn);
$warehouses = get_stock_viewer_warehouses($db_conn);
$rows       = get_stock_viewer_rows($db_conn);

// [profile_id][warehouse key] => rows
$buckets = [];
$profileTotals = [];
$grandTotal = 0;
foreach ($rows as $row) {
    $pk = (int)$row['company_godown_id'];
    $wk = $row['warehouse_id'] !== null ? (string)$row['warehouse_id'] : '';
    $buckets[$pk][$wk][] = $row;
    $profileTotals[$pk] = ($profileTotals[$pk] ?? 0) + (int)$row['closing_qty'];
    $grandTotal += (int)$row['closing_qty'];
}
$activeProfiles = array_values(array_filter($profiles, function ($p) use ($buckets) { return !empty($buckets[(int)$p['id']]); }));
function wh_label($warehouses, $key) {
    if ($key === '') return 'Unassigned';
    foreach ($warehouses as $w) { if ((string)$w['id'] === (string)$key) return $w['code'] . ($w['name'] ? ' - ' . $w['name'] : ''); }
    return 'Godown #' . $key;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock by Company Profile : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <?php include("include/stock-style.php"); ?>
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

                        <div class="page-description">
                            <h1>Stock by Company Profile</h1>
                            <span>Every company profile, split by godown</span>
                        </div>

                        <div class="row">
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card wh-card wh-kpi"><div class="card-body">
                                    <div class="wh-kpi-icon" style="background:#e8eaf6;color:#3f51b5;"><i class="material-icons-outlined">store</i></div>
                                    <div><p class="wh-kpi-label">Company Profiles</p><p class="wh-kpi-value"><?php echo count($activeProfiles); ?></p></div>
                                </div></div>
                            </div>
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card wh-card wh-kpi"><div class="card-body">
                                    <div class="wh-kpi-icon" style="background:#e8eaf6;color:#3f51b5;"><i class="material-icons-outlined">stacked_bar_chart</i></div>
                                    <div><p class="wh-kpi-label">Total Closing Qty</p><p class="wh-kpi-value"><?php echo inr_format($grandTotal, 0); ?></p></div>
                                </div></div>
                            </div>
                            <div class="col-xl-4 col-md-12 mb-4">
                                <div class="card wh-card wh-kpi"><div class="card-body">
                                    <div class="wh-kpi-icon" style="background:#e8eaf6;color:#5c6bc0;"><i class="material-icons-outlined">warehouse</i></div>
                                    <div><p class="wh-kpi-label">Godowns</p><p class="wh-kpi-value"><?php echo count($warehouses); ?></p></div>
                                </div></div>
                            </div>
                        </div>

                        <div class="card wh-card mb-4"><div class="card-body">
                            <div class="row g-3">
                                <div class="col-lg-4">
                                    <label class="form-label" style="font-size:12px;color:#64748b;" for="pf">Search product</label>
                                    <input type="text" id="pf" class="form-control" placeholder="Type a product name…" autocomplete="off">
                                </div>
                                <div class="col-lg-4">
                                    <label class="form-label" style="font-size:12px;color:#64748b;">Company profile</label>
                                    <div>
                                    <?php foreach ($activeProfiles as $p): ?>
                                        <label class="wh-check"><input type="checkbox" class="f-profile" value="<?php echo (int)$p['id']; ?>" checked> <?php echo htmlspecialchars($p['gname']); ?></label>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <label class="form-label" style="font-size:12px;color:#64748b;">Godown</label>
                                    <div>
                                        <label class="wh-check"><input type="checkbox" class="f-wh" value="" checked> Unassigned</label>
                                    <?php foreach ($warehouses as $w): ?>
                                        <label class="wh-check"><input type="checkbox" class="f-wh" value="<?php echo (int)$w['id']; ?>" checked> <?php echo htmlspecialchars($w['code']); ?></label>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div></div>

                        <?php if (empty($activeProfiles)): ?>
                            <div class="card wh-card"><div class="card-body"><div class="wh-empty"><i class="material-icons-outlined">inventory_2</i>No stock recorded yet.</div></div></div>
                        <?php endif; ?>

                        <?php foreach ($activeProfiles as $p): $pid = (int)$p['id']; ?>
                        <div class="card wh-card mb-4 profile-card" data-profile="<?php echo $pid; ?>">
                            <div class="card-body">
                                <div class="wh-profile-head">
                                    <h5 class="mb-0"><i class="material-icons-outlined" style="vertical-align:middle;margin-right:6px;color:#3f51b5;">store</i><?php echo htmlspecialchars($p['gname']); ?></h5>
                                    <span class="text-muted" style="font-size:13px;">Total: <b style="color:#0f172a;"><?php echo inr_format($profileTotals[$pid], 0); ?></b></span>
                                </div>
                                <?php foreach ($buckets[$pid] as $wk => $list):
                                    $wk = (string)$wk;
                                    $sub = 0; $max = 0;
                                    foreach ($list as $r) { $sub += (int)$r['closing_qty']; $max = max($max, (int)$r['closing_qty']); }
                                ?>
                                <div class="wh-wh-block" data-wh="<?php echo htmlspecialchars($wk); ?>">
                                    <div class="wh-sub"><?php echo htmlspecialchars(wh_label($warehouses, $wk)); ?> &middot; <?php echo inr_format($sub, 0); ?> units</div>
                                    <div class="table-responsive">
                                        <table class="table table-hover wh-table">
                                            <thead><tr><th>Product</th><th class="text-end" style="width:140px;">Closing Qty</th><th style="width:180px;">Level</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($list as $r):
                                                $q = (int)$r['closing_qty'];
                                                $pct = $max > 0 ? max(2, min(100, round(abs($q) / $max * 100))) : 0;
                                                $low = $q < STOCK_VIEWER_LOW_STOCK_THRESHOLD;
                                            ?>
                                                <tr class="prow" data-name="<?php echo htmlspecialchars(strtolower($r['productName'])); ?>">
                                                    <td><b><?php echo htmlspecialchars($r['productName']); ?></b><?php if ($low): ?> <span class="wh-badge-low">Low</span><?php endif; ?></td>
                                                    <td class="text-end"><b><?php echo inr_format($q, 0); ?></b></td>
                                                    <td><div class="wh-bar <?php echo $low ? 'low' : ''; ?>"><span style="width:<?php echo $pct; ?>%"></span></div></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="wh-empty" id="noMatch" style="display:none;">Nothing matches the current filters.</div>
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
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script>
    (function () {
        function vals(sel) { return Array.prototype.map.call(document.querySelectorAll(sel + ':checked'), function (c) { return c.value; }); }
        function apply() {
            var q = document.getElementById('pf').value.trim().toLowerCase();
            var ps = vals('.f-profile'), ws = vals('.f-wh'), anyCard = false;
            document.querySelectorAll('.profile-card').forEach(function (card) {
                var cardShown = false;
                if (ps.indexOf(card.getAttribute('data-profile')) !== -1) {
                    card.querySelectorAll('.wh-wh-block').forEach(function (blk) {
                        var shown = false;
                        if (ws.indexOf(blk.getAttribute('data-wh')) !== -1) {
                            blk.querySelectorAll('.prow').forEach(function (r) {
                                var ok = !q || r.getAttribute('data-name').indexOf(q) !== -1;
                                r.style.display = ok ? '' : 'none';
                                if (ok) shown = true;
                            });
                        }
                        blk.style.display = shown ? '' : 'none';
                        if (shown) cardShown = true;
                    });
                }
                card.style.display = cardShown ? '' : 'none';
                if (cardShown) anyCard = true;
            });
            document.getElementById('noMatch').style.display = anyCard ? 'none' : 'block';
        }
        document.getElementById('pf').addEventListener('input', apply);
        document.querySelectorAll('.f-profile, .f-wh').forEach(function (c) { c.addEventListener('change', apply); });
    })();
    </script>
</body>
</html>
