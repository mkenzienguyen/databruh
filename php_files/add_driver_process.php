<?php
/**
 * Processes a "new driver" form.
 * Expected POST fields: first_name, last_name, license_number, status
 * (status defaults to 'Active' if left blank)
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

$firstName     = trim($_POST['first_name'] ?? '');
$lastName      = trim($_POST['last_name'] ?? '');
$licenseNumber = trim($_POST['license_number'] ?? '');
$status        = trim($_POST['status'] ?? '') ?: 'Active';

if ($firstName === '' || $lastName === '' || $licenseNumber === '') {
    show_error('First name, last name, and license number are required.');
}

$stmt = $conn->prepare(
    "INSERT INTO driver (FirstName, LastName, LicenseNumber, Status) VALUES (?, ?, ?, ?)"
);
$stmt->bind_param("ssss", $firstName, $lastName, $licenseNumber, $status);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header("Location: home_page.php?driver_added=1");
    exit;
}

$stmt->close();
$conn->close();
show_error('Could not add driver - check that the license number isn\'t already in use.');
