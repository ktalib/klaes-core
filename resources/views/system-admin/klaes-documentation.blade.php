@extends('layouts.app')
@section('page-title')
    {{ __('KLAES Documentation') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header', [
        'PageTitle' => 'KLAES Documentation',
        'PageDescription' => 'Manuals, SoPs, system documentation and quarterly reports',
    ])

    <div class="bg-gradient-to-r from-gray-700 via-gray-600 to-gray-800 px-6 py-3 flex items-center gap-3 shadow-sm">
        <i data-lucide="library" class="h-5 w-5 text-white flex-shrink-0"></i>
        <div class="flex items-center gap-2">
            <span class="text-white font-bold text-sm uppercase tracking-widest">System Admin</span>
            <span class="text-gray-300 text-sm">&middot;</span>
            <span class="text-white text-sm font-medium">KLAES Documentation</span>
        </div>
    </div>

    <div class="p-4 sm:p-6">
        <div class="bg-white rounded-lg shadow-sm border overflow-hidden" style="height: calc(100vh - 180px); min-height: 520px;">
            <div class="flex h-full">
                {{-- Sections + documents --}}
                <aside class="border-r bg-gray-50 flex flex-col" style="width: 340px; flex-shrink: 0;">
                    <div class="p-3 border-b grid grid-cols-2 gap-2" id="kd-tabs">
                        @foreach($sections as $i => $section)
                            <button type="button"
                                class="kd-tab px-2 py-2 rounded-md text-xs font-semibold transition flex items-center justify-center gap-1 {{ $i === 0 ? 'bg-gray-700 text-white' : 'bg-white text-gray-600 border hover:bg-gray-100' }}"
                                data-section="{{ $section['key'] }}">
                                <i data-lucide="{{ $section['icon'] }}" class="h-3.5 w-3.5"></i>
                                <span>{{ $section['label'] }}</span>
                                <span class="opacity-70">({{ count($section['files']) }})</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="px-3 pt-3">
                        <input type="search" id="kd-filter" placeholder="Filter documents…"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400">
                    </div>

                    <div class="flex-1 overflow-auto p-3">
                        @foreach($sections as $section)
                            <div class="kd-list space-y-2" data-section="{{ $section['key'] }}" @if(!$loop->first) style="display:none;" @endif>
                                @php $lastQuarter = false; @endphp
                                @forelse($section['files'] as $doc)
                                    @if($section['key'] === 'reports' && $doc['quarter'] !== $lastQuarter)
                                        <div class="kd-group text-xs font-bold tracking-wider text-gray-500 pt-2">
                                            {{ $doc['quarter'] ? strtoupper($doc['quarter']) : 'OTHER REPORTS' }}
                                        </div>
                                        @php $lastQuarter = $doc['quarter']; @endphp
                                    @endif
                                    <button type="button"
                                        class="kd-item w-full text-left p-3 border rounded-md bg-white hover:border-gray-400 hover:shadow-sm transition flex items-start gap-3"
                                        data-url="{{ $doc['url'] }}"
                                        data-download="{{ $doc['download_url'] }}"
                                        data-name="{{ $doc['name'] }}"
                                        data-meta="{{ $section['label'] }}{{ $doc['quarter'] ? ' · ' . $doc['quarter'] : '' }}">
                                        <span class="flex-shrink-0 w-9 h-9 rounded bg-red-50 text-red-600 flex items-center justify-center">
                                            <i data-lucide="file-text" class="h-5 w-5"></i>
                                        </span>
                                        <span class="flex-1" style="min-width: 0;">
                                            <span class="block text-sm font-medium text-gray-800">{{ $doc['name'] }}</span>
                                            <span class="block text-xs text-gray-400 mt-0.5">
                                                {{ number_format($doc['size'] / 1048576, 1) }} MB &middot; PDF &middot; {{ date('d M Y', $doc['updated']) }}
                                            </span>
                                        </span>
                                    </button>
                                @empty
                                    <div class="text-center py-8">
                                        <i data-lucide="folder-open" class="h-8 w-8 mx-auto text-gray-300"></i>
                                        <p class="text-sm text-gray-500 mt-2">No {{ $section['label'] }} published yet.</p>
                                    </div>
                                @endforelse
                                <p class="kd-no-match text-sm text-gray-400 text-center py-6" style="display:none;">No documents match.</p>
                            </div>
                        @endforeach
                    </div>
                </aside>

                {{-- Viewer --}}
                <section class="flex-1 flex flex-col bg-gray-100" style="min-width: 0;">
                    <div class="flex items-center justify-between px-4 py-2 border-b bg-white">
                        <div style="min-width: 0;">
                            <div class="text-sm font-semibold text-gray-800 truncate" id="kd-title">Select a document</div>
                            <div class="text-xs text-gray-400" id="kd-sub">Manuals, SoPs, system documentation and quarterly reports</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a id="kd-open" href="#" target="_blank" rel="noopener"
                               class="btn btn-ghost btn-sm border flex items-center gap-1 pointer-events-none opacity-50">
                                <i data-lucide="external-link" class="h-4 w-4"></i><span class="text-xs">Open</span>
                            </a>
                            <a id="kd-download" href="#"
                               class="btn btn-sm bg-gray-700 hover:bg-gray-800 text-white flex items-center gap-1 pointer-events-none opacity-50">
                                <i data-lucide="download" class="h-4 w-4"></i><span class="text-xs">Download</span>
                            </a>
                        </div>
                    </div>

                    <div class="flex-1 relative">
                        <iframe id="kd-frame" title="Document preview" class="absolute inset-0 w-full h-full hidden" frameborder="0"></iframe>
                        <div id="kd-empty" class="absolute inset-0 flex flex-col items-center justify-center text-center text-gray-400 gap-3">
                            <i data-lucide="file-search" class="h-12 w-12 text-gray-300"></i>
                            <p class="text-sm">Pick a document on the left to read it here.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filter = document.getElementById('kd-filter');

    function activeList() {
        return Array.from(document.querySelectorAll('.kd-list')).find((l) => l.style.display !== 'none');
    }

    function applyFilter() {
        const list = activeList();
        if (!list) return;
        const term = filter.value.trim().toLowerCase();
        let shown = 0;
        list.querySelectorAll('.kd-item').forEach(function (item) {
            const match = !term || item.dataset.name.toLowerCase().includes(term);
            item.style.display = match ? '' : 'none';
            if (match) shown++;
        });
        // A quarter heading stays only while one of its reports is showing.
        list.querySelectorAll('.kd-group').forEach(function (group) {
            let next = group.nextElementSibling, any = false;
            while (next && !next.classList.contains('kd-group') && !next.classList.contains('kd-no-match')) {
                if (next.classList.contains('kd-item') && next.style.display !== 'none') any = true;
                next = next.nextElementSibling;
            }
            group.style.display = any ? '' : 'none';
        });
        const none = list.querySelector('.kd-no-match');
        if (none) none.style.display = (term && shown === 0 && list.querySelector('.kd-item')) ? '' : 'none';
    }

    document.querySelectorAll('.kd-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.kd-tab').forEach(function (t) {
                const active = t === tab;
                t.classList.toggle('bg-gray-700', active);
                t.classList.toggle('text-white', active);
                t.classList.toggle('bg-white', !active);
                t.classList.toggle('text-gray-600', !active);
                t.classList.toggle('border', !active);
            });
            document.querySelectorAll('.kd-list').forEach(function (list) {
                list.style.display = list.dataset.section === tab.dataset.section ? '' : 'none';
            });
            applyFilter();
        });
    });

    filter.addEventListener('input', applyFilter);

    const frame = document.getElementById('kd-frame');
    const open = document.getElementById('kd-open');
    const download = document.getElementById('kd-download');

    document.querySelectorAll('.kd-item').forEach(function (item) {
        item.addEventListener('click', function () {
            document.querySelectorAll('.kd-item').forEach(function (i) {
                i.classList.remove('border-gray-500', 'bg-gray-50', 'ring-1', 'ring-gray-300');
            });
            item.classList.add('border-gray-500', 'bg-gray-50', 'ring-1', 'ring-gray-300');

            frame.src = item.dataset.url + '#toolbar=1&view=FitH';
            frame.classList.remove('hidden');
            document.getElementById('kd-empty').classList.add('hidden');

            document.getElementById('kd-title').textContent = item.dataset.name;
            document.getElementById('kd-sub').textContent = item.dataset.meta;

            open.href = item.dataset.url;
            download.href = item.dataset.download;
            [open, download].forEach(function (a) { a.classList.remove('pointer-events-none', 'opacity-50'); });
        });
    });

    if (window.lucide) lucide.createIcons();
});
</script>
@endsection
