import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    base: './',
    plugins: [vue()],
    build: {
        outDir: 'public/build',
        emptyOutDir: false,
        rollupOptions: {
            input: 'frontend/dispatch/main.js',
            output: {
                entryFileNames: 'dispatch.js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]'
            }
        }
    }
});
