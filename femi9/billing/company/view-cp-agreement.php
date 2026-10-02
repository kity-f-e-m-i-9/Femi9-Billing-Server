<?php
// Read-only preview of a Channel Partner's Agreement, rendered exactly like
// channel-partner/agreement.php's own locked view — so finance/admin can
// see precisely what the CP sees/signed, without needing to log in as that
// CP. Schedule-1 particulars are edited on manage-agreements.php, linked
// from here; nothing on this page itself is editable.
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

$cp_id = (int) ($_GET['cp_id'] ?? 0);
$stmtCp = $db_conn->prepare("SELECT * FROM channel_partners WHERE id = ?");
$stmtCp->bind_param('i', $cp_id);
$stmtCp->execute();
$cp = $stmtCp->get_result()->fetch_assoc();
$stmtCp->close();

if (!$cp) {
    header("Location: manage-agreements.php");
    exit;
}

$settings  = get_agreement_settings($db_conn);
$agreement = get_or_create_cp_agreement($db_conn, $cp_id);
$isSigned  = (int) ($agreement['is_locked'] ?? 0) === 1;

function fv($val) { return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View CP Agreement : <?php echo fv($business_name ?? 'Femi9'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <style>
        .agr-doc { max-width:820px; margin:0 auto; background:#fff; padding:36px 44px; border:1px solid #e5e7eb; border-radius:10px; font-size:13.5px; line-height:1.6; color:#1f2937; }
        .agr-doc h1 { font-size:18px; text-align:center; margin-bottom:20px; }
        .agr-doc h2 { font-size:14.5px; margin:22px 0 8px; }
        .agr-field-row { display:flex; align-items:center; gap:8px; margin:10px 0; flex-wrap:wrap; }
        .agr-field-row label { font-weight:600; min-width:150px; }
        .agr-field-row .agr-value { flex:1; min-width:180px; border-bottom:1px solid #9ca3af; padding:2px 4px; }
        .agr-value { color:#374151; }
        @media (max-width: 575.98px) {
            .agr-doc { padding:18px 16px; font-size:13px; }
            .agr-field-row { flex-direction:column; align-items:flex-start; gap:2px; }
            .agr-field-row label { min-width:0; }
            .agr-field-row .agr-value { width:100%; min-width:0; }
        }
        .agr-value.agr-blank { color:#9ca3af; font-style:italic; }
        .agr-ul { margin:8px 0 8px 20px; }
        .agr-witness-box { border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; margin-top:14px; }
        .agr-sig-img { max-width:200px; max-height:44px; object-fit:contain; border-bottom:1px solid #9ca3af; }
        .agr-locked-banner { background:#f0fdf4; border:1px solid #bbf7d0; color:#065f46; border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px; }
        .agr-unsigned-banner { background:#fffbeb; border:1px solid #fde68a; color:#92400e; border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px; }
        @media print { .no-print { display:none !important; } .agr-doc { border:none; } }
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

                    <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                        <h1 style="font-size:20px;font-weight:600;color:#1f2937;margin:0;">CP Agreement — <?php echo fv($cp['name']); ?> (<?php echo fv($cp['cp_id']); ?>)</h1>
                        <div style="display:flex;gap:8px;">
                            <a href="manage-agreements.php?cp_id=<?php echo (int) $cp_id; ?>" class="btn btn-outline-primary btn-sm">Edit Schedule-1 Particulars</a>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print / Save as PDF</button>
                        </div>
                    </div>

                    <?php if ($isSigned): ?>
                    <div class="agr-locked-banner no-print">
                        This agreement was signed and submitted by the Channel Partner on <?php echo fv(date('d-M-Y h:i A', strtotime($agreement['signed_at']))); ?>.
                    </div>
                    <?php else: ?>
                    <div class="agr-unsigned-banner no-print">
                        The Channel Partner has not signed this agreement yet. This is a live preview of what they currently see — any missing Schedule-1 particulars still show "To be set by Company".
                    </div>
                    <?php endif; ?>

                    <div class="agr-doc">
                        <h1>FEMI9 LLP DIVISION CHANNEL PARTNER AGREEMENT</h1>

                        <p>This Division Channel Partner Agreement (hereinafter referred to as the "Agreement") is made and executed on the date and at the place specified below and shall become effective from the Effective Date recorded in Schedule&nbsp;&ndash;&nbsp;1.</p>

                        <div class="agr-field-row"><label>DATE:</label><span class="agr-value<?php echo empty($agreement['agreement_date']) ? ' agr-blank' : ''; ?>"><?php echo $agreement['agreement_date'] ? fv(date('d-m-Y', strtotime($agreement['agreement_date']))) : 'Not yet filled by CP'; ?></span></div>
                        <div class="agr-field-row"><label>PLACE:</label><span class="agr-value<?php echo empty($agreement['agreement_place']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['agreement_place'] ?: 'Not yet filled by CP'); ?></span></div>
                        <div class="agr-field-row"><label>Applicable Security Deposit (Initial):</label>
                            <span class="agr-value<?php echo $agreement['security_deposit'] === null ? ' agr-blank' : ''; ?>">
                                <?php echo $agreement['security_deposit'] !== null ? 'Rs. ' . fv(number_format((float)$agreement['security_deposit'], 2)) : 'To be set by Company'; ?>
                            </span>
                        </div>
                        <p class="text-muted" style="font-size:12px;margin-top:-6px;">(subject to review under clause 5 and schedule 1)</p>
                        <div class="agr-field-row"><label>Mode of Deposit:</label><span class="agr-value<?php echo empty($agreement['deposit_mode']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['deposit_mode'] ?: '—'); ?></span></div>
                        <div class="agr-field-row"><label>Transaction / Receipt / Reference No.:</label><span class="agr-value<?php echo empty($agreement['deposit_txn_ref']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['deposit_txn_ref'] ?: '—'); ?></span></div>

                        <h2>BETWEEN</h2>
                        <p><strong>FEMI9 LLP</strong>, a Limited Liability Partnership duly incorporated under the provisions of the Limited Liability Partnership Act, 2008, having its Registered Office / Principal Place of Business at:</p>
                        <p class="agr-value<?php echo empty($settings['company_address']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($settings['company_address'] ?: 'To be filled by Company')); ?></p>
                        <p>(hereinafter referred to as "FEMI9" or the "Company", which expression shall, unless repugnant to the context or meaning thereof, include its successors, legal representatives, permitted assigns and administrators);</p>

                        <h2>AND</h2>
                        <p><strong>CHANNEL PARTNER</strong></p>
                        <div class="agr-field-row"><label>Name:</label><span class="agr-value"><?php echo fv($cp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($cp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row"><label>Address:</label><span class="agr-value"><?php echo fv($cp['address']); ?></span></div>
                        <div class="agr-field-row"><label>Mobile Number:</label><span class="agr-value"><?php echo fv($cp['mobile']); ?></span></div>
                        <div class="agr-field-row"><label>Email Address:</label><span class="agr-value<?php echo empty($cp['email']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['email'] ?: '—'); ?></span></div>
                        <p>(hereinafter referred to as the "Channel Partner" or "CP", which expression shall, unless repugnant to the context or meaning thereof, include, where applicable, its successors, legal representatives, heirs, executors, administrators and permitted assigns).</p>
                        <p>FEMI9 and the Channel Partner are hereinafter individually referred to as a "Party" and collectively as the "Parties."</p>

                        <p class="text-muted" style="font-style:italic;">(Clauses 1&ndash;21 of the standard Division Channel Partner Agreement apply — identical to the copy shown to the Channel Partner. See <a href="agreement.php" onclick="return false;" style="pointer-events:none;color:inherit;">the CP-facing page</a> for the full clause text, or use Print/Save as PDF above once fully filled.)</p>

                        <h2>SCHEDULE &ndash; 1: DIVISION ALLOCATION &amp; COMMERCIAL PARTICULARS</h2>
                        <div class="agr-field-row"><label>Channel Partner Name:</label><span class="agr-value"><?php echo fv($cp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Channel Partner ID / Code:</label><span class="agr-value"><?php echo fv($cp['cp_id']); ?></span></div>
                        <div class="agr-field-row"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($cp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row"><label>State:</label><span class="agr-value<?php echo empty($cp['branch_state']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['branch_state'] ?: '—'); ?></span></div>
                        <div class="agr-field-row"><label>District:</label><span class="agr-value<?php echo empty($cp['branch_district']) ? ' agr-blank' : ''; ?>"><?php echo fv($cp['branch_district'] ?: '—'); ?></span></div>
                        <div class="agr-field-row"><label>Approved Division(s):</label><span class="agr-value<?php echo empty($agreement['approved_divisions']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['approved_divisions'] ?: 'To be set by Company')); ?></span></div>
                        <div class="agr-field-row"><label>Division Code(s):</label><span class="agr-value<?php echo empty($agreement['division_codes']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['division_codes'] ?: '—')); ?></span></div>
                        <div class="agr-field-row"><label>Approved Warehouse Address:</label><span class="agr-value<?php echo empty($agreement['approved_warehouse_address']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['approved_warehouse_address'] ?: 'To be set by Company')); ?></span></div>
                        <div class="agr-field-row"><label>Applicable Security Deposit:</label><span class="agr-value<?php echo $agreement['security_deposit'] === null ? ' agr-blank' : ''; ?>"><?php echo $agreement['security_deposit'] !== null ? 'Rs. ' . fv(number_format((float)$agreement['security_deposit'], 2)) : 'To be set by Company'; ?></span></div>
                        <div class="agr-field-row"><label>Approved Stock Holding Capacity:</label><span class="agr-value<?php echo empty($agreement['stock_holding_capacity']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['stock_holding_capacity'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Applicable Commercial / Purchase Category:</label><span class="agr-value<?php echo empty($agreement['commercial_category']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['commercial_category'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Monthly Business Return:</label><span class="agr-value">As per Clause 6 of this Agreement and the Company's prevailing Commercial Commitment Policy.</span></div>
                        <div class="agr-field-row"><label>Effective Date:</label><span class="agr-value<?php echo empty($agreement['effective_date']) ? ' agr-blank' : ''; ?>"><?php echo $agreement['effective_date'] ? fv(date('d-m-Y', strtotime($agreement['effective_date']))) : 'To be set by Company'; ?></span></div>
                        <div class="agr-field-row"><label>Other Approved Commercial Particulars:</label><span class="agr-value<?php echo empty($agreement['other_particulars']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['other_particulars'] ?: '—')); ?></span></div>

                        <h2>EXECUTION</h2>
                        <p><strong>FOR FEMI9 LLP</strong></p>
                        <div class="agr-field-row"><label>Authorized Signatory Name:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_name'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Designation:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_designation']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_designation'] ?: 'To be set by Company'); ?></span></div>

                        <p style="margin-top:18px;"><strong>FOR THE CHANNEL PARTNER</strong></p>
                        <div class="agr-field-row"><label>Name:</label><span class="agr-value"><?php echo fv($cp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Designation:</label><span class="agr-value<?php echo empty($agreement['cp_designation']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['cp_designation'] ?: '—'); ?></span></div>
                        <div class="agr-field-row"><label>Signature:</label>
                            <?php if (!empty($agreement['cp_signature'])): ?>
                                <img src="<?php echo fv($agreement['cp_signature']); ?>" class="agr-sig-img">
                            <?php else: ?>
                                <span class="agr-value agr-blank">Not yet signed</span>
                            <?php endif; ?>
                        </div>

                        <div class="agr-witness-box">
                            <p style="font-weight:700;">WITNESS &ndash; 1</p>
                            <div class="agr-field-row"><label>Name:</label><span class="agr-value<?php echo empty($agreement['witness1_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['witness1_name'] ?: '—'); ?></span></div>
                            <div class="agr-field-row"><label>Address:</label><span class="agr-value<?php echo empty($agreement['witness1_address']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['witness1_address'] ?: '—'); ?></span></div>
                            <div class="agr-field-row"><label>Signature:</label>
                                <?php if (!empty($agreement['witness1_signature'])): ?>
                                    <img src="<?php echo fv($agreement['witness1_signature']); ?>" class="agr-sig-img">
                                <?php else: ?>
                                    <span class="agr-value agr-blank">&mdash;</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="agr-witness-box">
                            <p style="font-weight:700;">WITNESS &ndash; 2</p>
                            <div class="agr-field-row"><label>Name:</label><span class="agr-value<?php echo empty($agreement['witness2_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['witness2_name'] ?: '—'); ?></span></div>
                            <div class="agr-field-row"><label>Address:</label><span class="agr-value<?php echo empty($agreement['witness2_address']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['witness2_address'] ?: '—'); ?></span></div>
                            <div class="agr-field-row"><label>Signature:</label>
                                <?php if (!empty($agreement['witness2_signature'])): ?>
                                    <img src="<?php echo fv($agreement['witness2_signature']); ?>" class="agr-sig-img">
                                <?php else: ?>
                                    <span class="agr-value agr-blank">&mdash;</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h2>ACKNOWLEDGEMENT</h2>
                        <p>By signing this Agreement, the Parties acknowledge that they have read, understood and voluntarily accepted all the terms and conditions contained herein and agree to be legally bound by the provisions of this Division Channel Partner Agreement.</p>
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
