@extends('layouts.app')

@section('page-title')
    {{ __('Track File (Archive)') }}
@endsection

@section('content')

<link rel="stylesheet" href="{{ asset('css/track-file-archive.css') }}">
<link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')

    @if($module === 'kangis')
    <div class="bg-gradient-to-r from-yellow-500 via-amber-400 to-yellow-600 px-6 py-3 flex items-center gap-3 shadow-sm">
        <i data-lucide="map" class="h-5 w-5 text-white shrink-0"></i>
        <div class="flex items-center gap-2">
            <span class="text-white font-bold text-sm uppercase tracking-widest">KANGIS</span>
            <span class="text-yellow-100 text-sm">·</span>
            <span class="text-white text-sm font-medium">Digital Archive</span>
            <span class="text-yellow-100 text-sm">·</span>
            <span class="text-yellow-100 text-sm">File Tracker (Archive)</span>
        </div>
    </div>
    @endif

    <!-- Page Header -->
    <header class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Track File (Archive)</h1>
                <p class="text-gray-600 mt-1">Search and track archived files in the system</p>
            </div>
        </div>
    </header>

    {{-- Expose module context to JS --}}
    <meta name="archive-module" content="{{ $module }}" id="archive-module-meta">

    <div class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- First Card (Bigger) -->
            <div class="bg-white rounded-lg shadow-lg p-8 md:col-span-1 md:row-span-2">
                <div class="h-full flex flex-col">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Archive Search</h2>

                    <!-- Search Mode Toggle -->
                    <div class="inline-flex rounded-md border border-gray-300 overflow-hidden mb-4 self-start">
                        <button type="button" id="archive-mode-fileno"
                                class="archive-mode-btn px-4 py-2 text-sm font-medium bg-blue-600 text-white"
                                data-mode="fileno">
                            <i data-lucide="hash" class="h-4 w-4 inline mr-1"></i>
                            File Number
                        </button>
                        <button type="button" id="archive-mode-qr"
                                class="archive-mode-btn px-4 py-2 text-sm font-medium bg-white text-gray-700 hover:bg-gray-50 border-l border-gray-300"
                                data-mode="qr">
                            <i data-lucide="qr-code" class="h-4 w-4 inline mr-1"></i>
                            QR Scan
                        </button>
                    </div>

                    <!-- QR Scan Panel (hardware scanner input) -->
                    <div id="archive-qr-panel" class="hidden mb-4">
                        <label for="archive-qr-input" class="block text-sm font-medium text-gray-700 mb-2">Scan QR Code</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <i data-lucide="scan-line" class="h-5 w-5"></i>
                            </span>
                            <input type="text"
                                   id="archive-qr-input"
                                   autocomplete="off"
                                   placeholder="Scan a file QR code..."
                                   class="block w-full pl-10 pr-3 py-3 border-2 border-dashed border-blue-400 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-bold text-blue-700 bg-blue-50">
                        </div>
                        <div id="archive-qr-status" class="mt-3 text-sm text-gray-600 text-center px-3 py-2 bg-gray-50 border border-gray-200 rounded-md">
                            Waiting for scan — keep this box focused
                        </div>
                    </div>

                    <div class="flex-1">
                        <div class="space-y-4">
                            <div class="relative">
                                <label for="archive-file-no" class="block text-sm font-medium text-gray-700 mb-2">File Number</label>
                                <input type="text"
                                       id="archive-file-no"
                                       name="file_number"
                                       placeholder="e.g. RES-2015-4859"
                                       readonly
                                       class="block w-full px-3 py-2 pr-12 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-bold bg-gray-50">
                                <button type="button"
                                        id="archive-fileno-selector-btn"
                                        class="absolute bottom-0 right-0 flex items-center px-3 py-2 border-l border-gray-300 text-gray-500 hover:text-blue-600 hover:bg-blue-50 transition-colors rounded-r-md"
                                        title="Select from existing file numbers">
                                    <i data-lucide="search" class="h-4 w-4"></i>
                                </button>
                            </div>
                            <div>
                                <label for="archive-file-name" class="block text-sm font-medium text-gray-700 mb-2">File Name</label>
                                <input type="text" 
                                       id="archive-file-name" 
                                       name="file_name" 
                                       placeholder="File name will appear here..." 
                                       disabled
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-500 cursor-not-allowed">
                                <p class="text-xs text-gray-500 mt-1">Auto-filled when a file number is selected</p>
                            </div>
                            <button type="button" onclick="searchArchive()" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                <i data-lucide="search" class="h-4 w-4 inline mr-2"></i>
                                Search Archive
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Second Card (Smaller) - File Details -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex flex-col h-full">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">File Details</h3>
                    <div class="flex-1">
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Tracking ID -->
                            <div class="flex flex-col p-3 bg-blue-50 rounded-lg border border-blue-100">
                                <div class="flex items-center space-x-2 mb-2">
                                    <i data-lucide="hash" class="w-4 h-4 text-blue-600"></i>
                                    <span class="text-xs font-medium text-gray-700">Tracking ID</span>
                                </div>
                                <span id="detail-tracking-id" class="inline-flex items-center justify-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-600 text-white">
                                    Not Selected
                                </span>
                            </div>

                            <!-- Land Use -->
                            <div class="flex flex-col p-3 bg-green-50 rounded-lg border border-green-100">
                                <div class="flex items-center space-x-2 mb-2">
                                    <i data-lucide="map-pin" class="w-4 h-4 text-green-600"></i>
                                    <span class="text-xs font-medium text-gray-700">Land Use</span>
                                </div>
                                <span id="detail-land-use" class="inline-flex items-center justify-center px-2 py-1 rounded-full text-xs font-semibold bg-green-600 text-white">
                                    -
                                </span>
                            </div>

                            <!-- Registry -->
                            <div class="flex flex-col p-3 bg-purple-50 rounded-lg border border-purple-100">
                                <div class="flex items-center space-x-2 mb-2">
                                    <i data-lucide="file-type" class="w-4 h-4 text-purple-600"></i>
                                    <span class="text-xs font-medium text-gray-700">Origin (Registry)</span>
                                </div>
                                <span id="detail-registry" class="inline-flex items-center justify-center px-2 py-1 rounded-full text-xs font-semibold bg-purple-600 text-white">
                                  -
                                </span>
                            </div>

                            <!-- Current Location -->
                            <div class="flex flex-col p-3 bg-cyan-50 rounded-lg border border-cyan-100">
                                <div class="flex items-center space-x-2 mb-2">
                                    <i data-lucide="map-pinned" class="w-4 h-4 text-cyan-600"></i>
                                    <span class="text-xs font-medium text-gray-700">Destination (Location)</span>
                                </div>
                                <span id="detail-current-location" class="inline-flex items-center justify-center px-2 py-1 rounded-full text-xs font-semibold bg-cyan-600 text-white">
                                    -
                                </span>
                            </div>


                           
                            <!-- Rack -->
                            <div class="flex flex-col p-3 bg-orange-50 rounded-lg border border-orange-100">
                                <div class="flex items-center space-x-2 mb-2">
                                    <i data-lucide="layers" class="w-4 h-4 text-orange-600"></i>
                                    <span class="text-xs font-medium text-gray-700">Rack</span>
                                </div>
                                <span id="detail-shelf" class="inline-flex items-center justify-center px-2 py-1 rounded-full text-xs font-semibold bg-orange-600 text-white">
                                    -
                                </span>
                            </div>

                          
   <!-- Shelf -->
                            <div class="flex flex-col p-3 bg-amber-50 rounded-lg border border-amber-100">
                                <div class="flex items-center space-x-2 mb-2">
                                    <i data-lucide="package" class="w-4 h-4 text-amber-600"></i>
                                    <span class="text-xs font-medium text-gray-700">Shelf</span>
                                </div>
                                <span  id="detail-rack"  class="inline-flex items-center justify-center px-2 py-1 rounded-full text-xs font-semibold bg-amber-600 text-white">
                                    -
                                </span>
                            </div>
                            <!-- Full Shelf Rack -->
                            <div class="flex flex-col p-3 bg-indigo-50 rounded-lg border border-indigo-100 col-span-2">
                                <div class="flex items-center space-x-2 mb-2">
                                    <i data-lucide="archive" class="w-4 h-4 text-indigo-600"></i>
                                    <span class="text-xs font-medium text-gray-700">Full Rack Shelf </span>
                                </div>
                                <span id="detail-full-shelf-rack" class="inline-flex items-center justify-center px-2 py-1 rounded-full text-xs font-semibold bg-indigo-600 text-white">
                                    -
                                </span>
                            </div>

                            <!-- Status Indicator -->
                            <div class="flex flex-col p-3 bg-gray-50 rounded-lg border border-gray-200 col-span-2">
                                <div class="flex items-center space-x-2 mb-2">
                                    <i data-lucide="info" class="w-4 h-4 text-gray-600"></i>
                                    <span class="text-xs font-medium text-gray-700">File Log History</span>
                                </div>
                                <span id="detail-status" class="inline-flex items-center justify-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-600 text-white">
                                    Ready
                                </span>
                            </div>

                            <!-- Movement History -->
                            <div class="flex flex-col col-span-2 bg-slate-50 border border-slate-200 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="git-branch" class="w-4 h-4 text-slate-600"></i>
                                        <span class="text-xs font-medium text-gray-700 uppercase tracking-wide">Movement History</span>
                                    </div>
                                    <span id="file-history-last-updated" class="text-xs text-gray-500">Last updated: --</span>
                                </div>
                                <div id="file-history-timeline" class="relative flex flex-col gap-4 border-l border-slate-200 pl-6">
                                    <span id="file-history-empty-state" class="text-sm text-gray-500">Select a file to view movement history.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    @include('admin.footer')
</div>

<!-- Include Global File Number Modal (outside main content) -->
@include('components.global-fileno-modal')

<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/track-file-archive.js') }}"></script>

@endsection
