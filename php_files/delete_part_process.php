<?php
/**
 * Deletes a part outright. If the part is already referenced by
 * activity_instance_part_used or warranty_part_list, MySQL's default FK
 * behavior (RESTRICT) blocks the delete - we just catch that and show a
 * friendly message instead of a raw SQL error.
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

$partId = $_POST['part_id'] ?? null;

if (!$partId) {
    show_error('A part is required.');
}

$stmt = $conn->prepare("DELETE FROM part WHERE PartID = ?");
$stmt->bind_param("i", $partId);

try {
    if ($stmt->execute()) {
        $stmt->close();
        log_action($conn, 'part', (string) $partId, "Deleted part #{$partId}", 'DELETE');
        $conn->close();
        header("Location: manage_fleet.php?part_deleted=1");
        exit;
    }
} catch (mysqli_sql_exception $e) {
    $stmt->close();
    $conn->close();
    show_error('This part is already in use (on a maintenance activity or warranty claim) and cannot be deleted.');
}

$stmt->close();
$conn->close();
show_error('Could not delete part.');
