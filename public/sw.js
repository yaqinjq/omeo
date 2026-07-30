const CACHE_VERSION = 'omeo-shell-v1';
const CORE_URLS = ['/', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_VERSION);

    // Cache app shell routes first.
    await cache.addAll(CORE_URLS);

    // Try caching hashed build assets from Vite manifest.
    try {
      const response = await fetch('/build/manifest.json', { cache: 'no-store' });
      if (response.ok) {
        const manifest = await response.json();
        const assets = new Set();

        Object.values(manifest).forEach((entry) => {
          if (entry.file) assets.add('/build/' + entry.file);
          if (Array.isArray(entry.css)) entry.css.forEach((css) => assets.add('/build/' + css));
          if (Array.isArray(entry.assets)) entry.assets.forEach((asset) => assets.add('/build/' + asset));
        });

        await cache.addAll(Array.from(assets));
      }
    } catch (_) {
      // Silent fallback: app keeps running without precaching build assets.
    }
  })());

  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map((key) => key !== CACHE_VERSION ? caches.delete(key) : Promise.resolve()));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  const request = event.request;
  const url = new URL(request.url);
  const sameOrigin = url.origin === self.location.origin;

  if (!sameOrigin) return;

  // Build assets: cache-first for faster app shell.
  if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
    event.respondWith((async () => {
      const cache = await caches.open(CACHE_VERSION);
      const cached = await cache.match(request);
      if (cached) return cached;

      try {
        const network = await fetch(request);
        if (network && network.ok) cache.put(request, network.clone());
        return network;
      } catch (_) {
        return cached || Response.error();
      }
    })());
    return;
  }

  // HTML pages: network-first, fallback cache.
  if (request.mode === 'navigate') {
    event.respondWith((async () => {
      const cache = await caches.open(CACHE_VERSION);
      try {
        const network = await fetch(request);
        if (network && network.ok) cache.put(request, network.clone());
        return network;
      } catch (_) {
        const cached = await cache.match(request);
        return cached || cache.match('/');
      }
    })());
  }
});
