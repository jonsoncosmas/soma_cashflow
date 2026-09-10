/**
 * Soma Cashflow - Service worker (Phase 10)
 *
 * Strategy:
 *  - Static assets (JS, manifest, icons): cache-first, since they rarely change.
 *  - Page navigations (GET requests for HTML pages): network-first, falling
 *    back to whatever was last cached for that exact URL if the network is
 *    unavailable. This means pages you've actually visited before work
 *    offline (with whatever data was current at your last visit); pages
 *    you've never opened show the friendly offline.php fallback instead of
 *    a browser error.
 *  - Anything that isn't a GET request (form submissions, the API endpoints
 *    from Phase 9) is left completely alone - never cached, never
 *    intercepted - so writes always behave exactly as the page JS expects.
 *
 * Bump CACHE_VERSION whenever static asset content changes, so old caches
 * get cleaned up automatically on the next activate.
 */

const CACHE_VERSION = 'soma-cashflow-v1';
const OFFLINE_URL = '/soma_cashflow/public/offline.php';

const STATIC_ASSETS = [
    '/soma_cashflow/public/js/offline_sync.js',
    '/soma_cashflow/public/manifest.json',
    '/soma_cashflow/public/icons/icon-192.png',
    '/soma_cashflow/public/icons/icon-512.png',
    OFFLINE_URL,
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Never touch non-GET requests (form POSTs, the Phase 9 API endpoints) -
    // those must always go straight to the network untouched.
    if (req.method !== 'GET') {
        return;
    }

    // Page navigations: network-first, cache the result, fall back to cache
    // (or the offline page) if the network fails.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req)
                .then((res) => {
                    const clone = res.clone();
                    caches.open(CACHE_VERSION).then((cache) => cache.put(req, clone));
                    return res;
                })
                .catch(() =>
                    caches.match(req).then((cached) => cached || caches.match(OFFLINE_URL))
                )
        );
        return;
    }

    // Only the explicit static assets list is cache-first. Everything else
    // (photo.php, statement_pdf.php, and any other dynamic GET endpoint)
    // is left alone entirely - these carry access-controlled or generated
    // content that must always go through the PHP auth check, never served
    // from a cache that could outlive a login session on a shared device.
    if (STATIC_ASSETS.includes(new URL(req.url).pathname)) {
        event.respondWith(
            caches.match(req).then((cached) => cached || fetch(req))
        );
    }
});
