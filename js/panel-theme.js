(function () {
    var STORAGE_KEY = 'panel_theme_mode';

    function applyTheme(theme) {
        if (theme === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
        } else {
            document.body.removeAttribute('data-theme');
            theme = 'light';
        }
        var toggles = document.querySelectorAll('[data-theme-toggle]');
        for (var i = 0; i < toggles.length; i++) {
            var icon = toggles[i].querySelector('i');
            toggles[i].setAttribute('title', theme === 'dark' ? 'تفعيل الوضع العادي' : 'تفعيل الوضع الليلي');
            if (icon) {
                icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            }
        }
    }

    function loadTheme() {
        var saved = localStorage.getItem(STORAGE_KEY);
        if (saved !== 'dark' && saved !== 'light') {
            saved = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        applyTheme(saved);
    }

    function initToggles() {
        var toggles = document.querySelectorAll('[data-theme-toggle]');
        for (var i = 0; i < toggles.length; i++) {
            toggles[i].addEventListener('click', function () {
                var isDark = document.body.getAttribute('data-theme') === 'dark';
                var next = isDark ? 'light' : 'dark';
                localStorage.setItem(STORAGE_KEY, next);
                applyTheme(next);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadTheme();
        initToggles();
    });
})();
