{{--
    Commissioner memo, printable.

    Printable at every stage on purpose: a PENDING memo is the document carried
    to the Commissioner for signature, so it prints with an empty decision block
    to sign; a DECIDED one prints the decision that was recorded, and the block
    becomes the record rather than a form.
--}}
@php
    $app      = $memo->application;
    $decision = $memo->commissioner_decision;
    $decided  = in_array($decision, ['approved', 'rejected'], true);
    $inspections = $app ? $app->fieldData()->orderByDesc('inspection_date')->orderByDesc('id')->get() : collect();
    $photo = collect($app->photos ?? [])->filter()->first()
        ?: $inspections->flatMap(fn ($inspection) => $inspection->photos ?? [])->filter()->first();
    $photoUrl = $photo ? url('storage/'.ltrim($photo, '/')) : null;
    $mapInspection = $inspections->first(fn ($inspection) => !empty($inspection->coordinates) || !empty($inspection->parcel_geometry));
    $coordinates = $mapInspection?->coordinates;
    $geometry = $mapInspection?->parcel_geometry;
    $qrRenderer = new \BaconQrCode\Renderer\ImageRenderer(
        new \BaconQrCode\Renderer\RendererStyle\RendererStyle(160, 4),
        new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
    );
    $qrSvg = (new \BaconQrCode\Writer($qrRenderer))->writeString(route('special-assignment.memo.print', $memo->id));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memo – {{ $memo->memo_no }}</title>
    <style>
        @page { size: A4; margin: 0; }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f0f0f0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .a4-page {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            box-sizing: border-box;
            padding: 14mm 20mm 8mm;
            box-shadow: 0 0 15px rgba(0,0,0,.1);
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
        }
        .logo-box img { max-width: 80px; max-height: 80px; object-fit: contain; }
        .header-marks { display: flex; align-items: center; gap: 8px; }
        .memo-qr { width: 60px; height: 60px; flex-shrink: 0; }
        .gov-title-container { text-align: center; }
        .gov-title-container h1 { font-size: 18px; margin: 0; }
        .gov-title-container h2 { font-size: 14px; margin: 2px 0 0; font-weight: normal; }
        /* Slightly smaller and tracked out, so the department reads as
           subordinate to the Ministry rather than competing with it. */
        .gov-title-container h2.dept-line {
            font-size: 12px; letter-spacing: .04em; text-transform: uppercase;
            margin-top: 3px; color: #333;
        }
        .memo-footer-logo {
            margin-top: 14px; padding-top: 8px;
            border-top: 1px solid #ddd; text-align: right;
        }
        .memo-footer-logo img { max-height: 46px; width: auto; object-fit: contain; }
        .gov-title-container h3 {
            font-size: 15px; margin: 8px 0 0;
            text-transform: uppercase; letter-spacing: 1.5px;
        }

        .memo-head {
            margin-top: 16px;
            font-size: 13px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .memo-head .row { display: flex; padding: 3px 0; }
        .memo-head .label {
            width: 34mm;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11.5px;
            letter-spacing: .04em;
        }

        .subject {
            margin: 16px 0 10px;
            font-size: 13.5px;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
        }

        p.body-text { font-size: 13px; line-height: 1.75; text-align: justify; margin: 0 0 10px; }

        table.details {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 4px;
            font-size: 12.5px;
        }
        table.details th, table.details td {
            border: 1px solid #cfcfcf;
            padding: 7px 10px;
            text-align: left;
            vertical-align: top;
        }
        table.details th {
            background: #f4f4f0;
            width: 45mm;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .property-summary { display: flex; gap: 10px; align-items: stretch; margin-top: 12px; }
        .property-summary table.details { width: 70%; margin: 0; table-layout: fixed; }
        .property-summary table.details th { width: 34%; }
        .property-summary table.details td { overflow-wrap: anywhere; }
        .property-summary table.details th, .property-summary table.details td { padding: 6px; }
        .property-photo { width: 30%; margin: 0; border: 1px solid #cfcfcf; display: flex; flex-direction: column; justify-content: center; text-align: center; }
        .property-photo img { display: block; width: 100%; height: 44mm; object-fit: contain; }
        .property-photo figcaption { font-size: 10px; padding: 5px; }
        .media-placeholder { color: #666; font-size: 12px; padding: 12px; text-align: center; }
        .map-section { margin-top: 12px; border: 1px solid #cfcfcf; break-inside: avoid; }
        .map-section h4 { margin: 0; padding: 6px 10px; font-size: 11px; text-transform: uppercase; }
        #memo-map { height: 30mm; background: #f4f4f0; }
        .property-summary, .decision-block, .sign-row { break-inside: avoid; }
        .decision-block {
            margin-top: 16px;
            border: 1px solid #cfcfcf;
            padding: 12px 14px;
            font-size: 12.5px;
        }
        .decision-block h4 {
            margin: 0 0 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .decision-line { min-height: 9mm; border-bottom: 1px dotted #999; margin-bottom: 8px; }
        .stamp {
            display: inline-block;
            padding: 4px 14px;
            border: 2px solid;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: 13px;
        }
        .stamp.approved { color: #15803d; border-color: #15803d; }
        .stamp.rejected { color: #b91c1c; border-color: #b91c1c; }

        .sign-row { display: flex; justify-content: space-between; margin-top: 15mm; font-size: 12px; }
        .sign-box { width: 68mm; text-align: center; }
        .sign-line { border-top: 1px solid #333; padding-top: 4px; }

        .toolbar { text-align: center; padding: 12px; }
        .toolbar button {
            padding: 8px 18px; font-size: 13px; border: none; border-radius: 6px;
            cursor: pointer; margin: 0 4px; background: rgb(186,191,12); color: #fff;
        }
        .toolbar .btn-close { background: #6b7280; }

        /* The three signing sections of the updated memo. */
        .sig-sections { margin-top: 5mm; font-size: 12.5px; break-inside: avoid; }
        .sig-prep { display: flex; justify-content: space-between; gap: 24px; }
        .sig-col { flex: 1; display: flex; flex-direction: column; gap: 9px; }
        .sig-counter { flex: 0 0 46%; }
        .sig-field { display: flex; align-items: flex-end; gap: 6px; }
        .sig-field span { flex-shrink: 0; font-weight: bold; }
        .sig-field span.gap { margin-left: 12px; }
        .sig-field i { flex: 1; border-bottom: 1px solid #000; height: 14px; }
        .sig-field i.short { flex: 0 0 22%; }
        .sig-role { text-align: right; font-weight: bold; }
        .sig-office { margin-top: 4mm; }
        .sig-office h4 { margin: 0 0 4px; text-align: center; font-size: 12.5px; text-transform: uppercase; text-decoration: underline; }
        .sig-office p { margin: 0 0 8px; }
        .sig-decided { margin: 8px 0 0; display: flex; gap: 10px; align-items: center; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .a4-page { margin: 0; box-shadow: none; width: auto; min-height: auto; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button onclick="window.print()">Print Memo</button>
    <button class="btn-close" onclick="window.close()">Close</button>
</div>

<div class="a4-page">

    <div class="header-section">
        <div class="logo-box">
            <img src="{{ url('assets/logo/ministry2.png') }}" alt="Ministry Logo">
        </div>
        <div class="gov-title-container">
            <h1>Kano State Government</h1>
            <h2>Ministry of Land &amp; Physical Planning</h2>
            {{-- The issuing department. A SPAS memo lands on desks across the
                 Ministry, so the heading has to say which department raised
                 it, not only which Ministry. --}}
            <h2 class="dept-line">Department of Special Assignment</h2>
            <h3>Internal Memo</h3>
        </div>
        <div class="header-marks">
            <img class="memo-qr" src="data:image/svg+xml;base64,{{ base64_encode($qrSvg) }}" alt="QR code to open this memo">
            <div class="logo-box">
                <img src="{{ url('assets/logo/ministry1.jpg') }}" alt="Coat of Arms">
            </div>
        </div>
    </div>

    <div class="memo-head">
        <div class="row"><span class="label">Memo No:</span><span><strong>{{ $memo->memo_no }}</strong></span></div>
        <div class="row"><span class="label">To:</span><span>{{ $memo->forwarded_to ?: 'Honourable Commissioner' }}</span></div>
        <div class="row"><span class="label">From:</span><span>Special Assignment Department</span></div>
        <div class="row"><span class="label">Date:</span><span>{{ $memo->forwarded_at?->format('d F Y') ?? '—' }}</span></div>
    </div>

    {{-- Wording and layout as on the Department's updated memo (2026-10-12). --}}
    <p class="subject">Contravention File No. {{ ($app->spa_id ?? null) ?: (($app->file_number ?? null) ?: '—') }}</p>

    <p class="body-text">
        Following the special assignment field exercise, the property described below was
        assessed and its particulars established as recorded. The file is hereby forwarded
        for {{ $memo->forwarded_to ?? 'the Honourable Commissioner' }}'s consideration and approval.
    </p>

    <div class="property-summary">
    <table class="details">
        {{-- Approved vs prevailing: the same two uses the contravention bill is raised on. --}}
        <tr><th>File Number</th><td>{{ ($app->file_number ?? null) ?: (($app->spa_id ?? null) ?: '—') }}</td></tr>
        <tr><th>Owner's Name</th><td>{{ mb_strtoupper($app->owner_name ?? '—', 'UTF-8') }}</td></tr>
        <tr><th>Location</th><td>{{ mb_strtoupper($app->location ?? '—', 'UTF-8') }}</td></tr>
        <tr><th>District / LGA</th><td>{{ mb_strtoupper($app->district ?? '—', 'UTF-8') }}{{ $app->lga ? ' / '.mb_strtoupper($app->lga, 'UTF-8') : '' }}</td></tr>
        <tr><th>Approved Land Use</th><td>{{ ($app->proposed_use ?? null) ? mb_strtoupper($app->proposed_use, 'UTF-8').' PURPOSE' : '—' }}</td></tr>
        <tr><th>Prevailing Use</th><td>{{ ($app->existing_use ?? null) ? mb_strtoupper($app->existing_use, 'UTF-8').' USE' : '—' }}</td></tr>
    </table>
    <figure class="property-photo">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="Property photograph" onerror="this.hidden=true; this.nextElementSibling.textContent='Property photograph unavailable';">
        @else
            <div class="media-placeholder">No property photograph recorded</div>
        @endif
        <figcaption>Property Photograph</figcaption>
    </figure>
    </div>

    <section class="map-section">
        <h4>Property Location Map</h4>
        <div id="memo-map"><div class="media-placeholder">{{ $mapInspection ? 'Loading property map...' : 'No property coordinates recorded' }}</div></div>
    </section>

    <div class="sig-sections">
        {{-- Prepared by, counter-signed by the Director. --}}
        <div class="sig-prep">
            <div class="sig-col">
                <div class="sig-field"><span>Name:</span><i></i></div>
                <div class="sig-field"><span>Rank:</span><i></i></div>
                <div class="sig-field"><span>Date:</span><i></i></div>
                <div class="sig-field"><span>Sign:</span><i></i><span class="gap">Date:</span><i></i></div>
            </div>
            <div class="sig-col sig-counter">
                <div class="sig-field"><span>Counter Sign:</span><i></i><span class="gap">Date:</span><i class="short"></i></div>
                <div class="sig-role">Director Special Assignment</div>
            </div>
        </div>

        <div class="sig-office">
            <h4>Permanent Secretary</h4>
            <p>The application is hereby approved / not approved</p>
            <div class="sig-field"><span>Sign:</span><i></i><span class="gap">Date:</span><i></i></div>
        </div>

        <div class="sig-office">
            <h4>Honourable Commissioner</h4>
            <p>The application is hereby recommended for approval please</p>
            <div class="sig-field"><span>Sign:</span><i></i><span class="gap">Date:</span><i></i></div>
    
            {{-- Once recorded in KLAES, the decision prints as the record of it. --}}
            @if ($decided)
                <p class="sig-decided">
                    <span class="stamp {{ $decision }}">{{ $decision }}</span>
                    <span>Dated {{ $memo->decided_at?->format('d F Y') ?? '—' }}</span>
                    @if ($memo->commissioner_notes)<span>— {{ $memo->commissioner_notes }}</span>@endif
                </p>
            @endif
        </div>
    </div>

    {{-- Ministry mark at the foot of the printed page. Served from the live
         host rather than asset(), because these documents are printed and
         emailed from environments whose APP_URL is not app.klaes.ng. --}}
    <div class="memo-footer-logo">
        <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="">
    </div>

</div>

@if ($mapInspection)
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    (() => {
        const container = document.getElementById('memo-map');
        const coordinates = @json($coordinates);
        const geometry = @json($geometry);
        if (typeof L === 'undefined') {
            container.textContent = 'Property map unavailable. Please reload before printing.';
            return;
        }
        const lat = Number(coordinates?.lat), lng = Number(coordinates?.lng);
        const hasPin = coordinates?.lat != null && coordinates?.lng != null
            && Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
        container.innerHTML = '';
        const map = L.map(container, { zoomControl: false, attributionControl: true,
            dragging: false, scrollWheelZoom: false, doubleClickZoom: false, boxZoom: false, keyboard: false, touchZoom: false });
        const tiles = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '&copy; Esri', maxZoom: 19
        }).addTo(map);
        let bounds;
        if (geometry) {
            try {
                const parcel = L.geoJSON(geometry, { style: { color: '#ffdf00', weight: 3, fillOpacity: 0.12 } }).addTo(map);
                if (parcel.getBounds().isValid()) bounds = parcel.getBounds();
            } catch (error) { /* A valid recorded pin can still locate the property. */ }
        }
        if (hasPin) L.circleMarker([lat, lng], { radius: 6, color: '#fff', weight: 2, fillColor: '#dc2626', fillOpacity: 1 }).addTo(map);
        if (bounds) map.fitBounds(bounds, { padding: [16, 16], maxZoom: 18 });
        else if (hasPin) map.setView([lat, lng], 17);
        else { map.remove(); container.textContent = 'No valid property coordinates recorded'; return; }
        window.addEventListener('beforeprint', () => map.invalidateSize({ animate: false }));
    })();
</script>
@endif
</body>
</html>
