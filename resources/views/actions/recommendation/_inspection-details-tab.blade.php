{{-- Inspection Details Tab --}}
@php
    $inspectionDetailsTabActive = ($activeTab ?? null) === 'inspection-details';
@endphp
<div id="inspection-details-tab" class="tab-content {{ $inspectionDetailsTabActive ? 'active' : '' }}">
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-4 border-b bg-slate-50 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <i data-lucide="layout-dashboard" class="w-5 h-5 text-indigo-600"></i>
                    <span>Inspection Details</span>
                </h3>
                <p class="text-sm text-slate-600 mt-1">Review the captured joint site inspection inputs, summary, and ground observations.</p>
            </div>
            @if(!empty($jointInspectionReport))
                @php
                    $returnParam = ['return' => request()->fullUrl()];
                    $detailsRoute = route('planning-recommendation.joint-inspection.details', array_merge(['application' => $application->id], $returnParam));
                    $editRoute = route('planning-recommendation.joint-inspection.edit', array_merge(['application' => $application->id], $returnParam));
                    $report = $jointInspectionReport;
                    $canEditInspection = !$report->is_generated && !$report->is_submitted;
                @endphp
                <div class="flex flex-wrap gap-3 hidden">
                    <a href="{{ $detailsRoute }}" target="_blank" rel="noopener" class="inline-flex items-center px-3 py-2 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100 transition">
                        <i data-lucide="external-link" class="w-4 h-4 mr-1.5"></i>
                        <span>Open Full Details</span>
                    </a>
                    @if($canEditInspection)
                        <a href="{{ $editRoute }}" class="inline-flex items-center px-3 py-2 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 transition">
                            <i data-lucide="file-edit" class="w-4 h-4 mr-1.5"></i>
                            <span>Edit Inspection</span>
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <div class="p-6 space-y-8">
            @if(empty($jointInspectionReport))
                <div class="text-center py-12">
                    <div class="mx-auto w-14 h-14 bg-slate-100 rounded-full flex items-center justify-center mb-4">
                        <i data-lucide="file-x" class="w-7 h-7 text-slate-400"></i>
                    </div>
                    <h4 class="text-lg font-semibold text-slate-800 mb-2">No Inspection Details Available</h4>
                    <p class="text-sm text-slate-600 max-w-md mx-auto">Capture and save the joint site inspection data to unlock this tab. Once the inspection is generated, the summary will appear here.</p>
                </div>
            @else
                @php
                    $report = $jointInspectionReport;

                    $statusMeta = [
                        'label' => __('Not Captured'),
                        'classes' => 'bg-slate-100 text-slate-700 border border-slate-200',
                        'icon' => 'file-warning',
                        'description' => __('No inspection details have been captured for this application yet.'),
                        'timestamp' => null,
                    ];

                    if ($report->is_submitted) {
                        $statusMeta = [
                            'label' => __('Approved'),
                            'classes' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                            'icon' => 'badge-check',
                            'description' => __('The inspection report has been approved and locked from further edits.'),
                            'timestamp' => optional($report->submitted_at)->format('d M Y \a\t h:i A'),
                        ];
                    } elseif ($report->is_generated) {
                        $statusMeta = [
                            'label' => __('Generated'),
                            'classes' => 'bg-blue-100 text-blue-700 border border-blue-200',
                            'icon' => 'file-output',
                            'description' => __('The report is generated and awaiting approval.'),
                            'timestamp' => optional($report->generated_at)->format('d M Y \a\t h:i A'),
                        ];
                    } else {
                        $statusMeta = [
                            'label' => __(''),
                            'classes' => 'bg-amber-100 text-amber-700 border border-amber-200',
                            'icon' => 'pencil',
                            'description' => __(''),
                            'timestamp' => optional($report->updated_at ?? $report->created_at)->format('d M Y \a\t h:i A'),
                        ];
                    }

                    $formatDate = fn ($value) => $value ? optional(
                        $value instanceof \Carbon\Carbon ? $value : \Carbon\Carbon::parse($value)
                    )->format('d M Y') : __('Not captured');

                    $complianceMap = [
                        'obtainable' => __('Obtainable'),
                        'not_obtainable' => __('Not Obtainable'),
                    ];

                    $generalInfo = [
                        ['label' => __('Inspection Date'), 'value' => $formatDate($report->inspection_date), 'icon' => 'calendar'],
                        ['label' => __('LPKN Number'), 'value' => $report->lkn_number ?? ($jointInspectionDefaults['lkn_number'] ?? __('Not captured')), 'icon' => 'hash'],
                        ['label' => __('Applicant'), 'value' => $report->applicant_name ?? ($jointInspectionDefaults['applicant_name'] ?? __('Not captured')), 'icon' => 'user'],
                        ['label' => __('Location'), 'value' => $report->location ?? ($jointInspectionDefaults['location'] ?? __('Not captured')), 'icon' => 'map-pin'],
                        ['label' => __('Plot Number'), 'value' => $report->plot_number ?? ($jointInspectionDefaults['plot_number'] ?? __('Not captured')), 'icon' => 'layers'],
                        ['label' => __('Scheme Number'), 'value' => $report->scheme_number ?? ($jointInspectionDefaults['scheme_number'] ?? __('Not captured')), 'icon' => 'compass'],
                        ['label' => __('Road Reservation'), 'value' => $report->road_reservation ?? __('Not captured'), 'icon' => 'route'],
                        ['label' => __('Compliance Status'), 'value' => $report->compliance_status ? ($complianceMap[$report->compliance_status] ?? ucfirst($report->compliance_status)) : __('Not captured'), 'icon' => 'shield-check'],
                        ['label' => __('Applied Land Use'), 'value' => $report->applied_land_use ?? __('Not captured'), 'icon' => 'clipboard-list'],
                        ['label' => __('Prevailing Land Use'), 'value' => $report->prevailing_land_use ?? __('Not captured'), 'icon' => 'building-2'],
                    ];

                    $availableOnGround = $report->available_on_ground;
                    $availableOnGroundLabel = is_null($availableOnGround)
                        ? __('Not specified')
                        : ($availableOnGround ? __('Yes') : __('No'));

                    $measurementSummary = $report->existing_site_measurement_summary
                        ?? ($defaultMeasurementSummaryMessage ?? __('No measurement summary provided.'));

                    $measurementRows = collect([]);
                    if (is_array($report->existing_site_measurement_entries) && !empty($report->existing_site_measurement_entries)) {
                        $measurementRows = collect($report->existing_site_measurement_entries)
                            ->filter(fn ($entry) => !empty($entry))
                            ->reject(function ($entry) {
                                // Drop internal meta-sentinel rows (__need_for_eia__,
                                // __conversion_reservation_meta__, __existing_structure__, etc.).
                                $desc = trim((string) ($entry['description'] ?? $entry['utility_type'] ?? ''));
                                return preg_match('/^__.+__$/', $desc) === 1;
                            })
                            ->values()
                            ->map(function ($entry, $index) {
                                $description = trim((string) ($entry['description'] ?? $entry['utility_type'] ?? ''));
                                $dimension = trim((string) ($entry['dimension'] ?? $entry['measurement'] ?? ''));
                                return [
                                    'sn' => $entry['sn'] ?? $index + 1,
                                    'description' => $description !== '' ? $description : __('Not specified'),
                                    'dimension' => $dimension !== '' ? $dimension : __('Not specified'),
                                ];
                            });
                    }

                    if ($measurementRows->isEmpty() && !empty($measurementEntries)) {
                        $measurementRows = collect($measurementEntries)
                            ->filter(fn ($entry) => is_array($entry))
                            ->values()
                            ->map(function ($entry, $index) {
                                $description = trim((string) ($entry['description'] ?? ''));
                                $dimension = trim((string) ($entry['dimension'] ?? ''));
                                return [
                                    'sn' => $entry['sn'] ?? $index + 1,
                                    'description' => $description !== '' ? $description : __('Not specified'),
                                    'dimension' => $dimension !== '' ? $dimension : __('Not specified'),
                                ];
                            });
                    }

                    $sharedUtilities = [];
                    if (is_array($report->shared_utilities) && !empty($report->shared_utilities)) {
                        $sharedUtilities = array_filter(array_map('trim', $report->shared_utilities));
                    } elseif (!empty($selectedSharedUtilities) && is_array($selectedSharedUtilities)) {
                        $sharedUtilities = array_filter(array_map('trim', $selectedSharedUtilities));
                    }

                    $additionalObservations = $report->has_additional_observations ? trim((string) ($report->additional_observations ?? '')) : '';
                @endphp

                <section class="space-y-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold {{ $statusMeta['classes'] }}">
                                <i data-lucide="{{ $statusMeta['icon'] }}" class="w-4 h-4"></i>
                                <span>{{ $statusMeta['label'] }}</span>
                            </span>
                            @if(!empty($statusMeta['timestamp']))
                                <span class="text-xs text-slate-500">{{ __('Updated :timestamp', ['timestamp' => $statusMeta['timestamp']]) }}</span>
                            @endif
                        </div>
                        @if(!empty($report->inspection_officer))
                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-800 text-xs font-semibold text-white">
                                <i data-lucide="user-check" class="w-4 h-4 text-white/80"></i>
                                <span>{{ $report->inspection_officer }}</span>
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-slate-600">{{ $statusMeta['description'] }}</p>
                </section>

                <section class="space-y-4">
                    <h4 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">{{ __('Inspection Snapshot') }}</h4>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($generalInfo as $info)
                            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                                <div class="flex items-start gap-3">
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-100 text-slate-600">
                                        <i data-lucide="{{ $info['icon'] }}" class="w-5 h-5"></i>
                                    </span>
                                    <div class="flex-1">
                                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ $info['label'] }}</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">{!! nl2br(e($info['value'])) !!}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">{{ __('Ground Availability & Utilities') }}</h4>
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-slate-200 text-xs font-medium text-slate-600">
                            <i data-lucide="radar" class="w-4 h-4"></i>
                            <span>{{ __('Available on Ground: :status', ['status' => $availableOnGroundLabel]) }}</span>
                        </span>
                    </div>

                    @if(!empty($sharedUtilities))
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($sharedUtilities as $utility)
                                <div class="border border-emerald-200 bg-emerald-50 rounded-lg px-4 py-3 text-sm text-emerald-700 flex items-center gap-2">
                                    <i data-lucide="plug" class="w-4 h-4"></i>
                                    <span>{{ $utility }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500">{{ __('No shared utilities were recorded for this inspection.') }}</p>
                    @endif
                </section>

                <section class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">{{ __('Existing Site Measurements') }}</h4>
                        <span class="text-xs uppercase tracking-wide text-slate-400">{{ __('Reference only') }}</span>
                    </div>
                    <p class="text-sm text-slate-600">{{ $measurementSummary }}</p>
                    @if($measurementRows->isNotEmpty())
                        <div class="overflow-hidden border border-slate-200 rounded-xl">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left font-semibold">{{ __('S/N') }}</th>
                                        <th scope="col" class="px-4 py-3 text-left font-semibold">{{ __('Description') }}</th>
                                        <th scope="col" class="px-4 py-3 text-left font-semibold">{{ __('Dimension / Size') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach($measurementRows as $row)
                                        <tr>
                                            <td class="px-4 py-3 text-slate-600">{{ $row['sn'] }}</td>
                                            <td class="px-4 py-3 text-slate-800">{{ $row['description'] }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $row['dimension'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="border border-dashed border-slate-200 rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                            {{ __('No measurement entries have been recorded for this inspection.') }}
                        </div>
                    @endif
                </section>

                @if($additionalObservations !== '')
                    <section class="space-y-3">
                        <h4 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">{{ __('Additional Observations') }}</h4>
                        <div class="border border-slate-200 rounded-xl bg-white p-4 text-sm text-slate-700 whitespace-pre-line">
                            {{ $additionalObservations }}
                        </div>
                    </section>
                @endif
            @endif
        </div>
    </div>
</div>
