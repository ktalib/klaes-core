@extends('layouts.app')

@section('page-title')
    {{ __('LAAS Portal — Ownership transfers') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'LAAS Portal — Ownership transfers',
        'PageDescription' => 'Registered transfers naming someone other than the portal holder.'
    ])

    <div class="py-8 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-6">
                <a href="{{ route('laas-admin.applicants') }}" class="mb-2 inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i> Applicants
                </a>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Ownership transfers</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">
                    A registered Deed of Assignment, Purchase or Gift on a portal file, naming a different person, is listed here.
                    <strong>Approve</strong> moves the file to the new holder's account; the old holder keeps its history up to the registration date.
                    <strong>Dismiss</strong> when it is the same holder spelt differently, or the deed is the file's own root of title.
                </p>
            </div>

            @if(session('status'))
                <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            <div class="mb-5 flex flex-wrap gap-2">
                @foreach(['pending' => 'Pending (' . $pendingCount . ')', 'approved' => 'Approved', 'dismissed' => 'Dismissed', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('laas-admin.transfers', ['status' => $key]) }}"
                       class="rounded-lg border px-4 py-2 text-sm font-semibold {{ $status === $key ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $label }}</a>
                @endforeach
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3 w-12">S/N</th>
                                <th class="px-4 py-3">File No.</th>
                                <th class="px-4 py-3">Instrument</th>
                                <th class="px-4 py-3">Portal holder</th>
                                <th class="px-4 py-3">Named in the deed</th>
                                <th class="px-4 py-3">Name match</th>
                                <th class="px-4 py-3">Registered</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($candidates as $c)
                                @php
                                    $pct = $similarity[$c->id] ?? 0;
                                    $actions = [];
                                    if ($c->status === 'pending') {
                                        $actions[] = ['label' => 'Approve transfer', 'icon' => 'arrow-right-left', 'post' => route('laas-admin.transfers.approve', $c->id),
                                                      'confirm' => 'Move ' . $c->file_number . ' from ' . $c->current_holder . ' to ' . $c->transferee_name . '?'];
                                        $actions[] = ['label' => 'Dismiss (same holder)', 'icon' => 'x-circle', 'post' => route('laas-admin.transfers.dismiss', $c->id),
                                                      'confirm' => $c->file_number . ' stays with ' . $c->current_holder . '. Dismiss?'];
                                    }
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-500">{{ $candidates->firstItem() + $loop->index }}</td>
                                    <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-900">{{ $c->file_number }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-700">{{ $c->instrument_type }}</td>
                                    <td class="px-4 py-3 text-slate-900">{{ $c->current_holder }}</td>
                                    <td class="px-4 py-3">
                                        <p class="text-slate-900">{{ $c->transferee_name }}</p>
                                        @if($c->transferee_phone)
                                            <p class="font-mono text-xs text-slate-500">{{ $c->transferee_phone }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold
                                            {{ $pct >= 80 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}"
                                              title="How alike the two names are. A high score usually means the same holder spelt differently.">
                                            {{ $pct }}%{{ $pct >= 80 ? ' · likely same holder' : '' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $c->registered_at?->format('j M Y') ?: '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold
                                            {{ ['pending' => 'bg-blue-100 text-blue-800', 'approved' => 'bg-green-100 text-green-800', 'dismissed' => 'bg-slate-200 text-slate-700'][$c->status] ?? '' }}">
                                            {{ ucfirst($c->status) }}
                                        </span>
                                        @if($c->decision_note)
                                            <p class="mt-1 max-w-[200px] text-xs text-slate-500">{{ $c->decision_note }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($actions)
                                            @include('laas_admin.partials._row_actions', ['items' => $actions])
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-12 text-center text-slate-500">Nothing here.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">{{ $candidates->links() }}</div>
        </div>
    </div>
</div>
@endsection
