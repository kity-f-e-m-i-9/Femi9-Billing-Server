<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once __DIR__ . '/../shared/AgreementService.php';
include("config.php");
error_reporting(0);

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['admin', 'finance'], true)) {
    header("Location: dashboard.php");
    exit;
}

$settings = get_agreement_settings($db_conn);

$cps = $db_conn->query("SELECT id, cp_id, name, company_name FROM channel_partners ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$tps = $db_conn->query("SELECT id, tp_id, name, company_name FROM territory_partners WHERE deleted_at IS NULL ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

$selectedCpId = (int) ($_GET['cp_id'] ?? 0);
$selectedTpId = (int) ($_GET['tp_id'] ?? 0);
$cpAgreement = $selectedCpId ? get_or_create_cp_agreement($db_conn, $selectedCpId) : null;
$tpAgreement = $selectedTpId ? get_or_create_tp_agreement($db_conn, $selectedTpId) : null;

// Territory Partner's own menu links here with ?tab=tp (or a tp_id, e.g.
// from a submitted TP-tab form) so it lands straight on the TP pane
// instead of always defaulting to the CP one.
$activeTab = ($_GET['tab'] ?? '') === 'tp' || $selectedTpId ? 'tp' : 'cp';

if (isset($_SESSION['sucMessage'])) { $flashMsg = $_SESSION['sucMessage']; unset($_SESSION['sucMessage']); }

function fv($val) { return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Agreements : <?php echo fv($business_name ?? 'Femi9'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
                    <?php if (!empty($flashMsg)): ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:'success', title:'Success', text:<?php echo json_encode($flashMsg); ?>});</script>
                    <?php endif; ?>

                    <h1 style="font-size:20px;font-weight:600;color:#1f2937;margin-bottom:16px;">Manage Agreements</h1>

                    <div class="card mb-4">
                        <div class="card-header"><h5 class="card-title">Company Settings (shown on every CP/TP Agreement)</h5></div>
                        <div class="card-body">
                            <form method="post" action="manage-agreements-action.php">
                                <input type="hidden" name="action" value="save_settings">
                                <div class="mb-3">
                                    <label class="form-label">FEMI9 LLP Registered Office / Principal Place of Business</label>
                                    <textarea name="company_address" class="form-control" rows="2"><?php echo fv($settings['company_address']); ?></textarea>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label">Authorized Signatory Name</label>
                                        <input type="text" name="authorized_signatory_name" class="form-control" value="<?php echo fv($settings['authorized_signatory_name']); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Designation</label>
                                        <input type="text" name="authorized_signatory_designation" class="form-control" value="<?php echo fv($settings['authorized_signatory_designation']); ?>">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary">Save Company Settings</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item"><button class="nav-link<?php echo $activeTab === 'cp' ? ' active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#cpPane" type="button">Channel Partners</button></li>
                        <li class="nav-item"><button class="nav-link<?php echo $activeTab === 'tp' ? ' active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tpPane" type="button">Territory Partners</button></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade<?php echo $activeTab === 'cp' ? ' show active' : ''; ?>" id="cpPane">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <form method="get">
                                        <label class="form-label">Select Channel Partner</label>
                                        <select name="cp_id" class="form-control" onchange="this.form.submit()" style="max-width:420px;">
                                            <option value="0">-- Select --</option>
                                            <?php foreach ($cps as $c): ?>
                                            <option value="<?php echo (int) $c['id']; ?>" <?php echo $selectedCpId === (int) $c['id'] ? 'selected' : ''; ?>>
                                                <?php echo fv($c['name'] . ' (' . $c['cp_id'] . ')' . ($c['company_name'] ? ' — ' . $c['company_name'] : '')); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </div>
                            </div>
                            <?php if ($cpAgreement): ?>
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h5 class="card-title mb-0">Schedule-1 Particulars
                                        <?php if ((int)$cpAgreement['is_locked'] === 1): ?>
                                            <span class="badge bg-success">Signed on <?php echo fv(date('d-M-Y', strtotime($cpAgreement['signed_at']))); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not signed yet</span>
                                        <?php endif; ?>
                                    </h5>
                                    <a href="view-cp-agreement.php?cp_id=<?php echo (int) $selectedCpId; ?>" class="btn btn-outline-primary btn-sm">View Full Agreement</a>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="manage-agreements-action.php">
                                        <input type="hidden" name="action" value="save_cp_schedule">
                                        <input type="hidden" name="cp_id" value="<?php echo (int) $selectedCpId; ?>">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Security Deposit (Rs.)</label>
                                                <input type="number" step="0.01" name="security_deposit" class="form-control" value="<?php echo fv($cpAgreement['security_deposit']); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Approved Stock Holding Capacity</label>
                                                <input type="text" name="stock_holding_capacity" class="form-control" value="<?php echo fv($cpAgreement['stock_holding_capacity']); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Applicable Commercial / Purchase Category</label>
                                                <input type="text" name="commercial_category" class="form-control" value="<?php echo fv($cpAgreement['commercial_category']); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Approved Division(s)</label>
                                                <textarea name="approved_divisions" class="form-control" rows="2"><?php echo fv($cpAgreement['approved_divisions']); ?></textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Division Code(s)</label>
                                                <textarea name="division_codes" class="form-control" rows="2"><?php echo fv($cpAgreement['division_codes']); ?></textarea>
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">Approved Warehouse Address</label>
                                                <textarea name="approved_warehouse_address" class="form-control" rows="2"><?php echo fv($cpAgreement['approved_warehouse_address']); ?></textarea>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Effective Date</label>
                                                <input type="date" name="effective_date" class="form-control" value="<?php echo fv($cpAgreement['effective_date']); ?>">
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">Other Approved Commercial Particulars</label>
                                                <input type="text" name="other_particulars" class="form-control" value="<?php echo fv($cpAgreement['other_particulars']); ?>">
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary mt-3">Save</button>
                                        <?php if ((int) $cpAgreement['is_locked'] === 1): ?>
                                        <button type="submit" form="unlockCpForm" class="btn btn-outline-danger mt-3" onclick="return confirm('Unlock this signed agreement so the Channel Partner can re-sign? Their previous signature/witness details will remain visible until they submit again.');">Unlock for Re-signing</button>
                                        <?php endif; ?>
                                    </form>
                                    <?php if ((int) $cpAgreement['is_locked'] === 1): ?>
                                    <form method="post" action="manage-agreements-action.php" id="unlockCpForm">
                                        <input type="hidden" name="action" value="unlock_cp">
                                        <input type="hidden" name="cp_id" value="<?php echo (int) $selectedCpId; ?>">
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="tab-pane fade<?php echo $activeTab === 'tp' ? ' show active' : ''; ?>" id="tpPane">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <form method="get">
                                        <label class="form-label">Select Territory Partner</label>
                                        <select name="tp_id" class="form-control" onchange="this.form.submit()" style="max-width:420px;">
                                            <option value="0">-- Select --</option>
                                            <?php foreach ($tps as $t): ?>
                                            <option value="<?php echo (int) $t['id']; ?>" <?php echo $selectedTpId === (int) $t['id'] ? 'selected' : ''; ?>>
                                                <?php echo fv($t['name'] . ' (' . $t['tp_id'] . ')' . ($t['company_name'] ? ' — ' . $t['company_name'] : '')); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </div>
                            </div>
                            <?php if ($tpAgreement): ?>
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h5 class="card-title mb-0">Schedule-1 Particulars
                                        <?php if ((int)$tpAgreement['is_locked'] === 1): ?>
                                            <span class="badge bg-success">Signed on <?php echo fv(date('d-M-Y', strtotime($tpAgreement['signed_at']))); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not signed yet</span>
                                        <?php endif; ?>
                                    </h5>
                                    <a href="view-tp-agreement.php?tp_id=<?php echo (int) $selectedTpId; ?>" class="btn btn-outline-primary btn-sm">View Full Agreement</a>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="manage-agreements-action.php">
                                        <input type="hidden" name="action" value="save_tp_schedule">
                                        <input type="hidden" name="tp_id" value="<?php echo (int) $selectedTpId; ?>">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Approved Monthly Purchase Commitment (Rs.)</label>
                                                <input type="number" step="0.01" name="monthly_purchase_commitment" class="form-control" value="<?php echo fv($tpAgreement['monthly_purchase_commitment']); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Taluk / Block</label>
                                                <input type="text" name="taluk_block" class="form-control" value="<?php echo fv($tpAgreement['taluk_block']); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Effective Date</label>
                                                <input type="date" name="effective_date" class="form-control" value="<?php echo fv($tpAgreement['effective_date']); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Allotted Firka / Territory</label>
                                                <textarea name="territory_firka" class="form-control" rows="2"><?php echo fv($tpAgreement['territory_firka']); ?></textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Territory Code</label>
                                                <textarea name="territory_code" class="form-control" rows="2"><?php echo fv($tpAgreement['territory_code']); ?></textarea>
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">Other Approved Commercial Particulars</label>
                                                <input type="text" name="other_particulars" class="form-control" value="<?php echo fv($tpAgreement['other_particulars']); ?>">
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary mt-3">Save</button>
                                        <?php if ((int) $tpAgreement['is_locked'] === 1): ?>
                                        <button type="submit" form="unlockTpForm" class="btn btn-outline-danger mt-3" onclick="return confirm('Unlock this signed agreement so the Territory Partner can re-sign? Their previous signature/witness details will remain visible until they submit again.');">Unlock for Re-signing</button>
                                        <?php endif; ?>
                                    </form>
                                    <?php if ((int) $tpAgreement['is_locked'] === 1): ?>
                                    <form method="post" action="manage-agreements-action.php" id="unlockTpForm">
                                        <input type="hidden" name="action" value="unlock_tp">
                                        <input type="hidden" name="tp_id" value="<?php echo (int) $selectedTpId; ?>">
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
