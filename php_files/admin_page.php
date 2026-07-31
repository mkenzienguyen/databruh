<?php
session_start();
require_once __DIR__ . '/includes/layout.php';

$host = "localhost";
$username = "root";
$password = "";
$dbname = "databruh_password_db";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['AccountID']) || $_SESSION['TypeID'] !== 'ADMIN') {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied. System Administrator privileges required.");
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $targetID = intval($_POST['account_id']);

        if ($_POST['action'] === 'update_role') {
            $newTypeID = trim($_POST['type_id']);

            $stmt = $conn->prepare("UPDATE account SET TypeID = ? WHERE AccountID = ?");
            $stmt->bind_param("si", $newTypeID, $targetID);
            if ($stmt->execute()) {
                $message = "Account role successfully updated.";
            } else {
                $message = "Error updating role: " . $conn->error;
            }
            $stmt->close();
        }
        elseif ($_POST['action'] === 'delete_account') {
            if ($targetID === intval($_SESSION['AccountID'])) {
                $message = "Error: You cannot delete your own active administrator account.";
            } else {
                $stmt = $conn->prepare("DELETE FROM account WHERE AccountID = ?");
                $stmt->bind_param("i", $targetID);
                if ($stmt->execute()) {
                    $message = "Account successfully deleted.";
                } else {
                    $message = "Error deleting account: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : "";
if (!empty($search)) {
    $searchParam = "%" . $search . "%";
    $stmt = $conn->prepare("SELECT a.AccountID, a.FullName, a.Email, a.CreatedAt, t.TypeName, t.TypeID
                            FROM account a
                            JOIN account_type t ON a.TypeID = t.TypeID
                            WHERE a.FullName LIKE ? OR a.Email LIKE ?
                            ORDER BY a.AccountID DESC");
    $stmt->bind_param("ss", $searchParam, $searchParam);
} else {
    $stmt = $conn->prepare("SELECT a.AccountID, a.FullName, a.Email, a.CreatedAt, t.TypeName, t.TypeID
                            FROM account a
                            JOIN account_type t ON a.TypeID = t.TypeID
                            ORDER BY a.AccountID DESC");
}
$stmt->execute();
$result = $stmt->get_result();
$visibleAccountCount = $result->num_rows;

$typesResult = $conn->query("SELECT TypeID, TypeName FROM account_type");
$accountTypes = [];
while ($row = $typesResult->fetch_assoc()) {
    $accountTypes[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta
        name="description"
        content="Search Databruh accounts, update assigned roles, and remove accounts as a system administrator."
    >
    <title>Account Management - Databruh</title>
    <link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="../css_files/design_system.css">
    <link rel="stylesheet" href="../css_files/admin_page.css">
    <link rel="stylesheet" href="../css_files/minimalist_theme.css">
    <link rel="stylesheet" href="../css_files/swiss_bento_theme.css">
    <script>
        function confirmRoleChange(selectElement) {
            const roleName = selectElement.options[selectElement.selectedIndex].text;
            if (confirm(`Are you sure you want to change this account's role to "${roleName}"?`)) {
                selectElement.form.submit();
            } else {
                selectElement.value = selectElement.getAttribute('data-original');
            }
        }

        function storeOriginalRole(selectElement) {
            selectElement.setAttribute('data-original', selectElement.value);
        }
    </script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to account management</a>
    <?php renderSiteNavigation('admin'); ?>

    <main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
        <section class="site-hero admin-hero" aria-labelledby="admin-title">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="site-hero-content">
                <p class="eyebrow" data-hero-item>Operational access governance</p>
                <h1 id="admin-title" class="max-w-6xl" data-hero-item>
                    Govern
                    <span class="hero-inline-image" aria-hidden="true"></span>
                    <br>every fleet<br> role.
                </h1>
                <p class="hero-copy" data-hero-item>
                    Keep safety, workshop, mechanic, driver, and administrator access
                    aligned with real operational responsibility.
                </p>
                <div class="hero-actions" aria-label="Administrator page shortcuts" data-hero-item>
                    <a class="button button-primary" href="#account-directory">Manage accounts</a>
                    <button
                        class="button button-secondary"
                        type="button"
                        data-detail-modal-open="admin-role-details"
                        aria-haspopup="dialog"
                        aria-controls="admin-role-details"
                        aria-expanded="false"
                    >
                        View role details
                    </button>
                </div>
            </div>
            <a class="scroll-cue" href="#admin-summary" aria-label="Scroll to administrator summary">
                <span>Controls below</span>
                <span class="scroll-line" aria-hidden="true"></span>
            </a>
        </section>

        <div class="site-marquee" aria-hidden="true">
            <div class="marquee-track">
                <div class="marquee-group">
                    <span>System administrator</span><i></i>
                    <span>Fleet safety manager</span><i></i>
                    <span>Workshop manager</span><i></i>
                    <span>Mechanic and driver</span><i></i>
                </div>
                <div class="marquee-group">
                    <span>System administrator</span><i></i>
                    <span>Fleet safety manager</span><i></i>
                    <span>Workshop manager</span><i></i>
                    <span>Mechanic and driver</span><i></i>
                </div>
            </div>
        </div>

        <section id="admin-summary" class="admin-summary" aria-label="Account management summary">
            <div class="admin-summary-grid">
                <article data-stack-card>
                    <span>Identities in current result</span>
                    <strong><?php echo $visibleAccountCount; ?></strong>
                </article>
                <article data-stack-card>
                    <span>Operational roles available</span>
                    <strong><?php echo count($accountTypes); ?></strong>
                </article>
                <article data-stack-card>
                    <span>Active administrator</span>
                    <strong><?php echo htmlspecialchars($_SESSION['FullName']); ?></strong>
                </article>
            </div>
        </section>

        <section id="role-blueprint" class="role-blueprint" aria-labelledby="role-blueprint-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Responsibility blueprint</span>
                        <h2 id="role-blueprint-title">Access follows the work, not a generic permission tier.</h2>
                    </div>
                    <p data-scrub-text>
                        Each account type represents a distinct part of the smart fleet
                        operating model described in the project brief.
                    </p>
                </div>

                <div class="role-grid" aria-label="Databruh operational role responsibilities">
                    <article class="role-card role-card-admin" data-reveal data-stack-card>
                        <span>ADMIN</span>
                        <h3>System administrator</h3>
                        <p>Govern identities, assign roles, and protect system-level access.</p>
                    </article>
                    <article class="role-card role-card-fleet" data-reveal data-stack-card>
                        <span>FLEET_MGR</span>
                        <h3>Fleet safety manager</h3>
                        <p>Review incidents, compare depots, monitor risk, and coordinate coaching.</p>
                    </article>
                    <article class="role-card role-card-workshop" data-reveal data-stack-card>
                        <span>WS_MGR</span>
                        <h3>Workshop manager</h3>
                        <p>Prioritise alerts, service bays, workload, parts, downtime, and cost.</p>
                    </article>
                    <article class="role-card role-card-mechanic" data-reveal data-stack-card>
                        <span>MECHANIC</span>
                        <h3>Mechanic</h3>
                        <p>Use diagnostics, maintenance history, previous repairs, and job context.</p>
                    </article>
                    <article class="role-card role-card-driver" data-reveal data-stack-card>
                        <span>DRIVER</span>
                        <h3>Driver</h3>
                        <p>Maintain an accountable identity linked to safety and certification history.</p>
                    </article>
                </div>
            </div>
        </section>

        <section
            id="account-directory"
            class="admin-directory"
            aria-labelledby="directory-title"
        >
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Operational identity directory</span>
                        <h2 id="directory-title">Find the person. Confirm the responsibility.</h2>
                    </div>
                    <p data-scrub-text>
                        Role selections still submit immediately after confirmation,
                        while delete actions retain their separate confirmation step
                        and the active administrator remains protected.
                    </p>
                </div>

                <?php if (!empty($message)): ?>
                    <div
                        class="system-feedback admin-feedback<?php echo str_starts_with($message, 'Error') ? ' is-error' : ''; ?>"
                        role="<?php echo str_starts_with($message, 'Error') ? 'alert' : 'status'; ?>"
                    >
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="directory-toolbar" data-reveal data-stack-card>
                    <div>
                        <span class="toolbar-label">Directory filter</span>
                        <p>
                            <?php if (!empty($search)): ?>
                                Showing matches for “<?php echo htmlspecialchars($search); ?>”
                            <?php else: ?>
                                Showing every available account
                            <?php endif; ?>
                        </p>
                    </div>

                    <form method="GET" class="search-bar">
                        <label class="sr-only" for="account-search">
                            Search by full name or email
                        </label>
                        <input
                            type="text"
                            id="account-search"
                            name="search"
                            placeholder="Search by full name or email"
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                        <button type="submit" class="btn btn-search">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="admin_page.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div
                    class="admin-table-shell"
                    role="region"
                    aria-label="Databruh account directory"
                    tabindex="0"
                    data-reveal
                    data-stack-card
                >
                    <table class="admin-table">
                        <caption class="sr-only">Databruh user accounts and management actions</caption>
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Full name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Current role</th>
                                <th scope="col">Created at</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr<?php echo intval($row['AccountID']) === intval($_SESSION['AccountID']) ? ' class="is-current-account"' : ''; ?>>
                                        <td class="account-id">#<?php echo $row['AccountID']; ?></td>
                                        <td class="account-name">
                                            <?php echo htmlspecialchars($row['FullName']); ?>
                                            <?php if (intval($row['AccountID']) === intval($_SESSION['AccountID'])): ?>
                                                <span class="current-tag">You</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($row['Email']); ?>">
                                                <?php echo htmlspecialchars($row['Email']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <form method="POST" class="inline-form role-form">
                                                <input type="hidden" name="action" value="update_role">
                                                <input
                                                    type="hidden"
                                                    name="account_id"
                                                    value="<?php echo $row['AccountID']; ?>"
                                                >
                                                <label
                                                    class="sr-only"
                                                    for="role-<?php echo $row['AccountID']; ?>"
                                                >
                                                    Role for <?php echo htmlspecialchars($row['FullName']); ?>
                                                </label>
                                                <select
                                                    id="role-<?php echo $row['AccountID']; ?>"
                                                    name="type_id"
                                                    onfocus="storeOriginalRole(this)"
                                                    onchange="confirmRoleChange(this)"
                                                >
                                                    <?php foreach ($accountTypes as $type): ?>
                                                        <option
                                                            value="<?php echo $type['TypeID']; ?>"
                                                            <?php echo ($row['TypeID'] === $type['TypeID']) ? 'selected' : ''; ?>
                                                        >
                                                            <?php echo htmlspecialchars($type['TypeName']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <time datetime="<?php echo htmlspecialchars($row['CreatedAt']); ?>">
                                                <?php echo htmlspecialchars($row['CreatedAt']); ?>
                                            </time>
                                        </td>
                                        <td>
                                            <form
                                                method="POST"
                                                class="inline-form"
                                                onsubmit="return confirm('Are you sure you want to delete this account?');"
                                            >
                                                <input type="hidden" name="action" value="delete_account">
                                                <input
                                                    type="hidden"
                                                    name="account_id"
                                                    value="<?php echo $row['AccountID']; ?>"
                                                >
                                                <button type="submit" class="btn btn-danger">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">No accounts found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="site-cta admin-cta" aria-labelledby="admin-cta-title">
            <div>
                <h2 id="admin-cta-title">Access governance complete for this shift?</h2>
                <p>Review your own operational identity before leaving the workspace.</p>
            </div>
            <a class="button button-dark" href="./account_page.php">Review my account</a>
        </section>
    </main>

    <?php renderSiteFooter('admin'); ?>
    <?php
    renderDetailModal([
        'id' => 'admin-role-details',
        'kicker' => 'Access governance',
        'title' => 'Role changes stay tied to operational responsibility.',
        'intro' => 'This page keeps account discovery, role assignment, and destructive controls inside the existing administrator-only workflow.',
        'items' => [
            [
                'label' => 'Administrator scope',
                'title' => 'Protect the control surface',
                'body' => 'The account directory is available only when the active session carries the ADMIN role.',
            ],
            [
                'label' => 'Role alignment',
                'title' => 'Assign real responsibility',
                'body' => 'Administrator, safety, workshop, mechanic, and driver access can stay aligned with each person’s fleet work.',
            ],
            [
                'label' => 'Guarded changes',
                'title' => 'Confirm before mutation',
                'body' => 'Role updates and deletions retain confirmation steps, and the active administrator cannot delete their own account.',
            ],
        ],
    ]);
    ?>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
