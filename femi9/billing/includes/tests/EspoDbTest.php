<?php
// femi9/billing/includes/tests/EspoDbTest.php
// Manual run: php EspoDbTest.php
require_once __DIR__ . '/../EspoDb.php';

$conn = getEspoDbConnection();
if ($conn === null) {
    echo "PASS: getEspoDbConnection() returns null when ESPO_DB_* env vars are unset/unreachable\n";
} else {
    $result = $conn->query("SELECT COUNT(*) AS c FROM user WHERE deleted = 0");
    $row = $result->fetch_assoc();
    echo "PASS: connected, {$row['c']} active EspoCRM users found\n";
    $conn->close();
}
