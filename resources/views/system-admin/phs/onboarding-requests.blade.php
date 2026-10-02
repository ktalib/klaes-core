@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Review and approve PHS onboarding requests submitted by organizations.'])
    <div class="flex-1 p-6">
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-6">{{ $PageTitle }}</h1>

            <!-- Overview Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Requests Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between transition hover:shadow-md">
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Requests</p>
                        <h3 class="text-3xl font-black text-slate-900 mt-2">{{ number_format($overviewStats['total_requests']) }}</h3>
                        <p class="text-xs text-slate-400 mt-1">All registered applications</p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>

                <!-- Pending Actions Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between transition hover:shadow-md">
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Pending Actions</p>
                        <h3 class="text-3xl font-black text-amber-600 mt-2">{{ number_format($overviewStats['pending_actions']) }}</h3>
                        <p class="text-xs text-amber-500 font-semibold mt-1">Requires admin review</p>
                    </div>
                    <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                </div>

                <!-- Active Organizations Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between transition hover:shadow-md">
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Active Partners</p>
                        <h3 class="text-3xl font-black text-emerald-600 mt-2">{{ number_format($overviewStats['active_orgs']) }}</h3>
                        <p class="text-xs text-emerald-500 font-semibold mt-1">Approved & activated</p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                </div>

                <!-- Total Revenue Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between transition hover:shadow-md">
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Revenue</p>
                        <h3 class="text-3xl font-black text-blue-600 mt-2">₦{{ number_format($overviewStats['total_revenue'], 2) }}</h3>
                        <p class="text-xs text-slate-400 mt-1">From verified payments</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Status Filter Tabs -->
        <div class="flex space-x-4 border-b mb-6 overflow-x-auto">
            <a href="{{ route('system-admin.phs.requests.index') }}"
                class="px-4 py-2 border-b-2 font-medium {{ !$statusFilter ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800' }}">
                All ({{ array_sum($statsByStatus) }})
            </a>
            @foreach(['pending', 'documents_approved', 'awaiting_sla', 'sla_uploaded', 'sla_approved', 'payment_pending', 'payment_received', 'approved', 'activated', 'rejected'] as $status)
                <a href="{{ route('system-admin.phs.requests.index', ['status' => $status]) }}"
                    class="px-4 py-2 border-b-2 font-medium {{ $statusFilter === $status ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800' }}">
                    {{ ucwords(str_replace('_', ' ', $status)) }} ({{ $statsByStatus[$status] }})
                </a>
            @endforeach
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            {{ session('error') }}
        </div>
    @endif

    @if ($requests->isEmpty())
        <div class="text-center py-12">
            <p class="text-gray-600">No onboarding requests found.</p>
        </div>
    @else
        <div class="bg-white rounded-lg shadow overflow-visible">
            <table class="w-full">
                <thead class="bg-gray-100 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900 w-10">S/N</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Organization</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Contact</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Email</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Type</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Subscription</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Payment</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">SLA</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Submitted</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $req)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-6 py-4 text-sm">
                                <strong>{{ \Illuminate\Support\Str::title($req->organization_name) }}</strong>
                            </td>
                            <td class="px-6 py-4 text-sm">{{ \Illuminate\Support\Str::title($req->contact_name) }}</td>
                            <td class="px-6 py-4 text-sm">{{ $req->contact_email }}</td>
                            <td class="px-6 py-4 text-sm capitalize">{{ str_replace('_', ' ', $req->organization_type) }}</td>
                            <td class="px-6 py-4 text-sm">
                                @if ($req->initial_token_package)
                                    @php $pkg = \App\Http\Controllers\Phs\PhsTokenController::packages()[strtolower($req->initial_token_package)] ?? null; @endphp
                                    <div class="font-medium text-gray-900">{{ $req->initial_token_package }}</div>
                                    @if ($pkg)
                                        <div class="text-xs text-gray-500">{{ number_format($pkg['tokens']) }} tokens</div>
                                    @endif
                                    @if ($req->payment_amount)
                                        <div class="text-xs text-gray-500">₦{{ number_format((float) $req->payment_amount) }}</div>
                                    @endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @php
                                    $statusColors = [
                                        'pending'            => 'bg-yellow-100 text-yellow-800',
                                        'documents_approved' => 'bg-teal-100 text-teal-800',
                                        'awaiting_sla'       => 'bg-violet-100 text-violet-800',
                                        'payment_pending'    => 'bg-orange-100 text-orange-800',
                                        'payment_received'   => 'bg-blue-100 text-blue-800',
                                        'sla_uploaded'       => 'bg-indigo-100 text-indigo-800',
                                        'sla_approved'       => 'bg-cyan-100 text-cyan-800',
                                        'approved'           => 'bg-purple-100 text-purple-800',
                                        'activated'          => 'bg-green-100 text-green-800',
                                        'rejected'           => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$req->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucwords(str_replace('_', ' ', $req->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @php
                                    $payColors = [
                                        'completed' => 'bg-green-100 text-green-800',
                                        'incomplete' => 'bg-amber-100 text-amber-800',
                                        'overpaid' => 'bg-sky-100 text-sky-800',
                                        'not_paid' => 'bg-gray-100 text-gray-600',
                                    ];
                                    $payLabels = [
                                        'completed' => 'Completed',
                                        'incomplete' => 'Incomplete',
                                        'overpaid' => 'Overpaid',
                                        'not_paid' => 'Not paid',
                                    ];
                                    $ps = $req->payment_status ?: 'not_paid';
                                @endphp
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $payColors[$ps] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $payLabels[$ps] ?? ucfirst($ps) }}
                                </span>
                                @if ($ps === 'incomplete' && $req->outstanding_amount)
                                    <div class="text-xs text-amber-700 mt-1">₦{{ number_format((float) $req->outstanding_amount) }} outstanding</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if ($req->lsa_signed_document_path)
                                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Signed</span>
                                @else
                                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Pending</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">{{ $req->created_at->format('M j, Y') }}</td>
                            <td class="px-6 py-4 text-sm relative">
                                <details class="relative inline-block">
                                                                        <summary class="list-none p-2 rounded-full text-slate-600 hover:bg-slate-100 cursor-pointer" title="Actions" aria-haspopup="true">
                                                                                <!-- vertical ellipsis icon -->
                                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z" />
                                                                                </svg>
                                                                        </summary>
                                    @php
                                        // Action availability gates (greyed-out when false):
                                        //  b. Print Invoice — only once the org has actually paid (invoice exists).
                                        //  c. Approve Request — only after legal approves the documents (sends payment link).
                                        //  d. Send Onboarding Link — only after the signed SLA is approved.
                                        $canPrintInvoice   = $req->isPaymentComplete()
                                                                || !empty($req->invoice_generated_at)
                                                                || !empty($req->invoice_pdf_path);
                                        $canApprove        = $req->status === 'documents_approved';
                                        $canSendOnboarding = $req->status === 'sla_approved';
                                    @endphp
                                    <div class="absolute mt-2 right-0 bg-white border rounded shadow-lg z-50 w-56">
                                        {{-- a. View — always available --}}
                                        <a href="{{ route('system-admin.phs.requests.show', ['id' => $req->id]) }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path d="M2.94 10.94a10.97 10.97 0 0114.12 0 1 1 0 01-1.28 1.54 8.97 8.97 0 00-11.56 0 1 1 0 01-1.28-1.54z" opacity=".2" />
                                                <path d="M10 4a6 6 0 100 12 6 6 0 000-12zM10 8a2 2 0 110 4 2 2 0 010-4z" />
                                            </svg>
                                            View
                                        </a>

                                        {{-- b. Print Invoice — disabled until payment is done --}}
                                        @if ($canPrintInvoice)
                                            <a href="{{ route('system-admin.phs.requests.invoice', ['id' => $req->id]) }}" target="_blank" rel="noopener" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                                Print Invoice
                                            </a>
                                        @else
                                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed" title="Available after payment is completed">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                                Print Invoice
                                            </span>
                                        @endif

                                        {{-- c. Approve Request — disabled until documents are approved --}}
                                        @if ($canApprove)
                                            <form action="{{ route('system-admin.phs.requests.approve', ['id' => $req->id]) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="button"
                                                    class="approve-btn w-full flex items-center gap-2 text-left px-4 py-2 text-sm text-green-700 hover:bg-gray-50"
                                                    data-title="Approve request?"
                                                    data-msg="Approve {{ $req->organization_name }} and send the payment link?"
                                                    data-confirm="Yes, approve">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414-1.414L8 11.172 4.707 7.879a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8z" clip-rule="evenodd" />
                                                    </svg>
                                                    Approve Request
                                                </button>
                                            </form>
                                        @else
                                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed" title="Available once the documents are approved">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414-1.414L8 11.172 4.707 7.879a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8z" clip-rule="evenodd" />
                                                </svg>
                                                Approve Request
                                            </span>
                                        @endif

                                        {{-- d. Send Onboarding Link — disabled until the SLA is approved --}}
                                        @if ($canSendOnboarding)
                                            <form action="{{ route('system-admin.phs.requests.final-approve', ['id' => $req->id]) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="button"
                                                    class="approve-btn w-full flex items-center gap-2 text-left px-4 py-2 text-sm text-green-700 hover:bg-gray-50"
                                                    data-title="Send onboarding link?"
                                                    data-msg="Send the onboarding link to {{ $req->organization_name }}?"
                                                    data-confirm="Yes, send">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
                                                    Send Onboarding Link
                                                </button>
                                            </form>
                                        @else
                                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed" title="Available once the signed SLA is approved">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
                                                Send Onboarding Link
                                            </span>
                                        @endif

                                        {{-- e. Resend SLA Link --}}
                                        @if ($req->status === 'awaiting_sla')
                                            <form action="{{ route('system-admin.phs.requests.resend-sla', ['id' => $req->id]) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="button"
                                                    class="approve-btn w-full flex items-center gap-2 text-left px-4 py-2 text-sm text-indigo-700 hover:bg-gray-50"
                                                    data-title="Resend SLA Link?"
                                                    data-msg="Resend the SLA download &amp; upload link for {{ $req->organization_name }} (request #{{ $req->id }}) to: {{ $req->contact_email }}"
                                                    data-confirm="Yes, resend">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                                    Resend SLA Link
                                                </button>
                                            </form>
                                        @endif

                                        {{-- f. Resend Onboarding/Payment Link --}}
                                        @if ($req->status === 'payment_pending')
                                            <form action="{{ route('system-admin.phs.requests.resend-onboarding', ['id' => $req->id]) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="button"
                                                    class="approve-btn w-full flex items-center gap-2 text-left px-4 py-2 text-sm text-indigo-700 hover:bg-gray-50"
                                                    data-title="Resend Onboarding Link?"
                                                    data-msg="Resend the payment &amp; onboarding link for {{ $req->organization_name }} (request #{{ $req->id }}) to: {{ $req->contact_email }}"
                                                    data-confirm="Yes, resend">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                                    Resend Onboarding Link
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.approve-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = btn.closest('form');
                if (!form) {
                    console.error('[phs] confirm button is not inside a form', btn);
                    return;
                }
                Swal.fire({
                    title: btn.getAttribute('data-title') || 'Confirm?',
                    text: btn.getAttribute('data-msg') || 'Proceed with this action?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: btn.getAttribute('data-confirm') || 'Yes',
                    cancelButtonText: 'No'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        btn.disabled = true;
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush
