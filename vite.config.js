import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
import fs from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Check if vendor ziggy exists
const vendorZiggyPath = resolve(__dirname, '../../vendor/tightenco/ziggy');
const hasVendorZiggy = fs.existsSync(vendorZiggyPath);

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            // Use vendor ziggy if available, otherwise use mock
            '../../vendor/tightenco/ziggy': hasVendorZiggy 
                ? vendorZiggyPath
                : resolve(__dirname, 'resources/js/ziggy-mock.js'),
        },
    },
});
