/* eslint-disable no-restricted-globals */
/**
 * DDP app-shell service worker (Phase 8, T10).
 *
 * Strategy:
 *  - Precache a minimal app shell + the offline fallback on install.
 *  - Navigation requests: network-first, falling back to the cached page or
 *    `/offline`.
 *  - Static same-origin GET assets: cache-first with a background refresh.
 *  - `/api/` requests are NEVER cached or intercepted — orders, auth and all
 *    mutations must always hit the network (offline order capture is handled
 *    separately by the IndexedDB queue in T11).
 */

const CACHE_NAME = 'ddp-shell-v1';
const OFFLINE_URL = '/offline';
const PRECACHE_URLS = ['/', OFFLINE_URL, '/icon-192.png', '/icon-512.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Never touch non-GET, cross-origin or API traffic.
  if (request.method !== 'GET' || url.origin !== self.location.origin) return;
  if (url.pathname.startsWith('/api/')) return;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() =>
        caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL)),
      ),
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      const network = fetch(request)
        .then((response) => {
          if (response && response.ok) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          }
          return response;
        })
        .catch(() => cached);
      return cached || network;
    }),
  );
});
