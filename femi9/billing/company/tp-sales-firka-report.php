<?php
/**
 * TP Sales & Firka Coverage Report (network-wide, all history) — PDF only.
 *
 * Covers Territory Partner SELL-OUT (TP -> shop), not TP purchases from
 * the company/SS. Sales come from user_invoice where
 * from_user_type='territory_partner' AND to_user_type='shop' — the same
 * verified invoice pipeline used by shop-purchase-frequency-report.php.
 * tp_orders / tp_invoices / tp_purchase_orders are deliberately NOT used:
 * tp_orders only covers ~6 weeks with a garbage qty outlier and most rows
 * un-invoiced, and tp_invoices/tp_purchase_orders are the TP's purchase
 * side (excluded per requirement).
 *
 * Geography: firkas are partner_location_nodes at depth=6 ("FIRKA
 * (AREA)"), the level TPs are actually assigned at
 * (territory_partner_locations, is_tp_filter_enabled=1 on that layer).
 * shop.firka_id is an orphaned legacy column (no lookup table backs it,
 * and it actually lines up with depth=5/TALUK data, not depth=6) so shop
 * density cannot be used to judge firka potential — coverage and
 * performance are judged purely from TP assignment + TP sales.
 *
 * "Filled" firka = has >=1 TP assigned via territory_partner_locations
 * AND at least one of those TPs has a sale (per the definition above) to
 * a shop in that firka's own district (shop.district_id, matched to the
 * firka's depth-3 ancestor). A firka with a TP on paper but zero sales in
 * its district is treated as unfilled — assigned-but-idle is the
 * important case to surface, not hide behind a "has a TP" checkbox.
 */

include("checksession.php");
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../shared/ShopInsightService.php';

use Dompdf\Dompdf;
use Dompdf\Options;

error_reporting(0);
date_default_timezone_set("Asia/Kolkata");
set_time_limit(300);
ini_set('memory_limit', '512M');

$today  = new DateTimeImmutable('today');
$todayS = $today->format('Y-m-d');

/* ------------------------------------------------------------------ *
 * 1. Firka master: every depth-6 node with its target and district.
 * ------------------------------------------------------------------ */
$firkas = []; // firka_id => [name, target, district_id, district_name]
$fr = mysqli_query($db_conn, "
    SELECT f.id AS firka_id, f.name AS firka_name, f.target_amount,
           d.id AS district_id, d.name AS district_name
    FROM partner_location_nodes f
    JOIN partner_location_nodes t  ON t.id  = f.parent_id   -- taluk (depth 5)
    JOIN partner_location_nodes dv ON dv.id = t.parent_id   -- division (depth 4)
    JOIN partner_location_nodes d  ON d.id  = dv.parent_id  -- district (depth 3)
    WHERE f.depth = 6 AND f.is_active = 1
");
while ($row = mysqli_fetch_assoc($fr)) {
    $firkas[(int)$row['firka_id']] = [
        'name'          => $row['firka_name'],
        'target'        => (float)$row['target_amount'],
        'district_id'   => (int)$row['district_id'],
        'district_name' => $row['district_name'],
    ];
}
if (!$firkas) {
    header('Content-Type: text/plain');
    echo 'No firka location data found.';
    exit;
}

/* ------------------------------------------------------------------ *
 * 2. TP -> firka assignments (many-to-many).
 * ------------------------------------------------------------------ */
$firkaTpIds = [];  // firka_id => [tp_id, ...]
$tpFirkaIds = [];  // tp_id => [firka_id, ...]
$ar = mysqli_query($db_conn, "
    SELECT tpl.territory_partner_id AS tp_id, tpl.location_id AS firka_id
    FROM territory_partner_locations tpl
    JOIN partner_location_nodes n ON n.id = tpl.location_id AND n.depth = 6
");
while ($row = mysqli_fetch_assoc($ar)) {
    $tp = (int)$row['tp_id']; $fk = (int)$row['firka_id'];
    if (!isset($firkas[$fk])) continue;
    $firkaTpIds[$fk][] = $tp;
    $tpFirkaIds[$tp][] = $fk;
}

/* ------------------------------------------------------------------ *
 * 3. TP master (active + inactive, not deleted).
 * ------------------------------------------------------------------ */
$tps = []; // tp_id => aggregate
$tr = mysqli_query($db_conn, "
    SELECT id, tp_id, name, is_active, branch_district, created_at
    FROM territory_partners
    WHERE deleted_at IS NULL
");
while ($row = mysqli_fetch_assoc($tr)) {
    $tps[(int)$row['id']] = [
        'id'          => (int)$row['id'],
        'code'        => $row['tp_id'],
        'name'        => trim($row['name']) ?: '(unnamed)',
        'is_active'   => (int)$row['is_active'] === 1,
        'branch'      => $row['branch_district'],
        'onboarded'   => $row['created_at'] ? substr($row['created_at'], 0, 10) : null,
        'firkas'      => $tpFirkaIds[(int)$row['id']] ?? [],
        'qty'         => 0.0,
        'value'       => 0.0,
        'sale_count'  => 0,
        'shop_count'  => 0,
        'first_sale'  => null,
        'last_sale'   => null,
        'districts'   => [],   // district_id => true, from shops actually sold to
    ];
}

/* ------------------------------------------------------------------ *
 * 4. TP sell-out: user_invoice (TP -> shop), joined to the shop's
 *    district so "filled" can require real activity in the firka's own
 *    district, not just a name on the assignment list.
 * ------------------------------------------------------------------ */
$sr = mysqli_query($db_conn, "
    SELECT ui.from_user_id AS tp_id, ui.to_user_id AS shop_key, ui.date AS pdate,
           CAST(ui.total AS DECIMAL(14,2)) AS inv_total,
           COALESCE(it.qty_total, 0) AS qty_total,
           s.district_id AS shop_district_id
    FROM user_invoice ui
    LEFT JOIN (
        SELECT inv_id, SUM(qty) AS qty_total
        FROM user_invoice_items
        WHERE to_user_type = 'shop'
        GROUP BY inv_id
    ) it ON it.inv_id = ui.inv_id
    LEFT JOIN shop s ON s.temp_id = ui.to_user_id
    WHERE ui.from_user_type = 'territory_partner'
      AND ui.to_user_type = 'shop'
      AND ui.status <> 'cancelled'
      AND ui.deleted_at IS NULL
      AND ui.voided_at IS NULL
      AND ui.date IS NOT NULL
      AND ui.date > '2000-01-01'
");
$shopsPerTp = []; // tp_id => [shop_key => true]
while ($row = mysqli_fetch_assoc($sr)) {
    $tpId = (int)$row['tp_id'];
    if (!isset($tps[$tpId])) continue; // sale from a since-deleted TP row
    $t =& $tps[$tpId];
    $d = new DateTimeImmutable($row['pdate']);

    $t['qty']        += (float)$row['qty_total'];
    $t['value']       += (float)$row['inv_total'];
    $t['sale_count']++;
    $t['first_sale']  = $t['first_sale'] === null ? $d : min($t['first_sale'], $d);
    $t['last_sale']   = $t['last_sale']  === null ? $d : max($t['last_sale'], $d);
    $shopsPerTp[$tpId][$row['shop_key']] = true;
    if ($row['shop_district_id']) $t['districts'][(int)$row['shop_district_id']] = true;
    unset($t);
}
foreach ($shopsPerTp as $tpId => $shopSet) $tps[$tpId]['shop_count'] = count($shopSet);
mysqli_free_result($sr);

$dormantCutoff = $today->modify('-60 days');
foreach ($tps as &$t) {
    $t['days_since_last_sale'] = $t['last_sale'] ? (int)$t['last_sale']->diff($today)->days : null;
    $t['is_dormant'] = $t['is_active'] && ($t['last_sale'] === null || $t['last_sale'] < $dormantCutoff);
    // Sales rate = this TP's sell-out value vs the summed target of the
    // firkas assigned to them (their "quota").
    $quota = 0.0;
    foreach ($t['firkas'] as $fk) $quota += $firkas[$fk]['target'] ?? 0;
    $t['quota'] = $quota;
    $t['sales_rate'] = $quota > 0 ? $t['value'] / $quota : null;
}
unset($t);

/* ------------------------------------------------------------------ *
 * 5. Per-firka fill status: assigned + has a TP with a sale in the
 *    firka's own district.
 * ------------------------------------------------------------------ */
foreach ($firkas as $fk => &$f) {
    $assignedTps = $firkaTpIds[$fk] ?? [];
    $f['assigned_tp_count'] = count($assignedTps);
    $sellingTps = [];
    $salesValue = 0.0;
    foreach ($assignedTps as $tpId) {
        if (!isset($tps[$tpId])) continue;
        if (isset($tps[$tpId]['districts'][$f['district_id']])) {
            $sellingTps[] = $tpId;
            // Attribute a share of the TP's value to this firka's target
            // weight among the TP's own assigned firkas (best available
            // split — the invoice itself only knows the shop, not which
            // firka the shop sits in, since that link doesn't exist).
        }
    }
    $f['selling_tp_count'] = count($sellingTps);
    $f['filled'] = $f['assigned_tp_count'] > 0 && $f['selling_tp_count'] > 0;
    $f['status'] = $f['assigned_tp_count'] === 0
        ? 'unassigned'
        : ($f['selling_tp_count'] > 0 ? 'filled' : 'assigned_idle');
}
unset($f);

/* ------------------------------------------------------------------ *
 * 6. Roll-ups.
 * ------------------------------------------------------------------ */
$totalFirkas     = count($firkas);
$unassignedFirkas = array_filter($firkas, fn($f) => $f['status'] === 'unassigned');
$idleFirkas       = array_filter($firkas, fn($f) => $f['status'] === 'assigned_idle');
$filledFirkas     = array_filter($firkas, fn($f) => $f['status'] === 'filled');

$totalTargetAll     = array_sum(array_map(fn($f) => $f['target'], $firkas));
$totalTargetUnfilled = array_sum(array_map(fn($f) => $f['target'], $unassignedFirkas));
$totalTargetIdle     = array_sum(array_map(fn($f) => $f['target'], $idleFirkas));

$activeTps  = array_filter($tps, fn($t) => $t['is_active']);
$dormantTps = array_filter($tps, fn($t) => $t['is_dormant']);
$sellingTpsTotal = array_filter($tps, fn($t) => $t['sale_count'] > 0);

$totalTpQty = array_sum(array_map(fn($t) => $t['qty'], $tps));
$totalTpValue = array_sum(array_map(fn($t) => $t['value'], $tps));

// District roll-up for the heatmap: fill rate + sales-rate-vs-target.
$byDistrict = []; // district_id => aggregate
foreach ($firkas as $fk => $f) {
    $did = $f['district_id'];
    if (!isset($byDistrict[$did])) {
        $byDistrict[$did] = [
            'name' => $f['district_name'], 'total' => 0, 'filled' => 0,
            'idle' => 0, 'unassigned' => 0, 'target' => 0.0,
        ];
    }
    $byDistrict[$did]['total']++;
    $byDistrict[$did]['target'] += $f['target'];
    if ($f['status'] === 'filled') $byDistrict[$did]['filled']++;
    elseif ($f['status'] === 'assigned_idle') $byDistrict[$did]['idle']++;
    else $byDistrict[$did]['unassigned']++;
}
// District sales value = sum of value from TPs whose district-of-sale set includes this district.
foreach ($tps as $t) {
    foreach (array_keys($t['districts']) as $did) {
        if (!isset($byDistrict[$did])) continue;
        // Split the TP's total value evenly across the districts they
        // actually sold into — an approximation, flagged in the footer,
        // since one TP invoice doesn't carry a firka/district split.
        $n = count($t['districts']);
        $byDistrict[$did]['sales_value'] = ($byDistrict[$did]['sales_value'] ?? 0) + ($n ? $t['value'] / $n : 0);
    }
}
foreach ($byDistrict as &$d) {
    $d['fill_rate'] = $d['total'] ? $d['filled'] / $d['total'] : 0;
    $d['sales_value'] = $d['sales_value'] ?? 0.0;
    $d['sales_rate'] = $d['target'] > 0 ? $d['sales_value'] / $d['target'] : null;
}
unset($d);
uasort($byDistrict, fn($a, $b) => $a['fill_rate'] <=> $b['fill_rate']);

/* ------------------------------------------------------------------ *
 * 7. Lists.
 * ------------------------------------------------------------------ */
$tpArr = array_values($tps);

$tpBySalesRate = array_values(array_filter($tpArr, fn($t) => $t['is_active']));
usort($tpBySalesRate, function ($a, $b) {
    // Nulls (no quota / no firka assigned) sort last.
    if ($a['sales_rate'] === null && $b['sales_rate'] === null) return $b['value'] <=> $a['value'];
    if ($a['sales_rate'] === null) return 1;
    if ($b['sales_rate'] === null) return -1;
    return $a['sales_rate'] <=> $b['sales_rate'];
});

$topTps = $tpArr;
usort($topTps, fn($a, $b) => $b['value'] <=> $a['value']);
$topTps = array_slice($topTps, 0, 30);

$dormantList = array_values($dormantTps);
usort($dormantList, fn($a, $b) => ($b['days_since_last_sale'] ?? 99999) <=> ($a['days_since_last_sale'] ?? 99999));

$unfilledList = array_values($unassignedFirkas);
usort($unfilledList, fn($a, $b) => $b['target'] <=> $a['target']);
$unfilledList = array_slice($unfilledList, 0, 50);

$idleList = array_values($idleFirkas);
usort($idleList, fn($a, $b) => $b['target'] <=> $a['target']);
$idleList = array_slice($idleList, 0, 50);

/* ------------------------------------------------------------------ *
 * 8. AI narrative.
 * ------------------------------------------------------------------ */
$briefTps = fn($list) => array_map(fn($t) => [
    'tp' => $t['name'], 'code' => $t['code'],
    'sales_value' => round($t['value']), 'shops_sold_to' => $t['shop_count'],
    'sales_rate' => $t['sales_rate'] !== null ? round($t['sales_rate'] * 100, 1) . '%' : null,
    'days_since_last_sale' => $t['days_since_last_sale'],
], array_slice($list, 0, 15));

$briefFirkas = fn($list) => array_map(fn($f) => [
    'firka' => $f['name'], 'district' => $f['district_name'], 'target' => round($f['target']),
], array_slice($list, 0, 15));

$summaryForAI = [
    'note' => 'TP sales = TP-to-shop invoices only (sell-out), not TP purchases. Firka coverage judged purely '
        . 'from TP assignment + sales, since shop records have no reliable firka link.',
    'total_tps' => count($tps), 'active_tps' => count($activeTps),
    'dormant_active_tps_60d' => count($dormantTps),
    'total_firkas' => $totalFirkas,
    'firkas_filled' => count($filledFirkas), 'firkas_assigned_idle' => count($idleFirkas),
    'firkas_unassigned' => count($unassignedFirkas),
    'unassigned_firka_target_total' => round($totalTargetUnfilled),
    'assigned_idle_firka_target_total' => round($totalTargetIdle),
    'network_total_sell_out_qty' => round($totalTpQty),
    'network_total_sell_out_value' => round($totalTpValue),
    'top_tps_by_value' => $briefTps($topTps),
    'worst_sales_rate_active_tps' => $briefTps($tpBySalesRate),
    'dormant_tps_60d' => $briefTps($dormantList),
    'top_unfilled_firkas_by_target' => $briefFirkas($unfilledList),
    'top_assigned_idle_firkas_by_target' => $briefFirkas($idleList),
];

$tpFraming = <<<FRAMING
You are a sales analyst for "Femi9", a company selling sanitary/hygiene
products through a distribution network. Territory Partners (TPs) are
field sales agents each assigned to one or more "firkas" (small
geographic areas, each with its own sales target) who sell stock on to
retail shops.

Below is a pre-computed summary of TP SELL-OUT performance (TP-to-shop
sales only — never what the TP itself purchased) and geographic coverage,
covering the entire history of the billing system. Key terms:
- "sales rate": a TP's total sell-out value divided by the summed target
  of the firkas assigned to them (their quota). Below 100% means they are
  behind their own quota.
- "dormant TP": marked active in the system but with zero sales in the
  last 60 days.
- "unassigned firka": has no TP assigned at all — a coverage gap.
- "assigned-but-idle firka": has a TP assigned on paper, but that TP has
  no recorded sales in the firka's own district — a real-activity gap
  hiding behind a coverage checkbox.
- "target": the firka's own sales quota (target_amount), independent of
  which TP if any is covering it.

Write a tight briefing of about 180-220 words, in plain business English,
for a sales manager. Cover, in flowing prose (not headings):
1. Overall TP performance — how sales rates are distributed among active
   TPs, and whether the dormant-TP rate is a concern.
2. Which TPs are the biggest opportunities to fix (high quota, low sales
   rate) versus which are simply idle candidates for reassignment.
3. The scale of the geography gap — unassigned firkas and assigned-but-
   idle firkas, and roughly how much sales target sits unaddressed in
   each.
4. Whether reassigning dormant TPs into unfilled firkas looks like a
   faster fix than fresh recruitment, based on the numbers given.
5. Three concrete, specific actions the field team should take.
FRAMING;

$aiSvc = new ShopInsightService();
$ai = $aiSvc->analyze($summaryForAI, $tpFraming);

/* ------------------------------------------------------------------ *
 * 9. Render helpers.
 * ------------------------------------------------------------------ */
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function nfmt($v, $dec = 0) { return number_format((float)$v, $dec); }
function money($v) { return 'Rs. ' . number_format((float)$v, 0); }
function pct($v, $dec = 0) { return $v === null ? '-' : number_format($v * 100, $dec) . '%'; }

function svg_bar_chart($data, $opts = []) {
    $w        = $opts['width']   ?? 1000;
    $barH     = $opts['bar_h']   ?? 20;
    $gap      = $opts['bar_gap'] ?? 8;
    $labelW   = $opts['label_w'] ?? 230;
    $valFmt   = $opts['val_fmt'] ?? 'number_format';
    $color    = $opts['color']   ?? '#2563eb';
    $n        = max(1, count($data));
    $height   = $n * ($barH + $gap) + 12;
    $max      = max(1, max(array_map('floatval', $data ?: [0])));
    $chartW   = $w - $labelW - 90;

    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $svg  = '<?xml version="1.0" encoding="UTF-8"?>';
    $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $height
          . '" viewBox="0 0 ' . $w . ' ' . $height . '">';
    $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';
    $y = 6;
    foreach ($data as $label => $val) {
        $bw = max(1.0, ($val / $max) * $chartW);
        $ty = $y + $barH - 6;
        $svg .= '<text x="4" y="' . $ty . '" font-family="sans-serif" font-size="11" fill="#333">' . $esc($label) . '</text>';
        $svg .= '<rect x="' . $labelW . '" y="' . $y . '" width="' . round($bw, 1) . '" height="' . $barH . '" fill="' . $color . '"/>';
        $svg .= '<text x="' . round($labelW + $bw + 6, 1) . '" y="' . $ty . '" font-family="sans-serif" font-size="11" fill="#333">' . $esc($valFmt($val)) . '</text>';
        $y += $barH + $gap;
    }
    $svg .= '</svg>';
    return '<img src="data:image/svg+xml;base64,' . base64_encode($svg)
        . '" style="width:100%; max-width:' . $w . 'px;" alt="chart"/>';
}

// Heatmap: one row per district, colored cells for fill-rate and sales-rate.
function heat_color($ratio) {
    // ratio 0..1 -> red (#dc2626) through amber (#f59e0b) to green (#16a34a).
    $ratio = max(0, min(1, $ratio));
    if ($ratio < 0.5) {
        $t = $ratio / 0.5;
        $r = 220 + $t * (245 - 220); $g = 38 + $t * (158 - 38); $b = 38 + $t * (11 - 38);
    } else {
        $t = ($ratio - 0.5) / 0.5;
        $r = 245 + $t * (22 - 245); $g = 158 + $t * (163 - 158); $b = 11 + $t * (74 - 11);
    }
    return sprintf('#%02x%02x%02x', (int)$r, (int)$g, (int)$b);
}

function svg_heatmap($rows, $opts = []) {
    $w      = $opts['width']  ?? 1000;
    $rowH   = $opts['row_h']  ?? 20;
    $labelW = $opts['label_w'] ?? 260;
    $colW   = $opts['col_w']  ?? 90;
    $cols   = $opts['cols'];   // [ ['key'=>..,'head'=>..,'ratio'=>true/false,'fmt'=>fn], ... ]
    $n      = max(1, count($rows));
    $headH  = 22;
    $height = $headH + $n * $rowH + 8;

    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $svg  = '<?xml version="1.0" encoding="UTF-8"?>';
    $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $height
          . '" viewBox="0 0 ' . $w . ' ' . $height . '">';
    $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';

    $x = $labelW;
    foreach ($cols as $c) {
        $svg .= '<text x="' . ($x + $colW / 2) . '" y="15" font-family="sans-serif" font-size="10" fill="#333" text-anchor="middle">' . $esc($c['head']) . '</text>';
        $x += $colW;
    }

    $y = $headH;
    foreach ($rows as $r) {
        $svg .= '<text x="2" y="' . ($y + $rowH - 6) . '" font-family="sans-serif" font-size="10" fill="#222">' . $esc(mb_strimwidth($r['label'], 0, 34, '…')) . '</text>';
        $x = $labelW;
        foreach ($cols as $c) {
            $val = $r[$c['key']] ?? null;
            $fillColor = ($c['ratio'] && $val !== null) ? heat_color($val) : '#eeeeee';
            $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . ($colW - 4) . '" height="' . ($rowH - 4) . '" fill="' . $fillColor . '"/>';
            $label = $c['fmt'] ? $c['fmt']($val, $r) : (string)$val;
            $textColor = $c['ratio'] ? '#1a1a1a' : '#222';
            $svg .= '<text x="' . ($x + ($colW - 4) / 2) . '" y="' . ($y + $rowH - 8) . '" font-family="sans-serif" font-size="9.5" fill="' . $textColor . '" text-anchor="middle">' . $esc($label) . '</text>';
            $x += $colW;
        }
        $y += $rowH;
    }
    $svg .= '</svg>';
    return '<img src="data:image/svg+xml;base64,' . base64_encode($svg)
        . '" style="width:100%; max-width:' . $w . 'px;" alt="heatmap"/>';
}

function rows_table($title, $note, $list, $cols) {
    $html  = '<h3>' . h($title) . '</h3>';
    if ($note) $html .= '<p class="muted">' . h($note) . '</p>';
    if (!$list) return $html . '<p class="muted">None.</p>';
    $html .= '<table class="grid"><thead><tr><th>#</th>';
    foreach ($cols as $c) $html .= '<th' . (($c['num'] ?? false) ? ' class="r"' : '') . '>' . h($c['head']) . '</th>';
    $html .= '</tr></thead><tbody>';
    $i = 0;
    foreach ($list as $row) {
        $i++;
        $html .= '<tr><td>' . $i . '</td>';
        foreach ($cols as $c) {
            $v = $c['fmt']($row);
            $html .= '<td' . (($c['num'] ?? false) ? ' class="r"' : '') . '>' . h($v) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

/* ------------------------------------------------------------------ *
 * 10. Build HTML.
 * ------------------------------------------------------------------ */
$genAt = $today->format('d M Y');

$tpCols = [
    ['head' => 'TP',            'fmt' => fn($t) => $t['name'] . ' (' . $t['code'] . ')'],
    ['head' => 'Firkas',        'num' => true, 'fmt' => fn($t) => nfmt(count($t['firkas']))],
    ['head' => 'Shops sold to', 'num' => true, 'fmt' => fn($t) => nfmt($t['shop_count'])],
    ['head' => 'Sales value',   'num' => true, 'fmt' => fn($t) => money($t['value'])],
    ['head' => 'Sales qty',     'num' => true, 'fmt' => fn($t) => nfmt($t['qty'])],
    ['head' => 'Quota (target)','num' => true, 'fmt' => fn($t) => $t['quota'] > 0 ? money($t['quota']) : '-'],
    ['head' => 'Sales rate',    'num' => true, 'fmt' => fn($t) => pct($t['sales_rate'])],
    ['head' => 'Last sale',     'num' => true, 'fmt' => fn($t) => $t['last_sale'] ? $t['last_sale']->format('d-M-Y') : 'never'],
    ['head' => 'Days idle',     'num' => true, 'fmt' => fn($t) => $t['days_since_last_sale'] ?? 'never sold'],
];

$firkaCols = [
    ['head' => 'Firka',    'fmt' => fn($f) => $f['name']],
    ['head' => 'District', 'fmt' => fn($f) => $f['district_name']],
    ['head' => 'Target',   'num' => true, 'fmt' => fn($f) => money($f['target'])],
];
$firkaColsIdle = $firkaCols;
$firkaColsIdle[] = ['head' => 'TPs assigned', 'num' => true, 'fmt' => fn($f) => nfmt($f['assigned_tp_count'])];

// Heatmap rows: top 40 districts by firka count (keeps the SVG readable).
$heatRows = [];
$byDistrictArr = $byDistrict;
uasort($byDistrictArr, fn($a, $b) => $b['total'] <=> $a['total']);
foreach (array_slice($byDistrictArr, 0, 40, true) as $d) {
    $heatRows[] = [
        'label' => $d['name'] . ' (' . $d['total'] . ' firkas)',
        'fill' => $d['fill_rate'],
        'sales' => $d['sales_rate'],
    ];
}
$heatCols = [
    ['key' => 'fill', 'head' => 'Fill rate', 'ratio' => true, 'fmt' => fn($v) => $v === null ? '-' : round($v * 100) . '%'],
    ['key' => 'sales', 'head' => 'Sales vs target', 'ratio' => true, 'fmt' => fn($v) => $v === null ? 'n/a' : round($v * 100) . '%'],
];

ob_start();
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 10px; color: #222; }
    h1 { font-size: 20px; margin: 0 0 2px; }
    h2 { font-size: 14px; margin: 18px 0 6px; border-bottom: 2px solid #16a34a; padding-bottom: 3px; color: #14532d; }
    h3 { font-size: 12px; margin: 14px 0 4px; color: #14532d; }
    .sub { color: #666; font-size: 10px; margin-bottom: 10px; }
    .muted { color: #888; }
    .cards { width: 100%; }
    .cards td { border: 1px solid #d0d7de; padding: 7px 9px; width: 25%; vertical-align: top; }
    .cards .big { font-size: 15px; font-weight: bold; color: #111; }
    .cards .lbl { font-size: 9px; color: #666; text-transform: uppercase; letter-spacing: .3px; }
    table.grid { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    table.grid th, table.grid td { border: 1px solid #d0d7de; padding: 3px 5px; font-size: 9px; }
    table.grid th { background: #eafaf0; text-align: left; }
    table.grid td.r, table.grid th.r { text-align: right; }
    .chartbox { border: 1px solid #e5e7eb; padding: 8px; margin-bottom: 8px; }
    .ai { border: 1px solid #bbf7d0; background: #f3fbf6; padding: 12px 14px; margin-top: 6px; line-height: 1.5; font-size: 10.5px; }
    .ai .cap { font-size: 9px; color: #6b7280; margin-top: 8px; }
    .pb { page-break-before: always; }
    .foot { color: #999; font-size: 8px; margin-top: 4px; }
    .legend { font-size: 9px; color: #555; margin: 4px 0 10px; }
    .legend span { display: inline-block; width: 12px; height: 12px; margin-right: 3px; vertical-align: middle; }
</style>
</head><body>

<h1>TP Sales &amp; Firka Coverage Report</h1>
<div class="sub">
    TP sell-out only (TP &rarr; shop invoices) &nbsp;|&nbsp; All history to <?= h($genAt) ?> &nbsp;|&nbsp; Generated <?= h($genAt) ?>
    <br>Firka coverage judged from TP assignment + sales activity only (shop records have no reliable firka link).
</div>

<h2>Network Summary</h2>
<table class="cards"><tr>
    <td><div class="lbl">Total TPs</div><div class="big"><?= nfmt(count($tps)) ?></div><div class="lbl"><?= nfmt(count($activeTps)) ?> active</div></td>
    <td><div class="lbl">Dormant active TPs (60d+)</div><div class="big"><?= nfmt(count($dormantTps)) ?></div></td>
    <td><div class="lbl">Total firkas</div><div class="big"><?= nfmt($totalFirkas) ?></div></td>
    <td><div class="lbl">Filled firkas</div><div class="big"><?= nfmt(count($filledFirkas)) ?></div><div class="lbl"><?= pct(count($filledFirkas)/$totalFirkas) ?> of network</div></td>
</tr><tr>
    <td><div class="lbl">Unassigned firkas</div><div class="big"><?= nfmt(count($unassignedFirkas)) ?></div><div class="lbl"><?= money($totalTargetUnfilled) ?> target unaddressed</div></td>
    <td><div class="lbl">Assigned but idle firkas</div><div class="big"><?= nfmt(count($idleFirkas)) ?></div><div class="lbl"><?= money($totalTargetIdle) ?> target unmet</div></td>
    <td><div class="lbl">Network sell-out qty</div><div class="big"><?= nfmt($totalTpQty) ?></div></td>
    <td><div class="lbl">Network sell-out value</div><div class="big"><?= money($totalTpValue) ?></div></td>
</tr></table>

<h2>Firka Coverage Heatmap <span class="muted">(top 40 districts by firka count)</span></h2>
<div class="legend">
    <span style="background:#dc2626;"></span> 0%
    <span style="background:#f59e0b;"></span> 50%
    <span style="background:#16a34a;"></span> 100%
    &nbsp;&nbsp;Fill rate = % of the district's firkas with an assigned TP who has sold in that district.
    Sales-vs-target splits a TP's total value evenly across the districts they sold into (invoices don't carry a firka/district split) — treat as approximate.
</div>
<div class="chartbox">
<?= svg_heatmap($heatRows, ['cols' => $heatCols]) ?>
</div>

<div class="pb"></div>
<h2>AI Inference &mdash; TP Coverage &amp; Sales Performance</h2>
<div class="ai">
<?php if ($ai['success']): ?>
    <?= nl2br(h($ai['narrative'])) ?>
    <div class="cap">Generated by Claude (claude-sonnet-4-5) from the aggregated figures above. Treat as advisory.</div>
<?php else: ?>
    <em>AI summary unavailable &mdash; <?= h($ai['message']) ?>.</em>
    The rest of this report is unaffected.
<?php endif; ?>
</div>

<div class="pb"></div>
<h2>TP Sales Rate &mdash; All Active TPs <span class="muted">(lowest sales-vs-target first)</span></h2>
<?= rows_table('Sorted by sales rate (worst first)', 'Sales rate = TP sell-out value ÷ the summed target of firkas assigned to them. \'-\' = no firka assigned, no quota to compare against.', $tpBySalesRate, $tpCols) ?>

<div class="pb"></div>
<h2>Top 30 TPs by Sales Value</h2>
<?= rows_table('Highest sell-out value', null, $topTps, $tpCols) ?>

<div class="pb"></div>
<h2>Dormant Active TPs <span class="muted">(active, zero sales in last 60 days &mdash; reassignment candidates)</span></h2>
<?= rows_table('Longest idle first', 'These TPs are marked active but have not sold to any shop in 60+ days. Consider reassigning them to an unfilled firka below instead of recruiting fresh.', $dormantList, $tpCols) ?>

<div class="pb"></div>
<h2>Unfilled Firkas &mdash; No TP Assigned <span class="muted">(potential: highest target first)</span></h2>
<?= rows_table('Top 50 by target amount', 'These firkas have zero TPs assigned. Ranked by sales target, i.e. where a new TP would have the most upside.', $unfilledList, $firkaCols) ?>

<div class="pb"></div>
<h2>Assigned-but-Idle Firkas <span class="muted">(TP on paper, no sales in the district)</span></h2>
<?= rows_table('Top 50 by target amount', 'A TP is assigned to these firkas but none of their sales fall in the firka\'s district. Investigate before recruiting a new TP here.', $idleList, $firkaColsIdle) ?>

<div class="foot">
    Excludes cancelled, voided and soft-deleted invoices/TP records. "Sales" = TP-to-shop invoice value/qty (sell-out), never TP purchases from company/SS.
    Sales rate and quota compare a TP's total sell-out against the summed target_amount of their assigned firkas; a TP with no firka assignment has no quota to compare against.
    District sales-vs-target and the heatmap split a TP's value evenly across every district they sold into, since an invoice itself does not carry a firka/district tag &mdash; treat these two figures as approximate, all other figures are exact.
</div>

</body></html>
<?php
$html = ob_get_clean();

if (($_GET['debug'] ?? '') === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

/* ------------------------------------------------------------------ *
 * 11. Render PDF.
 * ------------------------------------------------------------------ */
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'landscape');
$dompdf->loadHtml($html);
$dompdf->render();

$fileName = 'TP_Sales_Firka_Coverage_' . $todayS . '.pdf';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$dompdf->stream($fileName, ['Attachment' => false]);
