<div class="rounded-lg border border-gray-200 bg-white p-6">
    <div class="flex items-center justify-between gap-4 mb-4">
        <div class="flex items-center gap-2 min-w-0">
            <i data-lucide="map" class="h-4 w-4 text-blue-600 flex-shrink-0"></i>
            <h4 class="text-sm font-bold uppercase tracking-wide text-gray-700 whitespace-nowrap">
                Property Location Map
            </h4>
            <span x-show="fileParams.length > 1"
                  x-text="'(File ' + (activeTab + 1) + '/' + fileParams.length + ')'"
                  class="text-xs font-medium text-gray-400 whitespace-nowrap ml-1"></span>
        </div>
        <span x-show="fileParams[activeTab] && fileParams[activeTab].latitude && fileParams[activeTab].longitude"
              x-cloak
              class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-100 rounded-full px-3 py-1">
            <i data-lucide="move" class="h-3.5 w-3.5"></i>
            Drag the pin or click the map to fine-tune
        </span>
    </div>

    <template x-for="(param, index) in fileParams" :key="param.id">
        <div x-show="index === activeTab">
            {{-- Map shown once the file is geocoded, or opened empty for manual pinning --}}
            <div x-show="(param.latitude && param.longitude) || param.awaitingManualPin" x-cloak class="rounded-md overflow-hidden border border-gray-200 shadow-sm"
                 x-init="$nextTick(() => { if (param.latitude && param.longitude && typeof google !== 'undefined' && google.maps) renderPropertyMap(param, index); })">
                <div :id="'map-' + index" style="height: 420px; width: 100%;"></div>
                <div class="text-sm text-gray-600 px-3 py-2 bg-gray-50 flex flex-wrap gap-x-6 gap-y-1">
                    <template x-if="param.latitude && param.longitude">
                        <span>Latitude: <strong x-text="param.latitude"></strong></span>
                    </template>
                    <template x-if="param.latitude && param.longitude">
                        <span>Longitude: <strong x-text="param.longitude"></strong></span>
                    </template>
                    <template x-if="!(param.latitude && param.longitude)">
                        <span class="text-amber-700 font-medium">Click the property on the map to drop the pin.</span>
                    </template>
                    <span class="truncate" x-show="param.location">Address: <strong x-text="param.location"></strong></span>
                </div>
            </div>

            {{-- Empty state before geocoding --}}
            <div x-show="!((param.latitude && param.longitude) || param.awaitingManualPin)"
                 class="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-gray-300 bg-gray-50 text-gray-400"
                 style="height: 420px;">
                <i data-lucide="map-pin" class="h-8 w-8"></i>
                <p class="text-sm">No location pinned yet.</p>
                <p class="text-xs">Fill in the Property Details above, then click <strong>Apply &amp; Pin on Map</strong>.</p>
            </div>
        </div>
    </template>
</div>
