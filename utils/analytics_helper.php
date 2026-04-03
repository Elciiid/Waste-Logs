<?php
/**
 * utils/analytics_helper.php
 * Helper functions for dashboard analytics — PostgreSQL (Supabase) version.
 */

/**
 * Get top 3 distribution of waste by category.
 */
function getWasteDistributionByCategory($conn) {
    $sql = "SELECT c.\"CategoryName\", COUNT(w.\"LogID\") as log_count,
                   SUM(w.\"KG\") as total_kg, SUM(w.\"PCS\") as total_pcs
            FROM wst_pcategories c
            LEFT JOIN wst_logs w ON c.\"CategoryID\" = w.\"CategoryID\"
            GROUP BY c.\"CategoryName\"
            ORDER BY log_count DESC
            LIMIT 3";
    try {
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get monthly waste trends (current year up to current month).
 */
function getMonthlyWasteTrends($conn) {
    $dataMap = [];
    $today        = new DateTime();
    $currentYear  = $today->format('Y');
    $currentMonth = (int)$today->format('n');

    for ($m = 1; $m <= $currentMonth; $m++) {
        $dateObj = DateTime::createFromFormat('!Y-n', "$currentYear-$m");
        $key     = $dateObj->format('Y-m');
        $dataMap[$key] = [
            'month_label'  => $dateObj->format('M'),
            'year_num'     => $currentYear,
            'month_num'    => $m,
            'total_kg'     => 0,
            'others_count' => 0,
        ];
    }

    $yearStartStr = "$currentYear-01-01";
    $sql = "SELECT
                TO_CHAR(w.\"LogDate\", 'YYYY-MM') as month_key,
                SUM(w.\"KG\") as total_kg,
                COUNT(CASE WHEN t.\"TypeName\" ILIKE '%Other%' THEN 1 END) as others_count
            FROM wst_logs w
            JOIN wst_log_types t ON w.\"TypeID\" = t.\"TypeID\"
            WHERE w.\"LogDate\"::date >= :year_start
            GROUP BY TO_CHAR(w.\"LogDate\", 'YYYY-MM')";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([':year_start' => $yearStartStr]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            $monthKey = $row['month_key'];
            if ($monthKey && isset($dataMap[$monthKey])) {
                $dataMap[$monthKey]['total_kg']     += $row['total_kg'];
                $dataMap[$monthKey]['others_count'] += $row['others_count'];
            }
        }
    } catch (PDOException $e) {}

    return array_values($dataMap);
}

/**
 * Get daily waste trends (current week starting Sunday).
 */
function getDailyWasteTrends($conn) {
    $dataMap    = [];
    $today      = new DateTime();
    $startOfWeek = clone $today;
    $dayOfWeek  = (int)$today->format('w');
    if ($dayOfWeek > 0) {
        $startOfWeek->modify("-$dayOfWeek days");
    }

    $currentDate = clone $startOfWeek;
    $endDate     = clone $today;
    $endDate->modify('+1 day');
    while ($currentDate < $endDate) {
        $key = $currentDate->format('Y-m-d');
        $dataMap[$key] = [
            'label'        => $currentDate->format('d M'),
            'date_val'     => $key,
            'total_kg'     => 0,
            'others_count' => 0,
        ];
        $currentDate->modify('+1 day');
    }

    $sql = "SELECT
                w.\"LogDate\"::date as date_val,
                SUM(w.\"KG\") as total_kg,
                COUNT(CASE WHEN t.\"TypeName\" ILIKE '%Other%' THEN 1 END) as others_count
            FROM wst_logs w
            JOIN wst_log_types t ON w.\"TypeID\" = t.\"TypeID\"
            WHERE w.\"LogDate\" >= :start_of_week
            GROUP BY w.\"LogDate\"::date";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([':start_of_week' => $startOfWeek->format('Y-m-d')]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            $dateStr = $row['date_val'];
            if ($dateStr) {
                $formattedDate = substr($dateStr, 0, 10);
                if (isset($dataMap[$formattedDate])) {
                    $dataMap[$formattedDate]['total_kg']     += $row['total_kg'];
                    $dataMap[$formattedDate]['others_count'] += $row['others_count'];
                }
            }
        }
    } catch (PDOException $e) {}

    return array_values($dataMap);
}

/**
 * Get weekly waste trends (7-day blocks in the current month).
 */
function getWeeklyWasteTrends($conn) {
    $dataMap          = [];
    $today            = new DateTime();
    $currentDay       = (int)$today->format('j');
    $currentWeekOfMonth = ceil($currentDay / 7);
    $monthStr         = $today->format('M');
    $yearMonth        = $today->format('Y-m');

    for ($w = 1; $w <= $currentWeekOfMonth; $w++) {
        $key = "$yearMonth-$w";
        $dataMap[$key] = [
            'label'        => "Week $w $monthStr",
            'key'          => $key,
            'total_kg'     => 0,
            'others_count' => 0,
        ];
    }

    $monthStartStr = $today->format('Y-m-01');
    $sql = "SELECT
                w.\"LogDate\"::date as date_val,
                SUM(w.\"KG\") as total_kg,
                COUNT(CASE WHEN t.\"TypeName\" ILIKE '%Other%' THEN 1 END) as others_count
            FROM wst_logs w
            JOIN wst_log_types t ON w.\"TypeID\" = t.\"TypeID\"
            WHERE w.\"LogDate\"::date >= :month_start
            GROUP BY w.\"LogDate\"::date";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([':month_start' => $monthStartStr]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            $dateStr = $row['date_val'];
            if ($dateStr) {
                $formattedDate = substr($dateStr, 0, 10);
                $logDate = new DateTime($formattedDate);
                $day  = (int)$logDate->format('j');
                $w    = ceil($day / 7);
                $key  = $logDate->format('Y-m') . '-' . $w;
                if (isset($dataMap[$key])) {
                    $dataMap[$key]['total_kg']     += $row['total_kg'];
                    $dataMap[$key]['others_count'] += $row['others_count'];
                }
            }
        }
    } catch (PDOException $e) {}

    return array_values($dataMap);
}

/**
 * Get filtered counts and weights based on time scale.
 */
function getWasteStatsFiltered($conn, $timeScale = 'daily') {
    try {
        if ($timeScale === 'daily') {
            $dateCondition = "\"LogDate\"::date = CURRENT_DATE";
        } elseif ($timeScale === 'weekly') {
            $dateCondition = "\"LogDate\" >= date_trunc('week', CURRENT_DATE) AND \"LogDate\"::date <= CURRENT_DATE";
        } else {
            $dateCondition = "EXTRACT(YEAR FROM \"LogDate\") = EXTRACT(YEAR FROM NOW()) AND EXTRACT(MONTH FROM \"LogDate\") = EXTRACT(MONTH FROM NOW())";
        }

        $totalLogs   = $conn->query("SELECT COUNT(*) FROM wst_logs WHERE $dateCondition")->fetchColumn();
        $stmt        = $conn->query("SELECT SUM(\"KG\") as total_kg, SUM(\"PCS\") as total_pcs FROM wst_logs WHERE $dateCondition");
        $weights     = $stmt->fetch(PDO::FETCH_ASSOC);
        $othersCount = $conn->query("SELECT COUNT(w.\"LogID\") FROM wst_logs w JOIN wst_log_types t ON w.\"TypeID\" = t.\"TypeID\" WHERE t.\"TypeName\" ILIKE '%Other%' AND $dateCondition")->fetchColumn();
        $areaCount   = $conn->query("SELECT COUNT(DISTINCT \"AreaID\") FROM wst_logs WHERE $dateCondition")->fetchColumn();

        return [
            'total_logs'   => $totalLogs   ?: 0,
            'total_kg'     => $weights['total_kg'] ?: 0,
            'others_count' => $othersCount ?: 0,
            'area_count'   => $areaCount   ?: 0,
        ];
    } catch (PDOException $e) {
        return ['total_logs' => 0, 'total_kg' => 0, 'others_count' => 0, 'area_count' => 0];
    }
}

/**
 * Get general counts and weights.
 */
function getWasteStats($conn) {
    try {
        $totalLogsResult = $conn->query("SELECT COUNT(*) FROM wst_logs")->fetchColumn();
        $todayLogsResult = $conn->query("SELECT COUNT(*) FROM wst_logs WHERE \"LogDate\"::date = CURRENT_DATE")->fetchColumn();
        $stmt    = $conn->query("SELECT SUM(\"KG\") as total_kg, SUM(\"PCS\") as total_pcs FROM wst_logs");
        $weights = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt    = $conn->query("SELECT t.\"TypeName\", COUNT(w.\"LogID\") as count FROM wst_log_types t LEFT JOIN wst_logs w ON t.\"TypeID\" = w.\"TypeID\" GROUP BY t.\"TypeName\"");
        $types   = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $othersCount = 0;
        foreach ($types as $name => $count) {
            if (stripos($name, 'Other') !== false) $othersCount += $count;
        }
        $areaCount = $conn->query("SELECT COUNT(DISTINCT \"AreaID\") FROM wst_logs")->fetchColumn() ?: 0;

        return [
            'total_logs'   => $totalLogsResult ?: 0,
            'today_logs'   => $todayLogsResult ?: 0,
            'total_kg'     => $weights['total_kg'] ?: 0,
            'others_count' => $othersCount,
            'area_count'   => $areaCount,
        ];
    } catch (PDOException $e) {
        return ['total_logs' => 0, 'today_logs' => 0, 'total_kg' => 0, 'total_pcs' => 0, 'types' => []];
    }
}

/**
 * Get metrics for the TV Dashboard based on Phase and Categories.
 */
function getTVBoardMetrics($conn, $phaseId) {
    try {
        $sqlTodayCats = "SELECT DISTINCT w.\"CategoryID\", c.\"CategoryName\"
                         FROM wst_logs w
                         JOIN wst_pcategories c ON w.\"CategoryID\" = c.\"CategoryID\"
                         WHERE w.\"PhaseID\" = :phaseId
                         AND w.\"LogDate\"::date = CURRENT_DATE";
        $stmtCats = $conn->prepare($sqlTodayCats);
        $stmtCats->execute([':phaseId' => $phaseId]);
        $activeCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

        if (empty($activeCats)) return [];

        $results = [];
        foreach ($activeCats as $cat) {
            $catId   = $cat['CategoryID'];
            $catName = $cat['CategoryName'];
            $categoryData = ['metrics' => []];

            $targetTypes = match((int)$phaseId) {
                1 => ['Disposed Waste' => ['%Dispos%'], 'Crumble' => ['%Crumble%']],
                2 => ['Disposed Waste' => ['%Dispos%'], 'Excess Dough' => ['%Excess%']],
                3 => ['Shell' => ['%Shell%'], 'Base / Tart' => ['%Base%', '%Tart%'], 'Puff' => ['%Puff%'], 'Assembled' => ['%Assembl%']],
                default => ['Disposed Waste' => ['%Dispos%']],
            };

            foreach ($targetTypes as $label => $patterns) {
                $todayVal = 0; $yesterdayVal = 0;
                foreach ($patterns as $typePattern) {
                    $sqlToday = "SELECT SUM(w.\"KG\") FROM wst_logs w
                                 JOIN wst_log_types t ON w.\"TypeID\" = t.\"TypeID\"
                                 WHERE w.\"CategoryID\" = :catId AND w.\"PhaseID\" = :phaseId
                                 AND t.\"TypeName\" ILIKE :typePattern
                                 AND w.\"LogDate\"::date = CURRENT_DATE";
                    $stmt = $conn->prepare($sqlToday);
                    $stmt->execute([':catId' => $catId, ':phaseId' => $phaseId, ':typePattern' => $typePattern]);
                    $todayVal += (float)$stmt->fetchColumn() ?: 0;

                    $sqlYesterday = "SELECT SUM(w.\"KG\") FROM wst_logs w
                                     JOIN wst_log_types t ON w.\"TypeID\" = t.\"TypeID\"
                                     WHERE w.\"CategoryID\" = :catId AND w.\"PhaseID\" = :phaseId
                                     AND t.\"TypeName\" ILIKE :typePattern
                                     AND w.\"LogDate\"::date = CURRENT_DATE - INTERVAL '1 day'";
                    $stmt = $conn->prepare($sqlYesterday);
                    $stmt->execute([':catId' => $catId, ':phaseId' => $phaseId, ':typePattern' => $typePattern]);
                    $yesterdayVal += (float)$stmt->fetchColumn() ?: 0;
                }

                $trend = 'stable';
                if ($todayVal > $yesterdayVal) $trend = 'up';
                elseif ($todayVal < $yesterdayVal && $yesterdayVal > 0) $trend = 'down';

                $categoryData['metrics'][] = [
                    'label' => strtoupper($label),
                    'data'  => ['val' => $todayVal, 'trend' => $trend],
                ];
            }
            $results[$catName] = $categoryData;
        }
        return $results;
    } catch (PDOException $e) {
        error_log("getTVBoardMetrics Error: " . $e->getMessage());
        return [];
    }
}
?>
