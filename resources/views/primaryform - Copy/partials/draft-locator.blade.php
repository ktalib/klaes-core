{{-- Draft Locator Panel - Collapsible --}}
<div class="bg-white border border-blue-200 rounded-md overflow-hidden" id="draftLocatorWrapper">
    {{-- Header (Always Visible) --}}
    <button type="button" 
            class="w-full px-4 py-3 flex items-center justify-between hover:bg-blue-50 transition-colors"
            onclick="toggleDraftLocator()"
            aria-expanded="false"
            aria-controls="draftLocatorContainer">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <span class="text-sm font-semibold text-blue-900">Draft Manager</span>
            @if(!empty($draftMeta['np_file_no']))
            <code class="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded font-mono">
                {{ $draftMeta['np_file_no'] }}
            </code>
            @endif
        </div>
        <svg id="draftLocatorChevron" class="w-5 h-5 text-blue-600 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- Collapsible Content --}}
    <div id="draftLocatorContainer" class="hidden border-t border-blue-200">
        <div class="px-4 py-4 space-y-4">
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                <div class="space-y-3 w-full">
                    <div class="flex flex-wrap items-center gap-2" id="draftModeSwitch">
                        <button type="button" id="draftModeFreshButton" data-mode="fresh" class="draft-mode-button px-3 py-1.5 text-xs font-semibold rounded-md transition border border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1 {{ $initialMode === 'fresh' ? 'bg-blue-600 text-white border-blue-600' : 'text-blue-700 hover:bg-blue-50' }}">Fresh Application</button>
                        <button type="button" id="draftModeDraftButton" data-mode="draft" class="draft-mode-button px-3 py-1.5 text-xs font-semibold rounded-md transition border border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1 {{ $initialMode === 'draft' ? 'bg-blue-600 text-white border-blue-600' : 'text-blue-700 hover:bg-blue-50' }}">Continue from Draft</button>
                        <span class="text-xs text-gray-500">Choose how you'd like to begin.</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs uppercase tracking-wide text-gray-500">Current file number:</span>
                        <code id="draftLocatorCurrentId" class="text-xs font-mono px-2 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded break-all">{{ $draftMeta['np_file_no'] ?? 'Not assigned yet' }}</code>
                        <button type="button" id="draftLocatorCopyButton" class="text-xs font-medium text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1">Copy</button>
                    </div>
                </div>
                <form id="draftLocatorForm" class="flex flex-col sm:flex-row sm:items-center gap-2 w-full xl:w-auto">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full">
                        <input type="text" id="draftLocatorInput" name="draft_locator" class="flex-1 border border-blue-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 hidden" placeholder="Enter file number (NPFN)">
                        <button type="submit" class="px-3 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1 hidden">Load Draft</button>
                    </div>
                    <p id="draftLocatorFeedback" class="mt-1 text-xs font-medium hidden"></p>
                </form>
            </div>

            <div id="draftModeDraftPanel" class="rounded-md border border-dashed border-blue-200 bg-blue-50/40 px-3 py-3 {{ $initialMode === 'draft' && !empty($draftList) ? '' : 'hidden' }}">
                <div class="flex flex-col lg:flex-row lg:items-center gap-2">
                    <label for="draftListSelect" class="text-xs font-semibold text-blue-800 uppercase tracking-wide">My drafts</label>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full">
                        <select id="draftListSelect" class="flex-1 border border-blue-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                            <option value="">Select a draft to resume</option>
                            @foreach ($draftList as $draftSummary)
                                @php
                                    $optionValue = $draftSummary['np_file_no'] ?? $draftSummary['draft_id'];
                                    $defaultLabel = $draftSummary['np_file_no'] ?? ('Draft ' . substr($draftSummary['draft_id'], 0, 8));
                                    $optionLabel = $draftSummary['label'] ?? $defaultLabel;
                                @endphp
                                <option value="{{ $optionValue }}" data-draft-id="{{ $draftSummary['draft_id'] }}" {{ !empty($draftSummary['is_current']) ? 'selected' : '' }}>
                                    {{ $optionLabel }}
                                </option>
                            @endforeach
                        </select>
                        <button type="button" id="draftListLoadButton" class="px-3 py-2 bg-white text-blue-700 border border-blue-300 text-sm font-semibold rounded-md hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1">Load Selected</button>
                    </div>
                </div>
                <p id="draftListEmpty" class="mt-2 text-xs text-blue-700 {{ empty($draftList) ? '' : 'hidden' }}">You don't have any saved drafts yet. Start a fresh application to create one.</p>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDraftLocator() {
    const container = document.getElementById('draftLocatorContainer');
    const chevron = document.getElementById('draftLocatorChevron');
    const button = document.querySelector('[aria-controls="draftLocatorContainer"]');
    
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
