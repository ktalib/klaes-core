@php
    $allFormats = $schedules->flatMap->formats;
@endphp

<div class="ce-tiles">
    @foreach([
        ['Schedules', $schedules->count(), 'map', 'blue'],
        ['File number formats', $allFormats->count(), 'hash', 'gray'],
        ['Serials generated', $allFormats->sum('generated'), 'list-ordered', 'green'],
    ] as [$label, $value, $icon, $tone])
        <div class="iw-card"><div class="iw-card-body flex items-center gap-3" style="padding:16px 18px">
            <span class="iw-icon-tile {{ $tone }}"><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
            <div><div class="text-2xl font-bold text-gray-900 leading-none">{{ number_format($value) }}</div><div class="text-sm text-gray-500 mt-1">{{ $label }}</div></div>
        </div></div>
    @endforeach
</div>

<div x-data="{ modal: null }" @keydown.escape.window="modal = null" class="space-y-5">
    <div class="iw-alert info">
        <i data-lucide="info" class="h-4 w-4 mt-0.5"></i>
        <div>
            File numbers are picked on the Indexing Interface as <strong>Schedule → File Prefix → Serial No → Suffix</strong>.
            <strong>File Indexing</strong> uses the serials already generated (up to the last serial) for existing files;
            <strong>File Commissioning</strong> numbers new files from 1, continuing after the last file commissioned under the prefix and skipping serials already indexed (the “Next commissioning no.” column).
            <strong>Generate serials</strong> adds a block of numbers in advance. A format's prefix and suffix are fixed once numbers have been generated with it.
        </div>
    </div>

    <div class="flex justify-end">
        <button type="button" class="iw-btn iw-btn-primary iw-btn-sm" @click="modal = 'schedule-new'"><i data-lucide="plus" class="h-4 w-4"></i> Add schedule</button>
    </div>

    @foreach($schedules as $schedule)
        <div class="iw-card">
            <div class="iw-card-head" style="align-items:center;flex-wrap:wrap">
                <div class="flex items-center gap-3">
                    <span class="iw-icon-tile blue"><i data-lucide="map-pin" class="h-5 w-5"></i></span>
                    <div>
                        <div class="iw-card-title">{{ $schedule->name }} <span class="ce-pill">{{ $schedule->code }}</span> @unless($schedule->is_active)<span class="ce-pill red">Off</span>@endunless</div>
                        <div class="iw-card-sub">{{ $schedule->formats->count() }} {{ \Illuminate\Support\Str::plural('format', $schedule->formats->count()) }} · {{ number_format($schedule->formats->sum('generated')) }} serials generated</div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="modal = 'schedule-{{ $schedule->id }}'"><i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit schedule</button>
                    <button type="button" class="iw-btn iw-btn-primary iw-btn-sm" @click="modal = 'format-new-{{ $schedule->id }}'"><i data-lucide="plus" class="h-3.5 w-3.5"></i> Add format</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="ce-table">
                    <thead><tr><th>Format</th><th>Prefix</th><th>Suffix</th><th>Next commissioning no.</th><th class="text-right">Generated up to</th><th>Generated</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse($schedule->formats as $format)
                            <tr>
                                <td><div class="iw-mono font-semibold text-gray-900">{{ $format->pattern }}</div><div class="ce-muted">{{ $format->label }}</div></td>
                                <td class="iw-mono">{{ $format->file_prefix }}</td>
                                <td class="iw-mono">{{ $format->suffix ?: '—' }}</td>
                                <td>
                                    <div class="iw-mono text-blue-700">{{ $format->example }}</div>
                                    <div class="ce-muted">{{ $format->last_commissioned ? 'Last commissioned: ' . number_format($format->last_commissioned) : 'None commissioned yet' }}{{ (int) ($format->commissioning_start_after ?? 0) > 0 ? ' · starts after ' . number_format((int) $format->commissioning_start_after) : '' }}</div>
                                </td>
                                <td class="text-right iw-mono font-semibold">{{ number_format((int) $format->last_serial) }}</td>
                                <td class="whitespace-nowrap">{{ number_format($format->generated) }}@if($format->generated)<div class="ce-muted">serials {{ number_format($format->first_serial) }}–{{ number_format($format->max_serial) }}</div>@endif</td>
                                <td>@if($format->is_active)<span class="ce-pill green">Active</span>@else<span class="ce-pill">Off</span>@endif</td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="modal = 'format-{{ $format->id }}'"><i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit</button>
                                    <button type="button" class="iw-btn iw-btn-success iw-btn-sm" @click="modal = 'generate-{{ $format->id }}'"><i data-lucide="list-plus" class="h-3.5 w-3.5"></i> Generate serials</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" style="padding:28px"><div class="iw-empty">No formats yet. Add one to start numbering files in {{ $schedule->name }}.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Edit schedule --}}
        <div class="iwr-backdrop" x-show="modal === 'schedule-{{ $schedule->id }}'" x-cloak x-transition.opacity @click.self="modal = null">
            <form method="POST" action="{{ route('configurable-entries.schedules.update', $schedule->id) }}" class="iwr-modal tone-blue">
                @csrf
                <div class="iwr-head"><span class="iw-icon-tile blue"><i data-lucide="map-pin" class="h-5 w-5"></i></span><div class="flex-1"><div class="iwt-now-label">Schedule {{ $schedule->code }}</div><div class="iwr-title">Edit schedule</div></div><button type="button" class="iwr-close" @click="modal = null">✕</button></div>
                <div class="iwr-body space-y-4">
                    <div class="iw-field"><label class="iw-label">Name <span class="req">*</span></label><input type="text" name="name" value="{{ $schedule->name }}" required class="iw-input"></div>
                    <div class="iw-field"><label class="iw-label">Display order</label><input type="number" min="0" name="sort_order" value="{{ $schedule->sort_order }}" class="iw-input"></div>
                    <label class="flex items-center gap-3 text-sm"><input type="hidden" name="is_active" value="0"><span class="ce-switch"><input type="checkbox" name="is_active" value="1" @checked($schedule->is_active)><span></span></span> Offered on the Indexing Interface</label>
                </div>
                <div class="iwr-foot"><span></span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="modal = null">Cancel</button><button class="iw-btn iw-btn-primary iw-btn-sm">Save</button></div></div>
            </form>
        </div>

        {{-- Add format --}}
        <div class="iwr-backdrop" x-show="modal === 'format-new-{{ $schedule->id }}'" x-cloak x-transition.opacity @click.self="modal = null">
            <form method="POST" action="{{ route('configurable-entries.formats.store') }}" class="iwr-modal tone-blue" x-data="{ prefix: '', suffix: '' }">
                @csrf
                <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
                <div class="iwr-head"><span class="iw-icon-tile blue"><i data-lucide="hash" class="h-5 w-5"></i></span><div class="flex-1"><div class="iwt-now-label">{{ $schedule->name }}</div><div class="iwr-title">Add file number format</div></div><button type="button" class="iwr-close" @click="modal = null">✕</button></div>
                <div class="iwr-body space-y-4">
                    <div class="iw-grid" style="gap:12px">
                        <div class="iw-field"><label class="iw-label">File prefix <span class="req">*</span></label><input type="text" name="file_prefix" x-model="prefix" required class="iw-input iw-mono" placeholder="e.g. LUAC/AB"></div>
                        <div class="iw-field"><label class="iw-label">Suffix</label><input type="text" name="suffix" x-model="suffix" class="iw-input iw-mono" placeholder="e.g. AB (optional)"></div>
                        <div class="iw-field"><label class="iw-label">Label</label><input type="text" name="label" class="iw-input" placeholder="e.g. LUAC/AB — {{ $schedule->name }}"></div>
                        <div class="iw-field"><label class="iw-label">Start after serial</label><input type="number" min="0" name="last_serial" value="0" class="iw-input"><span class="iw-help">Numbers already issued on paper end here; generation starts at the next one.</span></div>
                    </div>
                    <div class="iw-alert info" style="align-items:center"><i data-lucide="eye" class="h-4 w-4"></i><div>File numbers will look like <strong class="iw-mono" x-text="(prefix || 'PREFIX').toUpperCase().replace(/\/+$/, '') + '/1' + (suffix ? '/' + suffix.toUpperCase() : '')"></strong></div></div>
                </div>
                <div class="iwr-foot"><span></span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="modal = null">Cancel</button><button class="iw-btn iw-btn-primary iw-btn-sm">Add format</button></div></div>
            </form>
        </div>

        @foreach($schedule->formats as $format)
            {{-- Edit format --}}
            <div class="iwr-backdrop" x-show="modal === 'format-{{ $format->id }}'" x-cloak x-transition.opacity @click.self="modal = null">
                <form method="POST" action="{{ route('configurable-entries.formats.update', $format->id) }}" class="iwr-modal tone-blue">
                    @csrf
                    <div class="iwr-head"><span class="iw-icon-tile blue"><i data-lucide="hash" class="h-5 w-5"></i></span><div class="flex-1"><div class="iwt-now-label">{{ $schedule->name }}</div><div class="iwr-title iw-mono">{{ $format->pattern }}</div></div><button type="button" class="iwr-close" @click="modal = null">✕</button></div>
                    <div class="iwr-body space-y-4">
                        <div class="iw-grid" style="gap:12px">
                            <div class="iw-field"><label class="iw-label">File prefix</label><input type="text" name="file_prefix" value="{{ $format->file_prefix }}" class="iw-input iw-mono" @if($format->generated) readonly @endif></div>
                            <div class="iw-field"><label class="iw-label">Suffix</label><input type="text" name="suffix" value="{{ $format->suffix }}" class="iw-input iw-mono" @if($format->generated) readonly @endif></div>
                        </div>
                        @if($format->generated)<p class="iw-help">Prefix and suffix are fixed: {{ number_format($format->generated) }} file numbers already use this pattern.</p>@endif
                        <div class="iw-field"><label class="iw-label">Label <span class="req">*</span></label><input type="text" name="label" value="{{ $format->label }}" required class="iw-input"></div>
                        <div class="iw-grid" style="gap:12px">
                            <div class="iw-field"><label class="iw-label">Display order</label><input type="number" min="0" name="sort_order" value="{{ $format->sort_order }}" class="iw-input"></div>
                            <div class="iw-field"><label class="iw-label">Commissioning starts after serial</label><input type="number" min="0" name="commissioning_start_after" value="{{ (int) ($format->commissioning_start_after ?? 0) }}" class="iw-input"><span class="iw-help">0 = new files start at serial 1. Serials already indexed are always skipped.</span></div>
                        </div>
                        <label class="flex items-center gap-3 text-sm"><input type="hidden" name="is_active" value="0"><span class="ce-switch"><input type="checkbox" name="is_active" value="1" @checked($format->is_active)><span></span></span> Offered on the Indexing Interface</label>
                    </div>
                    <div class="iwr-foot"><span class="ce-muted">Generated up to {{ number_format((int) $format->last_serial) }} is moved only by generating serials.</span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="modal = null">Cancel</button><button class="iw-btn iw-btn-primary iw-btn-sm">Save</button></div></div>
                </form>
            </div>

            {{-- Generate serials --}}
            <div class="iwr-backdrop" x-show="modal === 'generate-{{ $format->id }}'" x-cloak x-transition.opacity @click.self="modal = null">
                <form method="POST" action="{{ route('configurable-entries.formats.generate', $format->id) }}" class="iwr-modal tone-green" x-data="{ count: 100 }">
                    @csrf
                    <div class="iwr-head"><span class="iw-icon-tile green"><i data-lucide="list-plus" class="h-5 w-5"></i></span><div class="flex-1"><div class="iwt-now-label">{{ $format->label }}</div><div class="iwr-title">Generate serial numbers</div></div><button type="button" class="iwr-close" @click="modal = null">✕</button></div>
                    <div class="iwr-body space-y-4">
                        <div class="iw-field"><label class="iw-label">How many <span class="req">*</span></label><input type="number" min="1" max="5000" name="count" x-model.number="count" required class="iw-input"><span class="iw-help">Up to 5,000 at a time. Serials that already exist are skipped.</span></div>
                        <div class="iw-alert info" style="align-items:center"><i data-lucide="hash" class="h-4 w-4"></i>
                            <div>Adds <strong class="iw-mono">{{ str_replace('{serial}', (string) ((int) $format->last_serial + 1), $format->pattern) }}</strong>
                                to <strong class="iw-mono" x-text="@js($format->pattern).replace('{serial}', {{ (int) $format->last_serial }} + (Number(count) || 0))"></strong></div></div>
                    </div>
                    <div class="iwr-foot"><span></span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="modal = null">Cancel</button><button class="iw-btn iw-btn-success iw-btn-sm">Generate</button></div></div>
                </form>
            </div>
        @endforeach
    @endforeach

    {{-- Add schedule --}}
    <div class="iwr-backdrop" x-show="modal === 'schedule-new'" x-cloak x-transition.opacity @click.self="modal = null">
        <form method="POST" action="{{ route('configurable-entries.schedules.store') }}" class="iwr-modal tone-blue">
            @csrf
            <div class="iwr-head"><span class="iw-icon-tile blue"><i data-lucide="map" class="h-5 w-5"></i></span><div class="flex-1"><div class="iwt-now-label">FileNo Prefix & SerialNo</div><div class="iwr-title">Add schedule</div></div><button type="button" class="iwr-close" @click="modal = null">✕</button></div>
            <div class="iwr-body space-y-4">
                <div class="iw-field"><label class="iw-label">Code <span class="req">*</span></label><input type="text" name="code" required class="iw-input iw-mono" placeholder="e.g. ARO"></div>
                <div class="iw-field"><label class="iw-label">Name <span class="req">*</span></label><input type="text" name="name" required class="iw-input" placeholder="e.g. Arochukwu"></div>
            </div>
            <div class="iwr-foot"><span></span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="modal = null">Cancel</button><button class="iw-btn iw-btn-primary iw-btn-sm">Add schedule</button></div></div>
        </form>
    </div>
</div>

