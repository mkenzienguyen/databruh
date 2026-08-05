<?php
/**
 * Incident / review actions:
 *   POST incidents_process.php?action=add_event  - record a behaviour event (telematics)
 *   POST incidents_process.php?action=add_review - add a review / coaching comment
 */
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db_connect_fleet.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/helpers.php';

function incidents_add_event(mysqli $conn): void
{
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
}

function incidents_add_review(mysqli $conn): void
{
    $eventId      = $_POST['event_id'] ?? null;
    $reviewerName = trim($_POST['reviewer_name'] ?? '');
    $comment      = trim($_POST['comment'] ?? '');

    if (!$eventId || $comment === '') {
        show_error('An incident and a comment are required.');
    }

    $stmt = $conn->prepare(
        "INSERT INTO incident_review (EventID, ReviewerName, Comment) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iss", $eventId, $reviewerName, $comment);
    if ($stmt->execute()) {
        $reviewId = $conn->insert_id;
        $stmt->close();
        log_action($conn, 'incident_review', (string) $reviewId, "Added review comment on event #{$eventId}" . ($reviewerName ? " by {$reviewerName}" : ''));
        $conn->close();
        header("Location: manage_fleet.php?review_added=1");
        exit;
    }
    $stmt->close();
    $conn->close();
    show_error('Could not add review comment - check that the incident exists.');
}

require_post();

switch ($_GET['action'] ?? $_POST['action'] ?? '') {
    case 'add_event':
        incidents_add_event($conn);
        break;
    case 'add_review':
        incidents_add_review($conn);
        break;
    default:
        show_error('Unknown incident action.');
}