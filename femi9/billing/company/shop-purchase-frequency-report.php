<?php
/**
 * Shop Purchase-Frequency Report (network-wide, all history) — PDF only.
 *
 * Hitting this URL as a logged-in company user streams an A4-landscape
 * PDF: how often retail shops re-order stock from the network, the gap
 * between consecutive purchases (restock cadence), sell-in volume, a set
 * of top/bottom shop lists, inline-SVG charts, and a short AI-written
 * narrative.
 *
 * Scope note: shops are almost never billed directly by a company godown
 * (they buy from stockists / distributors / TPs), so this report is
 * deliberately network-wide and does NOT apply the GodownAccess godown
 * filter — it aggregates every user_invoice with to_user_type='shop'
 * (excluding cancelled / soft-deleted / voided rows).
 *
 * "Restock gap" = days between a shop's purchase and its next purchase.
 * Only shops with >= 2 purchases have a gap; one-time buyers are counted
 * in their own bucket. Est. monthly offtake = total qty / history-span
 * days * 30 (approximate — the system has no shop-level onward sales).
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

// Report "as of" date — capped at end of June 2026 rather than the real
// current date, so all coverage / "days since last" / overdue math is
// computed against this cutoff instead of today.
$today   = new DateTimeImmutable('2026-06-30');
$todayS  = $today->format('Y-m-d');

/* ------------------------------------------------------------------ *
 * 1. Pull every valid shop purchase (one row per invoice) with its
 *    line-item quantity total.
 *
 *    Two data-quality corrections are applied here (the raw tables
 *    over-count both shops and purchases):
 *
 *    a) Duplicate shop records — the same retailer is onboarded 2-7 times,
 *       each copy with its own temp_id and its own slice of history. Shops
 *       are merged on normalised name + 10-digit mobile; a shop whose
 *       mobile isn't a clean 10 digits keeps its own temp_id as the key
 *       (can't safely merge those).
 *
 *    b) Split invoices — one purchase is sometimes written as several
 *       invoice rows on the same day. All rows for a merged shop on the
 *       same calendar date are collapsed into ONE purchase event before
 *       gaps are computed, so a shop that ordered twice in a day doesn't
 *       read as a "1-day restock cycle".
 * ------------------------------------------------------------------ */

// --- Shop master + dedupe-key map -----------------------------------
$catMap = [];
$cres = mysqli_query($db_conn, "SELECT id, catlable FROM shop_category");
while ($c = mysqli_fetch_assoc($cres)) $catMap[(int)$c['id']] = $c['catlable'];

function shop_dedupe_key($name, $mobile, $tempId) {
    $digits = preg_replace('/\D+/', '', (string)$mobile);
    $nm = strtolower(trim(preg_replace('/\s+/', ' ', (string)$name)));
    // Clean 10-digit mobile + a non-empty name => reliable identity.
    if (strlen($digits) === 10 && $nm !== '') {
        return 'nm:' . $nm . '|' . $digits;
    }
    // Otherwise fall back to the temp_id (no merge).
    return 'id:' . $tempId;
}

$mres = mysqli_query($db_conn, "SELECT temp_id, name, mobile_number, shop_cat, address FROM shop");
$keyByTempId   = [];   // temp_id => dedupe key
$masterByKey   = [];   // dedupe key => display details (first non-empty wins)
while ($m = mysqli_fetch_assoc($mres)) {
    $k = shop_dedupe_key($m['name'], $m['mobile_number'], $m['temp_id']);
    $keyByTempId[$m['temp_id']] = $k;
    if (!isset($masterByKey[$k])) {
        $masterByKey[$k] = [
            'name'   => trim($m['name']) ?: '(unnamed shop)',
            'mobile' => $m['mobile_number'],
            'cat'    => $catMap[(int)$m['shop_cat']] ?? '',
            'area'   => trim((string)$m['address']),
        ];
    } else {
        // Backfill any field the first record left blank.
        foreach (['cat', 'area'] as $f) {
            if ($masterByKey[$k][$f] === '' && trim((string)($f === 'cat'
                    ? ($catMap[(int)$m['shop_cat']] ?? '') : $m['address'])) !== '') {
                $masterByKey[$k][$f] = $f === 'cat'
                    ? ($catMap[(int)$m['shop_cat']] ?? '') : trim((string)$m['address']);
            }
        }
    }
}

// --- Raw purchase rows, grouped by merged shop + calendar day -------
$sql = "
    SELECT ui.to_user_id                         AS shop_key,
           ui.date                               AS pdate,
           CAST(ui.total AS DECIMAL(14,2))       AS inv_total,
           COALESCE(it.qty_total, 0)             AS qty_total
    FROM user_invoice ui
    LEFT JOIN (
        SELECT inv_id, SUM(qty) AS qty_total
        FROM user_invoice_items
        WHERE to_user_type = 'shop'
        GROUP BY inv_id
    ) it ON it.inv_id = ui.inv_id
    WHERE ui.to_user_type = 'shop'
      AND ui.status <> 'cancelled'
      AND ui.deleted_at IS NULL
      AND ui.voided_at IS NULL
      AND ui.date IS NOT NULL
      AND ui.date > '2000-01-01'
      AND ui.date < '" . $today->modify('+1 day')->format('Y-m-d') . "'
";
$res = mysqli_query($db_conn, $sql);

// key => ['days' => [ 'Y-m-d' => ['qty'=>x,'val'=>y], ... ]]
$byKey = [];
while ($row = mysqli_fetch_assoc($res)) {
    $tempId = $row['shop_key'];
    $k = $keyByTempId[$tempId] ?? ('id:' . $tempId);   // unmatched -> its own bucket
    $day = $row['pdate'];
    if (!isset($byKey[$k])) $byKey[$k] = [];
    if (!isset($byKey[$k][$day])) $byKey[$k][$day] = ['qty' => 0.0, 'val' => 0.0];
    $byKey[$k][$day]['qty'] += (float)$row['qty_total'];
    $byKey[$k][$day]['val'] += (float)$row['inv_total'];
}
mysqli_free_result($res);

if (!$byKey) {
    header('Content-Type: text/plain');
    echo 'No shop purchase data found.';
    exit;
}

/* ------------------------------------------------------------------ *
 * 2. One aggregate per merged shop: each distinct purchase DAY is one
 *    purchase event; gaps are the day-to-day differences.
 * ------------------------------------------------------------------ */
$shops = [];
foreach ($byKey as $k => $days) {
    ksort($days);                       // chronological
    $dates = array_keys($days);
    $n     = count($dates);
    $first = new DateTimeImmutable($dates[0]);
    $last  = new DateTimeImmutable($dates[$n - 1]);

    $qty = 0.0; $val = 0.0;
    foreach ($days as $d) { $qty += $d['qty']; $val += $d['val']; }

    $gaps = [];
    for ($i = 1; $i < $n; $i++) {
        $g = (int)(new DateTimeImmutable($dates[$i - 1]))->diff(new DateTimeImmutable($dates[$i]))->days;
        if ($g > 0) $gaps[] = $g;
    }
    $spanDays = max(1, (int)$first->diff($last)->days);

    $shops[$k] = [
        'shop_key'  => $k,
        'count'     => $n,
        'qty'       => $qty,
        'value'     => $val,
        'first'     => $first,
        'last'      => $last,
        'span_days' => $spanDays,
        'avg_gap'   => $gaps ? array_sum($gaps) / count($gaps) : null,
        'min_gap'   => $gaps ? min($gaps) : null,
        'max_gap'   => $gaps ? max($gaps) : null,
        'avg_qty'   => $n ? $qty / $n : 0,
        'days_since_last' => null,       // set against $today below
    ];
}
unset($byKey);

/* ------------------------------------------------------------------ *
 * 4. Derived per-shop fields + frequency / dormancy banding.
 * ------------------------------------------------------------------ */
function freq_band($avgGap, $count) {
    if ($count < 2)   return 'One-time';
    if ($avgGap === null) return 'One-time';
    if ($avgGap <= 10)  return 'Weekly (<=10d)';
    if ($avgGap <= 21)  return 'Fortnightly (11-21d)';
    if ($avgGap <= 45)  return 'Monthly (22-45d)';
    if ($avgGap <= 120) return 'Quarterly (46-120d)';
    return 'Rare (>120d)';
}
$FREQ_ORDER = ['Weekly (<=10d)','Fortnightly (11-21d)','Monthly (22-45d)','Quarterly (46-120d)','Rare (>120d)','One-time'];

function dormancy_band($days) {
    if ($days <= 30)  return '0-30d';
    if ($days <= 60)  return '31-60d';
    if ($days <= 90)  return '61-90d';
    if ($days <= 180) return '91-180d';
    return '180d+';
}
$DORM_ORDER = ['0-30d','31-60d','61-90d','91-180d','180d+'];

function gap_hist_band($g) {
    if ($g === null) return null;
    if ($g <= 7)   return '<=7d';
    if ($g <= 15)  return '8-15d';
    if ($g <= 30)  return '16-30d';
    if ($g <= 60)  return '31-60d';
    if ($g <= 120) return '61-120d';
    return '120d+';
}
$GAPH_ORDER = ['<=7d','8-15d','16-30d','31-60d','61-120d','120d+'];

$freqCounts = array_fill_keys($FREQ_ORDER, 0);
$dormCounts = array_fill_keys($DORM_ORDER, 0);
$gapHist    = array_fill_keys($GAPH_ORDER, 0);

$totalQty = 0.0; $totalValue = 0.0;
$repeatGapValues = [];        // avg-gap of every repeat shop, for median
$oneTimeCount = 0;
$oneTimeValue = 0.0;
$overdueCount = 0;
$overdueValue = 0.0;

foreach ($shops as $key => &$s) {
    $m = $masterByKey[$key] ?? ['name' => '(shop not in master)', 'mobile' => '', 'cat' => '', 'area' => ''];
    $s['name']   = $m['name'];
    $s['mobile'] = $m['mobile'];
    $s['cat']    = $m['cat'];
    $s['area']   = $m['area'];
    $s['days_since_last'] = (int)$s['last']->diff($today)->days;
    $s['band']   = freq_band($s['avg_gap'], $s['count']);
    // Overdue: gone longer than 1.5x its own average gap without re-ordering.
    $s['overdue'] = ($s['count'] >= 2 && $s['avg_gap'] !== null && $s['avg_gap'] > 0
                     && $s['days_since_last'] > $s['avg_gap'] * 1.5);
    $s['overdue_ratio'] = ($s['avg_gap'] && $s['avg_gap'] > 0)
        ? $s['days_since_last'] / $s['avg_gap'] : 0;
    // Est. monthly offtake only makes sense once a shop has a real
    // purchase history to extrapolate from. A single order (or a few
    // orders inside one week) gives a meaningless span, so a lone 1,700-qty
    // buy would otherwise read as "51,000/mo". Require >= 2 purchases and
    // at least a 14-day span; otherwise leave it blank.
    $s['monthly_offtake'] = ($s['count'] >= 2 && $s['span_days'] >= 14)
        ? $s['qty'] / $s['span_days'] * 30
        : null;

    $totalQty   += $s['qty'];
    $totalValue += $s['value'];
    $freqCounts[$s['band']]++;
    $dormCounts[dormancy_band($s['days_since_last'])]++;

    if ($s['count'] >= 2 && $s['avg_gap'] !== null) {
        $repeatGapValues[] = $s['avg_gap'];
        // The gap histogram counts one vote per repeat shop at its average
        // gap (the raw per-interval distribution would need every gap kept
        // in memory) — the chart title says as much.
        $b = gap_hist_band($s['avg_gap']);
        if ($b) $gapHist[$b]++;
    }
    if ($s['count'] < 2) { $oneTimeCount++; $oneTimeValue += $s['value']; }
    if ($s['overdue'])   { $overdueCount++; $overdueValue += $s['value']; }
}
unset($s);

sort($repeatGapValues);
$medianGap = null;
if ($repeatGapValues) {
    $c = count($repeatGapValues);
    $medianGap = ($c % 2)
        ? $repeatGapValues[intdiv($c, 2)]
        : ($repeatGapValues[$c/2 - 1] + $repeatGapValues[$c/2]) / 2;
}

$totalShops   = count($shops);
$repeatShops  = $totalShops - $oneTimeCount;
$coverageFrom = null;
foreach ($shops as $s) {
    if ($coverageFrom === null || $s['first'] < $coverageFrom) $coverageFrom = $s['first'];
}

// How many duplicate shop records the name+mobile merge folded away
// (only counting shops that actually have purchase history), for the
// methodology note in the report.
$keyHasPurchases = array_flip(array_keys($shops));
$tempIdsPerKey   = [];
foreach ($keyByTempId as $tid => $k) {
    if (isset($keyHasPurchases[$k])) $tempIdsPerKey[$k] = ($tempIdsPerKey[$k] ?? 0) + 1;
}
$mergedAway = 0;
foreach ($tempIdsPerKey as $cnt) { if ($cnt > 1) $mergedAway += ($cnt - 1); }

/* ------------------------------------------------------------------ *
 * 5. Top / bottom lists (50 rows each).
 * ------------------------------------------------------------------ */
$byArr = array_values($shops);

$mostFrequent = array_values(array_filter($byArr, fn($s) => $s['count'] >= 3 && $s['avg_gap'] !== null));
usort($mostFrequent, fn($a, $b) => $a['avg_gap'] <=> $b['avg_gap']);
$mostFrequent = array_slice($mostFrequent, 0, 50);

$largestVolume = $byArr;
usort($largestVolume, fn($a, $b) => $b['qty'] <=> $a['qty']);
$largestVolume = array_slice($largestVolume, 0, 50);

$mostOverdue = array_values(array_filter($byArr, fn($s) => $s['count'] >= 3 && $s['overdue']));
usort($mostOverdue, fn($a, $b) => $b['overdue_ratio'] <=> $a['overdue_ratio']);
$mostOverdue = array_slice($mostOverdue, 0, 50);

$longCycleActive = array_values(array_filter($byArr,
    fn($s) => $s['count'] >= 3 && $s['avg_gap'] !== null && $s['days_since_last'] <= 120));
usort($longCycleActive, fn($a, $b) => $b['avg_gap'] <=> $a['avg_gap']);
$longCycleActive = array_slice($longCycleActive, 0, 50);

/* ------------------------------------------------------------------ *
 * 6. AI narrative.
 * ------------------------------------------------------------------ */
$briefRows = function ($list) {
    return array_map(fn($s) => [
        'shop'            => $s['name'],
        'area'            => $s['area'],
        'purchases'       => $s['count'],
        'total_qty'       => round($s['qty']),
        'total_value'     => round($s['value']),
        'avg_gap_days'    => $s['avg_gap'] !== null ? round($s['avg_gap'], 1) : null,
        'days_since_last' => $s['days_since_last'],
        'monthly_offtake' => $s['monthly_offtake'] !== null ? round($s['monthly_offtake'], 1) : null,
    ], array_slice($list, 0, 20));
};

$summaryForAI = [
    'note' => 'Shop records deduplicated by name+mobile; same-day invoices counted as one purchase. '
        . $mergedAway . ' duplicate shop records were merged.',
    'coverage_from'          => $coverageFrom ? $coverageFrom->format('Y-m-d') : null,
    'coverage_to'            => $todayS,
    'total_shops_with_purchases' => $totalShops,
    'repeat_shops'           => $repeatShops,
    'one_time_shops'         => $oneTimeCount,
    'one_time_shops_value'   => round($oneTimeValue),
    'network_total_sell_in_qty'   => round($totalQty),
    'network_total_sell_in_value' => round($totalValue),
    'median_restock_gap_days'     => $medianGap !== null ? round($medianGap, 1) : null,
    'overdue_shops'          => $overdueCount,
    'overdue_shops_value'    => round($overdueValue),
    'frequency_band_counts'  => $freqCounts,
    'dormancy_band_counts'   => $dormCounts,
    'avg_gap_distribution'   => $gapHist,
    'top_most_frequent'      => $briefRows($mostFrequent),
    'top_largest_volume'     => $briefRows($largestVolume),
    'top_most_overdue'       => $briefRows($mostOverdue),
    'top_longest_cycle_active' => $briefRows($longCycleActive),
];

$aiSvc = new ShopInsightService();
$ai = $aiSvc->analyze($summaryForAI);

/* ------------------------------------------------------------------ *
 * 7. Render helpers.
 * ------------------------------------------------------------------ */
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function nfmt($v, $dec = 0) { return number_format((float)$v, $dec); }
function money($v) { return 'Rs. ' . number_format((float)$v, 0); }

/**
 * Horizontal bar chart returned as a self-contained SVG document wrapped
 * in a data-URI <img>. dompdf renders inline <svg> unreliably (it dumps
 * the child <text> as flowing text), but renders <img src="data:image/
 * svg+xml;base64,..."> through its SVG image backend correctly.
 * $data = ['label' => value, ...]; bars scale to the max value.
 */
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

function rows_table($title, $list, $cols) {
    $html  = '<h3>' . h($title) . '</h3>';
    if (!$list) return $html . '<p class="muted">No qualifying shops.</p>';
    $html .= '<table class="grid"><thead><tr>';
    foreach ($cols as $c) $html .= '<th' . (($c['num'] ?? false) ? ' class="r"' : '') . '>' . h($c['head']) . '</th>';
    $html .= '</tr></thead><tbody>';
    $i = 0;
    foreach ($list as $s) {
        $i++;
        $html .= '<tr><td>' . $i . '</td>';
        foreach ($cols as $c) {
            if (($c['key'] ?? '') === '_sno') continue;
            $v = $c['fmt']($s);
            $html .= '<td' . (($c['num'] ?? false) ? ' class="r"' : '') . '>' . h($v) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

$fmtName = fn($s) => $s['name'] . ($s['area'] !== '' ? ' — ' . mb_strimwidth($s['area'], 0, 40, '…') : '');

$listCols = [
    ['head' => '#',            'key' => '_sno', 'fmt' => fn($s) => ''],
    ['head' => 'Shop',         'fmt' => $fmtName],
    ['head' => 'Cat',          'fmt' => fn($s) => $s['cat']],
    ['head' => 'Buys',         'num' => true, 'fmt' => fn($s) => nfmt($s['count'])],
    ['head' => 'Avg gap (d)',  'num' => true, 'fmt' => fn($s) => $s['avg_gap'] !== null ? nfmt($s['avg_gap'], 1) : '-'],
    ['head' => 'Min/Max gap',  'num' => true, 'fmt' => fn($s) => ($s['min_gap'] ?? '-') . ' / ' . ($s['max_gap'] ?? '-')],
    ['head' => 'Since last (d)','num' => true, 'fmt' => fn($s) => nfmt($s['days_since_last'])],
    ['head' => 'Total qty',    'num' => true, 'fmt' => fn($s) => nfmt($s['qty'])],
    ['head' => 'Total value',  'num' => true, 'fmt' => fn($s) => money($s['value'])],
    ['head' => 'Offtake/mo',   'num' => true, 'fmt' => fn($s) => $s['monthly_offtake'] !== null ? nfmt($s['monthly_offtake'], 1) : '-'],
];

$overdueCols = $listCols;
$overdueCols[] = ['head' => 'Overdue x', 'num' => true, 'fmt' => fn($s) => nfmt($s['overdue_ratio'], 2)];

/* ------------------------------------------------------------------ *
 * 8. Build HTML.
 * ------------------------------------------------------------------ */
$genAt = $today->format('d M Y');
$covFromS = $coverageFrom ? $coverageFrom->format('d M Y') : '-';

ob_start();
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 10px; color: #222; }
    h1 { font-size: 20px; margin: 0 0 2px; }
    h2 { font-size: 14px; margin: 18px 0 6px; border-bottom: 2px solid #2563eb; padding-bottom: 3px; color: #1e3a8a; }
    h3 { font-size: 12px; margin: 14px 0 4px; color: #1e3a8a; }
    .sub { color: #666; font-size: 10px; margin-bottom: 10px; }
    .muted { color: #888; }
    .cards { width: 100%; }
    .cards td { border: 1px solid #d0d7de; padding: 7px 9px; width: 25%; vertical-align: top; }
    .cards .big { font-size: 15px; font-weight: bold; color: #111; }
    .cards .lbl { font-size: 9px; color: #666; text-transform: uppercase; letter-spacing: .3px; }
    table.grid { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    table.grid th, table.grid td { border: 1px solid #d0d7de; padding: 3px 5px; font-size: 9px; }
    table.grid th { background: #eef2ff; text-align: left; }
    table.grid td.r, table.grid th.r { text-align: right; }
    .chartbox { border: 1px solid #e5e7eb; padding: 8px; margin-bottom: 8px; }
    .ai { border: 1px solid #c7d2fe; background: #f5f7ff; padding: 12px 14px; margin-top: 6px; line-height: 1.5; font-size: 10.5px; }
    .ai .cap { font-size: 9px; color: #6b7280; margin-top: 8px; }
    .pb { page-break-before: always; }
    .foot { color: #999; font-size: 8px; margin-top: 4px; }
</style>
</head><body>

<h1>Shop Purchase-Frequency Report</h1>
<div class="sub">
    Network-wide (all sellers) &nbsp;|&nbsp; Coverage: <?= h($covFromS) ?> &ndash; <?= h($genAt) ?>
    &nbsp;|&nbsp; Generated <?= h($genAt) ?>
    <br>Shops deduplicated by name + mobile (<?= nfmt($mergedAway) ?> duplicate records merged);
    same-day invoices counted as one purchase.
</div>

<h2>Network Summary</h2>
<table class="cards"><tr>
    <td><div class="lbl">Shops with purchases</div><div class="big"><?= nfmt($totalShops) ?></div></td>
    <td><div class="lbl">Repeat buyers (2+)</div><div class="big"><?= nfmt($repeatShops) ?></div></td>
    <td><div class="lbl">One-time buyers</div><div class="big"><?= nfmt($oneTimeCount) ?></div><div class="lbl"><?= money($oneTimeValue) ?></div></td>
    <td><div class="lbl">Median restock gap</div><div class="big"><?= $medianGap !== null ? nfmt($medianGap, 1) . ' d' : '-' ?></div></td>
</tr><tr>
    <td><div class="lbl">Overdue to re-order</div><div class="big"><?= nfmt($overdueCount) ?></div><div class="lbl"><?= money($overdueValue) ?> at risk</div></td>
    <td><div class="lbl">Total sell-in qty</div><div class="big"><?= nfmt($totalQty) ?></div></td>
    <td><div class="lbl">Total sell-in value</div><div class="big"><?= money($totalValue) ?></div></td>
    <td><div class="lbl">Avg buys / shop</div><div class="big"><?= nfmt($totalShops ? array_sum(array_map(fn($s)=>$s['count'],$shops)) / $totalShops : 0, 1) ?></div></td>
</tr></table>

<h2>Charts</h2>
<div class="chartbox">
    <b>Shops by re-order frequency band</b>
    <?= svg_bar_chart($freqCounts, ['color' => '#2563eb']) ?>
</div>
<div class="chartbox">
    <b>Repeat shops by average restock gap</b> <span class="muted">(one shop = one vote at its average gap)</span>
    <?= svg_bar_chart($gapHist, ['color' => '#7c3aed']) ?>
</div>
<div class="chartbox">
    <b>Shops by dormancy (days since last purchase)</b>
    <?= svg_bar_chart($dormCounts, ['color' => '#dc2626']) ?>
</div>

<div class="pb"></div>
<h2>AI Inference &mdash; Sell-in &amp; Restock Cadence</h2>
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
<h2>Most Frequent Buyers <span class="muted">(lowest average restock gap, min 3 purchases)</span></h2>
<?= rows_table('Top 50 by re-order frequency', $mostFrequent, $listCols) ?>

<div class="pb"></div>
<h2>Largest-Volume Shops <span class="muted">(highest total sell-in quantity)</span></h2>
<?= rows_table('Top 50 by quantity', $largestVolume, $listCols) ?>

<div class="pb"></div>
<h2>Most Overdue to Re-order <span class="muted">(churn risk: days since last &gt; 1.5&times; own avg gap, min 3 purchases)</span></h2>
<?= rows_table('Top 50 by overdue ratio', $mostOverdue, $overdueCols) ?>

<div class="pb"></div>
<h2>Longest-Cycle Active Buyers <span class="muted">(slow but still buying &mdash; last purchase within 120 days)</span></h2>
<?= rows_table('Top 50 by average gap', $longCycleActive, $listCols) ?>

<div class="foot">
    Excludes cancelled, voided and soft-deleted invoices. "Sell-in" = quantity/value bought from the network.
    Offtake/mo is an estimate (total qty &divide; history span &times; 30); the system has no shop-level onward sales data.
    Shops with a non-standard mobile number (not 10 digits) could not be de-duplicated and may still appear more than once.
</div>

</body></html>
<?php
$html = ob_get_clean();

// Debug escape hatch: ?debug=html returns the raw HTML instead of the PDF,
// so the report layout can be inspected in a browser without dompdf in the way.
if (($_GET['debug'] ?? '') === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

/* ------------------------------------------------------------------ *
 * 9. Render PDF.
 * ------------------------------------------------------------------ */
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
// The legacy DOMDocument parser silently drops most of this document
// (large tables + inline SVG) and emits a 0-page PDF; the HTML5 parser
// handles it correctly.
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'landscape');
$dompdf->loadHtml($html);
$dompdf->render();

$fileName = 'Shop_Purchase_Frequency_' . $todayS . '.pdf';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$dompdf->stream($fileName, ['Attachment' => false]);
