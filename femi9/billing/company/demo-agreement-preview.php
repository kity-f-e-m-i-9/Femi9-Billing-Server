<?php
// Read-only "how will this look to a partner" preview for Manage
// Agreements' Company Settings form — lets Company see the effect of a
// Company Address / Authorized Signatory edit (even before it's saved)
// against a fake Demo Territory Partner / Demo Channel Partner, without
// touching any real partner's row. The sign-flow below (signature pad,
// Digi Card, Accept & Sign) is a fully working VISUAL simulation of the
// real one using the same widgets/generator -- it never POSTs to
// agreement-action.php and never touches territory_partners /
// channel_partners / *_agreements, so there is nothing here to actually
// "sign".
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

function fv($val) { return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8'); }

$type = ($_POST['type'] ?? $_GET['type'] ?? 'tp') === 'cp' ? 'cp' : 'tp';

// Start from the real saved Company Settings, then let any posted (even
// not-yet-saved) field override just that one value -- so typing only in
// "Authorized Signatory Name" still shows the real saved Address, not a
// blank one.
$settings = get_agreement_settings($db_conn);
foreach (['company_address', 'authorized_signatory_name', 'authorized_signatory_designation'] as $__k) {
    if (isset($_POST[$__k]) && trim($_POST[$__k]) !== '') {
        $settings[$__k] = $_POST[$__k];
    }
}

// Fake partner -- never read from or written to territory_partners /
// channel_partners. Schedule-1 particulars are shown as "To be set by
// Company" placeholders, same as a genuinely brand-new, unconfigured
// partner would see.
if ($type === 'cp') {
    $partner = [
        'name' => 'Demo Channel Partner', 'cp_id' => 'CP-DEMO', 'company_name' => 'Demo Enterprises',
        'mobile' => '9999999999', 'email' => 'demo.cp@example.com',
        'branch_line1' => '123 Demo Street', 'branch_line2' => 'Demo Layout', 'branch_city' => 'Demo City',
        'branch_district' => 'Demo District', 'branch_state' => 'Tamil Nadu', 'branch_country' => 'India', 'branch_pincode' => '600001',
    ];
    $title = 'FEMI9 LLP DIVISION CHANNEL PARTNER AGREEMENT';
    $bodyType = 'channel_partner';
} else {
    $partner = [
        'name' => 'Demo Territory Partner', 'tp_id' => 'TP-DEMO', 'company_name' => 'Demo Traders',
        'mobile' => '9999999999', 'email' => 'demo.tp@example.com',
        'branch_line1' => '123 Demo Street', 'branch_line2' => 'Demo Layout', 'branch_city' => 'Demo City',
        'branch_district' => 'Demo District', 'branch_state' => 'Tamil Nadu', 'branch_country' => 'India', 'branch_pincode' => '600001',
        'assigned_district' => 'Demo District',
    ];
    $title = 'FEMI9 LLP - TERRITORY PARTNER AGREEMENT';
    $bodyType = 'territory_partner';
}

$effectiveBody = get_effective_agreement_body($db_conn, [], $bodyType);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demo Agreement Preview : <?php echo fv($business_name ?? 'Femi9'); ?></title>
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; background:#f3f4f6; }
        .demo-banner {
            position: sticky; top: 0; z-index: 10;
            background: linear-gradient(135deg,#f59e0b,#d97706); color:#fff;
            padding: 12px 20px; font-weight: 700; font-size: 14px;
            display: flex; align-items: center; gap: 10px;
        }
        .agr-doc { max-width:820px; margin:20px auto; background:#fff; padding:36px 44px; border:1px solid #e5e7eb; border-radius:10px; font-size:13.5px; line-height:1.6; color:#1f2937; }
        .agr-doc h1 { font-size:18px; text-align:center; margin-bottom:20px; }
        .agr-doc h2 { font-size:14.5px; margin:22px 0 8px; }
        .agr-field-row { display:flex; align-items:center; gap:8px; margin:10px 0; flex-wrap:wrap; }
        .agr-field-row label { font-weight:600; min-width:220px; }
        .agr-value { flex:1; min-width:180px; border-bottom:1px solid #9ca3af; padding:2px 4px; color:#374151; }
        .agr-value.agr-blank { color:#9ca3af; font-style:italic; }
        .agr-ul { margin:8px 0 8px 20px; }
    </style>
</head>
<body>
    <div class="demo-banner">
        <span>&#128065; DEMO PREVIEW — <?php echo $type === 'cp' ? 'Channel Partner' : 'Territory Partner'; ?> agreement, shown with a fake "<?php echo fv($partner['name']); ?>" (<?php echo fv($partner[$type === 'cp' ? 'cp_id' : 'tp_id']); ?>). Nothing here is saved or sent to any real partner.</span>
        <a href="manage-agreements.php" class="btn btn-sm btn-light" style="margin-left:auto;">&larr; Back to Manage Agreements</a>
    </div>

    <div class="agr-doc">
        <h1><?php echo fv($title); ?></h1>

        <p>This <?php echo $type === 'cp' ? 'Division Channel Partner' : 'Territory Partner'; ?> Agreement (hereinafter referred to as the "Agreement") is made and executed on the date and at the place specified below and shall become effective from the Effective Date recorded in Schedule&nbsp;&ndash;&nbsp;1.</p>

        <div class="agr-field-row"><label>DATE:</label><span class="agr-value agr-blank">To be set when signed</span></div>
        <div class="agr-field-row"><label>PLACE:</label><span class="agr-value agr-blank">To be set when signed</span></div>

        <h2>BETWEEN</h2>
        <p><strong>FEMI9 LLP</strong>, a Limited Liability Partnership duly incorporated under the provisions of the Limited Liability Partnership Act, 2008, having its Registered Office / Principal Place of Business at:</p>
        <p class="agr-value<?php echo empty($settings['company_address']) ? ' agr-blank' : ''; ?>"><?php echo nl2br(fv($settings['company_address'] ?: 'To be filled by Company')); ?></p>
        <p>(hereinafter referred to as "FEMI9" or the "Company", which expression shall, unless repugnant to the context or meaning thereof, include its successors, legal representatives, permitted assigns and administrators);</p>

        <h2>AND</h2>
        <p><strong><?php echo $type === 'cp' ? 'CHANNEL PARTNER' : 'TERRITORY PARTNER'; ?></strong></p>
        <div class="agr-field-row"><label>Name:</label><span class="agr-value"><?php echo fv($partner['name']); ?></span></div>
        <div class="agr-field-row"><label>Business / Entity Name:</label><span class="agr-value"><?php echo fv($partner['company_name']); ?></span></div>
        <div class="agr-field-row"><label>Address:</label><span class="agr-value"><?php echo fv(partner_billing_address_line($partner)); ?></span></div>
        <div class="agr-field-row"><label>Mobile Number:</label><span class="agr-value"><?php echo fv($partner['mobile']); ?></span></div>
        <div class="agr-field-row"><label>Email Address:</label><span class="agr-value"><?php echo fv($partner['email']); ?></span></div>
        <p>(hereinafter referred to as the "<?php echo $type === 'cp' ? 'Channel Partner" or "CP"' : 'Territory Partner" or "TP"'; ?>", which expression shall, unless repugnant to the context or meaning thereof, include, where applicable, its successors, legal representatives, heirs, executors, administrators and permitted assigns).</p>
        <p>FEMI9 and the <?php echo $type === 'cp' ? 'Channel' : 'Territory'; ?> Partner are hereinafter individually referred to as a "Party" and collectively as the "Parties."</p>

        <?php echo $effectiveBody; ?>

        <h2>SCHEDULE &ndash; 1</h2>
        <p>The following particulars shall form an integral part of this Agreement.</p>
        <div class="agr-field-row"><label><?php echo $type === 'cp' ? 'Channel' : 'Territory'; ?> Partner Name:</label><span class="agr-value"><?php echo fv($partner['name']); ?></span></div>
        <div class="agr-field-row"><label><?php echo $type === 'cp' ? 'Channel' : 'Territory'; ?> Partner ID / Code:</label><span class="agr-value"><?php echo fv($partner[$type === 'cp' ? 'cp_id' : 'tp_id']); ?></span></div>
        <div class="agr-field-row"><label>Effective Date:</label><span class="agr-value agr-blank">To be set by Company</span></div>
        <p class="text-muted" style="font-size:12px;">(Remaining Schedule-1 particulars — District, Firka/Division, Commitment/Deposit — are shown as "To be set by Company" for every new partner; this demo skips them for brevity.)</p>

        <h2>EXECUTION</h2>
        <p><strong>FOR FEMI9 LLP</strong></p>
        <div class="agr-field-row"><label>Authorized Signatory Name:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_name']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_name'] ?: 'To be set by Company'); ?></span></div>
        <div class="agr-field-row"><label>Designation:</label><span class="agr-value<?php echo empty($settings['authorized_signatory_designation']) ? ' agr-blank' : ''; ?>"><?php echo fv($settings['authorized_signatory_designation'] ?: 'To be set by Company'); ?></span></div>
        <?php if (!empty($settings['authorized_signatory_signature'])): ?>
        <div class="agr-field-row"><label>Signature:</label><span class="agr-value"><img src="company_signature/<?php echo fv($settings['authorized_signatory_signature']); ?>" style="max-height:44px;max-width:200px;"></span></div>
        <?php endif; ?>

        <p style="margin-top:18px;"><strong>FOR THE <?php echo $type === 'cp' ? 'CHANNEL' : 'TERRITORY'; ?> PARTNER</strong></p>
        <div class="agr-field-row"><label>Name:</label><span class="agr-value"><?php echo fv($partner['name']); ?></span></div>

        <div id="demoPreSign">
            <div class="agr-field-row"><label>PAN Card:</label>
                <input type="file" disabled class="no-print" style="max-width:260px;">
                <span class="text-muted" style="font-size:12px;">(demo only — upload disabled)</span>
            </div>
            <div style="margin:10px 0;">
                <label style="font-weight:600;display:block;margin-bottom:6px;">Signature:</label>
                <div id="demoSigContainer"></div>
                <input type="hidden" name="demo_signature" id="demo_signature_input">
            </div>
            <div style="margin-top:24px;text-align:center;">
                <button type="button" id="demoSignBtn" class="btn btn-success" style="padding:10px 28px;font-weight:600;" onclick="demoAcceptAndSign()">Accept &amp; Sign Agreement</button>
            </div>
            <p class="text-muted text-center" style="font-size:12px;margin-top:6px;">Demo only — nothing here is saved or sent anywhere.</p>
        </div>

        <div id="demoPostSign" style="display:none;">
            <div class="agr-field-row"><label>Signature:</label><span class="agr-value"><img id="demoSignedImg" class="agr-sig-img" style="max-height:60px;"></span></div>
            <div style="margin:16px 0;">
                <label style="display:block;margin-bottom:8px;font-weight:600;">Digi Card:</label>
                <img id="demoDigiCardImg" style="max-width:100%;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.15);">
            </div>
            <p class="text-success text-center" style="font-weight:600;">&#10003; Demo signed — this is only a preview, nothing was actually saved.</p>
        </div>
    </div>

    <script src="../../assets/js/signature-pad.js"></script>
    <script>
        initSignaturePad('demoSigContainer', 'demo_signature_input');

        function demoAcceptAndSign() {
            var sig = document.getElementById('demo_signature_input').value;
            if (!sig) {
                alert('Draw and Save your signature first (same as a real partner would have to).');
                return;
            }
            document.getElementById('demoSignedImg').src = sig;

            var fd = new FormData();
            fd.append('type', <?php echo json_encode($type); ?>);
            fd.append('signature', sig);
            fetch('demo-digi-card.php', { method: 'POST', body: fd })
                .then(function (r) { return r.blob(); })
                .then(function (blob) {
                    document.getElementById('demoDigiCardImg').src = URL.createObjectURL(blob);
                    document.getElementById('demoPreSign').style.display = 'none';
                    document.getElementById('demoPostSign').style.display = 'block';
                    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
                })
                .catch(function () { alert('Demo Digi Card preview failed to render.'); });
        }
    </script>
</body>
</html>
