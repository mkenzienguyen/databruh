<?php
session_start();
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/password_policy.php';

$signupPasswordPolicyJson = passwordPolicyClientConfigJson();

$passwordConn = new mysqli('localhost', 'root', '', 'databruh_password_db');
if ($passwordConn->connect_error) {
    die('Account services are temporarily unavailable. Please try again later.');
}

// FLEET_MGR/WS_MGR are granted by an admin in admin_page.php, never at signup.
$roleOptions = [];
$roleResult = $passwordConn->query(
    "SELECT TypeID, TypeName FROM account_type
     WHERE TypeID IN ('DRIVER', 'MECHANIC')
     ORDER BY FIELD(TypeID, 'DRIVER', 'MECHANIC')"
);
while ($row = $roleResult->fetch_assoc()) {
    $roleOptions[] = $row;
}
$passwordConn->close();
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
                        your dashboard shows after you log in. An administrator
                        links your account to your existing driver or mechanic
                        record after signup, so nobody can self-claim someone
                        else's operational history.
                    </p>

                    <dl class="auth-details">
                        <div>
                            <dt>Available roles</dt>
                            <dd>Mechanic, Driver</dd>
                        </div>
                        <div>
                            <dt>Operational records</dt>
                            <dd>Linked by an administrator</dd>
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
                            <select id="type_id" name="type_id" required>
                                <option value="" disabled selected>Select your role</option>
                                <?php foreach ($roleOptions as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role['TypeID']); ?>">
                                        <?php echo htmlspecialchars($role['TypeName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <p class="field-hint">
                            An administrator will link this account to your
                            driver or mechanic record after signup, so your
                            dashboard shows only your own history.
                        </p>

                        <div class="field-group">
                            <label for="password">Password</label>
                            <div class="password-field">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    autocomplete="new-password"
                                    aria-describedby="signup-password-guidance signup-password-strength"
                                    data-password-input="signup-password-strength"
                                    required
                                    minlength="<?php echo DATABRUH_PASSWORD_MIN_LENGTH; ?>"
                                    maxlength="<?php echo DATABRUH_PASSWORD_MAX_LENGTH; ?>"
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
                            <p id="signup-password-guidance" class="field-guidance">
                                Use 15–128 characters with mixed case, a number, and a symbol.
                                Spaces and Unicode characters are allowed.
                            </p>
                            <div
                                id="signup-password-strength"
                                class="password-strength"
                                data-score="0"
                                data-password-policy="<?php echo htmlspecialchars($signupPasswordPolicyJson, ENT_QUOTES, 'UTF-8'); ?>"
                                data-password-name-input="fullname"
                                data-password-email-input="email"
                                role="status"
                                aria-live="polite"
                            >
                                <span
                                    class="password-strength-track"
                                    role="meter"
                                    aria-label="Password requirement progress"
                                    aria-valuemin="0"
                                    aria-valuemax="4"
                                    aria-valuenow="0"
                                >
                                    <span class="password-strength-fill" data-strength-fill></span>
                                </span>
                                <span class="password-strength-meta">
                                    <span data-strength-copy>Complete all four requirements</span>
                                    <span data-strength-count>0/4 requirements</span>
                                </span>
                                <ul class="password-requirements" aria-label="Password requirements">
                                    <li data-password-check="validLength">15–128 printable characters</li>
                                    <li data-password-check="mixedCase">Uppercase and lowercase letters</li>
                                    <li data-password-check="numberAndSymbol">At least one number and one symbol</li>
                                    <li data-password-check="safeChoice">Not common, personal, or Databruh-based</li>
                                </ul>
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
                                    minlength="<?php echo DATABRUH_PASSWORD_MIN_LENGTH; ?>"
                                    maxlength="<?php echo DATABRUH_PASSWORD_MAX_LENGTH; ?>"
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
                'body' => 'Mechanic and Driver accounts each open a dashboard scoped to that responsibility. Fleet Manager, Workshop Manager, and Administrator access are elevated roles granted only by an administrator, never self-selected at signup.',
            ],
        ],
    ]);
    ?>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
