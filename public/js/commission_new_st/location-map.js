/**
 * Location Details + Google Map component for the ST commissioning tabs.
 *
 * One instance per tab, keyed by an element-id prefix ('pua', 'sua', ...), so
 * several copies of the markup can live in the same page without clashing.
 * Markup: resources/views/commission_new_st/partials/location-details-map.blade.php
 *
 *   STLocationMaps['pua'].backfill({district, lga, street_name, plot_number,
 *                                   latitude, longitude})  // fill from parent file
 *   STLocationMaps['pua'].clear()                          // back to empty state
 *   STLocationMaps['pua'].getPayload()                     // fields for the commission POST
 */
window.STLocationMaps = window.STLocationMaps || {};

// Where the map opens when an address cannot be resolved and the officer has
// to find the plot on the satellite imagery.
var KANO_CENTER = { lat: 12.0022, lng: 8.5920 };

// Every file handled here is a Kano State registry file, so the State field
// starts on Kano (and its LGA list preloaded) instead of blank.
var DEFAULT_STATE = 'Kano';

function initLocationDetailsMap(config) {
    var prefix = config.prefix;
    var manual = !!config.manual;

    // Pages that already have their own address inputs (e.g. the Primary
    // Application form) pass `fieldIds` to point a suffix at an existing id
    // instead of the prefixed one this component would otherwise create.
    var fieldIds = config.fieldIds || {};

    function el(suffix) {
        return document.getElementById(fieldIds[suffix] || (prefix + suffix));
    }

    function roundCoord(v) {
        return Math.round(parseFloat(v) * 1e7) / 1e7;
    }

    function markerIcon() {
        var FILL = '#2563eb';
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="42" height="50" viewBox="0 0 42 50">' +
                  '<path d="M21 0C10 0 1 9 1 20c0 14 20 30 20 30s20-16 20-30C41 9 32 0 21 0z" fill="' + FILL + '" stroke="#ffffff" stroke-width="2"/>' +
                  '<circle cx="21" cy="20" r="13" fill="#ffffff"/>' +
                  '<path d="M21 12l-8 7h2.4v8.4h11.2V19H29z" fill="' + FILL + '"/></svg>';
        return {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            scaledSize: new google.maps.Size(42, 50),
            anchor: new google.maps.Point(21, 50),
            labelOrigin: new google.maps.Point(21, 20)
        };
    }

    var instance = {
        prefix: prefix,
        manual: manual,
        map: null,
        marker: null,

        /* ---------------- address fields ---------------- */

        updateAddress: function () {
            var parts = [
                el('PropertyHouseNo') ? el('PropertyHouseNo').value : '',
                el('PropertyStreetName') ? el('PropertyStreetName').value : '',
                el('PropertyDistrict') ? el('PropertyDistrict').value : '',
                el('PropertyLga') ? el('PropertyLga').value : '',
                el('PropertyState') ? el('PropertyState').value : ''
            ].filter(function (p) { return p && p.trim() !== ''; });

            var full = parts.join(', ');
            if (el('PropertyAddressDisplay')) el('PropertyAddressDisplay').value = full;
            if (el('FullPropertyAddress')) {
                el('FullPropertyAddress').textContent = full || 'Enter property details above';
            }
        },

        /**
         * Populate the State dropdown from the shared Nigerian states dataset
         * (loaded asynchronously by js/primaryform/states-lga.js).
         *
         * The ST partial already renders the full list server-side, so this is a
         * no-op there; it still runs for hosts that render only the placeholder.
         */
        populateStates: function (attemptsLeft) {
            attemptsLeft = typeof attemptsLeft === 'number' ? attemptsLeft : 20;
            var stateSelect = el('PropertyState');
            if (!stateSelect) return;
            if (stateSelect.options.length > 1) return;

            if (typeof nigerianStatesData === 'undefined' || !nigerianStatesData.length) {
                if (attemptsLeft > 0) {
                    var self = this;
                    setTimeout(function () { self.populateStates(attemptsLeft - 1); }, 300);
                }
                return;
            }

            var current = stateSelect.value;
            while (stateSelect.children.length > 1) {
                stateSelect.removeChild(stateSelect.lastChild);
            }
            nigerianStatesData.forEach(function (state) {
                var option = document.createElement('option');
                option.value = state.name;
                option.textContent = state.name;
                stateSelect.appendChild(option);
            });
            stateSelect.value = current || DEFAULT_STATE;
            this.populateLgas();
        },

        /** Refill the LGA dropdown for the selected state. */
        populateLgas: function (selectedLga, attemptsLeft) {
            var stateSelect = el('PropertyState');
            var lgaSelect = el('PropertyLga');
            if (!stateSelect || !lgaSelect) return;

            // The reference data loads asynchronously — bail out before wiping the
            // server-rendered list, otherwise the LGA field empties itself.
            if (typeof nigerianStatesData === 'undefined' || !nigerianStatesData.length) {
                attemptsLeft = typeof attemptsLeft === 'number' ? attemptsLeft : 20;
                if (attemptsLeft > 0) {
                    var self = this;
                    setTimeout(function () { self.populateLgas(selectedLga, attemptsLeft - 1); }, 300);
                }
                return;
            }

            var state = nigerianStatesData.find(function (s) { return s.name === stateSelect.value; });

            lgaSelect.innerHTML = '<option value="">Select LGA</option>';
            if (!state || !state.local_governments) return;

            state.local_governments.forEach(function (lga) {
                var option = document.createElement('option');
                option.value = lga;
                option.textContent = lga;
                lgaSelect.appendChild(option);
            });

            if (selectedLga) {
                var match = Array.from(lgaSelect.options).find(function (opt) {
                    return opt.value.toLowerCase() === String(selectedLga).toLowerCase();
                });
                if (match) lgaSelect.value = match.value;
            }
        },

        onStateChange: function () {
            this.populateLgas();
            this.updateAddress();
        },

        /**
         * Resolve the parent state of an LGA by reverse-lookup, then select
         * both. The indexing record stores an LGA but no state.
         */
        applyLga: function (lgaName, attemptsLeft) {
            if (!lgaName) return;
            attemptsLeft = typeof attemptsLeft === 'number' ? attemptsLeft : 10;

            if (typeof nigerianStatesData === 'undefined' || !nigerianStatesData.length) {
                if (attemptsLeft > 0) {
                    var self = this;
                    setTimeout(function () { self.applyLga(lgaName, attemptsLeft - 1); }, 300);
                }
                return;
            }

            var matchedState = nigerianStatesData.find(function (state) {
                return (state.local_governments || []).some(function (lga) {
                    return lga.toLowerCase() === String(lgaName).toLowerCase();
                });
            });
            if (!matchedState) return;

            var stateSelect = el('PropertyState');
            if (!stateSelect) return;

            this.populateStates(0);
            stateSelect.value = matchedState.name;
            this.populateLgas(lgaName);
            this.updateAddress();
        },

        /* ---------------- read-only / edit toggle ---------------- */

        toggleEdit: function () {
            var fields = document.querySelectorAll('.' + prefix + '-location-field');
            var willEnable = fields.length ? fields[0].disabled : true;

            fields.forEach(function (field) {
                field.disabled = !willEnable;
                field.classList.toggle('bg-gray-100', !willEnable);
                field.classList.toggle('text-gray-500', !willEnable);
                field.classList.toggle('cursor-not-allowed', !willEnable);
                field.classList.toggle('bg-white', willEnable);
                field.classList.toggle('text-gray-900', willEnable);
                field.classList.toggle('cursor-text', willEnable);

                // Keep the Select2 widget in step with the underlying select
                if (field.tagName === 'SELECT' && typeof setReferenceSelectDisabled === 'function') {
                    setReferenceSelectDisabled(field, !willEnable);
                }
            });

            var btn = el('LocationDetailsEditBtn');
            if (btn) {
                btn.innerHTML = willEnable
                    ? '<i data-lucide="lock" class="w-4 h-4 mr-1"></i>Done'
                    : '<i data-lucide="pencil" class="w-4 h-4 mr-1"></i>Edit';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            var hint = el('LocationDetailsHint');
            if (hint) {
                hint.textContent = willEnable
                    ? 'Editing enabled - values will be used as entered'
                    : 'Backfilled automatically from the selected file number (read-only)';
            }
        },

        /* ---------------- map ---------------- */

        setPin: function (lat, lng, recenter, sourceLabel) {
            lat = roundCoord(lat);
            lng = roundCoord(lng);

            if (el('PropertyLatitude')) el('PropertyLatitude').value = lat;
            if (el('PropertyLongitude')) el('PropertyLongitude').value = lng;
            if (el('PropertyLatDisplay')) el('PropertyLatDisplay').textContent = lat;
            if (el('PropertyLngDisplay')) el('PropertyLngDisplay').textContent = lng;

            if (typeof sourceLabel === 'string' && el('PropertyMapCoordSource')) {
                el('PropertyMapCoordSource').textContent = sourceLabel ? '(' + sourceLabel + ')' : '';
            }

            if (el('PropertyMapWrapper')) el('PropertyMapWrapper').classList.remove('hidden');
            if (el('PropertyMapEmpty')) el('PropertyMapEmpty').classList.add('hidden');
            var hint = el('PropertyMapDragHint');
            if (hint) { hint.classList.remove('hidden'); hint.classList.add('inline-flex'); }

            this.render(lat, lng, recenter);
        },

        /**
         * Create (or recentre) the map itself. Split out from render() so the
         * map can also be opened with no marker for manual pinning.
         * Returns false while the map library or its container is not ready.
         */
        ensureMap: function (lat, lng, zoom, recenter) {
            var self = this;

            var mapEl = el('PropertyMapCanvas');
            if (!mapEl) return false;

            var pos = { lat: lat, lng: lng };

            if (!this.map) {
                this.map = new google.maps.Map(mapEl, {
                    center: pos,
                    zoom: zoom || 17,
                    mapTypeId: 'satellite',
                    streetViewControl: true,
                    fullscreenControl: true
                });
                // Click anywhere on the map to move the pin there.
                this.map.addListener('click', function (e) {
                    self.setPin(e.latLng.lat(), e.latLng.lng(), false, 'Adjusted manually');
                });
            } else {
                if (recenter) this.map.panTo(pos);
                // Container may have been display:none while the tab was hidden.
                google.maps.event.trigger(this.map, 'resize');
                this.map.setCenter(pos);
            }
            return true;
        },

        /**
         * Open the map with no pin, centred on Kano, so the officer can click
         * the property directly. Used when address lookup finds nothing.
         */
        openManualPin: function () {
            var self = this;

            if (typeof google === 'undefined' || !google.maps) {
                setTimeout(function () { self.openManualPin(); }, 400);
                return;
            }

            if (el('PropertyMapWrapper')) el('PropertyMapWrapper').classList.remove('hidden');
            if (el('PropertyMapEmpty')) el('PropertyMapEmpty').classList.add('hidden');
            if (el('PropertyMapCoordSource')) {
                el('PropertyMapCoordSource').textContent = '(click the property on the map to pin it)';
            }
            var hint = el('PropertyMapDragHint');
            if (hint) { hint.classList.remove('hidden'); hint.classList.add('inline-flex'); }

            this.ensureMap(KANO_CENTER.lat, KANO_CENTER.lng, 13, true);
        },

        render: function (lat, lng, recenter) {
            var self = this;

            if (typeof google === 'undefined' || !google.maps) {
                // Map library still loading — retry shortly.
                setTimeout(function () { self.render(lat, lng, recenter); }, 400);
                return;
            }

            if (!this.ensureMap(lat, lng, 17, recenter)) return;

            var pos = { lat: lat, lng: lng };

            if (!this.marker) {
                this.marker = new google.maps.Marker({
                    position: pos,
                    map: this.map,
                    draggable: true,
                    title: 'Drag to adjust the exact property location',
                    animation: google.maps.Animation.DROP,
                    icon: markerIcon()
                });
                this.marker.addListener('dragend', function (e) {
                    self.setPin(e.latLng.lat(), e.latLng.lng(), false, 'Adjusted manually');
                });
            } else {
                this.marker.setPosition(pos);
                this.marker.setMap(this.map);
            }
        },

        /**
         * Nudge the map after its tab becomes visible again — Google Maps
         * renders grey if the container was display:none while it laid out.
         */
        refresh: function () {
            if (!this.map || typeof google === 'undefined' || !google.maps) return;
            var center = this.map.getCenter();
            google.maps.event.trigger(this.map, 'resize');
            if (center) this.map.setCenter(center);
        },

        /** Geocode the typed address and drop a pin on the result. */
        geocode: function () {
            var self = this;
            var plot = el('PropertyPlotNo') && el('PropertyPlotNo').value ? 'Plot ' + el('PropertyPlotNo').value : '';
            var parts = [
                el('PropertyHouseNo') ? el('PropertyHouseNo').value : '',
                plot,
                el('PropertyStreetName') ? el('PropertyStreetName').value : '',
                el('PropertyDistrict') ? el('PropertyDistrict').value : '',
                el('PropertyLga') ? el('PropertyLga').value : '',
                el('PropertyState') ? el('PropertyState').value : '',
                'Nigeria'
            ].filter(function (p) { return p && p.trim() !== ''; });

            if (parts.length <= 1) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No address yet',
                        text: 'Fill in the Location Details above before pinning on the map.'
                    });
                }
                return;
            }

            if (typeof google === 'undefined' || !google.maps) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Map not ready', text: 'The map is still loading. Please try again in a moment.' });
                }
                return;
            }

            var address = parts.join(', ');
            new google.maps.Geocoder().geocode({ address: address, region: 'NG' }, function (results, status) {
                if (status === 'OK' && results[0]) {
                    var pos = results[0].geometry.location;
                    self.setPin(pos.lat(), pos.lng(), true, 'Geocoded from address');
                    return;
                }

                // Address lookup has poor coverage of Kano plot/street addresses,
                // so a miss opens the map for manual pinning rather than stopping.
                self.openManualPin();

                if (typeof Swal === 'undefined') return;

                if (status === 'ZERO_RESULTS') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Address not found — pin it manually',
                        text: 'No match for: ' + address + '. The satellite map is now open at Kano; '
                            + 'zoom to the property and click it to drop the pin.'
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Address lookup unavailable',
                        text: 'The address lookup service could not be reached. '
                            + 'Zoom to the property on the satellite map and click it to drop the pin.'
                    });
                }
            });
        },

        /* ---------------- backfill / reset ---------------- */

        /**
         * Fill the fields from a file_indexings-shaped record and, when it
         * carries coordinates, pin them straight away.
         */
        backfill: function (data) {
            data = data || {};

            // District / Street are searchable reference dropdowns — go through
            // the helper so values missing from the reference tables still stick.
            if (typeof setReferenceSelectValue === 'function') {
                setReferenceSelectValue(el('PropertyStreetName'), data.street_name || '', true);
                setReferenceSelectValue(el('PropertyDistrict'), data.district || '', true);
            }
            if (el('PropertyPlotNo')) el('PropertyPlotNo').value = (data.plot_number || '').toString().toUpperCase();

            this.applyLga(data.lga || '');
            this.updateAddress();

            var lat = parseFloat(data.latitude);
            var lng = parseFloat(data.longitude);
            if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
                this.setPin(lat, lng, true, 'Backfilled from file indexing');
            } else {
                this.clearPin();
            }
        },

        /** Drop the pin only (keeps the typed address fields). */
        clearPin: function () {
            if (el('PropertyLatitude')) el('PropertyLatitude').value = '';
            if (el('PropertyLongitude')) el('PropertyLongitude').value = '';

            if (this.marker) {
                this.marker.setMap(null);
                this.marker = null;
            }

            if (el('PropertyMapWrapper')) el('PropertyMapWrapper').classList.add('hidden');
            if (el('PropertyMapEmpty')) el('PropertyMapEmpty').classList.remove('hidden');
            var hint = el('PropertyMapDragHint');
            if (hint) { hint.classList.add('hidden'); hint.classList.remove('inline-flex'); }
            if (el('PropertyMapCoordSource')) el('PropertyMapCoordSource').textContent = '';
        },

        /** Reset everything — address fields and pin. */
        clear: function () {
            ['PropertyHouseNo', 'PropertyPlotNo'].forEach(function (suffix) {
                if (el(suffix)) el(suffix).value = '';
            });
            if (typeof clearReferenceSelect === 'function') {
                clearReferenceSelect(el('PropertyStreetName'), true);
                clearReferenceSelect(el('PropertyDistrict'), true);
            }
            if (el('PropertyState')) el('PropertyState').value = DEFAULT_STATE;
            this.populateLgas();
            this.clearPin();
            this.updateAddress();
        },

        /** Location fields to merge into the commission request payload. */
        getPayload: function () {
            return {
                property_house_no: el('PropertyHouseNo') ? el('PropertyHouseNo').value : '',
                property_plot_no: el('PropertyPlotNo') ? el('PropertyPlotNo').value : '',
                property_street_name: el('PropertyStreetName') ? el('PropertyStreetName').value : '',
                property_district: el('PropertyDistrict') ? el('PropertyDistrict').value : '',
                property_lga: el('PropertyLga') ? el('PropertyLga').value : '',
                property_state: el('PropertyState') ? el('PropertyState').value : '',
                property_address: el('PropertyAddressDisplay') ? el('PropertyAddressDisplay').value : '',
                latitude: el('PropertyLatitude') && el('PropertyLatitude').value ? el('PropertyLatitude').value : null,
                longitude: el('PropertyLongitude') && el('PropertyLongitude').value ? el('PropertyLongitude').value : null
            };
        }
    };

    window.STLocationMaps[prefix] = instance;

    // `ownsFields: false` — the host page already renders and drives its own
    // address inputs (State/LGA population, address preview); this instance
    // only reads them and manages the map.
    if (config.ownsFields !== false) {
        instance.populateStates();
        instance.updateAddress();
    }

    return instance;
}
window.initLocationDetailsMap = initLocationDetailsMap;

/**
 * Look up a file number's indexing record (district / LGA / street / plot /
 * coordinates) so a child application can inherit its parent's location.
 * Resolves to null when the file has no indexing row.
 */
async function fetchIndexedLocation(fileNumber) {
    if (!fileNumber) return null;

    try {
        const response = await fetch('/file-indexing/search?per_page=20&search=' + encodeURIComponent(fileNumber), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        });

        if (!response.ok) return null;

        const payload = await response.json();
        const rows = (payload && payload.data) || [];
        const target = String(fileNumber).trim().toUpperCase();

        return rows.find(function (row) {
            return String(row.file_number || '').trim().toUpperCase() === target;
        }) || null;
    } catch (error) {
        console.error('Could not load indexed location for', fileNumber, error);
        return null;
    }
}
window.fetchIndexedLocation = fetchIndexedLocation;
