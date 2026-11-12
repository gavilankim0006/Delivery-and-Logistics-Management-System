<template>
  <div class="container page-shell">
    <section class="home-hero-section" aria-labelledby="home-title">
      <div class="home-hero-content">
        <span class="home-eyebrow">
          <span class="home-eyebrow-dot" aria-hidden="true"></span>
          Delivery operations, connected
        </span>
        <h1 id="home-title" class="home-headline">Every delivery, moving in the&nbsp;right direction.</h1>
        <p class="home-subheadline">Dispatch brings orders, riders, and operations teams together-so deliveries stay organized from assignment through arrival.</p>
        <div class="home-actions">
          <a class="btn btn-primary" :href="`${baseUrl}login`">Log in to Dispatch <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
          <a class="btn btn-outline-primary" :href="`${baseUrl}applicant/login`">Join our team</a>
        </div>
        <p class="home-note">A shared operations hub for customers, riders, dispatchers, and administrators.</p>
      </div>
      <div class="home-hero-graphic" aria-hidden="true">
        <div class="home-hero-ring home-hero-ring--1"></div>
        <div class="home-hero-ring home-hero-ring--2"></div>
      </div>
    </section>

    <section class="home-section" id="services" aria-labelledby="services-title">
      <header class="home-section-header">
        <span class="home-eyebrow">
          <span class="home-eyebrow-dot" aria-hidden="true"></span>
          One coordinated workflow
        </span>
        <h2 id="services-title">The right tools for every step.</h2>
        <p>Keep delivery work visible and coordinated with role-based tools built around the people doing the work.</p>
      </header>
      <div class="home-cards">
        <article class="home-card surface">
          <span class="home-card-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span>
          <h3>Order coordination</h3>
          <p>Keep order details, customer updates, and delivery progress together in one operations flow.</p>
        </article>
        <article class="home-card surface">
          <span class="home-card-icon" aria-hidden="true"><i class="bi bi-signpost-2"></i></span>
          <h3>Rider operations</h3>
          <p>Connect riders with assigned deliveries and the information they need while out on the road.</p>
        </article>
        <article class="home-card surface">
          <span class="home-card-icon" aria-hidden="true"><i class="bi bi-diagram-3"></i></span>
          <h3>Dispatch visibility</h3>
          <p>Give dispatchers and administrators a clearer view of assignments, status, and delivery activity.</p>
        </article>
      </div>
    </section>

    <section class="home-cta surface" aria-labelledby="cta-title">
      <div class="home-cta-text">
        <h2 id="cta-title">Need help or want to work with us?</h2>
        <p>For service questions, contact our team. Interested in joining as a rider or dispatcher? Start an application.</p>
      </div>
      <div class="home-cta-actions">
        <a class="btn btn-primary" :href="`${baseUrl}login`">Contact support</a>
        <a class="btn btn-outline-primary" :href="`${baseUrl}applicant/login`">Explore opportunities</a>
      </div>
    </section>
  </div>
</template>

<script setup>
defineProps({
  baseUrl: { type: String, default: '/' },
});
</script>