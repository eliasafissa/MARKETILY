/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: [
          'Space Grotesk',
          'ui-sans-serif',
          'system-ui',
          '-apple-system',
          'Segoe UI',
          'sans-serif',
        ],
        heading: [
          'Space Grotesk',
          'ui-sans-serif',
          'system-ui',
          'sans-serif',
        ],
        display: [
          'Space Grotesk',
          'ui-sans-serif',
          'system-ui',
          'sans-serif',
        ],
        mono: ['JetBrains Mono', 'ui-monospace', 'SFMono-Regular', 'monospace'],
      },
      colors: {
        // Surfaces — dark theme (default)
        ink: {
          DEFAULT: '#0A0A0A',
          base:    '#0A0A0A',
          50:      '#111111',
          100:     '#161616',
          200:     '#1F1F1F',
          300:     '#2A2A2A',
          400:     '#3D3D3D',
          500:     '#5C5C5C',
          600:     '#8A8A8A',
          700:     '#B5B5B5',
          800:     '#E5E5E5',
          900:     '#FAFAFA',
        },
        // Surfaces — light theme
        light: {
          DEFAULT: '#FFFFFF',
          base:    '#FFFFFF',
          50:      '#F8F9FA',
          100:     '#F1F3F5',
          200:     '#E9ECEF',
          300:     '#DEE2E6',
          400:     '#CED4DA',
          500:     '#ADB5BD',
          600:     '#868E96',
          700:     '#495057',
          800:     '#343A40',
          900:     '#212529',
        },
        // Accent — Emerald Green
        accent: {
          50:  '#ECFDF5',
          100: '#D1FAE5',
          200: '#A7F3D0',
          300: '#6EE7B7',
          400: '#34D399',
          500: '#22C55E',
          600: '#16A34A',
          700: '#15803D',
          800: '#166534',
          900: '#14532D',
        },
        // Status colors — same in both themes
        status: {
          pending:    '#F5A524',
          processing: '#3B82F6',
          completed:  '#10B981',
          rejected:   '#EF4444',
          vip:       '#A78BFA',
        },
      },
      boxShadow: {
        'soft':   '0 1px 2px 0 rgba(0,0,0,0.4)',
        'lift':   '0 18px 40px -16px rgba(0,0,0,0.45), 0 1px 2px 0 rgba(0,0,0,0.3)',
        'glow':   '0 0 0 1px rgba(34,197,94,0.45), 0 12px 32px -10px rgba(34,197,94,0.35)',
        'glow-lg': '0 0 0 1px rgba(34,197,94,0.55), 0 24px 60px -16px rgba(34,197,94,0.45)',
      },
      letterSpacing: {
        tightest: '-0.04em',
        tighter: '-0.02em',
      },
      fontSize: {
        'display-1': ['4.5rem', { lineHeight: '1.05', letterSpacing: '-0.04em', fontWeight: '800' }],
        'display-2': ['3.5rem', { lineHeight: '1.1',  letterSpacing: '-0.03em', fontWeight: '800' }],
        'h1':         ['2.5rem',  { lineHeight: '1.15', letterSpacing: '-0.025em', fontWeight: '700' }],
        'h2':         ['1.875rem',{ lineHeight: '1.2',  letterSpacing: '-0.02em',  fontWeight: '700' }],
        'h3':         ['1.375rem',{ lineHeight: '1.3',  letterSpacing: '-0.015em', fontWeight: '600' }],
        'body-lg':    ['1.0625rem',{ lineHeight: '1.6' }],
        'body':       ['0.9375rem',{ lineHeight: '1.6' }],
        'small':      ['0.8125rem',{ lineHeight: '1.5' }],
        'micro':      ['0.6875rem',{ lineHeight: '1.4', letterSpacing: '0.08em', fontWeight: '600' }],
      },
      borderRadius: {
        'sm': '6px', DEFAULT: '8px', 'md': '10px',
        'lg': '12px', 'xl': '16px', '2xl': '20px',
      },
      transitionTimingFunction: {
        'smooth': 'cubic-bezier(0.16, 1, 0.3, 1)',
      },
    },
  },
  plugins: [],
};
