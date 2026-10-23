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
    // Signature image: optional on every save (the text fields are saved
    // far more often than the signature changes) -- null here tells
    // save_agreement_settings() to leave whatever's already on file alone.
    $signatoryImagePath = null;
    if (!empty($_FILES['authorized_signatory_signature']['name'])) {
        $ext = strtolower(pathinfo($_FILES['authorized_signatory_signature']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $_SESSION['errorMessage'] = "Signature must be a JPG or PNG file.";
            header('Location: manage-agreements.php');
            exit;
        }
        if ($_FILES['authorized_signatory_signature']['size'] > 2 * 1024 * 1024) {
            $_SESSION['errorMessage'] = "Signature file must be under 2 MB.";
            header('Location: manage-agreements.php');
            exit;
        }
        // NOT under shared/ -- that whole directory is blocked from direct
        // web access by its own .htaccess ("Require all denied", since
        // everything else there is a PHP include, never meant to be
        // fetched directly), which silently turned this signature image
        // into a 403/broken-image everywhere it was displayed. This mirrors
        // the public kyc_documents/ folders already used for PAN card
        // uploads instead.
        $uploadDir = __DIR__ . '/company_signature/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $newName = 'company_signature_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($_FILES['authorized_signatory_signature']['tmp_name'], $uploadDir . $newName)) {
            $signatoryImagePath = $newName;
            // A signature photo/scan is usually mostly blank margin around
            // the actual ink; left uncropped it shrinks to an invisible
            // sliver at the small size the signature strip displays at.
            trim_signature_image_whitespace($uploadDir . $newName);
        }
    }

    save_agreement_settings(
        $db_conn,
        trim($_POST['company_address'] ?? ''),
        trim($_POST['authorized_signatory_name'] ?? ''),
        trim($_POST['authorized_signatory_designation'] ?? ''),
        $updatedBy,
        $signatoryImagePath
    );
    $_SESSION['sucMessage'] = "Company settings saved.";
    header('Location: manage-agreements.php');
    exit;
}

if ($action === 'save_cp_schedule') {
    $cpId = (int) ($_POST['cp_id'] ?? 0);
    $existing = get_or_create_cp_agreement($db_conn, $cpId);
    $wasSignedAndLocked = !empty($existing['signed_at']) && (int) ($existing['is_locked'] ?? 0) === 1;
    // Security Deposit and Approved Division(s) are company-editable on
    // manage-agreements.php (requested 2026-10-21, same as the TP side's
    // Target/Taluk/Firka) -- manage-agreements.php's own "Recompute" button
    // re-fills them client-side from "Partner Location"'s own assignment, so
    // they can still be re-synced on demand without this save ever
    // overwriting a deliberate manual edit.
    $securityDeposit = ($_POST['security_deposit'] ?? '') !== ''
        ? (float) str_replace(',', '', $_POST['security_deposit'])
        : null;
    $approvedDivisions = trim($_POST['approved_divisions'] ?? '');

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
    // Approved Monthly Purchase Commitment, Taluk/Block, and Allotted
    // Firka/Territory are company-editable on this form (requested
    // 2026-10-21 -- company wants to hand-adjust these per TP, e.g. a
    // negotiated commitment different from the raw Firka-target sum).
    // manage-agreements.php's own "Recompute from Partner Location" button
    // re-fills them client-side from the same Firka/location sum this
    // handler used to force unconditionally, so they can still be re-synced
    // on demand without this save ever overwriting a deliberate manual edit.
    $commitment = ($_POST['monthly_purchase_commitment'] ?? '') !== ''
        ? (float) str_replace(',', '', $_POST['monthly_purchase_commitment'])
        : null;
    $talukBlock = trim($_POST['taluk_block'] ?? '');
    $territoryFirka = trim($_POST['territory_firka'] ?? '');

    // Territory Code is simply this TP's own tp_id (e.g. "TP-0082").
    $stmtCode = $db_conn->prepare("SELECT tp_id FROM territory_partners WHERE id = ?");
    $stmtCode->bind_param('i', $tpId);
    $stmtCode->execute();
    $territoryCode = $stmtCode->get_result()->fetch_assoc()['tp_id'] ?? '';
    $stmtCode->close();

    $effectiveDate = ($_POST['effective_date'] ?? '') !== '' ? $_POST['effective_date'] : null;
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

    $table = $type === 'channel_partner' ? 'channel_partner_agreements' : 'territory_partner_agreements';
    $idColumn = $type === 'channel_partner' ? 'channel_partner_id' : 'territory_partner_id';

    // Partners the admin ticked in the "N partner(s) have their own custom
    // wording" checklist (manage-agreements.php) -- dropping their
    // custom_body_html back to NULL switches them onto this shared
    // template too, since get_effective_agreement_body() only falls back
    // to the shared template when custom_body_html is empty.
    $resetIds = array_values(array_filter(array_map('intval', explode(',', $_POST['reset_custom_ids'] ?? ''))));
    if ($resetIds) {
        $placeholders = implode(',', array_fill(0, count($resetIds), '?'));
        $types = str_repeat('i', count($resetIds));
        $stmtReset = $db_conn->prepare("UPDATE {$table} SET custom_body_html = NULL WHERE {$idColumn} IN ({$placeholders})");
        $stmtReset->bind_param($types, ...$resetIds);
        $stmtReset->execute();
        $stmtReset->close();
    }

    // Only unlock partners whose EFFECTIVE wording actually changed just
    // now: those still relying on the shared template (custom_body_html
    // empty), plus whoever's custom override was just cleared above. A
    // partner who KEEPS their own custom wording is untouched by this
    // save -- their already-signed copy still matches what they actually
    // signed, so leaving them locked is correct, not an oversight.
    $db_conn->query("UPDATE {$table} SET is_locked = 0 WHERE signed_at IS NOT NULL AND is_locked = 1 AND (custom_body_html IS NULL OR custom_body_html = '')");

    $_SESSION['sucMessage'] = ($type === 'channel_partner' ? 'Channel Partner' : 'Territory Partner')
        . " agreement wording saved."
        . ($resetIds ? ' ' . count($resetIds) . ' partner(s) switched back to the shared wording.' : '')
        . " Every already-signed " . ($type === 'channel_partner' ? 'CP' : 'TP')
        . " agreement using this shared wording has been unlocked for re-signing with the updated wording.";
    header('Location: manage-agreements.php' . ($type === 'channel_partner' ? '' : '?tab=tp'));
    exit;
}

if ($action === 'toggle_tp_sign_enforced' || $action === 'toggle_cp_sign_enforced') {
    $type = $action === 'toggle_cp_sign_enforced' ? 'cp' : 'tp';
    $enforced = ($_POST['enforced'] ?? '') === '1';
    set_agreement_sign_enforced($db_conn, $type, $enforced);
    $_SESSION['sucMessage'] = ($type === 'cp' ? 'Channel Partners' : 'Territory Partners')
        . ($enforced
            ? ' will now be forced straight to the Agreement page on login until they sign.'
            : ' can now use the portal normally again without being forced to the Agreement page.');
    header('Location: manage-agreements.php' . ($type === 'tp' ? '?tab=tp' : ''));
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
