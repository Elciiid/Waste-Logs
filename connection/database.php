<?php
date_default_timezone_set('Asia/Manila');

// Read connection string from environment variable (set in Vercel dashboard)
// Format: postgres://USER:PASSWORD@HOST:PORT/DATABASE
$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    die("<div style=\"font-family:sans-serif; padding:50px; text-align:center;\">
            <h2 style=\"color:#e11d48;\">Configuration Error</h2>
            <p>DATABASE_URL environment variable is not set. Please configure it in Vercel.</p>
         </div>");
}

// Clean up the URL (sometimes users accidentally include "DATABASE_URL=" or spaces)
$databaseUrl = trim($databaseUrl);
if (strpos($databaseUrl, 'DATABASE_URL=') === 0) {
    $databaseUrl = substr($databaseUrl, 13);
}

$user = $pass = $host = $dbname = null;
$port = 5432;

// Method 1: Robust Regex (handles special characters in passwords)
if (preg_match('/^postgres(?:ql)?:\/\/([^:]+):(.*)@([^:\/]+)(?::(\d+))?\/(.+)$/', $databaseUrl, $matches)) {
    $user   = $matches[1];
    $pass   = $matches[2];
    $host   = $matches[3];
    $port   = $matches[4] ?: 5432;
    $dbPath = $matches[5];
    // Remove query params from dbname if present (e.g. ?sslmode=require)
    $dbname = explode('?', $dbPath)[0];
} 
// Method 2: parse_url fallback
else {
    $parsed = parse_url($databaseUrl);
    $user   = $parsed['user'] ?? null;
    $pass   = $parsed['pass'] ?? null;
    $host   = $parsed['host'] ?? null;
    $port   = $parsed['port'] ?? 5432;
    $dbPath = ltrim($parsed['path'] ?? '', '/');
    $dbname = explode('?', $dbPath)[0];
}

if (!$host || !$user || !$dbname) {
    $errorType = !$host ? "host" : (!$user ? "username" : "database name");
    die("<div style=\"font-family:sans-serif; padding:50px; text-align:center;\">
            <h2 style=\"color:#e11d48;\">Configuration Error</h2>
            <p>DATABASE_URL is missing a valid <strong>$errorType</strong>.</p>
            <p style=\"color:#64748b; font-size:0.85rem;\">Format: postgres://USER:PASS@HOST:PORT/DBNAME</p>
         </div>");
}

try {
    $dsn  = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Register Database-backed Session Handler (for Vercel persistence)
    require_once __DIR__ . '/../utils/session_handler.php';
    $handler = new PdoSessionHandler($conn);
    session_set_save_handler($handler, true);

    // Start session AFTER handler is registered
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());

    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['error_msg'] = "Database connection failed. Please try again later.";

    die("<div style=\"font-family:sans-serif; padding:50px; text-align:center;\">
            <h2 style=\"color:#e11d48;\">System Unavailable</h2>
            <p>We're having trouble connecting to the database. Please try again later.</p>
            <div style=\"margin-top:20px;\">
                <button onclick=\"location.reload()\" style=\"padding:10px 20px; background:#181a1f; color:#fff; border:none; border-radius:5px; cursor:pointer;\">Retry</button>
            </div>
         </div>");
}
?>
