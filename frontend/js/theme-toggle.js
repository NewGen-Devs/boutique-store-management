/**
 * Global Theme Toggle Logic
 */
(function () {
    // Initialize theme on load (minimizes flash)
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);

    document.addEventListener('DOMContentLoaded', () => {
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');

        if (themeToggle) {
            // Set initial icon
            if (themeIcon) {
                themeIcon.setAttribute('data-lucide', currentTheme === 'dark' ? 'sun' : 'moon');
                if (window.lucide) lucide.createIcons();
            }

            themeToggle.addEventListener('click', () => {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                const newTheme = isDark ? 'light' : 'dark';

                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);

                if (themeIcon) {
                    themeIcon.setAttribute('data-lucide', newTheme === 'dark' ? 'sun' : 'moon');
                    if (window.lucide) lucide.createIcons();
                }
            });
        }
    });
})();
