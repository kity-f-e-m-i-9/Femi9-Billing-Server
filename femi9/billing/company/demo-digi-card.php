<?php
// Renders a DEMO Digi Card PNG from whatever signature the demo page's own
// in-browser signature pad just drew -- same _dcRenderPartnerCard() image
// generator real/signed Digi Cards use (see shared/DigiCardGenerator.php),
// fed fake partner details instead of a real territory_partners /
// channel_partners row. Nothing here reads or writes any real partner data;
// the posted signature data URI only ever exists in this one request.
include("checksession.php");
require_once("include/GodownAccess.php");
require_once __DIR__ . '/../shared/AgreementService.php';
require_once __DIR__ . '/../shared/DigiCardGenerator.php';
include("config.php");
error_reporting(0);

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['admin', 'finance'], true)) {
    http_response_code(403);
    exit;
}

$type = ($_POST['type'] ?? 'tp') === 'cp' ? 'cp' : 'tp';
$signature = $_POST['signature'] ?? '';
if (!str_starts_with($signature, 'data:image')) {
    http_response_code(400);
    exit;
}

$settings = get_agreement_settings($db_conn);
$companySigPath = !empty($settings['authorized_signatory_signature'])
    ? (__DIR__ . '/company_signature/' . $settings['authorized_signatory_signature'])
    : null;

if ($type === 'cp') {
    $img = _dcRenderPartnerCard([
        'titleText'             => 'AUTHORIZED CHANNEL PARTNER',
        'partnerName'           => 'Demo Channel Partner',
        'photoPath'             => null,
        'idLabel'               => 'Channel Partner ID',
        'idValue'               => 'CP-DEMO',
        'itemsLabel'            => 'Divisions name',
        'itemPairs'             => [['Demo Division', 'Demo District']],
        'fallbackDistrict'      => 'Demo District',
        'companySigPath'        => $companySigPath,
        'companySigName'        => $settings['authorized_signatory_name'] ?? '',
        'companySigDesignation' => $settings['authorized_signatory_designation'] ?? '',
        'partnerSigSource'      => $signature,
        'partnerRoleLabel'      => 'Authorized Channel Partner',
        'partnerDesignation'    => trim($_POST['designation'] ?? ''),
    ]);
} else {
    $img = _dcRenderPartnerCard([
        'titleText'             => 'AUTHORIZED TERRITORY PARTNER',
        'partnerName'           => 'Demo Territory Partner',
        'photoPath'             => null,
        'idLabel'               => 'Territory Partner ID',
        'idValue'               => 'TP-DEMO',
        'itemsLabel'            => 'Frikas name',
        'itemPairs'             => [['Demo Firka', 'Demo District']],
        'fallbackDistrict'      => 'Demo District',
        'companySigPath'        => $companySigPath,
        'companySigName'        => $settings['authorized_signatory_name'] ?? '',
        'companySigDesignation' => $settings['authorized_signatory_designation'] ?? '',
        'partnerSigSource'      => $signature,
        'partnerRoleLabel'      => 'Authorized Territory Partner',
        'partnerDesignation'    => trim($_POST['designation'] ?? ''),
    ]);
}

if (!$img) {
    http_response_code(500);
    exit;
}
header('Content-Type: image/png');
imagepng($img);
imagedestroy($img);
