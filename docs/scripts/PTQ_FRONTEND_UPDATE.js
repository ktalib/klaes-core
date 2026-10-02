// Updated JavaScript for PTQ Control with real backend integration

// Replace the sample data section with this:

// Data arrays for QC files - will be populated from backend
let pendingQCFiles = [];
let inProgressQCFiles = [];
let completedQCFiles = [];

// API functions for backend integration
async function loadPendingFiles() {
  try {
    const response = await fetch('/ptq-control/list-pending');
    const data = await response.json();
    
    if (data.success) {
      // Update the data array with real data
      pendingQCFiles = data.data.map(file => ({
        id: file.id,
        fileNumber: file.file_number,
        name: file.file_title || 'Untitled File',
        type: file.land_use_type || 'Unknown',
        pages: file.total_pages_count || 0,
        completed: file.total_pages_count || 0,
        qc_reviewed: 0,
        date: new Date(file.created_at).toISOString().split('T')[0],
        status: 'Pending QC',
        pagetyped_by: file.pagetyped_by_name || 'Unknown',
        pagetyped_at: file.last_pagetyped_at
      }));
      
      renderPendingQCFiles();
      
      // Update stats
      elements.pendingCount.textContent = pendingQCFiles.length;
    } else {
      console.error('Failed to load pending files:', data.message);
    }
  } catch (error) {
    console.error('Error loading pending files:', error);
  }
}

async function loadInProgressFiles() {
  try {
    const response = await fetch('/ptq-control/list-in-progress');
    const data = await response.json();
    
    if (data.success) {
      inProgressQCFiles = data.data.map(file => ({
        id: file.id,
        fileNumber: file.file_number,
        name: file.file_title || 'Untitled File',
        type: file.land_use_type || 'Unknown',
        pages: file.total_pages_count || 0,
        completed: file.total_pages_count || 0,
        qc_reviewed: file.reviewed_pages_count || 0,
        date: new Date(file.created_at).toISOString().split('T')[0],
        status: 'QC In Progress',
        pagetyped_by: file.pagetyped_by_name || 'Unknown',
        pagetyped_at: file.last_pagetyped_at,
        qc_progress: file.qc_progress || 0
      }));
      
      renderInProgressQCFiles();
      
      // Update stats
      elements.inProgressCount.textContent = inProgressQCFiles.length;
    } else {
      console.error('Failed to load in-progress files:', data.message);
    }
  } catch (error) {
    console.error('Error loading in-progress files:', error);
  }
}

async function loadCompletedFiles() {
  try {
    const response = await fetch('/ptq-control/list-completed');
    const data = await response.json();
    
    if (data.success) {
      completedQCFiles = data.data.map(file => ({
        id: file.id,
        fileNumber: file.file_number,
        name: file.file_title || 'Untitled File',
        type: file.land_use_type || 'Unknown',
        pages: file.total_pages_count || 0,
        completed: file.total_pages_count || 0,
        qc_reviewed: file.total_pages_count || 0,
        date: new Date(file.created_at).toISOString().split('T')[0],
        status: 'QC Completed',
        pagetyped_by: file.pagetyped_by_name || 'Unknown',
        pagetyped_at: file.last_pagetyped_at,
        qc_reviewed_by: file.qc_reviewed_by_name || 'QC Team',
        qc_reviewed_at: file.qc_completed_at,
        processedPages: file.processedPages || []
      }));
      
      renderCompletedQCFilesTable();
      
      // Update stats
      elements.completedCount.textContent = completedQCFiles.length;
    } else {
      console.error('Failed to load completed files:', data.message);
    }
  } catch (error) {
    console.error('Error loading completed files:', error);
  }
}

async function loadQCDetails(fileId) {
  try {
    const response = await fetch(`/ptq-control/qc-details/${fileId}`);
    const data = await response.json();
    
    if (data.success) {
      // Update sample pages with real data
      samplePages[fileId] = data.data.page_typings.map(page => page.file_url);
      return data.data;
    } else {
      console.error('Failed to load QC details:', data.message);
    }
  } catch (error) {
    console.error('Error loading QC details:', error);
  }
  return null;
}

async function submitQCDecision(pageTypingIds, decision, notes = '') {
  try {
    let endpoint = '';
    let payload = {
      page_typing_ids: pageTypingIds
    };

    switch (decision) {
      case 'approve':
        endpoint = '/ptq-control/mark-qc-status';
        payload.qc_status = 'passed';
        payload.notes = notes;
        break;
      case 'reject':
        endpoint = '/ptq-control/mark-qc-status';
        payload.qc_status = 'failed';
        payload.notes = notes;
        break;
      case 'override':
        endpoint = '/ptq-control/override-qc';
        payload.override_note = notes;
        break;
    }

    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify(payload)
    });

    const data = await response.json();
    
    if (data.success) {
      alert(data.message);
      // Refresh the current view
      await refreshCurrentView();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error submitting QC decision:', error);
    alert('Error submitting QC decision. Please try again.');
  }
}

async function refreshCurrentView() {
  switch (state.activeTab) {
    case 'pending':
      await loadPendingFiles();
      break;
    case 'in-progress':
      await loadInProgressFiles();
      break;
    case 'completed':
      await loadCompletedFiles();
      break;
  }
  updateUI();
}

// Update the selectFileForQCReview function to load real data
async function selectFileForQCReview(fileId) {
  state.selectedFile = fileId;
  state.currentPage = 1;
  state.activeTab = "qc-review";
  state.zoomLevel = 100;
  state.rotation = 0;
  state.showFolderView = true;
  state.selectedPageInFolder = null;
  state.batchMode = false;
  state.batchQCPages = {};
  state.currentBatchPageIndex = null;
  state.batchSubmitReady = false;
  state.processedPages = {};

  // Load QC details for the selected file
  const qcDetails = await loadQCDetails(fileId);
  if (qcDetails) {
    // Calculate QC progress based on reviewed pages
    const file = getFileById(fileId);
    if (file) {
      state.qcProgress = (qcDetails.qc_summary.total_pages - qcDetails.qc_summary.pending_pages) / qcDetails.qc_summary.total_pages * 100;
    }
  }
  
  updateUI();
}

// Update the initialization to load real data
document.addEventListener('DOMContentLoaded', async () => {
  // Add CSRF token to meta tag if not present
  if (!document.querySelector('meta[name="csrf-token"]')) {
    const meta = document.createElement('meta');
    meta.name = 'csrf-token';
    meta.content = '{{ csrf_token() }}';
    document.head.appendChild(meta);
  }

  // Add tab event listeners
  elements.tabs.forEach(tab => {
    tab.addEventListener('click', async () => {
      const tabId = tab.getAttribute('data-tab');
      if (tabId !== 'qc-review' || state.selectedFile) {
        switchTab(tabId);
        
        // Load data for the selected tab
        switch (tabId) {
          case 'pending':
            await loadPendingFiles();
            break;
          case 'in-progress':
            await loadInProgressFiles();
            break;
          case 'completed':
            await loadCompletedFiles();
            break;
        }
      }
    });
  });

  // Override modal event listeners
  document.getElementById('cancel-override-btn').addEventListener('click', hideOverrideModal);
  document.getElementById('override-modal-backdrop').addEventListener('click', hideOverrideModal);
  
  // Override form submit
  document.getElementById('qc-override-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const note = document.getElementById('override-note').value.trim();
    if (!note) {
      alert('Please provide a reason for the override.');
      return;
    }
    
    await submitQCDecision([state.selectedPageInFolder], 'override', note);
    hideOverrideModal();
  });

  // Load initial data
  await loadPendingFiles();
  updateUI();
});