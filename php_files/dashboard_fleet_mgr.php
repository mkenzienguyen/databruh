<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/layout.php';
requireRole('FLEET_MGR');

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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

    if ($action === 'update_employment_status') {
        $driverId = trim((string) ($_POST['driver_id'] ?? ''));
        $status = trim((string) ($_POST['employment_status'] ?? ''));
        $allowedStatuses = ['Active', 'On Leave', 'Suspended', 'Terminated'];

        if ($driverId === '' || !in_array($status, $allowedStatuses, true)) {
            $message = 'Invalid employment status update.';
            $messageIsError = true;
        } else {
            $stmt = $conn->prepare('UPDATE driver SET EmploymentStatus = ? WHERE DriverID = ?');
            $stmt->bind_param('ss', $status, $driverId);
            $message = $stmt->execute() ? 'Driver employment status updated.' : 'Could not update employment status.';
            $messageIsError = !$stmt->affected_rows && $stmt->error !== '';
            $stmt->close();
        }
    } elseif ($action === 'update_vehicle_status') {
        $vehicleId = trim((string) ($_POST['vehicle_id'] ?? ''));
        $statusId = (int) ($_POST['status_id'] ?? 0);

        if ($vehicleId === '' || $statusId <= 0) {
            $message = 'Invalid vehicle status update.';
            $messageIsError = true;
        } else {
            $stmt = $conn->prepare('UPDATE vehicle SET StatusID = ? WHERE VehicleID = ?');
            $stmt->bind_param('is', $statusId, $vehicleId);
            $message = $stmt->execute() ? 'Vehicle status updated.' : 'Could not update vehicle status.';
            $stmt->close();
        }
    } elseif ($action === 'create_assignment') {
        $vehicleId = trim((string) ($_POST['vehicle_id'] ?? ''));
        $driverId = trim((string) ($_POST['driver_id'] ?? ''));
        $startDate = trim((string) ($_POST['start_date'] ?? ''));

        if ($vehicleId === '' || $driverId === '' || $startDate === '') {
            $message = 'Vehicle, driver, and start date are all required.';
            $messageIsError = true;
        } else {
            $activeCheck = $conn->prepare(
                'SELECT 1 FROM vehicle_driver_assignment WHERE VehicleID = ? AND EndDate IS NULL'
            );
            $activeCheck->bind_param('s', $vehicleId);
            $activeCheck->execute();
            $activeCheck->store_result();

            if ($activeCheck->num_rows > 0) {
                $message = 'That vehicle already has an active driver assignment. End it first.';
                $messageIsError = true;
                $activeCheck->close();
            } else {
                $activeCheck->close();
                $stmt = $conn->prepare(
                    'INSERT INTO vehicle_driver_assignment (VehicleID, DriverID, StartDate) VALUES (?, ?, ?)'
                );
                $stmt->bind_param('sss', $vehicleId, $driverId, $startDate);
                $message = $stmt->execute() ? 'Assignment created.' : 'Could not create assignment.';
                $messageIsError = !$stmt->affected_rows;
                $stmt->close();
            }
        }
    } elseif ($action === 'end_assignment') {
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);

        if ($assignmentId <= 0) {
            $message = 'Invalid assignment.';
            $messageIsError = true;
        } else {
            $stmt = $conn->prepare(
                'UPDATE vehicle_driver_assignment SET EndDate = CURDATE() WHERE AssignmentID = ? AND EndDate IS NULL'
            );
            $stmt->bind_param('i', $assignmentId);
            $message = $stmt->execute() ? 'Assignment ended.' : 'Could not end assignment.';
            $stmt->close();
        }
    }
}

$totalDrivers = (int) $conn->query('SELECT COUNT(*) AS c FROM driver')->fetch_assoc()['c'];
$totalVehicles = (int) $conn->query('SELECT COUNT(*) AS c FROM vehicle')->fetch_assoc()['c'];
$activeAssignments = (int) $conn->query(
    'SELECT COUNT(*) AS c FROM vehicle_driver_assignment WHERE EndDate IS NULL'
)->fetch_assoc()['c'];
$criticalThisMonth = (int) $conn->query(
    "SELECT COUNT(*) AS c
     FROM behaviour_event be
     JOIN severity_level sl ON be.SeverityID = sl.SeverityID
     WHERE sl.LevelName = 'Critical'
       AND MONTH(be.Timestamp) = MONTH(CURDATE())
       AND YEAR(be.Timestamp) = YEAR(CURDATE())"
)->fetch_assoc()['c'];

$drivers = [];
$driverResult = $conn->query(
    "SELECT d.DriverID, d.FullName, dl.DepotName, d.LicenseNumber, d.LicenseExpiration,
            d.EmploymentStatus, v.RegistrationNumber AS CurrentVehicle
     FROM driver d
     LEFT JOIN depot_location dl ON d.DepotID = dl.DepotID
     LEFT JOIN vehicle_driver_assignment vda ON d.DriverID = vda.DriverID AND vda.EndDate IS NULL
     LEFT JOIN vehicle v ON vda.VehicleID = v.VehicleID
     ORDER BY d.FullName"
);
while ($row = $driverResult->fetch_assoc()) {
    $drivers[] = $row;
}

$vehicleStatuses = [];
$vehicleStatusResult = $conn->query('SELECT StatusID, StatusName FROM vehicle_status ORDER BY StatusID');
while ($row = $vehicleStatusResult->fetch_assoc()) {
    $vehicleStatuses[] = $row;
}

$vehicles = [];
$vehicleResult = $conn->query(
    "SELECT v.VehicleID, v.RegistrationNumber, v.Manufacturer, v.Model, vc.ClassificationName,
            dl.DepotName, vs.StatusName, v.StatusID, v.CurrentOdometer, d.FullName AS CurrentDriver
     FROM vehicle v
     LEFT JOIN vehicle_classification vc ON v.ClassificationID = vc.ClassificationID
     LEFT JOIN depot_location dl ON v.DepotID = dl.DepotID
     LEFT JOIN vehicle_status vs ON v.StatusID = vs.StatusID
     LEFT JOIN vehicle_driver_assignment vda ON v.VehicleID = vda.VehicleID AND vda.EndDate IS NULL
     LEFT JOIN driver d ON vda.DriverID = d.DriverID
     ORDER BY v.RegistrationNumber"
);
while ($row = $vehicleResult->fetch_assoc()) {
    $vehicles[] = $row;
}

$activeAssignmentRows = [];
$assignmentResult = $conn->query(
    "SELECT vda.AssignmentID, v.RegistrationNumber, d.FullName, vda.StartDate
     FROM vehicle_driver_assignment vda
     JOIN vehicle v ON vda.VehicleID = v.VehicleID
     JOIN driver d ON vda.DriverID = d.DriverID
     WHERE vda.EndDate IS NULL
     ORDER BY vda.StartDate DESC"
);
while ($row = $assignmentResult->fetch_assoc()) {
    $activeAssignmentRows[] = $row;
}

$assignableVehicles = [];
$assignableVehicleResult = $conn->query('SELECT VehicleID, RegistrationNumber FROM vehicle ORDER BY RegistrationNumber');
while ($row = $assignableVehicleResult->fetch_assoc()) {
    $assignableVehicles[] = $row;
}

$assignableDrivers = [];
$assignableDriverResult = $conn->query('SELECT DriverID, FullName FROM driver ORDER BY FullName');
while ($row = $assignableDriverResult->fetch_assoc()) {
    $assignableDrivers[] = $row;
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Manage driver and vehicle activity across the Databruh fleet.">
    <title>Fleet Manager Dashboard - Databruh</title>
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
        <section class="site-hero dashboard-hero" aria-labelledby="fleet-dashboard-title">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="site-hero-content">
                <p class="eyebrow" data-hero-item>Fleet manager · Driver and vehicle activity</p>
                <h1 id="fleet-dashboard-title" class="max-w-6xl" data-hero-item>
                    Drivers, vehicles,
                    <br>and every assignment.
                </h1>
                <p class="hero-copy" data-hero-item>
                    Review incidents, compare depots, and manage driver and vehicle
                    activity from one workspace.
                </p>
                <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                    <div class="hero-feedback system-feedback" role="status" data-hero-item>
                        Successfully logged in as Fleet Manager.
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
                    <span>Active assignments</span>
                    <strong><?php echo $activeAssignments; ?></strong>
                </div>
                <div>
                    <span>Critical incidents this month</span>
                    <strong><?php echo $criticalThisMonth; ?></strong>
                </div>
            </div>
        </section>

        <section id="safety-visuals" class="dashboard-analysis" data-chart-section aria-labelledby="fleet-safety-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Safety snapshot</span>
                        <h2 id="fleet-safety-title">Driver behaviour across the network.</h2>
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
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="driver-directory-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Driver activity</span>
                        <h2 id="driver-directory-title">Directory and employment status.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Driver directory</caption>
                        <thead>
                            <tr>
                                <th scope="col">Driver</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Licence</th>
                                <th scope="col">Expires</th>
                                <th scope="col">Current vehicle</th>
                                <th scope="col">Employment status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($drivers): ?>
                                <?php foreach ($drivers as $driver): ?>
                                    <?php
                                    $expired = strtotime($driver['LicenseExpiration']) < time();
                                    ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($driver['FullName']); ?></td>
                                        <td><?php echo escape($driver['DepotName'] ?? '—'); ?></td>
                                        <td><?php echo escape($driver['LicenseNumber']); ?></td>
                                        <td class="<?php echo $expired ? 'sev-Critical' : ''; ?>">
                                            <?php echo escape($driver['LicenseExpiration']); ?>
                                            <?php if ($expired): ?> (expired)<?php endif; ?>
                                        </td>
                                        <td><?php echo escape($driver['CurrentVehicle'] ?? '—'); ?></td>
                                        <td>
                                            <form method="POST" class="inline-form role-form">
                                                <input type="hidden" name="action" value="update_employment_status">
                                                <input type="hidden" name="driver_id" value="<?php echo escape($driver['DriverID']); ?>">
                                                <label class="sr-only" for="emp-<?php echo escape($driver['DriverID']); ?>">
                                                    Employment status for <?php echo escape($driver['FullName']); ?>
                                                </label>
                                                <select
                                                    id="emp-<?php echo escape($driver['DriverID']); ?>"
                                                    name="employment_status"
                                                    onfocus="storeOriginalSelectValue(this)"
                                                    onchange="confirmSelectChange(this, 'employment status')"
                                                >
                                                    <?php foreach (['Active', 'On Leave', 'Suspended', 'Terminated'] as $status): ?>
                                                        <option value="<?php echo escape($status); ?>" <?php echo $driver['EmploymentStatus'] === $status ? 'selected' : ''; ?>>
                                                            <?php echo escape($status); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="empty-row">No drivers recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="vehicle-directory-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Vehicle activity</span>
                        <h2 id="vehicle-directory-title">Directory and operational status.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Vehicle directory</caption>
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Type</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Odometer</th>
                                <th scope="col">Current driver</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($vehicles): ?>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <tr>
                                        <td class="cell-strong">
                                            <?php echo escape($vehicle['RegistrationNumber']); ?>
                                            <div><?php echo escape(trim(($vehicle['Manufacturer'] ?? '') . ' ' . ($vehicle['Model'] ?? ''))); ?></div>
                                        </td>
                                        <td><?php echo escape($vehicle['ClassificationName'] ?? '—'); ?></td>
                                        <td><?php echo escape($vehicle['DepotName'] ?? '—'); ?></td>
                                        <td><?php echo number_format((int) $vehicle['CurrentOdometer']); ?> km</td>
                                        <td><?php echo escape($vehicle['CurrentDriver'] ?? '—'); ?></td>
                                        <td>
                                            <form method="POST" class="inline-form role-form">
                                                <input type="hidden" name="action" value="update_vehicle_status">
                                                <input type="hidden" name="vehicle_id" value="<?php echo escape($vehicle['VehicleID']); ?>">
                                                <label class="sr-only" for="vs-<?php echo escape($vehicle['VehicleID']); ?>">
                                                    Status for <?php echo escape($vehicle['RegistrationNumber']); ?>
                                                </label>
                                                <select
                                                    id="vs-<?php echo escape($vehicle['VehicleID']); ?>"
                                                    name="status_id"
                                                    onfocus="storeOriginalSelectValue(this)"
                                                    onchange="confirmSelectChange(this, 'vehicle status')"
                                                >
                                                    <?php foreach ($vehicleStatuses as $status): ?>
                                                        <option value="<?php echo (int) $status['StatusID']; ?>" <?php echo ((int) $vehicle['StatusID'] === (int) $status['StatusID']) ? 'selected' : ''; ?>>
                                                            <?php echo escape($status['StatusName']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="empty-row">No vehicles recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="assignment-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Assignment management</span>
                        <h2 id="assignment-title">Connect drivers to vehicles.</h2>
                    </div>
                </div>

                <form method="POST" class="directory-toolbar" data-reveal data-stack-card>
                    <input type="hidden" name="action" value="create_assignment">
                    <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
                        <div class="field-group">
                            <label for="assign-vehicle">Vehicle</label>
                            <select id="assign-vehicle" name="vehicle_id" required>
                                <option value="" disabled selected>Select vehicle</option>
                                <?php foreach ($assignableVehicles as $v): ?>
                                    <option value="<?php echo escape($v['VehicleID']); ?>"><?php echo escape($v['RegistrationNumber']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="assign-driver">Driver</label>
                            <select id="assign-driver" name="driver_id" required>
                                <option value="" disabled selected>Select driver</option>
                                <?php foreach ($assignableDrivers as $d): ?>
                                    <option value="<?php echo escape($d['DriverID']); ?>"><?php echo escape($d['FullName']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="assign-start">Start date</label>
                            <input type="date" id="assign-start" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-search">Create assignment</button>
                    </div>
                </form>

                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Active vehicle-driver assignments</caption>
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Driver</th>
                                <th scope="col">Start date</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($activeAssignmentRows): ?>
                                <?php foreach ($activeAssignmentRows as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['RegistrationNumber']); ?></td>
                                        <td><?php echo escape($row['FullName']); ?></td>
                                        <td><?php echo escape($row['StartDate']); ?></td>
                                        <td>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('End this assignment?');">
                                                <input type="hidden" name="action" value="end_assignment">
                                                <input type="hidden" name="assignment_id" value="<?php echo (int) $row['AssignmentID']; ?>">
                                                <button type="submit" class="btn btn-danger">End assignment</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="empty-row">No active assignments.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
    </script>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
