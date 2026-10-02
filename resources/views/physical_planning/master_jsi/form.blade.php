@extends('layouts.app')

@php
    use App\Models\MasterJsiPurpose;
    use App\Models\MasterJsiReport;
    use App\Support\MasterJsiPortionTemplates;
    use App\Support\MasterJsiSubjectResolver;

    /**
     * The Master JSI capture form — a joint site inspection with one or more purposes.
     *
     * The purpose multi-select replaces the old "one sheet per parcel update type"
     * idea: an inspection may cover a merger AND an extension on the same visit, so
     * which sections are drawn follows the ticked purposes, not the other way round.
     * Category and the parcel update type are still asked (they say which register
     * the file number is looked up in, and what the legacy sheet would have been).
     *
     * Legacy records — captured before this update, carrying no purpose children —
     * keep their single old measurement sheet; everything new uses the purpose
     * sections. Legacy records are the only ones that never change shape.
     */
    $isEdit = $report !== null;
    $isLegacy = $isEdit && $report->isLegacyLayout();

    $value = function (string $field, $default = null) use ($report, $prefill) {
        if ($report && $report->{$field} !== null && $report->{$field} !== '') {
            return $report->{$field};
        }
        return $prefill[$field] ?? $default;
    };

    $segments = $isEdit ? ($report->boundary_segments ?? []) : [];

    // Site figures are stored in m² but entered (and re-read) in the unit picked
    // on the sheet — the controller converts them the same way the totals do, so
    // here a stored figure is divided back when the recorded unit is hectares.
    $siteUnit = (string) ($isEdit
        ? ($report->site_unit ?: 'sqm')
        : ($prefill['site_unit'] ?? 'sqm'));

    $siteInput = function (string $field, $default = null) use ($report, $value, $siteUnit) {
        $raw = $value($field, $default);
        $fromModel = $report && $report->{$field} !== null && $report->{$field} !== '';
        if ($fromModel && $siteUnit === 'ha' && $raw !== '' && $raw !== null) {
            return trim(rtrim(rtrim(number_format((float) $raw / 10000, 6), '0'), '.'));
        }
        return $raw;
    };

    $jsData = [
        'isEdit'    => $isEdit,
        'isLegacy'  => $isLegacy,
        'reportId'  => $isEdit ? $report->id : null,
        'returnUrl' => $returnUrl,
        'landUses'  => $landUses->pluck('landuse')->values()->all(),

        'purposes'  => $isEdit ? $report->purposes->pluck('purpose')->all() : [],
        'participants' => $isEdit ? $report->participants->map(fn ($p) => [
            'participant'  => $p->participant,
            'name'         => $p->name,
            'phone'        => $p->phone,
            'relationship' => $p->relationship,
        ])->all() : [],

        'mergerProperties' => $isEdit ? $report->mergerProperties->map(fn ($r) => [
            'property_file_number' => $r->property_file_number,
            'plot_number'          => $r->plot_number,
            'area_sqm'             => $r->area_sqm !== null ? trim((string) $r->area_sqm) : '',
            'unit'                 => $r->unit ?? ($r->area_sqm !== null && (float) $r->area_sqm > 0 && $r->area_sqm / 10000 >= 1 ? 'ha' : 'sqm'),
        ])->all() : [],

        'extensionPortions' => $isEdit ? $report->extensionPortions->map(fn ($r) => [
            'description'  => $r->description,
            'area_sqm'     => $r->area_sqm !== null ? trim((string) $r->area_sqm) : '',
            'unit'         => $r->unit ?? 'sqm',
        ])->all() : [],

        'subdivisionPlots' => $isEdit ? $report->subdivisionPlots->map(fn ($r) => [
            'plot_number' => $r->plot_number,
            'area_sqm'    => $r->area_sqm !== null ? trim((string) $r->area_sqm) : '',
            'unit'        => $r->unit ?? 'sqm',
            'remarks'     => $r->remarks,
        ])->all() : [],

        'purposeChanges' => $isEdit ? $report->purposeChanges->map(fn ($r) => [
            'current_land_use'  => $r->current_land_use,
            'proposed_land_use' => $r->proposed_land_use,
            'area_sqm'          => $r->area_sqm !== null ? trim((string) $r->area_sqm) : '',
            'unit'              => $r->unit ?? 'sqm',
            'remarks'           => $r->remarks,
        ])->all() : [],

        'parent' => $isEdit ? [
            'proposed_merged_plot_number' => $value('proposed_merged_plot_number'),
            'recommended_merged_area_sqm' => $value('recommended_merged_area_sqm'),
            'merger_remarks'              => $value('merger_remarks'),
            'extension_remarks'           => $value('extension_remarks'),
        ] : ['proposed_merged_plot_number' => '', 'recommended_merged_area_sqm' => '', 'merger_remarks' => '', 'extension_remarks' => ''],
    ];

    $jsLegacy = $isLegacy ? [
        'templates' => collect(MasterJsiPortionTemplates::types())
            ->mapWithKeys(fn ($t) => [$t => [
                'heading'    => MasterJsiPortionTemplates::heading($t),
                'narrative'  => MasterJsiPortionTemplates::narrative($t),
                'rows'       => MasterJsiPortionTemplates::rows($t),
                'repeatable' => MasterJsiPortionTemplates::repeatable($t),
            ]])->all(),
        'existing' => $report->portions->keyBy('role')->map(fn ($p) => [
            'role'       => $p->role,
            'label'      => $p->label,
            'dimensions' => $p->dimensions,
            'area_sqm'   => $p->area_sqm,
            'land_use'   => $p->land_use,
            'unit_count' => $p->unit_count,
        ])->all(),
    ] : null;
@endphp

@section('styles')
<style>
    .swal2-container { z-index: 20000 !important; }

    .portion-row.is-derived { background:#f0fdf4; }
    .portion-row.is-derived td { border-color:#bbf7d0; }

    .purpose-card.active { border-color:#fecdd3; background:#fef2f2; }
    .purpose-card.active .purpose-tick { border-color:#dc2626; background-color:#dc2626; }
    #app_applicant_name {
        background-color: #fff;
        color: #0f172a;
        opacity: 1;
        cursor: text;
    }
    #app_file_title,
    #location,
    #inspection_officer {
        background-color: #f1f5f9;
        color: #64748b;
        cursor: default;
    }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => $isEdit ? 'Master JSI ' . $report->jsi_ref : 'Create Master JSI',
        'PageDescription' => 'Physical Planning joint site inspection for a parcel update.',
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

        {{-- Header --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm px-7 py-6 flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center shrink-0">
                    <i data-lucide="ruler" class="w-6 h-6 text-red-600"></i>
                </div>
                <div>
                    <h1 class="text-xl font-black text-slate-800 leading-tight">
                        {{ $isEdit ? 'Master JSI ' . $report->jsi_ref : 'Create Master JSI' }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Joint site inspection supporting the parcel-update application.
                    </p>
                </div>
            </div>
            <a href="{{ $returnUrl }}" class="text-xs font-bold text-slate-400 hover:text-slate-600 px-3 py-2">Cancel</a>
        </div>

        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm px-5 py-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 flex-wrap" data-wizardsteps>
                <button type="button" data-wstep-to="1" class="px-4 py-2 rounded-xl text-xs font-bold bg-red-600 text-white shadow-sm shadow-red-600/20">1 · Application</button>
                <button type="button" data-wstep-to="2" class="px-4 py-2 rounded-xl text-xs font-bold bg-white text-slate-500 border border-slate-200 hover:bg-slate-50">2 · Purpose</button>
                <button type="button" data-wstep-to="3" class="px-4 py-2 rounded-xl text-xs font-bold bg-white text-slate-500 border border-slate-200 hover:bg-slate-50">3 · Site &amp; People</button>
                <button type="button" data-wstep-to="4" class="px-4 py-2 rounded-xl text-xs font-bold bg-white text-slate-500 border border-slate-200 hover:bg-slate-50">4 · Findings &amp; Evidence</button>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-wstep-prev class="px-5 py-2 rounded-xl text-xs font-bold text-slate-500 border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed" disabled>← Back</button>
                <button type="button" id="saveBtn" data-wstep-next class="px-6 py-2 rounded-xl text-sm font-bold text-white bg-red-600 shadow-sm shadow-red-600/20 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed">Next →</button>
            </div>
        </div>

        <form id="masterJsiForm" class="space-y-5">
            @csrf
            <input type="hidden" name="subject_type" id="subject_type" value="{{ $subjectType }}">
            <input type="hidden" name="subject_id" id="subject_id" value="{{ $subjectId }}">
            <input type="hidden" name="duplex_stage_id" value="{{ $isEdit ? $report->duplex_stage_id : '' }}">

            {{-- ===== 1. What this inspection is about ===== --}}
            <div data-wstep="1" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">1 · Category &amp; Parcel Update</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Which register the file number is looked up in, and the application being cleared.</p>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Category <span class="text-red-500">*</span></label>
                        <select name="category" id="category" required
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            <option value="">Select category</option>
                            @foreach (MasterJsiReport::CATEGORIES as $key => $label)
                                <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Application / Parcel Update Type <span class="text-red-500">*</span></label>
                        <select name="parcel_update_type" id="parcel_update_type" required
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            <option value="">Select type</option>
                            @foreach (MasterJsiPortionTemplates::types() as $type)
                                <option value="{{ $type }}" @selected($parcelUpdateType === $type)>
                                    {{ ucwords(str_replace('_', ' ', $type)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- The file number is what the officer is actually holding, so it is
                         what the form asks for. Picking one through the global selector
                         resolves the parcel-update application behind it, which is what the
                         clearance attaches to — see resolveSubject(). READ-ONLY on purpose:
                         the global selector is the only way in. --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">File Number <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <input type="text" name="file_number" id="file_number" value="{{ $value('file_number') }}"
                                placeholder="Select a file number" readonly onclick="openFileSelector()"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 text-slate-700 cursor-pointer focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            <button type="button" onclick="openFileSelector()"
                                class="shrink-0 px-3 py-2 bg-slate-800 text-white text-xs font-bold rounded-lg hover:bg-slate-900 transition">Select</button>
                        </div>
                        <p id="subjectLinkNote" class="mt-1 text-[10px] text-slate-400">
                            {{ $subjectId
                                ? 'Linked to ' . MasterJsiSubjectResolver::label($subjectType, MasterJsiSubjectResolver::find($subjectType, $subjectId))
                                : '' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- ===== 2. Application details ===== --}}
            <div data-wstep="1" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">2 · Application Details</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach ([
                        ['file_title', 'File Title', 'text'],
                        ['applicant_name', 'Applicant Name', 'text'],
                        ['inspection_date', 'Inspection Date', 'date'],
                    ] as [$field, $label, $type])
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">{{ $label }}</label>
                        <input type="{{ $type }}" name="{{ $field }}" id="app_{{ $field }}"
                            value="{{ $field === 'inspection_date' && $value($field) ? \Carbon\Carbon::parse($value($field))->format('Y-m-d') : $value($field) }}"
                            @if($type === 'date') max="{{ now()->format('Y-m-d') }}" @endif
                            @if($field === 'file_title') readonly @endif
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                    </div>
                    @endforeach

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Plot Number</label>
                        <input type="text" name="plot_number" id="plot_number" value="{{ $value('plot_number') }}"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                    </div>

                    @php $districtValue = (string) $value('district'); @endphp
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">District</label>
                        <select name="district" id="district"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            <option value="">Select district</option>
                            @foreach ($districts as $d)
                                <option value="{{ $d->name }}" @selected(strcasecmp($districtValue, (string) $d->name) === 0)>
                                    {{ strtoupper($d->name) }}
                                </option>
                            @endforeach
                            <option value="OTHER" @selected($districtValue !== '' && !$districts->contains(fn ($d) => strcasecmp($districtValue, (string) $d->name) === 0))>OTHER</option>
                        </select>
                        <input type="text" name="district_other" id="district_other"
                            value="{{ $districtValue !== '' && !$districts->contains(fn ($d) => strcasecmp($districtValue, (string) $d->name) === 0) ? $districtValue : '' }}"
                            placeholder="Specify district"
                            class="{{ $districtValue !== '' && !$districts->contains(fn ($d) => strcasecmp($districtValue, (string) $d->name) === 0) ? '' : 'hidden' }} mt-2 w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    </div>

                    @php $lgaValue = (string) $value('lga'); @endphp
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">LGA</label>
                        <select name="lga" id="lga"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            <option value="">Select LGA</option>
                            @foreach ($lgas as $l)
                                <option value="{{ $l->name }}" @selected(strcasecmp($lgaValue, (string) $l->name) === 0)>
                                    {{ strtoupper($l->name) }}
                                </option>
                            @endforeach
                            <option value="OTHER" @selected($lgaValue !== '' && !$lgas->contains(fn ($l) => strcasecmp($lgaValue, (string) $l->name) === 0))>OTHER</option>
                        </select>
                        <input type="text" name="lga_other" id="lga_other"
                            value="{{ $lgaValue !== '' && !$lgas->contains(fn ($l) => strcasecmp($lgaValue, (string) $l->name) === 0) ? $lgaValue : '' }}"
                            placeholder="Specify LGA"
                            class="{{ $lgaValue !== '' && !$lgas->contains(fn ($l) => strcasecmp($lgaValue, (string) $l->name) === 0) ? '' : 'hidden' }} mt-2 w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Location</label>
                        <input type="text" name="location" id="location" value="{{ $value('location') }}" readonly
                            placeholder="Fills from the selected file number"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Inspection Officer</label>
                        <input type="text" name="inspection_officer" id="inspection_officer" readonly value="{{ $value('inspection_officer', $isEdit ? null : (trim(auth()->user()->name ?? '') ?: auth()->user()->username)) }}"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                    </div>
                </div>
            </div>

            {{-- ===== 3. Purpose of the inspection ===== --}}
            <div data-wstep="2" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">3 · Purpose of Inspection</h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Pick one or more. The sections below follow what is ticked, so one visit can settle a merger
                        and an extension at once.
                    </p>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3" id="purposeGrid">
                    @foreach (MasterJsiPurpose::PURPOSES as $slug => $label)
                    <button type="button" data-purpose="{{ $slug }}"
                        class="purpose-card rounded-xl border border-slate-200 px-4 py-4 text-left transition hover:border-red-300 bg-white">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" data-purpose-box="{{ $slug }}"
                                @checked(in_array($slug, $jsData['purposes']))
                                class="purpose-tick mt-0.5 w-4 h-4 rounded border-slate-300" tabindex="-1">
                            <span>
                                <span class="block text-sm font-black text-slate-700">{{ $label }}</span>
                                @if ($slug === 'subdivision')
                                <span class="block text-[10px] text-slate-400 mt-0.5">Also covers Separation</span>
                                @endif
                            </span>
                        </label>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- ===== 4. Site & land measurement ===== --}}
            <div data-wstep="2" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">4 · Site &amp; Land Measurement</h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Stored in <strong>square metres</strong>. If a figure was measured in hectares, pick ha beside it — it is converted.
                    </p>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Unit entered on this sheet</label>
                        <select name="site_unit" id="site_unit" onchange="onSiteUnitChange()"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="sqm" @selected(($value('site_unit', 'sqm')) === 'sqm')>Square metres (m²)</option>
                            <option value="ha" @selected($value('site_unit', 'sqm') === 'ha')>Hectares (ha)</option>
                        </select>
                    </div>
                    <div>
                        <label for="site_existing_dimensions" class="block text-xs font-bold text-slate-600 mb-1.5">Existing Site Dimensions (m)</label>
                        <input type="text" name="site_existing_dimensions" id="site_existing_dimensions"
                            maxlength="255" value="{{ $value('site_existing_dimensions') }}" placeholder="e.g. 10 m x 20 m x 30 m"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                        <p class="mt-1 text-[10px] text-slate-500">Enter the measured sides of the existing site. Record its area separately.</p>
                    </div>
                    <div>
                        <label for="site_existing_area_sqm" class="block text-xs font-bold text-slate-600 mb-1.5">Existing Site Area</label>
                        <div class="flex items-center gap-2">
                            <input type="number" step="any" min="0" name="site_existing_area_sqm" id="site_existing_area_sqm"
                                oninput="recomputeTotals()" value="{{ $siteInput('site_existing_area_sqm') }}" placeholder="e.g. 1200"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            <span class="text-xs font-bold text-slate-400">m²</span>
                        </div>
                        <p class="mt-1 text-[10px] text-slate-500">Area of the existing site before the proposed changes. Use the unit selected on this sheet.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Recommended Total Area</label>
                        <div class="flex items-center gap-2">
                            <input type="number" step="any" min="0" name="site_recommended_area_sqm" id="site_recommended_area_sqm"
                                oninput="recomputeTotals()" value="{{ $siteInput('site_recommended_area_sqm') }}" placeholder="Including additions"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            <span class="text-xs font-bold text-slate-400">m²</span>
                        </div>
                        <p class="mt-1 text-[10px] text-slate-400" id="recommendedTotalHint">
                            Leave blank to use Existing + Additions.
                        </p>
                    </div>

                    <div class="md:col-span-3 border-t border-slate-100 pt-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Override the calculated recommended total</label>
                            <p class="text-[10px] text-slate-400">For an authorised correction, with the reason kept on the record.</p>
                        </div>
                        <button type="button" id="overrideToggle" class="text-xs font-bold text-red-600 hover:text-red-700">
                            Show override
                        </button>
                    </div>
                    <div id="overrideFields" class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 hidden">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Override total (m²)</label>
                            <input type="number" step="any" min="0" name="recommended_total_override_sqm"
                                value="{{ $siteInput('recommended_total_override_sqm') }}"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Reason for override</label>
                            <input type="text" name="override_reason" maxlength="500"
                                value="{{ $value('override_reason') }}"
                                placeholder="Why the calculated total is not used"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== 5. Persons present ===== --}}
            <div data-wstep="3" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">5 · Persons Present</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Who was physically on site for the joint inspection.</p>
                    </div>
                    <button type="button" onclick="addRep()"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-lg hover:bg-slate-200 transition">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add representative
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="applicantPresent" checked class="w-4 h-4 rounded border-slate-300 text-red-600">
                        <label for="applicantPresent" class="text-sm font-bold text-slate-700">The applicant was present</label>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 ml-7">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Applicant name (as on the application)</label>
                            <input type="text" id="applicantName"
                                value="{{ $value('applicant_name') }}"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Phone</label>
                            <input type="text" id="applicantPhone"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div id="repRows" class="space-y-3">
                        <div id="noReps" class="text-xs text-slate-400 text-center py-3">
                            No representatives recorded. Anyone standing in for the applicant goes here.
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== 6. Inspection sections ===== --}}
            <div data-wstep="2" id="purposeSections" class="space-y-5">
                <div id="noPurposeBox"
                    class="bg-white rounded-3xl border border-slate-100 shadow-sm px-6 py-8 text-center text-sm text-slate-400">
                    Tick a purpose in section 3 to draw its inspection section.
                </div>
            </div>

            @if ($isLegacy)
            <div data-wstep="4" id="legacySheet" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">6 · Measurements</h2>
                        <p class="text-xs text-slate-400 mt-0.5" id="narrativeHint"></p>
                    </div>
                    <button type="button" id="addPortionBtn" onclick="addPortionRow()"
                        class="hidden shrink-0 inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-lg hover:bg-slate-200 transition">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add portion
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left w-14">S/N</th>
                                <th class="px-4 py-3 text-left">Portion</th>
                                <th class="px-4 py-3 text-left">Dimension (m)</th>
                                <th class="px-4 py-3 text-left w-64">Measurement (m²/ha)</th>
                            </tr>
                        </thead>
                        <tbody id="portionsBody" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- ===== 7. Findings & recommendation ===== --}}
            <div data-wstep="4" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">7 · Findings &amp; Recommendation</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Site suitable for proposed use</label>
                        <select name="finding_site_suitable" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="">Select</option>
                            <option value="1" @selected($value('finding_site_suitable') === true || $value('finding_site_suitable') === 1)>Yes</option>
                            <option value="0" @selected($value('finding_site_suitable') === false || $value('finding_site_suitable') === 0)>No</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Site accessible</label>
                        <select name="finding_site_accessible" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="">Select</option>
                            <option value="1" @selected($value('finding_site_accessible') === true || $value('finding_site_accessible') === 1)>Yes</option>
                            <option value="0" @selected($value('finding_site_accessible') === false || $value('finding_site_accessible') === 0)>No</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Development status</label>
                        <select name="development_status" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="">Select</option>
                            @foreach (['developed' => 'Developed', 'partially_developed' => 'Partially developed', 'undeveloped' => 'Undeveloped'] as $key => $label)
                                <option value="{{ $key }}" @selected($value('development_status') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Recommendation of the inspecting officer</label>
                        <textarea name="officer_recommendation" rows="3"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">{{ $value('officer_recommendation') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Officer designation</label>
                        <input type="text" name="officer_designation" maxlength="255"
                            value="{{ $value('officer_designation') }}" placeholder="e.g. Senior Surveyor"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Further directions</label>
                        <textarea name="further_directions" rows="2"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">{{ $value('further_directions') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- ===== 8. Boundaries & observations ===== --}}
            <div data-wstep="4" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">8 · Boundaries &amp; Observations</h2>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Available on Ground</label>
                            <select name="available_on_ground" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <option value="">Select</option>
                                @foreach (['Available', 'Not Available', 'Partially Available'] as $option)
                                    <option value="{{ $option }}" @selected($value('available_on_ground') === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Conforms with surrounding land use</label>
                            <select name="conformity" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <option value="">Select</option>
                                <option value="1" @selected($value('conformity') === true || $value('conformity') === 1)>Yes — conforms</option>
                                <option value="0" @selected($value('conformity') === false || $value('conformity') === 0)>No — does not conform</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Prevailing Land Use</label>
                            <select name="prevailing_land_use" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <option value="">Select</option>
                                @foreach (['Residential', 'Commercial', 'Agricultural', 'Industrial'] as $option)
                                    <option value="{{ $option }}" @selected($value('prevailing_land_use') === $option)>{{ $option }}</option>
                                @endforeach
                                @php $prevalent = $value('prevailing_land_use'); @endphp
                                @if ($prevalent && !in_array($prevalent, ['Residential', 'Commercial', 'Agricultural', 'Industrial'], true))
                                    <option value="{{ $prevalent }}" selected>{{ $prevalent }}</option>
                                @endif
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">The site was bounded by</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach (['north' => 'North', 'east' => 'East', 'south' => 'South', 'west' => 'West'] as $dir => $dirLabel)
                            <div>
                                <label class="block text-[11px] font-medium text-slate-500 mb-1">On the {{ $dirLabel }}</label>
                                <input type="text" name="boundary_segments[{{ $dir }}]" value="{{ $segments[$dir] ?? '' }}"
                                    placeholder="What lies on the {{ strtolower($dirLabel) }}"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Road Reservation</label>
                            <input type="text" name="road_reservation" value="{{ $value('road_reservation') }}"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Location Coordinates</label>
                            <input type="text" name="location_coordinates" id="location_coordinates" maxlength="500"
                                value="{{ $value('location_coordinates') }}"
                                placeholder="e.g. 8.5241, 12.0022 (longitude, latitude)"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/60">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <i data-lucide="map-pin" class="w-4 h-4 text-slate-500 flex-shrink-0"></i>
                                <span class="text-xs font-black uppercase tracking-wider text-slate-600 whitespace-nowrap">Location Map</span>
                                <span id="coordsSource" class="text-[10px] font-medium text-slate-400 truncate"></span>
                            </div>
                            <button type="button" onclick="forceBackfillCoordinates()"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-100 transition">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5"></i> Pin on Map
                            </button>
                        </div>
                        <div id="jsiMapCanvas" class="hidden rounded-lg overflow-hidden border border-slate-200" style="height: 320px;"></div>
                        <div id="jsiMapEmpty"
                            class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 bg-white text-slate-400"
                            style="height: 320px;">
                            <i data-lucide="map-pin" class="h-7 w-7"></i>
                            <p class="text-xs">No location pinned yet.</p>
                            <p class="text-[10px]">Coordinates are backfilled from the selected file number, or drag/pin the map by hand.</p>
                        </div>
                        <p id="coordsNote" class="mt-2 text-[10px] text-slate-400"></p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Additional Observations</label>
                        <textarea name="additional_observations" rows="3"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">{{ $value('additional_observations') }}</textarea>
                    </div>
                </div>
            </div>

            @include('physical_planning.master_jsi.partials.evidence_form', ['report' => $report ?? new \App\Models\MasterJsiReport])

            <div class="flex items-center justify-between gap-3 pb-8 pt-2">
                <a href="{{ $returnUrl }}" class="px-5 py-2 rounded-xl text-xs font-bold text-slate-500 border border-slate-200 hover:bg-slate-50">Cancel</a>
                <div class="flex items-center gap-2">
                    <button type="button" data-wstep-prev-bottom class="px-5 py-2 rounded-xl text-xs font-bold text-slate-500 border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed" disabled>← Back</button>
                    <button type="button" id="saveBtnBottom" data-wstep-next-bottom class="px-6 py-2 rounded-xl text-sm font-bold text-white bg-red-600 shadow-sm shadow-red-600/20 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed">Next →</button>
                </div>
            </div>

        </form>
    </div>
    </div>

    @include('components.global-fileno-modal')
    @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
/* ============================================================================
   Master JSI capture — purpose-driven.
   DATA comes from the server (see the @php block at the top of this file).
   ========================================================================== */
const DATA = @json($jsData);
const IS_EDIT = DATA.isEdit;
const IS_LEGACY = DATA.isLegacy;
const REPORT_ID = DATA.reportId;
const RETURN_URL = DATA.returnUrl;
const LAND_USES = DATA.landUses;
const M2_PER_HECTARE = 10000;

const esc = v => (v === undefined || v === null) ? '' : String(v).replace(/"/g, '&quot;');

/* -------------------------------------------------------------- state */
const state = {
    purposes: [...DATA.purposes],

    participants: DATA.participants.filter(p => p.participant === 'applicant_representative'),

    merger: DATA.mergerProperties.map(r => ({
        property_file_number: r.property_file_number || '',
        plot_number: r.plot_number || '',
        area_sqm: r.area_sqm || '',
        unit: r.unit || 'sqm',
    })),

    extension: DATA.extensionPortions.map(r => ({
        description: r.description || '',
        area_sqm: r.area_sqm || '',
        unit: r.unit || 'sqm',
    })),

    subdivision: DATA.subdivisionPlots.map(r => ({
        plot_number: r.plot_number || '',
        area_sqm: r.area_sqm || '',
        unit: r.unit || 'sqm',
        remarks: r.remarks || '',
    })),

    change_of_purpose: DATA.purposeChanges.map(r => ({
        current_land_use: r.current_land_use || '',
        proposed_land_use: r.proposed_land_use || '',
        area_sqm: r.area_sqm || '',
        unit: r.unit || 'sqm',
        remarks: r.remarks || '',
    })),

    parent: DATA.parent,
};

/* ------------------------------------------------ measurement helpers */
function sqmOf(area, unit) {
    const v = parseFloat(area);
    if (!isFinite(v) || v <= 0) return 0;
    return String(unit || 'sqm').toLowerCase() === 'ha' ? v * M2_PER_HECTARE : v;
}

function areaText(n) {
    const v = Number(n) || 0;
    return (v === Math.round(v)
        ? v.toLocaleString('en-NG')
        : v.toLocaleString('en-NG', { minimumFractionDigits: 1, maximumFractionDigits: 2 })) + ' m²';
}

function existingSiteSqm() {
    return sqmOf(document.getElementById('site_existing_area_sqm').value, document.getElementById('site_unit').value);
}

/* ------------------------------------------- section card rendering */
const SECTION_TITLES = {
    merger:            'Merger',
    extension:         'Extension',
    subdivision:       'Subdivision',
    change_of_purpose: 'Change of Purpose',
};

function selectedPurposes() {
    return [...document.querySelectorAll('.purpose-tick:checked')]
        .map(el => el.dataset.purposeBox)
        .filter(Boolean);
}

function sectionWanted(key) {
    const purposes = selectedPurposes();
    return purposes.includes(key)
        || (key === 'subdivision' && purposes.includes('separation'));
}

window.togglePurpose = function (slug) {
    const box = document.querySelector('[data-purpose-box="' + slug + '"]');
    if (!box) return;
    const turningOn = !box.checked;
    box.checked = turningOn;
    document.querySelectorAll('[data-purpose="' + slug + '"]')
        .forEach(card => card.classList.toggle('active', turningOn));
    refreshSections();
};

/* Purpose cards: clicking anywhere on the card toggles its box. */
document.querySelectorAll('.purpose-card').forEach(card => {
    card.addEventListener('click', e => {
        if (e.target.closest('.purpose-tick')) return;
        togglePurpose(card.dataset.purpose);
    });
    card.querySelector('.purpose-tick').addEventListener('change', () => {
        card.classList.toggle('active', card.querySelector('.purpose-tick').checked);
        refreshSections();
    });
    if (card.querySelector('.purpose-tick').checked) card.classList.add('active');
});

/* --------------------------------------------------------- sections */
function refreshSections() {
    const wrap = document.getElementById('purposeSections');
    const none = document.getElementById('noPurposeBox');
    const purposes = selectedPurposes();

    none.classList.toggle('hidden', purposes.length > 0);

    Object.keys(SECTION_TITLES).forEach(key => {
        const sec = wrap.querySelector('#section-' + key);
        const wanted = sectionWanted(key);

        if (wanted && !sec) {
            wrap.insertAdjacentHTML('beforeend', sectionFrame(key));
            document.getElementById(key + 'Rows').innerHTML = state[key].map((r, i) => rowHtmlInner(key, i, r)).join('');
            recomputeTotals();
            if (window.lucide) lucide.createIcons();
        }

        const node = wrap.querySelector('#section-' + key);
        if (node) node.style.display = wanted ? '' : 'none';
    });
}

function sectionFrame(key) {
    return '<div id="section-' + key + '" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">'
        + '<div class="px-6 py-4 border-b border-slate-100">'
        +   '<h2 class="text-sm font-black uppercase tracking-wider text-slate-600">' + SECTION_TITLES[key] + '</h2>'
        +   '<p class="text-xs text-slate-400 mt-0.5">' + SECTION_LEADS[key] + '</p>'
        + '</div>'
        + '<div class="p-6">' + sectionBody(key) + '</div></div>';
}

const SECTION_LEADS = {
    merger:            'Two or more distinct properties being merged.',
    extension:         'Each separate portion of land being added — the count is portions, not years.',
    subdivision:       'The plots the site is proposed to be cut into.',
    change_of_purpose: 'Both uses come from the configured land-use register.',
};

function sectionBody(key) {
    switch (key) {
        case 'merger': return `
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <label class="text-xs font-bold text-slate-600 whitespace-nowrap">Number of properties involved</label>
                <input type="number" id="mergerCount" min="0" value="${state.merger.length || ''}"
                    oninput="window.syncRowCount('merger')"
                    class="w-24 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <span class="text-[10px] text-slate-400">The other property rows appear automatically.</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1.5">Recommended merged area (m²)</label>
                    <input type="number" step="any" min="0" data-bind="parent.recommended_merged_area_sqm"
                        value="${esc(state.parent.recommended_merged_area_sqm)}"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <label class="text-[11px] font-medium text-slate-500">File number</label>
                    <label class="text-[11px] font-medium text-slate-500">Plot number</label>
                    <label class="text-[11px] font-medium text-slate-500">Area</label>
                </div>
                <div id="mergerRows" class="space-y-3"></div>
                <button type="button" onclick="addRow('merger')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-lg hover:bg-slate-200 transition">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add property
                </button>
                <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                    <span class="text-xs font-bold text-slate-500">Total area being merged</span>
                    <span class="text-sm font-black text-slate-700" id="mergerTotal">0 m²</span>
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-xs font-bold text-slate-600 mb-1.5">Merger remarks</label>
                <textarea data-bind="parent.merger_remarks" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">${esc(state.parent.merger_remarks)}</textarea>
            </div>`;

        case 'extension': return `
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <label class="text-xs font-bold text-slate-600 whitespace-nowrap">Number of portions involved</label>
                <input type="number" id="extensionCount" min="0" value="${state.extension.length || ''}"
                    oninput="window.syncRowCount('extension')"
                    class="w-24 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <span class="text-[10px] text-slate-400">The other portion rows appear automatically.</span>
            </div>
            <div class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <label class="text-[11px] font-medium text-slate-500">#</label>
                    <label class="text-[11px] font-medium text-slate-500">Description of portion</label>
                    <label class="text-[11px] font-medium text-slate-500">Area</label>
                </div>
                <div id="extensionRows" class="space-y-3"></div>
                <button type="button" onclick="addRow('extension')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-lg hover:bg-slate-200 transition">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add portion
                </button>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
                    <div>
                        <span class="text-xs font-bold text-slate-500">Total extension area</span>
                        <span class="block text-sm font-black text-slate-700" id="extensionTotal">0 m²</span>
                    </div>
                    <p class="text-[10px] text-slate-400" id="extensionCalc"></p>
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-xs font-bold text-slate-600 mb-1.5">Extension remarks</label>
                <textarea data-bind="parent.extension_remarks" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">${esc(state.parent.extension_remarks)}</textarea>
            </div>`;

        case 'subdivision': return `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                <div class="md:col-span-2 text-xs text-slate-500">
                    One row per proposed plot. The remainder is what is left of the recommended
                    site area once the plots are allocated.
                </div>
                <div class="text-right text-xs font-bold text-slate-600">
                    Recommended site area: <span id="subdivBase" class="text-red-600">0 m²</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <label class="text-xs font-bold text-slate-600 whitespace-nowrap">Number of plots involved</label>
                <input type="number" id="subdivisionCount" min="0" value="${state.subdivision.length || ''}"
                    oninput="window.syncRowCount('subdivision')"
                    class="w-24 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <span class="text-[10px] text-slate-400">The other plot rows appear automatically — also used for separations.</span>
            </div>
            <div class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <label class="text-[11px] font-medium text-slate-500">Plot number</label>
                    <label class="text-[11px] font-medium text-slate-500">Area</label>
                    <label class="text-[11px] font-medium text-slate-500">Remarks</label>
                    <span></span>
                </div>
                <div id="subdivisionRows" class="space-y-3"></div>
                <button type="button" onclick="addRow('subdivision')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-lg hover:bg-slate-200 transition">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add plot
                </button>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t border-slate-100 pt-3">
                    <div>
                        <span class="text-xs font-bold text-slate-500">Total allocated</span>
                        <span class="block text-sm font-black text-slate-700" id="subdivisionTotal">0 m²</span>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-500">Remaining (unallocated)</span>
                        <span class="block text-sm font-black text-emerald-600" id="subdivisionRemaining">0 m²</span>
                    </div>
                </div>
            </div>`;

        case 'change_of_purpose': return `
            <div class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <label class="text-[11px] font-medium text-slate-500">Current land use</label>
                    <label class="text-[11px] font-medium text-slate-500">Proposed land use</label>
                    <label class="text-[11px] font-medium text-slate-500">Area</label>
                    <span></span>
                </div>
                <div id="change_of_purposeRows" class="space-y-3"></div>
                <button type="button" onclick="addRow('change_of_purpose')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-lg hover:bg-slate-200 transition">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add change
                </button>
                <div class="flex justify-between border-t border-slate-100 pt-3">
                    <span class="text-xs font-bold text-slate-500">Total area affected</span>
                    <span class="text-sm font-black text-slate-700" id="purposeChangeTotal">0 m²</span>
                </div>
            </div>`;
    }
}

function unitSelect(value) {
    return '<select class="unit-sel w-20 border border-slate-200 rounded-lg px-1 py-2 text-xs text-slate-600 bg-white">'
        + '<option value="sqm"' + (String(value) === 'ha' ? '' : ' selected') + '>m²</option>'
        + '<option value="ha"' + (String(value) === 'ha' ? ' selected' : '') + '>ha</option>'
        + '</select>';
}

/* ------------------------------------------------------ row wiring */
function rowIndex(target) {
    const row = target.closest('[data-row-key]');
    return row ? parseInt(row.dataset.idx, 10) : null;
}

/* Delegated writes: any element with data-row-input returns to its row. */
document.getElementById('masterJsiForm').addEventListener('input', e => {
    const t = e.target;
    if (t.dataset.rowInput) {
        const row = t.closest('[data-row-key]');
        const idx = parseInt(row.dataset.idx, 10);
        const key = row.dataset.key;
        state[key][idx][t.dataset.rowInput] = t.value;
        recomputeTotals();
    } else if (t.dataset.bind) {
        const path = t.dataset.bind.split('.');
        if (path[0] === 'parent' && state.parent[path[1]] !== undefined) {
            state.parent[path[1]] = t.value;
        }
    }
});

document.getElementById('masterJsiForm').addEventListener('change', e => {
    const t = e.target;
    if (t.dataset.rowInput) {
        const row = t.closest('[data-row-key]');
        const idx = parseInt(row.dataset.idx, 10);
        const key = row.dataset.key;
        state[key][idx][t.dataset.rowInput] = t.value;
        recomputeTotals();
        return;
    }
    if (t.closest('[data-row-input-wrap]')) {
        const wrap = t.closest('[data-row-input-wrap]');
        const row = wrap.closest('[data-row-key]');
        const idx = parseInt(row.dataset.idx, 10);
        state[row.dataset.key][idx][wrap.dataset.rowInputWrap] = t.value;
        return;
    }
    // unit selects inside a row
    const row = t.closest('[data-row-key]');
    if (row && t.classList.contains('unit-sel')) {
        const idx = parseInt(row.dataset.idx, 10);
        state[row.dataset.key][idx].unit = t.value;
        recomputeTotals();
    }
});

function blankRow(key) {
    return {
        merger: { property_file_number: '', plot_number: '', area_sqm: '', unit: 'sqm' },
        extension: { description: '', area_sqm: '', unit: 'sqm' },
        subdivision: { plot_number: '', area_sqm: '', unit: 'sqm', remarks: '' },
        change_of_purpose: { current_land_use: '', proposed_land_use: '', area_sqm: '', unit: 'sqm', remarks: '' },
    }[key];
}

window.addRow = function (key) {
    state[key].push(blankRow(key));
    renderRows(key);
    if (window.lucide) lucide.createIcons();
};

/* The "Number of X involved" input drives how many rows exist. */
window.syncRowCount = function (key) {
    const input = document.getElementById(key + 'Count');
    if (!input) return;
    let n = parseInt(input.value, 10);
    if (!isFinite(n)) n = 0;
    n = Math.max(0, n);
    while (state[key].length < n) state[key].push(blankRow(key));
    if (state[key].length > n) state[key] = state[key].slice(0, n);
    if (input.value !== state[key].length) input.value = state[key].length || '';
    renderRows(key);
    recomputeTotals();
};

window.removeRow = function (key, idx) {
    state[key].splice(idx, 1);
    renderRows(key);
    recomputeTotals();
};

function renderRows(key) {
    const body = document.getElementById(key + 'Rows');
    if (!body) return;
    const count = document.getElementById(key + 'Count');
    if (count) count.value = state[key].length || '';
    body.innerHTML = state[key].map((r, i) =>
        '<div class="grid grid-cols-1 ' + rowCols(key) + ' gap-3" data-row-key="' + key + '" data-idx="' + i + '">'
        + rowHtmlInner(key, i, r) + '</div>'
    ).join('');

    const none = document.getElementById('noReps');
    if (key === 'participants' && none) none.style.display = state.participants.length ? 'none' : '';
    if (window.lucide) lucide.createIcons();
}

function rowCols(key) {
    return {
        merger:            'md:grid-cols-3',
        extension:         'md:grid-cols-[auto_1fr_1fr_auto]',
        subdivision:       'md:grid-cols-[1fr_1fr_1.5fr_auto]',
        change_of_purpose: 'md:grid-cols-[1fr_1fr_1fr_1.5fr_auto]',
    }[key];
}

/* rowHtml expects its own grid; renderRows re-wraps, so rowHtmlInner drops the grid. */
function rowHtmlInner(key, idx, r) {
    const areaCol = '<div class="flex items-center gap-1.5">'
        + '<input type="number" step="any" min="0" data-row-input="area_sqm" value="' + esc(r.area_sqm) + '" placeholder="0"'
        +   ' class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">'
        + unitSelect(r.unit)
        + '</div>';
    const del = '<button type="button" onclick="removeRow(\'' + key + '\',' + idx + ')"'
        + ' class="w-9 h-9 rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 text-lg leading-none">&times;</button>';

    switch (key) {
        case 'merger': return ''
            + '<div class="flex items-center gap-2 min-w-0">'
            + '<input type="text" data-row-input="property_file_number" value="' + esc(r.property_file_number) + '" placeholder="Select a file number" readonly aria-label="File number" onclick="openMergerFileSelector(' + idx + ')"'
            + ' class="w-full min-w-0 border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 cursor-pointer">'
            + '<button type="button" onclick="openMergerFileSelector(' + idx + ')" class="shrink-0 px-3 py-2 bg-slate-800 text-white text-xs font-bold rounded-lg hover:bg-slate-900">Select</button></div>'
            + '<input type="text" data-row-input="plot_number" value="' + esc(r.plot_number) + '" placeholder="Plot"'
            + ' class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">'
            + areaCol + del;

        case 'extension': return ''
            + '<span class="px-3 py-2 text-xs font-black text-slate-500 bg-slate-100 rounded-lg inline-flex items-center justify-center">' + (idx + 1) + '</span>'
            + '<input type="text" data-row-input="description" value="' + esc(r.description) + '" placeholder="Portion of the site added"'
            + ' class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">'
            + areaCol + del;

        case 'subdivision': return ''
            + '<input type="text" data-row-input="plot_number" value="' + esc(r.plot_number) + '" placeholder="Plot number"'
            + ' class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">'
            + areaCol
            + '<input type="text" data-row-input="remarks" value="' + esc(r.remarks) + '" placeholder="Remarks"'
            + ' class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">'
            + del;

        case 'change_of_purpose': return ''
            + '<div data-row-input-wrap="current_land_use"><select class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white">'
            + landUseOptions(r.current_land_use) + '</select></div>'
            + '<div data-row-input-wrap="proposed_land_use"><select class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white">'
            + landUseOptions(r.proposed_land_use) + '</select></div>'
            + areaCol
            + '<input type="text" data-row-input="remarks" value="' + esc(r.remarks) + '" placeholder="Remarks"'
            + ' class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">'
            + del;
    }
}

function landUseOptions(value) {
    return LAND_USES.map(u =>
        '<option value="' + esc(u) + '"' + (String(u) === String(value || '') ? ' selected' : '') + '>' + esc(u) + '</option>'
    ).join('');
}

/* ------------------------------------------------------ totals */
function sumSqm(rows) {
    return rows.reduce((sum, r) => sum + sqmOf(r.area_sqm, r.unit), 0);
}

function recommendedBaseSqm() {
    const input = document.getElementById('site_recommended_area_sqm');
    const typed = sqmOf(input ? input.value : '', document.getElementById('site_unit').value);
    if (typed > 0) return typed;
    return existingSiteSqm() + sumSqm(state.extension);
}

/* A site figure travels with the sheet unit — switching the unit on an existing
   value rescales the three inputs so the numbers on screen are always in the
   unit currently picked, and the controller converts them back to m² on save. */
let currentSiteUnit = (document.getElementById('site_unit') || {}).value || 'sqm';
window.onSiteUnitChange = function () {
    const next = document.getElementById('site_unit').value;
    const factorOf = (u) => String(u).toLowerCase() === 'ha' ? 10000 : 1;
    const from = factorOf(currentSiteUnit);
    const to = factorOf(next);
    ['site_existing_area_sqm', 'site_recommended_area_sqm', 'recommended_total_override_sqm']
        .forEach((id) => {
            const el = document.getElementById(id);
            if (!el || el.value === '') return;
            const v = parseFloat(el.value);
            if (!isFinite(v) || v <= 0) { el.value = ''; return; }
            el.value = String(Math.round((v * from / to) * 100000) / 100000);
        });
    currentSiteUnit = next;
    recomputeTotals();
};

function recomputeTotals() {
    const e = document.getElementById('extensionTotal');
    if (e) e.textContent = areaText(sumSqm(state.extension));

    const m = document.getElementById('mergerTotal');
    if (m) m.textContent = areaText(sumSqm(state.merger));

    const calc = document.getElementById('extensionCalc');
    if (calc) {
        const ext = sumSqm(state.extension);
        const existing = existingSiteSqm();
        calc.textContent = ext > 0
            ? 'Existing ' + areaText(existing) + ' + additions ' + areaText(ext)
                + ' = recommended <strong>' + areaText(existing + ext) + '</strong>.'
            : 'Recommended total defaults to the existing site area when no additions are recorded.';
    }

    const base = recommendedBaseSqm();
    const baseEl = document.getElementById('subdivBase');
    if (baseEl) baseEl.textContent = areaText(base);

    const total = document.getElementById('subdivisionTotal');
    if (total) total.textContent = areaText(sumSqm(state.subdivision));

    const rem = document.getElementById('subdivisionRemaining');
    if (rem) rem.textContent = areaText(Math.max(0, base - sumSqm(state.subdivision)));

    const pc = document.getElementById('purposeChangeTotal');
    if (pc) pc.textContent = areaText(sumSqm(state.change_of_purpose));

    const hint = document.getElementById('recommendedTotalHint');
    if (hint) {
        hint.textContent = (parseFloat(document.getElementById('site_recommended_area_sqm').value) > 0)
            ? 'This figure is used as the recommended total (or override).'
            : 'Leave blank to use Existing Site Area + Additions.';
    }
}

/* ------------------------------------------------------ persons present */
window.addRep = function () {
    state.participants.push({ participant: 'applicant_representative', name: '', phone: '', relationship: '' });
    renderRows('participants');
};

/* Participant rows belong to their own little list, not the grid rows. */
(function () {
    const body = document.getElementById('repRows');
    const none = document.getElementById('noReps');
    const draw = () => {
        body.querySelectorAll('.rep-row').forEach(el => el.remove());
        (none.style.display = state.participants.length ? 'none' : '');
        state.participants.forEach((r, i) =>
            body.insertAdjacentHTML('beforeend', repHtml(i, r)));
    };
    window.drawReps = draw;

    document.getElementById('masterJsiForm').addEventListener('input', e => {
        const t = e.target;
        if (t.dataset.repField) {
            const idx = parseInt(t.closest('.rep-row').dataset.idx, 10);
            state.participants[idx][t.dataset.repField] = t.value;
        }
    });

    window.removeRep = function (idx) {
        state.participants.splice(idx, 1);
        drawReps();
    };
    draw();
})();

function repHtml(idx, r) {
    return '<div class="rep-row grid grid-cols-1 md:grid-cols-[1fr_1fr_1fr_auto] gap-3" data-idx="' + idx + '">'
        + '<input type="text" data-rep-field="name" value="' + esc(r.name) + '" placeholder="Representative name"'
        + ' class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">'
        + '<input type="text" data-rep-field="phone" value="' + esc(r.phone) + '" placeholder="Phone"'
        + ' class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">'
        + '<input type="text" data-rep-field="relationship" value="' + esc(r.relationship) + '" placeholder="Capacity (e.g. counsel, brother)"'
        + ' class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">'
        + '<button type="button" onclick="removeRep(' + idx + ')"'
        + ' class="w-9 h-9 rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 text-lg leading-none">&times;</button>'
        + '</div>';
}

/* ------------------------------------------------------ override reveal */
document.getElementById('overrideToggle').addEventListener('click', () => {
    const wrap = document.getElementById('overrideFields');
    const hidden = wrap.classList.toggle('hidden');
    document.getElementById('overrideToggle').textContent = hidden ? 'Show override' : 'Hide override';
});

/* ------------------------------------------------------ file picker */
window.openMergerFileSelector = function (idx) {
    const row = state.merger[idx];
    if (!row) return;
    if (!window.GlobalFileNoModal) {
        return Swal.fire({ icon: 'error', title: 'File selector unavailable', confirmButtonColor: '#dc2626' });
    }
    GlobalFileNoModal.open({
        initialValue: row.property_file_number || null,
        autoPopulateGenericFields: false,
        targetFields: [],
        callback: function (data) {
            if (!data.fileNumber || !state.merger.includes(row)) return;
            row.property_file_number = data.fileNumber;
            renderRows('merger');
        }
    });
};

window.openFileSelector = function () {
    if (!window.GlobalFileNoModal) {
        return Swal.fire({ icon: 'error', title: 'File selector unavailable', confirmButtonColor: '#dc2626' });
    }
    GlobalFileNoModal.open({
        callback: function (data) {
            if (!data.fileNumber) return;
            document.getElementById('file_number').value = data.fileNumber;
            if (data.record) {
                const name = data.record.file_name || data.record.applicant_name || '';
                setIfEmpty('app_file_title', name);
                setIfEmpty('app_applicant_name', name);
                setIfEmpty('plot_number', data.record.plot_no);
                setDistrict(data.record.district || data.record.property_district);
                setLga(data.record.lga);
                setAlways('location', locationWithoutPlot(data.record.location || data.record.Location, data.record.plot_no));
            }
            resolveSubject();
        }
    });
};

function setIfEmpty(name, value) {
    const el = document.getElementById(name);
    if (el && !el.value && value) el.value = value;
}

/* Location is read-only and comes from the file, so picking a SECOND file number
   has to replace it — setIfEmpty would leave the first file's location on screen
   with no way to type over it. Blanked when the new file carries none, which lets
   resolveSubject's prefill supply one instead. */
function setAlways(name, value) {
    const el = document.getElementById(name);
    if (el) el.value = value || '';
}

function setDistrict(value) {
    const select = document.getElementById('district');
    const other  = document.getElementById('district_other');
    if (!select || !value || select.value) return;
    const match = [...select.options].find(o => o.value && o.value.toUpperCase() === String(value).trim().toUpperCase());
    if (match) { select.value = match.value; other.classList.add('hidden'); other.value = ''; }
    else { select.value = 'OTHER'; other.classList.remove('hidden'); other.value = value; }
}

function setLga(value) {
    const select = document.getElementById('lga');
    const other  = document.getElementById('lga_other');
    if (!select || !value || select.value) return;
    const match = [...select.options].find(o => o.value && o.value.toUpperCase() === String(value).trim().toUpperCase());
    if (match) { select.value = match.value; other.classList.add('hidden'); other.value = ''; }
    else { select.value = 'OTHER'; other.classList.remove('hidden'); other.value = value; }
}

function locationWithoutPlot(location, plotNo) {
    const loc  = String(location || '').trim();
    const plot = String(plotNo || '').trim();
    if (!loc || !plot) return loc;
    const escaped = plot.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const re = new RegExp('^\\s*(?:plot\\s*(?:no\\.?|number)?\\s*)?' + escaped + '\\s*[,\\-\\/]?\\s+', 'i');
    return loc.replace(re, '').trim() || loc;
}

document.getElementById('district').addEventListener('change', function () {
    const other = document.getElementById('district_other');
    other.classList.toggle('hidden', this.value !== 'OTHER');
    if (this.value !== 'OTHER') other.value = '';
});

document.getElementById('lga').addEventListener('change', function () {
    const other = document.getElementById('lga_other');
    other.classList.toggle('hidden', this.value !== 'OTHER');
    if (this.value !== 'OTHER') other.value = '';
});

async function resolveSubject() {
    const note = document.getElementById('subjectLinkNote');
    const fileNumber = document.getElementById('file_number').value.trim();
    const category = document.getElementById('category').value;
    const type = document.getElementById('parcel_update_type').value;

    document.getElementById('subject_type').value = '';
    document.getElementById('subject_id').value = '';

    if (!fileNumber) {
        note.textContent = '';
        note.className = 'mt-1 text-[10px] text-slate-400';
        return;
    }

    const workflow = category === 'APU' ? 'duplex' : type;
    if (!workflow) {
        note.textContent = 'Choose the parcel update type so the application can be found.';
        note.className = 'mt-1 text-[10px] text-amber-600 font-bold';
        return;
    }

    note.textContent = 'Looking up the application…';
    note.className = 'mt-1 text-[10px] text-slate-400';

    try {
        const res = await fetch(
            `/master-jsi/resolve-subject?subject_type=${encodeURIComponent(workflow)}&file_number=${encodeURIComponent(fileNumber)}`,
            { headers: { 'Accept': 'application/json' } }
        );
        const data = await res.json().catch(() => ({}));

        if (data.found) {
            document.getElementById('subject_type').value = data.subject_type;
            document.getElementById('subject_id').value = data.subject_id;
            note.textContent = data.message;
            note.className = 'mt-1 text-[10px] text-emerald-600 font-bold';
            Object.entries(data.prefill || {}).forEach(([field, val]) => {
                if (field === 'district') setDistrict(val);
                else if (field === 'lga') setLga(val);
                else setIfEmpty(field, val);
            });

            // The applicant is usually the person on screen — the "was present"
            // block is checked by default. Mirror the resolved name and the
            // file-indexing phone into it when it is still blank.
            const present = document.getElementById('applicantPresent');
            if (present && present.checked) {
                setIfEmpty('applicantName', data.prefill?.applicant_name);
                setIfEmpty('applicantPhone', data.applicant_phone);
            }
        } else {
            note.textContent = '';
            note.className = 'mt-1 text-[10px] text-slate-400';
        }
    } catch (e) {
        note.textContent = 'The application could not be looked up. The inspection will be captured unlinked.';
        note.className = 'mt-1 text-[10px] text-amber-600 font-bold';
    } finally {
        backfillCoordinates();
    }
}

document.getElementById('category').addEventListener('change', resolveSubject);
document.getElementById('parcel_update_type').addEventListener('change', resolveSubject);

/* ------------------------------------------------------ location coordinates */
/* Backfill from the file-indexing record for the picked file number; the server
   geocodes the location parts when the indexing row has no coordinates stored. */
window.forceBackfillCoordinates = function () {
    backfillCoordinates(true);
};

window.backfillCoordinates = function (force) {
    const input = document.getElementById('location_coordinates');
    if (!input) return Promise.resolve();

    const fileNumber = (document.getElementById('file_number').value || '').trim();
    if (!fileNumber) { setMapEmptyState(); return Promise.resolve(); }

    const existing = input.value.trim();
    if (!force && existing) {
        const parts = parseCoords(existing);
        if (parts) placeMapMarker(parts.lat, parts.lng);
        else setMapEmptyState();
        return Promise.resolve();
    }

    const districtSelect = document.getElementById('district');
    let district = districtSelect ? districtSelect.value : '';
    if (district === 'OTHER') district = (document.getElementById('district_other')?.value || '').trim();

    const lgaSelect = document.getElementById('lga');
    let lga = lgaSelect ? lgaSelect.value : '';
    if (lga === 'OTHER') lga = (document.getElementById('lga_other')?.value || '').trim();

    const params = new URLSearchParams({ file_number: fileNumber });
    const location = (document.getElementById('location').value || '').trim();
    if (location) params.set('location', location);
    if (district) params.set('district', district);
    if (lga) params.set('lga', lga);

    setCoordNote('Looking up the file-indexing record…');
    return fetch('/master-jsi/coordinates?' + params.toString(), { headers: { 'Accept': 'application/json' } })
        .then(r => r.json().catch(() => ({})))
        .then(data => {
            if (data.success) {
                input.value = data.value;
                document.getElementById('coordsSource').textContent =
                    data.source === 'geocoded' ? 'derived from location' : 'from file indexing';
                setCoordNote(data.source === 'geocoded'
                    ? 'No stored coordinates — the pin was derived from the location instead.'
                    : 'Backfilled from the file-number indexing record. Drag the pin or click the map to fine-tune.');
                placeMapMarker(Number(data.latitude), Number(data.longitude));
            } else {
                setMapEmptyState();
                setCoordNote('No coordinates found for this file number or its location — type them, or click the map to pin the site.');
            }
        })
        .catch(() => {
            setMapEmptyState();
            setCoordNote('Coordinates could not be looked up — type them, or click the map to pin the site.');
        });
};

let jsiMap = null;
let jsiMarker = null;

function ensureMap() {
    if (jsiMap) return jsiMap;
    if (typeof L === 'undefined' || typeof L.map !== 'function') return null;
    const canvas = document.getElementById('jsiMapCanvas');
    if (!canvas) return null;
    jsiMap = L.map(canvas, { scrollWheelZoom: true }).setView([12.0, 8.52], 12);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: '&copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics',
    }).addTo(jsiMap);
    jsiMap.on('click', e => onMapPicked(e.latlng.lat, e.latlng.lng));
    return jsiMap;
}

function placeMapMarker(lat, lng) {
    lat = Number(lat); lng = Number(lng);
    if (!isFinite(lat) || !isFinite(lng)) return setMapEmptyState();
    const canvas = document.getElementById('jsiMapCanvas');
    const empty = document.getElementById('jsiMapEmpty');
    if (canvas) canvas.classList.remove('hidden');
    if (empty) empty.classList.add('hidden');
    const map = ensureMap();
    if (!map) return;
    if (!jsiMarker) {
        jsiMarker = L.marker([lat, lng], { draggable: true }).addTo(map);
        jsiMarker.on('dragend', () => {
            const p = jsiMarker.getLatLng();
            onMapPicked(p.lat, p.lng);
        });
    } else {
        jsiMarker.setLatLng([lat, lng]);
    }
    map.setView([lat, lng], Math.max(map.getZoom(), 16));
    if (!map._size) map.invalidateSize();
}

function onMapPicked(lat, lng) {
    document.getElementById('location_coordinates').value =
        Number(lng).toFixed(7) + ', ' + Number(lat).toFixed(7);
    setCoordNote('Pin placed from the map.');
    placeMapMarker(lat, lng);
}

function setMapEmptyState() {
    if (jsiMap || (document.getElementById('location_coordinates').value || '').trim()) return;
    const canvas = document.getElementById('jsiMapCanvas');
    const empty = document.getElementById('jsiMapEmpty');
    if (canvas) canvas.classList.add('hidden');
    if (empty) empty.classList.remove('hidden');
}

function setCoordNote(msg) {
    const note = document.getElementById('coordsNote');
    if (note) note.textContent = msg || '';
}

/* The field holds "longitude, latitude" (the label reads longitude first, app-wide). */
function parseCoords(value) {
    const parts = String(value || '').trim().split(/,\s*/).map(s => parseFloat(s)).filter(n => isFinite(n));
    if (parts.length < 2) return null;
    return { lat: parts[1], lng: parts[0] };
}

(function initCoordsOnLoad() {
    const input = document.getElementById('location_coordinates');
    if (!document.querySelector('#jsiMapCanvas')) return;
    const existing = (input ? input.value : '').trim();
    if (existing) {
        const parts = parseCoords(existing);
        if (parts) placeMapMarker(parts.lat, parts.lng);
        return;
    }
    if ((document.getElementById('file_number').value || '').trim()) backfillCoordinates();
})();

/* ------------------------------------------------------ submit */
document.getElementById('masterJsiForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    const data = await submitMasterJsiForm(btn);
    btn.disabled = false;
    if (!data) return;
    Swal.fire({ icon: 'success', title: 'Saved', text: data.message, confirmButtonColor: '#dc2626' })
        .then(() => { window.location.href = data.redirect || RETURN_URL; });
});

async function submitMasterJsiForm(btn) {
    try {
        const form = document.getElementById('masterJsiForm');
        const fd = new FormData(form);
        const payload = {};
        for (const [key, val] of fd.entries()) {
            if (key === '_token') continue;
            const path = key.replace(/\]/g, '').split('[');
            let node = payload;
            path.forEach((seg, i) => {
                const last = i === path.length - 1;
                if (seg === '') { if (Array.isArray(node)) node.push(val); return; }
                if (last) { node[seg] = val; return; }
                const nextIsArray = path[i + 1] === '';
                if (node[seg] === undefined) node[seg] = nextIsArray ? [] : {};
                node = node[seg];
            });
        }

        if (payload.district === 'OTHER') payload.district = (payload.district_other || '').trim();
        delete payload.district_other;

        // The inspection purpose(s).
        payload.purposes = selectedPurposes();

        // Persons present: the applicant (if they were) plus any representatives.
        const participants = [];
        if (document.getElementById('applicantPresent').checked) {
            participants.push({
                participant: 'applicant',
                name: document.getElementById('applicantName').value.trim(),
                phone: document.getElementById('applicantPhone').value.trim(),
                relationship: null,
            });
        }
        state.participants.forEach(r => {
            participants.push({
                participant: 'applicant_representative',
                name: r.name.trim(),
                phone: r.phone.trim(),
                relationship: r.relationship.trim() || null,
            });
        });
        payload.participants = participants;

        // Purpose sections.
        payload.merger_properties = state.merger.map(r => ({
            property_file_number: r.property_file_number.trim(),
            plot_number: r.plot_number.trim() || null,
            area_sqm: r.area_sqm === '' ? null : r.area_sqm,
            unit: r.unit,
        }));
        payload.extension_portions = state.extension.map((r, i) => ({
            extension_number: String(i + 1),
            description: r.description.trim() || null,
            area_sqm: r.area_sqm === '' ? null : r.area_sqm,
            unit: r.unit,
        }));
        payload.subdivision_plots = state.subdivision.map(r => ({
            plot_number: r.plot_number.trim() || null,
            area_sqm: r.area_sqm === '' ? null : r.area_sqm,
            unit: r.unit,
            remarks: r.remarks.trim() || null,
        }));
        payload.purpose_changes = state.change_of_purpose.map(r => ({
            current_land_use: r.current_land_use || null,
            proposed_land_use: r.proposed_land_use || null,
            area_sqm: r.area_sqm === '' ? null : r.area_sqm,
            unit: r.unit,
            remarks: r.remarks.trim() || null,
        }));

        // Parent inspection fields from the section 6 cards.
        payload.recommended_merged_area_sqm = state.parent.recommended_merged_area_sqm || null;
        payload.merger_remarks = state.parent.merger_remarks || null;
        payload.extension_remarks = state.parent.extension_remarks || null;

        // Number of units tracks the recorded subdivision plots.
        if (payload.subdivision_plots.length > 0) payload.number_of_units = payload.subdivision_plots.length;

        // Legacy edits carry the old measurement table as posted by its own inputs.
        if (IS_LEGACY && payload.portions && !Array.isArray(payload.portions)) {
            payload.portions = Object.values(payload.portions);
        } else if (!IS_LEGACY && IS_EDIT) {
            payload.portions = [];
        }

        const url = IS_EDIT ? `/master-jsi/${REPORT_ID}` : '/master-jsi';
        const res = await fetch(url, {
            method: IS_EDIT ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            const msg = data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : 'The save was refused.');
            Swal.fire({ icon: 'error', title: 'Not saved', text: msg, confirmButtonColor: '#dc2626' });
            return null;
        }

        return data;
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Not saved', text: err.message, confirmButtonColor: '#dc2626' });
        return null;
    }
}

/* ------------------------------------------------------ legacy sheet engine */
if (IS_LEGACY) {
    const LEGACY = @json($jsLegacy);
    const TEMPLATES = LEGACY.templates;
    const EXISTING = LEGACY.existing;
    const DERIVABLE_TERMS = 2;

    function lhHa(m2) {
        const ha = (Number(m2) || 0) / M2_PER_HECTARE;
        return ha.toLocaleString('en-NG', { minimumFractionDigits: 4, maximumFractionDigits: 4 }) + ' Ha';
    }
    function lhAreaText(n) {
        const v = Number(n) || 0;
        return (v === Math.round(v)
            ? v.toLocaleString('en-NG')
            : v.toLocaleString('en-NG', { minimumFractionDigits: 1, maximumFractionDigits: 2 })) + ' m²';
    }
    function lhTermsOf(slot) {
        const wrap = document.querySelector('.portion-dims[data-slot="' + slot + '"]');
        return wrap ? [...wrap.querySelectorAll('.portion-dim')].map(el => el.value) : [];
    }
    function lhArea(terms) {
        const nums = (terms || []).map(v => parseFloat(v)).filter(v => isFinite(v) && v > 0);
        return nums.length === DERIVABLE_TERMS ? nums[0] * nums[1] : null;
    }
    function lhBoxes(slot, terms) {
        const box = (t, value) => '<div class="relative">'
            + '<input type="number" step="any" min="0" placeholder="' + (t + 1) + '" aria-label="Side ' + (t + 1) + ' in metres"'
            + ' value="' + esc(value) + '" name="portions[' + slot + '][dimensions][]" data-slot="' + slot + '"'
            + ' oninput="lhSync(' + slot + ')" class="portion-dim w-16 px-1.5 py-2 rounded-lg border border-slate-200 bg-white text-sm text-center">'
            + (terms.length > 1
                ? '<button type="button" onclick="lhRemoveDim(' + slot + ',' + t + ')" tabindex="-1"'
                  + ' class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-slate-200 text-slate-500 text-[10px] leading-none hover:bg-red-100 hover:text-red-600">&times;</button>'
                : '')
            + '</div>';
        return '<div class="portion-dims flex flex-wrap items-center gap-1.5" data-slot="' + slot + '">'
            + terms.map(box).join('')
            + '<button type="button" onclick="lhAddDim(' + slot + ')" title="One more side"'
            + ' class="w-8 h-8 rounded-lg border border-dashed border-slate-300 text-slate-400 hover:border-slate-400 hover:text-slate-600 transition">+</button>'
            + '</div>';
    }
    function lhRedraw(slot, terms) {
        const cell = document.querySelector('.portion-dim-cell[data-slot="' + slot + '"]');
        if (cell) { cell.innerHTML = lhBoxes(slot, terms.length ? terms : ['', '']); lhSync(slot); }
    }
    window.lhAddDim = function (slot) { lhRedraw(slot, lhTermsOf(slot).concat([''])); };
    window.lhRemoveDim = function (slot, term) {
        const terms = lhTermsOf(slot);
        if (terms.length <= 1) return;
        terms.splice(Number(term), 1);
        lhRedraw(slot, terms);
    };
    window.lhSync = function (slot) {
        const input = document.querySelector('.portion-area[data-slot="' + slot + '"]');
        const out = document.querySelector('.portion-area-out[data-slot="' + slot + '"]');
        if (!input) return;
        const derived = lhArea(lhTermsOf(slot));
        if (derived !== null) { input.value = derived; input.readOnly = true; input.classList.add('bg-slate-50'); }
        else { input.readOnly = false; input.classList.remove('bg-slate-50'); }
        const m2 = parseFloat(input.value);
        if (out) out.innerHTML = (isFinite(m2) && m2 > 0)
            ? lhAreaText(m2) + ' &middot; <span class="text-emerald-700 font-bold">' + lhHa(m2) + '</span>' : '';
    };
    window.addPortionRow = function () {
        const type = document.getElementById('parcel_update_type').value;
        const template = TEMPLATES[type];
        if (!template || !template.repeatable) return;
        const body = document.getElementById('portionsBody');
        const slot = lhRowSeq++;
        const letter = String.fromCharCode(65 + body.querySelectorAll('tr').length - 1);
        const derivedRow = body.querySelector('tr.is-derived');
        const html = lhRowHtml({ sn: body.querySelectorAll('tr').length, role: 'portion_' + letter.toLowerCase(), label: 'Portion ' + letter, land_use: false, count: false, derived: false }, slot);
        if (derivedRow) derivedRow.insertAdjacentHTML('beforebegin', html); else body.insertAdjacentHTML('beforeend', html);
        lhRenumber(); lhSync(slot);
    };
    function lhRowHtml(row, slot) {
        const existing = EXISTING[row.role] || {};
        const terms = existing.dimensions ? String(existing.dimensions).split(/\s*x\s*/i) : ['', ''];
        const dimCell = row.land_use
            ? '<input type="text" name="portions[' + slot + '][land_use]" value="' + esc(existing.land_use) + '" placeholder="Land use" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">'
            : (row.count
                ? '<input type="number" min="0" name="portions[' + slot + '][unit_count]" value="' + esc(existing.unit_count) + '" placeholder="How many" class="w-40 border border-slate-200 rounded-lg px-3 py-2 text-sm">'
                : lhBoxes(slot, terms));
        const areaCell = row.land_use
            ? '<span class="text-xs text-slate-400">—</span>'
            : '<input type="number" step="any" min="0" name="portions[' + slot + '][area_sqm]" value="' + esc(existing.area_sqm) + '" placeholder="m²" data-slot="' + slot + '" oninput="lhSync(' + slot + ')" class="portion-area w-40 border border-slate-200 rounded-lg px-3 py-2 text-sm">'
              + '<div class="portion-area-out text-[11px] text-slate-500 mt-1" data-slot="' + slot + '"></div>';
        return '<tr class="portion-row ' + (row.derived ? 'is-derived' : '') + '" data-slot="' + slot + '">'
            + '<td class="px-4 py-3 align-top font-black text-slate-500">' + row.sn + '</td>'
            + '<td class="px-4 py-3 align-top">'
            +   '<input type="hidden" name="portions[' + slot + '][role]" value="' + esc(row.role) + '">'
            +   '<input type="hidden" name="portions[' + slot + '][label]" value="' + esc(row.label) + '">'
            +   '<span class="font-bold text-slate-700 text-sm">' + row.label + '</span>'
            +   (row.derived ? '<span class="block text-[10px] font-black text-emerald-600 uppercase tracking-wider mt-0.5">Recommended</span>' : '')
            + '</td>'
            + '<td class="px-4 py-3 align-top portion-dim-cell" data-slot="' + slot + '">' + dimCell + '</td>'
            + '<td class="px-4 py-3 align-top">' + areaCell + '</td></tr>';
    }
    function lhRenumber() {
        document.querySelectorAll('#portionsBody tr').forEach((tr, i) => {
            const cell = tr.querySelector('td');
            if (cell) cell.textContent = i + 1;
        });
    }
    let lhRowSeq = 0;
    function renderLegacy() {
        const type = document.getElementById('parcel_update_type').value;
        const body = document.getElementById('portionsBody');
        const template = TEMPLATES[type];
        const hint = document.getElementById('narrativeHint');
        const addBtn = document.getElementById('addPortionBtn');
        if (!template) { body.innerHTML = ''; hint.textContent = ''; addBtn.classList.add('hidden'); return; }
        hint.textContent = template.narrative;
        lhRowSeq = 0;
        body.innerHTML = template.rows.map(row => lhRowHtml(row, lhRowSeq++)).join('');
        addBtn.classList.toggle('hidden', !template.repeatable);
        template.rows.forEach((row, i) => { if (!row.land_use) lhSync(i); });
        if (window.lucide) lucide.createIcons();
    }

    // Editing a legacy sheet must not redraw on type change (its type is locked in
    // by the rows that exist), so render only once on load.
    renderLegacy();
}

/* ------------------------------------------------------ evidence */
document.getElementById('evidenceKind')?.addEventListener('change', e => {
    const isCoords = e.target.value === 'location_coordinates';
    document.getElementById('evidenceFileWrap')?.classList.toggle('hidden', isCoords);
    document.getElementById('evidenceValueWrap')?.classList.toggle('hidden', !isCoords);
});

window.attachEvidence = async function () {
    const kind = document.getElementById('evidenceKind').value;
    const file = document.getElementById('evidenceFile')?.files[0];
    const value = document.getElementById('evidenceValue')?.value.trim();
    const description = document.getElementById('evidenceDescription')?.value.trim();

    if (!file && !value) {
        document.getElementById('evidenceNote').textContent = 'Choose a file or, for coordinates, type them.';
        return;
    }

    let reportId = REPORT_ID;

    if (!reportId) {
        // Capturing fresh: save the inspection first so the attachment has a report
        // to hang on. submitMasterJsiForm() alerts on refusal itself.
        document.getElementById('evidenceNote').textContent = 'Saving the inspection before attaching…';
        const saved = await submitMasterJsiForm(document.getElementById('saveBtn'));
        if (!saved || !saved.id) return;
        reportId = saved.id;
    }

    const fd = new FormData();
    fd.append('kind', kind);
    fd.append('description', description || '');
    if (file) fd.append('file', file);
    if (value) fd.append('value', value);

    document.getElementById('evidenceNote').textContent = 'Attaching…';

    try {
        const res = await fetch('/master-jsi/' + reportId + '/evidence', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json',
            },
            body: fd,
        });
        const data = await res.json().catch(() => ({}));
        document.getElementById('evidenceNote').textContent = '';

        if (!res.ok) {
            const msg = data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : 'Not attached.');
            return Swal.fire({ icon: 'error', title: 'Not attached', text: msg, confirmButtonColor: '#dc2626' });
        }

        Swal.fire({ icon: 'success', title: 'Attached', text: data.message, confirmButtonColor: '#dc2626' })
            .then(() => REPORT_ID ? window.location.reload() : window.location.assign('/master-jsi/' + reportId + '/edit'));
    } catch (err) {
        document.getElementById('evidenceNote').textContent = '';
        Swal.fire({ icon: 'error', title: 'Not attached', text: err.message, confirmButtonColor: '#dc2626' });
    }
};

window.removeEvidence = async function (id) {
    const res = await Swal.fire({
        icon: 'warning',
        title: 'Remove this attachment?',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, remove',
    });
    if (!res.isConfirmed) return;

    const resp = await fetch('/master-jsi/evidence/' + id, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            'Accept': 'application/json',
        },
    });
    const data = await resp.json().catch(() => ({}));
    if (!resp.ok) {
        return Swal.fire({ icon: 'error', title: 'Not removed', text: data.message || 'The attachment was not removed.', confirmButtonColor: '#dc2626' });
    }
    Swal.fire({ icon: 'success', title: 'Removed', text: data.message, confirmButtonColor: '#dc2626' })
        .then(() => window.location.reload());
};

/* ------------------------------------------------------ boot */
function renderPurposeCheckboxes() {
    if (!state.purposes.length) return;
    state.purposes.forEach(slug => {
        const box = document.querySelector('[data-purpose-box="' + slug + '"]');
        if (box) box.checked = true;
    });
}

renderPurposeCheckboxes();
refreshSections();
if (window.lucide) lucide.createIcons();

document.addEventListener('DOMContentLoaded', function () {
    if (window.GlobalFileNoModal) GlobalFileNoModal.init();
});

/* ------------------------------------------------------ wizard */
const wizard = (() => {
    const STEP_EL = '[data-wstep]';
    const pillBtns = [...document.querySelectorAll('[data-wstep-to]')];
    const prevBtns = [...document.querySelectorAll('[data-wstep-prev], [data-wstep-prev-bottom]')];
    const nextBtns = [...document.querySelectorAll('[data-wstep-next], [data-wstep-next-bottom]')];
    let current = 1;

    const stepEls = () => [...document.querySelectorAll(STEP_EL)];
    const allSteps = () => [...new Set(stepEls().map(el => Number(el.dataset.wstep)))].sort((a, b) => a - b);

    function rebuildPills() {
        const steps = allSteps();
        pillBtns.forEach(btn => {
            const n = Number(btn.dataset.wstepTo);
            const active = n === current;
            btn.classList.toggle('bg-red-600', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('shadow-sm', active);
            btn.classList.toggle('shadow-red-600/20', active);
            btn.classList.toggle('bg-white', !active);
            btn.classList.toggle('bg-slate-50', !active);
            btn.classList.toggle('text-slate-500', !active);
            btn.classList.toggle('border-slate-200', !active);
            btn.classList.toggle('border', !active);
            btn.classList.toggle('hover:bg-slate-50', !active);
            void steps;
        });
    }

    function paint() {
        const last = allSteps()[allSteps().length - 1];
        stepEls().forEach(el => {
            el.style.display = Number(el.dataset.wstep) === current ? '' : 'none';
        });
        prevBtns.forEach(btn => { btn.disabled = current <= 1; });
        nextBtns.forEach(btn => { btn.textContent = current >= last ? 'Save Master JSI' : 'Next →'; });
        rebuildPills();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateVisible() {
        // Only required fields inside the active step block submission.
        let firstBad = null;
        stepEls().forEach(el => {
            if (Number(el.dataset.wstep) !== current) return;
            el.querySelectorAll('input, select, textarea').forEach(f => {
                if (f.required && f.checkValidity && !f.checkValidity()) {
                    if (!firstBad) firstBad = f;
                }
            });
        });
        return firstBad;
    }

    function submitIfComplete() {
        const firstBad = validateVisible();
        if (firstBad) {
            firstBad.reportValidity();
            return;
        }
        document.getElementById('masterJsiForm').requestSubmit();
    }

    function go(n) {
        if (!allSteps().includes(n)) return;
        current = n;
        paint();
    }

    nextBtns.forEach(btn => btn.addEventListener('click', () => {
        const last = allSteps()[allSteps().length - 1];
        if (current >= last) { submitIfComplete(); return; }
        go(current + 1);
    }));

    prevBtns.forEach(btn => btn.addEventListener('click', () => go(current - 1)));
    pillBtns.forEach(btn => btn.addEventListener('click', () => go(Number(btn.dataset.wstepTo))));

    go(1);
    return { go, paint, current: () => current };
})();
</script>
@endsection
