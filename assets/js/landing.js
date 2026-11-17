(function () {
    'use strict';

    const themeBtn = document.getElementById('themeToggle');
    const themeColorMeta = document.querySelector('meta[name="theme-color"]');

    const applyTheme = function (dark) {
        if (dark) {
            document.documentElement.setAttribute('data-theme', 'dark');
            if (themeColorMeta) themeColorMeta.setAttribute('content', '#0a1710');
            themeBtn && themeBtn.setAttribute('aria-label', 'Ganti ke mode terang');
        } else {
            document.documentElement.removeAttribute('data-theme');
            if (themeColorMeta) themeColorMeta.setAttribute('content', '#0d3823');
            themeBtn && themeBtn.setAttribute('aria-label', 'Ganti ke mode gelap');
        }
    };

    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            const dark = document.documentElement.getAttribute('data-theme') === 'dark';
            try {
                localStorage.setItem('recycash-theme', dark ? 'light' : 'dark');
            } catch (e) {}
            applyTheme(!dark);
        });
        applyTheme(document.documentElement.getAttribute('data-theme') === 'dark');
    }

    const header = document.getElementById('siteHeader');
    const toggle = document.getElementById('menuToggle');
    const nav = document.getElementById('mainNav');
    const navLinks = nav ? Array.from(nav.querySelectorAll('a[href^="#"]')) : [];

    const closeMenu = function () {
        if (!nav || !toggle) return;
        nav.classList.remove('is-open');
        toggle.classList.remove('is-active');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Buka menu navigasi');
    };

    const openMenu = function () {
        if (!nav || !toggle) return;
        nav.classList.add('is-open');
        toggle.classList.add('is-active');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Tutup menu navigasi');
    };

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            if (nav.classList.contains('is-open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        nav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });

        document.addEventListener('click', function (event) {
            if (!nav.classList.contains('is-open')) return;
            if (nav.contains(event.target) || toggle.contains(event.target)) return;
            closeMenu();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeMenu();
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 980) closeMenu();
        });
    }

    const updateHeader = function () {
        if (!header) return;
        header.classList.toggle('is-scrolled', window.scrollY > 8);
    };

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    const revealItems = document.querySelectorAll('.reveal');

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.08,
            rootMargin: '0px 0px -40px 0px'
        });

        revealItems.forEach(function (item) {
            revealObserver.observe(item);
        });
    } else {
        revealItems.forEach(function (item) {
            item.classList.add('is-visible');
        });
    }

    const setActiveLink = function (id) {
        if (!navLinks.length) return;
        navLinks.forEach(function (link) {
            const isActive = link.getAttribute('href') === '#' + id;
            link.classList.toggle('is-active', isActive);
        });
    };

    if (navLinks.length && 'IntersectionObserver' in window) {
        const sectionIds = navLinks.map(function (link) {
            return link.getAttribute('href');
        });

        const sections = sectionIds.map(function (id) {
            return document.querySelector(id);
        }).filter(Boolean);

        const sectionObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                setActiveLink(entry.target.id);
            });
        }, {
            rootMargin: '-38% 0px -55% 0px',
            threshold: 0
        });

        sections.forEach(function (section) {
            sectionObserver.observe(section);
        });
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const toast = document.querySelector('.hero-toast');

    if (toast && reduceMotion.matches) {
        toast.style.animation = 'none';
    }
})();
