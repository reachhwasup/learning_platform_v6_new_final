/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.{php,html,js}",
    "./admin/**/*.{php,html,js}",
    "./includes/**/*.{php,html,js}"
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
      },
      colors: {
        'primary': '#0052cc',
        'primary-dark': '#0041a3',
        'secondary': '#f4f5f7',
        'accent': '#ffab00',
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
