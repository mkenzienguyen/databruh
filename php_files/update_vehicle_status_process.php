<?php
/**
 * Updates a vehicle's operational status (Active, Under Maintenance, Retired, etc).
 * Expected POST fields: vehicle_id, status_id
 * (status_id should come from a <select> populated from vehicle_status)
 */

require_once __DIR__ . '/db_connect_fleet.php';

function show_error(string $message): void
{
    echo "<p>" . htmlspecialchars($message) . "</p>";
    echo "<p><a href='javascript:history.back()'>Back</a></p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    show_error('Invalid request.');
}

$vehicleId = $_POST['vehicle_id'] ?? null;
$statusId  = $_POST['status_id'] ?? null;

if (!$vehicleId || !$statusId) {
    show_error('Vehicle and new status are required.');
}

$stmt = $conn->prepare("UPDATE vehicle SET StatusID = ? WHERE VehicleID = ?");
$stmt->bind_param("ii", $statusId, $vehicleId);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header("Location: home_page.php?status_updated=1");
    exit;
}

$stmt->close();
$conn->close();
show_error('Could not update vehicle status.');
