<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/layout.php';
requireRole('MECHANIC');

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function statusSlug(string $status): string
{
    return trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', trim($status))), '-');
}

$linkedId = $_SESSION['LinkedID'] ?? null;

$tasks = [];
$totalTasks = 0;
$openTasks = 0;
$closedTasks = 0;
$labourHoursThisMonth = 0.0;
$mechanicName = $_SESSION['FullName'] ?? 'Mechanic';

$conn = new mysqli('localhost', 'root', '', 'databruh_db');
if ($conn->connect_error) {
    http_response_code(503);
    die('Workshop data is temporarily unavailable. Please try again later.');
}
$conn->set_charset('utf8mb4');

if ($linkedId !== null) {
    $stmt = $conn->prepare(
        "SELECT mj.JobID, v.RegistrationNumber, at.ActivityTypeName, ai.LabourHours,
                ai.DiagnosticResult, mj.Status AS JobStatus, w.WorkshopName, mj.StartDate
         FROM activity_instance_worker_assigned aiwa
         JOIN activity_instance ai ON aiwa.ActivityID = ai.ActivityID
         JOIN activity_type at ON ai.ActivityTypeID = at.ActivityTypeID
         JOIN maintenance_job mj ON ai.JobID = mj.JobID
         JOIN vehicle v ON mj.VehicleID = v.VehicleID
         JOIN workshop w ON mj.WorkshopID = w.WorkshopID
         WHERE aiwa.MechanicID = ?
         ORDER BY mj.StartDate DESC"
    );
    $stmt->bind_param('s', $linkedId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
        $totalTasks++;
        if ($row['JobStatus'] === 'Closed') {
            $closedTasks++;
        } else {
            $openTasks++;
        }
        if ($row['StartDate'] && date('Y-m', strtotime($row['StartDate'])) === date('Y-m')) {
            $labourHoursThisMonth += (float) $row['LabourHours'];
        }
    }
    $stmt->close();
}

// Vehicle maintenance history lookup — available to every mechanic
// regardless of link status, since it's fleet reference data rather
// than a personal record.
$allVehicles = [];
$vehicleListResult = $conn->query(
    'SELECT VehicleID, RegistrationNumber, Manufacturer, Model FROM vehicle ORDER BY RegistrationNumber'
);
while ($row = $vehicleListResult->fetch_assoc()) {
    $allVehicles[] = $row;
}

$selectedVehicleId = trim((string) ($_GET['vehicle_id'] ?? ''));
$vehicleJobHistory = [];
$vehicleActivityHistory = [];

if ($selectedVehicleId !== '') {
    $jobStmt = $conn->prepare(
        'SELECT mj.JobID, w.WorkshopName, mj.StartDate, mj.EndDate, mj.Status, mj.ToTalCost,
                TIMESTAMPDIFF(HOUR, mj.StartDate, mj.EndDate) AS DowntimeHours
         FROM maintenance_job mj
         JOIN workshop w ON mj.WorkshopID = w.WorkshopID
         WHERE mj.VehicleID = ?
         ORDER BY mj.StartDate DESC'
    );
    $jobStmt->bind_param('s', $selectedVehicleId);
    $jobStmt->execute();
    $jobHistResult = $jobStmt->get_result();
    while ($row = $jobHistResult->fetch_assoc()) {
        $vehicleJobHistory[] = $row;
    }
    $jobStmt->close();

    $activityStmt = $conn->prepare(
        "SELECT ai.ActivityID, ai.JobID, at.ActivityTypeName, ai.LabourHours, ai.DiagnosticResult,
                GROUP_CONCAT(DISTINCT mw.FullName ORDER BY mw.FullName SEPARATOR ', ') AS AssignedMechanics,
                GROUP_CONCAT(DISTINCT CONCAT(p.PartName, ' (x', aipu.QuantityUsed, ')') SEPARATOR ', ') AS PartsUsed
         FROM activity_instance ai
         JOIN maintenance_job mj ON ai.JobID = mj.JobID
         JOIN activity_type at ON ai.ActivityTypeID = at.ActivityTypeID
         LEFT JOIN activity_instance_worker_assigned aiwa ON aiwa.ActivityID = ai.ActivityID
         LEFT JOIN mechanic_worker mw ON mw.MechanicID = aiwa.MechanicID
         LEFT JOIN activity_instance_part_used aipu ON aipu.ActivityID = ai.ActivityID
         LEFT JOIN part p ON aipu.PartID = p.PartID
         WHERE mj.VehicleID = ?
         GROUP BY ai.ActivityID, ai.JobID, at.ActivityTypeName, ai.LabourHours, ai.DiagnosticResult
         ORDER BY ai.ActivityID DESC"
    );
    $activityStmt->bind_param('s', $selectedVehicleId);
    $activityStmt->execute();
    $activityHistResult = $activityStmt->get_result();
    while ($row = $activityHistResult->fetch_assoc()) {
        $vehicleActivityHistory[] = $row;
    }
    $activityStmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Your assigned maintenance tasks, and full vehicle maintenance history lookup.">
    <title>Mechanic Dashboard - Databruh</title>
    <link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="../css_files/design_system.css">
    <link rel="stylesheet" href="../css_files/datavs.css">
    <link rel="stylesheet" href="../css_files/admin_page.css">
    <link rel="stylesheet" href="../css_files/role_dashboards.css">
    <link rel="stylesheet" href="../css_files/minimalist_theme.css">
    <link rel="stylesheet" href="../css_files/swiss_bento_theme.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to dashboard</a>
    <?php renderSiteNavigation('dashboard'); ?>

    <main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
        <section class="site-hero dashboard-hero" aria-labelledby="mechanic-dashboard-title">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="site-hero-content">
                <p class="eyebrow" data-hero-item>Mechanic · Assigned tasks</p>
                <h1 id="mechanic-dashboard-title" class="max-w-6xl" data-hero-item>
                    Your assigned
                    <br>diagnostic work.
                </h1>
                <p class="hero-copy" data-hero-item>
                    Read-only view of the maintenance activities assigned to you,
                    plus lookup of any vehicle's full maintenance history,
                    diagnostics, and previous repairs.
                </p>
                <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                    <div class="hero-feedback system-feedback" role="status" data-hero-item>
                        Successfully logged in as Mechanic.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($linkedId === null): ?>
            <section class="section-shell" style="padding: 4rem var(--page-gutter);">
                <div class="linked-notice">
                    <h2>Your account isn't linked to a mechanic record yet.</h2>
                    <p>
                        Contact an administrator to link <?php echo escape($mechanicName); ?>'s
                        account to a mechanic record so assigned tasks can appear here.
                    </p>
                </div>
            </section>
        <?php else: ?>
            <section id="dashboard-summary" class="dashboard-summary" aria-label="Dashboard summary">
                <div class="dashboard-metrics">
                    <div>
                        <span>Total assigned tasks</span>
                        <strong><?php echo $totalTasks; ?></strong>
                    </div>
                    <div>
                        <span>Open tasks</span>
                        <strong><?php echo $openTasks; ?></strong>
                    </div>
                    <div>
                        <span>Closed tasks</span>
                        <strong><?php echo $closedTasks; ?></strong>
                    </div>
                    <div>
                        <span>Labour hours this month</span>
                        <strong><?php echo number_format($labourHoursThisMonth, 1); ?></strong>
                    </div>
                </div>
            </section>

            <section class="admin-directory" aria-labelledby="tasks-title">
                <div class="section-shell">
                    <div class="chapter-heading">
                        <div>
                            <span class="section-kicker">Assigned tasks</span>
                            <h2 id="tasks-title">Every activity assigned to you.</h2>
                        </div>
                    </div>
                    <div class="admin-table-shell" data-reveal data-stack-card>
                        <table class="admin-table">
                            <caption class="sr-only">Your assigned maintenance activities</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Job</th>
                                    <th scope="col">Vehicle</th>
                                    <th scope="col">Workshop</th>
                                    <th scope="col">Activity</th>
                                    <th scope="col">Labour hours</th>
                                    <th scope="col">Diagnostic result</th>
                                    <th scope="col">Job status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($tasks): ?>
                                    <?php foreach ($tasks as $task): ?>
                                        <tr>
                                            <td>#<?php echo (int) $task['JobID']; ?></td>
                                            <td class="cell-strong"><?php echo escape($task['RegistrationNumber']); ?></td>
                                            <td><?php echo escape($task['WorkshopName']); ?></td>
                                            <td><?php echo escape($task['ActivityTypeName']); ?></td>
                                            <td><?php echo $task['LabourHours'] !== null ? number_format((float) $task['LabourHours'], 2) : '—'; ?></td>
                                            <td class="description-cell"><?php echo escape($task['DiagnosticResult'] ?? '—'); ?></td>
                                            <td>
                                                <span class="status-pill status-<?php echo statusSlug($task['JobStatus'] ?? ''); ?>">
                                                    <?php echo escape($task['JobStatus'] ?? 'Unknown'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="empty-row">No tasks assigned yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="admin-directory" aria-labelledby="vehicle-history-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Vehicle lookup</span>
                        <h2 id="vehicle-history-title">Maintenance history, diagnostics, and previous repairs.</h2>
                    </div>
                    <p>
                        Look up any vehicle in the fleet to see its full job
                        history, diagnostic results, and repairs — not just the
                        activities assigned to you.
                    </p>
                </div>

                <form method="GET" class="directory-toolbar" data-reveal data-stack-card>
                    <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
                        <div class="field-group">
                            <label for="vehicle-lookup">Vehicle</label>
                            <select id="vehicle-lookup" name="vehicle_id" required>
                                <option value="" disabled <?php echo $selectedVehicleId === '' ? 'selected' : ''; ?>>Select vehicle</option>
                                <?php foreach ($allVehicles as $v): ?>
                                    <option value="<?php echo escape($v['VehicleID']); ?>" <?php echo $selectedVehicleId === $v['VehicleID'] ? 'selected' : ''; ?>>
                                        <?php echo escape($v['RegistrationNumber']); ?>
                                        — <?php echo escape(trim(($v['Manufacturer'] ?? '') . ' ' . ($v['Model'] ?? ''))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-search">Look up</button>
                    </div>
                </form>

                <?php if ($selectedVehicleId !== ''): ?>
                    <div class="admin-table-shell" data-reveal data-stack-card>
                        <table class="admin-table">
                            <caption class="sr-only">Job history for the selected vehicle</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Job</th>
                                    <th scope="col">Workshop</th>
                                    <th scope="col">Started</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Downtime</th>
                                    <th scope="col">Cost (VND)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($vehicleJobHistory): ?>
                                    <?php foreach ($vehicleJobHistory as $job): ?>
                                        <tr>
                                            <td>#<?php echo (int) $job['JobID']; ?></td>
                                            <td class="cell-strong"><?php echo escape($job['WorkshopName']); ?></td>
                                            <td><?php echo escape($job['StartDate']); ?></td>
                                            <td>
                                                <span class="status-pill status-<?php echo statusSlug($job['Status'] ?? ''); ?>">
                                                    <?php echo escape($job['Status'] ?? 'Unknown'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $job['DowntimeHours'] !== null ? (int) $job['DowntimeHours'] . 'h' : '—'; ?></td>
                                            <td><?php echo $job['ToTalCost'] !== null ? number_format((int) $job['ToTalCost']) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="empty-row">No maintenance jobs recorded for this vehicle.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="admin-table-shell" data-reveal data-stack-card style="margin-top:1.5rem;">
                        <table class="admin-table">
                            <caption class="sr-only">Diagnostic and repair records for the selected vehicle</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Job</th>
                                    <th scope="col">Activity</th>
                                    <th scope="col">Mechanics</th>
                                    <th scope="col">Hours</th>
                                    <th scope="col">Diagnostic result</th>
                                    <th scope="col">Parts used</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($vehicleActivityHistory): ?>
                                    <?php foreach ($vehicleActivityHistory as $activity): ?>
                                        <tr>
                                            <td>#<?php echo (int) $activity['JobID']; ?></td>
                                            <td class="cell-strong"><?php echo escape($activity['ActivityTypeName']); ?></td>
                                            <td><?php echo escape($activity['AssignedMechanics'] ?? '—'); ?></td>
                                            <td><?php echo $activity['LabourHours'] !== null ? number_format((float) $activity['LabourHours'], 2) : '—'; ?></td>
                                            <td class="description-cell"><?php echo escape($activity['DiagnosticResult'] ?? '—'); ?></td>
                                            <td class="description-cell"><?php echo escape($activity['PartsUsed'] ?? '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="empty-row">No diagnostic or repair records for this vehicle.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php renderSiteFooter('dashboard'); ?>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
