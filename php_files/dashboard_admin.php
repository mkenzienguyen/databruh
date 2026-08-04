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

$recentAlerts = [];
$recentAlertsResult = $conn->query(
    'SELECT a.AlertID, a.AlertName, a.Status, a.AlertTimestamp, v.RegistrationNumber
     FROM alert a
     JOIN vehicle v ON a.VehicleID = v.VehicleID
     ORDER BY a.AlertTimestamp DESC
     LIMIT 10'
);
while ($row = $recentAlertsResult->fetch_assoc()) {
    $recentAlerts[] = $row;
}

$recentJobs = [];
$recentJobsResult = $conn->query(
    'SELECT mj.JobID, v.RegistrationNumber, w.WorkshopName, mj.StartDate, mj.Status, mj.ToTalCost
     FROM maintenance_job mj
     JOIN vehicle v ON mj.VehicleID = v.VehicleID
     JOIN workshop w ON mj.WorkshopID = w.WorkshopID
     ORDER BY mj.StartDate DESC
     LIMIT 10'
);
while ($row = $recentJobsResult->fetch_assoc()) {
    $recentJobs[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Full-visibility operations hub for Databruh administrators.">
    <title>Administrator Dashboard - Databruh</title>
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
        <section class="site-hero dashboard-hero" aria-labelledby="admin-dashboard-title">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="site-hero-content">
                <p class="eyebrow" data-hero-item>Administrator · Full fleet visibility</p>
                <h1 id="admin-dashboard-title" class="max-w-6xl" data-hero-item>
                    Every domain,
                    <br>one operating picture.
                </h1>
                <p class="hero-copy" data-hero-item>
                    Cross-domain counts across drivers, vehicles, alerts, and
                    maintenance — the parts of the fleet no single role sees alone.
                </p>
                <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                    <div class="hero-feedback system-feedback" role="status" data-hero-item>
                        Successfully logged in as Administrator.
                    </div>
                <?php endif; ?>
            </div>
        </section>

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

        <section class="admin-directory" aria-labelledby="recent-alerts-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Predictive alerts</span>
                        <h2 id="recent-alerts-title">Most recent alerts across the fleet.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Ten most recent alerts</caption>
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Alert</th>
                                <th scope="col">Status</th>
                                <th scope="col">Raised</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentAlerts): ?>
                                <?php foreach ($recentAlerts as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['RegistrationNumber']); ?></td>
                                        <td><?php echo escape($row['AlertName']); ?></td>
                                        <td>
                                            <span class="status-pill status-<?php echo statusSlug($row['Status']); ?>">
                                                <?php echo escape($row['Status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo escape($row['AlertTimestamp']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="empty-row">No alerts recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="recent-jobs-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Workshop activity</span>
                        <h2 id="recent-jobs-title">Most recent maintenance jobs.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Ten most recent maintenance jobs</caption>
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Workshop</th>
                                <th scope="col">Started</th>
                                <th scope="col">Status</th>
                                <th scope="col">Cost (VND)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentJobs): ?>
                                <?php foreach ($recentJobs as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['RegistrationNumber']); ?></td>
                                        <td><?php echo escape($row['WorkshopName']); ?></td>
                                        <td><?php echo escape($row['StartDate']); ?></td>
                                        <td>
                                            <span class="status-pill status-<?php echo statusSlug($row['Status'] ?? ''); ?>">
                                                <?php echo escape($row['Status'] ?? 'Unknown'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $row['ToTalCost'] !== null ? number_format((int) $row['ToTalCost']) : '—'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-row">No maintenance jobs recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="site-cta" aria-labelledby="admin-cta-title">
            <div>
                <h2 id="admin-cta-title">Need account governance or safety detail?</h2>
                <p>Manage identities and roles, or open the full driver safety dashboard.</p>
            </div>
            <div class="hero-actions">
                <a class="button button-dark" href="./admin_page.php">Manage accounts</a>
                <a class="button button-outline" href="./datavs.php">Safety dashboard</a>
            </div>
        </section>
    </main>

    <?php renderSiteFooter('dashboard'); ?>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
