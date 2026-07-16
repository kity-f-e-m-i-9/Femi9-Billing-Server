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

// No fetch handler: this worker only exists so the browser treats the app
// as installable. Registering a fetch listener here previously caused
// "Failed to fetch" errors on the browser's own speculative/preload
// requests (e.g. Chrome's "preload pages" feature) whenever fetch(request)
// was called on a request the browser hadn't fully formed yet — omitting
// the handler entirely means every request goes straight to the network,
// untouched.
