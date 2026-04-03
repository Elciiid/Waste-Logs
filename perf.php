<?php
$start_total = microtime(true);
require_once __DIR__ . '/connection/database.php';
$after_db = microtime(true);

header('Content-Type: text/plain');

echo "--- Performance Audit ---\n";
echo "DB Connection + Session Init: " . round(($after_db - $start_total) * 1000, 2) . "ms\n";

$start_q = microtime(true);
$conn->query("SELECT 1");
$after_q = microtime(true);
echo "Simple Query (SELECT 1): " . round(($after_q - $start_q) * 1000, 2) . "ms\n";

$start_meta = microtime(true);
$conn->query("SELECT COUNT(*) FROM app_users");
$after_meta = microtime(true);
echo "Table Query (app_users): " . round(($after_meta - $start_meta) * 1000, 2) . "ms\n";

echo "\nTotal execution: " . round((microtime(true) - $start_total) * 1000, 2) . "ms\n";
echo "PHP Version: " . phpversion() . "\n";
?>
