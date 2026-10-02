@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Entity Details') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')

    @php
        $customers = $entity->customers ?? collect();
        $statusColors = [
            'Active' => 'bg-green-100 text-green-800',
            'Pending' => 'bg-amber-100 text-amber-800',
            'Suspended' => 'bg-rose-100 text-rose-800',
            'Retired' => 'bg-gray-200 text-gray-800',
        ];
        $typeIcons = [
            'Individual' => 'user',
            'Corporate' => 'building-2',
            'Multiple' => 'users',
        ];
        $icon = $typeIcons[$entity->entity_type] ?? 'layers';
        $customersCount = $customers->count();
        $recentCustomers = $customers->sortByDesc('updated_at')->take(6);
    @endphp

    <div class="mx-auto max-w-6xl px-6 py-8">
        <!-- Header -->
        <div
            class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-50 via-white to-purple-50 p-8 text-slate-900 shadow-xl">
            <div class="absolute -right-20 -top-24 h-40 w-40 rounded-full bg-blue-200/30 blur-3xl"></div>
            <div class="absolute bottom-0 left-0 h-32 w-32 rounded-full bg-purple-200/40 blur-2xl"></div>
            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-xl border border-blue-200 bg-blue-500/10 text-blue-600">
                        <i data-lucide="{{ $icon }}" class="h-7 w-7"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-blue-600">Entity Capsule</p>
                        <h1 class="mt-2 text-3xl font-bold">{{ $entity->entity_name }}</h1>
                        <p class="mt-3 max-w-xl text-sm text-slate-600">Insights, relationships, and operational context
                            for this entity’s footprint across the registry.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('entities.index') }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-white/80 px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm backdrop-blur hover:bg-blue-50">
                        <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Entities
                    </a>
                    <a href="{{ route('entities.customers', $entity) }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 7h18M3 12h18M3 17h18" />
                        </svg>
                        View Linked Customers
                    </a>
                    <a href="{{ route('entities.edit', $entity) }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-500">
                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit Entity
                    </a>
                </div>
            </div>
        </div>

        <!-- At-a-glance cards -->
        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-blue-100 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl border border-blue-200 bg-blue-500/10 text-blue-600">
                        <i data-lucide="{{ $icon }}" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Entity Type</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900">
                            {{ $entity->entity_type === 'Multiple' ? 'Multiple Owners' : $entity->entity_type }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-5 text-indigo-900 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600">Linked Customers</p>
                        <p class="mt-2 text-3xl font-bold">{{ number_format($customersCount) }}</p>
                    </div>
                    <svg class="h-8 w-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5V4H2v16h5m2 0h8m-8 0v-6a1 1 0 011-1h6a1 1 0 011 1v6" />
                    </svg>
                </div>
                <p class="mt-3 text-xs text-indigo-500">{{ $recentCustomers->count() }} recently touched records</p>
            </div>

            <div class="rounded-2xl border border-purple-100 bg-purple-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-purple-600">File Number</p>
                <p class="mt-2 font-mono text-lg font-bold text-purple-900">{{ $entity->file_number ?? '—' }}</p>
                <p class="mt-1 text-xs text-purple-500">Referenced in legacy filings</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-600">Timestamps</p>
                <p class="mt-2 text-sm font-medium text-gray-900">Updated
                    {{ $entity->updated_at?->format('M d, Y H:i') ?? '—' }}
                </p>
                <p class="mt-1 text-xs text-gray-500">Created {{ $entity->created_at?->format('M d, Y H:i') ?? '—' }}
                </p>
            </div>
        </div>

        <!-- Detail + recent activity -->
        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                    <h2 class="text-lg font-bold text-gray-900">Linked Customers</h2>
                    <span class="text-sm text-gray-500">Showing {{ $recentCustomers->count() }} of
                        {{ $customersCount }}</span>
                </div>
                @if($recentCustomers->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-3 py-10 text-center text-gray-500">
                        <svg class="h-10 w-10 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5V4H2v16h5m2 0h8m-8 0v-6a1 1 0 011-1h6a1 1 0 011 1v6" />
                        </svg>
                        <p class="text-sm font-semibold text-gray-600">No customer records linked yet.</p>
                        <a href="{{ route('customers.create') }}"
                            class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Create
                            Customer</a>
                    </div>
                @else
                <div class="mt-6 space-y-4">
                    @foreach($recentCustomers as $customer)
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 shadow-sm">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $customer->customer_name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $customer->customer_type === 'Multiple' ? 'Multiple Owners' : $customer->customer_type }}
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-gray-600">
                                    <span
                                        class="inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1 font-medium text-blue-700">
                                        <svg class="h-3 w-3 text-blue-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        File {{ $customer->file_number ?? '—' }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 font-medium text-emerald-700">
                                        <svg class="h-3 w-3 text-emerald-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        {{ $customer->phone ?? 'No phone' }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-2 rounded-full border border-amber-100 bg-amber-50 px-3 py-1 font-medium text-amber-700">
                                        <svg class="h-3 w-3 text-amber-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        {{ $customer->email ?? 'No email' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-3 text-right">
                                @php($badge = $statusColors[$customer->status] ?? 'bg-gray-200 text-gray-800')
                                <span
                                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}">{{ $customer->status }}</span>
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                    <svg class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3" />
                                    </svg>
                                    <span>{{ $customer->updated_at?->diffForHumans() ?? '—' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('customers.show', $customer) }}"
                                        class="text-sm font-semibold text-blue-600 hover:text-blue-500">View</a>
                                    <span class="text-gray-300">|</span>
                                    <a href="{{ route('customers.edit', $customer) }}"
                                        class="text-sm font-semibold text-purple-600 hover:text-purple-500">Edit</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @if($customersCount > $recentCustomers->count())
                    <div class="mt-4 text-right">
                        <a href="{{ route('entities.customers', $entity) }}"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-500">
                            View full customer list
                            <svg class="h-4 w-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                    </div>
                @endif
                @endif
            </div>

            <div class="space-y-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900">Entity Metadata</h2>
                    <dl class="mt-4 space-y-3 text-sm text-slate-700">
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Entity ID</dt>
                            <dd>#{{ $entity->id }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Created At</dt>
                            <dd>{{ $entity->created_at?->format('M d, Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Last Updated</dt>
                            <dd>{{ $entity->updated_at?->format('M d, Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Media Available</dt>
                            <dd>{{ $entity->media_url ? 'Yes' : 'No' }}</dd>
                        </div>
                    </dl>
                    @if($entity->media_url)
                        <div class="mt-4 overflow-hidden rounded-xl border border-white/70 bg-white/60">
                            <img src="{{ $entity->media_url }}" alt="{{ $entity->entity_name }} media"
                                class="h-32 w-full object-cover">
                        </div>
                    @endif
                </div>

                <div
                    class="rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-purple-50 p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-indigo-900">Next Actions</h2>
                    <ul class="mt-4 space-y-3 text-sm text-indigo-800">
                        <li class="flex items-start gap-2">
                            <svg class="mt-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Review linked customers to keep contact information current.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3" />
                            </svg>
                            <span>Schedule periodic audits for suspended or retired records.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0" />
                            </svg>
                            <span>Capture media documents (logo or passport) when available.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
    });
</script>
@endsection