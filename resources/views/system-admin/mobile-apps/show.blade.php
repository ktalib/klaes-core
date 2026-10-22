@extends('layouts.app')

@section('page-title', $app->name)

@section('content')
@php
    $shareUrl = $app->shareUrl();
    $iconUrl = $app->icon_path ? route('mobile-apps.icon', $app).'?v='.optional($app->updated_at)->timestamp : null;
    $qr = qr_data_uri($shareUrl, 220);
    $shareText = $app->name.($app->version ? ' (v'.$app->version.')' : '').' - download and install: '.$shareUrl;
@endphp
<div class="flex-1 overflow-auto bg-gray-50 min-h-screen">
    @include('admin.header')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <p class="text-sm uppercase tracking-wide text-blue-600 font-semibold">System Admin &middot; KLAES Mobile Apps</p>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $app->name }}</h1>
            <p class="text-gray-600 mt-1 text-sm">Upload the APK and app icon, describe the app, then share its download page.</p>

            <div class="flex flex-wrap gap-2 mt-4">
                @foreach($apps as $item)
                    <a href="{{ route('system-admin.mobile-apps.show', $item->slug) }}"
                       class="px-3 py-1.5 rounded-full text-sm border {{ $item->id === $app->id ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                        {{ $item->name }}
                    </a>
                @endforeach
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 text-red-800 px-4 py-3 text-sm">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 text-red-800 px-4 py-3 text-sm">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Details and uploads --}}
            <form method="POST" action="{{ route('system-admin.mobile-apps.update', $app) }}" enctype="multipart/form-data"
                  id="mobileAppForm" class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-5">
                @csrf

                <h2 class="text-lg font-semibold text-gray-900">App details</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">App name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required maxlength="150" value="{{ old('name', $app->name) }}"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Version</label>
                        <input type="text" name="version" maxlength="50" placeholder="e.g. 1.0.3" value="{{ old('version', $app->version) }}"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Android version</label>
                        <input type="text" name="min_android" maxlength="50" placeholder="e.g. Android 8.0 or later" value="{{ old('min_android', $app->min_android) }}"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Short description</label>
                        <input type="text" name="short_description" maxlength="500" placeholder="One line shown under the app name"
                               value="{{ old('short_description', $app->short_description) }}"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">About the app</label>
                        <textarea name="description" rows="5" maxlength="5000" placeholder="What the app is for, who should install it, what's new in this version..."
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('description', $app->description) }}</textarea>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 mt-3">App icon</label>
                        <div class="flex items-center gap-3">
                            <div class="h-16 w-16 rounded-2xl border border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                                <img id="iconPreview" src="{{ $iconUrl }}" alt="" class="h-full w-full object-cover {{ $iconUrl ? '' : 'hidden' }}">
                                <i id="iconPlaceholder" data-lucide="smartphone" class="h-7 w-7 text-slate-400 {{ $iconUrl ? 'hidden' : '' }}"></i>
                            </div>
                            <input type="file" name="icon" id="iconInput" accept="image/png,image/jpeg,image/webp" class="text-sm">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">PNG, JPG or WEBP, up to 2 MB. A square image (512 &times; 512) looks best.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 mt-3">APK file</label>
                        <input type="file" name="apk" id="apkInput" accept=".apk,application/vnd.android.package-archive" class="text-sm">
                        <p class="text-xs text-gray-500 mt-1">
                            @if($app->hasApk())
                                Current: {{ $app->apk_original_name }} ({{ $app->apkSizeForHumans() }}), uploaded {{ optional($app->apk_uploaded_at)->format('d M Y, H:i') }}.
                                Uploading a new file replaces it.
                            @else
                                No APK uploaded yet. Up to 500 MB.
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <span id="savingNote" class="text-sm text-gray-500 hidden">Uploading, please wait&hellip;</span>
                    <button type="submit" id="saveBtn"
                            class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                        <i data-lucide="save" class="h-4 w-4"></i> Save
                    </button>
                </div>
            </form>

            {{-- Download and share --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-5">
                <h2 class="text-lg font-semibold text-gray-900">Download &amp; share</h2>

                @if($app->hasApk())
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div class="text-gray-500 text-xs">Version</div>
                            <div class="font-semibold text-gray-900">{{ $app->version ?: '—' }}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div class="text-gray-500 text-xs">Downloads</div>
                            <div class="font-semibold text-gray-900">{{ number_format($app->download_count) }}</div>
                        </div>
                    </div>

                    <a href="{{ route('mobile-apps.download', $app) }}"
                       class="w-full inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                        <i data-lucide="download" class="h-4 w-4"></i> Download APK
                    </a>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Share link (no login needed)</label>
                        <div class="flex">
                            <input type="text" readonly id="shareUrl" value="{{ $shareUrl }}"
                                   class="flex-1 min-w-0 border border-gray-300 rounded-l-md px-3 py-2 text-xs bg-slate-50">
                            <button type="button" id="copyBtn" class="px-3 border border-l-0 border-gray-300 rounded-r-md text-sm hover:bg-slate-50" title="Copy link">
                                <i data-lucide="copy" class="h-4 w-4"></i>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <a href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener"
                           class="flex flex-col items-center gap-1 rounded-lg border border-slate-200 py-2 hover:bg-slate-50">
                            <i data-lucide="message-circle" class="h-5 w-5 text-green-600"></i> WhatsApp
                        </a>
                        <a href="mailto:?subject={{ rawurlencode($app->name) }}&body={{ rawurlencode($shareText) }}"
                           class="flex flex-col items-center gap-1 rounded-lg border border-slate-200 py-2 hover:bg-slate-50">
                            <i data-lucide="mail" class="h-5 w-5 text-blue-600"></i> Email
                        </a>
                        <button type="button" id="nativeShareBtn"
                                class="flex flex-col items-center gap-1 rounded-lg border border-slate-200 py-2 hover:bg-slate-50">
                            <i data-lucide="share-2" class="h-5 w-5 text-slate-600"></i> More
                        </button>
                    </div>

                    @if($qr)
                        <div class="text-center pt-2 border-t border-slate-100">
                            <img src="{{ $qr }}" alt="QR code for {{ $app->name }}" class="mx-auto h-44 w-44 mt-3">
                            <p class="text-xs text-gray-500 mt-1">Scan with a phone camera to open the download page.</p>
                            <a href="{{ $qr }}" download="{{ $app->slug }}-qr.png" class="text-xs text-blue-600 hover:underline">Save QR code</a>
                        </div>
                    @endif

                    <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="block text-center text-sm text-blue-600 hover:underline">
                        Preview the public download page
                    </a>
                @else
                    <div class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-gray-500">
                        <i data-lucide="upload-cloud" class="h-8 w-8 mx-auto text-slate-400"></i>
                        <p class="mt-2">Upload the APK file to get a download link, share options and a QR code.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var iconInput = document.getElementById('iconInput');
    if (iconInput) {
        iconInput.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (!file) return;
            var img = document.getElementById('iconPreview');
            img.src = URL.createObjectURL(file);
            img.classList.remove('hidden');
            var ph = document.getElementById('iconPlaceholder');
            if (ph) ph.classList.add('hidden');
        });
    }

    var apkInput = document.getElementById('apkInput');
    if (apkInput) {
        apkInput.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (file && !/\.apk$/i.test(file.name)) {
                alert('Please choose an .apk file.');
                this.value = '';
            }
        });
    }

    var form = document.getElementById('mobileAppForm');
    if (form) {
        form.addEventListener('submit', function () {
            var btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.classList.add('opacity-60');
            document.getElementById('savingNote').classList.remove('hidden');
        });
    }

    var copyBtn = document.getElementById('copyBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var input = document.getElementById('shareUrl');
            var done = function () {
                copyBtn.innerHTML = '<span class="text-xs text-green-600">Copied</span>';
                setTimeout(function () {
                    copyBtn.innerHTML = '<i data-lucide="copy" class="h-4 w-4"></i>';
                    if (window.lucide) window.lucide.createIcons();
                }, 1500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(done);
            } else {
                input.select();
                document.execCommand('copy');
                done();
            }
        });
    }

    var nativeBtn = document.getElementById('nativeShareBtn');
    if (nativeBtn) {
        nativeBtn.addEventListener('click', function () {
            var data = { title: @json($app->name), text: @json($shareText), url: @json($shareUrl) };
            if (navigator.share) {
                navigator.share(data).catch(function () {});
            } else {
                document.getElementById('copyBtn').click();
            }
        });
    }
})();
</script>
@endpush
