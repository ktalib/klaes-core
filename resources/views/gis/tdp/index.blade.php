@extends('layouts.app')
@section('page-title')
    {{ __('Title Deed Plan Management') }}
@endsection

{{--
    GIS → Title Deed Plan Management.

    The plans are files on the GIS server, one folder per LGA
    (config/tdp.php → TDP_ROOT_PATH). Everything on this page survives the
    folder being absent: the store panel says so and the library, the search and
    the upload card stand down.

    Tailwind 2.2 from the CDN has no slash opacities and no arbitrary values, so
    anything beyond Tailwind 2 lives in the scoped tdp- styles at the bottom.
--}}

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
    @include('admin.header', [
        'PageTitle' => 'Title Deed Plan Management',
        'PageDescription' => 'GIS · the Title Deed Plan store on the GIS server, by LGA and file number',
    ])
    @include('instrument_workflow.partials.styles')

    <div class="iw-page space-y-5" style="max-width:1400px"
         x-data="tdpScreen({{ Js::from(['preview' => route('tdp.file'), 'extensions' => $allowedExtensions]) }})">

        @include('instrument_workflow.partials.flash')

        <div class="iw-crumbs" style="margin-bottom:0">
            <span>GIS</span><span>/</span><span class="text-gray-900">Title Deed Plan Management</span>
        </div>

        {{-- ── The store itself ──────────────────────────────────────────── --}}
        <div class="tdp-store tdp-store-{{ $status['state'] }}">
            <div class="tdp-store-icon">
                <i data-lucide="{{ $status['state'] === 'ready' ? 'hard-drive' : ($status['state'] === 'unreachable' ? 'plug-zap' : 'settings-2') }}" class="h-5 w-5"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center flex-wrap gap-2">
                    <span class="tdp-store-title">
                        @if($status['state'] === 'ready') Title Deed Plan store connected
                        @elseif($status['state'] === 'unreachable') TDP folder is not reachable
                        @else TDP folder is not configured
                        @endif
                    </span>
                    <span class="tdp-pill tdp-pill-state {{ $status['state'] === 'ready' ? 'green' : 'red' }}">{{ str_replace('_', ' ', $status['state']) }}</span>
                    @if($status['state'] === 'ready' && !$status['writable'])
                        <span class="tdp-pill yellow">read-only</span>
                    @endif
                </div>
                <p class="tdp-store-text">{{ $status['message'] }}</p>
                @if($status['state'] !== 'ready')
                    <div class="tdp-help">
                        <div class="tdp-help-row"><span>Expected layout</span><code class="iw-mono">{{ $status['root'] ?: 'C:\Kano State\TDP' }}\&lt;LGA&gt;\&lt;file number&gt;.pdf</code></div>
                        <div class="tdp-help-row"><span>Setting</span><code class="iw-mono">TDP_ROOT_PATH</code> in <code class="iw-mono">.env</code>, read by <code class="iw-mono">config/tdp.php</code></div>
                        <div class="tdp-help-row"><span>Note</span><span>KLAES normally runs on the GIS server, where this is a local folder. Nothing was changed on disk.</span></div>
                    </div>
                @endif
            </div>
            @if($status['state'] === 'ready')
                <div class="tdp-store-root">
                    <span class="tdp-store-root-label">Root</span>
                    <code class="iw-mono">{{ $status['root'] }}</code>
                </div>
            @endif
        </div>

        @if($status['reachable'])
            {{-- ── Counts ────────────────────────────────────────────────── --}}
            <div class="tdp-tiles">
                <div class="tdp-tile">
                    <div class="tdp-tile-icon blue"><i data-lucide="folders" class="h-5 w-5"></i></div>
                    <div>
                        <div class="tdp-tile-value">{{ number_format(count($folders)) }}</div>
                        <div class="tdp-tile-label">LGA folders</div>
                    </div>
                </div>
                <div class="tdp-tile">
                    <div class="tdp-tile-icon green"><i data-lucide="map" class="h-5 w-5"></i></div>
                    <div>
                        <div class="tdp-tile-value">{{ number_format($totalPlans) }}</div>
                        <div class="tdp-tile-label">Plans on file</div>
                    </div>
                </div>
                <div class="tdp-tile">
                    <div class="tdp-tile-icon purple"><i data-lucide="search" class="h-5 w-5"></i></div>
                    <div>
                        <div class="tdp-tile-value">{{ number_format($results['total']) }}</div>
                        <div class="tdp-tile-label">{{ $query !== '' || $lga !== '' ? 'Matching this search' : 'Listed below' }}</div>
                    </div>
                </div>
                <a href="{{ route('tdp.reconciliation') }}" class="tdp-tile tdp-tile-link">
                    <div class="tdp-tile-icon {{ $unmatchedFolders ? 'red' : 'gray' }}"><i data-lucide="git-compare" class="h-5 w-5"></i></div>
                    <div>
                        <div class="tdp-tile-value">{{ number_format($unmatchedFolders) }}</div>
                        <div class="tdp-tile-label">Folders with no KLAES LGA <i data-lucide="arrow-right" class="h-3 w-3"></i></div>
                    </div>
                </a>
            </div>

            <div class="tdp-layout">
                {{-- ── LGA picker ────────────────────────────────────────── --}}
                <aside class="iw-card tdp-rail">
                    <div class="iw-card-head" style="padding:16px 18px">
                        <div>
                            <div class="iw-card-title" style="font-size:15px">
                                <span class="iw-icon-tile blue"><i data-lucide="layers" class="h-4 w-4"></i></span>
                                LGA
                            </div>
                            <div class="iw-card-sub">Folders under the TDP root</div>
                        </div>
                    </div>
                    <div class="tdp-rail-body">
                        <a href="{{ route('tdp.index', ['q' => $query]) }}" class="tdp-rail-item {{ $lga === '' ? 'active' : '' }}">
                            <span class="tdp-rail-name"><i data-lucide="globe" class="h-4 w-4"></i> All LGAs</span>
                            <span class="tdp-count">{{ number_format($totalPlans) }}</span>
                        </a>
                        @forelse($folders as $folder)
                            <a href="{{ route('tdp.index', ['lga' => $folder['name'], 'q' => $query]) }}"
                               class="tdp-rail-item {{ strcasecmp($lga, $folder['name']) === 0 ? 'active' : '' }}">
                                <span class="tdp-rail-name">
                                    <i data-lucide="folder" class="h-4 w-4"></i>
                                    <span class="truncate">{{ $folder['name'] }}</span>
                                    @unless($folder['matches_lga'])
                                        <i data-lucide="alert-triangle" class="h-3 w-3 tdp-warn" title="No KLAES LGA of this name"></i>
                                    @endunless
                                </span>
                                <span class="tdp-count">{{ number_format($folder['file_count']) }}</span>
                            </a>
                        @empty
                            <div class="iw-empty" style="margin:10px">No LGA folders under the root yet.</div>
                        @endforelse
                    </div>
                </aside>

                {{-- ── Search + results ──────────────────────────────────── --}}
                <section class="space-y-4" style="min-width:0">
                    <div class="iw-card">
                        <div class="iw-card-body" style="padding:16px 18px">
                            <form method="GET" action="{{ route('tdp.index') }}" class="tdp-search">
                                <input type="hidden" name="lga" value="{{ $lga }}">
                                <div class="iw-input-group" style="flex:1 1 320px">
                                    <i data-lucide="search"></i>
                                    <input type="text" name="q" value="{{ $query }}" class="iw-input"
                                           placeholder="File number — ST/KN/7655/KMC, ST-KN-7655-KMC or just 7655"
                                           autocomplete="off">
                                </div>
                                <button type="submit" class="iw-btn iw-btn-primary">
                                    <i data-lucide="search" class="h-4 w-4"></i> Search
                                </button>
                                @if($query !== '' || $lga !== '')
                                    <a href="{{ route('tdp.index') }}" class="iw-btn iw-btn-light">
                                        <i data-lucide="x" class="h-4 w-4"></i> Clear
                                    </a>
                                @endif
                            </form>
                            <p class="iw-help" style="margin-top:8px">
                                Separators and case are ignored: <code class="iw-mono">ST/KN/7655/KMC</code> finds
                                <code class="iw-mono">ST-KN-7655-KMC.pdf</code>, <code class="iw-mono">ST_KN_7655_KMC.pdf</code> and
                                <code class="iw-mono">st kn 7655 kmc.pdf</code>.
                            </p>
                        </div>
                    </div>

                    <div class="iw-card">
                        <div class="iw-card-head" style="padding:16px 18px">
                            <div>
                                <div class="iw-card-title" style="font-size:15px">
                                    <span class="iw-icon-tile green"><i data-lucide="file-text" class="h-4 w-4"></i></span>
                                    Title Deed Plans
                                </div>
                                <div class="iw-card-sub">
                                    {{ $lga !== '' ? $lga : 'All LGAs' }}@if($query !== ''), matching “{{ $query }}”@endif —
                                    {{ number_format($results['total']) }} {{ Str::plural('plan', $results['total']) }}
                                </div>
                            </div>
                            @if($results['total'] > 0)
                                <span class="tdp-pill gray">Page {{ $results['page'] }} of {{ $results['last_page'] }}</span>
                            @endif
                        </div>

                        <div style="overflow-x:auto">
                            <table class="tdp-table">
                                <thead>
                                    <tr>
                                        <th style="width:38%">File</th>
                                        <th>File number</th>
                                        <th>LGA</th>
                                        <th style="text-align:right">Size</th>
                                        <th>Modified</th>
                                        <th style="text-align:right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($results['items'] as $item)
                                    @php
                                        $isImage = in_array($item['extension'], ['jpg', 'jpeg', 'png'], true);
                                        $isPdf = $item['extension'] === 'pdf';
                                        $url = route('tdp.file', ['path' => $item['relative_path']]);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="tdp-file">
                                                <span class="tdp-ext tdp-ext-{{ $item['extension'] }}">{{ strtoupper($item['extension']) }}</span>
                                                <span class="tdp-file-name" title="{{ $item['name'] }}">{{ $item['name'] }}</span>
                                            </div>
                                        </td>
                                        <td><span class="iw-mono tdp-number">{{ $item['file_number'] }}</span></td>
                                        <td>{{ $item['lga'] }}</td>
                                        <td style="text-align:right" class="tdp-muted">
                                            {{ $item['size'] >= 1048576 ? number_format($item['size'] / 1048576, 1) . ' MB' : number_format(max(1, $item['size'] / 1024)) . ' KB' }}
                                        </td>
                                        <td class="tdp-muted">{{ $item['modified'] ? \Carbon\Carbon::createFromTimestamp($item['modified'])->format('d M Y H:i') : '—' }}</td>
                                        <td style="text-align:right">
                                            <div class="tdp-actions">
                                                @if($isPdf || $isImage)
                                                    <button type="button" class="tdp-icon-btn" title="Preview" aria-label="Preview {{ $item['name'] }}"
                                                            @click="preview({{ Js::from(['name' => $item['name'], 'number' => $item['file_number'], 'lga' => $item['lga'], 'url' => $url, 'kind' => $isPdf ? 'pdf' : 'image']) }})">
                                                        <i data-lucide="eye" class="h-4 w-4"></i>
                                                    </button>
                                                @else
                                                    <span class="tdp-icon-btn tdp-disabled" title="{{ strtoupper($item['extension']) }} files cannot be previewed in a browser">
                                                        <i data-lucide="eye-off" class="h-4 w-4"></i>
                                                    </span>
                                                @endif
                                                <a href="{{ $url }}&download=1" class="tdp-icon-btn" title="Download" aria-label="Download {{ $item['name'] }}">
                                                    <i data-lucide="download" class="h-4 w-4"></i>
                                                </a>
                                                @if($canManage && $uploadsEnabled)
                                                    <button type="button" class="tdp-icon-btn" title="Replace this plan" aria-label="Replace {{ $item['name'] }}"
                                                            @click="replace({{ Js::from(['lga' => $item['lga'], 'number' => $item['file_number']]) }})">
                                                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <div class="iw-empty" style="margin:18px">
                                                <i data-lucide="file-search" class="h-6 w-6" style="margin:0 auto 8px"></i>
                                                @if($query !== '')
                                                    No plan in {{ $lga !== '' ? $lga : 'the store' }} matches “{{ $query }}”.
                                                @else
                                                    No Title Deed Plans in {{ $lga !== '' ? $lga : 'the store' }} yet.
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($results['last_page'] > 1)
                            <div class="iw-card-foot">
                                <span class="tdp-muted">
                                    {{ number_format(($results['page'] - 1) * $results['per_page'] + 1) }}–{{ number_format(min($results['total'], $results['page'] * $results['per_page'])) }}
                                    of {{ number_format($results['total']) }}
                                </span>
                                <div class="tdp-actions">
                                    <a class="iw-btn iw-btn-light iw-btn-sm {{ $results['page'] <= 1 ? 'tdp-disabled' : '' }}"
                                       href="{{ route('tdp.index', ['lga' => $lga, 'q' => $query, 'page' => max(1, $results['page'] - 1)]) }}">
                                        <i data-lucide="chevron-left" class="h-4 w-4"></i> Previous
                                    </a>
                                    <a class="iw-btn iw-btn-light iw-btn-sm {{ $results['page'] >= $results['last_page'] ? 'tdp-disabled' : '' }}"
                                       href="{{ route('tdp.index', ['lga' => $lga, 'q' => $query, 'page' => min($results['last_page'], $results['page'] + 1)]) }}">
                                        Next <i data-lucide="chevron-right" class="h-4 w-4"></i>
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- ── Upload / replace ──────────────────────────────── --}}
                    @if($canManage)
                        <div class="iw-card" id="tdp-upload" x-ref="upload">
                            <div class="iw-card-head" style="padding:16px 18px">
                                <div>
                                    <div class="iw-card-title" style="font-size:15px">
                                        <span class="iw-icon-tile blue"><i data-lucide="upload-cloud" class="h-4 w-4"></i></span>
                                        Upload or replace a plan
                                    </div>
                                    <div class="iw-card-sub">
                                        Written into the LGA folder on the GIS server as
                                        <code class="iw-mono">&lt;file number&gt;.&lt;ext&gt;</code>. An existing plan is kept as a timestamped copy.
                                    </div>
                                </div>
                            </div>
                            <div class="iw-card-body">
                                @if(!$uploadsEnabled)
                                    <div class="iw-alert warn">
                                        <i data-lucide="lock" class="h-4 w-4"></i>
                                        <span>Writing to the TDP store is switched off (<code class="iw-mono">TDP_UPLOADS_ENABLED</code>). The library stays readable.</span>
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('tdp.store') }}" enctype="multipart/form-data" class="space-y-4">
                                        @csrf
                                        <div class="iw-grid">
                                            <div class="iw-field">
                                                <label class="iw-label" for="tdp-lga">LGA folder <span class="req">*</span></label>
                                                <select name="lga" id="tdp-lga" class="iw-select" x-model="form.lga" required>
                                                    <option value="">Choose an LGA…</option>
                                                    @if(count($folders))
                                                        <optgroup label="Folders in the store">
                                                            @foreach($folders as $folder)
                                                                <option value="{{ $folder['name'] }}" @selected(old('lga', $lga) === $folder['name'])>{{ $folder['name'] }} ({{ $folder['file_count'] }})</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                    @php $folderKeys = collect($folders)->map(fn ($f) => strtolower(preg_replace('/[^a-z0-9]/i', '', $f['name'])))->all(); @endphp
                                                    @php $missing = collect($knownLgas)->reject(fn ($n) => in_array(strtolower(preg_replace('/[^a-z0-9]/i', '', $n)), $folderKeys, true)); @endphp
                                                    @if($missing->isNotEmpty())
                                                        <optgroup label="KLAES LGAs with no folder yet">
                                                            @foreach($missing as $name)
                                                                <option value="{{ $name }}" @selected(old('lga') === $name)>{{ $name }} — folder will be created</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                </select>
                                            </div>
                                            {{-- File number: picked from the file index, not typed blind.
                                                 file_indexings holds ~170,000 rows, so a plain <select> is not an
                                                 option — this searches the same endpoint the instrument-capture
                                                 File Number dropdown uses (api.file-numbers.indexing-search) and
                                                 shows matches as you type.

                                                 Picking a file also pre-selects its LGA folder, because the file
                                                 index already knows where the plot is and making the officer
                                                 choose it a second time is how a plan ends up in the wrong folder. --}}
                                            <div class="iw-field" x-data="filePicker()" @click.outside="close()">
                                                <label class="iw-label" for="tdp-number">File number <span class="req">*</span></label>
                                                {{-- A combobox, not a plain text field: it opens on click with the
                                                     file index already listed, and typing narrows it. A native
                                                     <select> is impossible here — file_indexings holds ~170,000
                                                     rows — so it reads as a dropdown and behaves as a search. --}}
                                                <div class="tdp-combo" :class="{ 'is-open': open }">
                                                    <input type="text" name="file_number" id="tdp-number"
                                                           class="iw-input iw-mono tdp-combo-input"
                                                           x-model="form.number" value="{{ old('file_number') }}"
                                                           @input.debounce.250ms="search()"
                                                           @focus="openList()" @click="openList()"
                                                           @keydown.escape.stop="close()"
                                                           @keydown.arrow-down.prevent="openList()"
                                                           autocomplete="off" placeholder="Select a file number…" required>
                                                    <button type="button" class="tdp-combo-caret" tabindex="-1"
                                                            @click="toggle()" :aria-expanded="open ? 'true' : 'false'"
                                                            aria-label="Show file numbers">
                                                        <i data-lucide="chevron-down" class="h-4 w-4"></i>
                                                    </button>
                                                    <div class="tdp-typeahead" x-show="open" x-cloak>
                                                        <template x-if="busy">
                                                            <div class="tdp-typeahead-note">Searching the file index…</div>
                                                        </template>
                                                        <template x-for="hit in hits" :key="hit.file_number">
                                                            <button type="button" class="tdp-typeahead-row" @click="choose(hit)">
                                                                <span class="iw-mono tdp-number" x-text="hit.file_number"></span>
                                                                <span class="tdp-typeahead-sub" x-text="hit.subtitle || '—'"></span>
                                                            </button>
                                                        </template>
                                                        <template x-if="!busy && noMatch">
                                                            <div class="tdp-typeahead-note">
                                                                No indexed file matches that. You may still file the plan under
                                                                the number as typed.
                                                            </div>
                                                        </template>
                                                        <template x-if="!busy && more">
                                                            <div class="tdp-typeahead-note">
                                                                More files match — keep typing to narrow the list.
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                                <span class="iw-help">
                                                    Saved as <code class="iw-mono" x-text="storedName() || '&lt;file number&gt;'"></code>
                                                </span>
                                            </div>
                                            <div class="iw-field full">
                                                <label class="iw-label" for="tdp-file">Plan file <span class="req">*</span></label>
                                                <input type="file" name="plan" id="tdp-file" class="iw-input" required
                                                       accept="{{ collect($allowedExtensions)->map(fn ($e) => '.' . $e)->implode(',') }}"
                                                       @change="picked($event)">
                                                <span class="iw-help">
                                                    {{ strtoupper(implode(', ', $allowedExtensions)) }} · up to {{ number_format($maxUploadKb / 1024, 0) }} MB.
                                                    The previous plan is renamed to <code class="iw-mono">&lt;name&gt;.{{ now()->format('Y-m-d-His') }}.bak.&lt;ext&gt;</code>, never deleted.
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between flex-wrap gap-2">
                                            <span class="iw-help" x-show="chosen" x-cloak>
                                                <i data-lucide="paperclip" class="h-3 w-3"></i> <span x-text="chosen"></span>
                                            </span>
                                            <button type="submit" class="iw-btn iw-btn-primary">
                                                <i data-lucide="upload" class="h-4 w-4"></i> Save to the GIS server
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif
                </section>
            </div>
        @endif

        {{-- ── Preview ───────────────────────────────────────────────────── --}}
        <div class="tdp-modal" x-show="open" x-cloak @keydown.escape.window="close()" style="display:none">
            <div class="tdp-modal-backdrop" @click="close()"></div>
            <div class="tdp-modal-panel" role="dialog" aria-modal="true">
                <div class="tdp-modal-head">
                    <div class="min-w-0">
                        <div class="tdp-modal-title" x-text="current.name"></div>
                        <div class="tdp-modal-sub"><span x-text="current.lga"></span> · <span class="iw-mono" x-text="current.number"></span></div>
                    </div>
                    <div class="tdp-actions">
                        <a :href="current.url + '&download=1'" class="iw-btn iw-btn-light iw-btn-sm">
                            <i data-lucide="download" class="h-4 w-4"></i> Download
                        </a>
                        <button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="close()">
                            <i data-lucide="x" class="h-4 w-4"></i> Close
                        </button>
                    </div>
                </div>
                <div class="tdp-modal-body">
                    <template x-if="current.kind === 'pdf'">
                        <iframe :src="current.url" class="tdp-frame" title="Title Deed Plan preview"></iframe>
                    </template>
                    <template x-if="current.kind === 'image'">
                        <img :src="current.url" class="tdp-image" alt="Title Deed Plan preview">
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }

    .tdp-store { display: flex; gap: 14px; align-items: flex-start; border-radius: 16px; padding: 16px 18px; border: 1px solid #e5e7eb; background: #fff; }
    .tdp-store-ready { border-color: #bbf7d0; background: #f6fffa; }
    .tdp-store-unreachable, .tdp-store-not_configured { border-color: #fecaca; background: #fff7f7; }
    .tdp-store-icon { width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex: none; background: #f3f4f6; color: #4b5563; }
    .tdp-store-ready .tdp-store-icon { background: #dcfce7; color: #15803d; }
    .tdp-store-unreachable .tdp-store-icon, .tdp-store-not_configured .tdp-store-icon { background: #fee2e2; color: #b91c1c; }
    .tdp-store-title { font-size: 15px; font-weight: 700; color: #111827; }
    .tdp-store-text { font-size: 13px; color: #4b5563; margin-top: 3px; }
    .tdp-store-root { text-align: right; font-size: 12px; color: #4b5563; flex: none; max-width: 340px; }
    .tdp-store-root-label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #9ca3af; }
    .tdp-store-root code { word-break: break-all; }
    .tdp-help { margin-top: 10px; border-top: 1px dashed #e5e7eb; padding-top: 10px; display: grid; gap: 6px; }
    .tdp-help-row { display: flex; gap: 10px; font-size: 12.5px; color: #4b5563; flex-wrap: wrap; }
    .tdp-help-row > span:first-child { width: 110px; flex: none; color: #9ca3af; text-transform: uppercase; font-size: 11px; letter-spacing: .05em; padding-top: 1px; }
    .tdp-help code, .iw-card code { background: #f3f4f6; border-radius: 5px; padding: 1px 5px; font-size: 12px; color: #374151; }

    .tdp-tiles { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
    .tdp-tile { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px 16px; }
    .tdp-tile-link:hover { border-color: #93c5fd; box-shadow: 0 1px 4px rgba(37, 99, 235, .12); }
    .tdp-tile-icon { width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex: none; }
    .tdp-tile-icon.blue { background: #eff6ff; color: #2563eb; }
    .tdp-tile-icon.green { background: #f0fdf4; color: #16a34a; }
    .tdp-tile-icon.purple { background: #f5f3ff; color: #7c3aed; }
    .tdp-tile-icon.red { background: #fee2e2; color: #b91c1c; }
    .tdp-tile-icon.gray { background: #f3f4f6; color: #6b7280; }
    .tdp-tile-value { font-size: 20px; font-weight: 700; color: #111827; line-height: 1.1; }
    .tdp-tile-label { font-size: 12px; color: #6b7280; display: flex; align-items: center; gap: 4px; margin-top: 2px; }

    .tdp-layout { display: grid; gap: 16px; grid-template-columns: 280px minmax(0, 1fr); align-items: start; }
    @media (max-width: 1024px) { .tdp-layout { grid-template-columns: 1fr; } }
    .tdp-rail { position: sticky; top: 12px; }
    .tdp-rail-body { padding: 8px; max-height: 620px; overflow-y: auto; }
    .tdp-rail-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; border-radius: 9px; font-size: 13.5px; color: #374151; }
    .tdp-rail-item:hover { background: #f3f4f6; color: #111827; }
    .tdp-rail-item.active { background: #eff6ff; color: #1d4ed8; font-weight: 600; }
    .tdp-rail-name { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .tdp-count { font-size: 11px; font-weight: 600; color: #6b7280; background: #f3f4f6; border-radius: 99px; padding: 1px 8px; flex: none; }
    .tdp-rail-item.active .tdp-count { background: #dbeafe; color: #1d4ed8; }
    .tdp-warn { color: #b45309; flex: none; }

    .tdp-search { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

    .tdp-table { width: 100%; font-size: 13.5px; border-collapse: collapse; }
    .tdp-table thead tr { background: #f9fafb; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
    .tdp-table th { padding: 11px 16px; font-weight: 600; white-space: nowrap; }
    .tdp-table td { padding: 11px 16px; border-top: 1px solid #f3f4f6; vertical-align: middle; }
    .tdp-table tbody tr:hover { background: #fafafa; }
    .tdp-file { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .tdp-file-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 320px; color: #111827; }
    .tdp-ext { font-size: 10px; font-weight: 700; letter-spacing: .04em; padding: 3px 6px; border-radius: 6px; background: #f3f4f6; color: #4b5563; flex: none; }
    .tdp-ext-pdf { background: #fee2e2; color: #b91c1c; }
    .tdp-ext-jpg, .tdp-ext-jpeg, .tdp-ext-png { background: #eff6ff; color: #1d4ed8; }
    .tdp-ext-tif, .tdp-ext-tiff { background: #f5f3ff; color: #6d28d9; }
    .tdp-number { font-size: 12.5px; color: #374151; }

    /* The File number combobox. It has to read as a dropdown beside the native <select>
       next to it, while actually being a search — file_indexings is far too large to put
       in a <select>. Tailwind 2.2 from the CDN has no arbitrary values, so this is here. */
    .tdp-combo { position: relative; }
    .tdp-combo-input { padding-right: 34px; }
    .tdp-combo-caret {
        position: absolute; top: 0; right: 0; height: 100%; width: 32px;
        display: flex; align-items: center; justify-content: center;
        border: 0; background: none; cursor: pointer; color: #6b7280;
    }
    .tdp-combo-caret:hover { color: #111827; }
    .tdp-combo.is-open .tdp-combo-caret { transform: rotate(180deg); }

    /* The File number typeahead. Tailwind 2.2 from the CDN has no arbitrary values,
       so the panel is styled here rather than with utility classes. */
    .tdp-typeahead {
        position: absolute; z-index: 30; left: 0; right: 0; top: calc(100% + 4px);
        max-height: 260px; overflow-y: auto;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .12);
    }
    .tdp-typeahead-row {
        display: block; width: 100%; text-align: left; padding: 8px 12px;
        border: 0; background: none; cursor: pointer; border-bottom: 1px solid #f3f4f6;
    }
    .tdp-typeahead-row:last-child { border-bottom: 0; }
    .tdp-typeahead-row:hover { background: #f8fafc; }
    .tdp-typeahead-sub {
        display: block; font-size: 11.5px; color: #6b7280; margin-top: 1px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tdp-typeahead-note { padding: 10px 12px; font-size: 12px; color: #6b7280; }
    .tdp-muted { color: #6b7280; font-size: 12.5px; white-space: nowrap; }
    .tdp-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: nowrap; white-space: nowrap; }
    .tdp-table td:last-child, .tdp-table th:last-child { white-space: nowrap; width: 1%; }
    .tdp-table td:nth-child(2), .tdp-table td:nth-child(3) { white-space: nowrap; }
    @media (max-width: 900px) { .tdp-actions { flex-wrap: wrap; } }
    .tdp-disabled { pointer-events: none; opacity: .45; }
    .tdp-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 9px; border: 1px solid #d1d5db; background: #fff; color: #4b5563; cursor: pointer; transition: background .15s, color .15s, border-color .15s; }
    .tdp-icon-btn:hover { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }

    .tdp-pill { display: inline-flex; align-items: center; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; background: #f3f4f6; color: #4b5563; white-space: nowrap; }
    .tdp-pill-state { text-transform: capitalize; }
    .tdp-pill.green { background: #dcfce7; color: #166534; }
    .tdp-pill.red { background: #fee2e2; color: #991b1b; }
    .tdp-pill.yellow { background: #fef9c3; color: #854d0e; }
    .tdp-pill.gray { background: #f3f4f6; color: #4b5563; }

    .tdp-modal { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .tdp-modal-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, .55); }
    .tdp-modal-panel { position: relative; background: #fff; border-radius: 16px; width: 100%; max-width: 1040px; height: 88vh; display: flex; flex-direction: column; box-shadow: 0 20px 45px rgba(15, 23, 42, .25); overflow: hidden; }
    .tdp-modal-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #f3f4f6; }
    .tdp-modal-title { font-size: 15px; font-weight: 700; color: #111827; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tdp-modal-sub { font-size: 12.5px; color: #6b7280; }
    .tdp-modal-body { flex: 1; background: #f3f4f6; overflow: auto; display: flex; align-items: center; justify-content: center; }
    .tdp-frame { width: 100%; height: 100%; border: 0; background: #fff; }
    .tdp-image { max-width: 100%; max-height: 100%; display: block; }
</style>

<script>
    function tdpScreen(config) {
        return {
            open: false,
            chosen: '',
            current: { name: '', number: '', lga: '', url: '', kind: 'pdf' },
            form: { lga: @json(old('lga', $lga)), number: @json(old('file_number', '')) },

            preview(item) {
                this.current = item;
                this.open = true;
                this.$nextTick(() => window.lucide && window.lucide.createIcons());
            },
            close() { this.open = false; },

            // "Replace" only prefills the upload form and scrolls to it: the
            // replacement itself is the ordinary upload, which keeps the old file.
            replace(item) {
                this.form.lga = item.lga;
                this.form.number = item.number;
                const card = document.getElementById('tdp-upload');
                if (card) { card.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            },

            picked(event) {
                const file = event.target.files && event.target.files[0];
                this.chosen = file ? file.name + ' · ' + Math.max(1, Math.round(file.size / 1024)) + ' KB' : '';
            },

            // Folder names currently in the store, lower-cased, so a picked file's
            // LGA can be matched to one without another round trip.
            lgaFolders: @json(collect($folders)->pluck('name')->values()),

            // Mirrors TdpLibrary::fileNameFor(): the number's separators become
            // hyphens, so the officer sees the name before saving.
            storedName() {
                const raw = (this.form.number || '').trim().toUpperCase();
                if (!raw) { return ''; }
                return raw.replace(/[\\/:*?"<>|\s;]+/g, '-').replace(/-+/g, '-').replace(/^[-.]+|[-.]+$/g, '');
            },
        };
    }

    /**
     * The File number field's typeahead over file_indexings.
     *
     * Nested inside tdpScreen, so `form` and `lgaFolders` resolve up the Alpine scope
     * chain to the parent — `form` is the same object, so writing to it here updates the
     * form the parent renders.
     *
     * It searches rather than listing: file_indexings holds ~170,000 rows and no dropdown
     * can hold that. The endpoint is the one the instrument-capture File Number field
     * already uses, so both screens agree on what counts as an indexed file.
     */
    function filePicker() {
        return {
            open: false,
            hits: [],
            busy: false,
            noMatch: false,
            more: false,
            lastQuery: null,

            openList() {
                this.open = true;
                this.search();
            },

            toggle() {
                if (this.open) { this.close(); return; }
                this.openList();
            },

            close() { this.open = false; },

            search() {
                const term = (this.form.number || '').trim();

                // An empty term is valid: opening the list shows the first page of the file
                // index, which is what makes this read as a dropdown rather than a search
                // box that stays blank until you guess a prefix.
                if (term === this.lastQuery && this.hits.length) { return; }
                this.lastQuery = term;

                this.busy = true;
                this.noMatch = false;

                fetch('{{ route('api.file-numbers.indexing-search') }}?per_page=25&q=' + encodeURIComponent(term), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(r => r.ok ? r.json() : Promise.reject(r.status))
                    .then(data => {
                        this.hits = (data && data.results) ? data.results : [];
                        this.more = !!(data && data.pagination && data.pagination.more);
                        this.noMatch = this.hits.length === 0;
                    })
                    .catch(() => {
                        // A failed lookup must not block filing a plan: the field stays
                        // typable and the officer is told nothing was found, not that the
                        // number is wrong.
                        this.hits = [];
                        this.more = false;
                        this.noMatch = true;
                    })
                    .finally(() => {
                        this.busy = false;
                        this.$nextTick(() => window.lucide && window.lucide.createIcons());
                    });
            },

            choose(hit) {
                this.form.number = hit.file_number;
                this.hits = [];
                this.more = false;
                this.noMatch = false;
                this.lastQuery = hit.file_number;
                this.close();

                // Pre-select the folder this plot belongs to, when the store has one by
                // that name. Case-insensitive: the file index and the folder names are
                // maintained by different people.
                const lga = (hit.lga || '').trim().toLowerCase();
                if (lga) {
                    const match = (this.lgaFolders || []).find(f => String(f).trim().toLowerCase() === lga);
                    if (match) { this.form.lga = match; }
                }
            },
        };
    }
</script>
@endsection
