import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
    server: {
        host: '127.0.0.1', // Match Laravel origin; avoid [::1] vs 127.0.0.1 mismatch
        port: 5173,
        strictPort: true,
        // Allow Laravel app origin to load Vite dev scripts without CORS mismatch.
        cors: {
            origin: ['http://127.0.0.1:8000', 'http://localhost:8000'],
        },
        hmr: {
            host: '127.0.0.1',
            protocol: 'ws',
            port: 5173,
            clientPort: 5173,
            timeout: 20000,
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    build: {
        // Fewer, larger chunks = fewer requests; browser caches them (fast repeat loads)
        rollupOptions: {
            output: {
                manualChunks: (id) => {
                    if (id.includes('node_modules')) return 'vendor';
                },
                chunkFileNames: 'js/[name]-[hash].js',
                entryFileNames: 'js/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },
        chunkSizeWarningLimit: 600,
        sourcemap: false, // Faster build; set true if you need to debug production
    },
});
