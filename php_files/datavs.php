<?php
session_start();
require_once __DIR__ . '/includes/layout.php';

$conn = new mysqli("localhost", "root", "", "databruh_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql1 = "SELECT EventType, COUNT(*) AS EventCount
         FROM view_driver_incidents
         GROUP BY EventType
         ORDER BY EventCount DESC";
$result1 = $conn->query($sql1);

$eventTypeLabels = [];
$eventTypeValues = [];
while ($row = $result1->fetch_assoc()) {
    $eventTypeLabels[] = $row['EventType'];
    $eventTypeValues[] = (int) $row['EventCount'];
}

$sql2 = "SELECT SeverityLevel, COUNT(*) AS EventCount
         FROM view_driver_incidents
         GROUP BY SeverityLevel
         ORDER BY FIELD(SeverityLevel, 'Low', 'Medium', 'High', 'Critical')";
$result2 = $conn->query($sql2);

$severityLabels = [];
$severityValues = [];
while ($row = $result2->fetch_assoc()) {
    $severityLabels[] = $row['SeverityLevel'];
    $severityValues[] = (int) $row['EventCount'];
}

$sql3 = "SELECT d.FullName, msl.Month, msl.Year, msl.Score
         FROM monthly_score_log msl
         JOIN driver d ON msl.DriverID = d.DriverID
         ORDER BY d.FullName, msl.Year, msl.Month";
$result3 = $conn->query($sql3);

$rawScores = [];
$allMonths = [];
while ($row = $result3->fetch_assoc()) {
    $name = $row['FullName'];
    $label = str_pad($row['Month'], 2, '0', STR_PAD_LEFT) . "/" . $row['Year'];

    $rawScores[$name][$label] = (int) $row['Score'];
    $allMonths[$label] = [$row['Year'], $row['Month']];
}

uasort($allMonths, function ($a, $b) {
    return [$a[0], $a[1]] <=> [$b[0], $b[1]];
});
$scoreLabels = array_keys($allMonths);

$driverScores = [];
foreach ($rawScores as $name => $monthScores) {
    $aligned = [];
    foreach ($scoreLabels as $label) {
        $aligned[] = $monthScores[$label] ?? null;
    }
    $driverScores[$name] = $aligned;
}

$sql4 = "SELECT DepotName, COUNT(*) AS EventCount
         FROM view_driver_incidents
         WHERE DepotName IS NOT NULL
         GROUP BY DepotName
         ORDER BY EventCount DESC";
$result4 = $conn->query($sql4);

$depotLabels = [];
$depotValues = [];
while ($row = $result4->fetch_assoc()) {
    $depotLabels[] = $row['DepotName'];
    $depotValues[] = (int) $row['EventCount'];
}

$sql5 = "SELECT Timestamp, VehiclePlate, DriverName, DepotName, EventType, SeverityLevel, Description
         FROM view_driver_incidents
         ORDER BY Timestamp DESC";
$result5 = $conn->query($sql5);

$incidentRows = [];
while ($row = $result5->fetch_assoc()) {
    $incidentRows[] = $row;
}

$totalIncidents = array_sum($eventTypeValues);
$criticalIncidents = 0;
$criticalIndex = array_search('Critical', $severityLabels, true);

if ($criticalIndex !== false) {
    $criticalIncidents = $severityValues[$criticalIndex];
}

$sql6 = "SELECT VehiclePlate, SUM(TotalCostVND) AS TotalCost
         FROM view_vehicle_maintenance_summary
         GROUP BY VehiclePlate
         ORDER BY TotalCost DESC";
$result6 = $conn->query($sql6);

$costLabels = [];
$costValues = [];
while ($row = $result6->fetch_assoc()) {
    $costLabels[] = $row['VehiclePlate'];
    $costValues[] = (float) $row['TotalCost'];
}

$sql7 = "SELECT VehiclePlate, SUM(DowntimeHours) AS TotalDowntime
         FROM view_vehicle_maintenance_summary
         WHERE DowntimeHours IS NOT NULL
         GROUP BY VehiclePlate
         ORDER BY TotalDowntime DESC";
$result7 = $conn->query($sql7);

$downtimeLabels = [];
$downtimeValues = [];
while ($row = $result7->fetch_assoc()) {
    $downtimeLabels[] = $row['VehiclePlate'];
    $downtimeValues[] = (float) $row['TotalDowntime'];
}

$sql8 = "SELECT vs.StatusName, COUNT(*) AS VehicleCount
         FROM vehicle v
         JOIN vehicle_status vs ON v.StatusID = vs.StatusID
         GROUP BY vs.StatusName
         ORDER BY VehicleCount DESC";
$result8 = $conn->query($sql8);

$statusLabels = [];
$statusValues = [];
while ($row = $result8->fetch_assoc()) {
    $statusLabels[] = $row['StatusName'];
    $statusValues[] = (int) $row['VehicleCount'];
}

$sql9 = "SELECT AlertStatus, COUNT(*) AS AlertCount
         FROM view_active_alerts
         GROUP BY AlertStatus";
$result9 = $conn->query($sql9);

$alertLabels = [];
$alertValues = [];
while ($row = $result9->fetch_assoc()) {
    $alertLabels[] = $row['AlertStatus'];
    $alertValues[] = (int) $row['AlertCount'];
}

$sql10 = "SELECT JobID, VehiclePlate, Manufacturer, Model, VehicleCategory,
                 WorkshopName, OpenedDate, ClosedDate, DowntimeHours,
                 TotalCostVND, JobStatus
          FROM view_vehicle_maintenance_summary
          ORDER BY OpenedDate DESC";
$result10 = $conn->query($sql10);

$maintenanceRows = [];
while ($row = $result10->fetch_assoc()) {
    $maintenanceRows[] = $row;
}

$totalCost = array_sum($costValues);
$totalDowntime = array_sum($downtimeValues);
$totalVehicles = array_sum($statusValues);
$totalAlerts = array_sum($alertValues);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta
        name="description"
        content="Explore live Databruh driver safety data by incident type, severity, depot, and monthly score."
    >
    <title>Driver Safety Dashboard - Databruh</title>
    <link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="../css_files/design_system.css">
    <link rel="stylesheet" href="../css_files/datavs.css">
    <link rel="stylesheet" href="../css_files/minimalist_theme.css">
    <link rel="stylesheet" href="../css_files/swiss_bento_theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to dashboard</a>
    <?php renderSiteNavigation('dashboard'); ?>
    <div><a class="button button-dark" href="#maintenance-dashboard">Go to maintenance dashboard</a></div>

    <main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
        <section id="safety-dashboard" class="site-hero dashboard-hero" aria-labelledby="dashboard-title">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="site-hero-content">
                <p class="eyebrow" data-hero-item>Fleet safety operations · Vietnam</p>
                <h1 id="dashboard-title" class="max-w-6xl" data-hero-item>
                    Every road
                    <span class="hero-inline-image" aria-hidden="true"></span>
                    <br>event, in<br> context.
                </h1>
                <p class="hero-copy" data-hero-item>
                    Connect each behaviour signal to its driver, vehicle, depot,
                    severity, timestamp, and monthly safety score.
                </p>
                <div class="hero-actions" aria-label="Dashboard shortcuts" data-hero-item>
                    <a class="button button-primary" href="#safety-visuals">Explore the data</a>
                    <button
                        class="button button-secondary"
                        type="button"
                        data-detail-modal-open="dashboard-safety-details"
                        aria-haspopup="dialog"
                        aria-controls="dashboard-safety-details"
                        aria-expanded="false"
                    >
                        View safety details
                    </button>
                    <a class="button button-primary" href="#maintenance-dashboard">Go to maintenance dashboard</a>
                </div>
            </div>
            <a class="scroll-cue" href="#dashboard-summary" aria-label="Scroll to dashboard summary">
                <span>Live summary below</span>
                <span class="scroll-line" aria-hidden="true"></span>
            </a>
        </section>

        <div class="site-marquee" aria-hidden="true">
            <div class="marquee-track">
                <div class="marquee-group">
                    <span>Harsh braking</span><i></i>
                    <span>Excessive speeding</span><i></i>
                    <span>Fatigue warning</span><i></i>
                    <span>Phone distraction</span><i></i>
                </div>
                <div class="marquee-group">
                    <span>Harsh braking</span><i></i>
                    <span>Excessive speeding</span><i></i>
                    <span>Fatigue warning</span><i></i>
                    <span>Phone distraction</span><i></i>
                </div>
            </div>
        </div>

        <section id="dashboard-summary" class="dashboard-summary" aria-label="Dashboard summary">
            <div class="dashboard-metrics">
                <div>
                    <span>Recorded safety events</span>
                    <strong><?php echo $totalIncidents; ?></strong>
                </div>
                <div>
                    <span>Critical review queue</span>
                    <strong><?php echo $criticalIncidents; ?></strong>
                </div>
                <div>
                    <span>Depots in view</span>
                    <strong><?php echo count($depotLabels); ?></strong>
                </div>
                <div>
                    <span>Drivers with score trends</span>
                    <strong><?php echo count($driverScores); ?></strong>
                </div>
            </div>
        </section>

        <section id="safety-protocol" class="safety-protocol" aria-labelledby="protocol-title">
            <div class="protocol-shell">
                <div class="protocol-heading">
                    <span class="section-kicker">Decision protocol</span>
                    <h2 id="protocol-title">A signal only matters when it changes the next action.</h2>
                </div>
                <div class="protocol-rules">
                    <article data-reveal>
                        <span>High or Critical event</span>
                        <strong>Open a formal review</strong>
                        <p>Investigate context and record the coaching or retraining response.</p>
                    </article>
                    <article data-reveal>
                        <span>Monthly score at 75 or below</span>
                        <strong>Schedule coaching</strong>
                        <p>Use the event history to target the behaviour that needs attention.</p>
                    </article>
                    <article data-reveal>
                        <span>Monthly score at 50 or below</span>
                        <strong>Stop new assignments</strong>
                        <p>Restore eligibility only after the required safety action is complete.</p>
                    </article>
                </div>
            </div>
        </section>

        <section
            id="safety-visuals"
            class="dashboard-analysis"
            data-chart-section
            aria-labelledby="analysis-title"
        >
            <div class="section-shell">
                <div class="chapter-heading" data-chart-heading>
                    <div>
                        <span class="section-kicker">Safety review desk</span>
                        <h2 id="analysis-title">Four views. One accountable incident history.</h2>
                    </div>
                    <p data-scrub-text>
                        Compare behaviour type, response severity, depot exposure, and
                        driver score without separating the chart from its source record.
                    </p>
                </div>

                <div class="dashboard-bento">
                    <article class="chart-card chart-card-type" data-reveal data-stack-card data-chart-card>
                        <div class="chart-heading">
                            <div>
                                <span>Telematics mix</span>
                                <h3>Driver behaviour events</h3>
                            </div>
                            <p>Count of recorded events</p>
                        </div>
                        <div class="chart-wrap">
                            <canvas
                                id="eventTypeChart"
                                role="img"
                                aria-label="Bar chart showing the number of incidents for each event type."
                            >
                                Incident counts by event type.
                            </canvas>
                        </div>
                    </article>

                    <article class="chart-card chart-card-severity" data-reveal data-stack-card data-chart-card>
                        <div class="chart-heading">
                            <div>
                                <span>Response priority</span>
                                <h3>Events by severity</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas
                                id="severityChart"
                                role="img"
                                aria-label="Doughnut chart showing incidents grouped by severity level."
                            >
                                Incident counts by severity level.
                            </canvas>
                        </div>
                    </article>

                    <article class="chart-card chart-card-depot" data-reveal data-stack-card data-chart-card>
                        <div class="chart-heading">
                            <div>
                                <span>Network comparison</span>
                                <h3>Depot exposure</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas
                                id="depotChart"
                                role="img"
                                aria-label="Horizontal bar chart showing the number of incidents for each depot."
                            >
                                Incident counts by depot.
                            </canvas>
                        </div>
                    </article>

                    <article class="chart-card chart-card-score" data-reveal data-stack-card data-chart-card>
                        <div class="chart-heading">
                            <div>
                                <span>Assignment readiness</span>
                                <h3>Monthly driver score</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas
                                id="scoreChart"
                                role="img"
                                aria-label="Line chart showing each driver's monthly safety score trend."
                            >
                                Monthly driver safety score trends.
                            </canvas>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section
            id="incident-log"
            class="incident-section"
            data-pin-section
            aria-labelledby="incident-log-title"
        >
            <div class="section-shell incident-layout">
                <div class="incident-intro" data-pin-title>
                    <span class="section-kicker">Reviewable event register</span>
                    <h2 id="incident-log-title">Every signal returns to its operational context.</h2>
                    <p data-reveal>
                        The complete incident list remains ordered by its original
                        timestamp, preserving the vehicle, driver, depot, event,
                        severity, and description behind every chart.
                    </p>
                </div>

                <div
                    class="table-shell"
                    role="region"
                    aria-label="Recent fleet incidents"
                    tabindex="0"
                    data-reveal
                >
                    <table class="data-table">
                        <caption class="sr-only">Recent fleet incident records</caption>
                        <thead>
                            <tr>
                                <th scope="col">Timestamp</th>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Driver</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Event type</th>
                                <th scope="col">Severity</th>
                                <th scope="col">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidentRows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['Timestamp']); ?></td>
                                    <td class="cell-strong">
                                        <?php echo htmlspecialchars($row['VehiclePlate']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['DriverName'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($row['DepotName'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($row['EventType']); ?></td>
                                    <td class="sev-<?php echo htmlspecialchars($row['SeverityLevel']); ?>">
                                        <span class="severity-badge">
                                            <?php echo htmlspecialchars($row['SeverityLevel']); ?>
                                        </span>
                                    </td>
                                    <td class="description-cell">
                                        <?php echo htmlspecialchars($row['Description']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="site-cta" aria-labelledby="dashboard-cta-title">
            <div>
                <h2 id="dashboard-cta-title">Keep every safety decision traceable.</h2>
                <p>
                    Return to the operating model or review the account identity behind
                    your role-aware access.
                </p>
            </div>
            <a class="button button-dark" href="./home_page.php">Return home</a>
        </section>

        <section id="maintenance-dashboard" class="site-hero dashboard-hero" aria-labelledby="maintenance-dashboard-title">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="site-hero-content">
                <p class="eyebrow" data-hero-item>Fleet operations · Vietnam</p>
                <h1 id="maintenance-dashboard-title" class="max-w-6xl" data-hero-item>
                    Every vehicle,<br>
                    every cost,<br> accounted for.
                </h1>
                <p class="hero-copy" data-hero-item>
                    Track maintenance spend, downtime, fleet readiness, and open
                    alerts across every depot in one place.
                </p>
                <div class="hero-actions" aria-label="Maintenance dashboard shortcuts" data-hero-item>
                    <a class="button button-primary" href="#fleet-visuals">Explore the maintenance data</a>
                    <button
                        class="button button-secondary"
                        type="button"
                        data-detail-modal-open="fleet-dashboard-details"
                        aria-haspopup="dialog"
                        aria-controls="fleet-dashboard-details"
                        aria-expanded="false"
                    >
                        View fleet details
                    </button>
                    <a class="button button-primary" href="#safety-dashboard">Go to driver safety view</a>
                </div>
            </div>
            <a class="scroll-cue" href="#fleet-summary" aria-label="Scroll to fleet summary">
                <span>Live summary below</span>
                <span class="scroll-line" aria-hidden="true"></span>
            </a>
        </section>

        <section id="fleet-summary" class="dashboard-summary" aria-label="Fleet summary">
            <div class="dashboard-metrics">
                <div>
                    <span>Total maintenance cost (VND)</span>
                    <strong><?php echo number_format($totalCost); ?></strong>
                </div>
                <div>
                    <span>Total downtime (hours)</span>
                    <strong><?php echo number_format($totalDowntime, 1); ?></strong>
                </div>
                <div>
                    <span>Vehicles in fleet</span>
                    <strong><?php echo $totalVehicles; ?></strong>
                </div>
                <div>
                    <span>Active alerts</span>
                    <strong><?php echo $totalAlerts; ?></strong>
                </div>
            </div>
        </section>

        <section
            id="fleet-visuals"
            class="dashboard-analysis"
            data-chart-section
            aria-labelledby="fleet-analysis-title"
        >
            <div class="section-shell">
                <div class="chapter-heading" data-chart-heading>
                    <div>
                        <span class="section-kicker">Fleet operations desk</span>
                        <h2 id="fleet-analysis-title">Four views. One operating picture.</h2>
                    </div>
                    <p data-scrub-text>
                        Compare maintenance spend, downtime, vehicle readiness, and
                        open alerts across the fleet.
                    </p>
                </div>

                <div class="dashboard-bento">
                    <article class="chart-card chart-card-type" data-reveal data-stack-card data-chart-card>
                        <div class="chart-heading">
                            <div>
                                <span>Spend by vehicle</span>
                                <h3>Maintenance cost</h3>
                            </div>
                            <p>Total cost recorded (VND)</p>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="costChart" role="img" aria-label="Bar chart showing total maintenance cost for each vehicle.">Maintenance cost by vehicle.</canvas>
                        </div>
                    </article>

                    <article class="chart-card chart-card-severity" data-reveal data-stack-card data-chart-card>
                        <div class="chart-heading">
                            <div>
                                <span>Time off the road</span>
                                <h3>Downtime hours</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="downtimeChart" role="img" aria-label="Horizontal bar chart showing downtime hours for each vehicle.">Downtime hours by vehicle.</canvas>
                        </div>
                    </article>

                    <article class="chart-card chart-card-depot" data-reveal data-stack-card data-chart-card>
                        <div class="chart-heading">
                            <div>
                                <span>Fleet readiness</span>
                                <h3>Vehicle status</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="statusChart" role="img" aria-label="Doughnut chart showing the number of vehicles in each status.">Vehicle count by status.</canvas>
                        </div>
                    </article>

                    <article class="chart-card chart-card-score" data-reveal data-stack-card data-chart-card>
                        <div class="chart-heading">
                            <div>
                                <span>Open follow-ups</span>
                                <h3>Active alerts</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="alertsChart" role="img" aria-label="Pie chart showing active alerts grouped by status.">Active alerts by status.</canvas>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section
            id="maintenance-log"
            class="incident-section"
            data-pin-section
            aria-labelledby="maintenance-log-title"
        >
            <div class="section-shell incident-layout">
                <div class="incident-intro" data-pin-title>
                    <span class="section-kicker">Reviewable job register</span>
                    <h2 id="maintenance-log-title">Every job returns to its cost and downtime record.</h2>
                    <p data-reveal>
                        The complete maintenance job list remains ordered by its
                        opened date, preserving the vehicle, workshop, cost,
                        downtime, and status behind every chart above.
                    </p>
                </div>

                <div class="table-shell" role="region" aria-label="Recent maintenance jobs" tabindex="0" data-reveal>
                    <table class="data-table">
                        <caption class="sr-only">Recent fleet maintenance job records</caption>
                        <thead>
                            <tr>
                                <th scope="col">Job ID</th>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Model</th>
                                <th scope="col">Category</th>
                                <th scope="col">Workshop</th>
                                <th scope="col">Opened</th>
                                <th scope="col">Closed</th>
                                <th scope="col">Downtime (hrs)</th>
                                <th scope="col">Cost (VND)</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($maintenanceRows as $row): ?>
                                <tr>
                                    <td class="cell-strong"><?php echo htmlspecialchars($row['JobID']); ?></td>
                                    <td><?php echo htmlspecialchars($row['VehiclePlate']); ?></td>
                                    <td><?php echo htmlspecialchars($row['Manufacturer'] . ' ' . $row['Model']); ?></td>
                                    <td><?php echo htmlspecialchars($row['VehicleCategory']); ?></td>
                                    <td><?php echo htmlspecialchars($row['WorkshopName']); ?></td>
                                    <td><?php echo htmlspecialchars($row['OpenedDate']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ClosedDate'] ?? '—'); ?></td>
                                    <td><?php echo $row['DowntimeHours'] !== null ? htmlspecialchars($row['DowntimeHours']) : '—'; ?></td>
                                    <td><?php echo number_format((float) $row['TotalCostVND']); ?></td>
                                    <td><?php echo htmlspecialchars($row['JobStatus']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="site-cta" aria-labelledby="fleet-cta-title">
            <div>
                <h2 id="fleet-cta-title">Keep every vehicle decision traceable.</h2>
                <p>
                    Return to the driver safety dashboard or the operating model
                    behind your role-aware access.
                </p>
            </div>
            <a class="button button-dark" href="./home_page.php">Return home</a>
        </section>
    </main>

    <?php renderSiteFooter('dashboard'); ?>
    <?php
    renderDetailModal([
        'id' => 'dashboard-safety-details',
        'kicker' => 'Safety review model',
        'title' => 'Read the event, threshold, and source record together.',
        'intro' => 'The dashboard keeps visual summaries connected to the operational evidence required for a responsible fleet-safety decision.',
        'items' => [
            [
                'label' => 'Event context',
                'title' => 'See more than a count',
                'body' => 'Behaviour type, driver, vehicle, depot, severity, timestamp, and description remain available for review.',
            ],
            [
                'label' => 'Response thresholds',
                'title' => 'Escalate consistently',
                'body' => 'High or Critical events open review; scores at 75 or below prompt coaching and scores at 50 or below stop new assignments.',
            ],
            [
                'label' => 'Incident register',
                'title' => 'Return to the evidence',
                'body' => 'The complete timestamp-ordered register remains on this page beneath the four safety visualisations.',
            ],
        ],
    ]);
    renderDetailModal([
        'id' => 'fleet-dashboard-details',
        'kicker' => 'Fleet operating model',
        'title' => 'Read the cost, downtime, and alert picture together.',
        'intro' => 'The dashboard keeps visual summaries connected to the operational data required for a responsible fleet-maintenance decision.',
        'items' => [
            [
                'label' => 'Maintenance spend',
                'title' => 'See where the cost goes',
                'body' => 'Total maintenance cost is broken down per vehicle, sourced from every recorded job and activity.',
            ],
            [
                'label' => 'Downtime',
                'title' => 'Track time off the road',
                'body' => 'Downtime hours per vehicle highlight which units are spending the most time under repair.',
            ],
            [
                'label' => 'Readiness and alerts',
                'title' => 'Know the fleet state at a glance',
                'body' => 'Vehicle status and open alert counts show what is active, under maintenance, or awaiting review right now.',
            ],
        ],
    ]);
    ?>

    <script>
        window.DatabruhDashboardData = {
            eventTypeLabels: <?php echo json_encode($eventTypeLabels); ?>,
            eventTypeValues: <?php echo json_encode($eventTypeValues); ?>,
            severityLabels: <?php echo json_encode($severityLabels); ?>,
            severityValues: <?php echo json_encode($severityValues); ?>,
            depotLabels: <?php echo json_encode($depotLabels); ?>,
            depotValues: <?php echo json_encode($depotValues); ?>,
            driverScores: <?php echo json_encode($driverScores); ?>,
            scoreLabels: <?php echo json_encode($scoreLabels); ?>,
            costLabels: <?php echo json_encode($costLabels); ?>,
            costValues: <?php echo json_encode($costValues); ?>,
            downtimeLabels: <?php echo json_encode($downtimeLabels); ?>,
            downtimeValues: <?php echo json_encode($downtimeValues); ?>,
            statusLabels: <?php echo json_encode($statusLabels); ?>,
            statusValues: <?php echo json_encode($statusValues); ?>,
            alertLabels: <?php echo json_encode($alertLabels); ?>,
            alertValues: <?php echo json_encode($alertValues); ?>
        };
    </script>
    <script src="../js_files/datavs_dashboard.js"></script>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>