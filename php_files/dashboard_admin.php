<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/layout.php';
requireRole('ADMIN');

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function statusSlug(string $status): string
{
    return strtolower(str_replace(' ', '-', trim($status)));
}

$conn = new mysqli('localhost', 'root', '', 'databruh_db');
if ($conn->connect_error) {
    http_response_code(503);
    die('Fleet data is temporarily unavailable. Please try again later.');
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
    }
}

$totalDrivers = (int) $conn->query('SELECT COUNT(*) AS c FROM driver')->fetch_assoc()['c'];
$totalVehicles = (int) $conn->query('SELECT COUNT(*) AS c FROM vehicle')->fetch_assoc()['c'];
$openAlerts = (int) $conn->query(
    "SELECT COUNT(*) AS c FROM alert WHERE Status IN ('New', 'Escalated')"
)->fetch_assoc()['c'];
$activeJobs = (int) $conn->query(
    "SELECT COUNT(*) AS c FROM maintenance_job WHERE Status <> 'Closed'"
)->fetch_assoc()['c'];

$vehicleStatusBreakdown = [];
$vehicleStatusResult = $conn->query(
    'SELECT vs.StatusName, COUNT(*) AS EventCount
     FROM vehicle v
     JOIN vehicle_status vs ON v.StatusID = vs.StatusID
     GROUP BY vs.StatusName
     ORDER BY EventCount DESC'
);
while ($row = $vehicleStatusResult->fetch_assoc()) {
    $vehicleStatusBreakdown[] = $row;
}

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
    'SELECT mj.JobID, v.RegistrationNumber, w.WorkshopName, mj.StartDate, mj.EndDate, mj.Status, mj.ToTalCost
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
    'SELECT p.PartName, pc.PartnerName AS Supplier, spl.CostPerUnit
     FROM part p
     JOIN partner_company pc ON p.PrimarySupplierID = pc.PartnerID
     LEFT JOIN supplier_product_list spl ON p.PartID = spl.PartID AND p.PrimarySupplierID = spl.PartnerID
     ORDER BY p.PartName'
);
while ($row = $partsResult->fetch_assoc()) {
    $parts[] = $row;
}

$eventTypeLabels = [];
$eventTypeValues = [];
$eventTypeResult = $conn->query(
    'SELECT EventType, COUNT(*) AS EventCount
     FROM view_driver_incidents
     GROUP BY EventType
     ORDER BY EventCount DESC'
);
while ($row = $eventTypeResult->fetch_assoc()) {
    $eventTypeLabels[] = $row['EventType'];
    $eventTypeValues[] = (int) $row['EventCount'];
}

$severityLabels = [];
$severityValues = [];
$severityResult = $conn->query(
    "SELECT SeverityLevel, COUNT(*) AS EventCount
     FROM view_driver_incidents
     GROUP BY SeverityLevel
     ORDER BY FIELD(SeverityLevel, 'Low', 'Medium', 'High', 'Critical')"
);
while ($row = $severityResult->fetch_assoc()) {
    $severityLabels[] = $row['SeverityLevel'];
    $severityValues[] = (int) $row['EventCount'];
}

$depotLabels = [];
$depotValues = [];
$depotResult = $conn->query(
    "SELECT DepotName, COUNT(*) AS EventCount
     FROM view_driver_incidents
     WHERE DepotName IS NOT NULL
     GROUP BY DepotName
     ORDER BY EventCount DESC"
);
while ($row = $depotResult->fetch_assoc()) {
    $depotLabels[] = $row['DepotName'];
    $depotValues[] = (int) $row['EventCount'];
}

$scoreAnomalies = [];
$anomalyResult = $conn->query('SELECT * FROM view_driver_score_anomalies');
while ($row = $anomalyResult->fetch_assoc()) {
    $scoreAnomalies[] = $row;
}

// Monthly score per driver, keyed to a shared axis of every (Year, Month) that appears for any driver.
$driverScoresRaw = [];
$scoreMonthKeys = [];
$scoreResult = $conn->query(
    'SELECT d.FullName, msl.Month, msl.Year, msl.Score
     FROM monthly_score_log msl
     JOIN driver d ON msl.DriverID = d.DriverID
     ORDER BY d.FullName, msl.Year, msl.Month'
);
while ($row = $scoreResult->fetch_assoc()) {
    $monthKey = sprintf('%04d-%02d', (int) $row['Year'], (int) $row['Month']);
    $scoreMonthKeys[$monthKey] = true;
    $driverScoresRaw[$row['FullName']][$monthKey] = (int) $row['Score'];
}
$scoreMonthKeys = array_keys($scoreMonthKeys);
sort($scoreMonthKeys);

$scoreChartLabels = array_map(
    static fn (string $key): string => substr($key, 5, 2) . '/' . substr($key, 0, 4),
    $scoreMonthKeys
);
$driverScoreSeries = [];
foreach ($driverScoresRaw as $driverName => $scoresByMonth) {
    $series = [];
    foreach ($scoreMonthKeys as $monthKey) {
        $series[] = $scoresByMonth[$monthKey] ?? null;
    }
    $driverScoreSeries[] = ['label' => $driverName, 'data' => $series];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Full-visibility operations hub for Databruh administrators — query and manage every domain.">
    <title>Administrator Dashboard - Databruh</title>
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
        <section class="site-hero dashboard-hero" aria-labelledby="admin-dashboard-title">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="site-hero-content">
                <p class="eyebrow" data-hero-item>Administrator · Full fleet visibility</p>
                <h1 id="admin-dashboard-title" class="max-w-6xl" data-hero-item>
                    Every domain,
                    <br>one operating picture.
                </h1>
                <p class="hero-copy" data-hero-item>
                    Query every domain — safety, vehicles, alerts, and maintenance —
                    and manage workshop operations directly from one dashboard.
                </p>
                <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                    <div class="hero-feedback system-feedback" role="status" data-hero-item>
                        Successfully logged in as Administrator.
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
                    <span>Total drivers</span>
                    <strong><?php echo $totalDrivers; ?></strong>
                </div>
                <div>
                    <span>Total vehicles</span>
                    <strong><?php echo $totalVehicles; ?></strong>
                </div>
                <div>
                    <span>Open alerts</span>
                    <strong><?php echo $openAlerts; ?></strong>
                </div>
                <div>
                    <span>Active maintenance jobs</span>
                    <strong><?php echo $activeJobs; ?></strong>
                </div>
            </div>
        </section>

        <section id="safety-visuals" class="dashboard-analysis" data-chart-section aria-labelledby="admin-safety-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Safety visualisations</span>
                        <h2 id="admin-safety-title">Driver behaviour across the network.</h2>
                    </div>
                </div>
                <div class="dashboard-bento">
                    <article class="chart-card chart-card-type" data-stack-card>
                        <div class="chart-heading">
                            <div>
                                <span>Telematics mix</span>
                                <h3>Behaviour events</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="eventTypeChart" role="img" aria-label="Bar chart of incident counts by event type."></canvas>
                        </div>
                    </article>
                    <article class="chart-card chart-card-severity" data-stack-card>
                        <div class="chart-heading">
                            <div>
                                <span>Response priority</span>
                                <h3>Events by severity</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="severityChart" role="img" aria-label="Doughnut chart of incidents grouped by severity."></canvas>
                        </div>
                    </article>
                    <article class="chart-card chart-card-depot" data-stack-card>
                        <div class="chart-heading">
                            <div>
                                <span>Network comparison</span>
                                <h3>Depot exposure</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="depotChart" role="img" aria-label="Bar chart of incidents by depot."></canvas>
                        </div>
                    </article>
                    <article class="chart-card chart-card-score" data-stack-card>
                        <div class="chart-heading">
                            <div>
                                <span>Assignment readiness</span>
                                <h3>Monthly driver score</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="scoreChart" role="img" aria-label="Line chart showing each driver's monthly safety score trend."></canvas>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="anomaly-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Statistical anomaly detection</span>
                        <h2 id="anomaly-title">Score drops vs. each driver's own baseline.</h2>
                    </div>
                    <p>
                        Each driver's monthly score is compared against their own
                        historical mean and standard deviation (Z-score), not a
                        fleet-wide threshold. Flags a driver only when this month
                        is a statistical outlier for <em>them</em>.
                    </p>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Driver monthly score anomalies</caption>
                        <thead>
                            <tr>
                                <th scope="col">Driver</th>
                                <th scope="col">Month</th>
                                <th scope="col">Score</th>
                                <th scope="col">Driver average</th>
                                <th scope="col">Z-score</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($scoreAnomalies): ?>
                                <?php foreach ($scoreAnomalies as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['DriverName']); ?></td>
                                        <td><?php echo str_pad((string) $row['Month'], 2, '0', STR_PAD_LEFT) . '/' . $row['Year']; ?></td>
                                        <td><?php echo (int) $row['Score']; ?></td>
                                        <td><?php echo $row['DriverAvgScore'] !== null ? escape((string) $row['DriverAvgScore']) : '—'; ?></td>
                                        <td><?php echo $row['ZScore'] !== null ? escape((string) $row['ZScore']) : '—'; ?></td>
                                        <td>
                                            <span class="status-pill status-<?php echo statusSlug($row['AnomalyStatus']); ?>">
                                                <?php echo escape($row['AnomalyStatus']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="empty-row">No monthly scores recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="fleet-status-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Vehicle status</span>
                        <h2 id="fleet-status-title">Where every vehicle stands right now.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Vehicle count by status</caption>
                        <thead>
                            <tr>
                                <th scope="col">Status</th>
                                <th scope="col">Vehicles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($vehicleStatusBreakdown): ?>
                                <?php foreach ($vehicleStatusBreakdown as $row): ?>
                                    <tr>
                                        <td><?php echo escape($row['StatusName']); ?></td>
                                        <td><?php echo (int) $row['EventCount']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="empty-row">No vehicles recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                                <tr><td colspan="6" class="empty-row">No maintenance jobs recorded.</td></tr>
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
                        <h2 id="mechanic-workload-title">Mechanic workload by workshop.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
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
                        <h2 id="parts-title">Primary supplier and unit cost.</h2>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($parts): ?>
                                <?php foreach ($parts as $part): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($part['PartName']); ?></td>
                                        <td><?php echo escape($part['Supplier']); ?></td>
                                        <td><?php echo $part['CostPerUnit'] !== null ? number_format((int) $part['CostPerUnit']) : '—'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="empty-row">No parts recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="site-cta" aria-labelledby="admin-cta-title">
            <div>
                <h2 id="admin-cta-title">Need account governance or driver/vehicle detail?</h2>
                <p>Manage identities and roles, or open the full driver safety dashboard.</p>
            </div>
            <div class="hero-actions">
                <a class="button button-dark" href="./admin_page.php">Manage accounts</a>
                <a class="button button-outline" href="./datavs.php">Safety dashboard</a>
            </div>
        </section>
    </main>

    <?php renderSiteFooter('dashboard'); ?>
    <script>
        const eventTypeLabels = <?php echo json_encode($eventTypeLabels); ?>;
        const eventTypeValues = <?php echo json_encode($eventTypeValues); ?>;
        const severityLabels = <?php echo json_encode($severityLabels); ?>;
        const severityValues = <?php echo json_encode($severityValues); ?>;
        const depotLabels = <?php echo json_encode($depotLabels); ?>;
        const depotValues = <?php echo json_encode($depotValues); ?>;
        const scoreChartLabels = <?php echo json_encode($scoreChartLabels); ?>;
        const driverScoreSeries = <?php echo json_encode($driverScoreSeries); ?>;
        const scoreColors = ['#111d26', '#b83d29', '#285f77', '#42695e', '#a97221'];

        Chart.defaults.color = '#58636b';
        Chart.defaults.font.family = "'Geist', 'Avenir Next', sans-serif";
        Chart.defaults.borderColor = 'rgba(17, 29, 38, 0.1)';

        new Chart(document.getElementById('eventTypeChart'), {
            type: 'bar',
            data: {
                labels: eventTypeLabels,
                datasets: [{
                    label: 'Events',
                    data: eventTypeValues,
                    backgroundColor: '#285f77',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        new Chart(document.getElementById('severityChart'), {
            type: 'doughnut',
            data: {
                labels: severityLabels,
                datasets: [{
                    data: severityValues,
                    backgroundColor: ['#42695e', '#a97221', '#b83d29', '#742a23']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        new Chart(document.getElementById('depotChart'), {
            type: 'bar',
            data: {
                labels: depotLabels,
                datasets: [{
                    label: 'Events',
                    data: depotValues,
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

        new Chart(document.getElementById('scoreChart'), {
            type: 'line',
            data: {
                labels: scoreChartLabels,
                datasets: driverScoreSeries.map((series, index) => ({
                    label: series.label,
                    data: series.data,
                    borderColor: scoreColors[index % scoreColors.length],
                    backgroundColor: 'transparent',
                    spanGaps: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, boxHeight: 10, padding: 14 }
                    }
                },
                scales: { y: { min: 0, max: 100 } }
            }
        });
    </script>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
