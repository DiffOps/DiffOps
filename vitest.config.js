import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    test: {
        environment: 'happy-dom',
        globals: true,
        setupFiles: ['resources/js/__tests__/setup.jsx'],
        include: ['resources/js/__tests__/**/*.{test,spec}.{js,jsx}', 'resources/js/components/**/__tests__/**/*.{test,spec}.{js,jsx}'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'json', 'html'],
            exclude: ['node_modules/', 'resources/js/__tests__/setup.js'],
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
});