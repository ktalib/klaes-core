@php
    // Slot => group, label, where it is shown, default image (config/alaes_logos.php).
    // $logoRows is already filtered by the search / area / state pickers below. With
    // 250-odd slots the tab opens on the areas; slots are listed once one is chosen.
    $extensions = strtoupper(implode(', ', $logoExtensions));
    $filtering = $logoSearch !== '' || $logoGroup !== '' || $logoOnly !== '';
    $listed = collect($logoRows)->sum(fn ($slots) => count($slots));
    $accept = collect($logoExtensions)->map(fn ($e) => '.' . $e)->implode(',');
@endphp

<div class="iw-alert info">
    <i data-lucide="image" class="h-4 w-4 mt-0.5"></i>
    <div>
        Every logo KLAES shows or prints has a slot here — one for each position on a screen or document, so the receipt can carry a
        different seal from the portal header. Upload a replacement and it takes effect at once, everywhere that slot is used;
        <strong>Restore default</strong> puts back the image KLAES ships with. {{ $extensions }} up to {{ $logoMaxKb }}KB.
    </div>
</div>

{{-- ============ Counts ============ --}}
<div class="ce-tiles">
    <div class="iw-card"><div class="iw-card-body">
        <div class="iw-summary-label">Logo slots</div>
        <div class="iw-summary-value">{{ number_format($logoCounts['slots']) }}</div>
        <div class="ce-muted">across {{ $logoCounts['groups'] }} areas</div>
    </div></div>
    <div class="iw-card"><div class="iw-card-body">
        <div class="iw-summary-label">Replaced</div>
        <div class="iw-summary-value">{{ number_format($logoCounts['custom']) }}</div>
        <div class="ce-muted">the rest use the shipped image</div>
    </div></div>
    <div class="iw-card"><div class="iw-card-body">
        <div class="iw-summary-label">Image missing</div>
        <div class="iw-summary-value">{{ number_format($logoCounts['missing']) }}</div>
        <div class="ce-muted">
            @if($logoCounts['missing'])
                <a href="{{ route('configurable-entries.index', ['tab' => 'logos', 'only' => 'missing']) }}" class="text-red-700 font-semibold">these print a gap — show them</a>
            @else
                every slot has an image
            @endif
        </div>
    </div></div>
</div>

{{-- ============ Filters ============ --}}
<form method="GET" action="{{ route('configurable-entries.index') }}" data-iw-no-ajax class="iw-card">
    <input type="hidden" name="tab" value="logos">
    <div class="iw-card-body iw-grid" style="grid-template-columns: 2fr 1fr 1fr auto; align-items:end; gap:12px">
        <div class="iw-field">
            <label class="iw-label">Search</label>
            <input type="text" name="q" value="{{ $logoSearch }}" class="iw-input" placeholder="Receipt, favicon, portal header…">
        </div>
        <div class="iw-field">
            <label class="iw-label">Area</label>
            <select name="group" class="ce-select">
                <option value="">All areas</option>
                @foreach($logoGroups as $key => $meta)
                    <option value="{{ $key }}" @selected($logoGroup === $key)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="iw-field">
            <label class="iw-label">State</label>
            <select name="only" class="ce-select">
                <option value="">Any</option>
                <option value="custom" @selected($logoOnly === 'custom')>Replaced</option>
                <option value="missing" @selected($logoOnly === 'missing')>Image missing</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="iw-btn iw-btn-primary"><i data-lucide="search" class="h-4 w-4"></i> Find</button>
            @if($filtering)
                <a href="{{ route('configurable-entries.index', ['tab' => 'logos']) }}" class="iw-btn iw-btn-light">Clear</a>
            @endif
        </div>
    </div>
</form>

@unless($filtering)
    {{-- ============ The areas ============ --}}
    <div class="lg-areas">
        @foreach($logoGroups as $key => $meta)
            @php $stats = $logoGroupCounts[$key] ?? null; @endphp
            @continue(!$stats)
            <a class="lg-area" href="{{ route('configurable-entries.index', ['tab' => 'logos', 'group' => $key]) }}">
                <div class="lg-area-head">
                    <span class="iw-icon-tile blue"><i data-lucide="{{ $meta['icon'] ?? 'image' }}" class="h-5 w-5"></i></span>
                    <div>
                        <div class="lg-title">{{ $meta['label'] }}</div>
                        <div class="ce-muted">{{ $meta['description'] ?? '' }}</div>
                    </div>
                </div>
                <div class="lg-area-strip">
                    @foreach($stats['preview'] as $url)
                        <span class="lg-chip"><img src="{{ $url }}" alt="" loading="lazy"></span>
                    @endforeach
                </div>
                <div class="lg-area-foot">
                    <span class="ce-pill">{{ $stats['slots'] }} {{ \Illuminate\Support\Str::plural('logo', $stats['slots']) }}</span>
                    @if($stats['custom'])<span class="ce-pill blue">{{ $stats['custom'] }} replaced</span>@endif
                    @if($stats['missing'])<span class="ce-pill red">{{ $stats['missing'] }} missing</span>@endif
                    <span class="lg-open">Open <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></span>
                </div>
            </a>
        @endforeach
    </div>
@else
    {{-- ============ The slots ============ --}}
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="ce-muted">
            {{ number_format($listed) }} of {{ number_format($logoCounts['slots']) }} {{ \Illuminate\Support\Str::plural('logo', $logoCounts['slots']) }}
            @if($logoSearch !== '') matching “{{ $logoSearch }}” @endif
        </div>
        <a href="{{ route('configurable-entries.index', ['tab' => 'logos']) }}" class="iw-btn iw-btn-light iw-btn-sm"><i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> All areas</a>
    </div>

    @if($listed === 0)
        <div class="iw-card"><div class="iw-card-body ce-muted">No logo slot matches that.</div></div>
    @endif

    @foreach($logoRows as $groupKey => $slots)
        @php $meta = $logoGroups[$groupKey] ?? ['label' => $groupKey, 'description' => '', 'icon' => 'image']; @endphp
        <div class="iw-card">
            <div class="iw-card-head" style="align-items:center">
                <div>
                    <div class="iw-card-title">
                        <span class="iw-icon-tile blue"><i data-lucide="{{ $meta['icon'] ?? 'image' }}" class="h-5 w-5"></i></span>
                        {{ $meta['label'] }}
                        <span class="ce-pill">{{ count($slots) }}</span>
                    </div>
                    <div class="iw-card-sub">{{ $meta['description'] ?? '' }}</div>
                </div>
            </div>

            <div class="iw-card-body lg-grid">
                @foreach($slots as $slot => $row)
                    <div class="lg-item @if($row['missing']) is-missing @endif">
                        <div class="lg-preview">
                            @if($row['missing'])
                                <div class="lg-gap"><i data-lucide="image-off" class="h-5 w-5"></i></div>
                            @else
                                <img src="{{ $row['url'] }}?v={{ substr(md5($row['relative']), 0, 8) }}" alt="{{ $row['label'] }}" loading="lazy">
                            @endif
                        </div>

                        <div class="lg-body">
                            <div class="lg-title">
                                {{ $row['label'] }}
                                @if($row['custom'])<span class="ce-pill blue">Replaced</span>@endif
                                @if($row['missing'])<span class="ce-pill red">Image missing</span>@endif
                            </div>
                            <div class="ce-muted">{{ $row['where'] ?? '' }}</div>
                            @if(!empty($row['guide']))
                                <div class="ce-muted">{{ $row['guide'] }}</div>
                            @endif
                            <div class="lg-path iw-mono">{{ $row['relative'] ?: $row['default'] }}</div>

                            <div class="lg-actions">
                                <form method="POST" action="{{ route('configurable-entries.logos.save', $slot) }}" enctype="multipart/form-data" class="lg-upload">
                                    @csrf
                                    <input type="file" name="image" id="lg-{{ $slot }}" accept="{{ $accept }}" required
                                           onchange="this.form.querySelector('[data-lg-name]').textContent = this.files[0] ? this.files[0].name : 'No file chosen'">
                                    <label for="lg-{{ $slot }}" class="iw-btn iw-btn-light iw-btn-sm"><i data-lucide="upload" class="h-3.5 w-3.5"></i> Choose</label>
                                    <span class="ce-muted lg-name" data-lg-name>No file chosen</span>
                                    <button type="submit" class="iw-btn iw-btn-primary iw-btn-sm">Save</button>
                                </form>

                                @if($row['custom'])
                                    <form method="POST" action="{{ route('configurable-entries.logos.reset', $slot) }}">
                                        @csrf
                                        <button type="submit" class="iw-btn iw-btn-light iw-btn-sm"><i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Restore default</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endunless

<style>
    /* Areas first, slots second. A slot row is a preview, what it is and where it is
       shown, and the two things that can be done to it. The file input hides behind
       its label so the control matches the other buttons on the page. */
    .lg-areas { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
    .lg-area { display: flex; flex-direction: column; gap: 10px; padding: 16px; background: #fff;
               border: 1px solid #e5e7eb; border-radius: 14px; transition: border-color .15s, box-shadow .15s; }
    .lg-area:hover { border-color: #4338ca; box-shadow: 0 2px 10px rgba(67, 56, 202, .10); }
    .lg-area-head { display: flex; gap: 12px; align-items: flex-start; }
    .lg-area-strip { display: flex; gap: 6px; flex-wrap: wrap; }
    .lg-chip { width: 46px; height: 34px; display: flex; align-items: center; justify-content: center;
               border: 1px solid #e5e7eb; border-radius: 7px; background: #f9fafb; overflow: hidden; }
    .lg-chip img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .lg-area-foot { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-top: auto; }
    .lg-open { margin-left: auto; font-size: 12px; font-weight: 600; color: #4338ca; display: inline-flex; align-items: center; gap: 4px; }

    .lg-grid { display: grid; gap: 10px; }
    .lg-item { display: flex; gap: 14px; align-items: flex-start; padding: 12px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; }
    .lg-item.is-missing { border-color: #fecaca; background: #fff5f5; }
    .lg-preview { flex: none; width: 92px; height: 68px; display: flex; align-items: center; justify-content: center;
                  border: 1px solid #e5e7eb; border-radius: 9px; background: #f9fafb; overflow: hidden; }
    .lg-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .lg-gap { color: #b91c1c; }
    .lg-body { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 3px; }
    .lg-title { font-size: 14px; font-weight: 600; color: #111827; display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
    .lg-path { font-size: 11px; color: #9ca3af; word-break: break-all; }
    .lg-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
    .lg-upload { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .lg-upload input[type=file] { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
    .lg-name { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    @media (max-width: 640px) {
        .lg-item { flex-direction: column; }
        .lg-preview { width: 100%; height: 96px; }
    }
</style>

