<script>
  // ---- Config
  const MIGRATE_ENDPOINT = "{{ route('blind-scanning.migrate') }}";
  const API_LIST = "{{ route('blind-scanning.list') }}";
  const API_LOGS = "{{ route('blind-scanning.logs') }}";
  const API_SYNC_MIGRATIONS = "{{ route('blind-scanning.sync-migrations') }}";
  const API_SAVE_IMAGE = "{{ route('blind-scanning.save-image') }}";
  const API_DELETE_FILE = "{{ route('blind-scanning.delete-file') }}";
  const CSRF_TOKEN = "{{ csrf_token() }}";
  const MIGRATION_SYNC_INTERVAL_MS = 5 * 60 * 1000; // Auto-sync every 5 minutes

  // ---- Preview Modal State
  let previewState = {
    currentIndex: 0,
    fileList: [],
    isOpen: false,
    currentDirItems: [],
    currentFilePath: ''
  };

  // ---- Image Editing State
  let currentImageState = {
    element: null,
    originalSrc: null,
    rotation: 0,
    scale: 1,
    isCropping: false,
    cropOverlay: null,
    cropStartX: 0,
    cropStartY: 0,
    cropWidth: 0,
    cropHeight: 0,
    currentFileName: ''
  };

  // ---- Logs fetch function
  async function fetchLogs() {
    const res = await fetch(API_LOGS);
    const data = await res.json();
    if (!res.ok || !data.ok) { alert(data.error || ('HTTP ' + res.status)); return; }
    const logs = data.logs || [];
    const root = document.getElementById('logsContent');

    // Add mode indicator for logs
    let modeIndicator = '';
    if (data.isAdmin) {
      modeIndicator = `
        <div class="mb-4 flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-md">
          <i class="fa-solid fa-crown text-amber-600"></i>
          <span class="text-sm font-medium text-amber-700">Viewing all user migrations</span>
        </div>
      `;
    } else {
      modeIndicator = `
        <div class="mb-4 flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-md">
          <i class="fa-solid fa-user text-blue-600"></i>
          <span class="text-sm font-medium text-blue-700">Viewing your migrations only</span>
        </div>
      `;
    }

    if (!logs.length) {
      root.innerHTML = modeIndicator + `
        <div class="text-center py-8">
          <i class="fa-solid fa-clock-rotate-left text-3xl text-muted-foreground mx-auto mb-4"></i>
          <p class="text-muted-foreground">No migrations yet</p>
          <p class="text-sm text-muted-foreground">After migrating, entries will appear here.</p>
        </div>`;
      return;
    }
    root.innerHTML = modeIndicator + `
      <div class="border rounded-lg overflow-hidden">
        <table id="logsTable" class="w-full text-sm">
          <thead class="bg-muted">
            <tr class="border-b">
              <th class="text-left p-3">S/N</th>
              <th class="text-left p-3">When</th>
              <th class="text-left p-3">Parent Folder</th>
              <th class="text-left p-3">Server Path</th>
            </tr>
          </thead>
          <tbody>
            ${logs.map((m, i) => `
              <tr class="border-b">
                <td class="p-3">${i + 1}</td>
                <td class="p-3">${m.when || '-'}</td>
                <td class="p-3 font-mono">${m.folder || '-'}</td>
                <td class="p-3 font-mono"><a class="text-blue-700 hover:underline" href="${m.serverPath || '#'}" target="_blank" rel="noopener">${m.serverPath || '-'}</a></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>`;

    // Initialize DataTable for logs
    $('#logsTable').DataTable({
      pageLength: 50,
      order: [[1, 'desc']],
      language: {
        search: "_INPUT_",
        searchPlaceholder: "Search logs..."
      }
    });
  }

  // ---- Preview Modal Functions
  function openPreviewModal(fileName) {
    const currentDir = previewState.currentDirItems || [];

    // Filter to only include previewable files (images and PDFs)
    const previewableFiles = currentDir.filter(item =>
      item.type === 'file' && (
        item.name.toLowerCase().endsWith('.pdf') ||
        item.name.toLowerCase().match(/\.(jpg|jpeg|png|gif|webp|bmp)$/i)
      )
    );

    if (previewableFiles.length === 0) {
      alert('No previewable files (PDF or images) in this directory');
      return;
    }

    // Find current file index
    const currentIndex = previewableFiles.findIndex(file => file.name === fileName);
    if (currentIndex === -1) {
      alert('File not found in previewable files');
      return;
    }

    previewState.fileList = previewableFiles;
    previewState.currentIndex = currentIndex;
    previewState.isOpen = true;

    // Show modal
    document.getElementById('previewModal').classList.remove('hidden');
    document.getElementById('previewModal').classList.add('flex');
    document.body.style.overflow = 'hidden';

    loadCurrentPreview();
  }

  function closePreviewModal() {
    document.getElementById('previewModal').classList.add('hidden');
    document.getElementById('previewModal').classList.remove('flex');
    document.body.style.overflow = 'auto';
    previewState.isOpen = false;

    // Reset image editing state
    if (currentImageState.element) {
      resetImage();
    }
  }

  function loadCurrentPreview() {
    if (!previewState.isOpen || previewState.fileList.length === 0) return;

    const currentFile = previewState.fileList[previewState.currentIndex];
    const modalContent = document.getElementById('modalContent');
    const modalFileName = document.getElementById('modalFileName');
    const modalFileInfo = document.getElementById('modalFileInfo');
    const modalCounter = document.getElementById('modalCounter');
    const imageEditToolbar = document.getElementById('imageEditToolbar');

    // Update file info
    modalFileName.textContent = currentFile.name;
    modalFileInfo.textContent = `Size: ${fmtBytes(currentFile.size)} • Modified: ${fmtDate(currentFile.mtime)}`;
    modalCounter.textContent = `${previewState.currentIndex + 1}/${previewState.fileList.length}`;

    // Store current file path (relative to storage)
    const hrefParts = currentFile.href.split('/storage/');
    previewState.currentFilePath = hrefParts.length > 1 ? hrefParts[1] : '';

    // Clear previous content
    modalContent.innerHTML = '<div class="text-muted-foreground">Loading preview...</div>';
    imageEditToolbar.style.display = 'none';

    const ext = currentFile.name.toLowerCase().split('.').pop();

    if (['pdf'].includes(ext)) {
      // PDF preview
      modalContent.innerHTML = `
        <iframe src="${currentFile.href}" class="pdf-iframe" 
                title="PDF Preview: ${currentFile.name}"></iframe>
      `;
    } else if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)) {
      // Image preview - add cache buster to force refresh
      const cacheBuster = '?t=' + (currentFile.mtime || Date.now());
      const img = document.createElement('img');
      img.src = currentFile.href + cacheBuster;
      img.alt = currentFile.name;
      img.className = 'preview-image-editable';
      img.onload = () => {
        initializeImageEditing(img, currentFile.name);
        imageEditToolbar.style.display = 'flex';
      };
      img.onerror = () => {
        // If cached version fails, try without cache buster
        img.src = currentFile.href;
      };
      modalContent.innerHTML = '';
      modalContent.appendChild(img);
    } else {
      modalContent.innerHTML = `
        <div class="text-center">
          <i class="fa-solid fa-file text-4xl text-muted-foreground mb-2"></i>
          <div class="text-muted-foreground">No preview available for this file type</div>
          <a href="${currentFile.href}" target="_blank" class="btn btn-primary mt-2">
            <i class="fa-solid fa-external-link mr-2"></i>Open File
          </a>
        </div>
      `;
    }
  }

  function navigatePreview(direction) {
    if (!previewState.isOpen) return;

    const newIndex = previewState.currentIndex + direction;
    if (newIndex >= 0 && newIndex < previewState.fileList.length) {
      previewState.currentIndex = newIndex;
      loadCurrentPreview();
    }
  }

  function fitToScreen() {
    if (currentImageState.element) {
      currentImageState.scale = 1;
      updateImageTransform();
    }
  }

  function actualSize() {
    if (currentImageState.element) {
      currentImageState.scale = 1;
      updateImageTransform();
    }
  }

  // ---- Image Editing Functions
  function initializeImageEditing(imgElement, fileName) {
    currentImageState = {
      element: imgElement,
      originalSrc: imgElement.src,
      rotation: 0,
      scale: 1,
      isCropping: false,
      cropOverlay: null,
      cropStartX: 0,
      cropStartY: 0,
      cropWidth: 0,
      cropHeight: 0,
      currentFileName: fileName
    };

    updateImageTransform();
  }

  function rotateImage(degrees) {
    currentImageState.rotation += degrees;
    updateImageTransform();
    // Auto-save after rotation
    autoSaveImage('Rotating image...');
  }

  function zoomImage(factor) {
    currentImageState.scale *= factor;
    updateImageTransform();
    // Note: Zoom is visual only, no auto-save needed
  }

  function resetZoom() {
    currentImageState.scale = 1;
    updateImageTransform();
  }

  function resetImage() {
    currentImageState.rotation = 0;
    currentImageState.scale = 1;
    if (currentImageState.isCropping) {
      toggleCropMode();
    }
    updateImageTransform();
    // Auto-save reset
    autoSaveImage('Resetting image...');
  }

  function updateImageTransform() {
    if (currentImageState.element) {
      currentImageState.element.style.transform =
        `rotate(${currentImageState.rotation}deg) scale(${currentImageState.scale})`;
      currentImageState.element.style.transformOrigin = 'center center';
      document.getElementById('zoomLevel').textContent = Math.round(currentImageState.scale * 100) + '%';
    }
  }

  function toggleCropMode() {
    currentImageState.isCropping = !currentImageState.isCropping;

    if (currentImageState.isCropping) {
      createCropOverlay();
    } else {
      removeCropOverlay();
      // Auto-save when crop is applied (toggled off)
      if (currentImageState.cropWidth > 0 && currentImageState.cropHeight > 0) {
        autoSaveImage('Applying crop...');
      }
    }
  }

  function createCropOverlay() {
    const container = currentImageState.element.parentElement;
    const rect = currentImageState.element.getBoundingClientRect();

    currentImageState.cropOverlay = document.createElement('div');
    currentImageState.cropOverlay.className = 'crop-overlay';

    // Set initial crop area (80% of image)
    currentImageState.cropWidth = rect.width * 0.8;
    currentImageState.cropHeight = rect.height * 0.8;
    currentImageState.cropStartX = (rect.width - currentImageState.cropWidth) / 2;
    currentImageState.cropStartY = (rect.height - currentImageState.cropHeight) / 2;

    currentImageState.cropOverlay.style.left = currentImageState.cropStartX + 'px';
    currentImageState.cropOverlay.style.top = currentImageState.cropStartY + 'px';
    currentImageState.cropOverlay.style.width = currentImageState.cropWidth + 'px';
    currentImageState.cropOverlay.style.height = currentImageState.cropHeight + 'px';

    // Add resize handles
    const handles = ['nw', 'ne', 'sw', 'se'];
    handles.forEach(handle => {
      const handleEl = document.createElement('div');
      handleEl.className = `crop-handle crop-handle-${handle}`;
      currentImageState.cropOverlay.appendChild(handleEl);
    });

    setupCropInteractions();
    container.appendChild(currentImageState.cropOverlay);
  }

  function removeCropOverlay() {
    if (currentImageState.cropOverlay) {
      currentImageState.cropOverlay.remove();
      currentImageState.cropOverlay = null;
    }
  }

  function setupCropInteractions() {
    let isDragging = false;
    let isResizing = false;
    let resizeDirection = '';
    let startX, startY;

    currentImageState.cropOverlay.addEventListener('mousedown', startDrag);

    function startDrag(e) {
      if (e.target.classList.contains('crop-handle')) {
        isResizing = true;
        resizeDirection = e.target.classList[1].split('-')[2];
      } else {
        isDragging = true;
      }

      startX = e.clientX;
      startY = e.clientY;

      e.preventDefault();
      document.addEventListener('mousemove', handleMove);
      document.addEventListener('mouseup', stopDrag);
    }

    function handleMove(e) {
      if (!isDragging && !isResizing) return;

      const dx = e.clientX - startX;
      const dy = e.clientY - startY;

      if (isDragging) {
        currentImageState.cropStartX = Math.max(0, currentImageState.cropStartX + dx);
        currentImageState.cropStartY = Math.max(0, currentImageState.cropStartY + dy);
      } else if (isResizing) {
        if (resizeDirection.includes('e')) {
          currentImageState.cropWidth = Math.max(50, currentImageState.cropWidth + dx);
        }
        if (resizeDirection.includes('w')) {
          currentImageState.cropStartX = Math.max(0, currentImageState.cropStartX + dx);
          currentImageState.cropWidth = Math.max(50, currentImageState.cropWidth - dx);
        }
        if (resizeDirection.includes('s')) {
          currentImageState.cropHeight = Math.max(50, currentImageState.cropHeight + dy);
        }
        if (resizeDirection.includes('n')) {
          currentImageState.cropStartY = Math.max(0, currentImageState.cropStartY + dy);
          currentImageState.cropHeight = Math.max(50, currentImageState.cropHeight - dy);
        }
      }

      updateCropOverlay();
      startX = e.clientX;
      startY = e.clientY;
    }

    function stopDrag() {
      isDragging = false;
      isResizing = false;
      document.removeEventListener('mousemove', handleMove);
      document.removeEventListener('mouseup', stopDrag);
    }
  }

  function updateCropOverlay() {
    if (currentImageState.cropOverlay) {
      currentImageState.cropOverlay.style.left = currentImageState.cropStartX + 'px';
      currentImageState.cropOverlay.style.top = currentImageState.cropStartY + 'px';
      currentImageState.cropOverlay.style.width = currentImageState.cropWidth + 'px';
      currentImageState.cropOverlay.style.height = currentImageState.cropHeight + 'px';
    }
  }

  function getEditedImageBlob() {
    return new Promise((resolve) => {
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      const img = currentImageState.element;

      // Set canvas dimensions based on whether we're cropping
      if (currentImageState.isCropping && currentImageState.cropWidth > 0 && currentImageState.cropHeight > 0) {
        // For crop: use the crop dimensions
        const scaleX = img.naturalWidth / img.width;
        const scaleY = img.naturalHeight / img.height;

        canvas.width = currentImageState.cropWidth * scaleX;
        canvas.height = currentImageState.cropHeight * scaleY;

        // Calculate the source crop coordinates in natural image dimensions
        const srcX = currentImageState.cropStartX * scaleX;
        const srcY = currentImageState.cropStartY * scaleY;
        const srcWidth = currentImageState.cropWidth * scaleX;
        const srcHeight = currentImageState.cropHeight * scaleY;

        // Apply transformations to the context before drawing
        ctx.translate(canvas.width / 2, canvas.height / 2);
        ctx.rotate(currentImageState.rotation * Math.PI / 180);
        ctx.scale(currentImageState.scale, currentImageState.scale);
        ctx.translate(-canvas.width / 2, -canvas.height / 2);

        // Draw the cropped portion
        ctx.drawImage(
          img,
          srcX, srcY, srcWidth, srcHeight,  // source rectangle (crop area)
          0, 0, canvas.width, canvas.height // destination rectangle (full canvas)
        );
      } else {
        // For non-crop: use full image dimensions
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;

        // Apply transformations
        ctx.translate(canvas.width / 2, canvas.height / 2);
        ctx.rotate(currentImageState.rotation * Math.PI / 180);
        ctx.scale(currentImageState.scale, currentImageState.scale);
        ctx.translate(-canvas.width / 2, -canvas.height / 2);

        // Draw the full image
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      }

      // Convert to blob
      canvas.toBlob(resolve);
    });
  }

  async function saveEditedImage() {
    if (!currentImageState.element || !previewState.currentFilePath) {
      alert('No image to save or file path not available');
      return;
    }

    try {
      // Show saving indicator
      const saveBtn = document.querySelector('button[onclick="saveEditedImage()"]');
      const originalText = saveBtn.innerHTML;
      saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
      saveBtn.disabled = true;

      // Get the edited image as blob
      const blob = await getEditedImageBlob();

      // Create form data
      const formData = new FormData();
      formData.append('_token', CSRF_TOKEN);
      formData.append('filePath', previewState.currentFilePath);
      formData.append('image', blob, currentImageState.currentFileName);

      // Send to server
      const response = await fetch(API_SAVE_IMAGE, {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.ok) {
        // Update the current file in the file list with new metadata
        const currentFile = previewState.fileList[previewState.currentIndex];
        if (currentFile) {
          currentFile.size = result.fileSize;
          currentFile.mtime = result.mtime;
        }

        // Update the image source with cache buster to force refresh
        const timestamp = result.cacheBuster || Date.now();
        currentImageState.element.src = currentImageState.originalSrc.split('?')[0] + '?t=' + timestamp;
        currentImageState.originalSrc = currentImageState.element.src;

        // Show success message
        showNotification('Image saved successfully!', 'success');

        // Refresh the file list to show updated file size and modification time
        await fetchServerList(currentSrvPath);
      } else {
        throw new Error(result.error || 'Failed to save image');
      }

    } catch (error) {
      console.error('Save error:', error);
      showNotification('Failed to save image: ' + error.message, 'error');
    } finally {
      // Restore button state
      const saveBtn = document.querySelector('button[onclick="saveEditedImage()"]');
      saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
      saveBtn.disabled = false;
    }
  }

  function downloadEditedImage() {
    getEditedImageBlob().then(blob => {
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'edited-' + currentImageState.currentFileName;
      a.click();
      URL.revokeObjectURL(url);
    });
  }

  // ---- Auto-save with debounce to prevent rapid successive saves
  let autoSaveTimeout = null;
  let isSaving = false;

  async function autoSaveImage(message = 'Saving changes...') {
    // Clear any pending auto-save
    if (autoSaveTimeout) {
      clearTimeout(autoSaveTimeout);
    }

    // Debounce: wait 500ms before auto-saving
    autoSaveTimeout = setTimeout(async () => {
      if (isSaving) {
        console.log('Already saving, skipping auto-save');
        return;
      }

      if (!currentImageState.element || !previewState.currentFilePath) {
        console.warn('No image to auto-save or file path not available');
        return;
      }

      try {
        isSaving = true;
        console.log('Auto-saving:', message);

        // Show subtle indicator in toolbar
        const toolbar = document.getElementById('imageEditToolbar');
        const originalBg = toolbar.style.background;
        toolbar.style.background = 'rgba(79, 70, 229, 0.1)';

        // Get the edited image as blob
        const blob = await getEditedImageBlob();

        // Create form data
        const formData = new FormData();
        formData.append('filePath', previewState.currentFilePath);
        formData.append('image', blob, currentImageState.currentFileName);
        formData.append('_token', CSRF_TOKEN);

        // Send to server
        const response = await fetch(API_SAVE_IMAGE, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.ok || result.success) {
          // Update the current file in the file list with new metadata
          const currentFile = previewState.fileList[previewState.currentIndex];
          if (currentFile) {
            currentFile.size = result.fileSize || result.file_size;
            currentFile.mtime = result.mtime;
          }

          // Update the image source with cache buster to force refresh
          const timestamp = result.cacheBuster || result.cache_buster || Date.now();
          const newSrc = currentImageState.originalSrc.split('?')[0] + '?t=' + timestamp;

          // Reset transformation state but keep rotation/crop data
          const savedRotation = currentImageState.rotation;
          const savedScale = currentImageState.scale;

          // Load the new image
          currentImageState.element.src = newSrc;
          currentImageState.originalSrc = newSrc;

          // Reapply transformations after image loads
          currentImageState.element.onload = () => {
            currentImageState.rotation = savedRotation;
            currentImageState.scale = savedScale;
            updateImageTransform();
          };

          // Show success indicator briefly
          showNotification('✓ Changes auto-saved', 'success');

          // Restore toolbar background
          setTimeout(() => {
            toolbar.style.background = originalBg;
          }, 300);

          console.log('Auto-save successful');
        } else {
          throw new Error(result.error || result.message || 'Failed to auto-save image');
        }

      } catch (error) {
        console.error('Auto-save error:', error);
        showNotification('Auto-save failed: ' + error.message, 'error');
      } finally {
        isSaving = false;
      }
    }, 500); // 500ms debounce
  }

  // ---- Delete File Functionality
  async function deleteCurrentFile() {
    if (!previewState.currentFilePath) {
      alert('No file to delete');
      return;
    }

    const currentFile = previewState.fileList[previewState.currentIndex];
    if (!currentFile) return;

    if (!confirm(`Are you sure you want to delete "${currentFile.name}"? This action cannot be undone.`)) {
      return;
    }

    try {
      // Show deleting indicator
      const deleteBtn = document.querySelector('button[onclick="deleteCurrentFile()"]');
      const originalText = deleteBtn.innerHTML;
      deleteBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
      deleteBtn.disabled = true;

      const formData = new FormData();
      formData.append('_token', CSRF_TOKEN);
      formData.append('filePath', previewState.currentFilePath);

      const response = await fetch(API_DELETE_FILE, {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.ok) {
        showNotification('File deleted successfully!', 'success');

        // Close the modal and refresh the file list
        closePreviewModal();
        await fetchServerList(currentSrvPath);
      } else {
        throw new Error(result.error || 'Failed to delete file');
      }

    } catch (error) {
      console.error('Delete error:', error);
      showNotification('Failed to delete file: ' + error.message, 'error');
    } finally {
      // Restore button state
      const deleteBtn = document.querySelector('button[onclick="deleteCurrentFile()"]');
      deleteBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';
      deleteBtn.disabled = false;
    }
  }

  function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${type === 'success' ? 'bg-green-500 text-white' :
      type === 'error' ? 'bg-red-500 text-white' :
        'bg-blue-500 text-white'
      }`;
    notification.innerHTML = `
      <div class="flex items-center">
        <i class="fa-solid ${type === 'success' ? 'fa-check-circle' :
        type === 'error' ? 'fa-exclamation-circle' :
          'fa-info-circle'
      } mr-2"></i>
        <span>${message}</span>
      </div>
    `;

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
      notification.remove();
    }, 3000);
  }

  // ---- DOM Elements
  const fileNoEl = document.getElementById('fileNo');
  const folderEl = document.getElementById('folderInput');
  const statusEl = document.getElementById('status');
  const migrateBtn = document.getElementById('migrateBtn');
  const ZIP_SIZE_THRESHOLD = 200 * 1024 * 1024; // 200 MB
  const ZIP_COUNT_THRESHOLD = 250;

  // Progress modal helpers
  function openProgress(title, sub, pct = 0) {
    document.getElementById('progressTitle').textContent = title;
    document.getElementById('progressSub').textContent = sub || '';
    document.querySelector('#progressModal .progress-bar').style.width = pct + '%';
    document.getElementById('progressText').textContent = pct + '%';
    document.getElementById('progressDone').classList.add('hidden');
    const m = document.getElementById('progressModal');
    m.classList.remove('hidden'); m.classList.add('flex');
  }
  function setProgress(pct) {
    document.querySelector('#progressModal .progress-bar').style.width = pct + '%';
    document.getElementById('progressText').textContent = pct + '%';
  }
  function doneProgress() {
    document.getElementById('progressDone').classList.remove('hidden');
    setTimeout(() => {
      const m = document.getElementById('progressModal');
      m.classList.add('hidden'); m.classList.remove('flex');
    }, 600);
  }

  // ---- Upload state
  let selectedFiles = [];
  let ready = false;
  let localParentName = null;
  let totalSelectedBytes = 0;

  const norm = s => (s || '').trim();

  function detectSelectedRoot(files) {
    const segs = [];
    for (const f of files) {
      const rel = f.webkitRelativePath || f.name;
      const first = (rel.split('/')[0] || '').trim();
      if (first) segs.push(first.toLowerCase());
    }
    if (!segs.length) return null;
    const unique = Array.from(new Set(segs));
    if (unique.length === 1 && unique[0] !== 'a3' && unique[0] !== 'a4') {
      return unique[0];
    }
    return null;
  }

  function validateUploadSection() {
    const typedRaw = norm(fileNoEl.value);
    const typed = typedRaw.toLowerCase();
    const hasFiles = selectedFiles.length > 0;

    if (!hasFiles) {
      statusEl.textContent = 'No folder selected.';
      migrateBtn.disabled = true; ready = false; return;
    }

    const inferred = detectSelectedRoot(selectedFiles);
    localParentName = inferred;

    if (!typed) {
      statusEl.textContent = inferred
        ? `Detected local folder "${inferred}". Enter the File Number to proceed.`
        : 'Folder selected. Enter the File Number to proceed.';
      migrateBtn.disabled = true; ready = false; return;
    }

    // Check if folder name matches file number (case-insensitive)
    if (inferred) {
      const match = (typed === inferred.toLowerCase());
      if (!match) {
        statusEl.innerHTML = `<div class="text-yellow-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Warning: Folder name is <code>${inferred}</code> but file number is <code>${typedRaw}</code>. Files will be stored as <code>${typedRaw}</code>.</div>${buildPerformanceHint()}`;
        migrateBtn.disabled = false; ready = true; return;
      }
      statusEl.innerHTML = `<span class="text-green-700"><i class="fa-solid fa-circle-check mr-1"></i>Ready:</span> <b>${typedRaw}</b> will be migrated with all subfolders and files (${selectedFiles.length} items).${buildPerformanceHint()}`;
      migrateBtn.disabled = false; ready = true; return;
    } else {
      statusEl.innerHTML = `<span class="text-green-700"><i class="fa-solid fa-circle-check mr-1"></i>Ready:</span> <b>${typedRaw}</b> (parent name not detectable by browser). All selected items (${selectedFiles.length}) will be zipped and migrated.${buildPerformanceHint()}`;
      migrateBtn.disabled = false; ready = true; return;
    }
  }

  folderEl.addEventListener('change', () => {
    selectedFiles = Array.from(folderEl.files || []);
    totalSelectedBytes = selectedFiles.reduce((sum, file) => sum + (file.size || 0), 0);
    validateUploadSection();
  });
  fileNoEl.addEventListener('input', validateUploadSection);

  function shouldPreferZipUpload() {
    return totalSelectedBytes >= ZIP_SIZE_THRESHOLD || selectedFiles.length >= ZIP_COUNT_THRESHOLD;
  }

  function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = bytes;
    let unitIndex = 0;
    while (size >= 1024 && unitIndex < units.length - 1) {
      size /= 1024;
      unitIndex++;
    }
    const precision = size >= 10 || unitIndex === 0 ? 0 : 1;
    return `${size.toFixed(precision)} ${units[unitIndex]}`;
  }

  function buildPerformanceHint() {
    if (!selectedFiles.length) {
      return '';
    }
    const sizeLabel = formatBytes(totalSelectedBytes);
    if (shouldPreferZipUpload()) {
      return `<div class="mt-2 text-xs text-blue-700 flex items-start gap-1"><i class="fa-solid fa-bolt mt-0.5"></i><span>Large folder detected (~${sizeLabel}, ${selectedFiles.length} files). We'll compress before uploading to keep things fast.</span></div>`;
    }
    return `<div class="mt-2 text-xs text-muted-foreground">${selectedFiles.length} files selected • ${sizeLabel}</div>`;
  }

  // ---- Client zip (preserves subfolders)
  async function filesToZipBlob(files, folderName, onUpdate) {
    const zip = new JSZip();
    for (const f of files) {
      const rel = computeRelativeUploadPath(f, folderName);
      zip.file(rel, f);
    }
    return await zip.generateAsync(
      { type: 'blob', compression: 'DEFLATE', compressionOptions: { level: 6 } },
      metadata => {
        if (typeof onUpdate === 'function') {
          onUpdate(metadata);
        }
      }
    );
  }

  function computeRelativeUploadPath(file, folderName) {
    let relativePath = file.webkitRelativePath || file.name || '';
    if (!relativePath) {
      return file.name || 'document';
    }

    relativePath = relativePath.replace(/\\/g, '/').replace(/^\/+/, '');

    const parts = relativePath.split('/').filter(Boolean);
    if (parts.length > 1) {
      const firstSegment = parts[0].toLowerCase();
      const normalizedFolder = (folderName || '').toLowerCase();

      if (normalizedFolder && firstSegment === normalizedFolder) {
        parts.shift();
      } else if (localParentName && firstSegment === localParentName.toLowerCase()) {
        parts.shift();
      }
    }

    return parts.join('/') || (file.name || 'document');
  }

  async function migrateNow() {

    if (!ready) return;



    const folderName = norm(fileNoEl.value);

    const zipFirst = shouldPreferZipUpload();



    if (zipFirst) {

      openProgress('Creating ZIP Archive', `Compressing ${selectedFiles.length} files (${formatBytes(totalSelectedBytes)})`, 8);

      const zipAttempt = await attemptZipUpload(folderName);

      if (zipAttempt.success) {

        return;

      }



      console.warn('Zip-first upload failed, attempting direct upload.', zipAttempt.message || 'No message');

      openProgress('Uploading Files', `${folderName} ? storage`, 5);

      const directFallback = await attemptDirectUpload(folderName);

      if (directFallback.success) {

        return;

      }



      handleUploadFailure(zipAttempt.message, directFallback.message);

      return;

    }



    openProgress('Uploading Files', `${folderName} ? storage`, 5);

    const directResult = await attemptDirectUpload(folderName);



    if (directResult.success) {

      return;

    }



    console.warn('Direct upload failed, falling back to ZIP.', directResult.message || 'No message');

    openProgress('Creating ZIP Archive', `${folderName} ? storage`, 10);



    const zipResult = await attemptZipUpload(folderName);

    if (zipResult.success) {

      return;

    }



    handleUploadFailure(directResult.message, zipResult.message);

  }



  migrateBtn.addEventListener('click', migrateNow);

  function handleUploadFailure(primaryMessage, secondaryMessage) {
    const modal = document.getElementById('progressModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');

    const failureMessage = primaryMessage || secondaryMessage || 'Upload failed. Please try again or contact support.';
    alert('Migration failed: ' + failureMessage);
  }

  async function attemptDirectUpload(folderName) {
    try {
      document.getElementById('progressSub').textContent = `Uploading ${selectedFiles.length} files (${formatBytes(totalSelectedBytes)}) directly...`;

      const form = new FormData();
      form.append('_token', CSRF_TOKEN);
      form.append('folderName', folderName);
      form.append('uploadMethod', 'direct');
      form.append('registry_type', document.getElementById('registryType').value);

      const totalFiles = selectedFiles.length || 1;
      selectedFiles.forEach((file, index) => {
        const relativePath = computeRelativeUploadPath(file, folderName);
        form.append('files[]', file);
        form.append('paths[]', relativePath);

        if (index % 25 === 0) {
          const pct = 15 + Math.round(((index + 1) / totalFiles) * 30);
          setProgress(Math.min(pct, 50));
        }
      });

      setProgress(65);
      document.getElementById('progressSub').textContent = 'Finalizing direct upload on server...';

      const res = await fetch(MIGRATE_ENDPOINT, { method: 'POST', body: form });
      const data = await res.json().catch(() => ({}));

      if (res.ok && data.ok) {
        await handleMigrationSuccess(data, folderName, 'direct upload');
        return { success: true };
      }

      const msg = (data && (data.error || data.message)) || `HTTP ${res.status}`;
      return { success: false, message: msg };
    } catch (error) {
      console.error('Direct upload exception', error);
      return { success: false, message: error.message || 'Direct upload error' };
    }
  }

  async function attemptZipUpload(folderName) {
    try {
      document.getElementById('progressSub').textContent = 'Compressing files into ZIP archive...';

      const zipBlob = await filesToZipBlob(selectedFiles, folderName, (metadata) => {
        const pct = 10 + Math.round((metadata.percent || 0) * 0.4);
        setProgress(Math.min(55, pct));
        document.getElementById('progressSub').textContent = `Compressing files into ZIP archive (${(metadata.percent || 0).toFixed(1)}%)...`;
      });
      setProgress(60);

      document.getElementById('progressSub').textContent = 'Uploading ZIP archive (server will extract)...';

      const form = new FormData();
      form.append('_token', CSRF_TOKEN);
      form.append('folderName', folderName);
      form.append('uploadMethod', 'zip');
      form.append('registry_type', document.getElementById('registryType').value);
      form.append('zip', zipBlob, `${folderName}.zip`);

      setProgress(70);

      const res = await fetch(MIGRATE_ENDPOINT, { method: 'POST', body: form });
      const data = await res.json().catch(() => ({}));

      if (res.ok && data.ok) {
        await handleMigrationSuccess(data, folderName, 'zip fallback');
        return { success: true };
      }

      const msg = (data && (data.error || data.message)) || `HTTP ${res.status}`;
      return { success: false, message: msg };
    } catch (error) {
      console.error('ZIP upload exception', error);
      return { success: false, message: error.message || 'ZIP upload error' };
    }
  }

  async function handleMigrationSuccess(data, folderName, methodLabel) {
    document.getElementById('progressSub').textContent = 'Refreshing server view and logs...';
    setProgress(90);
    await Promise.all([fetchServerList(currentSrvPath), fetchLogs()]);
    setProgress(100);
    doneProgress();

    statusEl.innerHTML = `<span class="text-green-700">Migration complete (${methodLabel}).</span> Saved to <code>${data.serverPath || '/storage/' + folderName}</code>.`;
    folderEl.value = '';
    selectedFiles = [];
    ready = false;
    migrateBtn.disabled = true;
  }

  // ----------------------------
  // Server browser + logs (GET)
  // ----------------------------
  const srvStatusEl = document.getElementById('srvStatus');
  const syncMigrationBtn = document.getElementById('syncMigrationBtn');
  let currentSrvPath = '';
  let isSyncingMigrationLog = false;
  let autoSyncTimer = null;

  function updateSrvStatus(message, state = 'info') {
    if (!srvStatusEl) {
      return;
    }
    if (!message) {
      srvStatusEl.classList.add('hidden');
      return;
    }

    srvStatusEl.textContent = message;
    srvStatusEl.classList.remove('hidden');
    srvStatusEl.classList.remove('text-muted-foreground', 'text-green-700', 'text-red-600');

    if (state === 'success') {
      srvStatusEl.classList.add('text-green-700');
    } else if (state === 'error') {
      srvStatusEl.classList.add('text-red-600');
    } else {
      srvStatusEl.classList.add('text-muted-foreground');
    }
  }

  async function syncMigrationLog() {
    if (isSyncingMigrationLog) {
      return;
    }

    isSyncingMigrationLog = true;
    if (syncMigrationBtn) {
      syncMigrationBtn.disabled = true;
    }

    updateSrvStatus('Scanning for new folders...', 'info');

    try {
      const response = await fetch(API_SYNC_MIGRATIONS, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': CSRF_TOKEN
        },
        body: JSON.stringify({})
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok || !data.ok) {
        const errorMessage = (data && (data.error || data.message)) || `HTTP ${response.status}`;
        throw new Error(errorMessage);
      }

      const addedCount = data.added_count || 0;
      const dbAddedCount = data.db_added_count || 0;
      const dbSkippedCount = data.db_skipped_count || 0;
      const errorCount = Array.isArray(data.errors) ? data.errors.length : 0;

      const summaryParts = [];

      if (addedCount > 0) {
        summaryParts.push(`${addedCount} new folder${addedCount === 1 ? '' : 's'} logged`);
      }

      if (dbAddedCount > 0) {
        summaryParts.push(`${dbAddedCount} folder${dbAddedCount === 1 ? '' : 's'} synced to database`);
      }

      if (dbSkippedCount > 0) {
        summaryParts.push(`${dbSkippedCount} folder${dbSkippedCount === 1 ? '' : 's'} already synced`);
      }

      const summaryMessage = summaryParts.length ? summaryParts.join(' • ') : (data.message || 'No new folders detected.');

      if (errorCount > 0) {
        console.warn('Migration sync errors', data.errors);
        updateSrvStatus(`${summaryMessage} • ${errorCount} error${errorCount === 1 ? '' : 's'} encountered.`, 'error');
      } else if (addedCount > 0 || dbAddedCount > 0) {
        updateSrvStatus(summaryMessage, 'success');
      } else {
        updateSrvStatus(summaryMessage, 'info');
      }

      await Promise.all([
        fetchLogs(),
        fetchServerList(currentSrvPath)
      ]);
    } catch (error) {
      console.error('Migration sync error', error);
      updateSrvStatus(error.message || 'Failed to sync migration log.', 'error');
    } finally {
      if (syncMigrationBtn) {
        syncMigrationBtn.disabled = false;
      }
      isSyncingMigrationLog = false;
    }
  }

  function srvRow(html) { const tr = document.createElement('tr'); tr.className = 'border-b row'; tr.innerHTML = html; return tr; }
  function fmtBytes(n) {
    if (!n && n !== 0) return '-';
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
    return (n / (1024 * 1024)).toFixed(1) + ' MB';
  }
  function fmtDate(ts) {
    if (!ts) return '-';
    const d = new Date(ts * 1000);
    return d.toLocaleString();
  }
  function clearPreview() {
    document.getElementById('previewBox').innerHTML =
      `<div class="text-sm text-muted-foreground">Select a file to preview.</div>`;
  }

  function showQuickPreview(href, name) {
    const box = document.getElementById('previewBox');
    const ext = (name.split('.').pop() || '').toLowerCase();

    if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) {
      // Add cache buster to force refresh
      const cacheBuster = '?t=' + Date.now();
      box.innerHTML = `
        <div class="text-center">
          <img src="${href}${cacheBuster}" alt="${name}" class="max-w-full max-h-32 mx-auto rounded border cursor-pointer" 
               onclick="openPreviewModal('${name}')">
          <div class="text-xs mt-2">Click to open in preview modal</div>
        </div>
      `;
    } else if (ext === 'pdf') {
      box.innerHTML = `
        <div class="text-center">
          <i class="fa-solid fa-file-pdf text-4xl text-red-500 mb-2"></i>
          <div class="text-sm">${name}</div>
          <button class="btn btn-sm mt-2" onclick="openPreviewModal('${name}')">
            <i class="fa-solid fa-expand mr-1"></i> Preview PDF
          </button>
        </div>
      `;
    } else {
      box.innerHTML = `
        <div class="text-center">
          <i class="fa-solid fa-file text-4xl text-muted-foreground mb-2"></i>
          <div class="text-sm">${name}</div>
          <div class="text-xs text-muted-foreground mt-1">No preview available</div>
        </div>
      `;
    }
  }

  async function fetchServerList(subPath = '') {
    const url = new URL(API_LIST, window.location.href);
    if (subPath) url.searchParams.set('path', subPath);
    const res = await fetch(url);
    const data = await res.json();
    if (!res.ok || !data.ok) { alert(data.error || ('HTTP ' + res.status)); return; }

    currentSrvPath = data.sub || '';
    previewState.currentDirItems = data.items || [];

    // Update user mode indicator
    const indicator = document.getElementById('userModeIndicator');
    if (indicator) {
      if (data.isAdmin) {
        indicator.innerHTML = `
          <div class="flex items-center gap-2 px-3 py-1 bg-amber-50 border border-amber-200 rounded-md">
            <i class="fa-solid fa-crown text-amber-600"></i>
            <span class="text-xs font-medium text-amber-700">Admin View (All Files)</span>
          </div>
        `;
        indicator.classList.remove('hidden');
      } else {
        indicator.innerHTML = `
          <div class="flex items-center gap-2 px-3 py-1 bg-blue-50 border border-blue-200 rounded-md">
            <i class="fa-solid fa-user text-blue-600"></i>
            <span class="text-xs font-medium text-blue-700">My Files Only</span>
          </div>
        `;
        indicator.classList.remove('hidden');
      }
    }

    document.getElementById('srvPath').textContent = '/storage/EDMS/BLIND_SCAN' + (currentSrvPath ? '/' + currentSrvPath : '');

    const crumbs = data.crumbs || [];
    const crumbsEl = document.getElementById('srvCrumbs');
    if (!crumbs.length) {
      crumbsEl.textContent = 'Root';
    } else {
      crumbsEl.innerHTML = crumbs.map((c, i) => (
        `<button class="text-blue-700 hover:underline" data-path="${c.path}">${c.name}</button>${i < crumbs.length - 1 ? ' / ' : ''}`
      )).join('');
      crumbsEl.querySelectorAll('button').forEach(b => {
        b.addEventListener('click', () => fetchServerList(b.dataset.path));
      });
    }

    const $table = $('#srvTable');
    const tbody = document.getElementById('srvRows');

    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#srvTable')) {
      $table.DataTable().destroy();
    }

    tbody.innerHTML = '';
    let sn = 1;

    if (currentSrvPath) {
      const up = currentSrvPath.split('/').slice(0, -1).join('/');
      const trUp = srvRow(`
        <td class="p-3"></td>
        <td class="p-3"><i class="fa-regular fa-folder"></i></td>
        <td class="p-3"><button class="srv-nav text-blue-700 hover:underline" data-path="${up}">Go Back</button></td>
        <td class="p-3">-</td>
        <td class="p-3">-</td>
        <td class="p-3"></td>
      `);
      trUp.querySelector('.srv-nav').addEventListener('click', () => fetchServerList(up));
      tbody.appendChild(trUp);
    }

    const items = data.items || [];
    // If no items and at root, DataTables will show its own empty message.
    // However, if we're in a sub-path, we always have the 'Go Back' row.
    if (items.length > 0) {
      for (const item of items) {
        if (item.type === 'dir') {
          const tr = srvRow(`
            <td class="p-3">${sn++}</td>
            <td class="p-3"><i class="fa-regular fa-folder text-yellow-500"></i></td>
            <td class="p-3"><button class="srv-nav text-blue-700 hover:underline" data-path="${(currentSrvPath ? currentSrvPath + '/' : '') + item.name}">${item.name}</button></td>
            <td class="p-3">-</td>
            <td class="p-3">${fmtDate(item.mtime)}</td>
            <td class="p-3"></td>
          `);
          tr.querySelector('.srv-nav').addEventListener('click', () => fetchServerList((currentSrvPath ? currentSrvPath + '/' : '') + item.name));
          tbody.appendChild(tr);
        } else {
          const href = item.href || '#';
          const isPreviewable = item.name.toLowerCase().endsWith('.pdf') ||
            item.name.toLowerCase().match(/\.(jpg|jpeg|png|gif|webp|bmp)$/i);
          const tr = srvRow(`
            <td class="p-3">${sn++}</td>
            <td class="p-3"><i class="fa-regular fa-file"></i></td>
            <td class="p-3">${item.name}</td>
            <td class="p-3">${fmtBytes(item.size)}</td>
            <td class="p-3">${fmtDate(item.mtime)}</td>
            <td class="p-3">
              <div class="flex gap-2">
                ${isPreviewable ? `<button class="btn srv-preview text-xs" data-href="${href}" data-name="${item.name}">Preview</button>` : ''}
                <a class="btn text-xs" href="${href}" target="_blank" rel="noopener">Open</a>
              </div>
            </td>
          `);
          if (isPreviewable) {
            tr.querySelector('.srv-preview').addEventListener('click', (e) => {
              showQuickPreview(e.currentTarget.dataset.href, e.currentTarget.dataset.name);
            });
          }
          tbody.appendChild(tr);
        }
      }
    }

    // Initialize DataTable for server list
    $table.DataTable({
      pageLength: 50,
      order: [[0, 'asc']],
      columnDefs: [
        { orderable: false, targets: [1, 5] } // icon and actions columns
      ],
      language: {
        emptyTable: "No files found in this directory.",
        search: "_INPUT_",
        searchPlaceholder: "Search files..."
      }
    });

    clearPreview();
  }

  async function fetchLogs() {
    const res = await fetch(API_LOGS);
    const data = await res.json();
    if (!res.ok || !data.ok) { alert(data.error || ('HTTP ' + res.status)); return; }
    const logs = data.logs || [];
    const root = document.getElementById('logsContent');
    if (!logs.length) {
      root.innerHTML = `
        <div class="text-center py-8">
          <i class="fa-solid fa-clock-rotate-left text-3xl text-muted-foreground mx-auto mb-4"></i>
          <p class="text-muted-foreground">No migrations yet</p>
          <p class="text-sm text-muted-foreground">After migrating, entries will appear here.</p>
        </div>`;
      return;
    }
    root.innerHTML = `
      <div class="border rounded-lg overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-muted">
            <tr class="border-b">
              <th class="text-left p-3">When</th>
              <th class="text-left p-3">Parent Folder</th>
              <th class="text-left p-3">Server Path</th>
            </tr>
          </thead>
          <tbody>
            ${logs.map(m => `
              <tr class="border-b">
                <td class="p-3">${m.when || '-'}</td>
                <td class="p-3 font-mono">${m.folder || '-'}</td>
                <td class="p-3 font-mono"><a class="text-blue-700 hover:underline" href="${m.serverPath || '#'}" target="_blank" rel="noopener">${m.serverPath || '-'}</a></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>`;
  }

  // Modal event listeners
  document.getElementById('modalClose').addEventListener('click', closePreviewModal);
  document.getElementById('modalPrev').addEventListener('click', () => navigatePreview(-1));
  document.getElementById('modalNext').addEventListener('click', () => navigatePreview(1));

  // Keyboard navigation
  document.addEventListener('keydown', (e) => {
    if (!previewState.isOpen) return;

    switch (e.key) {
      case 'Escape':
        closePreviewModal();
        break;
      case 'ArrowLeft':
        navigatePreview(-1);
        break;
      case 'ArrowRight':
        navigatePreview(1);
        break;
    }
  });

  // Tabs
  const serverPanel = document.getElementById('serverPanel');
  const logsPanel = document.getElementById('logsPanel');
  document.getElementById('tabServer').addEventListener('click', () => { serverPanel.classList.remove('hidden'); logsPanel.classList.add('hidden'); });
  document.getElementById('tabLogs').addEventListener('click', () => { logsPanel.classList.remove('hidden'); serverPanel.classList.add('hidden'); });
  document.getElementById('srvRefresh').addEventListener('click', () => fetchServerList(currentSrvPath));
  if (syncMigrationBtn) {
    syncMigrationBtn.addEventListener('click', syncMigrationLog);
  }

  function startAutomaticSync() {
    if (autoSyncTimer !== null) {
      return;
    }

    // Run an initial sync so logs/DB catch up without manual clicks
    syncMigrationLog();

    autoSyncTimer = setInterval(() => {
      syncMigrationLog();
    }, MIGRATION_SYNC_INTERVAL_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startAutomaticSync);
  } else {
    startAutomaticSync();
  }

  // Boot
  (async function () {
    await fetchServerList('');
    await fetchLogs();
  })();
</script>