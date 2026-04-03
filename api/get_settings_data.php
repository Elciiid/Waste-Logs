<?php
require_once __DIR__ . '/../utils/functions.php';
require_once __DIR__ . '/../connection/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$table  = $_GET['table']  ?? '';
$search = $_GET['search'] ?? '';

if (!isTableAllowed($table)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized table access']);
    exit;
}

// Map PascalCase table names (from JS) to PostgreSQL lowercase names
$tableMap = [
    'wst_Users'        => ['pg' => 'wst_users',        'id' => 'UserID',       'name' => 'Username'],
    'wst_PCategories'  => ['pg' => 'wst_pcategories',  'id' => 'CategoryID',   'name' => 'CategoryName'],
    'wst_PDescriptions'=> ['pg' => 'wst_pdescriptions','id' => 'DescriptionID','name' => 'DescriptionName'],
    'wst_LogTypes'     => ['pg' => 'wst_log_types',    'id' => 'TypeID',       'name' => 'TypeName'],
    'wst_Shifts'       => ['pg' => 'wst_shifts',       'id' => 'ShiftID',      'name' => 'ShiftName'],
    'wst_Phases'       => ['pg' => 'wst_phases',       'id' => 'PhaseID',      'name' => 'PhaseName'],
    'wst_Areas'        => ['pg' => 'wst_areas',        'id' => 'AreaID',       'name' => 'AreaName'],
    'wst_Roles'        => ['pg' => 'wst_roles',        'id' => 'RoleID',       'name' => 'RoleName'],
];

if (!isset($tableMap[$table])) {
    echo json_encode(['success' => false, 'message' => "Configuration not found for table: $table"]);
    exit;
}

$cfg     = $tableMap[$table];
$pgTable = $cfg['pg'];
$idCol   = $cfg['id'];
$nameCol = $cfg['name'];
$params  = [];

try {
    if ($table === 'wst_Users') {
        $sql = "SELECT u.*, r.\"RoleName\", p.\"PhaseName\", a.\"AreaName\",
                       TRIM(COALESCE(e.\"FirstName\", '') || ' ' || COALESCE(e.\"LastName\", '')) as FullName
                FROM wst_users u
                LEFT JOIN wst_roles  r ON u.\"RoleID\"  = r.\"RoleID\"
                LEFT JOIN wst_phases p ON u.\"PhaseID\" = p.\"PhaseID\"
                LEFT JOIN wst_areas  a ON u.\"AreaID\"  = a.\"AreaID\"
                LEFT JOIN app_employees e ON LOWER(u.\"Username\") = LOWER(e.\"BiometricsID\")";
        if ($search) {
            $sql .= " WHERE u.\"Username\" ILIKE ? OR e.\"FirstName\" ILIKE ? OR e.\"LastName\" ILIKE ?";
            $params = ["%$search%", "%$search%", "%$search%"];
        }
        $sql .= " ORDER BY u.\"FullName\" ASC";

    } elseif ($table === 'wst_LogTypes') {
        $sql = "SELECT t.*, p.\"PhaseName\"
                FROM wst_log_types t
                LEFT JOIN wst_phases p ON t.\"PhaseID\" = p.\"PhaseID\"";
        if ($search) {
            $sql .= " WHERE t.\"TypeName\" ILIKE ?";
            $params = ["%$search%"];
        }
        $sql .= " ORDER BY t.\"TypeName\" ASC";

    } else {
        $sql = "SELECT * FROM $pgTable";
        if ($search) {
            $sql .= " WHERE \"$nameCol\" ILIKE ?";
            $params = ["%$search%"];
        }
        $sql .= " ORDER BY \"$nameCol\" ASC";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $data,
        'config'  => ['id' => $idCol, 'name' => $nameCol],
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
