/*
 * Family Hub mobile service worker.
 *
 * Deliberately conservative:
 *  - Never caches API responses, so mutations are always fresh.
 *  - Navigation requests are network-first with the app shell as an offline fallback.
 *  - Hashed build assets are cached stale-while-revalidate.
 */
const CACHE = 'family-hub-mobile-v1'
const APP_SHELL = '/m'

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll([APP_SHELL]))
            .catch(() => undefined),
    )
    self.skipWaiting()
})

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    )
})

self.addEventListener('fetch', (event) => {
    const { request } = event
    const url = new URL(request.url)

    if (url.origin !== self.location.origin) return
    if (url.pathname.startsWith('/api/')) return
    if (request.method !== 'GET') return

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone()
                    caches.open(CACHE).then((cache) => cache.put(APP_SHELL, copy))
                    return response
                })
                .catch(() => caches.match(APP_SHELL)),
        )
        return
    }

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const network = fetch(request)
                    .then((response) => {
                        const copy = response.clone()
                        caches.open(CACHE).then((cache) => cache.put(request, copy))
                        return response
                    })
                    .catch(() => cached)
                return cached || network
            }),
        )
    }
})
