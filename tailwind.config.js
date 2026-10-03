/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './app/Livewire/**/*.php',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            fontFamily: {
                sans: ['Vazirmatn', 'Tahoma', 'Segoe UI', 'sans-serif'],
            },
            colors: {
                surface: {
                    50: '#f8fafc',
                    100: '#f1f5f9',
                    200: '#e2e8f0',
                    300: '#cbd5e1',
                    400: '#94a3b8',
                    500: '#64748b',
                    600: '#475569',
                    700: '#334155',
                    800: '#1e293b',
                    900: '#0f172a',
                    950: '#020617',
                },
                brand: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                },
            },
            keyframes: {
                'pulse-ring': {
                    '0%': { transform: 'scale(.6)', opacity: '1' },
                    '100%': { transform: 'scale(2.2)', opacity: '0' },
                },
                'flash-border': {
                    '0%, 100%': { boxShadow: '0 0 0 3px rgba(239,68,68,.95)' },
                    '50%': { boxShadow: '0 0 0 3px rgba(239,68,68,.25)' },
                },
                'slide-in': {
                    from: { transform: 'translateX(-1rem)', opacity: '0' },
                    to: { transform: 'translateX(0)', opacity: '1' },
                },
            },
            animation: {
                'pulse-ring': 'pulse-ring 1.6s cubic-bezier(.2,.6,.4,1) infinite',
                'flash-border': 'flash-border .9s ease-in-out infinite',
                'slide-in': 'slide-in .25s ease-out both',
            },
        },
    },
    plugins: [require('tailwindcss-rtl')],
};
