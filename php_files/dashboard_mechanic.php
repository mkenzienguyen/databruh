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
    return strtolower(str_replace(' ', '-', trim($status)));
}

$linkedId = $_SESSION['LinkedID'] ?? null;

$tasks = [];
$totalTasks = 0;
$openTasks = 0;
$closedTasks = 0;
$labourHoursThisMonth = 0.0;
$mechanicName = $_SESSION['FullName'] ?? 'Mechanic';

if ($linkedId !== null) {
    $conn = new mysqli('localhost', 'root', '', 'databruh_db');
    if ($conn->connect_error) {
        http_response_code(503);
        die('Workshop data is temporarily unavailable. Please try again later.');
    }
    $conn->set_charset('utf8mb4');

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
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Your assigned maintenance tasks.">
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
                    Read-only view of the maintenance activities assigned to you —
                    diagnostics, repair history, and job context.
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
    </main>

    <?php renderSiteFooter('dashboard'); ?>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
