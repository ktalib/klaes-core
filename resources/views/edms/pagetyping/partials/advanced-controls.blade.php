<div class="advanced-controls-section" style="margin-bottom: 1.5rem;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div class="control-card control-card--compact">
            <div class="control-header">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-square" style="width: 1.25rem; height: 1.25rem; color: #6366f1;"></i>
                    <h3 class="control-title">Multi-Select Mode</h3>
                </div>
                <button type="button" class="toggle-multi-select btn-control" data-active="false">
                    <span class="control-text">Enable</span>
                </button>
            </div>
            <p class="control-description">Select multiple pages to apply the same classification settings to all at once</p>
            <div class="multi-select-active" style="display: none; margin-top: 1rem; padding: 1rem; background: #f0f9ff; border-radius: 0.5rem; border: 1px solid #0ea5e9;">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <span class="selected-count text-sm font-medium text-blue-700">0 pages selected</span>
                        <div class="flex gap-1">
                            <button type="button" class="btn-xs btn-outline select-all">Select All</button>
                            <button type="button" class="btn-xs btn-outline clear-selection">Clear</button>
                        </div>
                    </div>
                    <button type="button" class="btn-xs btn-outline exit-multi-select text-red-600 border-red-200">Exit Multi-Select</button>
                </div>
                <p class="text-xs text-blue-600">Click on page thumbnails to select/deselect them, then use the classification form to apply settings to all selected pages.</p>
            </div>
        </div>

        <div class="control-card control-card--compact">
            <div class="control-header">
                <div class="flex items-center gap-2">
                    <i data-lucide="book-open" style="width: 1.25rem; height: 1.25rem; color: #9333ea;"></i>
                    <h3 class="control-title">Booklet Management</h3>
                </div>
                <button type="button" class="start-booklet btn-control" data-active="false">
                    <span class="control-text">Start Booklet</span>
                </button>
            </div>
            <p class="control-description">Group consecutive pages as a single document (e.g., Power of Attorney, Survey Plan)</p>
            <div class="booklet-active" style="display: none; margin-top: 1rem; padding: 1rem; background: #fdf4ff; border-radius: 0.5rem; border: 1px solid #d946ef;">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-purple-700">
                        <strong>Active Booklet:</strong> <span class="booklet-info-text">Pages 1a, 1b, 1c...</span>
                    </span>
                    <button type="button" class="btn-xs btn-outline end-booklet text-red-600 border-red-200">End Booklet</button>
                </div>
                <div class="text-xs text-purple-600">
                    Next page will be numbered: <strong class="next-booklet-number">1a</strong>
                </div>
            </div>
        </div>
    </div>
</div>
