@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Customer Details') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="mx-auto max-w-6xl px-6 py-8">
            <!-- Header with Navigation -->
            <div class="flex flex-col items-start justify-between space-y-4 sm:flex-row sm:items-center sm:space-y-0">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Customer Profile</p>
                    <h1 class="mt-2 text-3xl font-bold text-gray-900">{{ $customer->customer_name }}</h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($customer->entity)
                        <a href="{{ route('entities.show', $customer->entity) }}"
                            class="inline-flex items-center rounded-lg border border-purple-200 bg-purple-50 px-4 py-2 text-sm font-semibold text-purple-700 shadow-sm hover:bg-purple-100">
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                            View Entity
                        </a>
                    @endif
                    <a href="{{ route('customers.edit', $customer) }}"
                        class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                    </a>
                    <a href="{{ route('customers.index') }}"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back
                    </a>
                </div>
            </div>

            <!-- Quick Info Cards -->
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- File Number -->
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">File Number</p>
                            <p class="mt-1 font-mono text-lg font-bold text-blue-900">{{ $customer->file_number ?? '—' }}
                            </p>
                        </div>
                        <svg class="h-8 w-8 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>

                <!-- Account Number -->
                <div class="rounded-lg border border-amber-100 bg-amber-50 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Account Number</p>
                            <p class="mt-1 font-mono text-lg font-bold text-amber-900">{{ $customer->account_no ?? '—' }}
                            </p>
                        </div>
                        <svg class="h-8 w-8 text-amber-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>

                <!-- Customer Type -->
                <div class="rounded-lg border border-purple-100 bg-purple-50 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-purple-600">Type</p>
                            <p class="mt-1 text-lg font-bold text-purple-900">
                                {{ $customer->customer_type === 'Multiple' ? 'Multiple Owners' : $customer->customer_type }}
                            </p>
                        </div>
                        <svg class="h-8 w-8 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Status -->
                <div class="rounded-lg border border-green-100 bg-green-50 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-green-600">Status</p>
                            <div class="mt-1">
                                @php
                                    $statusColors = [
                                        'Active' => 'bg-green-100 text-green-800',
                                        'Pending' => 'bg-yellow-100 text-yellow-800',
                                        'Suspended' => 'bg-red-100 text-red-800',
                                        'Retired' => 'bg-gray-100 text-gray-800',
                                    ];
                                    $statusClass = $statusColors[$customer->status] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $statusClass }}">
                                    {{ $customer->status }}
                                </span>
                            </div>
                        </div>
                        <svg class="h-8 w-8 text-green-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Snapshot Overview -->
            <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Snapshot</h2>
                        <p class="mt-1 text-sm text-gray-600">Key identifiers and lifecycle details for this customer.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c1.105 0 2-.672 2-1.5S13.105 5 12 5s-2 .672-2 1.5S10.895 8 12 8zM7 16a5 5 0 0110 0v3H7v-3z" />
                            </svg>
                            Entity: {{ $customer->entity_id ?? '—' }}
                        </span>
                        <span
                            class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 7h18M3 12h18M3 17h18" />
                            </svg>
                            Code: {{ $customer->customer_code ?? '—' }}
                        </span>
                        <span
                            class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Retired By: {{ $customer->retired_by ?? '—' }}
                        </span>
                    </div>
                </div>

                <dl class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">Entity Details</dt>
                        <dd class="mt-2 flex flex-col gap-1 text-sm text-gray-900">
                            <span>ID: {{ $customer->entity_id ?? '—' }}</span>
                            <span>Link Status: {{ $customer->entity ? 'Linked' : 'Unlinked' }}</span>
                        </dd>
                    </div>

                    <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-blue-700">Customer Code</dt>
                        <dd class="mt-2 font-mono text-sm text-blue-900">{{ $customer->customer_code ?? '—' }}</dd>
                    </div>

                    <div class="rounded-lg border border-amber-100 bg-amber-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-amber-700">Retired By</dt>
                        <dd class="mt-2 text-sm text-amber-900">{{ $customer->retired_by ?? '—' }}</dd>
                    </div>

                    <div class="rounded-lg border border-rose-100 bg-rose-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-rose-700">Reason Retired</dt>
                        <dd class="mt-2 text-sm text-rose-900">{{ $customer->reason_retired ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Main Content Grid -->
            <div class="mt-8 grid gap-6 lg:grid-cols-3">
                <!-- Contact Information Panel -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                        <h2 class="text-lg font-bold text-gray-900">Contact Information</h2>
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="mt-6 grid gap-6 sm:grid-cols-2">
                        <!-- Email -->
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Email</p>
                            <p class="mt-2 flex items-center text-gray-900">
                                <svg class="mr-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                @if($customer->email)
                                    <a href="mailto:{{ $customer->email }}"
                                        class="hover:text-blue-600">{{ $customer->email }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Phone</p>
                            <p class="mt-2 flex items-center text-gray-900">
                                <svg class="mr-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                @if($customer->phone)
                                    <a href="tel:{{ $customer->phone }}" class="hover:text-blue-600">{{ $customer->phone }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </p>
                        </div>

                        <!-- Mobile -->
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Mobile</p>
                            <p class="mt-2 flex items-center text-gray-900">
                                <svg class="mr-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                @if($customer->mobile)
                                    <a href="tel:{{ $customer->mobile }}"
                                        class="hover:text-blue-600">{{ $customer->mobile }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </p>
                        </div>

                        <!-- Customer Code -->
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Customer Code</p>
                            <p class="mt-2 font-mono text-sm text-gray-900">{{ $customer->customer_code ?? '—' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Linked Entity Panel -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                        <h2 class="text-lg font-bold text-gray-900">Linked Entity</h2>
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.658 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </div>
                    @if($customer->entity)
                        <div class="mt-6">
                            <div class="rounded-lg bg-gradient-to-br from-purple-50 to-blue-50 p-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold uppercase tracking-wider text-gray-600">Entity Name</p>
                                        <p class="mt-2 text-lg font-bold text-gray-900">{{ $customer->entity->entity_name }}</p>
                                        <div class="mt-3 flex items-center space-x-2">
                                            <span
                                                class="inline-flex rounded-full bg-purple-100 px-3 py-1 text-xs font-semibold text-purple-800">
                                                {{ $customer->entity->entity_type }}
                                            </span>
                                        </div>
                                    </div>
                                    <svg class="h-8 w-8 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                            </div>
                            <a href="{{ route('entities.show', $customer->entity) }}"
                                class="mt-4 inline-flex items-center rounded-md bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                                View Entity
                            </a>
                        </div>
                    @else
                        <div class="mt-6 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-center">
                            <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.658 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                            <p class="mt-2 text-sm font-medium text-gray-600">No entity linked</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Addresses Section -->
            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                <!-- Property Address -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                        <h2 class="text-lg font-bold text-gray-900">Property Address</h2>
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    @if($customer->property_address)
                        <p class="mt-4 leading-relaxed text-gray-700">{{ $customer->property_address }}</p>
                    @else
                        <div class="mt-4 text-center text-gray-400">
                            <p class="text-sm">No property address on file</p>
                        </div>
                    @endif
                </div>

                <!-- Physical Address -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                        <h2 class="text-lg font-bold text-gray-900">Physical Address</h2>
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-3m0 0l7-4 7 4M5 10v10a1 1 0 001 1h3m10-11l2 3m-2-3V9m-5 11h3a1 1 0 001-1V9m-9 11a9 9 0 019-9" />
                        </svg>
                    </div>
                    @if($customer->physical_address)
                        <p class="mt-4 leading-relaxed text-gray-700">{{ $customer->physical_address }}</p>
                    @else
                        <div class="mt-4 text-center text-gray-400">
                            <p class="text-sm">No physical address on file</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Reason Retired (if applicable) -->
            @if($customer->status === 'Retired' && $customer->reason_retired)
                <div class="mt-8 rounded-lg border border-red-200 bg-red-50 p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-red-200 pb-4">
                        <h2 class="text-lg font-bold text-red-900">Retirement Information</h2>
                        <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="mt-4 text-red-900">{{ $customer->reason_retired }}</p>
                </div>
            @endif

            <!-- Notes Section -->
            @if($customer->notes)
                <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                        <h2 class="text-lg font-bold text-gray-900">Notes</h2>
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <div class="mt-4 space-y-2 rounded-lg bg-gray-50 p-4">
                        <p class="whitespace-pre-line text-gray-700">{{ $customer->notes }}</p>
                    </div>
                </div>
            @endif

            <!-- Metadata Footer -->
            <div class="mt-8 rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-600">Created</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $customer->created_at?->format('M d, Y H:i') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-600">Last Updated</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $customer->updated_at?->format('M d, Y H:i') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-600">Created By</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $customer->created_by ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-600">Updated By</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $customer->updated_by ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        @include('admin.footer')
    </div>
@endsection