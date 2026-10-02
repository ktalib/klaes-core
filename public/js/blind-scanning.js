/**
 * Blind Scanning JavaScript Module
 * Handles file upload, migration, server browsing, and records management
 */

class BlindScanningManager {
    constructor(config) {
        this.config = config;
        this.selectedFiles = [];
        this.ready = false;
        this.localParentName = null;
        this.currentSrvPath = '';
        this.currentPage = 1;
        this.currentFilters = {};
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeFileNumberModal();
        this.boot();
    }

    // Progress modal helpers
    openProgress(title, sub, pct = 0) {
        document.getElementById('progressTitle').textContent = title;
        document.getElementById('progressSub').textContent = sub || '';
        document.querySelector('#progressModal .progress-bar').style.width = pct + '%';
        document.getElementById('progressText').textContent = pct + '%';
        document.getElementById('progressDone').classList.add('hidden');
        const m = document.getElementById('progressModal');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    setProgress(pct) {
        document.querySelector('#progressModal .progress-bar').style.width = pct + '%';
        document.getElementById('progressText').textContent = pct + '%';
    }

    doneProgress() {
        document.getElementById('progressDone').classList.remove('hidden');
        setTimeout(() => {
            const m = document.getElementById('progressModal');
            m.classList.add('hidden');
            m.classList.remove('flex');
        }, 600);
    }

    // Utility functions
    norm(s) {
        return (s || '').trim();
    }

    // Try to infer a root name from the picked files (may return null)
    detectSelectedRoot(files) {
        const segs = [];
        for (const f of files) {
            const rel = f.webkitRelativePath || f.name;
            const first = (rel.split('/')[0] || '').trim();
            if (first) segs.push(first.toLowerCase());
        }
        if (!segs.length) return null;
        const unique = Array.from(new Set(segs));
        // If the first segment is consistent AND not "a3"/"a4", assume it's the parent
        if (unique.length === 1 && unique[0] !== 'a3' && unique[0] !== 'a4') {
            return unique[0];
        }
        // Common case: browser only exposes A3/A4 as first segment → cannot infer
        return null;
    }

    validateUploadSection() {
        const fileNoEl = document.getElementById('fileNo');
        const statusEl = document.getElementById('status');
        const migrateBtn = document.getElementById('migrateBtn');
        
        const typedRaw = this.norm(fileNoEl.value);
        const typed = typedRaw.toLowerCase();
        const hasFiles = this.selectedFiles.length > 0;

        if (!hasFiles) {
            statusEl.textContent = 'No folder selected.';
            migrateBtn.disabled = true;
            this.ready = false;
            return;
        }

        const inferred = this.detectSelectedRoot(this.selectedFiles); // may be null
        this.localParentName = inferred;

        if (!typed) {
            statusEl.textContent = inferred
                ? `Detected local folder "${inferred}". Enter the File Number to proceed.`
                : 'Folder selected. Enter the File Number to proceed.';
            migrateBtn.disabled = true;
            this.ready = false;
            return;
        }

        if (inferred) {
            const match = (typed === inferred);
            if (!match) {
                statusEl.innerHTML = `Folder appears to be <code>${inferred}</code> but you typed <code>${typedRaw}</code>.`;
                migrateBtn.disabled = true;
                this.ready = false;
                return;
            }
            statusEl.innerHTML = `Ready: <b>${typedRaw}</b> will be migrated with all subfolders and files (${this.selectedFiles.length} items).`;
            migrateBtn.disabled = false;
            this.ready = true;
            return;
        } else {
            statusEl.innerHTML = `Ready: <b>${typedRaw}</b> (parent name not detectable by browser). `
                + `All selected items (${this.selectedFiles.length}) will be zipped and migrated.`;
            migrateBtn.disabled = false;
            this.ready = true;
            return;
        }
    }

    async migrateNow() {
        if (!this.ready) return;

        const fileNoEl = document.getElementById('fileNo');
        const statusEl = document.getElementById('status');
        const folderEl = document.getElementById('folderInput');
        const migrateBtn = document.getElementById('migrateBtn');
        
        const folderName = this.norm(fileNoEl.value);
        this.openProgress('Preparing Upload', `${folderName} → storage\\app\\public\\EDMS\\BLIND_SCAN`, 5);

        try {
            const form = new FormData();
            form.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            form.append('folderName', folderName);
            form.append('uploadMethod', 'direct');

            // Add all files with their relative paths
            let fileCount = 0;
            for (const file of this.selectedFiles) {
                const relativePath = file.webkitRelativePath || file.name;
                form.append(`files[${fileCount}]`, file);
                form.append(`paths[${fileCount}]`, relativePath);
                fileCount++;
            }

            this.setProgress(20);

            const res = await fetch(this.config.MIGRATE_ENDPOINT, {
                method: 'POST',
                body: form
            });

            this.setProgress(95);

            const data = await res.json().catch(() => ({}));
            if (res.ok && data.ok) {
                this.setProgress(100);
                this.doneProgress();
                // refresh server view & logs & records
                await Promise.all([
                    this.fetchServerList(this.currentSrvPath),
                    this.fetchLogs(),
                    this.loadRecords(this.currentPage) // Refresh records table
                ]);
                statusEl.innerHTML = `<span class="text-green-700">Migration complete.</span> Saved to <code>${data.serverPath || '/storage/' + folderName}</code>.`;
                // Clear selection
                folderEl.value = '';
                this.selectedFiles = [];
                this.ready = false;
                migrateBtn.disabled = true;
            } else {
                document.getElementById('progressModal').classList.add('hidden');
                const msg = (data && (data.error || data.message)) || `HTTP ${res.status}`;
                alert('Migration failed: ' + msg);
            }
        } catch (error) {
            document.getElementById('progressModal').classList.add('hidden');
            alert('Migration failed: ' + error.message);
        }
    }

    // Server browser helper functions
    srvRow(html) {
        const tr = document.createElement('tr');
        tr.className = 'border-b table-row hover:bg-gray-50 transition-colors duration-150';
        tr.innerHTML = html;
        return tr;
    }

    fmtBytes(n) {
        if (!n && n !== 0) return '-';
        if (n < 1024) return n + ' B';
        if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
        return (n / (1024 * 1024)).toFixed(1) + ' MB';
    }

    fmtDate(ts) {
        if (!ts) return '-';
        const d = new Date(ts * 1000);
        return d.toLocaleString();
    }

    clearPreview() {
        document.getElementById('previewBox').innerHTML =
            `<div class="flex flex-col items-center justify-center h-full space-y-4 p-8">
          <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
            <i class="fa-solid fa-file-image text-2xl text-blue-600"></i>
          </div>
          <div class="text-center space-y-2">
            <p class="text-gray-700 font-medium">No file selected</p>
            <p class="text-sm text-gray-500">Click "Preview" next to any file to view it here</p>
          </div>
          <div class="flex items-center space-x-4 text-xs text-gray-400">
            <div class="flex items-center space-x-1">
              <i class="fa-solid fa-file-pdf"></i>
              <span>PDF</span>
            </div>
            <div class="flex items-center space-x-1">
              <i class="fa-solid fa-file-image"></i>
              <span>Images</span>
            </div>
          </div>
        </div>`;
    }

    previewFile(href, name) {
        const box = document.getElementById('previewBox');
        const ext = (name.split('.').pop() || '').toLowerCase();

        // Show loading state
        box.innerHTML = `
        <div class="flex flex-col items-center justify-center h-full space-y-4 p-8">
          <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center animate-pulse">
            <i class="fa-solid fa-spinner fa-spin text-2xl text-blue-600"></i>
          </div>
          <div class="text-center space-y-2">
            <p class="text-gray-700 font-medium">Loading preview...</p>
            <p class="text-sm text-gray-500">${name}</p>
          </div>
        </div>`;

        setTimeout(() => {
            box.innerHTML = '';
            if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) {
                const imgContainer = document.createElement('div');
                imgContainer.className = 'flex items-center justify-center h-full p-4';

                const img = document.createElement('img');
                img.src = href;
                img.alt = name;
                img.className = 'max-w-full max-h-full rounded-lg border shadow-sm object-contain';
                img.style.maxHeight = '480px';

                img.onload = () => {
                    imgContainer.innerHTML = '';
                    imgContainer.appendChild(img);
                };

                img.onerror = () => {
                    imgContainer.innerHTML = `
              <div class="text-center space-y-2">
                <i class="fa-solid fa-exclamation-triangle text-3xl text-yellow-500"></i>
                <p class="text-gray-600">Failed to load image</p>
                <a href="${href}" target="_blank" class="text-blue-600 hover:underline text-sm">Open in new tab</a>
              </div>`;
                };

                box.appendChild(imgContainer);
            } else if (ext === 'pdf') {
                const iframe = document.createElement('iframe');
                iframe.src = href;
                iframe.className = 'w-full rounded-lg border';
                iframe.style.minHeight = '480px';
                iframe.style.height = '480px';
                box.appendChild(iframe);
            } else {
                box.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full space-y-4 p-8">
              <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-file text-2xl text-gray-400"></i>
              </div>
              <div class="text-center space-y-2">
                <p class="text-gray-700 font-medium">Preview not available</p>
                <p class="text-sm text-gray-500">File type: ${ext || 'unknown'}</p>
                <a class="inline-flex items-center space-x-2 text-blue-600 hover:text-blue-800 font-medium" href="${href}" target="_blank" rel="noopener">
                  <i class="fa-solid fa-external-link"></i>
                  <span>Open in new tab</span>
                </a>
              </div>
            </div>`;
            }
        }, 300);
    }

    async fetchServerList(subPath = '') {
        const url = new URL(this.config.API_LIST, window.location.href);
        if (subPath) url.searchParams.set('path', subPath);
        const res = await fetch(url);
        const data = await res.json();
        if (!res.ok || !data.ok) {
            alert(data.error || ('HTTP ' + res.status));
            return;
        }

        this.currentSrvPath = data.sub || '';
        document.getElementById('srvPath').textContent = '/storage/EDMS/BLIND_SCAN' + (this.currentSrvPath ? '/' + this.currentSrvPath : '');

        const crumbs = data.crumbs || [];
        const crumbsEl = document.getElementById('srvCrumbs');
        if (!crumbs.length) {
            crumbsEl.textContent = 'Root';
        } else {
            crumbsEl.innerHTML = crumbs.map((c, i) => (
                `<button class="text-blue-700 hover:underline" data-path="${c.path}">${c.name}</button>${i < crumbs.length - 1 ? ' / ' : ''}`
            )).join('');
            crumbsEl.querySelectorAll('button').forEach(b => {
                b.addEventListener('click', () => this.fetchServerList(b.dataset.path));
            });
        }

        const tbody = document.getElementById('srvRows');
        tbody.innerHTML = '';

        if (this.currentSrvPath) {
            const up = this.currentSrvPath.split('/').slice(0, -1).join('/');
            const trUp = this.srvRow(`
          <td class="px-4 py-3">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-50">
              <i class="fa-solid fa-arrow-left text-blue-600 text-sm"></i>
            </div>
          </td>
          <td class="px-4 py-3">
            <button class="srv-nav text-blue-700 hover:text-blue-800 font-medium flex items-center space-x-2 hover:underline transition-colors duration-200" data-path="${up}">
              <span>← Go Back</span>
            </button>
          </td>
          <td class="px-4 py-3 text-gray-500">-</td>
          <td class="px-4 py-3"></td>
        `);
            trUp.querySelector('.srv-nav').addEventListener('click', () => this.fetchServerList(up));
            tbody.appendChild(trUp);
        }

        const items = data.items || [];
        if (!items.length) {
            tbody.appendChild(this.srvRow(`<td class="px-4 py-3 text-gray-500 text-center" colspan="4">
          <div class="flex flex-col items-center py-4 space-y-2">
            <i class="fa-solid fa-folder-open text-2xl text-gray-300"></i>
            <span>Empty folder</span>
          </div>
        </td>`));
        } else {
            for (const item of items) {
                if (item.type === 'dir') {
                    const tr = this.srvRow(`
              <td class="px-4 py-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-50">
                  <i class="fa-solid fa-folder text-blue-600 text-sm"></i>
                </div>
              </td>
              <td class="px-4 py-3">
                <button class="srv-nav text-blue-700 hover:text-blue-800 font-medium hover:underline transition-colors duration-200" data-path="${(this.currentSrvPath ? this.currentSrvPath + '/' : '') + item.name}">
                  ${item.name}
                </button>
              </td>
              <td class="px-4 py-3 text-gray-600 text-sm">${this.fmtDate(item.mtime)}</td>
              <td class="px-4 py-3"></td>
            `);
                    tr.querySelector('.srv-nav').addEventListener('click', () => this.fetchServerList((this.currentSrvPath ? this.currentSrvPath + '/' : '') + item.name));
                    tbody.appendChild(tr);
                } else {
                    const href = item.href || '#';
                    const fileExt = (item.name.split('.').pop() || '').toLowerCase();
                    let fileIcon = 'fa-file';
                    let iconColor = 'text-gray-500';

                    // Set specific icons and colors based on file type
                    if (['pdf'].includes(fileExt)) {
                        fileIcon = 'fa-file-pdf';
                        iconColor = 'text-red-500';
                    } else if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(fileExt)) {
                        fileIcon = 'fa-file-image';
                        iconColor = 'text-green-500';
                    } else if (['doc', 'docx'].includes(fileExt)) {
                        fileIcon = 'fa-file-word';
                        iconColor = 'text-blue-500';
                    } else if (['xls', 'xlsx'].includes(fileExt)) {
                        fileIcon = 'fa-file-excel';
                        iconColor = 'text-green-600';
                    }

                    const tr = this.srvRow(`
              <td class="px-4 py-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-50">
                  <i class="fa-solid ${fileIcon} ${iconColor} text-sm"></i>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-col">
                  <span class="font-medium text-gray-900">${item.name}</span>
                  <span class="text-xs text-gray-500 uppercase">${fileExt || 'file'}</span>
                </div>
              </td>
              <td class="px-4 py-3 text-gray-600 text-sm">${this.fmtDate(item.mtime)}</td>
              <td class="px-4 py-3">
                <div class="flex gap-2">
                  <button class="srv-preview px-3 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-md text-xs font-medium transition-colors duration-200 flex items-center space-x-1" data-href="${href}" data-name="${item.name}">
                    <i class="fa-solid fa-eye"></i>
                    <span>Preview</span>
                  </button>
                  <a class="px-3 py-1.5 bg-gray-50 text-gray-600 hover:bg-gray-100 rounded-md text-xs font-medium no-underline transition-colors duration-200 flex items-center space-x-1" href="${href}" target="_blank" rel="noopener">
                    <i class="fa-solid fa-external-link"></i>
                    <span>Open</span>
                  </a>
                </div>
              </td>
            `);
                    tr.querySelector('.srv-preview').addEventListener('click', (e) => this.previewFile(e.currentTarget.dataset.href, e.currentTarget.dataset.name));
                    tbody.appendChild(tr);
                }
            }
        }
        this.clearPreview();
    }

    async fetchLogs() {
        const res = await fetch(this.config.API_LOGS);
        const data = await res.json();
        if (!res.ok || !data.ok) {
            alert(data.error || ('HTTP ' + res.status));
            return;
        }
        const logs = data.logs || [];
        const root = document.getElementById('logsContent');
        if (!logs.length) {
            root.innerHTML = `
          <div class="text-center py-8">
            <i class="fa-solid fa-clock-rotate-left text-3xl text-gray-400 mx-auto mb-4"></i>
            <p class="text-gray-500">No migrations yet</p>
            <p class="text-sm text-gray-400">After migrating, entries will appear here.</p>
          </div>`;
            return;
        }
        root.innerHTML = `
        <div class="border border-gray-200 rounded-lg overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50">
              <tr class="border-b border-gray-200">
                <th class="text-left p-3 font-medium text-gray-700">When</th>
                <th class="text-left p-3 font-medium text-gray-700">Parent Folder</th>
                <th class="text-left p-3 font-medium text-gray-700">Server Path</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              ${logs.map(m => `
                <tr class="hover:bg-gray-50">
                  <td class="p-3">${m.when || '-'}</td>
                  <td class="p-3 font-mono">${m.folder || '-'}</td>
                  <td class="p-3 font-mono"><a class="text-blue-700 hover:underline" href="${m.serverPath || '#'}" target="_blank" rel="noopener">${m.serverPath || '-'}</a></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>`;
    }

    // Records management
    loadRecords(page = 1) {
        const tbody = document.getElementById('recordsTableBody');
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading records...</td></tr>';

        const params = new URLSearchParams({
            page: page,
            per_page: 15,
            ...this.currentFilters
        });

        fetch(`/blind-scanning/list?${params.toString()}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    this.displayRecords(data.data);
                    this.updatePagination(data.data);
                    this.currentPage = page;
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-red-500">Error loading records: ' + (data.message || 'Unknown error') + '</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error loading records:', error);
                tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-red-500">Error loading records: ' + error.message + '</td></tr>';
            });
    }

    displayRecords(records) {
        const tbody = document.getElementById('recordsTableBody');

        if (records.data && records.data.length > 0) {
            tbody.innerHTML = records.data.map((record, index) => {
                const statusClass = this.getStatusClass(record.status);
                const uploadedBy = record.uploader ? record.uploader.name : 'Unknown';
                const uploadDate = new Date(record.created_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const serialNumber = ((records.current_page - 1) * records.per_page) + index + 1;
                const a4Count = record.a4_count || (record.paper_size === 'A4' ? 1 : 0);
                const a3Count = record.a3_count || (record.paper_size === 'A3' ? 1 : 0);
                const totalPages = record.total_pages || (a4Count + a3Count);

                return `
            <tr class="hover:bg-gray-50 transition-colors duration-150">
              <td class="px-4 py-3 text-gray-600">${serialNumber}</td>
              <td class="px-4 py-3">
                <div class="font-medium text-gray-900">
                  ${record.file_number || record.temp_file_id || 'N/A'}
                </div>
              </td>
              <td class="px-4 py-3 text-gray-600 text-sm">
                <span class="font-mono bg-gray-100 px-2 py-1 rounded text-xs">${record.local_pc_path || record.original_filename || 'N/A'}</span>
              </td>
              <td class="px-4 py-3 text-gray-600 text-sm">
                <span class="font-mono bg-gray-100 px-2 py-1 rounded text-xs">${record.document_path || 'N/A'}</span>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                  ${record.status.charAt(0).toUpperCase() + record.status.slice(1)}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-gray-600">
                <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded">
                  ${a4Count}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-gray-600">
                <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded">
                  ${a3Count}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-gray-600">
                <span class="inline-flex items-center px-2 py-1 bg-purple-100 text-purple-800 text-xs font-medium rounded">
                  ${totalPages}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600">${uploadedBy}</td>
              <td class="px-4 py-3 text-gray-600 text-sm">${uploadDate}</td>
              <td class="px-4 py-3">
                <div class="flex items-center space-x-2">
                  ${record.document_path ? `<button onclick="blindScanManager.viewDocument('${record.document_path}')" class="text-blue-600 hover:text-blue-800 text-sm" title="View Document">
                    <i class="fa-solid fa-eye"></i>
                  </button>` : ''}
                  <button onclick="blindScanManager.viewRecordDetails(${record.id})" class="text-gray-600 hover:text-gray-800 text-sm" title="View Details">
                    <i class="fa-solid fa-info-circle"></i>
                  </button>
                  <button onclick="blindScanManager.editRecord(${record.id})" class="text-green-600 hover:text-green-800 text-sm" title="Edit">
                    <i class="fa-solid fa-edit"></i>
                  </button>
                  <button onclick="blindScanManager.deleteRecord(${record.id})" class="text-red-600 hover:text-red-800 text-sm" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          `;
            }).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="11" class="px-4 py-8 text-center text-gray-500">No records found</td></tr>';
        }
    }

    getStatusClass(status) {
        switch (status) {
            case 'pending':
                return 'bg-yellow-100 text-yellow-800';
            case 'converted':
                return 'bg-green-100 text-green-800';
            case 'archived':
                return 'bg-gray-100 text-gray-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    }

    updatePagination(records) {
        const from = ((records.current_page - 1) * records.per_page) + 1;
        const to = Math.min(records.current_page * records.per_page, records.total);

        document.getElementById('recordsFrom').textContent = records.total > 0 ? from : 0;
        document.getElementById('recordsTo').textContent = to;
        document.getElementById('recordsTotal').textContent = records.total;

        const prevBtn = document.getElementById('prevPage');
        const nextBtn = document.getElementById('nextPage');

        prevBtn.disabled = records.current_page <= 1;
        nextBtn.disabled = records.current_page >= records.last_page;

        if (prevBtn.disabled) {
            prevBtn.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            prevBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }

        if (nextBtn.disabled) {
            nextBtn.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            nextBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    viewDocument(path) {
        // Open document in new tab
        window.open(`/storage/${path}`, '_blank');
    }

    viewRecordDetails(recordId) {
        // You can implement a modal to show record details
        alert(`View details for record ID: ${recordId}`);
    }

    editRecord(recordId) {
        // You can implement a modal to edit record details
        alert(`Edit record ID: ${recordId}`);
    }

    deleteRecord(recordId) {
        if (confirm('Are you sure you want to delete this record?')) {
            // Implement delete functionality
            fetch(`/blind-scanning/${recordId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Record deleted successfully');
                        this.loadRecords(this.currentPage); // Refresh records
                    } else {
                        alert('Error deleting record: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting record: ' + error.message);
                });
        }
    }

    // Tab management
    hideAllPanels() {
        document.getElementById('serverPanel').classList.add('hidden');
        document.getElementById('logsPanel').classList.add('hidden');
    }

    setActiveTab(activeTabId) {
        // Remove active class from all tabs
        ['tabServer', 'tabLogs'].forEach(id => {
            const tab = document.getElementById(id);
            tab.classList.remove('bg-blue-600', 'text-white');
            tab.classList.add('bg-white', 'text-gray-700');
        });

        // Add active class to selected tab
        const activeTab = document.getElementById(activeTabId);
        activeTab.classList.remove('bg-white', 'text-gray-700');
        activeTab.classList.add('bg-blue-600', 'text-white');
    }

    // File Number Modal Initialization
    initializeFileNumberModal() {
        console.log('Initializing file number modal...');
        console.log('Select button exists:', $('#selectFileNoBtn').length > 0);
        console.log('Modal exists:', $('#global-fileno-modal').length > 0);

        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
            console.log('Lucide icons initialized');
        } else {
            console.log('Lucide not available');
        }

        // File number button click handler
        $('#selectFileNoBtn, #changeFileNoBtn').off('click').on('click', (e) => {
            e.preventDefault();
            console.log('File number button clicked!');

            try {
                // Use the global object directly
                if (typeof GlobalFileNoModal !== 'undefined' && typeof GlobalFileNoModal.open === 'function') {
                    console.log('Opening modal via GlobalFileNoModal.open()...');

                    // Set the callback function that will be called when the Apply button is clicked
                    GlobalFileNoModal.config.callback = (data) => {
                        const fileNumber = data.fileNumber;
                        const tabName = data.tab;

                        console.log('File number selected:', fileNumber, 'from', tabName);

                        // Update the display and hidden input
                        $('#selectedFileNo').text(fileNumber);
                        $('#fileNo').val(fileNumber);

                        // Show the display area and hide the select button
                        $('#fileNoDisplay').show();
                        $('#selectFileNoBtn').hide();              // Trigger validation update for migrate button
                        if (typeof this.validateUploadSection === 'function') {
                            this.validateUploadSection();
                        }

                        // Show success feedback
                        const notification = $(`
                <div class="fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 flex items-center">
                  <i class="fa-solid fa-check-circle mr-2"></i>
                  File number selected: <strong>${fileNumber}</strong>
                </div>
              `);

                        $('body').append(notification);
                        setTimeout(() => {
                            notification.fadeOut(() => notification.remove());
                        }, 3000);
                    };

                    // Open the modal
                    GlobalFileNoModal.open({
                        initialTab: 'mls'
                    });
                } else {
                    console.error('GlobalFileNoModal global object not available');
                    alert('File Number Modal is not available. Please refresh the page.');
                }
            } catch (error) {
                console.error('Error opening modal:', error);
                alert('Error opening modal: ' + error.message);
            }
        });

        console.log('File number modal initialization complete');
    }

    bindEvents() {
        const folderEl = document.getElementById('folderInput');
        const fileNoEl = document.getElementById('fileNo');
        const migrateBtn = document.getElementById('migrateBtn');

        folderEl.addEventListener('change', () => {
            this.selectedFiles = Array.from(folderEl.files || []);
            this.validateUploadSection();
        });

        fileNoEl.addEventListener('input', () => this.validateUploadSection());
        migrateBtn.addEventListener('click', () => this.migrateNow());

        // Tab navigation
        document.getElementById('tabServer').addEventListener('click', () => {
            this.hideAllPanels();
            document.getElementById('serverPanel').classList.remove('hidden');
            this.setActiveTab('tabServer');
        });

        document.getElementById('tabLogs').addEventListener('click', () => {
            this.hideAllPanels();
            document.getElementById('logsPanel').classList.remove('hidden');
            this.setActiveTab('tabLogs');
        });

        document.getElementById('srvRefresh').addEventListener('click', () => this.fetchServerList(this.currentSrvPath));

        // Records event listeners
        document.getElementById('refreshRecords').addEventListener('click', () => this.loadRecords(this.currentPage));
        
        document.getElementById('applyFilters').addEventListener('click', () => {
            this.currentFilters = {
                status: document.getElementById('filterStatus').value,
                paper_size: document.getElementById('filterPaperSize').value,
                document_type: document.getElementById('filterDocType').value,
                search: document.getElementById('filterSearch').value,
            };
            // Remove empty filters
            Object.keys(this.currentFilters).forEach(key => {
                if (!this.currentFilters[key]) delete this.currentFilters[key];
            });
            this.loadRecords(1); // Reset to page 1 when applying filters
        });
        
        document.getElementById('clearFilters').addEventListener('click', () => {
            this.currentFilters = {};
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterPaperSize').value = '';
            document.getElementById('filterDocType').value = '';
            document.getElementById('filterSearch').value = '';
            this.loadRecords(1);
        });
        
        document.getElementById('prevPage').addEventListener('click', () => {
            if (this.currentPage > 1) this.loadRecords(this.currentPage - 1);
        });
        
        document.getElementById('nextPage').addEventListener('click', () => {
            this.loadRecords(this.currentPage + 1);
        });
    }

    async boot() {
        await this.fetchServerList('');
        await this.fetchLogs();

        // Set Server tab as active by default
        this.setActiveTab('tabServer');

        // Initialize file number modal after everything else is loaded
        this.initializeFileNumberModal();
    }
}

// Make the class available globally
window.BlindScanningManager = BlindScanningManager;