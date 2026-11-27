<?php
// One-time manual fix — 2026-11-27.
//
// 7 of TP-0088 (Suganya Manirajan)'s shops had NO district_id at all
// (confirmed via backfill-dm-order-invoice-district-20261127.php's own
// "Skipped" list — she's a multi-district TP, Namakkal+Trichy, so the
// script's automatic fallback correctly refused to guess). User confirmed
// these 7 belong to TRICHY — their names (Thotiyam/Thottiyam/kattuputhur/
// vayaloor road) match her own "Thottiyam" Firka (Musiri Division, TRICHY
// — confirmed via territory_partner_locations), same town-name pattern as
// her OTHER already-correctly-districted shops.
//
// An 8th shop in that same skipped list, "Abinash medical elurpatti", was
// deliberately left OUT of this fix — its name doesn't match any of
// Suganya's known Firkas (6 Namakkal ones + this one Trichy one), so there
// is no reliable signal for it; it stays for manual review separately.
//
// This script: (1) sets shop.district_id = TRICHY for these 7 shops
// (so any future invoice for them also resolves correctly, not just the
// ones already flagged), and (2) sets invoice_district_id directly on
// every currently-NULL invoice already raised against them.
include("checksession.php");
include("config.php");
header('Content-Type: text/plain');

$tpId = 88; // Suganya Manirajan, TP-0088
$trichyDistrictId = 33;
$shopTempIds = [
    '85591FSHP241026125508', // Dhanushya super market Thotiyam
    '22343FSHP241026120512', // Prabhu hyper market Thotiyam
    '73497FSHP241026115625', // Meenakshi medical Thottiyam
    '19394FSHP251126124358', // Bangaru hospital kattuputhur
    '92726FSHP251126115439', // Karuppan super market Thottiyam
    '61859FSHP251126122133', // Vss department store vayaloor road
    '91128FSHP251126125137', // Magizh super market 2 kattuputhur
];

$apply = (($_GET['apply'] ?? '') === '1');
$idList = "'" . implode("','", array_map(fn($v) => $db_conn->real_escape_string($v), $shopTempIds)) . "'";

$shopsRes = $db_conn->query("SELECT temp_id, name, district_id FROM shop WHERE temp_id IN ($idList) AND onboard_userID='$tpId' AND onboard_userTYPE='territory_partner'");
$shops = [];
while ($r = $shopsRes->fetch_assoc()) { $shops[] = $r; }

$invRes = $db_conn->query("SELECT inv_id, inv_number, to_user_id FROM user_invoice WHERE from_user_id=$tpId AND to_user_id IN ($idList) AND invoice_district_id IS NULL");
$invoices = [];
while ($r = $invRes->fetch_assoc()) { $invoices[] = $r; }

echo "Shops matched: " . count($shops) . " (expected 7)\n";
foreach ($shops as $s) { echo "  {$s['temp_id']} \"{$s['name']}\" current district_id={$s['district_id']}\n"; }
echo "\nInvoices to fix: " . count($invoices) . "\n";
foreach ($invoices as $i) { echo "  {$i['inv_id']} inv_number={$i['inv_number']}\n"; }

if ($apply) {
    $db_conn->query("UPDATE shop SET district_id=$trichyDistrictId WHERE temp_id IN ($idList) AND onboard_userID='$tpId' AND onboard_userTYPE='territory_partner'");
    $db_conn->query("UPDATE user_invoice SET invoice_district_id=$trichyDistrictId WHERE from_user_id=$tpId AND to_user_id IN ($idList) AND invoice_district_id IS NULL");
    echo "\nAPPLIED — shop.district_id and invoice_district_id both set to TRICHY ($trichyDistrictId) for the above.\n";
} else {
    echo "\nDry run only — nothing written. Re-run with ?apply=1 to actually apply.\n";
}
