<?php
// TEMPORARY one-time fix — on 2026-09-22 two internal-transfer legs
// (tempid GDNMOVE20260922131409332-A and -B) were created with the literal
// placeholder text "PENDING-A" / "PENDING-B" typed into the invoice number
// field instead of a real number, and never corrected. There's no edit UI
// for inv_number after creation, so this assigns them the next real numbers
// in their respective series (G/26-27/192 and S/26-27/199) and shifts every
// invoice number after that point up by one to make room — by row id, so a
// pre-existing duplicate inv_number elsewhere (confirmed: S/26-27/279, two
// rows, unrelated to this fix) can't cause two rows to collide onto the same
// new number.
//
// Default mode is DRY RUN (prints what it would do, touches nothing).
// Visit with ?apply=1 to actually commit the transaction.
// Delete this file after use.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
include("config.php");
error_reporting(0);

header('Content-Type: text/plain; charset=utf-8');

$apply = isset($_GET['apply']) && $_GET['apply'] == '1';

function buildLeg($db_conn, $prefix, $startNum, $tempidPending) {
    $stmt = $db_conn->prepare("SELECT id, CAST(SUBSTRING_INDEX(inv_number,'/',-1) AS UNSIGNED) n
        FROM internal_transfer_invoice WHERE inv_number LIKE CONCAT(?, '/%')
        HAVING n >= ? ORDER BY n DESC, id DESC");
    $likePrefix = $prefix;
    $stmt->bind_param('si', $likePrefix, $startNum);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();

    $updates = [];
    foreach ($rows as $row) {
        $updates[] = ['id' => (int)$row['id'], 'from' => $prefix . '/' . $row['n'], 'to' => $prefix . '/' . ($row['n'] + 1)];
    }
    $updates[] = ['tempid' => $tempidPending, 'from' => 'PENDING', 'to' => $prefix . '/' . $startNum];
    return $updates;
}

echo "=== PENDING invoice number fix ===\n";
echo "Mode: " . ($apply ? "APPLY (will commit)" : "DRY RUN (nothing written — add ?apply=1 to commit)") . "\n\n";

$legA = buildLeg($db_conn, 'G/26-27', 192, 'GDNMOVE20260922131409332-A');
$legB = buildLeg($db_conn, 'S/26-27', 199, 'GDNMOVE20260922131409332-B');

echo "Leg A (NEKSOMO -> FEMI HEALTH CARE, dated 2026-09-22): " . count($legA) . " rows to update\n";
echo "Leg B (FEMI HEALTH CARE -> FEMI NAYAN LLP, dated 2026-09-22): " . count($legB) . " rows to update\n\n";

echo "--- Leg A detail ---\n";
foreach ($legA as $u) echo "  " . ($u['from']) . " -> " . $u['to'] . (isset($u['id']) ? " (id {$u['id']})" : " (tempid {$u['tempid']})") . "\n";
echo "--- Leg B detail ---\n";
foreach ($legB as $u) echo "  " . ($u['from']) . " -> " . $u['to'] . (isset($u['id']) ? " (id {$u['id']})" : " (tempid {$u['tempid']})") . "\n";

if (!$apply) {
    echo "\nDry run only — nothing was changed. Review the list above, then revisit this URL with ?apply=1 to commit.\n";
    exit;
}

$db_conn->begin_transaction();
try {
    foreach ([$legA, $legB] as $leg) {
        foreach ($leg as $u) {
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
    }
    $db_conn->commit();
    echo "\nCOMMITTED. " . (count($legA) + count($legB)) . " rows updated.\n";
} catch (Exception $e) {
    $db_conn->rollback();
    echo "\nROLLED BACK — error: " . $e->getMessage() . "\n";
}

echo "\nDone. Delete this file now.\n";
