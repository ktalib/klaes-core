@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Lands One Stop Shop Bill',
        'PageDescription' => '.'
    ])

    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 space-y-6">

            {{-- ── Page heading ── --}}
            <div>
                <p class="text-[10px] font-black text-slate-400 tracking-[0.35em] uppercase mb-1">Lands One Stop Shop</p>
                <h1 class="text-2xl font-extrabold text-slate-900 leading-tight">Bills</h1>
                <p class="text-slate-500 text-sm">{{ $tagline ?? '' }}</p>
            </div>

            {{-- ── Statistic Cards ── --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Total Bills --}}
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center">
                        <i class="fas fa-file-invoice w-5 h-5 text-slate-600"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Bills</p>
                        <p class="text-2xl font-extrabold text-slate-900 leading-tight">{{ number_format($totalBills) }}</p>
                    </div>
                </div>
                {{-- Paid --}}
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <i class="fas fa-circle-check w-5 h-5 text-emerald-600"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Paid</p>
                        <p class="text-2xl font-extrabold text-emerald-700 leading-tight">{{ number_format($paidBills) }}</p>
                    </div>
                </div>
                {{-- Unpaid --}}
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center">
                        <i class="fas fa-clock w-5 h-5 text-amber-600"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Unpaid</p>
                        <p class="text-2xl font-extrabold text-amber-700 leading-tight">{{ number_format($unpaidBills) }}</p>
                    </div>
                </div>
                {{-- Total Amount --}}
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-naira-sign w-5 h-5 text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Amount</p>
                        <p class="text-2xl font-extrabold text-blue-700 leading-tight">&#8358;{{ number_format($totalAmount) }}</p>
                    </div>
                </div>
            </div>

            {{-- ── Bills Table ── --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-100 text-slate-700 uppercase text-[11px] tracking-wider font-bold">
                            <tr>
                                <th class="px-4 py-3 text-left">Reference Bill ID</th>
                                <th class="px-4 py-3 text-left">Application</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-right">Change of Name</th>
                                <th class="px-4 py-3 text-right">Processing Fee</th>
                                <th class="px-4 py-3 text-right">Survey Deposit</th>
                                <th class="px-4 py-3 text-right">Application Form</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($bills as $bill)
                                @php
                                    $total = ($bill->Change_of_Name ?? 0) + ($bill->Processing_Fee ?? 0) + ($bill->survey_fee ?? 0) + ($bill->Application_Form ?? 0);
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ $bill->ref_id ?? ('#' . $bill->ID) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="text-slate-900 font-semibold">{{ $bill->applicant_name ?? '—' }}</div>
                                        <div class="text-[11px] text-slate-500">Application ID: {{ $bill->source_id ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 capitalize">{{ $bill->application_type ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-slate-700">&#8358;{{ number_format($bill->Change_of_Name ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-700">&#8358;{{ number_format($bill->Processing_Fee ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-700">&#8358;{{ number_format($bill->survey_fee ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-700">&#8358;{{ number_format($bill->Application_Form ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-slate-900">&#8358;{{ number_format($total) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-semibold rounded-full
                                            {{ ($bill->Payment_Status ?? '') === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                            {{ strtoupper($bill->Payment_Status ?? 'unpaid') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ optional($bill->created_at)->format('M d, Y h:i A') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-6 text-center text-slate-500">No bills found yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>
@endsection
