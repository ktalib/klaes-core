{{-- Read-only register of one batch.
     The Batches tab's expander lists which files are in a batch; this shows what
     was actually captured against each of them, in the columns the letter prints
     from. Nothing here changes a record — editing is still the batch edit form
     and the per-record edit on the main list. --}}
@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header')
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <a href="{{ route('land-recommendations.index', ['type' => $summary['is_oss'] ? 'OSS' : 'ROFO', 'tab' => 'batches']) }}"
                       class="inline-flex items-center gap-2 text-slate-500 hover:text-slate-900 transition text-sm font-medium mb-2">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Batches
                    </a>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">
                        {{ $summary['mother_file_no'] !== '' ? $summary['mother_file_no'] : 'Regular files' }}
                    </h1>
                    <p class="text-slate-500 text-sm mt-1">
                        {{ $summary['application_type'] ?: 'Batch' }} ·
                        <span class="font-mono text-xs">{{ $summary['batch_id'] }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('land-recommendations.batch-edit', $summary['batch_id']) }}"
                       class="inline-flex items-center gap-2 px-5 py-3 bg-white border border-slate-300 text-slate-700 font-bold rounded-xl hover:bg-slate-50 transition shadow-sm">
                        <i data-lucide="pencil" class="h-4 w-4"></i> Edit batch
                    </a>
                    @if($summary['approved'] >= $summary['total'])
                        <a href="{{ route('land-recommendations.batch-print', $summary['batch_id']) }}" target="_blank"
                           class="inline-flex items-center gap-2 px-5 py-3 bg-violet-600 text-white font-bold rounded-xl hover:bg-violet-700 transition shadow-lg shadow-violet-100">
                            <i data-lucide="printer" class="h-4 w-4"></i> Print all ({{ $summary['total'] }})
                        </a>
                    @else
                        <span class="inline-flex items-center gap-2 px-5 py-3 bg-slate-100 text-slate-400 font-bold rounded-xl border border-slate-200 cursor-not-allowed"
                              title="Every recommendation in the batch must be approved before it can be printed">
                            <i data-lucide="printer" class="h-4 w-4"></i> Print all (locked)
                        </span>
                    @endif
                </div>
            </div>

            {{-- Summary strip --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                @php
                    $tiles = [
                        ['label' => 'Records',       'value' => number_format($summary['total']),                                  'icon' => 'layers',       'tone' => 'violet'],
                        ['label' => 'Approved',      'value' => $summary['approved'] . ' of ' . $summary['total'],                 'icon' => 'check-circle', 'tone' => $summary['approved'] >= $summary['total'] ? 'green' : 'amber'],
                        ['label' => 'RofO Generated','value' => $summary['generated'] . ' of ' . $summary['total'],                'icon' => 'file-check',   'tone' => $summary['generated'] >= $summary['total'] ? 'green' : 'slate'],
                        ['label' => 'Captured by',   'value' => $summary['created_by'],                                            'icon' => 'user',         'tone' => 'blue'],
                    ];
                @endphp
                @foreach($tiles as $t)
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                    <div class="p-2.5 bg-{{ $t['tone'] }}-50 text-{{ $t['tone'] }}-600 rounded-xl border border-{{ $t['tone'] }}-100">
                        <i data-lucide="{{ $t['icon'] }}" class="h-5 w-5"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ $t['label'] }}</p>
                        <h3 class="text-lg font-black text-slate-800 tracking-tight truncate">{{ $t['value'] }}</h3>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="list" class="h-4 w-4 text-violet-600"></i>
                        Batch Records
                        <span class="text-slate-400 normal-case tracking-normal font-medium">
                            · {{ number_format($summary['total']) }} record(s), whole
                        </span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[1800px]">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-4 py-4 text-center whitespace-nowrap">S/N</th>
                                <th class="px-4 py-4 whitespace-nowrap">File Number</th>
                                <th class="px-4 py-4 whitespace-nowrap">Applicant Name</th>
                                <th class="px-4 py-4 whitespace-nowrap">Landuse/Purpose Clause</th>
                                <th class="px-4 py-4 whitespace-nowrap">Location</th>
                                <th class="px-4 py-4 whitespace-nowrap text-blue-600">Applicant Address</th>
                                <th class="px-4 py-4 text-center whitespace-nowrap">Plot No</th>
                                <th class="px-4 py-4 text-center whitespace-nowrap">Layout Plan</th>
                                <th class="px-4 py-4 text-center whitespace-nowrap">Term</th>
                                <th class="px-4 py-4 text-right whitespace-nowrap">Ground Rent</th>
                                <th class="px-4 py-4 text-center whitespace-nowrap">Dev. Period</th>
                                <th class="px-4 py-4 text-right whitespace-nowrap">Dev. Value</th>
                                <th class="px-4 py-4 text-right whitespace-nowrap">Dev. Charge</th>
                                <th class="px-4 py-4 text-right whitespace-nowrap">Survey Fees</th>
                                <th class="px-4 py-4 text-right whitespace-nowrap">Prep. Fees</th>
                                <th class="px-4 py-4 text-center whitespace-nowrap">Pg</th>
                                <th class="px-4 py-4 text-center whitespace-nowrap">PP Pg</th>
                                <th class="px-4 py-4 text-center whitespace-nowrap">Status</th>
                                <th class="px-4 py-4 text-center whitespace-nowrap">RofO</th>
                                <th class="px-4 py-4 text-right sticky right-0 bg-slate-50 border-l border-slate-200 z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach($records as $i => $rec)
                            <tr class="hover:bg-violet-50/30 transition">
                                <td class="px-4 py-2 text-center text-slate-400 font-bold whitespace-nowrap">{{ $rec->batch_seq ?: $i + 1 }}</td>
                                <td class="px-4 py-2 font-mono font-bold text-slate-900 whitespace-nowrap">{{ $rec->file_number }}</td>
                                <td class="px-4 py-2 text-blue-900 font-bold uppercase whitespace-nowrap">{{ $rec->applicant_name }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->landuse_purpose }}</td>
                                <td class="px-4 py-2 text-slate-600 uppercase whitespace-nowrap">{{ $rec->display_location }}</td>
                                <td class="px-4 py-2 text-blue-600 italic whitespace-nowrap">{{ $rec->applicant_address ?: '—' }}</td>
                                <td class="px-4 py-2 text-center text-slate-600 whitespace-nowrap">{{ $rec->plot_number ?: '—' }}</td>
                                <td class="px-4 py-2 text-center text-slate-600 whitespace-nowrap">{{ $rec->layout_plan_no ?: '—' }}</td>
                                <td class="px-4 py-2 text-center text-slate-600 whitespace-nowrap">{{ $rec->term ?: '—' }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 whitespace-nowrap">₦{{ number_format($rec->ground_rent, 2) }}</td>
                                <td class="px-4 py-2 text-center text-slate-600 whitespace-nowrap">{{ $rec->development_period_label ?: '—' }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 whitespace-nowrap">₦{{ number_format($rec->development_value, 2) }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 whitespace-nowrap">
                                    {{ $rec->development_charge ? (is_numeric($rec->development_charge) ? '₦' . number_format($rec->development_charge, 2) : $rec->development_charge) : 'NIL' }}
                                </td>
                                <td class="px-4 py-2 text-right text-slate-600 whitespace-nowrap">₦{{ number_format($rec->survey_fees, 2) }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 whitespace-nowrap">₦{{ number_format($rec->preparation_fees, 2) }}</td>
                                <td class="px-4 py-2 text-center text-slate-500 text-xs whitespace-nowrap">{{ $rec->page ?: '—' }}</td>
                                <td class="px-4 py-2 text-center text-slate-500 text-xs whitespace-nowrap">{{ $rec->page_2 ?: '—' }}</td>
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if($rec->status === \App\Models\LandRecommendation::STATUS_APPROVED)
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800">APPROVED</span>
                                    @else
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">PENDING</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if($rec->rofo_status === \App\Models\LandRecommendation::ROFO_GENERATED)
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">GENERATED</span>
                                    @else
                                        <span class="text-[10px] font-bold text-slate-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right sticky right-0 bg-white border-l border-slate-100 z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] whitespace-nowrap">
                                    <a href="{{ route('land-recommendations.edit', $rec->id) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-bold text-blue-700 hover:bg-blue-50 rounded-lg transition">
                                        <i data-lucide="edit-3" class="h-3.5 w-3.5"></i> Edit
                                    </a>
                                    @if($rec->status === \App\Models\LandRecommendation::STATUS_APPROVED)
                                    <a href="{{ route('land-recommendations.print', $rec->id) }}" target="_blank"
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-bold text-violet-700 hover:bg-violet-50 rounded-lg transition">
                                        <i data-lucide="printer" class="h-3.5 w-3.5"></i> Print
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
    @include('admin.footer')
</div>
@endsection
