<?php
// READ-ONLY diagnostic — upload to company/, open once in a logged-in
// company browser tab, paste the output back, then DELETE this file.
// Does not write anything to the database. Checks whether Ranjith
// Kanagaraj (CP-0002, internal id 2)'s November 2026 CP Commission has
// already been credited, and what his current sales/deposit basis is.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
header('Content-Type: text/plain');

$cpId = '2'; // Ranjith Kanagaraj, CP-0002
$from = '2026-11-01';
$to   = '2026-11-30';

echo "=== channel_partners row ===\n";
print_r($db_conn->query("SELECT id, name, cp_id FROM channel_partners WHERE id = '$cpId'")->fetch_assoc());

echo "\n=== Already credited for Nov 2026? (wallet_monthly_sls_report, CP Commission) ===\n";
$stmt = $db_conn->prepare("SELECT * FROM wallet_monthly_sls_report
    WHERE user_type = 'channel_partner' AND user_id = ?
      AND from_date = ? AND to_date = ? AND commission_type = 'CP Commission'");
$stmt->bind_param("sss", $cpId, $from, $to);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($existing) {
    echo "ALREADY CREDITED — row found:\n";
    print_r($existing);
} else {
    echo "NOT credited yet for this exact month range (no row found) — a future run would compute it fresh.\n";
}

echo "\n=== Current sales figure (tp_invoices, source_cp_id = $cpId, Nov 2026) ===\n";
$stmt = $db_conn->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total FROM tp_invoices
    WHERE source_cp_id = ? AND invoice_date BETWEEN ? AND ?");
$stmt->bind_param("sss", $cpId, $from, $to);
$stmt->execute();
$sales = $stmt->get_result()->fetch_assoc();
$stmt->close();
print_r($sales);
echo "(Note: this will NOT yet include invoice TP/26-27/1701's ₹7,500 unless the CP-source fix has already been applied with ?confirm=yes)\n";

echo "\n=== Returns against those invoices (Nov 2026) ===\n";
$stmt = $db_conn->prepare("SELECT COALESCE(SUM(urs.total),0) AS total
    FROM user_return_stock urs
    JOIN tp_invoices tpi ON tpi.invoice_number = urs.invnumber COLLATE utf8mb4_unicode_ci
    WHERE tpi.source_cp_id = ?
      AND urs.from_usertype = 'territory_partner'
      AND urs.date BETWEEN ? AND ?
      AND urs.status = 'accept'
      AND urs.deleted_at IS NULL");
$stmt->bind_param("sss", $cpId, $from, $to);
$stmt->execute();
print_r($stmt->get_result()->fetch_assoc());
$stmt->close();

echo "\n=== Total deposit across CP's assigned locations ===\n";
$stmt = $db_conn->prepare("SELECT COALESCE(SUM(n.deposit_amount),0) AS total
    FROM channel_partner_locations cpl
    JOIN partner_location_nodes n ON n.id = cpl.location_id
    WHERE cpl.channel_partner_id = ?");
$stmt->bind_param("s", $cpId);
$stmt->execute();
$deposit = $stmt->get_result()->fetch_assoc();
$stmt->close();
print_r($deposit);

$grossSales = (float)$sales['total'];
$netSales = $grossSales; // returns subtracted in real calc; approximated here for the headline numbers
$depositTotal = (float)$deposit['total'];
$fromSales = round($netSales * 0.06, 2);
$fromDeposit = round($depositTotal * 0.02, 2);
echo "\n=== Rough basis comparison (ignoring returns here — see 'Returns' section above for the exact deduction) ===\n";
echo "6% of current sales (₹$grossSales): ₹$fromSales\n";
echo "2% of total deposit (₹$depositTotal): ₹$fromDeposit\n";
echo "Current winning basis: " . ($fromSales >= $fromDeposit ? "Sales-based (6%)" : "Deposit-floor (2%)") . "\n";
echo "If sales-based wins, adding ₹7,500 more sales changes commission by: ₹" . round(7500 * 0.06, 2) . "\n";

echo "\nDONE.\n";
