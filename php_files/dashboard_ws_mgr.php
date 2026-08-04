<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/layout.php';
requireRole('WS_MGR');

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function statusSlug(string $status): string
{
    return trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', trim($status))), '-');
}

$conn = new mysqli('localhost', 'root', '', 'databruh_db');
if ($conn->connect_error) {
    http_response_code(503);
    die('Workshop data is temporarily unavailable. Please try again later.');
}
$conn->set_charset('utf8mb4');

$message = '';
$messageIsError = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_alert_status') {
        $alertId = (int) ($_POST['alert_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));
        $allowedStatuses = ['New', 'Escalated', 'Resolved'];

        if ($alertId <= 0 || !in_array($status, $allowedStatuses, true)) {
            $message = 'Invalid alert status update.';
            $messageIsError = true;
        } else {
            $stmt = $conn->prepare('UPDATE alert SET Status = ? WHERE AlertID = ?');
            $stmt->bind_param('si', $status, $alertId);
            $message = $stmt->execute() ? 'Alert status updated.' : 'Could not update alert status.';
            $stmt->close();
        }
    } elseif ($action === 'create_job_from_alert') {
        $alertId = (int) ($_POST['alert_id'] ?? 0);
        $workshopId = (int) ($_POST['workshop_id'] ?? 0);
        $startDate = trim((string) ($_POST['start_date'] ?? ''));

        if ($alertId <= 0 || $workshopId <= 0 || $startDate === '') {
            $message = 'Workshop and start date are required to open a job.';
            $messageIsError = true;
        } else {
            $alertStmt = $conn->prepare('SELECT VehicleID FROM alert WHERE AlertID = ?');
            $alertStmt->bind_param('i', $alertId);
            $alertStmt->execute();
            $alertRow = $alertStmt->get_result()->fetch_assoc();
            $alertStmt->close();

            if (!$alertRow) {
                $message = 'Alert not found.';
                $messageIsError = true;
            } else {
                $vehicleId = $alertRow['VehicleID'];
                $jobStmt = $conn->prepare(
                    "INSERT INTO maintenance_job (VehicleID, WorkshopID, StartDate, Status, AlertID)
                     VALUES (?, ?, ?, 'Open', ?)"
                );
                $jobStmt->bind_param('sisi', $vehicleId, $workshopId, $startDate, $alertId);

                if ($jobStmt->execute()) {
                    $escalate = $conn->prepare("UPDATE alert SET Status = 'Escalated' WHERE AlertID = ?");
                    $escalate->bind_param('i', $alertId);
                    $escalate->execute();
                    $escalate->close();
                    $message = 'Maintenance job created from alert.';
                } else {
                    $message = 'Could not create the maintenance job.';
                    $messageIsError = true;
                }
                $jobStmt->close();
            }
        }
    } elseif ($action === 'close_job') {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $totalCost = (int) ($_POST['total_cost'] ?? 0);

        if ($jobId <= 0 || $endDate === '') {
            $message = 'An end date is required to close a job.';
            $messageIsError = true;
        } else {
            $stmt = $conn->prepare(
                "UPDATE maintenance_job SET EndDate = ?, ToTalCost = ?, Status = 'Closed' WHERE JobID = ?"
            );
            $stmt->bind_param('sii', $endDate, $totalCost, $jobId);
            $message = $stmt->execute() ? 'Job closed.' : 'Could not close the job.';
            $stmt->close();
        }
    } elseif ($action === 'add_job_activity') {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $activityTypeId = (int) ($_POST['activity_type_id'] ?? 0);
        $mechanicId = trim((string) ($_POST['mechanic_id'] ?? ''));
        $labourHoursRaw = trim((string) ($_POST['labour_hours'] ?? ''));
        $diagnosticResult = trim((string) ($_POST['diagnostic_result'] ?? ''));

        if ($jobId <= 0 || $activityTypeId <= 0 || $mechanicId === '') {
            $message = 'Job, activity type, and mechanic are required.';
            $messageIsError = true;
        } else {
            $labourHours = $labourHoursRaw !== '' ? (float) $labourHoursRaw : null;
            $stmt = $conn->prepare(
                'INSERT INTO activity_instance (JobID, ActivityTypeID, LabourHours, DiagnosticResult) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('iids', $jobId, $activityTypeId, $labourHours, $diagnosticResult);

            if ($stmt->execute()) {
                $activityId = $stmt->insert_id;
                $stmt->close();
                $assignStmt = $conn->prepare(
                    'INSERT INTO activity_instance_worker_assigned (ActivityID, MechanicID) VALUES (?, ?)'
                );
                $assignStmt->bind_param('is', $activityId, $mechanicId);
                $message = $assignStmt->execute()
                    ? 'Activity added and mechanic assigned.'
                    : 'Activity created, but mechanic assignment failed.';
                $messageIsError = !$assignStmt->affected_rows;
                $assignStmt->close();
            } else {
                $message = 'Could not create the activity.';
                $messageIsError = true;
                $stmt->close();
            }
        }
    } elseif ($action === 'record_parts_usage') {
        $activityId = (int) ($_POST['activity_id'] ?? 0);
        $partId = (int) ($_POST['part_id'] ?? 0);
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $quantityUsed = (int) ($_POST['quantity_used'] ?? 0);

        if ($activityId <= 0 || $partId <= 0 || $supplierId <= 0 || $quantityUsed <= 0) {
            $message = 'Activity, part, supplier, and a positive quantity are required.';
            $messageIsError = true;
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO activity_instance_part_used (ActivityID, PartID, QuantityUsed, SupplierID)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE QuantityUsed = QuantityUsed + VALUES(QuantityUsed), SupplierID = VALUES(SupplierID)'
            );
            $stmt->bind_param('iiii', $activityId, $partId, $quantityUsed, $supplierId);

            if ($stmt->execute()) {
                $stockStmt = $conn->prepare(
                    'UPDATE part SET QuantityOnHand = GREATEST(QuantityOnHand - ?, 0) WHERE PartID = ?'
                );
                $stockStmt->bind_param('ii', $quantityUsed, $partId);
                $stockStmt->execute();
                $stockStmt->close();
                $message = 'Parts usage recorded.';
            } else {
                $message = 'Could not record parts usage.';
                $messageIsError = true;
            }
            $stmt->close();
        }
    }
}

$openAlerts = (int) $conn->query(
    "SELECT COUNT(*) AS c FROM alert WHERE Status IN ('New', 'Escalated')"
)->fetch_assoc()['c'];
$activeJobs = (int) $conn->query(
    "SELECT COUNT(*) AS c FROM maintenance_job WHERE Status <> 'Closed'"
)->fetch_assoc()['c'];
$vehiclesInMaintenance = (int) $conn->query(
    "SELECT COUNT(*) AS c FROM vehicle v
     JOIN vehicle_status vs ON v.StatusID = vs.StatusID
     WHERE vs.StatusName = 'Under Maintenance'"
)->fetch_assoc()['c'];
$mechanicsOnStaff = (int) $conn->query('SELECT COUNT(*) AS c FROM mechanic_worker')->fetch_assoc()['c'];

$workshops = [];
$workshopResult = $conn->query('SELECT WorkshopID, WorkshopName FROM workshop ORDER BY WorkshopName');
while ($row = $workshopResult->fetch_assoc()) {
    $workshops[] = $row;
}

$alerts = [];
$alertResult = $conn->query(
    'SELECT a.AlertID, a.AlertName, a.AlertDescription, a.AlertTimestamp, a.Status,
            v.RegistrationNumber, mj.JobID
     FROM alert a
     JOIN vehicle v ON a.VehicleID = v.VehicleID
     LEFT JOIN maintenance_job mj ON mj.AlertID = a.AlertID
     ORDER BY a.AlertTimestamp DESC'
);
while ($row = $alertResult->fetch_assoc()) {
    $alerts[] = $row;
}

$jobs = [];
$jobResult = $conn->query(
    'SELECT mj.JobID, v.RegistrationNumber, w.WorkshopName, mj.StartDate, mj.EndDate, mj.Status, mj.ToTalCost,
            TIMESTAMPDIFF(HOUR, mj.StartDate, mj.EndDate) AS DowntimeHours
     FROM maintenance_job mj
     JOIN vehicle v ON mj.VehicleID = v.VehicleID
     JOIN workshop w ON mj.WorkshopID = w.WorkshopID
     ORDER BY mj.StartDate DESC'
);
while ($row = $jobResult->fetch_assoc()) {
    $jobs[] = $row;
}

$mechanicWorkload = [];
$mechanicResult = $conn->query(
    "SELECT m.MechanicID, m.FullName, w.WorkshopName,
            COUNT(DISTINCT CASE WHEN mj.Status <> 'Closed' THEN ai.ActivityID END) AS OpenActivities
     FROM mechanic_worker m
     LEFT JOIN workshop w ON m.WorkshopID = w.WorkshopID
     LEFT JOIN activity_instance_worker_assigned aiwa ON m.MechanicID = aiwa.MechanicID
     LEFT JOIN activity_instance ai ON aiwa.ActivityID = ai.ActivityID
     LEFT JOIN maintenance_job mj ON ai.JobID = mj.JobID
     GROUP BY m.MechanicID, m.FullName, w.WorkshopName
     ORDER BY m.FullName"
);
while ($row = $mechanicResult->fetch_assoc()) {
    $mechanicWorkload[] = $row;
}

$parts = [];
$partsResult = $conn->query(
    'SELECT p.PartID, p.PartName, pc.PartnerName AS Supplier, spl.CostPerUnit, p.QuantityOnHand, p.ReorderThreshold
     FROM part p
     JOIN partner_company pc ON p.PrimarySupplierID = pc.PartnerID
     LEFT JOIN supplier_product_list spl ON p.PartID = spl.PartID AND p.PrimarySupplierID = spl.PartnerID
     ORDER BY p.PartName'
);
while ($row = $partsResult->fetch_assoc()) {
    $parts[] = $row;
}

$suppliers = [];
$supplierResult = $conn->query('SELECT PartnerID, PartnerName FROM partner_company ORDER BY PartnerName');
while ($row = $supplierResult->fetch_assoc()) {
    $suppliers[] = $row;
}

$activityTypes = [];
$activityTypeResult = $conn->query('SELECT ActivityTypeID, ActivityTypeName FROM activity_type ORDER BY ActivityTypeName');
while ($row = $activityTypeResult->fetch_assoc()) {
    $activityTypes[] = $row;
}

$allMechanics = [];
$allMechanicResult = $conn->query('SELECT MechanicID, FullName FROM mechanic_worker ORDER BY FullName');
while ($row = $allMechanicResult->fetch_assoc()) {
    $allMechanics[] = $row;
}

$openJobs = [];
$openJobResult = $conn->query(
    "SELECT mj.JobID, v.RegistrationNumber
     FROM maintenance_job mj
     JOIN vehicle v ON mj.VehicleID = v.VehicleID
     WHERE mj.Status <> 'Closed'
     ORDER BY mj.JobID DESC"
);
while ($row = $openJobResult->fetch_assoc()) {
    $openJobs[] = $row;
}

$jobActivities = [];
$jobActivityResult = $conn->query(
    "SELECT ai.ActivityID, ai.JobID, at.ActivityTypeName, ai.LabourHours, ai.DiagnosticResult,
            mj.Status AS JobStatus, v.RegistrationNumber,
            GROUP_CONCAT(DISTINCT mw.FullName ORDER BY mw.FullName SEPARATOR ', ') AS AssignedMechanics,
            COUNT(DISTINCT aipu.PartID) AS PartsUsedCount
     FROM activity_instance ai
     JOIN maintenance_job mj ON ai.JobID = mj.JobID
     JOIN vehicle v ON mj.VehicleID = v.VehicleID
     JOIN activity_type at ON ai.ActivityTypeID = at.ActivityTypeID
     LEFT JOIN activity_instance_worker_assigned aiwa ON aiwa.ActivityID = ai.ActivityID
     LEFT JOIN mechanic_worker mw ON mw.MechanicID = aiwa.MechanicID
     LEFT JOIN activity_instance_part_used aipu ON aipu.ActivityID = ai.ActivityID
     GROUP BY ai.ActivityID, ai.JobID, at.ActivityTypeName, ai.LabourHours, ai.DiagnosticResult, mj.Status, v.RegistrationNumber
     ORDER BY ai.ActivityID DESC"
);
while ($row = $jobActivityResult->fetch_assoc()) {
    $jobActivities[] = $row;
}

$urgentRepairVehicles = [];
$urgentResult = $conn->query('SELECT * FROM view_urgent_repair_vehicles');
while ($row = $urgentResult->fetch_assoc()) {
    $urgentRepairVehicles[] = $row;
}
$urgentRepairVehicleCount = count(array_unique(array_column($urgentRepairVehicles, 'VehicleID')));

$awaitingInspection = [];
$inspectionResult = $conn->query('SELECT * FROM view_vehicles_awaiting_inspection');
while ($row = $inspectionResult->fetch_assoc()) {
    $awaitingInspection[] = $row;
}

$workshopWorkload = [];
$workloadResult = $conn->query('SELECT * FROM view_workshop_workload');
while ($row = $workloadResult->fetch_assoc()) {
    $workshopWorkload[] = $row;
}

$maintenanceCostByModel = [];
$costResult = $conn->query('SELECT * FROM view_maintenance_cost_by_model');
while ($row = $costResult->fetch_assoc()) {
    $maintenanceCostByModel[] = $row;
}

$overdueVehicles = [];
$overdueResult = $conn->query('SELECT * FROM view_vehicles_overdue_for_service');
while ($row = $overdueResult->fetch_assoc()) {
    $overdueVehicles[] = $row;
}

$repeatedFailures = [];
$repeatedResult = $conn->query('SELECT * FROM view_repeated_component_failures');
while ($row = $repeatedResult->fetch_assoc()) {
    $repeatedFailures[] = $row;
}

$partsBelowReorder = [];
$reorderResult = $conn->query('SELECT * FROM view_parts_below_reorder');
while ($row = $reorderResult->fetch_assoc()) {
    $partsBelowReorder[] = $row;
}

$supplierPerformance = [];
$supplierPerfResult = $conn->query('SELECT * FROM view_supplier_performance');
while ($row = $supplierPerfResult->fetch_assoc()) {
    $supplierPerformance[] = $row;
}

$mechanicCertifications = [];
$certResult = $conn->query(
    "SELECT * FROM view_mechanic_certifications ORDER BY QualificationStatus DESC, ExpiryDate"
);
while ($row = $certResult->fetch_assoc()) {
    $mechanicCertifications[] = $row;
}

$costByModelLabels = [];
$costByModelValues = [];
foreach ($maintenanceCostByModel as $row) {
    $costByModelLabels[] = trim(($row['Manufacturer'] ?? '') . ' ' . ($row['Model'] ?? ''));
    $costByModelValues[] = (int) $row['TotalCostVND'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Manage workshop alerts, maintenance jobs, and mechanic workload.">
    <title>Workshop Manager Dashboard - Databruh</title>
    <link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="../css_files/design_system.css">
    <link rel="stylesheet" href="../css_files/datavs.css">
    <link rel="stylesheet" href="../css_files/admin_page.css">
    <link rel="stylesheet" href="../css_files/role_dashboards.css">
    <link rel="stylesheet" href="../css_files/minimalist_theme.css">
    <link rel="stylesheet" href="../css_files/swiss_bento_theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
    <script>
        function confirmSelectChange(selectElement, label) {
            const optionText = selectElement.options[selectElement.selectedIndex].text;
            if (confirm(`Set ${label} to "${optionText}"?`)) {
                selectElement.form.submit();
            } else {
                selectElement.value = selectElement.getAttribute('data-original');
            }
        }

        function storeOriginalSelectValue(selectElement) {
            selectElement.setAttribute('data-original', selectElement.value);
        }
    </script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to dashboard</a>
    <?php renderSiteNavigation('dashboard'); ?>

    <main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
        <section class="site-hero dashboard-hero" aria-labelledby="ws-dashboard-title">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="site-hero-content">
                <p class="eyebrow" data-hero-item>Workshop manager · Maintenance and workshop</p>
                <h1 id="ws-dashboard-title" class="max-w-6xl" data-hero-item>
                    Alerts into
                    <br>planned, costed jobs.
                </h1>
                <p class="hero-copy" data-hero-item>
                    Prioritise alerts, plan workshop capacity, and track cost and
                    downtime from one workspace.
                </p>
                <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                    <div class="hero-feedback system-feedback" role="status" data-hero-item>
                        Successfully logged in as Workshop Manager.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($message !== ''): ?>
            <div class="section-shell" style="padding-top: 2rem;">
                <div class="system-feedback<?php echo $messageIsError ? ' is-error' : ''; ?>" role="<?php echo $messageIsError ? 'alert' : 'status'; ?>">
                    <?php echo escape($message); ?>
                </div>
            </div>
        <?php endif; ?>

        <section id="dashboard-summary" class="dashboard-summary" aria-label="Dashboard summary">
            <div class="dashboard-metrics">
                <div>
                    <span>Open alerts</span>
                    <strong><?php echo $openAlerts; ?></strong>
                </div>
                <div>
                    <span>Active maintenance jobs</span>
                    <strong><?php echo $activeJobs; ?></strong>
                </div>
                <div>
                    <span>Vehicles in maintenance</span>
                    <strong><?php echo $vehiclesInMaintenance; ?></strong>
                </div>
                <div>
                    <span>Mechanics on staff</span>
                    <strong><?php echo $mechanicsOnStaff; ?></strong>
                </div>
                <div>
                    <span>Vehicles needing urgent repair</span>
                    <strong><?php echo $urgentRepairVehicleCount; ?></strong>
                </div>
                <div>
                    <span>Parts below reorder threshold</span>
                    <strong><?php echo count($partsBelowReorder); ?></strong>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="alerts-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Predictive alerts</span>
                        <h2 id="alerts-title">Prioritise and escalate into jobs.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Alerts and their status</caption>
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Alert</th>
                                <th scope="col">Raised</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($alerts): ?>
                                <?php foreach ($alerts as $alert): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($alert['RegistrationNumber']); ?></td>
                                        <td>
                                            <?php echo escape($alert['AlertName']); ?>
                                            <div class="description-cell"><?php echo escape($alert['AlertDescription'] ?? ''); ?></div>
                                        </td>
                                        <td><?php echo escape($alert['AlertTimestamp']); ?></td>
                                        <td>
                                            <form method="POST" class="inline-form role-form">
                                                <input type="hidden" name="action" value="update_alert_status">
                                                <input type="hidden" name="alert_id" value="<?php echo (int) $alert['AlertID']; ?>">
                                                <label class="sr-only" for="alert-status-<?php echo (int) $alert['AlertID']; ?>">
                                                    Status for alert <?php echo (int) $alert['AlertID']; ?>
                                                </label>
                                                <select
                                                    id="alert-status-<?php echo (int) $alert['AlertID']; ?>"
                                                    name="status"
                                                    onfocus="storeOriginalSelectValue(this)"
                                                    onchange="confirmSelectChange(this, 'alert status')"
                                                >
                                                    <?php foreach (['New', 'Escalated', 'Resolved'] as $status): ?>
                                                        <option value="<?php echo escape($status); ?>" <?php echo $alert['Status'] === $status ? 'selected' : ''; ?>>
                                                            <?php echo escape($status); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <?php if ($alert['JobID']): ?>
                                                <span class="status-pill status-open">Job #<?php echo (int) $alert['JobID']; ?></span>
                                            <?php else: ?>
                                                <form method="POST" class="cell-actions">
                                                    <input type="hidden" name="action" value="create_job_from_alert">
                                                    <input type="hidden" name="alert_id" value="<?php echo (int) $alert['AlertID']; ?>">
                                                    <div class="inline-form">
                                                        <label class="sr-only" for="job-workshop-<?php echo (int) $alert['AlertID']; ?>">Workshop</label>
                                                        <select id="job-workshop-<?php echo (int) $alert['AlertID']; ?>" name="workshop_id" required>
                                                            <option value="" disabled selected>Workshop</option>
                                                            <?php foreach ($workshops as $w): ?>
                                                                <option value="<?php echo (int) $w['WorkshopID']; ?>"><?php echo escape($w['WorkshopName']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                    <button type="submit" class="btn btn-search">Create job</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-row">No alerts recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="urgent-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Urgent attention</span>
                        <h2 id="urgent-title">Vehicles needing urgent repair, and vehicles awaiting inspection.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Vehicles requiring urgent repair</caption>
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Category</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Status</th>
                                <th scope="col">Open alert</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($urgentRepairVehicles): ?>
                                <?php foreach ($urgentRepairVehicles as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['VehiclePlate']); ?></td>
                                        <td><?php echo escape($row['VehicleCategory'] ?? '—'); ?></td>
                                        <td><?php echo escape($row['DepotName'] ?? '—'); ?></td>
                                        <td>
                                            <span class="status-pill status-<?php echo statusSlug($row['VehicleStatus'] ?? ''); ?>">
                                                <?php echo escape($row['VehicleStatus'] ?? 'Unknown'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $row['AlertName'] !== null ? escape($row['AlertName']) : '—'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-row">No vehicles currently need urgent repair.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card style="margin-top:1.5rem;">
                    <table class="admin-table">
                        <caption class="sr-only">Vehicles awaiting inspection</caption>
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Category</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Odometer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($awaitingInspection): ?>
                                <?php foreach ($awaitingInspection as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['VehiclePlate']); ?></td>
                                        <td><?php echo escape($row['VehicleCategory'] ?? '—'); ?></td>
                                        <td><?php echo escape($row['DepotName'] ?? '—'); ?></td>
                                        <td><?php echo number_format((int) $row['CurrentOdometer']); ?> km</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="empty-row">No vehicles awaiting inspection.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="jobs-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Maintenance jobs</span>
                        <h2 id="jobs-title">Track status, downtime, and cost.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Maintenance jobs</caption>
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Workshop</th>
                                <th scope="col">Started</th>
                                <th scope="col">Status</th>
                                <th scope="col">Downtime</th>
                                <th scope="col">Cost (VND)</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($jobs): ?>
                                <?php foreach ($jobs as $job): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($job['RegistrationNumber']); ?></td>
                                        <td><?php echo escape($job['WorkshopName']); ?></td>
                                        <td><?php echo escape($job['StartDate']); ?></td>
                                        <td>
                                            <span class="status-pill status-<?php echo statusSlug($job['Status'] ?? ''); ?>">
                                                <?php echo escape($job['Status'] ?? 'Unknown'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $job['DowntimeHours'] !== null ? (int) $job['DowntimeHours'] . 'h' : '—'; ?></td>
                                        <td><?php echo $job['ToTalCost'] !== null ? number_format((int) $job['ToTalCost']) : '—'; ?></td>
                                        <td>
                                            <?php if ($job['Status'] !== 'Closed'): ?>
                                                <form method="POST" class="cell-actions">
                                                    <input type="hidden" name="action" value="close_job">
                                                    <input type="hidden" name="job_id" value="<?php echo (int) $job['JobID']; ?>">
                                                    <div class="inline-form">
                                                        <label class="sr-only" for="close-end-<?php echo (int) $job['JobID']; ?>">End date</label>
                                                        <input type="date" id="close-end-<?php echo (int) $job['JobID']; ?>" name="end_date" value="<?php echo date('Y-m-d'); ?>" required>
                                                        <label class="sr-only" for="close-cost-<?php echo (int) $job['JobID']; ?>">Total cost</label>
                                                        <input type="number" id="close-cost-<?php echo (int) $job['JobID']; ?>" name="total_cost" min="0" placeholder="Cost (VND)" required>
                                                    </div>
                                                    <button type="submit" class="btn btn-search">Close job</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="empty-row">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="empty-row">No maintenance jobs recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="activities-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Job activities</span>
                        <h2 id="activities-title">Allocate mechanics, and record parts usage.</h2>
                    </div>
                    <p>
                        Add an activity to an open job and assign a mechanic. Check the
                        "Mechanic certifications" table further down before assigning
                        work that needs a specific licence.
                    </p>
                </div>

                <?php if ($openJobs): ?>
                    <form method="POST" class="directory-toolbar" data-reveal data-stack-card>
                        <input type="hidden" name="action" value="add_job_activity">
                        <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
                            <div class="field-group">
                                <label for="activity-job">Job</label>
                                <select id="activity-job" name="job_id" required>
                                    <option value="" disabled selected>Select job</option>
                                    <?php foreach ($openJobs as $job): ?>
                                        <option value="<?php echo (int) $job['JobID']; ?>">#<?php echo (int) $job['JobID']; ?> — <?php echo escape($job['RegistrationNumber']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="activity-type">Activity type</label>
                                <select id="activity-type" name="activity_type_id" required>
                                    <option value="" disabled selected>Select activity</option>
                                    <?php foreach ($activityTypes as $type): ?>
                                        <option value="<?php echo (int) $type['ActivityTypeID']; ?>"><?php echo escape($type['ActivityTypeName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="activity-mechanic">Mechanic</label>
                                <select id="activity-mechanic" name="mechanic_id" required>
                                    <option value="" disabled selected>Select mechanic</option>
                                    <?php foreach ($allMechanics as $mechanic): ?>
                                        <option value="<?php echo escape($mechanic['MechanicID']); ?>"><?php echo escape($mechanic['FullName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="activity-hours">Est. hours</label>
                                <input type="number" id="activity-hours" name="labour_hours" min="0" step="0.25" placeholder="Hours">
                            </div>
                            <div class="field-group">
                                <label for="activity-diagnostic">Diagnostic note</label>
                                <input type="text" id="activity-diagnostic" name="diagnostic_result" placeholder="Optional">
                            </div>
                            <button type="submit" class="btn btn-search">Add activity</button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Job activities and parts usage</caption>
                        <thead>
                            <tr>
                                <th scope="col">Job</th>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Activity</th>
                                <th scope="col">Mechanics</th>
                                <th scope="col">Hours</th>
                                <th scope="col">Parts used</th>
                                <th scope="col">Record parts usage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($jobActivities): ?>
                                <?php foreach ($jobActivities as $activity): ?>
                                    <tr>
                                        <td>
                                            #<?php echo (int) $activity['JobID']; ?>
                                            <span class="status-pill status-<?php echo statusSlug($activity['JobStatus'] ?? ''); ?>">
                                                <?php echo escape($activity['JobStatus'] ?? 'Unknown'); ?>
                                            </span>
                                        </td>
                                        <td class="cell-strong"><?php echo escape($activity['RegistrationNumber']); ?></td>
                                        <td><?php echo escape($activity['ActivityTypeName']); ?></td>
                                        <td><?php echo escape($activity['AssignedMechanics'] ?? '—'); ?></td>
                                        <td><?php echo $activity['LabourHours'] !== null ? number_format((float) $activity['LabourHours'], 2) : '—'; ?></td>
                                        <td><?php echo (int) $activity['PartsUsedCount']; ?></td>
                                        <td class="cell-actions">
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="action" value="record_parts_usage">
                                                <input type="hidden" name="activity_id" value="<?php echo (int) $activity['ActivityID']; ?>">
                                                <label class="sr-only" for="usage-part-<?php echo (int) $activity['ActivityID']; ?>">Part</label>
                                                <select id="usage-part-<?php echo (int) $activity['ActivityID']; ?>" name="part_id" required>
                                                    <option value="" disabled selected>Part</option>
                                                    <?php foreach ($parts as $part): ?>
                                                        <option value="<?php echo (int) $part['PartID']; ?>"><?php echo escape($part['PartName']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label class="sr-only" for="usage-supplier-<?php echo (int) $activity['ActivityID']; ?>">Supplier</label>
                                                <select id="usage-supplier-<?php echo (int) $activity['ActivityID']; ?>" name="supplier_id" required>
                                                    <option value="" disabled selected>Supplier</option>
                                                    <?php foreach ($suppliers as $supplier): ?>
                                                        <option value="<?php echo (int) $supplier['PartnerID']; ?>"><?php echo escape($supplier['PartnerName']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="number" name="quantity_used" min="1" placeholder="Qty" required style="width:5rem;">
                                                <button type="submit" class="btn btn-search">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="empty-row">No job activities recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="mechanic-workload-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Workforce</span>
                        <h2 id="mechanic-workload-title">Workshop and mechanic workload.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Workshop workload</caption>
                        <thead>
                            <tr>
                                <th scope="col">Workshop</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Open jobs</th>
                                <th scope="col">Closed jobs</th>
                                <th scope="col">Mechanics on staff</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($workshopWorkload): ?>
                                <?php foreach ($workshopWorkload as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['WorkshopName']); ?></td>
                                        <td><?php echo escape($row['DepotName'] ?? '—'); ?></td>
                                        <td><?php echo (int) $row['OpenJobs']; ?></td>
                                        <td><?php echo (int) $row['ClosedJobs']; ?></td>
                                        <td><?php echo (int) $row['MechanicsOnStaff']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-row">No workshops recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card style="margin-top:1.5rem;">
                    <table class="admin-table">
                        <caption class="sr-only">Mechanic workload</caption>
                        <thead>
                            <tr>
                                <th scope="col">Mechanic</th>
                                <th scope="col">Workshop</th>
                                <th scope="col">Open activities</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($mechanicWorkload): ?>
                                <?php foreach ($mechanicWorkload as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['FullName']); ?></td>
                                        <td><?php echo escape($row['WorkshopName'] ?? '—'); ?></td>
                                        <td><?php echo (int) $row['OpenActivities']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="empty-row">No mechanics recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="parts-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Parts and suppliers</span>
                        <h2 id="parts-title">Stock, primary supplier, and unit cost.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Parts and suppliers</caption>
                        <thead>
                            <tr>
                                <th scope="col">Part</th>
                                <th scope="col">Primary supplier</th>
                                <th scope="col">Cost per unit (VND)</th>
                                <th scope="col">On hand</th>
                                <th scope="col">Reorder threshold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($parts): ?>
                                <?php foreach ($parts as $part): ?>
                                    <?php $lowStock = (int) $part['QuantityOnHand'] <= (int) $part['ReorderThreshold']; ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($part['PartName']); ?></td>
                                        <td><?php echo escape($part['Supplier']); ?></td>
                                        <td><?php echo $part['CostPerUnit'] !== null ? number_format((int) $part['CostPerUnit']) : '—'; ?></td>
                                        <td class="<?php echo $lowStock ? 'sev-Critical' : ''; ?>">
                                            <?php echo (int) $part['QuantityOnHand']; ?><?php if ($lowStock): ?> (reorder)<?php endif; ?>
                                        </td>
                                        <td><?php echo (int) $part['ReorderThreshold']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-row">No parts recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="supplier-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Supplier performance</span>
                        <h2 id="supplier-title">Units supplied and warranty claim history.</h2>
                    </div>
                    <p>
                        Units supplied is attributed to whichever supplier actually
                        fulfilled each recorded parts usage, so this stays accurate
                        even after a part's primary supplier changes.
                    </p>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Supplier performance</caption>
                        <thead>
                            <tr>
                                <th scope="col">Supplier</th>
                                <th scope="col">Type</th>
                                <th scope="col">Lead time</th>
                                <th scope="col">Units supplied</th>
                                <th scope="col">Warranty claims</th>
                                <th scope="col">Open claims</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($supplierPerformance): ?>
                                <?php foreach ($supplierPerformance as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['PartnerName']); ?></td>
                                        <td><?php echo escape($row['PartnerType'] ?? '—'); ?></td>
                                        <td><?php echo escape($row['DeliveryLeadTimes'] ?? '—'); ?></td>
                                        <td><?php echo $row['TotalUnitsSupplied'] !== null ? (int) $row['TotalUnitsSupplied'] : '—'; ?></td>
                                        <td><?php echo (int) $row['WarrantyClaims']; ?></td>
                                        <td><?php echo (int) $row['OpenWarrantyClaims']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="empty-row">No suppliers recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="safety-visuals" class="dashboard-analysis" data-chart-section aria-labelledby="cost-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Cost comparison</span>
                        <h2 id="cost-title">Maintenance cost by vehicle model.</h2>
                    </div>
                </div>
                <article class="chart-card" data-stack-card style="min-height: 22rem;">
                    <div class="chart-heading">
                        <div>
                            <span>Closed jobs only</span>
                            <h3>Total cost by model</h3>
                        </div>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="costByModelChart" role="img" aria-label="Bar chart of total maintenance cost by vehicle model."></canvas>
                    </div>
                </article>
                <div class="admin-table-shell" data-reveal data-stack-card style="margin-top:1.5rem;">
                    <table class="admin-table">
                        <caption class="sr-only">Maintenance cost by vehicle model</caption>
                        <thead>
                            <tr>
                                <th scope="col">Manufacturer</th>
                                <th scope="col">Model</th>
                                <th scope="col">Closed jobs</th>
                                <th scope="col">Total cost (VND)</th>
                                <th scope="col">Avg cost/job (VND)</th>
                                <th scope="col">Avg downtime</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($maintenanceCostByModel): ?>
                                <?php foreach ($maintenanceCostByModel as $row): ?>
                                    <tr>
                                        <td><?php echo escape($row['Manufacturer'] ?? '—'); ?></td>
                                        <td class="cell-strong"><?php echo escape($row['Model'] ?? '—'); ?></td>
                                        <td><?php echo (int) $row['ClosedJobs']; ?></td>
                                        <td><?php echo number_format((int) $row['TotalCostVND']); ?></td>
                                        <td><?php echo number_format((int) $row['AvgCostPerJobVND']); ?></td>
                                        <td><?php echo escape((string) $row['AvgDowntimeHours']); ?>h</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="empty-row">No closed jobs recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="overdue-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Service compliance</span>
                        <h2 id="overdue-title">Overdue for service, and repeated component failures.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Vehicles overdue for service</caption>
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Category</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Last service</th>
                                <th scope="col">Days since service</th>
                                <th scope="col">Interval</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($overdueVehicles): ?>
                                <?php foreach ($overdueVehicles as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['VehiclePlate']); ?></td>
                                        <td><?php echo escape($row['VehicleCategory'] ?? '—'); ?></td>
                                        <td><?php echo escape($row['DepotName'] ?? '—'); ?></td>
                                        <td><?php echo $row['LastServiceDate'] !== null ? escape((string) $row['LastServiceDate']) : 'Never serviced'; ?></td>
                                        <td class="sev-Critical"><?php echo (int) $row['DaysSinceService']; ?> days</td>
                                        <td><?php echo (int) $row['IntervalDays']; ?> days</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="empty-row">No vehicles overdue for service.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card style="margin-top:1.5rem;">
                    <table class="admin-table">
                        <caption class="sr-only">Vehicles with repeated component failures</caption>
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Activity type</th>
                                <th scope="col">Occurrences</th>
                                <th scope="col">Most recent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($repeatedFailures): ?>
                                <?php foreach ($repeatedFailures as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['VehiclePlate']); ?></td>
                                        <td><?php echo escape($row['ActivityTypeName']); ?></td>
                                        <td class="sev-Critical"><?php echo (int) $row['OccurrenceCount']; ?></td>
                                        <td><?php echo escape((string) $row['MostRecentOccurrence']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="empty-row">No vehicles with repeated component failures.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="certs-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Workforce compliance</span>
                        <h2 id="certs-title">Mechanic certifications.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Mechanic certifications</caption>
                        <thead>
                            <tr>
                                <th scope="col">Mechanic</th>
                                <th scope="col">Workshop</th>
                                <th scope="col">Certification</th>
                                <th scope="col">Expires</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($mechanicCertifications): ?>
                                <?php foreach ($mechanicCertifications as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['MechanicName']); ?></td>
                                        <td><?php echo escape($row['WorkshopName'] ?? '—'); ?></td>
                                        <td><?php echo escape($row['QualificationName']); ?></td>
                                        <td><?php echo escape((string) $row['ExpiryDate']); ?></td>
                                        <td>
                                            <span class="status-pill status-<?php echo statusSlug($row['QualificationStatus']); ?>">
                                                <?php echo escape($row['QualificationStatus']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-row">No mechanic certifications recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <?php renderSiteFooter('dashboard'); ?>
    <script>
        const costByModelLabels = <?php echo json_encode($costByModelLabels); ?>;
        const costByModelValues = <?php echo json_encode($costByModelValues); ?>;

        Chart.defaults.color = '#58636b';
        Chart.defaults.font.family = "'Geist', 'Avenir Next', sans-serif";
        Chart.defaults.borderColor = 'rgba(17, 29, 38, 0.1)';

        new Chart(document.getElementById('costByModelChart'), {
            type: 'bar',
            data: {
                labels: costByModelLabels,
                datasets: [{
                    label: 'Total cost (VND)',
                    data: costByModelValues,
                    backgroundColor: '#285f77',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } }
            }
        });
    </script>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
