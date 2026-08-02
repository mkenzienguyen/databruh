<?php
/**
 * Soft-deletes a driver: sets IsDeleted = 1 and DeletedAt, rather than
 * removing the row, so historical records (behaviour_event,
 * monthly_score_log, driver_certification_owned, etc.) stay intact.
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

$driverId = trim($_POST['driver_id'] ?? '');

if ($driverId === '') {
    show_error('Driver is required.');
}

$stmt = $conn->prepare("UPDATE driver SET IsDeleted = 1, DeletedAt = NOW() WHERE DriverID = ?");
$stmt->bind_param("s", $driverId);

if ($stmt->execute()) {
    $stmt->close();
    log_action($conn, 'driver', $driverId, "Soft-deleted driver {$driverId}", 'DELETE');
    $conn->close();
    header("Location: manage_fleet.php?driver_deleted=1");
    exit;
}

$stmt->close();
$conn->close();
show_error('Could not remove driver.');
