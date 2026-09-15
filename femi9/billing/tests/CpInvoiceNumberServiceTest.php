<?php
// femi9/billing/tests/CpInvoiceNumberServiceTest.php
// Run: php femi9/billing/tests/CpInvoiceNumberServiceTest.php
require_once __DIR__ . '/../company/include/db-connect.php'; // provides $db_conn
require_once __DIR__ . '/../shared/CpInvoiceNumberService.php';

function assertTrue($cond, $msg) {
    echo ($cond ? "PASS: " : "FAIL: ") . $msg . "\n";
    if (!$cond) exit(1);
}

// cp_invoices must exist for the MAX() cross-check query not to error.
// Task 2 owns the real schema (shared/CpInvoiceSchema.php); if this harness
// runs before Task 2 lands, it creates a minimal stand-in table itself for
// the duration of this run only — CREATE TABLE implicitly commits, so it
// can't live inside the rolled-back transaction below, and it must not be
// left behind: a narrow stand-in table would make Task 2's own
// "CREATE TABLE IF NOT EXISTS"-style guard skip creating the real columns.
// Dropped again at the very end of this script (only if we created it).
$t = $db_conn->query("SHOW TABLES LIKE 'cp_invoices'");
$created_standin_table = ($t && $t->num_rows === 0);
if ($created_standin_table) {
    $db_conn->query("CREATE TABLE cp_invoices (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, invoice_number VARCHAR(30) UNIQUE) ENGINE=InnoDB");
}

$db_conn->begin_transaction();

$date = date('Y-m-d');
$num1 = cpInvoiceNextNumber($db_conn, 'CO', $date);
assertTrue((bool)preg_match('#^CPDN/\d{2}-\d{2}/\d{3}$#', $num1), "first number matches CPDN/{fy}/{seq} format, got $num1");

// Simulate the number actually being used (insert it), then request the next one.
$db_conn->query("INSERT INTO cp_invoices (invoice_number) VALUES ('$num1')");
$num2 = cpInvoiceNextNumber($db_conn, 'CO', $date);
assertTrue($num1 !== $num2, "second call returns a different number than the first ($num1 vs $num2)");

preg_match('#/(\d+)$#', $num1, $m1);
preg_match('#/(\d+)$#', $num2, $m2);
assertTrue((int)$m2[1] === (int)$m1[1] + 1, "sequence increments by exactly 1 ($m1[1] -> $m2[1])");

$db_conn->rollback(); // never persist test data

if ($created_standin_table) {
    $db_conn->query("DROP TABLE cp_invoices"); // leave no trace for Task 2's real schema guard
}

echo "All CpInvoiceNumberService tests passed.\n";
