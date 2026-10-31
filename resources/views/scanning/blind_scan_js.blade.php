<script>
  // ---- Config
  const MIGRATE_ENDPOINT = "{{ route('blind-scanning.migrate') }}";
  const API_LIST = "{{ route('blind-scanning.list') }}";
  const API_LOGS = "{{ route('blind-scanning.logs') }}";
  const API_SYNC_MIGRATIONS = "{{ route('blind-scanning.sync-migrations') }}";
  const API_SAVE_IMAGE = "{{ route('blind-scanning.save-image') }}";
  const API_DELETE_FILE = "{{ route('blind-scanning.delete-file') }}";
  const API_DELETE_FOLDER = "{{ route('blind-scanning.delete-folder') }}";
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
  const registryEl = document.getElementById('registryType');
  const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'gif', 'webp', 'pdf'];
  // A folder goes up in batches: each request stays far below the server's
  // size limit, progress is measured on real bytes sent, and a failed batch is
  // retried on its own instead of restarting the whole folder.
  const BATCH_MAX_FILES = 20;
  const BATCH_MAX_BYTES = 80 * 1024 * 1024;
  const BATCH_RETRIES = 3;

  // Progress modal helpers
  function openProgress(title, sub, pct = 0) {
    document.getElementById('progressTitle').textContent = title;
    document.getElementById('progressSub').textContent = sub || '';
    document.getElementById('progressDetail').textContent = '';
    setProgress(pct);
    document.getElementById('progressDone').classList.add('hidden');
    document.getElementById('progressCancel').classList.remove('hidden');
    document.getElementById('progressCancel').disabled = false;
    const m = document.getElementById('progressModal');
    m.classList.remove('hidden'); m.classList.add('flex');
  }
  function setProgress(pct) {
    document.querySelector('#progressModal .progress-bar').style.width = pct + '%';
    document.getElementById('progressText').textContent = pct + '%';
  }
  function setProgressDetail(text) {
    document.getElementById('progressDetail').textContent = text || '';
  }
  function closeProgress() {
    const m = document.getElementById('progressModal');
    m.classList.add('hidden'); m.classList.remove('flex');
  }
  function doneProgress() {
    document.getElementById('progressCancel').classList.add('hidden');
    document.getElementById('progressDone').classList.remove('hidden');
    setTimeout(closeProgress, 900);
  }

  // ---- Upload state
  let selectedFiles = [];
  let skippedLocal = [];
  let ready = false;
  let localParentName = null;
  let totalSelectedBytes = 0;
  let uploading = false;
  let cancelRequested = false;

  const norm = s => (s || '').trim();
  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  document.getElementById('progressCancel').addEventListener('click', () => {
    cancelRequested = true;
    document.getElementById('progressCancel').disabled = true;
    setProgressDetail('Stopping after the current batch...');
  });

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

  // Only scans travel: Thumbs.db, desktop.ini and shortcuts stay behind.
  function isAllowedFile(file) {
    const name = (file.name || '').toLowerCase();
    if (!name || name.startsWith('.') || name === 'thumbs.db' || name === 'desktop.ini') return false;
    const ext = name.includes('.') ? name.split('.').pop() : '';
    return ALLOWED_EXT.includes(ext);
  }

  function paperCounts(files) {
    const counts = { A4: 0, A3: 0, other: 0 };
    for (const f of files) {
      const rel = '/' + (f.webkitRelativePath || f.name || '').replace(/\\/g, '/').toUpperCase();
      if (rel.includes('/A3/')) counts.A3++;
      else if (rel.includes('/A4/')) counts.A4++;
      else counts.other++;
    }
    return counts;
  }

  function validateUploadSection() {
    const typedRaw = norm(fileNoEl.value);
    const registryOk = !!(registryEl && registryEl.value);
    const skippedNote = skippedLocal.length
      ? `<div class="mt-1 text-xs text-muted-foreground">${skippedLocal.length} non-image file(s) will be left out (e.g. ${escapeHtml(skippedLocal[0].name)}).</div>`
      : '';

    if (!selectedFiles.length) {
      statusEl.innerHTML = skippedLocal.length
        ? `<span class="text-red-600">The folder has no image or PDF files.</span>${skippedNote}`
        : 'No folder selected.';
      migrateBtn.disabled = true; ready = false; return;
    }

    const inferred = detectSelectedRoot(selectedFiles);
    localParentName = inferred;

    if (!typedRaw) {
      statusEl.innerHTML = `Folder selected${inferred ? ` ("${escapeHtml(inferred)}")` : ''}. Click <b>Select</b> to choose the file number.${skippedNote}`;
      migrateBtn.disabled = true; ready = false; return;
    }
    if (!registryOk) {
      statusEl.innerHTML = `Select the <b>registry</b> these scans belong to.${skippedNote}`;
      migrateBtn.disabled = true; ready = false; return;
    }

    const counts = paperCounts(selectedFiles);
    const registryLabel = registryEl.options[registryEl.selectedIndex].text;
    const mismatch = inferred && inferred.toLowerCase() !== typedRaw.toLowerCase()
      ? `<div class="text-yellow-700 mt-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i>The folder is named <code>${escapeHtml(inferred)}</code> but the file number is <code>${escapeHtml(typedRaw)}</code>. You will be asked to confirm.</div>`
      : '';

    statusEl.innerHTML = `<span class="text-green-700"><i class="fa-solid fa-circle-check mr-1"></i>Ready:</span>
      <b>${selectedFiles.length}</b> file(s), ${formatBytes(totalSelectedBytes)} &mdash; A4 ${counts.A4}, A3 ${counts.A3}${counts.other ? `, no paper folder ${counts.other}` : ''}
      &rarr; <b>${escapeHtml(typedRaw)}</b> in <b>${escapeHtml(registryLabel)}</b>.${mismatch}${skippedNote}`;
    migrateBtn.disabled = uploading; ready = true;
  }

  folderEl.addEventListener('change', () => {
    const all = Array.from(folderEl.files || []);
    selectedFiles = all.filter(isAllowedFile);
    skippedLocal = all.filter(f => !isAllowedFile(f));
    totalSelectedBytes = selectedFiles.reduce((sum, file) => sum + (file.size || 0), 0);
    validateUploadSection();
  });
  if (registryEl) {
    registryEl.addEventListener('change', validateUploadSection);
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

  function formatDuration(seconds) {
    if (!isFinite(seconds) || seconds <= 0) return 'a moment';
    if (seconds < 60) return `${Math.ceil(seconds)}s`;
    const m = Math.floor(seconds / 60);
    return m < 60 ? `${m}m ${Math.ceil(seconds % 60)}s` : `${Math.floor(m / 60)}h ${m % 60}m`;
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

  function buildBatches(files) {
    const batches = [];
    let current = [];
    let bytes = 0;
    for (const file of files) {
      if (current.length && (current.length >= BATCH_MAX_FILES || bytes + file.size > BATCH_MAX_BYTES)) {
        batches.push(current);
        current = [];
        bytes = 0;
      }
      current.push(file);
      bytes += file.size || 0;
    }
    if (current.length) batches.push(current);
    return batches;
  }

  // XHR rather than fetch: only XHR reports upload progress.
  function sendBatch(form, onProgress) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', MIGRATE_ENDPOINT);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-CSRF-TOKEN', CSRF_TOKEN);
      xhr.timeout = 15 * 60 * 1000;
      xhr.upload.onprogress = e => { if (e.lengthComputable) onProgress(e.loaded); };
      xhr.onload = () => {
        let data = {};
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (e) { data = {}; }
        const error = data.error || data.message || (xhr.status === 419 ? 'Your session expired. Reload the page and sign in again.' : `HTTP ${xhr.status}`);
        resolve({ ok: xhr.status >= 200 && xhr.status < 300 && data.ok, status: xhr.status, data, error });
      };
      xhr.onerror = () => reject(new Error('Network error — check the connection to the server.'));
      xhr.ontimeout = () => reject(new Error('The server took too long to answer.'));
      xhr.send(form);
    });
  }

  /**
   * Show the selected folder before anything is sent: where it will go, the
   * page counts, a thumbnail of every scan (grouped A4 / A3), and any warning.
   * Resolves true only when the user confirms the upload.
   */
  async function previewBeforeUpload(folderName, registryLabel) {
    const mismatch = localParentName && localParentName !== folderName.toLowerCase();

    if (typeof Swal === 'undefined') {
      return confirm(`${mismatch ? `The folder you picked is "${localParentName}" but the file number is "${folderName}".\n\n` : ''}Upload ${selectedFiles.length} file(s) to ${folderName} in ${registryLabel}?`);
    }

    const MAX_TILES = 150;
    const objectUrls = [];
    const counts = paperCounts(selectedFiles);
    const groups = { A4: [], A3: [], other: [] };
    selectedFiles.forEach(file => {
      const rel = '/' + (file.webkitRelativePath || file.name || '').replace(/\\/g, '/').toUpperCase();
      (rel.includes('/A3/') ? groups.A3 : rel.includes('/A4/') ? groups.A4 : groups.other).push(file);
    });
    const naturalSort = (a, b) => (a.name || '').localeCompare(b.name || '', undefined, { numeric: true, sensitivity: 'base' });
    Object.values(groups).forEach(list => list.sort(naturalSort));

    let shown = 0;
    const tile = file => {
      const ext = (file.name.split('.').pop() || '').toLowerCase();
      const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext);
      let media = `<div class="bsp-icon"><i class="fa-solid ${ext === 'pdf' ? 'fa-file-pdf text-red-500' : 'fa-file-image text-gray-400'}"></i></div>`;
      if (isImage) {
        const url = URL.createObjectURL(file);
        objectUrls.push(url);
        media = `<img src="${url}" alt="" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'bsp-icon',innerHTML:'<i class=&quot;fa-solid fa-file-image text-gray-400&quot;></i>'}))">`;
      }
      return `<a class="bsp-tile" ${isImage ? `href="${objectUrls[objectUrls.length - 1]}" target="_blank" rel="noopener"` : ''} title="${escapeHtml(file.webkitRelativePath || file.name)}">
          ${media}
          <span class="bsp-name">${escapeHtml(file.name)}</span>
          <span class="bsp-size">${formatBytes(file.size)}</span>
        </a>`;
    };
    const section = (label, list) => {
      if (!list.length) return '';
      const room = Math.max(0, MAX_TILES - shown);
      const visible = list.slice(0, room);
      shown += visible.length;
      const more = list.length - visible.length;
      return `<div class="bsp-section">
          <div class="bsp-section-title">${label} <span>${list.length} file(s)</span></div>
          <div class="bsp-grid">${visible.map(tile).join('')}</div>
          ${more > 0 ? `<div class="bsp-more">+ ${more} more not shown</div>` : ''}
        </div>`;
    };

    const html = `
      <style>
        .bsp { text-align: left; font-size: 13px; }
        .bsp-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; margin-bottom: 12px; }
        .bsp-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 10px; background: #f9fafb; }
        .bsp-card small { display: block; color: #6b7280; font-size: 11px; }
        .bsp-card b { font-size: 14px; color: #111827; word-break: break-all; }
        .bsp-warn { border: 1px solid #fcd34d; background: #fffbeb; color: #92400e; border-radius: 8px; padding: 8px 10px; margin-bottom: 10px; }
        .bsp-note { color: #6b7280; font-size: 12px; margin-bottom: 10px; }
        .bsp-scroll { max-height: 52vh; overflow-y: auto; padding-right: 4px; }
        .bsp-section { margin-bottom: 12px; }
        .bsp-section-title { font-weight: 600; margin: 4px 0 6px; color: #374151; }
        .bsp-section-title span { font-weight: 400; color: #6b7280; font-size: 12px; margin-left: 4px; }
        .bsp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; }
        .bsp-tile { display: flex; flex-direction: column; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #fff; text-decoration: none; color: inherit; }
        .bsp-tile:hover { border-color: #6366f1; box-shadow: 0 2px 8px rgba(99,102,241,.2); }
        .bsp-tile img, .bsp-icon { width: 100%; height: 120px; object-fit: contain; background: #f3f4f6; display: flex; align-items: center; justify-content: center; font-size: 34px; }
        .bsp-name { font-size: 11px; padding: 4px 6px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bsp-size { font-size: 10px; color: #9ca3af; padding: 0 6px 5px; }
        .bsp-more { font-size: 12px; color: #6b7280; margin-top: 6px; }
      </style>
      <div class="bsp">
        <div class="bsp-summary">
          <div class="bsp-card"><small>File number</small><b>${escapeHtml(folderName)}</b></div>
          <div class="bsp-card"><small>Registry</small><b>${escapeHtml(registryLabel)}</b></div>
          <div class="bsp-card"><small>Files</small><b>${selectedFiles.length}</b> <small>${formatBytes(totalSelectedBytes)}</small></div>
          <div class="bsp-card"><small>Paper size</small><b>A4 ${counts.A4} · A3 ${counts.A3}</b>${counts.other ? `<small>${counts.other} not in an A4/A3 folder (stored as A4)</small>` : ''}</div>
        </div>
        ${mismatch ? `<div class="bsp-warn"><i class="fa-solid fa-triangle-exclamation mr-1"></i>The folder you picked is <b>${escapeHtml(localParentName)}</b> but the file number is <b>${escapeHtml(folderName)}</b>. Check these are the right scans before uploading.</div>` : ''}
        ${skippedLocal.length ? `<div class="bsp-note">${skippedLocal.length} non-image file(s) will be left out: ${skippedLocal.slice(0, 5).map(f => escapeHtml(f.name)).join(', ')}${skippedLocal.length > 5 ? '…' : ''}</div>` : ''}
        <div class="bsp-note">Click a thumbnail to open it full size in a new tab.</div>
        <div class="bsp-scroll">
          ${section('A4', groups.A4)}${section('A3', groups.A3)}${section('Not in an A4/A3 folder', groups.other)}
        </div>
      </div>`;

    try {
      const result = await Swal.fire({
        title: 'Review scans before upload',
        html,
        width: '64rem',
        icon: mismatch ? 'warning' : undefined,
        showCancelButton: true,
        confirmButtonText: `<i class="fa-solid fa-paper-plane mr-1"></i> Upload ${selectedFiles.length} file(s)`,
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#4f46e5',
        reverseButtons: true,
        focusCancel: !!mismatch,
      });
      return result.isConfirmed;
    } finally {
      objectUrls.forEach(url => URL.revokeObjectURL(url));
    }
  }

  async function migrateNow() {
    if (!ready || uploading) return;

    const folderName = norm(fileNoEl.value);
    const registry = registryEl.value;
    const registryLabel = registryEl.options[registryEl.selectedIndex].text;

    if (!(await previewBeforeUpload(folderName, registryLabel))) {
      return;
    }

    uploading = true;
    cancelRequested = false;
    migrateBtn.disabled = true;

    const files = selectedFiles.slice();
    const totalBytes = Math.max(1, totalSelectedBytes);
    const batches = buildBatches(files);
    const totals = { saved: 0, skipped: 0, registered: 0, renamed: [], failed: [] };
    let bytesDone = 0;
    let serverPath = '';
    let completedBatches = 0;
    const started = Date.now();

    openProgress(`Uploading to ${registryLabel}`, `${folderName}: ${files.length} file(s), ${formatBytes(totalSelectedBytes)}, in ${batches.length} batch(es)`, 0);

    try {
      for (let i = 0; i < batches.length; i++) {
        if (cancelRequested) break;

        const batch = batches[i];
        const batchBytes = batch.reduce((sum, f) => sum + (f.size || 0), 0);
        let result = null;
        let lastError = '';

        for (let attempt = 1; attempt <= BATCH_RETRIES; attempt++) {
          const form = new FormData();
          form.append('_token', CSRF_TOKEN);
          form.append('folderName', folderName);
          form.append('registry', registry);
          form.append('uploadMethod', 'direct');
          form.append('batch_index', String(i));
          form.append('batch_total', String(batches.length));
          batch.forEach(file => {
            form.append('files[]', file);
            form.append('paths[]', computeRelativeUploadPath(file, folderName));
          });

          try {
            result = await sendBatch(form, loaded => {
              const done = bytesDone + Math.min(loaded, batchBytes);
              setProgress(Math.min(99, Math.round(done / totalBytes * 100)));
              const elapsed = Math.max(0.5, (Date.now() - started) / 1000);
              const speed = done / elapsed;
              setProgressDetail(`Batch ${i + 1} of ${batches.length} · ${formatBytes(done)} of ${formatBytes(totalSelectedBytes)} · ${formatBytes(speed)}/s · about ${formatDuration((totalBytes - done) / Math.max(speed, 1))} left`);
            });
            if (result.ok) break;
            lastError = result.error;
            // Validation and session errors will not fix themselves on retry.
            if (result.status >= 400 && result.status < 500 && ![408, 429].includes(result.status)) break;
          } catch (error) {
            lastError = error.message;
          }

          if (attempt < BATCH_RETRIES) {
            setProgressDetail(`Batch ${i + 1} failed (${lastError}). Retrying ${attempt + 1} of ${BATCH_RETRIES}...`);
            await sleep(1500 * attempt);
          }
        }

        if (!result || !result.ok) {
          throw new Error(`Batch ${i + 1} of ${batches.length} failed: ${lastError || 'unknown error'}.\n\nEverything sent before it is saved. Click Migrate again to send the rest — files already on the server are skipped, not duplicated.`);
        }

        const data = result.data || {};
        totals.saved += data.saved || 0;
        totals.skipped += data.skipped || 0;
        totals.registered += data.registered || 0;
        totals.renamed.push(...(data.renamed || []));
        totals.failed.push(...(data.failed || []));
        serverPath = data.serverPath || serverPath;
        bytesDone += batchBytes;
        completedBatches++;
        setProgress(Math.min(99, Math.round(bytesDone / totalBytes * 100)));
      }

      // A file PHP received incomplete (a network blip mid-upload) comes back as failed while the rest of its
      // batch saved (2026-10-30). Send just those once more, as a non-final batch so the migration is not logged twice.
      if (totals.failed.length && !cancelRequested) {
        const norm = p => String(p || '').replace(/\\/g, '/').split('/').filter(s => s && s !== '.' && s !== '..').join('/');
        const failedPaths = new Set(totals.failed.map(norm));
        const again = files.filter(f => failedPaths.has(norm(computeRelativeUploadPath(f, folderName))));
        if (again.length) {
          setProgressDetail(`Re-sending ${again.length} file(s) the server did not receive whole...`);
          const form = new FormData();
          form.append('_token', CSRF_TOKEN);
          form.append('folderName', folderName);
          form.append('registry', registry);
          form.append('uploadMethod', 'direct');
          form.append('batch_index', '0');
          form.append('batch_total', '2');
          again.forEach(file => { form.append('files[]', file); form.append('paths[]', computeRelativeUploadPath(file, folderName)); });
          try {
            const retry = await sendBatch(form, () => {});
            if (retry && retry.ok) {
              const d = retry.data || {};
              totals.saved += d.saved || 0;
              totals.skipped += d.skipped || 0;
              totals.registered += d.registered || 0;
              totals.renamed.push(...(d.renamed || []));
              totals.failed = (d.failed || []).slice();
            }
          } catch (e) { /* keep the original failed list */ }
        }
      }

      const summary = `${totals.saved} new file(s) saved${totals.skipped ? `, ${totals.skipped} already on the server (skipped)` : ''}${totals.renamed.length ? `, ${totals.renamed.length} renamed to avoid overwriting` : ''}${totals.failed.length ? `, <span class="text-red-600">${totals.failed.length} could not be saved</span>` : ''}.`;

      if (cancelRequested && completedBatches < batches.length) {
        closeProgress();
        statusEl.innerHTML = `<span class="text-yellow-700">Stopped after batch ${completedBatches} of ${batches.length}.</span> ${summary} Click Migrate to send the rest.`;
      } else {
        setProgress(100);
        setProgressDetail(`${files.length} file(s) in ${formatDuration((Date.now() - started) / 1000)}`);
        doneProgress();
        statusEl.innerHTML = `<span class="text-green-700"><i class="fa-solid fa-circle-check mr-1"></i>Upload complete.</span> ${summary} Saved to <code>${escapeHtml(serverPath)}</code>.`
          + (totals.failed.length
            ? `<div class="text-red-600 mt-1">Not saved (send these again): ${totals.failed.map(escapeHtml).join(', ')}. Select the folder again and click Migrate: files already on the server are skipped.</div>`
            : '');
        folderEl.value = '';
        selectedFiles = [];
        skippedLocal = [];
        totalSelectedBytes = 0;
        fileNoEl.value = '';
        document.getElementById('clearFileNoBtn')?.classList.add('hidden');
        ready = false;
      }
      if (totals.failed.length) {
        console.warn('Files the server could not save:', totals.failed);
      }

      await Promise.all([
        fetchServerList(currentSrvPath).catch(() => {}),
        fetchLogs().catch(() => {}),
      ]);
      if (typeof loadAggregates === 'function') loadAggregates();
    } catch (error) {
      closeProgress();
      statusEl.innerHTML = `<span class="text-red-600"><i class="fa-solid fa-circle-exclamation mr-1"></i>${escapeHtml(error.message).replace(/\n/g, '<br>')}</span>`;
      alert(error.message);
    } finally {
      uploading = false;
      migrateBtn.disabled = !ready;
    }
  }

  migrateBtn.addEventListener('click', migrateNow);

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

    updateSrvStatus('Registering folders copied onto the server in the last 2 days. This can take a few minutes...', 'info');

    try {
      const response = await fetch(API_SYNC_MIGRATIONS, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': CSRF_TOKEN
        },
        body: JSON.stringify({ days: 2 })
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok || !data.ok) {
        const errorMessage = (data && (data.error || data.message)) || `HTTP ${response.status}`;
        throw new Error(errorMessage);
      }

      // The command prints a summary table; show its "Rows inserted" line.
      const inserted = ((data.output || '').match(/Rows inserted\s*\|\s*(\d+)/) || [])[1];
      updateSrvStatus(`${data.message || 'Done.'}${inserted !== undefined ? ` ${inserted} new page(s) recorded.` : ''}`, 'success');

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

  // Admins see everyone's uploads unless they tick "Show only my uploads".
  let mineOnlyScope = false;
  let srvSearchTerm = '';

  async function fetchServerList(subPath = '', search = null) {
    const url = new URL(API_LIST, window.location.href);
    if (subPath) url.searchParams.set('path', subPath);
    if (search !== null) srvSearchTerm = search;
    if (subPath !== currentSrvPath && search === null) srvSearchTerm = '';
    if (srvSearchTerm) url.searchParams.set('q', srvSearchTerm);
    if (mineOnlyScope) url.searchParams.set('mine', '1');
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) { updateSrvStatus(data.error || ('HTTP ' + res.status), 'error'); return; }
    updateSrvStatus('');

    currentSrvPath = data.sub || '';
    previewState.currentDirItems = data.items || [];

    // Folder search is offered inside a registry folder, which is too big to list.
    const searchForm = document.getElementById('srvSearchForm');
    const searchInput = document.getElementById('srvSearch');
    if (searchForm) {
      searchForm.classList.toggle('hidden', !data.searchable);
      searchForm.classList.toggle('flex', !!data.searchable);
      if (searchInput && document.activeElement !== searchInput) searchInput.value = srvSearchTerm;
    }
    const noteEl = document.getElementById('srvNote');
    if (noteEl) {
      noteEl.textContent = data.note || '';
      noteEl.classList.toggle('hidden', !data.note);
    }
    const scopeWrap = document.getElementById('scopeToggleWrap');
    if (scopeWrap) {
      scopeWrap.classList.toggle('hidden', !data.isAdmin);
      scopeWrap.classList.toggle('flex', !!data.isAdmin);
    }
    if (syncMigrationBtn) {
      syncMigrationBtn.classList.toggle('hidden', !data.isAdmin);
    }

    // Update user mode indicator
    const indicator = document.getElementById('userModeIndicator');
    if (indicator) {
      if (data.isAdmin && !data.mineOnly) {
        indicator.innerHTML = `
          <div class="flex items-center gap-2 px-3 py-1 bg-amber-50 border border-amber-200 rounded-md">
            <i class="fa-solid fa-crown text-amber-600"></i>
            <span class="text-xs font-medium text-amber-700">Admin View (All Users)</span>
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
        <td class="p-3">-</td>
        <td class="p-3"></td>
      `);
      trUp.querySelector('.srv-nav').addEventListener('click', () => fetchServerList(up));
      tbody.appendChild(trUp);
    }

    // Admins delete whole file folders, one level inside a registry (Survey_Registry_Raw/CO-1-2022), 2026-10-30.
    const canDeleteFolder = !!data.isAdmin && /^[^/]+_Raw$/i.test(currentSrvPath || '');
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
            <td class="p-3">${item.files ? item.files + ' file(s)' : '-'}</td>
            <td class="p-3">${fmtDate(item.mtime)}</td>
            <td class="p-3">${escapeHtml(item.uploaded_by || '-')}</td>
            <td class="p-3">${canDeleteFolder ? `<button class="btn srv-del-folder text-xs text-red-600 border-red-200 hover:bg-red-50" title="Delete this folder (admin)"><i class="fa-regular fa-trash-can mr-1"></i>Delete</button>` : ''}</td>
          `);
          tr.querySelector('.srv-nav').addEventListener('click', () => fetchServerList((currentSrvPath ? currentSrvPath + '/' : '') + item.name));
          tr.querySelector('.srv-del-folder')?.addEventListener('click', () => deleteServerFolder(currentSrvPath + '/' + item.name, item.name, item.files));
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
            <td class="p-3">${escapeHtml(item.uploaded_by || '-')}</td>
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
        { orderable: false, targets: [1, 6] } // icon and actions columns
      ],
      language: {
        emptyTable: "No files found in this directory.",
        search: "_INPUT_",
        searchPlaceholder: "Search files..."
      }
    });

    clearPreview();
  }

  async function deleteServerFolder(folderPath, name, files) {
    const msg = `Delete the folder "${name}"${files ? ` (${files} file(s))` : ''} from Blind Scanning?\n\nIt is moved to the deleted-files area and its records are removed, so it can be uploaded again.`;
    if (!confirm(msg)) return;
    try {
      const res = await fetch(API_DELETE_FOLDER, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: JSON.stringify({ folder: folderPath }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) { alert(data.error || 'The folder could not be deleted.'); return; }
      alert(`${data.folder} deleted (${data.files} file(s)).`);
      await Promise.all([fetchServerList(currentSrvPath).catch(() => {}), fetchLogs().catch(() => {})]);
      if (typeof loadAggregates === 'function') loadAggregates();
    } catch (e) {
      alert('The folder could not be deleted: ' + e.message);
    }
  }

  // Upload history: one row per file folder, newest first, with how far its
  // pages have got (moved into Scan Upload = status converted).
  async function fetchLogs() {
    const url = new URL(API_LOGS, window.location.href);
    if (mineOnlyScope) url.searchParams.set('mine', '1');
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const data = await res.json().catch(() => ({}));
    const root = document.getElementById('logsContent');
    if (!res.ok || !data.ok) {
      root.innerHTML = `<div class="text-sm text-red-600 p-3">${escapeHtml(data.error || ('HTTP ' + res.status))}</div>`;
      return;
    }
    const logs = data.logs || [];
    if (!logs.length) {
      root.innerHTML = `
        <div class="text-center py-8">
          <i class="fa-solid fa-clock-rotate-left text-3xl text-muted-foreground mx-auto mb-4"></i>
          <p class="text-muted-foreground">No uploads in the last 30 days</p>
          <p class="text-sm text-muted-foreground">Folders you migrate will appear here with their progress.</p>
        </div>`;
      return;
    }
    const badge = m => {
      if (m.converted >= m.files) return 'bg-green-50 text-green-700 border-green-200';
      if (m.converted > 0) return 'bg-blue-50 text-blue-700 border-blue-200';
      return 'bg-amber-50 text-amber-700 border-amber-200';
    };
    root.innerHTML = `
      <div class="text-xs text-muted-foreground mb-2">${data.mineOnly ? 'Your' : 'All users\''} uploads in the last 30 days, newest first.</div>
      <div class="border rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-muted">
            <tr class="border-b">
              <th class="text-left p-3">S/N</th>
              <th class="text-left p-3">Last upload</th>
              <th class="text-left p-3">File Number</th>
              <th class="text-left p-3">Registry</th>
              <th class="text-left p-3">Pages</th>
              ${data.mineOnly ? '' : '<th class="text-left p-3">Uploaded by</th>'}
              <th class="text-left p-3">Progress</th>
            </tr>
          </thead>
          <tbody>
            ${logs.map((m, i) => `
              <tr class="border-b">
                <td class="p-3">${i + 1}</td>
                <td class="p-3 whitespace-nowrap">${escapeHtml(m.when ? new Date(String(m.when).replace(' ', 'T')).toLocaleString() : '-')}</td>
                <td class="p-3 font-mono"><a class="text-blue-700 hover:underline" href="#" data-open-folder="${escapeHtml(m.folder)}">${escapeHtml(m.file_number)}</a></td>
                <td class="p-3">${escapeHtml(m.registry)}</td>
                <td class="p-3 whitespace-nowrap">${m.files} <span class="text-xs text-muted-foreground">(A4 ${m.a4}, A3 ${m.a3})</span></td>
                ${data.mineOnly ? '' : `<td class="p-3">${escapeHtml(m.user)}</td>`}
                <td class="p-3"><span class="inline-block text-xs px-2 py-0.5 rounded border ${badge(m)}">${escapeHtml(m.progress)}</span></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>`;
    root.querySelectorAll('[data-open-folder]').forEach(link => {
      link.addEventListener('click', event => {
        event.preventDefault();
        serverPanel.classList.remove('hidden');
        logsPanel.classList.add('hidden');
        fetchServerList(link.dataset.openFolder);
      });
    });
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

  // No automatic sync on page load: uploads record their own rows, and the
  // old auto-sync walked every folder on the server from each open page,
  // timing out after 10 minutes. Copied folders are registered nightly.

  document.getElementById('srvSearchForm')?.addEventListener('submit', event => {
    event.preventDefault();
    fetchServerList(currentSrvPath, norm(document.getElementById('srvSearch').value));
  });
  document.getElementById('scopeToggle')?.addEventListener('change', event => {
    mineOnlyScope = event.target.checked;
    fetchServerList(currentSrvPath);
    fetchLogs();
    if (typeof loadAggregates === 'function') loadAggregates();
  });

  // Boot
  (async function () {
    await fetchServerList('');
    await fetchLogs();
  })();
</script>