{{-- 4.2 Cadastral Report — the desk officer's view across all three streams. --}}
@include('cadastral_module.partials._flash')

<div class="cad-viz">
    @include('cadastral_module.partials._unit_nav', ['unit' => 'reports'])

    <div class="cad-actions">
        @canDo('Cad - Records', 'create')
            <a href="{{ route('cadastral-module.reports.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Open a Report
            </a>
        @endcanDo
        <a href="{{ route('cadastral-module.reports.index') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-list"></i> All Reports
        </a>
        @canDo('Cad - Records', 'export')
            <a href="{{ route('cadastral-module.reports.export') }}" class="btn btn-outline btn-sm">
                <i class="fas fa-file-csv"></i> Export
            </a>
        @endcanDo
    </div>

    @if ($myPosts)
        <p class="cad-card-sub" style="margin-top:-6px;">
            Signed in as
            <strong style="color:var(--viz-ink);">{{ collect($myPosts)->map(fn ($p) => config('cadastral_module.posts')[$p] ?? $p)->implode(', ') }}</strong>.
            Steps routed to your post are actionable below.
        </p>
    @else
        <div class="caveat">
            <i class="fas fa-circle-info"></i>
            <div>
                <strong>You hold no Cadastral post.</strong>
                Report steps route by job post, held in the officer directory, not by module role —
                so nothing is assigned to you and post-gated steps will refuse you. An administrator
                needs to record your post.
            </div>
        </div>
    @endif

    @include('cadastral_module.partials._hero', ['hero' => $m['hero'], 'tiles' => $m['tiles']])

    <div class="cad-grid-3">
        @include('cadastral_module.partials.charts._columns', [
            'series'   => $m['opened'],
            'title'    => 'Reports opened',
            'subtitle' => 'New reports across all three streams.',
        ])

        <div style="display:flex;flex-direction:column;gap:14px;">
            @include('cadastral_module.partials.charts._stacked', [
                'parts'     => $m['byType'],
                'title'     => 'The three streams',
                'subtitle'  => 'Verification runs eight steps; customary and statutory run the same seven without the field inspection.',
                'dimension' => 'Stream',
                'empty'     => 'No reports opened yet.',
            ])

            @include('cadastral_module.partials.charts._hbars', [
                'rows'      => $m['ageing'],
                'title'     => 'How long in-flight reports have been open',
                'dimension' => 'Age',
                'empty'     => 'No reports in flight.',
                'labelW'    => 120,
            ])
        </div>
    </div>

    {{-- The stage chart is the heart of this dashboard: it says where the work
         is stuck. One series, one colour — the axis carries stage identity and
         its order is the chain, not the size. --}}
    <div class="cad-grid-2">
        @include('cadastral_module.partials.charts._hbars', [
            'rows'      => $m['byStage'],
            'title'     => 'Where in-flight reports are sitting',
            'subtitle'  => 'In chain order, not by size — a funnel read out of order is not a funnel. Shown against the verification chain, the longest of the three.',
            'dimension' => 'Stage',
            'empty'     => 'No reports in flight.',
            'labelW'    => 160,
        ])

        @include('cadastral_module.partials.charts._hbars', [
            'rows'      => $m['byDesk'],
            'title'     => 'Which desk is holding them',
            'subtitle'  => 'By job post. A desk with nobody recorded against it is a step no one can complete.',
            'dimension' => 'Desk',
            'empty'     => 'Nothing is assigned to a desk.',
            'labelW'    => 190,
        ])
    </div>

    <div class="cad-grid-2">
        @include('cadastral_module.partials._queue', [
            'title'    => 'On your desk',
            'subtitle' => 'Routed to a post you hold. Due date first.',
            'empty'    => $myPosts ? 'Nothing is waiting on your post.' : 'You hold no post, so nothing routes to you.',
            'rows'     => collect($m['queues']['mine'])->map(fn ($r) => [
                'primary'   => $r->report_ref . ' · ' . $r->file_number,
                'secondary' => $r->type_label . ' · step ' . $r->current_step . ' — ' . Str::headline((string) $r->current_step_key),
                'when'      => $r->due_date ? $r->due_date->format('d M') : '',
                'badge'     => $r->due_date && $r->due_date->isPast() ? 'Overdue' : null,
                'badgeTone' => 'rejected',
                'href'      => route('cadastral-module.reports.show', $r),
            ])->all(),
        ])

        @include('cadastral_module.partials._queue', [
            'title'    => 'Past the due date',
            'subtitle' => 'Still in flight after the date they were due.',
            'more'     => route('cadastral-module.reports.index'),
            'empty'    => 'Nothing is overdue.',
            'rows'     => collect($m['queues']['overdue'])->map(fn ($r) => [
                'primary'   => $r->report_ref . ' · ' . $r->file_number,
                'secondary' => $r->type_label . ' · ' . (config('cadastral_module.posts')[$r->assigned_post] ?? 'unassigned'),
                'when'      => optional($r->due_date)->diffForHumans(null, true) . ' late',
                'badge'     => 'Overdue',
                'badgeTone' => 'rejected',
                'href'      => route('cadastral-module.reports.show', $r),
            ])->all(),
        ])
    </div>

    @include('cadastral_module.partials._queue', [
        'title'    => 'Returned for correction',
        'subtitle' => 'A step was sent back. The report reopens at that step; nothing later than it has run.',
        'more'     => route('cadastral-module.reports.index', ['status' => 'Returned']),
        'empty'    => 'Nothing has been returned.',
        'rows'     => collect($m['queues']['returned'])->map(fn ($r) => [
            'primary'   => $r->report_ref . ' · ' . $r->file_number,
            'secondary' => $r->type_label . ' · back at step ' . $r->current_step . ' — ' . Str::headline((string) $r->current_step_key),
            'when'      => optional($r->updated_at)->diffForHumans(null, true),
            'badge'     => 'Returned',
            'badgeTone' => 'review',
            'href'      => route('cadastral-module.reports.show', $r),
        ])->all(),
    ])
</div>
