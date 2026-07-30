<?php
/**
 * Creates a new maintenance job with one or more activities, under the schema.
 */

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db_connect_fleet.php';
require_once __DIR__ . '/log_helper.php';

function show_error(string $message): void
{
    echo "<p>" . htmlspecialchars($message) . "</p>";
    echo "<p><a href='javascript:history.back()'>Back</a></p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    show_error('Invalid request.');
}

$vehicleId  = trim($_POST['vehicle_id']  ?? '');
$workshopId = $_POST['workshop_id'] ?? null;
$startDate  = trim($_POST['start_date']  ?? '');
$alertId    = $_POST['alert_id']    ?? null;
$alertId    = ($alertId === '') ? null : $alertId;
$totalCost  = $_POST['total_cost']  ?? null;
$totalCost  = ($totalCost === '') ? null : $totalCost;

$activityTypeIds = $_POST['activity_type_id']    ?? [];
$labourHours     = $_POST['labour_hours']         ?? [];
$diagnosticResults = $_POST['diagnostic_result']  ?? [];

if ($vehicleId === '' || !$workshopId || $startDate === '') {
    show_error('Vehicle, workshop, and start date are required.');
}

if (empty($activityTypeIds)) {
    show_error('At least one activity is required for the job.');
}

$conn->begin_transaction();

try {
    // 1. Create the job itself
    $stmt = $conn->prepare(
        "INSERT INTO maintenance_job (VehicleID, WorkshopID, StartDate, Status, AlertID, TotalCost)
         VALUES (?, ?, ?, 'Open', ?, ?)"
    );
    $stmt->bind_param("sisii", $vehicleId, $workshopId, $startDate, $alertId, $totalCost);
    $stmt->execute();
    $jobId = $conn->insert_id;
    $stmt->close();

    // 2. Create each activity under that job
    $activityStmt = $conn->prepare(
        "INSERT INTO activity_instance (JobID, ActivityTypeID, LabourHours, DiagnosticResult)
         VALUES (?, ?, ?, ?)"
    );

    foreach ($activityTypeIds as $index => $activityTypeId) {
        $hours = $labourHours[$index] ?? null;
        $hours = ($hours === '' || $hours === null) ? null : $hours;
        $diagnosticResult = trim($diagnosticResults[$index] ?? '');

        $activityStmt->bind_param("iids", $jobId, $activityTypeId, $hours, $diagnosticResult);
        $activityStmt->execute();
    }
    $activityStmt->close();

    $conn->commit();
    log_action($conn, 'maintenance_job', (string) $jobId, "Opened maintenance job #{$jobId} for vehicle {$vehicleId} (" . count($activityTypeIds) . " activity/activities)");
    $conn->close();
    header("Location: manage_fleet.php?job_added=1&job_id=" . $jobId);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    show_error('Could not create maintenance job. Check that the vehicle, workshop, and activity types exist.');
}
