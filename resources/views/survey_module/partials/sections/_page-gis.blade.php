@php
    use App\Models\Survey\SurveyCompCase;
    use App\Models\Survey\SurveyGknRecord;
    use App\Models\Survey\SurveyLpkn;
    use App\Models\Survey\SurveyPlotAllocation;

    /*
     | DashboardController::gis() owns this route and may pass:
     |
     |   $gisStats   array{gkn_total,gkn_mapped,case_total,case_mapped,lpkn_total,plot_total}
     |   $gisResults iterable of ['type','ref','label','location','coordinates','url']
     |   $gisQuery   string — the term that produced $gisResults
     |
     | Until it does, the page falls back to the same queries itself so the
     | counts and the search stay live rather than fake. Anything the
     | controller passes wins.
     */
    $hasCoords = fn ($q) => $q->whereNotNull('coordinates')->where('coordinates', '<>', '');

    $gisStats = $gisStats ?? [
        'gkn_total'   => SurveyGknRecord::count(),
        'gkn_mapped'  => $hasCoords(SurveyGknRecord::query())->count(),
        'case_total'  => SurveyCompCase::count(),
        'case_mapped' => $hasCoords(SurveyCompCase::query())->count(),
        'lpkn_total'  => SurveyLpkn::count(),
        'plot_total'  => SurveyPlotAllocation::count(),
    ];

    $gisQuery = $gisQuery ?? trim((string) request('q', ''));

    if (!isset($gisResults)) {
        $gisResults = collect();

        if ($gisQuery !== '') {
            $gkn = SurveyGknRecord::query()
                ->where(fn ($w) => $w->where('gkn_number', 'like', "%$gisQuery%")
                                     ->orWhere('title', 'like', "%$gisQuery%")
                                     ->orWhere('coordinates', 'like', "%$gisQuery%")
                                     ->orWhere('prop_district', 'like', "%$gisQuery%")
                                     ->orWhere('prop_plot', 'like', "%$gisQuery%"))
                ->orderByDesc('id')->limit(10)->get()
                ->map(fn ($g) => [
                    'type'        => 'GKN',
                    'ref'         => $g->gkn_number,
                    'label'       => $g->title,
                    'location'    => $g->property_location,
                    'coordinates' => $g->coordinates,
                    'url'         => route('survey-module.gkn.edit', $g),
                ]);

            $cases = SurveyCompCase::query()
                ->where(fn ($w) => $w->where('case_ref', 'like', "%$gisQuery%")
                                     ->orWhere('coordinates', 'like', "%$gisQuery%")
                                     ->orWhere('gps_reading', 'like', "%$gisQuery%")
                                     ->orWhere('prop_district', 'like', "%$gisQuery%")
                                     ->orWhere('prop_plot', 'like', "%$gisQuery%"))
                ->orderByDesc('id')->limit(10)->get()
                ->map(fn ($c) => [
                    'type'        => 'Case',
                    'ref'         => $c->case_ref,
                    'label'       => $c->purpose,
                    'location'    => $c->property_location,
                    'coordinates' => $c->coordinates ?: $c->gps_reading,
                    'url'         => route('survey-module.compensation.cases.show', $c),
                ]);

            $gisResults = $gkn->concat($cases);
        }
    }

    $gisResults = collect($gisResults);
    $stat = fn ($k) => number_format((int) ($gisStats[$k] ?? 0));
@endphp

@include('survey_module.partials._flash')

<div class="gis-layout">
    <div class="gis-map">
        <i class="fas fa-map"></i>
        <div>Interactive map canvas</div>
        <div style="font-size:12px;opacity:0.7;">
            {{ $stat('gkn_mapped') }} GKN parcel(s) and {{ $stat('case_mapped') }} case(s) have coordinates stored
        </div>
        <div style="font-size:12px;opacity:0.55;">Rendering is not wired up yet — references below link to the record.</div>
        <div class="map-controls">
            <button type="button" disabled title="Not available until the map canvas is wired up">Zoom +</button>
            <button type="button" disabled title="Not available until the map canvas is wired up">Zoom −</button>
            <button type="button" disabled title="Not available until the map canvas is wired up">Layers</button>
            <button type="button" disabled title="Not available until the map canvas is wired up">Measure</button>
        </div>
    </div>

    <div class="gis-panel">
        <h3 style="margin-bottom:12px;font-size:15px;">Layers</h3>

        <div class="layer-item">
            <input type="checkbox" checked disabled />
            <span style="flex:1;">Base map</span>
        </div>
        <div class="layer-item">
            <input type="checkbox" checked disabled />
            <span style="flex:1;">GKN parcels</span>
            <span style="font-size:12px;color:var(--gray-600);">{{ $stat('gkn_mapped') }} / {{ $stat('gkn_total') }}</span>
        </div>
        <div class="layer-item">
            <input type="checkbox" checked disabled />
            <span style="flex:1;">Compensation cases</span>
            <span style="font-size:12px;color:var(--gray-600);">{{ $stat('case_mapped') }} / {{ $stat('case_total') }}</span>
        </div>
        <div class="layer-item">
            <input type="checkbox" disabled />
            <span style="flex:1;">Plot allocation</span>
            <span style="font-size:12px;color:var(--gray-600);">{{ $stat('plot_total') }}</span>
        </div>
        <div class="layer-item">
            <input type="checkbox" disabled />
            <span style="flex:1;">Layout plans (LPKN)</span>
            <span style="font-size:12px;color:var(--gray-600);" title="LPKN records hold no coordinates column">{{ $stat('lpkn_total') }}</span>
        </div>
        <div class="layer-item">
            <input type="checkbox" disabled />
            <span style="flex:1;color:var(--gray-500);">Cadastre grid</span>
            <span style="font-size:12px;color:var(--gray-500);">no data</span>
        </div>

        <p class="helper-text" style="margin-top:8px;">
            Counts are mapped / total: a record is mapped once its coordinates field is filled.
            Layer toggles activate with the map canvas.
        </p>

        <hr style="margin:16px 0;border:none;border-top:1px solid var(--gray-200);" />

        <h3 style="margin-bottom:8px;font-size:15px;">Quick search</h3>
        <form method="GET" action="{{ route('survey-module.tools.gis') }}">
            <input type="text" name="q" value="{{ $gisQuery }}" placeholder="GKN no., case ref, plot, district…"
                   style="width:100%;padding:8px 12px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;" />
            <button class="btn btn-primary btn-sm" type="submit" style="margin-top:10px;width:100%;">
                <i class="fas fa-search"></i> Locate
            </button>
        </form>

        @if ($gisQuery !== '')
            <div style="margin-top:14px;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--gray-600);margin-bottom:6px;">
                    {{ $gisResults->count() }} match(es) for “{{ $gisQuery }}”
                </div>

                @forelse ($gisResults as $res)
                    <a href="{{ data_get($res, 'url') ?: '#' }}" class="layer-item"
                       style="display:block;text-decoration:none;color:inherit;">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                            <strong style="font-size:13px;">{{ data_get($res, 'ref') ?: '—' }}</strong>
                            <span class="status-badge {{ data_get($res, 'type') === 'GKN' ? 'active' : 'review' }}">
                                <span class="dot"></span>{{ data_get($res, 'type') ?: 'Record' }}
                            </span>
                        </div>
                        @if (data_get($res, 'label'))
                            <div style="font-size:12px;color:var(--gray-700);">{{ data_get($res, 'label') }}</div>
                        @endif
                        <div style="font-size:12px;color:var(--gray-600);">
                            {{ data_get($res, 'location') ?: 'Location not recorded' }}
                        </div>
                        <div style="font-size:12px;color:var(--gray-500);">
                            <i class="fas fa-location-dot"></i>
                            {{ data_get($res, 'coordinates') ?: 'No coordinates stored' }}
                        </div>
                    </a>
                @empty
                    <p class="helper-text">
                        Nothing matched. Search a GKN number, case reference, plot number, district or a coordinate string.
                    </p>
                @endforelse
            </div>
        @endif
    </div>
</div>
