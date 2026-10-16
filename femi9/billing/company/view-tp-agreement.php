<?php
// Read-only preview of a Territory Partner's Agreement, rendered exactly
// like territory-partner/agreement.php's own locked view — so finance/admin
// can see precisely what the TP sees/signed, without needing to log in as
// that TP. Schedule-1 particulars are edited on manage-agreements.php,
// linked from here; nothing on this page itself is editable.
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

$tp_id = (int) ($_GET['tp_id'] ?? 0);
$stmtTp = $db_conn->prepare("SELECT * FROM territory_partners WHERE id = ?");
$stmtTp->bind_param('i', $tp_id);
$stmtTp->execute();
$tp = $stmtTp->get_result()->fetch_assoc();
$stmtTp->close();

if (!$tp) {
    header("Location: manage-agreements.php");
    exit;
}

$settings  = get_agreement_settings($db_conn);
$agreement = get_or_create_tp_agreement($db_conn, $tp_id);
$isSigned  = (int) ($agreement['is_locked'] ?? 0) === 1;

function fv($val) { return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8'); }

// See territory-partner/agreement.php's identical $printMode for why this
// exists -- the fixed letterhead header/footer doesn't repeat reliably
// across printed pages once nested in the dashboard chrome, so "Print /
// Save as PDF" opens ?print=1, a bare standalone document instead.
$printMode = isset($_GET['print']);
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View TP Agreement : <?php echo fv($business_name ?? 'Femi9'); ?></title>
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
        .agr-doc h2 { font-size:14.5px; margin:22px 0 8px; break-after:avoid; page-break-after:avoid; } .agr-doc p { orphans:3; widows:3; }
        .agr-letterhead-img { width:100%; height:auto; display:block; }
        .agr-letterhead { margin-bottom:16px; }
        .agr-letterhead-footer { margin-top:30px; }
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
        .agr-witness-box { border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; margin-top:14px; }
        .agr-sig-img { max-width:200px; max-height:44px; object-fit:contain; border-bottom:1px solid #9ca3af; }
        .agr-pan-img { max-width:320px; max-height:220px; display:block; margin-top:6px; border:1px solid #e5e7eb; border-radius:4px; }
        .agr-locked-banner { background:#f0fdf4; border:1px solid #bbf7d0; color:#065f46; border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px; }
        .agr-unsigned-banner { background:#fffbeb; border:1px solid #fde68a; color:#92400e; border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px; }
        @media print {
            .no-print { display:none !important; }
            .app, .app-container, .app-content, .content-wrapper, .container-fluid {
                position:static !important; margin:0 !important; padding:0 !important;
                width:auto !important; max-width:none !important; float:none !important;
            }
            .agr-doc { border:none; padding:0; max-width:none; margin:0; }
            @page { size: A4; margin: 175px 42px 105px 42px; }
            .agr-letterhead { position:fixed; top:0; left:0; right:0; }
            .agr-letterhead-footer { position:fixed; bottom:0; left:0; right:0; margin-top:0; }
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

                    <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                        <h1 style="font-size:20px;font-weight:600;color:#1f2937;margin:0;">TP Agreement — <?php echo fv($tp['name']); ?> (<?php echo fv($tp['tp_id']); ?>)</h1>
                        <div style="display:flex;gap:8px;">
                            <a href="manage-agreements.php?tp_id=<?php echo (int) $tp_id; ?>" class="btn btn-outline-primary btn-sm">Edit Schedule-1 Particulars</a>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.open('view-tp-agreement.php?tp_id=<?php echo (int) $tp_id; ?>&print=1', '_blank')">Print / Save as PDF</button>
                        </div>
                    </div>

                    <?php if ($isSigned): ?>
                    <div class="agr-locked-banner no-print">
                        This agreement was signed and submitted by the Territory Partner on <?php echo fv(date('d-M-Y h:i A', strtotime($agreement['signed_at']))); ?>.
                    </div>
                    <?php else: ?>
                    <div class="agr-unsigned-banner no-print">
                        The Territory Partner has not signed this agreement yet. This is a live preview of what they currently see — any missing Schedule-1 particulars still show "To be set by Company".
                    </div>
                    <?php endif; ?>

                    <?php ob_start(); ?>
                    <div class="agr-doc">
                        <?php if (!$printMode): ?><?php render_agreement_letterhead_header(); ?><?php endif; ?>
                        <h1>FEMI9 LLP - TERRITORY PARTNER AGREEMENT</h1>

                        <p>This Territory Partner Agreement (hereinafter referred to as the "Agreement") is made and executed on the date and at the place specified below and shall become effective from the Effective Date recorded in Schedule&nbsp;&ndash;&nbsp;1.</p>

                        <div class="agr-field-row"><label>DATE:</label><span class="agr-value<?php echo empty($agreement['agreement_date']) ? ' agr-blank' : ''; ?>"><?php echo $agreement['agreement_date'] ? fv(date('d-m-Y', strtotime($agreement['agreement_date']))) : 'Not yet filled by TP'; ?></span></div>
                        <div class="agr-field-row"><label>PLACE:</label><span class="agr-value<?php echo empty($agreement['agreement_place']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['agreement_place'] ?: 'Not yet filled by TP'); ?></span></div>
                        <div class="agr-field-row"><label>Approved Initial Monthly Purchase Commitment:</label>
                            <span class="agr-value<?php echo $agreement['monthly_purchase_commitment'] === null ? ' agr-blank' : ''; ?>">
                                <?php echo $agreement['monthly_purchase_commitment'] !== null ? 'Rs. ' . fv(number_format((float)$agreement['monthly_purchase_commitment'], 2)) . ' per Month' : 'To be set by Company'; ?>
                            </span>
                        </div>

                        <h2>BETWEEN</h2>
                        <p><strong>FEMI9 LLP</strong>, a Limited Liability Partnership duly incorporated under the provisions of the Limited Liability Partnership Act, 2008, having its Registered Office / Principal Place of Business at:</p>
                        <p class="agr-value<?php echo empty($settings['company_address']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($settings['company_address'] ?: 'To be filled by Company')); ?></p>
                        <p>(hereinafter referred to as "FEMI9" or the "Company", which expression shall, unless repugnant to the context or meaning thereof, include its successors, legal representatives, permitted assigns and administrators);</p>

                        <h2>AND</h2>
                        <p><strong>TERRITORY PARTNER</strong></p>
                        <div class="agr-field-row"><label>Name:</label><span class="agr-value"><?php echo fv($tp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Business / Entity Name:</label><span class="agr-value<?php echo empty($tp['company_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['company_name'] ?: 'Not applicable'); ?></span></div>
                        <div class="agr-field-row"><label>Address:</label><span class="agr-value"><?php echo fv($tp['address']); ?></span></div>
                        <div class="agr-field-row"><label>Mobile Number:</label><span class="agr-value"><?php echo fv($tp['mobile']); ?></span></div>
                        <div class="agr-field-row"><label>Email Address:</label><span class="agr-value<?php echo empty($tp['email']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['email'] ?: '—'); ?></span></div>
                        <p>(hereinafter referred to as the "Territory Partner" or "TP", which expression shall, unless repugnant to the context or meaning thereof, include, where applicable, its successors, legal representatives, heirs, executors, administrators and permitted assigns).</p>
                        <p>FEMI9 and the Territory Partner are hereinafter individually referred to as a "Party" and collectively as the "Parties."</p>

                        <?php echo get_effective_agreement_body($db_conn, $agreement, 'territory_partner'); ?>

                        <h2>SCHEDULE &ndash; 1: TERRITORY / FIRKA ALLOCATION &amp; COMMERCIAL PARTICULARS</h2>
                        <div class="agr-field-row"><label>Territory Partner Name:</label><span class="agr-value"><?php echo fv($tp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Territory Partner ID / Code:</label><span class="agr-value"><?php echo fv($tp['tp_id']); ?></span></div>
                        <div class="agr-field-row"><label>State:</label><span class="agr-value<?php echo empty($tp['branch_state']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['branch_state'] ?: '—'); ?></span></div>
                        <div class="agr-field-row"><label>District:</label><span class="agr-value<?php echo empty($tp['assigned_district']) ? ' agr-blank' : ''; ?>"><?php echo fv($tp['assigned_district'] ?: '—'); ?></span></div>
                        <div class="agr-field-row"><label>Taluk / Block:</label><span class="agr-value<?php echo empty($agreement['taluk_block']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['taluk_block'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Allotted Firka / Territory:</label><span class="agr-value<?php echo empty($agreement['territory_firka']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['territory_firka'] ?: 'To be set by Company')); ?></span></div>
                        <div class="agr-field-row"><label>Territory Code:</label><span class="agr-value<?php echo empty($agreement['territory_code']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['territory_code'] ?: '—')); ?></span></div>
                        <div class="agr-field-row"><label>Effective Date:</label><span class="agr-value<?php echo empty($agreement['effective_date']) ? ' agr-blank' : ''; ?>"><?php echo $agreement['effective_date'] ? fv(date('d-m-Y', strtotime($agreement['effective_date']))) : 'To be set by Company'; ?></span></div>
                        <div class="agr-field-row"><label>Approved Monthly Purchase Commitment:</label><span class="agr-value<?php echo $agreement['monthly_purchase_commitment'] === null ? ' agr-blank' : ''; ?>"><?php echo $agreement['monthly_purchase_commitment'] !== null ? 'Rs. ' . fv(number_format((float)$agreement['monthly_purchase_commitment'], 2)) . ' per Month' : 'To be set by Company'; ?></span></div>
                        <div class="agr-field-row"><label>Other Approved Commercial Particulars:</label><span class="agr-value<?php echo empty($agreement['other_particulars']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($agreement['other_particulars'] ?: '—')); ?></span></div>

                        <h2>EXECUTION</h2>
                        <p><strong>FOR FEMI9 LLP</strong></p>
                        <div class="agr-field-row"><label>Authorized Signatory Name:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_name'] ?: 'To be set by Company'); ?></span></div>
                        <div class="agr-field-row"><label>Designation:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_designation']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_designation'] ?: 'To be set by Company'); ?></span></div>
                        <?php if (!empty($settings['authorized_signatory_signature'])): ?>
                        <div class="agr-field-row"><label>Signature:</label><span class="agr-value"><img src="<?php echo $printMode ? fv(__DIR__ . '/company_signature/' . $settings['authorized_signatory_signature']) : 'company_signature/' . fv($settings['authorized_signatory_signature']); ?>" class="agr-sig-img"></span></div>
                        <?php endif; ?>

                        <p style="margin-top:18px;"><strong>FOR THE TERRITORY PARTNER</strong></p>
                        <div class="agr-field-row"><label>Name:</label><span class="agr-value"><?php echo fv($tp['name']); ?></span></div>
                        <div class="agr-field-row"><label>Designation:</label><span class="agr-value<?php echo empty($agreement['tp_designation']) ? ' agr-blank' : ''; ?>"><?php echo fv($agreement['tp_designation'] ?: '—'); ?></span></div>
                        <div class="agr-field-row"><label>Signature:</label>
                            <?php if (!empty($agreement['tp_signature'])): ?>
                                <img src="<?php echo fv($agreement['tp_signature']); ?>" class="agr-sig-img">
                            <?php else: ?>
                                <span class="agr-value agr-blank">Not yet signed</span>
                            <?php endif; ?>
                        </div>
                        <div class="agr-field-row"><label>PAN Card:</label>
                            <?php if (!empty($agreement['pan_card_path'])): ?>
                                <a href="../territory-partner/kyc_documents/<?php echo fv($agreement['pan_card_path']); ?>" target="_blank" class="agr-value">View Uploaded PAN Card</a>
                                <?php if (pan_card_is_displayable_image($agreement['pan_card_path'])): ?>
                                    <img src="../territory-partner/kyc_documents/<?php echo fv($agreement['pan_card_path']); ?>" class="agr-pan-img">
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="agr-value agr-blank">Not uploaded</span>
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
                        <p>By signing this Agreement, the Parties acknowledge that they have read, understood and voluntarily accepted all the terms and conditions contained herein and agree to be legally bound by the provisions of this Territory Partner Agreement.</p>
                        <?php if (!$printMode): ?><?php render_agreement_letterhead_footer(); ?><?php endif; ?>
                    </div>
                    <?php $__agrDocHtml = ob_get_clean(); echo $__agrDocHtml; ?>

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
<?php
$__fullPage = ob_get_clean();
if ($printMode) {
    // See territory-partner/agreement.php's identical $printMode branch for
    // why this renders a real PDF via dompdf instead of browser print.
    require_once __DIR__ . '/../../vendor/autoload.php';

    $headerImg = __DIR__ . '/../../assets/images/agreement-letterhead-header.png';
    $footerImg = __DIR__ . '/../../assets/images/agreement-letterhead-footer.png';
    [$hImgW, $hImgH] = getimagesize($headerImg);
    [$fImgW, $fImgH] = getimagesize($footerImg);

    // The letterhead images are drawn full-bleed (page-edge to page-edge,
    // no side margin); only the TEXT content gets $marginSidePt via @page.
    $pageWidthPt  = 595.28; // A4
    $marginSidePt = 54;
    $textGapPt    = 12;
    $headerHPt    = $pageWidthPt * ($hImgH / $hImgW);
    $footerHPt    = $pageWidthPt * ($fImgH / $fImgW);
    $marginTopPt    = $headerHPt + $textGapPt;
    $marginBottomPt = $footerHPt + $textGapPt;

    // See territory-partner/agreement.php's identical $calibriAvailable for
    // why Calibri is registered by reference to the system install instead
    // of a copy bundled into the repo (licensed Microsoft font).
    $calibriDir = 'C:/Windows/Fonts/';
    $calibriAvailable = is_file($calibriDir . 'calibri.ttf');

    // See wrap_headings_with_next_block_for_pdf()'s own comment in
    // AgreementService.php for why this, not just a CSS "h2 + p" rule, is
    // needed to reliably keep a clause heading from being stranded alone
    // at the bottom of a page.
    $pdfBodyHtml = inline_pan_card_image_for_pdf($__agrDocHtml, __DIR__ . '/../territory-partner/kyc_documents');
    $pdfBodyHtml = wrap_headings_with_next_block_for_pdf($pdfBodyHtml);

    $pdfHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        @page { size: A4; margin: ' . $marginTopPt . 'pt ' . $marginSidePt . 'pt ' . $marginBottomPt . 'pt ' . $marginSidePt . 'pt; }
        body { margin:0; background:#ffffff; font-family: "Calibri", "DejaVu Sans", sans-serif; font-size:12px; line-height:1.6; color:#1f2937; }
        .agr-doc { font-size:12px; }
        .agr-doc h1 { font-size:18px; text-align:center; margin-bottom:20px; font-family: "Calibri", "DejaVu Sans", sans-serif; font-weight:bold; }
        .agr-doc h2 { font-size:14px; margin:22px 0 8px; page-break-after:avoid; page-break-inside:avoid; font-family: "Calibri", "DejaVu Sans", sans-serif; font-weight:bold; }
        .agr-doc h2 + p { page-break-before:avoid; }
        .agr-pdf-keeptogether { page-break-inside: avoid; }
        .agr-doc p { orphans:3; widows:3; }
        .agr-field-row { display:table; width:100%; margin:10px 0; }
        .agr-field-row label { display:table-cell; font-weight:bold; width:200px; padding-right:8px; font-family: "Calibri", "DejaVu Sans", sans-serif; }
        .agr-field-row .agr-value { display:table-cell; border-bottom:1px solid #9ca3af; padding:2px 4px; }
        .agr-value { color:#374151; }
        .agr-value.agr-blank { color:#9ca3af; font-style:italic; }
        .agr-ul { margin:8px 0 8px 20px; }
        .agr-witness-box { border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; margin-top:14px; }
        .agr-sig-img { max-width:200px; max-height:44px; }
        .agr-pan-img { max-width:320px; max-height:220px; display:block; margin-top:4px; border:1px solid #e5e7eb; }
        </style></head><body>' . $pdfBodyHtml . '</body></html>';

    $pdfOptions = new \Dompdf\Options();
    $pdfOptions->set('isRemoteEnabled', true);
    // See territory-partner/agreement.php's identical setChroot() call for
    // why this is needed -- without it, dompdf silently rejects the PAN
    // card <img> (and any other local image) with a broken-image icon.
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
