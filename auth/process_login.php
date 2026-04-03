<?php
session_start();
require_once '../connection/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Please enter both username and password.";
        header("Location: ../pages/login.php");
        exit();
    }

    try {
        // Query app_users joined with wst_users for role/phase/area
        $stmt = $conn->prepare("
            SELECT u.user_id, u.username, u.password, u.full_name, u.role,
                   w.\"RoleID\", r.\"RoleName\", w.\"AreaID\", w.\"PhaseID\"
            FROM app_users u
            LEFT JOIN wst_users w ON LOWER(u.username) = LOWER(w.\"Username\")
            LEFT JOIN wst_roles r ON w.\"RoleID\" = r.\"RoleID\"
            WHERE LOWER(u.username) = LOWER(?)
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Fetch extra details from app_employees using username (BiometricsID)
            $stmtEmp = $conn->prepare("
                SELECT \"FirstName\", \"LastName\", \"PositionTitle\", \"EmployeeID\", \"Department\"
                FROM app_employees
                WHERE LOWER(\"BiometricsID\") = LOWER(?) AND \"IsActive\" = TRUE
            ");
            $stmtEmp->execute([$user['username']]);
            $masterInfo = $stmtEmp->fetch(PDO::FETCH_ASSOC);

            // Bootstrap session
            require_once __DIR__ . '/auth_helpers.php';
            bootstrapSession($user, $masterInfo);

            header("Location: ../pages/dashboard.php");
            exit();
        } else {
            $_SESSION['login_error'] = "Invalid username or password.";
            header("Location: ../pages/login.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['login_error'] = "Authentication error: " . $e->getMessage();
        header("Location: ../pages/login.php");
        exit();
    }
} else {
    header("Location: ../pages/login.php");
    exit();
}
?>
