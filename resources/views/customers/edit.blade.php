@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Edit Customer') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')

    <div class="mx-auto max-w-6xl px-6 py-8">
        <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Edit Customer</p>
                <h1 class="mt-2 text-3xl font-bold text-gray-900">{{ $customer->customer_name }}</h1>
                <p class="mt-1 text-sm text-gray-600">Refresh customer records, align contact details, and manage entity associations in one place.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($customer->entity)
                    <a href="{{ route('entities.show', $customer->entity) }}" class="inline-flex items-center gap-2 rounded-lg border border-purple-200 bg-purple-50 px-4 py-2 text-sm font-semibold text-purple-700 shadow-sm hover:bg-purple-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        View Entity
                    </a>
                @endif
                <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                <a href="{{ route('customers.show', $customer) }}" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 20h20"/></svg>
                    View Profile
                </a>
            </div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <form action="{{ route('customers.update', $customer) }}" method="POST" class="space-y-10">
                        @csrf
                        @method('PUT')

                        @include('customers.partials.form', ['entities' => $entities, 'customer' => $customer])

                        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
                            <a href="{{ route('customers.show', $customer) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-5">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-blue-700">Current Snapshot</h2>
                    <dl class="mt-4 space-y-3 text-sm text-blue-900">
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Status</dt>
                            <dd class="flex items-center gap-2">
                                @php
                                    $statusColors = [
                                        'Active' => 'bg-green-100 text-green-800',
                                        'Pending' => 'bg-amber-100 text-amber-800',
                                        'Suspended' => 'bg-red-100 text-red-800',
                                        'Retired' => 'bg-gray-100 text-gray-800',
                                    ];
                                    $statusClass = $statusColors[$customer->status] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $customer->status }}</span>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">File Number</dt>
                            <dd class="font-mono">{{ $customer->file_number ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Account No.</dt>
                            <dd class="font-mono">{{ $customer->account_no ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Last Updated</dt>
                            <dd>{{ $customer->updated_at?->format('M d, Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Editing Tips</h2>
                    <ul class="mt-3 space-y-2 text-sm text-gray-600">
                        <li>Double-check status changes and provide a retirement reason when applicable.</li>
                        <li>Keep contact channels up to date to support downstream notifications.</li>
                        <li>Use the entity link to keep customer and organization data synchronized.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>
@endsection
