<script>
    // Initialize PDF.js service worker
    if (typeof pdfjsLib !== "undefined") {
        pdfjsLib.GlobalWorkerOptions.workerSrc =
            "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
    }

    // Initialize Lucide icons
    // Initialize Lucide icons moved to init
    // lucide.createIcons();

    // State management
    let elements = {};
    let previewDragIndex = null;
    let selectedFilesRenderVersion = 0;
    const state = {
        activeTab: 'upload',
        uploadStatus: 'idle', // 'idle', 'uploading', 'complete', 'error'
        uploadProgress: 0,
        showUploadProgressModal: false,
        uploadProgressMessage: '',
        previewOpen: false,
        selectedFile: null,
        zoomLevel: 100,
        rotation: 0,
        currentPreviewPage: 1,
        selectedIndexedFile: null,
        selectedFilePreviewPages: [],
        selectedFilePreviewIndex: 0,
        selectedFilePreviewToken: 0,
        showFileSelector: false,
        selectedUploadFiles: [],
        showFolderView: false,
        selectedPageInFolder: null,
        showDocumentDetails: false,
        currentDocumentIndex: null,
        previewSettings: {
            items: [],
            activeIndex: 0,
            source: null,
            fileNumber: null,
            fullScreen: false,
            panOffset: { x: 0, y: 0 },
            panLimits: { x: 0, y: 0 },
            panBaseline: { x: 0, y: 0 },
            isPanning: false,
            panStart: null,
            cropMode: false,
            cropSelecting: false,
            cropStart: null,
            cropSelection: null,
            history: [],
            future: [],
            tempObjectUrls: [],
            sidebarCollapsed: false,
            selectMode: false,
            selectedForDelete: new Set()
        },
        documentBatches: [], // This will be populated from server
        uploadDocuments: [],
        filterPaperSize: 'All',
        searchQuery: '',
        pagination: {
            currentPage: 1,
            nextPage: null,
            prevPage: null,
            pageQuery: 'pag_next'
        },
        folderPagination: {
            currentPage: 1,
            perPage: 20,
            totalPages: 1,
        },
        // PDF Conversion State
        pdfConversionEnabled: true,
        hasPdfFiles: false,
        pdfConversionResults: {
            converted: 0,
            failed: 0,
            total: 0,
            failedFiles: []
        },
        lastBlindTransfer: {
            documents: [],
            indexedFile: null,
        },
        currentFolderId: null,
        // Store actual file URLs for display
        filePreviews: new Map(),
        blindScan: {
            modalOpen: false,
            loading: false,
            processing: false,
            error: null,
            fileNumber: null,
            files: [],
            stagedFiles: [],
            counts: {
                total: 0,
                byPaperSize: {},
                byDocumentType: {}
            }
        },
        // Server upload configuration - endpoints provided via dataset
        uploadEndpoint: null,
        reorderEndpoint: null,
        logEndpoint: null,
        deleteEndpointTemplate: null,
        downloadEndpointTemplate: null,
        applyEndpointTemplate: null,
        debugEndpoint: null,
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        blindScanDiscoverEndpoint: null,
        blindScanTransferEndpoint: null,
        // NEW: Track selected file number for upload more functionality
        selectedFileNumberForUpload: null,
        selectedUploadMethod: null,
        indexedFilesSearchTerm: ''
    };

    const appBaseMeta = document.querySelector('meta[name="app-base-url"]');
    const appBaseUrl = (appBaseMeta?.content || window.location.origin || '').replace(/\/+$/, '');
    const storageRootUrl = `${appBaseUrl}/storage`.replace(/\/+$/, '');

    function stripStorageAppPublicPrefix(path = '') {
        if (!path) {
            return '';
        }

        let sanitized = String(path).trim();

        // Remove host prefix if present
        sanitized = sanitized.replace(/^https?:\/\/[^/]+/i, '');

        // Remove leading slashes for easier matching
        sanitized = sanitized.replace(/^\/+/, '');

        // Remove common Laravel storage prefixes
        sanitized = sanitized.replace(/^storage\/app\/public\//i, '');
        sanitized = sanitized.replace(/^storage\/public\//i, '');
        sanitized = sanitized.replace(/^app\/public\//i, '');
        sanitized = sanitized.replace(/^storage\//i, '');

        return sanitized.replace(/^\/+/, '');
    }

    function resolveStorageUrl(candidatePath = '') {
        if (!candidatePath) {
            return null;
        }

        if (/^(https?:|data:|blob:)/i.test(candidatePath)) {
            return candidatePath;
        }

        const sanitized = stripStorageAppPublicPrefix(candidatePath) || candidatePath;
        return buildStorageUrl(sanitized);
    }

    function buildStorageUrl(relativePath = '') {
        if (!relativePath) {
            return storageRootUrl;
        }

        if (/^(https?:|data:|blob:)/i.test(relativePath)) {
            return relativePath;
        }

        const trimmedPath = String(relativePath || '').replace(/^\/+/, '');
        const sanitizedPath = stripStorageAppPublicPrefix(relativePath);

        // If sanitization changed the path (meaning it was pointing inside storage), use storage root
        if (sanitizedPath && sanitizedPath !== trimmedPath) {
            return `${storageRootUrl}/${sanitizedPath}`;
        }

        if (relativePath.startsWith('/')) {
            return `${appBaseUrl}/${trimmedPath}`;
        }

        return `${storageRootUrl}/${sanitizedPath || trimmedPath}`;
    }

    function toAbsoluteUrl(url = '') {
        if (!url) {
            return null;
        }

        if (/^(https?:|data:|blob:)/i.test(url)) {
            return url;
        }

        if (url.startsWith('/')) {
            return `${appBaseUrl}/${url.replace(/^\/+/, '')}`;
        }

        return `${appBaseUrl}/${url.replace(/^\/+/, '')}`;
    }

    // Document type suggestions
    const documentTypes = ["Certificate", "Deed", "Letter", "Application Form", "Map", "Survey Plan", "Receipt", "Other"];

    const indexedFilesEndpoint = "{{ route('scan-uploads.api.indexed-files.quick') }}";
    let indexedFiles = [];
    let indexedFilesPagination = { current_page: 1, per_page: 10, last_page: 1, total: 0 };
    let indexedFilesLoading = false;
    let indexedFilesError = '';

    // DOM Elements
    function queryDomElements() {
        return {
            // Tabs
            tabs: document.querySelectorAll('[role="tab"]'),
            tabContents: document.querySelectorAll('[role="tabpanel"]'),

            // Upload tab
            selectedFileBadge: document.querySelector('.selected-file-badge'),
            selectedFileNumber: document.getElementById('selected-file-number'),
            selectedUploadMethodLabel: document.getElementById('selected-upload-method'),
            selectFileBtn: document.getElementById('select-file-btn'),
            changeFileText: document.getElementById('change-file-text'),
            uploadIdle: document.getElementById('upload-idle'),
            fileUpload: document.getElementById('file-upload'),
            dropZone: document.querySelector('.drop-zone'),
            browseFilesBtn: document.getElementById('browse-files-btn'),
            selectFileWarning: document.getElementById('select-file-warning'),
            selectedFilesContainer: document.getElementById('selected-files-container'),
            selectedFilesCount: document.getElementById('selected-files-count'),
            selectedFilesList: document.getElementById('selected-files-list'),
            previewSelectedFilesBtn: document.getElementById('preview-selected-files-btn'),
            clearAllBtn: document.getElementById('clear-all-btn'),
            uploadProgress: document.getElementById('upload-progress'),
            uploadingCount: document.getElementById('uploading-count'),
            uploadPercentage: document.getElementById('upload-percentage'),
            progressBar: document.getElementById('progress-bar'),
            uploadComplete: document.getElementById('upload-complete'),
            uploadError: document.getElementById('upload-error'),
            uploadErrorMessage: document.getElementById('upload-error-message'),
            startUploadBtn: document.getElementById('start-upload-btn'),
            cancelUploadBtn: document.getElementById('cancel-upload-btn'),
            uploadMoreBtn: document.getElementById('upload-more-btn'),
            viewUploadedBtn: document.getElementById('view-uploaded-btn'),

            // PDF Conversion elements
            convertPdfs: document.getElementById('convertPdfs'),
            convertPdfsBtn: document.getElementById('convert-pdfs-btn'),
            pdfConversionText: document.getElementById('pdfConversionText'),
            autoConvertBadge: document.getElementById('autoConvertBadge'),
            pdfConversionModal: document.getElementById('pdf-conversion-modal'),
            pdfConversionCurrentFile: document.getElementById('pdf-conversion-current-file'),
            pdfConversionProgressPercent: document.getElementById('pdf-conversion-progress-percent'),
            pdfConversionProgressBar: document.getElementById('pdf-conversion-progress-bar'),

            // Uploaded files tab
            uploadsCount: document.getElementById('uploads-count'),
            pendingCount: document.getElementById('pending-count'),
            paperSizeFilter: document.getElementById('paper-size-filter'),
            fileSearch: document.getElementById('file-search'),
            toggleViewBtn: document.getElementById('toggle-view-btn'),
            noDocuments: document.getElementById('no-documents'),
            listView: document.getElementById('list-view'),
            folderView: document.getElementById('folder-view'),
            folderPaginationContainer: document.getElementById('folder-pagination'),
            folderPaginationSummary: document.getElementById('folder-pagination-summary'),
            batchActions: document.getElementById('batch-actions'),
            proceedToTypingBtn: document.getElementById('proceed-to-typing-btn'),
            goToUploadBtn: document.getElementById('go-to-upload-btn'),

            // Preview dialog
            previewDialog: document.getElementById('preview-dialog'),
            previewTitle: document.getElementById('preview-title'),
            previewFilename: document.getElementById('preview-filename'),
            previewImage: document.getElementById('preview-image'),
            previewImageWrapper: document.getElementById('preview-image-wrapper'),
            documentInfo: document.getElementById('document-info'),
            previewThumbnails: document.getElementById('preview-thumbnails'),
            previewSidebarCount: document.getElementById('preview-sidebar-count'),
            previewSidebarToggle: document.getElementById('preview-sidebar-toggle'),
            closePreviewBtn: document.getElementById('close-preview-btn'),
            toggleFullscreenBtn: document.getElementById('toggle-fullscreen-btn'),
            prevPageBtn: document.getElementById('prev-page-btn'),
            nextPageBtn: document.getElementById('next-page-btn'),
            zoomOutBtn: document.getElementById('zoom-out-btn'),
            zoomLevel: document.getElementById('zoom-level'),
            zoomInBtn: document.getElementById('zoom-in-btn'),
            rotateLeftBtn: document.getElementById('rotate-left-btn'),
            rotateBtn: document.getElementById('rotate-btn'),
            resetViewBtn: document.getElementById('reset-view-btn'),
            undoBtn: document.getElementById('undo-btn'),
            redoBtn: document.getElementById('redo-btn'),
            cropToggleBtn: document.getElementById('crop-toggle-btn'),
            cropOverlay: document.getElementById('crop-overlay'),
            cropSelection: document.getElementById('crop-selection'),
            cropActionButtons: document.getElementById('crop-action-buttons'),
            cropApplyBtn: document.getElementById('crop-apply-btn'),
            cropCancelBtn: document.getElementById('crop-cancel-btn'),
            previewUploadBtn: document.getElementById('preview-upload-btn'),
            previewUploadInput: document.getElementById('preview-upload-input'),
            previewDialogContent: document.querySelector('#preview-dialog .dialog-content'),
            previewShell: document.querySelector('#preview-dialog .preview-shell'),
            previewCanvas: document.getElementById('preview-canvas'),
            previewEditBtn: document.getElementById('preview-edit-btn'),
            previewReassignBtn: document.getElementById('preview-reassign-btn'),
            previewDeleteBtn: document.getElementById('preview-delete-btn'),
            applyPreviewBtn: document.getElementById('apply-preview-btn'),

            // File selector dialog
            fileSelectorDialog: document.getElementById('file-selector-dialog'),
            indexedFilesList: document.getElementById('indexed-files-list'),
            cancelFileSelectBtn: document.getElementById('cancel-file-select-btn'),
            confirmFileSelectBtn: document.getElementById('confirm-file-select-btn'),
            blindScanRegistry: document.getElementById('blind-scan-registry'),
            scanUploadFileType: document.getElementById('scan-upload-file-type'),
            scanUploadFileTypeCategory: document.getElementById('scan-upload-file-type-category'),
            scanUploadFileTypeType: document.getElementById('scan-upload-file-type-type'),
            scanUploadFileTypeVariant: document.getElementById('scan-upload-file-type-variant'),
            scanUploadFileTypeDetail: document.getElementById('scan-upload-file-type-detail'),
            scanUploadFileTypeVariantWrap: document.getElementById('scan-upload-file-type-variant-wrap'),
            scanUploadFileTypeVariantLabel: document.getElementById('scan-upload-file-type-variant-label'),
            scanUploadFileTypeHint: document.getElementById('scan-upload-file-type-hint'),
            previewCoverBtn: document.getElementById('scan-upload-preview-cover'),
            coverPreview: document.getElementById('scan-upload-cover-preview'),
            coverPreviewBody: document.getElementById('scan-upload-cover-body'),
            coverPreviewNavigation: document.getElementById('scan-upload-cover-navigation'),
            coverPreviewPrevious: document.getElementById('scan-upload-cover-previous'),
            coverPreviewNext: document.getElementById('scan-upload-cover-next'),
            coverPreviewPosition: document.getElementById('scan-upload-cover-position'),
            uploadMethodRadios: document.querySelectorAll('input[name="upload-method"]'),
            globalSelectedFileNoSelect: document.getElementById('global-selected-fileno-select'),

            // Document details dialog
            documentDetailsDialog: document.getElementById('document-details-dialog'),
            documentName: document.getElementById('document-name'),
            paperSizeRadios: document.querySelectorAll('input[name="paper-size"]'),
            documentType: document.getElementById('document-type'),
            documentNotes: document.getElementById('document-notes'),
            cancelDetailsBtn: document.getElementById('cancel-details-btn'),
            saveDetailsBtn: document.getElementById('save-details-btn')
        };
    }

    let uploadProgressElements = {};
    function queryUploadProgressElements() {
        return {
            modal: document.getElementById('upload-progress-modal'),
            message: document.getElementById('upload-progress-modal-message'),
            percent: document.getElementById('upload-progress-modal-percent'),
            bar: document.getElementById('upload-progress-modal-bar'),
            closeBtn: document.getElementById('upload-progress-modal-close')
        };
    }

    let blindScanElements = {};
    function queryBlindScanElements() {
        return {
            modal: document.getElementById('blind-scan-modal'),
            fileNumber: document.getElementById('blind-scan-modal-file-number'),
            loading: document.getElementById('blind-scan-loading'),
            error: document.getElementById('blind-scan-error'),
            empty: document.getElementById('blind-scan-empty'),
            summary: document.getElementById('blind-scan-summary'),
            total: document.getElementById('blind-scan-total'),
            paperSizes: document.getElementById('blind-scan-paper-sizes'),
            documentTypes: document.getElementById('blind-scan-document-types'),
            tableWrapper: document.getElementById('blind-scan-table-wrapper'),
            tableBody: document.getElementById('blind-scan-table-body'),
            transferBtn: document.getElementById('blind-scan-transfer-btn'),
            registry: document.getElementById('blind-scan-registry'),
            footnote: document.getElementById('blind-scan-footnote'),
            closeButtons: document.querySelectorAll('[data-blind-scan-close], [data-blind-scan-close-secondary]')
        };
    }

    function initConfig() {
        const rootElement = document.querySelector('[data-scan-upload-root]');
        if (rootElement) {
            const {
                uploadEndpoint: uploadEndpointAttr,
                reorderEndpoint: reorderEndpointAttr,
                logEndpoint: logEndpointAttr,
                deleteEndpoint: deleteEndpointAttr,
                downloadEndpoint: downloadEndpointAttr,
                debugEndpoint: debugEndpointAttr,
                applyEndpoint: applyEndpointAttr,
                blindDiscoverEndpoint: blindDiscoverEndpointAttr,
                blindTransferEndpoint: blindTransferEndpointAttr,
                scanUploads: scanUploadsAttr,
                currentPage: currentPageAttr,
                nextPage: nextPageAttr,
                prevPage: prevPageAttr,
                pageQuery: pageQueryAttr
            } = rootElement.dataset;

            state.uploadEndpoint = uploadEndpointAttr || state.uploadEndpoint || window.location.href;
            state.reorderEndpoint = reorderEndpointAttr || state.reorderEndpoint;
            state.logEndpoint = logEndpointAttr || state.logEndpoint;
            state.deleteEndpointTemplate = deleteEndpointAttr || state.deleteEndpointTemplate;
            state.downloadEndpointTemplate = downloadEndpointAttr || state.downloadEndpointTemplate;
            state.debugEndpoint = debugEndpointAttr || state.debugEndpoint;
            state.applyEndpointTemplate = applyEndpointAttr || state.applyEndpointTemplate;
            state.blindScanDiscoverEndpoint = blindDiscoverEndpointAttr || null;
            state.blindScanTransferEndpoint = blindTransferEndpointAttr || null;
            if (currentPageAttr) {
                const pageValue = parseInt(currentPageAttr, 10);
                state.pagination.currentPage = Number.isNaN(pageValue) ? 1 : pageValue;
            }
            if (typeof nextPageAttr !== 'undefined') {
                const nextValue = parseInt(nextPageAttr, 10);
                state.pagination.nextPage = Number.isNaN(nextValue) ? null : nextValue;
            }
            if (typeof prevPageAttr !== 'undefined') {
                const prevValue = parseInt(prevPageAttr, 10);
                state.pagination.prevPage = Number.isNaN(prevValue) ? null : prevValue;
            }
            if (pageQueryAttr) {
                state.pagination.pageQuery = pageQueryAttr;
            }

            if (scanUploadsAttr) {
                try {
                    const initialUploads = JSON.parse(scanUploadsAttr);
                    if (Array.isArray(initialUploads) && initialUploads.length && !state.documentBatches.length) {
                        state.documentBatches = initialUploads
                            .map((entry, index) => buildBatchFromServerEntry(entry, index))
                            .filter(Boolean);
                    }
                } catch (error) {
                    console.warn('Unable to parse initial scan uploads payload:', error);
                }
            }
        } else {
            console.warn('Scan Upload root element not found. Falling back to current URL for uploads.');
            state.uploadEndpoint = state.uploadEndpoint || window.location.href;
        }
    }

    // Helper functions
    function formatDateFromIso(value) {
        if (!value) {
            return '';
        }

        const date = value instanceof Date ? value : new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        return date.toLocaleDateString();
    }

    function resolveFileIndexingIdFromBatch(batch) {
        if (!batch) {
            return null;
        }

        const directId = Number(batch.fileIndexingId);
        if (Number.isInteger(directId) && directId > 0) {
            return directId;
        }

        const batchId = String(batch.id || '');
        const fromPattern = batchId.match(/^FILE-(\d+)$/i);
        if (fromPattern) {
            const parsed = Number(fromPattern[1]);
            if (Number.isInteger(parsed) && parsed > 0) {
                return parsed;
            }
        }

        const fromDocument = (batch.documents || []).find(doc => {
            const candidate = Number(doc?.fileIndexingId);
            return Number.isInteger(candidate) && candidate > 0;
        });

        return fromDocument ? Number(fromDocument.fileIndexingId) : null;
    }

    function openFilePagesInPageTyping(batchId, options = {}) {
        const batch = state.documentBatches.find(item => String(item.id) === String(batchId));
        const fileIndexingId = resolveFileIndexingIdFromBatch(batch);

        if (!fileIndexingId) {
            alert('Unable to open Page Typing for this item: file reference is missing.');
            return;
        }

        const target = new URL('/pagetyping', window.location.origin);
        target.searchParams.set('file_indexing_id', String(fileIndexingId));
        target.searchParams.set('tab', 'typing');
        if (options.folderId) {
            target.searchParams.set('folder', String(options.folderId));
        }

        window.location.href = target.toString();
    }

    function normalizeServerDocument(rawDoc) {
        if (!rawDoc) {
            return null;
        }

        const fileNumber = rawDoc.fileNumber || rawDoc.file_number || rawDoc.fileno || null;
        const definition = Number.isFinite(Number(rawDoc.definition)) ? Number(rawDoc.definition) : 0;
        const definitionCode = rawDoc.definitionCode || rawDoc.definition_code || (fileNumber ? `${definition}-${fileNumber}` : null);
        const uploadedAt = rawDoc.uploadedAt || rawDoc.date || null;
        const fileName = rawDoc.fileName || rawDoc.originalName || rawDoc.original_filename || rawDoc.name || (rawDoc.id ? `Document ${rawDoc.id}` : 'Document');
        const fileSize = typeof rawDoc.fileSize === 'number' ? rawDoc.fileSize : (rawDoc.size ?? 0);

        const rawDocumentPath = rawDoc.documentPath || rawDoc.document_path || '';
        const normalizedDocumentPath = rawDocumentPath
            ? stripStorageAppPublicPrefix(rawDocumentPath)
            : null;

        // Priority: downloadUrl > webPath > serverPath > derived storage path
        let imageUrl = rawDoc.downloadUrl || rawDoc.webPath || rawDoc.serverPath || rawDoc.server_path || null;

        if (!imageUrl && normalizedDocumentPath) {
            imageUrl = buildStorageUrl(normalizedDocumentPath);
        }

        if (imageUrl) {
            imageUrl = toAbsoluteUrl(imageUrl);
        }

        return {
            id: rawDoc.id ?? null,
            scanId: rawDoc.id ?? null,
            fileIndexingId: rawDoc.fileIndexingId ?? rawDoc.file_indexing_id ?? null,
            fileNumber,
            fileTitle: rawDoc.fileTitle || rawDoc.name || null,
            file: null,
            fileName,
            fileSize,
            definition,
            definitionCode,
            paperSize: rawDoc.paperSize || rawDoc.paper_size || 'A4',
            documentType: rawDoc.documentType || rawDoc.document_type || 'Document',
            notes: rawDoc.notes || '',
            date: formatDateFromIso(uploadedAt),
            uploadedAt,
            status: rawDoc.status || 'pending',
            uploadedBy: rawDoc.uploadedBy || rawDoc.uploaded_by || 'Not recorded',
            serverPath: imageUrl,
            downloadUrl: imageUrl,
            documentPath: normalizedDocumentPath,
            storedDocumentPath: rawDoc.documentPath || rawDoc.document_path || '',
            serverFileName: rawDoc.serverFileName || fileName,
            isConvertedPdf: Boolean(rawDoc.isPdfConverted || rawDoc.is_pdf_converted),
            isPdfFolder: Boolean(rawDoc.isPdfFolder),
            registry: rawDoc.registry || null
        };
    }

    function isPlaceholderUploader(value) {
        const normalized = String(value || '').trim().toLowerCase();
        return !normalized
            || normalized === 'not recorded'
            || normalized === 'system'
            || normalized === 'n/a'
            || normalized === 'na';
    }

    function buildBatchFromServerEntry(entry, index = 0) {
        if (!entry) {
            return null;
        }

        const fileNumber = entry.fileNumber || entry.file_number || `UNKNOWN-${index}`;
        const documents = Array.isArray(entry.documents)
            ? entry.documents.map(normalizeServerDocument).filter(Boolean)
            : [];
        documents.sort((a, b) => {
            const defA = Number.isFinite(Number(a.definition)) ? Number(a.definition) : 0;
            const defB = Number.isFinite(Number(b.definition)) ? Number(b.definition) : 0;
            if (defA !== defB) return defA - defB;
            const codeA = (a.definitionCode || '').toString();
            const codeB = (b.definitionCode || '').toString();
            if (codeA && codeB && codeA !== codeB) return codeA.localeCompare(codeB);
            return 0;
        });
        const primaryDocument = documents[0] || null;
        const batchId = entry.file_indexing_id
            ? `FILE-${entry.file_indexing_id}`
            : `BATCH-${fileNumber}-${index}`;

        const derivedName =
            entry.fileTitle ||
            entry.file_title ||
            primaryDocument?.fileTitle ||
            primaryDocument?.fileName ||
            'Indexed File';

        const rawDate = entry.date || primaryDocument?.uploadedAt || primaryDocument?.date || null;
        let formattedDate = formatDateFromIso(rawDate);
        if (!formattedDate && typeof rawDate === 'string') {
            formattedDate = rawDate;
        }

        const uploaderCandidates = [
            entry.uploadedBy,
            primaryDocument?.uploadedBy,
            ...(Array.isArray(documents) ? documents.map(d => d?.uploadedBy) : []),
            ...(Array.isArray(entry.documents) ? entry.documents.map(d => d?.uploadedBy || d?.uploaded_by) : []),
        ];

        const bestUploader = uploaderCandidates.find(candidate => !isPlaceholderUploader(candidate)) || 'Not recorded';

        // Derive registry from the first document that has one
        const batchRegistry = documents.find(d => d.registry)?.registry || entry.registry || null;

        return {
            id: batchId,
            fileIndexingId: entry.file_indexing_id ?? primaryDocument?.fileIndexingId ?? null,
            fileNumber,
            name: derivedName,
            documents,
            date: formattedDate || '',
            status: entry.status || primaryDocument?.status || 'Ready for page typing',
            uploadedBy: bestUploader,
            registry: batchRegistry
        };
    }

    function setUrlParam(key, value) {
        const url = new URL(window.location.href);
        if (value === undefined || value === null || value === '') {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, value);
        }
        window.history.replaceState({}, '', url);
    }

    function persistActiveTabInQuery() {
        setUrlParam('tab', state.activeTab);
    }

    function persistViewPreferenceInQuery() {
        setUrlParam('view', state.showFolderView ? 'folder' : 'list');
    }

    function persistFolderPageInQuery() {
        if (state.showFolderView) {
            setUrlParam('folder_page', state.folderPagination.currentPage);
        } else {
            setUrlParam('folder_page', '');
        }
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + " B";
        else if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + " KB";
        else return (bytes / (1024 * 1024)).toFixed(2) + " MB";
    }

    function getPaperSizeColor(size) {
        switch (size) {
            case "A4": return "bg-blue-500";
            case "A5": return "bg-green-500";
            case "A3": return "bg-purple-500";
            case "Letter": return "bg-amber-500";
            case "Legal": return "bg-rose-500";
            case "Custom": return "bg-gray-500";
            default: return "bg-gray-500";
        }
    }

    function debounce(fn, delay = 300) {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn.apply(null, args), delay);
        };
    }

    function getUploadMethodLabel(method) {
        if (!method) {
            return '';
        }

        return method === 'blind' ? 'Blind scan' : 'Direct upload';
    }

    function renderBlindScanSummaryList(container, items) {
        if (!container) {
            return;
        }

        container.innerHTML = '';

        const entries = Object.entries(items || {});
        if (!entries.length) {
            container.innerHTML = '<p class="text-xs text-gray-500">No data</p>';
            return;
        }

        entries.forEach(([label, count]) => {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between gap-2';
            row.innerHTML = `
          <span class="font-medium">${label}</span>
          <span class="text-sm text-gray-600">${count}</span>
        `;
            container.appendChild(row);
        });
    }

    function renderBlindScanTable() {
        const { tableBody } = blindScanElements;
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';

        state.blindScan.files.forEach((file) => {
            const row = document.createElement('tr');
            row.innerHTML = `
          <td class="px-4 py-2 text-sm font-medium text-gray-900">${escapeHtml(file.name || file.file_name || 'Document')}</td>
          <td class="px-4 py-2 text-xs text-gray-600">${escapeHtml(file.relative_path || '')}</td>
          <td class="px-4 py-2 text-sm text-gray-600">${escapeHtml(file.paper_size || 'A4')}</td>
          <td class="px-4 py-2 text-sm text-gray-600">${escapeHtml(file.document_type || 'Document')}</td>
          <td class="px-4 py-2 text-right text-sm text-gray-900">${formatFileSize(file.size || 0)}</td>
        `;
            tableBody.appendChild(row);
        });
    }

    async function startBlindScanTransfer(indexedFile) {
        try {
            state.uploadProgress = 25;
            updateUI();

            const stagedSnapshot = Array.isArray(state.blindScan.stagedFiles)
                ? [...state.blindScan.stagedFiles]
                : [];
            const stagedLookup = stagedSnapshot.reduce((lookup, entry) => {
                const key = (entry?.fileName || entry?.relativePath || '').toLowerCase();
                if (key && !lookup.has(key)) {
                    lookup.set(key, entry);
                }
                return lookup;
            }, new Map());

            const transferResult = await transferBlindScanDocuments();
            const documents = Array.isArray(transferResult?.documents) ? transferResult.documents : [];

            if (!documents.length) {
                throw new Error('Blind scan transfer did not return any documents.');
            }

            const normalizedDocs = documents
                .map(normalizeServerDocument)
                .filter(Boolean);

            state.lastBlindTransfer = {
                documents: normalizedDocs,
                indexedFile,
            };
            updatePdfConversionButtonVisibility();

            normalizedDocs.forEach(doc => {
                if (!doc) {
                    return;
                }

                const lookupKey = (doc.fileName || doc.originalName || '').toLowerCase();
                const stagedMatch = lookupKey ? stagedLookup.get(lookupKey) : null;

                if (stagedMatch) {
                    doc.blindStoragePath = doc.blindStoragePath || stagedMatch.blindStoragePath || null;
                    doc.relativePath = doc.relativePath || stagedMatch.relativePath || null;

                    if (!doc.documentPath && stagedMatch.targetRelativePath && indexedFile?.fileNumber) {
                        const sanitizedTarget = stagedMatch.targetRelativePath.replace(/^\/+/, '');
                        const combinedPath = `EDMS/SCAN_UPLOAD/${indexedFile.fileNumber}/${sanitizedTarget}`;
                        doc.documentPath = combinedPath.replace(/\/{2,}/g, '/');
                    }
                }
            });

            const uploadedFiles = normalizedDocs.map(doc => ({
                fileName: doc.fileName,
                fileSize: doc.fileSize,
                definition: Number.isFinite(Number(doc.definition)) ? Number(doc.definition) : ((Number.isFinite(Number(doc.displayOrder)) ? Number(doc.displayOrder) : 0) + 1),
                definitionCode: doc.definitionCode || (indexedFile?.fileNumber ? `${Number.isFinite(Number(doc.definition)) ? Number(doc.definition) : ((Number.isFinite(Number(doc.displayOrder)) ? Number(doc.displayOrder) : 0) + 1)}-${indexedFile.fileNumber}` : null),
                paperSize: doc.paperSize,
                documentType: doc.documentType,
                notes: doc.notes,
                serverFileName: doc.serverFileName,
                serverPath: doc.serverPath || doc.downloadUrl,
                downloadUrl: doc.downloadUrl,
                scanId: doc.scanId,
                status: doc.status,
                isConvertedPdf: doc.isConvertedPdf
            }));

            createBatchFromUploadedFiles(indexedFile, uploadedFiles);
            state.uploadStatus = 'complete';
            state.uploadDocuments = [];
            state.selectedUploadFiles = [];
            state.blindScan.files = [];
            state.blindScan.stagedFiles = [];
            detectFileTypes([]);
            state.selectedUploadMethod = null;
            updateUploadMethodInputs();
            updateSelectedUploadMethodLabel();
            toggleDirectUploadAvailability(false);
            elements.uploadError.classList.add('hidden');

            await loadServerData();
            showNotification('Blind scan documents uploaded successfully.');

            return normalizedDocs;
        } catch (error) {
            console.error('Blind scan upload failed:', error);
            state.uploadStatus = 'error';
            elements.uploadErrorMessage.textContent = error.message || 'Unable to move blind scan documents.';
            elements.uploadError.classList.remove('hidden');
            throw error;
        } finally {
            state.uploadProgress = 100;
            updateUI();
        }
    }

    function buildBlindScanPreviewUrl(file) {
        if (!file || !file.blind_storage_path) {
            return null;
        }
        const normalizedPath = file.blind_storage_path.replace(/^\/+/, '');
        return buildStorageUrl(normalizedPath);
    }

    function mapBlindManifestToUploadDoc(file, index = 0) {
        if (!file) {
            return null;
        }

        const previewUrl = buildBlindScanPreviewUrl(file);

        return {
            id: `BLIND-${Date.now()}-${index}`,
            fileName: file.name || `Document ${index + 1}`,
            fileSize: file.size || 0,
            definition: index + 1,
            definitionCode: state.selectedFileNumberForUpload ? `${index + 1}-${state.selectedFileNumberForUpload}` : null,
            paperSize: file.paper_size || 'A4',
            documentType: file.document_type || 'Document',
            relativePath: file.relative_path,
            targetRelativePath: file.target_relative_path,
            blindStoragePath: file.blind_storage_path,
            downloadUrl: previewUrl,
            source: 'blind',
            isBlindScan: true,
            notes: '',
            displayOrder: index
        };
    }

    function stageBlindScanFiles() {
        if (!state.blindScan.files.length) {
            alert('No blind scan documents were discovered for this file number.');
            return;
        }

        const stagedDocs = state.blindScan.files
            .map((file, index) => mapBlindManifestToUploadDoc(file, index))
            .filter(Boolean);

        if (!stagedDocs.length) {
            alert('Unable to stage blind scan documents.');
            return;
        }

        // "Next" only holds the documents — it does not hand them to the main
        // upload screen yet. That screen must stay empty until the operator
        // confirms with Load Documents, otherwise a batch they are still
        // reviewing (or about to cancel) already looks selected behind the dialog.
        state.blindScan.stagedFiles = stagedDocs;
        state.uploadStatus = 'idle';
        state.uploadProgress = 0;
        elements.uploadError.classList.add('hidden');

        closeBlindScanModal(false);
        toggleDirectUploadAvailability(true);

        // Stay in the file selector and show what was just loaded in the Preview
        // card. The operator still has to pick a File Type before confirming, and
        // that instruction is written on these very pages — closing the dialog here
        // would take them away just as they need to read it.
        showBlindScanFilesInPreview(stagedDocs);

        // updateUI() does not touch the confirm button, so re-evaluate it here —
        // the documents are loaded now, which is what it was waiting on.
        syncConfirmButtonState();

        showNotification('Blind scan documents loaded. Review them in Preview, then Load Documents.');
        updateUI();
    }

    /**
     * Hand the staged blind-scan documents to the main upload screen.
     *
     * Called only from Load Documents, so the dropzone shows a file count at the
     * same moment the dialog closes on it — never while the operator is still
     * previewing pages inside the dialog.
     */
    function commitStagedBlindScanFiles() {
        const stagedDocs = state.blindScan.stagedFiles || [];
        if (!stagedDocs.length) {
            return;
        }

        const lfDocs = state.uploadDocuments.filter(doc => doc.source === 'large-format');
        state.uploadDocuments = [...stagedDocs, ...lfDocs];
        state.selectedUploadFiles = state.uploadDocuments.map(doc => doc.file || null);
        applyDefinitionToStagedDocs();
        detectFileTypes(stagedDocs);
        updatePdfConversionButtonVisibility();
        state.uploadStatus = 'idle';
        state.uploadProgress = 0;
        elements.uploadError.classList.add('hidden');
    }

    /**
     * Render the staged blind-scan documents in the Preview card.
     *
     * Reuses the selected-file page viewer, so the pager and the full-size link
     * behave exactly as they do for a file's already-scanned pages. These previews
     * point at EDMS/BLIND_SCAN, since the documents have not been ingested yet.
     */
    function showBlindScanFilesInPreview(stagedDocs) {
        const pages = (stagedDocs || [])
            .filter(doc => doc.downloadUrl)
            .map(doc => ({
                url: doc.downloadUrl,
                filename: doc.fileName,
                is_pdf: /\.pdf$/i.test(doc.fileName || ''),
            }));

        state.selectedFilePreviewToken++;

        if (!pages.length) {
            if (elements.coverPreview && elements.coverPreviewBody) {
                elements.coverPreview.classList.remove('hidden');
                elements.coverPreviewBody.innerHTML =
                    '<p class="text-sm text-gray-500 text-center px-4">The loaded documents could not be previewed.</p>';
            }
            return;
        }

        state.selectedFilePreviewPages = pages;
        state.selectedFilePreviewIndex = 0;
        renderSelectedFilePagePreview();
    }

    function renderBlindScanModal() {
        const { modal, fileNumber, loading, error, empty, summary, total, paperSizes, documentTypes, tableWrapper, transferBtn, footnote } = blindScanElements;
        if (!modal) {
            // Silence here reads as "blind scan is broken and throws nothing".
            console.error('[scan-uploads] #blind-scan-modal is not in the DOM — the blind_scan_modal partial did not render.');
            return;
        }

        // The partial must be the standalone dialog. If a stale copy is deployed
        // (an inline <section>), toggling `hidden` renders it as a plain block at
        // the foot of the page instead of an overlay — it looks like nothing
        // happened, with no console error to explain why.
        if (!modal.classList.contains('dialog-backdrop')) {
            console.error('[scan-uploads] #blind-scan-modal is missing the .dialog-backdrop class — a stale blind_scan_modal partial is deployed. Redeploy the view and run: php artisan view:clear');
        }

        const { modalOpen, loading: isLoading, error: errorMessage, files, counts, processing } = state.blindScan;
        modal.classList.toggle('hidden', !modalOpen);
        modal.setAttribute('aria-hidden', modalOpen ? 'false' : 'true');

        if (fileNumber) {
            fileNumber.textContent = state.blindScan.fileNumber
                ? `File number: ${state.blindScan.fileNumber}`
                : 'Searching for folder...';
        }

        if (loading) {
            loading.classList.toggle('hidden', !isLoading);
        }

        if (error) {
            if (errorMessage) {
                error.textContent = errorMessage;
                error.classList.remove('hidden');
            } else {
                error.classList.add('hidden');
            }
        }

        const hasFiles = !isLoading && !errorMessage && files.length > 0;
        const isEmpty = !isLoading && !errorMessage && files.length === 0;

        if (empty) {
            empty.classList.toggle('hidden', !isEmpty);
        }

        if (summary) {
            summary.classList.toggle('hidden', !hasFiles);
        }

        if (total) {
            total.textContent = counts.total ?? files.length;
        }

        renderBlindScanSummaryList(paperSizes, counts.byPaperSize);
        renderBlindScanSummaryList(documentTypes, counts.byDocumentType);

        if (tableWrapper) {
            tableWrapper.classList.toggle('hidden', !hasFiles);
        }

        if (hasFiles) {
            renderBlindScanTable();
        }

        // Next stages the discovered files and hands them to the preview
        // card, so it only lights up once there is something to load.
        if (transferBtn) {
            transferBtn.disabled = !hasFiles || processing || isLoading;
            transferBtn.classList.add('gap-2');
            transferBtn.innerHTML = processing
                ? '<div class="loading-spinner"></div><span>Loading...</span>'
                : '<i data-lucide="arrow-right" class="h-4 w-4"></i>Next';
        }

        if (footnote) {
            footnote.textContent = processing
                ? 'Moving documents into Scan Uploads. Please wait...'
                : 'Review the files, then click Next to add them to the upload list.';
        }

        lucide.createIcons();
    }

    function renderUploadProgressModal() {
        const { modal, message, percent, bar, closeBtn } = uploadProgressElements;
        if (!modal) {
            return;
        }

        const isOpen = state.showUploadProgressModal;
        modal.classList.toggle('hidden', !isOpen);
        modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');

        if (!isOpen) {
            return;
        }

        if (message) {
            message.textContent = state.uploadProgressMessage || 'Uploading documents...';
        }

        if (percent) {
            percent.textContent = `${state.uploadProgress}%`;
        }

        if (bar) {
            bar.style.width = `${state.uploadProgress}%`;
        }

        if (closeBtn) {
            const canClose = state.uploadStatus === 'complete' || state.uploadStatus === 'error';
            closeBtn.classList.toggle('hidden', !canClose);
            closeBtn.disabled = !canClose;
        }
    }

    function openUploadProgressModal(message) {
        state.showUploadProgressModal = true;
        state.uploadProgressMessage = message || 'Uploading documents...';
        renderUploadProgressModal();
    }

    function closeUploadProgressModal() {
        state.showUploadProgressModal = false;
        renderUploadProgressModal();
    }

    async function fetchBlindScanManifest(fileNumber) {
        if (!state.blindScanDiscoverEndpoint) {
            state.blindScan.loading = false;
            state.blindScan.error = 'Blind scan discovery endpoint is not configured.';
            renderBlindScanModal();
            return;
        }

        try {
            const response = await fetch(state.blindScanDiscoverEndpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(state.csrfToken ? { 'X-CSRF-TOKEN': state.csrfToken } : {})
                },
                body: JSON.stringify({
                    file_number: fileNumber
                })
            });

            const result = await response.json();

            if (!response.ok || result.success === false) {
                throw new Error(result.message || 'Unable to discover blind scan documents.');
            }

            const payload = result.data || {};
            const files = Array.isArray(payload.files) ? payload.files : [];
            const counts = payload.counts || {};

            state.blindScan.files = files;
            state.blindScan.counts = {
                total: counts.total ?? files.length,
                byPaperSize: counts.by_paper_size || {},
                byDocumentType: counts.by_document_type || {}
            };
            state.blindScan.error = null;
        } catch (error) {
            console.error('Blind scan discovery failed:', error);
            state.blindScan.files = [];
            state.blindScan.counts = {
                total: 0,
                byPaperSize: {},
                byDocumentType: {}
            };
            state.blindScan.error = error.message || 'Unable to discover blind scan documents.';
        } finally {
            state.blindScan.loading = false;
            renderBlindScanModal();
            syncConfirmButtonState();
        }
    }

    async function transferBlindScanDocuments() {
        if (!state.blindScanTransferEndpoint) {
            throw new Error('Blind scan transfer endpoint is not configured.');
        }

        if (!state.selectedIndexedFile || !state.selectedFileNumberForUpload) {
            throw new Error('Select an indexed file before transferring documents.');
        }

        const stagedDocs = state.uploadDocuments.filter(doc => doc.source === 'blind' && doc.relativePath);
        if (!stagedDocs.length) {
            throw new Error('No blind scan documents are staged for upload.');
        }

        state.blindScan.processing = true;
        renderBlindScanModal();

        try {
            const requestBody = {
                file_number: state.selectedFileNumberForUpload,
                file_indexing_id: Number(state.selectedIndexedFile) || null,
                registry: blindScanElements.registry?.value,
                // Same master folder a direct upload would use; omitted when blank
                // so the file keeps whatever it is already classified as.
                edms_file_type: elements.scanUploadFileType?.value || null,
                files: stagedDocs.map(doc => ({
                    relative_path: doc.relativePath
                }))
            };

            const response = await fetch(state.blindScanTransferEndpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(state.csrfToken ? { 'X-CSRF-TOKEN': state.csrfToken } : {})
                },
                body: JSON.stringify(requestBody)
            });

            const result = await response.json();
            if (!response.ok || result.success === false) {
                throw new Error(result.message || 'Unable to move blind scan documents.');
            }

            return result.data || null;
        } finally {
            state.blindScan.processing = false;
            renderBlindScanModal();
        }
    }

    function toggleDirectUploadAvailability(disabled) {
        if (!elements.dropZone || !elements.fileUpload || !elements.browseFilesBtn) {
            return;
        }

        elements.fileUpload.disabled = disabled;
        elements.browseFilesBtn.disabled = disabled || !state.selectedIndexedFile;
        elements.browseFilesBtn.classList.toggle('cursor-not-allowed', disabled);
    }

    function openBlindScanModal() {
        if (!state.selectedFileNumberForUpload) {
            alert('Select an indexed file before using blind scan mode.');
            return;
        }

        state.blindScan.modalOpen = true;
        state.blindScan.loading = true;
        state.blindScan.error = null;
        state.blindScan.processing = false;
        state.blindScan.files = [];
        state.blindScan.stagedFiles = [];
        state.blindScan.counts = {
            total: 0,
            byPaperSize: {},
            byDocumentType: {}
        };
        state.blindScan.fileNumber = state.selectedFileNumberForUpload;

        renderBlindScanModal();
        syncConfirmButtonState();
        toggleDirectUploadAvailability(true);
        fetchBlindScanManifest(state.selectedFileNumberForUpload);
    }

    function closeBlindScanModal(restoreUploadAvailability = true) {
        state.blindScan.modalOpen = false;
        state.blindScan.processing = false;
        renderBlindScanModal();
        if (restoreUploadAvailability) {
            toggleDirectUploadAvailability(false);
        }
    }

    function updateUploadMethodInputs() {
        if (!elements.uploadMethodRadios || elements.uploadMethodRadios.length === 0) {
            return;
        }

        elements.uploadMethodRadios.forEach(radio => {
            const isSelected = state.selectedUploadMethod === radio.value;
            radio.checked = isSelected;
            const label = radio.closest('label');
            if (label) {
                label.classList.toggle('scan-source-selected', isSelected);
            }
        });
    }

    function syncConfirmButtonState(forceDisable = false) {
        const lfBrowseButton = document.getElementById('browse-large-format-scans');
        if (lfBrowseButton) {
            lfBrowseButton.disabled = forceDisable || !state.selectedIndexedFile || state.uploadStatus === 'uploading';
        }
        if (!elements.confirmFileSelectBtn) {
            return;
        }

        if (forceDisable) {
            elements.confirmFileSelectBtn.disabled = true;
            return;
        }

        const hasFile = Boolean(state.selectedIndexedFile);
        const hasMethod = Boolean(state.selectedUploadMethod);
        const hasRegistry = blindScanElements.registry ? Boolean(blindScanElements.registry.value) : true;
        const blindScanReady = state.selectedUploadMethod !== 'blind'
            || (!state.blindScan.loading && !state.blindScan.error && state.blindScan.files.length > 0);

        elements.confirmFileSelectBtn.disabled = !(hasFile && hasMethod && hasRegistry && blindScanReady);
    }

    function updateSelectedUploadMethodLabel() {
        if (!elements.selectedUploadMethodLabel) {
            return;
        }

        if (state.selectedIndexedFile && state.selectedUploadMethod) {
            elements.selectedUploadMethodLabel.textContent = `Upload method: ${getUploadMethodLabel(state.selectedUploadMethod)}`;
            elements.selectedUploadMethodLabel.classList.remove('hidden');
        } else {
            elements.selectedUploadMethodLabel.classList.add('hidden');
        }
    }

    function findIndexedFileById(id) {
        if (!id) {
            return null;
        }
        return indexedFiles.find(file => String(file.id) === String(id)) || null;
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function documentUploadActionsMenu(batch) {
        const batchId = escapeHtml(batch?.id || '');
        const fileNumber = escapeHtml(batch?.fileNumber || '');

        return `
          <details class="relative document-upload-actions-menu">
            <summary class="btn btn-outline btn-xs btn-icon cursor-pointer list-none" title="File actions" aria-label="File actions">
              <i data-lucide="ellipsis" class="h-4 w-4"></i>
            </summary>
            <div class="document-upload-actions-popover fixed z-[10000] w-56 overflow-hidden rounded-md border bg-white py-1 text-gray-700 shadow-lg"
                 style="visibility: hidden;">
              <button type="button" class="preview-batch flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-100"
                      data-id="${batchId}" data-doc-index="0">
                <i data-lucide="eye" class="h-4 w-4 text-blue-600"></i>
                Preview
              </button>
              <button type="button" class="start-typing flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-100"
                      data-id="${batchId}">
                <i data-lucide="type" class="h-4 w-4 text-blue-600"></i>
                Page Typing
              </button>
              <button type="button" class="upload-more-to-folder flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-100"
                      data-file-number="${fileNumber}">
                <i data-lucide="upload" class="h-4 w-4 text-blue-600"></i>
                Upload More
              </button>
              <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-100" data-lf-batch="${batchId}">Replace with LF Scan</button>
              <div class="my-1 border-t border-gray-100"></div>
              <button type="button" class="move-upload-to-registry flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-100"
                      data-batch-id="${batchId}">
                <i data-lucide="folder-symlink" class="h-4 w-4 text-blue-600"></i>
                Move to NR
              </button>
              <button type="button" class="file-upload-into-master flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-100"
                      data-batch-id="${batchId}">
                <i data-lucide="folder-tree" class="h-4 w-4 text-blue-600"></i>
                File into Master Folder
              </button>
              <div class="my-1 border-t border-gray-100"></div>
              <button type="button" class="reassign-upload-file-number flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-amber-700 hover:bg-amber-50"
                      data-batch-id="${batchId}">
                <i data-lucide="unlink" class="h-4 w-4 text-amber-600"></i>
                Reassign File Number
              </button>
            </div>
          </details>
        `;
    }

    function positionDocumentUploadActionsMenu(details) {
        if (!details?.open) return;

        const trigger = details.querySelector('summary');
        const menu = details.querySelector('.document-upload-actions-popover');
        if (!trigger || !menu) return;

        const triggerRect = trigger.getBoundingClientRect();
        const viewportPadding = 8;
        const gap = 4;
        const menuWidth = menu.offsetWidth || 224;
        const menuHeight = menu.offsetHeight || 240;

        const maximumLeft = Math.max(viewportPadding, window.innerWidth - menuWidth - viewportPadding);
        const left = Math.min(
            Math.max(viewportPadding, triggerRect.right - menuWidth),
            maximumLeft
        );

        const spaceBelow = window.innerHeight - triggerRect.bottom - viewportPadding;
        const top = spaceBelow >= menuHeight
            ? triggerRect.bottom + gap
            : Math.max(viewportPadding, triggerRect.top - menuHeight - gap);

        menu.style.left = `${left}px`;
        menu.style.top = `${top}px`;
        menu.style.visibility = 'visible';
    }

    /**
     * Unlink this upload from its file number and attach it to another.
     *
     * For the case the folder actions cannot fix: the documents were uploaded
     * against the WRONG FILE ENTIRELY. Every scan in the batch moves to the target
     * file — and therefore into that file's registry and master folder — with its
     * typed pages and Doc-WARE copies following, all audited in
     * scan_reassignment_logs.
     *
     * Reuses the existing reassignment dialog and its /scan-uploads/reassign
     * endpoints; this is only a new way in, scoped to a whole batch rather than a
     * single page.
     */
    function openDocumentUploadReassign(button) {
        const batch = state.documentBatches.find(item => String(item.id) === String(button.dataset.batchId));
        button.closest('details')?.removeAttribute('open');

        if (!batch) {
            alert('Unable to reassign: this upload could not be found.');
            return;
        }

        // Only saved scans can be reassigned — a document still staged in the
        // browser has no scannings row to move.
        const documents = (batch.documents || []).filter(doc => Number(doc.scanId ?? doc.id) > 0);
        const scanIds = documents.map(doc => Number(doc.scanId ?? doc.id));

        if (!scanIds.length) {
            alert('Unable to reassign: no saved documents were found for this file.');
            return;
        }

        if (!window.scanReassignmentManager) {
            alert('The reassignment dialog is not available on this page.');
            return;
        }

        window.scanReassignmentManager.openModal(scanIds, documents.map(doc => ({
            id: doc.scanId ?? doc.id,
            fileName: doc.fileName,
            fileNumber: batch.fileNumber,
            paperSize: doc.paperSize,
            documentType: doc.documentType,
            registry: doc.registry || batch.registry || null,
        })));
    }

    function openDocumentUploadEdmsAction(button) {
        const batch = state.documentBatches.find(item => String(item.id) === String(button.dataset.batchId));
        const fileIndexingId = resolveFileIndexingIdFromBatch(batch);

        if (!fileIndexingId) {
            alert('Unable to open file actions: file reference is missing.');
            return;
        }

        const dialog = button.classList.contains('move-upload-to-registry')
            ? window.EdmsRegistryTransfer
            : window.EdmsFileType;

        button.closest('details')?.removeAttribute('open');
        dialog.open(fileIndexingId, batch.fileNumber, () => window.location.reload());
    }

    // File to URL conversion
    function fileToUrl(file) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.readAsDataURL(file);
        });
    }

    // Store file preview URLs
    async function storeFilePreview(file, key) {
        if (!state.filePreviews.has(key)) {
            const url = await fileToUrl(file);
            state.filePreviews.set(key, url);
        }
        return state.filePreviews.get(key);
    }

    // Debug function to check file paths
    function debugDeletePaths() {
        console.log('=== DELETE PATH DEBUG ===');
        state.documentBatches.forEach((batch, batchIndex) => {
            console.log(`Batch ${batchIndex}: ${batch.fileNumber}`);
            batch.documents.forEach((doc, docIndex) => {
                console.log(`  Document ${docIndex}:`, {
                    fileName: doc.fileName,
                    serverPath: doc.serverPath,
                    hasServerPath: !!doc.serverPath
                });
            });
        });
    }

    // Test server configuration
    async function testServerConfig() {
        const debugUrl = state.debugEndpoint || (state.uploadEndpoint ? `${state.uploadEndpoint}?action=debug` : null);
        if (!debugUrl) {
            console.warn('Debug endpoint is not configured for Scan Uploads. Skipping diagnostics.');
            return;
        }

        try {
            const response = await fetch(debugUrl, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            if (!response.ok) {
                throw new Error(`Debug endpoint responded with ${response.status}`);
            }
            const result = await response.json();
            console.log('=== SERVER CONFIG DEBUG ===', result);
            return result;
        } catch (error) {
            console.error('Failed to test server config:', error);
        }
    }

    // PDF Conversion Functions
    function detectFileTypes(files = []) {
        const items = Array.isArray(files) ? files : [];
        state.hasPdfFiles = false;
        let pdfCount = 0;

        for (const file of items) {
            if (!file) {
                continue;
            }

            const candidateName = String(
                file.name ||
                file.fileName ||
                file.originalName ||
                file.original_filename ||
                (file.file ? file.file.name : '') ||
                ''
            ).toLowerCase();

            if (candidateName.endsWith('.pdf')) {
                state.hasPdfFiles = true;
                pdfCount++;
            }
        }

        if (!elements.pdfConversionText || !elements.autoConvertBadge) {
            return;
        }

        if (state.hasPdfFiles && pdfCount > 0) {
            if (state.pdfConversionEnabled) {
                elements.pdfConversionText.textContent = `Found ${pdfCount} PDF file(s). PDF conversion activated.`;
                elements.autoConvertBadge.classList.remove('hidden');
            } else {
                elements.pdfConversionText.textContent = `Found ${pdfCount} PDF file(s). Enable conversion to convert to images.`;
                elements.autoConvertBadge.classList.add('hidden');
            }
        } else {
            elements.pdfConversionText.textContent = 'No PDF files detected';
            elements.autoConvertBadge.classList.add('hidden');
        }

        updatePdfConversionButtonVisibility();
    }

    function hasPdfDocuments(documents = []) {
        return (documents || []).some(doc => {
            const name = (doc?.fileName || doc?.originalName || doc?.file?.name || '').toLowerCase();
            const path = (doc?.documentPath || doc?.downloadUrl || doc?.serverPath || '').toLowerCase();
            return name.endsWith('.pdf') || path.endsWith('.pdf');
        });
    }

    function updatePdfConversionButtonVisibility() {
        if (!elements.convertPdfsBtn) {
            return;
        }

        const hasRecentTransferPdfs = hasPdfDocuments(state.lastBlindTransfer.documents);
        const shouldShow = state.hasPdfFiles || hasRecentTransferPdfs;

        elements.convertPdfsBtn.classList.toggle('hidden', !shouldShow);
        elements.convertPdfsBtn.disabled = !shouldShow;
    }

    function isValidUploadFile(candidate) {
        if (!candidate || typeof candidate !== 'object') {
            return false;
        }

        if (typeof File !== 'undefined' && candidate instanceof File) {
            return true;
        }

        const hasName = typeof candidate.name === 'string' && candidate.name.length > 0;
        const hasSize = typeof candidate.size === 'number' || typeof candidate.size === 'undefined';
        return hasName && hasSize;
    }

    function normalizeFileInputCollection(collection) {
        if (!collection) {
            return [];
        }

        let list = [];

        if (typeof FileList !== 'undefined' && collection instanceof FileList) {
            list = Array.from(collection);
        } else if (Array.isArray(collection)) {
            list = collection.slice();
        } else if (typeof collection.length === 'number') {
            try {
                list = Array.from(collection);
            } catch (error) {
                console.warn('Unable to normalize file input collection.', error);
                list = [];
            }
        } else {
            list = [collection];
        }

        return list.filter(isValidUploadFile);
    }

    function getDirectUploadFiles() {
        const inputFiles = normalizeFileInputCollection(elements.fileUpload?.files);
        if (inputFiles.length) {
            return inputFiles;
        }
        return normalizeFileInputCollection(state.selectedUploadFiles);
    }

    function normalizeBlindDocForConversion(doc, index = 0) {
        if (!doc) {
            return null;
        }

        const derivedName =
            doc.fileName ||
            doc.name ||
            doc.originalName ||
            doc.original_filename ||
            doc.file?.name ||
            `Document ${index + 1}`;

        const rawDocumentPath =
            doc.documentPath ||
            doc.document_path ||
            doc.targetRelativePath ||
            doc.relativePath ||
            doc.blindStoragePath ||
            doc.blind_storage_path ||
            '';

        const sanitizedDocumentPath = stripStorageAppPublicPrefix(rawDocumentPath);
        const blindStoragePath = stripStorageAppPublicPrefix(
            doc.blindStoragePath || doc.blind_storage_path || rawDocumentPath
        );

        const resolvedDownloadUrl =
            doc.downloadUrl ||
            doc.serverPath ||
            doc.server_path ||
            doc.previewUrl ||
            doc.blindStoragePath ||
            doc.blind_storage_path ||
            sanitizedDocumentPath;

        return {
            fileName: derivedName,
            originalName: derivedName,
            documentPath: sanitizedDocumentPath,
            blindStoragePath,
            downloadUrl: resolveStorageUrl(resolvedDownloadUrl || blindStoragePath || sanitizedDocumentPath),
            paperSize: doc.paperSize || 'A4',
            documentType: doc.documentType || 'Document',
            scanId: doc.scanId || doc.id || null,
        };
    }

    function getStagedBlindPdfDocs() {
        const stagedDocs = (state.uploadDocuments || []).filter(doc => doc && (doc.source === 'blind' || doc.isBlindScan));
        if (!stagedDocs.length) {
            return { documents: [], indexedFile: null };
        }

        const indexedFile = findIndexedFileById(state.selectedIndexedFile);
        if (!indexedFile) {
            return { documents: [], indexedFile: null };
        }

        const pdfDocs = stagedDocs.filter(doc => {
            const name = (
                doc.fileName ||
                doc.name ||
                doc.originalName ||
                doc.original_filename ||
                doc.file?.name ||
                ''
            ).toLowerCase();
            return name.endsWith('.pdf');
        });

        if (!pdfDocs.length) {
            return { documents: [], indexedFile };
        }

        const normalizedDocs = pdfDocs
            .map((doc, index) => normalizeBlindDocForConversion(doc, index))
            .filter(Boolean);

        return { documents: normalizedDocs, indexedFile };
    }

    async function convertStagedBlindPdfsClientSide(documents = [], indexedFile = null) {
        if (!documents.length || !indexedFile) {
            return false;
        }

        if (elements.pdfConversionModal) {
            elements.pdfConversionProgressPercent.textContent = '0%';
            elements.pdfConversionProgressBar.style.width = '0%';
            elements.pdfConversionCurrentFile.textContent = 'Preparing PDF conversion...';
            elements.pdfConversionModal.classList.remove('hidden');
        }

        const convertedDocs = [];

        for (let i = 0; i < documents.length; i++) {
            const doc = documents[i];
            const pdfName = doc.fileName || doc.originalName || `document_${i + 1}.pdf`;
            const sourceUrl = doc.downloadUrl || doc.blindStoragePath || doc.documentPath;

            if (!sourceUrl) {
                console.warn(`Unable to resolve URL for ${pdfName}. Skipping.`);
                continue;
            }

            const progress = Math.round((i / documents.length) * 100);
            updatePdfConversionProgress(progress, `Converting: ${pdfName}`);

            const pdfFile = await fetchFileFromUrl(sourceUrl, pdfName);
            const images = await convertPdfInBrowser(pdfFile);

            images.forEach((image, pageIndex) => {
                convertedDocs.push({
                    file: image.file,
                    paperSize: doc.paperSize || 'A4',
                    documentType: doc.documentType || 'Document',
                    isConvertedPdf: true,
                    source: 'blind-converted',
                    parentPdf: pdfName,
                    pageNumber: image.page || pageIndex + 1,
                    displayOrder: convertedDocs.length,
                });
            });
        }

        if (!convertedDocs.length) {
            throw new Error('No images were produced during conversion.');
        }

        state.uploadDocuments = convertedDocs.map((doc, index) => ({
            ...doc,
            displayOrder: index,
        }));
        state.selectedUploadFiles = convertedDocs.map(doc => doc.file);
        state.blindScan.stagedFiles = [];
        detectFileTypes(state.selectedUploadFiles);
        await pregenerateImagePreviews();
        updateUI();
        showNotification('Blind scan PDFs converted to images. Ready to upload.');

        if (elements.pdfConversionModal) {
            updatePdfConversionProgress(100, 'PDF conversion complete.');
            setTimeout(() => elements.pdfConversionModal.classList.add('hidden'), 400);
        }

        return true;
    }

    function hasLocalBlindConvertedUploads() {
        if (state.selectedUploadMethod !== 'blind') {
            return false;
        }
        return (state.uploadDocuments || []).some(doc => {
            if (!doc || !doc.file) {
                return false;
            }
            if (typeof File !== 'undefined' && doc.file instanceof File) {
                return true;
            }
            return Boolean(doc.file && typeof doc.file === 'object' && typeof doc.file.size === 'number');
        });
    }

    function normalizeBlindDocForConversion(doc, index = 0) {
        if (!doc) {
            return null;
        }

        const derivedName =
            doc.fileName ||
            doc.name ||
            doc.originalName ||
            doc.original_filename ||
            doc.file?.name ||
            `Document ${index + 1}`;

        const rawDocumentPath =
            doc.documentPath ||
            doc.document_path ||
            doc.targetRelativePath ||
            doc.relativePath ||
            doc.blindStoragePath ||
            doc.blind_storage_path ||
            '';

        const sanitizedDocumentPath = stripStorageAppPublicPrefix(rawDocumentPath);
        const blindStoragePath = stripStorageAppPublicPrefix(
            doc.blindStoragePath || doc.blind_storage_path || rawDocumentPath
        );

        const resolvedDownloadUrl =
            doc.downloadUrl ||
            doc.serverPath ||
            doc.server_path ||
            doc.previewUrl ||
            doc.blindStoragePath ||
            doc.blind_storage_path ||
            sanitizedDocumentPath;

        return {
            fileName: derivedName,
            originalName: derivedName,
            documentPath: sanitizedDocumentPath,
            blindStoragePath,
            downloadUrl: resolveStorageUrl(resolvedDownloadUrl || blindStoragePath || sanitizedDocumentPath),
            paperSize: doc.paperSize || 'A4',
            documentType: doc.documentType || 'Document',
            scanId: doc.scanId || doc.id || null,
        };
    }

    function getStagedBlindPdfDocs() {
        const stagedDocs = (state.uploadDocuments || []).filter(doc => doc && (doc.source === 'blind' || doc.isBlindScan));
        if (!stagedDocs.length) {
            return { documents: [], indexedFile: null };
        }

        const indexedFile = findIndexedFileById(state.selectedIndexedFile);
        if (!indexedFile) {
            return { documents: [], indexedFile: null };
        }

        const pdfDocs = stagedDocs.filter(doc => {
            const name = (
                doc.fileName ||
                doc.name ||
                doc.originalName ||
                doc.original_filename ||
                doc.file?.name ||
                ''
            ).toLowerCase();
            return name.endsWith('.pdf');
        });

        if (!pdfDocs.length) {
            return { documents: [], indexedFile };
        }

        const normalizedDocs = pdfDocs
            .map((doc, index) => normalizeBlindDocForConversion(doc, index))
            .filter(Boolean);

        return { documents: normalizedDocs, indexedFile };
    }

    async function handleManualPdfConversionClick() {
        if (!state.pdfConversionEnabled) {
            if (elements.convertPdfs) {
                elements.convertPdfs.checked = true;
            }
            state.pdfConversionEnabled = true;
        }

        try {
            const directFiles = getDirectUploadFiles();
            if (directFiles.length) {
                await handleFileSelect({ target: { files: directFiles } });
                showNotification('PDF conversion complete for selected files.');
                return;
            }

            const { documents: stagedBlindDocs, indexedFile: stagedIndexedFile } = getStagedBlindPdfDocs();
            if (stagedBlindDocs.length && stagedIndexedFile) {
                await convertStagedBlindPdfsClientSide(stagedBlindDocs, stagedIndexedFile);
                return;
            }

            const blindDocs = Array.isArray(state.lastBlindTransfer.documents)
                ? state.lastBlindTransfer.documents
                : [];
            const blindIndexedFile = state.lastBlindTransfer.indexedFile;
            const hasBlindTransferDocs = blindDocs.length > 0 && Boolean(blindIndexedFile);

            if (hasBlindTransferDocs) {
                await convertTransferredPdfsToImages(blindDocs, blindIndexedFile);
                return;
            }

            if (state.selectedUploadMethod === 'blind') {
                alert('Move blind scan documents into Scan Uploads first, then try converting again.');
                return;
            }

            alert('Select PDF files to convert first.');
        } catch (error) {
            console.error('Manual PDF conversion failed:', error);
            alert(error.message || 'Unable to convert PDFs. Please try again.');
        }
    }

    async function convertPdfInBrowser(pdfFile) {
        const arrayBuffer = await pdfFile.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        const images = [];

        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const viewport = page.getViewport({ scale: 2.0 });

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = viewport.width;
            canvas.height = viewport.height;

            await page.render({
                canvasContext: ctx,
                viewport: viewport
            }).promise;

            const blob = await new Promise(resolve => {
                canvas.toBlob(resolve, 'image/jpeg', 0.85);
            });

            const imageFile = new File([blob], `page_${i}.jpg`, {
                type: 'image/jpeg',
                lastModified: pdfFile.lastModified
            });

            images.push({
                file: imageFile,
                page: i,
                width: canvas.width,
                height: canvas.height,
                fileName: `page_${i}.jpg`
            });
        }

        return images;
    }

    async function processPdfFilesForUpload(files) {
        const processedFiles = [];
        state.pdfConversionResults = { converted: 0, failed: 0, total: 0, failedFiles: [] };

        // Find all PDF files
        const pdfFiles = files.filter(file => file.name.toLowerCase().endsWith('.pdf'));
        state.pdfConversionResults.total = pdfFiles.length;

        if (pdfFiles.length === 0) {
            // If no PDFs, just return all files as-is
            return files.map(file => ({ file, type: 'file' }));
        }

        // Show PDF conversion modal
        if (elements.pdfConversionModal) elements.pdfConversionModal.classList.remove("hidden");

        for (let i = 0; i < pdfFiles.length; i++) {
            const pdfFile = pdfFiles[i];

            // Update progress
            const progress = (i / pdfFiles.length) * 100;
            updatePdfConversionProgress(progress, `Converting: ${pdfFile.name}`);

            try {
                const images = await convertPdfInBrowser(pdfFile);

                // Create a folder structure for this PDF
                const pdfFolderName = pdfFile.name.replace(/\.pdf$/i, '');
                const pdfFolder = {
                    id: `FOLDER-${Date.now()}-${i}`,
                    name: pdfFolderName,
                    type: 'folder',
                    status: 'Ready for page typing',
                    date: new Date().toLocaleDateString(),
                    children: [],
                    isPdfFolder: true,
                    originalPdfFile: pdfFile
                };

                // Add each page as a separate file within the folder
                for (let j = 0; j < images.length; j++) {
                    const image = images[j];

                    const fileEntry = {
                        id: `UPLOAD-${Date.now()}-${i}-${j}`,
                        name: image.fileName,
                        size: formatFileSize(image.file.size),
                        type: 'image/jpeg',
                        status: 'Ready for page typing',
                        date: new Date().toLocaleDateString(),
                        file: image.file,
                        isConvertedPdf: true,
                        parentFolder: pdfFolder.id,
                        pageNumber: j + 1
                    };

                    pdfFolder.children.push(fileEntry);
                }

                processedFiles.push(pdfFolder);
                state.pdfConversionResults.converted++;
                console.log(`Converted ${pdfFile.name} to ${images.length} pages in folder ${pdfFolderName}`);

            } catch (error) {
                state.pdfConversionResults.failed++;
                state.pdfConversionResults.failedFiles.push(pdfFile.name);
                console.error(`Failed to convert ${pdfFile.name}:`, error);
                // Add original PDF if conversion fails
                processedFiles.push({
                    file: pdfFile,
                    type: 'file',
                    isConvertedPdf: false
                });
            }
        }

        // Add all non-PDF files
        const nonPdfFiles = files.filter(file => !file.name.toLowerCase().endsWith('.pdf'));
        nonPdfFiles.forEach((file, index) => {
            processedFiles.push({
                file: file,
                type: 'file',
                isConvertedPdf: false
            });
        });

        // Hide conversion modal
        if (elements.pdfConversionModal) elements.pdfConversionModal.classList.add("hidden");

        return processedFiles;
    }

    async function fetchFileFromUrl(url, fallbackName = 'document.pdf', fallbackType = 'application/pdf') {
        if (!url) {
            throw new Error('Missing file URL for conversion.');
        }

        const absoluteUrl = toAbsoluteUrl(url);
        const response = await fetch(absoluteUrl, {
            credentials: 'same-origin',
            cache: 'no-store'
        });
        if (!response.ok) {
            throw new Error(`Unable to fetch file for conversion (HTTP ${response.status}).`);
        }

        const blob = await response.blob();
        if (!blob || blob.size <= 0) {
            throw new Error(`Fetched file is empty from ${absoluteUrl}.`);
        }

        if ((blob.type || '').includes('text/html')) {
            throw new Error(`Fetched HTML instead of PDF from ${absoluteUrl}.`);
        }

        const filename = fallbackName || 'document.pdf';
        const mimeType = blob.type || fallbackType || 'application/pdf';

        return new File([blob], filename, {
            type: mimeType,
            lastModified: Date.now()
        });
    }

    function buildScanDownloadUrl(scanId) {
        if (!scanId || !state.downloadEndpointTemplate) {
            return null;
        }
        return state.downloadEndpointTemplate.replace('ID', scanId);
    }

    async function fetchPdfForConversion(doc, pdfName, primaryUrl, blindFallbackUrl) {
        const candidates = [primaryUrl, blindFallbackUrl].filter(Boolean);
        let lastError = null;

        for (const candidateUrl of candidates) {
            try {
                return await fetchFileFromUrl(candidateUrl, pdfName);
            } catch (error) {
                lastError = error;
                console.warn(`PDF fetch failed from ${candidateUrl}:`, error?.message || error);
            }
        }

        const scanId = doc?.scanId || doc?.id || null;
        const downloadUrl = buildScanDownloadUrl(scanId);
        if (downloadUrl) {
            try {
                console.info(`Trying scan download fallback endpoint for scan ${scanId}.`);
                return await fetchFileFromUrl(downloadUrl, pdfName);
            } catch (error) {
                lastError = error;
                console.warn(`Scan download fallback failed for scan ${scanId}:`, error?.message || error);
            }
        }

        throw lastError || new Error('Unable to fetch PDF for conversion.');
    }

    async function deleteDocumentSilently(scanId) {
        if (!scanId || !state.deleteEndpointTemplate) {
            return false;
        }

        const deleteUrl = state.deleteEndpointTemplate.replace('ID', scanId);
        const headers = {
            'Accept': 'application/json',
        };

        if (state.csrfToken) {
            headers['X-CSRF-TOKEN'] = state.csrfToken;
        }

        const response = await fetch(deleteUrl, {
            method: 'DELETE',
            headers,
        });

        if (!response.ok) {
            console.warn(`Failed to delete PDF scan ${scanId}: HTTP ${response.status}`);
            return false;
        }

        try {
            const result = await response.json();
            if (result?.success === false) {
                console.warn(`Delete API reported failure for scan ${scanId}:`, result?.message);
                return false;
            }
        } catch (error) {
            console.warn('Delete API returned non-JSON payload.', error);
        }

        return true;
    }

    async function convertTransferredPdfsToImages(documents = [], indexedFile = null) {
        if (!state.pdfConversionEnabled) {
            console.info('PDF conversion disabled. Skipping blind scan conversion workflow.');
            return;
        }

        if (!documents.length || !indexedFile) {
            return;
        }

        const pdfDocs = documents.filter(doc => {
            const name = (doc.fileName || doc.originalName || '').toLowerCase();
            const path = (doc.documentPath || '').toLowerCase();
            return name.endsWith('.pdf') || path.endsWith('.pdf');
        });

        if (!pdfDocs.length) {
            return;
        }

        console.info(`Starting blind scan PDF conversion for ${pdfDocs.length} document(s).`);

        if (elements.pdfConversionModal) {
            elements.pdfConversionProgressPercent.textContent = '0%';
            elements.pdfConversionProgressBar.style.width = '0%';
            elements.pdfConversionCurrentFile.textContent = 'Preparing PDF conversion...';
            elements.pdfConversionModal.classList.remove('hidden');
        }

        for (let i = 0; i < pdfDocs.length; i++) {
            const doc = pdfDocs[i];
            const pdfName = doc.fileName || doc.originalName || `document_${i + 1}.pdf`;
            const normalizedDocumentPath = stripStorageAppPublicPrefix(doc.documentPath || '');

            let primaryUrl =
                resolveStorageUrl(doc.downloadUrl || doc.serverPath || doc.server_path || normalizedDocumentPath) ||
                resolveStorageUrl(normalizedDocumentPath);

            let blindFallbackUrl = resolveStorageUrl(doc.blindStoragePath);

            if (!primaryUrl && blindFallbackUrl) {
                primaryUrl = blindFallbackUrl;
            }

            try {
                const progress = Math.round((i / pdfDocs.length) * 100);
                updatePdfConversionProgress(progress, `Converting: ${pdfName}`);

                if (!primaryUrl) {
                    console.warn(`No accessible URL found for ${pdfName}. Skipping conversion.`);
                    continue;
                }

                const pdfFile = await fetchPdfForConversion(doc, pdfName, primaryUrl, blindFallbackUrl);

                const convertedPages = await convertPdfInBrowser(pdfFile);

                if (!convertedPages.length) {
                    console.warn('No pages produced during conversion for', pdfName);
                    continue;
                }

                const uploads = [];
                for (const page of convertedPages) {
                    const uploadDoc = {
                        file: page.file,
                        paperSize: doc.paperSize || 'A4',
                        documentType: doc.documentType || 'Document',
                        isConvertedPdf: true,
                    };

                    try {
                        await uploadFileToServer(uploadDoc, indexedFile);
                        uploads.push(page.file.name);
                    } catch (uploadError) {
                        console.error(`Failed to upload converted page ${page.file.name}:`, uploadError);
                    }
                }

                if (uploads.length) {
                    console.info(`Converted ${pdfName} into ${uploads.length} image(s).`);
                    await deleteDocumentSilently(doc.scanId || doc.id);
                }
            } catch (error) {
                console.error('Blind scan PDF conversion failed:', error);
            }
        }

        if (elements.pdfConversionModal) {
            updatePdfConversionProgress(100, 'PDF conversion complete.');
            setTimeout(() => {
                elements.pdfConversionModal.classList.add('hidden');
            }, 500);
        }

        await loadServerData();
        showNotification('Blind scan PDFs converted to images successfully.');
    }

    function updatePdfConversionProgress(progress, text = '') {
        if (elements.pdfConversionProgressPercent) elements.pdfConversionProgressPercent.textContent = `${Math.round(progress)}%`;
        if (elements.pdfConversionProgressBar) elements.pdfConversionProgressBar.style.width = `${progress}%`;
        if (elements.pdfConversionCurrentFile && text) elements.pdfConversionCurrentFile.textContent = text;
    }

    // Server Upload Functions
    async function uploadFileToServer(doc, indexedFile, displayOrder = null) {
        if (!doc?.file) {
            throw new Error('Upload document is missing the file payload.');
        }

        if (!indexedFile) {
            throw new Error('Indexed file metadata is required for uploads.');
        }

        if (!state.uploadEndpoint) {
            throw new Error('Upload endpoint is not configured.');
        }

        const registryValue = elements.blindScanRegistry?.value || blindScanElements.registry?.value || '';
        if (!registryValue) {
            throw new Error('Select a registry before uploading.');
        }

        const formData = new FormData();
        formData.append('file', doc.file);
        formData.append('file_number', indexedFile.fileNumber);

        // Only send file_indexing_id when it's a real integer DB id
        // (global selector sets id to "GLOBAL_<fileNumber>" which isn't valid)
        const rawId = indexedFile.id;
        const numericId = Number(rawId);
        if (Number.isInteger(numericId) && numericId > 0) {
            formData.append('file_indexing_id', numericId);
        }
        formData.append('paper_size', doc.paperSize || 'A4');
        formData.append('document_type', doc.documentType || 'Document');
        const computedDisplayOrder = Number.isFinite(displayOrder) ? displayOrder : (doc.displayOrder ?? 0);
        const computedDefinition = Number(computedDisplayOrder) + 1;
        formData.append('definition', computedDefinition);
        formData.append('original_filename', doc.file?.name || doc.fileName || 'document');
        formData.append('display_order', computedDisplayOrder);
        formData.append('registry', registryValue);

        // The EDMS master folder these scans go into. Blank is a valid answer —
        // the file then stays directly under its registry until someone classifies
        // it — so the field is only sent when the operator actually picked one.
        const fileTypeValue = elements.scanUploadFileType?.value || '';
        if (fileTypeValue) {
            formData.append('edms_file_type', fileTypeValue);
        }

        if (doc.notes) {
            formData.append('notes', doc.notes);
        }

        if (doc.isConvertedPdf) {
            formData.append('is_pdf_converted', doc.isConvertedPdf ? '1' : '0');
        }

        const headers = {
            'Accept': 'application/json'
        };
        if (state.csrfToken) {
            headers['X-CSRF-TOKEN'] = state.csrfToken;
        }

        try {
            // console.log('=== UPLOAD ATTEMPT ===');
            // console.log('Uploading to:', state.uploadEndpoint);
            // console.log('File details:', {
            //     name: doc.file.name,
            //     size: doc.file.size,
            //     type: doc.file.type,
            //     lastModified: doc.file.lastModified
            // });
            // console.log('File number:', indexedFile.fileNumber);

            const response = await fetch(state.uploadEndpoint, {
                method: 'POST',
                headers,
                body: formData,
            });

            // console.log('=== SERVER RESPONSE ===');
            // console.log('Status:', response.status, response.statusText);

            const responseText = await response.text();
            // console.log('Raw response:', responseText);

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response:', parseError);
                throw new Error(`Server returned invalid JSON: ${responseText.substring(0, 200)}`);
            }

            // console.log('Parsed result:', result);

            const errorDetails = result?.errors
                ? Object.values(result.errors).flat().join('; ')
                : '';

            if (!response.ok) {
                const message = result.message || 'Upload failed';
                throw new Error(`HTTP ${response.status}: ${message}${errorDetails ? ' - ' + errorDetails : ''}`);
            }

            if (!result.success) {
                throw new Error(result.message || errorDetails || 'Upload failed');
            }

            // console.log('UPLOAD SUCCESS:', result.message);
            return result.data || null;
        } catch (error) {
            console.error('UPLOAD FAILED:', {
                message: error.message,
                fileName: doc.file.name,
                fileSize: doc.file.size,
                fileType: doc.file.type,
                stack: error.stack
            });
            throw error;
        }
    }

    // Helper function to create batch from uploaded files
    function createBatchFromUploadedFiles(indexedFile, uploadedFiles) {
        if (uploadedFiles.length === 0) return;

        const batchDocuments = [];

        // Group PDF pages by folder
        const pdfFolders = {};

        uploadedFiles.forEach(doc => {
            if (doc.isPdfFolder) {
                const folderKey = doc.folderName;
                if (!pdfFolders[folderKey]) {
                    pdfFolders[folderKey] = {
                        id: `FOLDER-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
                        fileName: doc.folderName + '.pdf',
                        fileSize: (doc.file?.size || doc.fileSize || 0) * doc.pageCount,
                        paperSize: doc.paperSize || 'A4',
                        documentType: doc.documentType || 'PDF Document',
                        isPdfFolder: true,
                        folderName: doc.folderName,
                        pageCount: doc.pageCount,
                        date: new Date().toLocaleDateString(),
                        pages: [],
                        downloadUrl: doc.downloadUrl || doc.serverPath || null
                    };
                }
                pdfFolders[folderKey].pages.push({
                    file: doc.file,
                    fileName: doc.file?.name || doc.fileName || `Page ${pdfFolders[folderKey].pages.length + 1}`,
                    fileSize: doc.file?.size || doc.fileSize || 0,
                    paperSize: doc.paperSize || 'A4',
                    documentType: doc.documentType || 'Certificate',
                    pageNumber: pdfFolders[folderKey].pages.length + 1,
                    serverFileName: doc.serverFileName,
                    serverPath: doc.serverPath
                });
            } else {
                // Regular file
                const rawFile = doc.file || null;
                const displayName = doc.fileName || rawFile?.name || doc.originalName || 'Document';
                const resolvedSize = typeof doc.fileSize === 'number' ? doc.fileSize : rawFile?.size || 0;

                batchDocuments.push({
                    file: rawFile,
                    fileName: displayName,
                    fileSize: resolvedSize,
                    definition: Number.isFinite(Number(doc.definition)) ? Number(doc.definition) : 0,
                    definitionCode: doc.definitionCode || null,
                    paperSize: doc.paperSize || 'A4',
                    documentType: doc.documentType || 'Other',
                    notes: doc.notes,
                    date: new Date().toLocaleDateString(),
                    isConvertedPdf: doc.isConvertedPdf || false,
                    serverFileName: doc.serverFileName || doc.serverPath || null,
                    serverPath: doc.serverPath || doc.downloadUrl || null,
                    downloadUrl: doc.downloadUrl || doc.serverPath || null,
                    scanId: doc.scanId || null
                });
            }
        });

        // Add PDF folders and their pages
        Object.values(pdfFolders).forEach(folder => {
            batchDocuments.push({
                id: folder.id,
                fileName: folder.fileName,
                fileSize: folder.fileSize,
                paperSize: folder.paperSize,
                documentType: folder.documentType,
                isPdfFolder: true,
                folderName: folder.folderName,
                pageCount: folder.pageCount,
                date: folder.date,
                downloadUrl: folder.downloadUrl || null
            });

            folder.pages.forEach(page => {
                batchDocuments.push({
                    fileName: page.fileName,
                    fileSize: page.fileSize,
                    paperSize: page.paperSize,
                    documentType: page.documentType,
                    parentFolder: folder.id,
                    pageNumber: page.pageNumber,
                    file: page.file,
                    date: new Date().toLocaleDateString(),
                    serverFileName: page.serverFileName,
                    serverPath: page.serverPath || page.downloadUrl || null,
                    downloadUrl: page.downloadUrl || page.serverPath || null,
                    scanId: page.scanId || null
                });
            });
        });

        const newBatch = {
            id: `BATCH-${Date.now()}`,
            fileNumber: indexedFile.fileNumber,
            name: indexedFile.name,
            documents: batchDocuments,
            date: new Date().toLocaleDateString(),
            status: 'Ready for page typing',
            uploadTime: new Date().toISOString(),
            registry: elements.blindScanRegistry?.value || blindScanElements.registry?.value || null
        };

        newBatch.documents.sort((a, b) => {
            const defA = Number.isFinite(Number(a.definition)) ? Number(a.definition) : 0;
            const defB = Number.isFinite(Number(b.definition)) ? Number(b.definition) : 0;
            if (defA !== defB) return defA - defB;
            const codeA = (a.definitionCode || '').toString();
            const codeB = (b.definitionCode || '').toString();
            if (codeA && codeB && codeA !== codeB) return codeA.localeCompare(codeB);
            return 0;
        });

        // Add to client state (pre-existing batches from server + new one)
        state.documentBatches = [newBatch, ...state.documentBatches];
        console.log('Created new batch:', newBatch);
        console.log('Total batches:', state.documentBatches.length);
    }

    async function startUpload() {
        if (!state.selectedIndexedFile) {
            state.showFileSelector = true;
            updateUI();
            return;
        }

        if (state.uploadDocuments.length === 0) {
            alert('Please select files to upload');
            return;
        }

        state.uploadStatus = 'uploading';
        state.uploadProgress = 0;
        updateUI();

        const isBlindMode = state.selectedUploadMethod === 'blind';
        const hasLocalBlindConversions = hasLocalBlindConvertedUploads();
        const uploadProgressMessage =
            isBlindMode && !hasLocalBlindConversions
                ? 'Moving blind scan documents into Scan Uploads...'
                : 'Uploading selected documents...';
        openUploadProgressModal(uploadProgressMessage);

        // Find the selected indexed file
        const indexedFile = findIndexedFileById(state.selectedIndexedFile);

        if (!indexedFile) {
            alert('Selected file not found');
            state.uploadStatus = 'idle';
            closeUploadProgressModal();
            updateUI();
            return;
        }

        if (isBlindMode && !hasLocalBlindConversions) {
            try {
                const transferredDocs = await startBlindScanTransfer(indexedFile);
                await convertTransferredPdfsToImages(transferredDocs, indexedFile);
            } catch (error) {
                console.error('Blind scan transfer failed:', error);
            } finally {
                closeUploadProgressModal();
            }
            return;
        }

        try {
            await hydrateUploadDocumentFilesBeforeUpload();
        } catch (error) {
            console.error('Upload hydration failed:', error);
            state.uploadStatus = 'error';
            elements.uploadErrorMessage.textContent = error.message || 'Unable to prepare files for upload.';
            elements.uploadError.classList.remove('hidden');
            closeUploadProgressModal();
            updateUI();
            return;
        }

        // Upload files one by one
        // const totalFiles = state.uploadDocuments.length;
        // let successfulUploads = 0;
        // let failedUploads = 0;
        // const uploadedFiles = []; // Track successfully uploaded files

        // Upload files with concurrency control
        const totalFiles = state.uploadDocuments.length;
        let completedFiles = 0;
        let successfulUploads = 0;
        let failedUploads = 0;
        const uploadedFiles = new Array(totalFiles); // Keep order

        const CONCURRENCY_LIMIT = 5;
        const uploadQueue = [...state.uploadDocuments.entries()];

        async function uploadWorker() {
            while (uploadQueue.length > 0) {
                const [index, doc] = uploadQueue.shift();

                try {
                    const serverDoc = await uploadFileToServer(doc, indexedFile, index);
                    successfulUploads++;

                    const normalized = normalizeServerDocument({
                        ...serverDoc,
                        fileNumber: indexedFile.fileNumber,
                        fileTitle: indexedFile.name
                    }) || {};

                    uploadedFiles[index] = {
                        ...doc,
                        fileName: normalized.fileName || doc.file?.name,
                        fileSize: typeof normalized.fileSize === 'number' && normalized.fileSize > 0
                            ? normalized.fileSize
                            : doc.file?.size,
                        definition: Number.isFinite(Number(normalized.definition)) ? Number(normalized.definition) : (Number.isFinite(Number(doc.definition)) ? Number(doc.definition) : ((Number.isFinite(Number(doc.displayOrder)) ? Number(doc.displayOrder) : 0) + 1)),
                        definitionCode: normalized.definitionCode || (indexedFile.fileNumber ? `${Number.isFinite(Number(doc.definition)) ? Number(doc.definition) : ((Number.isFinite(Number(doc.displayOrder)) ? Number(doc.displayOrder) : 0) + 1)}-${indexedFile.fileNumber}` : null),
                        paperSize: normalized.paperSize || doc.paperSize,
                        documentType: normalized.documentType || doc.documentType,
                        notes: normalized.notes || doc.notes,
                        serverFileName: normalized.serverFileName,
                        serverPath: normalized.serverPath || normalized.downloadUrl,
                        downloadUrl: normalized.downloadUrl,
                        scanId: normalized.scanId,
                        status: normalized.status || 'pending',
                        uploadedAt: normalized.uploadedAt,
                        fileNumber: indexedFile.fileNumber,
                        isConvertedPdf: doc.isConvertedPdf || normalized.isConvertedPdf
                    };
                } catch (error) {
                    console.error(`Failed to upload ${doc.file?.name || 'unknown'}:`, error);
                    failedUploads++;
                } finally {
                    completedFiles++;
                    state.uploadProgress = Math.round((completedFiles / totalFiles) * 100);
                    updateUI();
                }
            }
        }

        // Start workers
        const workers = [];
        for (let i = 0; i < Math.min(CONCURRENCY_LIMIT, totalFiles); i++) {
            workers.push(uploadWorker());
        }

        await Promise.all(workers);

        // Filter out failed uploads for batch creation
        const finalUploadedFiles = uploadedFiles.filter(Boolean);

        // Complete upload
        state.uploadProgress = 100;

        if (failedUploads > 0) {
            if (successfulUploads > 0) {
                // Some files uploaded successfully - create batch with successful ones
                createBatchFromUploadedFiles(indexedFile, finalUploadedFiles);
                state.uploadStatus = 'complete';
                elements.uploadErrorMessage.textContent = `${successfulUploads} files uploaded successfully, ${failedUploads} files failed. Documents tab has been updated.`;
                elements.uploadError.classList.remove('hidden');
                console.log(`Partial success: ${successfulUploads} uploaded, ${failedUploads} failed`);
            } else {
                // All files failed
                state.uploadStatus = 'error';
                elements.uploadErrorMessage.textContent = `All ${failedUploads} files failed to upload. Check console for details.`;
                elements.uploadError.classList.remove('hidden');
                console.log('All files failed to upload');
            }
        } else {
            // All files uploaded successfully
            createBatchFromUploadedFiles(indexedFile, finalUploadedFiles);
            state.uploadStatus = 'complete';
            elements.uploadError.classList.add('hidden');

            // Show conversion results if applicable
            if (state.pdfConversionEnabled && state.hasPdfFiles && state.pdfConversionResults.converted > 0) {
                console.log(`PDF conversion complete. Converted ${state.pdfConversionResults.converted} PDF file(s) to images.`);
            }
            console.log('All files uploaded successfully');
        }

        closeUploadProgressModal();

        updateUI();
    }

    async function loadIndexedFiles(searchTerm = '') {
        if (!indexedFilesEndpoint) {
            console.warn('Indexed files endpoint is not configured.');
            return;
        }

        indexedFilesLoading = true;
        indexedFilesError = '';
        const trimmedSearch = searchTerm.trim();
        state.indexedFilesSearchTerm = trimmedSearch;

        if (elements.indexedFilesList) {
            elements.indexedFilesList.innerHTML = `
          <div class="p-4 text-sm text-muted-foreground">Loading indexed files...</div>
        `;
        }

        try {
            const params = new URLSearchParams({
                limit: 20,
            });

            if (trimmedSearch) {
                params.append('search', trimmedSearch);
            }

            const response = await fetch(`${indexedFilesEndpoint}?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`Failed to load indexed files: ${response.status} ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success) {
                indexedFiles = Array.isArray(data.indexed_files) ? data.indexed_files : [];
                const metadata = data.metadata ?? {};
                indexedFilesPagination = {
                    current_page: 1,
                    per_page: metadata.limit ?? 20,
                    last_page: 1,
                    total: metadata.count ?? indexedFiles.length,
                };

                if (state.selectedIndexedFile) {
                    const stillExists = indexedFiles.some(file => String(file.id) === String(state.selectedIndexedFile));
                    if (!stillExists) {
                        state.selectedIndexedFile = null;
                        state.selectedFileNumberForUpload = null;
                        state.selectedUploadMethod = null;
                    }
                }
            } else {
                indexedFiles = [];
                indexedFilesError = data.message || 'No indexed files found.';
            }
        } catch (error) {
            console.error('Failed to load indexed files:', error);
            indexedFiles = [];
            indexedFilesError = error.message || 'Unable to load indexed files.';
        } finally {
            indexedFilesLoading = false;
            renderIndexedFiles();
            updateUI();
        }
    }

    function clearIndexedFileSelection() {
        state.selectedIndexedFile = null;
        state.selectedFileNumberForUpload = null;

        // Clears the hidden value AND resets the three dropdowns that feed it.
        setFileTypeFromCode('');
        if (elements.previewCoverBtn) {
            elements.previewCoverBtn.disabled = true;
        }
        hideCoverPreview();

        const selectedBadge = document.getElementById('selected-file-badge');
        if (selectedBadge) {
            selectedBadge.classList.add('hidden');
        }

        applyDefinitionToStagedDocs();
        syncConfirmButtonState();
        updateSelectedUploadMethodLabel();
    }

    function initializeSmartFileNumberSelector() {
        const selectElement = elements.globalSelectedFileNoSelect;
        if (!selectElement) {
            return;
        }

        const hasRegistry = Boolean(blindScanElements.registry?.value);
        selectElement.disabled = !hasRegistry;

        if (typeof $ === 'undefined' || typeof $.fn.select2 === 'undefined') {
            selectElement.addEventListener('change', () => {
                const selectedId = selectElement.value;
                if (!selectedId) {
                    clearIndexedFileSelection();
                    renderIndexedFiles();
                    return;
                }
                selectIndexedFileTemp(selectedId);
                renderIndexedFiles();
            });
            return;
        }

        $(selectElement).select2({
            width: '100%',
            allowClear: true,
            placeholder: 'Search file number',
            minimumInputLength: 1,
            dropdownParent: $('#file-selector-dialog .dialog-content'),
            ajax: {
                delay: 250,
                transport: function (params, success, failure) {
                    const term = params?.data?.term || '';
                    const registrySelected = Boolean(blindScanElements.registry?.value);

                    if (!registrySelected) {
                        success({ results: [] });
                        return null;
                    }

                    const query = new URLSearchParams({ limit: 20 });
                    if (term) {
                        query.append('search', term);
                    }

                    fetch(`${indexedFilesEndpoint}?${query.toString()}`, {
                        headers: { 'Accept': 'application/json' }
                    })
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error(`Failed to load indexed files: ${response.status} ${response.statusText}`);
                            }
                            return response.json();
                        })
                        .then((data) => {
                            const files = data.success && Array.isArray(data.indexed_files) ? data.indexed_files : [];
                            indexedFiles = files;
                            const results = files.map((file) => ({
                                id: String(file.id),
                                text: file.fileNumber || '',
                                file
                            }));
                            success({ results });
                        })
                        .catch((error) => {
                            console.error('Smart selector search failed:', error);
                            failure(error);
                        });

                    return null;
                },
                processResults: function (data) {
                    return data;
                }
            }
        });

        $(selectElement).on('select2:select', function (event) {
            const selectedData = event?.params?.data;
            const selectedId = selectedData?.id;
            if (!selectedId) {
                return;
            }

            const selectedFile = selectedData.file;
            if (selectedFile && !indexedFiles.some(file => String(file.id) === String(selectedFile.id))) {
                indexedFiles.unshift(selectedFile);
            }

            selectIndexedFileTemp(selectedId);
            renderIndexedFiles();
        });

        $(selectElement).on('select2:clear', function () {
            clearIndexedFileSelection();
            renderIndexedFiles();
        });
    }

    /**
     * Fetches the upload log from the server and populates the state.
     */
    async function loadServerData() {
        if (!state.logEndpoint) {
            console.warn('Scan Uploads log endpoint is not configured. Skipping log fetch.');
            return;
        }

        try {
            const pageQueryParam = state.pagination?.pageQuery || 'pag_next';
            const params = new URLSearchParams();
            if (state.pagination?.currentPage) {
                params.set(pageQueryParam, state.pagination.currentPage);
            }
            // Pass active search query to the server so results stay filtered
            if (state.searchQuery && state.searchQuery.trim()) {
                params.set('search', state.searchQuery.trim());
            }
            let logUrl = state.logEndpoint;
            const queryString = params.toString();
            if (queryString) {
                logUrl += (state.logEndpoint.includes('?') ? '&' : '?') + queryString;
            }

            const response = await fetch(logUrl, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            if (!response.ok) {
                throw new Error(`Failed to fetch log: ${response.statusText}`);
            }

            const result = await response.json();
            if (result.success && result.data) {
                const rawEntries = Array.isArray(result.data)
                    ? result.data
                    : Object.entries(result.data).map(([fileNumber, documents]) => ({
                        fileNumber,
                        documents
                    }));

                const newBatches = rawEntries
                    .map((serverBatch, index) => {
                        const normalized = buildBatchFromServerEntry(serverBatch, index);
                        if (!normalized) {
                            return null;
                        }

                        const indexedFile = indexedFiles.find(f => f.fileNumber === normalized.fileNumber);
                        if (indexedFile && (!normalized.name || normalized.name === 'Indexed File')) {
                            normalized.name = indexedFile.name;
                        }

                        return normalized;
                    })
                    .filter(Boolean);

                state.documentBatches = newBatches;
                state.folderPagination.currentPage = 1;
                persistFolderPageInQuery();
                console.log('Loaded server data:', state.documentBatches);

                // Debug the loaded paths
                debugDeletePaths();

                if (result.pagination) {
                    const meta = result.pagination;
                    const nextValue = meta.next_page ?? meta.nextPage ?? null;
                    const prevValue = meta.prev_page ?? meta.prevPage ?? null;
                    const currentValue = meta.current_page ?? meta.currentPage ?? state.pagination.currentPage;

                    state.pagination.currentPage = Number.isNaN(parseInt(currentValue, 10)) ? state.pagination.currentPage : parseInt(currentValue, 10);
                    state.pagination.nextPage = Number.isNaN(parseInt(nextValue, 10)) ? null : parseInt(nextValue, 10);
                    state.pagination.prevPage = Number.isNaN(parseInt(prevValue, 10)) ? null : parseInt(prevValue, 10);

                    if (meta.page_query || meta.pageQuery) {
                        state.pagination.pageQuery = meta.page_query || meta.pageQuery;
                    }
                }
            } else {
                state.documentBatches = [];
            }
        } catch (error) {
            console.error('Failed to load server data:', error);
            // Don't show an alert, just log it.
        }
    }

    /**
     * Deletes a single document from the server and updates the state.
     * @param {string} serverPath The web-accessible path to the file (e.g., /storage/FILE-123/unique_name.jpg)
     */
    async function deleteDocument({ scanId, serverPath }) {
        if (!confirm('Are you sure you want to delete this document?\nThis action cannot be undone.')) {
            return false;
        }

        try {
            console.log('=== DELETE ATTEMPT ===', { scanId, serverPath });

            let response;
            let result = null;

            if (scanId && state.deleteEndpointTemplate) {
                const deleteUrl = state.deleteEndpointTemplate.replace('ID', scanId);
                response = await fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        ...(state.csrfToken ? { 'X-CSRF-TOKEN': state.csrfToken } : {})
                    }
                });

                const textResponse = await response.text();
                try {
                    result = textResponse ? JSON.parse(textResponse) : {};
                } catch (parseError) {
                    console.warn('Delete endpoint returned non-JSON payload:', textResponse);
                    result = {};
                }
            } else if (serverPath && state.uploadEndpoint) {
                response = await fetch(state.uploadEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...(state.csrfToken ? { 'X-CSRF-TOKEN': state.csrfToken } : {})
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        path: serverPath
                    })
                });

                try {
                    result = await response.json();
                } catch (parseError) {
                    console.warn('Legacy delete endpoint returned non-JSON payload.');
                    result = {};
                }
            } else {
                throw new Error('No valid endpoint configured for document deletion.');
            }

            if (!response.ok || (result && result.success === false)) {
                throw new Error(result?.message || `Failed to delete document (HTTP ${response.status}).`);
            }

            console.log('? Successfully deleted:', scanId || serverPath);

            removeDocumentFromState({ scanId, serverPath });
            return true;
        } catch (error) {
            console.error('? Delete failed:', error);
            alert(`Error: ${error.message}`);
            return false;
        }
    }

    /**
     * Removes a document from the client state by server path
     */
    function removeDocumentFromState({ scanId, serverPath }) {
        const normalizedScanId = scanId ? String(scanId) : null;
        const normalizedPath = serverPath || null;

        state.documentBatches = state.documentBatches.map(batch => {
            return {
                ...batch,
                documents: batch.documents.filter(doc => {
                    if (normalizedScanId) {
                        const docId = doc.scanId || doc.id || null;
                        if (docId && String(docId) === normalizedScanId) {
                            return false;
                        }
                    }

                    if (normalizedPath) {
                        const matchesServerPath = doc.serverPath === normalizedPath || doc.downloadUrl === normalizedPath;
                        if (matchesServerPath) {
                            return false;
                        }
                    }

                    return true;
                })
            };
        }).filter(batch => batch.documents.length > 0); // Remove empty batches

        updateUI();
    }

    // NEW: Handle Upload More functionality for specific file numbers
    async function handleUploadMoreToFolder(fileNumber) {
        console.log(`Uploading more documents to file number: ${fileNumber}`);

        // First try to find in the current indexedFiles array
        let selectedFile = indexedFiles.find(f => f.fileNumber === fileNumber);

        // If not found locally, search via API
        if (!selectedFile) {
            console.log(`File number ${fileNumber} not in local cache, searching via API...`);

            try {
                await loadIndexedFiles(fileNumber);
                selectedFile = indexedFiles.find(f => f.fileNumber === fileNumber);
            } catch (error) {
                console.error('Failed to search for file number:', error);
            }
        }

        if (selectedFile) {
            // Set the selected file for upload
            state.selectedIndexedFile = String(selectedFile.id);
            state.selectedFileNumberForUpload = selectedFile.fileNumber;

            // Auto-set registry from existing batch data
            const existingBatch = state.documentBatches.find(b => b.fileNumber === fileNumber);
            let registryFromBatch = existingBatch?.registry || null;

            // Fallback: extract registry from document path (EDMS/SCAN_UPLOAD/{Registry_Folder}/...)
            if (!registryFromBatch && existingBatch?.documents?.length) {
                const docPath = existingBatch.documents[0]?.documentPath || '';
                const pathMatch = docPath.match(/SCAN_UPLOAD\/([^/]+)\//);
                if (pathMatch) {
                    const folderToRegistry = {
                        'Lands_Registry': 'Lands Registry',
                        'Cadastral_Registry': 'Cadastral Registry',
                        'DCIV_Registry': 'DCIV Registry',
                        'Secret_Registry': 'Secret Registry',
                        'KANGIS_Registry': 'KANGIS Registry',
                        'SLTR_Registry': 'SLTR Registry',
                        'ST_Registry': 'ST Registry',
                        'Deeds_Registry': 'Deeds Registry',
                    };
                    registryFromBatch = folderToRegistry[pathMatch[1]] || null;
                }
            }

            if (registryFromBatch) {
                const registrySelect = elements.blindScanRegistry || blindScanElements.registry;
                if (registrySelect) {
                    registrySelect.value = registryFromBatch;
                    // Trigger change event so dependent UI updates
                    registrySelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            // Switch to upload tab
            switchTab('upload');

            // Update UI to show the selected file
            updateUI();

            // Show success notification
            showNotification(`Ready to upload more documents to <strong>${fileNumber}</strong>`);
        } else {
            console.error(`File number ${fileNumber} not found in indexed files`);
            alert(`Error: File number ${fileNumber} not found.\n\nPlease ensure the file has been indexed before uploading documents to it.`);
        }
    }

    // NEW: Show toast notification
    function showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'toast-notification';
        notification.innerHTML = `
        <div class="flex items-center gap-2">
          <i data-lucide="check-circle" class="h-5 w-5"></i>
          <span>${message}</span>
        </div>
      `;
        document.body.appendChild(notification);
        lucide.createIcons();

        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    // Image Preview Functions
    function createFileIcon(fileType) {
        const isImage = fileType.startsWith('image/');
        const isPDF = fileType === 'application/pdf' || fileType.endsWith('.pdf');

        if (isImage) {
            return `
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="4" fill="#E5E5E5"/>
            <path d="M22 12H10V20H22V12Z" fill="#9CA3AF"/>
            <circle cx="13" cy="14" r="2" fill="#6B7280"/>
            <path d="M22 16L18 12L10 20L14 16L16 18L22 12V16Z" fill="#6B7280"/>
          </svg>
        `;
        } else if (isPDF) {
            return `
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="4" fill="#E5E5E5"/>
            <path d="M24 28H8C4.68629 28 2 27.3137 2 24V8C2 4.68629 4.68629 2 8 2H24C27.3137 2 30 4.68629 30 8V24C30 27.3137 27.3137 28 24 28Z" fill="#E5E5E5"/>
            <path d="M18 10H12V16H18V10Z" fill="#999999"/>
            <path d="M20 22H22C22.5523 22 23 21.5523 23 21V14C23 13.4477 22.5523 13 22 13H20C19.4477 13 19 13.4477 19 14V21C19 21.5523 19.4477 22 20 22Z" fill="#999999"/>
            <path d="M12 22H14C14.5523 22 15 21.5523 15 21V14C15 13.4477 14.5523 13 14 13H12C11.4477 13 11 13.4477 11 14V21C11 21.5523 11.4477 22 12 22Z" fill="#999999"/>
            <path d="M8 22H10C10.5523 22 11 21.5523 11 21V14C11 13.4477 10.5523 13 10 13H8C7.44772 13 7 13.4477 7 14V21C7 21.5523 7.44772 22 8 22Z" fill="#999999"/>
          </svg>
        `;
        } else {
            return `
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="4" fill="#E5E5E5"/>
            <path d="M19 12H9V14H19V12Z" fill="#9CA3AF"/>
            <path d="M19 16H9V18H19V16Z" fill="#9CA3AF"/>
            <path d="M13 20H9V22H13V20Z" fill="#9CA3AF"/>
            <path d="M23 12V20H21V14H17V12H23Z" fill="#6B7280"/>
          </svg>
        `;
        }
    }

    const IMAGE_PREVIEW_FALLBACK = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200"%3E%3Crect fill="%23ddd" width="200" height="200"/%3E%3Ctext fill="%23999" x="50%25" y="50%25" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif"%3ENo Image%3C/text%3E%3C/svg%3E';

    function cleanupPreviewTempUrls() {
        if (!state.previewSettings.tempObjectUrls?.length) {
            return;
        }
        state.previewSettings.tempObjectUrls.forEach(url => {
            try {
                URL.revokeObjectURL(url);
            } catch (error) {
                console.warn('Failed to revoke preview URL', url, error);
            }
        });
        state.previewSettings.tempObjectUrls = [];
    }

    function resolveDocumentSrc(doc) {
        let candidate = doc.downloadUrl || doc.serverPath || doc.documentPath || doc.imageUrl || doc.src || '';
        if (candidate && !candidate.startsWith('http') && !candidate.startsWith('blob:') && !candidate.startsWith('data:') && !candidate.startsWith('/')) {
            candidate = '/' + candidate.replace(/^\/+/, '');
        }
        return candidate;
    }

    function withCacheBuster(url) {
        if (!url) {
            return url;
        }
        const separator = url.includes('?') ? '&' : '?';
        return `${url}${separator}_=${Date.now()}`;
    }

    function getApplyEndpoint(scanId) {
        if (!scanId || !state.applyEndpointTemplate) {
            return null;
        }
        return state.applyEndpointTemplate.replace('ID', scanId);
    }

    function updateDocumentInBatches(updatedDocPayload) {
        if (!updatedDocPayload) {
            return;
        }
        const normalized = normalizeServerDocument(updatedDocPayload);
        if (!normalized?.id) {
            return;
        }
        state.documentBatches = state.documentBatches.map(batch => {
            if (!Array.isArray(batch.documents) || !batch.documents.length) {
                return batch;
            }
            const documents = batch.documents.map(doc => {
                const docIdentifier = doc.id ?? doc.scanId ?? doc.scan_id;
                if (!docIdentifier || String(docIdentifier) !== String(normalized.id)) {
                    return doc;
                }
                return {
                    ...doc,
                    ...normalized,
                    id: doc.id ?? normalized.id,
                    scanId: normalized.id ?? doc.scanId,
                };
            });
            return { ...batch, documents };
        });
    }

    function getFileExtension(filename = '') {
        const clean = (filename || '').toString();
        const match = clean.match(/\.([a-zA-Z0-9]+)$/);
        return match ? match[1].toLowerCase() : 'jpg';
    }

    function buildPositionBasedFileName(position, fileNumber = null, currentName = '') {
        const extension = getFileExtension(currentName);
        const prefixSeed = (fileNumber || state.selectedFileNumberForUpload || 'page').toString();
        const safePrefix = prefixSeed.replace(/[^\w\-]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '') || 'page';
        const serial = String(position + 1).padStart(4, '0');
        return `${safePrefix}-page-${serial}.${extension}`;
    }

    function applyPreviewOrderToSource() {
        const { source, items } = state.previewSettings;
        if (!Array.isArray(items) || !items.length) return;

        if (source === 'selected') {
            const reorderedDocs = items
                .map(item => state.uploadDocuments[item.sourceIndex])
                .filter(Boolean)
                .map((doc, position) => {
                    const nextName = buildPositionBasedFileName(position, state.selectedFileNumberForUpload || state.previewSettings.fileNumber, doc.fileName || doc.file?.name || '');
                    const updated = {
                        ...doc,
                        fileName: nextName,
                        displayOrder: position
                    };
                    if (doc.file instanceof File) {
                        updated.file = new File([doc.file], nextName, {
                            type: doc.file.type || 'application/octet-stream',
                            lastModified: doc.file.lastModified || Date.now()
                        });
                    }
                    return updated;
                });

            state.uploadDocuments = reorderedDocs;
            state.selectedUploadFiles = reorderedDocs.map(doc => (doc?.file instanceof File ? doc.file : null));
            state.previewSettings.items = state.previewSettings.items.map((item, index) => ({
                ...item,
                sourceIndex: index,
                fileName: reorderedDocs[index]?.fileName || item.fileName,
                definition: reorderedDocs[index]?.definition ?? item.definition,
                definitionCode: reorderedDocs[index]?.definitionCode ?? item.definitionCode
            }));
            return;
        }

        if (source === 'uploaded' && state.selectedFile) {
            state.documentBatches = state.documentBatches.map(batch => {
                if (batch.id !== state.selectedFile || !Array.isArray(batch.documents)) {
                    return batch;
                }

                const documents = [...batch.documents];
                const orderedDocs = state.previewSettings.items
                    .map(item => {
                        const itemIdentifier = item.scanId ?? item.id;
                        return documents.find(doc => {
                            const docIdentifier = doc.id ?? doc.scanId ?? doc.scan_id;
                            return itemIdentifier && docIdentifier && String(itemIdentifier) === String(docIdentifier);
                        }) || null;
                    })
                    .filter(Boolean)
                    .map((doc, position) => ({
                        ...doc,
                        fileName: buildPositionBasedFileName(position, batch.fileNumber || state.previewSettings.fileNumber, doc.fileName || ''),
                        displayOrder: position
                    }));

                const orderedIds = new Set(orderedDocs.map(doc => String(doc.id ?? doc.scanId ?? '')));
                const untouchedDocs = documents
                    .filter(doc => !orderedIds.has(String(doc.id ?? doc.scanId ?? '')))
                    .map((doc, index) => ({ ...doc, displayOrder: orderedDocs.length + index }));

                return {
                    ...batch,
                    documents: [...orderedDocs, ...untouchedDocs]
                };
            });

            state.previewSettings.items = state.previewSettings.items.map((item, index) => ({
                ...item,
                sourceIndex: index,
                fileName: buildPositionBasedFileName(index, state.previewSettings.fileNumber, item.fileName || '')
            }));
        }
    }

    async function hydrateUploadDocumentFilesBeforeUpload() {
        const hydratedDocs = [];
        const failedDocs = [];

        for (let index = 0; index < (state.uploadDocuments || []).length; index += 1) {
            const doc = state.uploadDocuments[index];
            if (!doc) {
                continue;
            }

            if (doc.file instanceof File) {
                hydratedDocs.push(doc);
                continue;
            }

            const sourceCandidates = [
                doc.previewDataUrl,
                doc.downloadUrl,
                doc.serverPath,
                resolveStorageUrl(doc.documentPath || doc.document_path || ''),
                resolveStorageUrl(doc.relativePath || ''),
                resolveStorageUrl(doc.blindStoragePath || doc.blind_storage_path || '')
            ].filter(Boolean);

            let recoveredFile = null;
            let lastError = null;

            for (const sourceUrl of sourceCandidates) {
                try {
                    const response = await fetch(sourceUrl);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const blob = await response.blob();
                    if (!blob || !blob.size) {
                        throw new Error('Empty payload');
                    }

                    const fallbackName = doc.fileName || doc.originalName || `document-${index + 1}.jpg`;
                    const safeName = String(fallbackName).replace(/[\\/:*?"<>|]+/g, '-');
                    recoveredFile = new File([blob], safeName, {
                        type: blob.type || 'image/jpeg',
                        lastModified: Date.now()
                    });
                    break;
                } catch (error) {
                    lastError = error;
                }
            }

            if (recoveredFile) {
                hydratedDocs.push({
                    ...doc,
                    file: recoveredFile,
                    fileSize: recoveredFile.size,
                    fileName: recoveredFile.name,
                    previewDataUrl: doc.previewDataUrl || await fileToUrl(recoveredFile)
                });
            } else {
                failedDocs.push({
                    index,
                    name: doc.fileName || doc.originalName || `Document ${index + 1}`,
                    error: lastError?.message || 'Unknown error'
                });
                hydratedDocs.push(doc);
            }
        }

        state.uploadDocuments = hydratedDocs;
        state.selectedUploadFiles = hydratedDocs.map(doc => (doc?.file instanceof File ? doc.file : null));

        if (failedDocs.length) {
            const failedNames = failedDocs.map(item => item.name).join(', ');
            throw new Error(`Some edited files could not be prepared for upload: ${failedNames}`);
        }
    }

    async function persistPreviewOrder() {
        if (state.previewSettings.source !== 'uploaded') {
            showNotification('Order updated in preview queue.');
            return true;
        }

        if (!state.reorderEndpoint) {
            showNotification('Order updated locally (save endpoint not configured).');
            return false;
        }

        const items = (state.previewSettings.items || [])
            .map((item, index) => ({
                scan_id: Number(item.scanId || item.id || 0),
                display_order: index,
                original_filename: item.fileName || null
            }))
            .filter(item => Number.isInteger(item.scan_id) && item.scan_id > 0);

        if (!items.length) {
            return false;
        }

        try {
            const response = await fetch(state.reorderEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': state.csrfToken || ''
                },
                body: JSON.stringify({ items })
            });

            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.success) {
                throw new Error(payload?.message || 'Unable to save new order.');
            }

            showNotification('Order saved successfully.');
            return true;
        } catch (error) {
            console.error('Failed to persist preview order', error);
            showNotification(error.message || 'Order changed, but save failed.');
            return false;
        }
    }

    function handlePreviewThumbDragStart(event) {
        if (state.previewSettings.selectMode) return;
        const wrapper = event.target.closest('.preview-thumbnail-wrapper[data-preview-index]');
        if (!wrapper) return;
        previewDragIndex = Number(wrapper.getAttribute('data-preview-index'));
        if (!Number.isFinite(previewDragIndex)) return;
        wrapper.classList.add('is-dragging');
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(previewDragIndex));
        }
    }

    function handlePreviewThumbDragOver(event) {
        if (previewDragIndex === null || state.previewSettings.selectMode) return;
        const wrapper = event.target.closest('.preview-thumbnail-wrapper[data-preview-index]');
        if (!wrapper) return;
        event.preventDefault();
        wrapper.classList.add('is-drag-over');
    }

    function handlePreviewThumbDragLeave(event) {
        const wrapper = event.target.closest('.preview-thumbnail-wrapper[data-preview-index]');
        if (!wrapper) return;
        wrapper.classList.remove('is-drag-over');
    }

    async function handlePreviewThumbDrop(event) {
        if (previewDragIndex === null || state.previewSettings.selectMode) return;
        const wrapper = event.target.closest('.preview-thumbnail-wrapper[data-preview-index]');
        if (!wrapper) return;
        event.preventDefault();

        const targetIndex = Number(wrapper.getAttribute('data-preview-index'));
        if (!Number.isFinite(targetIndex) || targetIndex === previewDragIndex) {
            return;
        }

        const items = [...state.previewSettings.items];
        const [movedItem] = items.splice(previewDragIndex, 1);
        items.splice(targetIndex, 0, movedItem);
        state.previewSettings.items = items;
        state.previewSettings.activeIndex = targetIndex;
        state.currentPreviewPage = targetIndex + 1;

        applyPreviewOrderToSource();
        renderPreviewModal();
        updateUI();
        await persistPreviewOrder();
    }

    function handlePreviewThumbDragEnd() {
        previewDragIndex = null;
        document.querySelectorAll('.preview-thumbnail-wrapper.is-dragging, .preview-thumbnail-wrapper.is-drag-over')
            .forEach(node => node.classList.remove('is-dragging', 'is-drag-over'));
    }

    function buildPreviewItemsFromBatch(batch, folderId = null) {
        if (!batch) {
            return [];
        }
        const documents = Array.isArray(batch.documents) ? batch.documents : [];
        const filteredDocs = documents.filter(doc => {
            if (folderId) {
                return doc.parentFolder === folderId;
            }
            return !doc.parentFolder;
        });

        return filteredDocs.map((doc, index) => {
            const src = resolveDocumentSrc(doc);
            return {
                id: doc.id || `doc-${index}`,
                fileName: doc.fileName || doc.fileTitle || `Document ${index + 1}`,
                fileSize: doc.fileSize || 0,
                paperSize: doc.paperSize || 'A4',
                documentType: doc.documentType || 'Document',
                fileNumber: batch.fileNumber || doc.fileNumber || '',
                src,
                context: 'uploaded',
                sourceIndex: index,
                notes: doc.notes || '',
                scanId: doc.scanId || doc.id || null,
                definition: Number.isFinite(Number(doc.definition)) ? Number(doc.definition) : ((Number.isFinite(Number(doc.displayOrder)) ? Number(doc.displayOrder) : index) + 1),
                definitionCode: doc.definitionCode || null
            };
        });
    }

    async function buildPreviewItemsFromUploads() {
        const items = [];
        for (let i = 0; i < state.uploadDocuments.length; i += 1) {
            const doc = state.uploadDocuments[i];
            let src = doc.previewDataUrl || doc.downloadUrl || null;
            if (!src && doc.file && doc.file.type.startsWith('image/')) {
                src = await fileToUrl(doc.file);
                doc.previewDataUrl = src;
            }
            items.push({
                id: `selected-${i}`,
                fileName: doc.file?.name || doc.fileName || doc.originalName || `Document ${i + 1}`,
                fileSize: typeof doc.fileSize === 'number' ? doc.fileSize : (doc.file?.size || 0),
                paperSize: doc.paperSize || 'A4',
                documentType: doc.documentType || 'Document',
                fileNumber: doc.fileNumber || state.selectedFileNumberForUpload || '',
                src,
                context: doc.source === 'blind' ? 'blind' : 'selected',
                sourceIndex: i,
                notes: doc.notes || '',
                isBlindScan: doc.source === 'blind',
                definition: Number.isFinite(Number(doc.definition)) ? Number(doc.definition) : ((Number.isFinite(Number(doc.displayOrder)) ? Number(doc.displayOrder) : i) + 1),
                definitionCode: doc.definitionCode || null
            });
        }
        return items;
    }

    function openPreviewWithItems(items, activeIndex = 0, options = {}) {
        if (!Array.isArray(items) || !items.length) {
            alert('No documents available for preview.');
            return;
        }
        cleanupPreviewTempUrls();
        state.previewSettings.items = items;
        state.previewSettings.activeIndex = Math.max(0, Math.min(activeIndex, items.length - 1));
        state.previewSettings.source = options.source || 'uploaded';
        state.previewSettings.fileNumber = options.fileNumber || null;
        state.previewSettings.history = [];
        state.previewSettings.future = [];
        state.previewSettings.panOffset = { x: 0, y: 0 };
        state.previewSettings.panLimits = { x: 0, y: 0 };
        state.previewSettings.cropMode = false;
        state.previewSettings.cropSelection = null;
        state.previewSettings.fullScreen = false;
        state.previewSettings.sidebarCollapsed = false;
        state.previewSettings.selectMode = false;
        state.previewSettings.selectedForDelete = new Set();
        state.zoomLevel = 100;
        state.rotation = 0;
        state.previewOpen = true;
        state.currentPreviewPage = state.previewSettings.activeIndex + 1;
        renderPreviewModal();
        updateUI();
    }

    function renderPreviewThumbnails() {
        if (!elements.previewThumbnails) {
            return;
        }
        const { items, activeIndex, selectMode, selectedForDelete } = state.previewSettings;
        elements.previewThumbnails.innerHTML = '';
        items.forEach((item, index) => {
            const isActive = index === activeIndex;
            const isSelected = selectedForDelete.has(index);
            const wrapper = document.createElement('div');
            wrapper.className = `preview-thumbnail-wrapper ${isSelected ? 'is-selected' : ''}`;
            wrapper.setAttribute('data-preview-index', index);
            wrapper.draggable = !selectMode;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = `preview-thumbnail ${isActive ? 'active' : ''}`;
            button.setAttribute('data-preview-index', index);
            const thumbSrc = item.src || IMAGE_PREVIEW_FALLBACK;
            button.innerHTML = `
          <img src="${thumbSrc}" alt="${escapeHtml(item.fileName || `Document ${index + 1}`)}" onerror="this.src='${IMAGE_PREVIEW_FALLBACK}'" />
          <span>${escapeHtml(item.fileName || `Document ${index + 1}`)}</span>
        `;

            if (selectMode) {
                const checkbox = document.createElement('label');
                checkbox.className = 'preview-thumb-checkbox';
                checkbox.innerHTML = `<input type="checkbox" ${isSelected ? 'checked' : ''} data-select-index="${index}" />`;
                checkbox.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
                checkbox.querySelector('input').addEventListener('change', (e) => {
                    e.stopPropagation();
                    togglePreviewItemSelection(index);
                });
                wrapper.appendChild(checkbox);
            }

            wrapper.appendChild(button);
            elements.previewThumbnails.appendChild(wrapper);
        });
        if (elements.previewSidebarCount) {
            elements.previewSidebarCount.textContent = `${items.length} file${items.length === 1 ? '' : 's'}`;
        }
        updatePreviewSidebarHeader();
        lucide.createIcons();
    }

    function renderPreviewModal() {
        if (!state.previewOpen || !elements.previewDialog) {
            return;
        }
        const { items, activeIndex } = state.previewSettings;
        const activeItem = items[activeIndex];
        if (!activeItem) {
            elements.previewDialog.classList.add('hidden');
            return;
        }
        state.currentPreviewPage = activeIndex + 1;
        elements.previewDialog.classList.remove('hidden');
        elements.previewDialog.classList.toggle('is-fullscreen', state.previewSettings.fullScreen);
        if (elements.previewDialogContent) {
            elements.previewDialogContent.classList.toggle('fullscreen', state.previewSettings.fullScreen);
        }
        if (elements.previewShell) {
            elements.previewShell.classList.toggle('sidebar-hidden', !!state.previewSettings.sidebarCollapsed);
        }
        if (elements.previewSidebarToggle) {
            const sidebarTitle = state.previewSettings.sidebarCollapsed ? 'Show gallery' : 'Hide gallery';
            const iconName = state.previewSettings.sidebarCollapsed ? 'panel-right' : 'panel-left';
            elements.previewSidebarToggle.setAttribute('aria-pressed', state.previewSettings.sidebarCollapsed ? 'false' : 'true');
            elements.previewSidebarToggle.setAttribute('title', sidebarTitle);
            elements.previewSidebarToggle.innerHTML = `<i data-lucide="${iconName}" class="h-4 w-4"></i>`;
        }
        if (elements.toggleFullscreenBtn) {
            const icon = state.previewSettings.fullScreen ? 'minimize-2' : 'maximize-2';
            elements.toggleFullscreenBtn.innerHTML = `<i data-lucide="${icon}" class="h-4 w-4"></i>`;
        }
        if (elements.cropOverlay) {
            elements.cropOverlay.classList.toggle('hidden', !state.previewSettings.cropMode);
        }
        if (elements.previewTitle) {
            elements.previewTitle.textContent = activeItem.fileNumber || 'Document Preview';
        }
        if (elements.previewFilename) {
            elements.previewFilename.textContent = activeItem.fileName || '';
        }
        const src = activeItem.src || IMAGE_PREVIEW_FALLBACK;
        if (elements.previewImage && elements.previewImage.getAttribute('src') !== src) {
            elements.previewImage.src = src;
        } else {
            updatePreviewBounds();
            applyPreviewTransform();
        }
        if (elements.documentInfo) {
            elements.documentInfo.innerHTML = `
          <span>${escapeHtml(activeItem.paperSize || 'A4')}</span>
          <span>${escapeHtml(activeItem.documentType || 'Document')}</span>
          <span>${formatFileSize(activeItem.fileSize || 0)}</span>
        `;
        }

        if (elements.prevPageBtn) {
            elements.prevPageBtn.disabled = activeIndex <= 0;
        }
        if (elements.nextPageBtn) {
            elements.nextPageBtn.disabled = activeIndex >= items.length - 1;
        }
        if (elements.applyPreviewBtn) {
            const canApply = Boolean(activeItem.scanId);
            elements.applyPreviewBtn.disabled = !canApply;
            elements.applyPreviewBtn.classList.toggle('opacity-60', !canApply);
            elements.applyPreviewBtn.classList.toggle('pointer-events-none', !canApply);
        }
        if (elements.previewEditBtn) {
            const canEdit = state.previewSettings.source === 'selected' && typeof activeItem.sourceIndex === 'number';
            elements.previewEditBtn.disabled = !canEdit;
        }
        if (elements.previewDeleteBtn) {
            const canDelete =
                (state.previewSettings.source === 'selected' && typeof activeItem.sourceIndex === 'number') ||
                Boolean(activeItem.scanId || activeItem.id || activeItem.serverPath || activeItem.src);
            elements.previewDeleteBtn.disabled = !canDelete;
        }
        if (elements.undoBtn) {
            elements.undoBtn.disabled = !(state.previewSettings.history && state.previewSettings.history.length);
        }
        if (elements.redoBtn) {
            elements.redoBtn.disabled = !(state.previewSettings.future && state.previewSettings.future.length);
        }
        renderPreviewThumbnails();
        applyPreviewTransform();
    }

    function selectPreviewIndex(index) {
        if (!state.previewSettings.items.length) {
            return;
        }
        const clamped = Math.max(0, Math.min(index, state.previewSettings.items.length - 1));
        state.previewSettings.activeIndex = clamped;
        state.currentPreviewPage = clamped + 1;
        state.previewSettings.panOffset = { x: 0, y: 0 };
        state.zoomLevel = 100;
        state.rotation = 0;
        state.previewSettings.cropMode = false;
        state.previewSettings.cropSelection = null;
        renderPreviewModal();
    }

    function applyPreviewTransform() {
        const image = elements.previewImage;
        if (!image) {
            return;
        }
        const offset = state.previewSettings.panOffset || { x: 0, y: 0 };
        const scale = state.zoomLevel / 100;
        const transform = `translate(${offset.x}px, ${offset.y}px) scale(${scale}) rotate(${state.rotation}deg)`;
        image.style.transform = transform;
        requestAnimationFrame(() => updatePreviewBounds());
        if (elements.zoomLevel) {
            elements.zoomLevel.textContent = `${Math.round(state.zoomLevel)}%`;
        }
    }

    function updatePreviewBounds() {
        const wrapper = elements.previewImageWrapper;
        const image = elements.previewImage;
        if (!wrapper || !image) {
            return;
        }
        const wrapperRect = wrapper.getBoundingClientRect();
        const imageRect = image.getBoundingClientRect();
        const maxX = Math.max(0, (imageRect.width - wrapperRect.width) / 2);
        const maxY = Math.max(0, (imageRect.height - wrapperRect.height) / 2);
        state.previewSettings.panLimits = { x: maxX, y: maxY };
        clampPreviewPan();
    }

    function clampPreviewPan() {
        const limits = state.previewSettings.panLimits || { x: 0, y: 0 };
        state.previewSettings.panOffset.x = Math.max(-limits.x, Math.min(limits.x, state.previewSettings.panOffset.x));
        state.previewSettings.panOffset.y = Math.max(-limits.y, Math.min(limits.y, state.previewSettings.panOffset.y));
    }

    function resetPreviewView(options = {}) {
        if (!state.previewOpen) {
            return;
        }
        const { pushHistory = true } = options;
        if (pushHistory) {
            pushPreviewHistory();
        }
        state.zoomLevel = 100;
        state.rotation = 0;
        state.previewSettings.panOffset = { x: 0, y: 0 };
        applyPreviewTransform();
    }

    function pushPreviewHistory() {
        if (!state.previewSettings.history) {
            state.previewSettings.history = [];
        }
        const snapshot = createPreviewSnapshot();
        state.previewSettings.history.push(snapshot);
        if (state.previewSettings.history.length > 15) {
            state.previewSettings.history.shift();
        }
        state.previewSettings.future = [];
    }

    function createPreviewSnapshot() {
        return {
            items: state.previewSettings.items.map(item => ({ ...item })),
            activeIndex: state.previewSettings.activeIndex,
            zoomLevel: state.zoomLevel,
            rotation: state.rotation,
            panOffset: { ...state.previewSettings.panOffset }
        };
    }

    function restorePreviewSnapshot(snapshot) {
        if (!snapshot) {
            return;
        }
        state.previewSettings.items = snapshot.items.map(item => ({ ...item }));
        state.previewSettings.activeIndex = snapshot.activeIndex;
        state.zoomLevel = snapshot.zoomLevel;
        state.rotation = snapshot.rotation;
        state.previewSettings.panOffset = { ...snapshot.panOffset };
        renderPreviewModal();
    }

    function undoPreviewEdit() {
        if (!state.previewSettings.history?.length) {
            return;
        }
        const currentSnapshot = createPreviewSnapshot();
        state.previewSettings.future.push(currentSnapshot);
        const snapshot = state.previewSettings.history.pop();
        restorePreviewSnapshot(snapshot);
    }

    function redoPreviewEdit() {
        if (!state.previewSettings.future?.length) {
            return;
        }
        const currentSnapshot = createPreviewSnapshot();
        state.previewSettings.history.push(currentSnapshot);
        const snapshot = state.previewSettings.future.pop();
        restorePreviewSnapshot(snapshot);
    }

    function togglePreviewFullScreen() {
        if (!state.previewOpen) {
            return;
        }
        state.previewSettings.fullScreen = !state.previewSettings.fullScreen;
        renderPreviewModal();
        updatePreviewBounds();
    }

    function togglePreviewSidebar() {
        if (!state.previewOpen) {
            return;
        }
        state.previewSettings.sidebarCollapsed = !state.previewSettings.sidebarCollapsed;
        renderPreviewModal();
    }

    function handlePreviewEdit() {
        if (!state.previewOpen) {
            return;
        }
        const activeItem = state.previewSettings.items[state.previewSettings.activeIndex];
        if (!activeItem) {
            return;
        }
        if (state.previewSettings.source === 'selected' && typeof activeItem.sourceIndex === 'number') {
            openDocumentDetails(activeItem.sourceIndex);
            return;
        }
        alert('Editing is only available for files staged in this upload session.');
    }

    async function handlePreviewDelete() {
        if (!state.previewOpen) {
            return;
        }
        const activeItem = state.previewSettings.items[state.previewSettings.activeIndex];
        if (!activeItem) {
            return;
        }

        if (state.previewSettings.source === 'selected' && typeof activeItem.sourceIndex === 'number') {
            if (!confirm('Remove this document from the upload list?')) {
                return;
            }
            const removedIndex = activeItem.sourceIndex;
            removeFile(removedIndex);
            const remainingItems = await buildPreviewItemsFromUploads();
            if (remainingItems.length) {
                const nextIndex = Math.min(removedIndex, remainingItems.length - 1);
                openPreviewWithItems(remainingItems, nextIndex, { source: 'selected' });
            } else {
                closePreview();
            }
            return;
        }

        const targetScanId = activeItem.scanId || activeItem.id || null;
        const targetPath = activeItem.serverPath || activeItem.src || null;
        if (!targetScanId && !targetPath) {
            alert('Unable to determine which document to delete.');
            return;
        }

        const success = await deleteDocument({
            scanId: targetScanId,
            serverPath: targetPath
        });

        if (success) {
            // Remove from preview items and stay in the modal
            const removedIdx = state.previewSettings.activeIndex;
            state.previewSettings.items.splice(removedIdx, 1);

            // Also remove from multi-select set if present
            state.previewSettings.selectedForDelete.delete(removedIdx);
            // Re-index the selectedForDelete set (shift indices down)
            const updated = new Set();
            state.previewSettings.selectedForDelete.forEach(idx => {
                if (idx > removedIdx) updated.add(idx - 1);
                else updated.add(idx);
            });
            state.previewSettings.selectedForDelete = updated;

            if (state.previewSettings.items.length === 0) {
                closePreview();
                return;
            }

            // Navigate to next available page
            const nextIdx = Math.min(removedIdx, state.previewSettings.items.length - 1);
            state.previewSettings.activeIndex = nextIdx;
            state.currentPreviewPage = nextIdx + 1;
            state.previewSettings.history = [];
            state.previewSettings.future = [];
            state.zoomLevel = 100;
            state.rotation = 0;
            state.previewSettings.cropMode = false;
            state.previewSettings.cropSelection = null;
            renderPreviewModal();
            showNotification('Document deleted. ' + state.previewSettings.items.length + ' remaining.');
        }
    }

    async function handlePreviewReassign() {
        if (!state.previewOpen) {
            return;
        }
        const activeItem = state.previewSettings.items[state.previewSettings.activeIndex];
        if (!activeItem) {
            return;
        }

        const targetScanId = activeItem.scanId || activeItem.id || null;
        if (!targetScanId) {
            alert('Unable to determine which document to reassign.');
            return;
        }

        // Try to determine file number and registry from client-side data first
        let fileNumber = activeItem.fileNumber || '';
        let registry = activeItem.registry || '';

        // Fallback: batch-level fileNumber if preview opened from a batch
        if (!fileNumber && state.previewSettings.fileNumber) {
            fileNumber = state.previewSettings.fileNumber || '';
        }

        // Fallback: definitionCode (e.g. "1-RES-1981-257")
        if (!fileNumber && activeItem.definitionCode) {
            const parts = String(activeItem.definitionCode).split('-');
            if (parts.length >= 2) {
                fileNumber = parts.slice(1).join('-');
            }
        }

        // Fallback: parse from documentPath if present
        if (!fileNumber && activeItem.documentPath) {
            const pathParts = activeItem.documentPath.split('/').filter(Boolean);
            if (pathParts.length >= 3) {
                // Assume .../{registry}/{fileno}/{paper}/{file}
                // fileno is usually at -3 index
                fileNumber = pathParts[pathParts.length - 3] || '';
                if (!registry) {
                    registry = pathParts[pathParts.length - 4] || '';
                }
            }
        }

        // Prepare scanData with whatever we have so far
        const scanData = {
            fileName: activeItem.fileName || 'Document',
            fileNumber: fileNumber || '',
            registry: registry || '',
            scanId: targetScanId,
            paperSize: activeItem.paperSize,
            documentType: activeItem.documentType
        };

        console.log('Reassign - Active Item:', activeItem);
        console.log('Reassign - Initial extracted data:', scanData);

        // Only call server if we still don't have a fileNumber
        if (!scanData.fileNumber) {
            try {
                const response = await fetch('/scan-uploads/scan-file-info?scan_id=' + targetScanId);
                const result = await response.json();
                if (result.success && result.data) {
                    scanData.fileNumber = result.data.file_number || '';
                    scanData.registry = scanData.registry || result.data.registry || '';
                    console.log('Reassign - Fetched from database:', scanData);
                }
            } catch (error) {
                console.warn('Could not fetch scan file info:', error);
            }
        }

        // Open modal with the best data available
        if (window.scanReassignmentManager) {
            window.scanReassignmentManager.openModal([targetScanId], [scanData]);
        } else {
            alert('Reassignment module not loaded.');
        }
    }

    // --- Bulk reassign selected scans ---
    async function handleBulkReassign() {
        const selected = state.previewSettings.selectedForDelete;
        if (!selected.size) return;

        const scanIds = [];
        const scanDataArr = [];

        for (const index of selected) {
            const item = state.previewSettings.items[index];
            if (!item) continue;

            const scanId = item.scanId || item.id;
            if (!scanId) continue;

            let fileNumber = item.fileNumber || '';
            let registry = item.registry || '';

            if (!fileNumber && state.previewSettings.fileNumber) {
                fileNumber = state.previewSettings.fileNumber || '';
            }
            if (!fileNumber && item.definitionCode) {
                const parts = String(item.definitionCode).split('-');
                if (parts.length >= 2) fileNumber = parts.slice(1).join('-');
            }
            if (!fileNumber && item.documentPath) {
                const pathParts = item.documentPath.split('/').filter(Boolean);
                if (pathParts.length >= 3) {
                    fileNumber = pathParts[pathParts.length - 3] || '';
                    if (!registry) registry = pathParts[pathParts.length - 4] || '';
                }
            }

            scanIds.push(scanId);
            scanDataArr.push({
                fileName: item.fileName || 'Document',
                fileNumber: fileNumber,
                registry: registry,
                scanId: scanId,
                paperSize: item.paperSize,
                documentType: item.documentType
            });
        }

        if (!scanIds.length) {
            alert('No valid documents selected for reassignment.');
            return;
        }

        if (window.scanReassignmentManager) {
            window.scanReassignmentManager.openModal(scanIds, scanDataArr);
        } else {
            alert('Reassignment module not loaded.');
        }
    }

    // --- Multi-select delete ---
    function togglePreviewSelectMode() {
        state.previewSettings.selectMode = !state.previewSettings.selectMode;
        if (!state.previewSettings.selectMode) {
            state.previewSettings.selectedForDelete.clear();
        }
        renderPreviewThumbnails();
        updatePreviewSidebarHeader();
    }

    function togglePreviewItemSelection(index) {
        if (state.previewSettings.selectedForDelete.has(index)) {
            state.previewSettings.selectedForDelete.delete(index);
        } else {
            state.previewSettings.selectedForDelete.add(index);
        }
        renderPreviewThumbnails();
        updatePreviewSidebarHeader();
    }

    function selectAllPreviewItems() {
        state.previewSettings.items.forEach((_, idx) => {
            state.previewSettings.selectedForDelete.add(idx);
        });
        renderPreviewThumbnails();
        updatePreviewSidebarHeader();
    }

    function deselectAllPreviewItems() {
        state.previewSettings.selectedForDelete.clear();
        renderPreviewThumbnails();
        updatePreviewSidebarHeader();
    }

    async function deleteSelectedPreviewItems() {
        const selectedIndices = Array.from(state.previewSettings.selectedForDelete).sort((a, b) => b - a); // descending
        if (!selectedIndices.length) {
            alert('No documents selected for deletion.');
            return;
        }

        if (!confirm(`Delete ${selectedIndices.length} selected document(s)?\nThis action cannot be undone.`)) {
            return;
        }

        let deletedCount = 0;
        let failedCount = 0;

        for (const idx of selectedIndices) {
            const item = state.previewSettings.items[idx];
            if (!item) continue;

            const targetScanId = item.scanId || item.id || null;
            const targetPath = item.serverPath || item.src || null;

            if (state.previewSettings.source === 'selected' && typeof item.sourceIndex === 'number') {
                removeFile(item.sourceIndex);
                state.previewSettings.items.splice(idx, 1);
                deletedCount++;
                continue;
            }

            if (!targetScanId && !targetPath) {
                failedCount++;
                continue;
            }

            // Use deleteDocument but skip the inner confirm (already confirmed above)
            try {
                const scanId = targetScanId;
                const serverPath = targetPath;
                let response;
                let result = null;

                if (scanId && state.deleteEndpointTemplate) {
                    const deleteUrl = state.deleteEndpointTemplate.replace('ID', scanId);
                    response = await fetch(deleteUrl, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            ...(state.csrfToken ? { 'X-CSRF-TOKEN': state.csrfToken } : {})
                        }
                    });
                    const textResponse = await response.text();
                    try { result = textResponse ? JSON.parse(textResponse) : {}; } catch(e) { result = {}; }
                } else if (serverPath && state.uploadEndpoint) {
                    response = await fetch(state.uploadEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            ...(state.csrfToken ? { 'X-CSRF-TOKEN': state.csrfToken } : {})
                        },
                        body: JSON.stringify({ action: 'delete', path: serverPath })
                    });
                    try { result = await response.json(); } catch(e) { result = {}; }
                }

                if (response && response.ok && result?.success !== false) {
                    removeDocumentFromState({ scanId, serverPath });
                    state.previewSettings.items.splice(idx, 1);
                    deletedCount++;
                } else {
                    failedCount++;
                }
            } catch (err) {
                console.error('Bulk delete error for item', idx, err);
                failedCount++;
            }
        }

        // Clear selection
        state.previewSettings.selectedForDelete.clear();
        state.previewSettings.selectMode = false;

        if (state.previewSettings.items.length === 0) {
            closePreview();
            showNotification(`Deleted ${deletedCount} document(s).` + (failedCount ? ` ${failedCount} failed.` : ''));
            return;
        }

        // Re-navigate
        const nextIdx = Math.min(state.previewSettings.activeIndex, state.previewSettings.items.length - 1);
        state.previewSettings.activeIndex = nextIdx;
        state.currentPreviewPage = nextIdx + 1;
        state.zoomLevel = 100;
        state.rotation = 0;
        renderPreviewModal();
        showNotification(`Deleted ${deletedCount} document(s).` + (failedCount ? ` ${failedCount} failed.` : ''));
    }

    function updatePreviewSidebarHeader() {
        const header = document.querySelector('.preview-sidebar-header');
        if (!header) return;

        const count = state.previewSettings.items.length;
        const selectedCount = state.previewSettings.selectedForDelete.size;
        const inSelectMode = state.previewSettings.selectMode;

        header.innerHTML = `
            <div class="flex items-center gap-2">
                <span>Pages</span>
                <span id="preview-sidebar-count">${count}</span>
            </div>
            <div class="flex items-center gap-1">
                ${inSelectMode && selectedCount > 0 ? `
                    <button type="button" class="preview-sidebar-action-btn reassign-selected-btn" title="Reassign ${selectedCount} selected" style="color: #2563eb;">
                        <i data-lucide="git-branch" class="h-3.5 w-3.5"></i>
                        <span>${selectedCount}</span>
                    </button>
                    <button type="button" class="preview-sidebar-action-btn delete-selected-btn" title="Delete ${selectedCount} selected">
                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                        <span>${selectedCount}</span>
                    </button>
                    <button type="button" class="preview-sidebar-action-btn select-all-btn" title="${selectedCount === count ? 'Deselect All' : 'Select All'}">
                        <i data-lucide="${selectedCount === count ? 'x-square' : 'check-square'}" class="h-3.5 w-3.5"></i>
                    </button>
                ` : ''}
                <button type="button" class="preview-sidebar-action-btn toggle-select-btn ${inSelectMode ? 'is-active' : ''}" title="${inSelectMode ? 'Exit select mode' : 'Select to delete'}">
                    <i data-lucide="${inSelectMode ? 'x' : 'list-checks'}" class="h-3.5 w-3.5"></i>
                </button>
            </div>
        `;

        // Re-bind sidebar header button events
        header.querySelector('.toggle-select-btn')?.addEventListener('click', togglePreviewSelectMode);
        header.querySelector('.delete-selected-btn')?.addEventListener('click', deleteSelectedPreviewItems);
        header.querySelector('.reassign-selected-btn')?.addEventListener('click', handleBulkReassign);
        header.querySelector('.select-all-btn')?.addEventListener('click', () => {
            if (state.previewSettings.selectedForDelete.size === state.previewSettings.items.length) {
                deselectAllPreviewItems();
            } else {
                selectAllPreviewItems();
            }
        });

        lucide.createIcons();
    }

    function setApplyChangesLoading(isLoading) {
        if (!elements.applyPreviewBtn) {
            return;
        }
        elements.applyPreviewBtn.disabled = isLoading;
        elements.applyPreviewBtn.classList.toggle('opacity-60', isLoading);
        elements.applyPreviewBtn.classList.toggle('pointer-events-none', isLoading);
    }

    async function applyPreviewChanges() {
        if (!state.previewOpen) {
            return;
        }
        const activeItem = state.previewSettings.items[state.previewSettings.activeIndex];
        if (!activeItem) {
            alert('No document selected.');
            return;
        }

        if (!activeItem.scanId) {
            showNotification('Edits saved to the upload queue. Complete the upload to persist them.');
            return;
        }

        const endpoint = getApplyEndpoint(activeItem.scanId);
        if (!endpoint) {
            alert('Apply endpoint is not configured.');
            return;
        }

        try {
            setApplyChangesLoading(true);
            const source = activeItem.src || elements.previewImage?.src;
            if (!source) {
                throw new Error('Unable to read the edited image.');
            }

            const blob = await (await fetch(source)).blob();
            const safeName = (activeItem.fileName || `scan-${activeItem.scanId}.jpg`).replace(/[^\w.\-]/g, '_');
            const formData = new FormData();
            formData.append('file', blob, safeName);

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': state.csrfToken || ''
                },
                body: formData
            });

            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.success) {
                throw new Error(payload?.message || 'Unable to apply changes.');
            }

            const updatedDocument = payload.data || null;
            if (updatedDocument) {
                updateDocumentInBatches(updatedDocument);
                const refreshedSrc = withCacheBuster(
                    updatedDocument.downloadUrl ||
                    updatedDocument.webPath ||
                    updatedDocument.serverPath ||
                    source
                );
                activeItem.scanId = activeItem.scanId || updatedDocument.id || updatedDocument.scanId;
                activeItem.src = refreshedSrc;
                activeItem.fileName = updatedDocument.fileName || updatedDocument.originalName || activeItem.fileName;
                activeItem.fileSize = updatedDocument.fileSize || activeItem.fileSize;
                activeItem.paperSize = updatedDocument.paperSize || activeItem.paperSize;
                activeItem.documentType = updatedDocument.documentType || activeItem.documentType;
            }

            showNotification('Changes applied to this document.');
            renderPreviewModal();
            updateUI();
        } catch (error) {
            console.error('Apply changes failed', error);
            alert(error.message || 'Unable to apply changes.');
        } finally {
            setApplyChangesLoading(false);
        }
    }

    async function handlePreviewUploadChange(event) {
        if (!state.previewOpen) {
            return;
        }
        const file = event.target.files && event.target.files[0];
        if (!file) {
            return;
        }
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file.');
            event.target.value = '';
            return;
        }

        pushPreviewHistory();
        const fileUrl = await fileToUrl(file);
        const activeItem = state.previewSettings.items[state.previewSettings.activeIndex];
        if (!activeItem) {
            return;
        }

        if (state.previewSettings.source === 'selected' && typeof activeItem.sourceIndex === 'number') {
            const targetDoc = state.uploadDocuments[activeItem.sourceIndex];
            if (targetDoc) {
                targetDoc.file = file;
                targetDoc.fileName = file.name;
                targetDoc.fileSize = file.size;
                targetDoc.previewDataUrl = fileUrl;
            }
        } else if (state.previewSettings.source === 'uploaded') {
            const clone = {
                id: `uploaded-${Date.now()}`,
                fileName: file.name,
                fileSize: file.size,
                paperSize: 'A4',
                documentType: 'Document',
                fileNumber: state.previewSettings.fileNumber || '',
                src: fileUrl,
                context: 'custom'
            };
            state.previewSettings.items.push(clone);
            state.previewSettings.activeIndex = state.previewSettings.items.length - 1;
        }

        activeItem.src = fileUrl;
        activeItem.fileName = file.name;
        activeItem.fileSize = file.size;
        activeItem.context = state.previewSettings.source;
        event.target.value = '';
        state.previewSettings.panOffset = { x: 0, y: 0 };
        state.zoomLevel = 100;
        state.rotation = 0;
        renderPreviewModal();
        updateUI();
    }

    async function updateCurrentPreviewSource(newSrc, meta = {}) {
        if (!state.previewOpen) {
            return;
        }
        const activeItem = state.previewSettings.items[state.previewSettings.activeIndex];
        if (!activeItem) {
            return;
        }
        activeItem.src = newSrc;
        if (elements.previewImage && elements.previewImage.src !== newSrc) {
            elements.previewImage.src = newSrc;
        }

        if (state.previewSettings.source === 'selected' && typeof activeItem.sourceIndex === 'number') {
            const targetDoc = state.uploadDocuments[activeItem.sourceIndex];
            if (targetDoc) {
                const blob = await (await fetch(newSrc)).blob();
                const extension = blob.type === 'image/png' ? 'png' : 'jpg';
                const baseName = (targetDoc.file?.name || activeItem.fileName || `document-${Date.now()}`).replace(/\.[^.]+$/, '');
                const newFileName = `${baseName}-${meta.reason || 'edit'}.${extension}`;
                const editedFile = new File([blob], newFileName, { type: blob.type || 'image/jpeg' });
                targetDoc.file = editedFile;
                targetDoc.fileName = newFileName;
                targetDoc.fileSize = editedFile.size;
                targetDoc.previewDataUrl = newSrc;
            }
        }
    }

    function enterCropMode() {
        if (!state.previewOpen) {
            return;
        }
        if (!elements.cropOverlay) {
            return;
        }
        state.previewSettings.cropMode = true;
        state.previewSettings.cropSelection = null;
        state.previewSettings.cropStart = null;
        elements.cropOverlay.classList.remove('hidden');
        if (elements.cropActionButtons) {
            elements.cropActionButtons.classList.add('hidden');
        }
        resetPreviewView({ pushHistory: false });
    }

    function exitCropMode() {
        state.previewSettings.cropMode = false;
        state.previewSettings.cropSelection = null;
        state.previewSettings.cropStart = null;
        state.previewSettings.cropSelecting = false;
        if (elements.cropOverlay) {
            elements.cropOverlay.classList.add('hidden');
        }
        if (elements.cropActionButtons) {
            elements.cropActionButtons.classList.add('hidden');
        }
    }

    function updateCropSelection() {
        if (!elements.cropOverlay || !elements.cropSelection) {
            return;
        }
        const selection = state.previewSettings.cropSelection;
        const selectionEl = elements.cropSelection;
        if (!selectionEl) {
            return;
        }
        if (!selection) {
            selectionEl.style.width = '0px';
            selectionEl.style.height = '0px';
            if (elements.cropActionButtons) {
                elements.cropActionButtons.classList.add('hidden');
            }
            return;
        }
        selectionEl.style.left = `${selection.x}px`;
        selectionEl.style.top = `${selection.y}px`;
        selectionEl.style.width = `${selection.width}px`;
        selectionEl.style.height = `${selection.height}px`;
    }

    function getCropRectFromSelection(selection) {
        if (!selection || !elements.previewImageWrapper || !elements.previewImage) {
            return null;
        }
        const wrapperRect = elements.previewImageWrapper.getBoundingClientRect();
        const imageRect = elements.previewImage.getBoundingClientRect();
        const startX = wrapperRect.left + selection.x;
        const startY = wrapperRect.top + selection.y;
        const endX = startX + selection.width;
        const endY = startY + selection.height;
        const cropLeft = Math.max(startX, imageRect.left);
        const cropTop = Math.max(startY, imageRect.top);
        const cropRight = Math.min(endX, imageRect.right);
        const cropBottom = Math.min(endY, imageRect.bottom);
        const visibleWidth = cropRight - cropLeft;
        const visibleHeight = cropBottom - cropTop;
        if (visibleWidth <= 8 || visibleHeight <= 8) {
            return null;
        }
        const naturalWidth = elements.previewImage.naturalWidth || visibleWidth;
        const naturalHeight = elements.previewImage.naturalHeight || visibleHeight;
        const scaleX = naturalWidth / imageRect.width;
        const scaleY = naturalHeight / imageRect.height;
        const relativeX = cropLeft - imageRect.left;
        const relativeY = cropTop - imageRect.top;

        return {
            x: relativeX * scaleX,
            y: relativeY * scaleY,
            width: visibleWidth * scaleX,
            height: visibleHeight * scaleY
        };
    }

    function handleCropPointerDown(event) {
        if (!state.previewSettings.cropMode) {
            return;
        }
        if (!elements.previewImageWrapper) {
            return;
        }
        state.previewSettings.cropSelecting = true;
        event.preventDefault();
        if (!elements.previewImageWrapper) {
            return;
        }
        const rect = elements.previewImageWrapper.getBoundingClientRect();
        const startX = event.clientX - rect.left;
        const startY = event.clientY - rect.top;
        state.previewSettings.cropStart = { x: startX, y: startY };
        state.previewSettings.cropSelection = { x: startX, y: startY, width: 0, height: 0 };
        updateCropSelection();
    }

    function handleCropPointerMove(event) {
        if (!state.previewSettings.cropSelecting || !state.previewSettings.cropMode) {
            return;
        }
        event.preventDefault();
        const rect = elements.previewImageWrapper.getBoundingClientRect();
        const currentX = Math.max(0, Math.min(event.clientX - rect.left, rect.width));
        const currentY = Math.max(0, Math.min(event.clientY - rect.top, rect.height));
        const { x, y } = state.previewSettings.cropStart;
        const width = currentX - x;
        const height = currentY - y;
        state.previewSettings.cropSelection = {
            x: width < 0 ? currentX : x,
            y: height < 0 ? currentY : y,
            width: Math.abs(width),
            height: Math.abs(height)
        };
        updateCropSelection();
    }

    function handleCropPointerUp(event) {
        if (!state.previewSettings.cropSelecting) {
            return;
        }
        if (event) {
            event.preventDefault();
        }
        state.previewSettings.cropSelecting = false;
        if (elements.cropActionButtons) {
            const selection = state.previewSettings.cropSelection;
            const cropRect = getCropRectFromSelection(selection);
            const isValid = Boolean(cropRect);
            elements.cropActionButtons.classList.toggle('hidden', !isValid);
            if (!isValid) {
                state.previewSettings.cropSelection = null;
            }
        }
    }

    async function applyCropSelection() {
        const selection = state.previewSettings.cropSelection;
        if (!selection || !elements.previewImage) {
            alert('Draw a selection to crop.');
            return;
        }
        const cropRect = getCropRectFromSelection(selection);
        if (!cropRect) {
            alert('Selection must be inside the document before cropping.');
            return;
        }
        pushPreviewHistory();
        await performImageCrop(cropRect);
        exitCropMode();
        renderPreviewModal();
    }

    async function performImageCrop(cropRect) {
        const image = elements.previewImage;
        if (!image || !state.previewOpen) {
            return;
        }
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(cropRect.width));
        canvas.height = Math.max(1, Math.round(cropRect.height));
        const ctx = canvas.getContext('2d');
        ctx.drawImage(
            image,
            cropRect.x,
            cropRect.y,
            cropRect.width,
            cropRect.height,
            0,
            0,
            canvas.width,
            canvas.height
        );
        const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
        await updateCurrentPreviewSource(dataUrl, { reason: 'crop' });
        state.previewSettings.panOffset = { x: 0, y: 0 };
        state.zoomLevel = 100;
        state.rotation = 0;
    }

    async function rotateCurrentPreview(direction = 'right') {
        if (!state.previewOpen) {
            return;
        }
        const image = elements.previewImage;
        if (!image) {
            return;
        }
        pushPreviewHistory();
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const width = image.naturalWidth || image.width;
        const height = image.naturalHeight || image.height;
        canvas.width = height;
        canvas.height = width;
        ctx.translate(height / 2, width / 2);
        const rotationAngle = direction === 'left' ? -Math.PI / 2 : Math.PI / 2;
        ctx.rotate(rotationAngle);
        ctx.drawImage(image, -width / 2, -height / 2);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
        await updateCurrentPreviewSource(dataUrl, { reason: 'rotate' });
        state.previewSettings.panOffset = { x: 0, y: 0 };
        state.zoomLevel = 100;
        state.rotation = 0;
        renderPreviewModal();
    }

    function handlePreviewPanStart(event) {
        if (!state.previewOpen || state.previewSettings.cropMode || !state.previewSettings.items.length) {
            return;
        }
        if (typeof event.button === 'number' && event.button !== 0) {
            return;
        }
        state.previewSettings.isPanning = true;
        state.previewSettings.panStart = { x: event.clientX, y: event.clientY };
        state.previewSettings.panBaseline = { ...state.previewSettings.panOffset };
        if (elements.previewImageWrapper) {
            elements.previewImageWrapper.classList.add('dragging');
        }
        event.preventDefault();
    }

    function handlePreviewPanMove(event) {
        if (!state.previewSettings.isPanning) {
            return;
        }
        const deltaX = event.clientX - state.previewSettings.panStart.x;
        const deltaY = event.clientY - state.previewSettings.panStart.y;
        state.previewSettings.panOffset = {
            x: state.previewSettings.panBaseline.x + deltaX,
            y: state.previewSettings.panBaseline.y + deltaY
        };
        clampPreviewPan();
        applyPreviewTransform();
    }

    function handlePreviewPanEnd() {
        if (!state.previewSettings.isPanning) {
            return;
        }
        state.previewSettings.isPanning = false;
        if (elements.previewImageWrapper) {
            elements.previewImageWrapper.classList.remove('dragging');
        }
        clampPreviewPan();
        applyPreviewTransform();
    }

    function handlePreviewWheel(event) {
        if (!state.previewOpen || !elements.previewCanvas || state.previewSettings.cropMode) {
            return;
        }
        event.preventDefault();
        const direction = event.deltaY < 0 ? 1 : -1;
        const step = event.ctrlKey || event.metaKey ? 4 : 8;
        setZoomLevel(state.zoomLevel + direction * step);
    }

    function handlePreviewResize() {
        if (!state.previewOpen) {
            return;
        }
        updatePreviewBounds();
    }
    async function replaceStagedWithLF(index) {
        const doc = state.uploadDocuments[index];
        if (!doc || state.uploadStatus === 'uploading') return;
        const existingUrl = doc.previewDataUrl || doc.downloadUrl || (doc.file ? await fileToUrl(doc.file) : '');
        LargeFormatScans.open({ existingUrl, title: `Replace page ${index + 1} with Large-Format Scan`, confirmLabel: 'Confirm Replacement',
            fileIndexingId: state.selectedIndexedFile,
            onConfirm: async entry => {
                const file = await LargeFormatScans.file(entry);
                const preview = await fileToUrl(file);
                if (state.uploadDocuments[index] !== doc) throw new Error('The selected page changed. Select it again.');
                state.uploadDocuments[index] = { ...doc, lfPreviousVersion: doc, file, fileName: file.name,
                    fileSize: file.size, previewDataUrl: preview, downloadUrl: null, source: 'large-format', isBlindScan: false };
                if (state.previewOpen) {
                    state.previewSettings.items = await buildPreviewItemsFromUploads();
                }
                updateUI();
            }
        });
    }

    function replaceUploadedWithLF(scanId) {
        const batch = state.documentBatches.find(batch => batch.documents?.some(doc => String(doc.scanId || doc.id) === String(scanId)));
        const doc = batch?.documents.find(doc => String(doc.scanId || doc.id) === String(scanId));
        if (!doc) return;
        LargeFormatScans.open({ existingUrl: resolveDocumentSrc(doc), title: `Replace ${doc.fileName || 'selected page'} with LF Scan`, confirmLabel: 'Confirm Replacement',
            fileIndexingId: doc.fileIndexingId || resolveFileIndexingIdFromBatch(batch),
            onConfirm: async entry => {
                const result = await LargeFormatScans.save({ source: entry.path, scanning_id: Number(scanId),
                    file_indexing_id: doc.fileIndexingId || resolveFileIndexingIdFromBatch(batch), expected_path: doc.storedDocumentPath || doc.documentPath });
                const updated = result.document;
                Object.assign(doc, { documentPath: updated.document_path, storedDocumentPath: updated.document_path,
                    downloadUrl: updated.file_url, serverPath: updated.file_url, fileName: updated.original_filename, fileSize: updated.file_size });
                const item = state.previewSettings.items.find(item => String(item.scanId) === String(scanId));
                if (item) Object.assign(item, { src: updated.file_url, fileName: updated.original_filename, fileSize: updated.file_size });
                updateUI();
            }
        });
    }

    function chooseUploadedLFPage(batchId) {
        const batch = state.documentBatches.find(batch => String(batch.id) === String(batchId));
        if (!batch) return;
        const dialog = document.createElement('dialog');
        dialog.style.cssText = 'padding:24px;max-height:80vh;overflow:auto;border-radius:10px';
        const title = document.createElement('h3');
        title.textContent = 'Choose the page to replace with LF Scan'; dialog.append(title);
        batch.documents.filter(doc => !doc.isPdfFolder).forEach((doc, index) => {
            const button = document.createElement('button');
            button.type = 'button'; button.style.cssText = 'display:block;padding:10px';
            button.textContent = `Page ${doc.definition || index + 1}: ${doc.fileName}`;
            button.onclick = () => { dialog.close(); dialog.remove(); replaceUploadedWithLF(doc.scanId || doc.id); };
            dialog.append(button);
        });
        const cancel = document.createElement('button'); cancel.textContent = 'Cancel';
        cancel.onclick = () => { dialog.close(); dialog.remove(); }; dialog.append(cancel);
        dialog.addEventListener('cancel', () => dialog.remove());
        document.body.append(dialog); dialog.showModal();
    }

    async function renderSelectedFiles() {
        const renderVersion = ++selectedFilesRenderVersion;
        const uploadDocsSnapshot = Array.isArray(state.uploadDocuments) ? [...state.uploadDocuments] : [];

        elements.selectedFilesList.innerHTML = '';

        for (const [index, doc] of uploadDocsSnapshot.entries()) {
            if (renderVersion !== selectedFilesRenderVersion) {
                return;
            }

            const fileCard = document.createElement('div');
            fileCard.className = 'file-card';

            const rawFile = doc.file || null;
            const isBlindDoc = (doc.source === 'blind' && !doc.file) || (doc.isBlindScan && !doc.file);
            const showBlindBadge = doc.source === 'blind' || doc.isBlindScan;
            const derivedName = rawFile?.name || doc.fileName || doc.originalName || `Document ${index + 1}`;
            const derivedSize = typeof doc.fileSize === 'number' ? doc.fileSize : rawFile?.size || 0;
            const fileType = rawFile?.type || '';
            const lowerName = (derivedName || '').toLowerCase();
            const isPdfFile = !isBlindDoc && (lowerName.endsWith('.pdf') || fileType === 'application/pdf');
            const canPreviewImage = isBlindDoc
                ? Boolean(doc.downloadUrl)
                : Boolean(rawFile && fileType.startsWith('image/'));

            let imageContent = '';

            if (isBlindDoc && doc.downloadUrl) {
                const safeName = escapeHtml(derivedName);
                imageContent = `<img src="${doc.downloadUrl}" alt="${safeName}" loading="lazy" onerror="this.src='${IMAGE_PREVIEW_FALLBACK}'" />`;
            } else if (canPreviewImage && rawFile) {
                try {
                    const imageUrl = await fileToUrl(rawFile);
                    if (renderVersion !== selectedFilesRenderVersion) {
                        return;
                    }
                    doc.previewDataUrl = imageUrl;
                    imageContent = `<img src="${imageUrl}" alt="${escapeHtml(derivedName)}" />`;
                } catch (error) {
                    console.error('Failed to load image preview:', error);
                    imageContent = createFileIcon(fileType || lowerName);
                }
            } else {
                imageContent = createFileIcon(fileType || lowerName);
            }

            const paperSizeValue = doc.paperSize || 'A4';
            const paperSizeClass = `badge-${paperSizeValue.toLowerCase()}`;
            const blindBadge = showBlindBadge ? '<span class="badge badge-outline text-[11px] uppercase">Blind Scan</span>' : '';
            const fileNumberForDoc = doc.fileNumber || state.selectedFileNumberForUpload || '';
            const definitionCode = doc.definitionCode || (fileNumberForDoc ? `${Number.isFinite(Number(doc.definition)) ? Number(doc.definition) : ((Number.isFinite(Number(doc.displayOrder)) ? Number(doc.displayOrder) : index) + 1)}-${fileNumberForDoc}` : null);

            fileCard.innerHTML = `
          <!-- Image Container (A4 aspect ratio) -->
          <div
            class="file-card-image-container preview-card-image"
            data-preview-index="${index}"
            role="button"
            tabindex="0"
            aria-label="Preview ${escapeHtml(derivedName)}"
          >
            ${imageContent}
          </div>

          <!-- Content Section -->
          <div class="file-card-content">
            <div class="file-card-name" title="${escapeHtml(derivedName)}">${escapeHtml(derivedName)}</div>

            <div class="file-card-badges">
              <span class="badge ${paperSizeClass}">${paperSizeValue}</span>
              <span class="badge badge-outline">${doc.documentType || 'Document'}</span>
                            ${definitionCode ? `<span class="badge" style="background:#dcfce7;color:#166534;">${escapeHtml(definitionCode)}</span>` : ''}
              ${blindBadge}
              ${isPdfFile ? '<span class="badge" style="background: #fee2e2; color: #dc2626;">PDF</span>' : ''}
              ${doc.isConvertedPdf ? '<span class="badge" style="background: #d1fae5; color: #059669;">PDF Converted</span>' : ''}
            </div>

          <div class="file-card-size">${formatFileSize(derivedSize)}</div>
        </div>
        `;

            elements.selectedFilesList.appendChild(fileCard);
            const replaceLF = document.createElement('button');
            replaceLF.type = 'button'; replaceLF.className = 'btn btn-outline btn-sm';
            replaceLF.textContent = 'Replace with Large-Format Scan';
            replaceLF.onclick = () => replaceStagedWithLF(index);
            fileCard.append(replaceLF);

            const previewTrigger = fileCard.querySelector('.preview-card-image');
            if (previewTrigger) {
                previewTrigger.addEventListener('click', () => previewSelectedFile(index));
                previewTrigger.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        previewSelectedFile(index);
                    }
                });
            }
        }

        if (renderVersion !== selectedFilesRenderVersion) {
            return;
        }


        // Initialize icons for the new elements
        lucide.createIcons();
    }

    // Function to preview selected file in modal
    async function previewSelectedFile(index) {
        const items = await buildPreviewItemsFromUploads();
        if (!items.length) {
            alert('No selected documents available to preview.');
            return;
        }
        openPreviewWithItems(items, index, { source: 'selected' });
    }

    // Function to pre-generate image previews
    async function pregenerateImagePreviews() {
        for (const doc of state.uploadDocuments) {
            if (doc.file && doc.file.type && doc.file.type.startsWith('image/')) {
                const previewKey = `selected-${doc.file.name}-${doc.file.lastModified}`;
                await storeFilePreview(doc.file, previewKey);
            }
        }
    }

    // UI update functions
    function updateUI() {
        // Update tabs
        elements.tabs.forEach(tab => {
            const tabId = tab.getAttribute('data-tab');
            tab.setAttribute('aria-selected', tabId === state.activeTab);
        });

        elements.tabContents.forEach(content => {
            const contentId = content.getAttribute('data-tab-content');
            content.setAttribute('aria-hidden', contentId !== state.activeTab);
        });

        // Update selected file badge
        if (state.selectedIndexedFile) {
            const selectedFile = findIndexedFileById(state.selectedIndexedFile);
            elements.selectedFileBadge.classList.remove('hidden');
            elements.selectedFileNumber.textContent = selectedFile ? selectedFile.fileNumber : 'No file selected';
            elements.changeFileText.textContent = 'Change File';
            elements.browseFilesBtn.disabled = false;
            elements.selectFileWarning.classList.add('hidden');
        } else {
            elements.selectedFileBadge.classList.add('hidden');
            elements.changeFileText.textContent = 'Select File';
            elements.browseFilesBtn.disabled = true;
            elements.selectFileWarning.classList.remove('hidden');
        }

        updateSelectedUploadMethodLabel();

        // Update upload status
        elements.uploadIdle.classList.toggle('hidden', state.uploadStatus !== 'idle');
        elements.uploadProgress.classList.toggle('hidden', state.uploadStatus !== 'uploading');
        elements.uploadComplete.classList.toggle('hidden', state.uploadStatus !== 'complete');
        elements.uploadError.classList.toggle('hidden', state.uploadStatus !== 'error');

        // Update buttons based on upload status
        elements.startUploadBtn.classList.toggle('hidden',
            !(state.uploadStatus === 'idle' && state.uploadDocuments.length > 0));
        elements.cancelUploadBtn.classList.toggle('hidden', state.uploadStatus !== 'uploading');
        elements.uploadMoreBtn.classList.toggle('hidden', state.uploadStatus !== 'complete' && state.uploadStatus !== 'error');
        elements.viewUploadedBtn.classList.toggle('hidden', state.uploadStatus !== 'complete' && state.uploadStatus !== 'error');

        // Update selected files
        elements.selectedFilesContainer.classList.toggle('hidden', state.uploadDocuments.length === 0);
        elements.selectedFilesCount.textContent = state.uploadDocuments.length;

        if (state.uploadDocuments.length > 0) {
            renderSelectedFiles();
        }

        // Update upload progress
        if (state.uploadStatus === 'uploading') {
            elements.uploadingCount.textContent = state.uploadDocuments.length;
            elements.uploadPercentage.textContent = `${state.uploadProgress}%`;
            elements.progressBar.style.width = `${state.uploadProgress}%`;
        }

        // Update uploaded files tab
        // Get today's date in MM/DD/YYYY format
        const today = new Date().toLocaleDateString();
        const todaysUploads = state.documentBatches.filter(batch => batch.date === today).length;

        elements.uploadsCount.textContent = todaysUploads;
        elements.pendingCount.textContent = state.documentBatches.reduce(
            (total, batch) => total + batch.documents.length, 0
        );

        elements.noDocuments.classList.toggle('hidden', state.documentBatches.length > 0);
        elements.batchActions.classList.toggle('hidden', state.documentBatches.length === 0);

        // Update view toggle
        elements.toggleViewBtn.textContent = state.showFolderView ? 'List View' : 'Folder View';
        elements.listView.classList.toggle('hidden', state.showFolderView);
        elements.folderView.classList.toggle('hidden', !state.showFolderView);
        if (!state.showFolderView && elements.folderPaginationContainer) {
            elements.folderPaginationContainer.classList.add('hidden');
        }

        if (state.documentBatches.length > 0) {
            renderBatches();
        }

        // Update dialogs
        elements.previewDialog.classList.toggle('hidden', !state.previewOpen);
        elements.fileSelectorDialog.classList.toggle('hidden', !state.showFileSelector);
        elements.documentDetailsDialog.classList.toggle('hidden', !state.showDocumentDetails);

        // Update preview
        if (state.previewOpen) {
            renderPreviewModal();
        }

        // Update file selector
        if (state.showFileSelector) {
            updateUploadMethodInputs();
            renderIndexedFiles();
        }

        renderBlindScanModal();
        renderUploadProgressModal();

        // Update document details
        if (state.showDocumentDetails && state.currentDocumentIndex !== null) {
            populateDocumentDetailsForm();
        }
    }

    function renderBatches() {
        const filteredBatches = getFilteredBatches();

        const totalFolderPages = Math.max(1, Math.ceil(((filteredBatches.length || 1)) / state.folderPagination.perPage));
        if (state.folderPagination.currentPage > totalFolderPages) {
            state.folderPagination.currentPage = totalFolderPages;
        }

        if (state.showFolderView) {
            renderFolderView(filteredBatches);
        } else {
            renderListView(filteredBatches);
        }
    }


    function renderListView(batches) {
        elements.listView.innerHTML = '';

        const tableWrapper = document.createElement('div');
        tableWrapper.className = 'overflow-x-auto';

        const table = document.createElement('table');
        table.className = 'min-w-full divide-y divide-gray-200 text-sm';
        table.innerHTML = `
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">File Number</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Document</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Paper Size</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Type</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Uploaded</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Uploaded By</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Status</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-600 uppercase tracking-wide text-xs">Actions</th>
          </tr>
        </thead>
      `;

        const tbody = document.createElement('tbody');
        tbody.className = 'bg-white divide-y divide-gray-100';
        table.appendChild(tbody);

        let rowsRendered = 0;

        batches.forEach(batch => {
            if (!Array.isArray(batch.documents) || batch.documents.length === 0) {
                return;
            }

            rowsRendered++;
            const totalUploads = batch.documents.length;
            const safeFileNumber = escapeHtml(batch.fileNumber || 'N/A');
            const uniquePaperSizes = Array.from(new Set(batch.documents.map(doc => doc.paperSize).filter(Boolean)));
            const paperSummary = uniquePaperSizes.length ? uniquePaperSizes.join(', ') : 'N/A';
            const representativeDoc = batch.documents[0] || null;
            const representativeType = representativeDoc?.documentType || 'Document';
            const hasPending = batch.documents.some(doc => (doc.status || '').toLowerCase() === 'pending');
            const statusText = hasPending ? 'Pending Page Typing' : 'Ready for Page Typing';
            const statusClass = hasPending ? 'bg-amber-500 text-white' : 'bg-emerald-500 text-white';
            const latestDate = batch.documents.reduce((latest, doc) => {
                const docDate = doc.uploadedAt || doc.date || null;
                if (!docDate) {
                    return latest;
                }
                if (!latest) {
                    return docDate;
                }
                return new Date(docDate) > new Date(latest) ? docDate : latest;
            }, null);
            const formattedDate = latestDate ? formatDateFromIso(latestDate) : (batch.date || 'N/A');

            const row = document.createElement('tr');
            row.innerHTML = `
          <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">${safeFileNumber}</td>
          <td class="px-4 py-3 text-gray-700 whitespace-nowrap">Total Uploads: ${totalUploads}</td>
          <td class="px-4 py-3 text-gray-700 whitespace-nowrap">${paperSummary}</td>
          <td class="px-4 py-3 text-gray-700 whitespace-nowrap">${escapeHtml(representativeType)}</td>
          <td class="px-4 py-3 text-gray-700 whitespace-nowrap">${formattedDate || 'N/A'}</td>
          <td class="px-4 py-3 text-gray-700 whitespace-nowrap">${escapeHtml((batch.uploadedBy && String(batch.uploadedBy).trim()) || 'Not recorded')}</td>
          <td class="px-4 py-3 whitespace-nowrap">
            <span class="badge ${statusClass} text-xs">${statusText}</span>
          </td>
          <td class="px-4 py-3 whitespace-nowrap">
            <div class="flex items-center justify-end gap-2">
              ${documentUploadActionsMenu(batch)}
            </div>
          </td>
        `;

            tbody.appendChild(row);
        });

        if (!rowsRendered) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = `
          <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">
            No uploaded documents available.
          </td>
        `;
            tbody.appendChild(emptyRow);
        }

        tableWrapper.appendChild(table);
        elements.listView.appendChild(tableWrapper);

        lucide.createIcons();

        elements.listView.querySelectorAll('.preview-batch').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('details')?.removeAttribute('open');
                const id = btn.getAttribute('data-id');
                const docIndex = parseInt(btn.getAttribute('data-doc-index') || '0', 10);
                openPreview(id, Number.isNaN(docIndex) ? 0 : docIndex);
            });
        });

        elements.listView.querySelectorAll('.start-typing').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('details')?.removeAttribute('open');
                const id = btn.getAttribute('data-id');
                openFilePagesInPageTyping(id);
            });
        });

        elements.listView.querySelectorAll('.upload-more-to-folder').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                btn.closest('details')?.removeAttribute('open');
                const fileNumber = btn.getAttribute('data-file-number');
                handleUploadMoreToFolder(fileNumber);
            });
        });

        elements.listView.querySelectorAll('.move-upload-to-registry, .file-upload-into-master').forEach(btn => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openDocumentUploadEdmsAction(btn);
            });
        });

        elements.listView.querySelectorAll('.reassign-upload-file-number').forEach(btn => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openDocumentUploadReassign(btn);
            });
        });
    }

    async function renderFolderView(batches) {
        elements.folderView.innerHTML = '';

        const totalFolders = batches.length;
        const perPage = state.folderPagination.perPage;
        const adjustedTotal = totalFolders > 0 ? totalFolders : 1;
        const totalPages = Math.max(1, Math.ceil(adjustedTotal / perPage));
        state.folderPagination.totalPages = totalPages;
        if (state.folderPagination.currentPage > totalPages) {
            state.folderPagination.currentPage = totalPages;
        }
        if (state.folderPagination.currentPage < 1) {
            state.folderPagination.currentPage = 1;
        }

        const startIndex = (state.folderPagination.currentPage - 1) * perPage;
        const visibleBatches = batches.slice(startIndex, startIndex + perPage);

        for (const batch of visibleBatches) {
            if (!Array.isArray(batch.documents) || !batch.documents.length) {
                continue;
            }

            const folderWrapper = document.createElement('div');
            folderWrapper.className = 'border rounded-lg bg-white shadow-sm';

            const headerRow = document.createElement('div');
            headerRow.className = 'flex items-center';

            const documentCount = batch.documents.filter(doc => !doc.isPdfFolder).length;
            const pdfFolderCount = batch.documents.filter(doc => doc.isPdfFolder).length;

            const headerButton = document.createElement('button');
            headerButton.type = 'button';
            headerButton.className = 'flex-1 flex items-center justify-between px-4 py-3 text-left folder-header-button';
            headerButton.setAttribute('data-folder-toggle', batch.id);
            headerButton.setAttribute('aria-expanded', 'false');
            headerButton.innerHTML = `
          <div class="flex items-center gap-3">
            <i data-lucide="folder" class="h-5 w-5 text-blue-500"></i>
            <div>
              <p class="font-semibold text-gray-900">${escapeHtml(batch.fileNumber || 'Unknown File')}</p>
              <p class="text-xs text-gray-500">
                ${documentCount} document${documentCount === 1 ? '' : 's'}${pdfFolderCount ? ` - ${pdfFolderCount} PDF folder${pdfFolderCount === 1 ? '' : 's'}` : ''}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="badge badge-outline text-xs">${batch.status || 'Ready for page typing'}</span>
            <span class="badge badge-outline text-xs">${batch.date || ''}</span>
            <i data-lucide="chevron-down" class="h-4 w-4 text-gray-500 transition-transform" data-folder-chevron></i>
          </div>
        `;
            headerRow.appendChild(headerButton);

            const headerActions = document.createElement('div');
            headerActions.className = 'pr-4';
            headerActions.innerHTML = documentUploadActionsMenu(batch);
            headerRow.appendChild(headerActions);
            folderWrapper.appendChild(headerRow);

            const contentWrapper = document.createElement('div');
            contentWrapper.className = 'hidden border-t bg-gray-50';
            contentWrapper.setAttribute('data-folder-content', batch.id);

            const contentInner = document.createElement('div');
            contentInner.className = 'p-4 space-y-4';
            contentWrapper.appendChild(contentInner);
            folderWrapper.appendChild(contentWrapper);

            const hasPdfFolders = batch.documents.some(doc => doc.isPdfFolder);

            if (hasPdfFolders) {
                for (const doc of batch.documents) {
                    if (doc.isPdfFolder) {
                        const folderSection = document.createElement('div');
                        folderSection.className = 'border rounded-md overflow-hidden';
                        folderSection.innerHTML = `
                <div class="p-4 bg-yellow-50 border-b flex items-center justify-between">
                  <div>
                    <p class="font-medium text-blue-600">${escapeHtml(batch.fileNumber || '')} - ${escapeHtml(doc.folderName || 'PDF Folder')}</p>
                    <p class="text-xs text-gray-600">${doc.pageCount || 0} pages</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="badge bg-green-500 text-white text-xs">PDF Converted</span>
                  </div>
                </div>
                <div class="p-4">
                  <h4 class="text-sm font-medium mb-3">Pages</h4>
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 documents-grid" data-batch-id="${batch.id}" data-folder-id="${doc.id}"></div>
                </div>
              `;

                        contentInner.appendChild(folderSection);

                        const documentsGrid = folderSection.querySelector('.documents-grid');
                        const pdfPages = batch.documents.filter(page => page.parentFolder === doc.id);

                        for (const [index, page] of pdfPages.entries()) {
                            const pageItem = document.createElement('div');
                            pageItem.className = 'border rounded-md overflow-hidden cursor-pointer hover:border-blue-500 transition-colors document-item';
                            pageItem.setAttribute('data-batch-id', batch.id);
                            pageItem.setAttribute('data-folder-id', doc.id);
                            pageItem.setAttribute('data-page-index', index);

                            const previewKey = `${batch.id}-${doc.id}-${index}`;
                            let imageUrl = '/placeholder.svg';

                            if (page.file && page.file instanceof File) {
                                imageUrl = await storeFilePreview(page.file, previewKey);
                            } else if (page.serverPath) {
                                imageUrl = page.serverPath;
                            } else if (page.downloadUrl) {
                                imageUrl = page.downloadUrl;
                            }

                            pageItem.innerHTML = `
                  <div class="h-40 bg-muted flex items-center justify-center">
                    <img
                      src="${imageUrl}"
                      alt="Page ${index + 1}"
                      class="document-image max-h-full max-w-full object-contain"
                      onerror="this.src='/placeholder.svg'"
                    >
                  </div>
                  <div class="p-2 bg-gray-50 border-t">
                    <div class="flex justify-between items-center">
                                            <button type="button" class="text-sm font-medium text-left hover:text-blue-700 underline-offset-2 hover:underline open-page-typing" data-open-batch-id="${batch.id}" data-open-folder-id="${doc.id}">Page ${index + 1}</button>
                      <span class="badge ${getPaperSizeColor(page.paperSize)} text-white text-xs">${page.paperSize}</span>
                    </div>
                    <div class="mt-1">
                      <span class="badge badge-outline text-xs w-full justify-center">${page.documentType}</span>
                    </div>
                    <div class="mt-1 flex justify-between items-center">
                      <span class="badge bg-blue-500 text-white text-xs overflow-hidden text-ellipsis">
                        ${batch.fileNumber}-P${(index + 1).toString().padStart(2, '0')}
                      </span>
                      <button class="btn btn-ghost btn-xs text-red-500 delete-document"
                        data-server-path="${page.serverPath || ''}"
                        data-scan-id="${page.scanId || page.id || ''}"
                        style="padding: 2px; height: auto;">
                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                      </button>
                    </div>
                  </div>
                `;

                            documentsGrid.appendChild(pageItem);
                        }
                    }
                }

                const individualFiles = batch.documents.filter(doc => !doc.isPdfFolder && !doc.parentFolder);
                if (individualFiles.length > 0) {
                    const filesSection = document.createElement('div');
                    filesSection.className = 'border rounded-md overflow-hidden';
                    filesSection.innerHTML = `
              <div class="p-4 bg-blue-50 border-b flex items-center justify-between">
                <div>
                  <p class="font-medium text-blue-600">${escapeHtml(batch.fileNumber || '')}</p>
                  <p class="text-xs text-gray-500">${individualFiles.length} file${individualFiles.length === 1 ? '' : 's'}</p>
                </div>
              </div>
              <div class="p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 documents-grid" data-batch-id="${batch.id}"></div>
              </div>
            `;

                    contentInner.appendChild(filesSection);
                    const documentsGrid = filesSection.querySelector('.documents-grid');

                    for (const [index, doc] of individualFiles.entries()) {
                        const docItem = document.createElement('div');
                        docItem.className = 'border rounded-md overflow-hidden cursor-pointer hover:border-blue-500 transition-colors document-item';
                        docItem.setAttribute('data-batch-id', batch.id);
                        docItem.setAttribute('data-index', batch.documents.indexOf(doc));

                        const previewKey = `${batch.id}-${index}`;
                        let imageUrl = '/placeholder.svg';

                        if (doc.file && doc.file instanceof File) {
                            imageUrl = await storeFilePreview(doc.file, previewKey);
                        } else if (doc.serverPath) {
                            imageUrl = doc.serverPath;
                        }

                        docItem.innerHTML = `
                <div class="h-40 bg-muted flex items-center justify-center">
                  <img
                    src="${imageUrl}"
                    alt="${doc.fileName}"
                    class="document-image max-h-full max-w-full object-contain"
                    onerror="this.src='/placeholder.svg'"
                  >
                </div>
                <div class="p-2 bg-gray-50 border-t">
                  <div class="flex justify-between items-center">
                                        <button type="button" class="text-sm font-medium overflow-hidden text-ellipsis whitespace-nowrap text-left hover:text-blue-700 underline-offset-2 hover:underline open-page-typing" style="max-width: 100px;" data-open-batch-id="${batch.id}">${escapeHtml(doc.fileName || 'Document')}</button>
                    <span class="badge ${getPaperSizeColor(doc.paperSize)} text-white text-xs">${doc.paperSize}</span>
                  </div>
                  <div class="mt-1">
                    <span class="badge badge-outline text-xs w-full justify-center">${escapeHtml(doc.documentType || 'Document')}</span>
                  </div>
                  <div class="mt-1 flex justify-between items-center">
                    <span class="badge bg-blue-500 text-white text-xs mt-1 overflow-hidden text-ellipsis">
                      ${batch.fileNumber}-${(index + 1).toString().padStart(2, '0')}
                    </span>
                    <button class="btn btn-ghost btn-xs text-red-500 delete-document"
                      data-server-path="${doc.serverPath || ''}"
                      data-scan-id="${doc.scanId || doc.id || ''}"
                      style="padding: 2px; height: auto;">
                      <i data-lucide="trash-2" class="h-4 w-4"></i>
                    </button>
                  </div>
                </div>
              `;

                        documentsGrid.appendChild(docItem);
                    }
                }
            } else {
                const folderSection = document.createElement('div');
                folderSection.className = 'border rounded-md overflow-hidden';
                folderSection.innerHTML = `
            <div class="p-4 bg-muted/20 border-b flex items-center justify-between">
              <div>
                <p class="font-medium text-blue-600">${escapeHtml(batch.fileNumber || '')}</p>
                <p class="text-xs text-gray-500">${escapeHtml(batch.name || 'Scanned Documents')}</p>
              </div>
              <div class="flex items-center gap-2">
                <span class="badge badge-outline text-xs">${batch.documents.length} item${batch.documents.length === 1 ? '' : 's'}</span>
              </div>
            </div>
            <div class="p-4">
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 documents-grid" data-batch-id="${batch.id}"></div>
            </div>
          `;

                contentInner.appendChild(folderSection);
                const documentsGrid = folderSection.querySelector('.documents-grid');

                for (const [index, doc] of batch.documents.entries()) {
                    if (state.filterPaperSize !== 'All' && doc.paperSize !== state.filterPaperSize) {
                        continue;
                    }

                    const docItem = document.createElement('div');
                    docItem.className = 'border rounded-md overflow-hidden cursor-pointer hover:border-blue-500 transition-colors document-item';
                    docItem.setAttribute('data-batch-id', batch.id);
                    docItem.setAttribute('data-index', index);

                    const previewKey = `${batch.id}-${index}`;
                    let imageUrl = '/placeholder.svg';

                    if (doc.file && doc.file instanceof File) {
                        imageUrl = await storeFilePreview(doc.file, previewKey);
                    } else if (doc.serverPath) {
                        imageUrl = doc.serverPath;
                    }

                    docItem.innerHTML = `
                <div class="h-40 bg-muted flex items-center justify-center">
                  <img
                    src="${imageUrl}"
                    alt="Document ${index + 1}"
                    class="document-image max-h-full max-w-full object-contain"
                    onerror="this.src='/placeholder.svg'"
                  >
                </div>
                <div class="p-2 bg-gray-50 border-t">
                  <div class="flex justify-between items-center">
                                        <button type="button" class="text-sm font-medium text-left hover:text-blue-700 underline-offset-2 hover:underline open-page-typing" data-open-batch-id="${batch.id}">${escapeHtml(doc.fileName || `Document ${index + 1}`)}</button>
                    <span class="badge ${getPaperSizeColor(doc.paperSize)} text-white text-xs">${doc.paperSize}</span>
                  </div>
                  <div class="mt-1">
                    <span class="badge badge-outline text-xs w-full justify-center">${escapeHtml(doc.documentType || 'Document')}</span>
                  </div>
                  <div class="mt-1 flex justify-between items-center">
                    <span class="badge bg-blue-500 text-white text-xs mt-1 overflow-hidden text-ellipsis">
                      ${batch.fileNumber}-${(index + 1).toString().padStart(2, '0')}
                    </span>
                    <button class="btn btn-ghost btn-xs text-red-500 delete-document"
                      data-server-path="${doc.serverPath || ''}"
                      data-scan-id="${doc.scanId || doc.id || ''}"
                      style="padding: 2px; height: auto;">
                      <i data-lucide="trash-2" class="h-4 w-4"></i>
                    </button>
                  </div>
                </div>
              `;

                    documentsGrid.appendChild(docItem);
                }
            }

            elements.folderView.appendChild(folderWrapper);
        }

        lucide.createIcons();

        elements.folderView.querySelectorAll('[data-folder-toggle]').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-folder-toggle');
                const content = elements.folderView.querySelector(`[data-folder-content="${targetId}"]`);
                if (!content) {
                    return;
                }
                const willOpen = content.classList.contains('hidden');
                content.classList.toggle('hidden');
                btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                const chevron = btn.querySelector('[data-folder-chevron]');
                if (chevron) {
                    chevron.style.transform = willOpen ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            });
        });

        elements.folderView.querySelectorAll('.preview-batch').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('details')?.removeAttribute('open');
                const id = btn.getAttribute('data-id');
                const docIndex = parseInt(btn.getAttribute('data-doc-index') || '0', 10);
                openPreview(id, Number.isNaN(docIndex) ? 0 : docIndex);
            });
        });

        elements.folderView.querySelectorAll('.start-typing').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('details')?.removeAttribute('open');
                const id = btn.getAttribute('data-id');
                const folderId = btn.getAttribute('data-folder');
                openFilePagesInPageTyping(id, { folderId });
            });
        });

        document.querySelectorAll('.document-item').forEach(item => {
            item.addEventListener('click', () => {
                const batchId = item.getAttribute('data-batch-id');
                const folderId = item.getAttribute('data-folder-id');
                const index = parseInt(item.getAttribute('data-index') || item.getAttribute('data-page-index') || 0, 10);
                openPreview(batchId, index, folderId);
            });
        });

        document.querySelectorAll('.open-page-typing').forEach(linkBtn => {
            linkBtn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                const batchId = linkBtn.getAttribute('data-open-batch-id');
                const folderId = linkBtn.getAttribute('data-open-folder-id');
                openFilePagesInPageTyping(batchId, { folderId });
            });
        });

        document.querySelectorAll('.delete-document').forEach(btn => {
            const lfButton = document.createElement('button');
            lfButton.type = 'button'; lfButton.className = 'btn btn-outline btn-xs';
            lfButton.textContent = 'Replace with LF Scan';
            lfButton.onclick = event => { event.stopPropagation(); replaceUploadedWithLF(btn.dataset.scanId); };
            btn.before(lfButton);
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const scanId = btn.getAttribute('data-scan-id');
                const serverPath = btn.getAttribute('data-server-path');
                if (!scanId && !serverPath) {
                    alert('No identifiers found for deletion.');
                    return;
                }

                deleteDocument({
                    scanId: scanId || null,
                    serverPath: serverPath || null
                });
            });
        });

        elements.folderView.querySelectorAll('.upload-more-to-folder').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                btn.closest('details')?.removeAttribute('open');
                const fileNumber = btn.getAttribute('data-file-number');
                handleUploadMoreToFolder(fileNumber);
            });
        });

        elements.folderView.querySelectorAll('.move-upload-to-registry, .file-upload-into-master').forEach(btn => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openDocumentUploadEdmsAction(btn);
            });
        });

        elements.folderView.querySelectorAll('.reassign-upload-file-number').forEach(btn => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openDocumentUploadReassign(btn);
            });
        });
    }

    function updateFolderPaginationUI(totalFolders) {
        if (!elements.folderPaginationContainer || !elements.folderPaginationSummary) {
            return;
        }

        if (!state.showFolderView || totalFolders === 0) {
            elements.folderPaginationContainer.classList.add('hidden');
            elements.folderPaginationSummary.textContent = '';
            return;
        }

        const perPage = state.folderPagination.perPage;
        const totalPages = Math.max(1, Math.ceil(totalFolders / perPage));
        state.folderPagination.totalPages = totalPages;

        if (state.folderPagination.currentPage > totalPages) {
            state.folderPagination.currentPage = totalPages;
        }

        const start = (state.folderPagination.currentPage - 1) * perPage + 1;
        const end = Math.min(start + perPage - 1, totalFolders);

        elements.folderPaginationSummary.textContent = `Showing folders ${start}-${end} of ${totalFolders}`;
        elements.folderPaginationContainer.classList.remove('hidden');

        const prevBtn = elements.folderPaginationContainer.querySelector('[data-folder-page=\"prev\"]');
        const nextBtn = elements.folderPaginationContainer.querySelector('[data-folder-page=\"next\"]');

        if (prevBtn) {
            prevBtn.disabled = state.folderPagination.currentPage === 1;
        }
        if (nextBtn) {
            nextBtn.disabled = state.folderPagination.currentPage >= totalPages;
        }
    }


    function updatePreview() {
        renderPreviewModal();
    }

    function renderIndexedFiles() {
        if (!elements.indexedFilesList) {
            return;
        }

        const registrySelected = elements.blindScanRegistry?.value;

        // Reset list and disable search if no registry
        if (!registrySelected) {
            elements.indexedFilesList.innerHTML = `
                <div class="p-8 text-center text-muted-foreground">
                    <i data-lucide="info" class="h-8 w-8 mx-auto mb-2 opacity-20"></i>
                    <p class="text-sm">Please select a registry first to view available files.</p>
                </div>
            `;
            lucide.createIcons();
            syncConfirmButtonState(true);
            return;
        }

        // Enable search if registry is selected

        elements.indexedFilesList.innerHTML = '';

        if (indexedFilesLoading) {
            elements.indexedFilesList.innerHTML = `
          <div class="p-4 text-sm text-muted-foreground">Loading indexed files...</div>
        `;
            syncConfirmButtonState(true);
            return;
        }

        if (indexedFilesError) {
            const errorMessage = document.createElement('div');
            errorMessage.className = 'p-4 text-sm text-red-600';
            errorMessage.textContent = indexedFilesError;
            elements.indexedFilesList.appendChild(errorMessage);
            syncConfirmButtonState(true);
            return;
        }

        // Filter out files that are already in active batches
        const usedFileNumbers = new Set(state.documentBatches.map(b => b.fileNumber));
        const availableFiles = indexedFiles.filter(file => !usedFileNumbers.has(file.fileNumber));
        const selectedId = state.selectedIndexedFile ? String(state.selectedIndexedFile) : null;

        // If a file is selected via smart selector, only show that exact file to avoid wrong picks
        const filteredFiles = selectedId
            ? availableFiles.filter(file => String(file.id) === selectedId)
            : availableFiles;

        if (!filteredFiles.length) {
            const message = state.indexedFilesSearchTerm
                ? `No indexed files found for "${state.indexedFilesSearchTerm}".`
                : 'Please use the "smart file number selector" above to find and select a file.';
            const emptyState = document.createElement('div');
            emptyState.className = 'p-4 text-sm text-blue-600 bg-blue-50/30 text-center italic';
            emptyState.textContent = message;
            elements.indexedFilesList.appendChild(emptyState);
            syncConfirmButtonState(true);
            return;
        }

        filteredFiles.forEach(file => {
            const fileId = String(file.id);
            const isSelected = selectedId === fileId;

            const fileItem = document.createElement('div');
            fileItem.className = `flex items-center p-4 cursor-pointer hover:bg-muted/50 transition-all ${isSelected ? 'bg-muted border-l-4 border-l-blue-500' : 'border-l-4 border-l-transparent'
                }`;
            fileItem.setAttribute('data-id', fileId);

            const landUse = file.landUseType ? escapeHtml(file.landUseType) : null;
            const district = file.district ? escapeHtml(file.district) : null;
            const fileNumber = escapeHtml(file.fileNumber || 'No File Number');
            const fileName = escapeHtml(file.fileTitle || file.name || 'Unnamed File');
            const badges = [];

            if (landUse) {
                badges.push(`<span class="badge badge-secondary text-xs">${landUse}</span>`);
            }

            if (district) {
                badges.push(`<span class="badge badge-outline text-xs">${district}</span>`);
            }

            const badgesHtml = badges.length
                ? `<div class="flex items-center gap-2 mt-2">${badges.join('')}</div>`
                : '';

            fileItem.innerHTML = `
          <div class="flex-1">
            <div class="flex items-center gap-3">
              <i data-lucide="folder" class="h-6 w-6 transition-colors ${isSelected ? 'text-blue-500' : 'text-gray-400'
                }"></i>
              <div class="flex-1">
                <p class="font-semibold ${isSelected ? 'text-blue-600' : 'text-gray-900'}">${fileNumber}</p>
                <p class="text-sm text-gray-600">${fileName}</p>
                ${badgesHtml}
              </div>
            </div>
          </div>
          ${isSelected ? `
            <div class="ml-3 flex-shrink-0">
              <div class="flex items-center justify-center w-8 h-8 rounded-full bg-green-100">
                <i data-lucide="check-circle-2" class="h-5 w-5 text-green-600"></i>
              </div>
            </div>
          ` : ''}
        `;

            elements.indexedFilesList.appendChild(fileItem);
        });

        // Initialize icons for the new elements
        lucide.createIcons();

        // Add event listeners
        elements.indexedFilesList.querySelectorAll('[data-id]').forEach(item => {
            item.addEventListener('click', () => {
                const id = item.getAttribute('data-id');
                selectIndexedFileTemp(id);
            });
        });

        // Update confirm button
        syncConfirmButtonState();
    }

    function populateDocumentDetailsForm() {
        const doc = state.uploadDocuments[state.currentDocumentIndex];

        if (!doc) return;

        const derivedName = doc.file?.name || doc.fileName || doc.originalName || 'Document';
        elements.documentName.textContent = derivedName;

        // Update paper size
        elements.paperSizeRadios.forEach(radio => {
            radio.checked = radio.value === doc.paperSize;
        });

        // Update document type
        elements.documentType.value = doc.documentType || 'Document';

        // Update notes
        elements.documentNotes.value = doc.notes || '';
    }

    function getFilteredBatches() {
        let filtered = state.documentBatches;

        // Filter by paper size
        if (state.filterPaperSize !== 'All') {
            filtered = filtered.filter(batch =>
                batch.documents.some(doc => doc.paperSize === state.filterPaperSize)
            );
        }

        // Search is now handled server-side via URL 'search' param,
        // so no client-side search filtering needed here.

        return filtered;
    }

    // Event handlers
    function switchTab(tabId) {
        state.activeTab = tabId;
        persistActiveTabInQuery();
        updateUI();
    }

    function handleServerPagination(pageNumber) {
        if (!pageNumber) {
            return;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('pag_next', pageNumber);
        url.searchParams.set('tab', state.activeTab);
        url.searchParams.set('view', state.showFolderView ? 'folder' : 'list');
        if (state.showFolderView) {
            url.searchParams.set('folder_page', state.folderPagination.currentPage);
        } else {
            url.searchParams.delete('folder_page');
        }
        // Preserve active search query across pagination
        if (state.searchQuery && state.searchQuery.trim()) {
            url.searchParams.set('search', state.searchQuery.trim());
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }

    function handleFolderPage(direction) {
        if (!direction) {
            return;
        }

        const filteredBatches = getFilteredBatches();
        const totalPages = Math.max(1, Math.ceil(((filteredBatches.length || 1)) / state.folderPagination.perPage));

        if (direction === 'prev' && state.folderPagination.currentPage > 1) {
            state.folderPagination.currentPage--;
        } else if (direction === 'next' && state.folderPagination.currentPage < totalPages) {
            state.folderPagination.currentPage++;
        } else {
            return;
        }

        persistFolderPageInQuery();
        updateUI();
    }

    async function handleFileSelect(e) {
        const previouslySelectedDocs = [...state.uploadDocuments];
        const files = normalizeFileInputCollection(e?.target?.files);
        if (!files.length) {
            alert('Select files to upload before converting.');
            return;
        }

        state.selectedUploadFiles = files;

        // Detect file types for PDF conversion
        detectFileTypes(files);

        if (state.pdfConversionEnabled && state.hasPdfFiles) {
            try {
                const processedFiles = await processPdfFilesForUpload(files);

                // Convert processed files to upload documents
                state.uploadDocuments = [];

                for (const item of processedFiles) {
                    if (item.type === 'folder' && item.isPdfFolder) {
                        // For PDF folders, add all pages as separate upload documents
                        for (const page of item.children) {
                            state.uploadDocuments.push({
                                file: page.file,
                                definition: state.uploadDocuments.length + 1,
                                definitionCode: state.selectedFileNumberForUpload ? `${state.uploadDocuments.length + 1}-${state.selectedFileNumberForUpload}` : null,
                                paperSize: 'A4',
                                documentType: 'Certificate',
                                isPdfFolder: true,
                                folderName: item.name,
                                pageCount: item.children.length,
                                originalFile: item.originalPdfFile,
                                displayOrder: state.uploadDocuments.length
                            });
                        }
                    } else {
                        state.uploadDocuments.push({
                            file: item.file,
                            definition: state.uploadDocuments.length + 1,
                            definitionCode: state.selectedFileNumberForUpload ? `${state.uploadDocuments.length + 1}-${state.selectedFileNumberForUpload}` : null,
                            paperSize: 'A4',
                            documentType: 'Other',
                            isConvertedPdf: item.isConvertedPdf || false,
                            displayOrder: state.uploadDocuments.length
                        });
                    }
                }

            } catch (error) {
                console.error("PDF conversion failed:", error);
                alert("PDF conversion failed. Uploading original files instead.");
                // Continue with original files if conversion fails
                state.uploadDocuments = files.map(file => ({
                    file,
                    definition: 1,
                    definitionCode: state.selectedFileNumberForUpload ? `1-${state.selectedFileNumberForUpload}` : null,
                    paperSize: 'A4',
                    documentType: 'Other',
                    displayOrder: 0 // Placeholder, will be reassigned below
                }));
            }
        } else {
            // Process files without conversion
            state.uploadDocuments = files.map(file => ({
                file,
                definition: 1,
                definitionCode: state.selectedFileNumberForUpload ? `1-${state.selectedFileNumberForUpload}` : null,
                paperSize: 'A4',
                documentType: 'Other',
                displayOrder: 0 // Placeholder, will be reassigned below
            }));
        }

        // Browsing another source must not discard pages already selected.
        state.uploadDocuments = [...previouslySelectedDocs, ...state.uploadDocuments];
        state.selectedUploadFiles = state.uploadDocuments.map(doc => doc.file).filter(Boolean);

        // Pre-generate previews for images
        await pregenerateImagePreviews();

        // Ensure sequential display order values
        state.uploadDocuments = state.uploadDocuments.map((doc, idx) => ({
            ...doc,
            displayOrder: idx
        }));

        applyDefinitionToStagedDocs();

        updateUI();
    }

    function openDocumentDetails(index) {
        state.currentDocumentIndex = index;
        state.showDocumentDetails = true;
        updateUI();
    }

    function updateDocumentDetails(index, updates) {
        state.uploadDocuments = state.uploadDocuments.map((doc, i) =>
            i === index ? { ...doc, ...updates } : doc
        );
        updateUI();
    }

    function removeFile(index) {
        state.selectedUploadFiles = state.selectedUploadFiles.filter((_, i) => i !== index);
        state.uploadDocuments = state.uploadDocuments.filter((_, i) => i !== index);
        updateUI();
    }

    function applyDefinitionToStagedDocs() {
        const fileNumber = state.selectedFileNumberForUpload || '';

        state.uploadDocuments = (state.uploadDocuments || []).map((doc, idx) => {
            const displayOrder = Number.isFinite(Number(doc.displayOrder)) ? Number(doc.displayOrder) : idx;
            const definition = displayOrder + 1;
            return {
                ...doc,
                displayOrder,
                definition,
                definitionCode: fileNumber ? `${definition}-${fileNumber}` : (doc.definitionCode || null)
            };
        });
    }

    function resetUpload() {
        state.uploadStatus = 'idle';
        state.uploadProgress = 0;
        state.selectedUploadFiles = [];
        state.uploadDocuments = [];
        state.blindScan.stagedFiles = [];
        detectFileTypes([]);
        state.lastBlindTransfer = { documents: [], indexedFile: null };
        updatePdfConversionButtonVisibility();
        elements.fileUpload.value = '';
        elements.uploadError.classList.add('hidden');
        if (state.selectedUploadMethod === 'blind') {
            state.selectedUploadMethod = null;
            updateUploadMethodInputs();
            updateSelectedUploadMethodLabel();
            toggleDirectUploadAvailability(false);
        }
        updateUI();
    }

    function sendToPageTyping() {
        if (state.documentBatches.length === 0) {
            alert('No files to send to page typing');
            return;
        }

        window.location.href = '/pagetyping';
    }

    function openPreview(batchId, documentIndex = 0, folderId = null) {
        const batch = state.documentBatches.find(b => b.id === batchId);
        if (!batch) {
            alert('Unable to open preview for this batch.');
            return;
        }
        const items = buildPreviewItemsFromBatch(batch, folderId);
        let relativeIndex = documentIndex || 0;
        if (folderId) {
            const folderDocs = batch.documents.filter(doc => doc.parentFolder === folderId);
            const targetDoc = batch.documents[documentIndex] || folderDocs[documentIndex] || null;
            const foundIndex = folderDocs.findIndex(doc => doc === targetDoc || (doc?.id && targetDoc?.id && doc.id === targetDoc.id));
            relativeIndex = foundIndex >= 0 ? foundIndex : 0;
        } else {
            const topLevelDocs = batch.documents.filter(doc => !doc.parentFolder);
            const targetDoc = batch.documents[documentIndex] || topLevelDocs[documentIndex] || null;
            const foundIndex = topLevelDocs.findIndex(doc => doc === targetDoc || (doc?.id && targetDoc?.id && doc.id === targetDoc.id));
            relativeIndex = foundIndex >= 0 ? foundIndex : relativeIndex;
        }
        if (!items.length) {
            alert('No documents available in this section.');
            return;
        }
        state.selectedFile = batchId;
        state.currentFolderId = folderId;
        openPreviewWithItems(items, relativeIndex, {
            source: 'uploaded',
            fileNumber: batch.fileNumber || null
        });
    }

    function closePreview() {
        state.previewOpen = false;
        state.currentFolderId = null;
        state.previewSettings.items = [];
        state.previewSettings.history = [];
        state.previewSettings.future = [];
        state.previewSettings.cropMode = false;
        state.previewSettings.fullScreen = false;
        state.previewSettings.sidebarCollapsed = false;
        state.previewSettings.selectMode = false;
        state.previewSettings.selectedForDelete.clear();
        cleanupPreviewTempUrls();
        if (elements.previewDialogContent) {
            elements.previewDialogContent.classList.remove('fullscreen');
        }
        if (elements.previewShell) {
            elements.previewShell.classList.remove('sidebar-hidden');
        }
        if (elements.previewDialog) {
            elements.previewDialog.classList.add('hidden');
            elements.previewDialog.classList.remove('is-fullscreen');
        }
        updateUI();
    }

    function nextPage() {
        if (!state.previewSettings.items.length) {
            return;
        }
        selectPreviewIndex(state.previewSettings.activeIndex + 1);
    }

    function prevPage() {
        if (!state.previewSettings.items.length) {
            return;
        }
        selectPreviewIndex(state.previewSettings.activeIndex - 1);
    }

    function setZoomLevel(nextLevel, options = {}) {
        const { pushHistory = false } = options;
        if (!state.previewOpen) {
            state.zoomLevel = nextLevel;
            return;
        }
        const clamped = Math.max(40, Math.min(nextLevel, 400));
        if (Math.abs(clamped - state.zoomLevel) < 0.1) {
            return;
        }
        if (pushHistory) {
            pushPreviewHistory();
        }
        state.zoomLevel = clamped;
        clampPreviewPan();
        applyPreviewTransform();
    }

    function zoomIn() {
        setZoomLevel(state.zoomLevel + 10);
    }

    function zoomOut() {
        setZoomLevel(state.zoomLevel - 10);
    }

    function rotateRight() {
        rotateCurrentPreview('right');
    }

    function rotateLeft() {
        rotateCurrentPreview('left');
    }

    function rotate() {
        rotateRight();
    }

    function selectIndexedFileTemp(fileId, skipWarning = false) {
        const normalizedId = String(fileId);
        const selectedFile = findIndexedFileById(normalizedId);

        // Check if the file already has uploads and show warning if not skipped
        if (!skipWarning && selectedFile && selectedFile.has_scans) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Existing Uploads Detected',
                    text: `The file number "${selectedFile.fileNumber}" already has ${selectedFile.scans_count} uploaded document(s). Do you want to continue and upload more?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, upload more',
                    cancelButtonText: 'No, cancel',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Re-run this function but skip the warning to proceed with selection
                        selectIndexedFileTemp(fileId, true);
                        renderIndexedFiles();
                    } else {
                        // Reset selection if canceled
                        const selectElement = elements.globalSelectedFileNoSelect;
                        if (selectElement) {
                            if (typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined' && $(selectElement).data('select2')) {
                                $(selectElement).val(null).trigger('change');
                            } else {
                                selectElement.value = '';
                            }
                        }
                        clearIndexedFileSelection();
                        renderIndexedFiles();
                    }
                });
                return;
            } else {
                // Fallback to native confirm if Swal is not available
                if (!confirm(`The file number "${selectedFile.fileNumber}" already has ${selectedFile.scans_count} uploaded document(s). Do you want to continue and upload more?`)) {
                    const selectElement = elements.globalSelectedFileNoSelect;
                    if (selectElement) {
                        if (typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined' && $(selectElement).data('select2')) {
                            $(selectElement).val(null).trigger('change');
                        } else {
                            selectElement.value = '';
                        }
                    }
                    clearIndexedFileSelection();
                    renderIndexedFiles();
                    return;
                }
            }
        }

        document.querySelectorAll('#indexed-files-list > div').forEach(item => {
            const id = item.getAttribute('data-id');
            const folderIcon = item.querySelector('[data-lucide="folder"]');
            const isSelected = id === normalizedId;

            item.classList.toggle('bg-muted', isSelected);
            if (folderIcon) {
                folderIcon.classList.toggle('text-blue-500', isSelected);
                folderIcon.classList.toggle('text-gray-400', !isSelected);
            }
        });

        state.selectedIndexedFile = normalizedId;

        state.selectedFileNumberForUpload = selectedFile ? selectedFile.fileNumber : null;
        applyDefinitionToStagedDocs();

        // Update the selected file badge
        const selectedBadge = document.getElementById('selected-file-badge');
        const selectedFileName = document.getElementById('selected-file-name');

        if (selectedFile) {
            selectedFileName.textContent = selectedFile.fileNumber;
            selectedBadge.classList.remove('hidden');
        } else {
            selectedBadge.classList.add('hidden');
        }

        syncConfirmButtonState();
        updateSelectedUploadMethodLabel();
        syncFileTypeControls();

        if (state.selectedUploadMethod === 'blind' && selectedFile) {
            openBlindScanModal();
        }
    }

    /* ────────────────────── File Type cascade (3 dropdowns) ──────────────────────
       Category -> Type -> Old/New, driven by the edms_file_types lookup table
       rendered into #scan-upload-file-type-tree by the Blade partial.

       Regular is a complete answer on its own, so it hides the other two
       dropdowns. Everything else reveals Type, and Type reveals Old/New (or
       Mother/Children, or Children/New File — the variant labels come from the
       lookup table, not from here).

       Whatever the three resolve to is written into the hidden
       #scan-upload-file-type input; every other part of this page reads that one
       value, exactly as it read the old single select.
       ──────────────────────────────────────────────────────────────────────── */

    let fileTypeTree = null;

    /** The catalogue, parsed once. Empty when the partial is not on the page. */
    function getFileTypeTree() {
        if (fileTypeTree) return fileTypeTree;

        const node = document.getElementById('scan-upload-file-type-tree');
        if (!node) return (fileTypeTree = {});

        try {
            fileTypeTree = JSON.parse(node.textContent || '{}') || {};
        } catch (error) {
            console.error('File type catalogue could not be parsed', error);
            fileTypeTree = {};
        }

        return fileTypeTree;
    }

    /** Find the catalogue row for a stored key, or null. */
    function findFileTypeByCode(code) {
        if (!code) return null;

        const tree = getFileTypeTree();
        for (const [categoryKey, category] of Object.entries(tree)) {
            for (const [typeKey, type] of Object.entries(category.types || {})) {
                for (const variant of type.variants || []) {
                    if (variant.code === code) {
                        return { categoryKey, typeKey, variantKey: variant.key || '', label: variant.label };
                    }
                }
            }
        }

        return null;
    }

    /** Replace a select's options, keeping `selected` if it is still offered. */
    function fillFileTypeSelect(select, options, placeholder, selected) {
        if (!select) return;

        select.innerHTML = '';
        select.appendChild(new Option(placeholder, ''));
        options.forEach(option => select.appendChild(new Option(option.label, option.value)));
        select.value = options.some(o => o.value === selected) ? selected : '';
    }

    /**
     * Repaint the Type and Old/New dropdowns for the chosen category, then
     * resolve the three into the hidden input.
     *
     * `keep` preserves the current type/variant where they still exist — so
     * re-entering a category does not silently drop a valid selection.
     */
    function refreshFileTypeCascade(keep = true) {
        const tree = getFileTypeTree();
        const categorySelect = elements.scanUploadFileTypeCategory;
        if (!categorySelect) return;

        const categoryKey = categorySelect.value || '';
        const category = tree[categoryKey] || null;
        const wantedType = keep ? (elements.scanUploadFileTypeType?.value || '') : '';
        const wantedVariant = keep ? (elements.scanUploadFileTypeVariant?.value || '') : '';

        // No category, or one that is its own answer (Regular): both extra
        // dropdowns stay hidden and the hidden input takes the category's only
        // code straight away.
        if (!category || !category.has_children) {
            elements.scanUploadFileTypeDetail?.classList.add('hidden');
            elements.scanUploadFileTypeVariantWrap?.classList.add('hidden');
            if (elements.scanUploadFileTypeType) elements.scanUploadFileTypeType.value = '';
            if (elements.scanUploadFileTypeVariant) elements.scanUploadFileTypeVariant.value = '';
            commitFileTypeSelection(category ? onlyCodeIn(category) : '');
            return;
        }

        elements.scanUploadFileTypeDetail?.classList.remove('hidden');

        const types = Object.entries(category.types || {})
            .map(([value, type]) => ({ value, label: type.label }));
        fillFileTypeSelect(elements.scanUploadFileTypeType, types, 'Select type…', wantedType);

        const typeKey = elements.scanUploadFileTypeType?.value || '';
        const type = typeKey ? (category.types || {})[typeKey] : null;
        const variants = (type?.variants || []).filter(v => v.key !== '');

        // A type with no real variants (none today, but the lookup table allows
        // one) needs no third dropdown.
        if (!type || variants.length === 0) {
            elements.scanUploadFileTypeVariantWrap?.classList.add('hidden');
            if (elements.scanUploadFileTypeVariant) elements.scanUploadFileTypeVariant.value = '';
            commitFileTypeSelection(type ? (type.variants?.[0]?.code || '') : '');
            return;
        }

        elements.scanUploadFileTypeVariantWrap?.classList.remove('hidden');
        if (elements.scanUploadFileTypeVariantLabel) {
            elements.scanUploadFileTypeVariantLabel.textContent = variants.map(v => v.label).join(' / ');
        }
        fillFileTypeSelect(
            elements.scanUploadFileTypeVariant,
            variants.map(v => ({ value: v.key, label: v.label })),
            'Select…',
            wantedVariant
        );

        const variantKey = elements.scanUploadFileTypeVariant?.value || '';
        const chosen = variants.find(v => v.key === variantKey);
        commitFileTypeSelection(chosen ? chosen.code : '');
    }

    /** The single code under a category that has no further choices. */
    function onlyCodeIn(category) {
        const type = Object.values(category.types || {})[0];

        return type?.variants?.[0]?.code || '';
    }

    /** Write the resolved key into the hidden input and update the hint line. */
    function commitFileTypeSelection(code) {
        if (elements.scanUploadFileType) {
            elements.scanUploadFileType.value = code || '';
        }

        const hint = elements.scanUploadFileTypeHint;
        if (!hint) return;

        if (!code) {
            hint.textContent = elements.scanUploadFileTypeCategory?.value
                ? 'Finish the selection to choose a master folder.'
                : 'Leave as "Not specified" to keep the file directly under its registry.';
            return;
        }

        const found = findFileTypeByCode(code);
        const category = getFileTypeTree()[found?.categoryKey] || null;
        const type = category?.types?.[found?.typeKey] || null;
        const trail = [category?.label, type?.label, found?.variantKey ? found.label : null]
            .filter(Boolean)
            .join(' › ');
        hint.textContent = trail ? 'Scans will be filed under ' + trail + '.' : '';
    }

    /** Drive the three dropdowns from a stored key (or clear them for a blank). */
    function setFileTypeFromCode(code) {
        const found = findFileTypeByCode(code);

        if (elements.scanUploadFileTypeCategory) {
            elements.scanUploadFileTypeCategory.value = found ? found.categoryKey : '';
        }
        if (elements.scanUploadFileTypeType) {
            elements.scanUploadFileTypeType.value = found ? found.typeKey : '';
        }
        if (elements.scanUploadFileTypeVariant) {
            elements.scanUploadFileTypeVariant.value = found ? found.variantKey : '';
        }

        refreshFileTypeCascade(true);
    }

    /** Bound once, when the dialog wiring runs. */
    function bindFileTypeCascade() {
        elements.scanUploadFileTypeCategory?.addEventListener('change', () => refreshFileTypeCascade(false));
        elements.scanUploadFileTypeType?.addEventListener('change', () => {
            if (elements.scanUploadFileTypeVariant) elements.scanUploadFileTypeVariant.value = '';
            refreshFileTypeCascade(true);
        });
        elements.scanUploadFileTypeVariant?.addEventListener('change', () => refreshFileTypeCascade(true));
    }

    /**
     * Keep the File Type controls in step with the selected file.
     *
     * A file that has already been classified reappears with its own master
     * folder preselected, so a second batch of scans lands beside the first
     * instead of quietly starting a new folder. The cover button only lights up
     * for a real indexing id — the global picker uses "GLOBAL_<number>" ids that
     * the cover endpoint cannot look up.
     */
    function syncFileTypeControls() {
        const select = elements.scanUploadFileType;
        const button = elements.previewCoverBtn;
        const selectedFile = findIndexedFileById(state.selectedIndexedFile);

        hideCoverPreview();

        if (select) {
            const existing = selectedFile?.edmsFileType || selectedFile?.edms_file_type || '';
            // `select` is the hidden input the cascade writes to now; driving the
            // three dropdowns is what actually sets its value.
            setFileTypeFromCode(existing);
        }

        if (button) {
            button.disabled = !numericIndexingId();
        }

        if (numericIndexingId()) {
            loadSelectedFilePagePreview();
        }
    }

    /** The selected file's real file_indexings id, or null for a synthetic one. */
    function numericIndexingId() {
        const id = Number(state.selectedIndexedFile);

        return Number.isInteger(id) && id > 0 ? id : null;
    }

    function hideCoverPreview(clearPages = true) {
        state.selectedFilePreviewToken++;
        elements.coverPreview?.classList.add('hidden');
        if (elements.coverPreviewBody) {
            elements.coverPreviewBody.innerHTML = '';
        }
        elements.coverPreviewNavigation?.classList.add('hidden');
        elements.coverPreviewNavigation?.classList.remove('flex');
        if (clearPages) {
            state.selectedFilePreviewPages = [];
            state.selectedFilePreviewIndex = 0;
        }
    }

    function renderSelectedFilePagePreview() {
        const box = elements.coverPreview;
        const body = elements.coverPreviewBody;
        const pages = state.selectedFilePreviewPages;
        const index = state.selectedFilePreviewIndex;
        const page = pages[index];

        if (!box || !body || !page) return;

        box.classList.remove('hidden');
        body.innerHTML = page.is_pdf
            ? `<embed src="${escapeHtml(page.url)}#page=1&view=FitH" type="application/pdf" class="w-full h-[320px]">`
            : `<a href="${escapeHtml(page.url)}" target="_blank" rel="noopener">
                 <img src="${escapeHtml(page.url)}" alt="${escapeHtml(page.filename || `Page ${index + 1}`)}" class="max-h-[320px] object-contain">
               </a>`;

        const showNavigation = pages.length > 1;
        elements.coverPreviewNavigation?.classList.toggle('hidden', !showNavigation);
        elements.coverPreviewNavigation?.classList.toggle('flex', showNavigation);
        if (elements.coverPreviewPosition) {
            elements.coverPreviewPosition.textContent = `Page ${index + 1} of ${pages.length}`;
        }
        if (elements.coverPreviewPrevious) {
            elements.coverPreviewPrevious.disabled = index === 0;
        }
        if (elements.coverPreviewNext) {
            elements.coverPreviewNext.disabled = index >= pages.length - 1;
        }
        if (window.lucide) lucide.createIcons();
    }

    function navigateSelectedFilePreview(direction) {
        const nextIndex = state.selectedFilePreviewIndex + Number(direction);
        if (nextIndex < 0 || nextIndex >= state.selectedFilePreviewPages.length) return;

        state.selectedFilePreviewIndex = nextIndex;
        renderSelectedFilePagePreview();
    }

    async function loadSelectedFilePagePreview() {
        const box = elements.coverPreview;
        const body = elements.coverPreviewBody;
        const id = numericIndexingId();
        if (!box || !body || !id) return;

        const token = ++state.selectedFilePreviewToken;
        state.selectedFilePreviewPages = [];
        state.selectedFilePreviewIndex = 0;

        box.classList.remove('hidden');
        body.innerHTML = '<p class="text-sm text-gray-500">Loading cover…</p>';
        elements.coverPreviewNavigation?.classList.add('hidden');
        elements.coverPreviewNavigation?.classList.remove('flex');

        try {
            const response = await fetch(`/edms/file-type/cover?file_indexing_id=${encodeURIComponent(id)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const payload = await response.json();
            if (token !== state.selectedFilePreviewToken || id !== numericIndexingId()) return;
            const data = payload.data || {};
            const pages = Array.isArray(data.pages) && data.pages.length
                ? data.pages
                : (data.url ? [{
                    url: data.url,
                    filename: data.filename,
                    is_pdf: data.is_pdf,
                    scanning_id: data.scanning_id,
                }] : []);

            if (!pages.length) {
                body.innerHTML = `<p class="text-sm text-gray-500 text-center px-4">${escapeHtml(data.message || 'No cover available.')}</p>`;
                return;
            }

            state.selectedFilePreviewPages = pages;
            state.selectedFilePreviewIndex = 0;
            renderSelectedFilePagePreview();
        } catch (error) {
            if (token !== state.selectedFilePreviewToken) return;
            body.innerHTML = `<p class="text-sm text-red-600 text-center px-4">Could not load the cover: ${escapeHtml(error.message)}</p>`;
        }
    }

    async function toggleCoverPreview() {
        if (!elements.coverPreview?.classList.contains('hidden')) {
            hideCoverPreview(false);
            return;
        }

        if (state.selectedFilePreviewPages.length) {
            renderSelectedFilePagePreview();
            return;
        }

        await loadSelectedFilePagePreview();
    }

    function selectIndexedFile() {
        const selectedFile = findIndexedFileById(state.selectedIndexedFile);

        if (!selectedFile) {
            alert('Please select an indexed file before continuing.');
            return;
        }

        if (!state.selectedUploadMethod) {
            alert('Please select an upload method before continuing.');
            return;
        }

        state.selectedFileNumberForUpload = selectedFile.fileNumber;
        applyDefinitionToStagedDocs();

        if (state.selectedUploadMethod === 'blind') {
            if (state.blindScan.loading) {
                alert('Please wait while the blind scan documents are loaded.');
                return;
            }

            if (state.blindScan.error) {
                alert(state.blindScan.error);
                return;
            }

            if (!state.blindScan.files.length) {
                alert('No blind scan documents were found for this file number.');
                return;
            }

            // "Next" in the Blind Scan dialog is what stages the files.
            // If the operator confirmed without going through it, stage them here
            // rather than losing their selection.
            if (!(state.blindScan.stagedFiles || []).length) {
                stageBlindScanFiles();
                return;
            }

            // Confirmed: only now do the staged pages reach the upload screen.
            commitStagedBlindScanFiles();
        }

        state.showFileSelector = false;
        updateUI();
        const methodLabel = getUploadMethodLabel(state.selectedUploadMethod);
        const methodSuffix = methodLabel ? ` via <strong>${methodLabel}</strong>` : '';
        showNotification(`Ready to upload documents to <strong>${selectedFile.fileNumber}</strong>${methodSuffix}`);

    }

    function deleteBatch(id) {
        if (confirm('Are you sure you want to delete this batch?')) {
            state.documentBatches = state.documentBatches.filter(batch => batch.id !== id);
            updateUI();
        }
    }

    function saveDocumentDetails() {
        if (state.currentDocumentIndex === null) return;

        const paperSize = Array.from(elements.paperSizeRadios).find(radio => radio.checked)?.value || 'A4';
        const documentType = elements.documentType.value;
        const notes = elements.documentNotes.value;

        updateDocumentDetails(state.currentDocumentIndex, {
            paperSize: paperSize,
            documentType: documentType,
            notes: notes
        });

        state.showDocumentDetails = false;
        updateUI();
    }

    // Initialize the page
    async function init() {
        document.addEventListener('toggle', (event) => {
            const menu = event.target.closest?.('.document-upload-actions-menu');
            if (menu?.open) {
                requestAnimationFrame(() => positionDocumentUploadActionsMenu(menu));
            }
        }, true);

        document.addEventListener('click', (event) => {
            document.querySelectorAll('.document-upload-actions-menu[open]').forEach(menu => {
                if (!menu.contains(event.target)) {
                    menu.removeAttribute('open');
                }
            });
        });

        // Initialize Core Dependencies
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Initialize DOM Elements and Config
        elements = queryDomElements();
        uploadProgressElements = queryUploadProgressElements();
        blindScanElements = queryBlindScanElements();
        initConfig();

        const urlParams = new URLSearchParams(window.location.search);
        const initialTab = urlParams.get('tab');
        if (initialTab === 'uploaded-files') {
            state.activeTab = 'uploaded-files';
        }
        const viewParam = urlParams.get('view');
        if (viewParam === 'folder') {
            state.showFolderView = true;
        }
        const folderPageParam = parseInt(urlParams.get('folder_page') || '1', 10);
        if (!Number.isNaN(folderPageParam) && folderPageParam > 0) {
            state.folderPagination.currentPage = folderPageParam;
        }

        // Restore search query from URL
        const searchParam = urlParams.get('search');
        if (searchParam) {
            state.searchQuery = searchParam;
        }

        // Set up event listeners

        // Tab switching
        elements.tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.getAttribute('data-tab');
                switchTab(tabId);
            });
        });

        // File upload
        elements.fileUpload.addEventListener('change', handleFileSelect);
        document.getElementById('browse-large-format-scans').onclick = () => {
            if (state.uploadStatus === 'uploading') return;
            if (!state.selectedIndexedFile) { alert('Select an indexed file first.'); return; }
            const fileId = state.selectedIndexedFile;
            LargeFormatScans.open({ confirmLabel: 'Add to Upload Pages', fileIndexingId: fileId, onConfirm: async entry => {
                const file = await LargeFormatScans.file(entry);
                if (fileId !== state.selectedIndexedFile) throw new Error('The indexed file changed. Select it again.');
                state.uploadDocuments.push({ file, fileName: file.name, fileSize: file.size, paperSize: 'Custom',
                    documentType: 'Other', source: 'large-format', displayOrder: state.uploadDocuments.length });
                applyDefinitionToStagedDocs(); updateUI();
            }});
        };
        document.getElementById('preview-replace-lf').onclick = () => {
            const item = state.previewSettings.items[state.previewSettings.activeIndex];
            if (!item) return;
            if (item.context === 'uploaded') replaceUploadedWithLF(item.scanId);
            else replaceStagedWithLF(item.sourceIndex);
        };
        document.addEventListener('click', event => {
            const button = event.target.closest('[data-lf-batch]');
            if (!button) return;
            event.preventDefault(); event.stopPropagation();
            button.closest('details')?.removeAttribute('open');
            chooseUploadedLFPage(button.dataset.lfBatch);
        });
        elements.browseFilesBtn.addEventListener('click', () => elements.fileUpload.click());
        // PDF Conversion checkbox
        if (elements.convertPdfs) {
            elements.convertPdfs.addEventListener('change', (e) => {
                state.pdfConversionEnabled = e.target.checked;
                const filesForDetection = state.selectedUploadFiles.length > 0
                    ? state.selectedUploadFiles
                    : state.blindScan.stagedFiles;
                detectFileTypes(filesForDetection && filesForDetection.length ? filesForDetection : []);
            });
        }
        if (elements.convertPdfsBtn) {
            elements.convertPdfsBtn.addEventListener('click', handleManualPdfConversionClick);
        }

        // Select file
        elements.selectFileBtn.addEventListener('click', async () => {
            state.showFileSelector = true;
            updateUI();

            if (state.selectedIndexedFile || indexedFiles.length > 0) {
                renderIndexedFiles();
            } else {
                renderIndexedFiles(); // This will now show the 'No files' message if empty
            }
        });


        // Upload actions
        elements.clearAllBtn.addEventListener('click', resetUpload);
        elements.previewSelectedFilesBtn?.addEventListener('click', () => previewSelectedFile(0));
        elements.startUploadBtn.addEventListener('click', startUpload);
        elements.cancelUploadBtn.addEventListener('click', resetUpload);
        elements.uploadMoreBtn.addEventListener('click', resetUpload);
        elements.viewUploadedBtn.addEventListener('click', () => switchTab('uploaded-files'));
        elements.proceedToTypingBtn.addEventListener('click', sendToPageTyping);
        elements.goToUploadBtn.addEventListener('click', () => switchTab('upload'));

        // Search functionality — server-side search across all pages
        let searchDebounceTimer = null;
        elements.fileSearch.addEventListener('input', (e) => {
            state.searchQuery = e.target.value;
            state.folderPagination.currentPage = 1;

            // Debounce: wait 600ms after user stops typing, then do server search
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                const query = (state.searchQuery || '').trim();
                const url = new URL(window.location.href);
                if (query.length > 0) {
                    url.searchParams.set('search', query);
                    url.searchParams.set('pag_next', '1');
                } else {
                    url.searchParams.delete('search');
                }
                url.searchParams.set('tab', 'uploaded-files');
                url.searchParams.set('view', state.showFolderView ? 'folder' : 'list');
                window.location.href = url.toString();
            }, 600);
        });

        // Preview dialog
        if (elements.previewDialog) {
            elements.previewDialog.addEventListener('click', e => {
                if (e.target === elements.previewDialog) {
                    closePreview();
                }
            });
        }
        if (elements.closePreviewBtn) {
            elements.closePreviewBtn.addEventListener('click', closePreview);
        }
        if (elements.prevPageBtn) {
            elements.prevPageBtn.addEventListener('click', prevPage);
        }
        if (elements.nextPageBtn) {
            elements.nextPageBtn.addEventListener('click', nextPage);
        }
        if (elements.zoomInBtn) {
            elements.zoomInBtn.addEventListener('click', zoomIn);
        }
        if (elements.zoomOutBtn) {
            elements.zoomOutBtn.addEventListener('click', zoomOut);
        }
        if (elements.rotateBtn) {
            elements.rotateBtn.addEventListener('click', rotateRight);
        }
        if (elements.rotateLeftBtn) {
            elements.rotateLeftBtn.addEventListener('click', rotateLeft);
        }
        if (elements.resetViewBtn) {
            elements.resetViewBtn.addEventListener('click', () => resetPreviewView());
        }
        if (elements.undoBtn) {
            elements.undoBtn.addEventListener('click', undoPreviewEdit);
        }
        if (elements.redoBtn) {
            elements.redoBtn.addEventListener('click', redoPreviewEdit);
        }
        if (elements.toggleFullscreenBtn) {
            elements.toggleFullscreenBtn.addEventListener('click', togglePreviewFullScreen);
        }
        if (elements.previewSidebarToggle) {
            elements.previewSidebarToggle.addEventListener('click', togglePreviewSidebar);
        }
        if (elements.previewUploadBtn && elements.previewUploadInput) {
            elements.previewUploadBtn.addEventListener('click', () => elements.previewUploadInput.click());
            elements.previewUploadInput.addEventListener('change', handlePreviewUploadChange);
        }
        if (elements.previewEditBtn) {
            elements.previewEditBtn.addEventListener('click', handlePreviewEdit);
        }
        if (elements.previewReassignBtn) {
            elements.previewReassignBtn.addEventListener('click', handlePreviewReassign);
        }
        if (elements.previewDeleteBtn) {
            elements.previewDeleteBtn.addEventListener('click', handlePreviewDelete);
        }
        if (elements.cropToggleBtn) {
            elements.cropToggleBtn.addEventListener('click', () => {
                if (state.previewSettings.cropMode) {
                    exitCropMode();
                } else {
                    enterCropMode();
                }
            });
        }
        if (elements.cropApplyBtn) {
            elements.cropApplyBtn.addEventListener('click', applyCropSelection);
        }
        if (elements.cropCancelBtn) {
            elements.cropCancelBtn.addEventListener('click', () => {
                state.previewSettings.cropSelection = null;
                exitCropMode();
            });
        }
        if (elements.cropActionButtons) {
            ['pointerdown', 'mousedown', 'touchstart'].forEach(evt => {
                elements.cropActionButtons.addEventListener(evt, (event) => {
                    event.stopPropagation();
                });
            });
        }
        if (elements.previewThumbnails) {
            elements.previewThumbnails.addEventListener('click', event => {
                // Don't handle if it came from a checkbox
                if (event.target.closest('.preview-thumb-checkbox')) return;

                const thumb = event.target.closest('[data-preview-index]');
                if (!thumb) {
                    return;
                }
                const idx = parseInt(thumb.getAttribute('data-preview-index'), 10);
                if (!Number.isNaN(idx)) {
                    if (state.previewSettings.selectMode) {
                        togglePreviewItemSelection(idx);
                    } else {
                        selectPreviewIndex(idx);
                    }
                }
            });
            elements.previewThumbnails.addEventListener('dragstart', handlePreviewThumbDragStart);
            elements.previewThumbnails.addEventListener('dragover', handlePreviewThumbDragOver);
            elements.previewThumbnails.addEventListener('dragleave', handlePreviewThumbDragLeave);
            elements.previewThumbnails.addEventListener('drop', handlePreviewThumbDrop);
            elements.previewThumbnails.addEventListener('dragend', handlePreviewThumbDragEnd);
        }
        if (elements.previewImage) {
            elements.previewImage.addEventListener('load', () => {
                updatePreviewBounds();
                applyPreviewTransform();
            });
        }
        if (elements.previewImageWrapper) {
            elements.previewImageWrapper.addEventListener('pointerdown', handlePreviewPanStart);
        }
        if (elements.previewCanvas) {
            elements.previewCanvas.addEventListener('wheel', handlePreviewWheel, { passive: false });
        }
        if (elements.cropOverlay) {
            elements.cropOverlay.addEventListener('pointerdown', handleCropPointerDown);
        }
        window.addEventListener('pointermove', handlePreviewPanMove);
        window.addEventListener('pointerup', handlePreviewPanEnd);
        window.addEventListener('pointermove', handleCropPointerMove);
        window.addEventListener('pointerup', handleCropPointerUp);
        window.addEventListener('resize', handlePreviewResize);
        if (elements.applyPreviewBtn) {
            elements.applyPreviewBtn.addEventListener('click', applyPreviewChanges);
        }

        // File selector dialog
        elements.fileSelectorDialog.addEventListener('click', e => {
            if (e.target === elements.fileSelectorDialog) {
                state.showFileSelector = false;
                updateUI();
            }
        });
        elements.cancelFileSelectBtn.addEventListener('click', () => {
            state.showFileSelector = false;
            updateUI();
        });
        elements.confirmFileSelectBtn.addEventListener('click', selectIndexedFile);

        // File Type: Category -> Type -> Old/New.
        bindFileTypeCascade();
        refreshFileTypeCascade(false);

        // Cover preview: the instruction that decides the file type is on the cover.
        elements.previewCoverBtn?.addEventListener('click', toggleCoverPreview);
        elements.coverPreviewPrevious?.addEventListener('click', () => navigateSelectedFilePreview(-1));
        elements.coverPreviewNext?.addEventListener('click', () => navigateSelectedFilePreview(1));

        if (elements.uploadMethodRadios && elements.uploadMethodRadios.length) {
            elements.uploadMethodRadios.forEach(radio => {
                radio.addEventListener('change', (event) => {
                    const { value } = event.target;
                    state.selectedUploadMethod = value;
                    updateUploadMethodInputs();
                    syncConfirmButtonState();
                    updateSelectedUploadMethodLabel();
                    if (value === 'blind') {
                        if (state.selectedIndexedFile) {
                            openBlindScanModal();
                        }
                    } else {
                        closeBlindScanModal();
                    }
                    updateUI();
                });
            });
        }

        if (blindScanElements.registry) {
            blindScanElements.registry.addEventListener('change', () => {
                if (elements.globalSelectedFileNoSelect) {
                    const hasRegistry = Boolean(blindScanElements.registry.value);
                    elements.globalSelectedFileNoSelect.disabled = !hasRegistry;

                    if (!hasRegistry) {
                        if (typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
                            $(elements.globalSelectedFileNoSelect).val(null).trigger('change');
                        } else {
                            elements.globalSelectedFileNoSelect.value = '';
                        }
                        clearIndexedFileSelection();
                    }
                }
                renderIndexedFiles();
                syncConfirmButtonState();
            });
        }

        initializeSmartFileNumberSelector();

        // "Next" — stages the discovered files and shows them in Preview.
        if (blindScanElements.transferBtn) {
            blindScanElements.transferBtn.addEventListener('click', stageBlindScanFiles);
        }

        // Clicking the backdrop closes the dialog, as it does for every other modal.
        if (blindScanElements.modal) {
            blindScanElements.modal.addEventListener('click', (event) => {
                if (event.target === blindScanElements.modal) {
                    closeBlindScanModal();
                    updateUI();
                }
            });
        }

        if (blindScanElements.closeButtons && blindScanElements.closeButtons.length) {
            blindScanElements.closeButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    closeBlindScanModal();
                    if (state.selectedUploadMethod === 'blind') {
                        state.selectedUploadMethod = null;
                        updateUploadMethodInputs();
                        syncConfirmButtonState();
                        updateSelectedUploadMethodLabel();
                    }
                    updateUI();
                });
            });
        }

        if (uploadProgressElements.closeBtn) {
            uploadProgressElements.closeBtn.addEventListener('click', () => {
                closeUploadProgressModal();
            });
        }

        if (elements.selectedFilesList) {
            elements.selectedFilesList.addEventListener('click', (event) => {
                const previewBtn = event.target.closest('.preview-file');
                if (previewBtn) {
                    event.preventDefault();
                    const index = parseInt(previewBtn.getAttribute('data-index'), 10);
                    if (!Number.isNaN(index)) {
                        previewSelectedFile(index);
                    }
                    return;
                }

                const editBtn = event.target.closest('.edit-details');
                if (editBtn) {
                    event.preventDefault();
                    const index = parseInt(editBtn.getAttribute('data-index'), 10);
                    if (!Number.isNaN(index)) {
                        openDocumentDetails(index);
                    }
                    return;
                }

                const removeBtn = event.target.closest('.remove-file');
                if (removeBtn) {
                    event.preventDefault();
                    const index = parseInt(removeBtn.getAttribute('data-index'), 10);
                    if (!Number.isNaN(index)) {
                        removeFile(index);
                    }
                }
            });
        }

        // Document details dialog
        elements.documentDetailsDialog.addEventListener('click', e => {
            if (e.target === elements.documentDetailsDialog) {
                state.showDocumentDetails = false;
                updateUI();
            }
        });
        elements.cancelDetailsBtn.addEventListener('click', () => {
            state.showDocumentDetails = false;
            updateUI();
        });
        elements.saveDetailsBtn.addEventListener('click', saveDocumentDetails);

        // Toggle view
        elements.toggleViewBtn.addEventListener('click', () => {
            state.showFolderView = !state.showFolderView;
            if (state.showFolderView) {
                state.folderPagination.currentPage = 1;
            }
            persistViewPreferenceInQuery();
            persistFolderPageInQuery();
            updateUI();
        });

        // Paper size filter
        elements.paperSizeFilter.addEventListener('change', () => {
            state.filterPaperSize = elements.paperSizeFilter.value;
            state.folderPagination.currentPage = 1;
            persistFolderPageInQuery();
            updateUI();
        });

        document.querySelectorAll('[data-pagination-btn]').forEach(btn => {
            btn.addEventListener('click', () => {
                const pageNumber = btn.getAttribute('data-page-number');
                handleServerPagination(pageNumber);
            });
        });

        document.querySelectorAll('[data-folder-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                handleFolderPage(btn.getAttribute('data-folder-page'));
            });
        });

        // Test server configuration first
        await testServerConfig();

        // Load data from server
        await loadServerData();

        // Initialize Scan Reassignment Manager
        if (typeof ScanReassignmentManager !== 'undefined') {
            window.scanReassignmentManager = new ScanReassignmentManager({});
        }

        // Initialize Global File Number Modal
        if (typeof GlobalFileNoModal !== 'undefined') {
            GlobalFileNoModal.init();
        }

        // Restore search input value from URL
        if (state.searchQuery && elements.fileSearch) {
            elements.fileSearch.value = state.searchQuery;
        }

        // Initial UI update
        updateUI();

        console.log('Application initialized successfully');
    }

    // Initialize the page when DOM is loaded
    document.addEventListener('DOMContentLoaded', init);
</script>
