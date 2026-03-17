<?php
date_default_timezone_set('Asia/Manila');
$auth_server_name = "10.2.0.9";
$database_name    = "LRNPH_OJT";
$username         = "sa";
$password         = "S3rverDB02lrn25";

try {
    $conn = new PDO("sqlsrv:server=$auth_server_name;Database=$database_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Log error internally
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Show a user-friendly message instead of raw error
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['error_msg'] = "Database connection failed. Please try again later.";
    
    die("<div style=\"font-family:sans-serif; padding:50px; text-align:center;\">
            <h2 style=\"color:#e11d48;\">System Unavailable</h2>
            <p>We're having trouble connecting to the database. Please contact IT support if this persists.</p>
            <button onclick=\"location.reload()\" style=\"padding:10px 20px; background:#181a1f; color:#fff; border:none; border-radius:5px; cursor:pointer;\">Retry</button>
         </div>");
}
?>
