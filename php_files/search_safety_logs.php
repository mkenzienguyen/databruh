<?php
/**
 * Safety log search
 */

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db_connect_fleet.php';

$driverId   = trim($_GET['driver_id']   ?? '');
$vehicleId  = trim($_GET['vehicle_id']  ?? '');
$depotId    = $_GET['depot_id']    ?? '';
$severityId = $_GET['severity_id'] ?? '';
$eventType  = trim($_GET['event_type']  ?? '');
$dateFrom   = $_GET['date_from']   ?? '';
$dateTo     = $_GET['date_to']     ?? '';

$conditions = [];
$params = [];
$types = '';

if ($driverId !== '')   { $conditions[] = "e.DriverID = ?";    $params[] = $driverId;              $types .= 's'; }
if ($vehicleId !== '')  { $conditions[] = "e.VehicleID = ?";   $params[] = $vehicleId;              $types .= 's'; }
if ($depotId !== '')    { $conditions[] = "e.DepotID = ?";     $params[] = $depotId;                $types .= 'i'; }
if ($severityId !== '') { $conditions[] = "e.SeverityID = ?";  $params[] = $severityId;             $types .= 'i'; }
if ($eventType !== '')  { $conditions[] = "e.EventType = ?";   $params[] = $eventType;              $types .= 's'; }
if ($dateFrom !== '')   { $conditions[] = "e.Timestamp >= ?";  $params[] = $dateFrom . ' 00:00:00'; $types .= 's'; }
if ($dateTo !== '')     { $conditions[] = "e.Timestamp <= ?";  $params[] = $dateTo . ' 23:59:59';   $types .= 's'; }

$whereClause = empty($conditions) ? '' : ('WHERE ' . implode(' AND ', $conditions));

$sql = "SELECT e.EventID, e.Timestamp, v.RegistrationNumber, d.FullName AS DriverName,
               dep.DepotName, sl.LevelName AS Severity, e.EventType, e.Description
        FROM behaviour_event e
        JOIN vehicle v ON e.VehicleID = v.VehicleID
        LEFT JOIN driver d ON e.DriverID = d.DriverID
        LEFT JOIN depot_location dep ON e.DepotID = dep.DepotID
        LEFT JOIN severity_level sl ON e.SeverityID = sl.SeverityID
        $whereClause
        ORDER BY e.Timestamp DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Safety Log Search</title>
<style>
    body { font-family: Arial, sans-serif; background: #f4f6f8; padding: 24px; color: #1f2937; }
    form { background: #fff; padding: 16px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: end; }
    label { display: flex; flex-direction: column; font-size: 13px; color: #4b5563; }
    input { padding: 6px 8px; margin-top: 4px; }
    button { padding: 8px 16px; }
    table { width: 100%; border-collapse: collapse; background: #fff; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
    th { background: #f9fafb; }
</style>
</head>
<body>

<h1>Safety Log Search</h1>

<!-- Placeholder filter UI - frontend should replace raw ID inputs with
     proper dropdowns showing names, and event_type with a <select>
     populated from DISTINCT EventType values. -->
<form method="GET">
    <label>Driver ID <input type="text" name="driver_id" value="<?php echo htmlspecialchars($driverId); ?>" placeholder="D-112"></label>
    <label>Vehicle ID <input type="text" name="vehicle_id" value="<?php echo htmlspecialchars($vehicleId); ?>" placeholder="VEH-001"></label>
    <label>Depot ID <input type="number" name="depot_id" value="<?php echo htmlspecialchars($depotId); ?>"></label>
    <label>Severity ID <input type="number" name="severity_id" value="<?php echo htmlspecialchars($severityId); ?>"></label>
    <label>Event Type <input type="text" name="event_type" value="<?php echo htmlspecialchars($eventType); ?>" placeholder="Speeding"></label>
    <label>From <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"></label>
    <label>To <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"></label>
    <button type="submit">Search</button>
</form>

<table>
    <thead>
        <tr><th>Timestamp</th><th>Vehicle</th><th>Driver</th><th>Depot</th><th>Event Type</th><th>Severity</th><th>Description</th></tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="7">No matching events.</td></tr>
        <?php else: foreach ($rows as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['Timestamp']); ?></td>
                <td><?php echo htmlspecialchars($row['RegistrationNumber']); ?></td>
                <td><?php echo htmlspecialchars($row['DriverName'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($row['DepotName'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($row['EventType']); ?></td>
                <td><?php echo htmlspecialchars($row['Severity'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($row['Description']); ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

</body>
</html>
