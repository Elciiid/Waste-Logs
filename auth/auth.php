<?php
// auth/auth.php — Session guard + auto-fill from Supabase
require_once __DIR__ . '/../connection/database.php';
require_once __DIR__ . '/auth_helpers.php';

// session_start() is now handled inside database.php

// 1. Gatekeeper: If not logged in, redirect to login
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header('Location: /pages/login.php');
    exit();
}

// 2. Auto-fill session details if incomplete
if (!isset($_SESSION['user_id']) || !isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    try {
        $identifier = $_SESSION['user_id'] ?? null;
        $field      = $identifier ? 'u.user_id' : 'u.username';
        $value      = $identifier ?? $_SESSION['username'];

        $stmt = $conn->prepare("
            SELECT u.user_id, u.username, u.full_name, u.role,
                   w.\"RoleID\", r.\"RoleName\", w.\"AreaID\", w.\"PhaseID\"
            FROM app_users u
            LEFT JOIN wst_users w ON LOWER(u.username) = LOWER(w.\"Username\")
            LEFT JOIN wst_roles r ON w.\"RoleID\" = r.\"RoleID\"
            WHERE $field = ?
        ");
        $stmt->execute([$value]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $stmtEmp = $conn->prepare("
                SELECT \"FirstName\", \"LastName\", \"PositionTitle\", \"EmployeeID\", \"Department\"
                FROM app_employees
                WHERE LOWER(\"BiometricsID\") = LOWER(?) AND \"IsActive\" = TRUE
            ");
            $stmtEmp->execute([$user['username']]);
            $masterInfo = $stmtEmp->fetch(PDO::FETCH_ASSOC);
            bootstrapSession($user, $masterInfo);
        } else {
            session_destroy();
            header('Location: /pages/login.php?error=session_invalid');
            exit();
        }
    } catch (PDOException $e) {
        header('Location: /pages/login.php?error=db_error');
        exit();
    }
}

// Default avatar
if (!defined('DEFAULT_AVATAR_URL')) {
    define('DEFAULT_AVATAR_URL', "data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2394a3b8%22 style=%22background:%23e2e8f0; border-radius: 50%;%22%3e%3cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3e%3c/svg%3e");
}
?>
