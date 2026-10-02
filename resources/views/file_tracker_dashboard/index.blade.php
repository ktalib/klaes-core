@extends('layouts.app')

@section('page-title', $PageTitle ?? 'File Tracker Dashboard')

 @section('content')
    <link rel="stylesheet" href="{{ asset('css/file-tracker-dashboard.css') }}">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="{{ asset('js/file-tracker-dashboard.js') }}" defer></script>
 


    <div class="flex-1 overflow-auto bg-gray-50">
        @include('admin.header')

        @if(($module ?? '') === 'kangis')
        <div class="bg-gradient-to-r from-yellow-500 via-amber-400 to-yellow-600 px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="map" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">KANGIS</span>
                <span class="text-yellow-100 text-sm">·</span>
                <span class="text-white text-sm font-medium">Digital Archive</span>
                <span class="text-yellow-100 text-sm">·</span>
                <span class="text-yellow-100 text-sm">File Tracker Dashboard</span>
            </div>
        </div>
        @endif

        @include('file_tracker_dashboard.partials.dashboard')

        @include('admin.footer')
    </div>
@endsection
