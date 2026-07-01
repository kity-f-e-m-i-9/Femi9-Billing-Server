self.addEventListener('install', event => {
  event.waitUntil(
    caches.open('v1').then(cache => {
      return cache.addAll([
        '/femi9',
        '/femi9/styles/main.css',  // Adjust path as necessary for your styles
        '/femi9/scripts/main.js',  // Adjust path as necessary for your scripts
        '/femi9/images/logo.png',  // Adjust path as necessary for your images
        // Include other assets
      ]);
    })
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request);
    })
  );
});
