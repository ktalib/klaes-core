@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Review and approve documents and SLA submissions from onboarding organizations.'])
    <div class="flex-1 p-6">
        <h1 class="text-3xl font-bold mb-6">{{ $PageTitle }}</h1>

        @if (session('success'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">{{ session('error') }}</div>
        @endif

        {{-- Tab navigation --}}
        <div x-data="{ tab: 'docs' }">
        <div class="flex gap-4 border-b border-slate-200 mb-6">
            <button @click="tab = 'docs'"
                :class="tab === 'docs' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-800'"
                class="px-4 py-2 font-medium text-sm">
                Document Review
                @php $pendingDocsCount = $docReview->where('status', 'pending')->count(); @endphp
                @if ($pendingDocsCount > 0)
                    <span class="ml-1.5 inline-flex items-center justify-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-bold text-yellow-800">{{ $pendingDocsCount }}</span>
                @endif
            </button>
            <button @click="tab = 'sla'"
                :class="tab === 'sla' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-800'"
                class="px-4 py-2 font-medium text-sm">
                SLA Review
                @php $pendingSlaCount = $slaReview->where('status', 'sla_uploaded')->count(); @endphp
                @if ($pendingSlaCount > 0)
                    <span class="ml-1.5 inline-flex items-center justify-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-bold text-indigo-800">{{ $pendingSlaCount }}</span>
                @endif
            </button>
        </div>

        <div>

            {{-- Document Review Tab --}}
            <div x-show="tab === 'docs'" x-cloak>
                @if ($docReview->isEmpty())
                    <p class="text-gray-500 text-center py-12">No requests found for document review.</p>
                @else
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-100 border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Organization</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Type</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Contact</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Submitted</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($docReview as $req)
                                    @php $isPending = $req->status === 'pending'; @endphp
                                    <tr class="border-b hover:bg-gray-50 {{ !$isPending ? 'bg-slate-50/70 text-slate-500' : '' }}">
                                        <td class="px-6 py-4 text-sm font-semibold">{{ \Illuminate\Support\Str::title($req->organization_name) }}</td>
                                        <td class="px-6 py-4 text-sm capitalize">{{ str_replace('_', ' ', $req->organization_type) }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            {{ \Illuminate\Support\Str::title($req->contact_name) }}
                                            <br>
                                            <span class="text-xs {{ $isPending ? 'text-gray-500' : 'text-slate-400' }}">{{ $req->contact_email }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-sm">{{ $req->created_at->format('M j, Y') }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            @if ($isPending)
                                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Pending Review</span>
                                            @else
                                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Documents Approved</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            <a href="{{ route('system-admin.phs.legal.show', $req->id) }}"
                                               class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold text-white {{ $isPending ? 'bg-blue-600 hover:bg-blue-700' : 'bg-slate-600 hover:bg-slate-700' }}">
                                                {{ $isPending ? 'Review Documents' : 'View' }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- SLA Review Tab --}}
            <div x-show="tab === 'sla'" x-cloak>
                @if ($slaReview->isEmpty())
                    <p class="text-gray-500 text-center py-12">No requests found for SLA review.</p>
                @else
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-100 border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Organization</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Package</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">SLA Uploaded</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($slaReview as $req)
                                    @php $isPending = $req->status === 'sla_uploaded'; @endphp
                                    <tr class="border-b hover:bg-gray-50 {{ !$isPending ? 'bg-slate-50/70 text-slate-500' : '' }}">
                                        <td class="px-6 py-4 text-sm font-semibold">{{ \Illuminate\Support\Str::title($req->organization_name) }}</td>
                                        <td class="px-6 py-4 text-sm">{{ $req->initial_token_package ?? '—' }}</td>
                                        <td class="px-6 py-4 text-sm">{{ $req->lsa_signed_at?->format('M j, Y') ?? '—' }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            @if ($isPending)
                                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Pending Review</span>
                                            @else
                                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">SLA Approved</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            <a href="{{ route('system-admin.phs.legal.show', $req->id) }}"
                                               class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold text-white {{ $isPending ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-slate-600 hover:bg-slate-700' }}">
                                                {{ $isPending ? 'Review SLA' : 'View' }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
        </div>{{-- end x-data --}}
    </div>
</div>
@endsection
