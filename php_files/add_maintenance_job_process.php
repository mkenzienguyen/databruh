<?php
/**
 * Creates a new maintenance job with one or more activities in one submission.
 *
 * Expected POST fields:
 *   vehicle_id, workshop_id, start_date   (job-level)
 *   activity_type_id[]        - array, one entry per activity row
 *   activity_description[]    - array, matching index
 *   activity_cost[]           - optional array, matching index
 *                                (falls back to activity_type.CostEstimate if left blank)
 * Job is created with Status = 'Open' and no EndDate - use
 * close_maintenance_job_process.php later to close it out.
 */

require_once __DIR__ . '/db_connect_fleet.php';

function show_error(string $message): void
{
    echo "<p>" . htmlspecialchars($message) . "</p>";
    echo "<p><a href='javascript:history.back()'>Back</a></p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    show_error('Invalid request.');
}

$vehicleId  = $_POST['vehicle_id']  ?? null;
$workshopId = $_POST['workshop_id'] ?? null;
$startDate  = $_POST['start_date']  ?? '';

$activityTypeIds = $_POST['activity_type_id']     ?? [];
$activityDescs   = $_POST['activity_description'] ?? [];
$activityCosts   = $_POST['activity_cost']         ?? [];

if (!$vehicleId || !$workshopId || $startDate === '') {
    show_error('Vehicle, workshop, and start date are required.');
}

if (empty($activityTypeIds)) {
    show_error('At least one activity is required for the job.');
}

$conn->begin_transaction();

try {
    // 1. Create the job itself
    $stmt = $conn->prepare(
        "INSERT INTO maintenance_job (VehicleID, WorkshopID, StartDate, Status)
         VALUES (?, ?, ?, 'Open')"
    );
    $stmt->bind_param("iis", $vehicleId, $workshopId, $startDate);
    $stmt->execute();
    $jobId = $conn->insert_id;
    $stmt->close();

    // 2. Create each activity under that job
    $activityStmt = $conn->prepare(
        "INSERT INTO activity_issued (JobID, ActivityTypeID, Cost, Description)
         VALUES (?, ?, ?, ?)"
    );
    $estimateStmt = $conn->prepare(
        "SELECT CostEstimate FROM activity_type WHERE ActivityTypeID = ?"
    );

    foreach ($activityTypeIds as $index => $activityTypeId) {
        $description = trim($activityDescs[$index] ?? '');
        $cost = $activityCosts[$index] ?? '';

        if ($cost === '') {
            $estimateStmt->bind_param("i", $activityTypeId);
            $estimateStmt->execute();
            $estimateRow = $estimateStmt->get_result()->fetch_assoc();
            $cost = $estimateRow['CostEstimate'] ?? 0;
        }

        $activityStmt->bind_param("iids", $jobId, $activityTypeId, $cost, $description);
        $activityStmt->execute();
    }

    $activityStmt->close();
    $estimateStmt->close();

    $conn->commit();
    $conn->close();
    header("Location: home_page.php?job_added=1&job_id=" . $jobId);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    show_error('Could not create maintenance job. Check that the vehicle, workshop, and activity types exist.');
}
