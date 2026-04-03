<?php
require_once __DIR__ . '/connection/database.php';

header('Content-Type: text/plain');

$username = 'admin';
$password = 'password';

try {
    echo "--- Auth Debug Info ---\n";
    echo "Testing with username: $username\n";
    echo "Testing with password: $password\n\n";

    $stmt = $conn->prepare("SELECT user_id, username, password FROM app_users WHERE LOWER(username) = LOWER(?)");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "User Found: Yes\n";
        echo "Stored Hash (first 20 chars): " . substr($user['password'], 0, 20) . "...\n";
        
        $verify = password_verify($password, $user['password']);
        echo "password_verify Result: " . ($verify ? "SUCCESS" : "FAILURE") . "\n";
        
        if (!$verify) {
            echo "\nDetailed Hash Comparison:\n";
            echo "Algo: " . password_get_info($user['password'])['algoName'] . "\n";
            // Re-hash to see if it matches the general format
            echo "Format Match: " . (preg_match('/^\$2[ay]\$\d{2}\$/', $user['password']) ? "Yes" : "No") . "\n";
        }
    } else {
        echo "User NOT FOUND.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
