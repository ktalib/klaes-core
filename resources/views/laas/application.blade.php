@extends('laas.layouts.portal')

@section('title', $application->reference_no . ' — LAAS Portal')

@section('content')
@php
    use App\Models\Laas\LaasApplication;
    use App\Models\Laas\LaasDocument;

    $officeDocs    = $documents->where('source', LaasDocument::SOURCE_OFFICE);
    $applicantDocs = $documents->where('source', LaasDocument::SOURCE_APPLICANT);
@endphp

<div class="mb-6">
    <a href="{{ route('laas.dashboard') }}" class="mb-3 inline-flex items-center gap-2 text-sm font-medium text-[var(--ink-soft)] hover:text-[var(--ink)] dark:text-[var(--ink-soft)] dark:hover:text-[var(--ink)]">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> All applications
    </a>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="font-mono text-2xl font-bold text-[var(--ink)] dark:text-[var(--ink)]">{{ $application->reference_no }}</h1>
            <p class="mt-1 text-sm text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">
                {{ $application->land_use }}
                @if($application->location) — {{ $application->location }} @endif
            </p>
        </div>

        @if($application->file_number)
            <div class="rounded-xl border border-[var(--brand-line)] bg-[var(--brand-tint)] px-4 py-3 dark:border-[var(--brand-line)] dark:bg-[var(--brand-tint)]">
                <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--brand)] dark:text-[var(--brand)]">Your File Number</p>
                <p class="mt-0.5 font-mono text-lg font-bold text-[var(--brand)] dark:text-[var(--brand)]">{{ $application->file_number }}</p>
            </div>
        @endif
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">

    <!-- Progress -->
    <div class="lg:col-span-1">
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface-card)] p-6 dark:border-[var(--border)] dark:bg-[var(--surface-card)]">
            <h2 class="mb-5 text-sm font-extrabold uppercase tracking-widest text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">Progress</h2>
            @include('laas.partials.stage-tracker', ['application' => $application])
        </div>
    </div>

    <div class="space-y-6 lg:col-span-2">

        <!-- Timeline -->
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface-card)] dark:border-[var(--border)] dark:bg-[var(--surface-card)]">
            <div class="border-b border-[var(--border)] px-6 py-4 dark:border-[var(--border)]">
                <h2 class="text-sm font-extrabold uppercase tracking-widest text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">What has happened</h2>
            </div>

            @if($events->isEmpty())
                <p class="p-6 text-sm text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">Nothing recorded yet.</p>
            @else
                <ul class="divide-y divide-[var(--border)] dark:divide-[var(--border)]">
                    @foreach($events as $event)
                        <li class="flex gap-4 px-6 py-4">
                            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-[var(--brand-tint)] text-[var(--brand)] dark:bg-[var(--brand-tint)] dark:text-[var(--brand)]">
                                <i data-lucide="check" class="h-4 w-4"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="text-sm font-semibold text-[var(--ink)] dark:text-[var(--ink)]">{{ $event->title }}</p>
                                    <p class="text-xs text-[var(--ink-faint)] dark:text-[var(--ink-faint)]">{{ $event->created_at->format('j M Y, g:ia') }}</p>
                                </div>
                                @if($event->body)
                                    <p class="mt-1 text-sm text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">{{ $event->body }}</p>
                                @endif
                                @if($event->sms_status === 'sent')
                                    <p class="mt-1.5 inline-flex items-center gap-1 text-xs text-[var(--brand)] dark:text-[var(--brand)]">
                                        <i data-lucide="message-square-check" class="h-3 w-3"></i> Texted to {{ $event->sms_to }}
                                    </p>
                                @elseif($event->sms_status === 'failed')
                                    <p class="mt-1.5 inline-flex items-center gap-1 text-xs" style="color: var(--danger);">
                                        <i data-lucide="message-square-x" class="h-3 w-3"></i> SMS could not be delivered to {{ $event->sms_to }}
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <!-- Documents ready for the applicant -->
        @if($officeDocs->isNotEmpty())
            <div class="rounded-2xl border border-[var(--brand-line)] bg-[var(--brand-tint)] p-6 dark:border-[var(--brand-line)] dark:bg-[var(--brand-tint)]">
                <h2 class="mb-4 flex items-center gap-2 text-sm font-extrabold uppercase tracking-widest text-[var(--brand)] dark:text-[var(--brand)]">
                    <i data-lucide="file-check-2" class="h-4 w-4"></i> Ready for you
                </h2>
                <ul class="space-y-2">
                    @foreach($officeDocs as $doc)
                        <li>
                            <a href="{{ route('laas.application.documents.download', [$application->reference_no, $doc->id]) }}"
                               class="flex items-center justify-between gap-3 rounded-lg border border-[var(--brand-line)] bg-[var(--surface-card)] px-4 py-3 transition hover:border-[var(--brand)] dark:border-[var(--brand-line)] dark:bg-[var(--surface-card)]">
                                <span class="flex min-w-0 items-center gap-3">
                                    <i data-lucide="file-text" class="h-4 w-4 flex-shrink-0 text-[var(--brand)] dark:text-[var(--brand)]"></i>
                                    <span class="truncate text-sm font-medium text-[var(--ink)] dark:text-[var(--ink)]">
                                        {{ $docTypes[$doc->doc_type]['label'] ?? ucfirst(str_replace('_', ' ', $doc->doc_type)) }}
                                    </span>
                                </span>
                                <i data-lucide="download" class="h-4 w-4 flex-shrink-0 text-[var(--ink-faint)]"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Applicant uploads -->
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface-card)] p-6 dark:border-[var(--border)] dark:bg-[var(--surface-card)]">
            <h2 class="mb-4 text-sm font-extrabold uppercase tracking-widest text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">Your documents</h2>

            @if($applicantDocs->isNotEmpty())
                <ul class="mb-5 space-y-2">
                    @foreach($applicantDocs as $doc)
                        <li class="flex items-center justify-between gap-3 rounded-lg border border-[var(--border)] px-4 py-2.5 dark:border-[var(--border)]">
                            <span class="flex min-w-0 items-center gap-3">
                                <i data-lucide="paperclip" class="h-4 w-4 flex-shrink-0 text-[var(--ink-faint)]"></i>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-[var(--ink)] dark:text-[var(--ink)]">
                                        {{ $docTypes[$doc->doc_type]['label'] ?? $doc->doc_type }}
                                    </span>
                                    <span class="block truncate text-xs text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">{{ $doc->original_name }}</span>
                                </span>
                            </span>
                            <a href="{{ route('laas.application.documents.download', [$application->reference_no, $doc->id]) }}"
                               class="flex-shrink-0 rounded-md p-2 text-[var(--ink-faint)] hover:bg-[var(--brand-tint)] hover:text-[var(--ink)] dark:hover:bg-[var(--brand-tint)]">
                                <i data-lucide="download" class="h-4 w-4"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('laas.application.documents.upload', $application->reference_no) }}"
                  enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-[180px] flex-1">
                    <label for="doc_type" class="block text-xs font-medium text-[var(--ink)] dark:text-[var(--ink-soft)]">Document type</label>
                    <select id="doc_type" name="doc_type" required
                            class="mt-1.5 block w-full rounded-lg border border-[var(--border)] bg-[var(--surface-card)] px-3 py-2 text-sm dark:border-[var(--border)] dark:bg-[var(--brand-tint)] dark:text-[var(--ink)]">
                        @foreach($docTypes as $key => $meta)
                            <option value="{{ $key }}">{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[180px] flex-1">
                    <label for="file" class="block text-xs font-medium text-[var(--ink)] dark:text-[var(--ink-soft)]">File (PDF or image, max 5&nbsp;MB)</label>
                    <input id="file" type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png"
                           class="mt-1.5 block w-full rounded-lg border border-[var(--border)] bg-[var(--surface-card)] px-3 py-1.5 text-sm file:mr-3 file:rounded file:border-0 file:bg-[var(--brand-tint)] file:px-3 file:py-1.5 file:text-xs file:font-semibold dark:border-[var(--border)] dark:bg-[var(--brand-tint)] dark:text-[var(--ink)] dark:file:bg-[var(--border)] dark:file:text-[var(--ink)]">
                </div>
                <button type="submit" class="laas-btn rounded-lg px-4 py-2 text-sm font-semibold transition">
                    <i data-lucide="upload" class="mr-1.5 inline h-4 w-4"></i> Upload
                </button>
            </form>
        </div>

        <!-- Submitted details -->
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface-card)] p-6 dark:border-[var(--border)] dark:bg-[var(--surface-card)]">
            <h2 class="mb-4 text-sm font-extrabold uppercase tracking-widest text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">What you submitted</h2>

            @include('laas.partials.answers', [
                'sections'  => $sections,
                'answers'   => $answers,
                'typeLabel' => $typeLabel,
            ])

            @if($application->submitted_at)
                <p class="mt-6 border-t border-[var(--border)] pt-4 text-xs text-[var(--ink-faint)] dark:border-[var(--border)] dark:text-[var(--ink-faint)]">
                    Submitted {{ $application->submitted_at->format('j M Y, g:ia') }}
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
