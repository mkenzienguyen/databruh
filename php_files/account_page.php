<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['AccountID'])) {
    header('Location: login.php?account=required');
    exit();
}

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/password_policy.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function firstCharacter(string $value): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 1, 'UTF-8');
    }

    return substr($value, 0, 1);
}

$conn = new mysqli('localhost', 'root', '', 'databruh_password_db');

if ($conn->connect_error) {
    http_response_code(503);
    die('Account services are temporarily unavailable. Please try again later.');
}

$conn->set_charset('utf8mb4');

$accountId = (int) $_SESSION['AccountID'];
$accountStatement = $conn->prepare(
    'SELECT
        a.AccountID,
        a.FullName,
        a.Email,
        a.Password,
        a.CreatedAt,
        a.TypeID,
        a.LinkedID,
        t.TypeName
     FROM account a
     INNER JOIN account_type t ON a.TypeID = t.TypeID
     WHERE a.AccountID = ?
     LIMIT 1'
);

if (!$accountStatement) {
    http_response_code(500);
    die('The account could not be loaded.');
}

$accountStatement->bind_param('i', $accountId);
$accountStatement->execute();
$accountResult = $accountStatement->get_result();
$account = $accountResult->fetch_assoc();
$accountStatement->close();

if (!$account) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php?account=missing');
    exit();
}

$_SESSION['FullName'] = $account['FullName'];
$_SESSION['Email'] = $account['Email'];
$_SESSION['TypeID'] = $account['TypeID'];
$_SESSION['LinkedID'] = $account['LinkedID'];

$accountPasswordPolicyJson = passwordPolicyClientConfigJson(
    (string) $account['FullName'],
    (string) $account['Email']
);

if (empty($_SESSION['account_csrf_token'])) {
    $_SESSION['account_csrf_token'] = bin2hex(random_bytes(32));
}

$feedback = $_SESSION['account_feedback'] ?? null;
unset($_SESSION['account_feedback']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $errors = [];
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) $_SESSION['account_csrf_token'];
    $action = (string) ($_POST['action'] ?? '');

    if (!hash_equals($sessionToken, $submittedToken)) {
        $errors[] = 'Your security token expired. Refresh the page and try again.';
    }

    if ($action !== 'change_password') {
        $errors[] = 'The requested account action is not supported.';
    }

    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errors[] = 'Complete all three password fields.';
    }

    if ($newPassword !== '') {
        $errors = array_merge(
            $errors,
            passwordPolicyErrors(
                $newPassword,
                (string) $account['FullName'],
                (string) $account['Email']
            )
        );
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'The new password and confirmation do not match.';
    }

    if ($currentPassword !== '' && !password_verify($currentPassword, $account['Password'])) {
        $errors[] = 'The current password is incorrect.';
    }

    if (
        $newPassword !== ''
        && password_verify($newPassword, $account['Password'])
    ) {
        $errors[] = 'Choose a password that is different from the current password.';
    }

    if (!$errors) {
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        if ($newPasswordHash === false) {
            $errors[] = 'The new password could not be secured. Please try again.';
        } else {
            $updateStatement = $conn->prepare(
                'UPDATE account SET Password = ? WHERE AccountID = ?'
            );

            if (!$updateStatement) {
                $errors[] = 'The password update could not be prepared.';
            } else {
                $updateStatement->bind_param('si', $newPasswordHash, $accountId);

                if ($updateStatement->execute()) {
                    $updateStatement->close();
                    session_regenerate_id(true);
                    $_SESSION['account_csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['account_feedback'] = [
                        'type' => 'success',
                        'messages' => ['Your password was changed successfully.'],
                    ];
                    $conn->close();
                    header('Location: account_page.php?password=changed#security');
                    exit();
                }

                $errors[] = 'The password could not be changed. Please try again.';
                $updateStatement->close();
            }
        }
    }

    if ($errors) {
        $feedback = [
            'type' => 'error',
            'messages' => array_values(array_unique($errors)),
        ];
    }
}

$nameParts = preg_split('/\s+/', trim($account['FullName'])) ?: [];
$initials = '';

foreach (array_slice($nameParts, 0, 2) as $namePart) {
    $initials .= firstCharacter($namePart);
}

if (function_exists('mb_strtoupper')) {
    $initials = mb_strtoupper($initials, 'UTF-8');
} else {
    $initials = strtoupper($initials);
}

$firstName = $nameParts[0] ?? $account['FullName'];
$createdTimestamp = strtotime($account['CreatedAt']);
$createdDisplay = $createdTimestamp ? date('d M Y', $createdTimestamp) : $account['CreatedAt'];
$createdIso = $createdTimestamp ? date('c', $createdTimestamp) : '';

$roleDescriptions = [
    'ADMIN' => 'Identity governance, role assignment, and system-level access.',
    'FLEET_MGR' => 'Incident review, depot comparison, driver risk, coaching, and safety oversight.',
    'WS_MGR' => 'Predictive alerts, workshop capacity, maintenance planning, parts, cost, and downtime.',
    'MECHANIC' => 'Diagnostics, maintenance history, prior repairs, and assigned workshop activity.',
    'DRIVER' => 'An accountable fleet identity connected to safety and certification history.',
];
$roleDescription = $roleDescriptions[$account['TypeID']]
    ?? 'Authenticated access to the Databruh fleet workspace.';

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta
        name="description"
        content="Review your Databruh account details and securely change your password."
    >
    <title>Your Account - Databruh</title>
    <link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="../css_files/design_system.css">
    <link rel="stylesheet" href="../css_files/account_page.css">
    <link rel="stylesheet" href="../css_files/minimalist_theme.css">
    <link rel="stylesheet" href="../css_files/swiss_bento_theme.css">
</head>
<body>
    <a class="skip-link" href="#account-content">Skip to account content</a>

    <?php renderSiteNavigation('account'); ?>

    <main id="account-content" class="account-main overflow-x-hidden w-full max-w-full">
        <section class="account-hero" aria-labelledby="account-title">
            <div class="hero-wash" aria-hidden="true"></div>
            <div class="hero-content">
                <p class="hero-welcome" data-hero-item>Operational identity · <?php echo escape($firstName); ?></p>
                <h1 id="account-title" class="account-hero-title max-w-6xl" data-hero-item>
                    Your role
                    <span class="hero-inline-image" aria-hidden="true"></span>
                    <br>ready for<br>
                    every shift.
                </h1>
                <p class="hero-copy" data-hero-item>
                    Review the verified account, assigned responsibility, and credential
                    security behind every role-aware action.
                </p>
                <div class="hero-actions" aria-label="Account page shortcuts" data-hero-item>
                    <button
                        class="button button-light"
                        type="button"
                        data-detail-modal-open="account-identity-details"
                        aria-haspopup="dialog"
                        aria-controls="account-identity-details"
                        aria-expanded="false"
                    >
                        View account details
                    </button>
                    <a class="button button-outline" href="#security">Update password</a>
                    <a
                        class="button button-primary"
                        href="<?php echo escape(roleDashboardPath($account['TypeID'])); ?>"
                    >
                        Open your dashboard
                    </a>
                </div>
            </div>
            <a class="scroll-cue" href="#profile" aria-label="Scroll to your account details">
                <span>Scroll to continue</span>
                <span class="scroll-line" aria-hidden="true"></span>
            </a>
        </section>

        <div class="account-marquee" aria-hidden="true">
            <div class="marquee-track">
                <div class="marquee-group">
                    <span>Accountable identity</span>
                    <i></i>
                    <span>Operational role</span>
                    <i></i>
                    <span>Certification-aware history</span>
                    <i></i>
                    <span>Protected credential</span>
                    <i></i>
                </div>
                <div class="marquee-group">
                    <span>Accountable identity</span>
                    <i></i>
                    <span>Operational role</span>
                    <i></i>
                    <span>Certification-aware history</span>
                    <i></i>
                    <span>Protected credential</span>
                    <i></i>
                </div>
            </div>
        </div>

        <section id="profile" class="profile-chapter" aria-labelledby="profile-title">
            <div class="chapter-heading">
                <h2 id="profile-title">The identity behind your fleet responsibility.</h2>
                <p data-scrub-text>
                    These details come directly from the account database and remain
                    read-only here, preserving a stable account reference for
                    role-aware operational history.
                </p>
            </div>

            <div class="profile-bento">
                <article class="profile-card profile-card-identity" data-stack-card>
                    <div class="identity-topline">
                        <div class="identity-avatar" aria-hidden="true">
                            <?php echo escape($initials); ?>
                        </div>
                        <div>
                            <p class="card-caption">Signed-in identity</p>
                            <h3><?php echo escape($account['FullName']); ?></h3>
                            <a
                                class="identity-email"
                                href="<?php echo escape('mailto:' . $account['Email']); ?>"
                            >
                                <?php echo escape($account['Email']); ?>
                            </a>
                        </div>
                    </div>

                    <dl class="identity-details">
                        <div>
                            <dt>Account ID</dt>
                            <dd>#<?php echo (int) $account['AccountID']; ?></dd>
                        </div>
                        <div>
                            <dt>Access code</dt>
                            <dd><?php echo escape($account['TypeID']); ?></dd>
                        </div>
                        <div>
                            <dt>Account state</dt>
                            <dd><span class="status-dot"></span>Active</dd>
                        </div>
                    </dl>
                </article>

                <article class="profile-card profile-card-role" data-stack-card>
                    <p class="card-caption">Your access</p>
                    <h3><?php echo escape($account['TypeName']); ?></h3>
                    <p><?php echo escape($roleDescription); ?></p>
                    <span class="card-line" aria-hidden="true"></span>
                </article>

                <article class="profile-card profile-card-member" data-stack-card>
                    <p class="card-caption">With Databruh since</p>
                    <h3>
                        <time datetime="<?php echo escape($createdIso); ?>">
                            <?php echo escape($createdDisplay); ?>
                        </time>
                    </h3>
                    <p>Your profile and role are loaded fresh whenever this page opens.</p>
                    <span class="member-orbit" aria-hidden="true"></span>
                </article>
            </div>

            <div class="access-accordion" aria-label="How your account works">
                <button
                    class="accordion-panel is-active"
                    type="button"
                    aria-expanded="true"
                    style="--panel-image: url('../assets/fleet/driver-cab.jpg');"
                >
                    <span class="accordion-content">
                        <strong>Operational identity</strong>
                        <span>
                            Your account anchors role-aware actions while driver and
                            certification records retain their own history.
                        </span>
                    </span>
                </button>

                <button
                    class="accordion-panel"
                    type="button"
                    aria-expanded="false"
                    style="--panel-image: url('../assets/fleet/refrigerated-trucks.jpg');"
                >
                    <span class="accordion-content">
                        <strong>Network access</strong>
                        <span>
                            Your assigned role keeps safety, workshop, mechanic, driver,
                            and administrator responsibilities distinct.
                        </span>
                    </span>
                </button>

                <button
                    class="accordion-panel"
                    type="button"
                    aria-expanded="false"
                    style="--panel-image: url('../assets/fleet/truck-maintenance.jpg');"
                >
                    <span class="accordion-content">
                        <strong>Credential protection</strong>
                        <span>
                            Current-password verification prevents another signed-in
                            person from silently replacing your credentials.
                        </span>
                    </span>
                </button>
            </div>
        </section>

        <section id="security" class="security-chapter" aria-labelledby="security-title">
            <div class="security-shell">
                <div class="security-intro">
                    <h2 id="security-title">Secure the identity that follows every shift.</h2>
                    <p data-scrub-text>
                        A password update verifies the current credential before
                        protecting the next safety, workshop, or account session.
                    </p>
                    <div class="security-rule" aria-hidden="true"></div>
                    <p class="security-note">
                        Databruh never displays or sends your existing password.
                    </p>
                </div>

                <div class="security-stack">
                    <article class="security-step security-step-guide" data-stack-card>
                        <p class="card-caption">Before you update</p>
                        <h3>A stronger password starts with distance.</h3>
                        <ul>
                            <li>Use a unique passphrase with at least 15 characters.</li>
                            <li>Include mixed case, at least one number, and one symbol.</li>
                            <li>Avoid common passwords, personal details, and Databruh.</li>
                            <li>Do not reuse your current password.</li>
                        </ul>
                    </article>

                    <article class="security-step security-step-form" data-stack-card>
                        <div class="form-heading">
                            <div>
                                <p class="card-caption">Secure credential change</p>
                                <h3>Set a new password</h3>
                            </div>
                            <span class="lock-glyph" aria-hidden="true"></span>
                        </div>

                        <?php if ($feedback): ?>
                            <?php $feedbackIsError = ($feedback['type'] ?? '') === 'error'; ?>
                            <div
                                class="feedback <?php echo $feedbackIsError ? 'feedback-error' : 'feedback-success'; ?>"
                                role="<?php echo $feedbackIsError ? 'alert' : 'status'; ?>"
                                tabindex="-1"
                                data-feedback
                            >
                                <?php if ($feedbackIsError): ?>
                                    <p>Check the following and try again:</p>
                                    <ul>
                                        <?php foreach ($feedback['messages'] as $message): ?>
                                            <li><?php echo escape((string) $message); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p><?php echo escape((string) $feedback['messages'][0]); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="account_page.php#security">
                            <input type="hidden" name="action" value="change_password">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo escape($_SESSION['account_csrf_token']); ?>"
                            >

                            <div class="field-group">
                                <label for="current_password">Current password</label>
                                <div class="password-field">
                                    <input
                                        type="password"
                                        id="current_password"
                                        name="current_password"
                                        autocomplete="current-password"
                                        required
                                        maxlength="128"
                                    >
                                    <button
                                        class="password-toggle"
                                        type="button"
                                        data-password-toggle
                                        aria-controls="current_password"
                                        aria-pressed="false"
                                    >
                                        Show
                                    </button>
                                </div>
                            </div>

                            <div class="field-group">
                                <label for="new_password">New password</label>
                                <div class="password-field">
                                    <input
                                        type="password"
                                        id="new_password"
                                        name="new_password"
                                        autocomplete="new-password"
                                        aria-describedby="password-guidance password-strength"
                                        data-password-input="password-strength"
                                        required
                                        minlength="<?php echo DATABRUH_PASSWORD_MIN_LENGTH; ?>"
                                        maxlength="<?php echo DATABRUH_PASSWORD_MAX_LENGTH; ?>"
                                    >
                                    <button
                                        class="password-toggle"
                                        type="button"
                                        data-password-toggle
                                        aria-controls="new_password"
                                        aria-pressed="false"
                                    >
                                        Show
                                    </button>
                                </div>
                                <p id="password-guidance" class="field-guidance">
                                    Use 15–128 characters with mixed case, a number, and a symbol.
                                    Spaces and Unicode characters are allowed.
                                </p>
                                <div
                                    id="password-strength"
                                    class="password-strength"
                                    data-score="0"
                                    data-password-policy="<?php echo escape($accountPasswordPolicyJson); ?>"
                                    role="status"
                                    aria-live="polite"
                                >
                                    <span
                                        class="password-strength-track"
                                        role="meter"
                                        aria-label="New password requirement progress"
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
                                <label for="confirm_password">Confirm new password</label>
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

                            <button class="button button-submit" type="submit">
                                Change password
                            </button>
                        </form>
                    </article>

                    <article class="security-step security-step-after" data-stack-card>
                        <p class="card-caption">After the change</p>
                        <h3>Your operational session stays active on this device.</h3>
                        <p>
                            The active session receives a fresh identifier immediately.
                            Use the log-out control below when the device is shared.
                        </p>
                        <a class="text-link" href="./logout_process.php">
                            End this session now
                            <span aria-hidden="true">-&gt;</span>
                        </a>
                    </article>
                </div>
            </div>
        </section>

        <section class="session-cta" aria-labelledby="session-title">
            <div>
                <h2 id="session-title">Shift complete?</h2>
                <p>Close the operational session before another person uses this device.</p>
            </div>
            <a class="button button-session" href="./logout_process.php">
                Log out of Databruh
            </a>
        </section>
    </main>

    <?php renderSiteFooter('account'); ?>
    <?php
    renderDetailModal([
        'id' => 'account-identity-details',
        'kicker' => 'Operational identity',
        'title' => 'The account behind every role-aware action.',
        'intro' => 'Your identity, assigned role, and credential state form the stable account reference used throughout Databruh.',
        'items' => [
            [
                'label' => 'Database identity',
                'title' => 'One verified reference',
                'body' => 'Account ID, full name, email, and creation date load directly from the account database on this page.',
            ],
            [
                'label' => 'Role responsibility',
                'title' => 'Access follows the work',
                'body' => 'The assigned account type keeps administrator, safety, workshop, mechanic, and driver responsibilities distinct.',
            ],
            [
                'label' => 'Credential security',
                'title' => 'Protect the active shift',
                'body' => 'Password changes require the current credential, enforce strength checks, and refresh the active session identifier.',
            ],
        ],
    ]);
    ?>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
