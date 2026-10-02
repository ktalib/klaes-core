@extends('layouts.app')
@section('page-title')
    {{ __($PageTitle ?? 'File Digital Archive') }}
@endsection

@section('content')
    @include('filearchive.assets.style')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        
        @if(isset($moduleTheme) && $moduleTheme)
        <div class="{{ $moduleTheme['bg'] }} px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="{{ $moduleTheme['icon'] }}" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">{{ $moduleTheme['title'] }}</span>
                <span class="{{ $moduleTheme['text_muted'] }} text-sm">&middot;</span>
                <span class="text-white text-sm font-medium">Digital Archive</span>
                <span class="{{ $moduleTheme['text_muted'] }} text-sm">&middot;</span>
                <span class="{{ $moduleTheme['text_muted'] }} text-sm">File Digital Library &ndash; Doc-WARE</span>
            </div>
        </div>
        @endif
        <!-- Dashboard Content -->

        
        <div class="p-6">
            <div class="flex flex-col min-h-screen">
                <!-- Page Header -->
                @include('filearchive.partials.header')

                <div class="flex-1 p-4 sm:p-6 container mx-auto space-y-6">
                    <!-- Stats Cards -->
                    @include('filearchive.partials.stats')

                    <!-- Search Card -->
                    @include('filearchive.partials.search')

                    <!-- Files Display Area -->
                    @include('filearchive.partials.files_grid')
                </div>
            </div>

            <!-- File Details Dialog -->
            @include('filearchive.partials.file_details_modal')

            <!-- Document Viewer Dialog -->
            @include('filearchive.partials.document_viewer_modal')

            @include('components.edms.registry-transfer-modal')
            @include('components.edms.file-type-transfer-modal')
            @include('scan_uploads.partials.reassign_modal')
            {{-- Master LFS Folder browser — the viewer opens it to rebuild a page
                 whose scanned image has gone missing from the EDMS disk. --}}
            @include('scan_uploads.partials.large_format_browser')
            {{-- The file picker both EDMS cards (and the reassignment dialog) open. Its
                 plugin script is already loaded further down; without this markup
                 GlobalFileNoModal.open() finds no #global-fileno-modal and bails. --}}
            @include('components.global-fileno-modal')
        </div>
        <!-- Footer -->
        @include('admin.footer')
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    {{-- Reassignment dialog dependencies. These must come AFTER jQuery: the
         dialog's file picker (GlobalFileNoModal) is a select2 widget, and loading
         scan-reassignment.js first throws "$(...).select2 is not a function". --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <script src="{{ asset('js/scan-reassignment.js') }}"></script>
    
    <!-- Scripts --> 
    @include('filearchive.assets.js')
@endsection 

