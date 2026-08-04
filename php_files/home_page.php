<?php
session_start();
require_once __DIR__ . '/includes/layout.php';

$homeAssetVersions = [
    'design' => @filemtime(__DIR__ . '/../css_files/design_system.css') ?: 1,
    'page' => @filemtime(__DIR__ . '/../css_files/home_page.css') ?: 1,
    'minimal' => @filemtime(__DIR__ . '/../css_files/minimalist_theme.css') ?: 1,
    'swiss' => @filemtime(__DIR__ . '/../css_files/swiss_bento_theme.css') ?: 1,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta
        name="description"
        content="Databruh connects driver safety, multi-depot vehicle operations, maintenance, parts, and suppliers across a Vietnamese smart fleet."
    >
    <title>Databruh - Smart Fleet Intelligence</title>
    <link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="../css_files/design_system.css?v=<?php echo $homeAssetVersions['design']; ?>">
    <link rel="stylesheet" href="../css_files/home_page.css?v=<?php echo $homeAssetVersions['page']; ?>">
    <link rel="stylesheet" href="../css_files/minimalist_theme.css?v=<?php echo $homeAssetVersions['minimal']; ?>">
    <link rel="stylesheet" href="../css_files/swiss_bento_theme.css?v=<?php echo $homeAssetVersions['swiss']; ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <?php renderSiteNavigation('home'); ?>

    <main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
        <section class="site-hero home-hero" aria-labelledby="home-title">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="home-hero-orbit" aria-hidden="true"></div>
            <div class="site-hero-content">
                <p class="eyebrow" data-hero-item>Vietnam multi-depot operations</p>
                <h1 id="home-title" class="max-w-6xl" data-hero-item>
                    One fleet
                    <span class="hero-inline-image" aria-hidden="true"></span>
                    <br>four depots.<br>
                    One network.
                </h1>
                <p class="hero-copy" data-hero-item>
                    Databruh connects delivery vans, refrigerated trucks, electric
                    vehicles, drivers, workshops, and suppliers across Vietnam.
                </p>
                <div class="hero-actions" aria-label="Databruh shortcuts" data-hero-item>
                    <a class="button button-primary" href="./datavs.php">
                        Open safety dashboard
                    </a>
                    <button
                        class="button button-secondary"
                        type="button"
                        data-detail-modal-open="home-system-details"
                        aria-haspopup="dialog"
                        aria-controls="home-system-details"
                        aria-expanded="false"
                    >
                        View system details
                    </button>
                </div>

                <?php if (isset($_GET['login']) && $_GET['login'] == 'success'): ?>
                    <div class="hero-feedback system-feedback" role="status" data-hero-item>
                        Successfully logged in. Welcome back.
                    </div>
                <?php endif; ?>
            </div>
            <a class="scroll-cue" href="#overview" aria-label="Scroll to project overview">
                <span>Explore the system</span>
                <span class="scroll-line" aria-hidden="true"></span>
            </a>
        </section>

        <div class="site-marquee" aria-hidden="true">
            <div class="marquee-track">
                <div class="marquee-group">
                    <span>Hà Nội depot</span><i></i>
                    <span>Đà Nẵng depot</span><i></i>
                    <span>Hồ Chí Minh City depot</span><i></i>
                    <span>Cần Thơ depot</span><i></i>
                </div>
                <div class="marquee-group">
                    <span>Hà Nội depot</span><i></i>
                    <span>Đà Nẵng depot</span><i></i>
                    <span>Hồ Chí Minh City depot</span><i></i>
                    <span>Cần Thơ depot</span><i></i>
                </div>
            </div>
        </div>

        <section id="overview" class="home-overview">
            <div class="section-shell">
                <div class="chapter-heading">
                    <div>
                        <span class="section-kicker">One connected operating model</span>
                        <h2>From telematics signal to depot action.</h2>
                    </div>
                    <p>
                        The system replaces fragmented spreadsheets, workshop boards,
                        and disconnected records with one traceable operational history.
                    </p>
                </div>

                <div class="home-bento" aria-label="Databruh project overview">
                    <article class="home-card home-card-lead" data-reveal data-stack-card>
                        <span class="card-index">Fleet safety operations</span>
                        <div>
                            <p class="card-label">Telematics and driver behaviour</p>
                            <h3>Turn every road event into an accountable review.</h3>
                            <p>
                                Compare harsh braking, speeding, fatigue warnings,
                                severity, depot context, and monthly driver scores.
                            </p>
                        </div>
                        <a class="card-link" href="./datavs.php">
                            Open dashboard <span aria-hidden="true">-&gt;</span>
                        </a>
                    </article>

                    <article class="home-card home-card-project home-card-network" data-reveal data-stack-card>
                        <span class="card-index">Depot network</span>
                        <div>
                            <p class="card-label">North to Mekong Delta</p>
                            <h3>Four operating bases</h3>
                            <ul class="depot-list" aria-label="Databruh depot locations">
                                <li>Hà Nội</li>
                                <li>Đà Nẵng</li>
                                <li>Hồ Chí Minh City</li>
                                <li>Cần Thơ</li>
                            </ul>
                        </div>
                    </article>

                    <article class="home-card home-card-focus home-card-workshop" data-reveal data-stack-card>
                        <span class="card-index">Workshop readiness</span>
                        <div>
                            <p class="card-label">Maintenance and supply</p>
                            <h3>Keep vehicles service-ready</h3>
                            <p>
                                Connect diagnostic alerts, service bays, mechanics,
                                parts, suppliers, warranty, downtime, and cost.
                            </p>
                        </div>
                    </article>

                    <article class="home-card home-card-flow" data-reveal data-stack-card>
                        <span class="card-index">Connected workflow</span>
                        <ol class="fleet-flow">
                            <li>
                                <span>Detect</span>
                                <strong>Telematics or diagnostic alert</strong>
                            </li>
                            <li>
                                <span>Contextualise</span>
                                <strong>Driver, vehicle, depot, and severity</strong>
                            </li>
                            <li>
                                <span>Act</span>
                                <strong>Review, coach, inspect, or repair</strong>
                            </li>
                            <li>
                                <span>Preserve</span>
                                <strong>Keep the complete operational history</strong>
                            </li>
                        </ol>
                    </article>
                </div>
            </div>
        </section>

        <section class="home-story" data-pin-section aria-labelledby="story-title">
            <div class="section-shell split-story">
                <div data-pin-title>
                    <span class="section-kicker">Why the system exists</span>
                    <h2 id="story-title">Operations cannot live across disconnected boards.</h2>
                </div>
                <div class="split-story-copy">
                    <p data-scrub-text>
                        Databruh gives safety and workshop teams one history for each
                        driver, vehicle, incident, repair, part, supplier, and depot,
                        even when people and assets move through the network.
                    </p>
                    <div class="story-fact" data-stack-card>
                        <span>Road signal</span>
                        <p>Every event retains its driver, vehicle, depot, timestamp, and severity.</p>
                    </div>
                    <div class="story-fact" data-stack-card>
                        <span>Safety response</span>
                        <p>High and Critical events move into review, coaching, or retraining.</p>
                    </div>
                    <div class="story-fact" data-stack-card>
                        <span>Workshop response</span>
                        <p>Diagnostic alerts become planned jobs with bays, mechanics, parts, and cost.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="team" class="feedback-section" aria-labelledby="team-title">
            <div class="feedback-shell" data-carousel>
                <div class="feedback-heading">
                    <div>
                        <span class="section-kicker">Designed around real responsibilities</span>
                        <h2 id="team-title">Five builders. One operating picture.</h2>
                    </div>
                    <div class="carousel-controls" aria-label="Team carousel controls">
                        <button
                            class="carousel-button"
                            type="button"
                            data-carousel-prev
                            aria-label="Previous team member"
                        >
                            <span aria-hidden="true">&larr;</span>
                        </button>
                        <span class="carousel-status" data-carousel-status aria-live="polite"></span>
                        <button
                            class="carousel-button"
                            type="button"
                            data-carousel-next
                            aria-label="Next team member"
                        >
                            <span aria-hidden="true">&rarr;</span>
                        </button>
                    </div>
                </div>

                <div
                    class="feedback-stage"
                    role="region"
                    aria-label="Team perspectives"
                    aria-live="polite"
                    aria-atomic="true"
                >
                    <article class="feedback-slide is-active" data-carousel-slide>
                        <p class="feedback-quote">
                            “A fleet record becomes useful when the driver, vehicle, depot,
                            and decision remain connected from the first signal.”
                        </p>
                        <div class="feedback-meta">
                            <strong>Duong</strong>
                            <span>Data model and integration</span>
                        </div>
                    </article>
                    <article class="feedback-slide" data-carousel-slide hidden>
                        <p class="feedback-quote">
                            “Safety teams need to compare event type, severity, depot, and
                            score without losing the incident behind the chart.”
                        </p>
                        <div class="feedback-meta">
                            <strong>Tung</strong>
                            <span>Safety intelligence and interface</span>
                        </div>
                    </article>
                    <article class="feedback-slide" data-carousel-slide hidden>
                        <p class="feedback-quote">
                            “Assignment decisions must respect valid licences, required
                            certifications, vehicle status, and the complete history.”
                        </p>
                        <div class="feedback-meta">
                            <strong>Viet Anh</strong>
                            <span>Driver and vehicle operations</span>
                        </div>
                    </article>
                    <article class="feedback-slide" data-carousel-slide hidden>
                        <p class="feedback-quote">
                            “A diagnostic alert only becomes useful when it leads to a
                            workshop job, qualified mechanic, available part, and clear cost.”
                        </p>
                        <div class="feedback-meta">
                            <strong>Long</strong>
                            <span>Maintenance and supply workflow</span>
                        </div>
                    </article>
                    <article class="feedback-slide" data-carousel-slide hidden>
                        <p class="feedback-quote">
                            “Role-aware access keeps safety, workshop, mechanic, driver, and
                            administrator responsibilities precise without fragmenting data.”
                        </p>
                        <div class="feedback-meta">
                            <strong>Hai</strong>
                            <span>Access and account governance</span>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="site-cta" aria-labelledby="home-cta-title">
            <div>
                <h2 id="home-cta-title">See the network through its safety signals.</h2>
                <p>
                    Compare recorded behaviour events, severity, depot exposure, and
                    monthly driver scores in the existing live dashboard.
                </p>
            </div>
            <a class="button button-dark" href="./datavs.php">View dashboard</a>
        </section>
    </main>

    <?php renderSiteFooter('home'); ?>
    <?php
    renderDetailModal([
        'id' => 'home-system-details',
        'kicker' => 'Fleet operating model',
        'title' => 'One network, three connected decisions.',
        'intro' => 'Databruh connects the road, depot, and workshop so each signal can become a traceable operational response.',
        'items' => [
            [
                'label' => 'Depot network',
                'title' => 'Four operating bases',
                'body' => 'Hà Nội, Đà Nẵng, Hồ Chí Minh City, and Cần Thơ share one fleet view while retaining their own operational context.',
            ],
            [
                'label' => 'Safety operations',
                'title' => 'Context before action',
                'body' => 'Every behaviour event stays connected to its driver, vehicle, depot, severity, timestamp, and monthly score.',
            ],
            [
                'label' => 'Workshop readiness',
                'title' => 'Alerts become service work',
                'body' => 'Diagnostics can move into jobs, bay planning, qualified mechanic assignment, parts, suppliers, and auditable cost.',
            ],
        ],
    ]);
    ?>
    <?php renderSiteMotionScripts(); ?>
</body>
</html>
