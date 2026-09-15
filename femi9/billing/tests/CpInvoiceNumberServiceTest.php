<?php
// femi9/billing/tests/CpInvoiceNumberServiceTest.php
// Run: php femi9/billing/tests/CpInvoiceNumberServiceTest.php
require_once __DIR__ . '/../company/include/db-connect.php'; // provides $db_conn
require_once __DIR__ . '/../shared/CpInvoiceNumberService.php';

function assertTrue($cond, $msg) {
    echo ($cond ? "PASS: " : "FAIL: ") . $msg . "\n";
    if (!$cond) exit(1);
}

// cp_invoices must exist for the MAX() cross-check query not to error —
// Task 2 creates it; this harness assumes Task 2 has already run, or
// creates a minimal stand-in table if missing. Create outside transaction
// so table persists (fine for subsequent runs), but test data is rolled back.
$t = $db_conn->query("SHOW TABLES LIKE 'cp_invoices'");
if ($t && $t->num_rows === 0) {
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
echo "All CpInvoiceNumberService tests passed.\n";
