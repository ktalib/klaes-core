@php
    use App\Models\InstrumentWorkflow\InstrumentFeeMapping;
    $money = fn ($v) => '₦' . number_format((float) $v, 2);
@endphp

<div class="ce-tiles">
    @foreach([
        ['Revenue items', $stats['total'], 'list', 'blue'],
        ['Active', $stats['active'], 'check-circle', 'green'],
        ['With a rate above ₦0', $stats['rated'], 'banknote', 'green'],
        ['Revenue heads', $stats['categories'], 'layers', 'gray'],
    ] as [$label, $value, $icon, $tone])
        <div class="iw-card"><div class="iw-card-body flex items-center gap-3" style="padding:16px 18px">
            <span class="iw-icon-tile {{ $tone }}"><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
            <div><div class="text-2xl font-bold text-gray-900 leading-none">{{ number_format($value) }}</div><div class="text-sm text-gray-500 mt-1">{{ $label }}</div></div>
        </div></div>
    @endforeach
</div>

{{-- ============ Register ============ --}}
<div class="iw-card" x-data="{ editing: null, adding: false }" @keydown.escape.window="editing = null; adding = false">
    <div class="iw-card-head" style="align-items:center;flex-wrap:wrap">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile green"><i data-lucide="banknote" class="h-5 w-5"></i></span> Revenue Items</div>
            <div class="iw-card-sub">The Revenue Sub-Heads with their state Revenue Codes and base rates. Changing a rate affects new bills only; issued bills keep their amounts.</div>
        </div>
        <button type="button" class="iw-btn iw-btn-primary iw-btn-sm" @click="adding = true"><i data-lucide="plus" class="h-4 w-4"></i> Add revenue item</button>
    </div>

    <form method="GET" class="flex flex-wrap gap-2 items-center" style="padding:12px 22px;border-bottom:1px solid #f3f4f6">
        <input type="hidden" name="tab" value="revenue">
        <div class="iw-input-group" style="flex:1;min-width:240px">
            <i data-lucide="search"></i>
            <input type="text" name="q" value="{{ $search }}" class="iw-input" placeholder="Name or Revenue Code">
        </div>
        <select name="category" class="ce-select" style="width:auto;min-width:220px" onchange="this.form.submit()">
            <option value="">All revenue heads</option>
            @foreach($categories as $head)<option value="{{ $head }}" @selected($category === $head)>{{ $head }}</option>@endforeach
        </select>
        <select name="status" class="ce-select" style="width:auto" onchange="this.form.submit()">
            <option value="">Any status</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Switched off</option>
            <option value="unrated" @selected($status === 'unrated')>Rate ₦0</option>
        </select>
        <button class="iw-btn iw-btn-light">Search</button>
        @if($search !== '' || $category !== '' || $status !== '')
            <a href="{{ route('configurable-entries.index', ['tab' => 'revenue']) }}" class="text-sm text-blue-600 hover:underline">Clear</a>
        @endif
        <span class="ce-muted" style="margin-left:auto">{{ number_format($items->total()) }} {{ \Illuminate\Support\Str::plural('item', $items->total()) }}</span>
    </form>

    <div class="overflow-x-auto">
        <table class="ce-table">
            <thead><tr><th>Revenue Code</th><th>Revenue item</th><th class="text-right">Base rate</th><th>Status</th><th>Last change</th><th></th></tr></thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td class="iw-mono font-semibold text-gray-900">{{ $item->revenue_code }}</td>
                        <td>
                            <div class="font-medium text-gray-900">{{ $item->shortName() }}</div>
                            <div class="ce-muted">{{ $item->category() }} · Rate ID {{ $item->rate_id ?? '—' }}</div>
                        </td>
                        <td class="text-right font-semibold whitespace-nowrap {{ (float) $item->base_rate > 0 ? 'text-gray-900' : 'text-gray-400' }}">{{ $money($item->base_rate) }}</td>
                        <td>@if($item->is_active)<span class="ce-pill green">Active</span>@else<span class="ce-pill">Off</span>@endif</td>
                        <td class="ce-muted whitespace-nowrap">{{ $item->updated_by_name ?: '—' }}<div>{{ optional($item->updated_at)->format('d M Y, H:i') }}</div></td>
                        <td class="text-right"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = {{ $item->id }}"><i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:40px"><div class="iw-empty">No revenue items match.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($items->hasPages())<div class="iw-card-foot">{{ $items->links() }}</div>@endif

    @foreach($items as $item)
        <div class="iwr-backdrop" x-show="editing === {{ $item->id }}" x-cloak x-transition.opacity @click.self="editing = null">
            <form method="POST" action="{{ route('configurable-entries.revenue.update', $item) }}" class="iwr-modal tone-green" style="max-width:560px">
                @csrf
                <div class="iwr-head">
                    <span class="iw-icon-tile green"><i data-lucide="banknote" class="h-5 w-5"></i></span>
                    <div class="flex-1 min-w-0"><div class="iwt-now-label">Revenue Code {{ $item->revenue_code }}</div><div class="iwr-title">Edit revenue item</div></div>
                    <button type="button" class="iwr-close" @click="editing = null">✕</button>
                </div>
                <div class="iwr-body space-y-4">
                    <div class="iw-field"><label class="iw-label">Name <span class="req">*</span></label><input type="text" name="name" value="{{ $item->name }}" required class="iw-input"></div>
                    <div class="iw-field"><label class="iw-label">Base rate (₦) <span class="req">*</span></label><input type="number" step="0.01" min="0" name="base_rate" value="{{ (float) $item->base_rate }}" required class="iw-input"><span class="iw-help">New bills use this rate. Bills already issued keep their amounts.</span></div>
                    <label class="flex items-center gap-3 text-sm"><input type="hidden" name="is_active" value="0"><span class="ce-switch"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)><span></span></span> Active (can be billed)</label>
                </div>
                <div class="iwr-foot"><span class="ce-muted">The Revenue Code cannot be changed.</span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = null">Cancel</button><button class="iw-btn iw-btn-success iw-btn-sm">Save</button></div></div>
            </form>
        </div>
    @endforeach

    <div class="iwr-backdrop" x-show="adding" x-cloak x-transition.opacity @click.self="adding = false">
        <form method="POST" action="{{ route('configurable-entries.revenue.store') }}" class="iwr-modal tone-green" style="max-width:560px">
            @csrf
            <div class="iwr-head">
                <span class="iw-icon-tile green"><i data-lucide="plus" class="h-5 w-5"></i></span>
                <div class="flex-1"><div class="iwt-now-label">Revenue Items</div><div class="iwr-title">Add revenue item</div></div>
                <button type="button" class="iwr-close" @click="adding = false">✕</button>
            </div>
            <div class="iwr-body space-y-4">
                <div class="iw-field"><label class="iw-label">Revenue Code <span class="req">*</span></label><input type="text" name="revenue_code" required inputmode="numeric" class="iw-input iw-mono" placeholder="e.g. 4000651"></div>
                <div class="iw-field"><label class="iw-label">Name <span class="req">*</span></label><input type="text" name="name" required class="iw-input" placeholder="e.g. Application Fee for Deed of Partition"><span class="iw-help">“Rate of” is added in front if you leave it out, to match the Revenue Sub-Heads.</span></div>
                <div class="iw-field"><label class="iw-label">Base rate (₦) <span class="req">*</span></label><input type="number" step="0.01" min="0" name="base_rate" required class="iw-input" value="0"></div>
            </div>
            <div class="iwr-foot"><span></span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="adding = false">Cancel</button><button class="iw-btn iw-btn-primary iw-btn-sm">Add item</button></div></div>
        </form>
    </div>
</div>

{{-- ============ Instrument Registration fees ============ --}}
<div class="iw-card">
    <div class="iw-card-head">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile blue"><i data-lucide="file-badge" class="h-5 w-5"></i></span> Instrument Registration fees</div>
            <div class="iw-card-sub">
                Which revenue items each instrument type is billed with. The <strong>Application Fee</strong> goes on the application-fee bill (KLAES REV-M);
                the <strong>Registration Fee</strong> and <strong>Approval Fee</strong> go on the registration fee bill, each line carrying its Revenue Code.
                A slot left empty, or an item rated ₦0 or switched off, uses the default tariff instead.
            </div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="ce-table">
            <thead><tr><th style="min-width:180px">Instrument type</th>@foreach(InstrumentFeeMapping::SLOTS as $slot)<th style="min-width:250px">{{ $slot['label'] }} <span class="normal-case font-normal text-gray-400">· {{ $slot['bill'] }}</span></th>@endforeach<th></th></tr></thead>
            <tbody>
                @foreach($instrumentTypes as $i => $type)
                    @php $mapping = $mappings[$type] ?? null; $formId = 'fee-map-' . $i; @endphp
                    <tr>
                        <td>
                            <div class="font-medium text-gray-900">{{ $type }}</div>
                            <div class="ce-muted">{{ $mapping?->updated_by_name ? 'Set by ' . $mapping->updated_by_name : 'Not set' }}</div>
                            <form id="{{ $formId }}" method="POST" action="{{ route('configurable-entries.fee-mappings.save') }}">@csrf<input type="hidden" name="instrument_type" value="{{ $type }}"></form>
                        </td>
                        @foreach(InstrumentFeeMapping::SLOTS as $slotKey => $slot)
                            @php $current = $mapping?->{$slot['column']}; $currentItem = $mapping?->{$slotKey . 'Item'}; @endphp
                            <td>
                                <select name="{{ $slot['column'] }}" form="{{ $formId }}" class="ce-select">
                                    <option value="">— default tariff —</option>
                                    @foreach($slotOptions[$slotKey] as $option)
                                        <option value="{{ $option->id }}" @selected((int) $current === (int) $option->id)>{{ $option->revenue_code }} · {{ \Illuminate\Support\Str::after($option->shortName(), ' for ') }} · {{ $money($option->base_rate) }}</option>
                                    @endforeach
                                </select>
                                @if($currentItem)
                                    <div class="ce-muted mt-1">
                                        @if(!$currentItem->is_active)<span class="ce-pill red">Item switched off → default tariff</span>
                                        @elseif((float) $currentItem->base_rate <= 0)<span class="ce-pill yellow">Rated ₦0 → default tariff</span>
                                        @else Bills {{ $money($currentItem->base_rate) }} · code {{ $currentItem->revenue_code }}@endif
                                    </div>
                                @endif
                            </td>
                        @endforeach
                        <td class="text-right"><button form="{{ $formId }}" class="iw-btn iw-btn-success iw-btn-sm">Save</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="iw-card-foot">
        <span class="ce-muted">
            Default tariff (Instrument Registration, always applied for Stamp Duty and Dicing):
            @foreach($fallback as $fee)
                {{ $fee->label }} {{ $fee->calc_type === 'percent_of_consideration' ? rtrim(rtrim(number_format((float) $fee->rate, 2), '0'), '.') . '% of consideration (min ' . $money($fee->min_amount) . ')' : $money($fee->amount) }}@if(!$loop->last) · @endif
            @endforeach
        </span>
    </div>
</div>

