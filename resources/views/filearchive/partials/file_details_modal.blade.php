<div id="file-details-dialog" class="dialog-backdrop" style="display: none;" aria-hidden="true" tabindex="-1">
    <div class="dialog-content animate-fade-in">
        <!-- Close button for mobile -->
        <div class="flex justify-end p-4 md:hidden border-b">
            <button onclick="window.closeFileDetails()" class="btn btn-ghost btn-sm">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
        
        <div class="flex flex-col lg:flex-row h-full">
            <!-- Left side - Document preview -->
            <div class="w-full md:w-2/5 bg-gray-50 p-6 flex flex-col border-r">
                <div class="flex items-center justify-between mb-4">
                    <span id="file-status-badge" class="badge badge-success">Archived</span>
                    <button id="toggle-star" class="btn btn-ghost h-8 w-8">
                        <i data-lucide="star" class="h-4 w-4"></i>
                    </button>
                </div>

                <div id="file-preview" class="flex-1 flex flex-col items-center justify-center">
                    <!-- Document preview -->
                    <div class="relative w-full max-w-[250px] aspect-3/4 bg-white rounded-lg shadow-md border overflow-hidden mx-auto">
                        <div class="absolute inset-0 flex flex-col bg-white">
                            <div class="h-8 bg-blue-500 flex items-center justify-between px-3">
                                <div class="flex space-x-1">
                                    <div class="w-3 h-3 rounded-full bg-gray-200 opacity-70"></div>
                                    <div class="w-3 h-3 rounded-full bg-gray-200 opacity-70"></div>
                                    <div class="w-3 h-3 rounded-full bg-gray-200 opacity-70"></div>
                                </div>
                                <span class="text-white font-medium text-xs" id="preview-page-count">
                                    Loading...
                                </span>
                            </div>
                            <div class="flex-1 flex flex-col p-4 overflow-hidden" id="preview-content">
                                <!-- Dynamic content based on page types -->
                                <div class="w-full h-3 bg-gray-200 rounded mb-2"></div>
                                <div class="w-3/4 h-3 bg-gray-200 rounded mb-3"></div>
                                <div class="w-full h-2 bg-gray-100 rounded mb-2"></div>
                                <div class="w-full h-2 bg-gray-100 rounded mb-2"></div>
                                <div class="w-5/6 h-2 bg-gray-100 rounded mb-3"></div>
                                <div class="w-full h-2 bg-gray-100 rounded mb-2"></div>
                                <div class="w-4/5 h-2 bg-gray-100 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Format:</span>
                        <span id="file-format" class="badge badge-outline font-mono">PDF</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Pages:</span>
                        <span id="file-pages" class="font-medium">Loading...</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Page Types:</span>
                        <span id="file-page-types" class="font-medium">Loading...</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Completed:</span>
                        <span id="file-completion-date" class="font-medium">Loading...</span>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button id="close-details" class="btn btn-outline w-full" onclick="window.closeFileDetails()">Close</button>
                    <button id="view-document" class="btn btn-primary w-full" onclick="viewDocument()">View Document</button>
                </div>
            </div>

            <!-- Right side - Document details -->
            <div class="w-full md:w-3/5 p-6">
                <div class="mb-6">
                    <h2 id="file-name" class="text-2xl font-bold">Loading...</h2>
                </div>

                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">File Number</h4>
                            <div class="flex items-center">
                                <div class="w-2 h-2 rounded-full bg-green-500 mr-2"></div>
                                <p id="file-number" class="text-sm font-medium">Loading...</p>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Land Use Type</h4>
                            <div class="flex items-center">
                                <div class="w-2 h-2 rounded-full bg-blue-500 mr-2"></div>
                                <p id="land-use-type" class="text-sm font-medium">Loading...</p>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">District</h4>
                            <div class="flex items-center">
                                <div class="w-2 h-2 rounded-full bg-purple-500 mr-2"></div>
                                <p id="district" class="text-sm font-medium">Loading...</p>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">LGA</h4>
                            <p id="lga" class="text-sm">Loading...</p>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Created Date</h4>
                            <p id="created-date" class="text-sm">Loading...</p>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Last Updated</h4>
                            <p id="last-updated" class="text-sm">Loading...</p>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-medium text-gray-500 mb-2">Tags</h4>
                        <div id="file-tags" class="flex flex-wrap gap-1.5">
                            <span class="badge badge-secondary px-2 py-1">Loading...</span>
                        </div>
                    </div>

                    <div id="document-pages-container">
                        <h4 class="text-sm font-medium text-gray-500 mb-2">Page Classifications</h4>
                        <div class="border rounded-md overflow-hidden">
                            <div class="bg-gray-50 px-4 py-2 border-b grid grid-cols-12 text-xs font-medium text-gray-500">
                                <div class="col-span-1">#</div>
                                <div class="col-span-4">Page Type</div>
                                <div class="col-span-3">Subtype</div>
                                <div class="col-span-2">Serial</div>
                                <div class="col-span-2">Typed By</div>
                            </div>
                            <div id="document-pages" class="max-h-[300px] overflow-y-auto">
                                <div class="px-4 py-3 text-center text-gray-500">
                                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mx-auto mb-2"></div>
                                    Loading page classifications...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <button class="btn btn-outline btn-sm gap-1" onclick="downloadFile()">
                        <i data-lucide="download" class="h-4 w-4"></i>
                        Download
                    </button>
                    <button class="btn btn-outline btn-sm gap-1" onclick="printFile()">
                        <i data-lucide="printer" class="h-4 w-4"></i>
                        Print
                    </button>
                    {{-- <button class="btn btn-outline btn-sm gap-1" onclick="shareFile()">
                        <i data-lucide="share-2" class="h-4 w-4"></i>
                        Share
                    </button>
                    <button class="btn btn-outline btn-sm gap-1" onclick="editPageTyping()">
                        <i data-lucide="edit" class="h-4 w-4"></i>
                        Edit Typing
                    </button> --}}
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Global variable to store current file data
let currentFileData = null;

// Immediately executing function to ensure the modal is hidden
(function() {
    // Make absolutely sure the file details dialog is hidden on page load
    const hideFileDetails = function() {
        const dialog = document.getElementById('file-details-dialog');
        if (dialog) {
            dialog.classList.add('hidden');
            dialog.style.display = 'none';
            dialog.setAttribute('aria-hidden', 'true');
        }
    };
    
    // Hide immediately
    hideFileDetails();
    
    // Also hide after a tiny delay to override any other scripts
    setTimeout(hideFileDetails, 50);
    
    // And again after the page has fully loaded
    window.addEventListener('load', hideFileDetails);
    
    // Define a global function to close the dialog
    window.closeFileDetails = function() {
        const dialog = document.getElementById('file-details-dialog');
        if (dialog) {
            dialog.classList.add('hidden');
            dialog.style.display = 'none';
            dialog.setAttribute('aria-hidden', 'true');
        }
    };
    
    // Add a click handler to the backdrop to close when clicking outside
    const dialog = document.getElementById('file-details-dialog');
    if (dialog) {
        dialog.addEventListener('click', function(e) {
            if (e.target === dialog) {
                window.closeFileDetails();
            }
        });
    }
})();

// Function to show file details
function showFileDetails(fileId) {
    console.log('Loading file details for:', fileId);
    
    // Show the modal
    const dialog = document.getElementById('file-details-dialog');
    if (dialog) {
        dialog.classList.remove('hidden');
        dialog.style.display = 'block';
        dialog.setAttribute('aria-hidden', 'false');
    }
    
    // Fetch file details from server
    fetch(`/filearchive/file-details/${fileId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentFileData = data.file;
                populateFileDetails(data.file);
            } else {
                console.error('Failed to load file details');
                showError('Failed to load file details');
            }
        })
        .catch(error => {
            console.error('Error loading file details:', error);
            showError('Error loading file details');
        });
}

// Function to populate file details in the modal
function populateFileDetails(file) {
    // Basic file information
    document.getElementById('file-name').textContent = file.file_title || 'Unknown File';
    document.getElementById('file-number').textContent = file.file_number || 'N/A';
    document.getElementById('land-use-type').textContent = file.land_use_type || 'N/A';
    document.getElementById('district').textContent = file.district || 'N/A';
    document.getElementById('lga').textContent = file.lga || 'N/A';
    
    // Dates
    if (file.created_at) {
        document.getElementById('created-date').textContent = new Date(file.created_at).toLocaleDateString();
    }
    if (file.updated_at) {
        document.getElementById('last-updated').textContent = new Date(file.updated_at).toLocaleDateString();
    }
    
    // Page information
    document.getElementById('file-pages').textContent = file.pagetypings_count || '0';
    document.getElementById('preview-page-count').textContent = `${file.pagetypings_count || 0} Pages`;
    
    // Page types
    if (file.pagetypings && file.pagetypings.length > 0) {
        const uniqueTypes = [...new Set(file.pagetypings.map(p => p.page_type))];
        document.getElementById('file-page-types').textContent = uniqueTypes.length;
        
        // Completion date (last page typing date)
        const lastTyping = file.pagetypings[file.pagetypings.length - 1];
        if (lastTyping && lastTyping.created_at) {
            document.getElementById('file-completion-date').textContent = new Date(lastTyping.created_at).toLocaleDateString();
        }
    }
    
    // Tags
    const tags = [];
    if (file.land_use_type) tags.push(file.land_use_type);
    if (file.district) tags.push(file.district);
    if (file.pagetypings && file.pagetypings.length > 0) {
        const uniqueTypes = [...new Set(file.pagetypings.map(p => p.page_type))];
        tags.push(...uniqueTypes.slice(0, 3)); // Add first 3 unique page types
    }
    
    const tagsContainer = document.getElementById('file-tags');
    tagsContainer.innerHTML = tags.map(tag => 
        `<span class="badge badge-secondary px-2 py-1">${tag}</span>`
    ).join('');
    
    // Page classifications
    populatePageClassifications(file.pagetypings || []);
    
    // Update preview content based on page types
    updatePreviewContent(file.pagetypings || []);
}

// Function to populate page classifications
function populatePageClassifications(pagetypings) {
    const container = document.getElementById('document-pages');
    
    if (pagetypings.length === 0) {
        container.innerHTML = `
            <div class="px-4 py-3 text-center text-gray-500">
                No page classifications found
            </div>
        `;
        return;
    }
    
    const rows = pagetypings.map((page, index) => {
        // Handle page type - check if it's an object or raw value
        let pageTypeDisplay = 'Unknown';
        if (page.page_type) {
            if (typeof page.page_type === 'object' && page.page_type !== null) {
                pageTypeDisplay = page.page_type.PageType || page.page_type.name || 'Unknown';
            } else {
                pageTypeDisplay = page.page_type.toString();
            }
        }
        
        // Handle page subtype - check if it's an object or raw value
        let pageSubtypeDisplay = '-';
        if (page.page_subtype) {
            if (typeof page.page_subtype === 'object' && page.page_subtype !== null) {
                pageSubtypeDisplay = page.page_subtype.PageSubType || page.page_subtype.name || 'Unknown';
            } else {
                pageSubtypeDisplay = page.page_subtype.toString();
            }
        }
        
        // Handle typed by
        let typedByDisplay = 'System';
        if (page.typed_by) {
            if (typeof page.typed_by === 'object' && page.typed_by !== null) {
                typedByDisplay = page.typed_by.name || 
                               (page.typed_by.first_name + ' ' + page.typed_by.last_name) || 
                               'Unknown User';
            } else {
                typedByDisplay = page.typed_by.toString();
            }
        }
        
        return `
            <div class="px-4 py-2 border-b grid grid-cols-12 text-sm hover:bg-gray-50 cursor-pointer">
                <div class="col-span-1 font-medium text-gray-500">${page.page_number || index + 1}</div>
                <div class="col-span-4 truncate" title="${pageTypeDisplay}">${pageTypeDisplay}</div>
                <div class="col-span-3 truncate text-gray-600" title="${pageSubtypeDisplay}">${pageSubtypeDisplay}</div>
                <div class="col-span-2 truncate font-mono text-xs text-gray-500">${page.serial_number || '-'}</div>
                <div class="col-span-2 truncate text-xs text-gray-500" title="${typedByDisplay}">${typedByDisplay}</div>
            </div>
        `;
    }).join('');
    
    container.innerHTML = rows;
}

// Function to update preview content based on page types
function updatePreviewContent(pagetypings) {
    const previewContent = document.getElementById('preview-content');
    
    if (pagetypings.length === 0) return;
    
    const pageTypes = pagetypings.map(p => p.page_type);
    const hasDeeds = pageTypes.includes('Deed');
    const hasCertificates = pageTypes.includes('Certificate');
    const hasApplications = pageTypes.includes('Application Form');
    const hasMaps = pageTypes.includes('Map') || pageTypes.includes('Survey Plan');
    
    let content = '';
    
    if (hasCertificates) {
        // Certificate-style preview
        content = `
            <div class="w-full h-3 bg-blue-200 rounded mb-2"></div>
            <div class="w-3/4 h-3 bg-blue-200 rounded mb-3"></div>
            <div class="w-full flex justify-center my-2">
                <div class="w-16 h-12 bg-blue-100 rounded border-2 border-blue-300"></div>
            </div>
            <div class="w-full h-2 bg-gray-100 rounded mb-2"></div>
            <div class="w-full h-2 bg-gray-100 rounded mb-2"></div>
            <div class="w-4/5 h-2 bg-gray-100 rounded"></div>
        `;
    } else if (hasMaps) {
        // Map-style preview
        content = `
            <div class="w-full h-3 bg-green-200 rounded mb-2"></div>
            <div class="w-4/5 h-3 bg-green-200 rounded mb-3"></div>
            <div class="w-full bg-gray-100 rounded-sm mb-3 p-1 flex-1 flex items-center justify-center relative">
                <div class="w-full h-full bg-gray-50">
                    <div class="absolute w-1/2 h-px bg-gray-300 top-1/2 left-1/4"></div>
                    <div class="absolute w-px h-1/2 bg-gray-300 top-1/4 left-1/2"></div>
                    <div class="absolute w-4 h-4 rounded-full bg-green-100 border border-green-300 top-1/3 left-1/3"></div>
                    <div class="absolute w-3 h-3 rounded-full bg-blue-100 border border-blue-300 bottom-1/4 right-1/4"></div>
                </div>
            </div>
        `;
    } else if (hasApplications) {
        // Form-style preview
        content = `
            <div class="w-full h-3 bg-orange-200 rounded mb-3"></div>
            <div class="mb-2">
                <div class="w-1/4 h-2 bg-gray-200 mb-1"></div>
                <div class="w-full h-3 bg-gray-100 rounded border border-gray-200"></div>
            </div>
            <div class="mb-2">
                <div class="w-1/3 h-2 bg-gray-200 mb-1"></div>
                <div class="w-full h-3 bg-gray-100 rounded border border-gray-200"></div>
            </div>
            <div class="flex justify-end">
                <div class="w-1/4 h-4 bg-orange-500 rounded"></div>
            </div>
        `;
    } else {
        // Default document preview
        content = `
            <div class="w-full h-3 bg-gray-200 rounded mb-2"></div>
            <div class="w-3/4 h-3 bg-gray-200 rounded mb-3"></div>
            <div class="w-full h-2 bg-gray-100 rounded mb-2"></div>
            <div class="w-full h-2 bg-gray-100 rounded mb-2"></div>
            <div class="w-5/6 h-2 bg-gray-100 rounded mb-3"></div>
            <div class="w-full h-2 bg-gray-100 rounded mb-2"></div>
            <div class="w-4/5 h-2 bg-gray-100 rounded"></div>
        `;
    }
    
    previewContent.innerHTML = content;
}

// Function to show error message
function showError(message) {
    document.getElementById('file-name').textContent = 'Error';
    document.getElementById('document-pages').innerHTML = `
        <div class="px-4 py-3 text-center text-red-500">
            ${message}
        </div>
    `;
}

// Action functions
function viewDocument() {
    if (currentFileData) {
        const moduleParam = new URLSearchParams(window.location.search).get('url') || '';
        const pagesUrl = `/filearchive/document-pages/${currentFileData.id}` + (moduleParam ? `?url=${encodeURIComponent(moduleParam)}` : '');
        if (typeof window.openDocumentViewer === 'function') {
            window.openDocumentViewer(pagesUrl, true, {
                number: currentFileData.file_number || currentFileData.fileno || '-',
                title: currentFileData.file_title || currentFileData.file_name || '-'
            });
        }
    }
}

function downloadFile() {
    if (currentFileData) {
        // Implement download functionality
        alert('Download functionality will be implemented');
    }
}

function printFile() {
    if (currentFileData) {
        // Implement print functionality
        alert('Print functionality will be implemented');
    }
}

function shareFile() {
    if (currentFileData) {
        // Implement share functionality
        alert('Share functionality will be implemented');
    }
}

function editPageTyping() {
    if (currentFileData) {
        // Open page typing interface for editing
        window.open(`/pagetyping?file_indexing_id=${currentFileData.id}`, '_blank');
    }
}

// Make sure clicking outside the dialog and the close button works
(function() {
    function setupListeners() {
        const dialog = document.getElementById('file-details-dialog');
        const closeBtn = document.getElementById('close-details');
        
        // Ensure click outside closes modal
        if (dialog) {
            dialog.addEventListener('click', function(e) {
                if (e.target === this && window.closeFileDetails) {
                    window.closeFileDetails();
                }
            });
        }
        
        // Ensure close button works
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                if (window.closeFileDetails) {
                    window.closeFileDetails();
                }
            });
        }
    }
    
    // Run on load and with a small delay for good measure
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupListeners);
    } else {
        setupListeners();
        setTimeout(setupListeners, 100);
    }
})();
</script>
