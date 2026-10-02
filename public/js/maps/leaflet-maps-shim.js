/**
 * Leaflet-backed replacement for the small slice of the Google Maps JS API
 * that KLAES actually uses.
 *
 * WHY: the Google Cloud billing account was suspended, so maps.googleapis.com
 * rejects every request. Rather than hand-port six near-identical call sites,
 * this implements the exact API surface they call — google.maps.Map / Marker /
 * Geocoder / Size / Point / event — on top of Leaflet + free tile sources that
 * need no API key and no billing account.
 *
 * Call sites therefore keep working unchanged. To go back to real Google Maps,
 * swap the <script> tags back to maps.googleapis.com and delete this file; no
 * other code has to change.
 *
 * Deliberate differences from Google:
 *   - Street View does not exist in Leaflet, so `streetViewControl` is ignored.
 *   - Geocoding uses OpenStreetMap's Nominatim, which is far weaker than Google
 *     on Kano street addresses. Treat it as best-effort: the reliable way to set
 *     a location is to click the satellite image or drag the pin.
 *   - Nominatim's usage policy caps callers at ~1 request/second, so geocode
 *     requests are queued and spaced out here.
 *
 * Load order (both required, before any call site):
 *   <link  href=".../leaflet.css">
 *   <script src=".../leaflet.js"></script>
 *   <script src="{{ asset('js/maps/leaflet-maps-shim.js') }}"></script>
 */
(function () {
    'use strict';

    // If the genuine Google Maps API is present, leave it alone.
    if (window.google && window.google.maps && !window.google.maps.__klaesLeafletShim) {
        return;
    }

    if (typeof L === 'undefined') {
        console.error('[klaes-maps] Leaflet failed to load — maps will not render.');
        return;
    }

    /* ------------------------------------------------------------------ *
     * Tile sources (no API key, no billing)
     * ------------------------------------------------------------------ */

    var TILES = {
        // Esri World Imagery — the satellite view land officers rely on.
        satellite: {
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            options: {
                maxZoom: 21,
                maxNativeZoom: 19,
                attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics'
            }
        },
        // Place/road labels drawn over the imagery, so satellite reads like
        // Google's "Hybrid" rather than bare pixels.
        labels: {
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
            options: { maxZoom: 21, maxNativeZoom: 19 }
        },
        street: {
            url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            options: {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }
        }
    };

    /* ------------------------------------------------------------------ *
     * Coordinate helpers — Google hands callbacks a LatLng with lat()/lng()
     * accessor methods, and accepts either that or a {lat, lng} literal.
     * ------------------------------------------------------------------ */

    function LatLng(lat, lng) {
        this._lat = parseFloat(lat);
        this._lng = parseFloat(lng);
    }
    LatLng.prototype.lat = function () { return this._lat; };
    LatLng.prototype.lng = function () { return this._lng; };
    LatLng.prototype.toJSON = function () { return { lat: this._lat, lng: this._lng }; };
    LatLng.prototype.toString = function () { return '(' + this._lat + ', ' + this._lng + ')'; };

    /** Accept a LatLng, a {lat, lng} literal, or an [lat, lng] pair. */
    function toLatLngArray(pos) {
        if (!pos) return null;
        if (Array.isArray(pos)) return [parseFloat(pos[0]), parseFloat(pos[1])];
        var lat = typeof pos.lat === 'function' ? pos.lat() : pos.lat;
        var lng = typeof pos.lng === 'function' ? pos.lng() : pos.lng;
        lat = parseFloat(lat);
        lng = parseFloat(lng);
        if (isNaN(lat) || isNaN(lng)) return null;
        return [lat, lng];
    }

    /** Wrap a Leaflet event so callers can keep using `e.latLng.lat()`. */
    function googleEvent(latlng) {
        return { latLng: new LatLng(latlng.lat, latlng.lng) };
    }

    /* ------------------------------------------------------------------ *
     * google.maps.Map
     * ------------------------------------------------------------------ */

    function Map(element, opts) {
        opts = opts || {};

        var center = toLatLngArray(opts.center) || [12.0022, 8.5920]; // Kano fallback
        var zoom = opts.zoom || 17;

        this._el = element;
        this._leaflet = L.map(element, {
            center: center,
            zoom: zoom,
            zoomControl: opts.zoomControl !== false,
            attributionControl: true
        });

        // Base layers, with a control so users can flip satellite <-> street
        // (the closest equivalent to Google's map-type buttons).
        var satellite = L.layerGroup([
            L.tileLayer(TILES.satellite.url, TILES.satellite.options),
            L.tileLayer(TILES.labels.url, TILES.labels.options)
        ]);
        var street = L.tileLayer(TILES.street.url, TILES.street.options);

        var wantsStreet = opts.mapTypeId && String(opts.mapTypeId).toLowerCase() === 'roadmap';
        (wantsStreet ? street : satellite).addTo(this._leaflet);

        L.control.layers(
            { 'Satellite': satellite, 'Street map': street },
            {},
            { position: 'topright' }
        ).addTo(this._leaflet);

        if (opts.fullscreenControl !== false) {
            addFullscreenControl(this._leaflet, element);
        }

        // Google renders correctly after its container becomes visible; Leaflet
        // needs an explicit nudge, so watch for the container gaining a size
        // (tab switches, modals opening) and invalidate then.
        observeResize(this);
    }

    Map.prototype.setCenter = function (pos) {
        var ll = toLatLngArray(pos);
        if (ll) this._leaflet.setView(ll, this._leaflet.getZoom());
    };

    Map.prototype.panTo = function (pos) {
        var ll = toLatLngArray(pos);
        if (ll) this._leaflet.panTo(ll);
    };

    Map.prototype.getCenter = function () {
        var c = this._leaflet.getCenter();
        return new LatLng(c.lat, c.lng);
    };

    Map.prototype.setZoom = function (z) { this._leaflet.setZoom(z); };
    Map.prototype.getZoom = function () { return this._leaflet.getZoom(); };

    Map.prototype.addListener = function (eventName, handler) {
        var self = this;
        if (eventName === 'click') {
            this._leaflet.on('click', function (e) { handler(googleEvent(e.latlng)); });
        } else if (eventName === 'resize') {
            this._leaflet.on('resize', function () { handler({}); });
        } else {
            this._leaflet.on(eventName, function () { handler({}); });
        }
        return { remove: function () { self._leaflet.off(eventName); } };
    };

    /** Google's `event.trigger(map, 'resize')` maps onto invalidateSize(). */
    Map.prototype.__invalidate = function () {
        var self = this;
        // Defer a frame so it runs after the container's layout settles.
        setTimeout(function () { self._leaflet.invalidateSize(); }, 0);
    };

    /**
     * Re-measure whenever the container changes size — covers the "map renders
     * grey because it was display:none" case the call sites work around.
     */
    function observeResize(mapWrapper) {
        if (typeof ResizeObserver === 'undefined') return;
        var last = 0;
        var observer = new ResizeObserver(function (entries) {
            var box = entries[0] && entries[0].contentRect;
            if (!box || box.width === 0) return;
            if (box.width === last) return;
            last = box.width;
            mapWrapper._leaflet.invalidateSize();
        });
        observer.observe(mapWrapper._el);
    }

    /** Minimal stand-in for Google's fullscreen button. */
    function addFullscreenControl(leafletMap, element) {
        var Control = L.Control.extend({
            options: { position: 'topright' },
            onAdd: function () {
                var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                var link = L.DomUtil.create('a', '', container);
                link.href = '#';
                link.title = 'Toggle fullscreen';
                link.innerHTML = '&#9974;';
                link.style.fontSize = '16px';
                link.style.lineHeight = '26px';
                link.style.textAlign = 'center';

                L.DomEvent.on(link, 'click', function (e) {
                    L.DomEvent.stop(e);
                    if (document.fullscreenElement) {
                        document.exitFullscreen();
                    } else if (element.requestFullscreen) {
                        element.requestFullscreen();
                    }
                    setTimeout(function () { leafletMap.invalidateSize(); }, 200);
                });
                return container;
            }
        });
        leafletMap.addControl(new Control());
    }

    /* ------------------------------------------------------------------ *
     * google.maps.Marker
     * ------------------------------------------------------------------ */

    function Marker(opts) {
        opts = opts || {};
        this._handlers = {};

        var pos = toLatLngArray(opts.position) || [0, 0];

        this._leaflet = L.marker(pos, {
            draggable: !!opts.draggable,
            title: opts.title || '',
            icon: buildIcon(opts.icon),
            keyboard: false
        });

        // Google fires 'dragend' with the final position; Leaflet's carries the
        // marker, so unwrap it into the shape the call sites expect.
        var self = this;
        this._leaflet.on('dragend', function () {
            var ll = self._leaflet.getLatLng();
            (self._handlers.dragend || []).forEach(function (fn) { fn(googleEvent(ll)); });
        });
        this._leaflet.on('drag', function () {
            var ll = self._leaflet.getLatLng();
            (self._handlers.drag || []).forEach(function (fn) { fn(googleEvent(ll)); });
        });
        this._leaflet.on('click', function () {
            var ll = self._leaflet.getLatLng();
            (self._handlers.click || []).forEach(function (fn) { fn(googleEvent(ll)); });
        });

        if (opts.map) this.setMap(opts.map);
    }

    /**
     * Translate a Google icon descriptor — {url, scaledSize, anchor} — into a
     * Leaflet icon. The call sites all pass an inline SVG data URI.
     */
    function buildIcon(icon) {
        if (!icon || !icon.url) {
            return L.icon({
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41]
            });
        }
        var size = icon.scaledSize || icon.size || { width: 42, height: 50 };
        var anchor = icon.anchor || { x: size.width / 2, y: size.height };
        return L.icon({
            iconUrl: icon.url,
            iconSize: [size.width, size.height],
            iconAnchor: [anchor.x, anchor.y]
        });
    }

    Marker.prototype.setPosition = function (pos) {
        var ll = toLatLngArray(pos);
        if (ll) this._leaflet.setLatLng(ll);
    };

    Marker.prototype.getPosition = function () {
        var ll = this._leaflet.getLatLng();
        return new LatLng(ll.lat, ll.lng);
    };

    /** `setMap(map)` attaches, `setMap(null)` detaches — same as Google. */
    Marker.prototype.setMap = function (map) {
        if (map && map._leaflet) {
            this._map = map;
            this._leaflet.addTo(map._leaflet);
        } else if (this._map && this._map._leaflet) {
            this._map._leaflet.removeLayer(this._leaflet);
            this._map = null;
        }
    };

    Marker.prototype.setDraggable = function (flag) {
        if (flag) this._leaflet.dragging.enable();
        else this._leaflet.dragging.disable();
    };

    Marker.prototype.setTitle = function (title) {
        if (this._leaflet.getElement()) this._leaflet.getElement().title = title;
    };

    Marker.prototype.addListener = function (eventName, handler) {
        this._handlers[eventName] = this._handlers[eventName] || [];
        this._handlers[eventName].push(handler);
        return { remove: function () {} };
    };

    /* ------------------------------------------------------------------ *
     * google.maps.Geocoder  ->  OpenStreetMap Nominatim
     * ------------------------------------------------------------------ */

    // Nominatim's policy allows at most ~1 request/second from a client, so
    // serialise requests through a queue rather than firing them in parallel.
    var geocodeQueue = [];
    var geocodeBusy = false;
    var MIN_GAP_MS = 1200;

    // Nominatim ranks an LGA's administrative boundary above the town inside it,
    // and a boundary's point is the polygon centroid - for "Albasu, Kano, Nigeria"
    // that is empty bush ~11km from Albasu town. Every caller here is pinning a
    // property, so prefer the most specific settlement (class "place": town,
    // village, suburb...) over the boundary, and only fall back to Nominatim's own
    // first result when no settlement came back.
    function pickBestPlace(results) {
        var best = null;
        for (var i = 0; i < results.length; i++) {
            var r = results[i];
            if (r['class'] !== 'place') continue;
            var rank = Number(r.place_rank);
            if (!best || (isFinite(rank) && rank > Number(best.place_rank))) best = r;
        }
        return best || results[0];
    }

    function pumpGeocodeQueue() {
        if (geocodeBusy || !geocodeQueue.length) return;
        geocodeBusy = true;

        var job = geocodeQueue.shift();
        var url = 'https://nominatim.openstreetmap.org/search'
                + '?format=json&limit=5&countrycodes=ng&addressdetails=0'
                + '&q=' + encodeURIComponent(job.address);

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (results) {
                if (!results || !results.length) {
                    job.callback([], 'ZERO_RESULTS');
                    return;
                }
                var hit = pickBestPlace(results);
                job.callback([{
                    geometry: { location: new LatLng(hit.lat, hit.lon) },
                    formatted_address: hit.display_name || job.address
                }], 'OK');
            })
            .catch(function (err) {
                console.error('[klaes-maps] geocode failed', err);
                job.callback([], 'ERROR');
            })
            .finally(function () {
                setTimeout(function () {
                    geocodeBusy = false;
                    pumpGeocodeQueue();
                }, MIN_GAP_MS);
            });
    }

    function Geocoder() {}
    Geocoder.prototype.geocode = function (request, callback) {
        var address = (request && request.address) || '';
        if (!address.trim()) {
            callback([], 'ZERO_RESULTS');
            return;
        }
        geocodeQueue.push({ address: address, callback: callback });
        pumpGeocodeQueue();
    };

    /* ------------------------------------------------------------------ *
     * Value types + the event namespace
     * ------------------------------------------------------------------ */

    function Size(width, height) {
        this.width = width;
        this.height = height;
    }

    function Point(x, y) {
        this.x = x;
        this.y = y;
    }

    var event = {
        /** Only 'resize' is ever triggered by the call sites. */
        trigger: function (target, eventName) {
            if (eventName === 'resize' && target && typeof target.__invalidate === 'function') {
                target.__invalidate();
            }
        },
        addListener: function (target, eventName, handler) {
            if (target && typeof target.addListener === 'function') {
                return target.addListener(eventName, handler);
            }
            return { remove: function () {} };
        },
        clearListeners: function () {}
    };

    /* ------------------------------------------------------------------ */

    window.google = window.google || {};
    window.google.maps = {
        __klaesLeafletShim: true,
        Map: Map,
        Marker: Marker,
        Geocoder: Geocoder,
        LatLng: LatLng,
        Size: Size,
        Point: Point,
        Animation: { DROP: 'drop', BOUNCE: 'bounce' },
        MapTypeId: { SATELLITE: 'satellite', ROADMAP: 'roadmap', HYBRID: 'hybrid', TERRAIN: 'terrain' },
        event: event
    };
})();
