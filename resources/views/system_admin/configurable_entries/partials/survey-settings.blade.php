{{-- Survey settings (CR 30102026, user 2026-10-31), saved by SystemAdmin\SurveySettingsController and read
     back by App\Services\Survey\SurveySettings:
       Sharing Ratios  - item 11: the general ratio is the default (50:50); a project picks one when it is set up.
       Classmark       - item 8: turns the part plot of the creatable plots into full / half / quarter plots.
       Land Rates      - item 5: the rate per hectare / m2 that fills a land line in the Monetary Compensation calculator.
       Beacon Prefixes - Issue Beacon No on the Beacon Register. --}}
@php
    use App\Http\Controllers\SystemAdmin\SurveySettingsController as SS;
    use App\Services\Survey\SurveySettings as Settings;

    $fmtFrac = fn ($v) => (float) $v >= 1 ? '1.00' : '.' . str_pad((string) round((float) $v * 100), 2, '0', STR_PAD_LEFT);
    $cards = [
        'ratios' => ['title' => 'Sharing Ratios', 'icon' => 'scale', 'tone' => 'blue', 'rows' => $svRatios,
            'sub' => 'Farmer : Government plots on Land to Land. The default is the general ratio; a project picks one when it is set up, and only its own cases use it.',
            'cols' => ['Ratio', 'Farmer', 'Government', 'Default', 'Status']],
        'classmarks' => ['title' => 'Classmark', 'icon' => 'ruler', 'tone' => 'green', 'rows' => $svClassmarks,
            'sub' => 'Reads the part plot of the creatable plots (e.g. 1.49 → 1½). Our first ranges until Survey gives theirs; replace them here.',
            'cols' => ['Part plot from', 'to', 'Gives', 'Status']],
        'land-rates' => ['title' => 'Land Rates', 'icon' => 'map', 'tone' => 'yellow', 'rows' => $svLandRates,
            'sub' => 'Fills each land line in the Monetary Compensation calculator (rate × land size); the officer can still change it on the line.',
            'cols' => ['Name', 'Rate', 'Unit', 'Default', 'Status']],
        'beacon-prefixes' => ['title' => 'Beacon Prefixes', 'icon' => 'map-pin', 'tone' => 'red', 'rows' => $svPrefixes,
            'sub' => 'Offered when a beacon number is issued on the Beacon Register, for its beacon type.',
            'cols' => ['Prefix', 'Beacon Type', 'Description', 'Status']],
        'survey-types' => ['title' => 'Survey Types', 'icon' => 'compass', 'tone' => 'blue', 'rows' => $svTypes ?? collect(),
            'sub' => 'Offered on the Instruction to Survey, in Survey and Cadastral.',
            'cols' => ['Survey Type', 'Status']],
    ];
    // Hidden for now (user, 2026-11-01): the Monetary calculator values land by each crop's / tree's cost of
    // production per hectare (Kano schedule), so this list is unused. Rows, routes and saving are kept.
    unset($cards['land-rates']);
@endphp

@unless($svInstalled)
    <div class="iw-card" style="padding:18px 22px">The Survey settings tables have not been created yet.</div>
@else
<div class="grid gap-5" style="grid-template-columns:repeat(auto-fit,minmax(520px,1fr))"
    x-data="{ editing: null, menu: null, menuTop: 0, menuLeft: 0,
        openMenu(id, el) { const r = el.getBoundingClientRect(); this.menuTop = r.bottom + 4; this.menuLeft = Math.max(8, r.right - 210); this.menu = this.menu === id ? null : id; } }"
    @keydown.escape.window="editing = null; menu = null" @scroll.window="menu = null" @resize.window="menu = null">

    @foreach($cards as $list => $card)
        <div class="iw-card">
            <div class="iw-card-head" style="align-items:center">
                <div>
                    <div class="iw-card-title"><span class="iw-icon-tile {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i></span> {{ $card['title'] }}</div>
                    <div class="iw-card-sub">{{ $card['sub'] }}</div>
                </div>
                <button type="button" class="iw-btn iw-btn-primary iw-btn-sm" @click="editing = '{{ $list }}:new'"><i data-lucide="plus" class="h-4 w-4"></i> Add</button>
            </div>
            <div class="overflow-x-auto">
                <table class="ce-table">
                    <thead><tr><th style="width:50px">S/N</th>@foreach($card['cols'] as $c)<th>{{ $c }}</th>@endforeach<th style="width:50px"></th></tr></thead>
                    <tbody>
                        @forelse($card['rows'] as $row)
                            <tr>
                                <td class="ce-muted">{{ $loop->iteration }}</td>
                                @switch($list)
                                    @case('ratios')
                                        <td class="font-medium text-gray-900">{{ $row->label }}</td>
                                        <td>{{ $row->farmer_pct }}%</td>
                                        <td>{{ $row->govt_pct }}%</td>
                                        <td>@if($row->is_default)<span class="ce-pill blue">General</span>@endif</td>
                                        @break
                                    @case('classmarks')
                                        <td>{{ $fmtFrac($row->from_fraction) }}</td>
                                        <td>{{ $fmtFrac($row->to_fraction) }}</td>
                                        <td class="font-medium text-gray-900">{{ SS::CLASSMARK_PLOTS[rtrim(rtrim(number_format((float) $row->plots, 2, '.', ''), '0'), '.')] ?? $row->plots }}</td>
                                        @break
                                    @case('land-rates')
                                        <td class="font-medium text-gray-900">{{ $row->name }}</td>
                                        <td>₦{{ number_format((float) $row->rate, 2) }}</td>
                                        <td>{{ Settings::LAND_UNITS[$row->unit] ?? $row->unit }}</td>
                                        <td>@if($row->is_default)<span class="ce-pill blue">Default</span>@endif</td>
                                        @break
                                    @case('beacon-prefixes')
                                        <td class="font-medium text-gray-900" style="font-family:monospace">{{ $row->prefix }}</td>
                                        <td>{{ ($row->beacon_type ?? null) ?: 'Both' }}</td>
                                        <td>{{ $row->description ?: '—' }}</td>
                                        @break
                                    @case('survey-types')
                                        <td class="font-medium text-gray-900">{{ $row->name }}</td>
                                        @break
                                @endswitch
                                <td>@if($row->is_active)<span class="ce-pill green">Active</span>@else<span class="ce-pill">Off</span>@endif</td>
                                <td class="text-right">
                                    <button type="button" class="iw-btn iw-btn-light iw-btn-sm" aria-label="Actions" aria-haspopup="menu"
                                        @click.stop="openMenu('{{ $list }}:{{ $row->id }}', $el)">
                                        <i data-lucide="more-vertical" class="h-4 w-4"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($card['cols']) + 2 }}" class="ce-muted text-center" style="padding:20px">Nothing yet. Use Add.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    {{-- Row menus, fixed-positioned so the tables' overflow cannot clip them. --}}
    @foreach($cards as $list => $card)
        @foreach($card['rows'] as $row)
            <div x-show="menu === '{{ $list }}:{{ $row->id }}'" x-cloak role="menu" @click.outside="menu = null"
                :style="'position:fixed;z-index:60;top:' + menuTop + 'px;left:' + menuLeft + 'px;width:210px'"
                class="bg-white border border-gray-200 rounded-lg shadow-lg py-1 text-sm">
                <button type="button" role="menuitem" class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50" @click="menu = null; editing = '{{ $list }}:{{ $row->id }}'">
                    <i data-lucide="pencil" class="h-4 w-4 text-gray-500"></i> Edit
                </button>
                @unless(!empty($row->is_default))
                    <form method="POST" action="{{ route('configurable-entries.survey.toggle', [$list, $row->id]) }}">
                        @csrf
                        <button role="menuitem" class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50" @click="menu = null">
                            @if($row->is_active)<i data-lucide="toggle-left" class="h-4 w-4 text-gray-500"></i> Switch off
                            @else<i data-lucide="toggle-right" class="h-4 w-4 text-green-600"></i> Switch on @endif
                        </button>
                    </form>
                    <form method="POST" action="{{ route('configurable-entries.survey.delete', [$list, $row->id]) }}" onsubmit="return confirm('Delete this entry? It will no longer be offered anywhere.')">
                        @csrf
                        <button role="menuitem" class="w-full flex items-center gap-2 px-3 py-2 text-left text-red-700 hover:bg-red-50" @click="menu = null">
                            <i data-lucide="trash-2" class="h-4 w-4"></i> Delete
                        </button>
                    </form>
                @else
                    <div class="flex items-center gap-2 px-3 py-2 text-gray-400 cursor-not-allowed" title="Make another entry the default first">
                        <i data-lucide="lock" class="h-4 w-4"></i> Default: stays on
                    </div>
                @endunless
            </div>
        @endforeach
    @endforeach

    {{-- Add / edit forms, one per row plus one blank per list. --}}
    @foreach($cards as $list => $card)
        @foreach($card['rows']->concat([null]) as $row)
            @php $key = $list . ':' . ($row ? $row->id : 'new'); @endphp
            <div class="iwr-backdrop" x-show="editing === '{{ $key }}'" x-cloak x-transition.opacity @click.self="editing = null">
                <form method="POST" action="{{ $row ? route('configurable-entries.survey.update', [$list, $row->id]) : route('configurable-entries.survey.store', $list) }}" class="iwr-modal tone-{{ $card['tone'] }}">
                    @csrf
                    <div class="iwr-head"><span class="iw-icon-tile {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i></span><div class="flex-1"><div class="iwt-now-label">Survey · {{ $card['title'] }}</div><div class="iwr-title">{{ $row ? 'Edit' : 'Add' }}</div></div><button type="button" class="iwr-close" @click="editing = null">✕</button></div>
                    <div class="iwr-body space-y-4">
                        @switch($list)
                            @case('ratios')
                                <div class="iw-field" x-data="{ f: {{ (int) ($row->farmer_pct ?? 50) }} }">
                                    <label class="iw-label">Farmer's share (%) <span class="req">*</span></label>
                                    <input type="number" name="farmer_pct" min="1" max="99" required class="iw-input" x-model.number="f">
                                    <p class="ce-muted mt-1">Government takes the rest: <strong x-text="(100 - (f || 0)) + '%'"></strong>. The ratio reads <strong x-text="(f || 0) + ':' + (100 - (f || 0))"></strong>.</p>
                                </div>
                                <label class="flex items-center gap-3 text-sm"><input type="hidden" name="is_default" value="0"><span class="ce-switch"><input type="checkbox" name="is_default" value="1" @checked($row?->is_default)><span></span></span> The general ratio (used when a project picks none)</label>
                                @break
                            @case('classmarks')
                                <div class="iw-grid" style="gap:12px">
                                    <div class="iw-field"><label class="iw-label">Part plot from <span class="req">*</span></label><input type="number" step="0.01" min="0" max="1" name="from_fraction" value="{{ $row ? number_format((float) $row->from_fraction, 2) : '' }}" required class="iw-input" placeholder="e.g. 0.10"></div>
                                    <div class="iw-field"><label class="iw-label">to <span class="req">*</span></label><input type="number" step="0.01" min="0" max="1" name="to_fraction" value="{{ $row ? number_format((float) $row->to_fraction, 2) : '' }}" required class="iw-input" placeholder="e.g. 0.24"></div>
                                </div>
                                <div class="iw-field"><label class="iw-label">Gives <span class="req">*</span></label>
                                    <select name="plots" required class="iw-input">
                                        @foreach(SS::CLASSMARK_PLOTS as $v => $label)
                                            <option value="{{ $v }}" @selected($row && abs((float) $row->plots - (float) $v) < 0.001)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <p class="ce-muted">The part plot is the decimal after the whole plots, read to two places: 1.49 has .49. Ranges may not overlap.</p>
                                @break
                            @case('land-rates')
                                <div class="iw-field"><label class="iw-label">Name <span class="req">*</span></label><input type="text" name="name" value="{{ $row?->name }}" maxlength="100" required class="iw-input" placeholder="e.g. Farmland"></div>
                                <div class="iw-grid" style="gap:12px">
                                    <div class="iw-field"><label class="iw-label">Rate (₦) <span class="req">*</span></label><input type="number" step="0.01" min="0.01" name="rate" value="{{ $row ? (float) $row->rate : '' }}" required class="iw-input" placeholder="e.g. 1000000"></div>
                                    <div class="iw-field"><label class="iw-label">Unit <span class="req">*</span></label>
                                        <select name="unit" required class="iw-input">
                                            @foreach(Settings::LAND_UNITS as $v => $label)<option value="{{ $v }}" @selected(($row->unit ?? 'hectare') === $v)>{{ $label }}</option>@endforeach
                                        </select>
                                    </div>
                                </div>
                                <label class="flex items-center gap-3 text-sm"><input type="hidden" name="is_default" value="0"><span class="ce-switch"><input type="checkbox" name="is_default" value="1" @checked($row?->is_default)><span></span></span> Fill new land lines with this rate</label>
                                @break
                            @case('beacon-prefixes')
                                <div class="iw-field"><label class="iw-label">Prefix <span class="req">*</span></label><input type="text" name="prefix" value="{{ $row?->prefix }}" maxlength="20" required class="iw-input" style="text-transform:uppercase" placeholder="e.g. PBB"></div>
                                <div class="iw-field"><label class="iw-label">Beacon Type</label>
                                    <select name="beacon_type" class="iw-input">
                                        <option value="">Both (Public and Private)</option>
                                        @foreach (\App\Models\Survey\SurveyBeaconRegister::LAYOUT_TYPES as $btKey => $btLabel)
                                            <option value="{{ $btKey }}" @selected(($row?->beacon_type ?? null) === $btKey)>{{ $btLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="iw-field"><label class="iw-label">Description</label><input type="text" name="description" value="{{ $row?->description }}" maxlength="255" class="iw-input" placeholder="Optional"></div>
                                @break
                            @case('survey-types')
                                <div class="iw-field"><label class="iw-label">Survey Type <span class="req">*</span></label><input type="text" name="name" value="{{ $row?->name }}" maxlength="100" required class="iw-input"></div>
                                @break
                        @endswitch
                    </div>
                    <div class="iwr-foot"><span class="ce-muted"></span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = null">Cancel</button><button class="iw-btn iw-btn-success iw-btn-sm">Save</button></div></div>
                </form>
            </div>
        @endforeach
    @endforeach
</div>
@endunless
