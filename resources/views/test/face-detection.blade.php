{{--
    Face-detection TEST PAGE — isolated from the real profile-picture workflow.

    Nothing here writes to the database, touches a user record, or uploads a file: the
    chosen image is read and analysed entirely in the browser. See
    public/js/face-detection/profile-picture-detector.js for the rules.
--}}
@extends('layouts.app')

@section('page-title')
    {{ $PageTitle }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="p-6">
            <div id="faceTestRoot" class="mx-auto max-w-5xl space-y-4"
                data-model-url="{{ asset('models/face-detection') }}">

                {{-- Isolation notice, so nobody mistakes this for a live feature. --}}
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm font-bold text-amber-900">Test page — not connected to profile picture uploads</p>
                    <p class="mt-1 text-sm text-amber-800">
                        Images are analysed in your browser only. Nothing is uploaded, saved to storage,
                        or written to the database.
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    {{-- Left: input + preview --}}
                    <div class="rounded-xl border border-gray-200 bg-white p-5">
                        <h2 class="text-base font-bold text-gray-900">1. Choose a picture</h2>
                        <p class="mt-1 text-xs text-gray-500">JPG, PNG or WEBP · maximum 8MB</p>

                        <input type="file" id="ftFile" accept="image/jpeg,image/png,image/webp"
                            class="mt-3 w-full rounded-lg border border-gray-300 p-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm">

                        <p id="ftFileNote" class="mt-2 text-xs font-medium text-gray-500"></p>

                        <div id="ftPreviewWrap" class="relative mt-4 hidden">
                            {{-- The canvas sits exactly over the image and carries the boxes. --}}
                            <img id="ftPreview" alt="Selected picture"
                                class="block w-full rounded-lg border border-gray-200 bg-gray-50">
                            <canvas id="ftOverlay" class="pointer-events-none absolute left-0 top-0"></canvas>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" id="ftCheck" disabled
                                class="inline-flex items-center rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-50">
                                Check Picture
                            </button>
                            <button type="button" id="ftReset"
                                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Test another picture
                            </button>
                        </div>
                    </div>

                    {{-- Right: status + result --}}
                    <div class="space-y-4">
                        <div class="rounded-xl border border-gray-200 bg-white p-5">
                            <h2 class="text-base font-bold text-gray-900">2. Result</h2>

                            <div id="ftStatus" class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700">
                                Loading detection model…
                            </div>

                            <div id="ftPanel" class="mt-3 hidden rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <div id="ftRows"></div>
                            </div>

                            <p id="ftThresholds" class="mt-3 text-[11px] leading-relaxed text-gray-400"></p>
                        </div>

                        {{-- The distinction that matters: presence, not identity. --}}
                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                            <p class="text-sm font-semibold text-blue-900">Important limitation</p>
                            <p class="mt-1 text-sm text-blue-800">
                                Face detection only confirms that a human face is present. It does not confirm
                                that the person in the picture is the actual account owner.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- Local copies only — no CDN, so the page keeps working offline and the model
         cannot change under us. --}}
    <script src="{{ asset('js/face-detection/face-api.js') }}?v={{ @filemtime(public_path('js/face-detection/face-api.js')) }}"></script>
    <script src="{{ asset('js/face-detection/profile-picture-detector.js') }}?v={{ @filemtime(public_path('js/face-detection/profile-picture-detector.js')) }}"></script>
    <script src="{{ asset('js/face-detection/test-page.js') }}?v={{ @filemtime(public_path('js/face-detection/test-page.js')) }}"></script>
@endpush
