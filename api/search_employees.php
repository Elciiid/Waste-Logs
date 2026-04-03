<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../auth/auth_helpers.php';

$currentUser = getCurrentUser();
if (!hasSettingsAccess($conn, $currentUser['username'])) {
    echo json_encode([]);
    exit();
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

try {
    // Search in app_employees (replaces LRNPH_E.dbo.lrn_master_list)
    $sql = "
        SELECT
            e.\"BiometricsID\" as username,
            TRIM(COALESCE(e.\"FirstName\", '') || ' ' || COALESCE(e.\"LastName\", '')) as full_name,
            e.\"PositionTitle\"
        FROM app_employees e
        WHERE (e.\"BiometricsID\" ILIKE ? OR e.\"FirstName\" ILIKE ? OR e.\"LastName\" ILIKE ?)
        AND e.\"IsActive\" = TRUE
        ORDER BY e.\"BiometricsID\" ASC
        LIMIT 10
    ";

    $searchTerm = "%$query%";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);

} catch (PDOException $e) {
    echo json_encode([]);
}
