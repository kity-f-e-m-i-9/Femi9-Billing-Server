<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/RawMaterialBundles.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support),
// matching the other pages in this feature family.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'undo') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
        header("Location: manage-piece-pack-conversions.php");
        exit;
    }

    $refId = trim((string) ($_POST['ref_id'] ?? ''));
    $actingUser = $_SESSION['LOGIN_USER'] ?? 'system';

    if ($refId === '') {
        $_SESSION['errorMessage'] = "Invalid conversion reference.";
        header("Location: manage-piece-pack-conversions.php");
        exit;
    }

    // The conversion's own stock_ledger 'credit' row is the source of
    // truth for exactly which (product, godown, warehouse) and qty to
    // reverse — the draws table only tracks the bundle side.
    $ledgerStmt = $db_conn->prepare(
        "SELECT product_id, user_id, warehouse_id, qty FROM stock_ledger
         WHERE ref_type = 'conversion' AND ref_id = ? AND action = 'credit' AND user_type = 'company'
         LIMIT 1"
    );
    $ledgerStmt->bind_param('s', $refId);
    $ledgerStmt->execute();
    $ledgerRow = $ledgerStmt->get_result()->fetch_assoc();
    $ledgerStmt->close();

    if (!$ledgerRow) {
        $_SESSION['errorMessage'] = "Could not find the stock record for this conversion — it may already have been undone.";
        header("Location: manage-piece-pack-conversions.php");
        exit;
    }

    if (!is_godown_allowed($db_conn, (int) $ledgerRow['user_id'])) {
        $_SESSION['errorMessage'] = "You are not authorized to undo a conversion for this company profile.";
        header("Location: manage-piece-pack-conversions.php");
        exit;
    }

    $stockService = new StockService($db_conn);

    $db_conn->begin_transaction();
    try {
        // Give the pieces back to the bundle first — refuses if the
        // bundle has since been closed, before any stock is touched.
        restore_bundle_draw($db_conn, $refId);

        // Reverse the pack credit: closing_qty ↓, input_qty ↓ (floored at
        // 0) — the exact inverse of the credit() this conversion made.
        $reverseResult = $stockService->reverseCredit(
            (int) $ledgerRow['product_id'], 'company', $ledgerRow['user_id'], (int) $ledgerRow['qty'],
            'conversion', $refId, $actingUser,
            true, // externalTransaction
            $ledgerRow['warehouse_id'] !== null ? (int) $ledgerRow['warehouse_id'] : null
        );
        if (empty($reverseResult['success'])) {
            throw new StockException("Could not reverse the pack stock for this conversion — it may already have been sold or moved on.");
        }

        $db_conn->commit();
        $_SESSION['sucMessage'] = "Conversion undone — pieces returned to the bundle.";
    } catch (StockException $e) {
        $db_conn->rollback();
        $_SESSION['errorMessage'] = $e->getMessage();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("manage-piece-pack-conversions.php undo error: " . $e->getMessage());
        $_SESSION['errorMessage'] = "An error occurred. Please try again.";
    }

    header("Location: manage-piece-pack-conversions.php");
    exit;
}

$conversions = get_bundle_conversions($db_conn);
$totalConversions = count($conversions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Conversions : <?php echo $business_name; ?></title>

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

        .badge-open { background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }
        .badge-closed { background:#f3f4f6;color:#6b7280;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }

        .conv-table { width:100%; border-collapse:separate; border-spacing:0; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.05); }
        .conv-table thead th { background:#f8fafc; color:#475569; font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.5px; padding:12px 14px; border-bottom:2px solid #e5e7eb; white-space:nowrap; }
        .conv-table tbody td { padding:11px 14px; vertical-align:middle; border-bottom:1px solid #f1f5f9; font-size:13.5px; color:#1e293b; }
        .conv-table tbody tr:last-child td { border-bottom:none; }
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
                        <h1><i class="material-icons-outlined" style="font-size:22px;vertical-align:middle;margin-right:8px;color:var(--ata-tp-1);">sync_alt</i>Manage Conversions</h1>
                    </div>
                    <p class="ata-intro">Every bundle-sourced Pieces &harr; Pack conversion — undo one to return the pieces to their bundle and reverse the pack credit.</p>

                    <div class="ata-nav-tabs">
                        <a href="input-stock-bundles.php" class="ata-nav-tab"><i class="material-icons-outlined">add_box</i> Input Stock</a>
                        <a href="raw-material-bundles-manage.php" class="ata-nav-tab"><i class="material-icons-outlined">list_alt</i> Manage Bundles</a>
                        <a href="neksomo-piece-pack-convert.php" class="ata-nav-tab"><i class="material-icons-outlined">sync_alt</i> Convert Pieces &harr; Packs</a>
                        <a href="manage-piece-pack-conversions.php" class="ata-nav-tab active"><i class="material-icons-outlined">history</i> Manage Conversions</a>
                    </div>

                    <?php if (isset($_SESSION['errorMessage'])): $flashErr = htmlspecialchars($_SESSION['errorMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['errorMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"error",title:"Error",text:"<?= $flashErr ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['sucMessage'])): $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['sucMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"success",title:"Success",text:"<?= $flashMsg ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>

                    <?php if (empty($conversions)): ?>
                        <div class="alert alert-info">No bundle-sourced conversions recorded yet.</div>
                    <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="conv-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Company Profile</th>
                                    <th>Bundle</th>
                                    <th style="text-align:center;">Packs Made</th>
                                    <th style="text-align:center;">Pieces Used</th>
                                    <th>By</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conversions as $i => $c): ?>
                                <tr>
                                    <td style="color:#9ca3af;"><?php echo $i + 1; ?></td>
                                    <td style="color:#6b7280;"><?php echo date('d-M-Y', strtotime($c['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($c['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($c['gname'], ENT_QUOTES, 'UTF-8'); ?><?php echo $c['warehouse_code'] ? ' · ' . htmlspecialchars($c['warehouse_code'], ENT_QUOTES, 'UTF-8') : ''; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($c['bundle_label'], ENT_QUOTES, 'UTF-8'); ?>
                                        <span class="<?php echo $c['bundle_status'] === 'open' ? 'badge-open' : 'badge-closed'; ?>" style="margin-left:6px;"><?php echo ucfirst($c['bundle_status']); ?></span>
                                    </td>
                                    <td style="text-align:center;font-weight:600;"><?php echo number_format($c['packs_made']); ?></td>
                                    <td style="text-align:center;"><?php echo number_format($c['pieces_used']); ?></td>
                                    <td style="color:#6b7280;"><?php echo htmlspecialchars($c['created_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if ($c['bundle_status'] === 'open'): ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Undo this conversion? <?php echo (int) $c['packs_made']; ?> pack(s) will be removed from stock and <?php echo (int) $c['pieces_used']; ?> piece(s) returned to <?php echo htmlspecialchars(addslashes($c['bundle_label']), ENT_QUOTES, 'UTF-8'); ?>.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="undo">
                                            <input type="hidden" name="ref_id" value="<?php echo htmlspecialchars($c['ref_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Undo</button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-muted small" title="The bundle this conversion drew from has been closed">Bundle closed</span>
                                        <?php endif; ?>
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

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
