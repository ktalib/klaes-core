@php
    $money = fn ($v) => $v === null ? '—' : '₦' . number_format((float) $v, 2);
    $factor = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.') ?: '0';
    $mMissing = $occupancy->where('is_active', true)->whereNull('rate')->count();
    // The M and PCR cards are hidden until the Ministry supplies those rates (config/land_charges.php).
    $showLucParameters = (bool) config('land_charges.show_luc_parameters', false);
    // Tax Zones offered to a district: the zones of its own LGA (districts.tax_zone_id → tax_zones).
    $zonesByLga = $taxZones->groupBy(fn ($z) => mb_strtolower($z->lga));
@endphp

{{-- Formulas --}}
<div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(420px, 1fr))">
    <div class="iw-card"><div class="iw-card-body">
        <div class="iw-summary-label">Annual Ground Rent</div>
        <div class="clr-formula">G.Rent = Area × Land use × Location</div>
        <div class="ce-muted">Area in m² · land-use rate in ₦ per m² · the district’s rate as a multiplier. A mixed use adds the rates of each use it combines.</div>
    </div></div>
    <div class="iw-card"><div class="iw-card-body">
        <div class="iw-summary-label">Land Use Charge</div>
        <div class="clr-formula">LUC = M × {(LA × LV) + (BA × BV × PCR)}</div>
        <div class="ce-muted"><strong>M</strong> charge rate (% of assessed value) by occupancy · <strong>LA</strong> land area m² · <strong>LV</strong> land value ₦/m² (district) · <strong>BA</strong> building floor area m² · <strong>BV</strong> building value ₦/m² (district) · <strong>PCR</strong> property code rate.</div>
    </div></div>
</div>

@if($showLucParameters && $mMissing)
    <div class="iw-alert warn"><i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5"></i>
        <div>{{ $mMissing }} occupancy {{ \Illuminate\Support\Str::plural('status', $mMissing) }} {{ $mMissing === 1 ? 'has' : 'have' }} no charge rate (M) yet. The LUC calculator refuses a status without one. District land and building values (LV, BV) are also blank until entered below.</div>
    </div>
@endif

{{-- ============ Land-use rates ============ --}}
<div class="iw-card" x-data="{ editing: null }" @keydown.escape.window="editing = null">
    <div class="iw-card-head" style="align-items:center">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile green"><i data-lucide="layers" class="h-5 w-5"></i></span> Land-use rates (Ground Rent)</div>
            <div class="iw-card-sub">₦ per m² for a standard plot of each land use.</div>
        </div>
        <button type="button" class="iw-btn iw-btn-primary iw-btn-sm" @click="editing = 'new'"><i data-lucide="plus" class="h-4 w-4"></i> Add land use</button>
    </div>
    <div class="overflow-x-auto">
        <table class="ce-table">
            <thead><tr><th style="width:60px">#</th><th>Land use</th><th class="text-right">Rate per m²</th><th>Status</th><th>Last change</th><th></th></tr></thead>
            <tbody>
                @foreach($landUses as $use)
                    <tr>
                        <td class="ce-muted">{{ $use->sort_order }}</td>
                        <td class="font-medium text-gray-900">{{ $use->name }}</td>
                        <td class="text-right font-semibold">{{ $money($use->ground_rent_rate) }}</td>
                        <td>@if($use->is_active)<span class="ce-pill green">Active</span>@else<span class="ce-pill">Off</span>@endif</td>
                        <td class="ce-muted">{{ $use->updated_by_name ?: '—' }}</td>
                        <td class="text-right"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = {{ $use->id }}"><i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit</button></td>
                    </tr>
                @endforeach
                <tr><td></td><td colspan="5" class="ce-muted"><strong>Mixed</strong> — select several uses in the Ground Rent calculator; their rates are added together.</td></tr>
            </tbody>
        </table>
    </div>

    @foreach($landUses->push(null) as $use)
        <div class="iwr-backdrop" x-show="editing === {{ $use ? $use->id : "'new'" }}" x-cloak x-transition.opacity @click.self="editing = null">
            <form method="POST" action="{{ $use ? route('configurable-entries.land-uses.update', $use->id) : route('configurable-entries.land-uses.store') }}" class="iwr-modal tone-green">
                @csrf
                <div class="iwr-head"><span class="iw-icon-tile green"><i data-lucide="layers" class="h-5 w-5"></i></span><div class="flex-1"><div class="iwt-now-label">Ground Rent</div><div class="iwr-title">{{ $use ? 'Edit land use' : 'Add land use' }}</div></div><button type="button" class="iwr-close" @click="editing = null">✕</button></div>
                <div class="iwr-body space-y-4">
                    <div class="iw-field"><label class="iw-label">Land use <span class="req">*</span></label><input type="text" name="name" value="{{ $use?->name }}" required class="iw-input"></div>
                    <div class="iw-grid" style="gap:12px">
                        <div class="iw-field"><label class="iw-label">Rate per m² (₦) <span class="req">*</span></label><input type="number" step="0.01" min="0" name="ground_rent_rate" value="{{ $use ? (float) $use->ground_rent_rate : '' }}" required class="iw-input"></div>
                        <div class="iw-field"><label class="iw-label">Display order</label><input type="number" min="0" name="sort_order" value="{{ $use?->sort_order }}" class="iw-input"></div>
                    </div>
                    @if($use)<label class="flex items-center gap-3 text-sm"><input type="hidden" name="is_active" value="0"><span class="ce-switch"><input type="checkbox" name="is_active" value="1" @checked($use->is_active)><span></span></span> Offered in the calculator</label>@endif
                </div>
                <div class="iwr-foot"><span class="ce-muted">Bills already generated keep their amounts.</span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = null">Cancel</button><button class="iw-btn iw-btn-success iw-btn-sm">Save</button></div></div>
            </form>
        </div>
    @endforeach
</div>

{{-- ============ District rates ============ --}}
<div class="iw-card">
    <div class="iw-card-head" style="align-items:center;flex-wrap:wrap">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile blue"><i data-lucide="map-pin" class="h-5 w-5"></i></span> District rates</div>
            <div class="iw-card-sub">
                {{ number_format($districtStats['total']) }} districts from Districts (1).xlsx ·
                {{ $districtStats['with_zone'] }} with a Tax Zone ·
                {{ $districtStats['custom_rate'] }} with a rate other than 1 ·
                {{ $districtStats['with_lv'] }} with LV · {{ $districtStats['with_bv'] }} with BV.
                The rate multiplies Ground Rent; LV and BV feed the Land Use Charge.
            </div>
        </div>
    </div>
    <form method="GET" class="flex flex-wrap gap-2 items-center" style="padding:12px 22px;border-bottom:1px solid #f3f4f6">
        <input type="hidden" name="tab" value="land-rates">
        <div class="iw-input-group" style="flex:1;min-width:220px"><i data-lucide="search"></i><input type="text" name="q" value="{{ $search }}" class="iw-input" placeholder="District, CAD zone or CAD name"></div>
        <select name="lga" class="ce-select" style="width:auto" onchange="this.form.submit()"><option value="">All LGAs</option>@foreach($districtLgas as $option)<option value="{{ $option }}" @selected($lga === $option)>{{ $option }}</option>@endforeach</select>
        <select name="category" class="ce-select" style="width:auto" onchange="this.form.submit()"><option value="">All categories</option>@foreach($districtCategories as $option)<option value="{{ $option }}" @selected($category === $option)>{{ $option }}</option>@endforeach</select>
        <select name="zone" class="ce-select" style="width:auto" onchange="this.form.submit()">
            <option value="">All Tax Zones</option>
            <option value="none" @selected($zone === 'none')>No Tax Zone</option>
            @foreach($taxZones->groupBy('lga') as $zoneLga => $group)
                <optgroup label="{{ $zoneLga }}">@foreach($group as $option)<option value="{{ $option->id }}" @selected($zone === (string) $option->id)>Zone {{ $option->code }} · {{ $zoneLga }}</option>@endforeach</optgroup>
            @endforeach
        </select>
        <button class="iw-btn iw-btn-light">Search</button>
    </form>
    <div class="overflow-x-auto">
        <table class="ce-table">
            <thead><tr><th>District</th><th>LGA</th><th>Category · type</th><th style="width:170px">Tax Zone</th><th style="width:120px">Rate ×</th><th style="width:160px">LV ₦/m²</th><th style="width:160px">BV ₦/m²</th><th></th></tr></thead>
            <tbody>
                @forelse($districts as $district)
                    @php $formId = 'district-rate-' . $district->id; @endphp
                    <tr>
                        <td>
                            <div class="font-medium text-gray-900">{{ $district->name }}</div>
                            <div class="ce-muted iw-mono">{{ $district->cad_zone ?: '—' }}{{ $district->cad_name && $district->cad_name !== $district->name ? ' · ' . $district->cad_name : '' }}</div>
                            <form id="{{ $formId }}" method="POST" action="{{ route('configurable-entries.districts.rates', $district->id) }}">@csrf</form>
                        </td>
                        <td>{{ $district->lga ?: '—' }}</td>
                        <td>{{ $district->category ?: '—' }}<div class="ce-muted">{{ $district->district_type ?: '' }}</div></td>
                        <td>
                            @php $lgaZones = $zonesByLga->get(mb_strtolower((string) $district->lga), collect()); @endphp
                            <select form="{{ $formId }}" name="tax_zone_id" class="ce-select" style="padding:6px 8px">
                                <option value="">— none —</option>
                                @foreach($lgaZones as $option)
                                    <option value="{{ $option->id }}" @selected((int) $district->tax_zone_id === (int) $option->id)>Zone {{ $option->code }}{{ $option->amount !== null ? ' · ₦' . number_format((float) $option->amount) : '' }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input form="{{ $formId }}" type="number" step="0.0001" min="0" name="rate_multiplier" value="{{ $factor($district->rate_multiplier) }}" required class="iw-input" style="padding:6px 8px"></td>
                        <td><input form="{{ $formId }}" type="number" step="0.01" min="0" name="luc_land_value" value="{{ $district->luc_land_value !== null ? (float) $district->luc_land_value : '' }}" class="iw-input" style="padding:6px 8px" placeholder="not set"></td>
                        <td><input form="{{ $formId }}" type="number" step="0.01" min="0" name="luc_building_value" value="{{ $district->luc_building_value !== null ? (float) $district->luc_building_value : '' }}" class="iw-input" style="padding:6px 8px" placeholder="not set"></td>
                        <td class="text-right"><button form="{{ $formId }}" class="iw-btn iw-btn-success iw-btn-sm">Save</button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="padding:32px"><div class="iw-empty">No districts match.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($districts->hasPages())<div class="iw-card-foot">{{ $districts->links() }}</div>@endif
</div>

{{-- ============ Tax Zones ============ --}}
<div class="iw-card">
    <div class="iw-card-head" style="align-items:center">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile orange"><i data-lucide="layers-3" class="h-5 w-5"></i></span> Tax Zones</div>
            <div class="iw-card-sub">From Districts (1).xlsx · {{ $taxZones->count() }} zones across {{ $taxZones->pluck('lga')->unique()->count() }} LGAs. A zone belongs to its LGA; each district references one. The amount is recorded for reference.</div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="ce-table">
            <thead><tr><th>LGA</th><th>Tax Zone</th><th class="text-right">Amount</th><th class="text-right">Districts</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($taxZones as $taxZone)
                    <tr>
                        <td class="text-gray-900">{{ $taxZone->lga }}</td>
                        <td><span class="ce-pill blue" style="font-size:12px">Zone {{ $taxZone->code }}</span></td>
                        <td class="text-right font-semibold">{{ $money($taxZone->amount) }}</td>
                        <td class="text-right">
                            @if($taxZone->districts_count)
                                <a href="{{ route('configurable-entries.index', ['tab' => 'land-rates', 'zone' => $taxZone->id]) }}" class="text-blue-600 hover:underline">{{ number_format($taxZone->districts_count) }}</a>
                            @else
                                <span class="ce-muted">0</span>
                            @endif
                        </td>
                        <td>@if($taxZone->is_active)<span class="ce-pill green">Active</span>@else<span class="ce-pill">Off</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:32px"><div class="iw-empty">No Tax Zones yet. Run <span class="iw-mono">php artisan districts:import</span>.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ============ LUC parameters (hidden for now: land_charges.show_luc_parameters) ============ --}}
@if($showLucParameters)
<div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(460px, 1fr))" x-data="{ editing: null }" @keydown.escape.window="editing = null">
    @foreach([
        ['kind' => 'occupancy', 'title' => 'M — charge rate by occupancy status', 'sub' => 'Annual charge rate as a percentage of the assessed value.', 'rows' => $occupancy, 'unit' => '%'],
        ['kind' => 'property_code', 'title' => 'PCR — property code rates', 'sub' => 'Factor for a building above or below the neighbourhood average and its degree of completion (1 = average, complete).', 'rows' => $propertyCodes, 'unit' => '×'],
    ] as $group)
        <div class="iw-card">
            <div class="iw-card-head" style="align-items:center">
                <div><div class="iw-card-title">{{ $group['title'] }}</div><div class="iw-card-sub">{{ $group['sub'] }}</div></div>
                <button type="button" class="iw-btn iw-btn-primary iw-btn-sm" @click="editing = 'new-{{ $group['kind'] }}'"><i data-lucide="plus" class="h-4 w-4"></i> Add</button>
            </div>
            <table class="ce-table">
                <thead><tr><th>{{ $group['kind'] === 'occupancy' ? 'Occupancy status' : 'Property code' }}</th><th class="text-right">{{ $group['kind'] === 'occupancy' ? 'M' : 'PCR' }}</th><th></th></tr></thead>
                <tbody>
                    @forelse($group['rows'] as $param)
                        <tr>
                            <td><div class="font-medium text-gray-900">{{ $param->label }} @unless($param->is_active)<span class="ce-pill">Off</span>@endunless</div>@if($param->description)<div class="ce-muted">{{ $param->description }}</div>@endif</td>
                            <td class="text-right whitespace-nowrap">@if($param->hasRate())<span class="font-semibold">{{ $group['unit'] === '×' ? '× ' : '' }}{{ $factor($param->rate) }}{{ $group['unit'] === '%' ? '%' : '' }}</span>@else<span class="ce-pill yellow">Not set</span>@endif</td>
                            <td class="text-right"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = 'param-{{ $param->id }}'"><i data-lucide="pencil" class="h-3.5 w-3.5"></i></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="iw-empty">None yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @foreach($group['rows']->concat([null]) as $param)
            <div class="iwr-backdrop" x-show="editing === '{{ $param ? 'param-' . $param->id : 'new-' . $group['kind'] }}'" x-cloak x-transition.opacity @click.self="editing = null">
                <form method="POST" action="{{ $param ? route('configurable-entries.luc-parameters.update', $param->id) : route('configurable-entries.luc-parameters.store') }}" class="iwr-modal tone-blue">
                    @csrf
                    <input type="hidden" name="kind" value="{{ $group['kind'] }}">
                    <div class="iwr-head"><span class="iw-icon-tile blue"><i data-lucide="percent" class="h-5 w-5"></i></span><div class="flex-1"><div class="iwt-now-label">Land Use Charge</div><div class="iwr-title">{{ $param ? 'Edit' : 'Add' }} {{ $group['kind'] === 'occupancy' ? 'charge rate (M)' : 'property code rate (PCR)' }}</div></div><button type="button" class="iwr-close" @click="editing = null">✕</button></div>
                    <div class="iwr-body space-y-4">
                        <div class="iw-field"><label class="iw-label">{{ $group['kind'] === 'occupancy' ? 'Occupancy status' : 'Property code' }} <span class="req">*</span></label><input type="text" name="label" value="{{ $param?->label }}" required class="iw-input"></div>
                        <div class="iw-field"><label class="iw-label">{{ $group['kind'] === 'occupancy' ? 'M — % of assessed value' : 'PCR factor' }}</label><input type="number" step="0.000001" min="0" name="rate" value="{{ $param && $param->hasRate() ? $factor($param->rate) : '' }}" class="iw-input" placeholder="{{ $group['kind'] === 'occupancy' ? 'e.g. 0.5' : 'e.g. 1' }}"><span class="iw-help">Leave blank if not decided; the calculator will not use it until set.</span></div>
                        <div class="iw-field"><label class="iw-label">Description</label><input type="text" name="description" value="{{ $param?->description }}" class="iw-input"></div>
                        @if($param)<label class="flex items-center gap-3 text-sm"><input type="hidden" name="is_active" value="0"><span class="ce-switch"><input type="checkbox" name="is_active" value="1" @checked($param->is_active)><span></span></span> Offered in the calculator</label>@endif
                    </div>
                    <div class="iwr-foot"><span></span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = null">Cancel</button><button class="iw-btn iw-btn-primary iw-btn-sm">Save</button></div></div>
                </form>
            </div>
        @endforeach
    @endforeach
</div>
@endif

<style>
    .clr-formula { font-family: Georgia, 'Times New Roman', serif; font-size: 20px; font-weight: 700; color: #1e3a8a; margin: 6px 0; }
</style>

