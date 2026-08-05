<?php
/**
 * Writes one row to system_log. needs to run system_log_table.sql
 */
function log_action(mysqli $conn, string $entityType, string $entityId, string $description, string $action = 'CREATE'): void
{
    $stmt = $conn->prepare(
        "INSERT INTO system_log (EntityType, EntityID, Action, Description) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("ssss", $entityType, $entityId, $action, $description);
    $stmt->execute();
    $stmt->close();
}
