@extends('layouts.app')

@section('page-title')
    {{ __('LAAS — ') . $application->reference_no }}
@endsection

@section('content')
@php
    use App\Models\Laas\LaasApplication;

    $canApprove  = $application->stage === LaasApplication::STAGE_SUBMITTED;
    $canAssign   = $application->stage === LaasApplication::STAGE_DIRECTOR_APPROVED && !$application->file_number;
    $canReject   = !$application->hasReached(LaasApplication::STAGE_FILENO_ASSIGNED)
                   && $application->stage !== LaasApplication::STAGE_REJECTED;
@endphp

<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'LAAS Application ' . $application->reference_no,
        'PageDescription' => 'Review a portal application, approve it, and assign its file number.'
    ])

    <div class="py-8 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">

            <a href="{{ route('laas-admin.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to queue
            </a>

            @if(session('status'))
                <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-800">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="font-mono text-2xl font-extrabold tracking-tight text-slate-900">{{ $application->reference_no }}</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $application->applicant_name }} · {{ $application->applicant_phone }}
                    </p>
                </div>
                <div class="text-right">
                    <span class="rounded-full bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-800">
                        {{ LaasApplication::label($application->stage) }}
                    </span>
                    @if($application->file_number)
                        <p class="mt-2 font-mono text-lg font-bold text-green-700">{{ $application->file_number }}</p>
                    @endif
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">

                {{-- Actions --}}
                <div class="space-y-5 lg:col-span-1">

                    @if($canApprove)
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="mb-1 text-sm font-extrabold uppercase tracking-widest text-slate-700">Director's decision</h2>
                            <p class="mb-4 text-xs text-slate-500">Approving notifies the applicant at once and opens file-number assignment.</p>

                            <form method="POST" action="{{ route('laas-admin.approve', $application->id) }}" class="space-y-3">
                                @csrf
                                <textarea name="remarks" rows="2" placeholder="Remarks to the applicant (optional)"
                                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                                <button type="submit" class="w-full rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-800">
                                    <i data-lucide="check" class="mr-1.5 inline h-4 w-4"></i> Approve application
                                </button>
                            </form>
                        </div>
                    @endif

                    @if($canAssign)
                        <div class="rounded-xl border border-green-300 bg-green-50 p-5 shadow-sm">
                            <h2 class="mb-1 text-sm font-extrabold uppercase tracking-widest text-green-800">Assign File Number</h2>
                            <p class="mb-4 text-xs text-green-700">
                                The serial is drawn from the same stream as counter allocations, so the number cannot collide with one issued elsewhere.
                            </p>

                            <form method="POST" action="{{ route('laas-admin.assign-file-number', $application->id) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label for="prefix" class="block text-xs font-semibold text-green-900">Prefix</label>
                                    <select id="prefix" name="prefix" required class="mt-1.5 w-full rounded-lg border border-green-300 bg-white px-3 py-2 text-sm">
                                        <option value="">Select a prefix…</option>
                                        @foreach($prefixes as $prefix)
                                            <option value="{{ $prefix->prefix }}">{{ $prefix->prefix }}</option>
                                        @endforeach
                                    </select>
                                    @if($prefixes->isEmpty())
                                        <p class="mt-1.5 text-xs text-red-700">No prefixes are configured — check the prefix table before allocating.</p>
                                    @endif
                                </div>
                                <button type="submit" class="w-full rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-800">
                                    <i data-lucide="hash" class="mr-1.5 inline h-4 w-4"></i> Generate &amp; assign
                                </button>
                            </form>
                        </div>
                    @endif

                    @if($canReject)
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="mb-1 text-sm font-extrabold uppercase tracking-widest text-red-700">Decline</h2>
                            <p class="mb-4 text-xs text-slate-500">The reason is texted to the applicant, so write it for them to read.</p>

                            <form method="POST" action="{{ route('laas-admin.reject', $application->id) }}" class="space-y-3"
                                  onsubmit="return confirm('Decline this application? The applicant will be notified immediately.');">
                                @csrf
                                <textarea name="rejection_reason" rows="3" required placeholder="Reason for declining…"
                                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                                <button type="submit" class="w-full rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">
                                    <i data-lucide="x" class="mr-1.5 inline h-4 w-4"></i> Decline application
                                </button>
                            </form>
                        </div>
                    @endif

                    @if($application->file_number)
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="mb-3 text-sm font-extrabold uppercase tracking-widest text-slate-700">Next steps</h2>
                            <p class="mb-4 text-xs text-slate-500">
                                This file now moves through the normal Land modules. Each one updates the applicant automatically.
                            </p>
                            <div class="space-y-2">
                                <a href="{{ route('survey-report.index') }}"
                                   class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                    Lands 12 — Survey Report <i data-lucide="arrow-up-right" class="h-4 w-4 text-slate-400"></i>
                                </a>
                                <a href="{{ route('land-recommendations.index') }}"
                                   class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                    Land Recommendation <i data-lucide="arrow-up-right" class="h-4 w-4 text-slate-400"></i>
                                </a>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Detail + history --}}
                <div class="space-y-6 lg:col-span-2">

                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="mb-4 text-sm font-extrabold uppercase tracking-widest text-slate-700">Application details</h2>

                        {{-- Everything the applicant answered, rendered from the same
                             schema the form was built from, so the two cannot drift. --}}
                        @include('laas.partials.answers', [
                            'sections'  => $sections,
                            'answers'   => $answers,
                            'typeLabel' => $typeLabel,
                        ])

                        <dl class="mt-6 grid gap-x-6 gap-y-4 border-t border-slate-200 pt-5 sm:grid-cols-3">
                            @foreach([
                                'Reference'  => $application->reference_no,
                                'Land type'  => $typeLabel,
                                'Submitted'  => $application->submitted_at?->format('j M Y, g:ia'),
                            ] as $term => $value)
                                @if($value)
                                    <div>
                                        <dt class="text-xs font-medium text-slate-500">{{ $term }}</dt>
                                        <dd class="mt-0.5 text-sm text-slate-900">{{ $value }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    </div>

                    @if($documents->isNotEmpty())
                        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h2 class="mb-4 text-sm font-extrabold uppercase tracking-widest text-slate-700">Documents</h2>
                            <ul class="space-y-2">
                                @foreach($documents as $doc)
                                    <li class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-2.5">
                                        <i data-lucide="paperclip" class="h-4 w-4 flex-shrink-0 text-slate-400"></i>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-slate-900">
                                                {{ $docTypes[$doc->doc_type]['label'] ?? $doc->doc_type }}
                                            </span>
                                            <span class="block truncate text-xs text-slate-500">{{ $doc->original_name }}</span>
                                        </span>
                                        <span class="flex-shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-600">{{ $doc->source }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-4">
                            <h2 class="text-sm font-extrabold uppercase tracking-widest text-slate-700">History</h2>
                        </div>
                        @if($events->isEmpty())
                            <p class="p-6 text-sm text-slate-500">Nothing recorded yet.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach($events as $event)
                                    <li class="px-6 py-4">
                                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                                            <p class="text-sm font-semibold text-slate-900">{{ $event->title }}</p>
                                            <p class="text-xs text-slate-400">{{ $event->created_at->format('j M Y, g:ia') }}</p>
                                        </div>
                                        @if($event->body)
                                            <p class="mt-1 text-sm text-slate-600">{{ $event->body }}</p>
                                        @endif
                                        <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                                            <span>{{ $event->actor_name ?: ucfirst($event->actor_type) }}</span>
                                            @if($event->sms_status)
                                                <span>·</span>
                                                <span class="{{ $event->sms_status === 'sent' ? 'text-green-600' : 'text-amber-600' }}">
                                                    SMS {{ $event->sms_status }}{{ $event->sms_to ? ' — ' . $event->sms_to : '' }}
                                                </span>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
