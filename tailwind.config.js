import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    teal: '#0D9488',
                    mint: '#F0FDFA',
                    charcoal: '#0F172A',
                    offwhite: '#F1F5F9',
                },
                status: {
                    success: '#16A34A',
                    warning: '#EA580C',
                    neutral: '#64748B',
                },
                indigo: {
                    50: '#F0FDFA',   // Mint Tint (wash)
                    100: '#CCFBF1',
                    200: '#99F6E4',
                    300: '#5EEAD4',
                    400: '#2DD4BF',
                    500: '#14B8A6',
                    600: '#0D9488',  // Deep Teal (primary brand button)
                    700: '#0F766E',
                    800: '#115E59',
                    900: '#134E4A',
                    950: '#042F2E',
                },
            },
        },
    },

    plugins: [forms, typography],
};
