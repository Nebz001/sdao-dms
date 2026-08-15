import path from 'node:path';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/testing/setup.ts'],
        css: false,
        // Vitest's default exclude list only knows about node_modules, not
        // PHP's vendor/ — pestphp/pest-plugin-browser ships its own example
        // *.spec.js files (importing @playwright/test, which this project
        // doesn't install) under vendor/, and vitest's default include glob
        // picks them up otherwise.
        exclude: ['**/node_modules/**', '**/vendor/**'],
    },
});
