import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Palette des badges : les couleurs sont composées dynamiquement dans les
// composants (badges d'enum, pastilles de saison), le scanner JIT ne peut donc
// pas les voir. On liste uniquement les classes réellement produites : une
// safelist par motif générerait aussi toutes les variantes d'opacité
// (bg-…-100/0, /5, …), soit plusieurs milliers de règles inutiles.
const palette = [
    'gray', 'green', 'amber', 'red', 'blue', 'indigo', 'purple', 'pink', 'rose',
    'sky', 'orange', 'teal', 'lime', 'violet', 'emerald', 'cyan', 'slate',
];

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/**/*.php',
    ],

    safelist: palette.flatMap((color) => [
        `bg-${color}-100`,
        `text-${color}-800`,
        `bg-${color}-500`,
    ]),

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
