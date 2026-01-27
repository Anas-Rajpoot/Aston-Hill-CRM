import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.js',
  ],

  theme: {
    extend: {
      fontFamily: {
        sans: ['Figtree', ...defaultTheme.fontFamily.sans],
      },

      colors: {
        brand: {
          primary: 'rgb(var(--brand-primary) / <alpha-value>)',
          dark: 'rgb(var(--brand-dark) / <alpha-value>)',
          bg: 'rgb(var(--brand-bg) / <alpha-value>)',
          text: 'rgb(var(--brand-text) / <alpha-value>)',
        },
      },
    },
  },

  plugins: [forms],
};
