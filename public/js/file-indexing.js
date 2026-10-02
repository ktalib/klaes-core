// ...existing code...

// Remove disabled checkbox and tracked status functionality
function renderIndexedFilesTable(files) {
    let html = '';
    
    if (files.length === 0) {
        html = '<div class="alert alert-info">No indexed files found</div>';
    } else {
        files.forEach(function(file) {
            // Determine status based on workflow progress
            let statusBadge = '';
            let statusClass = '';
            
            if (file.page_typing_count > 0) {
                statusBadge = 'Typed';
                statusClass = 'badge-success';
            } else if (file.scanning_count > 0) {
                statusBadge = 'Scanned';
                statusClass = 'badge-warning';
            } else {
                statusBadge = 'Indexed';
                statusClass = 'badge-info';
            }
            
            html += `
                <tr>
                    <td>
                        <input type="checkbox" class="file-checkbox" value="${file.id}" data-file='${JSON.stringify(file)}'>
                    </td>
                    <td>
                        <strong>${file.fileNumber}</strong><br>
                        <small class="text-muted">${file.type}</small>
                    </td>
                    <td>
                        <div class="file-name-cell">
                            <strong>${file.name}</strong><br>
                            <small class="text-muted">${file.landUseType} - ${file.district}</small>
                        </div>
                    </td>
                    <td>
                        <span class="badge ${statusClass}">${statusBadge}</span><br>
                        <small class="text-muted">${file.scanning_count} scanned, ${file.page_typing_count} typed</small>
                    </td>
                    <td>
                        <small class="text-muted">${file.date}</small>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="viewFileDetails(${file.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="generateTrackingSheet(${file.id})" title="Generate Tracking Sheet">
                                <i class="fas fa-print"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="editFile(${file.id})" title="Edit File">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
    }
    
    $('#indexedFilesTableBody').html(html);
    updateSelectionCount();
}

// ...existing code...

// Update the Generate Batch Tracking Sheets button functionality
function generateBatchTrackingSheets() {
    const selectedFiles = getSelectedFiles();
    
    if (selectedFiles.length === 0) {
        showMessage('warning', 'Please select at least one file to generate tracking sheets');
        return;
    }
    
    // Get selected file IDs
    const selectedFileIds = selectedFiles.map(file => file.id);
    
    // Redirect to smart batch tracking interface
    const url = "{{ route('fileindexing.batch-tracking-interface') }}?files=" + selectedFileIds.join(',');
    window.location.href = url;
}

// Update bulk operations to remove disabled/tracked status options
function initializeBulkOperations() {
    // Update file count display
    $('#selectedFileCount').text('0');
    
    // Bind Generate Batch Tracking Sheets button
    $('#generateBatchTrackingBtn').off('click').on('click', function() {
        generateBatchTrackingSheets();
    });
    
    // Bind individual tracking sheet generation
    $(document).on('click', '.generate-tracking-btn', function() {
        const fileId = $(this).data('file-id');
        generateTrackingSheet(fileId);
    });
    
    // Bind select all checkbox
    $('#selectAllFiles').off('change').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.file-checkbox').prop('checked', isChecked);
        updateSelectionCount();
    });
    
    // Bind individual file checkboxes
    $(document).on('change', '.file-checkbox', function() {
        updateSelectionCount();
        
        // Update select all checkbox state
        const totalCheckboxes = $('.file-checkbox').length;
        const checkedCheckboxes = $('.file-checkbox:checked').length;
        
        if (checkedCheckboxes === 0) {
            $('#selectAllFiles').prop('indeterminate', false).prop('checked', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $('#selectAllFiles').prop('indeterminate', false).prop('checked', true);
        } else {
            $('#selectAllFiles').prop('indeterminate', true);
        }
    });
}

// Remove disabled and tracked status functions
// These functions are no longer needed:
// - markAsDisabled()
// - markAsTracked()
// - updateFileStatus()

// Update the bulk operations panel HTML to remove disabled/tracked options
function updateBulkOperationsPanel() {
    const bulkOperationsHtml = `
        <div class="bulk-operations-panel" style="display: none;">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-tasks"></i> Bulk Operations
                        <span class="badge badge-light ml-2" id="selectedFileCount">0</span> selected
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-success" id="generateBatchTrackingBtn">
                                    <i class="fas fa-print"></i> Smart Batch Tracking
                                </button>
                                <button type="button" class="btn btn-info" onclick="exportSelectedFiles()">
                                    <i class="fas fa-download"></i> Export Selected
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="clearSelection()">
                                    <i class="fas fa-times"></i> Clear Selection
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Replace existing bulk operations panel if it exists
    if ($('.bulk-operations-panel').length > 0) {
        $('.bulk-operations-panel').replaceWith(bulkOperationsHtml);
    } else {
        // Insert after the indexed files controls
        $('.indexed-files-controls').after(bulkOperationsHtml);
    }
}

// ...existing code...

// Enhanced file selection management
function getSelectedFiles() {
    const selectedFiles = [];
    $('.file-checkbox:checked').each(function() {
        const fileData = $(this).data('file');
        if (fileData) {
            selectedFiles.push(fileData);
        }
    });
    return selectedFiles;
}

function updateSelectionCount() {
    const selectedCount = $('.file-checkbox:checked').length;
    $('#selectedFileCount').text(selectedCount);
    
    // Show/hide bulk operations panel
    if (selectedCount > 0) {
        $('.bulk-operations-panel').slideDown();
    } else {
        $('.bulk-operations-panel').slideUp();
    }
    
    // Update bulk action button states
    $('#generateBatchTrackingBtn').prop('disabled', selectedCount === 0);
}

function clearSelection() {
    $('.file-checkbox').prop('checked', false);
    $('#selectAllFiles').prop('checked', false).prop('indeterminate', false);
    updateSelectionCount();
}

function exportSelectedFiles() {
    const selectedFiles = getSelectedFiles();
    
    if (selectedFiles.length === 0) {
        showMessage('warning', 'Please select at least one file to export');
        return;
    }
    
    // Create CSV export
    let csvContent = "File Number,File Title,Type,Source,Date,Land Use,District,LGA,Status\n";
    
    selectedFiles.forEach(function(file) {
        csvContent += [
            `"${file.fileNumber}"`,
            `"${file.name}"`,
            `"${file.type}"`,
            `"${file.source}"`,
            `"${file.date}"`,
            `"${file.landUseType}"`,
            `"${file.district}"`,
            `"${file.lga}"`,
            `"${file.scanning_count > 0 ? (file.page_typing_count > 0 ? 'Typed' : 'Scanned') : 'Indexed'}"`
        ].join(',') + '\n';
    });
    
    // Download CSV
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `indexed_files_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showMessage('success', `Exported ${selectedFiles.length} files successfully`);
}

// Individual tracking sheet generation
function generateTrackingSheet(fileId) {
    const url = "{{ route('fileindexing.tracking-sheet', ':id') }}".replace(':id', fileId);
    window.open(url, '_blank');
    showMessage('info', 'Opening tracking sheet...');
}

// Initialize when document is ready
$(document).ready(function() {
    // Initialize bulk operations
    initializeBulkOperations();
    
    // Update bulk operations panel
    updateBulkOperationsPanel();
    
    // Load indexed files when tab is shown
    $('#indexed-files-tab').on('shown.bs.tab', function() {
        loadIndexedFiles();
    });
});

// ...existing code...