@extends('layouts.app')

@section('page-title')
    {{ __('Planning Recommendation') }}
@endsection

@section('content')
@include('actions.parts.recomm_css')

@php
    $jsiApproved = (bool) ($report->is_approved ?? false);
    $jsiStatusClass  = $jsiApproved ? 'bg-green-100 text-green-800'  : 'bg-yellow-100 text-yellow-800';
    $jsiStatusIcon   = $jsiApproved ? 'check-circle' : 'clock';
    $jsiStatusLabel  = $jsiApproved ? 'Approved' : 'Pending';
    $activeTab = 'documents';
@endphp

<div class="flex-1 overflow-auto">
    @include('admin.header')

    <div class="p-6">
        <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">

            {{-- ── Page Header ── --}}
            <div class="p-4 pb-2">
                <div class="flex justify-between items-start gap-4 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2 flex-wrap">
                            Planning Recommendation
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $jsiStatusClass }}">
                                <i data-lucide="{{ $jsiStatusIcon }}" class="w-3 h-3 mr-1"></i>
                                {{ $jsiStatusLabel }}
                            </span>
                        </h2>
                        <div class="text-sm text-gray-500 mt-0.5">
                            Approval Date:
                            <span class="font-medium text-gray-700">
                                {{ !empty($report->approved_at) ? \Carbon\Carbon::parse($report->approved_at)->format('d M Y') : '—' }}
                            </span>
                        </div>
                    </div>
                    <a href="{{ route('physical-planning.regular.planning-recommendation') }}{{ ($isPPDirector ?? false) ? '?role=pp_director' : '' }}"
                       class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        Back to List
                    </a>
                </div>

                <div class="py-2">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-medium text-gray-800">
                                {{ $report->applied_land_use ?? '-' }}  
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{-- Application ID: {{ $report->application_id }} |  --}}
                                File No: {{ $report->file_number ?? '-' }}
                            </p>
                            <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded text-xs font-medium {{ $jsiStatusClass }}">
                                <i data-lucide="{{ $jsiStatusIcon }}" class="w-3 h-3 mr-1"></i>
                                {{ $jsiStatusLabel }}
                            </span>
                        </div>
                        <div class="text-right">
                            <h3 class="text-sm font-medium text-gray-800">{{ $report->applicant_name ?? '—' }}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                    {{ $report->applied_land_use ?? 'Conversion' }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Tab Navigation ── --}}
            <div class="mb-6">
                <div class="bg-gradient-to-br from-slate-50 to-white border border-slate-200 rounded-2xl shadow-sm">
                    <nav class="flex flex-wrap md:flex-nowrap items-stretch gap-2 md:gap-3 p-3 overflow-x-auto" aria-label="Recommendation Tabs">

                        <button type="button" class="tab-button group active" data-tab="documents" role="tab" aria-selected="true">
                            <span class="tab-button__inner">
                                <span class="tab-button__icon"><i data-lucide="folder-open" class="w-4 h-4"></i></span>
                                <span class="tab-button__label"><span>View Documents</span></span>
                            </span>
                            <span class="tab-button__indicator" aria-hidden="true"></span>
                        </button>

                        <button type="button" class="tab-button group" data-tab="schedule" role="tab" aria-selected="false">
                            <span class="tab-button__inner">
                                <span class="tab-button__icon"><i data-lucide="credit-card" class="w-4 h-4"></i></span>
                                <span class="tab-button__label"><span>Schedule of Payment</span></span>
                            </span>
                            <span class="tab-button__indicator" aria-hidden="true"></span>
                        </button>

                        <button type="button" class="tab-button group" data-tab="inspection-details" role="tab" aria-selected="false">
                            <span class="tab-button__inner">
                                <span class="tab-button__icon"><i data-lucide="layout-dashboard" class="w-4 h-4"></i></span>
                                <span class="tab-button__label"><span>Joint Inspection Details</span></span>
                            </span>
                            <span class="tab-button__indicator" aria-hidden="true"></span>
                        </button>

                        <button type="button" class="tab-button group" data-tab="jsi-report" role="tab" aria-selected="false">
                            <span class="tab-button__inner">
                                <span class="tab-button__icon"><i data-lucide="clipboard-check" class="w-4 h-4"></i></span>
                                <span class="tab-button__label"><span>Joint Site Inspection</span></span>
                            </span>
                            <span class="tab-button__indicator" aria-hidden="true"></span>
                        </button>

                        <button type="button" class="tab-button group" data-tab="planning" role="tab" aria-selected="false">
                            <span class="tab-button__inner">
                                <span class="tab-button__icon"><i data-lucide="file-check" class="w-4 h-4"></i></span>
                                <span class="tab-button__label"><span>Planning Recommendation</span></span>
                            </span>
                            <span class="tab-button__indicator" aria-hidden="true"></span>
                        </button>

                    </nav>

                    <style>
                        .tab-button__inner {
                            display: inline-flex;
                            align-items: center;
                            gap: 0.75rem;
                            width: 100%;
                            min-height: 2.75rem;
                            padding: 0.65rem 1rem;
                            border-radius: 0.9rem;
                            border: 1px solid transparent;
                            background: rgba(248, 250, 252, 0.8);
                            color: #475569;
                            font-size: 0.9rem;
                            font-weight: 500;
                            line-height: 1.25rem;
                            transition: all 0.25s ease;
                            box-shadow: inset 0 -1px 0 rgba(148, 163, 184, 0.08);
                        }
                        .tab-button__icon {
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: 2.2rem;
                            height: 2.2rem;
                            border-radius: 999px;
                            background: rgba(191, 219, 254, 0.45);
                            color: #1e3a8a;
                            transition: all 0.25s ease;
                        }
                        .tab-button__label {
                            display: inline-flex;
                            align-items: center;
                            gap: 0.4rem;
                        }
                        .tab-button__indicator {
                            position: absolute;
                            left: 16%;
                            right: 16%;
                            bottom: 6px;
                            height: 3px;
                            border-radius: 999px;
                            background: linear-gradient(90deg, #60a5fa, #2563eb);
                            transform: scaleX(0);
                            transform-origin: center;
                            opacity: 0;
                            transition: transform 0.25s ease, opacity 0.25s ease;
                        }
                        .tab-button {
                            position: relative;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            cursor: pointer;
                            background: transparent;
                            border: none;
                            padding: 0;
                        }
                        .tab-button:hover .tab-button__inner {
                            background: rgba(226, 232, 240, 0.85);
                            color: #1e3a8a;
                            border-color: rgba(148, 163, 184, 0.45);
                        }
                        .tab-button:hover .tab-button__icon {
                            background: rgba(191, 219, 254, 0.7);
                            color: #1d4ed8;
                        }
                        .tab-button:hover .tab-button__indicator {
                            transform: scaleX(0.55);
                            opacity: 0.6;
                        }
                        .tab-button.active .tab-button__inner {
                            background: #ffffff;
                            color: #1d4ed8;
                            border-color: rgba(59, 130, 246, 0.35);
                            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.12), inset 0 0 0 1px rgba(59, 130, 246, 0.2);
                        }
                        .tab-button.active .tab-button__icon {
                            background: rgba(59, 130, 246, 0.15);
                            color: #1d4ed8;
                            box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.15);
                        }
                        .tab-button.active .tab-button__indicator {
                            transform: scaleX(1);
                            opacity: 1;
                        }
                        @media (max-width: 640px) {
                            .tab-button { flex: 1 1 calc(50% - 0.5rem); }
                            .tab-button__inner { justify-content: flex-start; }
                        }
                    </style>
                </div>
            </div>

            {{-- ── Tab Contents ── --}}

            {{-- View Documents --}}
            <div id="documents-tab" class="tab-content active">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b">
                        <h3 class="text-sm font-medium text-slate-800">View Documents</h3>
                        <p class="text-xs text-slate-500 mt-1">Application documents for this conversion record.</p>
                    </div>
                    <div class="p-4">
                        <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-xs text-slate-500">
                            <i data-lucide="folder-open" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                            No document backend integration yet.
                        </div>
                    </div>
                </div>
            </div>

            {{-- Schedule of Payment --}}
            <div id="schedule-tab" class="tab-content">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b bg-sky-50">
                        <h3 class="text-sm font-medium text-sky-800 flex items-center gap-2">
                            <i data-lucide="credit-card" class="w-4 h-4"></i>
                            Schedule of Payment
                        </h3>
                        <p class="text-xs text-sky-600 mt-1">View and print the schedule of payment for this application.</p>
                    </div>
                    <div class="p-4">
                        <div class="flex gap-2 mb-3">
                            <button type="button" onclick="viewScheduleDetails()"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-slate-700 text-white text-xs font-semibold hover:bg-slate-800 transition-colors">
                                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                View Details
                            </button>
                            <button type="button" onclick="printScheduleOfPayment()"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-sky-600 text-white text-xs font-semibold hover:bg-sky-700 transition-colors">
                                <i data-lucide="printer" class="h-3.5 w-3.5"></i>
                                Print
                            </button>
                        </div>
                        <div class="rounded-lg border border-slate-200 overflow-hidden bg-slate-50">
                            <iframe id="schedulePreviewFrame" title="Schedule of Payment Preview" class="w-full h-[68vh]" loading="lazy"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Joint Inspection Details --}}
            <div id="inspection-details-tab" class="tab-content">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b bg-slate-50 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                                <i data-lucide="layout-dashboard" class="w-5 h-5 text-indigo-600"></i>
                                <span>Inspection Details</span>
                            </h3>
                            <p class="text-sm text-slate-600 mt-1">Review the joint site inspection inputs and field observations.</p>
                        </div>
                        <button type="button" onclick="approveOrDeclineJsi()"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition-colors">
                            <i data-lucide="check-check" class="h-3.5 w-3.5"></i>
                            Approve / Decline JSI Report
                        </button>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="hash" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Application</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'application_id', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="folder" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">File Number</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'file_number', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="calendar-days" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Inspection Date</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ !empty(data_get($report, 'inspection_date')) ? \Carbon\Carbon::parse(data_get($report, 'inspection_date'))->format('d M Y') : 'N/A' }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="shield-check" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Compliance</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'compliance_status', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="briefcase" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Purpose</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'purpose', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="workflow" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Source</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'source', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="layers" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Applied Land Use</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'applied_land_use', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="map" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Prevailing Land Use</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'prevailing_land_use', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="map-pin" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Plot Number</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'plot_number', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="badge-info" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">LPKN Number</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'lkn_number', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="ruler" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Existing Site Area (m²)</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'existing_site_area', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="move-diagonal" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Recommended Site Area (m²)</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'recommended_site_area', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="user" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Applicant</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'applicant_name', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="user-check" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Inspection Officer</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ preg_replace('/\s*\[\s*ID\s*:\s*\d+\s*\]\s*$/i', '', (string) data_get($report, 'inspection_officer', 'N/A')) }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="home" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Applicant Address</p>
                                    <p class="text-sm font-semibold text-slate-800 break-words">{{ data_get($report, 'applicant_address', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="navigation" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Property Location</p>
                                    <p class="text-sm font-semibold text-slate-800 break-words">{{ data_get($report, 'location', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="stamp" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Approved By</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ data_get($report, 'approved_by', 'N/A') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                                <i data-lucide="clock-3" class="h-4 w-4 text-indigo-600 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-[11px] text-slate-500 uppercase font-bold tracking-wider">Approved At</p>
                                    <p class="text-sm font-semibold text-slate-800">{{ !empty(data_get($report, 'approved_at')) ? \Carbon\Carbon::parse(data_get($report, 'approved_at'))->format('d M Y, h:i A') : 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Joint Site Inspection Report --}}
            <div id="jsi-report-tab" class="tab-content">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b bg-green-50">
                        <h3 class="text-lg font-medium text-green-800 flex items-center gap-2">
                            <i data-lucide="clipboard-check" class="w-5 h-5"></i>
                            Joint Site Inspection Report
                        </h3>
                        <p class="text-sm text-green-600 mt-1">View and print the joint site inspection report for this application.</p>
                    </div>
                    <div class="p-4">
                        <div class="flex gap-2 mb-3">
                            <button type="button" onclick="printJsiReport()"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700 transition-colors">
                                <i data-lucide="printer" class="h-3.5 w-3.5"></i>
                                Print JSI Report
                            </button>
                        </div>
                        <div class="rounded-lg border border-slate-200 overflow-hidden bg-slate-50">
                            <iframe id="jointSitePreviewFrame" title="Joint Site Inspection Preview" class="w-full h-[68vh]" loading="lazy"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Planning Recommendation --}}
            <div id="planning-tab" class="tab-content">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b bg-violet-50">
                        <h3 class="text-lg font-medium text-violet-800 flex items-center gap-2">
                            <i data-lucide="file-check" class="w-5 h-5"></i>
                            Planning Recommendation
                        </h3>
                        <p class="text-sm text-violet-600 mt-1">View and print the planning recommendation document.</p>
                    </div>
                    <div class="p-4">
                        <div class="flex gap-2 mb-3">
                            <button type="button" onclick="viewRecommendationDetails()"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-violet-700 text-white text-xs font-semibold hover:bg-violet-800 transition-colors">
                                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                View Details
                            </button>
                            <button type="button" onclick="printRecommendation()"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-violet-600 text-white text-xs font-semibold hover:bg-violet-700 transition-colors">
                                <i data-lucide="file-text" class="h-3.5 w-3.5"></i>
                                Print Recommendation
                            </button>
                        </div>
                        <div class="rounded-lg border border-slate-200 overflow-hidden bg-slate-50">
                            <iframe id="planningPreviewFrame" title="Planning Recommendation Preview" class="w-full h-[68vh]" loading="lazy"></iframe>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @include('admin.footer')
</div>

<script>
(function () {
    const applicationId = @json($report->application_id);
    const approveUrl = @json(route('planning-recommendation.joint-inspection.approve'));
    const jsiShowTemplate = @json(route('planning-recommendation.joint-inspection.show', ['application' => '__APP_ID__']));
    const recommendationTemplate = @json(route('physical-planning.conversion.recommendation.print', ['applicationId' => '__APP_ID__']));
    const scheduleTemplate = @json(route('physical-planning.conversion.schedule-of-payment.print', ['applicationId' => '__APP_ID__']));

    function replaceAppId(template) {
        return String(template || '').replace('__APP_ID__', encodeURIComponent(applicationId));
    }

    // ── Tab switching (mirrors _navigation.blade.php logic) ──────────────────
    const tabButtons  = Array.from(document.querySelectorAll('.tab-button'));
    const tabContents = Array.from(document.querySelectorAll('.tab-content'));

    function activateTab(tabId, triggeringButton) {
        const target = document.getElementById(tabId + '-tab');
        if (!target) return;

        tabButtons.forEach(function (btn) {
            const active = btn === triggeringButton;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        tabContents.forEach(function (el) {
            el.classList.toggle('active', el === target);
        });

        loadTabPreview(tabId);

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const tabId = btn.getAttribute('data-tab');
            if (tabId) activateTab(tabId, btn);
        });
    });

    // ── Lazy-load iframes ───────────────────────────────────────────────────
    function ensureLoaded(frameId, url) {
        const frame = document.getElementById(frameId);
        if (frame && url && !frame.getAttribute('src')) {
            frame.setAttribute('src', url);
        }
    }

    function loadTabPreview(tabId) {
        if (tabId === 'schedule')          ensureLoaded('schedulePreviewFrame',  replaceAppId(scheduleTemplate));
        if (tabId === 'jsi-report')        ensureLoaded('jointSitePreviewFrame', replaceAppId(jsiShowTemplate) + '?print=1');
        if (tabId === 'planning')          ensureLoaded('planningPreviewFrame',  replaceAppId(recommendationTemplate));
    }

    // ── Public API ──────────────────────────────────────────────────────────
    window.viewScheduleDetails     = function () { window.open(replaceAppId(scheduleTemplate), '_blank'); };
    window.printScheduleOfPayment  = function () { window.open(replaceAppId(scheduleTemplate), '_blank'); };
    window.printJsiReport          = function () { window.open(replaceAppId(jsiShowTemplate) + '?print=1', '_blank'); };
    window.viewRecommendationDetails = function () { window.open(replaceAppId(recommendationTemplate), '_blank'); };
    window.printRecommendation     = function () { window.open(replaceAppId(recommendationTemplate), '_blank'); };

    window.approveOrDeclineJsi = function () {
        Swal.fire({
            title: 'Approve or Decline JSI Report',
            text: 'Choose an action for this report.',
            icon: 'question',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Approve',
            denyButtonText: 'Decline',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (result.isConfirmed) {
                fetch(approveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': @json(csrf_token())
                    },
                    body: JSON.stringify({ application_id: applicationId })
                })
                .then(async function (response) {
                    const payload = await response.json();
                    if (!response.ok || !payload.success) throw new Error(payload.message || 'Failed to approve JSI report.');
                    Swal.fire({ icon: 'success', title: 'Approved', text: payload.message || 'JSI report approved.' })
                        .then(function () { window.location.reload(); });
                })
                .catch(function (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'Unable to approve JSI report.' });
                });
                return;
            }
            if (result.isDenied) {
                Swal.fire({ icon: 'info', title: 'Decline Action', text: 'Decline endpoint is not yet available.' });
            }
        });
    };

    if (typeof lucide !== 'undefined') lucide.createIcons();
})();
</script>
@endsection
