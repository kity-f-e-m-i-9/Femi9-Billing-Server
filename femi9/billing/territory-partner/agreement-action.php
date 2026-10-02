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

get_or_create_tp_agreement($db_conn, $tp_id);

$stmt = $db_conn->prepare(
    "UPDATE territory_partner_agreements SET
        agreement_date = ?, agreement_place = ?,
        tp_designation = ?, tp_signature = ?,
        witness1_name = ?, witness1_address = ?, witness1_signature = ?,
        witness2_name = ?, witness2_address = ?, witness2_signature = ?,
        signed_at = NOW(), is_locked = 1
     WHERE territory_partner_id = ?"
);
$stmt->bind_param(
    'ssssssssssi',
    $agreementDate, $agreementPlace,
    $tpDesignation, $tpSignature,
    $w1Name, $w1Address, $w1Signature,
    $w2Name, $w2Address, $w2Signature,
    $tp_id
);
$stmt->execute();
$stmt->close();

$_SESSION['sucMessage'] = "Agreement signed and submitted successfully.";
header('Location: agreement.php');
exit;
