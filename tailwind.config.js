/** @type {import('tailwindcss').Config} */
const colors = require('tailwindcss/colors')

module.exports = {
    content: [
        './resources/**/*.blade.php',
        './app/Filament/**/*.php',
        './app/Http/Livewire/**/*.php',
        './vendor/filament/**/*.blade.php',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                danger: colors.rose,
                primary: colors.blue,
                success: colors.green,
                warning: colors.yellow,
            },
            spacing: {
                1: '0.25rem', // Ensure is available for spacing
            },
            gap: {
                1: '0.25rem', // Ensure is available for gap utilities
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
        require('flowbite/plugin')
    ],
    safelist: [
        'gap-1',
        'bg-white',
    ],
}
