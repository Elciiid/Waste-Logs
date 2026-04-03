<?php
/**
 * ============================================================
 * Approval Workflow — Core Module (PostgreSQL / Supabase)
 * ============================================================
 *
 * STATE MACHINE
 * ─────────────
 * Every WasteLog starts with CurrentStep = 1, ApprovalStatus = 'Pending'.
 *
 * CurrentStep  │ Who Must Approve           │ Scope
 * ─────────────┼────────────────────────────┼─────────────
 *      1       │ Manager (all types)        │ Same Phase
 *      2       │ Internal Security          │ All Phases
 *      0       │ (fully approved)           │ —
 */

define('APPROVAL_STEPS', [
    1 => [
        'roles' => ['Manager', 'Production Manager A', 'Production Manager B', 'Production Manager C', 'Assistant Product Manager'],
        'label' => 'Manager',
        'scope' => 'phase',
    ],
    2 => [
        'roles' => ['Internal Security'],
        'label' => 'Internal Security',
        'scope' => 'global',
    ],
]);

function userHasPermissionForStep($conn, int $step): bool
{
    require_once __DIR__ . '/../auth/auth_helpers.php';
    return hasPermission($conn, "approve_step_$step");
}

function getAuthorizedStepsForUser($conn): array
{
    $authorizedSteps = [];
    foreach (APPROVAL_STEPS as $step => $config) {
        if (userHasPermissionForStep($conn, $step)) {
            $authorizedSteps[] = $step;
        }
    }
    return $authorizedSteps;
}

function getStepForUser($conn): array
{
    $steps = getAuthorizedStepsForUser($conn);
    if (empty($steps)) return [null, null];
    $firstStep = $steps[0];
    return [$firstStep, APPROVAL_STEPS[$firstStep]];
}

/**
 * Returns all WasteLog rows the current user is allowed to approve.
 */
function getPendingRequests(PDO $conn, $userPhaseId, ?string $userRoleName = null, array $filters = []): array
{
    $authorizedSteps = getAuthorizedStepsForUser($conn);
    if (empty($authorizedSteps)) return [];

    $limitClause = '';
    if (isset($filters['limit']) && is_numeric($filters['limit'])) {
        $limitClause = "LIMIT " . intval($filters['limit']);
    }

    $stepsIn = implode(',', array_map('intval', $authorizedSteps));

    $sql = "
        SELECT w.\"LogID\", w.\"LogDate\", w.\"TypeID\", w.\"PhaseID\", w.\"AreaID\",
               w.\"ShiftID\", w.\"CategoryID\", w.\"DescriptionID\",
               w.\"PCS\", w.\"KG\", w.\"Reason\", w.\"SubmittedBy\",
               w.\"CurrentStep\", w.\"ApprovalStatus\",
               w.\"Step1ApprovedBy\", w.\"Step1ApprovedAt\",
               w.\"Step2ApprovedBy\", w.\"Step2ApprovedAt\",
               w.\"Step3ApprovedBy\", w.\"Step3ApprovedAt\",
               w.\"Step4ApprovedBy\", w.\"Step4ApprovedAt\",
               w.\"Step5ApprovedBy\", w.\"Step5ApprovedAt\",
               w.\"OtherTypeRemark\",
               p.\"PhaseName\", t.\"TypeName\", a.\"AreaName\",
               s.\"ShiftName\", c.\"CategoryName\", d.\"DescriptionName\",
               COALESCE(NULLIF(TRIM(COALESCE(m.\"FirstName\",'') || ' ' || COALESCE(m.\"LastName\",'')), ''),
                        NULLIF(TRIM(lu.full_name), ''),
                        w.\"SubmittedBy\") AS \"SubmitterName\",
               COALESCE(NULLIF(TRIM(m.\"EmployeeID\"), ''), w.\"SubmittedBy\") AS \"SubmitterEmployeeID\",
               COALESCE(NULLIF(TRIM(COALESCE(au.\"FirstName\",'') || ' ' || COALESCE(au.\"LastName\",'')), ''),
                        NULLIF(TRIM(alu.full_name), ''),
                        CASE WHEN w.\"ApprovalStatus\" = 'Declined' THEN w.\"RejectedBy\"
                             ELSE COALESCE(w.\"Step5ApprovedBy\",w.\"Step4ApprovedBy\",w.\"Step3ApprovedBy\",w.\"Step2ApprovedBy\",w.\"Step1ApprovedBy\") END) AS \"ApproverName\",
               COALESCE(NULLIF(TRIM(au.\"EmployeeID\"), ''),
                        CASE WHEN w.\"ApprovalStatus\" = 'Declined' THEN w.\"RejectedBy\"
                             ELSE COALESCE(w.\"Step5ApprovedBy\",w.\"Step4ApprovedBy\",w.\"Step3ApprovedBy\",w.\"Step2ApprovedBy\",w.\"Step1ApprovedBy\") END) AS \"ApproverEmployeeID\",
               COALESCE(NULLIF(TRIM(au.\"BiometricsID\"), ''),
                        CASE WHEN w.\"ApprovalStatus\" = 'Declined' THEN w.\"RejectedBy\"
                             ELSE COALESCE(w.\"Step5ApprovedBy\",w.\"Step4ApprovedBy\",w.\"Step3ApprovedBy\",w.\"Step2ApprovedBy\",w.\"Step1ApprovedBy\") END) AS \"ApproverBiometricsID\"
        FROM wst_logs w
        LEFT JOIN wst_phases      p ON w.\"PhaseID\"       = p.\"PhaseID\"
        LEFT JOIN wst_log_types   t ON w.\"TypeID\"        = t.\"TypeID\"
        LEFT JOIN wst_areas       a ON w.\"AreaID\"        = a.\"AreaID\"
        LEFT JOIN wst_shifts      s ON w.\"ShiftID\"       = s.\"ShiftID\"
        LEFT JOIN wst_pcategories c ON w.\"CategoryID\"    = c.\"CategoryID\"
        LEFT JOIN wst_pdescriptions d ON w.\"DescriptionID\" = d.\"DescriptionID\"
        LEFT JOIN app_employees   m ON LOWER(w.\"SubmittedBy\") = LOWER(m.\"BiometricsID\") AND m.\"IsActive\" = TRUE
        LEFT JOIN app_users      lu ON LOWER(w.\"SubmittedBy\") = LOWER(lu.username)
        LEFT JOIN app_employees  au ON LOWER(
            CASE WHEN w.\"ApprovalStatus\" = 'Declined' THEN w.\"RejectedBy\"
                 ELSE COALESCE(w.\"Step5ApprovedBy\",w.\"Step4ApprovedBy\",w.\"Step3ApprovedBy\",w.\"Step2ApprovedBy\",w.\"Step1ApprovedBy\") END
        ) = LOWER(au.\"BiometricsID\")
        LEFT JOIN app_users      alu ON LOWER(
            CASE WHEN w.\"ApprovalStatus\" = 'Declined' THEN w.\"RejectedBy\"
                 ELSE COALESCE(w.\"Step5ApprovedBy\",w.\"Step4ApprovedBy\",w.\"Step3ApprovedBy\",w.\"Step2ApprovedBy\",w.\"Step1ApprovedBy\") END
        ) = LOWER(alu.username)
        WHERE w.\"CurrentStep\" IN ($stepsIn)
          AND w.\"ApprovalStatus\" = 'Pending'
    ";

    $params = [];

    if (!empty($userPhaseId)) {
        $phaseBoundSteps = [];
        foreach ($authorizedSteps as $sNum) {
            if (APPROVAL_STEPS[$sNum]['scope'] === 'phase') {
                $phaseBoundSteps[] = $sNum;
            }
        }
        if (!empty($phaseBoundSteps)) {
            $phaseIn = implode(',', array_map('intval', $phaseBoundSteps));
            $sql .= " AND (w.\"CurrentStep\" NOT IN ($phaseIn) OR w.\"PhaseID\" = :phaseId)";
            $params[':phaseId'] = $userPhaseId;
        }
    }

    if (!empty($filters['startDate'])) { $sql .= " AND w.\"LogDate\" >= :startDate"; $params[':startDate'] = $filters['startDate']; }
    if (!empty($filters['endDate'])) {
        $pe = $filters['endDate'];
        if (strlen($pe) == 10) $pe .= ' 23:59:59';
        $sql .= " AND w.\"LogDate\" <= :endDate";
        $params[':endDate'] = $pe;
    }
    if (!empty($filters['shiftId']))    { $sql .= " AND w.\"ShiftID\" = :shiftId";   $params[':shiftId']   = $filters['shiftId']; }
    if (!empty($filters['areaId']))     { $sql .= " AND w.\"AreaID\" = :areaId";     $params[':areaId']     = $filters['areaId']; }
    if (!empty($filters['typeId']))     { $sql .= " AND w.\"TypeID\" = :typeId";     $params[':typeId']     = $filters['typeId']; }
    if (!empty($filters['categoryId'])) { $sql .= " AND w.\"CategoryID\" = :categoryId"; $params[':categoryId'] = $filters['categoryId']; }

    $sql .= " ORDER BY w.\"LogDate\" DESC, w.\"LogID\" DESC $limitClause";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function approveRequest(PDO $conn, int $requestId, string $userId, $userPhaseId): array
{
    return processApprovalAction($conn, $requestId, $userId, $userPhaseId, 'approve');
}

function rejectRequest(PDO $conn, int $requestId, string $userId, $userPhaseId, string $reason = ''): array
{
    return processApprovalAction($conn, $requestId, $userId, $userPhaseId, 'reject', $reason);
}

function processApprovalAction(PDO $conn, int $requestId, string $userId, $userPhaseId, string $action, string $reason = ''): array
{
    $stmt = $conn->prepare("SELECT \"LogID\", \"PhaseID\", \"CurrentStep\", \"ApprovalStatus\" FROM wst_logs WHERE \"LogID\" = :id");
    $stmt->execute([':id' => $requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) return ['success' => false, 'message' => 'Request not found.'];
    if ($request['ApprovalStatus'] !== 'Pending') return ['success' => false, 'message' => 'This request has already been ' . strtolower($request['ApprovalStatus']) . '.'];

    $currentStep = (int)$request['CurrentStep'];
    if ($currentStep < 1 || $currentStep > 2) return ['success' => false, 'message' => "Request is not in an approvable state (step $currentStep)."];

    $stepConfig = APPROVAL_STEPS[$currentStep];
    if (!userHasPermissionForStep($conn, $currentStep)) {
        return ['success' => false, 'message' => "Access denied. You do not have the 'approve_step_$currentStep' permission."];
    }

    if ($stepConfig['scope'] === 'phase' && !empty($userPhaseId)) {
        if ((int)$userPhaseId !== (int)$request['PhaseID']) {
            return ['success' => false, 'message' => "Access denied. Phase mismatch."];
        }
    }

    if ($action === 'reject') {
        $sql = "UPDATE wst_logs SET
                    \"Step{$currentStep}ApprovedBy\" = :userId,
                    \"Step{$currentStep}ApprovedAt\" = NOW(),
                    \"ApprovalStatus\" = 'Rejected',
                    \"RejectionReason\" = :reason,
                    \"RejectedBy\" = :rejectedBy
                WHERE \"LogID\" = :id AND \"CurrentStep\" = :step AND \"ApprovalStatus\" = 'Pending'";
        $update = $conn->prepare($sql);
        $update->execute([':userId' => $userId, ':id' => $requestId, ':step' => $currentStep, ':reason' => $reason, ':rejectedBy' => $userId]);
        if ($update->rowCount() === 0) return ['success' => false, 'message' => 'Race condition: please refresh.'];
        return ['success' => true, 'message' => 'Entry Declined'];
    }

    $nextStep  = ($currentStep === 2) ? 0 : $currentStep + 1;
    $newStatus = ($currentStep === 2) ? 'Approved' : 'Pending';

    $sql = "UPDATE wst_logs SET
                \"Step{$currentStep}ApprovedBy\" = :userId,
                \"Step{$currentStep}ApprovedAt\" = NOW(),
                \"CurrentStep\" = :nextStep,
                \"ApprovalStatus\" = :newStatus
            WHERE \"LogID\" = :id AND \"CurrentStep\" = :currentStep AND \"ApprovalStatus\" = 'Pending'";
    $update = $conn->prepare($sql);
    $update->execute([':userId' => $userId, ':nextStep' => $nextStep, ':newStatus' => $newStatus, ':id' => $requestId, ':currentStep' => $currentStep]);
    if ($update->rowCount() === 0) return ['success' => false, 'message' => 'Race condition: please refresh.'];

    $stepLabel = $stepConfig['label'];
    if ($currentStep === 2) return ['success' => true, 'message' => "Request #$requestId fully approved! Final approval by $stepLabel ($userId)."];
    $nextLabel = APPROVAL_STEPS[$nextStep]['label'];
    return ['success' => true, 'message' => "Request #$requestId approved at Step $currentStep ($stepLabel). Now pending Step $nextStep ($nextLabel)."];
}

function getApprovalHistory(array $wasteLog): array
{
    $history     = [];
    $currentStep = (int)($wasteLog['CurrentStep'] ?? 0);
    $status      = $wasteLog['ApprovalStatus'] ?? 'Pending';

    foreach (APPROVAL_STEPS as $step => $config) {
        $approvedBy = $wasteLog["Step{$step}ApprovedBy"] ?? null;
        $approvedAt = $wasteLog["Step{$step}ApprovedAt"] ?? null;

        if ($approvedBy) {
            $stepStatus = ($status === 'Rejected' && $step === $currentStep) ? 'Rejected' : 'Approved';
        } elseif ($step === $currentStep && $status === 'Pending') {
            $stepStatus = 'Awaiting';
        } else {
            $stepStatus = 'Pending';
        }

        $history[] = [
            'step'       => $step,
            'role'       => $config['label'],
            'scope'      => $config['scope'],
            'approvedBy' => $approvedBy,
            'approvedAt' => $approvedAt,
            'status'     => $stepStatus,
        ];
    }
    return $history;
}

function getApprovalContext(PDO $conn): array
{
    $role  = $_SESSION['wst_role_name'] ?? null;
    $phase = $_SESSION['wst_phase_id']  ?? null;

    $pendingLogs = [];
    if ($role) {
        try {
            $pendingLogs = getPendingRequests($conn, $phase);
        } catch (PDOException $e) {}
    }

    return [
        'pendingLogs'       => $pendingLogs,
        'pendingCount'      => count($pendingLogs),
        'latestPendingLogs' => array_slice($pendingLogs, 0, 5),
    ];
}
?>
