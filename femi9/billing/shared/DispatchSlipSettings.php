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
 * The full two-stage Box calculation shared by company/dispatch-slip-print.php,
 * salesbdm/dispatch-slip-print.php, and the shipping-label pages (which need
 * the same TotalBoxes number to know how many label rows to start with, and
 * the 'boxes' breakdown to know what to pre-fill each one with).
 *
 * Mutates each $po_items row in place, adding 'carton_display' and
 * 'box_display' (same as before this was extracted into a function), and
 * returns the shipment-level totals plus a per-box product breakdown.
 *
 * The per-box breakdown is NOT a guess — it's reconstructed from the same
 * arithmetic that produced TotalBoxes:
 *   - A line's own Stage-1 boxes are single-product by construction (each is
 *     exactly that line's packs_per_carton units of that one product).
 *   - Stage-2's pooled boxes are filled by walking every line's leftover (or
 *     full qty, for a line with no per-line Box math) in order and packing
 *     it into overall_packs_per_box-sized buckets — the same floor-division
 *     the totals already use, just keeping track of which product filled
 *     which bucket instead of only counting packs. A contribution that
 *     doesn't fit the current bucket splits across the bucket boundary, so
 *     one product can appear more than once across adjacent boxes.
 *
 * @param array $po_items Each row needs 'qty', 'packs_per_carton', 'packs_per_cover', 'productName'.
 * @return array{TotalQty:int,TotalCartons:int,TotalBoxes:int,TotalBoxesDisplay:string,poolQty:int,afterGrouping:int,boxes:array}
 *   'boxes' is a list of $TotalBoxes entries, each array{contents: list<array{product:string,packs:int}>}.
 */
function dispatchSlipComputeBoxes(mysqli $db, array &$po_items): array
{
    $overallSettings = dispatchSlipGetOverallSettings($db);

    $TotalQty     = 0;
    $TotalCartons = 0;
    $lineBoxesSum = 0; // sum of each line's own exact box count below
    $pooledQty    = 0; // each line's leftover (its whole qty, if it has no carton size at all) — pooled for the overall grouping
    $lineBoxes123 = []; // Stage-1 boxes, each single-product, in line order
    $poolContrib  = []; // ordered list of {product, packs, carton?, idx} chunks feeding Stage 2
    foreach ($po_items as $idx => &$item) {
        $qty = (int)$item['qty'];
        $TotalQty += $qty;
        $productName = $item['productName'] ?? '';

        $ppc = $item['packs_per_carton'];
        $item['carton_display'] = '—';
        if ($ppc !== null && $ppc !== '' && (int)$ppc > 0) {
            $ppc_int  = (int)$ppc;
            $cartons  = intdiv($qty, $ppc_int);
            $leftover = $qty % $ppc_int;
            $TotalCartons += $cartons;
            $item['carton_display'] = $cartons . ' ctn' . ($leftover > 0 ? ' + ' . $leftover . ' pack' . ($leftover > 1 ? 's' : '') : '');
        }

        // Per-line Box column, stage 1 only: this line's qty grouped into
        // full boxes of its OWN packs_per_carton (the same number the
        // Cartons column already uses) — e.g. 130 at 100/box is 1 box, 30
        // left over. Only needs packs_per_carton — a line missing that
        // entirely has no carton size of its own to group by, so its whole
        // qty instead flows into the pooled overall grouping below.
        //
        // Any leftover here is NOT resolved locally (no more per-line
        // "does it exceed some threshold" check) — it always goes into the
        // shipment-wide pool below FIRST, so it gets a chance to combine
        // with another line that shares the exact same carton size (e.g. a
        // 34-pack leftover from one product filling out the spare room
        // left by a 15-pack leftover from a different product, both
        // 50/carton) before anything decides box vs. cover. Resolving it
        // per-line up front — as this used to do via packs_per_cover — was
        // exactly what silently blocked that kind of combining: a leftover
        // big enough to "win" its own exceed-check became a box on its own
        // immediately, never getting a chance to be topped up by a sibling
        // line's smaller leftover first. box_display below is completed
        // AFTER the pool resolves (see the final pass past Stage 3), once
        // it's known whether this line's leftover ended up boxed, covered,
        // or split across both.
        $item['box_display'] = '';
        if ($ppc !== null && $ppc !== '' && (int)$ppc > 0) {
            $ppc_int      = (int)$ppc;
            $lineBoxes    = intdiv($qty, $ppc_int);
            $lineLeftover = $qty % $ppc_int;
            $lineBoxesSum += $lineBoxes;
            for ($b = 0; $b < $lineBoxes; $b++) {
                $lineBoxes123[] = ['contents' => [['product' => $productName, 'packs' => $ppc_int]]];
            }
            if ($lineLeftover > 0) {
                // 'carton' tags this chunk with the product's OWN carton
                // size, so Stage 2 below tries grouping it with other
                // products that share that exact size before falling back
                // to the generic overall pool. 'idx' traces it back to this
                // line for the final box_display pass.
                $poolContrib[] = ['product' => $productName, 'packs' => $lineLeftover, 'carton' => $ppc_int, 'idx' => $idx];
            }
            $item['box_display'] = $lineBoxes > 0 ? $lineBoxes . ' box' . ($lineBoxes > 1 ? 'es' : '') : '';
        } else {
            $pooledQty += $qty;
            if ($qty > 0) {
                $poolContrib[] = ['product' => $productName, 'packs' => $qty, 'idx' => $idx];
            }
        }
    }
    unset($item);

    // Shipment-level box estimate: every line's own exact box count above,
    // plus, applied to the pooled leftover:
    //   1. FIRST, pool every leftover chunk that HAS a known carton size —
    //      even across DIFFERENT sizes — into shared physical boxes. Every
    //      carton is the SAME physical box; a product's own carton-size
    //      number just says how many of ITS packs fill one (a bigger pack
    //      needs fewer to fill the same box). So "room left in a box" is
    //      tracked as a FRACTION of one box, not a raw pack count — 1 pack
    //      of a 90/carton product uses 1/90 of a box, 1 pack of a
    //      100/carton product uses 1/100 — and different products can share
    //      the leftover room in the same box on that basis (e.g. a
    //      100/carton product's 60 leftover packs use 60% of a box,
    //      leaving 40% free for a 90/carton product's leftover packs to
    //      fill in). Internally this is done with exact integer arithmetic
    //      (everything converted to a common "box size" — the LCM of every
    //      carton size involved — so there's no floating-point rounding).
    //      A leftover with no known carton size (packs_per_carton wasn't
    //      set on that line at all) never enters this stage.
    //   2. Any carton-less leftover pools separately, using the generic
    //      overall_packs_per_box setting (unchanged from before this stage
    //      existed) — that's the only thing overall_packs_per_box is for now.
    //   3. Whatever's left incomplete after (1) or (2) is checked against
    //      overall_packs_per_cover — for (1) this is compared as the SAME
    //      proportion (overall_packs_per_cover / overall_packs_per_box) of
    //      a box, since the leftover itself may be a mix of different
    //      carton sizes with no single raw pack count to compare directly.
    //      Exceed that proportion and the leftover becomes one more box
    //      outright; otherwise it's exactly one cover.
    $pooledBoxes123 = [];
    $fullBoxCount   = 0;
    $idxOutcomes    = []; // idx => ordered list of 'box'/'cover' tokens, for the final box_display pass below

    // Stage 2a — pool everything with a known carton size together, sharing
    // box room proportionally (order-preserving: original line order).
    $cartonedContrib   = []; // ordered list of {product, packs, carton, idx}
    $cartonlessContrib = []; // chunks with no known carton size of their own
    foreach ($poolContrib as $contrib) {
        if (isset($contrib['carton']) && $contrib['carton'] > 0) {
            $cartonedContrib[] = $contrib;
        } else {
            $cartonlessContrib[] = $contrib;
        }
    }
    $leftoverUnits = []; // one entry per incomplete bucket: {contents, sum, ofSize} — 'sum'/'ofSize' are in "one box" units (sum/ofSize = fraction full)
    if (!empty($cartonedContrib)) {
        $L = 1;
        foreach (array_unique(array_column($cartonedContrib, 'carton')) as $c) {
            $L = dispatchSlipLcm($L, (int)$c);
        }
        $bucket = [];
        $sumUnits = 0;
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
                }
            }
        }
        if ($sumUnits > 0) {
            $leftoverUnits[] = ['contents' => $bucket, 'sum' => $sumUnits, 'ofSize' => $L];
        }
    }

    // Stage 2b — generic overall_packs_per_box pooling for carton-less
    // leftover only (this doesn't feed the per-line box_display pass below,
    // since carton-less lines never had a per-line carton size to begin with).
    $overallBox = $overallSettings['box'];
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
        $leftoverUnits[] = ['contents' => $currentBucket, 'sum' => $currentSum, 'ofSize' => $overallBox];
    }

    // Stage 3 — each leftover unit, independently, either exceeds the cover
    // threshold (becomes one more box) or doesn't (stays exactly one cover).
    // Compared as a PROPORTION of a full box (sum/ofSize vs cover/box),
    // not a raw pack count — a Stage 2a unit may be a mix of different
    // carton sizes with no single raw count that means anything on its own.
    // Either way it's a real physical package and gets its own boxes[]
    // entry (and so, eventually, its own shipping label) — a cover is only
    // ever excluded from $TotalBoxes itself, never from the box/label list.
    $coverCount = 0;
    $coverQty   = 0;
    foreach ($leftoverUnits as $unit) {
        $pooledBoxes123[] = ['contents' => $unit['contents']];
        $isBox = ($unit['sum'] * $overallSettings['box']) > ($overallSettings['cover'] * $unit['ofSize']);
        if ($isBox) {
            $fullBoxCount++;
        } else {
            $coverCount++;
            $coverQty += $unit['sum'];
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
        'TotalBoxesDisplay' => $TotalBoxesDisplay,
        'poolQty'           => $pooledQty,
        'afterGrouping'     => $coverQty,
        'boxes'             => array_merge($lineBoxes123, $pooledBoxes123),
    ];
}
