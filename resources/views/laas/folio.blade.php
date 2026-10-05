@extends('laas.layouts.portal')

@section('title', 'Folio — LAAS Portal')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold" style="color: var(--ink);">Folio</h1>
        <p class="mt-1 text-sm" style="color: var(--ink-soft);">
            Every document on your file — scanned pages and the copies held by the Lands office.
        </p>
    </div>
    @if($selected)
        <a href="{{ route('laas.application.show', $selected->reference_no) }}"
           class="inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-bold transition hover:bg-[var(--brand-tint)]"
           style="border-color: var(--border); color: var(--ink-soft);">
            <i data-lucide="file-text" class="h-4 w-4" aria-hidden="true"></i> View application
        </a>
    @endif
</div>

@if($files->isEmpty())
    <div class="rounded-2xl border p-10 text-center" style="border-color: var(--border); background: var(--surface-card);">
        <i data-lucide="folder-open" class="mx-auto h-10 w-10" style="color: var(--ink-faint);" aria-hidden="true"></i>
        <p class="mt-3 text-sm font-semibold" style="color: var(--ink);">No files yet</p>
        <p class="mt-1 text-sm" style="color: var(--ink-soft);">Your documents will appear here once a file number has been commissioned for you.</p>
    </div>
@else
    @if($files->count() > 1)
        {{-- One chip per file the applicant holds. --}}
        <nav class="mb-6 flex gap-2 overflow-x-auto pb-1" aria-label="Your files">
            @foreach($files as $file)
                @php $isActive = $selected && $selected->id === $file->id; @endphp
                <a href="{{ route('laas.folio.index', ['file' => $file->reference_no]) }}" @if($isActive) aria-current="page" @endif
                   class="flex-shrink-0 rounded-xl border px-4 py-2.5 transition hover:border-[var(--brand)]"
                   style="{{ $isActive
                        ? 'border-color: var(--brand); background: var(--brand-tint);'
                        : 'border-color: var(--border); background: var(--surface-card);' }}">
                    <span class="block font-mono text-sm font-bold" style="color: {{ $isActive ? 'var(--brand)' : 'var(--ink)' }};">{{ $file->file_number }}</span>
                    <span class="block text-xs" style="color: var(--ink-faint);">{{ $file->reference_no }}</span>
                </a>
            @endforeach
        </nav>
    @endif

    @include('laas.partials.folio', ['application' => $selected, 'folio' => $folio])
@endif
@endsection
