(function () {
    'use strict';

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    function initThemeToggle() {
        var btn = document.getElementById('themeToggle');
        if (!btn) {
            return;
        }

        var root = document.documentElement;

        function applyTheme(dark) {
            root.setAttribute('data-theme', dark ? 'dark' : 'light');
            root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
            btn.setAttribute('aria-label', dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap');
            btn.setAttribute('title', dark ? 'Mode terang' : 'Mode gelap');
            document.dispatchEvent(new CustomEvent('recycash:themechange', {
                detail: { theme: dark ? 'dark' : 'light' }
            }));
        }

        applyTheme(root.getAttribute('data-theme') === 'dark');

        btn.addEventListener('click', function () {
            var dark = root.getAttribute('data-theme') === 'dark';
            var nextTheme = dark ? 'light' : 'dark';

            try {
                localStorage.setItem('recycash-theme', nextTheme);
            } catch (error) {
            }

            applyTheme(!dark);
        });
    }

    function initSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle = document.getElementById('btnMenuToggle');

        if (!sidebar || !overlay) {
            return;
        }

        function isOpen() {
            return sidebar.classList.contains('is-open');
        }

        function openSidebar() {
            sidebar.classList.add('is-open');
            overlay.classList.add('is-open');
            document.body.classList.add('sidebar-open');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
            }
        }

        function closeSidebar() {
            sidebar.classList.remove('is-open');
            overlay.classList.remove('is-open');
            document.body.classList.remove('sidebar-open');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        }

        window.toggleSidebar = function () {
            if (isOpen()) {
                closeSidebar();
            } else {
                openSidebar();
            }
        };

        document.querySelectorAll('[data-sidebar-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (isOpen()) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
        });

        document.querySelectorAll('[data-sidebar-close]').forEach(function (button) {
            button.addEventListener('click', closeSidebar);
        });

        overlay.addEventListener('click', closeSidebar);

        sidebar.querySelectorAll('.sidebar-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 991) {
                    closeSidebar();
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && isOpen()) {
                closeSidebar();
                if (toggle) {
                    toggle.focus();
                }
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 991 && isOpen()) {
                closeSidebar();
            }
        });
    }

    function initCountUps() {
        var elements = document.querySelectorAll('[data-countup]');
        if (!elements.length) {
            return;
        }

        elements.forEach(function (element) {
            var target = parseFloat(element.getAttribute('data-countup')) || 0;
            var decimals = parseInt(element.getAttribute('data-decimals') || '0', 10);
            var duration = parseInt(element.getAttribute('data-duration') || '1400', 10);
            var formatter = new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });

            if (reducedMotion || target === 0) {
                element.textContent = formatter.format(target);
                return;
            }

            var startedAt = null;

            function animate(timestamp) {
                if (startedAt === null) {
                    startedAt = timestamp;
                }

                var progress = Math.min((timestamp - startedAt) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                var current = decimals > 0
                    ? target * eased
                    : Math.round(target * eased);

                element.textContent = formatter.format(current);

                if (progress < 1) {
                    window.requestAnimationFrame(animate);
                }
            }

            window.requestAnimationFrame(animate);
        });
    }

    function preparePageAnimations() {
        if (reducedMotion) {
            return;
        }

        var cards = document.querySelectorAll('.main-content .card, .main-content .stat-card');
        var visibleCards = [];

        cards.forEach(function (card) {
            if (card.hasAttribute('data-aos') || card.closest('.tab-pane, .modal, .collapse')) {
                return;
            }

            visibleCards.push(card);
        });

        visibleCards.forEach(function (card, index) {
            card.setAttribute('data-aos', 'fade-up');
            card.setAttribute('data-aos-delay', String(Math.min(index * 45, 225)));
        });
    }

    function initScrollAnimations() {
        preparePageAnimations();

        if (window.AOS) {
            window.AOS.init({
                duration: 650,
                easing: 'ease-out-cubic',
                once: true,
                offset: 55,
                disable: reducedMotion
            });
            return;
        }

        document.querySelectorAll('[data-aos]').forEach(function (element) {
            element.style.opacity = '1';
            element.style.transform = 'none';
        });
    }

    onReady(function () {
        initThemeToggle();
        initSidebar();
        initCountUps();
        initScrollAnimations();
    });
})();
