<?php
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Driver Safety Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
    <link rel="stylesheet" href="../css_files/datavs.css">
</head>
<body>
    <header>
        <div class="logo">Databruh</div>
        <nav>
            <a href="./home_page.php">Home</a>
            <a href="./datavs.php">Dashboard</a>
                
            <?php if (isset($_SESSION['AccountID'])): ?>
                <span class="user-greeting">Hi, <?php echo htmlspecialchars($_SESSION['FullName']); ?></span>
                <a class="logout-link" href="./logout_process.php">Log Out</a>
            <?php else: ?>
                <a href="./login.php">Login</a>
                <a href="./signup.php">Sign Up</a>
            <?php endif; ?>
        </nav>
    </header>
<h1>Driver Safety Dashboard</h1>
<p class="subtitle">Live data from the Smart Fleet database</p>

<div class="grid">

    <div class="card">
        <h2>Incidents by Type</h2>
        <canvas id="eventTypeChart"></canvas>
    </div>

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

</div>

<script>
const eventTypeLabels = <?php echo json_encode($eventTypeLabels); ?>;
const eventTypeValues = <?php echo json_encode($eventTypeValues); ?>;

const severityLabels = <?php echo json_encode($severityLabels); ?>;
const severityValues = <?php echo json_encode($severityValues); ?>;

const depotLabels = <?php echo json_encode($depotLabels); ?>;
const depotValues = <?php echo json_encode($depotValues); ?>;

const driverScores = <?php echo json_encode($driverScores); ?>;
const scoreLabels = <?php echo json_encode($scoreLabels); ?>;

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

new Chart(document.getElementById('severityChart'), {
    type: 'doughnut',
    data: {
        labels: severityLabels,
        datasets: [{
            data: severityValues,
            backgroundColor: [
                'rgba(34, 197, 94, 0.8)',
                'rgba(234, 179, 8, 0.8)',
                'rgba(249, 115, 22, 0.8)',
                'rgba(220, 38, 38, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

new Chart(document.getElementById('depotChart'), {
    type: 'bar',
    data: {
        labels: depotLabels,
        datasets: [{
            label: 'Number of Incidents',
            data: depotValues,
            backgroundColor: 'rgba(168, 85, 247, 0.7)'
        }]
    },
    options: {
        responsive: true,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

const scoreColors = [
    'rgb(59, 130, 246)',
    'rgb(234, 88, 12)',
    'rgb(16, 185, 129)',
    'rgb(217, 70, 239)',
    'rgb(220, 38, 38)'
];

const scoreDatasets = Object.keys(driverScores).map((name, i) => ({
    label: name,
    data: driverScores[name],
    borderColor: scoreColors[i % scoreColors.length],
    backgroundColor: 'transparent',
    spanGaps: true,
    tension: 0.3
}));

new Chart(document.getElementById('scoreChart'), {
    type: 'line',
    data: {
        labels: scoreLabels,
        datasets: scoreDatasets
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { min: 50, max: 150 } }
    }
});
</script>

</body>
</html>