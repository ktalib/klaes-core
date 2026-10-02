{{-- Draft Status Panel - Collapsible --}}
<div class="bg-blue-50 border border-blue-200 rounded-md overflow-hidden" id="draftStatusWrapper">
    {{-- Header (Always Visible) --}}
    <button type="button" 
            class="w-full px-4 py-3 flex items-center justify-between hover:bg-blue-100 transition-colors"
            onclick="toggleDraftStatus()"
            aria-expanded="false"
            aria-controls="draftStatusContainer">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="text-sm font-semibold text-blue-900">Draft Status</span>
            <span id="draftStatusBadge" class="text-xs px-2 py-0.5 bg-blue-200 text-blue-800 rounded-full">
                {{ number_format($draftMeta['progress_percent'] ?? 0, 0) }}%
            </span>
        </div>
        <svg id="draftStatusChevron" class="w-5 h-5 text-blue-600 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- Collapsible Content --}}
    <div id="draftStatusContainer" class="hidden border-t border-blue-200">
        <div class="px-4 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm text-blue-900 font-semibold">Draft status: <span id="draftStatusText" class="font-bold text-blue-700">Initializing…</span></p>
                <p class="text-xs text-blue-700" id="draftLastSavedText">Last saved: {{ isset($draftMeta['last_saved_at']) && $draftMeta['last_saved_at'] ? \Carbon\Carbon::parse($draftMeta['last_saved_at'])->diffForHumans() : 'Not yet saved' }}</p>
                <p class="text-xs text-blue-700 mt-1" id="draftCollaboratorText">Collaborators: <span id="draftCollaboratorCount">{{ isset($draftMeta['collaborators']) ? count($draftMeta['collaborators']) : 1 }}</span></p>
            </div>
            <div class="flex items-center gap-4 w-full md:w-auto">
                <div class="flex-1 md:flex-none w-full md:w-56 h-2 bg-white border border-blue-200 rounded-full overflow-hidden">
                    <div id="draftProgressBar" class="h-full bg-blue-600 transition-all duration-500" style="width: {{ $draftMeta['progress_percent'] ?? 0 }}%"></div>
                </div>
                <span id="draftProgressValue" class="text-sm font-semibold text-blue-900">{{ number_format($draftMeta['progress_percent'] ?? 0, 0) }}%</span>
                <div class="flex items-center gap-2">
                    <button type="button" id="draftHistoryButton" class="hidden items-center px-3 py-1.5 border border-blue-200 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-100">History</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDraftStatus() {
    const container = document.getElementById('draftStatusContainer');
    const chevron = document.getElementById('draftStatusChevron');
    const button = document.querySelector('[aria-controls="draftStatusContainer"]');
    
    if (container.classList.contains('hidden')) {
        container.classList.remove('hidden');
        chevron.classList.add('rotate-180');
        button.setAttribute('aria-expanded', 'true');
    } else {
        container.classList.add('hidden');
        chevron.classList.remove('rotate-180');
        button.setAttribute('aria-expanded', 'false');
    }
}
</script>
