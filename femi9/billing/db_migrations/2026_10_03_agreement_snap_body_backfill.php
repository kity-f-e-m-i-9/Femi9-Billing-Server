<?php
/**
 * One-time backfill for channel_partner_agreements.snap_body_html and
 * territory_partner_agreements.snap_body_html.
 *
 * Context: the wording-change-highlight feature (diff_highlight_agreement_body()
 * in shared/AgreementService.php) compares the currently effective clause
 * wording against snap_body_html — a snapshot of the wording as it stood at
 * the partner's LAST signature, written by agreement-action.php from that
 * point on. Any partner who signed BEFORE this column/logic existed has
 * snap_body_html = NULL, so there is no baseline to diff against and no
 * highlight can ever show for them — even after the Company edits the
 * wording — until they sign again from scratch.
 *
 * This backfill gives every already-signed partner a starting baseline
 * right now (today's current effective wording), so the very next wording
 * edit the Company makes is correctly highlighted for them, without
 * forcing an immediate unnecessary re-sign.
 *
 * Safe to re-run: only touches rows where signed_at IS NOT NULL AND
 * snap_body_html IS NULL, so a partner who has since re-signed (and so
 * already has a real snapshot) is never touched.
 *
 * Usage: php 2026_10_03_agreement_snap_body_backfill.php [--apply]
 *   Without --apply: dry run, prints what it WOULD change, writes nothing.
 *   With --apply: performs the UPDATEs.
 */

require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/../shared/AgreementService.php';

$servername = $_ENV['DB_HOST']     ?? 'localhost';
$db_port    = (int)($_ENV['DB_PORT'] ?? 3306);
$username   = $_ENV['DB_USERNAME'] ?? 'billing0femi9_femi9admin';
$password   = $_ENV['DB_PASSWORD'] ?? 'mavNip-xukvyk-9veqra';
$dbname     = $_ENV['DB_NAME']     ?? 'billing0femi9_billingapp';

$db_conn = mysqli_connect($servername, $username, $password, $dbname, $db_port);
if (!$db_conn) {
    die("Connection failed: " . mysqli_connect_error() . PHP_EOL);
}

ensure_agreement_tables($db_conn);

$apply = in_array('--apply', $argv, true);
echo $apply ? "Running in APPLY mode.\n" : "Dry run (pass --apply to write changes).\n";

function backfill(mysqli $db_conn, string $table, string $idCol, string $type, bool $apply): void
{
    $res = $db_conn->query("SELECT * FROM {$table} WHERE signed_at IS NOT NULL AND snap_body_html IS NULL");
    $count = 0;
    while ($row = $res->fetch_assoc()) {
        $id = (int) $row[$idCol];
        $effectiveBody = get_effective_agreement_body($db_conn, $row, $type);
        echo "  {$table}.{$idCol}={$id}: snapshot length " . strlen($effectiveBody) . "\n";
        if ($apply) {
            $stmt = $db_conn->prepare("UPDATE {$table} SET snap_body_html = ? WHERE {$idCol} = ?");
            $stmt->bind_param('si', $effectiveBody, $id);
            $stmt->execute();
            $stmt->close();
        }
        $count++;
    }
    echo "{$table}: " . ($apply ? "updated" : "would update") . " {$count} row(s).\n";
}

echo "Channel Partner agreements:\n";
backfill($db_conn, 'channel_partner_agreements', 'channel_partner_id', 'channel_partner', $apply);

echo "Territory Partner agreements:\n";
backfill($db_conn, 'territory_partner_agreements', 'territory_partner_id', 'territory_partner', $apply);

echo "Done.\n";
