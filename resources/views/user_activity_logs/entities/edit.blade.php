@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Edit Entity') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')

    @php
        $customersCount = $entity->customers()->count();
        $mediaUrl = $entity->media_url;
    @endphp

    <div class="mx-auto max-w-6xl px-6 py-8">
        <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Entity Settings</p>
                <h1 class="mt-2 text-3xl font-bold text-gray-900">{{ $entity->entity_name }}</h1>
                <p class="mt-1 text-sm text-gray-600">Update naming, refresh media, and keep this entity in sync with its linked customers.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('entities.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                <a href="{{ route('entities.show', $entity) }}" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 20h20"/></svg>
                    View Profile
                </a>
                <a href="{{ route('entities.customers', $entity) }}" class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-500">
                    <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/></svg>
                    Linked Customers
                </a>
            </div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <form action="{{ route('entities.update', $entity) }}" method="POST" enctype="multipart/form-data" class="space-y-10">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label for="entity_name" class="block text-sm font-semibold uppercase tracking-wide text-gray-600">Entity Name</label>
                                <input id="entity_name" name="entity_name" type="text" value="{{ old('entity_name', $entity->entity_name) }}" required class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                @error('entity_name')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold uppercase tracking-wide text-gray-600">Entity Type</label>
                                <div class="mt-2 flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                    <svg class="h-4 w-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c1.38 0 2.5-1.12 2.5-2.5S13.38 6 12 6 9.5 7.12 9.5 8.5 10.62 11 12 11z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20v-1a4 4 0 014-4h2a4 4 0 014 4v1"/>
                                    </svg>
                                    <span>{{ $entity->entity_type }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-semibold uppercase tracking-wide text-gray-600">File Number</label>
                                <p class="mt-2 font-mono text-sm text-gray-900">{{ $entity->file_number ?? '—' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold uppercase tracking-wide text-gray-600">Linked Customers</label>
                                <p class="mt-2 text-sm text-gray-900">{{ number_format($customersCount) }}</p>
                            </div>
                        </div>

                        @if($entity->entity_type === 'Individual')
                            <div class="space-y-3">
                                <label for="passport_photo" class="block text-sm font-semibold uppercase tracking-wide text-gray-600">Passport Photo</label>
                                <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_200px]">
                                    <div>
                                        <input id="passport_photo" name="passport_photo" type="file" accept="image/jpeg,image/png" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                        <p class="mt-2 text-sm text-gray-500">JPEG or PNG up to 5 MB. Replacing the file will overwrite the existing passport photo.</p>
                                        @error('passport_photo')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50">
                                        @if($mediaUrl)
                                            <img src="{{ $mediaUrl }}" alt="{{ $entity->entity_name }} passport" class="h-40 w-full object-cover">
                                        @else
                                            <div class="flex h-40 items-center justify-center text-sm text-gray-400">No passport uploaded</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @elseif($entity->entity_type === 'Corporate')
                            <div class="space-y-3">
                                <label for="company_logo" class="block text-sm font-semibold uppercase tracking-wide text-gray-600">Company Logo</label>
                                <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_200px]">
                                    <div>
                                        <input id="company_logo" name="company_logo" type="file" accept="image/jpeg,image/png" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                        <p class="mt-2 text-sm text-gray-500">JPEG or PNG up to 5 MB. Replacing the file will overwrite the existing logo.</p>
                                        @error('company_logo')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50">
                                        @if($mediaUrl)
                                            <img src="{{ $mediaUrl }}" alt="{{ $entity->entity_name }} logo" class="h-40 w-full object-cover">
                                        @else
                                            <div class="flex h-40 items-center justify-center text-sm text-gray-400">No logo uploaded</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
                            <a href="{{ route('entities.show', $entity) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
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
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-indigo-700">Quick Facts</h2>
                    <dl class="mt-4 space-y-3 text-sm text-indigo-900">
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Entity ID</dt>
                            <dd>#{{ $entity->id }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Created At</dt>
                            <dd>{{ $entity->created_at?->format('M d, Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Updated At</dt>
                            <dd>{{ $entity->updated_at?->format('M d, Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Customers</dt>
                            <dd>{{ number_format($customersCount) }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Editing Checklist</h2>
                    <ul class="mt-3 space-y-2 text-sm text-gray-600">
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Confirm the entity name matches supporting documents.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/></svg>
                            <span>Upload fresh media when branding or identification changes.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v2h6v-2a3 3 0 00-3-3 3 3 0 00-3 3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11a3 3 0 100-6 3 3 0 000 6z"/></svg>
                            <span>Coordinate updates with customer records to avoid mismatched data.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>
@endsection
