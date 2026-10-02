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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage-agreements.php');
    exit;
}

ensure_agreement_tables($db_conn);
$updatedBy = $_SESSION['LOGIN_USER'] ?? 'system';
$action = $_POST['action'] ?? '';

if ($action === 'save_settings') {
    save_agreement_settings(
        $db_conn,
        trim($_POST['company_address'] ?? ''),
        trim($_POST['authorized_signatory_name'] ?? ''),
        trim($_POST['authorized_signatory_designation'] ?? ''),
        $updatedBy
    );
    $_SESSION['sucMessage'] = "Company settings saved.";
    header('Location: manage-agreements.php');
    exit;
}

if ($action === 'save_cp_schedule') {
    $cpId = (int) ($_POST['cp_id'] ?? 0);
    $existing = get_or_create_cp_agreement($db_conn, $cpId);
    $wasSignedAndLocked = !empty($existing['signed_at']) && (int) ($existing['is_locked'] ?? 0) === 1;
    $securityDeposit = ($_POST['security_deposit'] ?? '') !== '' ? (float) $_POST['security_deposit'] : null;
    $effectiveDate   = ($_POST['effective_date'] ?? '') !== '' ? $_POST['effective_date'] : null;
    // A per-partner wording override is optional -- the "use custom wording"
    // checkbox decides whether this partner's own custom_body_html is kept
    // (NULL falls back to the shared master template, see
    // get_effective_agreement_body()).
    $useCustomBody = ($_POST['use_custom_body'] ?? '') === '1';
    $customBodyHtml = $useCustomBody ? ($_POST['custom_body_html'] ?? '') : null;
    // Editing an already-signed agreement's particulars auto-unlocks it --
    // the CP's own agreement.php then shows it as editable again and their
    // dashboard surfaces a "please review & sign again" banner (see
    // agreement_needs_resign() in AgreementService.php) -- the old signed
    // copy on file no longer matches these new terms, so it can't stay
    // "locked" as if nothing changed.
    $stmt = $db_conn->prepare(
        "UPDATE channel_partner_agreements SET
            security_deposit = ?, approved_divisions = ?, division_codes = ?,
            approved_warehouse_address = ?, stock_holding_capacity = ?, commercial_category = ?,
            effective_date = ?, other_particulars = ?, custom_body_html = ?, schedule_set_by = ?, schedule_set_at = NOW(),
            is_locked = 0
         WHERE channel_partner_id = ?"
    );
    $approvedDivisions = trim($_POST['approved_divisions'] ?? '');
    $divisionCodes = trim($_POST['division_codes'] ?? '');
    $warehouseAddress = trim($_POST['approved_warehouse_address'] ?? '');
    $stockCapacity = trim($_POST['stock_holding_capacity'] ?? '');
    $commercialCategory = trim($_POST['commercial_category'] ?? '');
    $otherParticulars = trim($_POST['other_particulars'] ?? '');
    $stmt->bind_param(
        'dsssssssssi',
        $securityDeposit, $approvedDivisions, $divisionCodes,
        $warehouseAddress, $stockCapacity, $commercialCategory,
        $effectiveDate, $otherParticulars, $customBodyHtml, $updatedBy, $cpId
    );
    $stmt->execute();
    $stmt->close();
    $_SESSION['sucMessage'] = $wasSignedAndLocked
        ? "Channel Partner Schedule-1 particulars saved. The agreement was already signed, so it has been unlocked for re-signing with the updated terms."
        : "Channel Partner Schedule-1 particulars saved.";
    header('Location: manage-agreements.php?cp_id=' . $cpId);
    exit;
}

if ($action === 'save_tp_schedule') {
    $tpId = (int) ($_POST['tp_id'] ?? 0);
    $existingTp = get_or_create_tp_agreement($db_conn, $tpId);
    $wasSignedAndLockedTp = !empty($existingTp['signed_at']) && (int) ($existingTp['is_locked'] ?? 0) === 1;
    $commitment = ($_POST['monthly_purchase_commitment'] ?? '') !== '' ? (float) $_POST['monthly_purchase_commitment'] : null;
    $effectiveDate = ($_POST['effective_date'] ?? '') !== '' ? $_POST['effective_date'] : null;
    $talukBlock = trim($_POST['taluk_block'] ?? '');
    $territoryFirka = trim($_POST['territory_firka'] ?? '');
    $territoryCode = trim($_POST['territory_code'] ?? '');
    $otherParticulars = trim($_POST['other_particulars'] ?? '');
    // Same optional per-partner wording override as save_cp_schedule above.
    $useCustomBodyTp = ($_POST['use_custom_body'] ?? '') === '1';
    $customBodyHtmlTp = $useCustomBodyTp ? ($_POST['custom_body_html'] ?? '') : null;
    // Same auto-unlock-on-change reasoning as save_cp_schedule above.
    $stmt = $db_conn->prepare(
        "UPDATE territory_partner_agreements SET
            monthly_purchase_commitment = ?, taluk_block = ?, territory_firka = ?,
            territory_code = ?, effective_date = ?, other_particulars = ?, custom_body_html = ?,
            schedule_set_by = ?, schedule_set_at = NOW(),
            is_locked = 0
         WHERE territory_partner_id = ?"
    );
    $stmt->bind_param(
        'dsssssssi',
        $commitment, $talukBlock, $territoryFirka,
        $territoryCode, $effectiveDate, $otherParticulars, $customBodyHtmlTp,
        $updatedBy, $tpId
    );
    $stmt->execute();
    $stmt->close();
    $_SESSION['sucMessage'] = $wasSignedAndLockedTp
        ? "Territory Partner Schedule-1 particulars saved. The agreement was already signed, so it has been unlocked for re-signing with the updated terms."
        : "Territory Partner Schedule-1 particulars saved.";
    header('Location: manage-agreements.php?tp_id=' . $tpId);
    exit;
}

if ($action === 'save_cp_body_template' || $action === 'save_tp_body_template') {
    $type = $action === 'save_cp_body_template' ? 'channel_partner' : 'territory_partner';
    $bodyHtml = $_POST['body_html'] ?? '';
    save_agreement_body_template($db_conn, $type, $bodyHtml, $updatedBy);
    // The shared wording just changed for every partner of this type --
    // same auto-unlock-on-change reasoning as the per-partner schedule
    // saves above, just applied in bulk: every already-signed copy on file
    // no longer matches these new terms.
    $table = $type === 'channel_partner' ? 'channel_partner_agreements' : 'territory_partner_agreements';
    $db_conn->query("UPDATE {$table} SET is_locked = 0 WHERE signed_at IS NOT NULL AND is_locked = 1");
    $_SESSION['sucMessage'] = ($type === 'channel_partner' ? 'Channel Partner' : 'Territory Partner')
        . " agreement wording saved. Every already-signed " . ($type === 'channel_partner' ? 'CP' : 'TP')
        . " agreement has been unlocked for re-signing with the updated wording.";
    header('Location: manage-agreements.php' . ($type === 'channel_partner' ? '' : '?tab=tp'));
    exit;
}

if ($action === 'unlock_cp') {
    $cpId = (int) ($_POST['cp_id'] ?? 0);
    $stmt = $db_conn->prepare("UPDATE channel_partner_agreements SET is_locked = 0 WHERE channel_partner_id = ?");
    $stmt->bind_param('i', $cpId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['sucMessage'] = "Agreement unlocked — the Channel Partner can now re-sign.";
    header('Location: manage-agreements.php?cp_id=' . $cpId);
    exit;
}

if ($action === 'unlock_tp') {
    $tpId = (int) ($_POST['tp_id'] ?? 0);
    $stmt = $db_conn->prepare("UPDATE territory_partner_agreements SET is_locked = 0 WHERE territory_partner_id = ?");
    $stmt->bind_param('i', $tpId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['sucMessage'] = "Agreement unlocked — the Territory Partner can now re-sign.";
    header('Location: manage-agreements.php?tp_id=' . $tpId);
    exit;
}

header('Location: manage-agreements.php');
exit;
