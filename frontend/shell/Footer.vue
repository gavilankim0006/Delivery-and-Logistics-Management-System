<script setup>
defineProps({ year: { type: Number, default: new Date().getFullYear() }, title: { type: String, default: 'Dispatch' }, description: { type: String, default: 'Delivery and logistics management' } });
</script>

<template>
    <footer class="app-footer mt-auto">
        <div class="container page-shell d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>© {{ year }} {{ title }}</span>
            <span>{{ description }}</span>
        </div>
    </footer>
</template>
