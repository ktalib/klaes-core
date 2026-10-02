<script>
// Backend Integration for PageTyping
document.addEventListener('DOMContentLoaded', function() {
    // Override the existing functions with real backend calls
    
    // Load real statistics from backend
    async function loadRealStats() {
        try {
            const response = await fetch('{{ route("pagetyping.api.stats") }}');
            const data = await response.json();
            
            if (data.success) {
                // Update the stats in the UI
                if (elements.pendingCount) elements.pendingCount.textContent = data.stats.pending_count || 0;
                if (elements.inProgressCount) elements.inProgressCount.textContent = data.stats.in_progress_count || 0;
                if (elements.completedCount) elements.completedCount.textContent = data.stats.completed_count || 0;
                if (elements.pageTypeMoreCount) elements.pageTypeMoreCount.textContent = data.stats.pagetype_more_count || 0;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }
    
    // Load real PageType More files
    async function loadRealPageTypeMoreFiles() {
        try {
            const response = await fetch('{{ route("pagetyping.api.pagetype-more-files") }}');
            const data = await response.json();
            
            if (data.success) {
                // Replace the sample data with real data
                window.pageTypeMoreFiles = data.files.map(file => ({
                    id: file.id,
                    fileNumber: file.file_number,
                    name: file.file_title,
                    type: file.main_application?.applicant_name || 'Unknown',
                    existingPages: file.existing_pages,
                    newScans: file.new_scans,
                    totalPages: file.total_pages,
                    lastUpdated: file.last_updated,
                    status: file.status,
                    isUpdated: file.is_updated,
                    district: file.district,
                    lga: file.lga
                }));
                
                // Re-render the PageType More files
                renderPageTypeMoreFiles();
            }
        } catch (error) {
            console.error('Error loading PageType More files:', error);
        }
    }
    
    // Load real files by status
    async function loadRealFilesByStatus(status) {
        try {
            const response = await fetch(`{{ route("pagetyping.api.files") }}?status=${status}`);
            const data = await response.json();
            
            if (data.success) {
                return data.files;
            }
            return [];
        } catch (error) {
            console.error('Error loading files by status:', error);
            return [];
        }
    }
    
    // Enhanced startPageTypeMore function with real backend data
    window.startPageTypeMoreReal = async function(fileId) {
        try {
            const response = await fetch(`{{ route("pagetyping.api.pagetype-more") }}?file_indexing_id=${fileId}`);
            const data = await response.json();
            
            if (data.success) {
                state.selectedFile = fileId;
                state.pageTypeMoreMode = true;
                state.activeTab = "typing";
                state.showFolderView = true;
                
                // Store the real page data
                state.existingPageTypings = data.pages.filter(page => page.is_existing);
                state.newScans = data.pages.filter(page => !page.is_existing);
                state.combinedPages = data.pages;
                
                updateUI();
                renderRealPageTypeMoreInterface(data.file, data.pages);
            } else {
                alert('Error loading PageType More data: ' + data.message);
            }
        } catch (error) {
            console.error('Error starting PageType More:', error);
            alert('Error loading PageType More data');
        }
    };
    
    // Render real PageType More interface
    function renderRealPageTypeMoreInterface(file, pages) {
        const headerContent = `
            <div class="p-6 border-b">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">
                            <span class="text-blue-600">${file.file_number}</span> - PageType More
                            <span class="badge bg-orange-500 text-white ml-2">
                                <i data-lucide="plus-circle" class="h-3 w-3 mr-1"></i>
                                ${pages.filter(p => !p.is_existing).length} New Scans
                            </span>
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Continue page typing with existing ${pages.filter(p => p.is_existing).length} pages + ${pages.filter(p => !p.is_existing).length} new scans
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="btn btn-outline btn-sm back-to-pagetype-more">
                            <i data-lucide="arrow-left" class="h-4 w-4 mr-1"></i>
                            Back to PageType More
                        </button>
                    </div>
                </div>
            </div>
        `;

        const content = `
            ${headerContent}
            <div class="p-6">
                <div class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium">Combined File Pages</h3>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-muted-foreground">
                                ${pages.filter(p => p.is_existing).length} existing + ${pages.filter(p => !p.is_existing).length} new = ${pages.length} total pages
                            </span>
                            <span class="badge bg-blue-500 text-white">${file.file_number}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="combined-pages">
                        ${pages.map((page, index) => {
                            const isExisting = page.is_existing;
                            const isNew = !page.is_existing;
                            
                            return `
                                <div class="border rounded-md overflow-hidden cursor-pointer hover:border-blue-500 transition-colors combined-page ${
                                    isExisting ? 'border-green-500 bg-green-50' : 
                                    isNew ? 'border-orange-500 bg-orange-50' : ''
                                }" data-index="${index}">
                                    <div class="h-40 bg-muted flex items-center justify-center relative">
                                        <div class="absolute top-2 right-2 z-10">
                                            <span class="badge ${
                                                isExisting ? 'bg-green-500' : 
                                                isNew ? 'bg-orange-500' : 'bg-gray-500'
                                            } text-white text-xs">
                                                ${isExisting ? 'TYPED' : isNew ? 'NEW' : 'UNKNOWN'}
                                            </span>
                                        </div>
                                        <div class="text-center">
                                            <i data-lucide="file-text" class="h-12 w-12 text-gray-400 mb-2"></i>
                                            <p class="text-xs text-gray-500">Page ${page.page_number}</p>
                                        </div>
                                    </div>
                                    <div class="p-2 bg-gray-50 border-t">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm font-medium">Page ${page.page_number}</span>
                                            <span class="badge badge-outline text-xs">
                                                ${isExisting ? 'Existing' : isNew ? 'New Scan' : 'Unknown'}
                                            </span>
                                        </div>
                                        ${isExisting ? `
                                            <div class="mt-1">
                                                <span class="badge bg-blue-500 text-white text-xs w-full justify-center">
                                                    ${file.file_number}-${page.serial_number.toString().padStart(2, '0')}
                                                </span>
                                                <div class="text-xs text-green-600 mt-1">
                                                    ${page.page_type || 'Typed'}
                                                </div>
                                            </div>
                                        ` : isNew ? `
                                            <div class="mt-1 text-xs text-orange-600">
                                                Needs page typing
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>

                    <div class="mt-6 flex justify-center">
                        <button class="btn btn-primary btn-lg continue-pagetype-more">
                            <i data-lucide="play" class="h-4 w-4 mr-2"></i>
                            Continue Page Typing (${pages.filter(p => !p.is_existing).length} new pages)
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        elements.typingCard.innerHTML = content;
        lucide.createIcons();
        
        // Add event listeners
        document.querySelector('.back-to-pagetype-more')?.addEventListener('click', () => {
            state.pageTypeMoreMode = false;
            state.selectedFile = null;
            state.activeTab = 'pagetype-more';
            updateUI();
        });

        document.querySelector('.continue-pagetype-more')?.addEventListener('click', () => {
            // Start the actual page typing interface for new pages
            startPageTypingForNewPages(file, pages.filter(p => !p.is_existing));
        });
    }
    
    // Start page typing for new pages
    function startPageTypingForNewPages(file, newPages) {
        if (newPages.length === 0) {
            alert('No new pages to type');
            return;
        }
        
        // This would open the page typing interface for the new pages
        alert(`Starting page typing for ${newPages.length} new pages in file ${file.file_number}`);
        
        // TODO: Implement the actual page typing interface
        // This would involve:
        // 1. Loading the page images/PDFs
        // 2. Showing the typing form
        // 3. Saving the page typings via the API
    }
    
    // Save page typing data to backend
    async function savePageTypingToBackend(pageData) {
        try {
            const response = await fetch('{{ route("pagetyping.save-single") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(pageData)
            });
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error saving page typing:', error);
            return { success: false, message: 'Network error' };
        }
    }
    
    // Override the existing renderPageTypeMoreFiles function
    const originalRenderPageTypeMoreFiles = window.renderPageTypeMoreFiles;
    window.renderPageTypeMoreFiles = function() {
        if (!elements.pageTypeMoreTableBody) return;
        
        elements.pageTypeMoreTableBody.innerHTML = '';
        
        if (pageTypeMoreFiles.length === 0) {
            elements.pageTypeMoreTableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center p-8">
                        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                            <i data-lucide="file-plus" class="h-6 w-6"></i>
                        </div>
                        <h3 class="mb-2 text-lg font-medium">No files need additional page typing</h3>
                        <p class="mb-4 text-sm text-muted-foreground">Files with new scans (IsUpdated = 1) will appear here</p>
                    </td>
                </tr>
            `;
            lucide.createIcons();
            return;
        }
        
        pageTypeMoreFiles.forEach(file => {
            const row = document.createElement('tr');
            row.className = 'border-b hover:bg-muted/10';
            row.innerHTML = `
                <td class="p-3">
                    <span class="text-blue-600 font-medium">${file.fileNumber}</span>
                </td>
                <td class="p-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="file-plus" class="h-4 w-4 text-orange-500"></i>
                        <span class="font-medium">${file.name}</span>
                    </div>
                    ${file.district ? `<div class="text-xs text-gray-500">${file.district}, ${file.lga || ''}</div>` : ''}
                </td>
                <td class="p-3">
                    <span class="badge bg-green-500 text-white">${file.existingPages}</span>
                </td>
                <td class="p-3">
                    <span class="badge bg-orange-500 text-white">${file.newScans}</span>
                </td>
                <td class="p-3">
                    <span class="badge badge-secondary">${file.totalPages}</span>
                </td>
                <td class="p-3 text-sm text-muted-foreground">${file.lastUpdated}</td>
                <td class="p-3">
                    <span class="badge bg-orange-500 text-white">
                        <i data-lucide="alert-circle" class="h-3 w-3 mr-1"></i>
                        ${file.status}
                    </span>
                </td>
                <td class="p-3">
                    <div class="flex items-center gap-2">
                        <button class="btn btn-ghost btn-sm view-combined" data-id="${file.id}" title="View Combined File">
                            <i data-lucide="eye" class="h-4 w-4"></i>
                        </button>
                        <button class="btn btn-primary btn-sm pagetype-more-action" data-id="${file.id}" title="PageType More">
                            <i data-lucide="edit" class="h-4 w-4 mr-1"></i>
                            PageType More
                        </button>
                    </div>
                </td>
            `;
            
            elements.pageTypeMoreTableBody.appendChild(row);
        });
        
        // Initialize icons for the new elements
        lucide.createIcons();
        
        // Add event listeners for PageType More actions
        document.querySelectorAll('.pagetype-more-action').forEach(btn => {
            btn.addEventListener('click', () => {
                const fileId = btn.getAttribute('data-id');
                // Use the real backend function instead of the sample one
                startPageTypeMoreReal(fileId);
            });
        });
        
        document.querySelectorAll('.view-combined').forEach(btn => {
            btn.addEventListener('click', () => {
                const fileId = btn.getAttribute('data-id');
                viewCombinedFile(fileId);
            });
        });
    };
    
    // Initialize real data loading
    loadRealStats();
    loadRealPageTypeMoreFiles();
    
    // Refresh data every 30 seconds
    setInterval(() => {
        loadRealStats();
        loadRealPageTypeMoreFiles();
    }, 30000);
});

//  $if {

//     $backCover = $pageTypeCode === 'BC';
//     else if ($pageTypeCode === 'FC') {
//         $frontCover = true;
//     }
//  } 



</script>