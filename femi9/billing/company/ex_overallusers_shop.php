<?php
ob_start();

require_once __DIR__ . '/../shared/env-loader.php';
include("checksession.php");
require_once __DIR__ . '/../shared/EncryptionService.php';
$encryption = new EncryptionService();
error_reporting(0);

ob_end_clean(); // Clear any output from includes before sending headers

// ── CSV download headers ──────────────────────────────────────────────────────
$filename = 'Shop_Users_Export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');
// ─────────────────────────────────────────────────────────────────────────────

// Open output stream
$output = fopen('php://output', 'w');

// UTF-8 BOM — makes Excel open the file with correct encoding (no garbled Tamil/special chars)
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// ── Title rows ────────────────────────────────────────────────────────────────
fputcsv($output, ['Overall Shop Users Export']);
fputcsv($output, ['Generated on: ' . date('d/m/Y H:i:s')]);
fputcsv($output, []); // blank spacer row

// ── Column headers ────────────────────────────────────────────────────────────
fputcsv($output, [
    '#',
    'User ID',
    'Name',
    'Country Code',
    'Mobile Number',
    'District',
    'Taluk',
    'Referred ID',
    'Referred Name',
    'Referred Mobile',
    'Referred Type',
    'Referral Source',
]);

// ── Data rows ─────────────────────────────────────────────────────────────────
$tablename          = 'shop';
$fetch_product_list = mysqli_query($db_conn, "SELECT * FROM $tablename ORDER BY id DESC");

$i = 0;
while ($r = mysqli_fetch_array($fetch_product_list)) {

    // District name
    $district_id = $r['district_id'];
    if (is_numeric($district_id)) {
        $d             = mysqli_fetch_array(mysqli_query($db_conn, "SELECT dist_name FROM district WHERE id='$district_id'"));
        $district_name = $d['dist_name'] ?? $district_id;
    } else {
        $district_name = $district_id;
    }

    // Taluk name
    $taluk_id = $r['taluk_id'];
    if (is_numeric($taluk_id)) {
        $t          = mysqli_fetch_array(mysqli_query($db_conn, "SELECT taluk FROM taluk WHERE id='$taluk_id'"));
        $taluk_name = $t['taluk'] ?? $taluk_id;
    } else {
        $taluk_name = $taluk_id;
    }

    // Referral details
    $ref             = mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT * FROM stockist_referral WHERE stockist_id='" . $r['temp_id'] . "'"
    ));
    $referral_source = $ref['st_ref_type'] ?? '';

    if ($referral_source === 'company') {
        $referred_id     = '---';
        $referred_name   = 'Company';
        $referred_mobile = '---';
        $referred_type   = '---';
    } else {
        switch ($r['onboard_userTYPE']) {
            case 'super_stockiest':   $tblename = 'super_stockiest'; $labelname = 'Super Stockist'; break;
            case 'stockiest':         $tblename = 'stockiest';       $labelname = 'Stockist';       break;
            case 'distributor':
            case 'super_distributor': $tblename = 'distributor';     $labelname = 'Distributor';    break;
            default:                  $tblename = 'stockiest';       $labelname = '';
        }
        $ref2            = mysqli_fetch_array(mysqli_query($db_conn,
            "SELECT * FROM $tblename WHERE temp_id='" . $r['onboard_userID'] . "'"
        ));
        $referred_id     = $ref2['useridtext']    ?? '---';
        $referred_name   = $ref2['name']          ?? '---';
        $referred_mobile = $ref2['mobile_number'] ?? '---';
        $referred_type   = $labelname;
    }

    // Prefix mobile numbers with a tab character to force Excel to treat them
    // as text — prevents 10-digit numbers from showing as scientific notation
    $mobile_number   = "\t" . ($r['mobile_number'] ?? '');
    $referred_mobile = $referred_mobile !== '---' ? "\t" . $referred_mobile : $referred_mobile;

    fputcsv($output, [
        ++$i,
        $r['useridtext']  ?? '',
        ucwords($r['name'] ?? ''),
        $r['country_code'] ?? '',
        $mobile_number,
        $district_name,
        $taluk_name,
        $referred_id,
        ucwords($referred_name),
        $referred_mobile,
        $referred_type,
        ucwords($referral_source),
    ]);
}

// ── Total row ─────────────────────────────────────────────────────────────────
fputcsv($output, []); // blank spacer
fputcsv($output, ['Total Records', $i]);

fclose($output);
exit;