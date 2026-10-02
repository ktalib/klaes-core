@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Review the organization\'s submitted documents and approve or reject.'])
    <div class="flex-1 p-6">

        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-3xl font-bold">{{ $PageTitle }}</h1>
                <a href="{{ route('system-admin.phs.legal.index') }}" class="text-blue-600 hover:text-blue-800 mt-2 inline-block">← Back to Legal Dashboard</a>
            </div>
            @php
                $statusColors = [
                    'pending'            => 'bg-yellow-100 text-yellow-800',
                    'documents_approved' => 'bg-teal-100 text-teal-800',
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
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">{{ session('error') }}</div>
        @endif

        {{-- Org + contact summary --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
            <div class="grid gap-6 lg:grid-cols-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Organization</h3>
                    <div class="space-y-1.5 text-sm text-slate-700">
                        <p><span class="text-slate-400">Name:</span> <strong>{{ $request->organization_name }}</strong></p>
                        <p><span class="text-slate-400">Type:</span> {{ ucwords(str_replace('_', ' ', $request->organization_type)) }}</p>
                        <p><span class="text-slate-400">Address:</span> {{ $request->address ?? '—' }}</p>
                        <p><span class="text-slate-400">CAC Reg No:</span> {{ $request->cac_registration_number ?? '—' }}</p>
                        <p><span class="text-slate-400">Submitted:</span> {{ $request->created_at->format('M j, Y g:i A') }}</p>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Contact Person</h3>
                    <div class="space-y-1.5 text-sm text-slate-700">
                        <p><span class="text-slate-400">Name:</span> <strong>{{ $request->contact_name }}</strong></p>
                        <p><span class="text-slate-400">Email:</span> {{ $request->contact_email }}</p>
                        <p><span class="text-slate-400">Phone:</span> {{ $request->phone ?? '—' }}</p>
                        <p><span class="text-slate-400">Job Title:</span> {{ $request->job_title ?? '—' }}</p>
                        <p><span class="text-slate-400">Department:</span> {{ $request->department ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Documents --}}
        @php
            $isImg = fn($p) => in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']);
        @endphp

        <div class="grid gap-4 grid-cols-1 lg:grid-cols-3 mb-6">

            {{-- CAC Documentation --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-900 mb-4">CAC Documentation</h3>
                <div class="flex flex-wrap gap-4">
                    @if ($request->cac_document_path)
                        <a href="{{ asset('storage/' . $request->cac_document_path) }}" target="_blank"
                           class="group w-32 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden hover:shadow-md transition-all">
                            <div class="h-24 flex items-center justify-center bg-slate-100">
                                @if ($isImg($request->cac_document_path))
                                    <img src="{{ asset('storage/' . $request->cac_document_path) }}" class="w-full h-full object-cover">
                                @else
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-9 h-11 bg-red-500 rounded-sm flex items-end justify-center pb-1 shadow"><span class="text-white text-[9px] font-bold">PDF</span></div>
                                    </div>
                                @endif
                            </div>
                            <div class="px-3 py-2 border-t border-slate-100"><p class="text-[11px] font-semibold text-slate-700 truncate">CAC Certificate</p><p class="text-[10px] text-slate-400 group-hover:text-blue-500">View →</p></div>
                        </a>
                    @endif
                    @if (!empty($request->additional_documents))
                        @foreach ($request->additional_documents as $doc)
                            <a href="{{ asset('storage/' . $doc) }}" target="_blank"
                               class="group w-32 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden hover:shadow-md transition-all">
                                <div class="h-24 flex items-center justify-center bg-slate-100">
                                    @if ($isImg($doc))
                                        <img src="{{ asset('storage/' . $doc) }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="flex flex-col items-center gap-1">
                                            <div class="w-9 h-11 bg-red-500 rounded-sm flex items-end justify-center pb-1 shadow"><span class="text-white text-[9px] font-bold">PDF</span></div>
                                        </div>
                                    @endif
                                </div>
                                <div class="px-3 py-2 border-t border-slate-100"><p class="text-[11px] font-semibold text-slate-700 truncate">ID Card</p><p class="text-[10px] text-slate-400 group-hover:text-blue-500">View →</p></div>
                            </a>
                        @endforeach
                    @endif
                    @if (!$request->cac_document_path && empty($request->additional_documents))
                        <div class="w-32 h-24 rounded-xl border border-dashed border-slate-300 flex items-center justify-center text-xs text-slate-400">No docs</div>
                    @endif
                </div>
            </div>

            {{-- Request Letter --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-900 mb-4">Request Letter</h3>
                <div class="flex flex-wrap gap-4">
                    @if ($request->request_letter_path)
                        <a href="{{ asset('storage/' . $request->request_letter_path) }}" target="_blank"
                           class="group w-32 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden hover:shadow-md transition-all">
                            <div class="h-24 flex items-center justify-center bg-slate-100">
                                @if ($isImg($request->request_letter_path))
                                    <img src="{{ asset('storage/' . $request->request_letter_path) }}" class="w-full h-full object-cover">
                                @else
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-9 h-11 bg-red-500 rounded-sm flex items-end justify-center pb-1 shadow"><span class="text-white text-[9px] font-bold">PDF</span></div>
                                    </div>
                                @endif
                            </div>
                            <div class="px-3 py-2 border-t border-slate-100"><p class="text-[11px] font-semibold text-slate-700 truncate">Request Letter</p><p class="text-[10px] text-slate-400 group-hover:text-blue-500">View →</p></div>
                        </a>
                    @else
                        <div class="w-32 h-24 rounded-xl border border-dashed border-slate-300 flex items-center justify-center text-xs text-slate-400">Not uploaded</div>
                    @endif
                </div>
            </div>

            {{-- Signed SLA (shown when sla_uploaded) --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-900 mb-4">Signed SLA</h3>
                <div class="flex flex-wrap gap-4">
                    @if ($request->lsa_signed_document_path)
                        <a href="{{ asset('storage/' . $request->lsa_signed_document_path) }}" target="_blank"
                           class="group w-32 rounded-xl border border-green-200 bg-white shadow-sm overflow-hidden hover:shadow-md transition-all">
                            <div class="h-24 flex items-center justify-center bg-green-50">
                                @if ($isImg($request->lsa_signed_document_path))
                                    <img src="{{ asset('storage/' . $request->lsa_signed_document_path) }}" class="w-full h-full object-cover">
                                @else
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-9 h-11 bg-red-500 rounded-sm flex items-end justify-center pb-1 shadow"><span class="text-white text-[9px] font-bold">PDF</span></div>
                                    </div>
                                @endif
                            </div>
                            <div class="px-3 py-2 border-t border-green-100 bg-green-50"><p class="text-[11px] font-semibold text-green-800 truncate">Signed SLA</p><p class="text-[10px] text-green-500 group-hover:text-green-700">View →</p></div>
                        </a>
                        <p class="text-xs text-slate-500 mt-1 w-full">Uploaded {{ $request->lsa_signed_at?->format('M j, Y') }}</p>
                    @else
                        <div class="w-32 h-24 rounded-xl border border-dashed border-amber-300 bg-amber-50 flex flex-col items-center justify-center text-xs text-amber-400 gap-1">
                            <i data-lucide="clock" class="h-5 w-5"></i>Awaiting upload
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- Action buttons --}}
        @if ($request->status === 'pending')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <form method="POST" action="{{ route('system-admin.phs.legal.approve-docs', $request->id) }}">
                    @csrf
                    <div class="rounded-xl border border-green-300 bg-green-50 p-5">
                        <h3 class="font-semibold text-green-900 mb-2">Approve Documents</h3>
                        <p class="text-sm text-green-800 mb-4">Confirm the submitted documents are valid. The Admin will then be able to approve and send the payment link.</p>
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium text-sm">
                            <i data-lucide="check-circle" class="h-4 w-4"></i> Approve Documents
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('system-admin.phs.legal.reject', $request->id) }}">
                    @csrf
                    <div class="rounded-xl border border-red-300 bg-red-50 p-5">
                        <h3 class="font-semibold text-red-900 mb-2">Reject Request</h3>
                        <label class="block text-sm text-red-800 mb-2">Rejection Reason <span class="text-rose-600">*</span></label>
                        <textarea name="rejection_reason" required rows="3"
                            class="w-full px-3 py-2 border border-red-300 rounded-md text-sm mb-3"
                            placeholder="Explain why this request is being rejected..."></textarea>
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium text-sm">
                            <i data-lucide="x-circle" class="h-4 w-4"></i> Reject Request
                        </button>
                    </div>
                </form>
            </div>

        @elseif ($request->status === 'sla_uploaded')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <form method="POST" action="{{ route('system-admin.phs.legal.approve-sla', $request->id) }}">
                    @csrf
                    <div class="rounded-xl border border-green-300 bg-green-50 p-5">
                        <h3 class="font-semibold text-green-900 mb-2">Approve Signed SLA</h3>
                        <p class="text-sm text-green-800 mb-4">Confirm the signed SLA is valid. The Admin will then issue the registration link.</p>
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium text-sm">
                            <i data-lucide="check-circle" class="h-4 w-4"></i> Approve SLA
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('system-admin.phs.legal.reject', $request->id) }}">
                    @csrf
                    <div class="rounded-xl border border-red-300 bg-red-50 p-5">
                        <h3 class="font-semibold text-red-900 mb-2">Reject SLA</h3>
                        <label class="block text-sm text-red-800 mb-2">Rejection Reason <span class="text-rose-600">*</span></label>
                        <textarea name="rejection_reason" required rows="3"
                            class="w-full px-3 py-2 border border-red-300 rounded-md text-sm mb-3"
                            placeholder="Explain the issue with the signed SLA..."></textarea>
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium text-sm">
                            <i data-lucide="x-circle" class="h-4 w-4"></i> Reject SLA
                        </button>
                    </div>
                </form>
            </div>

        @else
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
                This request is currently in <strong>{{ ucwords(str_replace('_', ' ', $request->status)) }}</strong> status. No legal action is required at this stage.
            </div>
        @endif

    </div>
</div>
@endsection
