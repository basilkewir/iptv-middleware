import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        screens: {
            'sm': '640px',
            'md': '768px',
            'lg': '1024px',
            'xl': '1280px',
            '2xl': '1536px',
            '3xl': '1920px',
            'tv': '2560px',
        },
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                dark: '#0a0a1a',
                primary: {
                    50: '#f5f3ff',
                    100: '#ede9fe',
                    200: '#ddd6fe',
                    300: '#c4b5fd',
                    400: '#a78bea',
                    500: '#8b5cf6',
                    600: '#7c3aed',
                    700: '#6d28d9',
                    800: '#5b21b6',
                    900: '#4c1d95',
                },
            },
            spacing: {
                'safe-top': 'env(safe-area-inset-top)',
                'safe-bottom': 'env(safe-area-inset-bottom)',
                'safe-left': 'env(safe-area-inset-left)',
                'safe-right': 'env(safe-area-inset-right)',
            },
            fontSize: {
                'tv-xs': '0.875rem',
                'tv-sm': '1rem',
                'tv-base': '1.125rem',
                'tv-lg': '1.25rem',
                'tv-xl': '1.5rem',
                'tv-2xl': '1.75rem',
                'tv-3xl': '2rem',
                'tv-4xl': '2.5rem',
            },
        },
    },

    plugins: [
        forms,
        typography,
        function({ addComponents, theme }) {
            addComponents({
                '.btn-primary': {
                    '@apply bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200': {},
                },
                '.btn-secondary': {
                    '@apply bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200': {},
                },
                '.btn-danger': {
                    '@apply bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200': {},
                },
                '.card': {
                    '@apply bg-gray-900 border border-gray-800 rounded-xl p-6': {},
                },
                '.input-field': {
                    '@apply w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent': {},
                },
                '.badge': {
                    '@apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium': {},
                },
                '.badge-success': {
                    '@apply bg-green-100 text-green-800': {},
                },
                '.badge-warning': {
                    '@apply bg-yellow-100 text-yellow-800': {},
                },
                '.badge-danger': {
                    '@apply bg-red-100 text-red-800': {},
                },
            });
        },
    ],

    darkMode: 'class',
};
