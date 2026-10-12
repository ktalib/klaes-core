@extends('laas.layouts.portal')

@section('title', 'Dashboard — LAAS Portal')

@section('content')
@php use App\Models\Laas\LaasApplication; @endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-[var(--ink)] dark:text-[var(--ink)]">My applications</h1>
        <p class="mt-1 text-sm text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">
            Every land allocation application you have started, and where each one has reached.
        </p>
    </div>
    <a href="{{ route('laas.apply.form') }}"
       class="laas-btn inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition">
        <i data-lucide="file-plus-2" class="h-4 w-4"></i> New application
    </a>
</div>

@if($applications->isEmpty())
    <div class="rounded-2xl border border-dashed border-[var(--border)] bg-[var(--surface-card)] p-12 text-center dark:border-[var(--border)] dark:bg-[var(--surface-card)]">
        <i data-lucide="folder-open" class="mx-auto mb-4 h-10 w-10 text-[var(--ink-faint)] dark:text-[var(--ink-faint)]"></i>
        <p class="text-base font-semibold text-[var(--ink)] dark:text-[var(--ink)]">You have not applied yet</p>
        <p class="mx-auto mt-2 max-w-md text-sm text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">
            Start your first land allocation application. You will be told by SMS the moment it is received,
            and at every stage after that.
        </p>
        <a href="{{ route('laas.apply.form') }}"
           class="laas-btn mt-6 inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold transition">
            <i data-lucide="file-plus-2" class="h-4 w-4"></i> Start an application
        </a>
    </div>
@else
    @php
        $groups = [
            ['title' => null, 'rows' => $applications->reject->isReadOnly()],
            ['title' => 'Past files', 'rows' => $applications->filter->isReadOnly()],
        ];
    @endphp
    @foreach($groups as $group)
    @continue($group['rows']->isEmpty())
    @if($group['title'])
        <h2 class="mb-1 mt-10 text-sm font-extrabold uppercase tracking-widest text-[var(--ink-soft)]">{{ $group['title'] }}</h2>
        <p class="mb-4 text-sm text-[var(--ink-soft)]">Files retired by a parcel update or transferred to a new holder. They are kept here, read-only, as part of your history.</p>
    @endif
    <div class="grid gap-4 md:grid-cols-2">
        @foreach($group['rows'] as $application)
            @php $isDraft = $application->stage === LaasApplication::STAGE_DRAFT; @endphp

            <a href="{{ $isDraft ? route('laas.apply.form') : route('laas.application.show', $application->reference_no) }}"
               class="block rounded-2xl border border-[var(--border)] bg-[var(--surface-card)] p-5 shadow-sm transition hover:border-[var(--brand)] hover:shadow-md dark:border-[var(--border)] dark:bg-[var(--surface-card)] dark:hover:border-green-500">

                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-mono text-sm font-bold text-[var(--ink)] dark:text-[var(--ink)]">{{ $application->reference_no }}</p>
                        <p class="mt-0.5 truncate text-xs text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">
                            {{ $application->land_use ?: 'Land use not set' }}
                            @if($application->location) — {{ $application->location }} @endif
                        </p>
                    </div>

                    @if($application->isTransferredOut())
                        <span class="flex-shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide"
                              style="border-color: var(--border); color: var(--ink-soft);">Transferred</span>
                    @elseif($application->isRetired())
                        <span class="flex-shrink-0 rounded-full border border-amber-400 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">Retired</span>
                    @elseif($isDraft)
                        <span class="flex-shrink-0 rounded-full bg-[var(--brand-tint)] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-[var(--ink-soft)] dark:bg-[var(--brand-tint)] dark:text-[var(--ink-soft)]">Draft</span>
                    @elseif($application->stage === LaasApplication::STAGE_REJECTED)
                        <span class="flex-shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide"
                              style="border-color: var(--danger); color: var(--danger);">Not approved</span>
                    @elseif($application->stage === LaasApplication::STAGE_ROFO_SIGNED)
                        <span class="flex-shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide"
                              style="background: var(--brand); color: var(--on-brand);">Complete</span>
                    @else
                        <span class="flex-shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide"
                              style="border-color: var(--border); color: var(--ink-soft);">In progress</span>
                    @endif
                </div>

                @if($application->file_number)
                    <p class="mb-3 inline-flex items-center gap-1.5 rounded-md bg-[var(--brand-tint)] px-2 py-1 text-xs font-semibold text-[var(--brand)] dark:bg-[var(--brand-tint)] dark:text-[var(--brand)]">
                        <i data-lucide="hash" class="h-3 w-3"></i> {{ $application->file_number }}
                    </p>
                @endif

                @if($isDraft)
                    <p class="text-sm text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">Not submitted yet — continue where you left off.</p>
                @else
                    @include('laas.partials.stage-tracker', ['application' => $application, 'compact' => true])
                @endif

                <p class="mt-3 text-xs text-[var(--ink-faint)] dark:text-[var(--ink-faint)]">
                    {{ $application->submitted_at ? 'Submitted ' . $application->submitted_at->format('j M Y') : 'Started ' . $application->created_at->format('j M Y') }}
                </p>
            </a>
        @endforeach
    </div>
    @endforeach
@endif
@endsection
