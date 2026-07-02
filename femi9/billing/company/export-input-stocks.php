<?php
include("checksession.php");
require_once("include/GodownAccess.php");
error_reporting(0);

$from_date = !empty($_GET['frdate']) ? $_GET['frdate'] : date("Y-m-d", strtotime("-2 days"));
$to_date   = !empty($_GET['todate']) ? $_GET['todate'] : date("Y-m-d");

$sql_exp = "SELECT i.*, p.productName, g.gname
            FROM input_stock i
            LEFT JOIN products p ON p.id = i.product_id
            LEFT JOIN company_godown g ON g.id = i.godownid
            WHERE i.input_date BETWEEN '$from_date' AND '$to_date'
              AND (g.id IS NULL OR " . godown_finance_filter_sql($db_conn, 'g') . ")
            ORDER BY i.input_date DESC";
$res_exp = mysqli_query($db_conn, $sql_exp);

// ── Build sheet rows XML ──────────────────────────────────────────────
function xlsxCell($value, $type = 'inlineStr') {
    $value = htmlspecialchars($value, ENT_XML1, 'UTF-8');
    if($type === 'n') {
        return '<c t="n"><v>' . $value . '</v></c>';
    }
    return '<c t="inlineStr"><is><t>' . $value . '</t></is></c>';
}

$rowsXml = '';

// Header row
$headers = ['S.No', 'Godown Name', 'Date', 'Product Name', 'Input Qty', 'Remarks'];
$rowsXml .= '<row>';
foreach($headers as $h){
    $rowsXml .= xlsxCell($h);
}
$rowsXml .= '</row>';

// Data rows
$sno = 1;
while($row = mysqli_fetch_array($res_exp)){
    $rowsXml .= '<row>';
    $rowsXml .= xlsxCell($sno++, 'n');
    $rowsXml .= xlsxCell($row['gname']);
    $rowsXml .= xlsxCell(date("d/M/Y", strtotime($row['input_date'])));
    $rowsXml .= xlsxCell($row['productName']);
    $rowsXml .= xlsxCell($row['input_qty'], 'n');
    $rowsXml .= xlsxCell($row['input_remarks']);
    $rowsXml .= '</row>';
}

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
  <Override PartName="/xl/sharedStrings.xml"        ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml"               ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

$files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"    Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"        Target="styles.xml"/>
</Relationships>';

$files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Input Stocks" sheetId="1" r:id="rId1"/>
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
  <sheetData>' . $rowsXml . '</sheetData>
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
        "\x50\x4b\x03\x04"       // local file header signature
        . pack('v', 20)           // version needed
        . pack('v', 0)            // general purpose bit flag
        . pack('v', 8)            // compression method: deflate
        . pack('v', 0)            // last mod time
        . pack('v', 0)            // last mod date
        . pack('V', $dataCrc)     // crc-32
        . pack('V', $sizeComp)    // compressed size
        . pack('V', $sizeRaw)     // uncompressed size
        . pack('v', $nameLen)     // file name length
        . pack('v', 0)            // extra field length
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

foreach($files as $name => $data) {
    $entry          = addZipEntry($name, $data);
    $entry['offset'] = $offset;

    $zip    .= $entry['header'];
    $offset += strlen($entry['header']);

    $nameLen = strlen($name);

    $central .=
        "\x50\x4b\x01\x02"           // central dir signature
        . pack('v', 20)               // version made by
        . pack('v', 20)               // version needed
        . pack('v', 0)                // general purpose bit flag
        . pack('v', 8)                // compression method
        . pack('v', 0)                // last mod time
        . pack('v', 0)                // last mod date
        . pack('V', $entry['crc'])
        . pack('V', $entry['sizeComp'])
        . pack('V', $entry['sizeRaw'])
        . pack('v', $nameLen)
        . pack('v', 0)                // extra field length
        . pack('v', 0)                // file comment length
        . pack('v', 0)                // disk number start
        . pack('v', 0)                // internal file attributes
        . pack('V', 0)                // external file attributes
        . pack('V', $entry['offset'])
        . $name;

    $entryCount++;
}

$centralLen    = strlen($central);
$endOfCentral  =
    "\x50\x4b\x05\x06"           // end of central dir signature
    . pack('v', 0)                // disk number
    . pack('v', 0)                // disk with central dir
    . pack('v', $entryCount)      // entries on this disk
    . pack('v', $entryCount)      // total entries
    . pack('V', $centralLen)      // size of central dir
    . pack('V', $offset)          // offset of central dir
    . pack('v', 0);               // comment length

$zipData = $zip . $central . $endOfCentral;

// ── Output ────────────────────────────────────────────────────────────
$filename = 'input_stocks_' . $from_date . '_to_' . $to_date . '.xlsx';

ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($zipData));
header('Cache-Control: max-age=0');
header('Pragma: public');

echo $zipData;
exit();
?>