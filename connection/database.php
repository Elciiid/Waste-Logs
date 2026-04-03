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

// Parse the DATABASE_URL into PDO DSN components using regex for robustness
// This handles special characters in passwords better than parse_url()
if (preg_match('/^postgres(?:ql)?:\/\/([^:]+):(.*)@([^:\/]+)(?::(\d+))?\/(.+)$/', $databaseUrl, $matches)) {
    $user   = $matches[1];
    $pass   = $matches[2];
    $host   = $matches[3];
    $port   = $matches[4] ?: 5432;
    $dbname = $matches[5];
} else {
    // Fallback if regex fails, though DATABASE_URL should match the pattern
    $parsed = parse_url($databaseUrl);
    $host   = $parsed['host'] ?? null;
    $port   = $parsed['port'] ?? 5432;
    $dbname = ltrim($parsed['path'] ?? '', '/');
    $user   = $parsed['user'] ?? null;
    $pass   = $parsed['pass'] ?? null;
}

if (!$host || !$user) {
    die("<div style=\"font-family:sans-serif; padding:50px; text-align:center;\">
            <h2 style=\"color:#e11d48;\">Configuration Error</h2>
            <p>DATABASE_URL is malformed or invalid. Please check your Vercel settings.</p>
         </div>");
}

try {
    $dsn  = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());

    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['error_msg'] = "Database connection failed. Please try again later.";

    die("<div style=\"font-family:sans-serif; padding:50px; text-align:center;\">
            <h2 style=\"color:#e11d48;\">System Unavailable</h2>
            <p>We're having trouble connecting to the database. Please contact IT support if this persists.</p>
            <button onclick=\"location.reload()\" style=\"padding:10px 20px; background:#181a1f; color:#fff; border:none; border-radius:5px; cursor:pointer;\">Retry</button>
         </div>");
}
?>
