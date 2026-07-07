// Minimal PWA service worker — installability only.
// Deliberately does NOT cache pages or API responses: this app shows live
// billing/invoice/stock data, and a cached response could be shown as if
// current. It only exists so the browser treats the app as installable.
self.addEventListener('install', (e) => {
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  self.clients.claim();
});

// Pass every request straight to the network — no offline cache.
self.addEventListener('fetch', (e) => {
  e.respondWith(fetch(e.request));
});
