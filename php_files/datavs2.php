<?php
session_start();
require_once __DIR__ . '/includes/layout.php';

$conn = new mysqli("localhost", "root", "", "databruh_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql1 = "SELECT VehiclePlate, SUM(TotalCostVND) AS TotalCost
         FROM view_vehicle_maintenance_summary
         GROUP BY VehiclePlate
         ORDER BY TotalCost DESC";
$result1 = $conn->query($sql1);

$costLabels = [];
$costValues = [];
while ($row = $result1->fetch_assoc()) {
    $costLabels[] = $row['VehiclePlate'];
    $costValues[] = (float) $row['TotalCost'];
}

$sql2 = "SELECT VehiclePlate, SUM(DowntimeHours) AS TotalDowntime
         FROM view_vehicle_maintenance_summary
         WHERE DowntimeHours IS NOT NULL
         GROUP BY VehiclePlate
         ORDER BY TotalDowntime DESC";
$result2 = $conn->query($sql2);

$downtimeLabels = [];
$downtimeValues = [];
while ($row = $result2->fetch_assoc()) {
    $downtimeLabels[] = $row['VehiclePlate'];
    $downtimeValues[] = (float) $row['TotalDowntime'];
}

$sql3 = "SELECT vs.StatusName, COUNT(*) AS VehicleCount
         FROM vehicle v
         JOIN vehicle_status vs ON v.StatusID = vs.StatusID
         GROUP BY vs.StatusName
         ORDER BY VehicleCount DESC";
$result3 = $conn->query($sql3);

$statusLabels = [];
$statusValues = [];
while ($row = $result3->fetch_assoc()) {
    $statusLabels[] = $row['StatusName'];
    $statusValues[] = (int) $row['VehicleCount'];
}

$sql4 = "SELECT AlertStatus, COUNT(*) AS AlertCount
         FROM view_active_alerts
         GROUP BY AlertStatus";
$result4 = $conn->query($sql4);

$alertLabels = [];
$alertValues = [];
while ($row = $result4->fetch_assoc()) {
    $alertLabels[] = $row['AlertStatus'];
    $alertValues[] = (int) $row['AlertCount'];
}

$sql5 = "SELECT JobID, VehiclePlate, Manufacturer, Model, VehicleCategory,
                WorkshopName, OpenedDate, ClosedDate, DowntimeHours,
                TotalCostVND, JobStatus
         FROM view_vehicle_maintenance_summary
         ORDER BY OpenedDate DESC";
$result5 = $conn->query($sql5);

$maintenanceRows = [];
while ($row = $result5->fetch_assoc()) {
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
        content="Explore live Databruh fleet data: maintenance cost, downtime, vehicle status, and active alerts."
    >
    <title>Fleet & Maintenance Dashboard - Databruh</title>
    <link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="../css_files/design_system.css">
    <link rel="stylesheet" href="../css_files/datavs.css">
    <link rel="stylesheet" href="../css_files/minimalist_theme.css">
    <link rel="stylesheet" href="../css_files/swiss_bento_theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
</head>
<body>

    <?php renderSiteNavigation('dashboard'); ?>

    <main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
        <section class="site-hero dashboard-hero" aria-labelledby="dashboard-title">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="site-hero-content">
                <p class="eyebrow" data-hero-item>Fleet operations · Vietnam</p>
                <h1 id="dashboard-title" class="max-w-6xl" data-hero-item>
                    Every vehicle,<br>
                    every cost,<br> accounted for.
                </h1>
                <p class="hero-copy" data-hero-item>
                    Track maintenance spend, downtime, fleet readiness, and open
                    alerts across every depot in one place.
                </p>
                <div class="hero-actions" aria-label="Dashboard shortcuts" data-hero-item>
                    <a class="button button-primary" href="#fleet-visuals">Explore the data</a>
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
                    <a class="button button-primary" href="./driver_safety_dashboard.php">Go to driver safety dashboard</a>
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
                            <canvas
                                id="costChart"
                                role="img"
                                aria-label="Bar chart showing total maintenance cost for each vehicle."
                            >
                                Maintenance cost by vehicle.
                            </canvas>
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
                            <canvas
                                id="downtimeChart"
                                role="img"
                                aria-label="Horizontal bar chart showing downtime hours for each vehicle."
                            >
                                Downtime hours by vehicle.
                            </canvas>
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
                            <canvas
                                id="statusChart"
                                role="img"
                                aria-label="Doughnut chart showing the number of vehicles in each status."
                            >
                                Vehicle count by status.
                            </canvas>
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
                            <canvas
                                id="alertsChart"
                                role="img"
                                aria-label="Pie chart showing active alerts grouped by status."
                            >
                                Active alerts by status.
                            </canvas>
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

                <div
                    class="table-shell"
                    role="region"
                    aria-label="Recent maintenance jobs"
                    tabindex="0"
                    data-reveal
                >
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
                                    <td>
                                        <?php echo htmlspecialchars($row['Manufacturer'] . ' ' . $row['Model']); ?>
                                    </td>
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
        const costLabels = <?php echo json_encode($costLabels); ?>;
        const costValues = <?php echo json_encode($costValues); ?>;
        const downtimeLabels = <?php echo json_encode($downtimeLabels); ?>;
        const downtimeValues = <?php echo json_encode($downtimeValues); ?>;
        const statusLabels = <?php echo json_encode($statusLabels); ?>;
        const statusValues = <?php echo json_encode($statusValues); ?>;
        const alertLabels = <?php echo json_encode($alertLabels); ?>;
        const alertValues = <?php echo json_encode($alertValues); ?>;

        Chart.defaults.color = '#58636b';
        Chart.defaults.font.family = "'Geist', 'Avenir Next', sans-serif";
        Chart.defaults.borderColor = 'rgba(17, 29, 38, 0.1)';

        const sharedScale = {
            grid: { color: 'rgba(17, 29, 38, 0.08)' },
            ticks: { color: '#58636b' }
        };

        const chartMotion = {
            reducedMotion: window.matchMedia(
                '(prefers-reduced-motion: reduce)'
            ).matches,
            entries: new Map(),
            register(canvasId, config) {
                const canvas = document.getElementById(canvasId);

                if (!canvas) {
                    return null;
                }

                const targetData = (config.data?.datasets ?? []).map(
                    (dataset) =>
                        Array.isArray(dataset.data) ? [...dataset.data] : []
                );

                if (!this.reducedMotion) {
                    config.data.datasets.forEach((dataset, index) => {
                        dataset.data = targetData[index].map((value) =>
                            Number.isFinite(Number(value)) ? 0 : value
                        );
                    });
                }

                config.options = {
                    ...(config.options ?? {}),
                    animation: { duration: 0 }
                };

                const chart = new Chart(canvas, config);
                const card = canvas.closest('[data-chart-card]');

                this.entries.set(canvasId, {
                    canvas,
                    card,
                    chart,
                    targetData,
                    type: config.type,
                    played: this.reducedMotion
                });

                canvas.dataset.chartAnimationState = this.reducedMotion
                    ? 'complete'
                    : 'ready';

                if (this.reducedMotion) {
                    card?.classList.add('is-chart-active', 'is-chart-drawn');
                }

                return chart;
            },
            play(canvasId) {
                const entry = this.entries.get(canvasId);

                if (!entry || entry.played) {
                    return;
                }

                entry.played = true;
                entry.card?.classList.add('is-chart-active');

                if (this.reducedMotion) {
                    entry.canvas.dataset.chartAnimationState = 'complete';
                    entry.card?.classList.add('is-chart-drawn');
                    return;
                }

                entry.chart.stop();
                entry.targetData.forEach((values, index) => {
                    entry.chart.data.datasets[index].data = [...values];
                });

                entry.chart.options.animation.duration =
                    entry.type === 'bar' ? 1650 : 1350;
                entry.chart.options.animation.easing = 'easeOutQuart';
                entry.chart.options.animation.delay = (context) => {
                    if (context.type !== 'data') {
                        return 0;
                    }

                    const step = entry.type === 'bar' ? 90 : 55;
                    return context.dataIndex * step + context.datasetIndex * 60;
                };
                entry.chart.options.animation.onComplete = () => {
                    entry.canvas.dataset.chartAnimationState = 'complete';
                    entry.card?.classList.add('is-chart-drawn');
                };
                entry.canvas.dataset.chartAnimationState = 'playing';
                entry.chart.update();
            }
        };

        window.DatabruhCharts = chartMotion;

        chartMotion.register('costChart', {
            type: 'bar',
            data: {
                labels: costLabels,
                datasets: [{
                    label: 'Total Cost (VND)',
                    data: costValues,
                    backgroundColor: '#285f77',
                    hoverBackgroundColor: '#c74732',
                    borderColor: '#111d26',
                    hoverBorderColor: '#111d26',
                    borderWidth: 1,
                    hoverBorderWidth: 3,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                transitions: {
                    active: {
                        animation: { duration: 240, easing: 'easeOutQuart' }
                    }
                },
                plugins: { legend: { display: false } },
                scales: {
                    x: sharedScale,
                    y: { ...sharedScale, beginAtZero: true }
                }
            }
        });

        chartMotion.register('downtimeChart', {
            type: 'bar',
            data: {
                labels: downtimeLabels,
                datasets: [{
                    label: 'Downtime (Hours)',
                    data: downtimeValues,
                    backgroundColor: '#a97221',
                    hoverBackgroundColor: '#c74732',
                    borderColor: '#111d26',
                    hoverBorderColor: '#111d26',
                    borderWidth: 1,
                    hoverBorderWidth: 3,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                transitions: {
                    active: {
                        animation: { duration: 240, easing: 'easeOutQuart' }
                    }
                },
                plugins: { legend: { display: false } },
                scales: {
                    x: { ...sharedScale, beginAtZero: true },
                    y: sharedScale
                }
            }
        });

        chartMotion.register('statusChart', {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: ['#42695e', '#285f77', '#a97221', '#b83d29', '#742a23', '#111d26'],
                    borderColor: '#f2ddd7',
                    borderWidth: 4,
                    hoverOffset: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, boxHeight: 10, padding: 16 }
                    }
                }
            }
        });

        chartMotion.register('alertsChart', {
            type: 'pie',
            data: {
                labels: alertLabels,
                datasets: [{
                    data: alertValues,
                    backgroundColor: ['#b83d29', '#a97221'],
                    borderColor: '#f2ddd7',
                    borderWidth: 4,
                    hoverOffset: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, boxHeight: 10, padding: 16 }
                    }
                }
            }
        });
    </script>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>