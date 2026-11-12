// =====================================================
// MyStudy Service Worker v2 — Production Speed PWA
// Cache-first for static assets, network-first for pages
// With Navigation Preload and offline fallback
// =====================================================

const CACHE_NAME = 'mystudy-v2';
const STATIC_CACHE = 'mystudy-static-v2';

// Static assets to pre-cache on install for instant repeat loads
const PRECACHE_URLS = [
    '/',
    '/assets/css/design-system.min.css',
    '/assets/js/main.min.js',
    '/assets/img/favicon.png'
];

// External CDN assets to cache on first use
const CACHEABLE_CDN_HOSTS = [
    'cdn.jsdelivr.net',
    'cdnjs.cloudflare.com',
    'fonts.googleapis.com',
    'fonts.gstatic.com'
];

// =====================================================
// INSTALL — Pre-cache critical assets
// =====================================================
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(function(cache) {
                // Add all precache URLs, don't fail install if any fail
                return Promise.allSettled(
                    PRECACHE_URLS.map(function(url) {
                        return cache.add(url).catch(function() {});
                    })
                );
            })
            .then(function() {
                return self.skipWaiting(); // Activate immediately
            })
    );
});

// =====================================================
// ACTIVATE — Clean old caches + enable Navigation Preload
// =====================================================
self.addEventListener('activate', function(event) {
    var validCaches = [CACHE_NAME, STATIC_CACHE];

    event.waitUntil(
        Promise.all([
            // Delete old caches
            caches.keys().then(function(cacheNames) {
                return Promise.all(
                    cacheNames
                        .filter(function(name) { return validCaches.indexOf(name) === -1; })
                        .map(function(name) { return caches.delete(name); })
                );
            }),
            // Enable Navigation Preload if supported (speeds up page loads)
            self.registration.navigationPreload
                ? self.registration.navigationPreload.enable()
                : Promise.resolve()
        ]).then(function() {
            return self.clients.claim(); // Take control immediately
        })
    );
});

// =====================================================
// FETCH — Smart caching strategy
// =====================================================
self.addEventListener('fetch', function(event) {
    var url = new URL(event.request.url);

    // Only handle GET requests
    if (event.request.method !== 'GET') return;

    // Skip API calls, AJAX endpoints, and admin pages
    if (url.pathname.indexOf('api_') !== -1) return;
    if (url.pathname.indexOf('ajax') !== -1) return;
    if (url.pathname.indexOf('/admin/') !== -1) return;
    if (url.search.indexOf('action=') !== -1) return;

    // === STRATEGY 1: CSS, JS, fonts, images — Cache-first (fastest) ===
    if (isStaticAsset(url.pathname) || isCacheableCDN(url)) {
        event.respondWith(
            caches.match(event.request).then(function(cached) {
                if (cached) {
                    // Serve from cache instantly, but update in background
                    updateCacheInBackground(event.request, STATIC_CACHE);
                    return cached;
                }
                // Not cached yet — fetch, cache, and return
                return fetchAndCache(event.request, STATIC_CACHE);
            })
        );
        return;
    }

    // === STRATEGY 2: HTML pages — Network-first with cache fallback ===
    if (isPageRequest(event.request)) {
        event.respondWith(
            (function() {
                // Use Navigation Preload response if available
                var preloadResponse = event.preloadResponse || Promise.resolve(null);

                return preloadResponse.then(function(preloaded) {
                    if (preloaded) {
                        // Cache the preloaded response
                        cachePage(event.request, preloaded.clone());
                        return preloaded;
                    }
                    // Normal fetch
                    return fetch(event.request).then(function(response) {
                        if (response.ok) {
                            cachePage(event.request, response.clone());
                        }
                        return response;
                    });
                }).catch(function() {
                    // Network failed — try cache
                    return caches.match(event.request).then(function(cached) {
                        return cached || caches.match('/');
                    });
                });
            })()
        );
        return;
    }
});

// =====================================================
// HELPER FUNCTIONS
// =====================================================

function isStaticAsset(pathname) {
    return /\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|eot)(\?|$)/i.test(pathname);
}

function isCacheableCDN(url) {
    return CACHEABLE_CDN_HOSTS.indexOf(url.hostname) !== -1;
}

function isPageRequest(request) {
    var accept = request.headers.get('accept') || '';
    return accept.indexOf('text/html') !== -1 || request.mode === 'navigate';
}

function fetchAndCache(request, cacheName) {
    return fetch(request).then(function(response) {
        if (response.ok && response.type !== 'opaque') {
            var clone = response.clone();
            caches.open(cacheName).then(function(cache) {
                cache.put(request, clone);
            });
        }
        return response;
    }).catch(function() {
        return caches.match(request);
    });
}

function updateCacheInBackground(request, cacheName) {
    fetch(request).then(function(response) {
        if (response.ok) {
            caches.open(cacheName).then(function(cache) {
                cache.put(request, response);
            });
        }
    }).catch(function() {});
}

function cachePage(request, response) {
    caches.open(CACHE_NAME).then(function(cache) {
        cache.put(request, response);
    });
}

// =====================================================
// PUSH — Show notifications sent by WebPushService
// Server payload shape: { title, body, url, icon }
// =====================================================
self.addEventListener('push', function(event) {
    var data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = { body: event.data.text() };
        }
    }

    var title = data.title || 'MyStudy';
    var targetUrl = data.url || '/';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: data.body || '',
            // Server's icon field points to /assets/img/logo.png, which does not exist
            icon: '/assets/img/favicon.png',
            badge: '/assets/img/favicon.png',
            data: { url: targetUrl }
        })
    );
});

// =====================================================
// NOTIFICATION CLICK — Focus an open tab or open the target URL
// =====================================================
self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    var targetUrl = (event.notification.data && event.notification.data.url) || '/';
    var absoluteUrl = new URL(targetUrl, self.location.href).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url === absoluteUrl && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(absoluteUrl);
            }
        })
    );
});
