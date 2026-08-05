<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/db_connect_fleet.php';
require_once __DIR__ . '/helpers.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
function actionSlug(string $action): string
{
    return strtolower(str_replace(' ', '-', trim($action)));
}

// ---- Role gate: audit feed is ADMIN-only ----
if (!user_can([ROLE_SYS_ADMIN])) {
    header('Location: ' . roleDashboardPath((string) ($_SESSION['TypeID'] ?? '')));
    exit;
}

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
    FROM system_log $whereClause
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
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Internal activity feed for the Databruh fleet system.">
<title>System Log - Databruh</title>
<link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="stylesheet" href="../css_files/design_system.css">
<link rel="stylesheet" href="../css_files/datavs.css">
<link rel="stylesheet" href="../css_files/admin_page.css">
<link rel="stylesheet" href="../css_files/role_dashboards.css">
<link rel="stylesheet" href="../css_files/minimalist_theme.css">
<link rel="stylesheet" href="../css_files/swiss_bento_theme.css">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to system log</a>
<?php renderSiteNavigation('log'); ?>
<main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
<section class="site-hero dashboard-hero" aria-labelledby="log-title">
<div class="hero-grid" aria-hidden="true"></div>
<div class="site-hero-content">
<p class="eyebrow" data-hero-item>Internal activity feed · Administrator</p>
<h1 id="log-title" class="max-w-6xl" data-hero-item>
Every change,
<br>kept on record.
</h1>
<p class="hero-copy" data-hero-item>
Adds, updates, and removals across drivers, vehicles, jobs, and
parts — written automatically by the fleet process endpoints.
</p>
</div>
</section>
<section class="admin-directory" aria-labelledby="log-directory-title">
<div class="section-shell">
<div class="chapter-heading">
<div>
<span class="section-kicker">System log</span>
<h2 id="log-directory-title">Audit trail across the fleet.</h2>
</div>
</div>
<form method="GET" class="directory-toolbar" data-reveal data-stack-card>
<div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
<div class="field-group"><label for="entity-filter">Entity type</label>
<select id="entity-filter" name="entity_type">
<option value="">-- all --</option>
<?php foreach ($entityTypes as $type): ?>
<option value="<?php echo escape($type); ?>" <?php echo ($entityType === $type) ? 'selected' : ''; ?>>
<?php echo escape($type); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<button type="submit" class="btn btn-search">Filter</button>
<?php if ($entityType !== ''): ?>
<a href="view_system_log.php" class="btn btn-secondary">Reset</a>
<?php endif; ?>
</div>
</form>
<div class="admin-table-shell" data-reveal data-stack-card>
<table class="admin-table">
<caption class="sr-only">System activity log</caption>
<thead>
<tr>
<th scope="col">Log ID</th>
<th scope="col">Entity type</th>
<th scope="col">Entity ID</th>
<th scope="col">Action</th>
<th scope="col">Description</th>
<th scope="col">Logged at</th>
</tr>
</thead>
<tbody>
<?php if ($rows): ?>
<?php foreach ($rows as $row): ?>
<tr>
<td class="cell-strong">#<?php echo (int) $row['LogID']; ?></td>
<td><?php echo escape($row['EntityType']); ?></td>
<td><?php echo escape($row['EntityID']); ?></td>
<td>
<span class="status-pill status-<?php echo actionSlug($row['Action']); ?>">
<?php echo escape($row['Action']); ?>
</span>
</td>
<td><?php echo escape($row['Description']); ?></td>
<td><?php echo escape($row['LoggedAt']); ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="6" class="empty-row">No log entries yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</section>
</main>
<?php renderSiteFooter('log'); ?>
<?php renderSiteMotionScripts(); ?>
</body>
</html>