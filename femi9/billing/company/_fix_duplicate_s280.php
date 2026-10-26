<?php
// TEMPORARY one-time fix — two internal-transfer legs both ended up with
// inv_number='S/26-27/280' (originally both were 'S/26-27/279' before the
// PENDING-A/B fix shifted everything above 199 up by one; that earlier fix
// correctly preserved this pre-existing duplicate rather than guessing how
// to resolve it, per https://internal convention of not silently altering
// data beyond the requested scope).
//
// The earlier-created transfer (by same-day creation time encoded in its
// tempid) keeps S/26-27/280. The later one is reassigned S/26-27/281, and
// every invoice number from 281 upward in the S/26-27 series shifts up by
// one to make room — same row-id-based approach as the PENDING-A/B fix, so
// it's immune to any other duplicate elsewhere in the series.
//
// Default mode is DRY RUN. Visit with ?apply=1 to actually commit.
// Delete this file after use.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
include("config.php");
error_reporting(0);

header('Content-Type: text/plain; charset=utf-8');

$apply = isset($_GET['apply']) && $_GET['apply'] == '1';

// The later-created of the two S/26-27/280 rows (earlier-created keeps 280).
$laterTempid = 'AUTO20261007131630236-N2';

$stmt = $db_conn->prepare("SELECT id, CAST(SUBSTRING_INDEX(inv_number,'/',-1) AS UNSIGNED) n
    FROM internal_transfer_invoice WHERE inv_number LIKE 'S/26-27/%'
    HAVING n >= 281 ORDER BY n DESC, id DESC");
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) $rows[] = $row;
$stmt->close();

$updates = [];
foreach ($rows as $row) {
    $updates[] = ['id' => (int)$row['id'], 'from' => 'S/26-27/' . $row['n'], 'to' => 'S/26-27/' . ($row['n'] + 1)];
}
$updates[] = ['tempid' => $laterTempid, 'from' => 'S/26-27/280 (duplicate)', 'to' => 'S/26-27/281'];

echo "=== S/26-27/280 duplicate fix ===\n";
echo "Mode: " . ($apply ? "APPLY (will commit)" : "DRY RUN (nothing written — add ?apply=1 to commit)") . "\n\n";
echo count($updates) . " rows to update\n\n";
foreach ($updates as $u) echo "  " . $u['from'] . " -> " . $u['to'] . (isset($u['id']) ? " (id {$u['id']})" : " (tempid {$u['tempid']})") . "\n";

if (!$apply) {
    echo "\nDry run only — nothing was changed. Revisit this URL with ?apply=1 to commit.\n";
    exit;
}

$db_conn->begin_transaction();
try {
    foreach ($updates as $u) {
        if (isset($u['id'])) {
            $s = $db_conn->prepare("UPDATE internal_transfer_invoice SET inv_number=? WHERE id=?");
            $s->bind_param('si', $u['to'], $u['id']);
        } else {
            $s = $db_conn->prepare("UPDATE internal_transfer_invoice SET inv_number=? WHERE tempid=?");
            $s->bind_param('ss', $u['to'], $u['tempid']);
        }
        if (!$s->execute()) throw new Exception($s->error);
        $s->close();
    }
    $db_conn->commit();
    echo "\nCOMMITTED. " . count($updates) . " rows updated.\n";
} catch (Exception $e) {
    $db_conn->rollback();
    echo "\nROLLED BACK — error: " . $e->getMessage() . "\n";
}

echo "\nDone. Delete this file now.\n";
