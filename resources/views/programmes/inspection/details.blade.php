@extends('layouts.app')

@section('page-title')
{{ __('Inspection Details') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include($headerPartial ?? 'admin.header')

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        @php
            $editButtonClasses = 'inline-flex items-center gap-2 rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700';
            $returnUrl = request()->query('return');
            $showEditButton = true;

            if (!empty($returnUrl)) {
                $decodedReturn = urldecode($returnUrl);
                $queryString = parse_url($decodedReturn, PHP_URL_QUERY);
                $queryParams = [];
                if (!empty($queryString)) {
                    parse_str($queryString, $queryParams);
                }
                $returnMode = $queryParams['url'] ?? null;
                $showEditButton = $returnMode === null || $returnMode === 'view';
            }

            // Disable edit if inspection is approved
            if (!empty($is_approved) && ($is_approved === true || $is_approved == 1)) {
                $showEditButton = false;
            }
        @endphp
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 hidden">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $subjectLabel }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-900">{{ __('Joint Site Inspection') }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ $actionHint }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    <span>{{ __('Back') }}</span>
                </a>
            </div>
        </div>

        @if($showEditButton)
            <div class="flex items-center justify-end">
                @if($canEditDetails)
                    <a href="{{ $editUrl }}" class="{{ $editButtonClasses }} hover:bg-indigo-100">
                        <i data-lucide="pencil" class="h-4 w-4"></i>
                        <span>{{ __('Edit Details') }}</span>
                    </a>
                @else
                    <span class="{{ $editButtonClasses }} opacity-60 cursor-not-allowed" aria-disabled="true" @if($editDisabledReason) title="{{ $editDisabledReason }}" @endif>
                        <i data-lucide="pencil" class="h-4 w-4"></i>
                        <span>{{ __('Edit Details') }}</span>
                    </span>
                @endif
            </div>
        @endif

        @if(!$canEditDetails && $editDisabledReason)
            <p class="text-xs text-slate-500">{{ $editDisabledReason }}</p>
        @endif

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $statusMeta['badgeClasses'] }}">
                                <i data-lucide="{{ $statusMeta['icon'] ?? 'file-text' }}" class="h-3.5 w-3.5"></i>
                                <span>{{ $statusMeta['label'] }}</span>
                            </span>
                            @if(!empty($statusMeta['timestamp']))
                                <span class="text-xs text-slate-500">
                                    {{ __('Updated :timestamp', ['timestamp' => $statusMeta['timestamp']]) }}
                                </span>
                            @endif
                        </div>
                        <p class="text-sm text-slate-600 max-w-3xl">{{ $statusMeta['description'] }}</p>
                        @if(!empty($inspectionOfficerValue))
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm">
                                    <i data-lucide="user-check" class="h-3.5 w-3.5 text-slate-500"></i>
                                    <span>{{ $inspectionOfficerLabel }}</span>
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full bg-slate-800 px-3 py-1 text-xs font-semibold text-white">
                                    <i data-lucide="shield" class="h-3.5 w-3.5 text-white/80"></i>
                                    <span>{{ $inspectionOfficerValue }}</span>
                                </span>
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-col items-start gap-3 lg:items-end hidden">
                        <div class="flex flex-wrap gap-3">
                            <button id="jsiDetailsGenerate"
                                class="inline-flex items-center gap-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-60 disabled:cursor-not-allowed"
                                @if(!$canGenerate) disabled @endif>
                                <i data-lucide="file-output" class="h-4 w-4"></i>
                                <span>{{ __('Generate Report') }}</span>
                            </button>
                            @unless(str_contains(request()->fullUrl(), '2Fplanning_recomm%3Furl%3Dview'))
                                <button id="jsiDetailsApprove"
                                    class="inline-flex items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100 disabled:opacity-60 disabled:cursor-not-allowed"
                                    @if(!$canApprove) disabled @endif>
                                    <i data-lucide="badge-check" class="h-4 w-4"></i>
                                    <span>{{ __('Approve Report') }}</span>
                                </button>
                            @endunless
                        </div>
                        <div class="text-xs text-slate-500 space-y-1 hidden">
                            @if($generateDisabledReason)
                                <p>{{ __('Generate: :reason', ['reason' => $generateDisabledReason]) }}</p>
                            @endif
                            @if($approveDisabledReason)
                                <p>{{ __('Approve: :reason', ['reason' => $approveDisabledReason]) }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-6 space-y-8">
                <section class="space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <i data-lucide="info" class="h-5 w-5 text-slate-500"></i>
                        <span>{{ __('Inspection Summary') }}</span>
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach($generalInfo as $item)
                            <div class="{{ $item['wrapperClasses'] }}">
                                <div class="{{ $item['accentClasses'] }}"></div>
                                <div class="relative z-10 flex items-start gap-3">
                                    @if(!empty($item['icon']))
                                        <span class="{{ $item['iconContainerClasses'] }}">
                                            <i data-lucide="{{ $item['icon'] }}" class="h-5 w-5"></i>
                                        </span>
                                    @endif
                                    <div class="flex-1">
                                        <p class="{{ $item['labelClasses'] }}">{{ $item['label'] }}</p>
                                        <p class="mt-1 {{ $item['valueClasses'] }}">{!! nl2br(e($item['value'])) !!}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <i data-lucide="ruler" class="h-5 w-5 text-slate-500"></i>
                            <span>{{ __('Existing Site Measurements') }}</span>
                        </h2>
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ __('Reference only') }}</span>
                    </div>
                    <p class="text-sm text-slate-600">{{ $measurementSummary }}</p>
                    @if(!empty($measurementEntries))
                        <div class="overflow-hidden rounded-2xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left font-semibold">{{ __('S/N') }}</th>
                                        <th scope="col" class="px-4 py-3 text-left font-semibold">{{ __('Description') }}</th>
                                        <th scope="col" class="px-4 py-3 text-left font-semibold">{{ __('Dimension / Size') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach($measurementEntries as $entry)
                                        <tr>
                                            <td class="px-4 py-3 text-slate-600">{{ $entry['sn'] ?? $loop->iteration }}</td>
                                            <td class="px-4 py-3 text-slate-800">{{ $entry['description'] ?? __('Not specified') }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $entry['dimension'] !== '' ? $entry['dimension'] : __('Not specified') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                            {{ __('No measurement entries have been recorded for this inspection.') }}
                        </div>
                    @endif
                </section>

                <section class="space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <i data-lucide="clipboard-check" class="h-5 w-5 text-slate-500"></i>
                        <span>{{ __('Ground Verification') }}</span>
                    </h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Available on Ground?') }}</p>
                            @php
                                $availability = null;
                                if (!is_null($availableOnGround)) {
                                    $normalized = is_bool($availableOnGround) ? $availableOnGround : in_array(strtolower((string) $availableOnGround), ['yes', 'true', '1'], true);
                                    $availability = $normalized ? __('Yes') : __('No');
                                }
                            @endphp
                            <p class="mt-2 text-base font-semibold text-slate-900">
                                {{ $availability ?? __('Not captured') }}
                            </p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Additional Observations') }}</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-700">
                                {{ $additionalObservations ? $additionalObservations : __('None recorded') }}
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<form id="jsiDetailsContext" class="hidden">
    @csrf
    <input type="hidden" name="application_id" value="{{ $targetApplicationId ?? '' }}">
    <input type="hidden" name="sub_application_id" value="{{ $targetSubApplicationId ?? '' }}">
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        const generateBtn = document.getElementById('jsiDetailsGenerate');
        const approveBtn = document.getElementById('jsiDetailsApprove');
        const contextForm = document.getElementById('jsiDetailsContext');
        const tokenInput = contextForm.querySelector('input[name="_token"]');
        const applicationId = contextForm.querySelector('input[name="application_id"]').value;
        const subApplicationId = contextForm.querySelector('input[name="sub_application_id"]').value;

        async function postStatusUpdate(payload) {
            const response = await fetch('{{ route('joint-inspection.update-status') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': tokenInput.value
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.success) {
                const message = data.message || '{{ __('Unable to update the inspection status. Please try again.') }}';
                throw new Error(message);
            }

            return data;
        }

        function withBusyState(button, callback) {
            if (!button) return;
            if (button.disabled) return;
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="flex items-center gap-2"><svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" opacity="0.25"></circle><path d="M22 12a10 10 0 0 1-10 10" opacity="0.75"></path></svg><span>{{ __('Processing...') }}</span></span>';

            Promise.resolve()
                .then(callback)
                .then(() => window.location.reload())
                .catch(error => {
                    console.error(error);
                    alert(error.message);
                })
                .finally(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
        }

        if (generateBtn) {
            generateBtn.addEventListener('click', function () {
                withBusyState(generateBtn, () => {
                    if (!applicationId) {
                        throw new Error('{{ __('Application ID is required to generate the report.') }}');
                    }

                    return postStatusUpdate({
                        application_id: applicationId || null,
                        sub_application_id: subApplicationId || null,
                        is_generated: true,
                        generated_at: new Date().toISOString()
                    });
                });
            });
        }

        if (approveBtn) {
            approveBtn.addEventListener('click', function () {
                if (!confirm('{{ __('Approve this inspection report? This action will lock further edits.') }}')) {
                    return;
                }

                withBusyState(approveBtn, () => {
                    if (!applicationId) {
                        throw new Error('{{ __('Application ID is required to approve the report.') }}');
                    }

                    return postStatusUpdate({
                        application_id: applicationId || null,
                        sub_application_id: subApplicationId || null,
                        is_submitted: true,
                        submitted_at: new Date().toISOString()
                    });
                });
            });
        }
    });
</script>
@endsection
