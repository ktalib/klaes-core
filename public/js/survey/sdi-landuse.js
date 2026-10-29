// SDI Land Use overlay (2026-10-29): indexed files from file_indexings, grouped per point,
// as bubbles sized by file count and coloured by the dominant land use. The coordinates
// mark the locality (district), not the plot, so this is a density/mix view, never parcels.
// Colours: dataviz default categorical slots 1-3 (validated all-pairs); the rest fold to grey.
(function (global) {
    'use strict';

    var COLOURS = { Residential: '#2a78d6', Commercial: '#eb6834', Agriculture: '#1baf7a' };
    var OTHER = '#8a8a85';

    function colourOf(use) { return COLOURS[use] || OTHER; }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function nf(n) { return Number(n).toLocaleString(); }

    // Ray casting on the exterior ring(s); coordinates are GeoJSON [lng, lat].
    function inRing(lng, lat, ring) {
        var inside = false;
        for (var i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            var xi = ring[i][0], yi = ring[i][1], xj = ring[j][0], yj = ring[j][1];
            if (((yi > lat) !== (yj > lat)) && (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi)) inside = !inside;
        }
        return inside;
    }
    function inFeature(lng, lat, feature) {
        var g = feature && (feature.geometry || feature);
        if (!g) return false;
        var polys = g.type === 'Polygon' ? [g.coordinates] : (g.type === 'MultiPolygon' ? g.coordinates : []);
        for (var p = 0; p < polys.length; p++) if (polys[p] && polys[p][0] && inRing(lng, lat, polys[p][0])) return true;
        return false;
    }

    function breakdown(order, uses) {
        return order.filter(function (u) { return uses[u]; }).map(function (u) {
            return '<tr><td><span style="display:inline-block;width:9px;height:9px;border-radius:2px;margin-right:6px;background:' + colourOf(u) + '"></span>' +
                esc(u) + '</td><td style="text-align:right;padding-left:12px;">' + nf(uses[u]) + '</td></tr>';
        }).join('');
    }

    /**
     * SdiLandUse(map, url, { summaryEl }) -> { show(on), summarize(feature) }
     * Data is fetched once, on first show.
     */
    global.SdiLandUse = function (map, url, opts) {
        opts = opts || {};
        var renderer = L.canvas({ padding: 0.3 });
        var layer = L.layerGroup();
        var legend = null, data = null, loading = null, visible = false, lastFeature = null;

        function load() {
            if (data) return Promise.resolve(data);
            if (loading) return loading;
            loading = fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(function (d) { data = d; draw(); return d; })
                .catch(function (e) { loading = null; if (opts.summaryEl) opts.summaryEl.innerHTML = '<p class="helper-text">Land use could not be loaded (' + esc(e.message) + ').</p>'; throw e; });
            return loading;
        }

        function draw() {
            layer.clearLayers();
            // Biggest first, so small bubbles stay on top and clickable.
            data.points.slice().sort(function (a, b) { return b[2] - a[2]; }).forEach(function (p) {
                var dominant = null, best = -1;
                Object.keys(p[3]).forEach(function (u) { if (p[3][u] > best) { best = p[3][u]; dominant = u; } });
                var m = L.circleMarker([p[0], p[1]], {
                    renderer: renderer,
                    radius: Math.min(26, 4 + Math.sqrt(p[2]) * 0.4),
                    color: '#ffffff', weight: 1.5, fillColor: colourOf(dominant), fillOpacity: 0.72
                });
                m.bindTooltip(
                    '<div style="font-size:12px;"><strong>' + esc(p[4] || 'Unnamed locality') + '</strong><br>' +
                    nf(p[2]) + ' indexed file' + (p[2] === 1 ? '' : 's') +
                    '<table style="margin-top:4px;">' + breakdown(data.order, p[3]) + '</table>' +
                    '<div style="color:#6b7280;margin-top:3px;">Locality point, not the plot position.</div></div>',
                    { sticky: true, direction: 'top', opacity: 0.97 }
                );
                layer.addLayer(m);
            });
        }

        function addLegend() {
            if (legend) return;
            legend = L.control({ position: 'bottomright' });
            legend.onAdd = function () {
                var d = L.DomUtil.create('div');
                d.style.cssText = 'background:#fff;padding:8px 10px;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.25);font-size:12px;line-height:1.6;color:#374151;';
                d.innerHTML = '<strong>Indexed land use</strong><br>' +
                    ['Residential', 'Commercial', 'Agriculture'].map(function (u) {
                        return '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px;background:' + COLOURS[u] + '"></span>' + u;
                    }).join('<br>') +
                    '<br><span style="display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px;background:' + OTHER + '"></span>Other (Industrial, Government, …)' +
                    '<div style="color:#6b7280;margin-top:2px;">Colour = main use · size = no. of files</div>';
                L.DomEvent.disableClickPropagation(d);
                return d;
            };
            legend.addTo(map);
        }

        function summarize(feature) {
            lastFeature = feature || null;
            var el = opts.summaryEl;
            if (!el || !visible) return;
            if (!data) { el.innerHTML = '<p class="helper-text">Loading indexed land use…</p>'; return; }
            if (!feature) {
                el.innerHTML = '<p class="helper-text">' + nf(data.files) + ' indexed files with coordinates are shown. Draw the area to count the land use inside it.</p>';
                return;
            }
            var uses = {}, files = 0, pts = 0;
            data.points.forEach(function (p) {
                if (!inFeature(p[1], p[0], feature)) return;
                pts++; files += p[2];
                Object.keys(p[3]).forEach(function (u) { uses[u] = (uses[u] || 0) + p[3][u]; });
            });
            if (!files) {
                el.innerHTML = '<p class="helper-text">No indexed files fall inside the drawn area.</p>';
                return;
            }
            var rows = data.order.filter(function (u) { return uses[u]; }).map(function (u) {
                return '<tr><td><span style="display:inline-block;width:9px;height:9px;border-radius:2px;margin-right:6px;background:' + colourOf(u) + '"></span>' + esc(u) +
                    '</td><td style="text-align:right;">' + nf(uses[u]) + '</td><td style="text-align:right;color:#6b7280;">' + (uses[u] * 100 / files).toFixed(1) + '%</td></tr>';
            }).join('');
            el.innerHTML = '<div style="font-size:13px;font-weight:600;margin-bottom:4px;">Indexed land use in this area</div>' +
                '<table data-column-picker="off" style="width:100%;font-size:13px;border-collapse:collapse;"><thead><tr style="color:#6b7280;text-align:left;"><th style="font-weight:500;">Land use</th><th style="font-weight:500;text-align:right;">Files</th><th style="font-weight:500;text-align:right;">Share</th></tr></thead><tbody>' +
                rows + '<tr style="border-top:1px solid #e5e7eb;font-weight:600;"><td>Total</td><td style="text-align:right;">' + nf(files) + '</td><td></td></tr></tbody></table>' +
                '<p class="helper-text" style="margin-top:4px;">From ' + nf(pts) + ' locality point' + (pts === 1 ? '' : 's') + ' in File Indexing. Locations are per locality, so edge areas are approximate.</p>';
        }

        return {
            show: function (on) {
                visible = !!on;
                if (opts.summaryEl) opts.summaryEl.style.display = visible ? '' : 'none';
                if (!visible) {
                    map.removeLayer(layer);
                    if (legend) { map.removeControl(legend); legend = null; }
                    return;
                }
                layer.addTo(map);
                addLegend();
                summarize(lastFeature);
                load().then(function () { summarize(lastFeature); }, function () {});
            },
            summarize: summarize
        };
    };
})(window);
