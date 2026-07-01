<?php
// diag.php — put this in the same folder as export_report_xlsx.php

header('Content-Type: text/plain; charset=utf-8');

echo "=== PHP VERSION ===\n";
echo PHP_VERSION . "\n\n";

echo "=== SAPI / OS ===\n";
echo php_sapi_name() . " / " . PHP_OS_FAMILY . "\n\n";

echo "=== Current Script Dir (__DIR__) ===\n";
echo __DIR__ . "\n\n";

echo "=== Candidate vendor/autoload.php paths (relative to this file) ===\n";
$paths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php', // sometimes useful
];
foreach ($paths as $p) {
    echo (file_exists($p) ? "[FOUND]   " : "[missing] ") . $p . "\n";
}

echo "\n=== Composer presence near document root ===\n";
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if ($docRoot) {
    foreach (['/composer.json','/composer.lock','/vendor/autoload.php'] as $suf) {
        $p = $docRoot . $suf;
        echo (file_exists($p) ? "[FOUND]   " : "[missing] ") . $p . "\n";
    }
}

echo "\n=== Try to load PhpSpreadsheet ===\n";
foreach ($paths as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
    echo "PhpSpreadsheet class is LOADABLE ✅\n";
} else {
    echo "PhpSpreadsheet NOT found ❌ (install via Composer or fix autoload path)\n";
}

echo "\n=== Error log candidate ===\n";
$ini = ini_get('error_log');
echo $ini ? $ini : "(no explicit error_log in php.ini; check domain error_log in this directory)\n";
