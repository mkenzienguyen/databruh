<?php
/**
 * Driver actions:
 *   POST drivers_process.php?action=add         - add a new driver (generates D-xxx id)
 *   POST drivers_process.php?action=soft_delete - soft-delete a driver
 */
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db_connect_fleet.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/helpers.php';

function drivers_add(mysqli $conn): void
{
    $fullName          = trim($_POST['full_name'] ?? '');
    $licenseNumber     = trim($_POST['license_number'] ?? '');
    $licenseExpiration = trim($_POST['license_expiration'] ?? '');
    $depotId           = $_POST['depot_id'] ?? null;
    $employmentStatus  = trim($_POST['employment_status'] ?? '') ?: 'Active';
    $contactInfo       = trim($_POST['contact_info'] ?? '');
    $emergencyContact  = trim($_POST['emergency_contact'] ?? '');

    if ($fullName === '' || $licenseNumber === '' || $licenseExpiration === '') {
        show_error('Full name, license number, and license expiration are required.');
    }

    // ---- Generate the next DriverID following the D-xxx pattern ----
    $idResult = $conn->query(
        "SELECT DriverID FROM driver WHERE DriverID REGEXP '^D-[0-9]+$'
         ORDER BY CAST(SUBSTRING(DriverID, 3) AS UNSIGNED) DESC LIMIT 1"
    );
    $lastRow  = $idResult->fetch_assoc();
    $nextNum  = $lastRow ? ((int) substr($lastRow['DriverID'], 2) + 1) : 100;
    $driverId = 'D-' . $nextNum;

    $stmt = $conn->prepare(
        "INSERT INTO driver (DriverID, FullName, DepotID, LicenseNumber, LicenseExpiration, EmploymentStatus, ContactInfo, EmergencyContactDetails)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "ssisssss",
        $driverId, $fullName, $depotId, $licenseNumber, $licenseExpiration, $employmentStatus, $contactInfo, $emergencyContact
    );
    if ($stmt->execute()) {
        $stmt->close();
        log_action($conn, 'driver', $driverId, "Added driver {$driverId} ({$fullName})");
        $conn->close();
        header("Location: manage_fleet.php?driver_added=1&driver_id=" . urlencode($driverId));
        exit;
    }
    $stmt->close();
    $conn->close();
    show_error('Could not add driver - check that the license number isn\'t already in use.');
}

function drivers_soft_delete(mysqli $conn): void
{
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
}

require_post();

switch ($_GET['action'] ?? $_POST['action'] ?? '') {
    case 'add':
        drivers_add($conn);
        break;
    case 'soft_delete':
        drivers_soft_delete($conn);
        break;
    default:
        show_error('Unknown driver action.');
}