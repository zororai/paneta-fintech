/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.ts',
    ],
    theme: {
        extend: {
            colors: {
                paneta: {
                    primary: '#0B3D91',
                    primaryDark: '#072C66',
                    accent: '#D4AF37',
                    accentLight: '#F1D27A',
                    navy: '#0A1A2F',
                    bg: '#F8FAFC',
                    gray: '#E5E7EB',
                    text: '#1F2937',
                    white: '#FFFFFF',
                },
            },
            fontFamily: {
                sans: ['Inter', 'Instrument Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            borderRadius: {
                'xl': '1rem',
                '2xl': '1.5rem',
            },
        },
    },
    plugins: [],
};
