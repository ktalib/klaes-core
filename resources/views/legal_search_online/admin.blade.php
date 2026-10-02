@extends('layouts.app')
@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Monitor payments, searches and activity for the Online Legal Search portal.'])

    <div class="flex-1 p-6 space-y-6">

        {{-- KPI Cards --}}
        @php
            $cards = [
                ['label' => "Today's Revenue",   'value' => '₦' . number_format($stats['today_revenue'], 2),  'icon' => 'calendar-check',  'tone' => 'bg-sky-50 text-sky-700 ring-sky-100'],
                ['label' => "Month Revenue",      'value' => '₦' . number_format($stats['month_revenue'], 2),  'icon' => 'bar-chart-3',     'tone' => 'bg-indigo-50 text-indigo-700 ring-indigo-100'],
                ['label' => "Total Revenue",      'value' => '₦' . number_format($stats['total_revenue'], 2),  'icon' => 'banknote',        'tone' => 'bg-emerald-50 text-emerald-700 ring-emerald-100'],
                ['label' => "Total Paid Searches",'value' => number_format($stats['total_paid']),               'icon' => 'search-check',    'tone' => 'bg-violet-50 text-violet-700 ring-violet-100'],
                ['label' => "Open Complaints",    'value' => number_format($stats['open_feedback']),            'icon' => 'message-circle-warning', 'tone' => 'bg-amber-50 text-amber-700 ring-amber-100'],
            ];
        @endphp

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach($cards as $card)
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500 truncate">{{ $card['label'] }}</p>
                        <p class="mt-2 text-2xl font-extrabold text-slate-900 leading-tight">{{ $card['value'] }}</p>
                    </div>
                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-lg ring-1 {{ $card['tone'] }}">
                        <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Payments Table --}}
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="grid h-9 w-9 place-items-center rounded-md bg-slate-100 text-slate-700">
                        <i data-lucide="credit-card" class="h-4 w-4"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900">Payment Transactions</h3>
                        <p class="text-xs text-slate-500">{{ number_format($payments->total()) }} record(s) total &mdash; page {{ $payments->currentPage() }} of {{ $payments->lastPage() }}</p>
                    </div>
                </div>
                <a href="{{ route('legal-search-online.admin.feedback') }}"
                   class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-200 hover:bg-amber-100 transition">
                    <i data-lucide="message-circle-warning" class="h-3.5 w-3.5"></i>
                    {{ $stats['open_feedback'] }} open complaint{{ $stats['open_feedback'] !== 1 ? 's' : '' }}
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 w-10">#</th>
                            <th class="px-4 py-3">Reference</th>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">File Number</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Paid At</th>
                            <th class="px-4 py-3">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($payments as $i => $p)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3 text-slate-400 text-xs">{{ $payments->firstItem() + $i }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $p->reference }}</td>
                            <td class="px-4 py-3">
                                {{-- The applicant's real name comes from the IYC record: the portal
                                     is public, so the `user` relation is always null here. --}}
                                <div class="font-semibold text-slate-800">
                                    {{ $p->verification?->applicant_full_name ?? $p->user?->name ?? '—' }}
                                </div>
                                <div class="text-xs text-slate-400">{{ $p->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{-- A payment may cover several files, charged per file. Listing them
                                     all is what makes the amount in the next column add up. --}}
                                @php $paidFiles = $p->fileNumbers(); @endphp
                                @forelse($paidFiles as $paidFile)
                                    <div class="{{ count($paidFiles) > 1 ? 'text-xs' : '' }}">{{ $paidFile }}</div>
                                @empty
                                    —
                                @endforelse
                                @if(count($paidFiles) > 1)
                                    <span class="mt-1 inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-bold text-blue-700 ring-1 ring-blue-200">
                                        {{ count($paidFiles) }} files
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-bold text-emerald-700">₦{{ number_format($p->amount / 100, 2) }}</td>
                            <td class="px-4 py-3">
                                @if($p->status === 'paid')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">Paid</span>
                                @elseif($p->status === 'pending')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-700 ring-1 ring-amber-200">Pending</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-50 text-red-700 ring-1 ring-red-200">Failed</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ $p->paid_at ? $p->paid_at->format('d M Y, H:i') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ $p->created_at->format('d M Y, H:i') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-slate-400 text-sm">No payment records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($payments->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $payments->links() }}
            </div>
            @endif
        </div>

    </div>

    @include('admin.footer')
</div>
@endsection
