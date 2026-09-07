<?php
/**
 * DispatchSlipSettings — the two global numbers driving the dispatch
 * slip's shipment-level Total Boxes estimate (see
 * db_migrations/2026_08_21_dispatch_slip_settings.sql). Separate from
 * products.packs_per_cover, which is a per-product, per-line threshold.
 *
 * Applied in two stages to whatever packs are left after per-line BOX
 * flags are already accounted for (see company/dispatch-slip-print.php):
 *   1. overall_packs_per_box  — group that pool into full boxes (floor
 *      division) — e.g. 80 packs at 50/box = 1 box, 30 packs left over.
 *   2. overall_packs_per_cover — check what's STILL left over after step 1
 *      against this second, smaller threshold — if it exceeds this, that
 *      remainder becomes one more box outright (same "exceed it -> box"
 *      rule as the per-line flag); otherwise it just stays shown as
 *      loose packs.
 */

/** Least common multiple — used to convert several different carton sizes into one shared "box size" for proportional pooling. */
function dispatchSlipLcm(int $a, int $b): int
{
    if ($a <= 0 || $b <= 0) return max($a, $b, 1);
    $x = $a; $y = $b;
    while ($y !== 0) { [$x, $y] = [$y, $x % $y]; }
    $gcd = $x !== 0 ? $x : 1;
    return intdiv($a, $gcd) * $b;
}

function dispatchSlipEnsureSettingsTable(mysqli $db): void
{
    $db->query("
        CREATE TABLE IF NOT EXISTS dispatch_slip_settings (
          id INT UNSIGNED NOT NULL PRIMARY KEY,
          overall_packs_per_box INT UNSIGNED NOT NULL DEFAULT 50,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $db->query("INSERT IGNORE INTO dispatch_slip_settings (id, overall_packs_per_box) VALUES (1, 50)");

    // Self-migrating: see db_migrations/2026_08_21_dispatch_slip_settings_cover.sql
    $col = $db->query("SHOW COLUMNS FROM dispatch_slip_settings LIKE 'overall_packs_per_cover'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE dispatch_slip_settings ADD COLUMN overall_packs_per_cover INT UNSIGNED NOT NULL DEFAULT 21 AFTER overall_packs_per_box");
    }
}

/** @return array{box:int,cover:int} Both always >= 1 (never lets a bad/zero value cause division by zero). */
function dispatchSlipGetOverallSettings(mysqli $db): array
{
    dispatchSlipEnsureSettingsTable($db);
    $row = $db->query("SELECT overall_packs_per_box, overall_packs_per_cover FROM dispatch_slip_settings WHERE id = 1")->fetch_assoc();
    $box   = (int)($row['overall_packs_per_box'] ?? 50);
    $cover = (int)($row['overall_packs_per_cover'] ?? 21);
    return [
        'box'   => $box > 0 ? $box : 50,
        'cover' => $cover > 0 ? $cover : 21,
    ];
}

/**
 * The full Box calculation shared by company/dispatch-slip-print.php,
 * salesbdm/dispatch-slip-print.php, and the shipping-label pages (which need
 * the same TotalBoxes number to know how many label rows to start with, and
 * the 'boxes' breakdown to know what to pre-fill each one with).
 *
 * Mutates each $po_items row in place, adding 'carton_display' and
 * 'box_display' (same as before this was extracted into a function), and
 * returns the shipment-level totals plus a per-box product breakdown.
 *
 * Box-filling stays proportional (2026-09-03 note): two different products
 * that each have their own carton size still correctly share ONE physical
 * box based on that real capacity — e.g. two products both boxed 100/carton,
 * each with 50 leftover packs, fill exactly one shared box together (50+50
 * = 100 = one full carton's worth), not two. Only the FINAL leftover-vs-cover
 * decision (Stage 3) changed: it now compares the raw pack count still
 * sitting in that last incomplete bucket against overall_packs_per_cover
 * directly (e.g. 35 raw leftover packs > 21 → a box), instead of the
 * proportional comparison this used before.
 *   1. Each line's own qty divides by its own packs_per_carton into exact
 *      full boxes (Stage 1); any remainder is leftover.
 *   2. Every leftover chunk that HAS a known carton size pools together
 *      (even across different sizes) into shared boxes, sized by each
 *      product's own real carton capacity (LCM-normalized internally so
 *      there's no floating-point rounding) — this is what lets two
 *      half-full different products correctly combine into one box.
 *      Carton-less leftover pools separately via overall_packs_per_box.
 *   3. Whatever's left incomplete after that is compared as a RAW pack
 *      count against overall_packs_per_cover — more than that and it's one
 *      more box outright, otherwise it's exactly one cover.
 *
 * The per-box breakdown is reconstructed from the same walk that produced
 * TotalBoxes — a contribution that doesn't fit the current bucket splits
 * across the bucket boundary, so one product can appear in more than one box.
 *
 * @param array $po_items Each row needs 'qty', 'packs_per_carton', 'productName'.
 * @return array{TotalQty:int,TotalCartons:int,TotalBoxes:int,TotalCovers:int,TotalBoxesDisplay:string,poolQty:int,afterGrouping:int,boxes:array}
 *   'boxes' is a list of $TotalBoxes+$TotalCovers entries, each array{contents: list<array{product:string,packs:int}>}.
 */
function dispatchSlipComputeBoxes(mysqli $db, array &$po_items): array
{
    $overallSettings = dispatchSlipGetOverallSettings($db);
    $overallBox   = $overallSettings['box'];
    $overallCover = $overallSettings['cover'];

    $TotalQty     = 0;
    $TotalCartons = 0;
    $lineBoxesSum = 0; // sum of each line's own exact box count below
    $pooledQty    = 0; // total leftover packs pooled into Stage 2, across every line
    $lineBoxes123 = []; // Stage-1 boxes, each single-product, in line order
    $poolContrib  = []; // ordered list of {product, packs, carton?, idx} chunks feeding Stage 2
    foreach ($po_items as $idx => &$item) {
        $qty = (int)$item['qty'];
        $TotalQty += $qty;
        $productName = $item['productName'] ?? '';

        $ppc = $item['packs_per_carton'];
        $item['carton_display'] = '—';
        $item['box_display'] = '';
        if ($ppc !== null && $ppc !== '' && (int)$ppc > 0) {
            $ppc_int  = (int)$ppc;
            $cartons  = intdiv($qty, $ppc_int);
            $leftover = $qty % $ppc_int;
            $TotalCartons += $cartons;
            $item['carton_display'] = $cartons . ' ctn' . ($leftover > 0 ? ' + ' . $leftover . ' pack' . ($leftover > 1 ? 's' : '') : '');

            $lineBoxesSum += $cartons;
            for ($b = 0; $b < $cartons; $b++) {
                $lineBoxes123[] = ['contents' => [['product' => $productName, 'packs' => $ppc_int]]];
            }
            $item['box_display'] = $cartons > 0 ? $cartons . ' box' . ($cartons > 1 ? 'es' : '') : '';
            if ($leftover > 0) {
                $pooledQty += $leftover;
                // 'carton' tags this chunk with the product's OWN carton
                // size, so Stage 2 tries grouping it with other products
                // that share physical box room proportionally.
                $poolContrib[] = ['product' => $productName, 'packs' => $leftover, 'carton' => $ppc_int, 'idx' => $idx];
            }
        } else {
            $pooledQty += $qty;
            if ($qty > 0) {
                $poolContrib[] = ['product' => $productName, 'packs' => $qty, 'idx' => $idx];
            }
        }
    }
    unset($item);

    $pooledBoxes123 = [];
    $fullBoxCount   = 0;
    $idxOutcomes    = []; // idx => ordered list of 'box'/'cover' tokens, for the final box_display pass below

    // Stage 2a — pool everything with a known carton size together, sharing
    // box room proportionally (order-preserving: original line order). Every
    // carton is the SAME physical box; a product's own carton-size number
    // just says how many of ITS packs fill one (a bigger pack needs fewer to
    // fill the same box). Tracked as a FRACTION of one box — 1 pack of a
    // 90/carton product uses 1/90 of a box, 1 pack of a 100/carton product
    // uses 1/100 — via exact integer arithmetic (everything converted to a
    // common "box size", the LCM of every carton size involved, so there's
    // no floating-point rounding).
    $cartonedContrib   = []; // ordered list of {product, packs, carton, idx}
    $cartonlessContrib = []; // chunks with no known carton size of their own
    foreach ($poolContrib as $contrib) {
        if (isset($contrib['carton']) && $contrib['carton'] > 0) {
            $cartonedContrib[] = $contrib;
        } else {
            $cartonlessContrib[] = $contrib;
        }
    }
    // One entry per incomplete bucket: {contents, rawPacks} — 'rawPacks' is
    // the actual leftover pack count (NOT LCM units), used by Stage 3 below.
    $leftoverUnits = [];
    if (!empty($cartonedContrib)) {
        $L = 1;
        foreach (array_unique(array_column($cartonedContrib, 'carton')) as $c) {
            $L = dispatchSlipLcm($L, (int)$c);
        }
        $bucket = [];
        $sumUnits = 0;
        $rawPacks = 0;
        foreach ($cartonedContrib as $contrib) {
            $unitPerPack = intdiv($L, $contrib['carton']); // exact — $L is a multiple of every carton size involved
            $remaining = $contrib['packs'];
            while ($remaining > 0) {
                $spaceUnits   = $L - $sumUnits;
                $maxPacksFit  = intdiv($spaceUnits, $unitPerPack); // whole packs of THIS product that still fit
                $take = min($maxPacksFit, $remaining);
                if ($take > 0) {
                    $bucket[] = ['product' => $contrib['product'], 'packs' => $take, 'idx' => $contrib['idx']];
                    $sumUnits += $take * $unitPerPack;
                    $rawPacks += $take;
                    $remaining -= $take;
                }
                // Close the box once it's full, OR once nothing more fits
                // (its own pack size doesn't divide evenly into whatever
                // sliver of room is left — that sliver simply goes unused,
                // same as a real box that can't fit one more whole pack).
                if ($sumUnits >= $L || $take === 0) {
                    $pooledBoxes123[] = ['contents' => $bucket];
                    $fullBoxCount++;
                    foreach (array_unique(array_column($bucket, 'idx')) as $i) { $idxOutcomes[$i][] = 'box'; }
                    $bucket = [];
                    $sumUnits = 0;
                    $rawPacks = 0;
                }
            }
        }
        if ($sumUnits > 0) {
            $leftoverUnits[] = ['contents' => $bucket, 'rawPacks' => $rawPacks];
        }
    }

    // Stage 2b — generic overall_packs_per_box pooling for carton-less
    // leftover only (this doesn't feed the per-line box_display pass below,
    // since carton-less lines never had a per-line carton size to begin with).
    $currentBucket = [];
    $currentSum = 0;
    foreach ($cartonlessContrib as $contrib) {
        $remaining = $contrib['packs'];
        while ($remaining > 0) {
            $space = $overallBox - $currentSum;
            $take = min($space, $remaining);
            if ($take > 0) {
                $currentBucket[] = ['product' => $contrib['product'], 'packs' => $take];
                $currentSum += $take;
                $remaining -= $take;
            }
            if ($currentSum >= $overallBox) {
                $pooledBoxes123[] = ['contents' => $currentBucket];
                $fullBoxCount++;
                $currentBucket = [];
                $currentSum = 0;
            }
        }
    }
    if ($currentSum > 0) {
        $leftoverUnits[] = ['contents' => $currentBucket, 'rawPacks' => $currentSum];
    }

    // Stage 3 — each leftover unit, independently, compared as a RAW pack
    // count against overall_packs_per_cover: more than that and it's one
    // more box outright; at or under it, it stays exactly one cover. Either
    // way it's a real physical package and gets its own boxes[] entry (and
    // so, eventually, its own shipping label) — a cover is only ever
    // excluded from $TotalBoxes itself, never from the box/label list.
    $coverCount = 0;
    $coverQty   = 0;
    foreach ($leftoverUnits as $unit) {
        $pooledBoxes123[] = ['contents' => $unit['contents']];
        $isBox = $unit['rawPacks'] > $overallCover;
        if ($isBox) {
            $fullBoxCount++;
        } else {
            $coverCount++;
            $coverQty += $unit['rawPacks'];
        }
        foreach (array_unique(array_column($unit['contents'], 'idx')) as $i) {
            if ($i !== null) { $idxOutcomes[$i][] = $isBox ? 'box' : 'cover'; }
        }
    }

    $TotalBoxes = $lineBoxesSum + $fullBoxCount;
    $TotalBoxesDisplay = $TotalBoxes . ' box' . ($TotalBoxes !== 1 ? 'es' : '')
        . ($coverCount > 0 ? ' + ' . $coverCount . ' cover' . ($coverCount > 1 ? 's' : '') : '');

    // Final box_display pass — now that every leftover chunk's fate (boxed,
    // covered, or split across both) is known, append it to whatever each
    // line's own carton math already produced above.
    foreach ($po_items as $idx => &$item) {
        if (!empty($idxOutcomes[$idx])) {
            $boxCount = 0; $coverCount2 = 0;
            foreach ($idxOutcomes[$idx] as $o) { $o === 'box' ? $boxCount++ : $coverCount2++; }
            $parts = [];
            if ($boxCount > 0) $parts[] = $boxCount . ' box' . ($boxCount > 1 ? 'es' : '');
            if ($coverCount2 > 0) $parts[] = $coverCount2 . ' cover' . ($coverCount2 > 1 ? 's' : '');
            $suffix = implode(' + ', $parts);
            $item['box_display'] = ($item['box_display'] !== '' ? $item['box_display'] . ' + ' : '') . $suffix;
        }
        if ($item['box_display'] === '') $item['box_display'] = '—';
    }
    unset($item);

    return [
        'TotalQty'          => $TotalQty,
        'TotalCartons'      => $TotalCartons,
        'TotalBoxes'        => $TotalBoxes,
        'TotalCovers'       => $coverCount,
        'TotalBoxesDisplay' => $TotalBoxesDisplay,
        'poolQty'           => $pooledQty,
        'afterGrouping'     => $coverQty,
        'boxes'             => array_merge($lineBoxes123, $pooledBoxes123),
    ];
}
