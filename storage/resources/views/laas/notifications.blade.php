@extends('laas.layouts.portal')

@section('title', 'Updates — LAAS Portal')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[var(--ink)] dark:text-[var(--ink)]">Updates</h1>
    <p class="mt-1 text-sm text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">
        Every update on your applications, newest first. These are the same messages sent to your phone.
    </p>
</div>

@if($events->isEmpty())
    <div class="rounded-2xl border border-dashed border-[var(--border)] bg-[var(--surface-card)] p-12 text-center dark:border-[var(--border)] dark:bg-[var(--surface-card)]">
        <i data-lucide="bell-off" class="mx-auto mb-4 h-10 w-10 text-[var(--ink-faint)] dark:text-[var(--ink-faint)]"></i>
        <p class="text-base font-semibold text-[var(--ink)] dark:text-[var(--ink)]">No updates yet</p>
        <p class="mt-2 text-sm text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">Updates appear here as soon as your application moves.</p>
    </div>
@else
    <div class="overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--surface-card)] dark:border-[var(--border)] dark:bg-[var(--surface-card)]">
        <ul class="divide-y divide-[var(--border)] dark:divide-[var(--border)]">
            @foreach($events as $event)
                <li class="flex gap-4 p-5">
                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-[var(--brand-tint)] text-[var(--brand)] dark:bg-[var(--brand-tint)] dark:text-[var(--brand)]">
                        <i data-lucide="bell" class="h-4 w-4"></i>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm font-semibold text-[var(--ink)] dark:text-[var(--ink)]">{{ $event->title }}</p>
                            <p class="text-xs text-[var(--ink-faint)] dark:text-[var(--ink-faint)]">{{ $event->created_at->format('j M Y, g:ia') }}</p>
                        </div>

                        @if($event->body)
                            <p class="mt-1 text-sm text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">{{ $event->body }}</p>
                        @endif

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <a href="{{ route('laas.application.show', $event->reference_no) }}"
                               class="font-mono text-xs font-semibold text-[var(--brand)] hover:underline dark:text-[var(--brand)]">{{ $event->reference_no }}</a>

                            @if($event->file_number)
                                <span class="text-xs text-[var(--ink-faint)] dark:text-[var(--ink-faint)]">·</span>
                                <span class="text-xs text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">{{ $event->file_number }}</span>
                            @endif

                            @if($event->sms_status === 'sent')
                                <span class="inline-flex items-center gap-1 rounded-full bg-[var(--brand-tint)] px-2 py-0.5 text-[10px] font-semibold text-[var(--brand)] dark:bg-[var(--brand-tint)] dark:text-[var(--brand)]">
                                    <i data-lucide="message-square-check" class="h-3 w-3"></i> Sent to {{ $event->sms_to }}
                                </span>
                            @elseif($event->sms_status === 'failed')
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                    <i data-lucide="message-square-x" class="h-3 w-3"></i> SMS could not be delivered
                                </span>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
