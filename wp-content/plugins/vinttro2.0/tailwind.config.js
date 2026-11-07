/** @type {import('tailwindcss').Config} */
export default {
  prefix: 'vt-',
  content: [
    './src/**/*.{js,jsx,ts,tsx}',
  ],
  corePlugins: {
    preflight: false, // Prevents resetting WordPress base styles
  },
  theme: {
    extend: {},
  },
  plugins: [],
};