import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.{vue,js}',
  ],

  theme: {
    extend: {
      fontFamily: {
        sans: ['Figtree', ...defaultTheme.fontFamily.sans],
      },
      colors: {
        brand: {
          primary:       'rgb(var(--brand-primary) / <alpha-value>)',
          'primary-hover':'rgb(var(--brand-primary-hover) / <alpha-value>)',
          'primary-dark': 'rgb(var(--brand-primary-dark) / <alpha-value>)',
          'primary-light':'rgb(var(--brand-primary-light) / <alpha-value>)',
          'primary-muted':'rgb(var(--brand-primary-muted) / <alpha-value>)',
          dark:           'rgb(var(--brand-dark) / <alpha-value>)',
          bg:             'rgb(var(--brand-bg) / <alpha-value>)',
          text:           'rgb(var(--brand-text) / <alpha-value>)',
          surface:        'rgb(var(--brand-surface) / <alpha-value>)',
          border:         'rgb(var(--brand-border) / <alpha-value>)',
          'muted-text':   'rgb(var(--brand-muted-text) / <alpha-value>)',
        },
        sidebar: {
          bg:       'rgb(var(--sidebar-bg) / <alpha-value>)',
          hover:    'rgb(var(--sidebar-hover) / <alpha-value>)',
          border:   'rgb(var(--sidebar-border) / <alpha-value>)',
          text:     'rgb(var(--sidebar-text) / <alpha-value>)',
        },
        neon: {
          green: 'var(--neon-green)',
        },
        status: {
          success: 'rgb(var(--status-success) / <alpha-value>)',
          warning: 'rgb(var(--status-warning) / <alpha-value>)',
          danger:  'rgb(var(--status-danger) / <alpha-value>)',
          info:    'rgb(var(--status-info) / <alpha-value>)',
        },
      },
    },
  },

  plugins: [forms],
};
