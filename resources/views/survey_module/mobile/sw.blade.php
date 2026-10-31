{{-- Served as /survey-module/mobile/sw.js (MobileOfflineController::serviceWorker). Plain JS, no markup. --}}
/* SCF Mobile service worker, {{ $version }}. Keeps the field app's pages and assets on the phone. */
'use strict';
var VERSION = @json($version);
var PAGES = VERSION + '-pages', ASSETS = VERSION + '-assets';
var SCOPE = @json($scope);
var OFFLINE = @json(route('survey-module.mobile.offline'));
var LOGIN = @json(route('survey-module.mobile.login'));

// Opened once while online, so they are there without a signal.
// All three field apps share this scope (App\Support\SurveyMobileApps); pages a phone's account cannot open are skipped.
var WARM_PAGES = [OFFLINE, LOGIN, @json(route('survey-module.mobile.index')), @json(route('survey-module.mobile.register')), @json(route('survey-module.mobile.observations')),
                  @json(route('survey-module.mobile.plots.index')), @json(route('survey-module.mobile.plots.ops')), @json(route('survey-module.mobile.plots.checks.create'))];
var WARM_ASSETS = [
    @json(asset('js/survey-mobile-offline.js') . '?v=' . $version),
    @json(asset('assets/logo/SCF.png')),
    'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap',
    'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
];

self.addEventListener('install', function (event) {
    event.waitUntil(Promise.all([
        caches.open(PAGES).then(function (c) {
            return Promise.all(WARM_PAGES.map(function (u) {
                return fetch(u, { credentials: 'same-origin' }).then(function (r) { if (r.ok && !r.redirected) return c.put(u, r); }).catch(function () {});
            }));
        }),
        caches.open(ASSETS).then(function (c) {
            return Promise.all(WARM_ASSETS.map(function (u) {
                var cross = u.indexOf(self.location.origin) !== 0;
                return fetch(u, cross ? { mode: 'no-cors' } : {}).then(function (r) { return c.put(u, r); }).catch(function () {});
            }));
        })
    ]).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (event) {
    event.waitUntil(caches.keys().then(function (keys) {
        return Promise.all(keys.filter(function (k) { return k.indexOf('scf-') === 0 && k.indexOf(VERSION) !== 0; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); }));
});

function isAsset(url) {
    return url.origin !== self.location.origin
        || /\/(js|css|assets|fonts|images|build)\//.test(url.pathname)
        || /\.(js|css|png|jpe?g|svg|webp|woff2?|ttf)$/i.test(url.pathname);
}

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') return;                       // saves go through the page's own queue
    var url = new URL(req.url);

    // Pages: the network first; without a signal, the copy from the last visit, else the offline page.
    if (req.mode === 'navigate') {
        if (url.href.indexOf(SCOPE) !== 0) return;
        event.respondWith(fetch(req).then(function (r) {
            // A redirect to the login page is not a page to keep.
            if (r.ok && !r.redirected) { var copy = r.clone(); caches.open(PAGES).then(function (c) { c.put(req, copy); }); }
            return r;
        }).catch(function () {
            return caches.match(req).then(function (hit) { return hit || caches.match(OFFLINE); });
        }));
        return;
    }

    // Survey lookups (district / street / LGA pickers) and photos: network first, then the last answer.
    if (url.origin === self.location.origin && /^\/survey-module\/(lookup\/|mobile\/cases\/\d+\/photos\/|mobile\/plots\/(showing|check)-photos\/)/.test(url.pathname)) {
        event.respondWith(fetch(req).then(function (r) {
            if (r.ok) { var copy = r.clone(); caches.open(PAGES).then(function (c) { c.put(req, copy); }); }
            return r;
        }).catch(function () { return caches.match(req); }));
        return;
    }

    // Fonts, icons, maps, scripts and images: from the phone when there, else fetched and kept.
    if (isAsset(url)) {
        event.respondWith(caches.match(req).then(function (hit) {
            return hit || fetch(req).then(function (r) {
                if (r.ok || r.type === 'opaque') { var copy = r.clone(); caches.open(ASSETS).then(function (c) { c.put(req, copy); }); }
                return r;
            });
        }));
    }
});
