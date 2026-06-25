/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        clinic: {
          50:  '#EBF8FF',
          100: '#D6EEFB',
          200: '#A6D8F4',
          400: '#4299E1',
          500: '#3182CE',
          600: '#2B6CB0',
          700: '#225488',
          800: '#1A365D'
        }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif']
      }
    }
  },
  plugins: []
}
