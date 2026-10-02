@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', [
        'PageTitle' => $PageTitle,
        'PageDescription' => 'Search results members have reported as wrong. Correcting and returning one entitles that member to a single free re-run.',
    ])

    <div class="flex-1 p-6">
        @if (session('success'))
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>
        @endif

        {{-- Status filters. Defaults to Edit Requested — what is actually waiting. --}}
        <div class="mb-5 flex flex-wrap items-center gap-2">
            @php
                $tabs = ['all' => 'All'] + \App\Models\Phs\PhsEditRequest::STATUS_LABELS;
                $tone = [
                    'edit_requested'  => 'amber',
                    'ready_for_rerun' => 'emerald',
                    'completed'       => 'slate',
                    'declined'        => 'rose',
                ];
            @endphp
            @foreach ($tabs as $value => $label)
                @php
                    $active = ($statusFilter ?? '') === $value || ($value === 'all' && ($statusFilter ?? '') === 'all');
                    $count = $value === 'all' ? array_sum($statsByStatus) : ($statsByStatus[$value] ?? 0);
                @endphp
                <a href="{{ route('system-admin.phs.edit-requests', ['status' => $value]) }}"
                   class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition
                          {{ $active
                              ? 'border-slate-900 bg-slate-900 text-white'
                              : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300' }}">
                    {{ $label }}
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]
                                 {{ $active ? 'bg-white/20' : 'bg-slate-100 text-slate-600' }}">{{ $count }}</span>
                </a>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Requested</th>
                            <th class="px-4 py-3">File number</th>
                            <th class="px-4 py-3">Organisation / Member</th>
                            <th class="px-4 py-3">Reason</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($items as $item)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3 whitespace-nowrap text-slate-600">
                                    {{ optional($item->requested_at)->format('d M Y H:i') ?? '—' }}
                                    @if ($item->reference_no)
                                        <div class="text-[11px] font-mono text-slate-400">{{ $item->reference_no }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $item->file_number ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ optional($item->institution)->name ?? '—' }}
                                    <div class="text-[11px] text-slate-400">
                                        {{ $item->requester_name ?: (optional($item->member)->name ?? '—') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <div class="font-medium">{{ $item->reasonLabel() }}</div>
                                    <div class="text-[11px] text-slate-400 line-clamp-2 max-w-xs">{{ $item->reason }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @php $t = $tone[$item->status] ?? 'slate'; @endphp
                                    <span class="inline-flex rounded-full bg-{{ $t }}-50 px-2 py-0.5 text-[11px] font-semibold text-{{ $t }}-700 ring-1 ring-{{ $t }}-100">
                                        {{ $item->statusLabel() }}
                                    </span>
                                    @if ($item->status === \App\Models\Phs\PhsEditRequest::STATUS_COMPLETED && $item->rerun_at)
                                        <div class="text-[11px] text-slate-400 mt-0.5">
                                            Re-run {{ $item->rerun_at->format('d M H:i') }} · no charge
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('system-admin.phs.edit-requests.preview', $item->id) }}"
                                       class="inline-flex items-center gap-1.5 rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">
                                        {{ $item->status === \App\Models\Phs\PhsEditRequest::STATUS_EDIT_REQUESTED ? 'Correct' : 'View' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                                    Nothing here. Members have not reported any results with this status.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
