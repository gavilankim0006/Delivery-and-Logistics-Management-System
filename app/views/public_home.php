<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$loginUrl = base_url('login');
$applicationUrl = base_url('careers/apply');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Dispatch helps teams coordinate orders, riders, and deliveries from one clear operations hub.">
    <meta name="color-scheme" content="light">
    <title>Dispatch — Delivery operations, connected</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(base_url('dispatch-mark.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('css/app.css')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>">
        :root { --home-ink: #172b3a; --home-muted: #617687; --home-route: #087f98; --home-paper: #eef2f5; --home-line: #d3dde5; }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; background: var(--home-paper); color: var(--home-ink); font-family: 'Manrope', sans-serif; }
        a { color: inherit; }
        .home-shell { width: min(1160px, calc(100% - 40px)); margin: 0 auto; }
        .home-nav { min-height: 76px; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .home-brand { display: inline-flex; align-items: center; gap: 12px; color: var(--home-ink); text-decoration: none; font-size: 1.05rem; font-weight: 800; letter-spacing: -.04em; }
        .home-brand-mark { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 12px; background: var(--home-route); color: white; }
        .home-nav-actions { display: flex; align-items: center; gap: 12px; }
        .home-nav-link { padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: .9rem; font-weight: 700; }
        .home-nav-link:hover { background: white; }
        .home-button { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 20px; border: 1px solid var(--home-route); border-radius: 8px; background: var(--home-route); color: white; text-decoration: none; font-weight: 800; transition: transform .18s ease, background .18s ease; }
        .home-button:hover { background: #05687e; color: white; transform: translateY(-1px); }
        .home-button-light { border-color: white; background: white; color: var(--home-ink); }
        .home-button-light:hover { border-color: #e4eef1; background: #e4eef1; color: var(--home-ink); }
        .home-hero { position: relative; overflow: hidden; margin: 12px auto 0; padding: clamp(46px, 8vw, 92px); border-radius: 24px; background: var(--home-ink); color: white; }
        .home-hero::after { position: absolute; top: -150px; right: -90px; width: 460px; height: 460px; border: 1px solid rgba(255,255,255,.11); border-radius: 50%; box-shadow: 0 0 0 48px rgba(255,255,255,.035), 0 0 0 96px rgba(255,255,255,.025); content: ''; pointer-events: none; }
        .home-hero-content { position: relative; z-index: 1; max-width: 710px; }
        .home-eyebrow { display: inline-flex; align-items: center; gap: 9px; color: #9edce5; font-size: .78rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .home-eyebrow::before { width: 8px; height: 8px; border-radius: 50%; background: #e8a33d; content: ''; }
        .home-hero h1 { max-width: 700px; margin: 18px 0; font-size: clamp(2.5rem, 6vw, 4.9rem); line-height: 1.04; letter-spacing: -.065em; }
        .home-hero p { max-width: 590px; margin: 0; color: #d3dde5; font-size: clamp(1rem, 2vw, 1.16rem); line-height: 1.8; }
        .home-hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }
        .home-hero-note { margin-top: 22px !important; color: #aebfca !important; font-size: .82rem !important; }
        .home-section { padding: 76px 0; }
        .home-section-heading { max-width: 650px; margin-bottom: 32px; }
        .home-section-heading .home-eyebrow { color: var(--home-route); }
        .home-section-heading h2 { margin: 12px 0; font-size: clamp(1.9rem, 4vw, 2.8rem); line-height: 1.15; letter-spacing: -.05em; }
        .home-section-heading p { margin: 0; color: var(--home-muted); line-height: 1.75; }
        .home-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .home-card { min-height: 230px; padding: 26px; border: 1px solid var(--home-line); border-radius: 14px; background: white; }
        .home-card-icon { display: grid; width: 44px; height: 44px; place-items: center; border-radius: 12px; background: #e4f4f7; color: var(--home-route); font-size: 1.2rem; }
        .home-card h3 { margin: 22px 0 9px; font-size: 1.1rem; letter-spacing: -.025em; }
        .home-card p { margin: 0; color: var(--home-muted); font-size: .94rem; line-height: 1.7; }
        .home-contact { display: flex; align-items: center; justify-content: space-between; gap: 28px; padding: 30px; border-radius: 16px; background: #e3edf1; }
        .home-contact h2 { margin: 0 0 8px; font-size: 1.35rem; letter-spacing: -.03em; }
        .home-contact p { margin: 0; color: var(--home-muted); line-height: 1.7; }
        .home-contact a:not(.home-button) { color: var(--home-route); font-weight: 700; }
        .home-footer { padding: 28px 0; border-top: 1px solid var(--home-line); color: var(--home-muted); font-size: .86rem; }
        .home-footer-inner { display: flex; justify-content: space-between; gap: 18px; }
        @media (max-width: 700px) {
            .home-shell { width: min(100% - 32px, 1160px); }
            .home-nav { min-height: 68px; }
            .home-nav-actions { gap: 2px; }
            .home-nav-link { padding: 9px 10px; }
            .home-hero { padding: 42px 26px; border-radius: 18px; }
            .home-hero::after { top: -220px; right: -300px; }
            .home-cards { grid-template-columns: 1fr; }
            .home-card { min-height: 0; }
            .home-section { padding: 56px 0; }
            .home-contact, .home-footer-inner { align-items: flex-start; flex-direction: column; }
        }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } *, *::before, *::after { transition-duration: .01ms !important; } }
    </style>
</head>
<body>
    <header class="home-shell">
        <nav class="home-nav" aria-label="Main navigation">
            <a class="home-brand" href="<?= e(base_url()) ?>" aria-label="Dispatch home">
                <span class="home-brand-mark" aria-hidden="true"><i class="bi bi-signpost-split-fill"></i></span>
                <span>Dispatch</span>
            </a>
            <div class="home-nav-actions">
                <a class="home-nav-link" href="#services">Services</a>
                <a class="home-nav-link" href="<?= e($loginUrl) ?>">Log in</a>
            </div>
        </nav>
    </header>

    <main class="home-shell">
        <section class="home-hero" aria-labelledby="home-title">
            <div class="home-hero-content">
                <span class="home-eyebrow">Delivery operations, connected</span>
                <h1 id="home-title">Every delivery, moving in the right direction.</h1>
                <p>Dispatch brings orders, riders, and operations teams together—so deliveries stay organized from assignment through arrival.</p>
                <div class="home-hero-actions">
                    <a class="home-button home-button-light" href="<?= e($loginUrl) ?>">Log in to Dispatch <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></a>
                    <a class="home-button" href="<?= e($applicationUrl) ?>">Join our team</a>
                </div>
                <p class="home-hero-note">A shared operations hub for customers, riders, dispatchers, and administrators.</p>
            </div>
        </section>

        <section class="home-section" id="services" aria-labelledby="services-title">
            <div class="home-section-heading">
                <span class="home-eyebrow">One coordinated workflow</span>
                <h2 id="services-title">The right tools for every step.</h2>
                <p>Keep delivery work visible and coordinated with role-based tools built around the people doing the work.</p>
            </div>
            <div class="home-cards">
                <article class="home-card">
                    <span class="home-card-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span>
                    <h3>Order coordination</h3>
                    <p>Keep order details, customer updates, and delivery progress together in one operations flow.</p>
                </article>
                <article class="home-card">
                    <span class="home-card-icon" aria-hidden="true"><i class="bi bi-signpost-2"></i></span>
                    <h3>Rider operations</h3>
                    <p>Connect riders with assigned deliveries and the information they need while out on the road.</p>
                </article>
                <article class="home-card">
                    <span class="home-card-icon" aria-hidden="true"><i class="bi bi-diagram-3"></i></span>
                    <h3>Dispatch visibility</h3>
                    <p>Give dispatchers and administrators a clearer view of assignments, status, and delivery activity.</p>
                </article>
            </div>
        </section>

        <section class="home-contact" aria-labelledby="contact-title">
            <div>
                <h2 id="contact-title">Need help or want to work with us?</h2>
                <p>For service questions, contact our team. Interested in joining as a rider or dispatcher? Start an application.</p>
            </div>
            <div class="home-hero-actions" style="margin-top:0">
                <a class="home-button" href="<?= e($loginUrl) ?>">Contact support</a>
                <a class="home-button home-button-light" href="<?= e($applicationUrl) ?>">Explore opportunities</a>
            </div>
        </section>
    </main>

    <footer class="home-footer mt-5">
        <div class="home-shell home-footer-inner">
            <span>&copy; <?= date('Y') ?> Dispatch</span>
            <span>Delivery and logistics management</span>
        </div>
    </footer>

</body>
</html>
