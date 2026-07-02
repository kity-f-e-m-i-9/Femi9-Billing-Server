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

    if($type=="candf"){$table="c_and_f";}
    elseif($type=="super_stockiest"){$table="super_stockiest";}
    elseif($type=="stockiest"){$table="stockiest";}
    elseif($type=="distributor"){$table="distributor";}
    else{$table="super_distributor";}

    $user = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM $table WHERE temp_id='$uid'"));
    if (!$user) continue;

    $district = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM district WHERE id='".$user['district_id']."'"));
    $taluk = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM taluk WHERE id='".$user['taluk_id']."'"));
    $state = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM state WHERE id='".$user['state_id']."'"));

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