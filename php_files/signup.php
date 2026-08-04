<?php
session_start();
require_once __DIR__ . '/includes/layout.php';

$passwordConn = new mysqli('localhost', 'root', '', 'databruh_password_db');
if ($passwordConn->connect_error) {
    die('Account services are temporarily unavailable. Please try again later.');
}

$roleOptions = [];
$roleResult = $passwordConn->query(
    "SELECT TypeID, TypeName FROM account_type
     WHERE TypeID <> 'ADMIN'
     ORDER BY FIELD(TypeID, 'DRIVER', 'MECHANIC', 'FLEET_MGR', 'WS_MGR')"
);
while ($row = $roleResult->fetch_assoc()) {
    $roleOptions[] = $row;
}

$linkedIds = [];
$linkedResult = $passwordConn->query('SELECT LinkedID FROM account WHERE LinkedID IS NOT NULL');
while ($row = $linkedResult->fetch_assoc()) {
    $linkedIds[] = $row['LinkedID'];
}
$passwordConn->close();

$fleetConn = new mysqli('localhost', 'root', '', 'databruh_db');
if ($fleetConn->connect_error) {
    die('Account services are temporarily unavailable. Please try again later.');
}

$driverOptions = [];
$driverResult = $fleetConn->query('SELECT DriverID, FullName FROM driver ORDER BY FullName');
while ($row = $driverResult->fetch_assoc()) {
    if (!in_array($row['DriverID'], $linkedIds, true)) {
        $driverOptions[] = $row;
    }
}

$mechanicOptions = [];
$mechanicResult = $fleetConn->query('SELECT MechanicID, FullName FROM mechanic_worker ORDER BY FullName');
while ($row = $mechanicResult->fetch_assoc()) {
    if (!in_array($row['MechanicID'], $linkedIds, true)) {
        $mechanicOptions[] = $row;
    }
}
$fleetConn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta
        name="description"
        content="Create a Databruh driver account for secure smart fleet access."
    >
    <title>Sign Up - Databruh</title>
    <link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="../css_files/design_system.css">
    <link rel="stylesheet" href="../css_files/signup.css">
    <link rel="stylesheet" href="../css_files/minimalist_theme.css">
    <link rel="stylesheet" href="../css_files/swiss_bento_theme.css">
</head>
<body class="auth-page">
    <a class="skip-link" href="#main-content">Skip to sign up</a>
    <?php renderSiteNavigation('signup'); ?>

    <main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
        <div class="site-marquee" aria-hidden="true">
            <div class="marquee-track">
                <div class="marquee-group">
                    <span>Verified identity</span><i></i>
                    <span>Driver role</span><i></i>
                    <span>Certification-aware</span><i></i>
                    <span>History preserved</span><i></i>
                </div>
                <div class="marquee-group">
                    <span>Verified identity</span><i></i>
                    <span>Driver role</span><i></i>
                    <span>Certification-aware</span><i></i>
                    <span>History preserved</span><i></i>
                </div>
            </div>
        </div>

        <section id="signup-form" class="auth-workspace" aria-labelledby="signup-title">
            <div class="auth-shell">
                <div class="auth-intro" data-reveal>
                    <span class="section-kicker">Create a secure identity</span>
                    <h2 id="signup-title">Pick your role — your dashboard follows.</h2>
                    <p data-scrub-text>
                        Your name and email identify the account, the password is
                        securely hashed, and the role you choose decides exactly what
                        your dashboard shows after you log in. Driver and Mechanic
                        accounts link to an existing operational record so their
                        dashboard shows only their own history.
                    </p>

                    <dl class="auth-details">
                        <div>
                            <dt>Available roles</dt>
                            <dd>Fleet Manager, Workshop Manager, Mechanic, Driver</dd>
                        </div>
                        <div>
                            <dt>Operational records</dt>
                            <dd>Linked, not duplicated</dd>
                        </div>
                    </dl>

                    <button
                        class="button button-secondary"
                        type="button"
                        data-detail-modal-open="signup-onboarding-details"
                        aria-haspopup="dialog"
                        aria-controls="signup-onboarding-details"
                        aria-expanded="false"
                    >
                        View onboarding details
                    </button>
                </div>

                <div class="auth-form-card" data-reveal data-stack-card>
                    <div class="auth-form-heading">
                        <div>
                            <p class="form-caption">New account</p>
                            <h2>Sign up</h2>
                        </div>
                        <span class="auth-lock auth-lock-add" aria-hidden="true"></span>
                    </div>

                    <form action="signup_process.php" method="POST" class="auth-form">
                        <div class="field-group">
                            <label for="fullname">Full name</label>
                            <input
                                type="text"
                                id="fullname"
                                name="fullname"
                                autocomplete="name"
                                required
                            >
                        </div>

                        <div class="field-group">
                            <label for="email">Email address</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                autocomplete="email"
                                required
                            >
                        </div>

                        <div class="field-group">
                            <label for="type_id">Account role</label>
                            <select id="type_id" name="type_id" required data-role-select>
                                <option value="" disabled selected>Select your role</option>
                                <?php foreach ($roleOptions as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role['TypeID']); ?>">
                                        <?php echo htmlspecialchars($role['TypeName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field-group" data-role-link="DRIVER" hidden>
                            <label for="linked_id_driver">Your driver record</label>
                            <select id="linked_id_driver" name="linked_id_driver">
                                <option value="" disabled selected>Select your name</option>
                                <?php foreach ($driverOptions as $driver): ?>
                                    <option value="<?php echo htmlspecialchars($driver['DriverID']); ?>">
                                        <?php echo htmlspecialchars($driver['FullName']); ?>
                                        (<?php echo htmlspecialchars($driver['DriverID']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-hint">
                                Links this account to your existing driver record so your
                                dashboard shows only your own history.
                            </p>
                        </div>

                        <div class="field-group" data-role-link="MECHANIC" hidden>
                            <label for="linked_id_mechanic">Your mechanic record</label>
                            <select id="linked_id_mechanic" name="linked_id_mechanic">
                                <option value="" disabled selected>Select your name</option>
                                <?php foreach ($mechanicOptions as $mechanic): ?>
                                    <option value="<?php echo htmlspecialchars($mechanic['MechanicID']); ?>">
                                        <?php echo htmlspecialchars($mechanic['FullName']); ?>
                                        (<?php echo htmlspecialchars($mechanic['MechanicID']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-hint">
                                Links this account to your existing mechanic record so your
                                dashboard shows only your own assigned tasks.
                            </p>
                        </div>

                        <div class="field-group">
                            <label for="password">Password</label>
                            <div class="password-field">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    autocomplete="new-password"
                                    aria-describedby="signup-password-strength"
                                    data-password-input="signup-password-strength"
                                    required
                                >
                                <button
                                    class="password-toggle"
                                    type="button"
                                    data-password-toggle
                                    aria-controls="password"
                                    aria-pressed="false"
                                >
                                    Show
                                </button>
                            </div>
                            <div
                                id="signup-password-strength"
                                class="password-strength"
                                data-score="0"
                                role="status"
                                aria-live="polite"
                            >
                                <span
                                    class="password-strength-track"
                                    role="meter"
                                    aria-label="Password strength"
                                    aria-valuemin="0"
                                    aria-valuemax="4"
                                    aria-valuenow="0"
                                >
                                    <span class="password-strength-fill" data-strength-fill></span>
                                </span>
                                <span class="password-strength-meta">
                                    <span data-strength-copy>Strength appears as you type</span>
                                    <span data-strength-count>0/4 checks</span>
                                </span>
                            </div>
                        </div>

                        <div class="field-group">
                            <label for="confirm_password">Confirm password</label>
                            <div class="password-field">
                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    autocomplete="new-password"
                                    required
                                >
                                <button
                                    class="password-toggle"
                                    type="button"
                                    data-password-toggle
                                    aria-controls="confirm_password"
                                    aria-pressed="false"
                                >
                                    Show
                                </button>
                            </div>
                        </div>

                        <button class="button button-primary auth-submit" type="submit">
                            Create account
                        </button>
                    </form>

                    <p class="auth-footnote">
                        Already have an account? <a href="./login.php">Log in</a>
                    </p>
                </div>
            </div>
        </section>

        <section class="auth-proof-section" aria-labelledby="signup-proof-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Account before assignment</span>
                        <h2 id="signup-proof-title">Secure entry without flattening fleet history.</h2>
                    </div>
                    <p>
                        The form keeps its original names and endpoint so server-side
                        validation, duplicate checks, hashing, and redirects remain intact.
                        Driver certifications and assignment eligibility stay separate.
                    </p>
                </div>

                <div class="auth-proof-grid">
                    <article data-reveal data-stack-card>
                        <span>Identity</span>
                        <h3>Validate</h3>
                        <p>Required fields, email format, and matching passwords are checked.</p>
                    </article>
                    <article data-reveal data-stack-card>
                        <span>Credential</span>
                        <h3>Protect</h3>
                        <p>The password is hashed before the account reaches the database.</p>
                    </article>
                    <article data-reveal data-stack-card>
                        <span>Fleet access</span>
                        <h3>Assign</h3>
                        <p>The Driver role is assigned without altering certification records.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="site-cta auth-cta" aria-labelledby="signup-cta-title">
            <div>
                <h2 id="signup-cta-title">Already part of the operation?</h2>
                <p>Use your verified email and password to return to the fleet workspace.</p>
            </div>
            <a class="button button-dark" href="./login.php">Log in</a>
        </section>
    </main>

    <?php renderSiteFooter('signup'); ?>
    <?php
    renderDetailModal([
        'id' => 'signup-onboarding-details',
        'kicker' => 'Driver account onboarding',
        'title' => 'A secure identity without rewriting fleet history.',
        'intro' => 'Registration creates the account needed for access while keeping driver certification, assignment, and operational records separate.',
        'items' => [
            [
                'label' => 'Account identity',
                'title' => 'Establish who is joining',
                'body' => 'Full name and email create the stable account identity used by the existing Databruh login flow.',
            ],
            [
                'label' => 'Credential protection',
                'title' => 'Store a protected password',
                'body' => 'Matching passwords are validated and the credential is hashed before the account reaches the database.',
            ],
            [
                'label' => 'Role-aware access',
                'title' => 'Choose the role that matches your job',
                'body' => 'Fleet Manager, Workshop Manager, Mechanic, and Driver accounts each open a dashboard scoped to that responsibility. Administrator access stays admin-provisioned only.',
            ],
        ],
    ]);
    ?>
    <script>
        (function () {
            const roleSelect = document.querySelector('[data-role-select]');
            const linkGroups = document.querySelectorAll('[data-role-link]');

            if (!roleSelect || !linkGroups.length) {
                return;
            }

            function syncLinkGroups() {
                const selectedRole = roleSelect.value;

                linkGroups.forEach((group) => {
                    const matches = group.dataset.roleLink === selectedRole;
                    group.hidden = !matches;

                    const select = group.querySelector('select');
                    if (select) {
                        select.required = matches;
                        if (!matches) {
                            select.value = '';
                        }
                    }
                });
            }

            roleSelect.addEventListener('change', syncLinkGroups);
            syncLinkGroups();
        })();
    </script>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
