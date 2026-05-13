/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'system-ui', '-apple-system', 'sans-serif'],
      },
      colors: {
        brand: {
          50: '#eef2ff',
          100: '#e0e7ff',
          500: '#6366f1',
          600: '#4f46e5',
          700: '#4338ca',
          900: '#312e81',
        },
        ink: {
          DEFAULT: '#0f172a',
          muted: '#475569',
          soft: '#64748b',
        },
        category: {
          wfh: '#8b5cf6',
          titip: '#f59e0b',
          tenaga: '#10b981',
          service: '#f43f5e',
        },
      },
      borderRadius: {
        card: '12px',
        btn: '8px',
      },
      boxShadow: {
        card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
        elevated: '0 10px 25px -5px rgb(15 23 42 / 0.10), 0 4px 6px -2px rgb(15 23 42 / 0.05)',
      },
      animation: {
        'fade-in': 'fadeIn 200ms ease',
        'slide-up': 'slideUp 220ms cubic-bezier(0.16, 1, 0.3, 1)',
      },
      keyframes: {
        fadeIn: { '0%': { opacity: 0 }, '100%': { opacity: 1 } },
        slideUp: { '0%': { opacity: 0, transform: 'translateY(12px)' }, '100%': { opacity: 1, transform: 'translateY(0)' } },
      },
    },
  },
  plugins: [],
};
