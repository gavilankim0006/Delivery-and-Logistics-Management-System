<script setup>
const props = defineProps({ role: { type: String, default: 'guest' }, links: { type: Array, default: () => [] }, active: { type: String, default: '' } });
</script>

<template>
    <nav class="navbar navbar-expand-lg app-navbar" aria-label="Main navigation">
        <div class="container page-shell">
            <a class="navbar-brand" href="/SIAproject/">Dispatch</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#app-navigation" aria-controls="app-navigation" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div id="app-navigation" class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li v-for="link in links" :key="link.href" class="nav-item">
                        <a class="nav-link" :class="{ active: active === link.href }" :href="link.href" :aria-current="active === link.href ? 'page' : undefined">{{ link.label }}</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</template>
