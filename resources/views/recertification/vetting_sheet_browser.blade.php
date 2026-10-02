@extends('layouts.app')
@section('page-title')
    {{ __('Vetting Sheet Directory Browser') }}
@endsection

@section('content')
<style>
/* Directory Browser Styles */
.directory-browser {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f8f9fa;
    min-height: 100vh;
}

.browser-header {
    background: white;
    border-bottom: 1px solid #e5e7eb;
    padding: 1rem 2rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 1rem 0;
    font-size: 0.875rem;
}

.breadcrumb-item {
    color: #6b7280;
    text-decoration: none;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    transition: all 0.2s;
}

.breadcrumb-item:hover {
    background: #f3f4f6;
    color: #374151;
}

.breadcrumb-separator {
    color: #9ca3af;
}

.directory-listing {
    background: white;
    margin: 1rem 2rem;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.listing-header {
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    padding: 1rem;
    font-weight: 600;
    color: #374151;
}

.file-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f3f4f6;
    transition: background-color 0.2s;
    cursor: pointer;
}

.file-item:hover {
    background: #f9fafb;
}

.file-item:last-child {
    border-bottom: none;
}

.file-icon {
    width: 1.5rem;
    height: 1.5rem;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.file-icon.directory {
    color: #3b82f6;
}

.file-icon.file {
    color: #6b7280;
}

.file-name {
    flex: 1;
    font-weight: 500;
    color: #374151;
}

.file-size {
    color: #6b7280;
    font-size: 0.875rem;
    margin-right: 1rem;
    min-width: 80px;
    text-align: right;
}

.file-modified {
    color: #6b7280;
    font-size: 0.875rem;
    min-width: 150px;
}

.error-message {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
    padding: 1rem;
    margin: 1rem 2rem;
    border-radius: 0.5rem;
}

.empty-directory {
    text-align: center;
    padding: 3rem 1rem;
    color: #6b7280;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #f3f4f6;
    color: #374151;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.2s;
    margin-bottom: 1rem;
}

.back-button:hover {
    background: #e5e7eb;
    color: #111827;
}

.path-display {
    background: #f3f4f6;
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    color: #374151;
    margin: 1rem 0;
    word-break: break-all;
}
</style>

<div class="directory-browser">
    <!-- Header -->
    <div class="browser-header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Vetting Sheet Directory</h1>
                <p class="text-gray-600">Browse and access vetting sheet files</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('recertification.vetting-sheet') }}" class="back-button">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Vetting Sheet
                </a>
            </div>
        </div>

        <!-- Current Path Display -->
        <div class="path-display">
            <strong>Current Path:</strong> {{ $currentPath }}
        </div>

        <!-- Breadcrumb Navigation -->
        @if(count($breadcrumbs) > 0)
        <nav class="breadcrumb">
            @foreach($breadcrumbs as $index => $crumb)
                @if($index > 0)
                    <span class="breadcrumb-separator">/</span>
                @endif
                <a href="?path={{ urlencode($crumb['path']) }}" class="breadcrumb-item">
                    {{ $crumb['name'] }}
                </a>
            @endforeach
        </nav>
        @endif
    </div>

    <!-- Error Message -->
    @if(isset($error))
    <div class="error-message">
        <strong>Error:</strong> {{ $error }}
    </div>
    @endif

    <!-- Directory Listing -->
    <div class="directory-listing">
        <div class="listing-header">
            Index of {{ basename($currentPath) }}
        </div>

        @if(count($items) === 0 && !isset($error))
        <div class="empty-directory">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
            </svg>
            <p>This directory is empty</p>
        </div>
        @else
            <!-- Parent Directory Link -->
            @if($parentPath)
            <div class="file-item" onclick="window.location.href='?path={{ urlencode($parentPath) }}'">
                <svg class="file-icon directory" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                <div class="file-name">..</div>
                <div class="file-size">-</div>
                <div class="file-modified">Parent Directory</div>
            </div>
            @endif

            <!-- Directory and File Items -->
            @foreach($items as $item)
            <div class="file-item" 
                 @if($item['type'] === 'directory')
                     onclick="window.location.href='?path={{ urlencode($item['path']) }}'"
                 @else
                     onclick="window.open('file://{{ $item['path'] }}', '_blank')"
                 @endif>
                
                @if($item['type'] === 'directory')
                    <svg class="file-icon directory" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                    </svg>
                @else
                    <svg class="file-icon file" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                @endif

                <div class="file-name">{{ $item['name'] }}</div>
                
                <div class="file-size">
                    @if($item['type'] === 'directory')
                        -
                    @else
                        {{ number_format($item['size'] / 1024, 1) }} KB
                    @endif
                </div>
                
                <div class="file-modified">
                    {{ date('M j, Y H:i', strtotime($item['modified'])) }}
                </div>
            </div>
            @endforeach
        @endif
    </div>

    <!-- Instructions -->
    <div style="margin: 2rem; padding: 1rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.5rem; color: #1e40af;">
        <h3 style="margin: 0 0 0.5rem 0; font-weight: 600;">Instructions:</h3>
        <ul style="margin: 0; padding-left: 1.5rem;">
            <li>Click on folders to navigate into them</li>
            <li>Click on files to open them in your default application</li>
            <li>Use the breadcrumb navigation to quickly jump to parent directories</li>
            <li>Click ".." to go up one directory level</li>
        </ul>
    </div>
</div>

@endsection