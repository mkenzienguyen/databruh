<?php
/**
 * Workshop actions:
 *   POST workshop_process.php?action=add_job     - create maintenance job + activities
 *   POST workshop_process.php?action=close_job   - close a job (shows downtime report)
 *   POST workshop_process.php?action=delete_job  - delete an Open (draft) job
 *   POST workshop_process.php?action=add_part    - add a part type
 *   POST workshop_process.php?action=delete_part - delete an unused part
 */
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db_connect_fleet.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/helpers.php';

function workshop_add_job(mysqli $conn): void
{
    $vehicleId  = trim($_POST['vehicle_id'] ?? '');
    $workshopId = $_POST['workshop_id'] ?? null;
    $startDate  = trim($_POST['start_date'] ?? '');
    $alertId    = $_POST['alert_id'] ?? null;
    $alertId    = ($alertId === '') ? null : $alertId;
    $totalCost  = $_POST['total_cost'] ?? null;
    $totalCost  = ($totalCost === '') ? null : $totalCost;

    $activityTypeIds   = $_POST['activity_type_id'] ?? [];
    $labourHours       = $_POST['labour_hours'] ?? [];
    $diagnosticResults = $_POST['diagnostic_result'] ?? [];

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
            $activityStmt->bind_param("idds", $jobId, $activityTypeId, $hours, $diagnosticResult);
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
}

function workshop_close_job(mysqli $conn): void
{
    $jobId     = $_POST['job_id'] ?? null;
    $endDate   = trim($_POST['end_date'] ?? '');
    $totalCost = $_POST['total_cost'] ?? null;

    if (!$jobId || $endDate === '' || $totalCost === null || $totalCost === '') {
        show_error('Job, end date, and total cost are required.');
    }

    $stmt = $conn->prepare(
        "UPDATE maintenance_job SET EndDate = ?, Status = 'Closed', TotalCost = ? WHERE JobID = ?"
    );
    $stmt->bind_param("sii", $endDate, $totalCost, $jobId);
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        show_error('Could not close job.');
    }
    $stmt->close();

    // Fetch dates to compute downtime in hours
    $dateStmt = $conn->prepare("SELECT StartDate, EndDate FROM maintenance_job WHERE JobID = ?");
    $dateStmt->bind_param("i", $jobId);
    $dateStmt->execute();
    $dates = $dateStmt->get_result()->fetch_assoc();
    $dateStmt->close();

    $downtimeHours = round((strtotime($dates['EndDate']) - strtotime($dates['StartDate'])) / 3600, 1);

    log_action($conn, 'maintenance_job', (string) $jobId, "Closed maintenance job #{$jobId} (downtime {$downtimeHours} hour(s))", 'UPDATE');
    $conn->close();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><title>Job Closed</title></head>
    <body>
    <h1>Job #<?php echo (int) $jobId; ?> closed</h1>
    <p>Downtime: <?php echo $downtimeHours; ?> hour(s)</p>
    <p>Total cost: <?php echo number_format((float) $totalCost, 0); ?> VND</p>
    <p><a href="manage_fleet.php">Back to Manage Fleet</a></p>
    </body>
    </html>
    <?php
    exit;
}

function workshop_delete_job(mysqli $conn): void
{
    $jobId = $_POST['job_id'] ?? null;
    if (!$jobId) {
        show_error('A job is required.');
    }

    $checkStmt = $conn->prepare("SELECT Status FROM maintenance_job WHERE JobID = ?");
    $checkStmt->bind_param("i", $jobId);
    $checkStmt->execute();
    $job = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$job) {
        show_error('Job not found.');
    }
    if ($job['Status'] === 'Closed') {
        show_error('This job has already been closed and is part of the historical record - it cannot be deleted.');
    }

    $stmt = $conn->prepare("DELETE FROM maintenance_job WHERE JobID = ?");
    $stmt->bind_param("i", $jobId);
    if ($stmt->execute()) {
        $stmt->close();
        log_action($conn, 'maintenance_job', (string) $jobId, "Deleted draft maintenance job #{$jobId}", 'DELETE');
        $conn->close();
        header("Location: manage_fleet.php?job_deleted=1");
        exit;
    }
    $stmt->close();
    $conn->close();
    show_error('Could not delete job.');
}

function workshop_add_part(mysqli $conn): void
{
    $partName          = trim($_POST['part_name'] ?? '');
    $primarySupplierId = $_POST['primary_supplier_id'] ?? null;
    $backupSupplierId  = $_POST['backup_supplier_id'] ?? null;
    $backupSupplierId  = ($backupSupplierId === '') ? null : $backupSupplierId;

    if ($partName === '' || !$primarySupplierId) {
        show_error('Part name and a primary supplier are required.');
    }

    $stmt = $conn->prepare(
        "INSERT INTO part (PartName, PrimarySupplierID, BackupSupplierID) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("sii", $partName, $primarySupplierId, $backupSupplierId);
    if ($stmt->execute()) {
        $partId = $conn->insert_id;
        $stmt->close();
        log_action($conn, 'part', (string) $partId, "Added part \"{$partName}\" (PartID {$partId})");
        $conn->close();
        header("Location: manage_fleet.php?part_added=1");
        exit;
    }
    $stmt->close();
    $conn->close();
    show_error('Could not add part - check that the supplier(s) exist.');
}

function workshop_delete_part(mysqli $conn): void
{
    $partId = $_POST['part_id'] ?? null;
    if (!$partId) {
        show_error('A part is required.');
    }

    $checkStmt = $conn->prepare(
        "SELECT (SELECT COUNT(*) FROM activity_instance_part_used WHERE PartID = ?) +
                (SELECT COUNT(*) FROM warranty_part_list WHERE PartID = ?) AS Uses"
    );
    $checkStmt->bind_param("ii", $partId, $partId);
    $checkStmt->execute();
    $uses = (int) $checkStmt->get_result()->fetch_assoc()['Uses'];
    $checkStmt->close();
    if ($uses > 0) {
        $conn->close();
        show_error('This part is already in use (on a maintenance activity or warranty claim) and cannot be deleted.');
    }

    $stmt = $conn->prepare("DELETE FROM part WHERE PartID = ?");
    $stmt->bind_param("i", $partId);
    if ($stmt->execute()) {
        $stmt->close();
        log_action($conn, 'part', (string) $partId, "Deleted part #{$partId}", 'DELETE');
        $conn->close();
        header("Location: manage_fleet.php?part_deleted=1");
        exit;
    }
    $stmt->close();
    $conn->close();
    show_error('Could not delete part.');
}

require_post();
require_role(ROLES_WORKSHOP);

switch ($_GET['action'] ?? $_POST['action'] ?? '') {
    case 'add_job':
        workshop_add_job($conn);
        break;
    case 'close_job':
        workshop_close_job($conn);
        break;
    case 'delete_job':
        workshop_delete_job($conn);
        break;
    case 'add_part':
        workshop_add_part($conn);
        break;
    case 'delete_part':
        workshop_delete_part($conn);
        break;
    default:
        show_error('Unknown workshop action.');
}