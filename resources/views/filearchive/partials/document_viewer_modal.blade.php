<!-- Document Viewer Modal -->
<div id="document-viewer-dialog" class="dialog-backdrop" style="display: none;" aria-hidden="true" tabindex="-1">
    <div class="modal-container h-screen w-screen p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl h-full flex flex-col">
            <div class="flex-shrink-0 flex items-center justify-between p-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold">Document Viewer</h2>
                <div class="flex items-center gap-2" aria-label="File navigation">
                    <button type="button" id="viewer-previous-file" class="btn btn-outline btn-sm gap-1" title="Preview previous file">
                        <i data-lucide="chevron-left" class="h-4 w-4"></i>
                        <span class="hidden sm:inline">Previous File</span>
                    </button>
                    <span id="viewer-file-position" class="min-w-16 text-center text-xs font-medium text-gray-500">File 0 of 0</span>
                    <button type="button" id="viewer-next-file" class="btn btn-outline btn-sm gap-1" title="Preview next file">
                        <span class="hidden sm:inline">Next File</span>
                        <i data-lucide="chevron-right" class="h-4 w-4"></i>
                    </button>
                </div>
                <button id="close-viewer" class="btn btn-ghost btn-sm">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <div class="flex-1 flex overflow-hidden">
                <div class="w-64 border-r flex flex-col bg-gray-50">
                    <div class="p-3 border-b">
                        <h3 class="text-sm font-medium text-gray-700">Document Pages</h3>
                    </div>
                    {{-- Shown while the operator is picking pages to classify together.
                         Started from the Edit Page Classification dialog, which closes
                         so the whole panel is reachable, and reopens on Apply. --}}
                    <div id="page-select-bar" class="hidden border-b bg-amber-50 p-3 space-y-2">
                        <p class="text-xs font-semibold text-amber-900">
                            Select the pages to classify
                        </p>
                        <p class="text-xs text-amber-700">
                            <span id="page-select-count">0</span> selected
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="button" id="page-select-apply"
                                    class="btn btn-sm bg-amber-600 hover:bg-amber-700 text-white flex-1">Apply</button>
                            <button type="button" id="page-select-cancel"
                                    class="btn btn-sm btn-outline">Cancel</button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-auto">
                        <div id="pages-list" class="p-2 space-y-2"></div>
                    </div>
                </div>

                <div class="flex-1 flex flex-col overflow-hidden">
                    <div class="p-2 border-b flex items-center justify-between bg-gray-50">
                        <div class="flex items-center gap-2">
                            <button class="btn btn-ghost btn-sm" id="prev-page">
                                <i data-lucide="chevron-left" class="h-4 w-4"></i>
                            </button>
                            <span class="text-sm" id="page-indicator">Page 1 of 0</span>
                            <button class="btn btn-ghost btn-sm" id="next-page">
                                <i data-lucide="chevron-right" class="h-4 w-4"></i>
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            {{-- How many pages of this file have lost their image, and a
                                 jump to the next one, so gaps are found without paging
                                 through the whole document. --}}
                            <button type="button" id="viewer-missing-badge"
                                    class="hidden items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700 hover:bg-rose-200"
                                    title="Go to the next page with a missing image">
                                <i data-lucide="image-off" class="h-3.5 w-3.5"></i>
                                <span id="viewer-missing-count"></span>
                            </button>
                            <span id="qc-status-badge" class="hidden items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"></span>
                            <button class="btn btn-ghost btn-sm" id="zoom-out">
                                <i data-lucide="zoom-out" class="h-4 w-4"></i>
                            </button>
                            <span class="text-sm" id="zoom-level">100%</span>
                            <button class="btn btn-ghost btn-sm" id="zoom-in">
                                <i data-lucide="zoom-in" class="h-4 w-4"></i>
                            </button>
                            <button class="btn btn-ghost btn-sm" id="rotate">
                                <i data-lucide="rotate-cw" class="h-4 w-4"></i>
                            </button>
                            <div class="w-px h-5 bg-gray-300 mx-1"></div>

                            {{-- Master Edit — the single switch for every action on this toolbar
                                 that changes something. The actions below it stay out of sight
                                 until it is switched on, so a document being read cannot be
                                 altered by a stray click. Off again for every file opened. --}}
                            <button class="btn btn-sm bg-slate-700 hover:bg-slate-800 text-white flex items-center gap-1"
                                    id="master-edit-toggle" type="button" aria-pressed="false"
                                    aria-controls="master-edit-actions"
                                    title="Turn on edit mode to show the editing actions">
                                <i data-lucide="lock" class="h-4 w-4"></i>
                                <span class="hidden sm:inline">Edit Mode</span>
                            </button>

                            <div id="master-edit-actions" class="hidden items-center gap-2">
                                <button class="btn btn-sm bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-1" id="viewer-move-registry" title="Move this file's documents to another registry">
                                    <i data-lucide="folder-symlink" class="h-4 w-4"></i>
                                    <span class="hidden sm:inline">Move to NR</span>
                                </button>
                                <button class="btn btn-sm bg-violet-600 hover:bg-violet-700 text-white flex items-center gap-1" id="viewer-file-type" title="File this file into a master folder">
                                    <i data-lucide="folder-tree" class="h-4 w-4"></i>
                                    <span class="hidden sm:inline">Master Folder</span>
                                </button>
                                <button class="btn btn-sm bg-amber-600 hover:bg-amber-700 text-white flex items-center gap-1" id="viewer-reassign" title="Unlink these documents and attach them to a different file number">
                                    <i data-lucide="unlink" class="h-4 w-4"></i>
                                    <span class="hidden sm:inline">Reassign</span>
                                </button>
                                <button class="btn btn-sm bg-emerald-600 hover:bg-emerald-700 text-white flex items-center gap-1" id="edit-filetype-toggle" title="Edit page classification (file type)">
                                    <i data-lucide="tag" class="h-4 w-4"></i>
                                    <span class="hidden sm:inline">Edit Type</span>
                                </button>
                                <button class="btn btn-sm bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-1" id="qc-edit-toggle" title="Quality control tools">
                                    <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
                                    <span class="hidden sm:inline">Quality Control</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- QC / Edit toolbar (hidden until "Quality Control" is toggled) -->
                    <div id="qc-toolbar" class="hidden border-b bg-indigo-50/60 px-3 py-2" data-apply-endpoint="{{ route('filearchive.pages.apply-edits', ['pageTyping' => '__ID__']) }}" data-qc-endpoint="{{ route('filearchive.pages.qc-status', ['pageTyping' => '__ID__']) }}" data-delete-endpoint="{{ route('filearchive.pages.delete', ['pageTyping' => '__ID__']) }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold text-indigo-700 uppercase tracking-wide mr-1">Edit</span>
                            <button type="button" class="btn btn-ghost btn-sm border" id="qc-rotate-left" title="Rotate left 90°">
                                <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm border" id="qc-rotate-right" title="Rotate right 90°">
                                <i data-lucide="rotate-cw" class="h-4 w-4"></i>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm border flex items-center gap-1" id="qc-crop-toggle" title="Crop to a selected region">
                                <i data-lucide="crop" class="h-4 w-4"></i><span class="text-xs">Crop</span>
                            </button>
                            <button type="button" class="btn btn-sm bg-amber-500 hover:bg-amber-600 text-white flex items-center gap-1 hidden" id="qc-crop-apply" title="Apply crop">
                                <i data-lucide="check" class="h-4 w-4"></i><span class="text-xs">Apply crop</span>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm border flex items-center gap-1 hidden" id="qc-crop-cancel">
                                <i data-lucide="x" class="h-4 w-4"></i><span class="text-xs">Cancel crop</span>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm border flex items-center gap-1" id="qc-enhance-toggle" title="Brightness / contrast">
                                <i data-lucide="sun" class="h-4 w-4"></i><span class="text-xs">Enhance</span>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm border flex items-center gap-1" id="qc-replace-btn" title="Replace with a new image">
                                <i data-lucide="upload" class="h-4 w-4"></i><span class="text-xs">Replace</span>
                            </button>
                            <input type="file" id="qc-replace-input" accept="image/png,image/jpeg,image/webp" class="hidden">
                            <button type="button" class="btn btn-ghost btn-sm border flex items-center gap-1" id="qc-lf-replace-btn" title="Replace this page from the Archive Scan Folder">
                                <i data-lucide="scan-line" class="h-4 w-4"></i><span class="text-xs">From Folder</span>
                            </button>
                            <button type="button" class="btn btn-sm bg-red-600 hover:bg-red-700 text-white flex items-center gap-1" id="qc-delete-btn" title="Delete this page">
                                <i data-lucide="trash-2" class="h-4 w-4"></i><span class="text-xs">Delete page</span>
                            </button>

                            <div class="flex-1"></div>

                            <button type="button" class="btn btn-sm bg-emerald-600 hover:bg-emerald-700 text-white flex items-center gap-1 disabled:opacity-50" id="qc-save-btn" disabled>
                                <i data-lucide="save" class="h-4 w-4"></i><span class="text-xs">Save changes</span>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm border flex items-center gap-1" id="qc-cancel-btn">
                                <i data-lucide="x" class="h-4 w-4"></i><span class="text-xs">Cancel</span>
                            </button>
                        </div>

                        <!-- Enhance sliders (hidden until Enhance is toggled) -->
                        <div id="qc-enhance-panel" class="hidden mt-2 flex flex-wrap items-center gap-4 bg-white border rounded-md p-2">
                            <label class="flex items-center gap-2 text-xs text-gray-600">
                                <i data-lucide="sun" class="h-3.5 w-3.5"></i> Brightness
                                <input type="range" id="qc-brightness" min="50" max="150" value="100" class="w-32">
                                <span id="qc-brightness-val" class="w-10 text-right font-mono">100%</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-gray-600">
                                <i data-lucide="contrast" class="h-3.5 w-3.5"></i> Contrast
                                <input type="range" id="qc-contrast" min="50" max="150" value="100" class="w-32">
                                <span id="qc-contrast-val" class="w-10 text-right font-mono">100%</span>
                            </label>
                            <button type="button" class="btn btn-ghost btn-xs border text-xs" id="qc-enhance-reset">Reset</button>
                        </div>

                        <!-- QC review decision -->
                        <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-indigo-100 pt-2">
                            <span class="text-xs font-semibold text-indigo-700 uppercase tracking-wide mr-1">Review</span>
                            <button type="button" class="btn btn-sm bg-green-600 hover:bg-green-700 text-white flex items-center gap-1" id="qc-pass-btn">
                                <i data-lucide="check-circle" class="h-4 w-4"></i><span class="text-xs">Pass</span>
                            </button>
                            <button type="button" class="btn btn-sm bg-red-600 hover:bg-red-700 text-white flex items-center gap-1" id="qc-fail-btn">
                                <i data-lucide="x-circle" class="h-4 w-4"></i><span class="text-xs">Fail</span>
                            </button>
                            <input type="text" id="qc-note" placeholder="Issue note (optional)" class="flex-1 min-w-[160px] text-sm border rounded-md px-2 py-1">
                            <span id="qc-review-meta" class="text-xs text-gray-500"></span>
                        </div>
                    </div>

                    <div class="flex-1 overflow-auto flex items-center justify-center bg-gray-100 p-4">
                        <div id="current-page-content" class="bg-white shadow-md rounded-md max-w-[900px] w-full mx-auto transition-transform" style="transform: scale(1) rotate(0deg);">
                            <div class="relative h-full w-full flex items-center justify-center">
                                <img src="" alt="Document page" id="document-image" class="w-full max-h-[85vh] object-contain hidden">
                                <canvas id="qc-canvas" class="max-w-full max-h-[85vh] object-contain hidden"></canvas>
                                <iframe id="document-pdf" src="" title="Document page" class="w-full h-[85vh] hidden" frameborder="0"></iframe>
                                <div id="document-placeholder" class="flex flex-col items-center justify-center p-6 text-center text-sm text-gray-500 gap-3" style="display: none;">
                                    <i data-lucide="file" class="h-10 w-10 text-gray-300"></i>
                                    <p>No preview available for this page.</p>
                                    {{-- The page row is intact; only its scanned image is gone.
                                         Offer the rebuild right where the gap is noticed. --}}
                                    <div id="placeholder-missing-note" class="hidden flex-col items-center gap-2">
                                        <p class="max-w-sm text-xs text-gray-500">
                                            This page is recorded in the archive but its scanned image is missing
                                            from the EDMS storage. Restore it from the Archive Scan Folder.
                                        </p>
                                        <button type="button" id="placeholder-lf-replace"
                                                class="btn btn-sm bg-violet-600 hover:bg-violet-700 text-white flex items-center gap-1">
                                            <i data-lucide="scan-line" class="h-4 w-4"></i>
                                            <span>Restore Page Image</span>
                                        </button>
                                        {{-- Replacing is an edit, so it stays behind the master
                                             switch like every other action that changes a file. --}}
                                        <p id="placeholder-lf-locked" class="hidden text-xs font-medium text-gray-400">
                                            Turn on Edit Mode to replace this page.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-2 border-t bg-gray-50">
                        <div class="text-sm text-gray-500">
                            <div class="flex items-center justify-between">
                                <div id="page-info">
                                    <span class="font-medium">Select a page</span>
                                </div>
                                <div class="text-right space-y-0.5">
                                    <div class="font-semibold text-xs text-gray-900" id="viewer-file-title">-</div>
                                    <div class="font-mono text-xs font-semibold text-blue-700" id="viewer-file-number">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit File Type (Page Classification) Modal -->
<div id="edit-filetype-dialog" class="dialog-backdrop" style="display: none;" aria-hidden="true" tabindex="-1"
     data-typing-data-url="{{ route('pagetyping.api.typing-data') }}"
     data-save-url="{{ route('filearchive.pages.classification', ['pageTyping' => '__ID__']) }}">
    <div class="modal-container flex items-center justify-center p-4 min-h-screen">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md flex flex-col max-h-[92vh]">
            <div class="flex-shrink-0 flex items-center justify-between p-4 border-b bg-gray-50 rounded-t-lg">
                <div class="flex items-center gap-2">
                    <i data-lucide="tag" class="h-5 w-5 text-emerald-600"></i>
                    <h2 class="text-base font-semibold">Edit Page Classification</h2>
                </div>
                <button id="edit-filetype-close" class="btn btn-ghost btn-sm" type="button">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <div id="edit-filetype-scope" class="hidden mx-4 mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                Applying to <strong id="edit-filetype-scope-count">0</strong> selected page(s).
            </div>
            <form id="edit-filetype-form" class="p-4 space-y-4 overflow-y-auto" autocomplete="off">
                <div class="text-xs text-gray-500">
                    Editing <span class="font-mono font-semibold text-blue-700" id="eft-page-label">page</span>
                    of <span class="font-semibold" id="eft-file-number">-</span>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Registry</label>
                    <select id="eft-registry" class="form-input w-full text-sm py-1.5 px-2 border rounded-md">
                        <option value="Lands Registry">Lands Registry</option>
                        <option value="KANGIS">KANGIS</option>
                        <option value="SLTR">SLTR</option>
                        <option value="DCIV">DCIV</option>
                        <option value="Cadastral">Cadastral</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Cover Type</label>
                    <select id="eft-cover-type" required class="form-input w-full text-sm py-1.5 px-2 border rounded-md">
                        <option value="">Select cover type</option>
                    </select>
                    <p class="text-xs text-amber-600 mt-1">Front Cover: main documents with pagination | Back Cover: supporting documents without pagination</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Page Type</label>
                    <select id="eft-page-type" required class="form-input w-full text-sm py-1.5 px-2 border rounded-md">
                        <option value="">Select page type</option>
                    </select>
                    <input type="text" id="eft-page-type-other" maxlength="50" placeholder="Enter custom page type"
                           class="form-input w-full text-sm mt-1 py-1.5 px-2 border rounded-md hidden">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Page Subtype</label>
                    <select id="eft-page-subtype" class="form-input w-full text-sm py-1.5 px-2 border rounded-md">
                        <option value="">Select page subtype</option>
                    </select>
                    <input type="text" id="eft-page-subtype-other" maxlength="50" placeholder="Enter custom subtype"
                           class="form-input w-full text-sm mt-1 py-1.5 px-2 border rounded-md hidden">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Serial Number</label>
                    <input type="text" id="eft-serial" maxlength="5" required placeholder="e.g., 0"
                           class="form-input w-full text-sm py-1.5 px-2 border rounded-md">
                    <p class="text-xs text-gray-500 mt-1">Auto-calculated: <span id="eft-serial-auto">0</span></p>
                </div>

                <div class="border rounded-md p-3 bg-gray-50">
                    <h4 class="text-sm font-semibold mb-2">Page Code Preview</h4>
                    <div class="flex flex-wrap items-center gap-2">
                        <span id="eft-code-preview" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-semibold bg-blue-100 text-blue-800">—</span>
                        <span id="eft-definition-preview" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-semibold bg-green-100 text-green-800">—</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-2" id="eft-format-note">Format: CoverType-PageType-SubType-SerialNo</p>
                    <p class="text-xs text-gray-500" id="eft-definition-note">Definition Code: AutoPosition-PageCode</p>
                </div>
            </form>

            <div class="flex-shrink-0 flex items-center justify-between gap-2 p-4 border-t bg-gray-50 rounded-b-lg">
                {{-- Closes this dialog so the Document Pages panel is reachable, then
                     reopens it on Apply with whatever was already entered. --}}
                <button type="button" id="edit-filetype-select-pages" class="btn btn-outline btn-sm flex items-center gap-1">
                    <i data-lucide="list-checks" class="h-4 w-4"></i>
                    <span>Select pages</span>
                </button>
                <div class="flex items-center gap-2">
                <button type="button" id="edit-filetype-cancel" class="btn btn-outline btn-sm">Cancel</button>
                <button type="button" id="edit-filetype-save" class="btn btn-sm bg-emerald-600 hover:bg-emerald-700 text-white flex items-center gap-1">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    <span>Save Classification</span>
                </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let documentPages = [];
let currentFileMeta = null;
let currentPageIndex = 0;
let zoomLevel = 100;
let rotation = 0;

const thumbnailPlaceholder = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none"><rect width="64" height="64" rx="8" fill="#F3F4F6"/><path d="M20 18h24v28H20z" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M24 26h16M24 32h10" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round"/></svg>');

function clearDocumentViewerData() {
    if (typeof exitQcEditMode === 'function') exitQcEditMode();
    documentPages = [];
    currentFileMeta = null;
    currentPageIndex = 0;
    zoomLevel = 100;
    rotation = 0;
    // Edit mode never carries over from one file to the next: whoever opens a
    // document has to ask for editing again before anything can be changed.
    if (typeof setMasterEditMode === 'function') setMasterEditMode(false);
    // Nor does the previous file's missing-image count.
    if (typeof updateMissingPagesBadge === 'function') updateMissingPagesBadge();
}

window.clearDocumentViewerData = clearDocumentViewerData;

/* ============================================================
 * Master Edit — one switch over every editing action in the viewer.
 * Move to NR, Master Folder, Reassign, Edit Type and Quality Control
 * are hidden until it is on, so a document that is only being read
 * cannot be modified by a mis-click. Switching it back off closes
 * whatever editor was open rather than leaving it stranded on screen.
 * ============================================================ */
let masterEditMode = false;

function setMasterEditMode(on) {
    masterEditMode = !!on;

    const actions = document.getElementById('master-edit-actions');
    if (actions) {
        actions.classList.toggle('hidden', !masterEditMode);
        actions.classList.toggle('flex', masterEditMode);
    }

    const toggle = document.getElementById('master-edit-toggle');
    if (toggle) {
        toggle.setAttribute('aria-pressed', masterEditMode ? 'true' : 'false');
        // Off is slate — a solid button that belongs to the action row but reads
        // as shut. On is red on purpose: it is the state in which the document
        // can be changed. Text stays white either way.
        toggle.classList.add('text-white');
        toggle.classList.toggle('bg-slate-700', !masterEditMode);
        toggle.classList.toggle('hover:bg-slate-800', !masterEditMode);
        toggle.classList.toggle('bg-rose-600', masterEditMode);
        toggle.classList.toggle('hover:bg-rose-700', masterEditMode);
        toggle.title = masterEditMode
            ? 'Editing is on — click to lock this document again'
            : 'Turn on edit mode to show the editing actions';

        const icon = toggle.querySelector('i');
        if (icon) icon.setAttribute('data-lucide', masterEditMode ? 'unlock' : 'lock');
        const label = toggle.querySelector('span');
        if (label) label.textContent = masterEditMode ? 'Editing On' : 'Edit Mode';
    }

    // An editor left open would outlive the switch that revealed it.
    if (!masterEditMode) {
        if (typeof qcEditMode !== 'undefined' && qcEditMode && typeof exitQcEditMode === 'function') {
            exitQcEditMode();
        }
        if (typeof window.closeEditFileTypeDialog === 'function') {
            window.closeEditFileTypeDialog();
        }
    }

    // The replace button on the empty-preview panel follows the same switch.
    if (typeof showMissingImageNote === 'function') {
        const page = documentPages[currentPageIndex];
        showMissingImageNote(!!page && isPageBroken(page));
    }

    if (window.lucide) lucide.createIcons();
}

window.setMasterEditMode = setMasterEditMode;

document.addEventListener('DOMContentLoaded', function () {
    const openFileAction = function (dialog) {
        return function () {
            if (!currentFileMeta || !currentFileMeta.id) {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No file open',
                        text: 'Open a file in the viewer first.',
                        timer: 2500
                    });
                }
                return;
            }

            dialog.open(currentFileMeta.id, currentFileMeta.file_number, function () {
                document.getElementById('close-viewer')?.click();
                window.location.reload();
            });
        };
    };

    document.getElementById('viewer-move-registry')
        ?.addEventListener('click', openFileAction(window.EdmsRegistryTransfer));

    document.getElementById('viewer-file-type')
        ?.addEventListener('click', openFileAction(window.EdmsFileType));

    // Reassignment is keyed on scan ids, not the file, so it cannot use openFileAction().
    document.getElementById('viewer-reassign')?.addEventListener('click', function () {
        if (!currentFileMeta || !currentFileMeta.id) {
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: 'No file open', text: 'Open a file in the viewer first.', timer: 2500 });
            }
            return;
        }

        openArchiveReassign(currentFileMeta.id, currentFileMeta.file_number);
    });
});

/**
 * Unlink a file's scans and attach them to a different file number.
 *
 * Shared by the viewer toolbar and the file cards. The reassignment dialog works
 * on scan ids, so the file's scans are fetched first.
 */
async function openArchiveReassign(fileIndexingId, fileNumber) {
    if (!window.scanReassignmentManager) {
        if (window.Swal) {
            Swal.fire({ icon: 'error', title: 'Reassignment unavailable', text: 'The reassignment dialog did not load on this page.' });
        }
        return;
    }

    try {
        const response = await fetch(`/scan-uploads/file-scans?file_indexing_id=${encodeURIComponent(fileIndexingId)}`, {
            headers: { 'Accept': 'application/json' }
        });
        const payload = await response.json();
        const documents = payload?.data?.documents || [];

        if (!documents.length) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'info',
                    title: 'Nothing to reassign',
                    text: `${fileNumber || 'This file'} has no scanned documents to move.`
                });
            }
            return;
        }

        window.scanReassignmentManager.openModal(documents.map(doc => doc.id), documents);
    } catch (error) {
        if (window.Swal) {
            Swal.fire({ icon: 'error', title: 'Could not load the documents', text: error.message });
        }
    }
}

window.openArchiveReassign = openArchiveReassign;

function loadDocumentPages(fileMeta, pages) {
    currentFileMeta = fileMeta || null;
    documentPages = Array.isArray(pages) ? pages : [];
    currentPageIndex = 0;
    zoomLevel = 100;
    rotation = 0;

    const fileTitleEl = document.getElementById('viewer-file-title');
    const fileNumberEl = document.getElementById('viewer-file-number');
    if (fileTitleEl) {
        fileTitleEl.textContent = currentFileMeta && currentFileMeta.file_title ? currentFileMeta.file_title : '-';
    }
    if (fileNumberEl) {
        fileNumberEl.textContent = currentFileMeta && currentFileMeta.file_number ? currentFileMeta.file_number : '-';
    }

    renderPagesList();

    if (documentPages.length > 0) {
        selectPage(0);
    } else {
        showEmptyState();
    }
}

/**
 * Multi-page selection for the Document Pages panel.
 *
 * The Edit Page Classification dialog covers the panel, so picking several pages
 * cannot happen while it is open. The flow is therefore: dialog remembers what
 * you typed and closes -> you tick pages here -> Apply reopens the dialog with
 * your entries intact -> Save applies them to every ticked page.
 */
const pageSelection = {
    active: false,
    selected: new Set(),
    onApply: null,
};

function enterPageSelectionMode(onApply) {
    pageSelection.active = true;
    pageSelection.onApply = typeof onApply === 'function' ? onApply : null;

    // Start from the page that was being edited, so a single-page edit that turns
    // into a multi-page one does not lose the page it began with.
    pageSelection.selected = new Set([currentPageIndex]);

    document.getElementById('page-select-bar')?.classList.remove('hidden');
    renderPagesList();
    updatePageSelectionCount();
}

function exitPageSelectionMode() {
    pageSelection.active = false;
    pageSelection.onApply = null;
    pageSelection.selected.clear();
    document.getElementById('page-select-bar')?.classList.add('hidden');
    renderPagesList();
}

function togglePageSelection(index) {
    if (pageSelection.selected.has(index)) {
        pageSelection.selected.delete(index);
    } else {
        pageSelection.selected.add(index);
    }
    renderPagesList();
    updatePageSelectionCount();
}

function updatePageSelectionCount() {
    const el = document.getElementById('page-select-count');
    if (el) el.textContent = String(pageSelection.selected.size);
}

/** Indexes the classification should be applied to, in page order. */
function selectedPageIndexes() {
    return Array.from(pageSelection.selected).sort((a, b) => a - b);
}

function renderPagesList() {
    const pagesList = document.getElementById('pages-list');
    if (!pagesList) {
        return;
    }

    pagesList.innerHTML = '';

    if (!documentPages.length) {
        pagesList.innerHTML = '<div class="p-4 text-center text-sm text-gray-500">No pages available.</div>';
        updatePageIndicator();
        return;
    }

    documentPages.forEach((page, index) => {
        const pageItem = document.createElement('div');
        pageItem.className = 'p-2 border rounded-md cursor-pointer hover:bg-gray-50 page-item';
        if (index === 0) {
            if (page.cover_type && page.cover_type.code === 'BC') {
                pageItem.classList.add('bg-green-50', 'border-green-200');
            } else {
                pageItem.classList.add('bg-blue-50', 'border-blue-200');
            }
        }

        const thumbnail = page.thumbnail_url || thumbnailPlaceholder;
        const primaryLabel = page.page_code || (page.page_type ? page.page_type.name : 'Untitled');
        // A page whose image is gone still has to be findable in the list.
        const missingBadge = isPageBroken(page)
            ? '<span class="ml-1 inline-flex items-center gap-0.5 rounded bg-rose-100 px-1 py-px text-[10px] font-semibold text-rose-700" title="Scanned image missing">'
                + '<i data-lucide="image-off" class="h-3 w-3"></i>Missing</span>'
            : '';
        const secondaryParts = [];
        if (page.page_type && page.page_type.name) {
            secondaryParts.push(`<span class="text-emerald-600">${page.page_type.name}</span>`);
        }
        if (page.page_subtype && page.page_subtype.name) {
            secondaryParts.push(`<span class="text-emerald-600">${page.page_subtype.name}</span>`);
        }
        if (page.typed_by && page.typed_by.name) {
            secondaryParts.push(`<span class="text-orange-500">Typed by: ${page.typed_by.name}</span>`);
        }
        const secondaryLabel = secondaryParts.join(' · ');

        const isTicked = pageSelection.active && pageSelection.selected.has(index);
        if (isTicked) {
            pageItem.classList.add('ring-2', 'ring-amber-500', 'bg-amber-50');
        }

        pageItem.innerHTML = `
            <div class="flex items-center gap-2">
                ${pageSelection.active ? `
                    <span class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-sm border-2 ${isTicked ? 'border-amber-600 bg-amber-600 text-white' : 'border-gray-400 bg-white'}">
                        ${isTicked ? '<i data-lucide="check" class="h-3 w-3"></i>' : ''}
                    </span>` : ''}
                <div class="w-10 h-10 bg-gray-100 rounded overflow-hidden flex items-center justify-center">
                    <img src="${thumbnail}" alt="Page ${index + 1}" class="w-full h-full object-cover" onerror="this.src='${thumbnailPlaceholder}'" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold truncate flex items-center gap-1 text-indigo-700">
                        ${primaryLabel}${missingBadge}
                    </div>
                    <div class="text-xs truncate">
                        ${secondaryLabel || '<span class="text-gray-400">Not typed</span>'}
                    </div>
                </div>
            </div>
        `;

        // While selecting, a click ticks the page instead of navigating to it —
        // navigating would swap the viewer image out from under the operator.
        pageItem.addEventListener('click', () => {
            if (pageSelection.active) {
                togglePageSelection(index);
            } else {
                selectPage(index);
            }
        });
        pagesList.appendChild(pageItem);
    });

    if (window.lucide) lucide.createIcons();
    updatePageIndicator();
    updateMissingPagesBadge();
}

function selectPage(index) {
    if (index < 0 || index >= documentPages.length) {
        return;
    }

    currentPageIndex = index;

    const pageItems = document.querySelectorAll('.page-item');
    pageItems.forEach((item, i) => {
        item.classList.remove('bg-blue-50', 'border-blue-200', 'bg-green-50', 'border-green-200');
        const page = documentPages[i];
        if (i === index) {
            if (page.cover_type && page.cover_type.code === 'BC') {
                item.classList.add('bg-green-50', 'border-green-200');
            } else {
                item.classList.add('bg-blue-50', 'border-blue-200');
            }
        }
    });

    const page = documentPages[index];
    const documentImage = document.getElementById('document-image');
    const documentPdf = document.getElementById('document-pdf');
    const documentPlaceholder = document.getElementById('document-placeholder');

    if (documentImage) {
        documentImage.classList.add('hidden');
        documentImage.removeAttribute('src');
    }
    if (documentPdf) {
        documentPdf.classList.add('hidden');
        documentPdf.src = '';
    }
    if (documentPlaceholder) {
        documentPlaceholder.style.display = 'none';
    }
    showMissingImageNote(isPageBroken(page));

    if (page.media_type === 'image' && page.viewer_url) {
        if (documentImage) {
            documentImage.src = page.viewer_url;
            documentImage.classList.remove('hidden');
            documentImage.onerror = function() {
                if (documentPlaceholder) {
                    documentPlaceholder.style.display = 'flex';
                }
                this.classList.add('hidden');
                // The path resolved but the file will not decode — just as
                // unusable as a missing one, so offer the same repair.
                page.is_broken = true;
                showMissingImageNote(true);
                updateMissingPagesBadge();
            };
        }
    } else if (page.viewer_url) {
        if (documentPdf) {
            let viewerUrl = page.viewer_url;
            if (page.media_type === 'pdf') {
                const baseUrl = viewerUrl.split('#')[0];
                const hashParts = [];
                hashParts.push('toolbar=0');
                const pdfTargetPage = page.pdf_page_number || page.page_number;
                if (pdfTargetPage) {
                    hashParts.push('page=' + pdfTargetPage);
                }
                viewerUrl = baseUrl + '#' + hashParts.join('&');
            }

            if (documentPdf.src !== viewerUrl) {
                documentPdf.src = '';
            }
            documentPdf.src = viewerUrl;
            documentPdf.classList.remove('hidden');
        }
    } else if (documentPlaceholder) {
        documentPlaceholder.style.display = 'flex';
    }

    const pageInfo = document.getElementById('page-info');
    if (pageInfo) {
        const codeLabel = page.page_code || (page.page_type ? page.page_type.name : 'Untitled');
        const detailParts = [];
        if (page.page_type && page.page_type.name) {
            detailParts.push(page.page_type.name);
        }
        if (page.page_subtype && page.page_subtype.name) {
            detailParts.push(page.page_subtype.name);
        }

        pageInfo.innerHTML = `
            <span class="font-semibold text-indigo-700">${codeLabel}</span>
            ${detailParts.length ? `<span> - </span><span class="text-emerald-700 font-medium">${detailParts.join('</span> · <span class="text-emerald-700 font-medium">')}</span>` : ''}
            ${page.typed_by && page.typed_by.name ? `<span> · </span><span class="text-orange-600 font-medium">Typed by: ${page.typed_by.name}</span>` : ''}
        `;
    }

    updatePageIndicator();
    updateTransform();
    refreshQcUi();
}

function showEmptyState(message = 'No pages available for this document.') {
    const documentImage = document.getElementById('document-image');
    const documentPdf = document.getElementById('document-pdf');
    const documentPlaceholder = document.getElementById('document-placeholder');
    const pageInfo = document.getElementById('page-info');

    if (documentImage) {
        documentImage.classList.add('hidden');
        documentImage.removeAttribute('src');
    }
    if (documentPdf) {
        documentPdf.classList.add('hidden');
        documentPdf.src = '';
    }
    if (documentPlaceholder) {
        documentPlaceholder.style.display = 'flex';
        const placeholderText = documentPlaceholder.querySelector('p');
        if (placeholderText) {
            placeholderText.textContent = message;
        }
    }
    // Nothing is selected, so there is no page to offer a replacement for.
    showMissingImageNote(false);
    if (pageInfo) {
        pageInfo.innerHTML = `<span class="font-medium text-gray-500">${message}</span>`;
    }

    currentPageIndex = 0;
    updatePageIndicator();

    const zoomLabel = document.getElementById('zoom-level');
    if (zoomLabel) {
        zoomLabel.textContent = '100%';
    }
}

function updatePageIndicator() {
    const pageIndicator = document.getElementById('page-indicator');
    if (!pageIndicator) {
        return;
    }

    if (!documentPages.length) {
        pageIndicator.textContent = 'Page 0 of 0';
    } else {
        pageIndicator.textContent = 'Page ' + (currentPageIndex + 1) + ' of ' + documentPages.length;
    }
}

function updateTransform() {
    const content = document.getElementById('current-page-content');
    const zoomLabel = document.getElementById('zoom-level');
    if (!content || !zoomLabel) {
        return;
    }

    const currentPage = documentPages[currentPageIndex];

    if (currentPage && currentPage.media_type === 'image') {
        content.style.transform = 'scale(' + (zoomLevel / 100) + ') rotate(' + rotation + 'deg)';
        zoomLabel.textContent = zoomLevel + '%';
    } else {
        content.style.transform = 'scale(1) rotate(' + rotation + 'deg)';
        zoomLabel.textContent = '100%';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const prevButton = document.getElementById('prev-page');
    const nextButton = document.getElementById('next-page');
    const zoomInButton = document.getElementById('zoom-in');
    const zoomOutButton = document.getElementById('zoom-out');
    const rotateButton = document.getElementById('rotate');
    const closeButton = document.getElementById('close-viewer');

    if (prevButton) {
        prevButton.addEventListener('click', function() {
            if (currentPageIndex > 0) {
                selectPage(currentPageIndex - 1);
            }
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', function() {
            if (currentPageIndex < documentPages.length - 1) {
                selectPage(currentPageIndex + 1);
            }
        });
    }

    if (zoomInButton) {
        zoomInButton.addEventListener('click', function() {
            zoomLevel = Math.min(zoomLevel + 25, 200);
            updateTransform();
        });
    }

    if (zoomOutButton) {
        zoomOutButton.addEventListener('click', function() {
            zoomLevel = Math.max(zoomLevel - 25, 50);
            updateTransform();
        });
    }

    if (rotateButton) {
        rotateButton.addEventListener('click', function() {
            rotation = (rotation + 90) % 360;
            updateTransform();
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', function() {
            $('#document-viewer-dialog').fadeOut('fast');
            clearDocumentViewerData();
        });
    }

    document.addEventListener('keydown', function(e) {
        const viewer = document.getElementById('document-viewer-dialog');
        if (!viewer || viewer.style.display === 'none') {
            return;
        }

        switch (e.key) {
            case 'ArrowLeft':
                if (currentPageIndex > 0) {
                    selectPage(currentPageIndex - 1);
                }
                break;
            case 'ArrowRight':
                if (currentPageIndex < documentPages.length - 1) {
                    selectPage(currentPageIndex + 1);
                }
                break;
            case 'Escape':
                $('#document-viewer-dialog').fadeOut('fast');
                clearDocumentViewerData();
                break;
            case '+':
            case '=':
                zoomLevel = Math.min(zoomLevel + 25, 200);
                updateTransform();
                break;
            case '-':
                zoomLevel = Math.max(zoomLevel - 25, 50);
                updateTransform();
                break;
            case 'r':
            case 'R':
                rotation = (rotation + 90) % 360;
                updateTransform();
                break;
        }
    });
});

/* ============================================================
 * Quality Control — in-viewer editing (rotate/enhance/replace)
 * + pass/fail review. Edits overwrite the image in place; the
 * stored filename/path never changes (server backs up original).
 * ============================================================ */
let qcEditMode = false;
let qcDirty = false;
let qcBaseImage = null;          // HTMLImageElement of the current (or replacement) image
let qcRotation = 0;              // 0/90/180/270
let qcBrightness = 100;          // percent
let qcContrast = 100;            // percent
let qcCropping = false;          // crop selection in progress/armed
let qcCropStart = null;          // {x, y} viewport coords of drag start
let qcCropRect = null;           // {left, top, w, h} viewport coords of selection

function qcEndpoint(kind) {
    const toolbar = document.getElementById('qc-toolbar');
    const page = documentPages[currentPageIndex];
    if (!toolbar || !page || !page.pagetyping_id) return null;
    const attr = kind === 'apply' ? 'data-apply-endpoint'
        : kind === 'delete' ? 'data-delete-endpoint'
        : 'data-qc-endpoint';
    const tpl = toolbar.getAttribute(attr);
    return tpl ? tpl.replace('__ID__', page.pagetyping_id) : null;
}

function qcCsrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/* ============================================================
 * Missing / broken page images.
 *
 * The page row, its classification and its QC history are all intact —
 * only the scanned image is gone from the EDMS disk. Restoring the page
 * therefore means sourcing a fresh image, not re-typing it, so the viewer
 * reuses the Master LFS Folder browser that Scan Upload and Page Typing
 * already replace images with. The replacement is recorded in
 * scan_image_versions like any other, and nothing is deleted.
 * ============================================================ */

/** The archive stages its replacement scans in its own drop folder. */
const ARCHIVE_SCAN_LIBRARY = 'archive';

function isPageBroken(page) {
    return !!page && (page.is_broken === true || !page.viewer_url);
}

/**
 * Show the "image is missing — replace it" note on the empty-preview panel.
 * The panel's caption is reset here too, because showEmptyState() overwrites
 * it with a file-level message that must not linger on a page view.
 */
function showMissingImageNote(show) {
    const placeholder = document.getElementById('document-placeholder');
    const note = document.getElementById('placeholder-missing-note');
    if (!note) return;

    note.classList.toggle('hidden', !show);
    note.classList.toggle('flex', !!show);

    // Replacing changes the file, so it lives behind the master Edit switch
    // like every other editing action in this viewer.
    const button = document.getElementById('placeholder-lf-replace');
    const locked = document.getElementById('placeholder-lf-locked');
    if (button) button.classList.toggle('hidden', !masterEditMode);
    if (locked) locked.classList.toggle('hidden', !!masterEditMode);

    if (show && placeholder) {
        const caption = placeholder.querySelector('p');
        if (caption) caption.textContent = 'No preview available for this page.';
        if (window.lucide) lucide.createIcons();
    }
}

function missingPageIndexes() {
    const indexes = [];
    documentPages.forEach((page, index) => {
        if (isPageBroken(page)) indexes.push(index);
    });
    return indexes;
}

function updateMissingPagesBadge() {
    const badge = document.getElementById('viewer-missing-badge');
    const count = document.getElementById('viewer-missing-count');
    if (!badge || !count) return;

    const missing = missingPageIndexes().length;
    badge.classList.toggle('hidden', missing === 0);
    badge.classList.toggle('inline-flex', missing > 0);
    count.textContent = missing === 1 ? '1 missing image' : missing + ' missing images';
}

/** Jump to the next page with no image, wrapping back to the first. */
function goToNextMissingPage() {
    const missing = missingPageIndexes();
    if (!missing.length) return;
    let next = missing.find(index => index > currentPageIndex);
    if (next === undefined) next = missing[0];
    selectPage(next);
}

function lfReplaceBlocked(reason) {
    if (window.Swal) {
        Swal.fire({ icon: 'warning', title: 'Cannot replace this page', text: reason });
    } else {
        alert(reason);
    }
}

/**
 * Replace the current page's image from the Master LFS Folder.
 *
 * Works both for a page that still has an image (a bad scan) and for one
 * whose image has gone missing — the second case is why the archive needs
 * it, and it is the one the server has to be told to expect.
 */
function openLfReplacement() {
    const page = documentPages[currentPageIndex];
    if (!page) return;

    if (typeof window.LargeFormatScans === 'undefined') {
        lfReplaceBlocked('The Master LFS Folder browser did not load. Refresh the page and try again.');
        return;
    }
    if (!currentFileMeta || !currentFileMeta.id) {
        lfReplaceBlocked('The file could not be identified. Reopen the document and try again.');
        return;
    }
    // The new image is attached to the scan record this page was typed from;
    // without one there is nothing to hang it on.
    if (!page.scanning_id || !page.scanning_document_path) {
        lfReplaceBlocked('This page has no scan record, so its image cannot be replaced here.');
        return;
    }

    const broken = isPageBroken(page);
    const label = page.page_code || ('page ' + (currentPageIndex + 1));

    window.LargeFormatScans.open({
        existingUrl: broken ? '' : (page.viewer_url || ''),
        existingMissing: broken,
        title: (broken ? 'Restore ' : 'Replace ') + label + ' from the Archive Scan Folder',
        confirmLabel: broken ? 'Confirm Restore' : 'Confirm Replacement',
        // Archive pages restored from this folder are ordinary document pages,
        // not large-format scans, so the panes say what they really are and the
        // browser reads the archive's own drop folder, not the LF one.
        replacementLabel: 'Replacement scan',
        library: ARCHIVE_SCAN_LIBRARY,
        sourceLabel: 'Archive Scan Folder',
        // The raw scans are filed one folder per file number, so the picker
        // opens inside this file's own folder instead of a share root that
        // holds a folder for every file ever scanned.
        fileIndexingId: currentFileMeta.id,
        onConfirm: async entry => {
            const result = await window.LargeFormatScans.save({
                source: entry.path,
                library: ARCHIVE_SCAN_LIBRARY,
                file_indexing_id: currentFileMeta.id,
                scanning_id: page.scanning_id,
                expected_path: page.scanning_document_path,
                // A missing image is the whole reason this page is being restored,
                // so the server's "original must still be on disk" guard is waived.
                allow_missing_original: broken,
            });

            const doc = result.document || {};
            const freshUrl = doc.file_url
                ? doc.file_url + (doc.file_url.indexOf('?') === -1 ? '?' : '&') + 'v=' + Date.now()
                : page.viewer_url;

            page.viewer_url = freshUrl;
            page.thumbnail_url = freshUrl;
            page.media_type = 'image';
            page.is_editable = true;
            page.is_broken = false;
            if (doc.document_path) page.scanning_document_path = doc.document_path;
            if (doc.original_filename) page.scanning_original_filename = doc.original_filename;

            renderPagesList();
            selectPage(currentPageIndex);

            if (window.Swal) {
                Swal.fire({
                    icon: 'success',
                    title: broken ? 'Page image restored' : 'Page image replaced',
                    text: 'The page keeps its classification and QC history. Check that the type and subtype are still correct.',
                });
            }
        },
    });
}

window.openLfReplacement = openLfReplacement;

function refreshQcUi() {
    const page = documentPages[currentPageIndex];
    // Any page change leaves edit mode without saving.
    if (qcEditMode) exitQcEditMode();

    const badge = document.getElementById('qc-status-badge');
    const toggle = document.getElementById('qc-edit-toggle');
    const meta = document.getElementById('qc-review-meta');
    const editable = !!(page && page.is_editable);

    if (toggle) {
        toggle.disabled = !editable;
        toggle.classList.toggle('opacity-50', !editable);
        toggle.title = editable ? 'Quality control tools' : 'Only image pages can be edited';
    }

    if (badge) {
        const status = page ? (page.qc_status || 'pending') : 'pending';
        const map = {
            passed: { t: 'QC Passed', c: 'bg-green-100 text-green-700' },
            failed: { t: 'QC Failed', c: 'bg-red-100 text-red-700' },
            pending: { t: 'QC Pending', c: 'bg-gray-100 text-gray-600' },
        };
        const cfg = map[status] || map.pending;
        badge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ' + cfg.c;
        badge.textContent = cfg.t;
        badge.classList.remove('hidden');
    }

    if (meta) {
        if (page && page.qc_reviewed_by) {
            meta.textContent = 'Reviewed by ' + page.qc_reviewed_by + (page.qc_reviewed_at ? ' · ' + page.qc_reviewed_at : '');
        } else {
            meta.textContent = 'Not yet reviewed';
        }
    }
    const noteInput = document.getElementById('qc-note');
    if (noteInput) noteInput.value = (page && page.qc_override_note) ? page.qc_override_note : '';
}

function qcRenderCanvas() {
    const canvas = document.getElementById('qc-canvas');
    if (!canvas || !qcBaseImage) return;
    const ctx = canvas.getContext('2d');
    const w = qcBaseImage.naturalWidth;
    const h = qcBaseImage.naturalHeight;
    const swap = (qcRotation === 90 || qcRotation === 270);
    canvas.width = swap ? h : w;
    canvas.height = swap ? w : h;

    ctx.save();
    ctx.filter = 'brightness(' + qcBrightness + '%) contrast(' + qcContrast + '%)';
    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.rotate(qcRotation * Math.PI / 180);
    ctx.drawImage(qcBaseImage, -w / 2, -h / 2);
    ctx.restore();
}

function qcMarkDirty() {
    qcDirty = true;
    const save = document.getElementById('qc-save-btn');
    if (save) save.disabled = false;
}

function qcShowBrokenCanvas() {
    qcBaseImage = null; // nothing to rotate/crop/enhance until a Replace happens
    const canvas = document.getElementById('qc-canvas');
    if (!canvas) return;
    canvas.width = 800;
    canvas.height = 1035;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#f9fafb';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 3;
    ctx.strokeRect(12, 12, canvas.width - 24, canvas.height - 24);
    ctx.fillStyle = '#9ca3af';
    ctx.textAlign = 'center';
    ctx.font = 'bold 30px sans-serif';
    ctx.fillText('Image unavailable', canvas.width / 2, canvas.height / 2 - 12);
    ctx.font = '20px sans-serif';
    ctx.fillText('Click "Replace" to upload a new image for this page.', canvas.width / 2, canvas.height / 2 + 26);
    canvas.classList.remove('hidden');
    const save = document.getElementById('qc-save-btn');
    if (save) save.disabled = true; // stays disabled until a replacement is chosen
    qcToast('This image is broken or missing — use Replace to upload a new one.');
}

function enterQcEditMode() {
    const page = documentPages[currentPageIndex];
    if (!page || !page.is_editable) return;

    qcEditMode = true;
    qcDirty = false;
    qcRotation = 0;
    qcBrightness = 100;
    qcContrast = 100;

    document.getElementById('qc-toolbar').classList.remove('hidden');
    document.getElementById('qc-enhance-panel').classList.add('hidden');
    ['qc-brightness', 'qc-contrast'].forEach(id => { const el = document.getElementById(id); if (el) el.value = 100; });
    document.getElementById('qc-brightness-val').textContent = '100%';
    document.getElementById('qc-contrast-val').textContent = '100%';
    const save = document.getElementById('qc-save-btn'); if (save) save.disabled = true;

    // Neutralise the view-only transform while editing on the canvas.
    const content = document.getElementById('current-page-content');
    if (content) content.style.transform = 'scale(1) rotate(0deg)';

    const img = document.getElementById('document-image');
    const canvas = document.getElementById('qc-canvas');
    if (img) img.classList.add('hidden');

    // Broken/missing image (no source URL) → go straight to the replace canvas.
    if (!page.viewer_url || page.is_broken) {
        qcShowBrokenCanvas();
        return;
    }

    qcBaseImage = new Image();
    qcBaseImage.onload = function () {
        qcRenderCanvas();
        if (canvas) canvas.classList.remove('hidden');
    };
    qcBaseImage.onerror = function () {
        // The URL existed but the file won't decode — treat as broken so the
        // reviewer can still Replace it.
        qcShowBrokenCanvas();
    };
    // Same-origin public-disk image → canvas stays untainted for toBlob().
    qcBaseImage.src = (page.viewer_url || '').split('?')[0] + '?edit=' + Date.now();
}

function exitQcEditMode() {
    qcCancelCrop();
    qcEditMode = false;
    qcDirty = false;
    qcBaseImage = null;
    const toolbar = document.getElementById('qc-toolbar');
    if (toolbar) toolbar.classList.add('hidden');
    const canvas = document.getElementById('qc-canvas');
    if (canvas) canvas.classList.add('hidden');
    const img = document.getElementById('document-image');
    const page = documentPages[currentPageIndex];
    if (img && page && page.media_type === 'image' && page.viewer_url) {
        img.classList.remove('hidden');
    }
    updateTransform();
}

/* ---- Crop: drag a rectangle over the canvas, then apply ---- */
function qcCropOverlayEl() {
    let el = document.getElementById('qc-crop-rect');
    if (!el) {
        el = document.createElement('div');
        el.id = 'qc-crop-rect';
        el.className = 'fixed z-[70] border-2 border-dashed border-amber-500 bg-amber-400/20 pointer-events-none';
        el.style.display = 'none';
        document.body.appendChild(el);
    }
    return el;
}

function qcStartCropMode() {
    if (!qcEditMode || !qcBaseImage) return;
    qcCropping = true;
    qcCropStart = null;
    qcCropRect = null;
    const canvas = document.getElementById('qc-canvas');
    if (canvas) canvas.style.cursor = 'crosshair';
    document.getElementById('qc-crop-apply').classList.remove('hidden');
    document.getElementById('qc-crop-cancel').classList.remove('hidden');
    document.getElementById('qc-crop-toggle').classList.add('hidden');
    qcToast('Drag a rectangle over the page, then "Apply crop".');
}

function qcCancelCrop() {
    qcCropping = false;
    qcCropStart = null;
    qcCropRect = null;
    const overlay = document.getElementById('qc-crop-rect');
    if (overlay) overlay.style.display = 'none';
    const canvas = document.getElementById('qc-canvas');
    if (canvas) canvas.style.cursor = '';
    ['qc-crop-apply', 'qc-crop-cancel'].forEach(id => { const el = document.getElementById(id); if (el) el.classList.add('hidden'); });
    const toggle = document.getElementById('qc-crop-toggle');
    if (toggle && qcEditMode) toggle.classList.remove('hidden');
}

function qcCropMouseDown(e) {
    if (!qcCropping) return;
    const canvas = document.getElementById('qc-canvas');
    const rect = canvas.getBoundingClientRect();
    if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) return;
    e.preventDefault();
    qcCropStart = { x: e.clientX, y: e.clientY };
    qcCropRect = null;
}

function qcCropMouseMove(e) {
    if (!qcCropping || !qcCropStart) return;
    const overlay = qcCropOverlayEl();
    const left = Math.min(qcCropStart.x, e.clientX);
    const top = Math.min(qcCropStart.y, e.clientY);
    const w = Math.abs(e.clientX - qcCropStart.x);
    const h = Math.abs(e.clientY - qcCropStart.y);
    qcCropRect = { left, top, w, h };
    overlay.style.display = 'block';
    overlay.style.left = left + 'px';
    overlay.style.top = top + 'px';
    overlay.style.width = w + 'px';
    overlay.style.height = h + 'px';
}

function qcCropMouseUp() {
    if (!qcCropping) return;
    qcCropStart = null; // selection kept in qcCropRect until Apply
}

function qcApplyCrop() {
    const canvas = document.getElementById('qc-canvas');
    if (!canvas || !qcCropRect || qcCropRect.w < 8 || qcCropRect.h < 8) {
        qcToast('Draw a larger selection first.');
        return;
    }
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    let sx = Math.max(0, (qcCropRect.left - rect.left) * scaleX);
    let sy = Math.max(0, (qcCropRect.top - rect.top) * scaleY);
    let sw = Math.min(canvas.width - sx, qcCropRect.w * scaleX);
    let sh = Math.min(canvas.height - sy, qcCropRect.h * scaleY);
    if (sw < 1 || sh < 1) { qcToast('Selection is outside the page.'); return; }

    // Bake the currently displayed canvas (rotation + enhance already applied) into the crop.
    const tmp = document.createElement('canvas');
    tmp.width = Math.round(sw);
    tmp.height = Math.round(sh);
    tmp.getContext('2d').drawImage(canvas, sx, sy, sw, sh, 0, 0, tmp.width, tmp.height);

    const dataUrl = tmp.toDataURL('image/jpeg', 0.95);
    qcCancelCrop();
    // Flatten: cropped result becomes the new base; reset transform/filters.
    qcRotation = 0;
    qcBrightness = 100;
    qcContrast = 100;
    ['qc-brightness', 'qc-contrast'].forEach(id => { const el = document.getElementById(id); if (el) el.value = 100; });
    document.getElementById('qc-brightness-val').textContent = '100%';
    document.getElementById('qc-contrast-val').textContent = '100%';
    qcBaseImage = new Image();
    qcBaseImage.onload = function () { qcRenderCanvas(); qcMarkDirty(); };
    qcBaseImage.src = dataUrl;
}

function qcSave() {
    const canvas = document.getElementById('qc-canvas');
    const url = qcEndpoint('apply');
    if (!canvas || !url) return;

    const saveBtn = document.getElementById('qc-save-btn');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.querySelector('span').textContent = 'Saving…'; }

    canvas.toBlob(function (blob) {
        if (!blob) { alert('Could not read the edited image.'); return; }
        const form = new FormData();
        form.append('file', blob, 'qc-edit.jpg');

        fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': qcCsrf(), 'Accept': 'application/json' },
            body: form,
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Save failed');
            const page = documentPages[currentPageIndex];
            if (page && data.viewer_url) {
                page.viewer_url = data.viewer_url;
                page.thumbnail_url = data.viewer_url;
                page.media_type = 'image'; // a broken page is now a real image
                page.is_broken = false;
            }
            exitQcEditMode();
            // Re-render the (now overwritten) image + thumbnail with the cache-busted URL.
            renderPagesList();
            selectPage(currentPageIndex);
            qcToast('Document updated — filename unchanged.');
        })
        .catch(err => alert('Unable to save: ' + err.message))
        .finally(() => {
            if (saveBtn) { saveBtn.querySelector('span').textContent = 'Save changes'; }
        });
    }, 'image/jpeg', 0.95);
}

function qcSetStatus(status) {
    const url = qcEndpoint('qc');
    if (!url) return;
    const note = document.getElementById('qc-note');
    const form = new FormData();
    form.append('qc_status', status);
    if (note && note.value.trim()) form.append('qc_override_note', note.value.trim());
    if (status === 'failed') form.append('has_qc_issues', '1');

    fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': qcCsrf(), 'Accept': 'application/json' },
        body: form,
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.message || 'Failed to save QC status');
        const page = documentPages[currentPageIndex];
        if (page) {
            page.qc_status = data.qc_status;
            page.qc_reviewed_by = data.qc_reviewed_by;
            page.qc_reviewed_at = data.qc_reviewed_at;
            page.qc_override_note = data.qc_override_note;
            page.has_qc_issues = data.has_qc_issues;
        }
        // Update the badge/meta without leaving edit mode.
        const badge = document.getElementById('qc-status-badge');
        const map = {
            passed: { t: 'QC Passed', c: 'bg-green-100 text-green-700' },
            failed: { t: 'QC Failed', c: 'bg-red-100 text-red-700' },
            pending: { t: 'QC Pending', c: 'bg-gray-100 text-gray-600' },
        };
        const cfg = map[data.qc_status] || map.pending;
        if (badge) { badge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ' + cfg.c; badge.textContent = cfg.t; badge.classList.remove('hidden'); }
        const meta = document.getElementById('qc-review-meta');
        if (meta && data.qc_reviewed_by) meta.textContent = 'Reviewed by ' + data.qc_reviewed_by + (data.qc_reviewed_at ? ' · ' + data.qc_reviewed_at : '');
        qcToast(status === 'passed' ? 'Marked as QC passed.' : 'Marked as QC failed.');
    })
    .catch(err => alert(err.message));
}

function qcDeletePage() {
    const page = documentPages[currentPageIndex];
    const url = qcEndpoint('delete');
    if (!page || !url) return;

    const label = page.page_code || (page.page_type && page.page_type.name) || ('Page ' + (page.page_number ?? (currentPageIndex + 1)));
    if (!confirm(`Delete "${label}"?\n\nThis permanently removes the page from the archive and cannot be undone.`)) {
        return;
    }

    const delBtn = document.getElementById('qc-delete-btn');
    if (delBtn) { delBtn.disabled = true; const s = delBtn.querySelector('span'); if (s) s.textContent = 'Deleting…'; }

    fetch(url, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': qcCsrf(),
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.message || 'Delete failed');

        exitQcEditMode();

        // Drop the page from memory and re-render the list.
        const removedIndex = currentPageIndex;
        documentPages.splice(removedIndex, 1);

        if (!documentPages.length) {
            renderPagesList();
            showEmptyState('This document has no pages left.');
            qcToast('Page deleted.');
            return;
        }

        const nextIndex = Math.min(removedIndex, documentPages.length - 1);
        renderPagesList();
        selectPage(nextIndex);
        qcToast('Page deleted.');
    })
    .catch(err => alert('Unable to delete: ' + err.message))
    .finally(() => {
        if (delBtn) { delBtn.disabled = false; const s = delBtn.querySelector('span'); if (s) s.textContent = 'Delete page'; }
    });
}

function qcToast(msg) {
    let el = document.getElementById('qc-toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'qc-toast';
        el.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] bg-gray-900 text-white text-sm px-4 py-2 rounded-md shadow-lg transition-opacity';
        document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.opacity = '1';
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.style.opacity = '0'; }, 2500);
}

document.addEventListener('DOMContentLoaded', function () {
    const on = (id, evt, fn) => { const el = document.getElementById(id); if (el) el.addEventListener(evt, fn); };

    on('master-edit-toggle', 'click', () => setMasterEditMode(!masterEditMode));
    on('qc-edit-toggle', 'click', () => { qcEditMode ? exitQcEditMode() : enterQcEditMode(); if (window.lucide) lucide.createIcons(); });
    on('qc-cancel-btn', 'click', () => {
        if (qcDirty && !confirm('Discard unsaved edits?')) return;
        exitQcEditMode();
    });
    on('qc-rotate-left', 'click', () => { if (!qcEditMode) return; qcRotation = (qcRotation + 270) % 360; qcRenderCanvas(); qcMarkDirty(); });
    on('qc-rotate-right', 'click', () => { if (!qcEditMode) return; qcRotation = (qcRotation + 90) % 360; qcRenderCanvas(); qcMarkDirty(); });
    on('qc-enhance-toggle', 'click', () => { document.getElementById('qc-enhance-panel').classList.toggle('hidden'); });
    on('qc-enhance-reset', 'click', () => {
        qcBrightness = 100; qcContrast = 100;
        document.getElementById('qc-brightness').value = 100;
        document.getElementById('qc-contrast').value = 100;
        document.getElementById('qc-brightness-val').textContent = '100%';
        document.getElementById('qc-contrast-val').textContent = '100%';
        qcRenderCanvas(); qcMarkDirty();
    });
    on('qc-brightness', 'input', (e) => { qcBrightness = parseInt(e.target.value, 10); document.getElementById('qc-brightness-val').textContent = qcBrightness + '%'; qcRenderCanvas(); qcMarkDirty(); });
    on('qc-contrast', 'input', (e) => { qcContrast = parseInt(e.target.value, 10); document.getElementById('qc-contrast-val').textContent = qcContrast + '%'; qcRenderCanvas(); qcMarkDirty(); });

    on('qc-replace-btn', 'click', () => { const i = document.getElementById('qc-replace-input'); if (i) i.click(); });
    on('qc-replace-input', 'change', (e) => {
        const file = e.target.files && e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (ev) => {
            qcRotation = 0;
            qcBaseImage = new Image();
            qcBaseImage.onload = () => { qcRenderCanvas(); document.getElementById('qc-canvas').classList.remove('hidden'); qcMarkDirty(); };
            qcBaseImage.src = ev.target.result;
        };
        reader.readAsDataURL(file);
        e.target.value = '';
    });

    // Restore/replace a page image from the Master LFS Folder. Reachable both
    // from the QC toolbar and straight from the empty-preview panel, which is
    // where a missing page actually announces itself.
    on('qc-lf-replace-btn', 'click', () => { exitQcEditMode(); openLfReplacement(); });
    on('placeholder-lf-replace', 'click', openLfReplacement);
    on('viewer-missing-badge', 'click', goToNextMissingPage);

    on('qc-crop-toggle', 'click', qcStartCropMode);
    on('qc-crop-apply', 'click', qcApplyCrop);
    on('qc-crop-cancel', 'click', qcCancelCrop);

    const canvasEl = document.getElementById('qc-canvas');
    if (canvasEl) canvasEl.addEventListener('mousedown', qcCropMouseDown);
    document.addEventListener('mousemove', qcCropMouseMove);
    document.addEventListener('mouseup', qcCropMouseUp);

    on('qc-save-btn', 'click', qcSave);
    on('qc-delete-btn', 'click', qcDeletePage);
    on('qc-pass-btn', 'click', () => qcSetStatus('passed'));
    on('qc-fail-btn', 'click', () => qcSetStatus('failed'));
});
</script>

<script>
/* ============================================================
 * Edit File Type — re-classify the current page from the viewer.
 * Reuses the same reference data + page-code format as pagetyping,
 * saving through /filearchive/pages/{id}/classification. It never
 * renames the stored file; only the classification columns change.
 * ============================================================ */
(function () {
    const dialog = document.getElementById('edit-filetype-dialog');
    if (!dialog) return;

    let typingData = null;              // { coverTypes, pageTypes, pageSubTypes }
    let typingDataPromise = null;
    let editingPageIndex = null;

    const $ = (id) => document.getElementById(id);
    const csrf = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };
    const isOther = (v) => ['other', 'others', 'oth'].includes(String(v).toLowerCase());
    const otherCode = (text) => (String(text || '').trim().substring(0, 4).toUpperCase() || 'OTH');

    async function loadTypingData() {
        if (typingData) return typingData;
        if (typingDataPromise) return typingDataPromise;

        typingDataPromise = fetch(dialog.dataset.typingDataUrl, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Failed to load classification data');
                typingData = {
                    coverTypes: (data.cover_types || []).map(c => ({ id: String(c.id ?? c.Id), name: c.name ?? c.Name, code: c.code ?? c.Code ?? 'CV' })),
                    pageTypes: (data.page_types || []).map(p => ({ id: String(p.id ?? p.Id), name: p.name ?? p.Name, code: p.code ?? p.Code ?? 'PT' })),
                    pageSubTypes: {}
                };
                const raw = data.page_sub_types || {};
                Object.keys(raw).forEach(k => {
                    typingData.pageSubTypes[String(k)] = (raw[k] || []).map(s => ({ id: String(s.id ?? s.Id), name: s.name ?? s.Name, code: s.code ?? s.Code ?? 'ST' }));
                });
                return typingData;
            });
        return typingDataPromise;
    }

    function coverById(id) { return typingData.coverTypes.find(c => c.id == id); }
    function pageTypeById(id) { return typingData.pageTypes.find(p => p.id == id); }
    function subTypeById(typeId, subId) { return (typingData.pageSubTypes[String(typeId)] || []).find(s => s.id == subId); }

    function fillSelect(select, items, placeholder) {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        items.forEach(it => {
            const opt = document.createElement('option');
            opt.value = it.id;
            opt.textContent = `${it.name} (${it.code})`;
            select.appendChild(opt);
        });
    }

    function populateSubtypes(typeId, selectedSubId) {
        const subs = typingData.pageSubTypes[String(typeId)] || [];
        fillSelect($('eft-page-subtype'), subs, 'Select page subtype');
        if (selectedSubId != null) $('eft-page-subtype').value = String(selectedSubId);
    }

    function toggleOther(selectId, inputId) {
        const input = $(inputId);
        if (isOther($(selectId).value)) {
            input.classList.remove('hidden');
        } else {
            input.classList.add('hidden');
        }
    }

    function updatePreview() {
        const page = documentPages[editingPageIndex] || {};
        const coverCode = coverById($('eft-cover-type').value)?.code || 'XX';

        const ptVal = $('eft-page-type').value;
        const pageCode = ptVal
            ? (isOther(ptVal) ? otherCode($('eft-page-type-other').value) : (pageTypeById(ptVal)?.code || 'XX'))
            : 'XX';

        const stVal = $('eft-page-subtype').value;
        let subCode = '';
        if (stVal) {
            subCode = isOther(stVal) ? otherCode($('eft-page-subtype-other').value) : (subTypeById(ptVal, stVal)?.code || 'XX');
        }

        const serial = ($('eft-serial').value || '').trim();
        const code = `${coverCode}-${pageCode}${subCode ? '-' + subCode : ''}-${serial}`;

        const position = page.definition != null ? page.definition : page.page_number;
        $('eft-code-preview').textContent = code;
        $('eft-definition-preview').textContent = `${position ?? ''}-${code}`;

        // With several pages ticked the code shown is only the FIRST one — each
        // page gets its own serial, so spell out the run rather than letting the
        // operator assume all pages share this exact code.
        const selectedCount = (typeof pageSelection !== 'undefined' && pageSelection.selected.size) || 0;
        const formatNote = $('eft-format-note');
        const definitionNote = $('eft-definition-note');
        const startSerial = parseInt(serial, 10);

        if (selectedCount > 1 && !isNaN(startSerial)) {
            const endSerial = startSerial + selectedCount - 1;
            const prefix = `${coverCode}-${pageCode}${subCode ? '-' + subCode : ''}`;

            if (formatNote) {
                formatNote.textContent =
                    `BC+FC apply: ${selectedCount} pages numbered ${prefix}-${startSerial} through ${prefix}-${endSerial}`;
            }
            if (definitionNote) {
                definitionNote.textContent =
                    'Serial increments per page, in panel order. Definition Code: AutoPosition-PageCode';
            }
        } else {
            if (formatNote) formatNote.textContent = 'Format: CoverType-PageType-SubType-SerialNo';
            if (definitionNote) definitionNote.textContent = 'Definition Code: AutoPosition-PageCode';
        }
    }

    // What the operator had typed before the dialog stepped aside for page
    // selection. Restored on reopen so nothing has to be entered twice.
    let pendingEntries = null;

    function snapshotEntries() {
        return {
            cover: $('eft-cover-type').value,
            pageType: $('eft-page-type').value,
            pageTypeOther: $('eft-page-type-other').value,
            subtype: $('eft-page-subtype').value,
            subtypeOther: $('eft-page-subtype-other').value,
            serial: $('eft-serial').value,
            registry: $('eft-registry').value,
        };
    }

    function restoreEntries(entry) {
        if (!entry) return;

        $('eft-cover-type').value = entry.cover || '';
        $('eft-page-type').value = entry.pageType || '';
        $('eft-page-type-other').value = entry.pageTypeOther || '';
        toggleOther('eft-page-type', 'eft-page-type-other');

        populateSubtypes($('eft-page-type').value, null);
        $('eft-page-subtype').value = entry.subtype || '';
        $('eft-page-subtype-other').value = entry.subtypeOther || '';
        toggleOther('eft-page-subtype', 'eft-page-subtype-other');

        if (entry.serial !== '') $('eft-serial').value = entry.serial;
        if (entry.registry) $('eft-registry').value = entry.registry;

        updatePreview();
    }

    /** Step aside so the Document Pages panel can be used, then come back. */
    function beginPageSelection() {
        pendingEntries = snapshotEntries();
        closeModal({ keepEntries: true });

        enterPageSelectionMode(() => {
            openModal();
        });
    }

    function renderScopeBanner() {
        const banner = $('edit-filetype-scope');
        if (!banner) return;

        const count = (typeof pageSelection !== 'undefined' && pageSelection.selected.size) || 0;
        if (count > 1) {
            $('edit-filetype-scope-count').textContent = String(count);
            banner.classList.remove('hidden');
        } else {
            banner.classList.add('hidden');
        }
    }

    async function openModal() {
        if (typeof documentPages === 'undefined' || !documentPages.length) {
            alert('Open a document and select a page first.');
            return;
        }
        editingPageIndex = currentPageIndex;
        const page = documentPages[editingPageIndex];
        if (!page || !page.pagetyping_id) {
            alert('This page cannot be re-classified.');
            return;
        }

        try {
            await loadTypingData();
        } catch (e) {
            alert('Could not load classification options: ' + e.message);
            return;
        }

        // Populate selects
        fillSelect($('eft-cover-type'), typingData.coverTypes, 'Select cover type');
        fillSelect($('eft-page-type'), typingData.pageTypes, 'Select page type');

        // Pre-select existing classification
        if (page.cover_type && page.cover_type.id != null) $('eft-cover-type').value = String(page.cover_type.id);

        const pt = page.page_type || {};
        const knownType = pt.id != null && pageTypeById(pt.id);
        if (knownType) {
            $('eft-page-type').value = String(pt.id);
            $('eft-page-type-other').value = '';
        } else if (pt.id != null && String(pt.id) !== '') {
            $('eft-page-type').value = 'other';
            $('eft-page-type-other').value = pt.name && pt.name !== 'Unknown Type' ? pt.name : String(pt.id);
        }
        toggleOther('eft-page-type', 'eft-page-type-other');

        // Subtypes depend on the chosen type
        const activeTypeId = $('eft-page-type').value;
        populateSubtypes(activeTypeId, null);
        const st = page.page_subtype;
        if (st && st.id != null) {
            const knownSub = subTypeById(activeTypeId, st.id);
            if (knownSub) {
                $('eft-page-subtype').value = String(st.id);
                $('eft-page-subtype-other').value = '';
            } else {
                $('eft-page-subtype').value = 'other';
                $('eft-page-subtype-other').value = st.name && st.name !== 'Unknown Subtype' ? st.name : String(st.id);
            }
        }
        toggleOther('eft-page-subtype', 'eft-page-subtype-other');

        $('eft-serial').value = page.serial_number != null ? String(page.serial_number) : '';
        $('eft-serial-auto').textContent = page.serial_number != null ? String(page.serial_number) : '0';
        $('eft-page-label').textContent = 'Page ' + (page.page_number ?? (editingPageIndex + 1));
        $('eft-file-number').textContent = (currentFileMeta && currentFileMeta.file_number) ? currentFileMeta.file_number : (document.getElementById('viewer-file-number')?.textContent || '-');

        updatePreview();

        // Coming back from page selection: the operator's entries win over the
        // page's stored values, which is the whole point of stepping aside.
        if (pendingEntries) {
            restoreEntries(pendingEntries);
            pendingEntries = null;
        }

        renderScopeBanner();

        dialog.style.display = 'flex';
        dialog.setAttribute('aria-hidden', 'false');
        if (window.lucide) lucide.createIcons();
    }

    function closeModal(options = {}) {
        dialog.style.display = 'none';
        dialog.setAttribute('aria-hidden', 'true');
        editingPageIndex = null;

        // A normal close abandons a selection in progress; stepping aside for the
        // page picker does not.
        if (!options.keepEntries) {
            pendingEntries = null;
            if (typeof pageSelection !== 'undefined' && pageSelection.active) {
                exitPageSelectionMode();
            }
        }
    }

    // Master Edit closes this dialog when editing is switched off, so it needs a
    // way in from outside this module.
    window.closeEditFileTypeDialog = closeModal;

    async function save() {
        const page = documentPages[editingPageIndex];
        if (!page || !page.pagetyping_id) return;

        const coverVal = $('eft-cover-type').value;
        const ptVal = $('eft-page-type').value;
        const stVal = $('eft-page-subtype').value;
        const serial = ($('eft-serial').value || '').trim();

        if (!coverVal) { alert('Please select a cover type.'); return; }
        if (!ptVal) { alert('Please select a page type.'); return; }
        if (isOther(ptVal) && !$('eft-page-type-other').value.trim()) { alert('Please specify the custom page type.'); return; }
        if (stVal && isOther(stVal) && !$('eft-page-subtype-other').value.trim()) { alert('Please specify the custom page subtype.'); return; }
        if (serial === '' || isNaN(parseInt(serial, 10))) { alert('Please enter a numeric serial number.'); return; }

        updatePreview();
        const pageCode = $('eft-code-preview').textContent;

        const payload = {
            cover_type_id: coverVal,
            page_type: ptVal,
            page_type_other: isOther(ptVal) ? $('eft-page-type-other').value.trim() : null,
            page_subtype: stVal || null,
            page_subtype_other: (stVal && isOther(stVal)) ? $('eft-page-subtype-other').value.trim() : null,
            serial_number: parseInt(serial, 10),
            page_code: pageCode,
            registry: $('eft-registry').value || null
        };

        // Every ticked page gets this classification; with nothing ticked it is
        // just the page being edited.
        const targets = (typeof pageSelection !== 'undefined' && pageSelection.selected.size)
            ? selectedPageIndexes()
            : [editingPageIndex];

        const saveBtn = $('edit-filetype-save');
        const label = saveBtn.querySelector('span');
        saveBtn.disabled = true;
        if (label) label.textContent = targets.length > 1 ? `Saving 1 of ${targets.length}…` : 'Saving…';

        const failures = [];
        let saved = 0;

        try {
            for (const index of targets) {
                const target = documentPages[index];
                if (!target || !target.pagetyping_id) {
                    failures.push(`Page ${index + 1} cannot be re-classified.`);
                    continue;
                }

                if (label && targets.length > 1) {
                    label.textContent = `Saving ${saved + 1} of ${targets.length}…`;
                }

                // The serial identifies the page within the file, so each page in a
                // multi-page apply gets its own — reusing one would collide.
                const pagePayload = Object.assign({}, payload, {
                    serial_number: payload.serial_number + saved,
                });

                const res = await fetch(dialog.dataset.saveUrl.replace('__ID__', target.pagetyping_id), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(pagePayload)
                });
                const data = await res.json();

                if (!data.success) {
                    failures.push(`Page ${index + 1}: ${data.message || 'save failed'}`);
                    continue;
                }

                // Refresh in-memory page + re-render the list/detail in place.
                target.page_code = data.page_code;
                target.serial_number = data.serial_number;
                if (data.cover_type) target.cover_type = data.cover_type;
                if (data.page_type) target.page_type = data.page_type;
                target.page_subtype = data.page_subtype || null;
                saved++;
            }

            if (typeof pageSelection !== 'undefined' && pageSelection.active) {
                exitPageSelectionMode();
            }
            if (typeof renderPagesList === 'function') renderPagesList();
            if (typeof selectPage === 'function') selectPage(editingPageIndex);

            closeModal();

            if (failures.length) {
                alert('Saved ' + saved + ' page(s). These could not be saved: ' + failures.join(' | '));
            } else if (typeof qcToast === 'function') {
                qcToast(saved > 1 ? `${saved} pages re-classified.` : 'Page classification updated.');
            }
        } catch (err) {
            alert('Unable to save: ' + err.message);
        } finally {
            saveBtn.disabled = false;
            if (label) label.textContent = 'Save Classification';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const btn = $('edit-filetype-toggle');
        if (btn) btn.addEventListener('click', openModal);
        $('edit-filetype-close')?.addEventListener('click', () => closeModal());
        $('edit-filetype-cancel')?.addEventListener('click', () => closeModal());
        $('edit-filetype-select-pages')?.addEventListener('click', beginPageSelection);

        // Panel controls: Apply hands control back to the dialog, Cancel drops the
        // selection entirely.
        document.getElementById('page-select-apply')?.addEventListener('click', () => {
            const onApply = pageSelection.onApply;
            document.getElementById('page-select-bar')?.classList.add('hidden');
            pageSelection.active = false;
            renderPagesList();
            if (onApply) onApply();
        });

        document.getElementById('page-select-cancel')?.addEventListener('click', () => {
            exitPageSelectionMode();
        });
        $('edit-filetype-save')?.addEventListener('click', save);

        dialog.addEventListener('click', (e) => { if (e.target === dialog) closeModal(); });

        $('eft-cover-type')?.addEventListener('change', updatePreview);
        $('eft-serial')?.addEventListener('input', updatePreview);
        $('eft-page-type')?.addEventListener('change', function () {
            toggleOther('eft-page-type', 'eft-page-type-other');
            populateSubtypes(this.value, null);
            toggleOther('eft-page-subtype', 'eft-page-subtype-other');
            updatePreview();
        });
        $('eft-page-subtype')?.addEventListener('change', function () {
            toggleOther('eft-page-subtype', 'eft-page-subtype-other');
            updatePreview();
        });
        $('eft-page-type-other')?.addEventListener('input', updatePreview);
        $('eft-page-subtype-other')?.addEventListener('input', updatePreview);
    });

    window.openEditFileTypeModal = openModal;
})();
</script>
