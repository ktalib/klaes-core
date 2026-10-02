/**
 * Simple PDF Viewer for EDMS Page Typing
 * Fallback solution when PDF.js is not available
 */

class SimplePDFViewer {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.currentPdf = null;
        this.currentPage = 1;
    }

    loadPDF(pdfUrl, pageNumber = 1) {
        this.currentPdf = pdfUrl;
        this.currentPage = pageNumber;
        
        // Clear container
        this.container.innerHTML = '';
        
        // Create iframe for PDF viewing
        const iframe = document.createElement('iframe');
        iframe.src = `${pdfUrl}#page=${pageNumber}&zoom=page-fit&toolbar=0&navpanes=0&scrollbar=0`;
        iframe.style.width = '100%';
        iframe.style.height = '500px';
        iframe.style.border = 'none';
        iframe.style.borderRadius = '0.5rem';
        iframe.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
        iframe.title = `PDF Page ${pageNumber}`;
        
        // Add loading indicator
        const loadingDiv = document.createElement('div');
        loadingDiv.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 500px; background: #f8fafc; border-radius: 0.5rem;">
                <div style="text-align: center; color: #718096;">
                    <div style="margin-bottom: 1rem;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14,2 14,8 20,8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10,9 9,9 8,9"></polyline>
                        </svg>
                    </div>
                    <p>Loading PDF...</p>
                </div>
            </div>
        `;
        
        this.container.appendChild(loadingDiv);
        
        // Handle iframe load
        iframe.onload = () => {
            this.container.removeChild(loadingDiv);
            this.container.appendChild(iframe);
        };
        
        // Handle iframe error
        iframe.onerror = () => {
            this.container.removeChild(loadingDiv);
            this.showError();
        };
        
        // Fallback timeout
        setTimeout(() => {
            if (this.container.contains(loadingDiv)) {
                this.container.removeChild(loadingDiv);
                this.container.appendChild(iframe);
            }
        }, 3000);
    }

    loadImage(imageUrl, altText) {
        this.container.innerHTML = '';
        
        const img = document.createElement('img');
        img.src = imageUrl;
        img.alt = altText;
        img.style.maxWidth = '100%';
        img.style.maxHeight = '500px';
        img.style.objectFit = 'contain';
        img.style.borderRadius = '0.5rem';
        img.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
        
        img.onerror = () => {
            this.showError('Unable to load image');
        };
        
        this.container.appendChild(img);
    }

    showError(message = 'Unable to load document') {
        this.container.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 500px; background: #f8fafc; border-radius: 0.5rem;">
                <div style="text-align: center; color: #718096;">
                    <div style="margin-bottom: 1rem;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                    </div>
                    <p>${message}</p>
                </div>
            </div>
        `;
    }

    showPlaceholder(text = 'Select a document to view') {
        this.container.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 500px; background: #f8fafc; border-radius: 0.5rem;">
                <div style="text-align: center; color: #718096;">
                    <div style="margin-bottom: 1rem;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14,2 14,8 20,8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10,9 9,9 8,9"></polyline>
                        </svg>
                    </div>
                    <p>${text}</p>
                </div>
            </div>
        `;
    }
}

// Export for use in other scripts
window.SimplePDFViewer = SimplePDFViewer;