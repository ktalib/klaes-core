{{-- Shared action menu for Doc-WARE file cards (Change Request 22102026 items 27 and 28).
     Move to NR is offered on every registry's archive; Delete File only to Super Admins,
     and the delete routes are gated by the super.admin middleware as well. --}}
@php
    $canDeleteArchiveFiles = auth()->check() && method_exists(auth()->user(), 'isSuperAdmin') && auth()->user()->isSuperAdmin();
@endphp
<div id="doc-card-actions-menu" role="menu"
     class="hidden fixed z-[70] w-52 overflow-hidden rounded-md border border-gray-200 bg-white py-1 shadow-lg">
    <button type="button" role="menuitem" data-card-action="move"
            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100">
        <i data-lucide="folder-symlink" class="h-4 w-4 text-blue-600"></i>
        Move to NR
    </button>
    @if($canDeleteArchiveFiles)
        <div class="my-1 border-t border-gray-100"></div>
        <button type="button" role="menuitem" data-card-action="delete"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50">
            <i data-lucide="trash-2" class="h-4 w-4 text-red-600"></i>
            Delete File
        </button>
    @endif
</div>

<script>
(function () {
    'use strict';

    const menu = document.getElementById('doc-card-actions-menu');
    if (!menu) return;

    const canDelete = @json($canDeleteArchiveFiles);
    const moduleKey = @json(request('url', ''));
    const previewUrl = @json(route('filearchive.delete.preview', ['id' => '__ID__']));
    const deleteUrl = @json(route('filearchive.delete', ['id' => '__ID__']));
    let current = null;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    function closeMenu() {
        menu.classList.add('hidden');
        current = null;
    }

    function openMenu(button) {
        current = { id: button.dataset.id, fileNumber: button.dataset.fileNumber || '' };

        menu.style.visibility = 'hidden';
        menu.classList.remove('hidden');

        const anchor = button.getBoundingClientRect();
        const { width, height } = menu.getBoundingClientRect();
        const margin = 8;

        let left = Math.max(margin, Math.min(anchor.right - width, window.innerWidth - width - margin));
        let top = anchor.bottom + 4;
        if (top + height > window.innerHeight - margin) top = Math.max(margin, anchor.top - height - 4);

        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        menu.style.visibility = '';
        if (window.lucide) lucide.createIcons();
    }

    // Capture phase: the card itself opens the viewer on click, so the trigger
    // must be handled (and stopped) before the card's jQuery handler sees it.
    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.doc-card-actions-btn');
        if (trigger) {
            event.preventDefault();
            event.stopPropagation();
            if (current && current.id === trigger.dataset.id && !menu.classList.contains('hidden')) {
                closeMenu();
            } else {
                openMenu(trigger);
            }
            return;
        }

        if (!menu.contains(event.target)) closeMenu();
    }, true);

    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });
    window.addEventListener('resize', closeMenu);
    window.addEventListener('scroll', closeMenu, true);

    menu.addEventListener('click', function (event) {
        const item = event.target.closest('[data-card-action]');
        if (!item || !current) return;
        event.stopPropagation();

        const target = current;
        closeMenu();

        if (item.dataset.cardAction === 'move') {
            if (!window.EdmsRegistryTransfer) {
                Swal.fire({ icon: 'error', title: 'Unavailable', text: 'The registry transfer dialog did not load on this page.' });
                return;
            }
            window.EdmsRegistryTransfer.open(target.id, target.fileNumber, () => window.location.reload());
        } else if (item.dataset.cardAction === 'delete' && canDelete) {
            deleteFile(target);
        }
    });

    async function deleteFile(target) {
        const query = moduleKey ? ('?url=' + encodeURIComponent(moduleKey)) : '';
        let preview;

        try {
            const res = await fetch(previewUrl.replace('__ID__', encodeURIComponent(target.id)) + query, {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (!res.ok || !json.success) throw new Error(json.message || 'Could not load the file.');
            preview = json.preview;
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Cannot delete', text: err.message });
            return;
        }

        const fileNumber = preview.file_number || target.fileNumber;
        const counts = preview.counts || {};

        if (preview.blockers && preview.blockers.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete blocked',
                html: preview.blockers.map((b) => '<p class="text-sm">' + escapeHtml(b) + '</p>').join('')
            });
            return;
        }

        const { value: form } = await Swal.fire({
            icon: 'warning',
            title: 'Delete ' + escapeHtml(fileNumber) + '?',
            html: `
                <div class="text-left text-sm space-y-3">
                    <p>This removes the file's documents from Doc-WARE:</p>
                    <ul class="list-disc pl-5">
                        <li><strong>${counts.scannings || 0}</strong> scan record(s)</li>
                        <li><strong>${counts.pagetypings || 0}</strong> typed page(s)</li>
                        <li><strong>${counts.files_on_disk || 0}</strong> file(s) on disk, moved to the EDMS trash</li>
                    </ul>
                    ${preview.file_indexing_id ? '<p class="text-gray-600">The indexing record is kept.</p>' : '<p class="text-gray-600">This folder has no indexing record.</p>'}
                    <label class="block font-medium">Reason <span class="text-red-600">*</span></label>
                    <textarea id="doc-delete-reason" class="swal2-textarea" style="margin:0;width:100%" rows="3" placeholder="Why is this file being deleted?"></textarea>
                    <label class="block font-medium">Type <code>${escapeHtml(fileNumber)}</code> to confirm</label>
                    <input id="doc-delete-confirm" class="swal2-input" style="margin:0;width:100%" autocomplete="off">
                </div>`,
            showCancelButton: true,
            confirmButtonText: 'Delete File',
            confirmButtonColor: '#dc2626',
            focusCancel: true,
            preConfirm: () => {
                const reason = document.getElementById('doc-delete-reason').value.trim();
                const confirmNo = document.getElementById('doc-delete-confirm').value.trim();
                if (reason.length < 5) {
                    Swal.showValidationMessage('Enter a reason (at least 5 characters).');
                    return false;
                }
                if (confirmNo.toLowerCase() !== String(fileNumber).trim().toLowerCase()) {
                    Swal.showValidationMessage('The file number does not match.');
                    return false;
                }
                return { reason, confirm_file_number: confirmNo };
            }
        });

        if (!form) return;

        Swal.fire({ title: 'Deleting…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            const res = await fetch(deleteUrl.replace('__ID__', encodeURIComponent(target.id)) + query, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify(form)
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.success) {
                const firstError = json.errors ? Object.values(json.errors).flat()[0] : null;
                throw new Error(firstError || json.message || 'Delete failed.');
            }
            await Swal.fire({ icon: 'success', title: 'Deleted', text: json.message });
            window.location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Delete failed', text: err.message });
        }
    }
})();
</script>
