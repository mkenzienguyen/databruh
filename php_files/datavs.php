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

    <main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
        <section class="site-hero dashboard-hero" aria-labelledby="dashboard-title">
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
    ?>

    <div class="card">
        <h2>Incidents by Severity</h2>
        <canvas id="severityChart"></canvas>
    </div>

    <div class="card">
        <h2>Incidents by Depot</h2>
        <canvas id="depotChart"></canvas>
    </div>

    <div class="card">
        <h2>Monthly Safety Score Trend</h2>
        <canvas id="scoreChart"></canvas>
    </div>

    <div class="card full-width">
        <h2>Recent Incident Log</h2>
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Vehicle</th>
                    <th>Driver</th>
                    <th>Depot</th>
                    <th>Event Type</th>
                    <th>Severity</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incidentRows as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['Timestamp']); ?></td>
                    <td><?php echo htmlspecialchars($row['VehiclePlate']); ?></td>
                    <td><?php echo htmlspecialchars($row['DriverName'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['DepotName'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['EventType']); ?></td>
                    <td class="sev-<?php echo htmlspecialchars($row['SeverityLevel']); ?>">
                        <?php echo htmlspecialchars($row['SeverityLevel']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['Description']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

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

        chartMotion.register('eventTypeChart', {
            type: 'bar',
            data: {
                labels: eventTypeLabels,
                datasets: [{
                    label: 'Number of Incidents',
                    data: eventTypeValues,
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
                    y: {
                        ...sharedScale,
                        beginAtZero: true,
                        ticks: { ...sharedScale.ticks, stepSize: 1 }
                    }
                }
            }
        });

        chartMotion.register('severityChart', {
            type: 'doughnut',
            data: {
                labels: severityLabels,
                datasets: [{
                    data: severityValues,
                    backgroundColor: ['#42695e', '#a97221', '#b83d29', '#742a23'],
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

        chartMotion.register('depotChart', {
            type: 'bar',
            data: {
                labels: depotLabels,
                datasets: [{
                    label: 'Number of Incidents',
                    data: depotValues,
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
                indexAxis: 'y',
                transitions: {
                    active: {
                        animation: { duration: 240, easing: 'easeOutQuart' }
                    }
                },
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ...sharedScale,
                        beginAtZero: true,
                        ticks: { ...sharedScale.ticks, stepSize: 1 }
                    },
                    y: sharedScale
                }
            }
        });

        const scoreColors = [
            '#111d26',
            '#b83d29',
            '#285f77',
            '#42695e',
            '#a97221'
        ];

        const scoreDatasets = Object.keys(driverScores).map((name, i) => ({
            label: name,
            data: driverScores[name].scores,
            borderColor: scoreColors[i % scoreColors.length],
            backgroundColor: 'transparent',
            tension: 0.3,
            pointRadius: 3,
            pointHoverRadius: 5
        }));

new Chart(document.getElementById('eventTypeChart'), {
    type: 'bar',
    data: {
        labels: eventTypeLabels,
        datasets: [{
            label: 'Number of Incidents',
            data: eventTypeValues,
            backgroundColor: 'rgba(54, 162, 235, 0.7)'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

        chartMotion.register('scoreChart', {
            type: 'line',
            data: {
                labels: scoreLabels,
                datasets: scoreDatasets
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
                scales: {
                    x: sharedScale,
                    y: { ...sharedScale, min: 0, max: 100 }
                }
            }
        });
    </script>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
