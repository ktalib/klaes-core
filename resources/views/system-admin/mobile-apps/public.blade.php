<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $app->name }} - KLAES</title>
    <meta property="og:title" content="{{ $app->name }}">
    <meta property="og:description" content="{{ $app->short_description ?: 'Download the '.$app->name.' for Android.' }}">
    @if($app->icon_path)
        <meta property="og:image" content="{{ route('mobile-apps.icon', $app) }}">
    @endif
    <link rel="icon" type="image/png" href="{{ asset('assets/logo/klas_core_logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
@php
    $iconUrl = $app->icon_path ? route('mobile-apps.icon', $app).'?v='.optional($app->updated_at)->timestamp : null;
@endphp
<div class="max-w-md mx-auto px-4 py-8">
    <div class="flex items-center justify-center gap-2 mb-6 text-gray-600 text-sm">
        <img src="{{ asset('assets/logo/klas_core_logo.png') }}" alt="" class="h-8 w-8 object-contain">
        <span class="font-semibold">KLAES Mobile Apps</span>
    </div>

    <div class="bg-white rounded-2xl shadow p-6 text-center">
        <div class="h-24 w-24 mx-auto rounded-3xl overflow-hidden bg-gray-100 flex items-center justify-center shadow-sm">
            @if($iconUrl)
                <img src="{{ $iconUrl }}" alt="{{ $app->name }} icon" class="h-full w-full object-cover">
            @else
                <svg class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
            @endif
        </div>

        <h1 class="text-xl font-bold text-gray-900 mt-4">{{ $app->name }}</h1>
        @if($app->short_description)
            <p class="text-gray-600 text-sm mt-1">{{ $app->short_description }}</p>
        @endif

        <div class="flex justify-center flex-wrap gap-2 mt-3 text-xs text-gray-600">
            @if($app->version)<span class="bg-gray-100 rounded-full px-3 py-1">Version {{ $app->version }}</span>@endif
            @if($app->apkSizeForHumans())<span class="bg-gray-100 rounded-full px-3 py-1">{{ $app->apkSizeForHumans() }}</span>@endif
            @if($app->min_android)<span class="bg-gray-100 rounded-full px-3 py-1">{{ $app->min_android }}</span>@endif
        </div>

        @if($app->hasApk())
            <a href="{{ route('mobile-apps.download', $app) }}"
               class="mt-6 block w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-xl">
                Download for Android
            </a>
            @if($app->apk_uploaded_at)
                <p class="text-xs text-gray-400 mt-2">Updated {{ $app->apk_uploaded_at->format('d M Y') }}</p>
            @endif
        @else
            <div class="mt-6 rounded-xl bg-yellow-50 text-yellow-800 text-sm py-3 px-4">
                This app is not available for download yet.
            </div>
        @endif
    </div>

    @if($app->description)
        <div class="bg-white rounded-2xl shadow p-6 mt-4">
            <h2 class="font-semibold text-gray-900 mb-2">About this app</h2>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $app->description }}</p>
        </div>
    @endif

    @if($app->hasApk())
        <div class="bg-white rounded-2xl shadow p-6 mt-4">
            <h2 class="font-semibold text-gray-900 mb-2">How to install</h2>
            <ol class="list-decimal pl-5 text-sm text-gray-700 space-y-1">
                <li>Tap <strong>Download for Android</strong> and wait for the download to finish.</li>
                <li>Open the downloaded file from your notifications or the Downloads folder.</li>
                <li>If asked, allow your browser or file manager to <strong>install unknown apps</strong>.</li>
                <li>Tap <strong>Install</strong>, then open the app and sign in with your KLAES account.</li>
            </ol>
        </div>
    @endif

    <p class="text-center text-xs text-gray-400 mt-6">Kano State Ministry of Land and Physical Planning</p>
</div>
</body>
</html>
