<?php
session_start();
require_once __DIR__ . '/includes/layout.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta
        name="description"
        content="Log in to your Databruh account and access the smart fleet workspace."
    >
    <title>Log In - Databruh</title>
    <link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="../css_files/design_system.css">
    <link rel="stylesheet" href="../css_files/login.css">
    <link rel="stylesheet" href="../css_files/minimalist_theme.css">
    <link rel="stylesheet" href="../css_files/swiss_bento_theme.css">
</head>
<body class="auth-page">
    <a class="skip-link" href="#main-content">Skip to log in</a>
    <?php renderSiteNavigation('login'); ?>

    <main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
        <div class="site-marquee" aria-hidden="true">
            <div class="marquee-track">
                <div class="marquee-group">
                    <span>Fleet safety manager</span><i></i>
                    <span>Workshop manager</span><i></i>
                    <span>Mechanic</span><i></i>
                    <span>Driver</span><i></i>
                </div>
                <div class="marquee-group">
                    <span>Fleet safety manager</span><i></i>
                    <span>Workshop manager</span><i></i>
                    <span>Mechanic</span><i></i>
                    <span>Driver</span><i></i>
                </div>
            </div>
        </div>

        <section id="login-form" class="auth-workspace" aria-labelledby="login-title">
            <div class="auth-shell">
                <div class="auth-intro" data-reveal>
                    <span class="section-kicker">Welcome back</span>
                    <h2 id="login-title">Enter the workspace for your responsibility.</h2>
                    <p data-scrub-text>
                        Your account restores a trusted identity and its assigned role
                        before any fleet or account-level action begins.
                    </p>

                    <dl class="auth-details">
                        <div>
                            <dt>Operating network</dt>
                            <dd>Four Vietnamese depots</dd>
                        </div>
                        <div>
                            <dt>Entry control</dt>
                            <dd>Verified email and password</dd>
                        </div>
                    </dl>

                    <button
                        class="button button-secondary"
                        type="button"
                        data-detail-modal-open="login-access-details"
                        aria-haspopup="dialog"
                        aria-controls="login-access-details"
                        aria-expanded="false"
                    >
                        View access details
                    </button>
                </div>

                <div class="auth-form-card" data-reveal data-stack-card>
                    <div class="auth-form-heading">
                        <div>
                            <p class="form-caption">Account sign in</p>
                            <h2>Log in</h2>
                        </div>
                        <span class="auth-lock" aria-hidden="true"></span>
                    </div>

                    <?php if (isset($_GET['signup']) && $_GET['signup'] === 'success'): ?>
                        <div class="system-feedback" role="status">
                            Account created successfully. You can log in now.
                        </div>
                    <?php elseif (isset($_GET['account']) && $_GET['account'] === 'required'): ?>
                        <div class="system-feedback is-error" role="alert">
                            Log in before opening your account page.
                        </div>
                    <?php elseif (isset($_GET['account']) && $_GET['account'] === 'missing'): ?>
                        <div class="system-feedback is-error" role="alert">
                            That account is no longer available. Please log in again.
                        </div>
                    <?php endif; ?>

                    <form action="login_process.php" method="POST" class="auth-form">
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
                            <label for="password">Password</label>
                            <div class="password-field">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    autocomplete="current-password"
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
                        </div>

                        <button class="button button-primary auth-submit" type="submit">
                            Log in
                        </button>
                    </form>

                    <p class="auth-footnote">
                        Don’t have an account? <a href="./signup.php">Sign up</a>
                    </p>
                </div>
            </div>
        </section>

        <section class="auth-proof-section" aria-labelledby="login-proof-title">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">Role-aware entry</span>
                        <h2 id="login-proof-title">One credential. Clear operational responsibility.</h2>
                    </div>
                    <p>
                        The existing login flow verifies credentials, refreshes the
                        session identifier, and restores the account’s assigned role
                        without changing any existing authentication behaviour.
                    </p>
                </div>

                <div class="auth-proof-grid">
                    <article data-reveal data-stack-card>
                        <span>Identity check</span>
                        <h3>Verify</h3>
                        <p>Email locates one account; the password confirms who is entering.</p>
                    </article>
                    <article data-reveal data-stack-card>
                        <span>Role routing</span>
                        <h3>Restore</h3>
                        <p>Name, email, and account type return to the protected session.</p>
                    </article>
                    <article data-reveal data-stack-card>
                        <span>Operational continuity</span>
                        <h3>Continue</h3>
                        <p>You return with role-aware navigation and the same fleet data intact.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="site-cta auth-cta" aria-labelledby="login-cta-title">
            <div>
                <h2 id="login-cta-title">Joining the driver operation?</h2>
                <p>Create a Driver-role account through the existing registration flow.</p>
            </div>
            <a class="button button-dark" href="./signup.php">Create account</a>
        </section>
    </main>

    <?php renderSiteFooter('login'); ?>
    <?php
    renderDetailModal([
        'id' => 'login-access-details',
        'kicker' => 'Role-aware entry',
        'title' => 'One sign-in restores the right operating context.',
        'intro' => 'The existing login flow verifies one account and returns its identity and assigned role to the protected session.',
        'items' => [
            [
                'label' => 'Identity check',
                'title' => 'Verify the operator',
                'body' => 'The email locates one Databruh account and the stored password check confirms who is entering.',
            ],
            [
                'label' => 'Role restoration',
                'title' => 'Return the assigned access',
                'body' => 'The account name, email, identifier, and role are restored for role-aware navigation and protected pages.',
            ],
            [
                'label' => 'Session continuity',
                'title' => 'Continue securely',
                'body' => 'The signed-in session keeps fleet access consistent until the operator deliberately logs out.',
            ],
        ],
    ]);
    ?>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
