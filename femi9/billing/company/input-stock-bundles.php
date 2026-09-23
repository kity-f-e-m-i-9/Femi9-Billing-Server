<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");
date_default_timezone_set("Asia/Kolkata");

// Dedicated to the neksomo login (admin retained for oversight/support),
// matching the Convert Pieces<->Packs page this feature feeds into —
// unlike Internal Stock Transfer/Manage Godowns, raw material bundle
// tracking is a Neksomo production concern, not a finance one.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Raw Neksomo placeholder products only — the opposite scope of the
// regular Add Input Stock page, which deliberately EXCLUDES these.
$rawProducts = $db_conn->query(
    "SELECT id, productName FROM products WHERE temp_id LIKE 'NKS-%' ORDER BY productName ASC"
)->fetch_all(MYSQLI_ASSOC);

$godowns = $db_conn->query(
    "SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname ASC"
)->fetch_all(MYSQLI_ASSOC);

$warehouses = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Input Stock — Raw Bundles : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --ata-tp-1: #667eea;
            --ata-tp-2: #764ba2;
        }
        .ata-page-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
        .ata-page-head h1 { font-size:20px; font-weight:600; color:#1f2937; margin:0; display:flex; align-items:center; }
        .ata-intro { color:#6b7280; font-size:13.5px; line-height:1.55; margin-bottom:16px; }
        .ata-card { border:1px solid #eef0f3; border-radius:14px; box-shadow:0 1px 3px rgba(16,24,40,.04); }

        .ata-section-head { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; padding:14px 20px; border-bottom:1px solid #eef0f3; }
        .ata-section-title { display:flex; align-items:center; gap:8px; font-size:14.5px; font-weight:600; color:#1f2937; }
        .ata-section-title i { font-size:19px; color:var(--ata-tp-1); }

        .ata-nav-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
        .ata-nav-tab { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:9px; font-size:13.5px; font-weight:500; text-decoration:none; transition:filter .15s, background .15s; }
        .ata-nav-tab i { font-size:17px; }
        .ata-nav-tab.active { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); color:#fff; box-shadow:0 2px 8px rgba(102,126,234,.3); }
        .ata-nav-tab:not(.active) { background:#f3f4f6; color:#4b5563; }
        .ata-nav-tab:not(.active):hover { background:#e5e7eb; color:#1f2937; }

        .ata-hint-box { background:#f8f9fc; border:1px solid #eaecf5; border-radius:10px; padding:12px 16px; font-size:12.5px; color:#6b7280; margin-bottom:20px; display:flex; gap:10px; align-items:flex-start; }
        .ata-hint-box i { color:var(--ata-tp-1); font-size:18px; flex-shrink:0; margin-top:1px; }

        .ata-form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; padding:20px; }
        .ata-field { grid-column: span 1; }
        .ata-field.ata-field-full { grid-column: 1 / -1; }
        .ata-field label { display:block; font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.02em; margin-bottom:4px; }
        .ata-field select, .ata-field input { border:1px solid #dde1ea; border-radius:8px; height:40px; font-size:13.5px; width:100%; padding:6px 10px; }
        .ata-field select:focus, .ata-field input:focus { border-color: var(--ata-tp-1); box-shadow: 0 0 0 3px rgba(102,126,234,.15); outline:none; }

        .ata-btn { display:inline-flex; align-items:center; gap:6px; border:none; border-radius:9px; color:#fff; font-size:13px; font-weight:500; padding:8px 14px; cursor:pointer; transition:filter .15s, transform .1s; }
        .ata-btn:active { transform:translateY(1px); }
        .ata-btn:hover { filter:brightness(1.06); color:#fff; }
        .ata-btn-tp { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); box-shadow:0 2px 6px rgba(102,126,234,.3); }
        .ata-btn-submit { font-size:14px; padding:10px 22px; }
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
                    <div class="ata-page-head">
                        <h1><i class="material-icons-outlined" style="font-size:22px;vertical-align:middle;margin-right:8px;color:var(--ata-tp-1);">inventory_2</i>Raw Material Bundles</h1>
                    </div>
                    <p class="ata-intro">Record raw material received as physical bundles and track each one's true piece count as it's used up.</p>

                    <div class="ata-nav-tabs">
                        <a href="input-stock-bundles.php" class="ata-nav-tab active"><i class="material-icons-outlined">add_box</i> Input Stock</a>
                        <a href="raw-material-bundles-manage.php" class="ata-nav-tab"><i class="material-icons-outlined">list_alt</i> Manage Bundles</a>
                        <a href="neksomo-piece-pack-convert.php" class="ata-nav-tab"><i class="material-icons-outlined">sync_alt</i> Convert Pieces &harr; Packs</a>
                    </div>

                    <?php if (isset($_SESSION['errorMessage'])): $flashErr = htmlspecialchars($_SESSION['errorMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['errorMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"error",title:"Error",text:"<?= $flashErr ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['sucMessage'])): $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['sucMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"success",title:"Success",text:"<?= $flashMsg ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-9 col-xl-8">
                            <div class="ata-card card">
                                <div class="ata-section-head">
                                    <div class="ata-section-title"><i class="material-icons-outlined">add_box</i> Record New Bundles</div>
                                </div>
                                <?php if (empty($rawProducts)): ?>
                                    <div style="padding:20px;">
                                        <div class="alert alert-warning mb-0">No raw material products found (products with a temp_id starting "NKS-"). Nothing to record bundles against yet.</div>
                                    </div>
                                <?php else: ?>
                                <div style="padding:20px 20px 0;">
                                    <div class="ata-hint-box">
                                        <i class="material-icons-outlined">info</i>
                                        <div>The actual piece count per bundle is rarely exact — some run short, some have extra. The system tracks each bundle individually and only reveals the true count once it's used up in Convert Pieces&harr;Packs and closed.</div>
                                    </div>
                                </div>
                                <form id="rmbForm" action="input-stock-bundles-action.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                                    <div class="ata-form-grid">
                                        <div class="ata-field">
                                            <label>Raw Product <span style="color:#ef4444;">*</span></label>
                                            <select required name="raw_product_id">
                                                <option value="" hidden>Select Raw Product</option>
                                                <?php foreach ($rawProducts as $p): ?>
                                                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['productName'], ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="ata-field">
                                            <label>Company Profile <span style="color:#ef4444;">*</span></label>
                                            <select required name="company_godown_id">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($godowns as $g): ?>
                                                <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['gname'], ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="ata-field">
                                            <label>Godown (physical)</label>
                                            <select name="warehouse_id">
                                                <option value="">— Not tracked —</option>
                                                <?php foreach ($warehouses as $wh): ?>
                                                <option value="<?= (int)$wh['id'] ?>"><?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="ata-field">
                                            <label>Nominal Pieces / Bundle <span style="color:#ef4444;">*</span></label>
                                            <input type="number" required min="1" name="nominal_pieces" id="nominalPieces" placeholder="e.g. 1000">
                                        </div>
                                        <div class="ata-field">
                                            <label>Number of Bundles <span style="color:#ef4444;">*</span></label>
                                            <input type="number" required min="1" name="bundle_count" id="bundleCount" placeholder="e.g. 5">
                                        </div>
                                        <div class="ata-field ata-field-full">
                                            <div style="background:#eef2ff;border-radius:8px;padding:10px 14px;font-size:13px;color:#4338ca;font-weight:500;" id="totalPreview" hidden>
                                                <i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;margin-right:4px;">calculate</i>
                                                Total: <span id="totalPreviewValue">0</span> pieces credited across <span id="totalPreviewBundles">0</span> bundle(s)
                                            </div>
                                        </div>
                                    </div>

                                    <div style="padding:0 20px 20px;">
                                        <button type="submit" class="ata-btn ata-btn-tp ata-btn-submit">
                                            <i class="material-icons" style="font-size:17px;">add_box</i> Record Bundles
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>
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
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
function updateTotalPreview() {
    var nominal = parseInt(document.getElementById('nominalPieces').value, 10) || 0;
    var count = parseInt(document.getElementById('bundleCount').value, 10) || 0;
    var preview = document.getElementById('totalPreview');
    if (nominal > 0 && count > 0) {
        document.getElementById('totalPreviewValue').textContent = (nominal * count).toLocaleString('en-IN');
        document.getElementById('totalPreviewBundles').textContent = count;
        preview.hidden = false;
    } else {
        preview.hidden = true;
    }
}
document.getElementById('nominalPieces').addEventListener('input', updateTotalPreview);
document.getElementById('bundleCount').addEventListener('input', updateTotalPreview);
</script>
</body>
</html>
