@php
    $vaults = $vaults ?? collect();
    $liveShared = $liveShared ?? null;
    $unifiedColumns = $unifiedColumns ?? false;
    // Registration registers panel hidden on request (2026-09-17): with the
    // Single Unified Register live, only that one register issues numbers, so
    // the per-type history rows are noise. Flip to true to show the panel again.
    $showRegisters = false;
@endphp
<style>
    .ce-live-bar { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; padding: 14px 22px; border-top: 1px solid #f3f4f6; }
    .ce-live-dot { width: 10px; height: 10px; border-radius: 99px; background: #9ca3af; flex: none; }
    .ce-live-dot.on { background: #16a34a; box-shadow: 0 0 0 4px #dcfce7; }
    .ce-row-live { background: #f0fdf4; }
    .ce-row-live:hover { background: #ecfdf5 !important; }
    .ce-row-idle td { color: #6b7280; }
    .ce-types { font-size: 12px; color: #6b7280; max-width: 320px; }
</style>

{{-- ============ Registers (vaults) ============ --}}
@if($showRegisters)
<div class="iw-card" x-data="{ editingVault: null }" @keydown.escape.window="editingVault = null">
    <div class="iw-card-head">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile blue"><i data-lucide="library" class="h-5 w-5"></i></span> Registration registers</div>
            <div class="iw-card-sub">
                Each register (vault) holds the last particulars it issued. Serial = page; when the serial passes the register’s page limit the volume advances and serial and page restart at 1.
                With the <strong>Single Unified Register</strong> live, every instrument type numbers from that one register.
            </div>
        </div>
    </div>

    <div class="ce-live-bar">
        @if($liveShared)
            <span class="ce-live-dot on"></span>
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-gray-900">Shared mode is ON — every instrument type numbers from <span class="iw-mono">{{ $liveShared }}</span></div>
                <div class="ce-muted">The per-type registers below are kept as history and issue nothing while shared mode is on.</div>
            </div>
        @else
            <span class="ce-live-dot"></span>
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-gray-900">Shared mode is OFF — each instrument type numbers from its own register</div>
                <div class="ce-muted">
                    @if(!$unifiedColumns)
                        The register table has no page limit or shared flag yet: run the 2026_09_15_200000 migration.
                    @elseif(!$unifiedEnabled)
                        Shared mode is switched off in configuration (<span class="iw-mono">INSTRUMENT_UNIFIED_VAULT=false</span>); the Shared flag below has no effect until it is switched back on.
                    @else
                        To go live, run <span class="iw-mono">php artisan deeds:reset-unified-register --activate</span> (it prints the collision report first), or tick Shared on the {{ $unifiedName }}.
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="iw-alert danger" style="margin:0 22px">
        <i data-lucide="alert-octagon" class="h-4 w-4 mt-0.5"></i>
        <div><strong>Editing counters can re-issue registration numbers.</strong> The next registration continues from the last issued number, so moving a register back — or starting the {{ $unifiedName }} at 1/1/1 — issues particulars that already exist on older certificates. KLAES cannot see registration numbers issued outside this system. Run <span class="iw-mono">php artisan deeds:reset-unified-register --dry-run</span> for the collision report before changing them.</div>
    </div>

    <div class="overflow-x-auto" style="margin-top:14px">
        <table class="ce-table">
            <thead><tr><th>Register</th><th class="text-right">Volume</th><th class="text-right">Page</th><th class="text-right">Serial</th><th class="text-right">Page limit</th><th>Shared</th><th>Next registration</th><th>Last change</th><th></th></tr></thead>
            <tbody>
                @foreach($vaults as $vault)
                    <tr class="{{ $vault->live ? ($vault->is_shared ? 'ce-row-live' : '') : 'ce-row-idle' }}">
                        <td>
                            <div class="font-medium text-gray-900">{{ $vault->name }}
                                @if($liveShared && $vault->name === $liveShared)<span class="ce-pill green" style="margin-left:6px">Live · all types</span>
                                @elseif($liveShared)<span class="ce-pill" style="margin-left:6px">History</span>
                                @elseif($vault->live)<span class="ce-pill blue" style="margin-left:6px">In use</span>
                                @else<span class="ce-pill" style="margin-left:6px">Not used</span>@endif
                            </div>
                            @if(count($vault->types))<div class="ce-types">{{ implode(', ', $vault->types) }}</div>@endif
                        </td>
                        <td class="text-right iw-mono font-semibold">{{ $vault->volume }}</td>
                        <td class="text-right iw-mono">{{ $vault->page }}</td>
                        <td class="text-right iw-mono">{{ $vault->serial }}</td>
                        <td class="text-right iw-mono">{{ $vault->page_limit }}</td>
                        <td>@if($vault->is_shared)<span class="ce-pill green">Shared</span>@else<span class="ce-muted">—</span>@endif</td>
                        <td>@if($vault->next)<span class="ce-pill {{ $vault->live ? 'blue' : '' }} iw-mono">{{ $vault->next }}</span>@endif</td>
                        <td class="ce-muted whitespace-nowrap">{{ $vault->updated_at ? \Illuminate\Support\Carbon::parse($vault->updated_at)->format('d M Y, H:i') : '—' }}</td>
                        <td class="text-right">@if($unifiedColumns)<button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editingVault = {{ $vault->id }}"><i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit</button>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($unifiedColumns)
        @foreach($vaults as $vault)
            <div class="iwr-backdrop" x-show="editingVault === {{ $vault->id }}" x-cloak x-transition.opacity @click.self="editingVault = null">
                <form method="POST" action="{{ route('configurable-entries.volumes.vault', $vault->id) }}" class="iwr-modal tone-red" style="max-width:640px"
                      onsubmit="return confirm('Save the {{ addslashes($vault->name) }} register? Counters set below what has already been issued will issue the same registration numbers again.')">
                    @csrf
                    <div class="iwr-head">
                        <span class="iw-icon-tile blue"><i data-lucide="library" class="h-5 w-5"></i></span>
                        <div class="flex-1 min-w-0"><div class="iwt-now-label">Register</div><div class="iwr-title">{{ $vault->name }}</div></div>
                        <button type="button" class="iwr-close" @click="editingVault = null">✕</button>
                    </div>
                    <div class="iwr-body space-y-4">
                        <div class="iw-alert warn">
                            <i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5"></i>
                            <div>Now at <span class="iw-mono">{{ $vault->serial }}/{{ $vault->page }}/{{ $vault->volume }}</span>, next <span class="iw-mono">{{ $vault->next }}</span>. Lowering these re-issues numbers that already exist.</div>
                        </div>
                        <div class="iw-label">Last issued</div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="iw-field"><label class="iw-label">Volume <span class="req">*</span></label><input type="number" min="1" name="last_volume" value="{{ $vault->volume ?: 1 }}" required class="iw-input"></div>
                            <div class="iw-field"><label class="iw-label">Page <span class="req">*</span></label><input type="number" min="0" name="last_page" value="{{ $vault->page }}" required class="iw-input"></div>
                            <div class="iw-field"><label class="iw-label">Serial <span class="req">*</span></label><input type="number" min="0" name="last_serial" value="{{ $vault->serial }}" required class="iw-input"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="iw-field"><label class="iw-label">Page limit <span class="req">*</span></label><input type="number" min="1" name="page_limit" value="{{ $vault->page_limit }}" required class="iw-input"><div class="ce-muted">Serials per volume before it rolls.</div></div>
                            <div class="iw-field">
                                <label class="iw-label">Single Unified Register</label>
                                @if($vault->name === $landVault)
                                    <div class="ce-muted">The Land Registry numbers on its own rules and cannot be shared.</div>
                                @else
                                    <label class="flex items-center gap-2" style="margin-top:6px">
                                        <input type="hidden" name="is_shared" value="0">
                                        <span class="ce-switch"><input type="checkbox" name="is_shared" value="1" @checked($vault->is_shared)><span></span></span>
                                        <span class="text-sm text-gray-700">Every instrument type numbers from this register</span>
                                    </label>
                                @endif
                            </div>
                        </div>
                        <div class="iw-label">Where the register began <span class="font-normal text-gray-500">(optional, for the missing-number survey)</span></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="iw-field"><label class="iw-label">Start volume</label><input type="number" min="1" name="start_volume" value="{{ $vault->start_volume }}" class="iw-input"></div>
                            <div class="iw-field"><label class="iw-label">Start serial</label><input type="number" min="1" name="start_serial" value="{{ $vault->start_serial }}" class="iw-input"></div>
                        </div>
                    </div>
                    <div class="iwr-foot"><span class="ce-muted">Switching Shared on moves every instrument type to this register.</span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editingVault = null">Cancel</button><button class="iw-btn iw-btn-primary iw-btn-sm">Save register</button></div></div>
                </form>
            </div>
        @endforeach
    @endif
</div>
@endif

{{-- ============ Per instrument type ============ --}}
<div class="iw-card" x-data="{ editing: null }" @keydown.escape.window="editing = null">
    <div class="iw-card-head">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile blue"><i data-lucide="book-marked" class="h-5 w-5"></i></span> Deeds Instrument Volume</div>
            <div class="iw-card-sub">
                @if($liveShared)
                    Which register each instrument type numbers from. Shared mode is on, so every type takes its next number from <strong>{{ $liveShared }}</strong>; edit its counters in the table above.
                @else
                    The last registration particulars issued per instrument type (Serial / Page / Volume). The next registration continues from here.
                    Some types share one register (e.g. Deed of Surrender and Release uses Power of Attorney’s); editing either moves the shared register.
                    The same numbers are managed from Instrument Registration → Manage Instrument Types.
                @endif
            </div>
        </div>
    </div>

    @unless($liveShared)
        <div class="iw-alert warn" style="margin:14px 22px 0">
            <i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5"></i>
            <div>Lowering a volume, page or serial below what has already been issued will issue the same particulars twice. Change these only to match the paper register.</div>
        </div>
    @endunless

    <div class="overflow-x-auto" style="margin-top:14px">
        <table class="ce-table">
            <thead><tr><th>Instrument type</th><th class="text-right">Volume</th><th class="text-right">Page</th><th class="text-right">Serial</th><th>Register began at</th><th>Next registration</th><th>Last change</th><th></th></tr></thead>
            <tbody>
                @foreach($types as $type)
                    <tr>
                        <td>
                            <div class="font-medium text-gray-900">{{ $type->name }}</div>
                            @if($type->unified)<div class="ce-muted">Numbers from the <strong>{{ $type->vault }}</strong> (own register: {{ $type->own_vault }})</div>
                            @elseif($type->shared)<div class="ce-muted">Uses the <strong>{{ $type->vault }}</strong> register</div>@endif
                        </td>
                        <td class="text-right iw-mono font-semibold">{{ $type->volume ?? '—' }}</td>
                        <td class="text-right iw-mono">{{ $type->page ?? '—' }}</td>
                        <td class="text-right iw-mono">{{ $type->serial ?? '—' }}</td>
                        <td class="ce-muted whitespace-nowrap">{{ $type->start_volume || $type->start_serial ? 'Vol ' . ($type->start_volume ?? '—') . ' · serial ' . ($type->start_serial ?? '—') : 'Not recorded' }}</td>
                        <td>@if($type->next)<span class="ce-pill blue iw-mono">{{ $type->next }}</span>@else<span class="ce-pill">Not set up</span>@endif</td>
                        <td class="ce-muted whitespace-nowrap">{{ $type->updated_at ? \Illuminate\Support\Carbon::parse($type->updated_at)->format('d M Y, H:i') : '—' }}</td>
                        <td class="text-right">@unless($liveShared)<button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = {{ $type->id }}"><i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit</button>@endunless</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @unless($liveShared)
        @foreach($types as $type)
            <div class="iwr-backdrop" x-show="editing === {{ $type->id }}" x-cloak x-transition.opacity @click.self="editing = null">
                <form method="POST" action="{{ route('configurable-entries.volumes.update', $type->id) }}" class="iwr-modal tone-blue" style="max-width:600px">
                    @csrf
                    <div class="iwr-head">
                        <span class="iw-icon-tile blue"><i data-lucide="book-marked" class="h-5 w-5"></i></span>
                        <div class="flex-1 min-w-0"><div class="iwt-now-label">{{ $type->shared ? 'Register: ' . $type->vault : 'Instrument volume' }}</div><div class="iwr-title">{{ $type->name }}</div></div>
                        <button type="button" class="iwr-close" @click="editing = null">✕</button>
                    </div>
                    <div class="iwr-body space-y-4">
                        <div class="iw-label">Last issued</div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="iw-field"><label class="iw-label">Volume <span class="req">*</span></label><input type="number" min="0" name="last_volume" value="{{ $type->volume ?? 1 }}" required class="iw-input"></div>
                            <div class="iw-field"><label class="iw-label">Page <span class="req">*</span></label><input type="number" min="0" name="last_page" value="{{ $type->page ?? 0 }}" required class="iw-input"></div>
                            <div class="iw-field"><label class="iw-label">Serial <span class="req">*</span></label><input type="number" min="0" name="last_serial" value="{{ $type->serial ?? 0 }}" required class="iw-input"></div>
                        </div>
                        <div class="iw-label">Where the register began <span class="font-normal text-gray-500">(optional, for the missing-number survey)</span></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="iw-field"><label class="iw-label">Start volume</label><input type="number" min="1" name="start_volume" value="{{ $type->start_volume }}" class="iw-input"></div>
                            <div class="iw-field"><label class="iw-label">Start serial</label><input type="number" min="1" name="start_serial" value="{{ $type->start_serial }}" class="iw-input"></div>
                        </div>
                    </div>
                    <div class="iwr-foot"><span class="ce-muted">Currently next: {{ $type->next ?? 'not set up' }}</span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = null">Cancel</button><button class="iw-btn iw-btn-primary iw-btn-sm">Save</button></div></div>
                </form>
            </div>
        @endforeach
    @endunless
</div>

