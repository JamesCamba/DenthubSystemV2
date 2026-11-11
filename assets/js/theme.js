(function () {
    const KEY = 'denthub-theme';

    function currentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    }

    function setTheme(theme) {
        const next = theme === 'dark' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        document.documentElement.setAttribute('data-bs-theme', next);
        try { localStorage.setItem(KEY, next); } catch (e) {}
        document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
            }
            btn.setAttribute('aria-label', next === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        });
        document.dispatchEvent(new CustomEvent('denthub:theme', { detail: { theme: next } }));
        if (window.Denthub && Denthub.applyChartDefaults) {
            Denthub.applyChartDefaults();
        }
    }

    function toggleTheme() {
        setTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-theme-toggle]');
        if (!btn) return;
        e.preventDefault();
        toggleTheme();
    });

    document.addEventListener('DOMContentLoaded', function () {
        setTheme(currentTheme());
    });
})();
