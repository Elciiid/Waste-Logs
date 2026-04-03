<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../auth/auth_helpers.php';
require_once '../utils/functions.php';

$currentUser = getCurrentUser();

if (!hasPermission($conn, 'access_settings')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access: You lack the required permissions to manage settings.']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'save_user') {
        $userId   = $_POST['userId']   ?? '';
        $username = trim($_POST['username'] ?? '');
        $roleId   = $_POST['roleId']   ?? '';
        $phaseId  = $_POST['phaseId']  ?: null;
        $areaId   = $_POST['areaId']   ?: null;

        if (empty($username) || empty($roleId)) {
            throw new Exception("Username and Role are required");
        }

        // Lookup full name from app_employees (replaces LRNPH_E.dbo.lrn_master_list)
        $nameSql = $conn->prepare("SELECT \"FirstName\", \"LastName\" FROM app_employees WHERE LOWER(\"BiometricsID\") = LOWER(?) AND \"IsActive\" = TRUE");
        $nameSql->execute([$username]);
        $match    = $nameSql->fetch(PDO::FETCH_ASSOC);
        $fullName = $match ? trim($match['FirstName']) . ' ' . trim($match['LastName']) : $username;

        if (!empty($userId)) {
            $sql  = "UPDATE wst_users SET \"Username\" = ?, \"RoleID\" = ?, \"PhaseID\" = ?, \"AreaID\" = ?, \"FullName\" = ? WHERE \"UserID\" = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username, $roleId, $phaseId, $areaId, $fullName, $userId]);
            $msg = "Personnel assignment updated successfully";
        } else {
            $check = $conn->prepare("SELECT COUNT(*) FROM wst_users WHERE LOWER(\"Username\") = LOWER(?)");
            $check->execute([$username]);
            if ($check->fetchColumn() > 0) throw new Exception("This user is already assigned a role in the system");

            $sql  = "INSERT INTO wst_users (\"Username\", \"RoleID\", \"PhaseID\", \"AreaID\", \"FullName\") VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username, $roleId, $phaseId, $areaId, $fullName]);
            $msg = "Personnel assigned successfully";
        }
        echo json_encode(['success' => true, 'message' => $msg]);

    } elseif ($action === 'save_master') {
        $table  = $_POST['table']  ?? '';
        $column = $_POST['column'] ?? '';
        $value  = trim($_POST['value']  ?? '');
        $idVal  = $_POST['idVal']   ?? '';
        $idCol  = $_POST['idCol']   ?? '';

        if (!isTableAllowed($table))      throw new Exception("Invalid table");
        if (!isIdentifierAllowed($column)) throw new Exception("Invalid column");
        if (empty($value))                throw new Exception("Value cannot be empty");

        $value = ucwords(strtolower($value));

        // Map table/column names to PostgreSQL lowercase table names
        $pgTable  = strtolower($table);
        $pgColumn = "\"$column\"";
        $pgIdCol  = $idCol ? "\"$idCol\"" : null;

        if ($idVal && $idCol) {
            if (!isIdentifierAllowed($idCol)) throw new Exception("Invalid ID column");
            $check = $conn->prepare("SELECT COUNT(*) FROM $pgTable WHERE $pgColumn = ? AND $pgIdCol != ?");
            $check->execute([$value, $idVal]);
        } else {
            $check = $conn->prepare("SELECT COUNT(*) FROM $pgTable WHERE $pgColumn = ?");
            $check->execute([$value]);
        }
        if ($check->fetchColumn() > 0) throw new Exception("This entry already exists");

        $isLogType = ($table === 'wst_LogTypes');
        if ($idVal && $idCol) {
            $sql  = "UPDATE $pgTable SET $pgColumn = ?" . ($isLogType ? ", \"PhaseID\" = ?" : "") . " WHERE $pgIdCol = ?";
            $stmt = $conn->prepare($sql);
            $args = [$value];
            if ($isLogType) $args[] = $_POST['phaseId'] ?: null;
            $args[] = $idVal;
            $stmt->execute($args);
            $msg = "Entry updated successfully";
        } else {
            $sql  = "INSERT INTO $pgTable ($pgColumn" . ($isLogType ? ", \"PhaseID\"" : "") . ") VALUES (?" . ($isLogType ? ", ?" : "") . ")";
            $stmt = $conn->prepare($sql);
            $args = [$value];
            if ($isLogType) $args[] = $_POST['phaseId'] ?: null;
            $stmt->execute($args);
            $msg = "Entry added successfully";
        }
        echo json_encode(['success' => true, 'message' => $msg]);

    } elseif ($action === 'delete') {
        $table = $_POST['table'] ?? '';
        $idCol = $_POST['idCol'] ?? '';
        $idVal = $_POST['idVal'] ?? '';

        if (!isTableAllowed($table))       throw new Exception("Invalid table");
        if (!isIdentifierAllowed($idCol))  throw new Exception("Invalid ID column");

        $pgTable = strtolower($table);
        $sql  = "DELETE FROM $pgTable WHERE \"$idCol\" = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idVal]);
        echo json_encode(['success' => true, 'message' => "Record deleted successfully"]);
    } else {
        throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
