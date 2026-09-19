// Minimal service worker — exists only to satisfy PWA installability
// criteria (a registered SW with a fetch handler) and to show a friendly
// offline page instead of the browser's default error. Deliberately does
// NOT cache any app content: this is a highly dynamic, server-driven app
// (Livewire round-trips on nearly every interaction), so caching pages,
// scripts, or styles here would risk serving stale content instead — the
// same failure mode as the stale compiled-asset bug this app already hit
// in production once, just self-inflicted this time.
const OFFLINE_CACHE = 'letz-manage-offline-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(OFFLINE_CACHE).then((cache) => cache.add(OFFLINE_URL))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => caches.match(OFFLINE_URL))
    );
});
