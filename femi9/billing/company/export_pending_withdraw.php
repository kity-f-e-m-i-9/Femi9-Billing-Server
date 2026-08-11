<?php
ob_start();
error_reporting(0);

include("checksession.php");
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Pending Withdraw');

// ================= HEADER =================
$headers = [
    'S.NO','USER ID','NAME','STATE','DISTRICT','TALUK',
    'MOBILE NUMBER','CATEGORY','TARGET',
    'AMOUNT','TDS %','TDS DEDUCTION','FINAL AMOUNT',
    'BANK NAME','ACCOUNT NUMBER','ACCOUNT NAME','IFSC','PAN NUMBER',
    'DATE','TIME'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col.'1', $header);
    $col++;
}

// ================= HEADER STYLE =================
$sheet->getStyle('A1:T1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 12,
        'name' => 'Calibri'
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'FFE066'] // yellow
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN
        ]
    ]
]);

// Freeze header
$sheet->freezePane('A2');

$rowNum = 2;
$i = 0;

// Get TDS
$admin_q = mysqli_query($db_conn, "SELECT tds_percentage FROM admin_settings WHERE id='1'");
$admin = mysqli_fetch_array($admin_q);
$tds_percentage = $admin['tds_percentage'] ?? 0;

$query = "SELECT * FROM wallet_withdraw WHERE req_status='pending' ORDER BY id DESC";
$result = mysqli_query($db_conn, $query);

while ($row = mysqli_fetch_array($result)) {

    $type = $row['user_type'];
    $uid = $row['user_id'];
    $uid_esc = mysqli_real_escape_string($db_conn, $uid);

    $district = [];
    $taluk = [];
    $state = [];

    // territory_partner/channel_partner aren't in the onboard-user tables
    // this export otherwise reads from (they have no temp_id/district_id/
    // taluk_id/state_id/target columns) — resolved the same way
    // wallet_request.php's listing page already does, so exported rows
    // match what's shown on screen instead of being silently dropped.
    if ($type === 'territory_partner') {
        $user = mysqli_fetch_array(mysqli_query($db_conn,
            "SELECT id, name, mobile, tp_id AS useridtext FROM territory_partners WHERE id='$uid_esc' LIMIT 1"));
        if (!$user) continue;
        $user['country_code'] = '';
        $user['mobile_number'] = $user['mobile'] ?? '';
        $user['target'] = '';

        $r_dist = mysqli_fetch_assoc(mysqli_query($db_conn,
            "SELECT GROUP_CONCAT(DISTINCT district.name ORDER BY district.name SEPARATOR ', ') AS district_names
             FROM territory_partner_locations tpl
             JOIN partner_location_nodes leaf     ON leaf.id = tpl.location_id
             JOIN partner_location_nodes taluk_n  ON taluk_n.id = leaf.parent_id
             JOIN partner_location_nodes division ON division.id = taluk_n.parent_id
             JOIN partner_location_nodes district  ON district.id = division.parent_id
             WHERE tpl.territory_partner_id='$uid_esc'"));
        $district['dist_name'] = $r_dist['district_names'] ?? '';
    } elseif ($type === 'channel_partner') {
        $user = mysqli_fetch_array(mysqli_query($db_conn,
            "SELECT id, name, mobile, cp_id AS useridtext FROM channel_partners WHERE id='$uid_esc' LIMIT 1"));
        if (!$user) continue;
        $user['country_code'] = '';
        $user['mobile_number'] = $user['mobile'] ?? '';
        $user['target'] = '';
    } else {
        if($type=="candf"){$table="c_and_f";}
        elseif($type=="super_stockiest"){$table="super_stockiest";}
        elseif($type=="stockiest"){$table="stockiest";}
        elseif($type=="distributor"){$table="distributor";}
        else{$table="super_distributor";}

        $user = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM $table WHERE temp_id='$uid_esc'"));
        if (!$user) continue;

        $district = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM district WHERE id='".$user['district_id']."'")) ?: [];
        $taluk = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM taluk WHERE id='".$user['taluk_id']."'")) ?: [];
        $state = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM state WHERE id='".$user['state_id']."'")) ?: [];
    }

    $amount = $row['amount'];
    $tds = $amount * $tds_percentage / 100;
    $final = $amount - $tds;

    // ===== WRITE DATA =====
    $sheet->setCellValue('A'.$rowNum, ++$i);
    $sheet->setCellValue('B'.$rowNum, $user['useridtext']);
    $sheet->setCellValue('C'.$rowNum, ucwords($user['name']));
    $sheet->setCellValue('D'.$rowNum, $state['st_name'] ?? '');
    $sheet->setCellValue('E'.$rowNum, $district['dist_name'] ?? '');
    $sheet->setCellValue('F'.$rowNum, $taluk['taluk'] ?? '');

    // TEXT FIELDS (IMPORTANT)
    $sheet->setCellValueExplicit('G'.$rowNum, $user['country_code'].' '.$user['mobile_number'], DataType::TYPE_STRING);
    $sheet->setCellValue('H'.$rowNum, $type);
    $sheet->setCellValue('I'.$rowNum, $user['target'] ?? '');

    $sheet->setCellValue('J'.$rowNum, $amount);
    $sheet->setCellValue('K'.$rowNum, $tds_percentage);
    $sheet->setCellValue('L'.$rowNum, $tds);
    $sheet->setCellValue('M'.$rowNum, $final);

    $sheet->setCellValue('N'.$rowNum, $row['bankname']);
    $sheet->setCellValueExplicit('O'.$rowNum, $row['acnumber'], DataType::TYPE_STRING);
    $sheet->setCellValue('P'.$rowNum, $row['acname']);
    $sheet->setCellValueExplicit('Q'.$rowNum, $row['ifsc'], DataType::TYPE_STRING);
    $sheet->setCellValueExplicit('R'.$rowNum, $row['pannumber'], DataType::TYPE_STRING);

    $sheet->setCellValue('S'.$rowNum, date("d/m/Y", strtotime($row['date'])));
    $sheet->setCellValue('T'.$rowNum, date("h:i A", strtotime($row['time'])));

    $rowNum++;
}

// ================= BODY STYLE =================
$sheet->getStyle('A2:T'.$rowNum)->applyFromArray([
    'font' => [
        'name' => 'Calibri',
        'size' => 11
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN
        ]
    ]
]);

// Currency format
$sheet->getStyle('J2:M'.$rowNum)
->getNumberFormat()->setFormatCode('#,##0.00');

// Alignment
$sheet->getStyle('A:T')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

// Auto width
foreach(range('A','T') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ================= DOWNLOAD =================
$filename = "Pending_Withdraw_Report.xlsx";

ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;