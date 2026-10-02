/**
 * Global Smart Print Manager Helper
 * 
 * Usage:
 * window.SmartPrintManager.open('REF-123', 'Document Type', '/url/to/print');
 */
window.SmartPrintManager = {
    open(ref, type, url) {
        window.dispatchEvent(new CustomEvent('open-print-manager', { 
            detail: { ref, type, url } 
        }));
    }
};
