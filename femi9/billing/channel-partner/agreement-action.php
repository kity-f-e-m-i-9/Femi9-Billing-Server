<?php
include("checksession.php");
require_once __DIR__ . '/../shared/AgreementService.php';
include("config.php");
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: agreement.php');
    exit;
}

$cp_id = (int) $Login_user_IDvl;
ensure_agreement_tables($db_conn);

// Refuse if already locked — never overwrite a submitted, signed agreement.
$check = $db_conn->prepare("SELECT is_locked FROM channel_partner_agreements WHERE channel_partner_id = ?");
$check->bind_param('i', $cp_id);
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
$depositMode    = trim($_POST['deposit_mode'] ?? '');
$depositRef     = trim($_POST['deposit_txn_ref'] ?? '');
$cpDesignation  = trim($_POST['cp_designation'] ?? '');
$cpSignature    = $_POST['cp_signature'] ?? '';
$w1Name         = trim($_POST['witness1_name'] ?? '');
$w1Address      = trim($_POST['witness1_address'] ?? '');
$w1Signature    = $_POST['witness1_signature'] ?? '';
$w2Name         = trim($_POST['witness2_name'] ?? '');
$w2Address      = trim($_POST['witness2_address'] ?? '');
$w2Signature    = $_POST['witness2_signature'] ?? '';

// Only Place and the CP's own signature are mandatory — witnesses are
// optional. Confirmed 2026-09-28.
if ($agreementPlace === '' || $cpSignature === '') {
    $_SESSION['errorMessage'] = "Please fill in the Place and provide your signature before submitting.";
    header('Location: agreement.php');
    exit;
}

get_or_create_cp_agreement($db_conn, $cp_id); // ensures a row exists to UPDATE

$stmt = $db_conn->prepare(
    "UPDATE channel_partner_agreements SET
        agreement_date = ?, agreement_place = ?, deposit_mode = ?, deposit_txn_ref = ?,
        cp_designation = ?, cp_signature = ?,
        witness1_name = ?, witness1_address = ?, witness1_signature = ?,
        witness2_name = ?, witness2_address = ?, witness2_signature = ?,
        signed_at = NOW(), is_locked = 1
     WHERE channel_partner_id = ?"
);
$stmt->bind_param(
    'ssssssssssssi',
    $agreementDate, $agreementPlace, $depositMode, $depositRef,
    $cpDesignation, $cpSignature,
    $w1Name, $w1Address, $w1Signature,
    $w2Name, $w2Address, $w2Signature,
    $cp_id
);
$stmt->execute();
$stmt->close();

$_SESSION['sucMessage'] = "Agreement signed and submitted successfully.";
header('Location: agreement.php');
exit;
