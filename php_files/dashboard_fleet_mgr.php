<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/layout.php';
requireRole('FLEET_MGR');

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function statusSlug(string $status): string
{
    return trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', trim($status))), '-');
}

const COACHING_OUTCOMES = [
    'Coached - Verbal Warning',
    'Coached - Written Warning',
    'Retraining Required',
    'Completed - No Concerns',
];

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
                // The database enforces the brief's assignment rules directly
                // (trg_vehicle_driver_assignment_before_insert in
                // business_rules.sql): vehicle not Under Maintenance/Out of
                // Service, driver holds every required unexpired
                // certification, safety score above 50, and no unresolved
                // critical incident. A violation raises SIGNAL SQLSTATE
                // '45000', which mysqli surfaces as a mysqli_sql_exception —
                // catch it here and show its message directly, since it's
                // already a plain-language explanation of which rule failed.
                try {
                    $stmt = $conn->prepare(
                        'INSERT INTO vehicle_driver_assignment (VehicleID, DriverID, StartDate) VALUES (?, ?, ?)'
                    );
                    $stmt->bind_param('sss', $vehicleId, $driverId, $startDate);
                    if ($stmt->execute()) {
                        $message = 'Assignment created.';
                        $messageIsError = false;
                    } else {
                        // Covers environments where mysqli isn't in
                        // exception-throwing mode: execute() returns false
                        // instead, with the trigger's SIGNAL message on
                        // $stmt->error.
                        $message = $stmt->error !== '' ? $stmt->error : 'Could not create assignment.';
                        $messageIsError = true;
                    }
                    $stmt->close();
                } catch (mysqli_sql_exception $e) {
                    $message = $e->getMessage();
                    $messageIsError = true;
                }
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
    } elseif ($action === 'record_coaching') {
        $driverId = trim((string) ($_POST['driver_id'] ?? ''));
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $outcome = trim((string) ($_POST['outcome'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($driverId === '' || !in_array($outcome, COACHING_OUTCOMES, true)) {
            $message = 'Invalid coaching record.';
            $messageIsError = true;
        } else {
            $conductedBy = (string) ($_SESSION['FullName'] ?? 'Fleet Manager');
            $eventIdParam = $eventId > 0 ? $eventId : null;
            $stmt = $conn->prepare(
                'INSERT INTO coaching_log (DriverID, EventID, CoachDate, ConductedBy, Outcome, Notes)
                 VALUES (?, ?, CURDATE(), ?, ?, ?)'
            );
            $stmt->bind_param('sisss', $driverId, $eventIdParam, $conductedBy, $outcome, $notes);
            $message = $stmt->execute() ? 'Coaching outcome recorded.' : 'Could not record coaching outcome.';
            $messageIsError = !$stmt->affected_rows;
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

$scoreAnomalies = [];
$anomalyResult = $conn->query('SELECT * FROM view_driver_score_anomalies');
while ($row = $anomalyResult->fetch_assoc()) {
    $scoreAnomalies[] = $row;
}

$depotTrendMonths = [];
$depotTrendByDepot = [];
$depotTrendResult = $conn->query(
    "SELECT DepotName, DATE_FORMAT(Timestamp, '%Y-%m') AS YearMonth, COUNT(*) AS EventCount
     FROM view_driver_incidents
     WHERE DepotName IS NOT NULL
     GROUP BY DepotName, YearMonth
     ORDER BY YearMonth"
);
while ($row = $depotTrendResult->fetch_assoc()) {
    $depotTrendMonths[$row['YearMonth']] = true;
    $depotTrendByDepot[$row['DepotName']][$row['YearMonth']] = (int) $row['EventCount'];
}
$depotTrendMonths = array_keys($depotTrendMonths);
sort($depotTrendMonths);
$depotTrendSeries = [];
foreach ($depotTrendByDepot as $depotName => $monthCounts) {
    $series = [];
    foreach ($depotTrendMonths as $ym) {
        $series[] = $monthCounts[$ym] ?? 0;
    }
    $depotTrendSeries[] = ['label' => $depotName, 'data' => $series];
}

// ---------------------------------------------------------------
// Driver safety score trend: every driver's monthly score, so fleet
// managers can compare drivers over time (not just against their own
// baseline, which the anomaly table above already covers). Missing
// months for a given driver are left as null so Chart.js draws a gap
// instead of a misleading drop to zero.
// ---------------------------------------------------------------
$driverScoreTrendMonths = [];
$driverScoreTrendByDriver = [];
$driverScoreTrendResult = $conn->query(
    "SELECT d.FullName AS DriverName, msl.Year, msl.Month, msl.Score
     FROM monthly_score_log msl
     JOIN driver d ON msl.DriverID = d.DriverID
     ORDER BY msl.Year, msl.Month"
);
while ($row = $driverScoreTrendResult->fetch_assoc()) {
    $ymKey = ((int) $row['Year']) * 100 + (int) $row['Month'];
    $ymLabel = str_pad((string) $row['Month'], 2, '0', STR_PAD_LEFT) . '/' . $row['Year'];
    $driverScoreTrendMonths[$ymKey] = $ymLabel;
    $driverScoreTrendByDriver[$row['DriverName']][$ymKey] = (int) $row['Score'];
}
ksort($driverScoreTrendMonths);
$driverScoreTrendKeys = array_keys($driverScoreTrendMonths);
$driverScoreTrendLabels = array_values($driverScoreTrendMonths);
$driverScoreTrendSeries = [];
foreach ($driverScoreTrendByDriver as $driverName => $scoresByKey) {
    $series = [];
    foreach ($driverScoreTrendKeys as $key) {
        $series[] = $scoresByKey[$key] ?? null;
    }
    $driverScoreTrendSeries[] = ['label' => $driverName, 'data' => $series];
}

// ---------------------------------------------------------------
// Incident review: search/filter by driver, vehicle, depot, event
// type, severity, resolution status, and date range.
// ---------------------------------------------------------------
$depotOptions = [];
$depotOptResult = $conn->query('SELECT DepotName FROM depot_location ORDER BY DepotName');
while ($row = $depotOptResult->fetch_assoc()) {
    $depotOptions[] = $row['DepotName'];
}

$eventTypeOptions = [];
$eventTypeOptResult = $conn->query('SELECT DISTINCT EventType FROM behaviour_event ORDER BY EventType');
while ($row = $eventTypeOptResult->fetch_assoc()) {
    $eventTypeOptions[] = $row['EventType'];
}

$severityOptions = [];
$severityOptResult = $conn->query(
    "SELECT LevelName FROM severity_level ORDER BY FIELD(LevelName, 'Low', 'Medium', 'High', 'Critical')"
);
while ($row = $severityOptResult->fetch_assoc()) {
    $severityOptions[] = $row['LevelName'];
}

$filterDriver = trim((string) ($_GET['f_driver'] ?? ''));
$filterVehicle = trim((string) ($_GET['f_vehicle'] ?? ''));
$filterDepot = trim((string) ($_GET['f_depot'] ?? ''));
$filterEventType = trim((string) ($_GET['f_event_type'] ?? ''));
$filterSeverity = trim((string) ($_GET['f_severity'] ?? ''));
$filterStatus = trim((string) ($_GET['f_status'] ?? ''));
$filterDateFrom = trim((string) ($_GET['f_date_from'] ?? ''));
$filterDateTo = trim((string) ($_GET['f_date_to'] ?? ''));

$incidentWhere = [];
$incidentParams = [];
$incidentTypes = '';

if ($filterDriver !== '') {
    $incidentWhere[] = 'DriverID = ?';
    $incidentParams[] = $filterDriver;
    $incidentTypes .= 's';
}
if ($filterVehicle !== '') {
    $incidentWhere[] = 'VehiclePlate = ?';
    $incidentParams[] = $filterVehicle;
    $incidentTypes .= 's';
}
if ($filterDepot !== '') {
    $incidentWhere[] = 'DepotName = ?';
    $incidentParams[] = $filterDepot;
    $incidentTypes .= 's';
}
if ($filterEventType !== '') {
    $incidentWhere[] = 'EventType = ?';
    $incidentParams[] = $filterEventType;
    $incidentTypes .= 's';
}
if ($filterSeverity !== '') {
    $incidentWhere[] = 'SeverityLevel = ?';
    $incidentParams[] = $filterSeverity;
    $incidentTypes .= 's';
}
if (in_array($filterStatus, ['Resolved', 'Unresolved'], true)) {
    $incidentWhere[] = 'ResolutionStatus = ?';
    $incidentParams[] = $filterStatus;
    $incidentTypes .= 's';
}
if ($filterDateFrom !== '') {
    $incidentWhere[] = 'DATE(Timestamp) >= ?';
    $incidentParams[] = $filterDateFrom;
    $incidentTypes .= 's';
}
if ($filterDateTo !== '') {
    $incidentWhere[] = 'DATE(Timestamp) <= ?';
    $incidentParams[] = $filterDateTo;
    $incidentTypes .= 's';
}

$incidentSql = 'SELECT * FROM view_incident_resolution';
if ($incidentWhere) {
    $incidentSql .= ' WHERE ' . implode(' AND ', $incidentWhere);
}
$incidentSql .= ' ORDER BY Timestamp DESC';

$incidents = [];
$incidentStmt = $conn->prepare($incidentSql);
if ($incidentParams) {
    $incidentStmt->bind_param($incidentTypes, ...$incidentParams);
}
$incidentStmt->execute();
$incidentResult = $incidentStmt->get_result();
while ($row = $incidentResult->fetch_assoc()) {
    $incidents[] = $row;
}
$incidentStmt->close();
$unresolvedCount = count(array_filter($incidents, static fn (array $row): bool => $row['ResolutionStatus'] === 'Unresolved'));

// ---------------------------------------------------------------
// High-risk drivers and drivers flagged for retraining.
// ---------------------------------------------------------------
$driverRisk = [];
$driverRiskResult = $conn->query(
    'SELECT * FROM view_driver_risk_summary ORDER BY SevereIncidents DESC, TotalIncidents DESC'
);
while ($row = $driverRiskResult->fetch_assoc()) {
    $driverRisk[] = $row;
}

// ---------------------------------------------------------------
// Company-wide risk identification.
// ---------------------------------------------------------------
$repeatSpeedingDrivers = [];
$repeatSpeedingResult = $conn->query('SELECT * FROM view_repeat_speeding_drivers');
while ($row = $repeatSpeedingResult->fetch_assoc()) {
    $repeatSpeedingDrivers[] = $row;
}

$severeIncidentVehicles = [];
$severeIncidentVehicleResult = $conn->query('SELECT * FROM view_severe_incident_vehicles');
while ($row = $severeIncidentVehicleResult->fetch_assoc()) {
    $severeIncidentVehicles[] = $row;
}

$expiredCertifications = [];
$expiredCertResult = $conn->query('SELECT * FROM view_expired_certifications ORDER BY ExpiryDate DESC');
while ($row = $expiredCertResult->fetch_assoc()) {
    $expiredCertifications[] = $row;
}

$unauthorizedVehicleOperation = [];
$unauthorizedResult = $conn->query('SELECT * FROM view_unauthorized_vehicle_operation');
while ($row = $unauthorizedResult->fetch_assoc()) {
    $unauthorizedVehicleOperation[] = $row;
}

// ---------------------------------------------------------------
// Coaching / training compliance: "A driver with a score of 75 or
// below must attend driver coaching. A driver with a safety score of
// 50 or below cannot be assigned to a vehicle until they complete
// safety training." The <=50 half is enforced at the database layer
// (trg_vehicle_driver_assignment_before_insert); this table is how the
// fleet manager sees both halves and knows who to schedule.
// ---------------------------------------------------------------
$coachingRequired = [];
$coachingRequiredResult = $conn->query('SELECT * FROM view_coaching_required');
while ($row = $coachingRequiredResult->fetch_assoc()) {
    $coachingRequired[] = $row;
}

// Drivers currently on safety hold: an unresolved critical incident, per
// "If a critical event happens the driver will be made inactive and
// unable to be assigned to a vehicle until the review has been
// completed or he completes the safety training." Same rule the
// assignment trigger enforces, surfaced here so it's visible before a
// fleet manager even tries to create the assignment.
$safetyHoldDriverIds = [];
$safetyHoldResult = $conn->query(
    "SELECT DISTINCT be.DriverID
     FROM behaviour_event be
     JOIN severity_level sl ON be.SeverityID = sl.SeverityID
     WHERE sl.LevelName = 'Critical'
       AND be.DriverID IS NOT NULL
       AND NOT EXISTS (
           SELECT 1 FROM coaching_log cl WHERE cl.EventID = be.EventID
       )"
);
while ($row = $safetyHoldResult->fetch_assoc()) {
    $safetyHoldDriverIds[$row['DriverID']] = true;
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
                <div>
                    <span>Unresolved incidents</span>
                    <strong><?php echo $unresolvedCount; ?></strong>
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
                    <article class="chart-card chart-card-depot-trend" data-stack-card>
                        <div class="chart-heading">
                            <div>
                                <span>Month over month</span>
                                <h3>Depot safety trend</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="depotTrendChart" role="img" aria-label="Line chart comparing monthly incident counts across depots."></canvas>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="driver-score-visuals" class="dashboard-analysis" data-chart-section aria-labelledby="driver-score-trend-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Month over month</span>
                        <h2 id="driver-score-trend-title">Every driver's safety score, side by side.</h2>
                    </div>
                    <p>
                        One line per driver. Missing months mean that driver had no
                        recorded score that month, not a score of zero.
                    </p>
                </div>
                <article class="chart-card" data-stack-card style="min-height: 26rem;">
                    <div class="chart-wrap">
                        <canvas id="driverScoreTrendChart" role="img" aria-label="Line chart comparing every driver's monthly safety score."></canvas>
                    </div>
                </article>
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

        <section class="admin-directory" aria-labelledby="incident-review-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Incident review</span>
                        <h2 id="incident-review-title">Search, filter, and resolve driver incidents.</h2>
                    </div>
                    <p>
                        Filter by driver, vehicle, depot, event type, severity, resolution
                        status, or date range. Recording a coaching outcome on an
                        unresolved incident marks it resolved.
                    </p>
                </div>

                <form method="GET" class="directory-toolbar" data-reveal data-stack-card>
                    <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end; width:100%;">
                        <div class="field-group">
                            <label for="f_driver">Driver</label>
                            <select id="f_driver" name="f_driver">
                                <option value="">All drivers</option>
                                <?php foreach ($drivers as $d): ?>
                                    <option value="<?php echo escape($d['DriverID']); ?>" <?php echo $filterDriver === $d['DriverID'] ? 'selected' : ''; ?>>
                                        <?php echo escape($d['FullName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="f_vehicle">Vehicle</label>
                            <select id="f_vehicle" name="f_vehicle">
                                <option value="">All vehicles</option>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?php echo escape($v['RegistrationNumber']); ?>" <?php echo $filterVehicle === $v['RegistrationNumber'] ? 'selected' : ''; ?>>
                                        <?php echo escape($v['RegistrationNumber']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="f_depot">Depot</label>
                            <select id="f_depot" name="f_depot">
                                <option value="">All depots</option>
                                <?php foreach ($depotOptions as $depotName): ?>
                                    <option value="<?php echo escape($depotName); ?>" <?php echo $filterDepot === $depotName ? 'selected' : ''; ?>>
                                        <?php echo escape($depotName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="f_event_type">Event type</label>
                            <select id="f_event_type" name="f_event_type">
                                <option value="">All event types</option>
                                <?php foreach ($eventTypeOptions as $eventType): ?>
                                    <option value="<?php echo escape($eventType); ?>" <?php echo $filterEventType === $eventType ? 'selected' : ''; ?>>
                                        <?php echo escape($eventType); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="f_severity">Severity</label>
                            <select id="f_severity" name="f_severity">
                                <option value="">All severities</option>
                                <?php foreach ($severityOptions as $severityName): ?>
                                    <option value="<?php echo escape($severityName); ?>" <?php echo $filterSeverity === $severityName ? 'selected' : ''; ?>>
                                        <?php echo escape($severityName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="f_status">Resolution</label>
                            <select id="f_status" name="f_status">
                                <option value="">All</option>
                                <option value="Unresolved" <?php echo $filterStatus === 'Unresolved' ? 'selected' : ''; ?>>Unresolved</option>
                                <option value="Resolved" <?php echo $filterStatus === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="f_date_from">From</label>
                            <input type="date" id="f_date_from" name="f_date_from" value="<?php echo escape($filterDateFrom); ?>">
                        </div>
                        <div class="field-group">
                            <label for="f_date_to">To</label>
                            <input type="date" id="f_date_to" name="f_date_to" value="<?php echo escape($filterDateTo); ?>">
                        </div>
                        <button type="submit" class="btn btn-search">Filter</button>
                        <a href="dashboard_fleet_mgr.php#incident-review-title" class="btn btn-secondary">Reset</a>
                    </div>
                </form>

                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Driver incident review</caption>
                        <thead>
                            <tr>
                                <th scope="col">Timestamp</th>
                                <th scope="col">Driver</th>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Event type</th>
                                <th scope="col">Severity</th>
                                <th scope="col">Status</th>
                                <th scope="col">Coaching</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($incidents): ?>
                                <?php foreach ($incidents as $incident): ?>
                                    <tr>
                                        <td><?php echo escape($incident['Timestamp']); ?></td>
                                        <td class="cell-strong"><?php echo escape($incident['DriverName'] ?? '—'); ?></td>
                                        <td><?php echo escape($incident['VehiclePlate']); ?></td>
                                        <td><?php echo escape($incident['DepotName'] ?? '—'); ?></td>
                                        <td><?php echo escape($incident['EventType']); ?></td>
                                        <td class="sev-<?php echo escape($incident['SeverityLevel']); ?>">
                                            <span class="severity-badge"><?php echo escape($incident['SeverityLevel']); ?></span>
                                        </td>
                                        <td>
                                            <span class="status-pill status-<?php echo statusSlug($incident['ResolutionStatus']); ?>">
                                                <?php echo escape($incident['ResolutionStatus']); ?>
                                            </span>
                                        </td>
                                        <td class="cell-actions">
                                            <?php if ($incident['ResolutionStatus'] === 'Unresolved'): ?>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="action" value="record_coaching">
                                                    <input type="hidden" name="driver_id" value="<?php echo escape((string) $incident['DriverID']); ?>">
                                                    <input type="hidden" name="event_id" value="<?php echo (int) $incident['EventID']; ?>">
                                                    <label class="sr-only" for="outcome-<?php echo (int) $incident['EventID']; ?>">
                                                        Coaching outcome for event <?php echo (int) $incident['EventID']; ?>
                                                    </label>
                                                    <select id="outcome-<?php echo (int) $incident['EventID']; ?>" name="outcome" required>
                                                        <option value="" disabled selected>Record outcome</option>
                                                        <?php foreach (COACHING_OUTCOMES as $outcomeOption): ?>
                                                            <option value="<?php echo escape($outcomeOption); ?>"><?php echo escape($outcomeOption); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="text" name="notes" placeholder="Notes (optional)">
                                                    <button type="submit" class="btn btn-search">Save</button>
                                                </form>
                                            <?php elseif ($incident['DriverID'] !== null): ?>
                                                <span class="status-pill status-<?php echo statusSlug((string) $incident['CoachingOutcome']); ?>">
                                                    <?php echo escape((string) $incident['CoachingOutcome']); ?>
                                                </span>
                                                <div class="description-cell"><?php echo escape((string) $incident['CoachDate']); ?></div>
                                            <?php else: ?>
                                                <span class="empty-row">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="empty-row">No incidents match these filters.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="risk-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">High-risk drivers</span>
                        <h2 id="risk-title">Who needs attention, and who needs retraining.</h2>
                    </div>
                    <p>
                        Flagged "High risk" at two or more high/critical incidents, or
                        any critical incident. Flagged "Retraining" when a coaching
                        outcome has explicitly recorded it as required.
                    </p>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Driver risk and retraining summary</caption>
                        <thead>
                            <tr>
                                <th scope="col">Driver</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Total incidents</th>
                                <th scope="col">High</th>
                                <th scope="col">Critical</th>
                                <th scope="col">Most recent</th>
                                <th scope="col">Flags</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($driverRisk): ?>
                                <?php foreach ($driverRisk as $risk): ?>
                                    <?php
                                    $isHighRisk = (int) $risk['SevereIncidents'] >= 2 || (int) $risk['CriticalIncidents'] >= 1;
                                    $needsRetraining = (int) $risk['RetrainingFlags'] > 0;
                                    ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($risk['DriverName']); ?></td>
                                        <td><?php echo escape($risk['DepotName'] ?? '—'); ?></td>
                                        <td><?php echo (int) $risk['TotalIncidents']; ?></td>
                                        <td><?php echo (int) $risk['HighIncidents']; ?></td>
                                        <td><?php echo (int) $risk['CriticalIncidents']; ?></td>
                                        <td><?php echo $risk['MostRecentIncident'] !== null ? escape((string) $risk['MostRecentIncident']) : '—'; ?></td>
                                        <td>
                                            <?php if ($isHighRisk): ?>
                                                <span class="status-pill status-high-risk">High risk</span>
                                            <?php endif; ?>
                                            <?php if ($needsRetraining): ?>
                                                <span class="status-pill status-retraining-required">Retraining</span>
                                            <?php endif; ?>
                                            <?php if (!$isHighRisk && !$needsRetraining): ?>
                                                <span class="empty-row">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="empty-row">No drivers recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="patterns-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Risk patterns</span>
                        <h2 id="patterns-title">Repeat speeding, and vehicles tied to severe incidents.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Drivers with repeated speeding incidents</caption>
                        <thead>
                            <tr>
                                <th scope="col">Driver</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Speeding incidents</th>
                                <th scope="col">Most recent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($repeatSpeedingDrivers): ?>
                                <?php foreach ($repeatSpeedingDrivers as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['DriverName']); ?></td>
                                        <td><?php echo escape($row['DepotName'] ?? '—'); ?></td>
                                        <td><?php echo (int) $row['SpeedingIncidents']; ?></td>
                                        <td><?php echo escape((string) $row['MostRecentSpeedingIncident']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="empty-row">No drivers with repeated speeding incidents.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card style="margin-top:1.5rem;">
                    <table class="admin-table">
                        <caption class="sr-only">Vehicles associated with severe incidents</caption>
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Category</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Severe incidents</th>
                                <th scope="col">Most recent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($severeIncidentVehicles): ?>
                                <?php foreach ($severeIncidentVehicles as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['VehiclePlate']); ?></td>
                                        <td><?php echo escape($row['VehicleCategory'] ?? '—'); ?></td>
                                        <td><?php echo escape($row['DepotName'] ?? '—'); ?></td>
                                        <td><?php echo (int) $row['SevereIncidentCount']; ?></td>
                                        <td><?php echo escape((string) $row['MostRecentSevereIncident']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-row">No vehicles associated with severe incidents.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="compliance-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Certification compliance</span>
                        <h2 id="compliance-title">Expired certifications, and drivers outside their authorised category.</h2>
                    </div>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Drivers with expired certifications</caption>
                        <thead>
                            <tr>
                                <th scope="col">Driver</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Certification</th>
                                <th scope="col">Expired</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($expiredCertifications): ?>
                                <?php foreach ($expiredCertifications as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['DriverName']); ?></td>
                                        <td><?php echo escape($row['AssignedDepot'] ?? '—'); ?></td>
                                        <td><?php echo escape($row['CertificationName']); ?></td>
                                        <td class="sev-Critical"><?php echo escape((string) $row['ExpiryDate']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="empty-row">No expired certifications.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card style="margin-top:1.5rem;">
                    <table class="admin-table">
                        <caption class="sr-only">Drivers operating outside their authorised vehicle category</caption>
                        <thead>
                            <tr>
                                <th scope="col">Driver</th>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Category</th>
                                <th scope="col">Missing certification</th>
                                <th scope="col">Assigned since</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($unauthorizedVehicleOperation): ?>
                                <?php foreach ($unauthorizedVehicleOperation as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['DriverName']); ?></td>
                                        <td><?php echo escape($row['VehiclePlate']); ?></td>
                                        <td><?php echo escape($row['VehicleCategory'] ?? '—'); ?></td>
                                        <td class="sev-Critical"><?php echo escape($row['MissingCertification']); ?></td>
                                        <td><?php echo escape((string) $row['AssignmentStart']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-row">No drivers operating outside their authorised vehicle category.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="admin-directory" aria-labelledby="coaching-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Coaching &amp; training compliance</span>
                        <h2 id="coaching-title">Score-driven coaching and training requirements.</h2>
                    </div>
                    <p>
                        A driver whose most recent monthly score is 75 or below must
                        attend driver coaching. At 50 or below, the database blocks
                        any new vehicle assignment for that driver until they
                        complete safety training.
                    </p>
                </div>
                <div class="admin-table-shell" data-reveal data-stack-card>
                    <table class="admin-table">
                        <caption class="sr-only">Drivers requiring coaching or safety training</caption>
                        <thead>
                            <tr>
                                <th scope="col">Driver</th>
                                <th scope="col">Depot</th>
                                <th scope="col">Month</th>
                                <th scope="col">Score</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($coachingRequired): ?>
                                <?php foreach ($coachingRequired as $row): ?>
                                    <tr>
                                        <td class="cell-strong"><?php echo escape($row['DriverName']); ?></td>
                                        <td><?php echo escape($row['DepotName'] ?? '—'); ?></td>
                                        <td><?php echo str_pad((string) $row['Month'], 2, '0', STR_PAD_LEFT) . '/' . $row['Year']; ?></td>
                                        <td><?php echo (int) $row['Score']; ?></td>
                                        <td>
                                            <span class="status-pill status-<?php echo statusSlug($row['ComplianceStatus']); ?>">
                                                <?php echo escape($row['ComplianceStatus']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-row">No drivers currently require coaching or training.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                                        <td class="cell-strong">
                                            <?php echo escape($driver['FullName']); ?>
                                            <?php if (isset($safetyHoldDriverIds[$driver['DriverID']])): ?>
                                                <div><span class="status-pill status-critical">Safety hold — critical incident pending review</span></div>
                                            <?php endif; ?>
                                        </td>
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
        const depotTrendMonths = <?php echo json_encode($depotTrendMonths); ?>;
        const depotTrendSeries = <?php echo json_encode($depotTrendSeries); ?>;
        const depotTrendColors = ['#285f77', '#42695e', '#a97221', '#b83d29', '#742a23'];
        const driverScoreTrendLabels = <?php echo json_encode($driverScoreTrendLabels); ?>;
        const driverScoreTrendSeries = <?php echo json_encode($driverScoreTrendSeries); ?>;
        const driverScoreTrendColors = [
            '#285f77', '#42695e', '#a97221', '#b83d29', '#742a23',
            '#5b4b8a', '#1f7a63', '#c85321', '#3d5a80', '#8a4f7d',
            '#4a7c59', '#9a3b3b'
        ];

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

        new Chart(document.getElementById('depotTrendChart'), {
            type: 'line',
            data: {
                labels: depotTrendMonths,
                datasets: depotTrendSeries.map((series, index) => ({
                    label: series.label,
                    data: series.data,
                    borderColor: depotTrendColors[index % depotTrendColors.length],
                    backgroundColor: 'transparent',
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } }
            }
        });

        new Chart(document.getElementById('driverScoreTrendChart'), {
            type: 'line',
            data: {
                labels: driverScoreTrendLabels,
                datasets: driverScoreTrendSeries.map((series, index) => ({
                    label: series.label,
                    data: series.data,
                    borderColor: driverScoreTrendColors[index % driverScoreTrendColors.length],
                    backgroundColor: 'transparent',
                    spanGaps: true,
                    tension: 0.3,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    borderWidth: 1.5
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { min: 0, max: 100 } }
            }
        });
    </script>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
