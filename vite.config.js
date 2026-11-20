import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/crm.css', 'resources/css/crm-calls.css', 'resources/css/crm-customer.css', 'resources/css/crm-operations.css', 'resources/css/crm-travel.css', 'resources/css/crm-flight-quote.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
