@extends('layouts.app')
@section('page-title')
    {{ __('Report & Manual') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header', [
        'PageTitle' => 'Report & Manual',
        'PageDescription' => 'Reports and user manuals published for the Ministry',
    ])

    <div class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-800 px-6 py-3 flex items-center gap-3 shadow-sm">
        <i data-lucide="library" class="h-5 w-5 text-white shrink-0"></i>
        <div class="flex items-center gap-2">
            <span class="text-white font-bold text-sm uppercase tracking-widest">Document Library</span>
            <span class="text-slate-300 text-sm">&middot;</span>
            <span class="text-white text-sm font-medium">Reports &amp; Manuals</span>
        </div>
    </div>

    <div class="p-4 sm:p-6">
        <div class="bg-white rounded-lg shadow-sm border overflow-hidden" style="height: calc(100vh - 180px);">
            <div class="flex h-full">
                <!-- Sidebar: categories + documents -->
                <aside class="w-80 border-r bg-gray-50 flex flex-col">
                    <div class="p-3 border-b flex gap-2" id="doc-tabs">
                        @foreach($docs as $i => $cat)
                            <button type="button"
                                class="doc-tab flex-1 px-3 py-2 rounded-md text-sm font-semibold transition {{ $i === 0 ? 'bg-slate-700 text-white' : 'bg-white text-gray-600 border hover:bg-gray-100' }}"
                                data-cat="{{ $cat['key'] }}">
                                {{ $cat['label'] }}
                                <span class="ml-1 text-xs opacity-70">({{ count($cat['files']) }})</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="flex-1 overflow-auto p-2 space-y-4">
                        @foreach($docs as $cat)
                            <div class="doc-list space-y-2" data-cat="{{ $cat['key'] }}" @if(!$loop->first) style="display:none;" @endif>
                                @forelse($cat['files'] as $file)
                                    <button type="button"
                                        class="doc-item w-full text-left p-3 border rounded-md bg-white hover:border-slate-400 hover:shadow-sm transition flex items-start gap-3"
                                        data-url="{{ $file['url'] }}"
                                        data-name="{{ $file['name'] }}">
                                        <span class="shrink-0 w-9 h-9 rounded bg-red-50 text-red-600 flex items-center justify-center">
                                            <i data-lucide="file-text" class="h-5 w-5"></i>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-medium text-gray-800 truncate">{{ $file['name'] }}</span>
                                            <span class="block text-xs text-gray-400">{{ number_format($file['size'] / 1024, 0) }} KB &middot; PDF</span>
                                        </span>
                                    </button>
                                @empty
                                    <p class="text-sm text-gray-400 text-center py-6">No documents in this category.</p>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                </aside>

                <!-- Viewer -->
                <section class="flex-1 flex flex-col bg-gray-100 min-w-0">
                    <div class="flex items-center justify-between px-4 py-2 border-b bg-white">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-gray-800 truncate" id="doc-viewer-title">Select a document</div>
                            <div class="text-xs text-gray-400" id="doc-viewer-sub">Reports &amp; manuals preview</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a id="doc-open-tab" href="#" target="_blank" rel="noopener"
                               class="btn btn-ghost btn-sm border flex items-center gap-1 pointer-events-none opacity-50">
                                <i data-lucide="external-link" class="h-4 w-4"></i><span class="text-xs">Open</span>
                            </a>
                            <a id="doc-download" href="#" download
                               class="btn btn-sm bg-slate-700 hover:bg-slate-800 text-white flex items-center gap-1 pointer-events-none opacity-50">
                                <i data-lucide="download" class="h-4 w-4"></i><span class="text-xs">Download</span>
                            </a>
                        </div>
                    </div>

                    <div class="flex-1 relative">
                        <iframe id="doc-frame" title="Document preview" class="absolute inset-0 w-full h-full hidden" frameborder="0"></iframe>
                        <div id="doc-empty" class="absolute inset-0 flex flex-col items-center justify-center text-center text-gray-400 gap-3">
                            <i data-lucide="file-search" class="h-12 w-12 text-gray-300"></i>
                            <p class="text-sm">Pick a report or manual from the left to preview it here.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Category tabs
    document.querySelectorAll('.doc-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            const cat = tab.dataset.cat;
            document.querySelectorAll('.doc-tab').forEach(function (t) {
                const active = t === tab;
                t.classList.toggle('bg-slate-700', active);
                t.classList.toggle('text-white', active);
                t.classList.toggle('bg-white', !active);
                t.classList.toggle('text-gray-600', !active);
                t.classList.toggle('border', !active);
            });
            document.querySelectorAll('.doc-list').forEach(function (list) {
                list.style.display = (list.dataset.cat === cat) ? '' : 'none';
            });
        });
    });

    // Document selection -> load into iframe
    const frame = document.getElementById('doc-frame');
    const empty = document.getElementById('doc-empty');
    const title = document.getElementById('doc-viewer-title');
    const sub = document.getElementById('doc-viewer-sub');
    const openTab = document.getElementById('doc-open-tab');
    const download = document.getElementById('doc-download');

    document.querySelectorAll('.doc-item').forEach(function (item) {
        item.addEventListener('click', function () {
            const url = item.dataset.url;
            const name = item.dataset.name;

            document.querySelectorAll('.doc-item').forEach(function (i) {
                i.classList.remove('border-slate-500', 'bg-slate-50', 'ring-1', 'ring-slate-300');
            });
            item.classList.add('border-slate-500', 'bg-slate-50', 'ring-1', 'ring-slate-300');

            frame.src = url + '#toolbar=1&view=FitH';
            frame.classList.remove('hidden');
            empty.classList.add('hidden');

            title.textContent = name;
            sub.textContent = 'PDF document';

            openTab.href = url;
            download.href = url;
            [openTab, download].forEach(function (a) { a.classList.remove('pointer-events-none', 'opacity-50'); });
        });
    });

    if (window.lucide) lucide.createIcons();
});
</script>
@endsection
