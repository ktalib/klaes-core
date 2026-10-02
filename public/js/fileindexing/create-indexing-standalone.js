(function () {
    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    onReady(function () {
        const overlay = document.getElementById('new-file-dialog-overlay');
        const pageWrapper = document.querySelector('.create-indexing-page .form-shell');

        if (overlay && overlay.classList.contains('hidden')) {
            overlay.classList.remove('hidden');
        }

        if (overlay && pageWrapper && overlay.parentElement !== pageWrapper) {
            pageWrapper.appendChild(overlay);
        }

        const dialogTitle = overlay ? overlay.querySelector('.dialog-title') : null;
        if (dialogTitle) {
            dialogTitle.classList.add('standalone-title');
        }

        const cancelBtn = document.getElementById('cancel-btn');
        if (cancelBtn) {
            cancelBtn.textContent = 'Reset Form';
        }

        const originalClose = window.closeFileIndexingDialog;
        if (typeof originalClose === 'function') {
            window.closeFileIndexingDialog = function () {
                originalClose();
                if (overlay) {
                    overlay.classList.remove('hidden');
                }
            };
        }

        const breadcrumb = document.querySelector('[data-create-indexing-breadcrumb]');
        if (breadcrumb) {
            const filenoDisplay = document.getElementById('file-number-display');
            if (filenoDisplay) {
                filenoDisplay.addEventListener('input', function () {
                    breadcrumb.textContent = this.value ? this.value : 'New File Index';
                });
            }
        }
    });
})();
