// Ported from ALAES public/js/sdi/map-request-basemaps.js (2026-10-29): street / satellite / terrain previews for SDI map requests.
(function (global) {
    'use strict';

    global.SdiMapRequestBasemaps = function (map) {
        // Drop the "Leaflet |" prefix (allowed by Leaflet's licence). The tile credits stay:
        // Esri World Imagery and OpenStreetMap / OpenTopoMap require visible attribution.
        if (map.attributionControl) {
            map.attributionControl.setPrefix(false);
        }

        var layers = {
            streets: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }),
            satellite: L.tileLayer(
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                { maxZoom: 19, attribution: 'Imagery &copy; Esri' }
            ),
            terrain: L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                maxNativeZoom: 17,
                maxZoom: 19,
                attribution: '&copy; OpenTopoMap, OpenStreetMap contributors'
            })
        };
        var activeLayer = null;

        return {
            show: function (name) {
                var layer = layers[name] || layers.streets;
                if (layer !== activeLayer) {
                    if (activeLayer) {
                        map.removeLayer(activeLayer);
                    }
                    layer.addTo(map);
                    layer.bringToBack();
                    activeLayer = layer;
                }
                return layer === layers.satellite ? 'Satellite imagery' :
                    layer === layers.terrain ? 'Terrain map' : 'Street map';
            }
        };
    };
})(window);
