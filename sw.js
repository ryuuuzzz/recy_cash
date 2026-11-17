/**
 * RecyCash Service Worker (PWA / offline untuk teller lapangan)
 * - Aset statis (CSS/JS/font/ikon): cache-first
 * - Halaman (navigasi): network-first, fallback offline.html
 */
const CACHE_NAME = 'recycash-v2';
const OFFLINE_URL = './offline.html';

const PRECACHE = [
    OFFLINE_URL,
    './manifest.webmanifest',
    './assets/css/app.css',
    './assets/js/app.js',
    './assets/js/lokasi.js',
    './assets/img/icon-192.png',
    './assets/img/icon-512.png',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2',
    'https://cdn.jsdelivr.net/npm/aos@2.3.1/dist/aos.css',
    'https://cdn.jsdelivr.net/npm/aos@2.3.1/dist/aos.js',
    'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Jangan pernah intercept non-GET (form POST/transaksi harus ke server)
    if (req.method !== 'GET') return;

    // Navigasi halaman: network-first, fallback cache lalu offline.html
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req)
                .then((res) => {
                    const copy = res.clone();
                    caches.open(CACHE_NAME).then((c) => c.put(req, copy)).catch(() => {});
                    return res;
                })
                .catch(() =>
                    caches.match(req).then((hit) => hit || caches.match(OFFLINE_URL))
                )
        );
        return;
    }

    // Aset statis: cache-first dengan pembaruan di latar
    const url = new URL(req.url);
    const isStatic = /\.(css|js|woff2?|png|jpe?g|gif|svg|ico|webp)$/i.test(url.pathname) ||
        url.hostname.includes('fonts.googleapis') ||
        url.hostname.includes('fonts.gstatic') ||
        url.hostname.includes('jsdelivr');

    if (isStatic) {
        event.respondWith(
            caches.match(req).then((hit) => {
                if (hit) {
                    // Revalidate di latar agar cache tetap segar
                    fetch(req).then((res) => {
                        if (res && res.ok) {
                            caches.open(CACHE_NAME).then((c) => c.put(req, res.clone()));
                        }
                    }).catch(() => {});
                    return hit;
                }
                return fetch(req).then((res) => {
                    if (res && (res.ok || res.type === 'opaque')) {
                        const copy = res.clone();
                        caches.open(CACHE_NAME).then((c) => c.put(req, copy)).catch(() => {});
                    }
                    return res;
                });
            })
        );
    }
});
