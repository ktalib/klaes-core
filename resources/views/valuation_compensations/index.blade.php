@extends('layouts.app')

@section('page-title')
    {{ __('Valuation for Compensation') }}
@endsection

@section('content')
{{-- 
    Valuation for Compensation (VFC) Dashboard
    - This view serves as the central hub for managing compensation records.
    - It provides high-level statistics and groups individual valuation records by project.
--}}


<script>window.VFC = {};</script>
<div x-data="{}" class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Valuation for Compensation',
        'PageDescription' => 'Manage and generate property compensation valuations'
    ])

    <div class="p-6 space-y-8 max-w-7xl mx-auto">
        <!-- 
            Navigation and Actions Header 
            - Allows access to Workers and Project Management consoles.
            - "CFV Data entry" triggers the creation modal.
        -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Valuation for Compensation</h1>
                <p class="text-slate-500 mt-1">Manage property compensation records and valuations</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('valuation-compensations.workers.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-700 font-semibold shadow-sm hover:bg-slate-50 transition">
                    <i data-lucide="users" class="h-5 w-5"></i>
                    <span>Workers Console</span>
                </a>
                <a href="{{ route('valuation-compensations.projects.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-700 font-semibold shadow-sm hover:bg-slate-50 transition">
                    <i data-lucide="layout-dashboard" class="h-5 w-5"></i>
                    <span>Project Manager Console</span>
                </a>
                <button type="button" onclick="VFC.openCreateModal()" class="vfc-create-trigger inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-blue-600 text-white font-semibold shadow hover:bg-blue-700 transition">
                    <i data-lucide="plus-circle" class="h-5 w-5"></i>
                    <span>VFC Data entry</span>
                </button>
            </div>
        </div>

    <!-- 
        Statistics Cards 
        - Visual summary of the system state including total records, financial exposure, and recent activity.
    -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="h-12 w-12 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
                <i data-lucide="file-text" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Total Records</p>
                <h3 class="text-2xl font-bold text-slate-800">{{ number_format($stats['total_valuations']) }}</h3>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="h-12 w-12 bg-green-50 text-green-600 rounded-lg flex items-center justify-center">
                <i data-lucide="banknote" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Total Compensation</p>
                <h3 class="text-2xl font-bold text-slate-800">₦{{ number_format($stats['total_amount'], 2) }}</h3>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="h-12 w-12 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                <i data-lucide="home" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Total Buildings</p>
                <h3 class="text-2xl font-bold text-slate-800">{{ number_format($stats['total_buildings']) }}</h3>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="h-12 w-12 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center">
                <i data-lucide="calendar" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Captured Today</p>
                <h3 class="text-2xl font-bold text-slate-800">{{ number_format($stats['today_count']) }}</h3>
            </div>
        </div>
    </div>

    <!-- 
        Project Valuation Summary Table 
        - Records are grouped by project to simplify management of large-scale acquisition tasks.
        - Each row provides summary data for a project and access to individual valuation details.
    -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-slate-800">Project Valuation Summary</h2>
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ count($records) }} Projects Active</span>
        </div>
        <div class="p-6">
            <table id="valuationTable" class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-slate-500 text-sm uppercase tracking-wider">
                        <th class="pb-4 font-semibold">S/N</th>
                        <th class="pb-4 font-semibold">Project Name</th>
                        <th class="pb-4 font-semibold">Project Code</th>
                        <th class="pb-4 font-semibold">Project FileNo</th>
                        <th class="pb-4 font-semibold">Records</th>
                        <th class="pb-4 font-semibold">Total Amount</th>
                        <th class="pb-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-slate-600 text-sm">
                    @forelse($records as $projectId => $group)
                        @php
                            $project = $group->first()->project;
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors border-b border-slate-100 group">
                            <td class="px-6 py-4 text-xs font-bold text-slate-400">{{ $loop->iteration }}</td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-800">{{ $project->project_name ?? 'Individual Records' }}</span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs font-bold text-slate-500">{{ $project->project_code ?? 'N/A' }}</td>
                            <td class="px-6 py-4">
                                <a href="javascript:void(0)" onclick="VFC.openRecordsModal('{{ $projectId }}')" 
                                   class="px-2.5 py-1 rounded-lg bg-blue-600 text-white font-mono font-bold text-[11px] shadow-sm hover:bg-blue-700 transition inline-flex items-center gap-2 group/btn">
                                    {{ $project->project_fileno ?? 'N/A' }}
                                    <i data-lucide="eye" class="h-3 w-3 opacity-0 group-hover/btn:opacity-100 transition-opacity"></i>
                                </a>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">{{ $group->count() }}</span>
                            </td>
                            <td class="px-6 py-4 font-bold text-teal-600">
                                ₦{{ number_format($group->sum('total_compensation'), 2) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button onclick="VFC.openRecordsModal('{{ $projectId }}')" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Open Records">
                                    <i data-lucide="external-link" class="h-4 w-4"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 italic">No project valuation records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modals for creating records and viewing project-specific details --}}
@include('valuation_compensations.partials.modal')
@include('valuation_compensations.partials.records_modal')
@include('valuation_compensations.partials.apply_percent_modal')
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    /* 
       VFC Frontend Configuration 
       - Passes necessary backend data and routes to the valuation-compensation.js handler.
    */
    window.VFC_CONFIG = {
        csrf: '{{ csrf_token() }}',
        routes: {
            store: "{{ url('valuation-compensations') }}",
            batchStore: "{{ route('valuation-compensations.batch-store') }}",
            projectPrint: "{{ url('valuation-compensations/project-print') }}",
            projectSelection: "{{ route('valuation-compensations.projects.selection') }}",
            projectWorkers: "{{ url('valuation-compensations/projects') }}"
        }
    };
    window.VFC_BANKS = @json($banks ?? []);
    window.VFC_RECORDS = @json($records ?? []);
</script>
<script src="{{ asset('js/valuation-compensation.js') }}"></script>
<script>
    // Force initialization if not already done by the script itself
    if (window.VFC && typeof window.VFC.init === 'function') {
        console.log('VFC: Explicitly calling init from index.blade.php');
        window.VFC.init();
    }
</script>
@endpush
@endsection
