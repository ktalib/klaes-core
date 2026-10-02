@extends('layouts.app')

@php
    use App\Models\MasterJsiEvidence;
    use App\Models\MasterJsiPurpose;
    use App\Models\MasterJsiReport;
    use App\Support\MasterJsiPortionTemplates;
    use App\Support\MasterJsiSubjectResolver;
    use App\Support\ParcelSizeSummary;

    $statusTones = [
        MasterJsiReport::STATUS_DRAFT     => 'bg-slate-100 text-slate-600',
        MasterJsiReport::STATUS_GENERATED => 'bg-blue-100 text-blue-700',
        MasterJsiReport::STATUS_SUBMITTED => 'bg-amber-100 text-amber-700',
        MasterJsiReport::STATUS_APPROVED  => 'bg-emerald-100 text-emerald-700',
        MasterJsiReport::STATUS_REJECTED  => 'bg-red-100 text-red-700',
    ];

    $isLegacy = $report->isLegacyLayout();

    $sqm = function ($value) {
        return ($value === null || (float) $value <= 0) ? null : ParcelSizeSummary::number((float) $value) . ' m²';
    };

    $labelOf = function (?string $slug) {
        return $slug ? (MasterJsiPurpose::PURPOSES[$slug] ?? ucfirst(str_replace('_', ' ', $slug))) : null;
    };
@endphp

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => 'Master JSI ' . $report->jsi_ref,
        'PageDescription' => 'Physical Planning joint site inspection for a parcel update.',
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

        {{-- Header --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm px-7 py-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center shrink-0">
                        <i data-lucide="ruler" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-xl font-black text-slate-800 leading-tight">{{ $report->jsi_ref }}</h1>
                            <span class="text-[10px] font-black px-2 py-1 rounded-md {{ $statusTones[$report->status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ strtoupper($report->status) }}
                            </span>
                            <span class="text-[10px] font-black px-2 py-1 rounded-md {{ $report->category === 'APU' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $report->category }}
                            </span>
                            @if ($report->isReturned())
                                <span class="text-[10px] font-black px-2 py-1 rounded-md bg-rose-100 text-rose-700">
                                    RETURNED {{ $report->returned_at?->format('d M Y') }}
                                </span>
                            @endif
                            @if ($report->supervisor_decision && $report->supervisor_decision !== 'returned')
                                <span class="text-[10px] font-black px-2 py-1 rounded-md bg-slate-100 text-slate-600">
                                    SUPERVISOR: {{ strtoupper($report->supervisor_decision) }}
                                </span>
                            @endif
                            @if ($report->sent_to_deeds_at)
                                <span class="text-[10px] font-black px-2 py-1 rounded-md bg-indigo-50 text-indigo-600">
                                    SENT TO DEEDS {{ $report->sent_to_deeds_at->format('d M Y') }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ ucwords(str_replace('_', ' ', $report->parcel_update_type)) }}
                            @if ($report->file_number) · File {{ $report->file_number }} @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('master-jsi.print', $report->id) }}" target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-800 text-white rounded-xl text-xs font-bold hover:bg-slate-900 transition">
                        <i data-lucide="printer" class="w-4 h-4"></i> Print sheet
                    </a>
                    @if (!$report->isApproved())
                    <a href="{{ route('master-jsi.edit', $report->id) }}"
                        class="inline-flex items-center gap-2 px-4 py-2.5 border border-slate-200 text-slate-600 rounded-xl text-xs font-bold hover:border-slate-300 transition">
                        <i data-lucide="pencil" class="w-4 h-4"></i> Edit
                    </a>
                    @endif
                    <a href="{{ route('master-jsi.index') }}" class="text-xs font-bold text-slate-400 hover:text-slate-600 px-3 py-2">Back</a>
                </div>
            </div>

            @if ($report->status === MasterJsiReport::STATUS_REJECTED && $report->rejected_reason)
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                <p class="text-[10px] uppercase tracking-wider font-black text-red-500">Rejected</p>
                <p class="text-sm text-red-700 mt-1">{{ $report->rejected_reason }}</p>
            </div>
            @endif
        </div>

        {{-- Purpose (new template) --}}
        @if (!$isLegacy)
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm px-6 py-5">
            <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Purpose of the inspection</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @forelse ($report->purposeSlugs() as $slug)
                    <span class="text-xs font-black px-2.5 py-1 rounded-lg {{ in_array($slug, ['merger', 'extension'], true) ? 'bg-slate-100 text-slate-600' : ($slug === 'subdivision' ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-700') }}">
                        {{ $labelOf($slug) }}
                    </span>
                @empty
                    <span class="text-xs text-slate-400">Not recorded.</span>
                @endforelse
            </div>
        </div>
        @endif

        {{-- Application details --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Application &amp; Site</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
                @foreach ([
                    ['Applicant', \App\Support\PersonName::display($report->applicant_name)],
                    ['File Title', $report->file_title],
                    ['Inspection Date', $report->inspection_date?->format('d M Y')],
                    ['Plot Number', $report->plot_number],
                    ['Location', $report->location],
                    ['District', $report->district],
                    ['LGA', $report->lga],
                    ['Existing Site Dimensions (m)', $report->site_existing_dimensions],
                    ['Existing Site Area', $sqm($report->site_existing_area_sqm)],
                    ['Recommended Total', $sqm($report->recommendedTotalSiteAreaSqm())],
                    ['Recommended Override', $report->recommended_total_override_sqm !== null ? $sqm($report->recommended_total_override_sqm) : null],
                    ['Override Reason', $report->override_reason],
                    ['Location Coordinates', $report->location_coordinates],
                    ['Inspection Officer', $report->inspection_officer],
                ] as [$label, $val])
                    @if ($val !== null && $val !== '')
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">{{ $label }}</p>
                        <p class="text-sm text-slate-700 font-bold mt-0.5">{{ $val }}</p>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Persons present --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Persons Present</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Role</th>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Phone</th>
                            <th class="px-4 py-3 text-left">Capacity / Relationship</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($report->participants as $person)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="text-[10px] font-black px-2 py-1 rounded-md {{ $person->isRepresentative() ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ $person->isRepresentative() ? 'Representative' : 'Applicant' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-700">{{ $person->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $person->phone ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $person->relationship ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">None recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Inspection sections (new template) --}}
        @if (!$isLegacy)

        {{-- Merger --}}
        @if ($report->hasPurpose('merger'))
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Merger</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">S/N</th>
                            <th class="px-4 py-3 text-left">Property File Number</th>
                            <th class="px-4 py-3 text-left">Plot Number</th>
                            <th class="px-4 py-3 text-left">Area</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($report->mergerProperties as $property)
                        <tr>
                            <td class="px-4 py-3 font-black text-slate-500">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-bold text-slate-700">{{ $property->property_file_number }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $property->plot_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-700 font-bold">{{ $property->areaText() ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No properties recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-sm">
                <span class="text-xs font-bold text-slate-500">Total area being merged</span>
                <span class="text-sm font-black text-slate-800">{{ $sqm($report->totalMergerAreaSqm()) ?: '—' }}</span>
            </div>
            @if ($report->proposed_merged_plot_number || $report->recommended_merged_area_sqm || $report->merger_remarks)
            <div class="px-6 py-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-3 gap-4">
                @if ($report->proposed_merged_plot_number)
                <div><p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Proposed merged plot</p>
                    <p class="text-sm font-bold text-slate-700 mt-0.5">{{ $report->proposed_merged_plot_number }}</p></div>
                @endif
                @if ($report->recommended_merged_area_sqm)
                <div><p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Recommended merged area</p>
                    <p class="text-sm font-bold text-slate-700 mt-0.5">{{ $sqm($report->recommended_merged_area_sqm) }}</p></div>
                @endif
                @if ($report->merger_remarks)
                <div class="md:col-span-3"><p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Remarks</p>
                    <p class="text-sm text-slate-700 mt-0.5">{{ $report->merger_remarks }}</p></div>
                @endif
            </div>
            @endif
        </div>
        @endif

        {{-- Extension --}}
        @if ($report->hasPurpose('extension'))
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Extension</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left w-14">#</th>
                            <th class="px-4 py-3 text-left">Description of portion</th>
                            <th class="px-4 py-3 text-left">Area</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($report->extensionPortions as $extension)
                        <tr>
                            <td class="px-4 py-3 font-black text-slate-500">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-bold text-slate-700">{{ $extension->description ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-700 font-bold">{{ $extension->areaText() ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-slate-400">No extension portions recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <span class="text-xs font-bold text-slate-500">Total extension area</span>
                <span class="text-sm font-black text-slate-800">{{ $sqm($report->totalExtensionAreaSqm()) ?: '—' }}</span>
            </div>
            @if ($report->extension_remarks)
            <div class="px-6 py-4 border-t border-slate-100">
                <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Remarks</p>
                <p class="text-sm text-slate-700 mt-0.5">{{ $report->extension_remarks }}</p>
            </div>
            @endif
        </div>
        @endif

        {{-- Subdivision / Separation --}}
        @if ($report->wantsSubdivisionSection())
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Subdivision</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left w-14">S/N</th>
                            <th class="px-4 py-3 text-left">Plot Number</th>
                            <th class="px-4 py-3 text-left">Area</th>
                            <th class="px-4 py-3 text-left">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($report->subdivisionPlots as $plot)
                        <tr>
                            <td class="px-4 py-3 font-black text-slate-500">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-bold text-slate-700">{{ $plot->plot_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-700 font-bold">{{ $plot->areaText() ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $plot->remarks ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No plots recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div><p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Total allocated</p>
                    <p class="text-sm font-black text-slate-800 mt-0.5">{{ $sqm($report->totalSubdivisionAreaSqm()) ?: '—' }}</p></div>
                <div><p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Remaining (unallocated)</p>
                    <p class="text-sm font-black text-emerald-600 mt-0.5">{{ $sqm($report->subdivisionRemainingAreaSqm()) ?: '—' }}</p></div>
                <div><p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Recommended site area</p>
                    <p class="text-sm font-black text-slate-800 mt-0.5">{{ $sqm($report->site_recommended_area_sqm) ?: $sqm($report->recommendedTotalSiteAreaSqm()) ?: '—' }}</p></div>
            </div>
        </div>
        @endif

        {{-- Change of purpose --}}
        @if ($report->hasPurpose('change_of_purpose'))
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Change of Purpose</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">S/N</th>
                            <th class="px-4 py-3 text-left">Current Land Use</th>
                            <th class="px-4 py-3 text-left">Proposed Land Use</th>
                            <th class="px-4 py-3 text-left">Area</th>
                            <th class="px-4 py-3 text-left">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($report->purposeChanges as $change)
                        <tr>
                            <td class="px-4 py-3 font-black text-slate-500">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $change->current_land_use ?: '—' }}</td>
                            <td class="px-4 py-3 font-bold text-slate-700">{{ $change->proposed_land_use ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-700 font-bold">{{ $change->areaText() ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $change->remarks ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No changes recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @else

        {{-- Legacy: the old single measurement sheet. --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Measurements</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left w-14">S/N</th>
                            <th class="px-4 py-3 text-left">Portion</th>
                            <th class="px-4 py-3 text-left">Dimension</th>
                            <th class="px-4 py-3 text-left">Measurement (m²/ha)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($report->portions as $portion)
                        <tr>
                            <td class="px-4 py-3 font-black text-slate-500">{{ $portion->sn }}</td>
                            <td class="px-4 py-3 font-bold text-slate-700">{{ $portion->label }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $portion->dimensions ?: ($portion->land_use ?: ($portion->unit_count !== null ? $portion->unit_count : '—')) }}
                            </td>
                            <td class="px-4 py-3 text-slate-700 font-bold">{{ $portion->measurementText() ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-sm text-slate-400">No measurements recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Findings --}}
        @if (!$isLegacy && ($report->finding_site_suitable !== null || $report->finding_site_accessible !== null || $report->development_status || $report->officer_recommendation || $report->officer_designation || $report->further_directions))
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Findings &amp; Recommendation</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
                @foreach ([
                    ['Site suitable for proposed use', $report->finding_site_suitable === null ? null : ($report->finding_site_suitable ? 'Yes' : 'No')],
                    ['Site accessible', $report->finding_site_accessible === null ? null : ($report->finding_site_accessible ? 'Yes' : 'No')],
                    ['Development status', $report->development_status ? ucfirst(str_replace('_', ' ', $report->development_status)) : null],
                    ['Officer designation', $report->officer_designation],
                ] as [$label, $val])
                    @if ($val !== null)
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">{{ $label }}</p>
                        <p class="text-sm text-slate-700 font-bold mt-0.5">{{ $val }}</p>
                    </div>
                    @endif
                @endforeach
                @if ($report->officer_recommendation)
                <div class="md:col-span-2">
                    <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Recommendation of the inspecting officer</p>
                    <p class="text-sm text-slate-700 mt-0.5">{{ $report->officer_recommendation }}</p>
                </div>
                @endif
                @if ($report->further_directions)
                <div class="md:col-span-3">
                    <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Further directions</p>
                    <p class="text-sm text-slate-700 mt-0.5">{{ $report->further_directions }}</p>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Boundaries & observations --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Boundaries &amp; Observations</h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
                    @foreach ([
                        ['Available on Ground', $report->available_on_ground],
                        ['Conforms with surrounding land use', $report->conformity === null ? null : ($report->conformity ? 'Yes' : 'No')],
                        ['Prevailing Land Use', $report->prevailing_land_use],
                        ['Road Reservation', $report->road_reservation],
                        ['Number of Units', $report->number_of_units],
                    ] as [$label, $val])
                        @if ($val !== null && $val !== '')
                        <div>
                            <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">{{ $label }}</p>
                            <p class="text-sm text-slate-700 font-bold mt-0.5">{{ $val }}</p>
                        </div>
                        @endif
                    @endforeach
                </div>

                @if (!empty($report->boundary_description))
                <div class="pt-2">
                    <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Bounded by</p>
                    <p class="text-sm text-slate-700 mt-1 leading-relaxed">{{ $report->boundary_description }}</p>
                </div>
                @endif

                @if ($report->additional_observations)
                <div class="pt-2">
                    <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Additional Observations</p>
                    <p class="text-sm text-slate-700 mt-1 leading-relaxed">{{ $report->additional_observations }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Evidence --}}
        @if ($report->evidence->isNotEmpty())
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Evidence</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach ($report->evidence as $evidence)
                <div class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/60 px-4 py-3">
                    <div class="w-9 h-9 rounded-lg bg-white border border-slate-100 flex items-center justify-center shrink-0">
                        <i data-lucide="{{ $evidence->isImage() ? 'image' : 'file-text' }}" class="w-4 h-4 text-slate-500"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-slate-700 truncate">{{ $evidence->kindLabel() }}</p>
                        @if ($evidence->description)
                            <p class="text-[10px] text-slate-400 truncate">{{ $evidence->description }}</p>
                        @elseif ($evidence->value)
                            <p class="text-[10px] text-slate-400 truncate">{{ $evidence->value }}</p>
                        @endif
                    </div>
                    @if ($evidence->publicUrl())
                    <a href="{{ $evidence->publicUrl() }}" target="_blank"
                        class="text-[10px] font-bold text-slate-500 hover:text-slate-700">View</a>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Supervisor decision --}}
        @if ($report->supervisor_remarks || $report->supervisor_decision)
        <div class="rounded-3xl border border-slate-200 bg-slate-50/70 px-6 py-5">
            <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Supervisor</p>
            @if ($report->supervisor_decision)
            <p class="text-sm font-black text-slate-700 mt-1">
                Decision: <span class="{{ $report->supervisor_decision === 'approved' ? 'text-emerald-600' : ($report->supervisor_decision === 'rejected' ? 'text-red-600' : 'text-amber-600') }}">
                    {{ strtoupper($report->supervisor_decision) }}
                </span>
                @if ($report->returned_at)
                    on {{ $report->returned_at->format('d M Y') }}
                @endif
            </p>
            @endif
            @if ($report->supervisor_remarks)
            <p class="text-sm text-slate-600 mt-1 leading-relaxed">{{ $report->supervisor_remarks }}</p>
            @endif
        </div>
        @endif

        {{-- What the record links to --}}
        @if ($subject)
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm px-6 py-5">
            <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Parcel Update Record</p>
            <p class="text-sm text-slate-700 font-bold mt-1">
                {{ MasterJsiSubjectResolver::label($report->subject_type, $subject) }}
                <span class="text-xs font-normal text-slate-400 ml-2">
                    ({{ MasterJsiReport::SUBJECTS[$report->subject_type][1] ?? $report->subject_type }})
                </span>
            </p>
        </div>
        @endif
    </div>
    </div>

    @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
<script>if (window.lucide) lucide.createIcons();</script>
@endsection
