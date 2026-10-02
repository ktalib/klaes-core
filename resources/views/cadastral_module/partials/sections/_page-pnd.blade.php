@include('cadastral_module.partials._flash')

<div class="unit-tag"><i class="fas fa-ruler-combined"></i> 4.4 · Plan and Description</div>

<div class="page-header">
    <div></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('cadastral-module.plan-description.descriptions') }}" class="btn btn-outline">
            <i class="fas fa-file-lines"></i> Descriptions
        </a>
        @canDo('Cad - Records', 'create')
            {{-- Records start on Area & Pillars, from a registered intake file. --}}
            <a href="{{ route('cadastral-module.plan-description.area') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Record
            </a>
        @endcanDo
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Records</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">With an Area</div>
        <div class="kpi-value">{{ number_format($stats['with_area']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Bills Issued</div>
        <div class="kpi-value">{{ number_format($stats['billed']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Value Billed</div>
        <div class="kpi-value">&#8358;{{ number_format($stats['billed_sum'], 2) }}</div>
    </div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Ref, file number or title…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:260px;" />

        <select name="land_use" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Land Uses</option>
            @foreach (\App\Models\Cadastral\CadastralPlanDescription::LAND_USES as $u)
                <option value="{{ $u }}" @selected(request('land_use')===$u)>{{ $u }}</option>
            @endforeach
        </select>

        <select name="location_zone" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Zones</option>
            @foreach (\App\Models\Cadastral\CadastralPlanDescription::ZONES as $k => $label)
                <option value="{{ $k }}" @selected(request('location_zone')===$k)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','land_use','location_zone']))
            <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.plan-description.index') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>File Number</th>
                    <th>Location</th>
                    <th>Land Use</th>
                    <th>Zone</th>
                    <th>Area (sqm)</th>
                    <th>Hectares</th>
                    <th>Plots</th>
                    <th>Pillars</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td><strong>{{ $record->pd_ref }}</strong></td>
                        <td>{{ $record->file_number }}</td>
                        {{-- District, LGA, State. --}}
                        <td>{{ $record->property_location ?: '—' }}</td>
                        <td>{{ $record->land_use ?: '—' }}</td>
                        <td>{{ \App\Models\Cadastral\CadastralPlanDescription::ZONES[$record->location_zone] ?? '—' }}</td>
                        <td class="money">{{ $record->area_sqm_display }}</td>
                        <td class="money">{{ $record->area_ha !== null ? number_format($record->area_ha, 4) : '—' }}</td>
                        <td class="money">{{ $record->area_plots !== null ? number_format($record->area_plots, 2) : '—' }}</td>
                        <td>{{ $record->pillars_count }}</td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('cadastral-module.plan-description.area', ['record' => $record->id]) }}" title="Area &amp; Pillars">
                                    <i class="fas fa-ruler-combined"></i>
                                </a>
                                <a href="{{ route('cadastral-module.plan-description.descriptions', ['record' => $record->id]) }}" title="Description">
                                    <i class="fas fa-file-lines"></i>
                                </a>
                                <a href="{{ route('cadastral-module.plan-description.edit', $record) }}#fee-calculator" title="Fee Calculator (full record)">
                                    <i class="fas fa-calculator"></i>
                                </a>
                                @canDo('Cad - Records', 'delete')
                                    <form method="POST" action="{{ route('cadastral-module.plan-description.destroy', $record) }}"
                                          style="display:inline"
                                          onsubmit="return confirm('Delete {{ $record->pd_ref }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endcanDo
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-ruler-combined" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No records yet.
                            @canDo('Cad - Records', 'create')
                                <a href="{{ route('cadastral-module.plan-description.area') }}">Start the first one</a>
                            @endcanDo.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }}
            of {{ number_format($records->total()) }} · hectares, acres and plots are derived from the square-metre figure
        </span>
        <div class="pagination">{{ $records->links() }}</div>
    </div>
</div>
