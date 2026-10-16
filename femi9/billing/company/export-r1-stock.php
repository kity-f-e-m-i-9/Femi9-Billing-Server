<?php
include("checksession.php");
require_once("include/GodownAccess.php");
error_reporting(0);

$from_date = !empty($_GET['frdate']) ? $_GET['frdate'] : '2026-09-12';
$to_date   = !empty($_GET['todate']) ? $_GET['todate'] : '2026-10-12';

// R1 warehouse
$sql_wh = "SELECT id FROM warehouses WHERE code = 'R1' LIMIT 1";
$res_wh = mysqli_query($db_conn, $sql_wh);
$wh_row = mysqli_fetch_array($res_wh);
$r1_warehouse_id = $wh_row ? (int)$wh_row['id'] : 0;

// ── Input rows (stock received into R1) ───────────────────────────────
$sql_input = "SELECT i.*, p.productName, g.gname
              FROM input_stock i
              LEFT JOIN products p ON p.id = i.product_id
              LEFT JOIN company_godown g ON g.id = i.godownid
              WHERE i.warehouse_id = $r1_warehouse_id
                AND i.input_date BETWEEN '$from_date' AND '$to_date'
                AND (g.id IS NULL OR " . godown_finance_filter_sql($db_conn, 'g') . ")
              ORDER BY i.input_date, i.id";
$res_input = mysqli_query($db_conn, $sql_input);

// ── Transfer rows (moves into/out of R1) ───────────────────────────────
$sql_moves = "SELECT m.*, p.productName,
                     wf.code AS from_code, wt.code AS to_code
              FROM company_godown_stock_moves m
              LEFT JOIN products p ON p.id = m.product_id
              LEFT JOIN warehouses wf ON wf.id = m.from_warehouse_id
              LEFT JOIN warehouses wt ON wt.id = m.to_warehouse_id
              LEFT JOIN company_godown g ON g.id = m.from_company_godown_id
              WHERE (m.from_warehouse_id = $r1_warehouse_id OR m.to_warehouse_id = $r1_warehouse_id)
                AND DATE(m.created_at) BETWEEN '$from_date' AND '$to_date'
                AND (g.id IS NULL OR " . godown_finance_filter_sql($db_conn, 'g') . ")
              ORDER BY m.created_at, m.id";
$res_moves = mysqli_query($db_conn, $sql_moves);

// ── xlsx helpers ────────────────────────────────────────────────────────
function xlsxCell($value, $type = 'inlineStr') {
    $value = htmlspecialchars($value, ENT_XML1, 'UTF-8');
    if ($type === 'n') {
        return '<c t="n"><v>' . $value . '</v></c>';
    }
    return '<c t="inlineStr"><is><t>' . $value . '</t></is></c>';
}

function buildSheetRows($headers, $dataRows) {
    $xml = '<row>';
    foreach ($headers as $h) $xml .= xlsxCell($h);
    $xml .= '</row>';
    foreach ($dataRows as $cells) {
        $xml .= '<row>';
        foreach ($cells as $c) $xml .= xlsxCell($c[0], $c[1]);
        $xml .= '</row>';
    }
    return $xml;
}

// Sheet 1: Input
$inputHeaders = ['S.No', 'Date', 'Product Name', 'Input Qty', 'Remarks'];
$inputRows = [];
$sno = 1;
while ($row = mysqli_fetch_array($res_input)) {
    $inputRows[] = [
        [$sno++, 'n'],
        [date("d/M/Y", strtotime($row['input_date'])), 'inlineStr'],
        [$row['productName'], 'inlineStr'],
        [$row['input_qty'], 'n'],
        [$row['input_remarks'], 'inlineStr'],
    ];
}
$sheet1Xml = buildSheetRows($inputHeaders, $inputRows);

// Sheet 2: Transfers
$transferHeaders = ['S.No', 'Date', 'Product Name', 'From Warehouse', 'To Warehouse', 'Qty', 'Direction', 'Note'];
$transferRows = [];
$sno = 1;
while ($row = mysqli_fetch_array($res_moves)) {
    $direction = ((int)$row['to_warehouse_id'] === $r1_warehouse_id) ? 'IN to R1' : 'OUT from R1';
    $transferRows[] = [
        [$sno++, 'n'],
        [date("d/M/Y H:i", strtotime($row['created_at'])), 'inlineStr'],
        [$row['productName'], 'inlineStr'],
        [$row['from_code'], 'inlineStr'],
        [$row['to_code'], 'inlineStr'],
        [$row['qty'], 'n'],
        [$direction, 'inlineStr'],
        [$row['note'], 'inlineStr'],
    ];
}
$sheet2Xml = buildSheetRows($transferHeaders, $transferRows);

// ── Define all xlsx internal files ───────────────────────────────────
$files = [];

$files['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

$files['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"             ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml"    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml"        ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml"               ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

$files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"    Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"    Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"        Target="styles.xml"/>
</Relationships>';

$files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="R1 Input" sheetId="1" r:id="rId1"/>
    <sheet name="R1 Transfers" sheetId="2" r:id="rId2"/>
  </sheets>
</workbook>';

$files['xl/sharedStrings.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="0" uniqueCount="0"/>';

$files['xl/styles.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills>
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>';

$files['xl/worksheets/sheet1.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>' . $sheet1Xml . '</sheetData>
</worksheet>';

$files['xl/worksheets/sheet2.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>' . $sheet2Xml . '</sheetData>
</worksheet>';

// ── Build ZIP in memory ───────────────────────────────────────────────
function addZipEntry($name, $data) {
    $nameLen    = strlen($name);
    $dataRaw    = $data;
    $dataComp   = gzdeflate($data, 6);
    $dataCrc    = crc32($dataRaw);
    $sizeRaw    = strlen($dataRaw);
    $sizeComp   = strlen($dataComp);

    $localHeader =
        "\x50\x4b\x03\x04"
        . pack('v', 20)
        . pack('v', 0)
        . pack('v', 8)
        . pack('v', 0)
        . pack('v', 0)
        . pack('V', $dataCrc)
        . pack('V', $sizeComp)
        . pack('V', $sizeRaw)
        . pack('v', $nameLen)
        . pack('v', 0)
        . $name
        . $dataComp;

    return [
        'header'   => $localHeader,
        'name'     => $name,
        'crc'      => $dataCrc,
        'sizeComp' => $sizeComp,
        'sizeRaw'  => $sizeRaw,
        'offset'   => 0,
    ];
}

$zip        = '';
$central    = '';
$offset     = 0;
$entryCount = 0;

foreach ($files as $name => $data) {
    $entry           = addZipEntry($name, $data);
    $entry['offset'] = $offset;

    $zip    .= $entry['header'];
    $offset += strlen($entry['header']);

    $nameLen = strlen($name);

    $central .=
        "\x50\x4b\x01\x02"
        . pack('v', 20)
        . pack('v', 20)
        . pack('v', 0)
        . pack('v', 8)
        . pack('v', 0)
        . pack('v', 0)
        . pack('V', $entry['crc'])
        . pack('V', $entry['sizeComp'])
        . pack('V', $entry['sizeRaw'])
        . pack('v', $nameLen)
        . pack('v', 0)
        . pack('v', 0)
        . pack('v', 0)
        . pack('v', 0)
        . pack('V', 0)
        . pack('V', $entry['offset'])
        . $name;

    $entryCount++;
}

$centralLen   = strlen($central);
$endOfCentral =
    "\x50\x4b\x05\x06"
    . pack('v', 0)
    . pack('v', 0)
    . pack('v', $entryCount)
    . pack('v', $entryCount)
    . pack('V', $centralLen)
    . pack('V', $offset)
    . pack('v', 0);

$zipData = $zip . $central . $endOfCentral;

// ── Output ────────────────────────────────────────────────────────────
$filename = 'R1_stock_' . $from_date . '_to_' . $to_date . '.xlsx';

ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($zipData));
header('Cache-Control: max-age=0');
header('Pragma: public');

echo $zipData;
exit();
