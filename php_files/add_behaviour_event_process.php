<?php
/**
 * Records a new driver behavior event (from telematics) under the schema.
 */

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db_connect_fleet.php';
require_once __DIR__ . '/log_helper.php';

function show_error(string $message): void
{
    echo "<p>" . htmlspecialchars($message) . "</p>";
    echo "<p><a href='javascript:history.back()'>Back</a></p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    show_error('Invalid request.');
}

$vehicleId   = trim($_POST['vehicle_id'] ?? '');
$driverId    = trim($_POST['driver_id'] ?? '');
$driverId    = ($driverId === '') ? null : $driverId;
$depotId     = $_POST['depot_id'] ?? null;
$depotId     = ($depotId === '') ? null : $depotId;
$timestamp   = trim($_POST['timestamp'] ?? '');
$severityId  = $_POST['severity_id'] ?? null;
$severityId  = ($severityId === '') ? null : $severityId;
$eventType   = trim($_POST['event_type'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($vehicleId === '' || $timestamp === '' || $eventType === '') {
    show_error('Vehicle, timestamp, and event type are required.');
}

$stmt = $conn->prepare(
    "INSERT INTO behaviour_event (VehicleID, DriverID, DepotID, Timestamp, SeverityID, EventType, Description)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param(
    "ssisiss",
    $vehicleId, $driverId, $depotId, $timestamp, $severityId, $eventType, $description
);

if ($stmt->execute()) {
    $eventId = $conn->insert_id;
    $stmt->close();
    log_action($conn, 'behaviour_event', (string) $eventId, "Recorded {$eventType} event for vehicle {$vehicleId}" . ($driverId ? " (driver {$driverId})" : ''));
    $conn->close();
    header("Location: manage_fleet.php?event_added=1&event_id=" . $eventId);
    exit;
}

$stmt->close();
$conn->close();
show_error('Could not record event - check that the vehicle, driver, and severity exist.');
