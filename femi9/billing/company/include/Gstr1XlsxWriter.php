<?php
/**
 * Builds the GSTR-1 Excel workbook (GST Offline Tool format) from build_gstr1_json() output.
 * Shared by company/export_GSTR1_xlsx.php and territory-partner/export_gstr1_xlsx.php.
 * $summary = [ ['Intra-state', $sales_lines, $credit_lines], ... ] where each line is [label, registered, unregistered].
 */
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

if (!function_exists('gstr1_build_workbook')) {
function gstr1_build_workbook(array $data, string $from_date, string $to_date, array $summary) {
$states = ['01'=>'Jammu and Kashmir','02'=>'Himachal Pradesh','03'=>'Punjab','04'=>'Chandigarh','05'=>'Uttarakhand','06'=>'Haryana','07'=>'Delhi','08'=>'Rajasthan','09'=>'Uttar Pradesh','10'=>'Bihar','11'=>'Sikkim','12'=>'Arunachal Pradesh','13'=>'Nagaland','14'=>'Manipur','15'=>'Mizoram','16'=>'Tripura','17'=>'Meghalaya','18'=>'Assam','19'=>'West Bengal','20'=>'Jharkhand','21'=>'Odisha','22'=>'Chhattisgarh','23'=>'Madhya Pradesh','24'=>'Gujarat','26'=>'Dadra and Nagar Haveli and Daman and Diu','27'=>'Maharashtra','29'=>'Karnataka','30'=>'Goa','31'=>'Lakshadweep','32'=>'Kerala','33'=>'Tamil Nadu','34'=>'Puducherry','35'=>'Andaman and Nicobar Islands','36'=>'Telangana','37'=>'Andhra Pradesh','38'=>'Ladakh'];
$pos = function ($c) use ($states) { $c = str_pad((string)$c, 2, '0', STR_PAD_LEFT); return $c . '-' . ($states[$c] ?? ''); };
$xd  = function ($dmy) { return XlDate::PHP2Excel(DateTime::createFromFormat('d-m-Y', $dmy)->setTime(0, 0)); };
$n   = function ($v) { return round((float)$v, 2); };

$wb = IOFactory::load(__DIR__ . '/../templates/gstr1_offline_template.xlsx');

// Writes rows from row 5 down; $dateCols = 1-based columns holding d-m-Y strings.
$fill = function (string $sheet, array $rows, array $dateCols = []) use ($wb, $xd) {
    $ws = $wb->getSheetByName($sheet);
    $r = 5;
    foreach ($rows as $row) {
        foreach ($row as $i => $v) {
            if ($v === null) continue;
            $col = $i + 1;
            $isDate = in_array($col, $dateCols, true);
            $addr = Coordinate::stringFromColumnIndex($col) . $r;
            $ws->setCellValueExplicit($addr, $isDate ? $xd($v) : $v,
                ($isDate || is_float($v) || is_int($v)) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            if ($isDate) $ws->getStyle($addr)->getNumberFormat()->setFormatCode('dd-mmm-yy');
        }
        $r++;
    }
};

// b2b: one row per invoice x rate
$rows = [];
foreach ($data['b2b'] as $c) foreach ($c['inv'] as $inv) foreach ($inv['itms'] as $it) {
    $d = $it['itm_det'];
    $rows[] = [$c['ctin'], null, $inv['inum'], $inv['idt'], $n($inv['val']), $pos($inv['pos']), 'N', null, 'Regular B2B', null, $d['rt'], $n($d['txval']), 0];
}
$fill('b2b,sez,de', $rows, [4]);

// b2cs
$rows = [];
foreach ($data['b2cs'] as $b) $rows[] = ['OE', $pos($b['pos']), null, $b['rt'], $n($b['txval']), 0, null];
$fill('b2cs', $rows);

// exp
$rows = [];
foreach (($data['exp'] ?? []) as $g) foreach ($g['inv'] as $inv) foreach ($inv['itms'] as $it)
    $rows[] = [$g['exp_typ'], $inv['inum'], $inv['idt'], $n($inv['val']), '', null, null, $it['rt'], $n($it['txval']), 0];
$fill('exp', $rows, [3]);

// exemp (fixed 4 rows, in the tool's order; amounts go in the "Nil Rated Supplies" column, like the JSON nil_amt)
$nil = [];
foreach ($data['nil']['inv'] as $x) $nil[$x['sply_ty']] = $n($x['nil_amt']);
$fill('exemp', [
    ['Inter-State supplies to registered persons',   $nil['INTRB2B']   ?? 0, 0, 0],
    ['Intra-State supplies to registered persons',   $nil['INTRAB2B']  ?? 0, 0, 0],
    ['Inter-State supplies to unregistered persons', $nil['INTRB2C']   ?? 0, 0, 0],
    ['Intra-State supplies to unregistered persons', $nil['INTRAB2C']  ?? 0, 0, 0],
]);

// hsn
foreach (['hsn_b2b' => 'hsn(b2b)', 'hsn_b2c' => 'hsn(b2c)'] as $k => $sheet) {
    $rows = [];
    foreach ($data['hsn'][$k] as $h) {
        $rows[] = [$h['hsn_sc'], $h['user_desc'], $h['uqc'] === 'PAC' ? 'PAC-PACKS' : $h['uqc'], $h['qty'],
                   $n($h['txval'] + $h['iamt'] + $h['camt'] + $h['samt']), $h['rt'], $n($h['txval']), $n($h['iamt']), $n($h['camt']), $n($h['samt']), 0];
    }
    $fill($sheet, $rows);
}

// docs
$rows = [];
foreach (($data['doc_issue']['doc_det'][0]['docs'] ?? []) as $d) $rows[] = ['Invoices for outward supply', $d['from'], $d['to'], $d['totnum'], $d['cancel']];
$fill('docs', $rows);

// cdnr: one row per credit note x rate (registered buyers, valid GSTIN)
$rows = [];
foreach (($data['cdnr'] ?? []) as $c) foreach ($c['nt'] as $nt) foreach ($nt['itms'] as $it) {
    $d = $it['itm_det'];
    $rows[] = [$c['ctin'], null, $nt['nt_num'], $nt['nt_dt'], 'Credit', $pos($nt['pos']), 'N', 'Regular B2B', $n($nt['val']), null, $d['rt'], $n($d['txval']), 0];
}
$fill('cdnr', $rows, [4]);


// Summary sheet: the Intra-state / Inter-state tables from GSTR1.php (not part of the offline-tool import)
$sum = $wb->createSheet(); $sum->setTitle('Summary');
$sum->setCellValue('A1', 'GSTR-1 Summary - ' . $data['gstin'] . ' - ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)));
$sum->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$row = 3;
$block = function (string $title, array $lines, array $credit) use ($sum, &$row) {
    $sum->setCellValue("A$row", $title); $sum->getStyle("A$row")->getFont()->setBold(true)->setSize(12); $row++;
    foreach ([['Sales', $lines], ['Credit Note', $credit]] as [$h, $set]) {
        $sum->setCellValue("A$row", $h); $sum->setCellValue("B$row", 'Registered person'); $sum->setCellValue("C$row", 'Unregistered person');
        $sum->getStyle("A$row:C$row")->getFont()->setBold(true); $row++;
        $t1 = 0; $t2 = 0;
        foreach ($set as [$label, $reg, $unreg]) {
            $sum->setCellValue("A$row", $label); $sum->setCellValue("B$row", round((float)$reg, 2)); $sum->setCellValue("C$row", round((float)$unreg, 2));
            $t1 += $reg; $t2 += $unreg; $row++;
        }
        $sum->setCellValue("A$row", 'Total'); $sum->setCellValue("B$row", round($t1, 2)); $sum->setCellValue("C$row", round($t2, 2));
        $sum->getStyle("A$row:C$row")->getFont()->setBold(true); $row += 2;
    }
};
foreach ($summary as [$title, $lines, $credit]) $block($title, $lines, $credit);
$sum->getColumnDimension('A')->setWidth(46); $sum->getColumnDimension('B')->setWidth(22); $sum->getColumnDimension('C')->setWidth(22);
$sum->getStyle('B3:C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

$wb->setActiveSheetIndexByName('b2b,sez,de');
return $wb;
}
}
