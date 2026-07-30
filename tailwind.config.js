import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import aspectRatio from '@tailwindcss/aspect-ratio';

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      boxShadow: {
        soft: "0 10px 30px rgba(0,0,0,.15)",
        glow: "0 0 0 1px rgba(255,255,255,.06), 0 15px 40px rgba(0,0,0,.35)",
      },
      backdropBlur: {
        xs: '2px',
      }
    },
  },
  plugins: [
    forms,
    aspectRatio,
  ],
}
