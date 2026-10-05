<?php
include("checksession.php");
require_once __DIR__ . '/../shared/AgreementService.php';
include("config.php");
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: agreement.php');
    exit;
}

$tp_id = (int) $Login_user_IDvl;
ensure_agreement_tables($db_conn);

$check = $db_conn->prepare("SELECT is_locked FROM territory_partner_agreements WHERE territory_partner_id = ?");
$check->bind_param('i', $tp_id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();
if ($existing && (int) $existing['is_locked'] === 1) {
    $_SESSION['errorMessage'] = "This agreement has already been signed and cannot be edited.";
    header('Location: agreement.php');
    exit;
}

$agreementDate  = $_POST['agreement_date'] ?? date('Y-m-d');
$agreementPlace = trim($_POST['agreement_place'] ?? '');
$tpDesignation  = trim($_POST['tp_designation'] ?? '');
$tpSignature    = $_POST['tp_signature'] ?? '';
$w1Name         = trim($_POST['witness1_name'] ?? '');
$w1Address      = trim($_POST['witness1_address'] ?? '');
$w1Signature    = $_POST['witness1_signature'] ?? '';
$w2Name         = trim($_POST['witness2_name'] ?? '');
$w2Address      = trim($_POST['witness2_address'] ?? '');
$w2Signature    = $_POST['witness2_signature'] ?? '';

// Only Place and the TP's own signature are mandatory — witnesses are
// optional (a witness's name/address/signature can each be filled in
// independently of the others; nothing here requires both witnesses, or
// requires a witness's signature to match a filled name). Confirmed 2026-09-28.
if ($agreementPlace === '' || $tpSignature === '') {
    $_SESSION['errorMessage'] = "Please fill in the Place and provide your signature before submitting.";
    header('Location: agreement.php');
    exit;
}

$existingAgreement = get_or_create_tp_agreement($db_conn, $tp_id);

// PAN card: required once, kept as-is on re-sign if no new file is chosen.
$panCardPath = $existingAgreement['pan_card_path'] ?? null;
if (!empty($_FILES['pan_card']['name'])) {
    $ext = strtolower(pathinfo($_FILES['pan_card']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
        $_SESSION['errorMessage'] = "PAN card must be a JPG, PNG or PDF file.";
        header('Location: agreement.php');
        exit;
    }
    if ($_FILES['pan_card']['size'] > 5 * 1024 * 1024) {
        $_SESSION['errorMessage'] = "PAN card file must be under 5 MB.";
        header('Location: agreement.php');
        exit;
    }
    $uploadDir = __DIR__ . '/kyc_documents/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $newName = 'pan_tp_' . $tp_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (move_uploaded_file($_FILES['pan_card']['tmp_name'], $uploadDir . $newName)) {
        $panCardPath = $newName;
    }
}
if (empty($panCardPath)) {
    $_SESSION['errorMessage'] = "Please upload your PAN card before submitting.";
    header('Location: agreement.php');
    exit;
}

// Snapshot the TP's own profile fields as they stand right now, so a later
// edit by the Company (e.g. via Manage Territory Partner) can be detected
// and highlighted on this page next time it's opened.
$stmtTp = $db_conn->prepare("SELECT name, company_name, address, mobile, email FROM territory_partners WHERE id = ?");
$stmtTp->bind_param('i', $tp_id);
$stmtTp->execute();
$tpProfile = $stmtTp->get_result()->fetch_assoc() ?: [];
$stmtTp->close();
$snapName        = $tpProfile['name'] ?? '';
$snapCompanyName = $tpProfile['company_name'] ?? '';
$snapAddress     = $tpProfile['address'] ?? '';
$snapMobile      = $tpProfile['mobile'] ?? '';
$snapEmail       = $tpProfile['email'] ?? '';

// Snapshot the wording as currently effective (shared template or this TP's
// own override) too, so a later edit to the clauses can be highlighted
// paragraph-by-paragraph next time this page renders.
$snapBodyHtml = get_effective_agreement_body($db_conn, $existingAgreement, 'territory_partner');

$stmt = $db_conn->prepare(
    "UPDATE territory_partner_agreements SET
        agreement_date = ?, agreement_place = ?,
        tp_designation = ?, tp_signature = ?, pan_card_path = ?,
        witness1_name = ?, witness1_address = ?, witness1_signature = ?,
        witness2_name = ?, witness2_address = ?, witness2_signature = ?,
        snap_name = ?, snap_company_name = ?, snap_address = ?, snap_mobile = ?, snap_email = ?,
        snap_body_html = ?,
        signed_at = NOW(), is_locked = 1
     WHERE territory_partner_id = ?"
);
$stmt->bind_param(
    'sssssssssssssssssi',
    $agreementDate, $agreementPlace,
    $tpDesignation, $tpSignature, $panCardPath,
    $w1Name, $w1Address, $w1Signature,
    $w2Name, $w2Address, $w2Signature,
    $snapName, $snapCompanyName, $snapAddress, $snapMobile, $snapEmail,
    $snapBodyHtml,
    $tp_id
);
$stmt->execute();
$stmt->close();

$_SESSION['sucMessage'] = "Agreement signed and submitted successfully.";
header('Location: agreement.php');
exit;
