<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (!function_exists('layoutEscape')) {
    function layoutEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('layoutCurrentPage')) {
    function layoutCurrentPage(string $candidate, string $currentPage): string
    {
        return $candidate === $currentPage ? ' aria-current="page"' : '';
    }
}

if (!function_exists('renderSiteNavigation')) {
    function renderSiteNavigation(string $currentPage): void
    {
        $isLoggedIn = isset($_SESSION['AccountID']);
        $isAdministrator = $isLoggedIn
            && (($_SESSION['TypeID'] ?? '') === 'ADMIN');
        $fullName = trim((string) ($_SESSION['FullName'] ?? ''));
        $nameParts = $fullName !== '' ? preg_split('/\s+/', $fullName) : [];
        $firstName = $nameParts[0] ?? $fullName;
        $dashboardHref = $isLoggedIn
            ? './' . roleDashboardPath((string) ($_SESSION['TypeID'] ?? ''))
            : './datavs.php';
        ?>
        <header class="account-nav-shell site-nav-shell">
            <nav class="account-nav site-nav" aria-label="Primary navigation">
                <a class="brand" href="./home_page.php" aria-label="Databruh home">
                    <span class="brand-mark" aria-hidden="true"></span>
                    <span>Databruh</span>
                </a>

                <div class="nav-links">
                    <a href="./home_page.php"<?php echo layoutCurrentPage('home', $currentPage); ?>>
                        Home
                    </a>
                    <a href="<?php echo layoutEscape($dashboardHref); ?>"<?php echo layoutCurrentPage('dashboard', $currentPage); ?>>
                        Dashboard
                    </a>
                    <?php if ($isAdministrator): ?>
                        <a href="./admin_page.php"<?php echo layoutCurrentPage('admin', $currentPage); ?>>
                            Manage accounts
                        </a>
                    <?php endif; ?>
                    <?php if ($isLoggedIn): ?>
                        <a href="./account_page.php"<?php echo layoutCurrentPage('account', $currentPage); ?>>
                            Account
                        </a>
                    <?php else: ?>
                        <a href="./login.php"<?php echo layoutCurrentPage('login', $currentPage); ?>>
                            Log in
                        </a>
                    <?php endif; ?>
                </div>

                <div class="nav-actions">
                    <?php if ($isLoggedIn): ?>
                        <?php if ($firstName !== ''): ?>
                            <span class="nav-user">
                                Hi, <?php echo layoutEscape($firstName); ?>
                            </span>
                        <?php endif; ?>
                        <a class="nav-logout" href="./logout_process.php">Log out</a>
                    <?php else: ?>
                        <a
                            class="nav-logout<?php echo $currentPage === 'signup' ? ' is-current' : ''; ?>"
                            href="./signup.php"
                            <?php echo $currentPage === 'signup' ? 'aria-current="page"' : ''; ?>
                        >
                            Sign up
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        </header>
        <?php
    }
}

if (!function_exists('renderSiteFooter')) {
    function renderSiteFooter(string $currentPage): void
    {
        $isLoggedIn = isset($_SESSION['AccountID']);
        $dashboardHref = $isLoggedIn
            ? './' . roleDashboardPath((string) ($_SESSION['TypeID'] ?? ''))
            : './datavs.php';
        ?>
        <footer class="account-footer site-footer">
            <a class="brand footer-brand" href="./home_page.php">
                <span class="brand-mark" aria-hidden="true"></span>
                <span>Databruh</span>
            </a>
            <p>Smart fleet intelligence across Vietnam’s depot network.</p>
            <div class="footer-links">
                <a href="./home_page.php"<?php echo layoutCurrentPage('home', $currentPage); ?>>
                    Home
                </a>
                <a href="<?php echo layoutEscape($dashboardHref); ?>"<?php echo layoutCurrentPage('dashboard', $currentPage); ?>>
                    Dashboard
                </a>
                <?php if ($isLoggedIn): ?>
                    <a href="./account_page.php"<?php echo layoutCurrentPage('account', $currentPage); ?>>
                        Account
                    </a>
                <?php else: ?>
                    <a href="./login.php"<?php echo layoutCurrentPage('login', $currentPage); ?>>
                        Log in
                    </a>
                <?php endif; ?>
            </div>
        </footer>
        <?php
    }
}

if (!function_exists('renderDetailModal')) {
    function renderDetailModal(array $config): void
    {
        $modalId = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '',
            (string) ($config['id'] ?? 'page-details')
        );
        $modalId = $modalId !== '' ? $modalId : 'page-details';
        $titleId = $modalId . '-title';
        $descriptionId = $modalId . '-description';
        $items = isset($config['items']) && is_array($config['items'])
            ? array_slice($config['items'], 0, 3)
            : [];
        $itemCount = count($items);
        ?>
        <div
            id="<?php echo layoutEscape($modalId); ?>"
            class="detail-modal"
            data-detail-modal
            hidden
        >
            <div
                class="detail-modal__backdrop"
                data-detail-modal-close
                aria-hidden="true"
            ></div>

            <section
                class="detail-modal__dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="<?php echo layoutEscape($titleId); ?>"
                aria-describedby="<?php echo layoutEscape($descriptionId); ?>"
                tabindex="-1"
            >
                <div class="detail-modal__topline">
                    <p class="detail-modal__kicker">
                        <?php echo layoutEscape((string) ($config['kicker'] ?? 'Page details')); ?>
                    </p>
                    <button
                        class="detail-modal__close"
                        type="button"
                        data-detail-modal-close
                        aria-label="Close details"
                    >
                        <span>Close</span>
                        <span class="detail-modal__close-mark" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="detail-modal__heading">
                    <h2 id="<?php echo layoutEscape($titleId); ?>">
                        <?php echo layoutEscape((string) ($config['title'] ?? 'Operational details')); ?>
                    </h2>
                    <p id="<?php echo layoutEscape($descriptionId); ?>">
                        <?php echo layoutEscape((string) ($config['intro'] ?? '')); ?>
                    </p>
                </div>

                <?php if ($itemCount > 0): ?>
                    <div class="detail-modal__grid">
                        <?php foreach ($items as $index => $item): ?>
                            <article class="detail-modal__card">
                                <div class="detail-modal__card-meta">
                                    <span>
                                        <?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?>
                                        /
                                        <?php echo str_pad((string) $itemCount, 2, '0', STR_PAD_LEFT); ?>
                                    </span>
                                    <span>
                                        <?php echo layoutEscape((string) ($item['label'] ?? 'Detail')); ?>
                                    </span>
                                </div>
                                <h3>
                                    <?php echo layoutEscape((string) ($item['title'] ?? '')); ?>
                                </h3>
                                <p>
                                    <?php echo layoutEscape((string) ($item['body'] ?? '')); ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }
}

if (!function_exists('renderSiteMotionScripts')) {
    function renderSiteMotionScripts(): void
    {
        $motionScriptVersion =
            @filemtime(__DIR__ . '/../../js_files/site_motion.js') ?: 1;
        $sortableTablesScriptVersion =
            @filemtime(__DIR__ . '/../../js_files/sortable_tables.js') ?: 1;
        ?>
        <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
        <script src="../js_files/site_motion.js?v=<?php echo $motionScriptVersion; ?>"></script>
        <script src="../js_files/sortable_tables.js?v=<?php echo $sortableTablesScriptVersion; ?>"></script>
        <?php
    }
}
