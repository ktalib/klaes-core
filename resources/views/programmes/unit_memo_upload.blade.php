@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('KLAES') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include($headerPartial ?? 'admin.header')

    <div class="p-6 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto space-y-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $PageTitle ?? 'Upload Unit ST Memo' }}</h1>
                <p class="text-gray-600">{{ $PageDescription ?? 'Provide the finalized sectional titling memo for this unit.' }}</p>
            </div>

            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3">
                    <strong class="font-semibold">Success:</strong> {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3">
                    <strong class="font-semibold">Error:</strong> {{ session('error') }}
                </div>
            @endif

            <div class="grid gap-6 md:grid-cols-2">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Unit Summary</h2>
                        <p class="text-sm text-gray-500">Key details required for memo verification and owner confirmation.</p>
                    </div>
                    <div class="space-y-3 text-sm text-gray-700">
                        <div class="flex items-center justify-between gap-4">
                            <span class="flex items-center gap-3 text-gray-500">
                                <i data-lucide="file-text" class="w-4 h-4 text-indigo-500"></i>
                                <span>Unit File No</span>
                            </span>
                            <span class="font-medium text-gray-900">{{ $unitApplication->fileno ?? 'N/A' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="flex items-center gap-3 text-gray-500">
                                <i data-lucide="hash" class="w-4 h-4 text-amber-500"></i>
                                <span>NP File No</span>
                            </span>
                            <span class="font-medium text-gray-900">{{ $unitApplication->primary_np_fileno ?? 'N/A' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="flex items-center gap-3 text-gray-500">
                                <i data-lucide="layout-grid" class="w-4 h-4 text-emerald-500"></i>
                                <span>Scheme Number</span>
                            </span>
                            <span class="font-medium text-gray-900">{{ $unitApplication->scheme_no ?? 'N/A' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="flex items-center gap-3 text-gray-500">
                                <i data-lucide="map" class="w-4 h-4 text-sky-500"></i>
                                <span>Land Use</span>
                            </span>
                            <span class="font-medium text-gray-900">{{ $unitApplication->land_use ? ucwords(strtolower($unitApplication->land_use)) : 'N/A' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="flex items-center gap-3 text-gray-500">
                                <i data-lucide="map-pin" class="w-4 h-4 text-rose-500"></i>
                                <span>LGA</span>
                            </span>
                            <span class="font-medium text-gray-900">{{ $unitApplication->property_lga ?? 'N/A' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="flex items-center gap-3 text-gray-500">
                                <i data-lucide="users" class="w-4 h-4 text-purple-500"></i>
                                <span>Unit Owner</span>
                            </span>
                            <span class="font-medium text-gray-900 text-right">{{ $unitApplication->owner_name ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Unit Details</h2>
                        <p class="text-sm text-gray-500">Location identifiers for this individual unit.</p>
                    </div>
                    @php
                        $ownerList = $unitApplication->owner_names_list ?? [];

                        if (!is_array($ownerList) || empty($ownerList)) {
                            $ownerList = [];
                            if (!empty($unitApplication->multiple_owners_names)) {
                                $decodedOwners = json_decode($unitApplication->multiple_owners_names, true);
                                if (is_array($decodedOwners)) {
                                    $ownerList = array_filter(array_map('trim', $decodedOwners));
                                }
                            } elseif (!empty($unitApplication->corporate_name)) {
                                $ownerList = [trim($unitApplication->corporate_name)];
                            } else {
                                $ownerList = array_filter([trim(($unitApplication->applicant_title ?? '') . ' ' . ($unitApplication->first_name ?? '') . ' ' . ($unitApplication->surname ?? ''))]);
                            }
                        }
                    @endphp
                    <div class="space-y-3 text-sm text-gray-700">
                        @if (count($ownerList) > 1)
                            <div class="flex items-start gap-3">
                                <i data-lucide="users" class="w-4 h-4 mt-1 text-purple-500"></i>
                                <div class="flex-1">
                                    <span class="block text-gray-500">Owners</span>
                                    <span class="font-medium text-gray-900">
                                        <ul class="list-disc list-inside space-y-1 text-left">
                                            @foreach ($ownerList as $owner)
                                                <li>{{ $owner }}</li>
                                            @endforeach
                                        </ul>
                                    </span>
                                </div>
                            </div>
                        @elseif (count($ownerList) === 1)
                            <div class="flex items-center justify-between gap-4">
                                <span class="flex items-center gap-3 text-gray-500">
                                    <i data-lucide="user" class="w-4 h-4 text-purple-500"></i>
                                    <span>Owner</span>
                                </span>
                                <span class="font-medium text-gray-900">{{ $ownerList[0] }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between gap-4">
                            <span class="flex items-center gap-3 text-gray-500">
                                <i data-lucide="grid" class="w-4 h-4 text-emerald-500"></i>
                                <span>Block</span>
                            </span>
                            <span class="font-medium text-gray-900">{{ $unitApplication->block_number ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="flex items-center gap-3 text-gray-500">
                                <i data-lucide="layers" class="w-4 h-4 text-amber-500"></i>
                                <span>Floor</span>
                            </span>
                            <span class="font-medium text-gray-900">{{ $unitApplication->floor_number ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="flex items-center gap-3 text-gray-500">
                                <i data-lucide="home" class="w-4 h-4 text-indigo-500"></i>
                                <span>Unit</span>
                            </span>
                            <span class="font-medium text-gray-900">{{ $unitApplication->unit_number ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            @if (!$storageInitialized)
                <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl px-4 py-3 text-sm">
                    Unit memo upload storage isn’t initialized yet. Please run the latest database migrations before uploading.
                </div>
            @endif

            @if (!$primaryMemoExists)
                <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-4 py-3 text-sm">
                    Generate and approve the primary ST memo for this application before uploading a unit memo.
                </div>
            @endif

            @if ($existingUpload)
                <div class="bg-indigo-50 border border-indigo-200 text-indigo-800 rounded-xl px-4 py-3 text-sm flex flex-col gap-1">
                    <span class="font-semibold">Existing Upload</span>
                    <span>Uploaded {{ \Carbon\Carbon::parse($existingUpload->uploaded_at)->format('d M Y, h:ia') }} by {{ optional($existingUploadUser)->name ?? 'Unknown User' }}.</span>
                    <a href="{{ route('programmes.view_unit_st_memo', $unitApplication->id) }}" target="_blank" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">View current memo</a>
                </div>
            @endif

            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6">
                <form method="POST" action="{{ route('programmes.unit_scheme_memo.upload.store', $unitApplication->id) }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    <div>
                        <label for="memo_file" class="block text-sm font-medium text-gray-700">Memo document (PDF or image)</label>
                        <div class="mt-2 flex items-center justify-between gap-3 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6">
                            <div class="space-y-1">
                                <p class="text-sm text-gray-900">Select the final signed memo as a   high-quality image (JPEG, PNG,JPG).</p>
                                <p class="text-xs text-gray-500">Maximum size 100MB. Ensure the content matches the primary memo details before uploading.</p>
                            </div>
                            <label class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg cursor-pointer hover:bg-indigo-700 {{ $canUpload ? '' : 'opacity-60 pointer-events-none' }}">
                                <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                                Choose File
                                <input id="memo_file" type="file" name="memo_file" accept="application/pdf,image/*" class="hidden" @disabled(!$canUpload)>
                            </label>
                        </div>
                        @error('memo_file')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col sm:flex-row sm:justify-end sm:items-center gap-3">
                        <a href="{{ route('programmes.unit_scheme_memo') }}" class="w-full sm:w-auto px-4 py-2 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50">Back to listing</a>
                        <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" @disabled(!$canUpload)>
                            Upload Memo
                        </button>
                    </div>

                    @if (!$canUpload)
                        <p class="text-xs text-gray-400">
                            Upload is disabled until the storage is initialized, the primary memo is generated, and no existing upload is present.
                        </p>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
