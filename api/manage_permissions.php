<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../connection/database.php';
require_once __DIR__ . '/../auth/auth_helpers.php';

$currentUser = getCurrentUser();
if (!hasSettingsAccess($conn, $currentUser['username'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$roleId      = $_POST['roleId'] ?? null;
$roleName    = trim($_POST['roleName'] ?? '');
$permissions = $_POST['permissions'] ?? [];

if (!$roleId) {
    echo json_encode(['success' => false, 'error' => 'RoleID is required']);
    exit();
}

try {
    $conn->beginTransaction();

    // 1. Update role name if provided
    if (!empty($roleName)) {
        $roleName = ucwords(strtolower($roleName));
        $check = $conn->prepare("SELECT COUNT(*) FROM wst_roles WHERE \"RoleName\" = ? AND \"RoleID\" != ?");
        $check->execute([$roleName, $roleId]);
        if ($check->fetchColumn() > 0) throw new Exception("A role with this name already exists");

        $stmt = $conn->prepare("UPDATE wst_roles SET \"RoleName\" = ? WHERE \"RoleID\" = ?");
        $stmt->execute([$roleName, $roleId]);
    }

    // 2. Clear existing permissions for this role
    $stmt = $conn->prepare("DELETE FROM wst_role_permissions WHERE \"RoleID\" = ?");
    $stmt->execute([$roleId]);

    // 3. Insert new selections
    if (!empty($permissions)) {
        $insert = $conn->prepare("INSERT INTO wst_role_permissions (\"RoleID\", \"PermissionID\") VALUES (?, ?)");
        foreach ($permissions as $pId) {
            $insert->execute([$roleId, $pId]);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
