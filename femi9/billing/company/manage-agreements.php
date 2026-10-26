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

// Signed-vs-pending counts for the tab badges below — total CP/TP count
// against how many of their *_agreements rows are is_locked=1 (a row only
// exists once that partner has opened the Agreement page at least once, so
// counting locked rows directly, not joining, correctly treats "never
// opened the page" the same as "opened but not signed").
$cpSignedCount = (int) ($db_conn->query("SELECT COUNT(*) c FROM channel_partner_agreements WHERE is_locked=1")->fetch_assoc()['c'] ?? 0);
$tpSignedCount = (int) ($db_conn->query("SELECT COUNT(*) c FROM territory_partner_agreements tpa JOIN territory_partners tp ON tp.id = tpa.territory_partner_id WHERE tpa.is_locked=1 AND tp.deleted_at IS NULL")->fetch_assoc()['c'] ?? 0);
$cpTotalCount = count($cps);
$tpTotalCount = count($tps);

// Partners with a per-partner custom wording override set -- shown as a
// checklist inside "Edit Agreement Wording" (the shared/common template
// editor) so the admin can see who the common change WON'T reach (custom
// wording always wins over the shared template, see
// get_effective_agreement_body()) and choose to drop any of their
// overrides back to the shared template as part of this same save,
// instead of that silently never happening.
$cpsWithCustomWording = $db_conn->query("
    SELECT cp.id, cp.name, cp.cp_id FROM channel_partners cp
    JOIN channel_partner_agreements cpa ON cpa.channel_partner_id = cp.id
    WHERE cpa.custom_body_html IS NOT NULL AND cpa.custom_body_html != ''
    ORDER BY cp.name ASC
")->fetch_all(MYSQLI_ASSOC);
$tpsWithCustomWording = $db_conn->query("
    SELECT tp.id, tp.name, tp.tp_id FROM territory_partners tp
    JOIN territory_partner_agreements tpa ON tpa.territory_partner_id = tp.id
    WHERE tpa.custom_body_html IS NOT NULL AND tpa.custom_body_html != '' AND tp.deleted_at IS NULL
    ORDER BY tp.name ASC
")->fetch_all(MYSQLI_ASSOC);

$selectedCpId = (int) ($_GET['cp_id'] ?? 0);
$selectedTpId = (int) ($_GET['tp_id'] ?? 0);
$cpAgreement = $selectedCpId ? get_or_create_cp_agreement($db_conn, $selectedCpId) : null;
$tpAgreement = $selectedTpId ? get_or_create_tp_agreement($db_conn, $selectedTpId) : null;

// Effective Date defaults to the TP's own onboarding date (when the
// software first created their record) rather than sitting blank until
// someone picks one -- only used as a fallback for display; once
// effective_date is actually saved, that value wins.
$selectedTpCreatedDate = null;
if ($selectedTpId) {
    $stmtTpCreated = $db_conn->prepare("SELECT created_at FROM territory_partners WHERE id = ?");
    $stmtTpCreated->bind_param('i', $selectedTpId);
    $stmtTpCreated->execute();
    $tpCreatedAt = $stmtTpCreated->get_result()->fetch_assoc()['created_at'] ?? null;
    $stmtTpCreated->close();
    $selectedTpCreatedDate = $tpCreatedAt ? date('Y-m-d', strtotime($tpCreatedAt)) : null;
}

// Same Effective-Date-defaults-to-onboarding-date fallback for CP.
$selectedCpCreatedDate = null;
if ($selectedCpId) {
    $stmtCpCreated = $db_conn->prepare("SELECT created_at FROM channel_partners WHERE id = ?");
    $stmtCpCreated->bind_param('i', $selectedCpId);
    $stmtCpCreated->execute();
    $cpCreatedAt = $stmtCpCreated->get_result()->fetch_assoc()['created_at'] ?? null;
    $stmtCpCreated->close();
    $selectedCpCreatedDate = $cpCreatedAt ? date('Y-m-d', strtotime($cpCreatedAt)) : null;
}

// Security Deposit and Approved Division(s) are likewise computed from
// whichever Division(s) "Partner Location" has assigned to this CP --
// Division is depth 4 in partner_location_nodes, each with its own
// deposit_amount (the Division-level equivalent of a Firka's target_amount
// for TPs, see below). This is only the DEFAULT shown in the (now editable)
// Security Deposit / Approved Division(s) inputs below -- Company can
// hand-adjust either field directly, and a "Recompute" button re-fills them
// from these computed values on demand; see manage-agreements-action.php's
// save_cp_schedule, which persists whatever was actually typed. Division
// Code(s), Stock Holding Capacity and Commercial/Purchase Category stay
// manual: there's no matching column anywhere (partner_location_nodes.code
// is defined but never actually populated for any Division in this
// install, and stock capacity/commercial category aren't location
// attributes at all -- they vary per CP's own negotiated terms).
$cpComputedDeposit = null;
$cpComputedDivisions = '';
if ($selectedCpId) {
    $stmtDep = $db_conn->prepare("
        SELECT pln.name AS division_name, pln.deposit_amount
        FROM channel_partner_locations cpl
        JOIN partner_location_nodes pln ON pln.id = cpl.location_id
        WHERE cpl.channel_partner_id = ?
        ORDER BY pln.name ASC
    ");
    $stmtDep->bind_param('i', $selectedCpId);
    $stmtDep->execute();
    $cpLocationRows = $stmtDep->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtDep->close();

    if ($cpLocationRows) {
        $sum = 0.0;
        $hasAny = false;
        $divisionNames = [];
        foreach ($cpLocationRows as $row) {
            if ($row['deposit_amount'] !== null) { $sum += (float) $row['deposit_amount']; $hasAny = true; }
            $divisionNames[] = $row['division_name'];
        }
        $cpComputedDeposit = $hasAny ? $sum : null;
        $cpComputedDivisions = implode(', ', $divisionNames);
    }
}

// Approved Monthly Purchase Commitment is no longer typed in by hand: it's
// the sum of target_amount across whichever Firka(s)/location(s) are
// actually assigned to this TP on the "Partner Location" page (same
// SUM(pln.target_amount) via territory_partner_locations join used
// elsewhere, e.g. company/get-tp-advance-balance.php) -- so it stays
// correct automatically as a TP's territory grows/shrinks instead of
// someone remembering to update a manually-entered figure here.
// Taluk/Block and Allotted Firka/Territory are likewise read off the same
// "Partner Location" assignment instead of being typed in -- Firka nodes
// (depth 6) sit directly under their Taluk (depth 5) in
// partner_location_nodes, so a TP's assigned Firka(s) also give us their
// Taluk(s) for free via that parent_id. Territory Code is simply the TP's
// own tp_id (e.g. "TP-0082"), already unique per TP -- no separate
// location-derived code to compute.
$tpComputedTarget = null;
$tpComputedTaluks = '';
$tpComputedFirkas = '';
if ($selectedTpId) {
    $stmtTgt = $db_conn->prepare("
        SELECT pln.name AS firka_name, pln.target_amount, taluk.name AS taluk_name
        FROM territory_partner_locations tpl
        JOIN partner_location_nodes pln ON pln.id = tpl.location_id
        LEFT JOIN partner_location_nodes taluk ON taluk.id = pln.parent_id
        WHERE tpl.territory_partner_id = ?
        ORDER BY pln.name ASC
    ");
    $stmtTgt->bind_param('i', $selectedTpId);
    $stmtTgt->execute();
    $tpLocationRows = $stmtTgt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtTgt->close();

    if ($tpLocationRows) {
        $sum = 0.0;
        $hasAny = false;
        $firkaNames = [];
        $talukNames = [];
        foreach ($tpLocationRows as $row) {
            if ($row['target_amount'] !== null) { $sum += (float) $row['target_amount']; $hasAny = true; }
            $firkaNames[] = $row['firka_name'];
            if (!empty($row['taluk_name'])) $talukNames[$row['taluk_name']] = true;
        }
        $tpComputedTarget = $hasAny ? $sum : null;
        $tpComputedFirkas = implode(', ', $firkaNames);
        $tpComputedTaluks = implode(', ', array_keys($talukNames));
    }
}
$tpSelectedCode = $selectedTpId ? ($tps[array_search($selectedTpId, array_column($tps, 'id'))]['tp_id'] ?? '') : '';

// Shared wording (clauses), editable via the "Edit Agreement Wording"
// modals below -- see shared/AgreementService.php's get_effective_agreement_body()
// for how this, a per-partner override, and the original hardcoded default
// all fit together.
$cpMasterBody = get_agreement_body_template($db_conn, 'channel_partner') ?? get_default_agreement_body('channel_partner');
$tpMasterBody = get_agreement_body_template($db_conn, 'territory_partner') ?? get_default_agreement_body('territory_partner');

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <style>
        .ql-editor{min-height:380px;font-size:13.5px;}
        .select2-container { width: 100% !important; max-width:420px; }
        .select2-container--default .select2-selection--single { height:38px; padding:4px 10px; border:1px solid #ced4da; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:28px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height:36px; }
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
                    <?php if (!empty($flashMsg)): ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:'success', title:'Success', text:<?php echo json_encode($flashMsg); ?>});</script>
                    <?php endif; ?>

                    <h1 style="font-size:20px;font-weight:600;color:#1f2937;margin-bottom:16px;">Manage Agreements</h1>

                    <div class="card mb-4">
                        <div class="card-header"><h5 class="card-title">Company Settings (shown on every CP/TP Agreement)</h5></div>
                        <div class="card-body">
                            <form method="post" action="manage-agreements-action.php" enctype="multipart/form-data" id="companySettingsForm">
                                <input type="hidden" name="action" value="save_settings">
                                <div class="mb-3">
                                    <label class="form-label">FEMI9 LLP Registered Office / Principal Place of Business</label>
                                    <textarea name="company_address" id="companyAddressInput" class="form-control" rows="2"><?php echo fv($settings['company_address']); ?></textarea>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label">Authorized Signatory Name</label>
                                        <input type="text" name="authorized_signatory_name" id="signatoryNameInput" class="form-control" value="<?php echo fv($settings['authorized_signatory_name']); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Designation</label>
                                        <input type="text" name="authorized_signatory_designation" id="signatoryDesignationInput" class="form-control" value="<?php echo fv($settings['authorized_signatory_designation']); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Signature (shown on every Agreement)</label>
                                        <?php if (!empty($settings['authorized_signatory_signature'])): ?>
                                        <div class="mb-1">
                                            <img src="company_signature/<?php echo fv($settings['authorized_signatory_signature']); ?>" alt="Current signature" style="max-height:44px;max-width:160px;border:1px solid #e5e7eb;border-radius:4px;padding:2px;">
                                            <div class="form-text">Choose a file below only to replace it.</div>
                                        </div>
                                        <?php endif; ?>
                                        <input type="file" name="authorized_signatory_signature" class="form-control" accept=".jpg,.jpeg,.png">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary">Save Company Settings</button>
                                    </div>
                                </div>
                            </form>
                            <hr>
                            <p class="text-muted mb-2" style="font-size:12.5px;">See how the fields above (even before you click Save) will actually look on a partner's agreement — uses a fake "Demo" partner, nothing is saved or sent to anyone real.</p>
                            <button type="button" class="btn btn-info btn-sm text-white" onclick="openDemoPreview('tp')">&#128065; Preview Demo TP Agreement</button>
                            <button type="button" class="btn btn-info btn-sm text-white" onclick="openDemoPreview('cp')">&#128065; Preview Demo CP Agreement</button>
                            <hr>
                            <p class="text-muted mb-2" style="font-size:12.5px;">
                                Off by default: a TP/CP can log in and use the portal normally, agreement untouched.
                                Once switched ON below, any TP/CP who hasn't signed yet is forced straight to the
                                Agreement page on every login until they sign — same as before this existed.
                            </p>
                            <form method="post" action="manage-agreements-action.php" class="d-inline" onsubmit="return confirm(<?php echo $settings['tp_sign_enforced'] ? "'Turn OFF Work All TPs? Territory Partners will be able to use the portal normally again without signing.'" : "'Turn ON Work All TPs? Every Territory Partner who has not signed yet will be forced straight to the Agreement page on their next login.'"; ?>);">
                                <input type="hidden" name="action" value="toggle_tp_sign_enforced">
                                <input type="hidden" name="enforced" value="<?php echo $settings['tp_sign_enforced'] ? '0' : '1'; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $settings['tp_sign_enforced'] ? 'btn-success' : 'btn-outline-secondary'; ?>">
                                    Work All TPs: <?php echo $settings['tp_sign_enforced'] ? 'ON' : 'OFF'; ?>
                                </button>
                            </form>
                            <form method="post" action="manage-agreements-action.php" class="d-inline" onsubmit="return confirm(<?php echo $settings['cp_sign_enforced'] ? "'Turn OFF Work All CPs? Channel Partners will be able to use the portal normally again without signing.'" : "'Turn ON Work All CPs? Every Channel Partner who has not signed yet will be forced straight to the Agreement page on their next login.'"; ?>);">
                                <input type="hidden" name="action" value="toggle_cp_sign_enforced">
                                <input type="hidden" name="enforced" value="<?php echo $settings['cp_sign_enforced'] ? '0' : '1'; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $settings['cp_sign_enforced'] ? 'btn-success' : 'btn-outline-secondary'; ?>">
                                    Work All CPs: <?php echo $settings['cp_sign_enforced'] ? 'ON' : 'OFF'; ?>
                                </button>
                            </form>
                            <form method="post" action="demo-agreement-preview.php" target="_blank" id="demoPreviewForm" style="display:none;">
                                <input type="hidden" name="type" id="demoPreviewType">
                                <input type="hidden" name="company_address" id="demoPreviewAddress">
                                <input type="hidden" name="authorized_signatory_name" id="demoPreviewSigName">
                                <input type="hidden" name="authorized_signatory_designation" id="demoPreviewSigDesignation">
                            </form>
                            <script>
                            function openDemoPreview(type) {
                                document.getElementById('demoPreviewType').value = type;
                                document.getElementById('demoPreviewAddress').value = document.getElementById('companyAddressInput').value;
                                document.getElementById('demoPreviewSigName').value = document.getElementById('signatoryNameInput').value;
                                document.getElementById('demoPreviewSigDesignation').value = document.getElementById('signatoryDesignationInput').value;
                                document.getElementById('demoPreviewForm').submit();
                            }
                            </script>
                        </div>
                    </div>

                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item"><button class="nav-link<?php echo $activeTab === 'cp' ? ' active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#cpPane" type="button">Channel Partners <span class="badge bg-success ms-1" role="button" onclick="event.stopPropagation(); openAgreementSignersModal('cp','signed');"><?php echo $cpSignedCount; ?> signed</span> <span class="badge bg-secondary" role="button" onclick="event.stopPropagation(); openAgreementSignersModal('cp','pending');"><?php echo $cpTotalCount - $cpSignedCount; ?> pending</span></button></li>
                        <li class="nav-item"><button class="nav-link<?php echo $activeTab === 'tp' ? ' active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tpPane" type="button">Territory Partners <span class="badge bg-success ms-1" role="button" onclick="event.stopPropagation(); openAgreementSignersModal('tp','signed');"><?php echo $tpSignedCount; ?> signed</span> <span class="badge bg-secondary" role="button" onclick="event.stopPropagation(); openAgreementSignersModal('tp','pending');"><?php echo $tpTotalCount - $tpSignedCount; ?> pending</span></button></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade<?php echo $activeTab === 'cp' ? ' show active' : ''; ?>" id="cpPane">
                            <div class="card mb-3">
                                <div class="card-body d-flex justify-content-between align-items-end flex-wrap gap-3">
                                    <form method="get" class="mb-0">
                                        <label class="form-label">Select Channel Partner</label>
                                        <select name="cp_id" id="cpSelect" class="form-control" onchange="this.form.submit()" style="max-width:420px;">
                                            <option value="0">-- Select --</option>
                                            <?php foreach ($cps as $c): ?>
                                            <option value="<?php echo (int) $c['id']; ?>" <?php echo $selectedCpId === (int) $c['id'] ? 'selected' : ''; ?>>
                                                <?php echo fv($c['name'] . ' (' . $c['cp_id'] . ')' . ($c['company_name'] ? ' — ' . $c['company_name'] : '')); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#cpMasterWordingModal">Edit Agreement Wording (CP)</button>
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
                                    <div class="d-flex gap-2">
                                        <?php if ((int) $cpAgreement['is_locked'] === 1 && !empty($cpAgreement['cp_signature'])): ?>
                                        <a href="view-cp-digi-card.php?cp_id=<?php echo (int) $selectedCpId; ?>&download=1" class="btn btn-sm" style="background:#7c3aed;color:#fff;">Download CP Card</a>
                                        <?php endif; ?>
                                        <a href="view-cp-agreement.php?cp_id=<?php echo (int) $selectedCpId; ?>" class="btn btn-outline-primary btn-sm">View Full Agreement</a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="manage-agreements-action.php">
                                        <input type="hidden" name="action" value="save_cp_schedule">
                                        <input type="hidden" name="cp_id" value="<?php echo (int) $selectedCpId; ?>">
                                        <input type="hidden" name="use_custom_body" id="cpUseCustomBodyInput" value="<?php echo !empty($cpAgreement['custom_body_html']) ? '1' : '0'; ?>">
                                        <input type="hidden" name="custom_body_html" id="cpCustomBodyHtmlInput" value="<?php echo fv($cpAgreement['custom_body_html']); ?>">
                                        <div class="mb-3">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#cpOverrideWordingModal">
                                                <?php echo !empty($cpAgreement['custom_body_html']) ? 'Edit This CP\'s Custom Wording (active)' : 'Set Custom Wording for This CP Only'; ?>
                                            </button>
                                            <?php if (!empty($cpAgreement['custom_body_html'])): ?>
                                            <span class="badge bg-info text-dark ms-1">Using custom wording, not the shared template</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Security Deposit (Rs.)</label>
                                                <input type="number" step="0.01" name="security_deposit" class="form-control" id="cpDepositInput"
                                                    value="<?php echo $cpAgreement['security_deposit'] !== null ? fv($cpAgreement['security_deposit']) : ($cpComputedDeposit !== null ? fv($cpComputedDeposit) : ''); ?>"
                                                    data-computed="<?php echo $cpComputedDeposit !== null ? fv($cpComputedDeposit) : ''; ?>">
                                                <div class="form-text">Editable — defaults to the sum of the Deposit Amount on each Division assigned under "Partner Location". Use "Recompute" below to re-sync if that assignment changes.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Approved Stock Holding Capacity</label>
                                                <input type="text" name="stock_holding_capacity" class="form-control" value="<?php echo fv($cpAgreement['stock_holding_capacity']); ?>">
                                                <div class="form-text">Not location-derived — no Division attribute for this, set it per CP as agreed.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Applicable Commercial / Purchase Category</label>
                                                <input type="text" name="commercial_category" class="form-control" value="<?php echo fv($cpAgreement['commercial_category']); ?>">
                                                <div class="form-text">Not location-derived — no Division attribute for this, set it per CP as agreed.</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Approved Division(s)</label>
                                                <textarea name="approved_divisions" class="form-control" rows="2" id="cpDivisionsInput"
                                                    data-computed="<?php echo fv($cpComputedDivisions); ?>"><?php echo fv($cpAgreement['approved_divisions'] ?? $cpComputedDivisions); ?></textarea>
                                                <div class="form-text">Editable — defaults to the Division(s) assigned to this CP under "Partner Location".</div>
                                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;font-weight:600;" onclick="cpRecomputeFromPartnerLocation()">&#8635; Recompute Deposit / Division from Partner Location</button>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Division Code(s)</label>
                                                <textarea name="division_codes" class="form-control" rows="2"><?php echo fv($cpAgreement['division_codes']); ?></textarea>
                                                <div class="form-text">Not auto-fillable — Divisions in "Partner Location" don't have a code set on them yet.</div>
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">Approved Warehouse Address</label>
                                                <textarea name="approved_warehouse_address" class="form-control" rows="2"><?php echo fv($cpAgreement['approved_warehouse_address']); ?></textarea>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Effective Date</label>
                                                <input type="date" name="effective_date" class="form-control" value="<?php echo fv($cpAgreement['effective_date'] ?: $selectedCpCreatedDate); ?>">
                                                <?php if (empty($cpAgreement['effective_date']) && $selectedCpCreatedDate): ?>
                                                <div class="form-text">Defaulted to this CP's onboarding date — change if the agreement actually took effect on a different date.</div>
                                                <?php endif; ?>
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
                                <div class="card-body d-flex justify-content-between align-items-end flex-wrap gap-3">
                                    <form method="get" class="mb-0">
                                        <label class="form-label">Select Territory Partner</label>
                                        <select name="tp_id" id="tpSelect" class="form-control" onchange="this.form.submit()" style="max-width:420px;">
                                            <option value="0">-- Select --</option>
                                            <?php foreach ($tps as $t): ?>
                                            <option value="<?php echo (int) $t['id']; ?>" <?php echo $selectedTpId === (int) $t['id'] ? 'selected' : ''; ?>>
                                                <?php echo fv($t['name'] . ' (' . $t['tp_id'] . ')' . ($t['company_name'] ? ' — ' . $t['company_name'] : '')); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#tpMasterWordingModal">Edit Agreement Wording (TP)</button>
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
                                    <div class="d-flex gap-2">
                                        <?php if ((int) $tpAgreement['is_locked'] === 1 && !empty($tpAgreement['tp_signature'])): ?>
                                        <a href="view-tp-digi-card.php?tp_id=<?php echo (int) $selectedTpId; ?>&download=1" class="btn btn-sm" style="background:#7c3aed;color:#fff;">Download TP Card</a>
                                        <?php endif; ?>
                                        <a href="view-tp-agreement.php?tp_id=<?php echo (int) $selectedTpId; ?>" class="btn btn-outline-primary btn-sm">View Full Agreement</a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="manage-agreements-action.php">
                                        <input type="hidden" name="action" value="save_tp_schedule">
                                        <input type="hidden" name="tp_id" value="<?php echo (int) $selectedTpId; ?>">
                                        <input type="hidden" name="use_custom_body" id="tpUseCustomBodyInput" value="<?php echo !empty($tpAgreement['custom_body_html']) ? '1' : '0'; ?>">
                                        <input type="hidden" name="custom_body_html" id="tpCustomBodyHtmlInput" value="<?php echo fv($tpAgreement['custom_body_html']); ?>">
                                        <div class="mb-3">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#tpOverrideWordingModal">
                                                <?php echo !empty($tpAgreement['custom_body_html']) ? 'Edit This TP\'s Custom Wording (active)' : 'Set Custom Wording for This TP Only'; ?>
                                            </button>
                                            <?php if (!empty($tpAgreement['custom_body_html'])): ?>
                                            <span class="badge bg-info text-dark ms-1">Using custom wording, not the shared template</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Approved Monthly Purchase Commitment (Rs.)</label>
                                                <input type="number" step="0.01" name="monthly_purchase_commitment" class="form-control" id="tpCommitmentInput"
                                                    value="<?php echo $tpAgreement['monthly_purchase_commitment'] !== null ? fv($tpAgreement['monthly_purchase_commitment']) : ($tpComputedTarget !== null ? fv($tpComputedTarget) : ''); ?>"
                                                    data-computed="<?php echo $tpComputedTarget !== null ? fv($tpComputedTarget) : ''; ?>">
                                                <div class="form-text">Editable — defaults to the sum of the Target Amount on each Firka/location assigned under "Partner Location". Use "Recompute" below to re-sync if that assignment changes.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Taluk / Block</label>
                                                <input type="text" name="taluk_block" class="form-control" id="tpTalukInput"
                                                    value="<?php echo fv($tpAgreement['taluk_block'] ?? $tpComputedTaluks); ?>"
                                                    data-computed="<?php echo fv($tpComputedTaluks); ?>">
                                                <div class="form-text">Editable — defaults to the Taluk each assigned Firka belongs to.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Effective Date</label>
                                                <input type="date" name="effective_date" class="form-control" value="<?php echo fv($tpAgreement['effective_date'] ?: $selectedTpCreatedDate); ?>">
                                                <?php if (empty($tpAgreement['effective_date']) && $selectedTpCreatedDate): ?>
                                                <div class="form-text">Defaulted to this TP's onboarding date — change if the agreement actually took effect on a different date.</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Allotted Firka / Territory</label>
                                                <textarea name="territory_firka" class="form-control" rows="2" id="tpFirkaInput"
                                                    data-computed="<?php echo fv($tpComputedFirkas); ?>"><?php echo fv($tpAgreement['territory_firka'] ?? $tpComputedFirkas); ?></textarea>
                                                <div class="form-text">Editable — defaults to the Firka(s) assigned to this TP under "Partner Location".</div>
                                                <button type="button" class="btn btn-sm mt-2" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;font-weight:600;" onclick="tpRecomputeFromPartnerLocation()">
                                                    &#8635; Recompute Target / Taluk / Firka from Partner Location
                                                </button>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Territory Code</label>
                                                <input type="text" class="form-control" value="<?php echo fv($tpSelectedCode); ?>" readonly disabled>
                                                <div class="form-text">This TP's own TP ID.</div>
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

<!-- Shared master wording editors (apply to every CP / TP of that type) -->
<div class="modal fade" id="cpMasterWordingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Agreement Wording — Channel Partner <small class="text-muted">(shared by all Channel Partners)</small></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:12.5px;">Saving this unlocks every already-signed CP agreement for re-signing, since the wording they signed no longer matches.</p>
                <?php if ($cpsWithCustomWording): ?>
                <div class="mb-3 p-3" style="background:#fff8e1;border:1px solid #f0d78c;border-radius:8px;">
                    <p class="mb-2" style="font-size:12.5px;"><strong><?php echo count($cpsWithCustomWording); ?> CP(s) have their own custom wording</strong> — this common change won't reach them (their custom wording always wins). Tick any you want switched back to this shared wording instead:</p>
                    <?php foreach ($cpsWithCustomWording as $c): ?>
                    <div class="form-check">
                        <input class="form-check-input cp-custom-wording-reset" type="checkbox" value="<?php echo (int) $c['id']; ?>" id="cpCustomReset<?php echo (int) $c['id']; ?>">
                        <label class="form-check-label" for="cpCustomReset<?php echo (int) $c['id']; ?>"><?php echo fv($c['name'] . ' (' . $c['cp_id'] . ')'); ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div id="cpMasterEditor" style="min-height:420px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="
                    document.getElementById('cpMasterBodyHtml').value = cpMasterQuill.root.innerHTML;
                    var ids = Array.from(document.querySelectorAll('.cp-custom-wording-reset:checked')).map(function(el){ return el.value; });
                    document.getElementById('cpResetCustomIds').value = ids.join(',');
                    document.getElementById('cpMasterWordingForm').submit();
                ">Save Wording (applies to all CPs)</button>
            </div>
        </div>
    </div>
</div>
<form method="post" action="manage-agreements-action.php" id="cpMasterWordingForm" style="display:none;">
    <input type="hidden" name="action" value="save_cp_body_template">
    <input type="hidden" name="body_html" id="cpMasterBodyHtml">
    <input type="hidden" name="reset_custom_ids" id="cpResetCustomIds">
</form>

<div class="modal fade" id="tpMasterWordingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Agreement Wording — Territory Partner <small class="text-muted">(shared by all Territory Partners)</small></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:12.5px;">Saving this unlocks every already-signed TP agreement for re-signing, since the wording they signed no longer matches.</p>
                <?php if ($tpsWithCustomWording): ?>
                <div class="mb-3 p-3" style="background:#fff8e1;border:1px solid #f0d78c;border-radius:8px;">
                    <p class="mb-2" style="font-size:12.5px;"><strong><?php echo count($tpsWithCustomWording); ?> TP(s) have their own custom wording</strong> — this common change won't reach them (their custom wording always wins). Tick any you want switched back to this shared wording instead:</p>
                    <?php foreach ($tpsWithCustomWording as $t): ?>
                    <div class="form-check">
                        <input class="form-check-input tp-custom-wording-reset" type="checkbox" value="<?php echo (int) $t['id']; ?>" id="tpCustomReset<?php echo (int) $t['id']; ?>">
                        <label class="form-check-label" for="tpCustomReset<?php echo (int) $t['id']; ?>"><?php echo fv($t['name'] . ' (' . $t['tp_id'] . ')'); ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div id="tpMasterEditor" style="min-height:420px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="
                    document.getElementById('tpMasterBodyHtml').value = tpMasterQuill.root.innerHTML;
                    var ids = Array.from(document.querySelectorAll('.tp-custom-wording-reset:checked')).map(function(el){ return el.value; });
                    document.getElementById('tpResetCustomIds').value = ids.join(',');
                    document.getElementById('tpMasterWordingForm').submit();
                ">Save Wording (applies to all TPs)</button>
            </div>
        </div>
    </div>
</div>
<form method="post" action="manage-agreements-action.php" id="tpMasterWordingForm" style="display:none;">
    <input type="hidden" name="action" value="save_tp_body_template">
    <input type="hidden" name="body_html" id="tpMasterBodyHtml">
    <input type="hidden" name="reset_custom_ids" id="tpResetCustomIds">
</form>

<?php if ($cpAgreement): ?>
<!-- Per-partner override -- Apply only fills the hidden fields in the main
     Schedule-1 form above; the partner's own Save button still submits it. -->
<div class="modal fade" id="cpOverrideWordingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Custom Wording — <?php echo fv($cps[array_search($selectedCpId, array_column($cps, 'id'))]['name'] ?? 'this Channel Partner'); ?> <small class="text-muted">(this CP only)</small></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="cpOverrideEnabled" <?php echo !empty($cpAgreement['custom_body_html']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="cpOverrideEnabled">Use this custom wording for this Channel Partner instead of the shared template</label>
                </div>
                <div id="cpOverrideEditor"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="
                    document.getElementById('cpUseCustomBodyInput').value = document.getElementById('cpOverrideEnabled').checked ? '1' : '0';
                    document.getElementById('cpCustomBodyHtmlInput').value = cpOverrideQuill.root.innerHTML;
                ">Apply (then click Save on the Schedule-1 form)</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($tpAgreement): ?>
<div class="modal fade" id="tpOverrideWordingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Custom Wording — <?php echo fv($tps[array_search($selectedTpId, array_column($tps, 'id'))]['name'] ?? 'this Territory Partner'); ?> <small class="text-muted">(this TP only)</small></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="tpOverrideEnabled" <?php echo !empty($tpAgreement['custom_body_html']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="tpOverrideEnabled">Use this custom wording for this Territory Partner instead of the shared template</label>
                </div>
                <div id="tpOverrideEditor"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="
                    document.getElementById('tpUseCustomBodyInput').value = document.getElementById('tpOverrideEnabled').checked ? '1' : '0';
                    document.getElementById('tpCustomBodyHtmlInput').value = tpOverrideQuill.root.innerHTML;
                ">Apply (then click Save on the Schedule-1 form)</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Behind the "N signed" / "N pending" badges on each tab -- same list,
     just filtered by status, so one modal + one AJAX endpoint
     (agreement-signers-list.php) serves all four badges. -->
<div class="modal fade" id="agreementSignersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="agreementSignersModalTitle">&nbsp;</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="agreementSignersLoading" class="text-center text-muted py-4" style="display:none;">Loading…</div>
                <div id="agreementSignersEmpty" class="text-center text-muted py-4" style="display:none;">No partners in this list.</div>
                <table class="table table-sm table-hover" id="agreementSignersTable" style="display:none;">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>ID</th>
                            <th>Company</th>
                            <th id="agreementSignersDateCol">Signed On</th>
                        </tr>
                    </thead>
                    <tbody id="agreementSignersTbody"></tbody>
                </table>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="text-muted" id="agreementSignersCount" style="font-size:12.5px;"></span>
                <nav><ul class="pagination pagination-sm mb-0" id="agreementSignersPagination"></ul></nav>
            </div>
        </div>
    </div>
</div>
<script>
    var agreementSignersState = { type: 'cp', status: 'signed', page: 1 };

    function openAgreementSignersModal(type, status) {
        agreementSignersState = { type: type, status: status, page: 1 };
        var modalEl = document.getElementById('agreementSignersModal');
        // This page's Bootstrap build is v5.0.0-beta3 -- Modal.getOrCreateInstance()
        // was only added in 5.2, so it's not a function here and threw silently
        // (no console-visible crash from an inline onclick), which is why the
        // modal never opened. Modal.getInstance() (present since 5.0) + manual
        // fallback does the same job on this version.
        var modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modalInstance.show();
        loadAgreementSignersPage();
    }

    function loadAgreementSignersPage() {
        var typeLabel = agreementSignersState.type === 'cp' ? 'Channel Partner' : 'Territory Partner';
        var statusLabel = agreementSignersState.status === 'signed' ? 'Signed' : 'Pending';
        document.getElementById('agreementSignersModalTitle').textContent = typeLabel + 's — ' + statusLabel;
        document.getElementById('agreementSignersDateCol').textContent = agreementSignersState.status === 'signed' ? 'Signed On' : 'Status';

        var $loading = $('#agreementSignersLoading'), $empty = $('#agreementSignersEmpty'), $table = $('#agreementSignersTable');
        $table.hide(); $empty.hide(); $loading.show();
        $('#agreementSignersTbody').empty();
        $('#agreementSignersPagination').empty();
        $('#agreementSignersCount').text('');

        $.getJSON('agreement-signers-list.php', { type: agreementSignersState.type, status: agreementSignersState.status, page: agreementSignersState.page })
            .done(function (data) {
                $loading.hide();
                if (!data.rows || !data.rows.length) { $empty.show(); return; }

                var $tbody = $('#agreementSignersTbody');
                data.rows.forEach(function (r) {
                    var dateCell = agreementSignersState.status === 'signed'
                        ? (r.signed_at || '-')
                        : '<span class="badge bg-secondary">Not signed yet</span>';
                    $tbody.append(
                        '<tr>' +
                        '<td><a href="' + r.edit_url + '">' + $('<div>').text(r.name || '-').html() + '</a></td>' +
                        '<td>' + $('<div>').text(r.code || '-').html() + '</td>' +
                        '<td>' + $('<div>').text(r.company_name || '-').html() + '</td>' +
                        '<td>' + dateCell + '</td>' +
                        '</tr>'
                    );
                });
                $table.show();

                $('#agreementSignersCount').text('Showing ' + ((data.page - 1) * data.per_page + 1) + '-' + Math.min(data.page * data.per_page, data.total) + ' of ' + data.total);

                var $pg = $('#agreementSignersPagination');
                for (var p = 1; p <= data.total_pages; p++) {
                    $pg.append(
                        '<li class="page-item' + (p === data.page ? ' active' : '') + '">' +
                        '<a class="page-link" href="#" onclick="event.preventDefault(); agreementSignersState.page=' + p + '; loadAgreementSignersPage();">' + p + '</a>' +
                        '</li>'
                    );
                }
            })
            .fail(function () {
                $loading.hide();
                $empty.text('Failed to load — please try again.').show();
            });
    }
</script>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script>
    $('#cpSelect').select2({ placeholder: 'Search by name or CP ID…', allowClear: false });
    $('#tpSelect').select2({ placeholder: 'Search by name or TP ID…', allowClear: false });
</script>
<script>
    // Re-fills the three Schedule-1 fields from "Partner Location" without
    // reloading the page -- still just a form fill, so Save must be clicked
    // afterwards to persist it. Pulled from each field's own data-computed
    // attribute (rendered server-side from the same query that used to force
    // these fields read-only), not re-fetched, so this can't silently pick
    // up a different TP's data if the dropdown changes without a reload.
    function tpRecomputeFromPartnerLocation() {
        var commitmentInput = document.getElementById('tpCommitmentInput');
        var talukInput = document.getElementById('tpTalukInput');
        var firkaInput = document.getElementById('tpFirkaInput');
        if (commitmentInput) commitmentInput.value = commitmentInput.dataset.computed || '';
        if (talukInput) talukInput.value = talukInput.dataset.computed || '';
        if (firkaInput) firkaInput.value = firkaInput.dataset.computed || '';
    }

    // Same re-fill as tpRecomputeFromPartnerLocation() above, for CP's
    // Security Deposit / Approved Division(s).
    function cpRecomputeFromPartnerLocation() {
        var depositInput = document.getElementById('cpDepositInput');
        var divisionsInput = document.getElementById('cpDivisionsInput');
        if (depositInput) depositInput.value = depositInput.dataset.computed || '';
        if (divisionsInput) divisionsInput.value = divisionsInput.dataset.computed || '';
    }
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script>
// Quill editors are created lazily on each modal's first "shown" event --
// creating them while the modal is still display:none gives Quill a
// zero-width container to measure, which breaks its toolbar/layout.
var cpMasterQuill, tpMasterQuill, cpOverrideQuill, tpOverrideQuill;

document.getElementById('cpMasterWordingModal').addEventListener('shown.bs.modal', function () {
    if (!cpMasterQuill) {
        // The custom-wording checklist box below the editor (when any CP
        // has one) makes this modal's content taller than before, so give
        // the modal's own open transition + modal-dialog-scrollable one
        // extra frame to settle before Quill measures #cpMasterEditor --
        // otherwise Quill can read a transient zero/partial height mid-
        // transition and render its toolbar/content area collapsed, which
        // looked like the editor "flashing" and then disappearing.
        setTimeout(function () {
            cpMasterQuill = new Quill('#cpMasterEditor', { theme: 'snow' });
            cpMasterQuill.root.innerHTML = <?php echo json_encode($cpMasterBody); ?>;
        }, 50);
    }
});
document.getElementById('tpMasterWordingModal').addEventListener('shown.bs.modal', function () {
    if (!tpMasterQuill) {
        // See cpMasterWordingModal's identical handler above for why this
        // is deferred a frame instead of running synchronously.
        setTimeout(function () {
            tpMasterQuill = new Quill('#tpMasterEditor', { theme: 'snow' });
            tpMasterQuill.root.innerHTML = <?php echo json_encode($tpMasterBody); ?>;
        }, 50);
    }
});
<?php if ($cpAgreement): ?>
document.getElementById('cpOverrideWordingModal').addEventListener('shown.bs.modal', function () {
    if (!cpOverrideQuill) {
        cpOverrideQuill = new Quill('#cpOverrideEditor', { theme: 'snow' });
        cpOverrideQuill.root.innerHTML = <?php echo json_encode($cpAgreement['custom_body_html'] ?: $cpMasterBody); ?>;
    }
});
<?php endif; ?>
<?php if ($tpAgreement): ?>
document.getElementById('tpOverrideWordingModal').addEventListener('shown.bs.modal', function () {
    if (!tpOverrideQuill) {
        tpOverrideQuill = new Quill('#tpOverrideEditor', { theme: 'snow' });
        tpOverrideQuill.root.innerHTML = <?php echo json_encode($tpAgreement['custom_body_html'] ?: $tpMasterBody); ?>;
    }
});
<?php endif; ?>
</script>
</body>
</html>
