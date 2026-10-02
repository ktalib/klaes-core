@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Verify bank-transfer payments and credit tokens.'])

    <div class="flex-1 p-6">

        @if (session('success'))
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-lg bg-amber-50 text-amber-700 ring-1 ring-amber-100">
                        <i data-lucide="file-clock" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">Invoices</h2>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500">All onboarding invoices (bank transfer) for token packages. Pending ones can be verified and approved.</p>
                    </div>
                </div>
                <a href="{{ route('system-admin.phs.index') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i> Back
                </a>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left w-10">S/N</th>
                            <th class="px-5 py-3 text-left">Organization</th>
                            <th class="px-5 py-3 text-left">Package</th>
                            <th class="px-5 py-3 text-right">Expected (₦)</th>
                            <th class="px-5 py-3 text-right">Verified (₦)</th>
                            <th class="px-5 py-3 text-right">Tokens</th>
                            <th class="px-5 py-3 text-left">Invoice #</th>
                            <th class="px-5 py-3 text-center">Payment Status</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($pending as $request)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-5 py-3 text-slate-400 text-xs">{{ $loop->iteration }}</td>
                                <td class="px-5 py-3 font-semibold text-slate-900">{{ \Illuminate\Support\Str::title($request->organization_name) ?: '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $request->initial_token_package ?: '—' }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-900">₦{{ number_format((float) ($request->expected_amount ?? $request->payment_amount ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right text-slate-700">
                                    @if ($request->verified_amount !== null)
                                        ₦{{ number_format((float) $request->verified_amount, 2) }}
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right text-slate-700">
                                    @php
                                        $package = \App\Http\Controllers\Phs\PhsTokenController::packages()[strtolower((string) $request->initial_token_package)] ?? null;
                                        $tokenCount = $package['tokens'] ?? 0;
                                    @endphp
                                    {{ number_format($tokenCount) }}
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $request->invoice_number ?: '—' }}</td>
                                <td class="px-5 py-3 text-center">
                                    @php
                                        $paymentStatus = $request->payment_status ?? 'not_paid';
                                        if ($paymentStatus === 'not_paid') {
                                            $badge = ['Not Paid', 'bg-slate-100 text-slate-700'];
                                        } elseif ($paymentStatus === 'incomplete') {
                                            $badge = ['Incomplete', 'bg-amber-100 text-amber-800'];
                                            $outstanding = $request->outstanding_amount ?? 0;
                                        } elseif ($paymentStatus === 'overpaid') {
                                            $badge = ['Overpaid', 'bg-sky-100 text-sky-800'];
                                        } else {
                                            $badge = ['Completed', 'bg-emerald-100 text-emerald-800'];
                                        }
                                    @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
                                    @if ($paymentStatus === 'incomplete' && isset($outstanding) && $outstanding > 0)
                                        <div class="text-[11px] text-amber-700 mt-1">₦{{ number_format($outstanding, 2) }} outstanding</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('system-admin.phs.requests.invoice', ['id' => $request->id]) }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            <i data-lucide="printer" class="h-3.5 w-3.5"></i> Print
                                        </a>
                                        <a href="{{ route('system-admin.phs.requests.show', ['id' => $request->id]) }}"
                                           class="inline-flex items-center gap-1 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700">
                                            <i data-lucide="eye" class="h-3.5 w-3.5"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-5 py-10 text-center text-slate-500">No invoices found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    window.lucide?.createIcons();
</script>
@endsection
