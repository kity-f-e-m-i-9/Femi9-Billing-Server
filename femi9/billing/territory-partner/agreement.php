<?php
include("checksession.php");
require_once __DIR__ . '/../shared/AgreementService.php';
include("config.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$tp_id = (int) $Login_user_IDvl;

$stmtTp = $db_conn->prepare("SELECT * FROM territory_partners WHERE id = ?");
$stmtTp->bind_param('i', $tp_id);
$stmtTp->execute();
$tp = $stmtTp->get_result()->fetch_assoc();
$stmtTp->close();

$settings  = get_agreement_settings($db_conn);
$agreement = get_or_create_tp_agreement($db_conn, $tp_id);
$isLocked  = (int) ($agreement['is_locked'] ?? 0) === 1;
// Signed once, then company edited Schedule-1 after that signature --
// highlight it so the TP notices what needs re-review instead of having to
// spot the change themselves (see agreement_needs_resign() for the exact
// condition; same one driving the dashboard's "please review & sign again"
// banner).
$scheduleChanged = agreement_needs_resign($agreement);

// Which of the TP's own profile fields (name/business name/address/mobile/
// email) the Company has changed since the TP's last signature — each gets
// its own yellow highlight below instead of the whole-block Schedule-1 style
// highlight, since these fields appear individually rather than as one group.
$profileChanged = get_profile_change_flags($tp, $agreement);

if (isset($_SESSION['sucMessage'])) { $flashMsg = $_SESSION['sucMessage']; unset($_SESSION['sucMessage']); }
if (isset($_SESSION['errorMessage'])) { $flashErr = $_SESSION['errorMessage']; unset($_SESSION['errorMessage']); }

function fv($val) { return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8'); }

// Printing the dashboard-embedded page directly is unreliable: the fixed
// letterhead header/footer (meant to repeat on every printed page) gets
// mis-positioned by Chrome once nested inside the dashboard's own
// positioned wrappers (sidebar/app-container), overlapping body text from
// the second page onward. So "Print / Save as PDF" instead opens this same
// URL with ?print=1, which renders a bare standalone document (just the
// agreement content, no dashboard chrome at all) where the repeating fixed
// header/footer behaves correctly, then auto-triggers window.print(). Only
// available once locked (read-only) -- there's nothing meaningful to print
// before the TP has signed.
$printMode = $isLocked && isset($_GET['print']);
// The yellow "changed since signing" highlighting is an on-screen review aid
// only -- a printed/saved PDF agreement must read as a clean, final document,
// so every highlight source (profile fields, Schedule-1, body wording) is
// switched off here rather than in each render site.
if ($printMode) {
    $profileChanged = array_fill_keys(array_keys($profileChanged), false);
    $scheduleChanged = false;
}
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Territory Partner Agreement : <?php echo fv($business_name ?? 'Femi9'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .agr-doc { max-width:820px; margin:0 auto; background:#fff; padding:36px 44px; border:1px solid #e5e7eb; border-radius:10px; font-size:13.5px; line-height:1.6; color:#1f2937; }
        .agr-doc h1 { font-size:18px; text-align:center; margin-bottom:20px; }
        .agr-doc h2 { font-size:14.5px; margin:22px 0 8px; break-after:avoid; page-break-after:avoid; } .agr-doc p { orphans:3; widows:3; }
        .agr-letterhead-img { width:100%; height:auto; display:block; }
        .agr-letterhead { margin-bottom:16px; }
        .agr-letterhead-footer { margin-top:30px; }
        .agr-field-row { display:flex; align-items:center; gap:8px; margin:10px 0; flex-wrap:wrap; }
        .agr-field-row label { font-weight:600; min-width:150px; }
        .agr-field-row input, .agr-field-row .agr-value { flex:1; min-width:180px; border:none; border-bottom:1px solid #9ca3af; padding:2px 4px; background:transparent; }
        .agr-value { color:#374151; }
        .agr-value.agr-blank { color:#9ca3af; font-style:italic; }
        .agr-ul { margin:8px 0 8px 20px; }
        .agr-sig-block { margin-top:14px; padding-top:10px; border-top:1px dashed #e5e7eb; }
        .agr-sig-img { max-width:200px; max-height:44px; object-fit:contain; border-bottom:1px solid #9ca3af; }
        .agr-pan-img { max-width:320px; max-height:220px; display:block; margin-top:6px; border:1px solid #e5e7eb; border-radius:4px; }
        @media print {
            .no-print { display:none !important; }
            /* Reset the dashboard chrome's own positioned/offset wrappers
               (app-container is position:relative + margin-left:280px for
               the sidebar) -- left as-is, Chrome recalculates the fixed
               letterhead's position against these per page, producing a
               different (wrong) offset on every printed page beyond the
               first. Flattening them back to static/zero keeps the fixed
               header/footer anchored to the actual page box on every page. */
            .app, .app-container, .app-content, .content-wrapper, .container-fluid, #agreementForm {
                position:static !important; margin:0 !important; padding:0 !important;
                width:auto !important; max-width:none !important; float:none !important;
            }
            .agr-doc { border:none; padding:0; max-width:none; margin:0; }
            @page { size: A4; margin: 175px 42px 105px 42px; }
            .agr-letterhead { position:fixed; top:0; left:0; right:0; }
            .agr-letterhead-footer { position:fixed; bottom:0; left:0; right:0; margin-top:0; }
        }
        .agr-locked-banner { background:#f0fdf4; border:1px solid #bbf7d0; color:#065f46; border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px; }
        .agr-schedule-changed { background:#ffd400; border:1px solid #c9a600; border-radius:8px; padding:12px 16px; margin:10px 0; }
        .agr-schedule-changed .agr-field-row label, .agr-schedule-changed .agr-value { color:#1f2937; }
        .agr-schedule-changed .agr-field-row { margin:8px 0; }
        .agr-field-row.agr-row-changed { background:#ffd400; border:1px solid #c9a600; border-radius:8px; padding:8px 12px; }
        .agr-field-row.agr-row-changed label, .agr-field-row.agr-row-changed .agr-value { color:#1f2937; }
        .agr-wording-changed { background:#ffd400; border:1px solid #c9a600; border-radius:8px; padding:4px 14px; margin:10px 0; }
        .agr-word-changed { background:#ffd400; border-radius:3px; padding:0 2px; box-shadow:0 0 0 1px #c9a600; }
        @media (max-width: 575.98px) {
            .agr-doc { padding:18px 16px; font-size:13px; }
            .agr-field-row { flex-direction:column; align-items:flex-start; gap:2px; }
            .agr-field-row label { min-width:0; }
            .agr-field-row input, .agr-field-row .agr-value { width:100%; min-width:0; }
            .sig-draw-pane canvas, .sig-type-pane canvas { max-width:100%; }
        }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar no-print">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <div class="no-print"><?php include("app-header.php"); ?></div>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <?php if (!empty($flashMsg)): ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:'success', title:'Success', text:<?php echo json_encode($flashMsg); ?>});</script>
                    <?php endif; ?>
                    <?php if (!empty($flashErr)): ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:'error', title:'Error', text:<?php echo json_encode($flashErr); ?>});</script>
                    <?php endif; ?>

                    <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                        <h1 style="font-size:20px;font-weight:600;color:#1f2937;margin:0;">Territory Partner Agreement</h1>
                        <?php if ($isLocked): ?>
                        <div style="display:flex;gap:8px;">
                        <?php if (!empty($agreement['tp_signature'])): ?>
                        <button type="button" class="btn btn-sm" style="background:#7c3aed;color:#fff;" onclick="window.open('digi-card.php', '_blank')">View Your TP Card</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.open('agreement.php?print=1', '_blank')">Print / Save as PDF</button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($isLocked): ?>
                    <div class="agr-locked-banner no-print">
                        This agreement was signed and submitted on <?php echo fv(date('d-M-Y h:i A', strtotime($agreement['signed_at']))); ?> and is now read-only.
                    </div>
                    <?php endif; ?>

                    <form method="post" action="agreement-action.php" id="agreementForm" enctype="multipart/form-data" onsubmit="return prepareSignaturesAndValidate();">
                    <input type="hidden" name="csrf_token" value="<?php echo fv($_SESSION['csrf_token']); ?>">
                    <?php ob_start(); ?>
                    <div class="agr-doc">
                        <?php if (!$printMode): ?><?php render_agreement_letterhead_header(); ?><?php endif; ?>
                        <h1>FEMI9 LLP - TERRITORY PARTNER AGREEMENT</h1>

                        <p>This Territory Partner Agreement (hereinafter referred to as the "Agreement") is made and executed on the date and at the place specified below and shall become effective from the Effective Date recorded in Schedule&nbsp;&ndash;&nbsp;1.</p>

                        <div class="agr-field-row"><label>DATE:</label>
                            <?php if ($isLocked): ?><span class="agr-value"><?php echo fv(date('d-m-Y', strtotime($agreement['agreement_date'] ?? 'now'))); ?></span>
                            <?php else: ?><input type="date" name="agreement_date" value="<?php echo fv($agreement['agreement_date'] ?: date('Y-m-d')); ?>" required>
                            <?php endif; ?>
                        </div>
                        <div class="agr-field-row"><label>PLACE:</label>
                            <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['agreement_place']); ?></span>
                            <?php else: ?><input type="text" name="agreement_place" value="<?php echo fv($agreement['agreement_place']); ?>" required>
                            <?php endif; ?>
                        </div>
                        <div class="agr-field-row"><label>Approved Initial Monthly Purchase Commitment:</label>
                            <span class="agr-value<?php echo $agreement['monthly_purchase_commitment'] === null ? ' agr-blank' : ''; ?>">
                                <?php echo $agreement['monthly_purchase_commitment'] !== null ? 'Rs. ' . fv(number_format((float)$agreement['monthly_purchase_commitment'], 2)) . ' per Month' : 'To be set by Company'; ?>
                            </span>
                        </div>
                        <p class="text-muted" style="font-size:12px;margin-top:-6px;">(Subject to periodic review by FEMI9 in accordance with Clause 10 and Schedule &ndash; 1.)</p>

                        <h2>BETWEEN</h2>
                        <p><strong>FEMI9 LLP</strong>, a Limited Liability Partnership duly incorporated under the provisions of the Limited Liability Partnership Act, 2008, having its Registered Office / Principal Place of Business at:</p>
                        <p class="agr-value<?php echo empty($settings['company_address']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($settings['company_address'] ?: 'To be filled by Company')); ?></p>
                        <p>(hereinafter referred to as "FEMI9" or the "Company", which expression shall, unless repugnant to the context or meaning thereof, include its successors, legal representatives, permitted assigns and administrators);</p>

                        <h2>AND</h2>
                        <p><strong>TERRITORY PARTNER</strong></p>
                        <div class="agr-field-row<?php echo $profileChanged['name'] ? ' agr-row-changed' : ''; ?>"><label>Name:</label><span class="agr-value"><?php echo fv($tp['name']); ?></span></div>
                        <div class="agr-field-row<?php echo $profileChanged['company_name'] ? ' agr-row-changed' : ''; ?>"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($tp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row<?php echo $profileChanged['address'] ? ' agr-row-changed' : ''; ?>"><label>Address:</label><span class="agr-value"><?php echo fv(partner_billing_address_line($tp)); ?></span></div>
                        <div class="agr-field-row<?php echo $profileChanged['mobile'] ? ' agr-row-changed' : ''; ?>"><label>Mobile Number:</label><span class="agr-value"><?php echo fv($tp['mobile']); ?></span></div>
                        <div class="agr-field-row<?php echo $profileChanged['email'] ? ' agr-row-changed' : ''; ?>"><label>Email Address:</label><span class="agr-value<?php echo empty($tp['email']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['email'] ?: '—'); ?></span></div>
                        <p>(hereinafter referred to as the "Territory Partner" or "TP", which expression shall, unless repugnant to the context or meaning thereof, include, where applicable, its successors, legal representatives, heirs, executors, administrators and permitted assigns).</p>
                        <p>FEMI9 and the Territory Partner are hereinafter individually referred to as a "Party" and collectively as the "Parties."</p>

                        <?php
                        $effectiveBody = get_effective_agreement_body($db_conn, $agreement, 'territory_partner');
                        echo (!empty($agreement['signed_at']) && !$printMode)
                            ? diff_highlight_agreement_body($agreement['snap_body_html'] ?? null, $effectiveBody)
                            : $effectiveBody;
                        ?>

                        <h2>SCHEDULE &ndash; 1: TERRITORY / FIRKA ALLOCATION &amp; COMMERCIAL PARTICULARS</h2>
                        <p>The following particulars shall form an integral part of this Territory Partner Agreement and shall be read together with all the terms and conditions contained herein.</p>
                        <div class="agr-field-row<?php echo $profileChanged['name'] ? ' agr-row-changed' : ''; ?>"><label>Territory Partner Name:</label><span class="agr-value"><?php echo fv($tp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Territory Partner ID / Code:</label><span class="agr-value"><?php echo fv($tp['tp_id']); ?></span></div>
                        <div class="agr-field-row"><label>State:</label><span class="agr-value<?php echo empty($tp['branch_state']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['branch_state'] ?: '—'); ?></span></div>
                        <div class="agr-field-row"><label>District:</label><span class="agr-value<?php echo empty($tp['assigned_district']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['assigned_district'] ?: '—'); ?></span></div>
                        <?php if ($scheduleChanged): ?>
                        <p style="color:#92600a;font-size:12.5px;font-weight:600;margin:14px 0 2px;">&#9998; Updated by Company since your last signature &mdash; please review before signing again:</p>
                        <div class="agr-schedule-changed">
                        <?php endif; ?>
                        <div class="agr-field-row"><label>Taluk / Block:</label><span class="agr-value<?php echo empty($agreement['taluk_block']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['taluk_block'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Allotted Firka / Territory:</label><span class="agr-value<?php echo empty($agreement['territory_firka']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['territory_firka'] ?: 'To be set by Company')); ?></span></div>
                        <div class="agr-field-row"><label>Territory Code:</label><span class="agr-value<?php echo empty($agreement['territory_code']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['territory_code'] ?: '—')); ?></span></div>
                        <div class="agr-field-row"><label>Effective Date:</label><span class="agr-value<?php echo empty($agreement['effective_date']) ? ' agr-blank' : ''; ?>"><?php echo $agreement['effective_date'] ? fv(date('d-m-Y', strtotime($agreement['effective_date']))) : 'To be set by Company'; ?></span></div>
                        <div class="agr-field-row"><label>Approved Monthly Purchase Commitment:</label><span class="agr-value<?php echo $agreement['monthly_purchase_commitment'] === null ? ' agr-blank' : ''; ?>"><?php echo $agreement['monthly_purchase_commitment'] !== null ? 'Rs. ' . fv(number_format((float)$agreement['monthly_purchase_commitment'], 2)) . ' per Month' : 'To be set by Company'; ?></span></div>
                        <div class="agr-field-row"><label>Other Approved Commercial Particulars:</label><span class="agr-value<?php echo empty($agreement['other_particulars']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['other_particulars'] ?: '—')); ?></span></div>
                        <?php if ($scheduleChanged): ?>
                        </div>
                        <?php endif; ?>
                        <p>The above Territory / Firka allocation is granted exclusively for the purpose of carrying on authorised business activities under this Agreement and shall remain subject to the Territory Partner's continued performance, compliance and fulfilment of all obligations set out in this Agreement. Any modification to the above particulars shall be valid only if approved and communicated by FEMI9 through an authorised written process.</p>

                        <h2>FINAL DECLARATION &amp; ACCEPTANCE</h2>
                        <p>The Territory Partner hereby declares, confirms and agrees that:</p>
                        <ul class="agr-ul">
                            <li>This Agreement has been carefully read, fully understood and voluntarily accepted.</li>
                            <li>The rights, responsibilities, obligations and conditions governing the allotted territory have been clearly explained and are fully understood.</li>
                            <li>Active market development, field execution, retail servicing and business expansion are fundamental responsibilities of the Territory Partner.</li>
                            <li>The requirement to execute approved route plans, maintain regular market coverage, submit End-of-Day (EOD) reports and coordinate with the Company's authorised representatives is understood and accepted.</li>
                            <li>Continuation of the allotted territory is subject to satisfactory business performance, operational compliance and adherence to the terms of this Agreement.</li>
                            <li>The Territory Partner acknowledges that the Approved Monthly Purchase Commitment may be revised by FEMI9 from time to time based on the assessed business potential of the allotted territory and agrees to comply with such revised commitment from its effective date.</li>
                            <li>Responsibility for transportation, storage, handling and local distribution of products after authorised stock handover shall rest with the Territory Partner unless otherwise approved in writing by FEMI9.</li>
                            <li>The Referral Benefit opportunity, wherever applicable, shall be governed exclusively by the Company's prevailing Referral Benefit Policy, including its eligibility criteria, payment conditions and continuation requirements.</li>
                            <li>The Territory Partner shall comply with all applicable Company policies, Standard Operating Procedures (SOPs), operational guidelines and authorised business communications issued from time to time.</li>
                            <li>All information furnished to FEMI9 in connection with this appointment is true, complete and accurate to the best of the Territory Partner's knowledge and belief.</li>
                        </ul>
                        <p>The Territory Partner voluntarily accepts this appointment and agrees to faithfully perform all obligations contained in this Agreement.</p>

                        <h2>EXECUTION</h2>
                        <p><strong>FOR FEMI9 LLP</strong></p>
                        <div class="agr-field-row"><label>Authorized Signatory Name:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_name'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Designation:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_designation']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_designation'] ?: 'To be set by Company'); ?></span></div>
                        <?php
                        $__companySigSrc = null;
                        if (!empty($settings['authorized_signatory_signature'])) {
                            $__companySigSrc = $printMode
                                ? image_file_as_data_uri(__DIR__ . '/../company/company_signature/' . $settings['authorized_signatory_signature'])
                                : '../company/company_signature/' . fv($settings['authorized_signatory_signature']);
                        }
                        ?>
                        <?php if ($__companySigSrc): ?>
                        <div class="agr-field-row"><label>Signature:</label><span class="agr-value"><img src="<?php echo htmlspecialchars((string)($__companySigSrc), ENT_QUOTES, 'UTF-8', false); ?>" class="agr-sig-img"></span></div>
                        <?php endif; ?>

                        <p style="margin-top:18px;"><strong>FOR THE TERRITORY PARTNER</strong></p>
                        <div class="agr-field-row<?php echo $profileChanged['name'] ? ' agr-row-changed' : ''; ?>"><label>Name:</label><span class="agr-value"><?php echo fv($tp['name']); ?></span></div>
                        <div class="agr-field-row<?php echo $profileChanged['company_name'] ? ' agr-row-changed' : ''; ?>"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($tp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row"><label>Designation:</label>
                            <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['tp_designation']); ?></span>
                            <?php else: ?><input type="text" name="tp_designation" value="<?php echo fv($agreement['tp_designation']); ?>" placeholder="e.g. Proprietor">
                            <?php endif; ?>
                        </div>
                        <?php if ($isLocked): ?>
                        <div class="agr-field-row"><label>Signature:</label>
                            <?php if (!empty($agreement['tp_signature'])): ?>
                                <img src="<?php echo fv($agreement['tp_signature']); ?>" class="agr-sig-img">
                            <?php else: ?>
                                <span class="agr-value agr-blank">&mdash;</span>
                            <?php endif; ?>
                        </div>
                        <?php elseif (!empty($agreement['tp_signature'])): ?>
                        <div class="agr-sig-block no-print">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">Signature:</label>
                            <label style="font-weight:normal;display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                                <input type="radio" name="sig_choice" id="sigChoiceReuse" checked> Use my previous signature
                            </label>
                            <img src="<?php echo fv($agreement['tp_signature']); ?>" class="agr-sig-img" style="margin-bottom:10px;">
                            <label style="font-weight:normal;display:flex;align-items:center;gap:6px;">
                                <input type="radio" name="sig_choice" id="sigChoiceNew"> Draw a new signature instead
                            </label>
                            <div id="tpSigContainer" style="display:none;margin-top:8px;"></div>
                            <input type="hidden" name="tp_signature" id="tp_signature_input" value="<?php echo fv($agreement['tp_signature']); ?>">
                        </div>
                        <?php else: ?>
                        <div class="agr-sig-block">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">Signature:</label>
                            <div id="tpSigContainer" class="no-print"></div>
                            <input type="hidden" name="tp_signature" id="tp_signature_input">
                        </div>
                        <?php endif; ?>

                        <div class="agr-field-row"><label>PAN Card:</label>
                            <?php if ($isLocked): ?>
                                <?php if (!empty($agreement['pan_card_path'])): ?>
                                    <a href="kyc_documents/<?php echo fv($agreement['pan_card_path']); ?>" target="_blank" class="agr-value">View Uploaded PAN Card</a>
                                    <?php if (!$printMode && pan_card_is_displayable_image($agreement['pan_card_path'])): ?>
                                        <img src="kyc_documents/<?php echo fv($agreement['pan_card_path']); ?>" class="agr-pan-img">
                                    <?php endif; ?>
                                    <?php // In print mode, inline_pan_card_image_for_pdf() replaces the <a> above
                                          // with a data-URI <img> itself (a relative kyc_documents/ path, like this
                                          // one, has no base href to resolve against inside dompdf and renders as a
                                          // broken-image icon — see image_file_as_data_uri()'s doc comment). ?>
                                <?php else: ?>
                                    <span class="agr-value agr-blank">Not uploaded</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($agreement['pan_card_path'])): ?>
                                    <a href="kyc_documents/<?php echo fv($agreement['pan_card_path']); ?>" target="_blank" class="agr-value no-print">Currently uploaded — view</a>
                                    <?php if (pan_card_is_displayable_image($agreement['pan_card_path'])): ?>
                                        <img src="kyc_documents/<?php echo fv($agreement['pan_card_path']); ?>" class="agr-pan-img no-print">
                                    <?php endif; ?>
                                    <div class="form-text no-print">Choose a file below only if you want to replace it.</div>
                                <?php endif; ?>
                                <input type="file" name="pan_card" id="pan_card_input" accept=".jpg,.jpeg,.png,.pdf" class="no-print">
                                <div class="form-text no-print">JPG, PNG or PDF, up to 5 MB.</div>
                            <?php endif; ?>
                        </div>

                        <?php if ($isLocked && !empty($agreement['tp_signature']) && !$printMode): ?>
                        <!-- Digi Card is deliberately on-screen only (via the "View Your
                             TP Card" button and this preview) -- not embedded in the
                             printed/PDF Agreement itself; Company asked for the printed
                             document to stay just the agreement text and signatures. -->
                        <div class="agr-field-row no-print" style="display:block;">
                            <label style="display:block;margin-bottom:8px;">Your Digi Card:</label>
                            <img src="digi-card.php?t=<?php echo (int) strtotime($agreement['signed_at'] ?? 'now'); ?>" style="max-width:100%;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.15);">
                        </div>
                        <?php endif; ?>

                        <h2>ACKNOWLEDGEMENT</h2>
                        <p>By signing this Agreement, the Parties acknowledge that they have read, understood and voluntarily accepted all the terms and conditions contained herein and agree to be legally bound by the provisions of this Territory Partner Agreement. This Agreement shall become effective from the Effective Date specified in Schedule&nbsp;&ndash;&nbsp;1 and shall remain binding upon the Parties until terminated in accordance with its terms.</p>

                        <?php if (!$isLocked): ?>
                        <div class="no-print" style="margin-top:24px;text-align:center;">
                            <button type="submit" class="btn btn-success" style="padding:10px 28px;font-weight:600;">Accept &amp; Sign Agreement</button>
                        </div>
                        <?php endif; ?>
                        <?php if (!$printMode): ?><?php render_agreement_letterhead_footer(); ?><?php endif; ?>
                    </div>
                    <?php $__agrDocHtml = ob_get_clean(); echo $__agrDocHtml; ?>
                    </form>

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
<?php if (!$isLocked): ?>
<script src="../../assets/js/signature-pad.js"></script>
<script>
    initSignaturePad('tpSigContainer', 'tp_signature_input');

    // Re-sign flow: default to reusing the signature already on file instead
    // of forcing a fresh draw every time the Company edits the schedule or
    // wording — switching to "Draw a new signature" clears it so a stale
    // value can never be submitted alongside an unfinished new drawing.
    function wireSigReuseToggle(reuseId, freshId, containerId, inputId) {
        var reuse = document.getElementById(reuseId);
        var fresh = document.getElementById(freshId);
        var sigContainer = document.getElementById(containerId);
        var sigInput = document.getElementById(inputId);
        if (!reuse || !fresh) return;
        var previousSignature = sigInput.value;
        reuse.addEventListener('change', function () {
            if (this.checked) { sigContainer.style.display = 'none'; sigInput.value = previousSignature; }
        });
        fresh.addEventListener('change', function () {
            if (this.checked) { sigContainer.style.display = ''; sigInput.value = ''; }
        });
    }
    wireSigReuseToggle('sigChoiceReuse', 'sigChoiceNew', 'tpSigContainer', 'tp_signature_input');

    // Re-encodes an image file as JPEG at progressively lower quality until
    // it's under maxBytes, entirely in-browser — so a phone camera photo
    // (often 3-8MB) never hits the server's upload_max_filesize limit in the
    // first place. Dimensions are left untouched, only JPEG quality drops.
    // PDFs are passed through unchanged (onDone called with the original file).
    function compressImageFile(file, maxBytes, onDone) {
        if (!file.type || file.type.indexOf('image/') !== 0) { onDone(file); return; }
        if (!window.File || !window.URL || typeof HTMLCanvasElement === 'undefined' || !HTMLCanvasElement.prototype.toBlob) {
            onDone(file); return; // unsupported browser — fall back to the original file untouched
        }
        var img = new Image();
        var url = URL.createObjectURL(file);
        img.onload = function () {
            var canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);
            URL.revokeObjectURL(url);
            (function tryQuality(q) {
                canvas.toBlob(function (blob) {
                    if (!blob) { onDone(file); return; }
                    if (blob.size <= maxBytes || q <= 0.4) {
                        var newName = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                        onDone(new File([blob], newName, { type: 'image/jpeg' }));
                    } else {
                        tryQuality(q - 0.08);
                    }
                }, 'image/jpeg', q);
            })(0.88);
        };
        img.onerror = function () { URL.revokeObjectURL(url); onDone(file); };
        img.src = url;
    }

    function prepareSignaturesAndValidate() {
        var tpSig = document.getElementById('tp_signature_input').value;
        if (!tpSig) { alert('Please provide your signature (draw or type) before submitting.'); return false; }

        var panInput = document.getElementById('pan_card_input');
        var hasExistingPan = <?php echo !empty($agreement['pan_card_path']) ? 'true' : 'false'; ?>;
        if (panInput && !panInput.files.length && !hasExistingPan) {
            alert('Please upload your PAN card before submitting.');
            return false;
        }

        var form = document.getElementById('agreementForm');
        function confirmAndSubmit() {
            if (confirm('Once submitted, this agreement cannot be edited. Do you want to submit it now?')) {
                form.submit();
            }
        }
        if (panInput && panInput.files.length) {
            compressImageFile(panInput.files[0], 1.5 * 1024 * 1024, function (compressedFile) {
                try {
                    var dt = new DataTransfer();
                    dt.items.add(compressedFile);
                    panInput.files = dt.files;
                } catch (e) {
                    // DataTransfer unsupported on this browser — submit with
                    // the original, uncompressed file rather than block signing.
                }
                confirmAndSubmit();
            });
        } else {
            confirmAndSubmit();
        }
        return false; // actual submission happens via form.submit() above, once ready
    }
</script>
<?php endif; ?>
</body>
</html>
<?php
$__fullPage = ob_get_clean();
if ($printMode) {
    // Render a REAL multi-page PDF server-side via dompdf instead of
    // relying on the browser's own print pagination. Three earlier attempts
    // at a pure-CSS "repeating fixed header/footer" print page (position:fixed
    // + @page margins, even on a bare standalone document with no dashboard
    // chrome) all intermittently mispositioned the letterhead -- missing on
    // page 1, overlapping body text on later pages -- because Chrome's print
    // engine doesn't reliably keep a fixed-position element pinned to the
    // page box across page breaks. dompdf has no such ambiguity: its
    // setCallbacks('end_document', ...) hook draws the two letterhead PNGs
    // (the actual crop of the company's own Letterhead Femi9.pdf -- see
    // shared/AgreementService.php) at the exact same canvas coordinates on
    // every single page, independent of how the HTML content happens to
    // flow or break. $__agrDocHtml is the exact same markup the on-screen
    // page renders (captured via ob_start()/ob_get_clean() above, letterhead
    // calls skipped via $printMode since dompdf draws those itself) -- only
    // the stylesheet differs, because dompdf's CSS support doesn't extend to
    // flexbox (used for the on-screen .agr-field-row label/value layout), so
    // the label/value rows are laid out with CSS display:table instead here.
    require_once __DIR__ . '/../../vendor/autoload.php';

    $headerImg = __DIR__ . '/../../assets/images/agreement-letterhead-header.png';
    $footerImg = __DIR__ . '/../../assets/images/agreement-letterhead-footer.png';
    [$hImgW, $hImgH] = getimagesize($headerImg);
    [$fImgW, $fImgH] = getimagesize($footerImg);

    // All measurements in points (pt), matching dompdf's canvas units --
    // never mix in CSS px here, it would need a 96->72dpi (0.75x) conversion.
    // The letterhead images are drawn full-bleed (page-edge to page-edge,
    // no side margin) since that's how the real letterhead looks; only the
    // TEXT content gets the $marginSidePt left/right margin, via @page.
    $pageWidthPt  = 595.28; // A4
    $marginSidePt = 54; // left/right margin for the text content only
    $textGapPt    = 12; // gap between the header/footer image and the text
    $headerHPt    = $pageWidthPt * ($hImgH / $hImgW);
    $footerHPt    = $pageWidthPt * ($fImgH / $fImgW);
    $marginTopPt    = $headerHPt + $textGapPt;
    $marginBottomPt = $footerHPt + $textGapPt;

    // Calibri (the font the company actually wants) isn't one of dompdf's
    // built-in fonts, and it's a licensed Microsoft font -- not something to
    // bundle into the repo. It IS already installed on this Windows server
    // (every Office/Windows install ships it under C:\Windows\Fonts), so
    // it's registered here by reference to that system copy rather than a
    // copy checked into the app.
    $calibriDir = 'C:/Windows/Fonts/';
    $calibriAvailable = is_file($calibriDir . 'calibri.ttf');

    // See wrap_headings_with_next_block_for_pdf()'s own comment in
    // AgreementService.php for why this, not just a CSS "h2 + p" rule, is
    // needed to reliably keep a clause heading from being stranded alone
    // at the bottom of a page.
    $pdfBodyHtml = inline_pan_card_image_for_pdf($__agrDocHtml, __DIR__ . '/kyc_documents');
    $pdfBodyHtml = wrap_headings_with_next_block_for_pdf($pdfBodyHtml);

    $pdfHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        @page { size: A4; margin: ' . $marginTopPt . 'pt ' . $marginSidePt . 'pt ' . $marginBottomPt . 'pt ' . $marginSidePt . 'pt; }
        body { margin:0; background:#ffffff; font-family: "Calibri", "DejaVu Sans", sans-serif; font-size:13.5px; line-height:1.6; color:#1f2937; }
        .agr-doc { font-size:13.5px; }
        .agr-doc h1 { font-size:18px; text-align:center; margin-bottom:20px; font-family: "Calibri", "DejaVu Sans", sans-serif; font-weight:bold; }
        .agr-doc h2 { font-size:15.5px; margin:22px 0 8px; page-break-after:avoid; page-break-inside:avoid; font-family: "Calibri", "DejaVu Sans", sans-serif; font-weight:bold; }
        .agr-doc h2 + p { page-break-before:avoid; }
        .agr-pdf-keeptogether { page-break-inside: avoid; break-inside: avoid-page; }
        .agr-doc p { orphans:4; widows:3; }
        .agr-field-row { display:table; width:100%; margin:10px 0; }
        .agr-field-row label { display:table-cell; font-weight:bold; width:200px; padding-right:8px; font-family: "Calibri", "DejaVu Sans", sans-serif; }
        .agr-field-row .agr-value { display:table-cell; border-bottom:1px solid #9ca3af; padding:2px 4px; }
        .agr-value { color:#374151; }
        .agr-value.agr-blank { color:#9ca3af; font-style:italic; }
        .agr-ul { margin:8px 0 8px 20px; }
        .agr-sig-img { max-width:200px; max-height:44px; }
        .agr-pan-img { max-width:320px; max-height:220px; display:block; margin-top:4px; border:1px solid #e5e7eb; }
        .agr-schedule-changed { background:#ffd400; border:1px solid #c9a600; border-radius:8px; padding:12px 16px; margin:10px 0; }
        .agr-schedule-changed .agr-field-row label, .agr-schedule-changed .agr-value { color:#1f2937; }
        .agr-field-row.agr-row-changed { background:#ffd400; border:1px solid #c9a600; border-radius:8px; padding:8px 12px; }
        .agr-field-row.agr-row-changed label, .agr-field-row.agr-row-changed .agr-value { color:#1f2937; }
        .agr-wording-changed { background:#ffd400; border:1px solid #c9a600; border-radius:8px; padding:4px 14px; margin:10px 0; }
        .agr-word-changed { background:#ffd400; border-radius:3px; padding:0 2px; }
        </style></head><body>' . $pdfBodyHtml . '</body></html>';

    $pdfOptions = new \Dompdf\Options();
    $pdfOptions->set('isRemoteEnabled', true);
    // Without this, dompdf's default local-file security check (chroot)
    // silently rejects every <img src="..."> pointing outside its own
    // vendor directory -- including the PAN card image above -- falling
    // back to a broken-image icon with no error surfaced anywhere. Scoping
    // it to the whole femi9/ app root (not just kyc_documents/) keeps this
    // working for any other local image this PDF path embeds later too.
    $pdfOptions->setChroot([realpath(__DIR__ . '/../../')]);
    $dompdf = new \Dompdf\Dompdf($pdfOptions);
    if ($calibriAvailable) {
        $fm = $dompdf->getFontMetrics();
        $fm->registerFont(['family' => 'Calibri', 'style' => 'normal', 'weight' => 'normal'], $calibriDir . 'calibri.ttf');
        $fm->registerFont(['family' => 'Calibri', 'style' => 'normal', 'weight' => 'bold'], $calibriDir . 'calibrib.ttf');
        $fm->registerFont(['family' => 'Calibri', 'style' => 'italic', 'weight' => 'normal'], $calibriDir . 'calibrii.ttf');
        $fm->registerFont(['family' => 'Calibri', 'style' => 'italic', 'weight' => 'bold'], $calibriDir . 'calibriz.ttf');
    }
    $dompdf->setPaper('A4');
    $dompdf->loadHtml($pdfHtml);
    $dompdf->setCallbacks([
        ['event' => 'end_document', 'f' => function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($headerImg, $footerImg, $pageWidthPt, $headerHPt, $footerHPt) {
            $pageHPt = $canvas->get_height();
            $canvas->image($headerImg, 0, 0, $pageWidthPt, $headerHPt);
            $canvas->image($footerImg, 0, $pageHPt - $footerHPt, $pageWidthPt, $footerHPt);
        }],
    ]);
    $dompdf->render();

    $fileName = 'Territory_Partner_Agreement_' . preg_replace('/[^a-zA-Z0-9]+/', '', $tp['tp_id'] ?? 'TP') . '.pdf';
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    $dompdf->stream($fileName, ['Attachment' => false]);
    exit;
} else {
    echo $__fullPage;
}
