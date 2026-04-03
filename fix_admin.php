<?php
require_once __DIR__ . '/connection/database.php';

header('Content-Type: text/plain');

try {
    echo "--- System Fix & Setup ---\n\n";

    // 1. Create Session Table if not exists
    echo "1. Setting up Database Sessions...\n";
    $sql = "CREATE TABLE IF NOT EXISTS app_sessions (
        id VARCHAR(255) PRIMARY KEY,
        data TEXT NOT NULL,
        timestamp INT NOT NULL
    )";
    $conn->exec($sql);
    echo "   [✓] Session table ready.\n\n";

    // 2. Fix Admin Password
    echo "2. Resetting Admin Password to 'password'...\n";
    $newHash = password_hash('password', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE app_users SET password = ? WHERE LOWER(username) = 'admin'");
    $stmt->execute([$newHash]);
    
    if ($stmt->rowCount() > 0) {
        echo "   [✓] Admin password updated successfully.\n";
    } else {
        echo "   [!] Admin user not found. Creating admin user...\n";
        $insert = $conn->prepare("INSERT INTO app_users (username, password, full_name, role) VALUES ('admin', ?, 'System Admin', 'admin')");
        $insert->execute([$newHash]);
        echo "   [✓] Admin user created.\n";
    }

    echo "\n--- SUCCESS ---\n";
    echo "You can now try logging in with 'admin' and 'password'.\n";
    echo "NOTE: I will now implement the code to use these database sessions.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
