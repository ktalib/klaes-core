@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Manage subscriptions created from approved invoice purchases.'])
    <div class="flex-1 p-6">
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 w-10">S/N</th>
                        <th class="px-4 py-3">Organization</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Tokens</th>
                        <th class="px-4 py-3">Balance</th>
                        <th class="px-4 py-3">Activation date</th>
                        <th class="px-4 py-3">Expires</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Payment</th>
                        <th class="px-4 py-3 text-right">Invoice</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pending as $txn)
                        @php
                            $activation = optional($txn->institution)->created_at
                                ?? $txn->approved_at
                                ?? $txn->created_at;
                            $activation = $activation ? \Carbon\Carbon::parse($activation) : null;
                            // Subscriptions run for one year from activation.
                            $expires = $txn->expires_at
                                ? \Carbon\Carbon::parse($txn->expires_at)
                                : ($activation ? $activation->copy()->addYear() : null);
                            $expired = $expires && $expires->isPast();
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-slate-400 text-xs">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-bold">{{ \Illuminate\Support\Str::title(optional($txn->institution)->name) }}</td>
                            <td class="px-4 py-3">
                                @if ($txn->type === 'topup')
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">Top-up</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-inset ring-slate-400/20">Subscription</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">NGN {{ number_format((float) $txn->amount, 2) }}</td>
                            <td class="px-4 py-3">{{ number_format($txn->tokens) }}</td>
                            <td class="px-4 py-3">{{ number_format((int) optional($txn->institution)->token_balance) }}</td>
                            <td class="px-4 py-3">
                                @if ($activation)
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">
                                        {{ $activation->format('M j, Y') }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($expires)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $expired ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-amber-50 text-amber-700 ring-amber-600/20' }}">
                                        {{ $expires->format('M j, Y') }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $txn->reference_no }}</td>
                            <td class="px-4 py-3">
                                @switch($txn->validation_status)
                                    @case('verified')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Complete</span>
                                        @break
                                    @case('incomplete')
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20" title="Paid {{ number_format((float) $txn->paid_amount, 2) }} of {{ number_format((float) $txn->expected_amount, 2) }}">Short ₦{{ number_format(abs((float) $txn->payment_variance)) }}</span>
                                        @break
                                    @case('overpaid')
                                        <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-700 ring-1 ring-inset ring-sky-600/20">Overpaid ₦{{ number_format(abs((float) $txn->payment_variance)) }}</span>
                                        @break
                                    @default
                                        <span class="text-slate-400">—</span>
                                @endswitch
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('system-admin.phs.invoices.print', ['txnId' => $txn->id]) }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    <i data-lucide="printer" class="h-3.5 w-3.5"></i> Print
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-4 py-10 text-center text-slate-400">No subscriptions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('admin.footer')
</div>
@endsection
