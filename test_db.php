<?php
require_once __DIR__ . '/connection/database.php';

header('Content-Type: text/plain');

try {
    echo "--- Database Connection Test ---\n";
    echo "Connection: Success\n\n";

    echo "--- Tables and Row Counts ---\n";
    $tables = ['app_users', 'app_employees', 'wst_users', 'wst_roles'];
    foreach ($tables as $table) {
        try {
            $count = $conn->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "$table: $count rows\n";
        } catch (Exception $e) {
            echo "$table: Error - " . $e->getMessage() . "\n";
        }
    }

    echo "\n--- Admin User Check ---\n";
    $stmt = $conn->prepare("SELECT username, role, full_name FROM app_users WHERE LOWER(username) = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        echo "Admin User: Found\n";
        echo "Role: " . $admin['role'] . "\n";
        echo "Name: " . $admin['full_name'] . "\n";
    } else {
        echo "Admin User: NOT FOUND in app_users table.\n";
    }

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
