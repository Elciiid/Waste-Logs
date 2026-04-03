<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../connection/database.php';
require_once __DIR__ . '/../auth/auth_helpers.php';

$currentUser = getCurrentUser();
if (!hasPermission($conn, 'access_settings')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$roleId = $_GET['roleId'] ?? null;
if (!$roleId) {
    echo json_encode(['success' => false, 'error' => 'RoleID is required']);
    exit();
}

try {
    // 1. Fetch all permissions
    $allPerms = $conn->query(
        "SELECT \"PermissionID\", \"PermissionKey\", \"Label\"
         FROM wst_permissions
         WHERE \"Label\" NOT ILIKE 'Obsolete%'
         ORDER BY \"Label\""
    )->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch assigned permissions for this role
    $assignedStmt = $conn->prepare("SELECT \"PermissionID\" FROM wst_role_permissions WHERE \"RoleID\" = ?");
    $assignedStmt->execute([$roleId]);
    $assignedIds = $assignedStmt->fetchAll(PDO::FETCH_COLUMN);

    // 3. Merge
    foreach ($allPerms as &$p) {
        $p['assigned'] = in_array($p['PermissionID'], $assignedIds);
    }

    echo json_encode(['success' => true, 'permissions' => $allPerms]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
