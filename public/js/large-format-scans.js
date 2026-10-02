/* Shared LF source browser. Selection never writes: only the confirmation does. */
window.LargeFormatScans = (() => {
    async function json(url, options = {}) {
        const response = await fetch(url, { ...options, headers: {
            Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            ...options.headers
        }});
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'LF request failed.');
        return result;
    }
    function save(payload) {
        return json(window.largeFormatConfig.save, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    }
    async function file(entry) {
        const response = await fetch(entry.url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('The LF image could not be loaded.');
        const blob = await response.blob();
        return new File([blob], entry.name, { type: blob.type });
    }
    /* existingMissing keeps the "Current page" card in place for a page whose
       image is gone from storage, so the replacement still reads as a
       before/after comparison instead of a bare picker. */
    /* library picks which configured drop folder to browse; its name comes back
       from the server so the sidebar always names the folder actually in use. */
    /* fileIndexingId is for a library kept one folder per file number, such as
       the raw-scan share: the server uses it to open the picker inside that
       file's own folder, because the share root is far too large to list. */
    function open({ existingUrl = '', existingMissing = false, title = 'Browse Large-Format Scans', confirmLabel = 'Add LF Scan', replacementLabel = 'Large-format scan', library = '', sourceLabel = 'Master LFS Folder', fileIndexingId = null, onConfirm }) {
        const paths = {
            'scan-line': '<path d="M4 7V4h3m10 0h3v3M4 17v3h3m10 0h3v-3M4 12h16"/>',
            x: '<path d="m6 6 12 12M6 18 18 6"/>',
            'folder-open': '<path d="M3 8V5h6l2 3h10l-3 12H3V8Zm0 4h17"/>',
            folder: '<path d="M3 20V5h6l2 3h10v12Z"/>',
            'arrow-up': '<path d="M12 20V4m-6 6 6-6 6 6"/>',
            search: '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
            'zoom-in': '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6M7 10h6m-3-3v6"/>',
            'zoom-out': '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6M7 10h6"/>',
            maximize: '<path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/>',
            image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8" cy="8" r="1"/><path d="m3 17 6-6 4 4 3-3 5 5"/>',
            'file-image': '<path d="M14 2H4v20h16V8Zm0 0v6h6M6 18l4-4 3 3 2-2 3 3"/>',
            'mouse-pointer-2': '<path d="m4 3 7 18 3-7 7-3Z"/>',
            check: '<path d="m5 12 4 4L19 6"/>',
            'chevron-right': '<path d="m9 5 7 7-7 7"/>'
        };
        const icon = name => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || paths.image}</svg>`;
        const dialog = document.createElement('dialog');
        dialog.className = 'lfs-dialog';
        dialog.setAttribute('aria-label', title);
        dialog.innerHTML = `
            <header class="lfs-header">
                <div class="lfs-heading-icon">${icon('scan-line')}</div>
                <div class="lfs-heading"><h2></h2><p>Choose an image and check the details before confirming.</p></div>
                <button type="button" class="lfs-icon-button" data-close aria-label="Close browser">${icon('x')}</button>
            </header>
            <div class="lfs-layout">
                <aside class="lfs-sidebar">
                    <div class="lfs-source-label">${icon('folder-open')} <span data-source-label></span></div>
                    <p data-folder class="lfs-folder">Loading folder...</p>
                    <div class="lfs-navigation"><button type="button" data-up class="lfs-button" disabled>${icon('arrow-up')} Up one folder</button></div>
                    <label class="lfs-search">${icon('search')}<input aria-label="Filter LF images" placeholder="Search this folder"></label>
                    <form data-locate class="lfs-locate" hidden>
                        <label for="lfs-locate-input">Open a folder by name</label>
                        <div class="lfs-locate-row">
                            <input id="lfs-locate-input" data-locate-input placeholder="e.g. RES-1981-17" autocomplete="off">
                            <button type="submit" class="lfs-button">Open</button>
                        </div>
                    </form>
                    <p data-count class="lfs-count" aria-live="polite"></p>
                    <div data-entries class="lfs-entries" aria-label="Folder contents"></div>
                    <p class="lfs-source-note">JPG, PNG, WebP, GIF or BMP<br>The original stays in this folder.</p>
                </aside>
                <section class="lfs-workspace" aria-label="Image preview">
                    <div class="lfs-toolbar">
                        <div class="lfs-file-info"><strong data-filename>No image selected</strong><span data-dimensions>Select a file from the list to preview it.</span></div>
                        <div class="lfs-zoom-controls">
                            <button type="button" class="lfs-icon-button" data-minus aria-label="Zoom out" disabled>${icon('zoom-out')}</button>
                            <span data-zoom class="lfs-zoom-value">Fit</span>
                            <button type="button" class="lfs-icon-button" data-plus aria-label="Zoom in" disabled>${icon('zoom-in')}</button>
                            <button type="button" class="lfs-button" data-fit disabled>${icon('maximize')} Fit</button>
                        </div>
                    </div>
                    <div class="lfs-previews">
                        <figure data-old class="lfs-pane"><figcaption>Current page <span>Before replacement</span></figcaption><div class="lfs-canvas"><img alt="Current page"><p class="lfs-placeholder" data-old-error hidden>Current image preview is unavailable.</p></div></figure>
                        <figure class="lfs-pane"><figcaption><span data-incoming-label></span> <span data-selected-badge>Preview</span></figcaption><div class="lfs-canvas" data-canvas><img data-new alt="Selected large-format scan" hidden><div data-placeholder class="lfs-placeholder">${icon('image')}<strong>Select a scan to preview</strong><span>Your image will appear here at full available width.</span></div></div></figure>
                    </div>
                    <p class="lfs-preview-hint">${icon('mouse-pointer-2')} Zoom in to inspect fine details. Scroll the image to explore it.</p>
                </section>
            </div>
            <footer class="lfs-footer"><div class="lfs-footer-message"><p data-error role="alert"></p><span data-help></span></div><div class="lfs-footer-actions"><button type="button" class="lfs-button" data-cancel>Cancel</button><button type="button" class="lfs-button lfs-confirm" data-confirm disabled>${icon('check')}<span></span></button></div></footer>`;
        const find = selector => dialog.querySelector(selector);
        find('h2').textContent = title;
        find('[data-help]').textContent = existingMissing
            ? 'This page has no image in storage. Preview the scan, then confirm to restore it.'
            : existingUrl ? 'Compare both images, then confirm the replacement.' : 'Nothing is added until you confirm your selection.';
        find('[data-confirm] span').textContent = confirmLabel;
        // The incoming pane is only a "large-format scan" where the caller says
        // so; the File Archive restores ordinary A4 pages from the same folder.
        find('[data-incoming-label]').textContent = replacementLabel;
        find('[data-source-label]').textContent = sourceLabel;
        const old = find('[data-old]');
        const oldImage = old.querySelector('img');
        if (!existingUrl && !existingMissing) old.remove();
        else find('.lfs-previews').classList.add('lfs-comparison');
        const error = find('[data-error]');
        const confirm = find('[data-confirm]');
        const preview = find('[data-new]');
        const placeholder = find('[data-placeholder]');
        let currentPath = '', entries = [], selected = null, busy = false, generation = 0, imageGeneration = 0;
        // lockedRoot is the folder "up" stops at, so a per-file library can
        // never be walked back to a root that is not listable. emptyMessage is
        // the server's own account of why a folder came back with nothing.
        let lockedRoot = '', emptyMessage = '';
        let zoom = 1, ready = false;
        const zoomButtons = ['[data-minus]', '[data-plus]', '[data-fit]'].map(find);
        function resizeImages() {
            dialog.querySelectorAll('.lfs-canvas img').forEach(img => {
                if (!img.naturalWidth || img.hidden) return;
                const canvas = img.parentElement;
                const fit = Math.min((canvas.clientWidth - 40) / img.naturalWidth, (canvas.clientHeight - 40) / img.naturalHeight, 1);
                img.style.width = `${Math.max(1, Math.round(img.naturalWidth * fit * zoom))}px`;
                img.style.height = 'auto';
            });
            find('[data-zoom]').textContent = zoom === 1 ? 'Fit' : `${Math.round(zoom * 100)}%`;
            zoomButtons[0].disabled = !ready || zoom <= 0.5;
            zoomButtons[1].disabled = !ready || zoom >= 5;
            zoomButtons[2].disabled = !ready;
        }
        const observer = new ResizeObserver(resizeImages);
        const close = () => {
            if (busy) return;
            generation++; imageGeneration++; observer.disconnect(); dialog.close(); dialog.remove();
        };
        find('[data-cancel]').onclick = close;
        find('[data-close]').onclick = close;
        dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
        function resetPreview(message = 'Select a scan to preview') {
            ready = false; selected = null; zoom = 1; imageGeneration++;
            confirm.disabled = true; preview.hidden = true; preview.removeAttribute('src');
            placeholder.hidden = false; placeholder.querySelector('strong').textContent = message;
            find('[data-filename]').textContent = 'No image selected';
            find('[data-dimensions]').textContent = 'Select a file from the list to preview it.';
            find('[data-selected-badge]').textContent = 'Preview';
            resizeImages();
        }
        function select(entry) {
            resetPreview('Loading image...');
            selected = entry;
            const token = ++imageGeneration;
            find('[data-filename]').textContent = entry.name;
            find('[data-filename]').title = entry.name;
            find('[data-dimensions]').textContent = 'Loading preview...';
            error.textContent = '';
            render();
            const loader = new Image();
            loader.onload = () => {
                if (token !== imageGeneration) return;
                preview.onload = resizeImages;
                preview.src = entry.url; preview.alt = entry.name; preview.hidden = false;
                placeholder.hidden = true; ready = true; confirm.disabled = busy;
                find('[data-dimensions]').textContent = `${loader.naturalWidth.toLocaleString()} x ${loader.naturalHeight.toLocaleString()} pixels`;
                find('[data-selected-badge]').textContent = 'Selected';
                find('[data-canvas]').scrollTo(0, 0);
                resizeImages();
            };
            loader.onerror = () => {
                if (token !== imageGeneration) return;
                error.textContent = 'This image could not be previewed. Select another scan or try again.';
                placeholder.querySelector('strong').textContent = 'Preview unavailable';
                find('[data-dimensions]').textContent = 'Unable to load image';
            };
            loader.src = entry.url;
        }
        function render() {
            const list = find('[data-entries]'); list.replaceChildren();
            const search = find('input').value.toLowerCase();
            const visible = entries.filter(entry => entry.name.toLowerCase().includes(search));
            find('[data-count]').textContent = `${visible.length} ${visible.length === 1 ? 'item' : 'items'}`;
            visible.forEach(entry => {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'lfs-entry';
                button.classList.toggle('is-selected', selected?.path === entry.path);
                if (!entry.directory) button.setAttribute('aria-pressed', String(selected?.path === entry.path));
                button.innerHTML = `${icon(entry.directory ? 'folder' : 'file-image')}<span></span>${icon(entry.directory ? 'chevron-right' : 'check')}`;
                button.querySelector('span').textContent = entry.name;
                button.title = entry.name;
                button.onclick = () => { if (!busy) entry.directory ? browse(entry.path) : select(entry); };
                list.append(button);
            });
            if (!visible.length) {
                const empty = document.createElement('p'); empty.className = 'lfs-empty';
                empty.textContent = search ? 'No files match your search.'
                    : (emptyMessage || 'No supported images in this folder.');
                list.append(empty);
            }
        }
        /* path null asks the server to choose the landing folder, which is how
           a per-file library opens: only it knows where this file's scans are. */
        async function browse(path) {
            const request = ++generation;
            resetPreview(); entries = []; emptyMessage = ''; render();
            find('[data-count]').textContent = 'Loading...';
            try {
                const url = new URL(window.largeFormatConfig.browse, location.href);
                if (path !== null && path !== undefined) url.searchParams.set('path', path);
                if (library) url.searchParams.set('library', library);
                if (fileIndexingId) url.searchParams.set('file_indexing_id', fileIndexingId);
                const result = await json(url);
                if (request !== generation) return;
                currentPath = result.path || ''; entries = result.entries || [];
                lockedRoot = result.locked_root || '';
                emptyMessage = result.message || '';
                if (result.label) find('[data-source-label]').textContent = result.label;
                find('[data-folder]').textContent = `${result.folder}${currentPath ? ' / ' + currentPath : ''}`;
                find('[data-up]').disabled = !currentPath || currentPath === lockedRoot;
                // Naming a folder by hand is the way out of an unmatched file
                // number, and the only way to reach a different file's folder
                // on a share whose root cannot be browsed, so it stays offered.
                find('[data-locate]').hidden = !result.can_locate;
                find('input').value = ''; error.textContent = ''; render();
                if (result.matched === false) find('[data-count]').textContent = 'Nothing to show';
            } catch (e) { if (request === generation) { error.textContent = e.message; find('[data-count]').textContent = 'Folder unavailable'; } }
        }
        find('[data-up]').onclick = () => {
            if (busy || !currentPath || currentPath === lockedRoot) return;
            const parent = currentPath.split('/').slice(0, -1).join('/');
            // Never climb past the locked root, even if the parent is above it.
            browse(parent.startsWith(lockedRoot) ? parent : lockedRoot);
        };
        find('[data-locate]').onsubmit = event => {
            event.preventDefault();
            const name = find('[data-locate-input]').value.trim();
            if (name && !busy) browse(name);
        };
        find('input').oninput = render;
        find('[data-plus]').onclick = () => { zoom = Math.min(5, zoom + 0.5); resizeImages(); };
        find('[data-minus]').onclick = () => { zoom = Math.max(0.5, zoom - 0.5); resizeImages(); };
        find('[data-fit]').onclick = () => { zoom = 1; resizeImages(); dialog.querySelectorAll('.lfs-canvas').forEach(canvas => canvas.scrollTo(0, 0)); };
        confirm.onclick = async () => {
            if (!selected || !ready || busy) return;
            busy = true; confirm.disabled = true; error.textContent = '';
            find('[data-confirm] span').textContent = 'Saving...';
            find('[data-cancel]').disabled = true; find('[data-close]').disabled = true;
            try { await onConfirm(selected); busy = false; close(); }
            catch (e) {
                error.textContent = e.message; busy = false; confirm.disabled = false;
                find('[data-confirm] span').textContent = confirmLabel;
                find('[data-cancel]').disabled = false; find('[data-close]').disabled = false;
            }
        };
        document.body.append(dialog); dialog.showModal();
        if (existingUrl) {
            oldImage.onload = resizeImages;
            oldImage.onerror = () => { oldImage.hidden = true; find('[data-old-error]').hidden = false; };
            oldImage.src = existingUrl;
        } else if (existingMissing) {
            old.querySelector('figcaption span').textContent = 'Image missing';
            oldImage.hidden = true;
            find('[data-old-error]').textContent = 'This page has no image in storage.';
            find('[data-old-error]').hidden = false;
        }
        observer.observe(find('.lfs-previews')); browse(null);
    }
    return { open, save, file };
})();
