<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/layout.php';
requireRole('DRIVER');

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function statusSlug(string $status): string
{
    return strtolower(str_replace(' ', '-', trim($status)));
}

$linkedId = $_SESSION['LinkedID'] ?? null;
$driverName = $_SESSION['FullName'] ?? 'Driver';

$scoreLabels = [];
$scoreValues = [];
$currentMonthScore = null;
$ytdAverage = null;
$totalIncidents = 0;
$criticalIncidents = 0;
$incidents = [];
$alerts = [];

if ($linkedId !== null) {
    $conn = new mysqli('localhost', 'root', '', 'databruh_db');
    if ($conn->connect_error) {
        http_response_code(503);
        die('Fleet data is temporarily unavailable. Please try again later.');
    }
    $conn->set_charset('utf8mb4');

    $scoreStmt = $conn->prepare(
        'SELECT Month, Year, Score FROM monthly_score_log WHERE DriverID = ? ORDER BY Year, Month'
    );
    $scoreStmt->bind_param('s', $linkedId);
    $scoreStmt->execute();
    $scoreResult = $scoreStmt->get_result();
    $currentMonth = (int) date('n');
    $currentYear = (int) date('Y');
    $ytdScores = [];
    while ($row = $scoreResult->fetch_assoc()) {
        $scoreLabels[] = str_pad((string) $row['Month'], 2, '0', STR_PAD_LEFT) . '/' . $row['Year'];
        $scoreValues[] = (int) $row['Score'];
        if ((int) $row['Month'] === $currentMonth && (int) $row['Year'] === $currentYear) {
            $currentMonthScore = (int) $row['Score'];
        }
        if ((int) $row['Year'] === $currentYear) {
            $ytdScores[] = (int) $row['Score'];
        }
    }
    $scoreStmt->close();
    if ($ytdScores) {
        $ytdAverage = array_sum($ytdScores) / count($ytdScores);
    }

    $incidentStmt = $conn->prepare(
        'SELECT Timestamp, VehiclePlate, EventType, SeverityLevel, Description
         FROM view_driver_incidents
         WHERE DriverID = ?
         ORDER BY Timestamp DESC'
    );
    $incidentStmt->bind_param('s', $linkedId);
    $incidentStmt->execute();
    $incidentResult = $incidentStmt->get_result();
    while ($row = $incidentResult->fetch_assoc()) {
        $incidents[] = $row;
        $totalIncidents++;
        if ($row['SeverityLevel'] === 'Critical') {
            $criticalIncidents++;
        }
    }
    $incidentStmt->close();

    $alertStmt = $conn->prepare(
        "SELECT a.AlertName, a.AlertDescription, a.AlertTimestamp, a.Status, v.RegistrationNumber
         FROM alert a
         JOIN vehicle_driver_assignment vda ON a.VehicleID = vda.VehicleID
         JOIN vehicle v ON a.VehicleID = v.VehicleID
         WHERE vda.DriverID = ? AND vda.EndDate IS NULL
         ORDER BY a.AlertTimestamp DESC"
    );
    $alertStmt->bind_param('s', $linkedId);
    $alertStmt->execute();
    $alertResult = $alertStmt->get_result();
    while ($row = $alertResult->fetch_assoc()) {
        $alerts[] = $row;
    }
    $alertStmt->close();

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Your monthly safety score, incident history, and vehicle alerts.">
    <title>Driver Dashboard - Databruh</title>
    <link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="../css_files/design_system.css">
    <link rel="stylesheet" href="../css_files/datavs.css">
    <link rel="stylesheet" href="../css_files/admin_page.css">
    <link rel="stylesheet" href="../css_files/role_dashboards.css">
    <link rel="stylesheet" href="../css_files/minimalist_theme.css">
    <link rel="stylesheet" href="../css_files/swiss_bento_theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to dashboard</a>
    <?php renderSiteNavigation('dashboard'); ?>

    <main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
        <section class="site-hero dashboard-hero" aria-labelledby="driver-dashboard-title">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="site-hero-content">
                <p class="eyebrow" data-hero-item>Driver · Your safety record</p>
                <h1 id="driver-dashboard-title" class="max-w-6xl" data-hero-item>
                    Your score,
                    <br>your history.
                </h1>
                <p class="hero-copy" data-hero-item>
                    Read-only view of your monthly safety score, recorded incidents,
                    and alerts on the vehicle currently assigned to you.
                </p>
                <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                    <div class="hero-feedback system-feedback" role="status" data-hero-item>
                        Successfully logged in as Driver.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($linkedId === null): ?>
            <section class="section-shell" style="padding: 4rem var(--page-gutter);">
                <div class="linked-notice">
                    <h2>Your account isn't linked to a driver record yet.</h2>
                    <p>
                        Contact an administrator to link <?php echo escape($driverName); ?>'s
                        account to a driver record so your score, incidents, and alerts
                        can appear here.
                    </p>
                </div>
            </section>
        <?php else: ?>
            <section id="dashboard-summary" class="dashboard-summary" aria-label="Dashboard summary">
                <div class="dashboard-metrics">
                    <div>
                        <span>Current month score</span>
                        <strong><?php echo $currentMonthScore !== null ? $currentMonthScore : '—'; ?></strong>
                    </div>
                    <div>
                        <span>Year-to-date average</span>
                        <strong><?php echo $ytdAverage !== null ? number_format($ytdAverage, 1) : '—'; ?></strong>
                    </div>
                    <div>
                        <span>Total incidents</span>
                        <strong><?php echo $totalIncidents; ?></strong>
                    </div>
                    <div>
                        <span>Critical incidents</span>
                        <strong><?php echo $criticalIncidents; ?></strong>
                    </div>
                </div>
            </section>

            <section id="safety-visuals" class="dashboard-analysis" data-chart-section aria-labelledby="score-trend-title">
                <div class="section-shell">
                    <div class="chapter-heading">
                        <div>
                            <span class="section-kicker">Monthly score log</span>
                            <h2 id="score-trend-title">Your safety score over time.</h2>
                        </div>
                    </div>
                    <article class="chart-card chart-card-score" data-stack-card style="min-height: 24rem;">
                        <div class="chart-wrap">
                            <canvas id="scoreChart" role="img" aria-label="Line chart of your monthly safety score."></canvas>
                        </div>
                    </article>
                </div>
            </section>

            <section class="admin-directory" aria-labelledby="incidents-title">
                <div class="section-shell">
                    <div class="chapter-heading">
                        <div>
                            <span class="section-kicker">Recorded incidents</span>
                            <h2 id="incidents-title">Behaviour events on your record.</h2>
                        </div>
                    </div>
                    <div class="admin-table-shell" data-reveal data-stack-card>
                        <table class="admin-table">
                            <caption class="sr-only">Your recorded behaviour events</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Timestamp</th>
                                    <th scope="col">Vehicle</th>
                                    <th scope="col">Event type</th>
                                    <th scope="col">Severity</th>
                                    <th scope="col">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($incidents): ?>
                                    <?php foreach ($incidents as $incident): ?>
                                        <tr>
                                            <td><?php echo escape($incident['Timestamp']); ?></td>
                                            <td class="cell-strong"><?php echo escape($incident['VehiclePlate']); ?></td>
                                            <td><?php echo escape($incident['EventType']); ?></td>
                                            <td class="sev-<?php echo escape($incident['SeverityLevel']); ?>">
                                                <span class="severity-badge"><?php echo escape($incident['SeverityLevel']); ?></span>
                                            </td>
                                            <td class="description-cell"><?php echo escape($incident['Description'] ?? '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="empty-row">No incidents recorded.</td></tr>
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
                            <span class="section-kicker">Vehicle alerts</span>
                            <h2 id="alerts-title">Alerts on your currently assigned vehicle.</h2>
                        </div>
                    </div>
                    <div class="admin-table-shell" data-reveal data-stack-card>
                        <table class="admin-table">
                            <caption class="sr-only">Alerts on your assigned vehicle</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Vehicle</th>
                                    <th scope="col">Alert</th>
                                    <th scope="col">Raised</th>
                                    <th scope="col">Status</th>
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
                                                <span class="status-pill status-<?php echo statusSlug($alert['Status'] ?? ''); ?>">
                                                    <?php echo escape($alert['Status'] ?? 'Unknown'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="empty-row">No alerts on your assigned vehicle.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php renderSiteFooter('dashboard'); ?>
    <?php if ($linkedId !== null): ?>
    <script>
        Chart.defaults.color = '#58636b';
        Chart.defaults.font.family = "'Geist', 'Avenir Next', sans-serif";
        Chart.defaults.borderColor = 'rgba(17, 29, 38, 0.1)';

        new Chart(document.getElementById('scoreChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($scoreLabels); ?>,
                datasets: [{
                    label: 'Monthly score',
                    data: <?php echo json_encode($scoreValues); ?>,
                    borderColor: '#111d26',
                    backgroundColor: 'transparent',
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { min: 0, max: 100 } }
            }
        });
    </script>
    <?php endif; ?>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
