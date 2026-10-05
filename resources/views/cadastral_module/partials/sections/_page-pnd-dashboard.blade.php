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
            {{-- What the money is made of: the fee sheet's eight lines, in sheet
                 order. Eight is past what a stacked bar can colour apart, so
                 each line is its own bar in one colour; the order is fixed so
                 a quiet month never reshuffles it. --}}
            @php
                $mix      = collect($m['feeMix']['segments'] ?? []);
                $mixTotal = (int) ($m['feeMix']['total'] ?? 0);
                $mixMax   = max(1, (int) $mix->max('value'));
                $mixRowH  = 26;
                $mixLabel = 150;
                $mixTrack = 620 - $mixLabel - 86;
            @endphp
            <div class="cad-card" id="pnd-fee-mix">
                <div class="cad-card-head">
                    <h3>What the billing is made of</h3>
                    <span style="font-size:11.5px;color:var(--viz-muted);">₦{{ number_format($mixTotal) }} total</span>
                </div>
                <p class="cad-card-sub">Issued and paid bills, line by line as the official fee sheet prints them.</p>

                @if ($mixTotal === 0)
                    <div class="cad-empty">
                        <i class="fas fa-chart-bar"></i>
                        <span>No bills issued yet.</span>
                    </div>
                @else
                    @php $mixH = $mix->count() * $mixRowH + 6; @endphp
                    <svg class="cad-plot" viewBox="0 0 640 {{ $mixH }}" role="img"
                         aria-label="Billing by fee line" style="height:{{ $mixH }}px;">
                        @foreach ($mix as $i => $s)
                            @php
                                $y  = $i * $mixRowH;
                                $bw = $s['value'] > 0 ? max(3, ($s['value'] / $mixMax) * $mixTrack) : 0;
                            @endphp
                            <text class="clabel" x="0" y="{{ $y + 16 }}">{{ Str::limit($s['label'], 24) }}<title>{{ $s['label'] }}</title></text>
                            <rect x="{{ $mixLabel }}" y="{{ $y + 6 }}" width="{{ $mixTrack }}" height="12" rx="4" fill="var(--viz-plane)" />
                            @if ($bw > 0)
                                <rect class="mark" x="{{ $mixLabel }}" y="{{ $y + 6 }}" width="{{ round($bw, 2) }}" height="12" rx="4" fill="var(--viz-seq)">
                                    <title>{{ $s['label'] }} — ₦{{ number_format($s['value']) }} ({{ $s['percent'] }}%)</title>
                                </rect>
                                <rect x="{{ $mixLabel }}" y="{{ $y + 6 }}" width="{{ round(min(4, $bw), 2) }}" height="12" fill="var(--viz-seq)" pointer-events="none" />
                            @endif
                            <text class="vlabel" x="{{ 620 - 80 }}" y="{{ $y + 16 }}">₦{{ number_format($s['value']) }}</text>
                        @endforeach
                    </svg>

                    <details class="cad-tableview">
                        <summary>Table view</summary>
                        <table>
                            <thead><tr><th>Fee line</th><th class="num">Amount</th><th class="num">Share</th></tr></thead>
                            <tbody>
                                @foreach ($mix as $s)
                                    <tr>
                                        <td>{{ $s['label'] }}</td>
                                        <td class="num">₦{{ number_format($s['value']) }}</td>
                                        <td class="num">{{ $s['percent'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @endif
            </div>

            @include('cadastral_module.partials.charts._hbars', [
                'rows'      => $m['byZone'],
                'title'     => 'Records by location zone',
                'subtitle'  => 'Urban, semi-urban or rural, as recorded on each file.',
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
            'subtitle'  => 'Read from the file when the record starts.',
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
