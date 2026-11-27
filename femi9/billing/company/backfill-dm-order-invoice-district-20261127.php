<?php
// One-time backfill script — 2026-11-27.
//
// Companion fix to territory-partner/order-to-invoice-action.php (that file
// now freezes invoice_district_id on every NEW DM-Order→Invoice conversion
// going forward). Every DM-order invoice created BEFORE that fix (the whole
// flow has existed since 2026-11-25) still has invoice_district_id=NULL —
// confirmed 15,780 such rows system-wide. Until fixed, each one's district
// attribution falls back LIVE to shop.district_id, which for a multi-
// district TP can be wrong/stale (the exact bug reported: Suganya Manirajan
// TP-0088, 11 DM-order conversions on 2026-11-25, only 5 showing on her
// DM's own dashboard — those 5 happened to have a shop.district_id
// matching the DM's own district; the other 6 didn't).
//
// This script freezes invoice_district_id = shop.district_id (the shop's
// CURRENT value, same signal the live fallback already uses) for every
// affected invoice, one time — same caveat as every other TP-attribution
// fix this project has done: this does NOT fix shop.district_id staleness
// itself, it only stops that staleness from being re-read every single
// page load. A shop with district_id=0/NULL is left untouched (nothing
// reliable to backfill from) and reported separately for manual review.
//
// Dry-run by default (?apply=1 to actually write). Browser only (needs a
// real logged-in company session) — rewritten 2026-11-27 to reuse the
// normal company checksession.php/config.php $db_conn instead of the
// standalone shared/env-loader.php path, after that path's expected
// `shared/.env` turned out not to resolve the same way on live as it does
// locally (".env file not found" on live, env-loader.php itself loaded
// fine — only the file lookup under it failed). Every other company page
// already connects this same way, so this removes the live-environment
// guesswork entirely.
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../marketing/include/TpInvoiceAmount.php';
header('Content-Type: text/plain');

$apply = (($_GET['apply'] ?? $argv[1] ?? '') === '1');

$res = mysqli_query($db_conn, "
    SELECT ui.inv_id, ui.inv_number, ui.from_user_id AS tp_id, ui.to_user_id AS shop_temp_id,
           s.name AS shop_name, s.district_id,
           tp.tp_id AS tp_code, CONVERT(tp.name USING utf8mb4) COLLATE utf8mb4_general_ci AS tp_name
    FROM user_invoice ui
    JOIN tp_orders o ON o.invoiced_inv_id = ui.inv_id
    JOIN shop s ON s.temp_id = ui.to_user_id
    LEFT JOIN territory_partners tp ON tp.id = ui.from_user_id
    WHERE ui.invoice_district_id IS NULL
    GROUP BY ui.inv_id
");

// Single-district-TP fallback — 2026-11-27 v2, found via the live run's own
// "Skipped" list: a shop with NO district_id at all (district_id=0) still
// has an unambiguous answer when its OWNING TP is assigned to exactly ONE
// district (no other district that invoice could possibly belong to) —
// same priority rule TpInvoiceAmount.php's own live fallback already uses
// for exactly this reason. Only a MULTI-district TP's district-less shop
// stays genuinely unresolvable (no reliable signal at all) and is reported
// for manual review.
$singleDistrictTpMap = getSingleDistrictTpMap($db_conn);

$fixed = 0; $skippedNoDistrict = 0; $total = 0; $skippedRows = []; $fixedViaTpFallback = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $total++;
    $districtId = (int)$row['district_id'];
    if ($districtId <= 0) {
        $tpFallbackDistrict = $singleDistrictTpMap[(int)$row['tp_id']] ?? 0;
        if ($tpFallbackDistrict > 0) {
            $districtId = $tpFallbackDistrict;
            $fixedViaTpFallback++;
        } else {
            $skippedNoDistrict++;
            $skippedRows[] = $row;
            continue;
        }
    }
    if ($apply) {
        $stmt = $db_conn->prepare("UPDATE user_invoice SET invoice_district_id = ? WHERE inv_id = ?");
        $stmt->bind_param('is', $districtId, $row['inv_id']);
        $stmt->execute();
        $stmt->close();
    }
    $fixed++;
}

echo "Total DM-order invoices with NULL invoice_district_id: $total\n";
echo ($apply ? "Fixed (invoice_district_id set): " : "Would fix: ") . "$fixed\n";
echo "  ...of which via single-district-TP fallback (shop itself had no district_id): $fixedViaTpFallback\n";
echo "Skipped (multi-district TP, shop has no usable district_id, needs manual review): $skippedNoDistrict\n";
if (!$apply) {
    echo "\nDry run only — nothing written. Re-run with ?apply=1 (browser) or pass 1 as the first CLI argument to actually apply.\n";
}
if ($skippedNoDistrict > 0) {
    echo "\n--- Skipped invoices (shop has no district_id, needs manual review) ---\n";
    foreach ($skippedRows as $r) {
        echo "inv_id={$r['inv_id']} inv_number={$r['inv_number']} TP={$r['tp_code']} ({$r['tp_name']}) shop_temp_id={$r['shop_temp_id']} shop_name=\"{$r['shop_name']}\"\n";
    }
}
