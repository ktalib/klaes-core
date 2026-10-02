@extends('layouts.app')
@section('page-title')
    {{ __('File Merger Management') }}
@endsection
@section('content')
<div class="flex-1 overflow-y-auto overflow-x-hidden">
    @include('admin.header')

    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 space-y-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i data-lucide="git-merge" class="h-5 w-5 text-indigo-600"></i>
                File Merger Management
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Group files that will be merged together, or that function as a single unit. Grouped files
                share one lineage on the File Tracking Sheet / File Movement History.
            </p>
        </div>

        @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-4 w-4 shrink-0"></i>{{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 flex items-center gap-2">
            <i data-lucide="alert-triangle" class="h-4 w-4 shrink-0"></i>{{ session('error') }}
        </div>
        @endif
        @if($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        {{-- Create a new group --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Create a Merger Group</h2>
            <p class="text-xs text-gray-500 mb-4">
                Pick each child file with the file number selector    
            </p>

            <form method="POST" action="{{ route('file-merger.store') }}" id="merger-form">
                @csrf
                <div id="file-rows" class="space-y-2"></div>

                <button type="button" id="add-file-row"
                    class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                    <i data-lucide="plus" class="h-4 w-4"></i> Add file
                </button>

                <div class="mt-5 flex justify-end">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                        <i data-lucide="save" class="h-4 w-4"></i> Save
                    </button>
                </div>
            </form>
        </div>

        {{-- Existing groups --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-base font-semibold text-gray-900 mb-4">
                Existing Groups <span class="text-sm font-normal text-gray-400">({{ count($groups) }})</span>
            </h2>

            @forelse($groups as $group)
            <div class="border border-gray-200 rounded-lg mb-3 overflow-hidden">
                <div class="flex items-center justify-between gap-3 bg-gray-50 px-4 py-2 border-b border-gray-200">
                    <div class="min-w-0">
                        <span class="font-mono text-xs font-semibold text-gray-700">{{ $group['merger_id'] }}</span>
                        @if(($group['source'] ?? '') === 'manual')
                            <span class="ml-2 inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Manual</span>
                        @else
                            <span class="ml-2 inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-semibold text-gray-600">Auto</span>
                        @endif
                        <span class="ml-2 text-xs text-gray-400">{{ count($group['members']) }} files</span>
                    </div>
                    <form method="POST" action="{{ route('file-merger.destroy', $group['merger_id']) }}"
                          onsubmit="return confirm('Remove this group? This only deletes the grouping, not the files.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-rose-500 hover:text-rose-700" title="Delete group">
                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                        </button>
                    </form>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach($group['members'] as $m)
                    <li class="flex items-start justify-between gap-2 px-4 py-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $m->file_number }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $m->file_title ?: '—' }}{{ $m->location ? ' · ' . $m->location : '' }}</p>
                        </div>
                        @if($m->role === 'parent')
                            <span class="shrink-0 inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700">Source</span>
                        @else
                            <span class="shrink-0 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Result / Member</span>
                        @endif
                    </li>
                    @endforeach
                </ul>
            </div>
            @empty
            <p class="text-sm text-gray-500">No groups yet.</p>
            @endforelse
        </div>
    </div>
</div>

<template id="file-row-template">
    <div class="file-row flex items-center gap-2">
        <div class="flex-1 min-w-0">
            <div class="flex">
                <input type="text" data-role="file-input" readonly placeholder="Click Select to pick a file…"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-l-md shadow-sm text-sm bg-gray-50 focus:outline-none">
                <button type="button" data-role="pick-file"
                    class="inline-flex items-center gap-1 px-3 border border-l-0 border-gray-300 rounded-r-md bg-white text-sm text-gray-600 hover:text-indigo-600 hover:bg-indigo-50">
                    <i data-lucide="search" class="h-4 w-4"></i> Select
                </button>
            </div>
            <p data-role="file-meta" class="text-xs text-gray-400 mt-0.5 truncate"></p>
        </div>
        <select data-role="role-input"
            class="shrink-0 px-2 py-2 border border-gray-300 rounded-md shadow-sm text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="parent">Source (merged in)</option>
            <option value="child" selected>Result / Member</option>
        </select>
        <button type="button" data-role="remove-row" class="shrink-0 text-gray-400 hover:text-rose-600" title="Remove">
            <i data-lucide="x" class="h-4 w-4"></i>
        </button>
    </div>
</template>

{{-- Global file number selector modal --}}
@include('components.global-fileno-modal')

<script src="{{ asset('js/global-fileno-modal.js') }}?v={{ @filemtime(public_path('js/global-fileno-modal.js')) }}"></script>
<script>
(function () {
    const rowsWrap = document.getElementById('file-rows');
    const tmpl = document.getElementById('file-row-template');
    const form = document.getElementById('merger-form');
    const lookupUrl = @json(route('file-merger.lookup'));
    let index = 0;

    function enrichMeta(fileInput, meta, fallbackTitle) {
        const val = (fileInput.value || '').trim();
        if (!val) { meta.textContent = ''; return; }
        meta.textContent = fallbackTitle || 'Looking up…';
        fetch(`${lookupUrl}?file_number=${encodeURIComponent(val)}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.ok ? r.json() : null)
        .then(d => {
            if (!d || !d.exists) {
                meta.textContent = fallbackTitle || 'Not found in records (will still be saved).';
                return;
            }
            meta.textContent = [d.title || fallbackTitle || '—', d.location].filter(Boolean).join(' · ');
        })
        .catch(() => { meta.textContent = fallbackTitle || ''; });
    }

    function bindRow(row) {
        const i = index++;
        const fileInput = row.querySelector('[data-role="file-input"]');
        const roleInput = row.querySelector('[data-role="role-input"]');
        const meta = row.querySelector('[data-role="file-meta"]');
        fileInput.name = `files[${i}][file_number]`;
        roleInput.name = `files[${i}][role]`;

        row.querySelector('[data-role="remove-row"]').addEventListener('click', function () {
            row.remove();
        });

        row.querySelector('[data-role="pick-file"]').addEventListener('click', function () {
            if (typeof GlobalFileNoModal === 'undefined') {
                alert('File number selector is not available.');
                return;
            }
            GlobalFileNoModal.open({
                callback: function (data) {
                    const fn = (data && (data.fileNumber || data.file_number)) || '';
                    if (!fn) return;
                    fileInput.value = fn;
                    enrichMeta(fileInput, meta, (data && (data.file_title || data.file_name)) || '');
                }
            });
        });
    }

    function addRow() {
        const node = tmpl.content.firstElementChild.cloneNode(true);
        rowsWrap.appendChild(node);
        bindRow(node);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    document.getElementById('add-file-row').addEventListener('click', addRow);

    // Start with two rows.
    addRow();
    addRow();

    form.addEventListener('submit', function (e) {
        const filled = Array.from(rowsWrap.querySelectorAll('[data-role="file-input"]'))
            .filter(i => i.value.trim() !== '').length;
        if (filled < 2) {
            e.preventDefault();
            alert('Add at least two files to form a group.');
        }
    });
})();
</script>
@endsection
