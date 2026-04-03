<?php
// includes/functions.php
require_once __DIR__ . '/photo_helper.php';

/**
 * Centralized whitelist of allowed master tables for security
 */
function getAllowedTables()
{
    return [
        'wst_Users',
        'wst_LogTypes',
        'wst_Phases',
        'wst_PCategories',
        'wst_Areas',
        'wst_Shifts',
        'wst_PDescriptions',
        'wst_Roles',
    ];
}

/**
 * Centralized whitelist of allowed columns/identifiers for security
 */
function getAllowedIdentifiers()
{
    return [
        'UserID', 'Username', 'RoleID', 'RoleName', 'PhaseID', 'PhaseName', 'AreaID', 'FullName',
        'TypeID', 'TypeName',
        'CategoryID', 'CategoryName',
        'AreaName',
        'ShiftID', 'ShiftName',
        'DescriptionID', 'DescriptionName',
    ];
}

function isIdentifierAllowed($name) { return in_array($name, getAllowedIdentifiers()); }
function isTableAllowed($tableName) { return in_array($tableName, getAllowedTables()); }

/**
 * Fetch all records from a given table (PostgreSQL compatible).
 */
function fetchAllFromTable($conn, $tableName, $orderBy = '')
{
    if (!isTableAllowed($tableName)) return [];

    // Map PascalCase to lowercase PostgreSQL table name
    $pgTableMap = [
        'wst_Users'        => 'wst_users',
        'wst_LogTypes'     => 'wst_log_types',
        'wst_Phases'       => 'wst_phases',
        'wst_PCategories'  => 'wst_pcategories',
        'wst_Areas'        => 'wst_areas',
        'wst_Shifts'       => 'wst_shifts',
        'wst_PDescriptions'=> 'wst_pdescriptions',
        'wst_Roles'        => 'wst_roles',
    ];

    $pgTable = $pgTableMap[$tableName] ?? strtolower($tableName);
    $sql     = "SELECT * FROM $pgTable";
    if ($orderBy !== '') {
        $sql .= " ORDER BY \"$orderBy\"";
    }

    try {
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fetch wst_logs joined with all master tables — PostgreSQL version.
 */
function getWasteLogs($conn, $statusFilter = 'All', $filters = [], $submittedBy = null)
{
    $limitClause = '';
    if (isset($filters['limit']) && is_numeric($filters['limit'])) {
        $limitClause = "LIMIT " . intval($filters['limit']);
    }

    $sql = "SELECT w.*,
               t.\"TypeName\", p.\"PhaseName\", c.\"CategoryName\",
               a.\"AreaName\", s.\"ShiftName\", d.\"DescriptionName\",
               COALESCE(NULLIF(TRIM(COALESCE(u.\"FirstName\",'') || ' ' || COALESCE(u.\"LastName\",'')), ''),
                        NULLIF(TRIM(lu.full_name), ''),
                        w.\"SubmittedBy\") AS \"SubmitterName\",
               COALESCE(NULLIF(TRIM(u.\"EmployeeID\"), ''), w.\"SubmittedBy\") AS \"SubmitterEmployeeID\",
               COALESCE(NULLIF(TRIM(COALESCE(au.\"FirstName\",'') || ' ' || COALESCE(au.\"LastName\",'')), ''),
                        NULLIF(TRIM(alu.full_name), ''),
                        CASE WHEN w.\"ApprovalStatus\" = 'Declined' THEN w.\"RejectedBy\"
                             ELSE COALESCE(w.\"Step2ApprovedBy\", w.\"Step1ApprovedBy\") END) AS \"ApproverName\",
               COALESCE(NULLIF(TRIM(au.\"EmployeeID\"), ''),
                        CASE WHEN w.\"ApprovalStatus\" = 'Declined' THEN w.\"RejectedBy\"
                             ELSE COALESCE(w.\"Step2ApprovedBy\", w.\"Step1ApprovedBy\") END) AS \"ApproverEmployeeID\",
               COALESCE(NULLIF(TRIM(au.\"BiometricsID\"), ''),
                        CASE WHEN w.\"ApprovalStatus\" = 'Declined' THEN w.\"RejectedBy\"
                             ELSE COALESCE(w.\"Step2ApprovedBy\", w.\"Step1ApprovedBy\") END) AS \"ApproverBiometricsID\"
        FROM wst_logs w
        LEFT JOIN wst_log_types t  ON w.\"TypeID\"        = t.\"TypeID\"
        LEFT JOIN wst_phases p     ON w.\"PhaseID\"       = p.\"PhaseID\"
        LEFT JOIN wst_pcategories c ON w.\"CategoryID\"   = c.\"CategoryID\"
        LEFT JOIN wst_areas a      ON w.\"AreaID\"        = a.\"AreaID\"
        LEFT JOIN wst_shifts s     ON w.\"ShiftID\"       = s.\"ShiftID\"
        LEFT JOIN wst_pdescriptions d ON w.\"DescriptionID\" = d.\"DescriptionID\"
        LEFT JOIN app_employees u  ON LOWER(w.\"SubmittedBy\") = LOWER(u.\"BiometricsID\")
        LEFT JOIN app_users lu     ON LOWER(w.\"SubmittedBy\") = LOWER(lu.username)
        LEFT JOIN app_employees au ON LOWER(
            CASE WHEN w.\"ApprovalStatus\" = 'Declined' THEN w.\"RejectedBy\"
                 ELSE COALESCE(w.\"Step2ApprovedBy\", w.\"Step1ApprovedBy\") END
        ) = LOWER(au.\"BiometricsID\")
        LEFT JOIN app_users alu    ON LOWER(
            CASE WHEN w.\"ApprovalStatus\" = 'Declined' THEN w.\"RejectedBy\"
                 ELSE COALESCE(w.\"Step2ApprovedBy\", w.\"Step1ApprovedBy\") END
        ) = LOWER(alu.username)
        WHERE 1=1";

    if ($statusFilter === 'Pending')  $sql .= " AND (w.\"ApprovalStatus\" = 'Pending' OR w.\"ApprovalStatus\" IS NULL)";
    elseif ($statusFilter === 'Resolved') $sql .= " AND w.\"ApprovalStatus\" IN ('Approved', 'Declined')";
    elseif ($statusFilter === 'Approved') $sql .= " AND w.\"ApprovalStatus\" = 'Approved'";
    elseif ($statusFilter === 'Declined') $sql .= " AND w.\"ApprovalStatus\" = 'Declined'";

    $params = [];
    if ($submittedBy !== null) {
        $sql .= " AND w.\"SubmittedBy\" = :submittedBy";
        $params[':submittedBy'] = $submittedBy;
    }
    if (!empty($filters['startDate'])) {
        $sql .= " AND w.\"LogDate\" >= :startDate";
        $params[':startDate'] = $filters['startDate'];
    }
    if (!empty($filters['endDate'])) {
        $sql .= " AND w.\"LogDate\" <= :endDate";
        $pe = $filters['endDate'];
        if (strlen($pe) == 10) $pe .= ' 23:59:59';
        $params[':endDate'] = $pe;
    }
    if (!empty($filters['typeId']))     { $sql .= " AND w.\"TypeID\" = :typeId";     $params[':typeId']     = $filters['typeId']; }
    if (!empty($filters['phaseId']))    { $sql .= " AND w.\"PhaseID\" = :phaseId";   $params[':phaseId']   = $filters['phaseId']; }
    if (!empty($filters['areaId']))     { $sql .= " AND w.\"AreaID\" = :areaId";     $params[':areaId']     = $filters['areaId']; }
    if (!empty($filters['shiftId']))    { $sql .= " AND w.\"ShiftID\" = :shiftId";   $params[':shiftId']   = $filters['shiftId']; }
    if (!empty($filters['categoryId'])) { $sql .= " AND w.\"CategoryID\" = :categoryId"; $params[':categoryId'] = $filters['categoryId']; }

    $sql .= " ORDER BY w.\"LogDate\" DESC, w.\"LogID\" DESC $limitClause";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        handleSystemError("History Fetch Error: " . $e->getMessage());
        return [];
    }
}

/**
 * DRY: Shared error handler.
 */
function handleSystemError($message, $redirect = '../pages/dashboard.php')
{
    error_log("System Error: " . $message);
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['error_msg'] = "Something went wrong. Please try again or contact IT if the issue persists.";
    header("Location: " . $redirect);
    exit();
}
?>
