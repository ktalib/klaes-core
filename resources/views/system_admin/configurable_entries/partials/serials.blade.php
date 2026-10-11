{{--
    Serial Initialization.

    The starting serial for each file-number generator: the serial the paper
    register had already reached before KLAES began numbering. Every counter
    stays editable so a wrong entry can be corrected — the generator continues
    from it, so a counter set too low re-issues file numbers that already exist
    on files in the registry.

    Registers and their counters come from ConfigurableEntriesController::SERIAL_REGISTERS.
--}}
@php
    $allCounters = $registers->flatMap(fn ($r) => $r->counters);
    $setCount = $allCounters->where('exists', true)->count();
@endphp

<div x-data="{ activeSerialRegister: @js($registers->keys()->first() ?? '') }">
<div class="ce-tiles">
    @foreach([
        ['Registers', $registers->count(), 'library', 'blue'],
        ['Counters set', $setCount, 'check-circle', 'green'],
        ['Not yet set', $allCounters->count() - $setCount, 'pencil', 'orange'],
    ] as [$label, $value, $icon, $tone])
        <div class="iw-card"><div class="iw-card-body flex items-center gap-3" style="padding:16px 18px">
            <span class="iw-icon-tile {{ $tone }}"><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
            <div><div class="text-2xl font-bold text-gray-900 leading-none">{{ number_format($value) }}</div><div class="text-sm text-gray-500 mt-1">{{ $label }}</div></div>
        </div></div>
    @endforeach
</div>

<div class="space-y-5">
    <div class="iw-alert info">
        <i data-lucide="info" class="h-4 w-4 mt-0.5"></i>
        <div>
            Enter the <strong>last serial already used on paper</strong> for the counter — not the next one. The first file number
            the generator mints continues after it. Land and DCIV keep a counter per year, so each new year starts its own;
            Survey keeps one counter for good. A counter that has been used already stands at a live serial — the generator
            has been counting up on it — so its last serial is a number that has been issued, not a blank.
        </div>
    </div>

    <div class="iw-alert danger">
        <i data-lucide="alert-octagon" class="h-4 w-4 mt-0.5"></i>
        <div>
            <strong>Every counter can be edited and saved here.</strong> Take care when lowering one: a counter set below what
            the registry has already issued hands out file numbers that exist on other files. Check the paper register before
            you save — every change is recorded in the audit trail.
        </div>
    </div>

    <div class="ce-subtabs" role="tablist" aria-label="Serial registers">
        @foreach($registers as $register)
            <button type="button"
                    class="ce-subtab"
                    :class="{ 'active': activeSerialRegister === @js($register->key) }"
                    @click="activeSerialRegister = @js($register->key)"
                    role="tab"
                    :aria-selected="activeSerialRegister === @js($register->key)">
                <i data-lucide="{{ $register->icon }}" class="h-4 w-4"></i>
                {{ $register->label }}
                <span class="ce-subtab-count">{{ $register->counters->count() }}</span>
            </button>
        @endforeach
    </div>

    @foreach($registers as $register)
        <div class="iw-card" x-show="activeSerialRegister === @js($register->key)" x-cloak>
            <div class="iw-card-head" style="align-items:center;flex-wrap:wrap">
                <div class="flex items-center gap-3">
                    <span class="iw-icon-tile blue"><i data-lucide="{{ $register->icon }}" class="h-5 w-5"></i></span>
                    <div>
                        <div class="iw-card-title">
                            {{ $register->label }}
                            @if($register->yearly)<span class="ce-pill">{{ $register->year }}</span>@else<span class="ce-pill">All years</span>@endif
                        </div>
                        <div class="iw-card-sub">
                            Used by {{ $register->where }} ·
                            Serials can be edited and saved
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="ce-table">
                    <thead>
                        <tr>
                            <th>{{ $register->key_label }}</th>
                            @if($register->yearly)<th>Year</th>@endif
                            <th class="text-right">Last serial</th>
                            <th>Next file number gets</th>
                            <th>Status</th>
                            <th>Last saved by</th>
                            <th>Last saved at</th>
                            <th class="text-right">Edit last serial</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($register->counters as $counter)
                            <tr>
                                <td><span class="iw-mono font-semibold text-gray-900">{{ $counter->label }}</span></td>
                                @if($register->yearly)<td class="iw-mono">{{ $counter->year }}</td>@endif
                                <td class="text-right">
                                    @if($counter->exists)
                                        <span class="iw-mono font-semibold">{{ number_format($counter->last_serial) }}</span>
                                    @else
                                        <span class="ce-muted">Not set</span>
                                    @endif
                                </td>
                                <td>
                                    @if($counter->exists)
                                        <span class="ce-pill {{ $counter->locked ? 'blue' : '' }} iw-mono">{{ number_format($counter->last_serial + 1) }}</span>
                                    @else
                                        <span class="ce-pill blue iw-mono">1</span>
                                    @endif
                                </td>
                                <td>
                                    @if($register->key === 'deeds')
                                        <span class="ce-pill blue">Editable</span>
                                    @elseif($counter->locked)
                                        <span class="ce-pill green">Set</span>
                                    @elseif($counter->initialized)
                                        <span class="ce-pill yellow">Initialized</span>
                                    @elseif($counter->exists)
                                        <span class="ce-pill yellow">Counting, not set</span>
                                    @else
                                        <span class="ce-pill">Never used</span>
                                    @endif
                                </td>
                                <td class="ce-muted">{{ $counter->initialized_by ?: '—' }}</td>
                                <td class="ce-muted whitespace-nowrap">
                                    {{ $counter->initialized_at ? \Illuminate\Support\Carbon::parse($counter->initialized_at)->format('d M Y, H:i') : '—' }}
                                </td>
                                <td class="text-right">
                                        @php
                                            // A counter already counting is warned about by name: lowering it
                                            // hands out serials the generator has issued once already.
                                            $confirm = 'Save ' . addslashes($counter->label) . ($register->yearly ? ' for ' . $register->year : '')
                                                . ' at the serial entered? The next file number continues after it.'
                                                . ($counter->exists ? '\n\nCurrent last serial: ' . $counter->last_serial . '. A lower value re-issues serials that have already been given out.' : '');
                                        @endphp
                                        <form method="POST" action="{{ route('configurable-entries.serials.initialize', $register->key) }}"
                                              class="inline-flex items-center gap-2 justify-end"
                                              onsubmit="return confirm('{{ $confirm }}')">
                                            @csrf
                                            <input type="hidden" name="name" value="{{ $counter->name }}">
                                            <input type="number" name="last_serial" min="0" step="1" required
                                                   value="{{ $counter->exists ? $counter->last_serial : '' }}"
                                                   placeholder="e.g. 0"
                                                   class="iw-input iw-mono text-right" style="width:104px">
                                            <button class="iw-btn iw-btn-primary iw-btn-sm whitespace-nowrap">
                                                <i data-lucide="save" class="h-3.5 w-3.5"></i> {{ $counter->locked || $register->key === 'deeds' ? 'Save serial' : 'Initialize' }}
                                            </button>
                                        </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $register->yearly ? 8 : 7 }}" style="padding:28px">
                                    <div class="iw-empty">This register has no counters to set.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    @if($registers->isEmpty())
        <div class="iw-card"><div class="iw-card-body"><div class="iw-empty">None of the serial-control tables are installed on this deployment.</div></div></div>
    @endif
</div>
</div>
