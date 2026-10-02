@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Token top-up purchases made by organizations.'])

    <div class="flex-1 p-6">
        @if (session('success'))
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>
        @endif

        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                        <i data-lucide="wallet" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">Token Topups</h2>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500">Additional token bundles purchased by organizations beyond their initial subscription.</p>
                    </div>
                </div>
                <a href="{{ route('system-admin.phs.index') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i> Back
                </a>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left w-10">S/N</th>
                            <th class="px-5 py-3 text-left">Organization</th>
                            <th class="px-5 py-3 text-left">Bundle</th>
                            <th class="px-5 py-3 text-right">Bundles</th>
                            <th class="px-5 py-3 text-right">Tokens</th>
                            <th class="px-5 py-3 text-right">Amount (₦)</th>
                            <th class="px-5 py-3 text-left">Method</th>
                            <th class="px-5 py-3 text-center">Status</th>
                            <th class="px-5 py-3 text-left">Date</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($topups as $txn)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-5 py-3 text-slate-400 text-xs">{{ $loop->iteration }}</td>
                                <td class="px-5 py-3 font-semibold text-slate-900">{{ \Illuminate\Support\Str::title(optional($txn->institution)->name) ?: '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $txn->package_name ?: '—' }}</td>
                                <td class="px-5 py-3 text-right text-slate-700">{{ $txn->topup_bundle_count ?: '—' }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-emerald-700">+{{ number_format(abs((int) $txn->tokens)) }}</td>
                                <td class="px-5 py-3 text-right text-slate-900">{{ number_format((float) $txn->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600 capitalize">{{ str_replace('_', ' ', $txn->payment_method ?: '—') }}</td>
                                <td class="px-5 py-3 text-center">
                                    @if ($txn->status === 'completed')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                            <i data-lucide="check" class="h-3 w-3"></i> Credited
                                        </span>
                                    @elseif ($txn->status === 'pending')
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Pending</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">{{ ucfirst($txn->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500">{{ optional($txn->created_at)->format('M j, Y g:i A') }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @if ($txn->status === 'pending')
                                            <form method="POST" action="{{ route('system-admin.phs.topups.approve', ['txnId' => $txn->id]) }}"
                                                  onsubmit="return confirm('Confirm payment received and credit {{ number_format(abs((int) $txn->tokens)) }} tokens to {{ optional($txn->institution)->name }}?');">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">
                                                    <i data-lucide="check-circle" class="h-3.5 w-3.5"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('system-admin.phs.topups.reject', ['txnId' => $txn->id]) }}"
                                                  onsubmit="return confirm('Reject this top-up request? No tokens will be credited.');">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                                    <i data-lucide="x" class="h-3.5 w-3.5"></i> Reject
                                                </button>
                                            </form>
                                        @elseif (optional($txn->institution)->id)
                                            <a href="{{ route('system-admin.phs.institutions.show', ['id' => $txn->institution->id]) }}"
                                               class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                <i data-lucide="eye" class="h-3.5 w-3.5"></i> View Org
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-5 py-10 text-center text-slate-500">No token topups yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
