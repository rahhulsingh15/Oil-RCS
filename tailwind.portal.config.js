/**
 * Tailwind (v3) config for the portal's prebuilt stylesheet public/css/portal.css.
 * Replaces the in-browser Tailwind CDN, which is slow and not meant for production.
 *
 * After changing classes in any Blade view, rebuild:
 *   npx tailwindcss@3.4.16 -c tailwind.portal.config.js -i resources/css/portal.css -o public/css/portal.css --minify
 */
module.exports = {
  content: [
    './resources/views/**/*.blade.php',
    './app/**/*.php',
  ],
  // Classes built from variables in Blade (e.g. text-{{ $color }}-600)
  safelist: [
    { pattern: /^(text|bg|border)-(emerald|amber|red|blue|orange|slate)-(50|100|200|500|600|700)$/ },
  ],
  theme: {
    extend: {
      colors: {
        oilSidebar: '#0b1626',
        oilSidebarDark: '#070e19',
        oilSidebarHover: '#14233a',
        oilSidebarActive: '#1d4ed8',
        oilBg: '#f1f5f9',
        oilCardBorder: '#e2e8f0',
        oilNavyText: '#0f172a',
      },
      fontFamily: {
        sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
      },
    },
  },
};
