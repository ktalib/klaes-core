@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'View the onboarding request details and approve or reject the applicant.'])
    <div class="flex-1 p-6">
        <div class="mb-8">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h1 class="text-3xl font-bold">{{ $PageTitle }}</h1>
                    <a href="{{ route('system-admin.phs.requests.index') }}" class="text-blue-600 hover:text-blue-800 mt-2">← Back to Requests</a>
                </div>
                <a href="{{ route('system-admin.phs.requests.invoice', ['id' => $request->id]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="printer" class="h-4 w-4"></i> Print Invoice
                </a>
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
            <span class="px-4 py-2 rounded-full text-sm font-medium {{ $statusColors[$request->status] ?? 'bg-gray-100 text-gray-800' }}">
                {{ ucwords(str_replace('_', ' ', $request->status)) }}
            </span>
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
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Request Overview</p>
                <h2 class="text-2xl font-bold text-slate-900">{{ $request->organization_name }} — {{ str_replace('_', ' ', $request->organization_type) }}</h2>
            </div>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-100">
                {{ ucwords(str_replace('_', ' ', $request->status)) }}
            </span>
        </div>

        <div class="p-6 space-y-6">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-6">
                    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="text-lg font-semibold text-slate-900 mb-3">Organization</h3>
                        <div class="space-y-2 text-sm text-slate-700">
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 6a3 3 0 013-3h8a3 3 0 013 3v8a3 3 0 01-3 3H6a3 3 0 01-3-3V6z"/></svg><span class="text-slate-500">Name:</span> <span class="font-semibold">{{ $request->organization_name }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 5a1 1 0 011-1h12a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V5z"/></svg><span class="text-slate-500">Type:</span> <span class="font-semibold capitalize">{{ str_replace('_', ' ', $request->organization_type) }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.94 10.94a8 8 0 1114.12 0L10 18l-7.06-7.06z"/></svg><span class="text-slate-500">Address:</span> <span class="font-semibold">{{ $request->address ?? 'Not provided' }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 5a2 2 0 012-2h3.28a1 1 0 01.97.757L9.9 7.9a11.04 11.04 0 005.2 5.2l4.14-1.66A1 1 0 0120 11.72V15a2 2 0 01-2 2h-1C9.163 17 3 10.837 3 3V2a1 1 0 011-1h1z"/></svg><span class="text-slate-500">Phone:</span> <span class="font-semibold">{{ $request->phone ?? 'Not provided' }}</span></p>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="text-lg font-semibold text-slate-900 mb-3">Request Details</h3>
                        <div class="space-y-2 text-sm text-slate-700">
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6 2a1 1 0 000 2h8a1 1 0 100-2H6zM3 7a1 1 0 011-1h12a1 1 0 011 1v8a1 1 0 01-1 1H4a1 1 0 01-1-1V7z"/></svg><span class="text-slate-500">Submitted:</span> <span class="font-semibold">{{ $request->created_at->format('F j, Y \a\t g:i A') }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 3h12v4H4V3zM3 9h14v8a1 1 0 01-1 1H4a1 1 0 01-1-1V9z"/></svg><span class="text-slate-500">Preferred Token Package:</span> <span class="font-semibold">{{ $request->initial_token_package ?? 'No preference' }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zM8 9h4v2H8V9z"/></svg><span class="text-slate-500">Payment Status:</span>
                                @if ($request->payment_received_at)
                                    <span class="font-semibold">Received on {{ $request->payment_received_at->format('M j, Y') }}</span>
                                @else
                                    <span class="font-semibold">Pending</span>
                                @endif
                            </p>
                        </div>
                    </section>
                </div>

                <div class="space-y-6">
                    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="text-lg font-semibold text-slate-900 mb-3">Contact</h3>
                        <div class="space-y-2 text-sm text-slate-700">
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a4 4 0 100 8 4 4 0 000-8zM2 18a8 8 0 0116 0H2z"/></svg><span class="text-slate-500">Name:</span> <span class="font-semibold">{{ $request->contact_name }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"/></svg><span class="text-slate-500">Email:</span> <span class="font-semibold">{{ $request->contact_email }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 3h12v4H4V3zM3 9h14v8a1 1 0 01-1 1H4a1 1 0 01-1-1V9z"/></svg><span class="text-slate-500">Job Title:</span> <span class="font-semibold">{{ $request->job_title ?? 'Not provided' }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 4h12v12H4z"/></svg><span class="text-slate-500">Department:</span> <span class="font-semibold">{{ $request->department ?? 'Not provided' }}</span></p>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="text-lg font-semibold text-slate-900 mb-3">Notes</h3>
                        <p class="text-sm text-slate-700">
                            @if ($request->additional_notes)
                                {{ $request->additional_notes }}
                            @else
                                <span class="text-slate-500 italic">None provided</span>
                            @endif
                        </p>
                    </section>
                </div>
            </div>

            @php
                /* Helper: returns true if a stored file path is a viewable image */
                $isImg = fn($p) => in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']);
            @endphp

            {{-- Documents row: CAC | Request Letter | LSA ──────────────── --}}
            <div class="grid gap-4 grid-cols-1 lg:grid-cols-3">

            {{-- CAC Documentation --}}
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <h3 class="text-lg font-semibold text-slate-900 mb-1">CAC Documentation</h3>
                <p class="text-sm text-slate-500 mb-4">
                    CAC Reg No: <strong class="text-slate-800">{{ $request->cac_registration_number ?: 'Not provided' }}</strong>
                </p>
                <div class="flex flex-wrap gap-4">
                    {{-- CAC Certificate --}}
                    @if ($request->cac_document_path)
                        <a href="{{ asset('storage/' . $request->cac_document_path) }}" target="_blank" rel="noopener"
                           class="group w-36 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden hover:shadow-md hover:border-slate-300 transition-all">
                            <div class="h-28 flex items-center justify-center overflow-hidden bg-slate-100">
                                @if ($isImg($request->cac_document_path))
                                    <img src="{{ asset('storage/' . $request->cac_document_path) }}"
                                         alt="CAC Certificate" class="w-full h-full object-cover">
                                @else
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-10 h-12 bg-red-500 rounded-sm flex items-end justify-center pb-1 shadow">
                                            <span class="text-white text-[9px] font-bold tracking-wide">PDF</span>
                                        </div>
                                        <span class="text-[10px] text-slate-400 mt-1">Document</span>
                                    </div>
                                @endif
                            </div>
                            <div class="px-3 py-2 border-t border-slate-100">
                                <p class="text-[11px] font-semibold text-slate-700 truncate">CAC Certificate</p>
                                <p class="text-[10px] text-slate-400 group-hover:text-blue-500 transition-colors">Click to view →</p>
                            </div>
                        </a>
                    @else
                        <div class="w-36 rounded-xl border border-dashed border-slate-300 bg-white flex flex-col items-center justify-center gap-1 h-[7.5rem] text-slate-400">
                            <i data-lucide="file-x" class="h-6 w-6"></i>
                            <span class="text-[11px]">Not uploaded</span>
                        </div>
                    @endif

                    {{-- ID Card (additional_documents) --}}
                    @if (!empty($request->additional_documents))
                        @foreach ($request->additional_documents as $i => $doc)
                            <a href="{{ asset('storage/' . $doc) }}" target="_blank" rel="noopener"
                               class="group w-36 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden hover:shadow-md hover:border-slate-300 transition-all">
                                <div class="h-28 flex items-center justify-center overflow-hidden bg-slate-100">
                                    @if ($isImg($doc))
                                        <img src="{{ asset('storage/' . $doc) }}"
                                             alt="ID Card" class="w-full h-full object-cover">
                                    @else
                                        <div class="flex flex-col items-center gap-1">
                                            <div class="w-10 h-12 bg-red-500 rounded-sm flex items-end justify-center pb-1 shadow">
                                                <span class="text-white text-[9px] font-bold tracking-wide">PDF</span>
                                            </div>
                                            <span class="text-[10px] text-slate-400 mt-1">Document</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="px-3 py-2 border-t border-slate-100">
                                    <p class="text-[11px] font-semibold text-slate-700 truncate">ID Card</p>
                                    <p class="text-[10px] text-slate-400 group-hover:text-blue-500 transition-colors">Click to view →</p>
                                </div>
                            </a>
                        @endforeach
                    @endif
                </div>
            </section>

            {{-- Request Letter --}}
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <h3 class="text-lg font-semibold text-slate-900 mb-1">Request Letter</h3>
                <p class="text-sm text-slate-500 mb-4">Official letter submitted by <strong class="text-slate-700">{{ $request->organization_name }}</strong></p>
                <div class="flex flex-wrap gap-4">
                    @if ($request->request_letter_path)
                        <a href="{{ asset('storage/' . $request->request_letter_path) }}" target="_blank" rel="noopener"
                           class="group w-36 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden hover:shadow-md hover:border-slate-300 transition-all">
                            <div class="h-28 flex items-center justify-center overflow-hidden bg-slate-100">
                                @if ($isImg($request->request_letter_path))
                                    <img src="{{ asset('storage/' . $request->request_letter_path) }}"
                                         alt="Request Letter" class="w-full h-full object-cover">
                                @else
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-10 h-12 bg-red-500 rounded-sm flex items-end justify-center pb-1 shadow">
                                            <span class="text-white text-[9px] font-bold tracking-wide">PDF</span>
                                        </div>
                                        <span class="text-[10px] text-slate-400 mt-1">Document</span>
                                    </div>
                                @endif
                            </div>
                            <div class="px-3 py-2 border-t border-slate-100">
                                <p class="text-[11px] font-semibold text-slate-700 truncate">Request Letter</p>
                                <p class="text-[10px] text-slate-400 group-hover:text-blue-500 transition-colors">Click to view →</p>
                            </div>
                        </a>
                    @else
                        <div class="w-36 rounded-xl border border-dashed border-slate-300 bg-white flex flex-col items-center justify-center gap-1 h-[7.5rem] text-slate-400">
                            <i data-lucide="file-x" class="h-6 w-6"></i>
                            <span class="text-[11px]">Not uploaded</span>
                        </div>
                    @endif
                </div>
            </section>

            {{-- LSA --}}
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Service Level Agreement (SLA)</h3>
                    <div class="flex flex-wrap gap-2">
                        @if ($request->lsa_signed_document_path)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800 ring-1 ring-green-200">
                                <i data-lucide="check-circle" class="h-3.5 w-3.5"></i>
                                Signed — {{ $request->lsa_signed_at?->format('M j, Y') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">
                                <i data-lucide="clock" class="h-3.5 w-3.5"></i> Awaiting Signature
                            </span>
                        @endif
                        @if ($request->lsa_token)
                            <a href="{{ route('phs.lsa.download', [$request->id, $request->lsa_token]) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                                <i data-lucide="file-down" class="h-3.5 w-3.5"></i> Download Blank SLA
                            </a>
                            <a href="{{ route('phs.lsa.upload.form', [$request->id, $request->lsa_token]) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                <i data-lucide="external-link" class="h-3.5 w-3.5"></i> SLA Upload Page
                            </a>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap gap-4">
                    @if ($request->lsa_signed_document_path)
                        <a href="{{ asset('storage/' . $request->lsa_signed_document_path) }}" target="_blank" rel="noopener"
                           class="group w-36 rounded-xl border border-green-200 bg-white shadow-sm overflow-hidden hover:shadow-md hover:border-green-300 transition-all">
                            <div class="h-28 flex items-center justify-center overflow-hidden bg-green-50">
                                @if ($isImg($request->lsa_signed_document_path))
                                    <img src="{{ asset('storage/' . $request->lsa_signed_document_path) }}"
                                         alt="Signed SLA" class="w-full h-full object-cover">
                                @else
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-10 h-12 bg-red-500 rounded-sm flex items-end justify-center pb-1 shadow">
                                            <span class="text-white text-[9px] font-bold tracking-wide">PDF</span>
                                        </div>
                                        <span class="text-[10px] text-slate-400 mt-1">Signed</span>
                                    </div>
                                @endif
                            </div>
                            <div class="px-3 py-2 border-t border-green-100 bg-green-50">
                                <p class="text-[11px] font-semibold text-green-800 truncate">Signed SLA</p>
                                <p class="text-[10px] text-green-500 group-hover:text-green-700 transition-colors">Click to view →</p>
                            </div>
                        </a>
                    @else
                        <div class="w-36 rounded-xl border border-dashed border-amber-300 bg-amber-50 flex flex-col items-center justify-center gap-1 h-[7.5rem] text-amber-400">
                            <i data-lucide="clock" class="h-6 w-6"></i>
                            <span class="text-[11px] font-medium">Awaiting upload</span>
                        </div>
                    @endif
                </div>
            </section>

            </div>{{-- /documents grid --}}

            @if ($request->rejection_reason)
                <div class="rounded-2xl border border-red-200 bg-red-50 p-5">
                    <h3 class="text-lg font-semibold text-red-900 mb-3">Rejection Reason</h3>
                    <p class="text-sm text-red-800">{{ $request->rejection_reason }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Payment Verification -->
    @php
        $pkg = \App\Http\Controllers\Phs\PhsTokenController::packages()[strtolower((string) $request->initial_token_package)] ?? null;
        $expected = $request->expected_amount ?? ($pkg['price'] ?? $request->payment_amount ?? 0);
        $ps = $request->payment_status ?: 'not_paid';
        $payBadge = [
            'completed' => ['Completed', 'bg-green-100 text-green-800 ring-green-200'],
            'incomplete' => ['Incomplete', 'bg-amber-100 text-amber-800 ring-amber-200'],
            'overpaid' => ['Overpaid', 'bg-sky-100 text-sky-800 ring-sky-200'],
            'not_paid' => ['Not Paid', 'bg-gray-100 text-gray-600 ring-gray-200'],
        ][$ps] ?? ['Unknown', 'bg-gray-100 text-gray-600 ring-gray-200'];
    @endphp
    <div class="mt-8 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5 flex items-center justify-between">
            <div>
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Payment Verification</p>
                <h2 class="text-xl font-bold text-slate-900">Confirm the amount received</h2>
            </div>
            <span class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold ring-1 {{ $payBadge[1] }}">{{ $payBadge[0] }}</span>
        </div>
        <div class="p-6 grid gap-6 lg:grid-cols-2">
            <div class="space-y-3 text-sm">
                <div class="flex justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Expected (package price)</span><span class="font-semibold text-slate-900">₦{{ number_format((float) $expected, 2) }}</span></div>
                <div class="flex justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Amount entered by organization</span><span class="font-semibold text-slate-900">₦{{ number_format((float) $request->payment_amount, 2) }}</span></div>
                <div class="flex justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Reference</span><span class="font-mono text-xs text-slate-700">{{ $request->payment_reference ?: '—' }}</span></div>
                <div class="flex justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Verified amount</span><span class="font-semibold text-slate-900">{{ $request->verified_amount !== null ? '₦' . number_format((float) $request->verified_amount, 2) : '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Outstanding balance</span>
                    <span class="font-bold {{ (float) $request->outstanding_amount > 0 ? 'text-amber-700' : 'text-green-700' }}">₦{{ number_format((float) ($request->outstanding_amount ?? 0), 2) }}</span>
                </div>
                @if ($request->payment_verified_at)
                    <p class="text-xs text-slate-400 pt-2">Verified {{ $request->payment_verified_at->format('M j, Y g:i A') }}{{ optional($request->paymentVerifier)->name ? ' by ' . $request->paymentVerifier->name : '' }}.</p>
                @endif
                @if ($request->payment_verification_notes)
                    <p class="text-xs text-slate-500 italic">“{{ $request->payment_verification_notes }}”</p>
                @endif
            </div>

            <form method="POST" action="{{ route('system-admin.phs.requests.verify-payment', ['id' => $request->id]) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-5 space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Expected amount (₦)</label>
                    <input type="number" name="expected_amount" min="0" step="0.01" value="{{ (float) $expected }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Amount received (₦) <span class="text-rose-500">*</span></label>
                    <input type="number" name="verified_amount" min="0" step="0.01" required value="{{ $request->verified_amount }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="0.00">
                    <p class="text-[11px] text-slate-400 mt-1">Enter 0 if nothing has been received yet.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea name="payment_verification_notes" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Bank reference, discrepancy, etc.">{{ $request->payment_verification_notes }}</textarea>
                </div>
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">
                    <i data-lucide="badge-check" class="h-4 w-4"></i> Confirm Payment
                </button>
            </form>
        </div>
    </div>

    <!-- Legal approval trail -->
    @if ($request->legal_approved_at || $request->legal_sla_approved_at)
        <div class="mt-6 bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <h3 class="text-base font-semibold text-slate-900 mb-3">Legal Department Approvals</h3>
            <div class="flex flex-wrap gap-4 text-sm">
                @if ($request->legal_approved_at)
                    <div class="flex items-center gap-2 rounded-full bg-teal-50 border border-teal-200 px-4 py-2">
                        <i data-lucide="check-circle" class="h-4 w-4 text-teal-600"></i>
                        <span class="font-semibold text-teal-800">Docs approved</span>
                        <span class="text-teal-600">{{ $request->legal_approved_at->format('M j, Y') }}</span>
                        @if ($request->legal_approved_by)<span class="text-teal-500 text-xs">by {{ $request->legal_approved_by }}</span>@endif
                    </div>
                @endif
                @if ($request->legal_sla_approved_at)
                    <div class="flex items-center gap-2 rounded-full bg-cyan-50 border border-cyan-200 px-4 py-2">
                        <i data-lucide="check-circle" class="h-4 w-4 text-cyan-600"></i>
                        <span class="font-semibold text-cyan-800">SLA approved</span>
                        <span class="text-cyan-600">{{ $request->legal_sla_approved_at->format('M j, Y') }}</span>
                        @if ($request->legal_sla_approved_by)<span class="text-cyan-500 text-xs">by {{ $request->legal_sla_approved_by }}</span>@endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Paystack payment info -->
    @if ($request->paystack_reference)
        <div class="mt-4 bg-white rounded-2xl border border-slate-200 shadow-sm p-5 text-sm">
            <h3 class="text-base font-semibold text-slate-900 mb-2">Paystack Payment</h3>
            <p><span class="text-slate-400">Reference:</span> <span class="font-mono font-semibold">{{ $request->paystack_reference }}</span></p>
            @if ($request->paystack_amount)<p class="mt-1"><span class="text-slate-400">Verified amount:</span> <span class="font-semibold text-green-700">₦{{ number_format((float) $request->paystack_amount, 2) }}</span></p>@endif
        </div>
    @endif

    <!-- Admin Actions -->
    @if ($request->status === 'documents_approved')
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-1">Admin Actions</h2>
            <p class="text-sm text-slate-500 mb-5">Documents have been approved by Legal. Approving will email the organization a secure link to download, sign, and upload their Service Level Agreement (SLA).</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <form method="POST" action="{{ route('system-admin.phs.requests.approve', ['id' => $request->id]) }}" class="approve-form">
                    @csrf
                    <div class="border border-green-300 rounded-lg p-4 bg-green-50">
                        <h3 class="font-semibold text-green-900 mb-2">Send SLA Link</h3>
                        <p class="text-sm text-green-800 mb-4">Approving will email the organization a secure link to download, sign, and upload their SLA. No payment is requested at this stage.</p>
                        <button type="button" data-org="{{ $request->organization_name }}" data-msg="Send SLA download &amp; upload link to {{ $request->organization_name }}?" class="approve-btn-show w-full flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
                            <i data-lucide="send" class="h-4 w-4"></i> Approve &amp; Send SLA Link
                        </button>
                    </div>
                </form>
                <form method="POST" action="{{ route('system-admin.phs.requests.reject', ['id' => $request->id]) }}">
                    @csrf
                    <div class="border border-red-300 rounded-lg p-4 bg-red-50">
                        <h3 class="font-semibold text-red-900 mb-2">Reject Request</h3>
                        <label class="block text-sm text-red-800 mb-2">Rejection Reason *</label>
                        <textarea name="rejection_reason" required rows="3" class="w-full px-3 py-2 border border-red-300 rounded-md text-sm mb-3" placeholder="Explain why..."></textarea>
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium">
                            <i data-lucide="x-circle" class="h-4 w-4"></i> Reject Request
                        </button>
                    </div>
                </form>
            </div>
        </div>

    @elseif ($request->status === 'sla_approved')
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-1">Final Approval</h2>
            <p class="text-sm text-slate-500 mb-5">The signed SLA has been approved by Legal. Final approval will send the organization a combined payment &amp; onboarding link — once they pay, they proceed straight to registering their account.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <form method="POST" action="{{ route('system-admin.phs.requests.final-approve', ['id' => $request->id]) }}" class="approve-form">
                    @csrf
                    <div class="border border-green-300 rounded-lg p-4 bg-green-50">
                        <h3 class="font-semibold text-green-900 mb-2">Send Payment &amp; Onboarding Link</h3>
                        <p class="text-sm text-green-800 mb-4">This will email the organization a secure link to confirm their package and pay via Paystack. After payment they are taken directly to register their account.</p>
                        <button type="button" data-org="{{ $request->organization_name }}" data-msg="Send payment &amp; onboarding link to {{ $request->organization_name }}?" class="approve-btn-show w-full flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
                            <i data-lucide="check-circle" class="h-4 w-4"></i> Approve &amp; Send Payment Link
                        </button>
                    </div>
                </form>
                <form method="POST" action="{{ route('system-admin.phs.requests.reject', ['id' => $request->id]) }}">
                    @csrf
                    <div class="border border-red-300 rounded-lg p-4 bg-red-50">
                        <h3 class="font-semibold text-red-900 mb-2">Reject Request</h3>
                        <label class="block text-sm text-red-800 mb-2">Rejection Reason *</label>
                        <textarea name="rejection_reason" required rows="3" class="w-full px-3 py-2 border border-red-300 rounded-md text-sm mb-3" placeholder="Explain why..."></textarea>
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium">
                            <i data-lucide="x-circle" class="h-4 w-4"></i> Reject Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @elseif ($request->status === 'awaiting_sla')
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-1">Resend Actions</h2>
            <p class="text-sm text-slate-500 mb-5">This request is currently awaiting the signed SLA. If the contact did not receive the email, you can resend it here.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <form method="POST" action="{{ route('system-admin.phs.requests.resend-sla', ['id' => $request->id]) }}" class="approve-form">
                    @csrf
                    <div class="border border-indigo-300 rounded-lg p-4 bg-indigo-50">
                        <h3 class="font-semibold text-indigo-900 mb-2">Resend SLA Link</h3>
                        <p class="text-sm text-indigo-800 mb-4">Resend the secure link to download, sign, and upload the SLA document to the organization.</p>
                        <button type="button" data-org="{{ $request->organization_name }}" data-msg="Resend SLA link to {{ $request->organization_name }}?" class="approve-btn-show w-full flex items-center justify-center gap-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium py-2">
                            <i data-lucide="send" class="h-4 w-4"></i> Resend SLA Link
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @elseif ($request->status === 'payment_pending')
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-1">Resend Actions</h2>
            <p class="text-sm text-slate-500 mb-5">This request is currently awaiting payment. If the contact did not receive the email, you can resend it here.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <form method="POST" action="{{ route('system-admin.phs.requests.resend-onboarding', ['id' => $request->id]) }}" class="approve-form">
                    @csrf
                    <div class="border border-indigo-300 rounded-lg p-4 bg-indigo-50">
                        <h3 class="font-semibold text-indigo-900 mb-2">Resend Payment &amp; Onboarding Link</h3>
                        <p class="text-sm text-indigo-800 mb-4">Resend the secure link for subscription package payment and member registration to the organization.</p>
                        <button type="button" data-org="{{ $request->organization_name }}" data-msg="Resend payment &amp; onboarding link to {{ $request->organization_name }}?" class="approve-btn-show w-full flex items-center justify-center gap-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium py-2">
                            <i data-lucide="send" class="h-4 w-4"></i> Resend Onboarding Link
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.approve-btn-show').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                var msg = btn.getAttribute('data-msg') || ('Approve ' + (btn.getAttribute('data-org') || 'this request') + '?');
                Swal.fire({
                    title: 'Confirm action',
                    text: msg,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, proceed',
                    cancelButtonText: 'Cancel'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        var form = btn.closest('form');
                        if (form) form.submit();
                    }
                });
            });
        });
    });
</script>
@endsection
