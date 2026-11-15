{{-- Survey Plan Check card: Export (the last section), plus the card's styles and script. Included once, after
     _plan_check and the host page's Plan details. $spcExportNo: the section number (6 on upload, 5 on check).
     jQuery and select2 must be on the page before this (both layouts load them).
     $spcExportGate: upload pages (user, 2026-11-13) - the section starts disabled behind an unticked checkbox. --}}
@php
  // Geodatabase is written on the server (GDAL); KLAES and the MLPP-SG portal each have their own route.
  $spcGdbUrl = request()->routeIs('surveyor-portal.*') ? route('surveyor-portal.survey-plans.gdb') : route('survey-module.survey-plans.gdb');
@endphp
<div class="spc" id="spc-export" data-gdb="{{ $spcGdbUrl }}" data-csrf="{{ csrf_token() }}">
  <div class="spc-group"><span class="n">{{ $spcExportNo ?? 6 }}</span><span>Export</span><span class="aside" id="spc-ex-grid"></span></div>
  @if (! empty($spcExportGate))
    <label class="spc-check spc-exgate" for="spc-exOn"><input type="checkbox" id="spc-exOn" onchange="document.getElementById('spc-exBody').disabled = ! this.checked"><span>Export the beacons and drawings</span></label>
  @endif
  <fieldset class="spc-exbody" id="spc-exBody" @if (! empty($spcExportGate)) disabled @endif>
  <div id="spc-feats" class="spc-note">Shapes drawn with the map tools appear here.</div>
  <label class="spc-check" for="spc-incplan"><input type="checkbox" id="spc-incplan" checked><span>Include the reference coordinate, other beacons and polygon</span></label>
  <div class="spc-exports">
    <button type="button" class="spc-btn" data-export="geojson" title="GeoJSON, latitude / longitude (WGS 84)"><i class="fas fa-code"></i> GeoJSON</button>
    <button type="button" class="spc-btn" data-export="kml" title="KML for Google Earth"><i class="fas fa-earth-africa"></i> KML</button>
    <button type="button" class="spc-btn" data-export="shp" title="Shapefile (.zip), X / Y in the chosen system, with its .prj"><i class="fas fa-file-zipper"></i> Shapefile</button>
    <button type="button" class="spc-btn" data-export="dxf" title="DXF for CAD, X / Y in the chosen system"><i class="fas fa-compass-drafting"></i> DXF</button>
    <button type="button" class="spc-btn" data-export="csv" title="Every point: X / Y in the chosen system and latitude / longitude"><i class="fas fa-file-csv"></i> CSV</button>
    <button type="button" class="spc-btn" data-export="sql" title="SQL Server INSERTs with geometry in the chosen system"><i class="fas fa-database"></i> SQL</button>
    <button type="button" class="spc-btn" data-export="gdb" title="Esri File Geodatabase (.gdb, zipped) for ArcGIS / QGIS: beacons, lines and parcels layers, X / Y in the chosen system"><i class="fas fa-layer-group"></i> Geodatabase</button>
  </div>
  <p class="spc-note" id="spc-export-note"></p>
  </fieldset>
  <p class="spc-note">The map is latitude / longitude (WGS 84). NTM X / Y are converted with the Minna → WGS 84 datum shift (−92, −93, +122 m), never by adding constants. "In Kano" means 10.3°–12.9° N, 7.6°–9.5° E.</p>
</div>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="{{ asset('maps/vendor/maplibre-gl.css') }}">
<link rel="stylesheet" href="{{ asset('css/survey-plan-check.css') }}?v={{ filemtime(public_path('css/survey-plan-check.css')) }}">
<script src="{{ asset('maps/vendor/maplibre-gl.js') }}"></script>
<script src="{{ asset('maps/vendor/pmtiles.js') }}"></script>
<script src="{{ asset('maps/vendor/proj4.js') }}"></script>
<script src="{{ asset('maps/vendor/shpwrite.js') }}"></script>
<script src="{{ asset('maps/vendor/shp.js') }}"></script>
{{-- Scanned plans / GeoTIFF (2026-11-15): geotiff.js reads GeoTIFFs, UTIF.js the scans with tags geotiff.js refuses. --}}
<script src="{{ asset('maps/vendor/geotiff.js') }}"></script>
<script src="{{ asset('maps/vendor/UTIF.js') }}"></script>
<script src="{{ asset('js/survey-plan-scan.js') }}?v={{ filemtime(public_path('js/survey-plan-scan.js')) }}"></script>
<script src="{{ asset('js/survey-plan-check.js') }}?v={{ filemtime(public_path('js/survey-plan-check.js')) }}"></script>
