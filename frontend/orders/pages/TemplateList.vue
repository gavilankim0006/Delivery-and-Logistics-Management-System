<script setup>
import { ref } from 'vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    templateCount: { type: Number, default: 0 },
    templateLimit: { type: Number, default: 50 },
    baseUrl: { type: String, default: '/' },
    csrfToken: { type: String, default: '' },
    flashes: { type: Array, default: () => [] }
});

const templates = ref([...props.templates]);

function date(value) {
    if (!value) return '';
    const parsed = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

function weight(template) {
    return Number(template.parcel_weight_kg || 0);
}

function distance(template) {
    return Number(template.distance_km || 0);
}

function confirmUse(template, event) {
    if (!window.confirm(`Create a new draft order from "${template.name}"? The fee will be recalculated with current rates.`)) {
        event.preventDefault();
    }
}

function confirmDelete(template, event) {
    if (!window.confirm(`Delete the template "${template.name}"? This cannot be undone.`)) {
        event.preventDefault();
    }
}
</script>

<template>
    <div class="container py-4 ds-page page-shell">
        <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show mb-3" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
            <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
            {{ flash.message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <header class="ds-page-header page-header mb-4">
            <div>
                <p class="ds-eyebrow">Repeat deliveries</p>
                <h1 class="page-title mb-1">Order templates</h1>
                <p class="text-muted mb-0 page-description">
                    Saved routes and parcel details you can turn into a new draft in one click.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" :href="`${baseUrl}orders/new`">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>New delivery
                </a>
                <a class="btn btn-outline-secondary" :href="`${baseUrl}orders`">Back to orders</a>
            </div>
        </header>

        <p class="small text-muted">
            {{ templateCount }} of {{ templateLimit }} templates used. Save a new one from any order's detail page with "Save as template".
        </p>

        <section v-if="templates.length === 0" class="empty-state surface p-4">
            <h2 class="h6 mb-1">No templates yet</h2>
            <p class="text-muted mb-3">Open any order and choose "Save as template" to reuse its route for future deliveries.</p>
            <a class="btn btn-primary" :href="`${baseUrl}orders`">Browse orders</a>
        </section>

        <div v-else class="row g-3">
            <div v-for="template in templates" :key="template.id" class="col-12 col-lg-6">
                <article class="surface p-3 h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <h2 class="h6 mb-0">{{ template.name }}</h2>
                        <span class="small text-muted text-nowrap">Saved {{ date(template.created_at) }}</span>
                    </div>

                    <div class="d-flex flex-column gap-2 small mb-3">
                        <span class="d-flex gap-2">
                            <span class="order-card-dot order-card-dot--pickup mt-1" aria-hidden="true"></span>
                            <span><strong>From:</strong> {{ template.pickup_address }}</span>
                        </span>
                        <span class="d-flex gap-2">
                            <span class="order-card-dot order-card-dot--dropoff mt-1" aria-hidden="true"></span>
                            <span><strong>To:</strong> {{ template.dropoff_address }}</span>
                        </span>
                    </div>

                    <p class="small text-muted mb-3">
                        {{ template.parcel_description }}
                        · {{ weight(template) }} kg
                        · {{ template.parcel_size }}
                        <template v-if="Number(template.is_fragile) === 1"> · Fragile</template>
                        <template v-if="distance(template) > 0"> · {{ distance(template).toFixed(2) }} km</template>
                    </p>

                    <div class="mt-auto d-flex flex-wrap gap-2">
                        <form method="post" :action="`${baseUrl}templates/${template.id}/use`" @submit="confirmUse(template, $event)">
                            <input type="hidden" name="csrf_token" :value="csrfToken">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-lightning-charge me-1" aria-hidden="true"></i>Create draft
                            </button>
                        </form>
                        <form method="post" :action="`${baseUrl}templates/${template.id}/delete`" @submit="confirmDelete(template, $event)">
                            <input type="hidden" name="csrf_token" :value="csrfToken">
                            <button class="btn btn-outline-danger" type="submit">Delete</button>
                        </form>
                    </div>
                </article>
            </div>
        </div>
    </div>
</template>
