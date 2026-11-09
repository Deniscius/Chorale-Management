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
            colors: {
                choir: {50:'#f6f2f5',100:'#ede3eb',200:'#dbcbd8',300:'#c0a5b8',400:'#a17f98',500:'#855f7d',600:'#6b4964',700:'#593c53',800:'#493447',900:'#392c38',950:'#292028'},
                slate: {50:'#faf9f6',100:'#f0eeeb',200:'#dedad6',300:'#c8c2bd',400:'#7a706a',500:'#716963',600:'#625b56',700:'#514b48',800:'#403a3d',900:'#302c32',950:'#211e23'},
            },
            boxShadow: {sm:'0 1px 2px rgb(48 44 50 / .035)'},
            fontFamily: {
                sans: ['"Source Sans 3"', ...defaultTheme.fontFamily.sans],
                display: ['Alegreya', 'Georgia', 'serif'],
            },
        },
    },

    plugins: [forms],
};
