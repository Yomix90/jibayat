import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Noto Kufi Arabic', ...defaultTheme.fontFamily.sans],
                ar: ['Noto Kufi Arabic', 'Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                bg: '#F2F3F5',
                line: '#E6E8EC',
                ink: '#1F2933',
                muted: '#6B7280',
                navy: '#1E2A3A',
                gold: '#C9A13B',
                accent: '#C8601E',
                online: '#16A34A',
            },
        },
    },

    plugins: [forms],
};
