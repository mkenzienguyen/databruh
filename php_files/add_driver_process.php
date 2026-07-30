<?php
/**
 * Processes a "new driver" form under the schema
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
// Note: not race-condition-safe under simultaneous submissions - fine for a
// student project with one person testing at a time, but flag this if it
// ever needs to handle concurrent form submissions for real.
$idResult = $conn->query(
    "SELECT DriverID FROM driver WHERE DriverID REGEXP '^D-[0-9]+$'
     ORDER BY CAST(SUBSTRING(DriverID, 3) AS UNSIGNED) DESC LIMIT 1"
);
$lastRow = $idResult->fetch_assoc();
$nextNum = $lastRow ? ((int) substr($lastRow['DriverID'], 2) + 1) : 100;
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
