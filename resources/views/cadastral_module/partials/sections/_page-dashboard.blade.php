{{-- 4.1 Cadastral Registry — the module's landing dashboard. --}}
@include('cadastral_module.partials._flash')

<div class="cad-viz">
    @include('cadastral_module.partials._unit_nav', ['unit' => 'registry'])

    @if ($jobFormatUnconfirmed)
        <div class="caveat">
            <i class="fas fa-triangle-exclamation"></i>
            <div>
                <strong>Survey job numbers are being issued in an unconfirmed format.</strong>
                <code>{{ app(\App\Services\Cadastral\CadastralSettings::class)->jobNumberFormat()['format'] }}</code> is a KLAES-local
                placeholder — nobody here knows the pattern SURCON mandates. Confirm it with the
                Surveyor-General's office before issuing in bulk.
            </div>
        </div>
    @endif

    <div class="cad-actions">
        @canDo('Cad - Records', 'create')
            <a href="{{ route('cadastral-module.registry.receipts.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Log an Incoming File
            </a>
        @endcanDo
        <a href="{{ route('cadastral-module.registry.receipts') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-list"></i> Reception Log
        </a>
        <a href="{{ route('cadastral-module.registry.duplicates') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-clone"></i> Duplicate Check
        </a>
        <a href="{{ route('cadastral-module.registry.movements') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-route"></i> Trace a File
        </a>
    </div>

    @include('cadastral_module.partials._hero', ['hero' => $m['hero'], 'tiles' => $m['tiles']])

    {{-- Trend gets the wide column; the composition of the queue sits beside it. --}}
    <div class="cad-grid-3">
        @include('cadastral_module.partials.charts._columns', [
            'series'   => $m['intake'],
            'title'    => 'Files received',
            'subtitle' => 'Each day the registry took a file in. Today is highlighted; the busiest day is labelled.',
        ])

        <div style="display:flex;flex-direction:column;gap:14px;">
            @include('cadastral_module.partials.charts._stacked', [
                'parts'     => $m['pipeline'],
                'title'     => 'Where logged files sit',
                'subtitle'  => 'Every file the registry has taken in, by its current state.',
                'dimension' => 'State',
                'empty'     => 'No files logged yet.',
            ])

            @include('cadastral_module.partials.charts._stacked', [
                'parts'     => $m['fileClass'],
                'title'     => 'Direct against conversion',
                'subtitle'  => 'Conversion files are not charted — they go straight to index-card commissioning.',
                'dimension' => 'Class',
                'empty'     => 'No files logged yet.',
            ])
        </div>
    </div>

    <div class="cad-grid-2">
        @include('cadastral_module.partials.charts._hbars', [
            'rows'      => $m['bySource'],
            'title'     => 'Which registry files arrive from',
            'subtitle'  => 'Land, SLTR, ST, DCIV and KANGIS all send files here. The cadastral copy keeps the source file number.',
            'dimension' => 'Registry',
            'empty'     => 'No files logged yet.',
        ])

        @include('cadastral_module.partials.charts._hbars', [
            'rows'      => $m['byPurpose'],
            'title'     => 'What they came in for',
            'subtitle'  => 'The purpose recorded at reception, which decides where the file goes next.',
            'dimension' => 'Purpose',
            'empty'     => 'No purpose has been recorded yet.',
        ])
    </div>

    {{-- The queues: what a clerk should actually do next. --}}
    <div class="cad-grid-2">
        @include('cadastral_module.partials._queue', [
            'title'    => 'Flagged on arrival',
            'subtitle' => 'The file number matched the duplicate register, or another file shares its plot.',
            'more'     => route('cadastral-module.registry.duplicates'),
            'moreLabel'=> 'Duplicate check',
            'empty'    => 'Nothing is flagged.',
            'rows'     => collect($m['queues']['flagged'])->map(fn ($r) => [
                'primary'   => $r->file_number,
                'secondary' => Str::limit($r->duplicate_note, 58) ?: $r->receipt_ref,
                'when'      => optional($r->received_at)->diffForHumans(null, true),
                'badge'     => 'Check',
                'badgeTone' => 'rejected',
                'href'      => route('cadastral-module.registry.duplicates', ['file_number' => $r->file_number]),
            ])->all(),
        ])

        @include('cadastral_module.partials._queue', [
            'title'    => 'Waiting longest to be registered',
            'subtitle' => 'Logged in but still sitting at Received. Oldest first.',
            'more'     => route('cadastral-module.registry.receipts', ['status' => 'Received']),
            'empty'    => 'Everything logged in has been registered.',
            'rows'     => collect($m['queues']['oldest'])->map(fn ($r) => [
                'primary'   => $r->file_number,
                'secondary' => ($r->source_registry ? $r->source_registry . ' · ' : '')
                                . ($r->property_location ?: 'no location recorded'),
                'when'      => optional($r->received_at)->diffForHumans(null, true),
                'href'      => route('cadastral-module.registry.receipts.edit', $r),
            ])->all(),
        ])
    </div>

    @include('cadastral_module.partials._queue', [
        'title'    => 'Correspondence file not yet commissioned',
        'subtitle' => 'The cadastral copy is commissioned on the existing Match MLSFileNo screen — this is the backlog, read live from the index.',
        'more'     => route('cadastral-module.registry.correspondence'),
        'empty'    => 'Every logged file has its correspondence copy.',
        'rows'     => collect($m['queues']['pendingCorrespondence'])->map(fn ($r) => [
            'primary'   => $r->file_number,
            'secondary' => Str::limit($r->file_title, 60) ?: $r->receipt_ref,
            'when'      => optional($r->received_at)->diffForHumans(null, true),
            'badge'     => 'Pending',
            'badgeTone' => 'pending',
        ])->all(),
    ])
</div>
