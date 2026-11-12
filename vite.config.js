import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    base: './',
    publicDir: false,
    plugins: [vue()],
    build: {
        outDir: 'public/build',
        emptyOutDir: false,
        rollupOptions: {
            input: {
                app: 'frontend/app/main.js',
                static_pages: 'frontend/static/main.js',
                public_home: 'frontend/public_home/main.js',
                careers: 'frontend/careers/main.js',
                delivery_control: 'frontend/delivery_control/main.js',
                dispatch: 'frontend/dispatch/main.js',
                admin: 'frontend/admin/main.js',
                auth: 'frontend/auth/main.js',
                orders: 'frontend/orders/main.js',
                rider: 'frontend/rider/main.js',
                messenger: 'frontend/messenger/main.js',
                spa: 'frontend/spa/main.js'
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]'
            }
        }
    }
});
