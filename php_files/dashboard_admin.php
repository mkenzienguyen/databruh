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

$driverDirectory = [];
$driverDirResult = $conn->query(
    'SELECT d.DriverID, d.FullName, dl.DepotName, d.EmploymentStatus, d.LicenseExpiration
     FROM driver d
     LEFT JOIN depot_location dl ON d.DepotID = dl.DepotID
     ORDER BY d.FullName'
);
while ($row = $driverDirResult->fetch_assoc()) {
    $driverDirectory[] = $row;
}

$mechanicDirectory = [];
$mechanicDirResult = $conn->query(
    'SELECT m.MechanicID, m.FullName, w.WorkshopName, m.EmploymentStatus
     FROM mechanic_worker m
     LEFT JOIN workshop w ON m.WorkshopID = w.WorkshopID
     ORDER BY m.FullName'
);
while ($row = $mechanicDirResult->fetch_assoc()) {
    $mechanicDirectory[] = $row;
}

$criticalIncidentsCount = 0;
$criticalIndex = array_search('Critical', $severityLabels, true);
if ($criticalIndex !== false) {
    $criticalIncidentsCount = $severityValues[$criticalIndex];
}

$flaggedAnomalyCount = 0;
foreach ($scoreAnomalies as $anomalyRow) {
    if (in_array($anomalyRow['AnomalyStatus'], ['Critical anomaly', 'Notable anomaly'], true)) {
        $flaggedAnomalyCount++;
    }
}

$totalOpenActivities = 0;
foreach ($mechanicWorkload as $workloadRow) {
    $totalOpenActivities += (int) $workloadRow['OpenActivities'];
}
$totalMechanics = count($mechanicDirectory);

$conn->close();

$fleetManagerAccounts = [];
$workshopManagerAccounts = [];

$accountsConn = new mysqli('localhost', 'root', '', 'databruh_password_db');
if ($accountsConn->connect_error) {
    http_response_code(503);
    die('Account data is temporarily unavailable. Please try again later.');
}
$accountsConn->set_charset('utf8mb4');

$managerAccountsResult = $accountsConn->query(
    "SELECT FullName, Email, CreatedAt, TypeID
     FROM account
     WHERE TypeID IN ('FLEET_MGR', 'WS_MGR')
     ORDER BY FullName"
);
while ($row = $managerAccountsResult->fetch_assoc()) {
    if ($row['TypeID'] === 'FLEET_MGR') {
        $fleetManagerAccounts[] = $row;
    } else {
        $workshopManagerAccounts[] = $row;
    }
}
$accountsConn->close();

$fleetManagerCount = count($fleetManagerAccounts);
$workshopManagerCount = count($workshopManagerAccounts);
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
    <link rel="stylesheet" href="../css_files/admin_sidebar.css">
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
        <div class="dashboard-page-head">
            <p class="eyebrow">Administrator · Full fleet visibility</p>
            <h1 id="admin-dashboard-title">Every domain, one operating picture.</h1>
            <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                <div class="system-feedback" role="status">
                    Successfully logged in as Administrator.
                </div>
            <?php endif; ?>
        </div>

        <?php if ($message !== ''): ?>
            <div class="section-shell" style="padding-top: 2rem;">
                <div class="system-feedback<?php echo $messageIsError ? ' is-error' : ''; ?>" role="<?php echo $messageIsError ? 'alert' : 'status'; ?>">
                    <?php echo escape($message); ?>
                </div>
            </div>
        <?php endif; ?>

        <section id="admin-workspace" class="admin-workspace" aria-label="Administrator workspace">
            <div class="admin-workspace-shell">
                <nav class="admin-sidebar" aria-label="Dashboard sections">
                    <div class="admin-sidebar-head">
                        <span class="admin-sidebar-eyebrow">Workspace</span>
                        <strong>Admin control</strong>
                    </div>
                    <ul class="admin-sidebar-nav" role="tablist">
                        <li>
                            <button type="button" class="admin-sidebar-link is-active" data-panel-target="panel-dashboard" role="tab" aria-selected="true" aria-controls="panel-dashboard" id="tab-dashboard">
                                <span class="admin-sidebar-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                                </span>
                                <span>Dashboard</span>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="admin-sidebar-link" data-panel-target="panel-drivers" role="tab" aria-selected="false" aria-controls="panel-drivers" id="tab-drivers">
                                <span class="admin-sidebar-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2.1"/><line x1="12" y1="4" x2="12" y2="9.6"/><line x1="6.3" y1="15.8" x2="9.8" y2="13.6"/><line x1="17.7" y1="15.8" x2="14.2" y2="13.6"/></svg>
                                </span>
                                <span>Drivers</span>
                                <span class="admin-sidebar-count"><?php echo $totalDrivers; ?></span>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="admin-sidebar-link" data-panel-target="panel-mechanics" role="tab" aria-selected="false" aria-controls="panel-mechanics" id="tab-mechanics">
                                <span class="admin-sidebar-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.8 2.8-2-2z"/></svg>
                                </span>
                                <span>Mechanics</span>
                                <span class="admin-sidebar-count"><?php echo $totalMechanics; ?></span>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="admin-sidebar-link" data-panel-target="panel-fleet-managers" role="tab" aria-selected="false" aria-controls="panel-fleet-managers" id="tab-fleet-managers">
                                <span class="admin-sidebar-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="7" width="13" height="9" rx="1"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>
                                </span>
                                <span>Fleet managers</span>
                                <span class="admin-sidebar-count"><?php echo $fleetManagerCount; ?></span>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="admin-sidebar-link" data-panel-target="panel-workshop-managers" role="tab" aria-selected="false" aria-controls="panel-workshop-managers" id="tab-workshop-managers">
                                <span class="admin-sidebar-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V9l9-5 9 5v12"/><path d="M3 21h18"/><path d="M9 21v-6h6v6"/></svg>
                                </span>
                                <span>Workshop managers</span>
                                <span class="admin-sidebar-count"><?php echo $workshopManagerCount; ?></span>
                            </button>
                        </li>
                    </ul>
                </nav>

                <div class="admin-panels">
                    <section id="panel-dashboard" class="admin-panel" role="tabpanel" aria-labelledby="tab-dashboard">
                        <div class="admin-panel-head">
                            <span>Overview</span>
                            <h2>Every domain, at a glance.</h2>
                            <p>Headline counts across drivers, vehicles, alerts, and maintenance, plus current fleet readiness.</p>
                        </div>

                        <div class="admin-panel-block">
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
                        </div>

                        <div class="admin-panel-block">
                            <div class="admin-panel-block-head">
                                <span>Fleet readiness</span>
                                <h3>Where every vehicle stands right now.</h3>
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

                    <section id="panel-drivers" class="admin-panel" role="tabpanel" aria-labelledby="tab-drivers" hidden>
                        <div class="admin-panel-head">
                            <span>Drivers</span>
                            <h2>Driver behaviour across the network.</h2>
                            <p>Telematics events, severity, depot exposure, and monthly safety scores — with each driver flagged only against their own baseline.</p>
                        </div>

                        <div class="admin-panel-block">
                            <div class="dashboard-metrics">
                                <div>
                                    <span>Total drivers</span>
                                    <strong><?php echo $totalDrivers; ?></strong>
                                </div>
                                <div>
                                    <span>Critical incidents</span>
                                    <strong><?php echo $criticalIncidentsCount; ?></strong>
                                </div>
                                <div>
                                    <span>Flagged score anomalies</span>
                                    <strong><?php echo $flaggedAnomalyCount; ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="admin-panel-block" data-chart-section>
                            <div class="admin-panel-block-head">
                                <span>Safety visualisations</span>
                                <h3>Behaviour events, severity, depot, and score trend.</h3>
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

                        <div class="admin-panel-block">
                            <div class="admin-panel-block-head">
                                <span>Statistical anomaly detection</span>
                                <h3>Score drops vs. each driver's own baseline.</h3>
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

                        <div class="admin-panel-block">
                            <div class="admin-panel-block-head">
                                <span>Directory</span>
                                <h3>Every driver on record.</h3>
                            </div>
                            <div class="admin-table-shell" data-reveal data-stack-card>
                                <table class="admin-table">
                                    <caption class="sr-only">Driver directory</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Driver</th>
                                            <th scope="col">Depot</th>
                                            <th scope="col">License expiration</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($driverDirectory): ?>
                                            <?php foreach ($driverDirectory as $row): ?>
                                                <tr>
                                                    <td class="cell-strong"><?php echo escape($row['FullName']); ?></td>
                                                    <td><?php echo escape($row['DepotName'] ?? '—'); ?></td>
                                                    <td><?php echo escape($row['LicenseExpiration']); ?></td>
                                                    <td>
                                                        <span class="status-pill status-<?php echo statusSlug($row['EmploymentStatus'] ?? ''); ?>">
                                                            <?php echo escape($row['EmploymentStatus'] ?? 'Unknown'); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="empty-row">No drivers recorded.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section id="panel-mechanics" class="admin-panel" role="tabpanel" aria-labelledby="tab-mechanics" hidden>
                        <div class="admin-panel-head">
                            <span>Mechanics</span>
                            <h2>Workforce and workload by workshop.</h2>
                            <p>Who's carrying open work right now, and the full mechanic roster behind it.</p>
                        </div>

                        <div class="admin-panel-block">
                            <div class="dashboard-metrics">
                                <div>
                                    <span>Total mechanics</span>
                                    <strong><?php echo $totalMechanics; ?></strong>
                                </div>
                                <div>
                                    <span>Open activities</span>
                                    <strong><?php echo $totalOpenActivities; ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="admin-panel-block">
                            <div class="admin-panel-block-head">
                                <span>Workforce</span>
                                <h3>Mechanic workload by workshop.</h3>
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

                        <div class="admin-panel-block">
                            <div class="admin-panel-block-head">
                                <span>Directory</span>
                                <h3>Every mechanic on record.</h3>
                            </div>
                            <div class="admin-table-shell" data-reveal data-stack-card>
                                <table class="admin-table">
                                    <caption class="sr-only">Mechanic directory</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Mechanic</th>
                                            <th scope="col">Workshop</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($mechanicDirectory): ?>
                                            <?php foreach ($mechanicDirectory as $row): ?>
                                                <tr>
                                                    <td class="cell-strong"><?php echo escape($row['FullName']); ?></td>
                                                    <td><?php echo escape($row['WorkshopName'] ?? '—'); ?></td>
                                                    <td>
                                                        <span class="status-pill status-<?php echo statusSlug($row['EmploymentStatus'] ?? ''); ?>">
                                                            <?php echo escape($row['EmploymentStatus'] ?? 'Unknown'); ?>
                                                        </span>
                                                    </td>
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

                    <section id="panel-fleet-managers" class="admin-panel" role="tabpanel" aria-labelledby="tab-fleet-managers" hidden>
                        <div class="admin-panel-head">
                            <span>Fleet managers</span>
                            <h2>Who owns driver and vehicle safety.</h2>
                            <p>Fleet manager accounts, alongside the fleet-wide safety picture they're responsible for.</p>
                        </div>

                        <div class="admin-panel-block">
                            <div class="dashboard-metrics">
                                <div>
                                    <span>Fleet manager accounts</span>
                                    <strong><?php echo $fleetManagerCount; ?></strong>
                                </div>
                                <div>
                                    <span>Drivers managed</span>
                                    <strong><?php echo $totalDrivers; ?></strong>
                                </div>
                                <div>
                                    <span>Vehicles managed</span>
                                    <strong><?php echo $totalVehicles; ?></strong>
                                </div>
                                <div>
                                    <span>Flagged score anomalies</span>
                                    <strong><?php echo $flaggedAnomalyCount; ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="admin-panel-block">
                            <div class="admin-panel-block-head">
                                <span>Directory</span>
                                <h3>Fleet manager accounts.</h3>
                            </div>
                            <div class="admin-table-shell" data-reveal data-stack-card>
                                <table class="admin-table">
                                    <caption class="sr-only">Fleet manager accounts</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Name</th>
                                            <th scope="col">Email</th>
                                            <th scope="col">Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($fleetManagerAccounts): ?>
                                            <?php foreach ($fleetManagerAccounts as $row): ?>
                                                <tr>
                                                    <td class="cell-strong"><?php echo escape($row['FullName']); ?></td>
                                                    <td><?php echo escape($row['Email']); ?></td>
                                                    <td><?php echo escape($row['CreatedAt']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="empty-row">No fleet manager accounts recorded.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section id="panel-workshop-managers" class="admin-panel" role="tabpanel" aria-labelledby="tab-workshop-managers" hidden>
                        <div class="admin-panel-head">
                            <span>Workshop managers</span>
                            <h2>Who owns maintenance operations.</h2>
                            <p>Workshop manager accounts, plus alerts, jobs, and parts across every workshop.</p>
                        </div>

                        <div class="admin-panel-block">
                            <div class="dashboard-metrics">
                                <div>
                                    <span>Workshop manager accounts</span>
                                    <strong><?php echo $workshopManagerCount; ?></strong>
                                </div>
                                <div>
                                    <span>Open alerts</span>
                                    <strong><?php echo $openAlerts; ?></strong>
                                </div>
                                <div>
                                    <span>Active maintenance jobs</span>
                                    <strong><?php echo $activeJobs; ?></strong>
                                </div>
                                <div>
                                    <span>Mechanics on staff</span>
                                    <strong><?php echo $totalMechanics; ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="admin-panel-block">
                            <div class="admin-panel-block-head">
                                <span>Directory</span>
                                <h3>Workshop manager accounts.</h3>
                            </div>
                            <div class="admin-table-shell" data-reveal data-stack-card>
                                <table class="admin-table">
                                    <caption class="sr-only">Workshop manager accounts</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Name</th>
                                            <th scope="col">Email</th>
                                            <th scope="col">Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($workshopManagerAccounts): ?>
                                            <?php foreach ($workshopManagerAccounts as $row): ?>
                                                <tr>
                                                    <td class="cell-strong"><?php echo escape($row['FullName']); ?></td>
                                                    <td><?php echo escape($row['Email']); ?></td>
                                                    <td><?php echo escape($row['CreatedAt']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="empty-row">No workshop manager accounts recorded.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="admin-panel-block">
                            <div class="admin-panel-block-head">
                                <span>Predictive alerts</span>
                                <h3>Prioritise and escalate into jobs.</h3>
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

                        <div class="admin-panel-block">
                            <div class="admin-panel-block-head">
                                <span>Maintenance jobs</span>
                                <h3>Track status, downtime, and cost.</h3>
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

                        <div class="admin-panel-block">
                            <div class="admin-panel-block-head">
                                <span>Parts and suppliers</span>
                                <h3>Primary supplier and unit cost.</h3>
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
                </div>
            </div>
        </section>

        <section class="site-cta" aria-labelledby="admin-cta-title">
            <div>
                <h2 id="admin-cta-title">Need account governance?</h2>
                <p>Manage identities and roles from the account administration page.</p>
            </div>
            <div class="hero-actions">
                <a class="button button-dark" href="./admin_page.php">Manage accounts</a>
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
    <script>
        (function () {
            const tabs = Array.from(document.querySelectorAll('[data-panel-target]'));
            const panelIds = tabs.map((tab) => tab.getAttribute('data-panel-target'));

            function activatePanel(panelId, focusTab) {
                if (!panelIds.includes(panelId)) {
                    return;
                }

                tabs.forEach((tab) => {
                    const isMatch = tab.getAttribute('data-panel-target') === panelId;
                    tab.classList.toggle('is-active', isMatch);
                    tab.setAttribute('aria-selected', isMatch ? 'true' : 'false');

                    const panel = document.getElementById(tab.getAttribute('data-panel-target'));
                    if (!panel) {
                        return;
                    }

                    if (isMatch) {
                        panel.hidden = false;
                        panel.querySelectorAll('canvas').forEach((canvas) => {
                            const chart = window.Chart && Chart.getChart ? Chart.getChart(canvas) : null;
                            if (chart) {
                                chart.resize();
                            }
                        });
                        if (focusTab) {
                            tab.focus();
                        }
                    } else {
                        panel.hidden = true;
                    }
                });
            }

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    activatePanel(tab.getAttribute('data-panel-target'), false);
                });
            });

            const initialHash = window.location.hash.replace('#', '');
            if (panelIds.includes(initialHash)) {
                activatePanel(initialHash, false);
            }
        })();
    </script>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
