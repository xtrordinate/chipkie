import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',

        // Formkit. Run `npx formkit theme` to edit the theme
        './formkit.theme.mjs',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Nexa Book', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                navy: {
                    400: '#004053'
                },
                lightgreen: {
                    400: '#6dc4bc',
                },
                darkgreen: {
                    400: '#007c89'
                },
                teal: {
                    100: '#BFDEE1',
                    200: '#BFDEE1',
                    300: '#BFDEE1',
                    400: '#007c89',
                    500: '#007c89',
                    600: '#007c89',
                    700: '#007c89',
                    800: '#007c89',
                    900: '#007c89',
                },
                salmon: {
                    400: 'rgb(245, 131, 103)'
                },
                blue: {
                    400: '#5091cd'
                },
            }
        },
    },
    darkMode: 'class',
    plugins: [forms],
};
