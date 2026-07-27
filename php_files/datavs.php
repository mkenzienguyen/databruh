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

$driverScores = [];
while ($row = $result3->fetch_assoc()) {
    $name = $row['FullName'];
    $label = str_pad($row['Month'], 2, '0', STR_PAD_LEFT) . "/" . $row['Year'];

    if (!isset($driverScores[$name])) {
        $driverScores[$name] = ["labels" => [], "scores" => []];
    }
    $driverScores[$name]["labels"][] = $label;
    $driverScores[$name]["scores"][] = (int) $row['Score'];
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
<style>
    body {
        font-family: Arial, sans-serif;
        background: #f4f6f8;
        margin: 0;
        padding: 24px;
        color: #1f2937;
    }
    h1 {
        margin-bottom: 4px;
    }
    p.subtitle {
        margin-top: 0;
        color: #6b7280;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-top: 20px;
    }
    .card {
        background: #fff;
        border-radius: 10px;
        padding: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .card h2 {
        font-size: 16px;
        margin: 0 0 12px 0;
    }
    canvas {
        max-height: 300px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    th, td {
        text-align: left;
        padding: 8px 10px;
        border-bottom: 1px solid #e5e7eb;
    }
    th {
        background: #f9fafb;
    }
    .sev-Low { color: #16a34a; font-weight: bold; }
    .sev-Medium { color: #ca8a04; font-weight: bold; }
    .sev-High { color: #ea580c; font-weight: bold; }
    .sev-Critical { color: #dc2626; font-weight: bold; }
    .full-width {
        grid-column: span 2;
    }
</style>
</head>
<body>

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
    data: driverScores[name].scores,
    borderColor: scoreColors[i % scoreColors.length],
    backgroundColor: 'transparent',
    tension: 0.3
}));

const firstDriver = Object.keys(driverScores)[0];
const scoreLabels = firstDriver ? driverScores[firstDriver].labels : [];

new Chart(document.getElementById('scoreChart'), {
    type: 'line',
    data: {
        labels: scoreLabels,
        datasets: scoreDatasets
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { min: 0, max: 100 } }
    }
});
</script>

</body>
</html>