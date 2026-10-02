<div class="workspace-grid">
    <div class="viewer-card workspace-viewer">
        <div class="viewer-header">
            <div class="viewer-controls">
                <h3 class="viewer-title">Document Viewer</h3>
                <div class="nav-controls">
                    <button id="prev-page" class="nav-btn" type="button" title="Previous document">
                        <i data-lucide="chevron-left" style="width: 1.25rem; height: 1.25rem;"></i>
                    </button>
                    <div class="doc-counter" id="page-counter">1 of {{ $totalPages }}</div>
                    <button id="next-page" class="nav-btn" type="button" title="Next document">
                        <i data-lucide="chevron-right" style="width: 1.25rem; height: 1.25rem;"></i>
                    </button>
                </div>
            </div>
            <div class="viewer-tools" id="viewer-tools">
                <button class="tool-btn" type="button" data-tool="rotate-left" aria-label="Rotate left 90 degrees">
                    <i data-lucide="rotate-ccw"></i>
                </button>
                <button class="tool-btn" type="button" data-tool="rotate-right" aria-label="Rotate right 90 degrees">
                    <i data-lucide="rotate-cw"></i>
                </button>
                <span class="tool-divider"></span>
                <button class="tool-btn" type="button" data-tool="zoom-out" aria-label="Zoom out">
                    <i data-lucide="zoom-out"></i>
                </button>
                <button class="tool-btn" type="button" data-tool="zoom-in" aria-label="Zoom in">
                    <i data-lucide="zoom-in"></i>
                </button>
                <span class="tool-divider"></span>
                <button class="tool-btn" type="button" data-tool="crop" aria-label="Crop image">
                    <i data-lucide="crop"></i>
                </button>
                <button class="tool-btn" type="button" data-tool="pan" aria-label="Toggle drag to reposition">
                    <i data-lucide="move"></i>
                </button>
                <button class="tool-btn" type="button" data-tool="reset" aria-label="Reset view">
                    <i data-lucide="refresh-ccw"></i>
                </button>
            </div>
        </div>
        <div id="document-viewer" class="document-viewer">
            <div class="viewer-canvas">
                <div id="viewer-media-wrapper" class="viewer-media-wrapper">
                    <div id="viewer-media" class="viewer-media"></div>
                </div>
                <div class="viewer-placeholder" id="viewer-placeholder">
                    <i data-lucide="file-text" style="width: 4rem; height: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>Select a document to preview</p>
                </div>
            </div>
        </div>
    </div>

    <div class="folder-card workspace-folder">
        <div class="progress-card progress-card--inline">
            <div class="progress-header">
                <div class="progress-title">Page Classification Progress</div>
                <div class="progress-counter" id="progress-text">0 of {{ $totalPages }} pages completed</div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" id="progress-fill" style="width: 0%"></div>
            </div>
        </div>
        <div class="folder-header">
            <div class="folder-title">
                <i data-lucide="folder-tree"></i>
                Folder ({{ $totalPages }})
            </div>
            <div class="folder-meta">
                @if(!empty($smartHighlights))
                    <span class="folder-meta-label">Top groups:</span>
                    @foreach($smartHighlights as $highlight)
                        <span class="folder-chip" data-folder-chip="{{ $highlight['key'] }}">
                            {{ $highlight['label'] }} ({{ $highlight['count'] }})
                        </span>
                    @endforeach
                @endif
            </div>
        </div>
        <div class="folder-body">
            <div class="folder-group-scroller" data-folder-group-container>
                <div class="folder-group-header">
                    <div class="folder-group-title">
                        <i data-lucide="list-tree"></i>
                        Document Groups
                    </div>
                    <div class="folder-group-actions">
                        <span class="folder-group-count">{{ count($folderGroups) + 1 }} {{ Str::plural('list', count($folderGroups) + 1) }}</span>
                        <button type="button"
                                class="folder-group-toggle"
                                data-folder-group-toggle
                                aria-controls="folder-tree"
                                aria-expanded="false">
                            <span class="folder-group-toggle-text">Show</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                    </div>
                </div>
                <aside class="folder-tree"
                       id="folder-tree"
                       aria-label="Folder navigation"
                       data-folder-tree
                       data-collapsed="true">
                    <button type="button" class="folder-node active" data-folder="all">
                        <span class="folder-node-icon">
                            <i data-lucide="folder-open"></i>
                        </span>
                        <span class="folder-node-label">All Documents</span>
                        <span class="folder-node-count">{{ $totalPages }}</span>
                    </button>
                    @foreach($folderGroups as $group)
                        <button type="button"
                                class="folder-node"
                                data-folder="{{ $group['key'] }}"
                                data-group-type="{{ $group['type'] }}">
                            <span class="folder-node-icon">
                                <i data-lucide="{{ $group['type'] === 'document_type' ? 'layers' : 'files' }}"></i>
                            </span>
                            <span class="folder-node-label">{{ $group['label'] }}</span>
                            <span class="folder-node-count">{{ $group['count'] }}</span>
                        </button>
                    @endforeach
                </aside>
            </div>
            <div class="folder-workspace">
                <div class="folder-toolbar">
                    <div class="folder-sort-controls" role="group" aria-label="Folder sorting">
                        <button type="button" class="folder-sort-btn active" data-sort="custom">
                            <i data-lucide="grip-vertical"></i>
                            Custom
                        </button>
                        <button type="button" class="folder-sort-btn" data-sort="alpha">
                            <i data-lucide="sort-asc"></i>
                            A–Z
                        </button>
                        <button type="button" class="folder-sort-btn" data-sort="recent">
                            <i data-lucide="clock"></i>
                            Recent
                        </button>
                        <button type="button" class="folder-sort-btn" data-sort="type">
                            <i data-lucide="shapes"></i>
                            By Type
                        </button>
                    </div>
                    <div class="folder-status" id="folder-status-indicator">
                        Drag files to reorganize. Changes save automatically.
                    </div>
                </div>
                <div class="folder-grid" id="folder-grid" aria-live="polite">
                    @foreach($allPages as $pageData)
                        @php
                            $aliases = implode(',', $pageData['group_aliases']);
                            $docType = $pageData['metadata']['document_type'] ?? null;
                            $existingPageTyping = $fileIndexing->pagetypings
                                ->where('scanning_id', $pageData['scanning_id'])
                                ->where('page_number', $pageData['page_number'])
                                ->first();
                            $pageTypeLabel = $existingPageTyping?->pageType?->PageType ?? $existingPageTyping?->page_type_others;
                            $pageSubTypeLabel = $existingPageTyping?->pageSubType?->PageSubType ?? $existingPageTyping?->page_subtype_others;
                            $classifiedAt = optional($existingPageTyping?->updated_at)->toDateTimeString();
                        @endphp
                        <div class="document-thumbnail folder-item {{ $pageData['page_index'] === 0 ? 'active' : '' }} {{ $existingPageTyping ? 'classified' : '' }}"
                             data-page-index="{{ $pageData['page_index'] }}"
                             data-page-number="{{ $pageData['page_number'] }}"
                             data-scanning-id="{{ $pageData['scanning_id'] }}"
                             data-file-path="{{ $pageData['file_path'] }}"
                             data-type="{{ $pageData['type'] }}"
                             data-file-url="{{ $pageData['url'] }}"
                             data-display-name="{{ $pageData['display_name'] }}"
                             data-display-order="{{ $pageData['display_order'] }}"
                             data-groups="{{ $aliases }}"
                             data-folder-key="{{ $pageData['group_key'] }}"
                             data-created-at="{{ $pageData['created_at'] }}"
                             data-file-extension="{{ strtoupper($pageData['file_extension'] ?: 'DOC') }}"
                             data-classified="{{ $existingPageTyping ? '1' : '0' }}"
                             data-page-type-name="{{ $pageTypeLabel }}"
                             data-page-subtype-name="{{ $pageSubTypeLabel }}"
                             data-classified-at="{{ $classifiedAt }}"
                             data-page-code="{{ $existingPageTyping?->page_code }}"
                             draggable="true">
                            <div class="folder-item-surface">
                                <div class="folder-item-preview {{ $pageData['type'] === 'pdf' ? 'preview-pdf' : 'preview-image' }}">
                                    <span class="folder-extension-badge folder-tag" title="{{ strtoupper($pageData['file_extension'] ?: 'DOC') }} file">
                                        {{ strtoupper($pageData['file_extension'] ?: 'DOC') }}
                                    </span>
                                    @if($pageData['type'] === 'pdf')
                                        <div class="folder-icon pdf-icon">
                                            <i data-lucide="file-text"></i>
                                        </div>
                                    @else
                                        <img src="{{ $pageData['url'] }}"
                                             alt="{{ $pageData['display_name'] }}"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="folder-icon image-fallback">
                                            <i data-lucide="image"></i>
                                            <span>Preview unavailable</span>
                                        </div>
                                    @endif
                                    <button type="button" class="folder-drag-handle" aria-label="Drag to reorder">
                                        <i data-lucide="grip-vertical"></i>
                                    </button>
                                </div>
                                <div class="folder-item-meta">
                                    <div class="folder-item-name" title="{{ $pageData['display_name'] }}">
                                        {{--  not needed for now {{ $pageData['display_name'] }} --}}
                                    </div>
                                    <div class="folder-item-tags">
                                        @if($docType)
                                            <span class="folder-tag folder-tag-accent">{{ $docType }}</span>
                                        @endif
                                    </div>
                                    <div class="folder-item-reference text-xs text-gray-500" aria-live="polite">
                                        <span class="folder-item-reference-label">Code</span>
                                        <span
                                            class="folder-item-code folder-tag folder-tag-code font-mono"
                                            data-page-code-value="{{ $existingPageTyping?->page_code }}"
                                            title="{{ $existingPageTyping?->page_code ?? 'Not assigned' }}"
                                        >
                                            {{ $existingPageTyping?->page_code ?? '—' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="page-status {{ $existingPageTyping ? 'completed' : '' }}" data-page-index="{{ $pageData['page_index'] }}">
                                @if($existingPageTyping)
                                    <i data-lucide="check"></i>
                                @else
                                    <i data-lucide="circle"></i>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="classification-form workspace-classification">
        <div class="form-header">
            <div class="flex items-center gap-2">
                <i data-lucide="tag" style="width: 1.25rem; height: 1.25rem; color: #6366f1;"></i>
                <h3 class="form-title">Page Classification</h3>
            </div>
            <p class="form-subtitle text-sm text-gray-600 hidden" id="current-page-title">Classify Page 1</p>
        </div>

        <form id="page-typing-form" class="classification-form-content" autocomplete="off">
            @csrf
            <div class="form-body">
                @foreach($allPages as $pageData)
                    @php
                            $existingPageTyping = $fileIndexing->pagetypings->first(function ($typing) use ($pageData) {
                                $matchesPageNumber = (int) $typing->page_number === (int) $pageData['page_number'];

                                if (! $matchesPageNumber) {
                                    return false;
                                }

                                if (! empty($pageData['scanning_id'])) {
                                    return (int) $typing->scanning_id === (int) $pageData['scanning_id'];
                                }

                                return $typing->file_path === $pageData['file_path'];
                            });
                        $existingCoverType = $existingPageTyping?->cover_type_id;
                        $existingPageTypeId = $existingPageTyping?->page_type;
                        $existingPageTypeValue = $existingPageTypeId ?? ($existingPageTyping?->page_type_others ? 'others' : null);
                        $existingPageSubTypeId = $existingPageTyping?->page_subtype;
                        $existingPageSubTypeValue = $existingPageSubTypeId ?? ($existingPageTyping?->page_subtype_others ? 'others' : null);
                        $existingPageTypeName = $existingPageTyping?->pageType?->PageType ?? $existingPageTyping?->page_type_others;
                        $existingPageSubTypeName = $existingPageTyping?->pageSubType?->PageSubType ?? $existingPageTyping?->page_subtype_others;
                        $existingUpdatedAt = optional($existingPageTyping?->updated_at)->toDateTimeString();
                    @endphp
                    <div class="page-form {{ $pageData['page_index'] === 0 ? 'active' : 'hidden' }}"
                         data-page-index="{{ $pageData['page_index'] }}"
                         data-file-path="{{ $pageData['file_path'] }}"
                         data-page-number="{{ $pageData['page_number'] }}"
                         data-scanning-id="{{ $pageData['scanning_id'] }}"
                         data-saved="{{ $existingPageTyping ? '1' : '0' }}"
                         data-existing-cover-type="{{ $existingCoverType }}"
                         data-existing-page-type="{{ $existingPageTypeValue }}"
                         data-existing-page-subtype="{{ $existingPageSubTypeValue }}"
                         data-existing-page-type-others="{{ $existingPageTyping?->page_type_others }}"
                         data-existing-page-subtype-others="{{ $existingPageTyping?->page_subtype_others }}"
                         data-existing-page-type-name="{{ $existingPageTypeName }}"
                         data-existing-page-subtype-name="{{ $existingPageSubTypeName }}"
                         data-existing-updated-at="{{ $existingUpdatedAt }}"
                         data-existing-page-code="{{ $existingPageTyping?->page_code }}"
                         data-existing-serial-number="{{ $existingPageTyping?->serial_number }}">

                        @if($existingPageTyping)
                            <div class="status-indicator status-classified">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="check-circle" style="width: 1rem; height: 1rem; color: #10b981;"></i>
                                    <span class="text-sm font-medium text-green-700">Page classified</span>
                                </div>
                            </div>
                        @endif

                        <div class="multi-select-checkbox hidden">
                            <label class="checkbox-label flex items-center gap-2">
                                <input type="checkbox" class="page-select-checkbox" data-page-index="{{ $pageData['page_index'] }}">
                                <span class="text-sm">Select for batch processing</span>
                            </label>
                        </div>

                        <div class="form-fields space-y-3">
                            <div class="field-group">
                                <label class="field-label">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="layers" style="width: 0.875rem; height: 0.875rem; color: #6b7280;"></i>
                                        <span class="text-xs font-medium text-gray-700">Cover Type</span>
                                    </div>
                                </label>
                                <select class="cover-type-select form-input text-xs py-1.5 px-2"
                                        required
                                        data-page-index="{{ $pageData['page_index'] }}"
                                        data-initial-value="{{ $existingCoverType }}">
                                    <option value="">Select cover type</option>
                                </select>
                                <p class="field-help text-xs text-gray-500 mt-0.5">Front cover: main documents with pagination. Back cover: supporting documents.</p>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="file-text" style="width: 0.875rem; height: 0.875rem; color: #6b7280;"></i>
                                        <span class="text-xs font-medium text-gray-700">Page Type</span>
                                    </div>
                                </label>
                                <select class="page-type-select form-input text-xs py-1.5 px-2"
                                        required
                                        data-page-index="{{ $pageData['page_index'] }}"
                                        data-initial-value="{{ $existingPageTypeValue }}">
                                    <option value="">Select page type</option>
                                </select>
                                <div class="page-type-others-container hidden">
                                    <input type="text"
                                           class="page-type-others-input form-input text-xs mt-1 py-1.5 px-2"
                                           placeholder="Enter custom page type"
                                           maxlength="50"
                                           value="{{ $existingPageTyping?->page_type_others }}"
                                           data-page-index="{{ $pageData['page_index'] }}">
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="folder" style="width: 0.875rem; height: 0.875rem; color: #6b7280;"></i>
                                        <span class="text-xs font-medium text-gray-700">Page Subtype</span>
                                    </div>
                                </label>
                                <select class="page-subtype-select form-input text-xs py-1.5 px-2"
                                        data-page-index="{{ $pageData['page_index'] }}"
                                        data-initial-value="{{ $existingPageSubTypeValue }}">
                                    <option value="">Select page subtype</option>
                                </select>
                                <div class="page-subtype-others-container hidden">
                                    <input type="text"
                                           class="page-subtype-others-input form-input text-xs mt-1 py-1.5 px-2"
                                           placeholder="Enter custom subtype"
                                           maxlength="50"
                                           value="{{ $existingPageTyping?->page_subtype_others }}"
                                           data-page-index="{{ $pageData['page_index'] }}">
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="hash" style="width: 0.875rem; height: 0.875rem; color: #6b7280;"></i>
                                        <span class="text-xs font-medium text-gray-700">Serial Number</span>
                                    </div>
                                </label>
                                <input type="text"
                                       class="serial-input form-input text-xs py-1.5 px-2"
                                       value="{{ $existingPageTyping ? $existingPageTyping->serial_number : '' }}"
                                       required
                                       maxlength="5"
                                       placeholder="e.g., 001"
                                       data-page-index="{{ $pageData['page_index'] }}">
                                <p class="field-help text-xs text-gray-500 mt-0.5">Sequential number for ordering</p>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="code" style="width: 0.875rem; height: 0.875rem; color: #6b7280;"></i>
                                        <span class="text-xs font-medium text-gray-700">Reference Code</span>
                                    </div>
                                </label>
                                <div class="reference-code-display">
                                    <span class="code-preview text-xs font-mono bg-gray-100 px-2 py-1.5 rounded border"
                                          id="page-code-preview-{{ $pageData['page_index'] }}">
                                        {{ $existingPageTyping ? $existingPageTyping->page_code : 'AUTO' }}
                                    </span>
                                    <input type="hidden"
                                           class="page-code-input"
                                           value="{{ $existingPageTyping ? $existingPageTyping->page_code : '' }}"
                                           data-page-index="{{ $pageData['page_index'] }}">
                                </div>
                                <p class="field-help text-xs text-gray-500 mt-0.5">Automatically generated based on page type and serial number</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="form-footer">
                <button type="button" id="save-current-btn" class="btn-save flex items-center gap-2">
                    <i data-lucide="save" style="width: 1rem; height: 1rem;"></i>
                    <span class="text-sm">Save &amp; Next Page</span>
                </button>
            </div>
        </form>
    </div>
</div>
