// Add this JavaScript code to the PTQ Control view to fix the QC Review tab

// QC Review Functions
function renderQCReviewView() {
  const file = getFileById(state.selectedFile);
  if (!file) {
    elements.qcReviewCard.innerHTML = `
      <div class="p-6 text-center">
        <h3 class="mb-2 text-lg font-medium">File not found</h3>
        <p class="mb-4 text-sm text-muted-foreground">The selected file could not be loaded</p>
        <button class="btn btn-outline btn-sm" onclick="state.selectedFile = null; state.activeTab = 'pending'; updateUI();">
          Back to Pending Files
        </button>
      </div>
    `;
    return;
  }
  
  // QC Review content
  const content = `
    <div class="p-6 border-b">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold">
            <span class="text-blue-600">${file.fileNumber}</span> - 
            ${file.name}
          </h2>
          <p class="text-sm text-muted-foreground">
            Review and approve/reject each page - ${file.pages} pages total
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button class="btn btn-outline btn-sm back-button">
            Back to Files
          </button>
        </div>
      </div>
    </div>
    <div class="p-6">
      <div class="space-y-6">
        <div class="flex justify-between items-center">
          <h3 class="text-lg font-medium">Pages for QC Review</h3>
          <span class="badge bg-blue-500 text-white">${file.pages} pages</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          ${Array.from({length: file.pages}, (_, index) => {
            const isProcessed = state.processedPages[index];
            const processedInfo = state.processedPages[index];
            
            return `
              <div class="border rounded-md overflow-hidden ${
                isProcessed
                  ? processedInfo?.status === 'approved' 
                    ? 'border-green-500 bg-green-50'
                    : processedInfo?.status === 'rejected'
                    ? 'border-red-500 bg-red-50'
                    : 'border-yellow-500 bg-yellow-50'
                  : 'hover:border-blue-500'
              } transition-colors">
                <div class="h-48 bg-muted flex items-center justify-center relative">
                  ${isProcessed 
                    ? `<div class="absolute top-2 right-2 z-10">
                        <span class="badge ${
                          processedInfo?.status === 'approved' ? 'bg-green-500' : 
                          processedInfo?.status === 'rejected' ? 'bg-red-500' : 'bg-yellow-500'
                        } text-white">
                          ${processedInfo?.status === 'approved' ? 'Approved' : 
                            processedInfo?.status === 'rejected' ? 'Rejected' : 'Override'}
                        </span>
                      </div>`
                    : ''}
                  <div class="text-center">
                    <i data-lucide="file-text" class="h-12 w-12 text-muted-foreground mb-2"></i>
                    <p class="text-sm font-medium">Page ${index + 1}</p>
                    <p class="text-xs text-muted-foreground">${file.fileNumber}-${(index + 1).toString().padStart(2, '0')}</p>
                  </div>
                </div>
                <div class="p-3 bg-white border-t">
                  <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium">Page ${index + 1}</span>
                    <span class="badge badge-outline text-xs">
                      ${index === 0 ? 'Cover' : 'Content'}
                    </span>
                  </div>
                  ${isProcessed 
                    ? `<div class="text-center">
                        <span class="badge ${
                          processedInfo?.status === 'approved' ? 'bg-green-500' : 
                          processedInfo?.status === 'rejected' ? 'bg-red-500' : 'bg-yellow-500'
                        } text-white text-xs w-full justify-center">
                          QC ${processedInfo?.status === 'approved' ? 'Approved' : 
                               processedInfo?.status === 'rejected' ? 'Rejected' : 'Override'}
                        </span>
                      </div>`
                    : `<div class="flex gap-1">
                        <button class="btn btn-xs bg-green-500 text-white flex-1 approve-page-btn" data-index="${index}">
                          Approve
                        </button>
                        <button class="btn btn-xs bg-red-500 text-white flex-1 reject-page-btn" data-index="${index}">
                          Reject
                        </button>
                        <button class="btn btn-xs bg-yellow-500 text-white flex-1 override-page-btn" data-index="${index}">
                          Override
                        </button>
                      </div>`}
                </div>
              </div>
            `;
          }).join('')}
        </div>
      </div>
    </div>
  `;
  
  // Update the QC review card
  elements.qcReviewCard.innerHTML = content;
  
  // Initialize icons for the new elements
  lucide.createIcons();
  
  // Add event listeners
  document.querySelector('.back-button')?.addEventListener('click', () => {
    state.selectedFile = null;
    state.activeTab = 'pending';
    updateUI();
  });
  
  // Add QC action listeners
  document.querySelectorAll('.approve-page-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const index = parseInt(btn.getAttribute('data-index'));
      state.processedPages[index] = { status: 'approved' };
      alert('Page approved successfully!');
      updateUI();
    });
  });
  
  document.querySelectorAll('.reject-page-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const index = parseInt(btn.getAttribute('data-index'));
      const reason = prompt('Please provide a reason for rejecting this page:');
      if (reason !== null) {
        state.processedPages[index] = { status: 'rejected', reason: reason };
        alert('Page rejected successfully!');
        updateUI();
      }
    });
  });
  
  document.querySelectorAll('.override-page-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const index = parseInt(btn.getAttribute('data-index'));
      showOverrideModal(index);
    });
  });
}

function showOverrideModal(index) {
  const file = getFileById(state.selectedFile);
  state.selectedPageInFolder = index;
  
  // Populate modal with current page info
  document.getElementById('override-page-id').value = index;
  document.getElementById('override-page-details').innerHTML = `
    <p><strong>Page Number:</strong> ${index + 1}</p>
    <p><strong>File Number:</strong> ${file.fileNumber}</p>
    <p><strong>Page Code:</strong> ${file.fileNumber}-${(index + 1).toString().padStart(2, '0')}</p>
  `;
  
  // Show modal
  document.getElementById('qc-override-modal').classList.remove('hidden');
  document.getElementById('qc-override-modal').setAttribute('aria-hidden', 'false');
}

function hideOverrideModal() {
  document.getElementById('qc-override-modal').classList.add('hidden');
  document.getElementById('qc-override-modal').setAttribute('aria-hidden', 'true');
  document.getElementById('override-note').value = '';
  state.selectedPageInFolder = null;
}

function submitOverride() {
  const note = document.getElementById('override-note').value.trim();
  if (!note) {
    alert('Please provide a reason for the override.');
    return;
  }
  
  const index = state.selectedPageInFolder;
  if (index !== null) {
    state.processedPages[index] = { status: 'overridden', note: note };
    alert('Page override submitted successfully!');
    hideOverrideModal();
    updateUI();
  }
}

// INSTRUCTIONS TO FIX THE QC REVIEW TAB:

// 1. Add this code to the updateUI() function:
//    Add this line after "elements.qcReviewTab.setAttribute('aria-disabled', state.selectedFile ? 'false' : 'true');"
//    
//    // Render QC review content if on QC review tab
//    if (state.activeTab === 'qc-review' && state.selectedFile) {
//      renderQCReviewView();
//    }

// 2. Add modal event listeners to the DOMContentLoaded section:
//    Add these lines before "await loadPendingFiles();"
//    
//    // Override modal event listeners
//    document.getElementById('cancel-override-btn')?.addEventListener('click', hideOverrideModal);
//    document.getElementById('override-modal-backdrop')?.addEventListener('click', hideOverrideModal);
//    document.getElementById('qc-override-form')?.addEventListener('submit', (e) => {
//      e.preventDefault();
//      submitOverride();
//    });

// 3. Add all the functions above (renderQCReviewView, showOverrideModal, hideOverrideModal, submitOverride) 
//    to the JavaScript section of the PTQ control view file.

console.log('PTQ Control QC Review functions loaded. Apply the instructions above to fix the QC Review tab.');