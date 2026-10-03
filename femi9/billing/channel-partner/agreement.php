<?php
include("checksession.php");
require_once __DIR__ . '/../shared/AgreementService.php';
include("config.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$cp_id = (int) $Login_user_IDvl;

$stmtCp = $db_conn->prepare("SELECT * FROM channel_partners WHERE id = ?");
$stmtCp->bind_param('i', $cp_id);
$stmtCp->execute();
$cp = $stmtCp->get_result()->fetch_assoc();
$stmtCp->close();

$settings  = get_agreement_settings($db_conn);
$agreement = get_or_create_cp_agreement($db_conn, $cp_id);
$isLocked  = (int) ($agreement['is_locked'] ?? 0) === 1;
// See territory-partner/agreement.php's identical $scheduleChanged for why.
$scheduleChanged = agreement_needs_resign($agreement);

// See territory-partner/agreement.php's identical $profileChanged for why.
$profileChanged = get_profile_change_flags($cp, $agreement);

if (isset($_SESSION['sucMessage'])) { $flashMsg = $_SESSION['sucMessage']; unset($_SESSION['sucMessage']); }
if (isset($_SESSION['errorMessage'])) { $flashErr = $_SESSION['errorMessage']; unset($_SESSION['errorMessage']); }

function fv($val) { return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Channel Partner Agreement : <?php echo fv($business_name ?? 'Femi9'); ?></title>
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
        .agr-doc h2 { font-size:14.5px; margin:22px 0 8px; }
        .agr-field-row { display:flex; align-items:center; gap:8px; margin:10px 0; flex-wrap:wrap; }
        .agr-field-row label { font-weight:600; min-width:150px; }
        .agr-field-row input, .agr-field-row .agr-value { flex:1; min-width:180px; border:none; border-bottom:1px solid #9ca3af; padding:2px 4px; background:transparent; }
        .agr-value { color:#374151; }
        .agr-value.agr-blank { color:#9ca3af; font-style:italic; }
        .agr-ul { margin:8px 0 8px 20px; }
        .agr-witness-box { border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; margin-top:14px; }
        .agr-sig-block { margin-top:14px; padding-top:10px; border-top:1px dashed #e5e7eb; }
        .agr-sig-img { max-width:200px; max-height:44px; object-fit:contain; border-bottom:1px solid #9ca3af; }
        @media print { .no-print { display:none !important; } .agr-doc { border:none; } }
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
        <?php include("app-header.php"); ?>
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
                        <h1 style="font-size:20px;font-weight:600;color:#1f2937;margin:0;">Channel Partner Agreement</h1>
                        <?php if ($isLocked): ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print / Save as PDF</button>
                        <?php endif; ?>
                    </div>

                    <?php if ($isLocked): ?>
                    <div class="agr-locked-banner no-print">
                        This agreement was signed and submitted on <?php echo fv(date('d-M-Y h:i A', strtotime($agreement['signed_at']))); ?> and is now read-only.
                    </div>
                    <?php endif; ?>

                    <form method="post" action="agreement-action.php" id="agreementForm" enctype="multipart/form-data" onsubmit="return prepareSignaturesAndValidate();">
                    <input type="hidden" name="csrf_token" value="<?php echo fv($_SESSION['csrf_token']); ?>">
                    <div class="agr-doc">
                        <h1>FEMI9 LLP DIVISION CHANNEL PARTNER AGREEMENT</h1>

                        <p>This Division Channel Partner Agreement (hereinafter referred to as the "Agreement") is made and executed on the date and at the place specified below and shall become effective from the Effective Date recorded in Schedule&nbsp;&ndash;&nbsp;1.</p>

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
                        <div class="agr-field-row"><label>Applicable Security Deposit (Initial):</label>
                            <span class="agr-value<?php echo $agreement['security_deposit'] === null ? ' agr-blank' : ''; ?>">
                                <?php echo $agreement['security_deposit'] !== null ? 'Rs. ' . fv(number_format((float)$agreement['security_deposit'], 2)) : 'To be set by Company'; ?>
                            </span>
                        </div>
                        <p class="text-muted" style="font-size:12px;margin-top:-6px;">(subject to review under clause 5 and schedule 1)</p>
                        <div class="agr-field-row"><label>Mode of Deposit:</label>
                            <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['deposit_mode']); ?></span>
                            <?php else: ?><input type="text" name="deposit_mode" value="<?php echo fv($agreement['deposit_mode']); ?>">
                            <?php endif; ?>
                        </div>
                        <div class="agr-field-row"><label>Transaction / Receipt / Reference No.:</label>
                            <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['deposit_txn_ref']); ?></span>
                            <?php else: ?><input type="text" name="deposit_txn_ref" value="<?php echo fv($agreement['deposit_txn_ref']); ?>">
                            <?php endif; ?>
                        </div>

                        <h2>BETWEEN</h2>
                        <p><strong>FEMI9 LLP</strong>, a Limited Liability Partnership duly incorporated under the provisions of the Limited Liability Partnership Act, 2008, having its Registered Office / Principal Place of Business at:</p>
                        <p class="agr-value<?php echo empty($settings['company_address']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($settings['company_address'] ?: 'To be filled by Company')); ?></p>
                        <p>(hereinafter referred to as "FEMI9" or the "Company", which expression shall, unless repugnant to the context or meaning thereof, include its successors, legal representatives, permitted assigns and administrators);</p>

                        <h2>AND</h2>
                        <p><strong>CHANNEL PARTNER</strong></p>
                        <div class="agr-field-row<?php echo $profileChanged['name'] ? ' agr-row-changed' : ''; ?>"><label>Name:</label><span class="agr-value"><?php echo fv($cp['name']); ?></span></div>
                        <div class="agr-field-row<?php echo $profileChanged['company_name'] ? ' agr-row-changed' : ''; ?>"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($cp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row<?php echo $profileChanged['address'] ? ' agr-row-changed' : ''; ?>"><label>Address:</label><span class="agr-value"><?php echo fv($cp['address']); ?></span></div>
                        <div class="agr-field-row<?php echo $profileChanged['mobile'] ? ' agr-row-changed' : ''; ?>"><label>Mobile Number:</label><span class="agr-value"><?php echo fv($cp['mobile']); ?></span></div>
                        <div class="agr-field-row<?php echo $profileChanged['email'] ? ' agr-row-changed' : ''; ?>"><label>Email Address:</label><span class="agr-value<?php echo empty($cp['email']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['email'] ?: '—'); ?></span></div>
                        <p>(hereinafter referred to as the "Channel Partner" or "CP", which expression shall, unless repugnant to the context or meaning thereof, include, where applicable, its successors, legal representatives, heirs, executors, administrators and permitted assigns).</p>
                        <p>FEMI9 and the Channel Partner are hereinafter individually referred to as a "Party" and collectively as the "Parties."</p>

                        <?php
                        $effectiveBody = get_effective_agreement_body($db_conn, $agreement, 'channel_partner');
                        echo (!empty($agreement['signed_at']))
                            ? diff_highlight_agreement_body($agreement['snap_body_html'] ?? null, $effectiveBody)
                            : $effectiveBody;
                        ?>

                        <h2>SCHEDULE &ndash; 1: DIVISION ALLOCATION &amp; COMMERCIAL PARTICULARS</h2>
                        <p>The particulars contained in this Schedule shall form an integral part of this Division Channel Partner Agreement and shall be read together with all the terms and conditions contained herein.</p>
                        <div class="agr-field-row<?php echo $profileChanged['name'] ? ' agr-row-changed' : ''; ?>"><label>Channel Partner Name:</label><span class="agr-value"><?php echo fv($cp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Channel Partner ID / Code:</label><span class="agr-value"><?php echo fv($cp['cp_id']); ?></span></div>
                        <div class="agr-field-row<?php echo $profileChanged['company_name'] ? ' agr-row-changed' : ''; ?>"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($cp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row"><label>State:</label><span class="agr-value<?php echo empty($cp['branch_state']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['branch_state'] ?: '—'); ?></span></div>
                        <div class="agr-field-row"><label>District:</label><span class="agr-value<?php echo empty($cp['branch_district']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['branch_district'] ?: '—'); ?></span></div>
                        <?php if ($scheduleChanged): ?>
                        <p style="color:#92600a;font-size:12.5px;font-weight:600;margin:14px 0 2px;">&#9998; Updated by Company since your last signature &mdash; please review before signing again:</p>
                        <div class="agr-schedule-changed">
                        <?php endif; ?>
                        <div class="agr-field-row"><label>Approved Division(s):</label><span class="agr-value<?php echo empty($agreement['approved_divisions']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['approved_divisions'] ?: 'To be set by Company')); ?></span></div>
                        <div class="agr-field-row"><label>Division Code(s):</label><span class="agr-value<?php echo empty($agreement['division_codes']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['division_codes'] ?: '—')); ?></span></div>
                        <div class="agr-field-row"><label>Approved Warehouse Address:</label><span class="agr-value<?php echo empty($agreement['approved_warehouse_address']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['approved_warehouse_address'] ?: 'To be set by Company')); ?></span></div>
                        <div class="agr-field-row"><label>Applicable Security Deposit:</label><span class="agr-value<?php echo $agreement['security_deposit'] === null ? ' agr-blank' : ''; ?>"><?php echo $agreement['security_deposit'] !== null ? 'Rs. ' . fv(number_format((float)$agreement['security_deposit'], 2)) : 'To be set by Company'; ?></span></div>
                        <div class="agr-field-row"><label>Approved Stock Holding Capacity:</label><span class="agr-value<?php echo empty($agreement['stock_holding_capacity']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['stock_holding_capacity'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Applicable Commercial / Purchase Category:</label><span class="agr-value<?php echo empty($agreement['commercial_category']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['commercial_category'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Effective Date:</label><span class="agr-value<?php echo empty($agreement['effective_date']) ? ' agr-blank' : ''; ?>"><?php echo $agreement['effective_date'] ? fv(date('d-m-Y', strtotime($agreement['effective_date']))) : 'To be set by Company'; ?></span></div>
                        <div class="agr-field-row"><label>Other Approved Commercial Particulars:</label><span class="agr-value<?php echo empty($agreement['other_particulars']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['other_particulars'] ?: '—')); ?></span></div>
                        <?php if ($scheduleChanged): ?>
                        </div>
                        <?php endif; ?>
                        <div class="agr-field-row"><label>Monthly Business Return:</label><span class="agr-value">As per Clause 6 of this Agreement and the Company's prevailing Commercial Commitment Policy.</span></div>
                        <p>The above particulars shall remain valid subject to the Channel Partner's continued compliance with this Agreement, satisfactory operational performance and ongoing approval by FEMI9. Any modification to the above particulars shall be effective only upon written approval issued through the Company's authorized communication process.</p>

                        <h2>FINAL DECLARATION &amp; ACCEPTANCE</h2>
                        <p>The Channel Partner hereby declares, confirms and agrees that:</p>
                        <ul class="agr-ul">
                            <li>This Agreement has been carefully read, fully understood and voluntarily accepted.</li>
                            <li>The operational responsibilities relating to warehouse management, inventory accountability and Division operations have been clearly explained and are fully understood.</li>
                            <li>The Security Deposit applicable to each allotted Division is maintained solely as a refundable business security in accordance with the terms of this Agreement.</li>
                            <li>The Monthly Business Return, eligibility criteria and commercial commitment framework have been fully understood and accepted.</li>
                            <li>Separate operational and commercial accountability shall be maintained for each allotted Division covered under this Agreement.</li>
                            <li>Company Stock shall remain the exclusive property of FEMI9 until released through authorized business transactions.</li>
                            <li>Warehouse operations, stock handling, inventory management and stock handover shall be carried out strictly in accordance with the Company's authorized procedures.</li>
                            <li>All operational policies, SOPs, commercial policies and authorized business communications issued by FEMI9 from time to time shall be complied with.</li>
                            <li>All information and documents furnished to FEMI9 in connection with this appointment are true, complete and accurate to the best of the Channel Partner's knowledge.</li>
                            <li>The Channel Partner voluntarily accepts this appointment and undertakes to faithfully perform all obligations contained in this Agreement.</li>
                            <li>The Channel Partner acknowledges that no promise, assurance or representation other than those expressly contained in this Agreement shall be binding upon FEMI9.</li>
                        </ul>

                        <h2>EXECUTION</h2>
                        <p><strong>FOR FEMI9 LLP</strong></p>
                        <div class="agr-field-row"><label>Authorized Signatory Name:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_name'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Designation:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_designation']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_designation'] ?: 'To be set by Company'); ?></span></div>

                        <p style="margin-top:18px;"><strong>FOR THE CHANNEL PARTNER</strong></p>
                        <div class="agr-field-row<?php echo $profileChanged['name'] ? ' agr-row-changed' : ''; ?>"><label>Name:</label><span class="agr-value"><?php echo fv($cp['name']); ?></span></div>
                        <div class="agr-field-row<?php echo $profileChanged['company_name'] ? ' agr-row-changed' : ''; ?>"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($cp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row"><label>Designation:</label>
                            <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['cp_designation']); ?></span>
                            <?php else: ?><input type="text" name="cp_designation" value="<?php echo fv($agreement['cp_designation']); ?>" placeholder="e.g. Proprietor">
                            <?php endif; ?>
                        </div>
                        <?php if ($isLocked): ?>
                        <div class="agr-field-row"><label>Signature:</label>
                            <?php if (!empty($agreement['cp_signature'])): ?>
                                <img src="<?php echo fv($agreement['cp_signature']); ?>" class="agr-sig-img">
                            <?php else: ?>
                                <span class="agr-value agr-blank">&mdash;</span>
                            <?php endif; ?>
                        </div>
                        <?php elseif (!empty($agreement['cp_signature'])): ?>
                        <div class="agr-sig-block no-print">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">Signature:</label>
                            <label style="font-weight:normal;display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                                <input type="radio" name="sig_choice" id="sigChoiceReuse" checked> Use my previous signature
                            </label>
                            <img src="<?php echo fv($agreement['cp_signature']); ?>" class="agr-sig-img" style="margin-bottom:10px;">
                            <label style="font-weight:normal;display:flex;align-items:center;gap:6px;">
                                <input type="radio" name="sig_choice" id="sigChoiceNew"> Draw a new signature instead
                            </label>
                            <div id="cpSigContainer" style="display:none;margin-top:8px;"></div>
                            <input type="hidden" name="cp_signature" id="cp_signature_input" value="<?php echo fv($agreement['cp_signature']); ?>">
                        </div>
                        <?php else: ?>
                        <div class="agr-sig-block">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">Signature:</label>
                            <div id="cpSigContainer" class="no-print"></div>
                            <input type="hidden" name="cp_signature" id="cp_signature_input">
                        </div>
                        <?php endif; ?>

                        <div class="agr-field-row"><label>PAN Card:</label>
                            <?php if ($isLocked): ?>
                                <?php if (!empty($agreement['pan_card_path'])): ?>
                                    <a href="kyc_documents/<?php echo fv($agreement['pan_card_path']); ?>" target="_blank" class="agr-value">View Uploaded PAN Card</a>
                                <?php else: ?>
                                    <span class="agr-value agr-blank">Not uploaded</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($agreement['pan_card_path'])): ?>
                                    <a href="kyc_documents/<?php echo fv($agreement['pan_card_path']); ?>" target="_blank" class="agr-value no-print">Currently uploaded — view</a>
                                    <div class="form-text no-print">Choose a file below only if you want to replace it.</div>
                                <?php endif; ?>
                                <input type="file" name="pan_card" id="pan_card_input" accept=".jpg,.jpeg,.png,.pdf" class="no-print">
                                <div class="form-text no-print">JPG, PNG or PDF, up to 5 MB.</div>
                            <?php endif; ?>
                        </div>

                        <div class="agr-witness-box">
                            <p style="font-weight:700;">WITNESS &ndash; 1</p>
                            <div class="agr-field-row"><label>Name:</label>
                                <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['witness1_name']); ?></span>
                                <?php else: ?><input type="text" name="witness1_name" value="<?php echo fv($agreement['witness1_name']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="agr-field-row"><label>Address:</label>
                                <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['witness1_address']); ?></span>
                                <?php else: ?><input type="text" name="witness1_address" value="<?php echo fv($agreement['witness1_address']); ?>">
                                <?php endif; ?>
                            </div>
                            <?php if ($isLocked): ?>
                            <div class="agr-field-row"><label>Signature:</label>
                                <?php if (!empty($agreement['witness1_signature'])): ?>
                                    <img src="<?php echo fv($agreement['witness1_signature']); ?>" class="agr-sig-img">
                                <?php else: ?>
                                    <span class="agr-value agr-blank">&mdash;</span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="agr-sig-block">
                                <label style="font-weight:600;display:block;margin-bottom:6px;">Signature:</label>
                                <div id="witness1SigContainer" class="no-print"></div>
                                <input type="hidden" name="witness1_signature" id="witness1_signature_input">
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="agr-witness-box">
                            <p style="font-weight:700;">WITNESS &ndash; 2</p>
                            <div class="agr-field-row"><label>Name:</label>
                                <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['witness2_name']); ?></span>
                                <?php else: ?><input type="text" name="witness2_name" value="<?php echo fv($agreement['witness2_name']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="agr-field-row"><label>Address:</label>
                                <?php if ($isLocked): ?><span class="agr-value"><?php echo fv($agreement['witness2_address']); ?></span>
                                <?php else: ?><input type="text" name="witness2_address" value="<?php echo fv($agreement['witness2_address']); ?>">
                                <?php endif; ?>
                            </div>
                            <?php if ($isLocked): ?>
                            <div class="agr-field-row"><label>Signature:</label>
                                <?php if (!empty($agreement['witness2_signature'])): ?>
                                    <img src="<?php echo fv($agreement['witness2_signature']); ?>" class="agr-sig-img">
                                <?php else: ?>
                                    <span class="agr-value agr-blank">&mdash;</span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="agr-sig-block">
                                <label style="font-weight:600;display:block;margin-bottom:6px;">Signature:</label>
                                <div id="witness2SigContainer" class="no-print"></div>
                                <input type="hidden" name="witness2_signature" id="witness2_signature_input">
                            </div>
                            <?php endif; ?>
                        </div>

                        <h2>ACKNOWLEDGEMENT</h2>
                        <p>By signing this Agreement, the Parties acknowledge that they have read, understood and voluntarily accepted all the terms and conditions contained herein and agree to be legally bound by the provisions of this Division Channel Partner Agreement. This Agreement shall become effective from the Effective Date specified in Schedule&nbsp;&ndash;&nbsp;1 and shall remain in force until terminated in accordance with the provisions of this Agreement.</p>

                        <?php if (!$isLocked): ?>
                        <div class="no-print" style="margin-top:24px;text-align:center;">
                            <button type="submit" class="btn btn-success" style="padding:10px 28px;font-weight:600;">Accept &amp; Sign Agreement</button>
                        </div>
                        <?php endif; ?>
                    </div>
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
    initSignaturePad('cpSigContainer', 'cp_signature_input');
    initSignaturePad('witness1SigContainer', 'witness1_signature_input');
    initSignaturePad('witness2SigContainer', 'witness2_signature_input');

    // Re-sign flow: default to reusing the signature already on file instead
    // of forcing a fresh draw every time the Company edits the schedule or
    // wording — switching to "Draw a new signature" clears it so a stale
    // value can never be submitted alongside an unfinished new drawing.
    (function () {
        var reuse = document.getElementById('sigChoiceReuse');
        var fresh = document.getElementById('sigChoiceNew');
        var sigContainer = document.getElementById('cpSigContainer');
        var sigInput = document.getElementById('cp_signature_input');
        if (!reuse || !fresh) return;
        var previousSignature = sigInput.value;
        reuse.addEventListener('change', function () {
            if (this.checked) { sigContainer.style.display = 'none'; sigInput.value = previousSignature; }
        });
        fresh.addEventListener('change', function () {
            if (this.checked) { sigContainer.style.display = ''; sigInput.value = ''; }
        });
    })();

    function prepareSignaturesAndValidate() {
        // Only the Channel Partner's own signature is mandatory — witnesses
        // are optional, and filling in just one of the two is fine (not
        // both required). Confirmed 2026-09-28.
        var cpSig = document.getElementById('cp_signature_input').value;
        if (!cpSig) { alert('Please provide your signature (draw or type) before submitting.'); return false; }

        var panInput = document.getElementById('pan_card_input');
        var hasExistingPan = <?php echo !empty($agreement['pan_card_path']) ? 'true' : 'false'; ?>;
        if (panInput && !panInput.files.length && !hasExistingPan) {
            alert('Please upload your PAN card before submitting.');
            return false;
        }

        return confirm('Once submitted, this agreement cannot be edited. Do you want to submit it now?');
    }
</script>
<?php endif; ?>
</body>
</html>
