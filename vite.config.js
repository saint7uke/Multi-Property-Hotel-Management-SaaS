import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/filament/hotel/theme.css',
                'resources/js/public.ts',
                'resources/js/staff-auth.ts',
                'resources/css/chat-widget.css',
                'resources/js/chat-widget.js',
                'resources/css/hotel-assistant.css',
                'resources/js/hotel-assistant.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
