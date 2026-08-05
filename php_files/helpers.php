<?php
/**
 * Shared helpers used by the grouped process endpoints and pages.
 */
require_once __DIR__ . '/includes/layout.php';

const ROLE_SYS_ADMIN        = 'ADMIN';
const ROLE_FLEET_MANAGER    = 'FLEET_MGR';
const ROLE_WORKSHOP_MANAGER = 'WS_MGR';
const ROLE_MECHANIC         = 'MECHANIC';
const ROLE_DRIVER           = 'DRIVER';

/** Roles allowed to manage drivers & vehicles */
const ROLES_FLEET    = [ROLE_SYS_ADMIN, ROLE_FLEET_MANAGER];
/** Roles allowed to manage workshop stuff (jobs & parts) */
const ROLES_WORKSHOP = [ROLE_SYS_ADMIN, ROLE_WORKSHOP_MANAGER];

/**
 * Shows a styled, on-design error page and stops.
 */
function show_error(string $message): void
{
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Action blocked - Databruh</title>
<link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="stylesheet" href="../css_files/design_system.css">
<link rel="stylesheet" href="../css_files/admin_page.css">
<link rel="stylesheet" href="../css_files/role_dashboards.css">
<link rel="stylesheet" href="../css_files/minimalist_theme.css">
<link rel="stylesheet" href="../css_files/swiss_bento_theme.css">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<?php renderSiteNavigation('error'); ?>
<main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
<section class="admin-directory" aria-labelledby="error-title">
<div class="section-shell">
<div class="chapter-heading">
<div>
<span class="section-kicker">Action blocked</span>
<h2 id="error-title">That didn't go through.</h2>
</div>
<p>
The fleet process endpoints validate every request before touching
the database. Nothing was changed.
</p>
</div>
<div class="system-feedback is-error" role="alert">
<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
</div>
<div style="display:flex; flex-wrap:wrap; gap:0.75rem; margin-top:1.25rem;">
<a class="btn btn-secondary" href="javascript:history.back()">Go back</a>
<a class="btn btn-search" href="<?php echo htmlspecialchars(roleDashboardPath((string) ($_SESSION['TypeID'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
Back to my dashboard
</a>
</div>
</div>
</section>
</main>
<?php renderSiteFooter('error'); ?>
</body>
</html>
<?php
exit;
}

/**
 * All process endpoints are POST-only.
 */
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        show_error('Invalid request.');
    }
}

/**
 * Runs a SELECT and returns all rows as an array (for <select> options).
 */
function get_options(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * True if the logged-in user's TypeID is in the given role list.
 */
function user_can(array $allowedRoles): bool
{
    return in_array($_SESSION['TypeID'] ?? '', $allowedRoles, true);
}

/**
 * Backend enforcement. Same behaviour as includes/auth.php requireRole():
 * bounce the user to their own dashboard instead of showing the content.
 */
function require_role(array $allowedRoles): void
{
    if (!user_can($allowedRoles)) {
        header('Location: ' . roleDashboardPath((string) ($_SESSION['TypeID'] ?? '')));
        exit;
    }
}