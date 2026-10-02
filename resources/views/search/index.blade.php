@extends('layouts.app')

@section('page-title')
    {{ __('File Search') }}
@endsection

@section('content')
    @php
        $folderSearchUrl = trim(config('services.ai_folder_search.url'));
        $iframeUrl = $folderSearchUrl;
        if (!empty($iframeUrl)) {
            $iframeUrl .= (str_contains($iframeUrl, '?') ? '&' : '?') . 'embed=1';
        }
    @endphp
    <div class="flex-1 overflow-auto bg-white">
        <div class="p-3 border-b border-gray-200 bg-white sticky top-0 z-10">
            <h1 class="text-xl font-bold text-gray-900">File Search</h1>
        </div>

        <div class="h-screen overflow-hidden">
            @if (!empty($iframeUrl))
                <iframe
                    src="{{ $iframeUrl }}"
                    title="Folder Search"
                    loading="lazy"
                    class="w-full h-full" 
                    style="border: 0;"
                    allow="fullscreen"
                ></iframe>
            @else
                <div class="flex items-center justify-center h-full bg-amber-50 border border-amber-200 m-4 rounded-lg p-4 text-sm text-amber-800">
                    The folder search service URL is not configured. Set <code>AI_FOLDER_SEARCH_URL</code> in your environment to enable the embedded experience.
                </div>
            @endif
        </div>
    </div>
@endsection

