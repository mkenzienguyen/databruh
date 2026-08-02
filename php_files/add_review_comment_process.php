<?php
/**
 * Adds a review comment / coaching recommendation to an existing
 * behaviour_event, stored in incident_review.
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
