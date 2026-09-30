<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/CartonBoxes.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support),
// matching the other Raw Bundles / Covers pages this feature sits
// alongside. Deliberately standalone — never touched by Convert Pieces
// <-> Packs (see include/CartonBoxes.php's file header).
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['receive', 'damage', 'used'], true)) {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
        header("Location: manage-cartons.php");
        exit;
    }

    $cartonTypeId    = filter_var($_POST['carton_type_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $companyGodownId = filter_var($_POST['company_godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $warehouseId     = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
    $qty             = filter_var($_POST['qty'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $actingUser      = $_SESSION['LOGIN_USER'] ?? 'system';

    // Lets the operator backdate an entry (e.g. entering it the next
    // day) instead of every entry always being timestamped "now" — same
    // pattern as neksomo-piece-pack-convert-action.php's conversion_date.
    // Re-validated here since the client-side `max` on the date input
    // alone is never trusted.
    $rawEntryDate = trim($_POST['entry_date'] ?? '');
    $entryDateObj = \DateTime::createFromFormat('Y-m-d', $rawEntryDate);
    if (!$entryDateObj || $entryDateObj->format('Y-m-d') !== $rawEntryDate) {
        $_SESSION['errorMessage'] = "Please select a valid date.";
        header("Location: manage-cartons.php");
        exit;
    }
    if ($rawEntryDate > date('Y-m-d')) {
        $_SESSION['errorMessage'] = "Date cannot be in the future.";
        header("Location: manage-cartons.php");
        exit;
    }
    $entryDate = $rawEntryDate;

    if (!$cartonTypeId || !$companyGodownId || $qty <= 0) {
        $_SESSION['errorMessage'] = "Please fill in every required field.";
        header("Location: manage-cartons.php");
        exit;
    }
    if (!is_godown_allowed($db_conn, $companyGodownId)) {
        $_SESSION['errorMessage'] = "You are not authorized to use this company profile.";
        header("Location: manage-cartons.php");
        exit;
    }

    try {
        if ($_POST['action'] === 'receive') {
            $refId = 'CARTONIN' . date('YmdHis') . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
            $note = trim((string) ($_POST['note'] ?? ''));
            $note = $note !== '' ? mb_substr($note, 0, 255) : null;
            receive_cartons($db_conn, $cartonTypeId, $companyGodownId, $warehouseId, $qty, $note, $entryDate, $refId, $actingUser);
            $_SESSION['sucMessage'] = "$qty carton(s) received.";
        } elseif ($_POST['action'] === 'damage') {
            $reasonId = filter_var($_POST['reason_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
            $note = trim((string) ($_POST['note'] ?? ''));
            $note = $note !== '' ? mb_substr($note, 0, 255) : null;
            if (!$reasonId) {
                $_SESSION['errorMessage'] = "Please select a reason for the damage.";
                header("Location: manage-cartons.php");
                exit;
            }
            record_damaged_cartons($db_conn, $cartonTypeId, $companyGodownId, $warehouseId, $qty, $reasonId, $note, $entryDate, $actingUser);
            $_SESSION['sucMessage'] = "$qty damaged carton(s) recorded.";
        } elseif ($_POST['action'] === 'used') {
            $note = trim((string) ($_POST['note'] ?? ''));
            $note = $note !== '' ? mb_substr($note, 0, 255) : null;
            record_used_cartons($db_conn, $cartonTypeId, $companyGodownId, $warehouseId, $qty, $note, $entryDate, $actingUser);
            $_SESSION['sucMessage'] = "$qty carton(s) marked used.";
        }
    } catch (StockException $e) {
        $_SESSION['errorMessage'] = $e->getMessage();
    } catch (\Throwable $e) {
        error_log('[manage-cartons] ' . $e->getMessage());
        $_SESSION['errorMessage'] = "An error occurred. Please try again.";
    }

    header("Location: manage-cartons.php");
    exit;
}

$cartonTypes = get_active_carton_types($db_conn);
$damageReasons = get_active_carton_damage_reasons($db_conn);

$godowns = $db_conn->query(
    "SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname ASC"
)->fetch_all(MYSQLI_ASSOC);

$warehouses = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC")->fetch_all(MYSQLI_ASSOC);

// Optional date-range filter on the balance table below — when set,
// received/damaged are recomputed from the adjustments log within that
// range (see get_all_carton_boxes()); Balance always stays the live
// current total regardless of the filter.
$filterFrom = trim($_GET['from'] ?? '');
$filterTo   = trim($_GET['to'] ?? '');
$filterFrom = (\DateTime::createFromFormat('Y-m-d', $filterFrom) && $filterFrom !== '') ? $filterFrom : null;
$filterTo   = (\DateTime::createFromFormat('Y-m-d', $filterTo) && $filterTo !== '') ? $filterTo : null;

$cartons = get_all_carton_boxes($db_conn, $filterFrom, $filterTo);
$godownNamesById = [];
foreach ($db_conn->query("SELECT id, gname FROM company_godown")->fetch_all(MYSQLI_ASSOC) as $g) {
    $godownNamesById[(int) $g['id']] = $g['gname'];
}
$warehouseCodesById = [];
foreach ($warehouses as $wh) {
    $warehouseCodesById[(int) $wh['id']] = $wh['code'];
}

$totalCartons = count($cartons);
$lowCount = 0;
foreach ($cartons as $c) {
    if ($c['balance'] <= 0) $lowCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Cartons : <?php echo $business_name; ?></title>

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
        .ata-field select, .ata-field input, .ata-field textarea { border:1px solid #dde1ea; border-radius:8px; height:40px; font-size:13.5px; width:100%; padding:6px 10px; }
        .ata-field select:focus, .ata-field input:focus, .ata-field textarea:focus { border-color: var(--ata-tp-1); box-shadow: 0 0 0 3px rgba(102,126,234,.15); outline:none; }

        .ata-btn { display:inline-flex; align-items:center; gap:6px; border:none; border-radius:9px; color:#fff; font-size:13px; font-weight:500; padding:8px 14px; cursor:pointer; transition:filter .15s, transform .1s; }
        .ata-btn:active { transform:translateY(1px); }
        .ata-btn:hover { filter:brightness(1.06); color:#fff; }
        .ata-btn-tp { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); box-shadow:0 2px 6px rgba(102,126,234,.3); }
        .ata-btn-danger { background:#ef4444; box-shadow:0 2px 6px rgba(239,68,68,.3); }
        .ata-btn-submit { font-size:14px; padding:10px 22px; }
        .ata-btn-sm { font-size:12px; padding:6px 12px; }
        .ata-btn-link { background:none; border:none; color:var(--ata-tp-1); font-size:12.5px; font-weight:500; cursor:pointer; padding:0; }
        .ata-btn-link:hover { text-decoration:underline; }

        .ata-summary { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; }
        .ata-stat { flex:1 1 140px; border:1px solid #eef0f3; border-radius:12px; padding:12px 16px; background:#fafbfc; }
        .ata-stat .num { font-size:20px; font-weight:700; color:#1f2937; line-height:1.2; }
        .ata-stat .lbl { font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.03em; margin-top:2px; }
        .ata-stat.low .num { color:#991b1b; }

        table.ata-carton-table { width:100%; border-collapse:collapse; font-size:13px; }
        table.ata-carton-table th { text-align:left; font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.02em; padding:10px 16px; border-bottom:1px solid #eef0f3; }
        table.ata-carton-table td { padding:12px 16px; border-bottom:1px solid #f3f4f6; color:#374151; }
        table.ata-carton-table tr:last-child td { border-bottom:none; }
        .badge-balance-ok { background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }
        .badge-balance-low { background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }

        .ata-manage-list-row { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 0; border-bottom:1px solid #f3f4f6; }
        .ata-manage-list-row:last-child { border-bottom:none; }
        .ata-manage-list-actions { display:flex; gap:10px; }
        .ata-manage-list-actions i { font-size:16px; cursor:pointer; color:#9ca3af; }
        .ata-manage-list-actions i:hover { color:#4b5563; }
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
                        <h1><i class="material-icons-outlined" style="font-size:22px;vertical-align:middle;margin-right:8px;color:var(--ata-tp-1);">inventory_2</i>Manage Cartons</h1>
                    </div>
                    <p class="ata-intro">Standalone carton box stock — received and damaged only. Not linked to Convert Pieces &harr; Packs.</p>

                    <div class="ata-nav-tabs">
                        <a href="input-stock-bundles.php" class="ata-nav-tab"><i class="material-icons-outlined">add_box</i> Input Stock</a>
                        <a href="raw-material-bundles-manage.php" class="ata-nav-tab"><i class="material-icons-outlined">list_alt</i> Manage Bundles</a>
                        <a href="neksomo-piece-pack-convert.php" class="ata-nav-tab"><i class="material-icons-outlined">sync_alt</i> Convert Pieces &harr; Packs</a>
                        <a href="manage-piece-pack-conversions.php" class="ata-nav-tab"><i class="material-icons-outlined">history</i> Manage Conversions</a>
                        <a href="raw-material-bundles-report.php" class="ata-nav-tab"><i class="material-icons-outlined">assessment</i> Bundle Report</a>
                        <a href="manage-covers.php" class="ata-nav-tab"><i class="material-icons-outlined">layers</i> Covers</a>
                        <a href="manage-cartons.php" class="ata-nav-tab active"><i class="material-icons-outlined">inbox</i> Cartons</a>
                    </div>

                    <?php if (isset($_SESSION['errorMessage'])): $flashErr = htmlspecialchars($_SESSION['errorMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['errorMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"error",title:"Error",text:"<?= $flashErr ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['sucMessage'])): $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['sucMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"success",title:"Success",text:"<?= $flashMsg ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>

                    <?php if ($totalCartons > 0): ?>
                    <div class="ata-summary">
                        <div class="ata-stat">
                            <div class="num"><?php echo $totalCartons; ?></div>
                            <div class="lbl">Carton Balances Tracked</div>
                        </div>
                        <div class="ata-stat low">
                            <div class="num"><?php echo $lowCount; ?></div>
                            <div class="lbl">Out Of Stock</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-12">
                            <div class="ata-card card mb-4">
                                <div class="ata-section-head">
                                    <div class="ata-section-title"><i class="material-icons-outlined">add_box</i> Receive Cartons</div>
                                    <button type="button" class="ata-btn-link" id="manageCartonTypesBtn">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">settings</i> Manage Carton Types
                                    </button>
                                </div>
                                <?php if (empty($cartonTypes)): ?>
                                <div style="padding:20px;">
                                    <div class="alert alert-warning mb-0">No carton types defined yet. Click "Manage Carton Types" above to add one (e.g. "Large Box", "Small Box").</div>
                                </div>
                                <?php else: ?>
                                <form action="manage-cartons.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="receive">
                                    <div class="ata-form-grid">
                                        <div class="ata-field">
                                            <label>Carton Type <span style="color:#ef4444;">*</span></label>
                                            <select required name="carton_type_id" id="receiveCartonTypeSelect">
                                                <option value="" hidden>Select Carton Type</option>
                                                <?php foreach ($cartonTypes as $t): ?>
                                                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?></option>
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
                                            <label>Quantity Received <span style="color:#ef4444;">*</span></label>
                                            <input type="number" required min="1" name="qty" placeholder="e.g. 200">
                                        </div>
                                        <div class="ata-field">
                                            <label>Date <span style="color:#ef4444;">*</span></label>
                                            <input type="date" required name="entry_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="ata-field ata-field-full">
                                            <label>Remarks</label>
                                            <textarea name="note" rows="2" style="height:auto;" placeholder="Optional note about this receipt"></textarea>
                                        </div>
                                    </div>
                                    <div style="padding:0 20px 20px;">
                                        <button type="submit" class="ata-btn ata-btn-tp ata-btn-submit">
                                            <i class="material-icons" style="font-size:17px;">add_box</i> Record Receipt
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>

                            <div class="ata-card card">
                                <div class="ata-section-head">
                                    <div class="ata-section-title"><i class="material-icons-outlined">list_alt</i> Carton Balances</div>
                                </div>
                                <form method="get" action="manage-cartons.php" style="display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap; padding:16px 20px 0;">
                                    <div class="ata-field" style="max-width:180px;">
                                        <label>From</label>
                                        <input type="date" name="from" value="<?= htmlspecialchars($filterFrom ?? '', ENT_QUOTES, 'UTF-8') ?>" max="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="ata-field" style="max-width:180px;">
                                        <label>To</label>
                                        <input type="date" name="to" value="<?= htmlspecialchars($filterTo ?? '', ENT_QUOTES, 'UTF-8') ?>" max="<?= date('Y-m-d') ?>">
                                    </div>
                                    <button type="submit" class="ata-btn ata-btn-tp ata-btn-sm">Filter</button>
                                    <?php if ($filterFrom || $filterTo): ?>
                                    <a href="manage-cartons.php" class="ata-btn ata-btn-sm" style="background:#f3f4f6; color:#4b5563; box-shadow:none;">Clear</a>
                                    <span style="font-size:12px; color:#9ca3af; margin-bottom:8px;">Showing activity in range — Balance is always the current total on hand.</span>
                                    <?php endif; ?>
                                </form>
                                <?php if (empty($cartons)): ?>
                                <div style="padding:20px;">
                                    <div class="alert alert-light mb-0">No carton stock recorded yet.</div>
                                </div>
                                <?php else: ?>
                                <div style="overflow-x:auto;">
                                <table class="ata-carton-table">
                                    <thead>
                                        <tr>
                                            <th>Carton Type</th>
                                            <th>Company Profile</th>
                                            <th>Godown</th>
                                            <th>Received<?= ($filterFrom || $filterTo) ? ' (in range)' : '' ?></th>
                                            <th>Used<?= ($filterFrom || $filterTo) ? ' (in range)' : '' ?></th>
                                            <th>Damaged<?= ($filterFrom || $filterTo) ? ' (in range)' : '' ?></th>
                                            <th>Balance</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cartons as $c): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($c['cartonTypeName'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($godownNamesById[$c['company_godown_id']] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= $c['warehouse_id'] ? htmlspecialchars($warehouseCodesById[$c['warehouse_id']] ?? '—', ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                            <td><?= number_format($c['received_qty']) ?></td>
                                            <td><?= number_format($c['used_qty']) ?></td>
                                            <td><?= number_format($c['damaged_qty']) ?></td>
                                            <td><span class="<?= $c['balance'] > 0 ? 'badge-balance-ok' : 'badge-balance-low' ?>"><?= number_format($c['balance']) ?></span></td>
                                            <td style="white-space:nowrap;">
                                                <button type="button" class="ata-btn ata-btn-tp ata-btn-sm carton-used-btn"
                                                    data-carton-type-id="<?= (int)$c['carton_type_id'] ?>"
                                                    data-godown-id="<?= (int)$c['company_godown_id'] ?>"
                                                    data-warehouse-id="<?= $c['warehouse_id'] !== null ? (int)$c['warehouse_id'] : '' ?>"
                                                    data-carton-type-name="<?= htmlspecialchars($c['cartonTypeName'], ENT_QUOTES, 'UTF-8') ?>"
                                                    style="margin-right:6px;">
                                                    <i class="material-icons" style="font-size:14px;">outbox</i> Used
                                                </button>
                                                <button type="button" class="ata-btn ata-btn-danger ata-btn-sm carton-damage-btn"
                                                    data-carton-type-id="<?= (int)$c['carton_type_id'] ?>"
                                                    data-godown-id="<?= (int)$c['company_godown_id'] ?>"
                                                    data-warehouse-id="<?= $c['warehouse_id'] !== null ? (int)$c['warehouse_id'] : '' ?>"
                                                    data-carton-type-name="<?= htmlspecialchars($c['cartonTypeName'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="material-icons" style="font-size:14px;">report</i> Damage
                                                </button>
                                            </td>
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
</div>

<!-- Damage entry modal -->
<div class="modal fade" id="cartonDamageModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="manage-cartons.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="damage">
                <input type="hidden" name="carton_type_id" id="damageCartonTypeId">
                <input type="hidden" name="company_godown_id" id="damageGodownId">
                <input type="hidden" name="warehouse_id" id="damageWarehouseId">
                <div class="modal-header">
                    <h5 class="modal-title">Record Damaged Cartons — <span id="damageCartonTypeName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Quantity Damaged <span style="color:#ef4444;">*</span></label>
                        <input type="number" required min="1" name="qty" class="form-control">
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; justify-content:space-between;">
                            <span>Reason <span style="color:#ef4444;">*</span></span>
                            <button type="button" class="ata-btn-link" id="manageDamageReasonsBtn" style="text-transform:none; font-weight:500;">Manage Reasons</button>
                        </label>
                        <select required name="reason_id" class="form-control" id="damageReasonSelect">
                            <option value="" hidden>Select Reason</option>
                            <?php foreach ($damageReasons as $r): ?>
                            <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date <span style="color:#ef4444;">*</span></label>
                        <input type="date" required name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="note" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="ata-btn ata-btn-danger">Record Damage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Used entry modal — plain manual consumption log, no reason dropdown
     (see include/CartonBoxes.php's record_used_cartons()). -->
<div class="modal fade" id="cartonUsedModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="manage-cartons.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="used">
                <input type="hidden" name="carton_type_id" id="usedCartonTypeId">
                <input type="hidden" name="company_godown_id" id="usedGodownId">
                <input type="hidden" name="warehouse_id" id="usedWarehouseId">
                <div class="modal-header">
                    <h5 class="modal-title">Record Used Cartons — <span id="usedCartonTypeName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Quantity Used <span style="color:#ef4444;">*</span></label>
                        <input type="number" required min="1" name="qty" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Date <span style="color:#ef4444;">*</span></label>
                        <input type="date" required name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Optional note about this usage"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="ata-btn ata-btn-tp">Record Used</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manage Carton Types / Manage Damage Reasons modal — shared shell,
     `manageModalList` decides which list (type|reason) it's driving via
     carton-box-manage.php's `list` param. -->
<div class="modal fade" id="cartonManageModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cartonManageModalTitle">Manage Carton Types</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div style="display:flex; gap:8px; margin-bottom:14px;">
                    <input type="text" id="cartonManageInput" class="form-control" placeholder="e.g. Large Box">
                    <button type="button" class="ata-btn ata-btn-tp" id="cartonManageSaveBtn" style="white-space:nowrap;">Add</button>
                </div>
                <div id="cartonManageError" style="color:#ef4444; font-size:12.5px; margin-bottom:10px; display:none;"></div>
                <div id="cartonManageList"></div>
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
var csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;

document.querySelectorAll('.carton-damage-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('damageCartonTypeId').value = btn.dataset.cartonTypeId;
        document.getElementById('damageGodownId').value = btn.dataset.godownId;
        document.getElementById('damageWarehouseId').value = btn.dataset.warehouseId;
        document.getElementById('damageCartonTypeName').textContent = btn.dataset.cartonTypeName;
        var modal = new bootstrap.Modal(document.getElementById('cartonDamageModal'));
        modal.show();
    });
});

document.querySelectorAll('.carton-used-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('usedCartonTypeId').value = btn.dataset.cartonTypeId;
        document.getElementById('usedGodownId').value = btn.dataset.godownId;
        document.getElementById('usedWarehouseId').value = btn.dataset.warehouseId;
        document.getElementById('usedCartonTypeName').textContent = btn.dataset.cartonTypeName;
        var modal = new bootstrap.Modal(document.getElementById('cartonUsedModal'));
        modal.show();
    });
});

// Manage Carton Types / Manage Damage Reasons — reused single modal.
// manageModalList tracks which list ('type'|'reason') is currently open
// so add/edit/delete calls carton-box-manage.php with the right `list`.
var manageModalList = 'type';
var manageModalEditId = null;
var manageModalInstance = null;

function escCb(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function cartonManagePostAction(action, extraFields) {
    var formData = new URLSearchParams();
    formData.set('list', manageModalList);
    formData.set('action', action);
    formData.set('csrf_token', csrfToken);
    Object.keys(extraFields || {}).forEach(function (k) { formData.set(k, extraFields[k]); });
    return fetch('carton-box-manage.php', { method: 'POST', body: formData }).then(function (r) { return r.json(); });
}

function loadCartonManageList() {
    cartonManagePostAction('list', {}).then(function (data) {
        var listEl = document.getElementById('cartonManageList');
        listEl.innerHTML = '';
        (data.items || []).filter(function (i) { return i.is_active; }).forEach(function (item) {
            var row = document.createElement('div');
            row.className = 'ata-manage-list-row';
            row.innerHTML = '<span>' + escCb(item.label) + '</span>' +
                '<span class="ata-manage-list-actions">' +
                '<i class="material-icons-outlined edit-item" data-id="' + item.id + '" data-label="' + escCb(item.label) + '">edit</i>' +
                '<i class="material-icons-outlined delete-item" data-id="' + item.id + '">delete</i>' +
                '</span>';
            listEl.appendChild(row);
        });
        if (!listEl.children.length) {
            listEl.innerHTML = '<div style="color:#9ca3af; font-size:12.5px;">Nothing added yet.</div>';
        }
        listEl.querySelectorAll('.edit-item').forEach(function (el) {
            el.addEventListener('click', function () {
                manageModalEditId = el.dataset.id;
                document.getElementById('cartonManageInput').value = el.dataset.label;
                document.getElementById('cartonManageSaveBtn').textContent = 'Save';
            });
        });
        listEl.querySelectorAll('.delete-item').forEach(function (el) {
            el.addEventListener('click', function () {
                if (!confirm('Remove this entry?')) return;
                cartonManagePostAction('delete', { id: el.dataset.id }).then(function () {
                    loadCartonManageList();
                    refreshDamageReasonDropdownIfNeeded();
                    if (manageModalList === 'type') location.reload(); // refresh the Receive form's dropdown
                });
            });
        });
    });
}

// Keeps the damage modal's reason <select> in sync after an add/edit/
// delete against the Damage Reasons list, without reloading the page
// (the damage modal may still be open behind this one).
function refreshDamageReasonDropdownIfNeeded() {
    if (manageModalList !== 'reason') return;
    cartonManagePostAction('list', {}).then(function (data) {
        var select = document.getElementById('damageReasonSelect');
        var currentVal = select.value;
        var html = '<option value="" hidden>Select Reason</option>';
        (data.items || []).filter(function (i) { return i.is_active; }).forEach(function (r) {
            html += '<option value="' + r.id + '">' + escCb(r.label) + '</option>';
        });
        select.innerHTML = html;
        if (currentVal && Array.from(select.options).some(function (o) { return o.value === currentVal; })) {
            select.value = currentVal;
        }
    });
}

function resetCartonManageForm() {
    manageModalEditId = null;
    document.getElementById('cartonManageInput').value = '';
    document.getElementById('cartonManageSaveBtn').textContent = 'Add';
    document.getElementById('cartonManageError').style.display = 'none';
}

function openCartonManageModal(list) {
    manageModalList = list;
    document.getElementById('cartonManageModalTitle').textContent = list === 'type' ? 'Manage Carton Types' : 'Manage Damage Reasons';
    resetCartonManageForm();
    loadCartonManageList();
    if (!manageModalInstance) {
        manageModalInstance = new bootstrap.Modal(document.getElementById('cartonManageModal'));
    }
    manageModalInstance.show();
}

document.getElementById('manageCartonTypesBtn').addEventListener('click', function () { openCartonManageModal('type'); });
document.getElementById('manageDamageReasonsBtn').addEventListener('click', function () { openCartonManageModal('reason'); });

document.getElementById('cartonManageSaveBtn').addEventListener('click', function () {
    var label = document.getElementById('cartonManageInput').value.trim();
    if (!label) {
        document.getElementById('cartonManageError').textContent = 'This field is required.';
        document.getElementById('cartonManageError').style.display = '';
        return;
    }
    var isEdit = !!manageModalEditId;
    cartonManagePostAction(isEdit ? 'edit' : 'add', { id: manageModalEditId || '', label: label }).then(function (data) {
        if (data.success) {
            resetCartonManageForm();
            loadCartonManageList();
            refreshDamageReasonDropdownIfNeeded();
            if (manageModalList === 'type') location.reload();
        } else {
            document.getElementById('cartonManageError').textContent = data.error || 'Could not save.';
            document.getElementById('cartonManageError').style.display = '';
        }
    });
});
</script>
</body>
</html>
