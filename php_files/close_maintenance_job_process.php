<?php
/**
 * Closes a maintenance job: sets EndDate, Status = 'Closed', and TotalCost.
 *
 * Expected POST fields: job_id, end_date (full datetime), total_cost
 */

require_once __DIR__ . '/require_login.php';
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

$jobId     = $_POST['job_id'] ?? null;
$endDate   = trim($_POST['end_date'] ?? '');
$totalCost = $_POST['total_cost'] ?? null;

if (!$jobId || $endDate === '' || $totalCost === null || $totalCost === '') {
    show_error('Job, end date, and total cost are required.');
}

$stmt = $conn->prepare(
    "UPDATE maintenance_job SET EndDate = ?, Status = 'Closed', TotalCost = ? WHERE JobID = ?"
);
$stmt->bind_param("sii", $endDate, $totalCost, $jobId);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    show_error('Could not close job.');
}
$stmt->close();

// Fetch dates to compute downtime in hours
$dateStmt = $conn->prepare("SELECT StartDate, EndDate FROM maintenance_job WHERE JobID = ?");
$dateStmt->bind_param("i", $jobId);
$dateStmt->execute();
$dates = $dateStmt->get_result()->fetch_assoc();
$dateStmt->close();
$conn->close();

$downtimeHours = round((strtotime($dates['EndDate']) - strtotime($dates['StartDate'])) / 3600, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Job Closed</title></head>
<body>
<h1>Job #<?php echo (int) $jobId; ?> closed</h1>
<p>Downtime: <?php echo $downtimeHours; ?> hour(s)</p>
<p>Total cost: <?php echo number_format((float) $totalCost, 0); ?> VND</p>
<p><a href="manage_fleet.php">Back to Manage Fleet</a></p>
</body>
</html>
