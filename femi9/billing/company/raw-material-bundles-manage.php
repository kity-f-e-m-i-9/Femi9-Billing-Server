<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/RawMaterialBundles.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support),
// matching the Convert Pieces<->Packs page this feature feeds into.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['close', 'damage', 'add_extra'], true)) {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
        header("Location: raw-material-bundles-manage.php");
        exit;
    }
    $bundleId = (int) ($_POST['bundle_id'] ?? 0);
    $actingUser = $_SESSION['LOGIN_USER'] ?? 'system';

    try {
        if ($_POST['action'] === 'close') {
            // close_raw_material_bundle() locks the bundle row (and
            // possibly a merge-target row) with FOR UPDATE, then may
            // INSERT a new carry-forward bundle before marking this one
            // closed — wrapped in a transaction so a failure partway
            // through never leaves a bundle half-closed with its
            // leftover unaccounted for.
            $db_conn->begin_transaction();
            try {
                $closed = $bundleId > 0 && close_raw_material_bundle($db_conn, $bundleId, $actingUser);
                $db_conn->commit();
            } catch (\Throwable $e) {
                $db_conn->rollback();
                throw $e;
            }
            if ($closed) {
                $_SESSION['sucMessage'] = "Bundle closed.";
            } else {
                $_SESSION['errorMessage'] = "Could not close that bundle — it may already be closed.";
            }
        } elseif ($_POST['action'] === 'damage') {
            $qty = filter_var($_POST['qty'] ?? '', FILTER_VALIDATE_INT) ?: 0;
            $note = trim((string) ($_POST['note'] ?? ''));
            $note = $note !== '' ? mb_substr($note, 0, 255) : null;
            if ($bundleId > 0 && $qty > 0) {
                record_damaged_pieces($db_conn, $bundleId, $qty, $note, $actingUser);
                $_SESSION['sucMessage'] = "$qty damaged piece(s) recorded.";
            } else {
                $_SESSION['errorMessage'] = "Please enter a valid damaged quantity.";
            }
        } elseif ($_POST['action'] === 'add_extra') {
            $qty = filter_var($_POST['qty'] ?? '', FILTER_VALIDATE_INT) ?: 0;
            if ($bundleId > 0 && $qty > 0) {
                add_extra_pieces_to_bundle($db_conn, $bundleId, $qty, $actingUser);
                $_SESSION['sucMessage'] = "$qty extra piece(s) added to the bundle.";
            } else {
                $_SESSION['errorMessage'] = "Please enter a valid quantity.";
            }
        }
    } catch (StockException $e) {
        $_SESSION['errorMessage'] = $e->getMessage();
    }

    header("Location: raw-material-bundles-manage.php");
    exit;
}

$bundles = get_raw_material_bundles($db_conn);

$totalBundles  = count($bundles);
$openCount     = 0;
$carriedCount  = 0;
$excessCount   = 0;
foreach ($bundles as $b) {
    if ($b['status'] === 'open') $openCount++;
    if ($b['carried_to_bundle_id'] !== null) $carriedCount++;
    if ($b['variance_label'] !== null && strpos($b['variance_label'], 'Excess') !== false) $excessCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Raw Bundles : <?php echo $business_name; ?></title>

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

        .ata-nav-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
        .ata-nav-tab { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:9px; font-size:13.5px; font-weight:500; text-decoration:none; transition:filter .15s, background .15s; }
        .ata-nav-tab i { font-size:17px; }
        .ata-nav-tab.active { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); color:#fff; box-shadow:0 2px 8px rgba(102,126,234,.3); }
        .ata-nav-tab:not(.active) { background:#f3f4f6; color:#4b5563; }
        .ata-nav-tab:not(.active):hover { background:#e5e7eb; color:#1f2937; }

        .ata-summary { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; }
        .ata-stat { flex:1 1 140px; border:1px solid #eef0f3; border-radius:12px; padding:12px 16px; background:#fafbfc; }
        .ata-stat .num { font-size:20px; font-weight:700; color:#1f2937; line-height:1.2; }
        .ata-stat .lbl { font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.03em; margin-top:2px; }
        .ata-stat.open .num { color:#1e40af; }
        .ata-stat.short .num { color:#991b1b; }
        .ata-stat.excess .num { color:#92400e; }

        .badge-open { background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }
        .badge-closed { background:#f3f4f6;color:#6b7280;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }
        .badge-short { background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }
        .badge-excess { background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }
        .badge-exact { background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }

        .ata-filters { display:flex; gap:8px; margin-bottom:16px; }
        .ata-filter-btn { border:1px solid #e5e7eb; background:#fff; color:#4b5563; font-size:13px; font-weight:500; padding:6px 14px; border-radius:8px; cursor:pointer; transition:background .15s; }
        .ata-filter-btn:hover { background:#f3f4f6; }
        .ata-filter-btn.active { background:#1f2937; color:#fff; border-color:#1f2937; }

        .ata-bundle-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(270px, 1fr)); gap:14px; }
        .ata-bundle-card { border:1px solid #eef0f3; border-radius:14px; padding:16px 18px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.04); transition:box-shadow .15s; }
        .ata-bundle-card:hover { box-shadow:0 4px 14px rgba(0,0,0,.07); }
        .ata-bundle-card.is-short { border-color:#fecaca; }
        .ata-bundle-card.is-excess { border-color:#fde68a; }
        .ata-bc-top { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:8px; }
        .ata-bc-title { font-weight:600; font-size:14px; color:#1f2937; }
        .ata-bc-product { font-size:12.5px; color:#6b7280; margin-bottom:12px; }
        .ata-bc-meta { font-size:12px; color:#9ca3af; margin-bottom:10px; }
        .ata-bc-bar-wrap { background:#f1f5f9; border-radius:6px; height:8px; overflow:hidden; margin-bottom:6px; }
        .ata-bc-bar { height:100%; background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); border-radius:6px; }
        .ata-bc-bar.is-short { background:#f87171; }
        .ata-bc-bar.is-excess { background:#fbbf24; }
        .ata-bc-qty { font-size:12.5px; color:#4b5563; margin-bottom:12px; display:flex; justify-content:space-between; }
        .ata-bc-footer { display:flex; justify-content:space-between; align-items:center; margin-top:8px; }
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
                        <h1><i class="material-icons-outlined" style="font-size:22px;vertical-align:middle;margin-right:8px;color:var(--ata-tp-1);">list_alt</i>Manage Raw Bundles</h1>
                    </div>
                    <p class="ata-intro">Every raw material bundle, its remaining pieces, and variance once closed.</p>

                    <div class="ata-nav-tabs">
                        <a href="input-stock-bundles.php" class="ata-nav-tab"><i class="material-icons-outlined">add_box</i> Input Stock</a>
                        <a href="raw-material-bundles-manage.php" class="ata-nav-tab active"><i class="material-icons-outlined">list_alt</i> Manage Bundles</a>
                        <a href="neksomo-piece-pack-convert.php" class="ata-nav-tab"><i class="material-icons-outlined">sync_alt</i> Convert Pieces &harr; Packs</a>
                        <a href="manage-piece-pack-conversions.php" class="ata-nav-tab"><i class="material-icons-outlined">history</i> Manage Conversions</a>
                    </div>

                    <?php if (isset($_SESSION['errorMessage'])): $flashErr = htmlspecialchars($_SESSION['errorMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['errorMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"error",title:"Error",text:"<?= $flashErr ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['sucMessage'])): $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['sucMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"success",title:"Success",text:"<?= $flashMsg ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>

                    <?php if ($totalBundles > 0): ?>
                    <div class="ata-summary">
                        <div class="ata-stat">
                            <div class="num"><?php echo $totalBundles; ?></div>
                            <div class="lbl">Total Bundles</div>
                        </div>
                        <div class="ata-stat open">
                            <div class="num"><?php echo $openCount; ?></div>
                            <div class="lbl">Open</div>
                        </div>
                        <div class="ata-stat short">
                            <div class="num"><?php echo $carriedCount; ?></div>
                            <div class="lbl">Carried Forward</div>
                        </div>
                        <div class="ata-stat excess">
                            <div class="num"><?php echo $excessCount; ?></div>
                            <div class="lbl">Closed Excess</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($bundles)): ?>
                        <div class="alert alert-info">No bundles recorded yet.</div>
                    <?php else: ?>
                    <div class="ata-filters">
                        <button type="button" class="ata-filter-btn active" data-filter="all">All (<?php echo $totalBundles; ?>)</button>
                        <button type="button" class="ata-filter-btn" data-filter="open">Open (<?php echo $openCount; ?>)</button>
                        <button type="button" class="ata-filter-btn" data-filter="closed">Closed (<?php echo $totalBundles - $openCount; ?>)</button>
                    </div>

                    <div class="ata-bundle-grid" id="bundleGrid">
                        <?php foreach ($bundles as $b):
                            $isCarried = $b['carried_to_bundle_id'] !== null;
                            $isExcess = $b['variance_label'] !== null && strpos($b['variance_label'], 'Excess') !== false;
                            $cardExtraClass = $isCarried ? ' is-short' : ($isExcess ? ' is-excess' : '');
                            $pct = $b['nominal_pieces'] > 0 ? max(0, min(100, round((($b['nominal_pieces'] - max($b['remaining_pieces'], 0)) / $b['nominal_pieces']) * 100))) : 0;
                            $barExtraClass = $cardExtraClass;
                        ?>
                        <div class="ata-bundle-card<?php echo $cardExtraClass; ?>" data-status="<?php echo $b['status']; ?>">
                            <div class="ata-bc-top">
                                <div>
                                    <div class="ata-bc-title"><?php echo htmlspecialchars($b['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="ata-bc-product"><?php echo htmlspecialchars($b['product_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <span class="<?php echo $b['status'] === 'open' ? 'badge-open' : 'badge-closed'; ?>"><?php echo ucfirst($b['status']); ?></span>
                            </div>

                            <div class="ata-bc-meta">
                                <?php echo htmlspecialchars($b['gname'], ENT_QUOTES, 'UTF-8'); ?><?php echo $b['warehouse_code'] ? ' · ' . htmlspecialchars($b['warehouse_code'], ENT_QUOTES, 'UTF-8') : ''; ?>
                            </div>

                            <div class="ata-bc-bar-wrap"><div class="ata-bc-bar<?php echo $barExtraClass; ?>" style="width:<?php echo $pct; ?>%;"></div></div>
                            <div class="ata-bc-qty">
                                <span><?php echo number_format(max($b['remaining_pieces'], 0)); ?> left</span>
                                <span>of <?php echo number_format($b['nominal_pieces']); ?> nominal</span>
                            </div>

                            <?php if ($b['damaged_pieces'] > 0 || $b['added_extra_pieces'] > 0): ?>
                            <div style="font-size:11.5px;color:#9ca3af;margin-bottom:8px;">
                                <?php if ($b['damaged_pieces'] > 0): ?><span style="color:#b91c1c;">&#9888; <?php echo number_format($b['damaged_pieces']); ?> damaged</span><?php endif; ?>
                                <?php if ($b['damaged_pieces'] > 0 && $b['added_extra_pieces'] > 0): ?> &middot; <?php endif; ?>
                                <?php if ($b['added_extra_pieces'] > 0): ?><span style="color:#b45309;">+<?php echo number_format($b['added_extra_pieces']); ?> added</span><?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($isCarried): ?>
                            <div style="font-size:11.5px;color:#6b7280;margin-bottom:8px;">
                                <i class="material-icons-outlined" style="font-size:13px;vertical-align:middle;">arrow_forward</i> Carried into Bundle #<?php echo (int) $b['carried_to_bundle_id']; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($b['carried_from_bundle_id'] !== null): ?>
                            <div style="font-size:11.5px;color:#6b7280;margin-bottom:8px;">
                                <i class="material-icons-outlined" style="font-size:13px;vertical-align:middle;">arrow_back</i> Carried from Bundle #<?php echo (int) $b['carried_from_bundle_id']; ?>
                            </div>
                            <?php endif; ?>

                            <div class="ata-bc-footer">
                                <?php if ($b['variance_label'] !== null): ?>
                                    <span class="<?php echo $isCarried ? 'badge-short' : ($isExcess ? 'badge-excess' : 'badge-exact'); ?>"><?php echo htmlspecialchars($b['variance_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small"><?php echo date('d M Y', strtotime($b['created_at'])); ?></span>
                                <?php endif; ?>

                                <?php if ($b['status'] === 'open'): ?>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Record damaged pieces" onclick="openBundleActionModal(<?php echo (int) $b['id']; ?>, '<?php echo htmlspecialchars(addslashes($b['label']), ENT_QUOTES, 'UTF-8'); ?>', 'damage')">
                                        <i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">report_problem</i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Add extra pieces found in this bundle" onclick="openBundleActionModal(<?php echo (int) $b['id']; ?>, '<?php echo htmlspecialchars(addslashes($b['label']), ENT_QUOTES, 'UTF-8'); ?>', 'add_extra')">
                                        <i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">add_circle_outline</i>
                                    </button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Close <?php echo htmlspecialchars(addslashes($b['label']), ENT_QUOTES, 'UTF-8'); ?>? Remaining: <?php echo $b['remaining_pieces']; ?> piece(s)<?php echo $b['remaining_pieces'] > 0 ? ' — this will automatically carry forward into another open bundle (or a new one)' : ''; ?>. This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="close">
                                        <input type="hidden" name="bundle_id" value="<?php echo (int) $b['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Close</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                    <span class="text-muted small"><?php echo htmlspecialchars($b['closed_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shared modal for the two quick bundle adjustments — Record Damage /
     Add Extra Pieces both post to this same page (action="damage" or
     "add_extra"), only the copy/icon changes based on which one was
     clicked (see openBundleActionModal()). -->
<div class="modal fade" id="bundleActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="bundleActionForm">
                <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                    <h6 class="modal-title" id="bundleActionModalTitle" style="font-weight:600;color:#1f2937;"></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding:16px 22px;">
                    <p class="text-muted small mb-3" id="bundleActionModalDesc"></p>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" id="bundleActionField" value="">
                    <input type="hidden" name="bundle_id" id="bundleActionBundleId" value="">
                    <div class="mb-3">
                        <label class="form-label" id="bundleActionQtyLabel">Quantity</label>
                        <input type="number" min="1" required name="qty" id="bundleActionQty" class="form-control" placeholder="e.g. 10">
                    </div>
                    <div class="mb-1" id="bundleActionNoteWrap">
                        <label class="form-label">Note (optional)</label>
                        <input type="text" name="note" maxlength="255" class="form-control" placeholder="e.g. torn during handling">
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="bundleActionSubmitBtn">Save</button>
                </div>
            </form>
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
document.querySelectorAll('.ata-filter-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.ata-filter-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var filter = btn.getAttribute('data-filter');
        document.querySelectorAll('.ata-bundle-card').forEach(function (card) {
            var status = card.getAttribute('data-status');
            var show = filter === 'all' || filter === status;
            card.style.display = show ? '' : 'none';
        });
    });
});

function openBundleActionModal(bundleId, bundleLabel, action) {
    document.getElementById('bundleActionField').value = action;
    document.getElementById('bundleActionBundleId').value = bundleId;
    document.getElementById('bundleActionQty').value = '';

    var titleEl = document.getElementById('bundleActionModalTitle');
    var descEl = document.getElementById('bundleActionModalDesc');
    var qtyLabelEl = document.getElementById('bundleActionQtyLabel');
    var noteWrap = document.getElementById('bundleActionNoteWrap');
    var submitBtn = document.getElementById('bundleActionSubmitBtn');

    if (action === 'damage') {
        titleEl.textContent = 'Record Damaged Pieces — ' + bundleLabel;
        descEl.textContent = 'These pieces are removed from the bundle\'s remaining count and can never become a pack.';
        qtyLabelEl.textContent = 'Damaged Quantity';
        noteWrap.style.display = '';
        submitBtn.textContent = 'Record Damage';
        submitBtn.className = 'btn btn-danger btn-sm';
    } else {
        titleEl.textContent = 'Add Extra Pieces — ' + bundleLabel;
        descEl.textContent = 'Use this only when conversion stopped at 0 remaining but you know this bundle physically still has material left.';
        qtyLabelEl.textContent = 'Extra Quantity Found';
        noteWrap.style.display = 'none';
        submitBtn.textContent = 'Add Pieces';
        submitBtn.className = 'btn btn-primary btn-sm';
    }

    var modal = new bootstrap.Modal(document.getElementById('bundleActionModal'));
    modal.show();
}
</script>
</body>
</html>
