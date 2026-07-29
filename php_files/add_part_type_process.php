<?php
/**
 * Processes a "new part type" form.
 * Expected POST fields: part_name, manufacturer, current_stock, reorder_level
 * (current_stock defaults to 0, reorder_level defaults to 10 if left blank)
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

$partName     = trim($_POST['part_name'] ?? '');
$manufacturer = trim($_POST['manufacturer'] ?? '');
$currentStock = $_POST['current_stock'] !== '' ? (int) $_POST['current_stock'] : 0;
$reorderLevel = $_POST['reorder_level'] !== '' ? (int) $_POST['reorder_level'] : 10;

if ($partName === '') {
    show_error('Part name is required.');
}

$stmt = $conn->prepare(
    "INSERT INTO part_type (PartName, Manufacturer, CurrentStock, ReorderLevel) VALUES (?, ?, ?, ?)"
);
$stmt->bind_param("ssii", $partName, $manufacturer, $currentStock, $reorderLevel);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header("Location: home_page.php?part_added=1");
    exit;
}

$stmt->close();
$conn->close();
show_error('Could not add part.');
