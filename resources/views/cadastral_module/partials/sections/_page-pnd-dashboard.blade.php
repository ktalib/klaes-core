{{-- 4.4 Plan and Description — area, pillars, fees. --}}
@include('cadastral_module.partials._flash')

<div class="cad-viz">
    @include('cadastral_module.partials._unit_nav', ['unit' => 'pnd'])

    <div class="cad-actions">
        @canDo('Cad - Records', 'create')
            <a href="{{ route('cadastral-module.plan-description.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> New Record
            </a>
        @endcanDo
        <a href="{{ route('cadastral-module.plan-description.index') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-list"></i> All Records
        </a>
    </div>

    @include('cadastral_module.partials._hero', ['hero' => $m['hero'], 'tiles' => $m['tiles']])

    <div class="cad-grid-3">
        @include('cadastral_module.partials.charts._columns', [
            'series'   => $m['billed'],
            'title'    => 'Bills issued',
            'subtitle' => 'Consolidated cadastral fee notes issued per day.',
        ])

        <div style="display:flex;flex-direction:column;gap:14px;">
            {{-- What the money is made of. Categorical is right here: the four
                 fee lines ARE the subject, and their slot order is fixed so a
                 quiet month never repaints the legend. --}}
            @include('cadastral_module.partials.charts._stacked', [
                'parts'     => $m['feeMix'],
                'title'     => 'What the billing is made of',
                'subtitle'  => 'Across every bill not cancelled. Pillars are charged at the same rate whether government or private.',
                'dimension' => 'Fee line',
                'money'     => true,
                'empty'     => 'No bills issued yet.',
            ])

            @include('cadastral_module.partials.charts._hbars', [
                'rows'      => $m['byZone'],
                'title'     => 'Records by location zone',
                'subtitle'  => 'The zone multiplier applied to the area charge.',
                'dimension' => 'Zone',
                'empty'     => 'No zone recorded yet.',
                'labelW'    => 120,
            ])
        </div>
    </div>

    <div class="cad-grid-2">
        @include('cadastral_module.partials.charts._hbars', [
            'rows'      => $m['byUse'],
            'title'     => 'Records by land use',
            'subtitle'  => 'Land use sets the multiplier on the area charge.',
            'dimension' => 'Land use',
            'empty'     => 'No land use recorded yet.',
        ])

        @include('cadastral_module.partials.charts._hbars', [
            'rows'      => $m['areaMix'],
            'title'     => 'Parcels by size',
            'subtitle'  => 'Bands in ascending order, so this reads as a distribution rather than a ranking.',
            'dimension' => 'Size band',
            'empty'     => 'No areas recorded yet.',
            'labelW'    => 140,
        ])
    </div>

    <div class="cad-grid-2">
        @include('cadastral_module.partials._queue', [
            'title'    => 'Ready to bill',
            'subtitle' => 'Area and pillars recorded, no bill issued yet.',
            'more'     => route('cadastral-module.plan-description.index'),
            'empty'    => 'Every record has been billed.',
            'rows'     => collect($m['queues']['unbilled'])->map(fn ($p) => [
                'primary'   => $p->pd_ref . ' · ' . $p->file_number,
                'secondary' => ($p->land_use ? $p->land_use . ' · ' : '')
                                . ($p->area_sqm !== null ? number_format($p->area_sqm, 2) . ' sqm' : 'no area recorded'),
                'when'      => optional($p->created_at)->diffForHumans(null, true),
                'href'      => route('cadastral-module.plan-description.edit', $p),
            ])->all(),
        ])

        @include('cadastral_module.partials._queue', [
            'title'    => 'No area recorded',
            'subtitle' => 'Nothing can be billed until an area exists — from the chart\'s beacon ring, or entered by hand.',
            'empty'    => 'Every record has an area.',
            'rows'     => collect($m['queues']['noArea'])->map(fn ($p) => [
                'primary'   => $p->pd_ref . ' · ' . $p->file_number,
                'secondary' => $p->property_location ?: 'no location recorded',
                'when'      => optional($p->created_at)->diffForHumans(null, true),
                'badge'     => 'No area',
                'badgeTone' => 'pending',
                'href'      => route('cadastral-module.plan-description.edit', $p),
            ])->all(),
        ])
    </div>

    @include('cadastral_module.partials._queue', [
        'title'    => 'Description disagrees with the chart',
        'subtitle' => 'The saved description failed its check against the linked chart — usually the area, or a chart for a different file.',
        'empty'    => 'No description contradicts its chart.',
        'rows'     => collect($m['queues']['failedChecks'])->map(fn ($p) => [
            'primary'   => $p->pd_ref . ' · ' . $p->file_number,
            'secondary' => Str::limit($p->validation_notes, 70) ?: 'No note recorded',
            'badge'     => 'Failed',
            'badgeTone' => 'rejected',
            'href'      => route('cadastral-module.plan-description.edit', $p),
        ])->all(),
    ])
</div>
