<?php
/**
 * Closes a maintenance job: sets EndDate and Status = 'Closed'.
 * Expected POST fields: job_id, end_date
 *
 * The schema has no stored "downtime hours" or "total cost" columns on
 * maintenance_job, so both are computed here:
 *   - total cost = SUM(Cost) across all activities on this job
 *   - downtime   = EndDate - StartDate (in whole days, since both columns
 *     are DATE not DATETIME - flag to your group if hour-level precision
 *     is actually needed for the brief's example data)
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

$jobId   = $_POST['job_id'] ?? null;
$endDate = $_POST['end_date'] ?? '';

if (!$jobId || $endDate === '') {
    show_error('Job and end date are required.');
}

$stmt = $conn->prepare("UPDATE maintenance_job SET EndDate = ?, Status = 'Closed' WHERE JobID = ?");
$stmt->bind_param("si", $endDate, $jobId);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    show_error('Could not close job.');
}
$stmt->close();

// Compute total cost across this job's activities
$costStmt = $conn->prepare("SELECT COALESCE(SUM(Cost), 0) AS TotalCost FROM activity_issued WHERE JobID = ?");
$costStmt->bind_param("i", $jobId);
$costStmt->execute();
$totalCost = $costStmt->get_result()->fetch_assoc()['TotalCost'];
$costStmt->close();

// Compute downtime from the job's dates
$dateStmt = $conn->prepare("SELECT StartDate, EndDate FROM maintenance_job WHERE JobID = ?");
$dateStmt->bind_param("i", $jobId);
$dateStmt->execute();
$dates = $dateStmt->get_result()->fetch_assoc();
$dateStmt->close();
$conn->close();

$downtimeDays = (strtotime($dates['EndDate']) - strtotime($dates['StartDate'])) / 86400;
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Job Closed</title></head>
<body>
<h1>Job #<?php echo (int) $jobId; ?> closed</h1>
<p>Downtime: <?php echo (int) $downtimeDays; ?> day(s)</p>
<p>Total cost: <?php echo number_format((float) $totalCost, 2); ?> VND (sum of all activities on this job)</p>
<p><a href="home_page.php">Back to dashboard</a></p>
</body>
</html>
