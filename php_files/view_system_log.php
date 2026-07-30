<?php
/**
 * View System Log - shows the internal activity feed
 */

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db_connect_fleet.php';

$entityType = trim($_GET['entity_type'] ?? '');

$conditions = [];
$params = [];
$types = '';

if ($entityType !== '') {
    $conditions[] = "EntityType = ?";
    $params[] = $entityType;
    $types .= 's';
}

$whereClause = empty($conditions) ? '' : ('WHERE ' . implode(' AND ', $conditions));

$sql = "SELECT LogID, EntityType, EntityID, Action, Description, LoggedAt
        FROM system_log
        $whereClause
        ORDER BY LoggedAt DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

$entityTypesResult = $conn->query("SELECT DISTINCT EntityType FROM system_log ORDER BY EntityType");
$entityTypes = [];
while ($row = $entityTypesResult->fetch_assoc()) {
    $entityTypes[] = $row['EntityType'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>System Log &mdash; databruh</title>
<style>
    body { font-family: Arial, sans-serif; background: #f4f6f8; padding: 24px; color: #1f2937; }
    header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 16px 40px; display: flex; justify-content: space-between; align-items: center; margin: -24px -24px 20px -24px; }
    .logo { font-size: 18px; font-weight: bold; color: #111827; text-decoration: none; }
    nav a { color: #4b5563; text-decoration: none; margin-left: 24px; font-size: 14px; }
    nav a:hover { color: #111827; }
    h1 { margin-top: 0; }
    form { background: #fff; padding: 16px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 12px; align-items: end; }
    label { display: flex; flex-direction: column; font-size: 13px; color: #4b5563; }
    select { padding: 6px 8px; margin-top: 4px; }
    button { padding: 8px 16px; }
    table { width: 100%; border-collapse: collapse; background: #fff; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
    th { background: #f9fafb; }
    .action-CREATE { color: #166534; font-weight: bold; }
    .action-UPDATE { color: #92400e; font-weight: bold; }
    .action-DELETE { color: #991b1b; font-weight: bold; }
</style>
</head>
<body>

<header>
    <a class="logo" href="./home_page.php">databruh</a>
    <nav>
        <a href="./home_page.php">Home</a>
        <a href="./datavs.php">Dashboard</a>
        <a href="./manage_fleet.php">Manage Fleet</a>
        <a href="./view_system_log.php">System Log</a>
        <a href="./logout_process.php">Log out</a>
    </nav>
</header>

<h1>System Log</h1>
<p style="color:#6b7280;">Internal activity feed, tracks new records being added across the system.</p>

<form method="GET">
    <label>Entity type
        <select name="entity_type">
            <option value="">-- all --</option>
            <?php foreach ($entityTypes as $type): ?>
                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($entityType === $type) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($type); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Filter</button>
</form>

<table>
    <thead>
        <tr><th>Log ID</th><th>Entity Type</th><th>Entity ID</th><th>Action</th><th>Description</th><th>Logged At</th></tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="6">No log entries yet.</td></tr>
        <?php else: foreach ($rows as $row): ?>
            <tr>
                <td><?php echo (int) $row['LogID']; ?></td>
                <td><?php echo htmlspecialchars($row['EntityType']); ?></td>
                <td><?php echo htmlspecialchars($row['EntityID']); ?></td>
                <td class="action-<?php echo htmlspecialchars($row['Action']); ?>"><?php echo htmlspecialchars($row['Action']); ?></td>
                <td><?php echo htmlspecialchars($row['Description']); ?></td>
                <td><?php echo htmlspecialchars($row['LoggedAt']); ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

</body>
</html>
