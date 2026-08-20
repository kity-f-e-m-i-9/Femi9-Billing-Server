<?php
/**
 * Company admin tool to move UNSPENT balance between a TP's Napkin and
 * Diaper wallets — solves the "TP paid into the wrong wallet" situation
 * that error messages alone can't fix (see review-mixed-advance-payments.php
 * for the equivalent tool for already-spent/mixed history).
 *
 * Only ever moves product_type — the approver pool (Company vs a specific
 * SS) stays exactly as it was, since that reflects who actually approved
 * the money and must never silently change.
 *
 * Never touches adjusted_amount (already-spent money, tied to real invoices
 * via tp_invoice_advance_log) — only balance_amount can move.
 */
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/TpApproverContext.php';
error_reporting(0);

tpEnsureAdvanceWalletColumns($db_conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tps = $db_conn->query("SELECT id, tp_id, name, mobile FROM territory_partners WHERE is_active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$selectedTpId = (int)($_GET['tp_id'] ?? 0);
$pools = [];
if ($selectedTpId > 0) {
    $assignedSs = tpGetAssignedSs($db_conn, $selectedTpId);
    $approverPools = [['type' => 'company', 'ss_id' => null, 'label' => 'Company']];
    if ($assignedSs !== null) {
        $approverPools[] = ['type' => 'ss', 'ss_id' => $assignedSs['id'], 'label' => $assignedSs['name'] . ' (Super Stockist)'];
    }

    foreach ($approverPools as $ap) {
        foreach (['napkin', 'diaper'] as $type) {
            $stmt = $db_conn->prepare(
                "SELECT COALESCE(SUM(balance_amount), 0) AS bal
                 FROM tp_advance_payments
                 WHERE territory_partner_id = ? AND approver_type = ? AND approver_ss_id <=> ?
                   AND product_type = ? AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL"
            );
            $stmt->bind_param('isis', $selectedTpId, $ap['type'], $ap['ss_id'], $type);
            $stmt->execute();
            $bal = round((float)($stmt->get_result()->fetch_assoc()['bal'] ?? 0), 2);
            $stmt->close();
            $pools[] = [
                'approver_type' => $ap['type'], 'approver_ss_id' => $ap['ss_id'], 'approver_label' => $ap['label'],
                'product_type' => $type, 'balance' => $bal,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Wallet Transfer : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .wt-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:18px 20px; margin-bottom:16px; }
        .wt-pool { border:1px solid #e5e7eb; border-radius:8px; padding:12px 14px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; }
        .wt-pool .lbl { font-size:13px; font-weight:600; }
        .wt-pool .sub { font-size:11.5px; color:#6b7280; }
        .wt-pool .amt { font-size:16px; font-weight:700; }
        .badge-diaper { background:#ede9fe; color:#6d28d9; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
        .badge-napkin { background:#dcfce7; color:#15803d; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
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
                    <div class="page-description"><h1 style="margin:0;">TP Wallet Transfer</h1></div>
                    <p style="color:#6b7280;margin-top:8px;">
                        Move <strong>unspent</strong> advance balance between a TP's Napkin and Diaper wallets —
                        for when money landed in the wrong one. Only moves balance within the same approver pool
                        (Company stays Company, a Super Stockist's pool stays that SS's) and never touches
                        already-spent (adjusted) amounts, which stay tied to their real invoices.
                    </p>

                    <?php if (isset($_GET['done'])): ?>
                    <div class="alert alert-success">Transfer completed.</div>
                    <?php elseif (isset($_GET['error'])): ?>
                    <div class="alert alert-danger">
                        <?php
                            $errMsgs = [
                                'invalid' => 'Please check the amount and try again.',
                                'insufficient' => 'The source wallet does not have enough balance for this transfer.',
                                'same_type' => 'Source and destination must be different wallets.',
                                'failed' => 'Transfer failed. Please try again.',
                            ];
                            echo htmlspecialchars($errMsgs[$_GET['error']] ?? 'Something went wrong.');
                        ?>
                    </div>
                    <?php endif; ?>

                    <div class="wt-card">
                        <form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                            <div style="flex:1;min-width:260px;">
                                <label class="form-label" style="font-weight:600;font-size:13px;">Territory Partner</label>
                                <select name="tp_id" id="tpSelect" class="form-control" onchange="this.form.submit()">
                                    <option value="">Select a TP&hellip;</option>
                                    <?php foreach ($tps as $t): ?>
                                    <option value="<?=$t['id']?>" <?=$selectedTpId===(int)$t['id']?'selected':''?>>
                                        <?=htmlspecialchars($t['name'])?> (<?=htmlspecialchars($t['tp_id'])?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>

                    <?php if ($selectedTpId > 0): ?>
                    <div class="wt-card">
                        <h3 style="font-size:15px;margin-bottom:12px;">Current Balances</h3>
                        <?php foreach ($pools as $i => $p): ?>
                        <div class="wt-pool">
                            <div>
                                <div class="lbl">
                                    <span class="badge-<?=$p['product_type']?>"><?=tpProductTypeLabel($p['product_type'])?></span>
                                </div>
                                <div class="sub"><?=htmlspecialchars($p['approver_label'])?></div>
                            </div>
                            <div class="amt">&#8377;<?=number_format($p['balance'], 2)?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="wt-card">
                        <h3 style="font-size:15px;margin-bottom:12px;">Transfer</h3>
                        <form method="post" action="tp-wallet-transfer-action.php" id="transferForm">
                            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                            <input type="hidden" name="tp_id" value="<?=$selectedTpId?>">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label" style="font-size:12.5px;">From</label>
                                    <select name="from_pool" id="fromPool" class="form-control" required>
                                        <option value="">Select source wallet&hellip;</option>
                                        <?php foreach ($pools as $i => $p): if ($p['balance'] <= 0) continue; ?>
                                        <option value="<?=$i?>" data-balance="<?=$p['balance']?>">
                                            <?=htmlspecialchars($p['approver_label'])?> &mdash; <?=tpProductTypeLabel($p['product_type'])?> (&#8377;<?=number_format($p['balance'], 2)?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" style="font-size:12.5px;">To</label>
                                    <select name="to_pool" id="toPool" class="form-control" required>
                                        <option value="">Select destination wallet&hellip;</option>
                                        <?php foreach ($pools as $i => $p): ?>
                                        <option value="<?=$i?>"><?=htmlspecialchars($p['approver_label'])?> &mdash; <?=tpProductTypeLabel($p['product_type'])?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" style="font-size:12.5px;">Amount &#8377;</label>
                                    <input type="number" name="amount" id="transferAmount" class="form-control" min="0.01" step="0.01" required>
                                </div>
                            </div>
                            <div id="fromPoolMeta" data-approver-type="" data-approver-ss-id="" data-product-type=""></div>
                            <div id="toPoolMeta" data-approver-type="" data-approver-ss-id="" data-product-type=""></div>
                            <div style="margin-top:14px;">
                                <button type="submit" class="btn btn-primary" id="transferSubmit" onclick="return confirm('Move this balance between wallets? This cannot be undone automatically.');">
                                    <i class="material-icons" style="vertical-align:middle;">swap_horiz</i> Transfer
                                </button>
                            </div>
                            <!-- Real pool identity for each option, read by JS on submit -->
                            <script id="poolData" type="application/json"><?php
                                echo json_encode(array_map(function ($p) {
                                    return ['approver_type' => $p['approver_type'], 'approver_ss_id' => $p['approver_ss_id'], 'product_type' => $p['product_type']];
                                }, $pools));
                            ?></script>
                        </form>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script>
$('#tpSelect').select2({ placeholder: 'Select a TP...' });

var poolData = JSON.parse(document.getElementById('poolData') ? document.getElementById('poolData').textContent : '[]');

$('#transferForm').on('submit', function (e) {
    var fromIdx = $('#fromPool').val();
    var toIdx = $('#toPool').val();
    if (fromIdx === '' || toIdx === '') return;
    var from = poolData[fromIdx];
    var to = poolData[toIdx];
    if (from.approver_type === to.approver_type && from.approver_ss_id === to.approver_ss_id && from.product_type === to.product_type) {
        e.preventDefault();
        alert('Source and destination must be different wallets.');
        return false;
    }
    // Inject the real pool identity as hidden fields right before submit
    ['from', 'to'].forEach(function (side) {
        var p = side === 'from' ? from : to;
        $('<input>').attr({ type: 'hidden', name: side + '_approver_type', value: p.approver_type }).appendTo('#transferForm');
        $('<input>').attr({ type: 'hidden', name: side + '_approver_ss_id', value: p.approver_ss_id === null ? '' : p.approver_ss_id }).appendTo('#transferForm');
        $('<input>').attr({ type: 'hidden', name: side + '_product_type', value: p.product_type }).appendTo('#transferForm');
    });
});
</script>
</body>
</html>
