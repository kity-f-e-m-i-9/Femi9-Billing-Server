<?php
// DM Name | assigned District/Division(s) | Monthly Target | Weekly Target |
// Weekly Achieved — one row per DM. Target reuses getRawTargetAchievedStats()
// from include/TeamTargetRollup.php exactly as marketing/my-team.php and
// company/ms-team-view.php already do (Firka target_amount total under the
// DM's own assigned location(s), prorated to the date range) — Monthly =
// this calendar month, Weekly = this week (Monday to Sunday). Weekly
// Achieved = BOTH (a) that function's own 'achieved' (DM's Get Orders this
// week that a TP has converted to invoice) AND (b) whatever that DM's own
// TPs invoiced to shops independently this week (their own orders, not
// sourced from this DM's Get Order) — explicitly deduped so an invoice
// counted under (a) is never added again under (b). Both halves are
// Napkin-only (Diaper excluded), matching the existing Achieved convention.
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

require_once __DIR__ . '/../shared/db-helpers.php';
require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once("config.php");
require_once __DIR__ . '/include/TeamTargetRollup.php';

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;

function validateDate_dmta(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}

$monthlyFrom = date('Y-m-01');
$monthlyTo   = date('Y-m-t');
// "Weekly" Target/Achieved actually run over whatever period is passed in
// frdate/todate (?frdate=2026-11-01&todate=2026-11-08) — defaults to the
// current week (Monday-Sunday) when neither is given.
$weeklyFrom  = validateDate_dmta($_GET['frdate'] ?? null, date('Y-m-d', strtotime('monday this week')));
$weeklyTo    = validateDate_dmta($_GET['todate'] ?? null, date('Y-m-d', strtotime('sunday this week')));
if (strtotime($weeklyFrom) > strtotime($weeklyTo)) { [$weeklyFrom, $weeklyTo] = [$weeklyTo, $weeklyFrom]; }

// ── DM roster (level_rank = 3, same convention as asm-dm-order-conversion-
//    export.php) ─────────────────────────────────────────────────────────
$dmList = []; // ms_id => ms_name
$res = $db_conn->query("
    SELECT ms.id, ms.ms_name
    FROM marketing_staff ms
    JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
    WHERE tl.level_rank = 3 AND ms.deleted_at IS NULL
    ORDER BY ms.ms_name ASC
");
while ($row = $res->fetch_assoc()) { $dmList[(int)$row['id']] = $row['ms_name']; }
$dmIds = array_keys($dmList);

// ── Assigned District/Division name(s) + node ids per DM ────────────────────
$scopeNamesByDm = [];
$scopeNodeIdsByDm = [];
foreach ($dmIds as $id) {
    $nodes = getMsAssignedTargetScopeNodes($db_conn, $id);
    $scopeNamesByDm[$id] = implode('-', array_column($nodes, 'name'));
    $scopeNodeIdsByDm[$id] = array_column($nodes, 'id');
}

// ── Monthly Target, Weekly Target ────────────────────────────────────────────
$monthlyStats = getRawTargetAchievedStats($db_conn, $dmIds, $monthlyFrom, $monthlyTo);
$weeklyStats  = getRawTargetAchievedStats($db_conn, $dmIds, $weeklyFrom, $weeklyTo);

// ── Weekly Achieved = DM's Get Orders converted to invoice by a TP (the
//    'achieved' figure above) PLUS whatever that DM's own TPs invoiced to
//    shops independently (their own orders, not sourced from this DM's Get
//    Order) — both Napkin-only, both for the same weekly window. The TP-own
//    sum explicitly excludes every invoice already counted on the DM side
//    (inv_id already matched via ms_orders -> tp_orders for ANY DM this
//    week), so one invoice is never counted twice. ──────────────────────────
$weeklyFromEsc = $db_conn->real_escape_string($weeklyFrom);
$weeklyToEsc   = $db_conn->real_escape_string($weeklyTo);

$dmSourcedInvIds = [];
$resBridged = $db_conn->query("
    SELECT DISTINCT t.invoiced_inv_id
    FROM ms_orders o JOIN tp_orders t ON t.order_id = o.order_id
    WHERE o.new_order = 'yes' AND o.order_date BETWEEN '$weeklyFromEsc' AND '$weeklyToEsc'
      AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''
");
while ($row = $resBridged->fetch_assoc()) { $dmSourcedInvIds[] = $row['invoiced_inv_id']; }
$excludeInvSql = !empty($dmSourcedInvIds)
    ? "AND ui.inv_id COLLATE utf8mb4_general_ci NOT IN (" . implode(',', array_map(fn($v) => "'" . $db_conn->real_escape_string($v) . "'", $dmSourcedInvIds)) . ")"
    : '';

$tpOwnAmtByDm = array_fill_keys($dmIds, 0.0);
foreach ($dmIds as $id) {
    $tpIds = !empty($scopeNodeIdsByDm[$id]) ? getMsNodeTpIds($db_conn, $scopeNodeIdsByDm[$id], true) : [];
    if (empty($tpIds)) continue;
    $tpIdListSql = implode(',', array_map('intval', $tpIds));
    $r = $db_conn->query("
        SELECT COALESCE(SUM(uii.total), 0) AS amt
        FROM user_invoice ui
        JOIN user_invoice_items uii ON uii.inv_id COLLATE utf8mb4_general_ci = ui.inv_id COLLATE utf8mb4_general_ci
        JOIN products p ON p.id = uii.pr_id
        WHERE ui.from_user_type = 'territory_partner' AND ui.to_user_type = 'shop'
          AND ui.from_user_id IN ($tpIdListSql)
          AND ui.`date` BETWEEN '$weeklyFromEsc' AND '$weeklyToEsc'
          AND ui.deleted_at IS NULL AND ui.voided_at IS NULL
          AND COALESCE(p.category,'') != 'diaper'
          $excludeInvSql
    ");
    $row = $r->fetch_assoc();
    $tpOwnAmtByDm[$id] = (float)($row['amt'] ?? 0.0);
}

// ── ASM rollup — same staff-tree walk asm-dm-order-conversion-export.php
//    uses (manager_id chain up to the nearest level_rank=2 node), summing
//    each DM's Monthly Target / Weekly Target / Weekly Achieved under their
//    ASM, and joining all of that ASM's DMs' districts/divisions together. ──
$staffById = [];
$resStaff = $db_conn->query("
    SELECT ms.id, ms.ms_name, ms.manager_id, tl.level_rank
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
    WHERE ms.deleted_at IS NULL
");
while ($row = $resStaff->fetch_assoc()) {
    $staffById[(int)$row['id']] = [
        'name'       => trim($row['ms_name']),
        'manager_id' => $row['manager_id'] !== null ? (int)$row['manager_id'] : null,
        'level_rank' => $row['level_rank'] !== null ? (int)$row['level_rank'] : null,
    ];
}
function resolveAsmFor_dmta(int $dmId, array $staffById): string {
    $cur = $staffById[$dmId]['manager_id'] ?? null;
    $depth = 0;
    while ($cur !== null && isset($staffById[$cur]) && $depth < 10) {
        if (($staffById[$cur]['level_rank'] ?? null) === 2) { return $staffById[$cur]['name']; }
        $cur = $staffById[$cur]['manager_id'];
        $depth++;
    }
    return 'Unassigned';
}

$asmRows = []; // asm name => ['districts'=>[], 'monthly'=>0,'weekly_target'=>0,'weekly_achieved'=>0]
foreach ($dmIds as $id) {
    $asmName = resolveAsmFor_dmta($id, $staffById);
    if (!isset($asmRows[$asmName])) { $asmRows[$asmName] = ['districts' => [], 'monthly' => 0.0, 'weekly_target' => 0.0, 'weekly_achieved' => 0.0]; }
    if ($scopeNamesByDm[$id] !== '') { $asmRows[$asmName]['districts'][] = $scopeNamesByDm[$id]; }
    $asmRows[$asmName]['monthly'] += $monthlyStats[$id]['target'] ?? 0.0;
    $asmRows[$asmName]['weekly_target'] += $weeklyStats[$id]['target'] ?? 0.0;
    $asmRows[$asmName]['weekly_achieved'] += ($weeklyStats[$id]['achieved'] ?? 0.0) + ($tpOwnAmtByDm[$id] ?? 0.0);
}
$asmNames = array_keys($asmRows);
sort($asmNames, SORT_STRING | SORT_FLAG_CASE);
$asmNames = array_values(array_filter($asmNames, fn($n) => $n !== 'Unassigned'));
if (isset($asmRows['Unassigned'])) { $asmNames[] = 'Unassigned'; }

require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('DM Target vs Achievement');

$sheet->setCellValue('A1', 'DM Target vs Achievement | Monthly: ' . date('d-m-Y', strtotime($monthlyFrom)) . ' to ' . date('d-m-Y', strtotime($monthlyTo))
    . ' | Weekly: ' . date('d-m-Y', strtotime($weeklyFrom)) . ' to ' . date('d-m-Y', strtotime($weeklyTo)));
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

$headerRow = 3;
$headers = ['DM Name', 'Assigned District/Division', 'Monthly Target', 'Weekly Target', 'Weekly Achieved'];
foreach ($headers as $i => $h) { $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $headerRow, $h); }
$headerRange = 'A' . $headerRow . ':E' . $headerRow;
$sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
$sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

$row = $headerRow + 1;
foreach ($dmIds as $id) {
    $sheet->setCellValue('A' . $row, $dmList[$id]);
    $sheet->setCellValue('B' . $row, $scopeNamesByDm[$id]);
    $sheet->setCellValue('C' . $row, $monthlyStats[$id]['target'] ?? 0.0);
    $sheet->setCellValue('D' . $row, $weeklyStats[$id]['target'] ?? 0.0);
    $sheet->setCellValue('E' . $row, ($weeklyStats[$id]['achieved'] ?? 0.0) + ($tpOwnAmtByDm[$id] ?? 0.0));
    $sheet->getStyle('C' . $row . ':E' . $row)->getNumberFormat()->setFormatCode('#,##0');
    $row++;
}

if ($row > $headerRow + 1) {
    $sheet->getStyle('A' . ($headerRow + 1) . ':E' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}
foreach (range(1, 5) as $i) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}
$sheet->freezePane('A' . ($headerRow + 1));

// ── ASM sheet ────────────────────────────────────────────────────────────
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('ASM Target vs Achievement');

$sheet2->setCellValue('A1', 'ASM Target vs Achievement | Monthly: ' . date('d-m-Y', strtotime($monthlyFrom)) . ' to ' . date('d-m-Y', strtotime($monthlyTo))
    . ' | Weekly: ' . date('d-m-Y', strtotime($weeklyFrom)) . ' to ' . date('d-m-Y', strtotime($weeklyTo)));
$sheet2->mergeCells('A1:E1');
$sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(13);

$headers2 = ['ASM Name', 'Districts/Divisions (via their DMs)', 'Monthly Target', 'Weekly Target', 'Weekly Achieved'];
foreach ($headers2 as $i => $h) { $sheet2->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $headerRow, $h); }
$headerRange2 = 'A' . $headerRow . ':E' . $headerRow;
$sheet2->getStyle($headerRange2)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet2->getStyle($headerRange2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
$sheet2->getStyle($headerRange2)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet2->getStyle($headerRange2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

$row2 = $headerRow + 1;
foreach ($asmNames as $asmName) {
    $a = $asmRows[$asmName];
    $sheet2->setCellValue('A' . $row2, $asmName);
    $sheet2->setCellValue('B' . $row2, implode(' / ', $a['districts']));
    $sheet2->setCellValue('C' . $row2, $a['monthly']);
    $sheet2->setCellValue('D' . $row2, $a['weekly_target']);
    $sheet2->setCellValue('E' . $row2, $a['weekly_achieved']);
    $sheet2->getStyle('C' . $row2 . ':E' . $row2)->getNumberFormat()->setFormatCode('#,##0');
    $sheet2->getStyle('B' . $row2)->getAlignment()->setWrapText(true);
    $row2++;
}
if ($row2 > $headerRow + 1) {
    $sheet2->getStyle('A' . ($headerRow + 1) . ':E' . ($row2 - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}
foreach (range(1, 5) as $i) {
    $sheet2->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(28);
}
$sheet2->freezePane('A' . ($headerRow + 1));
$spreadsheet->setActiveSheetIndex(0);

$filename = 'DM_Target_vs_Achievement_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new XlsxWriter($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;
