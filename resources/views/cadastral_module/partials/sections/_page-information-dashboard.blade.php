{{-- 4.3 Cadastral Information — charting, index cards, survey jobs. --}}
@include('cadastral_module.partials._flash')

<div class="cad-viz">
    @include('cadastral_module.partials._unit_nav', ['unit' => 'information'])

    <div class="cad-actions">
        @canDo('Cad - Records', 'create')
            <a href="{{ route('cadastral-module.charting.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> New Chart
            </a>
        @endcanDo
        <a href="{{ route('cadastral-module.charting.index') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-pen-ruler"></i> Charting Register
        </a>
        <a href="{{ route('cadastral-module.index-cards.index') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-id-card"></i> Index Cards
        </a>
        <a href="{{ route('cadastral-module.survey-jobs.index') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-helmet-safety"></i> Survey Jobs
        </a>
        <a href="{{ route('cadastral-module.charting.conflicts') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-triangle-exclamation"></i> Conflicts
        </a>
    </div>

    @include('cadastral_module.partials._hero', ['hero' => $m['hero'], 'tiles' => $m['tiles']])

    {{-- Beacon-ring coverage is the honest limit on conflict detection, so it
         is stated as a meter rather than buried in a footnote. --}}
    @php $cov = $m['coverage']; @endphp
    <div class="cad-card" style="margin-bottom:14px;">
        <div class="cad-card-head">
            <h3>Beacon rings keyed</h3>
            <span style="font-size:11.5px;color:var(--viz-muted);">
                {{ number_format($cov['with']) }} of {{ number_format($cov['total']) }} charts
            </span>
        </div>
        <p class="cad-card-sub">
            Geometric conflict detection can only compare charts whose coordinates have been
            entered. KLAES stores no parcel geometry of its own, so this figure <em>is</em> the
            coverage — everything else is identity matching on plot, block and layout.
        </p>

        @if ($cov['total'] === 0)
            <div class="cad-empty">
                <i class="fas fa-ruler"></i>
                <span>No charts require charting yet.</span>
            </div>
        @else
            <div style="display:flex;align-items:center;gap:14px;">
                <div style="flex:1;height:12px;border-radius:6px;background:var(--viz-plane);overflow:hidden;">
                    <div style="height:100%;width:{{ $cov['percent'] }}%;background:var(--viz-seq);border-radius:6px;"></div>
                </div>
                <strong style="font-size:18px;color:var(--viz-ink);font-variant-numeric:tabular-nums;">{{ $cov['percent'] }}%</strong>
            </div>
            @if ($cov['without'] > 0)
                <p class="cad-card-sub" style="margin:10px 0 0;">
                    {{ number_format($cov['without']) }} chart(s) have no ring, so nothing can be
                    compared against them geometrically.
                </p>
            @endif
        @endif
    </div>

    <div class="cad-grid-3">
        @include('cadastral_module.partials.charts._columns', [
            'series'   => $m['charted'],
            'title'    => 'Charts created',
            'subtitle' => 'New chart records per day, including new versions of existing charts.',
        ])

        <div style="display:flex;flex-direction:column;gap:14px;">
            @include('cadastral_module.partials.charts._stacked', [
                'parts'     => $m['chartCategory'],
                'title'     => 'Charted against not charted',
                'subtitle'  => 'Conversion files are recorded but deliberately not charted.',
                'dimension' => 'Category',
                'empty'     => 'No charts yet.',
            ])

            @include('cadastral_module.partials.charts._hbars', [
                'rows'      => $m['cardStatus'],
                'title'     => 'Index cards by file status',
                'dimension' => 'Status',
                'empty'     => 'No index cards commissioned yet.',
                'labelW'    => 130,
            ])
        </div>
    </div>

    <div class="cad-grid-2">
        @include('cadastral_module.partials.charts._hbars', [
            'rows'      => $m['chartStatus'],
            'title'     => 'Current charts by status',
            'subtitle'  => 'Superseded versions are excluded — they are history, not workload.',
            'dimension' => 'Status',
            'empty'     => 'No charts yet.',
        ])

        @include('cadastral_module.partials.charts._hbars', [
            'rows'      => $m['jobStatus'],
            'title'     => 'Survey jobs by status',
            'subtitle'  => 'From registration through the Instruction to Surveyor to acceptance.',
            'dimension' => 'Status',
            'empty'     => 'No survey jobs registered yet.',
        ])
    </div>

    <div class="cad-grid-2">
        @include('cadastral_module.partials._queue', [
            'title'    => 'Waiting to be charted',
            'subtitle' => 'Direct files sitting at Draft. Oldest first. Conversion files are not listed — they are not charted.',
            'more'     => route('cadastral-module.charting.index', ['status' => 'Draft']),
            'empty'    => 'Nothing is waiting to be charted.',
            'rows'     => collect($m['queues']['unCharted'])->map(fn ($c) => [
                'primary'   => $c->file_number,
                'secondary' => ($c->plot_no ? 'Plot ' . $c->plot_no . ' · ' : '')
                                . ($c->property_location ?: 'no location recorded'),
                'when'      => optional($c->created_at)->diffForHumans(null, true),
                'href'      => route('cadastral-module.charting.edit', $c),
            ])->all(),
        ])

        @include('cadastral_module.partials._queue', [
            'title'    => 'Charts flagged as conflicting',
            'subtitle' => 'Two live charts appear to describe the same ground.',
            'more'     => route('cadastral-module.charting.conflicts'),
            'empty'    => 'No chart is flagged.',
            'rows'     => collect($m['queues']['conflicts'])->map(fn ($c) => [
                'primary'   => $c->chart_ref . ' · ' . $c->file_number,
                'secondary' => Str::limit($c->conflict_note, 58) ?: 'No note recorded',
                'badge'     => ucfirst($c->conflict_status),
                'badgeTone' => $c->conflict_status === 'confirmed' ? 'rejected' : 'review',
                'href'      => route('cadastral-module.charting.edit', $c),
            ])->all(),
        ])
    </div>

    @include('cadastral_module.partials._queue', [
        'title'    => 'Surveyor licences expiring',
        'subtitle' => 'Within the next 60 days. An Instruction to Surveyor is refused once a licence lapses.',
        'more'     => route('cadastral-module.surveyors.index'),
        'empty'    => 'No licence expires in the next 60 days.',
        'rows'     => collect($m['queues']['licences'])->map(fn ($s) => [
            'primary'   => $s->full_name,
            'secondary' => $s->firm_name ?: ($s->surcon_number ? 'SURCON ' . $s->surcon_number : 'No firm recorded'),
            'when'      => optional($s->licence_expires_on)->format('d M Y'),
            'badge'     => $s->licence_status,
            'badgeTone' => $s->canReceiveInstruction() ? 'pending' : 'rejected',
        ])->all(),
    ])
</div>
