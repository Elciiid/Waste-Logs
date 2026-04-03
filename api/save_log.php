<?php
require_once __DIR__ . '/../connection/database.php';
// session_start() is now handled inside database.php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitterId   = $_SESSION['username'] ?? null;

    $logDateRaw    = $_POST['LogDate'] ?? date('Y-m-d H:i:s');
    $logDateClean  = str_replace(' | ', ' ', $logDateRaw);
    $logDate       = date('Y-m-d H:i:s', strtotime($logDateClean));
    $typeId        = $_POST['TypeID']        ?? null;
    $phaseId       = (!empty($_SESSION['wst_phase_id'])) ? $_SESSION['wst_phase_id'] : ($_POST['PhaseID'] ?? null);
    $areaId        = $_POST['AreaID']        ?? null;
    $shiftId       = $_POST['ShiftID']       ?? null;
    $categoryId    = $_POST['CategoryID']    ?? null;
    $descriptionId = $_POST['DescriptionID'] ?? null;
    $pcs           = !empty($_POST['PCS']) ? $_POST['PCS'] : null;
    $kg            = !empty($_POST['KG'])  ? $_POST['KG']  : null;
    $reason          = $_POST['Reason']          ?? '';
    $otherTypeRemark = $_POST['OtherTypeRemark'] ?? null;

    if (!$typeId || !$phaseId || !$areaId || !$shiftId || !$categoryId || !$descriptionId) {
        $_SESSION['error_msg'] = "Please select all required dropdown options to save the log.";
        header('Location: /pages/index.php');
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT \"TypeName\" FROM wst_log_types WHERE \"TypeID\" = ?");
        $stmt->execute([$typeId]);
        $typeName = $stmt->fetchColumn();

        if ($typeName === 'Others' && empty($otherTypeRemark)) {
            $_SESSION['error_msg'] = "Other Type Detail (Remark) is mandatory when selecting 'Others'.";
            header('Location: /pages/index.php');
            exit();
        }

        $sql = "INSERT INTO wst_logs
                (\"LogDate\", \"TypeID\", \"PhaseID\", \"AreaID\", \"ShiftID\", \"CategoryID\", \"DescriptionID\", \"PCS\", \"KG\", \"Reason\", \"SubmittedBy\", \"CurrentStep\", \"OtherTypeRemark\")
                VALUES
                (:logDate, :typeId, :phaseId, :areaId, :shiftId, :categoryId, :descriptionId, :pcs, :kg, :reason, :submittedBy, 1, :otherTypeRemark)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':logDate',        $logDate);
        $stmt->bindParam(':typeId',         $typeId,         PDO::PARAM_INT);
        $stmt->bindParam(':phaseId',        $phaseId,        PDO::PARAM_INT);
        $stmt->bindParam(':areaId',         $areaId,         PDO::PARAM_INT);
        $stmt->bindParam(':shiftId',        $shiftId,        PDO::PARAM_INT);
        $stmt->bindParam(':categoryId',     $categoryId,     PDO::PARAM_INT);
        $stmt->bindParam(':descriptionId',  $descriptionId,  PDO::PARAM_INT);
        $stmt->bindParam(':pcs',            $pcs,            PDO::PARAM_INT);
        $stmt->bindParam(':kg',             $kg);
        $stmt->bindParam(':reason',         $reason,         PDO::PARAM_STR);
        $stmt->bindParam(':submittedBy',    $submitterId,    PDO::PARAM_STR);
        $stmt->bindParam(':otherTypeRemark',$otherTypeRemark,PDO::PARAM_STR);

        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Your waste/transfer log has been saved successfully.";
        } else {
            $_SESSION['error_msg'] = "Error saving the log. Please try again.";
        }
        header('Location: /pages/index.php');
        exit();

    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
        header('Location: /pages/index.php');
        exit();
    }

} else {
    header('Location: /pages/index.php');
    exit();
}
?>
