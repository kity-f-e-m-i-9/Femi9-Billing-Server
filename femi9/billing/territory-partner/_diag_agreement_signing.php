<?php
// TEMPORARY diagnostic — "Accept & Sign Agreement" is throwing a 500 for
// real users. The usual causes for an uncaught mysqli_sql_exception here
// (mysqli is in exception-report mode) are: a schema column this code
// expects that's missing on this install (e.g. a self-healing ALTER that
// silently failed because the DB user lost ALTER privilege after a
// reimport — see _diag_login_speed.php), or something else blowing up.
// This checks schema + ALTER privilege, then does a REAL UPDATE wrapped in
// a transaction that's always rolled back, so nothing is actually saved,
// to surface the exact error if one happens.
// Delete this file after use.
include("checksession.php");
require_once __DIR__ . '/../shared/AgreementService.php';
include("config.php");
error_reporting(0);

header('Content-Type: text/plain; charset=utf-8');

echo "=== Agreement signing diagnostic ===\n";
echo "Run at: " . date('Y-m-d H:i:s') . "\n\n";

echo "--- Can this DB user ALTER TABLE? ---\n";
$testRes = @$db_conn->query("ALTER TABLE territory_partner_agreements ADD COLUMN _diag_tmp_col INT NULL");
if ($testRes) {
    echo "YES\n";
    @$db_conn->query("ALTER TABLE territory_partner_agreements DROP COLUMN _diag_tmp_col");
} else {
    echo "NO — " . $db_conn->error . "\n";
}

echo "\n--- Columns present on territory_partner_agreements ---\n";
$expectedCols = ['territory_partner_id','agreement_date','agreement_place','tp_designation','tp_signature',
    'pan_card_path','witness1_name','witness1_address','witness1_signature','witness2_name','witness2_address',
    'witness2_signature','snap_name','snap_company_name','snap_address','snap_mobile','snap_email',
    'snap_body_html','custom_body_html','signed_at','is_locked'];
$res = $db_conn->query("SHOW COLUMNS FROM territory_partner_agreements");
$present = [];
while ($row = $res->fetch_assoc()) { $present[$row['Field']] = $row['Type']; }
foreach ($expectedCols as $c) {
    echo str_pad($c, 25) . " : " . (isset($present[$c]) ? $present[$c] : "**MISSING**") . "\n";
}

echo "\n--- Columns present on channel_partner_agreements ---\n";
$res2 = $db_conn->query("SHOW COLUMNS FROM channel_partner_agreements");
$present2 = [];
while ($row = $res2->fetch_assoc()) { $present2[$row['Field']] = $row['Type']; }
foreach ($expectedCols as $c) {
    $c2 = str_replace(['territory_partner_id', 'tp_designation', 'tp_signature'], ['channel_partner_id', 'cp_designation', 'cp_signature'], $c);
    echo str_pad($c2, 25) . " : " . (isset($present2[$c2]) ? $present2[$c2] : "**MISSING**") . "\n";
}

echo "\n--- Does agreement_settings table/row exist (checksession.php's enforcement gate reads it on EVERY page)? ---\n";
$res3 = @$db_conn->query("SELECT * FROM agreement_settings WHERE id=1");
if ($res3 && $row3 = $res3->fetch_assoc()) {
    echo json_encode($row3) . "\n";
} else {
    echo "NO ROW / QUERY FAILED: " . $db_conn->error . "\n";
}

echo "\n--- Simulated full sign flow on a real TP row, wrapped in a transaction that is always rolled back ---\n";
$testTp = $db_conn->query("SELECT tp.id, tp.name FROM territory_partners tp
    LEFT JOIN territory_partner_agreements tpa ON tpa.territory_partner_id = tp.id
    WHERE tp.deleted_at IS NULL AND (tpa.is_locked IS NULL OR tpa.is_locked = 0) LIMIT 1")->fetch_assoc();
if (!$testTp) {
    echo "No unsigned TP found to test with.\n";
} else {
    echo "Testing with TP id={$testTp['id']} ({$testTp['name']}) — will be ROLLED BACK, not saved.\n";
    $db_conn->begin_transaction();
    try {
        $tp_id = (int) $testTp['id'];
        $existingAgreement = get_or_create_tp_agreement($db_conn, $tp_id);
        $panCardPath = $existingAgreement['pan_card_path'] ?: 'diag_test.jpg';
        $snapBodyHtml = get_effective_agreement_body($db_conn, $existingAgreement, 'territory_partner');
        echo "snap_body_html length: " . strlen($snapBodyHtml) . "\n";

        $fakeSig = 'data:image/png;base64,' . base64_encode(random_bytes(15000));
        $agreementDate = date('Y-m-d');
        $agreementPlace = 'Diagnostic Test';
        $tpDesignation = 'Test';
        $w1Name = $w1Address = $w1Signature = $w2Name = $w2Address = $w2Signature = '';

        $stmtTp = $db_conn->prepare("SELECT name, company_name, address, mobile, email,
            branch_line1, branch_line2, branch_city, branch_district, branch_state, branch_country, branch_pincode
            FROM territory_partners WHERE id = ?");
        $stmtTp->bind_param('i', $tp_id);
        $stmtTp->execute();
        $tpProfile = $stmtTp->get_result()->fetch_assoc() ?: [];
        $stmtTp->close();
        $snapName = $tpProfile['name'] ?? '';
        $snapCompanyName = $tpProfile['company_name'] ?? '';
        $snapAddress = partner_billing_address_line($tpProfile);
        $snapMobile = $tpProfile['mobile'] ?? '';
        $snapEmail = $tpProfile['email'] ?? '';

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
            $tpDesignation, $fakeSig, $panCardPath,
            $w1Name, $w1Address, $w1Signature,
            $w2Name, $w2Address, $w2Signature,
            $snapName, $snapCompanyName, $snapAddress, $snapMobile, $snapEmail,
            $snapBodyHtml,
            $tp_id
        );
        $ok = $stmt->execute();
        echo "UPDATE result: " . var_export($ok, true) . "\n";
        if (!$ok) echo "UPDATE error: " . $stmt->error . "\n";
        $stmt->close();
        echo "No exception thrown. Rolling back now.\n";
    } catch (\Throwable $e) {
        echo "EXCEPTION CAUGHT: " . get_class($e) . ": " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    $db_conn->rollback();
}

echo "\nDone. Paste this whole output back, then delete this file.\n";
