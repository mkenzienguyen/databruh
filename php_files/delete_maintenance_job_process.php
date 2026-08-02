<?php
/**
 * Deletes a maintenance job outright - only permitted while the job is
 * still 'Open' (i.e. a draft with no close date/cost recorded yet). Once
 * a job has been closed it's historical data and should be soft-deleted
 * instead, not removed - this route intentionally won't touch those.
 * Nested activity_instance rows are removed automatically via
 * ON DELETE CASCADE.
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

$jobId = $_POST['job_id'] ?? null;

if (!$jobId) {
    show_error('A job is required.');
}

$checkStmt = $conn->prepare("SELECT Status FROM maintenance_job WHERE JobID = ?");
$checkStmt->bind_param("i", $jobId);
$checkStmt->execute();
$job = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if (!$job) {
    show_error('Job not found.');
}

if ($job['Status'] === 'Closed') {
    show_error('This job has already been closed and is part of the historical record - it cannot be deleted.');
}

$stmt = $conn->prepare("DELETE FROM maintenance_job WHERE JobID = ?");
$stmt->bind_param("i", $jobId);

if ($stmt->execute()) {
    $stmt->close();
    log_action($conn, 'maintenance_job', (string) $jobId, "Deleted draft maintenance job #{$jobId}", 'DELETE');
    $conn->close();
    header("Location: manage_fleet.php?job_deleted=1");
    exit;
}

$stmt->close();
$conn->close();
show_error('Could not delete job.');
