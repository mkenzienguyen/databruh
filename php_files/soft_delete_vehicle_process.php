<?php
/**
 * Soft-deletes a vehicle: sets IsDeleted = 1 and DeletedAt, rather than
 * removing the row, so historical records (maintenance_job, behaviour_event,
 * etc.) that reference this VehicleID stay intact.
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

$vehicleId = trim($_POST['vehicle_id'] ?? '');

if ($vehicleId === '') {
    show_error('Vehicle is required.');
}

$stmt = $conn->prepare("UPDATE vehicle SET IsDeleted = 1, DeletedAt = NOW() WHERE VehicleID = ?");
$stmt->bind_param("s", $vehicleId);

if ($stmt->execute()) {
    $stmt->close();
    log_action($conn, 'vehicle', $vehicleId, "Soft-deleted vehicle {$vehicleId}", 'DELETE');
    $conn->close();
    header("Location: manage_fleet.php?vehicle_deleted=1");
    exit;
}

$stmt->close();
$conn->close();
show_error('Could not remove vehicle.');
