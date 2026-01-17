const CACHE_NAME = 'vietstudocs-cache-v1';
const ASSETS_TO_CACHE = [
    '/',
    '/index.php',
    '/css/fontawesome/all.css',
    '/assets/js/notifications.js'
];

// Install Event - Caching basic assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[Service Worker] Caching app shell');
                return cache.addAll(ASSETS_TO_CACHE);
            })
    );
    self.skipWaiting();
});

// Activate Event
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keyList => {
            return Promise.all(keyList.map(key => {
                if (key !== CACHE_NAME) {
                    console.log('[Service Worker] Removing old cache', key);
                    return caches.delete(key);
                }
            }));
        })
    );
    return self.clients.claim();
});

// Fetch Event - Network first, then cache
self.addEventListener('fetch', event => {
    // Skip non-GET requests and cross-origin requests (except CDNs we want to cache)
    if (event.request.method !== 'GET') return;
    
    // Skip unsupported schemes (chrome-extension, etc.)
    if (!event.request.url.startsWith('http')) return;

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Clone the response to store it in cache
                const resClone = response.clone();
                if (response.status === 200) {
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, resClone);
                    });
                }
                return response;
            })
            .catch(() => {
                // If network fails, try cache
                return caches.match(event.request).then(response => {
                    if (response) return response;
                    // If both fail and it's a page request, we could return an offline page
                    // return caches.match('/offline.html');
                });
            })
    );
});

self.addEventListener('push', function (event) {
    console.log('[Service Worker] Push Received.');
    let data = { title: 'Thông báo mới', body: 'Bạn có tin nhắn mới từ DocShare' };

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: '/assets/img/logo.png',
        badge: '/assets/img/badge.png',
        data: {
            url: data.url || '/dashboard.php'
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    event.waitUntil(
        clients.openWindow(event.notification.data.url)
    );
});
