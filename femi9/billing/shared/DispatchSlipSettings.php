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
    $pooledQty    = 0; // each line's leftover (or, if no packs_per_cover set, its whole qty) — pooled for the overall grouping
    $lineBoxes123 = []; // Stage-1 boxes, each single-product, in line order
    $poolContrib  = []; // ordered list of {product, packs} chunks feeding Stage 2
    foreach ($po_items as &$item) {
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

        // Per-line Box column — two stages, same shape as the overall
        // shipment-level estimate below, just scoped to this one line:
        //   1. Group this line's qty into full boxes of its OWN
        //      packs_per_carton (the same number the Cartons column already
        //      uses) — e.g. 130 at 100/box is 1 box, 30 left over. Only
        //      needs packs_per_carton — a line missing that entirely has no
        //      carton size of its own to group by, so its whole qty instead
        //      flows into the pooled overall grouping below.
        //   2. IF the product also has packs_per_cover set, that leftover is
        //      then checked against it — exceed it and the leftover becomes
        //      one more box outright. Without a cover value there's no
        //      threshold to compare against, so the leftover just stays as
        //      loose packs feeding the pool (a small amount, at most one
        //      carton's worth — never the line's whole quantity).
        //
        // Two very differently-scaled product lines (e.g. Napkin cartons of
        // ~50-100 vs Diaper cartons of ~6-8) both get correct box counts
        // this way; only ever their own small leftover — never their whole
        // quantity — ends up sharing the overall pool's single napkin-scaled
        // box size, which is what made Diaper lines come out wrong before
        // this fix (their full qty was being divided by a Napkin-sized
        // overall_packs_per_box instead of their own much smaller carton).
        $ppcv = $item['packs_per_cover'];
        $item['box_display'] = '—';
        if ($ppc !== null && $ppc !== '' && (int)$ppc > 0) {
            $ppc_int      = (int)$ppc;
            $lineBoxes    = intdiv($qty, $ppc_int);
            $lineLeftover = $qty % $ppc_int;
            if ($ppcv !== null && $ppcv !== '' && (int)$ppcv > 0 && $lineLeftover > (int)$ppcv) {
                $lineBoxes++;
                $lineLeftover = 0;
            }
            $lineBoxesSum += $lineBoxes;
            $pooledQty    += $lineLeftover;
            for ($b = 0; $b < $lineBoxes; $b++) {
                $lineBoxes123[] = ['contents' => [['product' => $productName, 'packs' => $ppc_int]]];
            }
            if ($lineLeftover > 0) {
                $poolContrib[] = ['product' => $productName, 'packs' => $lineLeftover];
            }
            // A leftover that made it past the exceed-check above (didn't
            // exceed packs_per_cover) fits inside exactly one physical
            // cover, so it's shown as "1 cover" rather than a loose pack
            // count — only when this line actually HAS a cover threshold to
            // have been checked against; without one (no packs_per_cover),
            // the leftover is still undecided and just flows to the pool,
            // so it stays shown as raw packs.
            $hasCoverThreshold = $ppcv !== null && $ppcv !== '' && (int)$ppcv > 0;
            $item['box_display'] = ($lineBoxes > 0 ? $lineBoxes . ' box' . ($lineBoxes > 1 ? 'es' : '') : '')
                . ($lineLeftover > 0
                    ? ($lineBoxes > 0 ? ' + ' : '') . ($hasCoverThreshold ? '1 cover' : $lineLeftover . ' pack' . ($lineLeftover > 1 ? 's' : ''))
                    : '');
            if ($item['box_display'] === '') $item['box_display'] = '—';
        } else {
            $pooledQty += $qty;
            if ($qty > 0) {
                $poolContrib[] = ['product' => $productName, 'packs' => $qty];
            }
        }
    }
    unset($item);

    // Shipment-level box estimate: every line's own exact box count above,
    // plus two more stages applied to the pooled leftover:
    //   1. Group the pool into full boxes of overall_packs_per_box (floor
    //      division) — e.g. 80 pooled packs at 50/box is 1 box, 30 left over.
    //   2. Whatever's STILL left over after that grouping is checked once more
    //      against the smaller overall_packs_per_cover threshold — if it
    //      EXCEEDS that, the remainder becomes one more box outright;
    //      otherwise it just stays displayed as loose packs.
    //
    // The pooled boxes below are built by walking $poolContrib in the same
    // order and bucket-filling to overall_packs_per_box — this reproduces
    // $groupedBoxes/$afterGrouping exactly, just with product names attached.
    $overallBox = $overallSettings['box'];
    $pooledBoxes123 = [];
    $currentBucket = [];
    $currentSum = 0;
    foreach ($poolContrib as $contrib) {
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
                $currentBucket = [];
                $currentSum = 0;
            }
        }
    }
    $groupedBoxes  = count($pooledBoxes123);
    $afterGrouping = $currentSum; // whatever never filled a full bucket — matches $pooledQty % $overallBox
    $leftoverIsBox = $afterGrouping > $overallSettings['cover'];
    // Whatever's left — whether it grew into one more box above, or stayed
    // a "cover" below the threshold — is still a real physical package that
    // needs its own shipping label, so it's always appended to the box list
    // (just not counted in $TotalBoxes when it's only a cover, matching the
    // "N boxes + 1 cover" display below).
    if ($afterGrouping > 0) {
        $pooledBoxes123[] = ['contents' => $currentBucket];
    }
    $TotalBoxes = $lineBoxesSum + $groupedBoxes + ($leftoverIsBox ? 1 : 0);
    // Same "fits in one physical cover" rule as the per-line display above —
    // once pooled leftover has cleared the exceed-check (didn't exceed
    // overall_packs_per_cover), it's exactly 1 cover, not loose packs.
    $TotalBoxesDisplay = $TotalBoxes . ' box' . ($TotalBoxes !== 1 ? 'es' : '')
        . (!$leftoverIsBox && $afterGrouping > 0 ? ' + 1 cover' : '');

    return [
        'TotalQty'          => $TotalQty,
        'TotalCartons'      => $TotalCartons,
        'TotalBoxes'        => $TotalBoxes,
        'TotalBoxesDisplay' => $TotalBoxesDisplay,
        'poolQty'           => $pooledQty,
        'afterGrouping'     => $afterGrouping,
        'boxes'             => array_merge($lineBoxes123, $pooledBoxes123),
    ];
}
