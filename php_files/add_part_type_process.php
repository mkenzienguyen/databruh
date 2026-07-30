<?php
/**
 * Processes a "new part" form under the schema.
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

$partName          = trim($_POST['part_name'] ?? '');
$primarySupplierId = $_POST['primary_supplier_id'] ?? null;
$backupSupplierId  = $_POST['backup_supplier_id'] ?? null;
$backupSupplierId  = ($backupSupplierId === '') ? null : $backupSupplierId;

if ($partName === '' || !$primarySupplierId) {
    show_error('Part name and a primary supplier are required.');
}

$stmt = $conn->prepare(
    "INSERT INTO part (PartName, PrimarySupplierID, BackupSupplierID) VALUES (?, ?, ?)"
);
$stmt->bind_param("sii", $partName, $primarySupplierId, $backupSupplierId);

if ($stmt->execute()) {
    $partId = $conn->insert_id;
    $stmt->close();
    log_action($conn, 'part', (string) $partId, "Added part \"{$partName}\" (PartID {$partId})");
    $conn->close();
    header("Location: manage_fleet.php?part_added=1");
    exit;
}

$stmt->close();
$conn->close();
show_error('Could not add part - check that the supplier(s) exist.');
