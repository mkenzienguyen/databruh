<?php
/**
 * Vehicle actions:
 *   POST vehicles_process.php?action=add           - add a new vehicle (generates VEH-xxx id)
 *   POST vehicles_process.php?action=update_status - change operational status
 *   POST vehicles_process.php?action=soft_delete   - soft-delete a vehicle
 */
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db_connect_fleet.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/helpers.php';

function vehicles_add(mysqli $conn): void
{
    $registrationNumber = trim($_POST['registration_number'] ?? '');
    $manufacturer       = trim($_POST['manufacturer'] ?? '');
    $model              = trim($_POST['model'] ?? '');
    $classificationId   = $_POST['classification_id'] ?? null;
    $classificationId   = ($classificationId === '') ? null : $classificationId;
    $yearOfManufacture  = $_POST['year_of_manufacture'] ?? null;
    $yearOfManufacture  = ($yearOfManufacture === '') ? null : $yearOfManufacture;
    $statusId           = $_POST['status_id'] ?? null;
    $statusId           = ($statusId === '') ? null : $statusId;
    $depotId            = $_POST['depot_id'] ?? null;
    $depotId            = ($depotId === '') ? null : $depotId;
    $currentOdometer    = trim($_POST['current_odometer'] ?? '');
    $currentOdometer    = ($currentOdometer === '') ? 0 : (int) $currentOdometer;

    if ($registrationNumber === '') {
        show_error('Registration number is required.');
    }

    // ---- Generate the next VehicleID following the VEH-xxx pattern ----
    // (same approach as drivers' D-xxx; same single-user-testing caveat)
    $idResult = $conn->query(
        "SELECT VehicleID FROM vehicle WHERE VehicleID REGEXP '^VEH-[0-9]+$'
         ORDER BY CAST(SUBSTRING(VehicleID, 5) AS UNSIGNED) DESC LIMIT 1"
    );
    $lastRow   = $idResult->fetch_assoc();
    $nextNum   = $lastRow ? ((int) substr($lastRow['VehicleID'], 4) + 1) : 1;
    $vehicleId = 'VEH-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare(
        "INSERT INTO vehicle (VehicleID, RegistrationNumber, Manufacturer, Model, ClassificationID, YearOfManufacture, StatusID, DepotID, CurrentOdometer)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "ssssiiiii",
        $vehicleId, $registrationNumber, $manufacturer, $model, $classificationId, $yearOfManufacture, $statusId, $depotId, $currentOdometer
    );
    if ($stmt->execute()) {
        $stmt->close();
        log_action($conn, 'vehicle', $vehicleId, "Added vehicle {$vehicleId} ({$registrationNumber})");
        $conn->close();
        header("Location: manage_fleet.php?vehicle_added=1&vehicle_id=" . urlencode($vehicleId));
        exit;
    }
    $stmt->close();
    $conn->close();
    show_error('Could not add vehicle - check that the registration number isn\'t already in use.');
}

function vehicles_update_status(mysqli $conn): void
{
    $vehicleId = trim($_POST['vehicle_id'] ?? '');
    $statusId  = $_POST['status_id'] ?? null;

    if ($vehicleId === '' || !$statusId) {
        show_error('Vehicle and new status are required.');
    }

    $stmt = $conn->prepare("UPDATE vehicle SET StatusID = ? WHERE VehicleID = ?");
    $stmt->bind_param("is", $statusId, $vehicleId);
    if ($stmt->execute()) {
        $stmt->close();
        log_action($conn, 'vehicle', $vehicleId, "Updated status of vehicle {$vehicleId} to StatusID {$statusId}", 'UPDATE');
        $conn->close();
        header("Location: manage_fleet.php?status_updated=1");
        exit;
    }
    $stmt->close();
    $conn->close();
    show_error('Could not update vehicle status.');
}

function vehicles_soft_delete(mysqli $conn): void
{
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
}

require_post();

switch ($_GET['action'] ?? $_POST['action'] ?? '') {
    case 'add':
        vehicles_add($conn);
        break;
    case 'update_status':
        vehicles_update_status($conn);
        break;
    case 'soft_delete':
        vehicles_soft_delete($conn);
        break;
    default:
        show_error('Unknown vehicle action.');
}